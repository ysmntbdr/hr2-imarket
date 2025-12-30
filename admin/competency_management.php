<?php
require_once 'admin_auth.php';

try {
    $pdo = getPDO();
    
    // Handle form submissions
    if ($_POST) {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add_competency':
                    $stmt = $pdo->prepare("INSERT INTO competencies (name, description, category, created_at) VALUES (?, ?, ?, NOW())");
                    $stmt->execute([$_POST['name'], $_POST['description'], $_POST['category']]);
                    $success_message = "Competency added successfully!";
                    break;
                    
                case 'update_employee_competency':
                    $stmt = $pdo->prepare("
                        INSERT INTO employee_competencies (employee_id, competency_id, proficiency_level, last_assessed, assessed_by) 
                        VALUES (?, ?, ?, NOW(), ?) 
                        ON DUPLICATE KEY UPDATE 
                        proficiency_level = VALUES(proficiency_level), 
                        last_assessed = VALUES(last_assessed),
                        assessed_by = VALUES(assessed_by)
                    ");
                    $stmt->execute([$_POST['employee_id'], $_POST['competency_id'], $_POST['proficiency_level'], getCurrentEmployeeId()]);
                    $success_message = "Employee competency updated successfully!";
                    break;
            }
        }
    }
    
    // Get all competencies
    $stmt = $pdo->query("SELECT * FROM competencies ORDER BY category, name");
    $competencies = $stmt->fetchAll();
    
    // Get employees for dropdown
    $stmt = $pdo->query("SELECT id, full_name, department FROM employees WHERE status = 'active' ORDER BY full_name");
    $employees = $stmt->fetchAll();
    
    // Get competency assessments with employee details
    $stmt = $pdo->query("
        SELECT ec.*, e.full_name, e.department, c.name as competency_name, c.category,
               assessor.full_name as assessor_name
        FROM employee_competencies ec
        JOIN employees e ON ec.employee_id = e.id
        JOIN competencies c ON ec.competency_id = c.id
        LEFT JOIN employees assessor ON ec.assessed_by = assessor.id
        ORDER BY ec.last_assessed DESC
        LIMIT 50
    ");
    $assessments = $stmt->fetchAll();
    
} catch (Exception $e) {
    $error_message = "Database error: " . $e->getMessage();
    $competencies = [];
    $employees = [];
    $assessments = [];
}

