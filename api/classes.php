<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: public, max-age=60');

require_once __DIR__ . '/../includes/db.php';

$pdo = get_db();

$stmt = $pdo->query("
    SELECT c.id, c.name, c.description, c.difficulty,
           c.duration_minutes, c.capacity,
           u.name AS trainer_name
    FROM classes c
    JOIN users u ON c.trainer_id = u.id
    WHERE c.is_active = 1
    ORDER BY c.name
");
$classes = $stmt->fetchAll();

$stmt = $pdo->query("
    SELECT s.id, s.class_id, s.day_of_week, s.start_time, s.duration_minutes
    FROM sessions s
    JOIN classes c ON s.class_id = c.id
    WHERE c.is_active = 1
    ORDER BY
        CASE s.day_of_week
            WHEN 'monday'    THEN 1
            WHEN 'tuesday'   THEN 2
            WHEN 'wednesday' THEN 3
            WHEN 'thursday'  THEN 4
            WHEN 'friday'    THEN 5
            WHEN 'saturday'  THEN 6
            WHEN 'sunday'    THEN 7
        END,
        s.start_time
");
$all_sessions = $stmt->fetchAll();

$sessions_map = [];
foreach ($all_sessions as $s) {
    $sessions_map[$s['class_id']][] = $s;
}
foreach ($classes as &$c) {
    $c['sessions'] = $sessions_map[$c['id']] ?? [];
}
unset($c);

echo json_encode(['classes' => $classes], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
