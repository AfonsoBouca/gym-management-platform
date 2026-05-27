<?php
declare(strict_types=1);
 
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helper.php';
 
// constants for remember me cookie, duration
define('REMEMBER_COOKIE', 'fd_remember');
define('REMEMBER_DAYS',   30);
 
// session start (if not already) for authentication and CSRF token storage
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
 
// csrf
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
 
function csrf_verify(string $token): bool {
    return !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}
 
// remember me
function set_remember_cookie(int $user_id): void {
    $token      = bin2hex(random_bytes(32));
    $token_hash = hash('sha256', $token);
    $expires    = time() + (REMEMBER_DAYS * 86400);
 
    $pdo  = get_db();
    $stmt = $pdo->prepare(
        "INSERT INTO remember_me_tokens (user_id, token_hash, expires_at)
         VALUES (:uid, :th, datetime(:exp, 'unixepoch'))"
    );
    $stmt->execute([':uid' => $user_id, ':th' => $token_hash, ':exp' => $expires]);
 
    setcookie(REMEMBER_COOKIE, $token, [
        'expires'  => $expires,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        // 'secure' => true,  // uncomment when on HTTPS | ai said to implement this, but localhosting doesn't support
    ]);
}
 
function clear_remember_cookie(): void {
    if (isset($_COOKIE[REMEMBER_COOKIE])) {
        $token_hash = hash('sha256', $_COOKIE[REMEMBER_COOKIE]);
        get_db()->prepare(
            "DELETE FROM remember_me_tokens WHERE token_hash = ?"
        )->execute([$token_hash]);
    }
    setcookie(REMEMBER_COOKIE, '', time() - 3600, '/');
}
 
function try_remember_login(): void {
    if (!empty($_SESSION['user_id']) || !isset($_COOKIE[REMEMBER_COOKIE])) return;
 
    $token_hash = hash('sha256', $_COOKIE[REMEMBER_COOKIE]);
    $stmt = get_db()->prepare(
        "SELECT rt.user_id, rt.expires_at, u.role, u.is_active, u.name, u.username
           FROM remember_me_tokens rt
           JOIN users u ON u.id = rt.user_id
          WHERE rt.token_hash = :th"
    );
    $stmt->execute([':th' => $token_hash]);
    $row = $stmt->fetch();
 
    if ($row && $row['is_active'] && strtotime($row['expires_at']) > time()) {
        session_regenerate_id(true);
        $_SESSION['user_id']   = $row['user_id'];
        $_SESSION['role']      = $row['role'];
        $_SESSION['user_name'] = $row['name'];
        $_SESSION['username']  = $row['username'];
    } else {
        clear_remember_cookie();
    }
}
 
// role-based redirection helper
function redirect_by_role(string $role): void {
    $dest = match($role) {
        'admin'   => '/admin/dashboard.php',
        'trainer' => '/trainer/dashboard.php',
        default   => '/profile.php',
    };
    header("Location: $dest");
    exit;
}
 
// pageguards
 
// require any logged-in user
function require_login(): void {
    try_remember_login();
    if (empty($_SESSION['user_id'])) {
        header('Location: /login.php');
        exit;
    }
}
 
// require a specific role
function require_role(string $role): void {
    require_login();
    if ($_SESSION['role'] !== $role) {
        redirect_by_role($_SESSION['role']);
    }
}
?>