<?php
$configured_host = getenv('DB_HOST') ?: getenv('MYSQLHOST');
$using_configured_host = $configured_host !== false && $configured_host !== '';
$host = $using_configured_host ? $configured_host : '127.0.0.1';
$user = getenv('DB_USER') ?: (getenv('MYSQLUSER') ?: 'root');
$pass = getenv('DB_PASSWORD') ?: (getenv('MYSQLPASSWORD') ?: '');
$dbname = getenv('DB_NAME') ?: (getenv('MYSQLDATABASE') ?: 'register');
$configured_port = getenv('DB_PORT') ?: getenv('MYSQLPORT');
$port = $configured_port !== false && ctype_digit($configured_port) ? (int)$configured_port : 3307;

mysqli_report(MYSQLI_REPORT_OFF);

$con = mysqli_connect($host, $user, $pass, $dbname, $port);

if (!$con && !$using_configured_host) {
    $con = mysqli_connect("localhost", $user, $pass, $dbname, 3306);
}

if (!$con) {
    error_log('Database connection failed: ' . mysqli_connect_error());
    http_response_code(500);
    exit('Database connection failed. Check the application configuration.');
}

if (!mysqli_set_charset($con, "utf8mb4")) {
    error_log("Failed to set charset: " . mysqli_error($con));
}

if (!function_exists('popup_response_script')) {
    function popup_response_script($message, $type = 'error', $redirect = '') {
        $redirect_script = $redirect !== '' ? 'window.location.href = ' . json_encode($redirect) . ';' : '';
        return '<script>document.addEventListener("DOMContentLoaded", function () { var style = document.createElement("link"); style.rel = "stylesheet"; style.href = "popup.css"; document.head.appendChild(style); var script = document.createElement("script"); script.src = "popup.js?v=' . filemtime(__DIR__ . '/popup.js') . '"; script.onload = function () { AppPopup.show(' . json_encode($message) . ', ' . json_encode($type) . '); }; document.body.appendChild(script); window.addEventListener("appPopupClosed", function () { ' . $redirect_script . ' }); });</script>';
    }
}

// 1. Ensure users table exists
$table_sql = "CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(50) NOT NULL DEFAULT '',
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    phone VARCHAR(25) NOT NULL DEFAULT '',
    password VARCHAR(255) NOT NULL,
    role VARCHAR(50) NOT NULL DEFAULT 'student',
    education_level VARCHAR(20) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if (!mysqli_query($con, $table_sql)) {
    die("Users table creation failed: " . mysqli_error($con));
}

// Check & add missing columns to users table
$columns_result = mysqli_query($con, "SHOW COLUMNS FROM users");
$existing_user_columns = [];
if ($columns_result) {
    while ($column = mysqli_fetch_assoc($columns_result)) {
        $existing_user_columns[] = $column['Field'];
    }
}

if (!in_array('phone', $existing_user_columns, true)) {
    mysqli_query($con, "ALTER TABLE users ADD COLUMN phone VARCHAR(25) NOT NULL DEFAULT '' AFTER email");
}

if (!in_array('student_id', $existing_user_columns, true)) {
    mysqli_query($con, "ALTER TABLE users ADD COLUMN student_id VARCHAR(50) NOT NULL DEFAULT '' AFTER id");
}

if (!in_array('role', $existing_user_columns, true)) {
    mysqli_query($con, "ALTER TABLE users ADD COLUMN role VARCHAR(50) NOT NULL DEFAULT 'student' AFTER password");
} else {
    // Keep the role column flexible for staff and student accounts.
    mysqli_query($con, "ALTER TABLE users MODIFY COLUMN role VARCHAR(50) NOT NULL DEFAULT 'student'");
}
if (!in_array('education_level', $existing_user_columns, true)) {
    mysqli_query($con, "ALTER TABLE users ADD COLUMN education_level VARCHAR(20) NULL AFTER role");
}
// 2. Ensure appointments table exists
$appointments_table_sql = "CREATE TABLE IF NOT EXISTS appointments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(50) NOT NULL,
    office VARCHAR(100) NOT NULL DEFAULT 'registrar',
    service VARCHAR(150) NOT NULL,
    appointment_date DATE NOT NULL, 
    appointment_time TIME NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'Pending',
    rejection_reason TEXT NULL,
    registrar_notes TEXT NULL,
    queue_number VARCHAR(20) NOT NULL DEFAULT '',
    queue_status VARCHAR(30) NOT NULL DEFAULT 'Waiting',
    called_at DATETIME NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    processed_at DATETIME NULL,
    INDEX (student_id),
    INDEX (office),
    INDEX (appointment_date)
)";

