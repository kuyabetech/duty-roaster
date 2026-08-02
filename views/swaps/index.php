<?php
// =============================================
// Swap Requests - List View
// =============================================

// Load configuration
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../models/Swap.php';
require_once __DIR__ . '/../../models/Teacher.php';
require_once __DIR__ . '/../../models/Notification.php';

// Initialize auth
$auth = new Auth();
if (!$auth->isLoggedIn()) {
    redirect(SITE_URL . '/views/auth/login.php');
}

$user = $auth->getUser();
$role = $user['role'] ?? 'teacher';

// Set page variables
$pageTitle = 'Swap Requests';
$pageIcon = 'fas fa-exchange-alt';
$breadcrumb = [
    ['label' => 'Dashboard', 'url' => SITE_URL . '/views/dashboard/' . ($role === 'teacher' ? 'teacher' : 'admin') . '.php'],
    ['label' => 'Swap Requests', 'active' => true]
];

// Get teacher ID for current user
$db = Database::getInstance();
$teacherId = null;
if ($role === ROLE_TEACHER) {
    $stmt = $db->prepare("SELECT id FROM teachers WHERE email = ?");
    $stmt->execute([$user['email']]);
    $teacher = $stmt->fetch();
    $teacherId = $teacher['id'] ?? null;
}

// Get filters
$status = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

$filters = [];
if ($status) {
    $filters['status'] = $status;
}
if ($date_from) {
    $filters['date_from'] = $date_from;
}
if ($date_to) {
    $filters['date_to'] = $date_to;
}
if ($search) {
    $filters['search'] = $search;
}

// If teacher, only show their swaps (either as requester or target)
if ($role === ROLE_TEACHER && $teacherId) {
    $filters['teacher_id'] = $teacherId;
}

// Get pagination parameters
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// =============================================
// FIXED STATISTICS - Using the count method
// =============================================

// Build base filters for statistics
$statsBase = [];
if ($role === ROLE_TEACHER && $teacherId) {
    $statsBase['teacher_id'] = $teacherId;
}

// Total swaps
$totalSwaps = Swap::count($statsBase);

// Pending swaps
$pendingFilters = array_merge($statsBase, ['status' => SWAP_PENDING]);
$pendingSwaps = Swap::count($pendingFilters);

// Completed swaps
$completedFilters = array_merge($statsBase, ['status' => SWAP_COMPLETED]);
$completedSwaps = Swap::count($completedFilters);

// Cancelled/Rejected swaps
$cancelledFilters = array_merge($statsBase, [
    'status' => [SWAP_CANCELLED, SWAP_REJECTED_BY_TEACHER]
]);
$cancelledSwaps = Swap::count($cancelledFilters);

// =============================================
// FETCH SWAPS WITH PAGINATION
// =============================================
$filters['limit'] = $limit;
$filters['offset'] = $offset;

$swaps = Swap::all($filters);

