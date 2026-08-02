<?php
// =============================================
// User Model
// =============================================

require_once __DIR__ . '/../config/database.php';

class User {
    private $db;
    private $id;
    private $data;
    
    public function __construct($id = null) {
        $this->db = Database::getInstance();
        if ($id) {
            $this->id = $id;
            $this->load();
        }
    }
    
    /**
     * Load user data
     */
    public function load() {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$this->id]);
        $this->data = $stmt->fetch();
        return $this->data;
    }
    
    /**
     * Create user
     */
    public function create($data) {
        $stmt = $this->db->prepare("
            INSERT INTO users (username, email, password, full_name, role, status)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $result = $stmt->execute([
            $data['username'],
            $data['email'],
            password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]),
            $data['full_name'],
            $data['role'] ?? ROLE_TEACHER,
            $data['status'] ?? STATUS_ACTIVE
        ]);
        
        if ($result) {
            $this->id = $this->db->lastInsertId();
            $this->load();
            return true;
        }
        
        return false;
    }
    
    /**
     * Update user
     */
    public function update($data) {
        $fields = [];
        $params = [];
        
        $allowedFields = ['username', 'email', 'full_name', 'role', 'status', 'email_verified'];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = ?";
                $params[] = $data[$field];
            }
        }
        
        if (isset($data['password']) && !empty($data['password'])) {
            $fields[] = "password = ?";
            $params[] = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);
        }
        
        if (empty($fields)) return false;
        
        $params[] = $this->id;
        $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute($params);
        
        if ($result) {
            $this->load();
            return true;
        }
        
        return false;
    }
    
    /**
     * Delete user (soft delete)
     */
    public function delete() {
        $stmt = $this->db->prepare("UPDATE users SET status = ? WHERE id = ?");
        return $stmt->execute([STATUS_INACTIVE, $this->id]);
    }
    
    /**
     * Get all users
     */
    public static function all($filters = []) {
        $db = Database::getInstance();
        $sql = "SELECT * FROM users WHERE 1=1";
        $params = [];
        
        if (isset($filters['role'])) {
            $sql .= " AND role = ?";
            $params[] = $filters['role'];
        }
        
        if (isset($filters['status'])) {
            $sql .= " AND status = ?";
            $params[] = $filters['status'];
        }
        
        if (isset($filters['search'])) {
            $sql .= " AND (username LIKE ? OR email LIKE ? OR full_name LIKE ?)";
            $search = '%' . $filters['search'] . '%';
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }
        
        $sql .= " ORDER BY created_at DESC";
        
        if (isset($filters['limit'])) {
            $sql .= " LIMIT ?";
            $params[] = (int) $filters['limit'];
        }
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * Find user by email
     */
    public static function findByEmail($email) {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->fetch();
    }
    
    /**
     * Find user by username
     */
    public static function findByUsername($username) {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        return $stmt->fetch();
    }
    
    /**
     * Count users
     */
    public static function count($filters = []) {
        $db = Database::getInstance();
        $sql = "SELECT COUNT(*) as total FROM users WHERE 1=1";
        $params = [];
        
        if (isset($filters['role'])) {
            $sql .= " AND role = ?";
            $params[] = $filters['role'];
        }
        
        if (isset($filters['status'])) {
            $sql .= " AND status = ?";
            $params[] = $filters['status'];
        }
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        return $result['total'] ?? 0;
    }
    
    /**
     * Get user login history
     */
    public function getLoginHistory($limit = 10) {
        $stmt = $this->db->prepare("
            SELECT * FROM login_history 
            WHERE user_id = ? 
            ORDER BY login_time DESC 
            LIMIT ?
        ");
        $stmt->execute([$this->id, $limit]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get user sessions
     */
    public function getSessions() {
        $stmt = $this->db->prepare("
            SELECT * FROM user_sessions 
            WHERE user_id = ? AND is_active = 1 AND expires_at > NOW()
            ORDER BY created_at DESC
        ");
        $stmt->execute([$this->id]);
        return $stmt->fetchAll();
    }
    
    /**
     * Revoke session
     */
    public function revokeSession($token) {
        $stmt = $this->db->prepare("
            UPDATE user_sessions SET is_active = 0 
            WHERE session_token = ? AND user_id = ?
        ");
        return $stmt->execute([$token, $this->id]);
    }
    
    /**
     * Revoke all sessions
     */
    public function revokeAllSessions() {
        $stmt = $this->db->prepare("
            UPDATE user_sessions SET is_active = 0 
            WHERE user_id = ?
        ");
        return $stmt->execute([$this->id]);
    }
    
    /**
     * Get user data
     */
    public function getData() {
        return $this->data;
    }
    
    /**
     * Get user ID
     */
    public function getId() {
        return $this->id;
    }
    
    /**
     * Check if user is admin
     */
    public function isAdmin() {
        return $this->data['role'] === ROLE_ADMIN || $this->data['role'] === ROLE_SUPER_ADMIN;
    }
    
    /**
     * Check if user is super admin
     */
    public function isSuperAdmin() {
        return $this->data['role'] === ROLE_SUPER_ADMIN;
    }
    
    /**
     * Check if user is active
     */
    public function isActive() {
        return $this->data['status'] === STATUS_ACTIVE;
    }
}