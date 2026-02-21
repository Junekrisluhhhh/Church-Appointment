<?php
session_start();
include "db.php";

// Only allow logged-in users with role 'user'
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'user') {
    header("Location: index.php");
    exit;
}

// Check if ID is provided in URL
if (!isset($_GET['id'])) {
    $_SESSION['error'] = "Invalid appointment ID.";
    header("Location: user_dashboard.php");
    exit;
}

$appointment_id = intval($_GET['id']);
$user_id = $_SESSION['user']['id'];

// Verify the appointment belongs to the logged-in user
$stmt = $conn->prepare("SELECT id FROM appointments WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $appointment_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error'] = "Appointment not found or you do not have permission to cancel it.";
    header("Location: user_dashboard.php");
    exit;
}
$stmt->close();

// Delete related appointment history first (if exists)
$stmt = $conn->prepare("DELETE FROM appointment_history WHERE appointment_id = ?");
$stmt->bind_param("i", $appointment_id);
$stmt->execute();
$stmt->close();

// Delete the appointment itself
$stmt = $conn->prepare("DELETE FROM appointments WHERE id = ?");
$stmt->bind_param("i", $appointment_id);
$stmt->execute();
$stmt->close();

$_SESSION['success'] = "Appointment cancelled successfully.";
header("Location: dashboard.php");
exit;
?>
