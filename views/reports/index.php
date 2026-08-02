<?php
// =============================================
// Reports - List View
// =============================================

// Load configuration
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/security.php';

// Include Report model
require_once __DIR__ . '/../../models/Report.php';  // Make sure this is included
require_once __DIR__ . '/../../models/DutyCategory.php';
require_once __DIR__ . '/../../models/Department.php';

// Check if class exists
if (!class_exists('Report')) {
    die('Report class not found. Please check the file path.');
}

// Initialize auth
$auth = new Auth();
if (!$auth->isLoggedIn() || $auth->getUser()['role'] === 'teacher') {
    redirect(SITE_URL . '/views/dashboard/teacher.php');
}

$user = $auth->getUser();
$role = $user['role'] ?? 'admin';

// Set page variables
$pageTitle = 'Reports';
$pageIcon = 'fas fa-file-alt';
$breadcrumb = [
    ['label' => 'Dashboard', 'url' => SITE_URL . '/views/dashboard/admin.php'],
    ['label' => 'Reports', 'active' => true]
];

$report = new Report();  // This should work now
$categories = DutyCategory::getActive();
$departments = Department::all(['status' => STATUS_ACTIVE]);

// Get filters
$reportType = $_GET['type'] ?? 'duty';
$format = $_GET['format'] ?? 'html';
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// Generate report based on type
$reportData = [];
switch ($reportType) {
    case 'duty':
        $reportData = $report->generateDutyReport([
            'start_date' => $start_date,
            'end_date' => $end_date,
            'department_id' => $_GET['department_id'] ?? null,
            'category_id' => $_GET['category_id'] ?? null,
            'status' => $_GET['status'] ?? null
        ]);
        break;
    case 'teacher':
        $reportData = $report->generateTeacherWorkloadReport([
            'start_date' => $start_date,
            'end_date' => $end_date,
            'department_id' => $_GET['department_id'] ?? null
        ]);
        break;
    case 'department':
        $reportData = $report->generateDepartmentReport([
            'start_date' => $start_date,
            'end_date' => $end_date
        ]);
        break;
    default:
        $reportData = $report->generateDutyReport([
            'start_date' => $start_date,
            'end_date' => $end_date
        ]);
        break;
}

// Export if requested - FIX: Check format and export
if (isset($_GET['format'])) {
    $format = $_GET['format'];
    
    if ($format === 'csv' && !empty($reportData['data'])) {
        $report->exportCSV($reportData['data']);
        exit;
    } elseif ($format === 'pdf' && !empty($reportData['data'])) {
        // Generate HTML for PDF
        $html = generateReportHTML($reportData, $reportType);
        $report->exportPDF($html);
        exit;
    }
}

// Include header
require_once __DIR__ . '/../../includes/header.php';
?>

<!-- Page Styles -->
<style>
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 15px;
        margin-bottom: 25px;
    }
    .stat-card {
        background: var(--bg-white);
        padding: 15px 20px;
        border-radius: var(--radius);
        box-shadow: var(--shadow);
        border: 1px solid var(--border-light);
        transition: var(--transition);
        position: relative;
        overflow: hidden;
    }
    .stat-card:hover {
        transform: translateY(-3px);
        box-shadow: var(--shadow-md);
    }
    .stat-card .number {
        font-size: 22px;
        font-weight: 700;
        color: var(--text-dark);
    }
    .stat-card .label {
        font-size: 12px;
        color: var(--text-muted);
    }
    .stat-card .icon {
        position: absolute;
        right: 15px;
        top: 15px;
        font-size: 28px;
        opacity: 0.15;
    }
    .stat-card .icon.blue { color: var(--info); }
    .stat-card .icon.green { color: var(--success); }
    .stat-card .icon.orange { color: var(--warning); }
    .stat-card .icon.red { color: var(--danger); }
    
    .report-filters {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        align-items: flex-end;
    }
    .report-filters .form-group {
        flex: 1;
        min-width: 140px;
    }
    .report-filters .form-group label {
        font-size: 0.8rem;
        font-weight: 600;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.03em;
        margin-bottom: 4px;
    }
    .report-filters .form-group .form-control,
    .report-filters .form-group .form-select {
        font-size: 0.875rem;
    }
    
    .report-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }
    
    .report-table th {
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        font-weight: 600;
    }
    .report-table td {
        font-size: 0.85rem;
    }
    
    @media (max-width: 768px) {
        .report-filters {
            flex-direction: column;
            align-items: stretch;
        }
        .report-filters .form-group {
            min-width: 100%;
        }
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        .report-actions {
            justify-content: center;
        }
    }
</style>

