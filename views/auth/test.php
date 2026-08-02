<?php
// =============================================
// Login Page - Fixed Version
// =============================================

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);


// Check if files exist before including
$configFile = __DIR__ . '/../../config/config.php';
$functionsFile = __DIR__ . '/../../includes/functions.php';
$authFile = __DIR__ . '/../../includes/auth.php';
$securityFile = __DIR__ . '/../../includes/security.php';

echo "<!-- Debug: Checking files -->\n";
echo "<!-- config: " . (file_exists($configFile) ? 'Found' : 'Not Found') . " -->\n";
echo "<!-- functions: " . (file_exists($functionsFile) ? 'Found' : 'Not Found') . " -->\n";
echo "<!-- auth: " . (file_exists($authFile) ? 'Found' : 'Not Found') . " -->\n";
echo "<!-- security: " . (file_exists($securityFile) ? 'Found' : 'Not Found') . " -->\n";

// Try to include files with error handling
try {
    if (file_exists($configFile)) {
        require_once $configFile;
        echo "<!-- Config loaded -->\n";
    } else {
        die("Config file not found: " . $configFile);
    }
    
    if (file_exists($functionsFile)) {
        require_once $functionsFile;
        echo "<!-- Functions loaded -->\n";
    } else {
        die("Functions file not found: " . $functionsFile);
    }
    
    if (file_exists($authFile)) {
        require_once $authFile;
        echo "<!-- Auth loaded -->\n";
    } else {
        die("Auth file not found: " . $authFile);
    }
    
    if (file_exists($securityFile)) {
        require_once $securityFile;
        echo "<!-- Security loaded -->\n";
    } else {
        die("Security file not found: " . $securityFile);
    }
} catch (Exception $e) {
    die("Error loading files: " . $e->getMessage());
}

$auth = new Auth();

// Redirect if already logged in
if ($auth->isLoggedIn()) {
    header('Location: ' . SITE_URL . '/dashboard/');
    exit;
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Verify CSRF token
        if (!CSRF::verifyToken($_POST['csrf_token'] ?? '')) {
            $error = 'Invalid request. Please try again.';
        } else {
            $email = sanitizeInput($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $remember = isset($_POST['remember']);
            
            if (empty($email) || empty($password)) {
                $error = 'Please fill in all fields.';
            } else {
                $result = $auth->login($email, $password, $remember);
                
                if ($result['success']) {
                    header('Location: ' . SITE_URL . '/dashboard/');
                    exit;
                } else {
                    $error = $result['message'];
                }
            }
        }
    } catch (Exception $e) {
        $error = 'System error: ' . $e->getMessage();
        error_log("Login error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-container {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 40px;
            max-width: 420px;
            width: 100%;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .login-logo {
            text-align: center;
            margin-bottom: 30px;
        }
        .login-logo i {
            font-size: 48px;
            color: #ffd700;
        }
        .login-logo h2 {
            color: #fff;
            font-weight: 600;
            margin-top: 10px;
        }
        .login-logo p {
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
        .form-label {
            color: rgba(255, 255, 255, 0.8);
            font-weight: 500;
        }
        .btn-login {
            background: #ffd700;
            color: #1a1a2e;
            font-weight: 600;
            padding: 12px;
            border-radius: 10px;
            border: none;
            width: 100%;
            transition: all 0.3s ease;
        }
        .btn-login:hover {
            background: #ffed4a;
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(255, 215, 0, 0.3);
        }
        .text-link {
            color: #ffd700;
            text-decoration: none;
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
        .form-check-label {
            color: rgba(255, 255, 255, 0.7);
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-logo">
            <i class="fas fa-tasks"></i>
            <h2>ADPS</h2>
            <p>Automated Duty Processing System</p>
        </div>
        
        <?php if ($error): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <?php if (class_exists('CSRF')): ?>
                <?php echo CSRF::getTokenField(); ?>
            <?php else: ?>
                <input type="hidden" name="csrf_token" value="<?php echo bin2hex(random_bytes(32)); ?>">
            <?php endif; ?>
            
            <div class="mb-3">
                <label for="email" class="form-label">
                    <i class="fas fa-envelope me-2"></i> Email Address
                </label>
                <input type="email" class="form-control" id="email" name="email" 
                       placeholder="Enter your email" value="<?php echo htmlspecialchars($email); ?>" required>
            </div>
            
            <div class="mb-3">
                <label for="password" class="form-label">
                    <i class="fas fa-lock me-2"></i> Password
                </label>
                <input type="password" class="form-control" id="password" name="password" 
                       placeholder="Enter your password" required>
            </div>
            
            <div class="mb-3 d-flex justify-content-between align-items-center">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="remember" name="remember">
                    <label class="form-check-label" for="remember">
                        Remember me
                    </label>
                </div>
                <a href="#" class="text-link">
                    Forgot Password?
                </a>
            </div>
            
            <button type="submit" class="btn-login">
                <i class="fas fa-sign-in-alt me-2"></i> Login
            </button>
            
            <div class="text-center mt-3">
                <span style="color: rgba(255, 255, 255, 0.5);">Don't have an account?</span>
                <a href="#" class="text-link">
                    Register
                </a>
            </div>
        </form>
        
        <!-- Debug Info (Only visible to admins) -->
        <div style="margin-top: 20px; padding: 10px; background: rgba(255,255,255,0.05); border-radius: 10px; font-size: 11px; color: rgba(255,255,255,0.3); text-align: center;">
            <small>Debug: <?php echo date('Y-m-d H:i:s'); ?></small>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>