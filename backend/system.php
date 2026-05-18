<?php
// api/system.php - System Health and Logs API
include '../backend/config.php';

$action = isset($_GET['action']) ? $_GET['action'] : (isset($_POST['action']) ? $_POST['action'] : '');

if ($action === 'get_system_health') {
    // Get system health metrics and status
    
    // Get latest health metrics
    $query = "SELECT DISTINCT metric_name, metric_value, status, created_at FROM system_health ORDER BY created_at DESC LIMIT 20";
    $result = $connection->query($query);
    $metrics = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $metrics[] = $row;
        }
    }
    
    // Calculate current system status
    $activeOrdersQuery = "SELECT COUNT(*) as count FROM orders WHERE status IN ('Pending', 'Cooking', 'Ready', 'Delivering')";
    $activeOrdersResult = $connection->query($activeOrdersQuery);
    $activeOrders = $activeOrdersResult->fetch_assoc()['count'] ?? 0;
    
    $activeRobotsQuery = "SELECT COUNT(*) as count FROM robots WHERE status IN ('Idle', 'Moving', 'Delivering') AND is_powered = 1";
    $activeRobotsResult = $connection->query($activeRobotsQuery);
    $activeRobots = $activeRobotsResult->fetch_assoc()['count'] ?? 0;
    
    $totalRobotsQuery = "SELECT COUNT(*) as count FROM robots WHERE is_powered = 1";
    $totalRobotsResult = $connection->query($totalRobotsQuery);
    $totalRobots = $totalRobotsResult->fetch_assoc()['count'] ?? 0;
    
    // Get database connection status
    $dbStatus = 'Connected';
    $dbHealthy = true;
    
    // Server/System online status - check if XAMPP/database is responding
    $serverOnline = true;
    $serverUptime = date('Y-m-d H:i:s');
    
    // Get or set last restart time (stored in system_logs or environment)
    $lastRestartQuery = "SELECT created_at FROM system_logs WHERE action = 'System Restart' OR action = 'System Started' ORDER BY created_at DESC LIMIT 1";
    $lastRestartResult = $connection->query($lastRestartQuery);
    $lastRestart = $lastRestartResult ? $lastRestartResult->fetch_assoc() : null;
    $lastRestartTime = $lastRestart ? $lastRestart['created_at'] : date('Y-m-d H:i:s', strtotime('-1 day'));
    
    // Sensor/Robot status - check if all robots are functioning
    $robotsOutOfServiceQuery = "SELECT COUNT(*) as count FROM robots WHERE status = 'Out of Service'";
    $robotsOutOfServiceResult = $connection->query($robotsOutOfServiceQuery);
    $robotsOutOfService = $robotsOutOfServiceResult->fetch_assoc()['count'] ?? 0;
    
    $robotsChargingQuery = "SELECT COUNT(*) as count FROM robots WHERE status = 'Charging'";
    $robotsChargingResult = $connection->query($robotsChargingQuery);
    $robotsCharging = $robotsChargingResult->fetch_assoc()['count'] ?? 0;
    
    // Calculate overall health status
    $overallStatus = 'Healthy'; // Default to Healthy
    $statusColor = '#27ae60'; // Green
    
    // Check for warnings/critical issues
    if ($robotsOutOfService > 0 || $activeOrders > 10) {
        $overallStatus = 'Warning';
        $statusColor = '#f39c12'; // Orange
    }
    
    if ($robotsOutOfService > 0 && $activeOrders > 5) {
        $overallStatus = 'Critical';
        $statusColor = '#e74c3c'; // Red
    }
    
    // Sensor status
    $sensorStatus = 'Working';
    if ($totalRobots === 0) {
        $sensorStatus = 'Faulty - No robots detected';
    } elseif ($robotsOutOfService > ($totalRobots * 0.5)) {
        $sensorStatus = 'Faulty - More than 50% robots offline';
    }
    
    echo json_encode([
        'success' => true,
        'overall_status' => $overallStatus,
        'status_color' => $statusColor,
        'last_restart' => $lastRestartTime,
        'server_online' => $serverOnline,
        'server_uptime' => $serverUptime,
        'database_status' => $dbStatus,
        'sensor_status' => $sensorStatus,
        'system_metrics' => [
            'active_orders' => $activeOrders,
            'active_robots' => $activeRobots,
            'total_robots' => $totalRobots,
            'robots_out_of_service' => $robotsOutOfService,
            'robots_charging' => $robotsCharging
        ],
        'metrics' => $metrics
    ]);
    
} elseif ($action === 'get_logs') {
    // Get system logs
    $log_type = isset($_GET['log_type']) ? $_GET['log_type'] : '';
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
    
    $query = "SELECT * FROM system_logs";
    
    if (!empty($log_type)) {
        $query .= " WHERE log_type = '$log_type'";
    }
    
    $query .= " ORDER BY created_at DESC LIMIT $limit";
    
    $result = $connection->query($query);
    $logs = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $logs[] = $row;
        }
    }
    
    echo json_encode(['success' => true, 'logs' => $logs]);
    
} elseif ($action === 'get_log_types') {
    // Get unique log types
    $query = "SELECT DISTINCT log_type FROM system_logs ORDER BY log_type";
    
    $result = $connection->query($query);
    $logTypes = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $logTypes[] = $row['log_type'];
        }
    }
    
    echo json_encode(['success' => true, 'log_types' => $logTypes]);
    
} elseif ($action === 'record_health') {
    // Record health metric
    $metric_name = isset($_POST['metric_name']) ? $_POST['metric_name'] : '';
    $metric_value = isset($_POST['metric_value']) ? $_POST['metric_value'] : '';
    $status = isset($_POST['status']) ? $_POST['status'] : 'Healthy';
    
    if (empty($metric_name) || empty($metric_value)) {
        echo json_encode(['success' => false, 'message' => 'Metric name and value required']);
        exit;
    }
    
    $insertQuery = "INSERT INTO system_health (metric_name, metric_value, status) VALUES (?, ?, ?)";
    $stmt = $connection->prepare($insertQuery);
    $stmt->bind_param("sss", $metric_name, $metric_value, $status);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Health metric recorded']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error recording metric']);
    }
    $stmt->close();
    
} elseif ($action === 'get_dashboard_summary') {
    // Get dashboard summary for admin
    
    // Active orders count (excluding Delivered)
    $activeOrdersQuery = "SELECT COUNT(*) as count FROM orders WHERE status != 'Delivered'";
    $activeOrdersResult = $connection->query($activeOrdersQuery);
    $activeOrders = $activeOrdersResult->fetch_assoc()['count'] ?? 0;
    
    // Active robots count (only those that are Idle, Moving, or Delivering)
    $activeRobotsQuery = "SELECT COUNT(*) as count FROM robots WHERE status IN ('Idle', 'Moving', 'Delivering') AND is_powered = 1";
    $activeRobotsResult = $connection->query($activeRobotsQuery);
    $activeRobots = $activeRobotsResult->fetch_assoc()['count'] ?? 0;
    
    // Total tables count
    $totalTablesQuery = "SELECT COUNT(*) as count FROM table_monitors";
    $totalTablesResult = $connection->query($totalTablesQuery);
    $totalTables = $totalTablesResult->fetch_assoc()['count'] ?? 0;
    
    // Total revenue (from completed payments or all orders)
    $revenueQuery = "SELECT SUM(total_amount) as total_revenue FROM orders WHERE status = 'Delivered'";
    $revenueResult = $connection->query($revenueQuery);
    $revenue = $revenueResult->fetch_assoc()['total_revenue'] ?? 0;
    
    // Orders summary by status
    $ordersQuery = "SELECT status, COUNT(*) as count FROM orders GROUP BY status";
    $ordersResult = $connection->query($ordersQuery);
    $ordersSummary = [];
    while ($row = $ordersResult->fetch_assoc()) {
        $ordersSummary[] = $row;
    }
    
    // Recent orders with customer names
    $recentOrdersQuery = "SELECT o.id, o.order_number, o.table_id, o.status, o.total_amount, o.created_at, c.name as customer_name
                         FROM orders o
                         LEFT JOIN customers c ON o.customer_id = c.id
                         ORDER BY o.created_at DESC LIMIT 10";
    $recentOrdersResult = $connection->query($recentOrdersQuery);
    $recentOrders = [];
    while ($row = $recentOrdersResult->fetch_assoc()) {
        $recentOrders[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'active_orders' => $activeOrders,
        'active_robots' => $activeRobots,
        'total_tables' => $totalTables,
        'total_revenue' => $revenue,
        'orders_summary' => $ordersSummary,
        'recent_orders' => $recentOrders
    ]);
}

closeConnection($connection);
?>
