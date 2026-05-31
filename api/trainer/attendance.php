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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    json_out(false, 'Method not allowed.');
}

// ── CSRF ───────────────────────────────────────────────────────────
if (!csrf_verify($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    json_out(false, 'Invalid request.');
}

$pdo           = get_db();
$trainer_id    = (int)$_SESSION['user_id'];
$enrollment_id = (int)($_POST['enrollment_id'] ?? 0);
$attended      = isset($_POST['attended']) ? (int)$_POST['attended'] : -1;

if ($enrollment_id <= 0 || ($attended !== 0 && $attended !== 1)) {
    http_response_code(400);
    json_out(false, 'Invalid parameters.');
}

// ── verify ownership: enrollment -> session -> class -> trainer_id ─
$stmt = $pdo->prepare("
    SELECT e.id
    FROM enrollments e
    JOIN sessions s ON s.id = e.session_id
    JOIN classes c ON c.id = s.class_id
    WHERE e.id = :eid AND c.trainer_id = :tid
");
$stmt->execute([':eid' => $enrollment_id, ':tid' => $trainer_id]);

if (!$stmt->fetch()) {
    http_response_code(403);
    json_out(false, 'You do not own this session roster.');
}

// ── update attended status ─────────────────────────────────────────
$stmt = $pdo->prepare("UPDATE enrollments SET attended = :attended WHERE id = :eid");
$stmt->execute([':attended' => $attended, ':eid' => $enrollment_id]);

json_out(true, 'Attendance updated.', ['enrollment_id' => $enrollment_id, 'attended' => $attended]);
