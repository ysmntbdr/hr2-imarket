<?php
require_once 'admin_auth.php';

// Functions are provided by admin_auth.php

try {
    $pdo = getPDO();
    
    // Handle form submissions
    if ($_POST) {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add_course':
                    $stmt = $pdo->prepare("
                        INSERT INTO courses (title, description, category, duration_hours, instructor, rating, created_at) 
                        VALUES (?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([
                        $_POST['title'], $_POST['description'], $_POST['category'], 
                        $_POST['duration_hours'], $_POST['instructor'], $_POST['rating'] ?? 0
                    ]);
                    $success_message = "Course created successfully!";
                    break;
                    
                case 'enroll_employee':
                    $stmt = $pdo->prepare("
                        INSERT INTO employee_courses (employee_id, course_id, enrollment_date, status, progress) 
                        VALUES (?, ?, NOW(), 'enrolled', 0)
                        ON DUPLICATE KEY UPDATE status = 'enrolled', enrollment_date = NOW()
                    ");
                    $stmt->execute([$_POST['employee_id'], $_POST['course_id']]);
                    $success_message = "Employee enrolled successfully!";
                    break;
                    
                case 'update_progress':
                    $progress = (int)$_POST['progress'];
                    $is_completed = $progress >= 100;
                    $stmt = $pdo->prepare("
                        UPDATE employee_courses 
                        SET progress = ?, 
                            status = CASE WHEN ? >= 100 THEN 'completed' ELSE 'in_progress' END,
                            completion_date = CASE WHEN ? >= 100 THEN CURDATE() ELSE NULL END
                        WHERE employee_id = ? AND course_id = ?
                    ");
                    $stmt->execute([
                        $progress, $progress, $progress,
                        $_POST['employee_id'], $_POST['course_id']
                    ]);
                    $success_message = "Progress updated successfully!";
                    break;
            }
        }
    }
    
    // Get all courses with enrollment statistics
    $stmt = $pdo->query("
        SELECT c.*, 
               COUNT(DISTINCT ec.id) as enrolled_count,
               COUNT(DISTINCT CASE WHEN ec.status = 'completed' THEN ec.id END) as completed_count,
               COALESCE(AVG(ec.progress), 0) as avg_progress
        FROM courses c
        LEFT JOIN employee_courses ec ON c.id = ec.course_id
        GROUP BY c.id
        ORDER BY c.created_at DESC
    ");
    $courses = $stmt->fetchAll();
    
    // Get employees for enrollment
    $stmt = $pdo->query("SELECT id, full_name, department FROM employees WHERE status = 'active' ORDER BY full_name");
    $employees = $stmt->fetchAll();
    
    // Get course enrollments with details
    $stmt = $pdo->query("
        SELECT ec.*, e.full_name, e.department, c.title as course_title, c.category,
               COALESCE(ec.progress, 0) as progress,
               COALESCE(ec.status, 'enrolled') as status
        FROM employee_courses ec
        JOIN employees e ON ec.employee_id = e.id
        JOIN courses c ON ec.course_id = c.id
        ORDER BY ec.enrollment_date DESC
        LIMIT 50
    ");
    $enrollments = $stmt->fetchAll();
    
} catch (Exception $e) {
    $error_message = "Database error: " . $e->getMessage();
    $courses = [];
    $employees = [];
    $enrollments = [];
}

$current_user = getCurrentEmployee();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Learning Management - HR Admin</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <!-- Admin Theme -->
    <link rel="stylesheet" href="assets/admin-theme.css">
    
    <style>
        .course-card {
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }

        .course-card:hover {
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }

        .progress-bar-custom {
            height: 8px;
            border-radius: 4px;
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status-enrolled { background-color: #d1ecf1; color: #0c5460; }
        .status-in_progress { background-color: #fff3cd; color: #856404; }
        .status-completed { background-color: #d4edda; color: #155724; }
        .status-active { background-color: #d4edda; color: #155724; }
        .status-inactive { background-color: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="mb-1">Learning Management</h1>
                    <p class="text-muted mb-0">Manage courses, enrollments, and learning progress</p>
                </div>
                <div class="col-md-4 text-end">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCourseModal">
                        <i class="fas fa-plus me-2"></i>Create Course
                    </button>
                </div>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?= $success_message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?= $error_message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Quick Actions -->
        <div class="row g-4 mb-4">
            <div class="col-md-6">
                <div class="content-card">
                    <h5 class="mb-3">
                        <i class="fas fa-user-plus text-primary me-2"></i>
                        Enroll Employee
                    </h5>
                    <form method="POST">
                        <input type="hidden" name="action" value="enroll_employee">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Employee</label>
                                <select name="employee_id" class="form-select" required>
                                    <option value="">Select Employee</option>
                                    <?php foreach ($employees as $employee): ?>
                                        <option value="<?= $employee['id'] ?>">
                                            <?= htmlspecialchars($employee['full_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Course</label>
                                <select name="course_id" class="form-select" required>
                                    <option value="">Select Course</option>
                                    <?php foreach ($courses as $course): ?>
                                        <option value="<?= $course['id'] ?>">
                                            <?= htmlspecialchars($course['title']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-user-plus me-1"></i>Enroll Employee
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="content-card">
                    <h5 class="mb-3">
                        <i class="fas fa-chart-line text-primary me-2"></i>
                        Update Course Progress
                    </h5>
                    <form method="POST">
                        <input type="hidden" name="action" value="update_progress">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Employee</label>
                                <select name="employee_id" class="form-select" required>
                                    <option value="">Select Employee</option>
                                    <?php foreach ($employees as $employee): ?>
                                        <option value="<?= $employee['id'] ?>">
                                            <?= htmlspecialchars($employee['full_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Course</label>
                                <select name="course_id" class="form-select" required>
                                    <option value="">Select Course</option>
                                    <?php foreach ($courses as $course): ?>
                                        <option value="<?= $course['id'] ?>">
                                            <?= htmlspecialchars($course['title']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Progress (%)</label>
                                <input type="number" name="progress" class="form-control" min="0" max="100" required>
                                <small class="text-muted">Enter progress percentage (0-100). 100% will mark as completed.</small>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-info">
                                    <i class="fas fa-chart-line me-1"></i>Update Progress
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Courses List -->
        <div class="content-card">
            <h5 class="mb-3">
                <i class="fas fa-book-open text-primary me-2"></i>
                Available Courses
            </h5>
            <?php if (empty($courses)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-book-open fa-3x text-muted mb-3"></i>
                    <p class="text-muted">No courses available. Create your first course to get started.</p>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCourseModal">
                        <i class="fas fa-plus me-2"></i>Create Course
                    </button>
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($courses as $course): ?>
                        <div class="col-lg-6 col-xl-4">
                            <div class="course-card">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h6 class="fw-bold mb-1"><?= htmlspecialchars($course['title']) ?></h6>
                                    <span class="status-badge status-<?= ($course['is_active'] ?? 1) ? 'active' : 'inactive' ?>">
                                        <?= ($course['is_active'] ?? 1) ? 'Active' : 'Inactive' ?>
                                    </span>
                                </div>
                                <p class="text-muted small mb-2"><?= htmlspecialchars($course['description'] ?? 'No description') ?></p>
                                <div class="row g-2 mb-3">
                                    <div class="col-6">
                                        <small class="text-muted">Category:</small>
                                        <div class="fw-medium"><?= htmlspecialchars($course['category'] ?? 'N/A') ?></div>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted">Duration:</small>
                                        <div class="fw-medium"><?= $course['duration_hours'] ?? 0 ?>h</div>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted">Instructor:</small>
                                        <div class="fw-medium"><?= htmlspecialchars($course['instructor'] ?? 'TBD') ?></div>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted">Enrolled:</small>
                                        <div class="fw-medium"><?= $course['enrolled_count'] ?? 0 ?></div>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted">Completed:</small>
                                        <div class="fw-medium"><?= $course['completed_count'] ?? 0 ?></div>
                                    </div>
                                </div>
                                <div class="mb-2">
                                    <small class="text-muted">Average Progress:</small>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="flex-grow-1">
                                            <div class="progress progress-bar-custom mt-1">
                                                <div class="progress-bar bg-success" style="width: <?= round($course['avg_progress'] ?? 0) ?>%"></div>
                                            </div>
                                        </div>
                                        <small class="text-muted"><?= round($course['avg_progress'] ?? 0) ?>%</small>
                                    </div>
                                </div>
                                <div class="text-muted small">
                                    <i class="fas fa-star me-1"></i>
                                    Rating: <?= number_format($course['rating'] ?? 0, 1) ?>/5.0
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Recent Enrollments -->
        <div class="content-card">
            <h5 class="mb-3">
                <i class="fas fa-history text-primary me-2"></i>
                Recent Enrollments
            </h5>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Employee</th>
                            <th>Department</th>
                            <th>Course</th>
                            <th>Category</th>
                            <th>Progress</th>
                            <th>Status</th>
                            <th>Completed</th>
                            <th>Enrolled</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($enrollments)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">
                                    <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                                    No enrollments found. Enroll employees to courses to see their progress here.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($enrollments as $enrollment): ?>
                                <tr>
                                    <td class="fw-medium"><?= htmlspecialchars($enrollment['full_name'] ?? 'Unknown') ?></td>
                                    <td><?= htmlspecialchars($enrollment['department'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($enrollment['course_title'] ?? 'Unknown Course') ?></td>
                                    <td>
                                        <span class="badge bg-info"><?= htmlspecialchars($enrollment['category'] ?? 'N/A') ?></span>
                                    </td>
                                    <td>
                                        <?php 
                                        $progress = (int)($enrollment['progress'] ?? 0);
                                        $progressColor = $progress >= 100 ? 'success' : ($progress >= 50 ? 'info' : 'warning');
                                        ?>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="progress progress-bar-custom" style="width: 100px;">
                                                <div class="progress-bar bg-<?= $progressColor ?>" style="width: <?= $progress ?>%"></div>
                                            </div>
                                            <small class="text-muted"><?= $progress ?>%</small>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?= $enrollment['status'] ?? 'enrolled' ?>">
                                            <?= ucfirst(str_replace('_', ' ', $enrollment['status'] ?? 'enrolled')) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (isset($enrollment['completion_date']) && $enrollment['completion_date']): ?>
                                            <?= date('M d, Y', strtotime($enrollment['completion_date'])) ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= isset($enrollment['enrollment_date']) ? date('M d, Y', strtotime($enrollment['enrollment_date'])) : 'N/A' ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Add Course Modal -->
    <div class="modal fade" id="addCourseModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create New Course</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="add_course">
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label">Course Title</label>
                                <input type="text" name="title" class="form-control" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Category</label>
                                <select name="category" class="form-select" required>
                                    <option value="">Select Category</option>
                                    <option value="Technical">Technical</option>
                                    <option value="Leadership">Leadership</option>
                                    <option value="Communication">Communication</option>
                                    <option value="Compliance">Compliance</option>
                                    <option value="Professional Development">Professional Development</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Description</label>
                                <textarea name="description" class="form-control" rows="3"></textarea>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Duration (Hours)</label>
                                <input type="number" name="duration_hours" class="form-control" min="1" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Instructor</label>
                                <input type="text" name="instructor" class="form-control" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Initial Rating</label>
                                <input type="number" name="rating" class="form-control" min="0" max="5" step="0.1" value="0" placeholder="0.0">
                                <small class="text-muted">Optional: Initial course rating</small>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Course</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
