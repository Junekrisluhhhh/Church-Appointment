<?php
session_start();
include "db.php";

$showReminder = false;
if (!isset($_SESSION['show_reminder'])) {
    // First time visiting dashboard
    $showReminder = true;
    $_SESSION['show_reminder'] = true; // prevent showing again immediately
}

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'user') {
    header("Location: index.php");
    exit;
}

$user = $_SESSION['user'];


// ✅ Handle profile update if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $contact = $_POST['contact'];
    $birthday = $_POST['birthday'];

    $stmt = $conn->prepare("UPDATE users SET name=?, email=?, contact=?, birthday=? WHERE id=?");
    $stmt->bind_param("ssssi", $name, $email, $contact, $birthday, $user['id']);

    if ($stmt->execute()) {
        $_SESSION['user']['name'] = $name;
        $_SESSION['user']['email'] = $email;
        $_SESSION['user']['contact'] = $contact;
        $_SESSION['user']['birthday'] = $birthday;
        $_SESSION['success'] = "Profile updated successfully.";
    } else {
        $_SESSION['error'] = "Failed to update profile.";
    }

    header("Location: dashboard.php");
    exit;
}

// ✅ Fetch appointments and notifications AFTER handling profile update
$res = $conn->query("SELECT * FROM appointments WHERE user_id = " . intval($user['id']) . " ORDER BY appointment_date ASC");
$notifRes = $conn->query("SELECT * FROM notifications 
                          WHERE user_id=" . intval($user['id']) . " 
                          ORDER BY created_at DESC");

$notifCountRes = $conn->query("SELECT COUNT(*) AS cnt 
                               FROM notifications 
                               WHERE user_id=" . intval($user['id']) . " AND status = 0");
$notifCount = $notifCountRes ? $notifCountRes->fetch_assoc()['cnt'] : 0;
$notifRes = $conn->query("SELECT * FROM notifications 
                          WHERE user_id=" . intval($user['id']) . " 
                          ORDER BY created_at DESC");

$notifCountRes = $conn->query("SELECT COUNT(*) AS cnt 
                               FROM notifications 
                               WHERE user_id=" . intval($user['id']) . " AND status = 0");
$notifCount = $notifCountRes ? $notifCountRes->fetch_assoc()['cnt'] : 0;
?>



<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>User Dashboard</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

   <style>
/* === GLOBAL BODY & BACKGROUND === */
body {
  position: relative;
  color: #DFD0B8;
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
  margin: 0;
  padding: 0;
  z-index: 1;
  overflow-x: hidden;
  overflow-y: auto;
}
body::before {
  content: "";
  position: fixed;
  inset: 0;
  background: url("../image/st therese.jpg") no-repeat center center fixed;
  background-size: cover;
  filter: blur(6px);
  transform: scale(1.1);
  z-index: -1;
}

/* === HEADER === */
.header {
  background-color: #393E46;
  color: #DFD0B8;
  padding: 15px 30px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  position: sticky;
  top: 0;
  z-index: 1000;
}
.header-title {
  font-size: 20px;
  font-weight: bold;
}
.nav-links a {
  margin-left: 20px;
  color: #DFD0B8;
  text-decoration: none;
  font-weight: 500;
}
.nav-links a:hover {
  color: #948979;
  text-decoration: underline;
}

/* === CONTAINER & SECTIONS === */
.container {
  max-width: 800px;
  margin: 30px auto;
  background: #393E46;
  padding: 20px 25px;
  border-radius: 8px;
  box-shadow: 0 0 12px rgba(0, 0, 0, 0.15);
}
h2, h3 { margin-top: 0; color: #DFD0B8; }
ul { padding-left: 20px; }
li {
  margin-bottom: 10px;
  background: #222831;
  padding: 10px;
  border-left: 5px solid #948979;
  border-radius: 4px;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

/* === BADGES === */
.badge {
  padding: 3px 8px;
  border-radius: 5px;
  font-size: 12px;
  font-weight: bold;
  color: #222831;
}
.badge-pending { background-color: #FFD166; }
.badge-approved { background-color: #06D6A0; }
.badge-denied { background-color: #EF476F; }

/* === LINKS === */
a { color: #948979; text-decoration: none; font-weight: 500; }
a:hover { text-decoration: underline; }

/* === MENU BAR === */
.menu-bar { position: relative; display: inline-block; }
.menu-button {
  background: none;
  border: none;
  cursor: pointer;
  font-size: 24px;
  width: 30px;
  height: 24px;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
}
.hamburger-icon, .hamburger-icon::before, .hamburger-icon::after {
  content: '';
  display: block;
  width: 30px;
  height: 3px;
  background: #DFD0B8;
  border-radius: 5px;
  transition: all 0.3s ease-in-out;
}
.hamburger-icon::before { transform: translateY(-8px); }
.hamburger-icon::after { transform: translateY(8px); }

.menu-content {
  display: none;
  position: absolute;
  background: #393E46;
  color: #DFD0B8;
  min-width: 160px;
  border-radius: 5px;
  right: 0;
  box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
}
.menu-bar.active .menu-content { display: block; }
.menu-content a {
  color: #DFD0B8;
  padding: 10px 12px;
  display: block;
  font-size: 14px;
}
.menu-content a:hover {
  background: #948979;
  color: #222831;
}

/* === TOP BAR BUTTONS === */
.top-bar {
  display: flex;
  align-items: center;
  margin-bottom: 20px;
  gap: 15px;
  flex-wrap: wrap;
}
.top-bar h2 { margin-right: auto; }
.top-bar a {
  background: #948979;
  color: #222831;
  padding: 8px 15px;
  border-radius: 5px;
  font-weight: bold;
  transition: 0.3s;
}
.top-bar a:hover { background: #b4a393; }

/* === SERVICE BOXES === */
.services-list { display: flex; flex-wrap: wrap; gap: 15px; }
.service-box {
  background: #222831;
  padding: 15px 25px;
  border-radius: 6px;
  color: #DFD0B8;
  font-weight: 600;
  transition: 0.3s;
}
.service-box:hover { background: #948979; color: #222831; }

/* === NOTIFICATION DROPDOWN === */
/* === NOTIFICATION BELL (Balanced Style) === */
.notif-bell {
  position: relative;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  margin-right: 25px;
  vertical-align: middle;
}

.notif-bell button {
  background: none;
  border: none;
  color: #DFD0B8;
  font-size: 20px;
  cursor: pointer;
  padding: 5px;
  transition: transform 0.2s ease, color 0.2s ease;
}

.notif-bell button:hover {
  color: #948979;
  transform: scale(1.1);
}

.notif-count {
  position: absolute;
  top: 3px;
  right: 6px;
  background: #EF476F;
  color: #fff;
  font-size: 11px;
  padding: 2px 5px;
  border-radius: 50%;
  font-weight: bold;
  box-shadow: 0 0 6px rgba(239, 71, 111, 0.8);
}

.notif-dropdown {
  display: none;
  position: absolute;
  top: 35px;
  right: 0;
  background: #2b2f35;
  color: #DFD0B8;
  min-width: 280px;
  max-height: 380px;
  overflow-y: auto;
  border-radius: 8px;
  box-shadow: 0 6px 15px rgba(0, 0, 0, 0.4);
  padding: 8px 0;
  z-index: 1000;
  animation: fadeInNotif 0.25s ease;
}

.notif-dropdown.show {
  display: block;
}

.notif-dropdown ul {
  list-style: none;
  padding: 0;
  margin: 0;
}

.notif-dropdown li {
  border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}
#notifMessage, #notifTime {
    text-align: center;
    margin: 10px 0;
}


.notif-dropdown li:last-child {
  border-bottom: none;
}

.notif-dropdown a {
  display: block;
  padding: 10px 16px;
  color: #DFD0B8;
  text-decoration: none;
  transition: all 0.2s ease;
}

.notif-dropdown a:hover {
  background: #948979;
  color: #222831;
  transform: translateX(3px);
  border-radius: 5px;
}

.notif-dropdown .time {
  display: block;
  font-size: 11px;
  color: #b4a393;
  margin-top: 4px;
}

@keyframes fadeInNotif {
  from { opacity: 0; transform: translateY(-10px); }
  to { opacity: 1; transform: translateY(0); }
}


/* === MODAL (Notifications) === */
.modal {
  display: none;
  position: fixed;
  inset: 0;
  backdrop-filter: blur(6px);
  background: rgba(0,0,0,0.4);
  z-index: 2000;
}
.modal-content {
  background: #222831;
  margin: 8% auto;
  padding: 25px;
  border-radius: 10px;
  width: 420px;
  max-width: 95%;
  color: #DFD0B8;
  box-shadow: 0 8px 20px rgba(0,0,0,0.4);
}

.close-btn {
  float: right;
  font-size: 22px;
  font-weight: bold;
  cursor: pointer;
  color: #DFD0B8;
}
.close-btn:hover { color: #EF476F; }

/* === NOTIFICATION MODAL === */
#notifModal {
  display: none;
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.6); /* Darker backdrop for focus */
  backdrop-filter: blur(8px);
  justify-content: center;
  align-items: center;
  z-index: 2500;
  padding: 20px;
}

/* Modal Box */
#notifModal .modal-content {
  background: #2b2f35; /* Slightly lighter dark for contrast */
  width: 400px;
  max-width: 95%;
  border-radius: 14px;
  padding: 30px 25px;
  color: #DFD0B8;
  box-shadow: 0 12px 28px rgba(0, 0, 0, 0.6);
  animation: slideFadeIn 0.35s ease-out;
  text-align: center;
  position: relative;
}

/* Close Button */
#notifModal .close-btn {
  position: absolute;
  top: 12px;
  right: 18px;
  font-size: 22px;
  font-weight: bold;
  color: #DFD0B8;
  cursor: pointer;
  transition: color 0.2s ease, transform 0.2s ease;
}
#notifModal .close-btn:hover {
  color: #EF476F;
  transform: scale(1.2);
}

/* Modal Heading */
#notifModal h2 {
  font-size: 24px;
  margin-bottom: 20px;
  color: #fff;
  letter-spacing: 0.5px;
  border-bottom: 1px solid #948979;
  padding-bottom: 10px;
}

/* Notification Message */
#notifMessage {
  font-size: 15px;
  line-height: 1.5;
  color: #DFD0B8;
  margin-bottom: 15px;
  word-wrap: break-word;
}

/* Notification Time */
#notifTime {
  font-size: 12px;
  color: #b4a393;
  letter-spacing: 0.5px;
}

/* Smooth fade/slide animation */
@keyframes slideFadeIn {
  from {
    opacity: 0;
    transform: translateY(-15px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

/* Optional: add subtle hover effect to modal itself */
#notifModal .modal-content:hover {
  box-shadow: 0 16px 32px rgba(0, 0, 0, 0.65);
}


/* === PROFILE BUTTON (Header Icon) === */
.profile-menu { position: relative; display: inline-block; }
.profile-button {
  background: none;
  border: none;
  cursor: pointer;
  width: 42px;
  height: 42px;
  border-radius: 50%;
  overflow: hidden;
  transition: 0.3s;
}
.profile-button i {
  font-size: 36px;
  color: #DFD0B8;
  transition: color 0.3s ease;
}
.profile-button:hover { transform: scale(1.1); }
.profile-button:hover i { color: #948979; }
.profile-button::after {
  content: "";
  position: absolute;
  top: -4px; left: -4px;
  width: 50px; height: 50px;
  border: 2px solid #948979;
  border-radius: 50%;
  opacity: 0;
  transition: opacity 0.3s ease;
}
.profile-button:hover::after { opacity: 1; }

/* === PROFILE OVERLAY (MODAL) === */
.profile-overlay {
  display: none;
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.55);
  backdrop-filter: blur(10px);
  justify-content: center;
  align-items: center;
  z-index: 3000;
}
.profile-card {
  background: linear-gradient(145deg, #2f3640, #1e2228);
  border: 1px solid #444;
  border-radius: 16px;
  width: 420px;
  max-width: 95%;
  padding: 35px 40px;
  text-align: center;
  color: #dfd0b8;
  box-shadow: 0 12px 30px rgba(0, 0, 0, 0.5);
  animation: fadeIn 0.3s ease-out;
  position: relative;
}
.close-profile {
  position: absolute;
  right: 18px;
  top: 12px;
  font-size: 24px;
  font-weight: bold;
  cursor: pointer;
  color: #dfd0b8;
  transition: color 0.2s ease;
}
.close-profile:hover { color: #ef476f; }
.profile-card h2 {
  margin-top: 10px;
  margin-bottom: 25px;
  font-size: 26px;
  color: #fff;
  border-bottom: 2px solid #948979;
  padding-bottom: 10px;
  letter-spacing: 0.5px;
}
.profile-card p {
  margin: 6px 0;
  font-size: 15px;
  color: #b4a393;
}
.profile-card .username {
  font-size: 22px;
  font-weight: bold;
  color: #fff;
  margin-bottom: 12px;
}
.edit-btn, .logout-btn {
  display: inline-block;
  margin: 15px 8px 0;
  padding: 10px 22px;
  border-radius: 8px;
  font-weight: 600;
  font-size: 14px;
  border: none;
  cursor: pointer;
  transition: background 0.3s ease, transform 0.1s ease;
}
.edit-btn {
  background: #948979;
  color: #222831;
}
.edit-btn:hover {
  background: #b4a393;
  transform: scale(1.05);
}
.logout-btn {
  background: #ef476f;
  color: #fff;
}
.logout-btn:hover {
  background: #d93d5e;
  transform: scale(1.05);
}
.profile-card input {
  width: 100%;
  padding: 10px;
  margin: 8px 0 14px;
  border: none;
  border-radius: 6px;
  background: #2b2f35;
  color: #dfd0b8;
  font-size: 14px;
  outline: none;
}
.profile-card label {
  text-align: left;
  display: block;
  margin-top: 5px;
  color: #b4a393;
  font-size: 13px;
}
#changePasswordLink {
  color: #948979;
  font-size: 13px;
  text-decoration: none;
  margin-left: 4px;
}
#changePasswordLink:hover { text-decoration: underline; }

/* === ANIMATIONS === */
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(-20px); }
  to { opacity: 1; transform: translateY(0); }
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

/* Profile View Container */
.profile-view {
  background: rgba(255, 255, 255, 0.04);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 14px;
  padding: 25px 30px;
  margin-bottom: 20px;
  text-align: left;
  box-shadow: inset 0 0 12px rgba(255, 255, 255, 0.03);
  transition: all 0.3s ease;
}

/* Name Section */
.profile-view .username {
  display: block;
  text-align: center;
  font-size: 23px;
  font-weight: 700;
  color: #ffffff;
  margin-bottom: 20px;
  text-transform: capitalize;
  letter-spacing: 0.5px;
}

/* Info Rows */
.profile-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin: 12px 0;
  padding: 10px 0;
  border-bottom: 1px dashed rgba(255, 255, 255, 0.1);
  transition: all 0.2s ease;
}

.profile-row:last-child {
  border-bottom: none;
}

/* Label & Value */
.profile-row strong {
  color: #dfd0b8;
  font-weight: 600;
  font-size: 14px;
  letter-spacing: 0.3px;
}

.profile-row span {
  color: #c8bca9;
  font-size: 15px;
}

/* Subtle Hover Effect */
.profile-row:hover {
  background: rgba(255, 255, 255, 0.03);
  border-radius: 8px;
  padding-left: 8px;
}

/* Password Row Special Style */
.profile-row.password-row {
  justify-content: flex-start;
  gap: 8px;
}

.profile-row.password-row span {
  flex: 1;
}

#changePasswordLink {
  color: #948979;
  font-size: 13px;
  text-decoration: none;
  font-weight: 500;
}
#changePasswordLink:hover {
  text-decoration: underline;
}

/* Add Icons (Optional aesthetic touch) */
.profile-row i {
  margin-right: 8px;
  color: #948979;
  font-size: 15px;
}
/* Dropdown inside services */
.dropdown {
    position: relative;
    cursor: pointer;
}
.dropdown-content {
    display: none;
    position: absolute;
    top: 100%;
    left: 0;
    background: #222831;
    border-radius: 6px;
    min-width: 180px;
    padding: 10px 0;
    z-index: 100;
    box-shadow: 0 4px 10px rgba(0,0,0,0.3);
    flex-direction: column;
}
.dropdown-content a {
    display: block;
    padding: 8px 20px;
    color: #DFD0B8;
    text-decoration: none;
    font-weight: 500;
}
.dropdown-content a:hover {
    background: #948979;
    color: #222831;
}
.dropdown:hover .dropdown-content {
    display: flex;
}
.modal-overlay {
    position: fixed;
    top: 0; left: 0;
    width: 100%; height: 100%;
    background: rgba(0,0,0,0.7);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 99999;
}

.modal-box {
    background: #fff;
    color: #333;
    max-width: 420px;
    padding: 1.7rem;
    border-radius: 10px;
    text-align: center;
    box-shadow: 0 8px 25px rgba(0,0,0,0.4);
}

.close-btn {
    margin-top: 1rem;
    padding: 0.6rem 1.2rem;
    background: #007bff;
    color: white;
    border: none;
    border-radius: 8px;
    cursor: pointer;
}

.close-btn:hover {
    background: #0056cc;
}
/* Reminder Modal */
#reminderModal .modal-box {
    background: linear-gradient(135deg, #FFD166, #EF476F); /* Warm gradient */
    color: #222831; /* Dark text for contrast */
    max-width: 480px;
    padding: 25px 30px;
    border-radius: 14px;
    text-align: center;
    box-shadow: 0 12px 28px rgba(0, 0, 0, 0.4);
    animation: slideFadeIn 0.35s ease-out;
}

#reminderModal .modal-box h2 {
    font-size: 24px;
    margin-bottom: 15px;
    color: #222831;
}

#reminderModal .modal-box p {
    font-size: 15px;
    line-height: 1.6;
    color: #222831;
}

#reminderModal .close-btn {
    background: #222831; /* Dark button */
    color: #FFD166; /* Contrasting text */
    padding: 10px 20px;
    font-weight: bold;
    border-radius: 8px;
    margin-top: 12px;
    transition: all 0.3s ease;
}
#reminderModal .close-btn:hover {
    background: #EF476F;
    color: #fff;
    transform: scale(1.05);
}

