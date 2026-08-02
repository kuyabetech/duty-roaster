<?php
// =============================================
// Duty Calendar View
// =============================================

// Load configuration
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../models/Duty.php';
require_once __DIR__ . '/../../models/DutyCategory.php';

// Initialize auth
$auth = new Auth();
if (!$auth->isLoggedIn()) {
    redirect(SITE_URL . '/views/auth/login.php');
}

$user = $auth->getUser();
$role = $user['role'] ?? 'teacher';

// Set page variables
$pageTitle = 'Duty Calendar';
$pageIcon = 'fas fa-calendar-alt';
$breadcrumb = [
    ['label' => 'Dashboard', 'url' => SITE_URL . '/views/dashboard/' . ($role === 'teacher' ? 'teacher' : 'admin') . '.php'],
    ['label' => 'Duties', 'url' => SITE_URL . '/views/duties/'],
    ['label' => 'Calendar', 'active' => true]
];

// Get current month/year from URL or use current
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

// Validate month and year
if ($month < 1 || $month > 12) {
    $month = (int)date('n');
}
if ($year < 2000 || $year > 2100) {
    $year = (int)date('Y');
}

// Get database connection
$db = Database::getInstance();

// Get teacher ID if user is a teacher
$teacherId = null;
if ($role === ROLE_TEACHER) {
    $stmt = $db->prepare("SELECT id FROM teachers WHERE email = ?");
    $stmt->execute([$user['email']]);
    $teacher = $stmt->fetch();
    if ($teacher) {
        $teacherId = $teacher['id'];
    }
}

// Get duties for the month
$startDate = date('Y-m-d', strtotime("$year-$month-01"));
$endDate = date('Y-m-d', strtotime("$year-$month-01 +1 month -1 day"));

$filters = [
    'start_date' => $startDate,
    'end_date' => $endDate
];

// If teacher, only show their duties
if ($teacherId) {
    $filters['teacher_id'] = $teacherId;
}

$duties = Duty::all($filters);

// Group duties by date
$dutiesByDate = [];
foreach ($duties as $duty) {
    $date = $duty['duty_date'];
    if (!isset($dutiesByDate[$date])) {
        $dutiesByDate[$date] = [];
    }
    $dutiesByDate[$date][] = $duty;
}

// Get duty categories for color mapping
$categories = DutyCategory::getActive();
$categoryColors = [];
$categoryNames = [];
foreach ($categories as $cat) {
    $categoryColors[$cat['id']] = $cat['color'] ?? '#6c757d';
    $categoryNames[$cat['id']] = $cat['name'];
}

// Calendar generation
$firstDayOfMonth = date('N', strtotime("$year-$month-01"));
$daysInMonth = date('t', strtotime("$year-$month-01"));
$today = date('Y-m-d');

// Previous and next month navigation
$prevMonth = $month - 1;
$prevYear = $year;
if ($prevMonth < 1) {
    $prevMonth = 12;
    $prevYear--;
}

$nextMonth = $month + 1;
$nextYear = $year;
if ($nextMonth > 12) {
    $nextMonth = 1;
    $nextYear++;
}

// Month name
$monthNames = [
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
];
$monthName = $monthNames[$month];

// Statistics
$totalDuties = count($duties);
$completedDuties = 0;
$pendingDuties = 0;
$acceptedDuties = 0;
$missedDuties = 0;
$cancelledDuties = 0;

foreach ($duties as $duty) {
    switch ($duty['status']) {
        case DUTY_COMPLETED:
            $completedDuties++;
            break;
        case DUTY_PENDING:
            $pendingDuties++;
            break;
        case DUTY_ACCEPTED:
            $acceptedDuties++;
            break;
        case DUTY_MISSED:
            $missedDuties++;
            break;
        case DUTY_CANCELLED:
            $cancelledDuties++;
            break;
    }
}

// Get today's duties
$todayDuties = $dutiesByDate[$today] ?? [];

