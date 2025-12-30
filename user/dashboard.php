<?php  
// Protect this page - require authentication
require_once 'auth_check.php';
require_once 'config.php';

// Get current user data from session
$current_user = getCurrentEmployee();
$user = [
    'name' => 'John Doe',
    'role' => 'Employee',
    'competency_score' => 85,
    'competency_change' => '+5% from last month',
    'pending_requests' => 3,
    'pending_attention' => 2,
    'learning_progress' => 72,
    'courses_in_progress' => 3,
    'career_level' => 3,
    'career_next' => 'Senior Developer'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>ESS Dashboard</title>
  
  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  
  <style>
    :root {
      --primary-color: #4bc5ec;
      --primary-dark: #3ba3cc;
      --sidebar-width: 280px;
      --sidebar-collapsed: 80px;
    }

    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background-color: #f8f9fa;
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
      transition: width 0.3s ease;
      z-index: 1000;
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
      border-radius: 8px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--primary-color);
      font-size: 1.2rem;
    }

    .brand-text {
      font-size: 1.25rem;
      font-weight: 600;
      margin: 0;
    }

    .sidebar.collapsed .brand-text {
      display: none;
    }

    .sidebar-toggle {
      background: none;
      border: none;
      color: white;
      font-size: 1.1rem;
      cursor: pointer;
      padding: 8px;
      border-radius: 4px;
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
      border: 3px solid white;
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
      margin: 0 1rem 8px;
    }

    .nav-link {
      color: rgba(255,255,255,0.9) !important;
      padding: 12px 16px;
      border-radius: 8px;
      display: flex;
      align-items: center;
      gap: 12px;
      text-decoration: none;
      transition: all 0.2s;
      border: none;
      background: none;
      width: 100%;
      text-align: left;
    }

    .nav-link:hover {
      background: rgba(255,255,255,0.15);
      color: white !important;
    }

    .nav-link.active {
      background: white;
      color: var(--primary-color) !important;
      font-weight: 600;
    }

    .nav-icon {
      width: 20px;
      text-align: center;
      font-size: 1rem;
    }

    .sidebar.collapsed .nav-text {
      display: none;
    }

    .dropdown-menu-custom {
      background: rgba(255,255,255,0.1);
      border: none;
      margin-left: 1rem;
      margin-top: 4px;
    }

    .dropdown-menu-custom .nav-link {
      font-size: 0.9rem;
      padding: 8px 16px;
    }

    .nav-section {
      padding: 0.5rem 1rem;
      margin-top: 1rem;
    }

    .status-indicator {
      animation: pulse 2s infinite;
    }

    @keyframes pulse {
      0% { opacity: 1; }
      50% { opacity: 0.5; }
      100% { opacity: 1; }
    }

    /* Main Content */
    .main-content {
      margin-left: var(--sidebar-width);
      transition: margin-left 0.3s ease;
      min-height: 100vh;
    }

    .sidebar.collapsed + .main-content {
      margin-left: var(--sidebar-collapsed);
    }

    .content-frame {
      width: 100%;
      height: 100vh;
      border: none;
    }

    /* Responsive */
    @media (max-width: 768px) {
      .sidebar {
        transform: translateX(-100%);
      }
      
      .sidebar.show {
        transform: translateX(0);
      }
      
      .main-content {
        margin-left: 0;
      }
      
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
    }

  </style>
