<?php
// Get current user if not already set
if (!isset($current_user)) {
    $current_user = getCurrentEmployee();
}
?>

<style>
:root {
    --primary-color: #4bc5ec;
    --primary-dark: #3ba3cc;
    --sidebar-width: 280px;
    --sidebar-collapsed: 80px;
}

/* Custom Sidebar */
.sidebar {
    width: var(--sidebar-width);
    background: linear-gradient(180deg, var(--primary-color), #94dcf4);
    color: white;
    position: fixed;
    top: 0;
    left: 0;
    height: 100vh;
    z-index: 1000;
    transition: width 0.3s ease;
    box-shadow: 2px 0 10px rgba(0,0,0,0.1);
}

.sidebar.collapsed {
    width: var(--sidebar-collapsed);
}

.sidebar-brand {
    padding: 1.5rem;
    border-bottom: 1px solid rgba(255,255,255,0.2);
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.brand-content {
    display: flex;
    align-items: center;
    gap: 12px;
}

.brand-logo {
    width: 40px;
    height: 40px;
    background: white;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    flex-shrink: 0;
    color: var(--primary-color);
    font-size: 1.2rem;
}

.brand-text {
    font-size: 1.5rem;
    font-weight: 700;
    margin: 0;
    color: white;
}

.sidebar.collapsed .brand-text {
    display: none;
}

.sidebar-toggle {
    background: none;
    border: none;
    color: white;
    font-size: 1.2rem;
    padding: 8px;
    border-radius: 5px;
    cursor: pointer;
    transition: background 0.2s;
}

.sidebar-toggle:hover {
    background: rgba(255,255,255,0.1);
}

.sidebar-profile {
    padding: 1.5rem;
    text-align: center;
    border-bottom: 1px solid rgba(255,255,255,0.2);
}

.profile-avatar {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    margin-bottom: 12px;
}

.sidebar.collapsed .profile-info {
    display: none;
}

.sidebar-nav {
    padding: 1rem 0;
    flex: 1;
    overflow-y: auto;
}

.nav-item {
    margin: 0.25rem 0;
}

.nav-link {
    display: flex;
    align-items: center;
    padding: 0.75rem 1.5rem;
    color: rgba(255,255,255,0.8);
    text-decoration: none;
    transition: all 0.3s ease;
    border-radius: 0;
}

.nav-link:hover {
    background: rgba(255,255,255,0.1);
    color: white;
    text-decoration: none;
}

.nav-link.active {
    background: rgba(255,255,255,0.2);
    color: white;
    border-right: 4px solid white;
}

.nav-icon {
    width: 20px;
    text-align: center;
    margin-right: 12px;
    font-size: 1rem;
}

.sidebar.collapsed .nav-text {
    display: none;
}

/* Main Content */
.main-content {
    margin-left: var(--sidebar-width);
    transition: margin-left 0.3s ease;
    min-height: 100vh;
    padding: 2rem;
}

.sidebar.collapsed + .main-content {
    margin-left: var(--sidebar-collapsed);
}

/* Mobile Overlay */
.mobile-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 999;
    display: none;
}

.mobile-overlay.show {
    display: block;
}

/* Loading Animation */
.page-loader {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(255, 255, 255, 0.9);
    display: none;
    justify-content: center;
    align-items: center;
    z-index: 9999;
    backdrop-filter: blur(3px);
}

.page-loader.show {
    display: flex;
}

.loader-content {
    text-align: center;
    color: var(--primary-color);
}

.spinner {
    width: 50px;
    height: 50px;
    border: 4px solid #f3f3f3;
    border-top: 4px solid var(--primary-color);
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin: 0 auto 1rem;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.loader-text {
    font-size: 1.1rem;
    font-weight: 600;
    margin-top: 1rem;
}

/* Navigation Link Loading State */
.nav-link.loading {
    opacity: 0.7;
    pointer-events: none;
}

.nav-link.loading .nav-icon {
    animation: pulse 1s ease-in-out infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}
</style>

<!-- Mobile Overlay -->
<div class="mobile-overlay" id="mobileOverlay"></div>

<!-- Page Loader -->
<div class="page-loader" id="pageLoader">
    <div class="loader-content">
        <div class="spinner"></div>
        <div class="loader-text">Loading...</div>
    </div>
</div>

<!-- Sidebar -->
<aside class="sidebar d-flex flex-column" id="sidebar">
    <!-- Brand -->
    <div class="sidebar-brand">
        <div class="brand-content">
            <div class="brand-logo">
                <i class="fas fa-building"></i>
            </div>
            <h1 class="brand-text">ESS</h1>
        </div>
        <button class="sidebar-toggle" onclick="toggleSidebar()">
            <i class="fas fa-bars"></i>
        </button>
    </div>

    <!-- Profile -->
    <div class="sidebar-profile">
        <img src="https://cdn-icons-png.flaticon.com/512/3135/3135715.png" alt="Profile" class="profile-avatar">
        <div class="profile-info">
            <h6 class="mb-1"><?= htmlspecialchars($current_user['full_name'] ?? 'User') ?></h6>
            <small class="text-light opacity-75"><?= htmlspecialchars($current_user['position'] ?? 'Employee') ?></small>
            <small class="d-block text-light opacity-50"><?= htmlspecialchars($current_user['department'] ?? '') ?></small>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="sidebar-nav d-flex flex-column">
        <div class="nav-item">
            <a href="dashboard.php" class="nav-link">
                <i class="nav-icon fas fa-tachometer-alt"></i>
                <span class="nav-text">Dashboard</span>
            </a>
        </div>
        
        <div class="nav-item">
            <a href="profile.php" class="nav-link">
                <i class="nav-icon fas fa-user"></i>
                <span class="nav-text">Profile</span>
            </a>
        </div>
        
        <div class="nav-item">
            <a href="competency.php" class="nav-link">
                <i class="nav-icon fas fa-chart-line"></i>
                <span class="nav-text">Competency</span>
            </a>
        </div>
        
        <div class="nav-item">
            <a href="learning.php" class="nav-link">
                <i class="nav-icon fas fa-book-open"></i>
                <span class="nav-text">Learning</span>
            </a>
        </div>
        
        <div class="nav-item">
            <a href="training.php" class="nav-link">
                <i class="nav-icon fas fa-chalkboard-teacher"></i>
                <span class="nav-text">Training</span>
            </a>
        </div>
        
        <div class="nav-item">
            <a href="succession.php" class="nav-link">
                <i class="nav-icon fas fa-sitemap"></i>
                <span class="nav-text">Succession</span>
            </a>
        </div>
        
        <div class="nav-item">
            <a href="shift_schedule.php" class="nav-link">
                <i class="nav-icon fas fa-calendar-days"></i>
                <span class="nav-text">Shift & Schedule</span>
            </a>
        </div>
        
        <div class="nav-item">
            <a href="timesheet.php" class="nav-link">
                <i class="nav-icon fas fa-table"></i>
                <span class="nav-text">Timesheet</span>
            </a>
        </div>
        
        <div class="nav-item">
            <a href="leave.php" class="nav-link">
                <i class="nav-icon fas fa-plane-departure"></i>
                <span class="nav-text">Leave</span>
            </a>
        </div>
        
        <div class="nav-item">
            <a href="claims.php" class="nav-link">
                <i class="nav-icon fas fa-money-bill-wave"></i>
                <span class="nav-text">Claims</span>
            </a>
        </div>
        
        <div class="nav-item">
            <a href="payroll.php" class="nav-link">
                <i class="nav-icon fas fa-dollar-sign"></i>
                <span class="nav-text">Payroll</span>
            </a>
        </div>

        <!-- Logout at bottom -->
        <div class="nav-item mt-auto">
            <a href="logout.php" class="nav-link">
                <i class="nav-icon fas fa-sign-out-alt"></i>
                <span class="nav-text">Logout</span>
            </a>
        </div>
    </nav>
</aside>

<script>
// Sidebar toggle functionality
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('mobileOverlay');
    
    if (window.innerWidth <= 768) {
        // Mobile behavior
        sidebar.classList.toggle('show');
        overlay.classList.toggle('show');
    } else {
        // Desktop behavior
        sidebar.classList.toggle('collapsed');
    }
}

// Close sidebar when clicking overlay
document.addEventListener('DOMContentLoaded', function() {
    const overlay = document.getElementById('mobileOverlay');
    if (overlay) {
        overlay.addEventListener('click', function() {
            toggleSidebar();
        });
    }

    // Add active state to current page
    const currentPage = window.location.pathname.split('/').pop();
    const navLinks = document.querySelectorAll('.nav-link');
    
    navLinks.forEach(link => {
        if (link.getAttribute('href') === currentPage) {
            link.classList.add('active');
        } else {
            link.classList.remove('active');
        }
    });

    // Add loading animation to navigation links
    const pageLoader = document.getElementById('pageLoader');
    
    navLinks.forEach(link => {
        // Skip logout link and current page
        if (link.getAttribute('href') !== 'logout.php' && !link.classList.contains('active')) {
            link.addEventListener('click', function(e) {
                // Add loading state to clicked link
                this.classList.add('loading');
                
                // Show page loader
                if (pageLoader) {
                    pageLoader.classList.add('show');
                }
                
                // Update loader text based on the page being loaded
                const loaderText = document.querySelector('.loader-text');
                const pageName = this.querySelector('.nav-text').textContent;
                if (loaderText) {
                    loaderText.textContent = `Loading ${pageName}...`;
                }
                
                // Let the browser navigate normally
                // The loader will be hidden when the new page loads
            });
        }
    });
});
</script>
