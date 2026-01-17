<?php
// Employee Succession Planning View - Shows individual employee's succession plan
require_once 'auth_check.php';

$pdo = getPDO();
$employee_id = getCurrentEmployeeId();

// Get current user info
$current_user = getCurrentEmployee();

// Get current employee's succession plan
$succession_plan = null;
try {
    $stmt = $pdo->prepare("
        SELECT 
            sp.*,
            e.full_name, e.position as current_position, e.department
        FROM employees e
        LEFT JOIN succession_plans sp ON e.id = sp.employee_id
        WHERE e.id = ?
    ");
    $stmt->execute([$employee_id]);
    $result = $stmt->fetch();
    
    if ($result) {
        $succession_plan = [
            'id' => $result['id'],
            'target_position' => $result['target_position'],
            'readiness_level' => $result['readiness_level'],
            'target_date' => $result['target_date'],
            'created_at' => $result['created_at'],
            'updated_at' => $result['updated_at'],
            'current_position' => $result['current_position'] ?? $current_user['position'],
            'full_name' => $result['full_name'] ?? $current_user['full_name'],
            'department' => $result['department'] ?? $current_user['department']
        ];
    }
} catch (Exception $e) {
    error_log("Succession plan query error: " . $e->getMessage());
    $succession_plan = null;
}

// Calculate days until target date
$days_until_promotion = null;
$promotion_status = 'not_set';
if ($succession_plan && $succession_plan['target_date']) {
    $target_date = new DateTime($succession_plan['target_date']);
    $today = new DateTime();
    $diff = $today->diff($target_date);
    $days_until_promotion = $target_date > $today ? $diff->days : -$diff->days;
    
    if ($days_until_promotion > 0 && $days_until_promotion <= 90) {
        $promotion_status = 'soon';
    } elseif ($days_until_promotion <= 0) {
        $promotion_status = 'overdue';
    } else {
        $promotion_status = 'upcoming';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>My Succession Plan</title>
  
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

    .readiness-badge {
      display: inline-block;
      padding: 0.75rem 1.5rem;
      border-radius: 50px;
      font-weight: 600;
      font-size: 1.1rem;
    }

    .timeline-item {
      position: relative;
      padding-left: 2rem;
      padding-bottom: 2rem;
    }

    .timeline-item:before {
      content: '';
      position: absolute;
      left: 0.5rem;
      top: 0.5rem;
      bottom: -2rem;
      width: 2px;
      background: #e0e0e0;
    }

    .timeline-item:last-child:before {
      display: none;
    }

    .timeline-marker {
      position: absolute;
      left: 0;
      top: 0.5rem;
      width: 1rem;
      height: 1rem;
      border-radius: 50%;
      background: var(--primary-color);
      border: 3px solid white;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }

    .promotion-countdown {
      font-size: 2.5rem;
      font-weight: 700;
      color: var(--primary-color);
    }

    .info-box {
      background: linear-gradient(135deg, #f8f9fa, #e9ecef);
      border-left: 4px solid var(--primary-color);
      padding: 1.5rem;
      border-radius: 10px;
      margin-bottom: 1rem;
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

    .career-path {
      display: flex;
      align-items: center;
      gap: 1rem;
      padding: 1.5rem;
      background: linear-gradient(135deg, #f8f9fa, #e9ecef);
      border-radius: 15px;
      margin: 1rem 0;
    }

    .career-arrow {
      font-size: 2rem;
      color: var(--primary-color);
    }
  </style>
</head>
<body class="bg-light">
  <div class="container-fluid p-4">
    <!-- Header -->
    <div class="succession-header animate-slide-up">
      <div class="row align-items-center">
        <div class="col-md-8">
          <h1 class="mb-2">
            <i class="fas fa-sitemap me-3"></i>
            My Succession Plan
          </h1>
          <p class="mb-0 opacity-75">Your career progression path and promotion timeline</p>
        </div>
        <div class="col-md-4 text-end">
          <div class="bg-white bg-opacity-25 rounded-3 p-3">
            <div class="text-white-50 small">Managed by</div>
            <div class="h5 mb-0">HR Department</div>
          </div>
        </div>
      </div>
    </div>

    <?php if ($succession_plan): ?>
      <!-- Succession Plan Exists -->
      <div class="row g-4">
        <!-- Main Succession Details -->
        <div class="col-lg-8">
          <div class="succession-card animate-slide-up">
            <h4 class="text-primary mb-4">
              <i class="fas fa-user-tie me-2"></i>
              Your Career Progression
            </h4>
            
            <!-- Career Path -->
            <div class="career-path">
              <div class="text-center flex-grow-1">
                <div class="h6 text-muted mb-2">Current Position</div>
                <div class="h4 text-dark fw-bold"><?= htmlspecialchars($succession_plan['current_position']) ?></div>
                <div class="small text-muted"><?= htmlspecialchars($succession_plan['department']) ?></div>
              </div>
              <div class="career-arrow">
                <i class="fas fa-arrow-right"></i>
              </div>
              <div class="text-center flex-grow-1">
                <div class="h6 text-muted mb-2">Target Position</div>
                <div class="h4 text-primary fw-bold"><?= htmlspecialchars($succession_plan['target_position']) ?></div>
                <div class="small text-success">Promotion Target</div>
              </div>
            </div>

            <!-- Readiness Level -->
            <div class="info-box">
              <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="text-muted fw-semibold">Readiness Level</span>
                <span class="readiness-badge bg-<?= 
                  $succession_plan['readiness_level'] === 'high' ? 'success' : 
                  ($succession_plan['readiness_level'] === 'medium' ? 'warning' : 'danger') ?> text-white">
                  <?= ucfirst($succession_plan['readiness_level']) ?> 
                  <?= $succession_plan['readiness_level'] === 'high' ? '- Ready for Promotion' : 
                      ($succession_plan['readiness_level'] === 'medium' ? '- Nearly Ready' : '- Needs Development') ?>
                </span>
              </div>
              <div class="progress mt-2" style="height: 10px;">
                <div class="progress-bar bg-<?= 
                  $succession_plan['readiness_level'] === 'high' ? 'success' : 
                  ($succession_plan['readiness_level'] === 'medium' ? 'warning' : 'danger') ?>" 
                     style="width: <?= $succession_plan['readiness_level'] === 'high' ? '100' : 
                                  ($succession_plan['readiness_level'] === 'medium' ? '65' : '30') ?>%"></div>
              </div>
            </div>

            <!-- Development Notes -->
            <?php if (!empty($succession_plan['development_notes'])): ?>
            <div class="info-box">
              <h6 class="text-primary mb-3">
                <i class="fas fa-clipboard-list me-2"></i>
                Development Notes from HR
              </h6>
              <p class="mb-0" style="white-space: pre-wrap;"><?= htmlspecialchars($succession_plan['development_notes']) ?></p>
            </div>
            <?php endif; ?>

            <!-- Timeline -->
            <div class="mt-4">
              <h6 class="text-primary mb-3">
                <i class="fas fa-calendar-alt me-2"></i>
                Timeline
              </h6>
              <div class="timeline-item">
                <div class="timeline-marker"></div>
                <div class="ms-4">
                  <div class="fw-semibold">Succession Plan Created</div>
                  <div class="text-muted small">
                    <?= date('F j, Y', strtotime($succession_plan['created_at'])) ?>
                  </div>
                </div>
              </div>
              <?php if ($succession_plan['target_date']): ?>
              <div class="timeline-item">
                <div class="timeline-marker" style="background: var(--primary-color);"></div>
                <div class="ms-4">
                  <div class="fw-semibold">Expected Promotion Date</div>
                  <div class="text-primary fw-bold">
                    <?= date('F j, Y', strtotime($succession_plan['target_date'])) ?>
                  </div>
                  <?php if ($days_until_promotion !== null): ?>
                    <?php if ($promotion_status === 'soon'): ?>
                      <div class="text-warning mt-1">
                        <i class="fas fa-exclamation-triangle me-1"></i>
                        Promotion coming soon!
                      </div>
                    <?php elseif ($promotion_status === 'overdue'): ?>
                      <div class="text-info mt-1">
                        <i class="fas fa-info-circle me-1"></i>
                        Please contact HR for updates
                      </div>
                    <?php else: ?>
                      <div class="text-muted mt-1">
                        <?= $days_until_promotion ?> days remaining
                      </div>
                    <?php endif; ?>
                  <?php endif; ?>
                </div>
              </div>
              <?php endif; ?>
              <?php if ($succession_plan['updated_at']): ?>
              <div class="timeline-item">
                <div class="timeline-marker" style="background: #28a745;"></div>
                <div class="ms-4">
                  <div class="fw-semibold">Last Updated</div>
                  <div class="text-muted small">
                    <?= date('F j, Y', strtotime($succession_plan['updated_at'])) ?>
                  </div>
                </div>
              </div>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- Sidebar Info -->
        <div class="col-lg-4">
          <!-- Promotion Countdown -->
          <?php if ($succession_plan['target_date'] && $days_until_promotion !== null && $days_until_promotion > 0): ?>
          <div class="succession-card text-center animate-slide-up">
            <i class="fas fa-calendar-check fa-3x text-primary mb-3"></i>
            <div class="promotion-countdown"><?= $days_until_promotion ?></div>
            <div class="h5 text-muted mb-2">Days Until Promotion</div>
            <div class="text-muted small">
              Target: <?= date('M j, Y', strtotime($succession_plan['target_date'])) ?>
            </div>
          </div>
          <?php endif; ?>

          <!-- Status Card -->
          <div class="succession-card animate-slide-up">
            <h6 class="text-primary mb-3">
              <i class="fas fa-info-circle me-2"></i>
              Status Information
            </h6>
            <div class="mb-3">
              <div class="small text-muted mb-1">Plan Status</div>
              <div class="badge bg-success fs-6">Active</div>
            </div>
            <div class="mb-3">
              <div class="small text-muted mb-1">Managed By</div>
              <div class="fw-semibold">HR Department</div>
            </div>
            <div class="mb-3">
              <div class="small text-muted mb-1">Last Review</div>
              <div class="fw-semibold">
                <?= $succession_plan['updated_at'] ? date('M j, Y', strtotime($succession_plan['updated_at'])) : 'Not reviewed yet' ?>
              </div>
            </div>
            <hr>
            <div class="alert alert-info border-0 mb-0">
              <small>
                <i class="fas fa-info-circle me-1"></i>
                This succession plan is managed by your HR department. 
                For questions or updates, please contact HR.
              </small>
            </div>
          </div>

          <!-- Readiness Guide -->
          <div class="succession-card animate-slide-up">
            <h6 class="text-primary mb-3">
              <i class="fas fa-lightbulb me-2"></i>
              Readiness Levels
            </h6>
            <div class="small">
              <div class="mb-2">
                <span class="badge bg-success me-2">High</span>
                Ready for promotion now
              </div>
              <div class="mb-2">
                <span class="badge bg-warning me-2">Medium</span>
                Nearly ready, may need minor development
              </div>
              <div class="mb-0">
                <span class="badge bg-danger me-2">Low</span>
                Needs development and training
              </div>
            </div>
          </div>
        </div>
      </div>

    <?php else: ?>
      <!-- No Succession Plan -->
      <div class="row">
        <div class="col-12">
          <div class="succession-card text-center animate-slide-up">
            <i class="fas fa-calendar-times fa-4x text-muted mb-4"></i>
            <h3 class="text-muted mb-3">No Succession Plan Yet</h3>
            <p class="text-muted mb-4">
              You don't have an active succession plan at the moment. 
              Your HR department will create one when you're being considered for promotion.
            </p>
            <div class="alert alert-info border-0 d-inline-block">
              <i class="fas fa-info-circle me-2"></i>
              <strong>Note:</strong> Succession plans are managed by your HR department. 
              If you have questions about your career progression, please contact HR.
            </div>
          </div>
        </div>
      </div>
    <?php endif; ?>
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
    });
  </script>

</body>
</html>
