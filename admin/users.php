<?php
declare(strict_types=1);
 
require_once __DIR__ . '/../includes/auth.php';
 
require_role('admin');
 
$pdo      = get_db();
$admin_id = (int)$_SESSION['user_id'];
 
$errors  = [];
$success = '';
 
// handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token. Please try again.';
    } else {
        $action  = $_POST['action'] ?? '';
        $user_id = (int)($_POST['user_id'] ?? 0);
 
        // create user
        if ($action === 'create') {
            $name     = trim($_POST['name']     ?? '');
            $username = trim($_POST['username'] ?? '');
            $email    = trim($_POST['email']    ?? '');
            $password =      $_POST['password'] ?? '';
            $role     =      $_POST['role']     ?? '';
 
            if ($name === '')                                       $errors[] = 'Name is required.';
            if ($username === '')                                   $errors[] = 'Username is required.';
            if (!filter_var($email, FILTER_VALIDATE_EMAIL))        $errors[] = 'Valid email is required.';
            if (strlen($password) < 8)                             $errors[] = 'Password must be at least 8 characters.';
            if (!in_array($role, ['member', 'trainer', 'admin']))  $errors[] = 'Invalid role.';
 
            if (empty($errors)) {
                $check = $pdo->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
                $check->execute([$email, $username]);
                if ($check->fetch()) {
                    $errors[] = 'Email or username already in use.';
                } else {
                    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                    $pdo->prepare(
                        "INSERT INTO users (name, username, email, password_hash, role)
                         VALUES (:name, :username, :email, :hash, :role)"
                    )->execute([
                        ':name'     => $name,
                        ':username' => $username,
                        ':email'    => $email,
                        ':hash'     => $hash,
                        ':role'     => $role,
                    ]);
 
                    // if creating a trainer, also create their profile row
                    if ($role === 'trainer') {
                        $new_id = (int)$pdo->lastInsertId();
                        $pdo->prepare(
                            "INSERT OR IGNORE INTO trainer_profiles (user_id) VALUES (?)"
                        )->execute([$new_id]);
                    }
 
                    $success = "Account for {$name} created successfully.";
                }
            }
 
        // update user 
        } elseif ($action === 'update') {
            $name     = trim($_POST['name']     ?? '');
            $username = trim($_POST['username'] ?? '');
            $email    = trim($_POST['email']    ?? '');
            $role     =      $_POST['role']     ?? '';
            $password =      $_POST['password'] ?? '';
 
            if ($user_id === 0)                                    $errors[] = 'Invalid user.';
            if ($name === '')                                       $errors[] = 'Name is required.';
            if ($username === '')                                   $errors[] = 'Username is required.';
            if (!filter_var($email, FILTER_VALIDATE_EMAIL))        $errors[] = 'Valid email is required.';
            if (!in_array($role, ['member', 'trainer', 'admin']))  $errors[] = 'Invalid role.';
 
            if (empty($errors)) {
                // check uniqueness excluding current user
                $check = $pdo->prepare(
                    "SELECT id FROM users WHERE (email = ? OR username = ?) AND id != ?"
                );
                $check->execute([$email, $username, $user_id]);
                if ($check->fetch()) {
                    $errors[] = 'Email or username already in use by another account.';
                } else {
                    if ($password !== '') {
                        $pdo->prepare(
                            "UPDATE users SET name=:name, username=:username, email=:email,
                             role=:role, password_hash=:hash WHERE id=:id"
                        )->execute([
                            ':name' => $name, ':username' => $username,
                            ':email' => $email, ':role' => $role,
                            ':hash' => password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]),
                            ':id'   => $user_id,
                        ]);
                    } else {
                        $pdo->prepare(
                            "UPDATE users SET name=:name, username=:username,
                             email=:email, role=:role WHERE id=:id"
                        )->execute([
                            ':name' => $name, ':username' => $username,
                            ':email' => $email, ':role' => $role, ':id' => $user_id,
                        ]);
                    }
 
                    // ensure trainer profile row exists if promoted to trainer
                    if ($role === 'trainer') {
                        $pdo->prepare(
                            "INSERT OR IGNORE INTO trainer_profiles (user_id) VALUES (?)"
                        )->execute([$user_id]);
                    }
 
                    $success = 'Account updated successfully.';
                }
            }
 
        // toggle active
        } elseif ($action === 'toggle_active') {
            if ($user_id === $admin_id) {
                $errors[] = 'You cannot deactivate your own account.';
            } else {
                $pdo->prepare(
                    "UPDATE users SET is_active = CASE WHEN is_active = 1 THEN 0 ELSE 1 END
                     WHERE id = ?"
                )->execute([$user_id]);
                $success = 'Account status updated.';
            }
 
        // give admin role
        } elseif ($action === 'elevate') {
            if ($user_id === 0) {
                $errors[] = 'Invalid user.';
            } else {
                $pdo->prepare(
                    "UPDATE users SET role = 'admin' WHERE id = ?"
                )->execute([$user_id]);
                $success = 'User elevated to admin.';
            }
        }
    }
}
 
