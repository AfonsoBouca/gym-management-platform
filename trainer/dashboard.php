<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

require_role('trainer');

$pdo        = get_db();
$trainer_id = (int)$_SESSION['user_id'];

$columns = $pdo->query("PRAGMA table_info(sessions)")->fetchAll();
$col_names = array_column($columns, 'name');
if (!in_array('status', $col_names, true)) {
    $pdo->exec("ALTER TABLE sessions ADD COLUMN status TEXT NOT NULL DEFAULT 'scheduled'");
}
if (!in_array('notes', $col_names, true)) {
    $pdo->exec("ALTER TABLE sessions ADD COLUMN notes TEXT");
}

$stmt = $pdo->prepare("
    SELECT c.id, c.name, c.description, c.difficulty, c.duration_minutes, c.capacity
    FROM classes c
    WHERE c.trainer_id = ? AND c.is_active = 1
    ORDER BY c.name
");
$stmt->execute([$trainer_id]);
$classes = $stmt->fetchAll();

$class_ids = array_column($classes, 'id');

$sessions = [];
if (!empty($class_ids)) {
    $placeholders = implode(',', array_fill(0, count($class_ids), '?'));
    $stmt = $pdo->prepare("
        SELECT s.id AS session_id, s.class_id, s.day_of_week, s.start_time, s.duration_minutes,
               c.name AS class_name, c.capacity,
               COALESCE(s.status, 'scheduled') AS status, s.notes
        FROM sessions s
        JOIN classes c ON c.id = s.class_id
        WHERE s.class_id IN ($placeholders)
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
            s.start_time ASC
    ");
    $stmt->execute($class_ids);
    $sessions = $stmt->fetchAll();
}

$enrollment_counts = [];
if (!empty($sessions)) {
    $session_ids   = array_column($sessions, 'session_id');
    $placeholders2 = implode(',', array_fill(0, count($session_ids), '?'));
    $stmt = $pdo->prepare("
        SELECT session_id, COUNT(*) AS cnt
        FROM enrollments
        WHERE session_id IN ($placeholders2) AND status = 'enrolled'
        GROUP BY session_id
    ");
    $stmt->execute($session_ids);
    while ($row = $stmt->fetch()) {
        $enrollment_counts[(int)$row['session_id']] = (int)$row['cnt'];
    }
}

$day_label = [
    'monday'    => 'Monday',
    'tuesday'   => 'Tuesday',
    'wednesday' => 'Wednesday',
    'thursday'  => 'Thursday',
    'friday'    => 'Friday',
    'saturday'  => 'Saturday',
    'sunday'    => 'Sunday',
];

$day_abbr = [
    'monday' => 'Mon', 'tuesday' => 'Tue', 'wednesday' => 'Wed',
    'thursday' => 'Thu', 'friday' => 'Fri', 'saturday' => 'Sat', 'sunday' => 'Sun',
];

$page_title       = 'Trainer Dashboard — Fitness Down';
$page_description = 'Manage your profile, view your class schedule, and check enrolled members.';
$extra_css        = '/css/pages/trainer.css';
$extra_js         = '/js/trainer.js';

include __DIR__ . '/../includes/header.php';
?>

<script>var CSRF_TOKEN = <?= json_encode(csrf_token()) ?>;</script>

<main class="trainer-dashboard">

    <section class="profile-page__hero">
        <div class="profile-card">
            <div class="profile-card__body">
                <span class="profile-role-badge profile-role-badge--trainer">Trainer</span>
                <h1 class="profile-card__title">Welcome, <?= e($_SESSION['user_name'] ?? 'Trainer') ?></h1>
                <p class="profile-card__meta">View your schedule and class rosters. <a href="/profile.php" style="color:var(--text);text-decoration:underline;text-underline-offset:3px;">Return to profile →</a></p>
            </div>
        </div>
    </section>


    <section class="trainer-section" id="trainer-schedule-section">
        <header class="trainer-section__header">
            <h2>My Schedule</h2>
            <p>Your weekly class sessions and current enrollment status.</p>
        </header>

        <?php if (empty($sessions)): ?>
            <p class="trainer-schedule__empty">You have no sessions assigned yet.</p>
        <?php else: ?>
            <div class="trainer-schedule-wrap">
                <table class="trainer-schedule">
                    <thead>
                        <tr>
                            <th>Day</th>
                            <th>Class</th>
                            <th>Time</th>
                            <th>Duration</th>
                            <th>Enrolled</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sessions as $sess):
                            $count    = $enrollment_counts[(int)$sess['session_id']] ?? 0;
                            $capacity = (int)$sess['capacity'];
                            $end_min  = (int)explode(':', $sess['start_time'])[0] * 60
                                      + (int)explode(':', $sess['start_time'])[1]
                                      + (int)$sess['duration_minutes'];
                            $end_time = sprintf('%02d:%02d', intdiv($end_min, 60), $end_min % 60);
                            $status   = $sess['status'] ?? 'scheduled';
                        ?>
                        <tr class="schedule-row" data-session-id="<?= $sess['session_id'] ?>" data-status="<?= e($status) ?>">
                            <td class="schedule-day"><?= e($day_label[$sess['day_of_week']] ?? $sess['day_of_week']) ?></td>
                            <td class="schedule-class"><?= e($sess['class_name']) ?></td>
                            <td class="schedule-time"><?= e($sess['start_time']) ?> – <?= e($end_time) ?></td>
                            <td class="schedule-duration"><?= $sess['duration_minutes'] ?> min</td>
                            <td class="schedule-spots">
                                <span class="schedule-spots__enrolled"><?= $count ?></span>
                                <span class="schedule-spots__capacity">/ <?= $capacity ?></span>
                            </td>
                            <td>
                                <span class="schedule-status schedule-status--<?= e($status) ?>">
                                    <?= e(ucfirst($status)) ?>
                                </span>
                            </td>
                            <td class="schedule-actions">
                                <?php if ($status === 'scheduled'): ?>
                                    <button type="button" class="schedule-action-btn schedule-action-btn--complete"
                                            data-session-id="<?= $sess['session_id'] ?>" data-action="complete"
                                            title="Mark as completed">✓</button>
                                    <button type="button" class="schedule-action-btn schedule-action-btn--cancel"
                                            data-session-id="<?= $sess['session_id'] ?>" data-action="cancel"
                                            title="Cancel session">✕</button>
                                <?php else: ?>
                                    <button type="button" class="schedule-action-btn schedule-action-btn--reactivate"
                                            data-session-id="<?= $sess['session_id'] ?>" data-action="reactivate"
                                            title="Reactivate session">↺</button>
                                <?php endif; ?>
                                <button type="button" class="schedule-action-btn schedule-action-btn--notes"
                                        data-session-id="<?= $sess['session_id'] ?>"
                                        title="Edit notes">✎</button>
                            </td>
                        </tr>
                        <tr class="schedule-notes-row" data-session-id="<?= $sess['session_id'] ?>" style="display:none;">
                            <td colspan="7">
                                <div class="schedule-notes-editor">
                                    <textarea class="form__input schedule-notes-input"
                                              placeholder="Session notes…" rows="2"><?= e($sess['notes'] ?? '') ?></textarea>
                                    <div class="schedule-notes-actions">
                                        <button type="button" class="button button--primary schedule-notes-save"
                                                data-session-id="<?= $sess['session_id'] ?>">Save Notes</button>
                                        <button type="button" class="schedule-notes-cancel">Cancel</button>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>


    <section class="trainer-section" id="trainer-roster-section">
        <header class="trainer-section__header">
            <h2>Class Rosters</h2>
            <p>Click a session to view enrolled members.</p>
        </header>

        <?php if (empty($sessions)): ?>
            <p class="trainer-roster__empty-all">No sessions to display.</p>
        <?php else: ?>
            <ul class="trainer-roster-list">
                <?php foreach ($sessions as $sess):
                    $count    = $enrollment_counts[(int)$sess['session_id']] ?? 0;
                    $capacity = (int)$sess['capacity'];
                ?>
                <li class="trainer-roster-item">
                    <button type="button"
                            class="trainer-roster-toggle"
                            data-session-id="<?= $sess['session_id'] ?>"
                            aria-expanded="false">
                        <span class="trainer-roster-toggle__info">
                            <span class="trainer-roster-toggle__class"><?= e($sess['class_name']) ?></span>
                            <span class="trainer-roster-toggle__meta">
                                <?= e($day_abbr[$sess['day_of_week']] ?? $sess['day_of_week']) ?>
                                · <?= e($sess['start_time']) ?>
                                · <?= $count ?>/<?= $capacity ?> enrolled
                            </span>
                        </span>
                        <span class="trainer-roster-toggle__arrow" aria-hidden="true">▼</span>
                    </button>
                    <div class="trainer-roster-panel">
                        <p class="trainer-roster-panel__loading">Click to load roster…</p>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

</main>

<?php include __DIR__ . '/../includes/footer.php';
