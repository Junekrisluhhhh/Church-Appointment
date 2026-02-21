<?php
session_start();
include "db.php";

// Check if the user is logged in and has admin privileges
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

$adminId = $_SESSION['user']['id'] ?? null;

// small helper to log actions
function log_action($conn, $adminId, $action_type, $target_table, $target_id) {
    $ts = date('Y-m-d H:i:s');
    $stmtLog = $conn->prepare("INSERT INTO action_history (admin_id, action_type, target_table, target_id, timestamp) VALUES (?, ?, ?, ?, ?)");
    if ($stmtLog) {
        $stmtLog->bind_param("issis", $adminId, $action_type, $target_table, $target_id, $ts);
        $stmtLog->execute();
        $stmtLog->close();
    }
}

// Handle restore or permanent delete for slots only
if (isset($_GET['action'], $_GET['type'], $_GET['id'])) {
    $id = intval($_GET['id']);
    $type = $_GET['type']; // only "slot" supported now

    if ($type === 'slot') {
        if ($_GET['action'] === 'restore') {
            $stmt = $conn->prepare("UPDATE available_slots SET is_deleted = 0 WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();

            if ($adminId) log_action($conn, $adminId, 'restore', 'available_slots', $id);
        } elseif ($_GET['action'] === 'delete_permanent') {
            // Delete linked services first
            $stmt = $conn->prepare("DELETE FROM slot_services WHERE slot_id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();

            // Delete slot itself
            $stmt2 = $conn->prepare("DELETE FROM available_slots WHERE id = ?");
            $stmt2->bind_param("i", $id);
            $stmt2->execute();
            $stmt2->close();

            if ($adminId) log_action($conn, $adminId, 'delete_permanent', 'available_slots', $id);
        }
    }

    // Redirect to trash bin after action
    // Redirect to trash bin after action
    header("Location: trash_bin.php");
    exit;
}

// Fetch deleted slots only
$slotSql = "SELECT * FROM available_slots WHERE is_deleted = 1 ORDER BY slot_datetime ASC";
$slotRes = $conn->query($slotSql);

// Fetch services linked to each slot (for deleted slots)
$slotServices = [];
$deletedSlotsData = [];
$slotIds = [];
if ($slotRes && $slotRes->num_rows > 0) {
    while ($slot = $slotRes->fetch_assoc()) {
        $slotIds[] = $slot['id'];
        $deletedSlotsData[$slot['id']] = $slot;
    }
    if (!empty($slotIds)) {
        $ids = implode(",", $slotIds);
        $servicesRes = $conn->query("SELECT slot_id, service_type FROM slot_services WHERE slot_id IN ($ids)");
        if ($servicesRes) {
            while ($srv = $servicesRes->fetch_assoc()) {
                $slotServices[$srv['slot_id']][] = $srv['service_type'];
            }
        }
    }
}
?>



<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Trash Bin - Admin Panel</title>
    <style>
body {
    position: relative;
    color: #DFD0B8;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    margin: 0;
    padding: 0;
    z-index: 1;
    overflow: auto;
}

body::before {
    content: "";
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: url("../image/st therese.jpg") no-repeat center center fixed;
    background-size: cover;
    filter: blur(6px);
    transform: scale(1.1);
    z-index: -1;
}

/* ===================== CONTAINER ===================== */
.container {
    max-width: 1100px;
    margin: 60px auto;
    background-color: #393E46;
    padding: 30px;
    border-radius: 10px;
    box-shadow: 0 0 15px rgba(0,0,0,0.2);
}

h2 {
    text-align: center;
    margin-bottom: 30px;
    color: #00b894;
}

h3 {
    margin-top: 40px;
    margin-bottom: 20px;
    border-bottom: 2px solid #00b894;
    padding-bottom: 8px;
}

/* ===================== TABLE ===================== */
table {
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
}

th, td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid #948979;
    vertical-align: top;
}

th {
    background-color: #222831;
    color: #DFD0B8;
}

td {
    background-color: #393E46;
    color: #DFD0B8;
}

/* ===================== BUTTONS & LINKS ===================== */
a {
    color: #DFD0B8;
    text-decoration: none;
    font-weight: bold;
    padding: 6px 10px;
    border-radius: 6px;
    border: none;
    display: inline-block;
    transition: all 0.3s ease;
    cursor: pointer;
}

/* Back link */
.back-link {
    display: inline-block;
    margin-bottom: 20px;
    font-weight: bold;
    text-decoration: none;
    color: #fff;
    background-color: #00b894;
    padding: 8px 15px;
    border-radius: 6px;
    border: none;
    transition: background 0.3s ease, transform 0.2s ease;
}

.back-link:hover {
    background-color: #019870;
    transform: scale(1.05);
}

/* Restore button */
.restore-link {
    background-color: #00b894;
    color: #fff;
    border: none;
}

.restore-link:hover {
    background-color: #019870;
    transform: scale(1.05);
}

/* Delete button */
.delete-link {
    background-color: #d63031;
    color: #fff;
    border: none;
}

.delete-link:hover {
    background-color: #b02223;
    transform: scale(1.05);
}

/* ===================== SERVICES LIST ===================== */
.services-list {
    font-size: 12px;
    color: #DFD0B8;
    background: #222831;
    padding: 6px;
    border-radius: 5px;
    max-width: 180px;
}

body {
    position: relative;
    color: #DFD0B8;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    margin: 0;
    padding: 0;
    z-index: 1;
    overflow: auto;
}

body::before {
    content: "";
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: url("../image/st therese.jpg") no-repeat center center fixed;
    background-size: cover;
    filter: blur(6px);
    transform: scale(1.1);
    z-index: -1;
}

/* ===================== CONTAINER ===================== */
.container {
    max-width: 1100px;
    margin: 60px auto;
    background-color: #393E46;
    padding: 30px;
    border-radius: 10px;
    box-shadow: 0 0 15px rgba(0,0,0,0.2);
}

h2 {
    text-align: center;
    margin-bottom: 30px;
    color: #00b894;
}

h3 {
    margin-top: 40px;
    margin-bottom: 20px;
    border-bottom: 2px solid #00b894;
    padding-bottom: 8px;
}

/* ===================== TABLE ===================== */
table {
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
}

th, td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid #948979;
    vertical-align: top;
}

