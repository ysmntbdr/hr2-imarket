<?php
// Enhanced Training Management System
require_once 'auth_check.php';

$pdo = getPDO();
$employee_id = getCurrentEmployeeId();

// Get current employee data
$employee = getCurrentEmployee();

// Calculate training statistics
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_trainings,
        SUM(CASE WHEN et.completion_date IS NOT NULL THEN 1 ELSE 0 END) as completed_trainings,
        SUM(CASE WHEN t.start_date > CURDATE() THEN 1 ELSE 0 END) as upcoming_trainings,
        SUM(CASE WHEN t.start_date <= CURDATE() AND t.end_date >= CURDATE() AND et.completion_date IS NULL THEN 1 ELSE 0 END) as in_progress,
        SUM(CASE WHEN et.certificate_earned = 1 THEN 1 ELSE 0 END) as certificates_earned,
        SUM(t.duration_hours) as training_hours,
        AVG(et.rating) as average_rating
    FROM employee_trainings et 
    JOIN trainings t ON et.training_id = t.id 
    WHERE et.employee_id = ?
");
$stmt->execute([$employee_id]);
$stats = $stmt->fetch();

$training_stats = [
    'total_trainings' => $stats['total_trainings'] ?? 0,
    'completed_trainings' => $stats['completed_trainings'] ?? 0,
    'upcoming_trainings' => $stats['upcoming_trainings'] ?? 0,
    'in_progress' => $stats['in_progress'] ?? 0,
    'certificates_earned' => $stats['certificates_earned'] ?? 0,
    'training_hours' => $stats['training_hours'] ?? 0,
    'average_rating' => round($stats['average_rating'] ?? 0, 1)
];

