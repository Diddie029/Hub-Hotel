<?php
// api/orders.php - Order Management API
include '../backend/config.php';

$action = isset($_GET['action']) ? $_GET['action'] : (isset($_POST['action']) ? $_POST['action'] : '');

if ($action === 'create_order') {
    // Create new order
    $table_id = isset($_POST['table_id']) ? $_POST['table_id'] : '';
    $order_type = isset($_POST['order_type']) ? $_POST['order_type'] : 'Eat-in';
    $items = isset($_POST['items']) ? json_decode($_POST['items'], true) : [];
    $customer_name = isset($_POST['customer_name']) ? $_POST['customer_name'] : null;
    $customer_phone = isset($_POST['customer_phone']) ? $_POST['customer_phone'] : null;
    
    if (empty($table_id) || empty($items)) {
        echo json_encode(['success' => false, 'message' => 'Table ID and items are required']);
        exit;
    }
    
    $connection->begin_transaction();
    try {
        // Check if table exists and is active
        $tableCheck = $connection->prepare("SELECT id FROM table_monitors WHERE table_id = ? AND status = 'Active'");
        $tableCheck->bind_param("s", $table_id);
        $tableCheck->execute();
        if ($tableCheck->get_result()->num_rows == 0) {
            throw new Exception('Table not found or inactive');
        }
        $tableCheck->close();
        
        // Get or create customer
        $customer_id = null;
        if ($customer_phone) {
            $customerCheck = $connection->prepare("SELECT id FROM customers WHERE phone_number = ?");
            $customerCheck->bind_param("s", $customer_phone);
            $customerCheck->execute();
            $result = $customerCheck->get_result();
            
            if ($result->num_rows > 0) {
                $customer_id = $result->fetch_assoc()['id'];
                // Update customer name if provided
                if ($customer_name) {
                    $updateCustomer = $connection->prepare("UPDATE customers SET name = ? WHERE id = ?");
                    $updateCustomer->bind_param("si", $customer_name, $customer_id);
                    $updateCustomer->execute();
                    $updateCustomer->close();
                }
            } else {
                $insertCustomer = $connection->prepare("INSERT INTO customers (phone_number, name) VALUES (?, ?)");
                $insertCustomer->bind_param("ss", $customer_phone, $customer_name);
                $insertCustomer->execute();
                $customer_id = $connection->insert_id;
                $insertCustomer->close();
            }
            $customerCheck->close();
        } elseif ($customer_name) {
            // Create customer with name only (no phone)
            $insertCustomer = $connection->prepare("INSERT INTO customers (name, phone_number) VALUES (?, ?)");
            $emptyPhone = null;
            $insertCustomer->bind_param("ss", $customer_name, $emptyPhone);
            $insertCustomer->execute();
            $customer_id = $connection->insert_id;
            $insertCustomer->close();
        }
        
        // Calculate total amount
        $total_amount = 0;
        foreach ($items as $item) {
            $total_amount += $item['price'] * $item['quantity'];
        }
        
        // Create order
        $order_number = generateOrderNumber($connection);
        $status = 'Pending';
        $priority = 'Medium';
        $estimated_time = 20;
        
        $insertOrder = $connection->prepare(
            "INSERT INTO orders (order_number, customer_id, table_id, order_type, status, total_amount, priority, estimated_preparation_time) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $insertOrder->bind_param("sisssidi", $order_number, $customer_id, $table_id, $order_type, $status, $total_amount, $priority, $estimated_time);
        $insertOrder->execute();
        $order_id = $connection->insert_id;
        $insertOrder->close();
        
        // Insert order items
        $insertItem = $connection->prepare(
            "INSERT INTO order_items (order_id, menu_id, quantity, unit_price, subtotal) VALUES (?, ?, ?, ?, ?)"
        );
        
        foreach ($items as $item) {
            $menu_id = $item['id'];
            $quantity = $item['quantity'];
            $unit_price = $item['price'];
            $subtotal = $unit_price * $quantity;
            $insertItem->bind_param("iiidd", $order_id, $menu_id, $quantity, $unit_price, $subtotal);
            $insertItem->execute();
        }
        $insertItem->close();
        
        // AUTO-ASSIGN AN IDLE ROBOT
        $robotQuery = "SELECT id, robot_id FROM robots WHERE status = 'Idle' ORDER BY battery_level DESC LIMIT 1";
        $robotResult = $connection->query($robotQuery);
        $assigned_robot = null;
        $assigned_robot_id = null;
        
        if ($robotResult && $robotResult->num_rows > 0) {
            $robot = $robotResult->fetch_assoc();
            $assigned_robot_id = $robot['id'];
            $assigned_robot = $robot['robot_id'];
            
            // Update robot status to Delivering (changed from Moving to Delivering for demo)
            $updateRobot = $connection->prepare("UPDATE robots SET status = 'Delivering', current_task = ? WHERE id = ?");
            $updateRobot->bind_param("ii", $order_id, $assigned_robot_id);
            $updateRobot->execute();
            $updateRobot->close();
            
            // Update order with robot assignment
            $updateOrder = $connection->prepare("UPDATE orders SET assigned_robot_id = ? WHERE id = ?");
            $updateOrder->bind_param("ii", $assigned_robot_id, $order_id);
            $updateOrder->execute();
            $updateOrder->close();
        }
        
        // Log action
        logAction($connection, 'Order', 'Order created', "Order #$order_number created for table $table_id" . ($assigned_robot ? " - Assigned to $assigned_robot" : ""), 'Customer', $order_id, 'Success');
        
        $connection->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Order created successfully' . ($assigned_robot ? " - Robot $assigned_robot assigned!" : ""),
            'order_id' => $order_id,
            'order_number' => $order_number,
            'total_amount' => $total_amount,
            'customer_id' => $customer_id,
            'assigned_robot' => $assigned_robot,
            'assigned_robot_id' => $assigned_robot_id
        ]);
        
    } catch (Exception $e) {
        $connection->rollback();
        logAction($connection, 'Order', 'Order creation failed', $e->getMessage(), 'System', null, 'Error');
        echo json_encode(['success' => false, 'message' => 'Error creating order: ' . $e->getMessage()]);
    }
    
} elseif ($action === 'get_orders') {
    // Get all orders - optionally filtered by table_id or status
    $status = isset($_GET['status']) ? $_GET['status'] : '';
    $table_id = isset($_GET['table_id']) ? $_GET['table_id'] : '';
    
    $query = "SELECT o.*, tm.location_name, r.robot_id as assigned_robot, c.name as customer_name FROM orders o 
              LEFT JOIN table_monitors tm ON o.table_id = tm.table_id
              LEFT JOIN robots r ON o.assigned_robot_id = r.id
              LEFT JOIN customers c ON o.customer_id = c.id";
    
    $conditions = [];
    if (!empty($status)) {
        $conditions[] = "o.status = '" . $connection->real_escape_string($status) . "'";
    }
    if (!empty($table_id)) {
        $conditions[] = "o.table_id = '" . $connection->real_escape_string($table_id) . "'";
    }
    
    if (!empty($conditions)) {
        $query .= " WHERE " . implode(" AND ", $conditions);
    }
    
    $query .= " ORDER BY o.created_at DESC LIMIT 100";
    
    $result = $connection->query($query);
    $orders = [];
    $orderIds = [];
    
    if ($result) {
        // First pass: collect all order IDs and basic data
        while ($row = $result->fetch_assoc()) {
            $orders[] = $row;
            $orderIds[] = $row['id'];
        }
    }
    
    // Fetch all items for all orders in one query (avoid N+1)
    if (!empty($orderIds)) {
        $idsStr = implode(',', array_map('intval', $orderIds));
        $itemsQuery = "SELECT oi.*, m.name FROM order_items oi 
                      LEFT JOIN menu m ON oi.menu_id = m.id 
                      WHERE oi.order_id IN ($idsStr)";
        $itemsResult = $connection->query($itemsQuery);
        
        $itemsByOrder = [];
        if ($itemsResult) {
            while ($item = $itemsResult->fetch_assoc()) {
                $order_id = $item['order_id'];
                if (!isset($itemsByOrder[$order_id])) {
                    $itemsByOrder[$order_id] = [];
                }
                $itemsByOrder[$order_id][] = $item;
            }
        }
        
        // Attach items to orders
        foreach ($orders as &$order) {
            $order['items'] = $itemsByOrder[$order['id']] ?? [];
        }
    }
    
    echo json_encode(['success' => true, 'orders' => $orders]);
    
} elseif ($action === 'get_order') {
    // Get single order
    $order_id = isset($_GET['id']) ? $_GET['id'] : 0;
    
    $query = "SELECT o.*, tm.location_name FROM orders o 
              LEFT JOIN table_monitors tm ON o.table_id = tm.table_id 
              WHERE o.id = ?";
    
    $stmt = $connection->prepare($query);
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $order = $result->fetch_assoc();
        
        // Get order items
        $itemsQuery = "SELECT oi.*, m.name FROM order_items oi 
                      LEFT JOIN menu m ON oi.menu_id = m.id 
                      WHERE oi.order_id = ?";
        $itemsStmt = $connection->prepare($itemsQuery);
        $itemsStmt->bind_param("i", $order_id);
        $itemsStmt->execute();
        $itemsResult = $itemsStmt->get_result();
        
        $items = [];
        while ($item = $itemsResult->fetch_assoc()) {
            $items[] = $item;
        }
        $itemsStmt->close();
        
        $order['items'] = $items;
        echo json_encode(['success' => true, 'order' => $order]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Order not found']);
    }
    $stmt->close();
    
} elseif ($action === 'update_status') {
    // Update order status
    $order_id = isset($_POST['order_id']) ? $_POST['order_id'] : 0;
    $status = isset($_POST['status']) ? $_POST['status'] : '';
    
    $updateQuery = "UPDATE orders SET status = ? WHERE id = ?";
    $stmt = $connection->prepare($updateQuery);
    $stmt->bind_param("si", $status, $order_id);
    
    if ($stmt->execute()) {
        logAction($connection, 'Order', 'Order status updated', "Order #$order_id status changed to $status", 'System', $order_id, 'Success');
        echo json_encode(['success' => true, 'message' => 'Order status updated']);
    } else {
        logAction($connection, 'Order', 'Status update failed', "Failed to update order #$order_id", 'System', $order_id, 'Error');
        echo json_encode(['success' => false, 'message' => 'Error updating order status']);
    }
    $stmt->close();

} elseif ($action === 'simulate_delivery') {
    // Simulate robot delivery - mark order as delivered and free up robot
    $order_id = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
    
    if ($order_id === 0) {
        echo json_encode(['success' => false, 'message' => 'Order ID is required']);
        exit;
    }
    
    $connection->begin_transaction();
    try {
        // Get order with robot assignment
        $getOrder = $connection->prepare("SELECT assigned_robot_id, order_number FROM orders WHERE id = ?");
        $getOrder->bind_param("i", $order_id);
        $getOrder->execute();
        $orderResult = $getOrder->get_result();
        
        if ($orderResult->num_rows === 0) {
            throw new Exception('Order not found');
        }
        
        $order = $orderResult->fetch_assoc();
        $robot_id = $order['assigned_robot_id'];
        $order_number = $order['order_number'];
        $getOrder->close();
        
        // Update order status to Delivered
        $updateOrder = $connection->prepare("UPDATE orders SET status = 'Delivered' WHERE id = ?");
        $updateOrder->bind_param("i", $order_id);
        $updateOrder->execute();
        $updateOrder->close();
        
        // Return robot to Idle status
        if ($robot_id) {
            $updateRobot = $connection->prepare("UPDATE robots SET status = 'Idle', current_task = NULL WHERE id = ?");
            $updateRobot->bind_param("i", $robot_id);
            $updateRobot->execute();
            $updateRobot->close();
        }
        
        logAction($connection, 'Delivery', 'Order delivered', "Order #$order_number delivered successfully", 'System', $order_id, 'Success');
        
        $connection->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Order marked as delivered',
            'order_id' => $order_id
        ]);
        
    } catch (Exception $e) {
        $connection->rollback();
        logAction($connection, 'Delivery', 'Delivery simulation failed', $e->getMessage(), 'System', $order_id, 'Error');
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }

} elseif ($action === 'auto_progress_status') {
    // Automatically progress order statuses based on time
    // Pending (0s) -> Cooking (5s) -> Ready (15s) -> Delivering (manual via robot assignment)
    // Once Delivering, the frontend handles the 20-second timer and calls simulate_delivery
    
    $query = "SELECT id, status, created_at FROM orders WHERE status IN ('Pending', 'Cooking', 'Ready')";
    $result = $connection->query($query);
    
    if ($result) {
        $currentTime = time();
        $progressedCount = 0;
        
        while ($order = $result->fetch_assoc()) {
            $orderId = $order['id'];
            $status = $order['status'];
            $createdTime = strtotime($order['created_at']);
            $elapsedSeconds = $currentTime - $createdTime;
            
            $newStatus = $status;
            
            // Progress based on elapsed time (only for pre-delivery stages)
            if ($status === 'Pending' && $elapsedSeconds >= 5) {
                $newStatus = 'Cooking';
            } elseif ($status === 'Cooking' && $elapsedSeconds >= 15) {
                $newStatus = 'Ready';
            }
            // Note: "Delivering" status progression is handled by frontend 20-second timer via simulate_delivery API
            
            // Update if status changed
            if ($newStatus !== $status) {
                $updateStmt = $connection->prepare("UPDATE orders SET status = ? WHERE id = ?");
                $updateStmt->bind_param("si", $newStatus, $orderId);
                $updateStmt->execute();
                $updateStmt->close();
                $progressedCount++;
            }
        }
    }
    
    // Sync robot statuses based on updated order statuses
    $robotSyncQuery = "SELECT r.id, o.id as order_id, o.status as order_status 
                       FROM robots r 
                       LEFT JOIN orders o ON r.current_task = o.id 
                       WHERE r.current_task IS NOT NULL AND r.status != 'Charging'";
    
    $robotSyncResult = $connection->query($robotSyncQuery);
    $robotSyncCount = 0;
    
    if ($robotSyncResult) {
        while ($row = $robotSyncResult->fetch_assoc()) {
            $robotId = $row['id'];
            $orderStatus = $row['order_status'];
            
            $newRobotStatus = null;
            
            if ($orderStatus === 'Delivered') {
                $newRobotStatus = 'Idle';
                $updateStmt = $connection->prepare("UPDATE robots SET status = ?, current_task = NULL WHERE id = ?");
                $updateStmt->bind_param("si", $newRobotStatus, $robotId);
                $updateStmt->execute();
                $updateStmt->close();
                $robotSyncCount++;
            } elseif ($orderStatus === 'Ready' || $orderStatus === 'Delivering') {
                $newRobotStatus = 'Delivering';
                $updateStmt = $connection->prepare("UPDATE robots SET status = ? WHERE id = ? AND status != ?");
                $updateStmt->bind_param("sis", $newRobotStatus, $robotId, $newRobotStatus);
                $updateStmt->execute();
                $updateStmt->close();
                $robotSyncCount++;
            } elseif ($orderStatus === 'Pending' || $orderStatus === 'Cooking') {
                $newRobotStatus = 'Moving';
                $updateStmt = $connection->prepare("UPDATE robots SET status = ? WHERE id = ? AND status != ?");
                $updateStmt->bind_param("sis", $newRobotStatus, $robotId, $newRobotStatus);
                $updateStmt->execute();
                $updateStmt->close();
                $robotSyncCount++;
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Order statuses auto-progressed and robot statuses synced',
        'progressed_count' => $progressedCount ?? 0,
        'robot_synced_count' => $robotSyncCount ?? 0
    ]);

} elseif ($action === 'auto_deliver_all') {
    // Deliver all pending/active orders (for testing)
    $query = "SELECT id FROM orders WHERE status IN ('Pending', 'Cooking', 'Ready', 'Delivering')";
    $result = $connection->query($query);
    
    $deliveredCount = 0;
    while ($order = $result->fetch_assoc()) {
        $updateStmt = $connection->prepare("UPDATE orders SET status = 'Delivered' WHERE id = ?");
        $updateStmt->bind_param("i", $order['id']);
        $updateStmt->execute();
        $updateStmt->close();
        $deliveredCount++;
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'All orders marked as delivered',
        'delivered_count' => $deliveredCount
    ]);

} elseif ($action === 'getStatus') {
    // Real-time order status tracking - used by landing page
    $trackId = isset($_GET['trackId']) ? $_GET['trackId'] : '';
    
    if (empty($trackId)) {
        echo json_encode(['status' => 'error', 'message' => 'Track ID is required']);
        exit;
    }
    
    // Check if it's a table ID or order number
    $query = "SELECT id, status, table_id, order_number, assigned_robot_id, created_at FROM orders 
              WHERE table_id = ? OR order_number = ? OR id = ?
              ORDER BY created_at DESC LIMIT 1";
    
    $stmt = $connection->prepare($query);
    $trackId2 = $trackId;
    $trackId3 = (int)$trackId;
    $stmt->bind_param("ssi", $trackId, $trackId2, $trackId3);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $order = $result->fetch_assoc();
        
        // Map order status to tracking status for display
        $statusMap = [
            'Pending' => 'placed',
            'Cooking' => 'processing',
            'Ready' => 'assigned',
            'Delivering' => 'delivery',
            'Delivered' => 'delivered'
        ];
        
        $displayStatus = $statusMap[$order['status']] ?? $order['status'];
        
        // Get robot info if assigned
        $robotInfo = null;
        if ($order['assigned_robot_id']) {
            $robotQuery = "SELECT robot_id, status, battery_level, current_location FROM robots WHERE id = ?";
            $robotStmt = $connection->prepare($robotQuery);
            $robotStmt->bind_param("i", $order['assigned_robot_id']);
            $robotStmt->execute();
            $robotResult = $robotStmt->get_result();
            
            if ($robotResult->num_rows > 0) {
                $robotInfo = $robotResult->fetch_assoc();
            }
            $robotStmt->close();
        }
        
        echo json_encode([
            'status' => 'success',
            'orderStatus' => $displayStatus,
            'order' => [
                'id' => $order['id'],
                'number' => $order['order_number'],
                'table' => $order['table_id'],
                'status' => $order['status'],
                'displayStatus' => $displayStatus,
                'createdAt' => $order['created_at']
            ],
            'robot' => $robotInfo
        ]);
    } else {
        echo json_encode([
            'status' => 'not_found',
            'message' => 'Order not found',
            'trackId' => $trackId
        ]);
    }
    $stmt->close();

}

closeConnection($connection);
?>
