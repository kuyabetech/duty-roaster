<?php
// =============================================
// Admin Rejects Swap Request
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
    setFlashMessage('warning', 'This swap request has already been processed.');
    redirect(SITE_URL . '/views/swaps/view.php?id=' . $id);
}

// Process rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::verifyToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('error', 'Invalid request. Please try again.');
        redirect(SITE_URL . '/views/swaps/reject.php?id=' . $id);
    }
    
    $reason = sanitizeInput($_POST['reason'] ?? '');
    
    if (empty($reason)) {
        setFlashMessage('error', 'Please provide a reason for rejecting the swap request.');
        redirect(SITE_URL . '/views/swaps/reject.php?id=' . $id);
    }
    
    try {
        // Use rejectByTeacher to reject (it's the same method)
        if ($swap->rejectByTeacher()) {
            // Add reason to notes
            $db = Database::getInstance();
            $db->prepare("UPDATE duty_swaps SET reason = CONCAT(reason, '\n\nAdmin rejected: ', ?) WHERE id = ?")
               ->execute([$reason, $id]);
            
            // Notify requester
            Notification::send(
                $swapData['requester_teacher_id'],
                'swap_rejected_admin',
                'Swap Request Rejected',
                'Your swap request has been rejected by admin. Reason: ' . $reason,
                null,
                'high'
            );
            
            // Notify target teacher
            Notification::send(
                $swapData['target_teacher_id'],
                'swap_rejected_admin',
                'Swap Request Rejected',
                'A swap request for your duty has been rejected by admin.',
                null,
                'medium'
            );
            
            setFlashMessage('success', 'Swap request rejected successfully.');
            redirect(SITE_URL . '/views/swaps/view.php?id=' . $id);
        } else {
            setFlashMessage('error', 'Failed to reject swap request. Please try again.');
            redirect(SITE_URL . '/views/swaps/reject.php?id=' . $id);
        }
    } catch (Exception $e) {
        setFlashMessage('error', 'System error: ' . $e->getMessage());
        redirect(SITE_URL . '/views/swaps/reject.php?id=' . $id);
    }
}

// Set page variables
$pageTitle = 'Reject Swap Request';
$pageIcon = 'fas fa-times-circle';
$breadcrumb = [
    ['label' => 'Dashboard', 'url' => SITE_URL . '/views/dashboard/admin.php'],
    ['label' => 'Swap Requests', 'url' => SITE_URL . '/views/swaps/'],
    ['label' => 'Reject Swap', 'active' => true]
];