if (!mysqli_query($con, $appointments_table_sql)) {
    die("Appointments table creation failed: " . mysqli_error($con));
}

if (!mysqli_query($con, "CREATE TABLE IF NOT EXISTS queue_daily_counters (
    office VARCHAR(100) NOT NULL,
    queue_date DATE NOT NULL,
    last_number INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (office, queue_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4")) {
    die("Queue counter table creation failed: " . mysqli_error($con));
}

// Check & add missing columns to appointments table
$appointments_columns_result = mysqli_query($con, "SHOW COLUMNS FROM appointments");
$existing_app_columns = [];
if ($appointments_columns_result) {
    while ($column = mysqli_fetch_assoc($appointments_columns_result)) {
        $existing_app_columns[] = $column['Field'];
    }
}

if (!in_array('queue_number', $existing_app_columns, true)) {
    mysqli_query($con, "ALTER TABLE appointments ADD COLUMN queue_number VARCHAR(20) NOT NULL DEFAULT '' AFTER status");
}
if (!in_array('queue_status', $existing_app_columns, true)) {
    mysqli_query($con, "ALTER TABLE appointments ADD COLUMN queue_status VARCHAR(30) NOT NULL DEFAULT 'Waiting' AFTER queue_number");
}
if (!in_array('called_at', $existing_app_columns, true)) {
    mysqli_query($con, "ALTER TABLE appointments ADD COLUMN called_at DATETIME NULL DEFAULT NULL AFTER queue_status");
}
if (!in_array('slot_id', $existing_app_columns, true)) {
    mysqli_query($con, "ALTER TABLE appointments ADD COLUMN slot_id INT NULL DEFAULT NULL AFTER id");
    mysqli_query($con, "ALTER TABLE appointments ADD INDEX (slot_id)");
}
if (!in_array('rejection_reason', $existing_app_columns, true)) {
    mysqli_query($con, "ALTER TABLE appointments ADD COLUMN rejection_reason TEXT NULL AFTER status");
}
if (!in_array('updated_at', $existing_app_columns, true)) {
    mysqli_query($con, "ALTER TABLE appointments ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at");
}
if (!in_array('registrar_notes', $existing_app_columns, true)) {
    mysqli_query($con, "ALTER TABLE appointments ADD COLUMN registrar_notes TEXT NULL AFTER rejection_reason");
}
if (!in_array('processed_at', $existing_app_columns, true)) {
    mysqli_query($con, "ALTER TABLE appointments ADD COLUMN processed_at DATETIME NULL AFTER updated_at");
}
$academic_appointment_columns = [
    'education_level' => "VARCHAR(20) NULL AFTER service",
    'strand' => "VARCHAR(50) NULL AFTER education_level",
    'grade_level' => "VARCHAR(2) NULL AFTER strand",
    'program' => "VARCHAR(100) NULL AFTER grade_level",
    'college_year_level' => "VARCHAR(3) NULL AFTER program",
    'section' => "VARCHAR(50) NULL AFTER college_year_level",
];
foreach ($academic_appointment_columns as $column_name => $column_definition) {
    if (!in_array($column_name, $existing_app_columns, true)) {
        if (!mysqli_query($con, "ALTER TABLE appointments ADD COLUMN `$column_name` $column_definition")) {
            error_log("Failed to add appointments.$column_name: " . mysqli_error($con));
            http_response_code(500);
            exit('Database schema update failed. Check the PHP error log.');
        }
        $existing_app_columns[] = $column_name;
    }
}

// Canonical appointment statuses for reporting and future foreign-key integration.
mysqli_query($con, "CREATE TABLE IF NOT EXISTS statuses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(30) NOT NULL UNIQUE,
    label VARCHAR(50) NOT NULL
)");
mysqli_query($con, "INSERT IGNORE INTO statuses (code, label) VALUES
    ('pending', 'Pending'), ('approved', 'Approved'), ('rejected', 'Rejected'), ('cancelled', 'Cancelled')");

// 3. Ensure appointment_slots table exists (Staff-Controlled Availability System)
mysqli_query($con, "CREATE TABLE IF NOT EXISTS appointment_slots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    office VARCHAR(50) NOT NULL,
    slot_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    max_capacity INT NOT NULL DEFAULT 3,
    status VARCHAR(20) NOT NULL DEFAULT 'open',
    created_by VARCHAR(50) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_office_slot (office, slot_date, start_time),
    INDEX (office),
    INDEX (slot_date),
    INDEX (status)
)");

mysqli_query($con, "CREATE TABLE IF NOT EXISTS appointment_day_limits (
    office VARCHAR(50) NOT NULL,
    slot_date DATE NOT NULL,
    max_capacity INT UNSIGNED NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (office, slot_date)
)");

// Helper to seed initial published slots if none exist
$slots_count_q = mysqli_query($con, "SELECT COUNT(*) as total FROM appointment_slots");
$slots_count = mysqli_fetch_assoc($slots_count_q)['total'] ?? 0;
if ($slots_count == 0) {
    // Generate open slots for today and next 5 weekdays (e.g. 8:00 AM - 11:30 AM & 1:00 PM - 4:30 PM)
    $today_ts = strtotime('today');
    for ($d = 0; $d < 7; $d++) {
        $cur_date = date('Y-m-d', strtotime("+$d days", $today_ts));
        $day_of_week = date('N', strtotime($cur_date));
        if ($day_of_week >= 6) continue; // Skip weekends

        // Registrar standard open windows (e.g. Morning 9:00 - 11:30, Afternoon 1:30 - 4:00)
        $reg_times = [
            ['09:00:00', '09:30:00'],
            ['09:30:00', '10:00:00'],
            ['10:00:00', '10:30:00'],
            ['10:30:00', '11:00:00'],
            ['11:00:00', '11:30:00'],
            ['13:30:00', '14:00:00'],
            ['14:00:00', '14:30:00'],
            ['14:30:00', '15:00:00'],
            ['15:00:00', '15:30:00'],
            ['15:30:00', '16:00:00']
        ];
        foreach ($reg_times as $t) {
            mysqli_query($con, "INSERT IGNORE INTO appointment_slots (office, slot_date, start_time, end_time, max_capacity, status, created_by) 
                                VALUES ('registrar', '$cur_date', '{$t[0]}', '{$t[1]}', 3, 'open', 'ADMIN-001')");
        }

    }
}

// 4. Ensure offices table exists & is populated
mysqli_query($con, "CREATE TABLE IF NOT EXISTS offices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$offices_count = mysqli_query($con, "SELECT COUNT(*) AS total FROM offices");
$row_oc = mysqli_fetch_assoc($offices_count);
if (($row_oc['total'] ?? 0) == 0) {
    mysqli_query($con, "INSERT INTO offices (code, name, description) VALUES
        ('registrar', 'Registrar Office', 'Enrollment, Academic Records, Transcripts, and Certifications')");
}

// 4. Ensure services table exists & is populated
mysqli_query($con, "CREATE TABLE IF NOT EXISTS services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    office_code VARCHAR(50) NOT NULL,
    service_name VARCHAR(150) NOT NULL,
    estimated_duration_min INT DEFAULT 15,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (office_code)
)");
$service_cols = mysqli_query($con, "SHOW COLUMNS FROM services");
$service_fields = [];
if ($service_cols) while ($service_col = mysqli_fetch_assoc($service_cols)) $service_fields[] = $service_col['Field'];
if (!in_array('is_visible', $service_fields, true)) mysqli_query($con, "ALTER TABLE services ADD COLUMN is_visible TINYINT(1) NOT NULL DEFAULT 1");
if (!in_array('available_from', $service_fields, true)) mysqli_query($con, "ALTER TABLE services ADD COLUMN available_from DATETIME NULL");
if (!in_array('available_until', $service_fields, true)) mysqli_query($con, "ALTER TABLE services ADD COLUMN available_until DATETIME NULL");
if (!in_array('education_level', $service_fields, true)) mysqli_query($con, "ALTER TABLE services ADD COLUMN education_level VARCHAR(20) NOT NULL DEFAULT 'ALL'");
if (!in_array('target_level', $service_fields, true)) mysqli_query($con, "ALTER TABLE services ADD COLUMN target_level VARCHAR(20) NOT NULL DEFAULT 'Both'");
mysqli_query($con, "UPDATE services SET target_level = CASE WHEN education_level = 'SHS' THEN 'SHS' WHEN education_level = 'College' THEN 'College' ELSE 'Both' END WHERE target_level = 'Both'");

mysqli_query($con, "CREATE TABLE IF NOT EXISTS queue_control (
    office VARCHAR(50) PRIMARY KEY,
    is_paused TINYINT(1) NOT NULL DEFAULT 0,
    counter_name VARCHAR(100) NOT NULL DEFAULT 'Registrar Window 1',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");
mysqli_query($con, "INSERT IGNORE INTO queue_control (office) VALUES ('registrar')");

mysqli_query($con, "CREATE TABLE IF NOT EXISTS service_seed_state (
    id TINYINT NOT NULL PRIMARY KEY,
    seeded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
)");
$seed_state_result = mysqli_query($con, "SELECT id FROM service_seed_state WHERE id = 1 LIMIT 1");
if ($seed_state_result && mysqli_num_rows($seed_state_result) === 0) {
    $services_count = mysqli_query($con, "SELECT COUNT(*) AS total FROM services");
    $row_sc = $services_count ? mysqli_fetch_assoc($services_count) : null;
    if ((int)($row_sc['total'] ?? 0) === 0) {
        mysqli_query($con, "INSERT INTO services (office_code, service_name, estimated_duration_min) VALUES
            ('registrar', 'Enrollment Verification', 15),
            ('registrar', 'Transcript Request (TOR)', 20),
            ('registrar', 'Student Records Update', 15),
            ('registrar', 'Certificate of Enrollment (COE)', 10),
            ('registrar', 'Certificate of Good Moral Character', 10),
            ('registrar', 'Honorable Dismissal', 20)");

        $level_services = [
            ['SHS', 'Strand Transfer Request', 15],
            ['SHS', 'SHS Diploma Request', 15],
            ['College', 'Subject Add / Drop', 15],
            ['College', 'Graduation Clearance', 20],
            ['College', 'Evaluation Request', 20]
        ];
        foreach ($level_services as $level_service) {
            $level_service_stmt = mysqli_prepare($con, "INSERT INTO services (office_code, service_name, estimated_duration_min, education_level, target_level) VALUES ('registrar', ?, ?, ?, ?)");
            if ($level_service_stmt) {
                mysqli_stmt_bind_param($level_service_stmt, 'siss', $level_service[1], $level_service[2], $level_service[0], $level_service[0]);
                mysqli_stmt_execute($level_service_stmt);
                mysqli_stmt_close($level_service_stmt);
            }
        }
    }
    mysqli_query($con, "INSERT IGNORE INTO service_seed_state (id) VALUES (1)");
}
mysqli_query($con, "DELETE FROM services WHERE office_code = 'registrar' AND service_name = 'Form 137 / SF10 Request'");

// Durable event log for authenticated Server-Sent Event clients.
mysqli_query($con, "CREATE TABLE IF NOT EXISTS realtime_events (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    event_type VARCHAR(80) NOT NULL,
    appointment_id INT NOT NULL,
    student_id VARCHAR(50) NOT NULL,
    payload TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (student_id),
    INDEX (created_at)
)");

// Production admin bootstrap requires credentials supplied through environment variables.
$is_production = strtolower((string)getenv('APP_ENV')) === 'production';
$admin_email = getenv('BOOTSTRAP_ADMIN_EMAIL') ?: ($is_production ? '' : 'admin@aclc.com');
$admin_student_id = getenv('BOOTSTRAP_ADMIN_STUDENT_ID') ?: ($is_production ? '' : 'ADMIN-001');
$bootstrap_admin_password = getenv('BOOTSTRAP_ADMIN_PASSWORD');
if (!$is_production && ($bootstrap_admin_password === false || $bootstrap_admin_password === '')) {
    $bootstrap_admin_password = 'admin123';
}

if ($admin_email !== '' && $admin_student_id !== '' && $bootstrap_admin_password !== false && $bootstrap_admin_password !== '') {
    $admin_exists_stmt = mysqli_prepare($con, "SELECT student_id FROM users WHERE role = 'registrar' OR email = ? LIMIT 1");
    if ($admin_exists_stmt) {
        mysqli_stmt_bind_param($admin_exists_stmt, 's', $admin_email);
        mysqli_stmt_execute($admin_exists_stmt);
        $admin_exists_result = mysqli_stmt_get_result($admin_exists_stmt);
        $admin_exists = $admin_exists_result && mysqli_num_rows($admin_exists_result) > 0;
        mysqli_stmt_close($admin_exists_stmt);

        if (!$admin_exists) {
            $hashed_admin_password = password_hash($bootstrap_admin_password, PASSWORD_DEFAULT);
            $admin_first = 'Registrar';
            $admin_last = 'Admin';
            $admin_phone = '';
            $admin_role = 'registrar';
            $admin_insert = mysqli_prepare($con, "INSERT INTO users (student_id, first_name, last_name, email, phone, password, role) VALUES (?, ?, ?, ?, ?, ?, ?)");
            if ($admin_insert) {
                mysqli_stmt_bind_param($admin_insert, 'sssssss', $admin_student_id, $admin_first, $admin_last, $admin_email, $admin_phone, $hashed_admin_password, $admin_role);
                if (!mysqli_stmt_execute($admin_insert)) {
                    error_log('Unable to create the bootstrap registrar account: ' . mysqli_stmt_error($admin_insert));
                }
                mysqli_stmt_close($admin_insert);
            } else {
                error_log('Unable to prepare the bootstrap registrar account: ' . mysqli_error($con));
            }
        }
    } else {
        error_log('Unable to check for a bootstrap registrar account: ' . mysqli_error($con));
    }
}

?>
