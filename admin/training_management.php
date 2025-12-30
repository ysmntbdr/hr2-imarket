<?php
require_once 'admin_auth.php';

// Functions are provided by admin_auth.php

try {
    $pdo = getPDO();
    
    // Handle form submissions
    if ($_POST) {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add_training':
                    $stmt = $pdo->prepare("
                        INSERT INTO trainings (title, description, trainer, location, start_date, end_date, 
                                             max_participants, training_type, status, created_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'scheduled', NOW())
                    ");
                    $stmt->execute([
                        $_POST['title'], $_POST['description'], $_POST['trainer'], $_POST['location'],
                        $_POST['start_date'], $_POST['end_date'], $_POST['max_participants'], $_POST['training_type']
                    ]);
                    $success_message = "Training session created successfully!";
                    break;
                    
                case 'register_employee':
                    $stmt = $pdo->prepare("
                        INSERT INTO employee_trainings (employee_id, training_id, enrollment_date, progress) 
                        VALUES (?, ?, NOW(), 0)
                        ON DUPLICATE KEY UPDATE enrollment_date = NOW()
                    ");
                    $stmt->execute([$_POST['employee_id'], $_POST['training_id']]);
                    $success_message = "Employee registered successfully!";
                    break;
                    
                case 'update_progress':
                    $stmt = $pdo->prepare("
                        UPDATE employee_trainings 
                        SET progress = ?, completion_date = ?, certificate_earned = ?, rating = ?
                        WHERE employee_id = ? AND training_id = ?
                    ");
                    $stmt->execute([
                        $_POST['progress'], 
                        $_POST['progress'] >= 100 ? date('Y-m-d') : null,
                        $_POST['progress'] >= 100 ? 1 : 0,
                        $_POST['rating'] ?? null,
                        $_POST['employee_id'], 
                        $_POST['training_id']
                    ]);
                    $success_message = "Training progress updated successfully!";
                    break;
            }
        }
    }
    
    // Get all trainings
    $stmt = $pdo->query("
        SELECT t.*, 
               COUNT(et.id) as registered_count,
               COUNT(CASE WHEN et.completion_date IS NOT NULL THEN 1 END) as completed_count
        FROM trainings t
        LEFT JOIN employee_trainings et ON t.id = et.training_id
        GROUP BY t.id
        ORDER BY t.start_date DESC
    ");
    $trainings = $stmt->fetchAll();
    
    // Get employees for registration
    $stmt = $pdo->query("SELECT id, full_name, department FROM employees WHERE status = 'active' ORDER BY full_name");
    $employees = $stmt->fetchAll();
    
    // Get training registrations with details
    $stmt = $pdo->query("
        SELECT et.*, e.full_name, e.department, t.title as training_title, t.start_date, t.end_date,
               COALESCE(et.status, 'registered') as status,
               et.completion_status,
               et.progress,
               et.rating,
               COALESCE(et.enrollment_date, et.created_at) as enrollment_date
        FROM employee_trainings et
        JOIN employees e ON et.employee_id = e.id
        JOIN trainings t ON et.training_id = t.id
        ORDER BY COALESCE(et.enrollment_date, et.created_at) DESC
        LIMIT 50
    ");
    $registrations = $stmt->fetchAll();
    
} catch (Exception $e) {
    $error_message = "Database error: " . $e->getMessage();
    $trainings = [];
    $employees = [];
    $registrations = [];
}

$current_user = getCurrentEmployee();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Training Management - HR Admin</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <!-- Admin Theme -->
    <link rel="stylesheet" href="assets/admin-theme.css">
    
    <style>

        .training-card {
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }

        .training-card:hover {
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status-scheduled { background-color: #d1ecf1; color: #0c5460; }
        .status-ongoing { background-color: #fff3cd; color: #856404; }
        .status-completed { background-color: #d4edda; color: #155724; }
        .status-cancelled { background-color: #f8d7da; color: #721c24; }
        .status-registered { background-color: #d1ecf1; color: #0c5460; }
        .status-passed { background-color: #d4edda; color: #155724; }
        .status-failed { background-color: #f8d7da; color: #721c24; }
        
        .progress-bar-custom {
            height: 8px;
            border-radius: 4px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
            color: white;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
        }
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
                    <h1 class="mb-1">Training Management</h1>
                    <p class="text-muted mb-0">Schedule training sessions and manage employee registrations</p>
                </div>
                <div class="col-md-4 text-end">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTrainingModal">
                        <i class="fas fa-plus me-2"></i>Schedule Training
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
                        Register Employee
                    </h5>
                    <form method="POST">
                        <input type="hidden" name="action" value="register_employee">
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
                                <label class="form-label">Training</label>
                                <select name="training_id" class="form-select" required>
                                    <option value="">Select Training</option>
                                    <?php foreach ($trainings as $training): ?>
                                        <option value="<?= $training['id'] ?>">
                                            <?= htmlspecialchars($training['title']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-user-plus me-1"></i>Register Employee
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
                        Update Training Progress
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
                                <label class="form-label">Training</label>
                                <select name="training_id" class="form-select" required>
                                    <option value="">Select Training</option>
                                    <?php foreach ($trainings as $training): ?>
                                        <option value="<?= $training['id'] ?>">
                                            <?= htmlspecialchars($training['title']) ?>
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
                                <label class="form-label">Rating (Optional)</label>
                                <select name="rating" class="form-select">
                                    <option value="">No Rating</option>
                                    <option value="1">1 - Poor</option>
                                    <option value="2">2 - Fair</option>
                                    <option value="3">3 - Good</option>
                                    <option value="4">4 - Very Good</option>
                                    <option value="5">5 - Excellent</option>
                                </select>
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

        <!-- Training Sessions -->
        <div class="content-card">
            <h5 class="mb-3">
                <i class="fas fa-calendar-alt text-primary me-2"></i>
                Training Sessions
            </h5>
            <div class="row">
                <?php foreach ($trainings as $training): ?>
                    <div class="col-lg-6 col-xl-4">
                        <div class="training-card">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h6 class="fw-bold mb-1"><?= htmlspecialchars($training['title']) ?></h6>
                                <span class="status-badge status-<?= $training['status'] ?? 'scheduled' ?>">
                                    <?= ucfirst($training['status'] ?? 'scheduled') ?>
                                </span>
                            </div>
                            <p class="text-muted small mb-2"><?= htmlspecialchars($training['description']) ?></p>
                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <small class="text-muted">Trainer:</small>
                                    <div class="fw-medium"><?= htmlspecialchars($training['trainer'] ?? 'TBD') ?></div>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted">Type:</small>
                                    <div class="fw-medium"><?= htmlspecialchars($training['training_type'] ?? 'General') ?></div>
                                </div>
                                <div class="col-12">
                                    <small class="text-muted">Location:</small>
                                    <div class="fw-medium"><?= htmlspecialchars($training['location'] ?? 'TBD') ?></div>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted">Registered:</small>
                                    <div class="fw-medium"><?= $training['registered_count'] ?? 0 ?>/<?= $training['max_participants'] ?? 0 ?></div>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted">Completed:</small>
                                    <div class="fw-medium"><?= $training['completed_count'] ?? 0 ?></div>
                                </div>
                            </div>
                            <div class="text-muted small">
                                <i class="fas fa-calendar me-1"></i>
                                <?= isset($training['start_date']) ? date('M d', strtotime($training['start_date'])) : 'TBD' ?> - <?= isset($training['end_date']) ? date('M d, Y', strtotime($training['end_date'])) : 'TBD' ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Recent Registrations -->
        <div class="content-card">
            <h5 class="mb-3">
                <i class="fas fa-history text-primary me-2"></i>
                Recent Registrations
            </h5>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Employee</th>
                            <th>Department</th>
                            <th>Training</th>
                            <th>Training Date</th>
                            <th>Progress</th>
                            <th>Status</th>
                            <th>Rating</th>
                            <th>Registered</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($registrations as $registration): ?>
                            <tr>
                                <td class="fw-medium"><?= htmlspecialchars($registration['full_name'] ?? 'Unknown') ?></td>
                                <td><?= htmlspecialchars($registration['department'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($registration['training_title'] ?? 'Unknown Training') ?></td>
                                <td><?= isset($registration['start_date']) ? date('M d, Y', strtotime($registration['start_date'])) : 'TBD' ?></td>
                                <td>
                                    <?php 
                                    $progress = $registration['progress'] ?? 0;
                                    $progressColor = $progress >= 100 ? 'success' : ($progress >= 50 ? 'info' : 'warning');
                                    ?>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="flex-grow-1">
                                            <div class="progress progress-bar-custom">
                                                <div class="progress-bar bg-<?= $progressColor ?>" role="progressbar" 
                                                     style="width: <?= $progress ?>%" 
                                                     aria-valuenow="<?= $progress ?>" 
                                                     aria-valuemin="0" 
                                                     aria-valuemax="100">
                                                </div>
                                            </div>
                                        </div>
                                        <small class="text-muted"><?= $progress ?>%</small>
                                    </div>
                                </td>
                                <td>
                                    <?php if (isset($registration['completion_status']) && $registration['completion_status']): ?>
                                        <span class="status-badge status-<?= $registration['completion_status'] ?>">
                                            <?= ucfirst($registration['completion_status']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="status-badge status-<?= $registration['status'] ?? 'registered' ?>">
                                            <?= ucfirst($registration['status'] ?? 'registered') ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (isset($registration['rating']) && $registration['rating']): ?>
                                        <div class="d-flex align-items-center">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="fas fa-star <?= $i <= $registration['rating'] ? 'text-warning' : 'text-muted' ?>"></i>
                                            <?php endfor; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= date('M d, Y', strtotime($registration['enrollment_date'] ?? $registration['registration_date'] ?? 'now')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Add Training Modal -->
    <div class="modal fade" id="addTrainingModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Schedule New Training</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="add_training">
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label">Training Title</label>
                                <input type="text" name="title" class="form-control" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Training Type</label>
                                <select name="training_type" class="form-select" required>
                                    <option value="">Select Type</option>
                                    <option value="Workshop">Workshop</option>
                                    <option value="Seminar">Seminar</option>
                                    <option value="Online">Online</option>
                                    <option value="Certification">Certification</option>
                                    <option value="Compliance">Compliance</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Description</label>
                                <textarea name="description" class="form-control" rows="3"></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Trainer</label>
                                <input type="text" name="trainer" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Location</label>
                                <input type="text" name="location" class="form-control" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Max Participants</label>
                                <input type="number" name="max_participants" class="form-control" min="1" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Start Date</label>
                                <input type="datetime-local" name="start_date" class="form-control" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">End Date</label>
                                <input type="datetime-local" name="end_date" class="form-control" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Schedule Training</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
