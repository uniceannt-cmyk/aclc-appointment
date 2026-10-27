<?php
session_start();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
require_once("db.php");
require_once("realtime_helper.php");

// 1. Strict Server-Side Authentication & Role Gate
if (!isset($_SESSION['user_id'])) {
    header("Location: admin-login.php");
    exit();
}

$current_role = $_SESSION['user_role'] ?? '';
if ($current_role !== 'registrar') {
    header("Location: homepage.php");
    exit();
}

$admin_id = $_SESSION['user_id'];
$admin_stmt = mysqli_prepare($con, "SELECT student_id, first_name, last_name, email, phone, role FROM users WHERE student_id = ?");
$admin = null;

if ($admin_stmt) {
    mysqli_stmt_bind_param($admin_stmt, "s", $admin_id);
    mysqli_stmt_execute($admin_stmt);
    $admin_result = mysqli_stmt_get_result($admin_stmt);
    $admin = mysqli_fetch_assoc($admin_result);
    mysqli_stmt_close($admin_stmt);
}

// 2. Safe Date & Time Helpers
function safe_format_date($date_str, $format = 'l, F j, Y') {
    if (empty($date_str) || $date_str === '0000-00-00') {
        return 'Date Not Set';
    }
    $ts = strtotime($date_str);
    if ($ts === false || $ts < 0) {
        $parts = explode('-', $date_str);
        if (count($parts) === 3 && checkdate((int)($parts[1] ?? 0), (int)($parts[2] ?? 0), (int)($parts[0] ?? 0))) {
            $dt = new DateTime($date_str);
            return $dt->format($format);
        }
        return htmlspecialchars($date_str);
    }
    return date($format, $ts);
}

function safe_format_time($time_str, $format = 'h:i A') {
    if (empty($time_str) || $time_str === '00:00:00') {
        return 'Time Not Set';
    }
    $ts = strtotime($time_str);
    if ($ts === false) {
        return htmlspecialchars($time_str);
    }
    return date($format, $ts);
}

function render_time_slot_options($selected_time) {
    $slots = [];
    for ($minute = 8 * 60; $minute < 16 * 60; $minute += 15) {
        if ($minute >= 11 * 60 + 45 && $minute < 13 * 60) continue;
        $time_value = sprintf('%02d:%02d:00', intdiv($minute, 60), $minute % 60);
        $slots[$time_value] = date('g:i A', strtotime($time_value));
    }
    $norm_selected = substr((string)$selected_time, 0, 5);
    $html = '';
    foreach ($slots as $time_val => $time_label) {
        $norm_val = substr($time_val, 0, 5);
        $sel = ($norm_val === $norm_selected) ? 'selected' : '';
        $html .= "<option value=\"{$time_val}\" {$sel}>{$time_label}</option>";
    }
    return $html;
}

// 3. Determine Office View Filter
$selected_office = 'registrar';

$office_filter_sql = " AND a.office = 'registrar'";

// 4. Handle POST Actions: Create Staff Account
$popup_message = '';
$popup_type = 'info';
if (!empty($_SESSION['admin_flash'])) {
    $popup_message = $_SESSION['admin_flash']['message'] ?? '';
    $popup_type = $_SESSION['admin_flash']['type'] ?? 'info';
    unset($_SESSION['admin_flash']);
}

if (($_SERVER["REQUEST_METHOD"] ?? '') === "POST" && isset($_POST['action']) && $_POST['action'] === 'create_staff') {
    $staff_id = trim($_POST['staff_id'] ?? '');
    $staff_first = trim($_POST['staff_first_name'] ?? '');
    $staff_last = trim($_POST['staff_last_name'] ?? '');
    $staff_email = trim($_POST['staff_email'] ?? '');
    $staff_phone = trim($_POST['staff_phone'] ?? '');
    $staff_role = trim($_POST['staff_role'] ?? 'registrar');
    $staff_pass = $_POST['staff_password'] ?? '';

    $staff_role = 'registrar';

    if (!empty($staff_id) && !empty($staff_first) && !empty($staff_last) && !empty($staff_email) && !empty($staff_pass)) {
        $check_staff = mysqli_prepare($con, "SELECT student_id FROM users WHERE student_id = ? OR email = ?");
        mysqli_stmt_bind_param($check_staff, "ss", $staff_id, $staff_email);
        mysqli_stmt_execute($check_staff);
        mysqli_stmt_store_result($check_staff);

        if (mysqli_stmt_num_rows($check_staff) > 0) {
            $popup_message = 'Staff ID or Email already exists.';
            $popup_type = 'error';
        } else {
            $hashed = password_hash($staff_pass, PASSWORD_DEFAULT);
            $ins_staff = mysqli_prepare($con, "INSERT INTO users (student_id, first_name, last_name, email, phone, password, role) VALUES (?, ?, ?, ?, ?, ?, ?)");
            if ($ins_staff) {
                mysqli_stmt_bind_param($ins_staff, "sssssss", $staff_id, $staff_first, $staff_last, $staff_email, $staff_phone, $hashed, $staff_role);
                if (mysqli_stmt_execute($ins_staff)) {
                    $popup_message = "Staff account for {$staff_first} {$staff_last} ({$staff_role}) created successfully!";
                    $popup_type = 'success';
                } else {
                    $popup_message = 'Error creating staff: ' . mysqli_error($con);
                    $popup_type = 'error';
                }
                mysqli_stmt_close($ins_staff);
            }
        }
        mysqli_stmt_close($check_staff);
    } else {
        $popup_message = 'Please fill out all staff fields.';
        $popup_type = 'error';
    }
}

// 5. Handle POST Actions: Update Appointment Status & Schedule
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['action'] ?? '') === 'save_service_availability') {
    $service_id = (int)($_POST['service_id'] ?? 0);
    $state = $_POST['availability_state'] ?? 'unavailable';
    $target_level = $_POST['target_level'] ?? 'Both';
    $from = trim($_POST['available_from'] ?? '');
    $until = trim($_POST['available_until'] ?? '');
    $from = $from !== '' ? str_replace('T', ' ', $from) . (strlen($from) === 16 ? ':00' : '') : null;
    $until = $until !== '' ? str_replace('T', ' ', $until) . (strlen($until) === 16 ? ':00' : '') : null;
    if (!in_array($state, ['available', 'unavailable', 'scheduled'], true) || !in_array($target_level, ['Both', 'SHS', 'College'], true)) {
        $_SESSION['admin_flash'] = ['message' => 'Choose a valid service availability state.', 'type' => 'error'];
        header('Location: admin.php?office=registrar#service-settings');
        exit();
    }
    if ($state === 'scheduled' && (!$from || !$until || $from > $until)) {
        $_SESSION['admin_flash'] = ['message' => 'Scheduled services need a valid start and end time.', 'type' => 'error'];
        header('Location: admin.php?office=registrar#service-settings');
        exit();
    }
    $is_visible = $state === 'unavailable' ? 0 : 1;
    if ($state !== 'scheduled') { $from = null; $until = null; }
    $svc_stmt = mysqli_prepare($con, 'UPDATE services SET is_visible = ?, available_from = ?, available_until = ?, target_level = ? WHERE id = ? AND office_code = \'registrar\'');
    if ($svc_stmt) {
        mysqli_stmt_bind_param($svc_stmt, 'isssi', $is_visible, $from, $until, $target_level, $service_id);
        mysqli_stmt_execute($svc_stmt);
        mysqli_stmt_close($svc_stmt);
        $_SESSION['admin_flash'] = ['message' => 'Service availability saved.', 'type' => 'success'];
    }
    header('Location: admin.php?office=registrar#service-settings');
    exit();
}
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['action'] ?? '') === 'create_service') {
    $service_name = trim($_POST['service_name'] ?? '');
    $duration = filter_var($_POST['estimated_duration_min'] ?? 15, FILTER_VALIDATE_INT);
    $target_level = trim($_POST['target_level'] ?? 'Both');
    if (!in_array($target_level, ['Both', 'SHS', 'College'], true)) $target_level = 'Both';
    if ($service_name === '' || mb_strlen($service_name) > 150 || $duration === false || $duration < 5 || $duration > 240) {
        $_SESSION['admin_flash'] = ['message' => 'Enter a service name and duration between 5 and 240 minutes.', 'type' => 'error'];
    } else {
        $duplicate = mysqli_prepare($con, "SELECT id FROM services WHERE office_code = 'registrar' AND LOWER(service_name) = LOWER(?) LIMIT 1");
        mysqli_stmt_bind_param($duplicate, 's', $service_name);
        mysqli_stmt_execute($duplicate);
        mysqli_stmt_store_result($duplicate);
        if (mysqli_stmt_num_rows($duplicate) > 0) {
            $_SESSION['admin_flash'] = ['message' => 'A service with that name already exists.', 'type' => 'error'];
        } else {
            $add_service = mysqli_prepare($con, "INSERT INTO services (office_code, service_name, estimated_duration_min, target_level, is_visible) VALUES ('registrar', ?, ?, ?, 1)");
            mysqli_stmt_bind_param($add_service, 'sis', $service_name, $duration, $target_level);
            $_SESSION['admin_flash'] = mysqli_stmt_execute($add_service) ? ['message' => 'New registrar service added.', 'type' => 'success'] : ['message' => 'Unable to add the service. Please try again.', 'type' => 'error'];
            mysqli_stmt_close($add_service);
        }
        mysqli_stmt_close($duplicate);
    }
    header('Location: admin.php?office=registrar#service-settings');
    exit();
}
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['action'] ?? '') === 'save_day_capacity') {
    $capacity_date = trim($_POST['capacity_date'] ?? '');
    $capacity_raw = trim($_POST['day_capacity'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $capacity_date) || !checkdate((int)substr($capacity_date,5,2),(int)substr($capacity_date,8,2),(int)substr($capacity_date,0,4))) {
        $_SESSION['admin_flash'] = ['message' => 'Choose a valid date for the daily capacity.', 'type' => 'error'];
    } elseif ($capacity_raw === '') {
        $delete_limit = mysqli_prepare($con, "DELETE FROM appointment_day_limits WHERE office = 'registrar' AND slot_date = ?");
        mysqli_stmt_bind_param($delete_limit, 's', $capacity_date); mysqli_stmt_execute($delete_limit);
        $_SESSION['admin_flash'] = ['message' => 'Daily limit removed for this date.', 'type' => 'success'];
    } elseif (!ctype_digit($capacity_raw) || (int)$capacity_raw < 1 || (int)$capacity_raw > 1000) {
        $_SESSION['admin_flash'] = ['message' => 'Daily capacity must be between 1 and 1000.', 'type' => 'error'];
    } else {
        $capacity = (int)$capacity_raw;
        $save_limit = mysqli_prepare($con, "INSERT INTO appointment_day_limits (office, slot_date, max_capacity) VALUES ('registrar', ?, ?) ON DUPLICATE KEY UPDATE max_capacity = VALUES(max_capacity)");
        mysqli_stmt_bind_param($save_limit, 'si', $capacity_date, $capacity); mysqli_stmt_execute($save_limit);
        $_SESSION['admin_flash'] = ['message' => 'Daily appointment capacity saved.', 'type' => 'success'];
    }
    header('Location: admin.php?office=registrar&slot_date=' . rawurlencode($capacity_date) . '#slot-manager');
    exit();
}
if (($_SERVER["REQUEST_METHOD"] ?? '') === "POST" && isset($_POST['appointment_id']) && (isset($_POST['status']) || (isset($_POST['action']) && in_array($_POST['action'], ['update_status', 'update_schedule'])))) {
    $appointment_id = (int) $_POST['appointment_id'];
    // `decision` is sent by the visible Approve/Reject buttons; status is the fallback.
    $status = trim($_POST['decision'] ?? $_POST['status'] ?? 'Pending');
    $reason = trim($_POST['reason'] ?? '');
    $custom_date = trim($_POST['appointment_date'] ?? '');
    $custom_time = trim($_POST['appointment_time'] ?? '');

    if (!in_array($status, ['Pending', 'Approved', 'Rejected', 'Cancelled'], true)) {
        $popup_message = 'Invalid appointment status.';
        $popup_type = 'error';
    } elseif ($status === 'Rejected' && $reason === '') {
        $popup_message = 'A rejection reason is required.';
        $popup_type = 'error';
    } elseif (strlen($reason) > 1000) {
        $popup_message = 'Reason must be 1,000 characters or fewer.';
        $popup_type = 'error';
    } else {
        $u_q = mysqli_query($con, "SELECT id, student_id, service, office, appointment_date, appointment_time, queue_number, status
                                   FROM appointments WHERE id = $appointment_id LIMIT 1");
        if ($u_q && $existing = mysqli_fetch_assoc($u_q)) {
            $final_date = (!empty($custom_date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $custom_date)) ? $custom_date : $existing['appointment_date'];
            $final_time = !empty($custom_time) ? $custom_time : $existing['appointment_time'];
            if (strlen($final_time) === 5) {
                $final_time .= ':00';
            }

            $date_or_time_changed = ($final_date !== $existing['appointment_date'] || substr($final_time, 0, 5) !== substr($existing['appointment_time'], 0, 5));
            $stored_reason = $status === 'Rejected' ? $reason : '';
            $stmt = mysqli_prepare($con, "UPDATE appointments SET status = ?, rejection_reason = ?, appointment_date = ?, appointment_time = ?, processed_at = CASE WHEN ? IN ('Approved','Rejected','Cancelled') THEN NOW() ELSE NULL END WHERE id = ?");
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "sssssi", $status, $stored_reason, $final_date, $final_time, $status, $appointment_id);
                $updated = mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);

                if (!$updated) {
                    $popup_message = 'Unable to update appointment status.';
                    $popup_type = 'error';
                } else {
                    $queue_num = !empty($existing['queue_number']) ? $existing['queue_number'] : ('#' . $appointment_id);
                    $formatted_date = safe_format_date($final_date, 'M d, Y');
                    $formatted_time = safe_format_time($final_time, 'h:i A');

                    if ($date_or_time_changed) {
                        $popup_message = "Appointment #{$appointment_id} scheduled for {$formatted_date} at {$formatted_time} [{$status}].";
                    } else {
                        $popup_message = "Appointment #{$appointment_id} status updated to {$status}.";
                    }
                    if ($stored_reason !== '') $popup_message .= " Reason: {$stored_reason}";
                    $event_payload = [
                        'status' => $status,
                        'reason' => $stored_reason,
                        'service' => $existing['service'],
                        'appointment_date' => $final_date,
                        'appointment_time' => $final_time,
                        'queue_number' => $queue_num,
                        'message' => $popup_message
                    ];
                    publish_realtime_event($con, 'appointment_updated', $appointment_id, $existing['student_id'], $event_payload);
                    publish_slot_availability_event($con, $existing['appointment_date']);
                    if ($final_date !== $existing['appointment_date']) publish_slot_availability_event($con, $final_date);
                    $popup_type = 'success';
                }
            }
        }
    }
}

