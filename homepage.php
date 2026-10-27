<?php
session_start();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
include("db.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$current_role = $_SESSION['user_role'] ?? '';
if ($current_role === 'registrar') {
    header("Location: admin.php?office=registrar");
    exit();
}

$student_id = $_SESSION['user_id'];
$user_stmt = mysqli_prepare($con, "SELECT student_id, first_name, last_name, email, phone, role, education_level FROM users WHERE student_id = ?");
$user = null;

if ($user_stmt) {
    mysqli_stmt_bind_param($user_stmt, "s", $student_id);
    mysqli_stmt_execute($user_stmt);
    $user_result = mysqli_stmt_get_result($user_stmt);
    $user = mysqli_fetch_assoc($user_result);
    mysqli_stmt_close($user_stmt);
}
$education_level = $user['education_level'] ?? ($_SESSION['education_level'] ?? null);

$safe_student_id = mysqli_real_escape_string($con, $student_id);
$result = mysqli_query($con, "SELECT id, office, service, appointment_date, appointment_time, status, rejection_reason, registrar_notes, queue_number, queue_status FROM appointments WHERE student_id = '$safe_student_id' ORDER BY id DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>School Appointment & Queuing - ACLC</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="homepage.css">
    <link rel="stylesheet" href="popup.css?v=<?php echo filemtime(__DIR__ . '/popup.css'); ?>">
    <script src="popup.js?v=<?php echo filemtime(__DIR__ . '/popup.js'); ?>"></script>
</head>

<body>
    <header class="home-header">
        <div class="school-name">
            <img src="img/image.png?v=2" alt="ACLC Logo" class="ama-logo" onerror="this.style.display='none'">
            <div class="school-text">
                <h1>ACLC College of Daet Appointment</h1>
                <p class="school-info">2F Guinhawa Bldg. J. Lukban St. Daet, Camarines Norte, Daet, Philippines, 4600</p>
            </div>
        </div>

        <div class="header-user-actions">
            <button type="button" class="theme-toggle" id="themeToggle" aria-label="Toggle dark mode">
                <i class="fas fa-moon"></i>
            </button>
            <button type="button" class="profile-trigger" id="profileTrigger" aria-label="View profile">
                <?php
                    $user_initials = strtoupper(substr(trim($_SESSION['user_name'] ?? 'Student'), 0, 2));
                    echo htmlspecialchars($user_initials);
                ?>
            </button>
            <a href="logout.php" class="logout-button">Logout</a>
        </div>
    </header>

    <section class="welcome-sec">
        <div class="welcome-copy">
            <p class="welcome-kicker"><i class="fas fa-graduation-cap" aria-hidden="true"></i> Student portal</p>
            <h2>Welcome, <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Student'); ?>!</h2>
            <p>Book campus services and keep track of your appointments.</p>
        </div>
        <div class="welcome-campus" aria-label="ACLC Daet campus">
            <span>ACLC</span>
            <strong>DAET</strong>
        </div>
    </section>

    <section class="options" id="office-options">
        <div class="section-intro">
            <p class="section-kicker">Start here</p>
            <h3>Book a campus visit</h3>
        </div>
        <div class="cards">
            <div class="card card-registrar">
                <div class="card-icon" aria-hidden="true"><i class="fas fa-file-signature"></i></div>
                <div class="card-copy">
                    <h4>Registrar Office</h4>
                    <p>Enrollment verification, student records, honors, and subject add or drop requests.</p>
                </div>
                <a href="registrar.php" class="btn">Book an appointment <i class="fas fa-arrow-right" aria-hidden="true"></i></a>
            </div>

        </div>
    </section>

    <div class="profile-modal" id="profileModal" aria-hidden="true">
        <div class="profile-modal-content">
            <button type="button" class="close-profile" id="closeProfile" aria-label="Close profile">&times;</button>
            <div class="profile-modal-header">
                <img src="https://ui-avatars.com/api/?name=<?php echo rawurlencode($_SESSION['user_name'] ?? 'Student'); ?>&background=003366&color=fff&size=200" alt="Profile image">
                <h3><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Student'); ?></h3>
            </div>
            <div class="profile-details">
                <div>
                    <span>Student ID</span>
                    <strong><?php echo htmlspecialchars($user['student_id'] ?? $student_id); ?></strong>
                </div>
                <div>
                    <span>Email</span>
                    <strong><?php echo htmlspecialchars($_SESSION['user_email'] ?? ($user['email'] ?? 'N/A')); ?></strong>
                </div>
                <div>
                    <span>Mobile Phone</span>
                    <strong><?php echo htmlspecialchars($user['phone'] ?? 'Not set'); ?></strong>
                </div>
                <div>
                    <span>Role</span>
                    <strong><?php echo htmlspecialchars(ucfirst($_SESSION['user_role'] ?? ($user['role'] ?? 'student'))); ?></strong>
                </div>
                <div>
                    <span>Education Level</span>
                    <strong><?php echo $education_level === 'SHS' ? 'Senior High School' : ($education_level === 'College' ? 'College' : 'Not selected'); ?></strong>
                </div>
            </div>
            <button type="button" class="change-level-button" id="changeLevelButton">Change Level</button>
        </div>
    </div>

    <div class="education-level-modal" id="educationLevelModal" aria-hidden="true">
        <div class="education-level-dialog" role="dialog" aria-modal="true" aria-labelledby="educationLevelTitle">
            <h2 id="educationLevelTitle">Choose your educational level</h2>
            <p>Select the catalog that matches your current program.</p>
            <div class="education-level-options">
                <button type="button" class="education-level-card" data-education-level="SHS">
                    <i class="fas fa-graduation-cap"></i>
                    <strong>Senior High School</strong>
                    <span>Strands, Grade 11 &amp; 12 Requests</span>
                </button>
                <button type="button" class="education-level-card" data-education-level="College">
                    <i class="fas fa-university"></i>
                    <strong>College</strong>
                    <span>Degree Programs, Transcript &amp; Evaluation</span>
                </button>
            </div>
            <p class="education-level-error" id="educationLevelError" role="alert"></p>
        </div>
    </div>

  
    <!-- Toast notification container -->
    <div class="realtime-toast-container" id="realtimeToastContainer" aria-live="polite" aria-atomic="false"></div>

    <div class="rejection-modal-overlay" id="rejectionModal" aria-hidden="true">
        <section class="rejection-modal" role="alertdialog" aria-modal="true" aria-labelledby="rejectionModalTitle" aria-describedby="rejectionModalGuidance">
            <header class="rejection-modal-header">
                <span class="rejection-modal-icon" aria-hidden="true"><i class="fas fa-exclamation"></i></span>
                <div>
                    <p class="rejection-modal-kicker">Appointment update</p>
                    <h2 id="rejectionModalTitle">Appointment Rejected</h2>
                </div>
                <button type="button" class="rejection-modal-close" id="rejectionModalClose" aria-label="Close rejection details">&times;</button>
            </header>
            <div class="rejection-modal-details">
                <h3>Request details</h3>
                <dl>
                    <div><dt>Service</dt><dd id="rejectionModalService"></dd></div>
                    <div><dt>Requested schedule</dt><dd id="rejectionModalSchedule"></dd></div>
                </dl>
            </div>
            <div class="rejection-modal-reason">
                <h3><i class="fas fa-comment-dots" aria-hidden="true"></i> Why was it rejected?</h3>
                <p id="rejectionModalReason"></p>
            </div>
            <p class="rejection-modal-guidance" id="rejectionModalGuidance">Please select another available date and time, or contact the Registrar's Office for assistance.</p>
            <footer class="rejection-modal-actions">
                <button type="button" class="rejection-modal-dismiss" id="rejectionModalDismiss">Close</button>
                <a class="rejection-modal-book" href="registrar.php">Book Another Slot <i class="fas fa-arrow-right" aria-hidden="true"></i></a>
            </footer>
        </section>
    </div>

    <section class="appointments" id="appointments">
        <div class="appointments-heading">
            <div>
                <p class="section-kicker">Your activity</p>
                <h3><span class="appointments-title-icon" aria-hidden="true"><i class="fas fa-calendar-check"></i></span> Appointments &amp; queue tickets
                    <span class="live-indicator" id="liveIndicator" title="Auto-refreshing every 8 seconds">
                        <span class="live-dot"></span> LIVE
                    </span>
                </h3>
                <p class="appointments-help">Have your queue number ready when called at the campus counter.</p>
            </div>
            <div class="appointment-filters" aria-label="Filter upcoming appointments">
                <label class="sr-only" for="appointmentSearch">Search appointments</label>
                <input type="search" id="appointmentSearch" placeholder="Search appointments...">
            </div>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Queue #</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Office</th>
                    <th>Service</th>
                    <th>Appointment Status</th>
                    <th>Remarks / Reason</th>
                    <th>Action</th>
                </tr>
            </thead>

         <tbody id="studentAppointmentsTable">
                <?php if ($result && mysqli_num_rows($result) > 0): ?>
                    <?php while ($row = mysqli_fetch_assoc($result)): ?>
                        <?php
                            $status = strtolower(trim($row['status']));
                            $statusClass = 'status-default';

                            if ($status === 'approved') {
                                $statusClass = 'status-approved';
                            } elseif ($status === 'declined' || $status === 'rejected') {
                                $statusClass = 'status-declined';
                            } elseif ($status === 'pending') {
                                $statusClass = 'status-pending';
                            } elseif ($status === 'cancelled') {
                                $statusClass = 'status-declined';
                            }

                            $display_reason = trim((string)($row['rejection_reason'] ?? '')) !== ''
                                ? trim((string)($row['rejection_reason'] ?? ''))
                                : trim((string)($row['registrar_notes'] ?? ''));

                            $q_badge_class = 'queue-badge';
                            $display_queue = !empty($row['queue_number']) ? $row['queue_number'] : ('#' . $row['id']);
                        ?>
                        <tr class="appointment-row" data-status="<?php echo htmlspecialchars($status); ?>" data-appt-id="<?php echo (int)$row['id']; ?>">
                            <td data-label="Queue #">
                                <span class="<?php echo $q_badge_class; ?>">
                                    <?php echo htmlspecialchars($display_queue); ?>
                                </span>
                            </td>
                            <td data-label="Date" id="date-cell-<?php echo (int)$row['id']; ?>">
                                <strong><?php echo htmlspecialchars(date("M d, Y", strtotime($row['appointment_date']))); ?></strong>
                            </td>
                            <td data-label="Time" id="time-cell-<?php echo (int)$row['id']; ?>">
                                <span><?php echo htmlspecialchars(date("h:i A", strtotime($row['appointment_time']))); ?></span>
                            </td>
                            <td data-label="Office">
                                <span style="font-weight: 600; text-transform: capitalize;">
                                    <?php echo htmlspecialchars($row['office']); ?>
                                </span>
                            </td>
                            
                            <td data-label="Service" id="service-cell-<?php echo (int)$row['id']; ?>"><?php echo htmlspecialchars($row['service']); ?></td>
                            
                            <td data-label="Appointment Status" id="status-cell-<?php echo (int)$row['id']; ?>">
                                <div class="appointment-status-content">
                                <?php if ($status === 'approved'): ?>
                                    <span class="status-badge status-approved"><i class="fas fa-check-circle"></i> Confirmed Schedule</span>
                                <?php elseif ($status === 'pending'): ?>
                                    <span class="status-badge status-pending"><i class="fas fa-hourglass-half"></i> Pending Review</span>
                                <?php elseif ($status === 'rejected' || $status === 'declined'): ?>
                                    <span class="status-badge status-declined"><i class="fas fa-times-circle"></i> Rejected</span>
                                <?php elseif ($status === 'cancelled'): ?>
                                    <span class="status-badge status-declined"><i class="fas fa-ban"></i> Cancelled</span>
                                <?php else: ?>
                                    <span class="status-badge <?php echo $statusClass; ?>"><?php echo htmlspecialchars($row['status']); ?></span>
                                <?php endif; ?>
                                </div>
                            </td>

                            <td data-label="Remarks / Reason" id="reason-cell-<?php echo (int)$row['id']; ?>" class="appointment-reason-cell">
                                <?php if ($display_reason !== ''): ?>
                                    <div class="rejection-reason reason-badge">
                                        <span class="rejection-reason-text"><?php echo htmlspecialchars($display_reason); ?></span>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>

                            <td data-label="Action" id="action-cell-<?php echo (int)$row['id']; ?>">
                                <?php if ($status === 'pending'): ?>
                                    <form method="POST" action="cancel_appointment.php">
                                        <input type="hidden" name="appointment_id" value="<?php echo htmlspecialchars($row['id']); ?>">
                                        <button type="submit" class="cancel-button">Cancel</button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8">No appointments booked yet. Choose an office above to book your first slot!</td>
                    </tr>
                <?php endif; ?>
                <tr id="noAppointmentMatches" hidden>
                    <td colspan="8">No appointments match your search/filter criteria.</td>
                </tr>
            </tbody>    
        </table>
    </section>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
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

            const profileTrigger = document.getElementById('profileTrigger');
            const profileModal = document.getElementById('profileModal');
            const closeProfile = document.getElementById('closeProfile');
            const levelModal = document.getElementById('educationLevelModal');
            const changeLevelButton = document.getElementById('changeLevelButton');
            const levelError = document.getElementById('educationLevelError');

            function openLevelModal() {
                if (!levelModal) return;
                levelModal.classList.add('show');
                levelModal.setAttribute('aria-hidden', 'false');
            }

            function closeLevelModal() {
                if (!levelModal) return;
                levelModal.classList.remove('show');
                levelModal.setAttribute('aria-hidden', 'true');
            }

            document.querySelectorAll('[data-education-level]').forEach(function (card) {
                card.addEventListener('click', function () {
                    const body = new URLSearchParams({ education_level: card.dataset.educationLevel });
                    card.disabled = true;
                    fetch('update_education_level.php', { method: 'POST', body: body, headers: { Accept: 'application/json' } })
                        .then(response => response.json())
                        .then(data => {
                            if (!data.success) throw new Error(data.message || 'Could not save your level.');
                            window.location.href = 'registrar.php';
                        })
                        .catch(error => {
                            card.disabled = false;
                            if (levelError) levelError.textContent = error.message;
                        });
                });
            });

            if (changeLevelButton) changeLevelButton.addEventListener('click', openLevelModal);
            if (levelModal) levelModal.addEventListener('click', function (event) {
                if (event.target === levelModal && <?php echo $education_level ? 'true' : 'false'; ?>) closeLevelModal();
            });
            if (!<?php echo $education_level ? 'true' : 'false'; ?> || <?php echo isset($_GET['choose_level']) || isset($_GET['change_level']) ? 'true' : 'false'; ?>) openLevelModal();

            if (profileTrigger && profileModal && closeProfile) {
                profileTrigger.addEventListener('click', function () {
                    profileModal.classList.add('show');
                    profileModal.setAttribute('aria-hidden', 'false');
                });

                closeProfile.addEventListener('click', function () {
                    profileModal.classList.remove('show');
                    profileModal.setAttribute('aria-hidden', 'true');
                });

                profileModal.addEventListener('click', function (event) {
                    if (event.target === profileModal) {
                        profileModal.classList.remove('show');
                        profileModal.setAttribute('aria-hidden', 'true');
                    }
                });
            }

            const rejectionModal = document.getElementById('rejectionModal');
            document.getElementById('rejectionModalClose').addEventListener('click', closeRejectionModal);
            document.getElementById('rejectionModalDismiss').addEventListener('click', closeRejectionModal);
            rejectionModal.addEventListener('click', function (event) {
                if (event.target === rejectionModal) closeRejectionModal();
            });
            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && rejectionModal.classList.contains('is-visible')) closeRejectionModal();
            });

            const searchInput = document.getElementById('appointmentSearch');
            const statusFilter = document.getElementById('appointmentStatusFilter');
            let rows = Array.from(document.querySelectorAll('.appointment-row'));
            const noMatches = document.getElementById('noAppointmentMatches');

            function filterRows() {
                const query = (searchInput?.value || '').trim().toLowerCase();
                const selectedStatus = (statusFilter?.value || 'all').toLowerCase();
                let visibleCount = 0;

                rows.forEach(function (row) {
                    const rowStatus = (row.dataset.status || '').toLowerCase();
                    const text = row.textContent.toLowerCase();

                    const matchesQuery = query === '' || text.includes(query);
                    const matchesStatus = selectedStatus === 'all' || rowStatus === selectedStatus;

                    if (matchesQuery && matchesStatus) {
                        row.style.display = '';
                        visibleCount++;
                    } else {
                        row.style.display = 'none';
                    }
                });

                if (noMatches) {
                    noMatches.hidden = visibleCount > 0 || rows.length === 0;
                }
            }

            if (searchInput) searchInput.addEventListener('input', filterRows);
            if (statusFilter) statusFilter.addEventListener('change', filterRows);

            function escapeHtml(value) {
                return String(value ?? '').replace(/[&<>'"]/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[char]));
            }

            function formatDate(value) {
                const date = new Date(value + 'T00:00:00');
                return Number.isNaN(date.getTime()) ? value : date.toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' });
            }

            function formatTime(value) {
                const parts = String(value || '').slice(0, 5).split(':');
                if (parts.length !== 2) return value;
                const hour = Number(parts[0]);
                return `${((hour + 11) % 12) + 1}:${parts[1]} ${hour >= 12 ? 'PM' : 'AM'}`;
            }

            function renderStudentAppointments(appointments) {
                const table = document.getElementById('studentAppointmentsTable');
                if (!table) return;
                const noMatchesRow = document.getElementById('noAppointmentMatches');
                table.querySelectorAll('.appointment-row, .empty-appointments-row').forEach(row => row.remove());
                appointments.forEach(appt => {
                    const status = String(appt.status || 'Pending').toLowerCase();
                    const row = document.createElement('tr');
                    row.className = 'appointment-row';
                    row.dataset.status = status;
                    row.dataset.apptId = appt.id;
                    const queue = appt.queue_number || ('#' + appt.id);
                    const canCancel = status === 'pending';
                    row.innerHTML = `<td data-label="Queue #"><span class="queue-badge">${escapeHtml(queue)}</span></td>
                        <td data-label="Date" id="date-cell-${appt.id}"><strong>${escapeHtml(formatDate(appt.appointment_date))}</strong></td>
                        <td data-label="Time" id="time-cell-${appt.id}"><span>${escapeHtml(formatTime(appt.appointment_time))}</span></td>
                        <td data-label="Office"><span style="font-weight:600;text-transform:capitalize">${escapeHtml(appt.office)}</span></td>
                        <td data-label="Service" id="service-cell-${appt.id}">${escapeHtml(appt.service)}</td>
                        <td data-label="Appointment Status" id="status-cell-${appt.id}">${buildStatusBadgeHTML(appt.status)}</td>
                        <td data-label="Remarks / Reason" id="reason-cell-${appt.id}" class="appointment-reason-cell">${buildRejectionReasonHTML(appt.status, appt.rejection_reason, appt.registrar_notes)}</td>
                        <td data-label="Action" id="action-cell-${appt.id}">${canCancel ? `<form method="POST" action="cancel_appointment.php"><input type="hidden" name="appointment_id" value="${appt.id}"><button type="submit" class="cancel-button">Cancel</button></form>` : '<span class="text-muted">-</span>'}</td>`;
                    table.insertBefore(row, noMatchesRow || null);
                });
                if (!appointments.length) {
                    const empty = document.createElement('tr');
                    empty.className = 'empty-appointments-row';
                    empty.innerHTML = '<td colspan="8">No appointments booked yet.</td>';
                    table.insertBefore(empty, noMatchesRow || null);
                }
                rows = Array.from(document.querySelectorAll('.appointment-row'));
                rows.forEach(row => {
                    if (!Object.prototype.hasOwnProperty.call(knownStatuses, row.dataset.apptId)) {
                        knownStatuses[row.dataset.apptId] = row.dataset.status;
                    }
                });
                filterRows();
            }

            function hydrateStudentAppointments() {
                return fetch('api_appointments.php', { cache: 'no-store' })
                    .then(res => res.json())
                    .then(data => { if (data.success && Array.isArray(data.appointments)) renderStudentAppointments(data.appointments); });
            }

            // ================================================================
            // Real-Time Appointment Status Polling (Student View)
            // ================================================================

            // Track last-known status for each appointment id
            const knownStatuses = {};
            document.querySelectorAll('.appointment-row[data-appt-id]').forEach(function (row) {
                knownStatuses[row.dataset.apptId] = (row.dataset.status || '').toLowerCase();
            });

            function buildStatusBadgeHTML(status) {
                status = (status || '').toLowerCase();
                let badge = '';
                if (status === 'approved') {
                    badge = '<span class="status-badge status-approved"><i class="fas fa-check-circle"></i> Confirmed Schedule</span>';
                } else if (status === 'pending') {
                    badge = '<span class="status-badge status-pending"><i class="fas fa-hourglass-half"></i> Pending Review</span>';
                } else if (status === 'rejected' || status === 'declined') {
                    badge = '<span class="status-badge status-declined"><i class="fas fa-times-circle"></i> Rejected</span>';
                } else if (status === 'cancelled') {
                    badge = '<span class="status-badge status-declined"><i class="fas fa-ban"></i> Cancelled</span>';
                } else {
                    badge = '<span class="status-badge status-default">' + escapeHtml(status.charAt(0).toUpperCase() + status.slice(1)) + '</span>';
                }
                return '<div class="appointment-status-content">' + badge + '</div>';
            }

            function buildRejectionReasonHTML(status, rejectionReason, registrarNotes) {
                status = (status || '').toLowerCase();
                const reason = String(rejectionReason || '').trim() || String(registrarNotes || '').trim();
                return reason ? '<div class="rejection-reason reason-badge"><span class="rejection-reason-text">' + escapeHtml(reason) + '</span></div>' : '<span class="text-muted">-</span>';
            }

            let appointmentPopupQueue = Promise.resolve();
            function showRealtimeToast(type, title, message) {
                const popupType = type === 'approved' ? 'success'
                    : (type === 'rejected' || type === 'warning') ? 'warning'
                    : 'info';
                appointmentPopupQueue = appointmentPopupQueue.then(function () {
                    return AppPopup.alert(message, { title: title, type: popupType });
                }).catch(function (error) {
                    console.error('Unable to show appointment notification:', error);
                });
            }

            function showAppointmentDecisionPopup(status, appointment) {
                const normalizedStatus = String(status || '').toLowerCase();
                const accepted = normalizedStatus === 'approved' || normalizedStatus === 'accepted' || normalizedStatus === 'confirmed';
                const rejected = normalizedStatus === 'rejected' || normalizedStatus === 'declined';
                if (!accepted && !rejected) return;

                if (rejected) {
                    showRejectionModal(appointment);
                    return;
                }

                const service = String(appointment.service || 'Appointment').trim();
                const date = appointment.appointment_date ? formatDate(appointment.appointment_date) : 'date not specified';
                const time = appointment.appointment_time ? formatTime(appointment.appointment_time) : '';
                const schedule = date + (time ? ' at ' + time : '');
                showRealtimeToast('approved', 'Appointment Accepted', 'Service: ' + service + '. Schedule: ' + schedule + '.');
            }

            function showRejectionModal(appointment) {
                const modal = document.getElementById('rejectionModal');
                if (!modal) return;
                const reason = String(appointment.rejection_reason || appointment.reason || appointment.registrar_notes || appointment.notes || '').trim();
                const date = appointment.appointment_date ? formatDate(appointment.appointment_date) : 'Date not specified';
                const time = appointment.appointment_time ? formatTime(appointment.appointment_time) : '';
                document.getElementById('rejectionModalService').textContent = String(appointment.service || 'Appointment').trim();
                document.getElementById('rejectionModalSchedule').textContent = date + (time ? ' at ' + time : '');
                document.getElementById('rejectionModalReason').textContent = reason || "No specific reason was provided. Please contact the Registrar's Office if you need more information.";
                modal.classList.add('is-visible');
                modal.setAttribute('aria-hidden', 'false');
                document.getElementById('rejectionModalClose').focus();
            }

            function closeRejectionModal() {
                const modal = document.getElementById('rejectionModal');
                if (!modal) return;
                modal.classList.remove('is-visible');
                modal.setAttribute('aria-hidden', 'true');
            }

            function updateAppointmentRow(appointment) {
                const appointmentId = String(appointment.id);
                const status = String(appointment.status || '').toLowerCase();
                const statusCell = document.getElementById('status-cell-' + appointmentId);
                const reasonCell = document.getElementById('reason-cell-' + appointmentId);
                const dateCell = document.getElementById('date-cell-' + appointmentId);
                const timeCell = document.getElementById('time-cell-' + appointmentId);
                const serviceCell = document.getElementById('service-cell-' + appointmentId);
                const actionCell = document.getElementById('action-cell-' + appointmentId);
                const row = document.querySelector('.appointment-row[data-appt-id="' + appointmentId + '"]');

                if (statusCell) statusCell.innerHTML = buildStatusBadgeHTML(status);
                if (reasonCell) reasonCell.innerHTML = buildRejectionReasonHTML(status, appointment.rejection_reason || appointment.reason, appointment.registrar_notes || appointment.notes);
                if (dateCell) dateCell.innerHTML = '<strong>' + escapeHtml(formatDate(appointment.appointment_date)) + '</strong>';
                if (timeCell) timeCell.innerHTML = '<span>' + escapeHtml(formatTime(appointment.appointment_time)) + '</span>';
                if (serviceCell) serviceCell.textContent = appointment.service || '';
                if (actionCell && status !== 'pending') actionCell.innerHTML = '<span class="text-muted">-</span>';
                if (row) {
                    row.dataset.status = status;
                    row.classList.remove('row-flash-highlight');
                    void row.offsetWidth;
                    row.classList.add('row-flash-highlight');
                }
                filterRows();
            }

            function pollStudentStatus() {
                if (statusPollInProgress || document.visibilityState === 'hidden') return;
                statusPollInProgress = true;
                fetch('api_student_status.php')
                    .then(function (res) {
                        if (!res.ok) throw new Error('Status check failed with HTTP ' + res.status);
                        return res.json();
                    })
                    .then(function (data) {
                        if (!data || !data.success || !Array.isArray(data.appointments)) {
                            throw new Error(data && data.error ? data.error : 'Invalid status response.');
                        }

                        data.appointments.forEach(function (appt) {
                            const apptId = String(appt.id);
                            const newStatus = (appt.status || '').toLowerCase();
                            const oldStatus = knownStatuses[apptId];
                            updateAppointmentRow(appt);

                            if (oldStatus === undefined) {
                                // New appointment row appeared — update tracked status
                                knownStatuses[apptId] = newStatus;
                                return;
                            }

                            if (newStatus !== oldStatus) {
                                // Status changed — update the badge in-place
                                if (newStatus === 'approved' || newStatus === 'accepted' || newStatus === 'confirmed' || newStatus === 'rejected' || newStatus === 'declined') {
                                    showAppointmentDecisionPopup(newStatus, appt);
                                } else {
                                    showRealtimeToast('info', 'Appointment Updated',
                                        'Your appointment status changed to: ' + newStatus.charAt(0).toUpperCase() + newStatus.slice(1) + '.');
                                }

                                knownStatuses[apptId] = newStatus;
                            }
                        });
                    })
                    .catch(function (error) {
                        console.warn('Unable to refresh appointment statuses:', error);
                    })
                    .finally(function () {
                        statusPollInProgress = false;
                    });
            }

            // SSE delivers changes immediately; polling keeps the dashboard in sync if an event is missed.
            let statusPollInProgress = false;
            setInterval(pollStudentStatus, 5000);
            document.addEventListener('visibilitychange', function () {
                if (document.visibilityState === 'visible') pollStudentStatus();
            });

            let realtimeConnected = false;
            function connectRealtime() {
                const stream = new EventSource('api_realtime_events.php');
                stream.onopen = function () { realtimeConnected = true; };
                stream.addEventListener('appointment_updated', function (event) {
                    const realtimeEvent = JSON.parse(event.data);
                    const update = realtimeEvent.payload || {};
                    const appointmentId = String(realtimeEvent.appointment_id);
                    const status = (update.status || '').toLowerCase();
                    const previousStatus = knownStatuses[appointmentId];
                    const appointment = Object.assign({}, update, {
                        id: appointmentId,
                        status: update.status,
                        rejection_reason: update.reason,
                        registrar_notes: update.registrar_notes || update.notes
                    });
                    updateAppointmentRow(appointment);
                    const decisionStatus = ['approved', 'accepted', 'confirmed', 'rejected', 'declined'].includes(status);
                    if (status !== previousStatus && decisionStatus) {
                        showAppointmentDecisionPopup(status, appointment);
                    } else if (status !== previousStatus) {
                        showRealtimeToast('info', 'Appointment Updated', update.message || ('Your appointment is now ' + update.status + '.'));
                    }
                    knownStatuses[appointmentId] = status;
                    hydrateStudentAppointments();
                });
                stream.addEventListener('appointment_cancelled', function () { hydrateStudentAppointments(); });
                stream.onerror = function () {
                    if (realtimeConnected) AppPopup.show('Status checks will continue while the live connection reconnects.', 'info');
                    realtimeConnected = false;
                };
            }
            connectRealtime();
            pollStudentStatus();

            // Initial database rehydration: records survive reloads and new sessions.
            hydrateStudentAppointments();

            document.addEventListener('submit', function (event) {
                const form = event.target.closest('form[action="cancel_appointment.php"]');
                if (form) {
                    event.preventDefault();
                    AppPopup.confirm('Are you sure you want to cancel this appointment ticket?', {confirmLabel: 'Cancel appointment'}).then(confirmed => {
                        if (!confirmed) return;
                        fetch('cancel_appointment.php', { method: 'POST', body: new FormData(form), headers: { Accept: 'application/json' } })
                            .then(res => res.json()).then(data => { if (!data.success) throw new Error(data.message); showRealtimeToast('info', 'Appointment Cancelled', data.message); hydrateStudentAppointments(); })
                            .catch(err => showRealtimeToast('rejected', 'Cancellation failed', err.message || 'Please try again.'));
                    });
                }
            });
        });
    </script>
</body>
</html>
