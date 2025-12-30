<?php
// Enhanced Succession Planning System
require_once 'auth_check.php';

$pdo = getPDO();
$employee_id = getCurrentEmployeeId();

// Get current user info
$user = getCurrentEmployee();

// Get all employees with their succession plans
$stmt = $pdo->query("
    SELECT 
        e.id, e.full_name, e.position as role,
        sp.target_position as target_role, sp.readiness_level as readiness,
        sp.development_notes as notes, sp.target_date as succession_date
    FROM employees e
    LEFT JOIN succession_plans sp ON e.id = sp.employee_id
    ORDER BY e.full_name
");
$employees = $stmt->fetchAll();

// Calculate succession planning metrics
$totalEmployees = count($employees);
$employeesWithPlans = 0;
$readinessStats = ['high' => 0, 'medium' => 0, 'low' => 0];

foreach ($employees as $emp) {
    if ($emp['target_role']) {
        $employeesWithPlans++;
        if ($emp['readiness']) {
            $readinessStats[$emp['readiness']]++;
        }
    }
}

$planCoverage = $totalEmployees > 0 ? round(($employeesWithPlans / $totalEmployees) * 100, 1) : 0;

// Get key positions that need succession planning
$stmt = $pdo->query("
    SELECT DISTINCT position 
    FROM employees 
    WHERE position LIKE '%Manager%' OR position LIKE '%Lead%' OR position LIKE '%Director%'
    ORDER BY position
");
$keyPositions = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Handle form submission
$success_message = '';
if ($_POST) {
    $target_employee_id = $_POST['employee_id'] ?? null;
    $target_role = $_POST['target_role'] ?? '';
    $readiness = $_POST['readiness'] ?? 'low';
    $notes = $_POST['notes'] ?? '';
    $target_date = $_POST['target_date'] ?? null;
    
    if ($target_employee_id && $target_role) {
        try {
            // Check if succession plan already exists
            $stmt = $pdo->prepare("SELECT id FROM succession_plans WHERE employee_id = ?");
            $stmt->execute([$target_employee_id]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                // Update existing plan
                $stmt = $pdo->prepare("
                    UPDATE succession_plans 
                    SET target_position = ?, readiness_level = ?, development_notes = ?, target_date = ?, updated_at = NOW()
                    WHERE employee_id = ?
                ");
                $stmt->execute([$target_role, $readiness, $notes, $target_date, $target_employee_id]);
            } else {
                // Create new plan
                $stmt = $pdo->prepare("
                    INSERT INTO succession_plans (employee_id, target_position, readiness_level, development_notes, target_date, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, NOW(), NOW())
                ");
                $stmt->execute([$target_employee_id, $target_role, $readiness, $notes, $target_date]);
            }
            
            $success_message = "Succession plan updated successfully!";
            // Refresh data
            header("Location: " . $_SERVER['PHP_SELF'] . "?success=1");
            exit;
        } catch (Exception $e) {
            $success_message = "Error updating succession plan: " . $e->getMessage();
        }
    }
}

// Check for success message
if (isset($_GET['success'])) {
    $success_message = "Succession plan updated successfully!";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Succession Planning</title>
  
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

    .succession-header {
      background: linear-gradient(135deg, var(--primary-color), #94dcf4);
      color: white;
      border-radius: 20px;
      padding: 2rem;
      margin-bottom: 2rem;
      box-shadow: 0 8px 25px rgba(75, 197, 236, 0.3);
    }

    .succession-card {
      background: white;
      border-radius: 15px;
      padding: 2rem;
      box-shadow: 0 4px 15px rgba(0,0,0,0.08);
      border: none;
      margin-bottom: 2rem;
      transition: transform 0.2s, box-shadow 0.2s;
    }

    .succession-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    }

    .readiness-circle {
      width: 80px;
      height: 80px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-weight: bold;
      font-size: 1.5rem;
      margin: 0 auto 1rem;
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

    .position-card {
      background: white;
      border-radius: 12px;
      padding: 1.5rem;
      box-shadow: 0 2px 10px rgba(0,0,0,0.08);
      border-left: 4px solid var(--primary-color);
      margin-bottom: 1rem;
      transition: all 0.3s;
    }

    .position-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 15px rgba(0,0,0,0.15);
    }
  </style>
</head>
<body class="bg-light">
  <div class="container-fluid p-4">
    <!-- Succession Header -->
    <div class="succession-header animate-slide-up text-center">
      <h1 class="mb-2">
        <i class="fas fa-sitemap me-3"></i>
        Succession Planning
      </h1>
      <p class="mb-3 opacity-75">Plan and manage career progression and leadership development</p>
    </div>

    <!-- Success Messages -->
    <?php if (!empty($success_message)): ?>
      <div class="alert alert-success border-0 rounded-4 animate-slide-up" role="alert">
        <i class="fas fa-check-circle me-2"></i>
        <?= htmlspecialchars($success_message) ?>
        <br><small>This is demo mode. Use the succession.sql file to set up the database for full functionality.</small>
      </div>
    <?php endif; ?>
    
    <!-- Demo Mode Notice -->
    <div class="alert alert-info border-0 rounded-4 animate-slide-up" role="alert">
      <i class="fas fa-info-circle me-2"></i>
      <strong>Demo Mode Active</strong> - This page is running with sample data.
      <br><small>To enable database functionality, import the <strong>succession.sql</strong> file and update your database connection.</small>
    </div>

    <!-- Metrics Cards -->
    <div class="row g-4 mb-4">
      <div class="col-lg-4">
        <div class="succession-card text-center animate-slide-up" style="animation-delay: 0.1s;">
          <div class="h3 text-primary mb-2"><?= $totalEmployees ?></div>
          <div class="text-muted">
            <i class="fas fa-users me-2"></i>Total Employees
          </div>
          <small class="text-muted d-block mt-2">Active workforce</small>
        </div>
      </div>
      
      <div class="col-lg-4">
        <div class="succession-card text-center animate-slide-up" style="animation-delay: 0.2s;">
          <div class="h3 text-success mb-2"><?= $planCoverage ?>%</div>
          <div class="text-muted mb-3">
            <i class="fas fa-route me-2"></i>Succession Coverage
          </div>
          <div class="progress mb-2" style="height: 8px;">
            <div class="progress-bar bg-success" style="width: <?= $planCoverage ?>%"></div>
          </div>
          <small class="text-muted"><?= $employeesWithPlans ?> of <?= $totalEmployees ?> employees have succession plans</small>
        </div>
      </div>
      
      <div class="col-lg-4">
        <div class="succession-card animate-slide-up" style="animation-delay: 0.3s;">
          <h6 class="text-primary mb-3">
            <i class="fas fa-chart-pie me-2"></i>Readiness Distribution
          </h6>
          <div class="row g-2 text-center">
            <div class="col-4">
              <div class="readiness-circle bg-success"><?= $readinessStats['high'] ?></div>
              <small class="text-muted">High Ready</small>
            </div>
            <div class="col-4">
              <div class="readiness-circle bg-warning"><?= $readinessStats['medium'] ?></div>
              <small class="text-muted">Medium Ready</small>
            </div>
            <div class="col-4">
              <div class="readiness-circle bg-danger"><?= $readinessStats['low'] ?></div>
              <small class="text-muted">Low Ready</small>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Key Positions Section -->
    <div class="succession-card animate-slide-up">
      <h4 class="text-primary mb-4">
        <i class="fas fa-star me-2"></i>
        Key Positions Requiring Succession Planning
      </h4>
      <div class="row g-3">
        <?php foreach ($keyPositions as $position): ?>
          <?php 
          $candidates = array_filter($employees, function($emp) use ($position) {
            return $emp['target_role'] === $position;
          });
          $candidateCount = count($candidates);
          ?>
          <div class="col-lg-3 col-md-4 col-sm-6">
            <div class="position-card">
              <h6 class="text-primary mb-2"><?= htmlspecialchars($position) ?></h6>
              <div class="text-muted">
                <i class="fas fa-users me-1"></i>
                <?= $candidateCount ?> candidate<?= $candidateCount !== 1 ? 's' : '' ?> identified
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Succession Plans Management -->
    <div class="succession-card animate-slide-up">
      <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="text-primary mb-0">
          <i class="fas fa-list-check me-2"></i>
          Employee Succession Plans
        </h4>
        <button class="btn btn-primary" onclick="toggleForm('addForm')">
          <i class="fas fa-plus me-2"></i>Add Succession Plan
        </button>
      </div>
      
      <!-- Add/Edit Form -->
      <div id="addForm" class="collapse">
        <div class="card bg-light border-0 mb-4">
          <div class="card-body">
            <h5 class="card-title text-primary mb-3">Add/Update Succession Plan</h5>
            <form method="POST">
              <div class="row g-3">
                <div class="col-md-4">
                  <label for="employee_id" class="form-label fw-semibold">Employee</label>
                  <select name="employee_id" id="employee_id" class="form-select" required>
                    <option value="">Select Employee</option>
                    <?php foreach ($employees as $emp): ?>
                      <option value="<?= $emp['id'] ?>"><?= htmlspecialchars($emp['full_name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                
                <div class="col-md-4">
                  <label for="target_role" class="form-label fw-semibold">Target Role</label>
                  <select name="target_role" id="target_role" class="form-select" required>
                    <option value="">Select Target Role</option>
                    <?php foreach ($keyPositions as $position): ?>
                      <option value="<?= htmlspecialchars($position) ?>"><?= htmlspecialchars($position) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                
                <div class="col-md-4">
                  <label for="readiness" class="form-label fw-semibold">Readiness Level</label>
                  <select name="readiness" id="readiness" class="form-select" required>
                    <option value="low">Low - Needs Development</option>
                    <option value="medium">Medium - Nearly Ready</option>
                    <option value="high">High - Ready Now</option>
                  </select>
                </div>
                
                <div class="col-12">
                  <label for="notes" class="form-label fw-semibold">Development Notes</label>
                  <textarea name="notes" id="notes" class="form-control" rows="4" placeholder="Enter development plans, training needs, timeline, etc."></textarea>
                </div>
                
                <div class="col-12">
                  <button type="submit" class="btn btn-success">
                    <i class="fas fa-save me-2"></i>Save Succession Plan
                  </button>
                  <button type="button" class="btn btn-secondary ms-2" onclick="toggleForm('addForm')">
                    <i class="fas fa-times me-2"></i>Cancel
                  </button>
                </div>
              </div>
            </form>
          </div>
        </div>
      </div>

      <!-- Succession Plans Table -->
      <div class="table-responsive">
        <table class="table table-hover">
          <thead class="table-light">
            <tr>
              <th>Employee</th>
              <th>Current Role</th>
              <th>Target Role</th>
              <th>Readiness</th>
              <th>Development Notes</th>
              <th>Last Updated</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($employees as $emp): ?>
              <?php if ($emp['target_role']): ?>
                <tr>
                  <td><strong><?= htmlspecialchars($emp['full_name']) ?></strong></td>
                  <td><?= htmlspecialchars($emp['role']) ?></td>
                  <td><?= htmlspecialchars($emp['target_role']) ?></td>
                  <td>
                    <span class="badge bg-<?= 
                      $emp['readiness'] === 'high' ? 'success' : 
                      ($emp['readiness'] === 'medium' ? 'warning' : 'danger') ?>">
                      <?= ucfirst($emp['readiness']) ?>
                    </span>
                  </td>
                  <td>
                    <span class="text-truncate d-inline-block" style="max-width: 200px;" title="<?= htmlspecialchars($emp['notes'] ?? '') ?>">
                      <?= htmlspecialchars(substr($emp['notes'] ?? '', 0, 100)) ?><?= strlen($emp['notes'] ?? '') > 100 ? '...' : '' ?>
                    </span>
                  </td>
                  <td><?= $emp['succession_date'] ? date('M j, Y', strtotime($emp['succession_date'])) : 'N/A' ?></td>
                  <td>
                    <button class="btn btn-outline-primary btn-sm" onclick="editSuccessionPlan(<?= $emp['id'] ?>, '<?= htmlspecialchars($emp['target_role']) ?>', '<?= $emp['readiness'] ?>', '<?= htmlspecialchars($emp['notes']) ?>')">
                      <i class="fas fa-edit"></i> Edit
                    </button>
                  </td>
                </tr>
              <?php endif; ?>
            <?php endforeach; ?>
            
            <?php if ($employeesWithPlans === 0): ?>
              <tr>
                <td colspan="7" class="text-center text-muted py-4">
                  <i class="fas fa-info-circle me-2"></i>
                  No succession plans created yet. Click "Add Succession Plan" to get started.
                </td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Employees Without Succession Plans -->
    <div class="succession-card animate-slide-up">
      <h4 class="text-primary mb-4">
        <i class="fas fa-exclamation-triangle me-2"></i>
        Employees Without Succession Plans
      </h4>
      
      <div class="table-responsive">
        <table class="table table-hover">
          <thead class="table-light">
            <tr>
              <th>Employee Name</th>
              <th>Current Role</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php 
            $employeesWithoutPlans = array_filter($employees, function($emp) {
              return !$emp['target_role'];
            });
            ?>
            
            <?php foreach ($employeesWithoutPlans as $emp): ?>
              <tr>
                <td><strong><?= htmlspecialchars($emp['full_name']) ?></strong></td>
                <td><?= htmlspecialchars($emp['role']) ?></td>
                <td>
                  <button class="btn btn-outline-warning btn-sm" onclick="createSuccessionPlan(<?= $emp['id'] ?>, '<?= htmlspecialchars($emp['full_name']) ?>')">
                    <i class="fas fa-plus me-1"></i>Create Plan
                  </button>
                </td>
              </tr>
            <?php endforeach; ?>
            
            <?php if (empty($employeesWithoutPlans)): ?>
              <tr>
                <td colspan="3" class="text-center text-success py-4">
                  <i class="fas fa-check-circle me-2"></i>
                  All employees have succession plans!
                </td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
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
        document.querySelectorAll('.progress-bar').forEach(bar => {
          const width = bar.style.width;
          bar.style.width = '0%';
          setTimeout(() => {
            bar.style.width = width;
          }, 100);
        });
      }, 500);
    });

    function toggleForm(formId) {
      const formElement = document.getElementById(formId);
      const bsCollapse = new bootstrap.Collapse(formElement, {
        toggle: true
      });
      
      // Reset form when hiding
      formElement.addEventListener('hidden.bs.collapse', function () {
        formElement.querySelector('form').reset();
      });
    }
    
    function editSuccessionPlan(employeeId, targetRole, readiness, notes) {
      // Show form
      const form = document.getElementById('addForm');
      const bsCollapse = new bootstrap.Collapse(form, {
        show: true
      });
      
      // Populate form fields
      document.getElementById('employee_id').value = employeeId;
      document.getElementById('target_role').value = targetRole;
      document.getElementById('readiness').value = readiness;
      document.getElementById('notes').value = notes;
      
      // Scroll to form
      setTimeout(() => {
        form.scrollIntoView({ behavior: 'smooth' });
      }, 300);
    }
    
    function createSuccessionPlan(employeeId, employeeName) {
      // Show form
      const form = document.getElementById('addForm');
      const bsCollapse = new bootstrap.Collapse(form, {
        show: true
      });
      
      // Pre-select employee
      document.getElementById('employee_id').value = employeeId;
      
      // Scroll to form
      setTimeout(() => {
        form.scrollIntoView({ behavior: 'smooth' });
      }, 300);
    }
  </script>

</body>
</html>
