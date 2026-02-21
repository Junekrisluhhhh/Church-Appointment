<?php
date_default_timezone_set('Asia/Manila');

session_start();
include "db.php";
include "service_schedules.php";

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? '';
$serviceType = $_GET['service'] ?? '';
$baptismType = $_GET['baptism_type'] ?? null;
$date = $_GET['date'] ?? '';
$month = $_GET['month'] ?? date('Y-m');

header('Content-Type: application/json');

switch ($action) {
    case 'get_month_availability':
        getMonthAvailability($conn, $serviceType, $month, $baptismType);
        break;
        
    case 'get_day_slots':
        getDaySlots($conn, $serviceType, $date, $baptismType);
        break;
        
    case 'get_slot_info':
        getSlotInfo($conn, $serviceType, $date, $baptismType);
        break;
        
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
}

/**
 * Get month calendar with availability
 */
function getMonthAvailability($conn, $serviceType, $month, $baptismType = null) {
    global $FIXED_SCHEDULES;
    
    // FIX: Default Baptism Type
    if ($serviceType === 'Baptism' && empty($baptismType)) {
        $baptismType = 'Regular';
    }
    
    try {
        if (!isset($FIXED_SCHEDULES[$serviceType])) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid service type']);
            return;
        }
        
        list($year, $monthNum) = explode('-', $month);
        $year = intval($year);
        $monthNum = intval($monthNum);
        
        if ($monthNum < 1 || $monthNum > 12) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid month']);
            return;
        }
        
        $calendar = [];
        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $monthNum, $year);
        
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $dateStr = sprintf('%04d-%02d-%02d', $year, $monthNum, $day);
            $dayOfWeekName = date('l', strtotime($dateStr));
            
            $isValidDay = isValidDateForService($dateStr, $serviceType, $baptismType);
            
            // Conflicts
            $hasWedding = hasWeddingOnDate($conn, $dateStr);
            $hasFuneral = hasFuneralOnDate($conn, $dateStr);
            
            if ($serviceType === 'Wedding' && $hasFuneral) $isValidDay = false;
            if ($serviceType === 'Funeral' && $hasWedding) $isValidDay = false;
            
            if ($isValidDay) {
                $slots = getSlotStatus($conn, $serviceType, $dateStr, $baptismType);
                $availableCount = count(array_filter($slots, fn($s) => !$s['isBooked']));
                
                $calendar[$dateStr] = [
                    'date' => $dateStr,
                    'dayOfWeek' => $dayOfWeekName,
                    'day' => $day,
                    'available' => $availableCount > 0,
                    'availableCount' => $availableCount,
                    'totalSlots' => count($slots),
                    'color' => $availableCount > 0 ? '#4CAF50' : '#CCCCCC',
                    'slots' => $slots
                ];
            } else {
                $calendar[$dateStr] = [
                    'date' => $dateStr,
                    'dayOfWeek' => $dayOfWeekName,
                    'day' => $day,
                    'available' => false,
                    'availableCount' => 0,
                    'totalSlots' => 0,
                    'color' => '#EEEEEE',
                    'reason' => ($serviceType === 'Pre-Cana Seminar') 
                                ? 'Only 2nd & 4th Saturdays' 
                                : 'Service not available',
                    'slots' => []
                ];
            }
        }
        
        echo json_encode([
            'success' => true,
            'month' => $month,
            'service' => $serviceType,
            'baptism_type' => $baptismType,
            'calendar' => $calendar
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
    }
}

/**
 * Get all slots for a specific day
 */
function getDaySlots($conn, $serviceType, $date, $baptismType = null) {
    global $FIXED_SCHEDULES;
    
    if (!isset($FIXED_SCHEDULES[$serviceType])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid service type']);
        return;
    }

    $isValid = isValidDateForService($date, $serviceType, $baptismType);

    if (!$isValid) {
        echo json_encode([
            'success' => true,
            'date' => $date,
            'service' => $serviceType,
            'slots' => [],
            'reason' => 'Service not available on this date'
        ]);
        return;
    }
    
    // Conflict checks
    if ($serviceType === 'Funeral' && hasWeddingOnDate($conn, $date)) {
        echo json_encode(['success' => true, 'slots' => [], 'reason' => 'Wedding scheduled']);
        return;
    }
    
    if ($serviceType === 'Wedding' && hasFuneralOnDate($conn, $date)) {
        echo json_encode(['success' => true, 'slots' => [], 'reason' => 'Funeral scheduled']);
        return;
    }
    
    $slots = getSlotStatus($conn, $serviceType, $date, $baptismType);
    
    echo json_encode([
        'success' => true,
        'date' => $date,
        'dayOfWeek' => date('l', strtotime($date)),
        'service' => $serviceType,
        'baptism_type' => $baptismType,
        'slots' => $slots
    ]);
}

/**
 * Get info about a specific slot
 */
function getSlotInfo($conn, $serviceType, $date, $baptismType = null) {
    global $FIXED_SCHEDULES;
    
    if (!isset($FIXED_SCHEDULES[$serviceType])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid service type']);
        return;
    }
    
    $slots = getSlotStatus($conn, $serviceType, $date, $baptismType);
    
    $slotCount = count($slots);
    $availableCount = count(array_filter($slots, fn($s) => !$s['isBooked']));
    
    echo json_encode([
        'success' => true,
        'date' => $date,
        'service' => $serviceType,
        'totalSlots' => $slotCount,
        'availableSlots' => $availableCount,
        'bookedSlots' => $slotCount - $availableCount,
        'color' => $availableCount > 0 ? '#4CAF50' : '#CCCCCC'
    ]);
}
?>