<?php
require_once 'admin_auth.php';

// Set JSON response header
header('Content-Type: application/json');

// Check if user is logged in
if (!isAdminLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Debug logging
error_log("Update status request: " . print_r($input, true));

if (!$input || !isset($input['type']) || !isset($input['id']) || !isset($input['status'])) {
    error_log("Invalid request data: " . print_r($input, true));
    echo json_encode(['success' => false, 'message' => 'Invalid request data']);
    exit;
}

$type = $input['type'];
$id = (int)$input['id'];
$status = $input['status'];
$current_user = getCurrentEmployee();

// Validate status
if (!in_array($status, ['approved', 'rejected'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit;
}

try {
    $pdo = getAdminPDO();
    if (!$pdo) {
        throw new Exception("Database connection failed");
    }
    
    switch ($type) {
        case 'leave':
            // Update leave request status
            $stmt = $pdo->prepare("UPDATE leaves SET status = ? WHERE id = ?");
            $result = $stmt->execute([$status, $id]);
            
            if ($result) {
                // Get leave details for logging
                $stmt = $pdo->prepare("
                    SELECT l.*, e.full_name, e.email 
                    FROM leaves l 
                    JOIN employees e ON l.employee_id = e.id 
                    WHERE l.id = ?
                ");
                $stmt->execute([$id]);
                $leave = $stmt->fetch();
                
                if ($leave) {
                    // Log the action
                    logAdminActivity('leave_' . $status, "Leave request {$status} for {$leave['full_name']} (ID: {$id})");
                    
                    echo json_encode([
                        'success' => true, 
                        'message' => "Leave request {$status} successfully",
                        'employee' => $leave['full_name']
                    ]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Leave request not found']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to update leave request']);
            }
            break;
            
        case 'claim':
            // Update expense claim status
            $stmt = $pdo->prepare("UPDATE claims SET status = ? WHERE id = ?");
            $result = $stmt->execute([$status, $id]);
            
            if ($result) {
                // Get claim details for logging
                $stmt = $pdo->prepare("
                    SELECT c.*, e.full_name, e.email 
                    FROM claims c 
                    JOIN employees e ON c.employee_id = e.id 
                    WHERE c.id = ?
                ");
                $stmt->execute([$id]);
                $claim = $stmt->fetch();
                
                if ($claim) {
                    // Log the action
                    $amount = number_format($claim['amount'] ?? 0, 2);
                    logAdminActivity('claim_' . $status, "Expense claim {$status} for {$claim['full_name']} - ₱{$amount} (ID: {$id})");
                    
                    echo json_encode([
                        'success' => true, 
                        'message' => "Expense claim {$status} successfully",
                        'employee' => $claim['full_name'],
                        'amount' => $amount
                    ]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Expense claim not found']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to update expense claim']);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid request type']);
            break;
    }
    
} catch (Exception $e) {
    error_log("Update status error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?>
