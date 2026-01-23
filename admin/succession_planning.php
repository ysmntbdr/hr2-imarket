<?php
require_once 'admin_auth.php';

try {
    $pdo = getPDO();
    
    // Handle form submissions
    if ($_POST) {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add_succession_plan':
                    try {
                        $target_date = !empty($_POST['target_date']) ? $_POST['target_date'] : null;
                        
                        $stmt = $pdo->prepare("
                            INSERT INTO succession_plans (employee_id, target_position, 
                                                        readiness_level, target_date, 
                                                        created_at) 
                            VALUES (?, ?, ?, ?, NOW())
                        ");
                        $stmt->execute([
                            $_POST['employee_id'], 
                            $_POST['target_position'],
                            $_POST['readiness_level'], 
                            $target_date
                        ]);
                        $success_message = "Succession plan created successfully!";
                        // Redirect to prevent form resubmission
                        header("Location: succession_planning.php?success=1");
                        exit;
                    } catch (Exception $e) {
                        $error_message = "Failed to create succession plan: " . $e->getMessage();
                    }
                    break;
                    
                case 'update_readiness':
                    $stmt = $pdo->prepare("
                        UPDATE succession_plans 
                        SET readiness_level = ?, updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$_POST['readiness_level'], $_POST['plan_id']]);
                    $success_message = "Readiness level updated successfully!";
                    break;
                    
                case 'add_key_position':
                    // Since key_positions table doesn't exist, we'll skip this functionality
                    $success_message = "Key position functionality not available - table doesn't exist";
                    break;
            }
        }
    }
    
    // Get all succession plans
    $stmt = $pdo->query("
        SELECT sp.*, e.full_name, e.department, e.position as current_pos
        FROM succession_plans sp
        JOIN employees e ON sp.employee_id = e.id
        ORDER BY sp.created_at DESC
    ");
    $succession_plans = $stmt->fetchAll();
    
    // Get employees for dropdown
    $stmt = $pdo->query("SELECT id, full_name, department, position FROM employees WHERE status = 'active' ORDER BY full_name");
    $employees = $stmt->fetchAll();
    
    // Get unique target positions with additional details (instead of key_positions table)
    try {
        $stmt = $pdo->query("
            SELECT 
                sp.target_position,
                COUNT(*) as succession_count,
                COUNT(DISTINCT e.department) as department_count,
                GROUP_CONCAT(DISTINCT e.department SEPARATOR ', ') as departments,
                MIN(CASE 
                    WHEN sp.readiness_level = 'ready_now' THEN 1
                    WHEN sp.readiness_level = 'ready_1_2_years' THEN 2
                    WHEN sp.readiness_level = 'ready_3_5_years' THEN 3
                    ELSE 4
                END) as min_readiness_priority
            FROM succession_plans sp
            JOIN employees e ON sp.employee_id = e.id
            WHERE sp.target_position IS NOT NULL AND sp.target_position != ''
            GROUP BY sp.target_position
            ORDER BY succession_count DESC, sp.target_position
        ");
        $key_positions = $stmt->fetchAll();
    } catch (Exception $e) {
        $key_positions = [];
        error_log("Error fetching key positions: " . $e->getMessage());
    }
    
    // Get succession statistics
    $stats = [];
    $stmt = $pdo->query("SELECT COUNT(*) as total_plans FROM succession_plans");
    $stats['total_plans'] = $stmt->fetch()['total_plans'] ?? 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) as ready_now FROM succession_plans WHERE readiness_level = 'ready_now'");
    $stats['ready_now'] = $stmt->fetch()['ready_now'] ?? 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) as ready_1_2_years FROM succession_plans WHERE readiness_level = 'ready_1_2_years'");
    $stats['ready_1_2_years'] = $stmt->fetch()['ready_1_2_years'] ?? 0;
    
    $stmt = $pdo->query("SELECT COUNT(DISTINCT e.id) as resigning FROM employees e WHERE e.status IN ('inactive', 'resigned') AND e.position IS NOT NULL AND e.position != ''");
    $stats['resigning'] = $stmt->fetch()['resigning'] ?? 0;
    
    $stats['key_positions'] = count($key_positions);
    
    // Get resigning employees and their potential successors
    $resignation_recommendations = [];
    $stmt = $pdo->query("
        SELECT id, full_name, position, department, status
        FROM employees 
        WHERE status IN ('inactive', 'resigned') 
        AND position IS NOT NULL 
        AND position != ''
        ORDER BY full_name
    ");
    $resigning_employees = $stmt->fetchAll();
    
    foreach ($resigning_employees as $resigning) {
        // Find succession plans where target_position matches the resigning employee's position
        $stmt = $pdo->prepare("
            SELECT sp.*, e.full_name, e.department, e.position as current_pos
            FROM succession_plans sp
            JOIN employees e ON sp.employee_id = e.id
            WHERE sp.target_position = ? 
            AND e.status = 'active'
            ORDER BY 
                CASE sp.readiness_level
                    WHEN 'ready_now' THEN 1
                    WHEN 'ready_1_2_years' THEN 2
                    WHEN 'ready_3_5_years' THEN 3
                    ELSE 4
                END,
                sp.created_at DESC
        ");
        $stmt->execute([$resigning['position']]);
        $potential_successors = $stmt->fetchAll();
        
        if (!empty($potential_successors)) {
            $resignation_recommendations[] = [
                'resigning_employee' => $resigning,
                'successors' => $potential_successors
            ];
        }
    }
    
} catch (Exception $e) {
    $error_message = "Database error: " . $e->getMessage();
    $succession_plans = [];
    $employees = [];
    $key_positions = [];
    $stats = ['total_plans' => 0, 'ready_now' => 0, 'ready_1_2_years' => 0, 'key_positions' => 0, 'resigning' => 0];
    $resignation_recommendations = [];
    $pdo = null; // Ensure $pdo is set even on error
}

// Ensure $pdo is available for view
if (!isset($pdo)) {
    try {
        $pdo = getPDO();
    } catch (Exception $e) {
        $pdo = null;
        if (!isset($error_message)) {
            $error_message = "Database connection error: " . $e->getMessage();
        }
    }
}

$current_user = getCurrentEmployee();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Succession Planning - HR Admin</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <!-- Admin Theme -->
    <link rel="stylesheet" href="assets/admin-theme.css">
    
    <style>

        .readiness-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .readiness-ready_now { background-color: #d4edda; color: #155724; }
        .readiness-ready_1_2_years { background-color: #fff3cd; color: #856404; }
        .readiness-ready_3_5_years { background-color: #d1ecf1; color: #0c5460; }
        .readiness-not_ready { background-color: #f8d7da; color: #721c24; }

        .criticality-high { background-color: #f8d7da; color: #721c24; }
        .criticality-medium { background-color: #fff3cd; color: #856404; }
        .criticality-low { background-color: #d4edda; color: #155724; }

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

        .succession-card {
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }

        .succession-card:hover {
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transform: translateY(-2px);
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
                    <h1 class="mb-1">Succession Planning</h1>
                    <p class="text-muted mb-0">Manage succession plans and identify future leaders</p>
                </div>
                <div class="col-md-4 text-end">
                    <div class="btn-group">
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSuccessionModal">
                            <i class="fas fa-plus me-2"></i>Add Plan
                        </button>
                        <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addKeyPositionModal">
                            <i class="fas fa-key me-2"></i>Key Position
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if (isset($success_message) || (isset($_GET['success']) && $_GET['success'] == 1)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?= $success_message ?? 'Succession plan created successfully!' ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?= $error_message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="row g-4 mb-4">
            <div class="col-xl-3 col-md-6">
                <div class="stat-card">
                    <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                        <i class="fas fa-sitemap"></i>
                    </div>
                    <div class="stat-value text-primary"><?= number_format($stats['total_plans']) ?></div>
                    <div class="stat-label">Total Succession Plans</div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6">
                <div class="stat-card">
                    <div class="stat-icon bg-success bg-opacity-10 text-success">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-value text-success"><?= number_format($stats['ready_now']) ?></div>
                    <div class="stat-label">Ready Now</div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6">
                <div class="stat-card">
                    <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-value text-warning"><?= number_format($stats['ready_1_2_years']) ?></div>
                    <div class="stat-label">Ready in 1-2 Years</div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6">
                <div class="stat-card">
                    <div class="stat-icon bg-info bg-opacity-10 text-info">
                        <i class="fas fa-key"></i>
                    </div>
                    <div class="stat-value text-info"><?= number_format($stats['key_positions']) ?></div>
                    <div class="stat-label">Key Positions</div>
                </div>
            </div>
        </div>

        <!-- Resignation Recommendations -->
        <?php if (!empty($resignation_recommendations)): ?>
        <div class="content-card border-warning border-2">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">
                    <i class="fas fa-exclamation-triangle text-warning me-2"></i>
                    Succession Recommendations for Resigning Employees
                </h5>
                <span class="badge bg-warning text-dark"><?= count($resignation_recommendations) ?> Position<?= count($resignation_recommendations) > 1 ? 's' : '' ?> at Risk</span>
            </div>
            
            <?php foreach ($resignation_recommendations as $recommendation): ?>
                <div class="alert alert-warning alert-dismissible fade show mb-3" role="alert">
                    <div class="d-flex align-items-start">
                        <i class="fas fa-user-times fa-2x me-3 mt-1"></i>
                        <div class="flex-grow-1">
                            <h6 class="alert-heading mb-2">
                                <strong><?= htmlspecialchars($recommendation['resigning_employee']['full_name']) ?></strong> 
                                is resigning from 
                                <strong><?= htmlspecialchars($recommendation['resigning_employee']['position']) ?></strong>
                            </h6>
                            <p class="mb-2 text-muted">
                                <i class="fas fa-building me-1"></i>
                                Department: <?= htmlspecialchars($recommendation['resigning_employee']['department']) ?>
                            </p>
                            <hr>
                            <p class="mb-2 fw-bold">Recommended Successors:</p>
                            <div class="row g-3">
                                <?php foreach ($recommendation['successors'] as $successor): ?>
                                    <div class="col-md-6 col-lg-4">
                                        <div class="card border-success">
                                            <div class="card-body p-3">
                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                    <h6 class="card-title mb-0 fw-bold">
                                                        <?= htmlspecialchars($successor['full_name']) ?>
                                                    </h6>
                                                    <span class="readiness-badge readiness-<?= $successor['readiness_level'] ?>">
                                                        <?= ucfirst(str_replace('_', ' ', $successor['readiness_level'])) ?>
                                                    </span>
                                                </div>
                                                <p class="card-text small mb-1">
                                                    <strong>Current:</strong> <?= htmlspecialchars($successor['current_pos']) ?>
                                                </p>
                                                <p class="card-text small mb-1">
                                                    <strong>Target:</strong> 
                                                    <span class="text-primary"><?= htmlspecialchars($successor['target_position']) ?></span>
                                </p>
                                                <?php if (!empty($successor['target_date'])): ?>
                                                    <p class="card-text small mb-0 mt-2">
                                                        <i class="fas fa-calendar-alt me-1"></i>
                                                        Target Date: <?= date('M d, Y', strtotime($successor['target_date'])) ?>
                                                    </p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Key Positions -->
        <div class="content-card">
            <h5 class="mb-3">
                <i class="fas fa-key text-primary me-2"></i>
                Key Positions
            </h5>
            <?php if (empty($key_positions)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    No key positions identified yet. Create succession plans to populate this list.
                </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Position</th>
                            <th>Department</th>
                            <th>Criticality</th>
                            <th>Required Skills</th>
                            <th>Succession Depth</th>
                            <th>Current Plans</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($key_positions as $position): ?>
                            <?php
                            // Determine criticality based on succession count and readiness
                            $criticality = 'low';
                            if ($position['succession_count'] < 2) {
                                $criticality = 'high';
                            } elseif ($position['succession_count'] < 3) {
                                $criticality = 'medium';
                            }
                            
                            // Get required skills from employees in this position (if any exist)
                            $position_info = null;
                            $succession_depth = 0;
                            if (isset($pdo) && $pdo !== null) {
                                try {
                                    $stmt = $pdo->prepare("
                                        SELECT DISTINCT e.position, e.department
                                        FROM employees e
                                        WHERE e.position = ? AND e.status = 'active'
                                        LIMIT 1
                                    ");
                                    $stmt->execute([$position['target_position']]);
                                    $position_info = $stmt->fetch();
                                    
                                    // Calculate succession depth (number of ready successors)
                                    $stmt = $pdo->prepare("
                                        SELECT COUNT(*) as depth
                                        FROM succession_plans
                                        WHERE target_position = ? 
                                        AND readiness_level IN ('ready_now', 'ready_1_2_years')
                                    ");
                                    $stmt->execute([$position['target_position']]);
                                    $depth_result = $stmt->fetch();
                                    $succession_depth = $depth_result['depth'] ?? 0;
                                } catch (Exception $e) {
                                    error_log("Error fetching position details: " . $e->getMessage());
                                }
                            }
                            ?>
                            <tr>
                                <td class="fw-medium"><?= htmlspecialchars($position['target_position']) ?></td>
                                <td>
                                    <?php if (!empty($position['departments'])): ?>
                                        <?= htmlspecialchars($position['departments']) ?>
                                    <?php elseif ($position_info && !empty($position_info['department'])): ?>
                                        <?= htmlspecialchars($position_info['department']) ?>
                                    <?php else: ?>
                                        <span class="text-muted">Multiple</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="readiness-badge criticality-<?= $criticality ?>">
                                        <?= ucfirst($criticality) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($position_info): ?>
                                        <small class="text-muted">See position requirements</small>
                                    <?php else: ?>
                                        <small class="text-muted">To be defined</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?= $succession_depth >= 2 ? 'success' : ($succession_depth >= 1 ? 'warning' : 'danger') ?>">
                                        <?= $succession_depth ?> successor<?= $succession_depth != 1 ? 's' : '' ?>
                                    </span>
                                </td>
                                <td><?= $position['succession_count'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <!-- Succession Plans -->
        <div class="content-card">
            <h5 class="mb-3">
                <i class="fas fa-users text-primary me-2"></i>
                Succession Plans
            </h5>
            <?php if (empty($succession_plans)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    No succession plans found. Click "Add Plan" to create your first succession plan.
                </div>
            <?php else: ?>
            <div class="row">
                <?php foreach ($succession_plans as $plan): ?>
                    <div class="col-lg-6 col-xl-4">
                        <div class="succession-card">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h6 class="fw-bold mb-1"><?= htmlspecialchars($plan['full_name']) ?></h6>
                                <span class="readiness-badge readiness-<?= $plan['readiness_level'] ?>">
                                    <?= ucfirst(str_replace('_', ' ', $plan['readiness_level'])) ?>
                                </span>
                            </div>
                            <div class="row g-2 mb-3">
                                <div class="col-12">
                                    <small class="text-muted">Current Position:</small>
                                    <div class="fw-medium"><?= htmlspecialchars($plan['current_pos'] ?? 'N/A') ?></div>
                                </div>
                                <div class="col-12">
                                    <small class="text-muted">Target Position:</small>
                                    <div class="fw-medium text-primary"><?= htmlspecialchars($plan['target_position'] ?? 'N/A') ?></div>
                                </div>
                                <div class="col-12">
                                    <small class="text-muted">Department:</small>
                                    <div class="fw-medium"><?= htmlspecialchars($plan['department'] ?? 'N/A') ?></div>
                                </div>
                                <div class="col-12">
                                    <small class="text-muted">Target Date:</small>
                                    <div class="fw-medium">
                                        <?php if (!empty($plan['target_date'])): ?>
                                            <?= date('M d, Y', strtotime($plan['target_date'])) ?>
                                        <?php else: ?>
                                            <span class="text-muted">Not set</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="text-muted small">
                                <i class="fas fa-calendar me-1"></i>
                                Created: <?= date('M d, Y', strtotime($plan['created_at'])) ?>
                                <?php if (!empty($plan['updated_at']) && $plan['updated_at'] != $plan['created_at']): ?>
                                    <br>
                                    <i class="fas fa-edit me-1"></i>
                                    Updated: <?= date('M d, Y', strtotime($plan['updated_at'])) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add Succession Plan Modal -->
    <div class="modal fade" id="addSuccessionModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create Succession Plan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="add_succession_plan">
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Employee</label>
                                <select name="employee_id" class="form-select" required>
                                    <option value="">Select Employee</option>
                                    <?php foreach ($employees as $employee): ?>
                                        <option value="<?= $employee['id'] ?>">
                                            <?= htmlspecialchars($employee['full_name']) ?> - <?= htmlspecialchars($employee['position']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Target Position</label>
                                <input type="text" name="target_position" class="form-control" placeholder="e.g., Senior Manager" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Readiness Level</label>
                                <select name="readiness_level" class="form-select" required>
                                    <option value="">Select Readiness</option>
                                    <option value="ready_now">Ready Now</option>
                                    <option value="ready_1_2_years">Ready in 1-2 Years</option>
                                    <option value="ready_3_5_years">Ready in 3-5 Years</option>
                                    <option value="not_ready">Not Ready</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Target Date</label>
                                <input type="date" name="target_date" class="form-control">
                                <small class="form-text text-muted">Expected promotion/transition date</small>
                            </div>
                        
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Plan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Key Position Modal -->
    <div class="modal fade" id="addKeyPositionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Key Position</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="add_key_position">
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label">Position Title</label>
                                <input type="text" name="position_title" class="form-control" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Department</label>
                                <input type="text" name="department" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Criticality Level</label>
                                <select name="criticality_level" class="form-select" required>
                                    <option value="">Select Level</option>
                                    <option value="high">High</option>
                                    <option value="medium">Medium</option>
                                    <option value="low">Low</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Succession Depth</label>
                                <input type="number" name="succession_depth" class="form-control" min="1" max="5" value="2" required>
                                <small class="form-text text-muted">Number of successors needed</small>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Required Skills</label>
                                <textarea name="required_skills" class="form-control" rows="3" placeholder="List key skills and competencies required for this position..."></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Position</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
