<?php
// =============================================
// Duty Controller
// =============================================

require_once __DIR__ . '/../models/Duty.php';
require_once __DIR__ . '/../models/Teacher.php';
require_once __DIR__ . '/../models/DutyCategory.php';
require_once __DIR__ . '/../models/Audit.php';
require_once __DIR__ . '/../includes/auth.php';

class DutyController {
    private $auth;
    private $audit;
    
    public function __construct() {
        $this->auth = new Auth();
        $this->audit = new Audit();
    }
    
    public function index($filters = []) {
        if (!$this->auth->isLoggedIn()) {
            return ['success' => false, 'message' => 'Unauthorized'];
        }
        
        $duties = Duty::all($filters);
        $total = Duty::count($filters);
        
        return [
            'success' => true,
            'data' => $duties,
            'total' => $total
        ];
    }
    
    public function create($data) {
        if (!$this->auth->isLoggedIn()) {
            return ['success' => false, 'message' => 'Unauthorized'];
        }
        
        // Validate required fields
        $required = ['teacher_id', 'category_id', 'duty_date', 'start_time', 'end_time'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return ['success' => false, 'message' => ucfirst(str_replace('_', ' ', $field)) . ' is required'];
            }
        }
        
        // Check for conflicts
        if (Duty::checkConflicts($data['teacher_id'], $data['duty_date'], $data['start_time'], $data['end_time'])) {
            return ['success' => false, 'message' => 'Teacher has a conflicting duty at this time'];
        }
        
        $data['assigned_by'] = $this->auth->getUserId();
        $duty = new Duty();
        $result = $duty->create($data);
        
        if ($result) {
            $this->audit->log(
                $this->auth->getUserId(),
                'create_duty',
                'duties',
                'Created duty for teacher ID: ' . $data['teacher_id'],
                null,
                $duty->getData()
            );
            
            return ['success' => true, 'data' => $duty->getData()];
        }
        
