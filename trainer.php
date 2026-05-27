<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

$trainer_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($trainer_id <= 0) {
    header('Location: /classes.php');
    exit;
}

$pdo = get_db();

$stmt = $pdo->prepare("
    SELECT id, name, username, profile_photo, role, is_active
    FROM users
    WHERE id = ? AND role = 'trainer' AND is_active = 1
");
$stmt->execute([$trainer_id]);
$trainer = $stmt->fetch();

if (!$trainer) {
    header('Location: /classes.php');
    exit;
}

$stmt = $pdo->prepare("SELECT bio, specializations, certifications FROM trainer_profiles WHERE user_id = ?");
$stmt->execute([$trainer_id]);
$profile = $stmt->fetch() ?: ['bio' => null, 'specializations' => null, 'certifications' => null];

$stmt = $pdo->prepare("
    SELECT c.id, c.name, c.description, c.difficulty, c.duration_minutes, c.capacity
    FROM classes c
    WHERE c.trainer_id = ? AND c.is_active = 1
    ORDER BY c.name
");
$stmt->execute([$trainer_id]);
$classes = $stmt->fetchAll();

$class_ids = array_column($classes, 'id');

$sessions_by_class = [];
if (!empty($class_ids)) {
    $placeholders = implode(',', array_fill(0, count($class_ids), '?'));
    $stmt = $pdo->prepare("
        SELECT s.class_id, s.day_of_week, s.start_time
        FROM sessions s
        WHERE s.class_id IN ($placeholders)
        ORDER BY CASE s.day_of_week
            WHEN 'monday'    THEN 1 WHEN 'tuesday'   THEN 2 WHEN 'wednesday' THEN 3
            WHEN 'thursday'  THEN 4 WHEN 'friday'    THEN 5 WHEN 'saturday'  THEN 6
            WHEN 'sunday'    THEN 7 END, s.start_time
    ");
    $stmt->execute($class_ids);
    foreach ($stmt->fetchAll() as $s) {
        $sessions_by_class[$s['class_id']][] = $s;
    }
}

$ratings = [];
if (!empty($class_ids)) {
    $placeholders = implode(',', array_fill(0, count($class_ids), '?'));
    $stmt = $pdo->prepare("
        SELECT c.id, ROUND(AVG(r.rating), 1) AS avg_rating, COUNT(r.id) AS review_count
        FROM classes c
        JOIN sessions s ON s.class_id = c.id
        JOIN enrollments e ON e.session_id = s.id
        JOIN reviews r ON r.enrollment_id = e.id
        WHERE c.id IN ($placeholders)
        GROUP BY c.id
    ");
    $stmt->execute($class_ids);
    foreach ($stmt->fetchAll() as $row) {
        $ratings[$row['id']] = $row;
    }
}

$day_abbr = [
    'monday' => 'Mon', 'tuesday' => 'Tue', 'wednesday' => 'Wed',
    'thursday' => 'Thu', 'friday' => 'Fri', 'saturday' => 'Sat', 'sunday' => 'Sun',
];

$difficulty_map = [
    'beginner'     => ['class' => 'easy', 'label' => 'All levels'],
    'intermediate' => ['class' => 'mid',  'label' => 'Intermediate'],
    'advanced'     => ['class' => 'hard', 'label' => 'Advanced'],
];

$initials = implode('', array_map(
    fn($p) => strtoupper($p[0] ?? ''),
    array_filter(explode(' ', $trainer['name']))
)) ?: 'T';

$page_title       = e($trainer['name']) . ' — Fitness Down';
$page_description = 'Trainer profile for ' . $trainer['name'] . ' at Fitness Down.';
$extra_css        = '/css/pages/trainer-public.css';

include __DIR__ . '/includes/header.php';
?>

<main class="trainer-public">

    <section class="tp-hero">
        <div class="tp-card">
            <div class="profile-avatar">
                <?php if ($trainer['profile_photo']): ?>
                    <img src="<?= e($trainer['profile_photo']) ?>" alt="<?= e($trainer['name']) ?>">
                <?php else: ?>
                    <span><?= e($initials) ?></span>
                <?php endif; ?>
            </div>
            <div class="tp-card__body">
                <span class="profile-role-badge profile-role-badge--trainer">Trainer</span>
                <h1 class="tp-card__name"><?= e($trainer['name']) ?></h1>
                <p class="tp-card__username">@<?= e($trainer['username']) ?></p>

                <?php if (!empty($profile['bio'])): ?>
                    <p class="tp-bio"><?= e($profile['bio']) ?></p>
                <?php endif; ?>

                <?php if (!empty($profile['specializations'])): ?>
                    <div class="tp-tags">
                        <?php foreach (array_map('trim', explode(',', $profile['specializations'])) as $tag): ?>
                            <?php if ($tag): ?>
                                <span class="tp-tag"><?= e($tag) ?></span>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($profile['certifications'])): ?>
                    <p class="tp-certs"><?= e($profile['certifications']) ?></p>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="tp-classes">
        <header class="tp-classes__header">
            <h2>Classes</h2>
            <p><?= count($classes) ?> class<?= count($classes) !== 1 ? 'es' : '' ?> taught by <?= e($trainer['name']) ?></p>
        </header>

        <?php if (empty($classes)): ?>
            <p class="tp-classes__empty">No active classes at the moment.</p>
        <?php else: ?>
            <div class="tp-class-grid">
                <?php foreach ($classes as $cls):
                    $diff     = $difficulty_map[$cls['difficulty']] ?? ['class' => 'easy', 'label' => $cls['difficulty']];
                    $sessions = $sessions_by_class[$cls['id']] ?? [];
                    $days     = array_unique(array_column($sessions, 'day_of_week'));
                    $day_disp = count($days) === 7
                              ? 'Daily'
                              : implode(' · ', array_map(fn($d) => $day_abbr[$d] ?? $d, $days));
                    $rating   = $ratings[$cls['id']] ?? null;
                    $first_time = $sessions[0]['start_time'] ?? null;
                ?>
                <div class="tp-class-card">
                    <div class="tp-class-card__top">
                        <h3 class="tp-class-card__name"><?= e($cls['name']) ?></h3>
                        <span class="crow__difficulty crow__difficulty--<?= $diff['class'] ?>"><?= e($diff['label']) ?></span>
                    </div>
                    <?php if (!empty($cls['description'])): ?>
                        <p class="tp-class-card__desc"><?= e($cls['description']) ?></p>
                    <?php endif; ?>
                    <div class="tp-class-card__meta">
                        <?php if ($day_disp): ?>
                            <span class="tp-class-card__days"><?= e($day_disp) ?></span>
                        <?php endif; ?>
                        <?php if ($first_time): ?>
                            <span class="tp-class-card__time"><?= e($first_time) ?> · <?= (int)$cls['duration_minutes'] ?> min</span>
                        <?php endif; ?>
                        <span class="tp-class-card__cap"><?= (int)$cls['capacity'] ?> spots</span>
                        <?php if ($rating): ?>
                            <span class="tp-class-card__rating">★ <?= e((string)$rating['avg_rating']) ?> (<?= (int)$rating['review_count'] ?>)</span>
                        <?php endif; ?>
                    </div>
                    <a href="/classes.php" class="tp-class-card__link">View schedule →</a>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
