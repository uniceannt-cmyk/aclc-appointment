<?php
// api_slots.php - Real-Time Available Appointment Slots & Calendar Query API
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

require_once __DIR__ . "/db.php";

function api_slots_error($message) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $message]);
    exit();
}

$office = strtolower(trim($_GET['office'] ?? 'registrar'));
if ($office !== 'registrar') {
    $office = 'registrar';
}

$action = trim($_GET['action'] ?? 'get_slots');
$date = trim($_GET['date'] ?? date('Y-m-d'));

$day_capacity = null;
$day_limit_stmt = mysqli_prepare($con, 'SELECT max_capacity FROM appointment_day_limits WHERE office = ? AND slot_date = ?');
if (!$day_limit_stmt) {
    error_log('api_slots.php: failed to prepare daily capacity query: ' . mysqli_error($con));
    api_slots_error('Unable to load slot availability.');
}
mysqli_stmt_bind_param($day_limit_stmt, 'ss', $office, $date);
if (!mysqli_stmt_execute($day_limit_stmt)) {
    error_log('api_slots.php: failed to load daily capacity: ' . mysqli_stmt_error($day_limit_stmt));
    mysqli_stmt_close($day_limit_stmt);
    api_slots_error('Unable to load slot availability.');
}
$day_limit_row = mysqli_fetch_assoc(mysqli_stmt_get_result($day_limit_stmt));
if ($day_limit_row) $day_capacity = (int)$day_limit_row['max_capacity'];
mysqli_stmt_close($day_limit_stmt);

$day_booked_stmt = mysqli_prepare($con, "SELECT COUNT(*) AS booked FROM appointments WHERE office = ? AND appointment_date = ? AND status IN ('Pending','Approved')");
if (!$day_booked_stmt) {
    error_log('api_slots.php: failed to prepare daily booking count: ' . mysqli_error($con));
    api_slots_error('Unable to load slot availability.');
}
mysqli_stmt_bind_param($day_booked_stmt, 'ss', $office, $date);
if (!mysqli_stmt_execute($day_booked_stmt)) {
    error_log('api_slots.php: failed to load daily booking count: ' . mysqli_stmt_error($day_booked_stmt));
    mysqli_stmt_close($day_booked_stmt);
    api_slots_error('Unable to load slot availability.');
}
$day_booked = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($day_booked_stmt))['booked'] ?? 0);
mysqli_stmt_close($day_booked_stmt);
$day_remaining = $day_capacity === null ? null : max(0, $day_capacity - $day_booked);

// Action: Fetch all upcoming dates that have active published slots
if ($action === 'calendar_month') {
    $month = trim($_GET['month'] ?? date('Y-m'));
    if (!preg_match('/^\d{4}-\d{2}$/', $month) || !checkdate((int)substr($month, 5, 2), 1, (int)substr($month, 0, 4))) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid month.']);
        exit();
    }
    $month_start = $month . '-01';
    $month_end = date('Y-m-t', strtotime($month_start));
    $calendar_stmt = mysqli_prepare($con, "SELECT s.slot_date, COUNT(s.id) AS total_slots, SUM(s.status = 'open') AS open_slots, COALESCE(MAX(dl.max_capacity), 0) AS day_capacity, (SELECT COUNT(*) FROM appointments a WHERE a.office = s.office AND a.appointment_date = s.slot_date AND a.status IN ('Pending','Approved')) AS booked FROM appointment_slots s LEFT JOIN appointment_day_limits dl ON dl.office = s.office AND dl.slot_date = s.slot_date WHERE s.office = ? AND s.slot_date BETWEEN ? AND ? AND s.start_time >= '08:00:00' AND s.end_time <= '16:00:00' AND NOT (s.start_time < '13:00:00' AND s.end_time > '11:45:00') GROUP BY s.office, s.slot_date ORDER BY s.slot_date");
    $days = [];
    if ($calendar_stmt) {
        mysqli_stmt_bind_param($calendar_stmt, 'sss', $office, $month_start, $month_end);
        mysqli_stmt_execute($calendar_stmt);
        $calendar_result = mysqli_stmt_get_result($calendar_stmt);
        while ($row = mysqli_fetch_assoc($calendar_result)) {
            $days[$row['slot_date']] = ['total_slots' => (int)$row['total_slots'], 'open_slots' => (int)$row['open_slots'], 'day_capacity' => (int)$row['day_capacity'], 'booked' => (int)$row['booked']];
        }
        mysqli_stmt_close($calendar_stmt);
    }
    echo json_encode(['success' => true, 'month' => $month, 'days' => $days]);
    exit();
}

