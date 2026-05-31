<?php
require_once __DIR__ . '/includes/auth.php';

try_remember_login();

// ── query data ─────────────────────────────────────────────────────
$pdo = get_db();

// all active classes + trainer name
$stmt = $pdo->query("
    SELECT c.*, u.name AS trainer_name
    FROM classes c
    JOIN users u ON c.trainer_id = u.id
    WHERE c.is_active = 1
    ORDER BY c.name
");
$classes = $stmt->fetchAll();

// all sessions for active classes
$stmt = $pdo->query("
    SELECT s.*, c.name AS class_name, c.id AS class_id
    FROM sessions s
    JOIN classes c ON s.class_id = c.id
    WHERE c.is_active = 1
    ORDER BY
        CASE s.day_of_week
            WHEN 'monday'    THEN 1
            WHEN 'tuesday'   THEN 2
            WHEN 'wednesday' THEN 3
            WHEN 'thursday'  THEN 4
            WHEN 'friday'    THEN 5
            WHEN 'saturday'  THEN 6
            WHEN 'sunday'    THEN 7
        END,
        s.start_time
");
$sessions = $stmt->fetchAll();

// group sessions by class
$sessions_by_class = [];
foreach ($sessions as $s) {
    $sessions_by_class[$s['class_id']][] = $s;
}

// average ratings per class (empty until reviews exist)
$stmt = $pdo->query("
    SELECT c.id, ROUND(AVG(r.rating), 1) AS avg_rating, COUNT(r.id) AS review_count
    FROM classes c
    JOIN sessions s ON s.class_id = c.id
    JOIN enrollments e ON e.session_id = s.id
    JOIN reviews r ON r.enrollment_id = e.id
    WHERE c.is_active = 1
    GROUP BY c.id
");
$ratings = [];
while ($row = $stmt->fetch()) {
    $ratings[$row['id']] = $row;
}

// enrolled sessions for logged-in member
$enrolled_sessions = [];
$waitlisted_sessions = []; // session_id => waitlist_pos
if (!empty($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("
        SELECT session_id FROM enrollments
        WHERE member_id = ? AND status = 'enrolled'
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $enrolled_sessions = array_column($stmt->fetchAll(), 'session_id');

    $stmt = $pdo->prepare("
        SELECT session_id, waitlist_pos FROM enrollments
        WHERE member_id = ? AND status = 'waitlisted'
    ");
    $stmt->execute([$_SESSION['user_id']]);
    while ($row = $stmt->fetch()) {
        $waitlisted_sessions[(int)$row['session_id']] = (int)$row['waitlist_pos'];
    }
}

$enrollment_counts = [];
$stmt = $pdo->query("SELECT session_id, COUNT(*) AS cnt FROM enrollments WHERE status = 'enrolled' GROUP BY session_id");
while ($row = $stmt->fetch()) {
    $enrollment_counts[$row['session_id']] = (int)$row['cnt'];
}

// ── maps & helpers ─────────────────────────────────────────────────
$difficulty_map = [
    'beginner'     => ['class' => 'easy', 'label' => 'All levels'],
    'intermediate' => ['class' => 'mid',  'label' => 'Intermediate'],
    'advanced'     => ['class' => 'hard', 'label' => 'Advanced'],
];

$day_abbr = [
    'monday' => 'Mon', 'tuesday' => 'Tue', 'wednesday' => 'Wed',
    'thursday' => 'Thu', 'friday' => 'Fri', 'saturday' => 'Sat', 'sunday' => 'Sun',
];

$class_images = [
    'Muay Thai'              => 'muay_thai.jpg',
    'Hyrox'                  => 'hyrox.jpg',
    'Pilates'                => 'pilates.jpg',
    'Boxing'                 => 'boxing.jpg',
    'Stretching & Mobility'  => 'stretching.jpg',
    'Yoga'                   => 'yoga.jpg',
];

$class_colors = [
    'Muay Thai'              => '#ef4444',
    'Hyrox'                  => '#f97316',
    'Pilates'                => '#a855f7',
    'Boxing'                 => '#3b82f6',
    'Stretching & Mobility'  => '#22c55e',
    'Yoga'                   => '#eab308',
];

$days       = ['monday','tuesday','wednesday','thursday','friday','saturday','sunday'];
$day_labels = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
$tt_start   = 7;   // 07:00
$tt_end     = 21;  // 21:00
$tt_slots   = ($tt_end - $tt_start) * 4; // 15-min slots

// unique class names (types) and trainers for filters
$class_types = array_unique(array_column($classes, 'name'));
sort($class_types);

$trainers = [];
foreach ($classes as $c) {
    $trainers[$c['trainer_id']] = $c['trainer_name'];
}
asort($trainers);

// ── selected class (timetable view) ────────────────────────────────
$selected_class_id = isset($_GET['class']) ? (int)$_GET['class'] : null;
$view = $selected_class_id ? 'timetable' : 'list';

// ── page meta & includes ──────────────────────────────────────────
$page_title       = 'Classes — Fitness Down';
$page_description = 'Fitness Down — Classes. Choose your discipline.';
$extra_js         = '/js/classes.js';
require __DIR__ . '/includes/header.php';
?>

<?php if (($_SESSION['role'] ?? '') === 'member'): ?>
<script>const CSRF_TOKEN = <?= json_encode(csrf_token()) ?>;</script>
<?php endif; ?>
<main>

    <!-- ── hero ────────────────────────────────────────────────── -->
    <section class="page-hero">
        <p class="eyebrow">Train harder</p>
        <h1 class="page-hero__title">Classes</h1>
        <p class="page-hero__sub"><?= count($classes) ?> disciplines. All levels. Every week.</p>
    </section>

    <!-- ── view controls ───────────────────────────────────────── -->
    <section class="view-controls" aria-label="View options">
        <div class="view-controls__inner">
            <div class="view-toggle" role="tablist" aria-label="Switch view">
                <button class="view-toggle__btn <?= $view === 'list' ? 'view-toggle__btn--active' : '' ?>"
                        data-view="list" type="button" role="tab"
                        aria-selected="<?= $view === 'list' ? 'true' : 'false' ?>" id="tab-list">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <rect x="3" y="3" width="7" height="7"></rect>
                        <rect x="14" y="3" width="7" height="7"></rect>
                        <rect x="3" y="14" width="7" height="7"></rect>
                        <rect x="14" y="14" width="7" height="7"></rect>
                    </svg>
                    Grid
                </button>
                <button class="view-toggle__btn <?= $view === 'timetable' ? 'view-toggle__btn--active' : '' ?>"
                        data-view="timetable" type="button" role="tab"
                        aria-selected="<?= $view === 'timetable' ? 'true' : 'false' ?>" id="tab-timetable">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <rect x="3" y="3" width="18" height="18" rx="2"></rect>
                        <line x1="3" y1="9" x2="21" y2="9"></line>
                        <line x1="3" y1="15" x2="21" y2="15"></line>
                        <line x1="9" y1="3" x2="9" y2="21"></line>
                        <line x1="15" y1="3" x2="15" y2="21"></line>
                    </svg>
                    Schedule
                </button>
            </div>
        </div>
    </section>


    <!-- ================================================================
         VIEW 1 — CARD GRID
         ================================================================ -->
    <div id="view-list" class="classes-view <?= $view !== 'list' ? 'classes-view--hidden' : '' ?>"
         role="tabpanel" aria-labelledby="tab-list">

        <!-- filters -->
        <section class="filters" aria-label="Filter classes">
            <div class="filters__inner">
                <div class="filters__group" data-filter="day">
                    <span class="filters__label">Day</span>
                    <div class="filters__buttons">
                        <button class="filter-btn filter-btn--active" data-value="all" type="button">All</button>
                        <button class="filter-btn" data-value="monday"    type="button">Mon</button>
                        <button class="filter-btn" data-value="tuesday"   type="button">Tue</button>
                        <button class="filter-btn" data-value="wednesday" type="button">Wed</button>
                        <button class="filter-btn" data-value="thursday"  type="button">Thu</button>
                        <button class="filter-btn" data-value="friday"    type="button">Fri</button>
                        <button class="filter-btn" data-value="saturday"  type="button">Sat</button>
                        <button class="filter-btn" data-value="sunday"    type="button">Sun</button>
                    </div>
                </div>
                <div class="filters__group" data-filter="time">
                    <span class="filters__label">Time</span>
                    <div class="filters__buttons">
                        <button class="filter-btn filter-btn--active" data-value="all" type="button">All</button>
                        <button class="filter-btn" data-value="morning"   type="button">Morning</button>
                        <button class="filter-btn" data-value="afternoon" type="button">Afternoon</button>
                        <button class="filter-btn" data-value="night"     type="button">Night</button>
                    </div>
                </div>
                <div class="filters__group" data-filter="type">
                    <span class="filters__label">Type</span>
                    <select class="filter-select" id="filter-type">
                        <option value="all">All</option>
                        <?php foreach ($class_types as $type): ?>
                            <option value="<?= e($type) ?>"><?= e($type) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filters__group" data-filter="trainer">
                    <span class="filters__label">Trainer</span>
                    <select class="filter-select" id="filter-trainer">
                        <option value="all">All</option>
                        <?php foreach ($trainers as $tid => $tname): ?>
                            <option value="<?= (int)$tid ?>"><?= e($tname) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </section>

        <!-- class cards -->
        <section class="class-list" aria-label="All classes">
            <div class="class-list__rows">
                <?php foreach ($classes as $cls):
                    $diff          = $difficulty_map[$cls['difficulty']] ?? ['class' => 'easy', 'label' => $cls['difficulty']];
                    $cls_sessions  = $sessions_by_class[$cls['id']] ?? [];
                    $cls_days      = array_unique(array_column($cls_sessions, 'day_of_week'));
                    $day_display   = count($cls_days) === 7
                                   ? 'Daily'
                                   : implode(' · ', array_map(fn($d) => $day_abbr[$d] ?? $d, $cls_days));
                    $start_time    = $cls_sessions[0]['start_time'] ?? '00:00';
                    $start_hour    = (int) explode(':', $start_time)[0];
                    $time_period   = $start_hour < 12 ? 'morning' : ($start_hour <= 19 ? 'afternoon' : 'night');
                    $image         = !empty($cls['photo']) ? $cls['photo'] : ($class_images[$cls['name']] ?? null);
                    $color         = $class_colors[$cls['name']] ?? 'var(--accent)';
                    $rating        = $ratings[$cls['id']] ?? null;
                ?>
                <article class="crow"
                         data-class-id="<?= $cls['id'] ?>"
                         data-class-name="<?= e($cls['name']) ?>"
                         data-trainer-id="<?= (int)$cls['trainer_id'] ?>"
                         data-days="<?= e(implode(',', $cls_days)) ?>"
                         data-time="<?= e($time_period) ?>"
                         style="--card-accent: <?= e($color) ?>;">
                    <?php if ($image): ?>
                    <figure class="crow__media">
                        <img src="/assets/images/classes/<?= e($image) ?>"
                             alt="<?= e($cls['name']) ?>"
                             class="crow__image" loading="lazy">
                    </figure>
                    <?php endif; ?>
                    <div class="crow__info">
                        <h2 class="crow__name"><?= e($cls['name']) ?></h2>
                        <p class="crow__desc"><?= e($cls['description'] ?? '') ?></p>
                    </div>
                    <div class="crow__meta">
                        <a class="crow__instructor" href="/trainer.php?id=<?= (int)$cls['trainer_id'] ?>"><?= e($cls['trainer_name']) ?></a>
                        <p class="crow__difficulty crow__difficulty--<?= $diff['class'] ?>">
                            <?= e($diff['label']) ?>
                        </p>
                    </div>
                    <div class="crow__schedule">
                        <p class="crow__days"><?= e($day_display) ?></p>
                        <p class="crow__time">
                            <?= e($start_time) ?> &nbsp;·&nbsp; <?= $cls['duration_minutes'] ?> min
                        </p>
                    </div>
                    <?php if (($_SESSION['role'] ?? '') === 'member'): ?>
                        <div class="crow__sessions">
                            <?php foreach ($cls_sessions as $sess):
                                $sess_id    = (int)$sess['id'];
                                $is_enrolled = in_array($sess_id, $enrolled_sessions, true);
                                $is_waitlisted = isset($waitlisted_sessions[$sess_id]);
                                $wl_pos     = $waitlisted_sessions[$sess_id] ?? 0;
                                $count      = $enrollment_counts[$sess_id] ?? 0;
                                $capacity   = (int)$cls['capacity'];
                                $free       = $capacity - $count;
                            ?>
                            <div class="crow__session-row">
                                <div class="crow__session-info">
                                    <span class="crow__session-day"><?= e($day_abbr[$sess['day_of_week']] ?? $sess['day_of_week']) ?></span>
                                    <span class="crow__session-time"><?= e($sess['start_time']) ?></span>
                                </div>
                                <span class="crow__spots <?= $is_waitlisted ? 'crow__spots--waitlisted' : ($free <= 0 && !$is_enrolled ? 'crow__spots--full' : '') ?>">
                                    <?php if ($is_enrolled): ?>Enrolled
                                    <?php elseif ($is_waitlisted): ?>Waitlisted (#<?= $wl_pos ?>)
                                    <?php elseif ($free <= 0): ?>Full
                                    <?php else: ?><?= $free ?> spot<?= $free === 1 ? '' : 's' ?> left
                                    <?php endif; ?>
                                </span>
                                <?php if ($is_enrolled): ?>
                                <button type="button"
                                        class="button button--ghost crow__enroll-btn crow__enroll-btn--enrolled"
                                        data-session-id="<?= $sess_id ?>"
                                        data-enrolled="1"
                                        data-waitlisted="0">
                                    Cancel
                                </button>
                                <?php elseif ($is_waitlisted): ?>
                                <button type="button"
                                        class="button button--ghost crow__enroll-btn crow__enroll-btn--waitlisted"
                                        data-session-id="<?= $sess_id ?>"
                                        data-enrolled="0"
                                        data-waitlisted="1"
                                        data-waitlist-pos="<?= $wl_pos ?>">
                                    Leave Waitlist
                                </button>
                                <?php elseif ($free <= 0): ?>
                                <button type="button"
                                        class="button button--ghost crow__enroll-btn"
                                        data-session-id="<?= $sess_id ?>"
                                        data-enrolled="0"
                                        data-waitlisted="0">
                                    Join Waitlist
                                </button>
                                <?php else: ?>
                                <button type="button"
                                        class="button button--ghost crow__enroll-btn"
                                        data-session-id="<?= $sess_id ?>"
                                        data-enrolled="0"
                                        data-waitlisted="0">
                                    Enroll
                                </button>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php elseif (!isset($_SESSION['user_id'])): ?>
                        <div class="crow__login-prompt">
                            <a href="/login.php">Login to enroll</a>
                        </div>
                    <?php else: ?>
                        <button type="button"
                                class="button button--ghost crow__book-btn"
                                data-class-id="<?= $cls['id'] ?>"
                                aria-label="View <?= e($cls['name']) ?> schedule">
                            View Schedule
                        </button>
                    <?php endif; ?>
                </article>
                <?php endforeach; ?>
            </div>
        </section>
    </div>


    <!-- ================================================================
         VIEW 2 — TIMETABLE
         ================================================================ -->
    <div id="view-timetable" class="classes-view <?= $view !== 'timetable' ? 'classes-view--hidden' : '' ?>"
         role="tabpanel" aria-labelledby="tab-timetable">

        <!-- class switcher (replaces filters in this view) -->
        <section class="class-switcher" aria-label="Highlight a class">
            <div class="class-switcher__inner">
                <button class="class-switcher__btn <?= !$selected_class_id ? 'class-switcher__btn--active' : '' ?>"
                        data-class-id="0" type="button"
                        style="--sw-color: var(--text-muted);">
                    All
                </button>
                <?php foreach ($classes as $cls):
                    $color = $class_colors[$cls['name']] ?? 'var(--accent)';
                ?>
                <button class="class-switcher__btn <?= $selected_class_id === $cls['id'] ? 'class-switcher__btn--active' : '' ?>"
                        data-class-id="<?= $cls['id'] ?>"
                        type="button"
                        style="--sw-color: <?= e($color) ?>;">
                    <?= e($cls['name']) ?>
                </button>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- timetable grid -->
        <section class="timetable" aria-label="Weekly class schedule">
            <div class="timetable__scroll">
                <div class="timetable__grid" style="--tt-rows: <?= $tt_slots ?>;">

                    <!-- corner cell -->
                    <div class="timetable__corner"></div>

                    <!-- day headers -->
                    <?php foreach ($day_labels as $i => $dl): ?>
                        <div class="timetable__day-header"
                             style="grid-column: <?= $i + 2 ?>; grid-row: 1;">
                            <?= $dl ?>
                        </div>
                    <?php endforeach; ?>

                    <!-- time labels + grid lines -->
                    <?php for ($h = $tt_start; $h < $tt_end; $h++): ?>
                        <?php $row = ($h - $tt_start) * 4 + 2; ?>
                        <div class="timetable__time"
                             style="grid-column: 1; grid-row: <?= $row ?> / span 4;">
                            <?= sprintf('%02d:00', $h) ?>
                        </div>
                        <div class="timetable__gridline"
                             style="grid-column: 2 / -1; grid-row: <?= $row ?>;"
                             aria-hidden="true"></div>
                    <?php endfor; ?>

                    <!-- session cells -->
                    <?php foreach ($sessions as $s):
                        $day_idx = array_search($s['day_of_week'], $days);
                        if ($day_idx === false) continue;

                        $col = $day_idx + 2;
                        [$sh, $sm] = explode(':', $s['start_time']);
                        $start_slot = ((int)$sh - $tt_start) * 4 + intdiv((int)$sm, 15) + 2;
                        $span       = (int) ceil($s['duration_minutes'] / 15);

                        $is_active = ($selected_class_id === (int)$s['class_id']);
                        $is_dimmed = $selected_class_id && !$is_active;

                        // find class info
                        $cls_info = null;
                        foreach ($classes as $c) {
                            if ($c['id'] == $s['class_id']) { $cls_info = $c; break; }
                        }
                        $color   = $class_colors[$cls_info['name'] ?? ''] ?? 'var(--accent)';
                        $end_min = (int)$sh * 60 + (int)$sm + $s['duration_minutes'];
                        $end_t   = sprintf('%02d:%02d', intdiv($end_min, 60), $end_min % 60);

                        $cell_classes = 'timetable__cell';
                        if ($is_active) $cell_classes .= ' timetable__cell--active';
                        if ($is_dimmed) $cell_classes .= ' timetable__cell--dim';
                    ?>
                    <div class="<?= $cell_classes ?>"
                         data-class-id="<?= $s['class_id'] ?>"
                         data-session-id="<?= $s['id'] ?>"
                         style="grid-column: <?= $col ?>;
                                grid-row: <?= $start_slot ?> / span <?= $span ?>;
                                --cell-color: <?= e($color) ?>;">
                        <span class="timetable__cell-name"><?= e($s['class_name']) ?></span>
                        <span class="timetable__cell-time"><?= e($s['start_time']) ?> – <?= e($end_t) ?></span>
                        <span class="timetable__cell-trainer"><?= e($cls_info['trainer_name'] ?? '') ?></span>
                    </div>
                    <?php endforeach; ?>

                </div>
            </div>
        </section>
    </div>

</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
