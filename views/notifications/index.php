<?php
// =============================================
// Notifications Page
// =============================================

// Load configuration
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../models/Notification.php';

// Initialize auth
$auth = new Auth();
if (!$auth->isLoggedIn()) {
    redirect(SITE_URL . '/views/auth/login.php');
}

$user = $auth->getUser();
$userId = $user['id'];
$role = $user['role'] ?? 'teacher';

// Set page variables
$pageTitle = 'Notifications';
$pageIcon = 'fas fa-bell';
$breadcrumb = [
    ['label' => 'Dashboard', 'url' => SITE_URL . '/views/dashboard/' . ($role === 'teacher' ? 'teacher' : 'admin') . '.php'],
    ['label' => 'Notifications', 'active' => true]
];

// Get filters
$filter = $_GET['filter'] ?? 'all';
$search = $_GET['search'] ?? '';

// Build filters
$filters = [];
if ($filter === 'unread') {
    $filters['is_read'] = 0;
} elseif ($filter === 'read') {
    $filters['is_read'] = 1;
}
if ($search) {
    $filters['search'] = $search;
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;
$filters['limit'] = $limit;
$filters['offset'] = $offset;

// Get notifications
$notifications = Notification::getByUser($userId, $filters);
$unreadCount = Notification::getUnreadCount($userId);

// Get total count for pagination
$db = Database::getInstance();
$sql = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ?";
$params = [$userId];
if ($filter === 'unread') {
    $sql .= " AND is_read = 0";
} elseif ($filter === 'read') {
    $sql .= " AND is_read = 1";
}
if ($search) {
    $sql .= " AND (title LIKE ? OR message LIKE ?)";
    $searchParam = '%' . $search . '%';
    $params[] = $searchParam;
    $params[] = $searchParam;
}
$stmt = $db->prepare($sql);
$stmt->execute($params);
$totalCount = $stmt->fetch()['count'] ?? 0;
$totalPages = ceil($totalCount / $limit);

// Priority colors
$priorityColors = [
    'urgent' => 'danger',
    'high' => 'warning',
    'medium' => 'info',
    'low' => 'secondary'
];

$priorityIcons = [
    'urgent' => 'fas fa-exclamation-circle',
    'high' => 'fas fa-arrow-up',
    'medium' => 'fas fa-minus',
    'low' => 'fas fa-arrow-down'
];

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $notificationId = $_POST['notification_id'] ?? 0;
    
    if (!CSRF::verifyToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request.';
    } else {
        switch ($action) {
            case 'mark_read':
                if ($notificationId) {
                    $notif = new Notification($notificationId);
                    if ($notif->getData() && $notif->getData()['user_id'] == $userId) {
                        $notif->markAsRead();
                    }
                }
                break;
            case 'mark_unread':
                if ($notificationId) {
                    $notif = new Notification($notificationId);
                    if ($notif->getData() && $notif->getData()['user_id'] == $userId) {
                        $notif->markAsUnread();
                    }
                }
                break;
            case 'delete':
                if ($notificationId) {
                    $notif = new Notification($notificationId);
                    if ($notif->getData() && $notif->getData()['user_id'] == $userId) {
                        $notif->delete();
                    }
                }
                break;
            case 'mark_all_read':
                Notification::markAllAsRead($userId);
                break;
            case 'delete_all':
                Notification::deleteAll($userId);
                break;
        }
        redirect(SITE_URL . '/views/notifications/?filter=' . $filter . '&search=' . urlencode($search));
    }
}

// Include header
require_once __DIR__ . '/../../includes/header.php';
?>

