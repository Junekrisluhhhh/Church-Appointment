<?php
session_start();
include "db.php";

// Redirect if not admin
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

// Fetch action history with admin and affected user (appointment user or target user if applicable)
// CLEANED VERSION: Removed Git merge markers
$sql = "SELECT ah.*, 
               admin.name AS admin_name,
               a.user_id AS appointment_user_id,
               user.name AS appointment_user_name,
               tu.name AS target_user_name
        FROM action_history ah
        LEFT JOIN users admin 
            ON ah.admin_id = admin.id
        LEFT JOIN appointments a 
            ON ah.target_table = 'appointments' AND ah.target_id = a.id
        LEFT JOIN users user 
            ON a.user_id = user.id
        LEFT JOIN users tu
            ON ah.target_table = 'users' AND ah.target_id = tu.id
        ORDER BY ah.timestamp DESC";

$res = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Action History</title>
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
/* ===================== LINKS ===================== */
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
    max-width: 1200px;
    margin: 60px auto;
    background-color: rgba(34,40,49,0.95);
    padding: 30px;
    border-radius: 15px;
    box-shadow: 0 6px 20px rgba(0,0,0,0.4);
    border-top: 4px solid #00b894; /* accent border */
}

/* ===================== HEADINGS ===================== */
h2 {
    text-align: center;
    margin-bottom: 25px;
    font-size: 28px;
    color: #00b894; /* theme accent */
}

/* ===================== LINKS ===================== */
a {
    color: #DFD0B8;
    text-decoration: none;
    font-weight: bold;
    margin-right: 15px;
}

a:hover {
    color: #00b894;
    text-decoration: underline;
}

table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 15px;
    font-size: 14px;
    border-radius: 10px;
    overflow: hidden;
}

th, td {
    padding: 12px;
    text-align: left;
}

th {
    background-color: #00b894; /* green header */
    color: #fff;
    font-weight: bold;
}

tr td {
    background-color: #393E46;
    border-bottom: 1px solid #555;
}

tr:hover td {
    background-color: #2f353d;
    transition: 0.3s;
}

/* ===================== ACTION TYPE BADGES ===================== */
.action-type {
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: bold;
    text-transform: uppercase;
    display: inline-block;
    color: #fff;
}

.action-type.approve { background-color: #00b894; } 
.action-type.deny { background-color: #d63031; }
.action-type.delete { background-color: #ff9800; }
.action-type.create { background-color: #4caf50; }
.action-type.reset_password { background-color: #607d8b; }
.action-type.other { background-color: #607d8b; }
</style>
</head>
<body>

<div class="container">
    <h2>Action History</h2>
    <a class="back-link" href="admin_dashboard.php">Back to Dashboard</a>

    <table>
        <thead>
            <tr>
                <th>Admin Name</th>
                <th>Affected User</th>
                <th>Action Type</th>
                <th>Target Table</th>
                <th>Target ID</th>  
                <th>Timestamp</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $res->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($row['admin_name'] ?? '—') ?></td>
                    <td>
                        <?php
                            // Check for appointment user first, then target user
                            $affected = $row['appointment_user_name'] ?? $row['target_user_name'] ?? '—';
                            echo htmlspecialchars($affected);
                        ?>
                    </td>
                    <td>
                        <?php 
                            $atype = htmlspecialchars($row['action_type']);
                            $base = in_array($row['action_type'], ['approve','deny','delete','create_user','create_appointment','reset_password']) ? $row['action_type'] : 'other';
                            
                            // Map specific create actions to the generic 'create' badge class
                            if ($base === 'create_user' || $base === 'create_appointment') {
                                $base = 'create';
                            }
                        ?>
                        <span class="action-type <?= $base ?>">
                            <?= $atype ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars($row['target_table']) ?></td>
                    <td><?= htmlspecialchars($row['target_id']) ?></td>
                    <td><?= htmlspecialchars($row['timestamp']) ?></td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

</body>
</html>