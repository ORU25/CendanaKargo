<?php
    session_start();
    include '../config/database.php';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $username = $_POST['username'];
        $password = $_POST['password'];

        // Hash password sebelum disimpan
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);

        // Simpan user baru ke database dengan role superadmin
        $stmt = $conn->prepare("INSERT INTO user(username, password, role) VALUES (?, ?, 'superadmin')");
        try {
            $stmt->execute([$username, $hashed_password]);
            echo "User superadmin berhasil dibuat. <a href='login.php'>Login di sini</a>.";
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) { // Kode error untuk duplicate entry
                echo "Username sudah ada. Silakan pilih username lain.";
            } else {
                echo "Error: " . $e->getMessage();
            }
        }
    }
?>

<form action="singup.php" method="POST">
    <label for="username">Username: </label>
    <input type="text" name="username" id="username" required value="superadmin">
    <label for="password">Password: </label>
    <input type="password" name="password" id="password" required value="superadmin123">
    <button type="submit">Sign Up</button>
</form>