// 6. Handle Queue Calling Action
if (($_SERVER["REQUEST_METHOD"] ?? '') === "POST" && isset($_POST['action']) && $_POST['action'] === 'call_queue') {
    $appointment_id = (int) ($_POST['appointment_id'] ?? 0);

    if ($appointment_id > 0) {
        $pause_q = mysqli_query($con, "SELECT is_paused FROM queue_control WHERE office = 'registrar'");
        $pause_row = $pause_q ? mysqli_fetch_assoc($pause_q) : null;
        if (!empty($pause_row['is_paused'])) {
            $popup_message = 'Queue is paused. Resume it before calling a ticket.';
            $popup_type = 'error';
        } else {
        $info_q = mysqli_query($con, "SELECT queue_number, office FROM appointments WHERE id = $appointment_id LIMIT 1");
        if ($info_q && $info = mysqli_fetch_assoc($info_q)) {
            $target_office = $info['office'];
            $counter = trim($_POST['counter'] ?? 'Registrar Window 1');
            $counter_stmt = mysqli_prepare($con, 'UPDATE queue_control SET counter_name = ? WHERE office = \'registrar\'');
            if ($counter_stmt) { mysqli_stmt_bind_param($counter_stmt, 's', $counter); mysqli_stmt_execute($counter_stmt); mysqli_stmt_close($counter_stmt); }

            // Mark previous serving as completed for this office
            mysqli_query($con, "UPDATE appointments SET queue_status = 'Completed' WHERE office = '$target_office' AND queue_status = 'Serving'");

            $q_stmt = mysqli_prepare($con, "UPDATE appointments SET queue_status = 'Serving', called_at = NOW() WHERE id = ?");
            if ($q_stmt) {
                mysqli_stmt_bind_param($q_stmt, "i", $appointment_id);
                mysqli_stmt_execute($q_stmt);
                mysqli_stmt_close($q_stmt);

                $popup_message = "Now serving Queue [{$info['queue_number']}] at {$counter}. Public display updated.";
                $popup_type = 'success';
            }
        }
        }
    }
}

// 7. Handle Slot & Availability Management POST Actions
if (($_SERVER["REQUEST_METHOD"] ?? '') === "POST" && isset($_POST['action'])) {
    // A. Generate and Publish Dynamic Time Slots
    if ($_POST['action'] === 'generate_slots') {
        $slot_office = trim($_POST['slot_office'] ?? $selected_office);
        $slot_office = 'registrar';
        $slot_date = trim($_POST['slot_date'] ?? '');
        $start_time_raw = trim($_POST['start_time'] ?? '');
        $end_time_raw = trim($_POST['end_time'] ?? '');
        $interval_min = max(10, min(120, (int)($_POST['interval_min'] ?? 30)));
        $max_capacity = max(1, min(50, (int)($_POST['max_capacity'] ?? 3)));

        if (
            preg_match('/^\d{4}-\d{2}-\d{2}$/', $slot_date) &&
            preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $start_time_raw) &&
            preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $end_time_raw) &&
            checkdate((int)substr($slot_date, 5, 2), (int)substr($slot_date, 8, 2), (int)substr($slot_date, 0, 4))
        ) {
            $start_minute = ((int)substr($start_time_raw, 0, 2) * 60) + (int)substr($start_time_raw, 3, 2);
            $end_minute = ((int)substr($end_time_raw, 0, 2) * 60) + (int)substr($end_time_raw, 3, 2);
            $lunch_start_minute = 11 * 60 + 45;
            $lunch_end_minute = 13 * 60;
            $start_ts = strtotime("$slot_date $start_time_raw");
            $end_ts = strtotime("$slot_date $end_time_raw");

            if (
                $start_minute >= 8 * 60 &&
                $end_minute <= 16 * 60 &&
                $start_minute < $end_minute &&
                !($start_minute >= $lunch_start_minute && $start_minute < $lunch_end_minute) &&
                !($end_minute > $lunch_start_minute && $end_minute < $lunch_end_minute)
            ) {
                $slots_created = 0;
                $curr = $start_ts;
                while ($curr + ($interval_min * 60) <= $end_ts) {
                    $slot_start_minute = (int)date('G', $curr) * 60 + (int)date('i', $curr);
                    $slot_end_ts = $curr + ($interval_min * 60);
                    $slot_end_minute = (int)date('G', $slot_end_ts) * 60 + (int)date('i', $slot_end_ts);
                    if ($slot_start_minute < $lunch_end_minute && $slot_end_minute > $lunch_start_minute) {
                        $curr = $slot_end_ts;
                        continue;
                    }

                    $s_time = date('H:i:00', $curr);
                    $e_time = date('H:i:00', $slot_end_ts);

                    $ins_slot = mysqli_prepare($con, "
                        INSERT INTO appointment_slots (office, slot_date, start_time, end_time, max_capacity, status, created_by)
                        VALUES (?, ?, ?, ?, ?, 'open', ?)
                        ON DUPLICATE KEY UPDATE max_capacity = VALUES(max_capacity), end_time = VALUES(end_time), status = 'open'
                    ");
                    if ($ins_slot) {
                        mysqli_stmt_bind_param($ins_slot, "ssssis", $slot_office, $slot_date, $s_time, $e_time, $max_capacity, $admin_id);
                        if (mysqli_stmt_execute($ins_slot)) {
                            $slots_created++;
                        }
                        mysqli_stmt_close($ins_slot);
                    }
                    $curr += ($interval_min * 60);
                }

                $formatted_d = safe_format_date($slot_date, 'M d, Y');
                $popup_message = "Successfully published {$slots_created} open slots for {$formatted_d} (" . ucfirst($slot_office) . ")!";
                $popup_type = 'success';
                if ($slots_created > 0) publish_slot_availability_event($con, $slot_date);
            } else {
                $popup_message = 'Choose a time range between 8:00 AM and 4:00 PM. Slots cannot overlap the 11:45 AM–1:00 PM lunch break.';
                $popup_type = 'error';
            }
        } else {
            $popup_message = 'Please provide a valid date and start and end times.';
            $popup_type = 'error';
        }
    }

    // B. Toggle Slot Status (Open / Closed)
    if ($_POST['action'] === 'toggle_slot') {
        $slot_id = (int)($_POST['slot_id'] ?? 0);
        $new_status = trim($_POST['new_status'] ?? 'closed');
        if (!in_array($new_status, ['open', 'closed'])) {
            $new_status = 'closed';
        }

        if ($slot_id > 0) {
            $slot_date_result = mysqli_query($con, "SELECT slot_date FROM appointment_slots WHERE id = $slot_id AND office = 'registrar' LIMIT 1");
            $slot_date_row = $slot_date_result ? mysqli_fetch_assoc($slot_date_result) : null;
            $stmt = mysqli_prepare($con, "UPDATE appointment_slots SET status = ? WHERE id = ?");
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "si", $new_status, $slot_id);
                $updated = mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                if ($updated) {
                    if ($slot_date_row) publish_slot_availability_event($con, $slot_date_row['slot_date']);
                    $popup_message = "Slot status changed to " . strtoupper($new_status) . ".";
                    $popup_type = 'success';
                }
            }
        }
    }

    // C. Delete Single Slot
    if ($_POST['action'] === 'delete_slot') {
        $slot_id = (int)($_POST['slot_id'] ?? 0);
        if ($slot_id > 0) {
            $slot_date_result = mysqli_query($con, "SELECT slot_date FROM appointment_slots WHERE id = $slot_id AND office = 'registrar' LIMIT 1");
            $slot_date_row = $slot_date_result ? mysqli_fetch_assoc($slot_date_result) : null;
            mysqli_query($con, "UPDATE appointments SET slot_id = NULL WHERE slot_id = $slot_id");
            $deleted = mysqli_query($con, "DELETE FROM appointment_slots WHERE id = $slot_id AND office = 'registrar'");
            if ($deleted && mysqli_affected_rows($con) > 0) {
                if ($slot_date_row) publish_slot_availability_event($con, $slot_date_row['slot_date']);
                $popup_message = "Availability time slot deleted. Student appointment records were kept.";
                $popup_type = 'success';
            }
        }
    }

    // D. Blackout / Close All Slots for a Date
    if ($_POST['action'] === 'close_date_slots') {
        $slot_office = trim($_POST['slot_office'] ?? $selected_office);
        $slot_date = trim($_POST['slot_date'] ?? '');
        if (!empty($slot_date)) {
            $close_q = mysqli_query($con, "UPDATE appointment_slots SET status = 'closed' WHERE office = 'registrar' AND slot_date = '" . mysqli_real_escape_string($con, $slot_date) . "'");
            if ($close_q) publish_slot_availability_event($con, $slot_date);
            $popup_message = "All slots for " . safe_format_date($slot_date, 'M d, Y') . " set to CLOSED.";
            $popup_type = 'info';
        }
    }

    // E. Delete All Unbooked Slots for a Date (safe: protects slots with active bookings)
    if ($_POST['action'] === 'delete_all_slots') {
        $slot_office = trim($_POST['slot_office'] ?? $selected_office);
        $slot_date = trim($_POST['slot_date'] ?? '');
        if (!empty($slot_date) && !empty($slot_office)) {
            // Find slots with active bookings — protect them
            $protected_q = mysqli_query($con, "
                SELECT s.id FROM appointment_slots s
                WHERE s.office = '" . mysqli_real_escape_string($con, $slot_office) . "'
                  AND s.slot_date = '" . mysqli_real_escape_string($con, $slot_date) . "'
                  AND (SELECT COUNT(*) FROM appointments a 
                       WHERE (a.slot_id = s.id OR (a.office = s.office AND a.appointment_date = s.slot_date AND a.appointment_time = s.start_time))
                         AND a.status IN ('Pending', 'Approved')) > 0
            ");
            $protected_ids = [];
            if ($protected_q) {
                while ($p = mysqli_fetch_assoc($protected_q)) {
                    $protected_ids[] = (int)$p['id'];
                }
            }

            $where_clause = "office = '" . mysqli_real_escape_string($con, $slot_office) . "' AND slot_date = '" . mysqli_real_escape_string($con, $slot_date) . "'";
            if (!empty($protected_ids)) {
                $where_clause .= " AND id NOT IN (" . implode(',', $protected_ids) . ")";
            }

            $delete_q = mysqli_query($con, "DELETE FROM appointment_slots WHERE $where_clause");
            $deleted_count = $delete_q ? mysqli_affected_rows($con) : 0;
            $protected_count = count($protected_ids);
            if ($deleted_count > 0) publish_slot_availability_event($con, $slot_date);

            $formatted_d = safe_format_date($slot_date, 'M d, Y');
            if ($deleted_count > 0 && $protected_count > 0) {
                $popup_message = "Deleted {$deleted_count} unbooked slot(s) for {$formatted_d}. {$protected_count} slot(s) with active bookings were preserved.";
                $popup_type = 'info';
            } elseif ($deleted_count > 0) {
                $popup_message = "All {$deleted_count} slot(s) for {$formatted_d} deleted successfully.";
                $popup_type = 'success';
            } else {
                $popup_message = "No deletable slots found for {$formatted_d}" . ($protected_count > 0 ? " ({$protected_count} slot(s) protected due to active bookings)." : ".");
                $popup_type = 'info';
            }
        }
    }
}

