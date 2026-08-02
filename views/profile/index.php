<?php
// =============================================
// User Profile Page
// =============================================

// Load configuration
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../models/User.php';
require_once __DIR__ . '/../../models/Teacher.php';
require_once __DIR__ . '/../../models/Notification.php';
require_once __DIR__ . '/../../models/Duty.php';

// Initialize auth
$auth = new Auth();
if (!$auth->isLoggedIn()) {
    redirect(SITE_URL . '/views/auth/login.php');
}

$user = $auth->getUser();
$userId = $user['id'];
$role = $user['role'] ?? 'teacher';

// Set page variables
$pageTitle = 'My Profile';
$pageIcon = 'fas fa-user';
$breadcrumb = [
    ['label' => 'Dashboard', 'url' => SITE_URL . '/views/dashboard/' . ($role === 'teacher' ? 'teacher' : 'admin') . '.php'],
    ['label' => 'Profile', 'active' => true]
];

// Get teacher data if exists
$db = Database::getInstance();
$teacher = null;
$stmt = $db->prepare("
    SELECT t.*, d.name as department_name 
    FROM teachers t
    LEFT JOIN departments d ON t.department_id = d.id
    WHERE t.email = ? AND t.deleted_at IS NULL
");
$stmt->execute([$user['email']]);
$teacher = $stmt->fetch();

// Get teacher statistics
$teacherStats = [
    'total_duties' => 0,
    'completed_duties' => 0,
    'pending_duties' => 0,
    'swap_requests' => 0
];

if ($teacher) {
    $teacherId = $teacher['id'];
    
    // Total duties
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM duties WHERE teacher_id = ?");
    $stmt->execute([$teacherId]);
    $teacherStats['total_duties'] = $stmt->fetch()['count'] ?? 0;
    
    // Completed duties
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM duties WHERE teacher_id = ? AND status = ?");
    $stmt->execute([$teacherId, DUTY_COMPLETED]);
    $teacherStats['completed_duties'] = $stmt->fetch()['count'] ?? 0;
    
    // Pending duties
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM duties WHERE teacher_id = ? AND status = ?");
    $stmt->execute([$teacherId, DUTY_PENDING]);
    $teacherStats['pending_duties'] = $stmt->fetch()['count'] ?? 0;
    
    // Swap requests
    $stmt = $db->prepare("
        SELECT COUNT(*) as count FROM duty_swaps 
        WHERE requester_teacher_id = ? OR target_teacher_id = ?
    ");
    $stmt->execute([$teacherId, $teacherId]);
    $teacherStats['swap_requests'] = $stmt->fetch()['count'] ?? 0;
}

$error = '';
$success = '';
$activeTab = $_GET['tab'] ?? 'account';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!CSRF::verifyToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $action = $_POST['action'];
        
        switch ($action) {
            case 'update_profile':
                $fullName = sanitizeInput($_POST['full_name'] ?? '');
                $email = sanitizeInput($_POST['email'] ?? '');
                
                if (empty($fullName) || empty($email)) {
                    $error = 'Please fill in all required fields.';
                } elseif (!isValidEmail($email)) {
                    $error = 'Please enter a valid email address.';
                } else {
                    $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                    $stmt->execute([$email, $userId]);
                    if ($stmt->fetch()) {
                        $error = 'Email already in use by another account.';
                    } else {
                        $userModel = new User($userId);
                        if ($userModel->update(['full_name' => $fullName, 'email' => $email])) {
                            $success = 'Profile updated successfully!';
                            $user = $auth->getUser();
                        } else {
                            $error = 'Failed to update profile.';
                        }
                    }
                }
                break;
                
            case 'update_password':
                $currentPassword = $_POST['current_password'] ?? '';
                $newPassword = $_POST['new_password'] ?? '';
                $confirmPassword = $_POST['confirm_password'] ?? '';
                
                if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
                    $error = 'Please fill in all password fields.';
                } elseif ($newPassword !== $confirmPassword) {
                    $error = 'New passwords do not match.';
                } elseif (strlen($newPassword) < PASSWORD_MIN_LENGTH) {
                    $error = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters.';
                } elseif (!password_verify($currentPassword, $user['password'])) {
                    $error = 'Current password is incorrect.';
                } else {
                    $userModel = new User($userId);
                    if ($userModel->update(['password' => $newPassword])) {
                        $success = 'Password updated successfully!';
                    } else {
                        $error = 'Failed to update password.';
                    }
                }
                break;
                
            case 'update_teacher':
                if ($teacher) {
                    $firstName = sanitizeInput($_POST['first_name'] ?? '');
                    $lastName = sanitizeInput($_POST['last_name'] ?? '');
                    $phone = sanitizeInput($_POST['phone_primary'] ?? '');
                    $address = sanitizeInput($_POST['address'] ?? '');
                    $bio = sanitizeInput($_POST['bio'] ?? '');
                    
                    if (empty($firstName) || empty($lastName)) {
                        $error = 'Please fill in all required teacher fields.';
                    } else {
                        $teacherModel = new Teacher($teacher['id']);
                        $updateData = [
                            'first_name' => $firstName,
                            'last_name' => $lastName,
                            'phone_primary' => $phone,
                            'current_address' => $address,
                            'bio' => $bio
                        ];
                        if ($teacherModel->update($updateData)) {
                            $success = 'Teacher profile updated successfully!';
                            $stmt = $db->prepare("
                                SELECT t.*, d.name as department_name 
                                FROM teachers t
                                LEFT JOIN departments d ON t.department_id = d.id
                                WHERE t.email = ? AND t.deleted_at IS NULL
                            ");
                            $stmt->execute([$user['email']]);
                            $teacher = $stmt->fetch();
                        } else {
                            $error = 'Failed to update teacher profile.';
                        }
                    }
                }
                break;
                
            case 'upload_photo':
                if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
                    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                    $maxSize = 5 * 1024 * 1024; // 5MB
                    
                    if (!in_array($_FILES['profile_photo']['type'], $allowedTypes)) {
                        $error = 'Invalid file type. Please upload JPEG, PNG, GIF, or WEBP.';
                    } elseif ($_FILES['profile_photo']['size'] > $maxSize) {
                        $error = 'File too large. Maximum size is 5MB.';
                    } else {
                        $uploadDir = UPLOAD_PATH . 'profiles/';
                        if (!is_dir($uploadDir)) {
                            mkdir($uploadDir, 0777, true);
                        }
                        
                        $extension = pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION);
                        $filename = 'profile_' . $userId . '_' . time() . '.' . $extension;
                        $targetPath = $uploadDir . $filename;
                        
                        if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $targetPath)) {
                            // Update teacher record
                            $stmt = $db->prepare("UPDATE teachers SET profile_photo = ? WHERE id = ?");
                            $stmt->execute([$filename, $teacher['id']]);
                            $success = 'Profile photo updated successfully!';
                            $teacher['profile_photo'] = $filename;
                        } else {
                            $error = 'Failed to upload photo.';
                        }
                    }
                } else {
                    $error = 'Please select a file to upload.';
                }
                break;
        }
    }
}

