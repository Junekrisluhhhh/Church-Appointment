<?php
include "db.php";

$success = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Sanitize inputs
    $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    $name = htmlspecialchars(trim($_POST['name']));
    $contact = htmlspecialchars(trim($_POST['contact']));
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];

    // Validation
    if (empty($name) || empty($email) || empty($contact) || empty($newPassword) || empty($confirmPassword)) {
        $error = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } elseif (!preg_match("/^(09|\+639)\d{9}$/", $contact)) {
        $error = "Invalid Philippine phone number. Format: 09XXXXXXXXX";
    } elseif (!preg_match("/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/", $newPassword)) {
        $error = "Password must be at least 8 characters with uppercase, lowercase, number, and special character.";
    } elseif ($newPassword !== $confirmPassword) {
        $error = "Passwords do not match.";
    } else {
        // Check if user exists
        $stmt = $conn->prepare("SELECT * FROM users WHERE email=? AND fullname=? AND phone=?");
        $stmt->bind_param("sss", $email, $name, $contact);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $updateStmt = $conn->prepare("UPDATE users SET password=? WHERE email=?");
            $updateStmt->bind_param("ss", $hashedPassword, $email);
            $updateStmt->execute();
            $success = "Password updated successfully. Redirecting to login...";
            $updateStmt->close();
        } else {
            $error = "No user found with that email, name, and contact number.";
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reset Password</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
    body {
    min-height: 100vh;
    margin: 0;
    font-family: Arial, sans-serif;
    display: flex;
    flex-direction: column;
    /* Remove justify-content: center from here */
    align-items: center;
    color: #DFD0B8;
    overflow-x: hidden;
}
    body::before {
        content: "";
        position: fixed;
        top: 0; left: 0;
        width: 100%; height: 100%;
        background: url("../image/st therese.jpg") no-repeat center center fixed;
        background-size: cover;
        filter: blur(6px);
        transform: scale(1.1);
        z-index: -1;
    }

    /* Add this new class to handle the centering */
.content-wrapper {
    flex: 1; /* Takes up all available space between header/top and footer */
    display: flex;
    flex-direction: column;
    justify-content: center; /* This centers the form vertically in the remaining space */
    align-items: center;
    width: 100%;
}

    form {
    background-color: rgba(57, 62, 70, 0.9);
    padding: 40px;
    border-radius: 8px;
    box-shadow: 0 0 15px rgba(0,0,0,0.5);
    width: 400px;
    text-align: center;
    margin-bottom: 20px; /* Space between form and potential footer */
}
    input {
        display: block;
        margin: 15px auto;
        padding: 15px;
        width: 80%;
        border-radius: 6px;
        border: 1px solid #444;
        background-color: #333;
        color: #fff;
        font-size: 16px;
    }
    input::placeholder { color: #aaa; }
    .input-wrapper { position: relative; }
    .toggle-password {
        position: absolute;
        right: 40px;
        top: 50%;
        transform: translateY(-50%);
        cursor: pointer;
        color: #aaa;
        font-size: 18px;
        transition: color 0.3s ease;
    }
    .toggle-password:hover { color: #fff; }
    button {
        padding: 12px 25px;
        background-color: #948979;
        border: none;
        color: white;
        font-weight: bold;
        cursor: pointer;
        border-radius: 6px;
        font-size: 16px;
        margin-top: 15px;
    }
    button:hover { background-color: #A89F91; }
    .back-btn { background-color: #555; margin-top: 15px; }
    .back-btn:hover { background-color: #666; }
    .message { margin-top: 15px; }
    a { color: #DFD0B8; text-decoration: underline; }
    /* ===== SMALL SLIDE FOOTER ===== */

    /* Updated Footer Styles */
.developer-footer {
    width: 100%;
    background: rgba(0, 0, 0, 0.8);
    border-top: 1px solid rgba(255, 255, 255, 0.15);
    padding: 10px 0;
    text-align: center;
    font-size: 11px;
    margin-top: auto; /* Pushes footer to the bottom of the body */
    z-index: 10;
}

/* Ensure the carousel stays centered */
.developer-carousel {
    overflow: hidden;
    position: relative;
    height: 18px;
    width: 100%;
    max-width: 400px; /* Keeps name container focused */
    margin: 0 auto;
}

.footer-title {
  font-size: 10px;
  color: #aaa;
  margin-bottom: 4px;
  letter-spacing: 1px;
  text-transform: uppercase;
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

<body>

<div class="content-wrapper">
    <form method="POST">
        <h2>Reset Password</h2>
        <input type="text" name="name" placeholder="Your Full Name" required>
        <input type="email" name="email" placeholder="Your Email" required>
        <input type="tel" name="contact" placeholder="Your Contact Number" pattern="(09|\+639)\d{9}" required>

        <div class="input-wrapper">
            <input type="password" name="new_password" id="new_password" placeholder="New Password" required>
            <span class="toggle-password" onclick="togglePassword('new_password','icon1')">
                <i class="fas fa-eye" id="icon1"></i>
            </span>
        </div>

        <div class="input-wrapper">
            <input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm Password" required>
            <span class="toggle-password" onclick="togglePassword('confirm_password','icon2')">
                <i class="fas fa-eye" id="icon2"></i>
            </span>
        </div>

        <button type="submit">Reset Password</button>
        <button type="button" class="back-btn" onclick="window.location.href='index.php';">Back</button>
    </form>

    <div class="message">
        <?php if ($success): ?>
            <p style="color: lightgreen;"><?= $success ?></p>
        <?php elseif ($error): ?>
            <p style="color: red;"><?= $error ?></p>
        <?php endif; ?>
    </div>
</div> <footer class="developer-footer">
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

</body>

<script>
function togglePassword(id, icon) {
    const input = document.getElementById(id);
    const iconEl = document.getElementById(icon);
    if (input.type === 'password') {
        input.type = 'text';
        iconEl.classList.remove('fa-eye');
        iconEl.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        iconEl.classList.remove('fa-eye-slash');
        iconEl.classList.add('fa-eye');
    }
}
</script>

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
