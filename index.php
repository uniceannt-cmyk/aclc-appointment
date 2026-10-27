<?php
require_once __DIR__ . '/db.php';

$services = [];
$services_result = mysqli_query(
    $con,
    "SELECT service_name
     FROM services
     WHERE office_code = 'registrar'
       AND is_visible = 1
       AND (available_from IS NULL OR available_from <= NOW())
       AND (available_until IS NULL OR available_until >= NOW())
     ORDER BY id ASC"
);

if ($services_result) {
    while ($service = mysqli_fetch_assoc($services_result)) {
        $services[] = $service;
    }
} else {
    error_log('Unable to load public registrar services: ' . mysqli_error($con));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#082b54">
    <title>ACLC College of Daet | Campus Appointments</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="index.css">
</head>
<body>
    <header class="site-header" id="home">
        <a class="brand" href="index.php" aria-label="ACLC College of Daet home">
            <img src="img/image.png" alt="AMA logo">
            <span class="brand-copy">
                <strong>ACLC College of Daet</strong>
                <small>Campus Appointment Portal</small>
            </span>
        </a>
        <nav class="main-nav" aria-label="Main navigation">
            <a class="nav-link" href="#home">Home</a>
            <a class="nav-link" href="#about">About</a>
            <a class="nav-link" href="#services">Services</a>
            <a class="nav-login" href="login.php">Login <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
        </nav>
    </header>

    <main>
        <section class="hero" aria-labelledby="hero-title">
            <div class="hero-content">
                <p class="eyebrow"><span></span> ACLC College of Daet</p>
                <h1 id="hero-title">Campus appointments,<br>made simple.</h1>
                <p class="hero-copy">Book a Registrar visit, choose a schedule, and keep your appointment details in one place.</p>
                <div class="hero-actions">
                    <a class="button button-primary" href="login.php">Continue to student portal <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
                    <a class="button button-quiet" href="#how-it-works">See how it works</a>
                </div>
            </div>
            <a class="hero-caption" href="#about"><span>01</span> Student services, in step with you <i class="fa-solid fa-arrow-down" aria-hidden="true"></i></a>
        </section>

        <section class="about-section" id="about" aria-labelledby="about-title">
            <div class="section-heading">
                <p class="section-kicker">A simpler campus visit</p>
                <h2 id="about-title">Your appointment, organized.</h2>
                <p>Plan your Registrar transaction before you arrive on campus.</p>
            </div>
            <div class="feature-list">
                <article class="feature">
                    <span class="feature-icon"><i class="fa-regular fa-calendar-check" aria-hidden="true"></i></span>
                    <div>
                        <h3>Book online</h3>
                        <p>Send an appointment request without having to visit the office just to reserve a schedule.</p>
                    </div>
                </article>
                <article class="feature">
                    <span class="feature-icon"><i class="fa-regular fa-clock" aria-hidden="true"></i></span>
                    <div>
                        <h3>Choose a schedule</h3>
                        <p>Review available dates and times, then select the slot that works for your visit.</p>
                    </div>
                </article>
                <article class="feature">
                    <span class="feature-icon"><i class="fa-solid fa-list-check" aria-hidden="true"></i></span>
                    <div>
                        <h3>Track your request</h3>
                        <p>Check appointment status and keep your queue details close at hand.</p>
                    </div>
                </article>
            </div>
        </section>

        <section class="services-section" id="services" aria-labelledby="services-title">
            <div class="section-heading">
                <p class="section-kicker">Registrar office</p>
                <h2 id="services-title">Available services</h2>
                <p>Choose from the services currently offered by the Registrar.</p>
            </div>
            <?php if ($services): ?>
                <ul class="services-list">
                    <?php foreach ($services as $service): ?>
                        <li class="service-item">
                            <span class="service-icon" aria-hidden="true"><i class="fa-regular fa-file-lines"></i></span>
                            <span><?php echo htmlspecialchars($service['service_name']); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="services-empty">There are no services available right now. Please check back later.</p>
            <?php endif; ?>
            <a class="services-cta" href="login.php">Sign in to book a service <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
        </section>

        <section class="steps-section" id="how-it-works" aria-labelledby="steps-title">
            <div class="steps-heading">
                <p class="section-kicker">Four quick steps</p>
                <h2 id="steps-title">From sign-in to scheduled.</h2>
            </div>
            <ol class="steps-list">
                <li><span class="step-number">01</span><div><h3>Create an account</h3><p>Register your student details.</p></div></li>
                <li><span class="step-number">02</span><div><h3>Choose a service</h3><p>Select the Registrar service you need.</p></div></li>
                <li><span class="step-number">03</span><div><h3>Pick a time</h3><p>Choose an available date and slot.</p></div></li>
                <li><span class="step-number">04</span><div><h3>Confirm your visit</h3><p>Submit and follow your request.</p></div></li>
            </ol>
            <a class="steps-login" href="login.php">Go to the student portal <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
        </section>
    </main>

    <footer class="site-footer">
        <span>ACLC College of Daet</span>
        <span>2F Guinhawa Bldg., J. Lukban St., Daet, Camarines Norte</span>
        <a href="admin-login.php">Staff login</a>
    </footer>
</body>
</html>