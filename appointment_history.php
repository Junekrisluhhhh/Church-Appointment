<?php
session_start();
include "db.php";

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'user') {
    header("Location: index.php");
    exit;
}

$user = $_SESSION['user'];

// Get all user's appointments
$stmt = $conn->prepare("SELECT * FROM appointments WHERE user_id = ? ORDER BY appointment_date DESC");
$stmt->bind_param("i", $user['id']);
$stmt->execute();
$appointments = $stmt->get_result();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>All Appointments History</title>
    <style>
        body {
            background: url("../image/st therese.jpg") no-repeat center center fixed;
            background-size: cover;
            color: #DFD0B8;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 30px;
            margin: 0;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: #35383dbe;
            padding: 25px 30px;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0,0,0,0.4);
        }
        h2, h3 {
            color: #DFD0B8;
        }
        .appointment {
            margin-bottom: 40px;
            padding-bottom: 20px;
            border-bottom: 1px solid #DFD0B8;
        }
        .appointment-info p {
            margin: 4px 0;
            font-size: 1.1rem;
            color: #FFD369;
        }
        .history-list {
            list-style-type: none;
            padding-left: 0;
            max-height: 250px;
            overflow-y: auto;
            margin-top: 10px;
        }
        .history-list li {
            background-color: #222831;
            border-left: 5px solid #DFD0B8;
            margin-bottom: 10px;
            padding: 12px 20px;
            border-radius: 6px;
            transition: background-color 0.3s ease;
            cursor: default;
        }
        .history-list li:hover {
            background-color: #2a2f36;
        }
        .history-action {
            font-weight: 700;
            font-size: 1.05rem;
            margin-bottom: 5px;
            color: #FFD369;
        }
        .history-date {
            font-size: 0.9rem;
            color: #a1a1a1;
            font-style: italic;
        }
        .no-history {
            color: #EF476F;
            font-weight: 600;
            margin: 10px 0;
        }
        .back-link {
            text-align: center;
            margin-top: 30px;
        }
        .back-link a {
            text-decoration: none;
            color: #DFD0B8;
            font-weight: 700;
            font-size: 1rem;
            border: 2px solid #DFD0B8;
            padding: 8px 18px;
            border-radius: 6px;
            transition: background-color 0.3s ease, color 0.3s ease;
        }
        .back-link a:hover {
            background-color: #DFD0B8;
            color: #222831; 
        }
        a.details-link {
            display: inline-block;
            margin-top: 6px;
            font-size: 0.9rem;
            color: #DFD0B8;
            font-weight: 600;
            text-decoration: underline;
        }
        a.details-link:hover {
            color: #FFD369;
        }
    </style>
</head>
<body>
<div class="container">
    <h2>Your Appointment Histories</h2>

    <?php if ($appointments->num_rows === 0): ?>
        <p>No appointments found.</p>
    <?php else: ?>  
        <?php while ($appt = $appointments->fetch_assoc()): ?>
            <div class="appointment">
                <h3>Appointment #<?= htmlspecialchars($appt['id']) ?></h3>
                <div class="appointment-info">
                    <p><strong>Type:</strong> <?= htmlspecialchars($appt['type']) ?></p>
                    <p><strong>Date:</strong> <?= date("F j, Y, g:i A", strtotime($appt['appointment_date'])) ?></p>
                </div>
                <?php
                // Get history for this appointment
                $stmt = $conn->prepare("SELECT * FROM appointment_history WHERE appointment_id = ? ORDER BY action_date DESC");
                $stmt->bind_param("i", $appt['id']);
                $stmt->execute();
                $history = $stmt->get_result();
                ?>
                <?php if ($history->num_rows === 0): ?>
                    <p class="no-history">No history records for this appointment.</p>
                <?php else: ?>
                    <ul class="history-list">
                        <?php while ($row = $history->fetch_assoc()): ?>
                            <li>
                                <div class="history-action"><?= htmlspecialchars($row['action']) ?></div>
                                <div class="history-date"><?= date('F j, Y, g:i A', strtotime($row['action_date'])) ?></div>
                            </li>
                        <?php endwhile; ?>
                    </ul>
                <?php endif; ?>
                <a class="details-link" href="appointment_history.php?id=<?= intval($appt['id']) ?>">View Detailed History</a>
            </div>
        <?php endwhile; ?>
    <?php endif; ?>

    <div class="back-link">
        <a href="dashboard.php">← Back to Dashboard</a>
    </div>
</div>
</body>
</html>
