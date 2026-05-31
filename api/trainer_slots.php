<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helper.php';

require_login();
header('Content-Type: application/json');

$trainer_id = (int)($_GET['trainer_id'] ?? 0);
$date       = trim($_GET['date'] ?? '');

// validate date format and that it's not in the past
if (!$trainer_id || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $date < date('Y-m-d')) {
    echo json_encode(['ok' => false, 'msg' => 'Invalid request.']);
    exit;
}

// verify trainer exists
$pdo  = get_db();
$stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = 'trainer' AND is_active = 1");
$stmt->execute([$trainer_id]);
if (!$stmt->fetch()) {
    echo json_encode(['ok' => false, 'msg' => 'Trainer not found.']);
    exit;
}

// fetch booked start_times for this trainer/date
$stmt = $pdo->prepare("
    SELECT start_time FROM personal_bookings
    WHERE trainer_id = ? AND booking_date = ? AND status = 'booked'
");
$stmt->execute([$trainer_id, $date]);
$booked = array_column($stmt->fetchAll(), 'start_time');

// build all slots 08:00–23:00
$slots = [];
for ($h = 8; $h <= 22; $h++) {
    $time = sprintf('%02d:00', $h);
    $slots[] = [
        'time'      => $time,
        'available' => !in_array($time, $booked, true),
    ];
}

echo json_encode(['ok' => true, 'slots' => $slots]);