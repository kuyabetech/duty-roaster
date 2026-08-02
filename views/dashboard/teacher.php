<?php
// =============================================
// Teacher Dashboard - Fixed
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

// Check if user is logged in
if (!$auth->isLoggedIn()) {
    redirect(SITE_URL . '/views/auth/login.php');
}

$user = $auth->getUser();
$role = $user['role'] ?? '';

// Set page variables
$pageTitle = 'Teacher Dashboard';
$pageIcon = 'fas fa-th-large';
$breadcrumb = [
    ['label' => 'Dashboard', 'active' => true]
];

// Get teacher information
$db = Database::getInstance();
$stmt = $db->prepare("
    SELECT t.*, d.name as department_name 
    FROM teachers t
    LEFT JOIN departments d ON t.department_id = d.id
    WHERE t.email = ?
");
$stmt->execute([$user['email']]);
$teacher = $stmt->fetch();

// If teacher not found, create default array with safe values
if (!$teacher) {
    $teacher = [
        'id' => null,
        'first_name' => $user['full_name'] ?? 'User',
        'last_name' => '',
        'department_name' => 'Not Assigned',
        'profile_photo' => null,
        'teacher_id' => 'N/A',
        'position' => 'N/A',
        'phone_primary' => 'N/A',
        'status' => 'active'
    ];
}

// Ensure all required keys exist with safe defaults
$teacher = array_merge([
    'id' => null,
    'first_name' => 'User',
    'last_name' => '',
    'department_name' => 'Not Assigned',
    'profile_photo' => null,
    'teacher_id' => 'N/A',
    'position' => 'N/A',
    'phone_primary' => 'N/A',
    'status' => 'active',
    'bio' => ''
], $teacher);

$teacherId = $teacher['id'];

// Get teacher statistics
// Total duties
$stmt = $db->prepare("SELECT COUNT(*) as count FROM duties WHERE teacher_id = ?");
$stmt->execute([$teacherId]);
$totalDuties = $stmt->fetch()['count'] ?? 0;

// Pending duties
$stmt = $db->prepare("SELECT COUNT(*) as count FROM duties WHERE teacher_id = ? AND status = ?");
$stmt->execute([$teacherId, DUTY_PENDING]);
$pendingDuties = $stmt->fetch()['count'] ?? 0;

// Today's duties
$today = date('Y-m-d');
$stmt = $db->prepare("
    SELECT COUNT(*) as count FROM duties 
    WHERE teacher_id = ? AND duty_date = ? AND status IN (?, ?)
");
$stmt->execute([$teacherId, $today, DUTY_PENDING, DUTY_ACCEPTED]);
$todayDuties = $stmt->fetch()['count'] ?? 0;

// Completed duties
$stmt = $db->prepare("SELECT COUNT(*) as count FROM duties WHERE teacher_id = ? AND status = ?");
$stmt->execute([$teacherId, DUTY_COMPLETED]);
$completedDuties = $stmt->fetch()['count'] ?? 0;

// Swap requests
$stmt = $db->prepare("
    SELECT COUNT(*) as count FROM duty_swaps 
    WHERE requester_teacher_id = ? OR target_teacher_id = ?
");
$stmt->execute([$teacherId, $teacherId]);
$swapRequests = $stmt->fetch()['count'] ?? 0;

// Swap pending
$stmt = $db->prepare("
    SELECT COUNT(*) as count FROM duty_swaps 
    WHERE target_teacher_id = ? AND status = ?
");
$stmt->execute([$teacherId, SWAP_PENDING]);
$swapPending = $stmt->fetch()['count'] ?? 0;

// Upcoming duties (next 7 days)
$weekLater = date('Y-m-d', strtotime('+7 days'));
$stmt = $db->prepare("
    SELECT d.*, c.name as category_name, c.color as category_color
    FROM duties d
    LEFT JOIN duty_categories c ON d.category_id = c.id
    WHERE d.teacher_id = ? 
    AND d.duty_date BETWEEN ? AND ?
    AND d.status IN (?, ?)
    ORDER BY d.duty_date, d.start_time
    LIMIT 5
");
$stmt->execute([$teacherId, $today, $weekLater, DUTY_PENDING, DUTY_ACCEPTED]);
$upcomingDuties = $stmt->fetchAll();

// Notification count
$stmt = $db->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
$stmt->execute([$user['id']]);
$unreadCount = $stmt->fetch()['count'] ?? 0;

// Include header
require_once __DIR__ . '/../../includes/header.php';
?>

<!-- Dashboard Styles -->
<style>
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
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
        font-size: 50px;
        opacity: 0.05;
        color: var(--primary);
    }
    .stat-card .icon {
        width: 45px;
        height: 45px;
        border-radius: var(--radius);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        margin-bottom: 10px;
        position: relative;
        z-index: 1;
    }
    .stat-card .icon.blue { background: var(--info-light); color: var(--info); }
    .stat-card .icon.green { background: var(--success-light); color: var(--success); }
    .stat-card .icon.orange { background: var(--warning-light); color: var(--warning); }
    .stat-card .icon.red { background: var(--danger-light); color: var(--danger); }
    .stat-card .icon.purple { background: var(--purple-light); color: var(--purple); }
    .stat-card .number {
        font-size: 24px;
        font-weight: 700;
        color: var(--text-dark);
        position: relative;
        z-index: 1;
    }
    .stat-card .label {
        color: var(--text-muted);
        font-size: 13px;
        position: relative;
        z-index: 1;
    }
    
    .profile-card {
        background: var(--bg-white);
        border-radius: var(--radius-lg);
        padding: 25px;
        text-align: center;
        box-shadow: var(--shadow);
        border: 1px solid var(--border-light);
        margin-bottom: 20px;
    }
    .profile-card .avatar {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        background: var(--accent-gradient);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 32px;
        color: var(--primary);
        margin: 0 auto 15px;
        font-weight: 700;
        background-size: cover;
        background-position: center;
    }
    .profile-card .name {
        font-size: 18px;
        font-weight: 600;
        margin-bottom: 2px;
    }
    .profile-card .role {
        color: var(--text-muted);
        font-size: 14px;
    }
    .profile-card .department {
        color: var(--text-light);
        font-size: 13px;
        margin-top: 5px;
    }
    .profile-card .teacher-id {
        font-size: 12px;
        color: var(--text-light);
        margin-top: 3px;
    }
    
    .duty-item {
        display: flex;
        align-items: center;
        padding: 10px 0;
        border-bottom: 1px solid var(--border-light);
    }
    .duty-item:last-child {
        border-bottom: none;
    }
    .duty-item .duty-time {
        font-weight: 600;
        min-width: 60px;
        font-size: 0.8rem;
    }
    .duty-item .duty-info {
        flex: 1;
        margin-left: 10px;
    }
    .duty-item .duty-info .duty-title {
        font-weight: 500;
        font-size: 0.85rem;
    }
    .duty-item .duty-info .duty-meta {
        font-size: 0.7rem;
        color: var(--text-muted);
    }
    .duty-item .duty-actions {
        display: flex;
        gap: 4px;
    }
    .duty-item .duty-actions .btn {
        padding: 2px 6px;
        font-size: 0.7rem;
    }
    
    .dashboard-row {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 20px;
        margin-top: 20px;
    }
    
    @media (max-width: 768px) {
        .dashboard-row {
            grid-template-columns: 1fr;
        }
        .stats-grid {
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
        }
    }
    @media (max-width: 480px) {
        .stats-grid {
            grid-template-columns: 1fr 1fr;
        }
        .profile-card .avatar {
            width: 60px;
            height: 60px;
            font-size: 24px;
        }
        .profile-card .name {
            font-size: 16px;
        }
    }
</style>

<div class="row">
    <!-- Profile Card -->
    <div class="col-md-4">
        <div class="profile-card">
            <div class="avatar" style="background-image: url('<?php echo !empty($teacher['profile_photo']) ? SITE_URL . '/uploads/profiles/' . $teacher['profile_photo'] : 'none'; ?>');">
                <?php if (empty($teacher['profile_photo'])): ?>
                    <?php echo substr($teacher['first_name'] ?? 'U', 0, 1); ?>
                <?php endif; ?>
            </div>
            <div class="name">
                <?php echo htmlspecialchars(trim(($teacher['first_name'] ?? '') . ' ' . ($teacher['last_name'] ?? '')) ?: 'User'); ?>
            </div>
            <div class="role">
                <span class="badge bg-info">Teacher</span>
                <?php if (!empty($teacher['position']) && $teacher['position'] !== 'N/A'): ?>
                    <span class="badge bg-secondary"><?php echo htmlspecialchars($teacher['position']); ?></span>
                <?php endif; ?>
            </div>
            <div class="department">
                <i class="fas fa-building me-1"></i> 
                <?php echo htmlspecialchars($teacher['department_name'] ?? 'No Department'); ?>
            </div>
            <?php if (!empty($teacher['teacher_id']) && $teacher['teacher_id'] !== 'N/A'): ?>
                <div class="teacher-id">
                    <i class="fas fa-id-card me-1"></i> ID: <?php echo htmlspecialchars($teacher['teacher_id']); ?>
                </div>
            <?php endif; ?>
            <div class="mt-3">
                <a href="<?php echo SITE_URL; ?>/views/profile/" class="btn btn-outline-primary btn-sm">
                    <i class="fas fa-edit me-1"></i> Edit Profile
                </a>
            </div>
        </div>
        
        <!-- Quick Actions for Teacher -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-bolt me-2"></i> Quick Actions
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="<?php echo SITE_URL; ?>/views/swaps/create.php" class="btn btn-outline-primary">
                        <i class="fas fa-exchange-alt me-2"></i> Request Swap
                    </a>
                    <a href="<?php echo SITE_URL; ?>/views/duties/my-duties.php" class="btn btn-outline-success">
                        <i class="fas fa-list me-2"></i> View My Duties
                    </a>
                    <a href="<?php echo SITE_URL; ?>/views/duties/calendar.php" class="btn btn-outline-info">
                        <i class="fas fa-calendar-alt me-2"></i> View Calendar
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="col-md-8">
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-bg-icon"><i class="fas fa-tasks"></i></div>
                <div class="icon blue"><i class="fas fa-tasks"></i></div>
                <div class="number"><?php echo number_format($totalDuties); ?></div>
                <div class="label">Total Duties</div>
            </div>
            <div class="stat-card">
                <div class="stat-bg-icon"><i class="fas fa-check-circle"></i></div>
                <div class="icon green"><i class="fas fa-check-circle"></i></div>
                <div class="number"><?php echo number_format($completedDuties); ?></div>
                <div class="label">Completed</div>
            </div>
            <div class="stat-card">
                <div class="stat-bg-icon"><i class="fas fa-clock"></i></div>
                <div class="icon orange"><i class="fas fa-clock"></i></div>
                <div class="number"><?php echo number_format($pendingDuties); ?></div>
                <div class="label">Pending</div>
            </div>
            <div class="stat-card">
                <div class="stat-bg-icon"><i class="fas fa-calendar-day"></i></div>
                <div class="icon red"><i class="fas fa-calendar-day"></i></div>
                <div class="number"><?php echo number_format($todayDuties); ?></div>
                <div class="label">Today's Duties</div>
            </div>
            <div class="stat-card">
                <div class="stat-bg-icon"><i class="fas fa-exchange-alt"></i></div>
                <div class="icon purple"><i class="fas fa-exchange-alt"></i></div>
                <div class="number"><?php echo number_format($swapRequests); ?></div>
                <div class="label">Swap Requests</div>
                <?php if ($swapPending > 0): ?>
                    <div class="trend down" style="font-size:11px;color:var(--danger);">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $swapPending; ?> pending
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Upcoming Duties -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-calendar-alt me-2"></i> Upcoming Duties (Next 7 Days)</span>
                <a href="<?php echo SITE_URL; ?>/views/duties/my-duties.php" class="btn btn-sm btn-outline-primary">
                    View All <i class="fas fa-arrow-right ms-1"></i>
                </a>
            </div>
            <div class="card-body">
                <?php if (empty($upcomingDuties)): ?>
                    <p class="text-muted text-center py-3">
                        <i class="fas fa-check-circle fa-2x d-block mb-2"></i>
                        No upcoming duties. Enjoy your break! 🎉
                    </p>
                <?php else: ?>
                    <?php foreach ($upcomingDuties as $duty): ?>
                        <div class="duty-item">
                            <div class="duty-time">
                                <span class="badge bg-primary"><?php echo date('d M', strtotime($duty['duty_date'])); ?></span>
                            </div>
                            <div class="duty-info">
                                <div class="duty-title">
                                    <?php echo htmlspecialchars($duty['category_name'] ?? 'Uncategorized'); ?>
                                    <span class="badge bg-<?php echo getPriorityBadgeColor($duty['priority'] ?? 'normal'); ?> ms-1">
                                        <?php echo ucfirst($duty['priority'] ?? 'normal'); ?>
                                    </span>
                                    <span class="badge bg-<?php echo getStatusBadgeColor($duty['status'] ?? 'pending'); ?> ms-1">
                                        <?php echo ucfirst($duty['status'] ?? 'pending'); ?>
                                    </span>
                                </div>
                                <div class="duty-meta">
                                    <?php echo date('h:i A', strtotime($duty['start_time'] ?? '00:00:00')); ?> - 
                                    <?php echo date('h:i A', strtotime($duty['end_time'] ?? '00:00:00')); ?>
                                    <?php if (!empty($duty['location'])): ?>
                                        • <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($duty['location']); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="duty-actions">
                                <?php if (($duty['status'] ?? '') === DUTY_PENDING): ?>
                                    <a href="<?php echo SITE_URL; ?>/views/duties/accept.php?id=<?php echo $duty['id']; ?>" 
                                       class="btn btn-sm btn-success" title="Accept">
                                        <i class="fas fa-check"></i>
                                    </a>
                                    <a href="<?php echo SITE_URL; ?>/views/duties/reject.php?id=<?php echo $duty['id']; ?>" 
                                       class="btn btn-sm btn-danger" title="Reject">
                                        <i class="fas fa-times"></i>
                                    </a>
                                <?php endif; ?>
                                <a href="<?php echo SITE_URL; ?>/views/duties/view.php?id=<?php echo $duty['id']; ?>" 
                                   class="btn btn-sm btn-outline-primary" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
require_once __DIR__ . '/../../includes/footer.php';
?>