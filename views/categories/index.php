<?php
// =============================================
// Duty Categories Management
// =============================================

// Load configuration
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../models/DutyCategory.php';

// Initialize auth
$auth = new Auth();
if (!$auth->isLoggedIn() || !in_array($auth->getUser()['role'], ['admin', 'super_admin'])) {
    redirect(SITE_URL . '/views/auth/login.php');
}

$user = $auth->getUser();
$pageTitle = 'Duty Categories';
$pageIcon = 'fas fa-tags';
$breadcrumb = [
    ['label' => 'Dashboard', 'url' => SITE_URL . '/views/dashboard/admin.php'],
    ['label' => 'Categories', 'active' => true]
];

$db = Database::getInstance();
$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::verifyToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'create':
                $data = [
                    'name' => sanitizeInput($_POST['name'] ?? ''),
                    'code' => sanitizeInput($_POST['code'] ?? ''),
                    'description' => sanitizeInput($_POST['description'] ?? ''),
                    'color' => sanitizeInput($_POST['color'] ?? '#007bff'),
                    'icon' => sanitizeInput($_POST['icon'] ?? 'fas fa-tasks'),
                    'priority' => sanitizeInput($_POST['priority'] ?? 'normal'),
                    'duration_minutes' => (int)$_POST['duration_minutes'] ?? 60
                ];
                
                if (empty($data['name']) || empty($data['code'])) {
                    $error = 'Name and code are required.';
                } else {
                    $category = new DutyCategory();
                    if ($category->create($data)) {
                        $success = 'Category created successfully!';
                    } else {
                        $error = 'Failed to create category.';
                    }
                }
                break;
                
            case 'delete':
                $id = (int)$_POST['id'] ?? 0;
                if ($id) {
                    $category = new DutyCategory($id);
                    if ($category->delete()) {
                        $success = 'Category deleted successfully!';
                    } else {
                        $error = 'Failed to delete category.';
                    }
                }
                break;
        }
    }
}

$categories = DutyCategory::all();

// Include header
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row">
    <!-- Add Category Form -->
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-plus me-2"></i> Add Category
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <form method="POST">
                    <?php echo CSRF::getTokenField(); ?>
                    <input type="hidden" name="action" value="create">
                    
                    <div class="mb-3">
                        <label class="form-label">Category Name *</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Code *</label>
                        <input type="text" name="code" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Color</label>
                            <input type="color" name="color" class="form-control" value="#007bff">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Priority</label>
                            <select name="priority" class="form-control">
                                <option value="low">Low</option>
                                <option value="normal" selected>Normal</option>
                                <option value="high">High</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Duration (minutes)</label>
                        <input type="number" name="duration_minutes" class="form-control" value="60">
                    </div>
                    
                    <button type="submit" class="btn btn-accent w-100">
                        <i class="fas fa-plus me-2"></i> Add Category
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Categories List -->
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-list me-2"></i> Categories (<?php echo count($categories); ?>)
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Name</th>
                                <th>Code</th>
                                <th>Color</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($categories)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4">
                                        <i class="fas fa-tag fa-2x text-muted d-block mb-2"></i>
                                        <span class="text-muted">No categories found</span>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($categories as $index => $cat): ?>
                                    <tr>
                                        <td><?php echo $index + 1; ?></td>
                                        <td>
                                            <i class="<?php echo $cat['icon'] ?? 'fas fa-tasks'; ?> me-2"></i>
                                            <?php echo htmlspecialchars($cat['name']); ?>
                                        </td>
                                        <td><code><?php echo htmlspecialchars($cat['code']); ?></code></td>
                                        <td>
                                            <span style="display:inline-block; width:20px; height:20px; border-radius:4px; background:<?php echo $cat['color']; ?>;"></span>
                                            <?php echo htmlspecialchars($cat['color']); ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo getPriorityBadgeColor($cat['priority']); ?>">
                                                <?php echo ucfirst($cat['priority']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo getStatusBadgeColor($cat['status']); ?>">
                                                <?php echo ucfirst($cat['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="<?php echo SITE_URL; ?>/views/categories/edit.php?id=<?php echo $cat['id']; ?>" 
                                                   class="btn btn-outline-warning">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <form method="POST" class="d-inline" 
                                                      onsubmit="return confirm('Delete this category?');">
                                                    <?php echo CSRF::getTokenField(); ?>
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?php echo $cat['id']; ?>">
                                                    <button type="submit" class="btn btn-outline-danger">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
require_once __DIR__ . '/../../includes/footer.php';
?>