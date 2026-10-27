<?php
// api_admin_stream.php - Real-Time Admin Dashboard Stats Polling Endpoint

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$current_role = $_SESSION['user_role'] ?? '';
if ($current_role !== 'registrar') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Staff role required']);
    exit();
}

require_once __DIR__ . '/db.php';

$office = strtolower(trim($_GET['office'] ?? $current_role));
if ($office !== 'registrar') $office = 'registrar';

$office_filter_sql = " AND a.office = 'registrar'";

// Pending appointment requests, including tickets already being served.
$pending_sql = "SELECT a.id, a.student_id, a.office, a.service, a.appointment_date, a.appointment_time, a.status, a.queue_number, a.queue_status, a.education_level, a.strand, a.grade_level, a.program, a.college_year_level, a.section, u.first_name, u.last_name, u.phone
    FROM appointments a
    LEFT JOIN users u ON a.student_id = u.student_id
    WHERE LOWER(a.status) = 'pending'
      AND (a.queue_status IN ('Waiting','Serving','') OR a.queue_status IS NULL)
      {$office_filter_sql}
    ORDER BY a.appointment_date ASC, a.appointment_time ASC, a.id ASC";
$pending_q = mysqli_query($con, $pending_sql);
if (!$pending_q) {
    error_log('Unable to fetch pending appointment requests: ' . mysqli_error($con));
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Unable to load pending appointment requests.']);
    exit();
}
$pending_appointments = [];
while ($pending_row = mysqli_fetch_assoc($pending_q)) {
    $pending_appointments[] = $pending_row;
}
$pending_count = count($pending_appointments);

$count_waiting_q = mysqli_query($con, "SELECT COUNT(*) as cnt FROM appointments a WHERE (a.queue_status = 'Waiting' OR a.queue_status = '' OR a.queue_status IS NULL) AND a.status = 'Pending' {$office_filter_sql}");
$waiting_count = ($count_waiting_q && ($cnt_row = mysqli_fetch_assoc($count_waiting_q))) ? (int)($cnt_row['cnt'] ?? 0) : 0;

// Serving count
$count_serving_q = mysqli_query($con, "SELECT COUNT(*) as cnt FROM appointments a WHERE a.queue_status = 'Serving' {$office_filter_sql}");
$serving_count = ($count_serving_q && ($cnt_row = mysqli_fetch_assoc($count_serving_q))) ? (int)($cnt_row['cnt'] ?? 0) : 0;

echo json_encode([
    'success' => true,
    'office' => $office,
    'waiting_count' => $waiting_count,
    'serving_count' => $serving_count,
    'pending_count' => $pending_count,
    'pending_appointments' => $pending_appointments,
    'timestamp' => time()
], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
