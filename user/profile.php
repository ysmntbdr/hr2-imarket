<?php
// Enhanced profile with comprehensive employee data
require_once 'auth_check.php';

$pdo = getPDO();
$employee_id = getCurrentEmployeeId();
$current_user = getCurrentEmployee();

// Demo data for enhanced profile
$user = [
    'id' => 1,
    'employee_id' => 'EMP001',
    'full_name' => 'John Doe',
    'first_name' => 'John',
    'last_name' => 'Doe',
    'email' => 'john.doe@company.com',
    'username' => 'jdoe',
    'role' => 'employee',
    'department' => 'IT',
    'position' => 'Software Developer',
    'hire_date' => '2023-01-15',
    'birth_date' => '1990-05-20',
    'phone' => '+1234567890',
    'address' => '123 Main Street, City, State 12345',
    'emergency_contact_name' => 'Jane Doe',
    'emergency_contact_phone' => '+1234567891',
    'salary' => 75000.00,
    'status' => 'active',
    'manager_name' => 'Alice Smith',
    'created_at' => '2023-01-15',
    'profile_picture' => 'https://cdn-icons-png.flaticon.com/512/3135/3135715.png'
];

$message = '';
$error = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_basic') {
        // In real implementation, validate and update database
        $message = "Basic information updated successfully!";
    } elseif ($action === 'update_contact') {
        $message = "Contact information updated successfully!";
    } elseif ($action === 'change_password') {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error = "All password fields are required.";
        } elseif ($new_password !== $confirm_password) {
            $error = "New passwords do not match.";
        } else {
            $message = "Password changed successfully!";
        }
    }
}

