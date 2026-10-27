<?php
session_start();
require_once("db.php");

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $student_id = trim($_POST['student_id'] ?? '');
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($student_id === '' || $first_name === '' || $last_name === '' || $email === '' || $password === '' || $phone === '') {
        echo popup_response_script('Please fill out all required fields including mobile number.');
    } else {
        $email = filter_var($email, FILTER_SANITIZE_EMAIL);
        $norm_phone = $phone;
        if (substr($phone, 0, 4) === '+639') {
            $norm_phone = '0' . substr($phone, 3);
        }
        $valid_phone = preg_match('/^(09[0-9]{9}|\+639[0-9]{9})$/', $phone) === 1;

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo popup_response_script('Please enter a valid email address.');
        } elseif (!$valid_phone || $norm_phone === '') {
            echo popup_response_script('Enter 11 digits beginning with 09 or +639 followed by 9 digits.');
        } else {
            $check_stmt = mysqli_prepare($con, "SELECT student_id FROM users WHERE student_id = ? OR email = ?");
            if ($check_stmt === false) {
                echo popup_response_script('Database prepare failed: ' . mysqli_error($con));
            } else {
                mysqli_stmt_bind_param($check_stmt, 'ss', $student_id, $email);
                mysqli_stmt_execute($check_stmt);
                mysqli_stmt_store_result($check_stmt);

                if (mysqli_stmt_num_rows($check_stmt) > 0) {
                    echo popup_response_script('Student ID or email is already registered!', 'error', 'register.php');
                } else {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $insert_stmt = mysqli_prepare($con, "INSERT INTO users (student_id, first_name, last_name, email, phone, password, role) VALUES (?, ?, ?, ?, ?, ?, 'student')");
                    if ($insert_stmt === false) {
                        echo popup_response_script('Insert prepare failed: ' . mysqli_error($con));
                    } else {
                        mysqli_stmt_bind_param($insert_stmt, 'ssssss', $student_id, $first_name, $last_name, $email, $norm_phone, $hashed_password);
                        if (mysqli_stmt_execute($insert_stmt)) {
                            echo popup_response_script('Account created successfully. You can now log in.', 'success', 'login.php');
                            exit();
                        }
                        echo popup_response_script('Database Error: ' . mysqli_error($con));
                        mysqli_stmt_close($insert_stmt);
                    }
                }
                mysqli_stmt_close($check_stmt);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - ACLC Appointment System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="register.css?v=1.1">
    <link rel="stylesheet" href="popup.css">
    <script src="popup.js"></script>
</head>
<body>
    <div class="register">
        <div class="brand-header">
            <img src="img/image.png" alt="ACLC Logo" class="ama-logo" onerror="this.style.display='none'" style="width: 80px; height: 80px; object-fit: contain; margin-bottom: 10px;">
            <h2>Student Sign Up</h2>
            <p class="subtitle">AMA Computer Learning Center College of Daet</p>
        </div>

        <form action="register.php" method="POST">
            <div class="input-container">
                <i class="fas fa-id-card"></i>
                <input type="text" name="student_id" placeholder="Student ID (e.g. 24001321100)" required>
            </div>
            <div class="input-row">
                <div class="input-container">
                    <i class="fas fa-user"></i>
                    <input type="text" name="first_name" placeholder="First Name" required>
                </div>
                <div class="input-container">
                    <i class="fas fa-user"></i>
                    <input type="text" name="last_name" placeholder="Last Name" required>
                </div>
            </div>
            <div class="input-container">
                <i class="fas fa-envelope"></i>
                <input type="email" name="email" placeholder="Email Address" required>
            </div>
            <div class="input-container">
                <i class="fas fa-phone"></i>
                <input type="tel" name="phone" placeholder="09123456789 or +639123456789" pattern="(09[0-9]{9}|\+639[0-9]{9})" maxlength="13" inputmode="tel" autocomplete="tel" title="Use 11 digits starting with 09 or +639 followed by 9 digits" required>
            </div>
            <div class="input-container">
                <i class="fas fa-lock"></i>
                <input type="password" id="password" name="password" placeholder="Create a Password" required>
            </div>
            <button type="submit">Create Account</button>
        </form>

        <p>Already have an account? <a href="login.php">Log In</a></p>
    </div>
<script>
document.querySelectorAll('input[name="phone"]').forEach(function(input){
  input.addEventListener('input', function(){
    this.value = this.value.replace(/[^0-9+]/g, '').replace(/(?!^)\+/g, '').slice(0, 13);
  });
});
</script>
</body>
</html>
