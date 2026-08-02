<?php
// =============================================
// Swap Model - Complete Working Version
// =============================================

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Duty.php';

// Define constants if not defined
// if (!defined('SWAP_PENDING')) define('SWAP_PENDING', 'pending');
// if (!defined('SWAP_APPROVED_BY_ADMIN')) define('SWAP_APPROVED_BY_ADMIN', 'approved_by_admin');
// if (!defined('SWAP_REJECTED_BY_TEACHER')) define('SWAP_REJECTED_BY_TEACHER', 'rejected_by_teacher');
// if (!defined('SWAP_APPROVED_BY_TEACHER')) define('SWAP_APPROVED_BY_TEACHER', 'approved_by_teacher');
// if (!defined('SWAP_COMPLETED')) define('SWAP_COMPLETED', 'completed');
// if (!defined('SWAP_CANCELLED')) define('SWAP_CANCELLED', 'cancelled');

class Swap {
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
        try {
            $stmt = $this->db->prepare("
                SELECT s.*,
                       r.first_name as requester_first, r.last_name as requester_last,
                       t.first_name as target_first, t.last_name as target_last,
                       d.duty_code, d.duty_date, d.start_time, d.end_time,
                       c.name as category_name,
                       u.full_name as admin_name
                FROM duty_swaps s
                LEFT JOIN teachers r ON s.requester_teacher_id = r.id
                LEFT JOIN teachers t ON s.target_teacher_id = t.id
                LEFT JOIN duties d ON s.duty_id = d.id
                LEFT JOIN duty_categories c ON d.category_id = c.id
                LEFT JOIN users u ON s.admin_approved_by = u.id
                WHERE s.id = ?
            ");
            $stmt->execute([$this->id]);
            $this->data = $stmt->fetch();
            return $this->data;
        } catch (Exception $e) {
            error_log("Swap load error: " . $e->getMessage());
            return false;
        }
    }
    