</head>
<body>
  <!-- Mobile Overlay -->
  <div class="mobile-overlay" id="mobileOverlay"></div>

  <!-- Sidebar -->
  <aside class="sidebar d-flex flex-column" id="sidebar">
    <!-- Brand -->
    <div class="sidebar-brand">
      <div class="brand-content">
        <div class="brand-logo">
          <i class="fas fa-building"></i>
        </div>
        <h1 class="brand-text">ESS System</h1>
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
        <a href="profile.php" target="contentFrame" class="nav-link">
          <i class="nav-icon fas fa-user"></i>
          <span class="nav-text">Profile</span>
        </a>
      </div>
      
      <div class="nav-item">
        <a href="dashboard_home.php" target="contentFrame" class="nav-link active">
          <i class="nav-icon fas fa-tachometer-alt"></i>
          <span class="nav-text">Dashboard</span>
        </a>
      </div>
      
      <div class="nav-item">
        <a href="competency.php" target="contentFrame" class="nav-link">
          <i class="nav-icon fas fa-chart-line"></i>
          <span class="nav-text">Competency</span>
        </a>
      </div>
      
      <div class="nav-item">
        <a href="learning.php" target="contentFrame" class="nav-link">
          <i class="nav-icon fas fa-book-open"></i>
          <span class="nav-text">Learning</span>
        </a>
      </div>
      
      <div class="nav-item">
        <a href="training.php" target="contentFrame" class="nav-link">
          <i class="nav-icon fas fa-chalkboard-teacher"></i>
          <span class="nav-text">Training</span>
        </a>
      </div>
      
      <div class="nav-item">
        <a href="succession.php" target="contentFrame" class="nav-link">
          <i class="nav-icon fas fa-sitemap"></i>
          <span class="nav-text">Succession</span>
        </a>
      </div>

      <!-- Time & Attendance Dropdown -->
      <div class="nav-item">
        <button class="nav-link" type="button" data-bs-toggle="collapse" data-bs-target="#attendanceMenu">
          <i class="nav-icon fas fa-clock"></i>
          <span class="nav-text">Time & Attendance</span>
          <i class="fas fa-chevron-down ms-auto"></i>
        </button>
        <div class="collapse dropdown-menu-custom" id="attendanceMenu">
          <a href="shift_schedule.php" target="contentFrame" class="nav-link">
            <i class="nav-icon fas fa-calendar-days"></i>
            <span class="nav-text">Shift & Schedule</span>
          </a>
          <a href="timesheet.php" target="contentFrame" class="nav-link">
            <i class="nav-icon fas fa-table"></i>
            <span class="nav-text">Timesheet</span>
          </a>
        </div>
      </div>

      <div class="nav-item">
        <a href="leave.php" target="contentFrame" class="nav-link">
          <i class="nav-icon fas fa-plane-departure"></i>
          <span class="nav-text">Leave</span>
        </a>
      </div>
      
      <div class="nav-item">
        <a href="claims.php" target="contentFrame" class="nav-link">
          <i class="nav-icon fas fa-money-bill-wave"></i>
          <span class="nav-text">Claims</span>
        </a>
      </div>
      
      <div class="nav-item">
        <a href="payroll.php" target="contentFrame" class="nav-link">
          <i class="nav-icon fas fa-dollar-sign"></i>
          <span class="nav-text">Payroll</span>
        </a>
      </div>

      <?php if (hasRole('hr') || hasRole('admin')): ?>
      <!-- HR/Admin Only Section -->
      <div class="nav-item mt-3">
        <div class="nav-section">
          <small class="text-light opacity-50">ADMINISTRATION</small>
        </div>
      </div>
      
      <div class="nav-item">
        <a href="hr2_admin/employee_management.php" target="_blank" class="nav-link">
          <i class="nav-icon fas fa-users-cog"></i>
          <span class="nav-text">Employee Management</span>
        </a>
      </div>
      
      <div class="nav-item">
        <a href="hr2_admin/reports.php" target="_blank" class="nav-link">
          <i class="nav-icon fas fa-chart-bar"></i>
          <span class="nav-text">Reports</span>
        </a>
      </div>
      <?php endif; ?>

      <!-- Logout -->
      <div class="nav-item mt-auto">
        <a href="logout.php" class="nav-link" onclick="return confirm('Are you sure you want to logout?')">
          <i class="nav-icon fas fa-sign-out-alt"></i>
          <span class="nav-text">Logout</span>
        </a>
      </div>
    </nav>

    <!-- Footer -->
    <div class="mt-auto p-3 text-center border-top border-light border-opacity-25">
      <div class="d-flex align-items-center justify-content-center mb-2">
        <div class="status-indicator bg-success rounded-circle me-2" style="width: 8px; height: 8px;"></div>
        <small class="text-light opacity-75">Online</small>
      </div>
      <small class="text-light opacity-50">© 2025 ESS System</small>
    </div>
  </aside>

  <!-- Main Content -->
  <main class="main-content">
    <iframe name="contentFrame" src="dashboard_home.php" class="content-frame"></iframe>
  </main>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  
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

    // Close sidebar on mobile when clicking overlay
    document.getElementById('mobileOverlay').addEventListener('click', function() {
      toggleSidebar();
    });

    // Handle active navigation links
    document.addEventListener('DOMContentLoaded', function() {
      const navLinks = document.querySelectorAll('.nav-link[href]');
      
      navLinks.forEach(link => {
        link.addEventListener('click', function() {
          // Remove active class from all links
          navLinks.forEach(l => l.classList.remove('active'));
          // Add active class to clicked link
          this.classList.add('active');
        });
      });

      // Handle responsive sidebar on window resize
      window.addEventListener('resize', function() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('mobileOverlay');
        
        if (window.innerWidth > 768) {
          sidebar.classList.remove('show');
          overlay.classList.remove('show');
        }
      });
    });

    // Handle iframe loading with loading indicator
    window.addEventListener('load', function() {
      const iframe = document.querySelector('iframe[name="contentFrame"]');
      
      iframe.addEventListener('load', function() {
        // Add any loading completion logic here
        console.log('Page loaded successfully');
      });
    });
  </script>
</body>
</html>
