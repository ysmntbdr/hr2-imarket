<?php
// Enhanced Payroll Management System
require_once 'auth_check.php';

$pdo = getPDO();
$employee_id = getCurrentEmployeeId();

// Get current employee data
$employee = getCurrentEmployee();

// Get current/latest payslip
$stmt = $pdo->prepare("
    SELECT * FROM payroll 
    WHERE employee_id = ? 
    ORDER BY pay_period_end DESC 
    LIMIT 1
");
$stmt->execute([$employee_id]);
$payslip_data = $stmt->fetch();

$current_payslip = [
    'period' => $payslip_data ? date('F j', strtotime($payslip_data['pay_period_start'])) . ' - ' . date('F j, Y', strtotime($payslip_data['pay_period_end'])) : 'No payslip available',
    'pay_date' => $payslip_data ? date('Y-m-d', strtotime($payslip_data['pay_date'])) : null,
    'basic_salary' => $payslip_data['basic_salary'] ?? 0,
    'overtime_hours' => $payslip_data['overtime_hours'] ?? 0,
    'overtime_rate' => $payslip_data['overtime_rate'] ?? 0,
    'overtime_pay' => $payslip_data['overtime_pay'] ?? 0,
    'allowances' => [
        'transportation' => $payslip_data['transportation_allowance'] ?? 0,
        'meal' => $payslip_data['meal_allowance'] ?? 0,
        'communication' => $payslip_data['communication_allowance'] ?? 0,
        'performance_bonus' => $payslip_data['performance_bonus'] ?? 0
    ],
    'deductions' => [
        'sss' => $payslip_data['sss_deduction'] ?? 0,
        'philhealth' => $payslip_data['philhealth_deduction'] ?? 0,
        'pagibig' => $payslip_data['pagibig_deduction'] ?? 0,
        'withholding_tax' => $payslip_data['withholding_tax'] ?? 0,
        'late_deduction' => $payslip_data['late_deduction'] ?? 0
    ],
    'gross_pay' => $payslip_data['gross_pay'] ?? 0,
    'total_deductions' => $payslip_data['total_deductions'] ?? 0,
    'net_pay' => $payslip_data['net_pay'] ?? 0
];

// Get payroll history
$stmt = $pdo->prepare("
    SELECT 
        CONCAT(DATE_FORMAT(pay_period_start, '%b %e'), ' - ', DATE_FORMAT(pay_period_end, '%b %e, %Y')) as period,
        pay_date,
        gross_pay,
        total_deductions as deductions,
        net_pay,
        status
    FROM payroll 
    WHERE employee_id = ? 
    ORDER BY pay_period_end DESC 
    LIMIT 10
");
$stmt->execute([$employee_id]);
$payroll_history = $stmt->fetchAll();

// Calculate YTD summary
$stmt = $pdo->prepare("
    SELECT 
        SUM(gross_pay) as gross_earnings,
        SUM(total_deductions) as total_deductions,
        SUM(net_pay) as net_earnings,
        SUM(withholding_tax) as tax_withheld,
        SUM(sss_deduction) as sss_contributions,
        SUM(philhealth_deduction) as philhealth_contributions,
        SUM(pagibig_deduction) as pagibig_contributions
    FROM payroll 
    WHERE employee_id = ? 
    AND YEAR(pay_period_end) = YEAR(CURDATE())
");
$stmt->execute([$employee_id]);
$ytd_data = $stmt->fetch();

$ytd_summary = [
    'gross_earnings' => $ytd_data['gross_earnings'] ?? 0,
    'total_deductions' => $ytd_data['total_deductions'] ?? 0,
    'net_earnings' => $ytd_data['net_earnings'] ?? 0,
    'tax_withheld' => $ytd_data['tax_withheld'] ?? 0,
    'sss_contributions' => $ytd_data['sss_contributions'] ?? 0,
    'philhealth_contributions' => $ytd_data['philhealth_contributions'] ?? 0,
    'pagibig_contributions' => $ytd_data['pagibig_contributions'] ?? 0
];

// Get tax documents
$stmt = $pdo->prepare("
    SELECT document_type as type, document_year as year, status 
    FROM tax_documents 
    WHERE employee_id = ? 
    ORDER BY document_year DESC
");
$stmt->execute([$employee_id]);
$tax_documents = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Payroll Management</title>
  
  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  
  <style>
    :root {
      --primary-color: #4bc5ec;
      --primary-dark: #3ba3cc;
    }

    body {
      background-color: #f8f9fa;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    .payroll-header {
      background: linear-gradient(135deg, var(--primary-color), #94dcf4);
      color: white;
      border-radius: 20px;
      padding: 2rem;
      margin-bottom: 2rem;
      box-shadow: 0 8px 25px rgba(75, 197, 236, 0.3);
    }

    .payroll-card {
      background: white;
      border-radius: 15px;
      padding: 2rem;
      box-shadow: 0 4px 15px rgba(0,0,0,0.08);
      border: none;
      margin-bottom: 2rem;
      transition: transform 0.2s, box-shadow 0.2s;
    }

    .payroll-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    }

    .payslip-card {
      background: white;
      border-radius: 15px;
      padding: 2rem;
      box-shadow: 0 4px 15px rgba(0,0,0,0.08);
      border: 1px solid #e9ecef;
      margin-bottom: 2rem;
    }

    .amount-display {
      font-size: 2rem;
      font-weight: 700;
      color: var(--primary-color);
    }

    .earnings-item, .deduction-item {
      display: flex;
      justify-content: between;
      align-items: center;
      padding: 0.75rem 0;
      border-bottom: 1px solid #f1f3f4;
    }

    .earnings-item:last-child, .deduction-item:last-child {
      border-bottom: none;
    }

    .animate-slide-up {
      animation: slideUp 0.6s ease-out;
    }

    @keyframes slideUp {
      from {
        opacity: 0;
        transform: translateY(30px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .currency {
      color: var(--primary-color);
      font-weight: 600;
    }

    .payslip-header {
      background: linear-gradient(135deg, #28a745, #20c997);
      color: white;
      border-radius: 15px;
      padding: 1.5rem;
      margin-bottom: 2rem;
    }
  </style>
</head>
<body class="bg-light">
  <?php include 'includes/sidebar.php'; ?>
  
  <main class="main-content">
    <div class="container-fluid p-4">
    <!-- Payroll Header -->
    <div class="payroll-header animate-slide-up text-center">
      <h1 class="mb-2">
        <i class="fas fa-money-bill-wave me-3"></i>
        Payroll Management
      </h1>
      <p class="mb-3 opacity-75">View your salary details and payroll history</p>
      <div class="row justify-content-center">
        <div class="col-md-8">
          <h3>Welcome, <?= htmlspecialchars($employee['full_name'] ?? '') ?></h3>
          <p class="mb-0"><?= htmlspecialchars($employee['position']) ?> • <?= htmlspecialchars($employee['department']) ?></p>
        </div>
      </div>
    </div>

    <!-- Current Payslip -->
    <div class="payslip-card animate-slide-up">
      <div class="payslip-header text-center">
        <h4 class="mb-2">
          <i class="fas fa-file-invoice-dollar me-2"></i>
          Current Payslip
        </h4>
        <p class="mb-0"><?= $current_payslip['period'] ?> • Pay Date: <?= $current_payslip['pay_date'] ? date('M j, Y', strtotime($current_payslip['pay_date'])) : 'N/A' ?></p>
      </div>

      <div class="row g-4">
        <!-- Earnings Section -->
        <div class="col-lg-4">
          <div class="h5 text-success mb-3">
            <i class="fas fa-plus-circle me-2"></i>Earnings
          </div>
          
          <div class="earnings-item">
            <span>Basic Salary</span>
            <span class="currency">₱<?= number_format($current_payslip['basic_salary'], 2) ?></span>
          </div>
          
          <div class="earnings-item">
            <span>Overtime (<?= $current_payslip['overtime_hours'] ?>hrs @ ₱<?= number_format($current_payslip['overtime_rate'], 2) ?>)</span>
            <span class="currency">₱<?= number_format($current_payslip['overtime_pay'], 2) ?></span>
          </div>
          
          <?php foreach ($current_payslip['allowances'] as $type => $amount): ?>
            <div class="earnings-item">
              <span><?= ucwords(str_replace('_', ' ', $type)) ?></span>
              <span class="currency">₱<?= number_format($amount, 2) ?></span>
            </div>
          <?php endforeach; ?>
          
          <div class="earnings-item border-top border-2 border-success pt-3 mt-3">
            <strong>Gross Pay</strong>
            <strong class="currency fs-5">₱<?= number_format($current_payslip['gross_pay'], 2) ?></strong>
          </div>
        </div>

        <!-- Deductions Section -->
        <div class="col-lg-4">
          <div class="h5 text-danger mb-3">
            <i class="fas fa-minus-circle me-2"></i>Deductions
          </div>
          
          <?php foreach ($current_payslip['deductions'] as $type => $amount): ?>
            <?php if ($amount > 0): ?>
              <div class="deduction-item">
                <span><?= strtoupper(str_replace('_', ' ', $type)) ?></span>
                <span class="text-danger">₱<?= number_format($amount, 2) ?></span>
              </div>
            <?php endif; ?>
          <?php endforeach; ?>
          
          <div class="deduction-item border-top border-2 border-danger pt-3 mt-3">
            <strong>Total Deductions</strong>
            <strong class="text-danger fs-5">₱<?= number_format($current_payslip['total_deductions'], 2) ?></strong>
          </div>
        </div>

        <!-- Net Pay Section -->
        <div class="col-lg-4">
          <div class="text-center p-4 bg-primary bg-opacity-10 rounded-3">
            <h5 class="text-primary mb-3">
              <i class="fas fa-wallet me-2"></i>Net Pay
            </h5>
            <div class="amount-display text-primary mb-2">
              ₱<?= number_format($current_payslip['net_pay'], 2) ?>
            </div>
            <p class="text-muted mb-3">Take Home Pay</p>
            <button class="btn btn-primary">
              <i class="fas fa-download me-2"></i>Download Payslip
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Main Content -->
    <div class="row g-4">
      <!-- Payroll History -->
      <div class="col-lg-8">
        <div class="payroll-card animate-slide-up">
          <h4 class="text-primary mb-4">
            <i class="fas fa-history me-2"></i>
            Payroll History
          </h4>
          
          <div class="table-responsive">
            <table class="table table-hover">
              <thead class="table-light">
                <tr>
                  <th>Pay Period</th>
                  <th>Pay Date</th>
                  <th>Gross Pay</th>
                  <th>Deductions</th>
                  <th>Net Pay</th>
                  <th>Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($payroll_history as $payroll): ?>
                  <tr>
                    <td><?= htmlspecialchars($payroll['period']) ?></td>
                    <td><?= date('M j, Y', strtotime($payroll['pay_date'])) ?></td>
                    <td class="currency">₱<?= number_format($payroll['gross_pay'], 2) ?></td>
                    <td class="text-danger">₱<?= number_format($payroll['deductions'], 2) ?></td>
                    <td class="currency fw-bold">₱<?= number_format($payroll['net_pay'], 2) ?></td>
                    <td>
                      <span class="badge bg-success"><?= $payroll['status'] ?></span>
                    </td>
                    <td>
                      <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-primary" title="View Details">
                          <i class="fas fa-eye"></i>
                        </button>
                        <button class="btn btn-outline-success" title="Download">
                          <i class="fas fa-download"></i>
                        </button>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- YTD Summary -->
        <div class="payroll-card animate-slide-up">
          <h4 class="text-primary mb-4">
            <i class="fas fa-chart-line me-2"></i>
            Year-to-Date Summary (2025)
          </h4>
          
          <div class="row g-4">
            <div class="col-md-3">
              <div class="text-center p-3 bg-success bg-opacity-10 rounded-3">
                <div class="h4 text-success mb-1">₱<?= number_format($ytd_summary['gross_earnings'], 0) ?></div>
                <small class="text-muted">Gross Earnings</small>
              </div>
            </div>
            <div class="col-md-3">
              <div class="text-center p-3 bg-danger bg-opacity-10 rounded-3">
                <div class="h4 text-danger mb-1">₱<?= number_format($ytd_summary['total_deductions'], 0) ?></div>
                <small class="text-muted">Total Deductions</small>
              </div>
            </div>
            <div class="col-md-3">
              <div class="text-center p-3 bg-primary bg-opacity-10 rounded-3">
                <div class="h4 text-primary mb-1">₱<?= number_format($ytd_summary['net_earnings'], 0) ?></div>
                <small class="text-muted">Net Earnings</small>
              </div>
            </div>
            <div class="col-md-3">
              <div class="text-center p-3 bg-warning bg-opacity-10 rounded-3">
                <div class="h4 text-warning mb-1">₱<?= number_format($ytd_summary['tax_withheld'], 0) ?></div>
                <small class="text-muted">Tax Withheld</small>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Sidebar -->
      <div class="col-lg-4">
        <!-- Employee Info -->
        <div class="payroll-card animate-slide-up">
          <h5 class="text-primary mb-3">
            <i class="fas fa-user me-2"></i>
            Employee Information
          </h5>
          
          <div class="mb-3">
            <strong>Employee ID:</strong><br>
            <?= htmlspecialchars($employee['employee_id']) ?>
          </div>
          
          <div class="mb-3">
            <strong>Hire Date:</strong><br>
            <?= !empty($employee['hire_date']) ? date('F j, Y', strtotime($employee['hire_date'])) : 'N/A' ?>
          </div>
          
          <div class="mb-3">
            <strong>Annual Salary:</strong><br>
            <span class="currency fs-5">₱<?= number_format($employee['annual_salary'] ?? 0, 2) ?></span>
          </div>
          
          <div class="mb-3">
            <strong>Pay Frequency:</strong><br>
            Bi-monthly (2x per month)
          </div>
        </div>

        <!-- Tax Documents -->
        <div class="payroll-card animate-slide-up">
          <h5 class="text-primary mb-3">
            <i class="fas fa-file-alt me-2"></i>
            Tax Documents
          </h5>
          
          <?php foreach ($tax_documents as $doc): ?>
            <div class="d-flex justify-content-between align-items-center mb-3 p-3 bg-light rounded">
              <div>
                <div class="fw-semibold"><?= htmlspecialchars($doc['type']) ?></div>
                <small class="text-muted">Year: <?= $doc['year'] ?></small>
              </div>
              <div>
                <span class="badge bg-<?= $doc['status'] === 'Available' ? 'success' : 'info' ?> me-2">
                  <?= $doc['status'] ?>
                </span>
                <button class="btn btn-outline-primary btn-sm">
                  <i class="fas fa-download"></i>
                </button>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <!-- Government Contributions -->
        <div class="payroll-card animate-slide-up">
          <h5 class="text-primary mb-3">
            <i class="fas fa-landmark me-2"></i>
            YTD Contributions
          </h5>
          
          <div class="mb-3">
            <div class="d-flex justify-content-between">
              <span>SSS</span>
              <span class="currency">₱<?= number_format($ytd_summary['sss_contributions'], 2) ?></span>
            </div>
          </div>
          
          <div class="mb-3">
            <div class="d-flex justify-content-between">
              <span>PhilHealth</span>
              <span class="currency">₱<?= number_format($ytd_summary['philhealth_contributions'], 2) ?></span>
            </div>
          </div>
          
          <div class="mb-3">
            <div class="d-flex justify-content-between">
              <span>Pag-IBIG</span>
              <span class="currency">₱<?= number_format($ytd_summary['pagibig_contributions'], 2) ?></span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      // Add animation delays to cards
      const cards = document.querySelectorAll('.animate-slide-up');
      cards.forEach((card, index) => {
        card.style.animationDelay = `${index * 0.1}s`;
      });

      // Add click handlers for download buttons
      document.querySelectorAll('.btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
          if (this.textContent.includes('Download')) {
            e.preventDefault();
            
            // Create toast notification
            const toast = document.createElement('div');
            toast.className = 'toast align-items-center text-white bg-success border-0 position-fixed top-0 end-0 m-3';
            toast.style.zIndex = '9999';
            toast.innerHTML = `
              <div class="d-flex">
                <div class="toast-body">
                  <i class="fas fa-download me-2"></i>
                  Document download will be available soon!
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
              </div>
            `;
            
            document.body.appendChild(toast);
            const bsToast = new bootstrap.Toast(toast);
            bsToast.show();
            
            // Remove toast after it's hidden
            toast.addEventListener('hidden.bs.toast', () => {
              toast.remove();
            });
          }
        });
      });
    });
  </script>

</body>
</html>


