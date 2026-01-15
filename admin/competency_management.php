<?php
require_once 'admin_auth.php';

try {
    $pdo = getPDO();
    
    $success_message = '';
    $error_message = '';
    
    // Handle form submissions
    if ($_POST) {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add_competency':
                    $stmt = $pdo->prepare("INSERT INTO competencies (name, description, category, created_at) VALUES (?, ?, ?, NOW())");
                    $stmt->execute([$_POST['name'], $_POST['description'], $_POST['category']]);
                    $success_message = "Competency added successfully!";
                    break;
                    
                case 'update_competency':
                    $stmt = $pdo->prepare("UPDATE competencies SET name = ?, description = ?, category = ? WHERE id = ?");
                    $stmt->execute([$_POST['name'], $_POST['description'], $_POST['category'], $_POST['competency_id']]);
                    $success_message = "Competency updated successfully!";
                    break;
                    
                case 'delete_competency':
                    // Check if competency is used in assessments
                    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM employee_competencies WHERE competency_id = ?");
                    $stmt->execute([$_POST['competency_id']]);
                    $result = $stmt->fetch();
                    if ($result['count'] > 0) {
                        $error_message = "Cannot delete competency: It is assigned to " . $result['count'] . " employee(s). Please remove assignments first.";
                    } else {
                        $stmt = $pdo->prepare("DELETE FROM competencies WHERE id = ?");
                        $stmt->execute([$_POST['competency_id']]);
                        $success_message = "Competency deleted successfully!";
                    }
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
                    $success_message = "Employee competency assessment saved successfully!";
                    break;
                    
                case 'bulk_assign':
                    $competency_id = $_POST['competency_id'];
                    $employee_ids = $_POST['employee_ids'] ?? [];
                    $proficiency_level = $_POST['proficiency_level'] ?? 1;
                    
                    if (empty($employee_ids)) {
                        $error_message = "Please select at least one employee.";
                    } else {
                        $stmt = $pdo->prepare("
                            INSERT INTO employee_competencies (employee_id, competency_id, proficiency_level, last_assessed, assessed_by) 
                            VALUES (?, ?, ?, NOW(), ?) 
                            ON DUPLICATE KEY UPDATE 
                            proficiency_level = VALUES(proficiency_level), 
                            last_assessed = VALUES(last_assessed),
                            assessed_by = VALUES(assessed_by)
                        ");
                        foreach ($employee_ids as $emp_id) {
                            $stmt->execute([$emp_id, $competency_id, $proficiency_level, getCurrentEmployeeId()]);
                        }
                        $success_message = "Competency assigned to " . count($employee_ids) . " employee(s) successfully!";
                    }
                    break;
            }
        }
    }
    
    // Get filter/search parameters
    $search = $_GET['search'] ?? '';
    $category_filter = $_GET['category'] ?? '';
    $competency_id_view = $_GET['view'] ?? '';
    
    // Build query for competencies with filters
    $where_conditions = [];
    $params = [];
    
    if ($search) {
        $where_conditions[] = "(c.name LIKE ? OR c.description LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    if ($category_filter) {
        $where_conditions[] = "c.category = ?";
        $params[] = $category_filter;
    }
    
    $where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    // Get all competencies
    $stmt = $pdo->prepare("SELECT c.*, 
        (SELECT COUNT(*) FROM employee_competencies WHERE competency_id = c.id) as assigned_count,
        (SELECT AVG(proficiency_level) FROM employee_competencies WHERE competency_id = c.id) as avg_proficiency
        FROM competencies c 
        $where_clause 
        ORDER BY c.category, c.name");
    $stmt->execute($params);
    $competencies = $stmt->fetchAll();
    
    // Get employees for dropdown
    $stmt = $pdo->query("SELECT id, full_name, department, position FROM employees WHERE status = 'active' ORDER BY full_name");
    $employees = $stmt->fetchAll();
    
    // Get departments for filters
    $stmt = $pdo->query("SELECT DISTINCT department FROM employees WHERE status = 'active' AND department IS NOT NULL ORDER BY department");
    $departments = $stmt->fetchAll();
    
    // Get categories for filters
    $stmt = $pdo->query("SELECT DISTINCT category FROM competencies ORDER BY category");
    $categories = $stmt->fetchAll();
    
    // Get competency assessments with employee details
    $stmt = $pdo->query("
        SELECT ec.*, e.full_name, e.department, e.position, c.name as competency_name, c.category,
               assessor.full_name as assessor_name
        FROM employee_competencies ec
        JOIN employees e ON ec.employee_id = e.id
        JOIN competencies c ON ec.competency_id = c.id
        LEFT JOIN employees assessor ON ec.assessed_by = assessor.id
        ORDER BY ec.last_assessed DESC
        LIMIT 100
    ");
    $assessments = $stmt->fetchAll();
    
    // Get statistics for overview
    $stats = [];
    
    // Total competencies
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM competencies");
    $stats['total_competencies'] = $stmt->fetch()['total'] ?? 0;
    
    // Total assessments
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM employee_competencies");
    $stats['total_assessments'] = $stmt->fetch()['total'] ?? 0;
    
    // Employees assessed
    $stmt = $pdo->query("SELECT COUNT(DISTINCT employee_id) as total FROM employee_competencies");
    $stats['employees_assessed'] = $stmt->fetch()['total'] ?? 0;
    
    // Competencies by category
    $stmt = $pdo->query("
        SELECT category, COUNT(*) as count 
        FROM competencies 
        GROUP BY category 
        ORDER BY category
    ");
    $stats['by_category'] = $stmt->fetchAll();
    
    // Top competencies (most assessed)
    $stmt = $pdo->query("
        SELECT c.id, c.name, c.category, COUNT(ec.id) as assessment_count
        FROM competencies c
        LEFT JOIN employee_competencies ec ON c.id = ec.competency_id
        GROUP BY c.id, c.name, c.category
        ORDER BY assessment_count DESC
        LIMIT 5
    ");
    $stats['top_competencies'] = $stmt->fetchAll();
    
    // Competency gaps (employees with low proficiency)
    $stmt = $pdo->query("
        SELECT e.id, e.full_name, e.department, 
               COUNT(CASE WHEN ec.proficiency_level <= 2 THEN 1 END) as low_competencies,
               AVG(ec.proficiency_level) as avg_proficiency
        FROM employees e
        JOIN employee_competencies ec ON e.id = ec.employee_id
        WHERE e.status = 'active'
        GROUP BY e.id, e.full_name, e.department
        HAVING low_competencies > 0
        ORDER BY low_competencies DESC, avg_proficiency ASC
        LIMIT 10
    ");
    $stats['competency_gaps'] = $stmt->fetchAll();
    
    // Proficiency distribution
    $stmt = $pdo->query("
        SELECT proficiency_level, COUNT(*) as count
        FROM employee_competencies
        GROUP BY proficiency_level
        ORDER BY proficiency_level
    ");
    $proficiency_distribution = $stmt->fetchAll();
    
    // Get detailed competency data if viewing specific competency
    $competency_details = null;
    $competency_employees = [];
    $competency_history = [];
    
    if ($competency_id_view) {
        $stmt = $pdo->prepare("SELECT * FROM competencies WHERE id = ?");
        $stmt->execute([$competency_id_view]);
        $competency_details = $stmt->fetch();
        
        if ($competency_details) {
            // Get employees with this competency
            $stmt = $pdo->prepare("
                SELECT ec.*, e.full_name, e.department, e.position,
                       assessor.full_name as assessor_name
                FROM employee_competencies ec
                JOIN employees e ON ec.employee_id = e.id
                LEFT JOIN employees assessor ON ec.assessed_by = assessor.id
                WHERE ec.competency_id = ?
                ORDER BY ec.last_assessed DESC
            ");
            $stmt->execute([$competency_id_view]);
            $competency_employees = $stmt->fetchAll();
        }
    }
    
    // Group competencies by category for better organization
    $competencies_by_category = [];
    foreach ($competencies as $competency) {
        $category = $competency['category'];
        if (!isset($competencies_by_category[$category])) {
            $competencies_by_category[$category] = [];
        }
        $competencies_by_category[$category][] = $competency;
    }
    
} catch (Exception $e) {
    $error_message = "Database error: " . $e->getMessage();
    $competencies = [];
    $employees = [];
    $assessments = [];
    $categories = [];
    $departments = [];
    $stats = [
        'total_competencies' => 0,
        'total_assessments' => 0,
        'employees_assessed' => 0,
        'by_category' => [],
        'top_competencies' => [],
        'competency_gaps' => []
    ];
    $competencies_by_category = [];
    $proficiency_distribution = [];
    $competency_details = null;
    $competency_employees = [];
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
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
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

        /* Stats Cards */
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border-left: 4px solid var(--primary-color);
            transition: transform 0.2s, box-shadow 0.2s;
            height: 100%;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.12);
        }

        .stat-card .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }

        .stat-card.primary .stat-icon {
            background: rgba(75, 197, 236, 0.1);
            color: var(--primary-color);
        }

        .stat-card.success .stat-icon {
            background: rgba(40, 167, 69, 0.1);
            color: #28a745;
        }

        .stat-card.info .stat-icon {
            background: rgba(23, 162, 184, 0.1);
            color: #17a2b8;
        }

        .stat-card.warning .stat-icon {
            background: rgba(255, 193, 7, 0.1);
            color: #ffc107;
        }

        .stat-card.danger .stat-icon {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
        }

        .stat-card .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 0.25rem;
        }

        .stat-card .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
            margin: 0;
        }

        /* Section Headers */
        .section-header {
            border-bottom: 2px solid #e9ecef;
            padding-bottom: 1rem;
            margin-bottom: 1.5rem;
        }

        .section-header h5 {
            margin: 0;
            color: #2c3e50;
            font-weight: 600;
        }

        .section-header .section-description {
            color: #6c757d;
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }

        /* Category Group */
        .category-group {
            margin-bottom: 2rem;
        }

        .category-header {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            padding: 0.75rem 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .category-header h6 {
            margin: 0;
            font-weight: 600;
            color: #495057;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .category-badge {
            background: var(--primary-color);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        /* Search and Filter Bar */
        .filter-bar {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        /* Progress Bars */
        .progress-custom {
            height: 8px;
            border-radius: 10px;
            background-color: #e9ecef;
        }

        .competency-card-item {
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            transition: all 0.2s;
        }

        .competency-card-item:hover {
            border-color: var(--primary-color);
            box-shadow: 0 2px 8px rgba(75, 197, 236, 0.2);
        }

        /* Action Buttons */
        .btn-action {
            padding: 0.25rem 0.5rem;
            font-size: 0.85rem;
            border-radius: 4px;
        }

        /* Tooltip Enhancement */
        [data-bs-toggle="tooltip"] {
            cursor: help;
        }

        /* Chart Container */
        .chart-container {
            position: relative;
            height: 300px;
            margin: 1rem 0;
        }

        /* Analytics Cards */
        .analytics-card {
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            border: 1px solid #e9ecef;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .competency-detail-view {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
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
                    <h1 class="mb-1">
                        <i class="fas fa-clipboard-check text-primary me-2"></i>
                        Competency Management System
                    </h1>
                    <p class="text-muted mb-0">
                        <strong>Admin Portal:</strong> Manage competency framework, assess employees, track progress, and analyze competency gaps
                    </p>
                </div>
                <div class="col-md-4 text-end">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCompetencyModal">
                        <i class="fas fa-plus me-2"></i>Add Competency
                    </button>
                </div>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if (isset($success_message) && $success_message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?= $success_message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message) && $error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?= $error_message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Overview Statistics -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="stat-card primary">
                    <div class="stat-icon">
                        <i class="fas fa-book"></i>
                    </div>
                    <div class="stat-value"><?= number_format($stats['total_competencies']) ?></div>
                    <p class="stat-label">Total Competencies</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card success">
                    <div class="stat-icon">
                        <i class="fas fa-clipboard-check"></i>
                    </div>
                    <div class="stat-value"><?= number_format($stats['total_assessments']) ?></div>
                    <p class="stat-label">Total Assessments</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card info">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-value"><?= number_format($stats['employees_assessed']) ?></div>
                    <p class="stat-label">Employees Assessed</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card warning">
                    <div class="stat-icon">
                        <i class="fas fa-tags"></i>
                    </div>
                    <div class="stat-value"><?= count($stats['by_category']) ?></div>
                    <p class="stat-label">Categories</p>
                </div>
            </div>
        </div>

        <!-- Analytics Dashboard -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="analytics-card">
                    <h6 class="mb-3">
                        <i class="fas fa-chart-bar text-primary me-2"></i>
                        Proficiency Level Distribution
                    </h6>
                    <div class="chart-container">
                        <canvas id="proficiencyChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="analytics-card">
                    <h6 class="mb-3">
                        <i class="fas fa-trophy text-warning me-2"></i>
                        Top 5 Competencies (Most Assessed)
                    </h6>
                    <?php if (empty($stats['top_competencies'])): ?>
                        <p class="text-muted text-center py-4">No assessment data available</p>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($stats['top_competencies'] as $idx => $comp): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                                    <div>
                                        <span class="badge bg-primary me-2">#<?= $idx + 1 ?></span>
                                        <strong><?= htmlspecialchars($comp['name']) ?></strong>
                                        <br><small class="text-muted"><?= htmlspecialchars($comp['category']) ?></small>
                                    </div>
                                    <span class="badge bg-success"><?= $comp['assessment_count'] ?> assessments</span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Competency Gaps Alert -->
        <?php if (!empty($stats['competency_gaps'])): ?>
        <div class="content-card bg-light border-warning">
            <h6 class="text-warning mb-3">
                <i class="fas fa-exclamation-triangle me-2"></i>
                Competency Gaps Identified
            </h6>
            <p class="text-muted small mb-3">Employees with low proficiency levels (≤2) requiring attention:</p>
            <div class="row g-2">
                <?php foreach (array_slice($stats['competency_gaps'], 0, 5) as $gap): ?>
                    <div class="col-md-4">
                        <div class="p-2 bg-white rounded border">
                            <strong><?= htmlspecialchars($gap['full_name']) ?></strong>
                            <br><small class="text-muted"><?= htmlspecialchars($gap['department']) ?></small>
                            <div class="mt-1">
                                <span class="badge bg-danger"><?= $gap['low_competencies'] ?> low</span>
                                <span class="badge bg-secondary">Avg: <?= number_format($gap['avg_proficiency'], 1) ?>/5</span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Search and Filter Bar -->
        <div class="filter-bar">
            <form method="GET" class="row g-3">
                <div class="col-md-5">
                    <label class="form-label fw-medium">
                        <i class="fas fa-search me-1"></i>Search Competencies
                    </label>
                    <input type="text" name="search" class="form-control" placeholder="Search by name or description..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-medium">
                        <i class="fas fa-filter me-1"></i>Filter by Category
                    </label>
                    <select name="category" class="form-select">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= htmlspecialchars($cat['category']) ?>" <?= $category_filter === $cat['category'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['category']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">&nbsp;</label>
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter me-1"></i>Apply Filters
                        </button>
                        <a href="competency_management.php" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-times me-1"></i>Clear
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Competency Details View (if viewing specific competency) -->
        <?php if ($competency_details): ?>
        <div class="competency-detail-view">
            <div class="d-flex justify-content-between align-items-start mb-3">
                <div>
                    <h4><?= htmlspecialchars($competency_details['name']) ?></h4>
                    <span class="badge bg-primary"><?= htmlspecialchars($competency_details['category']) ?></span>
                    <p class="text-muted mt-2 mb-0"><?= htmlspecialchars($competency_details['description'] ?: 'No description provided') ?></p>
                </div>
                <div>
                    <a href="competency_management.php" class="btn btn-secondary btn-sm">
                        <i class="fas fa-times me-1"></i>Close
                    </a>
                    <button class="btn btn-primary btn-sm" onclick="editCompetency(<?= $competency_details['id'] ?>, '<?= htmlspecialchars($competency_details['name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($competency_details['description'] ?: '', ENT_QUOTES) ?>', '<?= htmlspecialchars($competency_details['category'], ENT_QUOTES) ?>')">
                        <i class="fas fa-edit me-1"></i>Edit
                    </button>
                    <button class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#bulkAssignModal" onclick="setBulkAssignCompetency(<?= $competency_details['id'] ?>)">
                        <i class="fas fa-users me-1"></i>Bulk Assign
                    </button>
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-4">
                    <div class="card bg-light">
                        <div class="card-body text-center">
                            <h5><?= count($competency_employees) ?></h5>
                            <p class="text-muted mb-0">Employees Assigned</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card bg-light">
                        <div class="card-body text-center">
                            <h5><?= $competency_details['avg_proficiency'] ? number_format($competency_details['avg_proficiency'], 1) : 'N/A' ?></h5>
                            <p class="text-muted mb-0">Average Proficiency</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card bg-light">
                        <div class="card-body text-center">
                            <h5><?= date('M d, Y', strtotime($competency_details['created_at'])) ?></h5>
                            <p class="text-muted mb-0">Created Date</p>
                        </div>
                    </div>
                </div>
            </div>

            <h6 class="mt-4 mb-3">Assigned Employees</h6>
            <?php if (empty($competency_employees)): ?>
                <p class="text-muted">No employees assigned to this competency yet.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Employee</th>
                                <th>Department</th>
                                <th>Position</th>
                                <th class="text-center">Proficiency Level</th>
                                <th>Assessed By</th>
                                <th>Last Assessed</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($competency_employees as $emp): ?>
                                <tr>
                                    <td class="fw-medium"><?= htmlspecialchars($emp['full_name']) ?></td>
                                    <td><span class="badge bg-secondary"><?= htmlspecialchars($emp['department']) ?></span></td>
                                    <td><small><?= htmlspecialchars($emp['position']) ?></small></td>
                                    <td class="text-center">
                                        <span class="proficiency-badge proficiency-<?= $emp['proficiency_level'] ?>">
                                            Level <?= $emp['proficiency_level'] ?>
                                        </span>
                                    </td>
                                    <td><small><?= htmlspecialchars($emp['assessor_name'] ?? 'System') ?></small></td>
                                    <td><small class="text-muted"><?= date('M d, Y', strtotime($emp['last_assessed'])) ?></small></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Section 1: Competency Library Management -->
        <div class="content-card">
            <div class="section-header">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h5>
                            <i class="fas fa-book text-primary me-2"></i>
                            Competency Library Management
                        </h5>
                        <p class="section-description mb-0">
                            <strong>Admin Role:</strong> Create, update, and delete competencies in the framework. 
                            Click on any competency to view detailed information, assigned employees, and assessment history.
                        </p>
                    </div>
                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addCompetencyModal">
                        <i class="fas fa-plus me-1"></i>Add New
                    </button>
                </div>
            </div>

            <?php if (empty($competencies)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                    <p class="text-muted">No competencies found. Add your first competency to get started.</p>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCompetencyModal">
                        <i class="fas fa-plus me-2"></i>Add First Competency
                    </button>
                </div>
            <?php else: ?>
                <?php foreach ($competencies_by_category as $category => $category_competencies): ?>
                    <div class="category-group">
                        <div class="category-header">
                            <h6>
                                <i class="fas fa-folder-open"></i>
                                <?= htmlspecialchars($category) ?>
                            </h6>
                            <span class="category-badge"><?= count($category_competencies) ?> competency<?= count($category_competencies) != 1 ? 'ies' : 'y' ?></span>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 25%;">Competency Name</th>
                                        <th style="width: 35%;">Description</th>
                                        <th style="width: 12%;" class="text-center">Assigned</th>
                                        <th style="width: 12%;" class="text-center">Avg. Level</th>
                                        <th style="width: 10%;">Created</th>
                                        <th style="width: 6%;" class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($category_competencies as $competency): ?>
                                        <tr>
                                            <td>
                                                <a href="?view=<?= $competency['id'] ?><?= $search ? '&search=' . urlencode($search) : '' ?><?= $category_filter ? '&category=' . urlencode($category_filter) : '' ?>" 
                                                   class="text-decoration-none fw-medium text-primary" 
                                                   data-bs-toggle="tooltip" 
                                                   title="Click to view details and assigned employees">
                                                    <?= htmlspecialchars($competency['name']) ?>
                                                    <i class="fas fa-external-link-alt fa-xs ms-1"></i>
                                                </a>
                                            </td>
                                            <td class="text-muted small"><?= htmlspecialchars(mb_substr($competency['description'] ?: 'No description', 0, 60)) ?><?= mb_strlen($competency['description'] ?: '') > 60 ? '...' : '' ?></td>
                                            <td class="text-center">
                                                <span class="badge bg-info"><?= $competency['assigned_count'] ?> emp<?= $competency['assigned_count'] != 1 ? 's' : '' ?></span>
                                            </td>
                                            <td class="text-center">
                                                <?php if ($competency['avg_proficiency']): ?>
                                                    <span class="badge bg-success"><?= number_format($competency['avg_proficiency'], 1) ?>/5</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">N/A</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><small class="text-muted"><?= date('M d, Y', strtotime($competency['created_at'])) ?></small></td>
                                            <td class="text-center">
                                                <div class="btn-group btn-group-sm" role="group">
                                                    <button class="btn btn-outline-primary btn-action" 
                                                            onclick="editCompetency(<?= $competency['id'] ?>, '<?= htmlspecialchars($competency['name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($competency['description'] ?: '', ENT_QUOTES) ?>', '<?= htmlspecialchars($competency['category'], ENT_QUOTES) ?>')"
                                                            data-bs-toggle="tooltip" title="Edit competency">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="btn btn-outline-danger btn-action" 
                                                            onclick="deleteCompetency(<?= $competency['id'] ?>, '<?= htmlspecialchars($competency['name'], ENT_QUOTES) ?>')"
                                                            data-bs-toggle="tooltip" title="Delete competency">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Section 2: Employee Competency Assessments -->
        <div class="content-card">
            <div class="section-header">
                <h5>
                    <i class="fas fa-user-check text-success me-2"></i>
                    Employee Competency Assessments
                </h5>
                <p class="section-description mb-0">
                    <strong>Admin Role:</strong> Assess and record employee competency levels. Assign competencies to individuals or teams, 
                    track progress over time, and review assessment history.
                </p>
            </div>

            <!-- Assessment Form -->
            <div class="bg-light p-4 rounded mb-4">
                <h6 class="mb-3">
                    <i class="fas fa-edit text-primary me-2"></i>
                    Record New Assessment
                </h6>
                <form method="POST">
                    <input type="hidden" name="action" value="update_employee_competency">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-medium">
                                Select Employee
                                <i class="fas fa-question-circle text-muted ms-1" data-bs-toggle="tooltip" title="Choose the employee to assess"></i>
                            </label>
                            <select name="employee_id" class="form-select" required>
                                <option value="">Choose Employee...</option>
                                <?php foreach ($employees as $employee): ?>
                                    <option value="<?= $employee['id'] ?>">
                                        <?= htmlspecialchars($employee['full_name']) ?> - <?= htmlspecialchars($employee['department']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-medium">
                                Select Competency
                                <i class="fas fa-question-circle text-muted ms-1" data-bs-toggle="tooltip" title="Select the competency to assess"></i>
                            </label>
                            <select name="competency_id" class="form-select" required>
                                <option value="">Choose Competency...</option>
                                <?php foreach ($competencies as $competency): ?>
                                    <option value="<?= $competency['id'] ?>">
                                        <?= htmlspecialchars($competency['name']) ?> (<?= htmlspecialchars($competency['category']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-medium">
                                Proficiency Level
                                <i class="fas fa-question-circle text-muted ms-1" data-bs-toggle="tooltip" title="1=Beginner, 2=Basic, 3=Intermediate, 4=Advanced, 5=Expert"></i>
                            </label>
                            <select name="proficiency_level" class="form-select" required>
                                <option value="">Level...</option>
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
                                <i class="fas fa-save me-1"></i>Save
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Recent Assessments Table -->
            <div class="section-header">
                <h6 class="mb-0">
                    <i class="fas fa-history text-info me-2"></i>
                    Recent Assessment History (Last 100)
                </h6>
            </div>
            <?php if (empty($assessments)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-clipboard-list fa-2x text-muted mb-2"></i>
                    <p class="text-muted mb-0">No assessments recorded yet. Use the form above to create the first assessment.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Employee</th>
                                <th>Department</th>
                                <th>Competency</th>
                                <th>Category</th>
                                <th class="text-center">Proficiency Level</th>
                                <th>Assessed By</th>
                                <th>Assessment Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($assessments as $assessment): ?>
                                <tr>
                                    <td class="fw-medium"><?= htmlspecialchars($assessment['full_name']) ?></td>
                                    <td><span class="badge bg-secondary"><?= htmlspecialchars($assessment['department']) ?></span></td>
                                    <td><?= htmlspecialchars($assessment['competency_name']) ?></td>
                                    <td>
                                        <span class="badge bg-info"><?= htmlspecialchars($assessment['category']) ?></span>
                                    </td>
                                    <td class="text-center">
                                        <span class="proficiency-badge proficiency-<?= $assessment['proficiency_level'] ?>">
                                            Level <?= $assessment['proficiency_level'] ?>
                                        </span>
                                    </td>
                                    <td><small><?= htmlspecialchars($assessment['assessor_name'] ?? 'System') ?></small></td>
                                    <td><small class="text-muted"><?= date('M d, Y', strtotime($assessment['last_assessed'])) ?></small></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
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
                            <label class="form-label">Competency Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" required placeholder="e.g., Project Management">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Category <span class="text-danger">*</span></label>
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
                            <textarea name="description" class="form-control" rows="3" placeholder="Describe the competency and its requirements..."></textarea>
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

    <!-- Edit Competency Modal -->
    <div class="modal fade" id="editCompetencyModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Competency</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="update_competency">
                    <input type="hidden" name="competency_id" id="edit_competency_id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Competency Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="edit_competency_name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Category <span class="text-danger">*</span></label>
                            <select name="category" id="edit_competency_category" class="form-select" required>
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
                            <textarea name="description" id="edit_competency_description" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Competency</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Competency Confirmation Modal -->
    <div class="modal fade" id="deleteCompetencyModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Confirm Delete</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="delete_competency">
                    <input type="hidden" name="competency_id" id="delete_competency_id">
                    <div class="modal-body">
                        <p>Are you sure you want to delete the competency <strong id="delete_competency_name"></strong>?</p>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            This action cannot be undone. If this competency is assigned to employees, deletion will be prevented.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete Competency</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bulk Assign Modal -->
    <div class="modal fade" id="bulkAssignModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Bulk Assign Competency</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="bulk_assign">
                    <input type="hidden" name="competency_id" id="bulk_competency_id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Select Employees <span class="text-danger">*</span></label>
                            <div class="border rounded p-2" style="max-height: 300px; overflow-y: auto;">
                                <?php foreach ($employees as $employee): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="employee_ids[]" value="<?= $employee['id'] ?>" id="emp_<?= $employee['id'] ?>">
                                        <label class="form-check-label" for="emp_<?= $employee['id'] ?>">
                                            <?= htmlspecialchars($employee['full_name']) ?> - <?= htmlspecialchars($employee['department']) ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <small class="text-muted">Select one or more employees to assign the competency to</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Initial Proficiency Level <span class="text-danger">*</span></label>
                            <select name="proficiency_level" class="form-select" required>
                                <option value="1">1 - Beginner</option>
                                <option value="2">2 - Basic</option>
                                <option value="3" selected>3 - Intermediate</option>
                                <option value="4">4 - Advanced</option>
                                <option value="5">5 - Expert</option>
                            </select>
                            <small class="text-muted">This will be the initial proficiency level for all selected employees</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Assign to Selected Employees</button>
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
        // Initialize tooltips
        document.addEventListener('DOMContentLoaded', function() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });

        // Proficiency Distribution Chart
        <?php if (!empty($proficiency_distribution)): ?>
        const proficiencyData = <?= json_encode($proficiency_distribution) ?>;
        const proficiencyLabels = ['Level 1 (Beginner)', 'Level 2 (Basic)', 'Level 3 (Intermediate)', 'Level 4 (Advanced)', 'Level 5 (Expert)'];
        const proficiencyCounts = [0, 0, 0, 0, 0];
        
        proficiencyData.forEach(item => {
            if (item.proficiency_level >= 1 && item.proficiency_level <= 5) {
                proficiencyCounts[item.proficiency_level - 1] = parseInt(item.count);
            }
        });

        const ctx = document.getElementById('proficiencyChart');
        if (ctx) {
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: proficiencyLabels,
                    datasets: [{
                        label: 'Number of Assessments',
                        data: proficiencyCounts,
                        backgroundColor: [
                            'rgba(220, 53, 69, 0.8)',
                            'rgba(255, 193, 7, 0.8)',
                            'rgba(23, 162, 184, 0.8)',
                            'rgba(40, 167, 69, 0.8)',
                            'rgba(40, 167, 69, 0.8)'
                        ],
                        borderColor: [
                            'rgb(220, 53, 69)',
                            'rgb(255, 193, 7)',
                            'rgb(23, 162, 184)',
                            'rgb(40, 167, 69)',
                            'rgb(40, 167, 69)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });
        }
        <?php endif; ?>

        // Edit Competency Function
        function editCompetency(id, name, description, category) {
            document.getElementById('edit_competency_id').value = id;
            document.getElementById('edit_competency_name').value = name;
            document.getElementById('edit_competency_description').value = description || '';
            document.getElementById('edit_competency_category').value = category;
            var editModal = new bootstrap.Modal(document.getElementById('editCompetencyModal'));
            editModal.show();
        }

        // Delete Competency Function
        function deleteCompetency(id, name) {
            document.getElementById('delete_competency_id').value = id;
            document.getElementById('delete_competency_name').textContent = name;
            var deleteModal = new bootstrap.Modal(document.getElementById('deleteCompetencyModal'));
            deleteModal.show();
        }

        // Set Bulk Assign Competency
        function setBulkAssignCompetency(competencyId) {
            document.getElementById('bulk_competency_id').value = competencyId;
            // Uncheck all checkboxes
            document.querySelectorAll('#bulkAssignModal input[type="checkbox"]').forEach(cb => cb.checked = false);
        }

        // Sidebar toggle functionality
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('mobileOverlay');
            
            if (window.innerWidth <= 768) {
                sidebar.classList.toggle('show');
                overlay.classList.toggle('show');
            } else {
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
