<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Public API
require_once __DIR__ . '/../../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed']);
    exit;
}

try {
    $pdo = get_db();
    
    $query = "SELECT id, type, name, status, notes FROM equipment";
    $params = [];
    
    if (isset($_GET['status']) && in_array($_GET['status'], ['available', 'in_use', 'maintenance'], true)) {
        $query .= " WHERE status = ?";
        $params[] = $_GET['status'];
    }
    
    $query .= " ORDER BY type, name";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $equipment = $stmt->fetchAll();

    echo json_encode([
        'status' => 'success',
        'data' => $equipment
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Internal Server Error'
    ]);
}
