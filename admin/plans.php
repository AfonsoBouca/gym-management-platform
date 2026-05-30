<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

require_role('admin');

$pdo     = get_db();
$errors  = [];
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token. Please try again.';
    } else {
        $action  = $_POST['action'] ?? '';
        $plan_id = (int)($_POST['plan_id'] ?? 0);

        // Add plan
        if ($action === 'add') {
            $name        = trim($_POST['name'] ?? '');
            $price       = $_POST['price'] !== '' ? (int)$_POST['price'] : -1;
            $description = trim($_POST['description'] ?? '');
            $features    = trim($_POST['features'] ?? '');

            if ($name === '') {
                $errors[] = 'Name is required.';
            } else {
                // Check uniqueness
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM membership_plans WHERE name = ?");
                $stmt->execute([$name]);
                if ((int)$stmt->fetchColumn() > 0) {
                    $errors[] = 'A plan with this name already exists.';
                }
            }

            if ($price < 0) {
                $errors[] = 'Price must be 0 or greater.';
            }
            if ($description === '') {
                $errors[] = 'Description is required.';
            }
            if ($features === '') {
                $errors[] = 'Features are required.';
            }

            if (empty($errors)) {
                $pdo->prepare("
                    INSERT INTO membership_plans (name, price, description, features, is_active)
                    VALUES (:name, :price, :description, :features, 1)
                ")->execute([
                    ':name'        => $name,
                    ':price'       => $price,
                    ':description' => $description,
                    ':features'    => $features,
                ]);
                $success = "Membership plan \"$name\" added successfully.";
            }

        // Edit plan
        } elseif ($action === 'edit') {
            $name        = trim($_POST['name'] ?? '');
            $price       = $_POST['price'] !== '' ? (int)$_POST['price'] : -1;
            $description = trim($_POST['description'] ?? '');
            $features    = trim($_POST['features'] ?? '');
            $is_active   = (int)($_POST['is_active'] ?? 1);

            if ($plan_id === 0) {
                $errors[] = 'Invalid plan selection.';
            }

            if ($name === '') {
                $errors[] = 'Name is required.';
            } else {
                // Check uniqueness excluding current plan
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM membership_plans WHERE name = ? AND id != ?");
                $stmt->execute([$name, $plan_id]);
                if ((int)$stmt->fetchColumn() > 0) {
                    $errors[] = 'A plan with this name already exists.';
                }
            }

            if ($price < 0) {
                $errors[] = 'Price must be 0 or greater.';
            }
            if ($description === '') {
                $errors[] = 'Description is required.';
            }
            if ($features === '') {
                $errors[] = 'Features are required.';
            }
            if ($is_active !== 0 && $is_active !== 1) {
                $errors[] = 'Invalid active status.';
            }

            if (empty($errors)) {
                $pdo->prepare("
                    UPDATE membership_plans
                    SET name = :name, price = :price, description = :description, features = :features, is_active = :is_active
                    WHERE id = :id
                ")->execute([
                    ':name'        => $name,
                    ':price'       => $price,
                    ':description' => $description,
                    ':features'    => $features,
                    ':is_active'   => $is_active,
                    ':id'          => $plan_id,
                ]);
                $success = "Membership plan \"$name\" updated successfully.";
            }

        // Remove plan
        } elseif ($action === 'remove') {
            if ($plan_id === 0) {
                $errors[] = 'Invalid plan selection.';
            } else {
                try {
                    $stmt = $pdo->prepare("SELECT name FROM membership_plans WHERE id = ?");
                    $stmt->execute([$plan_id]);
                    $pname = $stmt->fetchColumn() ?: 'Unknown';

                    $pdo->prepare("DELETE FROM membership_plans WHERE id = ?")->execute([$plan_id]);
                    $success = "Membership plan \"$pname\" removed.";
                } catch (PDOException $e) {
                    $errors[] = "Cannot delete this plan because some members are subscribed to it. Try setting it to 'Inactive' instead.";
                }
            }
        }
    }
}

// Fetch all plans
$plans = $pdo->query("SELECT id, name, price, description, features, is_active FROM membership_plans ORDER BY price ASC")->fetchAll();

// Stats
$total_plans    = count($plans);
$active_plans   = 0;
$inactive_plans = 0;
foreach ($plans as $p) {
    if ((int)$p['is_active'] === 1) {
        $active_plans++;
    } else {
        $inactive_plans++;
    }
}

$page_title = 'Manage Membership Plans — Fitness Down';
$extra_css  = '/css/pages/admin.css';

include __DIR__ . '/../includes/header.php';
?>

