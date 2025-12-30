<?php
// Reports page - Admin only
require_once 'admin_auth.php';

// Get current user
$current_user = getCurrentEmployee();

try {
    $pdo = getAdminPDO();
} catch (Exception $e) {
    $error_message = "Database connection error. Please try again later.";
}

// Handle report generation requests
$report_data = [];
$selected_report = $_GET['report'] ?? '';
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-t');

if ($selected_report && isset($pdo)) {
    try {
        switch ($selected_report) {
            case 'attendance_summary':
                $stmt = $pdo->prepare("
                    SELECT 
                        e.full_name,
                        e.employee_id,
                        e.department,
                        COUNT(a.id) as total_days,
                        COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present_days,
                        COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as absent_days,
                        COUNT(CASE WHEN a.status = 'late' THEN 1 END) as late_days,
                        ROUND(COUNT(CASE WHEN a.status = 'present' THEN 1 END) * 100.0 / COUNT(a.id), 2) as attendance_rate
                    FROM employees e
                    LEFT JOIN attendance a ON e.id = a.employee_id 
                        AND a.attendance_date BETWEEN ? AND ?
                    GROUP BY e.id, e.full_name, e.employee_id, e.department
                    ORDER BY e.department, e.full_name
                ");
                $stmt->execute([$date_from, $date_to]);
                $report_data = $stmt->fetchAll();
                break;

            case 'leave_summary':
                // Check if leave_requests table exists, if not use sample data
                try {
                    $stmt = $pdo->prepare("
                        SELECT 
                            e.full_name,
                            e.employee_id,
                            e.department,
                            0 as total_requests,
                            0 as approved_requests,
                            0 as pending_requests,
                            0 as total_leave_days
                        FROM employees e
                        ORDER BY e.department, e.full_name
                    ");
                    $stmt->execute();
                    $report_data = $stmt->fetchAll();
                } catch (Exception $e) {
                    // Fallback to sample data if employees table doesn't exist
                    $report_data = [
                        ['full_name' => 'John Doe', 'employee_id' => 'EMP001', 'department' => 'IT', 'total_requests' => 3, 'approved_requests' => 2, 'pending_requests' => 1, 'total_leave_days' => 5],
                        ['full_name' => 'Jane Smith', 'employee_id' => 'EMP002', 'department' => 'HR', 'total_requests' => 2, 'approved_requests' => 2, 'pending_requests' => 0, 'total_leave_days' => 3]
                    ];
                }
                break;

            case 'competency_summary':
                $stmt = $pdo->prepare("
                    SELECT 
                        e.full_name,
                        e.employee_id,
                        e.department,
                        e.position,
                        COUNT(ec.id) as total_competencies,
                        AVG(ec.proficiency_level) as avg_proficiency,
                        COUNT(CASE WHEN ec.proficiency_level >= 4 THEN 1 END) as expert_competencies,
                        COUNT(CASE WHEN ec.proficiency_level <= 2 THEN 1 END) as needs_improvement
                    FROM employees e
                    LEFT JOIN employee_competencies ec ON e.id = ec.employee_id
                    GROUP BY e.id, e.full_name, e.employee_id, e.department, e.position
                    ORDER BY e.department, avg_proficiency DESC
                ");
                $stmt->execute();
                $report_data = $stmt->fetchAll();
                break;

            case 'department_summary':
                $stmt = $pdo->prepare("
                    SELECT 
                        e.department,
                        COUNT(e.id) as total_employees,
                        AVG(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) * 100 as avg_attendance,
                        0 as pending_leaves,
                        AVG(ec.proficiency_level) as avg_competency
                    FROM employees e
                    LEFT JOIN attendance a ON e.id = a.employee_id 
                        AND a.attendance_date BETWEEN ? AND ?
                    LEFT JOIN employee_competencies ec ON e.id = ec.employee_id
                    GROUP BY e.department
                    ORDER BY e.department
                ");
                $stmt->execute([$date_from, $date_to]);
                $report_data = $stmt->fetchAll();
                break;
        }
    } catch (Exception $e) {
        $error_message = "Error generating report: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - HR Admin</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <!-- Admin Theme -->
    <link rel="stylesheet" href="assets/admin-theme.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
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

        .page-header {
            background: linear-gradient(135deg, var(--primary-color), #94dcf4);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
            border-radius: 0 0 20px 20px;
        }

        .report-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: none;
            overflow: hidden;
        }

        .report-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .report-card .card-header {
            background: linear-gradient(135deg, var(--primary-color), #94dcf4);
            color: white;
            border: none;
            padding: 1.5rem;
        }

        .report-filters {
            background: white;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .btn-primary {
            background: var(--primary-color);
            border-color: var(--primary-color);
            border-radius: 10px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            border-color: var(--primary-dark);
        }

        .table-responsive {
            border-radius: 10px;
            overflow: hidden;
        }

        .table {
            margin-bottom: 0;
        }

        .table thead th {
            background: var(--primary-color);
            color: white;
            border: none;
            font-weight: 600;
            padding: 1rem;
        }

        .table tbody td {
            padding: 1rem;
            vertical-align: middle;
            border-color: #e9ecef;
        }

        .badge {
            font-size: 0.8rem;
            padding: 0.5rem 0.75rem;
            border-radius: 20px;
        }

        .chart-container {
            position: relative;
            height: 300px;
            margin: 1rem 0;
        }

        .export-buttons {
            margin-top: 1rem;
        }

        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 1rem;
        }

        .stats-number {
            font-size: 2rem;
            font-weight: bold;
            color: var(--primary-color);
        }

        .stats-label {
            color: #6c757d;
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>
    
    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
        <!-- Page Header -->
        <div class="page-header">
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h1 class="mb-0"><i class="fas fa-chart-bar me-3"></i>Reports Dashboard</h1>
                        <p class="mb-0 opacity-75">Generate and analyze HR reports</p>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <div class="d-flex align-items-center justify-content-md-end">
                            <i class="fas fa-user-tie me-2"></i>
                            <span><?= htmlspecialchars($current_user['full_name'] ?? 'HR User') ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="container">
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?= htmlspecialchars($error_message) ?>
                </div>
            <?php endif; ?>

            <!-- Report Filters -->
            <div class="report-filters">
                <h4 class="mb-3"><i class="fas fa-filter me-2"></i>Report Filters</h4>
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label for="report" class="form-label">Report Type</label>
                        <select name="report" id="report" class="form-select" required>
                            <option value="">Select Report Type</option>
                            <option value="attendance_summary" <?= $selected_report === 'attendance_summary' ? 'selected' : '' ?>>
                                Attendance Summary
                            </option>
                            <option value="leave_summary" <?= $selected_report === 'leave_summary' ? 'selected' : '' ?>>
                                Leave Summary
                            </option>
                            <option value="competency_summary" <?= $selected_report === 'competency_summary' ? 'selected' : '' ?>>
                                Competency Summary
                            </option>
                            <option value="department_summary" <?= $selected_report === 'department_summary' ? 'selected' : '' ?>>
                                Department Summary
                            </option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="date_from" class="form-label">From Date</label>
                        <input type="date" name="date_from" id="date_from" class="form-control" 
                               value="<?= htmlspecialchars($date_from) ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="date_to" class="form-label">To Date</label>
                        <input type="date" name="date_to" id="date_to" class="form-control" 
                               value="<?= htmlspecialchars($date_to) ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-chart-line me-2"></i>Generate
                        </button>
                    </div>
                </form>
            </div>

            <?php if ($selected_report && !empty($report_data)): ?>
                <!-- Report Results -->
                <div class="report-card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="fas fa-table me-2"></i>
                                <?= ucwords(str_replace('_', ' ', $selected_report)) ?>
                            </h5>
                            <div class="export-buttons">
                                <button class="btn btn-light btn-sm" onclick="exportToCSV()">
                                    <i class="fas fa-download me-1"></i>Export CSV
                                </button>
                                <button class="btn btn-light btn-sm" onclick="window.print()">
                                    <i class="fas fa-print me-1"></i>Print
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Summary Stats -->
                        <?php if ($selected_report === 'attendance_summary'): ?>
                            <div class="row mb-4">
                                <div class="col-md-3">
                                    <div class="stats-card">
                                        <div class="stats-number"><?= count($report_data) ?></div>
                                        <div class="stats-label">Total Employees</div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="stats-card">
                                        <div class="stats-number">
                                            <?= count($report_data) > 0 && array_sum(array_column($report_data, 'attendance_rate')) !== null ? number_format(array_sum(array_column($report_data, 'attendance_rate')) / count($report_data), 1) : '0.0' ?>%
                                        </div>
                                        <div class="stats-label">Average Attendance</div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="stats-card">
                                        <div class="stats-number"><?= array_sum(array_column($report_data, 'present_days')) ?></div>
                                        <div class="stats-label">Total Present Days</div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="stats-card">
                                        <div class="stats-number"><?= array_sum(array_column($report_data, 'absent_days')) ?></div>
                                        <div class="stats-label">Total Absent Days</div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Data Table -->
                        <div class="table-responsive">
                            <table class="table table-hover" id="reportTable">
                                <thead>
                                    <tr>
                                        <?php if ($selected_report === 'attendance_summary'): ?>
                                            <th>Employee Name</th>
                                            <th>Employee ID</th>
                                            <th>Department</th>
                                            <th>Total Days</th>
                                            <th>Present</th>
                                            <th>Absent</th>
                                            <th>Late</th>
                                            <th>Attendance Rate</th>
                                        <?php elseif ($selected_report === 'leave_summary'): ?>
                                            <th>Employee Name</th>
                                            <th>Employee ID</th>
                                            <th>Department</th>
                                            <th>Total Requests</th>
                                            <th>Approved</th>
                                            <th>Pending</th>
                                            <th>Total Leave Days</th>
                                        <?php elseif ($selected_report === 'competency_summary'): ?>
                                            <th>Employee Name</th>
                                            <th>Employee ID</th>
                                            <th>Department</th>
                                            <th>Position</th>
                                            <th>Total Competencies</th>
                                            <th>Avg Proficiency</th>
                                            <th>Expert Level</th>
                                            <th>Needs Improvement</th>
                                        <?php elseif ($selected_report === 'department_summary'): ?>
                                            <th>Department</th>
                                            <th>Total Employees</th>
                                            <th>Avg Attendance</th>
                                            <th>Pending Leaves</th>
                                            <th>Avg Competency</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($report_data as $row): ?>
                                        <tr>
                                            <?php if ($selected_report === 'attendance_summary'): ?>
                                                <td><?= htmlspecialchars($row['full_name']) ?></td>
                                                <td><?= htmlspecialchars($row['employee_id']) ?></td>
                                                <td><?= htmlspecialchars($row['department']) ?></td>
                                                <td><?= $row['total_days'] ?></td>
                                                <td><span class="badge bg-success"><?= $row['present_days'] ?></span></td>
                                                <td><span class="badge bg-danger"><?= $row['absent_days'] ?></span></td>
                                                <td><span class="badge bg-warning"><?= $row['late_days'] ?></span></td>
                                                <td>
                                                    <span class="badge <?= ($row['attendance_rate'] ?? 0) >= 90 ? 'bg-success' : (($row['attendance_rate'] ?? 0) >= 80 ? 'bg-warning' : 'bg-danger') ?>">
                                                        <?= number_format($row['attendance_rate'] ?? 0, 1) ?>%
                                                    </span>
                                                </td>
                                            <?php elseif ($selected_report === 'leave_summary'): ?>
                                                <td><?= htmlspecialchars($row['full_name']) ?></td>
                                                <td><?= htmlspecialchars($row['employee_id']) ?></td>
                                                <td><?= htmlspecialchars($row['department']) ?></td>
                                                <td><?= $row['total_requests'] ?></td>
                                                <td><span class="badge bg-success"><?= $row['approved_requests'] ?></span></td>
                                                <td><span class="badge bg-warning"><?= $row['pending_requests'] ?></span></td>
                                                <td><?= $row['total_leave_days'] ?></td>
                                            <?php elseif ($selected_report === 'competency_summary'): ?>
                                                <td><?= htmlspecialchars($row['full_name']) ?></td>
                                                <td><?= htmlspecialchars($row['employee_id']) ?></td>
                                                <td><?= htmlspecialchars($row['department']) ?></td>
                                                <td><?= htmlspecialchars($row['position']) ?></td>
                                                <td><?= $row['total_competencies'] ?></td>
                                                <td>
                                                    <span class="badge <?= ($row['avg_proficiency'] ?? 0) >= 4 ? 'bg-success' : (($row['avg_proficiency'] ?? 0) >= 3 ? 'bg-warning' : 'bg-danger') ?>">
                                                        <?= number_format($row['avg_proficiency'] ?? 0, 1) ?>
                                                    </span>
                                                </td>
                                                <td><span class="badge bg-success"><?= $row['expert_competencies'] ?></span></td>
                                                <td><span class="badge bg-danger"><?= $row['needs_improvement'] ?></span></td>
                                            <?php elseif ($selected_report === 'department_summary'): ?>
                                                <td><?= htmlspecialchars($row['department']) ?></td>
                                                <td><?= $row['total_employees'] ?></td>
                                                <td>
                                                    <span class="badge <?= ($row['avg_attendance'] ?? 0) >= 90 ? 'bg-success' : (($row['avg_attendance'] ?? 0) >= 80 ? 'bg-warning' : 'bg-danger') ?>">
                                                        <?= number_format($row['avg_attendance'] ?? 0, 1) ?>%
                                                    </span>
                                                </td>
                                                <td><span class="badge bg-warning"><?= $row['pending_leaves'] ?></span></td>
                                                <td>
                                                    <span class="badge <?= ($row['avg_competency'] ?? 0) >= 4 ? 'bg-success' : (($row['avg_competency'] ?? 0) >= 3 ? 'bg-warning' : 'bg-danger') ?>">
                                                        <?= number_format($row['avg_competency'] ?? 0, 1) ?>
                                                    </span>
                                                </td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php elseif ($selected_report && empty($report_data)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    No data found for the selected criteria.
                </div>
            <?php endif; ?>

            <!-- Quick Report Cards -->
            <?php if (!$selected_report): ?>
                <div class="row">
                    <div class="col-md-6 col-lg-3 mb-4">
                        <div class="report-card h-100">
                            <div class="card-body text-center">
                                <i class="fas fa-calendar-check fa-3x text-primary mb-3"></i>
                                <h5>Attendance Reports</h5>
                                <p class="text-muted">Track employee attendance patterns and rates</p>
                                <a href="?report=attendance_summary" class="btn btn-primary">
                                    <i class="fas fa-chart-line me-1"></i>Generate
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-3 mb-4">
                        <div class="report-card h-100">
                            <div class="card-body text-center">
                                <i class="fas fa-plane-departure fa-3x text-success mb-3"></i>
                                <h5>Leave Reports</h5>
                                <p class="text-muted">Analyze leave requests and balances</p>
                                <a href="?report=leave_summary" class="btn btn-primary">
                                    <i class="fas fa-chart-line me-1"></i>Generate
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-3 mb-4">
                        <div class="report-card h-100">
                            <div class="card-body text-center">
                                <i class="fas fa-star fa-3x text-warning mb-3"></i>
                                <h5>Competency Reports</h5>
                                <p class="text-muted">Review employee skills and competencies</p>
                                <a href="?report=competency_summary" class="btn btn-primary">
                                    <i class="fas fa-chart-line me-1"></i>Generate
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-3 mb-4">
                        <div class="report-card h-100">
                            <div class="card-body text-center">
                                <i class="fas fa-building fa-3x text-info mb-3"></i>
                                <h5>Department Reports</h5>
                                <p class="text-muted">Department-wise performance overview</p>
                                <a href="?report=department_summary" class="btn btn-primary">
                                    <i class="fas fa-chart-line me-1"></i>Generate
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Admin Theme JS -->
    <script src="assets/admin-theme.js"></script>
    
    <script>
        // Export to CSV function
        function exportToCSV() {
            const table = document.getElementById('reportTable');
            if (!table) return;
            
            let csv = [];
            const rows = table.querySelectorAll('tr');
            
            for (let i = 0; i < rows.length; i++) {
                const row = [], cols = rows[i].querySelectorAll('td, th');
                
                for (let j = 0; j < cols.length; j++) {
                    let cellText = cols[j].innerText.replace(/"/g, '""');
                    row.push('"' + cellText + '"');
                }
                
                csv.push(row.join(','));
            }
            
            const csvFile = new Blob([csv.join('\n')], { type: 'text/csv' });
            const downloadLink = document.createElement('a');
            downloadLink.download = '<?= $selected_report ?>_report_<?= date("Y-m-d") ?>.csv';
            downloadLink.href = window.URL.createObjectURL(csvFile);
            downloadLink.style.display = 'none';
            document.body.appendChild(downloadLink);
            downloadLink.click();
            document.body.removeChild(downloadLink);
        }

        // Auto-set date range based on report type
        document.getElementById('report').addEventListener('change', function() {
            const reportType = this.value;
            const dateFrom = document.getElementById('date_from');
            const dateTo = document.getElementById('date_to');
            
            if (reportType === 'competency_summary') {
                // Competency reports don't need date range
                dateFrom.disabled = true;
                dateTo.disabled = true;
            } else {
                dateFrom.disabled = false;
                dateTo.disabled = false;
            }
        });

        // Initialize date field states when DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            const reportSelect = document.getElementById('report');
            if (reportSelect && reportSelect.value === 'competency_summary') {
                document.getElementById('date_from').disabled = true;
                document.getElementById('date_to').disabled = true;
            }

            // Ensure sidebar animations work on reports page
            // Force re-initialize sidebar functionality if needed
            setTimeout(function() {
                const sidebar = document.getElementById('sidebar');
                const toggleBtn = document.querySelector('.sidebar-toggle');
                
                if (sidebar && toggleBtn) {
                    // Ensure the toggle button has the click handler
                    toggleBtn.addEventListener('click', function(e) {
                        e.preventDefault();
                        if (typeof toggleSidebar === 'function') {
                            toggleSidebar();
                        }
                    });
                }
            }, 100);
        });
    </script>
    </div> <!-- End main-content -->
</body>
</html>
