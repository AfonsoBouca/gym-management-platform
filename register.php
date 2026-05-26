<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

if (!empty($_SESSION['user_id'])) {
    redirect_by_role($_SESSION['role'] ?? 'member');
}

$errors = [];
$name = '';
$email = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request. Please try again.';
    }

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['password_confirm'] ?? '';

    if ($name === '') {
        $errors[] = 'Please enter your name.';
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if ($username === '') {
        $errors[] = 'Please choose a username.';
    }
    if ($password === '' || $confirm === '') {
        $errors[] = 'Please enter and confirm your password.';
    } elseif ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    } elseif (function_exists('mb_strlen') ? mb_strlen($password) : strlen($password) < 8) { 
        $errors[] = 'Password must be at least 8 characters long.';
    }

    if (empty($errors)) {
        $pdo = get_db();

        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? OR username = ?');
        $stmt->execute([$email, $username]);
        if ($stmt->fetch()) {
            $errors[] = 'Email or username is already in use.';
        }
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare(
            'INSERT INTO users (name, email, username, password_hash, profile_photo, role)
             VALUES (:name, :email, :username, :password_hash, NULL, :role)'
        );
        $stmt->execute([
            ':name' => $name,
            ':email' => $email,
            ':username' => $username,
            ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ':role' => 'member',
        ]);

        $user_id = (int) $pdo->lastInsertId();

        session_regenerate_id(true);
        $_SESSION['user_id'] = $user_id;
        $_SESSION['role'] = 'member';
        $_SESSION['user_name'] = $name;

        redirect_by_role('member');
    }
}

$page_title = 'Register — Fitness Down';
$page_description = 'Create a new Fitness Down account.';
$extra_css = '/css/pages/auth.css';

include __DIR__ . '/includes/header.php';
?>

<main class="auth">
    <section class="auth__card" aria-labelledby="auth-title">
        <header class="auth__header">
            <h1 id="auth-title" class="auth__title">Create your account</h1>
            <p class="auth__subtitle">Join Fitness Down and access classes, schedules, and your personal profile.</p>
        </header>

        <?php if ($errors): ?>
            <div class="profile-notice profile-notice--error">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form class="form" action="/register.php" method="post" novalidate>
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

            <div class="form__group">
                <label for="register-name" class="form__label">Full name</label>
                <input id="register-name" name="name" class="form__input" type="text" value="<?= e($name) ?>" required>
            </div>

            <div class="form__group">
                <label for="register-email" class="form__label">Email</label>
                <input id="register-email" name="email" class="form__input" type="email" value="<?= e($email) ?>" required>
            </div>

            <div class="form__group">
                <label for="register-username" class="form__label">Username</label>
                <input id="register-username" name="username" class="form__input" type="text" value="<?= e($username) ?>" required>
            </div>

            <div class="form__group">
                <label for="register-password" class="form__label">Password</label>
                <input id="register-password" name="password" class="form__input" type="password" required>
            </div>

            <div class="form__group">
                <label for="register-password-confirm" class="form__label">Confirm password</label>
                <input id="register-password-confirm" name="password_confirm" class="form__input" type="password" required>
            </div>

            <button type="submit" class="button button--primary button--block">Create account</button>
        </form>

        <p class="auth__footer-text">Already have an account? <a href="/login.php" class="form__link form__link--accent">Sign in</a></p>
    </section>
</main>

<?php include __DIR__ . '/includes/footer.php';
