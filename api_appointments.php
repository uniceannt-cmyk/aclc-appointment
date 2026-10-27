<?php
// Database-backed rehydration endpoint. This is the source of truth after a refresh.
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required.']);
    exit();
}

require_once __DIR__ . '/db.php';
$role = $_SESSION['user_role'] ?? 'student';
$fields = 'id, student_id, office, service, appointment_date, appointment_time, status, rejection_reason, registrar_notes, queue_number, queue_status, created_at, updated_at, processed_at';

function appointment_rows($result) {
    $rows = [];
    while ($result && ($row = mysqli_fetch_assoc($result))) $rows[] = $row;
    return $rows;
}

if ($role === 'registrar') {
    $pending = mysqli_query($con, "SELECT $fields FROM appointments WHERE office = 'registrar' AND LOWER(status) = 'pending' ORDER BY created_at ASC");
    $history = mysqli_query($con, "SELECT $fields FROM appointments WHERE office = 'registrar' AND LOWER(status) IN ('approved', 'rejected', 'cancelled') ORDER BY updated_at DESC, id DESC");
    echo json_encode(['success' => true, 'pending' => appointment_rows($pending), 'history' => appointment_rows($history)]);
    exit();
}

$student_id = $_SESSION['user_id'];
$stmt = mysqli_prepare($con, "SELECT $fields FROM appointments WHERE student_id = ? ORDER BY created_at DESC, id DESC");
mysqli_stmt_bind_param($stmt, 's', $student_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
echo json_encode(['success' => true, 'appointments' => appointment_rows($result)]);
mysqli_stmt_close($stmt);
?>
