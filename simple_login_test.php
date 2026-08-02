<?php
// =============================================
// Simple Login - Works Without Framework
// =============================================

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Database connection
$host = 'localhost';
$dbname = 'adps';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $dbConnected = true;
} catch (PDOException $e) {
    $dbConnected = false;
    $dbError = $e->getMessage();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$dbConnected) {
        $error = 'Database connection failed. Please check your configuration.';
    } else {
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        
        if (empty($email) || empty($password)) {
            $error = 'Please enter email and password.';
        } else {
            try {
                $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch();
                
                if ($user && password_verify($password, $user['password'])) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_role'] = $user['role'];
                    $_SESSION['user_name'] = $user['full_name'];
                    echo "<script>alert('Login successful! Redirecting...'); window.location.href='dashboard.php';</script>";
                    exit;
                } else {
                    $error = 'Invalid email or password.';
                }
            } catch (Exception $e) {
                $error = 'Login error: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simple Login</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-box {
            background: rgba(255, 255, 255, 0.95);
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.5);
            width: 100%;
            max-width: 400px;
        }
        .login-box h1 {
            text-align: center;
            color: #1a1a2e;
            margin-bottom: 10px;
        }
        .login-box .subtitle {
            text-align: center;
            color: #666;
            margin-bottom: 30px;
        }
        .login-box .logo {
            text-align: center;
            font-size: 48px;
            color: #ffd700;
            margin-bottom: 10px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #333;
            font-weight: 500;
        }
        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 10px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        .form-group input:focus {
            outline: none;
            border-color: #ffd700;
            box-shadow: 0 0 0 3px rgba(255, 215, 0, 0.2);
        }
        .btn-login {
            width: 100%;
            padding: 12px;
            background: #ffd700;
            color: #1a1a2e;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn-login:hover {
            background: #ffed4a;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 215, 0, 0.3);
        }
        .error {
            background: #fee;
            color: #c00;
            padding: 10px 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #c00;
        }
        .success {
            background: #efe;
            color: #0a0;
            padding: 10px 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #0a0;
        }
        .info {
            background: #eef;
            padding: 15px;
            border-radius: 10px;
            margin-top: 20px;
            font-size: 13px;
            color: #666;
        }
        .info strong { color: #333; }
        .db-status {
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 12px;
            display: inline-block;
        }
        .db-status.connected { background: #dfd; color: #080; }
        .db-status.disconnected { background: #fdd; color: #c00; }
    </style>
</head>
<body>
    <div class="login-box">
        <div class="logo">📋</div>
        <h1>ADPS Login</h1>
        <p class="subtitle">Automated Duty Processing System</p>
        
        <?php if ($error): ?>
            <div class="error">❌ <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label>📧 Email Address</label>
                <input type="email" name="email" placeholder="Enter your email" 
                       value="admin@adps.com" required>
            </div>
            
            <div class="form-group">
                <label>🔒 Password</label>
                <input type="password" name="password" placeholder="Enter your password" 
                       value="Admin@123" required>
            </div>
            
            <button type="submit" class="btn-login">🔑 Login</button>
        </form>
        
        <div class="info">
            <strong>Demo Credentials:</strong><br>
            Email: admin@adps.com<br>
            Password: Admin@123<br><br>
            <strong>Database Status:</strong> 
            <?php if ($dbConnected): ?>
                <span class="db-status connected">✅ Connected</span>
            <?php else: ?>
                <span class="db-status disconnected">❌ Disconnected</span>
                <br><small style="color:#c00;">Error: <?php echo htmlspecialchars($dbError); ?></small>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>