<?php
// =============================================
// Accept Duty - Complete Fixed Version
// =============================================

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

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
    setFlashMessage('error', 'You are not authorized to accept this duty.');
    redirect(SITE_URL . '/views/duties/');
}

// Check if duty is pending
if ($dutyData['status'] !== DUTY_PENDING) {
    setFlashMessage('warning', 'This duty is already ' . $dutyData['status'] . '.');
    redirect(SITE_URL . '/views/duties/view.php?id=' . $id);
}

$error = '';
$success = '';

// Process acceptance
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check CSRF token
    if (!isset($_POST['csrf_token']) || !CSRF::verifyToken($_POST['csrf_token'])) {
        $error = 'Invalid request. Please try again.';
    } else {
        $notes = sanitizeInput($_POST['notes'] ?? '');
        
        // Debug log
        error_log("Attempting to accept duty ID: " . $id . " by user: " . $user['email']);
        
        // Try to accept the duty
        try {
            $result = $duty->accept();
            
            if ($result) {
                // Add acceptance notes if provided
                if ($notes) {
                    $duty->update(['remarks' => 'Accepted: ' . $notes]);
                }
                
                // Create notification for admin
                try {
                    Notification::sendToRole(
                        'admin',
                        'duty_accepted',
                        'Duty Accepted',
                        'Teacher ' . htmlspecialchars($user['full_name']) . ' has accepted duty: ' . $dutyData['duty_code'],
                        SITE_URL . '/views/duties/view.php?id=' . $id,
                        'medium'
                    );
                } catch (Exception $e) {
                    error_log("Notification error: " . $e->getMessage());
                }
                
                setFlashMessage('success', 'Duty accepted successfully!');
                redirect(SITE_URL . '/views/duties/view.php?id=' . $id);
                exit;
            } else {
                $error = 'Failed to accept duty. Please try again.';
                error_log("Duty accept failed for ID: " . $id);
            }
        } catch (Exception $e) {
            $error = 'System error: ' . $e->getMessage();
            error_log("Duty accept exception: " . $e->getMessage() . " in " . $e->getFile() . " line " . $e->getLine());
        }
    }
}

// Set page variables
$pageTitle = 'Accept Duty';
$pageIcon = 'fas fa-check-circle';
$breadcrumb = [
    ['label' => 'Dashboard', 'url' => SITE_URL . '/views/dashboard/' . ($role === 'teacher' ? 'teacher' : 'admin') . '.php'],
    ['label' => 'Duties', 'url' => SITE_URL . '/views/duties/'],
    ['label' => 'Accept Duty', 'active' => true]
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
        border-left: 4px solid var(--success);
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
        border-color: var(--accent);
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
    
    @media (max-width: 768px) {
        .duty-summary .summary-item {
            flex-direction: column;
            gap: 2px;
        }
    }
</style>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="fas fa-check-circle me-2"></i> <?php echo $success; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-check-circle me-2" style="color: var(--success);"></i>
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
                
                <!-- Confirmation Form -->
                <div class="action-card">
                    <h6 class="mb-3">
                        <i class="fas fa-question-circle me-2" style="color: var(--accent);"></i>
                        Confirm Acceptance
                    </h6>
                    
                    <p class="text-muted">
                        You are about to accept this duty. By accepting, you agree to fulfill this duty at the specified time and location.
                    </p>
                    
                    <form method="POST" id="acceptForm">
                        <?php echo CSRF::getTokenField(); ?>
                        
                        <div class="mb-3">
                            <label class="form-label">Additional Notes (Optional)</label>
                            <textarea name="notes" class="form-control" rows="3" 
                                      placeholder="Add any notes about your acceptance..."></textarea>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <a href="<?php echo SITE_URL; ?>/views/duties/view.php?id=<?php echo $id; ?>" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-2"></i> Back
                            </a>
                            <div>
                                <a href="<?php echo SITE_URL; ?>/views/duties/reject.php?id=<?php echo $id; ?>" 
                                   class="btn btn-outline-danger me-2">
                                    <i class="fas fa-times me-2"></i> Reject Instead
                                </a>
                                <button type="submit" class="btn btn-success" id="acceptBtn">
                                    <i class="fas fa-check me-2"></i> Accept Duty
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
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('acceptForm');
    const submitBtn = document.getElementById('acceptBtn');
    
    form.addEventListener('submit', function(e) {
        // Prevent multiple submissions
        if (submitBtn.disabled) {
            e.preventDefault();
            return;
        }
        
        // Confirm with user
        if (!confirm('Are you sure you want to accept this duty?')) {
            e.preventDefault();
            return;
        }
        
        // Disable button and show loading
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Accepting...';
        
        // Form will submit normally
    });
    
    // Re-enable button if form submission fails (back button)
    window.addEventListener('pageshow', function() {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-check me-2"></i> Accept Duty';
    });
});
</script>

<?php
// Include footer
require_once __DIR__ . '/../../includes/footer.php';
?>