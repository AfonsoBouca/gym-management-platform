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
$demoPassword = getenv('GYM_DEMO_PASSWORD');
if ($demoPassword === false || $demoPassword === '') {
    $demoPassword = bin2hex(random_bytes(12));
}
$pw = password_hash($demoPassword, PASSWORD_DEFAULT);

// ── users ──────────────────────────────────────────────────────────
$insertUser = $pdo->prepare(
    "INSERT INTO users (name, email, username, password_hash, profile_photo, role)
     VALUES (:name, :email, :user, :pw, NULL, :role)"
);

$users = [
    // trainers (IDs 1-6)
    ['Alex Morgan',     'alex.morgan@example.test',   'alex.morgan',   $pw, 'trainer'],
    ['Jordan Lee',      'jordan.lee@example.test',    'jordan.lee',    $pw, 'trainer'],
    ['Taylor Smith',    'taylor.smith@example.test',  'taylor.smith',  $pw, 'trainer'],
    ['Casey Brown',     'casey.brown@example.test',   'casey.brown',   $pw, 'trainer'],
    ['Morgan Davis',    'morgan.davis@example.test',  'morgan.davis',  $pw, 'trainer'],
    ['Jamie Wilson',    'jamie.wilson@example.test',  'jamie.wilson',  $pw, 'trainer'],
    // admin (ID 7)
    ['Demo Admin',      'admin@example.test',          'admin',         $pw, 'admin'],
    // demo member (ID 8)
    ['Demo Member',     'member@example.test',         'demo.member',   $pw, 'member'],
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

// ── enrollments for the demo member (user id 8) ───────────────────
// sessions: 1=Muay Thai Mon, 4=Hyrox Mon, 6=Pilates Tue, 9=Boxing Mon, 12=Stretching Mon, 19=Yoga Wed
$insertEnrollment = $pdo->prepare(
    "INSERT INTO enrollments (member_id, session_id, status, attended)
     VALUES (:mid, :sid, :status, :attended)"
);

$enrollments = [
    // attended (can leave reviews)
    [8, 1,  'enrolled', 1],  // Muay Thai Monday
    [8, 4,  'enrolled', 1],  // Hyrox Monday
    [8, 6,  'enrolled', 1],  // Pilates Tuesday
    // enrolled but not yet attended
    [8, 9,  'enrolled', 0],  // Boxing Monday
    [8, 19, 'enrolled', 0],  // Yoga Wednesday
];

foreach ($enrollments as $e) {
    $insertEnrollment->execute([
        ':mid'      => $e[0],
        ':sid'      => $e[1],
        ':status'   => $e[2],
        ':attended' => $e[3],
    ]);
}
echo "Inserted " . count($enrollments) . " enrollments for demo member.\n";

// ── equipment ──────────────────────────────────────────────────────
$insertEquip = $pdo->prepare(
    "INSERT INTO equipment (type, name, status, notes)
     VALUES (:type, :name, :status, :notes)"
);

$equipment_items = [
    // Cardio
    ['Cardio', 'Treadmill #1', 'available', 'Operational'],
    ['Cardio', 'Treadmill #2', 'available', 'Operational'],
    ['Cardio', 'Treadmill #3', 'available', 'Operational'],
    ['Cardio', 'Treadmill #4', 'maintenance', 'Sensor calibration needed'],
    ['Cardio', 'Treadmill #5', 'available', 'Operational'],
    ['Cardio', 'Treadmill #6', 'available', 'Operational'],
    ['Cardio', 'Treadmill #7', 'maintenance', 'Belt worn, awaiting replacement'],
    ['Cardio', 'Treadmill #8', 'available', 'Operational'],
    ['Cardio', 'Treadmill #9', 'available', 'Operational'],
    ['Cardio', 'Treadmill #10', 'available', 'Operational'],
    
    ['Cardio', 'Stationary Bike #1', 'available', 'Operational'],
    ['Cardio', 'Stationary Bike #2', 'available', 'Operational'],
    ['Cardio', 'Stationary Bike #3', 'available', 'Operational'],
    ['Cardio', 'Stationary Bike #4', 'available', 'Operational'],
    ['Cardio', 'Stationary Bike #5', 'available', 'Operational'],
    ['Cardio', 'Stationary Bike #6', 'available', 'Operational'],
    ['Cardio', 'Stationary Bike #7', 'available', 'Operational'],
    ['Cardio', 'Stationary Bike #8', 'available', 'Operational'],
    
    ['Cardio', 'Elliptical Cross Trainer #1', 'available', 'Operational'],
    ['Cardio', 'Elliptical Cross Trainer #2', 'available', 'Operational'],
    ['Cardio', 'Elliptical Cross Trainer #3', 'maintenance', 'Squeaking noise in left pedal'],
    ['Cardio', 'Elliptical Cross Trainer #4', 'available', 'Operational'],
    ['Cardio', 'Elliptical Cross Trainer #5', 'available', 'Operational'],
    ['Cardio', 'Elliptical Cross Trainer #6', 'available', 'Operational'],
    
    // Chest
    ['Chest', 'Flat Bench Press #1', 'available', 'Operational'],
    ['Chest', 'Flat Bench Press #2', 'maintenance', 'Reupholstering torn vinyl cushion'],
    ['Chest', 'Flat Bench Press #3', 'available', 'Operational'],
    ['Chest', 'Flat Bench Press #4', 'available', 'Operational'],
    ['Chest', 'Flat Bench Press #5', 'available', 'Operational'],
    
    ['Chest', 'Incline Bench Press #1', 'available', 'Operational'],
    ['Chest', 'Incline Bench Press #2', 'available', 'Operational'],
    ['Chest', 'Incline Bench Press #3', 'available', 'Operational'],
    ['Chest', 'Incline Bench Press #4', 'available', 'Operational'],
    
    ['Chest', 'Decline Bench Press #1', 'available', 'Operational'],
    ['Chest', 'Decline Bench Press #2', 'available', 'Operational'],
    
    ['Chest', 'Pec Dec Fly Machine #1', 'available', 'Operational'],
    ['Chest', 'Pec Dec Fly Machine #2', 'available', 'Operational'],
    ['Chest', 'Pec Dec Fly Machine #3', 'maintenance', 'Pulley cable replacement'],
    
    // Legs
    ['Legs', 'Power Squat Rack #1', 'available', 'Operational'],
    ['Legs', 'Power Squat Rack #2', 'available', 'Operational'],
    ['Legs', 'Power Squat Rack #3', 'available', 'Operational'],
    ['Legs', 'Power Squat Rack #4', 'available', 'Operational'],
    ['Legs', 'Power Squat Rack #5', 'maintenance', 'Safety bar replacement pending'],
    ['Legs', 'Power Squat Rack #6', 'available', 'Operational'],
    ['Legs', 'Power Squat Rack #7', 'available', 'Operational'],
    ['Legs', 'Power Squat Rack #8', 'available', 'Operational'],
    
    ['Legs', 'Plate-Loaded Leg Press #1', 'available', 'Operational'],
    ['Legs', 'Plate-Loaded Leg Press #2', 'available', 'Operational'],
    ['Legs', 'Plate-Loaded Leg Press #3', 'available', 'Operational'],
    ['Legs', 'Plate-Loaded Leg Press #4', 'available', 'Operational'],
    
    ['Legs', 'Seated Leg Extension #1', 'available', 'Operational'],
    ['Legs', 'Seated Leg Extension #2', 'available', 'Operational'],
    ['Legs', 'Seated Leg Extension #3', 'maintenance', 'Weight stack pin replacement'],
    
    ['Legs', 'Lying Leg Curl #1', 'available', 'Operational'],
    ['Legs', 'Lying Leg Curl #2', 'available', 'Operational'],
    ['Legs', 'Lying Leg Curl #3', 'available', 'Operational'],
    
    // Back
    ['Back', 'Lat Pulldown Machine #1', 'available', 'Operational'],
    ['Back', 'Lat Pulldown Machine #2', 'maintenance', 'Cable fraying detected'],
    ['Back', 'Lat Pulldown Machine #3', 'available', 'Operational'],
    ['Back', 'Lat Pulldown Machine #4', 'available', 'Operational'],
    
    ['Back', 'Seated Cable Row #1', 'available', 'Operational'],
    ['Back', 'Seated Cable Row #2', 'available', 'Operational'],
    ['Back', 'Seated Cable Row #3', 'available', 'Operational'],
    
    ['Back', 'Assisted Chin-Dip Station #1', 'available', 'Operational'],
    ['Back', 'Assisted Chin-Dip Station #2', 'available', 'Operational'],
    
    // Shoulders
    ['Shoulders', 'Seated Shoulder Press #1', 'available', 'Operational'],
    ['Shoulders', 'Seated Shoulder Press #2', 'available', 'Operational'],
    ['Shoulders', 'Seated Shoulder Press #3', 'maintenance', 'Hydraulic seat adjustment faulty'],
    
    ['Shoulders', 'Lateral Raise Machine #1', 'available', 'Operational'],
    ['Shoulders', 'Lateral Raise Machine #2', 'available', 'Operational']
];

foreach ($equipment_items as $eq) {
    $insertEquip->execute([
        ':type'   => $eq[0],
        ':name'   => $eq[1],
        ':status' => $eq[2],
        ':notes'  => $eq[3]
    ]);
}
echo "Inserted " . count($equipment_items) . " equipment items.\n";

// ── done ───────────────────────────────────────────────────────────
echo "\n✔  gym.db created successfully at: $dbPath\n";
echo "   Demo account password: $demoPassword\n";
