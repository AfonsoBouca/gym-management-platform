<?php
declare(strict_types=1);

require_once __DIR__ . '/php/includes/auth.php';
require_once __DIR__ . '/php/includes/helper.php';

require_login();

$pdo = get_db();
$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare('SELECT id, name, email, username, profile_photo FROM users WHERE id = ?');
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: /login.php');
    exit;
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
                    $destination = $upload_dir . '/' . $filename;
                    if (!move_uploaded_file($_FILES['profile_photo']['tmp_name'], $destination)) {
                        $errors[] = 'Unable to save uploaded photo.';
                    } else {
                        $photo_path = '/assets/images/profiles/' . $filename;
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
        $success = 'Your profile has been updated successfully.';

        $stmt = $pdo->prepare('SELECT id, name, email, username, profile_photo FROM users WHERE id = ?');
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        $profile_photo = $user['profile_photo'];
    }
}

$page_title = 'My Profile — Fitness Down';
$page_description = 'Edit your profile details and update your profile photo.';
$extra_css = '/css/pages/profile.css';
$extra_js = '/js/profile.js';

include __DIR__ . '/includes/header.php';
?>

<main class="profile-page">
    <section class="profile-page__hero">
        <div class="profile-card profile-card--summary">
            <div class="profile-avatar <?= $profile_photo ? 'profile-avatar--image' : 'profile-avatar--fallback' ?>">
                <?php if ($profile_photo): ?>
                    <img src="<?= e($profile_photo) ?>" alt="<?= e($user['name']) ?> profile photo">
                <?php else: ?>
                    <span><?= e(implode('', array_map(fn($part) => strtoupper($part[0] ?? ''), explode(' ', $user['name']))) ?: 'U') ?></span>
                <?php endif; ?>
            </div>
            <div class="profile-card__body">
                <p class="profile-card__label">Account</p>
                <h1 class="profile-card__title"><?= e($user['name']) ?></h1>
                <p class="profile-card__meta">@<?= e($user['username']) ?></p>
                <p class="profile-card__email"><?= e($user['email']) ?></p>
            </div>
        </div>
    </section>

    <section class="profile-page__form">
        <div class="profile-form-card">
            <header class="profile-form-card__header">
                <h2>Update your profile</h2>
                <p>Change your name, username, password, or upload a new photo.</p>
            </header>

            <?php if ($success): ?>
                <div class="profile-notice profile-notice--success"><?= e($success) ?></div>
            <?php endif; ?>

            <?php if ($errors): ?>
                <div class="profile-notice profile-notice--error">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= e($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form class="form" action="/profile.php" method="post" enctype="multipart/form-data" novalidate>
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

                <div class="form__group">
                    <label for="name" class="form__label">Full name</label>
                    <input type="text" id="name" name="name" class="form__input" value="<?= e($name) ?>" required>
                </div>

                <div class="form__group">
                    <label for="username" class="form__label">Username</label>
                    <input type="text" id="username" name="username" class="form__input" value="<?= e($username) ?>" required>
                </div>

                <div class="form__group">
                    <label for="profile-photo" class="form__label">Profile photo</label>
                    <input type="file" id="profile-photo" name="profile_photo" class="form__input" accept="image/png,image/jpeg,image/webp">
                    <p class="form__hint">PNG, JPG, or WEBP up to 2.5MB.</p>
                    <div id="photo-preview" class="profile-photo-preview" aria-live="polite"></div>
                </div>

                <div class="form__group">
                    <label for="password" class="form__label">New password</label>
                    <input type="password" id="password" name="password" class="form__input" placeholder="Leave blank to keep current password" autocomplete="new-password">
                </div>

                <div class="form__group">
                    <label for="password-confirm" class="form__label">Confirm password</label>
                    <input type="password" id="password-confirm" name="password_confirm" class="form__input" placeholder="Confirm new password" autocomplete="new-password">
                </div>

                <button type="submit" class="button button--primary button--block">Save changes</button>
            </form>
        </div>
    </section>
</main>

<?php include __DIR__ . '/includes/footer.php';
