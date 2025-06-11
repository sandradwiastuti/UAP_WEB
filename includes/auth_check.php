<?php
// Session buat template males ngetik ulang tinggal include awoakawok
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}