/* Keep fade-in animation */
@keyframes slideFadeIn {
  from { opacity: 0; transform: translateY(-15px); }
  to { opacity: 1; transform: translateY(0); }
}

</style>
</head>
<body>
<?php if ($showReminder): ?>
<div id="reminderModal" class="modal-overlay" role="dialog" aria-modal="true">
    <div class="modal-box">
        <h2>📌 Important Reminder</h2>
        <p>
            After booking an appointment and once it gets <strong>approved</strong>, you must pay a <strong>50% downpayment</strong> within <strong>48 hours</strong>.
            Failure to do so will result in your appointment being <strong>denied</strong>.
        </p>
        <div style="text-align:center; margin-top:12px;">
            <button id="closeReminder" class="close-btn">Close</button>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Header -->
<div class="header">
    <div style="display:flex; align-items:center; gap:15px;">
        
        <!-- Profile Menu -->
        <div class="profile-menu">
            <button class="profile-button" id="profileBtn">
                <i class="fa-solid fa-user-circle"></i>
            </button>

            <!-- Profile Overlay -->
<div class="profile-overlay" id="profileOverlay">
    <div class="profile-card" style="width:500px; max-width:95%; padding:35px;">
        <span class="close-profile">&times;</span>
        <h2 style="font-size:26px; margin-bottom:20px;">Profile</h2>

        <!-- View Mode -->
        <div class="profile-view">
  <span class="username"><?= htmlspecialchars($user['name']) ?></span>

  <div class="profile-row">
    <strong>Email</strong>
    <span><?= htmlspecialchars($user['email']) ?></span>
  </div>

  <div class="profile-row">
    <strong>Contact</strong>
    <span><?= htmlspecialchars($user['contact']) ?></span>
  </div>

  <div class="profile-row">
    <strong>Birthday</strong>
    <span><?= htmlspecialchars($user['birthday']) ?></span>
  </div>

  <div class="profile-row">
    <strong>Age</strong>
    <span>
      <?php 
        $birthDate = new DateTime($user['birthday']);
        $today = new DateTime("today");
        echo $birthDate->diff($today)->y . " years old";
      ?>
    </span>
  </div>

  <div class="profile-row password-row">
    <strong>Password</strong>
    <span>••••••••</span>
    <a href="#" id="changePasswordLink">Change</a>
  </div>

  <div class="profile-actions">
    <button class="edit-btn" id="editProfileBtn">Edit Info</button>
    <a href="logout.php" class="logout-btn">Logout</a>
  </div>
