<?php
// =============================================
// Duty Model - Complete Fixed Version
// =============================================

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';

// Define constants if not defined
if (!defined('DUTY_PENDING')) define('DUTY_PENDING', 'pending');
if (!defined('DUTY_ACCEPTED')) define('DUTY_ACCEPTED', 'accepted');
if (!defined('DUTY_REJECTED')) define('DUTY_REJECTED', 'rejected');
if (!defined('DUTY_COMPLETED')) define('DUTY_COMPLETED', 'completed');
if (!defined('DUTY_MISSED')) define('DUTY_MISSED', 'missed');
if (!defined('DUTY_CANCELLED')) define('DUTY_CANCELLED', 'cancelled');
if (!defined('PRIORITY_NORMAL')) define('PRIORITY_NORMAL', 'normal');
if (!defined('PRIORITY_URGENT')) define('PRIORITY_URGENT', 'urgent');
if (!defined('STATUS_ACTIVE')) define('STATUS_ACTIVE', 'active');
if (!defined('NOTIFY_URGENT')) define('NOTIFY_URGENT', 'urgent');
if (!defined('NOTIFY_MEDIUM')) define('NOTIFY_MEDIUM', 'medium');

class Duty {
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
                SELECT d.*, 
                       t.first_name, t.last_name, t.teacher_id as teacher_code,
                       c.name as category_name, c.color as category_color,
                       cl.name as class_name
                FROM duties d
                LEFT JOIN teachers t ON d.teacher_id = t.id
                LEFT JOIN duty_categories c ON d.category_id = c.id
                LEFT JOIN classes cl ON d.class_id = cl.id
                WHERE d.id = ?
            ");
            $stmt->execute([$this->id]);
            $this->data = $stmt->fetch();
            return $this->data;
        } catch (Exception $e) {
            error_log("Duty load error: " . $e->getMessage());
            return false;
        }
    }
    
    public function create($data) {
        $dutyCode = 'DUTY' . date('Ymd') . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        $stmt = $this->db->prepare("
            INSERT INTO duties (
                duty_code, teacher_id, category_id, class_id, duty_date,
                start_time, end_time, location, priority, status,
                remarks, assigned_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $result = $stmt->execute([
            $dutyCode,
            $data['teacher_id'],
            $data['category_id'],
            $data['class_id'] ?? null,
            $data['duty_date'],
            $data['start_time'],
            $data['end_time'],
            $data['location'] ?? null,
            $data['priority'] ?? PRIORITY_NORMAL,
            $data['status'] ?? DUTY_PENDING,
            $data['remarks'] ?? null,
            $data['assigned_by']
        ]);
        
        if ($result) {
            $this->id = $this->db->lastInsertId();
            $this->load();
            $this->createNotification();
            return true;
        }
        return false;
    }
    
    public function update($data) {
        $fields = [];
        $params = [];
        
        $allowedFields = [
            'teacher_id', 'category_id', 'class_id', 'duty_date',
            'start_time', 'end_time', 'location', 'priority',
            'status', 'remarks'
        ];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = ?";
                $params[] = $data[$field];
            }
        }
        
        if (isset($data['status']) && $data['status'] === DUTY_COMPLETED) {
            $fields[] = "completed_at = NOW()";
        }
        
        if (isset($data['status']) && $data['status'] === DUTY_CANCELLED) {
            $fields[] = "cancelled_at = NOW()";
            if (isset($data['cancelled_reason'])) {
                $fields[] = "cancelled_reason = ?";
                $params[] = $data['cancelled_reason'];
            }
        }
        
        if (empty($fields)) return false;
        
        $params[] = $this->id;
        $sql = "UPDATE duties SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        
        try {
            $result = $stmt->execute($params);
            if ($result) {
                $this->load();
                return true;
            }
            return false;
        } catch (Exception $e) {
            error_log("Duty update error: " . $e->getMessage());
            return false;
        }
    }
    
    public function delete() {
        $stmt = $this->db->prepare("DELETE FROM duties WHERE id = ?");
        return $stmt->execute([$this->id]);
    }
    
    /**
     * Accept duty - FIXED
     */
    public function accept() {
        $result = $this->update(['status' => DUTY_ACCEPTED]);
        error_log("Duty accept: " . ($result ? 'Success' : 'Failed') . " for duty ID: " . $this->id);
        return $result;
    }
    
    public function reject($reason = null) {
        $data = ['status' => DUTY_REJECTED];
        if ($reason) {
            $data['remarks'] = 'Rejected: ' . $reason;
        }
        return $this->update($data);
    }
    
    public function complete() {
        return $this->update(['status' => DUTY_COMPLETED]);
    }
    
    public function cancel($reason = null) {
        $data = ['status' => DUTY_CANCELLED];
        if ($reason) {
            $data['cancelled_reason'] = $reason;
        }
        return $this->update($data);
    }
    
    /**
     * Get all duties with pagination support - FIXED
     */
    public static function all($filters = []) {
        $db = Database::getInstance();
        $sql = "SELECT d.*, 
                       t.first_name, t.last_name, t.teacher_id as teacher_code,
                       c.name as category_name, c.color as category_color
                FROM duties d
                LEFT JOIN teachers t ON d.teacher_id = t.id
                LEFT JOIN duty_categories c ON d.category_id = c.id
                WHERE 1=1";
        $params = [];
        
        if (isset($filters['teacher_id'])) {
            $sql .= " AND d.teacher_id = ?";
            $params[] = $filters['teacher_id'];
        }
        
        if (isset($filters['category_id'])) {
            $sql .= " AND d.category_id = ?";
            $params[] = $filters['category_id'];
        }
        
        if (isset($filters['status'])) {
            if (is_array($filters['status'])) {
                $placeholders = implode(',', array_fill(0, count($filters['status']), '?'));
                $sql .= " AND d.status IN ($placeholders)";
                $params = array_merge($params, $filters['status']);
            } else {
                $sql .= " AND d.status = ?";
                $params[] = $filters['status'];
            }
        }
        
        if (isset($filters['start_date'])) {
            $sql .= " AND d.duty_date >= ?";
            $params[] = $filters['start_date'];
        }
        
        if (isset($filters['end_date'])) {
            $sql .= " AND d.duty_date <= ?";
            $params[] = $filters['end_date'];
        }
        
        if (isset($filters['priority'])) {
            $sql .= " AND d.priority = ?";
            $params[] = $filters['priority'];
        }
        
        if (isset($filters['search'])) {
            $sql .= " AND (d.duty_code LIKE ? OR t.first_name LIKE ? OR t.last_name LIKE ? OR c.name LIKE ?)";
            $search = '%' . $filters['search'] . '%';
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }
        
        $sql .= " ORDER BY d.duty_date DESC, d.start_time DESC";
        
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
            error_log("Duty::all error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Count duties with filters
     */
    public static function count($filters = []) {
        $db = Database::getInstance();
        $sql = "SELECT COUNT(*) as total FROM duties d WHERE 1=1";
        $params = [];
        
        if (isset($filters['teacher_id'])) {
            $sql .= " AND d.teacher_id = ?";
            $params[] = $filters['teacher_id'];
        }
        
        if (isset($filters['category_id'])) {
            $sql .= " AND d.category_id = ?";
            $params[] = $filters['category_id'];
        }
        
        if (isset($filters['status'])) {
            if (is_array($filters['status'])) {
                $placeholders = implode(',', array_fill(0, count($filters['status']), '?'));
                $sql .= " AND d.status IN ($placeholders)";
                $params = array_merge($params, $filters['status']);
            } else {
                $sql .= " AND d.status = ?";
                $params[] = $filters['status'];
            }
        }
        
        if (isset($filters['start_date'])) {
            $sql .= " AND d.duty_date >= ?";
            $params[] = $filters['start_date'];
        }
        
        if (isset($filters['end_date'])) {
            $sql .= " AND d.duty_date <= ?";
            $params[] = $filters['end_date'];
        }
        
        if (isset($filters['search'])) {
            $sql .= " AND (d.duty_code LIKE ? OR t.first_name LIKE ? OR t.last_name LIKE ? OR c.name LIKE ?)";
            $search = '%' . $filters['search'] . '%';
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }
        
        try {
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch();
            return $result['total'] ?? 0;
        } catch (Exception $e) {
            error_log("Duty::count error: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Get duties by date range
     */
    public static function getByDateRange($startDate, $endDate, $filters = []) {
        $filters['start_date'] = $startDate;
        $filters['end_date'] = $endDate;
        return self::all($filters);
    }
    
    /**
     * Get duties for a teacher
     */
    public static function getByTeacher($teacherId, $filters = []) {
        $filters['teacher_id'] = $teacherId;
        return self::all($filters);
    }
    
    /**
     * Get duty statistics
     */
    public static function getStatistics($filters = []) {
        $db = Database::getInstance();
        $sql = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'accepted' THEN 1 ELSE 0 END) as accepted,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN status = 'missed' THEN 1 ELSE 0 END) as missed,
                    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
                    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
                FROM duties WHERE 1=1";
        $params = [];
        
        if (isset($filters['start_date'])) {
            $sql .= " AND duty_date >= ?";
            $params[] = $filters['start_date'];
        }
        
        if (isset($filters['end_date'])) {
            $sql .= " AND duty_date <= ?";
            $params[] = $filters['end_date'];
        }
        
        if (isset($filters['department_id'])) {
            $sql .= " AND teacher_id IN (SELECT id FROM teachers WHERE department_id = ?)";
            $params[] = $filters['department_id'];
        }
        
        try {
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetch();
        } catch (Exception $e) {
            error_log("Duty::getStatistics error: " . $e->getMessage());
            return [
                'total' => 0,
                'pending' => 0,
                'accepted' => 0,
                'completed' => 0,
                'missed' => 0,
                'cancelled' => 0,
                'rejected' => 0
            ];
        }
    }
    
    /**
     * Check for conflicts
     */
    public static function checkConflicts($teacherId, $date, $startTime, $endTime, $excludeId = null) {
        $db = Database::getInstance();
        $sql = "SELECT COUNT(*) as count 
                FROM duties 
                WHERE teacher_id = ? 
                AND duty_date = ? 
                AND status NOT IN (?, ?)";
        $params = [$teacherId, $date, DUTY_CANCELLED, DUTY_REJECTED];
        
        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }
        
        $sql .= " AND (
                    (start_time <= ? AND end_time > ?) OR
                    (start_time < ? AND end_time >= ?) OR
                    (start_time >= ? AND end_time <= ?)
                )";
        $params = array_merge($params, [$startTime, $startTime, $endTime, $endTime, $startTime, $endTime]);
        
        try {
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch();
            return $result['count'] > 0;
        } catch (Exception $e) {
            error_log("Duty::checkConflicts error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Auto-generate schedule
     */
    public static function autoGenerate($params) {
        $db = Database::getInstance();
        $generated = [];
        
        // Get available teachers
        $teacherSql = "SELECT * FROM teachers WHERE status = ? AND availability_status = ?";
        $teacherParams = [STATUS_ACTIVE, 'available'];
        
        if (isset($params['department_id'])) {
            $teacherSql .= " AND department_id = ?";
            $teacherParams[] = $params['department_id'];
        }
        
        $teacherStmt = $db->prepare($teacherSql);
        $teacherStmt->execute($teacherParams);
        $teachers = $teacherStmt->fetchAll();
        
        if (empty($teachers)) {
            return ['success' => false, 'message' => 'No available teachers'];
        }
        
        // Get duty categories
        $categoryStmt = $db->prepare("SELECT * FROM duty_categories WHERE status = ?");
        $categoryStmt->execute([STATUS_ACTIVE]);
        $categories = $categoryStmt->fetchAll();
        
        if (empty($categories)) {
            return ['success' => false, 'message' => 'No duty categories found'];
        }
        
        // Generate duties for each day in date range
        $startDate = new DateTime($params['start_date']);
        $endDate = new DateTime($params['end_date']);
        $interval = new DateInterval('P1D');
        $dateRange = new DatePeriod($startDate, $interval, $endDate->modify('+1 day'));
        
        $dutyCount = 0;
        $maxDuties = $params['max_duties'] ?? count($teachers) * 2;
        
        foreach ($dateRange as $date) {
            if ($dutyCount >= $maxDuties) break;
            
            $dateStr = $date->format('Y-m-d');
            
            if (isset($params['skip_weekends']) && $params['skip_weekends'] && $date->format('N') >= 6) {
                continue;
            }
            
            foreach ($categories as $category) {
                if ($dutyCount >= $maxDuties) break;
                
                $teacher = self::findBestTeacher($teachers, $dateStr);
                
                if ($teacher) {
                    $dutyData = [
                        'teacher_id' => $teacher['id'],
                        'category_id' => $category['id'],
                        'duty_date' => $dateStr,
                        'start_time' => $params['start_time'] ?? '08:00:00',
                        'end_time' => $params['end_time'] ?? '10:00:00',
                        'location' => $category['name'],
                        'priority' => $category['priority'] ?? PRIORITY_NORMAL,
                        'assigned_by' => $params['assigned_by']
                    ];
                    
                    $duty = new self();
                    if ($duty->create($dutyData)) {
                        $generated[] = $duty->getData();
                        $dutyCount++;
                    }
                }
            }
        }
        
        return [
            'success' => true,
            'generated' => $dutyCount,
            'duties' => $generated
        ];
    }
    
    private static function findBestTeacher($teachers, $date) {
        $db = Database::getInstance();
        $bestTeacher = null;
        $minDuties = PHP_INT_MAX;
        
        foreach ($teachers as $teacher) {
            // Check if teacher is on leave
            $leaveStmt = $db->prepare("
                SELECT COUNT(*) as count 
                FROM leave_requests 
                WHERE teacher_id = ? AND status = ? AND ? BETWEEN start_date AND end_date
            ");
            $leaveStmt->execute([$teacher['id'], 'approved', $date]);
            $leaveResult = $leaveStmt->fetch();
            
            if ($leaveResult['count'] > 0) continue;
            
            // Count duties for this teacher on this date
            $dutyStmt = $db->prepare("
                SELECT COUNT(*) as count 
                FROM duties 
                WHERE teacher_id = ? AND duty_date = ? 
                AND status NOT IN (?, ?)
            ");
            $dutyStmt->execute([$teacher['id'], $date, DUTY_CANCELLED, DUTY_REJECTED]);
            $dutyResult = $dutyStmt->fetch();
            $dutyCount = $dutyResult['count'];
            
            if ($dutyCount >= $teacher['max_duties_per_week']) continue;
            
            if ($dutyCount < $minDuties) {
                $minDuties = $dutyCount;
                $bestTeacher = $teacher;
            }
        }
        
        return $bestTeacher;
    }
    
    private function createNotification() {
        if (!$this->data) return;
        
        try {
            // Check if notifications table exists
            $stmt = $this->db->prepare("SHOW TABLES LIKE 'notifications'");
            $stmt->execute();
            if ($stmt->rowCount() == 0) {
                return;
            }
            
            $stmt = $this->db->prepare("
                INSERT INTO notifications (user_id, type, title, message, link, priority)
                SELECT u.id, 'duty_assignment', ?, ?, ?, ?
                FROM users u
                JOIN teachers t ON u.email = t.email
                WHERE t.id = ?
            ");
            
            $title = 'New Duty Assignment';
            $message = "You have been assigned a new duty: {$this->data['category_name']} on " . 
                       date('M d, Y', strtotime($this->data['duty_date']));
            $link = SITE_URL . '/views/duties/view.php?id=' . $this->id;
            $priority = $this->data['priority'] === PRIORITY_URGENT ? 'urgent' : 'medium';
            
            $stmt->execute([
                $title,
                $message,
                $link,
                $priority,
                $this->data['teacher_id']
            ]);
        } catch (Exception $e) {
            error_log("Notification error: " . $e->getMessage());
        }
    }
    
    public function getData() {
        return $this->data;
    }
    
    public function getId() {
        return $this->id;
    }
}