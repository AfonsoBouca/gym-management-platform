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
    
    // Fetch active classes with trainer name
    $stmt = $pdo->query("
        SELECT c.id, c.name, c.description, c.difficulty, c.duration_minutes, c.capacity, c.photo,
               u.name AS trainer_name
        FROM classes c
        JOIN users u ON c.trainer_id = u.id
        WHERE c.is_active = 1
        ORDER BY c.name
    ");
    $classes = $stmt->fetchAll();

    // Fetch sessions
    $stmt = $pdo->query("
        SELECT id, class_id, day_of_week, start_time, duration_minutes, status 
        FROM sessions 
        WHERE status != 'cancelled'
        ORDER BY
            CASE day_of_week
                WHEN 'monday'    THEN 1
                WHEN 'tuesday'   THEN 2
                WHEN 'wednesday' THEN 3
                WHEN 'thursday'  THEN 4
                WHEN 'friday'    THEN 5
                WHEN 'saturday'  THEN 6
                WHEN 'sunday'    THEN 7
            END,
            start_time
    ");
    $sessions = $stmt->fetchAll();

    // Group sessions by class
    $sessions_by_class = [];
    foreach ($sessions as $s) {
        $sessions_by_class[$s['class_id']][] = $s;
    }

    // Embed sessions into classes
    foreach ($classes as &$c) {
        $c['sessions'] = $sessions_by_class[$c['id']] ?? [];
    }
    // unset reference
    unset($c);

    echo json_encode([
        'status' => 'success',
        'data' => $classes
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Internal Server Error'
    ]);
}
