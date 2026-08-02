<?php
// =============================================
// Admin Dashboard
// =============================================

// Load configuration
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/security.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Initialize auth
$auth = new Auth();

// Check if user is logged in and has admin role
if (!$auth->isLoggedIn()) {
    redirect(SITE_URL . '/views/auth/login.php');
}

$user = $auth->getUser();
$role = $user['role'] ?? '';

// Check if user has admin access
if (!in_array($role, ['admin', 'super_admin'])) {
    redirect(SITE_URL . '/views/dashboard/teacher.php');
}

// Set page variables
$pageTitle = 'Admin Dashboard';
$pageIcon = 'fas fa-th-large';
$breadcrumb = [
    ['label' => 'Dashboard', 'active' => true]
];

// Get statistics for admin dashboard
$db = Database::getInstance();

// Total teachers
$stmt = $db->query("SELECT COUNT(*) as count FROM teachers WHERE deleted_at IS NULL");
$totalTeachers = $stmt->fetch()['count'] ?? 0;

// Total duties
$stmt = $db->query("SELECT COUNT(*) as count FROM duties");
$totalDuties = $stmt->fetch()['count'] ?? 0;

// Pending duties
$stmt = $db->prepare("SELECT COUNT(*) as count FROM duties WHERE status = ?");
$stmt->execute([DUTY_PENDING]);
$pendingDuties = $stmt->fetch()['count'] ?? 0;

// Today's duties
$today = date('Y-m-d');
$stmt = $db->prepare("SELECT COUNT(*) as count FROM duties WHERE duty_date = ? AND status IN (?, ?)");
$stmt->execute([$today, DUTY_PENDING, DUTY_ACCEPTED]);
$todayDuties = $stmt->fetch()['count'] ?? 0;

// Department count
$stmt = $db->prepare("SELECT COUNT(*) as count FROM departments WHERE status = ?");
$stmt->execute([STATUS_ACTIVE]);
$totalDepartments = $stmt->fetch()['count'] ?? 0;

// Total swap requests pending
$stmt = $db->prepare("SELECT COUNT(*) as count FROM duty_swaps WHERE status = ?");
$stmt->execute([SWAP_PENDING]);
$pendingSwaps = $stmt->fetch()['count'] ?? 0;

