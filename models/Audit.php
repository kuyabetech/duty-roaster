<?php
// =============================================
// Audit Log Model
// =============================================

require_once __DIR__ . '/../config/database.php';

class Audit {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function log($userId, $action, $module, $description = null, $oldValues = null, $newValues = null) {
        $stmt = $this->db->prepare("
            INSERT INTO audit_logs (user_id, action, module, description, ip_address, user_agent, old_values, new_values)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        return $stmt->execute([
            $userId,
            $action,
            $module,
            $description,
            $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            $oldValues ? json_encode($oldValues) : null,
            $newValues ? json_encode($newValues) : null
        ]);
    }
    
    public static function getLogs($filters = []) {
        $db = Database::getInstance();
        $sql = "SELECT a.*, u.full_name, u.email 
                FROM audit_logs a
                LEFT JOIN users u ON a.user_id = u.id
                WHERE 1=1";
        $params = [];
        
        if (isset($filters['user_id'])) {
            $sql .= " AND a.user_id = ?";
            $params[] = $filters['user_id'];
        }
        
        if (isset($filters['action'])) {
            $sql .= " AND a.action = ?";
            $params[] = $filters['action'];
        }
        
        if (isset($filters['module'])) {
            $sql .= " AND a.module = ?";
            $params[] = $filters['module'];
        }
        
        if (isset($filters['date_from'])) {
            $sql .= " AND DATE(a.created_at) >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (isset($filters['date_to'])) {
            $sql .= " AND DATE(a.created_at) <= ?";
            $params[] = $filters['date_to'];
        }
        
        if (isset($filters['search'])) {
            $sql .= " AND (a.action LIKE ? OR a.module LIKE ? OR a.description LIKE ?)";
            $search = '%' . $filters['search'] . '%';
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }
        
        $sql .= " ORDER BY a.created_at DESC";
        
        if (isset($filters['limit'])) {
            $sql .= " LIMIT ?";
            $params[] = (int) $filters['limit'];
        }
        
        if (isset($filters['offset'])) {
            $sql .= " OFFSET ?";
            $params[] = (int) $filters['offset'];
        }
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    public static function getActions() {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT DISTINCT action FROM audit_logs ORDER BY action");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    public static function getModules() {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT DISTINCT module FROM audit_logs ORDER BY module");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    public static function count($filters = []) {
        $db = Database::getInstance();
        $sql = "SELECT COUNT(*) as total FROM audit_logs WHERE 1=1";
        $params = [];
        
        if (isset($filters['user_id'])) {
            $sql .= " AND user_id = ?";
            $params[] = $filters['user_id'];
        }
        
        if (isset($filters['action'])) {
            $sql .= " AND action = ?";
            $params[] = $filters['action'];
        }
        
        if (isset($filters['module'])) {
            $sql .= " AND module = ?";
            $params[] = $filters['module'];
        }
        
        if (isset($filters['date_from'])) {
            $sql .= " AND DATE(created_at) >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (isset($filters['date_to'])) {
            $sql .= " AND DATE(created_at) <= ?";
            $params[] = $filters['date_to'];
        }
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        return $result['total'] ?? 0;
    }
}