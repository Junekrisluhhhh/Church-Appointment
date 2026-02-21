<?php
session_start();
include "db.php";

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'user') {
    header("Location: index.php");
    exit;
}

$user = $_SESSION['user'];

// Get appointment ID from query param
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid appointment ID.");
}

$appointment_id = intval($_GET['id']);

// Fetch the appointment and verify ownership
$stmt = $conn->prepare("SELECT * FROM appointments WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $appointment_id, $user['id']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Appointment not found or you don't have permission.");
}

$appointment = $result->fetch_assoc();

// Function to fetch booked slots on a date (exclude current appointment)
function getBookedSlots($conn, $date, $exclude_appointment_id = null) {
    $date_start = $date . ' 00:00:00';
    $date_end = $date . ' 23:59:59';

    if ($exclude_appointment_id) {
        $sql = "SELECT TIME(appointment_date) as time FROM appointments WHERE appointment_date BETWEEN ? AND ? AND id != ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssi", $date_start, $date_end, $exclude_appointment_id);
    } else {
        $sql = "SELECT TIME(appointment_date) as time FROM appointments WHERE appointment_date BETWEEN ? AND ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $date_start, $date_end);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $booked_slots = [];
    while ($row = $result->fetch_assoc()) {
        $booked_slots[] = substr($row['time'], 0, 5); // "HH:MM"
    }
    return $booked_slots;
}

// Define available time slots (change these as needed)
$available_slots = [
    "08:00", "09:00", "10:00", "11:00", "13:00", "14:00", "15:00", "16:00"
];

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = trim($_POST['type'] ?? '');
    $date = $_POST['date'] ?? '';
    $time = $_POST['time'] ?? '';

    if (!$type || !$date || !$time) {
        $error = "Please fill all fields.";
    } else {
        $selected_datetime = date("Y-m-d H:i:s", strtotime("$date $time"));
        $now = new DateTime();
        $selected_dt_obj = new DateTime($selected_datetime);

        if ($selected_dt_obj < $now) {
            $error = "Selected date/time must be in the future.";
        } else {
            $booked_slots = getBookedSlots($conn, $date, $appointment_id);
            if (in_array($time, $booked_slots)) {
                $error = "Selected time slot is no longer available. Please choose another.";
            } else {
                $update_stmt = $conn->prepare("UPDATE appointments SET type = ?, appointment_date = ?, approved = NULL WHERE id = ? AND user_id = ?");
                $update_stmt->bind_param("ssii", $type, $selected_datetime, $appointment_id, $user['id']);
                if ($update_stmt->execute()) {
                    // Log history
                    $action_desc = "Updated appointment: type changed to '$type', date/time changed to '$selected_datetime'";
                    $log_stmt = $conn->prepare("INSERT INTO appointment_history (appointment_id, user_id, action) VALUES (?, ?, ?)");
                    $log_stmt->bind_param("iis", $appointment_id, $user['id'], $action_desc);
                    $log_stmt->execute();

                    $success = "Appointment updated successfully!";
                    $appointment['type'] = $type;
                    $appointment['appointment_date'] = $selected_datetime;
                } else {
                    $error = "Failed to update appointment. Please try again.";
                }
            }
        }
    }
}

