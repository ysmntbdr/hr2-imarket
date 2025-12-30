<?php
$entered_password = "admin123"; // your login password
$stored_hash = '$2y$10$869QymhG0lzkgy/iumqlMuykxYmQWeKfC5xsp.MO4v1nNPxjXgEfu'; // the one from your DB

if (password_verify($entered_password, $stored_hash)) {
    echo "✅ Password is valid!";
} else {
    echo "❌ Password does NOT match!";
}
?>
