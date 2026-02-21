<?php
include "db.php";
session_start();

$userId = intval($_SESSION['user']['id']);

$data = [
    "pending" => 0,
    "approved" => 0,
    "denied" => 0
];

$res = $conn->query("SELECT status, COUNT(*) as total FROM appointments WHERE user_id=$userId GROUP BY status");
while ($row = $res->fetch_assoc()) {
    $status = strtolower($row['status']);
    $data[$status] = (int)$row['total'];
}

echo json_encode($data);
?>