</div>

<!-- Edit Profile Form (hidden at first) -->
<div id="profileEdit" style="display:none;">
  <form method="post" action="update_profile.php">
    <label>Name</label>
    <input type="text" name="name" value="<?= htmlspecialchars($user['name']) ?>" required>

    <label>Email</label>
    <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>

    <label>Contact</label>
    <input type="text" name="contact" value="<?= htmlspecialchars($user['contact']) ?>">

    <label>Birthday</label>
    <input type="date" name="birthday" value="<?= htmlspecialchars($user['birthday']) ?>">

    <button type="submit" class="edit-btn">Save</button>
    <button type="button" class="logout-btn" id="cancelEditBtn">Cancel</button>
  </form>
</div>

        <!-- Change Password Form -->
        <form method="POST" action="change_password.php" style="display:none;" id="changePasswordForm">
            <label>Current Password</label>
            <input type="password" name="current_password" required>

            <label>New Password</label>
            <input type="password" name="new_password" required>

            <label>Confirm New Password</label>
            <input type="password" name="confirm_password" required>

            <button type="submit" class="edit-btn">Update Password</button>
            <button type="button" class="logout-btn" id="cancelPasswordBtn">Cancel</button>
        </form>
    </div>
</div>

        </div>
        
        <!-- System Title -->
        <div class="header-title">Church Appointment System</div>
    </div>
    
    <!-- Navigation Links -->
    <div class="nav-links">
        <!-- Notification Bell -->
        <div class="notif-bell">
            <button id="notifBtn">
                <i class="fa-solid fa-bell"></i>
                <?php if ($notifCount > 0): ?>
                    <span class="notif-count"><?= $notifCount ?></span>
                <?php endif; ?>
            </button>
            <div class="notif-dropdown" id="notifDropdown">
                <?php if ($notifRes->num_rows === 0): ?>
                    <p class="empty">No new notifications.</p>
                <?php else: ?>
                    <ul>
                        <?php 
                        $notifRes->data_seek(0); 
                        while ($n = $notifRes->fetch_assoc()): ?>
                            <li class="<?= $n['status'] == 0 ? 'unread' : 'read' ?>">
                                <a href="#" class="openNotif" 
                                   data-id="<?= $n['id'] ?>"
                                   data-message="<?= htmlspecialchars($n['message'], ENT_QUOTES) ?>" 
                                   data-time="<?= date("F j, Y, g:i A", strtotime($n['created_at'])) ?>">
                                   <?= htmlspecialchars(mb_strimwidth($n['message'], 0, 40, "...")) ?>
                                   <div class="time"><?= date("M j, g:i A", strtotime($n['created_at'])) ?></div>
                                </a>
                            </li>
                        <?php endwhile; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

        <a href="#home">Home</a>
        <a href="#history">About</a>
        <a href="#services">Services</a>
        <a href="#mass">Mass Schedule</a>
        <a href="#contacts">Contacts</a>
    </div>
