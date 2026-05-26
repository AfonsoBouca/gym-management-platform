<?php
declare(strict_types=1);

require_once __DIR__ . '/../php/includes/auth.php';
require_once __DIR__ . '/../php/includes/helper.php';

require_role('trainer');

$page_title = 'Trainer Dashboard — Fitness Down';
$page_description = 'Trainer dashboard with class and student overview.';

include __DIR__ . '/../includes/header.php';
?>
<main class="profile-page">
    <section class="profile-page__hero">
        <div class="profile-card">
            <div class="profile-card__body">
                <p class="profile-card__label">Trainer dashboard</p>
                <h1 class="profile-card__title">Welcome, <?= e($_SESSION['user_name'] ?? 'Trainer') ?></h1>
                <p class="profile-card__meta">Manage your classes, students, and profile.</p>
            </div>
        </div>
    </section>
</main>
<?php include __DIR__ . '/../includes/footer.php';
