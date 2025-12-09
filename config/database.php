<?php 
// Set timezone ke WITA (GMT+8) untuk semua operasi tanggal/waktu
date_default_timezone_set('Asia/Makassar');

$host = "localhost";
$user = "root";
$pass = "";
$db = "db_clk";

$conn = mysqli_connect($host, $user, $pass, $db);
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Set timezone MySQL ke WITA (GMT+8)
mysqli_query($conn, "SET time_zone = '+08:00'");