</div>

<!-- Dashboard -->
<div class="container" id="home">
    
    
    <div class="top-bar">
        <h2>Welcome <?= htmlspecialchars($user['name']) ?></h2>
        <a href="appointment_history.php">History</a>
    </div>

    <?php
    if (isset($_SESSION['success'])) {
        echo '<div style="background:#06D6A0;color:#222831;padding:10px;margin-bottom:15px;border-radius:5px;">' . 
             htmlspecialchars($_SESSION['success']) . '</div>';
        unset($_SESSION['success']);
    }
    if (isset($_SESSION['error'])) {
        echo '<div style="background:#EF476F;color:#fff;padding:10px;margin-bottom:15px;border-radius:5px;">' . 
             htmlspecialchars($_SESSION['error']) . '</div>';
        unset($_SESSION['error']);
    }
    ?>

    <!-- Notification Modal -->
<div id="notifModal" class="modal">
  <div class="modal-content">
    <span class="close-btn">&times;</span>
    <h2>Notification</h2>
    <div id="notifMessage"></div>
    <div id="notifTime" class="time"></div>
  </div>
</div>


    <h3>Your Appointments</h3>

    <?php if ($res->num_rows === 0): ?>
      <p>No appointments found.</p>
    <?php else: ?>
    <ul>
        <?php 
    while ($row = $res->fetch_assoc()):
        // Check if appointment is older than 48 hours and still pending
        if (is_null($row['approved'])) {
            $created = isset($row['created_at']) ? strtotime($row['created_at']) : time();
            $now = time();
            if (($now - $created) > 48 * 3600) {
                // Auto-update status to denied
                $stmt = $conn->prepare("UPDATE appointments SET approved = 0 WHERE id = ?");
                $stmt->bind_param("i", $row['id']);
                $stmt->execute();
                $row['approved'] = 0; // update local variable so badge shows Denied
            }
        }
    ?>
            <li>