// Finish every regular form submission with Post/Redirect/Get. This prevents browser
// refreshes from resubmitting destructive slot actions or showing a resubmit warning.
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if ($popup_message !== '') {
        $_SESSION['admin_flash'] = ['message' => $popup_message, 'type' => $popup_type];
    }
    $redirect_slot_date = trim($_POST['slot_date'] ?? $_GET['slot_date'] ?? date('Y-m-d'));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $redirect_slot_date)) $redirect_slot_date = date('Y-m-d');
    header('Location: admin.php?office=registrar&slot_date=' . rawurlencode($redirect_slot_date), true, 303);
    exit();
}

// 8. Fetch Pending and Past Appointments using LEFT JOIN for safety
$pending_sql = "SELECT a.id, a.student_id, a.office, a.service, a.appointment_date, a.appointment_time, a.status, a.queue_number, a.queue_status, u.first_name, u.last_name, u.phone, a.education_level, a.strand, a.grade_level, a.program, a.college_year_level, a.section 
                FROM appointments a 
                LEFT JOIN users u ON a.student_id = u.student_id 
                WHERE LOWER(a.status) = 'pending' AND (a.queue_status IN ('Waiting','Serving','') OR a.queue_status IS NULL) {$office_filter_sql}
                ORDER BY a.appointment_date ASC, a.appointment_time ASC";
$result = mysqli_query($con, $pending_sql);

$history_sql = "SELECT a.id, a.student_id, a.office, a.service, a.appointment_date, a.appointment_time, a.status, a.rejection_reason, a.registrar_notes, a.processed_at, a.queue_number, a.queue_status, u.first_name, u.last_name, u.phone, a.education_level, a.strand, a.grade_level, a.program, a.college_year_level, a.section 
                FROM appointments a 
                LEFT JOIN users u ON a.student_id = u.student_id 
                WHERE (LOWER(a.status) IN ('approved', 'rejected', 'cancelled') OR LOWER(a.queue_status) IN ('completed','skipped')) {$office_filter_sql}
                ORDER BY a.appointment_date DESC, a.appointment_time DESC";

// CSV Export Handler
if (($_GET['export'] ?? '') === 'csv') {
    $export_result = mysqli_query($con, $history_sql);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="appointments-' . $selected_office . '-' . date('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Queue Number', 'Student ID', 'First Name', 'Last Name', 'Phone', 'Education Level', 'Strand', 'Grade Level', 'Program', 'College Year Level', 'Section', 'Office', 'Service', 'Date', 'Time', 'Status', 'Queue Status']);
    if ($export_result) {
        while ($export_row = mysqli_fetch_assoc($export_result)) {
            fputcsv($output, [
                $export_row['id'],
                $export_row['queue_number'] ?: ('#' . $export_row['id']),
                $export_row['student_id'],
                $export_row['first_name'] ?? 'N/A',
                $export_row['last_name'] ?? '',
                $export_row['phone'] ?? '',
                $export_row['education_level'] ?? '',
                $export_row['strand'] ?? '',
                $export_row['grade_level'] ?? '',
                $export_row['program'] ?? '',
                $export_row['college_year_level'] ?? '',
                $export_row['section'] ?? '',
                $export_row['office'],
                $export_row['service'],
                $export_row['appointment_date'],
                $export_row['appointment_time'],
                $export_row['status'],
                $export_row['queue_status']
            ]);
        }
    }
    fclose($output);
    exit();
}

$pastResult = mysqli_query($con, $history_sql);

// Queue statistics for today
$today_str = date('Y-m-d');
$count_serving_q = mysqli_query($con, "SELECT COUNT(*) as cnt FROM appointments a WHERE a.queue_status = 'Serving' {$office_filter_sql}");
$serving_count = ($count_serving_q && ($cnt_row = mysqli_fetch_assoc($count_serving_q))) ? (int)($cnt_row['cnt'] ?? 0) : 0;

$count_waiting_q = mysqli_query($con, "SELECT COUNT(*) as cnt FROM appointments a WHERE (a.queue_status = 'Waiting' OR a.queue_status = '' OR a.queue_status IS NULL) AND a.status = 'Pending' {$office_filter_sql}");
$waiting_count = ($count_waiting_q && ($cnt_row = mysqli_fetch_assoc($count_waiting_q))) ? (int)($cnt_row['cnt'] ?? 0) : 0;

