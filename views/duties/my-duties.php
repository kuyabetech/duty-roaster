<?php
// =============================================
// My Duties - Teacher View
// =============================================

// Load configuration
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../models/Duty.php';
require_once __DIR__ . '/../../models/DutyCategory.php';

// Initialize auth
$auth = new Auth();
if (!$auth->isLoggedIn()) {
    redirect(SITE_URL . '/views/auth/login.php');
}

$user = $auth->getUser();
$role = $user['role'] ?? 'teacher';

// Redirect admins to main duties page
if ($role !== 'teacher') {
    redirect(SITE_URL . '/views/duties/');
}

// Set page variables
$pageTitle = 'My Duties';
$pageIcon = 'fas fa-list';
$breadcrumb = [
    ['label' => 'Dashboard', 'url' => SITE_URL . '/views/dashboard/teacher.php'],
    ['label' => 'My Duties', 'active' => true]
];

// Get teacher ID
$db = Database::getInstance();
$stmt = $db->prepare("SELECT id FROM teachers WHERE email = ?");
$stmt->execute([$user['email']]);
$teacher = $stmt->fetch();
$teacherId = $teacher['id'] ?? null;

if (!$teacherId) {
    setFlashMessage('error', 'Teacher record not found.');
    redirect(SITE_URL . '/views/dashboard/teacher.php');
}