<div>
    <?php
        // Build display parts: only include type if present to avoid leading hyphen
        $displayParts = [];
        if (!empty($row['type'])) {
            $displayParts[] = htmlspecialchars($row['type']);
        }
        // Ensure appointment_date is parsed safely
        $apptDate = $row['appointment_date'] ?? '';
        $formattedDate = $apptDate ? date("F j, Y, g:i A", strtotime($apptDate)) : 'TBA';
        $displayParts[] = $formattedDate;

        echo implode(' - ', $displayParts);
        echo ' ';
        if (is_null($row['approved'])) {
            echo '<span class="badge badge-pending">Pending</span>';
        } elseif ($row['approved'] == 1) {
            echo '<span class="badge badge-approved">Approved</span>';
        } else {
            echo '<span class="badge badge-denied">Denied</span>';
        }
    ?>
</div>
                <div class="menu-bar">
                    <button class="menu-button" aria-label="Toggle menu">
                        <span class="hamburger-icon"></span>
                    </button>
                    <div class="menu-content">
                        <a href="edit_appointment.php?id=<?= intval($row['id']) ?>">Edit</a>
                        <a href="cancel_appointment.php?id=<?= intval($row['id']) ?>" onclick="return confirm('Are you sure you want to cancel this appointment?');">Cancel</a>
                    </div>
                </div>
            </li>
        <?php endwhile; ?>
    </ul>
    <?php endif; ?>

    <a href="add_appointment.php" class="add-appointment">+ Add Appointment</a>
