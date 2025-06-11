<?php
session_start();

// Hapus semua variabel session
$_SESSION = [];

// Hancurkan session
session_destroy();

// Arahkan ke halaman login
header("Location: login.php");
exit();