<?php
/**
 * database/seed.php
 * 
 * Creates gym.db from schema.sql and inserts sample data.
 * Run:  php database/seed.php
 */

$dbPath     = __DIR__ . '/gym.db';
$schemaPath = __DIR__ . '/schema.sql';

// ── wipe & recreate ────────────────────────────────────────────────
if (file_exists($dbPath)) {
    unlink($dbPath);
    echo "Deleted existing gym.db\n";
}

$pdo = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('PRAGMA foreign_keys = ON;');
$pdo->exec('PRAGMA journal_mode = WAL;');

// ── apply schema ───────────────────────────────────────────────────
$schema = file_get_contents($schemaPath);
$pdo->exec($schema);
echo "Schema applied.\n";

// ── helper ─────────────────────────────────────────────────────────
$pw = password_hash('password123', PASSWORD_DEFAULT);

// ── users ──────────────────────────────────────────────────────────
$insertUser = $pdo->prepare(
    "INSERT INTO users (name, email, username, password_hash, profile_photo, role)
     VALUES (:name, :email, :user, :pw, NULL, :role)"
);

$users = [
    // trainers (IDs 1-6)
    ['Carlos Silva',    'carlos@fitnessdown.pt',  'carlos.silva',    $pw, 'trainer'],
    ['Jorge Lopes',     'jorge@fitnessdown.pt',   'jorge.lopes',     $pw, 'trainer'],
    ['Sofia Matos',     'sofia@fitnessdown.pt',   'sofia.matos',     $pw, 'trainer'],
    ['Miguel Santos',   'miguel@fitnessdown.pt',  'miguel.santos',   $pw, 'trainer'],
    ['Afonso Pereira',  'afonso@fitnessdown.pt',  'afonso.pereira',  $pw, 'trainer'],
    ['Rita Costa',      'rita@fitnessdown.pt',     'rita.costa',      $pw, 'trainer'],
    // admin (ID 7)
    ['Admin',           'admin@fitnessdown.pt',   'admin',           $pw, 'admin'],
    // test member (ID 8)
    ['João Silva',      'joao@test.pt',           'joao.silva',      $pw, 'member'],
];

foreach ($users as $u) {
    $insertUser->execute([
        ':name'  => $u[0],
        ':email' => $u[1],
        ':user'  => $u[2],
        ':pw'    => $u[3],
        ':role'  => $u[4],
    ]);
}
echo "Inserted " . count($users) . " users.\n";

// ── trainer profiles ───────────────────────────────────────────────
$insertProfile = $pdo->prepare(
    "INSERT INTO trainer_profiles (user_id, bio, specializations, certifications)
     VALUES (:uid, :bio, :spec, :cert)"
);

$profiles = [
    [1, 'Expert in Muay Thai with 10+ years of competitive experience.',
        'Muay Thai, Kickboxing, Self-defense',
        'Level 3 Muay Thai Instructor, ACE Personal Trainer'],
    [2, 'Former competitive athlete specialising in functional fitness and endurance.',
        'CrossFit, Hyrox, Functional Training',
        'CrossFit Level 2, Hyrox Coach Certified'],
    [3, 'Certified Pilates instructor focused on core strength and rehabilitation.',
        'Pilates, Rehabilitation, Flexibility',
        'BASI Pilates Certified, Physical Therapy Degree'],
    [4, 'Professional boxing coach with a passion for teaching all levels.',
        'Boxing, Strength & Conditioning',
        'Boxing Coach Level 2, NSCA-CPT'],
    [5, 'Mobility specialist helping athletes move better and recover faster.',
        'Stretching, Mobility, Recovery',
        'FRC Mobility Specialist, NASM-CES'],
    [6, 'Dedicated yoga instructor bringing mindfulness to fitness.',
        'Yoga, Meditation, Breathwork',
        'RYT-500, Yin Yoga Certified'],
];

foreach ($profiles as $p) {
    $insertProfile->execute([':uid' => $p[0], ':bio' => $p[1], ':spec' => $p[2], ':cert' => $p[3]]);
}
echo "Inserted trainer profiles.\n";

// ── classes ────────────────────────────────────────────────────────
$insertClass = $pdo->prepare(
    "INSERT INTO classes (trainer_id, name, description, difficulty, duration_minutes, capacity)
     VALUES (:tid, :name, :desc, :diff, :dur, :cap)"
);

