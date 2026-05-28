<?php
declare(strict_types=1);
 
require_once __DIR__ . '/../includes/auth.php';
 
require_role('admin');
 
$pdo     = get_db();
$errors  = [];
$success = '';
 
// handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token. Please try again.';
    } else {
        $action  = $_POST['action']   ?? '';
        $equip_id = (int)($_POST['equip_id'] ?? 0);
 
        // add equipment
        if ($action === 'add') {
            $type   = trim($_POST['type']   ?? '');
            $name   = trim($_POST['name']   ?? '');
            $status = $_POST['status']       ?? '';
            $notes  = trim($_POST['notes']  ?? '');
 
            if ($type === '')                                                    $errors[] = 'Type is required.';
            if ($name === '')                                                    $errors[] = 'Name is required.';
            if (!in_array($status, ['available','in_use','maintenance']))        $errors[] = 'Invalid status.';
 
            if (empty($errors)) {
                $pdo->prepare(
                    "INSERT INTO equipment (type, name, status, notes) VALUES (:type, :name, :status, :notes)"
                )->execute([
                    ':type'   => $type,
                    ':name'   => $name,
                    ':status' => $status,
                    ':notes'  => $notes ?: null,
                ]);
                $success = "Equipment \"$name\" added.";
            }
 
        // update status 
        } elseif ($action === 'update_status') {
            $status = $_POST['status'] ?? '';
            $notes  = trim($_POST['notes'] ?? '');
 
            if ($equip_id === 0)                                                $errors[] = 'Invalid equipment.';
            if (!in_array($status, ['available','in_use','maintenance']))        $errors[] = 'Invalid status.';
 
            if (empty($errors)) {
                $pdo->prepare(
                    "UPDATE equipment SET status = :status, notes = :notes WHERE id = :id"
                )->execute([
                    ':status' => $status,
                    ':notes'  => $notes ?: null,
                    ':id'     => $equip_id,
                ]);
                $success = 'Equipment status updated.';
            }
 
        // remove equipment 
        } elseif ($action === 'remove') {
            if ($equip_id === 0) {
                $errors[] = 'Invalid equipment.';
            } else {
                $pdo->prepare("DELETE FROM equipment WHERE id = ?")->execute([$equip_id]);
                $success = 'Equipment removed.';
            }
        }
    }
}
 
// fetch equipment 
$filter_status = $_GET['status'] ?? '';
$filter_type   = trim($_GET['type'] ?? '');
 
$where  = ['1=1'];
$params = [];
 
if (in_array($filter_status, ['available','in_use','maintenance'])) {
    $where[]           = 'status = :status';
    $params[':status'] = $filter_status;
}
if ($filter_type !== '') {
    $where[]           = 'type LIKE :type';
    $params[':type']   = "%{$filter_type}%";
}
 
$stmt = $pdo->prepare(
    "SELECT id, type, name, status, notes FROM equipment
      WHERE " . implode(' AND ', $where) . "
      ORDER BY type, name"
);
$stmt->execute($params);
$equipment = $stmt->fetchAll();
 
// summary counts
$counts = $pdo->query(
    "SELECT status, COUNT(*) AS cnt FROM equipment GROUP BY status"
)->fetchAll(PDO::FETCH_KEY_PAIR);
 
$page_title = 'Manage Equipment — Fitness Down';
$extra_css  = '/css/pages/admin.css';
 
include __DIR__ . '/../includes/header.php';
?>
 
