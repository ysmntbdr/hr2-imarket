<?php
// Enhanced Claims Management System
require_once 'auth_check.php';

$pdo = getPDO();
$employee_id = getCurrentEmployeeId();

// Get claim categories from database
$stmt = $pdo->query("SELECT * FROM claim_categories ORDER BY name");
$claim_categories = $stmt->fetchAll();

// Get claims history for current employee
$stmt = $pdo->prepare("
    SELECT c.*, cc.name as category_name 
    FROM claims c 
    JOIN claim_categories cc ON c.category_id = cc.id 
    WHERE c.employee_id = ? 
    ORDER BY c.claim_date DESC
");
$stmt->execute([$employee_id]);
$claims_history = $stmt->fetchAll();

// Calculate monthly statistics
$stmt = $pdo->prepare("
    SELECT 
        SUM(amount) as total_claimed,
        SUM(CASE WHEN status = 'approved' THEN amount ELSE 0 END) as approved_amount,
        SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) as pending_amount,
        SUM(CASE WHEN status = 'rejected' THEN amount ELSE 0 END) as rejected_amount
    FROM claims 
    WHERE employee_id = ? 
    AND MONTH(claim_date) = MONTH(CURRENT_DATE()) 
    AND YEAR(claim_date) = YEAR(CURRENT_DATE())
");
$stmt->execute([$employee_id]);
$monthly_stats = $stmt->fetch() ?: [
    'total_claimed' => 0,
    'approved_amount' => 0,
    'pending_amount' => 0,
    'rejected_amount' => 0
];

$message = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category_id = $_POST['category_id'] ?? '';
    $amount = $_POST['amount'] ?? '';
    $expense_date = $_POST['expense_date'] ?? '';
    $description = $_POST['description'] ?? '';
    
    if (empty($category_id) || empty($amount) || empty($expense_date) || empty($description)) {
        $error = "All required fields must be filled.";
    } elseif (!is_numeric($amount) || $amount <= 0) {
        $error = "Please enter a valid amount.";
    } else {
        // Validate amount against category maximum
        $stmt = $pdo->prepare("SELECT max_amount FROM claim_categories WHERE id = ?");
        $stmt->execute([$category_id]);
        $category = $stmt->fetch();
        
        if ($category && $amount > $category['max_amount']) {
            $error = "Amount exceeds maximum allowed for this category (₱" . number_format($category['max_amount'], 2) . ").";
        } else {
            // Generate claim number
            $stmt = $pdo->query("SELECT COUNT(*) + 1 as next_number FROM claims WHERE YEAR(claim_date) = YEAR(CURRENT_DATE())");
            $next_number = $stmt->fetch()['next_number'];
            $claim_number = 'CLM-' . date('Y') . '-' . str_pad($next_number, 3, '0', STR_PAD_LEFT);
            
            // Insert new claim
            $stmt = $pdo->prepare("
                INSERT INTO claims (employee_id, category_id, claim_number, amount, expense_date, description, status, claim_date) 
                VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())
            ");
            
            if ($stmt->execute([$employee_id, $category_id, $claim_number, $amount, $expense_date, $description])) {
                $message = "Claim submitted successfully! Claim Number: $claim_number";
                // Refresh data
                header("Location: " . $_SERVER['PHP_SELF'] . "?success=1");
                exit;
            } else {
                $error = "Failed to submit claim. Please try again.";
            }
        }
    }
}

