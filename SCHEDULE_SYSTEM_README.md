# Fixed Schedule System for Church Appointments

## Overview
This document outlines the new fixed scheduling system for Wedding and Funeral services implemented in the church appointment system.

## Files Created/Modified

### New Files
1. **service_schedules.php** - Configuration and functions for service schedules
2. **calendar_api.php** - API endpoint for real-time calendar availability
3. **calendar_widget.html** - Interactive calendar UI component

### Modified Files
1. **add_appointment.php** - Updated appointment form with integrated calendar

## Service Schedule Configuration

### Wedding
- **Available Days:** Monday to Saturday
- **Fixed Time Slots:** 
  - 9:00 AM
  - 10:30 AM
  - 1:00 PM (13:00)
  - 2:30 PM (14:30)
  - 4:00 PM (16:00)
- **Duration:** 2 hours
- **Color:** Green (#4CAF50)
- **Minimum Advance Booking:** 3 weeks
- **Conflict:** Cannot be booked if funeral exists on same date

### Funeral
- **Available Days:** Monday to Saturday
- **Fixed Time Slots:**
  - 9:00 AM
  - 10:00 AM
  - 11:00 AM
  - 1:00 PM (13:00)
  - 2:00 PM (14:00)
  - 3:00 PM (15:00)
- **Duration:** 1.5 hours
- **Color:** Blue (#2196F3)
- **Conflict:** Cannot be booked if wedding exists on same date

### Other Services
- **Regular Baptism:** Sundays, Times: 8:30 AM, 9:30 AM, 10:30 AM, 11:30 AM
- **Special Baptism:** Monday-Saturday, Times: 9:00 AM, 10:00 AM, 11:00 AM, 1:00 PM, 2:00 PM, 3:00 PM
- **Blessing:** Monday-Saturday, Times: 9:00 AM, 10:00 AM, 11:00 AM, 1:00 PM, 2:00 PM, 3:00 PM, 4:00 PM

## Key Features

### 1. Real-Time Calendar Display
- Interactive calendar showing availability for each day
- Color-coded slots:
  - **Green:** Available slots
  - **Gray:** Booked or unavailable slots
  - **Light Gray:** Not available for this service (wrong day)

### 2. Conflict Detection
- **Wedding ↔ Funeral:** If a wedding is booked on a date, no funeral slots will be available, and vice versa
- System checks for existing bookings before allowing new appointments
- Automatic validation on form submission

### 3. Real-Time Availability
- Calendar API (`calendar_api.php`) provides:
  - `get_month_availability` - Full month calendar with status
  - `get_day_slots` - Specific day's available time slots
  - `get_slot_info` - Detailed slot information

### 4. Form Validation
Server-side validation in `add_appointment.php` ensures:
- Date is appropriate for service type
- Time is from fixed schedule
- No conflicts with other bookings
- Minimum advance booking requirements are met
- Wedding: Minimum 3 weeks advance

## Database Schema Changes

No database schema changes required. The system uses:
- `appointments` table (existing)
  - `type` field for service type
  - `appointment_date` field for date/time
  - `status` field to filter cancelled/denied appointments

## API Endpoints

### calendar_api.php

#### Get Month Availability
```
GET /calendar_api.php?action=get_month_availability&service=Wedding&month=2026-01
```

Response:
```json
{
  "success": true,
  "month": "2026-01",
  "service": "Wedding",
  "calendar": {
    "2026-01-05": {
      "date": "2026-01-05",
      "dayOfWeek": "Monday",
      "day": 5,
      "available": true,
      "availableCount": 5,
      "totalSlots": 5,
      "color": "#4CAF50",
      "slots": [...]
    }
  }
}
```

#### Get Day Slots
```
GET /calendar_api.php?action=get_day_slots&service=Wedding&date=2026-01-05
```

Response:
```json
{
  "success": true,
  "date": "2026-01-05",
  "dayOfWeek": "Monday",
  "service": "Wedding",
  "slots": [
    {
      "time": "09:00",
      "dateTime": "2026-01-05 09:00:00",
      "isBooked": false,
      "isAvailable": true,
      "color": "#4CAF50"
    }
  ]
}
```

## JavaScript Functions

### Wedding Calendar
- `initWeddingCalendar(minDate)` - Initialize wedding calendar
- `updateWeddingCalendar()` - Fetch and render month
- `selectWeddingDate(dateStr, slots)` - Handle date selection
- `selectWeddingTime(time)` - Handle time selection

### Funeral Calendar
- `initFuneralCalendar()` - Initialize funeral calendar
- `updateFuneralCalendar()` - Fetch and render month
- `selectFuneralDate(dateStr, slots)` - Handle date selection
- `selectFuneralTime(time)` - Handle time selection

## Usage Example

### For Users
1. Navigate to "Add Appointment"
2. Select "Wedding" or "Funeral"
3. Enter required information (names, contact, etc.)
4. Interactive calendar displays available dates in green
5. Click on a date to see available time slots
6. Click on a time slot to select it
7. Selected date/time automatically fill hidden form fields
8. Submit the form

### For Admin/Staff
The system automatically manages:
- Slot availability
- Conflict detection
- Time slot validation
- Real-time calendar updates

## Configuration

To modify schedules, edit `service_schedules.php`:

```php
$SERVICE_SCHEDULES = [
    'Wedding' => [
        'days' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
        'times' => ['09:00', '10:30', '13:00', '14:30', '16:00'],
        'duration_hours' => 2,
        'color' => '#4CAF50' // Green
    ],
    // ... other services
];
```

## Styling

Calendar styling is included in `add_appointment.php` (inline CSS):
- `.calendar-container` - Main calendar wrapper
- `.calendar-day` - Individual day cell
- `.time-slot` - Time slot button
- `.calendar-day.available` - Available day styling
- `.calendar-day.unavailable` - Unavailable day styling
- `.time-slot.available` - Available time slot
- `.time-slot.booked` - Booked time slot
- `.time-slot.selected` - Selected time slot

## Troubleshooting

### Calendar Not Loading
- Check browser console for errors
- Verify `calendar_api.php` is accessible
- Check database connection in `db.php`

### Slots Not Showing
- Verify service type is correct
- Check `SERVICE_SCHEDULES` configuration
- Ensure database has appointments table with correct structure

### Conflict Detection Not Working
- Check that `hasWeddingOnDate()` and `hasFuneralOnDate()` functions are properly included
- Verify status field in appointments table (non-cancelled appointments are counted)

## Future Enhancements

Possible improvements:
1. Admin dashboard for schedule configuration
2. Custom time slots per location
3. Multiple weddings/funerals per day with booking limits
4. Email notifications when slots become available
5. iCal export for calendar integration
6. Time zone support for international bookings

