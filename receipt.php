<?php
session_start();
include "db.php";

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

// Try GET first, then fall back to session
$appointment_id = intval($_GET['appointment_id'] ?? 0);
if ($appointment_id <= 0 && isset($_SESSION['last_appointment_id'])) {
    $appointment_id = intval($_SESSION['last_appointment_id']);
}

if ($appointment_id <= 0) {
    echo "<h2>Receipt: appointment id missing</h2>";
    echo "<p>No appointment id provided. Please contact support.</p>";
    exit;
}

// Fetch appointment
$stmt = $conn->prepare("
    SELECT a.*, u.name AS owner_name, u.email AS owner_email
    FROM appointments a
    LEFT JOIN users u ON a.user_id = u.id
    WHERE a.id = ?
    LIMIT 1
");

if (!$stmt) {
    echo "Database error preparing statement: " . htmlspecialchars($conn->error);
    exit;
}
$stmt->bind_param("i", $appointment_id);
$stmt->execute();
$res = $stmt->get_result();
$appt = $res->fetch_assoc();
$stmt->close();

if (!$appt) {
    echo "<h2>Appointment not found</h2>";
    echo "<p>No appointment found with id: <strong>" . htmlspecialchars($appointment_id) . "</strong>.</p>";
    exit;
}

// Check ownership
$currentUserId = intval($_SESSION['user']['id']);
if ($appt['user_id'] != $currentUserId) {
    echo "<h2>Permission denied</h2>";
    echo "<p>You do not have permission to view this receipt.</p>";
    exit;
}

// Calculate prices
$price = floatval($appt['price']);
$downpayment = round($price / 2, 2);
$formattedTotal = number_format($price, 2);
$formattedDown = number_format($downpayment, 2);
$apptDate = $appt['appointment_date'] ?: 'TBA';
$service = htmlspecialchars($appt['type']);
$apptNumber = intval($appt['id']);
$ownerName = htmlspecialchars($appt['owner_name'] ?? ($_SESSION['user']['name'] ?? 'User'));
$ownerEmail = htmlspecialchars($appt['owner_email'] ?? ($_SESSION['user']['email'] ?? ''));

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Official Receipt — Appointment #<?= $apptNumber ?></title>
<style>
/* Background & Fonts */
body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: url("../image/st therese.jpg") no-repeat center center fixed;
    background-size: cover;
    color: #fff;
    margin: 0;
    padding: 20px;
}

body::before {
    content: "";
    position: fixed;
    top: 0; left: 0;
    width: 100%; height: 100%;
    background: rgba(0, 0, 0, 0.4);
    z-index: 0;
    pointer-events: none;
}

/* Container */
.container {
    max-width: 850px;
    margin: 40px auto;
    background: rgba(34,40,49,0.95);
    border-radius: 12px;
    padding: 35px;
    box-shadow: 0 16px 50px rgba(0,0,0,0.3);
    border-top: 8px solid #000000;
    backdrop-filter: blur(10px);
    position: relative;
    z-index: 1;
}

/* Header */
.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
}

.header .title {
    font-size: 28px;
    font-weight: 700;
    color: #ffffff;
}

.header .church {
    font-size: 16px;
    font-weight: 600;
    color: #dfd0b8;
}

.header .small {
    color: #b4a393;
    font-size: 13px;
}

/* Sections */
.section {
    margin: 22px 0;
    padding: 20px 25px;
    background: rgba(45,52,61,0.8);
    border-radius: 10px;
    border-left: 6px solid #000000;
    box-shadow: 0 4px 12px rgba(0,0,0,0.3);
}

.section strong {
    color: #99958c;
}

.total {
    font-size: 19px;
    font-weight: 700;
    color: #ffffff;

    margin-top: 8px;
}

/* Notes */
.note {
    color: #d0e6ff;
    font-size: 14px;
    line-height: 1.7;
}

/* Buttons */
.paybox {
    display: flex;
    gap: 15px;
    margin-top: 25px;
}

.btn {
    padding: 12px 22px;
    border-radius: 6px;
    border: none;
    cursor: pointer;
    font-weight: 600;
    transition: 0.2s;
}

