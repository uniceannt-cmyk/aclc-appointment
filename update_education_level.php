<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db.php';

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please sign in again.']);
    exit();
}

$education_level = trim($_POST['education_level'] ?? '');
if (!in_array($education_level, ['SHS', 'College'], true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Choose SHS or College.']);
    exit();
}

$stmt = mysqli_prepare($con, 'UPDATE users SET education_level = ? WHERE student_id = ? AND role = \'student\'');
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not prepare the level update.']);
    exit();
}

mysqli_stmt_bind_param($stmt, 'ss', $education_level, $_SESSION['user_id']);
$updated = mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

if (!$updated) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not save your education level.']);
    exit();
}

$_SESSION['education_level'] = $education_level;
echo json_encode(['success' => true, 'education_level' => $education_level]);
