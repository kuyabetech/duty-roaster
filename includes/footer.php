<?php
// =============================================
// Footer
// =============================================
?>

        </div> <!-- /.page-content -->
        
        <!-- Footer -->
        <div class="footer mt-4" style="
            text-align: center;
            padding: 20px 0;
            color: var(--text-muted);
            font-size: 0.8rem;
            border-top: 1px solid var(--border-color);
        ">
            <p class="mb-0">
                &copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>.
                All rights reserved.
                <span class="d-none d-sm-inline">|</span>
                <span class="d-none d-sm-inline">Version <?php echo defined('APP_VERSION') ? APP_VERSION : '1.0.0'; ?></span>
            </p>
        </div>
    </div> <!-- /.main-content -->
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo SITE_URL; ?>/assets/js/main.js"></script>
    
    <!-- Page Specific JS -->
    <?php if (isset($pageJS)): ?>
        <?php foreach ($pageJS as $js): ?>
            <script src="<?php echo SITE_URL; ?>/assets/js/<?php echo $js; ?>.js"></script>
        <?php endforeach; ?>
    <?php endif; ?>
    
    <script>
        // =============================================
        // Sidebar Toggle for Mobile
        // =============================================
        document.getElementById('sidebarToggle')?.addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
            document.getElementById('sidebarOverlay').classList.toggle('active');
        });
        
        document.getElementById('sidebarOverlay')?.addEventListener('click', function() {
            document.getElementById('sidebar').classList.remove('active');
            this.classList.remove('active');
        });
        
        // =============================================
        // Dark Mode Toggle
        // =============================================
        document.getElementById('darkModeToggle')?.addEventListener('click', function() {
            document.body.classList.toggle('dark-mode');
            const icon = this.querySelector('i');
            if (document.body.classList.contains('dark-mode')) {
                icon.className = 'fas fa-sun';
                localStorage.setItem('darkMode', 'enabled');
            } else {
                icon.className = 'fas fa-moon';
                localStorage.setItem('darkMode', 'disabled');
            }
        });
        
        // Check saved dark mode preference
        if (localStorage.getItem('darkMode') === 'enabled') {
            document.body.classList.add('dark-mode');
            document.querySelector('#darkModeToggle i').className = 'fas fa-sun';
        }
        
        // =============================================
        // Auto-close alerts after 5 seconds
        // =============================================
        document.querySelectorAll('.alert-dismissible').forEach(function(alert) {
            setTimeout(function() {
                const closeBtn = alert.querySelector('.btn-close');
                if (closeBtn) {
                    closeBtn.click();
                }
            }, 5000);
        });
        
        // =============================================
        // Set active nav link based on current page
        // =============================================
        document.querySelectorAll('.sidebar .nav-link').forEach(function(link) {
            if (link.href === window.location.href) {
                link.classList.add('active');
            }
        });
        
        // =============================================
        // Keyboard shortcuts
        // =============================================
        document.addEventListener('keydown', function(e) {
            // Ctrl + N = Notifications
            if (e.ctrlKey && e.key === 'n') {
                e.preventDefault();
                window.location.href = '<?php echo SITE_URL; ?>/views/notifications/';
            }
            // Ctrl + P = Profile
            if (e.ctrlKey && e.key === 'p') {
                e.preventDefault();
                window.location.href = '<?php echo SITE_URL; ?>/views/profile/';
            }
            // Ctrl + D = Dashboard
            if (e.ctrlKey && e.key === 'd') {
                e.preventDefault();
                window.location.href = '<?php echo SITE_URL; ?>/views/dashboard/<?php echo $role === 'teacher' ? 'teacher' : 'admin'; ?>.php';
            }
        });
        
        console.log('🚀 ADPS loaded successfully!');
        console.log('📋 Role: <?php echo $role; ?>');
        console.log('👤 User: <?php echo htmlspecialchars($user['full_name']); ?>');
    </script>
</body>
</html>