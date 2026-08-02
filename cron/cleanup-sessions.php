<?php
// =============================================
// Cleanup Sessions Cron Job
// =============================================

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// Log start
error_log("[" . date('Y-m-d H:i:s') . "] Starting session cleanup...");

$db = Database::getInstance();

// Delete expired sessions
$stmt = $db->prepare("DELETE FROM user_sessions WHERE expires_at < NOW() OR is_active = 0");
$stmt->execute();
$deleted = $stmt->rowCount();
error_log("Deleted {$deleted} expired sessions");

// Clean up old login history (keep last 90 days)
$stmt = $db->prepare("DELETE FROM login_history WHERE login_time < DATE_SUB(NOW(), INTERVAL 90 DAY)");
$stmt->execute();
error_log("Cleaned up " . $stmt->rowCount() . " old login history records");

error_log("[" . date('Y-m-d H:i:s') . "] Session cleanup completed");