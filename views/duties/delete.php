<?php
// =============================================
// Delete Duty
// =============================================

// Load configuration
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../models/Duty.php';
require_once __DIR__ . '/../../models/Notification.php';

// Initialize auth
$auth = new Auth();
if (!$auth->isLoggedIn() || !in_array($auth->getUser()['role'], ['admin', 'super_admin'])) {
    redirect(SITE_URL . '/views/auth/login.php');
}

$user = $auth->getUser();
$role = $user['role'] ?? '';

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

// Check if duty can be deleted (only pending or rejected)
if (!in_array($dutyData['status'], [DUTY_PENDING, DUTY_REJECTED])) {
    setFlashMessage('warning', 'This duty cannot be deleted because it is ' . $dutyData['status'] . '.');
    redirect(SITE_URL . '/views/duties/view.php?id=' . $id);
}

// Set page variables
$pageTitle = 'Delete Duty';
$pageIcon = 'fas fa-trash';
$breadcrumb = [
    ['label' => 'Dashboard', 'url' => SITE_URL . '/views/dashboard/admin.php'],
    ['label' => 'Duties', 'url' => SITE_URL . '/views/duties/'],
    ['label' => 'Delete Duty', 'active' => true]
];

$error = '';
$success = '';

// Process deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::verifyToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('error', 'Invalid request. Please try again.');
        redirect(SITE_URL . '/views/duties/delete.php?id=' . $id);
    }
    
    $confirmation = sanitizeInput($_POST['confirmation'] ?? '');
    $reason = sanitizeInput($_POST['reason'] ?? '');
    
    // Require confirmation text
    if (strtolower($confirmation) !== 'delete') {
        setFlashMessage('error', 'Please type "delete" to confirm deletion.');
        redirect(SITE_URL . '/views/duties/delete.php?id=' . $id);
    }
    
    try {
        // Check if duty has any swap requests
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM duty_swaps WHERE duty_id = ? AND status IN (?, ?, ?)");
        $stmt->execute([$id, SWAP_PENDING, SWAP_APPROVED_BY_ADMIN, SWAP_APPROVED_BY_TEACHER]);
        $swapCount = $stmt->fetch()['count'] ?? 0;
        
        if ($swapCount > 0) {
            setFlashMessage('warning', 'This duty has active swap requests. Please cancel them first.');
            redirect(SITE_URL . '/views/duties/view.php?id=' . $id);
        }
        
        // Get duty data for notification before deletion
        $dutyCode = $dutyData['duty_code'];
        $teacherId = $dutyData['teacher_id'];
        
        if ($duty->delete()) {
            // Notify teacher if duty was assigned
            if ($teacherId) {
                Notification::send(
                    $teacherId,
                    'duty_deleted',
                    'Duty Cancelled',
                    'Your duty ' . $dutyCode . ' has been deleted by admin. Reason: ' . ($reason ?: 'No reason provided'),
                    null,
                    'high'
                );
            }
            
            setFlashMessage('success', 'Duty deleted successfully!');
            redirect(SITE_URL . '/views/duties/');
        } else {
            setFlashMessage('error', 'Failed to delete duty. Please try again.');
            redirect(SITE_URL . '/views/duties/delete.php?id=' . $id);
        }
    } catch (Exception $e) {
        setFlashMessage('error', 'System error: ' . $e->getMessage());
        redirect(SITE_URL . '/views/duties/delete.php?id=' . $id);
    }
}

// Include header
require_once __DIR__ . '/../../includes/header.php';
?>

