<?php
require_once 'admin_auth.php';

try {
    $pdo = getPDO();
    
    // Get ESS statistics and data
    $stats = [];
    
    // Pending leaves (using leaves table)
    $stmt = $pdo->query("SELECT COUNT(*) as pending_leaves FROM leaves WHERE status = 'pending'");
    $stats['pending_leaves'] = $stmt->fetch()['pending_leaves'] ?? 0;
    
    // Pending claims
    $stmt = $pdo->query("SELECT COUNT(*) as pending_claims FROM claims WHERE status = 'pending'");
    $stats['pending_claims'] = $stmt->fetch()['pending_claims'] ?? 0;
    
    // Active employees
    $stmt = $pdo->query("SELECT COUNT(*) as active_employees FROM employees WHERE status = 'active'");
    $stats['active_employees'] = $stmt->fetch()['active_employees'] ?? 0;
    
    // Recent timesheet submissions (handle different date column names)
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as recent_timesheets FROM timesheets WHERE DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
        $stats['recent_timesheets'] = $stmt->fetch()['recent_timesheets'] ?? 0;
    } catch (Exception $e) {
        // Try with different date column
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as recent_timesheets FROM timesheets WHERE DATE(date) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
            $stats['recent_timesheets'] = $stmt->fetch()['recent_timesheets'] ?? 0;
        } catch (Exception $e2) {
            $stats['recent_timesheets'] = 0;
        }
    }
    
    // Recent leaves (handle different date column names)
    try {
        $stmt = $pdo->query("
            SELECT l.*, e.full_name, e.department
            FROM leaves l
            JOIN employees e ON l.employee_id = e.id
            ORDER BY l.created_at DESC
            LIMIT 20
        ");
        $recent_leaves = $stmt->fetchAll();
    } catch (Exception $e) {
        // Try with different date column
        try {
            $stmt = $pdo->query("
                SELECT l.*, e.full_name, e.department
                FROM leaves l
                JOIN employees e ON l.employee_id = e.id
                ORDER BY l.start_date DESC
                LIMIT 20
            ");
            $recent_leaves = $stmt->fetchAll();
        } catch (Exception $e2) {
            $recent_leaves = [];
        }
    }
    
    // Recent claims
    try {
        $stmt = $pdo->query("
            SELECT c.*, e.full_name, e.department
            FROM claims c
            JOIN employees e ON c.employee_id = e.id
            ORDER BY c.claim_date DESC
            LIMIT 20
        ");
        $recent_claims = $stmt->fetchAll();
    } catch (Exception $e) {
        // Try with different date column
        try {
            $stmt = $pdo->query("
                SELECT c.*, e.full_name, e.department
                FROM claims c
                JOIN employees e ON c.employee_id = e.id
                ORDER BY c.created_at DESC
                LIMIT 20
            ");
            $recent_claims = $stmt->fetchAll();
        } catch (Exception $e2) {
            $recent_claims = [];
        }
    }
    
    // Employee activity summary (with error handling for different column names)
    try {
        $stmt = $pdo->query("
            SELECT e.id, e.full_name, e.department, e.position,
                   COUNT(DISTINCT l.id) as leaves,
                   COUNT(DISTINCT c.id) as claims,
                   COUNT(DISTINCT t.id) as timesheets
            FROM employees e
            LEFT JOIN leaves l ON e.id = l.employee_id AND l.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            LEFT JOIN claims c ON e.id = c.employee_id AND c.claim_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            LEFT JOIN timesheets t ON e.id = t.employee_id AND t.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            WHERE e.status = 'active'
            GROUP BY e.id
            ORDER BY (leaves + claims + timesheets) DESC
            LIMIT 15
        ");
        $employee_activity = $stmt->fetchAll();
    } catch (Exception $e) {
        // Simplified query without date filters if columns don't exist
        try {
            $stmt = $pdo->query("
                SELECT e.id, e.full_name, e.department, e.position,
                       COUNT(DISTINCT l.id) as leaves,
                       COUNT(DISTINCT c.id) as claims,
                       0 as timesheets
                FROM employees e
                LEFT JOIN leaves l ON e.id = l.employee_id
                LEFT JOIN claims c ON e.id = c.employee_id
                WHERE e.status = 'active'
                GROUP BY e.id
                ORDER BY (leaves + claims) DESC
                LIMIT 15
            ");
            $employee_activity = $stmt->fetchAll();
        } catch (Exception $e2) {
            $employee_activity = [];
        }
    }
    
} catch (Exception $e) {
    error_log("ESS Dashboard Error: " . $e->getMessage());
    $stats = [
        'pending_leaves' => 0,
        'pending_claims' => 0,
        'active_employees' => 0,
        'recent_timesheets' => 0
    ];
}

// Handle quick actions
$action_content = '';
$show_action_modal = false;

if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $show_action_modal = true;
    
    try {
        switch ($action) {
            case 'leaves':
                // First, let's check if we have any leaves at all
                $stmt = $pdo->query("SELECT COUNT(*) as total FROM leaves");
                $total_leaves = $stmt->fetch()['total'] ?? 0;
                
                // Then get pending leaves with employee data
                $stmt = $pdo->prepare("
                    SELECT l.*, 
                           l.employee_id as leave_employee_id,
                           e.id as actual_employee_id,
                           COALESCE(e.full_name, 'Unknown Employee') as full_name,
                           COALESCE(e.department, 'N/A') as department,
                           COALESCE(e.email, 'No email') as employee_email
                    FROM leaves l 
                    LEFT JOIN employees e ON l.employee_id = e.id 
                    WHERE l.status = 'pending' 
                    ORDER BY l.id DESC 
                    LIMIT 10
                ");
                $stmt->execute();
                $leaves = $stmt->fetchAll();
                
                $action_content = '<h5><i class="fas fa-calendar-check me-2"></i>Pending Leave Requests</h5>';
                $action_content .= '<p class="small text-muted">Total leaves in system: ' . $total_leaves . ' | Pending: ' . count($leaves) . '</p>';
                
                if (empty($leaves)) {
                    $action_content .= '<div class="alert alert-info">';
                    $action_content .= '<i class="fas fa-info-circle me-2"></i>';
                    if ($total_leaves == 0) {
                        $action_content .= 'No leave requests found in the system.';
                    } else {
                        $action_content .= 'No pending leave requests at this time.';
                    }
                    $action_content .= '</div>';
                } else {
                    $action_content .= '<div class="table-responsive"><table class="table table-hover">';
                    $action_content .= '<thead><tr><th>Employee</th><th>Department</th><th>Leave Type</th><th>Dates</th><th>Status</th><th>Debug Info</th><th>Actions</th></tr></thead><tbody>';
                    foreach ($leaves as $leave) {
                        $action_content .= '<tr>';
                        $action_content .= '<td>' . htmlspecialchars($leave['full_name']) . '<br><small class="text-muted">' . htmlspecialchars($leave['employee_email']) . '</small></td>';
                        $action_content .= '<td>' . htmlspecialchars($leave['department']) . '</td>';
                        $action_content .= '<td>' . htmlspecialchars($leave['leave_type'] ?? 'N/A') . '</td>';
                        $action_content .= '<td>' . htmlspecialchars($leave['start_date'] ?? 'N/A') . ' - ' . htmlspecialchars($leave['end_date'] ?? 'N/A') . '</td>';
                        $action_content .= '<td><span class="badge bg-warning">Pending</span></td>';
                        $action_content .= '<td><small>Leave ID: ' . $leave['leave_employee_id'] . '<br>Employee ID: ' . ($leave['actual_employee_id'] ?? 'NULL') . '</small></td>';
                        $action_content .= '<td>';
                        $action_content .= '<button class="btn btn-sm btn-success me-1" onclick="updateLeaveStatus(' . $leave['id'] . ', \'approved\')">Approve</button>';
                        $action_content .= '<button class="btn btn-sm btn-danger" onclick="updateLeaveStatus(' . $leave['id'] . ', \'rejected\')">Reject</button>';
                        $action_content .= '</td>';
                        $action_content .= '</tr>';
                    }
                    $action_content .= '</tbody></table></div>';
                }
                break;
                
            case 'claims':
                $stmt = $pdo->query("
                    SELECT c.*, e.full_name, e.department 
                    FROM claims c 
                    JOIN employees e ON c.employee_id = e.id 
                    WHERE c.status = 'pending' 
                    ORDER BY c.id DESC 
                    LIMIT 10
                ");
                $claims = $stmt->fetchAll();
                
                $action_content = '<h5><i class="fas fa-file-invoice-dollar me-2"></i>Pending Expense Claims</h5>';
                if (empty($claims)) {
                    $action_content .= '<p class="text-muted">No pending expense claims.</p>';
                } else {
                    $action_content .= '<div class="table-responsive"><table class="table table-hover">';
                    $action_content .= '<thead><tr><th>Employee</th><th>Department</th><th>Amount</th><th>Description</th><th>Date</th><th>Actions</th></tr></thead><tbody>';
                    foreach ($claims as $claim) {
                        $action_content .= '<tr>';
                        $action_content .= '<td>' . htmlspecialchars($claim['full_name']) . '</td>';
                        $action_content .= '<td>' . htmlspecialchars($claim['department']) . '</td>';
                        $action_content .= '<td>₱' . number_format($claim['amount'] ?? 0, 2) . '</td>';
                        $action_content .= '<td>' . htmlspecialchars($claim['description'] ?? 'N/A') . '</td>';
                        $action_content .= '<td>' . htmlspecialchars($claim['claim_date'] ?? $claim['date'] ?? 'N/A') . '</td>';
                        $action_content .= '<td>';
                        $action_content .= '<button class="btn btn-sm btn-success me-1" onclick="updateClaimStatus(' . $claim['id'] . ', \'approved\')">Approve</button>';
                        $action_content .= '<button class="btn btn-sm btn-danger" onclick="updateClaimStatus(' . $claim['id'] . ', \'rejected\')">Reject</button>';
                        $action_content .= '</td>';
                        $action_content .= '</tr>';
                    }
                    $action_content .= '</tbody></table></div>';
                }
                break;
                
            case 'timesheets':
                $stmt = $pdo->query("
                    SELECT t.*, e.full_name, e.department 
                    FROM timesheets t 
                    JOIN employees e ON t.employee_id = e.id 
                    ORDER BY t.id DESC 
                    LIMIT 15
                ");
                $timesheets = $stmt->fetchAll();
                
                $action_content = '<h5><i class="fas fa-business-time me-2"></i>Recent Timesheet Entries</h5>';
                if (empty($timesheets)) {
                    $action_content .= '<p class="text-muted">No timesheet entries found.</p>';
                } else {
                    $action_content .= '<div class="table-responsive"><table class="table table-hover">';
                    $action_content .= '<thead><tr><th>Employee</th><th>Department</th><th>Date</th><th>Hours</th><th>Status</th></tr></thead><tbody>';
                    foreach ($timesheets as $timesheet) {
                        $action_content .= '<tr>';
                        $action_content .= '<td>' . htmlspecialchars($timesheet['full_name']) . '</td>';
                        $action_content .= '<td>' . htmlspecialchars($timesheet['department']) . '</td>';
                        $action_content .= '<td>' . htmlspecialchars($timesheet['date'] ?? $timesheet['work_date'] ?? 'N/A') . '</td>';
                        $action_content .= '<td>' . htmlspecialchars($timesheet['hours'] ?? 'N/A') . '</td>';
                        $action_content .= '<td><span class="badge bg-info">' . ucfirst($timesheet['status'] ?? 'submitted') . '</span></td>';
                        $action_content .= '</tr>';
                    }
                    $action_content .= '</tbody></table></div>';
                }
                break;
                
            case 'employees':
                $stmt = $pdo->query("
                    SELECT * FROM employees 
                    WHERE status = 'active' 
                    ORDER BY full_name 
                    LIMIT 20
                ");
                $employees = $stmt->fetchAll();
                
                $action_content = '<h5><i class="fas fa-users me-2"></i>Active Employees</h5>';
                if (empty($employees)) {
                    $action_content .= '<p class="text-muted">No active employees found.</p>';
                } else {
                    $action_content .= '<div class="table-responsive"><table class="table table-hover">';
                    $action_content .= '<thead><tr><th>Name</th><th>Email</th><th>Department</th><th>Position</th><th>Status</th></tr></thead><tbody>';
                    foreach ($employees as $employee) {
                        $action_content .= '<tr>';
                        $action_content .= '<td>' . htmlspecialchars($employee['full_name']) . '</td>';
                        $action_content .= '<td>' . htmlspecialchars($employee['email']) . '</td>';
                        $action_content .= '<td>' . htmlspecialchars($employee['department']) . '</td>';
                        $action_content .= '<td>' . htmlspecialchars($employee['position']) . '</td>';
                        $action_content .= '<td><span class="badge bg-success">Active</span></td>';
                        $action_content .= '</tr>';
                    }
                    $action_content .= '</tbody></table></div>';
                }
                break;
        }
    } catch (Exception $e) {
        $action_content = '<div class="alert alert-danger">Error loading data: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

$current_user = getCurrentEmployee();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Self-Service - HR Admin</title>
    
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

        .status-pending { background-color: #fff3cd; color: #856404; }
        .status-approved { background-color: #d4edda; color: #155724; }
        .status-rejected { background-color: #f8d7da; color: #721c24; }
        .status-submitted { background-color: #d1ecf1; color: #0c5460; }

        .activity-item {
            padding: 1rem 0;
            border-bottom: 1px solid #f1f3f4;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
        }

        .quick-action-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 1.5rem;
            text-decoration: none;
            transition: transform 0.2s;
            display: block;
        }

        .quick-action-card:hover {
            transform: translateY(-3px);
            color: white;
            text-decoration: none;
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
                    <h1 class="mb-1">Employee Self-Service Overview</h1>
                    <p class="text-muted mb-0">Monitor employee self-service activities and requests</p>
                </div>
                <div class="col-md-4 text-end">
                    <div class="d-flex align-items-center justify-content-end gap-3">
                        <div class="text-end">
                            <small class="text-muted d-block">Last Updated</small>
                            <strong><?= date('H:i') ?></strong>
                        </div>
                        <div class="bg-primary bg-opacity-10 rounded-circle p-3">
                            <i class="fas fa-sync-alt text-primary"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Error Messages -->
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
                    <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                        <i class="fas fa-calendar-times"></i>
                    </div>
                    <div class="stat-value text-warning"><?= number_format($stats['pending_leaves']) ?></div>
                    <div class="stat-label">Pending Leaves</div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6">
                <div class="stat-card">
                    <div class="stat-icon bg-info bg-opacity-10 text-info">
                        <i class="fas fa-receipt"></i>
                    </div>
                    <div class="stat-value text-info"><?= number_format($stats['pending_claims']) ?></div>
                    <div class="stat-label">Pending Claims</div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6">
                <div class="stat-card">
                    <div class="stat-icon bg-success bg-opacity-10 text-success">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-value text-success"><?= number_format($stats['active_employees']) ?></div>
                    <div class="stat-label">Active Employees</div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6">
                <div class="stat-card">
                    <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-value text-primary"><?= number_format($stats['recent_timesheets']) ?></div>
                    <div class="stat-label">Recent Timesheets</div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="content-card mb-4">
            <h5 class="mb-3">
                <i class="fas fa-bolt text-primary me-2"></i>
                Quick Actions
            </h5>
            <div class="row g-3">
                <?php
                // Get quick action data from database
                try {
                    $pdo = getAdminPDO();
                    
                    // Get pending leaves count
                    $stmt = $pdo->query("SELECT COUNT(*) as count FROM leave_requests WHERE status = 'pending'");
                    $pending_leaves = $stmt->fetch()['count'] ?? 0;
                    
                    // Get pending claims count
                    $stmt = $pdo->query("SELECT COUNT(*) as count FROM expense_claims WHERE status = 'pending'");
                    $pending_claims = $stmt->fetch()['count'] ?? 0;
                    
                    // Get recent timesheets count (last 7 days)
                    $stmt = $pdo->query("SELECT COUNT(*) as count FROM timesheets WHERE DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
                    $recent_timesheets = $stmt->fetch()['count'] ?? 0;
                    
                    // Get active employees count
                    $stmt = $pdo->query("SELECT COUNT(*) as count FROM employees WHERE status = 'active'");
                    $active_employees = $stmt->fetch()['count'] ?? 0;
                    
                } catch (Exception $e) {
                    // Set defaults if database query fails
                    $pending_leaves = 0;
                    $pending_claims = 0;
                    $recent_timesheets = 0;
                    $active_employees = 0;
                    error_log("Quick actions data error: " . $e->getMessage());
                }
                ?>
                
                <div class="col-md-3">
                    <a href="?action=leaves" class="quick-action-card">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-calendar-check fa-2x me-3 text-warning"></i>
                            <div>
                                <h6 class="mb-1">Review Leaves</h6>
                                <small class="opacity-75"><?= $pending_leaves ?> pending requests</small>
                            </div>
                            <?php if ($pending_leaves > 0): ?>
                                <span class="badge bg-warning ms-auto"><?= $pending_leaves ?></span>
                            <?php endif; ?>
                        </div>
                    </a>
                </div>
                
                <div class="col-md-3">
                    <a href="?action=claims" class="quick-action-card">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-file-invoice-dollar fa-2x me-3 text-success"></i>
                            <div>
                                <h6 class="mb-1">Process Claims</h6>
                                <small class="opacity-75"><?= $pending_claims ?> pending claims</small>
                            </div>
                            <?php if ($pending_claims > 0): ?>
                                <span class="badge bg-success ms-auto"><?= $pending_claims ?></span>
                            <?php endif; ?>
                        </div>
                    </a>
                </div>
                
                <div class="col-md-3">
                    <a href="?action=timesheets" class="quick-action-card">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-business-time fa-2x me-3 text-info"></i>
                            <div>
                                <h6 class="mb-1">Timesheet Reports</h6>
                                <small class="opacity-75"><?= $recent_timesheets ?> recent entries</small>
                            </div>
                        </div>
                    </a>
                </div>
                
                <div class="col-md-3">
                    <a href="?action=employees" class="quick-action-card">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-users fa-2x me-3 text-primary"></i>
                            <div>
                                <h6 class="mb-1">Employee Management</h6>
                                <small class="opacity-75"><?= $active_employees ?> active employees</small>
                            </div>
                        </div>
                    </a>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- Recent Leaves -->
            <div class="col-lg-6">
                <div class="content-card">
                    <h5 class="mb-3">
                        <i class="fas fa-calendar-alt text-primary me-2"></i>
                        Recent Leaves
                    </h5>
                    <div class="activity-list">
                        <?php if (empty($recent_leaves)): ?>
                            <div class="text-center py-4 text-muted">
                                <i class="fas fa-inbox fa-3x mb-3"></i>
                                <p>No recent leaves</p>
                            </div>
                        <?php else: ?>
                            <?php foreach(array_slice($recent_leaves, 0, 8) as $leave): ?>
                                <div class="activity-item">
                                    <div class="activity-icon bg-info bg-opacity-10 text-info">
                                        <i class="fas fa-calendar-times"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="fw-medium"><?= htmlspecialchars($leave['full_name']) ?></div>
                                        <small class="text-muted">
                                            <?= htmlspecialchars($leave['leave_type']) ?> - 
                                            <?= date('M d', strtotime($leave['start_date'])) ?> to <?= date('M d', strtotime($leave['end_date'])) ?>
                                        </small>
                                    </div>
                                    <span class="status-badge status-<?= $leave['status'] ?>">
                                        <?= ucfirst($leave['status']) ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Recent Claims -->
            <div class="col-lg-6">
                <div class="content-card">
                    <h5 class="mb-3">
                        <i class="fas fa-receipt text-primary me-2"></i>
                        Recent Claims
                    </h5>
                    <div class="activity-list">
                        <?php if (empty($recent_claims)): ?>
                            <div class="text-center py-4 text-muted">
                                <i class="fas fa-inbox fa-3x mb-3"></i>
                                <p>No recent claims</p>
                            </div>
                        <?php else: ?>
                            <?php foreach(array_slice($recent_claims, 0, 8) as $claim): ?>
                                <div class="activity-item">
                                    <div class="activity-icon bg-success bg-opacity-10 text-success">
                                        <i class="fas fa-money-bill-wave"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="fw-medium"><?= htmlspecialchars($claim['full_name']) ?></div>
                                        <small class="text-muted">
                                            <?= htmlspecialchars($claim['claim_type']) ?> - $<?= number_format($claim['amount'], 2) ?>
                                        </small>
                                    </div>
                                    <span class="status-badge status-<?= $claim['status'] ?>">
                                        <?= ucfirst($claim['status']) ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Employee Activity Summary -->
        <div class="content-card">
            <h5 class="mb-3">
                <i class="fas fa-chart-bar text-primary me-2"></i>
                Employee Activity Summary (Last 30 Days)
            </h5>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Employee</th>
                            <th>Department</th>
                            <th>Position</th>
                            <th>Leaves</th>
                            <th>Claims</th>
                            <th>Timesheets</th>
                            <th>Total Activity</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($employee_activity as $activity): ?>
                            <tr>
                                <td class="fw-medium"><?= htmlspecialchars($activity['full_name']) ?></td>
                                <td><?= htmlspecialchars($activity['department']) ?></td>
                                <td><?= htmlspecialchars($activity['position']) ?></td>
                                <td>
                                    <span class="badge bg-info"><?= $activity['leaves'] ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-success"><?= $activity['claims'] ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-primary"><?= $activity['timesheets'] ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-secondary"><?= $activity['leaves'] + $activity['claims'] + $activity['timesheets'] ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Quick Action Modal -->
    <div class="modal fade" id="actionModal" tabindex="-1" aria-labelledby="actionModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="actionModalLabel">Quick Action</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="actionContent">
                        <?= $action_content ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" onclick="location.reload()">Refresh Data</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Show modal if action is set
        <?php if ($show_action_modal): ?>
        document.addEventListener('DOMContentLoaded', function() {
            var actionModal = new bootstrap.Modal(document.getElementById('actionModal'));
            actionModal.show();
        });
        <?php endif; ?>
        
        // Function to update leave status
        function updateLeaveStatus(leaveId, status) {
            if (confirm('Are you sure you want to ' + status + ' this leave request?')) {
                fetch('update_status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        type: 'leave',
                        id: leaveId,
                        status: status
                    })
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('HTTP ' + response.status + ': ' + response.statusText);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data && data.success) {
                        alert('Leave request ' + status + ' successfully!');
                        location.reload();
                    } else {
                        alert('Error: ' + (data ? data.message : 'Unknown error occurred'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Network error occurred while updating the leave request: ' + error.message);
                });
            }
        }
        
        // Function to update claim status
        function updateClaimStatus(claimId, status) {
            if (confirm('Are you sure you want to ' + status + ' this expense claim?')) {
                fetch('update_status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        type: 'claim',
                        id: claimId,
                        status: status
                    })
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('HTTP ' + response.status + ': ' + response.statusText);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data && data.success) {
                        alert('Expense claim ' + status + ' successfully!');
                        location.reload();
                    } else {
                        alert('Error: ' + (data ? data.message : 'Unknown error occurred'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Network error occurred while updating the expense claim: ' + error.message);
                });
            }
        }
        
        // Auto-refresh data every 5 minutes
        setInterval(function() {
            location.reload();
        }, 300000);
    </script>
</body>
</html>
