<?php
session_start();
include "db.php";

// Check if the user is logged in and has admin rights
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

// Get the slot ID from the URL
$id = intval($_GET['id']);

// Fetch the slot details from the available_slots table
$slotQuery = $conn->query("SELECT * FROM available_slots WHERE id = $id AND is_deleted = 0");

if ($slotQuery && $slotQuery->num_rows > 0) {
    $slot = $slotQuery->fetch_assoc();

    // Insert the slot into the trash bin (slot_trash table)
    $stmt = $conn->prepare("INSERT INTO slot_trash (slot_datetime, is_booked, created_at) VALUES (?, ?, NOW())");
    $stmt->bind_param("si", $slot['slot_datetime'], $slot['is_booked']);
    $stmt->execute();
    $stmt->close();

    // Mark the slot as deleted in the available_slots table (soft delete)
    $conn->query("UPDATE available_slots SET is_deleted = 1 WHERE id = $id");

    // Now check if there are any appointments associated with this slot
    $appointmentsQuery = $conn->query("SELECT id, assigned_slot FROM appointments WHERE assigned_slot = '{$slot['slot_datetime']}' AND is_deleted = 0");

    while ($appointment = $appointmentsQuery->fetch_assoc()) {
        // Set each associated appointment as deleted
        $conn->query("UPDATE appointments SET is_deleted = 1 WHERE id = {$appointment['id']}");

        // Mark the assigned slot as available again
        $conn->query("UPDATE available_slots SET is_booked = 0 WHERE slot_datetime = '{$appointment['assigned_slot']}'");
    }
}

// Redirect back to the admin dashboard after processing
header("Location: admin_dashboard.php");
exit;
?>
