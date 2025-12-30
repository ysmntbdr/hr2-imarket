<?php
// Enhanced dashboard with comprehensive metrics
require_once 'auth_check.php';

try {
    $pdo = getPDO();
    $employee_id = getCurrentEmployeeId();

    // Get current employee data
    $employee = getCurrentEmployee();
    
    if (!$employee) {
        throw new Exception("Employee not found");
    }
} catch (Exception $e) {
    // Handle database connection or employee lookup errors
    $error_message = "Dashboard temporarily unavailable. Please try again later.";
    $employee = [
        'full_name' => 'Guest User',
        'employee_id' => 'N/A',
        'position' => 'N/A',
        'department' => 'N/A'
    ];
}

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

// Only fetch data if we have a valid database connection and employee
if (isset($pdo) && isset($employee_id)) {
    try {
        // Calculate competency score
        $stmt = $pdo->prepare("
            SELECT AVG(proficiency_level) * 20 as competency_score
            FROM employee_competencies 
            WHERE employee_id = ?
        ");
        $stmt->execute([$employee_id]);
        $competency_data = $stmt->fetch() ?: $competency_data;

        // Get pending requests count
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as pending_requests
            FROM claims 
            WHERE employee_id = ? AND status = 'pending'
        ");
        $stmt->execute([$employee_id]);
        $pending_data = $stmt->fetch() ?: $pending_data;

        // Get learning progress
        $stmt = $pdo->prepare("
            SELECT 
                AVG(progress) as learning_progress,
                COUNT(CASE WHEN completion_date IS NULL THEN 1 END) as courses_in_progress
            FROM employee_courses 
            WHERE employee_id = ?
        ");
        $stmt->execute([$employee_id]);
        $learning_data = $stmt->fetch() ?: $learning_data;

        // Get leave balance
        $stmt = $pdo->prepare("
            SELECT SUM(total_days - used_days) as leave_balance
            FROM leave_balances 
            WHERE employee_id = ?
        ");
        $stmt->execute([$employee_id]);
        $leave_data = $stmt->fetch() ?: $leave_data;

        // Get overtime hours this month
        $stmt = $pdo->prepare("
            SELECT SUM(overtime_hours) as overtime_hours
            FROM attendance 
            WHERE employee_id = ? 
            AND MONTH(attendance_date) = MONTH(CURDATE())
            AND YEAR(attendance_date) = YEAR(CURDATE())
        ");
        $stmt->execute([$employee_id]);
        $overtime_data = $stmt->fetch() ?: $overtime_data;

        // Get attendance rate
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(CASE WHEN status = 'present' THEN 1 END) * 100.0 / COUNT(*) as attendance_rate
            FROM attendance 
            WHERE employee_id = ? 
            AND attendance_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        ");
        $stmt->execute([$employee_id]);
        $attendance_data = $stmt->fetch() ?: $attendance_data;

        // Get certificates earned
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as certificates
            FROM employee_courses 
            WHERE employee_id = ? AND certificate_earned = 1
        ");
        $stmt->execute([$employee_id]);
        $cert_data = $stmt->fetch() ?: $cert_data;

        // Get succession plan info
        $stmt = $pdo->prepare("
            SELECT target_position 
            FROM succession_plans 
            WHERE employee_id = ? 
            LIMIT 1
        ");
        $stmt->execute([$employee_id]);
        $succession_data = $stmt->fetch() ?: $succession_data;

        // Get recent activity from database
        $stmt = $pdo->prepare("
            (SELECT 'course_completed' as type, CONCAT('Completed \"', c.title, '\" course') as text, 
                    ec.completion_date as activity_date, 'green' as color, 'fa-check-circle' as icon
             FROM employee_courses ec 
             JOIN courses c ON ec.course_id = c.id 
             WHERE ec.employee_id = ? AND ec.completion_date IS NOT NULL
             ORDER BY ec.completion_date DESC LIMIT 2)
            UNION ALL
            (SELECT 'claim_submitted' as type, CONCAT('Submitted claim #', claim_number) as text,
                    claim_date as activity_date, 'blue' as color, 'fa-receipt' as icon
             FROM claims 
             WHERE employee_id = ?
             ORDER BY claim_date DESC LIMIT 2)
            ORDER BY activity_date DESC
            LIMIT 6
        ");
        $stmt->execute([$employee_id, $employee_id]);
        $recent_activities = $stmt->fetchAll();

        // Get additional dashboard metrics from database
        
        // Get pending attention count (high priority claims)
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as pending_attention
            FROM claims 
            WHERE employee_id = ? AND status = 'pending' 
            AND amount > 1000
        ");
        $stmt->execute([$employee_id]);
        $attention_data = $stmt->fetch();

        // Get performance rating from latest review (if exists)
        $stmt = $pdo->prepare("
            SELECT rating 
            FROM performance_reviews 
            WHERE employee_id = ? 
            ORDER BY review_date DESC 
            LIMIT 1
        ");
        $stmt->execute([$employee_id]);
        $performance_data = $stmt->fetch();

        // Get goals/projects count
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as goals_completed,
                COUNT(*) as goals_total
            FROM employee_goals 
            WHERE employee_id = ? 
            AND YEAR(created_at) = YEAR(CURDATE())
        ");
        $stmt->execute([$employee_id]);
        $goals_data = $stmt->fetch();

        // Get active projects count
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT project_name) as team_projects
            FROM timesheets 
            WHERE employee_id = ? 
            AND project_name IS NOT NULL 
            AND timesheet_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        ");
        $stmt->execute([$employee_id]);
        $projects_data = $stmt->fetch();

        // Get notifications from database
        $stmt = $pdo->prepare("
            (SELECT 'info' as type, 
                    CONCAT('New training session available: \"', title, '\"') as message,
                    '1 hour ago' as time
             FROM trainings 
             WHERE start_date > CURDATE() 
             AND id NOT IN (SELECT training_id FROM employee_trainings WHERE employee_id = ?)
             ORDER BY start_date ASC LIMIT 1)
            UNION ALL
            (SELECT 'success' as type,
                    CONCAT('Expense claim #', claim_number, ' approved') as message,
                    CONCAT(DATEDIFF(CURDATE(), claim_date), ' days ago') as time
             FROM claims 
             WHERE employee_id = ? AND status = 'approved'
             ORDER BY claim_date DESC LIMIT 1)
            UNION ALL
            (SELECT 'warning' as type,
                    'Performance review due soon' as message,
                    '2 days ago' as time
             FROM dual LIMIT 1)
            LIMIT 3
        ");
        $stmt->execute([$employee_id, $employee_id]);
        $notifications_data = $stmt->fetchAll();
        
    } catch (Exception $e) {
        // If any database query fails, use default values
        error_log("Dashboard query error: " . $e->getMessage());
        $attention_data = ['pending_attention' => 0];
        $performance_data = ['rating' => 4.2];
        $goals_data = ['goals_completed' => 0, 'goals_total' => 0];
        $projects_data = ['team_projects' => 0];
        $notifications_data = [];
    }
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

// Build user data array with database values
$user = [
    'name' => $employee['full_name'] ?? 'Unknown User',
    'employee_id' => $employee['employee_id'] ?? 'N/A',
    'role' => $employee['position'] ?? 'N/A',
    'department' => $employee['department'] ?? 'N/A',
    'competency_score' => round($competency_data['competency_score'] ?? 0),
    'competency_change' => '+5% from last month', // Could be calculated from historical data
    'pending_requests' => $pending_data['pending_requests'] ?? 0,
    'pending_attention' => $attention_data['pending_attention'] ?? 0,
    'learning_progress' => round($learning_data['learning_progress'] ?? 0),
    'courses_in_progress' => $learning_data['courses_in_progress'] ?? 0,
    'career_level' => 3, // Could be calculated from position hierarchy
    'career_next' => $succession_data['target_position'] ?? 'Not Set',
    'upcoming_task' => 'Complete Competency Assessment', // Could come from tasks table
    'task_due' => 'Tomorrow',
    'leave_balance' => $leave_data['leave_balance'] ?? 0,
    'overtime_hours' => $overtime_data['overtime_hours'] ?? 0,
    'performance_rating' => $performance_data['rating'] ?? 4.2,
    'recent_activity' => $formatted_activities,
    'quick_stats' => [
        'attendance_rate' => round($attendance_data['attendance_rate'] ?? 0, 1),
        'goals_completed' => $goals_data['goals_completed'] ?? 0,
        'goals_total' => $goals_data['goals_total'] ?? 0,
        'certifications' => $cert_data['certificates'] ?? 0,
        'team_projects' => $projects_data['team_projects'] ?? 0
    ],
    'notifications' => $notifications_data ?: [
        ['type' => 'info', 'message' => 'Welcome to your HR dashboard!', 'time' => 'now']
    ]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dashboard Home</title>
  
  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  
  <style>
    :root {
      --primary-color: #4bc5ec;
      --primary-dark: #3ba3cc;
    }

    body {
      background-color: #f8f9fa;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    .dashboard-header {
      background: linear-gradient(135deg, var(--primary-color), #94dcf4);
      color: white;
      border-radius: 15px;
      padding: 2rem;
      margin-bottom: 2rem;
      box-shadow: 0 4px 15px rgba(75, 197, 236, 0.3);
    }

    .stat-card {
      background: white;
      border-radius: 15px;
      padding: 1.5rem;
      box-shadow: 0 4px 15px rgba(0,0,0,0.08);
      border: none;
      transition: transform 0.2s, box-shadow 0.2s;
      height: 100%;
    }

    .stat-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    }

    .stat-icon {
      width: 50px;
      height: 50px;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.5rem;
      margin-bottom: 1rem;
    }

    .stat-value {
      font-size: 2rem;
      font-weight: 700;
      color: #2c3e50;
      margin-bottom: 0.5rem;
    }

    .stat-label {
      color: #6c757d;
      font-size: 0.9rem;
      font-weight: 500;
    }

    .stat-change {
      font-size: 0.8rem;
      margin-top: 0.5rem;
    }

    .activity-card {
      background: white;
      border-radius: 15px;
      padding: 1.5rem;
      box-shadow: 0 4px 15px rgba(0,0,0,0.08);
      border: none;
    }

    .activity-item {
      padding: 0.75rem 0;
      border-bottom: 1px solid #f1f3f4;
      display: flex;
      align-items: center;
      gap: 1rem;
    }

    .activity-item:last-child {
      border-bottom: none;
    }

    .activity-icon {
      width: 40px;
      height: 40px;
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1rem;
    }

    .progress-ring {
      width: 60px;
      height: 60px;
    }

    .progress-ring-circle {
      stroke: var(--primary-color);
      stroke-width: 4;
      fill: transparent;
      stroke-dasharray: 188.5;
      stroke-dashoffset: 188.5;
      transform: rotate(-90deg);
      transform-origin: 50% 50%;
      animation: progress 1s ease-out forwards;
    }

    @keyframes progress {
      to {
        stroke-dashoffset: calc(188.5 - (188.5 * var(--progress)) / 100);
      }
    }

    .notification-badge {
      position: relative;
    }

    .notification-badge::after {
      content: '';
      position: absolute;
      top: -2px;
      right: -2px;
      width: 8px;
      height: 8px;
      background: #dc3545;
      border-radius: 50%;
      border: 2px solid white;
    }

    .metric-trend {
      display: inline-flex;
      align-items: center;
      gap: 0.25rem;
      padding: 0.25rem 0.5rem;
      border-radius: 20px;
      font-size: 0.75rem;
      font-weight: 600;
    }

    .trend-up {
      background: #d4edda;
      color: #155724;
    }

    .trend-down {
      background: #f8d7da;
      color: #721c24;
    }

    .welcome-animation {
      animation: slideInDown 0.6s ease-out;
    }

    @keyframes slideInDown {
      from {
        opacity: 0;
        transform: translateY(-30px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .card-animation {
      animation: slideInUp 0.6s ease-out;
      animation-fill-mode: both;
    }

    @keyframes slideInUp {
      from {
        opacity: 0;
        transform: translateY(30px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }
  </style>
</head>
<body class="bg-light">
  <div class="container-fluid p-4">
    <!-- Welcome Header -->
    <div class="dashboard-header welcome-animation">
      <div class="row align-items-center">
        <div class="col-md-8">
          <div class="d-flex align-items-center mb-3">
            <i class="fas fa-hand-sparkles me-3" style="font-size: 2rem;"></i>
            <div>
              <h1 class="mb-1">Welcome back, <?= htmlspecialchars($user['name']) ?>!</h1>
              <p class="mb-0 opacity-75"><?= htmlspecialchars($user['role']) ?> • <?= htmlspecialchars($user['department']) ?></p>
            </div>
          </div>
          <p class="mb-0">Here's what's happening with your work today.</p>
        </div>
        <div class="col-md-4 text-end">
          <div class="d-flex align-items-center justify-content-end">
            <div class="me-3">
              <small class="d-block opacity-75">Employee ID</small>
              <strong><?= htmlspecialchars($user['employee_id']) ?></strong>
            </div>
            <div class="bg-white bg-opacity-25 rounded-circle p-3">
              <i class="fas fa-user" style="font-size: 1.5rem;"></i>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Main Statistics Cards -->
    <div class="row g-4 mb-4">
      <div class="col-xl-3 col-md-6">
        <div class="stat-card card-animation" style="animation-delay: 0.1s;">
          <div class="d-flex align-items-center justify-content-between">
            <div>
              <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                <i class="fas fa-chart-pie"></i>
              </div>
              <div class="stat-value"><?= $user['competency_score'] ?>%</div>
              <div class="stat-label">Competency Score</div>
              <div class="stat-change">
                <span class="metric-trend trend-up">
                  <i class="fas fa-arrow-up"></i>
                  <?= $user['competency_change'] ?>
                </span>
              </div>
            </div>
            <div class="progress-ring">
              <svg class="progress-ring" width="60" height="60">
                <circle class="progress-ring-circle" cx="30" cy="30" r="30" style="--progress: <?= $user['competency_score'] ?>"></circle>
              </svg>
            </div>
          </div>
        </div>
      </div>

      <div class="col-xl-3 col-md-6">
        <a href="claims.php" class="text-decoration-none">
          <div class="stat-card card-animation" style="animation-delay: 0.2s; cursor: pointer;">
            <div class="stat-icon bg-warning bg-opacity-10 text-warning notification-badge">
              <i class="fas fa-tasks"></i>
            </div>
            <div class="stat-value"><?= $user['pending_requests'] ?></div>
            <div class="stat-label">Pending Requests</div>
            <div class="stat-change">
              <small class="text-muted"><?= $user['pending_attention'] ?> require immediate attention</small>
            </div>
          </div>
        </a>
      </div>

      <div class="col-xl-3 col-md-6">
        <a href="learning.php" class="text-decoration-none">
          <div class="stat-card card-animation" style="animation-delay: 0.3s; cursor: pointer;">
            <div class="d-flex align-items-center justify-content-between">
              <div>
                <div class="stat-icon bg-success bg-opacity-10 text-success">
                  <i class="fas fa-graduation-cap"></i>
                </div>
                <div class="stat-value"><?= $user['learning_progress'] ?>%</div>
                <div class="stat-label">Learning Progress</div>
                <div class="stat-change">
                  <small class="text-muted"><?= $user['courses_in_progress'] ?> courses in progress</small>
                </div>
              </div>
              <div class="progress-ring">
                <svg class="progress-ring" width="60" height="60">
                  <circle class="progress-ring-circle" cx="30" cy="30" r="30" style="--progress: <?= $user['learning_progress'] ?>"></circle>
                </svg>
              </div>
            </div>
          </div>
        </a>
      </div>

      <div class="col-xl-3 col-md-6">
        <div class="stat-card card-animation" style="animation-delay: 0.4s;">
          <div class="stat-icon bg-info bg-opacity-10 text-info">
            <i class="fas fa-star"></i>
          </div>
          <div class="stat-value"><?= $user['performance_rating'] ?>/5.0</div>
          <div class="stat-label">Performance Rating</div>
          <div class="stat-change">
            <span class="metric-trend trend-up">
              <i class="fas fa-arrow-up"></i>
              Excellent
            </span>
          </div>
        </div>
      </div>
    </div>

    <!-- Secondary Statistics -->
    <div class="row g-4 mb-4">
      <div class="col-xl-3 col-md-6">
        <div class="stat-card card-animation" style="animation-delay: 0.5s;">
          <div class="stat-icon bg-secondary bg-opacity-10 text-secondary">
            <i class="fas fa-calendar-check"></i>
          </div>
          <div class="stat-value"><?= $user['leave_balance'] ?></div>
          <div class="stat-label">Leave Days Remaining</div>
        </div>
      </div>

      <div class="col-xl-3 col-md-6">
        <div class="stat-card card-animation" style="animation-delay: 0.6s;">
          <div class="stat-icon bg-primary bg-opacity-10 text-primary">
            <i class="fas fa-user-check"></i>
          </div>
          <div class="stat-value"><?= $user['quick_stats']['attendance_rate'] ?>%</div>
          <div class="stat-label">Attendance Rate</div>
        </div>
      </div>

      <div class="col-xl-3 col-md-6">
        <div class="stat-card card-animation" style="animation-delay: 0.7s;">
          <div class="stat-icon bg-success bg-opacity-10 text-success">
            <i class="fas fa-certificate"></i>
          </div>
          <div class="stat-value"><?= $user['quick_stats']['certifications'] ?></div>
          <div class="stat-label">Certifications Earned</div>
        </div>
      </div>

      <div class="col-xl-3 col-md-6">
        <div class="stat-card card-animation" style="animation-delay: 0.8s;">
          <div class="stat-icon bg-warning bg-opacity-10 text-warning">
            <i class="fas fa-users"></i>
          </div>
          <div class="stat-value"><?= $user['quick_stats']['team_projects'] ?></div>
          <div class="stat-label">Active Projects</div>
        </div>
      </div>
    </div>

    <!-- Content Sections -->
    <div class="row g-4">
      <!-- Upcoming Tasks -->
      <div class="col-lg-6">
        <div class="activity-card card-animation" style="animation-delay: 0.9s;">
          <div class="d-flex align-items-center justify-content-between mb-3">
            <h5 class="mb-0">
              <i class="fas fa-calendar-day text-primary me-2"></i>
              Upcoming Tasks
            </h5>
            <span class="badge bg-danger"><?= $user['task_due'] ?></span>
          </div>
          <div class="alert alert-warning border-0 bg-warning bg-opacity-10">
            <div class="d-flex align-items-center">
              <i class="fas fa-exclamation-triangle text-warning me-3"></i>
              <div>
                <strong><?= htmlspecialchars($user['upcoming_task']) ?></strong>
                <br><small class="text-muted">Due: <?= $user['task_due'] ?></small>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Quick Actions -->
      <div class="col-lg-6">
        <div class="activity-card card-animation" style="animation-delay: 1.0s;">
          <h5 class="mb-3">
            <i class="fas fa-bolt text-primary me-2"></i>
            Quick Actions
          </h5>
          <div class="row g-2">
            <div class="col-6">
              <a href="leave.php" class="btn btn-outline-primary w-100 btn-sm">
                <i class="fas fa-plane-departure me-1"></i>
                Apply Leave
              </a>
            </div>
            <div class="col-6">
              <a href="claims.php" class="btn btn-outline-success w-100 btn-sm">
                <i class="fas fa-money-bill-wave me-1"></i>
                Submit Claim
              </a>
            </div>
            <div class="col-6">
              <a href="timesheet.php" class="btn btn-outline-info w-100 btn-sm">
                <i class="fas fa-clock me-1"></i>
                View Timesheet
              </a>
            </div>
            <div class="col-6">
              <a href="learning.php" class="btn btn-outline-warning w-100 btn-sm">
                <i class="fas fa-graduation-cap me-1"></i>
                Browse Courses
              </a>
            </div>
            <div class="col-6">
              <a href="payroll.php" class="btn btn-outline-secondary w-100 btn-sm">
                <i class="fas fa-file-invoice-dollar me-1"></i>
                View Payroll
              </a>
            </div>
            <div class="col-6">
              <a href="profile.php" class="btn btn-outline-dark w-100 btn-sm">
                <i class="fas fa-user-edit me-1"></i>
                Edit Profile
              </a>
            </div>
            <div class="col-6">
              <a href="competency.php" class="btn btn-outline-purple w-100 btn-sm" style="border-color: #6f42c1; color: #6f42c1;">
                <i class="fas fa-chart-line me-1"></i>
                Competencies
              </a>
            </div>
            <div class="col-6">
              <a href="training.php" class="btn btn-outline-teal w-100 btn-sm" style="border-color: #20c997; color: #20c997;">
                <i class="fas fa-chalkboard-teacher me-1"></i>
                Training
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Activity and Notifications -->
    <div class="row g-4 mt-2">
      <!-- Recent Activity -->
      <div class="col-lg-8">
        <div class="activity-card card-animation" style="animation-delay: 1.1s;">
          <h5 class="mb-3">
            <i class="fas fa-clock text-primary me-2"></i>
            Recent Activity
          </h5>
          <div class="activity-list">
            <?php foreach($user['recent_activity'] as $index => $activity): ?>
              <div class="activity-item">
                <div class="activity-icon bg-<?= $activity['color'] ?> bg-opacity-10 text-<?= $activity['color'] ?>">
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

      <!-- Notifications -->
      <div class="col-lg-4">
        <div class="activity-card card-animation" style="animation-delay: 1.2s;">
          <h5 class="mb-3">
            <i class="fas fa-bell text-primary me-2"></i>
            Notifications
            <span class="badge bg-danger ms-2"><?= count($user['notifications']) ?></span>
          </h5>
          <div class="notification-list">
            <?php foreach($user['notifications'] as $notification): ?>
              <div class="activity-item">
                <div class="activity-icon bg-<?= $notification['type'] === 'info' ? 'primary' : ($notification['type'] === 'warning' ? 'warning' : 'success') ?> bg-opacity-10 text-<?= $notification['type'] === 'info' ? 'primary' : ($notification['type'] === 'warning' ? 'warning' : 'success') ?>">
                  <i class="fas fa-<?= $notification['type'] === 'info' ? 'info-circle' : ($notification['type'] === 'warning' ? 'exclamation-triangle' : 'check-circle') ?>"></i>
                </div>
                <div class="flex-grow-1">
                  <div class="fw-medium small"><?= htmlspecialchars($notification['message']) ?></div>
                  <small class="text-muted"><?= $notification['time'] ?></small>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  
  <script>
    // Animate progress rings on page load
    document.addEventListener('DOMContentLoaded', function() {
      const progressRings = document.querySelectorAll('.progress-ring-circle');
      
      progressRings.forEach(ring => {
        const progress = ring.style.getPropertyValue('--progress');
        ring.style.setProperty('--progress', progress);
      });

      // Quick actions now use direct links - no JavaScript needed
      // Add hover effects for better UX
      document.querySelectorAll('[class*="btn-outline-"]').forEach(btn => {
        btn.addEventListener('mouseenter', function() {
          this.style.transform = 'translateY(-2px)';
          this.style.transition = 'transform 0.2s ease';
          this.style.boxShadow = '0 4px 12px rgba(0,0,0,0.15)';
        });
        
        btn.addEventListener('mouseleave', function() {
          this.style.transform = 'translateY(0)';
          this.style.boxShadow = 'none';
        });
      });
    });
  </script>

</body>
</html>
