<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/auth.php';

function json_out(bool $ok, string $msg): never {
    echo json_encode(['ok' => $ok, 'msg' => $msg]);
    exit;
}

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    json_out(false, 'Login required.');
}

if (($_SESSION['role'] ?? '') !== 'member') {
    http_response_code(403);
    json_out(false, 'Members only.');
}

if (!csrf_verify($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    json_out(false, 'Invalid request.');
}

$enrollment_id = (int)($_POST['enrollment_id'] ?? 0);
$action        = $_POST['action'] ?? 'save';
$rating        = (int)($_POST['rating'] ?? 0);
$comment       = trim($_POST['comment'] ?? '');

if ($enrollment_id <= 0 || !in_array($action, ['save', 'delete'], true)) {
    http_response_code(400);
    json_out(false, 'Invalid parameters.');
}

if ($action === 'save' && ($rating < 1 || $rating > 5)) {
    http_response_code(400);
    json_out(false, 'Invalid parameters.');
}

$pdo       = get_db();
$member_id = (int)$_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT id FROM enrollments
    WHERE id = ? AND member_id = ? AND attended = 1
");
$stmt->execute([$enrollment_id, $member_id]);
if (!$stmt->fetch()) {
    http_response_code(403);
    json_out(false, 'You can only review classes you attended.');
}

if ($action === 'delete') {
    $pdo->prepare("DELETE FROM reviews WHERE enrollment_id = ?")->execute([$enrollment_id]);
    json_out(true, 'Review deleted.');
}

$pdo->prepare("
    INSERT INTO reviews (enrollment_id, rating, comment)
    VALUES (?, ?, ?)
    ON CONFLICT (enrollment_id) DO UPDATE
        SET rating = excluded.rating,
            comment = excluded.comment,
            created_at = CURRENT_TIMESTAMP
")->execute([$enrollment_id, $rating, $comment ?: null]);

json_out(true, 'Review saved.');