// Include header
require_once __DIR__ . '/../../includes/header.php';
?>

<!-- Page Styles -->
<style>
    .calendar-wrapper {
        background: var(--bg-white);
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow);
        overflow: hidden;
        border: 1px solid var(--border-light);
    }
    
    .calendar-header {
        background: var(--primary-gradient);
        color: #fff;
        padding: 20px 25px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .calendar-header h3 {
        margin: 0;
        color: #fff;
        font-weight: 600;
    }
    
    .calendar-header .btn-nav {
        background: rgba(255, 255, 255, 0.15);
        border: none;
        color: #fff;
        padding: 8px 16px;
        border-radius: var(--radius);
        transition: var(--transition);
        display: inline-flex;
        align-items: center;
        gap: 8px;
        text-decoration: none;
        font-weight: 500;
    }
    
    .calendar-header .btn-nav:hover {
        background: var(--accent);
        color: var(--primary);
        transform: translateY(-2px);
    }
    
    .calendar-grid {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: 0;
        background: var(--border-color);
    }
    
    .calendar-day-header {
        background: var(--bg-primary);
        padding: 12px 8px;
        text-align: center;
        font-weight: 600;
        font-size: 0.8rem;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    
    .calendar-day {
        background: var(--bg-white);
        min-height: 120px;
        padding: 8px 6px;
        position: relative;
        transition: var(--transition);
        cursor: default;
        border: 1px solid var(--border-light);
    }
    
    .calendar-day:hover {
        background: var(--bg-light);
    }
    
    .calendar-day.weekend {
        background: #fafafa;
    }
    
    .calendar-day.weekend .day-number {
        color: var(--danger);
    }
    
    .calendar-day .day-number {
        font-weight: 600;
        font-size: 0.85rem;
        color: var(--text-dark);
        display: inline-block;
        width: 30px;
        height: 30px;
        line-height: 30px;
        text-align: center;
        border-radius: 50%;
        margin-bottom: 4px;
        transition: var(--transition);
    }
    
    .calendar-day .day-number.today {
        background: var(--accent);
        color: var(--primary);
        font-weight: 700;
    }
    
    .calendar-day .day-number.has-duty {
        position: relative;
    }
    
    .calendar-day .day-number.has-duty::after {
        content: '';
        position: absolute;
        bottom: 2px;
        left: 50%;
        transform: translateX(-50%);
        width: 4px;
        height: 4px;
        border-radius: 50%;
        background: var(--accent);
    }
    
    .calendar-day .day-number.other-month {
        color: var(--text-light);
    }
    
    .calendar-day .duty-item {
        font-size: 0.7rem;
        padding: 2px 6px;
        border-radius: 4px;
        margin-bottom: 2px;
        cursor: pointer;
        transition: var(--transition);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        color: #fff;
        position: relative;
        border: 1px solid rgba(255, 255, 255, 0.2);
    }
    
    .calendar-day .duty-item:hover {
        transform: scale(1.03);
        box-shadow: var(--shadow-md);
        z-index: 1;
    }
    
    .calendar-day .duty-item .duty-time {
        font-weight: 600;
        margin-right: 3px;
    }
    
    .calendar-day .duty-item .duty-status-dot {
        display: inline-block;
        width: 6px;
        height: 6px;
        border-radius: 50%;
        margin-right: 3px;
        border: 1px solid rgba(255,255,255,0.3);
    }
    
    .calendar-day .duty-more {
        font-size: 0.65rem;
        color: var(--text-muted);
        padding: 2px 6px;
        cursor: pointer;
        display: inline-block;
        transition: var(--transition);
    }
    
    .calendar-day .duty-more:hover {
        color: var(--accent);
        text-decoration: underline;
    }
    
    .calendar-day.empty {
        background: var(--bg-light);
        min-height: 120px;
    }
    
    .calendar-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
        gap: 15px;
        margin-bottom: 25px;
    }
    
    .stat-item {
        background: var(--bg-white);
        padding: 15px 20px;
        border-radius: var(--radius);
        text-align: center;
        box-shadow: var(--shadow-sm);
        border: 1px solid var(--border-light);
        transition: var(--transition);
    }
    
    .stat-item:hover {
        transform: translateY(-3px);
        box-shadow: var(--shadow-md);
    }
    
    .stat-item .number {
        font-size: 24px;
        font-weight: 700;
        display: block;
    }
    
    .stat-item .label {
        font-size: 0.75rem;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }
    
    .stat-item.total .number { color: var(--info); }
    .stat-item.completed .number { color: var(--success); }
    .stat-item.pending .number { color: var(--warning); }
    .stat-item.accepted .number { color: var(--info); }
    .stat-item.missed .number { color: var(--danger); }
    
    /* Legend */
    .legend {
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        padding: 15px 20px;
        background: var(--bg-light);
        border-top: 1px solid var(--border-color);
    }
    
    .legend-item {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 0.78rem;
        color: var(--text-muted);
    }
    
    .legend-item .color-box {
        width: 14px;
        height: 14px;
        border-radius: 4px;
        border: 1px solid rgba(0,0,0,0.05);
    }
    
    /* Calendar actions */
    .calendar-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-top: 20px;
        justify-content: center;
    }
    
    .calendar-actions .btn {
        min-width: 140px;
    }
    
    /* Responsive */
    @media (max-width: 992px) {
        .calendar-day {
            min-height: 100px;
            padding: 6px 4px;
        }
        .calendar-day .day-number {
            font-size: 0.75rem;
            width: 26px;
            height: 26px;
            line-height: 26px;
        }
        .calendar-day .duty-item {
            font-size: 0.6rem;
            padding: 1px 4px;
        }
        .calendar-header h3 {
            font-size: 1.1rem;
        }
        .calendar-stats {
            grid-template-columns: repeat(3, 1fr);
        }
    }
    
    @media (max-width: 768px) {
        .calendar-day {
            min-height: 80px;
            padding: 4px 3px;
        }
        .calendar-day .day-number {
            font-size: 0.7rem;
            width: 22px;
            height: 22px;
            line-height: 22px;
        }
        .calendar-day .duty-item {
            font-size: 0.55rem;
            padding: 1px 3px;
        }
        .calendar-day .duty-more {
            font-size: 0.55rem;
        }
        .calendar-day-header {
            font-size: 0.65rem;
            padding: 6px 4px;
        }
        .calendar-header {
            padding: 12px 15px;
        }
        .calendar-header h3 {
            font-size: 0.95rem;
        }
        .calendar-stats {
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
        }
        .stat-item .number {
            font-size: 20px;
        }
        .calendar-actions .btn {
            min-width: 100px;
            font-size: 0.8rem;
        }
    }
    
    @media (max-width: 480px) {
        .calendar-day {
            min-height: 60px;
            padding: 3px 2px;
        }
        .calendar-day .day-number {
            font-size: 0.6rem;
            width: 18px;
            height: 18px;
            line-height: 18px;
        }
        .calendar-day .duty-item {
            font-size: 0.45rem;
            padding: 1px 2px;
        }
        .calendar-day .duty-more {
            font-size: 0.45rem;
        }
        .calendar-day-header {
            font-size: 0.55rem;
            padding: 4px 2px;
        }
        .calendar-header {
            padding: 10px 12px;
        }
        .calendar-header h3 {
            font-size: 0.8rem;
        }
        .calendar-header .btn-nav {
            padding: 4px 10px;
            font-size: 0.7rem;
        }
        .calendar-stats {
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }
        .stat-item {
            padding: 10px 12px;
        }
        .stat-item .number {
            font-size: 18px;
        }
        .stat-item .label {
            font-size: 0.65rem;
        }
        .calendar-actions .btn {
            min-width: 80px;
            font-size: 0.7rem;
            padding: 6px 10px;
        }
        .legend {
            gap: 8px;
            padding: 10px 12px;
        }
        .legend-item {
            font-size: 0.65rem;
        }
        .legend-item .color-box {
            width: 10px;
            height: 10px;
        }
    }
    
    /* Tooltip */
    .duty-tooltip {
        position: fixed;
        background: var(--primary);
        color: #fff;
        padding: 12px 16px;
        border-radius: var(--radius);
        font-size: 0.8rem;
        max-width: 280px;
        z-index: 1000;
        box-shadow: var(--shadow-lg);
        display: none;
        pointer-events: none;
        border: 1px solid rgba(255,255,255,0.1);
    }
    
    .duty-tooltip .duty-title {
        font-weight: 600;
        margin-bottom: 4px;
        font-size: 0.9rem;
    }
    
    .duty-tooltip .duty-detail {
        color: rgba(255, 255, 255, 0.8);
        font-size: 0.75rem;
        padding: 2px 0;
    }
    
    .duty-tooltip .duty-status {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 0.65rem;
        margin-top: 4px;
        font-weight: 600;
    }
    
    .duty-tooltip .duty-status.pending { background: var(--warning); color: var(--primary); }
    .duty-tooltip .duty-status.accepted { background: var(--info); color: #fff; }
    .duty-tooltip .duty-status.completed { background: var(--success); color: #fff; }
    .duty-tooltip .duty-status.missed { background: var(--danger); color: #fff; }
    .duty-tooltip .duty-status.cancelled { background: var(--secondary); color: #fff; }
    .duty-tooltip .duty-status.rejected { background: var(--danger); color: #fff; }
</style>

<!-- Statistics -->
<div class="calendar-stats">
    <div class="stat-item total">
        <span class="number"><?php echo $totalDuties; ?></span>
        <span class="label">Total Duties</span>
    </div>
    <div class="stat-item completed">
        <span class="number"><?php echo $completedDuties; ?></span>
        <span class="label">Completed</span>
    </div>
    <div class="stat-item pending">
        <span class="number"><?php echo $pendingDuties; ?></span>
        <span class="label">Pending</span>
    </div>
    <div class="stat-item accepted">
        <span class="number"><?php echo $acceptedDuties; ?></span>
        <span class="label">Accepted</span>
    </div>
    <div class="stat-item missed">
        <span class="number"><?php echo $missedDuties; ?></span>
        <span class="label">Missed</span>
    </div>
</div>

<!-- Today's Duties Alert -->
<?php if (!empty($todayDuties)): ?>
    <div class="alert alert-info alert-dismissible fade show mb-4">
        <div class="d-flex align-items-center flex-wrap gap-2">
            <i class="fas fa-bell me-2"></i>
            <strong>Today's Duties (<?php echo count($todayDuties); ?>):</strong>
            <?php foreach ($todayDuties as $duty): ?>
                <span class="badge" style="background: <?php echo $categoryColors[$duty['category_id']] ?? '#6c757d'; ?>; color: #fff; padding: 4px 10px;">
                    <?php echo date('h:i A', strtotime($duty['start_time'])); ?> - 
                    <?php echo htmlspecialchars($duty['category_name']); ?>
                </span>
            <?php endforeach; ?>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Calendar -->
<div class="calendar-wrapper">
    <!-- Calendar Header -->
    <div class="calendar-header">
        <a href="?month=<?php echo $prevMonth; ?>&year=<?php echo $prevYear; ?>" class="btn-nav">
            <i class="fas fa-chevron-left"></i>
        </a>
        <h3>
            <i class="fas fa-calendar-day me-2"></i>
            <?php echo $monthName . ' ' . $year; ?>
            <?php if ($month == date('n') && $year == date('Y')): ?>
                <span class="badge bg-accent text-dark ms-2">Current</span>
            <?php endif; ?>
        </h3>
        <a href="?month=<?php echo $nextMonth; ?>&year=<?php echo $nextYear; ?>" class="btn-nav">
            <i class="fas fa-chevron-right"></i>
        </a>
    </div>
    
    <!-- Calendar Grid -->
    <div class="calendar-grid">
        <!-- Day Headers -->
        <div class="calendar-day-header">Mon</div>
        <div class="calendar-day-header">Tue</div>
        <div class="calendar-day-header">Wed</div>
        <div class="calendar-day-header">Thu</div>
        <div class="calendar-day-header">Fri</div>
        <div class="calendar-day-header">Sat</div>
        <div class="calendar-day-header">Sun</div>
        
        <!-- Empty days before first day of month -->
        <?php 
        $firstDayOfWeek = $firstDayOfMonth;
        // Adjust for Monday start (1 = Monday, 7 = Sunday)
        if ($firstDayOfWeek == 7) $firstDayOfWeek = 0;
        ?>
        <?php for ($i = 1; $i < $firstDayOfWeek; $i++): ?>
            <div class="calendar-day empty"></div>
        <?php endfor; ?>
        
        <!-- Days of the month -->
        <?php for ($day = 1; $day <= $daysInMonth; $day++): ?>
            <?php 
            $date = date('Y-m-d', strtotime("$year-$month-$day"));
            $isToday = $date === $today;
            $dayDuties = $dutiesByDate[$date] ?? [];
            $isWeekend = date('N', strtotime($date)) >= 6;
            $hasDuty = !empty($dayDuties);
            ?>
            <div class="calendar-day <?php echo $isWeekend ? 'weekend' : ''; ?>" 
                 data-date="<?php echo $date; ?>">
                <div class="day-number <?php echo $isToday ? 'today' : ''; ?> <?php echo $hasDuty ? 'has-duty' : ''; ?>">
                    <?php echo $day; ?>
                </div>
                <?php 
                $displayCount = 0;
                $maxDisplay = 3;
                $totalDuties = count($dayDuties);
                ?>
                <?php foreach ($dayDuties as $index => $duty): ?>
                    <?php if ($index < $maxDisplay): ?>
                        <div class="duty-item" 
                             style="background: <?php echo $categoryColors[$duty['category_id']] ?? '#6c757d'; ?>"
                             data-duty-id="<?php echo $duty['id']; ?>"
                             data-duty-code="<?php echo htmlspecialchars($duty['duty_code']); ?>"
                             data-category="<?php echo htmlspecialchars($duty['category_name']); ?>"
                             data-teacher="<?php echo htmlspecialchars(($duty['first_name'] ?? '') . ' ' . ($duty['last_name'] ?? '')); ?>"
                             data-time="<?php echo date('h:i A', strtotime($duty['start_time'])); ?> - <?php echo date('h:i A', strtotime($duty['end_time'])); ?>"
                             data-status="<?php echo $duty['status']; ?>"
                             onclick="viewDuty(<?php echo $duty['id']; ?>)">
                            <span class="duty-time"><?php echo date('h:i', strtotime($duty['start_time'])); ?></span>
                            <?php echo htmlspecialchars( substr($duty['category_name'], 0, 12) ); ?>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
                <?php if ($totalDuties > $maxDisplay): ?>
                    <div class="duty-more" onclick="showAllDuties('<?php echo $date; ?>')">
                        +<?php echo $totalDuties - $maxDisplay; ?> more
                    </div>
                <?php endif; ?>
            </div>
        <?php endfor; ?>
    </div>
    
    <!-- Legend -->
    <div class="legend">
        <span class="legend-item">
            <span class="color-box" style="background: var(--success);"></span>
            Completed
        </span>
        <span class="legend-item">
            <span class="color-box" style="background: var(--warning);"></span>
            Pending
        </span>
        <span class="legend-item">
            <span class="color-box" style="background: var(--info);"></span>
            Accepted
        </span>
        <span class="legend-item">
            <span class="color-box" style="background: var(--danger);"></span>
            Missed / Rejected
        </span>
        <span class="legend-item">
            <span class="color-box" style="background: var(--secondary);"></span>
            Cancelled
        </span>
        <span class="legend-item">
            <span class="color-box" style="background: var(--accent);"></span>
            Today
        </span>
        <?php if (!empty($categoryColors)): ?>
            <span class="legend-item ms-2 border-start ps-2">
                <i class="fas fa-tag me-1"></i> Categories:
            </span>
            <?php 
            $shown = 0;
            foreach ($categoryColors as $catId => $color): 
                if ($shown >= 4) break;
                $name = $categoryNames[$catId] ?? 'Category';
            ?>
                <span class="legend-item">
                    <span class="color-box" style="background: <?php echo $color; ?>;"></span>
                    <?php echo htmlspecialchars(substr($name, 0, 10)); ?>
                </span>
            <?php 
                $shown++;
            endforeach; 
            if (count($categoryColors) > 4): ?>
                <span class="legend-item text-muted">+<?php echo count($categoryColors) - 4; ?> more</span>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Calendar Actions -->
<div class="calendar-actions">
    <a href="<?php echo SITE_URL; ?>/views/duties/" class="btn btn-outline-primary">
        <i class="fas fa-list me-2"></i> View List
    </a>
    <?php if ($role !== 'teacher'): ?>
        <a href="<?php echo SITE_URL; ?>/views/duties/create.php" class="btn btn-accent">
            <i class="fas fa-plus-circle me-2"></i> Assign Duty
        </a>
        <a href="<?php echo SITE_URL; ?>/views/duties/schedule.php" class="btn btn-info text-white">
            <i class="fas fa-calendar-alt me-2"></i> Generate Schedule
        </a>
    <?php endif; ?>
    <a href="?month=<?php echo date('n'); ?>&year=<?php echo date('Y'); ?>" class="btn btn-outline-secondary">
        <i class="fas fa-calendar-check me-2"></i> Today
    </a>
    <button onclick="window.print()" class="btn btn-outline-secondary">
        <i class="fas fa-print me-2"></i> Print
    </button>
</div>

<!-- Duty Details Modal -->
<div class="modal fade" id="dutyModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-tasks me-2" style="color: var(--accent);"></i>
                    Duty Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="dutyDetails">
                <div class="text-center py-5">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <a href="#" id="viewDutyBtn" class="btn btn-accent">
                    <i class="fas fa-eye me-2"></i> View Full Details
                </a>
            </div>
        </div>
    </div>
</div>

<!-- All Duties Modal -->
<div class="modal fade" id="allDutiesModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-calendar-day me-2" style="color: var(--accent);"></i>
                    Duties for <span id="modalDate"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="allDutiesList">
                <div class="text-center py-5">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// =============================================
// View Duty Details
// =============================================
function viewDuty(id) {
    const modal = new bootstrap.Modal(document.getElementById('dutyModal'));
    const details = document.getElementById('dutyDetails');
    
    details.innerHTML = '<div class="text-center py-5"><div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div></div>';
    modal.show();
    
    fetch('<?php echo SITE_URL; ?>/api/duties.php?action=get&id=' + id)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const duty = data.data;
                const statusColors = {
                    'pending': 'warning',
                    'accepted': 'info',
                    'rejected': 'danger',
                    'completed': 'success',
                    'missed': 'danger',
                    'cancelled': 'secondary'
                };
                
                const priorityColors = {
                    'low': 'info',
                    'normal': 'success',
                    'high': 'warning',
                    'urgent': 'danger'
                };
                
                details.innerHTML = `
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="text-muted">Duty Code</h6>
                            <p><strong><code>${duty.duty_code}</code></strong></p>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-muted">Status</h6>
                            <span class="badge bg-${statusColors[duty.status] || 'secondary'}">${duty.status.toUpperCase()}</span>
                        </div>
                    </div>
                    <hr>
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="text-muted">Category</h6>
                            <p><span class="badge" style="background: ${duty.category_color || '#6c757d'}">${duty.category_name}</span></p>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-muted">Teacher</h6>
                            <p><strong>${duty.first_name} ${duty.last_name}</strong></p>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="text-muted">Date</h6>
                            <p><i class="far fa-calendar-alt me-1"></i> ${duty.duty_date}</p>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-muted">Time</h6>
                            <p><i class="far fa-clock me-1"></i> ${duty.start_time} - ${duty.end_time}</p>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="text-muted">Location</h6>
                            <p>${duty.location || '<span class="text-muted">Not specified</span>'}</p>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-muted">Priority</h6>
                            <span class="badge bg-${priorityColors[duty.priority] || 'secondary'}">${duty.priority.toUpperCase()}</span>
                        </div>
                    </div>
                    ${duty.remarks ? `
                        <hr>
                        <div>
                            <h6 class="text-muted">Remarks</h6>
                            <p class="text-muted">${duty.remarks}</p>
                        </div>
                    ` : ''}
                `;
                
                document.getElementById('viewDutyBtn').href = '<?php echo SITE_URL; ?>/views/duties/view.php?id=' + id;
            } else {
                details.innerHTML = '<div class="alert alert-danger">Failed to load duty details</div>';
            }
        })
        .catch(error => {
            details.innerHTML = '<div class="alert alert-danger">Error loading duty details</div>';
            console.error('Error:', error);
        });
}

// =============================================
// Show All Duties for a Date
// =============================================
function showAllDuties(date) {
    const modal = new bootstrap.Modal(document.getElementById('allDutiesModal'));
    const list = document.getElementById('allDutiesList');
    const dateSpan = document.getElementById('modalDate');
    
    dateSpan.textContent = date;
    list.innerHTML = '<div class="text-center py-5"><div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div></div>';
    modal.show();
    
    fetch('<?php echo SITE_URL; ?>/api/duties.php?action=list&start_date=' + date + '&end_date=' + date)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data.length > 0) {
                let html = `
                    <div class="list-group">
                        <div class="list-group-item list-group-item-action active">
                            <div class="row fw-bold">
                                <div class="col-3">Time</div>
                                <div class="col-4">Category</div>
                                <div class="col-3">Teacher</div>
                                <div class="col-2">Status</div>
                            </div>
                        </div>
                `;
                data.data.forEach(duty => {
                    const statusColors = {
                        'pending': 'warning',
                        'accepted': 'info',
                        'rejected': 'danger',
                        'completed': 'success',
                        'missed': 'danger',
                        'cancelled': 'secondary'
                    };
                    html += `
                        <a href="<?php echo SITE_URL; ?>/views/duties/view.php?id=${duty.id}" class="list-group-item list-group-item-action">
                            <div class="row align-items-center">
                                <div class="col-3">
                                    <strong>${duty.start_time}</strong>
                                    <br>
                                    <small class="text-muted">${duty.end_time}</small>
                                </div>
                                <div class="col-4">
                                    <span class="badge" style="background: ${duty.category_color || '#6c757d'}">${duty.category_name}</span>
                                </div>
                                <div class="col-3">
                                    <small>${duty.first_name} ${duty.last_name}</small>
                                </div>
                                <div class="col-2">
                                    <span class="badge bg-${statusColors[duty.status] || 'secondary'}">${duty.status.toUpperCase()}</span>
                                </div>
                            </div>
                        </a>
                    `;
                });
                html += '</div>';
                list.innerHTML = html;
            } else {
                list.innerHTML = '<div class="text-center py-5"><i class="fas fa-calendar-times fa-3x text-muted d-block mb-3"></i><p class="text-muted">No duties on this day</p></div>';
            }
        })
        .catch(error => {
            list.innerHTML = '<div class="alert alert-danger">Error loading duties</div>';
            console.error('Error:', error);
        });
}

