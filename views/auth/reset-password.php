<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/security.php';

$auth = new Auth();

// Redirect if already logged in
if ($auth->isLoggedIn()) {
    redirect(SITE_URL . '/dashboard/');
}

// Get token from URL
$token = $_GET['token'] ?? '';

if (empty($token)) {
    redirect(SITE_URL . '/auth/login.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';
    
    // Validation
    $errors = [];
    
    if (empty($password)) {
        $errors[] = 'Password is required';
    } elseif (strlen($password) < PASSWORD_MIN_LENGTH) {
        $errors[] = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters';
    } elseif (!Validator::password($password)) {
        $requirements = [];
        if (PASSWORD_REQUIREMENTS['uppercase']) $requirements[] = 'uppercase letter';
        if (PASSWORD_REQUIREMENTS['lowercase']) $requirements[] = 'lowercase letter';
        if (PASSWORD_REQUIREMENTS['numbers']) $requirements[] = 'number';
        if (PASSWORD_REQUIREMENTS['special']) $requirements[] = 'special character';
        $errors[] = 'Password must contain at least one ' . implode(', ', $requirements);
    }
    
    if ($password !== $passwordConfirm) {
        $errors[] = 'Passwords do not match';
    }
    
    if (empty($errors)) {
        $result = $auth->resetPassword($token, $password);
        
        if ($result['success']) {
            $success = $result['message'];
            // Redirect to login after 3 seconds
            echo '<meta http-equiv="refresh" content="3;url=' . SITE_URL . '/auth/login.php">';
        } else {
            $error = $result['message'];
        }
    } else {
        $error = implode('<br>', $errors);
    }
}

$pageTitle = 'Reset Password';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .reset-container {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 40px;
            max-width: 450px;
            width: 100%;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .reset-logo {
            text-align: center;
            margin-bottom: 30px;
        }
        .reset-logo i {
            font-size: 48px;
            color: #ffd700;
        }
        .reset-logo h2 {
            color: #fff;
            font-weight: 600;
            margin-top: 10px;
        }
        .reset-logo p {
            color: rgba(255, 255, 255, 0.6);
        }
        .form-control {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #fff;
            padding: 12px 15px;
            border-radius: 10px;
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
        .btn-reset {
            background: #ffd700;
            color: #1a1a2e;
            font-weight: 600;
            padding: 12px;
            border-radius: 10px;
            border: none;
            width: 100%;
            transition: all 0.3s ease;
        }
        .btn-reset:hover {
            background: #ffed4a;
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(255, 215, 0, 0.3);
        }
        .btn-reset:disabled {
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
        .alert {
            border-radius: 10px;
            border: none;
        }
        .alert-danger {
            background: rgba(220, 53, 69, 0.2);
            border: 1px solid rgba(220, 53, 69, 0.3);
            color: #ff6b6b;
        }
        .alert-success {
            background: rgba(40, 167, 69, 0.2);
            border: 1px solid rgba(40, 167, 69, 0.3);
            color: #5cb85c;
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
        .input-group {
            position: relative;
        }
        .input-group .form-control {
            padding-right: 45px;
        }
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
    </style>
</head>
<body>
    <div class="reset-container">
        <div class="reset-logo">
            <i class="fas fa-tasks"></i>
            <h2>ADPS</h2>
            <p>Create New Password</p>
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
                <p class="text-muted">Redirecting to login...</p>
            </div>
        <?php else: ?>
            <form method="POST" action="" id="resetForm">
                <div class="mb-3">
                    <label for="password" class="form-label">
                        <i class="fas fa-lock me-2"></i> New Password
                    </label>
                    <div class="input-group">
                        <input type="password" class="form-control" id="password" name="password" 
                               placeholder="Enter new password" required minlength="<?php echo PASSWORD_MIN_LENGTH; ?>">
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
                </div>
                
                <button type="submit" class="btn-reset" id="resetBtn">
                    <i class="fas fa-key me-2"></i> Reset Password
                </button>
                
                <div class="text-center mt-3">
                    <a href="<?php echo SITE_URL; ?>/views/auth/login.php" class="text-link">
                        <i class="fas fa-arrow-left me-1"></i> Back to Login
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
        document.getElementById('resetForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password');
            const passwordConfirm = document.getElementById('password_confirm');
            const submitBtn = document.getElementById('resetBtn');
            
            let isValid = true;
            
            if (password.value !== passwordConfirm.value) {
                passwordConfirm.classList.add('is-invalid');
                isValid = false;
            } else {
                passwordConfirm.classList.remove('is-invalid');
                passwordConfirm.classList.add('is-valid');
            }
            
            if (!isValid) {
                e.preventDefault();
                return;
            }
            
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Resetting Password...';
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