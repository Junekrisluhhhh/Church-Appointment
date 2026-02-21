<?php
// Set timezone to Manila (UTC+8) for Philippines
date_default_timezone_set('Asia/Manila');

session_start();
include "db.php";
include "service_schedules.php";

if (!isset($_SESSION['user'])) exit;

$selectedType = $_GET['type'] ?? '';
$selectedType = $_GET['type'] ?? '';
$error = '';
$success = '';
$survey_error = '';
$survey_success = '';
$postData = $_POST ?? [];
$appointment_id = intval($_GET['appointment_id'] ?? 0);

if ($appointment_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM appointments WHERE id = ?");
    $stmt->bind_param("i", $appointment_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $existing = $result->fetch_assoc();
    if ($existing) {
        $postData = json_decode($existing['extra_info'], true) ?: [];
        // Fill other common fields
        $postData['appointment_type'] = $existing['type'];
        $postData['custom_date'] = substr($existing['appointment_date'], 0, 10);
        $postData['custom_time'] = strlen($existing['appointment_date']) >= 16 ? substr($existing['appointment_date'], 11, 5) : '';
        $postData['slot_id'] = $existing['assigned_slot'];
        $postData['purpose'] = $existing['reason'] ?? '';
        $postData['requirements'] = json_decode($existing['requirements'], true) ?: [];
    }
}
$success = isset($_GET['success']) ? "Your appointment has been booked successfully!" : '';

$service_prices = [
    "Wedding" => 15000,
    "Regular Baptism" => 500,
    "Special Baptism" => 2500,
    "Blessing" => 1000,
    "Funeral" => 3000,
    "Certificate Requesting" => 200,
    "Pre-Cana Seminar" => 500
];

// ---- Survey submission handling ----
// Goal: allow multiple surveys per appointment (or anonymous), and save every quick-survey to DB
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['survey_submit'])) {
    $survey_appointment_id = intval($_POST['appointment_id'] ?? 0); // may be 0 for anonymous
    $rating = intval($_POST['rating'] ?? 0);              // 1-5
    $nps = intval($_POST['nps'] ?? -1);                   // 0-10
    $helpful = (isset($_POST['helpful']) && $_POST['helpful'] === 'yes') ? 'yes' : 'no';
    $reasons = $_POST['reasons'] ?? [];                   // array of reasons
    $comments = trim($_POST['comments'] ?? '');
    $user_id = isset($_SESSION['user']['id']) ? intval($_SESSION['user']['id']) : 0;
    $service_type = trim($_POST['service_type'] ?? '');

    $isAjax = (isset($_POST['ajax']) && $_POST['ajax'] == '1');

    // Validate rating & nps only (appointment_id optional)
    if ($rating < 1 || $rating > 5) {
        $survey_error = "Please provide a valid star rating (1-5).";
    } elseif ($nps < 0 || $nps > 10) {
        $survey_error = "Please provide a valid recommendation score (0-10).";
    } else {
        // Create table if not exists -- NO UNIQUE on appointment_id so multiple feedback rows allowed
        $createSql = "
            CREATE TABLE IF NOT EXISTS surveys (
                id INT AUTO_INCREMENT PRIMARY KEY,
                appointment_id INT DEFAULT 0,
                user_id INT NOT NULL,
                service_type VARCHAR(100) DEFAULT NULL,
                rating TINYINT NOT NULL,
                nps TINYINT NOT NULL,
                helpful ENUM('yes','no') DEFAULT 'no',
                reasons TEXT,
                comments TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";
        if (!$conn->query($createSql)) {
            $survey_error = "Failed to ensure surveys table exists.";
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $survey_error, 'db_error' => $conn->error]);
                exit;
            }
        } else {
            // Insert survey (allow multiple rows for the same appointment_id)
            $reasons_json = json_encode(array_values($reasons));
            $sql = "INSERT INTO surveys (appointment_id, user_id, service_type, rating, nps, helpful, reasons, comments)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                $survey_error = "Failed to prepare survey save statement. DB: " . $conn->error;
                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => $survey_error, 'db_error' => $conn->error]);
                    exit;
                }
            } else {
                // bind: appointment_id (i), user_id (i), service_type (s), rating (i), nps (i), helpful (s), reasons (s), comments (s)
                $stmt->bind_param("iisiisss", $survey_appointment_id, $user_id, $service_type, $rating, $nps, $helpful, $reasons_json, $comments);
                if (!$stmt->execute()) {
                    $survey_error = "Failed to save survey. DB execute error: " . $stmt->error;
                    if ($isAjax) {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => false, 'message' => $survey_error, 'db_error' => $stmt->error]);
                        exit;
                    }
                } else {
                    $survey_success = "Feedback submitted successfully!";
                    if ($isAjax) {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => true, 'message' => $survey_success]);
                        exit;
                    }
                }
                $stmt->close();
            }
        }
    }

    // Non-AJAX flow: let the page render messages below
}