<!-- Page Styles -->
<style>
    .notification-item {
        display: flex;
        align-items: flex-start;
        padding: 15px 20px;
        border-bottom: 1px solid var(--border-color);
        transition: var(--transition);
        cursor: pointer;
        gap: 15px;
    }
    
    .notification-item:hover {
        background: var(--bg-light);
    }
    
    .notification-item.unread {
        background: rgba(255, 215, 0, 0.05);
        border-left: 3px solid var(--accent);
    }
    
    .notification-item .notif-icon {
        width: 45px;
        height: 45px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        font-size: 18px;
    }
    
    .notification-item .notif-icon.urgent { background: var(--danger-light); color: var(--danger); }
    .notification-item .notif-icon.high { background: var(--warning-light); color: var(--warning); }
    .notification-item .notif-icon.medium { background: var(--info-light); color: var(--info); }
    .notification-item .notif-icon.low { background: var(--bg-secondary); color: var(--text-muted); }
    
    .notification-item .notif-content {
        flex: 1;
        min-width: 0;
    }
    
    .notification-item .notif-title {
        font-weight: 600;
        margin-bottom: 3px;
        font-size: 0.95rem;
    }
    
    .notification-item .notif-message {
        color: var(--text-muted);
        font-size: 0.875rem;
        margin-bottom: 5px;
    }
    
    .notification-item .notif-time {
        font-size: 0.75rem;
        color: var(--text-light);
    }
    
    .notification-item .notif-actions {
        display: flex;
        gap: 5px;
        flex-shrink: 0;
    }
    
    .notification-item .notif-actions .btn {
        padding: 2px 8px;
        font-size: 0.7rem;
    }
    
    .notification-item .notif-badge {
        font-size: 0.6rem;
        padding: 2px 8px;
        border-radius: 12px;
    }
    
    .notification-item .notif-link {
        color: var(--primary);
        text-decoration: none;
    }
    
    .notification-item .notif-link:hover {
        color: var(--accent);
        text-decoration: underline;
    }
    
    .notification-empty {
        text-align: center;
        padding: 60px 20px;
    }
    
    .notification-empty i {
        font-size: 60px;
        color: var(--text-light);
        margin-bottom: 20px;
        display: block;
    }
    
    .notification-empty h4 {
        color: var(--text-muted);
    }
    
    .filter-badge {
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 0.8rem;
        cursor: pointer;
        transition: var(--transition);
        border: 1px solid var(--border-color);
        background: var(--bg-white);
        text-decoration: none;
        color: var(--text-dark);
    }
    
    .filter-badge:hover {
        border-color: var(--accent);
        text-decoration: none;
    }
    
    .filter-badge.active {
        background: var(--accent);
        border-color: var(--accent);
        color: var(--primary);
    }
    
    .filter-badge .count {
        background: var(--danger);
        color: #fff;
        padding: 0 6px;
        border-radius: 10px;
        font-size: 0.7rem;
        margin-left: 4px;
    }
    
    @media (max-width: 768px) {
        .notification-item {
            padding: 12px 15px;
            flex-wrap: wrap;
        }
        .notification-item .notif-actions {
            width: 100%;
            justify-content: flex-end;
            margin-top: 5px;
        }
        .filter-badge {
            font-size: 0.7rem;
            padding: 4px 10px;
        }
        .top-header .user-info .user-name {
            display: none;
        }
    }
</style>

