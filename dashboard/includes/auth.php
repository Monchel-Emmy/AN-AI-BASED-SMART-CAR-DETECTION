<?php
/**
 * Session guard — include at the top of every protected page.
 * Redirects to login if the user is not authenticated.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['user_id'])) {
    header('Location: /accident/web/dashboard/auth/login.php');
    exit;
}
