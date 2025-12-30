<?php
require_once 'config.php';
$pdo = getAdminPDO();
$stmt = $pdo->query("SHOW COLUMNS FROM otp_codes");
$cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
print_r($cols);
?>
