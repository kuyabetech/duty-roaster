<?php
// =============================================
// Teacher Registration - Fixed
// =============================================

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../models/Teacher.php';

$auth = new Auth();

// Redirect if already logged in
if ($auth->isLoggedIn()) {
    redirect(SITE_URL . '/views/dashboard/' . ($auth->getUser()['role'] === 'teacher' ? 'teacher' : 'admin') . '.php');
}

$error = '';
$success = '';
$formData = [
    'username' => '',
    'email' => '',
    'full_name' => '',
    'phone' => '',
    'role' => 'teacher'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::verifyToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        // Sanitize and validate input
        $formData['username'] = sanitizeInput($_POST['username'] ?? '');
        $formData['email'] = sanitizeInput($_POST['email'] ?? '');
        $formData['full_name'] = sanitizeInput($_POST['full_name'] ?? '');
        $formData['phone'] = sanitizeInput($_POST['phone'] ?? '');
        $formData['role'] = sanitizeInput($_POST['role'] ?? 'teacher');
        $password = $_POST['password'] ?? '';
        $passwordConfirm = $_POST['password_confirm'] ?? '';
        
        // Validation
        $errors = [];
        
        if (empty($formData['username'])) {
            $errors[] = 'Username is required';
        } elseif (strlen($formData['username']) < 3) {
            $errors[] = 'Username must be at least 3 characters';
        } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $formData['username'])) {
            $errors[] = 'Username can only contain letters, numbers, and underscores';
        }
        
        if (empty($formData['email'])) {
            $errors[] = 'Email is required';
        } elseif (!isValidEmail($formData['email'])) {
            $errors[] = 'Please enter a valid email address';
        }
        
        if (empty($formData['full_name'])) {
            $errors[] = 'Full name is required';
        }
        
        if (empty($formData['phone'])) {
            $errors[] = 'Phone number is required';
        }
        
        if (empty($password)) {
            $errors[] = 'Password is required';
        } elseif (strlen($password) < PASSWORD_MIN_LENGTH) {
            $errors[] = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters';
        } else {
            // Check password strength without Validator class
            $hasUpper = preg_match('/[A-Z]/', $password);
            $hasLower = preg_match('/[a-z]/', $password);
            $hasNumber = preg_match('/[0-9]/', $password);
            $hasSpecial = preg_match('/[^a-zA-Z0-9]/', $password);
            
            if (PASSWORD_REQUIREMENTS['uppercase'] && !$hasUpper) {
                $errors[] = 'Password must contain at least one uppercase letter';
            }
            if (PASSWORD_REQUIREMENTS['lowercase'] && !$hasLower) {
                $errors[] = 'Password must contain at least one lowercase letter';
            }
            if (PASSWORD_REQUIREMENTS['numbers'] && !$hasNumber) {
                $errors[] = 'Password must contain at least one number';
            }
            if (PASSWORD_REQUIREMENTS['special'] && !$hasSpecial) {
                $errors[] = 'Password must contain at least one special character';
            }
        }
        
        if ($password !== $passwordConfirm) {
            $errors[] = 'Passwords do not match';
        }
        
        if (empty($errors)) {
            // Register user
            $result = $auth->register([
                'username' => $formData['username'],
                'email' => $formData['email'],
                'password' => $password,
                'full_name' => $formData['full_name'],
                'role' => 'teacher'
            ]);
            
            if ($result['success']) {
                // Create teacher record
                $nameParts = explode(' ', $formData['full_name']);
                $firstName = $nameParts[0] ?? '';
                $lastName = implode(' ', array_slice($nameParts, 1));
                
                $teacherData = [
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'gender' => 'other',
                    'email' => $formData['email'],
                    'phone_primary' => $formData['phone'],
                    'status' => 'inactive' // Teacher needs admin approval
                ];
                
                $teacher = new Teacher();
                $teacher->create($teacherData);
                
                $success = 'Registration successful! Your account has been created. Please wait for admin approval.';
                // Clear form data
                $formData = ['username' => '', 'email' => '', 'full_name' => '', 'phone' => '', 'role' => 'teacher'];
            } else {
                $error = $result['message'];
            }
        } else {
            $error = implode('<br>', $errors);
        }
    }
}

