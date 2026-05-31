<?php
require_once __DIR__ . '/auth.php';
try_remember_login();
$current_page = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="en" class="page-<?= htmlspecialchars($current_page) ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= htmlspecialchars($page_description ?? 'Fitness Down — a gym built for those who train in silence.') ?>">
    <title><?= htmlspecialchars($page_title ?? 'Fitness Down — Built in Silence') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Anton&family=Inter:wght@300;400;500;700&family=JetBrains+Mono:ital,wght@0,100..900&display=swap">
    <link rel="stylesheet" href="/css/main.css">
    <?php if (isset($extra_css)): ?>
        <link rel="stylesheet" href="<?= htmlspecialchars($extra_css) ?>">
    <?php endif; ?>
    <?php if (isset($extra_js)): ?>
        <script src="<?= htmlspecialchars($extra_js) ?>" defer></script>
    <?php endif; ?>
</head>

<body>

<header class="site-header">
    <div class="site-header__inner">
        <a href="/index.php" class="brand" aria-label="Fitness Down — home">
            <span class="brand__mark">FITNESS DOWN</span>
        </a>

        <input type="checkbox" id="hamburger" class="hamburger__toggle">
        <label for="hamburger" class="hamburger__btn"></label>

        <nav class="primary-nav" aria-label="Primary">
            <ul class="primary-nav__list">
                <li><a href="/classes.php" class="primary-nav__link" <?= $current_page === 'classes' ? 'aria-current="page"' : '' ?>>Classes</a></li>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <li><a href="/equipment.php" class="primary-nav__link" <?= $current_page === 'equipment' ? 'aria-current="page"' : '' ?>>Equipment</a></li>
                    <li class="mobile-only-nav"><a href="/profile.php" class="primary-nav__link" <?= $current_page === 'profile' ? 'aria-current="page"' : '' ?>>My Account</a></li>
                <?php else: ?>
                    <li><a href="/login.php" class="primary-nav__link" <?= $current_page === 'login' ? 'aria-current="page"' : '' ?>>Login</a></li>
                    <li class="mobile-only-nav"><a href="/register.php" class="primary-nav__link" <?= $current_page === 'register' ? 'aria-current="page"' : '' ?>>Join Now</a></li>
                <?php endif; ?>
            </ul>
        </nav>

        <?php if (isset($_SESSION['user_id'])): ?>
            <a href="/profile.php" class="button button--primary">My Account</a>
        <?php else: ?>
            <a href="/register.php" class="button button--primary">Join Now</a>
        <?php endif; ?>
    </div>
</header>
