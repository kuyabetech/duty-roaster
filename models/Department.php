<?php
// =============================================
// Department Model
// =============================================

require_once __DIR__ . '/../config/database.php';

class Department {
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
            SELECT d.*, 
                   CONCAT(t.first_name, ' ', t.last_name) as hod_name
            FROM departments d
            LEFT JOIN teachers t ON d.hod_id = t.id
            WHERE d.id = ?
        ");
        $stmt->execute([$this->id]);
        $this->data = $stmt->fetch();
        return $this->data;
    }
    
    public function create($data) {
        $stmt = $this->db->prepare("
            INSERT INTO departments (name, code, description, hod_id, contact_email, contact_phone, year_established)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $result = $stmt->execute([
            $data['name'],
            strtoupper($data['code']),
            $data['description'] ?? null,
            $data['hod_id'] ?? null,
            $data['contact_email'] ?? null,
            $data['contact_phone'] ?? null,
            $data['year_established'] ?? null
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
        
        $allowedFields = ['name', 'code', 'description', 'hod_id', 'contact_email', 'contact_phone', 'year_established', 'status'];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = ?";
                $params[] = $data[$field];
            }
        }
        
        if (empty($fields)) return false;
        
        $params[] = $this->id;
        $sql = "UPDATE departments SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute($params);
        
        if ($result) {
            $this->load();
            return true;
        }
        return false;
    }
    
    public function delete() {
        $stmt = $this->db->prepare("UPDATE departments SET status = ? WHERE id = ?");
        return $stmt->execute([STATUS_INACTIVE, $this->id]);
    }
    
    public static function all($filters = []) {
        $db = Database::getInstance();
        $sql = "SELECT d.*, 
                       CONCAT(t.first_name, ' ', t.last_name) as hod_name,
                       (SELECT COUNT(*) FROM teachers WHERE department_id = d.id AND deleted_at IS NULL) as teacher_count
                FROM departments d
                LEFT JOIN teachers t ON d.hod_id = t.id
                WHERE 1=1";
        $params = [];
        
        if (isset($filters['status'])) {
            $sql .= " AND d.status = ?";
            $params[] = $filters['status'];
        }
        
        if (isset($filters['search'])) {
            $sql .= " AND (d.name LIKE ? OR d.code LIKE ?)";
            $search = '%' . $filters['search'] . '%';
            $params[] = $search;
            $params[] = $search;
        }
        
        $sql .= " ORDER BY d.name";
        
        if (isset($filters['limit'])) {
            $sql .= " LIMIT ?";
            $params[] = (int) $filters['limit'];
        }
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    public static function findByName($name) {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM departments WHERE name = ?");
        $stmt->execute([$name]);
        return $stmt->fetch();
    }
    
    public static function findByCode($code) {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM departments WHERE code = ?");
        $stmt->execute([$code]);
        return $stmt->fetch();
    }
    
    public function getTeachers() {
        $stmt = $this->db->prepare("
            SELECT * FROM teachers 
            WHERE department_id = ? AND deleted_at IS NULL
            ORDER BY last_name, first_name
        ");
        $stmt->execute([$this->id]);
        return $stmt->fetchAll();
    }
    
    public function getStatistics() {
        // Count teachers
        $stmt = $this->db->prepare("SELECT COUNT(*) as total FROM teachers WHERE department_id = ? AND deleted_at IS NULL");
        $stmt->execute([$this->id]);
        $teachers = $stmt->fetch()['total'] ?? 0;
        
        // Count duties
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as total,
                   SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as completed
            FROM duties 
            WHERE teacher_id IN (SELECT id FROM teachers WHERE department_id = ?)
        ");
        $stmt->execute([DUTY_COMPLETED, $this->id]);
        $duties = $stmt->fetch();
        
        return [
            'teacher_count' => $teachers,
            'total_duties' => $duties['total'] ?? 0,
            'completed_duties' => $duties['completed'] ?? 0,
            'completion_rate' => ($duties['total'] ?? 0) > 0 ? round(($duties['completed'] ?? 0) / ($duties['total'] ?? 0) * 100, 2) : 0
        ];
    }
    
    public function getData() {
        return $this->data;
    }
    
    public function getId() {
        return $this->id;
    }
}