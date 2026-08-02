<?php
// =============================================
// View Duty Details
// =============================================

// Load configuration
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../models/Duty.php';
require_once __DIR__ . '/../../models/Swap.php';
require_once __DIR__ . '/../../models/Notification.php';

// Initialize auth
$auth = new Auth();
if (!$auth->isLoggedIn()) {
    redirect(SITE_URL . '/views/auth/login.php');
}

$user = $auth->getUser();
$role = $user['role'] ?? 'teacher';

// Get duty ID
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    setFlashMessage('error', 'Invalid duty ID.');
    redirect(SITE_URL . '/views/duties/');
}

// Load duty
$duty = new Duty($id);
if (!$duty->getData()) {
    setFlashMessage('error', 'Duty not found.');
    redirect(SITE_URL . '/views/duties/');
}

$dutyData = $duty->getData();

// Check if user can view this duty
$db = Database::getInstance();
$stmt = $db->prepare("SELECT id FROM teachers WHERE email = ?");
$stmt->execute([$user['email']]);
$teacher = $stmt->fetch();
$teacherId = $teacher['id'] ?? null;

$isTeacher = ($teacherId && $teacherId == $dutyData['teacher_id']);
$isAdmin = in_array($role, ['admin', 'super_admin']);

if (!$isTeacher && !$isAdmin) {
    setFlashMessage('error', 'You do not have permission to view this duty.');
    redirect(SITE_URL . '/views/duties/');
}

// Get swap requests for this duty
$swaps = Swap::getByDuty($id);

// Set page variables
$pageTitle = 'Duty Details';
$pageIcon = 'fas fa-tasks';
$breadcrumb = [
    ['label' => 'Dashboard', 'url' => SITE_URL . '/views/dashboard/' . ($role === 'teacher' ? 'teacher' : 'admin') . '.php'],
    ['label' => 'Duties', 'url' => SITE_URL . '/views/duties/'],
    ['label' => '#' . $dutyData['duty_code'], 'active' => true]
];

// Status badge colors
$statusColors = [
    'pending' => 'warning',
    'accepted' => 'info',
    'rejected' => 'danger',
    'completed' => 'success',
    'missed' => 'danger',
    'cancelled' => 'secondary'
];

$statusIcons = [
    'pending' => 'fas fa-clock',
    'accepted' => 'fas fa-check-circle',
    'rejected' => 'fas fa-times-circle',
    'completed' => 'fas fa-check-double',
    'missed' => 'fas fa-exclamation-circle',
    'cancelled' => 'fas fa-ban'
];

$priorityColors = [
    'low' => 'info',
    'normal' => 'success',
    'high' => 'warning',
    'urgent' => 'danger'
];

// Include header
require_once __DIR__ . '/../../includes/header.php';
?>

<!-- Page Styles -->
<style>
    .duty-header {
        background: var(--bg-white);
        border-radius: var(--radius-lg);
        padding: 25px;
        margin-bottom: 25px;
        border-left: 5px solid var(--accent);
        box-shadow: var(--shadow);
    }
    .duty-header .duty-code {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--text-dark);
    }
    .duty-header .duty-code code {
        font-size: 1.2rem;
        background: var(--bg-light);
        padding: 4px 12px;
        border-radius: 6px;
    }
    .duty-header .duty-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        margin-top: 10px;
    }
    .duty-header .duty-meta .meta-item {
        display: flex;
        align-items: center;
        gap: 6px;
        color: var(--text-muted);
        font-size: 0.9rem;
    }
    .duty-header .duty-meta .meta-item i {
        color: var(--accent);
        width: 18px;
    }
    
    .info-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
    }
    .info-card {
        background: var(--bg-white);
        border-radius: var(--radius);
        padding: 20px;
        border: 1px solid var(--border-light);
        transition: var(--transition);
    }
    .info-card:hover {
        box-shadow: var(--shadow);
    }
    .info-card .info-label {
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--text-muted);
        font-weight: 600;
        margin-bottom: 5px;
    }
    .info-card .info-value {
        font-size: 1rem;
        font-weight: 500;
    }
    .info-card .info-value .badge {
        font-size: 0.85rem;
    }
    
    .action-bar {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-top: 20px;
        padding-top: 20px;
        border-top: 1px solid var(--border-color);
    }
    .action-bar .btn {
        min-width: 120px;
    }
    
    .swap-history-item {
        display: flex;
        align-items: center;
        padding: 10px 0;
        border-bottom: 1px solid var(--border-light);
    }
    .swap-history-item:last-child {
        border-bottom: none;
    }
    .swap-history-item .swap-status {
        margin-right: 15px;
    }
    .swap-history-item .swap-info {
        flex: 1;
    }
    .swap-history-item .swap-info .swap-title {
        font-weight: 500;
    }
    .swap-history-item .swap-info .swap-meta {
        font-size: 0.8rem;
        color: var(--text-muted);
    }
    
    @media (max-width: 768px) {
        .info-grid {
            grid-template-columns: 1fr;
        }
        .duty-header .duty-meta {
            flex-direction: column;
            gap: 8px;
        }
        .action-bar {
            justify-content: center;
        }
        .action-bar .btn {
            min-width: 100px;
            font-size: 0.85rem;
        }
    }
