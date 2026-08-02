<?php
// =============================================
// Report Model - Complete Version
// =============================================

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';

class Report {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Generate Duty Report
     */
    public function generateDutyReport($filters = []) {
        $sql = "SELECT 
                    d.id,
                    d.duty_code,
                    d.duty_date,
                    d.start_time,
                    d.end_time,
                    d.location,
                    d.priority,
                    d.status,
                    d.remarks,
                    t.first_name,
                    t.last_name,
                    t.teacher_id as teacher_code,
                    c.name as category_name,
                    c.color as category_color,
                    dep.name as department_name
                FROM duties d
                LEFT JOIN teachers t ON d.teacher_id = t.id
                LEFT JOIN duty_categories c ON d.category_id = c.id
                LEFT JOIN departments dep ON t.department_id = dep.id
                WHERE 1=1";
        $params = [];
        
        if (isset($filters['start_date']) && !empty($filters['start_date'])) {
            $sql .= " AND d.duty_date >= ?";
            $params[] = $filters['start_date'];
        }
        
        if (isset($filters['end_date']) && !empty($filters['end_date'])) {
            $sql .= " AND d.duty_date <= ?";
            $params[] = $filters['end_date'];
        }
        
        if (isset($filters['teacher_id']) && !empty($filters['teacher_id'])) {
            $sql .= " AND d.teacher_id = ?";
            $params[] = $filters['teacher_id'];
        }
        
        if (isset($filters['department_id']) && !empty($filters['department_id'])) {
            $sql .= " AND t.department_id = ?";
            $params[] = $filters['department_id'];
        }
        
        if (isset($filters['category_id']) && !empty($filters['category_id'])) {
            $sql .= " AND d.category_id = ?";
            $params[] = $filters['category_id'];
        }
        
        if (isset($filters['status']) && !empty($filters['status'])) {
            if (is_array($filters['status'])) {
                $placeholders = implode(',', array_fill(0, count($filters['status']), '?'));
                $sql .= " AND d.status IN ($placeholders)";
                $params = array_merge($params, $filters['status']);
            } else {
                $sql .= " AND d.status = ?";
                $params[] = $filters['status'];
            }
        }
        
        $sql .= " ORDER BY d.duty_date DESC, d.start_time DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll();
        
        // Calculate statistics
        $stats = [
            'total' => count($data),
            'completed' => 0,
            'pending' => 0,
            'accepted' => 0,
            'rejected' => 0,
            'missed' => 0,
            'cancelled' => 0
        ];
        
        foreach ($data as $duty) {
            $status = strtolower($duty['status'] ?? '');
            if (isset($stats[$status])) {
                $stats[$status]++;
            }
        }
        
        return [
            'data' => $data,
            'statistics' => $stats,
            'generated_at' => date('Y-m-d H:i:s'),
            'filters' => $filters
        ];
    }
    
    /**
     * Generate Teacher Workload Report
     */
    public function generateTeacherWorkloadReport($filters = []) {
        $sql = "SELECT 
                    t.id,
                    t.first_name,
                    t.last_name,
                    t.teacher_id,
                    dep.name as department_name,
                    COUNT(d.id) as total_duties,
                    SUM(CASE WHEN d.status = 'completed' THEN 1 ELSE 0 END) as completed_duties,
                    SUM(CASE WHEN d.status = 'pending' THEN 1 ELSE 0 END) as pending_duties,
                    SUM(CASE WHEN d.status = 'accepted' THEN 1 ELSE 0 END) as accepted_duties,
                    SUM(CASE WHEN d.status = 'missed' THEN 1 ELSE 0 END) as missed_duties,
                    SUM(CASE WHEN d.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_duties
                FROM teachers t
                LEFT JOIN departments dep ON t.department_id = dep.id
                LEFT JOIN duties d ON t.id = d.teacher_id";
        $params = [];
        
        $where = [];
        if (isset($filters['start_date']) && !empty($filters['start_date'])) {
            $where[] = "d.duty_date >= ?";
            $params[] = $filters['start_date'];
        }
        
        if (isset($filters['end_date']) && !empty($filters['end_date'])) {
            $where[] = "d.duty_date <= ?";
            $params[] = $filters['end_date'];
        }
        
        if (isset($filters['department_id']) && !empty($filters['department_id'])) {
            $where[] = "t.department_id = ?";
            $params[] = $filters['department_id'];
        }
        
        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }
        
        $sql .= " GROUP BY t.id ORDER BY total_duties DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll();
        
        // Calculate summary statistics
        $totalTeachers = count($data);
        $totalDuties = array_sum(array_column($data, 'total_duties'));
        $avgDuties = $totalTeachers > 0 ? round($totalDuties / $totalTeachers, 2) : 0;
        
        return [
            'data' => $data,
            'statistics' => [
                'total_teachers' => $totalTeachers,
                'total_duties' => $totalDuties,
                'average_per_teacher' => $avgDuties
            ],
            'generated_at' => date('Y-m-d H:i:s'),
            'filters' => $filters
        ];
    }
    
