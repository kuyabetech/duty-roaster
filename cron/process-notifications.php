<?php
// =============================================
// Process Notifications Cron Job
// =============================================

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Notification.php';
require_once __DIR__ . '/../models/Duty.php';
require_once __DIR__ . '/../models/Teacher.php';

// Log start
error_log("[" . date('Y-m-d H:i:s') . "] Starting notification processing...");

$db = Database::getInstance();

// Process duty reminders (24 hours before)
$tomorrow = date('Y-m-d', strtotime('+1 day'));
$stmt = $db->prepare("
    SELECT d.*, t.id as teacher_id, t.email, t.first_name, t.last_name
    FROM duties d
    JOIN teachers t ON d.teacher_id = t.id
    WHERE d.duty_date = ? 
    AND d.status IN (?, ?)
    AND d.id NOT IN (
        SELECT DISTINCT duty_id FROM notifications 
        WHERE type = 'duty_reminder' 
        AND created_at > DATE_SUB(NOW(), INTERVAL 1 DAY)
    )
");
$stmt->execute([$tomorrow, DUTY_PENDING, DUTY_ACCEPTED]);
$duties = $stmt->fetchAll();

foreach ($duties as $duty) {
    // Get user ID for notification
    $userStmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $userStmt->execute([$duty['email']]);
    $user = $userStmt->fetch();
    
    if ($user) {
        Notification::send(
            $user['id'],
            'duty_reminder',
            'Duty Reminder',
            "Reminder: You have a duty tomorrow ({$duty['category_name']}) at " . 
            date('h:i A', strtotime($duty['start_time'])),
            SITE_URL . '/duties/view.php?id=' . $duty['id'],
            NOTIFY_HIGH
        );
        
        error_log("Sent reminder for duty ID: {$duty['id']}");
    }
}

// Process overdue duties
$yesterday = date('Y-m-d', strtotime('-1 day'));
$stmt = $db->prepare("
    UPDATE duties 
    SET status = ? 
    WHERE duty_date < ? 
    AND status IN (?, ?)
");
$stmt->execute([DUTY_MISSED, $yesterday, DUTY_PENDING, DUTY_ACCEPTED]);
$updated = $stmt->rowCount();

if ($updated > 0) {
    error_log("Marked {$updated} overdue duties as missed");
}

// Clean up old notifications (keep last 30 days)
$stmt = $db->prepare("DELETE FROM notifications WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
$stmt->execute();
error_log("Cleaned up " . $stmt->rowCount() . " old notifications");

error_log("[" . date('Y-m-d H:i:s') . "] Notification processing completed");