</div>

<!-- About -->
<div class="container" id="history">
   <h3>About Church</h3>
   <p>
     The Archdiocesan Shrine of St. Therese of the Child Jesus, more commonly known as St. Therese Parish in Lahug, stands as a strong witness of faith and community in Cebu City. The first chapel was built in 1938, but during the Second World War it was destroyed and left in ruins, even serving for years as a stable and shelter for livestock.
   </p>
   <p>
     In 1959, through the faith and determination of the local community, the rebuilding of the chapel began. On March 19, 1959, the Feast of St. Joseph, the first postwar Mass was celebrated in a temporary structure. The new chapel was blessed on March 19, 1961 by Archbishop Julio Rosales, and just a few years later, on February 1, 1964, it was officially elevated into a parish under the patronage of St. Therese of the Child Jesus.
   </p>
   <p>
     Since then, St. Therese Parish has continued to grow as a vibrant center of Catholic life. Today, the parish offers daily and Sunday Masses in both English and Cebuano, regular confession schedules, and serves as a beloved venue for weddings and other sacraments.
   </p>
   <p>
     As the number of parishioners continues to increase, the community has embarked on a major renovation project to expand and strengthen the church, ensuring that it will continue to serve as a spiritual home for future generations.
   </p>
</div>

<!-- Services Section -->
<div class="container" id="services">
    <h3>Our Services</h3>
    <div class="services-list">
        <a href="add_appointment.php?type=Wedding" class="service-box">Wedding</a>
        <a href="add_appointment.php?type=Blessing" class="service-box">Blessing</a>
        <a href="add_appointment.php?type=Funeral" class="service-box">Funeral</a>
        
        <div class="service-box dropdown">
            Baptism
            <div class="dropdown-content">
                <a href="add_appointment.php?type=Regular Baptism" id="regularBaptismBtn">Regular Baptism</a>
                <a href="add_appointment.php?type=Special Baptism" id="specialBaptismBtn">Special Baptism</a>
            </div>
        </div>

        <a href="add_appointment.php?type=Certificate Requesting" class="service-box">Certificate Requesting</a>
        <a href="add_appointment.php?type=Pre-Cana Seminar" class="service-box">Pre-Cana Seminar</a>
    </div>
</div>



