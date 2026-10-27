<?php
session_start();
include("db.php");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!empty($email) && !empty($password)) {
        $stmt = mysqli_prepare($con, "SELECT student_id, first_name, last_name, email, password, role FROM users WHERE email = ? AND role = 'registrar'");

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "s", $email);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);

            if ($user = mysqli_fetch_assoc($result)) {
                if (password_verify($password, $user['password'])) {
                    $_SESSION['user_id'] = $user['student_id'];
                    $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_role'] = $user['role'];

                    $return_to = $_SESSION['registrar_return_to'] ?? '';
                    unset($_SESSION['registrar_return_to']);
                    $allowed_return_pages = ['schedule-calendar.php', 'appointment-history.php'];
                    $redirect = in_array($return_to, $allowed_return_pages, true) ? $return_to : 'admin.php?office=registrar';
                    header('Location: ' . $redirect);
                    exit();
                }
            }

            echo popup_response_script('Invalid staff or admin credentials.', 'error', 'admin-login.php');
            exit();
        }
    } else {
        echo popup_response_script('Please enter both email and password.', 'error', 'admin-login.php');
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff & Admin Portal - ACLC</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin-login.css?v=8">
    <link rel="stylesheet" href="popup.css">
    <script src="popup.js"></script>
</head>
<body>
    <div class="background-collage" aria-hidden="true">
        <img src="img/aclc1.jpg" alt="">
        <img src="img/aclc2.jpg" alt="">
        <img src="img/aclc3.jpg" alt="">
        <img src="img/aclc4.jpg" alt="">
        <img src="img/aclc6.jpg" alt="">
        <img src="img/aclc7.jpg" alt="">
        <img src="img/aclc8.jpg" alt="">
        <img src="img/aclc9.jpg" alt="">
        <img src="img/aclc1.jpg" alt="">
    </div>
    <div class="box">
        <div class="admin-header">
            <img src="img/image.png?v=2" alt="ACLC Logo" class="ama-logo" onerror="this.style.display='none'">
            <h2>ACLC Admin Login</h2>
            <p class="admin-subtitle">AMA Computer Learning Center College of Daet</p>
        </div>

        <form method="POST" action="admin-login.php">
            <div class="input-group">
                <i class="fa fa-envelope"></i>
                <input type="email" name="email" placeholder="Staff / Admin Email" required>
            </div>
            
            <div class="input-group">
                <i class="fa fa-lock"></i>
                <input type="password" name="password" placeholder="Password" required>
            </div>

            <button type="submit">Sign In to Dashboard</button>
        </form>

        <div class="admin-footer-links">
            <a href="login.php" class="link-student">&larr; Return to Student Portal</a>
            <a href="public-display.php" target="_blank" rel="noopener" class="link-serving"><i class="fas fa-tv"></i> View Public Now Serving Screen</a>
        </div>
    </div>
</body>
</html>
