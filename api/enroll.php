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

if ($session_id <= 0 || !in_array($action, ['enroll', 'cancel', 'waitlist', 'leave_waitlist'], true)) {
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

function waitlist_count(PDO $pdo, int $session_id): int {
    $s = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE session_id = ? AND status = 'waitlisted'");
    $s->execute([$session_id]);
    return (int)$s->fetchColumn();
}

function next_waitlist_pos(PDO $pdo, int $session_id): int {
    $s = $pdo->prepare("SELECT COALESCE(MAX(waitlist_pos), 0) + 1 FROM enrollments WHERE session_id = ? AND status = 'waitlisted'");
    $s->execute([$session_id]);
    return (int)$s->fetchColumn();
}

function promote_first_waitlisted(PDO $pdo, int $session_id): bool {
    $s = $pdo->prepare("
        SELECT id, member_id FROM enrollments
        WHERE session_id = ? AND status = 'waitlisted'
        ORDER BY waitlist_pos ASC
        LIMIT 1
    ");
    $s->execute([$session_id]);
    $first = $s->fetch();
    if (!$first) return false;

    $pdo->prepare("
        UPDATE enrollments SET status = 'enrolled', waitlist_pos = NULL
        WHERE id = ?
    ")->execute([$first['id']]);

    // re-number remaining waitlisted positions
    $pdo->prepare("
        UPDATE enrollments SET waitlist_pos = waitlist_pos - 1
        WHERE session_id = ? AND status = 'waitlisted'
    ")->execute([$session_id]);

    return true;
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
    $capacity = (int)$sess['capacity'];

    if ($count >= $capacity) {
        // session is full — redirect to waitlist action
        json_out(false, 'This session is full. You can join the waitlist.', [
            'full'     => true,
            'count'    => $count,
            'capacity' => $capacity,
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
        'capacity' => $capacity,
    ]);

} elseif ($action === 'cancel') {
    // cancel an enrolled member
    $stmt = $pdo->prepare("
        UPDATE enrollments SET status = 'cancelled'
        WHERE member_id = ? AND session_id = ? AND status = 'enrolled'
    ");
    $stmt->execute([$member_id, $session_id]);

    if ($stmt->rowCount() === 0) {
        json_out(false, 'No active enrollment found.');
    }

    // auto-promote the first waitlisted member
    $promoted = promote_first_waitlisted($pdo, $session_id);

    json_out(true, 'Enrollment cancelled.', [
        'enrolled'  => false,
        'count'     => enrolled_count($pdo, $session_id),
        'capacity'  => (int)$sess['capacity'],
        'promoted'  => $promoted,
    ]);

} elseif ($action === 'waitlist') {
    // check if already enrolled or waitlisted
    $stmt = $pdo->prepare("SELECT status FROM enrollments WHERE member_id = ? AND session_id = ?");
    $stmt->execute([$member_id, $session_id]);
    $existing = $stmt->fetch();

    if ($existing && $existing['status'] === 'enrolled') {
        json_out(false, 'You are already enrolled in this session.');
    }
    if ($existing && $existing['status'] === 'waitlisted') {
        json_out(false, 'You are already on the waitlist.');
    }

    $count = enrolled_count($pdo, $session_id);
    $capacity = (int)$sess['capacity'];

    // if spots opened up, enroll directly instead
    if ($count < $capacity) {
        $pdo->prepare("
            INSERT INTO enrollments (member_id, session_id, status)
            VALUES (?, ?, 'enrolled')
            ON CONFLICT (member_id, session_id) DO UPDATE SET status = 'enrolled', waitlist_pos = NULL
        ")->execute([$member_id, $session_id]);

        json_out(true, 'A spot opened up — you have been enrolled!', [
            'enrolled'   => true,
            'waitlisted' => false,
            'count'      => enrolled_count($pdo, $session_id),
            'capacity'   => $capacity,
        ]);
    }

    $pos = next_waitlist_pos($pdo, $session_id);

    $pdo->prepare("
        INSERT INTO enrollments (member_id, session_id, status, waitlist_pos)
        VALUES (?, ?, 'waitlisted', ?)
        ON CONFLICT (member_id, session_id) DO UPDATE SET status = 'waitlisted', waitlist_pos = ?
    ")->execute([$member_id, $session_id, $pos, $pos]);

    json_out(true, "You've been added to the waitlist (#$pos).", [
        'waitlisted'   => true,
        'waitlist_pos' => $pos,
        'count'        => enrolled_count($pdo, $session_id),
        'capacity'     => $capacity,
    ]);

} elseif ($action === 'leave_waitlist') {
    $stmt = $pdo->prepare("
        SELECT id, waitlist_pos FROM enrollments
        WHERE member_id = ? AND session_id = ? AND status = 'waitlisted'
    ");
    $stmt->execute([$member_id, $session_id]);
    $row = $stmt->fetch();

    if (!$row) {
        json_out(false, 'You are not on the waitlist for this session.');
    }

    $old_pos = (int)$row['waitlist_pos'];

    $pdo->prepare("
        UPDATE enrollments SET status = 'cancelled', waitlist_pos = NULL
        WHERE id = ?
    ")->execute([$row['id']]);

    // re-number members who were behind this position
    $pdo->prepare("
        UPDATE enrollments SET waitlist_pos = waitlist_pos - 1
        WHERE session_id = ? AND status = 'waitlisted' AND waitlist_pos > ?
    ")->execute([$session_id, $old_pos]);

    json_out(true, 'You have left the waitlist.', [
        'waitlisted' => false,
        'count'      => enrolled_count($pdo, $session_id),
        'capacity'   => (int)$sess['capacity'],
    ]);
}
