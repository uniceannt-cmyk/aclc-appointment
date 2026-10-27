<?php
/** Store a durable event so connected clients can receive it via SSE. */
function publish_realtime_event($con, $event_type, $appointment_id, $student_id, array $payload = []) {
    $payload_json = json_encode($payload, JSON_UNESCAPED_UNICODE);
    $stmt = mysqli_prepare($con, 'INSERT INTO realtime_events (event_type, appointment_id, student_id, payload) VALUES (?, ?, ?, ?)');
    if (!$stmt) return false;
    mysqli_stmt_bind_param($stmt, 'siss', $event_type, $appointment_id, $student_id, $payload_json);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $ok;
}

function publish_slot_availability_event($con, $date) {
    if (!publish_realtime_event($con, 'slots_changed', 0, '', ['date' => $date])) {
        error_log('Unable to publish slot availability event for ' . $date . ': ' . mysqli_error($con));
    }
}
?>