if ($action === 'available_dates') {
    $today = date('Y-m-d');
    $stmt = mysqli_prepare($con, "
        SELECT DISTINCT s.slot_date, COUNT(s.id) as slot_count
        FROM appointment_slots s
        WHERE s.office = ? 
          AND s.slot_date >= ?
          AND s.status != 'closed'
          AND s.start_time >= '08:00:00'
          AND s.end_time <= '16:00:00'
          AND NOT (s.start_time < '13:00:00' AND s.end_time > '11:45:00')
        GROUP BY s.slot_date
        ORDER BY s.slot_date ASC
    ");
    $dates = [];
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "ss", $office, $today);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($res)) {
            $dates[] = [
                'date' => $row['slot_date'],
                'slot_count' => (int)$row['slot_count'],
                'formatted' => date('l, M j, Y', strtotime($row['slot_date']))
            ];
        }
        mysqli_stmt_close($stmt);
    }
    echo json_encode(['success' => true, 'office' => $office, 'dates' => $dates]);
    exit();
}

// Action: Fetch slots for a specific date
$stmt = mysqli_prepare($con, "
    SELECT 
        s.id,
        s.office,
        s.slot_date,
        s.start_time,
        s.end_time,
        s.max_capacity,
        s.status,
        (SELECT COUNT(*) FROM appointments a 
         WHERE (a.slot_id = s.id OR (a.office = s.office AND a.appointment_date = s.slot_date AND a.appointment_time = s.start_time))
           AND a.status IN ('Pending', 'Approved')) as booked_count
    FROM appointment_slots s
    WHERE s.office = ? AND s.slot_date = ?
      AND s.start_time >= '08:00:00'
      AND s.end_time <= '16:00:00'
      AND NOT (s.start_time < '13:00:00' AND s.end_time > '11:45:00')
    ORDER BY s.start_time ASC
");

$slots = [];
if (!$stmt) {
    error_log('api_slots.php: failed to prepare slot query: ' . mysqli_error($con));
    api_slots_error('Unable to load slot availability.');
}
mysqli_stmt_bind_param($stmt, "ss", $office, $date);
if (!mysqli_stmt_execute($stmt)) {
    error_log('api_slots.php: failed to load slots: ' . mysqli_stmt_error($stmt));
    mysqli_stmt_close($stmt);
    api_slots_error('Unable to load slot availability.');
}
$result = mysqli_stmt_get_result($stmt);

while ($row = mysqli_fetch_assoc($result)) {
        $max_cap = (int)$row['max_capacity'];
        $booked = (int)$row['booked_count'];
        $remaining = max(0, $max_cap - $booked);
        $is_open = ($row['status'] === 'open' && $remaining > 0 && ($day_remaining === null || $day_remaining > 0));

        // Format start & end times nicely (e.g. 02:00 PM - 02:30 PM)
        $start_formatted = date('h:i A', strtotime($row['start_time']));
        $end_formatted = date('h:i A', strtotime($row['end_time']));

        $slots[] = [
            'id' => (int)$row['id'],
            'start_time' => substr($row['start_time'], 0, 5),
            'end_time' => substr($row['end_time'], 0, 5),
            'start_formatted' => $start_formatted,
            'end_formatted' => $end_formatted,
            'label' => $start_formatted . ' - ' . $end_formatted,
            'time_only_label' => $start_formatted,
            'max_capacity' => $max_cap,
            'booked_count' => $booked,
            'seats_remaining' => $remaining,
            'status' => $row['status'],
            'is_available' => $is_open
        ];
}
mysqli_stmt_close($stmt);

echo json_encode([
    'success' => true,
    'office' => $office,
    'date' => $date,
    'total_slots' => count($slots),
    'day_capacity' => $day_capacity,
    'day_booked_count' => $day_booked,
    'day_seats_remaining' => $day_remaining,
    'slots' => $slots
]);
exit();
