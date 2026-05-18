<?php
// api/tables.php - Table Monitors Management API
include '../backend/config.php';

$action = isset($_GET['action']) ? $_GET['action'] : (isset($_POST['action']) ? $_POST['action'] : '');

if ($action === 'get_tables') {
    // Get all table monitors
    $status = isset($_GET['status']) ? $_GET['status'] : '';
    
    $query = "SELECT * FROM table_monitors";
    
    if (!empty($status)) {
        $query .= " WHERE status = '" . $connection->real_escape_string($status) . "'";
    }
    
    $query .= " ORDER BY table_id ASC LIMIT 100";
    
    $result = $connection->query($query);
    $tables = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $tables[] = $row;
        }
    }
    
    echo json_encode(['success' => true, 'tables' => $tables]);
    
} elseif ($action === 'get_table') {
    // Get single table
    $table_id = isset($_GET['id']) ? $_GET['id'] : '';
    
    $query = "SELECT * FROM table_monitors WHERE table_id = ?";
    $stmt = $connection->prepare($query);
    $stmt->bind_param("s", $table_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $table = $result->fetch_assoc();
        echo json_encode(['success' => true, 'table' => $table]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Table not found']);
    }
    $stmt->close();
    
} elseif ($action === 'add_table') {
    // Add table monitor
    $table_id = isset($_POST['table_id']) ? $_POST['table_id'] : '';
    $location_name = isset($_POST['location_name']) ? $_POST['location_name'] : '';
    $coordinates_x = isset($_POST['coordinates_x']) ? (int)$_POST['coordinates_x'] : 0;
    $coordinates_y = isset($_POST['coordinates_y']) ? (int)$_POST['coordinates_y'] : 0;
    
    if (empty($table_id)) {
        echo json_encode(['success' => false, 'message' => 'Table ID is required']);
        exit;
    }
    
    // Check if table already exists
    $checkQuery = "SELECT id FROM table_monitors WHERE table_id = ?";
    $checkStmt = $connection->prepare($checkQuery);
    $checkStmt->bind_param("s", $table_id);
    $checkStmt->execute();
    
    if ($checkStmt->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Table ID already exists']);
        $checkStmt->close();
        exit;
    }
    $checkStmt->close();
    
    // Insert table
    $status = 'Active';
    $insertQuery = "INSERT INTO table_monitors (table_id, location_name, status, coordinates_x, coordinates_y) VALUES (?, ?, ?, ?, ?)";
    $stmt = $connection->prepare($insertQuery);
    $stmt->bind_param("sssii", $table_id, $location_name, $status, $coordinates_x, $coordinates_y);
    
    if ($stmt->execute()) {
        logAction($connection, 'Table', 'Table monitor added', "Table: $table_id - Location: $location_name", 'Admin', $connection->insert_id, 'Success');
        echo json_encode([
            'success' => true,
            'message' => 'Table monitor added successfully',
            'id' => $connection->insert_id
        ]);
    } else {
        logAction($connection, 'Table', 'Table monitor addition failed', "Failed to add table $table_id", 'Admin', null, 'Error');
        echo json_encode(['success' => false, 'message' => 'Error adding table monitor']);
    }
    $stmt->close();
    
} elseif ($action === 'update_table') {
    // Update table monitor
    $table_id = isset($_POST['table_id']) ? $_POST['table_id'] : '';
    $location_name = isset($_POST['location_name']) ? $_POST['location_name'] : '';
    $status = isset($_POST['status']) ? $_POST['status'] : 'Active';
    
    if (empty($table_id)) {
        echo json_encode(['success' => false, 'message' => 'Table ID is required']);
        exit;
    }
    
    $updateQuery = "UPDATE table_monitors SET location_name = ?, status = ? WHERE table_id = ?";
    $stmt = $connection->prepare($updateQuery);
    $stmt->bind_param("sss", $location_name, $status, $table_id);
    
    if ($stmt->execute()) {
        logAction($connection, 'Table', 'Table monitor updated', "Table: $table_id - Status: $status", 'Admin', null, 'Success');
        echo json_encode(['success' => true, 'message' => 'Table monitor updated successfully']);
    } else {
        logAction($connection, 'Table', 'Table monitor update failed', "Failed to update table $table_id", 'Admin', null, 'Error');
        echo json_encode(['success' => false, 'message' => 'Error updating table monitor']);
    }
    $stmt->close();
    
} elseif ($action === 'delete_table') {
    // Delete table monitor
    $table_id = isset($_POST['table_id']) ? $_POST['table_id'] : '';
    
    if (empty($table_id)) {
        echo json_encode(['success' => false, 'message' => 'Table ID is required']);
        exit;
    }
    
    $deleteQuery = "DELETE FROM table_monitors WHERE table_id = ?";
    $stmt = $connection->prepare($deleteQuery);
    $stmt->bind_param("s", $table_id);
    
    if ($stmt->execute()) {
        logAction($connection, 'Table', 'Table monitor removed', "Table: $table_id removed from system", 'Admin', null, 'Success');
        echo json_encode(['success' => true, 'message' => 'Table monitor deleted successfully']);
    } else {
        logAction($connection, 'Table', 'Table monitor removal failed', "Failed to remove table $table_id", 'Admin', null, 'Error');
        echo json_encode(['success' => false, 'message' => 'Error deleting table monitor']);
    }
    $stmt->close();
}

closeConnection($connection);
?>
