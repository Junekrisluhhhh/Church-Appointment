<?php
session_start();
include "db.php";

// --- Staff Authentication ---
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'staff') {
    header("Location: index.php");
    exit;
}

$staffId   = $_SESSION['user']['id'];
$staffName = htmlspecialchars($_SESSION['user']['name']);
$staffEmail = htmlspecialchars($_SESSION['user']['email']);
$message = '';

// --- Handle password change ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current = $_POST['current_password'];
    $new = $_POST['new_password'];
    $confirm = $_POST['confirm_password'];

    $stmt = $conn->prepare("SELECT password FROM users WHERE id=?");
    $stmt->bind_param("i", $staffId);
    $stmt->execute();
    $stmt->bind_result($hash);
    $stmt->fetch();
    $stmt->close();

    $verified = false;
    if ($hash !== null && $hash !== '') {
        // Prefer password_verify for modern hashes
        if (password_verify($current, $hash)) {
            $verified = true;
        } else {
            // Support legacy SHA256 hashes (if any). If matches, allow and re-hash below.
            if (hash('sha256', $current) === $hash) {
                $verified = true;
            }
        }
    }

    if (!$verified) {
        $message = "Current password is incorrect.";
    } elseif ($new !== $confirm) {
        $message = "New password and confirmation do not match.";
    } else {
        // Store new password with password_hash
        $newHash = password_hash($new, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password=? WHERE id=?");
        $stmt->bind_param("si", $newHash, $staffId);
        $stmt->execute();
        $stmt->close();
        $message = "Password updated successfully!";
    }
}

$sql = "SELECT a.id, a.user_id, a.type, a.appointment_date, a.assigned_slot, a.payment_status, u.name,
               s.slot_datetime AS slot_datetime, s.is_booked AS slot_is_booked
        FROM appointments a
        JOIN users u ON a.user_id = u.id
        LEFT JOIN available_slots s ON a.assigned_slot = s.id
        WHERE a.is_deleted = 0
        GROUP BY a.id
        ORDER BY a.appointment_date DESC";
$res = $conn->query($sql);
$appointments = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

// --- Fetch available slots ---
$slotsRes = $conn->query("SELECT * FROM available_slots WHERE is_deleted=0 ORDER BY slot_datetime ASC");
$slots = $slotsRes ? $slotsRes->fetch_all(MYSQLI_ASSOC) : [];

// --- Fetch slot services ---
$slotServices = [];
$slotSrvRes = $conn->query("SELECT slot_id, service_type FROM slot_services");
if ($slotSrvRes) {
    while ($s = $slotSrvRes->fetch_assoc()) {
        $slotServices[$s['slot_id']][] = $s['service_type'];
    }
}

