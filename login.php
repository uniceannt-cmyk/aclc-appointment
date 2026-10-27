<?php
session_start();
include("db.php");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $student_id = trim($_POST['student_id'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!empty($student_id) && !empty($password)) {
        $stmt = mysqli_prepare($con, "SELECT student_id, first_name, last_name, email, password, role, education_level FROM users WHERE student_id = ?");

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "s", $student_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);

            if ($user = mysqli_fetch_assoc($result)) {
                if (password_verify($password, $user['password'])) {
                    $_SESSION['user_id'] = $user['student_id'];
                    $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_role'] = $user['role'];
                    $_SESSION['education_level'] = $user['education_level'] ?: null;

                    if ($user['role'] === 'registrar') {
                        $redirect_url = 'admin.php?office=registrar';
                    } else {
                        $redirect_url = 'homepage.php';
                    }

                    echo popup_response_script('Login successful!', 'success', $redirect_url);
                    exit();
                } else {
                    echo popup_response_script('Invalid student number or password.', 'error', 'login.php');
                    exit();
                }
            } else {
                echo popup_response_script('Invalid student number or password.', 'error', 'login.php');
                exit();
            }

            mysqli_stmt_close($stmt);
        } else {
            echo popup_response_script('Database error occurred.', 'error', 'login.php');
            exit();
        }
    } else {
        echo popup_response_script('Please enter both student number and password.', 'error', 'login.php');
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log-In - ACLC Appointment System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="login.css?v=1.1">
    <link rel="stylesheet" href="popup.css">
    <script src="popup.js"></script>
</head>

<body>

    <div class="login">
        <div class="uppernames">
            <img src="img/image.png" alt="ACLC Logo" class="ama-logo" onerror="this.style.display='none'" style="width: 80px; height: 80px; object-fit: contain; margin-bottom: 10px;">
            <h2 class="stud-login">Student Log-In</h2>
            <p class="school-name">AMA Computer Learning Center College of Daet</p>
        </div>

        <form action="login.php" method="POST">
            <div class="input-container">
                <i class="fas fa-id-card"></i>
                <input type="text" name="student_id" placeholder="Enter your Student Number (USN)" value="<?php echo htmlspecialchars($student_id ?? '', ENT_QUOTES, 'UTF-8'); ?>" autocomplete="username" required>
            </div>

            <div class="input-container">
                <i class="fas fa-lock"></i>
                <input type="password" name="password" placeholder="Enter your Password" required>
            </div>

            <button type="submit">Login</button>
        </form>

        <p>Don't have an account? <a href="register.php">Sign-Up</a></p>
    </div>
</body>
</html>
