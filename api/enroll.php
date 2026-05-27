<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/auth.php';

function json_out(bool $ok, string $msg, array $extra = []): never {
    echo json_encode(array_merge(['ok' => $ok, 'msg' => $msg], $extra));
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

$session_id = (int)($_POST['session_id'] ?? 0);
$action     = $_POST['action'] ?? '';

if ($session_id <= 0 || !in_array($action, ['enroll', 'cancel'], true)) {
    http_response_code(400);
    json_out(false, 'Invalid parameters.');
}

$pdo       = get_db();
$member_id = (int)$_SESSION['user_id'];

// verify session exists and class is active
$stmt = $pdo->prepare("
    SELECT s.id, c.capacity, c.is_active
    FROM sessions s
    JOIN classes c ON c.id = s.class_id
    WHERE s.id = ?
");
$stmt->execute([$session_id]);
$sess = $stmt->fetch();

if (!$sess || !$sess['is_active']) {
    http_response_code(404);
    json_out(false, 'Session not found.');
}

// helper: get current enrolled count for this session
function enrolled_count(PDO $pdo, int $session_id): int {
    $s = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE session_id = ? AND status = 'enrolled'");
    $s->execute([$session_id]);
    return (int)$s->fetchColumn();
}

if ($action === 'enroll') {
    $count = enrolled_count($pdo, $session_id);

    if ($count >= (int)$sess['capacity']) {
        json_out(false, 'This session is full.', [
            'full'     => true,
            'count'    => $count,
            'capacity' => (int)$sess['capacity'],
        ]);
    }

    // insert or re-activate a cancelled enrollment
    $pdo->prepare("
        INSERT INTO enrollments (member_id, session_id, status)
        VALUES (?, ?, 'enrolled')
        ON CONFLICT (member_id, session_id) DO UPDATE SET status = 'enrolled', waitlist_pos = NULL
    ")->execute([$member_id, $session_id]);

    json_out(true, 'Enrolled!', [
        'enrolled' => true,
        'count'    => enrolled_count($pdo, $session_id),
        'capacity' => (int)$sess['capacity'],
    ]);

} else { // cancel
    $stmt = $pdo->prepare("
        UPDATE enrollments SET status = 'cancelled'
        WHERE member_id = ? AND session_id = ? AND status = 'enrolled'
    ");
    $stmt->execute([$member_id, $session_id]);

    if ($stmt->rowCount() === 0) {
        json_out(false, 'No active enrollment found.');
    }

    json_out(true, 'Enrollment cancelled.', [
        'enrolled' => false,
        'count'    => enrolled_count($pdo, $session_id),
        'capacity' => (int)$sess['capacity'],
    ]);
}
