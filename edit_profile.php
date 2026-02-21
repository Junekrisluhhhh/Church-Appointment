<?php
session_start();
include "db.php";

if (!isset($_SESSION['user'])) {
    header("Location: index.php");
    exit;
}

$user = $_SESSION['user'];

// ✅ If form submitted, update user info
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $contact = $_POST['contact'];

    $stmt = $conn->prepare("UPDATE users SET name=?, email=?, contact=? WHERE id=?");
    $stmt->bind_param("sssi", $name, $email, $contact, $user['id']);
    if ($stmt->execute()) {
        $_SESSION['user']['name'] = $name;
        $_SESSION['user']['email'] = $email;
        $_SESSION['user']['contact'] = $contact;
        $_SESSION['success'] = "Profile updated successfully.";
    } else {
        $_SESSION['error'] = "Failed to update profile.";
    }

    // ✅ Redirect back to dashboard
    header("Location: user_dashboard.php");
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Profile</title>
    <style>
        .profile-card {
            background: #fff;
            padding: 20px;
            border-radius: 12px;
            width: 320px;
            margin: 50px auto;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        .profile-card input {
            width: 100%;
            padding: 10px;
            margin: 8px 0;
            border-radius: 6px;
            border: 1px solid #ccc;
            font-size: 14px;
        }
        .profile-card button {
            padding: 10px 15px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }
        .save-btn {
            background: #28a745;
            color: white;
            margin-right: 10px;
        }
        .back-btn {
            background: #007bff;
            color: white;
            text-decoration: none;
            padding: 10px 15px;
            border-radius: 6px;
        }
    </style>
</head>
<body>

<div class="profile-card">
    <h2>Edit Profile</h2>
    <form method="POST">
        <input type="text" name="name" value="<?= htmlspecialchars($user['name']) ?>" required>
        <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
        <input type="text" name="contact" value="<?= htmlspecialchars($user['contact']) ?>" required>

        <button type="submit" class="save-btn">Save Changes</button>
        <a href="user_dashboard.php" class="back-btn">Back to Dashboard</a>
    </form>
</div>

</body>
</html>
