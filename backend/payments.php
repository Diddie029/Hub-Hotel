<?php
// api/payments.php - Payment Management API
include '../backend/config.php';

$action = isset($_GET['action']) ? $_GET['action'] : (isset($_POST['action']) ? $_POST['action'] : '');

if ($action === 'process_payment') {
    // Process payment for order
    $order_id = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
    $payment_method = isset($_POST['payment_method']) ? $_POST['payment_method'] : 'M-Pesa';
    $amount = isset($_POST['amount']) ? (float)$_POST['amount'] : 0;
    
    if ($order_id === 0 || $amount <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid order or amount']);
        exit;
    }
    
    // Get order details
    $orderQuery = "SELECT total_amount FROM orders WHERE id = ?";
    $orderStmt = $connection->prepare($orderQuery);
    $orderStmt->bind_param("i", $order_id);
    $orderStmt->execute();
    $orderResult = $orderStmt->get_result();
    
    if ($orderResult->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Order not found']);
        $orderStmt->close();
        exit;
    }
    
    $order = $orderResult->fetch_assoc();
    $orderStmt->close();
    
    // Verify amount matches order total
    if (abs($amount - $order['total_amount']) > 0.01) {
        echo json_encode(['success' => false, 'message' => 'Payment amount does not match order total']);
        exit;
    }
    
    // Simulate payment processing
    $transaction_id = 'TXN-' . time() . '-' . rand(1000, 9999);
    $status = 'Completed'; // In real system, would wait for M-Pesa callback
    
    $insertQuery = "INSERT INTO payments (order_id, amount, payment_method, transaction_id, status) VALUES (?, ?, ?, ?, ?)";
    $stmt = $connection->prepare($insertQuery);
    $stmt->bind_param("idsss", $order_id, $amount, $payment_method, $transaction_id, $status);
    
    if ($stmt->execute()) {
        // Update order status to Cooking
        $orderUpdateQuery = "UPDATE orders SET status = 'Cooking' WHERE id = ?";
        $orderUpdateStmt = $connection->prepare($orderUpdateQuery);
        $orderUpdateStmt->bind_param("i", $order_id);
        $orderUpdateStmt->execute();
        $orderUpdateStmt->close();
        
        logAction($connection, 'Payment', 'Payment processed', "Order #$order_id - Amount: KES $amount - Method: $payment_method", 'Customer', $order_id, 'Success');
        
        echo json_encode([
            'success' => true,
            'message' => 'Payment processed successfully',
            'transaction_id' => $transaction_id,
            'amount' => $amount
        ]);
    } else {
        logAction($connection, 'Payment', 'Payment failed', "Failed to process payment for order #$order_id", 'Customer', $order_id, 'Error');
        echo json_encode(['success' => false, 'message' => 'Error processing payment']);
    }
    $stmt->close();
    
} elseif ($action === 'get_payments') {
    // Get all payments
    $order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
    
    $query = "SELECT * FROM payments";
    
    if ($order_id > 0) {
        $query .= " WHERE order_id = $order_id";
    }
    
    $query .= " ORDER BY created_at DESC";
    
    $result = $connection->query($query);
    $payments = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $payments[] = $row;
        }
    }
    
    echo json_encode(['success' => true, 'payments' => $payments]);
    
} elseif ($action === 'get_payment') {
    // Get single payment
    $payment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    $query = "SELECT * FROM payments WHERE id = ?";
    $stmt = $connection->prepare($query);
    $stmt->bind_param("i", $payment_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $payment = $result->fetch_assoc();
        echo json_encode(['success' => true, 'payment' => $payment]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Payment not found']);
    }
    $stmt->close();
}

closeConnection($connection);
?>