// Calculate years of service
$hire_date = new DateTime($user['hire_date']);
$today = new DateTime();
$years_service = $hire_date->diff($today)->y;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Employee Profile</title>
  
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

    .profile-header {
      background: linear-gradient(135deg, var(--primary-color), #94dcf4);
      color: white;
      border-radius: 20px;
      padding: 2rem;
      margin-bottom: 2rem;
      box-shadow: 0 8px 25px rgba(75, 197, 236, 0.3);
    }

    .profile-avatar {
      width: 120px;
      height: 120px;
      border-radius: 50%;
      border: 5px solid white;
      box-shadow: 0 4px 15px rgba(0,0,0,0.2);
    }

    .profile-card {
      background: white;
      border-radius: 15px;
      padding: 2rem;
      box-shadow: 0 4px 15px rgba(0,0,0,0.08);
      border: none;
      margin-bottom: 2rem;
      transition: transform 0.2s, box-shadow 0.2s;
    }

    .profile-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    }

    .stat-badge {
      background: var(--primary-color);
      color: white;
      padding: 0.5rem 1rem;
      border-radius: 25px;
      font-size: 0.9rem;
      font-weight: 600;
      display: inline-block;
      margin: 0.25rem;
    }

    .info-item {
      padding: 1rem 0;
      border-bottom: 1px solid #f1f3f4;
    }

    .info-item:last-child {
      border-bottom: none;
    }

    .info-label {
      font-weight: 600;
      color: #6c757d;
      font-size: 0.9rem;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      margin-bottom: 0.5rem;
    }

    .info-value {
      color: #2c3e50;
      font-size: 1rem;
    }

    .nav-pills .nav-link {
      border-radius: 25px;
      padding: 0.75rem 1.5rem;
      font-weight: 500;
      transition: all 0.2s;
    }

    .nav-pills .nav-link.active {
      background-color: var(--primary-color);
      border-color: var(--primary-color);
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

    .section-title {
      color: var(--primary-color);
      font-weight: 600;
      margin-bottom: 1.5rem;
      display: flex;
      align-items: center;
      gap: 0.5rem;
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

    .progress-circle {
      width: 80px;
      height: 80px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: bold;
      color: white;
      font-size: 1.2rem;
    }

    .alert-custom {
      border: none;
      border-radius: 15px;
      padding: 1rem 1.5rem;
    }
  </style>
</head>
<body class="bg-light">
  <div class="container-fluid p-4">
    <!-- Profile Header -->
    <div class="profile-header animate-slide-up">
      <div class="row align-items-center w-100">
        <div class="col-md-3 text-center">
          <img src="<?= htmlspecialchars($user['profile_picture']) ?>" alt="Profile" class="profile-avatar">
        </div>
        <div class="col-md-6">
          <h1 class="mb-2"><?= htmlspecialchars($user['full_name']) ?></h1>
          <p class="mb-1 opacity-75 fs-5"><?= htmlspecialchars($user['position']) ?></p>
          <p class="mb-3 opacity-75"><?= htmlspecialchars($user['department']) ?> • Employee ID: <?= htmlspecialchars($user['employee_id']) ?></p>
          <div class="d-flex flex-wrap gap-2">
            <span class="stat-badge">
              <i class="fas fa-calendar-alt me-1"></i>
              <?= $years_service ?> Years Service
            </span>
            <span class="stat-badge">
              <i class="fas fa-check-circle me-1"></i>
              <?= ucfirst($user['status']) ?>
            </span>
          </div>
        </div>
        <div class="col-md-3 text-center">
          <div class="progress-circle bg-white bg-opacity-25">
            <div class="text-center">
              <div class="fs-4 fw-bold">$<?= number_format($user['salary']/1000, 0) ?>K</div>
              <small>Annual</small>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Messages -->
    <?php if ($message): ?>
      <div class="alert alert-success alert-custom animate-slide-up" role="alert">
        <i class="fas fa-check-circle me-2"></i>
        <?= htmlspecialchars($message) ?>
      </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
      <div class="alert alert-danger alert-custom animate-slide-up" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i>
        <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <div class="row g-4">
      <!-- Personal Information -->
      <div class="col-lg-8">
        <div class="profile-card animate-slide-up">
          <h4 class="section-title">
            <i class="fas fa-user"></i>
            Personal Information
          </h4>
          
          <!-- Navigation Tabs -->
          <ul class="nav nav-pills mb-4" id="personalTabs" role="tablist">
            <li class="nav-item" role="presentation">
              <button class="nav-link active" id="view-tab" data-bs-toggle="pill" data-bs-target="#view-pane" type="button" role="tab">
                <i class="fas fa-eye me-2"></i>View Details
              </button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" id="edit-tab" data-bs-toggle="pill" data-bs-target="#edit-pane" type="button" role="tab">
                <i class="fas fa-edit me-2"></i>Edit Information
              </button>
            </li>
          </ul>

          <!-- Tab Content -->
          <div class="tab-content" id="personalTabContent">
            <!-- View Tab -->
            <div class="tab-pane fade show active" id="view-pane" role="tabpanel">
              <div class="row g-4">
                <div class="col-md-6">
                  <div class="info-item">
                    <div class="info-label">Full Name</div>
                    <div class="info-value"><?= htmlspecialchars($user['full_name']) ?></div>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="info-item">
                    <div class="info-label">Email Address</div>
                    <div class="info-value"><?= htmlspecialchars($user['email']) ?></div>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="info-item">
                    <div class="info-label">Phone Number</div>
                    <div class="info-value"><?= htmlspecialchars($user['phone']) ?></div>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="info-item">
                    <div class="info-label">Birth Date</div>
                    <div class="info-value"><?= date('F j, Y', strtotime($user['birth_date'])) ?></div>
                  </div>
                </div>
                <div class="col-12">
                  <div class="info-item">
                    <div class="info-label">Address</div>
                    <div class="info-value"><?= htmlspecialchars($user['address']) ?></div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Edit Tab -->
            <div class="tab-pane fade" id="edit-pane" role="tabpanel">
              <form method="POST">
                <input type="hidden" name="action" value="update_basic">
                <div class="row g-3">
                  <div class="col-md-6">
                    <label class="form-label">First Name</label>
                    <input type="text" class="form-control" name="first_name" value="<?= htmlspecialchars($user['first_name']) ?>" required>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Last Name</label>
                    <input type="text" class="form-control" name="last_name" value="<?= htmlspecialchars($user['last_name']) ?>" required>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Email Address</label>
                    <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Phone Number</label>
                    <input type="tel" class="form-control" name="phone" value="<?= htmlspecialchars($user['phone']) ?>">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Birth Date</label>
                    <input type="date" class="form-control" name="birth_date" value="<?= $user['birth_date'] ?>">
                  </div>
                  <div class="col-12">
                    <label class="form-label">Address</label>
                    <textarea class="form-control" name="address" rows="3"><?= htmlspecialchars($user['address']) ?></textarea>
                  </div>
                  <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                      <i class="fas fa-save me-2"></i>Update Information
                    </button>
                  </div>
                </div>
              </form>
            </div>
          </div>
        </div>

        <!-- Security Settings -->
        <div class="profile-card animate-slide-up">
          <h4 class="section-title">
            <i class="fas fa-shield-alt"></i>
            Security Settings
          </h4>
          <form method="POST">
            <input type="hidden" name="action" value="change_password">
            <div class="row g-3">
              <div class="col-12">
                <label class="form-label">Current Password</label>
                <input type="password" class="form-control" name="current_password" required>
              </div>
              <div class="col-md-6">
                <label class="form-label">New Password</label>
                <input type="password" class="form-control" name="new_password" required>
              </div>
              <div class="col-md-6">
                <label class="form-label">Confirm New Password</label>
                <input type="password" class="form-control" name="confirm_password" required>
              </div>
              <div class="col-12">
                <button type="submit" class="btn btn-warning">
                  <i class="fas fa-key me-2"></i>Change Password
                </button>
              </div>
            </div>
          </form>
        </div>
      </div>

      <!-- Sidebar Information -->
      <div class="col-lg-4">
        <!-- Work Information -->
        <div class="profile-card animate-slide-up">
          <h5 class="section-title">
            <i class="fas fa-briefcase"></i>
            Work Information
          </h5>
          <div class="info-item">
            <div class="info-label">Employee ID</div>
            <div class="info-value"><?= htmlspecialchars($user['employee_id']) ?></div>
          </div>
          <div class="info-item">
            <div class="info-label">Department</div>
            <div class="info-value"><?= htmlspecialchars($user['department']) ?></div>
          </div>
          <div class="info-item">
            <div class="info-label">Position</div>
            <div class="info-value"><?= htmlspecialchars($user['position']) ?></div>
          </div>
          <div class="info-item">
            <div class="info-label">Role</div>
            <div class="info-value">
              <span class="badge bg-primary"><?= ucfirst($user['role']) ?></span>
            </div>
          </div>
          <div class="info-item">
            <div class="info-label">Hire Date</div>
            <div class="info-value"><?= date('F j, Y', strtotime($user['hire_date'])) ?></div>
          </div>
          <div class="info-item">
            <div class="info-label">Manager</div>
            <div class="info-value"><?= htmlspecialchars($user['manager_name']) ?></div>
          </div>
        </div>

        <!-- Emergency Contact -->
        <div class="profile-card animate-slide-up">
          <h5 class="section-title">
            <i class="fas fa-phone-alt"></i>
            Emergency Contact
          </h5>
          
          <!-- Navigation Pills -->
          <ul class="nav nav-pills nav-fill mb-3" id="emergencyTabs" role="tablist">
            <li class="nav-item" role="presentation">
              <button class="nav-link active btn-sm" id="emergency-view-tab" data-bs-toggle="pill" data-bs-target="#emergency-view" type="button" role="tab">View</button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link btn-sm" id="emergency-edit-tab" data-bs-toggle="pill" data-bs-target="#emergency-edit" type="button" role="tab">Edit</button>
            </li>
          </ul>

          <div class="tab-content" id="emergencyTabContent">
            <!-- View Emergency Contact -->
            <div class="tab-pane fade show active" id="emergency-view" role="tabpanel">
              <div class="info-item">
                <div class="info-label">Contact Name</div>
                <div class="info-value"><?= htmlspecialchars($user['emergency_contact_name']) ?></div>
              </div>
              <div class="info-item">
                <div class="info-label">Contact Phone</div>
                <div class="info-value"><?= htmlspecialchars($user['emergency_contact_phone']) ?></div>
              </div>
            </div>

            <!-- Edit Emergency Contact -->
            <div class="tab-pane fade" id="emergency-edit" role="tabpanel">
              <form method="POST">
                <input type="hidden" name="action" value="update_contact">
                <div class="mb-3">
                  <label class="form-label">Contact Name</label>
                  <input type="text" class="form-control" name="emergency_contact_name" value="<?= htmlspecialchars($user['emergency_contact_name']) ?>" required>
                </div>
                <div class="mb-3">
                  <label class="form-label">Contact Phone</label>
                  <input type="tel" class="form-control" name="emergency_contact_phone" value="<?= htmlspecialchars($user['emergency_contact_phone']) ?>" required>
                </div>
                <button type="submit" class="btn btn-success btn-sm w-100">
                  <i class="fas fa-save me-2"></i>Update Contact
                </button>
              </form>
            </div>
          </div>
        </div>

        <!-- Quick Stats -->
        <div class="profile-card animate-slide-up">
          <h5 class="section-title">
            <i class="fas fa-chart-bar"></i>
            Quick Stats
          </h5>
          <div class="row g-3 text-center">
            <div class="col-6">
              <div class="bg-primary bg-opacity-10 rounded-3 p-3">
                <div class="fs-4 fw-bold text-primary"><?= $years_service ?></div>
                <small class="text-muted">Years Service</small>
              </div>
            </div>
            <div class="col-6">
              <div class="bg-success bg-opacity-10 rounded-3 p-3">
                <div class="fs-4 fw-bold text-success">Active</div>
                <small class="text-muted">Status</small>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  
  <script>
    // Form validation and interactions
    document.addEventListener('DOMContentLoaded', function() {
      // Password confirmation validation
      const passwordForm = document.querySelector('form[action*="change_password"]');
      if (passwordForm) {
        passwordForm.addEventListener('submit', function(e) {
          const newPassword = this.querySelector('input[name="new_password"]').value;
          const confirmPassword = this.querySelector('input[name="confirm_password"]').value;
          
          if (newPassword !== confirmPassword) {
            e.preventDefault();
            alert('New passwords do not match!');
          }
        });
      }

      // Add animation delays to cards
      const cards = document.querySelectorAll('.animate-slide-up');
      cards.forEach((card, index) => {
        card.style.animationDelay = `${index * 0.1}s`;
      });

      // Success message auto-hide
      const alerts = document.querySelectorAll('.alert');
      alerts.forEach(alert => {
        setTimeout(() => {
          const bsAlert = new bootstrap.Alert(alert);
          bsAlert.close();
        }, 5000);
      });
    });
  </script>

</body>
</html>
