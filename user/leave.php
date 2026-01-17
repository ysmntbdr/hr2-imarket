<?php
// Enhanced Leave Management System
require_once 'auth_check.php';

$pdo = getPDO();
$employee_id = getCurrentEmployeeId();

// Get current employee data including gender
$current_user = getCurrentEmployee();

// Try to get employee gender from database - check all possible field names
$employee_gender = null;
try {
    // First, try to get all columns to see what fields exist
    $stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
    $stmt->execute([$employee_id]);
    $emp_data = $stmt->fetch();
    
    if ($emp_data) {
        // Check for gender or sex field (try both common field names and case variations)
        $employee_gender = $emp_data['gender'] ?? $emp_data['sex'] ?? 
                          $emp_data['Gender'] ?? $emp_data['Sex'] ??
                          $emp_data['GENDER'] ?? $emp_data['SEX'] ??
                          null;
        
        // Also check if it's in the current_user array
        if (!$employee_gender && isset($current_user)) {
            $employee_gender = $current_user['gender'] ?? $current_user['sex'] ?? null;
        }
        
        // Normalize gender values (case-insensitive)
        if ($employee_gender) {
            $employee_gender = strtolower(trim($employee_gender));
            // Map common variations to standardized values
            if (in_array($employee_gender, ['m', 'male', 'man', 'masculine', '1'])) {
                $employee_gender = 'male';
            } elseif (in_array($employee_gender, ['f', 'female', 'woman', 'feminine', '0', '2'])) {
                $employee_gender = 'female';
            } else {
                // If value doesn't match known patterns, set to null
                $employee_gender = null;
            }
        }
    }
} catch (Exception $e) {
    // If gender field doesn't exist, log but continue
    error_log("Gender check error: " . $e->getMessage());
    $employee_gender = null;
}

// Debug: Uncomment to check what gender value was found
// error_log("Employee ID: $employee_id, Gender: " . ($employee_gender ?? 'null'));

// Use existing database tables
// The database already has 'leaves' table, so we'll work with that

// Get leave balances (using sample data for now, can be enhanced later)
$leave_balances = [
    ['type' => 'Annual Leave', 'allocated' => 25, 'used' => 0, 'remaining' => 25],
    ['type' => 'Sick Leave', 'allocated' => 10, 'used' => 0, 'remaining' => 10],
    ['type' => 'Personal Leave', 'allocated' => 5, 'used' => 0, 'remaining' => 5],
    ['type' => 'Emergency Leave', 'allocated' => 3, 'used' => 0, 'remaining' => 3]
];

// Calculate used days from actual leave requests
try {
    foreach ($leave_balances as &$balance) {
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(DATEDIFF(end_date, start_date) + 1), 0) as used_days
            FROM leaves 
            WHERE employee_id = ? AND leave_type = ? AND status = 'approved'
            AND YEAR(start_date) = YEAR(CURDATE())
        ");
        $stmt->execute([$employee_id, $balance['type']]);
        $result = $stmt->fetch();
        $balance['used'] = $result['used_days'] ?? 0;
        $balance['remaining'] = $balance['allocated'] - $balance['used'];
    }
} catch (Exception $e) {
    // Keep default values if query fails
}