    /**
     * Generate Department Report
     */
    public function generateDepartmentReport($filters = []) {
        $sql = "SELECT 
                    dep.id,
                    dep.name,
                    dep.code,
                    COUNT(DISTINCT t.id) as teacher_count,
                    COUNT(d.id) as total_duties,
                    SUM(CASE WHEN d.status = 'completed' THEN 1 ELSE 0 END) as completed_duties,
                    SUM(CASE WHEN d.status = 'pending' THEN 1 ELSE 0 END) as pending_duties,
                    SUM(CASE WHEN d.status = 'accepted' THEN 1 ELSE 0 END) as accepted_duties,
                    SUM(CASE WHEN d.status = 'missed' THEN 1 ELSE 0 END) as missed_duties,
                    ROUND(
                        CASE 
                            WHEN COUNT(d.id) > 0 THEN 
                                SUM(CASE WHEN d.status = 'completed' THEN 1 ELSE 0 END) / COUNT(d.id) * 100 
                            ELSE 0 
                        END, 2
                    ) as completion_rate
                FROM departments dep
                LEFT JOIN teachers t ON dep.id = t.department_id
                LEFT JOIN duties d ON t.id = d.teacher_id";
        $params = [];
        
        $where = [];
        if (isset($filters['start_date']) && !empty($filters['start_date'])) {
            $where[] = "d.duty_date >= ?";
            $params[] = $filters['start_date'];
        }
        
        if (isset($filters['end_date']) && !empty($filters['end_date'])) {
            $where[] = "d.duty_date <= ?";
            $params[] = $filters['end_date'];
        }
        
        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }
        
        $sql .= " GROUP BY dep.id ORDER BY total_duties DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll();
        
        return [
            'data' => $data,
            'generated_at' => date('Y-m-d H:i:s'),
            'filters' => $filters
        ];
    }
    
    /**
     * Export to CSV
     */
    public function exportCSV($data, $filename = null) {
        if (!$filename) {
            $filename = 'report_' . date('Y-m-d_H-i-s') . '.csv';
        }
        
        if (ob_get_level()) ob_clean();
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        fputs($output, "\xEF\xBB\xBF");
        
        if (!empty($data)) {
            fputcsv($output, array_keys($data[0]));
            foreach ($data as $row) {
                fputcsv($output, $row);
            }
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Export to PDF
     */
    public function exportPDF($html, $filename = null) {
        if (!$filename) {
            $filename = 'report_' . date('Y-m-d_H-i-s') . '.pdf';
        }
        
        if (ob_get_level()) ob_clean();
        
        // Try Dompdf
        $dompdfAutoload = __DIR__ . '/../vendor/dompdf/dompdf/autoload.inc.php';
        
        if (file_exists($dompdfAutoload)) {
            require_once $dompdfAutoload;
            
            try {
                $options = new \Dompdf\Options();
                $options->set('defaultFont', 'DejaVu Sans');
                $options->set('isHtml5ParserEnabled', true);
                $options->set('isRemoteEnabled', true);
                $options->set('isPhpEnabled', true);
                
                $dompdf = new \Dompdf\Dompdf($options);
                $dompdf->loadHtml($html);
                $dompdf->setPaper('A4', 'portrait');
                $dompdf->render();
                
                // Add page numbers
                $canvas = $dompdf->getCanvas();
                $canvas->page_text(520, 815, "Page {PAGE_NUM}/{PAGE_COUNT}", null, 8, array(0, 0, 0));
                
                $dompdf->stream($filename, array('Attachment' => 1));
                exit;
            } catch (Exception $e) {
                error_log("Dompdf Error: " . $e->getMessage());
            }
        }
        
        // Fallback: HTML
        header('Content-Type: text/html');
        header('Content-Disposition: attachment; filename="' . str_replace('.pdf', '.html', $filename) . '"');
        echo $html;
        exit;
    }
}