$pageTitle = 'Teacher Registration';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - <?php echo SITE_NAME; ?></title>
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome 6 -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <style>
        body {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 20px;
        }
        .register-container {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 40px;
            max-width: 520px;
            width: 100%;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .register-logo {
            text-align: center;
            margin-bottom: 30px;
        }
        .register-logo i {
            font-size: 48px;
            color: #ffd700;
        }
        .register-logo h2 {
            color: #fff;
            font-weight: 600;
            margin-top: 10px;
        }
        .register-logo p {
            color: rgba(255, 255, 255, 0.6);
        }
        .register-logo .badge {
            background: #ffd700;
            color: #1a1a2e;
            padding: 4px 15px;
            border-radius: 20px;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .form-control {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #fff;
            padding: 12px 15px;
            border-radius: 10px;
            transition: all 0.3s ease;
        }
        .form-control:focus {
            background: rgba(255, 255, 255, 0.15);
            border-color: #ffd700;
            box-shadow: 0 0 0 0.25rem rgba(255, 215, 0, 0.25);
            color: #fff;
        }
        .form-control::placeholder {
            color: rgba(255, 255, 255, 0.5);
        }
        .form-control.is-invalid {
            border-color: #dc3545;
            background: rgba(220, 53, 69, 0.1);
        }
        .form-control.is-valid {
            border-color: #28a745;
            background: rgba(40, 167, 69, 0.1);
        }
        .form-label {
            color: rgba(255, 255, 255, 0.8);
            font-weight: 500;
        }
        .form-text {
            color: rgba(255, 255, 255, 0.5);
            font-size: 12px;
        }
        .btn-register {
            background: #ffd700;
            color: #1a1a2e;
            font-weight: 600;
            padding: 12px;
            border-radius: 10px;
            border: none;
            width: 100%;
            transition: all 0.3s ease;
        }
        .btn-register:hover {
            background: #ffed4a;
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(255, 215, 0, 0.3);
        }
        .btn-register:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        .text-link {
            color: #ffd700;
            text-decoration: none;
            transition: color 0.3s ease;
        }
        .text-link:hover {
            color: #ffed4a;
            text-decoration: underline;
        }
        .error-message {
            background: rgba(220, 53, 69, 0.2);
            border: 1px solid rgba(220, 53, 69, 0.3);
            color: #ff6b6b;
            padding: 10px 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .success-message {
            background: rgba(40, 167, 69, 0.2);
            border: 1px solid rgba(40, 167, 69, 0.3);
            color: #5cb85c;
            padding: 10px 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .password-strength {
            height: 4px;
            border-radius: 2px;
            margin-top: 5px;
            background: #e9ecef;
            transition: all 0.3s ease;
        }
        .password-strength.weak { background: #dc3545; width: 25%; }
        .password-strength.medium { background: #ffc107; width: 50%; }
        .password-strength.strong { background: #28a745; width: 75%; }
        .password-strength.very-strong { background: #28a745; width: 100%; }
        .password-strength-text {
            font-size: 12px;
            margin-top: 3px;
        }
        .password-strength-text.weak { color: #dc3545; }
        .password-strength-text.medium { color: #ffc107; }
        .password-strength-text.strong { color: #28a745; }
        .password-strength-text.very-strong { color: #28a745; }
        .toggle-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: rgba(255, 255, 255, 0.5);
            z-index: 10;
        }
        .toggle-password:hover {
            color: rgba(255, 255, 255, 0.8);
        }
        .form-check-label {
            color: rgba(255, 255, 255, 0.7);
        }
        .form-check-input:checked {
            background-color: #ffd700;
            border-color: #ffd700;
        }
        .input-group {
            position: relative;
        }
        .input-group .form-control {
            padding-right: 45px;
        }
        .alert {
            border-radius: 10px;
            border: none;
        }
        .info-box {
            background: rgba(255, 215, 0, 0.1);
            border-left: 3px solid #ffd700;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .info-box p {
            color: rgba(255, 255, 255, 0.7);
            margin: 0;
            font-size: 0.85rem;
        }
        .info-box i {
            color: #ffd700;
            margin-right: 8px;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .alert {
            animation: fadeIn 0.3s ease;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-logo">
            <i class="fas fa-tasks"></i>
            <h2>ADPS</h2>
            <p>Automated Duty Processing System</p>
            <span class="badge">Teacher Registration</span>
        </div>
        
        <div class="info-box">
            <p><i class="fas fa-info-circle"></i> Create your account to get started. After registration, an admin will activate your account.</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle me-2"></i> <?php echo $success; ?>
            </div>
            <div class="text-center mt-3">
                <a href="<?php echo SITE_URL; ?>/views/auth/login.php" class="btn btn-outline-light">
                    <i class="fas fa-sign-in-alt me-2"></i> Login Now
                </a>
            </div>
        <?php else: ?>
            <form method="POST" action="" id="registerForm" novalidate>
                <?php echo CSRF::getTokenField(); ?>
                
                <div class="mb-3">
                    <label for="full_name" class="form-label">
                        <i class="fas fa-user me-2"></i> Full Name
                    </label>
                    <input type="text" class="form-control" id="full_name" name="full_name" 
                           placeholder="Enter your full name" 
                           value="<?php echo htmlspecialchars($formData['full_name']); ?>" 
                           required autofocus>
                    <div class="invalid-feedback">Please enter your full name</div>
                </div>
                
                <div class="mb-3">
                    <label for="username" class="form-label">
                        <i class="fas fa-user-tag me-2"></i> Username
                    </label>
                    <input type="text" class="form-control" id="username" name="username" 
                           placeholder="Choose a username (min 3 characters)" 
                           value="<?php echo htmlspecialchars($formData['username']); ?>" 
                           required minlength="3" pattern="[a-zA-Z0-9_]+">
                    <div class="invalid-feedback">Username must be at least 3 characters and contain only letters, numbers, and underscores</div>
                    <div class="form-text">Username must be unique and can contain letters, numbers, and underscores</div>
                </div>
                
                <div class="mb-3">
                    <label for="email" class="form-label">
                        <i class="fas fa-envelope me-2"></i> Email Address
                    </label>
                    <input type="email" class="form-control" id="email" name="email" 
                           placeholder="Enter your email address" 
                           value="<?php echo htmlspecialchars($formData['email']); ?>" 
                           required>
                    <div class="invalid-feedback">Please enter a valid email address</div>
                    <div class="form-text">We'll send a verification link to this email</div>
                </div>
                
                <div class="mb-3">
                    <label for="phone" class="form-label">
                        <i class="fas fa-phone me-2"></i> Phone Number
                    </label>
                    <input type="tel" class="form-control" id="phone" name="phone" 
                           placeholder="Enter your phone number" 
                           value="<?php echo htmlspecialchars($formData['phone']); ?>" 
                           required>
                    <div class="invalid-feedback">Please enter your phone number</div>
                </div>
                
                <div class="mb-3">
                    <label for="password" class="form-label">
                        <i class="fas fa-lock me-2"></i> Password
                    </label>
                    <div class="input-group">
                        <input type="password" class="form-control" id="password" name="password" 
                               placeholder="Create a password" required minlength="<?php echo PASSWORD_MIN_LENGTH; ?>">
                        <span class="toggle-password" onclick="togglePassword('password')">
                            <i class="fas fa-eye" id="password-icon"></i>
                        </span>
                    </div>
                    <div class="password-strength" id="passwordStrength"></div>
                    <div class="password-strength-text" id="passwordStrengthText"></div>
                    <div class="form-text">Password must be at least <?php echo PASSWORD_MIN_LENGTH; ?> characters</div>
                </div>
                
                <div class="mb-3">
                    <label for="password_confirm" class="form-label">
                        <i class="fas fa-check-circle me-2"></i> Confirm Password
                    </label>
                    <div class="input-group">
                        <input type="password" class="form-control" id="password_confirm" name="password_confirm" 
                               placeholder="Confirm your password" required>
                        <span class="toggle-password" onclick="togglePassword('password_confirm')">
                            <i class="fas fa-eye" id="password_confirm-icon"></i>
                        </span>
                    </div>
                    <div class="invalid-feedback">Passwords do not match</div>
                </div>
                
                <div class="mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="terms" name="terms" required>
                        <label class="form-check-label" for="terms">
                            I agree to the <a href="#" class="text-link">Terms of Service</a> and 
                            <a href="#" class="text-link">Privacy Policy</a>
                        </label>
                        <div class="invalid-feedback">You must agree to the terms</div>
                    </div>
                </div>
                
                <button type="submit" class="btn-register" id="registerBtn">
                    <i class="fas fa-user-plus me-2"></i> Create Account
                </button>
                
                <div class="text-center mt-3">
                    <span style="color: rgba(255, 255, 255, 0.5);">Already have an account?</span>
                    <a href="<?php echo SITE_URL; ?>/views/auth/login.php" class="text-link">
                        Sign In
                    </a>
                </div>
            </form>
        <?php endif; ?>
    </div>
    
    <script>
        // =============================================
        // Password Strength Checker
        // =============================================
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const strengthBar = document.getElementById('passwordStrength');
            const strengthText = document.getElementById('passwordStrengthText');
            
            if (password.length === 0) {
                strengthBar.className = 'password-strength';
                strengthText.textContent = '';
                return;
            }
            
            let strength = 0;
            const requirements = {
                length: password.length >= <?php echo PASSWORD_MIN_LENGTH; ?>,
                uppercase: /[A-Z]/.test(password),
                lowercase: /[a-z]/.test(password),
                numbers: /[0-9]/.test(password),
                special: /[^a-zA-Z0-9]/.test(password)
            };
            
            if (requirements.length) strength++;
            if (requirements.uppercase) strength++;
            if (requirements.lowercase) strength++;
            if (requirements.numbers) strength++;
            if (requirements.special) strength++;
            
            let level, text;
            if (strength <= 1) {
                level = 'weak';
                text = 'Weak password';
            } else if (strength <= 2) {
                level = 'weak';
                text = 'Weak password';
            } else if (strength <= 3) {
                level = 'medium';
                text = 'Medium password';
            } else if (strength <= 4) {
                level = 'strong';
                text = 'Strong password';
            } else {
                level = 'very-strong';
                text = 'Very strong password';
            }
            
            strengthBar.className = 'password-strength ' + level;
            strengthText.textContent = text;
            strengthText.className = 'password-strength-text ' + level;
        });
        
        // =============================================
        // Toggle Password Visibility
        // =============================================
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const icon = document.getElementById(fieldId + '-icon');
            
            if (field.type === 'password') {
                field.type = 'text';
                icon.className = 'fas fa-eye-slash';
            } else {
                field.type = 'password';
                icon.className = 'fas fa-eye';
            }
        }
        
        // =============================================
        // Form Validation
        // =============================================
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password');
            const passwordConfirm = document.getElementById('password_confirm');
            const terms = document.getElementById('terms');
            const submitBtn = document.getElementById('registerBtn');
            
            let isValid = true;
            
            // Validate password match
            if (password.value !== passwordConfirm.value) {
                passwordConfirm.classList.add('is-invalid');
                isValid = false;
            } else {
                passwordConfirm.classList.remove('is-invalid');
            }
            
            // Validate terms
            if (!terms.checked) {
                terms.classList.add('is-invalid');
                isValid = false;
            } else {
                terms.classList.remove('is-invalid');
            }
            
            if (!isValid) {
                e.preventDefault();
                return;
            }
            
            // Disable button and show loading
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Creating Account...';
        });
        
        // =============================================
        // Real-time Validation
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
        
        // Email validation
        document.getElementById('email').addEventListener('input', function() {
            const email = this.value;
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (emailRegex.test(email)) {
                this.classList.remove('is-invalid');
                this.classList.add('is-valid');
            } else if (email.length > 0) {
                this.classList.remove('is-valid');
                this.classList.add('is-invalid');
            } else {
                this.classList.remove('is-invalid', 'is-valid');
            }
        });
        
        // Password confirmation validation
        document.getElementById('password_confirm').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            if (this.value === password && this.value.length > 0) {
                this.classList.remove('is-invalid');
                this.classList.add('is-valid');
            } else if (this.value.length > 0) {
                this.classList.remove('is-valid');
                this.classList.add('is-invalid');
            } else {
                this.classList.remove('is-invalid', 'is-valid');
            }
        });
    </script>
</body>
</html>