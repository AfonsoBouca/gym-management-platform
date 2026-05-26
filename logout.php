<?php
declare(strict_types=1);

<<<<<<< HEAD
require_once __DIR__ . '/includes/auth.php';
=======
require_once __DIR__ . '/php/includes/auth.php';
>>>>>>> baseReq

clear_remember_cookie();

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
    );
}

session_destroy();
header('Location: /login.php');
exit;
