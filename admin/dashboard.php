<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

require_role('admin');

$page_title = 'Admin Dashboard — Fitness Down';
$page_description = 'Administration dashboard for managing users, classes, and equipment.';

include __DIR__ . '/../includes/header.php';
?>
<main class="profile-page">
    <section class="profile-page__hero">
        <div class="profile-card">
            <div class="profile-card__body">
                <p class="profile-card__label">Admin dashboard</p>
                <h1 class="profile-card__title">Hello, <?= e($_SESSION['user_name'] ?? 'Admin') ?></h1>
                <p class="profile-card__meta">Monitor users, classes, and equipment from here.</p>
            </div>
        </div>
    </section>
</main>
<?php include __DIR__ . '/../includes/footer.php';
