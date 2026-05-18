<?php
// api/reviews.php - Reviews and Loyalty API
include '../backend/config.php';

$action = isset($_GET['action']) ? $_GET['action'] : (isset($_POST['action']) ? $_POST['action'] : '');

if ($action === 'submit_review') {
    // Submit review for order
    $order_id = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
    $customer_id = isset($_POST['customer_id']) ? (int)$_POST['customer_id'] : 0;
    $rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
    $comment = isset($_POST['comment']) ? $_POST['comment'] : '';
    
    if ($order_id === 0 || $rating < 1 || $rating > 5) {
        echo json_encode(['success' => false, 'message' => 'Invalid order or rating']);
        exit;
    }
    
    // Check if review already exists
    $checkQuery = "SELECT id FROM reviews WHERE order_id = ?";
    $checkStmt = $connection->prepare($checkQuery);
    $checkStmt->bind_param("i", $order_id);
    $checkStmt->execute();
    
    if ($checkStmt->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Review already submitted for this order']);
        $checkStmt->close();
        exit;
    }
    $checkStmt->close();
    
    // Insert review
    $insertQuery = "INSERT INTO reviews (order_id, customer_id, rating, comment) VALUES (?, ?, ?, ?)";
    $stmt = $connection->prepare($insertQuery);
    $stmt->bind_param("iiis", $order_id, $customer_id, $rating, $comment);
    
    if ($stmt->execute()) {
        // Award loyalty points (1 point per KES spent)
        if ($customer_id > 0) {
            $orderQuery = "SELECT total_amount FROM orders WHERE id = ?";
            $orderStmt = $connection->prepare($orderQuery);
            $orderStmt->bind_param("i", $order_id);
            $orderStmt->execute();
            $orderResult = $orderStmt->get_result();
            
            if ($orderResult->num_rows > 0) {
                $order = $orderResult->fetch_assoc();
                $points = (int)$order['total_amount'];
                
                $updateCustomerQuery = "UPDATE customers SET loyalty_points = loyalty_points + ?, total_spent = total_spent + ? WHERE id = ?";
                $updateCustomerStmt = $connection->prepare($updateCustomerQuery);
                $updateCustomerStmt->bind_param("idi", $points, $order['total_amount'], $customer_id);
                $updateCustomerStmt->execute();
                $updateCustomerStmt->close();
            }
            $orderStmt->close();
        }
        
        logAction($connection, 'Review', 'Review submitted', "Order #$order_id - Rating: $rating stars", 'Customer', $order_id, 'Success');
        
        echo json_encode([
            'success' => true,
            'message' => 'Review submitted successfully',
            'loyalty_points' => isset($order) ? (int)$order['total_amount'] : 0
        ]);
    } else {
        logAction($connection, 'Review', 'Review submission failed', "Failed to submit review for order #$order_id", 'Customer', $order_id, 'Error');
        echo json_encode(['success' => false, 'message' => 'Error submitting review']);
    }
    $stmt->close();
    
} elseif ($action === 'get_reviews') {
    // Get all reviews
    $order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
    
    $query = "SELECT r.*, c.phone_number FROM reviews r 
              LEFT JOIN customers c ON r.customer_id = c.id";
    
    if ($order_id > 0) {
        $query .= " WHERE r.order_id = $order_id";
    }
    
    $query .= " ORDER BY r.created_at DESC";
    
    $result = $connection->query($query);
    $reviews = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $reviews[] = $row;
        }
    }
    
    echo json_encode(['success' => true, 'reviews' => $reviews]);

} elseif ($action === 'get_reviews_for_table') {
    // Get reviews for orders from a specific table
    $table_id = isset($_GET['table_id']) ? $_GET['table_id'] : '';
    
    $query = "SELECT r.* FROM reviews r
              INNER JOIN orders o ON r.order_id = o.id
              WHERE o.table_id = ?
              ORDER BY r.created_at DESC";
    
    $stmt = $connection->prepare($query);
    $stmt->bind_param("s", $table_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $reviews = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $reviews[] = $row;
        }
    }
    
    echo json_encode(['success' => true, 'reviews' => $reviews]);
    $stmt->close();
    
} elseif ($action === 'get_customer_loyalty') {
    // Get customer loyalty info
    $customer_id = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : 0;
    
    if ($customer_id === 0) {
        echo json_encode(['success' => false, 'message' => 'Customer ID required']);
        exit;
    }
    
    $query = "SELECT id, phone_number, name, loyalty_points, total_spent FROM customers WHERE id = ?";
    $stmt = $connection->prepare($query);
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $customer = $result->fetch_assoc();
        
        // Calculate potential discount (100 points = 1% discount, max 10%)
        $discountPercentage = min(($customer['loyalty_points'] / 100), 10);
        
        echo json_encode([
            'success' => true,
            'customer' => $customer,
            'discount_percentage' => $discountPercentage,
            'can_redeem' => $customer['loyalty_points'] >= 100
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Customer not found']);
    }
    $stmt->close();
    
} elseif ($action === 'redeem_points') {
    // Redeem loyalty points for discount
    $customer_id = isset($_POST['customer_id']) ? (int)$_POST['customer_id'] : 0;
    $points_to_redeem = isset($_POST['points']) ? (int)$_POST['points'] : 0;
    
    if ($customer_id === 0 || $points_to_redeem === 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid input']);
        exit;
    }
    
    // Get customer loyalty points
    $customerQuery = "SELECT loyalty_points FROM customers WHERE id = ?";
    $customerStmt = $connection->prepare($customerQuery);
    $customerStmt->bind_param("i", $customer_id);
    $customerStmt->execute();
    $customerResult = $customerStmt->get_result();
    
    if ($customerResult->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Customer not found']);
        $customerStmt->close();
        exit;
    }
    
    $customer = $customerResult->fetch_assoc();
    $customerStmt->close();
    
    if ($customer['loyalty_points'] < $points_to_redeem) {
        echo json_encode(['success' => false, 'message' => 'Insufficient loyalty points']);
        exit;
    }
    
    // Deduct points
    $updateQuery = "UPDATE customers SET loyalty_points = loyalty_points - ? WHERE id = ?";
    $stmt = $connection->prepare($updateQuery);
    $stmt->bind_param("ii", $points_to_redeem, $customer_id);
    
    if ($stmt->execute()) {
        // Calculate discount (100 points = 1% discount)
        $discount = $points_to_redeem / 100;
        
        logAction($connection, 'Loyalty', 'Points redeemed', "Customer #$customer_id redeemed $points_to_redeem points", 'Customer', $customer_id, 'Success');
        
        echo json_encode([
            'success' => true,
            'message' => 'Points redeemed successfully',
            'discount' => $discount,
            'remaining_points' => $customer['loyalty_points'] - $points_to_redeem
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error redeeming points']);
    }
    $stmt->close();
}

closeConnection($connection);
?>
