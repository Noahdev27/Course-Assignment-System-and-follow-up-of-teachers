<?php
/**
 * Logout: clears and destroys the session.
 */
require_once __DIR__ . '/includes/functions.php';

if (current_user()) {
    log_action('Logout');
}
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 3600, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}
session_destroy();

session_start();
flash('success', 'You are logged out.');
redirect('index.php');
