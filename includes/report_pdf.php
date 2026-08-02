<?php
// =============================================
// PDF Report Generator - Professional Styling
// =============================================

// Check if function already exists to prevent redeclaration
if (!function_exists('generateReportHTML')) {
    
    /**
     * Generate Professional PDF Report HTML
     */
    function generateReportHTML($reportData, $type) {
        $html = '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Report</title>
            <style>
                /* =============================================
                   PDF Report Styles
                   ============================================= */
                
                /* Page Setup */
                @page {
                    margin: 20px 25px;
                    size: A4 portrait;
                }
                
                /* Body */
                body {
                    font-family: "DejaVu Sans", "Helvetica", Arial, sans-serif;
                    font-size: 11px;
                    line-height: 1.6;
                    color: #2d3436;
                    background: #ffffff;
                }
                
                /* =============================================
                   Header Section
                   ============================================= */
                .report-header {
                    text-align: center;
                    padding: 15px 0 20px;
                    border-bottom: 3px solid #ffd700;
                    margin-bottom: 20px;
                    position: relative;
                }
                
                .report-header .logo {
                    font-size: 28px;
                    font-weight: 800;
                    color: #1a1a2e;
                    letter-spacing: -1px;
                }
                
                .report-header .logo span {
                    color: #ffd700;
                }
                
                .report-header .subtitle {
                    font-size: 12px;
                    color: #7f8c8d;
                    margin-top: 3px;
                    letter-spacing: 1px;
                }
                
                .report-header .report-type {
                    font-size: 16px;
                    font-weight: 700;
                    color: #1a1a2e;
                    margin-top: 8px;
                    background: #f8f9fa;
                    display: inline-block;
                    padding: 4px 20px;
                    border-radius: 4px;
                }
                
                /* =============================================
                   Meta Information
                   ============================================= */
                .report-meta {
                    background: #f8f9fa;
                    padding: 10px 15px;
                    border-radius: 6px;
                    margin-bottom: 20px;
                    font-size: 10px;
                    color: #636e72;
                    display: flex;
                    justify-content: space-between;
                    flex-wrap: wrap;
                    border: 1px solid #e9ecef;
                }
                
                .report-meta .meta-item {
                    display: inline-block;
                    margin-right: 15px;
                }
                
                .report-meta .meta-item strong {
                    color: #2d3436;
                }
                
                /* =============================================
                   Statistics Grid
                   ============================================= */
                .stats-grid {
                    display: grid;
                    grid-template-columns: repeat(4, 1fr);
                    gap: 12px;
                    margin: 20px 0 25px;
                }
                
                .stat-box {
                    background: #f8f9fa;
                    padding: 12px 15px;
                    border-radius: 8px;
                    text-align: center;
                    border-left: 4px solid #ffd700;
                    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
                }
                
                .stat-box .number {
                    font-size: 20px;
                    font-weight: 700;
                    color: #1a1a2e;
                    display: block;
                    line-height: 1.2;
                }
                
                .stat-box .label {
                    font-size: 9px;
                    color: #7f8c8d;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                    margin-top: 2px;
                }
                
                .stat-box.primary { border-left-color: #3498db; }
                .stat-box.success { border-left-color: #2ecc71; }
                .stat-box.warning { border-left-color: #f39c12; }
                .stat-box.danger { border-left-color: #e74c3c; }
                .stat-box.purple { border-left-color: #9b59b6; }
                
                /* =============================================
                   Table Styles
                   ============================================= */
                .report-table {
                    width: 100%;
                    border-collapse: collapse;
                    margin: 15px 0 20px;
                    font-size: 9.5px;
                }
                
                .report-table thead th {
                    background: #1a1a2e;
                    color: #ffffff;
                    padding: 8px 10px;
                    text-align: left;
                    font-weight: 600;
                    text-transform: uppercase;
                    letter-spacing: 0.3px;
                    font-size: 8.5px;
                    border: 1px solid #1a1a2e;
                }
                
                .report-table tbody td {
                    padding: 6px 10px;
                    border-bottom: 1px solid #ecf0f1;
                    border-left: 1px solid #ecf0f1;
                    border-right: 1px solid #ecf0f1;
                    vertical-align: middle;
                }
                
                .report-table tbody tr:nth-child(even) {
                    background: #fafbfc;
                }
                
                .report-table tbody tr:hover {
                    background: #f0f0f0;
                }
                
                /* Status Badges */
                .badge {
                    display: inline-block;
                    padding: 2px 8px;
                    border-radius: 12px;
                    font-size: 8px;
                    font-weight: 600;
                    text-transform: uppercase;
                    letter-spacing: 0.3px;
                }
                
                .badge-success {
                    background: #d4edda;
                    color: #155724;
                }
                
                .badge-danger {
                    background: #f8d7da;
                    color: #721c24;
                }
                
                .badge-warning {
                    background: #fff3cd;
                    color: #856404;
                }
                
                .badge-info {
                    background: #d1ecf1;
                    color: #0c5460;
                }
                
                .badge-secondary {
                    background: #e2e3e5;
                    color: #383d41;
                }
                
                .badge-primary {
                    background: #cce5ff;
                    color: #004085;
                }
                
                /* Priority Badges */
                .priority-low {
                    background: #d1ecf1;
                    color: #0c5460;
                    padding: 2px 8px;
                    border-radius: 10px;
                    font-size: 8px;
                    font-weight: 600;
                }
                
                .priority-normal {
                    background: #d4edda;
                    color: #155724;
                    padding: 2px 8px;
                    border-radius: 10px;
                    font-size: 8px;
                    font-weight: 600;
                }
                
                .priority-high {
                    background: #fff3cd;
                    color: #856404;
                    padding: 2px 8px;
                    border-radius: 10px;
                    font-size: 8px;
                    font-weight: 600;
                }
                
                .priority-urgent {
                    background: #f8d7da;
                    color: #721c24;
                    padding: 2px 8px;
                    border-radius: 10px;
                    font-size: 8px;
                    font-weight: 600;
                }
                
                /* =============================================
                   Footer
                   ============================================= */
                .report-footer {
                    margin-top: 25px;
                    padding-top: 15px;
                    border-top: 2px solid #e9ecef;
                    text-align: center;
                    font-size: 8.5px;
                    color: #b2bec3;
                }
                
                .report-footer .footer-links {
                    margin-bottom: 5px;
                }
                
                .report-footer .footer-links span {
                    margin: 0 10px;
                }
                
                /* =============================================
                   Responsive Table
                   ============================================= */
                .table-responsive {
                    overflow-x: auto;
                    margin-bottom: 15px;
                }
                
                /* =============================================
                   Print Styles
                   ============================================= */
                @media print {
                    body { margin: 0; padding: 0; }
                    .no-print { display: none; }
                    .report-table thead th { background: #1a1a2e !important; color: #fff !important; }
                    .stat-box { break-inside: avoid; }
                }
                
                /* =============================================
                   Watermark
                   ============================================= */
                .watermark {
                    position: fixed;
                    top: 50%;
                    left: 50%;
                    transform: translate(-50%, -50%) rotate(-45deg);
                    font-size: 80px;
                    color: rgba(26, 26, 46, 0.03);
                    font-weight: 900;
                    pointer-events: none;
                    z-index: -1;
                    letter-spacing: 10px;
                    white-space: nowrap;
                }
                
                /* =============================================
                   Section Headers
                   ============================================= */
                .section-title {
                    font-size: 13px;
                    font-weight: 700;
                    color: #1a1a2e;
                    margin: 20px 0 10px;
                    padding-bottom: 5px;
                    border-bottom: 2px solid #ffd700;
                }
                
                .text-muted {
                    color: #7f8c8d;
                }
                
                .text-center {
                    text-align: center;
                }
                
                .text-right {
                    text-align: right;
                }
                
                .mt-2 { margin-top: 10px; }
                .mb-2 { margin-bottom: 10px; }
                .mt-3 { margin-top: 15px; }
                .mb-3 { margin-bottom: 15px; }
                
                /* =============================================
                   Summary Cards
                   ============================================= */
                .summary-card {
                    background: #f8f9fa;
                    border-radius: 6px;
                    padding: 12px 15px;
                    margin-bottom: 10px;
                    border: 1px solid #e9ecef;
                }
                
                .summary-card .summary-label {
                    font-weight: 600;
                    color: #2d3436;
                }
                
                .summary-card .summary-value {
                    float: right;
                    font-weight: 600;
                    color: #1a1a2e;
                }
                
                .category-dot {
                    display: inline-block;
                    width: 10px;
                    height: 10px;
                    border-radius: 50%;
                    margin-right: 4px;
                    vertical-align: middle;
                }
            </style>
        </head>
        <body>';
        
        // =============================================
        // Watermark
        // =============================================
        $html .= '<div class="watermark">ADPS</div>';
        
        // =============================================
        // Header
        // =============================================
        $html .= '<div class="report-header">';
        $html .= '<div class="logo">ADPS <span>Report</span></div>';
        $html .= '<div class="subtitle">Automated Duty Processing System</div>';
        $html .= '<div class="report-type">' . ucfirst($type) . ' Report</div>';
        $html .= '</div>';
        
        // =============================================
        // Meta Information
        // =============================================
        $html .= '<div class="report-meta">';
        $html .= '<div class="meta-item"><strong>Generated:</strong> ' . date('Y-m-d H:i:s') . '</div>';
        $html .= '<div class="meta-item"><strong>Period:</strong> ' . ($reportData['filters']['start_date'] ?? 'N/A') . ' to ' . ($reportData['filters']['end_date'] ?? 'N/A') . '</div>';
        
        if (!empty($reportData['filters']['department_id'])) {
            $html .= '<div class="meta-item"><strong>Department:</strong> ' . htmlspecialchars($reportData['filters']['department_id']) . '</div>';
        }
        
        $html .= '<div class="meta-item"><strong>Records:</strong> ' . number_format(count($reportData['data'] ?? [])) . '</div>';
        $html .= '</div>';
        
        // =============================================
        // Statistics
        // =============================================
        if (isset($reportData['statistics'])) {
            $stats = $reportData['statistics'];
            $html .= '<div class="stats-grid">';
            
            $statClasses = ['primary', 'success', 'warning', 'danger', 'purple'];
            $statLabels = [
                'total' => 'Total Records',
                'completed' => 'Completed',
                'pending' => 'Pending',
                'missed' => 'Missed',
                'cancelled' => 'Cancelled'
            ];
            
            $i = 0;
            foreach ($stats as $key => $value) {
                if ($key === 'total' || $value > 0 || $key === 'total') {
                    $class = $statClasses[$i % count($statClasses)];
                    $label = $statLabels[$key] ?? ucfirst(str_replace('_', ' ', $key));
                    $html .= '<div class="stat-box ' . $class . '">
                                <span class="number">' . number_format($value) . '</span>
                                <span class="label">' . htmlspecialchars($label) . '</span>
                              </div>';
                    $i++;
                }
            }
            
            $html .= '</div>';
        }
        
        // =============================================
        // Data Table
        // =============================================
        if (!empty($reportData['data'])) {
            $html .= '<div class="section-title">📊 Report Data</div>';
            $html .= '<div class="table-responsive">';
            $html .= '<table class="report-table">';
            
            // Table Headers
            $html .= '<thead><tr>';
            foreach (array_keys($reportData['data'][0]) as $key) {
                $label = str_replace('_', ' ', ucfirst($key));
                $html .= '<th>' . htmlspecialchars($label) . '</th>';
            }
            $html .= '</tr></thead>';
            
            // Table Body
            $html .= '<tbody>';
            $rowCount = 0;
            foreach ($reportData['data'] as $row) {
                $rowCount++;
                $html .= '<tr>';
                foreach ($row as $key => $value) {
                    // Format specific fields
                    if (strpos($key, 'status') !== false && $value) {
                        $statusColors = [
                            'completed' => 'success',
                            'pending' => 'warning',
                            'missed' => 'danger',
                            'cancelled' => 'secondary',
                            'accepted' => 'info',
                            'rejected' => 'danger',
                            'active' => 'success',
                            'inactive' => 'secondary',
                            'on_leave' => 'warning'
                        ];
                        $color = $statusColors[strtolower($value)] ?? 'secondary';
                        $html .= '<td><span class="badge badge-' . $color . '">' . htmlspecialchars($value) . '</span></td>';
                    } elseif (strpos($key, 'priority') !== false && $value) {
                        $html .= '<td><span class="priority-' . strtolower($value) . '">' . htmlspecialchars($value) . '</span></td>';
                    } elseif (strpos($key, 'date') !== false && $value) {
                        $html .= '<td>' . date('Y-m-d', strtotime($value)) . '</td>';
                    } elseif (strpos($key, 'time') !== false && $value) {
                        $html .= '<td>' . date('h:i A', strtotime($value)) . '</td>';
                    } elseif (strpos($key, 'color') !== false && $value) {
                        $html .= '<td><span class="category-dot" style="background:' . htmlspecialchars($value) . ';"></span></td>';
                    } else {
                        $html .= '<td>' . htmlspecialchars($value ?? '') . '</td>';
                    }
                }
                $html .= '</tr>';
            }
            
            $html .= '</tbody></table>';
            $html .= '</div>';
            
            // Table footer with record count
            $html .= '<div style="text-align:right;font-size:9px;color:#7f8c8d;margin-top:5px;">';
            $html .= 'Showing ' . number_format($rowCount) . ' records';
            $html .= '</div>';
        } else {
            $html .= '<div style="text-align:center;padding:40px 0;color:#7f8c8d;">';
            $html .= '<p style="font-size:16px;">📭 No data available for the selected filters</p>';
            $html .= '<p style="font-size:12px;">Please adjust your filters and try again.</p>';
            $html .= '</div>';
        }
        
        // =============================================
        // Footer
        // =============================================
        $html .= '<div class="report-footer">';
        $html .= '<div class="footer-links">';
        $html .= '<span>© ' . date('Y') . ' ' . htmlspecialchars(SITE_NAME) . '</span>';
        $html .= '<span>|</span>';
        $html .= '<span>Generated by ADPS System</span>';
        $html .= '<span>|</span>';
        $html .= '<span>v' . (defined('APP_VERSION') ? APP_VERSION : '1.0.0') . '</span>';
        $html .= '</div>';
        $html .= '<div style="margin-top:3px;">This report is for official use only</div>';
        $html .= '</div>';
        
        $html .= '</body></html>';
        
        return $html;
    }
}