// Completion rate - FIXED: Check for division by zero
$stmt = $db->query("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed 
    FROM duties");
$dutyStats = $stmt->fetch();
$total = $dutyStats['total'] ?? 0;
$completed = $dutyStats['completed'] ?? 0;
// FIX: Check if total > 0 before dividing
$completionRate = $total > 0 ? round(($completed / $total) * 100, 1) : 0;

// Recent activities
$stmt = $db->query("
    SELECT a.*, u.full_name as user_name 
    FROM audit_logs a
    LEFT JOIN users u ON a.user_id = u.id
    ORDER BY a.created_at DESC 
    LIMIT 10
");
$recentActivities = $stmt->fetchAll();

// Get upcoming duties (next 7 days)
$weekLater = date('Y-m-d', strtotime('+7 days'));
$stmt = $db->prepare("
    SELECT d.*, 
           t.first_name, t.last_name,
           c.name as category_name
    FROM duties d
    LEFT JOIN teachers t ON d.teacher_id = t.id
    LEFT JOIN duty_categories c ON d.category_id = c.id
    WHERE d.duty_date BETWEEN ? AND ?
    AND d.status IN (?, ?)
    ORDER BY d.duty_date, d.start_time
    LIMIT 5
");
$stmt->execute([$today, $weekLater, DUTY_PENDING, DUTY_ACCEPTED]);
$upcomingDuties = $stmt->fetchAll();

// Get teacher workload distribution
$stmt = $db->query("
    SELECT 
        t.id,
        CONCAT(t.first_name, ' ', t.last_name) as teacher_name,
        COUNT(d.id) as duty_count
    FROM teachers t
    LEFT JOIN duties d ON t.id = d.teacher_id
    WHERE t.deleted_at IS NULL
    GROUP BY t.id
    ORDER BY duty_count DESC
    LIMIT 5
");
$topTeachers = $stmt->fetchAll();

// FIX: Get max duties for percentage calculation
$maxDuties = !empty($topTeachers) ? max(array_column($topTeachers, 'duty_count')) : 0;

// Quick stats
$stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE status = 'active'");
$totalUsers = $stmt->fetch()['count'] ?? 0;

$stmt = $db->query("SELECT COUNT(*) as count FROM duty_categories WHERE status = 'active'");
$totalCategories = $stmt->fetch()['count'] ?? 0;

$stmt = $db->prepare("SELECT COUNT(*) as count FROM duty_swaps WHERE status = ?");
$stmt->execute([SWAP_COMPLETED]);
$completedSwaps = $stmt->fetch()['count'] ?? 0;

$stmt = $db->prepare("SELECT COUNT(*) as count FROM duties WHERE MONTH(duty_date) = ? AND YEAR(duty_date) = ?");
$stmt->execute([date('n'), date('Y')]);
$thisMonthDuties = $stmt->fetch()['count'] ?? 0;

// Include header
require_once __DIR__ . '/../../includes/header.php';
?>

<!-- Dashboard Styles -->
<style>
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    .stat-card {
        background: var(--bg-white);
        padding: 20px;
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow);
        transition: var(--transition);
        border: 1px solid var(--border-light);
        position: relative;
        overflow: hidden;
    }
    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: var(--shadow-lg);
    }
    .stat-card .stat-bg-icon {
        position: absolute;
        right: -10px;
        bottom: -10px;
        font-size: 60px;
        opacity: 0.05;
        color: var(--primary);
    }
    .stat-card .icon {
        width: 50px;
        height: 50px;
        border-radius: var(--radius);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        margin-bottom: 12px;
        position: relative;
        z-index: 1;
    }
    .stat-card .icon.blue { background: var(--info-light); color: var(--info); }
    .stat-card .icon.green { background: var(--success-light); color: var(--success); }
    .stat-card .icon.orange { background: var(--warning-light); color: var(--warning); }
    .stat-card .icon.red { background: var(--danger-light); color: var(--danger); }
    .stat-card .icon.purple { background: var(--purple-light); color: var(--purple); }
    .stat-card .icon.teal { background: #e0f2f1; color: #00695c; }
    .stat-card .icon.pink { background: var(--pink-light); color: var(--pink); }
    .stat-card .number {
        font-size: 28px;
        font-weight: 700;
        color: var(--text-dark);
        position: relative;
        z-index: 1;
    }
    .stat-card .label {
        color: var(--text-muted);
        font-size: 14px;
        position: relative;
        z-index: 1;
    }
    .stat-card .trend {
        font-size: 12px;
        margin-top: 5px;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 2px 10px;
        border-radius: var(--radius-full);
        position: relative;
        z-index: 1;
    }
    .stat-card .trend.up { background: var(--success-light); color: var(--success); }
    .stat-card .trend.down { background: var(--danger-light); color: var(--danger); }
    .stat-card .trend.neutral { background: var(--bg-secondary); color: var(--text-muted); }
    
    .dashboard-row {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 20px;
        margin-top: 20px;
    }
    
    .activity-item {
        display: flex;
        align-items: flex-start;
        padding: 12px 0;
        border-bottom: 1px solid var(--border-light);
        gap: 12px;
    }
    .activity-item:last-child {
        border-bottom: none;
    }
    .activity-item .activity-icon {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        font-size: 14px;
    }
    .activity-item .activity-icon.primary { background: var(--info-light); color: var(--info); }
    .activity-item .activity-icon.success { background: var(--success-light); color: var(--success); }
    .activity-item .activity-icon.warning { background: var(--warning-light); color: var(--warning); }
    .activity-item .activity-icon.danger { background: var(--danger-light); color: var(--danger); }
    .activity-item .activity-icon.purple { background: var(--purple-light); color: var(--purple); }
    .activity-item .activity-content {
        flex: 1;
    }
    .activity-item .activity-content .title {
        font-weight: 500;
        font-size: 0.9rem;
    }
    .activity-item .activity-content .time {
        font-size: 0.75rem;
        color: var(--text-light);
    }
    
    .quick-actions-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
    }
    .quick-action-btn {
        background: var(--bg-primary);
        border: 1px solid var(--border-color);
        border-radius: var(--radius);
        padding: 15px 10px;
        text-align: center;
        transition: var(--transition);
        text-decoration: none;
        color: var(--text-dark);
        font-weight: 500;
        font-size: 0.8rem;
    }
    .quick-action-btn:hover {
        background: var(--accent);
        color: var(--primary);
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(255, 215, 0, 0.2);
        border-color: var(--accent);
    }
    .quick-action-btn i {
        font-size: 20px;
        display: block;
        margin-bottom: 5px;
    }
    
    .duty-list-item {
        display: flex;
        align-items: center;
        padding: 10px 0;
        border-bottom: 1px solid var(--border-light);
    }
    .duty-list-item:last-child {
        border-bottom: none;
    }
    .duty-list-item .duty-time {
        font-weight: 600;
        min-width: 60px;
        font-size: 0.8rem;
    }
    .duty-list-item .duty-info {
        flex: 1;
        margin-left: 10px;
    }
    .duty-list-item .duty-info .duty-title {
        font-weight: 500;
        font-size: 0.85rem;
    }
    .duty-list-item .duty-info .duty-meta {
        font-size: 0.7rem;
        color: var(--text-muted);
    }
    
    .teacher-workload-item {
        display: flex;
        align-items: center;
        padding: 8px 0;
        border-bottom: 1px solid var(--border-light);
    }
    .teacher-workload-item:last-child {
        border-bottom: none;
    }
    .teacher-workload-item .teacher-name {
        flex: 1;
        font-size: 0.85rem;
    }
    .teacher-workload-item .workload-bar {
        width: 100px;
        height: 6px;
        background: var(--bg-secondary);
        border-radius: var(--radius-full);
        overflow: hidden;
        margin: 0 10px;
    }
    .teacher-workload-item .workload-bar .bar {
        height: 100%;
        background: var(--accent-gradient);
        border-radius: var(--radius-full);
        transition: width 1s ease;
    }
    .teacher-workload-item .workload-count {
        font-weight: 600;
        font-size: 0.85rem;
        min-width: 30px;
        text-align: right;
    }

    @media (max-width: 768px) {
        .dashboard-row {
            grid-template-columns: 1fr;
        }
        .quick-actions-grid {
            grid-template-columns: 1fr 1fr;
        }
        .stats-grid {
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        }
    }
    @media (max-width: 480px) {
        .stats-grid {
            grid-template-columns: 1fr 1fr;
        }
        .quick-actions-grid {
            grid-template-columns: 1fr;
        }
        .stat-card .number {
            font-size: 20px;
        }
    }
</style>

<!-- Statistics Grid -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-bg-icon"><i class="fas fa-user-friends"></i></div>
        <div class="icon blue"><i class="fas fa-user-friends"></i></div>
        <div class="number"><?php echo $totalTeachers; ?></div>
        <div class="label">Total Teachers</div>
        <div class="trend up"><i class="fas fa-arrow-up"></i> Active</div>
    </div>
    <div class="stat-card">
        <div class="stat-bg-icon"><i class="fas fa-tasks"></i></div>
        <div class="icon green"><i class="fas fa-tasks"></i></div>
        <div class="number"><?php echo $totalDuties; ?></div>
        <div class="label">Total Duties</div>
        <div class="trend <?php echo $completionRate >= 70 ? 'up' : ($completionRate >= 40 ? 'neutral' : 'down'); ?>">
            <i class="fas fa-<?php echo $completionRate >= 70 ? 'check-circle' : ($completionRate >= 40 ? 'minus-circle' : 'exclamation-circle'); ?>"></i>
            <?php echo $completionRate; ?>% completed
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-bg-icon"><i class="fas fa-clock"></i></div>
        <div class="icon orange"><i class="fas fa-clock"></i></div>
        <div class="number"><?php echo $pendingDuties; ?></div>
        <div class="label">Pending Duties</div>
        <div class="trend <?php echo $pendingDuties > 0 ? 'down' : 'up'; ?>">
            <i class="fas fa-<?php echo $pendingDuties > 0 ? 'exclamation-circle' : 'check-circle'; ?>"></i>
            <?php echo $pendingDuties > 0 ? 'Needs attention' : 'All clear'; ?>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-bg-icon"><i class="fas fa-calendar-day"></i></div>
        <div class="icon red"><i class="fas fa-calendar-day"></i></div>
        <div class="number"><?php echo $todayDuties; ?></div>
        <div class="label">Today's Duties</div>
        <div class="trend <?php echo $todayDuties > 0 ? 'up' : 'neutral'; ?>">
            <i class="fas fa-<?php echo $todayDuties > 0 ? 'calendar-check' : 'calendar'; ?>"></i>
            <?php echo $todayDuties > 0 ? 'In progress' : 'No duties today'; ?>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-bg-icon"><i class="fas fa-building"></i></div>
        <div class="icon purple"><i class="fas fa-building"></i></div>
        <div class="number"><?php echo $totalDepartments; ?></div>
        <div class="label">Departments</div>
        <div class="trend up"><i class="fas fa-check"></i> Active</div>
    </div>
    <div class="stat-card">
        <div class="stat-bg-icon"><i class="fas fa-exchange-alt"></i></div>
        <div class="icon pink"><i class="fas fa-exchange-alt"></i></div>
        <div class="number"><?php echo $pendingSwaps; ?></div>
        <div class="label">Pending Swaps</div>
        <div class="trend <?php echo $pendingSwaps > 0 ? 'down' : 'up'; ?>">
            <i class="fas fa-<?php echo $pendingSwaps > 0 ? 'exclamation-circle' : 'check-circle'; ?>"></i>
            <?php echo $pendingSwaps > 0 ? 'Needs review' : 'All resolved'; ?>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="card mb-4">
    <div class="card-header">
        <i class="fas fa-bolt me-2"></i> Quick Actions
    </div>
    <div class="card-body">
        <div class="quick-actions-grid">
            <a href="<?php echo SITE_URL; ?>/views/duties/create.php" class="quick-action-btn">
                <i class="fas fa-plus-circle"></i>
                Assign Duty
            </a>
            <a href="<?php echo SITE_URL; ?>/views/teachers/create.php" class="quick-action-btn">
                <i class="fas fa-user-plus"></i>
                Add Teacher
            </a>
            <a href="<?php echo SITE_URL; ?>/views/duties/schedule.php" class="quick-action-btn">
                <i class="fas fa-calendar-alt"></i>
                Generate Schedule
            </a>
            <a href="<?php echo SITE_URL; ?>/views/reports/index.php" class="quick-action-btn">
                <i class="fas fa-file-pdf"></i>
                Generate Report
            </a>
            <a href="<?php echo SITE_URL; ?>/views/departments/create.php" class="quick-action-btn">
                <i class="fas fa-building"></i>
                Add Department
            </a>
            <a href="<?php echo SITE_URL; ?>/views/categories/create.php" class="quick-action-btn">
                <i class="fas fa-tag"></i>
                Add Category
            </a>
        </div>
    </div>
</div>

<!-- Dashboard Row -->
<div class="dashboard-row">
    <!-- Left Column -->
    <div>
        <!-- Upcoming Duties -->
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-calendar-alt me-2"></i> Upcoming Duties (Next 7 Days)</span>
                <a href="<?php echo SITE_URL; ?>/views/duties/calendar.php" class="btn btn-sm btn-outline-primary">
                    View Calendar <i class="fas fa-arrow-right ms-1"></i>
                </a>
            </div>
            <div class="card-body">
                <?php if (empty($upcomingDuties)): ?>
                    <p class="text-muted text-center py-3">
                        <i class="fas fa-check-circle fa-2x d-block mb-2"></i>
                        No upcoming duties scheduled.
                    </p>
                <?php else: ?>
                    <?php foreach ($upcomingDuties as $duty): ?>
                        <div class="duty-list-item">
                            <div class="duty-time">
                                <span class="badge bg-primary"><?php echo date('d M', strtotime($duty['duty_date'])); ?></span>
                            </div>
                            <div class="duty-info">
                                <div class="duty-title">
                                    <?php echo htmlspecialchars($duty['category_name']); ?>
                                    <span class="badge bg-<?php echo getPriorityBadgeColor($duty['priority']); ?> ms-1">
                                        <?php echo ucfirst($duty['priority']); ?>
                                    </span>
                                </div>
                                <div class="duty-meta">
                                    <?php echo htmlspecialchars($duty['first_name'] . ' ' . $duty['last_name']); ?> • 
                                    <?php echo date('h:i A', strtotime($duty['start_time'])); ?> - 
                                    <?php echo date('h:i A', strtotime($duty['end_time'])); ?>
                                    <?php if ($duty['location']): ?>
                                        • <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($duty['location']); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <a href="<?php echo SITE_URL; ?>/views/duties/view.php?id=<?php echo $duty['id']; ?>" 
                               class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-eye"></i>
                            </a>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Recent Activity -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-bell me-2"></i> Recent Activity
            </div>
            <div class="card-body">
                <?php if (empty($recentActivities)): ?>
                    <p class="text-muted text-center py-3">
                        <i class="fas fa-inbox fa-2x d-block mb-2"></i>
                        No recent activity
                    </p>
                <?php else: ?>
                    <?php foreach ($recentActivities as $activity): ?>
                        <div class="activity-item">
                            <div class="activity-icon <?php echo $activity['action'] === 'login' ? 'success' : 'primary'; ?>">
                                <i class="fas fa-<?php echo $activity['action'] === 'login' ? 'sign-in-alt' : 'clipboard-list'; ?>"></i>
                            </div>
                            <div class="activity-content">
                                <div class="title">
                                    <?php echo htmlspecialchars($activity['action']); ?>
                                    <small class="text-muted">in <?php echo htmlspecialchars($activity['module']); ?></small>
                                </div>
                                <div class="time">
                                    <i class="far fa-clock me-1"></i>
                                    <?php echo timeAgo($activity['created_at']); ?>
                                    <?php if ($activity['user_name']): ?>
                                        • by <?php echo htmlspecialchars($activity['user_name']); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Right Column -->
    <div>
        <!-- Top Teachers Workload -->
        <div class="card mb-3">
            <div class="card-header">
                <i class="fas fa-chart-bar me-2"></i> Top Teachers Workload
            </div>
            <div class="card-body">
                <?php if (empty($topTeachers)): ?>
                    <p class="text-muted text-center py-3">No teacher data available</p>
                <?php else: ?>
                    <?php 
                    // FIX: Use pre-calculated max value
                    foreach ($topTeachers as $teacher): 
                        $percentage = $maxDuties > 0 ? round(($teacher['duty_count'] / $maxDuties) * 100) : 0;
                    ?>
                        <div class="teacher-workload-item">
                            <div class="teacher-name">
                                <i class="fas fa-user-circle me-1"></i>
                                <?php echo htmlspecialchars($teacher['teacher_name']); ?>
                            </div>
                            <div class="workload-bar">
                                <div class="bar" style="width: <?php echo $percentage; ?>%;"></div>
                            </div>
                            <div class="workload-count"><?php echo $teacher['duty_count']; ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- System Status -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-server me-2"></i> System Status
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                    <span><i class="fas fa-check-circle text-success me-2"></i> Database</span>
                    <span class="badge bg-success">Connected</span>
                </div>
                <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                    <span><i class="fas fa-check-circle text-success me-2"></i> Session</span>
                    <span class="badge bg-success">Active</span>
                </div>
                <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                    <span><i class="fas fa-check-circle text-success me-2"></i> File Upload</span>
                    <span class="badge bg-success">Enabled</span>
                </div>
                <div class="d-flex justify-content-between align-items-center py-2">
                    <span><i class="fas fa-envelope me-2"></i> Email Service</span>
                    <span class="badge bg-<?php echo defined('SMTP_HOST') && SMTP_HOST ? 'success' : 'warning'; ?>">
                        <?php echo defined('SMTP_HOST') && SMTP_HOST ? 'Configured' : 'Not Configured'; ?>
                    </span>
                </div>
            </div>
        </div>
        
        <!-- Quick Stats -->
        <div class="card mt-3">
            <div class="card-header">
                <i class="fas fa-info-circle me-2"></i> Quick Stats
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-6">
                        <div class="text-muted small">Total Users</div>
                        <div class="h4"><?php echo $totalUsers; ?></div>
                    </div>
                    <div class="col-6">
                        <div class="text-muted small">Duty Categories</div>
                        <div class="h4"><?php echo $totalCategories; ?></div>
                    </div>
                </div>
                <div class="row text-center mt-2">
                    <div class="col-6">
                        <div class="text-muted small">Swap Completed</div>
                        <div class="h4"><?php echo $completedSwaps; ?></div>
                    </div>
                    <div class="col-6">
                        <div class="text-muted small">This Month</div>
                        <div class="h4"><?php echo $thisMonthDuties; ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
require_once __DIR__ . '/../../includes/footer.php';
?>