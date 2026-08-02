<?php
// =============================================
// Cancel Duty
// =============================================

// Load configuration
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../models/Duty.php';
require_once __DIR__ . '/../../models/Notification.php';
require_once __DIR__ . '/../../models/Swap.php';

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

// Check if duty can be cancelled (not completed, not already cancelled)
if ($dutyData['status'] === DUTY_COMPLETED) {
    setFlashMessage('warning', 'Completed duties cannot be cancelled.');
    redirect(SITE_URL . '/views/duties/view.php?id=' . $id);
}

if ($dutyData['status'] === DUTY_CANCELLED) {
    setFlashMessage('warning', 'This duty is already cancelled.');
    redirect(SITE_URL . '/views/duties/view.php?id=' . $id);
}

// Set page variables
$pageTitle = 'Cancel Duty';
$pageIcon = 'fas fa-ban';
$breadcrumb = [
    ['label' => 'Dashboard', 'url' => SITE_URL . '/views/dashboard/admin.php'],
    ['label' => 'Duties', 'url' => SITE_URL . '/views/duties/'],
    ['label' => 'Cancel Duty', 'active' => true]
];

$error = '';
$success = '';

// Process cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::verifyToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('error', 'Invalid request. Please try again.');
        redirect(SITE_URL . '/views/duties/cancel.php?id=' . $id);
    }
    
    $reason = sanitizeInput($_POST['reason'] ?? '');
    $notify_teacher = isset($_POST['notify_teacher']);
    
    if (empty($reason)) {
        setFlashMessage('error', 'Please provide a reason for cancelling this duty.');
        redirect(SITE_URL . '/views/duties/cancel.php?id=' . $id);
    }
    
    try {
        // Check for pending swap requests
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM duty_swaps WHERE duty_id = ? AND status IN (?, ?, ?)");
        $stmt->execute([$id, SWAP_PENDING, SWAP_APPROVED_BY_ADMIN, SWAP_APPROVED_BY_TEACHER]);
        $swapCount = $stmt->fetch()['count'] ?? 0;
        
        if ($swapCount > 0) {
            // Cancel all pending swap requests
            $stmt = $db->prepare("UPDATE duty_swaps SET status = ?, cancelled_at = NOW() WHERE duty_id = ? AND status IN (?, ?, ?)");
            $stmt->execute([SWAP_CANCELLED, $id, SWAP_PENDING, SWAP_APPROVED_BY_ADMIN, SWAP_APPROVED_BY_TEACHER]);
            
            // Notify swap participants
            $stmt = $db->prepare("
                SELECT requester_teacher_id, target_teacher_id FROM duty_swaps 
                WHERE duty_id = ? AND status = ?
            ");
            $stmt->execute([$id, SWAP_CANCELLED]);
            $swaps = $stmt->fetchAll();
            
            foreach ($swaps as $swap) {
                Notification::send(
                    $swap['requester_teacher_id'],
                    'swap_cancelled_duty',
                    'Swap Cancelled - Duty Cancelled',
                    'Your swap request has been cancelled because the duty was cancelled by admin.',
                    null,
                    'high'
                );
                Notification::send(
                    $swap['target_teacher_id'],
                    'swap_cancelled_duty',
                    'Swap Cancelled - Duty Cancelled',
                    'Your swap request has been cancelled because the duty was cancelled by admin.',
                    null,
                    'high'
                );
            }
        }
        
        // Cancel the duty
        if ($duty->cancel($reason)) {
            // Notify teacher if requested
            if ($notify_teacher && $dutyData['teacher_id']) {
                Notification::send(
                    $dutyData['teacher_id'],
                    'duty_cancelled',
                    'Duty Cancelled',
                    'Your duty ' . $dutyData['duty_code'] . ' on ' . formatDate($dutyData['duty_date']) . 
                    ' has been cancelled. Reason: ' . $reason,
                    SITE_URL . '/views/duties/view.php?id=' . $id,
                    'high'
                );
            }
            
            setFlashMessage('success', 'Duty cancelled successfully!');
            redirect(SITE_URL . '/views/duties/view.php?id=' . $id);
        } else {
            setFlashMessage('error', 'Failed to cancel duty. Please try again.');
            redirect(SITE_URL . '/views/duties/cancel.php?id=' . $id);
        }
    } catch (Exception $e) {
        setFlashMessage('error', 'System error: ' . $e->getMessage());
        redirect(SITE_URL . '/views/duties/cancel.php?id=' . $id);
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
        border-left: 4px solid var(--warning);
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
        border-color: var(--warning);
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
    
    .reason-btn {
        padding: 6px 12px;
        border-radius: var(--radius);
        border: 1px solid var(--border-color);
        background: var(--bg-white);
        transition: var(--transition);
        cursor: pointer;
        font-size: 0.85rem;
        margin: 3px;
    }
    .reason-btn:hover {
        border-color: var(--accent);
        background: var(--bg-light);
    }
    .reason-btn.active {
        border-color: var(--accent);
        background: var(--accent);
        color: var(--primary);
    }
    
    .btn-warning {
        background: var(--warning);
        border-color: var(--warning);
        color: var(--text-dark);
        transition: var(--transition);
    }
    .btn-warning:hover {
        background: #d97706;
        border-color: #d97706;
        color: #fff;
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(245, 158, 11, 0.3);
    }
    .btn-warning:disabled {
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
                <h5>⚠️ Cancelling this duty</h5>
                <p>This action will cancel the duty and notify the assigned teacher. Any pending swap requests will also be cancelled.</p>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-ban me-2" style="color: var(--warning);"></i>
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
                
                <!-- Cancellation Form -->
                <div class="action-card">
                    <h6 class="mb-3">
                        <i class="fas fa-question-circle me-2" style="color: var(--warning);"></i>
                        Reason for Cancellation
                    </h6>
                    
                    <form method="POST" id="cancelForm">
                        <?php echo CSRF::getTokenField(); ?>
                        
                        <!-- Quick Reasons -->
                        <div class="mb-3">
                            <label class="form-label">Common Reasons</label>
                            <div class="d-flex flex-wrap">
                                <button type="button" class="reason-btn" onclick="setReason(this, 'Duty no longer required')">
                                    No longer required
                                </button>
                                <button type="button" class="reason-btn" onclick="setReason(this, 'Teacher unavailable')">
                                    Teacher unavailable
                                </button>
                                <button type="button" class="reason-btn" onclick="setReason(this, 'Schedule conflict')">
                                    Schedule conflict
                                </button>
                                <button type="button" class="reason-btn" onclick="setReason(this, 'Duplicate duty created')">
                                    Duplicate duty
                                </button>
                                <button type="button" class="reason-btn" onclick="setReason(this, 'Event cancelled')">
                                    Event cancelled
                                </button>
                                <button type="button" class="reason-btn" onclick="setReason(this, 'Other')">
                                    Other
                                </button>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Detailed Reason <span class="required-star">*</span></label>
                            <textarea name="reason" id="reasonInput" class="form-control" rows="4" 
                                      placeholder="Please provide a detailed reason for cancelling this duty..." required></textarea>
                            <div class="invalid-feedback">Please provide a reason for cancellation.</div>
                            <small class="text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                This reason will be shared with the assigned teacher.
                            </small>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="notify_teacher" name="notify_teacher" checked>
                                <label class="form-check-label" for="notify_teacher">
                                    <i class="fas fa-bell me-1"></i>
                                    Notify assigned teacher
                                </label>
                            </div>
                            <small class="text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                The teacher will receive a notification with the cancellation reason.
                            </small>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center mt-4">
                            <a href="<?php echo SITE_URL; ?>/views/duties/view.php?id=<?php echo $id; ?>" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-2"></i> Back
                            </a>
                            <button type="submit" class="btn btn-warning" id="cancelBtn">
                                <i class="fas fa-ban me-2"></i> Cancel Duty
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
    const form = document.getElementById('cancelForm');
    const cancelBtn = document.getElementById('cancelBtn');
    const reasonInput = document.getElementById('reasonInput');
    
    // =============================================
    // Set Reason from Quick Buttons
    // =============================================
    window.setReason = function(btn, reason) {
        document.querySelectorAll('.reason-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        reasonInput.value = reason;
        reasonInput.classList.remove('is-invalid');
    };
    
    // =============================================
    // Form Validation
    // =============================================
    form.addEventListener('submit', function(e) {
        const reason = reasonInput.value.trim();
        
        if (!reason) {
            e.preventDefault();
            reasonInput.classList.add('is-invalid');
            reasonInput.focus();
            alert('Please provide a reason for cancelling this duty.');
            return;
        }
        
        if (!confirm('Are you sure you want to cancel this duty?')) {
            e.preventDefault();
            return;
        }
        
        // Disable button and show loading
        cancelBtn.disabled = true;
        cancelBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Cancelling...';
    });
    
    // =============================================
    // Real-time validation
    // =============================================
    reasonInput.addEventListener('input', function() {
        if (this.value.trim()) {
            this.classList.remove('is-invalid');
        } else {
            this.classList.add('is-invalid');
        }
    });
    
    // =============================================
    // Keyboard shortcut: Ctrl+Enter to submit
    // =============================================
    reasonInput.addEventListener('keydown', function(e) {
        if (e.ctrlKey && e.key === 'Enter') {
            e.preventDefault();
            cancelBtn.click();
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
    
    console.log('🚫 Cancel Duty page loaded for duty: <?php echo htmlspecialchars($dutyData['duty_code']); ?>');
});
</script>

<?php
// Include footer
require_once __DIR__ . '/../../includes/footer.php';
?>