// Get total count for pagination
$countFilters = $filters;
unset($countFilters['limit']);
unset($countFilters['offset']);
$totalCount = Swap::count($countFilters);
$totalPages = ceil($totalCount / $limit);

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
    
    .swap-status {
        font-size: 0.75rem;
        padding: 4px 10px;
        border-radius: 12px;
        display: inline-block;
        font-weight: 600;
    }
    .swap-status.pending { background: #fff3cd; color: #856404; }
    .swap-status.approved_by_admin { background: #d1ecf1; color: #0c5460; }
    .swap-status.approved_by_teacher { background: #d4edda; color: #155724; }
    .swap-status.completed { background: #d4edda; color: #155724; }
    .swap-status.rejected_by_teacher { background: #f8d7da; color: #721c24; }
    .swap-status.cancelled { background: #f8d7da; color: #721c24; }
    
    .swap-card {
        background: var(--bg-white);
        border-radius: var(--radius);
        border: 1px solid var(--border-light);
        padding: 15px;
        margin-bottom: 15px;
        transition: var(--transition);
    }
    .swap-card:hover {
        box-shadow: var(--shadow-md);
        border-color: var(--accent);
    }
    .swap-card .swap-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
        padding-bottom: 10px;
        border-bottom: 1px solid var(--border-light);
    }
    .swap-card .swap-header .swap-code {
        font-weight: 600;
        font-size: 0.95rem;
        color: var(--text-dark);
    }
    .swap-card .swap-details {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
        font-size: 0.85rem;
    }
    .swap-card .swap-details .detail-label {
        color: var(--text-muted);
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-bottom: 2px;
    }
    .swap-card .swap-actions {
        margin-top: 15px;
        padding-top: 12px;
        border-top: 1px solid var(--border-light);
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }
    
    @media (max-width: 768px) {
        .swap-card .swap-details {
            grid-template-columns: 1fr;
        }
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        .filter-group {
            justify-content: center;
        }
    }
</style>

<!-- Statistics -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="icon"><i class="fas fa-exchange-alt"></i></div>
        <div class="number"><?php echo number_format($totalSwaps); ?></div>
        <div class="label">Total Requests</div>
    </div>
    <div class="stat-card">
        <div class="icon"><i class="fas fa-clock"></i></div>
        <div class="number"><?php echo number_format($pendingSwaps); ?></div>
        <div class="label">Pending</div>
    </div>
    <div class="stat-card">
        <div class="icon"><i class="fas fa-check-circle"></i></div>
        <div class="number"><?php echo number_format($completedSwaps); ?></div>
        <div class="label">Completed</div>
    </div>
    <div class="stat-card">
        <div class="icon"><i class="fas fa-times-circle"></i></div>
        <div class="number"><?php echo number_format($cancelledSwaps); ?></div>
        <div class="label">Cancelled/Rejected</div>
    </div>
</div>

<!-- Filters and Actions -->
<div class="card mb-4">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-12">
                <div class="filter-group">
                    <a href="?" class="btn btn-outline-secondary <?php echo !$status ? 'active' : ''; ?>">
                        <i class="fas fa-list me-1"></i> All
                    </a>
                    <a href="?status=<?php echo SWAP_PENDING; ?>" 
                       class="btn btn-outline-warning <?php echo $status === SWAP_PENDING ? 'active' : ''; ?>">
                        <i class="fas fa-clock me-1"></i> Pending
                        <?php if ($pendingSwaps > 0): ?>
                            <span class="badge bg-danger ms-1"><?php echo $pendingSwaps; ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="?status=<?php echo SWAP_APPROVED_BY_ADMIN; ?>" 
                       class="btn btn-outline-info <?php echo $status === SWAP_APPROVED_BY_ADMIN ? 'active' : ''; ?>">
                        <i class="fas fa-user-check me-1"></i> Admin Approved
                    </a>
                    <a href="?status=<?php echo SWAP_APPROVED_BY_TEACHER; ?>" 
                       class="btn btn-outline-success <?php echo $status === SWAP_APPROVED_BY_TEACHER ? 'active' : ''; ?>">
                        <i class="fas fa-check-circle me-1"></i> Teacher Approved
                    </a>
                    <a href="?status=<?php echo SWAP_COMPLETED; ?>" 
                       class="btn btn-outline-success <?php echo $status === SWAP_COMPLETED ? 'active' : ''; ?>">
                        <i class="fas fa-check-double me-1"></i> Completed
                    </a>
                    <a href="?status=<?php echo SWAP_CANCELLED; ?>" 
                       class="btn btn-outline-danger <?php echo $status === SWAP_CANCELLED ? 'active' : ''; ?>">
                        <i class="fas fa-times me-1"></i> Cancelled
                    </a>
                </div>
            </div>
            <div class="col-md-12">
                <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center">
                    <div>
                        <a href="<?php echo SITE_URL; ?>/views/swaps/create.php" class="btn btn-accent">
                            <i class="fas fa-plus me-2"></i> New Swap Request
                        </a>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <form method="GET" class="d-flex gap-2">
                            <input type="hidden" name="status" value="<?php echo htmlspecialchars($status); ?>">
                            <input type="date" name="date_from" class="form-control form-control-sm" 
                                   value="<?php echo htmlspecialchars($date_from); ?>" style="width:140px;" placeholder="From">
                            <input type="date" name="date_to" class="form-control form-control-sm" 
                                   value="<?php echo htmlspecialchars($date_to); ?>" style="width:140px;" placeholder="To">
                            <input type="text" name="search" class="form-control form-control-sm" 
                                   placeholder="Search..." value="<?php echo htmlspecialchars($search); ?>" style="width:150px;">
                            <button type="submit" class="btn btn-sm btn-primary">
                                <i class="fas fa-search"></i>
                            </button>
                            <a href="?" class="btn btn-sm btn-secondary">
                                <i class="fas fa-times"></i>
                            </a>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Swaps List -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-list me-2"></i> Swap Requests (<?php echo number_format($totalCount); ?>)</span>
        <?php if ($totalCount > 0): ?>
            <span class="text-muted small">Showing <?php echo count($swaps); ?> of <?php echo number_format($totalCount); ?></span>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if (empty($swaps)): ?>
            <div class="text-center py-5">
                <i class="fas fa-exchange-alt fa-3x text-muted d-block mb-3"></i>
                <h5 class="text-muted">No swap requests found</h5>
                <p class="text-muted">Create a new swap request to get started.</p>
                <a href="<?php echo SITE_URL; ?>/views/swaps/create.php" class="btn btn-accent">
                    <i class="fas fa-plus me-2"></i> New Swap Request
                </a>
            </div>
        <?php else: ?>
            <?php foreach ($swaps as $swap): ?>
                <div class="swap-card">
                    <div class="swap-header">
                        <div class="swap-code">
                            <i class="fas fa-exchange-alt me-2" style="color: var(--accent);"></i>
                            Swap #<?php echo $swap['id']; ?>
                            <small class="text-muted">(<?php echo htmlspecialchars($swap['duty_code'] ?? 'Duty #' . ($swap['duty_id'] ?? 'N/A')); ?>)</small>
                        </div>
                        <div>
                            <span class="swap-status <?php echo str_replace('_', '-', strtolower($swap['status'])); ?>">
                                <?php echo str_replace('_', ' ', ucfirst($swap['status'])); ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="swap-details">
                        <div>
                            <div class="detail-label">Requester</div>
                            <div>
                                <i class="fas fa-user me-1 text-muted"></i>
                                <?php 
                                    $reqName = trim(($swap['requester_first'] ?? '') . ' ' . ($swap['requester_last'] ?? ''));
                                    echo htmlspecialchars($reqName ?: 'Unknown'); 
                                ?>
                            </div>
                        </div>
                        <div>
                            <div class="detail-label">Target Teacher</div>
                            <div>
                                <i class="fas fa-user me-1 text-muted"></i>
                                <?php 
                                    $targetName = trim(($swap['target_first'] ?? '') . ' ' . ($swap['target_last'] ?? ''));
                                    echo htmlspecialchars($targetName ?: 'Unknown'); 
                                ?>
                            </div>
                        </div>
                        <div>
                            <div class="detail-label">Requested Date</div>
                            <div>
                                <i class="fas fa-calendar-day me-1 text-muted"></i>
                                <?php echo formatDate($swap['requested_date']); ?>
                            </div>
                        </div>
                        <div>
                            <div class="detail-label">Requested Time</div>
                            <div>
                                <i class="fas fa-clock me-1 text-muted"></i>
                                <?php 
                                    if (!empty($swap['requested_start_time']) && !empty($swap['requested_end_time'])): 
                                ?>
                                    <?php echo date('h:i A', strtotime($swap['requested_start_time'])); ?> - 
                                    <?php echo date('h:i A', strtotime($swap['requested_end_time'])); ?>
                                <?php else: ?>
                                    N/A
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if (!empty($swap['reason'])): ?>
                            <div style="grid-column: 1 / -1;">
                                <div class="detail-label">Reason</div>
                                <div class="text-muted small">
                                    <i class="fas fa-comment me-1"></i>
                                    <?php echo nl2br(htmlspecialchars(substr($swap['reason'], 0, 150))); ?>
                                    <?php if (strlen($swap['reason']) > 150): ?>...<?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="swap-actions">
                        <a href="<?php echo SITE_URL; ?>/views/swaps/view.php?id=<?php echo $swap['id']; ?>" 
                           class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-eye me-1"></i> View
                        </a>
                        
                        <!-- Admin Actions -->
                        <?php if ($swap['status'] === SWAP_PENDING && $role !== 'teacher'): ?>
                            <a href="<?php echo SITE_URL; ?>/views/swaps/approve.php?id=<?php echo $swap['id']; ?>" 
                               class="btn btn-sm btn-success">
                                <i class="fas fa-check me-1"></i> Approve
                            </a>
                            <a href="<?php echo SITE_URL; ?>/views/swaps/reject.php?id=<?php echo $swap['id']; ?>" 
                               class="btn btn-sm btn-danger">
                                <i class="fas fa-times me-1"></i> Reject
                            </a>
                        <?php endif; ?>
                        
                        <!-- Teacher Accept/Decline (After Admin Approval) -->
                        <?php if ($swap['status'] === SWAP_APPROVED_BY_ADMIN && $role === 'teacher'): ?>
                            <?php if ($teacherId && $teacherId == $swap['target_teacher_id']): ?>
                                <a href="<?php echo SITE_URL; ?>/views/swaps/approve-teacher.php?id=<?php echo $swap['id']; ?>" 
                                   class="btn btn-sm btn-success">
                                    <i class="fas fa-check me-1"></i> Accept
                                </a>
                                <a href="<?php echo SITE_URL; ?>/views/swaps/reject-teacher.php?id=<?php echo $swap['id']; ?>" 
                                   class="btn btn-sm btn-danger">
                                    <i class="fas fa-times me-1"></i> Decline
                                </a>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <!-- Cancel Action -->
                        <?php if (in_array($swap['status'], [SWAP_PENDING, SWAP_APPROVED_BY_ADMIN, SWAP_APPROVED_BY_TEACHER])): ?>
                            <?php 
                                $canCancel = false;
                                // Admins can always cancel
                                if ($role !== 'teacher') {
                                    $canCancel = true;
                                } 
                                // Requester can cancel their own request
                                elseif ($teacherId && $teacherId == $swap['requester_teacher_id']) {
                                    $canCancel = true;
                                }
                            ?>
                            <?php if ($canCancel): ?>
                                <a href="<?php echo SITE_URL; ?>/views/swaps/cancel.php?id=<?php echo $swap['id']; ?>" 
                                   class="btn btn-sm btn-outline-danger"
                                   onclick="return confirm('Are you sure you want to cancel this swap request?');">
                                    <i class="fas fa-ban me-1"></i> Cancel
                                </a>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <!-- Complete Action (Admin only after teacher approval) -->
                        <?php if ($swap['status'] === SWAP_APPROVED_BY_TEACHER && $role !== 'teacher'): ?>
                            <a href="<?php echo SITE_URL; ?>/views/swaps/complete.php?id=<?php echo $swap['id']; ?>" 
                               class="btn btn-sm btn-success">
                                <i class="fas fa-check-double me-1"></i> Complete Swap
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
                            <a class="page-link" href="?page=<?php echo $page - 1; ?>&status=<?php echo urlencode($status); ?>&search=<?php echo urlencode($search); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>">
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
                            <a class="page-link" href="?page=1&status=<?php echo urlencode($status); ?>&search=<?php echo urlencode($search); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>">1</a>
                        </li>
                        <?php if ($startPage > 2): ?>
                            <li class="page-item disabled"><span class="page-link">...</span></li>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo urlencode($status); ?>&search=<?php echo urlencode($search); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                    
                    <?php if ($endPage < $totalPages): ?>
                        <?php if ($endPage < $totalPages - 1): ?>
                            <li class="page-item disabled"><span class="page-link">...</span></li>
                        <?php endif; ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $totalPages; ?>&status=<?php echo urlencode($status); ?>&search=<?php echo urlencode($search); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>">
                                <?php echo $totalPages; ?>
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $page + 1; ?>&status=<?php echo urlencode($status); ?>&search=<?php echo urlencode($search); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>">
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