<?php
include "db.php";

// Handle GET request (old approve links)
if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $conn->query("UPDATE appointments SET approved=1 WHERE id=$id");
    header("Location: admin_dashboard.php");
    exit;
}

// Handle POST request (approve/deny form)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['appointment_id'], $_POST['action'])) {
    $appointment_id = intval($_POST['appointment_id']);
    $action = $_POST['action'];

    if ($action === 'approve') {
        $conn->query("UPDATE appointments SET status='approved' WHERE id=$appointment_id");
    } elseif ($action === 'deny') {
        $conn->query("UPDATE appointments SET status='denied' WHERE id=$appointment_id");
    }

    header("Location: admin_dashboard.php");
    exit;
}
