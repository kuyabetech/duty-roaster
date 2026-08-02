<?php
// =============================================
// Teachers Management - List View
// =============================================

// Load configuration
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../models/Teacher.php';  // Make sure this is included
require_once __DIR__ . '/../../models/Department.php';

// Initialize auth
$auth = new Auth();
if (!$auth->isLoggedIn() || !in_array($auth->getUser()['role'], ['admin', 'super_admin'])) {
    redirect(SITE_URL . '/views/auth/login.php');
}

$user = $auth->getUser();
$role = $user['role'] ?? '';

// Set page variables
$pageTitle = 'Teachers Management';
$pageIcon = 'fas fa-user-friends';
$breadcrumb = [
    ['label' => 'Dashboard', 'url' => SITE_URL . '/views/dashboard/admin.php'],
    ['label' => 'Teachers', 'active' => true]
];

// Get filters
$search = $_GET['search'] ?? '';
$department = $_GET['department'] ?? '';
$status = $_GET['status'] ?? '';

$filters = [];
if ($search) $filters['search'] = $search;
if ($department) $filters['department_id'] = $department;
if ($status) $filters['status'] = $status;

// Pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;
$filters['limit'] = $limit;
$filters['offset'] = $offset;

// Check if Teacher class exists
if (!class_exists('Teacher')) {
    die('Teacher class not found. Please check the file path.');
}

$teachers = Teacher::all($filters);
$totalCount = Teacher::count($filters);
$totalPages = ceil($totalCount / $limit);

$departments = Department::all(['status' => STATUS_ACTIVE]);

// Statistics
$db = Database::getInstance();
$stmt = $db->query("SELECT COUNT(*) as count FROM teachers WHERE deleted_at IS NULL AND status = 'active'");
$activeTeachers = $stmt->fetch()['count'] ?? 0;

$stmt = $db->query("SELECT COUNT(*) as count FROM teachers WHERE deleted_at IS NULL AND status = 'inactive'");
$inactiveTeachers = $stmt->fetch()['count'] ?? 0;

$stmt = $db->query("SELECT COUNT(*) as count FROM teachers WHERE deleted_at IS NULL AND status = 'on_leave'");
$onLeaveTeachers = $stmt->fetch()['count'] ?? 0;

// Include header
require_once __DIR__ . '/../../includes/header.php';
?>

<!-- Page Styles -->
<style>
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
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
        float: right;
        font-size: 28px;
        opacity: 0.2;
    }
    
    .teacher-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: var(--accent-gradient);
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--primary);
        font-weight: 700;
        font-size: 14px;
        background-size: cover;
        background-position: center;
    }
    
    .filter-group {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        align-items: center;
    }
    
    @media (max-width: 768px) {
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        .filter-group {
            flex-direction: column;
            align-items: stretch;
        }
        .table-responsive {
            font-size: 0.8rem;
        }
    }
</style>

<!-- Statistics -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="icon"><i class="fas fa-user-friends"></i></div>
        <div class="number"><?php echo $totalCount; ?></div>
        <div class="label">Total Teachers</div>
    </div>
    <div class="stat-card">
        <div class="icon"><i class="fas fa-check-circle"></i></div>
        <div class="number"><?php echo $activeTeachers; ?></div>
        <div class="label">Active</div>
    </div>
    <div class="stat-card">
        <div class="icon"><i class="fas fa-user-clock"></i></div>
        <div class="number"><?php echo $onLeaveTeachers; ?></div>
        <div class="label">On Leave</div>
    </div>
    <div class="stat-card">
        <div class="icon"><i class="fas fa-user-slash"></i></div>
        <div class="number"><?php echo $inactiveTeachers; ?></div>
        <div class="label">Inactive</div>
    </div>
</div>

