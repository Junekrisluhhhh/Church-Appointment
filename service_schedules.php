<?php
/**
 * Core Scheduling Logic
 * Handles time normalization and slot availability
 */
date_default_timezone_set('Asia/Manila');

$FIXED_SCHEDULES = [
    'Wedding' => [
        'mon-sat' => ['09:00','10:30','13:00','14:30','16:00']
    ],
    'Funeral' => [
        'mon-sat' => ['09:00','10:00','11:00','13:00','14:00','15:00'],
        'sun'     => ['12:00','12:45','13:30']
    ],
    'Blessing' => [
        'mon-sun' => ['09:00','10:00','11:00','13:00','14:00','15:00','16:00']
    ],
    'Pre-Cana Seminar' => [
        'sat' => ['07:00','08:00','09:00','10:00','11:00','13:00','14:00','15:00','16:00','17:00']
    ],
    'Baptism' => [
    'Regular' => [
        'sun' => ['08:30','09:30','10:30','11:30']
    ],
    'Special' => [
        'mon-sun' => ['09:00','10:00','11:00','13:00','14:00','15:00']
    ]
]
];

/**
 * Normalizes time strings to HH:mm format (e.g., "9:00" -> "09:00")
 */
function normalizeTime($timeStr) {
    return date("H:i", strtotime($timeStr));
}

function getTimeSlots($serviceType, $baptismType = null, $dateStr = null) {
    global $FIXED_SCHEDULES;
    $dayOfWeek = ($dateStr) ? (int)(new DateTime($dateStr))->format('w') : null;

    if ($serviceType === 'Baptism') {
        $type = $baptismType ?: 'Regular';
        $typeData = $FIXED_SCHEDULES['Baptism'][$type] ?? [];
        if ($type === 'Regular') {
            return ($dayOfWeek === 0) ? ($typeData['sun'] ?? []) : [];
        }
        return $typeData['mon-sun'] ?? [];
    }
    // Ensure there is NO closing brace here.
    
    $schedule = $FIXED_SCHEDULES[$serviceType] ?? [];
    // ... rest of the logic ...
 // This is the only brace that should close the function.
    if ($dayOfWeek !== null) {
        if ($dayOfWeek === 0 && isset($schedule['sun'])) return $schedule['sun'];
        if ($dayOfWeek === 6 && isset($schedule['sat'])) return $schedule['sat'];
        if ($dayOfWeek >= 1 && $dayOfWeek <= 5 && isset($schedule['mon-sat'])) return $schedule['mon-sat'];
        if (isset($schedule['mon-sun'])) return $schedule['mon-sun'];
    }
    return [];
}

function isValidDateForService($dateStr, $serviceType, $baptismType = null) {
    $date = new DateTime($dateStr);
    $dayOfWeek = (int)$date->format('w');
    if ($date < (new DateTime())->setTime(0,0,0)) return false;

    if ($serviceType === 'Baptism') {
        // Special Baptism is Mon-Sun, Regular is only Sunday (0)
        return ($baptismType === 'Special') ? true : ($dayOfWeek === 0);
    }
    if ($serviceType === 'Funeral') return true;
    if ($serviceType === 'Pre-Cana Seminar') {
        $weekOfMonth = ceil((int)$date->format('j') / 7);
        return ($dayOfWeek === 6 && ($weekOfMonth == 2 || $weekOfMonth == 4));
    }
    return isset($GLOBALS['FIXED_SCHEDULES'][$serviceType]);
}

function getSlotStatus($conn, $serviceType, $dateStr, $baptismType = null) {
    $slots = [];
    $times = getTimeSlots($serviceType, $baptismType, $dateStr);
    $dbSearchType = ($serviceType === 'Baptism') ? 'Baptism' : $serviceType;
    
    $stmt = $conn->prepare("SELECT appointment_date FROM appointments WHERE type = ? AND appointment_date LIKE ? AND is_deleted = 0");
    $likeDate = $dateStr . '%';
    $stmt->bind_param("ss", $dbSearchType, $likeDate);
    $stmt->execute();
    $res = $stmt->get_result();
    
    $bookedTimes = [];
    while($row = $res->fetch_assoc()) {
        $bookedTimes[] = normalizeTime(substr($row['appointment_date'], 11, 5));
    }
    $stmt->close();

    foreach ($times as $t) {
        $normalizedT = normalizeTime($t);
        $isBooked = in_array($normalizedT, $bookedTimes);
        
        $slots[] = [
            'time' => $t,
            'dateTime' => $dateStr . ' ' . $normalizedT . ':00',
            'isBooked' => $isBooked,
            'isAvailable' => !$isBooked,
            'color' => $isBooked ? '#CCCCCC' : '#4CAF50'
        ];
    }
    return $slots;
}

function hasWeddingOnDate($conn, $dateStr) {
    $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM appointments WHERE type = 'Wedding' AND appointment_date LIKE ? AND is_deleted = 0");
    $likeDate = $dateStr . '%';
    $stmt->bind_param("s", $likeDate);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return intval($result['cnt']) > 0;
}

function hasFuneralOnDate($conn, $dateStr) {
    $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM appointments WHERE type = 'Funeral' AND appointment_date LIKE ? AND is_deleted = 0");
    $likeDate = $dateStr . '%';
    $stmt->bind_param("s", $likeDate);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return intval($result['cnt']) > 0;
}
function isSlotBooked($conn, $serviceType, $appointmentDateTime) {
    // Extract date and time from the datetime string (format: YYYY-MM-DD HH:MM:SS)
    $date = substr($appointmentDateTime, 0, 10); // YYYY-MM-DD
    $time = substr($appointmentDateTime, 11, 5); // HH:MM

    $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM appointments WHERE type = ? AND appointment_date LIKE ? AND is_deleted = 0");
    $likeDate = $date . '%';
    $stmt->bind_param("ss", $serviceType, $likeDate);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // If there are appointments on this date, check if the specific time is booked
    if (intval($result['cnt']) > 0) {
        $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM appointments WHERE type = ? AND appointment_date = ? AND is_deleted = 0");
        $fullDateTime = $appointmentDateTime;
        $stmt->bind_param("ss", $serviceType, $fullDateTime);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return intval($result['cnt']) > 0;
    }

    return false;
}

// SERVICE_SCHEDULES alias for backward compatibility
$SERVICE_SCHEDULES = $FIXED_SCHEDULES;
?>
