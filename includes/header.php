<?php
// =============================================
// Header with Dynamic Sidebar - Complete
// =============================================

// Check if user is logged in
if (!$auth->isLoggedIn()) {
    redirect(SITE_URL . '/views/auth/login.php');
}

$user = $auth->getUser();
$role = $user['role'] ?? 'teacher';
$unreadCount = 0;

// Get notification count
try {
    require_once __DIR__ . '/../models/Notification.php';
    $unreadCount = Notification::getUnreadCount($user['id']);
} catch (Exception $e) {
    $unreadCount = 0;
}

// Get current page for active state
$currentPage = basename($_SERVER['PHP_SELF']);
$currentDir = basename(dirname($_SERVER['PHP_SELF']));

// Get teacher info if available
$teacherName = $user['full_name'];
$teacherInitial = substr($user['full_name'], 0, 1);
$profilePhoto = SITE_URL . '/assets/images/default-avatar.png';
$hasProfilePhoto = false;

// Check if teacher has profile photo
try {
    $db = Database::getInstance();
    $stmt = $db->prepare("SELECT profile_photo FROM teachers WHERE email = ?");
    $stmt->execute([$user['email']]);
    $teacher = $stmt->fetch();
    if ($teacher && $teacher['profile_photo']) {
        $profilePhoto = SITE_URL . '/uploads/profiles/' . $teacher['profile_photo'];
        $hasProfilePhoto = true;
    }
} catch (Exception $e) {
    // Teacher table might not exist or no photo
}

// Determine sidebar menu based on role
function getSidebarMenu($role, $currentPage, $currentDir) {
    $menu = [];
    
    // Common menu items for all users
    $commonMenu = [
        'dashboard' => [
            'label' => 'Dashboard',
            'icon' => 'fas fa-th-large',
            'url' => SITE_URL . '/views/dashboard/' . ($role === 'teacher' ? 'teacher' : 'admin') . '.php',
            'active' => $currentDir === 'dashboard'
        ],
        'duties' => [
            'label' => 'Duties',
            'icon' => 'fas fa-tasks',
            'url' => SITE_URL . '/views/duties/',
            'active' => $currentDir === 'duties' && $currentPage !== 'calendar.php'
        ],
        'calendar' => [
            'label' => 'Calendar',
            'icon' => 'fas fa-calendar-alt',
            'url' => SITE_URL . '/views/duties/calendar.php',
            'active' => $currentPage === 'calendar.php'
        ],
        'swaps' => [
            'label' => 'Swap Requests',
            'icon' => 'fas fa-exchange-alt',
            'url' => SITE_URL . '/views/swaps/',
            'active' => $currentDir === 'swaps'
        ],
        'notifications' => [
            'label' => 'Notifications',
            'icon' => 'fas fa-bell',
            'url' => SITE_URL . '/views/notifications/',
            'active' => $currentDir === 'notifications'
        ],
        'profile' => [
            'label' => 'Profile',
            'icon' => 'fas fa-user',
            'url' => SITE_URL . '/views/profile/',
            'active' => $currentDir === 'profile'
        ]
    ];
    
    // Admin specific menu items
    $adminMenu = [
        'teachers' => [
            'label' => 'Teachers',
            'icon' => 'fas fa-user-friends',
            'url' => SITE_URL . '/views/teachers/',
            'active' => $currentDir === 'teachers'
        ],
        'departments' => [
            'label' => 'Departments',
            'icon' => 'fas fa-building',
            'url' => SITE_URL . '/views/departments/',
            'active' => $currentDir === 'departments'
        ],
        'categories' => [
            'label' => 'Duty Categories',
            'icon' => 'fas fa-tag',
            'url' => SITE_URL . '/views/categories/',
            'active' => $currentDir === 'categories'
        ],
        'reports' => [
            'label' => 'Reports',
            'icon' => 'fas fa-file-alt',
            'url' => SITE_URL . '/views/reports/',
            'active' => $currentDir === 'reports'
        ],
        'settings' => [
            'label' => 'Settings',
            'icon' => 'fas fa-cog',
            'url' => SITE_URL . '/views/settings/',
            'active' => $currentDir === 'settings'
        ],
        'assign_duty' => [
            'label' => 'Assign Duty',
            'icon' => 'fas fa-plus-circle',
            'url' => SITE_URL . '/views/duties/create.php',
            'active' => $currentPage === 'create.php' && $currentDir === 'duties'
        ],
        'schedule' => [
            'label' => 'Generate Schedule',
            'icon' => 'fas fa-calendar-check',
            'url' => SITE_URL . '/views/duties/schedule.php',
            'active' => $currentPage === 'schedule.php'
        ]
    ];
    
    // Teacher specific menu items
    $teacherMenu = [
        'my_duties' => [
            'label' => 'My Duties',
            'icon' => 'fas fa-list',
            'url' => SITE_URL . '/views/duties/my-duties.php',
            'active' => $currentPage === 'my-duties.php'
        ],
        'request_swap' => [
            'label' => 'Request Swap',
            'icon' => 'fas fa-exchange-alt',
            'url' => SITE_URL . '/views/swaps/create.php',
            'active' => $currentPage === 'create.php' && $currentDir === 'swaps'
        ]
    ];
    
    // Build menu based on role
    if ($role === 'super_admin' || $role === 'admin') {
        $menu = array_merge($commonMenu, $adminMenu);
    } else {
        $menu = array_merge($commonMenu, $teacherMenu);
    }
    
    // Add logout at the end
    $menu['logout'] = [
        'label' => 'Logout',
        'icon' => 'fas fa-sign-out-alt',
        'url' => SITE_URL . '/views/auth/logout.php',
        'active' => false,
        'class' => 'text-danger'
    ];
    
    return $menu;
}

