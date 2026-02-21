<?php
session_start();
include "db.php";

// --- Only staff OR admin can update ---
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['staff','admin'])) {
    header("Location: index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['appointment_id'], $_POST['payment_status'])) {
    $appointmentId  = intval($_POST['appointment_id']);
    $paymentStatus  = trim($_POST['payment_status']);

    // ✅ Secure allowed values
    $allowed = ['Not Paid', 'Half Paid', 'Full Paid'];
    if (!in_array($paymentStatus, $allowed)) {
        die("Invalid payment status");
    }

    // ✅ Update appointments table
    $stmt = $conn->prepare("UPDATE appointments SET payment_status=? WHERE id=?");
    $stmt->bind_param("si", $paymentStatus, $appointmentId);

    if ($stmt->execute()) {
        // redirect back depending on role
        if ($_SESSION['user']['role'] === 'staff') {
            header("Location: staff_dashboard.php?msg=updated");
        } else {
            header("Location: admin_dashboard.php?msg=updated");
        }
        exit;
    } else {
        echo "❌ Error updating payment status: " . $stmt->error;
    }
}
?>