    public function create($data) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO duty_swaps (
                    duty_id, requester_teacher_id, target_teacher_id,
                    requested_date, requested_start_time, requested_end_time,
                    reason, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $result = $stmt->execute([
                $data['duty_id'],
                $data['requester_teacher_id'],
                $data['target_teacher_id'],
                $data['requested_date'],
                $data['requested_start_time'],
                $data['requested_end_time'],
                $data['reason'] ?? null,
                SWAP_PENDING
            ]);
            
            if ($result) {
                $this->id = $this->db->lastInsertId();
                $this->load();
                $this->createNotifications();
                return true;
            }
            return false;
        } catch (Exception $e) {
            error_log("Swap create error: " . $e->getMessage());
            return false;
        }
    }
    
    public function update($data) {
        try {
            $fields = [];
            $params = [];
            
            $allowedFields = ['status', 'reason'];
            
            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $fields[] = "$field = ?";
                    $params[] = $data[$field];
                }
            }
            
            if (isset($data['status'])) {
                switch ($data['status']) {
                    case SWAP_APPROVED_BY_ADMIN:
                        $fields[] = "admin_approved_by = ?";
                        $fields[] = "admin_approved_at = NOW()";
                        $params[] = $data['admin_id'];
                        break;
                    case SWAP_APPROVED_BY_TEACHER:
                        $fields[] = "teacher_approved_at = NOW()";
                        break;
                    case SWAP_COMPLETED:
                        $fields[] = "completed_at = NOW()";
                        break;
                    case SWAP_CANCELLED:
                        $fields[] = "cancelled_at = NOW()";
                        break;
                }
            }
            
            if (empty($fields)) return false;
            
            $params[] = $this->id;
            $sql = "UPDATE duty_swaps SET " . implode(', ', $fields) . " WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute($params);
            
            if ($result) {
                $this->load();
                
                // Update duty if swap is completed
                if (isset($data['status']) && $data['status'] === SWAP_COMPLETED) {
                    $this->completeSwap();
                }
                
                return true;
            }
            return false;
        } catch (Exception $e) {
            error_log("Swap update error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Admin approves swap - FIXED
     */
    public function approveByAdmin($adminId) {
        error_log("Swap::approveByAdmin called for swap ID: " . $this->id . " by admin ID: " . $adminId);
        
        // Check if swap is pending
        if ($this->data['status'] !== SWAP_PENDING) {
            error_log("Swap is not pending. Current status: " . $this->data['status']);
            return false;
        }
        
        $result = $this->update([
            'status' => SWAP_APPROVED_BY_ADMIN,
            'admin_id' => $adminId
        ]);
        
        error_log("Swap::approveByAdmin result: " . ($result ? 'Success' : 'Failed'));
        return $result;
    }
    
    /**
     * Teacher approves swap
     */
    public function approveByTeacher() {
        if ($this->data['status'] !== SWAP_APPROVED_BY_ADMIN) {
            return false;
        }
        return $this->update(['status' => SWAP_APPROVED_BY_TEACHER]);
    }
    
    /**
     * Teacher rejects swap
     */
    public function rejectByTeacher() {
        if ($this->data['status'] !== SWAP_APPROVED_BY_ADMIN && $this->data['status'] !== SWAP_PENDING) {
            return false;
        }
        return $this->update(['status' => SWAP_REJECTED_BY_TEACHER]);
    }
    
    /**
     * Complete swap
     */
    public function complete() {
        if ($this->data['status'] !== SWAP_APPROVED_BY_TEACHER) {
            return false;
        }
        return $this->update(['status' => SWAP_COMPLETED]);
    }
    
    /**
     * Cancel swap
     */
    public function cancel() {
        if (!in_array($this->data['status'], [SWAP_PENDING, SWAP_APPROVED_BY_ADMIN, SWAP_APPROVED_BY_TEACHER])) {
            return false;
        }
        return $this->update(['status' => SWAP_CANCELLED]);
    }
    
    private function completeSwap() {
        try {
            $duty = new Duty($this->data['duty_id']);
            if (!$duty->getData()) return;
            
            $duty->update([
                'teacher_id' => $this->data['target_teacher_id']
            ]);
            
            $this->notifySwapComplete();
        } catch (Exception $e) {
            error_log("Complete swap error: " . $e->getMessage());
        }
    }
    
    private function createNotifications() {
        try {
            // Notify target teacher
            $stmt = $this->db->prepare("
                INSERT INTO notifications (user_id, type, title, message, link, priority)
                SELECT u.id, 'swap_request', ?, ?, ?, ?
                FROM users u
                JOIN teachers t ON u.email = t.email
                WHERE t.id = ?
            ");
            
            $title = 'New Swap Request';
            $message = "You have received a swap request for duty on " . 
                       date('M d, Y', strtotime($this->data['duty_date']));
            $link = SITE_URL . '/views/swaps/view.php?id=' . $this->id;
            
            $stmt->execute([
                $title,
                $message,
                $link,
                'high',
                $this->data['target_teacher_id']
            ]);
        } catch (Exception $e) {
            error_log("Notification error: " . $e->getMessage());
        }
    }
    
    private function notifySwapComplete() {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO notifications (user_id, type, title, message, link, priority)
                SELECT u.id, 'swap_completed', ?, ?, ?, ?
                FROM users u
                JOIN teachers t ON u.email = t.email
                WHERE t.id = ?
            ");
            
            $title = 'Swap Completed';
            $message = "Your swap request has been completed successfully";
            $link = SITE_URL . '/views/swaps/view.php?id=' . $this->id;
            
            $stmt->execute([
                $title,
                $message,
                $link,
                'high',
                $this->data['requester_teacher_id']
            ]);
        } catch (Exception $e) {
            error_log("Swap complete notification error: " . $e->getMessage());
        }
    }
    
    public static function all($filters = []) {
        $db = Database::getInstance();
        $sql = "SELECT s.*,
                       r.first_name as requester_first, r.last_name as requester_last,
                       t.first_name as target_first, t.last_name as target_last,
                       d.duty_code, d.duty_date, d.start_time, d.end_time,
                       c.name as category_name
                FROM duty_swaps s
                LEFT JOIN teachers r ON s.requester_teacher_id = r.id
                LEFT JOIN teachers t ON s.target_teacher_id = t.id
                LEFT JOIN duties d ON s.duty_id = d.id
                LEFT JOIN duty_categories c ON d.category_id = c.id
                WHERE 1=1";
        $params = [];
        
        if (isset($filters['status'])) {
            if (is_array($filters['status'])) {
                $placeholders = implode(',', array_fill(0, count($filters['status']), '?'));
                $sql .= " AND s.status IN ($placeholders)";
                $params = array_merge($params, $filters['status']);
            } else {
                $sql .= " AND s.status = ?";
                $params[] = $filters['status'];
            }
        }
        
        if (isset($filters['requester_id'])) {
            $sql .= " AND s.requester_teacher_id = ?";
            $params[] = $filters['requester_id'];
        }
        
        if (isset($filters['target_id'])) {
            $sql .= " AND s.target_teacher_id = ?";
            $params[] = $filters['target_id'];
        }
        
        if (isset($filters['teacher_id'])) {
            $sql .= " AND (s.requester_teacher_id = ? OR s.target_teacher_id = ?)";
            $params[] = $filters['teacher_id'];
            $params[] = $filters['teacher_id'];
        }
        
        if (isset($filters['date_from'])) {
            $sql .= " AND s.requested_date >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (isset($filters['date_to'])) {
            $sql .= " AND s.requested_date <= ?";
            $params[] = $filters['date_to'];
        }
        
        if (isset($filters['search'])) {
            $sql .= " AND (r.first_name LIKE ? OR r.last_name LIKE ? OR t.first_name LIKE ? OR t.last_name LIKE ?)";
            $search = '%' . $filters['search'] . '%';
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }
        
        $sql .= " ORDER BY s.created_at DESC";
        
        if (isset($filters['limit']) && is_numeric($filters['limit'])) {
            $sql .= " LIMIT " . (int)$filters['limit'];
            if (isset($filters['offset']) && is_numeric($filters['offset'])) {
                $sql .= " OFFSET " . (int)$filters['offset'];
            }
        }
        
        try {
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Swap::all error: " . $e->getMessage());
            return [];
        }
    }
    
    public static function count($filters = []) {
        $db = Database::getInstance();
        $sql = "SELECT COUNT(*) as total FROM duty_swaps s WHERE 1=1";
        $params = [];
        
        if (isset($filters['status'])) {
            if (is_array($filters['status'])) {
                $placeholders = implode(',', array_fill(0, count($filters['status']), '?'));
                $sql .= " AND s.status IN ($placeholders)";
                $params = array_merge($params, $filters['status']);
            } else {
                $sql .= " AND s.status = ?";
                $params[] = $filters['status'];
            }
        }
        
        if (isset($filters['requester_id'])) {
            $sql .= " AND s.requester_teacher_id = ?";
            $params[] = $filters['requester_id'];
        }
        
        if (isset($filters['target_id'])) {
            $sql .= " AND s.target_teacher_id = ?";
            $params[] = $filters['target_id'];
        }
        
        if (isset($filters['teacher_id'])) {
            $sql .= " AND (s.requester_teacher_id = ? OR s.target_teacher_id = ?)";
            $params[] = $filters['teacher_id'];
            $params[] = $filters['teacher_id'];
        }
        
        if (isset($filters['date_from'])) {
            $sql .= " AND s.requested_date >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (isset($filters['date_to'])) {
            $sql .= " AND s.requested_date <= ?";
            $params[] = $filters['date_to'];
        }
        
        try {
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch();
            return $result['total'] ?? 0;
        } catch (Exception $e) {
            error_log("Swap::count error: " . $e->getMessage());
            return 0;
        }
    }
    
    public static function getPending($limit = null) {
        $filters = ['status' => SWAP_PENDING];
        if ($limit) {
            $filters['limit'] = $limit;
        }
        return self::all($filters);
    }
    
    public static function getByTeacher($teacherId, $filters = []) {
        $filters['teacher_id'] = $teacherId;
        return self::all($filters);
    }
    
    public static function getByDuty($dutyId) {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM duty_swaps WHERE duty_id = ? ORDER BY created_at DESC");
        $stmt->execute([$dutyId]);
        return $stmt->fetchAll();
    }
    
    public function getData() {
        return $this->data;
    }
    
    public function getId() {
        return $this->id;
    }
}