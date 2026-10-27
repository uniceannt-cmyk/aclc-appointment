<?php
session_start();
require_once __DIR__ . '/db.php';
if (($_SESSION['user_role'] ?? '') !== 'registrar') {
    $_SESSION['registrar_return_to'] = 'schedule-calendar.php';
    header('Location: admin-login.php');
    exit();
}
$date = trim($_GET['week'] ?? date('Y-m-d'));
$week_start = strtotime($date);
if (!$week_start) $week_start = strtotime('monday this week');
$week_start = strtotime('monday this week', $week_start);
$start = date('Y-m-d', $week_start);
$end = date('Y-m-d', strtotime('+5 days', $week_start));
$previous = date('Y-m-d', strtotime('-7 days', $week_start));
$next = date('Y-m-d', strtotime('+7 days', $week_start));
$days = [];
for ($d = 0; $d < 5; $d++) $days[] = date('Y-m-d', strtotime("+$d days", $week_start));
$items = [];
$stmt = mysqli_prepare($con, "SELECT id, appointment_date, appointment_time, service, status, queue_status, queue_number FROM appointments WHERE office = 'registrar' AND appointment_date BETWEEN ? AND ? AND status NOT IN ('Cancelled','Rejected') ORDER BY appointment_time");
mysqli_stmt_bind_param($stmt, 'ss', $start, $end); mysqli_stmt_execute($stmt); $res = mysqli_stmt_get_result($stmt);
while ($res && $row = mysqli_fetch_assoc($res)) $items[$row['appointment_date']][] = $row;
$slot_rows = [];
$slots_stmt = mysqli_prepare($con, "SELECT slot_date,start_time,end_time,status FROM appointment_slots WHERE office='registrar' AND slot_date BETWEEN ? AND ? ORDER BY start_time");
mysqli_stmt_bind_param($slots_stmt, 'ss', $start, $end); mysqli_stmt_execute($slots_stmt); $sr = mysqli_stmt_get_result($slots_stmt);
while ($sr && $row = mysqli_fetch_assoc($sr)) $slot_rows[$row['slot_date']][] = $row;
$service_duration = [];
$duration_res = mysqli_query($con, "SELECT service_name, estimated_duration_min FROM services WHERE office_code='registrar'");
while ($duration_res && $dr = mysqli_fetch_assoc($duration_res)) $service_duration[$dr['service_name']] = max(15, (int)$dr['estimated_duration_min']);
function calendar_minute($time) { return ((int)substr($time,0,2))*60 + (int)substr($time,3,2); }
function calendar_class($status) { $s = strtolower($status); if ($s === 'approved') return 'confirmed'; if ($s === 'completed') return 'completed'; if ($s === 'pending') return 'confirm'; if ($s === 'rejected' || $s === 'cancelled') return 'unavailable'; return 'special'; }
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Registrar Schedule Calendar</title><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"><link rel="stylesheet" href="admin.css"><style>
.cal-wrap{overflow:auto;width:min(1280px,100%);margin:0 auto;padding:0}.cal-toolbar{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap}.cal-toolbar nav a.active{font-weight:700;color:#1d4ed8;text-decoration:underline;text-underline-offset:4px}.cal-grid{border-collapse:collapse;min-width:900px;width:100%;table-layout:fixed;background:#fff}.cal-grid th,.cal-grid td{border:1px solid #cbd5e1;padding:3px;height:27px}.cal-grid th{position:sticky;top:0;background:#1e4e91;color:#fff;z-index:2}.cal-grid th:first-child,.cal-grid td:first-child{width:96px;text-align:center;background:#f1f5f9}.cal-grid .slot-cell{padding:0;background:#fff}.cal-grid .event{vertical-align:top;padding:6px 8px;font-size:12px;line-height:1.35;color:#172033}.cal-grid .confirmed{background:#c9f1d1}.cal-grid .confirm{background:#ffead0}.cal-grid .completed{background:#e2e8f0}.cal-grid .special{background:#dbeafe}.cal-grid .unavailable{background:#d1d5db}.cal-grid .lunch{background:#e5e7eb;color:#475569}.cal-legend{display:flex;gap:14px;flex-wrap:wrap;margin:12px 0}.cal-legend span{padding:5px 9px;border-radius:5px;font-size:12px}.cal-grid a{color:inherit}
</style></head><body><main class="cal-wrap"><?php $registrarHeaderPage = 'schedule'; $registrarHeaderEyebrow = 'Registrar Dashboard'; $registrarHeaderTitle = 'Registrar Appointment Schedule'; $registrarHeaderSubtitle = ''; require __DIR__ . '/registrar-header.php'; ?>
<div class="cal-toolbar"><a class="btn-refresh" href="?week=<?php echo $previous; ?>">← Previous week</a><strong><?php echo date('M j', $week_start) . ' – ' . date('M j, Y', strtotime($end)); ?></strong><a class="btn-refresh" href="?week=<?php echo $next; ?>">Next week →</a></div>
<div class="cal-legend"><span class="confirmed">Confirmed</span><span class="confirm">Need to Confirm</span><span class="completed">Completed</span><span class="special">Special Appointment</span><span class="unavailable">Unavailable / Out of Office</span><span class="lunch">Lunch</span></div>
<div class="cal-table-scroll"><table class="cal-grid"><thead><tr><th>Time</th><?php foreach($days as $day): ?><th><?php echo date('l, M j', strtotime($day)); ?></th><?php endforeach; ?></tr></thead><tbody>
<?php $occupied_until=[]; for($minute=480;$minute<=960;$minute+=15): $label=date('g:i A', mktime(0,$minute)); ?><tr><td><?php echo $label; ?></td><?php foreach($days as $day):
$appt=null; foreach($items[$day]??[] as $candidate) if(calendar_minute($candidate['appointment_time'])===$minute){$appt=$candidate;break;}
$slot=null; foreach($slot_rows[$day]??[] as $candidate) if(calendar_minute($candidate['start_time'])===$minute){$slot=$candidate;break;}
if(isset($occupied_until[$day]) && $minute < $occupied_until[$day]) { continue; }
if($appt){$duration=0; foreach(explode(', ', $appt['service']) as $service_name) $duration += $service_duration[$service_name] ?? 15; $span=max(1,min((int)ceil($duration/15),(960-$minute)/15)); $occupied_until[$day]=$minute+$span*15; $effective_status=strtolower($appt['queue_status'])==='completed'?'completed':$appt['status']; $statusClass=calendar_class($effective_status); ?>
<td class="event <?php echo $statusClass; ?>" rowspan="<?php echo $span; ?>"><strong><?php echo htmlspecialchars($appt['queue_number'] ?: '#'.$appt['id']); ?></strong> · <?php echo htmlspecialchars($effective_status); ?><br><?php echo htmlspecialchars($appt['service']); ?></td>
<?php } elseif ($minute>=705 && $minute<780) { ?><td class="slot-cell lunch">Lunch / Unavailable</td>
<?php } elseif ($slot) { ?><td class="slot-cell <?php echo $slot['status']==='closed'?'unavailable':($slot['status']==='full'?'special':''); ?>" title="<?php echo htmlspecialchars($slot['status']); ?>"></td>
<?php } else { ?><td class="slot-cell"></td><?php } ?><?php endforeach; ?></tr><?php endfor; ?>
</tbody></table></div></main></body></html>
