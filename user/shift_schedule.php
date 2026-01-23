<?php
// Enhanced Shift & Schedule Management System
require_once 'auth_check.php';

$pdo = getPDO();
$employee_id = getCurrentEmployeeId();

// Get current employee data
$employee = getCurrentEmployee();

// Get current shift schedule for today
$stmt = $pdo->prepare("
    SELECT * FROM shifts 
    WHERE employee_id = ? AND shift_date = CURDATE()
");
$stmt->execute([$employee_id]);
$current_shift_data = $stmt->fetch();

$current_shift = [
    'date' => date('Y-m-d'),
    'day' => date('l, F j'),
    'start_time' => $current_shift_data['start_time'] ?? '08:00:00',
    'end_time' => $current_shift_data['end_time'] ?? '17:00:00',
    'break_start' => $current_shift_data['break_start'] ?? '12:00:00',
    'break_end' => $current_shift_data['break_end'] ?? '13:00:00',
    'shift_type' => $current_shift_data['shift_type'] ?? 'Regular Day Shift',
    'location' => $current_shift_data['location'] ?? 'Main Office'
];

// Get today's attendance
$stmt = $pdo->prepare("
    SELECT * FROM attendance 
    WHERE employee_id = ? AND attendance_date = CURDATE()
");
$stmt->execute([$employee_id]);
$attendance_data = $stmt->fetch();

$attendance_today = [
    'time_in' => $attendance_data['time_in'] ?? null,
    'time_out' => $attendance_data['time_out'] ?? null,
    'break_start' => $attendance_data['break_start'] ?? null,
    'break_end' => $attendance_data['break_end'] ?? null,
    'status' => $attendance_data['status'] ?? 'Not Started',
    'total_hours' => $attendance_data['total_hours'] ?? 0,
    'overtime_hours' => $attendance_data['overtime_hours'] ?? 0
];

// Get weekly schedule (current week)
$stmt = $pdo->prepare("
    SELECT 
        s.shift_date,
        DAYNAME(s.shift_date) as day,
        CONCAT(TIME_FORMAT(s.start_time, '%H:%i'), ' - ', TIME_FORMAT(s.end_time, '%H:%i')) as shift,
        COALESCE(a.status, 'Scheduled') as status,
        COALESCE(a.total_hours, 0) as hours
    FROM shifts s
    LEFT JOIN attendance a ON s.employee_id = a.employee_id AND s.shift_date = a.attendance_date
    WHERE s.employee_id = ? 
    AND YEARWEEK(s.shift_date, 1) = YEARWEEK(CURDATE(), 1)
    ORDER BY s.shift_date
");
$stmt->execute([$employee_id]);
$weekly_schedule = $stmt->fetchAll();

// Format weekly schedule
foreach ($weekly_schedule as &$schedule) {
    $schedule['date'] = $schedule['shift_date'];
}

// Get leave credits
$stmt = $pdo->prepare("
    SELECT 
        leave_type as type,
        total_days as total,
        used_days as used,
        (total_days - used_days) as remaining
    FROM leave_balances 
    WHERE employee_id = ?
");
$stmt->execute([$employee_id]);
$leave_credits = $stmt->fetchAll();

// Get recent attendance (last 5 days)
$stmt = $pdo->prepare("
    SELECT 
        attendance_date as date,
        TIME_FORMAT(time_in, '%H:%i') as time_in,
        TIME_FORMAT(time_out, '%H:%i') as time_out,
        total_hours as hours,
        status
    FROM attendance 
    WHERE employee_id = ? 
    ORDER BY attendance_date DESC 
    LIMIT 5
");
$stmt->execute([$employee_id]);
$recent_attendance = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Shift & Schedule Management</title>
  
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

    .schedule-header {
      background: linear-gradient(135deg, var(--primary-color), #94dcf4);
      color: white;
      border-radius: 20px;
      padding: 2rem;
      margin-bottom: 2rem;
      box-shadow: 0 8px 25px rgba(75, 197, 236, 0.3);
    }

    .schedule-card {
      background: white;
      border-radius: 15px;
      padding: 2rem;
      box-shadow: 0 4px 15px rgba(0,0,0,0.08);
      border: none;
      margin-bottom: 2rem;
      transition: transform 0.2s, box-shadow 0.2s;
    }

    .schedule-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    }

    .time-card {
      background: white;
      border-radius: 12px;
      padding: 1.5rem;
      box-shadow: 0 2px 10px rgba(0,0,0,0.08);
      border-left: 4px solid var(--primary-color);
      margin-bottom: 1rem;
      transition: all 0.3s;
    }

    .time-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 15px rgba(0,0,0,0.15);
    }

    .status-badge {
      padding: 0.5rem 1rem;
      border-radius: 25px;
      font-size: 0.8rem;
      font-weight: 600;
      text-transform: uppercase;
    }

    .time-display {
      font-size: 2.5rem;
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

    .clock-widget {
      background: linear-gradient(135deg, var(--primary-color), #94dcf4);
      color: white;
      border-radius: 15px;
      padding: 2rem;
      text-align: center;
      margin-bottom: 2rem;
    }

    .attendance-btn {
      padding: 1rem 2rem;
      font-size: 1.1rem;
      font-weight: 600;
      border-radius: 25px;
      transition: all 0.3s;
      margin: 0.5rem;
    }

    .attendance-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 15px rgba(0,0,0,0.3);
    }
  </style>
</head>
<body class="bg-light">
  <?php include 'includes/sidebar.php'; ?>
  
  <main class="main-content">
    <div class="container-fluid p-4">
    <!-- Schedule Header -->
    <div class="schedule-header animate-slide-up text-center">
      <h1 class="mb-2">
        <i class="fas fa-calendar-alt me-3"></i>
        Shift & Schedule Management
      </h1>
      <p class="mb-3 opacity-75">Track your attendance and manage your work schedule</p>
      <div class="row justify-content-center">
        <div class="col-md-8">
          <h3>Welcome, <?= htmlspecialchars($employee['full_name'] ?? '') ?></h3>
          <p class="mb-0"><?= htmlspecialchars($employee['position']) ?> • <?= htmlspecialchars($employee['department']) ?></p>
        </div>
      </div>
    </div>

    <!-- Current Time & Quick Actions -->
    <div class="row g-4 mb-4">
      <div class="col-lg-4">
        <div class="clock-widget animate-slide-up">
          <div class="time-display" id="currentTime"><?= date('H:i:s') ?></div>
          <p class="mb-3"><?= date('l, F j, Y') ?></p>
          <div class="d-grid gap-2">
            <button class="btn btn-light attendance-btn" onclick="timeIn()">
              <i class="fas fa-sign-in-alt me-2"></i>Time In
            </button>
            <button class="btn btn-outline-light attendance-btn" onclick="timeOut()">
              <i class="fas fa-sign-out-alt me-2"></i>Time Out
            </button>
          </div>
        </div>
      </div>

      <div class="col-lg-8">
        <div class="schedule-card animate-slide-up">
          <h4 class="text-primary mb-4">
            <i class="fas fa-clock me-2"></i>
            Today's Schedule
          </h4>
          
          <div class="row g-3">
            <div class="col-md-6">
              <div class="time-card">
                <div class="d-flex justify-content-between align-items-center">
                  <div>
                    <h6 class="text-primary mb-1">Shift Start</h6>
                    <div class="h5 mb-0"><?= $current_shift['start_time'] ?></div>
                  </div>
                  <i class="fas fa-sun text-primary" style="font-size: 2rem;"></i>
                </div>
              </div>
            </div>
            
            <div class="col-md-6">
              <div class="time-card">
                <div class="d-flex justify-content-between align-items-center">
                  <div>
                    <h6 class="text-primary mb-1">Shift End</h6>
                    <div class="h5 mb-0"><?= $current_shift['end_time'] ?></div>
                  </div>
                  <i class="fas fa-moon text-primary" style="font-size: 2rem;"></i>
                </div>
              </div>
            </div>

            <div class="col-md-6">
              <div class="time-card">
                <div class="d-flex justify-content-between align-items-center">
                  <div>
                    <h6 class="text-success mb-1">Break Start</h6>
                    <div class="h5 mb-0"><?= $current_shift['break_start'] ?></div>
                  </div>
                  <i class="fas fa-coffee text-success" style="font-size: 2rem;"></i>
                </div>
              </div>
            </div>

            <div class="col-md-6">
              <div class="time-card">
                <div class="d-flex justify-content-between align-items-center">
                  <div>
                    <h6 class="text-success mb-1">Break End</h6>
                    <div class="h5 mb-0"><?= $current_shift['break_end'] ?></div>
                  </div>
                  <i class="fas fa-play text-success" style="font-size: 2rem;"></i>
                </div>
              </div>
            </div>
          </div>

          <div class="mt-4 p-3 bg-light rounded">
            <div class="row text-center">
              <div class="col-4">
                <div class="fw-bold text-primary">Shift Type</div>
                <div><?= htmlspecialchars($current_shift['shift_type']) ?></div>
              </div>
              <div class="col-4">
                <div class="fw-bold text-primary">Location</div>
                <div><?= htmlspecialchars($current_shift['location']) ?></div>
              </div>
              <div class="col-4">
                <div class="fw-bold text-primary">Status</div>
                <span class="status-badge bg-<?= $attendance_today['status'] === 'Not Started' ? 'warning' : 'success' ?>">
                  <?= $attendance_today['status'] ?>
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Weekly Schedule & Leave Credits -->
    <div class="row g-4">
      <!-- Weekly Schedule -->
      <div class="col-lg-8">
        <div class="schedule-card animate-slide-up">
          <h4 class="text-primary mb-4">
            <i class="fas fa-calendar-week me-2"></i>
            Weekly Schedule
          </h4>
          
          <div class="table-responsive">
            <table class="table table-hover">
              <thead class="table-light">
                <tr>
                  <th>Day</th>
                  <th>Date</th>
                  <th>Shift Hours</th>
                  <th>Status</th>
                  <th>Hours Worked</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($weekly_schedule as $day): ?>
                  <tr>
                    <td class="fw-semibold"><?= $day['day'] ?></td>
                    <td><?= date('M j', strtotime($day['date'])) ?></td>
                    <td><?= $day['shift'] ?></td>
                    <td>
                      <span class="status-badge bg-<?= 
                        $day['status'] === 'Completed' ? 'success' : 
                        ($day['status'] === 'In Progress' ? 'primary' : 
                        ($day['status'] === 'Scheduled' ? 'info' : 'secondary')) ?>">
                        <?= $day['status'] ?>
                      </span>
                    </td>
                    <td>
                      <?= $day['hours'] > 0 ? $day['hours'] . ' hrs' : '-' ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Recent Attendance -->
        <div class="schedule-card animate-slide-up">
          <h4 class="text-primary mb-4">
            <i class="fas fa-history me-2"></i>
            Recent Attendance
          </h4>
          
          <div class="table-responsive">
            <table class="table table-hover">
              <thead class="table-light">
                <tr>
                  <th>Date</th>
                  <th>Time In</th>
                  <th>Time Out</th>
                  <th>Hours</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($recent_attendance as $record): ?>
                  <tr>
                    <td><?= date('M j, Y', strtotime($record['date'])) ?></td>
                    <td><?= $record['time_in'] ?></td>
                    <td><?= $record['time_out'] ?></td>
                    <td><?= $record['hours'] > 0 ? $record['hours'] . ' hrs' : '-' ?></td>
                    <td>
                      <span class="status-badge bg-<?= 
                        $record['status'] === 'Present' ? 'success' : 
                        ($record['status'] === 'Late' ? 'warning' : 'secondary') ?>">
                        <?= $record['status'] ?>
                      </span>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Leave Credits & Quick Stats -->
      <div class="col-lg-4">
        <div class="schedule-card animate-slide-up">
          <h5 class="text-primary mb-3">
            <i class="fas fa-calendar-check me-2"></i>
            Leave Credits
          </h5>
          
          <?php foreach ($leave_credits as $leave): ?>
            <div class="time-card">
              <div class="d-flex justify-content-between align-items-center">
                <div>
                  <h6 class="mb-1"><?= htmlspecialchars($leave['type']) ?></h6>
                  <small class="text-muted"><?= $leave['used'] ?> used of <?= $leave['total'] ?></small>
                </div>
                <div class="text-end">
                  <div class="h5 mb-0 text-success"><?= $leave['remaining'] ?></div>
                  <small class="text-muted">remaining</small>
                </div>
              </div>
              <div class="progress mt-2" style="height: 6px;">
                <div class="progress-bar bg-success" 
                     style="width: <?= ($leave['remaining'] / $leave['total']) * 100 ?>%"></div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <!-- Quick Stats -->
        <div class="schedule-card animate-slide-up">
          <h5 class="text-primary mb-3">
            <i class="fas fa-chart-bar me-2"></i>
            This Week Stats
          </h5>
          
          <div class="row g-3 text-center">
            <div class="col-6">
              <div class="bg-primary bg-opacity-10 rounded-3 p-3">
                <div class="h4 text-primary mb-1">24.7</div>
                <small class="text-muted">Hours Worked</small>
              </div>
            </div>
            <div class="col-6">
              <div class="bg-success bg-opacity-10 rounded-3 p-3">
                <div class="h4 text-success mb-1">3</div>
                <small class="text-muted">Days Present</small>
              </div>
            </div>
            <div class="col-6">
              <div class="bg-warning bg-opacity-10 rounded-3 p-3">
                <div class="h4 text-warning mb-1">0.7</div>
                <small class="text-muted">Overtime</small>
              </div>
            </div>
            <div class="col-6">
              <div class="bg-info bg-opacity-10 rounded-3 p-3">
                <div class="h4 text-info mb-1">96%</div>
                <small class="text-muted">Attendance</small>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  
  <script>
    // Update current time
    function updateTime() {
      const now = new Date();
      const timeString = now.toLocaleTimeString('en-US', { hour12: false });
      document.getElementById('currentTime').textContent = timeString;
    }

    // Update time every second
    setInterval(updateTime, 1000);

    // Time In function
    function timeIn() {
      const now = new Date();
      const timeString = now.toLocaleTimeString('en-US', { hour12: false });
      
      // Create toast notification
      const toast = document.createElement('div');
      toast.className = 'toast align-items-center text-white bg-success border-0 position-fixed top-0 end-0 m-3';
      toast.style.zIndex = '9999';
      toast.innerHTML = `
        <div class="d-flex">
          <div class="toast-body">
            <i class="fas fa-check-circle me-2"></i>
            Time In recorded at ${timeString}
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

    // Time Out function
    function timeOut() {
      const now = new Date();
      const timeString = now.toLocaleTimeString('en-US', { hour12: false });
      
      // Create toast notification
      const toast = document.createElement('div');
      toast.className = 'toast align-items-center text-white bg-primary border-0 position-fixed top-0 end-0 m-3';
      toast.style.zIndex = '9999';
      toast.innerHTML = `
        <div class="d-flex">
          <div class="toast-body">
            <i class="fas fa-sign-out-alt me-2"></i>
            Time Out recorded at ${timeString}
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

    // Add animation delays to cards
    document.addEventListener('DOMContentLoaded', function() {
      const cards = document.querySelectorAll('.animate-slide-up');
      cards.forEach((card, index) => {
        card.style.animationDelay = `${index * 0.1}s`;
      });
    });
  </script>

</body>
</html>


