<?php
// =============================================
// Edit Duty
// =============================================

// Load configuration
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../models/Duty.php';
require_once __DIR__ . '/../../models/Teacher.php';
require_once __DIR__ . '/../../models/DutyCategory.php';
require_once __DIR__ . '/../../models/Department.php';

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

// Check if duty can be edited (only pending or accepted)
if (!in_array($dutyData['status'], [DUTY_PENDING, DUTY_ACCEPTED])) {
    setFlashMessage('warning', 'This duty cannot be edited because it is ' . $dutyData['status'] . '.');
    redirect(SITE_URL . '/views/duties/view.php?id=' . $id);
}

// Set page variables
$pageTitle = 'Edit Duty';
$pageIcon = 'fas fa-edit';
$breadcrumb = [
    ['label' => 'Dashboard', 'url' => SITE_URL . '/views/dashboard/admin.php'],
    ['label' => 'Duties', 'url' => SITE_URL . '/views/duties/'],
    ['label' => 'Edit Duty', 'active' => true]
];

// Get data for dropdowns
$teachers = Teacher::all(['status' => STATUS_ACTIVE]);
$categories = DutyCategory::getActive();
$departments = Department::all(['status' => STATUS_ACTIVE]);

$error = '';
$success = '';

// Form data
$formData = [
    'teacher_id' => $dutyData['teacher_id'] ?? '',
    'category_id' => $dutyData['category_id'] ?? '',
    'class_id' => $dutyData['class_id'] ?? '',
    'duty_date' => $dutyData['duty_date'] ?? '',
    'start_time' => $dutyData['start_time'] ?? '',
    'end_time' => $dutyData['end_time'] ?? '',
    'location' => $dutyData['location'] ?? '',
    'priority' => $dutyData['priority'] ?? PRIORITY_NORMAL,
    'remarks' => $dutyData['remarks'] ?? ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::verifyToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $formData = [
            'teacher_id' => (int)($_POST['teacher_id'] ?? 0),
            'category_id' => (int)($_POST['category_id'] ?? 0),
            'class_id' => !empty($_POST['class_id']) ? (int)$_POST['class_id'] : null,
            'duty_date' => $_POST['duty_date'] ?? '',
            'start_time' => $_POST['start_time'] ?? '',
            'end_time' => $_POST['end_time'] ?? '',
            'location' => sanitizeInput($_POST['location'] ?? ''),
            'priority' => sanitizeInput($_POST['priority'] ?? PRIORITY_NORMAL),
            'remarks' => sanitizeInput($_POST['remarks'] ?? '')
        ];
        
        // Validate
        $errors = [];
        if (empty($formData['teacher_id'])) $errors[] = 'Please select a teacher.';
        if (empty($formData['category_id'])) $errors[] = 'Please select a category.';
        if (empty($formData['duty_date'])) $errors[] = 'Please select a date.';
        if (empty($formData['start_time'])) $errors[] = 'Please select a start time.';
        if (empty($formData['end_time'])) $errors[] = 'Please select an end time.';
        
        // Check for conflicts (excluding current duty)
        if (empty($errors)) {
            if (Duty::checkConflicts($formData['teacher_id'], $formData['duty_date'], 
                                      $formData['start_time'], $formData['end_time'], $id)) {
                $errors[] = 'Teacher has a conflicting duty at this time.';
            }
        }
        
        // Validate time
        if (empty($errors) && $formData['start_time'] >= $formData['end_time']) {
            $errors[] = 'End time must be after start time.';
        }
        
        if (empty($errors)) {
            if ($duty->update($formData)) {
                setFlashMessage('success', 'Duty updated successfully!');
                redirect(SITE_URL . '/views/duties/view.php?id=' . $id);
            } else {
                $error = 'Failed to update duty. Please try again.';
            }
        } else {
            $error = implode('<br>', $errors);
        }
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
        border-left: 4px solid var(--accent);
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
    
    .form-section {
        background: var(--bg-light);
        border-radius: var(--radius);
        padding: 20px;
        margin-bottom: 25px;
        border: 1px solid var(--border-light);
    }
    .form-section .section-title {
        font-weight: 600;
        font-size: 0.95rem;
        color: var(--text-dark);
        margin-bottom: 15px;
        padding-bottom: 10px;
        border-bottom: 2px solid var(--border-color);
    }
    .form-section .section-title i {
        color: var(--accent);
        margin-right: 8px;
    }
    
    .required-star {
        color: var(--danger);
        margin-left: 2px;
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
        .form-section {
            padding: 15px;
        }
    }
</style>

<div class="row justify-content-center">
    <div class="col-lg-10">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-edit me-2" style="color: var(--accent);"></i>
                    <?php echo $pageTitle; ?>
                </h5>
                <small class="text-muted">Duty: <?php echo htmlspecialchars($dutyData['duty_code']); ?></small>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Current Duty Summary -->
                <div class="duty-summary">
                    <h6 class="mb-3"><i class="fas fa-info-circle me-2"></i>Current Duty Information</h6>
                    
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
                        <span class="summary-label">Teacher</span>
                        <span class="summary-value">
                            <?php echo htmlspecialchars(($dutyData['first_name'] ?? '') . ' ' . ($dutyData['last_name'] ?? '')); ?>
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
                
                <!-- Edit Form -->
                <form method="POST" id="editForm">
                    <?php echo CSRF::getTokenField(); ?>
                    
                    <div class="form-section">
                        <div class="section-title">
                            <i class="fas fa-user me-2"></i> Assignment Details
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Teacher <span class="required-star">*</span></label>
                                <select name="teacher_id" class="form-control" required>
                                    <option value="">Select Teacher...</option>
                                    <?php foreach ($teachers as $teacher): ?>
                                        <option value="<?php echo $teacher['id']; ?>" 
                                                <?php echo $formData['teacher_id'] == $teacher['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?>
                                            (<?php echo htmlspecialchars($teacher['teacher_id']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Category <span class="required-star">*</span></label>
                                <select name="category_id" class="form-control" required>
                                    <option value="">Select Category...</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo $cat['id']; ?>" 
                                                <?php echo $formData['category_id'] == $cat['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cat['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Date <span class="required-star">*</span></label>
                                <input type="date" name="duty_date" class="form-control" 
                                       value="<?php echo htmlspecialchars($formData['duty_date']); ?>" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Start Time <span class="required-star">*</span></label>
                                <input type="time" name="start_time" class="form-control" 
                                       value="<?php echo htmlspecialchars($formData['start_time']); ?>" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">End Time <span class="required-star">*</span></label>
                                <input type="time" name="end_time" class="form-control" 
                                       value="<?php echo htmlspecialchars($formData['end_time']); ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <div class="section-title">
                            <i class="fas fa-info-circle me-2"></i> Additional Details
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Location</label>
                                <input type="text" name="location" class="form-control" 
                                       value="<?php echo htmlspecialchars($formData['location']); ?>" 
                                       placeholder="e.g., Main Hall, Room 201">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Priority</label>
                                <select name="priority" class="form-control">
                                    <option value="<?php echo PRIORITY_LOW; ?>" <?php echo $formData['priority'] === PRIORITY_LOW ? 'selected' : ''; ?>>
                                        Low
                                    </option>
                                    <option value="<?php echo PRIORITY_NORMAL; ?>" <?php echo $formData['priority'] === PRIORITY_NORMAL ? 'selected' : ''; ?>>
                                        Normal
                                    </option>
                                    <option value="<?php echo PRIORITY_HIGH; ?>" <?php echo $formData['priority'] === PRIORITY_HIGH ? 'selected' : ''; ?>>
                                        High
                                    </option>
                                    <option value="<?php echo PRIORITY_URGENT; ?>" <?php echo $formData['priority'] === PRIORITY_URGENT ? 'selected' : ''; ?>>
                                        Urgent
                                    </option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Remarks</label>
                            <textarea name="remarks" class="form-control" rows="3" 
                                      placeholder="Any additional notes..."><?php echo htmlspecialchars($formData['remarks']); ?></textarea>
                        </div>
                    </div>
                    
                    <!-- Actions -->
                    <div class="d-flex justify-content-between align-items-center mt-4">
                        <a href="<?php echo SITE_URL; ?>/views/duties/view.php?id=<?php echo $id; ?>" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-2"></i> Cancel
                        </a>
                        <div>
                            <button type="reset" class="btn btn-outline-secondary me-2">
                                <i class="fas fa-undo me-1"></i> Reset
                            </button>
                            <button type="submit" class="btn btn-accent" id="submitBtn">
                                <i class="fas fa-save me-2"></i> Update Duty
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('editForm');
    const submitBtn = document.getElementById('submitBtn');
    
    // =============================================
    // Form Validation
    // =============================================
    form.addEventListener('submit', function(e) {
        const requiredFields = this.querySelectorAll('[required]');
        let isValid = true;
        
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                field.classList.add('is-invalid');
                isValid = false;
            } else {
                field.classList.remove('is-invalid');
                field.classList.add('is-valid');
            }
        });
        
        // Validate time
        const startTime = document.querySelector('input[name="start_time"]');
        const endTime = document.querySelector('input[name="end_time"]');
        if (startTime.value && endTime.value && startTime.value >= endTime.value) {
            endTime.classList.add('is-invalid');
            alert('End time must be after start time.');
            isValid = false;
        }
        
        if (!isValid) {
            e.preventDefault();
            const firstError = this.querySelector('.is-invalid');
            if (firstError) {
                firstError.focus();
                firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
            return;
        }
        
        // Confirm update
        if (!confirm('Are you sure you want to update this duty?')) {
            e.preventDefault();
            return;
        }
        
        // Disable submit button and show loading
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Updating...';
    });
    
    // =============================================
    // Real-time validation
    // =============================================
    document.querySelectorAll('[required]').forEach(field => {
        field.addEventListener('input', function() {
            if (this.value.trim()) {
                this.classList.remove('is-invalid');
                this.classList.add('is-valid');
            } else {
                this.classList.remove('is-valid');
                this.classList.add('is-invalid');
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
    
    console.log('📋 Edit Duty page loaded for duty: <?php echo htmlspecialchars($dutyData['duty_code']); ?>');
});
</script>

<?php
// Include footer
require_once __DIR__ . '/../../includes/footer.php';
?>