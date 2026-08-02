<?php
// =============================================
// Admin Approves Swap Request
// =============================================

// Load configuration
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../models/Swap.php';
require_once __DIR__ . '/../../models/Duty.php';
require_once __DIR__ . '/../../models/Notification.php';

// Initialize auth
$auth = new Auth();
if (!$auth->isLoggedIn() || !in_array($auth->getUser()['role'], ['admin', 'super_admin'])) {
    redirect(SITE_URL . '/views/auth/login.php');
}

$user = $auth->getUser();
$role = $user['role'] ?? 'admin';

// Get swap ID
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    setFlashMessage('error', 'Invalid swap request ID.');
    redirect(SITE_URL . '/views/swaps/');
}

// Load swap
$swap = new Swap($id);
if (!$swap->getData()) {
    setFlashMessage('error', 'Swap request not found.');
    redirect(SITE_URL . '/views/swaps/');
}

$swapData = $swap->getData();

// Check if swap is in correct status
if ($swapData['status'] !== SWAP_PENDING) {
    setFlashMessage('warning', 'This swap request has already been processed. Current status: ' . $swapData['status']);
    redirect(SITE_URL . '/views/swaps/view.php?id=' . $id);
}

// Process approval
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::verifyToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('error', 'Invalid request. Please try again.');
        redirect(SITE_URL . '/views/swaps/approve.php?id=' . $id);
    }
    
    $notes = sanitizeInput($_POST['notes'] ?? '');
    
    try {
        if ($swap->approveByAdmin($user['id'])) {
            // Add notes if provided
            if ($notes) {
                $db = Database::getInstance();
                $db->prepare("UPDATE duty_swaps SET reason = CONCAT(reason, '\n\nAdmin notes: ', ?) WHERE id = ?")
                   ->execute([$notes, $id]);
            }
            
            // Notify target teacher
            Notification::send(
                $swapData['target_teacher_id'],
                'swap_approved_admin',
                'Swap Request Approved by Admin',
                'A swap request has been approved by admin. Please review and accept or decline.',
                SITE_URL . '/views/swaps/approve-teacher.php?id=' . $id,
                'high'
            );
            
            // Notify requester
            Notification::send(
                $swapData['requester_teacher_id'],
                'swap_approved_admin',
                'Swap Request Approved by Admin',
                'Your swap request has been approved by admin. Waiting for target teacher response.',
                SITE_URL . '/views/swaps/view.php?id=' . $id,
                'high'
            );
            
            setFlashMessage('success', 'Swap request approved successfully! Target teacher has been notified.');
            redirect(SITE_URL . '/views/swaps/view.php?id=' . $id);
        } else {
            setFlashMessage('error', 'Failed to approve swap request. Please try again.');
            redirect(SITE_URL . '/views/swaps/approve.php?id=' . $id);
        }
    } catch (Exception $e) {
        setFlashMessage('error', 'System error: ' . $e->getMessage());
        redirect(SITE_URL . '/views/swaps/approve.php?id=' . $id);
    }
}

// Set page variables
$pageTitle = 'Approve Swap Request';
$pageIcon = 'fas fa-check-circle';
$breadcrumb = [
    ['label' => 'Dashboard', 'url' => SITE_URL . '/views/dashboard/admin.php'],
    ['label' => 'Swap Requests', 'url' => SITE_URL . '/views/swaps/'],
    ['label' => 'Approve Swap', 'active' => true]
];

// Include header
require_once __DIR__ . '/../../includes/header.php';
?>