$current_user = getCurrentEmployee();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Competency Management - HR Admin</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <!-- Admin Theme -->
    <link rel="stylesheet" href="assets/admin-theme.css">
    
    <style>
        :root {
            --primary-color: #4bc5ec;
            --primary-dark: #3ba3cc;
            --sidebar-width: 280px;
            --sidebar-collapsed: 80px;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
        }

        /* Custom Sidebar */
        .sidebar {
            width: var(--sidebar-width);
            background: linear-gradient(180deg, var(--primary-color), #94dcf4);
            color: white;
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            transition: width 0.3s ease;
            z-index: 1000;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }

        .sidebar.collapsed {
            width: var(--sidebar-collapsed);
        }

        .sidebar-brand {
            padding: 1.5rem;
            border-bottom: 1px solid rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .brand-content {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .brand-logo {
            width: 40px;
            height: 40px;
            background: white;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-color);
            font-size: 1.2rem;
        }

        .brand-text {
            font-size: 1.25rem;
            font-weight: 600;
            margin: 0;
            color: white;
            text-decoration: none;
        }

        .sidebar.collapsed .brand-text {
            display: none;
        }

        .sidebar-toggle {
            background: none;
            border: none;
            color: white;
            font-size: 1.1rem;
            cursor: pointer;
            padding: 8px;
            border-radius: 4px;
            transition: background 0.2s;
        }

        .sidebar-toggle:hover {
            background: rgba(255,255,255,0.1);
        }

        .sidebar-profile {
            padding: 1.5rem;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.2);
        }

        .profile-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            border: 3px solid white;
            margin-bottom: 12px;
        }

        .sidebar.collapsed .profile-info {
            display: none;
        }

        .sidebar-nav {
            padding: 1rem 0;
            flex: 1;
            overflow-y: auto;
        }

        .nav-item {
            margin: 0 1rem 8px;
        }

        .nav-link {
            color: rgba(255,255,255,0.9) !important;
            padding: 12px 16px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            transition: all 0.2s;
            border: none;
            background: none;
            width: 100%;
            text-align: left;
        }

        .nav-link:hover {
            background: rgba(255,255,255,0.15);
            color: white !important;
        }

        .nav-link.active {
            background: white;
            color: var(--primary-color) !important;
            font-weight: 600;
        }

        .nav-icon {
            width: 20px;
            text-align: center;
            font-size: 1rem;
        }

        .sidebar.collapsed .nav-text {
            display: none;
        }

        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            transition: margin-left 0.3s ease;
            min-height: 100vh;
            padding: 2rem;
        }

        .sidebar.collapsed + .main-content {
            margin-left: var(--sidebar-collapsed);
        }

        .page-header {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .content-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            border: none;
            margin-bottom: 2rem;
        }

        .proficiency-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .proficiency-1 { background-color: #f8d7da; color: #721c24; }
        .proficiency-2 { background-color: #fff3cd; color: #856404; }
        .proficiency-3 { background-color: #d1ecf1; color: #0c5460; }
        .proficiency-4 { background-color: #d4edda; color: #155724; }
        .proficiency-5 { background-color: #d4edda; color: #155724; }

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
                    <h1 class="mb-1">Competency Management</h1>
                    <p class="text-muted mb-0">Manage employee competencies and skill assessments</p>
                </div>
                <div class="col-md-4 text-end">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCompetencyModal">
                        <i class="fas fa-plus me-2"></i>Add New Competency
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

        <!-- Competency Assessment Form -->
        <div class="content-card">
            <h5 class="mb-3">
                <i class="fas fa-user-check text-primary me-2"></i>
                Assess Employee Competency
            </h5>
            <form method="POST">
                <input type="hidden" name="action" value="update_employee_competency">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Employee</label>
                        <select name="employee_id" class="form-select" required>
                            <option value="">Select Employee</option>
                            <?php foreach ($employees as $employee): ?>
                                <option value="<?= $employee['id'] ?>">
                                    <?= htmlspecialchars($employee['full_name']) ?> - <?= htmlspecialchars($employee['department']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Competency</label>
                        <select name="competency_id" class="form-select" required>
                            <option value="">Select Competency</option>
                            <?php foreach ($competencies as $competency): ?>
                                <option value="<?= $competency['id'] ?>">
                                    <?= htmlspecialchars($competency['name']) ?> (<?= htmlspecialchars($competency['category']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Proficiency Level</label>
                        <select name="proficiency_level" class="form-select" required>
                            <option value="">Select Level</option>
                            <option value="1">1 - Beginner</option>
                            <option value="2">2 - Basic</option>
                            <option value="3">3 - Intermediate</option>
                            <option value="4">4 - Advanced</option>
                            <option value="5">5 - Expert</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-success w-100">
                            <i class="fas fa-save me-1"></i>Assess
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Competencies List -->
        <div class="content-card">
            <h5 class="mb-3">
                <i class="fas fa-list text-primary me-2"></i>
                Available Competencies
            </h5>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Name</th>
                            <th>Category</th>
                            <th>Description</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($competencies as $competency): ?>
                            <tr>
                                <td class="fw-medium"><?= htmlspecialchars($competency['name']) ?></td>
                                <td>
                                    <span class="badge bg-secondary"><?= htmlspecialchars($competency['category']) ?></span>
                                </td>
                                <td><?= htmlspecialchars($competency['description']) ?></td>
                                <td><?= date('M d, Y', strtotime($competency['created_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Recent Assessments -->
        <div class="content-card">
            <h5 class="mb-3">
                <i class="fas fa-history text-primary me-2"></i>
                Recent Assessments
            </h5>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Employee</th>
                            <th>Department</th>
                            <th>Competency</th>
                            <th>Category</th>
                            <th>Proficiency</th>
                            <th>Assessed By</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($assessments as $assessment): ?>
                            <tr>
                                <td class="fw-medium"><?= htmlspecialchars($assessment['full_name']) ?></td>
                                <td><?= htmlspecialchars($assessment['department']) ?></td>
                                <td><?= htmlspecialchars($assessment['competency_name']) ?></td>
                                <td>
                                    <span class="badge bg-info"><?= htmlspecialchars($assessment['category']) ?></span>
                                </td>
                                <td>
                                    <span class="proficiency-badge proficiency-<?= $assessment['proficiency_level'] ?>">
                                        Level <?= $assessment['proficiency_level'] ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($assessment['assessor_name'] ?? 'System') ?></td>
                                <td><?= date('M d, Y', strtotime($assessment['assessed_date'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Add Competency Modal -->
    <div class="modal fade" id="addCompetencyModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Competency</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="add_competency">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Competency Name</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Category</label>
                            <select name="category" class="form-select" required>
                                <option value="">Select Category</option>
                                <option value="Technical">Technical</option>
                                <option value="Leadership">Leadership</option>
                                <option value="Communication">Communication</option>
                                <option value="Problem Solving">Problem Solving</option>
                                <option value="Project Management">Project Management</option>
                                <option value="Customer Service">Customer Service</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Competency</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Admin Theme JS -->
    <script src="assets/admin-theme.js"></script>
    
    <script>
        // Sidebar toggle functionality
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('mobileOverlay');
            
            if (window.innerWidth <= 768) {
                // Mobile behavior
                sidebar.classList.toggle('show');
                overlay.classList.toggle('show');
            } else {
                // Desktop behavior
                sidebar.classList.toggle('collapsed');
            }
        }

        // Close sidebar when clicking overlay
        document.getElementById('mobileOverlay')?.addEventListener('click', function() {
            toggleSidebar();
        });

        // Add active state to current page
        document.addEventListener('DOMContentLoaded', function() {
            const currentPage = window.location.pathname.split('/').pop();
            const navLinks = document.querySelectorAll('.nav-link');
            
            navLinks.forEach(link => {
                if (link.getAttribute('href') === currentPage) {
                    link.classList.add('active');
                } else {
                    link.classList.remove('active');
                }
            });
        });
    </script>
</body>
</html>
