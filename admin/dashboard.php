<?php
require_once 'admin_auth.php';

try {
    $pdo = getPDO();
    
    // Get admin dashboard statistics
    $stats = [];
    
    // Total employees
    $stmt = $pdo->query("SELECT COUNT(*) as total_employees FROM employees WHERE status = 'active'");
    $stats['total_employees'] = $stmt->fetch()['total_employees'] ?? 0;
    
    // Pending claims
    $stmt = $pdo->query("SELECT COUNT(*) as pending_claims FROM claims WHERE status = 'pending'");
    $stats['pending_claims'] = $stmt->fetch()['pending_claims'] ?? 0;
    
    // Active courses
    $stmt = $pdo->query("SELECT COUNT(*) as active_courses FROM courses WHERE status = 'active'");
    $stats['active_courses'] = $stmt->fetch()['active_courses'] ?? 0;
    
    // Upcoming trainings
    $stmt = $pdo->query("SELECT COUNT(*) as upcoming_trainings FROM trainings WHERE start_date > CURDATE()");
    $stats['upcoming_trainings'] = $stmt->fetch()['upcoming_trainings'] ?? 0;
    
    // Recent activities
    $stmt = $pdo->prepare("
        (SELECT 'employee_joined' as type, CONCAT(full_name, ' joined the company') as activity, 
                hire_date as activity_date, 'success' as color
         FROM employees 
         WHERE hire_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
         ORDER BY hire_date DESC LIMIT 3)
        UNION ALL
        (SELECT 'claim_submitted' as type, CONCAT('New claim submitted by employee ID ', employee_id) as activity,
                claim_date as activity_date, 'info' as color
         FROM claims 
         WHERE claim_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
         ORDER BY claim_date DESC LIMIT 3)
        ORDER BY activity_date DESC
        LIMIT 10
    ");
    $stmt->execute();
    $recent_activities = $stmt->fetchAll();
    
} catch (Exception $e) {
    $stats = [
        'total_employees' => 0,
        'pending_claims' => 0,
        'active_courses' => 0,
        'upcoming_trainings' => 0
    ];
    $recent_activities = [];
}

$current_user = getCurrentEmployee();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <!-- Admin Theme -->
    <link rel="stylesheet" href="assets/admin-theme.css">
    
    <style>

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

        .admin-header {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            border: none;
            transition: transform 0.2s, box-shadow 0.2s;
            height: 100%;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }

        .stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .activity-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            border: none;
        }

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

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .mobile-overlay {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.5);
                z-index: 999;
                display: none;
            }
            
            .mobile-overlay.show {
                display: block;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="admin-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="mb-1">Admin Dashboard</h1>
                    <p class="text-muted mb-0">Welcome to the administration portal</p>
                    <?php if (isset($_SESSION['admin_warning'])): ?>
                        <div class="alert alert-warning alert-sm mt-2 mb-0">
                            <i class="fas fa-exclamation-triangle me-2"></i><?= $_SESSION['admin_warning'] ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="col-md-4 text-end">
                    <div class="d-flex align-items-center justify-content-end gap-3">
                        <div class="text-end">
                            <small class="text-muted d-block">Today</small>
                            <strong><?= date('M d, Y') ?></strong>
                        </div>
                        <div class="bg-primary bg-opacity-10 rounded-circle p-3">
                            <i class="fas fa-calendar text-primary"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row g-4 mb-4">
            <div class="col-xl-3 col-md-6">
                <div class="stat-card">
                    <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-value text-primary"><?= number_format($stats['total_employees']) ?></div>
                    <div class="stat-label">Total Employees</div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6">
                <div class="stat-card">
                    <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-value text-warning"><?= number_format($stats['pending_claims']) ?></div>
                    <div class="stat-label">Pending Claims</div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6">
                <div class="stat-card">
                    <div class="stat-icon bg-success bg-opacity-10 text-success">
                        <i class="fas fa-book-open"></i>
                    </div>
                    <div class="stat-value text-success"><?= number_format($stats['active_courses']) ?></div>
                    <div class="stat-label">Active Courses</div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6">
                <div class="stat-card">
                    <div class="stat-icon bg-info bg-opacity-10 text-info">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div class="stat-value text-info"><?= number_format($stats['upcoming_trainings']) ?></div>
                    <div class="stat-label">Upcoming Trainings</div>
                </div>
            </div>
        </div>

        <!-- Recent Activities -->
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="activity-card">
                    <h5 class="mb-3">
                        <i class="fas fa-clock text-primary me-2"></i>
                        Recent Activities
                    </h5>
                    <div class="activity-list">
                        <?php if (empty($recent_activities)): ?>
                            <div class="text-center py-4 text-muted">
                                <i class="fas fa-inbox fa-3x mb-3"></i>
                                <p>No recent activities to display</p>
                            </div>
                        <?php else: ?>
                            <?php foreach($recent_activities as $activity): ?>
                                <div class="activity-item">
                                    <div class="activity-icon bg-<?= $activity['color'] ?> bg-opacity-10 text-<?= $activity['color'] ?>">
                                        <i class="fas fa-<?= $activity['type'] === 'employee_joined' ? 'user-plus' : 'receipt' ?>"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="fw-medium"><?= htmlspecialchars($activity['activity']) ?></div>
                                        <small class="text-muted"><?= date('M d, Y', strtotime($activity['activity_date'])) ?></small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Admin Theme JS -->
    <script src="assets/admin-theme.js"></script>
    
</body>
</html>
