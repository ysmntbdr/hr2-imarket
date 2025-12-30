<?php
// Enhanced Timesheet Management System
require_once 'auth_check.php';

$pdo = getPDO();
$employee_id = getCurrentEmployeeId();

// Get current employee data
$employee = getCurrentEmployee();

// Get current week timesheet data
$stmt = $pdo->prepare("
    SELECT 
        MIN(timesheet_date) as week_start,
        MAX(timesheet_date) as week_end,
        SUM(total_hours) as total_hours,
        SUM(CASE WHEN total_hours <= 8 THEN total_hours ELSE 8 END) as regular_hours,
        SUM(CASE WHEN total_hours > 8 THEN total_hours - 8 ELSE 0 END) as overtime_hours,
        status
    FROM timesheets 
    WHERE employee_id = ? 
    AND YEARWEEK(timesheet_date, 1) = YEARWEEK(CURDATE(), 1)
    GROUP BY status
");
$stmt->execute([$employee_id]);
$week_data = $stmt->fetch();

$current_week = [
    'week_start' => $week_data['week_start'] ?? date('Y-m-d', strtotime('monday this week')),
    'week_end' => $week_data['week_end'] ?? date('Y-m-d', strtotime('sunday this week')),
    'total_hours' => $week_data['total_hours'] ?? 0,
    'regular_hours' => $week_data['regular_hours'] ?? 0,
    'overtime_hours' => $week_data['overtime_hours'] ?? 0,
    'status' => $week_data['status'] ?? 'In Progress'
];

// Get daily entries for current week
$stmt = $pdo->prepare("
    SELECT 
        timesheet_date as date,
        DAYNAME(timesheet_date) as day,
        TIME_FORMAT(time_in, '%H:%i') as time_in,
        TIME_FORMAT(time_out, '%H:%i') as time_out,
        break_duration,
        total_hours,
        project_name as project,
        notes
    FROM timesheets 
    WHERE employee_id = ? 
    AND YEARWEEK(timesheet_date, 1) = YEARWEEK(CURDATE(), 1)
    ORDER BY timesheet_date
");
$stmt->execute([$employee_id]);
$daily_entries = $stmt->fetchAll();

// Get project breakdown
$stmt = $pdo->prepare("
    SELECT 
        project_name as name,
        SUM(CASE WHEN YEARWEEK(timesheet_date, 1) = YEARWEEK(CURDATE(), 1) THEN total_hours ELSE 0 END) as hours_this_week,
        SUM(total_hours) as total_hours,
        'Active' as status
    FROM timesheets 
    WHERE employee_id = ? 
    AND project_name IS NOT NULL
    GROUP BY project_name
    ORDER BY hours_this_week DESC
");
$stmt->execute([$employee_id]);
$projects = $stmt->fetchAll();

// Get recent timesheets
$stmt = $pdo->prepare("
    SELECT 
        CONCAT(DATE_FORMAT(MIN(timesheet_date), '%b %e'), ' - ', DATE_FORMAT(MAX(timesheet_date), '%b %e, %Y')) as week,
        SUM(total_hours) as total_hours,
        SUM(CASE WHEN total_hours > 8 THEN total_hours - 8 ELSE 0 END) as overtime,
        status
    FROM timesheets 
    WHERE employee_id = ? 
    AND timesheet_date < DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)
    GROUP BY YEARWEEK(timesheet_date, 1), status
    ORDER BY MIN(timesheet_date) DESC
    LIMIT 4
");
$stmt->execute([$employee_id]);
$recent_timesheets = $stmt->fetchAll();

// Calculate time statistics
$stmt = $pdo->prepare("
    SELECT 
        AVG(total_hours) as avg_daily_hours,
        SUM(CASE WHEN total_hours > 8 AND MONTH(timesheet_date) = MONTH(CURDATE()) THEN total_hours - 8 ELSE 0 END) as total_overtime_month,
        COUNT(CASE WHEN total_hours > 0 THEN 1 END) * 100.0 / COUNT(*) as attendance_rate,
        COUNT(DISTINCT project_name) as projects_active
    FROM timesheets 
    WHERE employee_id = ? 
    AND timesheet_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
");
$stmt->execute([$employee_id]);
$stats = $stmt->fetch();

$time_stats = [
    'avg_daily_hours' => round($stats['avg_daily_hours'] ?? 0, 1),
    'total_overtime_month' => $stats['total_overtime_month'] ?? 0,
    'attendance_rate' => round($stats['attendance_rate'] ?? 0, 1),
    'projects_active' => $stats['projects_active'] ?? 0
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Timesheet Management</title>
  
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

    .timesheet-header {
      background: linear-gradient(135deg, var(--primary-color), #94dcf4);
      color: white;
      border-radius: 20px;
      padding: 2rem;
      margin-bottom: 2rem;
      box-shadow: 0 8px 25px rgba(75, 197, 236, 0.3);
    }

    .timesheet-card {
      background: white;
      border-radius: 15px;
      padding: 2rem;
      box-shadow: 0 4px 15px rgba(0,0,0,0.08);
      border: none;
      margin-bottom: 2rem;
      transition: transform 0.2s, box-shadow 0.2s;
    }

    .timesheet-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    }

    .time-entry-card {
      background: white;
      border-radius: 12px;
      padding: 1.5rem;
      box-shadow: 0 2px 10px rgba(0,0,0,0.08);
      border-left: 4px solid var(--primary-color);
      margin-bottom: 1rem;
      transition: all 0.3s;
    }

    .time-entry-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 15px rgba(0,0,0,0.15);
    }

    .time-display {
      font-size: 1.5rem;
      font-weight: 700;
      color: var(--primary-color);
      font-family: 'Courier New', monospace;
    }

    .animate-slide-up {
      animation: slideUp 0.6s ease-out;
    }

    @keyframes slideUp {
      from {
        opacity: 0;
        transform: translateY(30px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .project-progress {
      height: 8px;
      border-radius: 10px;
      background: #e9ecef;
      overflow: hidden;
    }

    .project-fill {
      height: 100%;
      background: linear-gradient(90deg, var(--primary-color), #94dcf4);
      border-radius: 10px;
      transition: width 0.5s ease;
    }

    .day-off {
      opacity: 0.6;
      background-color: #f8f9fa !important;
    }
  </style>
</head>
<body class="bg-light">
  <div class="container-fluid p-4">
    <!-- Timesheet Header -->
    <div class="timesheet-header animate-slide-up text-center">
      <h1 class="mb-2">
        <i class="fas fa-clock me-3"></i>
        Timesheet Management
      </h1>
      <p class="mb-3 opacity-75">Track your work hours and project time</p>
      <div class="row justify-content-center">
        <div class="col-md-8">
          <h3>Welcome, <?= htmlspecialchars($employee['full_name'] ?? '') ?></h3>
          <p class="mb-0"><?= htmlspecialchars($employee['position']) ?> • <?= htmlspecialchars($employee['department']) ?></p>
        </div>
      </div>
    </div>

    <!-- Current Week Summary -->
    <div class="timesheet-card animate-slide-up">
      <div class="row align-items-center">
        <div class="col-md-8">
          <h4 class="text-primary mb-3">
            <i class="fas fa-calendar-week me-2"></i>
            Current Week: <?= date('M j', strtotime($current_week['week_start'])) ?> - <?= date('M j, Y', strtotime($current_week['week_end'])) ?>
          </h4>
          
          <div class="row g-4">
            <div class="col-md-3">
              <div class="text-center p-3 bg-primary bg-opacity-10 rounded-3">
                <div class="time-display text-primary"><?= $current_week['total_hours'] ?>h</div>
                <small class="text-muted">Total Hours</small>
              </div>
            </div>
            <div class="col-md-3">
              <div class="text-center p-3 bg-success bg-opacity-10 rounded-3">
                <div class="time-display text-success"><?= $current_week['regular_hours'] ?>h</div>
                <small class="text-muted">Regular Hours</small>
              </div>
            </div>
            <div class="col-md-3">
              <div class="text-center p-3 bg-warning bg-opacity-10 rounded-3">
                <div class="time-display text-warning"><?= $current_week['overtime_hours'] ?>h</div>
                <small class="text-muted">Overtime</small>
              </div>
            </div>
            <div class="col-md-3">
              <div class="text-center p-3 bg-info bg-opacity-10 rounded-3">
                <span class="badge bg-<?= $current_week['status'] === 'Approved' ? 'success' : 'warning' ?> fs-6">
                  <?= $current_week['status'] ?>
                </span>
                <small class="text-muted d-block mt-1">Status</small>
              </div>
            </div>
          </div>
        </div>
        
        <div class="col-md-4 text-end">
          <button class="btn btn-primary btn-lg mb-2">
            <i class="fas fa-plus me-2"></i>Add Time Entry
          </button>
          <br>
          <button class="btn btn-outline-success">
            <i class="fas fa-check me-2"></i>Submit Timesheet
          </button>
        </div>
      </div>
    </div>

    <!-- Main Content -->
    <div class="row g-4">
      <!-- Daily Time Entries -->
      <div class="col-lg-8">
        <div class="timesheet-card animate-slide-up">
          <h4 class="text-primary mb-4">
            <i class="fas fa-list me-2"></i>
            Daily Time Entries
          </h4>
          
          <div class="table-responsive">
            <table class="table table-hover">
              <thead class="table-light">
                <tr>
                  <th>Date</th>
                  <th>Time In</th>
                  <th>Time Out</th>
                  <th>Break</th>
                  <th>Hours</th>
                  <th>Project</th>
                  <th>Notes</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($daily_entries as $entry): ?>
                  <tr class="<?= $entry['total_hours'] == 0 ? 'day-off' : '' ?>">
                    <td>
                      <div class="fw-semibold"><?= date('M j', strtotime($entry['date'])) ?></div>
                      <small class="text-muted"><?= $entry['day'] ?></small>
                    </td>
                    <td class="time-display"><?= $entry['time_in'] ?: '-' ?></td>
                    <td class="time-display"><?= $entry['time_out'] ?: '-' ?></td>
                    <td><?= $entry['break_duration'] ? $entry['break_duration'] . 'm' : '-' ?></td>
                    <td>
                      <span class="badge bg-<?= $entry['total_hours'] >= 8 ? 'success' : ($entry['total_hours'] > 0 ? 'warning' : 'secondary') ?>">
                        <?= $entry['total_hours'] ?>h
                      </span>
                    </td>
                    <td><?= htmlspecialchars($entry['project']) ?></td>
                    <td>
                      <span class="text-truncate d-inline-block" style="max-width: 150px;" title="<?= htmlspecialchars($entry['notes']) ?>">
                        <?= htmlspecialchars($entry['notes']) ?>
                      </span>
                    </td>
                    <td>
                      <?php if ($entry['total_hours'] > 0): ?>
                        <div class="btn-group btn-group-sm">
                          <button class="btn btn-outline-primary" title="Edit Entry">
                            <i class="fas fa-edit"></i>
                          </button>
                          <button class="btn btn-outline-danger" title="Delete Entry">
                            <i class="fas fa-trash"></i>
                          </button>
                        </div>
                      <?php else: ?>
                        <button class="btn btn-outline-success btn-sm" title="Add Entry">
                          <i class="fas fa-plus"></i>
                        </button>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Recent Timesheets -->
        <div class="timesheet-card animate-slide-up">
          <h4 class="text-primary mb-4">
            <i class="fas fa-history me-2"></i>
            Recent Timesheets
          </h4>
          
          <div class="table-responsive">
            <table class="table table-hover">
              <thead class="table-light">
                <tr>
                  <th>Week Period</th>
                  <th>Total Hours</th>
                  <th>Overtime</th>
                  <th>Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($recent_timesheets as $timesheet): ?>
                  <tr>
                    <td><?= htmlspecialchars($timesheet['week']) ?></td>
                    <td class="time-display"><?= $timesheet['total_hours'] ?>h</td>
                    <td>
                      <?php if ($timesheet['overtime'] > 0): ?>
                        <span class="badge bg-warning"><?= $timesheet['overtime'] ?>h</span>
                      <?php else: ?>
                        <span class="text-muted">-</span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <span class="badge bg-<?= $timesheet['status'] === 'Approved' ? 'success' : 'warning' ?>">
                        <?= $timesheet['status'] ?>
                      </span>
                    </td>
                    <td>
                      <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-primary" title="View Details">
                          <i class="fas fa-eye"></i>
                        </button>
                        <button class="btn btn-outline-success" title="Download">
                          <i class="fas fa-download"></i>
                        </button>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Sidebar -->
      <div class="col-lg-4">
        <!-- Quick Stats -->
        <div class="timesheet-card animate-slide-up">
          <h5 class="text-primary mb-3">
            <i class="fas fa-chart-line me-2"></i>
            Time Statistics
          </h5>
          
          <div class="mb-3">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <span>Average Daily Hours</span>
              <span class="time-display"><?= $time_stats['avg_daily_hours'] ?>h</span>
            </div>
          </div>
          
          <div class="mb-3">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <span>Overtime This Month</span>
              <span class="time-display text-warning"><?= $time_stats['total_overtime_month'] ?>h</span>
            </div>
          </div>
          
          <div class="mb-3">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <span>Attendance Rate</span>
              <span class="time-display text-success"><?= $time_stats['attendance_rate'] ?>%</span>
            </div>
          </div>
          
          <div class="mb-3">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <span>Active Projects</span>
              <span class="time-display text-info"><?= $time_stats['projects_active'] ?></span>
            </div>
          </div>
        </div>

        <!-- Project Time Breakdown -->
        <div class="timesheet-card animate-slide-up">
          <h5 class="text-primary mb-3">
            <i class="fas fa-project-diagram me-2"></i>
            Project Time Breakdown
          </h5>
          
          <?php foreach ($projects as $project): ?>
            <div class="time-entry-card">
              <div class="d-flex justify-content-between align-items-start mb-2">
                <h6 class="text-primary mb-1"><?= htmlspecialchars($project['name']) ?></h6>
                <span class="badge bg-<?= $project['status'] === 'Active' ? 'success' : 'secondary' ?>">
                  <?= $project['status'] ?>
                </span>
              </div>
              
              <div class="row g-2 text-center mb-2">
                <div class="col-6">
                  <div class="time-display text-primary"><?= $project['hours_this_week'] ?>h</div>
                  <small class="text-muted">This Week</small>
                </div>
                <div class="col-6">
                  <div class="time-display text-secondary"><?= $project['total_hours'] ?>h</div>
                  <small class="text-muted">Total</small>
                </div>
              </div>
              
              <div class="project-progress">
                <div class="project-fill" style="width: <?= ($project['hours_this_week'] / 20) * 100 ?>%"></div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <!-- Quick Actions -->
        <div class="timesheet-card animate-slide-up">
          <h5 class="text-primary mb-3">
            <i class="fas fa-bolt me-2"></i>
            Quick Actions
          </h5>
          
          <div class="d-grid gap-2">
            <button class="btn btn-outline-primary">
              <i class="fas fa-clock me-2"></i>Clock In/Out
            </button>
            <button class="btn btn-outline-success">
              <i class="fas fa-calendar-plus me-2"></i>Add Time Entry
            </button>
            <button class="btn btn-outline-info">
              <i class="fas fa-file-export me-2"></i>Export Timesheet
            </button>
            <button class="btn btn-outline-warning">
              <i class="fas fa-chart-bar me-2"></i>View Reports
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      // Add animation delays to cards
      const cards = document.querySelectorAll('.animate-slide-up');
      cards.forEach((card, index) => {
        card.style.animationDelay = `${index * 0.1}s`;
      });

      // Animate progress bars
      setTimeout(() => {
        document.querySelectorAll('.project-fill').forEach(bar => {
          const width = bar.style.width;
          bar.style.width = '0%';
          setTimeout(() => {
            bar.style.width = width;
          }, 100);
        });
      }, 500);

      // Add click handlers for buttons
      document.querySelectorAll('.btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
          if (this.textContent.includes('Add') || this.textContent.includes('Submit') || this.textContent.includes('Export')) {
            e.preventDefault();
            
            // Create toast notification
            const toast = document.createElement('div');
            toast.className = 'toast align-items-center text-white bg-primary border-0 position-fixed top-0 end-0 m-3';
            toast.style.zIndex = '9999';
            toast.innerHTML = `
              <div class="d-flex">
                <div class="toast-body">
                  <i class="fas fa-info-circle me-2"></i>
                  Feature will be available soon!
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
              </div>
            `;
            
            document.body.appendChild(toast);
            const bsToast = new bootstrap.Toast(toast);
            bsToast.show();
            
            // Remove toast after it's hidden
            toast.addEventListener('hidden.bs.toast', () => {
              toast.remove();
            });
          }
        });
      });
    });
  </script>

</body>
</html>
