<?php  
// Protect this page - require authentication
require_once 'auth_check.php';
require_once 'config.php';

// Get current user data from session
$current_user = getCurrentEmployee();

// Initialize default data
$competency_data = ['competency_score' => 0];
$pending_data = ['pending_requests' => 0];
$learning_data = ['learning_progress' => 0, 'courses_in_progress' => 0];
$leave_data = ['leave_balance' => 0];
$overtime_data = ['overtime_hours' => 0];
$attendance_data = ['attendance_rate' => 0];
$cert_data = ['certificates' => 0];
$succession_data = ['target_position' => 'Not Set'];
$recent_activities = [];
$training_data = ['upcoming_trainings' => 0, 'completed_trainings' => 0];
$payroll_data = ['last_payroll' => 'N/A', 'net_pay' => 0];
$timesheet_data = ['hours_this_month' => 0, 'pending_approvals' => 0];

// Fetch data from database
try {
    $pdo = getPDO();
    $employee_id = getCurrentEmployeeId();
    
    // Calculate competency score
    $stmt = $pdo->prepare("SELECT AVG(proficiency_level) * 20 as competency_score FROM employee_competencies WHERE employee_id = ?");
    $stmt->execute([$employee_id]);
    $competency_data = $stmt->fetch() ?: $competency_data;

    // Get pending claims count
    $stmt = $pdo->prepare("SELECT COUNT(*) as pending_requests FROM claims WHERE employee_id = ? AND status = 'pending'");
    $stmt->execute([$employee_id]);
    $pending_data = $stmt->fetch() ?: $pending_data;

    // Get learning progress
    $stmt = $pdo->prepare("SELECT AVG(progress) as learning_progress, COUNT(CASE WHEN completion_date IS NULL THEN 1 END) as courses_in_progress FROM employee_courses WHERE employee_id = ?");
    $stmt->execute([$employee_id]);
    $learning_data = $stmt->fetch() ?: $learning_data;

    // Get leave balance
    $stmt = $pdo->prepare("SELECT SUM(total_days - used_days) as leave_balance FROM leave_balances WHERE employee_id = ?");
    $stmt->execute([$employee_id]);
    $leave_data = $stmt->fetch() ?: $leave_data;

    // Get overtime hours this month
    $stmt = $pdo->prepare("SELECT SUM(overtime_hours) as overtime_hours FROM attendance WHERE employee_id = ? AND MONTH(attendance_date) = MONTH(CURDATE()) AND YEAR(attendance_date) = YEAR(CURDATE())");
    $stmt->execute([$employee_id]);
    $overtime_data = $stmt->fetch() ?: $overtime_data;

    // Get attendance rate
    $stmt = $pdo->prepare("SELECT COUNT(CASE WHEN status = 'present' THEN 1 END) * 100.0 / COUNT(*) as attendance_rate FROM attendance WHERE employee_id = ? AND attendance_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
    $stmt->execute([$employee_id]);
    $attendance_data = $stmt->fetch() ?: $attendance_data;

    // Get certificates earned
    $stmt = $pdo->prepare("SELECT COUNT(*) as certificates FROM employee_courses WHERE employee_id = ? AND certificate_earned = 1");
    $stmt->execute([$employee_id]);
    $cert_data = $stmt->fetch() ?: $cert_data;

    // Get succession plan info
    $stmt = $pdo->prepare("SELECT target_position FROM succession_plans WHERE employee_id = ? LIMIT 1");
    $stmt->execute([$employee_id]);
    $succession_data = $stmt->fetch() ?: $succession_data;

    // Get training data
    $stmt = $pdo->prepare("SELECT COUNT(CASE WHEN status = 'registered' THEN 1 END) as upcoming_trainings, COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_trainings FROM employee_trainings WHERE employee_id = ?");
    $stmt->execute([$employee_id]);
    $training_data = $stmt->fetch() ?: $training_data;

    // Get payroll data
    $stmt = $pdo->prepare("SELECT DATE_FORMAT(pay_date, '%M %Y') as last_payroll, net_pay FROM payroll WHERE employee_id = ? ORDER BY pay_date DESC LIMIT 1");
    $stmt->execute([$employee_id]);
    $payroll_data = $stmt->fetch() ?: $payroll_data;

    // Get timesheet data
    $stmt = $pdo->prepare("SELECT SUM(hours_worked) as hours_this_month FROM timesheets WHERE employee_id = ? AND MONTH(timesheet_date) = MONTH(CURDATE()) AND YEAR(timesheet_date) = YEAR(CURDATE())");
    $stmt->execute([$employee_id]);
    $timesheet_result = $stmt->fetch();
    $timesheet_data['hours_this_month'] = $timesheet_result['hours_this_month'] ?? 0;

    // Get pending attention count
    $stmt = $pdo->prepare("SELECT COUNT(*) as pending_attention FROM claims WHERE employee_id = ? AND status = 'pending' AND amount > 1000");
    $stmt->execute([$employee_id]);
    $attention_data = $stmt->fetch() ?: ['pending_attention' => 0];

    // Get recent activity
    $stmt = $pdo->prepare("
        (SELECT 'course_completed' as type, CONCAT('Completed \"', c.title, '\" course') as text, ec.completion_date as activity_date, 'green' as color, 'fa-check-circle' as icon
         FROM employee_courses ec JOIN courses c ON ec.course_id = c.id 
         WHERE ec.employee_id = ? AND ec.completion_date IS NOT NULL ORDER BY ec.completion_date DESC LIMIT 2)
        UNION ALL
        (SELECT 'claim_submitted' as type, CONCAT('Submitted claim #', claim_number) as text, claim_date as activity_date, 'blue' as color, 'fa-receipt' as icon
         FROM claims WHERE employee_id = ? ORDER BY claim_date DESC LIMIT 2)
        ORDER BY activity_date DESC LIMIT 6
    ");
    $stmt->execute([$employee_id, $employee_id]);
    $recent_activities = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Dashboard query error: " . $e->getMessage());
    $attention_data = ['pending_attention' => 0];
}

// Format recent activities
$formatted_activities = [];
foreach ($recent_activities as $activity) {
    $time_diff = time() - strtotime($activity['activity_date']);
    if ($time_diff < 3600) {
        $time_ago = floor($time_diff / 60) . ' minutes ago';
    } elseif ($time_diff < 86400) {
        $time_ago = floor($time_diff / 3600) . ' hours ago';
    } else {
        $time_ago = floor($time_diff / 86400) . ' days ago';
    }
    
    $formatted_activities[] = [
        'icon' => $activity['icon'],
        'text' => $activity['text'],
        'time' => $time_ago,
        'color' => $activity['color']
    ];
}
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

    .dashboard-container {
      padding: 2rem;
    }

    .dashboard-header {
      background: linear-gradient(135deg, var(--primary-color), #94dcf4);
      color: white;
      border-radius: 15px;
      padding: 2rem;
      margin-bottom: 2rem;
      box-shadow: 0 4px 15px rgba(75, 197, 236, 0.3);
    }

    .module-card {
      background: white;
      border-radius: 15px;
      padding: 1.5rem;
      box-shadow: 0 4px 15px rgba(0,0,0,0.08);
      border: none;
      transition: transform 0.2s, box-shadow 0.2s;
      height: 100%;
      margin-bottom: 1.5rem;
    }

    .module-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    }

    .module-icon {
      width: 50px;
      height: 50px;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.5rem;
      margin-bottom: 1rem;
    }

    .module-title {
      font-size: 1.1rem;
      font-weight: 600;
      color: #2c3e50;
      margin-bottom: 0.5rem;
    }

    .module-value {
      font-size: 2rem;
      font-weight: 700;
      color: #2c3e50;
      margin-bottom: 0.5rem;
    }

    .module-label {
      color: #6c757d;
      font-size: 0.9rem;
      margin-bottom: 0.5rem;
    }

    .module-link {
      color: var(--primary-color);
      text-decoration: none;
      font-size: 0.9rem;
      font-weight: 500;
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
    }

    .module-link:hover {
      text-decoration: underline;
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
        <a href="profile.php" class="nav-link">
          <i class="nav-icon fas fa-user"></i>
          <span class="nav-text">Profile</span>
        </a>
      </div>
      
      <div class="nav-item">
        <a href="#dashboard" class="nav-link active" onclick="scrollToSection('dashboard'); return false;">
          <i class="nav-icon fas fa-tachometer-alt"></i>
          <span class="nav-text">Dashboard</span>
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

      <!-- Time & Attendance Dropdown -->
      <div class="nav-item">
        <button class="nav-link" type="button" data-bs-toggle="collapse" data-bs-target="#attendanceMenu">
          <i class="nav-icon fas fa-clock"></i>
          <span class="nav-text">Time & Attendance</span>
          <i class="fas fa-chevron-down ms-auto"></i>
        </button>
        <div class="collapse dropdown-menu-custom" id="attendanceMenu">
          <a href="shift_schedule.php" class="nav-link">
            <i class="nav-icon fas fa-calendar-days"></i>
            <span class="nav-text">Shift & Schedule</span>
          </a>
          <a href="timesheet.php" class="nav-link">
            <i class="nav-icon fas fa-table"></i>
            <span class="nav-text">Timesheet</span>
          </a>
        </div>
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
    <div class="dashboard-container" id="dashboard">
      <!-- Welcome Header -->
      <div class="dashboard-header">
        <div class="row align-items-center">
          <div class="col-md-8">
            <div class="d-flex align-items-center mb-3">
              <i class="fas fa-hand-sparkles me-3" style="font-size: 2rem;"></i>
              <div>
                <h1 class="mb-1">Welcome back, <?= htmlspecialchars($current_user['full_name'] ?? 'User') ?>!</h1>
                <p class="mb-0 opacity-75"><?= htmlspecialchars($current_user['position'] ?? 'Employee') ?> • <?= htmlspecialchars($current_user['department'] ?? '') ?></p>
              </div>
            </div>
            <p class="mb-0">Summary overview of all your modules</p>
          </div>
          <div class="col-md-4 text-end">
            <div class="d-flex align-items-center justify-content-end">
              <div class="me-3">
                <small class="d-block opacity-75">Employee ID</small>
                <strong><?= htmlspecialchars($current_user['employee_id'] ?? 'N/A') ?></strong>
              </div>
              <div class="bg-white bg-opacity-25 rounded-circle p-3">
                <i class="fas fa-user" style="font-size: 1.5rem;"></i>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="row g-4">
        <!-- Competency -->
        <div class="col-xl-3 col-md-6">
          <div class="module-card">
            <div class="module-icon bg-primary bg-opacity-10 text-primary">
              <i class="fas fa-chart-line"></i>
            </div>
            <div class="module-title">Competency</div>
            <div class="module-value"><?= round($competency_data['competency_score'] ?? 0) ?>%</div>
            <div class="module-label">Overall Competency Score</div>
            <a href="competency.php" class="module-link">
              View Details <i class="fas fa-arrow-right"></i>
            </a>
          </div>
        </div>

        <!-- Learning -->
        <div class="col-xl-3 col-md-6">
          <div class="module-card">
            <div class="module-icon bg-success bg-opacity-10 text-success">
              <i class="fas fa-book-open"></i>
            </div>
            <div class="module-title">Learning</div>
            <div class="module-value"><?= round($learning_data['learning_progress'] ?? 0) ?>%</div>
            <div class="module-label"><?= $learning_data['courses_in_progress'] ?? 0 ?> courses in progress</div>
            <a href="learning.php" class="module-link">
              View Details <i class="fas fa-arrow-right"></i>
            </a>
          </div>
        </div>

        <!-- Training -->
        <div class="col-xl-3 col-md-6">
          <div class="module-card">
            <div class="module-icon bg-info bg-opacity-10 text-info">
              <i class="fas fa-chalkboard-teacher"></i>
            </div>
            <div class="module-title">Training</div>
            <div class="module-value"><?= $training_data['upcoming_trainings'] ?? 0 ?></div>
            <div class="module-label"><?= $training_data['completed_trainings'] ?? 0 ?> completed</div>
            <a href="training.php" class="module-link">
              View Details <i class="fas fa-arrow-right"></i>
            </a>
          </div>
        </div>

        <!-- Succession -->
        <div class="col-xl-3 col-md-6">
          <div class="module-card">
            <div class="module-icon bg-warning bg-opacity-10 text-warning">
              <i class="fas fa-sitemap"></i>
            </div>
            <div class="module-title">Succession</div>
            <div class="module-value" style="font-size: 1.2rem;"><?= htmlspecialchars($succession_data['target_position'] ?? 'Not Set') ?></div>
            <div class="module-label">Target Position</div>
            <a href="succession.php" class="module-link">
              View Details <i class="fas fa-arrow-right"></i>
            </a>
          </div>
        </div>

        <!-- Time & Attendance -->
        <div class="col-xl-3 col-md-6">
          <div class="module-card">
            <div class="module-icon bg-secondary bg-opacity-10 text-secondary">
              <i class="fas fa-clock"></i>
            </div>
            <div class="module-title">Time & Attendance</div>
            <div class="module-value"><?= round($attendance_data['attendance_rate'] ?? 0, 1) ?>%</div>
            <div class="module-label">Attendance Rate (30 days)</div>
            <a href="timesheet.php" class="module-link">
              View Details <i class="fas fa-arrow-right"></i>
            </a>
          </div>
        </div>

        <!-- Timesheet -->
        <div class="col-xl-3 col-md-6">
          <div class="module-card">
            <div class="module-icon bg-primary bg-opacity-10 text-primary">
              <i class="fas fa-table"></i>
            </div>
            <div class="module-title">Timesheet</div>
            <div class="module-value"><?= round($timesheet_data['hours_this_month'] ?? 0, 1) ?></div>
            <div class="module-label">Hours this month</div>
            <a href="timesheet.php" class="module-link">
              View Details <i class="fas fa-arrow-right"></i>
            </a>
          </div>
        </div>

        <!-- Leave -->
        <div class="col-xl-3 col-md-6">
          <div class="module-card">
            <div class="module-icon bg-success bg-opacity-10 text-success">
              <i class="fas fa-plane-departure"></i>
            </div>
            <div class="module-title">Leave</div>
            <div class="module-value"><?= $leave_data['leave_balance'] ?? 0 ?></div>
            <div class="module-label">Days remaining</div>
            <a href="leave.php" class="module-link">
              View Details <i class="fas fa-arrow-right"></i>
            </a>
          </div>
        </div>

        <!-- Claims -->
        <div class="col-xl-3 col-md-6">
          <div class="module-card">
            <div class="module-icon bg-warning bg-opacity-10 text-warning">
              <i class="fas fa-money-bill-wave"></i>
            </div>
            <div class="module-title">Claims</div>
            <div class="module-value"><?= $pending_data['pending_requests'] ?? 0 ?></div>
            <div class="module-label">Pending requests</div>
            <a href="claims.php" class="module-link">
              View Details <i class="fas fa-arrow-right"></i>
            </a>
          </div>
        </div>

        <!-- Payroll -->
        <div class="col-xl-3 col-md-6">
          <div class="module-card">
            <div class="module-icon bg-info bg-opacity-10 text-info">
              <i class="fas fa-dollar-sign"></i>
            </div>
            <div class="module-title">Payroll</div>
            <div class="module-value" style="font-size: 1.2rem;"><?= htmlspecialchars($payroll_data['last_payroll'] ?? 'N/A') ?></div>
            <div class="module-label">Last payroll</div>
            <a href="payroll.php" class="module-link">
              View Details <i class="fas fa-arrow-right"></i>
            </a>
          </div>
        </div>

        <!-- Certificates -->
        <div class="col-xl-3 col-md-6">
          <div class="module-card">
            <div class="module-icon bg-success bg-opacity-10 text-success">
              <i class="fas fa-certificate"></i>
            </div>
            <div class="module-title">Certifications</div>
            <div class="module-value"><?= $cert_data['certificates'] ?? 0 ?></div>
            <div class="module-label">Certificates earned</div>
            <a href="learning.php" class="module-link">
              View Details <i class="fas fa-arrow-right"></i>
            </a>
          </div>
        </div>

        <!-- Overtime -->
        <div class="col-xl-3 col-md-6">
          <div class="module-card">
            <div class="module-icon bg-warning bg-opacity-10 text-warning">
              <i class="fas fa-hourglass-half"></i>
            </div>
            <div class="module-title">Overtime</div>
            <div class="module-value"><?= round($overtime_data['overtime_hours'] ?? 0, 1) ?></div>
            <div class="module-label">Hours this month</div>
            <a href="timesheet.php" class="module-link">
              View Details <i class="fas fa-arrow-right"></i>
            </a>
          </div>
        </div>

        <!-- Profile -->
        <div class="col-xl-3 col-md-6">
          <div class="module-card">
            <div class="module-icon bg-primary bg-opacity-10 text-primary">
              <i class="fas fa-user"></i>
            </div>
            <div class="module-title">Profile</div>
            <div class="module-value" style="font-size: 1.2rem;">Active</div>
            <div class="module-label">View and update profile</div>
            <a href="profile.php" class="module-link">
              View Details <i class="fas fa-arrow-right"></i>
            </a>
          </div>
        </div>
      </div>

      <!-- Recent Activity -->
      <?php if (!empty($formatted_activities)): ?>
      <div class="row mt-4">
        <div class="col-12">
          <div class="module-card">
            <h5 class="mb-3">
              <i class="fas fa-clock text-primary me-2"></i>
              Recent Activity
            </h5>
            <div class="activity-list">
              <?php foreach($formatted_activities as $activity): ?>
                <div class="d-flex align-items-center py-2 border-bottom">
                  <div class="module-icon bg-<?= $activity['color'] ?> bg-opacity-10 text-<?= $activity['color'] ?>" style="width: 40px; height: 40px; font-size: 1rem; margin-bottom: 0; margin-right: 1rem;">
                    <i class="<?= $activity['icon'] ?>"></i>
                  </div>
                  <div class="flex-grow-1">
                    <div class="fw-medium"><?= htmlspecialchars($activity['text']) ?></div>
                    <small class="text-muted"><?= $activity['time'] ?></small>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>
      <?php endif; ?>
    </div>
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

    // Scroll to dashboard section
    function scrollToSection(sectionId) {
      const element = document.getElementById(sectionId);
      if (element) {
        element.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    }
  </script>
</body>
</html>
