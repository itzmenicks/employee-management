<?php
// auth_employee.php - Employee pages ke liye
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
if (!isset($_SESSION['employee_id'])) {
    header('Location: login.php');
    exit();
}
?>
