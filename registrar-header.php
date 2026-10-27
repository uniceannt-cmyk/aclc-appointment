<?php
$header_pages = [
    'dashboard' => ['label' => 'Dashboard', 'href' => 'admin.php?office=registrar'],
    'schedule' => ['label' => 'Schedule', 'href' => 'schedule-calendar.php'],
    'history' => ['label' => 'Past History', 'href' => 'appointment-history.php'],
];
$header_user_name = (string)($_SESSION['user_name'] ?? 'Staff');
$header_initials = strtoupper(substr(trim($header_user_name), 0, 2));
?>
<header class="registrar-page-header">
    <div class="registrar-header-copy">
        <p class="registrar-header-eyebrow"><?php echo htmlspecialchars($registrarHeaderEyebrow ?? 'Registrar Dashboard'); ?></p>
        <h1><?php echo htmlspecialchars($registrarHeaderTitle ?? 'Registrar Dashboard'); ?></h1>
        <?php if (!empty($registrarHeaderSubtitle)): ?>
            <p class="registrar-header-subtitle"><?php echo htmlspecialchars($registrarHeaderSubtitle); ?></p>
        <?php endif; ?>
    </div>
    <nav class="registrar-header-nav" aria-label="Registrar navigation">
        <?php foreach ($header_pages as $key => $page): ?>
            <a class="registrar-header-link<?php echo ($registrarHeaderPage ?? '') === $key ? ' active' : ''; ?>"
               href="<?php echo htmlspecialchars($page['href']); ?>"
               <?php echo ($registrarHeaderPage ?? '') === $key ? 'aria-current="page"' : ''; ?>>
                <?php echo htmlspecialchars($page['label']); ?>
            </a>
        <?php endforeach; ?>
        <a class="registrar-header-link" href="public-display.php" target="_blank" rel="noopener">Public Queue Display</a>
        <?php if (($registrarHeaderPage ?? '') === 'dashboard'): ?>
            <button type="button" class="registrar-header-action registrar-header-add-staff" id="openStaffModal">
                <i class="fas fa-user-plus" aria-hidden="true"></i> Add Staff
            </button>
        <?php endif; ?>
        <button type="button" class="registrar-header-icon" id="themeToggle" aria-label="Toggle dark mode" title="Toggle dark mode">
            <i class="fas fa-moon" aria-hidden="true"></i>
        </button>
        <?php if (($registrarHeaderPage ?? '') === 'dashboard'): ?>
            <button type="button" class="registrar-header-avatar" id="profileTrigger" aria-label="View profile">
                <?php echo htmlspecialchars($header_initials); ?>
            </button>
        <?php else: ?>
            <span class="registrar-header-avatar" aria-label="<?php echo htmlspecialchars($header_user_name); ?>" title="<?php echo htmlspecialchars($header_user_name); ?>">
                <?php echo htmlspecialchars($header_initials); ?>
            </span>
        <?php endif; ?>
        <a class="registrar-header-logout" href="logout.php"><i class="fas fa-sign-out-alt" aria-hidden="true"></i> Logout</a>
    </nav>
</header>
<?php if (($registrarHeaderPage ?? '') !== 'dashboard'): ?>
<script>
    (function () {
        var toggle = document.getElementById('themeToggle');
        if (!toggle) return;
        var savedTheme = localStorage.getItem('theme') || 'light';
        document.body.setAttribute('data-theme', savedTheme);
        var icon = toggle.querySelector('i');
        if (icon) {
            icon.classList.toggle('fa-sun', savedTheme === 'dark');
            icon.classList.toggle('fa-moon', savedTheme !== 'dark');
        }
        toggle.addEventListener('click', function () {
            var nextTheme = document.body.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            document.body.setAttribute('data-theme', nextTheme);
            localStorage.setItem('theme', nextTheme);
            if (icon) {
                icon.classList.toggle('fa-sun', nextTheme === 'dark');
                icon.classList.toggle('fa-moon', nextTheme !== 'dark');
            }
        });
    }());
</script>
<?php endif; ?>
