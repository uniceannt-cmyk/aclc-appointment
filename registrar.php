<?php
session_start();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
require_once("db.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
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

$current_role = $_SESSION['user_role'] ?? ($user['role'] ?? 'student');
$education_level = $user['education_level'] ?? ($_SESSION['education_level'] ?? null);
if (!in_array($education_level, ['SHS', 'College'], true)) {
  header('Location: homepage.php?choose_level=1');
  exit();
}


$services_query = mysqli_prepare($con, "SELECT service_name, estimated_duration_min, is_visible, available_from, available_until FROM services WHERE office_code = 'registrar' AND target_level IN ('Both', ?) AND is_visible = 1 AND (available_from IS NULL OR available_from <= NOW()) AND (available_until IS NULL OR available_until >= NOW()) ORDER BY id ASC");
$services = [];
if ($services_query) {
  mysqli_stmt_bind_param($services_query, 's', $education_level);
  mysqli_stmt_execute($services_query);
  $services_result = mysqli_stmt_get_result($services_query);
  while ($s = mysqli_fetch_assoc($services_result)) {
        $services[] = $s;
    }
  mysqli_stmt_close($services_query);
}
function student_service_state($service) {
    $now = time();
    if (empty($service['is_visible']) || (!empty($service['available_until']) && strtotime($service['available_until']) < $now)) return 'unavailable';
    if (!empty($service['available_from']) && strtotime($service['available_from']) > $now) return 'scheduled';
    return 'available';
}
if (empty($services)) {
    $services = [
        ['service_name' => 'Enrollment Verification', 'estimated_duration_min' => 15],
        ['service_name' => 'Transcript Request (TOR)', 'estimated_duration_min' => 20],
        ['service_name' => 'Student Records Update', 'estimated_duration_min' => 15],
        ['service_name' => 'Certificate of Enrollment (COE)', 'estimated_duration_min' => 10],
        ['service_name' => 'Certificate of Good Moral Character', 'estimated_duration_min' => 10],
        ['service_name' => 'Honorable Dismissal', 'estimated_duration_min' => 20],
    ];
    foreach ($services as &$fallback_service) {
        $fallback_service['is_visible'] = 1;
        $fallback_service['available_from'] = null;
        $fallback_service['available_until'] = null;
    }
    unset($fallback_service);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Registrar Office Appointment - ACLC</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="registrar.css">
  <link rel="stylesheet" href="popup.css?v=<?php echo filemtime(__DIR__ . '/popup.css'); ?>">
  <script src="popup.js?v=<?php echo filemtime(__DIR__ . '/popup.js'); ?>"></script>
</head>

<body>
  <header class="home-header">
    <div class="school-name">
      <img src="img/image.png" alt="ACLC Logo" class="ama-logo" onerror="this.style.display='none'">
      <div class="school-text">
        <h1>ACLC College of Daet Appointment</h1>
        <p class="school-info">2F Guinhawa Bldg. J. Lukban St. Daet, Camarines Norte, Daet, Philippines, 4600</p>
      </div>
    </div>

    <div class="header-user-actions">
      <a href="homepage.php" class="home-link"><i class="fas fa-home"></i> Home</a>
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

  <div class="profile-modal" id="profileModal" aria-hidden="true">
    <div class="profile-modal-content">
      <button type="button" class="close-profile" id="closeProfile" aria-label="Close profile">&times;</button>
      <div class="profile-modal-header">
        <img src="https://ui-avatars.com/api/?name=<?php echo rawurlencode($_SESSION['user_name'] ?? 'Student'); ?>&background=003366&color=fff&size=200" alt="Profile avatar">
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
          <strong><?php echo htmlspecialchars(ucfirst($current_role)); ?></strong>
        </div>
                <div>
                  <span>Education Level</span>
                  <strong><?php echo $education_level === 'SHS' ? 'Senior High School' : 'College'; ?></strong>
                </div>
      </div>
              <button type="button" class="change-level-button" onclick="window.location.href='homepage.php?change_level=1'">Change Level</button>
    </div>
  </div>

  <section class="booking">
    <div class="booking-heading">
      <p class="section-kicker">Plan your visit</p>
      <h2><i class="fas fa-calendar-days" aria-hidden="true"></i> Schedule an appointment</h2>
    </div>
    <div class="sched-notice">
      <i class="fas fa-circle-info" aria-hidden="true"></i>
      <strong>Registrar Schedule Control:</strong> Select your preferred day and time. The Registrar will review, assign, or adjust your official slot. Check your student portal for appointment updates.
    </div>
    <form method="post" action="book_appointment.php" id="bookingForm">
      <input type="hidden" name="educational_level" value="<?php echo htmlspecialchars($education_level, ENT_QUOTES, 'UTF-8'); ?>">
      <section class="academic-fields is-visible" id="academicFields" data-level="<?php echo htmlspecialchars($education_level, ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="false">
        <h3>Your academic details</h3>
        <p>These details help the Registrar locate your student record.</p>
        <div class="academic-fields-grid">
          <div class="academic-field" data-field-level="SHS">
            <label for="shsSection">SHS Section</label>
            <select id="shsSection" name="shs_section" required>
              <option value="">Select your SHS section</option>
              <option value="ASSH 1-1">ASSH 1-1</option>
              <option value="ASSH 1-2">ASSH 1-2</option>
              <option value="ASSH 1-3">ASSH 1-3</option>
              <option value="ASSH 1-4">ASSH 1-4</option>
              <option value="ASSH 1-5">ASSH 1-5</option>
              <option value="TechPro 1-1">TechPro 1-1</option>
              <option value="TechPro 1-2">TechPro 1-2</option>
              <option value="ABM 10-1">ABM 10-1</option>
              <option value="GAS 10-1">GAS 10-1</option>
              <option value="HUMSS 10-1">HUMSS 10-1</option>
              <option value="HUMSS 10-2">HUMSS 10-2</option>
              <option value="HUMSS 10-3">HUMSS 10-3</option>
              <option value="TVL 10-1">TVL 10-1</option>
              <option value="TVL 10-2">TVL 10-2</option>
            </select>
          </div>
          <div class="academic-field" data-field-level="College">
            <label for="collegeBlock">College Program / Section</label>
            <select id="collegeBlock" name="college_block" required>
              <option value="">Select your program and block</option>
              <option value="BSE">BSE</option>
              <option value="BE 1-1">BE 1-1</option>
              <option value="BE 1-2">BE 1-2</option>
              <option value="ACT 1-1">ACT 1-1</option>
              <option value="ACT 2-1">ACT 2-1</option>
              <option value="BSIT 1-1A">BSIT 1-1A</option>
              <option value="BSIT 1-1B">BSIT 1-1B</option>
              <option value="BSIT 2-1">BSIT 2-1</option>
              <option value="BSIT 3-1">BSIT 3-1</option>
              <option value="BSIT 4-1">BSIT 4-1</option>
            </select>
          </div>
        </div>
      </section>
      <label>Select Registrar Service(s): <span>Appointments are available from 8:00 AM to 4:00 PM, excluding the 11:45 AM–1:00 PM lunch break.</span></label>
      <div class="services-checkbox-group" id="servicesCheckboxGroup">
        <?php foreach ($services as $idx => $srv): ?>
          <label class="service-checkbox-item" for="svc_<?php echo $idx; ?>">
            <?php $service_state = student_service_state($srv); $service_disabled = $service_state === 'unavailable'; ?>
            <input type="checkbox" id="svc_<?php echo $idx; ?>" name="services[]" value="<?php echo htmlspecialchars($srv['service_name']); ?>" data-visible="<?php echo !empty($srv['is_visible']) ? '1' : '0'; ?>" data-from="<?php echo htmlspecialchars($srv['available_from'] ?? ''); ?>" data-until="<?php echo htmlspecialchars($srv['available_until'] ?? ''); ?>" <?php echo $service_disabled ? 'disabled' : ''; ?>>
            <span class="service-checkbox-label">
              <i class="fas fa-file-alt"></i>
              <?php echo htmlspecialchars($srv['service_name']); ?>
            </span>
            <span class="service-state-badge state-<?php echo $service_state; ?>" data-service-state><?php echo ucfirst($service_state); ?></span>
          </label>
        <?php endforeach; ?>
      </div>
      <div class="selected-services-summary" id="selectedServicesSummary" style="display:none;">
        <span><i class="fas fa-check-circle" style="color:#059669;"></i> Selected:</span>
        <div class="selected-services-pills" id="selectedServicesPills"></div>
      </div>

      <div class="week-calendar-toolbar"><button type="button" id="bookingPrevWeek">← Previous week</button><strong id="bookingWeekLabel"></strong><button type="button" id="bookingNextWeek">Next week →</button></div>
      <div class="booking-calendar-scroll"><div id="bookingWeeklyCalendar" class="booking-week-grid" aria-label="Weekly appointment availability"></div></div>
      <input type="hidden" name="date" id="date" value="<?php echo date('Y-m-d'); ?>">

      <input type="hidden" name="slot_id" id="selectedSlotId" value="">
      <input type="hidden" name="time" id="selectedTimeValue" value="" required>

     
      <div class="slots-picker-area" style="display:none">
        <div class="slots-picker-label">
          <span><i class="fas fa-clock" style="color: var(--accent-color);"></i> Select Open Time Slot:</span>
          <span id="slotsLoadingStatus" style="font-size: 0.8rem; color: var(--text-secondary);"></span>
        </div>
        <div id="slotsChipsGrid" class="slots-grid-chips">
      </div>


        <div id="noSlotsMessage" class="slots-empty-notice" style="display: none;">
          <strong><i class="fas fa-exclamation-triangle"></i> No Open Slots for this Date</strong>
          <p>The Registrar Office has not published availability for this day. Please choose another date or check back later.</p>
        </div>
      </div>

      <input type="hidden" name="office" value="registrar">
      <button type="submit" id="submitBookingBtn" disabled>Select at least one service and a time slot</button>
    </form>
  </section>

  <script>
    <?php
      $slot_event_cursor_result = mysqli_query($con, 'SELECT COALESCE(MAX(id), 0) AS id FROM realtime_events');
      if (!$slot_event_cursor_result) {
          error_log('Unable to initialize the slot event stream: ' . mysqli_error($con));
          $slot_event_cursor = 0;
      } else {
          $slot_event_cursor = (int)(mysqli_fetch_assoc($slot_event_cursor_result)['id'] ?? 0);
      }
    ?>
    document.addEventListener('DOMContentLoaded', function () {
      const academicFields = document.getElementById('academicFields');
      const academicFieldGroups = academicFields ? Array.from(academicFields.querySelectorAll('[data-field-level]')) : [];
      const bookingLevel = academicFields ? academicFields.dataset.level : '';

      function updateAcademicFields() {
        academicFieldGroups.forEach(function (group) {
          const isActive = group.dataset.fieldLevel === bookingLevel;
          group.hidden = !isActive;
          group.querySelectorAll('input, select').forEach(function (field) {
            field.disabled = !isActive;
            field.required = isActive;
          });
        });
        const sectionField = document.getElementById('section');
        if (sectionField) {
          sectionField.disabled = !['SHS', 'College'].includes(bookingLevel);
          sectionField.required = ['SHS', 'College'].includes(bookingLevel);
        }
      }
      updateAcademicFields();

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
          if (e.target === profileModal) {
            profileModal.classList.remove('show');
            profileModal.setAttribute('aria-hidden', 'true');
          }
        });
      }

      const dateInput = document.getElementById('date');
      const slotsGrid = document.getElementById('slotsChipsGrid');
      const noSlotsMsg = document.getElementById('noSlotsMessage');
      const statusSpan = document.getElementById('slotsLoadingStatus');
      const slotIdInput = document.getElementById('selectedSlotId');
      const timeValInput = document.getElementById('selectedTimeValue');
      const submitBtn = document.getElementById('submitBookingBtn');
      let slotValidationInProgress = false;
      let slotValidationVersion = 0;
      let slotListRenderVersion = 0;

      // -----------------------------------------------
      // Multi-service checkbox logic
      // -----------------------------------------------
      const checkboxGroup = document.getElementById('servicesCheckboxGroup');
      const summary = document.getElementById('selectedServicesSummary');
      const pillsContainer = document.getElementById('selectedServicesPills');
      let selectedServices = [];
      let slotSelected = false;

      function updateServiceSummary() {
        const checked = Array.from(checkboxGroup.querySelectorAll('input[type=checkbox]:checked'));
        selectedServices = checked.map(cb => cb.value);

        if (selectedServices.length > 0) {
          summary.style.display = 'flex';
          pillsContainer.replaceChildren();
          selectedServices.forEach(function (serviceName) { const pill = document.createElement('span'); pill.className = 'service-pill'; pill.textContent = serviceName; pillsContainer.appendChild(pill); });
        } else {
          summary.style.display = 'none';
          pillsContainer.innerHTML = '';
        }
        updateSubmitButton();
      }

      function updateSubmitButton() {
        if (selectedServices.length > 0 && slotSelected && !slotValidationInProgress) {
          submitBtn.disabled = false;
          const count = selectedServices.length;
          submitBtn.textContent = `Confirm Registrar Appointment (${count} service${count > 1 ? 's' : ''})`;
        } else if (selectedServices.length === 0) {
          submitBtn.disabled = true;
          submitBtn.textContent = 'Select at least one service and a time slot';
        } else {
          submitBtn.disabled = true;
          submitBtn.textContent = 'Select an Open Time Slot to Confirm';
        }
      }

      function refreshServiceAvailability() {
        const hasTargetSlot = slotSelected && dateInput.value && timeValInput.value;
        const target = hasTargetSlot ? new Date(dateInput.value + 'T' + timeValInput.value + ':00') : new Date();
        checkboxGroup.querySelectorAll('input[type=checkbox]').forEach(function (input) {
          const from = input.dataset.from ? new Date(input.dataset.from.replace(' ', 'T')) : null;
          const until = input.dataset.until ? new Date(input.dataset.until.replace(' ', 'T')) : null;
          const visible = input.dataset.visible === '1';
          const allowed = visible && (!until || target <= until) && (!hasTargetSlot || !from || target >= from);
          input.disabled = !allowed;
          if (!allowed && input.checked) input.checked = false;
          const badge = input.closest('.service-checkbox-item').querySelector('[data-service-state]');
          let state = !visible || (until && target > until) ? 'unavailable' : (from && target < from ? 'scheduled' : 'available');
          if (badge) { badge.textContent = state.charAt(0).toUpperCase() + state.slice(1); badge.className = 'service-state-badge state-' + state; }
          input.closest('.service-checkbox-item').classList.toggle('service-disabled', !allowed);
        });
        updateServiceSummary();
      }

      if (checkboxGroup) checkboxGroup.addEventListener('change', function (event) {
        if (!event.target.matches('input[type=checkbox]')) return;
        event.target.closest('.service-checkbox-item').classList.toggle('checked', event.target.checked);
        refreshServiceAvailability();
      });

      function renderLiveServices(services) {
        if (!checkboxGroup) return;
        const selectedNames = new Set(Array.from(checkboxGroup.querySelectorAll('input:checked')).map(input => input.value));
        checkboxGroup.replaceChildren();
        services.forEach(function (service) {
          const name = String(service.service_name || '');
          const state = !Number(service.is_visible) || (service.available_until && new Date(service.available_until.replace(' ', 'T')) < new Date()) ? 'unavailable' : (service.available_from && new Date(service.available_from.replace(' ', 'T')) > new Date() ? 'scheduled' : 'available');
          const index = 'live_' + String(service.id);
          const item = document.createElement('label'); item.className = 'service-checkbox-item'; item.htmlFor = 'svc_' + index;
          const input = document.createElement('input'); input.type = 'checkbox'; input.id = 'svc_' + index; input.name = 'services[]'; input.value = name;
          input.dataset.visible = String(Number(service.is_visible)); input.dataset.from = service.available_from || ''; input.dataset.until = service.available_until || '';
          input.checked = selectedNames.has(name);
          const label = document.createElement('span'); label.className = 'service-checkbox-label';
          const icon = document.createElement('i'); icon.className = 'fas fa-file-alt'; label.append(icon, document.createTextNode(' ' + name));
          const badge = document.createElement('span'); badge.className = 'service-state-badge state-' + state; badge.dataset.serviceState = ''; badge.textContent = state.charAt(0).toUpperCase() + state.slice(1);
          item.append(input, label, badge); checkboxGroup.appendChild(item);
        });
        refreshServiceAvailability();
      }

      function loadSlotsForDate(selectedDate, preserveSelection) {
        if (!selectedDate) return;

        const renderVersion = ++slotListRenderVersion;
        const previousSlotId = preserveSelection && dateInput.value === selectedDate && slotSelected ? slotIdInput.value : '';
        const previousTime = timeValInput.value;
        statusSpan.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Checking open slots...';
        slotsGrid.innerHTML = '';
        noSlotsMsg.style.display = 'none';
        if (!preserveSelection) {
          slotValidationVersion++;
          slotIdInput.value = '';
          timeValInput.value = '';
          slotSelected = false;
        }
        updateSubmitButton();

        fetch(`api_slots.php?office=registrar&date=${encodeURIComponent(selectedDate)}`)
          .then(res => { if (!res.ok) throw new Error('Unable to load slot availability.'); return res.json(); })
          .then(data => {
            if (renderVersion !== slotListRenderVersion) return;
            statusSpan.textContent = '';
            if (data.success && data.slots && data.slots.length > 0) {
              let availableCount = 0;
              let selectedSlotStillAvailable = !previousSlotId;

              data.slots.forEach(slot => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'slot-btn-chip';
                btn.dataset.slotId = slot.id;
                btn.dataset.time = slot.start_time;
                const isSelectedSlot = String(slot.id) === String(previousSlotId) && slot.start_time === previousTime;

                if (!slot.is_available) {
                  btn.disabled = true;
                  btn.classList.add('slot-full');
                  btn.innerHTML = `
                    <span>${slot.label}</span>
                    <span class="slot-seats-badge">${slot.status === 'closed' ? 'CLOSED' : 'FULL'}</span>
                  `;
                } else {
                  availableCount++;
                  if (isSelectedSlot) {
                    btn.classList.add('selected');
                    selectedSlotStillAvailable = true;
                  }
                  btn.innerHTML = `
                    <span>${slot.label}</span>
                    <span class="slot-seats-badge">${slot.seats_remaining} seat${slot.seats_remaining > 1 ? 's' : ''} left</span>
                  `;

                  btn.addEventListener('click', async function () {
                    document.querySelectorAll('.slot-btn-chip').forEach(c => c.classList.remove('selected'));
                    btn.classList.add('selected');
                    slotIdInput.value = slot.id;
                    timeValInput.value = slot.start_time;
                    slotSelected = true;
                    slotValidationInProgress = true;
                    const validatingSlotId = String(slot.id);
                    updateSubmitButton();
                    const available = await verifySelectedSlot(true);
                    if (slotIdInput.value !== validatingSlotId) return;
                    slotValidationInProgress = false;
                    if (available) refreshServiceAvailability();
                    drawWeeklyCalendar(true);
                    updateSubmitButton();
                  });
                }

                slotsGrid.appendChild(btn);
              });

              if (availableCount === 0) {
                noSlotsMsg.style.display = 'block';
                noSlotsMsg.querySelector('p').textContent = 'All published slots for this date are currently full or closed. Please select another date.';
              }
              if (previousSlotId && !selectedSlotStillAvailable) clearSelectedSlot('Sorry, this time slot was recently removed or fully booked. Please select another slot.');
            } else {
              noSlotsMsg.style.display = 'block';
              if (previousSlotId) clearSelectedSlot('Sorry, this time slot was recently removed or fully booked. Please select another slot.');
            }
          })
          .catch(() => {
            if (renderVersion !== slotListRenderVersion) return;
            statusSpan.textContent = 'Error checking slots.';
            noSlotsMsg.style.display = 'block';
          });
      }

      const weeklyGrid = document.getElementById('bookingWeeklyCalendar');
      const weekLabel = document.getElementById('bookingWeekLabel');
      let calendarRenderVersion = 0;
      let visibleWeek = new Date(dateInput.value + 'T12:00:00');
      visibleWeek.setDate(visibleWeek.getDate() - ((visibleWeek.getDay() + 6) % 7));
      function isoDay(date) { return date.getFullYear() + '-' + String(date.getMonth()+1).padStart(2,'0') + '-' + String(date.getDate()).padStart(2,'0'); }
      function clearSelectedSlot(message) {
        const hadSelection = slotSelected || Boolean(slotIdInput.value);
        slotValidationVersion++;
        slotSelected = false;
        slotValidationInProgress = false;
        slotIdInput.value = '';
        timeValInput.value = '';
        weeklyGrid.querySelectorAll('.booking-grid-slot.is-selected').forEach(button => button.classList.remove('is-selected'));
        if (message && hadSelection && window.AppPopup) AppPopup.show(message, 'warning');
        refreshServiceAvailability();
        updateSubmitButton();
      }

      async function verifySelectedSlot(showStaleMessage) {
        if (!slotSelected || !slotIdInput.value || !dateInput.value || !timeValInput.value) return false;
        const validationVersion = ++slotValidationVersion;
        const selectedSlotId = slotIdInput.value;
        const selectedDate = dateInput.value;
        const selectedTime = timeValInput.value;
        try {
          const response = await fetch('api_slots.php?office=registrar&date=' + encodeURIComponent(selectedDate), { cache: 'no-store' });
          if (!response.ok) throw new Error('Unable to verify slot availability.');
          const data = await response.json();
          if (validationVersion !== slotValidationVersion) return false;
          if (!slotSelected || slotIdInput.value !== selectedSlotId || dateInput.value !== selectedDate || timeValInput.value !== selectedTime) return false;
          const slot = (data.slots || []).find(item => String(item.id) === String(selectedSlotId));
          const now = new Date();
          const currentTime = String(now.getHours()).padStart(2, '0') + ':' + String(now.getMinutes()).padStart(2, '0');
          const isFuture = selectedDate > isoDay(now) || (selectedDate === isoDay(now) && selectedTime > currentTime);
          if (!slot || !slot.is_available || slot.start_time !== selectedTime || !isFuture) {
            clearSelectedSlot(showStaleMessage ? 'Sorry, this time slot was recently removed or fully booked. Please select another slot.' : '');
            drawWeeklyCalendar(true);
            return false;
          }
          return true;
        } catch (error) {
          if (validationVersion !== slotValidationVersion) return false;
          if (showStaleMessage && window.AppPopup) AppPopup.show('Unable to verify this time slot. Please try selecting it again.', 'error');
          return false;
        }
      }

      function drawWeeklyCalendar(preserveSelection) {
        if (!weeklyGrid) return;
        const renderVersion = ++calendarRenderVersion;
        if (!preserveSelection) { slotSelected = false; slotIdInput.value = ''; timeValInput.value = ''; refreshServiceAvailability(); }
        const dates = Array.from({length:5}, (_,i) => { const d=new Date(visibleWeek); d.setDate(d.getDate()+i); return d; });
        weekLabel.textContent = dates[0].toLocaleDateString(undefined,{month:'short',day:'numeric'}) + ' – ' + dates[4].toLocaleDateString(undefined,{month:'short',day:'numeric',year:'numeric'});
        weeklyGrid.innerHTML = '<div class="booking-grid-header">Time</div>' + dates.map(d=>'<div class="booking-grid-header">'+d.toLocaleDateString(undefined,{weekday:'short',month:'numeric',day:'numeric'})+'</div>').join('');
        Promise.all(dates.map(d=>fetch('api_slots.php?office=registrar&date='+encodeURIComponent(isoDay(d)),{cache:'no-store'}).then(r=>{if(!r.ok)throw new Error('Availability request failed');return r.json();}).catch(()=>({success:false,slots:[]})))).then(results=>{
          if (renderVersion !== calendarRenderVersion) return;
          if(results.some(result=>!result.success||!Array.isArray(result.slots))){
            weeklyGrid.innerHTML='<div class="booking-grid-empty" style="grid-column:1 / -1">Availability could not be refreshed. Please try again.</div>';
            return;
          }
          results.forEach((result,index)=>{
            const header=weeklyGrid.querySelectorAll('.booking-grid-header')[index+1];
            if(header && result.day_capacity !== null && result.day_capacity !== undefined){
              header.textContent += result.day_seats_remaining > 0 ? ' · '+result.day_seats_remaining+' daily seats' : ' · Daily limit reached';
              if(result.day_seats_remaining === 0)header.classList.add('booking-day-full');
            }
          });
          const byTime=results.map(result=>{const map={};(result.slots||[]).forEach(slot=>map[slot.start_time]=slot);return map;});
          if (preserveSelection && slotSelected) {
            const now = new Date();
            const stillOpen = results.some((result,index) => (result.slots||[]).some(slot => String(slot.id) === String(slotIdInput.value) && slot.is_available && (isoDay(dates[index]) > isoDay(now) || (isoDay(dates[index]) === isoDay(now) && slot.start_time > String(now.getHours()).padStart(2,'0')+':'+String(now.getMinutes()).padStart(2,'0')))));
            if (!stillOpen) clearSelectedSlot('Sorry, this time slot was recently removed or fully booked. Please select another slot.');
          }
          const coveredUntil=dates.map(()=>0);
          for(let minute=480,rowIndex=0;minute<=960;minute+=15,rowIndex++){
            const hh=String(Math.floor(minute/60)).padStart(2,'0'),mm=String(minute%60).padStart(2,'0'),time=hh+':'+mm;
            const shown=new Date(2000,0,1,Math.floor(minute/60),minute%60).toLocaleTimeString('en-US',{hour:'numeric',minute:'2-digit',hour12:true});
            const label=document.createElement('div');label.className='booking-grid-time';label.textContent=shown;label.style.gridColumn='1';label.style.gridRow=String(rowIndex+2);weeklyGrid.appendChild(label);
            dates.forEach((day,index)=>{
              if(rowIndex<coveredUntil[index])return;
              const slot=byTime[index][time];
              if(slot){
                const now=new Date();const today=isoDay(now);const currentTime=String(now.getHours()).padStart(2,'0')+':'+String(now.getMinutes()).padStart(2,'0');const inFuture=isoDay(day)>today||(isoDay(day)===today&&slot.start_time>currentTime);const open=slot.is_available&&inFuture;
                const cell=document.createElement('button');cell.type='button';cell.className='booking-grid-slot '+(open?'is-open':'is-closed')+(slotSelected&&String(slot.id)===String(slotIdInput.value)?' is-selected':'');cell.disabled=!open;
                const duration=Math.max(15,((Number(slot.end_time.slice(0,2))*60+Number(slot.end_time.slice(3,5)))-minute));const span=Math.max(1,Math.ceil(duration/15))+(slot.end_time==='16:00'?1:0);coveredUntil[index]=rowIndex+span;cell.style.gridColumn=String(index+2);cell.style.gridRow=(rowIndex+2)+' / span '+span;
                cell.textContent=open?slot.label+' · '+slot.seats_remaining+' open':(!inFuture?'Past':(slot.status==='closed'?'Closed':'Full'));
                cell.title=slot.label+' ('+slot.status+')';
                if(open)cell.addEventListener('click',async function(){
                  weeklyGrid.querySelectorAll('.booking-grid-slot').forEach(b=>b.classList.remove('is-selected'));
                  cell.classList.add('is-selected');
                  dateInput.value=isoDay(day);
                  slotIdInput.value=slot.id;
                  timeValInput.value=slot.start_time;
                  slotSelected=true;
                  slotValidationInProgress=true;
                  const validatingSlotId=String(slot.id);
                  loadSlotsForDate(dateInput.value, true);
                  updateSubmitButton();
                  const available=await verifySelectedSlot(true);
                  if(slotIdInput.value!==validatingSlotId)return;
                  slotValidationInProgress=false;
                  if(available){refreshServiceAvailability();updateSubmitButton();}
                });
                weeklyGrid.appendChild(cell);
              }else{const empty=document.createElement('div');empty.className='booking-grid-empty';empty.textContent=(minute>=705&&minute<780)?'Lunch':'—';empty.style.gridColumn=String(index+2);empty.style.gridRow=String(rowIndex+2);weeklyGrid.appendChild(empty);}
            });
          }
        });
      }
      document.getElementById('bookingPrevWeek').addEventListener('click',function(){visibleWeek.setDate(visibleWeek.getDate()-7);drawWeeklyCalendar();});
      document.getElementById('bookingNextWeek').addEventListener('click',function(){visibleWeek.setDate(visibleWeek.getDate()+7);drawWeeklyCalendar();});
      drawWeeklyCalendar();

      if (dateInput) {
        dateInput.addEventListener('change', function () {
          loadSlotsForDate(this.value);
        });

       
        loadSlotsForDate(dateInput.value);
      }

      const bookingForm = document.getElementById('bookingForm');
      if (bookingForm) {
        bookingForm.addEventListener('submit', function (event) {
          event.preventDefault();
          if (!slotSelected || slotValidationInProgress) return;
          submitBtn.disabled = true;
          submitBtn.textContent = 'Submitting appointment...';
          verifySelectedSlot(true)
            .then(function (available) {
              if (!available) {
                updateSubmitButton();
                return null;
              }
              return fetch('book_appointment.php', { method: 'POST', body: new FormData(bookingForm), headers: { Accept: 'application/json' } });
            })
            .then(res => res ? res.json() : null)
            .then(data => {
              if (!data) return;
              if (!data.success) throw new Error(data.message || 'Unable to book appointment.');
              AppPopup.show(data.message, 'success');
              bookingForm.reset();
              updateAcademicFields();
              selectedServices = []; slotSelected = false;
              drawWeeklyCalendar();
              loadSlotsForDate(dateInput.value);
              updateSubmitButton();
            })
            .catch(err => {
              AppPopup.show(err.message || 'Unable to book appointment. Please try again.', 'error');
              if (String(err.message || '').toLowerCase().includes('slot')) {
                clearSelectedSlot();
                drawWeeklyCalendar(true);
              }
              updateSubmitButton();
            });
        });
      }

      <?php if ($current_role === 'student'): ?>
      function refreshSlotsForDate(date) {
        if (document.hidden) return;
        if (date === dateInput.value) loadSlotsForDate(date, true);
        const visibleDates = Array.from({length: 5}, (_, index) => {
          const day = new Date(visibleWeek);
          day.setDate(day.getDate() + index);
          return isoDay(day);
        });
        if (visibleDates.includes(date)) drawWeeklyCalendar(true);
      }

      document.addEventListener('visibilitychange', function () {
        if (!document.hidden) {
          loadSlotsForDate(dateInput.value, true);
          drawWeeklyCalendar(true);
        }
      });

      let appointmentNoticeQueue = Promise.resolve();
      function formatAppointmentTime(value) {
        const match = String(value || '').trim().match(/^(\d{1,2}):(\d{2})(?::\d{2})?$/);
        if (!match) return String(value || '');
        const hour = Number(match[1]);
        const period = hour >= 12 ? 'PM' : 'AM';
        return ((hour + 11) % 12 + 1) + ':' + match[2] + ' ' + period;
      }

      function showAppointmentNotice(event) {
        let eventData;
        try {
          eventData = JSON.parse(event.data);
        } catch (error) {
          console.error('Unable to read appointment update notification:', error);
          return;
        }

        const payload = eventData.payload || {};
        const status = String(payload.status || (event.type === 'appointment_cancelled' ? 'Cancelled' : 'Updated'));
        const normalizedStatus = status.toLowerCase();
        const title = normalizedStatus === 'approved' ? 'Appointment Approved'
          : normalizedStatus === 'rejected' ? 'Appointment Rejected'
          : normalizedStatus === 'cancelled' ? 'Appointment Cancelled'
          : 'Appointment Updated';
        const type = normalizedStatus === 'approved' ? 'success'
          : (normalizedStatus === 'rejected' || normalizedStatus === 'cancelled') ? 'warning'
          : 'info';
        const details = [];
        if (payload.service) details.push('Service: ' + payload.service + '.');
        if (payload.appointment_date) {
          const date = new Date(payload.appointment_date + 'T00:00:00');
          const formattedDate = Number.isNaN(date.getTime())
            ? payload.appointment_date
            : date.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
          details.push('Schedule: ' + formattedDate + (payload.appointment_time ? ' at ' + formatAppointmentTime(payload.appointment_time) : '') + '.');
        }
        if (payload.message) details.push(payload.message);
        else if (!details.length) details.push('Your appointment status is now ' + status + '.');

        appointmentNoticeQueue = appointmentNoticeQueue.then(function () {
          return AppPopup.alert(details.join(' '), { title: title, type: type });
        }).catch(function (error) {
          console.error('Unable to show appointment update notification:', error);
        });
      }

      const appointmentUpdates = new EventSource('api_realtime_events.php?last_event_id=<?php echo $slot_event_cursor; ?>');
      appointmentUpdates.addEventListener('slots_changed', function (event) {
        try {
          const eventData = JSON.parse(event.data);
          const changedDate = String(eventData.payload && eventData.payload.date || '');
          if (changedDate) refreshSlotsForDate(changedDate);
        } catch (error) {
          console.error('Unable to read slot availability update:', error);
        }
      });
      appointmentUpdates.addEventListener('appointment_updated', showAppointmentNotice);
      appointmentUpdates.addEventListener('appointment_cancelled', showAppointmentNotice);
      <?php endif; ?>
    });
  </script>
</body>

</html>
