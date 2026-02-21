<?php
session_start();
include "db.php";

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

// Only logged-in users can submit survey (optional)
$user_id = isset($_SESSION['user']['id']) ? intval($_SESSION['user']['id']) : 0;

// Get POST values
$appointment_id = intval($_POST['appointment_id'] ?? 0);
$rating         = intval($_POST['rating'] ?? 0);           // 1-5
$nps            = intval($_POST['nps'] ?? -1);            // 0-10
$helpful        = (isset($_POST['helpful']) && $_POST['helpful'] === 'yes') ? 'yes' : 'no';
$reasons        = isset($_POST['reasons']) && is_array($_POST['reasons']) ? $_POST['reasons'] : [];
$comments       = trim($_POST['comments'] ?? '');

// Validation
$errors = [];
if ($appointment_id <= 0) $errors[] = "Invalid appointment ID.";
if ($rating < 1 || $rating > 5) $errors[] = "Invalid rating.";
if ($nps < 0 || $nps > 10) $errors[] = "Invalid NPS value.";

if (!empty($errors)) {
    $_SESSION['survey_error'] = implode(" ", $errors);
    header("Location: " . ($_SERVER['HTTP_REFERER'] ?? 'dashboard.php'));
    exit;
}

// Ensure surveys table exists
$createSql = "
CREATE TABLE IF NOT EXISTS surveys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    appointment_id INT NOT NULL,
    user_id INT NOT NULL,
    rating TINYINT NOT NULL,
    nps TINYINT NOT NULL,
    helpful ENUM('yes','no') DEFAULT 'no',
    reasons TEXT,
    comments TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";
$conn->query($createSql);

// Prepare insertion
$reasons_json = json_encode(array_values($reasons));

$stmt = $conn->prepare("INSERT INTO surveys (appointment_id, user_id, rating, nps, helpful, reasons, comments) VALUES (?, ?, ?, ?, ?, ?, ?)");
if (!$stmt) {
    $_SESSION['survey_error'] = "DB prepare error: " . $conn->error;
    header("Location: " . ($_SERVER['HTTP_REFERER'] ?? 'dashboard.php'));
    exit;
}

// Bind parameters and execute
$stmt->bind_param("iiiisss", $appointment_id, $user_id, $rating, $nps, $helpful, $reasons_json, $comments);
if ($stmt->execute()) {
    $_SESSION['survey_success'] = "Thank you for your feedback!";
} else {
    $_SESSION['survey_error'] = "Failed to save survey: " . $stmt->error;
}

$stmt->close();
header("Location: " . ($_SERVER['HTTP_REFERER'] ?? 'dashboard.php'));
exit;
?>
