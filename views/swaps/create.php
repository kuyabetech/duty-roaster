<?php
// =============================================
// Create Swap Request
// =============================================

// Load configuration
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../models/Swap.php';
require_once __DIR__ . '/../../models/Duty.php';
require_once __DIR__ . '/../../models/Teacher.php';
require_once __DIR__ . '/../../models/Notification.php';

// Initialize auth
$auth = new Auth();
if (!$auth->isLoggedIn()) {
    redirect(SITE_URL . '/views/auth/login.php');
}

$user = $auth->getUser();
$role = $user['role'] ?? 'teacher';

// Set page variables
$pageTitle = 'Create Swap Request';
$pageIcon = 'fas fa-exchange-alt';
$breadcrumb = [
    ['label' => 'Dashboard', 'url' => SITE_URL . '/views/dashboard/' . ($role === 'teacher' ? 'teacher' : 'admin') . '.php'],
    ['label' => 'Swap Requests', 'url' => SITE_URL . '/views/swaps/'],
    ['label' => 'Create Swap', 'active' => true]
];

// Get teacher ID
$db = Database::getInstance();
$stmt = $db->prepare("SELECT id FROM teachers WHERE email = ?");
$stmt->execute([$user['email']]);
$teacher = $stmt->fetch();
$teacherId = $teacher['id'] ?? null;

if (!$teacherId) {
    setFlashMessage('error', 'Teacher record not found. Please contact administrator.');
    redirect(SITE_URL . '/views/swaps/');
}

// Get teacher's duties (pending or accepted)
$duties = Duty::all([
    'teacher_id' => $teacherId,
    'status' => [DUTY_PENDING, DUTY_ACCEPTED]
]);

// Get other teachers for swap target (exclude self)
$teachers = Teacher::all(['status' => STATUS_ACTIVE]);

// Filter out self
$teachers = array_filter($teachers, function($t) use ($teacherId) {
    return $t['id'] != $teacherId;
});

$error = '';
$success = '';
$formData = [
    'duty_id' => '',
    'target_teacher_id' => '',
    'requested_date' => '',
    'requested_start_time' => '',
    'requested_end_time' => '',
    'reason' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::verifyToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        // Get and sanitize form data
        $formData['duty_id'] = $_POST['duty_id'] ?? '';
        $formData['target_teacher_id'] = $_POST['target_teacher_id'] ?? '';
        $formData['requested_date'] = $_POST['requested_date'] ?? '';
        $formData['requested_start_time'] = $_POST['requested_start_time'] ?? '';
        $formData['requested_end_time'] = $_POST['requested_end_time'] ?? '';
        $formData['reason'] = sanitizeInput($_POST['reason'] ?? '');
        
        // Validate
        $errors = [];
        
        if (empty($formData['duty_id'])) {
            $errors[] = 'Please select a duty to swap.';
        }
        if (empty($formData['target_teacher_id'])) {
            $errors[] = 'Please select a target teacher.';
        }
        if (empty($formData['requested_date'])) {
            $errors[] = 'Please select a requested date.';
        }
        if (empty($formData['requested_start_time'])) {
            $errors[] = 'Please select a start time.';
        }
        if (empty($formData['requested_end_time'])) {
            $errors[] = 'Please select an end time.';
        }
        
        // Check if target teacher is available at requested time
        if (empty($errors)) {
            $targetTeacher = new Teacher($formData['target_teacher_id']);
            if ($targetTeacher->isOnLeave($formData['requested_date'])) {
                $errors[] = 'Target teacher is on leave on the requested date.';
            }
            
            // Check for conflicts with target teacher
            if (Duty::checkConflicts($formData['target_teacher_id'], $formData['requested_date'], 
                                      $formData['requested_start_time'], $formData['requested_end_time'])) {
                $errors[] = 'Target teacher has a conflicting duty at the requested time.';
            }
            
            // Check if date is in the future
            if (strtotime($formData['requested_date']) < strtotime(date('Y-m-d'))) {
                $errors[] = 'Requested date must be in the future.';
            }
        }
        
        if (empty($errors)) {
            $swap = new Swap();
            $data = $formData;
            $data['requester_teacher_id'] = $teacherId;
            $result = $swap->create($data);
            
            if ($result) {
                setFlashMessage('success', 'Swap request created successfully!');
                redirect(SITE_URL . '/views/swaps/');
            } else {
                $error = 'Failed to create swap request. Please try again.';
            }
        } else {
            $error = implode('<br>', $errors);
        }
    }
}

// Get duty details for AJAX preview
$selectedDuty = null;
if (!empty($formData['duty_id'])) {
    $selectedDuty = new Duty($formData['duty_id']);
    $selectedDuty = $selectedDuty->getData();
}

