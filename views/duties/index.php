<?php
// =============================================
// Duties Management - List View
// =============================================

// Load configuration
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../models/Duty.php';
require_once __DIR__ . '/../../models/DutyCategory.php';
require_once __DIR__ . '/../../models/Teacher.php';

// Initialize auth
$auth = new Auth();
if (!$auth->isLoggedIn()) {
    redirect(SITE_URL . '/views/auth/login.php');
}

$user = $auth->getUser();
$role = $user['role'] ?? 'teacher';

// Set page variables
$pageTitle = 'Duties Management';
$pageIcon = 'fas fa-tasks';
$breadcrumb = [
    ['label' => 'Dashboard', 'url' => SITE_URL . '/views/dashboard/' . ($role === 'teacher' ? 'teacher' : 'admin') . '.php'],
    ['label' => 'Duties', 'active' => true]
];

// Get filters
$status = $_GET['status'] ?? '';
$category = $_GET['category'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$search = $_GET['search'] ?? '';

$filters = [];
if ($status) $filters['status'] = $status;
if ($category) $filters['category_id'] = $category;
if ($date_from) $filters['start_date'] = $date_from;
if ($date_to) $filters['end_date'] = $date_to;
if ($search) $filters['search'] = $search;

// If teacher, only show their duties
if ($role === ROLE_TEACHER) {
    $db = Database::getInstance();
    $stmt = $db->prepare("SELECT id FROM teachers WHERE email = ?");
    $stmt->execute([$user['email']]);
    $teacher = $stmt->fetch();
    if ($teacher) {
        $filters['teacher_id'] = $teacher['id'];
    }
}

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
$stats = Duty::getStatistics([
    'start_date' => $date_from ?: date('Y-m-01'),
    'end_date' => $date_to ?: date('Y-m-d')
]);

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
    
    .filter-group {
        display: flex;
        flex-wrap: wrap;
        gap: 5px;
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
    
    .duty-status {
        font-size: 0.75rem;
        padding: 4px 10px;
        border-radius: 12px;
        display: inline-block;
        font-weight: 600;
    }
    .duty-status.pending { background: #fff3cd; color: #856404; }
    .duty-status.accepted { background: #d1ecf1; color: #0c5460; }
    .duty-status.completed { background: #d4edda; color: #155724; }
    .duty-status.missed { background: #f8d7da; color: #721c24; }
    .duty-status.cancelled { background: #e2e3e5; color: #383d41; }
    .duty-status.rejected { background: #f8d7da; color: #721c24; }
    
    .duty-priority {
        font-size: 0.7rem;
        padding: 2px 8px;
        border-radius: 10px;
        display: inline-block;
        font-weight: 600;
    }
    .duty-priority.low { background: #d1ecf1; color: #0c5460; }
    .duty-priority.normal { background: #d4edda; color: #155724; }
    .duty-priority.high { background: #fff3cd; color: #856404; }
    .duty-priority.urgent { background: #f8d7da; color: #721c24; }
    
    .action-buttons {
        display: flex;
        gap: 4px;
        flex-wrap: wrap;
    }
    .action-buttons .btn {
        padding: 2px 6px;
        font-size: 0.7rem;
    }
    
    @media (max-width: 768px) {
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        .table-responsive {
            font-size: 0.8rem;
        }
        .action-buttons .btn {
            padding: 1px 4px;
            font-size: 0.6rem;
        }
    }
</style>

<!-- Statistics -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="icon"><i class="fas fa-tasks"></i></div>
        <div class="number"><?php echo number_format($stats['total'] ?? 0); ?></div>
        <div class="label">Total Duties</div>
    </div>
    <div class="stat-card">
        <div class="icon"><i class="fas fa-check-circle"></i></div>
        <div class="number"><?php echo number_format($stats['completed'] ?? 0); ?></div>
        <div class="label">Completed</div>
    </div>
    <div class="stat-card">
        <div class="icon"><i class="fas fa-clock"></i></div>
        <div class="number"><?php echo number_format($stats['pending'] ?? 0); ?></div>
        <div class="label">Pending</div>
    </div>
    <div class="stat-card">
        <div class="icon"><i class="fas fa-times-circle"></i></div>
        <div class="number"><?php echo number_format(($stats['missed'] ?? 0) + ($stats['cancelled'] ?? 0)); ?></div>
        <div class="label">Missed/Cancelled</div>
    </div>
</div>

<!-- Filters and Actions -->
<div class="card mb-4">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-12">
                <form method="GET" class="row g-2">
                    <div class="col-md-3">
                        <select name="status" class="form-select form-select-sm">
                            <option value="">All Status</option>
                            <option value="<?php echo DUTY_PENDING; ?>" <?php echo $status === DUTY_PENDING ? 'selected' : ''; ?>>Pending</option>
                            <option value="<?php echo DUTY_ACCEPTED; ?>" <?php echo $status === DUTY_ACCEPTED ? 'selected' : ''; ?>>Accepted</option>
                            <option value="<?php echo DUTY_COMPLETED; ?>" <?php echo $status === DUTY_COMPLETED ? 'selected' : ''; ?>>Completed</option>
                            <option value="<?php echo DUTY_MISSED; ?>" <?php echo $status === DUTY_MISSED ? 'selected' : ''; ?>>Missed</option>
                            <option value="<?php echo DUTY_CANCELLED; ?>" <?php echo $status === DUTY_CANCELLED ? 'selected' : ''; ?>>Cancelled</option>
                            <option value="<?php echo DUTY_REJECTED; ?>" <?php echo $status === DUTY_REJECTED ? 'selected' : ''; ?>>Rejected</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select name="category" class="form-select form-select-sm">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" <?php echo $category == $cat['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <input type="date" name="date_from" class="form-control form-control-sm" 
                               value="<?php echo $date_from; ?>" placeholder="From">
                    </div>
                    <div class="col-md-2">
                        <input type="date" name="date_to" class="form-control form-control-sm" 
                               value="<?php echo $date_to; ?>" placeholder="To">
                    </div>
                    <div class="col-md-2">
                        <div class="d-flex gap-1">
                            <button type="submit" class="btn btn-primary btn-sm w-100">
                                <i class="fas fa-filter me-1"></i> Filter
                            </button>
                            <a href="?" class="btn btn-secondary btn-sm">
                                <i class="fas fa-times"></i>
                            </a>
                        </div>
                    </div>
                </form>
            </div>
            <div class="col-md-12">
                <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center">
                    <div>
                        <a href="<?php echo SITE_URL; ?>/views/duties/create.php" class="btn btn-accent btn-sm">
                            <i class="fas fa-plus me-1"></i> Assign Duty
                        </a>
                        <?php if ($role !== 'teacher'): ?>
                            <a href="<?php echo SITE_URL; ?>/views/duties/schedule.php" class="btn btn-info btn-sm text-white">
                                <i class="fas fa-calendar-alt me-1"></i> Generate Schedule
                            </a>
                        <?php endif; ?>
                    </div>
                    <div>
                        <a href="<?php echo SITE_URL; ?>/views/duties/calendar.php" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-calendar-alt me-1"></i> Calendar
                        </a>
                        <button onclick="window.print()" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-print me-1"></i> Print
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Duties Table -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-list me-2"></i> Duties List (<?php echo number_format($totalCount); ?>)</span>
        <?php if ($totalCount > 0): ?>
            <span class="text-muted small">Showing <?php echo count($duties); ?> of <?php echo number_format($totalCount); ?></span>
        <?php endif; ?>
    </div>
    <div class="card-body p-0">
        <?php if (empty($duties)): ?>
            <div class="text-center py-5">
                <i class="fas fa-calendar-times fa-3x text-muted d-block mb-3"></i>
                <h5 class="text-muted">No duties found</h5>
                <p class="text-muted">Adjust your filters or create a new duty.</p>
                <a href="<?php echo SITE_URL; ?>/views/duties/create.php" class="btn btn-accent">
                    <i class="fas fa-plus me-2"></i> Assign Duty
                </a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Code</th>
                            <th>Category</th>
                            <th>Teacher</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($duties as $index => $duty): ?>
                            <tr>
                                <td><?php echo $offset + $index + 1; ?></td>
                                <td><code><?php echo htmlspecialchars($duty['duty_code']); ?></code></td>
                                <td>
                                    <span class="badge" style="background: <?php echo $duty['category_color'] ?? '#6c757d'; ?>; color: #fff;">
                                        <?php echo htmlspecialchars($duty['category_name'] ?? 'Uncategorized'); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars(($duty['first_name'] ?? '') . ' ' . ($duty['last_name'] ?? '')); ?>
                                </td>
                                <td><?php echo formatDate($duty['duty_date']); ?></td>
                                <td>
                                    <?php echo date('h:i A', strtotime($duty['start_time'])); ?> - 
                                    <?php echo date('h:i A', strtotime($duty['end_time'])); ?>
                                </td>
                                <td>
                                    <span class="duty-priority <?php echo $duty['priority']; ?>">
                                        <?php echo ucfirst($duty['priority']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="duty-status <?php echo $duty['status']; ?>">
                                        <?php echo ucfirst($duty['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="<?php echo SITE_URL; ?>/views/duties/view.php?id=<?php echo $duty['id']; ?>" 
                                           class="btn btn-outline-primary btn-sm" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        
                                        <?php if ($role !== 'teacher' || $duty['status'] === DUTY_PENDING): ?>
                                            <a href="<?php echo SITE_URL; ?>/views/duties/edit.php?id=<?php echo $duty['id']; ?>" 
                                               class="btn btn-outline-warning btn-sm" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php if ($duty['status'] === DUTY_PENDING && $role === 'teacher'): ?>
                                            <a href="<?php echo SITE_URL; ?>/views/duties/accept.php?id=<?php echo $duty['id']; ?>" 
                                               class="btn btn-outline-success btn-sm" title="Accept">
                                                <i class="fas fa-check"></i>
                                            </a>
                                            <a href="<?php echo SITE_URL; ?>/views/duties/reject.php?id=<?php echo $duty['id']; ?>" 
                                               class="btn btn-outline-danger btn-sm" title="Reject">
                                                <i class="fas fa-times"></i>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php if ($role !== 'teacher' && $duty['status'] !== DUTY_COMPLETED): ?>
                                            <a href="<?php echo SITE_URL; ?>/views/duties/delete.php?id=<?php echo $duty['id']; ?>" 
                                               class="btn btn-outline-danger btn-sm" title="Delete"
                                               onclick="return confirm('Are you sure you want to delete this duty?');">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
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

<?php
// Include footer
require_once __DIR__ . '/../../includes/footer.php';
?>