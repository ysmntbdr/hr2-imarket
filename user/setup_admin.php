<?php
// Setup script to create an admin user
require_once 'config.php';

try {
    $pdo = getPDO();
    
    // Check if admin user already exists
    $stmt = $pdo->prepare("SELECT id FROM employees WHERE username = 'admin' OR role = 'admin'");
    $stmt->execute();
    $existing_admin = $stmt->fetch();
    
    if ($existing_admin) {
        echo "<h2>Admin user already exists!</h2>";
        echo "<p>An admin user is already set up in the system.</p>";
    } else {
        // Create admin user
        $stmt = $pdo->prepare("
            INSERT INTO employees (full_name, username, email, phone, department, position, 
                                 hire_date, salary, status, role, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', 'admin', NOW())
        ");
        
        $result = $stmt->execute([
            'System Administrator',
            'admin',
            'admin@company.com',
            '+1234567890',
            'IT',
            'System Administrator',
            date('Y-m-d'),
            100000
        ]);
        
        if ($result) {
            echo "<h2>✅ Admin user created successfully!</h2>";
            echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
            echo "<h3>Admin Login Credentials:</h3>";
            echo "<p><strong>Username:</strong> admin</p>";
            echo "<p><strong>Role:</strong> admin</p>";
            echo "<p><strong>Email:</strong> admin@company.com</p>";
            echo "</div>";
            echo "<p>You can now log in with these credentials to access the admin portal.</p>";
        } else {
            echo "<h2>❌ Error creating admin user</h2>";
        }
    }
    
    // Update existing user role (if needed)
    echo "<hr>";
    echo "<h3>Update Existing User Role</h3>";
    echo "<form method='POST' style='background: #f8f9fa; padding: 20px; border-radius: 5px;'>";
    echo "<p>If you want to make an existing user an admin:</p>";
    echo "<label>Username: <input type='text' name='username' required style='margin-left: 10px; padding: 5px;'></label><br><br>";
    echo "<label>New Role: ";
    echo "<select name='role' style='margin-left: 10px; padding: 5px;'>";
    echo "<option value='admin'>Admin</option>";
    echo "<option value='hr'>HR</option>";
    echo "<option value='manager'>Manager</option>";
    echo "<option value='employee'>Employee</option>";
    echo "</select></label><br><br>";
    echo "<button type='submit' name='update_role' style='background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 5px;'>Update Role</button>";
    echo "</form>";
    
    // Handle role update
    if (isset($_POST['update_role'])) {
        $stmt = $pdo->prepare("UPDATE employees SET role = ? WHERE username = ?");
        $result = $stmt->execute([$_POST['role'], $_POST['username']]);
        
        if ($result) {
            echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
            echo "✅ User role updated successfully!";
            echo "</div>";
        } else {
            echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
            echo "❌ Error updating user role. Please check if the username exists.";
            echo "</div>";
        }
    }
    
} catch (Exception $e) {
    echo "<h2>❌ Database Error</h2>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
    echo "<p>Please make sure your database is set up correctly.</p>";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Setup</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        h2, h3 { color: #333; }
        .back-link { margin-top: 30px; }
        .back-link a { background: #6c757d; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; }
    </style>
</head>
<body>
    <div class="back-link">
        <a href="login.php">← Back to Login</a>
        <a href="hr_admin/dashboard.php" style="margin-left: 10px;">Go to Admin Dashboard</a>
    </div>
</body>
</html>