<!-- Filters and Actions -->
<div class="card mb-4">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-12">
                <div class="filter-group">
                    <form method="GET" class="d-flex flex-wrap gap-2 flex-grow-1">
                        <input type="text" name="search" class="form-control form-control-sm" 
                               placeholder="Search teachers..." value="<?php echo htmlspecialchars($search); ?>" style="width:200px;">
                        <select name="department" class="form-select form-select-sm" style="width:180px;">
                            <option value="">All Departments</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo $dept['id']; ?>" <?php echo $department == $dept['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($dept['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <select name="status" class="form-select form-select-sm" style="width:150px;">
                            <option value="">All Status</option>
                            <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            <option value="on_leave" <?php echo $status === 'on_leave' ? 'selected' : ''; ?>>On Leave</option>
                        </select>
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="fas fa-search me-1"></i> Filter
                        </button>
                        <a href="?" class="btn btn-secondary btn-sm">
                            <i class="fas fa-times me-1"></i> Reset
                        </a>
                    </form>
                    <div class="ms-auto">
                        <a href="<?php echo SITE_URL; ?>/views/teachers/create.php" class="btn btn-accent btn-sm">
                            <i class="fas fa-plus me-1"></i> Add Teacher
                        </a>
                        <a href="<?php echo SITE_URL; ?>/views/teachers/import.php" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-file-import me-1"></i> Import
                        </a>
                        <a href="<?php echo SITE_URL; ?>/views/teachers/export.php" class="btn btn-outline-success btn-sm">
                            <i class="fas fa-file-export me-1"></i> Export
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Teachers Table -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-list me-2"></i> Teachers List (<?php echo number_format($totalCount); ?>)</span>
        <?php if ($totalCount > 0): ?>
            <span class="text-muted small">Showing <?php echo count($teachers); ?> of <?php echo number_format($totalCount); ?></span>
        <?php endif; ?>
    </div>
    <div class="card-body p-0">
        <?php if (empty($teachers)): ?>
            <div class="text-center py-5">
                <i class="fas fa-user-slash fa-3x text-muted d-block mb-3"></i>
                <h5 class="text-muted">No teachers found</h5>
                <p class="text-muted">Adjust your filters or add a new teacher.</p>
                <a href="<?php echo SITE_URL; ?>/views/teachers/create.php" class="btn btn-accent">
                    <i class="fas fa-plus me-2"></i> Add Teacher
                </a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Photo</th>
                            <th>Name</th>
                            <th>Teacher ID</th>
                            <th>Department</th>
                            <th>Position</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($teachers as $index => $teacher): ?>
                            <tr>
                                <td><?php echo $offset + $index + 1; ?></td>
                                <td>
                                    <div class="teacher-avatar" 
                                         style="background-image: url('<?php echo $teacher['profile_photo'] ? SITE_URL . '/uploads/profiles/' . $teacher['profile_photo'] : 'none'; ?>');">
                                        <?php if (!$teacher['profile_photo']): ?>
                                            <?php echo substr($teacher['first_name'] ?? 'U', 0, 1); ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars(($teacher['first_name'] ?? '') . ' ' . ($teacher['last_name'] ?? '')); ?></strong>
                                </td>
                                <td><code><?php echo htmlspecialchars($teacher['teacher_id'] ?? 'N/A'); ?></code></td>
                                <td><?php echo htmlspecialchars($teacher['department_name'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($teacher['position'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($teacher['email']); ?></td>
                                <td><?php echo htmlspecialchars($teacher['phone_primary']); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo getStatusBadgeColor($teacher['status']); ?>">
                                        <?php echo ucfirst($teacher['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?php echo SITE_URL; ?>/views/teachers/view.php?id=<?php echo $teacher['id']; ?>" 
                                           class="btn btn-outline-primary" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="<?php echo SITE_URL; ?>/views/teachers/edit.php?id=<?php echo $teacher['id']; ?>" 
                                           class="btn btn-outline-warning" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="<?php echo SITE_URL; ?>/views/teachers/delete.php?id=<?php echo $teacher['id']; ?>" 
                                           class="btn btn-outline-danger" title="Delete"
                                           onclick="return confirm('Are you sure you want to delete <?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?>?');">
                                            <i class="fas fa-trash"></i>
                                        </a>
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
                            <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&department=<?php echo urlencode($department); ?>&status=<?php echo urlencode($status); ?>">
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
                            <a class="page-link" href="?page=1&search=<?php echo urlencode($search); ?>&department=<?php echo urlencode($department); ?>&status=<?php echo urlencode($status); ?>">1</a>
                        </li>
                        <?php if ($startPage > 2): ?>
                            <li class="page-item disabled"><span class="page-link">...</span></li>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&department=<?php echo urlencode($department); ?>&status=<?php echo urlencode($status); ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                    
                    <?php if ($endPage < $totalPages): ?>
                        <?php if ($endPage < $totalPages - 1): ?>
                            <li class="page-item disabled"><span class="page-link">...</span></li>
                        <?php endif; ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $totalPages; ?>&search=<?php echo urlencode($search); ?>&department=<?php echo urlencode($department); ?>&status=<?php echo urlencode($status); ?>">
                                <?php echo $totalPages; ?>
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&department=<?php echo urlencode($department); ?>&status=<?php echo urlencode($status); ?>">
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