$sidebarMenu = getSidebarMenu($role, $currentPage, $currentDir);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? 'ADPS'; ?> - <?php echo SITE_NAME; ?></title>
    
    <!-- Meta Tags -->
    <meta name="description" content="Automated Duty Processing System">
    <meta name="author" content="ADPS">
    <meta name="csrf-token" content="<?php echo CSRF::generateToken(); ?>">
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome 6 -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link href="<?php echo SITE_URL; ?>/assets/css/styles.css" rel="stylesheet">
    
    <!-- Page Specific CSS -->
    <?php if (isset($pageCSS)): ?>
        <?php foreach ($pageCSS as $css): ?>
            <link href="<?php echo SITE_URL; ?>/assets/css/<?php echo $css; ?>.css" rel="stylesheet">
        <?php endforeach; ?>
    <?php endif; ?>
    
    <style>
        /* =============================================
           Sidebar Styles - Scrollable
           ============================================= */
        .sidebar {
            background: var(--primary-gradient);
            height: 100vh;
            padding: 0;
            position: fixed;
            width: 260px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            z-index: 1030;
            top: 0;
            left: 0;
            bottom: 0;
            display: flex;
            flex-direction: column;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
        }
        
        .sidebar-brand {
            text-align: center;
            padding: 20px 15px 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            flex-shrink: 0;
            background: var(--primary);
        }
        
        .sidebar-brand .logo-icon {
            font-size: 42px;
            color: var(--accent);
            display: block;
            margin-bottom: 8px;
        }
        
        .sidebar-brand h3 {
            color: #fff;
            font-weight: 700;
            margin: 0;
            font-size: 1.2rem;
            letter-spacing: -0.5px;
        }
        
        .sidebar-brand p {
            color: rgba(255, 255, 255, 0.4);
            font-size: 0.7rem;
            margin: 0;
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        
        /* Sidebar Navigation - Scrollable */
        .sidebar-nav {
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
            padding: 10px 0 20px;
            scrollbar-width: thin;
            scrollbar-color: var(--accent) transparent;
        }
        
        .sidebar-nav::-webkit-scrollbar {
            width: 4px;
        }
        
        .sidebar-nav::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.05);
        }
        
        .sidebar-nav::-webkit-scrollbar-thumb {
            background: var(--accent);
            border-radius: 10px;
        }
        
        .sidebar-nav .nav {
            padding: 0 10px;
            flex-direction: column;
        }
        
        .sidebar-nav .nav-section {
            color: rgba(255, 255, 255, 0.3);
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 10px 15px 5px;
            font-weight: 600;
        }
        
        .sidebar-nav .nav-link {
            color: rgba(255, 255, 255, 0.6);
            padding: 10px 15px;
            border-radius: 10px;
            margin: 2px 0;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
            font-size: 0.875rem;
            position: relative;
            text-decoration: none;
            width: 100%;
            border: none;
            background: transparent;
        }
        
        .sidebar-nav .nav-link i {
            width: 20px;
            text-align: center;
            font-size: 1rem;
            flex-shrink: 0;
        }
        
        .sidebar-nav .nav-link:hover {
            color: #fff;
            background: rgba(255, 255, 255, 0.05);
            transform: translateX(3px);
        }
        
        .sidebar-nav .nav-link.active {
            color: var(--primary);
            background: var(--accent);
            box-shadow: 0 4px 15px rgba(255, 215, 0, 0.2);
        }
        
        .sidebar-nav .nav-link.active i {
            color: var(--primary);
        }
        
        .sidebar-nav .nav-link .badge {
            margin-left: auto;
            background: var(--danger);
            color: #fff;
            font-size: 0.65rem;
            padding: 2px 8px;
            border-radius: 12px;
            animation: pulse-badge 2s infinite;
        }
        
        @keyframes pulse-badge {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }
        
        .sidebar-nav .nav-link.text-danger {
            color: rgba(255, 100, 100, 0.7);
        }
        
        .sidebar-nav .nav-link.text-danger:hover {
            color: #ff6b6b;
            background: rgba(255, 0, 0, 0.1);
        }
        
        .sidebar-nav .nav-link.text-danger.active {
            color: #fff;
            background: var(--danger);
        }
        
        /* Sidebar Footer */
        .sidebar-footer {
            flex-shrink: 0;
            padding: 15px 20px;
            text-align: center;
            border-top: 1px solid rgba(255, 255, 255, 0.05);
            background: var(--primary);
        }
        
        .sidebar-footer small {
            color: rgba(255, 255, 255, 0.2);
            font-size: 0.65rem;
        }
        
        /* =============================================
           Main Content
           ============================================= */
        .main-content {
            margin-left: 260px;
            padding: 20px;
            min-height: 100vh;
            background: var(--bg-primary);
            transition: margin-left 0.3s ease;
        }
        
        /* =============================================
           Top Header
           ============================================= */
        .top-header {
            background: var(--bg-white);
            padding: 12px 25px;
            border-radius: 15px;
            box-shadow: var(--shadow-sm);
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 1px solid var(--border-light);
            position: sticky;
            top: 0;
            z-index: 1020;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .top-header .page-title {
            display: flex;
            align-items: center;
            gap: 10px;
            flex: 1;
            min-width: 200px;
        }
        
        .top-header .page-title h4 {
            margin: 0;
            font-weight: 600;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .top-header .page-title .breadcrumb {
            margin: 0;
            padding: 0;
            background: transparent;
            font-size: 0.8rem;
        }
        
        .top-header .page-title .breadcrumb-item a {
            color: var(--text-muted);
            text-decoration: none;
        }
        
        .top-header .page-title .breadcrumb-item a:hover {
            color: var(--accent);
        }
        
        .top-header .page-title .breadcrumb-item.active {
            color: var(--text-dark);
        }
        
        .top-header .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
            flex-shrink: 0;
        }
        
        .top-header .user-info .user-name {
            font-weight: 500;
            font-size: 0.9rem;
        }
        
        .top-header .user-info .user-role {
            font-size: 0.7rem;
            color: var(--text-muted);
        }
        
        .top-header .user-info .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--accent-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            transition: var(--transition);
            border: 2px solid transparent;
            background-size: cover;
            background-position: center;
            flex-shrink: 0;
        }
        
        .top-header .user-info .avatar:hover {
            border-color: var(--accent);
            transform: scale(1.05);
        }
        
        .top-header .user-info .notif-icon {
            position: relative;
            cursor: pointer;
            font-size: 1.2rem;
            color: var(--text-muted);
            transition: var(--transition);
            text-decoration: none;
        }
        
        .top-header .user-info .notif-icon:hover {
            color: var(--accent);
        }
        
        .top-header .user-info .notif-icon .badge {
            position: absolute;
            top: -8px;
            right: -8px;
            font-size: 0.6rem;
            padding: 2px 6px;
            animation: pulse-badge 2s infinite;
        }
        
        /* =============================================
           Mobile Toggle
           ============================================= */
        .sidebar-toggle {
            display: none;
            background: none;
            border: none;
            color: var(--text-dark);
            font-size: 1.5rem;
            cursor: pointer;
            padding: 5px;
            transition: var(--transition);
        }
        
        .sidebar-toggle:hover {
            color: var(--accent);
        }
        
        /* =============================================
           Sidebar Overlay for Mobile
           ============================================= */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1029;
            transition: opacity 0.3s ease;
        }
        
        .sidebar-overlay.active {
            display: block;
        }
        
        /* =============================================
           Dropdown
           ============================================= */
        .user-dropdown {
            position: relative;
        }
        
        .user-dropdown .dropdown-menu {
            border-radius: 12px;
            border: none;
            box-shadow: var(--shadow-lg);
            padding: 8px 0;
            min-width: 200px;
        }
        
        .user-dropdown .dropdown-item {
            padding: 8px 20px;
            font-size: 0.875rem;
            transition: var(--transition);
        }
        
        .user-dropdown .dropdown-item:hover {
            background: var(--bg-light);
            color: var(--accent);
        }
        
        .user-dropdown .dropdown-item i {
            width: 20px;
            margin-right: 10px;
        }
        
        .user-dropdown .dropdown-divider {
            margin: 5px 0;
        }
        
        /* =============================================
           Dark Mode Toggle
           ============================================= */
        .dark-mode-toggle {
            background: none;
            border: none;
            color: var(--text-muted);
            font-size: 1.1rem;
            cursor: pointer;
            transition: var(--transition);
            padding: 5px;
        }
        
        .dark-mode-toggle:hover {
            color: var(--accent);
        }
        
        /* =============================================
           Responsive Styles
           ============================================= */
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
                width: 280px;
            }
            
            .sidebar.active {
                transform: translateX(0);
                box-shadow: 0 0 30px rgba(0, 0, 0, 0.3);
            }
            
            .main-content {
                margin-left: 0;
                padding: 15px;
            }
            
            .sidebar-toggle {
                display: block;
            }
            
            .top-header {
                padding: 10px 15px;
            }
            
            .top-header .page-title h4 {
                font-size: 1rem;
            }
            
            .sidebar-overlay.active {
                display: block;
            }
            
            .sidebar-brand {
                padding: 15px 15px 12px;
            }
            
            .sidebar-brand .logo-icon {
                font-size: 36px;
            }
        }
        
        @media (max-width: 576px) {
            .top-header .user-info .user-name {
                display: none;
            }
            
            .top-header .user-info .user-role {
                display: none;
            }
            
            .top-header .page-title .breadcrumb {
                font-size: 0.7rem;
            }
            
            .main-content {
                padding: 10px;
            }
            
            .top-header {
                padding: 8px 12px;
                border-radius: 10px;
            }
            
            .top-header .page-title h4 {
                font-size: 0.9rem;
            }
            
            .sidebar {
                width: 260px;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar Overlay (Mobile) -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <!-- Brand -->
        <div class="sidebar-brand">
            <span class="logo-icon">
                <img src="<?php echo SITE_URL; ?>/assets/images/logo.jpeg" alt="logo" width="40" height="40">
            </span>
            <h3>ADPS</h3>
            <p><?php echo ucfirst(str_replace('_', ' ', $role)); ?> Panel</p>
        </div>
        
        <!-- Scrollable Navigation -->
        <div class="sidebar-nav">
            <nav class="nav">
                <?php 
                $currentSection = '';
                foreach ($sidebarMenu as $key => $item):
                    // Add section headers
                    if ($key === 'teachers' && $currentSection !== 'management') {
                        $currentSection = 'management';
                        echo '<div class="nav-section">Management</div>';
                    }
                    if ($key === 'my_duties' && $currentSection !== 'personal') {
                        $currentSection = 'personal';
                        echo '<div class="nav-section">Personal</div>';
                    }
                    if ($key === 'notifications' && $currentSection !== 'general') {
                        $currentSection = 'general';
                        echo '<div class="nav-section">General</div>';
                    }
                    if ($key === 'logout' && $currentSection !== 'account') {
                        $currentSection = 'account';
                        echo '<div class="nav-section">Account</div>';
                    }
                ?>
                    <a class="nav-link <?php echo $item['active'] ? 'active' : ''; ?> <?php echo $item['class'] ?? ''; ?>" 
                       href="<?php echo $item['url']; ?>"
                       <?php echo $item['active'] ? 'aria-current="page"' : ''; ?>>
                        <i class="<?php echo $item['icon']; ?>"></i>
                        <span><?php echo $item['label']; ?></span>
                        <?php if ($key === 'notifications' && $unreadCount > 0): ?>
                            <span class="badge"><?php echo $unreadCount; ?></span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </nav>
        </div>
        
        <!-- Sidebar Footer -->
        <div class="sidebar-footer">
            <small>
                <?php echo SITE_NAME; ?><br>
                v<?php echo defined('APP_VERSION') ? APP_VERSION : '1.0.0'; ?>
            </small>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Header -->
        <div class="top-header">
            <div class="page-title">
                <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle Sidebar">
                    <i class="fas fa-bars"></i>
                </button>
                <div>
                    <h4>
                        <?php if (isset($pageIcon)): ?>
                            <i class="<?php echo $pageIcon; ?> me-2"></i>
                        <?php endif; ?>
                        <?php echo $pageTitle ?? 'Dashboard'; ?>
                    </h4>
                    <?php if (isset($breadcrumb) && is_array($breadcrumb)): ?>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item">
                                    <a href="<?php echo SITE_URL; ?>/views/dashboard/<?php echo $role === 'teacher' ? 'teacher' : 'admin'; ?>.php">
                                        <i class="fas fa-home"></i>
                                    </a>
                                </li>
                                <?php foreach ($breadcrumb as $item): ?>
                                    <?php if (isset($item['active']) && $item['active']): ?>
                                        <li class="breadcrumb-item active"><?php echo $item['label']; ?></li>
                                    <?php else: ?>
                                        <li class="breadcrumb-item">
                                            <a href="<?php echo $item['url'] ?? '#'; ?>"><?php echo $item['label']; ?></a>
                                        </li>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </ol>
                        </nav>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="user-info">
                <button class="dark-mode-toggle" id="darkModeToggle" title="Toggle Dark Mode" aria-label="Toggle Dark Mode">
                    <i class="fas fa-moon"></i>
                </button>
                
                <a href="<?php echo SITE_URL; ?>/views/notifications/" class="notif-icon" title="Notifications" aria-label="Notifications">
                    <i class="fas fa-bell"></i>
                    <?php if ($unreadCount > 0): ?>
                        <span class="badge bg-danger"><?php echo $unreadCount; ?></span>
                    <?php endif; ?>
                </a>
                
                <div class="user-dropdown">
                    <div class="avatar <?php echo $hasProfilePhoto ? 'has-image' : ''; ?>" 
                         style="<?php echo $hasProfilePhoto ? 'background-image: url(' . $profilePhoto . ');' : ''; ?>"
                         data-bs-toggle="dropdown" 
                         aria-expanded="false"
                         title="<?php echo htmlspecialchars($user['full_name']); ?>"
                         role="button"
                         tabindex="0">
                        <?php if (!$hasProfilePhoto): ?>
                            <?php echo $teacherInitial; ?>
                        <?php endif; ?>
                    </div>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <div class="dropdown-item-text">
                                <strong><?php echo htmlspecialchars($user['full_name']); ?></strong>
                                <br>
                                <small class="text-muted"><?php echo htmlspecialchars($user['email']); ?></small>
                                <br>
                                <span class="badge bg-<?php echo $role === 'super_admin' ? 'danger' : ($role === 'admin' ? 'warning' : 'info'); ?> mt-1">
                                    <?php echo ucfirst(str_replace('_', ' ', $role)); ?>
                                </span>
                            </div>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item" href="<?php echo SITE_URL; ?>/views/profile/">
                                <i class="fas fa-user"></i> My Profile
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="<?php echo SITE_URL; ?>/views/profile/#security">
                                <i class="fas fa-shield-alt"></i> Security
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item text-danger" href="<?php echo SITE_URL; ?>/views/auth/logout.php">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        
        <!-- Page Content -->
        <div class="page-content">
            <!-- Flash Messages -->
            <?php 
            $flash = getFlashMessage();
            if ($flash): 
            ?>
                <div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show" role="alert">
                    <i class="fas fa-<?php echo $flash['type'] === 'success' ? 'check-circle' : ($flash['type'] === 'error' ? 'exclamation-circle' : 'info-circle'); ?> me-2"></i>
                    <?php echo $flash['message']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>