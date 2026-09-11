<?php
require_once __DIR__ . '/config.php';

if (is_logged_in()) {
    log_activity(get_current_user_id(), 'User Logout', 'User logged out cleanly');
}

$_SESSION = array();

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

header('Location: ' . base_url('index.php'));
exit();