// Common rejection reasons
$commonReasons = [
    'Policy violation',
    'Invalid request',
    'Teacher already has a duty at that time',
    'Insufficient notice period',
    'No valid reason provided',
    'Department policy does not allow',
    'Other (please specify)'
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
        border-left: 4px solid var(--danger);
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
    }
    .swap-summary .summary-value {
        font-weight: 600;
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
    
    .swap-status-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
    }
    .swap-status-badge.pending { background: #fff3cd; color: #856404; }
    .swap-status-badge.rejected_by_teacher { background: #f8d7da; color: #721c24; }
    
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
    }
    .info-grid .info-item .info-label {
        font-size: 0.65rem;
        text-transform: uppercase;
        color: var(--text-muted);
        letter-spacing: 0.05em;
    }
    .info-grid .info-item .info-value {
        font-weight: 500;
        font-size: 0.95rem;
    }
    
    @media (max-width: 768px) {
        .swap-summary .summary-item {
            flex-direction: column;
            gap: 2px;
        }
        .info-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-times-circle me-2" style="color: var(--danger);"></i>
                    <?php echo $pageTitle; ?>
                </h5>
            </div>
            <div class="card-body">
                <!-- Swap Summary -->
                <div class="swap-summary">
                    <h6 class="mb-3"><i class="fas fa-info-circle me-2"></i>Swap Request Summary</h6>
                    
                    <div class="summary-item">
                        <span class="summary-label">Requester</span>
                        <span class="summary-value">
                            <?php echo htmlspecialchars(($swapData['requester_first'] ?? '') . ' ' . ($swapData['requester_last'] ?? '')); ?>
                        </span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Target Teacher</span>
                        <span class="summary-value">
                            <?php echo htmlspecialchars(($swapData['target_first'] ?? '') . ' ' . ($swapData['target_last'] ?? '')); ?>
                        </span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Duty Code</span>
                        <span class="summary-value">
                            <code><?php echo htmlspecialchars($swapData['duty_code'] ?? 'N/A'); ?></code>
                        </span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Category</span>
                        <span class="summary-value">
                            <span class="badge" style="background: <?php echo $swapData['category_color'] ?? '#6c757d'; ?>; color: #fff;">
                                <?php echo htmlspecialchars($swapData['category_name'] ?? 'N/A'); ?>
                            </span>
                        </span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Requested Date</span>
                        <span class="summary-value">
                            <?php echo formatDate($swapData['requested_date']); ?>
                        </span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Requested Time</span>
                        <span class="summary-value">
                            <?php echo date('h:i A', strtotime($swapData['requested_start_time'])); ?> - 
                            <?php echo date('h:i A', strtotime($swapData['requested_end_time'])); ?>
                        </span>
                    </div>
                    <?php if ($swapData['reason']): ?>
                        <div class="summary-item">
                            <span class="summary-label">Reason</span>
                            <span class="summary-value text-muted" style="font-weight: 400;">
                                <?php echo nl2br(htmlspecialchars($swapData['reason'])); ?>
                            </span>
                        </div>
                    <?php endif; ?>
                    <div class="summary-item">
                        <span class="summary-label">Current Status</span>
                        <span class="summary-value">
                            <span class="swap-status-badge <?php echo str_replace('_', '-', $swapData['status']); ?>">
                                <?php echo str_replace('_', ' ', ucfirst($swapData['status'])); ?>
                            </span>
                        </span>
                    </div>
                </div>
                
                <!-- Original vs Requested -->
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Original Date</div>
                        <div class="info-value">
                            <?php echo formatDate($swapData['duty_date'] ?? ''); ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Requested Date</div>
                        <div class="info-value">
                            <?php echo formatDate($swapData['requested_date']); ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Original Time</div>
                        <div class="info-value">
                            <?php echo date('h:i A', strtotime($swapData['start_time'] ?? '')); ?> - 
                            <?php echo date('h:i A', strtotime($swapData['end_time'] ?? '')); ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Requested Time</div>
                        <div class="info-value">
                            <?php echo date('h:i A', strtotime($swapData['requested_start_time'])); ?> - 
                            <?php echo date('h:i A', strtotime($swapData['requested_end_time'])); ?>
                        </div>
                    </div>
                </div>
                
                <!-- Admin Rejection Form -->
                <div class="action-card">
                    <h6 class="mb-3">
                        <i class="fas fa-question-circle me-2" style="color: var(--danger);"></i>
                        Reject Swap Request
                    </h6>
                    
                    <p class="text-muted">
                        Please provide a reason for rejecting this swap request. This helps the requester understand the decision.
                    </p>
                    
                    <form method="POST" id="rejectForm">
                        <?php echo CSRF::getTokenField(); ?>
                        
                        <!-- Quick Reasons -->
                        <div class="mb-3">
                            <label class="form-label">Common Reasons</label>
                            <div class="d-flex flex-wrap">
                                <?php foreach ($commonReasons as $reason): ?>
                                    <button type="button" class="reason-btn" onclick="setReason(this, '<?php echo addslashes($reason); ?>')">
                                        <?php echo htmlspecialchars($reason); ?>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Detailed Reason <span class="required-star">*</span></label>
                            <textarea name="reason" id="reasonInput" class="form-control" rows="4" 
                                      placeholder="Please provide a detailed reason for rejecting this swap request..." required></textarea>
                            <div class="invalid-feedback">Please provide a reason for rejecting.</div>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <a href="<?php echo SITE_URL; ?>/views/swaps/view.php?id=<?php echo $id; ?>" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-2"></i> Back
                            </a>
                            <div>
                                <a href="<?php echo SITE_URL; ?>/views/swaps/approve.php?id=<?php echo $id; ?>" 
                                   class="btn btn-outline-success me-2">
                                    <i class="fas fa-check me-2"></i> Approve Instead
                                </a>
                                <button type="submit" class="btn btn-danger" id="rejectBtn">
                                    <i class="fas fa-times me-2"></i> Reject Swap
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function setReason(btn, reason) {
    document.querySelectorAll('.reason-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('reasonInput').value = reason;
}

document.getElementById('rejectBtn').addEventListener('click', function(e) {
    const reason = document.getElementById('reasonInput').value.trim();
    if (!reason) {
        e.preventDefault();
        document.getElementById('reasonInput').classList.add('is-invalid');
        alert('Please provide a reason for rejecting this swap request.');
        return;
    }
    
    if (!confirm('Are you sure you want to reject this swap request?')) {
        e.preventDefault();
        return;
    }
    
    this.disabled = true;
    this.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Rejecting...';
});

document.getElementById('reasonInput').addEventListener('input', function() {
    if (this.value.trim()) {
        this.classList.remove('is-invalid');
    }
});
</script>

<?php
// Include footer
require_once __DIR__ . '/../../includes/footer.php';
?>