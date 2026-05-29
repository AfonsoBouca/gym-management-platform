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
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request. Please try again.';
    }

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
            $file_info = new finfo(FILEINFO_MIME_TYPE);
            $mime_type = $file_info->file($_FILES['profile_photo']['tmp_name']);
            $allowed = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
            ];
            if (!isset($allowed[$mime_type])) {
                $errors[] = 'Upload a PNG, JPG, or WEBP image.';
            } elseif ($_FILES['profile_photo']['size'] > 2_500_000) {
                $errors[] = 'Profile photo must be 2.5MB or smaller.';
            } else {
                $upload_dir = __DIR__ . '/assets/images/profiles';
                if (!is_dir($upload_dir) && !mkdir($upload_dir, 0755, true) && !is_dir($upload_dir)) {
                    $errors[] = 'Unable to create upload directory.';
                } else {
                    $extension = $allowed[$mime_type];
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
if (($_SESSION['role'] ?? '') === 'member') {
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
if ($_SERVER['REQUEST_METHOD'] === 'POST' || !empty($errors) || !empty($success)) {
    $active_tab = 'edit';
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
                <h1 class="profile-sidebar__name"><?= e($user['name']) ?></h1>
                <p class="profile-sidebar__username">@<?= e($user['username']) ?></p>
                <p class="profile-sidebar__email"><?= e($user['email']) ?></p>
            </div>
            
            <nav class="profile-sidebar__nav" aria-label="Profile navigation">
                <button type="button" class="profile-sidebar__nav-link <?= $active_tab === 'overview' ? 'profile-sidebar__nav-link--active' : '' ?>" id="tab-btn-overview">
                    📊 Overview
                </button>
                <button type="button" class="profile-sidebar__nav-link <?= $active_tab === 'edit' ? 'profile-sidebar__nav-link--active' : '' ?>" id="tab-btn-edit">
                    ⚙ Edit Profile
                </button>
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
                    <header class="profile-card-header">
                        <h2>My classes</h2>
                        <p>Sessions you're currently enrolled in.</p>
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
    </div>

</main>

<?php include __DIR__ . '/includes/footer.php';
