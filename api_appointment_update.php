<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
if (($_SESSION['user_role'] ?? '') !== 'registrar') { http_response_code(403); echo json_encode(['success' => false, 'message' => 'Registrar access required.']); exit(); }
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/realtime_helper.php';

$appointment_id = (int)($_POST['appointment_id'] ?? 0);
$status = trim($_POST['status'] ?? 'Pending');
$reason = trim($_POST['reason'] ?? '');
$notes = trim($_POST['registrar_notes'] ?? '');
$allowed = ['Pending', 'Approved', 'Rejected', 'Cancelled'];
if ($appointment_id < 1 || !in_array($status, $allowed, true)) { http_response_code(422); echo json_encode(['success' => false, 'message' => 'Invalid appointment update.']); exit(); }
if ($status === 'Rejected' && $reason === '') { http_response_code(422); echo json_encode(['success' => false, 'message' => 'A rejection reason is required.']); exit(); }
if (strlen($reason) > 1000) { http_response_code(422); echo json_encode(['success' => false, 'message' => 'Reason must be 1,000 characters or fewer.']); exit(); }
if (strlen($notes) > 2000) { http_response_code(422); echo json_encode(['success' => false, 'message' => 'Registrar notes must be 2,000 characters or fewer.']); exit(); }
if ($status !== 'Rejected') $reason = '';

$q = mysqli_query($con, "SELECT * FROM appointments WHERE id = $appointment_id AND office = 'registrar' LIMIT 1");
$appointment = $q ? mysqli_fetch_assoc($q) : null;
if (!$appointment) { http_response_code(404); echo json_encode(['success' => false, 'message' => 'Appointment not found.']); exit(); }

$date = trim($_POST['appointment_date'] ?? '') ?: $appointment['appointment_date'];
$time = trim($_POST['appointment_time'] ?? '') ?: $appointment['appointment_time'];
if (strlen($time) === 5) $time .= ':00';
if (!mysqli_begin_transaction($con)) { http_response_code(500); echo json_encode(['success' => false, 'message' => 'Unable to start appointment update.']); exit(); }
$stmt = mysqli_prepare($con, 'UPDATE appointments SET status = ?, rejection_reason = ?, registrar_notes = ?, appointment_date = ?, appointment_time = ?, processed_at = CASE WHEN ? IN (\'Approved\', \'Rejected\', \'Cancelled\') THEN NOW() ELSE NULL END WHERE id = ?');
if (!$stmt) { mysqli_rollback($con); http_response_code(500); echo json_encode(['success' => false, 'message' => 'Unable to prepare appointment update.']); exit(); }
mysqli_stmt_bind_param($stmt, 'ssssssi', $status, $reason, $notes, $date, $time, $status, $appointment_id);
$ok = mysqli_stmt_execute($stmt);
$changed_rows = mysqli_stmt_affected_rows($stmt);
mysqli_stmt_close($stmt);
if (!$ok || !mysqli_commit($con)) { mysqli_rollback($con); http_response_code(500); echo json_encode(['success' => false, 'message' => 'Unable to save appointment status.']); exit(); }

// Confirm the durable record exists with the selected final status before notifying clients.
$verify_stmt = mysqli_prepare($con, 'SELECT status FROM appointments WHERE id = ? AND office = \'registrar\' LIMIT 1');
$verified = false;
if ($verify_stmt) {
    mysqli_stmt_bind_param($verify_stmt, 'i', $appointment_id);
    mysqli_stmt_execute($verify_stmt);
    $verify_result = mysqli_stmt_get_result($verify_stmt);
    $verified_row = $verify_result ? mysqli_fetch_assoc($verify_result) : null;
    $verified = $verified_row && strtolower($verified_row['status']) === strtolower($status);
    mysqli_stmt_close($verify_stmt);
}
if (!$verified) { http_response_code(500); echo json_encode(['success' => false, 'message' => 'Appointment status was not verified in the database.']); exit(); }

$message = "Appointment {$status}." . ($reason !== '' ? " Reason: {$reason}" : '') . ($notes !== '' ? " Notes: {$notes}" : '');
$payload = ['status' => $status, 'reason' => $reason, 'service' => $appointment['service'], 'appointment_date' => $date, 'appointment_time' => $time, 'processed_at' => date('Y-m-d H:i:s'), 'queue_number' => $appointment['queue_number'], 'message' => $message];
publish_realtime_event($con, 'appointment_updated', $appointment_id, $appointment['student_id'], $payload);
publish_slot_availability_event($con, $appointment['appointment_date']);
if ($date !== $appointment['appointment_date']) publish_slot_availability_event($con, $date);
echo json_encode(['success' => true, 'appointment_id' => $appointment_id, 'changed_rows' => $changed_rows, 'payload' => $payload]);
?>
