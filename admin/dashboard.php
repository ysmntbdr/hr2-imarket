<?php
require_once 'admin_auth.php';

try {
    $pdo = getPDO();
    
    // Get comprehensive dashboard statistics for all modules
    $stats = [];
    
    // ========== EMPLOYEE MODULE ==========
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM employees WHERE status = 'active'");
    $stats['employees']['active'] = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM employees WHERE status = 'inactive'");
    $stats['employees']['inactive'] = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM employees");
    $stats['employees']['total'] = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM employees WHERE hire_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
    $stats['employees']['new_hires_30d'] = $stmt->fetch()['total'] ?? 0;
    
    // ========== CLAIMS MODULE ==========
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM claims WHERE status = 'pending'");
    $stats['claims']['pending'] = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM claims WHERE status = 'approved'");
    $stats['claims']['approved'] = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM claims");
    $stats['claims']['total'] = $stmt->fetch()['total'] ?? 0;
    
    // ========== LEARNING MANAGEMENT MODULE ==========
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM courses WHERE status = 'active'");
        $stats['courses']['active'] = $stmt->fetch()['total'] ?? 0;
    } catch (Exception $e) {
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM courses");
        $stats['courses']['active'] = $stmt->fetch()['total'] ?? 0;
    }
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM courses");
    $stats['courses']['total'] = $stmt->fetch()['total'] ?? 0;
    
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM employee_courses WHERE status = 'enrolled'");
        $stats['courses']['enrollments'] = $stmt->fetch()['total'] ?? 0;
        
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM employee_courses WHERE status = 'completed'");
        $stats['courses']['completions'] = $stmt->fetch()['total'] ?? 0;
    } catch (Exception $e) {
        $stats['courses']['enrollments'] = 0;
        $stats['courses']['completions'] = 0;
    }
    
    // ========== TRAINING MANAGEMENT MODULE ==========
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM trainings WHERE start_date > CURDATE()");
    $stats['trainings']['upcoming'] = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM trainings");
    $stats['trainings']['total'] = $stmt->fetch()['total'] ?? 0;
    
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM employee_trainings");
        $stats['trainings']['registrations'] = $stmt->fetch()['total'] ?? 0;
    } catch (Exception $e) {
        $stats['trainings']['registrations'] = 0;
    }
    
    // ========== COMPETENCY MANAGEMENT MODULE ==========
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM competencies");
        $stats['competencies']['total'] = $stmt->fetch()['total'] ?? 0;
        
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM employee_competencies");
        $stats['competencies']['assessments'] = $stmt->fetch()['total'] ?? 0;
    } catch (Exception $e) {
        $stats['competencies']['total'] = 0;
        $stats['competencies']['assessments'] = 0;
    }
    
    // ========== SUCCESSION PLANNING MODULE ==========
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM succession_plans");
        $stats['succession']['total_plans'] = $stmt->fetch()['total'] ?? 0;
        
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM succession_plans WHERE readiness_level = 'high'");
        $stats['succession']['ready_high'] = $stmt->fetch()['total'] ?? 0;
        
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM succession_plans WHERE readiness_level = 'medium'");
        $stats['succession']['ready_medium'] = $stmt->fetch()['total'] ?? 0;
    } catch (Exception $e) {
        $stats['succession']['total_plans'] = 0;
        $stats['succession']['ready_high'] = 0;
        $stats['succession']['ready_medium'] = 0;
    }
    
    // ========== LEAVE MODULE ==========
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM leaves WHERE status = 'pending'");
        $stats['leaves']['pending'] = $stmt->fetch()['total'] ?? 0;
        
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM leaves WHERE status = 'approved'");
        $stats['leaves']['approved'] = $stmt->fetch()['total'] ?? 0;
        
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM leaves");
        $stats['leaves']['total'] = $stmt->fetch()['total'] ?? 0;
    } catch (Exception $e) {
        $stats['leaves']['pending'] = 0;
        $stats['leaves']['approved'] = 0;
        $stats['leaves']['total'] = 0;
    }
    
    // ========== TIMESHEET MODULE ==========
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM timesheets WHERE DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
        $stats['timesheets']['recent_7d'] = $stmt->fetch()['total'] ?? 0;
    } catch (Exception $e) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM timesheets WHERE DATE(date) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
            $stats['timesheets']['recent_7d'] = $stmt->fetch()['total'] ?? 0;
        } catch (Exception $e2) {
            $stats['timesheets']['recent_7d'] = 0;
        }
    }
    
} catch (Exception $e) {
    // Initialize all stats to 0 on error
    $stats = [
        'employees' => ['active' => 0, 'inactive' => 0, 'total' => 0, 'new_hires_30d' => 0],
        'claims' => ['pending' => 0, 'approved' => 0, 'total' => 0],
        'courses' => ['active' => 0, 'total' => 0, 'enrollments' => 0, 'completions' => 0],
        'trainings' => ['upcoming' => 0, 'total' => 0, 'registrations' => 0],
        'competencies' => ['total' => 0, 'assessments' => 0],
        'succession' => ['total_plans' => 0, 'ready_high' => 0, 'ready_medium' => 0],
        'leaves' => ['pending' => 0, 'approved' => 0, 'total' => 0],
        'timesheets' => ['recent_7d' => 0]
    ];
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
        :root {
            --primary-color: #4bc5ec;
            --primary-dark: #3ba3cc;
            --sidebar-width: 280px;
            --sidebar-collapsed: 80px;
            --shadow-sm: 0 2px 4px rgba(0,0,0,0.05);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
            --shadow-lg: 0 8px 24px rgba(0,0,0,0.12);
            --shadow-xl: 0 12px 40px rgba(0,0,0,0.15);
            --border-radius: 12px;
            --border-radius-lg: 16px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e9ecef 100%);
            background-attachment: fixed;
            color: #2c3e50;
            line-height: 1.6;
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
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            border-radius: var(--border-radius-lg);
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-md);
            border: 1px solid rgba(255,255,255,0.8);
            backdrop-filter: blur(10px);
            position: relative;
            overflow: hidden;
        }

        .admin-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), #94dcf4, var(--primary-color));
        }

        .admin-header h1 {
            font-size: 2rem;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 0.5rem;
            letter-spacing: -0.5px;
        }

        .admin-header .text-muted {
            font-size: 0.95rem;
            color: #6c757d;
            font-weight: 400;
        }

        .stat-card {
            background: #ffffff;
            border-radius: var(--border-radius-lg);
            padding: 1.75rem;
            box-shadow: var(--shadow-md);
            border: 1px solid rgba(0,0,0,0.05);
            transition: var(--transition);
            height: 100%;
            position: relative;
            overflow: hidden;
            cursor: pointer;
        }

        .stat-card::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, transparent, var(--primary-color), transparent);
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: var(--shadow-xl);
            border-color: rgba(75, 197, 236, 0.2);
        }

        .stat-card:hover::after {
            transform: scaleX(1);
        }

        .stat-icon {
            width: 64px;
            height: 64px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.75rem;
            margin-bottom: 1.25rem;
            transition: var(--transition);
            position: relative;
        }

        .stat-card:hover .stat-icon {
            transform: scale(1.1) rotate(5deg);
        }

        .stat-value {
            font-size: 2.75rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            line-height: 1.2;
            background: linear-gradient(135deg, currentColor, currentColor);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            letter-spacing: -1px;
        }

        .stat-label {
            color: #6c757d;
            font-size: 0.95rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.75rem;
        }

        .stat-card small {
            font-size: 0.85rem;
            line-height: 1.6;
        }

        .stat-card small i {
            font-size: 0.75rem;
            margin-right: 0.25rem;
        }

        .bg-purple {
            background-color: #6f42c1 !important;
        }

        .bg-orange {
            background-color: #fd7e14 !important;
        }

        /* Module Summary Cards Enhancement */
        .module-summary-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            transition: var(--transition);
            border: 1px solid rgba(0,0,0,0.05);
            height: 100%;
        }

        .module-summary-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-md);
            border-color: rgba(75, 197, 236, 0.2);
        }

        .module-summary-card .text-muted {
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            opacity: 0.7;
        }

        .module-summary-card .fw-bold {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1a1a1a;
            margin: 0.5rem 0;
        }

        .module-summary-card i {
            font-size: 1.5rem;
            opacity: 0.8;
        }

        /* Smooth animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .stat-card,
        .activity-card,
        .module-summary-card {
            animation: fadeInUp 0.6s ease-out backwards;
        }

        .stat-card:nth-child(1) { animation-delay: 0.1s; }
        .stat-card:nth-child(2) { animation-delay: 0.2s; }
        .stat-card:nth-child(3) { animation-delay: 0.3s; }
        .stat-card:nth-child(4) { animation-delay: 0.4s; }
        .stat-card:nth-child(5) { animation-delay: 0.5s; }
        .stat-card:nth-child(6) { animation-delay: 0.6s; }
        .stat-card:nth-child(7) { animation-delay: 0.7s; }
        .stat-card:nth-child(8) { animation-delay: 0.8s; }

        .activity-card {
            background: #ffffff;
            border-radius: var(--border-radius-lg);
            padding: 2rem;
            box-shadow: var(--shadow-md);
            border: 1px solid rgba(0,0,0,0.05);
            transition: var(--transition);
        }

        .activity-card:hover {
            box-shadow: var(--shadow-lg);
        }

        .activity-card h5 {
            font-size: 1.25rem;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .activity-card h5 i {
            font-size: 1.1rem;
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

        /* Enhanced Responsive Design */
        @media (max-width: 1200px) {
            .stat-value {
                font-size: 2.25rem;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }

            .admin-header {
                padding: 1.5rem;
            }

            .admin-header h1 {
                font-size: 1.5rem;
            }

            .stat-card {
                padding: 1.25rem;
            }

            .stat-value {
                font-size: 2rem;
            }

            .stat-icon {
                width: 56px;
                height: 56px;
                font-size: 1.5rem;
            }

            .activity-card {
                padding: 1.5rem;
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
                backdrop-filter: blur(4px);
            }
            
            .mobile-overlay.show {
                display: block;
            }
        }

        @media (max-width: 576px) {
            .admin-header {
                padding: 1rem;
            }

            .stat-card {
                padding: 1rem;
            }

            .stat-value {
                font-size: 1.75rem;
            }

            .module-summary-card {
                padding: 1rem;
            }
        }

        /* Scrollbar Styling */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb {
            background: var(--primary-color);
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--primary-dark);
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
                    <h1 class="mb-2">
                        <i class="fas fa-tachometer-alt text-primary me-2"></i>
                        Admin Dashboard
                    </h1>
                    <p class="text-muted mb-0">
                        <i class="fas fa-user-shield me-2"></i>
                        Welcome back, <strong><?= htmlspecialchars($current_user['full_name'] ?? 'Administrator') ?></strong>
                    </p>
                    <?php if (isset($_SESSION['admin_warning'])): ?>
                        <div class="alert alert-warning alert-sm mt-3 mb-0 border-0 shadow-sm">
                            <i class="fas fa-exclamation-triangle me-2"></i><?= $_SESSION['admin_warning'] ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="col-md-4 text-end">
                    <div class="d-flex align-items-center justify-content-end gap-3">
                        <div class="text-end">
                            <small class="text-muted d-block text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.5px; font-weight: 600;">Today</small>
                            <strong style="font-size: 1.1rem; color: #1a1a1a;"><?= date('M d, Y') ?></strong>
                        </div>
                        <div class="bg-primary bg-opacity-10 rounded-circle p-3 d-flex align-items-center justify-content-center" style="width: 56px; height: 56px;">
                            <i class="fas fa-calendar text-primary" style="font-size: 1.25rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Module Summary Cards -->
        <div class="row g-4 mb-4">
            <!-- Employee Module -->
            <div class="col-xl-3 col-md-6">
                <div class="stat-card">
                    <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-value text-primary"><?= number_format($stats['employees']['active']) ?></div>
                    <div class="stat-label">Active Employees</div>
                    <div class="mt-2">
                        <small class="text-muted">
                            <i class="fas fa-user-check me-1"></i><?= number_format($stats['employees']['total']) ?> Total
                            <?php if ($stats['employees']['new_hires_30d'] > 0): ?>
                                | <span class="text-success"><i class="fas fa-user-plus me-1"></i><?= number_format($stats['employees']['new_hires_30d']) ?> New (30d)</span>
                            <?php endif; ?>
                        </small>
                    </div>
                </div>
            </div>
            
            <!-- Claims Module -->
            <div class="col-xl-3 col-md-6">
                <div class="stat-card">
                    <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="stat-value text-warning"><?= number_format($stats['claims']['pending']) ?></div>
                    <div class="stat-label">Pending Claims</div>
                    <div class="mt-2">
                        <small class="text-muted">
                            <i class="fas fa-check-circle text-success me-1"></i><?= number_format($stats['claims']['approved']) ?> Approved
                            | <i class="fas fa-list me-1"></i><?= number_format($stats['claims']['total']) ?> Total
                        </small>
                    </div>
                </div>
            </div>
            
            <!-- Learning Management Module -->
            <div class="col-xl-3 col-md-6">
                <div class="stat-card">
                    <div class="stat-icon bg-success bg-opacity-10 text-success">
                        <i class="fas fa-book-open"></i>
                    </div>
                    <div class="stat-value text-success"><?= number_format($stats['courses']['active']) ?></div>
                    <div class="stat-label">Active Courses</div>
                    <div class="mt-2">
                        <small class="text-muted">
                            <i class="fas fa-user-graduate me-1"></i><?= number_format($stats['courses']['enrollments']) ?> Enrollments
                            | <i class="fas fa-check-double text-success me-1"></i><?= number_format($stats['courses']['completions']) ?> Completed
                        </small>
                    </div>
                </div>
            </div>
            
            <!-- Training Management Module -->
            <div class="col-xl-3 col-md-6">
                <div class="stat-card">
                    <div class="stat-icon bg-info bg-opacity-10 text-info">
                        <i class="fas fa-chalkboard-teacher"></i>
                    </div>
                    <div class="stat-value text-info"><?= number_format($stats['trainings']['upcoming']) ?></div>
                    <div class="stat-label">Upcoming Trainings</div>
                    <div class="mt-2">
                        <small class="text-muted">
                            <i class="fas fa-list me-1"></i><?= number_format($stats['trainings']['total']) ?> Total
                            | <i class="fas fa-user-check me-1"></i><?= number_format($stats['trainings']['registrations']) ?> Registrations
                        </small>
                    </div>
                </div>
            </div>
            
            <!-- Competency Management Module -->
            <div class="col-xl-3 col-md-6">
                <div class="stat-card">
                    <div class="stat-icon bg-purple bg-opacity-10" style="color: #6f42c1;">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-value" style="color: #6f42c1;"><?= number_format($stats['competencies']['total']) ?></div>
                    <div class="stat-label">Competencies</div>
                    <div class="mt-2">
                        <small class="text-muted">
                            <i class="fas fa-clipboard-check me-1"></i><?= number_format($stats['competencies']['assessments']) ?> Assessments
                        </small>
                    </div>
                </div>
            </div>
            
            <!-- Succession Planning Module -->
            <div class="col-xl-3 col-md-6">
                <div class="stat-card">
                    <div class="stat-icon bg-orange bg-opacity-10" style="color: #fd7e14;">
                        <i class="fas fa-sitemap"></i>
                    </div>
                    <div class="stat-value" style="color: #fd7e14;"><?= number_format($stats['succession']['total_plans']) ?></div>
                    <div class="stat-label">Succession Plans</div>
                    <div class="mt-2">
                        <small class="text-muted">
                            <i class="fas fa-arrow-up text-success me-1"></i><?= number_format($stats['succession']['ready_high']) ?> High Ready
                            | <i class="fas fa-arrow-right text-warning me-1"></i><?= number_format($stats['succession']['ready_medium']) ?> Medium
                        </small>
                    </div>
                </div>
            </div>
            
            <!-- Leave Module -->
            <div class="col-xl-3 col-md-6">
                <div class="stat-card">
                    <div class="stat-icon bg-danger bg-opacity-10 text-danger">
                        <i class="fas fa-plane-departure"></i>
                    </div>
                    <div class="stat-value text-danger"><?= number_format($stats['leaves']['pending']) ?></div>
                    <div class="stat-label">Pending Leaves</div>
                    <div class="mt-2">
                        <small class="text-muted">
                            <i class="fas fa-check-circle text-success me-1"></i><?= number_format($stats['leaves']['approved']) ?> Approved
                            | <i class="fas fa-list me-1"></i><?= number_format($stats['leaves']['total']) ?> Total
                        </small>
                    </div>
                </div>
            </div>
            
            <!-- Timesheet Module -->
            <div class="col-xl-3 col-md-6">
                <div class="stat-card">
                    <div class="stat-icon bg-secondary bg-opacity-10 text-secondary">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-value text-secondary"><?= number_format($stats['timesheets']['recent_7d']) ?></div>
                    <div class="stat-label">Timesheets (Last 7 Days)</div>
                    <div class="mt-2">
                        <small class="text-muted">
                            <i class="fas fa-calendar-week me-1"></i>Recent submissions
                        </small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Module Summary Overview -->
        <div class="row g-4">
            <div class="col-12">
                <div class="activity-card">
                    <h5 class="mb-4">
                        <i class="fas fa-chart-pie text-primary me-2"></i>
                        System Overview Summary
                    </h5>
                    <div class="row g-3">
                        <div class="col-md-6 col-lg-3">
                            <div class="module-summary-card">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <span class="text-muted small">Employee Module</span>
                                    <i class="fas fa-users text-primary"></i>
                                </div>
                                <div class="fw-bold"><?= number_format($stats['employees']['active']) ?> Active</div>
                                <small class="text-muted d-block"><?= number_format($stats['employees']['inactive']) ?> Inactive</small>
                            </div>
                        </div>
                        <div class="col-md-6 col-lg-3">
                            <div class="module-summary-card">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <span class="text-muted small">Claims Module</span>
                                    <i class="fas fa-money-bill-wave text-warning"></i>
                                </div>
                                <div class="fw-bold"><?= number_format($stats['claims']['pending']) ?> Pending</div>
                                <small class="text-muted d-block"><?= number_format($stats['claims']['approved']) ?> Approved</small>
                            </div>
                        </div>
                        <div class="col-md-6 col-lg-3">
                            <div class="module-summary-card">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <span class="text-muted small">Learning & Training</span>
                                    <i class="fas fa-graduation-cap text-success"></i>
                                </div>
                                <div class="fw-bold"><?= number_format($stats['courses']['active']) ?> Courses</div>
                                <small class="text-muted d-block"><?= number_format($stats['trainings']['upcoming']) ?> Upcoming Trainings</small>
                            </div>
                        </div>
                        <div class="col-md-6 col-lg-3">
                            <div class="module-summary-card">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <span class="text-muted small">Leave Management</span>
                                    <i class="fas fa-plane-departure text-danger"></i>
                                </div>
                                <div class="fw-bold"><?= number_format($stats['leaves']['pending']) ?> Pending</div>
                                <small class="text-muted d-block"><?= number_format($stats['leaves']['approved']) ?> Approved</small>
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