<!-- Filters and Actions -->
<div class="card mb-4">
    <div class="card-body">
        <div class="row g-3 align-items-center">
            <div class="col-md-6">
                <div class="d-flex gap-2 flex-wrap">
                    <a href="?filter=all" class="filter-badge <?php echo $filter === 'all' ? 'active' : ''; ?>">
                        <i class="fas fa-list me-1"></i> All
                    </a>
                    <a href="?filter=unread" class="filter-badge <?php echo $filter === 'unread' ? 'active' : ''; ?>">
                        <i class="fas fa-envelope me-1"></i> Unread
                        <?php if ($unreadCount > 0): ?>
                            <span class="count"><?php echo $unreadCount; ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="?filter=read" class="filter-badge <?php echo $filter === 'read' ? 'active' : ''; ?>">
                        <i class="fas fa-check-circle me-1"></i> Read
                    </a>
                </div>
            </div>
            <div class="col-md-6">
                <div class="d-flex gap-2 justify-content-md-end flex-wrap">
                    <form method="GET" class="d-flex gap-2">
                        <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
                        <input type="text" name="search" class="form-control form-control-sm" 
                               placeholder="Search notifications..." value="<?php echo htmlspecialchars($search); ?>" 
                               style="width: 200px;">
                        <button type="submit" class="btn btn-sm btn-primary">
                            <i class="fas fa-search"></i>
                        </button>
                    </form>
                    <?php if ($notifications): ?>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Mark all as read?');">
                            <?php echo CSRF::getTokenField(); ?>
                            <input type="hidden" name="action" value="mark_all_read">
                            <button type="submit" class="btn btn-sm btn-outline-success">
                                <i class="fas fa-check-double me-1"></i> Mark All Read
                            </button>
                        </form>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Delete all notifications?');">
                            <?php echo CSRF::getTokenField(); ?>
                            <input type="hidden" name="action" value="delete_all">
                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                <i class="fas fa-trash me-1"></i> Delete All
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Notifications List -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-list me-2"></i> Notifications (<?php echo number_format($totalCount); ?>)</span>
        <?php if ($totalCount > 0 && $totalCount > $limit): ?>
            <span class="text-muted small">Showing <?php echo count($notifications); ?> of <?php echo number_format($totalCount); ?></span>
        <?php endif; ?>
    </div>
    <div class="card-body p-0">
        <?php if (empty($notifications)): ?>
            <div class="notification-empty">
                <i class="fas fa-bell-slash"></i>
                <h4>No notifications</h4>
                <p class="text-muted">You're all caught up!</p>
                <a href="<?php echo SITE_URL; ?>/views/dashboard/<?php echo $role === 'teacher' ? 'teacher' : 'admin'; ?>.php" class="btn btn-accent mt-3">
                    <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
                </a>
            </div>
        <?php else: ?>
            <?php foreach ($notifications as $notif): ?>
                <div class="notification-item <?php echo $notif['is_read'] ? 'read' : 'unread'; ?>">
                    <div class="notif-icon <?php echo $notif['priority']; ?>">
                        <i class="<?php echo $notif['icon'] ?? ($priorityIcons[$notif['priority']] ?? 'fas fa-bell'); ?>"></i>
                    </div>
                    <div class="notif-content">
                        <div class="notif-title">
                            <?php if (!$notif['is_read']): ?>
                                <span class="badge bg-primary me-1">New</span>
                            <?php endif; ?>
                            <?php echo htmlspecialchars($notif['title']); ?>
                            <span class="badge bg-<?php echo $priorityColors[$notif['priority']] ?? 'secondary'; ?> notif-badge">
                                <?php echo ucfirst($notif['priority'] ?? 'medium'); ?>
                            </span>
                        </div>
                        <div class="notif-message">
                            <?php echo nl2br(htmlspecialchars($notif['message'])); ?>
                        </div>
                        <div class="notif-time">
                            <i class="far fa-clock me-1"></i>
                            <?php echo timeAgo($notif['created_at']); ?>
                        </div>
                        <?php if ($notif['link']): ?>
                            <a href="<?php echo $notif['link']; ?>" class="notif-link">
                                <i class="fas fa-arrow-right me-1"></i> View Details
                            </a>
                        <?php endif; ?>
                    </div>
                    <div class="notif-actions">
                        <form method="POST" class="d-inline">
                            <?php echo CSRF::getTokenField(); ?>
                            <input type="hidden" name="notification_id" value="<?php echo $notif['id']; ?>">
                            <?php if ($notif['is_read']): ?>
                                <input type="hidden" name="action" value="mark_unread">
                                <button type="submit" class="btn btn-sm btn-outline-secondary" title="Mark as unread">
                                    <i class="fas fa-envelope"></i>
                                </button>
                            <?php else: ?>
                                <input type="hidden" name="action" value="mark_read">
                                <button type="submit" class="btn btn-sm btn-outline-success" title="Mark as read">
                                    <i class="fas fa-check"></i>
                                </button>
                            <?php endif; ?>
                            <input type="hidden" name="action" value="delete">
                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete" 
                                    onclick="return confirm('Delete this notification?');">
                                <i class="fas fa-times"></i>
                            </button>
                        </form>
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
                            <a class="page-link" href="?page=<?php echo $page - 1; ?>&filter=<?php echo urlencode($filter); ?>&search=<?php echo urlencode($search); ?>">
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
                            <a class="page-link" href="?page=1&filter=<?php echo urlencode($filter); ?>&search=<?php echo urlencode($search); ?>">1</a>
                        </li>
                        <?php if ($startPage > 2): ?>
                            <li class="page-item disabled"><span class="page-link">...</span></li>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>&filter=<?php echo urlencode($filter); ?>&search=<?php echo urlencode($search); ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                    
                    <?php if ($endPage < $totalPages): ?>
                        <?php if ($endPage < $totalPages - 1): ?>
                            <li class="page-item disabled"><span class="page-link">...</span></li>
                        <?php endif; ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $totalPages; ?>&filter=<?php echo urlencode($filter); ?>&search=<?php echo urlencode($search); ?>">
                                <?php echo $totalPages; ?>
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $page + 1; ?>&filter=<?php echo urlencode($filter); ?>&search=<?php echo urlencode($search); ?>">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// =============================================
// Auto-mark as read when clicking on notification
// =============================================
document.querySelectorAll('.notification-item').forEach(item => {
    item.addEventListener('click', function(e) {
        // Don't trigger if clicking on a button or link
        if (e.target.closest('button') || e.target.closest('a') || e.target.closest('form')) {
            return;
        }
        
        const markReadBtn = this.querySelector('button[value="mark_read"]');
        if (markReadBtn) {
            markReadBtn.click();
        }
    });
});

// =============================================
// Auto-dismiss alerts
// =============================================
setTimeout(() => {
    document.querySelectorAll('.alert-dismissible').forEach(el => {
        const closeBtn = el.querySelector('.btn-close');
        if (closeBtn) {
            setTimeout(() => closeBtn.click(), 5000);
        }
    });
}, 1000);

// =============================================
// Keyboard shortcuts
// =============================================
document.addEventListener('keydown', function(e) {
    // 'r' key - Mark all as read
    if (e.key === 'r' && !e.ctrlKey && !e.metaKey && !e.altKey) {
        const markAllBtn = document.querySelector('button[value="mark_all_read"]');
        if (markAllBtn && confirm('Mark all notifications as read?')) {
            markAllBtn.click();
        }
    }
    
    // 'd' key - Delete all
    if (e.key === 'd' && !e.ctrlKey && !e.metaKey && !e.altKey) {
        const deleteAllBtn = document.querySelector('button[value="delete_all"]');
        if (deleteAllBtn && confirm('Delete all notifications?')) {
            deleteAllBtn.click();
        }
    }
});

console.log('🔔 Notifications loaded successfully!');
console.log('📊 Total: <?php echo $totalCount; ?>');
console.log('📬 Unread: <?php echo $unreadCount; ?>');
</script>

<?php
// Include footer
require_once __DIR__ . '/../../includes/footer.php';
?>