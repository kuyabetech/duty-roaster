<?php
// =============================================
// Teacher Model - Updated with User Integration
// =============================================

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';

class Teacher {
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
    
    public function load() {
        $stmt = $this->db->prepare("
            SELECT t.*, d.name as department_name, d.code as department_code,
                   u.email, u.full_name as user_full_name, u.role as user_role
            FROM teachers t
            LEFT JOIN departments d ON t.department_id = d.id
            LEFT JOIN users u ON t.email = u.email
            WHERE t.id = ? AND t.deleted_at IS NULL
        ");
        $stmt->execute([$this->id]);
        $this->data = $stmt->fetch();
        return $this->data;
    }
    
    public function create($data) {
        // Check if user exists with this email
        $stmt = $this->db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$data['email']]);
        $user = $stmt->fetch();
        
        // If user doesn't exist, create one
        if (!$user) {
            $password = password_hash('password123', PASSWORD_DEFAULT);
            $stmt = $this->db->prepare("
                INSERT INTO users (username, email, password, full_name, role, status)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $data['email'],
                $data['email'],
                $password,
                $data['first_name'] . ' ' . $data['last_name'],
                'teacher',
                'active'
            ]);
            $userId = $this->db->lastInsertId();
        }
        
        $teacherId = 'T' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        $staffNumber = 'STF' . date('Y') . str_pad(mt_rand(1, 999), 3, '0', STR_PAD_LEFT);
        
        $stmt = $this->db->prepare("
            INSERT INTO teachers (
                teacher_id, staff_number, first_name, last_name, gender, 
                date_of_birth, phone_primary, phone_secondary, email, 
                nationality, permanent_address, current_address, department_id,
                qualification, position, employment_date, experience_years,
                profile_photo, bio, skills, languages, status, contract_type,
                max_duties_per_week, max_duties_per_month
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
            )
        ");
        
        $result = $stmt->execute([
            $teacherId,
            $staffNumber,
            $data['first_name'],
            $data['last_name'],
            $data['gender'],
            $data['date_of_birth'] ?? null,
            $data['phone_primary'],
            $data['phone_secondary'] ?? null,
            $data['email'],
            $data['nationality'] ?? null,
            $data['permanent_address'] ?? null,
            $data['current_address'] ?? null,
            $data['department_id'] ?? null,
            $data['qualification'] ?? null,
            $data['position'] ?? null,
            $data['employment_date'] ?? null,
            $data['experience_years'] ?? 0,
            $data['profile_photo'] ?? null,
            $data['bio'] ?? null,
            $data['skills'] ?? null,
            $data['languages'] ?? null,
            $data['status'] ?? 'active',
            $data['contract_type'] ?? 'permanent',
            $data['max_duties_per_week'] ?? 5,
            $data['max_duties_per_month'] ?? 20
        ]);
        
        if ($result) {
            $this->id = $this->db->lastInsertId();
            $this->load();
            return true;
        }
        return false;
    }
    
    public function update($data) {
        $fields = [];
        $params = [];
        
        $allowedFields = [
            'first_name', 'last_name', 'gender', 'date_of_birth',
            'phone_primary', 'phone_secondary', 'email', 'nationality',
            'permanent_address', 'current_address', 'department_id',
            'qualification', 'position', 'employment_date', 'experience_years',
            'profile_photo', 'bio', 'skills', 'languages', 'status',
            'contract_type', 'availability_status', 'leave_start_date',
            'leave_end_date', 'max_duties_per_week', 'max_duties_per_month'
        ];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = ?";
                $params[] = $data[$field];
            }
        }
        
        if (empty($fields)) return false;
        
        $params[] = $this->id;
        $sql = "UPDATE teachers SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute($params);
        
        if ($result) {
            // Update user table as well
            if (isset($data['email']) || isset($data['first_name']) || isset($data['last_name'])) {
                $userFields = [];
                $userParams = [];
                
                if (isset($data['email'])) {
                    $userFields[] = "email = ?";
                    $userParams[] = $data['email'];
                }
                if (isset($data['first_name']) && isset($data['last_name'])) {
                    $userFields[] = "full_name = ?";
                    $userParams[] = $data['first_name'] . ' ' . $data['last_name'];
                }
                
                if (!empty($userFields)) {
                    $userParams[] = $this->data['email'];
                    $sql = "UPDATE users SET " . implode(', ', $userFields) . " WHERE email = ?";
                    $stmt = $this->db->prepare($sql);
                    $stmt->execute($userParams);
                }
            }
            
            $this->load();
            return true;
        }
        return false;
    }
    
    public function delete() {
        $stmt = $this->db->prepare("UPDATE teachers SET deleted_at = NOW(), status = ? WHERE id = ?");
        return $stmt->execute(['inactive', $this->id]);
    }
    
    /**
     * Get all teachers with pagination - FIXED
     */
    public static function all($filters = []) {
        $db = Database::getInstance();
        $sql = "SELECT t.*, d.name as department_name, 
                       u.email as user_email, u.full_name as user_name, u.role as user_role
                FROM teachers t 
                LEFT JOIN departments d ON t.department_id = d.id
                LEFT JOIN users u ON t.email = u.email
                WHERE t.deleted_at IS NULL";
        $params = [];
        
        if (isset($filters['department_id'])) {
            $sql .= " AND t.department_id = ?";
            $params[] = $filters['department_id'];
        }
        
        if (isset($filters['status'])) {
            $sql .= " AND t.status = ?";
            $params[] = $filters['status'];
        }
        
        if (isset($filters['gender'])) {
            $sql .= " AND t.gender = ?";
            $params[] = $filters['gender'];
        }
        
        if (isset($filters['search'])) {
            $sql .= " AND (t.first_name LIKE ? OR t.last_name LIKE ? OR t.email LIKE ? OR t.teacher_id LIKE ? OR t.staff_number LIKE ?)";
            $search = '%' . $filters['search'] . '%';
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }
        
        $sql .= " ORDER BY t.last_name, t.first_name";
        
        if (isset($filters['limit']) && is_numeric($filters['limit'])) {
            $sql .= " LIMIT " . (int)$filters['limit'];
            if (isset($filters['offset']) && is_numeric($filters['offset'])) {
                $sql .= " OFFSET " . (int)$filters['offset'];
            }
        }
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * Count teachers with filters
     */
    public static function count($filters = []) {
        $db = Database::getInstance();
        $sql = "SELECT COUNT(*) as total FROM teachers t WHERE t.deleted_at IS NULL";
        $params = [];
        
        if (isset($filters['department_id'])) {
            $sql .= " AND t.department_id = ?";
            $params[] = $filters['department_id'];
        }
        
        if (isset($filters['status'])) {
            $sql .= " AND t.status = ?";
            $params[] = $filters['status'];
        }
        
        if (isset($filters['gender'])) {
            $sql .= " AND t.gender = ?";
            $params[] = $filters['gender'];
        }
        
        if (isset($filters['search'])) {
            $sql .= " AND (t.first_name LIKE ? OR t.last_name LIKE ? OR t.email LIKE ? OR t.teacher_id LIKE ? OR t.staff_number LIKE ?)";
            $search = '%' . $filters['search'] . '%';
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        return $result['total'] ?? 0;
    }
    
    public static function getByDepartment($departmentId) {
        return self::all(['department_id' => $departmentId]);
    }
    
    public static function findByEmail($email) {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM teachers WHERE email = ? AND deleted_at IS NULL");
        $stmt->execute([$email]);
        return $stmt->fetch();
    }
    
    public static function findByStaffNumber($staffNumber) {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM teachers WHERE staff_number = ? AND deleted_at IS NULL");
        $stmt->execute([$staffNumber]);
        return $stmt->fetch();
    }
    
    public function getDuties($filters = []) {
        $sql = "SELECT * FROM duties WHERE teacher_id = ?";
        $params = [$this->id];
        
        if (isset($filters['status'])) {
            $sql .= " AND status = ?";
            $params[] = $filters['status'];
        }
        
        if (isset($filters['start_date'])) {
            $sql .= " AND duty_date >= ?";
            $params[] = $filters['start_date'];
        }
        
        if (isset($filters['end_date'])) {
            $sql .= " AND duty_date <= ?";
            $params[] = $filters['end_date'];
        }
        
        $sql .= " ORDER BY duty_date DESC, start_time DESC";
        
        if (isset($filters['limit']) && is_numeric($filters['limit'])) {
            $sql .= " LIMIT " . (int)$filters['limit'];
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    public function getStatistics() {
        // Total duties
        $stmt = $this->db->prepare("SELECT COUNT(*) as total FROM duties WHERE teacher_id = ?");
        $stmt->execute([$this->id]);
        $total = $stmt->fetch()['total'] ?? 0;
        
        // Completed duties
        $stmt = $this->db->prepare("SELECT COUNT(*) as completed FROM duties WHERE teacher_id = ? AND status = ?");
        $stmt->execute([$this->id, DUTY_COMPLETED]);
        $completed = $stmt->fetch()['completed'] ?? 0;
        
        // Pending duties
        $stmt = $this->db->prepare("SELECT COUNT(*) as pending FROM duties WHERE teacher_id = ? AND status = ?");
        $stmt->execute([$this->id, DUTY_PENDING]);
        $pending = $stmt->fetch()['pending'] ?? 0;
        
        // Missed duties
        $stmt = $this->db->prepare("SELECT COUNT(*) as missed FROM duties WHERE teacher_id = ? AND status = ?");
        $stmt->execute([$this->id, DUTY_MISSED]);
        $missed = $stmt->fetch()['missed'] ?? 0;
        
        // Swap requests
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as swaps FROM duty_swaps 
            WHERE requester_teacher_id = ? OR target_teacher_id = ?
        ");
        $stmt->execute([$this->id, $this->id]);
        $swaps = $stmt->fetch()['swaps'] ?? 0;
        
        $completionRate = $total > 0 ? round(($completed / $total) * 100, 2) : 0;
        
        return [
            'total_duties' => $total,
            'completed_duties' => $completed,
            'pending_duties' => $pending,
            'missed_duties' => $missed,
            'swap_requests' => $swaps,
            'completion_rate' => $completionRate
        ];
    }
    
    public function isAvailable($date, $startTime, $endTime) {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count 
            FROM duties 
            WHERE teacher_id = ? 
            AND duty_date = ? 
            AND status NOT IN (?, ?)
            AND (
                (start_time <= ? AND end_time > ?) OR
                (start_time < ? AND end_time >= ?) OR
                (start_time >= ? AND end_time <= ?)
            )
        ");
        $stmt->execute([
            $this->id,
            $date,
            DUTY_CANCELLED,
            DUTY_REJECTED,
            $startTime,
            $startTime,
            $endTime,
            $endTime,
            $startTime,
            $endTime
        ]);
        $result = $stmt->fetch();
        return $result['count'] == 0;
    }
    
    public function isOnLeave($date) {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count 
            FROM leave_requests 
            WHERE teacher_id = ? 
            AND status = ? 
            AND ? BETWEEN start_date AND end_date
        ");
        $stmt->execute([$this->id, 'approved', $date]);
        $result = $stmt->fetch();
        return $result['count'] > 0;
    }
    
    public function getData() {
        return $this->data;
    }
    
    public function getId() {
        return $this->id;
    }
    
    public function getFullName() {
        return $this->data['first_name'] . ' ' . $this->data['last_name'];
    }
    
    public function getPhotoUrl() {
        if ($this->data['profile_photo']) {
            return SITE_URL . '/uploads/profiles/' . $this->data['profile_photo'];
        }
        return SITE_URL . '/assets/images/default-avatar.png';
    }
}