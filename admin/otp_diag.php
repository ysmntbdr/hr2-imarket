<?php
require_once 'config.php';

try {
    $pdo = getAdminPDO();
    $stmt = $pdo->query("SHOW TABLES LIKE 'otp_codes'");
    $table = $stmt->fetchColumn();
    echo "otp_codes table: " . ($table ? 'FOUND' : 'MISSING') . "\n";

    if ($table) {
        $count = $pdo->query("SELECT COUNT(*) FROM otp_codes")->fetchColumn();
        echo "otp_codes rows: {$count}\n";
    }
} catch (Exception $e) {
    echo "DB error: " . $e->getMessage() . "\n";
}
?>
