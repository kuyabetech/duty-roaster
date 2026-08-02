<?php
// =============================================
// Duty Category Model
// =============================================

require_once __DIR__ . '/../config/database.php';

class DutyCategory {
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
        $stmt = $this->db->prepare("SELECT * FROM duty_categories WHERE id = ?");
        $stmt->execute([$this->id]);
        $this->data = $stmt->fetch();
        return $this->data;
    }
    
    public function create($data) {
        $stmt = $this->db->prepare("
            INSERT INTO duty_categories (name, code, description, color, icon, priority, duration_minutes)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $result = $stmt->execute([
            $data['name'],
            strtoupper($data['code']),
            $data['description'] ?? null,
            $data['color'] ?? '#007bff',
            $data['icon'] ?? 'fas fa-tasks',
            $data['priority'] ?? PRIORITY_NORMAL,
            $data['duration_minutes'] ?? 60
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
        
        $allowedFields = ['name', 'code', 'description', 'color', 'icon', 'priority', 'duration_minutes', 'status'];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = ?";
                $params[] = $data[$field];
            }
        }
        
        if (empty($fields)) return false;
        
        $params[] = $this->id;
        $sql = "UPDATE duty_categories SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute($params);
        
        if ($result) {
            $this->load();
            return true;
        }
        return false;
    }
    
    public function delete() {
        $stmt = $this->db->prepare("UPDATE duty_categories SET status = ? WHERE id = ?");
        return $stmt->execute([STATUS_INACTIVE, $this->id]);
    }
    
    public static function all($filters = []) {
        $db = Database::getInstance();
        $sql = "SELECT * FROM duty_categories WHERE 1=1";
        $params = [];
        
        if (isset($filters['status'])) {
            $sql .= " AND status = ?";
            $params[] = $filters['status'];
        }
        
        if (isset($filters['search'])) {
            $sql .= " AND (name LIKE ? OR code LIKE ?)";
            $search = '%' . $filters['search'] . '%';
            $params[] = $search;
            $params[] = $search;
        }
        
        $sql .= " ORDER BY name";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    public static function getActive() {
        return self::all(['status' => STATUS_ACTIVE]);
    }
    
    public function getData() {
        return $this->data;
    }
    
    public function getId() {
        return $this->id;
    }
}