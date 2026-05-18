<?php
// api/robots.php - Robot Management API
include '../backend/config.php';

$action = isset($_GET['action']) ? $_GET['action'] : (isset($_POST['action']) ? $_POST['action'] : '');

if ($action === 'add_robot') {
    // Add new robot
    $robot_id = isset($_POST['robot_id']) ? $_POST['robot_id'] : '';
    
    if (empty($robot_id)) {
        echo json_encode(['success' => false, 'message' => 'Robot ID is required']);
        exit;
    }
    
    // Check if robot already exists
    $checkQuery = "SELECT id FROM robots WHERE robot_id = ?";
    $checkStmt = $connection->prepare($checkQuery);
    $checkStmt->bind_param("s", $robot_id);
    $checkStmt->execute();
    
    if ($checkStmt->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Robot ID already exists']);
        $checkStmt->close();
        exit;
    }
    $checkStmt->close();
    
    // Insert robot
    $status = 'Idle';
    $battery = 100;
    $posX = 0;
    $posY = 0;
    
    $insertQuery = "INSERT INTO robots (robot_id, status, battery_level, position_x, position_y) VALUES (?, ?, ?, ?, ?)";
    $stmt = $connection->prepare($insertQuery);
    $stmt->bind_param("ssiii", $robot_id, $status, $battery, $posX, $posY);
    
    if ($stmt->execute()) {
        logAction($connection, 'Robot', 'Robot added', "Robot $robot_id added to system", 'Admin', $connection->insert_id, 'Success');
        echo json_encode([
            'success' => true,
            'message' => 'Robot added successfully',
            'robot_id' => $connection->insert_id,
            'robot_code' => $robot_id
        ]);
    } else {
        logAction($connection, 'Robot', 'Robot addition failed', "Failed to add robot $robot_id", 'Admin', null, 'Error');
        echo json_encode(['success' => false, 'message' => 'Error adding robot']);
    }
    $stmt->close();
    
} elseif ($action === 'get_robots') {
    // Get all robots
    $status = isset($_GET['status']) ? $_GET['status'] : '';
    
    $query = "SELECT * FROM robots";
    
    if (!empty($status)) {
        $query .= " WHERE status = '" . $connection->real_escape_string($status) . "'";
    }
    
    $query .= " ORDER BY status ASC, battery_level DESC LIMIT 100";
    
    $result = $connection->query($query);
    $robots = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $robots[] = $row;
        }
    }
    
    echo json_encode(['success' => true, 'robots' => $robots]);
    
} elseif ($action === 'get_robot') {
    // Get single robot
    $robot_id = isset($_GET['id']) ? $_GET['id'] : 0;
    
    $query = "SELECT * FROM robots WHERE id = ?";
    $stmt = $connection->prepare($query);
    $stmt->bind_param("i", $robot_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $robot = $result->fetch_assoc();
        echo json_encode(['success' => true, 'robot' => $robot]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Robot not found']);
    }
    $stmt->close();
    
} elseif ($action === 'update_robot_status') {
    // Update robot status
    $robot_id = isset($_POST['robot_id']) ? $_POST['robot_id'] : 0;
    $status = isset($_POST['status']) ? $_POST['status'] : '';
    
    $updateQuery = "UPDATE robots SET status = ? WHERE id = ?";
    $stmt = $connection->prepare($updateQuery);
    $stmt->bind_param("si", $status, $robot_id);
    
    if ($stmt->execute()) {
        logAction($connection, 'Robot', 'Robot status updated', "Robot #$robot_id status changed to $status", 'Admin', $robot_id, 'Success');
        echo json_encode(['success' => true, 'message' => 'Robot status updated']);
    } else {
        logAction($connection, 'Robot', 'Status update failed', "Failed to update robot #$robot_id status", 'Admin', $robot_id, 'Error');
        echo json_encode(['success' => false, 'message' => 'Error updating robot status']);
    }
    $stmt->close();
    
} elseif ($action === 'update_battery') {
    // Update robot battery level
    $robot_id = isset($_POST['robot_id']) ? $_POST['robot_id'] : 0;
    $battery = isset($_POST['battery']) ? (int)$_POST['battery'] : 0;
    
    // Auto-set to Charging if battery < 20%
    $status = $battery < 20 ? 'Charging' : null;
    
    if ($status) {
        $updateQuery = "UPDATE robots SET battery_level = ?, status = ? WHERE id = ?";
        $stmt = $connection->prepare($updateQuery);
        $stmt->bind_param("isi", $battery, $status, $robot_id);
    } else {
        $updateQuery = "UPDATE robots SET battery_level = ? WHERE id = ?";
        $stmt = $connection->prepare($updateQuery);
        $stmt->bind_param("ii", $battery, $robot_id);
    }
    
    if ($stmt->execute()) {
        logAction($connection, 'Robot', 'Battery updated', "Robot #$robot_id battery: $battery%", 'System', $robot_id, 'Success');
        echo json_encode(['success' => true, 'message' => 'Battery updated']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error updating battery']);
    }
    $stmt->close();
    
} elseif ($action === 'assign_to_order') {
    // Assign robot to order
    $robot_id = isset($_POST['robot_id']) ? $_POST['robot_id'] : 0;
    $order_id = isset($_POST['order_id']) ? $_POST['order_id'] : 0;
    
    if ($robot_id === 0 || $order_id === 0) {
        echo json_encode(['success' => false, 'message' => 'Robot ID and Order ID are required']);
        exit;
    }
    
    // Get robot info
    $robotQuery = "SELECT * FROM robots WHERE id = ?";
    $robotStmt = $connection->prepare($robotQuery);
    $robotStmt->bind_param("i", $robot_id);
    $robotStmt->execute();
    $robotResult = $robotStmt->get_result();
    
    if ($robotResult->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Robot not found']);
        $robotStmt->close();
        exit;
    }
    
    $robot = $robotResult->fetch_assoc();
    $robotStmt->close();
    
    // Check if robot can be assigned (battery >= 20% and idle)
    if ($robot['battery_level'] < 20) {
        echo json_encode(['success' => false, 'message' => 'Robot battery too low for assignment']);
        exit;
    }
    
    if ($robot['status'] !== 'Idle') {
        echo json_encode(['success' => false, 'message' => 'Robot is not idle']);
        exit;
    }
    
    // Update order with robot assignment
    $assignStatus = 'Assigned to robot';
    $movingStatus = 'Moving';
    $updateQuery = "UPDATE orders SET assigned_robot_id = ?, status = ? WHERE id = ?";
    $updateStmt = $connection->prepare($updateQuery);
    $updateStmt->bind_param("isi", $robot_id, $assignStatus, $order_id);
    
    if ($updateStmt->execute()) {
        // Update robot status
        $robotUpdateQuery = "UPDATE robots SET status = ?, current_task = ? WHERE id = ?";
        $robotUpdateStmt = $connection->prepare($robotUpdateQuery);
        $robotUpdateStmt->bind_param("sii", $movingStatus, $order_id, $robot_id);
        $robotUpdateStmt->execute();
        $robotUpdateStmt->close();
        
        // Log the action
        logAction($connection, 'Robot Assignment', 'Robot assigned to order', "Robot #$robot_id assigned to Order #$order_id", 'Admin', $order_id, 'Success');
        
        echo json_encode([
            'success' => true,
            'message' => 'Robot assigned to order successfully',
            'robot_id' => $robot_id,
            'order_id' => $order_id
        ]);
    } else {
        logAction($connection, 'Robot Assignment', 'Assignment failed', "Failed to assign robot #$robot_id to order #$order_id", 'Admin', $order_id, 'Error');
        echo json_encode(['success' => false, 'message' => 'Error assigning robot to order']);
    }
    $updateStmt->close();
    
} elseif ($action === 'toggle_power') {
    // Toggle robot power on/off
    $robot_id = isset($_POST['robot_id']) ? (int)$_POST['robot_id'] : 0;
    $is_powered = isset($_POST['is_powered']) ? (int)$_POST['is_powered'] : 0;
    
    if ($robot_id === 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid robot ID']);
        exit;
    }

    // First, check if is_powered column exists
    $checkColumnQuery = "SHOW COLUMNS FROM robots LIKE 'is_powered'";
    $checkResult = $connection->query($checkColumnQuery);
    
    if ($checkResult->num_rows === 0) {
        // Column doesn't exist, create it
        $alterQuery = "ALTER TABLE robots ADD COLUMN is_powered TINYINT DEFAULT 1";
        if (!$connection->query($alterQuery)) {
            logAction($connection, 'Robot', 'Power toggle failed', "Failed to add is_powered column", 'Admin', $robot_id, 'Error');
            echo json_encode(['success' => false, 'message' => 'Database error']);
            exit;
        }
    }
    
    // Update robot power status
    $updateQuery = "UPDATE robots SET is_powered = ? WHERE id = ?";
    $stmt = $connection->prepare($updateQuery);
    $stmt->bind_param("ii", $is_powered, $robot_id);
    
    if ($stmt->execute()) {
        $statusText = $is_powered ? 'powered ON' : 'powered OFF';
        logAction($connection, 'Robot', 'Robot power toggled', "Robot #$robot_id $statusText", 'Admin', $robot_id, 'Success');
        echo json_encode([
            'success' => true,
            'message' => "Robot $statusText successfully",
            'is_powered' => $is_powered
        ]);
    } else {
        logAction($connection, 'Robot', 'Power toggle failed', "Failed to toggle robot #$robot_id power", 'Admin', $robot_id, 'Error');
        echo json_encode(['success' => false, 'message' => 'Error toggling robot power']);
    }
    $stmt->close();

} elseif ($action === 'delete_robot') {
    // Delete robot
    $robot_id = isset($_POST['robot_id']) ? $_POST['robot_id'] : 0;
    
    $deleteQuery = "DELETE FROM robots WHERE id = ?";
    $stmt = $connection->prepare($deleteQuery);
    $stmt->bind_param("i", $robot_id);
    
    if ($stmt->execute()) {
        logAction($connection, 'Robot', 'Robot removed', "Robot #$robot_id removed from system", 'Admin', $robot_id, 'Success');
        echo json_encode(['success' => true, 'message' => 'Robot deleted successfully']);
    } else {
        logAction($connection, 'Robot', 'Robot removal failed', "Failed to remove robot #$robot_id", 'Admin', $robot_id, 'Error');
        echo json_encode(['success' => false, 'message' => 'Error deleting robot']);
    }
    $stmt->close();

} elseif ($action === 'get_robots_with_assignments') {
    // Get robots with their current order assignments
    $query = "SELECT r.*, o.order_number, o.status as order_status, o.table_id 
              FROM robots r 
              LEFT JOIN orders o ON r.current_task = o.id
              ORDER BY r.status ASC, r.battery_level DESC";
    
    $result = $connection->query($query);
    $robots = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $robots[] = $row;
        }
    }
    
    echo json_encode(['success' => true, 'robots' => $robots]);

} elseif ($action === 'sync_robot_status') {
    // Synchronize robot statuses based on order progress
    // Robots follow orders: Idle -> Moving (Pending/Cooking) -> Delivering (Ready/Delivering) -> Idle (Delivered/Cancelled)
    
    $query = "SELECT r.id, o.id as order_id, o.status as order_status 
              FROM robots r 
              LEFT JOIN orders o ON r.current_task = o.id 
              WHERE r.current_task IS NOT NULL AND r.status != 'Charging'";
    
    $result = $connection->query($query);
    $syncedCount = 0;
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $robotId = $row['id'];
            $orderStatus = $row['order_status'];
            $orderId = $row['order_id'];
            
            $newRobotStatus = null;
            
            if ($orderStatus === 'Delivered') {
                // Order completed, return robot to Idle
                $newRobotStatus = 'Idle';
                
                // Update robot
                $updateStmt = $connection->prepare("UPDATE robots SET status = ?, current_task = NULL WHERE id = ?");
                $updateStmt->bind_param("si", $newRobotStatus, $robotId);
                $updateStmt->execute();
                $updateStmt->close();
                $syncedCount++;
            } elseif ($orderStatus === 'Ready' || $orderStatus === 'Delivering') {
                // Order ready/delivering, robot should be Delivering
                $robotStatus = 'Delivering';
                $updateStmt = $connection->prepare("UPDATE robots SET status = ? WHERE id = ? AND status != ?");
                $updateStmt->bind_param("sis", $robotStatus, $robotId, $robotStatus);
                $updateStmt->execute();
                $updateStmt->close();
                $syncedCount++;
            } elseif ($orderStatus === 'Pending' || $orderStatus === 'Cooking') {
                // Order pending/cooking, robot should be Moving
                $robotStatus = 'Moving';
                $updateStmt = $connection->prepare("UPDATE robots SET status = ? WHERE id = ? AND status != ?");
                $updateStmt->bind_param("sis", $robotStatus, $robotId, $robotStatus);
                $updateStmt->execute();
                $updateStmt->close();
                $syncedCount++;
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Robot statuses synchronized',
        'synced_count' => $syncedCount
    ]);
}

closeConnection($connection);
?>
