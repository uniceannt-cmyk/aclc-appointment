<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Only GET requests are supported.']);
    exit();
}

$configured_host = getenv('PUBLIC_DB_HOST');
if ($configured_host === false || $configured_host === '') {
    $configured_host = getenv('DB_HOST') ?: getenv('MYSQLHOST');
}
$using_configured_host = $configured_host !== false && $configured_host !== '';
$host = $using_configured_host ? $configured_host : '127.0.0.1';
$user = getenv('PUBLIC_DB_USER') ?: (getenv('DB_USER') ?: (getenv('MYSQLUSER') ?: 'root'));
$password = getenv('PUBLIC_DB_PASSWORD');
if ($password === false || $password === '') {
    $password = getenv('DB_PASSWORD') ?: (getenv('MYSQLPASSWORD') ?: '');
}
$database = getenv('PUBLIC_DB_NAME') ?: (getenv('DB_NAME') ?: (getenv('MYSQLDATABASE') ?: 'register'));
$configured_port = getenv('PUBLIC_DB_PORT');
if ($configured_port === false || $configured_port === '') {
    $configured_port = getenv('DB_PORT') ?: getenv('MYSQLPORT');
}
$port = $configured_port !== false && ctype_digit($configured_port) ? (int)$configured_port : ($using_configured_host ? 3306 : 3307);

mysqli_report(MYSQLI_REPORT_OFF);
$con = mysqli_connect($host, $user, $password, $database, $port);
if (!$con && !$using_configured_host) {
    $con = mysqli_connect('localhost', $user, $password, $database, 3306);
}
if (!$con) {
    error_log('Public display database connection failed: ' . mysqli_connect_error());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Queue status is temporarily unavailable.']);
    exit();
}
if (!mysqli_set_charset($con, 'utf8mb4')) {
    error_log('Public display charset setup failed: ' . mysqli_error($con));
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Queue status is temporarily unavailable.']);
    exit();
}

function public_display_error($message, $status_code = 500) {
    http_response_code($status_code);
    echo json_encode(['success' => false, 'message' => $message]);
    exit();
}

$serving_sql = "SELECT a.queue_number, a.service, a.called_at, u.first_name, u.last_name
                FROM appointments a
                LEFT JOIN users u ON u.student_id = a.student_id
                WHERE a.office = 'registrar'
                  AND a.queue_status = 'Serving'
                  AND DATE(a.called_at) = CURRENT_DATE()
                ORDER BY a.called_at DESC
                LIMIT 1";
$serving_result = mysqli_query($con, $serving_sql);
if (!$serving_result) {
    error_log('Public display serving query failed: ' . mysqli_error($con));
    public_display_error('Queue status is temporarily unavailable.');
}

$serving_row = mysqli_fetch_assoc($serving_result);
$now_serving = $serving_row ? [
    'queue_number' => (string)($serving_row['queue_number'] ?? ''),
    'student_name' => trim(($serving_row['first_name'] ?? '') . ' ' . ($serving_row['last_name'] ?? '')),
    'service' => (string)($serving_row['service'] ?? ''),
    'called_at' => (string)($serving_row['called_at'] ?? '')
] : null;

$waiting_sql = "SELECT a.queue_number, a.service, u.first_name, u.last_name
                FROM appointments a
                LEFT JOIN users u ON u.student_id = a.student_id
                WHERE a.office = 'registrar'
                  AND a.appointment_date = CURRENT_DATE()
                  AND (a.queue_status = 'Waiting' OR a.queue_status = '' OR a.queue_status IS NULL)
                  AND a.status NOT IN ('Rejected', 'Cancelled')
                ORDER BY a.appointment_time ASC, a.id ASC
                LIMIT 6";
$waiting_result = mysqli_query($con, $waiting_sql);
if (!$waiting_result) {
    error_log('Public display waiting query failed: ' . mysqli_error($con));
    public_display_error('Queue status is temporarily unavailable.');
}

$waiting_list = [];
while ($row = mysqli_fetch_assoc($waiting_result)) {
    $waiting_list[] = [
        'queue_number' => (string)($row['queue_number'] ?? ''),
        'student_name' => trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')),
        'service' => (string)($row['service'] ?? '')
    ];
}

$total_result = mysqli_query(
    $con,
    "SELECT COUNT(*) AS total
     FROM appointments
     WHERE office = 'registrar'
       AND appointment_date = CURRENT_DATE()
       AND (queue_status = 'Waiting' OR queue_status = '' OR queue_status IS NULL)
       AND status NOT IN ('Rejected', 'Cancelled')"
);
if (!$total_result) {
    error_log('Public display count query failed: ' . mysqli_error($con));
    public_display_error('Queue status is temporarily unavailable.');
}
$total_waiting = (int)(mysqli_fetch_assoc($total_result)['total'] ?? 0);

$control_result = mysqli_query(
    $con,
    "SELECT counter_name FROM queue_control WHERE office = 'registrar' LIMIT 1"
);
if (!$control_result) {
    error_log('Public display counter query failed: ' . mysqli_error($con));
    public_display_error('Queue status is temporarily unavailable.');
}
$control = mysqli_fetch_assoc($control_result);

echo json_encode([
    'success' => true,
    'queue' => [
        'counter_name' => (string)($control['counter_name'] ?? 'Registrar Window 1'),
        'now_serving' => $now_serving,
        'waiting_list' => $waiting_list,
        'total_waiting' => $total_waiting
    ]
], JSON_UNESCAPED_UNICODE);