</style>

<!-- Duty Header -->
<div class="duty-header">
    <div class="duty-code">
        <i class="fas fa-tasks me-2" style="color: var(--accent);"></i>
        <code><?php echo htmlspecialchars($dutyData['duty_code']); ?></code>
        <span class="badge bg-<?php echo $statusColors[$dutyData['status']] ?? 'secondary'; ?> ms-2" style="font-size: 0.9rem;">
            <i class="<?php echo $statusIcons[$dutyData['status']] ?? 'fas fa-circle'; ?> me-1"></i>
            <?php echo ucfirst($dutyData['status']); ?>
        </span>
    </div>
    <div class="duty-meta">
        <div class="meta-item">
            <i class="fas fa-calendar-alt"></i>
            <?php echo formatDate($dutyData['duty_date']); ?>
        </div>
        <div class="meta-item">
            <i class="fas fa-clock"></i>
            <?php echo date('h:i A', strtotime($dutyData['start_time'])); ?> - 
            <?php echo date('h:i A', strtotime($dutyData['end_time'])); ?>
        </div>
        <div class="meta-item">
            <i class="fas fa-user"></i>
            <?php echo htmlspecialchars(($dutyData['first_name'] ?? '') . ' ' . ($dutyData['last_name'] ?? '')); ?>
        </div>
        <?php if ($dutyData['location']): ?>
            <div class="meta-item">
                <i class="fas fa-map-marker-alt"></i>
                <?php echo htmlspecialchars($dutyData['location']); ?>
            </div>
        <?php endif; ?>
        <div class="meta-item">
            <i class="fas fa-tag"></i>
            <span class="badge" style="background: <?php echo $dutyData['category_color'] ?? '#6c757d'; ?>; color: #fff;">
                <?php echo htmlspecialchars($dutyData['category_name']); ?>
            </span>
        </div>
        <div class="meta-item">
            <i class="fas fa-flag"></i>
            <span class="badge bg-<?php echo $priorityColors[$dutyData['priority']] ?? 'secondary'; ?>">
                <?php echo ucfirst($dutyData['priority']); ?>
            </span>
        </div>
    </div>
</div>

<!-- Info Grid -->
<div class="info-grid">
    <!-- Left Column -->
    <div>
        <!-- Teacher Info -->
        <div class="info-card mb-3">
            <div class="info-label"><i class="fas fa-user me-1"></i> Teacher</div>
            <div class="info-value">
                <?php echo htmlspecialchars(($dutyData['first_name'] ?? '') . ' ' . ($dutyData['last_name'] ?? '')); ?>
                <br>
                <small class="text-muted"><?php echo htmlspecialchars($dutyData['teacher_code'] ?? ''); ?></small>
            </div>
        </div>
        
        <!-- Category Info -->
        <div class="info-card mb-3">
            <div class="info-label"><i class="fas fa-tag me-1"></i> Category</div>
            <div class="info-value">
                <span class="badge" style="background: <?php echo $dutyData['category_color'] ?? '#6c757d'; ?>; color: #fff; font-size: 1rem;">
                    <?php echo htmlspecialchars($dutyData['category_name']); ?>
                </span>
            </div>
        </div>
        
        <!-- Priority & Status -->
        <div class="info-card">
            <div class="info-label"><i class="fas fa-flag me-1"></i> Priority</div>
            <div class="info-value">
                <span class="badge bg-<?php echo $priorityColors[$dutyData['priority']] ?? 'secondary'; ?>" style="font-size: 1rem;">
                    <?php echo ucfirst($dutyData['priority']); ?>
                </span>
            </div>
        </div>
    </div>
    
    <!-- Right Column -->
    <div>
        <!-- Class Info -->
        <?php if ($dutyData['class_name']): ?>
            <div class="info-card mb-3">
                <div class="info-label"><i class="fas fa-chalkboard me-1"></i> Class</div>
                <div class="info-value">
                    <?php echo htmlspecialchars($dutyData['class_name']); ?>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Location -->
        <?php if ($dutyData['location']): ?>
            <div class="info-card mb-3">
                <div class="info-label"><i class="fas fa-map-marker-alt me-1"></i> Location</div>
                <div class="info-value">
                    <i class="fas fa-location-dot me-1" style="color: var(--accent);"></i>
                    <?php echo htmlspecialchars($dutyData['location']); ?>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Remarks -->
        <?php if ($dutyData['remarks']): ?>
            <div class="info-card">
                <div class="info-label"><i class="fas fa-comment me-1"></i> Remarks</div>
                <div class="info-value" style="font-weight: 400;">
                    <?php echo nl2br(htmlspecialchars($dutyData['remarks'])); ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Action Bar -->
