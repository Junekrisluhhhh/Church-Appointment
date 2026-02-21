<?php
session_start();
include "db.php";

// ✅ Allow both admin and staff to add slots
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['admin','staff'])) {
// ✅ Allow both admin and staff to add slots
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['admin','staff'])) {
    header("Location: index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['new_slot'])) {
        $newSlot = $_POST['new_slot'];
        $services = $_POST['services'] ?? [];

        // ✅ Insert new slot into available_slots
        $stmt = $conn->prepare("INSERT INTO available_slots (slot_datetime, is_booked, is_deleted) VALUES (?, 0, 0)");
        // ✅ Insert new slot into available_slots
        $stmt = $conn->prepare("INSERT INTO available_slots (slot_datetime, is_booked, is_deleted) VALUES (?, 0, 0)");
        $stmt->bind_param("s", $newSlot);
        if ($stmt->execute()) {
            $slotId = $stmt->insert_id;

            // ✅ Insert slot services if only 'Blessing' or 'Wedding' selected
            $allowedServices = ['Blessing', 'Wedding'];
            if (!empty($services)) {
                $stmtSrv = $conn->prepare("INSERT INTO slot_services (slot_id, service_type) VALUES (?, ?)");
                foreach ($services as $service) {
                    $service = trim($service);
                    if (in_array($service, $allowedServices)) {
                        $stmtSrv->bind_param("is", $slotId, $service);
                        $stmtSrv->execute();
                    }
                }
            }
        }
    }
}
if ($stmt->execute()) {
    $slotId = $stmt->insert_id;

    // Debug
    error_log("New slot ID: $slotId");

    // ✅ Insert slot services if only 'Blessing' or 'Wedding' selected
    $allowedServices = ['Blessing', 'Wedding'];
    if (!empty($services)) {
        $stmtSrv = $conn->prepare("INSERT INTO slot_services (slot_id, service_type) VALUES (?, ?)");
        foreach ($services as $service) {
            $service = trim($service);
            if (in_array($service, $allowedServices)) {
                $stmtSrv->bind_param("is", $slotId, $service);
                if($stmtSrv->execute()){
                    error_log("Inserted service: $service for slot $slotId");
                } else {
                    error_log("Failed to insert service: $service for slot $slotId");
                }
            }
        }
    }
}


// ✅ Redirect user back to the correct dashboard
if ($_SESSION['user']['role'] === 'staff') {
    header("Location: staff_dashboard.php");
} else {
    header("Location: admin_dashboard.php");
}
exit;
