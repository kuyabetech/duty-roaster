<?php
// =============================================
// Teacher Controller
// =============================================

require_once __DIR__ . '/../models/Teacher.php';
require_once __DIR__ . '/../models/Department.php';
require_once __DIR__ . '/../models/Audit.php';
require_once __DIR__ . '/../includes/auth.php';

class TeacherController {
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
        
        $teachers = Teacher::all($filters);
        $total = Teacher::count($filters);
        
        return [
            'success' => true,
            'data' => $teachers,
            'total' => $total
        ];
    }
    
    public function create($data) {
        if (!$this->auth->isLoggedIn()) {
            return ['success' => false, 'message' => 'Unauthorized'];
        }
        
        // Validate required fields
        $required = ['first_name', 'last_name', 'gender', 'email', 'phone_primary'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return ['success' => false, 'message' => ucfirst(str_replace('_', ' ', $field)) . ' is required'];
            }
        }
        
        // Check if email exists
        if (Teacher::findByEmail($data['email'])) {
            return ['success' => false, 'message' => 'Email already exists'];
        }
        
        $teacher = new Teacher();
        $result = $teacher->create($data);
        
        if ($result) {
            $this->audit->log(
                $this->auth->getUserId(),
                'create_teacher',
                'teachers',
                'Created teacher: ' . $data['first_name'] . ' ' . $data['last_name'],
                null,
                $teacher->getData()
            );
            
            return ['success' => true, 'data' => $teacher->getData()];
        }
        
        return ['success' => false, 'message' => 'Failed to create teacher'];
    }
    
    public function update($id, $data) {
        if (!$this->auth->isLoggedIn()) {
            return ['success' => false, 'message' => 'Unauthorized'];
        }
        
        $teacher = new Teacher($id);
        if (!$teacher->getData()) {
            return ['success' => false, 'message' => 'Teacher not found'];
        }
        
        $oldData = $teacher->getData();
        $result = $teacher->update($data);
        
        if ($result) {
            $this->audit->log(
                $this->auth->getUserId(),
                'update_teacher',
                'teachers',
                'Updated teacher: ' . $teacher->getData()['first_name'] . ' ' . $teacher->getData()['last_name'],
                $oldData,
                $teacher->getData()
            );
            
            return ['success' => true, 'data' => $teacher->getData()];
        }
        
        return ['success' => false, 'message' => 'Failed to update teacher'];
    }
    
    public function delete($id) {
        if (!$this->auth->isLoggedIn()) {
            return ['success' => false, 'message' => 'Unauthorized'];
        }
        
        if (!$this->auth->hasRole(ROLE_ADMIN) && !$this->auth->hasRole(ROLE_SUPER_ADMIN)) {
            return ['success' => false, 'message' => 'Permission denied'];
        }
        
        $teacher = new Teacher($id);
        if (!$teacher->getData()) {
            return ['success' => false, 'message' => 'Teacher not found'];
        }
        
        $data = $teacher->getData();
        $result = $teacher->delete();
        
        if ($result) {
            $this->audit->log(
                $this->auth->getUserId(),
                'delete_teacher',
                'teachers',
                'Deleted teacher: ' . $data['first_name'] . ' ' . $data['last_name'],
                $data,
                null
            );
            
            return ['success' => true, 'message' => 'Teacher deleted successfully'];
        }
        
        return ['success' => false, 'message' => 'Failed to delete teacher'];
    }
    
    public function get($id) {
        if (!$this->auth->isLoggedIn()) {
            return ['success' => false, 'message' => 'Unauthorized'];
        }
        
        $teacher = new Teacher($id);
        if (!$teacher->getData()) {
            return ['success' => false, 'message' => 'Teacher not found'];
        }
        
        $statistics = $teacher->getStatistics();
        $duties = $teacher->getDuties(['limit' => 10]);
        
        return [
            'success' => true,
            'data' => $teacher->getData(),
            'statistics' => $statistics,
            'recent_duties' => $duties
        ];
    }
    
    public function getByDepartment($departmentId) {
        if (!$this->auth->isLoggedIn()) {
            return ['success' => false, 'message' => 'Unauthorized'];
        }
        
        $teachers = Teacher::getByDepartment($departmentId);
        return ['success' => true, 'data' => $teachers];
    }
    
    public function import($file) {
        if (!$this->auth->isLoggedIn()) {
            return ['success' => false, 'message' => 'Unauthorized'];
        }
        
        if (!$this->auth->hasRole(ROLE_ADMIN) && !$this->auth->hasRole(ROLE_SUPER_ADMIN)) {
            return ['success' => false, 'message' => 'Permission denied'];
        }
        
        // Validate file
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'File upload failed'];
        }
        
        $extension = getFileExtension($file['name']);
        if (!in_array($extension, ['csv', 'xlsx', 'xls'])) {
            return ['success' => false, 'message' => 'Invalid file type. Please upload CSV or Excel file'];
        }
        
        // Process CSV or Excel file
        // This is a simplified version - in production, use a library like PhpSpreadsheet
        $imported = 0;
        $errors = [];
        
        if ($extension === 'csv') {
            $handle = fopen($file['tmp_name'], 'r');
            $headers = fgetcsv($handle);
            
            while (($row = fgetcsv($handle)) !== false) {
                $data = array_combine($headers, $row);
                $result = $this->create($data);
                
                if ($result['success']) {
                    $imported++;
                } else {
                    $errors[] = $result['message'];
                }
            }
            fclose($handle);
        }
        
        return [
            'success' => true,
            'imported' => $imported,
            'errors' => $errors
        ];
    }
    
    public function export($filters = []) {
        if (!$this->auth->isLoggedIn()) {
            return ['success' => false, 'message' => 'Unauthorized'];
        }
        
        $teachers = Teacher::all($filters);
        
        // Generate CSV
        $filename = 'teachers_export_' . date('Y-m-d_H-i-s') . '.csv';
        $filepath = REPORT_PATH . $filename;
        
        $fp = fopen($filepath, 'w');
        fputcsv($fp, [
            'Teacher ID', 'Staff Number', 'First Name', 'Last Name', 'Gender',
            'Email', 'Phone', 'Department', 'Position', 'Status'
        ]);
        
        foreach ($teachers as $teacher) {
            fputcsv($fp, [
                $teacher['teacher_id'],
                $teacher['staff_number'],
                $teacher['first_name'],
                $teacher['last_name'],
                $teacher['gender'],
                $teacher['email'],
                $teacher['phone_primary'],
                $teacher['department_name'] ?? '',
                $teacher['position'],
                $teacher['status']
            ]);
        }
        fclose($fp);
        
        $this->audit->log(
            $this->auth->getUserId(),
            'export_teachers',
            'teachers',
            'Exported teachers data'
        );
        
        return [
            'success' => true,
            'file' => $filename,
            'path' => $filepath,
            'count' => count($teachers)
        ];
    }
}