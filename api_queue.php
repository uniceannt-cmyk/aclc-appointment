<?php
// api_queue.php - Real-Time Queuing State & Counter Control API

session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_REQUEST['action'] ?? 'status';
$office = strtolower(trim($_REQUEST['office'] ?? 'all'));

// Handle Queue Actions (Staff Only: Registrar)
if ($method === 'POST' && in_array($action, ['call', 'call_next', 'complete', 'skip', 'reset', 'pause', 'resume', 'transfer'], true)) {
    $user_role = $_SESSION['user_role'] ?? '';
    if ($user_role !== 'registrar') {
        echo json_encode(['success' => false, 'error' => 'Unauthorized: Staff role required']);
        exit();
    }

    $appointment_id = (int)($_POST['appointment_id'] ?? 0);
    $target_office = 'registrar';
    $counter = trim($_POST['counter'] ?? 'Registrar Window 1');

    if ($action === 'pause' || $action === 'resume') {
        $paused = $action === 'pause' ? 1 : 0;
        $stmt = mysqli_prepare($con, 'UPDATE queue_control SET is_paused = ? WHERE office = ?');
        mysqli_stmt_bind_param($stmt, 'is', $paused, $target_office);
        mysqli_stmt_execute($stmt);
        echo json_encode(['success' => true, 'paused' => (bool)$paused, 'message' => $paused ? 'Queue paused' : 'Queue resumed']);
        exit();
    }
    if ($action === 'transfer' && $appointment_id > 0) {
        $current_q = mysqli_prepare($con, "SELECT id FROM appointments WHERE id = ? AND office = 'registrar' AND queue_status = 'Serving' LIMIT 1");
        mysqli_stmt_bind_param($current_q, 'i', $appointment_id); mysqli_stmt_execute($current_q);
        if (!mysqli_fetch_assoc(mysqli_stmt_get_result($current_q))) { echo json_encode(['success' => false, 'error' => 'Select the currently serving appointment to transfer']); exit(); }
        $counter = substr($counter, 0, 100);
        $stmt = mysqli_prepare($con, "UPDATE queue_control SET counter_name = ? WHERE office = ?");
        mysqli_stmt_bind_param($stmt, 'ss', $counter, $target_office);
        mysqli_stmt_execute($stmt);
        echo json_encode(['success' => true, 'message' => 'Queue transferred to ' . $counter]);
        exit();
    }

    if ($action === 'call_next') {
        $paused_q = mysqli_query($con, "SELECT is_paused FROM queue_control WHERE office = 'registrar'");
        $paused_row = $paused_q ? mysqli_fetch_assoc($paused_q) : null;
        if (!empty($paused_row['is_paused'])) {
            echo json_encode(['success' => false, 'error' => 'Queue is paused']);
            exit();
        }
        $next_q = mysqli_query($con, "SELECT id FROM appointments WHERE office = 'registrar' AND status IN ('Pending','Approved') AND queue_status IN ('Waiting','') ORDER BY appointment_date, appointment_time, id LIMIT 1");
        $next = $next_q ? mysqli_fetch_assoc($next_q) : null;
        if (!$next) { echo json_encode(['success' => false, 'error' => 'No waiting appointments']); exit(); }
        $appointment_id = (int)$next['id'];
        $action = 'call';
    }

    if ($action === 'call' && $appointment_id > 0) {
        $pause_check = mysqli_query($con, "SELECT is_paused FROM queue_control WHERE office = 'registrar'");
        $pause_state = $pause_check ? mysqli_fetch_assoc($pause_check) : null;
        if (!empty($pause_state['is_paused'])) { echo json_encode(['success' => false, 'error' => 'Queue is paused']); exit(); }
        $target_check = mysqli_prepare($con, "SELECT id FROM appointments WHERE id = ? AND office = 'registrar' AND status IN ('Pending','Approved') AND queue_status <> 'Completed' LIMIT 1");
        mysqli_stmt_bind_param($target_check, 'i', $appointment_id); mysqli_stmt_execute($target_check);
        if (!mysqli_fetch_assoc(mysqli_stmt_get_result($target_check))) { echo json_encode(['success' => false, 'error' => 'Appointment is not eligible to be called']); exit(); }
        $counter = substr($counter !== '' ? $counter : 'Registrar Window 1', 0, 100);
        $counter_stmt = mysqli_prepare($con, 'UPDATE queue_control SET counter_name = ? WHERE office = ?');
        mysqli_stmt_bind_param($counter_stmt, 'ss', $counter, $target_office); mysqli_stmt_execute($counter_stmt);
        // First mark any currently serving appointment for this office as 'Completed' or 'Called'
        mysqli_query($con, "UPDATE appointments SET queue_status = 'Completed' WHERE office = '$target_office' AND queue_status = 'Serving'");

        // Now set the selected appointment to 'Serving' and record called_at
        $stmt = mysqli_prepare($con, "UPDATE appointments SET queue_status = 'Serving', called_at = NOW() WHERE id = ? AND office = 'registrar' AND status IN ('Pending','Approved')");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $appointment_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            echo json_encode(['success' => true, 'message' => 'Now serving queue number', 'id' => $appointment_id]);
            exit();
        }
    } elseif ($action === 'complete' && $appointment_id > 0) {
        mysqli_query($con, "UPDATE appointments SET queue_status = 'Completed' WHERE id = $appointment_id AND office = 'registrar' AND queue_status = 'Serving'");
        $changed = mysqli_affected_rows($con) > 0;
        echo json_encode(['success' => $changed, 'message' => $changed ? 'Queue completed' : 'Only the serving ticket can be completed']);
        exit();
    } elseif ($action === 'skip' && $appointment_id > 0) {
        mysqli_query($con, "UPDATE appointments SET queue_status = 'Skipped' WHERE id = $appointment_id AND office = 'registrar' AND queue_status IN ('Waiting','Serving','')");
        $changed = mysqli_affected_rows($con) > 0;
        echo json_encode(['success' => $changed, 'message' => $changed ? 'Queue skipped' : 'Ticket could not be skipped']);
        exit();
    }
}

