<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

require_login();

$pdo = get_db();
$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare('SELECT id, name, email, username, profile_photo FROM users WHERE id = ?');
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    session_destroy();
    header('Location: /login.php');
    exit;
}

$trainer_profile = ['bio' => '', 'specializations' => '', 'certifications' => ''];
if (($_SESSION['role'] ?? '') === 'trainer') {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS trainer_profiles (
            user_id INTEGER PRIMARY KEY,
            bio TEXT,
            specializations TEXT,
            certifications TEXT,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");
    $stmt = $pdo->prepare('SELECT bio, specializations, certifications FROM trainer_profiles WHERE user_id = ?');
    $stmt->execute([$user_id]);
    $trainer_profile = $stmt->fetch() ?: $trainer_profile;
}

$errors = [];
$success = '';

$name = $user['name'];
$username = $user['username'];
$profile_photo = $user['profile_photo'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST) && isset($_SERVER['CONTENT_LENGTH']) && (int)$_SERVER['CONTENT_LENGTH'] > 0) {
        $errors[] = 'The file you uploaded is too large. Please upload an image smaller than 2.5MB.';
    } elseif (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request. Please try again.';
    } elseif (!isset($_POST['progress_action']) && !isset($_POST['booking_action'])) {
    $name = trim($_POST['name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['password_confirm'] ?? '';

    if ($name === '') {
        $errors[] = 'Please enter your name.';
    }

    if ($username === '') {
        $errors[] = 'Please enter a username.';
    } else {
        $check = $pdo->prepare('SELECT id FROM users WHERE username = ? AND id != ?');
        $check->execute([$username, $user_id]);
        if ($check->fetch()) {
            $errors[] = 'That username is already taken.';
        }
    }

    if ($password !== '' || $confirm !== '') {
        if ($password === '') {
            $errors[] = 'Please enter a new password or leave both password fields blank.';
        } elseif ($confirm === '') {
            $errors[] = 'Please confirm your new password.';
        } elseif ($password !== $confirm) {
            $errors[] = 'Passwords do not match.';
        } elseif (function_exists('mb_strlen') ? mb_strlen($password) : strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters long.';
        }
    }

    $photo_path = $profile_photo;
    if (!empty($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['profile_photo']['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'There was a problem uploading your photo.';
        } else {
            $allowed_ext = ['jpg', 'jpeg', 'png', 'webp'];
            $original_name = $_FILES['profile_photo']['name'];
            $extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
            $allowed = [
                'jpg' => 'jpg',
                'jpeg' => 'png',
                'png' => 'png',
                'webp' => 'webp',
            ];
            if (!isset($allowed[$extension])) {
                $errors[] = 'Upload a PNG, JPG, JPEG or WEBP image.';
            } elseif ($_FILES['profile_photo']['size'] > 2_500_000) {
                $errors[] = 'Profile photo must be 2.5MB or smaller.';
            } else {
                $upload_dir = __DIR__ . '/assets/images/profiles';
                if (!is_dir($upload_dir) && !mkdir($upload_dir, 0755, true) && !is_dir($upload_dir)) {
                    $errors[] = 'Unable to create upload directory.';
                } else {
                    $extension = $allowed[$extension];
                    $filename = sprintf('user-%d-%s.%s', $user_id, bin2hex(random_bytes(8)), $extension);
                    $destination = "$upload_dir/$filename";
                    if (!move_uploaded_file($_FILES['profile_photo']['tmp_name'], $destination)) {
                        $errors[] = 'Unable to save uploaded photo.';
                    } else {
                        $photo_path = "/assets/images/profiles/$filename";
                        if ($profile_photo && str_starts_with($profile_photo, '/assets/images/profiles/')) {
                            $old_file = __DIR__ . $profile_photo;
                            if (is_file($old_file)) {
                                @unlink($old_file);
                            }
                        }
                    }
                }
            }
        }
    }

    if (empty($errors)) {
        $fields = ['name' => $name, 'username' => $username, 'profile_photo' => $photo_path];
        $query = 'UPDATE users SET name = :name, username = :username, profile_photo = :profile_photo';

        if ($password !== '') {
            $fields['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
            $query .= ', password_hash = :password_hash';
        }

        $query .= ' WHERE id = :id';
        $fields['id'] = $user_id;

        $stmt = $pdo->prepare($query);
        $stmt->execute($fields);

        $_SESSION['user_name'] = $name;
        $_SESSION['username']  = $username;
        $success = 'Your profile has been updated successfully.';

        if (($_SESSION['role'] ?? '') === 'trainer') {
            $bio             = trim($_POST['bio'] ?? '');
            $specializations = trim($_POST['specializations'] ?? '');
            $certifications  = trim($_POST['certifications'] ?? '');

            $pdo->prepare("
                INSERT INTO trainer_profiles (user_id, bio, specializations, certifications)
                VALUES (:uid, :bio, :spec, :cert)
                ON CONFLICT (user_id) DO UPDATE
                    SET bio             = excluded.bio,
                        specializations = excluded.specializations,
                        certifications  = excluded.certifications
            ")->execute([
                ':uid'  => $user_id,
                ':bio'  => $bio ?: null,
                ':spec' => $specializations ?: null,
                ':cert' => $certifications ?: null,
            ]);

            $trainer_profile = [
                'bio'             => $bio,
                'specializations' => $specializations,
                'certifications'  => $certifications,
            ];
        }

        $stmt = $pdo->prepare('SELECT id, name, email, username, profile_photo FROM users WHERE id = ?');
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        $profile_photo = $user['profile_photo'];
    }
}


$attended_sessions = [];
$enrolled_sessions = [];
$active_sub = null;
$membership_plans = [];

if (($_SESSION['role'] ?? '') === 'member') {
    // Fetch active subscription
    $stmt = $pdo->prepare("
        SELECT ms.id AS sub_id, ms.start_date, mp.id AS plan_id, mp.name AS plan_name, mp.price, mp.description, mp.features
        FROM member_subscriptions ms
        JOIN membership_plans mp ON mp.id = ms.plan_id
        WHERE ms.member_id = ? AND ms.status = 'active'
        LIMIT 1
    ");
    $stmt->execute([$user_id]);
    $active_sub = $stmt->fetch();

    // Fetch monthly class enrollments count
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM enrollments 
        WHERE member_id = ? 
          AND status = 'enrolled' 
          AND enrolled_at >= date('now', 'start of month')
    ");
    $stmt->execute([$user_id]);
    $current_month_enrollments = (int)$stmt->fetchColumn();

    // Determine limit and remaining text
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

    if ($plan_limit === -1) {
        $remaining_text = "Unlimited";
        $remaining_classes = 9999;
    } else {
        $remaining_classes = max(0, $plan_limit - $current_month_enrollments);
        $remaining_text = "{$remaining_classes} / {$plan_limit} FREE classes remaining this month";
    }

    // Fetch all active membership plans
    $membership_plans = $pdo->query("
        SELECT id, name, price, description, features
        FROM membership_plans
        WHERE is_active = 1
        ORDER BY price ASC
    ")->fetchAll();

    $stmt = $pdo->prepare("
        SELECT e.id AS enrollment_id, c.name AS class_name, s.day_of_week, s.start_time,
               u.name AS trainer_name, r.rating, r.comment
        FROM enrollments e
        JOIN sessions s ON s.id = e.session_id
        JOIN classes c ON c.id = s.class_id
        JOIN users u ON u.id = c.trainer_id
        LEFT JOIN reviews r ON r.enrollment_id = e.id
        WHERE e.member_id = ? AND e.attended = 1
        ORDER BY e.enrolled_at DESC
    ");
    $stmt->execute([$user_id]);
    $attended_sessions = $stmt->fetchAll();
}

if (($_SESSION['role'] ?? '') === 'member') {
    $stmt = $pdo->prepare("
        SELECT e.session_id, c.name AS class_name, s.day_of_week, s.start_time,
               s.duration_minutes, u.name AS trainer_name, c.id AS class_id
        FROM enrollments e
        JOIN sessions s ON s.id = e.session_id
        JOIN classes c ON c.id = s.class_id
        JOIN users u ON u.id = c.trainer_id
        WHERE e.member_id = ? AND e.status = 'enrolled'
        ORDER BY CASE s.day_of_week
            WHEN 'monday'    THEN 1 WHEN 'tuesday'  THEN 2 WHEN 'wednesday' THEN 3
            WHEN 'thursday'  THEN 4 WHEN 'friday'   THEN 5 WHEN 'saturday'  THEN 6
            WHEN 'sunday'    THEN 7 END, s.start_time
    ");
    $stmt->execute([$user_id]);
    $enrolled_sessions = $stmt->fetchAll();
}
$workouts = [];
$lifts_by_workout = [];
$goals = [];
$progress_errors = [];
$progress_success = '';

if (($_SESSION['role'] ?? '') === 'member') {
    $pdo->exec("CREATE TABLE IF NOT EXISTS lift_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        workout_id INTEGER NOT NULL,
        exercise TEXT NOT NULL,
        sets INTEGER CHECK(sets > 0),
        reps INTEGER CHECK(reps > 0),
        weight_kg REAL CHECK(weight_kg >= 0),
        notes TEXT,
        FOREIGN KEY (workout_id) REFERENCES workout_logs(id) ON DELETE CASCADE
    )");

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['progress_action'])) {
        if (!csrf_verify($_POST['csrf_token'] ?? '')) {
            $progress_errors[] = 'Invalid security token.';
        } else {
            $progress_action = $_POST['progress_action'];
            if ($progress_action === 'log_workout') {
                $duration = (int)($_POST['duration_minutes'] ?? 0);
                $calories = ($_POST['calories_burnt'] ?? '') !== '' ? (int)$_POST['calories_burnt'] : null;
                $notes    = trim($_POST['workout_notes'] ?? '');
                if ($duration <= 0) { $progress_errors[] = 'Duration must be greater than 0.'; }
                else {
                    $pdo->prepare("INSERT INTO workout_logs (member_id, duration_minutes, calories_burnt, notes) VALUES (?,?,?,?)")
                        ->execute([$user_id, $duration, $calories, $notes ?: null]);
                    $progress_success = 'Workout logged.';
                }
            } elseif ($progress_action === 'log_lift') {
                $workout_id = (int)($_POST['workout_id'] ?? 0);
                $exercise   = trim($_POST['exercise'] ?? '');
                $sets       = (int)($_POST['sets'] ?? 0);
                $reps       = (int)($_POST['reps'] ?? 0);
                $weight     = ($_POST['weight_kg'] ?? '') !== '' ? (float)$_POST['weight_kg'] : null;
                $notes      = trim($_POST['lift_notes'] ?? '');
                $owns = $pdo->prepare("SELECT id FROM workout_logs WHERE id = ? AND member_id = ?");
                $owns->execute([$workout_id, $user_id]);
                if (!$owns->fetch())          { $progress_errors[] = 'Invalid workout.'; }
                elseif ($exercise === '')      { $progress_errors[] = 'Exercise name is required.'; }
                elseif ($sets <= 0 || $reps <= 0) { $progress_errors[] = 'Sets and reps must be greater than 0.'; }
                else {
                    $pdo->prepare("INSERT INTO lift_logs (workout_id, exercise, sets, reps, weight_kg, notes) VALUES (?,?,?,?,?,?)")
                        ->execute([$workout_id, $exercise, $sets, $reps, $weight, $notes ?: null]);
                    $progress_success = 'Lift logged.';
                }
            } elseif ($progress_action === 'delete_workout') {
                $pdo->prepare("DELETE FROM workout_logs WHERE id = ? AND member_id = ?")
                    ->execute([(int)$_POST['workout_id'], $user_id]);
                $progress_success = 'Workout deleted.';
            } elseif ($progress_action === 'delete_lift') {
                $pdo->prepare("DELETE FROM lift_logs WHERE id = ? AND workout_id IN (SELECT id FROM workout_logs WHERE member_id = ?)")
                    ->execute([(int)$_POST['lift_id'], $user_id]);
                $progress_success = 'Lift removed.';
            } elseif ($progress_action === 'add_goal') {
                $title    = trim($_POST['goal_title'] ?? '');
                $unit     = trim($_POST['goal_unit'] ?? '');
                $deadline = trim($_POST['goal_deadline'] ?? '');
                $gnotes   = trim($_POST['goal_notes'] ?? '');
                if ($title === '') { $progress_errors[] = 'Goal title is required.'; }
                else if ($deadline !== '' && $deadline < date('Y-m-d')) {
                    $progress_errors[] = 'Deadline cannot be in the past.';
                }
                else {
                    $pdo->prepare("INSERT INTO fitness_goals (member_id, title, unit, deadline, notes) VALUES (?,?,?,?,?)")
                        ->execute([$user_id, $title, $unit ?: null, $deadline ?: null, $gnotes ?: null]);
                    $progress_success = 'Goal added.';
                }
            } elseif ($progress_action === 'update_goal') {
                $status = $_POST['goal_status'] ?? '';
                if (in_array($status, ['active','achieved','abandoned'])) {
                    $pdo->prepare("UPDATE fitness_goals SET status = ? WHERE id = ? AND member_id = ?")
                        ->execute([$status, (int)$_POST['goal_id'], $user_id]);
                    $progress_success = 'Goal updated.';
                }
            } elseif ($progress_action === 'delete_goal') {
                $pdo->prepare("DELETE FROM fitness_goals WHERE id = ? AND member_id = ?")
                    ->execute([(int)$_POST['goal_id'], $user_id]);
                $progress_success = 'Goal deleted.';
            }
        }
    }
    if (!empty($progress_success) || !empty($progress_errors)) {
        $active_tab = 'progress';
    }

    $w = $pdo->prepare("SELECT id, logged_at, duration_minutes, calories_burnt, notes FROM workout_logs WHERE member_id = ? ORDER BY logged_at DESC LIMIT 20");
    $w->execute([$user_id]);
    $workouts = $w->fetchAll();

    if (!empty($workouts)) {
        $wids = array_column($workouts, 'id');
        $ph   = implode(',', array_fill(0, count($wids), '?'));
        $rows = $pdo->prepare("SELECT id, workout_id, exercise, sets, reps, weight_kg, notes FROM lift_logs WHERE workout_id IN ($ph) ORDER BY id ASC");
        $rows->execute($wids);
        foreach ($rows->fetchAll() as $lift) {
            $lifts_by_workout[(int)$lift['workout_id']][] = $lift;
        }
    }

    $g = $pdo->prepare("SELECT id, title, unit, deadline, notes, status FROM fitness_goals WHERE member_id = ? ORDER BY CASE status WHEN 'active' THEN 0 WHEN 'achieved' THEN 1 ELSE 2 END, created_at DESC");
    $g->execute([$user_id]);
    $goals = $g->fetchAll();
}

$bookings = [];
$booking_errors = [];
$booking_success = '';
$available_trainers = [];

if (($_SESSION['role'] ?? '') === 'member') {
    // fetch all active trainers for the booking form
    $t = $pdo->query("SELECT id, name FROM users WHERE role = 'trainer' AND is_active = 1 ORDER BY name");
    $available_trainers = $t->fetchAll();

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['booking_action'])) {
        if (!csrf_verify($_POST['csrf_token'] ?? '')) {
            $booking_errors[] = 'Invalid security token.';
        } else {
            $booking_action = $_POST['booking_action'];

            if ($booking_action === 'book') {
                $trainer_id   = (int)($_POST['trainer_id']    ?? 0);
                $booking_date = trim($_POST['booking_date']   ?? '');
                $start_time   = trim($_POST['start_time']     ?? '');
                $notes        = trim($_POST['booking_notes']  ?? '');

                // Calculate end_time as 1 hour after start_time
                $end_time = null;
                if ($start_time && preg_match('/^\d{2}:\d{2}$/', $start_time)) {
                    list($h, $m) = explode(':', $start_time);
                    $end_time = str_pad((string)($h + 1), 2, '0', STR_PAD_LEFT) . ':' . $m;
                }

                if ($trainer_id === 0)                              $booking_errors[] = 'Please select a trainer.';
                if ($booking_date === '' || $booking_date < date('Y-m-d')) $booking_errors[] = 'Please select a valid future date.';
                if (!preg_match('/^\d{2}:\d{2}$/', $start_time))   $booking_errors[] = 'Invalid start time.';
                if (!$end_time)                                     $booking_errors[] = 'Invalid start time.';


                // verify trainer exists
                if (empty($booking_errors)) {
                    $tc = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = 'trainer' AND is_active = 1");
                    $tc->execute([$trainer_id]);
                    if (!$tc->fetch()) $booking_errors[] = 'Invalid trainer selected.';
                }

                // check slot not already taken
                if (empty($booking_errors)) {
                    $conflict = $pdo->prepare("
                        SELECT id FROM personal_bookings
                        WHERE trainer_id = ? AND booking_date = ? AND start_time = ?
                        AND status = 'booked'
                    ");
                    $conflict->execute([$trainer_id, $booking_date, $start_time]);
                    if ($conflict->fetch()) $booking_errors[] = 'That time slot is already booked. Please choose another.';
                }

                if (empty($booking_errors)) {
                    // Check if a cancelled booking exists for this slot
                    $existing = $pdo->prepare("
                        SELECT id FROM personal_bookings
                        WHERE trainer_id = ? AND booking_date = ? AND start_time = ?
                        AND status = 'cancelled'
                    ");
                    $existing->execute([$trainer_id, $booking_date, $start_time]);
                    $old = $existing->fetch();

                    if ($old) {
                        // Reuse the old row, just update it
                        $pdo->prepare("
                            UPDATE personal_bookings
                            SET member_id = ?, end_time = ?, notes = ?, status = 'booked', created_at = CURRENT_TIMESTAMP
                            WHERE id = ?
                        ")->execute([$user_id, $end_time, $notes ?: null, $old['id']]);
                    } else {
                        $pdo->prepare("
                            INSERT INTO personal_bookings
                                (member_id, trainer_id, booking_date, start_time, end_time, notes)
                            VALUES (?, ?, ?, ?, ?, ?)
                        ")->execute([$user_id, $trainer_id, $booking_date, $start_time, $end_time, $notes ?: null]);
                    }
                    $booking_success = 'Session booked successfully.';
                }

            } elseif ($booking_action === 'cancel') {
                $booking_id = (int)($_POST['booking_id'] ?? 0);
                $pdo->prepare("
                    UPDATE personal_bookings SET status = 'cancelled'
                    WHERE id = ? AND member_id = ? AND status = 'booked'
                ")->execute([$booking_id, $user_id]);
                $booking_success = 'Booking cancelled.';
            }
        }
    }
    if (!empty($booking_success) || !empty($booking_errors)) {
        $active_tab = 'bookings';
    }

    // fetch this member's bookings
    $b = $pdo->prepare("
        SELECT pb.id, pb.booking_date, pb.start_time, pb.end_time, pb.status, pb.notes,
               u.name AS trainer_name
        FROM personal_bookings pb
        JOIN users u ON u.id = pb.trainer_id
        WHERE pb.member_id = ?
        ORDER BY pb.booking_date DESC, pb.start_time DESC
        LIMIT 20
    ");
    $b->execute([$user_id]);
    $bookings = $b->fetchAll();
}

$trainer_sessions = [];
if (($_SESSION['role'] ?? '') === 'trainer') {
    $stmt = $pdo->prepare("
        SELECT s.id AS session_id, c.name AS class_name, s.day_of_week, s.start_time, s.duration_minutes,
               c.capacity, (SELECT COUNT(*) FROM enrollments e WHERE e.session_id = s.id AND e.status = 'enrolled') AS enrolled_count
        FROM sessions s
        JOIN classes c ON c.id = s.class_id
        WHERE c.trainer_id = ? AND s.status = 'scheduled'
        ORDER BY CASE s.day_of_week
            WHEN 'monday'    THEN 1 WHEN 'tuesday'  THEN 2 WHEN 'wednesday' THEN 3
            WHEN 'thursday'  THEN 4 WHEN 'friday'   THEN 5 WHEN 'saturday'  THEN 6
            WHEN 'sunday'    THEN 7 END, s.start_time
    ");
    $stmt->execute([$user_id]);
    $trainer_sessions = $stmt->fetchAll();
}

$stats = [];
if (($_SESSION['role'] ?? '') === 'member') {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE member_id = ? AND status = 'enrolled'");
    $stmt->execute([$user_id]);
    $stats['enrolled'] = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE member_id = ? AND attended = 1");
    $stmt->execute([$user_id]);
    $stats['attended'] = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM reviews r JOIN enrollments e ON e.id = r.enrollment_id WHERE e.member_id = ?");
    $stmt->execute([$user_id]);
    $stats['reviews'] = $stmt->fetchColumn();
} elseif (($_SESSION['role'] ?? '') === 'trainer') {
    $stmt = $pdo->prepare("
        SELECT AVG(r.rating) 
        FROM reviews r 
        JOIN enrollments e ON e.id = r.enrollment_id
        JOIN sessions s ON s.id = e.session_id
        JOIN classes c ON c.id = s.class_id
        WHERE c.trainer_id = ?
    ");
    $stmt->execute([$user_id]);
    $stats['avg_rating'] = round((float)($stmt->fetchColumn() ?: 0.0), 1);

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM classes WHERE trainer_id = ?");
    $stmt->execute([$user_id]);
    $stats['classes'] = $stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COUNT(s.id) 
        FROM sessions s 
        JOIN classes c ON c.id = s.class_id
        WHERE c.trainer_id = ? AND s.status = 'scheduled'
    ");
    $stmt->execute([$user_id]);
    $stats['sessions'] = $stmt->fetchColumn();
}

$page_title = 'My Profile — Fitness Down';
$page_description = 'Edit your profile details and update your profile photo.';
$extra_css = '/css/pages/profile.css';
$extra_js = '/js/profile.js';

include __DIR__ . '/includes/header.php';

$active_tab = 'overview';
if (isset($_GET['tab']) && in_array($_GET['tab'], ['overview', 'membership', 'edit','progress','bookings'], true)) {
    $active_tab = $_GET['tab'];
}
if (($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['progress_action'])) || !empty($errors) || !empty($success)) {
    $active_tab = 'edit';
}
if (!empty($progress_success) || !empty($progress_errors)) {
    $active_tab = 'progress';
}
?>

<main class="profile-container">

    <aside class="profile-sidebar">
        <div class="profile-sidebar__card">
            <div class="profile-sidebar__avatar <?= $profile_photo ? '' : 'profile-sidebar__avatar--initials' ?>">
                <?php if ($profile_photo): ?>
                    <img src="<?= e($profile_photo) ?>" alt="<?= e($user['name']) ?>">
                <?php else: ?>
                    <span><?= e(implode('', array_map(fn($p) => strtoupper($p[0] ?? ''), explode(' ', $user['name']))) ?: 'U') ?></span>
                <?php endif; ?>
            </div>
            
            <div class="profile-sidebar__info">
                <span class="profile-role-badge profile-role-badge--<?= e($_SESSION['role'] ?? 'member') ?>">
                    <?= e(ucfirst($_SESSION['role'] ?? 'member')) ?>
                </span>
                <?php if (($_SESSION['role'] ?? '') === 'member' && $active_sub): ?>
                    <span class="profile-plan-badge plan-badge--<?= e(strtolower(str_replace(' ', '-', $active_sub['plan_name']))) ?>">
                        <?= e($active_sub['plan_name']) ?> Plan
                    </span>
                <?php endif; ?>
                <h1 class="profile-sidebar__name"><?= e($user['name']) ?></h1>
                <p class="profile-sidebar__username">@<?= e($user['username']) ?></p>
                <p class="profile-sidebar__email"><?= e($user['email']) ?></p>
            </div>
            
            <nav class="profile-sidebar__nav" aria-label="Profile navigation">
                <button type="button" class="profile-sidebar__nav-link <?= $active_tab === 'overview' ? 'profile-sidebar__nav-link--active' : '' ?>" id="tab-btn-overview">
                    📊 Overview
                </button>
                <?php if (($_SESSION['role'] ?? '') === 'member'): ?>
                    <button type="button" class="profile-sidebar__nav-link <?= $active_tab === 'membership' ? 'profile-sidebar__nav-link--active' : '' ?>" id="tab-btn-membership">
                        💳 Membership
                    </button>
                <?php endif; ?>
                <button type="button" class="profile-sidebar__nav-link <?= $active_tab === 'edit' ? 'profile-sidebar__nav-link--active' : '' ?>" id="tab-btn-edit">
                    ⚙ Edit Profile
                </button>
                <?php if (($_SESSION['role'] ?? '') === 'member'): ?>
                    <button type="button" class="profile-sidebar__nav-link <?= $active_tab === 'progress' ? 'profile-sidebar__nav-link--active' : '' ?>" id="tab-btn-progress">
                        💪 Progress
                    </button>
                <?php endif; ?>
                <?php if (($_SESSION['role'] ?? '') === 'member'): ?>
                    <button type="button" class="profile-sidebar__nav-link <?= $active_tab === 'bookings' ? 'profile-sidebar__nav-link--active' : '' ?>" id="tab-btn-bookings">
                        📅 Bookings
                        </button>
                <?php endif; ?>
                <a href="/logout.php" class="profile-sidebar__logout-link">
                    🚪 Logout
                </a>
            </nav>
        </div>
    </aside>

    <div class="profile-content">
        <!-- Overview Tab -->
        <div class="profile-tab-content" id="tab-content-overview" style="display: <?= $active_tab === 'overview' ? 'block' : 'none' ?>;">
            
            <!-- Dynamic Stats Bar (for members and trainers) -->
            <?php if (($_SESSION['role'] ?? '') === 'member' || ($_SESSION['role'] ?? '') === 'trainer'): ?>
                <div class="stats-grid">
                    <?php if ($_SESSION['role'] === 'member'): ?>
                        <div class="stat-item">
                            <span class="stat-item__value"><?= (int)($stats['enrolled'] ?? 0) ?></span>
                            <span class="stat-item__label">Enrolled Classes</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-item__value"><?= (int)($stats['attended'] ?? 0) ?></span>
                            <span class="stat-item__label">Attended Classes</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-item__value"><?= (int)($stats['reviews'] ?? 0) ?></span>
                            <span class="stat-item__label">Reviews Written</span>
                        </div>
                    <?php elseif ($_SESSION['role'] === 'trainer'): ?>
                        <div class="stat-item">
                            <span class="stat-item__value"><?= number_format((float)($stats['avg_rating'] ?? 0.0), 1) ?>★</span>
                            <span class="stat-item__label">Average Rating</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-item__value"><?= (int)($stats['classes'] ?? 0) ?></span>
                            <span class="stat-item__label">Classes Created</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-item__value"><?= (int)($stats['sessions'] ?? 0) ?></span>
                            <span class="stat-item__label">Active Sessions</span>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Trainer Bio Card -->
            <?php if (($_SESSION['role'] ?? '') === 'trainer'): ?>
                <section class="profile-card-wrapper">
                    <header class="profile-card-header">
                        <h2>Trainer Profile Details</h2>
                        <p>Your current public biography, specializations, and qualifications.</p>
                    </header>
                    
                    <div class="trainer-bio-info">
                        <div class="trainer-bio-text">
                            <h3 style="font-size: 0.85rem; text-transform: uppercase; color: var(--text-muted); margin-bottom: 0.5rem; font-family: var(--font-mono);">Biography</h3>
                            <?php if (!empty($trainer_profile['bio'])): ?>
                                <p style="margin: 0; line-height: 1.6;"><?= nl2br(e($trainer_profile['bio'])) ?></p>
                            <?php else: ?>
                                <p style="font-style: italic; color: var(--text-muted);">No biography written yet. Go to "Edit Profile" to write one!</p>
                            <?php endif; ?>
                        </div>

                        <div class="trainer-bio-meta-grid">
                            <div class="trainer-bio-meta-section">
                                <h3>Specializations</h3>
                                <?php if (!empty($trainer_profile['specializations'])): ?>
                                    <div class="tag-list">
                                        <?php foreach (explode(',', $trainer_profile['specializations']) as $spec): ?>
                                            <span class="tag-item"><?= e(trim($spec)) ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <p style="font-style: italic; color: var(--text-muted);">No specializations listed.</p>
                                <?php endif; ?>
                            </div>

                            <div class="trainer-bio-meta-section">
                                <h3>Certifications</h3>
                                <?php if (!empty($trainer_profile['certifications'])): ?>
                                    <ul class="cert-list">
                                        <?php foreach (explode(',', $trainer_profile['certifications']) as $cert): ?>
                                            <li class="cert-item"><?= e(trim($cert)) ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <p style="font-style: italic; color: var(--text-muted);">No certifications listed.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="profile-card-wrapper" style="margin-top: 2rem;">
                    <header class="profile-card-header">
                        <h2>My Schedule</h2>
                        <p>Your upcoming assigned class sessions.</p>
                    </header>
                    <?php if (empty($trainer_sessions)): ?>
                        <p style="font-style: italic; color: var(--text-muted); margin: 0;">You have no scheduled sessions assigned. <a href="/trainer/dashboard.php" style="color: var(--accent); text-decoration: underline;">Go to dashboard</a></p>
                    <?php else: ?>
                        <ul class="profile-enrollment-list enrollment-list">
                            <?php
                            $day_label = [
                                'monday' => 'Monday', 'tuesday' => 'Tuesday', 'wednesday' => 'Wednesday',
                                'thursday' => 'Thursday', 'friday' => 'Friday', 'saturday' => 'Saturday', 'sunday' => 'Sunday',
                            ];
                            foreach ($trainer_sessions as $sess): ?>
                            <li class="profile-enrollment-row enrollment-item">
                                <div class="profile-enrollment-info enrollment-details">
                                    <span class="profile-enrollment-name enrollment-name"><?= e($sess['class_name']) ?></span>
                                    <span class="profile-enrollment-meta enrollment-meta">
                                        <?= e($day_label[$sess['day_of_week']] ?? $sess['day_of_week']) ?>
                                        · <?= e($sess['start_time']) ?>
                                        · <?= $sess['duration_minutes'] ?> min
                                        · <?= (int)$sess['enrolled_count'] ?> / <?= (int)$sess['capacity'] ?> Enrolled
                                    </span>
                                </div>
                                <a href="/trainer/dashboard.php" class="button button--ghost" style="padding: 0.5rem 1rem; font-size: 0.85rem; text-decoration: none;">Manage</a>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <!-- Admin Action Links -->
            <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
                <section class="profile-card-wrapper">
                    <header class="profile-card-header">
                        <h2>System Administrator</h2>
                        <p>Quick access to administration and management sections.</p>
                    </header>
                    <div class="admin-actions-grid">
                        <a href="/admin/dashboard.php" class="admin-action-card">
                            <span class="admin-action-card__icon">📈</span>
                            <span class="admin-action-card__title">Dashboard</span>
                            <span class="admin-action-card__desc">View stats and site activity analytics.</span>
                        </a>
                        <a href="/admin/users.php" class="admin-action-card">
                            <span class="admin-action-card__icon">👥</span>
                            <span class="admin-action-card__title">Users</span>
                            <span class="admin-action-card__desc">Manage member and trainer accounts.</span>
                        </a>
                        <a href="/admin/classes.php" class="admin-action-card">
                            <span class="admin-action-card__icon">🏋️‍♂️</span>
                            <span class="admin-action-card__title">Classes</span>
                            <span class="admin-action-card__desc">Manage catalogs, schedules and trainers.</span>
                        </a>
                        <a href="/admin/equipment.php" class="admin-action-card">
                            <span class="admin-action-card__icon">⚙</span>
                            <span class="admin-action-card__title">Equipment</span>
                            <span class="admin-action-card__desc">Add and check status of workout gear.</span>
                        </a>
                    </div>
                </section>
            <?php endif; ?>

            <!-- Member Enrollments -->
            <?php if (($_SESSION['role'] ?? '') === 'member'): ?>
                <section class="profile-enrollments profile-card-wrapper">
                    <header class="profile-card-header" style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem;">
                        <div>
                            <h2>My classes</h2>
                            <p>Sessions you're currently enrolled in.</p>
                        </div>
                        <?php if ($active_sub): ?>
                            <div class="classes-counter-badge" style="background: rgba(255, 255, 255, 0.05); border: 1px solid var(--border); padding: 0.5rem 1rem; border-radius: 0.25rem; font-size: 0.875rem;">
                                <strong>Plan Allowance:</strong> 
                                <span class="classes-remaining-val" style="color: var(--accent); font-weight: bold; margin-left: 0.25rem;"><?= e($remaining_text) ?></span>
                            </div>
                        <?php else: ?>
                            <div class="classes-counter-badge" style="background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); padding: 0.5rem 1rem; border-radius: 0.25rem; font-size: 0.875rem; color: #ef4444;">
                                <strong>No Active Plan</strong> (0 classes allowed)
                            </div>
                        <?php endif; ?>
                    </header>
                    <?php if (empty($enrolled_sessions)): ?>
                        <p class="profile-enrollments__empty">
                            You're not enrolled in any sessions yet. <a href="/classes.php" style="color: var(--accent); text-decoration: underline;">Browse classes</a>
                        </p>
                    <?php else: ?>
                        <ul class="profile-enrollment-list enrollment-list">
                            <?php
                            $day_label = [
                                'monday' => 'Monday', 'tuesday' => 'Tuesday', 'wednesday' => 'Wednesday',
                                'thursday' => 'Thursday', 'friday' => 'Friday', 'saturday' => 'Saturday', 'sunday' => 'Sunday',
                            ];
                            foreach ($enrolled_sessions as $enr): ?>
                            <li class="profile-enrollment-row enrollment-item" data-session-id="<?= $enr['session_id'] ?>">
                                <div class="profile-enrollment-info enrollment-details">
                                    <span class="profile-enrollment-name enrollment-name"><?= e($enr['class_name']) ?></span>
                                    <span class="profile-enrollment-meta enrollment-meta">
                                        <?= e($day_label[$enr['day_of_week']] ?? $enr['day_of_week']) ?>
                                        · <?= e($enr['start_time']) ?>
                                        · <?= $enr['duration_minutes'] ?> min
                                        · <?= e($enr['trainer_name']) ?>
                                    </span>
                                </div>
                                <button type="button"
                                        class="button button--danger profile-enrollment-cancel"
                                        data-session-id="<?= $enr['session_id'] ?>"
                                        data-name="<?= e($enr['class_name']) ?>">
                                    Cancel
                                </button>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </section>
                <script>const CSRF_TOKEN = <?= json_encode(csrf_token()) ?>;</script>

                <!-- Member Reviews -->
                <?php if (!empty($attended_sessions)): ?>
                <section class="profile-reviews profile-card-wrapper" style="margin-top: 2rem;">
                    <header class="profile-card-header">
                        <h2>Reviews</h2>
                        <p>Rate the classes you attended.</p>
                    </header>
                    <ul class="profile-review-list review-list">
                        <?php
                        $day_label_r = [
                            'monday' => 'Monday', 'tuesday' => 'Tuesday', 'wednesday' => 'Wednesday',
                            'thursday' => 'Thursday', 'friday' => 'Friday', 'saturday' => 'Saturday', 'sunday' => 'Sunday',
                        ];
                        foreach ($attended_sessions as $att):
                            $reviewed = $att['rating'] !== null;
                        ?>
                        <li class="profile-review-row review-item" data-enrollment-id="<?= $att['enrollment_id'] ?>">
                            <div class="profile-review-info">
                                <span class="profile-enrollment-name enrollment-name"><?= e($att['class_name']) ?></span>
                                <span class="profile-enrollment-meta enrollment-meta">
                                    <?= e($day_label_r[$att['day_of_week']] ?? $att['day_of_week']) ?>
                                    · <?= e($att['start_time']) ?>
                                    · <?= e($att['trainer_name']) ?>
                                </span>
                            </div>
                            <div class="profile-review-form">
                                <?php if ($reviewed): ?>
                                <div class="profile-review-display">
                                    <div class="star-rating star-rating--readonly" data-current="<?= (int)$att['rating'] ?>">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <span class="star <?= $i <= (int)$att['rating'] ? 'star--on' : '' ?>"
                                              data-value="<?= $i ?>">★</span>
                                        <?php endfor; ?>
                                    </div>
                                    <?php if ($att['comment']): ?>
                                        <p class="profile-review-comment review-comment-box"><?= e($att['comment']) ?></p>
                                    <?php endif; ?>
                                    <div class="profile-review-actions" style="margin-top: 0.5rem; display: flex; gap: 1rem;">
                                        <button type="button" class="profile-review-edit-btn button button--ghost" style="padding: 0.4rem 1rem; font-size: 0.8rem;">Edit</button>
                                        <button type="button" class="profile-review-delete-btn button button--danger" style="padding: 0.4rem 1rem; font-size: 0.8rem;"
                                                data-enrollment-id="<?= $att['enrollment_id'] ?>">Clear</button>
                                    </div>
                                </div>
                                <div class="profile-review-editor" style="display:none">
                                    <div class="star-rating" data-current="<?= (int)$att['rating'] ?>">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <span class="star <?= $i <= (int)$att['rating'] ? 'star--on' : '' ?>"
                                              data-value="<?= $i ?>">★</span>
                                        <?php endfor; ?>
                                    </div>
                                    <textarea class="form__input profile-review-textarea"
                                              placeholder="Comment (optional)" rows="2" style="margin: 0.5rem 0; width:100%;"><?= e($att['comment'] ?? '') ?></textarea>
                                    <div class="profile-review-actions" style="display: flex; gap: 1rem;">
                                        <button type="button" class="button button--primary profile-review-submit" style="padding: 0.4rem 1rem; font-size: 0.8rem;"
                                                data-enrollment-id="<?= $att['enrollment_id'] ?>">Update</button>
                                        <button type="button" class="profile-review-cancel-edit-btn button button--ghost" style="padding: 0.4rem 1rem; font-size: 0.8rem;">Cancel</button>
                                    </div>
                                </div>
                                <?php else: ?>
                                <div class="profile-review-editor">
                                    <div class="star-rating" data-current="0">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <span class="star" data-value="<?= $i ?>">★</span>
                                        <?php endfor; ?>
                                    </div>
                                    <textarea class="form__input profile-review-textarea"
                                              placeholder="Comment (optional)" rows="2" style="margin: 0.5rem 0; width:100%;"></textarea>
                                    <div class="profile-review-actions" style="display: flex; gap: 1rem;">
                                        <button type="button" class="button button--primary profile-review-submit" style="padding: 0.4rem 1rem; font-size: 0.8rem;"
                                                data-enrollment-id="<?= $att['enrollment_id'] ?>">Submit</button>
                                        <button type="button" class="profile-review-dismiss button button--ghost" style="padding: 0.4rem 1rem; font-size: 0.8rem;">Dismiss</button>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
                <?php endif; ?>
            <?php endif; ?>
        </div>


        <!-- Membership Tab -->
        <?php if (($_SESSION['role'] ?? '') === 'member'): ?>
        <div class="profile-tab-content" id="tab-content-membership" style="display: <?= $active_tab === 'membership' ? 'block' : 'none' ?>;">
            <section class="profile-card-wrapper">
                <header class="profile-card-header">
                    <h2>Gym Membership Plan</h2>
                    <p>Select or upgrade your gym membership plan to unlock benefits and fitness classes.</p>
                </header>

                <div class="membership-current-box">
                    <h3 class="membership-section-title">Your Active Plan</h3>
                    <?php if ($active_sub): ?>
                        <div class="membership-status-card plan-tier--<?= e(strtolower(str_replace(' ', '-', $active_sub['plan_name']))) ?>">
                            <div class="membership-status-header">
                                <span class="membership-plan-badge"><?= e($active_sub['plan_name']) ?> Plan</span>
                                <span class="membership-price"><?= (int)$active_sub['price'] ?>€<span class="price-period">/month</span></span>
                            </div>
                            <p class="membership-desc" style="margin-top: 0.5rem; margin-bottom: 0.5rem;">
                                <strong>Class Allowance:</strong> <?= e($remaining_text) ?>
                            </p>
                            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-top: 0.5rem; border-top: 1px solid rgba(255,255,255,0.05); padding-top: 0.75rem;">
                                <p class="membership-start-date" style="margin: 0;">Active since: <strong><?= e(date('F j, Y', strtotime($active_sub['start_date']))) ?></strong></p>
                                <button type="button" class="button button--danger cancel-subscription-btn" style="padding: 0.5rem 1rem; font-size: 0.875rem;">Cancel Subscription</button>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="membership-status-card membership-status-card--none">
                            <p>You do not have an active membership plan. Please select a plan below to gain full access to classes and training facilities.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="membership-plans-grid">
                    <h3 class="membership-section-title">Available Plans</h3>
                    <div class="plans-list-grid">
                        <?php foreach ($membership_plans as $plan): 
                            $is_current = $active_sub && (int)$active_sub['plan_id'] === (int)$plan['id'];
                            $tier_class = strtolower(str_replace(' ', '-', $plan['name']));
                            $features_list = array_filter(array_map('trim', explode('|', $plan['features'])));
                        ?>
                            <div class="membership-plan-card <?= $is_current ? 'membership-plan-card--current' : '' ?> plan-tier--<?= e($tier_class) ?>">
                                <?php if ($is_current): ?>
                                    <span class="plan-card-ribbon">Active</span>
                                <?php endif; ?>
                                <div class="plan-card-header">
                                    <h4 class="plan-card-title"><?= e($plan['name']) ?></h4>
                                    <div class="plan-card-price">
                                        <span class="price-num"><?= (int)$plan['price'] ?>€</span>
                                        <span class="price-freq">/mo</span>
                                    </div>
                                </div>
                                <ul class="plan-card-features">
                                    <?php foreach ($features_list as $feat): ?>
                                        <li>
                                            <span class="feat-icon">✓</span>
                                            <span class="feat-text"><?= e($feat) ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                                <div class="plan-card-action">
                                    <?php if ($is_current): ?>
                                        <button type="button" class="button button--primary plan-btn" disabled>Current Plan</button>
                                    <?php else: ?>
                                        <button type="button" 
                                                class="button button--accent plan-btn subscribe-plan-btn" 
                                                data-plan-id="<?= (int)$plan['id'] ?>"
                                                data-plan-name="<?= e($plan['name']) ?>">
                                            <?= $active_sub ? 'Switch to ' . e($plan['name']) : 'Subscribe Now' ?>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
        </div>
        <?php endif; ?>
<!-- Progress Tab -->
<?php if (($_SESSION['role'] ?? '') === 'member'): ?>
<div class="profile-tab-content" id="tab-content-progress" style="display: <?= $active_tab === 'progress' ? 'block' : 'none' ?>;">

    <?php if ($progress_success): ?>
        <div class="alert-box alert-box--success"><?= e($progress_success) ?></div>
    <?php endif; ?>
    <?php if (!empty($progress_errors)): ?>
        <div class="alert-box alert-box--error">
            <ul><?php foreach ($progress_errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <!-- Workout Log -->
    <section class="profile-card-wrapper">
        <header class="profile-card-header">
            <h2>Workout Log</h2>
            <p>Record your sessions and the lifts you performed.</p>
        </header>
        <form class="progress-log-form" method="post" action="/profile.php" novalidate>
            <input type="hidden" name="csrf_token"      value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="progress_action" value="log_workout">
            <div class="progress-log-form__row">
                <div class="form__group">
                    <label class="form__label">Duration (min)</label>
                    <input type="number" name="duration_minutes" class="form__input" min="1" placeholder="e.g. 60" required>
                </div>
                <div class="form__group">
                    <label class="form__label">Calories burned <span class="form__optional">(optional)</span></label>
                    <input type="number" name="calories_burnt" class="form__input" min="0" placeholder="e.g. 400">
                </div>
                <div class="form__group">
                    <label class="form__label">Notes <span class="form__optional">(optional)</span></label>
                    <input type="text" name="workout_notes" class="form__input" placeholder="e.g. Leg day">
                </div>
            </div>
            <button type="submit" class="button button--primary">Log Workout</button>
        </form>

        <?php if (empty($workouts)): ?>
            <p class="progress-empty">No workouts logged yet.</p>
        <?php else: ?>
        <ul class="progress-workout-list">
            <?php foreach ($workouts as $w):
                $lifts = $lifts_by_workout[(int)$w['id']] ?? [];
            ?>
            <li class="progress-workout-item">
                <div class="progress-workout-header">
                    <div class="progress-workout-meta">
                        <span class="progress-workout-date"><?= e(date('d M Y · H:i', strtotime($w['logged_at']))) ?></span>
                        <span class="progress-workout-stats">
                            <?= $w['duration_minutes'] ?> min
                            <?php if ($w['calories_burnt'] !== null): ?> · <?= $w['calories_burnt'] ?> kcal<?php endif; ?>
                            <?php if ($w['notes']): ?> · <?= e($w['notes']) ?><?php endif; ?>
                        </span>
                    </div>
                    <form method="post" action="/profile.php" style="display:inline">
                        <input type="hidden" name="csrf_token"      value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="progress_action" value="delete_workout">
                        <input type="hidden" name="workout_id"      value="<?= $w['id'] ?>">
                        <button type="submit" class="progress-delete-btn" onclick="return confirm('Delete this workout and all its lifts?')">✕</button>
                    </form>
                </div>
                <?php if (!empty($lifts)): ?>
                <table class="progress-lift-table">
                    <thead><tr><th>Exercise</th><th>Sets</th><th>Reps</th><th>Weight</th><th>Notes</th><th></th></tr></thead>
                    <tbody>
                        <?php foreach ($lifts as $lift): ?>
                        <tr>
                            <td><?= e($lift['exercise']) ?></td>
                            <td class="progress-lift-num"><?= $lift['sets'] ?></td>
                            <td class="progress-lift-num"><?= $lift['reps'] ?></td>
                            <td class="progress-lift-num"><?= $lift['weight_kg'] !== null ? $lift['weight_kg'] . ' kg' : '—' ?></td>
                            <td class="progress-lift-notes"><?= e($lift['notes'] ?? '—') ?></td>
                            <td>
                                <form method="post" action="/profile.php" style="display:inline">
                                    <input type="hidden" name="csrf_token"      value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="progress_action" value="delete_lift">
                                    <input type="hidden" name="lift_id"         value="<?= $lift['id'] ?>">
                                    <button type="submit" class="progress-delete-btn" onclick="return confirm('Remove this lift?')">✕</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
                <form class="progress-add-lift" method="post" action="/profile.php">
                    <input type="hidden" name="csrf_token"      value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="progress_action" value="log_lift">
                    <input type="hidden" name="workout_id"      value="<?= $w['id'] ?>">
                    <input type="text"   name="exercise"   class="form__input progress-add-lift__field" placeholder="Exercise" required>
                    <input type="number" name="sets"       class="form__input progress-add-lift__field" placeholder="Sets" min="1" style="width:5rem">
                    <input type="number" name="reps"       class="form__input progress-add-lift__field" placeholder="Reps" min="1" style="width:5rem">
                    <input type="number" name="weight_kg"  class="form__input progress-add-lift__field" placeholder="kg"   min="0" step="0.5" style="width:5rem">
                    <input type="text"   name="lift_notes" class="form__input progress-add-lift__field" placeholder="Notes (optional)">
                    <button type="submit" class="button button--primary progress-add-lift__btn">+ Lift</button>
                </form>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </section>

    <!-- Fitness Goals -->
    <section class="profile-card-wrapper" style="margin-top: 2rem;">
        <header class="profile-card-header">
            <h2>Fitness Goals</h2>
            <p>Set targets and track what you're working towards.</p>
        </header>
        <form class="progress-log-form" method="post" action="/profile.php" novalidate>
            <input type="hidden" name="csrf_token"      value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="progress_action" value="add_goal">
            <div class="form__group">
                <label class="form__label">Goal</label>
                <input type="text" name="goal_title" class="form__input" placeholder="e.g. Bench press 100 kg" required>
            </div>
                <div class="form__group">
                    <label class="form__label">Unit <span class="form__optional">(optional)</span></label>
                    <select name="goal_unit" class="form__input" required>
                        <option value="">Select unit</option>
                        <option value="kg">kg</option>
                        <option value="km">km</option>
                        <option value="meters">meters</option>
                        <option value="reps">Repetitions</option>
                    </select>
                </div>
                <div class="form__group">
                    <label class="form__label">Deadline <span class="form__optional">(optional)</span></label>
                    <input type="date" name="goal_deadline" class="form__input" min="<?= date('Y-m-d') ?>">
                </div>
            <button type="submit" class="button button--primary">Add Goal</button>
        </form>

        <?php if (empty($goals)): ?>
            <p class="progress-empty">No goals set yet.</p>
        <?php else: ?>
        <ul class="progress-goal-list">
            <?php foreach ($goals as $g): ?>
            <li class="progress-goal-item progress-goal-item--<?= e($g['status']) ?>">
                <div class="progress-goal-info">
                    <span class="progress-goal-title"><?= e($g['title']) ?></span>
                     <span class="progress-goal-title"><?= e($g['unit']) ?></span>
                    <span class="progress-goal-meta">
                        <?php if ($g['deadline']): ?>By <?= e(date('d M Y', strtotime($g['deadline']))) ?> · <?php endif; ?>
                        <?php if ($g['notes']): ?><?= e($g['notes']) ?><?php endif; ?>
                    </span>
                </div>
                <div class="progress-goal-actions">
                    <span class="progress-goal-badge progress-goal-badge--<?= e($g['status']) ?>"><?= e(ucfirst($g['status'])) ?></span>
                    <form method="post" action="/profile.php" style="display:inline">
                        <input type="hidden" name="csrf_token"      value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="progress_action" value="update_goal">
                        <input type="hidden" name="goal_id"         value="<?= $g['id'] ?>">
                        <select name="goal_status" class="progress-goal-select" onchange="this.form.submit()">
                            <option value="active"    <?= $g['status'] === 'active'    ? 'selected' : '' ?>>Active</option>
                            <option value="achieved"  <?= $g['status'] === 'achieved'  ? 'selected' : '' ?>>Achieved</option>
                            <option value="abandoned" <?= $g['status'] === 'abandoned' ? 'selected' : '' ?>>Abandoned</option>
                        </select>
                    </form>
                    <form method="post" action="/profile.php" style="display:inline">
                        <input type="hidden" name="csrf_token"      value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="progress_action" value="delete_goal">
                        <input type="hidden" name="goal_id"         value="<?= $g['id'] ?>">
                        <button type="submit" class="progress-delete-btn" onclick="return confirm('Delete this goal?')">✕</button>
                    </form>
                </div>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </section>
</div>
<?php endif; ?>

        <!-- Edit Profile Tab -->
        <div class="profile-tab-content" id="tab-content-edit" style="display: <?= $active_tab === 'edit' ? 'block' : 'none' ?>;">
            <section class="profile-card-wrapper">
                <header class="profile-card-header">
                    <h2>Edit your profile</h2>
                    <p>Update your personal info, password, and public settings.</p>
                </header>

                <?php if ($success): ?>
                    <div class="alert-box alert-box--success"><?= e($success) ?></div>
                <?php endif; ?>

                <?php if ($errors): ?>
                    <div class="alert-box alert-box--error">
                        <ul style="margin: 0; padding-left: 1.25rem;">
                            <?php foreach ($errors as $error): ?>
                                <li><?= e($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form class="profile-form" action="/profile.php" method="post" enctype="multipart/form-data" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

                    <div class="form-grid-2">
                        <div class="form__group">
                            <label for="name" class="form__label">Full name</label>
                            <input type="text" id="name" name="name" class="form__input" value="<?= e($name) ?>" required>
                        </div>
                        <div class="form__group">
                            <label for="username" class="form__label">Username</label>
                            <input type="text" id="username" name="username" class="form__input" value="<?= e($username) ?>" required>
                        </div>
                    </div>

                    <div class="form__group" style="margin-top: 1.5rem;">
                        <label for="profile-photo" class="form__label">Profile photo</label>
                        <input type="file" id="profile-photo" name="profile_photo" class="form__input" accept="image/png,image/jpeg,image/webp">
                        <p class="form__hint" style="margin-top: 0.25rem; font-size: 0.8rem; color: var(--text-muted);">PNG, JPG, or WEBP up to 2.5MB.</p>
                        <div id="photo-preview" class="profile-photo-preview" aria-live="polite"></div>
                    </div>

                    <div class="form-grid-2" style="margin-top: 1.5rem;">
                        <div class="form__group">
                            <label for="password" class="form__label">New password</label>
                            <input type="password" id="password" name="password" class="form__input" placeholder="Leave blank to keep current" autocomplete="new-password">
                        </div>
                        <div class="form__group">
                            <label for="password-confirm" class="form__label">Confirm password</label>
                            <input type="password" id="password-confirm" name="password_confirm" class="form__input" placeholder="Confirm new password" autocomplete="new-password">
                        </div>
                    </div>

                    <?php if (($_SESSION['role'] ?? '') === 'trainer'): ?>
                        <div class="form__group" style="margin-top: 1.5rem;">
                            <label for="trainer-bio" class="form__label">Biography</label>
                            <textarea id="trainer-bio" name="bio" class="form__input" placeholder="Tell members about yourself…" rows="4"><?= e($trainer_profile['bio'] ?? '') ?></textarea>
                        </div>

                        <div class="form-grid-2" style="margin-top: 1.5rem;">
                            <div class="form__group">
                                <label for="trainer-specializations" class="form__label">Specializations</label>
                                <input type="text" id="trainer-specializations" name="specializations" class="form__input" value="<?= e($trainer_profile['specializations'] ?? '') ?>" placeholder="e.g. Muay Thai, Kickboxing">
                            </div>
                            <div class="form__group">
                                <label for="trainer-certifications" class="form__label">Certifications</label>
                                <input type="text" id="trainer-certifications" name="certifications" class="form__input" value="<?= e($trainer_profile['certifications'] ?? '') ?>" placeholder="e.g. ACE Personal Trainer">
                            </div>
                        </div>
                    <?php endif; ?>

                    <button type="submit" class="button button--primary button--block" style="margin-top: 2rem;">Save changes</button>
                </form>
            </section>
        </div>

        <div class="profile-tab-content" id="tab-content-bookings" style="display: <?= $active_tab === 'bookings' ? 'block' : 'none' ?>;">
            <?php if (($_SESSION['role'] ?? '') === 'member'): ?>
            <section class="profile-card-wrapper">
                <header class="profile-card-header">
                    <h2>Book a Personal Trainer</h2>
                    <p>Schedule one-on-one training sessions.</p>
                </header>

                <?php if ($booking_success): ?>
                    <div class="alert alert--success"><?= e($booking_success) ?></div>
                <?php endif; ?>
                <?php if (!empty($booking_errors)): ?>
                    <div class="alert alert--error">
                        <?php foreach ($booking_errors as $err): ?>
                            <div><?= e($err) ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

        <form method="POST" action="/profile.php?tab=bookings" class="booking-form" id="booking-form">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="booking_action" value="book">
            <input type="hidden" name="trainer_id"   id="booking-trainer-id" value="">
            <input type="hidden" name="booking_date" id="booking-date-val"   value="">
            <input type="hidden" name="start_time"   id="booking-time-val"   value="">

            <div class="form__group">
                <label class="form__label">Trainer</label>
                <select id="trainer-select" class="form__input" required>
                    <option value="">Select a trainer</option>
                    <?php foreach ($available_trainers as $trainer): ?>
                        <option value="<?= (int)$trainer['id'] ?>"><?= e($trainer['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div id="week-strip" style="display:none; margin-top:1.5rem;">
                <label class="form__label">Select a day</label>
                <div class="booking-week-strip" id="booking-days"></div>
            </div>

            <div id="slot-section" style="display:none; margin-top:1.5rem;">
                <label class="form__label">Available times for <span id="slot-date-label"></span></label>
                <div class="booking-slot-grid" id="slot-grid"></div>
            </div>

            <div id="booking-summary" style="display:none; margin-top:1.5rem;">
                <div class="booking-summary-box">
                    <span id="summary-text"></span>
                </div>
            </div>

            <div class="form__group" style="margin-top:1.25rem;">
                <label class="form__label">Notes <span class="form__optional">(optional)</span></label>
                <textarea name="booking_notes" class="form__input" rows="2"
                        placeholder="Any special requests..."></textarea>
            </div>

            <button type="submit" class="button button--primary" id="booking-submit"
                    style="margin-top:1rem; display:none;">Confirm Booking</button>
        </form>

                <hr style="margin: 2rem 0;">

                <h3 style="margin-bottom: 1rem;">Your Bookings</h3>
                <?php if (empty($bookings)): ?>
                    <p>No bookings yet.</p>
                <?php else: ?>
                    <ul style="list-style: none; padding: 0;">
                        <?php foreach ($bookings as $booking): ?>
                            <li style="padding: 1rem; border-bottom: 1px solid var(--border);">
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <div>
                                        <strong><?= e($booking['trainer_name']) ?></strong>
                                        <div style="font-size: 0.9rem; color: var(--text-secondary);">
                                            <?= e(date('M d, Y', strtotime($booking['booking_date']))) ?> · <?= e($booking['start_time']) ?> - <?= e($booking['end_time']) ?>
                                            <?php if (!empty($booking['notes'])): ?>
                                                <div style="margin-top: 0.25rem;">Notes: <?= e($booking['notes']) ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div style="text-align: right;">
                                        <span style="display: inline-block; padding: 0.25rem 0.75rem; border-radius: 0.25rem; font-size: 0.85rem; background: var(--bg-secondary);">
                                            <?= ucfirst(e($booking['status'])) ?>
                                        </span>
                                        <?php if ($booking['status'] === 'booked'): ?>
                                            <form method="POST" action="/profile.php" style="display: inline; margin-left: 1rem;">
                                                <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token'] ?? '') ?>">
                                                <input type="hidden" name="booking_action" value="cancel">
                                                <input type="hidden" name="booking_id" value="<?= $booking['id'] ?>">
                                                <button type="submit" class="button button--danger" style="padding: 0.25rem 0.75rem; font-size: 0.85rem;">Cancel</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php include __DIR__ . '/includes/footer.php';