// Get notification count
$unreadCount = Notification::getUnreadCount($userId);

// Include header
require_once __DIR__ . '/../../includes/header.php';
?>

<!-- Page Styles -->
<style>
    .profile-avatar-wrapper {
        position: relative;
        display: inline-block;
        margin: 0 auto 15px;
    }
    
    .profile-avatar {
        width: 130px;
        height: 130px;
        border-radius: 50%;
        background: var(--accent-gradient);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 48px;
        color: var(--primary);
        font-weight: 700;
        border: 4px solid var(--bg-white);
        box-shadow: var(--shadow);
        background-size: cover;
        background-position: center;
        transition: var(--transition);
    }
    
    .profile-avatar:hover {
        transform: scale(1.02);
        box-shadow: var(--shadow-lg);
    }
    
    .profile-avatar-upload {
        position: absolute;
        bottom: 5px;
        right: 5px;
        background: var(--accent);
        color: var(--primary);
        width: 36px;
        height: 36px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        border: 2px solid var(--bg-white);
        transition: var(--transition);
        box-shadow: var(--shadow);
    }
    
    .profile-avatar-upload:hover {
        transform: scale(1.1);
        background: var(--accent-light);
    }
    
    .profile-avatar-upload input[type="file"] {
        display: none;
    }
    
    .profile-sidebar {
        text-align: center;
    }
    
    .profile-sidebar .name {
        font-size: 1.25rem;
        font-weight: 600;
        margin-bottom: 2px;
    }
    
    .profile-sidebar .role {
        color: var(--text-muted);
        font-size: 0.9rem;
    }
    
    .profile-sidebar .email {
        color: var(--text-light);
        font-size: 0.85rem;
        margin-top: 5px;
    }
    
    .profile-stats {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 10px;
        margin-top: 20px;
    }
    
    .profile-stats .stat-item {
        background: var(--bg-light);
        padding: 10px;
        border-radius: var(--radius);
        text-align: center;
    }
    
    .profile-stats .stat-item .number {
        font-size: 1.2rem;
        font-weight: 700;
        display: block;
        color: var(--primary);
    }
    
    .profile-stats .stat-item .label {
        font-size: 0.7rem;
        color: var(--text-muted);
    }
    
    .tab-content {
        padding-top: 20px;
    }
    
    .nav-tabs .nav-link {
        color: var(--text-muted);
        font-weight: 500;
        border: none;
        padding: 10px 20px;
        transition: var(--transition);
    }
    
    .nav-tabs .nav-link:hover {
        color: var(--text-dark);
        background: var(--bg-light);
    }
    
    .nav-tabs .nav-link.active {
        color: var(--primary);
        border-bottom: 2px solid var(--accent);
        background: transparent;
    }
    
    .form-section-title {
        font-size: 0.9rem;
        font-weight: 600;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-bottom: 15px;
        padding-bottom: 8px;
        border-bottom: 1px solid var(--border-color);
    }
    
    .profile-bio {
        color: var(--text-muted);
        font-size: 0.9rem;
        margin-top: 5px;
    }
    
    @media (max-width: 768px) {
        .profile-stats {
            grid-template-columns: repeat(3, 1fr);
            gap: 5px;
        }
        .profile-stats .stat-item .number {
            font-size: 1rem;
        }
        .nav-tabs .nav-link {
            padding: 8px 12px;
            font-size: 0.85rem;
        }
    }
