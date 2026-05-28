<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

require_role('admin');

$pdo     = get_db();
$errors  = [];
$success = '';

// fetch trainers for dropdowns 
$trainers = $pdo->query(
    "SELECT id, name FROM users WHERE role = 'trainer' AND is_active = 1 ORDER BY name"
)->fetchAll();

// handle form submissions 
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token. Please try again.';
    } else {
        $action   = $_POST['action']   ?? '';
        $class_id = (int)($_POST['class_id'] ?? 0);

        // create class 
        if ($action === 'create_class') {
            $name       = trim($_POST['name']        ?? '');
            $trainer_id = (int)($_POST['trainer_id'] ?? 0);
            $difficulty = $_POST['difficulty']        ?? '';
            $duration   = (int)($_POST['duration']   ?? 0);
            $capacity   = (int)($_POST['capacity']   ?? 0);
            $description = trim($_POST['description'] ?? '');

            if ($name === '')                                                    $errors[] = 'Class name is required.';
            if ($trainer_id === 0)                                              $errors[] = 'A trainer must be assigned.';
            if (!in_array($difficulty, ['beginner','intermediate','advanced'])) $errors[] = 'Invalid difficulty.';
            if ($duration <= 0)                                                 $errors[] = 'Duration must be greater than 0.';
            if ($capacity <= 0)                                                 $errors[] = 'Capacity must be greater than 0.';

            if (empty($errors)) {
                $pdo->prepare(
                    "INSERT INTO classes (trainer_id, name, description, difficulty, duration_minutes, capacity)
                     VALUES (:tid, :name, :desc, :diff, :dur, :cap)"
                )->execute([
                    ':tid'  => $trainer_id, ':name' => $name,
                    ':desc' => $description ?: null,
                    ':diff' => $difficulty,  ':dur'  => $duration, ':cap' => $capacity,
                ]);
                $success = "Class \"$name\" created.";
            }

        // update class 
        } elseif ($action === 'update_class') {
            $name        = trim($_POST['name']        ?? '');
            $trainer_id  = (int)($_POST['trainer_id'] ?? 0);
            $difficulty  = $_POST['difficulty']        ?? '';
            $duration    = (int)($_POST['duration']   ?? 0);
            $capacity    = (int)($_POST['capacity']   ?? 0);
            $description = trim($_POST['description'] ?? '');

            if ($class_id === 0)                                                $errors[] = 'Invalid class.';
            if ($name === '')                                                    $errors[] = 'Class name is required.';
            if ($trainer_id === 0)                                              $errors[] = 'A trainer must be assigned.';
            if (!in_array($difficulty, ['beginner','intermediate','advanced'])) $errors[] = 'Invalid difficulty.';
            if ($duration <= 0)                                                 $errors[] = 'Duration must be greater than 0.';
            if ($capacity <= 0)                                                 $errors[] = 'Capacity must be greater than 0.';

            if (empty($errors)) {
                $pdo->prepare(
                    "UPDATE classes SET trainer_id=:tid, name=:name, description=:desc,
                     difficulty=:diff, duration_minutes=:dur, capacity=:cap
                     WHERE id=:id"
                )->execute([
                    ':tid'  => $trainer_id, ':name' => $name,
                    ':desc' => $description ?: null,
                    ':diff' => $difficulty, ':dur'  => $duration,
                    ':cap'  => $capacity,   ':id'   => $class_id,
                ]);
                $success = 'Class updated.';
            }

        // remove class (soft delete) 
        } elseif ($action === 'remove_class') {
            if ($class_id === 0) {
                $errors[] = 'Invalid class.';
            } else {
                $pdo->prepare(
                    "UPDATE classes SET is_active = 0 WHERE id = ?"
                )->execute([$class_id]);
                $success = 'Class removed from catalog.';
            }

        // add session 
        } elseif ($action === 'add_session') {
            $day      = $_POST['day_of_week']     ?? '';
            $time     = $_POST['start_time']      ?? '';
            $duration = (int)($_POST['duration']  ?? 0);
            $days     = ['monday','tuesday','wednesday','thursday','friday','saturday','sunday'];

            if ($class_id === 0)            $errors[] = 'Invalid class.';
            if (!in_array($day, $days))     $errors[] = 'Invalid day.';
            if (!preg_match('/^\d{2}:\d{2}$/', $time)) $errors[] = 'Invalid time format (HH:MM).';
            if ($duration <= 0)             $errors[] = 'Duration must be greater than 0.';

            if (empty($errors)) {
                $pdo->prepare(
                    "INSERT INTO sessions (class_id, day_of_week, start_time, duration_minutes)
                     VALUES (:cid, :day, :time, :dur)"
                )->execute([
                    ':cid' => $class_id, ':day' => $day,
                    ':time' => $time,    ':dur' => $duration,
                ]);
                $success = 'Session added.';
            }

        // remove session 
        } elseif ($action === 'remove_session') {
            $session_id = (int)($_POST['session_id'] ?? 0);
            if ($session_id === 0) {
                $errors[] = 'Invalid session.';
            } else {
                $pdo->prepare("DELETE FROM sessions WHERE id = ?")->execute([$session_id]);
                $success = 'Session removed.';
            }
        }
    }
}

