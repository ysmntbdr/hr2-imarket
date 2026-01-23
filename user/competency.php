<?php
// Enhanced Competency Management System
require_once 'auth_check.php';

$pdo = getPDO();
$employee_id = getCurrentEmployeeId();

// Get current employee data
$user = getCurrentEmployee();

// Handle competency assessment form submission
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $competency_id = $_POST['competency_id'] ?? '';
    $proficiency_level = $_POST['proficiency_level'] ?? '';
    $notes = $_POST['notes'] ?? '';
    
    if (empty($competency_id) || empty($proficiency_level)) {
        $error = "Please select a competency and proficiency level.";
    } elseif ($proficiency_level < 1 || $proficiency_level > 5) {
        $error = "Proficiency level must be between 1 and 5.";
    } else {
        try {
            // Check if assessment already exists
            $stmt = $pdo->prepare("SELECT id FROM employee_competencies WHERE employee_id = ? AND competency_id = ?");
            $stmt->execute([$employee_id, $competency_id]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                // Update existing assessment
                $stmt = $pdo->prepare("
                    UPDATE employee_competencies 
                    SET proficiency_level = ?, last_assessed = CURDATE(), notes = ?, updated_at = NOW()
                    WHERE employee_id = ? AND competency_id = ?
                ");
                $stmt->execute([$proficiency_level, $notes, $employee_id, $competency_id]);
                $message = "Competency assessment updated successfully!";
            } else {
                // Create new assessment
                $stmt = $pdo->prepare("
                    INSERT INTO employee_competencies (employee_id, competency_id, proficiency_level, last_assessed, notes, assessed_by)
                    VALUES (?, ?, ?, CURDATE(), ?, ?)
                ");
                $stmt->execute([$employee_id, $competency_id, $proficiency_level, $notes, $employee_id]);
                $message = "Competency assessment added successfully!";
            }
            
            // Refresh page to show updated data
            header("Location: " . $_SERVER['PHP_SELF'] . "?success=1");
            exit;
        } catch (Exception $e) {
            $error = "Error saving assessment: " . $e->getMessage();
        }
    }
}

// Check for success message
if (isset($_GET['success'])) {
    $message = "Competency assessment saved successfully!";
}

// Get all competencies
$stmt = $pdo->query("SELECT * FROM competencies ORDER BY category, name");
$competencies = $stmt->fetchAll();

// Get employee's competencies with assessment details
$stmt = $pdo->prepare("
    SELECT c.*, ec.proficiency_level as level, ec.last_assessed 
    FROM competencies c 
    JOIN employee_competencies ec ON c.id = ec.competency_id 
    WHERE ec.employee_id = ? 
    ORDER BY c.category, c.name
");
$stmt->execute([$employee_id]);
$userCompetencies = $stmt->fetchAll();

// Get missing competencies (competencies not yet assessed for this employee)
$stmt = $pdo->prepare("
    SELECT c.* 
    FROM competencies c 
    WHERE c.id NOT IN (
        SELECT competency_id 
        FROM employee_competencies 
        WHERE employee_id = ?
    ) 
    ORDER BY c.category, c.name
");
$stmt->execute([$employee_id]);
$missingCompetencies = $stmt->fetchAll();

// Calculate statistics
$total = count($competencies);
$owned = count($userCompetencies);
$percent = $total > 0 ? round(($owned / $total) * 100, 1) : 0;

// Get competency levels from database
$competency_levels = [];
try {
    $stmt = $pdo->query("SELECT id, label, color, description FROM competency_levels ORDER BY id");
    $levels = $stmt->fetchAll();
    foreach ($levels as $level) {
        $competency_levels[$level['id']] = [
            'label' => $level['label'],
            'color' => $level['color'],
            'description' => $level['description']
        ];
    }
} catch (Exception $e) {
    // Fallback to default levels if table doesn't exist
    $competency_levels = [
        1 => ['label' => 'Beginner', 'color' => 'danger', 'description' => 'Basic understanding'],
        2 => ['label' => 'Developing', 'color' => 'warning', 'description' => 'Some experience'],
        3 => ['label' => 'Proficient', 'color' => 'info', 'description' => 'Good working knowledge'],
        4 => ['label' => 'Advanced', 'color' => 'success', 'description' => 'Expert level'],
        5 => ['label' => 'Expert', 'color' => 'primary', 'description' => 'Industry leader']
    ];
}

// Group competencies by category
$competencyByCategory = [];
foreach ($userCompetencies as $comp) {
    $competencyByCategory[$comp['category']][] = $comp;
}

// Get competency statistics from database
$competency_stats = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            c.category,
            COUNT(*) as total_in_category,
            COUNT(ec.id) as owned_in_category,
            AVG(ec.proficiency_level) as avg_level
        FROM competencies c
        LEFT JOIN employee_competencies ec ON c.id = ec.competency_id AND ec.employee_id = ?
        GROUP BY c.category
        ORDER BY c.category
    ");
    $stmt->execute([$employee_id]);
    $category_stats = $stmt->fetchAll();
    
    foreach ($category_stats as $stat) {
        $competency_stats[$stat['category']] = [
            'total' => $stat['total_in_category'],
            'owned' => $stat['owned_in_category'],
            'percentage' => $stat['total_in_category'] > 0 ? round(($stat['owned_in_category'] / $stat['total_in_category']) * 100, 1) : 0,
            'avg_level' => round($stat['avg_level'] ?? 0, 1)
        ];
    }
} catch (Exception $e) {
    error_log("Competency stats error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Competency Management</title>
  
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

    .competency-header {
      background: linear-gradient(135deg, var(--primary-color), #94dcf4);
      color: white;
      border-radius: 20px;
      padding: 2rem;
      margin-bottom: 2rem;
      box-shadow: 0 8px 25px rgba(75, 197, 236, 0.3);
    }

    .competency-card {
      background: white;
      border-radius: 15px;
      padding: 2rem;
      box-shadow: 0 4px 15px rgba(0,0,0,0.08);
      border: none;
      margin-bottom: 2rem;
      transition: transform 0.2s, box-shadow 0.2s;
    }

    .competency-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    }

    .competency-item {
      background: white;
      border-radius: 12px;
      padding: 1.5rem;
      box-shadow: 0 2px 10px rgba(0,0,0,0.08);
      border-left: 4px solid var(--primary-color);
      margin-bottom: 1rem;
      transition: all 0.3s;
    }

    .competency-item:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 15px rgba(0,0,0,0.15);
    }

    .level-badge {
      padding: 0.5rem 1rem;
      border-radius: 25px;
      font-size: 0.8rem;
      font-weight: 600;
      text-transform: uppercase;
    }

    .progress-circle {
      width: 80px;
      height: 80px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: bold;
      color: white;
      font-size: 1.2rem;
      background: conic-gradient(var(--primary-color) 0deg, var(--primary-color) calc(var(--progress) * 3.6deg), #e9ecef calc(var(--progress) * 3.6deg));
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

    .skill-radar {
      position: relative;
      width: 200px;
      height: 200px;
      margin: 0 auto;
    }

    .category-icon {
      width: 50px;
      height: 50px;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.5rem;
      margin-bottom: 1rem;
    }
  </style>
</head>
<body class="bg-light">
  <?php include 'includes/sidebar.php'; ?>
  
  <main class="main-content">
    <div class="container-fluid p-4">
    <!-- Competency Header -->
    <div class="competency-header animate-slide-up text-center">
      <h1 class="mb-2">
        <i class="fas fa-chart-line me-3"></i>
        Competency Management
      </h1>
      <p class="mb-3 opacity-75">Track and develop your professional skills</p>
      <div class="row justify-content-center">
        <div class="col-md-6">
          <div class="progress-circle mx-auto" style="--progress: <?= $percent ?>">
            <div class="text-center">
              <div class="fs-4 fw-bold"><?= $percent ?>%</div>
              <small>Coverage</small>
            </div>
          </div>
          <p class="mt-3 mb-0">You have <?= $owned ?> out of <?= $total ?> competencies</p>
        </div>
      </div>
    </div>

    <!-- Success/Error Messages -->
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

    <!-- Debug Information (remove in production) -->
    <?php if (empty($competencies)): ?>
      <div class="alert alert-info border-0 rounded-4 animate-slide-up" role="alert">
        <h6><i class="fas fa-info-circle me-2"></i>Setup Required</h6>
        <p class="mb-2">No competencies found in the database. To use the competency system:</p>
        <ol class="mb-2">
          <li>Run the <code>complete_hr_database.sql</code> file to create tables and sample data</li>
          <li>Or manually add competencies to the <code>competencies</code> table</li>
          <li>Run <code>add_competency_levels.sql</code> to add competency levels</li>
        </ol>
        <p class="mb-3"><strong>Current status:</strong> 
          Competencies: <?= count($competencies) ?> | 
          User Competencies: <?= count($userCompetencies) ?> | 
          Missing: <?= count($missingCompetencies) ?>
        </p>
        <div class="d-flex gap-2">
          <a href="test_dashboard.php" class="btn btn-outline-info btn-sm">
            <i class="fas fa-database me-1"></i>Test Database
          </a>
          <button onclick="location.reload()" class="btn btn-outline-primary btn-sm">
            <i class="fas fa-refresh me-1"></i>Refresh Page
          </button>
        </div>
      </div>
    <?php endif; ?>

    <!-- Self-Assessment Form -->
    <div class="competency-card animate-slide-up mb-4">
      <h5 class="text-primary mb-3">
        <i class="fas fa-user-check me-2"></i>
        Self-Assessment
      </h5>
      
      <form method="POST" class="row g-3">
        <div class="col-md-4">
          <label for="competency_id" class="form-label">Select Competency</label>
          <select class="form-select" id="competency_id" name="competency_id" required>
            <option value="">Choose a competency...</option>
            <?php if (empty($competencies)): ?>
              <option value="" disabled>No competencies available - Please add competencies to the database</option>
            <?php else: ?>
              <?php foreach ($competencies as $comp): ?>
                <option value="<?= $comp['id'] ?>"><?= htmlspecialchars($comp['name']) ?> (<?= htmlspecialchars($comp['category']) ?>)</option>
              <?php endforeach; ?>
            <?php endif; ?>
          </select>
          <?php if (empty($competencies)): ?>
            <div class="form-text text-warning">
              <i class="fas fa-exclamation-triangle me-1"></i>
              No competencies found. Please ensure the competencies table has data.
            </div>
          <?php endif; ?>
        </div>
        
        <div class="col-md-3">
          <label for="proficiency_level" class="form-label">Proficiency Level</label>
          <select class="form-select" id="proficiency_level" name="proficiency_level" required>
            <option value="">Select level...</option>
            <?php foreach ($competency_levels as $level => $info): ?>
              <option value="<?= $level ?>"><?= $level ?> - <?= $info['label'] ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        
        <div class="col-md-3">
          <label for="notes" class="form-label">Notes (Optional)</label>
          <input type="text" class="form-control" id="notes" name="notes" placeholder="Add notes...">
        </div>
        
        <div class="col-md-2">
          <label class="form-label">&nbsp;</label>
          <button type="submit" class="btn btn-primary w-100">
            <i class="fas fa-save me-1"></i>
            Assess
          </button>
        </div>
      </form>
    </div>

    <!-- Competency Overview -->
    <div class="row g-4 mb-4">
      <div class="col-md-3">
        <div class="competency-card text-center animate-slide-up" style="animation-delay: 0.1s;">
          <div class="category-icon bg-primary bg-opacity-10 text-primary mx-auto">
            <i class="fas fa-code"></i>
          </div>
          <h6 class="text-primary">Technical Skills</h6>
          <div class="h4 mb-2"><?= count(array_filter($userCompetencies, fn($c) => $c['category'] === 'Technical')) ?></div>
          <small class="text-muted">Competencies</small>
        </div>
      </div>
      <div class="col-md-3">
        <div class="competency-card text-center animate-slide-up" style="animation-delay: 0.2s;">
          <div class="category-icon bg-success bg-opacity-10 text-success mx-auto">
            <i class="fas fa-users"></i>
          </div>
          <h6 class="text-success">Soft Skills</h6>
          <div class="h4 mb-2"><?= count(array_filter($userCompetencies, fn($c) => $c['category'] === 'Soft Skills')) ?></div>
          <small class="text-muted">Competencies</small>
        </div>
      </div>
      <div class="col-md-3">
        <div class="competency-card text-center animate-slide-up" style="animation-delay: 0.3s;">
          <div class="category-icon bg-warning bg-opacity-10 text-warning mx-auto">
            <i class="fas fa-tasks"></i>
          </div>
          <h6 class="text-warning">Management</h6>
          <div class="h4 mb-2"><?= count(array_filter($userCompetencies, fn($c) => $c['category'] === 'Management')) ?></div>
          <small class="text-muted">Competencies</small>
        </div>
      </div>
      <div class="col-md-3">
        <div class="competency-card text-center animate-slide-up" style="animation-delay: 0.4s;">
          <div class="category-icon bg-info bg-opacity-10 text-info mx-auto">
            <i class="fas fa-star"></i>
          </div>
          <h6 class="text-info">Average Level</h6>
          <div class="h4 mb-2"><?= $owned > 0 ? number_format(array_sum(array_column($userCompetencies, 'level')) / $owned, 1) : '0' ?></div>
          <small class="text-muted">Out of 5</small>
        </div>
      </div>
    </div>

    <!-- Main Content -->
    <div class="row g-4">
      <!-- Current Competencies -->
      <div class="col-lg-8">
        <div class="competency-card animate-slide-up">
          <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="text-primary mb-0">
              <i class="fas fa-check-circle me-2"></i>
              Your Competencies
            </h4>
            <div class="btn-group" role="group">
              <button type="button" class="btn btn-outline-primary btn-sm active">All</button>
              <button type="button" class="btn btn-outline-primary btn-sm">Technical</button>
              <button type="button" class="btn btn-outline-primary btn-sm">Soft Skills</button>
              <button type="button" class="btn btn-outline-primary btn-sm">Management</button>
            </div>
          </div>
          
          <?php if (empty($userCompetencies)): ?>
            <div class="text-center py-5">
              <i class="fas fa-chart-line text-muted" style="font-size: 3rem; opacity: 0.3;"></i>
              <p class="text-muted mt-3">No competencies assigned yet</p>
            </div>
          <?php else: ?>
            <div class="row g-3">
              <?php foreach ($userCompetencies as $comp): ?>
                <div class="col-12">
                  <div class="competency-item">
                    <div class="row align-items-center">
                      <div class="col-md-6">
                        <h6 class="mb-1 text-primary"><?= htmlspecialchars($comp['name']) ?></h6>
                        <p class="mb-2 text-muted small"><?= htmlspecialchars($comp['description']) ?></p>
                        <span class="badge bg-light text-dark"><?= htmlspecialchars($comp['category']) ?></span>
                      </div>
                      <div class="col-md-3">
                        <div class="text-center">
                          <span class="level-badge bg-<?= $competency_levels[$comp['level']]['color'] ?>">
                            Level <?= $comp['level'] ?> - <?= $competency_levels[$comp['level']]['label'] ?>
                          </span>
                          <div class="mt-2">
                            <div class="progress" style="height: 8px;">
                              <div class="progress-bar bg-<?= $competency_levels[$comp['level']]['color'] ?>" 
                                   style="width: <?= ($comp['level'] / 5) * 100 ?>%"></div>
                            </div>
                          </div>
                        </div>
                      </div>
                      <div class="col-md-3 text-end">
                        <small class="text-muted">Last assessed:</small><br>
                        <small class="fw-semibold"><?= date('M j, Y', strtotime($comp['last_assessed'])) ?></small>
                        <div class="mt-2">
                          <button class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-edit"></i> Update
                          </button>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Development Opportunities -->
      <div class="col-lg-4">
        <div class="competency-card animate-slide-up">
          <h5 class="text-primary mb-3">
            <i class="fas fa-plus-circle me-2"></i>
            Development Opportunities
          </h5>
          
          <?php if (empty($missingCompetencies)): ?>
            <div class="text-center py-4">
              <i class="fas fa-trophy text-success" style="font-size: 2rem;"></i>
              <p class="text-success mt-2 mb-0">All competencies acquired!</p>
            </div>
          <?php else: ?>
            <?php foreach ($missingCompetencies as $comp): ?>
              <div class="competency-item border-start border-warning border-3">
                <h6 class="text-warning mb-2"><?= htmlspecialchars($comp['name']) ?></h6>
                <p class="mb-2 small text-muted"><?= htmlspecialchars($comp['description']) ?></p>
                <div class="d-flex justify-content-between align-items-center">
                  <span class="badge bg-light text-dark"><?= htmlspecialchars($comp['category']) ?></span>
                  <button class="btn btn-outline-warning btn-sm" onclick="selectCompetency(<?= $comp['id'] ?>, '<?= htmlspecialchars($comp['name']) ?>')">
                    <i class="fas fa-plus"></i> Add
                  </button>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <!-- Competency Levels Guide -->
        <div class="competency-card animate-slide-up mt-4">
          <h5 class="text-primary mb-3">
            <i class="fas fa-info-circle me-2"></i>
            Level Guide
          </h5>
          
          <?php foreach ($competency_levels as $level => $info): ?>
            <div class="d-flex align-items-center mb-3">
              <span class="level-badge bg-<?= $info['color'] ?> me-3">
                <?= $level ?>
              </span>
              <div>
                <div class="fw-semibold"><?= $info['label'] ?></div>
                <small class="text-muted"><?= $info['description'] ?></small>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- Assessment History -->
    <div class="competency-card animate-slide-up mt-4">
      <h4 class="text-primary mb-4">
        <i class="fas fa-history me-2"></i>
        Assessment History
      </h4>
      
      <div class="table-responsive">
        <table class="table table-hover">
          <thead class="table-light">
            <tr>
              <th>Competency</th>
              <th>Category</th>
              <th>Current Level</th>
              <th>Last Assessment</th>
              <th>Progress</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($userCompetencies as $comp): ?>
              <tr>
                <td>
                  <div class="fw-semibold"><?= htmlspecialchars($comp['name']) ?></div>
                  <small class="text-muted"><?= htmlspecialchars($comp['description']) ?></small>
                </td>
                <td>
                  <span class="badge bg-light text-dark"><?= htmlspecialchars($comp['category']) ?></span>
                </td>
                <td>
                  <span class="level-badge bg-<?= $competency_levels[$comp['level']]['color'] ?>">
                    Level <?= $comp['level'] ?>
                  </span>
                </td>
                <td><?= date('M j, Y', strtotime($comp['last_assessed'])) ?></td>
                <td>
                  <div class="progress" style="height: 8px; width: 100px;">
                    <div class="progress-bar bg-<?= $competency_levels[$comp['level']]['color'] ?>" 
                         style="width: <?= ($comp['level'] / 5) * 100 ?>%"></div>
                  </div>
                </td>
                <td>
                  <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-primary" title="View Details">
                      <i class="fas fa-eye"></i>
                    </button>
                    <button class="btn btn-outline-success" title="Reassess" onclick="reassessCompetency(<?= $comp['id'] ?>, '<?= htmlspecialchars($comp['name']) ?>', <?= $comp['level'] ?>)">
                      <i class="fas fa-redo"></i>
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

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      // Add animation delays to cards
      const cards = document.querySelectorAll('.animate-slide-up');
      cards.forEach((card, index) => {
        card.style.animationDelay = `${index * 0.1}s`;
      });

      // Filter functionality for competencies
      document.querySelectorAll('.btn-group .btn').forEach(btn => {
        btn.addEventListener('click', function() {
          // Remove active class from all buttons
          document.querySelectorAll('.btn-group .btn').forEach(b => b.classList.remove('active'));
          // Add active class to clicked button
          this.classList.add('active');
          
          const filter = this.textContent.trim();
          const competencyItems = document.querySelectorAll('.competency-item');
          
          competencyItems.forEach(item => {
            const category = item.querySelector('.badge').textContent.trim();
            if (filter === 'All' || category === filter) {
              item.closest('.col-12').style.display = 'block';
            } else {
              item.closest('.col-12').style.display = 'none';
            }
          });
        });
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

    // Function to select competency from development opportunities
    function selectCompetency(competencyId, competencyName) {
      const competencySelect = document.getElementById('competency_id');
      const proficiencySelect = document.getElementById('proficiency_level');
      
      // Set the competency
      competencySelect.value = competencyId;
      
      // Focus on proficiency level
      proficiencySelect.focus();
      
      // Scroll to form
      document.querySelector('.competency-card form').scrollIntoView({ 
        behavior: 'smooth', 
        block: 'center' 
      });
      
      // Add highlight effect
      highlightForm('#ffc107');
    }

    // Function to reassess existing competency
    function reassessCompetency(competencyId, competencyName, currentLevel) {
      const competencySelect = document.getElementById('competency_id');
      const proficiencySelect = document.getElementById('proficiency_level');
      
      // Set the competency and current level
      competencySelect.value = competencyId;
      proficiencySelect.value = currentLevel;
      
      // Focus on proficiency level for easy change
      proficiencySelect.focus();
      
      // Scroll to form
      document.querySelector('.competency-card form').scrollIntoView({ 
        behavior: 'smooth', 
        block: 'center' 
      });
      
      // Add highlight effect
      highlightForm('#198754');
      
      // Show tooltip
      showTooltip('Update your proficiency level for: ' + competencyName);
    }

    // Helper function to highlight form
    function highlightForm(color) {
      const form = document.querySelector('.competency-card form').parentElement;
      form.style.border = '2px solid ' + color;
      form.style.borderRadius = '15px';
      form.style.transition = 'all 0.3s ease';
      
      setTimeout(() => {
        form.style.border = '';
      }, 3000);
    }

    // Helper function to show tooltip
    function showTooltip(message) {
      const tooltip = document.createElement('div');
      tooltip.className = 'alert alert-info alert-dismissible fade show position-fixed';
      tooltip.style.top = '20px';
      tooltip.style.right = '20px';
      tooltip.style.zIndex = '9999';
      tooltip.style.maxWidth = '300px';
      tooltip.innerHTML = `
        <i class="fas fa-info-circle me-2"></i>
        ${message}
        <button type="button" class="btn-close" onclick="this.parentElement.remove()"></button>
      `;
      
      document.body.appendChild(tooltip);
      
      // Auto remove after 5 seconds
      setTimeout(() => {
        if (tooltip.parentElement) {
          tooltip.remove();
        }
      }, 5000);
    }
  </script>

    </div>
  </main>

</body>
</html>
