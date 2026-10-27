<?php
session_start();
$role = $_SESSION['user_role'] ?? '';
session_unset();
session_destroy();

if ($role === 'registrar') {
    header("Location: admin-login.php");
} else {
    header("Location: login.php");
}
exit();
?>