// Get filters
$status = $_GET['status'] ?? '';
$category = $_GET['category'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$search = $_GET['search'] ?? '';

$filters = [
    'teacher_id' => $teacherId
];
if ($status) $filters['status'] = $status;
if ($category) $filters['category_id'] = $category;
if ($date_from) $filters['start_date'] = $date_from;
if ($date_to) $filters['end_date'] = $date_to;
if ($search) $filters['search'] = $search;

// Pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;
$filters['limit'] = $limit;
$filters['offset'] = $offset;

$duties = Duty::all($filters);
$totalCount = Duty::count($filters);
$totalPages = ceil($totalCount / $limit);

$categories = DutyCategory::getActive();

// Get statistics
$today = date('Y-m-d');
$weekLater = date('Y-m-d', strtotime('+7 days'));

$stmt = $db->prepare("SELECT COUNT(*) as count FROM duties WHERE teacher_id = ? AND status = ?");
$stmt->execute([$teacherId, DUTY_PENDING]);
$pendingCount = $stmt->fetch()['count'] ?? 0;

$stmt = $db->prepare("SELECT COUNT(*) as count FROM duties WHERE teacher_id = ? AND status = ?");
$stmt->execute([$teacherId, DUTY_ACCEPTED]);
$acceptedCount = $stmt->fetch()['count'] ?? 0;

$stmt = $db->prepare("SELECT COUNT(*) as count FROM duties WHERE teacher_id = ? AND status = ?");
$stmt->execute([$teacherId, DUTY_COMPLETED]);
$completedCount = $stmt->fetch()['count'] ?? 0;

$stmt = $db->prepare("
    SELECT COUNT(*) as count FROM duties 
    WHERE teacher_id = ? AND duty_date BETWEEN ? AND ? 
    AND status IN (?, ?)
");
$stmt->execute([$teacherId, $today, $weekLater, DUTY_PENDING, DUTY_ACCEPTED]);
$upcomingCount = $stmt->fetch()['count'] ?? 0;

// Include header
require_once __DIR__ . '/../../includes/header.php';
?>

<!-- Page Styles -->
<style>
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 15px;
        margin-bottom: 25px;
    }
    .stat-card {
        background: var(--bg-white);
        padding: 15px 20px;
        border-radius: var(--radius);
        box-shadow: var(--shadow);
        border: 1px solid var(--border-light);
        transition: var(--transition);
        cursor: pointer;
        position: relative;
        overflow: hidden;
    }
    .stat-card:hover {
        transform: translateY(-3px);
        box-shadow: var(--shadow-md);
    }
    .stat-card .number {
        font-size: 22px;
        font-weight: 700;
        color: var(--text-dark);
    }
    .stat-card .label {
        font-size: 12px;
        color: var(--text-muted);
    }
    .stat-card .icon {
        position: absolute;
        right: 15px;
        top: 15px;
        font-size: 28px;
        opacity: 0.15;
    }
    .stat-card .icon.pending { color: var(--warning); }
    .stat-card .icon.accepted { color: var(--info); }
    .stat-card .icon.completed { color: var(--success); }
    .stat-card .icon.upcoming { color: var(--primary); }
    .stat-card .stat-link {
        text-decoration: none;
        color: inherit;
        display: block;
    }
    
    .filter-group {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        align-items: center;
    }
    .filter-group .btn {
        font-size: 0.8rem;
        padding: 5px 12px;
    }
    .filter-group .btn.active {
        background: var(--accent);
        color: var(--primary);
        border-color: var(--accent);
        font-weight: 600;
    }
    
    .duty-card {
        background: var(--bg-white);
        border-radius: var(--radius);
        border: 1px solid var(--border-light);
        padding: 15px 20px;
        margin-bottom: 15px;
        transition: var(--transition);
        position: relative;
        overflow: hidden;
    }
    .duty-card:hover {
        box-shadow: var(--shadow-md);
        border-color: var(--accent);
    }
    .duty-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 4px;
        height: 100%;
        background: var(--border-color);
    }
    .duty-card.status-pending::before { background: var(--warning); }
    .duty-card.status-accepted::before { background: var(--info); }
    .duty-card.status-completed::before { background: var(--success); }
    .duty-card.status-rejected::before { background: var(--danger); }
    .duty-card.status-missed::before { background: var(--danger); }
    .duty-card.status-cancelled::before { background: var(--secondary); }
    
    .duty-card .duty-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 8px;
    }
    .duty-card .duty-code {
        font-weight: 600;
        font-size: 1rem;
    }
    .duty-card .duty-code code {
        background: var(--bg-light);
        padding: 2px 8px;
        border-radius: 4px;
        font-size: 0.85rem;
    }
    .duty-card .duty-details {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr;
        gap: 10px;
        font-size: 0.85rem;
    }
    .duty-card .duty-details .detail-item {
        display: flex;
        align-items: center;
        gap: 5px;
        color: var(--text-muted);
    }
    .duty-card .duty-details .detail-item i {
        width: 16px;
        color: var(--accent);
    }
    .duty-card .duty-actions {
        margin-top: 12px;
        padding-top: 12px;
        border-top: 1px solid var(--border-light);
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }
    .duty-card .duty-actions .btn {
        font-size: 0.75rem;
        padding: 4px 12px;
    }
    
    .empty-state {
        text-align: center;
        padding: 60px 20px;
    }
    .empty-state i {
        font-size: 60px;
        color: var(--text-light);
        margin-bottom: 20px;
        display: block;
    }
    .empty-state h4 {
        color: var(--text-muted);
    }
    .empty-state p {
        color: var(--text-light);
    }
    
    @media (max-width: 768px) {
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        .duty-card .duty-details {
            grid-template-columns: 1fr;
            gap: 5px;
        }
        .duty-card .duty-header {
            flex-direction: column;
            gap: 8px;
        }
        .filter-group {
            flex-direction: column;
            align-items: stretch;
        }
        .filter-group .btn-group {
            flex-wrap: wrap;
        }
    }
    
    @media (max-width: 480px) {
        .stats-grid {
            grid-template-columns: 1fr 1fr;
        }
        .stat-card .number {
            font-size: 18px;
        }
        .duty-card .duty-actions {
            justify-content: center;
        }
    }
</style>

<!-- Statistics -->
<div class="stats-grid">
    <div class="stat-card">
        <a href="?status=<?php echo DUTY_PENDING; ?>" class="stat-link">
            <div class="icon pending"><i class="fas fa-clock"></i></div>
            <div class="number"><?php echo $pendingCount; ?></div>
            <div class="label">Pending</div>
        </a>
    </div>
    <div class="stat-card">
        <a href="?status=<?php echo DUTY_ACCEPTED; ?>" class="stat-link">
            <div class="icon accepted"><i class="fas fa-check-circle"></i></div>
            <div class="number"><?php echo $acceptedCount; ?></div>
            <div class="label">Accepted</div>
        </a>
    </div>
    <div class="stat-card">
        <a href="?status=<?php echo DUTY_COMPLETED; ?>" class="stat-link">
            <div class="icon completed"><i class="fas fa-check-double"></i></div>
            <div class="number"><?php echo $completedCount; ?></div>
            <div class="label">Completed</div>
        </a>
    </div>
    <div class="stat-card">
        <a href="?date_from=<?php echo $today; ?>&date_to=<?php echo $weekLater; ?>" class="stat-link">
            <div class="icon upcoming"><i class="fas fa-calendar-alt"></i></div>
            <div class="number"><?php echo $upcomingCount; ?></div>
            <div class="label">Upcoming (7 days)</div>
        </a>
    </div>
