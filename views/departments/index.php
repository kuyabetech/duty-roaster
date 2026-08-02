<?php
// =============================================
// Departments Management - List View
// =============================================

// Load configuration
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../models/Department.php';

// Initialize auth
$auth = new Auth();
if (!$auth->isLoggedIn() || !in_array($auth->getUser()['role'], ['admin', 'super_admin'])) {
    redirect(SITE_URL . '/views/auth/login.php');
}

$user = $auth->getUser();
$pageTitle = 'Departments Management';
$pageIcon = 'fas fa-building';
$breadcrumb = [
    ['label' => 'Dashboard', 'url' => SITE_URL . '/views/dashboard/admin.php'],
    ['label' => 'Departments', 'active' => true]
];

// Get filters
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';

$filters = [];
if ($search) $filters['search'] = $search;
if ($status) $filters['status'] = $status;

$departments = Department::all($filters);
$totalCount = count($departments);

// Include header
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-building me-2"></i> Departments (<?php echo number_format($totalCount); ?>)</span>
        <a href="<?php echo SITE_URL; ?>/views/departments/create.php" class="btn btn-accent btn-sm">
            <i class="fas fa-plus me-1"></i> Add Department
        </a>
    </div>
    <div class="card-body">
        <?php if (empty($departments)): ?>
            <div class="text-center py-5">
                <i class="fas fa-building fa-3x text-muted d-block mb-3"></i>
                <h5 class="text-muted">No departments found</h5>
                <a href="<?php echo SITE_URL; ?>/views/departments/create.php" class="btn btn-accent">
                    <i class="fas fa-plus me-2"></i> Add Department
                </a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Name</th>
                            <th>Code</th>
                            <th>HOD</th>
                            <th>Teachers</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($departments as $index => $dept): ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td><strong><?php echo htmlspecialchars($dept['name']); ?></strong></td>
                                <td><code><?php echo htmlspecialchars($dept['code']); ?></code></td>
                                <td><?php echo htmlspecialchars($dept['hod_name'] ?? 'Not Assigned'); ?></td>
                                <td><?php echo $dept['teacher_count'] ?? 0; ?></td>
                                <td>
                                    <span class="badge bg-<?php echo getStatusBadgeColor($dept['status']); ?>">
                                        <?php echo ucfirst($dept['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?php echo SITE_URL; ?>/views/departments/view.php?id=<?php echo $dept['id']; ?>" 
                                           class="btn btn-outline-primary">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="<?php echo SITE_URL; ?>/views/departments/edit.php?id=<?php echo $dept['id']; ?>" 
                                           class="btn btn-outline-warning">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="<?php echo SITE_URL; ?>/views/departments/delete.php?id=<?php echo $dept['id']; ?>" 
                                           class="btn btn-outline-danger"
                                           onclick="return confirm('Are you sure you want to delete this department?');">
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
</div>

<?php
// Include footer
require_once __DIR__ . '/../../includes/footer.php';
?>