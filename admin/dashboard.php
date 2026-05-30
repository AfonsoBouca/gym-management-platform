<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

require_role('admin');

$pdo = get_db();

$total_members  = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'member'  AND is_active = 1")->fetchColumn();
$total_trainers = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'trainer' AND is_active = 1")->fetchColumn();
$total_classes  = $pdo->query("SELECT COUNT(*) FROM classes WHERE is_active = 1")->fetchColumn();
$total_equip    = $pdo->query("SELECT COUNT(*) FROM equipment")->fetchColumn();
$equip_issues   = $pdo->query("SELECT COUNT(*) FROM equipment WHERE status = 'maintenance'")->fetchColumn();
$open_enroll    = $pdo->query("SELECT COUNT(*) FROM enrollments WHERE status = 'enrolled'")->fetchColumn();
$total_plans    = $pdo->query("SELECT COUNT(*) FROM membership_plans")->fetchColumn();

$page_title = 'Admin Dashboard — Fitness Down';
$page_description = 'Administration dashboard for managing users, classes, and equipment.';
$extra_css        = '/css/pages/admin.css';

include __DIR__ . '/../includes/header.php';
?>

<main class="admin-dashboard">
    <section class="profile-page__hero">
        <div class="profile-card">
            <div class="profile-card__body">
                <span class="profile-role-badge profile-role-badge--admin">Admin</span>
                <h1 class="profile-card__title">Welcome, <?= e($_SESSION['user_name'] ?? 'Admin') ?></h1>
                <p class="profile-card__meta">Monitor users, classes, and equipment from here.</p>
            </div>
        </div>
    </section>

<!-- stats -->
    <section class="admin-section">
        <ul class="admin-stats">
            <li class="admin-stat">
                <p class="admin-stat__value"><?= (int)$total_members ?></p>
                <p class="admin-stat__label">Active Members</p>
            </li>
            <li class="admin-stat">
                <p class="admin-stat__value"><?= (int)$total_trainers ?></p>
                <p class="admin-stat__label">Active Trainers</p>
            </li>
            <li class="admin-stat">
                <p class="admin-stat__value"><?= (int)$total_classes ?></p>
                <p class="admin-stat__label">Active Classes</p>
            </li>
            <li class="admin-stat">
                <p class="admin-stat__value"><?= (int)$total_plans ?></p>
                <p class="admin-stat__label">Membership Plans</p>
            </li>
            <li class="admin-stat">
                <p class="admin-stat__value"><?= (int)$open_enroll ?></p>
                <p class="admin-stat__label">Enrollments</p>
            </li>
            <li class="admin-stat <?= $equip_issues > 0 ? 'admin-stat--warn' : '' ?>">
                <p class="admin-stat__value"><?= (int)$equip_issues ?></p>
                <p class="admin-stat__label">Equipment Issues</p>
            </li>
        </ul>
    </section>

<!-- navigation -->
    <section class="admin-section">
        <header class="admin-section__header">
            <h2>Control Panel</h2>
            <p>Select a section to manage.</p>
        </header>
 
        <ul class="admin-nav-cards">
            <li>
                <a href="/admin/users.php" class="admin-nav-card">
                    <span class="admin-nav-card__icon" aria-hidden="true">👤</span>
                    <span class="admin-nav-card__title">Users</span>
                    <span class="admin-nav-card__desc">Manage members and trainers. Create, update, deactivate, or elevate accounts.</span>
                    <span class="admin-nav-card__arrow" aria-hidden="true">→</span>
                </a>
            </li>
            <li>
                <a href="/admin/classes.php" class="admin-nav-card">
                    <span class="admin-nav-card__icon" aria-hidden="true">🏋️</span>
                    <span class="admin-nav-card__title">Classes</span>
                    <span class="admin-nav-card__desc">Create, edit, and remove classes from the catalog. Assign trainers and sessions.</span>
                    <span class="admin-nav-card__arrow" aria-hidden="true">→</span>
                </a>
            </li>
            <li>
                <a href="/admin/plans.php" class="admin-nav-card">
                    <span class="admin-nav-card__icon" aria-hidden="true">💳</span>
                    <span class="admin-nav-card__title">Plans</span>
                    <span class="admin-nav-card__desc">Manage membership subscription tiers, prices, descriptions, and feature details.</span>
                    <span class="admin-nav-card__arrow" aria-hidden="true">→</span>
                </a>
            </li>
            <li>
                <a href="/admin/equipment.php" class="admin-nav-card">
                    <span class="admin-nav-card__icon" aria-hidden="true">⚙️</span>
                    <span class="admin-nav-card__title">Equipment</span>
                    <span class="admin-nav-card__desc">Add, update availability, and remove equipment from the main training area.</span>
                    <span class="admin-nav-card__arrow" aria-hidden="true">→</span>
                </a>
            </li>
        </ul>
    </section>
</main>
<?php include __DIR__ . '/../includes/footer.php';