</div>

<!-- Filters and Actions -->
<div class="card mb-4">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-12">
                <form method="GET" class="filter-group">
                    <div class="btn-group" role="group">
                        <a href="?" class="btn btn-outline-secondary <?php echo !$status && !$date_from ? 'active' : ''; ?>">
                            All
                        </a>
                        <a href="?status=<?php echo DUTY_PENDING; ?>" 
                           class="btn btn-outline-warning <?php echo $status === DUTY_PENDING ? 'active' : ''; ?>">
                            Pending
                        </a>
                        <a href="?status=<?php echo DUTY_ACCEPTED; ?>" 
                           class="btn btn-outline-info <?php echo $status === DUTY_ACCEPTED ? 'active' : ''; ?>">
                            Accepted
                        </a>
                        <a href="?status=<?php echo DUTY_COMPLETED; ?>" 
                           class="btn btn-outline-success <?php echo $status === DUTY_COMPLETED ? 'active' : ''; ?>">
                            Completed
                        </a>
                        <a href="?status=<?php echo DUTY_REJECTED; ?>" 
                           class="btn btn-outline-danger <?php echo $status === DUTY_REJECTED ? 'active' : ''; ?>">
                            Rejected
                        </a>
                    </div>
                    
                    <div class="ms-auto d-flex gap-2 flex-wrap">
                        <select name="category" class="form-select form-select-sm" style="width:150px;">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" <?php echo $category == $cat['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="date" name="date_from" class="form-control form-control-sm" 
                               value="<?php echo $date_from; ?>" style="width:140px;" placeholder="From">
                        <input type="date" name="date_to" class="form-control form-control-sm" 
                               value="<?php echo $date_to; ?>" style="width:140px;" placeholder="To">
                        <input type="text" name="search" class="form-control form-control-sm" 
                               value="<?php echo htmlspecialchars($search); ?>" style="width:150px;" placeholder="Search...">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="fas fa-search"></i>
                        </button>
                        <a href="?" class="btn btn-secondary btn-sm">
                            <i class="fas fa-times"></i>
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Duties List -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-list me-2"></i> My Duties (<?php echo number_format($totalCount); ?>)</span>
        <?php if ($totalCount > 0): ?>
            <span class="text-muted small">Showing <?php echo count($duties); ?> of <?php echo number_format($totalCount); ?></span>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if (empty($duties)): ?>
            <div class="empty-state">
                <i class="fas fa-calendar-check"></i>
                <h4>No duties found</h4>
                <p>You don't have any duties matching the current filters.</p>
                <a href="?" class="btn btn-accent mt-3">
                    <i class="fas fa-undo me-2"></i> Clear Filters
                </a>
            </div>
        <?php else: ?>
            <?php foreach ($duties as $duty): ?>
                <div class="duty-card status-<?php echo $duty['status']; ?>">
                    <div class="duty-header">
                        <div class="duty-code">
                            <code><?php echo htmlspecialchars($duty['duty_code']); ?></code>
                            <span class="badge bg-<?php echo getStatusBadgeColor($duty['status']); ?> ms-2">
                                <?php echo ucfirst($duty['status']); ?>
                            </span>
                            <span class="badge bg-<?php echo getPriorityBadgeColor($duty['priority']); ?> ms-1">
                                <?php echo ucfirst($duty['priority']); ?>
                            </span>
                        </div>
                        <div>
                            <span class="text-muted small">
                                <i class="far fa-calendar-alt me-1"></i>
                                <?php echo formatDate($duty['duty_date']); ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="duty-details">
                        <div class="detail-item">
                            <i class="fas fa-tag"></i>
                            <span class="badge" style="background: <?php echo $duty['category_color'] ?? '#6c757d'; ?>; color: #fff;">
                                <?php echo htmlspecialchars($duty['category_name']); ?>
                            </span>
                        </div>
                        <div class="detail-item">
                            <i class="fas fa-clock"></i>
                            <?php echo date('h:i A', strtotime($duty['start_time'])); ?> - 
                            <?php echo date('h:i A', strtotime($duty['end_time'])); ?>
                        </div>
                        <?php if ($duty['location']): ?>
                            <div class="detail-item">
                                <i class="fas fa-map-marker-alt"></i>
                                <?php echo htmlspecialchars($duty['location']); ?>
                            </div>
                        <?php else: ?>
                            <div class="detail-item">
                                <i class="fas fa-map-marker-alt"></i>
                                <span class="text-muted">No location</span>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="duty-actions">
                        <a href="<?php echo SITE_URL; ?>/views/duties/view.php?id=<?php echo $duty['id']; ?>" 
                           class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-eye me-1"></i> View
                        </a>
                        
                        <?php if ($duty['status'] === DUTY_PENDING): ?>
                            <a href="<?php echo SITE_URL; ?>/views/duties/accept.php?id=<?php echo $duty['id']; ?>" 
                               class="btn btn-success btn-sm">
                                <i class="fas fa-check me-1"></i> Accept
                            </a>
                            <a href="<?php echo SITE_URL; ?>/views/duties/reject.php?id=<?php echo $duty['id']; ?>" 
                               class="btn btn-danger btn-sm">
                                <i class="fas fa-times me-1"></i> Reject
                            </a>
                            <a href="<?php echo SITE_URL; ?>/views/swaps/create.php?duty_id=<?php echo $duty['id']; ?>" 
                               class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-exchange-alt me-1"></i> Swap
                            </a>
                        <?php endif; ?>
                        
                        <?php if ($duty['status'] === DUTY_ACCEPTED): ?>
                            <a href="<?php echo SITE_URL; ?>/views/duties/complete.php?id=<?php echo $duty['id']; ?>" 
                               class="btn btn-success btn-sm"
                               onclick="return confirm('Mark this duty as completed?');">
                                <i class="fas fa-check-double me-1"></i> Complete
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <div class="card-footer">
            <nav>
                <ul class="pagination justify-content-center mb-0">
                    <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $page - 1; ?>&status=<?php echo urlencode($status); ?>&category=<?php echo urlencode($category); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>&search=<?php echo urlencode($search); ?>">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <?php 
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $page + 2);
                    ?>
                    
                    <?php if ($startPage > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=1&status=<?php echo urlencode($status); ?>&category=<?php echo urlencode($category); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>&search=<?php echo urlencode($search); ?>">1</a>
                        </li>
                        <?php if ($startPage > 2): ?>
                            <li class="page-item disabled"><span class="page-link">...</span></li>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo urlencode($status); ?>&category=<?php echo urlencode($category); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>&search=<?php echo urlencode($search); ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                    
                    <?php if ($endPage < $totalPages): ?>
                        <?php if ($endPage < $totalPages - 1): ?>
                            <li class="page-item disabled"><span class="page-link">...</span></li>
                        <?php endif; ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $totalPages; ?>&status=<?php echo urlencode($status); ?>&category=<?php echo urlencode($category); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>&search=<?php echo urlencode($search); ?>">
                                <?php echo $totalPages; ?>
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $page + 1; ?>&status=<?php echo urlencode($status); ?>&category=<?php echo urlencode($category); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>&search=<?php echo urlencode($search); ?>">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

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

// Stat card click handler - redirect to filtered view
document.querySelectorAll('.stat-card .stat-link').forEach(link => {
    link.addEventListener('click', function(e) {
        window.location.href = this.href;
        e.preventDefault();
    });
});

console.log('📋 My Duties loaded successfully!');
console.log('📊 Total duties: <?php echo $totalCount; ?>');
</script>

<?php
// Include footer
require_once __DIR__ . '/../../includes/footer.php';
?>