<!-- Report Filters -->
<div class="card mb-4">
    <div class="card-header">
        <i class="fas fa-filter me-2"></i> Report Filters
    </div>
    <div class="card-body">
        <form method="GET" class="report-filters">
            <div class="form-group">
                <label>Report Type</label>
                <select name="type" class="form-select form-select-sm">
                    <option value="duty" <?php echo $reportType === 'duty' ? 'selected' : ''; ?>>Duty Report</option>
                    <option value="teacher" <?php echo $reportType === 'teacher' ? 'selected' : ''; ?>>Teacher Workload</option>
                    <option value="department" <?php echo $reportType === 'department' ? 'selected' : ''; ?>>Department Report</option>
                </select>
            </div>
            <div class="form-group">
                <label>Date From</label>
                <input type="date" name="start_date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($start_date); ?>">
            </div>
            <div class="form-group">
                <label>Date To</label>
                <input type="date" name="end_date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($end_date); ?>">
            </div>
            <div class="form-group">
                <label>Department</label>
                <select name="department_id" class="form-select form-select-sm">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?php echo $dept['id']; ?>" <?php echo ($_GET['department_id'] ?? '') == $dept['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($dept['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Category</label>
                <select name="category_id" class="form-select form-select-sm">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>" <?php echo ($_GET['category_id'] ?? '') == $cat['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="flex: 0 0 auto;">
                <button type="submit" class="btn btn-primary btn-sm w-100">
                    <i class="fas fa-sync me-1"></i> Generate
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Report Statistics -->
<?php if (!empty($reportData['data'])): ?>
    <div class="stats-grid">
        <div class="stat-card">
            <div class="icon blue"><i class="fas fa-file-alt"></i></div>
            <div class="number"><?php echo number_format(count($reportData['data'])); ?></div>
            <div class="label">Total Records</div>
        </div>
        <?php if (isset($reportData['statistics'])): ?>
            <div class="stat-card">
                <div class="icon green"><i class="fas fa-check-circle"></i></div>
                <div class="number"><?php echo number_format($reportData['statistics']['completed'] ?? 0); ?></div>
                <div class="label">Completed</div>
            </div>
            <div class="stat-card">
                <div class="icon orange"><i class="fas fa-clock"></i></div>
                <div class="number"><?php echo number_format($reportData['statistics']['pending'] ?? 0); ?></div>
                <div class="label">Pending</div>
            </div>
            <div class="stat-card">
                <div class="icon red"><i class="fas fa-times-circle"></i></div>
                <div class="number"><?php echo number_format($reportData['statistics']['missed'] ?? 0); ?></div>
                <div class="label">Missed</div>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<!-- Report Data -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-table me-2"></i> Report Data</span>
        <div class="report-actions">
            <?php if (!empty($reportData['data'])): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['format' => 'csv'])); ?>" 
                   class="btn btn-sm btn-success">
                    <i class="fas fa-file-csv me-1"></i> CSV
                </a>
                <!-- <a href="?<?php echo http_build_query(array_merge($_GET, ['format' => 'pdf'])); ?>" 
                   class="btn btn-sm btn-danger">
                    <i class="fas fa-file-pdf me-1"></i> PDF
                </a> -->
            <?php endif; ?>
            <button onclick="window.print()" class="btn btn-sm btn-secondary">
                <i class="fas fa-print me-1"></i> Print
            </button>
        </div>
    </div>
    <div class="card-body p-0">
        <?php if (empty($reportData['data'])): ?>
            <div class="text-center py-5">
                <i class="fas fa-chart-pie fa-3x text-muted d-block mb-3"></i>
                <h5 class="text-muted">No data available</h5>
                <p class="text-muted">Please adjust your filters and try again.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover report-table mb-0">
                    <thead>
                        <tr>
                            <?php foreach (array_keys($reportData['data'][0]) as $key): ?>
                                <th><?php echo str_replace('_', ' ', ucfirst($key)); ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reportData['data'] as $row): ?>
                            <tr>
                                <?php foreach ($row as $value): ?>
                                    <td><?php echo htmlspecialchars($value ?? ''); ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    <div class="card-footer text-muted d-flex justify-content-between align-items-center">
        <span>
            <i class="far fa-clock me-1"></i>
            Generated: <?php echo $reportData['generated_at'] ?? date('Y-m-d H:i:s'); ?>
        </span>
        <span>
            <?php if (!empty($reportData['data'])): ?>
                <i class="fas fa-database me-1"></i>
                <?php echo number_format(count($reportData['data'])); ?> records
            <?php endif; ?>
        </span>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// =============================================
// Export functionality
// =============================================
document.querySelectorAll('.export-btn').forEach(btn => {
    btn.addEventListener('click', function(e) {
        const format = this.dataset.format;
        const url = new URL(window.location.href);
        url.searchParams.set('format', format);
        window.location.href = url.toString();
    });
});

// =============================================
// Auto-submit on filter change
// =============================================
document.querySelectorAll('.filter-auto-submit').forEach(el => {
    el.addEventListener('change', function() {
        this.closest('form').submit();
    });
});

// =============================================
// Print functionality
// =============================================
function printReport() {
    window.print();
}

// =============================================
// Keyboard shortcuts
// =============================================
document.addEventListener('keydown', function(e) {
    // Ctrl+P = Print
    if (e.ctrlKey && e.key === 'p') {
        e.preventDefault();
        printReport();
    }
});

console.log('📊 Reports loaded successfully!');
console.log('📋 Report Type: <?php echo $reportType; ?>');
console.log('📅 Date Range: <?php echo $start_date; ?> to <?php echo $end_date; ?>');
</script>

<?php
// Include footer
require_once __DIR__ . '/../../includes/footer.php';
?>