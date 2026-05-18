<?php
// api/menu.php - Menu Management API
include '../backend/config.php';

$action = isset($_GET['action']) ? $_GET['action'] : (isset($_POST['action']) ? $_POST['action'] : '');

if ($action === 'get_menu') {
    // Get all menu items
    $category = isset($_GET['category']) ? $_GET['category'] : '';
    
    $query = "SELECT * FROM menu WHERE is_available = 1";
    
    if (!empty($category)) {
        $query .= " AND category = '" . $connection->real_escape_string($category) . "'";
    }
    
    $query .= " ORDER BY category, name";
    
    $result = $connection->query($query);
    $menuItems = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $menuItems[] = $row;
        }
    }
    
    echo json_encode(['success' => true, 'menu' => $menuItems]);
    
} elseif ($action === 'upload_image') {
    // Upload menu item image
    if (!isset($_FILES['image'])) {
        echo json_encode(['success' => false, 'message' => 'No image provided']);
        exit;
    }

    $file = $_FILES['image'];
    $uploadDir = '../assets/meals/';
    
    // Create directory if it doesn't exist
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    // Validate file
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($file['type'], $allowedTypes)) {
        echo json_encode(['success' => false, 'message' => 'Invalid file type. Only images allowed']);
        exit;
    }
    
    if ($file['size'] > 5000000) { // 5MB limit
        echo json_encode(['success' => false, 'message' => 'File too large. Max 5MB']);
        exit;
    }
    
    // Generate unique filename
    $filename = time() . '_' . basename($file['name']);
    $filepath = $uploadDir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        echo json_encode([
            'success' => true,
            'image_url' => $filename,
            'message' => 'Image uploaded successfully'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to upload image']);
    }
    exit;
    
} elseif ($action === 'get_categories') {
    // Get menu categories
    $query = "SELECT DISTINCT category FROM menu WHERE is_available = 1 ORDER BY category";
    $result = $connection->query($query);
    $categories = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $categories[] = $row['category'];
        }
    }
    
    echo json_encode(['success' => true, 'categories' => $categories]);
    
} elseif ($action === 'add_item') {
    // Add menu item (admin)
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $category = isset($_POST['category']) ? trim($_POST['category']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $price = isset($_POST['price']) ? floatval($_POST['price']) : 0;
    $image_url = isset($_POST['image_url']) ? trim($_POST['image_url']) : '';
    
    if (empty($name)) {
        echo json_encode(['success' => false, 'message' => 'Item name is required']);
        exit;
    }
    if (empty($category)) {
        echo json_encode(['success' => false, 'message' => 'Category is required']);
        exit;
    }
    if ($price <= 0) {
        echo json_encode(['success' => false, 'message' => 'Price must be greater than 0']);
        exit;
    }
    
    $isAvailable = 1;
    
    $insertQuery = "INSERT INTO menu (name, category, description, price, image_url, is_available) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $connection->prepare($insertQuery);
    
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $connection->error]);
        exit;
    }
    
    $stmt->bind_param("sssdsi", $name, $category, $description, $price, $image_url, $isAvailable);
    
    if ($stmt->execute()) {
        logAction($connection, 'Menu', 'Menu item added', "Item: $name - Price: KES $price", 'Admin', $connection->insert_id, 'Success');
        echo json_encode([
            'success' => true,
            'message' => 'Menu item added successfully',
            'id' => $connection->insert_id
        ]);
    } else {
        logAction($connection, 'Menu', 'Menu item addition failed', "Failed to add $name - Error: " . $stmt->error, 'Admin', null, 'Error');
        echo json_encode(['success' => false, 'message' => 'Error adding menu item: ' . $stmt->error]);
    }
    $stmt->close();
    
} elseif ($action === 'update_item') {
    // Update menu item (admin)
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $name = isset($_POST['name']) ? $_POST['name'] : '';
    $category = isset($_POST['category']) ? $_POST['category'] : '';
    $description = isset($_POST['description']) ? $_POST['description'] : '';
    $price = isset($_POST['price']) ? (float)$_POST['price'] : 0;
    $is_available = isset($_POST['is_available']) ? (int)$_POST['is_available'] : 1;
    
    if ($id === 0 || empty($name)) {
        echo json_encode(['success' => false, 'message' => 'Invalid input data']);
        exit;
    }
    
    $updateQuery = "UPDATE menu SET name = ?, category = ?, description = ?, price = ?, is_available = ? WHERE id = ?";
    $stmt = $connection->prepare($updateQuery);
    $stmt->bind_param("sssdii", $name, $category, $description, $price, $is_available, $id);
    
    if ($stmt->execute()) {
        logAction($connection, 'Menu', 'Menu item updated', "Item ID: $id - $name", 'Admin', $id, 'Success');
        echo json_encode(['success' => true, 'message' => 'Menu item updated successfully']);
    } else {
        logAction($connection, 'Menu', 'Menu item update failed', "Failed to update item #$id", 'Admin', $id, 'Error');
        echo json_encode(['success' => false, 'message' => 'Error updating menu item']);
    }
    $stmt->close();
    
} elseif ($action === 'delete_item') {
    // Delete menu item (admin)
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    
    if ($id === 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid item ID']);
        exit;
    }
    
    // Soft delete - set is_available to false
    $deleteQuery = "UPDATE menu SET is_available = FALSE WHERE id = ?";
    $stmt = $connection->prepare($deleteQuery);
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        logAction($connection, 'Menu', 'Menu item removed', "Item ID: $id removed from availability", 'Admin', $id, 'Success');
        echo json_encode(['success' => true, 'message' => 'Menu item removed successfully']);
    } else {
        logAction($connection, 'Menu', 'Menu item removal failed', "Failed to remove item #$id", 'Admin', $id, 'Error');
        echo json_encode(['success' => false, 'message' => 'Error removing menu item']);
    }
    $stmt->close();
}

closeConnection($connection);
?>
