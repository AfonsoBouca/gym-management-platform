<?php
require_once __DIR__ . '/php/includes/auth.php';

if (!empty($_SESSION['user_id'])) {
    redirect_by_role($_SESSION['role']);
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request.';
    } else {
        $pdo  = get_db();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1");
        $stmt->execute([$_POST['email'] ?? '']);
        $user = $stmt->fetch();

        if (!$user || !password_verify($_POST['password'] ?? '', $user['password_hash'])) {
            $error = 'Invalid email or password.';
        } else {
            session_regenerate_id(true);
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['role']      = $user['role'];
            $_SESSION['user_name'] = $user['name'];

            if (!empty($_POST['remember'])) {
                set_remember_cookie($user['id']);
            }

            redirect_by_role($user['role']);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en" class="page-auth">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description"
        content="Sign in to your Fitness Down account. Access classes, bookings, and your personal training dashboard.">
    <title>Login — Fitness Down</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Anton&family=Inter:wght@300;400;500;700&family=JetBrains+Mono:ital,wght@0,100..900&display=swap">
    <link rel="stylesheet" href="css/main.css">
</head>

<body>

    <header class="site-header">
        <div class="site-header__inner">
            <a href="index.php" class="brand" aria-label="Fitness Down — home">
                <span class="brand__mark">FITNESS DOWN</span>
            </a>

            <input type="checkbox" id="hamburger" class="hamburger__toggle">
            <label for="hamburger" class="hamburger__btn"></label>

            <nav class="primary-nav" aria-label="Primary">
                <ul class="primary-nav__list">
                    <li><a href="classes.php" class="primary-nav__link">Classes</a></li>
                    <li><a href="login.php" class="primary-nav__link" aria-current="page">Login</a></li>
                </ul>
            </nav>

            <a href="index.php#enroll" class="button button--primary">Join Now</a>
        </div>
    </header>

    <main class="auth">

        <section class="auth__card" aria-labelledby="auth-title">

            <header class="auth__header">
                <h1 id="auth-title" class="auth__title">Welcome back</h1>
                <p class="auth__subtitle">Sign in to continue your training.</p>
            </header>

            <form class="form" action="login.php" method="post" id="login-form">

                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

                <div class="form__group">
                    <label for="login-email" class="form__label">Email</label>
                    <input
                        type="email"
                        id="login-email"
                        name="email"
                        class="form__input"
                        placeholder="you@example.com"
                        autocomplete="email"
                        required>
                </div>

                <div class="form__group">
                    <div class="form__label-row">
                        <label for="login-password" class="form__label">Password</label>
                        <a href="#" class="form__link">Forgot password?</a>
                    </div>
                    <input
                        type="password"
                        id="login-password"
                        name="password"
                        class="form__input"
                        placeholder="••••••••"
                        autocomplete="current-password"
                        required>
                </div>

                <div class="form__group form__group--row">
                    <input type="checkbox" id="login-remember" name="remember" class="form__checkbox">
                    <label for="login-remember" class="form__label form__label--inline">Remember me</label>
                </div>

                <?php if ($error): ?>
                    <p class="form__error"><?= e($error) ?></p>
                <?php endif; ?>

                <button type="submit" class="button button--primary button--block">Sign In</button>

            </form>

            <div class="auth__divider" aria-hidden="true">
                <span class="auth__divider-text">or</span>
            </div>

            <p class="auth__footer-text">
                Don't have an account? <a href="/register.php" class="form__link form__link--accent">Create one</a>
            </p>

        </section>

    </main>

    <footer class="site-footer">
        <div class="site-footer__inner">

            <div class="site-footer__brand">
                <p class="brand__mark brand__mark--footer">FITNESS DOWN</p>
                <p class="site-footer__tagline">Built in silence.</p>
            </div>

            <nav class="site-footer__nav" aria-label="Footer">
                <div class="site-footer__col">
                    <h2 class="site-footer__heading">Gym</h2>
                    <ul>
                        <li><a href="classes.php">Classes</a></li>
                        <li><a href="index.php#memberships">Memberships</a></li>
                        <li><a href="login.php">Join Now</a></li>
                    </ul>
                </div>
                <div class="site-footer__col">
                    <h2 class="site-footer__heading">Info</h2>
                    <address class="site-footer__contact">
                        <p>Rua Dr. Roberto Frias 400, Porto</p>
                        <p><a href="mailto:info@fitnessdown.pt">info@fitnessdown.pt</a></p>
                        <p><a href="tel:+351211000000">+351 211 000 000</a></p>
                    </address>
                </div>
                <div class="site-footer__col">
                    <h2 class="site-footer__heading">Hours</h2>
                    <dl class="hours">
                        <div>
                            <dt>Mon–Fri</dt>
                            <dd><time>06:00</time>–<time>23:00</time></dd>
                        </div>
                        <div>
                            <dt>Sat–Sun</dt>
                            <dd><time>08:00</time>–<time>21:00</time></dd>
                        </div>
                    </dl>
                </div>
            </nav>

            <div class="site-footer__base">
                <p class="site-footer__copy">© 2026 Fitness Down. All rights reserved.</p>
                <ul class="site-footer__legal">
                    <li><a href="#">Terms</a></li>
                    <li><a href="#">Privacy</a></li>
                </ul>
            </div>

        </div>
    </footer>

</body>

</html>