<div class="action-bar">
    <a href="<?php echo SITE_URL; ?>/views/duties/" class="btn btn-secondary">
        <i class="fas fa-arrow-left me-2"></i> Back to List
    </a>
    
    <?php if ($isTeacher && $dutyData['status'] === DUTY_PENDING): ?>
        <a href="<?php echo SITE_URL; ?>/views/duties/accept.php?id=<?php echo $dutyData['id']; ?>" 
           class="btn btn-success">
            <i class="fas fa-check me-2"></i> Accept
        </a>
        <a href="<?php echo SITE_URL; ?>/views/duties/reject.php?id=<?php echo $dutyData['id']; ?>" 
           class="btn btn-danger">
            <i class="fas fa-times me-2"></i> Reject
        </a>
    <?php endif; ?>
    
    <?php if ($isAdmin && $dutyData['status'] === DUTY_PENDING): ?>
        <a href="<?php echo SITE_URL; ?>/views/duties/edit.php?id=<?php echo $dutyData['id']; ?>" 
           class="btn btn-warning">
            <i class="fas fa-edit me-2"></i> Edit
        </a>
        <a href="<?php echo SITE_URL; ?>/views/duties/assign.php?id=<?php echo $dutyData['id']; ?>" 
           class="btn btn-primary">
            <i class="fas fa-user-plus me-2"></i> Reassign
        </a>
    <?php endif; ?>
    
    <?php if ($isAdmin && $dutyData['status'] !== DUTY_COMPLETED && $dutyData['status'] !== DUTY_CANCELLED): ?>
        <a href="<?php echo SITE_URL; ?>/views/duties/cancel.php?id=<?php echo $dutyData['id']; ?>" 
           class="btn btn-outline-danger"
           onclick="return confirm('Are you sure you want to cancel this duty?');">
            <i class="fas fa-ban me-2"></i> Cancel
        </a>
    <?php endif; ?>
    
    <?php if ($isTeacher && $dutyData['status'] === DUTY_ACCEPTED): ?>
        <a href="<?php echo SITE_URL; ?>/views/duties/complete.php?id=<?php echo $dutyData['id']; ?>" 
           class="btn btn-success"
           onclick="return confirm('Mark this duty as completed?');">
            <i class="fas fa-check-double me-2"></i> Mark Complete
        </a>
    <?php endif; ?>
    
    <?php if ($isTeacher && $dutyData['status'] === DUTY_PENDING): ?>
        <a href="<?php echo SITE_URL; ?>/views/swaps/create.php?duty_id=<?php echo $dutyData['id']; ?>" 
           class="btn btn-outline-primary">
            <i class="fas fa-exchange-alt me-2"></i> Request Swap
        </a>
    <?php endif; ?>
    
    <button onclick="window.print()" class="btn btn-outline-secondary">
        <i class="fas fa-print me-2"></i> Print
    </button>
</div>

<!-- Swap History -->
<?php if (!empty($swaps)): ?>
    <div class="card mt-4">
        <div class="card-header">
            <i class="fas fa-exchange-alt me-2"></i> Swap History
        </div>
        <div class="card-body">
            <?php foreach ($swaps as $swap): ?>
                <div class="swap-history-item">
                    <div class="swap-status">
                        <span class="badge bg-<?php echo getStatusBadgeColor($swap['status']); ?>">
                            <?php echo str_replace('_', ' ', ucfirst($swap['status'])); ?>
                        </span>
                    </div>
                    <div class="swap-info">
                        <div class="swap-title">
                            Swap request by <?php echo htmlspecialchars($swap['requester_teacher_id']); ?>
                        </div>
                        <div class="swap-meta">
                            <i class="far fa-clock me-1"></i>
                            <?php echo timeAgo($swap['created_at']); ?>
                            <?php if ($swap['status'] === SWAP_COMPLETED): ?>
                                • Completed
                            <?php endif; ?>
                        </div>
                    </div>
                    <a href="<?php echo SITE_URL; ?>/views/swaps/view.php?id=<?php echo $swap['id']; ?>" 
                       class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-eye"></i>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<!-- Print Styles -->
<style media="print">
    .sidebar, .top-header, .action-bar, .no-print {
        display: none !important;
    }
    .main-content {
        margin-left: 0 !important;
        padding: 20px !important;
    }
    .card {
        box-shadow: none !important;
        border: 1px solid #ddd !important;
    }
    .duty-header {
        box-shadow: none !important;
        border: 1px solid #ddd !important;
    }
    body {
        background: #fff !important;
    }
</style>

<script>
// Auto-dismiss alerts
setTimeout(() => {
    document.querySelectorAll('.alert-dismissible').forEach(el => {
        const closeBtn = el.querySelector('.btn-close');
        if (closeBtn) {
            setTimeout(() => closeBtn.click(), 5000);
        }
    });
}, 1000);

console.log('📋 Duty loaded: <?php echo htmlspecialchars($dutyData['duty_code']); ?>');
console.log('📊 Status: <?php echo $dutyData['status']; ?>');
</script>

<?php
// Include footer
require_once __DIR__ . '/../../includes/footer.php';
?>