<?php
include "db.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and validate inputs
    $name = htmlspecialchars(trim($_POST['name']));
    $birthday = $_POST['birthday'];
    $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    $contact = htmlspecialchars(trim($_POST['contact']));
    $password = trim($_POST['password']);

    // Validate fullname - must start with capital letters and have proper format
    if (empty($name)) {
        $error = "Full name is required!";
    } elseif (!preg_match("/^[A-Z][a-z'-]+( [A-Z][a-z'-]+)+$/", $name)) {
        $error = "Full name must have capitalized first and last name (e.g. Juan Dela Cruz)!";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format!";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long!";
    } else {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $result = $check->get_result();

        if ($result->num_rows > 0) {
            $error = "This email is already registered. <a href='index.php'>Login</a>";
        } else {
            $role = "User";

            $stmt = $conn->prepare("INSERT INTO users (name, birthday, email, contact, password, role) 
                                    VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssss", $name, $birthday, $email, $contact, $hashedPassword, $role);

            if ($stmt->execute()) {
                $success = "Registered successfully. <a href='index.php'>Login</a>";
            } else {
                $error = "Error: " . htmlspecialchars($stmt->error);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        /* Background Blur */
        /* Background Blur */
        body {
    position: relative;
    color: #DFD0B8;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    margin: 0;
    padding: 0;
    z-index: 1;
    overflow-y: auto;   /* allow vertical scrolling */
    overflow-x: hidden; /* prevent horizontal scroll (optional) */
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

        .header {
            text-align: center;
            padding: 20px;
            background: rgba(34, 40, 49, 0.8);
            color: #ffffff;
            font-size: 22px;
            font-weight: bold;
        }

        form {
            background: rgba(57, 62, 70, 0.85);
            max-width: 420px;
            margin: 50px auto;
            padding: 35px 30px;
            border-radius: 12px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.5);
            backdrop-filter: blur(6px);
        }

        form h2 {
            text-align: center;
            color: #ffffff;
            margin-bottom: 20px;
        }

        form label {
            font-weight: bold;
            display: block;
            margin-bottom: 6px;
            color: #d4d4c7;
        }

        .input-group {
            position: relative;
        }

        form input,
        form button {
            display: block;
            display: block;
            width: 100%;
            box-sizing: border-box;
            padding: 12px;
            margin-bottom: 18px;
            border-radius: 6px;
            padding: 12px;
            margin-bottom: 18px;
            border-radius: 6px;
            font-size: 14px;
        }

        form input {
            border: 1px solid #948979;
            background: rgba(255, 255, 255, 0.9);
        }

        form input {
            border: 1px solid #948979;
            background: rgba(255, 255, 255, 0.9);
            color: #222831;
            transition: all 0.3s ease;
            transition: all 0.3s ease;
        }

        form input:focus {
            border-color: #FFD369;
            outline: none;
            box-shadow: 0 0 6px #FFD369;
        }

        .toggle-password {
            position: absolute;
            top: 50%;
            right: 12px;
            transform: translateY(-50%);
            cursor: pointer;
            color: #222831;
            font-size: 18px;
        }

        form button {
            border: none;
            font-size: 15px;
            font-weight: bold;
            background-color: #dbc285;
            color: #222831;
            cursor: pointer;
            transition: 0.3s;
            transition: 0.3s;
        }

        form button:hover {
            background-color: #8bf06c;
        }

        .success, .error {
            max-width: 420px;
            margin: 15px auto;
            max-width: 420px;
            margin: 15px auto;
            text-align: center;
            padding: 12px;
            border-radius: 8px;
            padding: 12px;
            border-radius: 8px;
        }

        .success {
            background-color: #d4edda;
            color: #155724;
        }

        .error {
            background-color: #f8d7da;
            color: #721c24;
        }

        footer {
            text-align: center;
            padding: 20px;
            margin-top: 60px;
            color: #DFD0B8;
            background: rgba(34, 40, 49, 0.85);
            font-size: 14px;
        }

        footer p {
            margin: 5px 0;
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

<!-- Header -->
<div class="header">
    Church Appointment System
</div>

<!-- Error / Success -->
<?php if (!empty($error)): ?>
    <p class="error"><?= $error ?></p>
<?php elseif (!empty($success)): ?>
    <p class="success"><?= $success ?></p>
<?php endif; ?>

<!-- Registration Form -->
<form method="POST" onsubmit="return validatePasswords()">
    <h2>Register</h2>
    
    <label for="name">Full Name:</label>
    <input id="name" name="name" placeholder="e.g. Juan Dela Cruz" pattern="[A-Z][a-z'-]+( [A-Z][a-z'-]+)+" title="Full name must have capitalized first and last name (e.g. Juan Dela Cruz)" required>

    <label for="birthday">Birthday:</label>
    <input id="birthday" name="birthday" type="date" required>

    <label for="email">Email Address:</label>
    <input id="email" name="email" type="email" placeholder="e.g. juan@example.com" required>

    <label for="contact">Contact Number:</label>
    <input id="contact" name="contact" type="tel" placeholder="e.g. 09123456789" pattern="[0-9]{10,15}" required>

    <label for="password">Password:</label>
    <div class="input-group">
        <input id="password" name="password" type="password" placeholder="Minimum 6 characters" required minlength="6">
        <i class="fa-solid fa-eye-slash toggle-password" onclick="togglePasswordVisibility()"></i>
    </div>

    <label for="confirm_password">Confirm Password:</label>
    <div class="input-group">
        <input id="confirm_password" name="confirm_password" type="password" placeholder="Re-enter your password" required>
        <i class="fa-solid fa-eye-slash toggle-password" onclick="togglePasswordVisibility()"></i>
    </div>

    <button type="submit">Create Account</button>
    <button type="button" onclick="window.location.href='index.php'">Back</button>

</form>

<script>
function togglePasswordVisibility() {
    const pw = document.getElementById("password");
    const cpw = document.getElementById("confirm_password");
    const icons = document.querySelectorAll(".toggle-password");

    if (pw.type === "password") {
        pw.type = "text";
        cpw.type = "text";
        icons.forEach(icon => {
            icon.classList.remove("fa-eye-slash");
            icon.classList.add("fa-eye");
        });
    } else {
        pw.type = "password";
        cpw.type = "password";
        icons.forEach(icon => {
            icon.classList.remove("fa-eye");
            icon.classList.add("fa-eye-slash");
        });
    }
}

function validatePasswords() {
    const pw = document.getElementById("password").value;
    const cpw = document.getElementById("confirm_password").value;
    if (pw !== cpw) {
        alert("Passwords do not match!");
        return false;
    }
    return true;
}
</script>


<!-- Footer -->
<footer>
    <p>📍 Church of St. Therese, Lahug, Cebu City</p>
    <p>📞 (032) 123-4567</p>
    <p>✉️ church@example.com</p>
    <p>🕒 Office Hours: Mon–Fri, 8:00 AM – 5:00 PM</p>
</footer>

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
