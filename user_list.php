<?php
session_start();
include "db.php";

// Only admins allowed
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

$sql = "SELECT id, name, email, password, role, created_at FROM users ORDER BY created_at DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Registered Users</title>
    <style>
        body {
            background-color: #222831;
            color: #DFD0B8;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .container {
            max-width: 1000px;
            margin: 60px auto;
            background-color: #393E46;
            padding: 30px;
            border-radius: 10px;
        }
        h2 {
            text-align: center;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        th, td {
            padding: 10px;
            border-bottom: 1px solid #948979;
            text-align: left;
            word-break: break-word;
        }
        th {
            background-color: #222831;
        }
        td {
            background-color: #393E46;
        }
        a.back-link {
            display: inline-block;
            margin-bottom: 20px;
            background-color: #948979;
            color: #222831;
            padding: 10px 15px;
            text-decoration: none;
            border-radius: 4px;
        }
        a.back-link:hover {
            background-color: #DFD0B8;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Registered Users</h2>
        <a href="admin_dashboard.php" class="back-link">← Back to Admin Dashboard</a>

        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Password</th>
                    <th>Role</th>
                    <th>Registered On</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['id']) ?></td>
                            <td><?= htmlspecialchars($row['name']) ?></td>
                            <td><?= htmlspecialchars($row['email']) ?></td>
                            <td><?= htmlspecialchars($row['password']) ?></td>
                            <td><?= htmlspecialchars($row['role']) ?></td>
                            <td><?= htmlspecialchars($row['created_at']) ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="text-align:center;">No users found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