// Published Slot Management Data
$slot_manage_date = trim($_GET['slot_date'] ?? date('Y-m-d'));
$day_limit_stmt = mysqli_prepare($con, "SELECT max_capacity FROM appointment_day_limits WHERE office = 'registrar' AND slot_date = ?");
$day_limit_value = '';
if ($day_limit_stmt) {
    mysqli_stmt_bind_param($day_limit_stmt, 's', $slot_manage_date);
    mysqli_stmt_execute($day_limit_stmt);
    $day_limit_result = mysqli_stmt_get_result($day_limit_stmt);
    if ($day_limit_row = mysqli_fetch_assoc($day_limit_result)) $day_limit_value = (string)$day_limit_row['max_capacity'];
    mysqli_stmt_close($day_limit_stmt);
}
$day_booked_stmt = mysqli_prepare($con, "SELECT COUNT(*) AS booked FROM appointments WHERE office = 'registrar' AND appointment_date = ? AND status IN ('Pending','Approved')");
$day_booked_value = 0;
if ($day_booked_stmt) {
    mysqli_stmt_bind_param($day_booked_stmt, 's', $slot_manage_date);
    mysqli_stmt_execute($day_booked_stmt);
    $day_booked_value = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($day_booked_stmt))['booked'] ?? 0);
    mysqli_stmt_close($day_booked_stmt);
}
$slot_manage_office = 'registrar';
$slots_list_q = mysqli_query($con, "
    SELECT 
        s.id, s.office, s.slot_date, s.start_time, s.end_time, s.max_capacity, s.status,
        (SELECT COUNT(*) FROM appointments a 
         WHERE (a.slot_id = s.id OR (a.office = s.office AND a.appointment_date = s.slot_date AND a.appointment_time = s.start_time)) 
           AND a.status IN ('Pending', 'Approved')) as booked_count
    FROM appointment_slots s
    WHERE s.office = '$slot_manage_office' AND s.slot_date = '$slot_manage_date'
    ORDER BY s.start_time ASC
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff & Admin Dashboard - ACLC Queuing & Appointments</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin.css?v=<?php echo filemtime(__DIR__ . '/admin.css'); ?>">
    <link rel="stylesheet" href="popup.css">
    <script src="popup.js?v=<?php echo filemtime(__DIR__ . '/popup.js'); ?>"></script>
</head>
<body>

    <?php
    $registrarHeaderPage = 'dashboard';
    $registrarHeaderEyebrow = 'Registrar Dashboard';
    $registrarHeaderTitle = 'Registrar Admin & Queue Dashboard';
    $registrarHeaderSubtitle = '';
    require __DIR__ . '/registrar-header.php';
    ?>

    <nav class="dashboard-tabs" role="tablist" aria-label="Registrar dashboard workflows">
        <button type="button" class="dashboard-tab active" role="tab" aria-selected="true" aria-controls="queue-controls" data-dashboard-tab="live-queue-panel">
            <i class="fas fa-bullhorn"></i> Live Queue Operations
        </button>
        <button type="button" class="dashboard-tab" role="tab" aria-selected="false" aria-controls="pending-queue" data-dashboard-tab="appointment-requests-panel" id="appointmentRequestsTab">
            <i class="fas fa-clipboard-check"></i> Appointment Requests
            <span class="pending-tab-badge" id="pendingTabBadge" aria-label="<?php echo ($result ? mysqli_num_rows($result) : 0); ?> pending requests" <?php echo (!$result || mysqli_num_rows($result) === 0) ? 'hidden' : ''; ?>><?php echo ($result ? mysqli_num_rows($result) : 0); ?></span>
        </button>
        <button type="button" class="dashboard-tab" role="tab" aria-selected="false" aria-controls="slot-manager" data-dashboard-tab="slot-manager-panel">
            <i class="fas fa-calendar-check"></i> Slot Manager
        </button>
        <button type="button" class="dashboard-tab" role="tab" aria-selected="false" aria-controls="service-availability" data-dashboard-tab="service-settings-panel">
            <i class="fas fa-toggle-on"></i> Service Settings
        </button>
    </nav>

    <section class="table-card queue-controls-card dashboard-tab-content live-queue-content active" id="queue-controls">
        <!-- Office and live queue controls belong only to the Live Queue tab. -->
        <div class="dashboard-controls-bar">
            <div class="office-tabs">
                <a href="admin.php?office=registrar&slot_date=<?php echo htmlspecialchars($slot_manage_date); ?>" class="office-tab <?php echo ($selected_office === 'registrar') ? 'active' : ''; ?>">
                    <i class="fas fa-user-graduate"></i> Registrar Office
                </a>
            </div>

            <div class="stats-pills">
                <div class="stat-pill stat-waiting" id="statWaiting">
                    <i class="fas fa-user-clock"></i> Waiting: <strong id="waitingCountVal"><?php echo $waiting_count; ?></strong>
                </div>
                <div class="stat-pill stat-serving" id="statServing">
                    <i class="fas fa-bullhorn"></i> Currently Serving: <strong id="servingCountVal"><?php echo $serving_count; ?></strong>
                </div>
                <span class="live-indicator" id="adminLiveBadge" title="Live dashboard sync active">
                    <span class="live-dot"></span> Live
                </span>
                <button type="button" class="btn-refresh" id="manualRefreshBtn" title="Reload page to refresh all data">
                    <i class="fas fa-sync-alt" id="refreshIcon"></i> Refresh
                </button>
            </div>
        </div>
        <div class="queue-controls-heading">
            <div>
                <p class="queue-section-kicker">Live queue</p>
                <h2>Call and manage tickets</h2>
                <p>Use the next-ticket button for normal service, or choose a ticket for a specific action.</p>
            </div>
            <a href="public-display.php" target="_blank" rel="noopener" class="queue-display-link"><i class="fas fa-tv" aria-hidden="true"></i> Open public display</a>
        </div>
        <div class="queue-workflow-grid">
            <section class="queue-action-panel queue-next-panel" aria-labelledby="callNextHeading">
                <span class="queue-step-number">1</span>
                <div class="queue-action-content">
                    <h3 id="callNextHeading">Call the next waiting ticket</h3>
                    <p>Calls the earliest eligible ticket and displays it at the counter.</p>
                    <button type="button" class="btn-call queue-primary-button" data-queue-action="call_next">
                        <i class="fas fa-bullhorn" aria-hidden="true"></i> Call next waiting ticket
                    </button>
                </div>
            </section>
            <section class="queue-action-panel" aria-labelledby="selectedTicketHeading">
                <span class="queue-step-number">2</span>
                <div class="queue-action-content">
                    <h3 id="selectedTicketHeading">Choose a specific ticket</h3>
                    <label for="queueControlId">Waiting or currently serving</label>
                    <select id="queueControlId">
                        <option value="" data-queue-status="">Select a queue ticket</option>
                        <?php
                        $queue_options = mysqli_query($con, "SELECT id, queue_number, queue_status FROM appointments WHERE office = 'registrar' AND status IN ('Pending','Approved') AND queue_status IN ('Waiting','Serving','') ORDER BY appointment_date, appointment_time");
                        if ($queue_options):
                            while ($qo = mysqli_fetch_assoc($queue_options)):
                                $ticket_status = $qo['queue_status'] ?: 'Waiting';
                        ?>
                            <option value="<?php echo (int)$qo['id']; ?>" data-queue-status="<?php echo htmlspecialchars($ticket_status, ENT_QUOTES); ?>">
                                <?php echo htmlspecialchars(($qo['queue_number'] ?: '#' . $qo['id']) . ' · ' . $ticket_status); ?>
                            </option>
                        <?php endwhile; endif; ?>
                    </select>
                    <div class="queue-selected-actions" aria-label="Selected ticket actions">
                        <button type="button" class="btn-call" data-queue-action="call" data-requires-ticket data-allowed-status="Waiting" disabled>Call selected</button>
                        <button type="button" class="btn-refresh" data-queue-action="skip" data-requires-ticket data-allowed-status="Waiting,Serving" disabled>Skip ticket</button>
                        <button type="button" class="btn-refresh" data-queue-action="complete" data-requires-ticket data-allowed-status="Serving" disabled>Complete service</button>
                    </div>
                    <p class="queue-action-hint">Complete service is available only while a ticket is being served.</p>
                </div>
            </section>
        </div>
        <div class="queue-operations-footer">
            <label class="queue-counter-field" for="queueCounterName">
                <span>Counter name shown on public display</span>
                <input id="queueCounterName" type="text" value="Registrar Window 1" maxlength="100">
            </label>
            <div class="queue-pause-controls">
                <span>New calls</span>
                <button type="button" class="btn-refresh" data-queue-action="pause"><i class="fas fa-pause" aria-hidden="true"></i> Pause</button>
                <button type="button" class="btn-refresh" data-queue-action="resume"><i class="fas fa-play" aria-hidden="true"></i> Resume</button>
                <small>Pause stops new tickets from being called.</small>
            </div>
        </div>
    </section>

    <!-- Admin Profile Modal -->
    <div class="profile-modal" id="profileModal" aria-hidden="true">
        <div class="profile-modal-content">
            <button type="button" class="close-profile" id="closeProfile" aria-label="Close profile">&times;</button>
            <div class="profile-modal-header">
                <img src="https://ui-avatars.com/api/?name=<?php echo rawurlencode($_SESSION['user_name'] ?? 'Admin'); ?>&background=0284c7&color=fff&size=200" alt="Profile avatar">
                <h3><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Admin'); ?></h3>
            </div>
            <div class="profile-details">
                <div>
                    <span>Staff ID</span>
                    <strong><?php echo htmlspecialchars($admin['student_id'] ?? $admin_id); ?></strong>
                </div>
                <div>
                    <span>Email</span>
                    <strong><?php echo htmlspecialchars($_SESSION['user_email'] ?? ($admin['email'] ?? 'N/A')); ?></strong>
                </div>
                <div>
                    <span>Phone</span>
                    <strong><?php echo htmlspecialchars($admin['phone'] ?? 'Not set'); ?></strong>
                </div>
                <div>
                    <span>Assigned Role</span>
                    <strong><?php echo htmlspecialchars(ucfirst($_SESSION['user_role'] ?? ($admin['role'] ?? 'registrar'))); ?></strong>
                </div>
            </div>
        </div>
    </div>

    <!-- Onboard New Staff Modal -->
    <div class="profile-modal" id="staffModal" aria-hidden="true">
        <div class="profile-modal-content" style="max-width: 480px;">
            <button type="button" class="close-profile" id="closeStaffModal" aria-label="Close modal">&times;</button>
            <div class="profile-modal-header">
                <i class="fas fa-user-plus" style="font-size: 2.5rem; color: #0284c7; margin-bottom: 0.5rem;"></i>
                <h3>Onboard Staff Account</h3>
                <p style="font-size: 0.85rem; color: var(--muted);">Create additional Registrar staff credentials</p>
            </div>
            <form method="POST" action="admin.php?office=<?php echo htmlspecialchars($selected_office); ?>" class="staff-create-form">
                <input type="hidden" name="action" value="create_staff">
                <div class="form-group">
                    <label for="staff_id">Staff / Employee ID:</label>
                    <input type="text" id="staff_id" name="staff_id" placeholder="e.g. REG-002 or CSH-002" required>
                </div>
                <div style="display: flex; gap: 10px;">
                    <div class="form-group" style="flex: 1;">
                        <label for="staff_first_name">First Name:</label>
                        <input type="text" id="staff_first_name" name="staff_first_name" placeholder="First Name" required>
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label for="staff_last_name">Last Name:</label>
                        <input type="text" id="staff_last_name" name="staff_last_name" placeholder="Last Name" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="staff_email">Staff Email Address:</label>
                    <input type="email" id="staff_email" name="staff_email" placeholder="staff@aclc.com" required>
                </div>
                <div class="form-group">
                    <label for="staff_phone">Mobile Number:</label>
                    <input type="tel" id="staff_phone" name="staff_phone" placeholder="e.g. 09171234567">
                </div>
                <div class="form-group">
                    <label for="staff_role">Office Role:</label>
                    <input type="text" id="staff_role" value="Registrar Staff" readonly>
                    <input type="hidden" name="staff_role" value="registrar">
                </div>
                <div class="form-group">
                    <label for="staff_password">Temporary Password:</label>
                    <input type="password" id="staff_password" name="staff_password" placeholder="Create Password" required>
                </div>
                <button type="submit" class="btn-submit-staff">Create Staff Account</button>
            </form>
        </div>
    </div>

    <!-- SERVICE AVAILABILITY CONTROL -->
    <section class="table-card dashboard-tab-content service-settings-content" id="service-availability" style="margin: 18px 0; padding: 20px;">
        <div class="service-admin-heading"><div><h2><i class="fas fa-toggle-on" style="color:#0284c7"></i> Registrar Service Availability</h2>
        <p>Set each service to available, unavailable, or scheduled for a specific booking window.</p>
        </div><button type="button" class="btn-call" id="openAddService">+ Add New Service</button></div>
        <div class="service-admin-list">
        <div class="service-level-filters" role="tablist" aria-label="Filter services by audience">
            <button type="button" class="service-level-filter is-active" data-service-filter="all">All Services</button>
            <button type="button" class="service-level-filter" data-service-filter="SHS">SHS Services</button>
            <button type="button" class="service-level-filter" data-service-filter="College">College Services</button>
            <label class="service-search-label" for="serviceAdminSearch">Search services</label>
            <input type="search" id="serviceAdminSearch" class="service-admin-search" placeholder="Search service name..." autocomplete="off">
        </div>
        <?php $service_admin_rows = mysqli_query($con, "SELECT id, service_name, target_level, is_visible, available_from, available_until FROM services WHERE office_code = 'registrar' ORDER BY id"); ?>
        <?php if ($service_admin_rows): while ($svc = mysqli_fetch_assoc($service_admin_rows)): ?>
            <?php $admin_service_state = empty($svc['is_visible']) ? 'unavailable' : ((!empty($svc['available_from']) && strtotime($svc['available_from']) > time()) ? 'scheduled' : ((!empty($svc['available_until']) && strtotime($svc['available_until']) < time()) ? 'unavailable' : 'available')); ?>
            <form method="post" action="admin.php?office=registrar" class="service-admin-row" data-target-level="<?php echo htmlspecialchars($svc['target_level'] ?: 'Both'); ?>">
                <input type="hidden" name="action" value="save_service_availability">
                <input type="hidden" name="service_id" value="<?php echo (int)$svc['id']; ?>">
                <input type="hidden" name="dashboard_tab" value="service-settings">
                <strong class="service-row-title"><?php echo htmlspecialchars($svc['service_name']); ?></strong>
                <div class="service-row-controls">
                    <label>Availability <select name="availability_state"><option value="available" <?php echo $admin_service_state==='available'?'selected':''; ?>>Available</option><option value="unavailable" <?php echo $admin_service_state==='unavailable'?'selected':''; ?>>Unavailable</option><option value="scheduled" <?php echo $admin_service_state==='scheduled'?'selected':''; ?>>Scheduled</option></select></label>
                    <label>Target Level / Audience <select name="target_level"><option value="Both" <?php echo ($svc['target_level'] ?? 'Both') === 'Both' ? 'selected' : ''; ?>>Both (SHS &amp; College)</option><option value="SHS" <?php echo ($svc['target_level'] ?? '') === 'SHS' ? 'selected' : ''; ?>>Senior High School (SHS)</option><option value="College" <?php echo ($svc['target_level'] ?? '') === 'College' ? 'selected' : ''; ?>>College</option></select></label>
                    <label>Available from <input type="datetime-local" name="available_from" value="<?php echo $svc['available_from'] ? htmlspecialchars(date('Y-m-d\TH:i', strtotime($svc['available_from']))) : ''; ?>"></label>
                    <label>Until <input type="datetime-local" name="available_until" value="<?php echo $svc['available_until'] ? htmlspecialchars(date('Y-m-d\TH:i', strtotime($svc['available_until']))) : ''; ?>"></label>
                </div>
                <div class="service-row-actions">
                    <button type="submit" class="btn-refresh">Save</button>
                    <button type="button" class="btn-delete-service" data-delete-service="<?php echo (int)$svc['id']; ?>" data-service-name="<?php echo htmlspecialchars($svc['service_name'], ENT_QUOTES); ?>">Delete</button>
                </div>
            </form>
        <?php endwhile; endif; ?>
        </div>
    </section>

    <div class="profile-modal" id="addServiceModal" aria-hidden="true"><div class="profile-modal-content add-service-dialog">
      <button type="button" class="close-profile" id="closeAddService" aria-label="Close">&times;</button><h2>Add Registrar Service</h2>
    <form method="post" action="admin.php?office=registrar"><input type="hidden" name="action" value="create_service"><input type="hidden" name="dashboard_tab" value="service-settings">
        <label for="newServiceName">Service name</label><input id="newServiceName" name="service_name" maxlength="150" required>
        <label for="newServiceDuration">Estimated duration (minutes)</label><input id="newServiceDuration" type="number" name="estimated_duration_min" min="5" max="240" value="15" required>
        <label for="newServiceTargetLevel">Target Level / Audience</label><select id="newServiceTargetLevel" name="target_level" required><option value="Both">Both (SHS &amp; College)</option><option value="SHS">Senior High School (SHS)</option><option value="College">College</option></select>
        <button class="btn-call" type="submit">Add service</button>
      </form>
    </div></div>

    <!-- AVAILABILITY & SLOT MANAGER (Staff-Controlled Slot Publisher) -->
    <section class="table-card slot-manager-card dashboard-tab-content slot-manager-content" id="slot-manager">
        <div class="slot-manager-header">
            <div>
                <h2><i class="fas fa-calendar-check" style="color: #0284c7;"></i> <?php echo ucfirst($slot_manage_office); ?> Availability & Slot Manager</h2>
                <p style="font-size: 0.85rem; color: var(--muted); margin-top: 2px;">
                    Control and publish open appointment slots. Students can only book within your open time blocks.
                </p>
            </div>
        </div>

        <div class="slot-top-controls">
            <section class="slot-control-card">
                <div class="slot-control-copy">
                    <h3>View Date</h3>
                    <p>Choose a day to review or manage its appointment slots.</p>
                </div>
                <form method="GET" action="admin.php" class="slot-filter-form">
                    <input type="hidden" name="office" value="<?php echo htmlspecialchars($selected_office); ?>">
                    <label for="slot_date_picker" class="sr-only">View date</label>
                    <input type="date" id="slot_date_picker" name="slot_date" value="<?php echo htmlspecialchars($slot_manage_date); ?>" onchange="this.form.submit()">
                </form>
            </section>
            <section class="slot-control-card">
                <div class="slot-control-copy">
                    <h3>Daily Booking Cap</h3>
                    <p>Set a maximum for the selected date, or leave blank for no limit.</p>
                </div>
                <form method="POST" action="admin.php?office=registrar&slot_date=<?php echo rawurlencode($slot_manage_date); ?>#slot-manager" class="day-capacity-form">
                    <input type="hidden" name="action" value="save_day_capacity">
                    <input type="hidden" name="capacity_date" value="<?php echo htmlspecialchars($slot_manage_date); ?>">
                    <label for="day_capacity" class="sr-only">Daily booking cap</label>
                    <input type="number" id="day_capacity" name="day_capacity" min="1" max="1000" value="<?php echo htmlspecialchars($day_limit_value); ?>" placeholder="No daily limit">
                    <button type="submit" class="btn-refresh">Save cap</button>
                    <small>Current bookings: <strong><?php echo $day_booked_value; ?><?php echo $day_limit_value !== '' ? ' / ' . htmlspecialchars($day_limit_value) : ''; ?></strong></small>
                </form>
            </section>
        </div>

        <div class="slot-calendar" id="slotCalendar" data-selected-date="<?php echo htmlspecialchars($slot_manage_date); ?>">
            <div class="slot-calendar-heading"><button type="button" id="slotMonthPrev" aria-label="Previous month">‹</button><strong id="slotMonthLabel">Loading calendar…</strong><button type="button" id="slotMonthNext" aria-label="Next month">›</button></div>
            <div class="slot-month-grid slot-weekdays"><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span><span>Sun</span></div>
            <div class="slot-month-grid" id="slotMonthGrid" aria-live="polite"></div>
            <p class="slot-calendar-legend"><span class="legend-open"></span> Published slots <span class="legend-empty"></span> No slots published</p>
        </div>

        <div class="slot-manager-body">
            <!-- Left: Open Window / Slot Generator -->
            <div class="slot-generator-box slot-manager-sidebar">
                <h3><i class="fas fa-plus-circle" aria-hidden="true"></i> Create Time Slots</h3>
                <form method="POST" action="admin.php?office=<?php echo htmlspecialchars($selected_office); ?>" class="slot-gen-form" id="slotGenForm">
                    <input type="hidden" name="action" value="generate_slots">
                    <input type="hidden" name="slot_office" value="<?php echo htmlspecialchars($slot_manage_office); ?>">

                    <!-- Date Picker Row -->
                    <div class="slot-form-row">
                        <div class="slot-form-col slot-form-col-wide">
                            <label for="gen_slot_date"><i class="fas fa-calendar-day" style="color:#0284c7;"></i> Slot Date:</label>
                            <input type="date" id="gen_slot_date" name="slot_date"
                                value="<?php echo htmlspecialchars($slot_manage_date); ?>"
                                min="<?php echo date('Y-m-d'); ?>"
                                required>
                        </div>
                    </div>

                    <div class="slot-form-row">
                        <div class="slot-form-col">
                            <label for="gen_start_time">Start Time:</label>
                            <input type="time" id="gen_start_time" name="start_time" min="08:00" max="16:00" step="900" value="08:00" required>
                        </div>
                        <div class="slot-form-col">
                            <label for="gen_end_time">End Time:</label>
                            <input type="time" id="gen_end_time" name="end_time" min="08:15" max="16:00" step="900" value="16:00" required>
                        </div>
                    </div>

                    <div class="slot-form-row">
                        <div class="slot-form-col">
                            <label for="gen_interval">Slot Interval:</label>
                            <select id="gen_interval" name="interval_min">
                                <option value="15">15 Minutes</option>
                                <option value="20">20 Minutes</option>
                                <option value="30" selected>30 Minutes</option>
                                <option value="60">1 Hour</option>
                            </select>
                        </div>
                        <div class="slot-form-col">
                            <label for="gen_capacity">Max Students / Slot:</label>
                            <input type="number" id="gen_capacity" name="max_capacity" value="3" min="1" max="20" required>
                        </div>
                    </div>

                    <button type="submit" class="btn-publish-slots">
                        <i class="fas fa-bolt"></i> Publish Slots to Student Portal
                    </button>
                    <small class="slot-hours-note">Office hours: 8:00 AM–4:00 PM. Lunch break: 11:45 AM–1:00 PM; no slots are created during or across this break.</small>
                </form>

                <div class="slot-danger-panel" aria-labelledby="slotDangerHeading">
                    <h4 id="slotDangerHeading"><i class="fas fa-triangle-exclamation" aria-hidden="true"></i> Bulk slot actions</h4>
                    <p>These actions affect every published slot on <?php echo htmlspecialchars(safe_format_date($slot_manage_date, 'M d, Y')); ?>.</p>
                    <form method="POST" action="admin.php?office=<?php echo htmlspecialchars($selected_office); ?>&slot_date=<?php echo htmlspecialchars($slot_manage_date); ?>" class="closeAllSlotsForm">
                        <input type="hidden" name="action" value="close_date_slots">
                        <input type="hidden" name="slot_office" value="<?php echo htmlspecialchars($slot_manage_office); ?>">
                        <input type="hidden" name="slot_date" value="<?php echo htmlspecialchars($slot_manage_date); ?>">
                        <button type="submit" class="btn-blackout-day">
                            <i class="fas fa-ban"></i> Close All for <?php echo safe_format_date($slot_manage_date, 'M d'); ?>
                        </button>
                    </form>

                    <form method="POST" action="admin.php?office=<?php echo htmlspecialchars($selected_office); ?>&slot_date=<?php echo htmlspecialchars($slot_manage_date); ?>" id="deleteAllSlotsForm">
                        <input type="hidden" name="action" value="delete_all_slots">
                        <input type="hidden" name="slot_office" value="<?php echo htmlspecialchars($slot_manage_office); ?>">
                        <input type="hidden" name="slot_date" value="<?php echo htmlspecialchars($slot_manage_date); ?>">
                        <button type="button" class="btn-delete-all-slots" id="deleteAllSlotsBtn"
                            data-date="<?php echo htmlspecialchars(safe_format_date($slot_manage_date, 'M d, Y')); ?>">
                            <i class="fas fa-trash-alt"></i> Delete Unbooked Slots for <?php echo safe_format_date($slot_manage_date, 'M d'); ?>
                        </button>
                    </form>
                </div>
            </div>

            <!-- Right: Live Active Slots Grid for the Selected Date -->
            <div class="slot-display-box slot-manager-published">
                <div class="slot-display-header">
                    <h3>
                        Published Slots for <strong><?php echo safe_format_date($slot_manage_date, 'l, F j, Y'); ?></strong>
                        <span class="badge-slot-count"><?php echo ($slots_list_q ? mysqli_num_rows($slots_list_q) : 0); ?> slots</span>
                    </h3>
                </div>

                <div class="slots-chips-scroll">
                    <div class="slots-chips-grid">
                    <?php if ($slots_list_q && mysqli_num_rows($slots_list_q) > 0): ?>
                        <?php while ($s_item = mysqli_fetch_assoc($slots_list_q)): ?>
                            <?php
                                $s_id = (int)$s_item['id'];
                                $s_status = $s_item['status'];
                                $s_max = (int)$s_item['max_capacity'];
                                $s_booked = (int)$s_item['booked_count'];
                                $s_remaining = max(0, $s_max - $s_booked);
                                $is_s_open = ($s_status === 'open');
                                $fill_percent = $s_max > 0 ? min(100, (int)round(($s_booked / $s_max) * 100)) : 0;

                                $chip_cls = 'slot-chip-open';
                                if ($s_status === 'closed') {
                                    $chip_cls = 'slot-chip-closed';
                                } elseif ($s_remaining === 0) {
                                    $chip_cls = 'slot-chip-full';
                                }
                            ?>
                            <article class="slot-chip w-full <?php echo $chip_cls; ?>">
                                <div class="slot-chip-top">
                                    <span class="slot-chip-time">
                                        <i class="fas fa-clock"></i> <?php echo date('h:i A', strtotime($s_item['start_time'])); ?> - <?php echo date('h:i A', strtotime($s_item['end_time'])); ?>
                                    </span>
                                    <span class="slot-chip-badge">
                                        <?php if ($s_status === 'closed'): ?>
                                            CLOSED
                                        <?php elseif ($s_remaining === 0): ?>
                                            FULL
                                        <?php else: ?>
                                            OPEN
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <div class="slot-chip-meta">
                                    <span>Capacity <strong><?php echo $s_booked; ?>/<?php echo $s_max; ?></strong> · <?php echo $s_remaining; ?> left</span>
                                    <div class="slot-capacity-track" role="progressbar" aria-label="Slot capacity" aria-valuemin="0" aria-valuemax="<?php echo $s_max; ?>" aria-valuenow="<?php echo min($s_booked, $s_max); ?>" aria-valuetext="<?php echo $s_booked . ' of ' . $s_max . ' places booked'; ?>">
                                        <span style="width: <?php echo $fill_percent; ?>%;"></span>
                                    </div>
                                </div>
                                <div class="slot-chip-actions">
                                    <form method="POST" action="admin.php?office=<?php echo htmlspecialchars($selected_office); ?>&slot_date=<?php echo htmlspecialchars($slot_manage_date); ?>" style="display:inline;">
                                        <input type="hidden" name="action" value="toggle_slot">
                                        <input type="hidden" name="slot_id" value="<?php echo $s_id; ?>">
                                        <input type="hidden" name="new_status" value="<?php echo ($s_status === 'open') ? 'closed' : 'open'; ?>">
                                        <button type="submit" class="btn-slot-toggle" role="switch" aria-checked="<?php echo $is_s_open ? 'true' : 'false'; ?>" title="<?php echo $is_s_open ? 'Close this slot' : 'Open this slot'; ?>">
                                            <span class="slot-toggle-track" aria-hidden="true"><span></span></span>
                                            <span><?php echo $is_s_open ? 'Active' : 'Inactive'; ?></span>
                                        </button>
                                    </form>
                                    <form method="POST" action="admin.php?office=<?php echo htmlspecialchars($selected_office); ?>&slot_date=<?php echo htmlspecialchars($slot_manage_date); ?>" style="display:inline;" data-confirm="Delete this slot?">
                                        <input type="hidden" name="action" value="delete_slot">
                                        <input type="hidden" name="slot_id" value="<?php echo $s_id; ?>">
                                        <button type="submit" class="btn-slot-delete" title="Delete Slot">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </form>
                                </div>
                                </article>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="empty-slots-banner slot-grid-empty">
                            <i class="fas fa-calendar-times" style="font-size: 2rem; color: var(--muted); margin-bottom: 8px;"></i>
                            <p>No published slots for <?php echo safe_format_date($slot_manage_date, 'M d, Y'); ?>.</p>
                            <span style="font-size: 0.8rem; color: var(--muted);">Use the generator on the left to publish open windows for students.</span>
                        </div>
                    <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <!-- PENDING APPOINTMENT REQUESTS -->
    <section class="table-card dashboard-tab-content appointment-requests-content" id="pending-queue" aria-labelledby="pendingRequestsHeading">
        <div class="queue-review-heading">
            <div>
                <p class="queue-section-kicker">Appointment requests</p>
                <h2 id="pendingRequestsHeading"><i class="fas fa-clipboard-check" aria-hidden="true"></i> Review pending requests</h2>
                <p>Review appointment requests and record a decision. Queue controls are available in Live Queue Operations.</p>
            </div>
            <span class="badge-slot-count" id="pendingRequestCount" aria-live="polite"><?php echo ($result ? mysqli_num_rows($result) : 0); ?> pending</span>
        </div>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Queue #</th>
                        <th>Student ID</th>
                        <th>Student Name</th>
                        <th>Office</th>
                        <th>Service</th>
                        <th>Booked Schedule</th>
                        <th>Queue State</th>
                        <th style="min-width: 150px;">Decision</th>
                    </tr>
                </thead>
                <tbody id="pendingAppointmentsTable">
                    <?php if ($result && mysqli_num_rows($result) > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($result)): ?>
                            <?php
                                $q_num = !empty($row['queue_number']) ? $row['queue_number'] : ('#' . $row['id']);
                                $is_serving = ($row['queue_status'] === 'Serving');
                                $student_display_name = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
                                if (empty($student_display_name)) $student_display_name = 'Student #' . $row['student_id'];
                            ?>
                            <tr class="<?php echo $is_serving ? 'row-serving' : ''; ?>" data-appointment-id="<?php echo (int)$row['id']; ?>">
                                <td>
                                    <span class="badge-queue">
                                        <?php echo htmlspecialchars($q_num); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($row['student_id']); ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($student_display_name); ?></strong>
                                    <?php if (!empty($row['phone'])): ?>
                                        <span style="font-size: 0.8rem; color: var(--muted); margin-left: 4px;">&bull; <?php echo htmlspecialchars($row['phone']); ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($row['education_level'])): ?>
                                        <div style="font-size: 0.8rem; color: var(--muted); margin-top: 3px;">
                                            <?php if ($row['education_level'] === 'SHS'): ?>
                                                <?php echo htmlspecialchars(implode(' · ', array_filter([$row['strand'] ?? '', !empty($row['grade_level']) ? 'Grade ' . $row['grade_level'] : '', $row['section'] ?? '']))); ?>
                                            <?php else: ?>
                                                <?php echo htmlspecialchars(implode(' · ', array_filter([$row['program'] ?? '', !empty($row['college_year_level']) ? $row['college_year_level'] . ' Year' : '', $row['section'] ?? '']))); ?>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><span style="text-transform: capitalize; font-weight: 600;"><?php echo htmlspecialchars($row['office']); ?></span></td>
                                <td><?php echo htmlspecialchars($row['service']); ?></td>
                                <td style="white-space: nowrap;">
                                    <span style="font-size: 0.85rem; font-weight: 600;">
                                        <i class="fas fa-calendar-alt" style="color: #0284c7;"></i> <?php echo htmlspecialchars(safe_format_date($row['appointment_date'], 'M d, Y')); ?>
                                    </span>
                                    <span style="font-size: 0.8rem; color: var(--muted); margin-left: 4px;">
                                        &bull; <i class="fas fa-clock" style="color: #0284c7;"></i> <?php echo htmlspecialchars(safe_format_time($row['appointment_time'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($is_serving): ?>
                                        <span class="badge badge-serving"><i class="fas fa-bullhorn"></i> SERVING</span>
                                    <?php else: ?>
                                        <span class="badge badge-waiting">Waiting</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form method="POST" action="admin.php?office=<?php echo htmlspecialchars($selected_office); ?>" class="schedule-control-form">
                                        <input type="hidden" name="appointment_id" value="<?php echo htmlspecialchars($row['id']); ?>">
                                        <div class="appointment-decision-controls">
                                            <input type="hidden" name="status" value="Pending">
                                            <input type="hidden" name="reason" value="" disabled>
                                            <noscript>
                                                <label>Rejection reason
                                                    <textarea name="reason" required maxlength="1000"></textarea>
                                                </label>
                                            </noscript>
                                            <div class="appointment-decision-buttons">
                                                <button type="submit" name="decision" value="Approved" class="btn-save-schedule btn-approve" title="Approve this appointment">
                                                    <i class="fas fa-check"></i> Approve
                                                </button>
                                                <button type="submit" name="decision" value="Rejected" class="btn-save-schedule btn-reject" title="Reject this appointment">
                                                    <i class="fas fa-times"></i> Reject
                                                </button>
                                            </div>
                                            <input type="text" name="registrar_notes" maxlength="2000" placeholder="Registrar notes (optional)" title="Notes kept in the processed record">
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr id="emptyPendingRequestsRow">
                            <td colspan="8" class="empty-row">No pending appointment requests found for <?php echo ucfirst($selected_office); ?> office.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- PAST / PROCESSED APPOINTMENT RECORDS -->
    <main class="table-card history-card" id="past-appointments">
        <div class="history-header-row">
            <div>
                <h2>Processed Records & History (<?php echo ucfirst($selected_office); ?>)</h2>
                <p style="font-size: 0.85rem; color: var(--muted);">Archive of Approved, Rejected, and Cancelled appointment records (Read-Only)</p>
            </div>
            <div class="history-toolbar">
                <label class="group-by-control" for="historyGroupBy">
                    <span>Group by</span>
                    <select id="historyGroupBy">
                        <option value="date">Date</option>
                        <option value="month">Month</option>
                        <option value="year">Year</option>
                    </select>
                </label>
                <label class="history-search-box" for="pastSearch">
                    <i class="fas fa-search" aria-hidden="true"></i>
                    <span class="sr-only">Search past appointments</span>
                    <input type="search" id="pastSearch" placeholder="Search student, service, or queue #...">
                </label>
                <a href="admin.php?office=<?php echo htmlspecialchars($selected_office); ?>&export=csv" class="export-button">
                    <i class="fas fa-file-csv" aria-hidden="true"></i> Export CSV
                </a>
            </div>
        </div>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Queue #</th>
                        <th>Student ID</th>
                        <th>Student Name</th>
                        <th>Office</th>
                        <th>Service</th>
                        <th>Completed Schedule</th>
                        <th>Queue State</th>
                        <th>Final Status</th>
                        <th>Rejection Reason / Registrar Notes</th>
                    </tr>
                </thead>
                <tbody id="pastAppointmentsTable">
                    <?php if ($pastResult && mysqli_num_rows($pastResult) > 0): ?>
                        <?php $historyYear = ''; $historyMonth = ''; $historyDate = ''; ?>
                        <?php while ($pastRow = mysqli_fetch_assoc($pastResult)): ?>
                            <?php
                                $rowDate = $pastRow['appointment_date'];
                                $ts = strtotime($rowDate);
                                $rowYear = ($ts !== false) ? date('Y', $ts) : 'Year';
                                $rowMonth = ($ts !== false) ? date('Y-m', $ts) : 'Month';
                                $rowMonthName = ($ts !== false) ? date('F Y', $ts) : 'Month';
                                $rowDateFormatted = safe_format_date($rowDate, 'l, F j, Y');

                                if ($rowYear !== $historyYear) {
                                    $historyYear = $rowYear;
                                    $historyMonth = '';
                                    $historyDate = '';
                            ?>
                                <tr class="group-header year-group" data-group="year"><td colspan="9"><?php echo htmlspecialchars($rowYear); ?></td></tr>
                            <?php } ?>
                            <?php if ($rowMonth !== $historyMonth) {
                                    $historyMonth = $rowMonth;
                                    $historyDate = '';
                            ?>
                                <tr class="group-header month-group" data-group="month"><td colspan="9"><?php echo htmlspecialchars($rowMonthName); ?></td></tr>
                            <?php } ?>
                            <?php if ($rowDate !== $historyDate) { $historyDate = $rowDate; ?>
                                <tr class="group-header date-group" data-group="date"><td colspan="9"><i class="fas fa-calendar-day"></i> <?php echo htmlspecialchars($rowDateFormatted); ?></td></tr>
                            <?php } ?>
                            <?php
                                $status_val = $pastRow['status'];
                                $badge_cls = 'badge-rejected';
                                if ($status_val === 'Approved') {
                                    $badge_cls = 'badge-approved';
                                } elseif ($status_val === 'Cancelled') {
                                    $badge_cls = 'badge-cancelled';
                                }
                                $q_num = !empty($pastRow['queue_number']) ? $pastRow['queue_number'] : ('#' . $pastRow['id']);
                                $past_display_name = trim(($pastRow['first_name'] ?? '') . ' ' . ($pastRow['last_name'] ?? ''));
                                if (empty($past_display_name)) $past_display_name = 'Student #' . $pastRow['student_id'];
                                $queue_state = !empty($pastRow['queue_status']) ? $pastRow['queue_status'] : 'Completed';
                            ?>
                            <tr class="data-row">
                                <td>
                                    <span class="badge-queue">
                                        <?php echo htmlspecialchars($q_num); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($pastRow['student_id']); ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($past_display_name); ?></strong>
                                    <?php if (!empty($pastRow['phone'])): ?>
                                        <div style="font-size: 0.75rem; color: var(--muted);"><i class="fas fa-phone"></i> <?php echo htmlspecialchars($pastRow['phone']); ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($pastRow['education_level'])): ?>
                                        <div style="font-size: 0.75rem; color: var(--muted); margin-top: 3px;">
                                            <?php if ($pastRow['education_level'] === 'SHS'): ?>
                                                <?php echo htmlspecialchars(implode(' · ', array_filter([$pastRow['strand'] ?? '', !empty($pastRow['grade_level']) ? 'Grade ' . $pastRow['grade_level'] : '', $pastRow['section'] ?? '']))); ?>
                                            <?php else: ?>
                                                <?php echo htmlspecialchars(implode(' · ', array_filter([$pastRow['program'] ?? '', !empty($pastRow['college_year_level']) ? $pastRow['college_year_level'] . ' Year' : '', $pastRow['section'] ?? '']))); ?>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><span style="text-transform: capitalize; font-weight: 600;"><?php echo htmlspecialchars($pastRow['office']); ?></span></td>
                                <td><?php echo htmlspecialchars($pastRow['service']); ?></td>
                                <td>
                                    <div style="font-size: 0.85rem; font-weight: 600;">
                                        <i class="fas fa-calendar-alt" style="color: #0284c7;"></i> <?php echo htmlspecialchars(safe_format_date($pastRow['appointment_date'], 'M d, Y')); ?>
                                    </div>
                                    <div style="font-size: 0.78rem; color: var(--muted); margin-top: 2px;">
                                        <i class="fas fa-clock"></i> <?php echo htmlspecialchars(safe_format_time($pastRow['appointment_time'])); ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge <?php echo ($queue_state === 'Serving') ? 'badge-serving' : (($queue_state === 'Waiting') ? 'badge-waiting' : 'badge-completed'); ?>">
                                        <?php echo htmlspecialchars($queue_state); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge <?php echo $badge_cls; ?>">
                                        <?php if ($status_val === 'Approved'): ?>
                                            <i class="fas fa-check-circle"></i>
                                        <?php elseif ($status_val === 'Cancelled'): ?>
                                            <i class="fas fa-ban"></i>
                                        <?php else: ?>
                                            <i class="fas fa-times-circle"></i>
                                        <?php endif; ?>
                                        <?php echo htmlspecialchars($status_val); ?>
                                    </span>
                                </td>
                                <td style="max-width: 220px; font-size: 0.8rem;">
                                    <?php if (!empty($pastRow['rejection_reason'])): ?>
                                        <strong>Reason:</strong> <?php echo htmlspecialchars($pastRow['rejection_reason']); ?>
                                        <?php if (!empty($pastRow['registrar_notes'])): ?><br><?php endif; ?>
                                    <?php endif; ?>
                                    <?php if (!empty($pastRow['registrar_notes'])): ?>
                                        <strong>Notes:</strong> <?php echo htmlspecialchars($pastRow['registrar_notes']); ?>
                                    <?php endif; ?>
                                    <?php if (empty($pastRow['rejection_reason']) && empty($pastRow['registrar_notes'])): ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="empty-row">No past appointment records found for <?php echo ucfirst($selected_office); ?> office.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

    <dialog class="decision-reason-dialog" id="decisionReasonDialog" aria-labelledby="decisionReasonTitle">
        <form method="dialog" id="decisionReasonForm">
            <h2 id="decisionReasonTitle">Reason for rejection</h2>
            <p>This reason will be saved and shown to the student with the appointment status.</p>
            <label for="decisionReasonInput">Rejection reason</label>
            <textarea id="decisionReasonInput" maxlength="1000" required rows="4" placeholder="Explain why this appointment is being rejected."></textarea>
            <div class="decision-reason-actions">
                <button type="submit" value="cancel" class="btn-cancel-decision">Cancel</button>
                <button type="submit" value="continue" class="btn-confirm-decision">Continue</button>
            </div>
        </form>
    </dialog>

    <!-- Feedback Popup Logic -->
    <?php if (!empty($popup_message)): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                AppPopup.show(<?php echo json_encode($popup_message); ?>, <?php echo json_encode($popup_type); ?>);
            });
        </script>
    <?php endif; ?>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Theme Toggle Logic
            const themeToggle = document.getElementById('themeToggle');
            if (themeToggle) {
                const savedTheme = localStorage.getItem('theme') || 'light';
                document.body.setAttribute('data-theme', savedTheme);
                const icon = themeToggle.querySelector('i');
                if (icon) {
                    icon.classList.toggle('fa-sun', savedTheme === 'dark');
                    icon.classList.toggle('fa-moon', savedTheme !== 'dark');
                }

                themeToggle.addEventListener('click', () => {
                    const currentTheme = document.body.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
                    document.body.setAttribute('data-theme', currentTheme);
                    localStorage.setItem('theme', currentTheme);
                    if (icon) {
                        icon.classList.toggle('fa-sun', currentTheme === 'dark');
                        icon.classList.toggle('fa-moon', currentTheme !== 'dark');
                    }
                });
            }

            // Profile Modal Handler
            const profileTrigger = document.getElementById('profileTrigger');
            const profileModal = document.getElementById('profileModal');
            const closeProfile = document.getElementById('closeProfile');

            if (profileTrigger && profileModal && closeProfile) {
                profileTrigger.addEventListener('click', () => {
                    profileModal.classList.add('show');
                    profileModal.setAttribute('aria-hidden', 'false');
                });
                closeProfile.addEventListener('click', () => {
                    profileModal.classList.remove('show');
                    profileModal.setAttribute('aria-hidden', 'true');
                });
                profileModal.addEventListener('click', (e) => {
                    if (e.target === profileModal) profileModal.classList.remove('show');
                });
            }

            // Staff Onboarding Modal Handler
            const openStaffModal = document.getElementById('openStaffModal');
            const staffModal = document.getElementById('staffModal');
            const closeStaffModal = document.getElementById('closeStaffModal');

            if (openStaffModal && staffModal && closeStaffModal) {
                openStaffModal.addEventListener('click', () => {
                    staffModal.classList.add('show');
                    staffModal.setAttribute('aria-hidden', 'false');
                });
                closeStaffModal.addEventListener('click', () => {
                    staffModal.classList.remove('show');
                    staffModal.setAttribute('aria-hidden', 'true');
                });
                staffModal.addEventListener('click', (e) => {
                    if (e.target === staffModal) staffModal.classList.remove('show');
                });
            }

            // Defensive Table Grouping Handler (no 'Invalid Date' errors)
            function setupGrouping(selectId, tableSelector) {
                const groupSelect = document.getElementById(selectId);
                const table = document.querySelector(tableSelector);
                if (!groupSelect || !table) return;

                groupSelect.addEventListener('change', function () {
                    table.querySelectorAll('.group-header').forEach(function (header) {
                        header.hidden = header.dataset.group !== groupSelect.value;
                    });
                });

                groupSelect.dispatchEvent(new Event('change'));
            }

            setupGrouping('historyGroupBy', '#pastAppointmentsTable');

            // Table Search Handler
            const searchInput = document.getElementById('pastSearch');
            const rows = Array.from(document.querySelectorAll('#pastAppointmentsTable .data-row'));

            if (searchInput) {
                searchInput.addEventListener('input', function () {
                    const keyword = this.value.trim().toLowerCase();
                    rows.forEach(function (row) {
                        row.style.display = row.textContent.toLowerCase().includes(keyword) ? '' : 'none';
                    });
                });
            }

            // ================================================================
            // Real-Time Dashboard Polling (Feature 3)
            // ================================================================
            const refreshBtn = document.getElementById('manualRefreshBtn');
            const refreshIcon = document.getElementById('refreshIcon');
            const waitingCountEl = document.getElementById('waitingCountVal');
            const servingCountEl = document.getElementById('servingCountVal');

            const pendingTable = document.getElementById('pendingAppointmentsTable');
            const pendingCountBadge = document.getElementById('pendingRequestCount');
            const pendingTab = document.getElementById('appointmentRequestsTab');
            const pendingTabBadge = document.getElementById('pendingTabBadge');
            let pendingPollInProgress = false;
            let knownPendingIds = new Set(
                Array.from(document.querySelectorAll('#pendingAppointmentsTable tr[data-appointment-id]'))
                    .map(row => String(row.dataset.appointmentId))
            );
            let lastPendingCount = knownPendingIds.size;

            function updatePendingBadges(count) {
                if (pendingCountBadge) pendingCountBadge.textContent = count + ' pending';
                if (pendingTabBadge) {
                    pendingTabBadge.textContent = String(count);
                    pendingTabBadge.setAttribute('aria-label', count + ' pending requests');
                    pendingTabBadge.hidden = count === 0;
                }
            }

            function appendCell(row, text, className) {
                const cell = document.createElement('td');
                if (className) cell.className = className;
                cell.textContent = text;
                row.appendChild(cell);
                return cell;
            }

            function formatRequestDate(value) {
                const match = String(value || '').match(/^(\d{4})-(\d{2})-(\d{2})/);
                if (!match) return String(value || '');
                return new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]))
                    .toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
            }

            function formatRequestTime(value) {
                const match = String(value || '').match(/^(\d{1,2}):(\d{2})/);
                if (!match) return String(value || '');
                const hour = Number(match[1]);
                return ((hour + 11) % 12 + 1) + ':' + match[2] + (hour >= 12 ? ' PM' : ' AM');
            }

            function createPendingRow(appointment) {
                const id = String(appointment.id);
                const row = document.createElement('tr');
                row.dataset.appointmentId = id;
                if (appointment.queue_status === 'Serving') row.classList.add('row-serving');

                const queueNumber = appointment.queue_number || ('#' + id);
                const queueCell = document.createElement('td');
                const queueBadge = document.createElement('span');
                queueBadge.className = 'badge-queue';
                queueBadge.textContent = queueNumber;
                queueCell.appendChild(queueBadge);
                row.appendChild(queueCell);

                appendCell(row, String(appointment.student_id || ''));
                const nameCell = document.createElement('td');
                const studentName = [appointment.first_name, appointment.last_name].filter(Boolean).join(' ').trim()
                    || ('Student #' + String(appointment.student_id || ''));
                const name = document.createElement('strong');
                name.textContent = studentName;
                nameCell.appendChild(name);
                if (appointment.phone) {
                    const phone = document.createElement('div');
                    phone.className = 'pending-student-meta';
                    phone.textContent = appointment.phone;
                    nameCell.appendChild(phone);
                }
                if (appointment.education_level) {
                    const academic = [
                        appointment.education_level === 'SHS' ? appointment.strand : appointment.program,
                        appointment.education_level === 'SHS'
                            ? (appointment.grade_level ? 'Grade ' + appointment.grade_level : '')
                            : (appointment.college_year_level ? appointment.college_year_level + ' Year' : ''),
                        appointment.section
                    ].filter(Boolean);
                    if (academic.length) {
                        const academicDetails = document.createElement('div');
                        academicDetails.className = 'pending-student-meta';
                        academicDetails.textContent = academic.join(' · ');
                        nameCell.appendChild(academicDetails);
                    }
                }
                row.appendChild(nameCell);

                const officeCell = document.createElement('td');
                const office = document.createElement('span');
                office.style.textTransform = 'capitalize';
                office.style.fontWeight = '600';
                office.textContent = String(appointment.office || '');
                officeCell.appendChild(office);
                row.appendChild(officeCell);
                appendCell(row, String(appointment.service || ''));
                appendCell(row, formatRequestDate(appointment.appointment_date) + ' · ' + formatRequestTime(appointment.appointment_time));
                appendCell(row, appointment.queue_status === 'Serving' ? 'Serving' : 'Waiting');

                const decisionCell = document.createElement('td');
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'admin.php?office=registrar';
                form.className = 'schedule-control-form';
                const appointmentId = document.createElement('input');
                appointmentId.type = 'hidden';
                appointmentId.name = 'appointment_id';
                appointmentId.value = id;
                form.appendChild(appointmentId);
                const status = document.createElement('input');
                status.type = 'hidden';
                status.name = 'status';
                status.value = 'Pending';
                form.appendChild(status);
                const reason = document.createElement('input');
                reason.type = 'hidden';
                reason.name = 'reason';
                reason.value = '';
                reason.disabled = true;
                form.appendChild(reason);
                const controls = document.createElement('div');
                controls.className = 'appointment-decision-controls';
                const buttons = document.createElement('div');
                buttons.className = 'appointment-decision-buttons';
                [['Approved', 'Approve', 'btn-approve'], ['Rejected', 'Reject', 'btn-reject']].forEach(function (item) {
                    const button = document.createElement('button');
                    button.type = 'submit';
                    button.name = 'decision';
                    button.value = item[0];
                    button.className = 'btn-save-schedule ' + item[2];
                    button.textContent = item[1];
                    buttons.appendChild(button);
                });
                controls.appendChild(buttons);
                const notes = document.createElement('input');
                notes.type = 'text';
                notes.name = 'registrar_notes';
                notes.maxLength = 2000;
                notes.placeholder = 'Registrar notes (optional)';
                notes.title = 'Notes kept in the processed record';
                controls.appendChild(notes);
                form.appendChild(controls);
                decisionCell.appendChild(form);
                row.appendChild(decisionCell);
                bindAppointmentDecisionForm(form);
                return row;
            }

            function playAppointmentChime() {
                try {
                    const AudioContextClass = window.AudioContext || window.webkitAudioContext;
                    if (!AudioContextClass) return;
                    const audio = new AudioContextClass();
                    const playTone = function () {
                        const oscillator = audio.createOscillator();
                        const gain = audio.createGain();
                        oscillator.frequency.value = 740;
                        gain.gain.setValueAtTime(0.035, audio.currentTime);
                        gain.gain.exponentialRampToValueAtTime(0.001, audio.currentTime + 0.24);
                        oscillator.connect(gain);
                        gain.connect(audio.destination);
                        oscillator.addEventListener('ended', function () { audio.close(); }, { once: true });
                        oscillator.start();
                        oscillator.stop(audio.currentTime + 0.24);
                    };
                    if (audio.state === 'suspended') {
                        audio.resume().then(playTone).catch(function () { audio.close(); });
                    } else {
                        playTone();
                    }
                } catch (error) {
                    console.info('Notification sound is unavailable in this browser context.');
                }
            }

            async function pollDashboard() {
                if (pendingPollInProgress) return;
                pendingPollInProgress = true;
                try {
                    const response = await fetch('api_admin_stream.php?office=registrar', {
                        cache: 'no-store',
                        headers: { Accept: 'application/json' }
                    });
                    const data = await response.json();
                    if (!response.ok || !data.success) {
                        throw new Error(data.error || 'Unable to refresh pending appointment requests.');
                    }
                    if (waitingCountEl) waitingCountEl.textContent = String(data.waiting_count);
                    if (servingCountEl) servingCountEl.textContent = String(data.serving_count);

                    const appointments = Array.isArray(data.pending_appointments) ? data.pending_appointments : [];
                    const activeIds = new Set(appointments.map(item => String(item.id)));
                    const newAppointments = appointments.filter(item => !knownPendingIds.has(String(item.id)));
                    const pendingEmptyRow = document.getElementById('emptyPendingRequestsRow');
                    if (pendingEmptyRow && appointments.length) pendingEmptyRow.remove();

                    appointments.forEach(function (appointment) {
                        const id = String(appointment.id);
                        if (!pendingTable.querySelector('tr[data-appointment-id="' + id + '"]')) {
                            pendingTable.prepend(createPendingRow(appointment));
                        }
                    });
                    pendingTable.querySelectorAll('tr[data-appointment-id]').forEach(function (row) {
                        if (!activeIds.has(String(row.dataset.appointmentId))) row.remove();
                    });
                    if (!appointments.length && !document.getElementById('emptyPendingRequestsRow')) {
                        const emptyRow = document.createElement('tr');
                        emptyRow.id = 'emptyPendingRequestsRow';
                        const emptyCell = document.createElement('td');
                        emptyCell.colSpan = 8;
                        emptyCell.className = 'empty-row';
                        emptyCell.textContent = 'No pending appointment requests found for Registrar office.';
                        emptyRow.appendChild(emptyCell);
                        pendingTable.appendChild(emptyRow);
                    }

                    updatePendingBadges(Number(data.pending_count) || appointments.length);
                    if (newAppointments.length || appointments.length > lastPendingCount) {
                        pendingTab.classList.add('has-new-request');
                        window.setTimeout(() => pendingTab.classList.remove('has-new-request'), 12000);
                        playAppointmentChime();
                        const newest = newAppointments[0];
                        if (newest && window.AppPopup) {
                            const studentName = [newest.first_name, newest.last_name].filter(Boolean).join(' ')
                                || ('Student #' + String(newest.student_id || ''));
                            AppPopup.show('New appointment request from ' + studentName + '.', 'info');
                        }
                    }
                    knownPendingIds = activeIds;
                    lastPendingCount = appointments.length;
                } catch (error) {
                    console.error('Registrar appointment polling failed:', error);
                } finally {
                    pendingPollInProgress = false;
                }
            }

            if (pendingTab) pendingTab.addEventListener('click', function () {
                pendingTab.classList.remove('has-new-request');
            });

            if (refreshBtn) {
                refreshBtn.addEventListener('click', function () {
                    // An explicit refresh rehydrates both queues from MySQL, including history.
                    window.location.reload();
                });
            }

            pollDashboard();
            window.setInterval(pollDashboard, 7000);

            const reasonDialog = document.getElementById('decisionReasonDialog');
            const reasonDialogForm = document.getElementById('decisionReasonForm');
            const reasonInput = document.getElementById('decisionReasonInput');
            function askForRejectionReason() {
                return new Promise(function (resolve) {
                    const handleClose = function () {
                        reasonDialog.removeEventListener('close', handleClose);
                        const reason = reasonDialog.returnValue === 'continue' ? reasonInput.value.trim() : '';
                        reasonInput.value = '';
                        resolve(reason);
                    };
                    reasonDialog.addEventListener('close', handleClose);
                    reasonDialog.showModal();
                    reasonInput.focus();
                });
            }

            reasonDialogForm.addEventListener('submit', function (event) {
                if (event.submitter && event.submitter.value === 'continue' && !reasonInput.value.trim()) {
                    event.preventDefault();
                    reasonInput.setCustomValidity('Enter a reason before continuing.');
                    reasonInput.reportValidity();
                } else {
                    reasonInput.setCustomValidity('');
                }
            });
            reasonInput.addEventListener('input', function () {
                reasonInput.setCustomValidity('');
            });

            async function saveAppointmentDecision(form, decision, reason) {
                const button = form.querySelector('button[value="' + decision + '"]');
                if (button) button.disabled = true;
                try {
                    const formData = new FormData(form);
                    formData.set('status', decision);
                    formData.set('reason', reason || '');
                    const response = await fetch('api_appointment_update.php', { method: 'POST', body: formData });
                    const data = await response.json();
                    if (!response.ok || !data.success) throw new Error(data.message || 'Unable to update appointment.');
                    if (window.AppPopup) AppPopup.show(data.payload.message, 'success');

                    const pendingRow = form.closest('tr');
                    const historyTable = document.getElementById('pastAppointmentsTable');
                    if (pendingRow && historyTable && data.payload.status !== 'Pending') {
                        const sourceCells = pendingRow.querySelectorAll(':scope > td');
                        const historyRow = document.createElement('tr');
                        historyRow.className = 'data-row';
                        for (let index = 0; index < 7; index += 1) {
                            historyRow.appendChild(sourceCells[index].cloneNode(true));
                        }
                        const statusCell = document.createElement('td');
                        const statusBadge = document.createElement('span');
                        const statusClass = decision === 'Approved' ? 'badge-approved' : 'badge-rejected';
                        statusBadge.className = 'badge ' + statusClass;
                        statusBadge.textContent = decision;
                        statusCell.appendChild(statusBadge);
                        historyRow.appendChild(statusCell);
                        const reasonCell = document.createElement('td');
                        reasonCell.textContent = data.payload.reason || '';
                        historyRow.appendChild(reasonCell);
                        historyTable.prepend(historyRow);
                        pendingRow.remove();
                    }
                    pollDashboard();
                } catch (error) {
                    if (window.AppPopup) AppPopup.show(error.message || 'Unable to update appointment.', 'error');
                } finally {
                    if (button) button.disabled = false;
                }
            }

            const boundAppointmentDecisionForms = new WeakSet();
            function bindAppointmentDecisionForm(form) {
                if (boundAppointmentDecisionForms.has(form)) return;
                boundAppointmentDecisionForms.add(form);
                const reasonField = form.querySelector('input[name="reason"]');
                if (reasonField) reasonField.disabled = false;
                form.addEventListener('submit', async function (event) {
                    event.preventDefault();
                    const decision = event.submitter && event.submitter.value;
                    if ((decision !== 'Approved' && decision !== 'Rejected') || form.dataset.actionInProgress === 'true') return;
                    form.dataset.actionInProgress = 'true';
                    try {
                        let reason = '';
                        if (decision === 'Rejected') {
                            reason = await askForRejectionReason();
                            if (!reason) return;
                        }
                        const confirmMessage = decision === 'Approved'
                            ? 'Approve this appointment request?'
                            : 'Reject this appointment request and send the reason to the student?';
                        const confirmed = window.AppPopup
                            ? await AppPopup.confirm(confirmMessage, {
                                title: decision === 'Approved' ? 'Approve appointment?' : 'Reject appointment?',
                                cancelLabel: 'Go back',
                                confirmLabel: decision
                            })
                            : window.confirm(confirmMessage);
                        if (confirmed) await saveAppointmentDecision(form, decision, reason);
                    } finally {
                        delete form.dataset.actionInProgress;
                    }
                });
            }
            document.querySelectorAll('.schedule-control-form').forEach(bindAppointmentDecisionForm);

            // ================================================================
            // Delete All Slots Confirmation (Feature 2)
            // ================================================================
            const deleteAllSlotsBtn = document.getElementById('deleteAllSlotsBtn');
            const deleteAllSlotsForm = document.getElementById('deleteAllSlotsForm');
            document.querySelectorAll('.closeAllSlotsForm').forEach(function (form) {
                form.addEventListener('submit', function (event) {
                    event.preventDefault();
                    const dateLabel = form.querySelector('input[name="slot_date"]').value;
                    const confirmClose = window.AppPopup
                        ? AppPopup.confirm(
                            `Close all published slots for ${dateLabel}? Students will not be able to book those slots.`,
                            { title: 'Close all slots?', cancelLabel: 'Keep slots open', confirmLabel: 'Close all slots' }
                        )
                        : Promise.resolve(window.confirm(`Close all published slots for ${dateLabel}?`));
                    confirmClose.then(function (confirmed) {
                        if (confirmed) form.submit();
                    });
                });
            });

            if (deleteAllSlotsBtn && deleteAllSlotsForm) {
                deleteAllSlotsBtn.addEventListener('click', function () {
                    const dateLabel = this.dataset.date || 'this date';
                    AppPopup.confirm(
                    `Delete all unbooked availability slots for ${dateLabel}? Slots with active bookings will be preserved. This cannot be undone.`,
                        {
                            title: 'Delete unbooked slots?',
                            cancelLabel: 'Keep slots',
                            confirmLabel: 'Delete unbooked slots'
                        }
                    ).then(function (confirmed) {
                        if (confirmed) {
                            deleteAllSlotsForm.submit();
                        }
                    });
                });
            }
        });
    </script>
    <script>
    if (location.hash === '#service-settings' || location.hash === '#service-availability' || location.hash === '#slot-manager') {
        setTimeout(function(){ var target = document.querySelector(location.hash === '#service-settings' ? '#service-availability' : location.hash); if (target) target.scrollIntoView({block:'start'}); }, 0);
    }
    var queueTicketSelect = document.getElementById('queueControlId');
    function updateQueueActionAvailability() {
        if (!queueTicketSelect) return;
        var selectedOption = queueTicketSelect.options[queueTicketSelect.selectedIndex];
        var selectedStatus = selectedOption ? (selectedOption.dataset.queueStatus || '').toLowerCase() : '';
        document.querySelectorAll('[data-requires-ticket]').forEach(function(button) {
            var allowedStatuses = (button.dataset.allowedStatus || '').toLowerCase().split(',');
            button.disabled = !queueTicketSelect.value || !allowedStatuses.includes(selectedStatus);
        });
    }
    if (queueTicketSelect) {
        queueTicketSelect.addEventListener('change', updateQueueActionAvailability);
        updateQueueActionAvailability();
    }
    document.querySelectorAll('[data-queue-action]').forEach(function(button){button.addEventListener('click',function(){
        var action=button.dataset.queueAction, id=document.getElementById('queueControlId').value;
        if(['call','skip','complete'].includes(action)&&!id){window.AppPopup.show('Select a queue ticket for this action.','error');return;}
        var body=new FormData();body.append('action',action);if(id)body.append('appointment_id',id);
        body.append('counter',document.getElementById('queueCounterName').value);
        fetch('api_queue.php',{method:'POST',body:body}).then(function(r){return r.json();}).then(function(data){if(!data.success)throw new Error(data.error||'Queue action failed.');window.AppPopup.show(data.message||'Queue updated.','success');setTimeout(function(){location.reload();},900);}).catch(function(error){window.AppPopup.show(error.message,'error');});
    });});
    (function(){var modal=document.getElementById('addServiceModal'),open=document.getElementById('openAddService'),close=document.getElementById('closeAddService');if(open&&modal)open.addEventListener('click',function(){modal.classList.add('show');modal.setAttribute('aria-hidden','false');document.getElementById('newServiceName').focus();});if(close&&modal)close.addEventListener('click',function(){modal.classList.remove('show');modal.setAttribute('aria-hidden','true');});if(modal)modal.addEventListener('click',function(e){if(e.target===modal){modal.classList.remove('show');modal.setAttribute('aria-hidden','true');}});})();
    document.querySelectorAll('[data-delete-service]').forEach(function(button){button.addEventListener('click',function(){
        var row=button.closest('.service-admin-row'),name=button.dataset.serviceName||'this service';
        var confirmDelete=window.AppPopup?AppPopup.confirm('Delete "'+name+'" from the student service list?',{title:'Delete service?',cancelLabel:'Keep service',confirmLabel:'Delete'}):Promise.resolve(window.confirm('Delete "'+name+'" from the student service list?'));
        confirmDelete.then(function(confirmed){if(!confirmed)return;button.disabled=true;var body=new FormData();body.append('service_id',button.dataset.deleteService);
          fetch('api_services.php?action=delete',{method:'POST',body:body,headers:{Accept:'application/json'}}).then(function(r){return r.json();}).then(function(data){if(!data.success)throw new Error(data.message||'Unable to delete service.');if(row)row.remove();if(window.AppPopup)AppPopup.show(data.message,'success');}).catch(function(error){button.disabled=false;if(window.AppPopup)AppPopup.show(error.message,'error');});
        });
    });});
    (function(){
        var calendar=document.getElementById('slotCalendar'),grid=document.getElementById('slotMonthGrid'),label=document.getElementById('slotMonthLabel');
        if(!calendar||!grid||!label)return;
        var selected=calendar.dataset.selectedDate,month=new Date(selected+'T12:00:00');month.setDate(1);
        function render(){
            var year=month.getFullYear(),m=month.getMonth(),key=year+'-'+String(m+1).padStart(2,'0');
            label.textContent=month.toLocaleDateString(undefined,{month:'long',year:'numeric'});grid.innerHTML='';
            fetch('api_slots.php?action=calendar_month&office=registrar&month='+key).then(function(r){return r.json();}).then(function(data){
                if(!data.success)throw new Error(data.error||'Unable to load calendar.');
                var offset=(new Date(year,m,1).getDay()+6)%7,total=new Date(year,m+1,0).getDate();
                for(var i=0;i<offset;i++){var blank=document.createElement('span');blank.className='slot-calendar-blank';grid.appendChild(blank);}
                for(var day=1;day<=total;day++){
                    var date=key+'-'+String(day).padStart(2,'0'),info=data.days[date]||{total_slots:0,open_slots:0,day_capacity:0,booked:0},button=document.createElement('button');
                    button.type='button';button.className='slot-calendar-day'+(Number(info.open_slots)>0?' has-open-slots':' no-open-slots')+(date===selected?' is-selected':'')+(date===new Date().toISOString().slice(0,10)?' is-today':'');
                    button.innerHTML='<span class="slot-calendar-number">'+day+'</span><small>'+(info.total_slots?info.open_slots+' open / '+info.total_slots:'No slots')+'</small>';
                    button.setAttribute('aria-label',date+', '+(info.total_slots?info.open_slots+' open slots':'no slots published'));
                    button.addEventListener('click',function(chosen){return function(){window.location.href='admin.php?office=registrar&slot_date='+chosen+'#slot-manager';};}(date));grid.appendChild(button);
                }
            }).catch(function(){label.textContent='Calendar unavailable';});
        }
        document.getElementById('slotMonthPrev').addEventListener('click',function(){month.setMonth(month.getMonth()-1);render();});
        document.getElementById('slotMonthNext').addEventListener('click',function(){month.setMonth(month.getMonth()+1);render();});render();
    })();
    (function(){
        var tabs=document.querySelectorAll('[data-dashboard-tab]');
        var panels={
            'live-queue-panel':document.querySelectorAll('.live-queue-content'),
            'appointment-requests-panel':document.querySelectorAll('.appointment-requests-content'),
            'slot-manager-panel':document.querySelectorAll('.slot-manager-content'),
            'service-settings-panel':document.querySelectorAll('.service-settings-content')
        };
        var hashByPanel={
            'live-queue-panel':'live-queue',
            'appointment-requests-panel':'appointment-requests',
            'slot-manager-panel':'slot-manager',
            'service-settings-panel':'service-settings'
        };
        var panelByHash={};
        Object.keys(hashByPanel).forEach(function(panel){panelByHash[hashByPanel[panel]]=panel;});

        function activatePanel(panelName, updateUrl){
            if(!panels[panelName]) panelName='live-queue-panel';
            Object.keys(panels).forEach(function(name){
                panels[name].forEach(function(panel){panel.classList.toggle('active', name===panelName);});
            });
            tabs.forEach(function(tab){
                var isActive=tab.dataset.dashboardTab===panelName;
                tab.classList.toggle('active',isActive);
                tab.setAttribute('aria-selected',isActive?'true':'false');
            });
            var hash=hashByPanel[panelName];
            localStorage.setItem('adminDashboardTab',hash);
            if(updateUrl && window.location.hash !== '#'+hash) history.pushState({tab:hash},'',window.location.pathname+window.location.search+'#'+hash);
        }

        function panelFromLocation(){
            var hash=window.location.hash.replace(/^#/,'');
            return panelByHash[hash] || panelByHash[localStorage.getItem('adminDashboardTab')] || 'live-queue-panel';
        }

        tabs.forEach(function(tab){tab.addEventListener('click',function(){activatePanel(tab.dataset.dashboardTab,true);});});
        window.addEventListener('popstate',function(){activatePanel(panelFromLocation(),false);});
        window.addEventListener('hashchange',function(){activatePanel(panelFromLocation(),false);});
        activatePanel(panelFromLocation(),false);

        window.serviceAudienceFilter='all';
        function applyServiceFilters(){
            var filter=window.serviceAudienceFilter||'all';
            var searchInput=document.getElementById('serviceAdminSearch');
            var query=(searchInput ? searchInput.value : '').trim().toLowerCase();
            document.querySelectorAll('.service-admin-row[data-target-level]').forEach(function(row){
                var audienceMatch=filter==='all'||row.dataset.targetLevel==='Both'||row.dataset.targetLevel===filter;
                var title=row.querySelector('.service-row-title');
                var nameMatch=!query||(title ? title.textContent : '').toLowerCase().includes(query);
                row.hidden=!(audienceMatch&&nameMatch);
            });
        }
        document.querySelectorAll('[data-service-filter]').forEach(function(button){button.addEventListener('click',function(){
            window.serviceAudienceFilter=button.dataset.serviceFilter;
            document.querySelectorAll('[data-service-filter]').forEach(function(tab){tab.classList.toggle('is-active',tab===button);});
            applyServiceFilters();
        });});
        var serviceAdminSearch=document.getElementById('serviceAdminSearch');
        if(serviceAdminSearch)serviceAdminSearch.addEventListener('input',applyServiceFilters);
    })();
    </script>
</body>
</html>