$classes = [
    // [trainer_id, name, description, difficulty, duration, capacity]
    [1, 'Muay Thai',
        'Full-contact striking art. Fists, elbows, knees, kicks.',
        'intermediate', 60, 20],
    [2, 'Hyrox',
        '8km run combined with 8 brutal functional stations.',
        'advanced', 75, 15],
    [3, 'Pilates',
        'Core control and body precision. Strength, balance and posture.',
        'beginner', 50, 25],
    [4, 'Boxing',
        'Technical bag and pad work. Explosive power and footwork.',
        'beginner', 60, 20],
    [5, 'Stretching & Mobility',
        'Active recovery. Move better, reduce injury risk.',
        'beginner', 45, 30],
    [6, 'Yoga',
        'Breathwork, balance and deep stretching. Mental clarity.',
        'beginner', 60, 25],
];

foreach ($classes as $c) {
    $insertClass->execute([
        ':tid'  => $c[0],
        ':name' => $c[1],
        ':desc' => $c[2],
        ':diff' => $c[3],
        ':dur'  => $c[4],
        ':cap'  => $c[5],
    ]);
}
echo "Inserted " . count($classes) . " classes.\n";

// ── sessions ───────────────────────────────────────────────────────
$insertSession = $pdo->prepare(
    "INSERT INTO sessions (class_id, day_of_week, start_time, duration_minutes)
     VALUES (:cid, :day, :time, :dur)"
);

// [class_id, day_of_week, start_time, duration_minutes]
$sessions = [
    // Muay Thai (class 1) — Mon/Wed/Fri 19:00, 60 min
    [1, 'monday',    '19:00', 60],
    [1, 'wednesday', '19:00', 60],
    [1, 'friday',    '19:00', 60],

    // Hyrox (class 2) — Mon/Thu 18:00, 75 min
    [2, 'monday',    '18:00', 75],
    [2, 'thursday',  '18:00', 75],

    // Pilates (class 3) — Tue/Thu/Sat 10:00, 50 min
    [3, 'tuesday',   '10:00', 50],
    [3, 'thursday',  '10:00', 50],
    [3, 'saturday',  '10:00', 50],

    // Boxing (class 4) — Mon/Wed/Fri 20:00, 60 min
    [4, 'monday',    '20:00', 60],
    [4, 'wednesday', '20:00', 60],
    [4, 'friday',    '20:00', 60],

    // Stretching & Mobility (class 5) — Daily 09:00, 45 min
    [5, 'monday',    '09:00', 45],
    [5, 'tuesday',   '09:00', 45],
    [5, 'wednesday', '09:00', 45],
    [5, 'thursday',  '09:00', 45],
    [5, 'friday',    '09:00', 45],
    [5, 'saturday',  '09:00', 45],
    [5, 'sunday',    '09:00', 45],

    // Yoga (class 6) — Wed/Sat 08:00, 60 min
    [6, 'wednesday', '08:00', 60],
    [6, 'saturday',  '08:00', 60],
];

foreach ($sessions as $s) {
    $insertSession->execute([
        ':cid'  => $s[0],
        ':day'  => $s[1],
        ':time' => $s[2],
        ':dur'  => $s[3],
    ]);
}
echo "Inserted " . count($sessions) . " sessions.\n";

// ── membership plans ───────────────────────────────────────────────
$insertPlan = $pdo->prepare(
    "INSERT INTO membership_plans (name, price, description, features)
     VALUES (:name, :price, :desc, :feat)"
);

$plans = [
    ['Essential', 60,
     'Full facility access during staffed hours except swimming pool.',
     'Gym access Mon–Sat|06:30–22:30|2x classes per month'],
    ['All Access', 100,
     'Unrestricted entry, any day, any hour. Plus two weekly classes.',
     '24/7 unrestricted access|2x classes per week|Swimming pool access during the week'],
    ['Elite', 300,
     'No limits. Everything included, nothing held back.',
     '24/7 unrestricted access|Unlimited group classes|1 nutrition consultation/month|4x personal trainer/month|Priority class booking'],
];

foreach ($plans as $p) {
    $insertPlan->execute([':name' => $p[0], ':price' => $p[1], ':desc' => $p[2], ':feat' => $p[3]]);
}
echo "Inserted " . count($plans) . " membership plans.\n";

// ── done ───────────────────────────────────────────────────────────
echo "\n✔  gym.db created successfully at: $dbPath\n";
echo "   Users password: password123\n";