// Include header
require_once __DIR__ . '/../../includes/header.php';
?>

<!-- Page Styles -->
<style>
    .swap-preview {
        background: var(--bg-light);
        border-radius: var(--radius);
        padding: 15px;
        margin-top: 15px;
        border-left: 4px solid var(--accent);
    }
    .swap-preview .preview-item {
        display: flex;
        justify-content: space-between;
        padding: 5px 0;
        border-bottom: 1px solid var(--border-light);
    }
    .swap-preview .preview-item:last-child {
        border-bottom: none;
    }
    .swap-preview .preview-label {
        color: var(--text-muted);
        font-size: 0.85rem;
    }
    .swap-preview .preview-value {
        font-weight: 500;
    }
    
    .form-section {
        background: var(--bg-light);
        border-radius: var(--radius);
        padding: 15px;
        margin-bottom: 20px;
    }
    .form-section .section-title {
        font-weight: 600;
        font-size: 0.9rem;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-bottom: 12px;
        padding-bottom: 8px;
        border-bottom: 1px solid var(--border-color);
    }
    
    .duty-select-card {
        cursor: pointer;
        transition: var(--transition);
        border: 2px solid var(--border-color);
        border-radius: var(--radius);
        padding: 10px 15px;
        margin-bottom: 8px;
    }
    .duty-select-card:hover {
        border-color: var(--accent);
        background: rgba(255, 215, 0, 0.05);
    }
    .duty-select-card.selected {
        border-color: var(--accent);
        background: rgba(255, 215, 0, 0.08);
    }
    .duty-select-card .duty-date {
        font-weight: 600;
    }
    .duty-select-card .duty-category {
        font-size: 0.85rem;
        color: var(--text-muted);
    }
    .duty-select-card .duty-time {
        font-size: 0.8rem;
        color: var(--text-light);
    }
    
    .teacher-select-card {
        cursor: pointer;
        transition: var(--transition);
        border: 2px solid var(--border-color);
        border-radius: var(--radius);
        padding: 10px 15px;
        margin-bottom: 8px;
    }
    .teacher-select-card:hover {
        border-color: var(--accent);
        background: rgba(255, 215, 0, 0.05);
    }
    .teacher-select-card.selected {
        border-color: var(--accent);
        background: rgba(255, 215, 0, 0.08);
    }
    .teacher-select-card .teacher-name {
        font-weight: 500;
    }
    .teacher-select-card .teacher-dept {
        font-size: 0.8rem;
        color: var(--text-muted);
    }
    
    .form-control:disabled {
        background: var(--bg-light);
        cursor: not-allowed;
    }
    
    .required-star {
        color: var(--danger);
        margin-left: 2px;
    }
    
    @media (max-width: 768px) {
        .swap-preview .preview-item {
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
                    <i class="fas fa-exchange-alt me-2" style="color: var(--accent);"></i>
                    <?php echo $pageTitle; ?>
                </h5>
                <small class="text-muted">Request to swap your duty with another teacher</small>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (empty($duties)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        You don't have any pending or accepted duties to swap.
                        <a href="<?php echo SITE_URL; ?>/views/duties/" class="alert-link">View your duties</a>
                    </div>
                <?php endif; ?>
                
                <form method="POST" id="swapForm" novalidate>
                    <?php echo CSRF::getTokenField(); ?>
                    
                    <!-- Step 1: Select Duty -->
                    <div class="form-section">
                        <div class="section-title">
                            <i class="fas fa-tasks me-2"></i> Step 1: Select Duty to Swap
                            <span class="required-star">*</span>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Select a duty you want to swap</label>
                            <select name="duty_id" id="dutySelect" class="form-control" required <?php echo empty($duties) ? 'disabled' : ''; ?>>
                                <option value="">-- Select a duty --</option>
                                <?php foreach ($duties as $duty): ?>
                                    <option value="<?php echo $duty['id']; ?>" 
                                            data-date="<?php echo $duty['duty_date']; ?>"
                                            data-start="<?php echo $duty['start_time']; ?>"
                                            data-end="<?php echo $duty['end_time']; ?>"
                                            data-category="<?php echo htmlspecialchars($duty['category_name'] ?? ''); ?>"
                                            <?php echo $formData['duty_id'] == $duty['id'] ? 'selected' : ''; ?>>
                                        <?php echo formatDate($duty['duty_date']); ?> - 
                                        <?php echo htmlspecialchars($duty['category_name'] ?? 'Uncategorized'); ?> 
                                        (<?php echo date('h:i A', strtotime($duty['start_time'])); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Only pending or accepted duties can be swapped.</small>
                        </div>
                        
                        <!-- Selected Duty Preview -->
                        <div id="dutyPreview" style="display: none;" class="swap-preview">
                            <div class="preview-item">
                                <span class="preview-label">Duty Date</span>
                                <span class="preview-value" id="previewDate">-</span>
                            </div>
                            <div class="preview-item">
                                <span class="preview-label">Category</span>
                                <span class="preview-value" id="previewCategory">-</span>
                            </div>
                            <div class="preview-item">
                                <span class="preview-label">Original Time</span>
                                <span class="preview-value" id="previewTime">-</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Step 2: Select Target Teacher -->
                    <div class="form-section">
                        <div class="section-title">
                            <i class="fas fa-user-friends me-2"></i> Step 2: Select Target Teacher
                            <span class="required-star">*</span>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Choose a teacher to swap with</label>
                            <select name="target_teacher_id" id="teacherSelect" class="form-control" required>
                                <option value="">-- Select a teacher --</option>
                                <?php foreach ($teachers as $teacher): ?>
                                    <option value="<?php echo $teacher['id']; ?>"
                                            data-department="<?php echo htmlspecialchars($teacher['department_name'] ?? 'No Department'); ?>"
                                            <?php echo $formData['target_teacher_id'] == $teacher['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?>
                                        (<?php echo htmlspecialchars($teacher['department_name'] ?? 'No Department'); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (empty($teachers)): ?>
                                <small class="text-danger">No other teachers available to swap with.</small>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Selected Teacher Preview -->
                        <div id="teacherPreview" style="display: none;" class="swap-preview">
                            <div class="preview-item">
                                <span class="preview-label">Teacher</span>
                                <span class="preview-value" id="previewTeacher">-</span>
                            </div>
                            <div class="preview-item">
                                <span class="preview-label">Department</span>
                                <span class="preview-value" id="previewDepartment">-</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Step 3: Requested Date & Time -->
                    <div class="form-section">
                        <div class="section-title">
                            <i class="fas fa-calendar-alt me-2"></i> Step 3: Requested Date & Time
                            <span class="required-star">*</span>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">
                                    <i class="fas fa-calendar-day me-1"></i> Requested Date
                                    <span class="required-star">*</span>
                                </label>
                                <input type="date" name="requested_date" id="requestedDate" 
                                       class="form-control" 
                                       value="<?php echo $formData['requested_date']; ?>" 
                                       min="<?php echo date('Y-m-d'); ?>" required>
                                <small class="text-muted">Date must be in the future.</small>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">
                                    <i class="fas fa-clock me-1"></i> Start Time
                                    <span class="required-star">*</span>
                                </label>
                                <input type="time" name="requested_start_time" id="requestedStartTime" 
                                       class="form-control" 
                                       value="<?php echo $formData['requested_start_time']; ?>" required>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">
                                    <i class="fas fa-clock me-1"></i> End Time
                                    <span class="required-star">*</span>
                                </label>
                                <input type="time" name="requested_end_time" id="requestedEndTime" 
                                       class="form-control" 
                                       value="<?php echo $formData['requested_end_time']; ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Step 4: Reason -->
                    <div class="form-section">
                        <div class="section-title">
                            <i class="fas fa-comment me-2"></i> Step 4: Reason for Swap
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Please explain why you need this swap</label>
                            <textarea name="reason" class="form-control" rows="3" 
                                      placeholder="e.g., I have a personal appointment, I need to attend a meeting, etc."><?php echo htmlspecialchars($formData['reason']); ?></textarea>
                            <small class="text-muted">Providing a reason helps the target teacher understand your request.</small>
                        </div>
                    </div>
                    
                    <!-- Summary -->
                    <div id="swapSummary" style="display: none;" class="alert alert-info">
                        <h6 class="mb-2"><i class="fas fa-info-circle me-2"></i> Swap Request Summary</h6>
                        <div id="summaryContent"></div>
                    </div>
                    
                    <!-- Actions -->
                    <div class="d-flex justify-content-between align-items-center mt-4">
                        <a href="<?php echo SITE_URL; ?>/views/swaps/" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-2"></i> Back
                        </a>
                        <div>
                            <button type="reset" class="btn btn-outline-secondary me-2">
                                <i class="fas fa-undo me-1"></i> Reset
                            </button>
                            <button type="submit" class="btn btn-accent" id="submitBtn" 
                                    <?php echo empty($duties) ? 'disabled' : ''; ?>>
                                <i class="fas fa-paper-plane me-2"></i> Submit Request
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Help Card -->
        <div class="card mt-3">
            <div class="card-body">
                <h6 class="mb-2"><i class="fas fa-lightbulb me-2" style="color: var(--accent);"></i> Tips</h6>
                <ul class="text-muted small mb-0">
                    <li>Only pending or accepted duties can be swapped.</li>
                    <li>The target teacher must be available at the requested time.</li>
                    <li>Admin approval is required after both teachers agree.</li>
                    <li>You can cancel the request at any time before it's completed.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // =============================================
    // Duty Selection Preview
    // =============================================
    const dutySelect = document.getElementById('dutySelect');
    const dutyPreview = document.getElementById('dutyPreview');
    const previewDate = document.getElementById('previewDate');
    const previewCategory = document.getElementById('previewCategory');
    const previewTime = document.getElementById('previewTime');
    
    dutySelect.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        if (this.value) {
            dutyPreview.style.display = 'block';
            previewDate.textContent = selectedOption.dataset.date || '-';
            previewCategory.textContent = selectedOption.dataset.category || '-';
            previewTime.textContent = selectedOption.dataset.start + ' - ' + selectedOption.dataset.end;
        } else {
            dutyPreview.style.display = 'none';
        }
        updateSummary();
    });
    
    // =============================================
    // Teacher Selection Preview
    // =============================================
    const teacherSelect = document.getElementById('teacherSelect');
    const teacherPreview = document.getElementById('teacherPreview');
    const previewTeacher = document.getElementById('previewTeacher');
    const previewDepartment = document.getElementById('previewDepartment');
    
    teacherSelect.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        if (this.value) {
            teacherPreview.style.display = 'block';
            previewTeacher.textContent = selectedOption.text.split('(')[0].trim();
            previewDepartment.textContent = selectedOption.dataset.department || 'No Department';
        } else {
            teacherPreview.style.display = 'none';
        }
        updateSummary();
    });
    
    // =============================================
    // Date & Time Validation
    // =============================================
    const requestedDate = document.getElementById('requestedDate');
    const startTime = document.getElementById('requestedStartTime');
    const endTime = document.getElementById('requestedEndTime');
    
    requestedDate.addEventListener('change', updateSummary);
    startTime.addEventListener('change', updateSummary);
    endTime.addEventListener('change', function() {
        if (startTime.value && this.value && startTime.value >= this.value) {
            alert('End time must be after start time.');
            this.value = '';
        }
        updateSummary();
    });
    
    // =============================================
    // Update Summary
    // =============================================
    function updateSummary() {
        const summary = document.getElementById('swapSummary');
        const content = document.getElementById('summaryContent');
        
        const duty = dutySelect.value;
        const teacher = teacherSelect.value;
        const date = requestedDate.value;
        const start = startTime.value;
        const end = endTime.value;
        
        if (duty && teacher && date && start && end) {
            const dutyText = dutySelect.options[dutySelect.selectedIndex].text;
            const teacherText = teacherSelect.options[teacherSelect.selectedIndex].text;
            
            summary.style.display = 'block';
            content.innerHTML = `
                <div class="row">
                    <div class="col-md-6">
                        <strong>Duty:</strong> ${dutyText}
                    </div>
                    <div class="col-md-6">
                        <strong>Target Teacher:</strong> ${teacherText}
                    </div>
                </div>
                <div class="row mt-2">
                    <div class="col-md-6">
                        <strong>Requested Date:</strong> ${date}
                    </div>
                    <div class="col-md-6">
                        <strong>Time:</strong> ${start} - ${end}
                    </div>
                </div>
            `;
        } else {
            summary.style.display = 'none';
        }
    }
    
    // =============================================
    // Form Submission
    // =============================================
    document.getElementById('swapForm').addEventListener('submit', function(e) {
        const submitBtn = document.getElementById('submitBtn');
        const errors = [];
        
        // Validate required fields
        if (!dutySelect.value) {
            errors.push('Please select a duty to swap.');
        }
        if (!teacherSelect.value) {
            errors.push('Please select a target teacher.');
        }
        if (!requestedDate.value) {
            errors.push('Please select a requested date.');
        }
        if (!startTime.value) {
            errors.push('Please select a start time.');
        }
        if (!endTime.value) {
            errors.push('Please select an end time.');
        }
        
        // Validate time
        if (startTime.value && endTime.value && startTime.value >= endTime.value) {
            errors.push('End time must be after start time.');
        }
        
        // Validate date
        if (requestedDate.value && new Date(requestedDate.value) < new Date()) {
            errors.push('Requested date must be in the future.');
        }
        
        if (errors.length > 0) {
            e.preventDefault();
            alert('Please fix the following issues:\n\n• ' + errors.join('\n• '));
            return;
        }
        
        // Disable submit button and show loading
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Submitting...';
    });
    
    // =============================================
    // Trigger initial update
    // =============================================
    if (dutySelect.value) {
        dutySelect.dispatchEvent(new Event('change'));
    }
    if (teacherSelect.value) {
        teacherSelect.dispatchEvent(new Event('change'));
    }
    updateSummary();
});
</script>

<?php
// Include footer
require_once __DIR__ . '/../../includes/footer.php';
?>