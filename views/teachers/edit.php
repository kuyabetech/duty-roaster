<?php
// =============================================
// Edit Teacher
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

// Get teacher ID from URL
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    setFlashMessage('error', 'Invalid teacher ID.');
    redirect(SITE_URL . '/views/teachers/');
}

// Load teacher data
$teacher = new Teacher($id);
if (!$teacher->getData()) {
    setFlashMessage('error', 'Teacher not found.');
    redirect(SITE_URL . '/views/teachers/');
}

$teacherData = $teacher->getData();

// Set page variables
$pageTitle = 'Edit Teacher';
$pageIcon = 'fas fa-user-edit';
$breadcrumb = [
    ['label' => 'Dashboard', 'url' => SITE_URL . '/views/dashboard/admin.php'],
    ['label' => 'Teachers', 'url' => SITE_URL . '/views/teachers/'],
    ['label' => 'Edit Teacher', 'active' => true]
];

$departments = Department::all(['status' => STATUS_ACTIVE]);

$error = '';
$success = '';
$formData = [
    'first_name' => $teacherData['first_name'] ?? '',
    'last_name' => $teacherData['last_name'] ?? '',
    'gender' => $teacherData['gender'] ?? '',
    'email' => $teacherData['email'] ?? '',
    'phone_primary' => $teacherData['phone_primary'] ?? '',
    'phone_secondary' => $teacherData['phone_secondary'] ?? '',
    'date_of_birth' => $teacherData['date_of_birth'] ?? '',
    'department_id' => $teacherData['department_id'] ?? '',
    'position' => $teacherData['position'] ?? '',
    'qualification' => $teacherData['qualification'] ?? '',
    'experience_years' => $teacherData['experience_years'] ?? 0,
    'address' => $teacherData['current_address'] ?? '',
    'status' => $teacherData['status'] ?? 'active',
    'bio' => $teacherData['bio'] ?? '',
    'skills' => $teacherData['skills'] ?? '',
    'languages' => $teacherData['languages'] ?? '',
    'contract_type' => $teacherData['contract_type'] ?? 'permanent'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::verifyToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $formData = [
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
            'languages' => sanitizeInput($_POST['languages'] ?? ''),
            'contract_type' => sanitizeInput($_POST['contract_type'] ?? 'permanent')
        ];
        
        // Validate
        $errors = [];
        if (empty($formData['first_name'])) $errors[] = 'First name is required.';
        if (empty($formData['last_name'])) $errors[] = 'Last name is required.';
        if (empty($formData['gender'])) $errors[] = 'Gender is required.';
        if (empty($formData['email'])) $errors[] = 'Email is required.';
        if (!isValidEmail($formData['email'])) $errors[] = 'Please enter a valid email address.';
        if (empty($formData['phone_primary'])) $errors[] = 'Phone number is required.';
        
        // Check if email exists for other teachers
        if (empty($errors)) {
            $existingTeacher = Teacher::findByEmail($formData['email']);
            if ($existingTeacher && $existingTeacher['id'] != $id) {
                $errors[] = 'Email already exists in the system.';
            }
        }
        
        // Handle profile photo upload
        $profilePhoto = null;
        if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $maxSize = 5 * 1024 * 1024; // 5MB
            
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $_FILES['profile_photo']['tmp_name']);
            finfo_close($finfo);
            
            if (!in_array($mimeType, $allowedTypes)) {
                $errors[] = 'Invalid file type. Please upload JPEG, PNG, GIF, or WEBP.';
            } elseif ($_FILES['profile_photo']['size'] > $maxSize) {
                $errors[] = 'File too large. Maximum size is 5MB.';
            } else {
                $uploadDir = PROFILE_PHOTO_PATH;
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                $extension = pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION);
                $filename = 'profile_' . time() . '_' . uniqid() . '.' . $extension;
                $targetPath = $uploadDir . $filename;
                
                if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $targetPath)) {
                    // Delete old photo if exists
                    if (!empty($teacherData['profile_photo']) && file_exists($uploadDir . $teacherData['profile_photo'])) {
                        unlink($uploadDir . $teacherData['profile_photo']);
                    }
                    $profilePhoto = $filename;
                } else {
                    $errors[] = 'Failed to upload profile photo.';
                }
            }
        }
        
        if (empty($errors)) {
            if ($profilePhoto) {
                $formData['profile_photo'] = $profilePhoto;
            }
            
            if ($teacher->update($formData)) {
                setFlashMessage('success', 'Teacher updated successfully!');
                redirect(SITE_URL . '/views/teachers/');
            } else {
                $error = 'Failed to update teacher. Please try again.';
                // Delete uploaded photo if update failed
                if ($profilePhoto && file_exists(PROFILE_PHOTO_PATH . $profilePhoto)) {
                    unlink(PROFILE_PHOTO_PATH . $profilePhoto);
                }
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
    
    .photo-upload-wrapper {
        position: relative;
        display: inline-block;
        cursor: pointer;
    }
    .photo-upload-wrapper .photo-preview {
        width: 150px;
        height: 150px;
        border-radius: 50%;
        border: 3px solid var(--border-color);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-direction: column;
        transition: var(--transition);
        overflow: hidden;
        background: var(--bg-white);
    }
    .photo-upload-wrapper .photo-preview:hover {
        border-color: var(--accent);
        background: var(--bg-light);
    }
    .photo-upload-wrapper .photo-preview img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .photo-upload-wrapper .photo-preview .placeholder {
        color: var(--text-muted);
        text-align: center;
        padding: 20px;
    }
    .photo-upload-wrapper .photo-preview .placeholder i {
        font-size: 40px;
        display: block;
        margin-bottom: 10px;
        color: var(--text-light);
    }
    .photo-upload-wrapper .photo-preview .placeholder span {
        font-size: 0.8rem;
    }
    .photo-upload-wrapper input[type="file"] {
        display: none;
    }
    .photo-upload-wrapper .remove-photo {
        position: absolute;
        top: 5px;
        right: 5px;
        background: var(--danger);
        color: #fff;
        border: none;
        border-radius: 50%;
        width: 28px;
        height: 28px;
        font-size: 14px;
        cursor: pointer;
        transition: var(--transition);
        display: none;
        align-items: center;
        justify-content: center;
    }
    .photo-upload-wrapper .remove-photo:hover {
        transform: scale(1.1);
    }
    .photo-upload-wrapper .remove-photo.show {
        display: flex;
    }
    
    .form-control:disabled {
        background: var(--bg-secondary);
        cursor: not-allowed;
    }
    
    .help-text {
        font-size: 0.8rem;
        color: var(--text-muted);
        margin-top: 4px;
    }
    
    .status-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
    }
    .status-badge.active { background: #d4edda; color: #155724; }
    .status-badge.inactive { background: #e2e3e5; color: #383d41; }
    .status-badge.on_leave { background: #fff3cd; color: #856404; }
    
    @media (max-width: 768px) {
        .form-section {
            padding: 15px;
        }
        .photo-upload-wrapper .photo-preview {
            width: 120px;
            height: 120px;
        }
    }
</style>

<div class="row justify-content-center">
    <div class="col-lg-10">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-user-edit me-2" style="color: var(--accent);"></i>
                    <?php echo $pageTitle; ?>
                </h5>
                <small class="text-muted">Update teacher information</small>
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
                    
                    <!-- Profile Photo -->
                    <div class="form-section">
                        <div class="section-title">
                            <i class="fas fa-camera me-2"></i> Profile Photo
                        </div>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="photo-upload-wrapper" id="photoUpload">
                                    <div class="photo-preview" id="photoPreview">
                                        <?php if (!empty($teacherData['profile_photo']) && file_exists(PROFILE_PHOTO_PATH . $teacherData['profile_photo'])): ?>
                                            <img id="photoImage" src="<?php echo SITE_URL . '/uploads/profiles/' . $teacherData['profile_photo']; ?>" alt="Profile Photo">
                                        <?php else: ?>
                                            <div class="placeholder" id="photoPlaceholder">
                                                <i class="fas fa-user-circle"></i>
                                                <span>Click to upload photo</span>
                                            </div>
                                            <img id="photoImage" style="display:none;" alt="Profile Photo">
                                        <?php endif; ?>
                                    </div>
                                    <button type="button" class="remove-photo <?php echo !empty($teacherData['profile_photo']) ? 'show' : ''; ?>" id="removePhoto" title="Remove Photo">
                                        <i class="fas fa-times"></i>
                                    </button>
                                    <input type="file" name="profile_photo" id="profilePhoto" accept="image/*">
                                </div>
                                <div class="help-text">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Upload a profile photo (JPEG, PNG, GIF, WEBP). Max size: 5MB.
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Personal Information -->
                    <div class="form-section">
                        <div class="section-title">
                            <i class="fas fa-user me-2"></i> Personal Information
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">First Name <span class="required-star">*</span></label>
                                <input type="text" name="first_name" class="form-control" 
                                       value="<?php echo htmlspecialchars($formData['first_name']); ?>" 
                                       placeholder="Enter first name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Last Name <span class="required-star">*</span></label>
                                <input type="text" name="last_name" class="form-control" 
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
                    </div>
                    
                    <!-- Contact Information -->
                    <div class="form-section">
                        <div class="section-title">
                            <i class="fas fa-address-card me-2"></i> Contact Information
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email Address <span class="required-star">*</span></label>
                                <input type="email" name="email" class="form-control" 
                                       value="<?php echo htmlspecialchars($formData['email']); ?>" 
                                       placeholder="Enter email address" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone Number <span class="required-star">*</span></label>
                                <input type="tel" name="phone_primary" class="form-control" 
                                       value="<?php echo htmlspecialchars($formData['phone_primary']); ?>" 
                                       placeholder="Enter phone number" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Address</label>
                                <textarea name="address" class="form-control" rows="2" 
                                          placeholder="Enter residential address"><?php echo htmlspecialchars($formData['address']); ?></textarea>
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
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Contract Type</label>
                                <select name="contract_type" class="form-control">
                                    <option value="permanent" <?php echo $formData['contract_type'] === 'permanent' ? 'selected' : ''; ?>>Permanent</option>
                                    <option value="contract" <?php echo $formData['contract_type'] === 'contract' ? 'selected' : ''; ?>>Contract</option>
                                    <option value="part_time" <?php echo $formData['contract_type'] === 'part_time' ? 'selected' : ''; ?>>Part Time</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-control">
                                    <option value="active" <?php echo $formData['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo $formData['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                    <option value="on_leave" <?php echo $formData['status'] === 'on_leave' ? 'selected' : ''; ?>>On Leave</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Bio / About</label>
                                <textarea name="bio" class="form-control" rows="3" 
                                          placeholder="Brief biography or additional information"><?php echo htmlspecialchars($formData['bio']); ?></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Teacher ID & Staff Number (Read Only) -->
                    <div class="form-section">
                        <div class="section-title">
                            <i class="fas fa-id-card me-2"></i> System Information
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Teacher ID</label>
                                <input type="text" class="form-control" 
                                       value="<?php echo htmlspecialchars($teacherData['teacher_id'] ?? 'N/A'); ?>" disabled>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Staff Number</label>
                                <input type="text" class="form-control" 
                                       value="<?php echo htmlspecialchars($teacherData['staff_number'] ?? 'N/A'); ?>" disabled>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Actions -->
                    <div class="d-flex justify-content-between align-items-center mt-4">
                        <a href="<?php echo SITE_URL; ?>/views/teachers/" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-2"></i> Back
                        </a>
                        <div>
                            <button type="reset" class="btn btn-outline-secondary me-2">
                                <i class="fas fa-undo me-1"></i> Reset
                            </button>
                            <button type="submit" class="btn btn-accent" id="submitBtn">
                                <i class="fas fa-save me-2"></i> Update Teacher
                            </button>
                        </div>
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
    // Profile Photo Upload Preview
    // =============================================
    const photoInput = document.getElementById('profilePhoto');
    const photoPreview = document.getElementById('photoPreview');
    const photoImage = document.getElementById('photoImage');
    const photoPlaceholder = document.getElementById('photoPlaceholder');
    const removePhotoBtn = document.getElementById('removePhoto');
    
    photoInput.addEventListener('change', function(e) {
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(event) {
                photoImage.src = event.target.result;
                photoImage.style.display = 'block';
                if (photoPlaceholder) photoPlaceholder.style.display = 'none';
                removePhotoBtn.classList.add('show');
            };
            reader.readAsDataURL(file);
        }
    });
    
    removePhotoBtn.addEventListener('click', function() {
        photoInput.value = '';
        photoImage.src = '';
        photoImage.style.display = 'none';
        if (photoPlaceholder) photoPlaceholder.style.display = 'block';
        this.classList.remove('show');
    });
    
    // Click on preview to trigger file input
    photoPreview.addEventListener('click', function(e) {
        if (e.target !== removePhotoBtn) {
            photoInput.click();
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
        const email = document.querySelector('input[name="email"]');
        if (email.value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value)) {
            email.classList.add('is-invalid');
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
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Updating Teacher...';
    });
    
    // =============================================
    // Real-time Validation
    // =============================================
    document.querySelectorAll('input[required], select[required]').forEach(field => {
        field.addEventListener('input', function() {
            if (this.value.trim()) {
                this.classList.remove('is-invalid');
                this.classList.add('is-valid');
            } else {
                this.classList.remove('is-valid');
                this.classList.add('is-invalid');
            }
        });
        
        field.addEventListener('change', function() {
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
});
</script>

<?php
// Include footer
require_once __DIR__ . '/../../includes/footer.php';
?>