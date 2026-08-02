<?php
// =============================================
// Auth Controller
// =============================================

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Audit.php';

class AuthController {
    private $auth;
    private $audit;
    
    public function __construct() {
        $this->auth = new Auth();
        $this->audit = new Audit();
    }
    
    public function login() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return ['success' => false, 'message' => 'Invalid request method'];
        }
        
        $email = sanitizeInput($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = isset($_POST['remember']);
        
        if (empty($email) || empty($password)) {
            return ['success' => false, 'message' => 'Please fill in all fields'];
        }
        
        $result = $this->auth->login($email, $password, $remember);
        
        if ($result['success']) {
            // Log the login
            $this->audit->log(
                $result['user']['id'],
                'login',
                'auth',
                'User logged in successfully'
            );
        }
        
        return $result;
    }
    
    public function logout() {
        $userId = $this->auth->getUserId();
        
        if ($userId) {
            $this->audit->log(
                $userId,
                'logout',
                'auth',
                'User logged out'
            );
        }
        
        $this->auth->logout();
        return ['success' => true];
    }
    
    public function register($data) {
        $result = $this->auth->register($data);
        
        if ($result['success']) {
            $this->audit->log(
                $result['user_id'],
                'register',
                'auth',
                'New user registered: ' . ($data['email'] ?? '')
            );
        }
        
        return $result;
    }
    
    public function verifyEmail($token) {
        return $this->auth->verifyEmail($token);
    }
    
    public function requestPasswordReset($email) {
        return $this->auth->requestPasswordReset($email);
    }
    
    public function resetPassword($token, $newPassword) {
        return $this->auth->resetPassword($token, $newPassword);
    }
    
    public function getCurrentUser() {
        return $this->auth->getUser();
    }
    
    public function isLoggedIn() {
        return $this->auth->isLoggedIn();
    }
    
    public function hasRole($role) {
        return $this->auth->hasRole($role);
    }
}