<!-- Regular Baptism -->
<div class="container" id="regular-baptism">
    <h3>Regular Baptism</h3>
    <p><strong>Registration Fee:</strong> Php 500 (2 Sponsors Free)</p>
    <p><strong>Excess Sponsors Fee:</strong> Php 150 each</p>
    <p><strong>Schedule:</strong> Sunday</p>
    <ul>
        <li>8:30 – 9:00 AM: Registration</li>
        <li>9:30 – 10:30 AM: Seminar (Parents & Sponsors)</li>
        <li>10:30 – 11:30 AM: Mass</li>
        <li>11:30 – 1:00 PM: Baptism</li>
    </ul>
    <p><strong>Requirements:</strong></p>
    <ul>
        <li>Child’s Birth Certificate (photocopy)</li>
        <li>Parish Permit for Non-Parishioners</li>
    </ul>
    <p><strong>Guidelines:</strong></p>
    <ul>
        <li>Registration is on the day (Sunday)</li>
        <li>Parents, Sponsors, Guests must wear proper attire</li>
        <li>Sponsors must be Catholic</li>
    </ul>
    <a href="add_appointment.php?type=Regular Baptism" class="book-btn">Book Now</a>
</div>

<!-- Confession Schedule -->
<div class="container" id="confession">
    <h3>Confession Schedule</h3>
    <p><strong>Days:</strong> Wednesday and Friday</p>
    <ul>
        <li>10:00 AM – 11:00 AM</li>
        <li>4:00 PM – 5:00 PM</li>
    </ul>
</div>

<!-- Pre-Cana Seminar -->
<div class="container" id="precana">
    <h3>Pre-Cana Seminar</h3>
    <p><strong>Schedule:</strong> Every 2nd and 4th Saturday of the month</p>
    <p><strong>Time:</strong> 7:00 AM – 5:00 PM</p>
    <p><em>(By reservation only – slots must be booked in advance)</em></p>
    <a href="add_appointment.php?type=Pre-Cana Seminar" class="book-btn">Book Pre-Cana Slot</a>
</div>



<!-- Certificate -->
<div class="container" id="certificate">
    <h3>Baptismal / Confirmation Certificate</h3>
    <p><strong>Releasing of Certificate:</strong> The day after request</p>
    <p><strong>Releasing Period:</strong> 1:00 PM – 3:00 PM</p>
    <p>Ang Baptismal o Confirmation certificate mahimong makuha sa sunod adlaw human gihimo ang request sa opisina.</p>
    <a href="add_appointment.php?type=Certificate Releasing" class="book-btn">Request Certificate</a>
</div>

<!-- Mass Schedule -->
<div class="container" id="mass">
    <h3>Mass Schedule</h3>
    
    
    <ul>
      
        <li><strong>Monday to Friday:</strong>
            <ul>
                <li>6:30 AM – English (FB Live)</li>
                <li>12:15 Noon – English</li>
                <li>5:30 PM – Cebuano</li>
            </ul>
        </li>
        <li><strong>Saturday:</strong>
            <ul>
                <li>6:30 AM – English (FB Live)</li>
                <li>5:30 PM – Cebuano</li>
            </ul>
        </li>
        <li><strong>Sunday Morning:</strong>
            <ul>
                <li>5:30 AM – Cebuano</li>
                <li>7:00 AM – Cebuano (FB Live)</li>
                <li>7:00 AM – Cebuano (FB Live)</li>
                <li>9:00 AM – English</li>
                <li>10:30 AM – English</li>
            </ul>
        </li>
        
        <li><strong>Sunday Afternoon:</strong>
            <ul>
                <li>2:30 PM – English</li>
                <li>4:00 PM – English (FB Live)</li>
                <li>5:30 PM – Cebuano</li>
                <li>7:00 PM – English</li>
            </ul>
        </li>
    </ul>
</div>

<footer id="contacts" class="container">
    <h3>Contact Us</h3>
    <p>📍 Edison cor. Pasteur Sts., Lahug, Cebu City</p>
    <p><strong>Landline Numbers:</strong><br>
      (032) 233-4964<br>
      (032) 239-4396
    </p>
    <p><strong>Mobile Numbers:</strong><br>
      0915 120 1783 (Globe)<br>
      0947 690 2118 (Smart)
    </p>
    <p>✉️ teresitasanta689@yahoo.com</p>
    <p>🕒 Office Hours: Mon–Fri, 8:00 AM – 5:00 PM</p>
</footer>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const modal = document.getElementById("reminderModal");
    const closeBtn = document.getElementById("closeReminder");

    if (modal) {
        // Auto close after 10 seconds
        setTimeout(() => {
            modal.style.display = "none";
        }, 10000);

        closeBtn.addEventListener("click", () => {
            modal.style.display = "none";
        });
    }
});
</script>

<script>

  // Baptism dropdown buttons
const regularBaptismBtn = document.getElementById("regularBaptismBtn");
const specialBaptismBtn = document.getElementById("specialBaptismBtn");

regularBaptismBtn.addEventListener("click", (e) => {
    e.preventDefault();
    // Notify if another member has the same schedule
    alert("Notice: Another member is scheduled at the same time. Please coordinate.");
    // Redirect to booking page
    window.location.href = "add_appointment.php?type=Regular Baptism";
});

