<?php
// Authenticated Server-Sent Events stream. POST requests remain the client-to-server channel.
session_start();
if (!isset($_SESSION['user_id'])) { http_response_code(401); exit(); }
$user_id = $_SESSION['user_id'];
$role = $_SESSION['user_role'] ?? '';
session_write_close();

require_once __DIR__ . '/db.php';
header('Content-Type: text/event-stream; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('X-Accel-Buffering: no');
ignore_user_abort(true);

$last_event_header = $_SERVER['HTTP_LAST_EVENT_ID'] ?? $_GET['last_event_id'] ?? null;
if ($last_event_header === null) {
    // A fresh page obtains current state through its normal API call; do not replay old alerts.
    $latest = mysqli_query($con, 'SELECT COALESCE(MAX(id), 0) AS id FROM realtime_events');
    $last_id = $latest ? (int)(mysqli_fetch_assoc($latest)['id'] ?? 0) : 0;
} else {
    $last_id = max(0, (int)$last_event_header);
}
$deadline = time() + 25;
while (time() < $deadline && !connection_aborted()) {
    if ($role === 'registrar') {
        $sql = "SELECT id, event_type, appointment_id, student_id, payload FROM realtime_events WHERE id > $last_id ORDER BY id ASC LIMIT 50";
    } else {
        $safe_user = mysqli_real_escape_string($con, $user_id);
        $sql = "SELECT id, event_type, appointment_id, student_id, payload FROM realtime_events WHERE id > $last_id AND (student_id = '$safe_user' OR event_type = 'slots_changed') ORDER BY id ASC LIMIT 50";
    }
    $result = mysqli_query($con, $sql);
    $sent = false;
    while ($result && ($event = mysqli_fetch_assoc($result))) {
        $last_id = (int)$event['id'];
        $data = json_encode([
            'appointment_id' => (int)$event['appointment_id'],
            'student_id' => $event['student_id'],
            'payload' => json_decode($event['payload'], true) ?: []
        ]);
        echo "id: {$last_id}\n";
        echo "event: {$event['event_type']}\n";
        echo "data: {$data}\n\n";
        $sent = true;
    }
    if (!$sent) echo ": keepalive\n\n";
    @ob_flush(); flush();
    sleep(1);
}
?>