// =============================================
// Keyboard Navigation
// =============================================
document.addEventListener('keydown', function(e) {
    // Don't trigger if typing in input
    if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.tagName === 'SELECT') {
        return;
    }
    
    if (e.key === 'ArrowLeft') {
        window.location.href = '?month=<?php echo $prevMonth; ?>&year=<?php echo $prevYear; ?>';
    } else if (e.key === 'ArrowRight') {
        window.location.href = '?month=<?php echo $nextMonth; ?>&year=<?php echo $nextYear; ?>';
    } else if (e.key === 't' || e.key === 'T') {
        window.location.href = '?month=<?php echo date('n'); ?>&year=<?php echo date('Y'); ?>';
    }
});

// =============================================
// Tooltip for Duty Items
// =============================================
document.querySelectorAll('.duty-item').forEach(item => {
    item.addEventListener('mouseenter', function(e) {
        const tooltip = document.createElement('div');
        tooltip.className = 'duty-tooltip';
        const statusColors = {
            'pending': 'pending',
            'accepted': 'accepted',
            'rejected': 'rejected',
            'completed': 'completed',
            'missed': 'missed',
            'cancelled': 'cancelled'
        };
        tooltip.innerHTML = `
            <div class="duty-title">${this.dataset.category}</div>
            <div class="duty-detail"><i class="fas fa-user me-1"></i> ${this.dataset.teacher}</div>
            <div class="duty-detail"><i class="far fa-clock me-1"></i> ${this.dataset.time}</div>
            <span class="duty-status ${statusColors[this.dataset.status] || 'secondary'}">${this.dataset.status.toUpperCase()}</span>
        `;
        document.body.appendChild(tooltip);
        
        const rect = this.getBoundingClientRect();
        const tooltipRect = tooltip.getBoundingClientRect();
        let left = rect.left + rect.width / 2 - tooltipRect.width / 2;
        let top = rect.top - tooltipRect.height - 10;
        
        // Prevent tooltip from going off screen
        if (left < 10) left = 10;
        if (left + tooltipRect.width > window.innerWidth - 10) {
            left = window.innerWidth - tooltipRect.width - 10;
        }
        if (top < 10) {
            top = rect.bottom + 10;
        }
        
        tooltip.style.display = 'block';
        tooltip.style.left = left + 'px';
        tooltip.style.top = top + 'px';
        
        this._tooltip = tooltip;
    });
    
    item.addEventListener('mouseleave', function() {
        if (this._tooltip) {
            this._tooltip.remove();
            this._tooltip = null;
        }
    });
});

// =============================================
// Click on calendar day to show duties
// =============================================
document.querySelectorAll('.calendar-day:not(.empty)').forEach(day => {
    day.addEventListener('click', function(e) {
        // Don't trigger if clicking on a duty item or more link
        if (e.target.closest('.duty-item') || e.target.closest('.duty-more')) {
            return;
        }
        
        const date = this.dataset.date;
        const duties = this.querySelectorAll('.duty-item');
        if (duties.length > 0) {
            showAllDuties(date);
        }
    });
});

// =============================================
// Auto-close alerts
// =============================================
setTimeout(() => {
    document.querySelectorAll('.alert-dismissible').forEach(alert => {
        const closeBtn = alert.querySelector('.btn-close');
        if (closeBtn) {
            setTimeout(() => closeBtn.click(), 5000);
        }
    });
}, 1000);

console.log('📅 Calendar loaded successfully!');
console.log('📊 Total duties: <?php echo $totalDuties; ?>');
console.log('📆 Month: <?php echo $monthName . ' ' . $year; ?>');
</script>

<?php
// Include footer
require_once __DIR__ . '/../../includes/footer.php';
?>