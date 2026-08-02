<?php
// =============================================
// ADPS - Automated Duty Processing System
// Main Entry Point
// =============================================

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load configuration
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/security.php';

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Initialize authentication
try {
    $auth = new Auth();
} catch (Exception $e) {
    die("Authentication initialization failed: " . $e->getMessage());
}

// Check if user is logged in
if ($auth->isLoggedIn()) {
    $user = $auth->getUser();
    $role = $user['role'] ?? 'teacher';
    
    // Redirect based on user role
    switch ($role) {
        case 'super_admin':
        case 'admin':
            // Redirect admins to admin dashboard
            redirect(SITE_URL . '/views/dashboard/admin.php');
            break;
            
        case 'teacher':
        default:
            // Redirect teachers to teacher dashboard
            redirect(SITE_URL . '/views/dashboard/teacher.php');
            break;
    }
} else {
    // Redirect to login
    redirect(SITE_URL . '/views/auth/login.php');
}