
<?php
// =============================================
// Send Daily Reminders Cron Job
// =============================================

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Notification.php';

error_log("[" . date('Y-m-d H:i:s') . "] Starting daily reminders...");

$db = Database::getInstance();

// Get today's duties
$today = date('Y-m-d');
$stmt = $db->prepare("
    SELECT d.*, 
           t.first_name, t.last_name, t.email,
           c.name as category_name
    FROM duties d
    JOIN teachers t ON d.teacher_id = t.id
    JOIN duty_categories c ON d.category_id = c.id
    WHERE d.duty_date = ? 
    AND d.status IN (?, ?)
    AND d.reminder_sent = 0
");
$stmt->execute([$today, DUTY_PENDING, DUTY_ACCEPTED]);
$duties = $stmt->fetchAll();

foreach ($duties as $duty) {
    // Get user
    $userStmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $userStmt->execute([$duty['email']]);
    $user = $userStmt->fetch();
    
    if ($user) {
        Notification::send(
            $user['id'],
            'daily_reminder',
            "Today's Duty: {$duty['category_name']}",
            "You have a duty today at " . date('h:i A', strtotime($duty['start_time'])) . 
            ". Location: " . ($duty['location'] ?? 'Not specified'),
            SITE_URL . '/duties/view.php?id=' . $duty['id'],
            NOTIFY_HIGH
        );
        
        // Mark reminder sent
        $updateStmt = $db->prepare("UPDATE duties SET reminder_sent = 1 WHERE id = ?");
        $updateStmt->execute([$duty['id']]);
        
        error_log("Sent daily reminder for duty ID: {$duty['id']}");
    }
}

error_log("[" . date('Y-m-d H:i:s') . "] Daily reminders completed");