// Check for success message
if (isset($_GET['success'])) {
    $message = "Claim submitted successfully!";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Claims & Reimbursement</title>
  
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

    .claims-header {
      background: linear-gradient(135deg, var(--primary-color), #94dcf4);
      color: white;
      border-radius: 20px;
      padding: 2rem;
      margin-bottom: 2rem;
      box-shadow: 0 8px 25px rgba(75, 197, 236, 0.3);
    }

    .claims-card {
      background: white;
      border-radius: 15px;
      padding: 2rem;
      box-shadow: 0 4px 15px rgba(0,0,0,0.08);
      border: none;
      margin-bottom: 2rem;
      transition: transform 0.2s, box-shadow 0.2s;
    }

    .claims-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    }

    .stat-card {
      background: white;
      border-radius: 15px;
      padding: 1.5rem;
      box-shadow: 0 4px 15px rgba(0,0,0,0.08);
      border: none;
      text-align: center;
      transition: transform 0.2s;
      height: 100%;
    }

    .stat-card:hover {
      transform: translateY(-3px);
    }

    .stat-value {
      font-size: 2rem;
      font-weight: 700;
      margin-bottom: 0.5rem;
    }

    .stat-label {
      color: #6c757d;
      font-size: 0.9rem;
      font-weight: 500;
    }

    .category-card {
      background: white;
      border-radius: 12px;
      padding: 1.5rem;
      box-shadow: 0 2px 10px rgba(0,0,0,0.08);
      border-left: 4px solid var(--primary-color);
      transition: all 0.3s;
      cursor: pointer;
      height: 100%;
    }

    .category-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 15px rgba(0,0,0,0.15);
    }

    .form-control:focus, .form-select:focus {
      border-color: var(--primary-color);
      box-shadow: 0 0 0 0.2rem rgba(75, 197, 236, 0.25);
    }

    .btn-primary {
      background-color: var(--primary-color);
      border-color: var(--primary-color);
    }

    .btn-primary:hover {
      background-color: var(--primary-dark);
      border-color: var(--primary-dark);
    }

    .status-badge {
      padding: 0.5rem 1rem;
      border-radius: 25px;
      font-size: 0.8rem;
      font-weight: 600;
      text-transform: uppercase;
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

    .tip-card {
      border-left: 4px solid;
      border-radius: 8px;
      padding: 1.5rem;
      margin-bottom: 1rem;
    }
  </style>
</head>
<body class="bg-light">
  <?php include 'includes/sidebar.php'; ?>
  
  <main class="main-content">
    <div class="container-fluid p-4">
    <!-- Claims Header -->
    <div class="claims-header animate-slide-up text-center">
      <h1 class="mb-2">
        <i class="fas fa-money-bill-wave me-3"></i>
        Claims & Reimbursement
      </h1>
      <p class="mb-0 opacity-75">Submit and track your expense reimbursement claims</p>
    </div>

    <!-- Messages -->
    <?php if ($message): ?>
      <div class="alert alert-success border-0 rounded-4 animate-slide-up" role="alert">
        <i class="fas fa-check-circle me-2"></i>
        <?= htmlspecialchars($message) ?>
      </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
      <div class="alert alert-danger border-0 rounded-4 animate-slide-up" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i>
        <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <!-- Claims Statistics -->
    <div class="row g-4 mb-4">
      <div class="col-xl-3 col-md-6">
        <div class="stat-card animate-slide-up" style="animation-delay: 0.1s;">
          <div class="stat-value text-primary">₱<?= number_format($monthly_stats['total_claimed'] ?? 0, 2) ?></div>
          <div class="stat-label">Total Claimed This Month</div>
        </div>
      </div>
      <div class="col-xl-3 col-md-6">
        <div class="stat-card animate-slide-up" style="animation-delay: 0.2s;">
          <div class="stat-value text-success">₱<?= number_format($monthly_stats['approved_amount'] ?? 0, 2) ?></div>
          <div class="stat-label">Approved Amount</div>
        </div>
      </div>
      <div class="col-xl-3 col-md-6">
        <div class="stat-card animate-slide-up" style="animation-delay: 0.3s;">
          <div class="stat-value text-warning">₱<?= number_format($monthly_stats['pending_amount'] ?? 0, 2) ?></div>
          <div class="stat-label">Pending Approval</div>
        </div>
      </div>
      <div class="col-xl-3 col-md-6">
        <div class="stat-card animate-slide-up" style="animation-delay: 0.4s;">
          <div class="stat-value text-danger">₱<?= number_format($monthly_stats['rejected_amount'] ?? 0, 2) ?></div>
          <div class="stat-label">Rejected Amount</div>
        </div>
      </div>
    </div>

    <!-- Main Content -->
    <div class="row g-4">
      <!-- Submit New Claim -->
      <div class="col-lg-8">
        <div class="claims-card animate-slide-up">
          <h4 class="text-primary mb-4">
            <i class="fas fa-plus-circle me-2"></i>
            Submit New Claim
          </h4>
          
          <form method="POST" enctype="multipart/form-data">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label fw-semibold">Claim Category</label>
                <select class="form-select" name="category_id" required>
                  <option value="">Select category</option>
                  <?php foreach ($claim_categories as $category): ?>
                    <option value="<?= $category['id'] ?>" data-max="<?= $category['max_amount'] ?>">
                      <?= htmlspecialchars($category['name']) ?> (Max: ₱<?= number_format($category['max_amount'], 2) ?>)
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="col-md-6">
                <label class="form-label fw-semibold">Amount</label>
                <div class="input-group">
                  <span class="input-group-text">₱</span>
                  <input type="number" class="form-control" name="amount" step="0.01" min="0.01" placeholder="0.00" required>
                </div>
                <small class="text-muted" id="amount-limit"></small>
              </div>

              <div class="col-md-6">
                <label class="form-label fw-semibold">Expense Date</label>
                <input type="date" class="form-control" name="expense_date" required max="<?= date('Y-m-d') ?>">
              </div>

              <div class="col-md-6">
                <label class="form-label fw-semibold">Receipt/Invoice</label>
                <input type="file" class="form-control" name="receipt" accept=".jpg,.jpeg,.png,.pdf">
                <small class="text-muted">Supported: JPG, PNG, PDF (Max 5MB)</small>
              </div>

              <div class="col-12">
                <label class="form-label fw-semibold">Description</label>
                <textarea class="form-control" name="description" rows="4" placeholder="Provide details about the expense..." required></textarea>
              </div>

              <div class="col-12">
                <button type="submit" class="btn btn-primary btn-lg">
                  <i class="fas fa-paper-plane me-2"></i>Submit Claim
                </button>
              </div>
            </div>
          </form>
        </div>
      </div>

      <!-- Claim Categories -->
      <div class="col-lg-4">
        <div class="claims-card animate-slide-up">
          <h5 class="text-primary mb-3">
            <i class="fas fa-tags me-2"></i>
            Claim Categories
          </h5>
          
          <div class="row g-3">
            <?php foreach (array_slice($claim_categories, 0, 4) as $category): ?>
              <div class="col-12">
                <div class="category-card">
                  <h6 class="text-primary mb-2"><?= htmlspecialchars($category['name']) ?></h6>
                  <div class="fw-bold text-success mb-1">Max: ₱<?= number_format($category['max_amount'], 2) ?></div>
                  <small class="text-muted">
                    <?= $category['requires_receipt'] ? 'Receipt Required' : 'Receipt Optional' ?>
                  </small>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- Claims History -->
    <div class="claims-card animate-slide-up mt-4">
      <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="text-primary mb-0">
          <i class="fas fa-history me-2"></i>
          Claims History
        </h4>
        <div class="btn-group" role="group">
          <button type="button" class="btn btn-outline-primary btn-sm active">All</button>
          <button type="button" class="btn btn-outline-primary btn-sm">Pending</button>
          <button type="button" class="btn btn-outline-primary btn-sm">Approved</button>
        </div>
      </div>
      
      <div class="table-responsive">
        <table class="table table-hover">
          <thead class="table-light">
            <tr>
              <th>Claim Number</th>
              <th>Category</th>
              <th>Amount</th>
              <th>Date</th>
              <th>Status</th>
              <th>Description</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($claims_history as $claim): ?>
              <tr>
                <td><span class="badge bg-light text-dark"><?= htmlspecialchars($claim['claim_number']) ?></span></td>
                <td><?= htmlspecialchars($claim['category_name']) ?></td>
                <td class="fw-bold">₱<?= number_format($claim['amount'], 2) ?></td>
                <td><?= date('M j, Y', strtotime($claim['expense_date'])) ?></td>
                <td>
                  <span class="status-badge bg-<?= $claim['status'] === 'approved' ? 'success' : ($claim['status'] === 'pending' ? 'warning' : 'danger') ?>">
                    <?= ucfirst($claim['status']) ?>
                  </span>
                </td>
                <td>
                  <span class="text-truncate d-inline-block" style="max-width: 200px;" title="<?= htmlspecialchars($claim['description']) ?>">
                    <?= htmlspecialchars(substr($claim['description'], 0, 50)) ?><?= strlen($claim['description']) > 50 ? '...' : '' ?>
                  </span>
                </td>
                <td>
                  <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-secondary" title="View Details" data-bs-toggle="modal" data-bs-target="#claimModal<?= $claim['id'] ?>">
                      <i class="fas fa-eye"></i>
                    </button>
                    <?php if ($claim['status'] === 'pending'): ?>
                      <button class="btn btn-outline-danger" title="Cancel">
                        <i class="fas fa-times"></i>
                      </button>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Claim Submission Tips -->
    <div class="claims-card animate-slide-up mt-4">
      <h4 class="text-primary mb-4">
        <i class="fas fa-lightbulb me-2"></i>
        Claim Submission Tips
      </h4>
      
      <div class="row g-4">
        <div class="col-md-4">
          <div class="tip-card bg-primary bg-opacity-10 border-primary">
            <h6 class="text-primary mb-2">
              <i class="fas fa-receipt me-2"></i>Keep Your Receipts
            </h6>
            <p class="mb-0 small">Always keep original receipts for expenses over ₱25. Digital copies are acceptable for smaller amounts.</p>
          </div>
        </div>
        <div class="col-md-4">
          <div class="tip-card bg-warning bg-opacity-10 border-warning">
            <h6 class="text-warning mb-2">
              <i class="fas fa-clock me-2"></i>Submit Within 30 Days
            </h6>
            <p class="mb-0 small">Submit your claims within 30 days of the expense date for faster processing.</p>
          </div>
        </div>
        <div class="col-md-4">
          <div class="tip-card bg-success bg-opacity-10 border-success">
            <h6 class="text-success mb-2">
              <i class="fas fa-info-circle me-2"></i>Provide Details
            </h6>
            <p class="mb-0 small">Include clear descriptions and business justification for all expenses.</p>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Claim Detail Modals -->
  <?php foreach ($claims_history as $claim): ?>
    <div class="modal fade" id="claimModal<?= $claim['id'] ?>" tabindex="-1">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Claim Details - <?= htmlspecialchars($claim['claim_number']) ?></h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="row g-3">
              <div class="col-sm-6">
                <strong>Category:</strong><br>
                <?= htmlspecialchars($claim['category_name']) ?>
              </div>
              <div class="col-sm-6">
                <strong>Amount:</strong><br>
                ₱<?= number_format($claim['amount'], 2) ?>
              </div>
              <div class="col-sm-6">
                <strong>Date:</strong><br>
                <?= date('F j, Y', strtotime($claim['expense_date'])) ?>
              </div>
              <div class="col-sm-6">
                <strong>Status:</strong><br>
                <span class="status-badge bg-<?= $claim['status'] === 'approved' ? 'success' : ($claim['status'] === 'pending' ? 'warning' : 'danger') ?>">
                  <?= ucfirst($claim['status']) ?>
                </span>
              </div>
              <div class="col-12">
                <strong>Description:</strong><br>
                <?= htmlspecialchars($claim['description']) ?>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          </div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      // Auto-update max amount when category changes
      const categorySelect = document.querySelector('select[name="category_id"]');
      const amountInput = document.querySelector('input[name="amount"]');
      const amountLimit = document.getElementById('amount-limit');
      
      categorySelect?.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        const maxAmount = selectedOption.dataset.max;
        
        if (maxAmount) {
          amountInput.max = maxAmount;
          amountLimit.textContent = `Maximum allowed: ₱${parseFloat(maxAmount).toLocaleString()}`;
        } else {
          amountLimit.textContent = '';
        }
      });

      // Add animation delays to cards
      const cards = document.querySelectorAll('.animate-slide-up');
      cards.forEach((card, index) => {
        card.style.animationDelay = `${index * 0.1}s`;
      });

      // Filter functionality for claims history
      document.querySelectorAll('.btn-group .btn').forEach(btn => {
        btn.addEventListener('click', function() {
          // Remove active class from all buttons
          document.querySelectorAll('.btn-group .btn').forEach(b => b.classList.remove('active'));
          // Add active class to clicked button
          this.classList.add('active');
          
          // Here you would implement the actual filtering logic
          console.log('Filter by:', this.textContent.trim());
        });
      });

      // Form validation
      const claimForm = document.querySelector('form[method="POST"]');
      claimForm?.addEventListener('submit', function(e) {
        const amount = parseFloat(amountInput.value);
        const maxAmount = parseFloat(amountInput.max);
        
        if (maxAmount && amount > maxAmount) {
          e.preventDefault();
          alert(`Amount cannot exceed ₱${maxAmount.toLocaleString()}`);
        }
      });
    });
  </script>

</body>
</html>


