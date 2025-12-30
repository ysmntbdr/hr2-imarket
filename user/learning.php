<?php
// Enhanced Learning Management System
require_once 'auth_check.php';

$pdo = getPDO();
$employee_id = getCurrentEmployeeId();

// Get current employee data
$employee = getCurrentEmployee();

// Ensure employee is an array to prevent errors
if (!$employee || !is_array($employee)) {
    $employee = [
        'full_name' => 'Guest User',
        'position' => 'N/A',
        'department' => 'N/A',
        'employee_id' => 'N/A'
    ];
}

// Handle form submissions
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'enroll') {
        $course_id = $_POST['course_id'] ?? '';
        
        if (empty($course_id)) {
            $error = "Please select a course to enroll in.";
        } else {
            try {
                // Check if already enrolled
                $stmt = $pdo->prepare("SELECT id FROM employee_courses WHERE employee_id = ? AND course_id = ?");
                $stmt->execute([$employee_id, $course_id]);
                
                if ($stmt->fetch()) {
                    $error = "You are already enrolled in this course.";
                } else {
                    // Enroll in course
                    $stmt = $pdo->prepare("
                        INSERT INTO employee_courses (employee_id, course_id, enrollment_date, progress)
                        VALUES (?, ?, CURDATE(), 0)
                    ");
                    $stmt->execute([$employee_id, $course_id]);
                    $message = "Successfully enrolled in the course!";
                    
                    // Refresh page to show updated data
                    header("Location: " . $_SERVER['PHP_SELF'] . "?success=enrolled");
                    exit;
                }
            } catch (Exception $e) {
                $error = "Error enrolling in course: " . $e->getMessage();
            }
        }
    } elseif ($action === 'update_progress') {
        $course_id = $_POST['course_id'] ?? '';
        $progress = $_POST['progress'] ?? '';
        
        if (empty($course_id) || empty($progress)) {
            $error = "Please provide course and progress information.";
        } elseif ($progress < 0 || $progress > 100) {
            $error = "Progress must be between 0 and 100.";
        } else {
            try {
                $completion_date = ($progress == 100) ? 'CURDATE()' : 'NULL';
                $certificate = ($progress == 100) ? 1 : 0;
                
                $stmt = $pdo->prepare("
                    UPDATE employee_courses 
                    SET progress = ?, 
                        completion_date = " . $completion_date . ",
                        certificate_earned = ?,
                        updated_at = NOW()
                    WHERE employee_id = ? AND course_id = ?
                ");
                $stmt->execute([$progress, $certificate, $employee_id, $course_id]);
                $message = "Course progress updated successfully!";
                
                // Refresh page to show updated data
                header("Location: " . $_SERVER['PHP_SELF'] . "?success=progress");
                exit;
            } catch (Exception $e) {
                $error = "Error updating progress: " . $e->getMessage();
            }
        }
    }
}

// Check for success messages
if (isset($_GET['success'])) {
    $success_type = $_GET['success'];
    if ($success_type === 'enrolled') {
        $message = "Successfully enrolled in the course!";
    } elseif ($success_type === 'progress') {
        $message = "Course progress updated successfully!";
    }
}

// Get employee's enrolled courses with progress
$stmt = $pdo->prepare("
    SELECT c.*, ec.enrollment_date as enrolled, ec.completion_date as completed, 
           ec.progress, ec.certificate_earned as certificate,
           CASE 
               WHEN ec.completion_date IS NOT NULL THEN 'Completed'
               WHEN ec.progress > 0 THEN 'In Progress'
               ELSE 'Not Started'
           END as status
    FROM courses c 
    JOIN employee_courses ec ON c.id = ec.course_id 
    WHERE ec.employee_id = ? 
    ORDER BY ec.enrollment_date DESC
");
$stmt->execute([$employee_id]);
$learning_courses = $stmt->fetchAll();

// Get available courses (not enrolled by this employee)
$stmt = $pdo->prepare("
    SELECT c.* 
    FROM courses c 
    WHERE c.id NOT IN (
        SELECT course_id 
        FROM employee_courses 
        WHERE employee_id = ?
    ) 
    ORDER BY c.rating DESC, c.title
");
$stmt->execute([$employee_id]);
$available_courses = $stmt->fetchAll();

// Calculate learning statistics
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_courses,
        SUM(CASE WHEN ec.completion_date IS NOT NULL THEN 1 ELSE 0 END) as completed_courses,
        SUM(CASE WHEN ec.completion_date IS NULL AND ec.progress > 0 THEN 1 ELSE 0 END) as in_progress,
        SUM(c.duration_hours) as total_hours,
        SUM(CASE WHEN ec.certificate_earned = 1 THEN 1 ELSE 0 END) as certificates_earned,
        AVG(c.rating) as average_rating
    FROM employee_courses ec 
    JOIN courses c ON ec.course_id = c.id 
    WHERE ec.employee_id = ?
");
$stmt->execute([$employee_id]);
$stats = $stmt->fetch();

$learning_stats = [
    'total_courses' => $stats['total_courses'] ?? 0,
    'completed_courses' => $stats['completed_courses'] ?? 0,
    'in_progress' => $stats['in_progress'] ?? 0,
    'total_hours' => $stats['total_hours'] ?? 0,
    'certificates_earned' => $stats['certificates_earned'] ?? 0,
    'average_rating' => round($stats['average_rating'] ?? 0, 1)
];

// Get skill categories from database
$stmt = $pdo->query("
    SELECT DISTINCT category 
    FROM courses 
    ORDER BY category
");
$categories = $stmt->fetchAll(PDO::FETCH_COLUMN);

$skill_categories = [];
foreach ($categories as $category) {
    $stmt = $pdo->prepare("
        SELECT DISTINCT skills 
        FROM courses 
        WHERE category = ? AND skills IS NOT NULL
    ");
    $stmt->execute([$category]);
    $skills = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Parse skills (assuming they're stored as comma-separated values)
    $all_skills = [];
    foreach ($skills as $skill_list) {
        $parsed_skills = array_map('trim', explode(',', $skill_list));
        $all_skills = array_merge($all_skills, $parsed_skills);
    }
    
    if (!empty($all_skills)) {
        $skill_categories[$category] = array_unique($all_skills);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Learning & Development</title>
  
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

    .learning-header {
      background: linear-gradient(135deg, var(--primary-color), #94dcf4);
      color: white;
      border-radius: 20px;
      padding: 2rem;
      margin-bottom: 2rem;
      box-shadow: 0 8px 25px rgba(75, 197, 236, 0.3);
    }

    .learning-card {
      background: white;
      border-radius: 15px;
      padding: 2rem;
      box-shadow: 0 4px 15px rgba(0,0,0,0.08);
      border: none;
      margin-bottom: 2rem;
      transition: transform 0.2s, box-shadow 0.2s;
    }

    .learning-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    }

    .course-card {
      background: white;
      border-radius: 12px;
      padding: 1.5rem;
      box-shadow: 0 2px 10px rgba(0,0,0,0.08);
      border-left: 4px solid var(--primary-color);
      margin-bottom: 1rem;
      transition: all 0.3s;
    }

    .course-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 15px rgba(0,0,0,0.15);
    }

    .progress-ring {
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

    .skill-tag {
      background: var(--primary-color);
      color: white;
      padding: 0.25rem 0.75rem;
      border-radius: 15px;
      font-size: 0.8rem;
      margin: 0.25rem;
      display: inline-block;
    }

    .level-badge {
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

    .rating-stars {
      color: #ffc107;
    }
  </style>
</head>
<body class="bg-light">
  <div class="container-fluid p-4">
    <!-- Learning Header -->
    <div class="learning-header animate-slide-up text-center">
      <h1 class="mb-2">
        <i class="fas fa-graduation-cap me-3"></i>
        Learning & Development
      </h1>
      <p class="mb-3 opacity-75">Track your learning progress and discover new opportunities</p>
      <div class="row justify-content-center">
        <div class="col-md-8">
          <h3>Welcome, <?= htmlspecialchars($employee['full_name'] ?? 'Guest User') ?></h3>
          <p class="mb-0"><?= htmlspecialchars($employee['position'] ?? 'N/A') ?> • <?= htmlspecialchars($employee['department'] ?? 'N/A') ?></p>
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

    <!-- Debug Information -->
    <?php if (empty($learning_courses) && empty($available_courses)): ?>
      <div class="alert alert-info border-0 rounded-4 animate-slide-up" role="alert">
        <h6><i class="fas fa-info-circle me-2"></i>Setup Required</h6>
        <p class="mb-2">No courses found in the database. To use the learning system:</p>
        <ol class="mb-2">
          <li>Run the <code>complete_hr_database.sql</code> file to create tables and sample data</li>
          <li>Or manually add courses to the <code>courses</code> table</li>
        </ol>
        <p class="mb-0"><strong>Current status:</strong> 
          Enrolled Courses: <?= count($learning_courses) ?> | 
          Available Courses: <?= count($available_courses) ?>
        </p>
      </div>
    <?php endif; ?>

    <!-- Learning Statistics -->
    <div class="row g-4 mb-4">
      <div class="col-xl-2 col-md-4">
        <div class="learning-card text-center animate-slide-up" style="animation-delay: 0.1s;">
          <div class="h3 text-primary mb-2"><?= $learning_stats['total_courses'] ?></div>
          <div class="text-muted">Total Courses</div>
        </div>
      </div>
      <div class="col-xl-2 col-md-4">
        <div class="learning-card text-center animate-slide-up" style="animation-delay: 0.2s;">
          <div class="h3 text-success mb-2"><?= $learning_stats['completed_courses'] ?></div>
          <div class="text-muted">Completed</div>
        </div>
      </div>
      <div class="col-xl-2 col-md-4">
        <div class="learning-card text-center animate-slide-up" style="animation-delay: 0.3s;">
          <div class="h3 text-warning mb-2"><?= $learning_stats['in_progress'] ?></div>
          <div class="text-muted">In Progress</div>
        </div>
      </div>
      <div class="col-xl-2 col-md-4">
        <div class="learning-card text-center animate-slide-up" style="animation-delay: 0.4s;">
          <div class="h3 text-info mb-2"><?= $learning_stats['total_hours'] ?>h</div>
          <div class="text-muted">Total Hours</div>
        </div>
      </div>
      <div class="col-xl-2 col-md-4">
        <div class="learning-card text-center animate-slide-up" style="animation-delay: 0.5s;">
          <div class="h3 text-primary mb-2"><?= $learning_stats['certificates_earned'] ?></div>
          <div class="text-muted">Certificates</div>
        </div>
      </div>
      <div class="col-xl-2 col-md-4">
        <div class="learning-card text-center animate-slide-up" style="animation-delay: 0.6s;">
          <div class="h3 text-success mb-2"><?= $learning_stats['average_rating'] ?></div>
          <div class="text-muted">Avg Rating</div>
        </div>
      </div>
    </div>

    <!-- Main Content -->
    <div class="row g-4">
      <!-- My Courses -->
      <div class="col-lg-8">
        <div class="learning-card animate-slide-up">
          <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="text-primary mb-0">
              <i class="fas fa-book-open me-2"></i>
              My Learning Journey
            </h4>
            <div class="btn-group" role="group">
              <button type="button" class="btn btn-outline-primary btn-sm active">All</button>
              <button type="button" class="btn btn-outline-primary btn-sm">Completed</button>
              <button type="button" class="btn btn-outline-primary btn-sm">In Progress</button>
            </div>
          </div>
          
          <?php foreach ($learning_courses as $course): ?>
            <div class="course-card">
              <div class="row align-items-center">
                <div class="col-md-8">
                  <div class="d-flex justify-content-between align-items-start mb-2">
                    <h6 class="text-primary mb-1"><?= htmlspecialchars($course['title']) ?></h6>
                    <?php if ($course['certificate']): ?>
                      <i class="fas fa-certificate text-warning" title="Certificate Earned"></i>
                    <?php endif; ?>
                  </div>
                  
                  <p class="mb-2 text-muted small"><?= htmlspecialchars($course['description']) ?></p>
                  
                  <div class="mb-2">
                    <?php 
                    $skills = !empty($course['skills']) ? explode(',', $course['skills']) : [];
                    foreach ($skills as $skill): 
                      $skill = trim($skill);
                      if (!empty($skill)):
                    ?>
                      <span class="skill-tag"><?= htmlspecialchars($skill) ?></span>
                    <?php 
                      endif;
                    endforeach; 
                    ?>
                  </div>
                  
                  <div class="row g-2 text-muted small">
                    <div class="col-auto">
                      <i class="fas fa-clock me-1"></i><?= $course['duration_hours'] ?>h
                    </div>
                    <div class="col-auto">
                      <span class="level-badge bg-<?= 
                        $course['level'] === 'Beginner' ? 'success' : 
                        ($course['level'] === 'Intermediate' ? 'warning' : 'danger') ?>">
                        <?= $course['level'] ?>
                      </span>
                    </div>
                    <div class="col-auto">
                      <div class="rating-stars">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                          <i class="fas fa-star<?= $i <= $course['rating'] ? '' : '-o' ?>"></i>
                        <?php endfor; ?>
                        <?= $course['rating'] ?>
                      </div>
                    </div>
                  </div>
                </div>
                
                <div class="col-md-4 text-end">
                  <div class="progress-ring mx-auto mb-2" style="--progress: <?= $course['progress'] ?>">
                    <?= $course['progress'] ?>%
                  </div>
                  
                  <div class="small text-muted mb-2">
                    <?php if ($course['completed']): ?>
                      Completed: <?= date('M j, Y', strtotime($course['completed'])) ?>
                    <?php else: ?>
                      Started: <?= date('M j, Y', strtotime($course['enrolled'])) ?>
                    <?php endif; ?>
                  </div>
                  
                  <div class="progress mb-2" style="height: 8px;">
                    <div class="progress-bar bg-<?= $course['status'] === 'Completed' ? 'success' : 'primary' ?>" 
                         style="width: <?= $course['progress'] ?>%"></div>
                  </div>
                  
                  <div class="btn-group btn-group-sm">
                    <?php if ($course['status'] === 'Completed'): ?>
                      <button class="btn btn-outline-success">
                        <i class="fas fa-eye"></i> Review
                      </button>
                      <?php if ($course['certificate']): ?>
                        <button class="btn btn-outline-primary">
                          <i class="fas fa-download"></i> Certificate
                        </button>
                      <?php endif; ?>
                    <?php else: ?>
                      <button class="btn btn-primary" onclick="updateProgress(<?= $course['id'] ?>, '<?= htmlspecialchars($course['title']) ?>', <?= $course['progress'] ?>)">
                        <i class="fas fa-play"></i> Continue
                      </button>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Available Courses & Skills -->
      <div class="col-lg-4">
        <!-- Available Courses -->
        <div class="learning-card animate-slide-up">
          <h5 class="text-primary mb-3">
            <i class="fas fa-plus-circle me-2"></i>
            Recommended Courses
          </h5>
          
          <?php foreach ($available_courses as $course): ?>
            <div class="course-card border-start border-warning border-3">
              <h6 class="text-warning mb-2"><?= htmlspecialchars($course['title']) ?></h6>
              <p class="mb-2 small text-muted"><?= htmlspecialchars($course['description']) ?></p>
              
              <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="level-badge bg-<?= 
                  $course['level'] === 'Beginner' ? 'success' : 
                  ($course['level'] === 'Intermediate' ? 'warning' : 'danger') ?>">
                  <?= $course['level'] ?>
                </span>
                <div class="rating-stars small">
                  <?php for ($i = 1; $i <= 5; $i++): ?>
                    <i class="fas fa-star<?= $i <= $course['rating'] ? '' : '-o' ?>"></i>
                  <?php endfor; ?>
                  <?= $course['rating'] ?>
                </div>
              </div>
              
              <div class="mb-2">
                <?php 
                $skills = !empty($course['skills']) ? explode(',', $course['skills']) : [];
                foreach ($skills as $skill): 
                  $skill = trim($skill);
                  if (!empty($skill)):
                ?>
                  <span class="skill-tag bg-warning text-dark"><?= htmlspecialchars($skill) ?></span>
                <?php 
                  endif;
                endforeach; 
                ?>
              </div>
              
              <div class="d-flex justify-content-between align-items-center">
                <small class="text-muted">
                  <i class="fas fa-clock me-1"></i><?= $course['duration_hours'] ?>h
                </small>
                <form method="POST" style="display: inline;">
                  <input type="hidden" name="action" value="enroll">
                  <input type="hidden" name="course_id" value="<?= $course['id'] ?>">
                  <button type="submit" class="btn btn-outline-warning btn-sm">
                    <i class="fas fa-plus"></i> Enroll
                  </button>
                </form>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <!-- Skills Overview -->
        <div class="learning-card animate-slide-up mt-4">
          <h5 class="text-primary mb-3">
            <i class="fas fa-cogs me-2"></i>
            Skills Acquired
          </h5>
          
          <?php foreach ($skill_categories as $category => $skills): ?>
            <div class="mb-3">
              <h6 class="text-secondary mb-2"><?= $category ?></h6>
              <div>
                <?php foreach ($skills as $skill): ?>
                  <span class="skill-tag bg-secondary"><?= htmlspecialchars($skill) ?></span>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endforeach; ?>
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

      // Filter functionality for courses
      document.querySelectorAll('.btn-group .btn').forEach(btn => {
        btn.addEventListener('click', function() {
          // Remove active class from all buttons
          document.querySelectorAll('.btn-group .btn').forEach(b => b.classList.remove('active'));
          // Add active class to clicked button
          this.classList.add('active');
          
          const filter = this.textContent.trim();
          const courseCards = document.querySelectorAll('.course-card');
          
          courseCards.forEach(card => {
            const progressText = card.querySelector('.progress-ring').textContent;
            const isCompleted = progressText === '100%';
            const isInProgress = progressText !== '100%' && progressText !== '0%';
            
            let show = false;
            if (filter === 'All') show = true;
            else if (filter === 'Completed' && isCompleted) show = true;
            else if (filter === 'In Progress' && isInProgress) show = true;
            
            card.style.display = show ? 'block' : 'none';
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

    // Progress update functionality
    function updateProgress(courseId, courseTitle, currentProgress) {
      const newProgress = prompt(`Update progress for "${courseTitle}"\nCurrent progress: ${currentProgress}%\nEnter new progress (0-100):`, currentProgress);
      
      if (newProgress !== null && newProgress !== '') {
        const progress = parseInt(newProgress);
        
        if (isNaN(progress) || progress < 0 || progress > 100) {
          alert('Please enter a valid progress value between 0 and 100.');
          return;
        }
        
        // Create and submit form
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'update_progress';
        
        const courseInput = document.createElement('input');
        courseInput.type = 'hidden';
        courseInput.name = 'course_id';
        courseInput.value = courseId;
        
        const progressInput = document.createElement('input');
        progressInput.type = 'hidden';
        progressInput.name = 'progress';
        progressInput.value = progress;
        
        form.appendChild(actionInput);
        form.appendChild(courseInput);
        form.appendChild(progressInput);
        
        document.body.appendChild(form);
        form.submit();
      }
    }
  </script>

</body>
</html>