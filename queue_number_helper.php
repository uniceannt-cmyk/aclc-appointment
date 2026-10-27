<?php
function generate_daily_queue_number($con, $office) {
    $office_prefixes = [
        'registrar' => 'R',
        'cashier' => 'C'
    ];
    $office = strtolower(trim($office));
    if (!isset($office_prefixes[$office])) {
        error_log('Unable to generate queue number: unsupported office ' . $office);
        return false;
    }
    $prefix = $office_prefixes[$office];

    $seed_stmt = mysqli_prepare($con, "
        INSERT INTO queue_daily_counters (office, queue_date, last_number)
        SELECT ?, CURRENT_DATE(), COALESCE(MAX(CAST(SUBSTRING(queue_number, 3) AS UNSIGNED)), 0)
        FROM appointments
        WHERE office = ?
          AND DATE(created_at) = CURRENT_DATE()
          AND queue_number LIKE CONCAT(?, '-%')
        ON DUPLICATE KEY UPDATE last_number = last_number
    ");
    if (!$seed_stmt) {
        error_log('Unable to prepare daily queue counter: ' . mysqli_error($con));
        return false;
    }
    mysqli_stmt_bind_param($seed_stmt, 'sss', $office, $office, $prefix);
    if (!mysqli_stmt_execute($seed_stmt)) {
        error_log('Unable to initialize daily queue counter: ' . mysqli_stmt_error($seed_stmt));
        mysqli_stmt_close($seed_stmt);
        return false;
    }
    mysqli_stmt_close($seed_stmt);

    $lock_stmt = mysqli_prepare($con, 'SELECT last_number FROM queue_daily_counters WHERE office = ? AND queue_date = CURRENT_DATE() FOR UPDATE');
    if (!$lock_stmt) {
        error_log('Unable to prepare daily queue counter lock: ' . mysqli_error($con));
        return false;
    }
    mysqli_stmt_bind_param($lock_stmt, 's', $office);
    if (!mysqli_stmt_execute($lock_stmt)) {
        error_log('Unable to lock daily queue counter: ' . mysqli_stmt_error($lock_stmt));
        mysqli_stmt_close($lock_stmt);
        return false;
    }
    $counter = mysqli_fetch_assoc(mysqli_stmt_get_result($lock_stmt));
    mysqli_stmt_close($lock_stmt);
    if (!$counter) {
        error_log('Daily queue counter row was not created for ' . $office);
        return false;
    }

    $next_number = (int)$counter['last_number'] + 1;
    $update_stmt = mysqli_prepare($con, 'UPDATE queue_daily_counters SET last_number = ? WHERE office = ? AND queue_date = CURRENT_DATE()');
    if (!$update_stmt) {
        error_log('Unable to prepare daily queue counter update: ' . mysqli_error($con));
        return false;
    }
    mysqli_stmt_bind_param($update_stmt, 'is', $next_number, $office);
    if (!mysqli_stmt_execute($update_stmt) || mysqli_stmt_affected_rows($update_stmt) !== 1) {
        error_log('Unable to increment daily queue counter: ' . mysqli_stmt_error($update_stmt));
        mysqli_stmt_close($update_stmt);
        return false;
    }
    mysqli_stmt_close($update_stmt);

    return $prefix . '-' . str_pad((string)$next_number, 3, '0', STR_PAD_LEFT);
}
?>
