<?php
session_start();
include "db.php";
if (!isset($_SESSION['user'])) exit;

$type = $_POST['type'];
$reason = $_POST['reason'];
$slot_id = intval($_POST['slot_id']);
$user_id = $_SESSION['user']['id'];

$stmt = $conn->prepare("SELECT slot_datetime FROM available_slots WHERE id = ? AND is_booked = 0");
$stmt->bind_param("i", $slot_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 1) {
  $row = $res->fetch_assoc();
  $slot_datetime = $row['slot_datetime'];

  $stmt = $conn->prepare("INSERT INTO appointments (user_id, type, reason, appointment_date, assigned_slot) VALUES (?, ?, ?, ?, ?)");
  $stmt->bind_param("issss", $user_id, $type, $reason, $slot_datetime, $slot_datetime);
  $stmt->execute();

  $stmt = $conn->prepare("UPDATE available_slots SET is_booked = 1 WHERE id = ?");
  $stmt->bind_param("i", $slot_id);
  $stmt->execute();

  header("Location: dashboard.php");
  exit;
}
echo "Slot not available.";
?>