// Get leave history from database
try {
    $stmt = $pdo->prepare("
        SELECT id, leave_type as type, start_date, end_date, 
               DATEDIFF(end_date, start_date) + 1 as days,
               status, reason, applied_at as applied_date
        FROM leaves 
        WHERE employee_id = ? 
        ORDER BY applied_at DESC
    ");
    $stmt->execute([$employee_id]);
    $leave_history = $stmt->fetchAll();
} catch (Exception $e) {
    // Fallback to sample data
    $leave_history = [
        ['id' => 1, 'type' => 'Annual Leave', 'start_date' => '2024-12-20', 'end_date' => '2024-12-27', 'days' => 8, 'status' => 'approved', 'reason' => 'Christmas vacation'],
        ['id' => 2, 'type' => 'Sick Leave', 'start_date' => '2024-11-15', 'end_date' => '2024-11-16', 'days' => 2, 'status' => 'approved', 'reason' => 'Medical appointment']
    ];
}

// Base leave types
$leave_types = [
    'Annual Leave' => ['max_days' => 25, 'requires_approval' => true],
    'Sick Leave' => ['max_days' => 10, 'requires_approval' => false],
    'Personal Leave' => ['max_days' => 5, 'requires_approval' => true],
    'Emergency Leave' => ['max_days' => 3, 'requires_approval' => true],
    'Maternity Leave' => ['max_days' => 90, 'requires_approval' => true],
    'Paternity Leave' => ['max_days' => 14, 'requires_approval' => true]
];

// Filter out maternity leave for male employees
// NOTE: If gender field doesn't exist in database, gender will be null
// For testing: uncomment the line below to force hide maternity leave
// If gender is explicitly 'male', remove maternity leave
if ($employee_gender === 'male') {
    unset($leave_types['Maternity Leave']);
}

// TEMPORARY FIX: If gender cannot be detected (field doesn't exist in DB),
// This will hide maternity leave for all users until gender field is properly set up
// You can comment this out once the gender field exists in your employees table
if ($employee_gender === null) {
    // Assume male if gender field doesn't exist (hide maternity leave)
    // Change this to 'female' or remove if you want maternity leave visible when gender is unknown
    unset($leave_types['Maternity Leave']);
}

// Debug output (remove in production) - uncomment to check gender detection
// For testing: force gender to 'male' if not detected (comment out for production)
// if ($employee_gender === null) {
//     // $employee_gender = 'male'; // Force male for testing - REMOVE IN PRODUCTION
// }

$message = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $leave_type = $_POST['leave_type'] ?? '';
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $reason = $_POST['reason'] ?? '';
    
    if (empty($leave_type) || empty($start_date) || empty($end_date) || empty($reason)) {
        $error = "All fields are required.";
    } elseif ($leave_type === 'Maternity Leave' && $employee_gender === 'male') {
        $error = "Maternity leave is not available for male employees. Please select a different leave type.";
    } else {
        try {
            // Calculate total days
            $start = new DateTime($start_date);
            $end = new DateTime($end_date);
            $interval = $start->diff($end);
            $total_days = $interval->days + 1; // Include both start and end dates
            
            // Validate dates
            if ($start > $end) {
                $error = "End date must be after start date.";
            } elseif ($start < new DateTime('today')) {
                $error = "Start date cannot be in the past.";
            } else {
                // Check leave balance (basic validation)
                $current_balance = null;
                foreach ($leave_balances as $balance) {
                    if ($balance['type'] === $leave_type) {
                        $current_balance = $balance;
                        break;
                    }
                }
                
                if ($current_balance && $total_days > $current_balance['remaining']) {
                    $error = "Insufficient leave balance. Available: " . $current_balance['remaining'] . " days.";
                } else {
                    // Insert leave request into existing 'leaves' table
                    $stmt = $pdo->prepare("
                        INSERT INTO leaves (employee_id, leave_type, start_date, end_date, reason, status, applied_at) 
                        VALUES (?, ?, ?, ?, ?, 'pending', NOW())
                    ");
                    $stmt->execute([$employee_id, $leave_type, $start_date, $end_date, $reason]);
                    
                    $leave_id = $pdo->lastInsertId();
                    $message = "Leave application submitted successfully! Application ID: LV-" . date('Y') . "-" . str_pad($leave_id, 4, '0', STR_PAD_LEFT);
                    
                    // Refresh leave history
                    $stmt = $pdo->prepare("
                        SELECT id, leave_type as type, start_date, end_date, 
                               DATEDIFF(end_date, start_date) + 1 as days,
                               status, reason, applied_at as applied_date
                        FROM leaves 
                        WHERE employee_id = ? 
                        ORDER BY applied_at DESC
                    ");
                    $stmt->execute([$employee_id]);
                    $leave_history = $stmt->fetchAll();
                    
                    // Recalculate leave balances
                    foreach ($leave_balances as &$balance) {
                        $stmt = $pdo->prepare("
                            SELECT COALESCE(SUM(DATEDIFF(end_date, start_date) + 1), 0) as used_days
                            FROM leaves 
                            WHERE employee_id = ? AND leave_type = ? AND status = 'approved'
                            AND YEAR(start_date) = YEAR(CURDATE())
                        ");
                        $stmt->execute([$employee_id, $balance['type']]);
                        $result = $stmt->fetch();
                        $balance['used'] = $result['used_days'] ?? 0;
                        $balance['remaining'] = $balance['allocated'] - $balance['used'];
                    }
                }
            }
        } catch (Exception $e) {
            $error = "Error submitting leave request: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Leave Management</title>
  
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

    .leave-header {
      background: linear-gradient(135deg, var(--primary-color), #94dcf4);
      color: white;
      border-radius: 20px;
      padding: 2rem;
      margin-bottom: 2rem;
      box-shadow: 0 8px 25px rgba(75, 197, 236, 0.3);
    }

    .leave-card {
      background: white;
      border-radius: 15px;
      padding: 2rem;
      box-shadow: 0 4px 15px rgba(0,0,0,0.08);
      border: none;
      margin-bottom: 2rem;
      transition: transform 0.2s, box-shadow 0.2s;
    }

    .leave-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    }

    .balance-card {
      background: white;
      border-radius: 15px;
      padding: 1.5rem;
      box-shadow: 0 4px 15px rgba(0,0,0,0.08);
      border: none;
      text-align: center;
      transition: transform 0.2s;
      height: 100%;
    }

    .balance-card:hover {
      transform: translateY(-3px);
    }

    .balance-type {
      font-weight: 600;
      color: var(--primary-color);
      margin-bottom: 1rem;
    }

    .balance-remaining {
      font-size: 2rem;
      font-weight: 700;
      color: #27ae60;
      margin-bottom: 0.5rem;
    }

    .balance-details {
      color: #6c757d;
      font-size: 0.9rem;
    }

    .progress-bar-custom {
      height: 8px;
      border-radius: 10px;
      background: #e9ecef;
      margin: 1rem 0;
      overflow: hidden;
    }

    .progress-fill {
      height: 100%;
      background: linear-gradient(90deg, var(--primary-color), #94dcf4);
      border-radius: 10px;
      transition: width 0.5s ease;
    }

    .form-control:focus, .form-select:focus {
      border-color: var(--primary-color);
      box-shadow: 0 0 0 0.2rem rgba(75, 197, 236, 0.25);
    }

    .btn-primary {
      background-color: var(--primary-color);
      border-color: var(--primary-color);
    }

    .btn-primary:hover {
      background-color: var(--primary-dark);
      border-color: var(--primary-dark);
    }

    .status-badge {
      padding: 0.5rem 1rem;
      border-radius: 25px;
      font-size: 0.8rem;
      font-weight: 600;
      text-transform: uppercase;
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
  </style>
</head>
<body class="bg-light">
  <div class="container-fluid p-4">
    <!-- Leave Header -->
    <div class="leave-header animate-slide-up text-center">
      <h1 class="mb-2">
        <i class="fas fa-calendar-alt me-3"></i>
        Leave Management
      </h1>
      <p class="mb-0 opacity-75">Manage your leave applications and view balances</p>
    </div>

    <!-- Messages -->
    <?php if ($message): ?>
      <div class="alert alert-success border-0 rounded-4 animate-slide-up" role="alert">
        <i class="fas fa-check-circle me-2"></i>
        <?= htmlspecialchars($message) ?>
      </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
      <div class="alert alert-danger border-0 rounded-4 animate-slide-up" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i>
        <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <!-- Leave Balance Cards -->
    <div class="row g-4 mb-4">
      <?php foreach ($leave_balances as $index => $balance): ?>
        <div class="col-xl-3 col-md-6">
          <div class="balance-card animate-slide-up" style="animation-delay: <?= $index * 0.1 ?>s;">
            <div class="balance-type"><?= htmlspecialchars($balance['type']) ?></div>
            <div class="balance-remaining"><?= $balance['remaining'] ?></div>
            <div class="balance-details">of <?= $balance['allocated'] ?> days remaining</div>
            <div class="progress-bar-custom">
              <div class="progress-fill" style="width: <?= ($balance['used'] / $balance['allocated']) * 100 ?>%;"></div>
            </div>
            <div class="balance-details"><?= $balance['used'] ?> days used</div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Main Content -->
    <div class="row g-4">
      <!-- Apply for Leave -->
      <div class="col-lg-8">
        <div class="leave-card animate-slide-up">
          <h4 class="text-primary mb-4">
            <i class="fas fa-plus-circle me-2"></i>
            Apply for Leave
          </h4>
          
          <form method="POST">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label fw-semibold">Leave Type</label>
                <select class="form-select" name="leave_type" required>
                  <option value="">Select leave type</option>
                  <?php foreach ($leave_types as $type => $details): ?>
                    <option value="<?= htmlspecialchars($type) ?>">
                      <?= htmlspecialchars($type) ?> (Max: <?= $details['max_days'] ?> days)
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="col-md-6">
                <label class="form-label fw-semibold">Duration</label>
                <div class="row g-2">
                  <div class="col">
                    <input type="date" class="form-control" name="start_date" required min="<?= date('Y-m-d') ?>" placeholder="Start Date">
                  </div>
                  <div class="col-auto d-flex align-items-center">
                    <span class="text-muted">to</span>
                  </div>
                  <div class="col">
                    <input type="date" class="form-control" name="end_date" required min="<?= date('Y-m-d') ?>" placeholder="End Date">
                  </div>
                </div>
              </div>

              <div class="col-12">
                <label class="form-label fw-semibold">Reason</label>
                <textarea class="form-control" name="reason" rows="4" placeholder="Please provide a reason for your leave application..." required></textarea>
              </div>

              <div class="col-md-6">
                <label class="form-label fw-semibold">Emergency Contact <small class="text-muted">(Optional)</small></label>
                <input type="text" class="form-control" name="emergency_contact" placeholder="Name and phone number">
              </div>

              <div class="col-md-6">
                <label class="form-label fw-semibold">Handover Notes <small class="text-muted">(Optional)</small></label>
                <textarea class="form-control" name="handover_notes" rows="3" placeholder="Any work handover instructions..."></textarea>
              </div>

              <div class="col-12">
                <button type="submit" class="btn btn-primary btn-lg">
                  <i class="fas fa-paper-plane me-2"></i>Submit Application
                </button>
              </div>
            </div>
          </form>
        </div>
      </div>

      <!-- Leave Calendar & Quick Stats -->
      <div class="col-lg-4">
        <!-- Leave Calendar Placeholder -->
        <div class="leave-card animate-slide-up mb-4">
          <h5 class="text-primary mb-3">
            <i class="fas fa-calendar me-2"></i>
            Leave Calendar
          </h5>
          <div class="text-center py-5">
            <i class="fas fa-calendar text-primary" style="font-size: 3rem; opacity: 0.3;"></i>
            <p class="text-muted mt-3 mb-2">Interactive Calendar</p>
            <small class="text-muted">View team leave schedules</small>
          </div>
        </div>

        <!-- Quick Stats -->
        <div class="leave-card animate-slide-up">
          <h5 class="text-primary mb-3">
            <i class="fas fa-chart-bar me-2"></i>
            Leave Statistics
          </h5>
          <div class="row g-3">
            <div class="col-6">
              <div class="text-center p-3 bg-primary bg-opacity-10 rounded-3">
                <div class="h4 text-primary mb-1">9.5</div>
                <small class="text-muted">Days Taken</small>
              </div>
            </div>
            <div class="col-6">
              <div class="text-center p-3 bg-warning bg-opacity-10 rounded-3">
                <div class="h4 text-warning mb-1">1</div>
                <small class="text-muted">Pending</small>
              </div>
            </div>
            <div class="col-6">
              <div class="text-center p-3 bg-success bg-opacity-10 rounded-3">
                <div class="h4 text-success mb-1">3</div>
                <small class="text-muted">Approved</small>
              </div>
            </div>
            <div class="col-6">
              <div class="text-center p-3 bg-info bg-opacity-10 rounded-3">
                <div class="h4 text-info mb-1">0.8</div>
                <small class="text-muted">Avg/Month</small>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Leave History -->
    <div class="leave-card animate-slide-up mt-4">
      <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="text-primary mb-0">
          <i class="fas fa-history me-2"></i>
          Leave History
        </h4>
        <div class="btn-group" role="group">
          <button type="button" class="btn btn-outline-primary btn-sm active">All</button>
          <button type="button" class="btn btn-outline-primary btn-sm">Pending</button>
          <button type="button" class="btn btn-outline-primary btn-sm">Approved</button>
        </div>
      </div>
      
      <div class="table-responsive">
        <table class="table table-hover">
          <thead class="table-light">
            <tr>
              <th>Application ID</th>
              <th>Leave Type</th>
              <th>Start Date</th>
              <th>End Date</th>
              <th>Days</th>
              <th>Status</th>
              <th>Reason</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($leave_history as $leave): ?>
              <tr>
                <td><span class="badge bg-light text-dark">LA-<?= date('Y') ?>-<?= str_pad($leave['id'], 3, '0', STR_PAD_LEFT) ?></span></td>
                <td><?= htmlspecialchars($leave['type']) ?></td>
                <td><?= date('M j, Y', strtotime($leave['start_date'])) ?></td>
                <td><?= date('M j, Y', strtotime($leave['end_date'])) ?></td>
                <td><span class="badge bg-info"><?= $leave['days'] ?> days</span></td>
                <td>
                  <span class="status-badge bg-<?= $leave['status'] === 'approved' ? 'success' : ($leave['status'] === 'pending' ? 'warning' : 'danger') ?>">
                    <?= ucfirst($leave['status']) ?>
                  </span>
                </td>
                <td>
                  <span class="text-truncate d-inline-block" style="max-width: 200px;" title="<?= htmlspecialchars($leave['reason']) ?>">
                    <?= htmlspecialchars(substr($leave['reason'], 0, 50)) ?><?= strlen($leave['reason']) > 50 ? '...' : '' ?>
                  </span>
                </td>
                <td>
                  <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-secondary" title="View Details">
                      <i class="fas fa-eye"></i>
                    </button>
                    <?php if ($leave['status'] === 'pending'): ?>
                      <button class="btn btn-outline-danger" title="Cancel">
                        <i class="fas fa-times"></i>
                      </button>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      // Auto-calculate days when dates are selected
      const startDate = document.querySelector('input[name="start_date"]');
      const endDate = document.querySelector('input[name="end_date"]');
      
      function calculateDays() {
        if (startDate.value && endDate.value) {
          const start = new Date(startDate.value);
          const end = new Date(endDate.value);
          const timeDiff = end.getTime() - start.getTime();
          const dayDiff = Math.ceil(timeDiff / (1000 * 3600 * 24)) + 1;
          
          if (dayDiff > 0) {
            // You could show the calculated days here
            console.log(`Leave duration: ${dayDiff} days`);
          }
        }
      }
      
      startDate?.addEventListener('change', calculateDays);
      endDate?.addEventListener('change', calculateDays);

      // Add animation delays to cards
      const cards = document.querySelectorAll('.animate-slide-up');
      cards.forEach((card, index) => {
        card.style.animationDelay = `${index * 0.1}s`;
      });

      // Filter functionality for leave history
      document.querySelectorAll('.btn-group .btn').forEach(btn => {
        btn.addEventListener('click', function() {
          // Remove active class from all buttons
          document.querySelectorAll('.btn-group .btn').forEach(b => b.classList.remove('active'));
          // Add active class to clicked button
          this.classList.add('active');
          
          // Here you would implement the actual filtering logic
          console.log('Filter by:', this.textContent.trim());
        });
      });
    });
  </script>

</body>
</html>