        return ['success' => false, 'message' => 'Failed to create duty'];
    }
    
    public function update($id, $data) {
        if (!$this->auth->isLoggedIn()) {
            return ['success' => false, 'message' => 'Unauthorized'];
        }
        
        $duty = new Duty($id);
        if (!$duty->getData()) {
            return ['success' => false, 'message' => 'Duty not found'];
        }
        
        $oldData = $duty->getData();
        
        // Check for conflicts if teacher or time changed
        $teacherId = $data['teacher_id'] ?? $oldData['teacher_id'];
        $dutyDate = $data['duty_date'] ?? $oldData['duty_date'];
        $startTime = $data['start_time'] ?? $oldData['start_time'];
        $endTime = $data['end_time'] ?? $oldData['end_time'];
        
        if (Duty::checkConflicts($teacherId, $dutyDate, $startTime, $endTime, $id)) {
            return ['success' => false, 'message' => 'Teacher has a conflicting duty at this time'];
        }
        
        $result = $duty->update($data);
        
        if ($result) {
            $this->audit->log(
                $this->auth->getUserId(),
                'update_duty',
                'duties',
                'Updated duty: ' . $oldData['duty_code'],
                $oldData,
                $duty->getData()
            );
            
            return ['success' => true, 'data' => $duty->getData()];
        }
        
        return ['success' => false, 'message' => 'Failed to update duty'];
    }
    
    public function delete($id) {
        if (!$this->auth->isLoggedIn()) {
            return ['success' => false, 'message' => 'Unauthorized'];
        }
        
        if (!$this->auth->hasRole(ROLE_ADMIN) && !$this->auth->hasRole(ROLE_SUPER_ADMIN)) {
            return ['success' => false, 'message' => 'Permission denied'];
        }
        
        $duty = new Duty($id);
        if (!$duty->getData()) {
            return ['success' => false, 'message' => 'Duty not found'];
        }
        
        $data = $duty->getData();
        $result = $duty->delete();
        
        if ($result) {
            $this->audit->log(
                $this->auth->getUserId(),
                'delete_duty',
                'duties',
                'Deleted duty: ' . $data['duty_code'],
                $data,
                null
            );
            
            return ['success' => true, 'message' => 'Duty deleted successfully'];
        }
        
        return ['success' => false, 'message' => 'Failed to delete duty'];
    }
    
    public function get($id) {
        if (!$this->auth->isLoggedIn()) {
            return ['success' => false, 'message' => 'Unauthorized'];
        }
        
        $duty = new Duty($id);
        if (!$duty->getData()) {
            return ['success' => false, 'message' => 'Duty not found'];
        }
        
        return ['success' => true, 'data' => $duty->getData()];
    }
    
    public function accept($id) {
        if (!$this->auth->isLoggedIn()) {
            return ['success' => false, 'message' => 'Unauthorized'];
        }
        
        $duty = new Duty($id);
        if (!$duty->getData()) {
            return ['success' => false, 'message' => 'Duty not found'];
        }
        
        $result = $duty->accept();
        
        if ($result) {
            $this->audit->log(
                $this->auth->getUserId(),
                'accept_duty',
                'duties',
                'Accepted duty: ' . $duty->getData()['duty_code'],
                null,
                $duty->getData()
            );
            
            return ['success' => true, 'data' => $duty->getData()];
        }
        
        return ['success' => false, 'message' => 'Failed to accept duty'];
    }
    
    public function reject($id, $reason = null) {
        if (!$this->auth->isLoggedIn()) {
            return ['success' => false, 'message' => 'Unauthorized'];
        }
        
        $duty = new Duty($id);
        if (!$duty->getData()) {
            return ['success' => false, 'message' => 'Duty not found'];
        }
        
        $result = $duty->reject($reason);
        
        if ($result) {
            $this->audit->log(
                $this->auth->getUserId(),
                'reject_duty',
                'duties',
                'Rejected duty: ' . $duty->getData()['duty_code'] . ($reason ? ' - Reason: ' . $reason : ''),
                null,
                $duty->getData()
            );
            
            return ['success' => true, 'data' => $duty->getData()];
        }
        
        return ['success' => false, 'message' => 'Failed to reject duty'];
    }
    
    public function complete($id) {
        if (!$this->auth->isLoggedIn()) {
            return ['success' => false, 'message' => 'Unauthorized'];
        }
        
        $duty = new Duty($id);
        if (!$duty->getData()) {
            return ['success' => false, 'message' => 'Duty not found'];
        }
        
        $result = $duty->complete();
        
        if ($result) {
            $this->audit->log(
                $this->auth->getUserId(),
                'complete_duty',
                'duties',
                'Completed duty: ' . $duty->getData()['duty_code'],
                null,
                $duty->getData()
            );
            
            return ['success' => true, 'data' => $duty->getData()];
        }
        
        return ['success' => false, 'message' => 'Failed to complete duty'];
    }
    
    public function cancel($id, $reason = null) {
        if (!$this->auth->isLoggedIn()) {
            return ['success' => false, 'message' => 'Unauthorized'];
        }
        
        if (!$this->auth->hasRole(ROLE_ADMIN) && !$this->auth->hasRole(ROLE_SUPER_ADMIN)) {
            return ['success' => false, 'message' => 'Permission denied'];
        }
        
        $duty = new Duty($id);
        if (!$duty->getData()) {
            return ['success' => false, 'message' => 'Duty not found'];
        }
        
        $result = $duty->cancel($reason);
        
        if ($result) {
            $this->audit->log(
                $this->auth->getUserId(),
                'cancel_duty',
                'duties',
                'Cancelled duty: ' . $duty->getData()['duty_code'] . ($reason ? ' - Reason: ' . $reason : ''),
                null,
                $duty->getData()
            );
            
            return ['success' => true, 'data' => $duty->getData()];
        }
        
        return ['success' => false, 'message' => 'Failed to cancel duty'];
    }
    
    public function getStatistics($filters = []) {
        if (!$this->auth->isLoggedIn()) {
            return ['success' => false, 'message' => 'Unauthorized'];
        }
        
        $stats = Duty::getStatistics($filters);
        return ['success' => true, 'data' => $stats];
    }
    
    public function generateSchedule($params) {
        if (!$this->auth->isLoggedIn()) {
            return ['success' => false, 'message' => 'Unauthorized'];
        }
        
        if (!$this->auth->hasRole(ROLE_ADMIN) && !$this->auth->hasRole(ROLE_SUPER_ADMIN)) {
            return ['success' => false, 'message' => 'Permission denied'];
        }
        
        $params['assigned_by'] = $this->auth->getUserId();
        $result = Duty::autoGenerate($params);
        
        if ($result['success']) {
            $this->audit->log(
                $this->auth->getUserId(),
                'generate_schedule',
                'duties',
                'Generated schedule: ' . $result['generated'] . ' duties created'
            );
        }
        
        return $result;
    }
}