// Fetch available slots for services (except Baptism and Certificate Requesting)
function getAvailableSlots($conn, $serviceType){
    $slots = [];
    $stmt = $conn->prepare("
        SELECT s.*, ss.service_type 
        FROM available_slots s
        JOIN slot_services ss ON s.id = ss.slot_id
        WHERE s.is_booked = 0
          AND ss.service_type = ?
        ORDER BY s.slot_datetime ASC
    ");
    $stmt->bind_param("s", $serviceType);
    $stmt->execute();
    $res = $stmt->get_result();
    while($row = $res->fetch_assoc()) $slots[] = $row;
    $stmt->close();
    return $slots;
}

$allSlotsByService = [
    "Wedding" => getAvailableSlots($conn, "Wedding"),
    "Blessing" => getAvailableSlots($conn, "Blessing"),
];

// Fetch Pre-Cana bookings count per date
$preCanaBookings = [];
$res = $conn->query("SELECT appointment_date, COUNT(*) as count FROM appointments WHERE type='Pre-Cana Seminar' GROUP BY appointment_date");
while($row = $res->fetch_assoc()){
    $preCanaBookings[$row['appointment_date']] = intval($row['count']);
}

// Handle appointment form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['survey_submit'])) {
    $selected_type = $_POST['appointment_type'] ?? '';
    $slot_id       = intval($_POST['slot_id'] ?? 0);
    $reason        = $_POST['purpose'] ?? '';
    $custom_date   = $_POST['custom_date'] ?? '';
    $custom_time   = $_POST['custom_time'] ?? '';
    $user_id       = $_SESSION['user']['id'];
    $raw_name = trim($_POST['user_fullname'] ?? '');
    $extra_info = [];

    // Validate fullname: normalize spaces, require at least two words,
    // and ensure first letter of first and last word is uppercase.
    $normalized = preg_replace('/\s+/', ' ', $raw_name);
    if ($normalized === '') {
        $error = "Full name is required!";
    } else {
        $parts = explode(' ', $normalized);
        if (count($parts) < 2) {
            $error = "Full name must include at least first and last name.";
        } else {
            $firstInitial = mb_substr($parts[0], 0, 1, 'UTF-8');
            $lastInitial = mb_substr($parts[count($parts)-1], 0, 1, 'UTF-8');
            if ($firstInitial !== mb_strtoupper($firstInitial, 'UTF-8') || $lastInitial !== mb_strtoupper($lastInitial, 'UTF-8')) {
                $error = "Full name must have capitalized first and last name (e.g. Juan Dela Cruz)!";
            } elseif (!preg_match('/^[\p{L}0-9\.\'\-\s]+$/u', $normalized)) {
                $error = "Full name contains invalid characters.";
            } else {
                $user_fullname = htmlspecialchars($normalized, ENT_QUOTES, 'UTF-8');
                $appointment_datetime = null;
            }
        }
    }

        switch($selected_type){
        case "Wedding":
            $extra_info['groom_name'] = htmlspecialchars(trim($_POST['groom_name'] ?? ''));
            $extra_info['bride_name'] = htmlspecialchars(trim($_POST['bride_name'] ?? ''));
            
            // Validate groom and bride names
            if (empty($extra_info['groom_name'])) {
                $error = "Groom's name is required.";
            } elseif (!preg_match("/^[A-Z][a-z'-]+( [A-Z][a-z'-]+)+$/", $extra_info['groom_name'])) {
                $error = "Groom's name must have capitalized first and last name.";
            } elseif (empty($extra_info['bride_name'])) {
                $error = "Bride's name is required.";
            } elseif (!preg_match("/^[A-Z][a-z'-]+( [A-Z][a-z'-]+)+$/", $extra_info['bride_name'])) {
                $error = "Bride's name must have capitalized first and last name.";
            }
            
            // Validate wedding date and time
            if (!$error && (!$custom_date || !$custom_time)) {
                $error = "Please select both date and time for Wedding.";
            } else {
                // Check minimum 3 weeks in advance
                $weddingDate = new DateTime($custom_date);
                $today = new DateTime();
                $today->setTime(0, 0, 0);
                $minDate = clone $today;
                $minDate->add(new DateInterval('P21D')); // Add 21 days
                
                if ($weddingDate < $minDate) {
                    $error = "Wedding must be scheduled at least 3 weeks in advance.";
                } else if (!isValidDateForService($custom_date, 'Wedding')) {
                    $error = "Wedding is only available Monday to Saturday.";
                } else {
                    // Check if time is valid
                    $validTimes = getTimeSlots('Wedding', null, $custom_date);
                    if (!in_array($custom_time, $validTimes)) {
                        $error = "Invalid time selected. Wedding times: 9:00 AM, 10:30 AM, 1:00 PM, 2:30 PM, 4:00 PM";
                    } else {
                        // Check if funeral exists on this date
                        if (hasFuneralOnDate($conn, $custom_date)) {
                            $error = "Wedding cannot be scheduled on a date with a funeral booking.";
                        } else {
                            // Check if slot is already booked
                            $appointment_datetime = $custom_date . ' ' . $custom_time . ':00';
                            if (isSlotBooked($conn, 'Wedding', $appointment_datetime)) {
                                $error = "This time slot is already booked. Please choose another time.";
                            }
                        }
                    }
                }
            }
            break;
        case "Baptism":
            $extra_info['baptism_type'] = $_POST['baptism_type'] ?? '';
            $extra_info['child_name']   = htmlspecialchars(trim($_POST['child_name'] ?? ''));
            $extra_info['godparent']    = htmlspecialchars(trim($_POST['godparent'] ?? ''));
            $baptism_time = $_POST['baptism_time'] ?? '';
            
            // Validate baptism type
            if (empty($extra_info['baptism_type'])) {
                $error = "Baptism type (Regular or Special) is required.";
            }
            
            // Validate child name and godparent name
            if (!$error && empty($extra_info['child_name'])) {
                $error = "Child's name is required.";
            } elseif (!$error && !preg_match("/^[A-Z][a-z'-]+( [A-Z][a-z'-]+)+$/", $extra_info['child_name'])) {
                $error = "Child's name must have capitalized first and last name.";
            } elseif (!$error && empty($extra_info['godparent'])) {
                $error = "Godparent's name is required.";
            } elseif (!$error && !preg_match("/^[A-Z][a-z'-]+( [A-Z][a-z'-]+)+$/", $extra_info['godparent'])) {
                $error = "Godparent's name must have capitalized first and last name.";
            }
            
            if(!$error && (!$custom_date || !$baptism_time)){
                $error="Please select a date and time for Baptism.";
            } elseif(!$error) {
                $selectedDate = new DateTime($custom_date);
                $today = new DateTime();
                $today->setTime(0, 0, 0);
                
                // Check if date is in the past
                if ($selectedDate < $today) {
                    $error = "Baptism date must be a future date.";
                }
                // Regular Baptism - only Sundays
                elseif ($extra_info['baptism_type'] === "Regular" && $selectedDate->format('w') != 0) {
                    $error = "Regular Baptism is only available on Sundays.";
                }
                // Special Baptism - any day
                elseif ($extra_info['baptism_type'] === "Special") {
                    // Special baptism can be any day, just verify date is future
                    if ($selectedDate < $today) {
                        $error = "Baptism date must be a future date.";
                    }
                }
                
                if (!$error) {
                    $appointment_datetime = $custom_date . ' ' . $baptism_time . ':00';
                }
            }
            break;
        case "Blessing":
            $extra_info['blessing_type'] = htmlspecialchars(trim($_POST['blessing_type'] ?? ''));
            $extra_info['location'] = htmlspecialchars(trim($_POST['location'] ?? ''));
            $extra_info['contact_number'] = htmlspecialchars(trim($_POST['contact_number'] ?? ''));
            
            // Validate blessing type
            if (empty($extra_info['blessing_type'])) {
                $error = "Blessing type is required.";
            } elseif (strlen($extra_info['blessing_type']) < 3) {
                $error = "Blessing type must be at least 3 characters.";
            }
            
            // Validate location
            if (!$error && empty($extra_info['location'])) {
                $error = "Location is required.";
            } elseif (!$error && strlen($extra_info['location']) < 5) {
                $error = "Location must be at least 5 characters (street/place name).";
            } elseif (!$error && !preg_match("/^[a-zA-Z0-9\s,.'-]{5,}$/", $extra_info['location'])) {
                $error = "Location contains invalid characters. Use letters, numbers, spaces, commas, periods, hyphens, and apostrophes only.";
            }
            
            // Validate contact number (Philippine format)
            if (!$error && empty($extra_info['contact_number'])) {
                $error = "Contact number is required.";
            } elseif (!$error) {
                $contact = preg_replace('/\s+/', '', $extra_info['contact_number']);
                if (!preg_match("/^(\+63|09|639)\d{9}$/", $contact)) {
                    $error = "Invalid contact number. Use Philippine format: +63, 09, or 639 followed by 9 digits (e.g., 09123456789 or +639123456789).";
                }
            }
            
            // Validate date and time if not error
            if (!$error && (!$custom_date || !$custom_time)) {
                $error = "Please select both date and time for Blessing.";
            } elseif (!$error) {
                // Validate date is in future
                $blessingDate = new DateTime($custom_date);
                $today = new DateTime();
                $today->setTime(0, 0, 0);
                $minDate = clone $today;
                $minDate->add(new DateInterval('P21D')); // Add 21 days minimum for blessing
                
                if ($blessingDate < $minDate) {
                    $error = "Blessing must be scheduled at least 3 weeks in advance.";
                } else {
                    $appointment_datetime = $custom_date . ' ' . $custom_time . ':00';
                }
            }
            break;
        case "Funeral":
            $extra_info['deceased_name'] = htmlspecialchars(trim($_POST['deceased_name'] ?? ''));
            $extra_info['family_contact'] = htmlspecialchars(trim($_POST['family_contact'] ?? ''));
            $extra_info['death_date'] = $_POST['death_date'] ?? '';
            // store funeral date in extra_info from custom_date
            $extra_info['funeral_date'] = $_POST['custom_date'] ?? '';
            $extra_info['funeral_type'] = $_POST['funeral_type'] ?? '';
            $extra_info['funeral_location'] = htmlspecialchars(trim($_POST['funeral_location'] ?? ''));
            $extra_info['funeral_notes'] = htmlspecialchars(trim($_POST['funeral_notes'] ?? ''));
            
            // Validate deceased name
            if (empty($extra_info['deceased_name'])) {
                $error = "Deceased name is required.";
            } elseif (!preg_match("/^[A-Z][a-z'-]+( [A-Z][a-z'-]+)+$/", $extra_info['deceased_name'])) {
                $error = "Deceased name must have capitalized first and last name.";
            }
            
            // Validate family contact (Philippine phone number)
            if (!$error && empty($extra_info['family_contact'])) {
                $error = "Family contact number is required.";
            } elseif (!$error) {
                // Accept: +63XXXXXXXXXX, 09XXXXXXXXX, or 639XXXXXXXXX
                $contact = preg_replace('/\s+/', '', $extra_info['family_contact']);
                if (!preg_match("/^(\+63|09|639)\d{9}$/", $contact)) {
                    $error = "Invalid contact number. Use Philippine format: +63, 09, or 639 followed by 9 digits (e.g., 09123456789 or +639123456789).";
                }
            }
            
            // Validate funeral location
            if (!$error && empty($extra_info['funeral_location'])) {
                $error = "Funeral location is required.";
            } elseif (!$error && strlen($extra_info['funeral_location']) < 5) {
                $error = "Location must be at least 5 characters (street/place name).";
            } elseif (!$error && !preg_match("/^[a-zA-Z0-9\s,.'-]{5,}$/", $extra_info['funeral_location'])) {
                $error = "Location contains invalid characters. Use letters, numbers, spaces, commas, periods, hyphens, and apostrophes only.";
            }
            
            // Validate funeral date and time
            if (!$error && (!$custom_date || !$custom_time)) {
                $error = "Please select both date and time for Funeral.";
            } else {
                // Check if date is valid for funeral (Funeral available any day)
                if (!isValidDateForService($custom_date, 'Funeral')) {
                    $error = "Please select a future date for the funeral.";
                } else {
                    // Check if time is valid for the specific date
                    $validTimes = getTimeSlots('Funeral', null, $custom_date);
                    if (!in_array($custom_time, $validTimes)) {
                        $dayOfWeek = date('w', strtotime($custom_date)); // 0=Sun, 1=Mon, ..., 6=Sat
                        if ($dayOfWeek == 0) {
                            $error = "Invalid time selected. Sunday Funeral times: 12:00 PM, 12:45 PM, 1:30 PM";
                        } else {
                            $error = "Invalid time selected. Funeral times: 9:00 AM, 10:00 AM, 11:00 AM, 1:00 PM, 2:00 PM, 3:00 PM";
                        }
                    } else {
                        // Check if wedding exists on this date
                        if (hasWeddingOnDate($conn, $custom_date)) {
                            $error = "Funeral cannot be scheduled on a date with a wedding booking.";
                        } else {
                            // Check if slot is already booked
                            $appointment_datetime = $custom_date . ' ' . $custom_time . ':00';
                            if (isSlotBooked($conn, 'Funeral', $appointment_datetime)) {
                                $error = "This time slot is already booked. Please choose another time.";
                            }
                        }
                    }
                }
            }
            break;
        case "Pre-Cana Seminar":
            $extra_info['groom_name'] = htmlspecialchars(trim($_POST['groom_name'] ?? ''));
            $extra_info['bride_name'] = htmlspecialchars(trim($_POST['bride_name'] ?? ''));
            
            // Validate groom name
            if (empty($extra_info['groom_name'])) {
                $error = "Groom's name is required.";
            } elseif (!preg_match("/^[A-Z][a-z'-]+( [A-Z][a-z'-]+)+$/", $extra_info['groom_name'])) {
                $error = "Groom's name must have capitalized first and last name.";
            } elseif (empty($extra_info['bride_name'])) {
                $error = "Bride's name is required.";
            } elseif (!preg_match("/^[A-Z][a-z'-]+( [A-Z][a-z'-]+)+$/", $extra_info['bride_name'])) {
                $error = "Bride's name must have capitalized first and last name.";
            }
            break;
        case "Certificate Requesting":
            $extra_info['full_name'] = htmlspecialchars(trim($_POST['full_name'] ?? ''));
            $extra_info['certificate_type'] = $_POST['certificate_type'] ?? '';
            $extra_info['certificate_date'] = $_POST['certificate_date'] ?? '';
            
            // Validate full name
            if (empty($extra_info['full_name'])) {
                $error = "Full name is required.";
            } elseif (!preg_match("/^[A-Z][a-z'-]+( [A-Z][a-z'-]+)+$/", $extra_info['full_name'])) {
                $error = "Full name must have capitalized first and last name.";
            }
            break;
    }

    $price = 0;
    if($selected_type==="Baptism"){
        $price = ($extra_info['baptism_type']==="Regular") ? $service_prices['Regular Baptism'] : $service_prices['Special Baptism'];
    } else {
        $price = $service_prices[$selected_type] ?? 0;
    }

    // If user didn't pick a slot, but provided custom_date, we should require a time for certain services
    if ($slot_id === 0) {
        if (!empty($custom_date)) {
            if (in_array($selected_type, ['Wedding', 'Blessing', 'Funeral', 'Pre-Cana Seminar']) && empty($custom_time)) {
                $error = "Please select both date and time for " . htmlspecialchars($selected_type) . ".";
            } else {
                $appointment_datetime = $custom_date . (!empty($custom_time) ? ' ' . $custom_time . ':00' : '');
            }
        } else {
            // default to today's date (no time) - but for services requiring time, we already validated above
            $appointment_datetime = date("Y-m-d");
        }
    }

    // Server-side Pre-Cana validation (important)
    if ($selected_type === 'Pre-Cana Seminar' && !$error) {
        if (empty($custom_date) || empty($custom_time)) {
            $error = "Please select both date and time for Pre-Cana Seminar.";
        } else {
            $ds = DateTime::createFromFormat('Y-m-d', $custom_date);
            if (!$ds) {
                $error = "Invalid date format for Pre-Cana.";
            } else {
                // Check if date is in the past
                $today = new DateTime();
                $today->setTime(0, 0, 0);
                if ($ds < $today) {
                    $error = "Pre-Cana date must be a future date.";
                } else {
                    $dow = (int)$ds->format('w'); // 6 for Saturday
                    if ($dow !== 6) {
                        $error = "Pre-Cana can only be scheduled on Saturdays.";
                    } else {
                        $dayOfMonth = (int)$ds->format('j');
                        $weekOfMonth = intval(ceil($dayOfMonth / 7));
                        if ($weekOfMonth !== 2 && $weekOfMonth !== 4) {
                            $error = "Pre-Cana can only be scheduled on the 2nd or 4th Saturday of the month.";
                        } else {
                            // Check if time is valid for Pre-Cana Saturday schedule
                            $validTimes = getTimeSlots('Pre-Cana Seminar', null, $custom_date);
                            if (!in_array($custom_time, $validTimes)) {
                                $error = "Invalid time selected. Pre-Cana times: 7:00 AM, 8:00 AM, 9:00 AM, 10:00 AM, 11:00 AM, 1:00 PM, 2:00 PM, 3:00 PM, 4:00 PM, 5:00 PM";
                            } else {
                                // check bookings count
                                $stmtc = $conn->prepare("SELECT COUNT(*) as cnt FROM appointments WHERE type = 'Pre-Cana Seminar' AND appointment_date LIKE ?");
                                $likeDate = $custom_date . '%';
                                $stmtc->bind_param("s", $likeDate);
                                $stmtc->execute();
                                $rc = $stmtc->get_result()->fetch_assoc();
                                $cnt = intval($rc['cnt'] ?? 0);
                                $stmtc->close();
                                if ($cnt >= 10) {
                                    $error = "This Pre-Cana date already has 10 couples booked. Please choose another date.";
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    if(!$error){
        // Handle file uploads
        $uploaded_files = [];
        if (isset($_FILES['requirements']) && !empty($_FILES['requirements']['name'][0])) {
            $upload_dir = 'uploads/requirements/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

            foreach ($_FILES['requirements']['name'] as $key => $filename) {
                $tmp_name = $_FILES['requirements']['tmp_name'][$key];
                $error_code = $_FILES['requirements']['error'][$key];
                $size = $_FILES['requirements']['size'][$key];

                if ($error_code !== UPLOAD_ERR_OK) {
                    $error = "Error uploading file: $filename";
                    continue;
                }

                if ($size > 5 * 1024 * 1024) {
                    $error = "File too large: $filename (max 5MB)";
                    continue;
                }

                $allowed_ext = ['pdf', 'jpg', 'jpeg', 'png', 'jfif', 'docx'];
                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                if (!in_array($ext, $allowed_ext)) {
                    $error = "Invalid file type: $filename (allowed: pdf, jpg, jpeg, png, docx)";
                    continue;
                }

                $new_name = uniqid() . '.' . $ext;
                $destination = $upload_dir . $new_name;

                if (move_uploaded_file($tmp_name, $destination)) {
                    $uploaded_files[] = $destination;
                } else {
                    $error = "Failed to move uploaded file: $filename";
                }
            }
        }

        if (!$error) {
            $conn->begin_transaction();
            try {
                // If a slot was selected, check and lock it for update
                if ($slot_id > 0) {
                    $checkSlot = $conn->prepare("SELECT is_booked, slot_datetime FROM available_slots WHERE id = ? FOR UPDATE");
                    $checkSlot->bind_param("i", $slot_id);
                    $checkSlot->execute();
                    $res = $checkSlot->get_result()->fetch_assoc();
                    if (!$res) {
                        throw new Exception("Selected slot not found.");
                    }

                    if ($res['is_booked'] == 0) {
                        // Ensure appointment_datetime uses the slot datetime
                        $appointment_datetime = $res['slot_datetime'] ?: $appointment_datetime;

                        // Insert appointment
                        $stmt = $conn->prepare("INSERT INTO appointments (user_id, type, reason, appointment_date, assigned_slot, extra_info, price, requirements) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                        $json_extra = json_encode($extra_info);
                        $json_files = json_encode($uploaded_files);
                        // types: user_id (i), type (s), reason (s), appointment_date (s), assigned_slot (i), extra_info (s), price (i), requirements (s)
                        $stmt->bind_param("isssisis", $user_id, $selected_type, $reason, $appointment_datetime, $slot_id, $json_extra, $price, $json_files);
                        $stmt->execute();

                        // capture appointment id
                        $appointment_id = $conn->insert_id;

                        // Build a formal receipt-style notification (50% downpayment)
                        $downpayment = round($price / 2, 2);
                        $formattedTotal = number_format($price, 2);
                        $formattedDown = number_format($downpayment, 2);
                        $now = date('Y-m-d H:i:s');

                        // Appointment date/time string (fallback to 'TBA' if not present)
                        $apptDisplay = $appointment_datetime ?: ($custom_date ?: 'TBA');

                        $receiptMessage = <<<MSG
Official Receipt — Reservation Deposit

Service: {$selected_type}
Appointment #: {$appointment_id}
Scheduled: {$apptDisplay}

Total Fee: ₱{$formattedTotal}
Downpayment (50%): ₱{$formattedDown}

Please pay the 50% downpayment within 48 hours from receipt of this notice to confirm your booking. Failure to pay within 48 hours will result in cancellation of your reservation.

For payment arrangements or questions, please contact the parish office.
Thank you and God bless.
MSG;

                        // Insert notification for the user (staff_id set to 0 for system)
                        $notifStaffId = 0;
                        if ($stmtNotif = $conn->prepare("INSERT INTO notifications (user_id, staff_id, message) VALUES (?, ?, ?)")) {
                            $stmtNotif->bind_param("iis", $user_id, $notifStaffId, $receiptMessage);
                            $stmtNotif->execute();
                            $stmtNotif->close();
                        } else {
                            // Optional: log or handle the error $conn->error
                        }

                        // Mark slot booked
                        $updateSlot = $conn->prepare("UPDATE available_slots SET is_booked = 1 WHERE id = ?");
                        $updateSlot->bind_param("i", $slot_id);
                        $updateSlot->execute();

                        $conn->commit();
                        $appointment_id = $conn->insert_id;
                        // Redirect to a receipt page so user immediately sees total and downpayment details
                        header("Location: receipt.php?appointment_id=" . $appointment_id . "&success=1");
                        exit;

                    } else {
                        $conn->rollback();
                        $error = "Sorry, this slot has just been booked by someone else. Please choose another slot.";
                    }
                } else {
                    // No slot selected (custom date + time)
                    $stmt = $conn->prepare("INSERT INTO appointments (user_id, type, reason, appointment_date, assigned_slot, extra_info, price, requirements) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $json_extra = json_encode($extra_info);
                    $json_files = json_encode($uploaded_files);
                    $stmt->bind_param("isssisis", $user_id, $selected_type, $reason, $appointment_datetime, $slot_id, $json_extra, $price, $json_files);
                    $stmt->execute();

                    // capture appointment id
                    $appointment_id = $conn->insert_id;

                    // Build a formal receipt-style notification (50% downpayment)
                    $downpayment = round($price / 2, 2);
                    $formattedTotal = number_format($price, 2);
                    $formattedDown = number_format($downpayment, 2);
                    $now = date('Y-m-d H:i:s');

                    // Appointment date/time string (fallback to 'TBA' if not present)
                    $apptDisplay = $appointment_datetime ?: ($custom_date ?: 'TBA');

                    $receiptMessage = <<<MSG
Official Receipt — Reservation Deposit

Service: {$selected_type}
Appointment #: {$appointment_id}
Scheduled: {$apptDisplay}

Total Fee: ₱{$formattedTotal}
Downpayment (50%): ₱{$formattedDown}

Please pay the 50% downpayment within 48 hours from receipt of this notice to confirm your booking. Failure to pay within 48 hours will result in cancellation of your reservation.

For payment arrangements or questions, please contact the parish office.
Thank you and God bless.
MSG;

                    // Insert notification for the user (staff_id set to 0 for system)
                    $notifStaffId = 0;
                    if ($stmtNotif = $conn->prepare("INSERT INTO notifications (user_id, staff_id, message) VALUES (?, ?, ?)")) {
                        $stmtNotif->bind_param("iis", $user_id, $notifStaffId, $receiptMessage);
                        $stmtNotif->execute();
                        $stmtNotif->close();
                    } else {
                        // Optional: log or handle the error $conn->error
                    }

                    $conn->commit();
                    // Redirect to receipt page so user sees payment details immediately
                    header("Location: receipt.php?appointment_id=" . $appointment_id . "&success=1");
                    exit;
                }
            } catch (Exception $e) {
                $conn->rollback();
                $error = "An error occurred: " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Add Appointment</title>
<style>
/* Page and form */
html, body {height:100%;margin:0;font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;color:#DFD0B8;background:url("../image/st therese.jpg") no-repeat center center fixed;background-size:cover;overflow:hidden;}
body::before {content:'';position:fixed;top:0;left:0;width:100%;height:100%;background-color:rgba(34,40,49,0.7);z-index:-1;}
.form-container {background:#393E46;max-width:650px;height:80vh;overflow-y:auto;margin:40px auto;padding:30px;border-radius:8px;position:relative;z-index:1;transition:filter .25s ease;}
label {font-weight:bold;display:block;margin-bottom:6px;}
input, select, textarea {width:100%;padding:10px;margin-bottom:15px;border:1px solid #948979;border-radius:4px;background:#222831;color:#DFD0B8;}
button {width:100%;
padding:12px;
background: #44b6b0;
color:#222831;
font-weight:bold;
border:none;
border-radius:4px;
cursor:pointer;
}

button:hover{
background-color: #098dca;
color: #000000ad
}
.error {color:#ff6b6b;text-align:center;margin-bottom:15px;}
.note {background:#222;padding:15px;margin-top:20px;border-radius:6px;border:1px solid #948979;font-size:14px;line-height:1.6;}
.price {font-size:14px;font-weight:bold;margin-bottom:10px;}
.back-btn {background:#393E46;color:#DFD0B8;border:none;padding:10px 20px;margin-bottom:15px;border-radius:4px;cursor:pointer;} .success { color: #FFB800; background: rgba(0,0,0,0.85);border: 2px solid #FFB800;padding: 20px 25px;margin: 20px 0; text-align: center; border-radius: 12px; font-size: 1.5em; font-weight: 700; box-shadow: 0 6px 20px rgba(255,184,0,0.4);animation: popIn 0.4s ease-out;}
@keyframes popIn {
    0% { transform: scale(0.7); opacity: 0; }
    100% { transform: scale(1); opacity: 1; }
}
.survey-overlay { display: none; position: fixed; inset: 0;  background: rgba(0,0,0,0.65);  backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px); justify-content: center; align-items: center; z-index: 9999; padding: 20px;}
.survey-overlay.open { display: flex; }
.survey-modal {  background: #0b0b0b; color: #f1f1f1; border-radius: 12px; width: 720px; max-width: 100%; max-height: 90vh; overflow-y: auto; box-shadow: 0 8px 40px rgba(0,0,0,0.7); padding: 22px; border: 1px solid rgba(255,255,255,0.04); transform: translateY(0);}
/* ... (rest of CSS unchanged) ... */
.rating-row { display:flex; gap:10px; align-items:center; margin-bottom:12px; }
.rating-circle { width:48px; height:48px; border-radius:50%; display:flex; align-items:center; justify-content:center; cursor:pointer; font-weight:700; background:#000; color:#fff; border:2px solid #222; transition:transform .12s ease, box-shadow .12s ease; box-shadow: 0 2px 6px rgba(0,0,0,0.6);
}
.rating-circle:hover { transform:translateY(-4px); }
.rating-circle.selected { color:#fff; }
.rating-1.selected { background: #e74c3c; border-color:#c0392b; }
.rating-2.selected { background: #f39c12; border-color:#d35400; }
.rating-3.selected { background: #f1c40f; border-color:#d4ac0d; color:#111; }
.rating-4.selected { background: #2ecc71; border-color:#27ae60; }
.rating-5.selected { background: #00b894; border-color:#019875; }
.nps-row { margin-bottom:12px; }
.nps-slider { width:100%; }
.reasons { display:grid; grid-template-columns: repeat(2, 1fr); gap:8px; margin-bottom:12px; }
.reasons label { display:flex; gap:8px; align-items:center; color:#ddd; background:#111; padding:8px; border-radius:6px; cursor:pointer; border:1px solid rgba(255,255,255,0.03); }
textarea[name="comments"] { min-height:100px; resize:vertical; background:#0f0f0f; color:#eaeaea; border:1px solid rgba(255,255,255,0.04); }
.helper-row { display:flex; gap:10px; align-items:center; margin-bottom:12px; }
.survey-footer { display:flex; justify-content:space-between; gap:10px; align-items:center; margin-top:10px; }
.survey-footer .btn-muted { background:#222; color:#ddd; border:1px solid rgba(255,255,255,0.03); }
.survey-footer .btn-primary { background:#00b894; color:#051017; font-weight:700; border:none; }
.survey-note { color:#9e9e9e; font-size:13px; }

/* ===== CALENDAR STYLES ===== */
.calendar-container { background:#222831; border-radius:8px; padding:15px; margin-top:15px; }
.calendar-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:15px; }
.calendar-header h3 { margin:0; color:#DFD0B8; font-size:18px; }
.calendar-nav { display:flex; gap:8px; }
.calendar-grid { display:grid; grid-template-columns:repeat(7,1fr); gap:8px; margin-bottom:15px; background:#1a1f26; padding:12px; border-radius:6px; }
.weekday-header { font-weight:bold; text-align:center; color:#FFB800; padding:8px; font-size:13px; }
.calendar-day { 
    padding:12px 8px; 
    border-radius:6px; 
    cursor:default; 
    text-align:center; 
    border:1px solid #444; 
    background:#1a1f26; 
    color:#DFD0B8; 
    font-size:13px;
    transition:all 0.2s ease;
    min-height:60px;
    display:flex;
    flex-direction:column;
    justify-content:center;
    align-items:center;
}
.calendar-day.empty { background:transparent; border:none; cursor:default; }
.calendar-day.unavailable { background:#4a4a4a; color:#888; border:1px solid #5a5a5a; opacity:0.6; }
.calendar-day.available { 
    background:#2d5a2d; 
    border:2px solid #4a9d4a; 
    cursor:pointer;
    font-weight:bold;
}
.calendar-day.available:hover { background:#3a7a3a; box-shadow:0 0 8px rgba(74,157,74,0.4); }
.calendar-day-number { font-weight:bold; font-size:15px; }
.calendar-day-status { font-size:11px; color:#90EE90; margin-top:4px; }
.day-reason { font-size:11px; color:#bbb; margin-top:4px; font-style:italic; }
.slots-container { margin-top:15px; background:#1a1f26; padding:15px; border-radius:6px; }
.slots-header { font-weight:bold; color:#FFB800; margin-bottom:12px; font-size:14px; }
.time-slots { display:grid; grid-template-columns:repeat(auto-fit,minmax(90px,1fr)); gap:8px; }
.time-slot { 
    padding:10px; 
    border:2px solid #555; 
    border-radius:6px; 
    text-align:center; 
    font-size:13px; 
    background:#222; 
    color:#DFD0B8;
    cursor:default;
    transition:all 0.2s ease;
}
.time-slot.available { 
    background:#2d5a2d; 
    border-color:#4a9d4a; 
    cursor:pointer;
    font-weight:bold;
}
.time-slot.available:hover { background:#3a7a3a; box-shadow:0 0 8px rgba(74,157,74,0.4); }
.time-slot.booked { background:#4a4a4a; color:#888; border-color:#5a5a5a; opacity:0.5; cursor:not-allowed; }
.time-slot.selected { background:#00b894; color:#051017; border-color:#00b894; font-weight:bold; }
.no-slots-message { text-align:center; color:#ff6b6b; padding:15px; font-weight:bold; }
.calendar-widget-wrapper { margin:15px 0; }

@media(max-width:780px){
    .reasons { grid-template-columns: 1fr; }
    .rating-circle { width:42px;height:42px; }
    .calendar-grid { grid-template-columns:repeat(7,1fr); gap:4px; }
    .calendar-day { padding:8px 4px; font-size:11px; min-height:50px; }
    .time-slots { grid-template-columns:repeat(auto-fit,minmax(75px,1fr)); }
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
<script>
const prices = <?= json_encode($service_prices) ?>;
const postData = <?= json_encode($postData) ?>;
const allSlotsByService = <?= json_encode($allSlotsByService) ?>;
const preCanaBookings = <?= json_encode($preCanaBookings) ?>;
let appointmentId = <?= json_encode($appointment_id) ?>;
window.appointmentId = appointmentId; // expose for survey modal code to use

function updateForm(){
    const type = document.getElementById("appointment_type").value;
    const formFields = document.getElementById("service-form");
    document.getElementById("price-box").textContent = type ? "Service Price: ₱" + (type==="Baptism" ? "" : (prices[type]||0).toLocaleString()) : "";
    formFields.innerHTML = "";
    if(!type) return;

    let html = "";
    const today = new Date();

    switch(type){
        case "Wedding":
            // Minimum 3 weeks from today
            const minWedding = new Date(); minWedding.setDate(today.getDate() + 21);
            const minWeddingStr = minWedding.toISOString().split('T')[0];
            html += `
                <label>Groom's Name:</label>
                <input type="text" name="groom_name" pattern="[A-Z][a-z'-]+( [A-Z][a-z'-]+)+" title="Full name must have capitalized first and last name (e.g. Juan Dela Cruz)" value="${postData.groom_name||''}" required>
                <label>Bride's Name:</label>
                <input type="text" name="bride_name" pattern="[A-Z][a-z'-]+( [A-Z][a-z'-]+)+" title="Full name must have capitalized first and last name (e.g. Juan Dela Cruz)" value="${postData.bride_name||''}" required>

                <div class="calendar-widget-wrapper">
                    <label>📅 Select Date and Time:</label>
                    <p style="font-size: 12px; color: #FFB800; margin: 10px 0;">
                        ✓ Fixed times: 9:00 AM, 10:30 AM, 1:00 PM, 2:30 PM, 4:00 PM<br>
                        ✓ Monday to Saturday only<br>
                        ✓ Green = Available | Gray = Booked/Unavailable<br>
                        ✓ Min. 3 weeks advance booking
                    </p>
                    <div class="calendar-container" style="border: 2px solid #948979;">
                        <div class="calendar-header">
                            <h3 style="margin: 0; color: #DFD0B8;">Wedding Schedule</h3>
                            <div style="font-size: 14px; color: #FFB800; margin: 8px 0; font-weight: bold;" id="wedding-month-display"></div>
                            <div class="calendar-nav">
                                <button type="button" id="wedding-prev-month" style="background: #948979; color: #222831; border: none; padding: 8px 15px; border-radius: 4px; cursor: pointer; font-weight: bold;">← Previous</button>
                                <button type="button" id="wedding-next-month" style="background: #948979; color: #222831; border: none; padding: 8px 15px; margin-left: 5px; border-radius: 4px; cursor: pointer; font-weight: bold;">Next →</button>
                            </div>
                        </div>
                        <div class="calendar-grid" id="wedding-calendar-grid"></div>
                        <div id="wedding-slots-display"></div>
                    </div>
                </div>

                <input type="hidden" name="custom_date" id="custom_date_wedding" value="${postData.custom_date||''}">
                <input type="hidden" name="custom_time" id="custom_time_wedding" value="${postData.custom_time||''}">

                <div class="note">
                    📌 Wedding Requirements Reminder:<br>
                    • Marriage License<br>
                    • Baptismal & Confirmation Certificates<br>
                    • Certificate of Attendance of Pre-Cana Seminar<br>
                    • Marriage Banns Results<br>
                    • List of Sponsors<br>
                    • CENOMAR (if civilly married)<br>
                    Reservation Fee: ₱15,000 (50% downpayment, non-refundable).<br>
                    Must schedule at least 3 weeks before wedding.
                </div>
                <div class="note">
                    <label>Upload Requirements:</label>
                    <input type="file" name="requirements[]" multiple required>
                    <small>Attach all required documents (PDF or photos).</small>
                </div>
            `;
            // Initialize wedding calendar
            console.log('Wedding case: About to call initWeddingCalendar with minDate:', '${minWeddingStr}');
            setTimeout(() => {
                console.log('setTimeout callback for Wedding');
                initWeddingCalendar('${minWeddingStr}');
            }, 50);
        break;

        case "Baptism":
            const minBaptism = new Date(); minBaptism.setDate(today.getDate() + 1);
            const minBaptismStr = minBaptism.toISOString().split('T')[0];
            html += `
                <label>Baptism Type:</label>
                <select id="baptism_type" name="baptism_type" required onchange="handleBaptismTypeChange()">
                    <option value="">Select Type</option>
                    <option value="Regular" ${postData.baptism_type==="Regular"?"selected":""}>Regular Baptism</option>
                    <option value="Special" ${postData.baptism_type==="Special"?"selected":""}>Special Baptism</option>
                </select>

                <label>Child's Name:</label>
                <input type="text" name="child_name" pattern="[A-Z][a-z'-]+( [A-Z][a-z'-]+)+" title="Full name must have capitalized first and last name (e.g. Juan Dela Cruz)" value="${postData.child_name||''}" required>
                <label>Godparent:</label>
                <input type="text" name="godparent" pattern="[A-Z][a-z'-]+( [A-Z][a-z'-]+)+" title="Full name must have capitalized first and last name (e.g. Juan Dela Cruz)" value="${postData.godparent||''}" required>

                <div class="calendar-widget-wrapper" id="baptism-calendar-wrapper" style="display: none;">
                    <label>📅 Select Date and Time:</label>
                    <p style="font-size: 12px; color: #FFB800; margin: 10px 0;">
                        ✓ Regular: Sundays only | Special: Any day<br>
                        ✓ Green = Available | Gray = Booked/Unavailable
                    </p>
                    <div class="calendar-container" style="border: 2px solid #948979;">
                        <div class="calendar-header">
                            <h3 style="margin: 0; color: #DFD0B8;">Baptism Schedule</h3>
                            <div style="font-size: 14px; color: #FFB800; margin: 8px 0; font-weight: bold;" id="baptism-month-display"></div>
                            <div class="calendar-nav">
                                <button type="button" id="baptism-prev-month" style="background: #948979; color: #222831; border: none; padding: 8px 15px; border-radius: 4px; cursor: pointer; font-weight: bold;">← Previous</button>
                                <button type="button" id="baptism-next-month" style="background: #948979; color: #222831; border: none; padding: 8px 15px; margin-left: 5px; border-radius: 4px; cursor: pointer; font-weight: bold;">Next →</button>
                            </div>
                        </div>
                        <div class="calendar-grid" id="baptism-calendar-grid"></div>
                        <div id="baptism-slots-display"></div>
                    </div>
                </div>

                <input type="hidden" name="custom_date" id="custom_date_baptism" value="${postData.custom_date||''}">
                <input type="hidden" name="baptism_time" id="baptism_time_hidden" value="${postData.baptism_time||''}">

                <div class="note">
                    📌 Baptism Guidelines:<br>
                    <strong>Regular Baptism ₱500:</strong><br>
                    1. Only Sundays.<br>
                    2. Times: 8:30–9:00, 9:30–10:30, 10:30–11:30, 11:30.<br>
                    3. Registration is on the day (Sunday).<br>
                    4. Parents, sponsors, and guests must wear proper attire.<br>
                    5. Sponsors must be Catholic.<br>
                    <strong>Special Baptism ₱1000:</strong><br>
                    1. Any day (Mon–Sun).<br>
                    2. Times: 9:00, 10:00, 11:00, 14:00, 15:00, 16:00.<br>
                    3. Preferred registration 1 week before desired date.<br>
                    4. Parents, sponsors, and guests must wear proper attire.<br>
                    5. Sponsors must be Catholic.
                </div>
                <div class="note">
                    <label>Upload Requirements:</label>
                    <input type="file" name="requirements[]" multiple required>
                    <small>Attach all required documents (PDF or photos).</small>
                </div>
            `;
            // Initialize baptism calendar setup
            setTimeout(() => {
                const typeSelect = document.getElementById('baptism_type');
                if (typeSelect?.value) {
                    handleBaptismTypeChange();
                }
            }, 50);
        break;

        case "Blessing":
            const minBlessing = new Date(); minBlessing.setDate(today.getDate() + 21);
            const minBlessingStr = minBlessing.toISOString().split('T')[0];
            html += `
                <label>Blessing Type:</label>
                <input type="text" name="blessing_type" minlength="3" maxlength="50" placeholder="e.g. Home Blessing, Vehicle Blessing" value="${postData.blessing_type||''}" required>
                
                <label>Contact Number (Philippine Format):</label>
                <input type="tel" name="contact_number" placeholder="e.g. 09123456789 or +639123456789" pattern="^(\+63|09|639)\d{9}$" title="Philippine number format: 09 or +63 or 639 followed by 9 digits" value="${postData.contact_number||''}" required>
                
                <label>Location:</label>
                <input type="text" name="location" minlength="5" maxlength="100" pattern="[a-zA-Z0-9\s,.'-]{5,}" placeholder="e.g. 123 Main Street, Cebu City" title="Location must be at least 5 characters with valid address format" value="${postData.location||''}" required>

                <div class="calendar-widget-wrapper">
                    <label>📅 Select Date and Time:</label>
                    <p style="font-size: 12px; color: #FFB800; margin: 10px 0;">
                        ✓ Any day of the week (Mon-Sun)<br>
                        ✓ Multiple fixed times available<br>
                        ✓ Green = Available | Gray = Booked/Unavailable<br>
                        ✓ Min. 3 weeks advance booking
                    </p>
                    <div class="calendar-container" style="border: 2px solid #948979;">
                        <div class="calendar-header">
                            <h3 style="margin: 0; color: #DFD0B8;">Blessing Schedule</h3>
                            <div style="font-size: 14px; color: #FFB800; margin: 8px 0; font-weight: bold;" id="blessing-month-display"></div>
                            <div class="calendar-nav">
                                <button type="button" id="blessing-prev-month" style="background: #948979; color: #222831; border: none; padding: 8px 15px; border-radius: 4px; cursor: pointer; font-weight: bold;">← Previous</button>
                                <button type="button" id="blessing-next-month" style="background: #948979; color: #222831; border: none; padding: 8px 15px; margin-left: 5px; border-radius: 4px; cursor: pointer; font-weight: bold;">Next →</button>
                            </div>
                        </div>
                        <div class="calendar-grid" id="blessing-calendar-grid"></div>
                        <div id="blessing-slots-display"></div>
                    </div>
                </div>

                <input type="hidden" name="custom_date" id="custom_date_blessing" value="${postData.custom_date||''}">
                <input type="hidden" name="custom_time" id="custom_time_blessing" value="${postData.custom_time||''}">

                <div class="note">
                    📌 Blessing Guidelines:<br>
                    • Must be scheduled at least 3 weeks ahead.<br>
                    • Provide exact address and landmark.<br>
                    • Ensure someone is present during the blessing.<br>
                </div>

                <div class="note">
                    <label>Upload Requirements:</label>
                    <input type="file" name="requirements[]" multiple required>
                    <small>Attach supporting documents (if any).</small>
                </div>
            `;
            // Initialize blessing calendar
            console.log('Blessing case: About to call initBlessingCalendar with minDate:', '${minBlessingStr}');
            setTimeout(() => {
                console.log('setTimeout callback for Blessing');
                initBlessingCalendar('${minBlessingStr}');
            }, 50);
        break;

        case "Funeral":
            html += `
        <label>Deceased Name:</label>
        <input type="text" name="deceased_name" pattern="[A-Z][a-z'-]+( [A-Z][a-z'-]+)+" title="Full name must have capitalized first and last name (e.g. Juan Dela Cruz)" value="${postData.deceased_name||''}" required>

        <label>Family Contact Number:</label>
        <input type="text" name="family_contact" placeholder="e.g. 09123456789 or +639123456789" pattern="^(\+63|09|639)\d{9}$" title="Philippine number format: 09 or +63 or 639 followed by 9 digits" value="${postData.family_contact||''}" required>

        <label>Date of Death:</label>
        <input type="date" name="death_date" value="${postData.death_date||''}" required>

        <label>Funeral Type:</label>
        <select name="funeral_type" required>
            <option value="">-- Select Type --</option>
            <option value="Mass" ${postData.funeral_type==="Mass"?"selected":""}>Mass</option>
            <option value="Blessing Only" ${postData.funeral_type==="Blessing Only"?"selected":""}>Blessing Only</option>
            <option value="Wake" ${postData.funeral_type==="Wake"?"selected":""}>Wake</option>
        </select>

        <label>Funeral Location:</label>
        <input type="text" name="funeral_location" minlength="5" maxlength="100" pattern="[a-zA-Z0-9\s,.'-]{5,}" placeholder="e.g. 123 Main Street, Funeral Home" title="Location must be at least 5 characters with valid address format" value="${postData.funeral_location||''}" required>

        <label>Notes (optional):</label>
        <textarea name="funeral_notes" maxlength="500">${postData.funeral_notes||''}</textarea>

        <div class="calendar-widget-wrapper">
            <label>📅 Select Date and Time:</label>
            <p style="font-size: 12px; color: #FFB800; margin: 10px 0;">
                ✓ Sunday times: 12:00 PM, 12:45 PM, 1:30 PM<br>
                ✓ Monday-Saturday times: 9:00 AM, 10:00 AM, 11:00 AM, 1:00 PM, 2:00 PM, 3:00 PM<br>
                ✓ Not available if Wedding is scheduled on same date<br>
                ✓ Green = Available | Gray = Booked/Unavailable
            </p>
            <div class="calendar-container" style="border: 2px solid #948979;">
                <div class="calendar-header">
                    <h3 style="margin: 0; color: #DFD0B8;">Funeral Schedule</h3>
                    <div style="font-size: 14px; color: #FFB800; margin: 8px 0; font-weight: bold;" id="funeral-month-display"></div>
                    <div class="calendar-nav">
                        <button type="button" id="funeral-prev-month" style="background: #948979; color: #222831; border: none; padding: 8px 15px; border-radius: 4px; cursor: pointer; font-weight: bold;">← Previous</button>
                        <button type="button" id="funeral-next-month" style="background: #948979; color: #222831; border: none; padding: 8px 15px; margin-left: 5px; border-radius: 4px; cursor: pointer; font-weight: bold;">Next →</button>
                    </div>
                </div>
                <div class="calendar-grid" id="funeral-calendar-grid"></div>
                <div id="funeral-slots-display"></div>
            </div>
        </div>

        <input type="hidden" name="custom_date" id="custom_date_funeral" value="${postData.custom_date||''}">
        <input type="hidden" name="custom_time" id="custom_time_funeral" value="${postData.custom_time||''}">

        <div class="note">
            📌 Funeral Requirements Reminder:<br>
            • Death Certificate<br>
            • Authorization from the family<br>
            • Any other supporting documents required by the church<br>
            Please upload these documents below.
        </div>

        <div class="note">
            <label>Upload Requirements:</label>
            <input type="file" name="requirements[]" multiple required>
            <small>Attach the required documents (PDF, JPG, PNG, or DOCX).</small>
        </div>
    `;
            // Initialize funeral calendar
            setTimeout(() => {
                initFuneralCalendar();
            }, 50);
        break;


        case "Pre-Cana Seminar":
    html += `
        <label>Groom's Name:</label>
        <input type="text" name="groom_name" pattern="[A-Z][a-z'-]+( [A-Z][a-z'-]+)+" title="Full name must have capitalized first and last name (e.g. Juan Dela Cruz)" value="${postData.groom_name||''}" required>
        <label>Bride's Name:</label>
        <input type="text" name="bride_name" pattern="[A-Z][a-z'-]+( [A-Z][a-z'-]+)+" title="Full name must have capitalized first and last name (e.g. Juan Dela Cruz)" value="${postData.bride_name||''}" required>

        <div class="calendar-widget-wrapper">
            <label>📅 Select Date and Time:</label>
            <p style="font-size: 12px; color: #FFB800; margin: 10px 0;">
                ✓ Available times: 7:00 AM, 8:00 AM, 9:00 AM, 10:00 AM, 11:00 AM, 1:00 PM, 2:00 PM, 3:00 PM, 4:00 PM, 5:00 PM<br>
                ✓ Only 2nd and 4th Saturday of each month<br>
                ✓ Maximum 10 couples per date<br>
                ✓ Green = Available | Gray = Booked/Unavailable
            </p>
            <div class="calendar-container" style="border: 2px solid #948979;">
                <div class="calendar-header">
                    <h3 style="margin: 0; color: #DFD0B8;">Pre-Cana Schedule</h3>
                    <div style="font-size: 14px; color: #4CAF50; margin: 8px 0; font-weight: bold;" id="precana-month-display"></div>
                    <div class="calendar-nav">
                        <button type="button" id="precana-prev-month" style="background: #948979; color: #222831; border: none; padding: 8px 15px; border-radius: 4px; cursor: pointer; font-weight: bold;">← Previous</button>
                        <button type="button" id="precana-next-month" style="background: #948979; color: #222831; border: none; padding: 8px 15px; margin-left: 5px; border-radius: 4px; cursor: pointer; font-weight: bold;">Next →</button>
                    </div>
                </div>
                <div class="calendar-grid" id="precana-calendar-grid"></div>
                <div id="precana-slots-display"></div>
            </div>
        </div>

        <input type="hidden" name="custom_date" id="custom_date_precana" value="${postData.custom_date||''}">
        <input type="hidden" name="custom_time" id="custom_time_precana" value="${postData.custom_time||''}">

        <div class="note">
            📌 Pre-Cana Guidelines:<br>
            1. Limited to 10 couples per day.<br>
            2. Available only on 2nd and 4th Saturday of the month.<br>
            3. Time: 7:00 AM to 5:00 PM.<br>
            4. Bring ID and relevant documents.
        </div>
        <div class="note">
            <label>Upload Requirements:</label>
            <input type="file" name="requirements[]" multiple required>
            <small>Attach all required documents (PDF or photos).</small>
        </div>
    `;
    // Initialize Pre-Cana calendar
    setTimeout(() => {
        initPreCanaCalendar();
    }, 50);
break;


        case "Certificate Requesting":
            html += `
                <label>Full Name:</label>
                <input type="text" name="full_name" pattern="[A-Z][a-z'-]+( [A-Z][a-z'-]+)+" title="Full name must have capitalized first and last name (e.g. Juan Dela Cruz)" value="${postData.full_name||''}" required>
                <label>Certificate Type:</label>
                <select name="certificate_type" id="certificate_type_select" required onchange="updateCertificateDateLabel()">
                    <option value="">Select Certificate</option>
                    <option value="Baptismal" ${postData.certificate_type==='Baptismal'?'selected':''}>Baptismal</option>
                    <option value="Confirmation" ${postData.certificate_type==='Confirmation'?'selected':''}>Confirmation</option>
                    <option value="Marriage" ${postData.certificate_type==='Marriage'?'selected':''}>Marriage</option>
                    <option value="Death" ${postData.certificate_type==='Death'?'selected':''}>Death</option>
                </select>
                <label id="certificate_date_label">Date of Baptism:</label>
                <input type="date" name="certificate_date" id="certificate_date_input" value="${postData.baptism_date||postData.certificate_date||''}" required>
                <label>Purpose:</label>
                <textarea name="purpose" required>${postData.purpose||''}</textarea>
                <div class="note">
                    📌 Certificate Requesting Requirements Reminder:<br>
                    • Valid ID<br>
                    • Proof of original certificate if available
                </div>
                <div class="note">
                    <label>Upload Requirements (if any):</label>
                    <input type="file" name="requirements[]" multiple>
                    <small>Attach documents (PDF or photos) if available.</small>
                </div>
            `;
        break;
    }

    formFields.innerHTML = html;
    
    // Initialize Certificate Requesting label if needed
    if (type === "Certificate Requesting") {
        setTimeout(() => {
            updateCertificateDateLabel();
        }, 50);
    }
}

document.addEventListener("DOMContentLoaded", function(){
    const typeDropdown = document.getElementById("appointment_type");
    if(typeDropdown){
        if("<?= $selectedType ?>" !== "") typeDropdown.value="<?= $selectedType ?>";
        updateForm();
        typeDropdown.addEventListener("change", updateForm);
    }

    // Only open survey if URL has success=1 and no 'survey_submitted' flag
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('success') === '1' && !urlParams.has('survey_submitted')) {
        setTimeout(() => {
            openSurveyModal();
        }, 250);
    }
});

/* Survey modal logic */
function openSurveyModal() {
    const overlay = document.getElementById('surveyOverlay');
    overlay.classList.add('open');

    // set service_type hidden field from selected appointment_type (if any)
    const svc = document.getElementById('appointment_type')?.value || '';
    const svcInput = document.getElementById('survey_service_type');
    if(svcInput) svcInput.value = svc;

    // also set appointment id hidden field if present
    const appointmentIdField = document.querySelector('input[name="appointment_id"]');
    if (appointmentIdField && window.appointmentId) appointmentIdField.value = window.appointmentId;
}
function closeSurveyModal() {
    const overlay = document.getElementById('surveyOverlay');
    overlay.classList.remove('open');
}

/* Rating UI: circles 1..5 */
let selectedRating = 0;
function selectRating(val) {
    selectedRating = val;
    document.querySelectorAll('.rating-circle').forEach(el => {
        el.classList.remove('selected','rating-1','rating-2','rating-3','rating-4','rating-5');
        const v = parseInt(el.dataset.value,10);
        if (v === val) {
            el.classList.add('selected','rating-' + v);
            el.setAttribute('aria-checked', 'true');
        } else {
            el.classList.add('rating-' + v);
            el.setAttribute('aria-checked', 'false');
        }
    });
    // set hidden input
    const hidden = document.getElementById('survey_rating_input');
    if (hidden) hidden.value = val;
    // Enable submit if NPS also valid
    checkSurveyValidity();
}

/* NPS slider UI */
function updateNPS(val) {
    const display = document.getElementById('npsValue');
    if (display) display.textContent = val;
    const hidden = document.getElementById('survey_nps_input');
    if (hidden) hidden.value = val;
    checkSurveyValidity();
}

/* reasons - count selection */
function checkSurveyValidity() {
    const rating = parseInt(document.getElementById('survey_rating_input')?.value || 0, 10);
    const nps = parseInt(document.getElementById('survey_nps_input')?.value ?? -1, 10);
    const submitBtn = document.getElementById('surveySubmitBtn');
    if (submitBtn) {
        if (rating >= 1 && rating <= 5 && nps >= 0 && nps <= 10) {
            submitBtn.disabled = false;
            submitBtn.textContent = "Submit Feedback";
        } else {
            submitBtn.disabled = true;
        }
    }
}

// keyboard navigation for rating circles
document.addEventListener('keydown', function(e){
    if(!document.querySelector('.survey-overlay.open')) return;
    const circles = Array.from(document.querySelectorAll('.rating-circle'));
    if(!circles.length) return;
    const active = document.activeElement;
    let idx = circles.indexOf(active);
    if(['ArrowLeft','ArrowUp'].includes(e.key)) {
        if(idx === -1) { circles[0].focus(); idx = 0; }
        else { idx = Math.max(0, idx - 1); circles[idx].focus(); }
        selectRating(parseInt(circles[idx].dataset.value,10));
        e.preventDefault();
    } else if(['ArrowRight','ArrowDown'].includes(e.key)) {
        if(idx === -1) { circles[0].focus(); idx = 0; }
        else { idx = Math.min(circles.length - 1, idx + 1); circles[idx].focus(); }
        selectRating(parseInt(circles[idx].dataset.value,10));
        e.preventDefault();
    }
});

/* ===== CALENDAR FUNCTIONS FOR WEDDING & FUNERAL ===== */
let weddingMonth = new Date();
let funeralMonth = new Date();
let weddingSelectedDate = null;
let weddingSelectedTime = null;
let funeralSelectedDate = null;
let funeralSelectedTime = null;
let weddingSlotsVisible = false; // Track if wedding slots are showing
let funeralSlotsVisible = false; // Track if funeral slots are showing

// Manila timezone is UTC+8
const MANILA_OFFSET = 8 * 60; // 480 minutes

// Helper function to parse date string in Manila timezone
function parseDateInManila(dateStr) {
    const [year, month, day] = dateStr.split('-');
    // Create UTC date
    const utcDate = new Date(Date.UTC(year, month - 1, day));
    // Get browser's timezone offset in minutes
    const browserOffset = new Date().getTimezoneOffset();
    // Adjust to Manila time: convert from browser timezone to UTC, then to Manila
    const manilaDate = new Date(utcDate.getTime() - (browserOffset * 60000) + (MANILA_OFFSET * 60000));
    return manilaDate;
}

// Helper function to format month/year display
function getMonthYearDisplay(dateObj) {
    const monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
                       'July', 'August', 'September', 'October', 'November', 'December'];
    const month = monthNames[dateObj.getMonth()];
    const year = dateObj.getFullYear();
    return `${month} ${year} (Manila Time)`;
}

// Baptism type change handler - show/hide calendar based on type selection
function handleBaptismTypeChange() {
    const typeSelect = document.getElementById('baptism_type');
    const wrapper = document.getElementById('baptism-calendar-wrapper');
    
    if (typeSelect?.value) {
        wrapper.style.display = 'block';
        setTimeout(() => {
            const minBaptism = new Date(); 
            minBaptism.setDate(new Date().getDate() + 1);
            const minBaptismStr = minBaptism.toISOString().split('T')[0];
            initBaptismCalendar(minBaptismStr);
        }, 50);
    } else {
        wrapper.style.display = 'none';
    }
}

function updateCertificateDateLabel() {
    const certificateType = document.getElementById('certificate_type_select')?.value;
    const dateLabel = document.getElementById('certificate_date_label');
    
    if (!dateLabel) return;
    
    const labelMap = {
        'Baptismal': 'Date of Baptism:',
        'Confirmation': 'Date of Confirmation:',
        'Marriage': 'Date of Marriage:',
        'Death': 'Date of Death:'
    };
    
    dateLabel.textContent = labelMap[certificateType] || 'Date:';
}

// Pre-Cana calendar variables
let preCanaMonth = new Date();
let preCanaSelectedDate = null;
let preCanaSelectedTime = null;
let preCanaSlotsVisible = false; // Track if Pre-Cana slots are showing

function initPreCanaCalendar() {
    // Initialize with today's date in Manila timezone (UTC+8)
    preCanaMonth = new Date();
    // Convert browser time to Manila time
    const browserOffset = new Date().getTimezoneOffset();
    preCanaMonth = new Date(preCanaMonth.getTime() - (browserOffset * 60000) + (MANILA_OFFSET * 60000));
    updatePreCanaCalendar();
    
    document.getElementById('precana-prev-month')?.addEventListener('click', (e) => {
        e.preventDefault();
        preCanaMonth.setMonth(preCanaMonth.getMonth() - 1);
        updatePreCanaCalendar();
    });
    
    document.getElementById('precana-next-month')?.addEventListener('click', (e) => {
        e.preventDefault();
        preCanaMonth.setMonth(preCanaMonth.getMonth() + 1);
        updatePreCanaCalendar();
    });
}

function updatePreCanaCalendar() {
    const year = preCanaMonth.getFullYear();
    const month = String(preCanaMonth.getMonth() + 1).padStart(2, '0');
    const monthStr = `${year}-${month}`;
    
    fetch(`calendar_api.php?action=get_month_availability&service=Pre-Cana%20Seminar&month=${monthStr}`)
        .then(r => r.json())
        .then(data => {
            if (data.success && data.calendar) {
                renderPreCanaCalendar(data.calendar);
            } else {
                console.error('Invalid calendar data:', data);
                document.getElementById('precana-calendar-grid').innerHTML = 
                    '<div style="color: red;">Error loading calendar</div>';
            }
        })
        .catch(err => {
            console.error('Error loading Pre-Cana calendar:', err);
            document.getElementById('precana-calendar-grid').innerHTML = 
                '<div style="color: red;">Error loading calendar</div>';
        });
}

function renderPreCanaCalendar(calendarData) {
    const grid = document.getElementById('precana-calendar-grid');
    if (!grid) return;
    
    // Check if calendarData is valid (should be an object, even if empty initially)
    if (!calendarData || typeof calendarData !== 'object') {
        grid.innerHTML = '<div style="color: red; padding: 20px; grid-column: 1/-1;">No calendar data available</div>';
        return;
    }
    
    // Update month display
    const monthDisplay = document.getElementById('precana-month-display');
    if (monthDisplay) {
        monthDisplay.textContent = getMonthYearDisplay(preCanaMonth);
    }
    
    grid.innerHTML = '';
    
    // Add weekday headers
    const weekdays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    weekdays.forEach(day => {
        const header = document.createElement('div');
        header.className = 'weekday-header';
        header.textContent = day;
        grid.appendChild(header);
    });
    
    // Add empty cells for days before month starts
    const firstDay = new Date(preCanaMonth.getFullYear(), preCanaMonth.getMonth(), 1);
    const startingDayOfWeek = firstDay.getDay();
    
    for (let i = 0; i < startingDayOfWeek; i++) {
        const empty = document.createElement('div');
        empty.className = 'calendar-day empty';
        grid.appendChild(empty);
    }
    
    // Add calendar days
    Object.values(calendarData).forEach(dayData => {
        const dayElement = document.createElement('div');
        dayElement.className = `calendar-day ${dayData.available ? 'available' : 'unavailable'}`;
        
        let content = `<div class="calendar-day-number">${dayData.day}</div>`;
        
        if (dayData.available) {
            content += `<div class="calendar-day-status">${dayData.availableCount}/${dayData.totalSlots}</div>`;
        } else if (dayData.reason) {
            content += `<div class="day-reason">${dayData.reason}</div>`;
        }
        
        dayElement.innerHTML = content;
        
        if (dayData.available) {
            dayElement.style.cursor = 'pointer';
            dayElement.onclick = () => selectPreCanaDate(dayData.date, dayData.slots);
        }
        
        grid.appendChild(dayElement);
    });
}

function selectPreCanaDate(dateStr, slots) {
    // Toggle: if same date is clicked, close the slots
    if (preCanaSelectedDate === dateStr && preCanaSlotsVisible) {
        preCanaSlotsVisible = false;
        document.getElementById('precana-slots-display').innerHTML = '';
        preCanaSelectedDate = null;
        preCanaSelectedTime = null;
        document.getElementById('custom_date_precana').value = '';
        return;
    }
    
    // Otherwise, open the slots for the clicked date
    preCanaSelectedDate = dateStr;
    preCanaSelectedTime = null;
    preCanaSlotsVisible = true;
    
    document.getElementById('appointment_type').value = 'Pre-Cana Seminar';
    // Set the hidden date field
    document.getElementById('custom_date_precana').value = dateStr;
    
    // Display time slots like Funeral (showing individual time slots)
    displayPreCanaSlots(slots, dateStr);
}

function displayPreCanaSlots(slots, dateStr) {
    const display = document.getElementById('precana-slots-display');
    
    if (slots.length === 0) {
        display.innerHTML = '<div class="no-slots-message">No slots available for selected date</div>';
        return;
    }
    
    let html = '<div class="slots-container">';
    html += `<div class="slots-header">Available Times for ${dateStr}</div>`;
    html += '<div class="time-slots">';
    
    slots.forEach(slot => {
        const isBooked = slot.isBooked;
        html += `
            <div class="time-slot ${isBooked ? 'booked' : 'available'} ${slot.time === preCanaSelectedTime ? 'selected' : ''}"
                 onclick="${!isBooked ? `selectPreCanaTime('${slot.time}')` : ''}"
                 title="${slot.time}">
                ${slot.time}
            </div>
        `;
    });
    
    html += '</div></div>';
    display.innerHTML = html;
}

function selectPreCanaTime(time) {
    preCanaSelectedTime = time;
    document.getElementById('custom_time_precana').value = time;
    
    // Refresh the display to show the selected time highlighted
    fetch(`calendar_api.php?action=get_day_slots&service=Pre-Cana%20Seminar&date=${preCanaSelectedDate}`)
        .then(r => r.json())
        .then(data => displayPreCanaSlots(data.slots, preCanaSelectedDate));
}


function initWeddingCalendar(minDate) {
    console.log('=== initWeddingCalendar called with minDate:', minDate);
    
    // Parse date in Manila timezone (UTC+8)
    weddingMonth = parseDateInManila(minDate);
    if (isNaN(weddingMonth.getTime())) {
        weddingMonth = new Date();
    }
    console.log('weddingMonth set to:', weddingMonth, '(Manila Time)');
    
    const prevBtn = document.getElementById('wedding-prev-month');
    const nextBtn = document.getElementById('wedding-next-month');
    console.log('Previous button:', prevBtn);
    console.log('Next button:', nextBtn);
    
    updateWeddingCalendar();
    
    if (prevBtn) {
        prevBtn.addEventListener('click', (e) => {
            e.preventDefault();
            console.log('Previous clicked');
            weddingMonth.setMonth(weddingMonth.getMonth() - 1);
            updateWeddingCalendar();
        });
        console.log('Previous button listener attached');
    }
    
    if (nextBtn) {
        nextBtn.addEventListener('click', (e) => {
            e.preventDefault();
            console.log('Next clicked');
            weddingMonth.setMonth(weddingMonth.getMonth() + 1);
            updateWeddingCalendar();
        });
        console.log('Next button listener attached');
    }
}

function updateWeddingCalendar() {
    const year = weddingMonth.getFullYear();
    const month = String(weddingMonth.getMonth() + 1).padStart(2, '0');
    const monthStr = `${year}-${month}`;
    
    const url = `calendar_api.php?action=get_month_availability&service=Wedding&month=${monthStr}`;
    console.log('Fetching Wedding calendar:', url);
    
    fetch(url)
        .then(r => {
            console.log('Response status:', r.status);
            return r.text();
        })
        .then(text => {
            console.log('Raw response:', text);
            try {
                const data = JSON.parse(text);
                console.log('Parsed data:', data);
                if (data.success && data.calendar) {
                    renderWeddingCalendar(data.calendar);
                } else {
                    console.error('Invalid calendar data:', data);
                    document.getElementById('wedding-calendar-grid').innerHTML = '<div style="color: red;">Error: ' + (data.error || 'No data') + '</div>';
                }
            } catch (e) {
                console.error('JSON parse error:', e);
                document.getElementById('wedding-calendar-grid').innerHTML = '<div style="color: red;">Parse error: ' + e.message + '</div>';
            }
        })
        .catch(err => {
            console.error('Error loading wedding calendar:', err);
            document.getElementById('wedding-calendar-grid').innerHTML = '<div style="color: red;">Error loading calendar</div>';
        });
}

function renderWeddingCalendar(calendarData) {
    const grid = document.getElementById('wedding-calendar-grid');
    if (!grid) {
        console.error('Grid element not found');
        return;
    }
    
    console.log('renderWeddingCalendar called with data:', calendarData);
    
    // Check if calendarData is valid (should be an object, even if empty initially)
    if (!calendarData || typeof calendarData !== 'object') {
        console.error('Invalid calendarData type:', typeof calendarData);
        grid.innerHTML = '<div style="color: red; padding: 20px; grid-column: 1/-1;">No calendar data available</div>';
        return;
    }
    
    const dataKeys = Object.keys(calendarData);
    console.log('Calendar data keys count:', dataKeys.length);
    
    if (dataKeys.length === 0) {
        console.warn('Calendar data is empty object');
    }
    
    // Update month display
    const monthDisplay = document.getElementById('wedding-month-display');
    if (monthDisplay) {
        monthDisplay.textContent = getMonthYearDisplay(weddingMonth);
    }
    
    grid.innerHTML = '';
    
    // Add weekday headers
    const weekdays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    weekdays.forEach(day => {
        const header = document.createElement('div');
        header.className = 'weekday-header';
        header.textContent = day;
        grid.appendChild(header);
    });
    
    // Add empty cells for days before month starts
    const firstDay = new Date(weddingMonth.getFullYear(), weddingMonth.getMonth(), 1);
    const startingDayOfWeek = firstDay.getDay();
    
    for (let i = 0; i < startingDayOfWeek; i++) {
        const empty = document.createElement('div');
        empty.className = 'calendar-day empty';
        grid.appendChild(empty);
    }
    
    // Add calendar days
    const daysArray = Object.values(calendarData);
    console.log('Adding', daysArray.length, 'days to calendar');
    
    daysArray.forEach(dayData => {
        console.log('Processing day:', dayData);
        const dayElement = document.createElement('div');
        dayElement.className = `calendar-day ${dayData.available ? 'available' : 'unavailable'}`;
        
        let content = `<div class="calendar-day-number">${dayData.day}</div>`;
        
        if (dayData.available) {
            content += `<div class="calendar-day-status">${dayData.availableCount}/${dayData.totalSlots}</div>`;
        } else if (dayData.reason) {
            content += `<div class="day-reason">${dayData.reason}</div>`;
        }
        
        dayElement.innerHTML = content;
        
        if (dayData.available) {
            dayElement.style.cursor = 'pointer';
            dayElement.onclick = () => {
                console.log('Wedding date clicked:', dayData.date);
                selectWeddingDate(dayData.date, dayData.slots);
            };
        }
        
        grid.appendChild(dayElement);
    });
    
    console.log('Wedding calendar rendering complete');
}

function selectWeddingDate(dateStr, slots) {
    // Toggle: if same date is clicked, close the slots
    if (weddingSelectedDate === dateStr && weddingSlotsVisible) {
        weddingSlotsVisible = false;
        document.getElementById('wedding-slots-display').innerHTML = '';
        weddingSelectedDate = null;
        weddingSelectedTime = null;
        document.querySelector('input[name="custom_date"]').value = '';
        document.querySelector('input[name="custom_time"]').value = '';
        return;
    }
    
    // Otherwise, open the slots for the clicked date
    weddingSelectedDate = dateStr;
    weddingSelectedTime = null;
    weddingSlotsVisible = true;
    
    document.getElementById('appointment_type').value = 'Wedding';
    document.querySelector('input[name="custom_date"]').value = dateStr;
    document.querySelector('input[name="custom_time"]').value = '';
    
    displayWeddingSlots(slots, dateStr);
}

function displayWeddingSlots(slots, dateStr) {
    const display = document.getElementById('wedding-slots-display');
    
    if (slots.length === 0) {
        display.innerHTML = '<div class="no-slots-message">No slots available for selected date</div>';
        return;
    }
    
    let html = '<div class="slots-container">';
    html += `<div class="slots-header">Available Times for ${dateStr}</div>`;
    html += '<div class="time-slots">';
    
    slots.forEach(slot => {
        const isBooked = slot.isBooked;
        html += `
            <div class="time-slot ${isBooked ? 'booked' : 'available'} ${slot.time === weddingSelectedTime ? 'selected' : ''}"
                 onclick="${!isBooked ? `selectWeddingTime('${slot.time}')` : ''}"
                 title="${slot.time}">
                ${slot.time}
            </div>
        `;
    });
    
    html += '</div></div>';
    display.innerHTML = html;
}

function selectWeddingTime(time) {
    weddingSelectedTime = time;
    document.querySelector('input[name="custom_time"]').value = time;
    
    // Refresh the display to show the selected time highlighted
    fetch(`calendar_api.php?action=get_day_slots&service=Wedding&date=${weddingSelectedDate}`)
        .then(r => r.json())
        .then(data => displayWeddingSlots(data.slots, weddingSelectedDate));
}

function initFuneralCalendar() {
    // Initialize with today's date in Manila timezone (UTC+8)
    funeralMonth = new Date();
    // Convert browser time to Manila time
    const browserOffset = new Date().getTimezoneOffset();
    funeralMonth = new Date(funeralMonth.getTime() - (browserOffset * 60000) + (MANILA_OFFSET * 60000));
    updateFuneralCalendar();
    
    document.getElementById('funeral-prev-month')?.addEventListener('click', (e) => {
        e.preventDefault();
        funeralMonth.setMonth(funeralMonth.getMonth() - 1);
        updateFuneralCalendar();
    });
    
    document.getElementById('funeral-next-month')?.addEventListener('click', (e) => {
        e.preventDefault();
        funeralMonth.setMonth(funeralMonth.getMonth() + 1);
        updateFuneralCalendar();
    });
}

function updateFuneralCalendar() {
    const year = funeralMonth.getFullYear();
    const month = String(funeralMonth.getMonth() + 1).padStart(2, '0');
    const monthStr = `${year}-${month}`;
    
    fetch(`calendar_api.php?action=get_month_availability&service=Funeral&month=${monthStr}`)
        .then(r => r.json())
        .then(data => {
            if (data.success && data.calendar) {
                renderFuneralCalendar(data.calendar);
            } else {
                console.error('Invalid calendar data:', data);
                document.getElementById('funeral-calendar-grid').innerHTML = '<div style="color: red;">Error loading calendar</div>';
            }
        })
        .catch(err => {
            console.error('Error loading funeral calendar:', err);
            document.getElementById('funeral-calendar-grid').innerHTML = '<div style="color: red;">Error loading calendar</div>';
        });
}

function renderFuneralCalendar(calendarData) {
    const grid = document.getElementById('funeral-calendar-grid');
    if (!grid) return;
    
    // Check if calendarData is valid (should be an object, even if empty initially)
    if (!calendarData || typeof calendarData !== 'object') {
        grid.innerHTML = '<div style="color: red; padding: 20px; grid-column: 1/-1;">No calendar data available</div>';
        return;
    }
    
    // Update month display
    const monthDisplay = document.getElementById('funeral-month-display');
    if (monthDisplay) {
        monthDisplay.textContent = getMonthYearDisplay(funeralMonth);
    }
    
    grid.innerHTML = '';
    
    // Add weekday headers
    const weekdays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    weekdays.forEach(day => {
        const header = document.createElement('div');
        header.className = 'weekday-header';
        header.textContent = day;
        grid.appendChild(header);
    });
    
    // Add empty cells for days before month starts
    const firstDay = new Date(funeralMonth.getFullYear(), funeralMonth.getMonth(), 1);
    const startingDayOfWeek = firstDay.getDay();
    
    for (let i = 0; i < startingDayOfWeek; i++) {
        const empty = document.createElement('div');
        empty.className = 'calendar-day empty';
        grid.appendChild(empty);
    }
    
    // Add calendar days
    Object.values(calendarData).forEach(dayData => {
        const dayElement = document.createElement('div');
        dayElement.className = `calendar-day ${dayData.available ? 'available' : 'unavailable'}`;
        
        let content = `<div class="calendar-day-number">${dayData.day}</div>`;
        
        if (dayData.available) {
            content += `<div class="calendar-day-status">${dayData.availableCount}/${dayData.totalSlots}</div>`;
        } else if (dayData.reason) {
            content += `<div class="day-reason">${dayData.reason}</div>`;
        }
        
        dayElement.innerHTML = content;
        
        if (dayData.available) {
            dayElement.style.cursor = 'pointer';
            dayElement.onclick = () => selectFuneralDate(dayData.date, dayData.slots);
        }
        
        grid.appendChild(dayElement);
    });
}

function selectFuneralDate(dateStr, slots) {
    // Toggle: if same date is clicked, close the slots
    if (funeralSelectedDate === dateStr && funeralSlotsVisible) {
        funeralSlotsVisible = false;
        document.getElementById('funeral-slots-display').innerHTML = '';
        funeralSelectedDate = null;
        funeralSelectedTime = null;
        document.querySelector('input[name="custom_date"]').value = '';
        document.querySelector('input[name="custom_time"]').value = '';
        return;
    }
    
    // Otherwise, open the slots for the clicked date
    funeralSelectedDate = dateStr;
    funeralSelectedTime = null;
    funeralSlotsVisible = true;
    
    document.getElementById('appointment_type').value = 'Funeral';
    document.querySelector('input[name="custom_date"]').value = dateStr;
    document.querySelector('input[name="custom_time"]').value = '';
    
    displayFuneralSlots(slots, dateStr);
}

function displayFuneralSlots(slots, dateStr) {
    const display = document.getElementById('funeral-slots-display');
    
    if (slots.length === 0) {
        display.innerHTML = '<div class="no-slots-message">No slots available for selected date</div>';
        return;
    }
    
    let html = '<div class="slots-container">';
    html += `<div class="slots-header">Available Times for ${dateStr}</div>`;
    html += '<div class="time-slots">';
    
    slots.forEach(slot => {
        const isBooked = slot.isBooked;
        html += `
            <div class="time-slot ${isBooked ? 'booked' : 'available'} ${slot.time === funeralSelectedTime ? 'selected' : ''}"
                 onclick="${!isBooked ? `selectFuneralTime('${slot.time}')` : ''}"
                 title="${slot.time}">
                ${slot.time}
            </div>
        `;
    });
    
    html += '</div></div>';
    display.innerHTML = html;
}

function selectFuneralTime(time) {
    funeralSelectedTime = time;
    document.querySelector('input[name="custom_time"]').value = time;
    
    // Refresh the display to show the selected time highlighted
    fetch(`calendar_api.php?action=get_day_slots&service=Funeral&date=${funeralSelectedDate}`)
        .then(r => r.json())
        .then(data => displayFuneralSlots(data.slots, funeralSelectedDate));
}

/* ===== BAPTISM CALENDAR FUNCTIONS ===== */
let baptismMonth = new Date();
let baptismSelectedDate = null;
let baptismSelectedTime = null;
let baptismSlotsVisible = false; // Track if baptism slots are showing

function initBaptismCalendar(minDate) {
    // Parse date in Manila timezone (UTC+8)
    baptismMonth = parseDateInManila(minDate);
    if (isNaN(baptismMonth.getTime())) {
        baptismMonth = new Date();
    }
    updateBaptismCalendar();
    
    // Get current baptism type if set
    const typeSelect = document.getElementById('baptism_type');
    const baptismType = typeSelect?.value || 'Regular';
    
    document.getElementById('baptism-prev-month')?.addEventListener('click', (e) => {
        e.preventDefault();
        baptismMonth.setMonth(baptismMonth.getMonth() - 1);
        updateBaptismCalendar();
    });
    
    document.getElementById('baptism-next-month')?.addEventListener('click', (e) => {
        e.preventDefault();
        baptismMonth.setMonth(baptismMonth.getMonth() + 1);
        updateBaptismCalendar();
    });
    
    // Update when baptism type changes
    if (typeSelect) {
        typeSelect.addEventListener('change', () => {
            updateBaptismCalendar();
        });
    }
}

function updateBaptismCalendar() {
    const year = baptismMonth.getFullYear();
    const month = String(baptismMonth.getMonth() + 1).padStart(2, '0');
    const monthStr = `${year}-${month}`;
    
    // Get the baptism type to determine service type for API
    const typeSelect = document.getElementById('baptism_type');
    const baptismType = typeSelect?.value || 'Regular';
    
    console.log('[Baptism] Updating calendar for', monthStr, 'type:', baptismType);
    
    fetch(`calendar_api.php?action=get_month_availability&service=Baptism&baptism_type=${baptismType}&month=${monthStr}`)
        .then(r => r.json())
        .then(data => {
            console.log('[Baptism] Calendar API response:', data);
            if (data.success && data.calendar) {
                console.log('[Baptism] Calendar has', Object.keys(data.calendar).length, 'days');
                renderBaptismCalendar(data.calendar);
            } else {
                console.error('[Baptism] Invalid calendar data:', data);
                document.getElementById('baptism-calendar-grid').innerHTML = '<div style="color: red;">Error loading calendar: ' + (data.error || 'Unknown error') + '</div>';
            }
        })
        .catch(err => {
            console.error('[Baptism] Error loading baptism calendar:', err);
            document.getElementById('baptism-calendar-grid').innerHTML = '<div style="color: red;">Error loading calendar: ' + err.message + '</div>';
        });
}

function renderBaptismCalendar(calendarData) {
    console.log('[Baptism] renderBaptismCalendar called with data:', calendarData);
    const grid = document.getElementById('baptism-calendar-grid');
    if (!grid) {
        console.error('[Baptism] Calendar grid element not found');
        return;
    }
    
    // Check if calendarData is valid (should be an object, even if empty initially)
    if (!calendarData || typeof calendarData !== 'object') {
        console.error('[Baptism] Invalid calendarData');
        grid.innerHTML = '<div style="color: red; padding: 20px; grid-column: 1/-1;">No calendar data available</div>';
        return;
    }
    
    // Update month display
    const monthDisplay = document.getElementById('baptism-month-display');
    if (monthDisplay) {
        monthDisplay.textContent = getMonthYearDisplay(baptismMonth);
    }
    
    grid.innerHTML = '';
    
    // Add weekday headers
    const weekdays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    weekdays.forEach(day => {
        const header = document.createElement('div');
        header.className = 'weekday-header';
        header.textContent = day;
        grid.appendChild(header);
    });
    
    // Add empty cells for days before month starts
    const firstDay = new Date(baptismMonth.getFullYear(), baptismMonth.getMonth(), 1);
    const startingDayOfWeek = firstDay.getDay();
    
    for (let i = 0; i < startingDayOfWeek; i++) {
        const empty = document.createElement('div');
        empty.className = 'calendar-day empty';
        grid.appendChild(empty);
    }
    
    // Add calendar days
    const daysData = Object.values(calendarData);
    console.log('[Baptism] Rendering', daysData.length, 'days');
    
    daysData.forEach((dayData, index) => {
        console.log('[Baptism] Day', index + ':', dayData.date, 'available:', dayData.available, 'slots:', dayData.slots ? dayData.slots.length : 0);
        
        const dayElement = document.createElement('div');
        dayElement.className = `calendar-day ${dayData.available ? 'available' : 'unavailable'}`;
        
        let content = `<div class="calendar-day-number">${dayData.day}</div>`;
        
        if (dayData.available) {
            content += `<div class="calendar-day-status">${dayData.availableCount}/${dayData.totalSlots}</div>`;
        } else if (dayData.reason) {
            content += `<div class="day-reason">${dayData.reason}</div>`;
        }
        
        dayElement.innerHTML = content;
        
        if (dayData.available && dayData.slots && Array.isArray(dayData.slots)) {
            dayElement.style.cursor = 'pointer';
            dayElement.onclick = () => selectBaptismDate(dayData.date, dayData.slots);
        } else if (dayData.available) {
            console.warn('[Baptism] Available day but no valid slots array:', dayData);
        }
        
        grid.appendChild(dayElement);
    });
}

function selectBaptismDate(dateStr, slots) {
    console.log('[Baptism] selectBaptismDate called with dateStr:', dateStr, 'slots:', slots);
    
    // Toggle: if same date is clicked, close the slots
    if (baptismSelectedDate === dateStr && baptismSlotsVisible) {
        console.log('[Baptism] Toggling OFF - same date clicked again');
        baptismSlotsVisible = false;
        document.getElementById('baptism-slots-display').innerHTML = '';
        baptismSelectedDate = null;
        baptismSelectedTime = null;
        document.getElementById('custom_date_baptism').value = '';
        document.getElementById('baptism_time_hidden').value = '';
        return;
    }
    
    // Otherwise, open the slots for the clicked date
    console.log('[Baptism] Setting date and showing slots');
    baptismSelectedDate = dateStr;
    baptismSelectedTime = null;
    baptismSlotsVisible = true;
    
    document.getElementById('appointment_type').value = 'Baptism';
    document.getElementById('custom_date_baptism').value = dateStr;
    document.getElementById('baptism_time_hidden').value = '';
    
    console.log('[Baptism] Hidden fields updated:');
    console.log('  custom_date_baptism =', document.getElementById('custom_date_baptism').value);
    console.log('  baptism_time_hidden =', document.getElementById('baptism_time_hidden').value);
    
    // Ensure slots array is valid
    if (!Array.isArray(slots) || slots.length === 0) {
        console.warn('[Baptism] Warning: slots is not a valid array or is empty');
        console.log('[Baptism] Re-fetching slots from API for date:', dateStr);
        
        const typeSelect = document.getElementById('baptism_type');
        const baptismType = typeSelect?.value || 'Regular';
        
        fetch(`calendar_api.php?action=get_day_slots&service=Baptism&baptism_type=${baptismType}&date=${dateStr}`)
            .then(r => r.json())
            .then(data => {
                console.log('[Baptism] API response:', data);
                if (data.success && Array.isArray(data.slots)) {
                    displayBaptismSlots(data.slots, dateStr);
                } else {
                    console.error('[Baptism] Invalid API response');
                    displayBaptismSlots([], dateStr);
                }
            })
            .catch(err => {
                console.error('[Baptism] API fetch error:', err);
                displayBaptismSlots([], dateStr);
            });
    } else {
        displayBaptismSlots(slots, dateStr);
    }
}

function displayBaptismSlots(slots, dateStr) {
    console.log('[Baptism] displayBaptismSlots called with', slots.length, 'slots for date', dateStr);
    const display = document.getElementById('baptism-slots-display');
    
    if (!slots || slots.length === 0) {
        console.warn('[Baptism] No slots available for', dateStr);
        display.innerHTML = '<div class="no-slots-message" style="padding:15px; background:#ffebee; color:#c62828; border-radius:4px; text-align:center;">No slots available for selected date</div>';
        return;
    }
    
    let html = '<div class="slots-container" style="padding:15px; border:1px solid #ddd; border-radius:4px; background:#fafafa;">';
    html += `<div class="slots-header" style="font-weight:bold; margin-bottom:10px; color:#333;">Available Times for ${dateStr}</div>`;
    html += '<div class="time-slots" style="display:grid; grid-template-columns:repeat(auto-fill, minmax(100px, 1fr)); gap:8px;">';
    
    slots.forEach((slot, index) => {
        const isBooked = slot.isBooked;
        const isSelected = slot.time === baptismSelectedTime;
        const bgColor = isSelected ? '#2196F3' : (isBooked ? '#CCCCCC' : '#4CAF50');
        const textColor = isSelected ? 'white' : (isBooked ? '#666' : 'white');
        const cursor = isBooked ? 'not-allowed' : 'pointer';
        
        console.log(`[Baptism] Slot ${index}: ${slot.time}, booked=${isBooked}, selected=${isSelected}`);
        
        html += `
            <div class="time-slot" 
                 data-time="${slot.time}"
                 onclick="${!isBooked ? `selectBaptismTime('${slot.time}')` : ''}"
                 style="
                    padding: 10px;
                    text-align: center;
                    border-radius: 4px;
                    background-color: ${bgColor};
                    color: ${textColor};
                    cursor: ${cursor};
                    user-select: none;
                    border: 1px solid #999;
                    font-weight: bold;
                    transition: all 0.2s;
                 "
                 title="${slot.time} - ${isBooked ? 'Booked' : 'Available'}"
                 ${isBooked ? 'disabled' : ''}>
                ${slot.time}
            </div>
        `;
    });
    
    html += '</div></div>';
    display.innerHTML = html;
    console.log('[Baptism] Slots rendered successfully');
}

function selectBaptismTime(time) {
    console.log('[Baptism] selectBaptismTime called with time:', time);
    console.log('[Baptism] Current baptismSelectedDate:', baptismSelectedDate);
    
    baptismSelectedTime = time;
    const hiddenField = document.getElementById('baptism_time_hidden');
    console.log('[Baptism] Hidden field element:', hiddenField);
    console.log('[Baptism] Before setting value - field value:', hiddenField?.value);
    
    if (hiddenField) {
        hiddenField.value = time;
        console.log('[Baptism] After setting value - field value:', hiddenField.value);
    }
    
    // Refresh the display to show the selected time highlighted
    const typeSelect = document.getElementById('baptism_type');
    const baptismType = typeSelect?.value || 'Regular';
    fetch(`calendar_api.php?action=get_day_slots&service=Baptism&baptism_type=${encodeURIComponent(baptismType)}&date=${baptismSelectedDate}`)
        .then(r => r.json())
        .then(data => {
            console.log('[Baptism] Slots refreshed, current selection:', baptismSelectedTime, 'type:', baptismType);
            displayBaptismSlots(data.slots, baptismSelectedDate);
        });
}


/* ===== BLESSING CALENDAR FUNCTIONS ===== */
let blessingMonth = new Date();
let blessingSelectedDate = null;
let blessingSelectedTime = null;
let blessingSlotsVisible = false; // Track if blessing slots are showing

function initBlessingCalendar(minDate) {
    console.log('=== initBlessingCalendar called with minDate:', minDate);
    
    // Parse date in Manila timezone (UTC+8)
    blessingMonth = parseDateInManila(minDate);
    if (isNaN(blessingMonth.getTime())) {
        blessingMonth = new Date();
    }
    console.log('blessingMonth set to:', blessingMonth, '(Manila Time)');
    
    const prevBtn = document.getElementById('blessing-prev-month');
    const nextBtn = document.getElementById('blessing-next-month');
    console.log('Previous button:', prevBtn);
    console.log('Next button:', nextBtn);
    
    updateBlessingCalendar();
    
    if (prevBtn) {
        prevBtn.addEventListener('click', (e) => {
            e.preventDefault();
            console.log('Previous clicked');
            blessingMonth.setMonth(blessingMonth.getMonth() - 1);
            updateBlessingCalendar();
        });
        console.log('Previous button listener attached');
    }
    
    if (nextBtn) {
        nextBtn.addEventListener('click', (e) => {
            e.preventDefault();
            console.log('Next clicked');
            blessingMonth.setMonth(blessingMonth.getMonth() + 1);
            updateBlessingCalendar();
        });
        console.log('Next button listener attached');
    }
}

function updateBlessingCalendar() {
    const year = blessingMonth.getFullYear();
    const month = String(blessingMonth.getMonth() + 1).padStart(2, '0');
    const monthStr = `${year}-${month}`;
    
    const url = `calendar_api.php?action=get_month_availability&service=Blessing&month=${monthStr}`;
    console.log('Fetching Blessing calendar:', url);
    
    fetch(url)
        .then(r => {
            console.log('Response status:', r.status);
            return r.text();
        })
        .then(text => {
            console.log('Raw response:', text);
            try {
                const data = JSON.parse(text);
                console.log('Parsed data:', data);
                if (data.success && data.calendar) {
                    renderBlessingCalendar(data.calendar);
                } else {
                    console.error('Invalid calendar data:', data);
                    document.getElementById('blessing-calendar-grid').innerHTML = '<div style="color: red;">Error: ' + (data.error || 'No data') + '</div>';
                }
            } catch (e) {
                console.error('JSON parse error:', e);
                document.getElementById('blessing-calendar-grid').innerHTML = '<div style="color: red;">Parse error: ' + e.message + '</div>';
            }
        })
        .catch(err => {
            console.error('Error loading blessing calendar:', err);
            document.getElementById('blessing-calendar-grid').innerHTML = '<div style="color: red;">Error loading calendar</div>';
        });
}

function renderBlessingCalendar(calendarData) {
    const grid = document.getElementById('blessing-calendar-grid');
    if (!grid) {
        console.error('Blessing grid element not found');
        return;
    }
    
    console.log('renderBlessingCalendar called with data:', calendarData);
    
    // Check if calendarData is valid (should be an object, even if empty initially)
    if (!calendarData || typeof calendarData !== 'object') {
        console.error('Invalid calendarData type:', typeof calendarData);
        grid.innerHTML = '<div style="color: red; padding: 20px; grid-column: 1/-1;">No calendar data available</div>';
        return;
    }
    
    const dataKeys = Object.keys(calendarData);
    console.log('Calendar data keys count:', dataKeys.length);
    
    if (dataKeys.length === 0) {
        console.warn('Calendar data is empty object');
    }
    
    // Update month display
    const monthDisplay = document.getElementById('blessing-month-display');
    if (monthDisplay) {
        monthDisplay.textContent = getMonthYearDisplay(blessingMonth);
    }
    
    grid.innerHTML = '';
    
    // Add weekday headers
    const weekdays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    weekdays.forEach(day => {
        const header = document.createElement('div');
        header.className = 'weekday-header';
        header.textContent = day;
        grid.appendChild(header);
    });
    
    // Add empty cells for days before month starts
    const firstDay = new Date(blessingMonth.getFullYear(), blessingMonth.getMonth(), 1);
    const startingDayOfWeek = firstDay.getDay();
    
    for (let i = 0; i < startingDayOfWeek; i++) {
        const empty = document.createElement('div');
        empty.className = 'calendar-day empty';
        grid.appendChild(empty);
    }
    
    // Add calendar days
    const daysArray = Object.values(calendarData);
    console.log('Adding', daysArray.length, 'days to calendar');
    
    daysArray.forEach(dayData => {
        console.log('Processing day:', dayData);
        const dayElement = document.createElement('div');
        dayElement.className = `calendar-day ${dayData.available ? 'available' : 'unavailable'}`;
        
        let content = `<div class="calendar-day-number">${dayData.day}</div>`;
        
        if (dayData.available) {
            content += `<div class="calendar-day-status">${dayData.availableCount}/${dayData.totalSlots}</div>`;
        } else if (dayData.reason) {
            content += `<div class="day-reason">${dayData.reason}</div>`;
        }
        
        dayElement.innerHTML = content;
        
        if (dayData.available) {
            dayElement.style.cursor = 'pointer';
            dayElement.onclick = () => {
                console.log('Blessing date clicked:', dayData.date);
                selectBlessingDate(dayData.date, dayData.slots);
            };
        }
        
        grid.appendChild(dayElement);
    });
    
    console.log('Blessing calendar rendering complete');
}

function selectBlessingDate(dateStr, slots) {
    // Toggle: if same date is clicked, close the slots
    if (blessingSelectedDate === dateStr && blessingSlotsVisible) {
        blessingSlotsVisible = false;
        document.getElementById('blessing-slots-display').innerHTML = '';
        blessingSelectedDate = null;
        blessingSelectedTime = null;
        document.getElementById('custom_date_blessing').value = '';
        document.getElementById('custom_time_blessing').value = '';
        return;
    }
    
    // Otherwise, open the slots for the clicked date
    blessingSelectedDate = dateStr;
    blessingSelectedTime = null;
    blessingSlotsVisible = true;
    
    document.getElementById('appointment_type').value = 'Blessing';
    document.getElementById('custom_date_blessing').value = dateStr;
    document.getElementById('custom_time_blessing').value = '';
    
    displayBlessingSlots(slots, dateStr);
}

function displayBlessingSlots(slots, dateStr) {
    const display = document.getElementById('blessing-slots-display');
    
    if (slots.length === 0) {
        display.innerHTML = '<div class="no-slots-message">No slots available for selected date</div>';
        return;
    }
    
    let html = '<div class="slots-container">';
    html += `<div class="slots-header">Available Times for ${dateStr}</div>`;
    html += '<div class="time-slots">';
    
    slots.forEach(slot => {
        const isBooked = slot.isBooked;
        html += `
            <div class="time-slot ${isBooked ? 'booked' : 'available'} ${slot.time === blessingSelectedTime ? 'selected' : ''}"
                 onclick="${!isBooked ? `selectBlessingTime('${slot.time}')` : ''}"
                 title="${slot.time}">
                ${slot.time}
            </div>
        `;
    });
    
    html += '</div></div>';
    display.innerHTML = html;
}

function selectBlessingTime(time) {
    blessingSelectedTime = time;
    document.getElementById('custom_time_blessing').value = time;
    
    // Refresh the display to show the selected time highlighted
    fetch(`calendar_api.php?action=get_day_slots&service=Blessing&date=${blessingSelectedDate}`)
        .then(r => r.json())
        .then(data => displayBlessingSlots(data.slots, blessingSelectedDate));
}


</script>
</head>
<body>
<div class="form-container" id="pageContent">
<button class="back-btn" onclick="window.location.href='dashboard.php'">Go to Dashboard</button>
<h2>Add Appointment</h2>

<?php if($error): ?>
    <div class="error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if(!empty($success)): ?>
    <div class="success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<?php if(!empty($survey_success)): ?>
    <div class="success" style="margin-top:12px;"><?= htmlspecialchars($survey_success) ?></div>
<?php endif; ?>

<?php if(!empty($survey_error)): ?>
    <div class="error" style="margin-top:12px;"><?= htmlspecialchars($survey_error) ?></div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data" onsubmit="
    console.log('[Form Submit] Form is being submitted');
    const formData = new FormData(this);
    console.log('[Form Submit] Form data entries:');
    for (let [key, value] of formData.entries()) {
        if (key === 'requirements[]' || key === 'requirements') {
            console.log(`  ${key}: [File]`);
        } else {
            console.log(`  ${key}: ${value}`);
        }
    }
    // Specifically log baptism fields if present
    if (document.getElementById('appointment_type')?.value === 'Baptism') {
        console.log('[Baptism Debug] Form is for Baptism service');
        console.log('[Baptism Debug] custom_date value:', document.getElementById('custom_date_baptism')?.value);
        console.log('[Baptism Debug] baptism_time value:', document.getElementById('baptism_time_hidden')?.value);
        console.log('[Baptism Debug] baptism_type value:', document.getElementById('baptism_type')?.value);
    }
    const btn=this.querySelector('button[type=submit]');
    btn.disabled=true;
    btn.textContent='Submitting...';
">
<label>Full Name:</label>
<input type="text" name="user_fullname" pattern="[A-Z][a-z'-]+( [A-Z][a-z'-]+)+" title="Full name must have capitalized first and last name (e.g. Juan Dela Cruz)" value="<?= htmlspecialchars($postData['user_fullname'] ?? '') ?>" required>

<label>Appointment Type:</label>
<select name="appointment_type" id="appointment_type"required>
<option value="">Select Service</option>
<?php foreach($service_prices as $type=>$price){ if($type==="Regular Baptism"||$type==="Special Baptism") continue; ?>
<option value="<?= htmlspecialchars($type) ?>" <?= $selectedType===$type?'selected':'' ?>><?= htmlspecialchars($type) ?></option>
<?php } ?>
<option value="Baptism" <?= $selectedType==="Baptism"?'selected':'' ?>>Baptism</option>
</select>
<div id="price-box" class="price"></div>
<div id="service-form"></div>
<button type="submit">Submit</button>
</form>
</div>

<!-- SURVEY OVERLAY (modal) -->
<div id="surveyOverlay" class="survey-overlay" aria-hidden="true" role="dialog" aria-modal="true">
    <div class="survey-modal" role="document" aria-labelledby="surveyTitle">
        <div class="survey-header">
            <div>
                <h3 id="surveyTitle">Quick Service Feedback</h3>
                <div class="survey-sub">Help us improve — this takes less than a minute. Your answers are anonymous unless you choose to identify yourself.</div>
            </div>
            <div style="text-align:right;">
                <button onclick="closeSurveyModal()" class="btn-muted" style="padding:8px 10px;border-radius:8px;background:#111;color:#ddd;border:1px solid rgba(255,255,255,0.04);">Close</button>
            </div>
        </div>

       <form method="POST" id="surveyForm">
            <input type="hidden" name="appointment_id" value="<?= intval($appointment_id) ?>">
            <input type="hidden" id="survey_service_type" name="service_type" value="">
            <input type="hidden" id="survey_rating_input" name="rating" value="">
            <input type="hidden" id="survey_nps_input" name="nps" value="5">

            <!-- 1) Star rating (1-5) -->
            <div style="margin-bottom:6px;"><strong>1) Overall, how would you rate your booking experience?</strong></div>
            <div class="rating-row" role="radiogroup" aria-label="Overall rating">
                <div class="rating-circle" tabindex="0" role="radio" aria-checked="false" data-value="1" onclick="selectRating(1)" onkeydown="if(event.key==='Enter')selectRating(1)">1</div>
                <div class="rating-circle" tabindex="0" role="radio" aria-checked="false" data-value="2" onclick="selectRating(2)" onkeydown="if(event.key==='Enter')selectRating(2)">2</div>
                <div class="rating-circle" tabindex="0" role="radio" aria-checked="false" data-value="3" onclick="selectRating(3)" onkeydown="if(event.key==='Enter')selectRating(3)">3</div>
                <div class="rating-circle" tabindex="0" role="radio" aria-checked="false" data-value="4" onclick="selectRating(4)" onkeydown="if(event.key==='Enter')selectRating(4)">4</div>
                <div class="rating-circle" tabindex="0" role="radio" aria-checked="false" data-value="5" onclick="selectRating(5)" onkeydown="if(event.key==='Enter')selectRating(5)">5</div>
            </div>

            <!-- 2) NPS / likelihood to recommend -->
            <div style="margin-bottom:6px;"><strong>2) How likely are you to recommend our service to a friend or family? (0 = Not at all, 10 = Extremely likely)</strong></div>
            <div class="nps-row">
                <input type="range" min="0" max="10" value="5" class="nps-slider" oninput="updateNPS(this.value)" onchange="updateNPS(this.value)">
                <div style="display:flex;justify-content:space-between;font-size:13px;color:#bbb;"><span>0</span><span id="npsValue">5</span><span>10</span></div>
            </div>

            <!-- 3) Multiple choice reasons -->
            <div style="margin-bottom:6px;"><strong>3) What influenced your rating? (choose all that apply)</strong></div>
            <div class="reasons" role="group" aria-label="Reasons">
                <label><input type="checkbox" name="reasons[]" value="Ease of booking"> Ease of booking</label>
                <label><input type="checkbox" name="reasons[]" value="Clear requirements"> Clear requirements</label>
                <label><input type="checkbox" name="reasons[]" value="Availability of slots"> Availability of slots</label>
                <label><input type="checkbox" name="reasons[]" value="Staff helpfulness"> Staff helpfulness</label>
                <label><input type="checkbox" name="reasons[]" value="Timeliness"> Timeliness</label>
                <label><input type="checkbox" name="reasons[]" value="Website / UX"> Website / UX</label>
            </div>

            <!-- 4) Staff helpful (yes/no) -->
            <div class="helper-row">
                <div><strong>4) Was the staff or system helpful?</strong></div>
                <div style="margin-left:12px;">
                    <label style="margin-right:8px;"><input type="radio" name="helpful" value="yes" checked> Yes</label>
                    <label><input type="radio" name="helpful" value="no"> No</label>
                </div>
            </div>

            <!-- 5) Comments -->
            <div style="margin-bottom:6px;"><strong>5) Any additional comments? (optional)</strong></div>
            <textarea name="comments" placeholder="Tell us what we can improve..." ></textarea>

            <div class="survey-footer">
                <div class="survey-note">Your feedback is valuable and helps us improve our services.</div>
                <div style="display:flex; gap:8px;">
                    <button type="button" class="btn-muted" onclick="closeSurveyModal()">Maybe later</button>
                    <button type="submit" id="surveySubmitBtn" name="survey_submit" class="btn-primary" disabled>Submit Feedback</button>
                </div>
            </div>
        </form>
    </div>
</div>
<!-- SURVEY THANK YOU OVERLAY -->
<div id="surveyThankYouOverlay" class="survey-overlay" aria-hidden="true" role="dialog" aria-modal="true">
    <div class="survey-modal" role="document" style="text-align:center;">
        <h3>🎉 Thank You for Your Feedback!</h3>
        <p>Have a nice day!</p>
        <div style="margin-top:20px; display:flex; justify-content:center; gap:10px;">
            <button class="btn-muted" onclick="closeThankYouModal()">Close</button>
            <button class="btn-primary" onclick="window.location.href='dashboard.php'">Back to Dashboard</button>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize rating circles appearance (black until chosen)
    document.querySelectorAll('.rating-circle').forEach(el => {
        el.style.background = '#000';
    });

    // Set default NPS hidden input
    const npsHidden = document.getElementById('survey_nps_input');
    if (npsHidden) npsHidden.value = 5;

    // Check survey validity if you have a function
    checkSurveyValidity();

    // Open Thank You modal if survey was submitted via redirect
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('survey_submitted')) {
        openThankYouModal();
    }
});

// ensure service_type field exists and is set when modal opens
function openSurveyModal() {
    const overlay = document.getElementById('surveyOverlay');
    overlay.classList.add('open');
    const svc = document.getElementById('appointment_type')?.value || '';
    const svcInput = document.getElementById('survey_service_type');
    if (svcInput) svcInput.value = svc;
    const appointmentIdField = document.querySelector('input[name="appointment_id"]');
    if (appointmentIdField && window.appointmentId) appointmentIdField.value = window.appointmentId;
}
function closeSurveyModal() {
    const overlay = document.getElementById('surveyOverlay');
    overlay.classList.remove('open');
}

// Submit survey via AJAX, ensure server saves (we include ajax flag)
document.getElementById('surveyForm').addEventListener('submit', submitSurvey);

function submitSurvey(e) {
    e.preventDefault();
    const form = document.getElementById('surveyForm');
    const formData = new FormData(form);

    // include flags so server knows this is a survey and should return JSON
    formData.append('survey_submit', '1');
    formData.append('ajax', '1');

    const submitBtn = document.getElementById('surveySubmitBtn');
    submitBtn.disabled = true;
    submitBtn.textContent = "Submitting...";

    fetch("", {
        method: "POST",
        body: formData,
        credentials: 'same-origin'
    })
    .then(res => {
        if (!res.ok) throw new Error('Network response not ok: ' + res.status);
        return res.json();
    })
    .then(json => {
        if (json && json.success) {
            // show thank you overlay and keep it until user closes
            closeSurveyModal();
            openThankYouModal();
        } else {
            alert(json.message || "Failed to submit survey. Try again.");
            submitBtn.disabled = false;
            submitBtn.textContent = "Submit Feedback";
            console.error('Server DB error:', json.db_error || '');
        }
    })
    .catch(err => {
        console.error('Survey submission error:', err);
        alert("Failed to submit survey. Try again.");
        submitBtn.disabled = false;
        submitBtn.textContent = "Submit Feedback";
    });
}

// thank you modal functions
function openThankYouModal() {
    const overlay = document.getElementById('surveyThankYouOverlay');
    overlay.classList.add('open');
}
function closeThankYouModal() {
    const overlay = document.getElementById('surveyThankYouOverlay');
    overlay.classList.remove('open');
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