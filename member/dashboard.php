<?php
declare(strict_types=1);

require_once __DIR__ . '/../php/includes/auth.php';
require_once __DIR__ . '/../php/includes/helper.php';

require_role('member');

$page_title = 'Member Dashboard — Fitness Down';
$page_description = 'Your member dashboard with class recommendations and account quick links.';

include __DIR__ . '/../includes/header.php';
?>
<main class="profile-page">
    <section class="profile-page__hero">
        <div class="profile-card">
            <div class="profile-card__body">
                <p class="profile-card__label">Member dashboard</p>
                <h1 class="profile-card__title">Welcome back, <?= e($_SESSION['user_name'] ?? 'Member') ?></h1>
                <p class="profile-card__meta">Explore classes and manage your schedule.</p>
            </div>
        </div>
    </section>
</main>
<?php include __DIR__ . '/../includes/footer.php';