</style>

<div class="row">
    <!-- Profile Sidebar -->
    <div class="col-md-4">
        <div class="card">
            <div class="card-body profile-sidebar">
                <!-- Profile Photo -->
                <div class="profile-avatar-wrapper">
                    <div class="profile-avatar" 
                         style="background-image: url('<?php echo $teacher && $teacher['profile_photo'] ? SITE_URL . '/uploads/profiles/' . $teacher['profile_photo'] : 'none'; ?>');">
                        <?php if (!$teacher || !$teacher['profile_photo']): ?>
                            <?php echo substr($user['full_name'], 0, 1); ?>
                        <?php endif; ?>
                    </div>
                    <?php if ($teacher): ?>
                        <div class="profile-avatar-upload" title="Upload Profile Photo">
                            <i class="fas fa-camera"></i>
                            <form method="POST" enctype="multipart/form-data" id="photoUploadForm">
                                <?php echo CSRF::getTokenField(); ?>
                                <input type="hidden" name="action" value="upload_photo">
                                <input type="file" name="profile_photo" accept="image/*" 
                                       onchange="document.getElementById('photoUploadForm').submit();">
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="name"><?php echo htmlspecialchars($user['full_name']); ?></div>
                <div class="role">
                    <span class="badge bg-<?php echo $role === 'super_admin' ? 'danger' : ($role === 'admin' ? 'warning' : 'info'); ?>">
                        <?php echo ucfirst(str_replace('_', ' ', $role)); ?>
                    </span>
                </div>
                <div class="email">
                    <i class="fas fa-envelope me-1"></i> <?php echo htmlspecialchars($user['email']); ?>
                </div>
                
                <?php if ($teacher): ?>
                    <div class="mt-3">
                        <span class="badge bg-primary"><?php echo htmlspecialchars($teacher['teacher_id']); ?></span>
                        <span class="badge bg-secondary"><?php echo htmlspecialchars($teacher['department_name'] ?? 'No Department'); ?></span>
                    </div>
                    <?php if (!empty($teacher['bio'])): ?>
                        <div class="profile-bio mt-2">
                            <i class="fas fa-quote-left me-1 text-muted"></i>
                            <?php echo nl2br(htmlspecialchars(substr($teacher['bio'], 0, 100))); ?>
                            <?php if (strlen($teacher['bio']) > 100): ?>...<?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
                
                <div class="profile-stats">
                    <div class="stat-item">
                        <span class="number"><?php echo $unreadCount; ?></span>
                        <span class="label">Notifications</span>
                    </div>
                    <div class="stat-item">
                        <span class="number"><?php echo $teacherStats['total_duties']; ?></span>
                        <span class="label">Duties</span>
                    </div>
                    <div class="stat-item">
                        <span class="number"><?php echo $teacherStats['completed_duties']; ?></span>
                        <span class="label">Completed</span>
                    </div>
                </div>
                
                <hr>
                <div class="text-muted small">
                    <i class="far fa-clock me-1"></i> Member since: <?php echo formatDate($user['created_at']); ?>
                    <br>
                    <i class="fas fa-circle me-1" style="color: var(--success);"></i> Status: Active
                    <?php if ($teacher): ?>
                        <br>
                        <i class="fas fa-calendar-alt me-1"></i> Experience: <?php echo $teacher['experience_years'] ?? 0; ?> years
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Profile Content -->
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <ul class="nav nav-tabs card-header-tabs">
                    <li class="nav-item">
                        <a class="nav-link <?php echo $activeTab === 'account' ? 'active' : ''; ?>" 
                           data-bs-toggle="tab" href="#account">
                            <i class="fas fa-user-cog me-1"></i> Account
                        </a>
                    </li>
                    <?php if ($teacher): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $activeTab === 'teacher' ? 'active' : ''; ?>" 
                               data-bs-toggle="tab" href="#teacher">
                                <i class="fas fa-user-graduate me-1"></i> Teacher
                            </a>
                        </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $activeTab === 'security' ? 'active' : ''; ?>" 
                           data-bs-toggle="tab" href="#security">
                            <i class="fas fa-shield-alt me-1"></i> Security
                        </a>
                    </li>
                </ul>
            </div>
            <div class="card-body">
                <div class="tab-content">
                    <!-- Account Tab -->
                    <div class="tab-pane fade <?php echo $activeTab === 'account' ? 'show active' : ''; ?>" id="account">
                        <form method="POST">
                            <?php echo CSRF::getTokenField(); ?>
                            <input type="hidden" name="action" value="update_profile">
                            
                            <div class="form-section-title">Account Information</div>
                            
                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label class="form-label">Full Name *</label>
                                    <input type="text" name="full_name" class="form-control" 
                                           value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                                </div>
                                <div class="col-md-12 mb-3">
                                    <label class="form-label">Email Address *</label>
                                    <input type="email" name="email" class="form-control" 
                                           value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                </div>
                                <div class="col-md-12 mb-3">
                                    <label class="form-label">Role</label>
                                    <input type="text" class="form-control" 
                                           value="<?php echo ucfirst($role); ?>" disabled>
                                    <small class="text-muted">Role cannot be changed from this page.</small>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-accent">
                                <i class="fas fa-save me-2"></i> Update Account
                            </button>
                        </form>
                    </div>
                    
                    <!-- Teacher Tab -->
                    <?php if ($teacher): ?>
                        <div class="tab-pane fade <?php echo $activeTab === 'teacher' ? 'show active' : ''; ?>" id="teacher">
                            <form method="POST">
                                <?php echo CSRF::getTokenField(); ?>
                                <input type="hidden" name="action" value="update_teacher">
                                
                                <div class="form-section-title">Teacher Information</div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">First Name *</label>
                                        <input type="text" name="first_name" class="form-control" 
                                               value="<?php echo htmlspecialchars($teacher['first_name'] ?? ''); ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Last Name *</label>
                                        <input type="text" name="last_name" class="form-control" 
                                               value="<?php echo htmlspecialchars($teacher['last_name'] ?? ''); ?>" required>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Teacher ID</label>
                                        <input type="text" class="form-control" 
                                               value="<?php echo htmlspecialchars($teacher['teacher_id'] ?? ''); ?>" disabled>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Staff Number</label>
                                        <input type="text" class="form-control" 
                                               value="<?php echo htmlspecialchars($teacher['staff_number'] ?? ''); ?>" disabled>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Phone Number</label>
                                        <input type="tel" name="phone_primary" class="form-control" 
                                               value="<?php echo htmlspecialchars($teacher['phone_primary'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Department</label>
                                        <input type="text" class="form-control" 
                                               value="<?php echo htmlspecialchars($teacher['department_name'] ?? 'Not Assigned'); ?>" disabled>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Address</label>
                                    <textarea name="address" class="form-control" rows="2"><?php echo htmlspecialchars($teacher['current_address'] ?? ''); ?></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Bio / About</label>
                                    <textarea name="bio" class="form-control" rows="3" 
                                              placeholder="Tell us about yourself..."><?php echo htmlspecialchars($teacher['bio'] ?? ''); ?></textarea>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Position</label>
                                        <input type="text" class="form-control" 
                                               value="<?php echo htmlspecialchars($teacher['position'] ?? 'Not Assigned'); ?>" disabled>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Status</label>
                                        <span class="badge bg-<?php echo getStatusBadgeColor($teacher['status']); ?> mt-2">
                                            <?php echo ucfirst($teacher['status']); ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-accent">
                                    <i class="fas fa-save me-2"></i> Update Teacher Profile
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Security Tab -->
                    <div class="tab-pane fade <?php echo $activeTab === 'security' ? 'show active' : ''; ?>" id="security">
                        <form method="POST">
                            <?php echo CSRF::getTokenField(); ?>
                            <input type="hidden" name="action" value="update_password">
                            
                            <div class="form-section-title">Change Password</div>
                            
                            <div class="mb-3">
                                <label class="form-label">Current Password *</label>
                                <div class="input-group">
                                    <input type="password" name="current_password" class="form-control" required>
                                    <button type="button" class="btn btn-outline-secondary" onclick="togglePassword(this)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">New Password *</label>
                                <div class="input-group">
                                    <input type="password" name="new_password" class="form-control" required minlength="<?php echo PASSWORD_MIN_LENGTH; ?>">
                                    <button type="button" class="btn btn-outline-secondary" onclick="togglePassword(this)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <small class="text-muted">Password must be at least <?php echo PASSWORD_MIN_LENGTH; ?> characters.</small>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Confirm New Password *</label>
                                <div class="input-group">
                                    <input type="password" name="confirm_password" class="form-control" required>
                                    <button type="button" class="btn btn-outline-secondary" onclick="togglePassword(this)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-accent">
                                <i class="fas fa-key me-2"></i> Change Password
                            </button>
                        </form>
                        
                        <hr>
                        
                        <div class="form-section-title">Session Management</div>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            You are currently logged in from this device. 
                            <a href="<?php echo SITE_URL; ?>/auth/logout.php" class="alert-link">Logout from all devices</a>
                        </div>
                        
                        <div class="form-section-title mt-3">Account Security</div>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i>
                            Your account is secure. 
                            <span class="badge bg-success ms-2">2FA Disabled</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Toggle password visibility
    function togglePassword(btn) {
        const input = btn.parentElement.querySelector('input');
        const icon = btn.querySelector('i');
        
        if (input.type === 'password') {
            input.type = 'text';
            icon.className = 'fas fa-eye-slash';
        } else {
            input.type = 'password';
            icon.className = 'fas fa-eye';
        }
    }
    
    // Auto-dismiss alerts
    setTimeout(() => {
        document.querySelectorAll('.alert-dismissible').forEach(el => {
            const closeBtn = el.querySelector('.btn-close');
            if (closeBtn) {
                setTimeout(() => closeBtn.click(), 5000);
            }
        });
    }, 1000);
    
    // Profile photo preview
    document.querySelector('input[name="profile_photo"]')?.addEventListener('change', function(e) {
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(event) {
                const avatar = document.querySelector('.profile-avatar');
                avatar.style.backgroundImage = 'url(' + event.target.result + ')';
                avatar.innerHTML = '';
            };
            reader.readAsDataURL(file);
        }
    });
</script>

<?php
// Include footer
require_once __DIR__ . '/../../includes/footer.php';
?>