<!-- Page Styles -->
<style>
    .duty-summary {
        background: var(--bg-light);
        border-radius: var(--radius);
        padding: 20px;
        margin-bottom: 25px;
        border-left: 4px solid var(--danger);
    }
    .duty-summary .summary-item {
        display: flex;
        justify-content: space-between;
        padding: 8px 0;
        border-bottom: 1px solid var(--border-light);
    }
    .duty-summary .summary-item:last-child {
        border-bottom: none;
    }
    .duty-summary .summary-label {
        color: var(--text-muted);
        font-weight: 500;
    }
    .duty-summary .summary-value {
        font-weight: 600;
    }
    
    .warning-card {
        background: #fff3cd;
        border: 2px solid #ffc107;
        border-radius: var(--radius);
        padding: 20px;
        margin-bottom: 25px;
    }
    .warning-card .warning-icon {
        font-size: 48px;
        color: #ffc107;
        display: block;
        margin-bottom: 10px;
    }
    .warning-card h5 {
        color: #856404;
        font-weight: 600;
    }
    .warning-card p {
        color: #856404;
        margin-bottom: 0;
    }
    
    .action-card {
        background: var(--bg-white);
        border-radius: var(--radius);
        padding: 20px;
        border: 2px solid var(--border-color);
        transition: var(--transition);
    }
    .action-card:hover {
        border-color: var(--danger);
    }
    
    .confirmation-input {
        font-size: 1.1rem;
        font-weight: 600;
        text-align: center;
        letter-spacing: 2px;
    }
    .confirmation-input:focus {
        border-color: var(--danger);
        box-shadow: 0 0 0 0.25rem rgba(220, 53, 69, 0.25);
    }
    
    .duty-status-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
    }
    .duty-status-badge.pending { background: #fff3cd; color: #856404; }
    .duty-status-badge.accepted { background: #d1ecf1; color: #0c5460; }
    .duty-status-badge.completed { background: #d4edda; color: #155724; }
    .duty-status-badge.rejected { background: #f8d7da; color: #721c24; }
    .duty-status-badge.missed { background: #f8d7da; color: #721c24; }
    .duty-status-badge.cancelled { background: #e2e3e5; color: #383d41; }
    
    .btn-danger {
        background: var(--danger);
        border-color: var(--danger);
        transition: var(--transition);
        min-width: 150px;
    }
    .btn-danger:hover {
        background: #dc2626;
        border-color: #dc2626;
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3);
    }
    .btn-danger:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none;
    }
    
    @media (max-width: 768px) {
        .duty-summary .summary-item {
            flex-direction: column;
            gap: 2px;
        }
        .action-card .d-flex {
            flex-direction: column;
            gap: 10px;
        }
        .action-card .d-flex .btn {
            width: 100%;
        }
    }
</style>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <!-- Warning Card -->
        <div class="warning-card">
            <div class="text-center">
                <i class="fas fa-exclamation-triangle warning-icon"></i>
                <h5>⚠️ Warning: This action cannot be undone!</h5>
                <p>Deleting this duty will permanently remove it from the system. All associated data will be lost.</p>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-trash me-2" style="color: var(--danger);"></i>
                    <?php echo $pageTitle; ?>
                </h5>
                <small class="text-muted">Duty: <?php echo htmlspecialchars($dutyData['duty_code']); ?></small>
            </div>
            <div class="card-body">
                <!-- Duty Summary -->
                <div class="duty-summary">
                    <h6 class="mb-3"><i class="fas fa-info-circle me-2"></i>Duty Information</h6>
                    
                    <div class="summary-item">
                        <span class="summary-label">Duty Code</span>
                        <span class="summary-value"><code><?php echo htmlspecialchars($dutyData['duty_code']); ?></code></span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Status</span>
                        <span class="summary-value">
                            <span class="duty-status-badge <?php echo $dutyData['status']; ?>">
                                <?php echo ucfirst($dutyData['status']); ?>
                            </span>
                        </span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Category</span>
                        <span class="summary-value">
                            <span class="badge" style="background: <?php echo $dutyData['category_color'] ?? '#6c757d'; ?>; color: #fff;">
                                <?php echo htmlspecialchars($dutyData['category_name']); ?>
                            </span>
                        </span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Teacher</span>
                        <span class="summary-value">
                            <?php echo htmlspecialchars(($dutyData['first_name'] ?? '') . ' ' . ($dutyData['last_name'] ?? '')); ?>
                        </span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Date</span>
                        <span class="summary-value"><?php echo formatDate($dutyData['duty_date']); ?></span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Time</span>
                        <span class="summary-value">
                            <?php echo date('h:i A', strtotime($dutyData['start_time'])); ?> - 
                            <?php echo date('h:i A', strtotime($dutyData['end_time'])); ?>
                        </span>
                    </div>
                    <?php if ($dutyData['location']): ?>
                        <div class="summary-item">
                            <span class="summary-label">Location</span>
                            <span class="summary-value"><?php echo htmlspecialchars($dutyData['location']); ?></span>
                        </div>
                    <?php endif; ?>
                    <div class="summary-item">
                        <span class="summary-label">Priority</span>
                        <span class="summary-value">
                            <span class="badge bg-<?php echo getPriorityBadgeColor($dutyData['priority']); ?>">
                                <?php echo ucfirst($dutyData['priority']); ?>
                            </span>
                        </span>
                    </div>
                </div>
                
                <!-- Deletion Form -->
                <div class="action-card">
                    <h6 class="mb-3">
                        <i class="fas fa-question-circle me-2" style="color: var(--danger);"></i>
                        Confirm Deletion
                    </h6>
                    
                    <form method="POST" id="deleteForm">
                        <?php echo CSRF::getTokenField(); ?>
                        
                        <div class="mb-3">
                            <label class="form-label">
                                Type <strong>"delete"</strong> to confirm <span class="required-star">*</span>
                            </label>
                            <input type="text" name="confirmation" class="form-control confirmation-input" 
                                   placeholder="Type 'delete' to confirm" required autofocus>
                            <div class="invalid-feedback">Please type "delete" to confirm.</div>
                            <small class="text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                This is a safety measure to prevent accidental deletions.
                            </small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Reason for Deletion (Optional)</label>
                            <textarea name="reason" class="form-control" rows="3" 
                                      placeholder="Why is this duty being deleted?"></textarea>
                            <small class="text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                This reason will be shared with the assigned teacher.
                            </small>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center mt-4">
                            <a href="<?php echo SITE_URL; ?>/views/duties/view.php?id=<?php echo $id; ?>" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-2"></i> Cancel
                            </a>
                            <button type="submit" class="btn btn-danger" id="deleteBtn">
                                <i class="fas fa-trash me-2"></i> Permanently Delete
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('deleteForm');
    const deleteBtn = document.getElementById('deleteBtn');
    const confirmationInput = document.querySelector('input[name="confirmation"]');
    
    // =============================================
    // Real-time confirmation check
    // =============================================
    confirmationInput.addEventListener('input', function() {
        if (this.value.toLowerCase() === 'delete') {
            this.classList.remove('is-invalid');
            this.classList.add('is-valid');
            deleteBtn.disabled = false;
        } else {
            this.classList.remove('is-valid');
            if (this.value.length > 0) {
                this.classList.add('is-invalid');
            }
            deleteBtn.disabled = true;
        }
    });
    
    // =============================================
    // Form Submission
    // =============================================
    form.addEventListener('submit', function(e) {
        const confirmation = confirmationInput.value.trim();
        
        // Validate confirmation
        if (confirmation.toLowerCase() !== 'delete') {
            e.preventDefault();
            confirmationInput.classList.add('is-invalid');
            confirmationInput.focus();
            alert('Please type "delete" to confirm deletion.');
            return;
        }
        
        // Final confirmation
        if (!confirm('⚠️ Are you absolutely sure you want to delete this duty? This action cannot be undone!')) {
            e.preventDefault();
            return;
        }
        
        // Disable button and show loading
        deleteBtn.disabled = true;
        deleteBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Deleting...';
    });
    
    // =============================================
    // Keyboard shortcut: Enter to submit when valid
    // =============================================
    confirmationInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && this.value.toLowerCase() === 'delete') {
            e.preventDefault();
            deleteBtn.click();
        }
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
    
    console.log('🗑️ Delete Duty page loaded for duty: <?php echo htmlspecialchars($dutyData['duty_code']); ?>');
});
</script>

<?php
// Include footer
require_once __DIR__ . '/../../includes/footer.php';
?>