.btn-muted {
    background: #FFB800;
    color: #000;
    font-weight: 600;
}

.btn-muted:hover {
    background: #e6a200;
}

/* Footer */
.footer {
    margin-top: 45px;
    display: flex;
    flex-direction: column;
    font-size: 13px;
    color: #b4a393;
    border-top: 1px solid rgba(255,255,255,0.1);
    padding-top: 12px;
}
/* ===== SMALL SLIDE FOOTER ===== */
.developer-footer {
  background: rgba(0,0,0,0.7);
  border-top: 1px solid rgba(255,255,255,0.15);
  padding: 6px 10px;
  text-align: center;
  font-size: 11px;
}

.footer-title {
  font-size: 10px;
  color: #aaa;
  margin-bottom: 4px;
  letter-spacing: 1px;
  text-transform: uppercase;
}

.developer-carousel {
  overflow: hidden;
  position: relative;
  height: 18px;
}

.carousel-container {
  display: flex;
  transition: transform 0.5s ease;
}

.carousel-slide {
  min-width: 100%;
  text-align: center;
  font-weight: 500;
  color: #f1f1f1;
}

</style>
</head>
<body>
<div class="container">
    <!-- Header -->
    <div class="header">
        <div>
            <div class="title">Official Receipt</div>
            <div class="church">St. Therese Church</div>
            <div class="small">Appointment #: <?= $apptNumber ?> &nbsp; • &nbsp; Scheduled: <?= htmlspecialchars($apptDate) ?></div>
        </div>
        <div style="text-align:right;">
            <div class="small">Issued to</div>
            <div><?= $ownerName ?></div>
            <div class="small"><?= $ownerEmail ?></div>
        </div>
    </div>

    <!-- Service Details -->
    <div class="section">
        <div><strong>Service:</strong> <?= $service ?></div>
        <div><strong>Details:</strong> <?= nl2br(htmlspecialchars($appt['reason'] ?? 'N/A')) ?></div>
    </div>

    <!-- Payment Summary -->
    <div class="section">
        <div class="total">Total Fee: ₱<?= $formattedTotal ?></div>
        <div class="total">Downpayment (50%): ₱<?= $formattedDown ?></div>
    </div>

    <!-- Payment Note -->
    <div class="section note">
        Please pay the 50% downpayment within 48 hours at the parish office to confirm your booking. Failure to pay within 48 hours may result in cancellation of your reservation.
        <ul>
            <li>Payment methods accepted: Cash at parish office.</li>
            <li>Bring the screenshot of the booking to the parish office for confirmation.</li>
            <li>For inquiries, contact the parish office directly.</li>
        </ul>
    </div>

    <!-- Action Buttons -->
    <div class="paybox">
        <button class="btn btn-muted" onclick="window.location.href='dashboard.php'">Back to Dashboard</button>
        <button class="btn btn-muted" onclick="window.location.href='add_appointment.php?success=1&appointment_id=<?= $apptNumber ?>'">Give Feedback</button>
    </div>

    <!-- Footer -->
    <div class="footer">
        <div>Reference: Appointment #<?= $apptNumber ?> — <?= $service ?></div>
        <div>Thank you for booking with St. Therese Church.</div>
    </div>
</div>

<footer class="developer-footer">
    <div class="footer-title">Developed by</div>
    <div class="developer-carousel">
        <div class="carousel-container" id="carouselTrack">
            <div class="carousel-slide">Rivera Stella Grace</div>
            <div class="carousel-slide">Mangyao, June Chrysler L.</div>
            <div class="carousel-slide">Gensis Nina Carla</div>
            <div class="carousel-slide">Clemenia Reynaldo</div>
            <div class="carousel-slide">Gonzales Jessa</div>
            <div class="carousel-slide">Taguik Jessica</div>
        </div>
    </div>
</footer>

<script>
let index = 0;
const track = document.getElementById('carouselTrack');
const total = track.children.length;

setInterval(() => {
  index = (index + 1) % total;
  track.style.transform = `translateX(-${index * 100}%)`;
}, 3000);
</script>

</body>
</html>
