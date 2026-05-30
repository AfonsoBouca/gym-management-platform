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

$plan_id_param = $_POST['plan_id'] ?? null;
if ($plan_id_param === null) {
    http_response_code(400);
    json_out(false, 'Invalid parameters.');
}
$plan_id = (int)$plan_id_param;

$pdo = get_db();
$member_id = (int)$_SESSION['user_id'];

if ($plan_id === 0) {
    // Check if they have an active subscription
    $stmt = $pdo->prepare("SELECT id FROM member_subscriptions WHERE member_id = ? AND status = 'active'");
    $stmt->execute([$member_id]);
    if (!$stmt->fetch()) {
        json_out(false, 'No active subscription to cancel.');
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            UPDATE member_subscriptions 
            SET status = 'cancelled', end_date = CURRENT_TIMESTAMP 
            WHERE member_id = ? AND status = 'active'
        ");
        $stmt->execute([$member_id]);

        $pdo->commit();
        json_out(true, 'Successfully cancelled your subscription!');
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        http_response_code(500);
        json_out(false, 'An error occurred while cancelling your subscription.');
    }
}

if ($plan_id < 0) {
    http_response_code(400);
    json_out(false, 'Invalid parameters.');
}

// Check if the plan exists and is active
$stmt = $pdo->prepare("SELECT id, name FROM membership_plans WHERE id = ? AND is_active = 1");
$stmt->execute([$plan_id]);
$plan = $stmt->fetch();

if (!$plan) {
    http_response_code(404);
    json_out(false, 'Membership plan not found or inactive.');
}

try {
    $pdo->beginTransaction();

    // 1. Deactivate current active subscription
    $stmt = $pdo->prepare("
        UPDATE member_subscriptions 
        SET status = 'cancelled', end_date = CURRENT_TIMESTAMP 
        WHERE member_id = ? AND status = 'active'
    ");
    $stmt->execute([$member_id]);

    // 2. Insert new active subscription
    $stmt = $pdo->prepare("
        INSERT INTO member_subscriptions (member_id, plan_id, status, start_date)
        VALUES (?, ?, 'active', CURRENT_TIMESTAMP)
    ");
    $stmt->execute([$member_id, $plan_id]);

    $pdo->commit();

    json_out(true, 'Successfully subscribed to the ' . $plan['name'] . ' plan!');

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    json_out(false, 'An error occurred while updating your subscription.');
}
