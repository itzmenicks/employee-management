<?php
// auth.php - Har protected page me include karo
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
?>
