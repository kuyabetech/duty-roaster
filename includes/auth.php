<?php
// =============================================
// Authentication Class - Updated
// =============================================

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';

class Auth {
    private $db;
    private $user;
    
    public function __construct() {
        try {
            $this->db = Database::getInstance();
            $this->user = null;
            
            // Start session if not started
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            // Check if user is logged in
            if (isset($_SESSION['user_id'])) {
                $this->loadUser($_SESSION['user_id']);
            }
        } catch (Exception $e) {
            error_log("Auth constructor error: " . $e->getMessage());
            throw $e;
        }
    }
    
    private function loadUser($userId) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ? AND status = ?");
            $stmt->execute([$userId, 'active']);
            $this->user = $stmt->fetch();
            return $this->user;
        } catch (Exception $e) {
            error_log("Load user error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Register new user - ADD THIS METHOD
     */
    public function register($data) {
        try {
            // Validate email
            $stmt = $this->db->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$data['email']]);
            if ($stmt->fetch()) {
                return ['success' => false, 'message' => 'Email already registered'];
            }
            
            // Validate username
            $stmt = $this->db->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$data['username']]);
            if ($stmt->fetch()) {
                return ['success' => false, 'message' => 'Username already taken'];
            }
            
            // Hash password
            $hashedPassword = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);
            
            // Insert user
            $stmt = $this->db->prepare("
                INSERT INTO users (username, email, password, full_name, role, status, email_verified)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $status = $data['status'] ?? 'active';
            $emailVerified = $data['email_verified'] ?? 0;
            
            $result = $stmt->execute([
                $data['username'],
                $data['email'],
                $hashedPassword,
                $data['full_name'],
                $data['role'] ?? 'teacher',
                $status,
                $emailVerified
            ]);
            
            if ($result) {
                $userId = $this->db->lastInsertId();
                
                // Send verification email (optional)
                // $this->sendVerificationEmail($userId, $data['email']);
                
                return ['success' => true, 'user_id' => $userId];
            }
            
            return ['success' => false, 'message' => 'Registration failed'];
            
        } catch (Exception $e) {
            error_log("Registration error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Registration failed: ' . $e->getMessage()];
        }
    }
    
    /**
     * Login user
     */
    public function login($email, $password, $remember = false) {
        try {
            // Find user by email
            $stmt = $this->db->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if (!$user) {
                $this->logLoginAttempt($email, false);
                return ['success' => false, 'message' => 'Invalid email or password'];
            }
            
            // Check if user is active
            if ($user['status'] !== 'active') {
                $this->logLoginAttempt($email, false);
                return ['success' => false, 'message' => 'Account is not active'];
            }
            
            // Verify password
            if (!password_verify($password, $user['password'])) {
                $this->logLoginAttempt($email, false);
                return ['success' => false, 'message' => 'Invalid email or password'];
            }
            
            // Login success
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_name'] = $user['full_name'];
            
            // Update last login
            try {
                $stmt = $this->db->prepare("UPDATE users SET last_login = NOW(), last_ip = ? WHERE id = ?");
                $stmt->execute([$_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', $user['id']]);
            } catch (Exception $e) {
                error_log("Update last login error: " . $e->getMessage());
            }
            
            // Log login attempt
            $this->logLoginAttempt($email, true, $user['id']);
            
            // Load user
            $this->loadUser($user['id']);
            
            return ['success' => true, 'user' => $this->user];
            
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            return ['success' => false, 'message' => 'System error: ' . $e->getMessage()];
        }
    }
    
    private function logLoginAttempt($email, $success, $userId = null) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO login_history (user_id, email, ip_address, user_agent, login_time, status)
                VALUES (?, ?, ?, ?, NOW(), ?)
            ");
            $stmt->execute([
                $userId,
                $email,
                $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
                $_SERVER['HTTP_USER_AGENT'] ?? '',
                $success ? 'success' : 'failed'
            ]);
        } catch (Exception $e) {
            error_log("Login log error: " . $e->getMessage());
        }
    }
    
    public function logout() {
        try {
            if (isset($_SESSION['user_id'])) {
                try {
                    $stmt = $this->db->prepare("
                        UPDATE login_history 
                        SET logout_time = NOW() 
                        WHERE user_id = ? AND logout_time IS NULL 
                        ORDER BY login_time DESC LIMIT 1
                    ");
                    $stmt->execute([$_SESSION['user_id']]);
                } catch (Exception $e) {
                    error_log("Logout update error: " . $e->getMessage());
                }
            }
        } catch (Exception $e) {
            error_log("Logout error: " . $e->getMessage());
        }
        
        $_SESSION = [];
        session_destroy();
        return true;
    }
    
    public function isLoggedIn() {
        return $this->user !== null;
    }
    
    public function hasRole($role) {
        if (!$this->user) return false;
        return $this->user['role'] === $role || $this->user['role'] === 'super_admin';
    }
    
    public function getUser() {
        return $this->user;
    }
    
    public function getUserId() {
        return $this->user ? $this->user['id'] : null;
    }
}