<!-- Page Styles -->
<style>
    .swap-summary {
        background: var(--bg-light);
        border-radius: var(--radius);
        padding: 20px;
        margin-bottom: 25px;
        border-left: 4px solid var(--success);
        transition: var(--transition);
    }
    .swap-summary:hover {
        box-shadow: var(--shadow-sm);
    }
    .swap-summary .summary-item {
        display: flex;
        justify-content: space-between;
        padding: 8px 0;
        border-bottom: 1px solid var(--border-light);
    }
    .swap-summary .summary-item:last-child {
        border-bottom: none;
    }
    .swap-summary .summary-label {
        color: var(--text-muted);
        font-weight: 500;
        font-size: 0.85rem;
    }
    .swap-summary .summary-value {
        font-weight: 600;
        font-size: 0.9rem;
    }
    
    .action-card {
        background: var(--bg-white);
        border-radius: var(--radius);
        padding: 20px;
        border: 2px solid var(--border-color);
        transition: var(--transition);
    }
    .action-card:hover {
        border-color: var(--success);
        box-shadow: var(--shadow-sm);
    }
    
    .swap-status-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
    }
    .swap-status-badge.pending { background: #fff3cd; color: #856404; }
    .swap-status-badge.approved_by_admin { background: #d1ecf1; color: #0c5460; }
    .swap-status-badge.approved_by_teacher { background: #d4edda; color: #155724; }
    .swap-status-badge.completed { background: #d4edda; color: #155724; }
    .swap-status-badge.rejected_by_teacher { background: #f8d7da; color: #721c24; }
    .swap-status-badge.cancelled { background: #e2e3e5; color: #383d41; }
    
    .info-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
        margin: 15px 0;
    }
    .info-grid .info-item {
        background: var(--bg-white);
        padding: 10px 15px;
        border-radius: var(--radius);
        border: 1px solid var(--border-light);
        transition: var(--transition);
    }
    .info-grid .info-item:hover {
        border-color: var(--accent);
    }
    .info-grid .info-item .info-label {
        font-size: 0.65rem;
        text-transform: uppercase;
        color: var(--text-muted);
        letter-spacing: 0.05em;
        font-weight: 600;
    }
    .info-grid .info-item .info-value {
        font-weight: 500;
        font-size: 0.95rem;
        margin-top: 2px;
    }
    .info-grid .info-item .info-value.text-success {
        color: var(--success);
    }
    
    .required-star {
        color: var(--danger);
        margin-left: 2px;
    }
    
    .form-control:focus {
        border-color: var(--accent);
        box-shadow: 0 0 0 0.25rem rgba(255, 215, 0, 0.15);
    }
    
    .btn-success {
        background: var(--success);
        border-color: var(--success);
        transition: var(--transition);
    }
    .btn-success:hover {
        background: #059669;
        border-color: #059669;
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
    }
    .btn-success:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none;
    }
    
    .btn-outline-danger:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3);
    }
    
    .btn-secondary:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    }
    
    @media (max-width: 768px) {
        .swap-summary .summary-item {
            flex-direction: column;
            gap: 2px;
        }
        .info-grid {
            grid-template-columns: 1fr;
        }
        .action-card .d-flex {
            flex-direction: column;
            gap: 10px;
        }
        .action-card .d-flex .btn {
            width: 100%;
        }
        .action-card .d-flex .btn-group {
            display: flex;
            flex-direction: column;
            gap: 10px;
            width: 100%;
        }
    }
</style>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-check-circle me-2" style="color: var(--success);"></i>
                    <?php echo $pageTitle; ?>
                </h5>
                <small class="text-muted">Swap Request #<?php echo $id; ?></small>
            </div>
            <div class="card-body">
                <!-- Swap Summary -->
                <div class="swap-summary">
                    <h6 class="mb-3">
                        <i class="fas fa-info-circle me-2"></i>
                        Swap Request Summary
                    </h6>
                    
                    <div class="summary-item">
                        <span class="summary-label">
                            <i class="fas fa-user me-1"></i> Requester
                        </span>
                        <span class="summary-value">
                            <?php echo htmlspecialchars(($swapData['requester_first'] ?? '') . ' ' . ($swapData['requester_last'] ?? '')); ?>
                        </span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">
                            <i class="fas fa-user me-1"></i> Target Teacher
                        </span>
                        <span class="summary-value">
                            <?php echo htmlspecialchars(($swapData['target_first'] ?? '') . ' ' . ($swapData['target_last'] ?? '')); ?>
                        </span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">
                            <i class="fas fa-tasks me-1"></i> Duty Code
                        </span>
                        <span class="summary-value">
                            <code><?php echo htmlspecialchars($swapData['duty_code'] ?? 'N/A'); ?></code>
                        </span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">
                            <i class="fas fa-tag me-1"></i> Category
                        </span>
                        <span class="summary-value">
                            <span class="badge" style="background: <?php echo $swapData['category_color'] ?? '#6c757d'; ?>; color: #fff; padding: 4px 12px;">
                                <?php echo htmlspecialchars($swapData['category_name'] ?? 'N/A'); ?>
                            </span>
                        </span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">
                            <i class="fas fa-calendar-day me-1"></i> Requested Date
                        </span>
                        <span class="summary-value">
                            <?php echo formatDate($swapData['requested_date']); ?>
                        </span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">
                            <i class="fas fa-clock me-1"></i> Requested Time
                        </span>
                        <span class="summary-value">
                            <?php echo date('h:i A', strtotime($swapData['requested_start_time'])); ?> - 
                            <?php echo date('h:i A', strtotime($swapData['requested_end_time'])); ?>
                        </span>
                    </div>
                    <?php if ($swapData['reason']): ?>
                        <div class="summary-item">
                            <span class="summary-label">
                                <i class="fas fa-comment me-1"></i> Reason
                            </span>
                            <span class="summary-value text-muted" style="font-weight: 400; font-size: 0.85rem;">
                                <i class="fas fa-quote-left me-1" style="font-size: 0.7rem;"></i>
                                <?php echo nl2br(htmlspecialchars($swapData['reason'])); ?>
                            </span>
                        </div>
                    <?php endif; ?>
                    <div class="summary-item">
                        <span class="summary-label">
                            <i class="fas fa-info-circle me-1"></i> Current Status
                        </span>
                        <span class="summary-value">
                            <span class="swap-status-badge <?php echo str_replace('_', '-', $swapData['status']); ?>">
                                <i class="fas fa-clock me-1"></i>
                                <?php echo str_replace('_', ' ', ucfirst($swapData['status'])); ?>
                            </span>
                        </span>
                    </div>
                </div>
                
                <!-- Original vs Requested -->
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">
                            <i class="fas fa-calendar-alt me-1"></i> Original Date
                        </div>
                        <div class="info-value">
                            <?php echo formatDate($swapData['duty_date'] ?? ''); ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">
                            <i class="fas fa-calendar-plus me-1"></i> Requested Date
                        </div>
                        <div class="info-value text-success">
                            <i class="fas fa-arrow-right me-1"></i>
                            <?php echo formatDate($swapData['requested_date']); ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">
                            <i class="fas fa-clock me-1"></i> Original Time
                        </div>
                        <div class="info-value">
                            <?php echo date('h:i A', strtotime($swapData['start_time'] ?? '')); ?> - 
                            <?php echo date('h:i A', strtotime($swapData['end_time'] ?? '')); ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">
                            <i class="fas fa-clock me-1"></i> Requested Time
                        </div>
                        <div class="info-value text-success">
                            <i class="fas fa-arrow-right me-1"></i>
                            <?php echo date('h:i A', strtotime($swapData['requested_start_time'])); ?> - 
                            <?php echo date('h:i A', strtotime($swapData['requested_end_time'])); ?>
                        </div>
                    </div>
                </div>
                
                <!-- Admin Approval Form -->
                <div class="action-card">
                    <h6 class="mb-3">
                        <i class="fas fa-question-circle me-2" style="color: var(--success);"></i>
                        Confirm Approval
                    </h6>
                    
                    <p class="text-muted">
                        You are about to approve this swap request. The target teacher will be notified to accept or decline.
                    </p>
                    
                    <form method="POST" id="approveForm">
                        <?php echo CSRF::getTokenField(); ?>
                        
                        <div class="mb-3">
                            <label class="form-label">
                                <i class="fas fa-comment me-1"></i>
                                Admin Notes (Optional)
                            </label>
                            <textarea name="notes" class="form-control" rows="3" 
                                      placeholder="Add any notes about your approval..."></textarea>
                            <small class="text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                Your notes will be visible to both teachers.
                            </small>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center mt-4">
                            <a href="<?php echo SITE_URL; ?>/views/swaps/view.php?id=<?php echo $id; ?>" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-2"></i> Back
                            </a>
                            <div class="d-flex gap-2">
                                <a href="<?php echo SITE_URL; ?>/views/swaps/reject.php?id=<?php echo $id; ?>" 
                                   class="btn btn-outline-danger">
                                    <i class="fas fa-times me-2"></i> Reject Instead
                                </a>
                                <button type="submit" class="btn btn-success" id="approveBtn">
                                    <i class="fas fa-check me-2"></i> Approve Swap
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Help Card -->
        <div class="card mt-3">
            <div class="card-body">
                <h6 class="mb-2">
                    <i class="fas fa-lightbulb me-2" style="color: var(--accent);"></i>
                    What happens next?
                </h6>
                <ul class="text-muted small mb-0">
                    <li>After approval, the target teacher will be notified.</li>
                    <li>The target teacher can then accept or decline the swap.</li>
                    <li>If accepted, you can complete the swap to finalize it.</li>
                    <li>Both teachers will be notified of the final status.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('approveForm');
    const approveBtn = document.getElementById('approveBtn');
    
    // =============================================
    // Form Submission
    // =============================================
    form.addEventListener('submit', function(e) {
        // Prevent multiple submissions
        if (approveBtn.disabled) {
            e.preventDefault();
            return;
        }
        
        // Confirm with user
        if (!confirm('Are you sure you want to approve this swap request?')) {
            e.preventDefault();
            return;
        }
        
        // Disable button and show loading
        approveBtn.disabled = true;
        approveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Approving...';
    });
    
    // =============================================
    // Keyboard Shortcut: Ctrl+Enter to submit
    // =============================================
    document.querySelector('textarea[name="notes"]').addEventListener('keydown', function(e) {
        if (e.ctrlKey && e.key === 'Enter') {
            e.preventDefault();
            approveBtn.click();
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
    
    // =============================================
    // Console logging for debugging
    // =============================================
    console.log('📋 Swap Approve page loaded for request #<?php echo $id; ?>');
    console.log('👤 Admin: <?php echo htmlspecialchars($user['full_name']); ?>');
    console.log('📊 Current Status: <?php echo $swapData['status']; ?>');
});
</script>

<?php
// Include footer
require_once __DIR__ . '/../../includes/footer.php;
?>