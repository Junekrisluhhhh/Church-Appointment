<?php
session_start();
include "db.php";

// ===== CHECK ADMIN =====
// ===== CHECK ADMIN =====
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

$adminMessage = '';
$manageOpen = false;
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
if(isset($_POST['update'])){
    $stmt = $conn->prepare("UPDATE fixed_schedules SET time_slot=? WHERE id=?");
    $stmt->bind_param("si", $_POST['time_slot'], $_POST['id']);
    $stmt->execute();
}

if(isset($_POST['toggle'])){
    $stmt = $conn->prepare("
        UPDATE fixed_schedules 
        SET is_active = IF(is_active=1,0,1)
        WHERE id=?
    ");
    $stmt->bind_param("i", $_POST['id']);
    $stmt->execute();
}

// ===== HANDLE GET ACTIONS (Delete / Approve / ) =====
if (isset($_GET['action'], $_GET['id'])) {
    $action = $_GET['action'];
    $id = intval($_GET['id']);

    if ($action === 'delete') {
        // Optionally free booked slot
        $stmtSlot = $conn->prepare("SELECT assigned_slot FROM appointments WHERE id=?");
        $stmtSlot->bind_param("i", $id);
        $stmtSlot->execute();
        $stmtSlot->bind_result($assigned_slot);
        $stmtSlot->fetch();
        $stmtSlot->close();

        if (!empty($assigned_slot)) {
            $stmtFree = $conn->prepare("UPDATE available_slots SET is_booked=0 WHERE id=?");
            $stmtFree->bind_param("i", $assigned_slot);
            $stmtFree->execute();
            $stmtFree->close();
        }

        // Soft-delete appointment
        $stmt = $conn->prepare("UPDATE appointments SET is_deleted=1 WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();

        // Log action
        if ($adminId) log_action($conn, $adminId, 'delete', 'appointments', $id);

        header("Location: admin_dashboard.php");
        exit;
    }

    elseif ($action === 'approve' || $action === 'reject') {
        $approved = ($action === 'approve') ? 1 : 0;
        $stmt = $conn->prepare("UPDATE appointments SET approved=? WHERE id=?");
        $stmt->bind_param("ii", $approved, $id);
        $stmt->execute();
        $stmt->close();

        // Log action type as approve / deny
        if ($adminId) log_action($conn, $adminId, ($approved ? 'approve' : 'deny'), 'appointments', $id);

        header("Location: admin_dashboard.php");
        exit;
    }
}

// ===== SERVICE TYPES =====
$serviceTypes = [
    "Wedding",
    "Regular Baptism",
    "Special Baptism",
    "Blessing",
    "Mass Intentions",
    "Pre-Cana Seminar",
    "Certificate Releasing",
    "Funeral"
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


// ===== HANDLE POST REQUESTS =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // --- ADD USER ---
    if (isset($_POST['add_user'])) {
        $newName = trim($_POST['name']);
        $newEmail = strtolower(trim($_POST['email']));
        $newPassword = $_POST['password'];
        $newRole = strtolower(trim($_POST['role']));
        $contact = trim($_POST['contact'] ?? '');
        $birthday = $_POST['birthday'] ?? '2000-01-01';

        // Validate fullname - require at least first and last name, each starting with a capital letter
        if (empty($newName)) {
            $adminMessage = '<span style="color:red;">Full name is required.</span>';
        } else {
            $nameParts = preg_split('/\s+/', $newName, -1, PREG_SPLIT_NO_EMPTY);
            if (count($nameParts) < 2) {
                $adminMessage = '<span style="color:red;">Please enter at least a first name and a last name.</span>';
            } else {
                $bad = false;
                foreach ($nameParts as $part) {
                    $firstChar = mb_substr($part, 0, 1, 'UTF-8');
                    if ($firstChar !== mb_strtoupper($firstChar, 'UTF-8')) {
                        $bad = true;
                        break;
                    }
                }
                if ($bad) {
                    $adminMessage = '<span style="color:red;">Each name part must start with a capital letter (e.g. Juan Dela Cruz).</span>';
                }
            }
        }

        // Only continue other validations if the name passed
        if (empty($adminMessage)) {
            if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
                $adminMessage = '<span style="color:red;">Invalid email address.</span>';
            } elseif (strlen($newPassword) < 6) {
                $adminMessage = '<span style="color:red;">Password must be at least 6 characters.</span>';
            } elseif (!in_array($newRole, ['admin','staff'])) {
                $adminMessage = '<span style="color:red;">Invalid role selected.</span>';
            } else {
                $stmtCheck = $conn->prepare("SELECT id FROM users WHERE email=? LIMIT 1");
                $stmtCheck->bind_param("s", $newEmail);
                $stmtCheck->execute();
                $stmtCheck->store_result();

                if ($stmtCheck->num_rows > 0) {
                    $adminMessage = '<span style="color:red;">Email already exists.</span>';
                } else {
                    // Use password_hash for modern password storage
                    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                    $createdAt = date('Y-m-d H:i:s');
                    $stmt = $conn->prepare("INSERT INTO users (name, birthday, email, password, role, contact, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("sssssss", $newName, $birthday, $newEmail, $hashedPassword, $newRole, $contact, $createdAt);
                    $stmt->execute();
                    $newUserId = $conn->insert_id;
                    $stmt->close();
                    $adminMessage = "<span class='add-success'>$newRole account successfully added.</span>";

                    // Log create user
                    if ($adminId) log_action($conn, $adminId, 'create_user', 'users', $newUserId);
                }
                $stmtCheck->close();
            }
        }
        // Keep Manage Users panel open so admin sees results
        $manageOpen = true;
    }

    // --- RESET PASSWORD ---
    elseif (isset($_POST['reset_password']) && isset($_POST['user_id'])) {
        $userId = intval($_POST['user_id']);
        $tempPassword = bin2hex(random_bytes(4)); // 8 characters temp password
        $hashedTemp = password_hash($tempPassword, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("UPDATE users SET password=? WHERE id=?");
        $stmt->bind_param("si", $hashedTemp, $userId);
        $stmt->execute();
        $stmt->close();

        $adminMessage = "<span style='color:lightgreen;'>Temporary password for user ID $userId is <strong>$tempPassword</strong>. Inform the user to reset it.</span>";
        $manageOpen = true;

        // Log reset password
        if ($adminId) log_action($conn, $adminId, 'reset_password', 'users', $userId);
    }

    // --- NEW APPOINTMENT (ADMIN ENTRY) ---
    elseif (isset($_POST['submit_appointment'])) {
        $user_id = $_SESSION['user']['id'];
        $type = $_POST['appointment_type'] ?? '';
        $appointment_date = $_POST['appointment_date'] ?? '';

        $extra_info_array = [];
        if ($type === 'Funeral') {
            $extra_info_array['deceased_name']  = $_POST['deceased_name'] ?? '';
            $extra_info_array['family_contact'] = $_POST['family_contact'] ?? '';
            $extra_info_array['death_date']     = $_POST['death_date'] ?? '';
            $extra_info_array['funeral_date']   = $_POST['funeral_date'] ?? '';
        }
        $extra_info_json = json_encode($extra_info_array);

        $uploaded_files = [];
        if (!empty($_FILES['requirements']['tmp_name'][0])) {
            foreach ($_FILES['requirements']['tmp_name'] as $index => $tmp) {
                $filename = "uploads/" . time() . "_" . basename($_FILES['requirements']['name'][$index]);
                if (move_uploaded_file($tmp, $filename)) $uploaded_files[] = $filename;
            }
        }
        $requirements_json = json_encode($uploaded_files);

        $stmt = $conn->prepare("INSERT INTO appointments (user_id, type, appointment_date, extra_info, requirements, created_at) VALUES (?, ?, ?, ?, ?, ?)");
        $createdAt = date('Y-m-d H:i:s');
        $stmt->bind_param("isssss", $user_id, $type, $appointment_date, $extra_info_json, $requirements_json, $createdAt);
        $stmt->execute();
        $newAppId = $conn->insert_id;
        $stmt->close();
        $adminMessage = "<span style='color:lightgreen;'>Appointment successfully created.</span>";

        // Log appointment creation
        if ($adminId) log_action($conn, $adminId, 'create_appointment', 'appointments', $newAppId);
    }
}
// ===== FETCH DATA =====
$res = $conn->query("
    SELECT a.*, u.name, s.slot_datetime
    FROM appointments a
    JOIN users u ON a.user_id = u.id
    LEFT JOIN available_slots s ON a.assigned_slot = s.id
    WHERE a.is_deleted = 0
    GROUP BY a.id
    ORDER BY a.appointment_date DESC
");

$slots = $conn->query("SELECT * FROM available_slots WHERE is_deleted=0 ORDER BY slot_datetime ASC");

$slotServices = [];
$servicesRes = $conn->query("SELECT slot_id, service_type FROM slot_services");
while ($srv = $servicesRes->fetch_assoc()) {
    $slotServices[$srv['slot_id']][] = $srv['service_type'];
}

// ===== ADMIN PROFILE (for topbar) =====
$admin = $_SESSION['user'] ?? null;
$adminName  = htmlspecialchars($admin['name'] ?? 'Admin');
$adminEmail = htmlspecialchars($admin['email'] ?? '');
$adminRole  = htmlspecialchars($admin['role'] ?? 'admin');
$adminInitial = !empty($admin['name']) ? mb_strtoupper(mb_substr($admin['name'], 0, 1)) : 'A';

// Make add-user panel message consistent with earlier variable
$addUserMessage = $adminMessage;
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin Panel</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
/* ===================== GENERAL RESET ===================== */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

body {
    background: url("../image/night.jpeg") no-repeat center center fixed;
    background-size: cover;
    color: #fff;
    line-height: 1.6;
}
/* Requirements / View Document links */
.req-link {
    color: #fff;           /* white text */
    text-decoration: underline; /* optional, to show it's clickable */
}

.req-link:hover {
    color: #ccc;           /* slightly lighter on hover */
}


/* ===================== TOP BAR ===================== */
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
    color: #000;
}

.topbar a, .topbar button {
    color: #000;
    text-decoration: none;
    background: #FFB800;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 600;
    padding: 6px 12px;
    transition: background 0.3s;
}

.topbar a:hover, .topbar button:hover {
    background: #e6a200;
}

.trash-link {
    color: #000 !important;
}

/* Slightly lower the logout button for visual offset */
.topbar .logout-btn {
    transform: translateY(4px);
    transition: transform 0.2s ease;
}

/* ===================== PROFILE OVERLAY ===================== */
.profile-overlay {
    display: none;
    position: fixed;
    top: 0; left: 0;
    width: 100%; height: 100%;
    background: rgba(0,0,0,0.7);
    justify-content: center;
    align-items: center;
    z-index: 2000;
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

/* Ensure profile-card stacks content vertically so expanded forms push following items down */
.profile-card {
    display: flex;
    flex-direction: column;
    align-items: center;
}
.profile-avatar {
    width: 100px;
    height: 100px;
    color: #948979;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 15px;
}
.profile-avatar i {
    font-size: 70px;
}
.profile-card h3 {
    margin: 10px 0 15px;
    font-size: 24px;
    color: #fff;
    border-bottom: 2px solid #948979;
    padding-bottom: 10px;
}
.profile-card .email {
    font-size: 14px;
    color: #b4a393;
    margin-bottom: 12px;
}
.profile-card .profile-info div {
    margin: 6px 0;
    font-size: 14px;
    color: #b4a393;
}
.profile-card .btn-action {
    background: #948979;
    color: #222831;
    width: 100%;
    padding: 10px 22px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
    font-size: 14px;
    transition: background 0.3s ease, transform 0.1s ease;
    margin-top: 8px;
}
.profile-card .btn-action:hover {
    background: #b4a393;
    transform: scale(1.05);
}
.profile-card .logout-btn {
    background: #ef476f;
    color: #fff;
    margin-top: 24px;
}
.profile-card .logout-btn:hover {
    background: #d93d5e;
    transform: scale(1.05);
}

.close-btn {
    position: absolute;
    top: 10px;
    right: 15px;
    background: none;
    border: none;
    font-size: 18px;
    color: #fff;
    cursor: pointer;
}

/* Ensure password form inside profile card pushes following elements down */
.profile-card form#passwordForm {
    margin-top: 10px;
    margin-bottom: 60px !important; /* give extra space so logout is pushed down */
    width: 100%;
}

/* When password form is open, add extra spacing to logout to avoid collision */
.profile-card.password-open .logout-btn {
    margin-top: 60px !important;
}

/* ===================== NAVIGATION ===================== */
.admin-nav {
    display: flex;
    gap: 20px;
    padding: 10px 20px;
    background: rgba(0,0,0,0.5);
    backdrop-filter: blur(8px);
    justify-content: flex-end; /* Align items to the right */
}

.admin-nav a {
    color: #fff;
    text-decoration: none;
    font-weight: 500;
    padding: 6px 12px;
    border-radius: 6px;
    transition: background 0.3s;
}

.admin-nav a:hover {
    background: rgba(255,255,255,0.2);
}

/* ===================== ADD USER PANEL ===================== */
.add-admin {
    background: rgba(34,40,49,0.95);
    padding: 20px;
    margin: 20px;
    border-radius: 12px;
    box-shadow: 0 0 12px rgba(0,0,0,0.3);
}

.add-admin form {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.add-admin input, .add-admin select, .add-admin button {
    padding: 8px 10px;
    border-radius: 6px;
    border: 1px solid #ccc;
    font-size: 14px;
}

/* Light color for Add buttons */
.add-admin button {
    background: #f0f0f0;
    color: #333;
    cursor: pointer;
    transition: background 0.3s;
}

.add-admin button:hover {
    background: #e0e0e0;
}

/* ===================== MANAGE USERS TABLE ===================== */
.manage-users table {
    width: 100%;
    border-collapse: collapse;
}

.manage-users th, .manage-users td {
    border: 1px solid #555;
    padding: 8px 10px;
    text-align: left;
    color: #fff;
}

/* ===================== ACTION BUTTONS (LIGHT COLOR) ===================== */
.reset-btn,
.actions a.btn-details,
.actions a {
    padding: 6px 10px;
    background: #f0f0f0;  /* light gray background */
    color: #333;           /* dark text for contrast */
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 13px;
    text-decoration: none;
    display: inline-block;
    transition: background 0.3s, color 0.3s;
    margin: 2px;
}

.reset-btn:hover,
.actions a.btn-details:hover,
.actions a:hover {
    background: #e0e0e0;   /* slightly darker on hover */
    color: #000;
}

/* ===================== TABLES ===================== */
table {
    width: 95%;
    margin: 20px auto;
    border-collapse: collapse;
    background: rgba(34,40,49,0.95);
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 0 12px rgba(0,0,0,0.3);
}
/* ===================== SCHEDULE MANAGEMENT COLORS ===================== */
table tr td span.approved,
table tr td span.denied {
    font-weight: 200;
    color: #fff;
    padding: 4px 8px;
    border-radius: 6px;
}

table tr td span.approved {
    background-color: #00b894; /* same green as appointments */
}

table tr td span.denied {
    background-color: #d63031; /* same red as appointments */
}

/* Make Schedule Management action links match appointment buttons */
table tr td a {
    padding: 6px 10px;
    background: #f0f0f0;
    color: #333;
    border-radius: 6px;
    text-decoration: none;
    font-weight: 500;
    transition: background 0.3s, color 0.3s;
}

table tr td a:hover {
    background: #e0e0e0;
    color: #000;
}


th, td {
    padding: 10px 12px;
    border-bottom: 1px solid #555;
    text-align: left;
    color: #fff;
}

th {
    background: #00b894;
}

tr:nth-child(even) {
    background: rgba(255,255,255,0.05);
}

/* ===== YEAR SECTIONS ===== */
.year-section {
    margin: 20px auto;
    width: 95%;
    border-radius: 12px;
    overflow: hidden;
    background: rgba(34,40,49,0.95);
}

.year-header {
    background: linear-gradient(145deg, #00b894, #009370);
    padding: 15px 20px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 10px;
    user-select: none;
    transition: background 0.3s ease;
}

.year-header:hover {
    background: linear-gradient(145deg, #00c896, #009a75);
}

.year-header h4 {
    margin: 0;
    color: #fff;
    font-size: 18px;
}

.year-toggle {
    display: inline-block;
    transition: transform 0.3s ease;
    font-size: 16px;
    color: #fff;
}

.year-section.collapsed .year-toggle {
    transform: rotate(-90deg);
}

.year-section.collapsed .year-table {
    display: none !important;
}
.year-table tr:nth-child(odd) {
    background-color: rgba(0,0,0,0.25); /* darker row for contrast */
}       
.year-table tr:nth-child(even) {
    background-color: rgba(255,255,255,0.04); /* subtle lighter row (not bright white) */
}
.year-table tr:hover {
    background-color: rgba(255,255,255,0.10); /* clearer hover highlight */
}
.appointment-count {
    font-size: 14px;
    color: rgba(255,255,255,0.8);
    font-weight: normal;
}

/* ===================== STATUS BADGES ===================== */
.pending { color: #ffb703; font-weight: 600; }
.approved { color: #16a085; font-weight: 600; } /* teal for approved */
.denied { color: #e74c3c; font-weight: 600; }   /* red for denied */

.payment-full { color: #16a085; font-weight: bold; }    /* teal */
.payment-half { color: #f39c12; font-weight: bold; }    /* orange */
.payment-notpaid { color: #e74c3c; font-weight: bold; } /* red */

.status-badge { padding: 4px 8px; border-radius: 6px; color: #fff; font-weight: bold; }
.approved-badge { background: #16a085; }  /* teal */
.pending-badge { background: #f39c12; color: #111; } /* orange with dark text */
.denied-badge { background: #e74c3c; }    /* red */

/* Add-user success message styling */
.add-success {
    display: inline-block;
    background: #b7f5b0; /* light green */
    color: #000;          /* black text */
    padding: 6px 10px;
    border-radius: 8px;
    font-weight: 600;
}

/* Password field with toggle (icon button) */
.password-field { display: flex; gap: 8px; align-items: center; position: relative; }
.password-field input[type="password"], .password-field input[type="text"] { flex: 1; padding-right: 44px; }
.pwd-toggle {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    background: transparent; /* no green background */
    color: #000; /* black icon */
    border: none;
    width: 28px;
    height: 28px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    padding: 0;
}
.pwd-toggle i { font-size: 16px; }

/* ===================== DETAILS MODAL ===================== */
.details-modal {
    display: none; /* hidden by default */
    position: fixed;
    z-index: 3000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.7);
    justify-content: center;
    align-items: center;
}

.details-content {
    background: rgba(34,40,49,0.95);
    padding: 20px;
    width: 500px;
    max-width: 95%;
    border-radius: 12px;
    max-height: 90%;
    overflow-y: auto;
    color: #fff;
    position: relative;
    box-shadow: 0 0 15px rgba(0,0,0,0.5);
}
.details-content .close-btn {
    position: absolute;
    top: 10px;
    right: 15px;
    background: none;
    border: none;
    font-size: 20px;
    color: #fff;
    cursor: pointer;
}

.details-content h2 {
    margin-bottom: 15px;
    text-align: center;
}

.details-grid {
    display: grid;
    grid-template-columns: 1fr 2fr;
    gap: 10px;
    margin-bottom: 15px;
}

.details-label { font-weight: 600; color: #fff; }
.details-value { color: #ccc; }

.details-content button {
    display: block;
    margin: 0 auto;
    padding: 8px 12px;
    border: none;
    border-radius: 8px;
    background: #f0f0f0;
    color: #333;
    cursor: pointer;
}

.details-content button:hover {
    background: #e0e0e0;
}

/* ===================== SEARCH BAR ===================== */
.search-bar {
    width: 95%;
    margin: 20px auto;
    text-align: right;
}

.search-bar input {
    width: 250px;
    padding: 6px 10px;
    border-radius: 6px;
    border: 1px solid #ccc;
    font-size: 14px;
}

/* ===================== RESPONSIVE ===================== */
@media (max-width: 768px) {
    .details-grid {
        grid-template-columns: 1fr;
    }

    .topbar {
        flex-direction: column;
        align-items: flex-start;
    }

    .admin-nav {
        flex-direction: column;
        gap: 10px;
    }

    .search-bar input {
        width: 100%;
    }
}

/* ===================== ADD USER PANEL (TOGGLE STYLE) ===================== */
.btn-toggle {
    background: #f0f0f0;
    color: #333;
    border: none;
    border-radius: 6px;
    padding: 8px 16px;
    cursor: pointer;
    font-weight: 500;
    transition: background 0.3s;
}

.btn-toggle:hover {
    background: #e0e0e0;
}

.add-user-panel form {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.add-user-panel input, .add-user-panel select, .add-user-panel button {
    padding: 8px 10px;
    border-radius: 6px;
    border: 1px solid #ccc;
    font-size: 14px;
}

/* Light color for Add User buttons */
/* ===================== ADD USER ACCOUNT BUTTON STYLE ===================== */
.add-user-panel button,
.add-admin button {
    background: #00b894;        /* unified green color */
    color: #fff;
    border: none;
    border-radius: 6px;
    padding: 10px 14px;
    font-weight: 600;
    cursor: pointer;
    font-size: 14px;
    transition: background 0.3s ease, transform 0.2s ease;
}

.add-user-panel button:hover,
.add-admin button:hover {
    background: #019870;        /* darker green on hover */
    transform: scale(1.03);
}


/* Hide Manage Users and Add User Account by default */
.manage-users,
.add-user-panel {
    max-height: 0 !important;
    overflow: hidden !important;
    margin: 10px auto; /* center by default */
    transition: max-height 0.5s ease;
    display: none; /* hidden until opened */
}

/* Show when toggled */
.manage-users.open,
.add-user-panel.open {
    display: block;
    max-height: 1200px !important;
}
/* Make add-user panel narrower and centered when visible */
.add-user-panel {
    width: 480px;
    max-width: 95%;
    background: rgba(34,40,49,0.95);
    padding: 18px;
    border-radius: 10px;
    box-shadow: 0 8px 20px rgba(0,0,0,0.45);
}

.add-user-panel h3 {
    margin-top: 0;
    color: #fff;
    text-align: center;
}
/* ===== SURVEY AREA ===== */
.survey-area {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
    justify-content: space-between;
    margin: 30px 0;
}

/* Card base style */
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
    margin-bottom: 20px;
    border-bottom: 1px solid rgba(255,255,255,0.2);
    padding-bottom: 10px;
}

/* Chart styling */
.chart-card canvas {
    display: block;
    margin: 0 auto;
    max-width: 100%;
    height: 200px !important;
}

/* Feedback card specific */
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
    background: rgba(0,184,148,0.15);
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
/* ===== SCHEDULE DISPLAY ===== */
.schedule-display { margin: 20px 0; }
.schedule-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-top: 20px; }
.schedule-card {
    background: rgba(52, 52, 52, 0.8); /* darker transparent background */
    border: 2px solid #948979;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 4px 10px rgba(0,0,0,0.3); /* subtle shadow */
    transition: transform 0.3s, box-shadow 0.3s;
}

.schedule-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.5);
}

.schedule-card h4 { color: #FFB800; margin: 0 0 15px 0; font-size: 18px; }
.schedule-card p { color: #DFD0B8; margin: 8px 0; line-height: 1.6; font-size: 13px; }
.schedule-card strong { color: #FFB800; }

</style>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

<!-- ===== TOP BAR ===== -->
<div class="topbar" style="display:flex; justify-content:space-between; align-items:center; padding:10px; background:#222831; gap:15px;">
    <!-- Left-side actions -->
    <div style="display:flex; gap:15px; align-items:center;">
        <a class="trash-link" href="trash_bin.php" style="color:#fff; text-decoration:none;">Go to Trash Bin</a>
        <a class="trash-link" href="action_history.php" style="color:#fff; text-decoration:none;">View Action History</a>
        <a id="toggleUsersBtn" class="trash-link" href="#" style="color:#fff; text-decoration:none;">Manage Users</a>
        <a id="toggleAddUserBtn" class="trash-link" href="#" style="color:#fff; text-decoration:none;">Add User Account</a>
    </div>

    <!-- Right-side profile -->
    <div class="admin-profile-container">
        <button class="profile-button" onclick="toggleProfileOverlay()">
    <i class="fa-solid fa-user-circle fa-lg"></i>
</button>

    </div>
</div>

<!-- ===== PROFILE OVERLAY ===== -->
<div class="profile-overlay" id="profileOverlay">
    <div class="profile-card">
        <button class="close-btn" onclick="toggleProfileOverlay()">✖</button>

        <!-- Avatar -->
        <div class="profile-avatar">
    <i class="fa-solid fa-user-circle fa-3x"></i>
</div>

        <h3><?= htmlspecialchars($admin['name']) ?></h3>
        <p class="email"><?= htmlspecialchars($admin['email']) ?></p>

        <!-- Profile Info -->
        <div class="profile-info">
            <div><strong>Role:</strong> <?= ucfirst($admin['role']) ?></div>
            <div><strong>Contact:</strong> <?= htmlspecialchars($admin['contact'] ?? 'N/A') ?></div>
            <div><strong>Joined:</strong> <?= htmlspecialchars($admin['created_at'] ?? 'Unknown') ?></div>
        </div>

        <hr>

        <!-- Change Password -->
        <button type="button" class="btn-action" onclick="togglePasswordForm()">Change Password</button>
        <form id="passwordForm" method="POST" action="change_password.php" style="display:none;">
            <label>Current Password</label>
            <input type="password" name="old_password" required>
            <label>New Password</label>
            <input type="password" name="new_password" required>
            <button type="submit" class="btn-action">Update Password</button>
        </form>

        <!-- Logout -->
        <a href="logout.php" class="btn-action logout-btn">Logout</a>
    </div>
</div>

<div class="admin-nav">
    <a href="#appointmentsSection">All Appointments</a>
    <a href="#fixedscheduleSection">Fixed Service Schedules</a>
    <a href="#reportsSection">Reports & Analytics</a>
</div>

<?php
// ===== FETCH ALL USERS =====
$usersRes = $conn->query("SELECT id, name, email, role, contact, birthday, created_at FROM users ORDER BY created_at DESC");
?>

<!-- Manage Users Panel -->
<div id="manageUsersPanel" class="manage-users <?= $manageOpen ? 'open' : '' ?>">
    <h3>Manage Users</h3>
    <?php if(!empty($adminMessage)): ?>
        <p style='text-align:center;'><?= $adminMessage ?></p>
    <?php endif; ?>
    <table>
        <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Email</th>
            <th>Role</th>
            <th>Contact</th>
            <th>Birthday</th>
            <th>Actions</th>
        </tr>
        <?php while($user = $usersRes->fetch_assoc()): ?>
        <tr>
            <td><?= $user['id'] ?></td>
            <td><?= htmlspecialchars($user['name']) ?></td>
            <td><?= htmlspecialchars($user['email']) ?></td>
            <td><?= htmlspecialchars(ucfirst($user['role'])) ?></td>
            <td><?= htmlspecialchars($user['contact']) ?></td>
            <td><?= htmlspecialchars($user['birthday']) ?></td>
            <td>
                <form method="POST">
                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                    <button type="submit" name="reset_password" class="reset-btn" onclick="return confirm('Generate a temporary password for <?= htmlspecialchars($user['name']) ?>?');">
                        Reset Password
                    </button>
                </form>
            </td>
        </tr>
        <?php endwhile; ?>
    </table>
</div>
<div id="addUserPanel" class="add-user-panel">
    <h3>Add User Account</h3>
    <?php if($addUserMessage): ?>
        <p style="text-align:center;"><?= $addUserMessage ?></p>
    <?php endif; ?>
    <form method="post" autocomplete="off">
        <input type="text" name="name" placeholder="Full Name" pattern="[A-Z][a-z'-]+( [A-Z][a-z'-]+)+" title="Full name must have capitalized first and last name (e.g. Juan Dela Cruz)" required>
        <input type="email" name="email" placeholder="Email" required>
        <div class="password-field">
            <input id="addPassword" type="password" name="password" placeholder="Password" required>
            <button type="button" class="pwd-toggle" id="addPwdToggle" aria-label="Show password"><i class="fa-solid fa-eye"></i></button>
        </div>
        <select name="role" required>
            <option value="admin">Admin</option>
            <option value="staff">Staff</option>
        </select>
        <button type="submit" name="add_user">Add Account</button>
    </form>
</div>

<script>
const toggleBtn = document.getElementById('toggleUsersBtn');
const managePanel = document.getElementById('manageUsersPanel');

if (toggleBtn) {
    toggleBtn.addEventListener('click', (e) => {
        e.preventDefault();
        if (managePanel) managePanel.classList.toggle('open');
    });
}
// Password show/hide for add-user form (toggles eye icon)
const pwdInput = document.getElementById('addPassword');
const pwdToggle = document.getElementById('addPwdToggle');
if (pwdToggle && pwdInput) {
    pwdToggle.addEventListener('click', () => {
        if (pwdInput.type === 'password') {
            pwdInput.type = 'text';
            pwdToggle.innerHTML = '<i class="fa-solid fa-eye-slash"></i>';
            pwdToggle.setAttribute('aria-label','Hide password');
        } else {
            pwdInput.type = 'password';
            pwdToggle.innerHTML = '<i class="fa-solid fa-eye"></i>';
            pwdToggle.setAttribute('aria-label','Show password');
        }
    });
}
</script>

    <!-- Search Bar -->
    <div class="search-bar">
    <h3 id="appointmentsSection">All Appointments</h3>
    <input type="text" id="searchInput" onkeyup="filterTable()" placeholder="Search by Parishioner ID or user name...">

    <?php 
    // Group appointments by year
    $res->data_seek(0); // Reset result pointer
    $appointmentsByYear = [];
    $parishionerId = 1;
    
    while ($row = $res->fetch_assoc()) {
        $appointmentDate = $row['appointment_date'];
        $year = substr($appointmentDate, 0, 4);
        
        if (!isset($appointmentsByYear[$year])) {
            $appointmentsByYear[$year] = [];
        }
        $row['parishionerId'] = $parishionerId;
        $appointmentsByYear[$year][] = $row;
        $parishionerId++;
    }
    
    // Sort years in descending order
    krsort($appointmentsByYear);
    
    // Display appointments grouped by year
    foreach ($appointmentsByYear as $year => $appointments): 
    ?>
    
    <!-- Year Section -->
    <div class="year-section collapsed">
        <div class="year-header" onclick="toggleYearSection(this)">
            <span class="year-toggle">▼</span>
            <h4><?= $year ?> <span class="appointment-count">(<?= count($appointments) ?> appointments)</span></h4>
        </div>
        
        <table class="appointmentsTable year-table" style="display: table;">
            <tr>
                <th>User</th>
                <th>Type</th>
                <th>Requested Date</th>
                <th>Assigned Slot</th>
                <th>Status</th>
                <th>Payment</th>
                <th>Actions</th>
            </tr>
            
            <?php foreach ($appointments as $row): ?>
            <?php $formattedId = str_pad($row['parishionerId'], 3, "0", STR_PAD_LEFT); ?>
            <tr data-parishioner="<?= $formattedId ?>">
                <td><?= $formattedId ?> - <?= htmlspecialchars($row['name']) ?></td>
                <td><?= htmlspecialchars($row['type']) ?></td>
                <td><?= htmlspecialchars($row['appointment_date']) ?></td>
                <td>
                    <?php
                        if (!empty($row['slot_datetime'])) {
                            echo htmlspecialchars($row['slot_datetime']);
                        } else {
                            echo '<span class="pending">Not assigned</span>';
                        }
                    ?>
                </td>
                <td>
                    <?php
                        $paymentRaw = $row['payment_status'] ?? null;
                        $createdAtStr = $row['created_at'] ?? $row['appointment_date'] ?? null;
                        if (!empty($createdAtStr)) {
                            try {
                                $createdDT = new DateTime($createdAtStr);
                            } catch (Exception $e) {
                                $createdDT = new DateTime();
                            }
                        } else {
                            $createdDT = new DateTime();
                        }
                        $now = new DateTime();
                        $hoursDiff = ($now->getTimestamp() - $createdDT->getTimestamp()) / 3600;

                        if (is_null($row['approved'])) {
                            echo '<span class="pending-badge">For Approval</span>';
                        } else {
                            if ($row['approved'] == 0) {
                                echo '<span class="denied-badge">Denied</span>';
                            } else {
                                if ($paymentRaw === 'Full Paid') {
                                    echo '<span class="approved-badge">Complete</span>';
                                } elseif ($paymentRaw === 'Half Paid') {
                                    echo '<span class="pending-badge">Processing</span>';
                                } else {
                                    if ($hoursDiff > 48) {
                                        echo '<span class="denied-badge">Denied</span>';
                                    } else {
                                        echo '<span class="pending-badge">Pending Payment</span>';
                                    }
                                }
                            }
                        }
                    ?>
                </td>
                <td>
                    <?php 
                        $paymentStatus = $row['payment_status'] ?? 'Not Paid';
                        $class = $paymentStatus === 'Full Paid' ? 'payment-full' : 
                        ($paymentStatus === 'Half Paid' ? 'payment-half' : 'payment-notpaid');
                    ?>
                    <span class="payment-status <?= $class ?>"><?= htmlspecialchars($paymentStatus) ?></span>
                </td>
                <td class="actions">
                    <a href="javascript:void(0);" class="btn-details" onclick="showDetails(<?= $row['id'] ?>)">Details</a>
                    <?php if (is_null($row['approved'])): ?>
                        <a href="?action=approve&id=<?= $row['id'] ?>" onclick="return confirm('Approve this appointment?');">Approve</a>
                        <a href="?action=reject&id=<?= $row['id'] ?>" onclick="return confirm('Reject this appointment?');">Reject</a>
                    <?php endif; ?>
                </td>
            </tr>

            <!-- Details Modal -->
            <div id="detailsModal-<?= $row['id'] ?>" class="details-modal">
                <div class="details-content">
                    <h2>Appointment Details</h2>
                    <div class="details-grid">
                        <div class="detail-label">Parishioner Name:</div>
                        <div class="detail-value"><?= htmlspecialchars($row['name']) ?></div>
                        <div class="detail-label">Service Type:</div>
                        <div class="detail-value"><?= htmlspecialchars($row['type']) ?></div>
                        <div class="detail-label">Requested Date:</div>
                        <div class="detail-value"><?= htmlspecialchars($row['appointment_date']) ?></div>
                        <div class="detail-label">Assigned Slot:</div>
                        <div class="detail-value"><?= !empty($row['slot_datetime']) ? htmlspecialchars($row['slot_datetime']) : '<span class="pending">Not assigned</span>' ?></div>
                        <div class="detail-label">Status:</div>
                        <div class="detail-value">
                            <?php
                                if (is_null($row['approved'])) echo '<span class="pending">Pending</span>';
                                elseif ($row['approved'] == 0) echo '<span class="denied">Denied</span>';
                                else {
                                    if ($paymentRaw === 'Full Paid') echo '<span class="approved">Complete</span>';
                                    elseif ($paymentRaw === 'Half Paid') echo '<span class="pending">Processing</span>';
                                    elseif ($hoursDiff > 48) echo '<span class="denied">Denied</span>';
                                    else echo '<span class="pending">Pending Payment</span>';
                                }
                            ?>
                        </div>
                        <div class="detail-label">Payment Status:</div>
                        <div class="detail-value">
                            <?php 
                                $paymentStatus = $row['payment_status'] ?? 'Not Paid';
                                $class = $paymentStatus === 'Full Paid' ? 'payment-full' : 
                                         ($paymentStatus === 'Half Paid' ? 'payment-half' : 'payment-notpaid');
                            ?>
                            <span class="payment-status <?= $class ?>"><?= htmlspecialchars($paymentStatus) ?></span>
                        </div>
                        <div class="detail-label">Extra Information:</div>
                        <div class="detail-value">
                            <?php 
                                if (!empty($row['extra_info'])) {
                                    $extra = json_decode($row['extra_info'], true);
                                    if (is_array($extra)) {
                                        echo "<ul>";
                                        foreach ($extra as $key => $value) {
                                            echo "<li><strong>" . htmlspecialchars(ucwords(str_replace('_',' ',$key))) . ":</strong> " . htmlspecialchars($value) . "</li>";
                                        }
                                        echo "</ul>";
                                    } else echo "<p>" . htmlspecialchars($row['extra_info']) . "</p>";
                                } else echo "<p><em>None</em></p>";
                            ?>
                        </div>
                        <div class="detail-label">Requirements:</div>
                        <div class="detail-value">
                            <?php
                            $requirements = !empty($row['requirements']) ? json_decode($row['requirements'], true) : [];
                            if ($requirements && is_array($requirements)) {
                                echo "<ul>";
                                foreach ($requirements as $file) {
                                    echo "<li><a class='req-link' href='" . htmlspecialchars($file) . "' target='_blank'>View Document</a></li>";
                                }
                                echo "</ul>";
                            } else {
                                echo "<p><em>None</em></p>";
                            }
                            ?>
                        </div>
                    </div>
                    <button class="close-btn" onclick="closeDetails(<?= $row['id'] ?>)">✖</button>
                </div>
            </div>
            <?php endforeach; ?>
        </table>
    </div>
    <?php endforeach; ?>


    <!-- Slots Table -->
    <h3 id="fixedscheduleSection">Fixed Service Schedules</h3>
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

<!-- Reports & Analytics -->
<div class="reports-section" id="reportsSection">
    <h3>Reports & Analytics</h3>

    <?php
    // --- Booking Summary ---
    $bookingSummary = $conn->query("
        SELECT type, 
        COUNT(*) AS total,  
        SUM(CASE WHEN approved = 1 THEN 1 ELSE 0 END) AS approved_count,
        SUM(CASE WHEN approved IS NULL THEN 1 ELSE 0 END) AS pending_count,
        SUM(CASE WHEN approved = 0 THEN 1 ELSE 0 END) AS denied_count
        FROM appointments
        WHERE is_deleted = 0
        GROUP BY type
    ");
    

    // --- Payment Summary ---
    $paymentSummary = $conn->query("
        SELECT 
            SUM(CASE WHEN payment_status = 'Full Paid' THEN 1 ELSE 0 END) AS full_paid,
            SUM(CASE WHEN payment_status = 'Half Paid' THEN 1 ELSE 0 END) AS half_paid,
            SUM(CASE WHEN payment_status IS NULL OR payment_status = 'Not Paid' THEN 1 ELSE 0 END) AS not_paid
        FROM appointments
    ");

    // --- Attendance Summary ---
    $attendanceSummary = $conn->query("
        SELECT appointment_date,
        COUNT(*) AS scheduled,
        SUM(CASE WHEN approved = 1 THEN 1 ELSE 0 END) AS attended,
        SUM(CASE WHEN approved != 1 THEN 1 ELSE 0 END) AS no_show
        FROM appointments
        WHERE is_deleted = 0
        GROUP BY appointment_date
    ");
    ?>
    <h4>Booking Summary</h4>
    <table>
        <tr>
            <th>Service Type</th>
            <th>Total Bookings</th>
            <th>Approved</th>
            <th>Pending</th>
            <th>Denied</th>
        </tr>
        <?php while($row = $bookingSummary->fetch_assoc()): ?>
        <tr>
            <td><?= htmlspecialchars($row['type']) ?></td>
            <td><?= $row['total'] ?></td>
            <td><span class="status-badge approved-badge"><?= $row['approved_count'] ?></span></td>
            <td><span class="status-badge pending-badge"><?= $row['pending_count'] ?></span></td>
            <td><span class="status-badge denied-badge"><?= $row['denied_count'] ?></span></td>
        </tr>
        <?php endwhile; ?>
    </table>

    <h4>Payment Summary</h4>
    <?php $p = $paymentSummary->fetch_assoc(); ?>
    <table>
        <tr>
            <th>Full Paid</th>
            <th>Half Paid</th>
            <th>Not Paid</th>
        </tr>
        <tr>
            <td><span class="payment-status payment-full"><?= $p['full_paid'] ?></span></td>
            <td><span class="payment-status payment-half"><?= $p['half_paid'] ?></span></td>
            <td><span class="payment-status payment-notpaid"><?= $p['not_paid'] ?></span></td>
        </tr>
    </table>

    <h4>Attendance Summary</h4>
    <table>
        <tr>
            <th>Date</th>
            <th>Scheduled</th>
            <th>Attended</th>
            <th>No-show</th>
        </tr>
        <?php while($row = $attendanceSummary->fetch_assoc()): ?>
        <tr>
            <td><?= htmlspecialchars($row['appointment_date']) ?></td>
            <td><?= $row['scheduled'] ?></td>
            <td><?= $row['attended'] ?></td>
            <td><?= $row['no_show'] ?></td>
        </tr>
        <?php endwhile; ?>
    </table>
    <!-- Survey summary area (place near top of container) -->
<div class="survey-area">
    <div class="survey-card chart-card">
        <h4>Ratings (All time)</h4>
        <div class="chart-stat" id="avgRatingStat"></div>
        <canvas id="staffRatingChart"></canvas>
    </div>

    <div class="survey-card chart-card">
        <h4>Helpful (Yes / No)</h4>
        <div class="chart-stat" id="helpfulStat"></div>
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


</div>

<script>
/* Replace simple charts with the polished charts used in staff dashboard (avg stat, center text, data labels, polling).
   Uses the server-side arrays: $ratingLabels, $ratingData, $helpfulLabels, $helpfulData
*/
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
    window.ratingChart = new Chart(rCtx, {
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

    // show helpful stat
    const helpfulStatEl = document.getElementById('helpfulStat');
    helpfulStatEl.innerHTML = `<div class="big">${totalHelpful ? yesPercent + '%' : '—'}</div><div style="color:#ddd">${totalHelpful} responses • Yes recommend</div>`;

    window.helpfulChart = new Chart(hCtx, {
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

                // update recent comments if provided
                if (json.recent) {
                    const container = document.querySelector('.recent-comments');
                    if (container) {
                        if (json.recent.length === 0) {
                            container.innerHTML = '<p><em>No survey comments yet.</em></p>';
                        } else {
                            const html = json.recent.map(c => {
                                const user = c.user_name || 'Anonymous';
                                const time = c.created_at || '';
                                const comments = (c.comments || '').replace(/\n/g, '<br>');
                                return `<div class="comment-item"><strong>${escapeHtml(user)}</strong><div class="small">${escapeHtml(time)} • Rating: ${c.rating} • Helpful: ${escapeHtml(c.helpful)}</div><div class="comment-text">${comments}</div></div>`;
                            }).join('');
                            container.innerHTML = html;
                        }
                    }
                }

            }).catch(err => {
                console.warn('Polling error', err);
            });
    }, 30000);

    function escapeHtml(str) {
        return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
    }

});
</script>

<script>
// Set min datetime to current datetime
const toggleAddUserBtn = document.getElementById('toggleAddUserBtn');
const addUserPanel = document.getElementById('addUserPanel');

if (toggleAddUserBtn) {
    toggleAddUserBtn.addEventListener('click', (e) => {
        e.preventDefault();
        if (addUserPanel) addUserPanel.classList.toggle('open');
        // Optional: scroll into view when opened
        if (addUserPanel && addUserPanel.classList.contains('open')) {
            addUserPanel.scrollIntoView({ behavior: 'smooth' });
        }
    });
}

function toggleProfileOverlay() {
    const overlay = document.getElementById('profileOverlay');
    overlay.style.display = overlay.style.display === 'flex' ? 'none' : 'flex';
}
function togglePasswordForm() {
    const form = document.getElementById('passwordForm');
    const profileCard = document.querySelector('.profile-card');
    const isOpen = form.style.display === 'block';
    form.style.display = isOpen ? 'none' : 'block';
    if (profileCard) profileCard.classList.toggle('password-open', !isOpen);
}
function showDetails(id) {
    const modal = document.getElementById('detailsModal-' + id);
    modal.style.display = 'flex'; // show as flex for proper centering
}

function closeDetails(id) {
    const modal = document.getElementById('detailsModal-' + id);
    modal.style.display = 'none';
}

function toggleYearSection(headerElement) {
    const yearSection = headerElement.closest('.year-section');
    yearSection.classList.toggle('collapsed');
}

// Optional: click outside modal content to close
window.addEventListener('click', function(e) {
    if (e.target.classList.contains('details-modal')) {
        e.target.style.display = 'none';
    }
});


function filterTable() {
    // 1. Get the search input value
    let input = document.getElementById("searchInput");
    let filter = input.value.toLowerCase();
    
    // 2. Target all year sections (divs containing headers and tables)
    let sections = document.querySelectorAll(".year-section");

    sections.forEach(section => {
        let table = section.querySelector(".year-table");
        if (!table) return;

        let tr = table.getElementsByTagName("tr");
        let sectionHasMatch = false;

        // 3. Loop through all table rows (skipping the header row at index 0)
        for (let i = 1; i < tr.length; i++) {
            // Get columns: Parishioner Name (index 0), Service (index 1), ID (if applicable)
            let tdName = tr[i].getElementsByTagName("td")[0];
            let tdService = tr[i].getElementsByTagName("td")[1];
            let tdId = tr[i].getElementsByTagName("td")[tr[i].cells.length - 1]; // Assuming ID is last or elsewhere

            if (tdName || tdService) {
                let nameText = tdName.textContent || tdName.innerText;
                let serviceText = tdService.textContent || tdService.innerText;
                let idText = tdId ? (tdId.textContent || tdId.innerText) : "";

                // Check if search term exists in Name, Service, or ID
                if (
                    nameText.toLowerCase().indexOf(filter) > -1 || 
                    serviceText.toLowerCase().indexOf(filter) > -1 ||
                    idText.toLowerCase().indexOf(filter) > -1
                ) {
                    tr[i].style.display = ""; // Show row
                    sectionHasMatch = true;
                } else {
                    tr[i].style.display = "none"; // Hide row
                }
            }
        }

        // 4. Hide the entire Year Section (Header + Table) if no rows match
        if (sectionHasMatch) {
            section.style.display = "";
        } else {
            section.style.display = "none";
        }
    });
}
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