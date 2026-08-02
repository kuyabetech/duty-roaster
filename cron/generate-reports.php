<?php
// =============================================
// Generate Weekly/Monthly Reports Cron Job
// =============================================

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Duty.php';
require_once __DIR__ . '/../models/Teacher.php';

error_log("[" . date('Y-m-d H:i:s') . "] Starting report generation...");

$db = Database::getInstance();

// Weekly report (every Sunday)
if (date('N') == 7) {
    $startDate = date('Y-m-d', strtotime('-7 days'));
    $endDate = date('Y-m-d');
    
    generateWeeklyReport($startDate, $endDate);
}

// Monthly report (1st of month)
if (date('j') == 1) {
    $startDate = date('Y-m-d', strtotime('first day of previous month'));
    $endDate = date('Y-m-d', strtotime('last day of previous month'));
    
    generateMonthlyReport($startDate, $endDate);
}

function generateWeeklyReport($startDate, $endDate) {
    $db = Database::getInstance();
    
    // Get statistics
    $stats = Duty::getStatistics(['start_date' => $startDate, 'end_date' => $endDate]);
    
    // Get top performing teachers
    $stmt = $db->prepare("
        SELECT t.first_name, t.last_name, COUNT(d.id) as duty_count,
               SUM(CASE WHEN d.status = ? THEN 1 ELSE 0 END) as completed_count
        FROM teachers t
        JOIN duties d ON t.id = d.teacher_id
        WHERE d.duty_date BETWEEN ? AND ?
        GROUP BY t.id
        ORDER BY completed_count DESC
        LIMIT 10
    ");
    $stmt->execute([DUTY_COMPLETED, $startDate, $endDate]);
    $topTeachers = $stmt->fetchAll();
    
    // Save report
    $reportData = [
        'period' => 'weekly',
        'start_date' => $startDate,
        'end_date' => $endDate,
        'statistics' => $stats,
        'top_teachers' => $topTeachers,
        'generated_at' => date('Y-m-d H:i:s')
    ];
    
    $filename = 'weekly_report_' . date('Y-m-d') . '.json';
    file_put_contents(REPORT_PATH . $filename, json_encode($reportData, JSON_PRETTY_PRINT));
    
    error_log("Weekly report generated: {$filename}");
}

function generateMonthlyReport($startDate, $endDate) {
    $db = Database::getInstance();
    
    // Get comprehensive statistics
    $stats = Duty::getStatistics(['start_date' => $startDate, 'end_date' => $endDate]);
    
    // Get department performance
    $stmt = $db->prepare("
        SELECT d.name as department_name,
               COUNT(dt.id) as total_duties,
               SUM(CASE WHEN dt.status = ? THEN 1 ELSE 0 END) as completed_duties
        FROM departments d
        JOIN teachers t ON t.department_id = d.id
        JOIN duties dt ON dt.teacher_id = t.id
        WHERE dt.duty_date BETWEEN ? AND ?
        GROUP BY d.id
    ");
    $stmt->execute([DUTY_COMPLETED, $startDate, $endDate]);
    $departmentStats = $stmt->fetchAll();
    
    // Save report
    $reportData = [
        'period' => 'monthly',
        'start_date' => $startDate,
        'end_date' => $endDate,
        'statistics' => $stats,
        'department_stats' => $departmentStats,
        'generated_at' => date('Y-m-d H:i:s')
    ];
    
    $filename = 'monthly_report_' . date('Y-m') . '.json';
    file_put_contents(REPORT_PATH . $filename, json_encode($reportData, JSON_PRETTY_PRINT));
    
    error_log("Monthly report generated: {$filename}");
}

error_log("[" . date('Y-m-d H:i:s') . "] Report generation completed");