<?php
// api_student_status.php — Student-facing real-time appointment status polling endpoint

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

// Only students can poll this endpoint
$current_role = $_SESSION['user_role'] ?? '';
if ($current_role !== 'student') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Students only']);
    exit();
}

require_once __DIR__ . '/db.php';

$stmt = mysqli_prepare($con, "SELECT id, service, appointment_date, appointment_time, status, queue_number, queue_status, rejection_reason, registrar_notes, processed_at, updated_at FROM appointments WHERE student_id = ? ORDER BY id DESC");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Unable to load appointment statuses.']);
    exit();
}
mysqli_stmt_bind_param($stmt, 's', $_SESSION['user_id']);
if (!mysqli_stmt_execute($stmt)) {
    error_log('Unable to load student appointment statuses: ' . mysqli_stmt_error($stmt));
    mysqli_stmt_close($stmt);
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Unable to load appointment statuses.']);
    exit();
}
$result = mysqli_stmt_get_result($stmt);

$appointments = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $appointments[] = [
            'id'           => (int)$row['id'],
            'service'      => $row['service'],
            'appointment_date' => $row['appointment_date'],
            'appointment_time' => $row['appointment_time'],
            'status'       => $row['status'],
            'queue_number' => $row['queue_number'],
            'queue_status' => $row['queue_status'],
            'rejection_reason' => $row['rejection_reason'],
            'registrar_notes' => $row['registrar_notes'],
            'processed_at' => $row['processed_at'],
            'updated_at' => $row['updated_at'],
        ];
    }
    mysqli_stmt_close($stmt);
}

echo json_encode([
    'success'      => true,
    'appointments' => $appointments,
    'timestamp'    => time(),
]);