// fetch all users
$filter_role   = $_GET['role']   ?? '';
$filter_status = $_GET['status'] ?? '';
$search        = trim($_GET['q'] ?? '');
 
$where  = ['1=1'];
$params = [];
 
if (in_array($filter_role, ['member', 'trainer', 'admin'])) {
    $where[]  = 'role = :role';
    $params[':role'] = $filter_role;
}
if ($filter_status === 'active') {
    $where[] = 'is_active = 1';
} elseif ($filter_status === 'inactive') {
    $where[] = 'is_active = 0';
}
if ($search !== '') {
    $where[]       = '(name LIKE :q OR email LIKE :q OR username LIKE :q)';
    $params[':q']  = "%{$search}%";
}
 
$sql   = "SELECT id, name, username, email, role, is_active, created_at
            FROM users
           WHERE " . implode(' AND ', $where) . "
           ORDER BY created_at DESC";
$stmt  = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();
 
$page_title       = 'Manage Users — Fitness Down';
$page_description = 'Admin user management.';
$extra_css        = '/css/pages/admin.css';
 
include __DIR__ . '/../includes/header.php';
?>
 
<main class="admin-dashboard">
 
    <section class="profile-page__hero">
        <div class="profile-card">
            <div class="profile-card__body">
                <span class="profile-role-badge profile-role-badge--admin">Admin</span>
                <h1 class="profile-card__title">Manage Users</h1>
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
 
    <!--  create user -->
    <section class="admin-section">
        <header class="admin-section__header">
            <h2>Create Account</h2>
            <p>Add a new member, trainer, or admin.</p>
        </header>
        <div class="profile-form-card">
            <form class="profile-form" method="post" action="/admin/users.php" novalidate>
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action"     value="create">
 
                <div class="profile-form__row">
                    <div class="form__group">
                        <label class="form__label">Full Name</label>
                        <input type="text" name="name" class="form__input" required>
                    </div>
                    <div class="form__group">
                        <label class="form__label">Username</label>
                        <input type="text" name="username" class="form__input" required>
                    </div>
                </div>
                <div class="profile-form__row">
                    <div class="form__group">
                        <label class="form__label">Email</label>
                        <input type="email" name="email" class="form__input" required>
                    </div>
                    <div class="form__group">
                        <label class="form__label">Password</label>
                        <input type="password" name="password" class="form__input" required>
                    </div>
                </div>
                <div class="form__group">
                    <label class="form__label">Role</label>
                    <select name="role" class="form__input">
                        <option value="member">Member</option>
                        <option value="trainer">Trainer</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <button type="submit" class="button button--primary">Create Account</button>
            </form>
        </div>
    </section>
 
    <!--  filters  -->
    <section class="admin-section">
        <header class="admin-section__header">
            <h2>All Users</h2>
            <p><?= count($users) ?> user<?= count($users) !== 1 ? 's' : '' ?> found.</p>
        </header>
 
        <form class="admin-filters" method="get" action="/admin/users.php">
            <input type="text" name="q" class="form__input admin-filters__search"
                   placeholder="Search name, email, username…"
                   value="<?= e($search) ?>">
            <select name="role" class="form__input admin-filters__select">
                <option value="">All roles</option>
                <option value="member"  <?= $filter_role === 'member'  ? 'selected' : '' ?>>Members</option>
                <option value="trainer" <?= $filter_role === 'trainer' ? 'selected' : '' ?>>Trainers</option>
                <option value="admin"   <?= $filter_role === 'admin'   ? 'selected' : '' ?>>Admins</option>
            </select>
            <select name="status" class="form__input admin-filters__select">
                <option value="">All statuses</option>
                <option value="active"   <?= $filter_status === 'active'   ? 'selected' : '' ?>>Active</option>
                <option value="inactive" <?= $filter_status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
            </select>
            <button type="submit" class="button button--primary">Filter</button>
            <a href="/admin/users.php" class="button button--ghost">Reset</a>
        </form>
 
        <!--  user table  -->
        <?php if (empty($users)): ?>
            <p class="admin-empty">No users match your filters.</p>
        <?php else: ?>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Joined</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                    <tr class="<?= !$u['is_active'] ? 'admin-table__row--inactive' : '' ?>">
                        <td><?= e($u['name']) ?></td>
                        <td class="admin-table__mono">@<?= e($u['username']) ?></td>
                        <td><?= e($u['email']) ?></td>
                        <td>
                            <span class="profile-role-badge profile-role-badge--<?= e($u['role']) ?>">
                                <?= e(ucfirst($u['role'])) ?>
                            </span>
                        </td>
                        <td>
                            <span class="admin-status admin-status--<?= $u['is_active'] ? 'active' : 'inactive' ?>">
                                <?= $u['is_active'] ? 'Active' : 'Inactive' ?>
                            </span>
                        </td>
                        <td class="admin-table__mono"><?= e(date('d M Y', strtotime($u['created_at']))) ?></td>
                        <td class="admin-table__actions">
                            <!-- edit trigger -->
                            <button type="button"
                                    class="button button--ghost admin-edit-btn"
                                    data-id="<?= $u['id'] ?>"
                                    data-name="<?= e($u['name']) ?>"
                                    data-username="<?= e($u['username']) ?>"
                                    data-email="<?= e($u['email']) ?>"
                                    data-role="<?= e($u['role']) ?>">
                                Edit
                            </button>
 
                            <!-- toggle active -->
                            <form method="post" action="/admin/users.php" style="display:inline">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="action"     value="toggle_active">
                                <input type="hidden" name="user_id"    value="<?= $u['id'] ?>">
                                <button type="submit"
                                        class="button <?= $u['is_active'] ? 'button--danger' : 'button--ghost' ?>"
                                        <?= $u['id'] === $admin_id ? 'disabled title="Cannot deactivate yourself"' : '' ?>>
                                    <?= $u['is_active'] ? 'Deactivate' : 'Reactivate' ?>
                                </button>
                            </form>
 
                            <!-- elevate to admin -->
                            <?php if ($u['role'] !== 'admin'): ?>
                            <form method="post" action="/admin/users.php" style="display:inline">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="action"     value="elevate">
                                <input type="hidden" name="user_id"    value="<?= $u['id'] ?>">
                                <button type="submit" class="button button--ghost"
                                        onclick="return confirm('Elevate <?= e(addslashes($u['name'])) ?> to admin?')">
                                    → Admin
                                </button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </section>
 
    <!--  edit modal/window  -->
    <div class="admin-modal" id="edit-user-modal" aria-hidden="true">
        <div class="admin-modal__backdrop"></div>
        <div class="admin-modal__box" role="dialog" aria-labelledby="edit-modal-title">
            <header class="admin-modal__header">
                <h2 id="edit-modal-title">Edit Account</h2>
                <button type="button" class="admin-modal__close" aria-label="Close">✕</button>
            </header>
            <form class="profile-form" method="post" action="/admin/users.php" novalidate>
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action"     value="update">
                <input type="hidden" name="user_id"    id="edit-user-id">
 
                <div class="profile-form__row">
                    <div class="form__group">
                        <label class="form__label">Full Name</label>
                        <input type="text" name="name" id="edit-name" class="form__input" required>
                    </div>
                    <div class="form__group">
                        <label class="form__label">Username</label>
                        <input type="text" name="username" id="edit-username" class="form__input" required>
                    </div>
                </div>
                <div class="form__group">
                    <label class="form__label">Email</label>
                    <input type="email" name="email" id="edit-email" class="form__input" required>
                </div>
                <div class="form__group">
                    <label class="form__label">Role</label>
                    <select name="role" id="edit-role" class="form__input">
                        <option value="member">Member</option>
                        <option value="trainer">Trainer</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <div class="form__group">
                    <label class="form__label">New Password <span style="color:var(--text-muted);font-weight:400">(leave blank to keep current)</span></label>
                    <input type="password" name="password" class="form__input" autocomplete="new-password">
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
    //  edit modal /window
    const modal    = document.getElementById('edit-user-modal');
    const closeBtn = modal.querySelectorAll('.admin-modal__close');
 
    document.querySelectorAll('.admin-edit-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.getElementById('edit-user-id').value   = btn.dataset.id;
            document.getElementById('edit-name').value      = btn.dataset.name;
            document.getElementById('edit-username').value  = btn.dataset.username;
            document.getElementById('edit-email').value     = btn.dataset.email;
            document.getElementById('edit-role').value      = btn.dataset.role;
            modal.setAttribute('aria-hidden', 'false');
            modal.classList.add('is-open');
        });
    });
 
    closeBtn.forEach(btn => btn.addEventListener('click', () => {
        modal.setAttribute('aria-hidden', 'true');
        modal.classList.remove('is-open');
    }));
 
    modal.querySelector('.admin-modal__backdrop')
         .addEventListener('click', () => {
             modal.setAttribute('aria-hidden', 'true');
             modal.classList.remove('is-open');
         });
})();
</script>
 
<?php include __DIR__ . '/../includes/footer.php'; ?>