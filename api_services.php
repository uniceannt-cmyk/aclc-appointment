<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
require_once __DIR__ . '/db.php';

if (($_SESSION['user_role'] ?? '') !== 'registrar' || empty($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Registrar access is required.']);
    exit();
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || ($_GET['action'] ?? '') !== 'delete') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Unsupported service action.']);
    exit();
}

$service_id = filter_var($_POST['service_id'] ?? null, FILTER_VALIDATE_INT);
if (!$service_id || $service_id < 1) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Choose a valid service.']);
    exit();
}

$stmt = mysqli_prepare($con, "DELETE FROM services WHERE id = ? AND office_code = 'registrar'");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not prepare the delete request.']);
    exit();
}
mysqli_stmt_bind_param($stmt, 'i', $service_id);
$ok = mysqli_stmt_execute($stmt);
$deleted = mysqli_stmt_affected_rows($stmt);
mysqli_stmt_close($stmt);

if (!$ok || $deleted !== 1) {
    http_response_code($ok ? 404 : 500);
    echo json_encode(['success' => false, 'message' => $ok ? 'Service was not found.' : 'Unable to delete the service.']);
    exit();
}

echo json_encode(['success' => true, 'message' => 'Service deleted.']);
