<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../includes/auth.php';

function json_out(bool $ok, string $msg, array $extra = []): never {
    echo json_encode(array_merge(['ok' => $ok, 'msg' => $msg], $extra));
    exit;
}

// ── auth: must be logged-in trainer ────────────────────────────────
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    json_out(false, 'Login required.');
}

if (($_SESSION['role'] ?? '') !== 'trainer') {
    http_response_code(403);
    json_out(false, 'Trainers only.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    json_out(false, 'Method not allowed.');
}

$session_id = (int)($_GET['session_id'] ?? 0);

if ($session_id <= 0) {
    http_response_code(400);
    json_out(false, 'Invalid session ID.');
}

$pdo        = get_db();
$trainer_id = (int)$_SESSION['user_id'];

// ── verify ownership: session → class → trainer_id ────────────────
$stmt = $pdo->prepare("
    SELECT s.id
    FROM sessions s
    JOIN classes c ON c.id = s.class_id
    WHERE s.id = :sid AND c.trainer_id = :tid
");
$stmt->execute([':sid' => $session_id, ':tid' => $trainer_id]);

if (!$stmt->fetch()) {
    http_response_code(403);
    json_out(false, 'You do not own this session.');
}

// ── fetch enrolled members ────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT u.name, u.username, e.enrolled_at, e.attended
    FROM enrollments e
    JOIN users u ON u.id = e.member_id
    WHERE e.session_id = :sid AND e.status = 'enrolled'
    ORDER BY u.name
");
$stmt->execute([':sid' => $session_id]);
$members = $stmt->fetchAll();

json_out(true, 'OK', ['members' => $members]);
