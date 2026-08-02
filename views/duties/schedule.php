<?php
// =============================================
// Generate Schedule
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

// Set page variables
$pageTitle = 'Generate Schedule';
$pageIcon = 'fas fa-calendar-alt';
$breadcrumb = [
    ['label' => 'Dashboard', 'url' => SITE_URL . '/views/dashboard/admin.php'],
    ['label' => 'Duties', 'url' => SITE_URL . '/views/duties/'],
    ['label' => 'Generate Schedule', 'active' => true]
];

// Get data for filters
$db = Database::getInstance();
$departments = Department::all(['status' => STATUS_ACTIVE]);
$categories = DutyCategory::getActive();

$error = '';
$success = '';
$generatedCount = 0;
$generatedDuties = [];

// Process schedule generation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::verifyToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $params = [
            'start_date' => $_POST['start_date'] ?? '',
            'end_date' => $_POST['end_date'] ?? '',
            'department_id' => $_POST['department_id'] ?? '',
            'category_id' => $_POST['category_id'] ?? '',
            'start_time' => $_POST['start_time'] ?? '08:00:00',
            'end_time' => $_POST['end_time'] ?? '10:00:00',
            'max_duties' => (int)($_POST['max_duties'] ?? 50),
            'skip_weekends' => isset($_POST['skip_weekends'])
        ];
        
        // Validate
        $errors = [];
        if (empty($params['start_date'])) $errors[] = 'Start date is required.';
        if (empty($params['end_date'])) $errors[] = 'End date is required.';
        
        if (empty($errors)) {
            $params['assigned_by'] = $user['id'];
            
            $result = Duty::autoGenerate($params);
            
            if ($result['success']) {
                $generatedCount = $result['generated'];
                $generatedDuties = $result['duties'] ?? [];
                $success = "Schedule generated successfully! {$generatedCount} duties created.";
            } else {
                $error = $result['message'] ?? 'Failed to generate schedule.';
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
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 15px;
        margin-bottom: 25px;
    }
    .stat-card {
        background: var(--bg-white);
        padding: 15px 20px;
        border-radius: var(--radius);
        box-shadow: var(--shadow);
        border: 1px solid var(--border-light);
        transition: var(--transition);
        text-align: center;
    }
    .stat-card:hover {
        transform: translateY(-3px);
        box-shadow: var(--shadow-md);
    }
    .stat-card .number {
        font-size: 28px;
        font-weight: 700;
        color: var(--text-dark);
    }
    .stat-card .label {
        font-size: 12px;
        color: var(--text-muted);
    }
    .stat-card .icon {
        font-size: 32px;
        display: block;
        margin-bottom: 8px;
        opacity: 0.5;
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
    
    .schedule-preview {
        background: var(--bg-white);
        border-radius: var(--radius);
        border: 1px solid var(--border-light);
        padding: 15px;
        max-height: 400px;
        overflow-y: auto;
    }
    .schedule-preview .preview-item {
        display: flex;
        justify-content: space-between;
        padding: 8px 0;
        border-bottom: 1px solid var(--border-light);
        font-size: 0.9rem;
    }
    .schedule-preview .preview-item:last-child {
        border-bottom: none;
    }
    .schedule-preview .preview-item .duty-code {
        font-weight: 600;
    }
    .schedule-preview .preview-item .duty-details {
        color: var(--text-muted);
    }
    
    .btn-primary {
        background: var(--primary);
        border-color: var(--primary);
        transition: var(--transition);
    }
    .btn-primary:hover {
        background: var(--primary-light);
        border-color: var(--primary-light);
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(26, 26, 46, 0.3);
    }
    .btn-primary:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none;
    }
    
    @media (max-width: 768px) {
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        .form-section {
            padding: 15px;
        }
        .schedule-preview .preview-item {
            flex-direction: column;
            gap: 2px;
        }
    }
</style>

<!-- Statistics -->
<?php 
// Get current stats
$totalTeachers = Teacher::count(['status' => STATUS_ACTIVE]);
$totalCategories = count($categories);
$today = date('Y-m-d');
$stmt = $db->prepare("SELECT COUNT(*) as count FROM duties WHERE duty_date >= ?");
$stmt->execute([$today]);
$upcomingDuties = $stmt->fetch()['count'] ?? 0;
?>

<div class="stats-grid">
    <div class="stat-card">
        <span class="icon"><i class="fas fa-user-friends"></i></span>
        <span class="number"><?php echo $totalTeachers; ?></span>
        <span class="label">Active Teachers</span>
    </div>
    <div class="stat-card">
        <span class="icon"><i class="fas fa-tags"></i></span>
        <span class="number"><?php echo $totalCategories; ?></span>
        <span class="label">Duty Categories</span>
    </div>
    <div class="stat-card">
        <span class="icon"><i class="fas fa-calendar-alt"></i></span>
        <span class="number"><?php echo $upcomingDuties; ?></span>
        <span class="label">Upcoming Duties</span>
    </div>
    <?php if ($generatedCount > 0): ?>
        <div class="stat-card" style="border-color: var(--success);">
            <span class="icon"><i class="fas fa-check-circle" style="color: var(--success);"></i></span>
            <span class="number" style="color: var(--success);"><?php echo $generatedCount; ?></span>
            <span class="label">Generated</span>
        </div>
    <?php endif; ?>
</div>

<div class="row justify-content-center">
    <div class="col-lg-10">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-calendar-alt me-2" style="color: var(--accent);"></i>
                    <?php echo $pageTitle; ?>
                </h5>
                <small class="text-muted">Auto-generate duties based on available teachers and categories</small>
            </div>
            <div class="card-body">
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
                    
                    <?php if (!empty($generatedDuties)): ?>
                        <div class="schedule-preview mt-3">
                            <h6 class="mb-2"><i class="fas fa-list me-2"></i>Generated Duties</h6>
                            <?php foreach ($generatedDuties as $duty): ?>
                                <div class="preview-item">
                                    <span class="duty-code"><?php echo htmlspecialchars($duty['duty_code']); ?></span>
                                    <span class="duty-details">
                                        <?php echo htmlspecialchars($duty['category_name'] ?? ''); ?> - 
                                        <?php echo formatDate($duty['duty_date']); ?> 
                                        (<?php echo date('h:i A', strtotime($duty['start_time'])); ?>)
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="mt-3">
                            <a href="<?php echo SITE_URL; ?>/views/duties/" class="btn btn-primary">
                                <i class="fas fa-list me-2"></i> View All Duties
                            </a>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
                
                <form method="POST" id="scheduleForm">
                    <?php echo CSRF::getTokenField(); ?>
                    
                    <!-- Date Range -->
                    <div class="form-section">
                        <div class="section-title">
                            <i class="fas fa-calendar-range me-2"></i> Date Range
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Start Date <span class="required-star">*</span></label>
                                <input type="date" name="start_date" class="form-control" 
                                       value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">End Date <span class="required-star">*</span></label>
                                <input type="date" name="end_date" class="form-control" 
                                       value="<?php echo date('Y-m-d', strtotime('+7 days')); ?>" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="skip_weekends" name="skip_weekends" checked>
                                    <label class="form-check-label" for="skip_weekends">
                                        <i class="fas fa-calendar-week me-1"></i>
                                        Skip Weekends (Saturday & Sunday)
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Max Duties to Generate</label>
                                <input type="number" name="max_duties" class="form-control" 
                                       value="50" min="1" max="500">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Filters -->
                    <div class="form-section">
                        <div class="section-title">
                            <i class="fas fa-filter me-2"></i> Filters
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Department</label>
                                <select name="department_id" class="form-control">
                                    <option value="">All Departments</option>
                                    <?php foreach ($departments as $dept): ?>
                                        <option value="<?php echo $dept['id']; ?>">
                                            <?php echo htmlspecialchars($dept['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Category</label>
                                <select name="category_id" class="form-control">
                                    <option value="">All Categories</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo $cat['id']; ?>">
                                            <?php echo htmlspecialchars($cat['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Time Settings -->
                    <div class="form-section">
                        <div class="section-title">
                            <i class="fas fa-clock me-2"></i> Time Settings
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Default Start Time</label>
                                <input type="time" name="start_time" class="form-control" value="08:00">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Default End Time</label>
                                <input type="time" name="end_time" class="form-control" value="10:00">
                            </div>
                        </div>
                        <div class="text-muted small">
                            <i class="fas fa-info-circle me-1"></i>
                            These times will be used when assigning duties. Individual duties can be edited later.
                        </div>
                    </div>
                    
                    <!-- Actions -->
                    <div class="d-flex justify-content-between align-items-center mt-4">
                        <a href="<?php echo SITE_URL; ?>/views/duties/" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-2"></i> Back
                        </a>
                        <button type="submit" class="btn btn-primary" id="generateBtn">
                            <i class="fas fa-magic me-2"></i> Generate Schedule
                        </button>
                    </div>
                </form>
                
                <!-- Information Card -->
                <div class="card mt-4">
                    <div class="card-body">
                        <h6 class="mb-2">
                            <i class="fas fa-lightbulb me-2" style="color: var(--accent);"></i>
                            How it works
                        </h6>
                        <ul class="text-muted small mb-0">
                            <li>The system automatically assigns duties to available teachers.</li>
                            <li>Teachers are selected based on current workload (fewest duties assigned).</li>
                            <li>Conflicts are automatically prevented (no teacher with overlapping duties).</li>
                            <li>Teachers on leave are automatically excluded.</li>
                            <li>Each teacher has a maximum number of duties per week.</li>
                            <li>You can edit individual duties after generation.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('scheduleForm');
    const generateBtn = document.getElementById('generateBtn');
    
    // =============================================
    // Form Validation
    // =============================================
    form.addEventListener('submit', function(e) {
        const startDate = document.querySelector('input[name="start_date"]');
        const endDate = document.querySelector('input[name="end_date"]');
        let isValid = true;
        
        // Validate dates
        if (!startDate.value) {
            startDate.classList.add('is-invalid');
            isValid = false;
        } else {
            startDate.classList.remove('is-invalid');
        }
        
        if (!endDate.value) {
            endDate.classList.add('is-invalid');
            isValid = false;
        } else {
            endDate.classList.remove('is-invalid');
        }
        
        // Validate date range
        if (startDate.value && endDate.value && startDate.value > endDate.value) {
            endDate.classList.add('is-invalid');
            alert('End date must be after start date.');
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
        
        // Confirm generation
        const dutyCount = document.querySelector('input[name="max_duties"]').value;
        if (!confirm(`Generate up to ${dutyCount} duties for the selected date range?`)) {
            e.preventDefault();
            return;
        }
        
        // Disable button and show loading
        generateBtn.disabled = true;
        generateBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Generating...';
    });
    
    // =============================================
    // Real-time validation
    // =============================================
    document.querySelectorAll('input[required]').forEach(field => {
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
    
    console.log('📅 Schedule Generation page loaded');
});
</script>

<?php
// Include footer
require_once __DIR__ . '/../../includes/footer.php';
?>