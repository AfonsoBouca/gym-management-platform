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

function enrolled_count(PDO $pdo, int $session_id): int {
    $s = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE session_id = ? AND status = 'enrolled'");
    $s->execute([$session_id]);
    return (int)$s->fetchColumn();
}

if ($action === 'enroll') {
    // Check if already enrolled in this session
    $stmt = $pdo->prepare("SELECT status FROM enrollments WHERE member_id = ? AND session_id = ?");
    $stmt->execute([$member_id, $session_id]);
    $existing = $stmt->fetch();
    $is_already_enrolled = ($existing && $existing['status'] === 'enrolled');

    if (!$is_already_enrolled) {
        // Fetch active plan
        $stmt = $pdo->prepare("
            SELECT mp.name AS plan_name, mp.features 
            FROM member_subscriptions ms
            JOIN membership_plans mp ON mp.id = ms.plan_id
            WHERE ms.member_id = ? AND ms.status = 'active'
            LIMIT 1
        ");
        $stmt->execute([$member_id]);
        $active_sub = $stmt->fetch();

        // Determine plan limit
        $plan_limit = 0;
        if ($active_sub) {
            $plan_name = $active_sub['plan_name'];
            $features = $active_sub['features'];
            if (strcasecmp($plan_name, 'All Access') === 0) {
                $plan_limit = 8;
            } elseif (strcasecmp($plan_name, 'Essential') === 0) {
                $plan_limit = 2;
            } elseif (strcasecmp($plan_name, 'Elite') === 0) {
                $plan_limit = -1;
            } else {
                if (stripos($features ?? '', 'unlimited') !== false) {
                    $plan_limit = -1;
                } elseif (preg_match('/(\d+)\s*x?\s*class(es)?\s*per\s*week/i', $features ?? '', $matches)) {
                    $plan_limit = (int)$matches[1] * 4;
                } elseif (preg_match('/(\d+)\s*x?\s*class(es)?/i', $features ?? '', $matches)) {
                    $plan_limit = (int)$matches[1];
                } else {
                    $plan_limit = -1;
                }
            }
        }

        // If no active plan, block enrollment
        if ($plan_limit === 0) {
            json_out(false, 'You do not have an active membership plan. Please subscribe to a plan to enroll in classes.');
        }

        // If limited plan, check if the monthly limit was reached
        if ($plan_limit > 0) {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) 
                FROM enrollments 
                WHERE member_id = ? 
                  AND status = 'enrolled' 
                  AND enrolled_at >= date('now', 'start of month')
            ");
            $stmt->execute([$member_id]);
            $current_month_enrollments = (int)$stmt->fetchColumn();

            if ($current_month_enrollments >= $plan_limit) {
                json_out(false, "You have reached your plan's monthly limit of {$plan_limit} classes. Please upgrade your membership plan for more bookings.");
            }
        }
    }

    $count = enrolled_count($pdo, $session_id);

    if ($count >= (int)$sess['capacity']) {
        json_out(false, 'This session is full.', [
            'full'     => true,
            'count'    => $count,
            'capacity' => (int)$sess['capacity'],
        ]);
    }

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

} else {
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
