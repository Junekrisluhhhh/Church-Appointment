<?php
$host = 'localhost';
$db = 'appointment_system';
$user = 'root';
$pass = ''; // change if using password

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