specialBaptismBtn.addEventListener("click", (e) => {
    e.preventDefault();
    // Redirect to special baptism booking page
    window.location.href = "add_appointment.php?type=Special Baptism";
});

    // ====== SMOOTH SCROLL ======
document.querySelectorAll('.nav-links a').forEach(link => {
    link.addEventListener('click', e => {
        const targetId = link.getAttribute('href');
        if (targetId.startsWith('#')) {
            e.preventDefault();
            const section = document.querySelector(targetId);
            if (section) {
                window.scrollTo({
                    top: section.offsetTop - 60,
                    behavior: 'smooth'
                });
            }
        }
    });
});

// ====== MENU TOGGLE ======
document.querySelectorAll('.menu-button').forEach(button => {
    button.addEventListener('click', () => {
        const menuBar = button.closest('.menu-bar');
        document.querySelectorAll('.menu-bar.active').forEach(bar => {
            if (bar !== menuBar) bar.classList.remove('active');
        });
        menuBar.classList.toggle('active');
    });
});
document.addEventListener('click', e => {
    document.querySelectorAll('.menu-bar.active').forEach(menuBar => {
        if (!menuBar.contains(e.target)) menuBar.classList.remove('active');
    });
});

// ====== NOTIFICATIONS ======
const notifBtn = document.getElementById("notifBtn");
const notifDropdown = document.getElementById("notifDropdown");
const notifModal = document.getElementById("notifModal");
const notifMessage = document.getElementById("notifMessage");
const notifTime = document.getElementById("notifTime");
const notifCount = document.querySelector(".notif-count");
const notifCloseBtn = notifModal.querySelector(".close-btn");

notifBtn.addEventListener("click", e => {
    e.stopPropagation();
    notifDropdown.classList.toggle("show");
});

document.addEventListener("click", e => {
    if (!e.target.closest(".notif-bell")) notifDropdown.classList.remove("show");
});

document.querySelectorAll(".openNotif").forEach(link => {
    link.addEventListener("click", e => {
        e.preventDefault();
        notifMessage.textContent = link.dataset.message;
        notifTime.textContent = "Sent on " + link.dataset.time;
        notifModal.style.display = "flex";

        // AJAX mark as read
        fetch("mark_as_read.php", {
            method: "POST",
            headers: {"Content-Type": "application/x-www-form-urlencoded"},
            body: "id=" + link.dataset.id
        }).then(res => res.text()).then(() => {
            if (notifCount) {
                let count = parseInt(notifCount.textContent);
                if (count > 0) {
                    notifCount.textContent = count - 1;
                    if (count - 1 <= 0) notifCount.style.display = "none";
                }
            }
            link.parentElement.style.opacity = "0.5";
        });
    });
});

// Close notification modal
notifCloseBtn.onclick = () => notifModal.style.display = "none";
window.onclick = e => { if (e.target === notifModal) notifModal.style.display = "none"; };

// ====== PROFILE MENU & OVERLAY ======
const profileBtn = document.getElementById("profileBtn");
const profileOverlay = document.getElementById("profileOverlay");
const closeProfile = profileOverlay.querySelector(".close-profile");
const profileView = profileOverlay.querySelector(".profile-view");
const profileEdit = document.getElementById("profileEdit");
const editBtn = document.getElementById("editProfileBtn");
const cancelEditBtn = document.getElementById("cancelEditBtn");
const changePasswordLink = document.getElementById("changePasswordLink");
const changePasswordForm = document.getElementById("changePasswordForm");
const cancelPasswordBtn = document.getElementById("cancelPasswordBtn");

// Utility function to reset overlay
function resetProfileOverlay() {
    profileView.style.display = "block";
    if (profileEdit) profileEdit.style.display = "none";
    if (changePasswordForm) changePasswordForm.style.display = "none";
    profileOverlay.style.display = "none";
}

// Open profile overlay
profileBtn.addEventListener("click", (e) => {
    e.stopPropagation();
    profileOverlay.style.display = "flex";
});

// Close profile overlay
closeProfile.addEventListener("click", resetProfileOverlay);

// Switch to edit profile
if (editBtn && profileEdit) {
    editBtn.addEventListener("click", () => {
        profileView.style.display = "none";
        profileEdit.style.display = "block";
    });
}

// Cancel edit profile
if (cancelEditBtn) {
    cancelEditBtn.addEventListener("click", () => {
        profileEdit.style.display = "none";
        profileView.style.display = "block";
    });
}

// Switch to change password
if (changePasswordLink && changePasswordForm) {
    changePasswordLink.addEventListener("click", (e) => {
        e.preventDefault();
        profileView.style.display = "none";
        changePasswordForm.style.display = "block";
    });
}

// Cancel password change
if (cancelPasswordBtn) {
    cancelPasswordBtn.addEventListener("click", () => {
        changePasswordForm.style.display = "none";
        profileView.style.display = "block";
    });
}

// Close overlay if clicking outside content
profileOverlay.addEventListener("click", (e) => {
    if (e.target === profileOverlay) resetProfileOverlay();
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