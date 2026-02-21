<?php
include "db.php";
date_default_timezone_set("Asia/Manila");

$check = $conn->query("
    UPDATE appointments
    SET approved = 0
    WHERE approved IS NULL
    AND TIMESTAMPDIFF(HOUR, created_at, NOW()) >= 48
");
echo "OK";
?>