// --- Service Types ---
$serviceTypes = [
    "Wedding","Regular Baptism","Special Baptism",
    "Blessing","Mass Intentions","Pre-Cana Seminar","Certificate Releasing","Funeral","Certificate Requesting"
];
// --- Fetch survey summary ---
$ratingRes = $conn->query("
    SELECT rating, COUNT(*) AS total 
    FROM surveys 
    GROUP BY rating 
    ORDER BY rating ASC
");

$ratingLabels = [];
$ratingData = [];
if ($ratingRes) {
    while ($r = $ratingRes->fetch_assoc()) {
        $ratingLabels[] = $r['rating'];
        $ratingData[] = $r['total'];
    }
}

// Helpful yes/no summary
$helpRes = $conn->query("
    SELECT helpful, COUNT(*) AS total 
    FROM surveys 
    GROUP BY helpful
");

$helpfulLabels = [];
$helpfulData = [];
if ($helpRes) {
    while ($h = $helpRes->fetch_assoc()) {
        $helpfulLabels[] = $h['helpful'];
        $helpfulData[] = $h['total'];
    }
}

// Recent comments (limit 10)
$recentComments = [];
$cRes = $conn->query("
    SELECT s.comments, s.rating, s.helpful, u.name AS user_name, s.created_at 
    FROM surveys s
    JOIN users u ON s.user_id = u.id
    ORDER BY s.created_at DESC
    LIMIT 10
");

if ($cRes) {
    $recentComments = $cRes->fetch_all(MYSQLI_ASSOC);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Staff Dashboard</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>

* { margin:0; padding:0; box-sizing:border-box; font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
body { background: url("../image/st therese.jpg") no-repeat center center fixed; background-size: cover; color:#fff; line-height:1.6; }

.search-bar {
    width: 95%;
    margin: 20px auto;
    text-align: right;
}

.survey-area {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
    justify-content: space-between;
    margin: 30px 0;
}
.survey-card {
    background: linear-gradient(145deg, rgba(34,40,49,0.95), rgba(45,52,61,0.95));
    border-radius: 15px;
    padding: 25px;
    flex: 1 1 300px;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.5);
    color: #fff;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.survey-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 12px 30px rgba(0, 0, 0, 0.7);
}

.survey-card h4 {
    font-size: 1.3rem;
    margin-bottom: 12px;
    border-bottom: 1px solid rgba(255,255,255,0.08);
    padding-bottom: 10px;
}

.chart-card .chart-stat {
    display:flex;
    align-items:center;
    gap:12px;
    margin-bottom:12px;
}
.chart-card .chart-stat .big {
    font-size:28px;
    font-weight:700;
    color:#00b894;
}
.chart-card canvas {
    display: block;
    margin: 0 auto;
    max-width: 100%;
    height: 220px !important;
}

.feedback-card {
    flex: 1 1 100%;
    max-height: 300px;
    overflow-y: auto;
    padding: 20px;
    background: linear-gradient(145deg, rgba(45,52,61,0.9), rgba(34,40,49,0.95));
}

.recent-comments {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.comment-item {
    padding: 12px 15px;
    background: rgba(0,184,148,0.05);
    border-left: 4px solid #00b894;
    border-radius: 10px;
    transition: background 0.3s ease;
}

.comment-item:hover {
    background: rgba(0,184,148,0.12);
}

.comment-text {
    margin-top: 6px;
    color: #ddd;
    line-height: 1.5;
}

.comment-item .small {
    font-size: 12px;
    color: #aaa;
}

/* Responsive */
@media(max-width:768px){
    .survey-area {
        flex-direction: column;
    }

    .feedback-card {
        max-height: 400px;
    }
}

.search-bar input {
    width: 250px;
    padding: 8px 12px;
    border-radius: 6px;
    border: 1px solid #FFB800;; 
    background: rgba(255, 255, 255, 0.9);
    font-size: 14px;
    color: #333;
    transition: all 0.3s ease;
}

.search-bar input:focus {
    outline: none;
    border-color: #FFB800;;
    box-shadow: 0 0 8px rgba(0, 184, 148, 0.7);
}

.search-bar input::placeholder {
    color: #666;
}

/* ===== TOPBAR ===== */
.topbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 20px;
    background: rgba(0,0,0,0.6);
    backdrop-filter: blur(10px);
    gap: 15px;
    position: sticky;
    top: 0;
    z-index: 1000;
    border-bottom: 1px solid rgba(255,255,255,0.2);
}
.topbar .logo { font-weight:700; font-size:1.1rem; }
.topbar button {
    color: #fff;
    background: transparent;
    border: none;
    font-size: 1.2rem;
    cursor: pointer;
    padding: 6px;
    border-radius: 6px;
}

.profile-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.55);
    backdrop-filter: blur(10px);
    justify-content: center;
    align-items: center;
    z-index: 3000;
    padding: 20px;
}
.profile-overlay.open { display:flex; }
.profile-card {
    background: linear-gradient(145deg, #2f3640, #1e2228);
    border: 1px solid #444;
    border-radius: 16px;
    width: 420px;
    max-width: 100%;
    padding: 30px;
    text-align: center;
    color: #dfd0b8;
    box-shadow: 0 12px 30px rgba(0, 0, 0, 0.5);
    animation: fadeIn 0.3s ease-out;
    position: relative;
}
/* Stack items vertically so buttons don't overlap */
.profile-card {
    display: flex;
    flex-direction: column;
    align-items: center;
}
.profile-avatar {
    width:100px;
    height:100px;
    display:flex;
    align-items:center;
    justify-content:center;
    margin:0 auto 15px;
}
.profile-avatar i { font-size:70px; color:#948979; }
.profile-card h3 {
    margin:10px 0 15px;
    font-size:24px;
    color:#fff;
    border-bottom:2px solid #948979;
    padding-bottom:10px;
}
.profile-card .email, .profile-card .profile-info div { font-size:14px; color:#b4a393; }
.profile-card .btn-action {
    background:#948979;
    color:#222831;
    width:100%;
    padding:10px 22px;
    border:none;
    border-radius:8px;
    cursor:pointer;
    font-weight:600;
    font-size:14px;
    transition: background 0.3s ease, transform 0.1s ease;
    margin-top:8px;
}
.profile-card .btn-action:hover { background:#b4a393; transform:scale(1.05); }
.profile-card .logout-btn { background:#ef476f; color:#fff; margin-top:18px; }
.profile-card .logout-btn:hover { background:#d93d5e; transform:scale(1.05); }
.close-btn { position:absolute; top:10px; right:15px; background:none; border:none; font-size:18px; color:#fff; cursor:pointer; }

/* ===== MODAL ===== */
.modal {
    display:none;
    position:fixed;
    inset:0;
    background:rgba(0,0,0,0.8);
    justify-content:center;
    align-items:center;
    z-index:3000;
    padding:20px;
}
.modal.open { display:flex; }
.modal-content {
    background: rgba(34,40,49,0.95);
    padding:30px;
    border-radius:12px;
    width:400px;
    max-width:100%;
    position:relative;
    color:#fff;
    box-shadow:0 12px 30px rgba(0,0,0,0.5);
}
.close-modal { position:absolute; top:10px; right:15px; font-size:1.5rem; cursor:pointer; color:#ffd700; }

/* ===== NAVIGATION ===== */
.admin-nav {
    display:flex;
    gap:12px;
    padding:10px 20px;
    background: rgba(0,0,0,0.5);
    backdrop-filter: blur(8px);
    margin-top:15px;
    border-radius:8px;
}
.admin-nav a, .admin-nav button {
    color:#000;
    text-decoration:none;
    font-weight:600;
    padding:8px 14px;
    border-radius:8px;
    transition: background 0.2s, transform 0.08s;
    background:#FFB800;
    border:none;
    cursor:pointer;
    box-shadow: 0 4px 10px rgba(0,0,0,0.2);
}
.admin-nav a:hover, .admin-nav button:hover { background:#e6a200; transform: translateY(-1px); }

/* ===== CONTAINER ===== */
.container {
    max-width:1400px;
    margin:30px auto 100px;
    background-color: rgba(0,0,0,0.6);
    padding:30px;
    border-radius:12px;
    box-shadow:0 8px 20px rgba(0,0,0,0.4);
    overflow:visible;
    font-size: 16px; /* increase base text size by ~2px */
}

/* ===== TABLES ===== */
table { width:100%; border-collapse:collapse; margin-bottom:40px; color:#fff; background: rgba(34,40,49,0.95); border-radius:12px; overflow:hidden; }
th, td { padding:10px 12px; border-bottom:1px solid #555; text-align:left; }
th { background:#FFB800; color:#000; font-weight:600; }
tr:nth-child(even) { background: rgba(255,255,255,0.05); }
.status span { padding:5px 10px; border-radius:20px; font-weight:500; font-size:0.85rem; }
.pending { background-color:#ff6b6b; color:#fff; }
.approved { background-color:#1dd1a1; color:#fff; }
.denied { background-color:#ee5253; color:#fff; }
.payment-full { color:#1dd1a1; font-weight:bold; }
.payment-half { color:#fcbf49; font-weight:bold; }
.payment-notpaid { color:#ee5253; font-weight:bold; }

/* ===== SCHEDULE DISPLAY ===== */
.schedule-display { margin: 20px 0; }
.schedule-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-top: 20px; }
.schedule-card { background: rgba(148, 137, 121, 0.2); border: 2px solid #948979; border-radius: 12px; padding: 20px; }
.schedule-card h4 { color: #FFB800; margin: 0 0 15px 0; font-size: 18px; }
.schedule-card p { color: #DFD0B8; margin: 8px 0; line-height: 1.6; font-size: 13px; }
.schedule-card strong { color: #FFB800; }

/* ===== RESPONSIVE ===== */
@media(max-width:768px){ .admin-nav{ flex-direction:column; gap:10px; } }
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
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

<!-- TOPBAR -->
<div class="topbar">
    <div style="display:flex; gap:12px; align-items:center;">
        <a href="#appointments" style="color:#000;text-decoration:none;font-weight:600;padding:8px 14px;border-radius:8px;background:#FFB800;border:none;cursor:pointer;box-shadow:0 4px 10px rgba(0,0,0,0.2);">Appointments</a>
        <a href="#schedule" style="color:#000;text-decoration:none;font-weight:600;padding:8px 14px;border-radius:8px;background:#FFB800;border:none;cursor:pointer;box-shadow:0 4px 10px rgba(0,0,0,0.2);">Fixed Schedule</a>
    </div>
    <div><button onclick="toggleProfile()"><i class="fa-solid fa-user-circle fa-lg"></i></button></div>
</div>

<!-- PROFILE MODAL -->
<div class="profile-overlay" id="profileOverlay">
    <div class="profile-card">
        <button class="close-btn" onclick="toggleProfile()">&times;</button>
        <div class="profile-avatar"><i class="fa-solid fa-user-circle"></i></div>
        <h3><?= $staffName ?></h3>
        <div class="email"><?= $staffEmail ?></div>
        <button class="btn-action" onclick="openModal()">Change Password</button>
        <a href="logout.php" class="btn-action logout-btn">Logout</a>
    </div>
</div>

<!-- PASSWORD MODAL -->
<div class="modal" id="passwordModal">
    <div class="modal-content">
        <span class="close-modal" onclick="closeModal()">&times;</span>
        <h3>Change Password</h3>
        <?php if($message): ?>
        <p style="text-align:center;color:<?= strpos($message,'success')!==false?'lightgreen':'#ff6b6b' ?>"><?= $message ?></p>
        <?php endif; ?>
        <form method="post">
            <input type="hidden" name="change_password" value="1">
            <label>Current Password</label>
            <input type="password" name="current_password" required>
            <label>New Password</label>
            <input type="password" name="new_password" required>
            <label>Confirm New Password</label>
            <input type="password" name="confirm_password" required>
            <button type="submit">Update Password</button>
        </form>
    </div>
</div>

<!-- MAIN CONTAINER -->
<!-- MAIN CONTAINER -->
<div class="container">
<h2>Welcome, <?= $staffName ?>!</h2>
<!-- APPOINTMENTS -->
<h3 id="appointments">All Appointments</h3>
<div class="search-bar">
    <input type="text" id="appointmentSearch" placeholder="Search appointments by user, service, or date...">
    <i class="fa fa-search"></i>
</div>

<?php
// at top after your other logic
$survey_notice = '';
if (isset($_GET['survey_submitted']) && $_GET['survey_submitted'] == '1') {
    $survey_notice = "New survey submitted.";
}
?>
<?php if ($survey_notice): ?>
    <div style="background:#00b894;color:#051017;padding:12px;border-radius:8px;margin-bottom:16px;font-weight:700; text-align:center;">
        <?= htmlspecialchars($survey_notice) ?>
    </div>
<?php endif; ?>

<?php if(empty($appointments)): ?>
<p style="text-align:center; color:lightyellow;">No appointments found.</p>
<?php else: ?>
<table id="appointmentsTable">
<thead>
<tr><th>User</th><th>Service</th><th>Requested Date</th><th>Assigned Slot</th><th>Status</th><th>Payment</th><th>Notify</th></tr>
</thead>
<tbody>
<?php foreach($appointments as $row): ?>
<?php
    $paymentStatus = $row['payment_status'] ?? 'Not Paid';
    $appointmentTime = strtotime($row['appointment_date']);
    $now = time();

    // Determine status
    if ($paymentStatus === 'Half Paid' || $paymentStatus === 'Full Paid') {
        $status = 'Proceed';
    } elseif ($now - $appointmentTime >= 172800) { // 48 hours
        $status = 'Denied';
    } else {
        $status = $paymentStatus; // Before 48 hrs and not paid
    }
?>
<tr>
<td><?= htmlspecialchars($row['name']) ?></td>
<td><?= htmlspecialchars($row['type']) ?></td>
<td><?= htmlspecialchars($row['appointment_date']) ?></td>
<td>
<?php if(empty($row['assigned_slot'])): ?>
<form method="post" action="assign_slot.php">
    <input type="hidden" name="appointment_id" value="<?= $row['id'] ?>">
    <select name="slot_id" required>
        <option value="">Select Slot</option>
        <?php foreach($slots as $slot):
            if($slot['is_booked'] == 0): ?>
            <option value="<?= $slot['id'] ?>">
                <?= htmlspecialchars($slot['slot_datetime']) ?>
                (<?= isset($slotServices[$slot['id']]) ? implode(", ", $slotServices[$slot['id']]) : 'No Service' ?>)
            </option>
        <?php endif; endforeach; ?>
    </select>
    <button type="submit">Assign</button>
</form>
<?php else: ?>
<?= htmlspecialchars($row['slot_datetime'] ?: $row['assigned_slot']) ?>
<?php endif; ?>
</td>

<td class="status">
    <span class="<?= strtolower($status) === 'proceed' ? 'approved' : (strtolower($status) === 'denied' ? 'denied' : ($status==='Half Paid'?'payment-half':($status==='Full Paid'?'payment-full':'payment-notpaid'))) ?>">
        <?= $status ?>
    </span>
</td>

<td>
<form method="post" action="update_payment.php">
<input type="hidden" name="appointment_id" value="<?= $row['id'] ?>">
<select name="payment_status" onchange="this.form.submit()" class="<?= $paymentStatus==='Not Paid'?'payment-notpaid':($paymentStatus==='Half Paid'?'payment-half':'payment-full') ?>">
<option value="Not Paid" <?= $paymentStatus==='Not Paid'?'selected':'' ?>>Not Paid</option>
<option value="Half Paid" <?= $paymentStatus==='Half Paid'?'selected':'' ?>>Half Paid</option>
<option value="Full Paid" <?= $paymentStatus==='Full Paid'?'selected':'' ?>>Full Paid</option>
</select>
</form>
</td>
<td>
<form method="post" action="send_notification.php">
    <input type="hidden" name="user_id" value="<?= $row['user_id'] ?>">
    <input type="hidden" name="appointment_id" value="<?= $row['id'] ?>">

    <select name="message" onchange="toggleCustomMessage(this, 'customMessage<?= $row['id'] ?>')">
        <option value="">Choose...</option>
        <option value="1">💰 Payment Reminder</option>
        <option value="2">✅ Booking Confirmation</option>
        <option value="3">📅 Reminder: Upcoming Booking</option>
        <option value="4">🎓 Certificate Ready for Pick-Up</option>
        <option value="custom">Optional</option>
    </select>

    <input type="text" name="custom_message" id="customMessage<?= $row['id'] ?>" placeholder="Enter your message" style="display:none; margin-top:5px; padding:5px; border-radius:5px; border:1px solid #ccc; width:100%;">

    <button type="submit" style="margin-top:5px;">Send</button>
</form>
</td>

</tr>
<?php endforeach; ?>
</tbody>

</table>
<?php endif; ?>

<hr style="margin:40px 0; border-color:#948979;" id="schedule">

<!-- FIXED SCHEDULE DISPLAY -->
<h3>Fixed Service Schedule</h3>
<div class="schedule-display">
    <div class="schedule-grid">
        <div class="schedule-card">
            <h4>Wedding</h4>
            <p><strong>Days:</strong> Monday to Saturday</p>
            <p><strong>Times:</strong> 9:00 AM, 10:30 AM, 1:00 PM, 2:30 PM, 4:00 PM</p>
            <p><strong>Min Advance:</strong> 3 weeks</p>
        </div>
        <div class="schedule-card">
            <h4>Funeral</h4>
            <p><strong>Days:</strong> Monday to Saturday, Sunday (limited)</p>
            <p><strong>Mon-Sat:</strong> 9:00 AM, 10:00 AM, 11:00 AM, 1:00 PM, 2:00 PM, 3:00 PM</p>
            <p><strong>Sunday:</strong> 12:00 PM, 12:45 PM, 1:30 PM</p>
        </div>
        <div class="schedule-card">
            <h4>Blessing</h4>
            <p><strong>Days:</strong> Any day (Mon-Sun)</p>
            <p><strong>Times:</strong> 9:00 AM, 10:00 AM, 11:00 AM, 1:00 PM, 2:00 PM, 3:00 PM, 4:00 PM</p>
            <p><strong>Min Advance:</strong> 3 weeks</p>
        </div>
        <div class="schedule-card">
            <h4>Baptism - Regular</h4>
            <p><strong>Days:</strong> Sundays only</p>
            <p><strong>Times:</strong> 8:30 AM, 9:30 AM, 10:30 AM, 11:30 AM</p>
        </div>
        <div class="schedule-card">
            <h4>Baptism - Special</h4>
            <p><strong>Days:</strong> Any day (Mon-Sun)</p>
            <p><strong>Times:</strong> 9:00 AM, 10:00 AM, 11:00 AM, 2:00 PM, 3:00 PM, 4:00 PM</p>
        </div>
        <div class="schedule-card">
            <h4>Pre-Cana Seminar</h4>
            <p><strong>Days:</strong> 2nd & 4th Saturday only</p>
            <p><strong>Times:</strong> 7:00 AM - 5:00 PM</p>
            <p><strong>Max:</strong> 10 couples per day</p>
        </div>
    </div>
</div>

<!-- Survey summary area (place near top of container) -->
<div class="survey-area">
    <div class="survey-card chart-card">
        <h4>Ratings (All time)</h4>
        <div class="chart-stat" id="avgRatingStat">
            <!-- JS will populate -->
        </div>
        <canvas id="staffRatingChart"></canvas>
    </div>

    <div class="survey-card chart-card">
        <h4>Helpful (Yes / No)</h4>
        <div class="chart-stat" id="helpfulStat">
            <!-- JS will populate -->
        </div>
        <canvas id="staffHelpfulChart"></canvas>
    </div>

    <div class="survey-card feedback-card">
        <h4>Recent Feedback</h4>
        <div class="recent-comments">
            <?php if (empty($recentComments)): ?>
                <p><em>No recent feedback.</em></p>
            <?php else: ?>
                <?php foreach($recentComments as $c): ?>
                    <div class="comment-item">
                        <strong><?= htmlspecialchars($c['user_name']) ?></strong>
                        <div class="small"><?= htmlspecialchars($c['created_at']) ?> • Rating: <?= intval($c['rating']) ?> • Helpful: <?= htmlspecialchars($c['helpful']) ?></div>
                        <div class="comment-text"><?= nl2br(htmlspecialchars($c['comments'])) ?></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>



<script>
    function toggleCustomMessage(select, inputId) {
    const input = document.getElementById(inputId);
    if(select.value === 'custom') {
        input.style.display = 'block';
        input.focus();
    } else {
        input.style.display = 'none';
        input.value = '';
    }
}
const staffRatingLabels = <?= json_encode($ratingLabels) ?>;
const staffRatingData = <?= json_encode($ratingData) ?>;
const staffHelpfulLabels = <?= json_encode($helpfulLabels) ?>;
const staffHelpfulData = <?= json_encode($helpfulData) ?>;

document.addEventListener('DOMContentLoaded', function() {
    const rCtx = document.getElementById('staffRatingChart').getContext('2d');
    const hCtx = document.getElementById('staffHelpfulChart').getContext('2d');

    // Normalize rating data to ensure 1..5 keys exist
    const ratingCounts = { '1':0,'2':0,'3':0,'4':0,'5':0 };
    for(let i=0;i<staffRatingLabels.length;i++){
        const lbl = String(staffRatingLabels[i]);
        ratingCounts[lbl] = Number(staffRatingData[i]) || 0;
    }
    const ratingLabelsFull = ['1','2','3','4','5'];
    const ratingDataFull = ratingLabelsFull.map(l => ratingCounts[l]);

    // Compute totals and average
    const totalRatings = ratingDataFull.reduce((a,b)=>a+b,0);
    const weightedSum = ratingDataFull.reduce((acc, cnt, idx) => acc + cnt * (idx+1), 0); // idx+1 => rating value
    const avgRating = totalRatings ? (weightedSum / totalRatings) : 0;
    // show avg stat
    const avgStatEl = document.getElementById('avgRatingStat');
    avgStatEl.innerHTML = `<div class="big">${avgRating ? avgRating.toFixed(1) : '—'}</div><div style="color:#ddd">Average rating • ${totalRatings} responses</div>`;

    // Colors per rating from red -> green
    const ratingColors = [
        '#ff6b6b', // 1
        '#f39c12', // 2
        '#f1c40f', // 3
        '#2ecc71', // 4
        '#00b894'  // 5
    ];

    // Plugin to draw values and percentage over bars
    const dataLabelsPlugin = {
        id: 'dataLabels',
        afterDatasetsDraw(chart, args, options) {
            const {ctx, data, chartArea: {top, right, bottom, left, width, height}} = chart;
            ctx.save();
            chart.data.datasets.forEach((dataset, dsIndex) => {
                chart.getDatasetMeta(dsIndex).data.forEach((bar, index) => {
                    const value = dataset.data[index];
                    if (value === 0) return; // skip zeros to reduce clutter
                    const x = bar.x;
                    const y = bar.y;
                    ctx.fillStyle = '#fff';
                    ctx.font = '600 12px "Segoe UI", Tahoma, sans-serif';
                    ctx.textAlign = 'center';
                    ctx.textBaseline = 'bottom';
                    // Draw main value
                    ctx.fillText(value, x, y - 6);
                    // Draw percentage below value
                    const total = dataset.data.reduce((a,b)=>a+b,0);
                    const percent = total ? ((value / total) * 100).toFixed(0) + '%' : '';
                    ctx.font = '500 11px "Segoe UI", Tahoma, sans-serif';
                    ctx.fillStyle = 'rgba(255,255,255,0.8)';
                    ctx.fillText(percent, x, y + 10);
                });
            });
            ctx.restore();
        }
    };

    // ===== RATING BAR CHART =====
    const ratingChart = new Chart(rCtx, {
        type: 'bar',
        data: {
            labels: ratingLabelsFull,
            datasets: [{
                label: 'Ratings Count',
                data: ratingDataFull,
                backgroundColor: ratingColors,
                borderColor: '#222',
                borderWidth: 1,
                borderRadius: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: { duration: 900 },
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const total = context.dataset.data.reduce((a,b)=>a+b,0);
                            const percent = total ? ((context.raw/total)*100).toFixed(1) : 0;
                            return ` ${context.label} stars: ${context.raw} (${percent}%)`;
                        }
                    }
                }
            },
            scales: {
                y: { 
                    beginAtZero:true, 
                    ticks:{ color:'#fff', precision:0 },
                    grid: { color: 'rgba(255,255,255,0.06)' }
                },
                x: { 
                    ticks:{ color:'#fff', font:{weight:'700'} },
                    grid: { display:false }
                }
            }
        },
        plugins: [dataLabelsPlugin]
    });

    // ===== HELPFUL DOUGHNUT CHART WITH CENTER TEXT =====
    // Normalize helpful data
    const helpfulCounts = { 'yes':0, 'no':0 };
    for(let i=0;i<staffHelpfulLabels.length;i++){
        const lbl = String(staffHelpfulLabels[i]).toLowerCase();
        helpfulCounts[lbl] = Number(staffHelpfulData[i]) || 0;
    }
    const helpfulDataFull = [helpfulCounts['yes'], helpfulCounts['no']];
    const totalHelpful = helpfulDataFull.reduce((a,b)=>a+b,0);
    const yesPercent = totalHelpful ? Math.round((helpfulDataFull[0]/totalHelpful)*100) : 0;

    const helpfulCenterPlugin = {
        id: 'helpfulCenterText',
        beforeDraw(chart) {
            const {ctx, width, height} = chart;
            ctx.save();
            ctx.font = '600 20px "Segoe UI", Tahoma, sans-serif';
            ctx.fillStyle = '#fff';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            const centerX = width / 2;
            const centerY = height / 2 - 8;
            ctx.fillText(`${yesPercent}%`, centerX, centerY);
            ctx.font = '500 12px "Segoe UI", Tahoma, sans-serif';
            ctx.fillStyle = 'rgba(255,255,255,0.85)';
            ctx.fillText('Recommend', centerX, centerY + 20);
            ctx.restore();
        }
    };

    const helpfulStatEl = document.getElementById('helpfulStat');
    helpfulStatEl.innerHTML = `<div class="big">${totalHelpful ? yesPercent + '%' : '—'}</div><div style="color:#ddd">${totalHelpful} responses • Yes recommend</div>`;

    const helpfulChart = new Chart(hCtx, {
        type: 'doughnut',
        data: {
            labels: ['Yes','No'],
            datasets: [{
                data: helpfulDataFull,
                backgroundColor: ['#00b894','#ff6b6b'],
                borderColor: '#222',
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '65%',
            animation: { animateScale:true, duration:800 },
            plugins: {
                legend: { position:'bottom', labels:{ color:'#fff', font:{size:13} } },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const total = context.dataset.data.reduce((a,b)=>a+b,0);
                            const percent = total ? ((context.raw/total)*100).toFixed(1) : 0;
                            return `${context.label}: ${context.raw} (${percent}%)`;
                        }
                    }
                }
            }
        },
        plugins: [helpfulCenterPlugin]
    });

    // ===== POLLING UPDATE FUNCTION =====
    setInterval(()=> {
        fetch('surveys_api.php', { cache:'no-store' })
            .then(r => r.json())
            .then(json => {
                if(!json) return;
                // update ratings
                const ratingsObj = json.ratings || {};
                // build array for 1..5
                const newRatings = [ (ratingsObj['1']||0), (ratingsObj['2']||0), (ratingsObj['3']||0), (ratingsObj['4']||0), (ratingsObj['5']||0) ];
                ratingChart.data.datasets[0].data = newRatings;
                ratingChart.update();

                // update avg stat
                const total = newRatings.reduce((a,b)=>a+b,0);
                const weighted = newRatings.reduce((acc, cnt, idx) => acc + cnt * (idx+1), 0);
                const avg = total ? (weighted/total) : 0;
                avgStatEl.innerHTML = `<div class="big">${avg ? avg.toFixed(1) : '—'}</div><div style="color:#ddd">${total} responses • Average rating</div>`;

                // update helpful
                const helpfulObj = json.helpful || {};
                const newHelpful = [ (helpfulObj['yes']||0), (helpfulObj['no']||0) ];
                helpfulChart.data.datasets[0].data = newHelpful;
                helpfulChart.update();

                const totalHelp = newHelpful.reduce((a,b)=>a+b,0);
                const yesPct = totalHelp ? Math.round((newHelpful[0]/totalHelp)*100) : 0;
                helpfulStatEl.innerHTML = `<div class="big">${totalHelp ? yesPct + '%' : '—'}</div><div style="color:#ddd">${totalHelp} responses • Yes recommend</div>`;
            }).catch(err => {
                console.warn('Polling error', err);
            });
    }, 30000);

});
</script>

<script>
// Set min datetime to current datetime
const slotInput = document.getElementById('new_slot');
if(slotInput){
    const now = new Date();

    // Format as YYYY-MM-DDTHH:MM
    function formatDateTime(date) {
        const yyyy = date.getFullYear();
        const mm = String(date.getMonth()+1).padStart(2,'0');
        const dd = String(date.getDate()).padStart(2,'0');
        const hh = String(date.getHours()).padStart(2,'0');
        const min = String(date.getMinutes()).padStart(2,'0');
        return `${yyyy}-${mm}-${dd}T${hh}:${min}`;
    }

    // Minimum datetime is now
    slotInput.min = formatDateTime(now);

    // Optional: prevent selecting time outside 9am-2pm on submit
    slotInput.addEventListener('change', function() {
        const dt = new Date(this.value);
        const hours = dt.getHours();
        if (hours < 9 || hours > 14) {
            alert("Please select a time between 9:00 AM and 2:00 PM.");
            this.value = "";
        }
    });
}

// Profile toggle
function toggleProfile() { document.getElementById('profileOverlay').classList.toggle('open'); }

// Password modal
function openModal() { document.getElementById('passwordModal').classList.add('open'); }
function closeModal() { document.getElementById('passwordModal').classList.remove('open'); }
window.addEventListener('click', function(e){ if(e.target === document.getElementById('passwordModal')) closeModal(); });

// Add Slot toggle
function togglePanel(id) {
    const panel = document.getElementById(id);
    panel.classList.toggle('open');
    
    // Optional: Scroll into view when opened
    if (panel.classList.contains('open')) {
        panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}
// Appointment Search Filter
document.getElementById('appointmentSearch').addEventListener('keyup', function() {
    const filter = this.value.toLowerCase();
    const rows = document.querySelectorAll('#appointmentsTable tbody tr'); // target the appointments table

    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(filter) ? '' : 'none';
    });
});
</script>
<footer class="developer-footer">
    <div class="footer-title">Developed by</div>
    <div class="developer-carousel">
        <div class="carousel-container" id="carouselTrack">
            <div class="carousel-slide">Rivera Stella Grace</div>
            <div class="carousel-slide">Mangyao June Chrysler</div>
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