// Get upcoming trainings (available for enrollment)
$stmt = $pdo->prepare("
    SELECT t.*, 
           (SELECT COUNT(*) FROM employee_trainings WHERE training_id = t.id) as enrolled
    FROM trainings t 
    WHERE t.start_date > CURDATE() 
    AND t.id NOT IN (SELECT training_id FROM employee_trainings WHERE employee_id = ?)
    ORDER BY t.start_date ASC
");
$stmt->execute([$employee_id]);
$upcoming_trainings = $stmt->fetchAll();

// Get completed trainings for this employee
$stmt = $pdo->prepare("
    SELECT t.*, et.completion_date, et.certificate_earned as certificate, et.rating
    FROM trainings t 
    JOIN employee_trainings et ON t.id = et.training_id 
    WHERE et.employee_id = ? AND et.completion_date IS NOT NULL
    ORDER BY et.completion_date DESC
");
$stmt->execute([$employee_id]);
$completed_trainings = $stmt->fetchAll();

// Get current training (in progress)
$stmt = $pdo->prepare("
    SELECT t.*, et.enrollment_date, et.progress,
           CASE 
               WHEN t.start_date <= CURDATE() AND t.end_date >= CURDATE() THEN 'In Progress'
               ELSE 'Scheduled'
           END as status
    FROM trainings t 
    JOIN employee_trainings et ON t.id = et.training_id 
    WHERE et.employee_id = ? AND et.completion_date IS NULL
    AND t.start_date <= CURDATE()
    ORDER BY t.start_date DESC
    LIMIT 1
");
$stmt->execute([$employee_id]);
$current_training = $stmt->fetch();

$available_trainings = [
    [
        'id' => 7,
        'title' => 'Machine Learning Fundamentals',
        'description' => 'Introduction to ML algorithms and applications',
        'category' => 'Technical',
        'duration' => '4 days',
        'rating' => 4.5,
        'skills' => ['Machine Learning', 'Python', 'Scikit-learn']
    ],
    [
        'id' => 8,
        'title' => 'Agile Methodology Workshop',
        'description' => 'Scrum and Kanban implementation strategies',
        'category' => 'Management',
        'duration' => '2 days',
        'rating' => 4.4,
        'skills' => ['Scrum', 'Kanban', 'Agile Planning']
    ]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Training Management</title>
  
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

    .training-header {
      background: linear-gradient(135deg, var(--primary-color), #94dcf4);
      color: white;
      border-radius: 20px;
      padding: 2rem;
      margin-bottom: 2rem;
      box-shadow: 0 8px 25px rgba(75, 197, 236, 0.3);
    }

    .training-card {
      background: white;
      border-radius: 15px;
      padding: 2rem;
      box-shadow: 0 4px 15px rgba(0,0,0,0.08);
      border: none;
      margin-bottom: 2rem;
      transition: transform 0.2s, box-shadow 0.2s;
    }

    .training-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    }

    .training-item {
      background: white;
      border-radius: 12px;
      padding: 1.5rem;
      box-shadow: 0 2px 10px rgba(0,0,0,0.08);
      border-left: 4px solid var(--primary-color);
      margin-bottom: 1rem;
      transition: all 0.3s;
    }

    .training-item:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 15px rgba(0,0,0,0.15);
    }

    .skill-tag {
      background: var(--primary-color);
      color: white;
      padding: 0.25rem 0.75rem;
      border-radius: 15px;
      font-size: 0.8rem;
      margin: 0.25rem;
      display: inline-block;
    }

    .category-badge {
      padding: 0.5rem 1rem;
      border-radius: 25px;
      font-size: 0.8rem;
      font-weight: 600;
      text-transform: uppercase;
    }

    .progress-circle {
      width: 60px;
      height: 60px;
      border-radius: 50%;
      background: conic-gradient(var(--primary-color) 0deg, var(--primary-color) calc(var(--progress) * 3.6deg), #e9ecef calc(var(--progress) * 3.6deg));
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: bold;
      color: var(--primary-color);
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

    .rating-stars {
      color: #ffc107;
    }

    .enrollment-progress {
      height: 8px;
      border-radius: 10px;
      background: #e9ecef;
      overflow: hidden;
    }

    .enrollment-fill {
      height: 100%;
      background: linear-gradient(90deg, var(--primary-color), #94dcf4);
      border-radius: 10px;
      transition: width 0.5s ease;
    }
  </style>
</head>
<body class="bg-light">
  <div class="container-fluid p-4">
    <!-- Training Header -->
    <div class="training-header animate-slide-up text-center">
      <h1 class="mb-2">
        <i class="fas fa-chalkboard-teacher me-3"></i>
        Training Management
      </h1>
      <p class="mb-3 opacity-75">Enhance your skills through professional training programs</p>
      <div class="row justify-content-center">
        <div class="col-md-8">
          <h3>Welcome, <?= htmlspecialchars($employee['full_name'] ?? '') ?></h3>
          <p class="mb-0"><?= htmlspecialchars($employee['position']) ?> • <?= htmlspecialchars($employee['department']) ?></p>
        </div>
      </div>
    </div>

    <!-- Training Statistics -->
    <div class="row g-4 mb-4">
      <div class="col-xl-2 col-md-4">
        <div class="training-card text-center animate-slide-up" style="animation-delay: 0.1s;">
          <div class="h3 text-primary mb-2"><?= $training_stats['total_trainings'] ?></div>
          <div class="text-muted">Total Trainings</div>
        </div>
      </div>
      <div class="col-xl-2 col-md-4">
        <div class="training-card text-center animate-slide-up" style="animation-delay: 0.2s;">
          <div class="h3 text-success mb-2"><?= $training_stats['completed_trainings'] ?></div>
          <div class="text-muted">Completed</div>
        </div>
      </div>
      <div class="col-xl-2 col-md-4">
        <div class="training-card text-center animate-slide-up" style="animation-delay: 0.3s;">
          <div class="h3 text-warning mb-2"><?= $training_stats['upcoming_trainings'] ?></div>
          <div class="text-muted">Upcoming</div>
        </div>
      </div>
      <div class="col-xl-2 col-md-4">
        <div class="training-card text-center animate-slide-up" style="animation-delay: 0.4s;">
          <div class="h3 text-info mb-2"><?= $training_stats['certificates_earned'] ?></div>
          <div class="text-muted">Certificates</div>
        </div>
      </div>
      <div class="col-xl-2 col-md-4">
        <div class="training-card text-center animate-slide-up" style="animation-delay: 0.5s;">
          <div class="h3 text-primary mb-2"><?= $training_stats['training_hours'] ?>h</div>
          <div class="text-muted">Training Hours</div>
        </div>
      </div>
      <div class="col-xl-2 col-md-4">
        <div class="training-card text-center animate-slide-up" style="animation-delay: 0.6s;">
          <div class="h3 text-success mb-2"><?= $training_stats['average_rating'] ?></div>
          <div class="text-muted">Avg Rating</div>
        </div>
      </div>
    </div>

    <!-- Current Training Progress -->
    <?php if ($current_training): ?>
    <div class="training-card animate-slide-up mb-4">
      <h4 class="text-primary mb-4">
        <i class="fas fa-play-circle me-2"></i>
        Current Training
      </h4>
      
      <div class="training-item border-start border-success border-4">
        <div class="row align-items-center">
          <div class="col-md-8">
            <div class="d-flex justify-content-between align-items-start mb-2">
              <h6 class="text-success mb-1"><?= htmlspecialchars($current_training['title']) ?></h6>
              <span class="category-badge bg-success"><?= htmlspecialchars($current_training['category']) ?></span>
            </div>
            
            <p class="mb-2 text-muted"><?= htmlspecialchars($current_training['description']) ?></p>
            
            <div class="mb-2">
              <?php foreach ($current_training['skills'] as $skill): ?>
                <span class="skill-tag bg-success"><?= htmlspecialchars($skill) ?></span>
              <?php endforeach; ?>
            </div>
            
            <div class="row g-2 text-muted small">
              <div class="col-auto">
                <i class="fas fa-user me-1"></i><?= htmlspecialchars($current_training['instructor']) ?>
              </div>
              <div class="col-auto">
                <i class="fas fa-calendar me-1"></i><?= date('M j', strtotime($current_training['start_date'])) ?> - <?= date('M j', strtotime($current_training['end_date'])) ?>
              </div>
              <div class="col-auto">
                <i class="fas fa-clock me-1"></i><?= $current_training['duration'] ?>
              </div>
            </div>
          </div>
          
          <div class="col-md-4 text-end">
            <div class="progress-circle mx-auto mb-2" style="--progress: <?= $current_training['progress'] ?>">
              <?= $current_training['progress'] ?>%
            </div>
            
            <div class="progress mb-2" style="height: 8px;">
              <div class="progress-bar bg-success" style="width: <?= $current_training['progress'] ?>%"></div>
            </div>
            
            <button class="btn btn-success">
              <i class="fas fa-play"></i> Continue Training
            </button>
          </div>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Main Content -->
    <div class="row g-4">
      <!-- Upcoming Trainings -->
      <div class="col-lg-8">
        <div class="training-card animate-slide-up">
          <h4 class="text-primary mb-4">
            <i class="fas fa-calendar-plus me-2"></i>
            Upcoming Trainings
          </h4>
          
          <?php if (empty($upcoming_trainings)): ?>
            <div class="text-center py-5">
              <i class="fas fa-calendar text-muted" style="font-size: 3rem; opacity: 0.3;"></i>
              <p class="text-muted mt-3">No upcoming trainings scheduled</p>
            </div>
          <?php else: ?>
            <?php foreach ($upcoming_trainings as $training): ?>
              <div class="training-item">
                <div class="row align-items-center">
                  <div class="col-md-8">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                      <h6 class="text-primary mb-1"><?= htmlspecialchars($training['title']) ?></h6>
                      <span class="category-badge bg-<?= 
                        $training['category'] === 'Technical' ? 'primary' : 
                        ($training['category'] === 'Leadership' ? 'warning' : 'info') ?>">
                        <?= htmlspecialchars($training['category']) ?>
                      </span>
                    </div>
                    
                    <p class="mb-2 text-muted"><?= htmlspecialchars($training['description']) ?></p>
                    
                    <div class="mb-2">
                      <?php foreach ($training['skills'] as $skill): ?>
                        <span class="skill-tag"><?= htmlspecialchars($skill) ?></span>
                      <?php endforeach; ?>
                    </div>
                    
                    <div class="row g-2 text-muted small">
                      <div class="col-auto">
                        <i class="fas fa-user me-1"></i><?= htmlspecialchars($training['instructor']) ?>
                      </div>
                      <div class="col-auto">
                        <i class="fas fa-calendar me-1"></i><?= date('M j', strtotime($training['start_date'])) ?> - <?= date('M j', strtotime($training['end_date'])) ?>
                      </div>
                      <div class="col-auto">
                        <i class="fas fa-clock me-1"></i><?= $training['duration'] ?>
                      </div>
                      <div class="col-auto">
                        <i class="fas fa-map-marker-alt me-1"></i><?= htmlspecialchars($training['location']) ?>
                      </div>
                    </div>
                  </div>
                  
                  <div class="col-md-4 text-end">
                    <div class="mb-2">
                      <small class="text-muted">Enrollment</small>
                      <div class="fw-bold"><?= $training['enrolled'] ?>/<?= $training['max_participants'] ?></div>
                    </div>
                    
                    <div class="enrollment-progress mb-2">
                      <div class="enrollment-fill" style="width: <?= ($training['enrolled'] / $training['max_participants']) * 100 ?>%"></div>
                    </div>
                    
                    <button class="btn btn-outline-primary btn-sm">
                      <i class="fas fa-plus"></i> Enroll
                    </button>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <!-- Completed Trainings -->
        <div class="training-card animate-slide-up">
          <h4 class="text-primary mb-4">
            <i class="fas fa-check-circle me-2"></i>
            Completed Trainings
          </h4>
          
          <div class="table-responsive">
            <table class="table table-hover">
              <thead class="table-light">
                <tr>
                  <th>Training</th>
                  <th>Category</th>
                  <th>Completion Date</th>
                  <th>Rating</th>
                  <th>Certificate</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($completed_trainings as $training): ?>
                  <tr>
                    <td>
                      <div class="fw-semibold"><?= htmlspecialchars($training['title']) ?></div>
                      <small class="text-muted"><?= htmlspecialchars($training['instructor']) ?></small>
                    </td>
                    <td>
                      <span class="category-badge bg-<?= 
                        $training['category'] === 'Technical' ? 'primary' : 
                        ($training['category'] === 'Security' ? 'danger' : 'warning') ?>">
                        <?= htmlspecialchars($training['category']) ?>
                      </span>
                    </td>
                    <td><?= date('M j, Y', strtotime($training['completion_date'])) ?></td>
                    <td>
                      <div class="rating-stars">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                          <i class="fas fa-star<?= $i <= $training['rating'] ? '' : '-o' ?>"></i>
                        <?php endfor; ?>
                        <?= $training['rating'] ?>
                      </div>
                    </td>
                    <td>
                      <?php if ($training['certificate']): ?>
                        <i class="fas fa-certificate text-warning" title="Certificate Available"></i>
                      <?php else: ?>
                        <span class="text-muted">-</span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-primary" title="View Details">
                          <i class="fas fa-eye"></i>
                        </button>
                        <?php if ($training['certificate']): ?>
                          <button class="btn btn-outline-success" title="Download Certificate">
                            <i class="fas fa-download"></i>
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

      <!-- Available Trainings & Quick Actions -->
      <div class="col-lg-4">
        <!-- Available Trainings -->
        <div class="training-card animate-slide-up">
          <h5 class="text-primary mb-3">
            <i class="fas fa-plus-circle me-2"></i>
            Available Trainings
          </h5>
          
          <?php foreach ($available_trainings as $training): ?>
            <div class="training-item border-start border-info border-3">
              <h6 class="text-info mb-2"><?= htmlspecialchars($training['title']) ?></h6>
              <p class="mb-2 small text-muted"><?= htmlspecialchars($training['description']) ?></p>
              
              <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="category-badge bg-<?= 
                  $training['category'] === 'Technical' ? 'primary' : 'warning' ?>">
                  <?= htmlspecialchars($training['category']) ?>
                </span>
                <div class="rating-stars small">
                  <?php for ($i = 1; $i <= 5; $i++): ?>
                    <i class="fas fa-star<?= $i <= $training['rating'] ? '' : '-o' ?>"></i>
                  <?php endfor; ?>
                  <?= $training['rating'] ?>
                </div>
              </div>
              
              <div class="mb-2">
                <?php foreach ($training['skills'] as $skill): ?>
                  <span class="skill-tag bg-info"><?= htmlspecialchars($skill) ?></span>
                <?php endforeach; ?>
              </div>
              
              <div class="d-flex justify-content-between align-items-center">
                <small class="text-muted">
                  <i class="fas fa-clock me-1"></i><?= $training['duration'] ?>
                </small>
                <button class="btn btn-outline-info btn-sm">
                  <i class="fas fa-info"></i> Learn More
                </button>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <!-- Training Calendar -->
        <div class="training-card animate-slide-up mt-4">
          <h5 class="text-primary mb-3">
            <i class="fas fa-calendar-alt me-2"></i>
            Training Calendar
          </h5>
          
          <div class="text-center py-4">
            <i class="fas fa-calendar text-primary" style="font-size: 3rem; opacity: 0.3;"></i>
            <p class="text-muted mt-3 mb-2">Interactive Calendar</p>
            <small class="text-muted">View all training schedules</small>
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
        document.querySelectorAll('.progress-bar, .enrollment-fill').forEach(bar => {
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
          if (this.textContent.includes('Enroll') || this.textContent.includes('Learn More')) {
            e.preventDefault();
            
            // Create toast notification
            const toast = document.createElement('div');
            toast.className = 'toast align-items-center text-white bg-primary border-0 position-fixed top-0 end-0 m-3';
            toast.style.zIndex = '9999';
            toast.innerHTML = `
              <div class="d-flex">
                <div class="toast-body">
                  <i class="fas fa-info-circle me-2"></i>
                  Training enrollment will be available soon!
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
