<?php
// =============================================
// Add Teacher - Admin Adds Existing User
// =============================================

// Load configuration
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../models/Teacher.php';
require_once __DIR__ . '/../../models/Department.php';

// Initialize auth
$auth = new Auth();
if (!$auth->isLoggedIn() || !in_array($auth->getUser()['role'], ['admin', 'super_admin'])) {
    redirect(SITE_URL . '/views/auth/login.php');
}

$user = $auth->getUser();
$role = $user['role'] ?? '';

// Set page variables
$pageTitle = 'Add Teacher';
$pageIcon = 'fas fa-user-plus';
$breadcrumb = [
    ['label' => 'Dashboard', 'url' => SITE_URL . '/views/dashboard/admin.php'],
    ['label' => 'Teachers', 'url' => SITE_URL . '/views/teachers/'],
    ['label' => 'Add Teacher', 'active' => true]
];

$db = Database::getInstance();

// Get users who are not teachers yet (role = 'teacher' but not in teachers table)
$stmt = $db->query("
    SELECT u.id, u.username, u.email, u.full_name 
    FROM users u
    LEFT JOIN teachers t ON u.email = t.email AND t.deleted_at IS NULL
    WHERE u.role = 'teacher' AND t.id IS NULL AND u.status = 'active'
    ORDER BY u.full_name
");
$availableUsers = $stmt->fetchAll();

$departments = Department::all(['status' => STATUS_ACTIVE]);

$error = '';
$success = '';
$formData = [
    'user_id' => '',
    'first_name' => '',
    'last_name' => '',
    'gender' => '',
    'email' => '',
    'phone_primary' => '',
    'phone_secondary' => '',
    'date_of_birth' => '',
    'department_id' => '',
    'position' => '',
    'qualification' => '',
    'experience_years' => 0,
    'address' => '',
    'status' => 'active',
    'bio' => '',
    'skills' => '',
    'languages' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::verifyToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $formData = [
            'user_id' => (int)$_POST['user_id'] ?? 0,
            'first_name' => sanitizeInput($_POST['first_name'] ?? ''),
            'last_name' => sanitizeInput($_POST['last_name'] ?? ''),
            'gender' => sanitizeInput($_POST['gender'] ?? ''),
            'email' => sanitizeInput($_POST['email'] ?? ''),
            'phone_primary' => sanitizeInput($_POST['phone_primary'] ?? ''),
            'phone_secondary' => sanitizeInput($_POST['phone_secondary'] ?? ''),
            'date_of_birth' => $_POST['date_of_birth'] ?? '',
            'department_id' => $_POST['department_id'] ?? '',
            'position' => sanitizeInput($_POST['position'] ?? ''),
            'qualification' => sanitizeInput($_POST['qualification'] ?? ''),
            'experience_years' => (int)($_POST['experience_years'] ?? 0),
            'address' => sanitizeInput($_POST['address'] ?? ''),
            'status' => sanitizeInput($_POST['status'] ?? 'active'),
            'bio' => sanitizeInput($_POST['bio'] ?? ''),
            'skills' => sanitizeInput($_POST['skills'] ?? ''),
            'languages' => sanitizeInput($_POST['languages'] ?? '')
        ];
        
        // Validate
        $errors = [];
        if (empty($formData['user_id'])) $errors[] = 'Please select a user.';
        if (empty($formData['first_name'])) $errors[] = 'First name is required.';
        if (empty($formData['last_name'])) $errors[] = 'Last name is required.';
        if (empty($formData['gender'])) $errors[] = 'Gender is required.';
        if (empty($formData['email'])) $errors[] = 'Email is required.';
        if (!isValidEmail($formData['email'])) $errors[] = 'Please enter a valid email address.';
        if (empty($formData['phone_primary'])) $errors[] = 'Phone number is required.';
        
        // Check if email exists in teachers
        if (empty($errors) && Teacher::findByEmail($formData['email'])) {
            $errors[] = 'This user is already a teacher.';
        }
        
        if (empty($errors)) {
            $teacher = new Teacher();
            if ($teacher->create($formData)) {
                setFlashMessage('success', 'Teacher added successfully!');
                redirect(SITE_URL . '/views/teachers/');
            } else {
                $error = 'Failed to add teacher. Please try again.';
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
    .user-select-card {
        cursor: pointer;
        transition: var(--transition);
        border: 2px solid var(--border-color);
        border-radius: var(--radius);
        padding: 10px 15px;
        margin-bottom: 8px;
    }
    .user-select-card:hover {
        border-color: var(--accent);
        background: rgba(255, 215, 0, 0.05);
    }
    .user-select-card.selected {
        border-color: var(--accent);
        background: rgba(255, 215, 0, 0.08);
    }
    @media (max-width: 768px) {
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
                    <i class="fas fa-user-plus me-2" style="color: var(--accent);"></i>
                    <?php echo $pageTitle; ?>
                </h5>
                <small class="text-muted">Add an existing user as a teacher</small>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <form method="POST" enctype="multipart/form-data" id="teacherForm">
                    <?php echo CSRF::getTokenField(); ?>
                    
                    <!-- Select User -->
                    <div class="form-section">
                        <div class="section-title">
                            <i class="fas fa-user me-2"></i> Select User <span class="required-star">*</span>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Choose a registered user</label>
                            <select name="user_id" id="userSelect" class="form-control" required>
                                <option value="">-- Select a user --</option>
                                <?php foreach ($availableUsers as $u): ?>
                                    <option value="<?php echo $u['id']; ?>" 
                                            data-email="<?php echo htmlspecialchars($u['email']); ?>"
                                            data-name="<?php echo htmlspecialchars($u['full_name']); ?>"
                                            <?php echo $formData['user_id'] == $u['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($u['full_name']); ?> (<?php echo htmlspecialchars($u['email']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (empty($availableUsers)): ?>
                                <div class="alert alert-info mt-2">
                                    <i class="fas fa-info-circle me-2"></i>
                                    No users available. Users must register first before they can be added as teachers.
                                    <a href="<?php echo SITE_URL; ?>/views/auth/register.php" class="alert-link">Register a new user</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Personal Information -->
                    <div class="form-section">
                        <div class="section-title">
                            <i class="fas fa-address-card me-2"></i> Teacher Details
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">First Name <span class="required-star">*</span></label>
                                <input type="text" name="first_name" id="firstName" class="form-control" 
                                       value="<?php echo htmlspecialchars($formData['first_name']); ?>" 
                                       placeholder="Enter first name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Last Name <span class="required-star">*</span></label>
                                <input type="text" name="last_name" id="lastName" class="form-control" 
                                       value="<?php echo htmlspecialchars($formData['last_name']); ?>" 
                                       placeholder="Enter last name" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Gender <span class="required-star">*</span></label>
                                <select name="gender" class="form-control" required>
                                    <option value="">Select Gender...</option>
                                    <option value="male" <?php echo $formData['gender'] === 'male' ? 'selected' : ''; ?>>Male</option>
                                    <option value="female" <?php echo $formData['gender'] === 'female' ? 'selected' : ''; ?>>Female</option>
                                    <option value="other" <?php echo $formData['gender'] === 'other' ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Date of Birth</label>
                                <input type="date" name="date_of_birth" class="form-control" 
                                       value="<?php echo htmlspecialchars($formData['date_of_birth']); ?>">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email <span class="required-star">*</span></label>
                                <input type="email" name="email" id="email" class="form-control" 
                                       value="<?php echo htmlspecialchars($formData['email']); ?>" required readonly>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone Number <span class="required-star">*</span></label>
                                <input type="tel" name="phone_primary" class="form-control" 
                                       value="<?php echo htmlspecialchars($formData['phone_primary']); ?>" 
                                       placeholder="Enter phone number" required>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Professional Information -->
                    <div class="form-section">
                        <div class="section-title">
                            <i class="fas fa-briefcase me-2"></i> Professional Information
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Department</label>
                                <select name="department_id" class="form-control">
                                    <option value="">Select Department...</option>
                                    <?php foreach ($departments as $dept): ?>
                                        <option value="<?php echo $dept['id']; ?>" <?php echo $formData['department_id'] == $dept['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($dept['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Position</label>
                                <input type="text" name="position" class="form-control" 
                                       value="<?php echo htmlspecialchars($formData['position']); ?>" 
                                       placeholder="e.g., Senior Teacher">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Qualification</label>
                                <input type="text" name="qualification" class="form-control" 
                                       value="<?php echo htmlspecialchars($formData['qualification']); ?>" 
                                       placeholder="e.g., B.Sc. Education">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Experience (Years)</label>
                                <input type="number" name="experience_years" class="form-control" 
                                       value="<?php echo htmlspecialchars($formData['experience_years']); ?>" 
                                       min="0" max="50" placeholder="0">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Bio / About</label>
                                <textarea name="bio" class="form-control" rows="3" 
                                          placeholder="Brief biography or additional information"><?php echo htmlspecialchars($formData['bio']); ?></textarea>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-control">
                                    <option value="active" <?php echo $formData['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo $formData['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                    <option value="on_leave" <?php echo $formData['status'] === 'on_leave' ? 'selected' : ''; ?>>On Leave</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Actions -->
                    <div class="d-flex justify-content-between align-items-center mt-4">
                        <a href="<?php echo SITE_URL; ?>/views/teachers/" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-2"></i> Back
                        </a>
                        <button type="submit" class="btn btn-accent" id="submitBtn" <?php echo empty($availableUsers) ? 'disabled' : ''; ?>>
                            <i class="fas fa-save me-2"></i> Add Teacher
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // =============================================
    // Auto-fill user details on selection
    // =============================================
    const userSelect = document.getElementById('userSelect');
    const firstName = document.getElementById('firstName');
    const lastName = document.getElementById('lastName');
    const email = document.getElementById('email');
    
    userSelect.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        if (this.value) {
            const fullName = selectedOption.dataset.name || '';
            const nameParts = fullName.split(' ');
            firstName.value = nameParts[0] || '';
            lastName.value = nameParts.slice(1).join(' ') || '';
            email.value = selectedOption.dataset.email || '';
        }
    });
    
    // =============================================
    // Form Validation
    // =============================================
    document.getElementById('teacherForm').addEventListener('submit', function(e) {
        const submitBtn = document.getElementById('submitBtn');
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
        
        // Email validation
        const emailField = document.querySelector('input[name="email"]');
        if (emailField.value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailField.value)) {
            emailField.classList.add('is-invalid');
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
        
        // Disable submit button and show loading
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Adding Teacher...';
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
});
</script>

<?php
// Include footer
require_once __DIR__ . '/../../includes/footer.php';
?>