// fetch classes with trainer name 
$filter_trainer    = (int)($_GET['trainer'] ?? 0);
$filter_difficulty = $_GET['difficulty'] ?? '';

$where  = ['c.is_active = 1'];
$params = [];

if ($filter_trainer > 0) {
    $where[]           = 'c.trainer_id = :tid';
    $params[':tid']    = $filter_trainer;
}
if (in_array($filter_difficulty, ['beginner','intermediate','advanced'])) {
    $where[]           = 'c.difficulty = :diff';
    $params[':diff']   = $filter_difficulty;
}

$stmt = $pdo->prepare(
    "SELECT c.id, c.name, c.description, c.difficulty, c.duration_minutes, c.capacity,
            u.name AS trainer_name, u.id AS trainer_id
       FROM classes c
       JOIN users u ON u.id = c.trainer_id
      WHERE " . implode(' AND ', $where) . "
      ORDER BY c.name"
);
$stmt->execute($params);
$classes = $stmt->fetchAll();

// fetch sessions for all returned classes
$sessions_by_class = [];
if (!empty($classes)) {
    $cids = array_column($classes, 'id');
    $ph   = implode(',', array_fill(0, count($cids), '?'));
    $rows = $pdo->prepare(
        "SELECT id, class_id, day_of_week, start_time, duration_minutes
           FROM sessions WHERE class_id IN ($ph)
           ORDER BY CASE day_of_week
               WHEN 'monday' THEN 1 WHEN 'tuesday' THEN 2 WHEN 'wednesday' THEN 3
               WHEN 'thursday' THEN 4 WHEN 'friday' THEN 5 WHEN 'saturday' THEN 6
               WHEN 'sunday' THEN 7 END, start_time"
    );
    $rows->execute($cids);
    foreach ($rows->fetchAll() as $s) {
        $sessions_by_class[(int)$s['class_id']][] = $s;
    }
}

$day_label = [
    'monday' => 'Mon', 'tuesday' => 'Tue', 'wednesday' => 'Wed',
    'thursday' => 'Thu', 'friday' => 'Fri', 'saturday' => 'Sat', 'sunday' => 'Sun',
];

$page_title = 'Manage Classes — Fitness Down';
$extra_css  = '/css/pages/admin.css';

include __DIR__ . '/../includes/header.php';
?>

