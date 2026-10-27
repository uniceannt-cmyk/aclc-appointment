<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
require_once __DIR__ . '/db.php';

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please sign in again.']);
    exit();
}

$level_stmt = mysqli_prepare($con, "SELECT education_level FROM users WHERE student_id = ? AND role = 'student' LIMIT 1");
mysqli_stmt_bind_param($level_stmt, 's', $_SESSION['user_id']);
mysqli_stmt_execute($level_stmt);
$level_row = mysqli_fetch_assoc(mysqli_stmt_get_result($level_stmt));
mysqli_stmt_close($level_stmt);
$education_level = $level_row['education_level'] ?? '';
if (!in_array($education_level, ['SHS', 'College'], true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Choose an education level first.']);
    exit();
}

$result = mysqli_prepare($con, "SELECT id, service_name, estimated_duration_min, is_visible, available_from, available_until FROM services WHERE office_code = 'registrar' AND target_level IN ('Both', ?) AND is_visible = 1 AND (available_from IS NULL OR available_from <= NOW()) AND (available_until IS NULL OR available_until >= NOW()) ORDER BY id ASC");
mysqli_stmt_bind_param($result, 's', $education_level);
mysqli_stmt_execute($result);
$result = mysqli_stmt_get_result($result);
if (!$result) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not load registrar services.']);
    exit();
}
$services = [];
while ($row = mysqli_fetch_assoc($result)) {
    $services[] = [
        'id' => (int)$row['id'],
        'service_name' => $row['service_name'],
        'estimated_duration_min' => (int)$row['estimated_duration_min'],
        'is_visible' => (int)$row['is_visible'],
        'available_from' => $row['available_from'],
        'available_until' => $row['available_until'],
    ];
}

echo json_encode(['success' => true, 'services' => $services, 'updated_at' => time()]);
