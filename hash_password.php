<?php
/**
 * Password Hasher for Admin Account Fix
 * Generates bcrypt hash for password_verify() compatibility
 */

$password = 'admin123';
$hashed = password_hash($password, PASSWORD_DEFAULT);

echo "PASSWORD HASH GENERATOR\n";
echo "=====================\n\n";

echo "Password: admin123\n";
echo "Generated Hash:\n";
echo $hashed . "\n\n";

echo "COPY the hash above, then run this SQL in phpMyAdmin:\n";
echo "=====================================================\n\n";

echo "UPDATE users SET password = '" . addslashes($hashed) . "' WHERE email = 'Admin@example.com';\n\n";

echo "After running the SQL, try logging in with:\n";
echo "Email: Admin@example.com\n";
echo "Password: admin123\n";
?>
