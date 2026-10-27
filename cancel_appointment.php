<?php
session_start();
require_once("db.php");
require_once("realtime_helper.php");
$wants_json = strtolower($_SERVER['HTTP_ACCEPT'] ?? '') === 'application/json';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $student_id = $_SESSION['user_id'];
    $appointment_id = (int)($_POST['appointment_id'] ?? 0);

    if ($appointment_id > 0) {
        // Fetch appointment details and verify ownership
        $stmt = mysqli_prepare($con, "SELECT id, slot_id, office, service, appointment_date, appointment_time, status
                          FROM appointments WHERE id = ? AND student_id = ? LIMIT 1");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "is", $appointment_id, $student_id);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $app = mysqli_fetch_assoc($res);
            mysqli_stmt_close($stmt);

            if ($app) {
                if ($app['status'] === 'Cancelled') {
                    echo popup_response_script('This appointment is already cancelled.', 'error', 'homepage.php');
                    exit();
                }

                // Update appointment status to Cancelled
                $update_stmt = mysqli_prepare($con, "UPDATE appointments SET status = 'Cancelled', queue_status = 'Skipped' WHERE id = ?");
                if ($update_stmt) {
                    mysqli_stmt_bind_param($update_stmt, "i", $appointment_id);
                    mysqli_stmt_execute($update_stmt);
                    mysqli_stmt_close($update_stmt);

                    // Re-open slot if it was full
                    if (!empty($app['slot_id'])) {
                        $s_id = (int)$app['slot_id'];
                        mysqli_query($con, "UPDATE appointment_slots SET status = 'open' WHERE id = $s_id AND status = 'full'");
                    }

                    publish_realtime_event($con, 'appointment_cancelled', $appointment_id, $student_id, ['status' => 'Cancelled', 'service' => $app['service']]);
                    publish_slot_availability_event($con, $app['appointment_date']);

                    if ($wants_json) {
                        header('Content-Type: application/json; charset=utf-8');
                        echo json_encode(['success' => true, 'message' => "Appointment #{$appointment_id} has been cancelled."]);
                        exit();
                    }

                    echo popup_response_script("Appointment #{$appointment_id} for {$app['service']} has been cancelled.", 'success', 'homepage.php');
                    exit();
                } else {
                    echo popup_response_script('Failed to cancel appointment. Please try again.', 'error', 'homepage.php');
                    exit();
                }
            } else {
                echo popup_response_script('Appointment not found or unauthorized.', 'error', 'homepage.php');
                exit();
            }
        }
    } else {
        echo popup_response_script('Invalid appointment ID.', 'error', 'homepage.php');
        exit();
    }
}

header("Location: homepage.php");
exit();
?>
