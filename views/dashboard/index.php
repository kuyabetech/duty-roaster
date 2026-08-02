<?php
// =============================================
// Dashboard Redirect
// =============================================

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$auth = new Auth();

if (!$auth->isLoggedIn()) {
    redirect(SITE_URL . '/views/auth/login.php');
}

$user = $auth->getUser();
$role = $user['role'] ?? 'teacher';

// Redirect based on role
switch ($role) {
    case 'super_admin':
    case 'admin':
        redirect(SITE_URL . '/views/dashboard/admin.php');
        break;
    case 'teacher':
    default:
        redirect(SITE_URL . '/views/dashboard/teacher.php');
        break;
}