th {
    background-color: #222831;
    color: #DFD0B8;
}

td {
    background-color: #393E46;
    color: #DFD0B8;
}

/* ===================== BUTTONS & LINKS ===================== */
a {
    color: #DFD0B8;
    text-decoration: none;
    font-weight: bold;
    padding: 6px 10px;
    border-radius: 6px;
    border: none;
    display: inline-block;
    transition: all 0.3s ease;
    cursor: pointer;
}

/* Back link */
.back-link {
    display: inline-block;
    margin-bottom: 20px;
    font-weight: bold;
    text-decoration: none;
    color: #fff;
    background-color: #00b894;
    padding: 8px 15px;
    border-radius: 6px;
    border: none;
    transition: background 0.3s ease, transform 0.2s ease;
}

.back-link:hover {
    background-color: #019870;
    transform: scale(1.05);
}

/* Restore button */
.restore-link {
    background-color: #00b894;
    color: #fff;
    border: none;
}

.restore-link:hover {
    background-color: #019870;
    transform: scale(1.05);
}

/* Delete button */
.delete-link {
    background-color: #d63031;
    color: #fff;
    border: none;
}

.delete-link:hover {
    background-color: #b02223;
    transform: scale(1.05);
}

/* ===================== SERVICES LIST ===================== */
.services-list {
    font-size: 12px;
    color: #DFD0B8;
    background: #222831;
    padding: 6px;
    border-radius: 5px;
    max-width: 180px;
}

    </style>
</head>
<body>

<div class="container">
    <h2>Trash Bin - Deleted Available Slots</h2>
    <a class="back-link" href="admin_dashboard.php">&larr; Back to Admin Panel</a>

    <!-- Deleted Slots -->
    <h3>Deleted Available Slots</h3>
    <?php if (!empty($slotIds)): ?>
        <table>
            <tr>
                <th>Slot Date & Time</th>
                <th>Services</th>
                <th>Actions</th>
            </tr>
            <?php foreach ($deletedSlotsData as $slot): ?>
                <tr>
                    <td><?= htmlspecialchars($slot['slot_datetime']) ?></td>
                    <td>
                        <div class="services-list">
                            <?php
                            if (isset($slotServices[$slot['id']])) {
                                echo implode("<br>", array_map('htmlspecialchars', $slotServices[$slot['id']])); 
                            } else {
                                echo '<em>None</em>';
                            }
                            ?>
                        </div>
                    </td>
                    <td>
                        <a class="restore-link" href="?action=restore&type=slot&id=<?= $slot['id'] ?>" onclick="return confirm('Restore this slot?');">Restore</a>
                        <a class="delete-link" href="?action=delete_permanent&type=slot&id=<?= $slot['id'] ?>" onclick="return confirm('Permanently delete this slot? This cannot be undone.');">Delete Permanently</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php else: ?>
        <p>No deleted available slots.</p>
    <?php endif; ?>
</div>

</body>
</html>