<?php
session_start();
include "db.php";

// Allow admin and staff
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['admin','staff'])) {
    header("Location: index.php");
    exit;
}

$redirect = $_SERVER['HTTP_REFERER'] ?? (($_SESSION['user']['role'] === 'admin') ? 'admin_dashboard.php' : 'staff_dashboard.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $slot = intval($_POST['slot_id'] ?? 0);
    $appointment_id = intval($_POST['appointment_id'] ?? 0);

    if ($slot <= 0 || $appointment_id <= 0) {
        $_SESSION['flash_error'] = "Invalid slot or appointment.";
        header("Location: $redirect");
        exit;
    }

    // Start transaction to avoid race conditions
    $conn->begin_transaction();
    try {
        // Lock the appointment row and get current assigned slot and user
        $stmt = $conn->prepare("SELECT user_id, assigned_slot FROM appointments WHERE id = ? FOR UPDATE");
        if (!$stmt) throw new Exception("Prepare failed (appointment): " . $conn->error);
        $stmt->bind_param("i", $appointment_id);
        $stmt->execute();
        $stmt->bind_result($user_id, $prev_slot);
        if (!$stmt->fetch()) {
            $stmt->close();
            throw new Exception("Appointment not found.");
        }
        $stmt->close();

        // If the new slot is the same as the current assigned slot, nothing to do
        if (!empty($prev_slot) && intval($prev_slot) === $slot) {
            $_SESSION['flash_info'] = "This slot is already assigned to the appointment.";
            $conn->commit();
            header("Location: $redirect");
            exit;
        }

        // Lock the target slot row
        $stmt2 = $conn->prepare("SELECT is_booked FROM available_slots WHERE id = ? FOR UPDATE");
        if (!$stmt2) throw new Exception("Prepare failed (slot): " . $conn->error);
        $stmt2->bind_param("i", $slot);
        $stmt2->execute();
        $stmt2->bind_result($is_booked);
        if (!$stmt2->fetch()) {
            $stmt2->close();
            throw new Exception("Selected slot not found.");
        }
        $stmt2->close();

        // If the slot is already booked by someone/something else, disallow
        // (if prev_slot == slot it was short-circuited above)
        if (intval($is_booked) === 1) {
            throw new Exception("Selected slot is already booked.");
        }

        // Free previous slot only if it exists and is different from the new one
        if (!empty($prev_slot) && intval($prev_slot) !== $slot) {
            $stmtFree = $conn->prepare("UPDATE available_slots SET is_booked = 0 WHERE id = ?");
            if (!$stmtFree) throw new Exception("Prepare failed (free prev slot): " . $conn->error);
            $stmtFree->bind_param("i", $prev_slot);
            $stmtFree->execute();
            $stmtFree->close();
        }

        // Assign new slot to appointment
        $stmtAssign = $conn->prepare("UPDATE appointments SET assigned_slot = ? WHERE id = ?");
        if (!$stmtAssign) throw new Exception("Prepare failed (assign appointment): " . $conn->error);
        $stmtAssign->bind_param("ii", $slot, $appointment_id);
        $stmtAssign->execute();
        if ($stmtAssign->affected_rows === 0) {
            $stmtAssign->close();
            throw new Exception("Failed to assign slot to appointment.");
        }
        $stmtAssign->close();

        // Mark new slot as booked
        $stmtBooked = $conn->prepare("UPDATE available_slots SET is_booked = 1 WHERE id = ?");
        if (!$stmtBooked) throw new Exception("Prepare failed (mark booked): " . $conn->error);
        $stmtBooked->bind_param("i", $slot);
        $stmtBooked->execute();
        $stmtBooked->close();

        // Get slot datetime for notification message (no need to lock again, already FOR UPDATE above)
        $stmtSlot = $conn->prepare("SELECT slot_datetime FROM available_slots WHERE id = ?");
        if (!$stmtSlot) throw new Exception("Prepare failed (fetch slot datetime): " . $conn->error);
        $stmtSlot->bind_param("i", $slot);
        $stmtSlot->execute();
        $stmtSlot->bind_result($slot_datetime);
        $slotDatetimeFetched = $stmtSlot->fetch() ? $slot_datetime : null;
        $stmtSlot->close();

        // Create notification for the user
        if (!empty($user_id)) {
            $friendlyDate = $slotDatetimeFetched ? $slotDatetimeFetched : "the selected time";
            $message = "Your slot has been assigned for {$friendlyDate}.";
            $stmtNotif = $conn->prepare("INSERT INTO notifications (user_id, message, created_at) VALUES (?, ?, NOW())");
            if (!$stmtNotif) throw new Exception("Prepare failed (notification): " . $conn->error);
            $stmtNotif->bind_param("is", $user_id, $message);
            $stmtNotif->execute();
            $stmtNotif->close();
        }

        $conn->commit();
        $_SESSION['flash_success'] = "Slot assigned successfully.";
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['flash_error'] = "Error assigning slot: " . $e->getMessage();
    }
}

header("Location: $redirect");
exit;
?>