<main class="admin-dashboard">

    <section class="profile-page__hero">
        <div class="profile-card">
            <div class="profile-card__body">
                <span class="profile-role-badge profile-role-badge--admin">Admin</span>
                <h1 class="profile-card__title">Manage Classes</h1>
                <p class="profile-card__meta"><a href="/admin/dashboard.php" class="admin-back">← Back to dashboard</a></p>
            </div>
        </div>
    </section>

    <?php if ($success): ?>
        <div class="admin-notice admin-notice--success"><?= e($success) ?></div>
    <?php endif; ?>
    <?php if (!empty($errors)): ?>
        <div class="admin-notice admin-notice--error">
            <ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <!--  create class  -->
    <section class="admin-section">
        <header class="admin-section__header">
            <h2>Create Class</h2>
            <p>Add a new class to the catalog.</p>
        </header>
        <div class="profile-form-card">
            <form class="profile-form" method="post" action="/admin/classes.php" novalidate>
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action"     value="create_class">

                <div class="profile-form__row">
                    <div class="form__group">
                        <label class="form__label">Class Name</label>
                        <input type="text" name="name" class="form__input" required>
                    </div>
                    <div class="form__group">
                        <label class="form__label">Trainer</label>
                        <select name="trainer_id" class="form__input" required>
                            <option value="">Select trainer…</option>
                            <?php foreach ($trainers as $t): ?>
                                <option value="<?= $t['id'] ?>"><?= e($t['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="profile-form__row">
                    <div class="form__group">
                        <label class="form__label">Difficulty</label>
                        <select name="difficulty" class="form__input">
                            <option value="beginner">Beginner</option>
                            <option value="intermediate">Intermediate</option>
                            <option value="advanced">Advanced</option>
                        </select>
                    </div>
                    <div class="form__group">
                        <label class="form__label">Duration (minutes)</label>
                        <input type="number" name="duration" class="form__input" min="1" value="60">
                    </div>
                    <div class="form__group">
                        <label class="form__label">Capacity</label>
                        <input type="number" name="capacity" class="form__input" min="1" value="20">
                    </div>
                </div>
                <div class="form__group">
                    <label class="form__label">Description <span style="color:var(--text-muted)">(optional)</span></label>
                    <textarea name="description" class="form__input" rows="2"></textarea>
                </div>
                <button type="submit" class="button button--primary">Create Class</button>
            </form>
        </div>
    </section>

    <!-- filters -->
    <section class="admin-section">
        <header class="admin-section__header">
            <h2>Class Catalog</h2>
            <p><?= count($classes) ?> class<?= count($classes) !== 1 ? 'es' : '' ?> found.</p>
        </header>

        <form class="admin-filters" method="get" action="/admin/classes.php">
            <select name="trainer" class="form__input admin-filters__select">
                <option value="">All trainers</option>
                <?php foreach ($trainers as $t): ?>
                    <option value="<?= $t['id'] ?>" <?= $filter_trainer === (int)$t['id'] ? 'selected' : '' ?>>
                        <?= e($t['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select name="difficulty" class="form__input admin-filters__select">
                <option value="">All difficulties</option>
                <option value="beginner"     <?= $filter_difficulty === 'beginner'     ? 'selected' : '' ?>>Beginner</option>
                <option value="intermediate" <?= $filter_difficulty === 'intermediate' ? 'selected' : '' ?>>Intermediate</option>
                <option value="advanced"     <?= $filter_difficulty === 'advanced'     ? 'selected' : '' ?>>Advanced</option>
            </select>
            <button type="submit" class="button button--primary">Filter</button>
            <a href="/admin/classes.php" class="button button--ghost">Reset</a>
        </form>

        <?php if (empty($classes)): ?>
            <p class="admin-empty">No active classes found.</p>
        <?php else: ?>
            <ul class="admin-class-list">
                <?php foreach ($classes as $c):
                    $sessions = $sessions_by_class[(int)$c['id']] ?? [];
                ?>
                <li class="admin-class-item">
                    <div class="admin-class-header">
                        <div class="admin-class-info">
                            <span class="admin-class-name"><?= e($c['name']) ?></span>
                            <span class="admin-class-meta">
                                <?= e($c['trainer_name']) ?> ·
                                <?= e(ucfirst($c['difficulty'])) ?> ·
                                <?= $c['duration_minutes'] ?> min ·
                                <?= $c['capacity'] ?> spots
                            </span>
                            <?php if ($c['description']): ?>
                                <span class="admin-class-desc"><?= e($c['description']) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="admin-class-actions">
                            <button type="button" class="button button--ghost admin-class-edit-btn"
                                    data-id="<?= $c['id'] ?>"
                                    data-name="<?= e($c['name']) ?>"
                                    data-trainer="<?= $c['trainer_id'] ?>"
                                    data-difficulty="<?= e($c['difficulty']) ?>"
                                    data-duration="<?= $c['duration_minutes'] ?>"
                                    data-capacity="<?= $c['capacity'] ?>"
                                    data-description="<?= e($c['description'] ?? '') ?>">
                                Edit
                            </button>
                            <form method="post" action="/admin/classes.php" style="display:inline">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="action"     value="remove_class">
                                <input type="hidden" name="class_id"   value="<?= $c['id'] ?>">
                                <button type="submit" class="button button--danger"
                                        onclick="return confirm('Remove class <?= e(addslashes($c['name'])) ?>?')">
                                    Remove
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- sessions sub-list -->
                    <div class="admin-sessions">
                        <p class="admin-sessions__title">Sessions</p>
                        <?php if (empty($sessions)): ?>
                            <p class="admin-sessions__empty">No sessions yet.</p>
                        <?php else: ?>
                            <ul class="admin-session-list">
                                <?php foreach ($sessions as $s): ?>
                                <li class="admin-session-row">
                                    <span class="admin-session-info">
                                        <?= e($day_label[$s['day_of_week']] ?? $s['day_of_week']) ?>
                                        · <?= e($s['start_time']) ?>
                                        · <?= $s['duration_minutes'] ?> min
                                    </span>
                                    <form method="post" action="/admin/classes.php" style="display:inline">
                                        <input type="hidden" name="csrf_token"  value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="action"      value="remove_session">
                                        <input type="hidden" name="session_id"  value="<?= $s['id'] ?>">
                                        <button type="submit" class="admin-session-remove"
                                                onclick="return confirm('Remove this session?')">✕</button>
                                    </form>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>

                        <!-- add session inline form -->
                        <form class="admin-session-add" method="post" action="/admin/classes.php">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="action"     value="add_session">
                            <input type="hidden" name="class_id"   value="<?= $c['id'] ?>">
                            <select name="day_of_week" class="form__input admin-session-add__field">
                                <?php foreach (['monday','tuesday','wednesday','thursday','friday','saturday','sunday'] as $d): ?>
                                    <option value="<?= $d ?>"><?= ucfirst($d) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="time" name="start_time" class="form__input admin-session-add__field" required>
                            <input type="number" name="duration" class="form__input admin-session-add__field"
                                   placeholder="Min" min="1" value="60" style="width:5rem">
                            <button type="submit" class="button button--primary admin-session-add__btn">+ Add</button>
                        </form>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <!-- edit class modal/window -->
    <div class="admin-modal" id="edit-class-modal" aria-hidden="true">
        <div class="admin-modal__backdrop"></div>
        <div class="admin-modal__box" role="dialog" aria-labelledby="edit-class-title">
            <header class="admin-modal__header">
                <h2 id="edit-class-title">Edit Class</h2>
                <button type="button" class="admin-modal__close" aria-label="Close">✕</button>
            </header>
            <form class="profile-form" method="post" action="/admin/classes.php" novalidate>
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action"     value="update_class">
                <input type="hidden" name="class_id"   id="edit-class-id">

                <div class="profile-form__row">
                    <div class="form__group">
                        <label class="form__label">Class Name</label>
                        <input type="text" name="name" id="edit-class-name" class="form__input" required>
                    </div>
                    <div class="form__group">
                        <label class="form__label">Trainer</label>
                        <select name="trainer_id" id="edit-class-trainer" class="form__input">
                            <?php foreach ($trainers as $t): ?>
                                <option value="<?= $t['id'] ?>"><?= e($t['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="profile-form__row">
                    <div class="form__group">
                        <label class="form__label">Difficulty</label>
                        <select name="difficulty" id="edit-class-difficulty" class="form__input">
                            <option value="beginner">Beginner</option>
                            <option value="intermediate">Intermediate</option>
                            <option value="advanced">Advanced</option>
                        </select>
                    </div>
                    <div class="form__group">
                        <label class="form__label">Duration (min)</label>
                        <input type="number" name="duration" id="edit-class-duration" class="form__input" min="1">
                    </div>
                    <div class="form__group">
                        <label class="form__label">Capacity</label>
                        <input type="number" name="capacity" id="edit-class-capacity" class="form__input" min="1">
                    </div>
                </div>
                <div class="form__group">
                    <label class="form__label">Description</label>
                    <textarea name="description" id="edit-class-description" class="form__input" rows="2"></textarea>
                </div>
                <div class="admin-modal__footer">
                    <button type="submit" class="button button--primary">Save Changes</button>
                    <button type="button" class="button button--ghost admin-modal__close">Cancel</button>
                </div>
            </form>
        </div>
    </div>

</main>

<script>
(() => {
    const modal    = document.getElementById('edit-class-modal');
    const closeBtns = modal.querySelectorAll('.admin-modal__close');

    document.querySelectorAll('.admin-class-edit-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.getElementById('edit-class-id').value          = btn.dataset.id;
            document.getElementById('edit-class-name').value        = btn.dataset.name;
            document.getElementById('edit-class-trainer').value     = btn.dataset.trainer;
            document.getElementById('edit-class-difficulty').value  = btn.dataset.difficulty;
            document.getElementById('edit-class-duration').value    = btn.dataset.duration;
            document.getElementById('edit-class-capacity').value    = btn.dataset.capacity;
            document.getElementById('edit-class-description').value = btn.dataset.description;
            modal.setAttribute('aria-hidden', 'false');
            modal.classList.add('is-open');
        });
    });

    closeBtns.forEach(btn => btn.addEventListener('click', () => {
        modal.setAttribute('aria-hidden', 'true');
        modal.classList.remove('is-open');
    }));

    modal.querySelector('.admin-modal__backdrop').addEventListener('click', () => {
        modal.setAttribute('aria-hidden', 'true');
        modal.classList.remove('is-open');
    });
})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>