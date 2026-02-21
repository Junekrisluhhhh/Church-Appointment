<?php
session_start();
include "db.php";

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'staff') {
    header("Location: index.php");
    exit;
}

$staff_id = $_SESSION['user']['id'];
$user_id  = intval($_POST['user_id']);

// Determine message type
$msg_type = $_POST['message'] ?? '';
$custom_msg = trim($_POST['custom_message'] ?? '');

// Define preset messages
$messages = [
    1 => "This is a message from the staff of the Archdiocesan Shrine of St. Therese of the Child Jesus, Lahug, Cebu City.

Thank you for choosing our shrine. To confirm your booking, a 50% deposit is required within 24 hours. Failure to make this payment will result in the cancellation of your reservation.

Thank you for your understanding, and God bless.",

    2 => "This is a message from the staff of the Archdiocesan Shrine of St. Therese of the Child Jesus, Lahug, Cebu City.

Thank you for choosing our shrine. To confirm your booking, a 50% deposit is required within 24 hours. Failure to make this payment will result in the cancellation of your reservation.

Thank you for your understanding, and God bless.",

    3 => "This is a kind reminder regarding your upcoming booking at the Archdiocesan Shrine of St. Therese of the Child Jesus, Lahug, Cebu City.

We appreciate your downpayment and are pleased to confirm your reservation. To ensure a smooth and pleasant experience, please take note of the following:

Kindly arrive on time for your scheduled visit.

If you need to make any changes or have special requests, feel free to contact us in advance.

Should you require further assistance, our staff is always ready to help.

Thank you for choosing our shrine. We look forward to welcoming you and serving you soon.

God bless.",

    4 => "This is a reminder that your requested certificate is ready for pickup.

Please note that the certificate can be collected **only between 1:00 PM and 3:00 PM** at the parish office.

Thank you and God bless."
];

// Determine which message to send
if ($msg_type === 'custom' && !empty($custom_msg)) {
    $message_to_send = $custom_msg;
} elseif (isset($messages[intval($msg_type)])) {
    $message_to_send = $messages[intval($msg_type)];
} else {
    $_SESSION['error'] = "Invalid message type or empty custom message.";
    header("Location: staff_dashboard.php");
    exit;
}

// Insert into notifications table
$stmt = $conn->prepare("INSERT INTO notifications (user_id, staff_id, message) VALUES (?, ?, ?)");
$stmt->bind_param("iis", $user_id, $staff_id, $message_to_send);
$stmt->execute();
$stmt->close();

$_SESSION['success'] = "Notification sent successfully.";
header("Location: staff_dashboard.php");
exit;
?>
