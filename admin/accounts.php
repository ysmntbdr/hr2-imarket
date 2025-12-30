<?php
require_once 'admin_auth.php';

try {
    $pdo = getPDO();
    
    // Handle form submissions
    if ($_POST) {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add_employee':
                    $stmt = $pdo->prepare("
                        INSERT INTO employees (full_name, username, email, phone, department, 
                                             position, hire_date, salary, status, created_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())
                    ");
                    $stmt->execute([
                        $_POST['full_name'], $_POST['username'], $_POST['email'], $_POST['phone'],
                        $_POST['department'], $_POST['position'], $_POST['hire_date'], $_POST['salary']
                    ]);
                    $success_message = "Employee added successfully!";
                    break;
                    
                case 'update_status':
                    $stmt = $pdo->prepare("UPDATE employees SET status = ? WHERE id = ?");
                    $stmt->execute([$_POST['status'], $_POST['employee_id']]);
                    $success_message = "Employee status updated successfully!";
                    break;
            }
        }
    }
    
    // Get all employees
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    $department_filter = isset($_GET['department']) ? $_GET['department'] : '';
    
    $where_conditions = [];
    $params = [];
    
    if ($search) {
        $where_conditions[] = "(full_name LIKE ? OR username LIKE ? OR email LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    if ($department_filter) {
        $where_conditions[] = "department = ?";
        $params[] = $department_filter;
    }
    
    $where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    $stmt = $pdo->prepare("SELECT * FROM employees $where_clause ORDER BY full_name LIMIT 50");
    $stmt->execute($params);
    $employees = $stmt->fetchAll();
    
    // Get departments for filter
    $stmt = $pdo->query("SELECT DISTINCT department FROM employees WHERE department IS NOT NULL ORDER BY department");
    $departments = $stmt->fetchAll();
    
    // Get statistics
    $stats = [];
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM employees");
    $stats['total'] = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) as active FROM employees WHERE status = 'active'");
    $stats['active'] = $stmt->fetch()['active'] ?? 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) as inactive FROM employees WHERE status = 'inactive'");
    $stats['inactive'] = $stmt->fetch()['inactive'] ?? 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) as new_hires FROM employees WHERE hire_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
    $stats['new_hires'] = $stmt->fetch()['new_hires'] ?? 0;
    
} catch (Exception $e) {
    $error_message = "Database error: " . $e->getMessage();
    $employees = [];
    $departments = [];
    $stats = ['total' => 0, 'active' => 0, 'inactive' => 0, 'new_hires' => 0];
}

$current_user = getCurrentEmployee();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Accounts - HR Admin</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <!-- Admin Theme -->
    <link rel="stylesheet" href="assets/admin-theme.css">
    
    <style>
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status-active { background-color: #d4edda; color: #155724; }
        .status-inactive { background-color: #f8d7da; color: #721c24; }

        .employee-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
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
                    <h1 class="mb-1">Employee Accounts</h1>
                    <p class="text-muted mb-0">Manage employee accounts and information</p>
                </div>
                <div class="col-md-4 text-end">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addEmployeeModal">
                        <i class="fas fa-plus me-2"></i>Add Employee
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

        <!-- Statistics Cards -->
        <div class="row g-4 mb-4">
            <div class="col-xl-3 col-md-6">
                <div class="stat-card">
                    <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-value text-primary"><?= number_format($stats['total']) ?></div>
                    <div class="stat-label">Total Employees</div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6">
                <div class="stat-card">
                    <div class="stat-icon bg-success bg-opacity-10 text-success">
                        <i class="fas fa-user-check"></i>
                    </div>
                    <div class="stat-value text-success"><?= number_format($stats['active']) ?></div>
                    <div class="stat-label">Active Employees</div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6">
                <div class="stat-card">
                    <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                        <i class="fas fa-user-times"></i>
                    </div>
                    <div class="stat-value text-warning"><?= number_format($stats['inactive']) ?></div>
                    <div class="stat-label">Inactive Employees</div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6">
                <div class="stat-card">
                    <div class="stat-icon bg-info bg-opacity-10 text-info">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <div class="stat-value text-info"><?= number_format($stats['new_hires']) ?></div>
                    <div class="stat-label">New Hires (30 days)</div>
                </div>
            </div>
        </div>

        <!-- Search and Filter -->
        <div class="content-card">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label">Search Employees</label>
                    <input type="text" name="search" class="form-control" placeholder="Name, username, or email" value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Department</label>
                    <select name="department" class="form-select">
                        <option value="">All Departments</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?= htmlspecialchars($dept['department']) ?>" <?= $department_filter === $dept['department'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($dept['department']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search me-1"></i>Search
                    </button>
                </div>
                <div class="col-md-2">
                    <a href="accounts.php" class="btn btn-outline-secondary w-100">
                        <i class="fas fa-times me-1"></i>Clear
                    </a>
                </div>
            </form>
        </div>

        <!-- Employee List -->
        <div class="content-card">
            <h5 class="mb-3">
                <i class="fas fa-list text-primary me-2"></i>
                Employee Directory
            </h5>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Employee</th>
                            <th>Contact</th>
                            <th>Department</th>
                            <th>Position</th>
                            <th>Hire Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($employees as $employee): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="employee-avatar me-3">
                                            <?= strtoupper(substr($employee['full_name'], 0, 2)) ?>
                                        </div>
                                        <div>
                                            <div class="fw-medium"><?= htmlspecialchars($employee['full_name']) ?></div>
                                            <small class="text-muted">@<?= htmlspecialchars($employee['username']) ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div><?= htmlspecialchars($employee['email']) ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($employee['phone']) ?></small>
                                </td>
                                <td><?= htmlspecialchars($employee['department']) ?></td>
                                <td><?= htmlspecialchars($employee['position']) ?></td>
                                <td><?= date('M d, Y', strtotime($employee['hire_date'])) ?></td>
                                <td>
                                    <span class="status-badge status-<?= $employee['status'] ?>">
                                        <?= ucfirst($employee['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-primary" onclick="editEmployee(<?= $employee['id'] ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-outline-warning" onclick="toggleStatus(<?= $employee['id'] ?>, '<?= $employee['status'] ?>')">
                                            <i class="fas fa-toggle-<?= $employee['status'] === 'active' ? 'off' : 'on' ?>"></i>
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

    <!-- Add Employee Modal -->
    <div class="modal fade" id="addEmployeeModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Employee</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="add_employee">
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Full Name</label>
                                <input type="text" name="full_name" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Username</label>
                                <input type="text" name="username" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Phone</label>
                                <input type="tel" name="phone" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Department</label>
                                <input type="text" name="department" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Position</label>
                                <input type="text" name="position" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Hire Date</label>
                                <input type="date" name="hire_date" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Salary</label>
                                <input type="number" name="salary" class="form-control" step="0.01">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Employee</button>
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
        function toggleStatus(employeeId, currentStatus) {
            const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
            if (confirm(`Are you sure you want to ${newStatus === 'active' ? 'activate' : 'deactivate'} this employee?`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="employee_id" value="${employeeId}">
                    <input type="hidden" name="status" value="${newStatus}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function editEmployee(employeeId) {
            // This would open an edit modal - simplified for now
            alert('Edit functionality would be implemented here for employee ID: ' + employeeId);
        }
    </script>
</body>
</html>