$current_date = date("Y-m-d", strtotime($appointment['appointment_date']));
$current_time = date("H:i", strtotime($appointment['appointment_date']));
$booked_slots = getBookedSlots($conn, $current_date, $appointment_id);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <title>Edit Appointment</title>
    <style>
       body {
    background: url("../image/st therese.jpg") no-repeat center center fixed;
    background-size: cover;
    color: #DFD0B8;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    margin: 0; padding: 0;
}
        .container {
            max-width: 600px;
            margin: 40px auto;
            background: #393E46;
            padding: 30px 25px;
            border-radius: 8px;
            box-shadow: 0 0 15px rgba(0,0,0,0.3);
        }
        h2 {
            margin-top: 0;
            margin-bottom: 25px;
            color: #DFD0B8;
            text-align: center;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
        }
        input[type="date"],
        select {
            width: 100%;
            padding: 10px 12px;
            margin-bottom: 20px;
            border: none;
            border-radius: 6px;
            background-color: #222831;
            color: #DFD0B8;
            font-size: 15px;
        }
        select option {
            background-color: #222831;
            color: #DFD0B8;
        }
        button {
            background-color: #948979;
            color: #222831;
            border: none;
            padding: 12px 25px;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            width: 100%;
            transition: background-color 0.3s ease;
        }
        button:hover {
            background-color: #b4a393;
        }
        .message {
            text-align: center;
            margin-bottom: 20px;
            padding: 12px;
            border-radius: 6px;
        }
        .error {
            background-color: #EF476F;
            color: white;
        }
        .success {
            background-color: #06D6A0;
            color: #222831;
        }
        .back-link {
            margin-top: 15px;
            text-align: center;
        }
        .back-link a {
            color: #948979;
            text-decoration: none;
            font-weight: 600;
        }
        .back-link a:hover {
            text-decoration: underline;
        }
        .slots-list {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 25px;
        }
        .slot-option {
            flex: 1 1 30%;
        }
        .slot-option input[type="radio"] {
            display: none;
        }
        .slot-label {
            display: block;
            padding: 10px 15px;
            background-color: #222831;
            border-radius: 6px;
            text-align: center;
            font-weight: 600;
            cursor: pointer;
            color: #DFD0B8;
            user-select: none;
            transition: background-color 0.25s ease;
            border: 2px solid transparent;
        }
        .slot-option input[type="radio"]:checked + .slot-label {
            background-color: #06D6A0;
            color: #222831;
            border-color: #06D6A0;
        }
        .slot-label.disabled {
            background-color: #444c55;
            color: #7a7a7a;
            cursor: not-allowed;
            border-color: transparent;
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
    <h2>Edit Appointment</h2>

    <?php if ($error): ?>
        <div class="message error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="message success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <form method="POST" id="editForm" action="">
        <label for="type">Appointment Type</label>
        <select name="type" id="type" required>
            <?php
            $types = ['Wedding', 'Baptism', 'Blessing', 'Mass Intentions'];
            foreach ($types as $t):
                $selected = ($appointment['type'] === $t) ? 'selected' : '';
                echo "<option value=\"$t\" $selected>$t</option>";
            endforeach;
            ?>
        </select>

        <label for="date">Appointment Date</label>
        <input type="date" id="date" name="date" value="<?= htmlspecialchars($current_date) ?>" min="<?= date('Y-m-d') ?>" required />

        <label>Available Time Slots</label>
        <div class="slots-list" id="slotsList">
            <?php
            foreach ($available_slots as $slot):
                $disabled = in_array($slot, $booked_slots) ? 'disabled' : '';
                $checked = ($slot === $current_time) ? 'checked' : '';
                $labelClass = $disabled ? 'slot-label disabled' : 'slot-label';
            ?>
                <div class="slot-option">
                    <input type="radio" name="time" id="slot_<?= $slot ?>" value="<?= $slot ?>" <?= $disabled ? 'disabled' : '' ?> <?= $checked ?> />
                    <label class="<?= $labelClass ?>" for="slot_<?= $slot ?>"><?= date("g:i A", strtotime($slot)) ?></label>
                </div>
            <?php endforeach; ?>
        </div>

        <button type="submit">Update Appointment</button>
    </form>

    <div class="back-link">
        <a href="dashboard.php">← Back to Dashboard</a>
    </div>
</div>

<script>
    // When date changes, fetch available slots dynamically
    document.getElementById('date').addEventListener('change', function() {
        const date = this.value;
        if (!date) return;

        fetch('fetch_slots.php?date=' + encodeURIComponent(date) + '&exclude_id=<?= $appointment_id ?>')
            .then(response => response.json())
            .then(data => {
                const slotsList = document.getElementById('slotsList');
                slotsList.innerHTML = ''; // clear old slots

                const availableSlots = <?= json_encode($available_slots) ?>;
                const bookedSlots = data.bookedSlots || [];

                availableSlots.forEach(slot => {
                    const isBooked = bookedSlots.includes(slot);
                    const timeText = new Date('1970-01-01T' + slot + ':00').toLocaleTimeString([], {hour: '2-digit', minute:'2-digit', hour12:true});
                    const slotId = 'slot_' + slot;

                    const div = document.createElement('div');
                    div.className = 'slot-option';

                    const input = document.createElement('input');
                    input.type = 'radio';
                    input.name = 'time';
                    input.id = slotId;
                    input.value = slot;
                    if (isBooked) {
                        input.disabled = true;
                    }

                    const label = document.createElement('label');
                    label.htmlFor = slotId;
                    label.className = isBooked ? 'slot-label disabled' : 'slot-label';
                    label.textContent = timeText;

                    div.appendChild(input);
                    div.appendChild(label);

                    slotsList.appendChild(div);
                });
            })
            .catch(err => {
                console.error('Failed to fetch available slots:', err);
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
