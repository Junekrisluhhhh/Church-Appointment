<?php
session_start();
include "db.php";

if (!isset($_SESSION['user'])) {
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION['user']['id'];

// ✅ Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Fetch current user password
    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($hashed_password);
    $stmt->fetch();
    $stmt->close();

    // Validate current password
    if (!password_verify($current_password, $hashed_password)) {
        $_SESSION['error'] = "Current password is incorrect.";
        header("Location: dashboard.php");
        exit;
    }

    // Check if new password matches confirmation
    if ($new_password !== $confirm_password) {
        $_SESSION['error'] = "New passwords do not match.";
        header("Location: dashboard.php");
        exit;
    }

    // Hash and update new password
    $new_hashed = password_hash($new_password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
    $stmt->bind_param("si", $new_hashed, $user_id);

    if ($stmt->execute()) {
        $_SESSION['success'] = "Password updated successfully.";
    } else {
        $_SESSION['error'] = "Failed to update password.";
    }

    $stmt->close();
    header("Location: dashboard.php");
    exit;
} else {
    header("Location: dashboard.php");
    exit;
}
?>
