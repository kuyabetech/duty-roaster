<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

$auth = new Auth();

// Logout user
$auth->logout();

// Redirect to login page
redirect(SITE_URL . '/views/auth/login.php');