<main class="admin-dashboard">
 
    <section class="profile-page__hero">
        <div class="profile-card">
            <div class="profile-card__body">
                <span class="profile-role-badge profile-role-badge--admin">Admin</span>
                <h1 class="profile-card__title">Manage Equipment</h1>
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
 
    <!-- status summary  -->
    <section class="admin-section">
        <ul class="admin-stats">
            <li class="admin-stat">
                <p class="admin-stat__value"><?= (int)($counts['available']   ?? 0) ?></p>
                <p class="admin-stat__label">Available</p>
            </li>
            <li class="admin-stat">
                <p class="admin-stat__value"><?= (int)($counts['in_use']      ?? 0) ?></p>
                <p class="admin-stat__label">In Use</p>
            </li>
            <li class="admin-stat admin-stat--warn">
                <p class="admin-stat__value"><?= (int)($counts['maintenance'] ?? 0) ?></p>
                <p class="admin-stat__label">Maintenance</p>
            </li>
        </ul>
    </section>
 
    <!-- add equipment  -->
    <section class="admin-section">
        <header class="admin-section__header">
            <h2>Add Equipment</h2>
            <p>Register a new piece of equipment in the training area.</p>
        </header>
        <div class="profile-form-card">
            <form class="profile-form" method="post" action="/admin/equipment.php" novalidate>
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action"     value="add">
 
                <div class="profile-form__row">
                    <div class="form__group">
                        <label class="form__label">Type</label>
                        <input type="text" name="type" class="form__input"
                               placeholder="e.g. Cardio, Weights, Machines" required>
                    </div>
                    <div class="form__group">
                        <label class="form__label">Name</label>
                        <input type="text" name="name" class="form__input"
                               placeholder="e.g. Treadmill #3" required>
                    </div>
                </div>
                <div class="profile-form__row">
                    <div class="form__group">
                        <label class="form__label">Status</label>
                        <select name="status" class="form__input">
                            <option value="available">Available</option>
                            <option value="in_use">In Use</option>
                            <option value="maintenance">Maintenance</option>
                        </select>
                    </div>
                    <div class="form__group">
                        <label class="form__label">Notes <span style="color:var(--text-muted)">(optional)</span></label>
                        <input type="text" name="notes" class="form__input"
                               placeholder="e.g. Belt worn, due for service">
                    </div>
                </div>
                <button type="submit" class="button button--primary">Add Equipment</button>
            </form>
        </div>
    </section>
 
    <!-- equipment list -->
    <section class="admin-section">
        <header class="admin-section__header">
            <h2>Equipment Inventory</h2>
            <p><?= count($equipment) ?> item<?= count($equipment) !== 1 ? 's' : '' ?> found.</p>
        </header>
 
        <form class="admin-filters" method="get" action="/admin/equipment.php">
            <input type="text" name="type" class="form__input admin-filters__search"
                   placeholder="Filter by type…" value="<?= e($filter_type) ?>">
            <select name="status" class="form__input admin-filters__select">
                <option value="">All statuses</option>
                <option value="available"   <?= $filter_status === 'available'   ? 'selected' : '' ?>>Available</option>
                <option value="in_use"      <?= $filter_status === 'in_use'      ? 'selected' : '' ?>>In Use</option>
                <option value="maintenance" <?= $filter_status === 'maintenance' ? 'selected' : '' ?>>Maintenance</option>
            </select>
            <button type="submit" class="button button--primary">Filter</button>
            <a href="/admin/equipment.php" class="button button--ghost">Reset</a>
        </form>
 
        <?php if (empty($equipment)): ?>
            <p class="admin-empty">No equipment found.</p>
        <?php else: ?>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Name</th>
                        <th>Status</th>
                        <th>Notes</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($equipment as $eq): ?>
                    <tr>
                        <td class="admin-table__mono"><?= e($eq['type']) ?></td>
                        <td><?= e($eq['name']) ?></td>
                        <td>
                            <span class="admin-equip-status admin-equip-status--<?= e($eq['status']) ?>">
                                <?= e(str_replace('_', ' ', ucfirst($eq['status']))) ?>
                            </span>
                        </td>
                        <td class="admin-table__muted"><?= e($eq['notes'] ?? '—') ?></td>
                        <td class="admin-table__actions">
                            <button type="button" class="button button--ghost admin-equip-edit-btn"
                                    data-id="<?= $eq['id'] ?>"
                                    data-status="<?= e($eq['status']) ?>"
                                    data-notes="<?= e($eq['notes'] ?? '') ?>">
                                Update
                            </button>
                            <form method="post" action="/admin/equipment.php" style="display:inline">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="action"     value="remove">
                                <input type="hidden" name="equip_id"   value="<?= $eq['id'] ?>">
                                <button type="submit" class="button button--danger"
                                        onclick="return confirm('Remove <?= e(addslashes($eq['name'])) ?>?')">
                                    Remove
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </section>
 
    <!-- update status modal/window -->
    <div class="admin-modal" id="edit-equip-modal" aria-hidden="true">
        <div class="admin-modal__backdrop"></div>
        <div class="admin-modal__box" role="dialog" aria-labelledby="edit-equip-title">
            <header class="admin-modal__header">
                <h2 id="edit-equip-title">Update Status</h2>
                <button type="button" class="admin-modal__close" aria-label="Close">✕</button>
            </header>
            <form class="profile-form" method="post" action="/admin/equipment.php">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action"     value="update_status">
                <input type="hidden" name="equip_id"   id="edit-equip-id">
 
                <div class="form__group">
                    <label class="form__label">Status</label>
                    <select name="status" id="edit-equip-status" class="form__input">
                        <option value="available">Available</option>
                        <option value="in_use">In Use</option>
                        <option value="maintenance">Maintenance</option>
                    </select>
                </div>
                <div class="form__group">
                    <label class="form__label">Notes</label>
                    <input type="text" name="notes" id="edit-equip-notes" class="form__input"
                           placeholder="Optional details">
                </div>
                <div class="admin-modal__footer">
                    <button type="submit" class="button button--primary">Save</button>
                    <button type="button" class="button button--ghost admin-modal__close">Cancel</button>
                </div>
            </form>
        </div>
    </div>
 
</main>
 
<script>
(() => {
    const modal     = document.getElementById('edit-equip-modal');
    const closeBtns = modal.querySelectorAll('.admin-modal__close');
 
    document.querySelectorAll('.admin-equip-edit-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.getElementById('edit-equip-id').value     = btn.dataset.id;
            document.getElementById('edit-equip-status').value = btn.dataset.status;
            document.getElementById('edit-equip-notes').value  = btn.dataset.notes;
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