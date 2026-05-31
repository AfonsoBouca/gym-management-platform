<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed']);
    exit;
}

require_once __DIR__ . '/../../includes/db.php';

try {
    $pdo = get_db();
    $stmt = $pdo->query("
        SELECT u.id, u.name, u.username, u.profile_photo,
               tp.bio, tp.specializations, tp.certifications
        FROM users u
        LEFT JOIN trainer_profiles tp ON tp.user_id = u.id
        WHERE u.role = 'trainer' AND u.is_active = 1
        ORDER BY u.name
    ");
    $trainers = $stmt->fetchAll();

    echo json_encode([
        'status' => 'success',
        'data'   => $trainers,
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Internal Server Error']);
}
