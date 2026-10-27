<?php
session_start();
require_once __DIR__ . '/db.php';

if (($_SESSION['user_role'] ?? '') !== 'registrar') {
    header('Location: login.php');
    exit();
}

$sortable = [
    'appointment_date' => 'a.appointment_date',
    'appointment_time' => 'a.appointment_time',
    'service' => 'a.service',
    'status' => 'a.status',
    'queue_number' => 'a.queue_number',
    'student_id' => 'a.student_id',
];
$sort = $_GET['sort'] ?? 'appointment_date';
if (!isset($sortable[$sort])) $sort = 'appointment_date';
$direction = strtoupper($_GET['direction'] ?? 'DESC');
if (!in_array($direction, ['ASC', 'DESC'], true)) $direction = 'DESC';
$search = trim($_GET['q'] ?? '');
$size = (int)($_GET['size'] ?? 10);
if (!in_array($size, [10, 25, 50, 100], true)) $size = 10;

$where = "a.office = 'registrar' AND (a.status IN ('Approved','Rejected','Cancelled') OR a.queue_status IN ('Completed','Skipped'))";
$params = [];
$types = '';
if ($search !== '') {
    $where .= " AND (a.student_id LIKE ? OR a.service LIKE ? OR a.queue_number LIKE ? OR a.status LIKE ? OR a.queue_status LIKE ? OR CONCAT(COALESCE(u.first_name,''),' ',COALESCE(u.last_name,'')) LIKE ? )";
    $term = '%' . $search . '%';
    $params = array_fill(0, 6, $term);
    $types = 'ssssss';
}

$count_stmt = mysqli_prepare($con, "SELECT COUNT(*) AS total FROM appointments a LEFT JOIN users u ON u.student_id = a.student_id WHERE $where");
if ($count_stmt && $types !== '') {
    [$p1, $p2, $p3, $p4, $p5, $p6] = $params;
    mysqli_stmt_bind_param($count_stmt, 'ssssss', $p1, $p2, $p3, $p4, $p5, $p6);
}
if ($count_stmt) mysqli_stmt_execute($count_stmt);
$count_result = $count_stmt ? mysqli_stmt_get_result($count_stmt) : false;
$total = $count_result ? (int)(mysqli_fetch_assoc($count_result)['total'] ?? 0) : 0;
if ($count_stmt) mysqli_stmt_close($count_stmt);

$pages = max(1, (int)ceil($total / $size));
$page = min(max(1, (int)($_GET['page'] ?? 1)), $pages);
$offset = ($page - 1) * $size;
$sql = "SELECT a.id, a.student_id, a.queue_number, a.service, a.appointment_date, a.appointment_time, a.status, a.rejection_reason, a.queue_status, CONCAT(COALESCE(u.first_name,''),' ',COALESCE(u.last_name,'')) AS student_name FROM appointments a LEFT JOIN users u ON u.student_id = a.student_id WHERE $where ORDER BY {$sortable[$sort]} $direction LIMIT ? OFFSET ?";
$rows_stmt = mysqli_prepare($con, $sql);
if ($rows_stmt) {
    if ($types !== '') {
        [$p1, $p2, $p3, $p4, $p5, $p6] = $params;
        mysqli_stmt_bind_param($rows_stmt, 'ssssssii', $p1, $p2, $p3, $p4, $p5, $p6, $size, $offset);
    } else {
        mysqli_stmt_bind_param($rows_stmt, 'ii', $size, $offset);
    }
    mysqli_stmt_execute($rows_stmt);
    $rows = mysqli_stmt_get_result($rows_stmt);
} else {
    $rows = false;
}