<main class="admin-dashboard">
    <section class="profile-page__hero">
        <div class="profile-card">
            <div class="profile-card__body">
                <span class="profile-role-badge profile-role-badge--admin">Admin</span>
                <h1 class="profile-card__title">Manage Membership Plans</h1>
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

    <!-- Plans Stats -->
    <section class="admin-section">
        <ul class="admin-stats">
            <li class="admin-stat">
                <p class="admin-stat__value"><?= (int)$total_plans ?></p>
                <p class="admin-stat__label">Total Plans</p>
            </li>
            <li class="admin-stat">
                <p class="admin-stat__value" style="color: #10B981;"><?= (int)$active_plans ?></p>
                <p class="admin-stat__label">Active</p>
            </li>
            <li class="admin-stat admin-stat--warn">
                <p class="admin-stat__value"><?= (int)$inactive_plans ?></p>
                <p class="admin-stat__label">Inactive</p>
            </li>
        </ul>
    </section>

    <!-- Add Plan -->
    <section class="admin-section">
        <header class="admin-section__header">
            <h2>Create New Plan</h2>
            <p>Add a new membership tier with custom pricing, description, and list of features.</p>
        </header>
        <div class="profile-form-card">
            <form class="profile-form" method="post" action="/admin/plans.php" novalidate>
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action"     value="add">

                <div class="profile-form__row">
                    <div class="form__group">
                        <label class="form__label">Plan Name</label>
                        <input type="text" name="name" class="form__input"
                               placeholder="e.g. Essential, All Access, Elite" required>
                    </div>
                    <div class="form__group">
                        <label class="form__label">Monthly Price (€)</label>
                        <input type="number" name="price" min="0" class="form__input"
                               placeholder="e.g. 50" required>
                    </div>
                </div>

                <div class="form__group">
                    <label class="form__label">Short Description</label>
                    <input type="text" name="description" class="form__input"
                           placeholder="Short summary of the plan's access levels" required>
                </div>

                <div class="form__group">
                    <label class="form__label">Features list <span style="color:var(--text-muted)">(separate with vertical bar '|')</span></label>
                    <textarea name="features" class="form__input" rows="3"
                              placeholder="e.g. Gym access Mon–Sat|06:30–22:30|2x classes per month" required></textarea>
                </div>

                <button type="submit" class="button button--primary">Create Plan</button>
            </form>
        </div>
    </section>

    <!-- Plans Catalog -->
    <section class="admin-section">
        <header class="admin-section__header">
            <h2>Current Membership Plans</h2>
            <p><?= count($plans) ?> plan<?= count($plans) !== 1 ? 's' : '' ?> registered.</p>
        </header>

        <?php if (empty($plans)): ?>
            <p class="admin-empty">No membership plans found in database.</p>
        <?php else: ?>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Price</th>
                        <th>Status</th>
                        <th>Description</th>
                        <th>Features</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($plans as $p): ?>
                    <tr>
                        <td class="admin-table__mono" style="font-weight: bold;"><?= e($p['name']) ?></td>
                        <td><?= (int)$p['price'] ?>€/mo</td>
                        <td>
                            <span class="admin-equip-status admin-equip-status--<?= (int)$p['is_active'] === 1 ? 'available' : 'maintenance' ?>">
                                <?= (int)$p['is_active'] === 1 ? 'Active' : 'Inactive' ?>
                            </span>
                        </td>
                        <td class="admin-table__muted" style="max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?= e($p['description']) ?>">
                            <?= e($p['description']) ?>
                        </td>
                        <td class="admin-table__muted" style="max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?= e(str_replace('|', ', ', $p['features'])) ?>">
                            <?= e(str_replace('|', ', ', $p['features'])) ?>
                        </td>
                        <td class="admin-table__actions">
                            <button type="button" class="button button--ghost admin-plan-edit-btn"
                                    data-id="<?= $p['id'] ?>"
                                    data-name="<?= e($p['name']) ?>"
                                    data-price="<?= (int)$p['price'] ?>"
                                    data-description="<?= e($p['description']) ?>"
                                    data-features="<?= e($p['features']) ?>"
                                    data-isactive="<?= (int)$p['is_active'] ?>">
                                Edit
                            </button>
                            <form method="post" action="/admin/plans.php" style="display:inline">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="action"     value="remove">
                                <input type="hidden" name="plan_id"    value="<?= $p['id'] ?>">
                                <button type="submit" class="button button--danger"
                                        onclick="return confirm('Are you sure you want to delete plan <?= e(addslashes($p['name'])) ?>?')">
                                    Delete
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

    <!-- Edit Plan Modal -->
    <div class="admin-modal" id="edit-plan-modal" aria-hidden="true">
        <div class="admin-modal__backdrop"></div>
        <div class="admin-modal__box" role="dialog" aria-labelledby="edit-plan-title">
            <header class="admin-modal__header">
                <h2 id="edit-plan-title">Edit Membership Plan</h2>
                <button type="button" class="admin-modal__close" aria-label="Close">✕</button>
            </header>
            <form class="profile-form" method="post" action="/admin/plans.php">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action"     value="edit">
                <input type="hidden" name="plan_id"    id="edit-plan-id">

                <div class="profile-form__row">
                    <div class="form__group">
                        <label class="form__label">Plan Name</label>
                        <input type="text" name="name" id="edit-plan-name" class="form__input" required>
                    </div>
                    <div class="form__group">
                        <label class="form__label">Monthly Price (€)</label>
                        <input type="number" name="price" id="edit-plan-price" min="0" class="form__input" required>
                    </div>
                </div>

                <div class="profile-form__row">
                    <div class="form__group">
                        <label class="form__label">Short Description</label>
                        <input type="text" name="description" id="edit-plan-description" class="form__input" required>
                    </div>
                    <div class="form__group">
                        <label class="form__label">Status</label>
                        <select name="is_active" id="edit-plan-isactive" class="form__input">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                </div>

                <div class="form__group">
                    <label class="form__label">Features list <span style="color:var(--text-muted)">(separate with vertical bar '|')</span></label>
                    <textarea name="features" id="edit-plan-features" class="form__input" rows="3" required></textarea>
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
    const modal     = document.getElementById('edit-plan-modal');
    const closeBtns = modal.querySelectorAll('.admin-modal__close');

    document.querySelectorAll('.admin-plan-edit-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.getElementById('edit-plan-id').value          = btn.dataset.id;
            document.getElementById('edit-plan-name').value        = btn.dataset.name;
            document.getElementById('edit-plan-price').value       = btn.dataset.price;
            document.getElementById('edit-plan-description').value = btn.dataset.description;
            document.getElementById('edit-plan-features').value    = btn.dataset.features;
            document.getElementById('edit-plan-isactive').value    = btn.dataset.isactive;
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

<?php include __DIR__ . '/../includes/footer.php';
