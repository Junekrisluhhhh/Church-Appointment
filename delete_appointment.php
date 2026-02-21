<?php
include "db.php";
if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $conn->query("DELETE FROM appointments WHERE id=$id");
}
header("Location: admin_dashboard.php");
