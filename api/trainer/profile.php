<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../includes/auth.php';

function json_out(bool $ok, string $msg): never {
    echo json_encode(['ok' => $ok, 'msg' => $msg]);
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

$pdo->exec("
    CREATE TABLE IF NOT EXISTS trainer_profiles (
        user_id INTEGER PRIMARY KEY,
        bio TEXT,
        specializations TEXT,
        certifications TEXT,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )
");

$bio             = trim($_POST['bio'] ?? '');
$specializations = trim($_POST['specializations'] ?? '');
$certifications  = trim($_POST['certifications'] ?? '');

$stmt = $pdo->prepare("
    INSERT INTO trainer_profiles (user_id, bio, specializations, certifications)
    VALUES (:uid, :bio, :spec, :cert)
    ON CONFLICT (user_id) DO UPDATE
        SET bio             = excluded.bio,
            specializations = excluded.specializations,
            certifications  = excluded.certifications
");

$stmt->execute([
    ':uid'  => $trainer_id,
    ':bio'  => $bio ?: null,
    ':spec' => $specializations ?: null,
    ':cert' => $certifications ?: null,
]);

json_out(true, 'Profile updated successfully.');