function history_url(array $changes = []): string {
    return '?' . http_build_query(array_merge($_GET, $changes));
}
function history_badge_class(string $status): string {
    $key = strtolower(trim($status));
    $classes = [
        'approved' => 'badge-approved', 'completed' => 'badge-completed',
        'rejected' => 'badge-rejected', 'cancelled' => 'badge-cancelled',
        'serving' => 'badge-serving', 'waiting' => 'badge-waiting',
        'skipped' => 'badge-skipped', 'pending' => 'badge-pending',
    ];
    return $classes[$key] ?? 'badge-neutral';
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Past Appointment History</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="admin.css">
</head>
<body>
  <main class="history-page">
    <?php
    $registrarHeaderPage = 'history';
    $registrarHeaderEyebrow = 'Registrar Dashboard';
    $registrarHeaderTitle = 'Past History Appointments';
    $registrarHeaderSubtitle = '';
    require __DIR__ . '/registrar-header.php';
    ?>

    <section class="history-card" aria-labelledby="historyTableHeading">
      <div class="history-card-heading">
        <div><h2 id="historyTableHeading">Appointment records</h2><p>Search, sort, and page through registrar history.</p></div>
        <span class="history-total-pill"><?php echo number_format($total); ?> records</span>
      </div>

      <form class="history-tools" method="get" action="appointment-history.php">
        <label class="history-entry-control">Show
          <select name="size" onchange="this.form.submit()">
            <?php foreach ([10, 25, 50, 100] as $n): ?><option value="<?php echo $n; ?>" <?php echo $size === $n ? 'selected' : ''; ?>><?php echo $n; ?></option><?php endforeach; ?>
          </select> entries
        </label>
        <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort); ?>">
        <input type="hidden" name="direction" value="<?php echo htmlspecialchars($direction); ?>">
        <label class="history-search-control"><span>Search records</span>
          <input type="search" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Student, service, status, or queue">
        </label>
        <button class="history-search-button" type="submit">Search</button>
      </form>

      <div class="history-table-scroll">
        <table class="history-table">
          <thead><tr>
            <?php foreach (['queue_number' => 'Queue #', 'student_id' => 'Student', 'service' => 'Service', 'appointment_date' => 'Date', 'appointment_time' => 'Time', 'status' => 'Appointment Status'] as $key => $label): ?>
              <?php $next_direction = $sort === $key && $direction === 'ASC' ? 'DESC' : 'ASC'; ?>
              <th scope="col"><a href="<?php echo htmlspecialchars(history_url(['sort' => $key, 'direction' => $next_direction, 'page' => 1])); ?>"><?php echo htmlspecialchars($label); ?><?php echo $sort === $key ? ($direction === 'ASC' ? ' (ascending)' : ' (descending)') : ''; ?></a></th>
            <?php endforeach; ?>
            <th scope="col">Queue State</th>
            <th scope="col">Rejection Reason</th>
          </tr></thead>
          <tbody>
          <?php if ($rows && mysqli_num_rows($rows) > 0): while ($row = mysqli_fetch_assoc($rows)): ?>
            <?php $status = (string)($row['status'] ?? ''); $queue_state = trim((string)($row['queue_status'] ?? '')); ?>
            <tr>
              <td><span class="history-queue-number"><?php echo htmlspecialchars($row['queue_number'] ?: '#' . $row['id']); ?></span></td>
              <td><strong><?php echo htmlspecialchars($row['student_name'] ?: 'Student'); ?></strong><small><?php echo htmlspecialchars($row['student_id']); ?></small></td>
              <td><?php echo htmlspecialchars($row['service']); ?></td>
              <td><?php echo htmlspecialchars(date('M d, Y', strtotime($row['appointment_date']))); ?></td>
              <td><?php echo htmlspecialchars(date('h:i A', strtotime($row['appointment_time']))); ?></td>
              <td><span class="status-pill <?php echo history_badge_class($status); ?>"><?php echo htmlspecialchars($status ?: 'Unknown'); ?></span></td>
              <td><span class="status-pill <?php echo history_badge_class($queue_state); ?>"><?php echo htmlspecialchars($queue_state ?: 'None'); ?></span></td>
              <td><?php echo $status === 'Rejected' && trim((string)($row['rejection_reason'] ?? '')) !== '' ? htmlspecialchars($row['rejection_reason']) : '<span class="history-reason-empty">—</span>'; ?></td>
            </tr>
          <?php endwhile; else: ?>
            <tr><td class="history-empty" colspan="8">No appointment records match your search.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>

      <footer class="history-pages">
        <p><?php echo $total ? 'Showing ' . number_format($offset + 1) . '-' . number_format(min($offset + $size, $total)) . ' of ' . number_format($total) . ' entries' : 'Showing 0 entries'; ?></p>
        <div class="history-pagination" aria-label="History pagination">
          <a class="<?php echo $page <= 1 ? 'is-disabled' : ''; ?>" href="<?php echo htmlspecialchars(history_url(['page' => max(1, $page - 1)])); ?>" aria-disabled="<?php echo $page <= 1 ? 'true' : 'false'; ?>">Previous</a>
          <span>Page <?php echo $page; ?> of <?php echo $pages; ?></span>
          <a class="<?php echo $page >= $pages ? 'is-disabled' : ''; ?>" href="<?php echo htmlspecialchars(history_url(['page' => min($pages, $page + 1)])); ?>" aria-disabled="<?php echo $page >= $pages ? 'true' : 'false'; ?>">Next</a>
        </div>
      </footer>
    </section>
  </main>
</body>
</html>
<?php if ($rows_stmt) mysqli_stmt_close($rows_stmt); ?>
