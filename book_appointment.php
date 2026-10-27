<?php
session_start();
require_once("db.php");
require_once("realtime_helper.php");
require_once("queue_number_helper.php");
$wants_json = stripos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;

function booking_error_response($message, $redirect = 'registrar.php') {
    global $wants_json;
    if ($wants_json) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => $message]);
    } else {
        echo popup_response_script($message, 'error', $redirect);
    }
    exit();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $student_id = $_SESSION['user_id'];
    $office     = 'registrar'; // registrar only
    $date       = trim($_POST['date'] ?? '');
    $time       = trim($_POST['time'] ?? '');
    $slot_id    = !empty($_POST['slot_id']) ? (int)$_POST['slot_id'] : null;

    $level_stmt = mysqli_prepare($con, "SELECT education_level FROM users WHERE student_id = ? AND role = 'student' LIMIT 1");
    if (!$level_stmt) booking_error_response('Unable to verify your education level.');
    mysqli_stmt_bind_param($level_stmt, 's', $student_id);
    mysqli_stmt_execute($level_stmt);
    $level_row = mysqli_fetch_assoc(mysqli_stmt_get_result($level_stmt));
    mysqli_stmt_close($level_stmt);
    $education_level = $level_row['education_level'] ?? '';
    if (!in_array($education_level, ['SHS', 'College'], true)) {
        booking_error_response('Choose your education level before booking an appointment.', 'homepage.php?choose_level=1');
    }
    $clean_academic_value = static function ($value) {
        if (!is_string($value) && !is_numeric($value)) {
            return '';
        }
        return preg_replace('/\s+/', ' ', trim(strip_tags((string)$value)));
    };
    $submitted_level = $clean_academic_value($_POST['educational_level'] ?? '');
    if ($submitted_level !== $education_level) {
        booking_error_response('Your education level changed. Refresh the page and try again.');
    }

    $section = $clean_academic_value($_POST['section'] ?? '');
    $strand = null;
    $grade_level = null;
    $program = null;
    $college_year_level = null;
    if ($education_level === 'SHS') {
        $shs_sections = [
            'ASSH 1-1' => ['ASSH', '1-1'],
            'ASSH 1-2' => ['ASSH', '1-2'],
            'ASSH 1-3' => ['ASSH', '1-3'],
            'ASSH 1-4' => ['ASSH', '1-4'],
            'ASSH 1-5' => ['ASSH', '1-5'],
            'TechPro 1-1' => ['TechPro', '1-1'],
            'TechPro 1-2' => ['TechPro', '1-2'],
            'ABM 10-1' => ['ABM', '10-1'],
            'GAS 10-1' => ['GAS', '10-1'],
            'HUMSS 10-1' => ['HUMSS', '10-1'],
            'HUMSS 10-2' => ['HUMSS', '10-2'],
            'HUMSS 10-3' => ['HUMSS', '10-3'],
            'TVL 10-1' => ['TVL', '10-1'],
            'TVL 10-2' => ['TVL', '10-2'],
        ];
        $selected_shs_section = $clean_academic_value($_POST['shs_section'] ?? '');
        if (!isset($shs_sections[$selected_shs_section])) {
            booking_error_response('Select a valid SHS section.');
        }
        [$strand, $section] = $shs_sections[$selected_shs_section];
    } else {
        $college_blocks = [
            'BSE' => ['BSE', null, null],
            'BE 1-1' => ['BE', '1st', '1-1'],
            'BE 1-2' => ['BE', '1st', '1-2'],
            'ACT 1-1' => ['ACT', '1st', '1-1'],
            'ACT 2-1' => ['ACT', '2nd', '2-1'],
            'BSIT 1-1A' => ['BSIT', '1st', '1-1A'],
            'BSIT 1-1B' => ['BSIT', '1st', '1-1B'],
            'BSIT 2-1' => ['BSIT', '2nd', '2-1'],
            'BSIT 3-1' => ['BSIT', '3rd', '3-1'],
            'BSIT 4-1' => ['BSIT', '4th', '4-1'],
        ];
        $selected_college_block = $clean_academic_value($_POST['college_block'] ?? '');
        if (!isset($college_blocks[$selected_college_block])) {
            booking_error_response('Select a valid college program and section.');
        }
        [$program, $college_year_level, $section] = $college_blocks[$selected_college_block];
    }

    // Handle multi-service array
    $services_raw = $_POST['services'] ?? [];
    if (!is_array($services_raw)) {
        $services_raw = [$services_raw];
    }
    $services_raw = array_filter(array_map('trim', $services_raw));
    $service = implode(', ', array_slice($services_raw, 0, 10)); // max 10, comma-joined

    if (empty($service)) {
        booking_error_response('Please select at least one service.');
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !preg_match('/^\d{2}:\d{2}$/', $time) || !checkdate((int)substr($date,5,2),(int)substr($date,8,2),(int)substr($date,0,4))) {
        booking_error_response('Choose a valid appointment date and time.');
    }

    if ($time < '08:00' || $time >= '16:00') {
        booking_error_response('Registrar appointments are available from 8:00 AM to 4:00 PM.');
    }
    if ($time >= '11:45' && $time < '13:00') {
        booking_error_response('Appointments are unavailable during the 11:45 AM–1:00 PM lunch break.');
    }

    $service_check = mysqli_prepare($con, "SELECT COUNT(*) AS active_count FROM services WHERE office_code = 'registrar' AND service_name = ? AND target_level IN ('Both', ?) AND is_visible = 1 AND (available_from IS NULL OR available_from <= STR_TO_DATE(CONCAT(?, ' ', ?), '%Y-%m-%d %H:%i')) AND (available_until IS NULL OR available_until >= STR_TO_DATE(CONCAT(?, ' ', ?), '%Y-%m-%d %H:%i'))");
    foreach (array_slice($services_raw, 0, 10) as $requested_service) {
        mysqli_stmt_bind_param($service_check, 'ssssss', $requested_service, $education_level, $date, $time, $date, $time);
        mysqli_stmt_execute($service_check);
        $service_result = mysqli_stmt_get_result($service_check);
        $service_row = mysqli_fetch_assoc($service_result);
        if ((int)($service_row['active_count'] ?? 0) !== 1) booking_error_response('One of the selected services is no longer available. Refresh the page and try again.');
    }
    mysqli_stmt_close($service_check);

    if (!empty($service) && !empty($date) && !empty($time)) {
        $today = date('Y-m-d');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !preg_match('/^\d{2}:\d{2}$/', $time) || !checkdate((int)substr($date,5,2),(int)substr($date,8,2),(int)substr($date,0,4))) {
            booking_error_response('Choose a valid appointment date and time.');
        }
        if ($date < $today) {
            booking_error_response('Cannot book an appointment for a past date. Please select a valid future date.');
        }
        if ($date === $today && $time <= date('H:i')) booking_error_response('That time has already passed. Choose a later appointment slot.');

        // Check slot capacity
        $slot_to_link = null;
        if ($slot_id > 0) {
            $slot_check_q = mysqli_query($con, "SELECT id, max_capacity, status, start_time, end_time, slot_date FROM appointment_slots WHERE id = $slot_id AND office = 'registrar' LIMIT 1");
            if ($slot_check_q && $slot_row = mysqli_fetch_assoc($slot_check_q)) {
                if ($slot_row['slot_date'] !== $date || substr($slot_row['start_time'], 0, 5) !== $time) booking_error_response('The selected slot does not match the requested date and time. Refresh availability and try again.');
                $slot_to_link = $slot_row;
            }
        } else {
            $safe_time = substr($time, 0, 5) . ':00';
            $slot_check_q = mysqli_query($con, "SELECT id, max_capacity, status, start_time, end_time FROM appointment_slots WHERE office = 'registrar' AND slot_date = '$date' AND start_time = '$safe_time' LIMIT 1");
            if ($slot_check_q && $slot_row = mysqli_fetch_assoc($slot_check_q)) {
                $slot_to_link = $slot_row;
            }
        }

        if (!$slot_to_link) booking_error_response('Sorry, this time slot was recently removed or fully booked. Please select another slot.');

        if ($slot_to_link) {
            $slot_start_minute = ((int)substr($slot_to_link['start_time'], 0, 2) * 60) + (int)substr($slot_to_link['start_time'], 3, 2);
            $slot_end_minute = ((int)substr($slot_to_link['end_time'], 0, 2) * 60) + (int)substr($slot_to_link['end_time'], 3, 2);
            if (
                $slot_start_minute < 8 * 60 ||
                $slot_end_minute > 16 * 60 ||
                $slot_start_minute >= $slot_end_minute ||
                ($slot_start_minute < 13 * 60 && $slot_end_minute > (11 * 60 + 45))
            ) {
                booking_error_response('That appointment time is outside Registrar hours or overlaps the 11:45 AM–1:00 PM lunch break. Choose another slot.');
            }

        }

        // Persist the booking as one database transaction. The UI is updated only after commit.
        if (!mysqli_begin_transaction($con)) {
            booking_error_response('Unable to start appointment transaction: ' . mysqli_error($con));
        }

        $locked_slot_id = (int)$slot_to_link['id'];
        $slot_lock = mysqli_prepare($con, "SELECT status, max_capacity, slot_date, start_time FROM appointment_slots WHERE id = ? AND office = 'registrar' FOR UPDATE");
        if (!$slot_lock) {
            mysqli_rollback($con);
            booking_error_response('Unable to verify slot availability. Please select another slot.');
        }
        mysqli_stmt_bind_param($slot_lock, 'i', $locked_slot_id);
        if (!mysqli_stmt_execute($slot_lock)) {
            mysqli_stmt_close($slot_lock);
            mysqli_rollback($con);
            booking_error_response('Unable to verify slot availability. Please select another slot.');
        }
        $locked_slot = mysqli_fetch_assoc(mysqli_stmt_get_result($slot_lock));
        mysqli_stmt_close($slot_lock);
        if (
            !$locked_slot ||
            $locked_slot['status'] !== 'open' ||
            $locked_slot['slot_date'] !== $date ||
            substr($locked_slot['start_time'], 0, 5) !== $time
        ) {
            mysqli_rollback($con);
            booking_error_response('Sorry, this time slot was recently removed or fully booked. Please select another slot.');
        }

        $day_limit_check = mysqli_prepare($con, "SELECT max_capacity FROM appointment_day_limits WHERE office = 'registrar' AND slot_date = ? FOR UPDATE");
        if (!$day_limit_check) {
            mysqli_rollback($con);
            booking_error_response('Unable to verify the daily appointment limit.');
        }
        mysqli_stmt_bind_param($day_limit_check, 's', $date);
        if (!mysqli_stmt_execute($day_limit_check)) {
            mysqli_stmt_close($day_limit_check);
            mysqli_rollback($con);
            booking_error_response('Unable to verify the daily appointment limit.');
        }
        $day_limit = mysqli_fetch_assoc(mysqli_stmt_get_result($day_limit_check));
        mysqli_stmt_close($day_limit_check);
        if ($day_limit) {
            $daily_booked_q = mysqli_prepare($con, "SELECT COUNT(*) AS total FROM appointments WHERE office = 'registrar' AND appointment_date = ? AND status IN ('Pending','Approved')");
            if (!$daily_booked_q) {
                mysqli_rollback($con);
                booking_error_response('Unable to verify the daily appointment limit.');
            }
            mysqli_stmt_bind_param($daily_booked_q, 's', $date);
            if (!mysqli_stmt_execute($daily_booked_q)) {
                mysqli_stmt_close($daily_booked_q);
                mysqli_rollback($con);
                booking_error_response('Unable to verify the daily appointment limit.');
            }
            $daily_booked = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($daily_booked_q))['total'] ?? 0);
            mysqli_stmt_close($daily_booked_q);
            if ($daily_booked >= (int)$day_limit['max_capacity']) {
                mysqli_rollback($con);
                booking_error_response('The daily appointment limit has been reached. Choose another date.');
            }
        }

        $locked_count_stmt = mysqli_prepare($con, "SELECT COUNT(*) AS total FROM appointments WHERE (slot_id = ? OR (office = 'registrar' AND appointment_date = ? AND appointment_time = ?)) AND status IN ('Pending','Approved')");
        if (!$locked_count_stmt) {
            mysqli_rollback($con);
            booking_error_response('Unable to verify slot capacity. Please select another slot.');
        }
        $locked_time = $time . ':00';
        mysqli_stmt_bind_param($locked_count_stmt, 'iss', $locked_slot_id, $date, $locked_time);
        if (!mysqli_stmt_execute($locked_count_stmt)) {
            mysqli_stmt_close($locked_count_stmt);
            mysqli_rollback($con);
            booking_error_response('Unable to verify slot capacity. Please select another slot.');
        }
        $locked_booked = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($locked_count_stmt))['total'] ?? 0);
        mysqli_stmt_close($locked_count_stmt);
        if ($locked_booked >= (int)$locked_slot['max_capacity']) {
            mysqli_rollback($con);
            booking_error_response('Sorry, this time slot was recently removed or fully booked. Please select another slot.');
        }

        $queue_number = generate_daily_queue_number($con, $office);
        if ($queue_number === false) {
            mysqli_rollback($con);
            booking_error_response('Unable to generate your queue number. Please try again.');
        }

        $assigned_slot_id = $slot_to_link ? (int)$slot_to_link['id'] : null;

        $stmt = mysqli_prepare($con, "INSERT INTO appointments (slot_id, student_id, office, service, education_level, strand, grade_level, program, college_year_level, section, appointment_date, appointment_time, status, queue_number, queue_status) VALUES (?, ?, 'registrar', ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', ?, 'Waiting')");

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "isssssssssss", $assigned_slot_id, $student_id, $service, $education_level, $strand, $grade_level, $program, $college_year_level, $section, $date, $time, $queue_number);

            if (mysqli_stmt_execute($stmt)) {
                $new_appointment_id = mysqli_insert_id($con);
                mysqli_stmt_close($stmt);

                if ($locked_booked + 1 >= (int)$locked_slot['max_capacity']) {
                    $full_stmt = mysqli_prepare($con, "UPDATE appointment_slots SET status = 'full' WHERE id = ? AND status = 'open'");
                    if (!$full_stmt) {
                        mysqli_rollback($con);
                        booking_error_response('Unable to update slot availability. Please contact the Registrar.');
                    }
                    mysqli_stmt_bind_param($full_stmt, 'i', $locked_slot_id);
                    if (!mysqli_stmt_execute($full_stmt)) {
                        mysqli_stmt_close($full_stmt);
                        mysqli_rollback($con);
                        booking_error_response('Unable to update slot availability. Please contact the Registrar.');
                    }
                    mysqli_stmt_close($full_stmt);
                }

                if ($new_appointment_id < 1 || !mysqli_commit($con)) {
                    mysqli_rollback($con);
                    booking_error_response('Appointment was not saved: ' . mysqli_error($con));
                }

                // Read the committed row back. Never tell the browser it was saved unless it exists.
                $verify_stmt = mysqli_prepare($con, "SELECT id FROM appointments WHERE id = ? AND student_id = ? AND office = 'registrar' LIMIT 1");
                $saved = false;
                if ($verify_stmt) {
                    mysqli_stmt_bind_param($verify_stmt, 'is', $new_appointment_id, $student_id);
                    mysqli_stmt_execute($verify_stmt);
                    $verify_result = mysqli_stmt_get_result($verify_stmt);
                    $saved = $verify_result && mysqli_fetch_assoc($verify_result);
                    mysqli_stmt_close($verify_stmt);
                }
                if (!$saved) {
                    booking_error_response('The appointment could not be verified in the database after saving. Check the MySQL/XAMPP connection.');
                }

                publish_realtime_event($con, 'appointment_created', $new_appointment_id, $student_id, [
                    'student_name' => $_SESSION['user_name'] ?? 'Student',
                    'service' => $service,
                    'appointment_date' => $date,
                    'appointment_time' => $time,
                    'queue_number' => $queue_number,
                    'status' => 'Pending'
                ]);
                publish_slot_availability_event($con, $date);

                $formatted_time = date('h:i A', strtotime($time));
                $formatted_date = date('M d, Y', strtotime($date));
                $msg = "Appointment booked for {$formatted_date} at {$formatted_time}! Your Queue Ticket is [{$queue_number}]. Check your student portal for updates.";
                if ($wants_json) {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['success' => true, 'message' => $msg, 'appointment_id' => $new_appointment_id]);
                    exit();
                }
                echo popup_response_script($msg, 'success', 'homepage.php');
                exit();
            } else {
                mysqli_rollback($con);
                booking_error_response('Failed to book appointment: ' . mysqli_error($con));
            }
        } else {
            mysqli_rollback($con);
            booking_error_response('Database error during appointment booking: ' . mysqli_error($con));
        }
    } else {
        booking_error_response('Please complete all appointment fields.');
    }
}

header("Location: homepage.php");
exit();
?>
