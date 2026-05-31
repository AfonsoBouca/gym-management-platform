<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../includes/auth.php';

function json_out(bool $ok, string $msg, array $extra = []): never {
    echo json_encode(array_merge(['ok' => $ok, 'msg' => $msg], $extra));
    exit;
}

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    json_out(false, 'Login required.');
}

if (($_SESSION['role'] ?? '') !== 'trainer') {
    http_response_code(403);
    json_out(false, 'Trainers only.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    json_out(false, 'Method not allowed.');
}

if (!csrf_verify($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    json_out(false, 'Invalid request.');
}

$pdo        = get_db();
$trainer_id = (int)$_SESSION['user_id'];
$session_id = (int)($_POST['session_id'] ?? 0);
$action     = $_POST['action'] ?? '';

if ($session_id <= 0) {
    http_response_code(400);
    json_out(false, 'Invalid session ID.');
}

if (!in_array($action, ['complete', 'cancel', 'reactivate', 'save_notes'], true)) {
    http_response_code(400);
    json_out(false, 'Invalid action.');
}

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

$columns = $pdo->query("PRAGMA table_info(sessions)")->fetchAll();
$col_names = array_column($columns, 'name');

if (!in_array('status', $col_names, true)) {
    $pdo->exec("ALTER TABLE sessions ADD COLUMN status TEXT NOT NULL DEFAULT 'scheduled'");
}
if (!in_array('notes', $col_names, true)) {
    $pdo->exec("ALTER TABLE sessions ADD COLUMN notes TEXT");
}

if ($action === 'complete') {
    $stmt = $pdo->prepare("UPDATE sessions SET status = 'completed' WHERE id = ?");
    $stmt->execute([$session_id]);

    $stmt = $pdo->prepare("UPDATE enrollments SET attended = 1 WHERE session_id = ? AND status = 'enrolled'");
    $stmt->execute([$session_id]);

    json_out(true, 'Session marked as completed.', ['status' => 'completed']);

} elseif ($action === 'cancel') {
    $stmt = $pdo->prepare("UPDATE sessions SET status = 'cancelled' WHERE id = ?");
    $stmt->execute([$session_id]);
    json_out(true, 'Session cancelled.', ['status' => 'cancelled']);

} elseif ($action === 'reactivate') {
    $stmt = $pdo->prepare("UPDATE sessions SET status = 'scheduled' WHERE id = ?");
    $stmt->execute([$session_id]);
    json_out(true, 'Session reactivated.', ['status' => 'scheduled']);

} elseif ($action === 'save_notes') {
    $notes = trim($_POST['notes'] ?? '');
    $stmt = $pdo->prepare("UPDATE sessions SET notes = ? WHERE id = ?");
    $stmt->execute([$notes ?: null, $session_id]);
    json_out(true, 'Notes saved.', ['notes' => $notes]);
}