// GET Query: Return Queuing Status for Public Screen or Dashboard
function get_office_queue_state($con, $office_code) {
    // 1. Currently Serving
    $serving_sql = "SELECT a.id, a.queue_number, a.service, a.called_at, u.first_name, u.last_name 
                    FROM appointments a 
                    LEFT JOIN users u ON a.student_id = u.student_id 
                    WHERE a.office = '$office_code'
                      AND a.queue_status = 'Serving'
                      AND DATE(a.called_at) = CURRENT_DATE()
                    ORDER BY a.called_at DESC LIMIT 1";
    $serving_res = mysqli_query($con, $serving_sql);
    $now_serving = null;
    if ($serving_res && $row = mysqli_fetch_assoc($serving_res)) {
        $now_serving = [
            'id' => $row['id'],
            'queue_number' => $row['queue_number'] ?: ('#' . $row['id']),
            'student_name' => trim($row['first_name'] . ' ' . $row['last_name']),
            'service' => $row['service'],
            'called_at' => $row['called_at']
        ];
    }

    // 2. Waiting List (Pending or Approved, queue_status = 'Waiting')
    $waiting_sql = "SELECT a.id, a.queue_number, a.service, a.appointment_time, u.first_name, u.last_name 
                    FROM appointments a 
                    LEFT JOIN users u ON a.student_id = u.student_id 
                    WHERE a.office = '$office_code' 
                      AND a.appointment_date = CURRENT_DATE()
                      AND (a.queue_status = 'Waiting' OR a.queue_status = '' OR a.queue_status IS NULL)
                      AND a.status != 'Rejected' AND a.status != 'Cancelled'
                    ORDER BY a.appointment_date ASC, a.appointment_time ASC LIMIT 6";
    $waiting_res = mysqli_query($con, $waiting_sql);
    $waiting_list = [];
    if ($waiting_res) {
        while ($w = mysqli_fetch_assoc($waiting_res)) {
            $waiting_list[] = [
                'id' => $w['id'],
                'queue_number' => $w['queue_number'] ?: ('#' . $w['id']),
                'student_name' => trim($w['first_name'] . ' ' . $w['last_name']),
                'service' => $w['service'],
                'time' => date('h:i A', strtotime($w['appointment_time']))
            ];
        }
    }

    // 3. Total Waiting
    $total_sql = "SELECT COUNT(*) as total FROM appointments 
                  WHERE office = '$office_code' 
                    AND appointment_date = CURRENT_DATE()
                    AND (queue_status = 'Waiting' OR queue_status = '' OR queue_status IS NULL)
                    AND status != 'Rejected' AND status != 'Cancelled'";
    $total_res = mysqli_query($con, $total_sql);
    $total_waiting = 0;
    if ($total_res && $t = mysqli_fetch_assoc($total_res)) {
        $total_waiting = (int)$t['total'];
    }

    $control_q = mysqli_query($con, "SELECT is_paused, counter_name FROM queue_control WHERE office = '" . mysqli_real_escape_string($con, $office_code) . "' LIMIT 1");
    $control = $control_q ? mysqli_fetch_assoc($control_q) : null;
    return [
        'office' => $office_code,
        'is_paused' => (bool)($control['is_paused'] ?? false),
        'counter_name' => $control['counter_name'] ?? 'Registrar Window 1',
        'now_serving' => $now_serving,
        'waiting_list' => $waiting_list,
        'total_waiting' => $total_waiting
    ];
}

$response = [
    'success' => true,
    'timestamp' => date('Y-m-d H:i:s'),
    'registrar' => get_office_queue_state($con, 'registrar')
];

echo json_encode($response);
?>
