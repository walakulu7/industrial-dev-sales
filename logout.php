<?php

/**
 * Logout Page
 * Destroys session and redirects to login
 */

require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/functions.php';

if (is_logged_in()) {
    log_audit($_SESSION['user_id'], 'logout', 'users', $_SESSION['user_id']);
}

session_unset();
session_destroy();
header("Location: login.php?logout=1");
exit();
