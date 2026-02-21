<?php
session_start();
include "db.php";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["id"])) {
    $id = intval($_POST["id"]);
    $userId = intval($_SESSION['user']['id']);

    $stmt = $conn->prepare("UPDATE notifications SET status = 1 WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $id, $userId);
    $stmt->execute();
    echo "OK";
}
?>
