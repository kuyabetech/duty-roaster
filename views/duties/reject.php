<?php
// =============================================
// Reject Duty
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
if (!$auth->isLoggedIn()) {
    redirect(SITE_URL . '/views/auth/login.php');
}

$user = $auth->getUser();
$role = $user['role'] ?? 'teacher';

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

// Check if user is the assigned teacher
$db = Database::getInstance();
$stmt = $db->prepare("SELECT id FROM teachers WHERE email = ?");
$stmt->execute([$user['email']]);
$teacher = $stmt->fetch();

if (!$teacher || $teacher['id'] != $dutyData['teacher_id']) {
    setFlashMessage('error', 'You are not authorized to reject this duty.');
    redirect(SITE_URL . '/views/duties/');
}

// Check if duty is pending
if ($dutyData['status'] !== DUTY_PENDING) {
    setFlashMessage('warning', 'This duty is already ' . $dutyData['status'] . '.');
    redirect(SITE_URL . '/views/duties/view.php?id=' . $id);
}

// Process rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::verifyToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('error', 'Invalid request. Please try again.');
        redirect(SITE_URL . '/views/duties/view.php?id=' . $id);
    }
    
    $reason = sanitizeInput($_POST['reason'] ?? '');
    
    if (empty($reason)) {
        setFlashMessage('error', 'Please provide a reason for rejecting the duty.');
        redirect(SITE_URL . '/views/duties/reject.php?id=' . $id);
    }
    
    if ($duty->reject($reason)) {
        // Create notification for admin
        Notification::sendToRole(
            'admin',
            'duty_rejected',
            'Duty Rejected',
            'Teacher ' . htmlspecialchars($user['full_name']) . ' has rejected duty: ' . $dutyData['duty_code'] . ' - Reason: ' . $reason,
            SITE_URL . '/views/duties/view.php?id=' . $id,
            'high'
        );
        
        setFlashMessage('success', 'Duty rejected successfully. Reason has been recorded.');
        redirect(SITE_URL . '/views/duties/view.php?id=' . $id);
    } else {
        setFlashMessage('error', 'Failed to reject duty. Please try again.');
        redirect(SITE_URL . '/views/duties/reject.php?id=' . $id);
    }
}

// Set page variables
$pageTitle = 'Reject Duty';
$pageIcon = 'fas fa-times-circle';
$breadcrumb = [
    ['label' => 'Dashboard', 'url' => SITE_URL . '/views/dashboard/' . ($role === 'teacher' ? 'teacher' : 'admin') . '.php'],
    ['label' => 'Duties', 'url' => SITE_URL . '/views/duties/'],
    ['label' => 'Reject Duty', 'active' => true]
];

// Common rejection reasons
$commonReasons = [
    'Schedule conflict - I have another duty at the same time',
    'Personal emergency',
    'Medical reasons',
    'Transportation issues',
    'Family commitments',
    'Already overcommitted',
    'Other (please specify)'
];

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
    
    @media (max-width: 768px) {
        .duty-summary .summary-item {
            flex-direction: column;
            gap: 2px;
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
                <!-- Duty Summary -->
                <div class="duty-summary">
                    <h6 class="mb-3"><i class="fas fa-info-circle me-2"></i>Duty Summary</h6>
                    
                    <div class="summary-item">
                        <span class="summary-label">Duty Code</span>
                        <span class="summary-value"><code><?php echo htmlspecialchars($dutyData['duty_code']); ?></code></span>
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
                            <span class="summary-value">
                                <i class="fas fa-map-marker-alt me-1"></i>
                                <?php echo htmlspecialchars($dutyData['location']); ?>
                            </span>
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
                    <div class="summary-item">
                        <span class="summary-label">Current Status</span>
                        <span class="summary-value">
                            <span class="duty-status-badge <?php echo $dutyData['status']; ?>">
                                <?php echo ucfirst($dutyData['status']); ?>
                            </span>
                        </span>
                    </div>
                </div>
                
                <!-- Rejection Form -->
                <div class="action-card">
                    <h6 class="mb-3">
                        <i class="fas fa-question-circle me-2" style="color: var(--danger);"></i>
                        Rejection Reason
                    </h6>
                    
                    <p class="text-muted">
                        Please provide a reason for rejecting this duty. This helps the administrator reassign the duty appropriately.
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
                                      placeholder="Please provide a detailed reason for rejecting this duty..." required></textarea>
                            <div class="invalid-feedback">Please provide a reason for rejecting.</div>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <a href="<?php echo SITE_URL; ?>/views/duties/view.php?id=<?php echo $id; ?>" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-2"></i> Back
                            </a>
                            <div>
                                <a href="<?php echo SITE_URL; ?>/views/duties/accept.php?id=<?php echo $id; ?>" 
                                   class="btn btn-outline-success me-2">
                                    <i class="fas fa-check me-2"></i> Accept Instead
                                </a>
                                <button type="submit" class="btn btn-danger" id="rejectBtn">
                                    <i class="fas fa-times me-2"></i> Reject Duty
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
    // Remove active class from all buttons
    document.querySelectorAll('.reason-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    
    // Set the reason text
    document.getElementById('reasonInput').value = reason;
}

document.getElementById('rejectBtn').addEventListener('click', function(e) {
    const reason = document.getElementById('reasonInput').value.trim();
    if (!reason) {
        e.preventDefault();
        document.getElementById('reasonInput').classList.add('is-invalid');
        alert('Please provide a reason for rejecting this duty.');
        return;
    }
    
    if (!confirm('Are you sure you want to reject this duty?')) {
        e.preventDefault();
        return;
    }
    
    this.disabled = true;
    this.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Rejecting...';
});

// Clear invalid on input
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