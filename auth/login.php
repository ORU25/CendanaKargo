<?php
session_start();
include '../config/database.php';

if (isset($_SESSION['user_id'])) {
    // Kalau role superSuperAdmin
    if ($_SESSION['role'] == 'superSuperAdmin') {
        header("Location: ../dashboard/superSuperAdmin/?already_logined");
        exit;
    }
    // Kalau role superAdmin
    elseif ($_SESSION['role'] == 'superAdmin') {
        header("Location: ../dashboard/superAdmin/?already_logined");
        exit;
    }
    // Kalau role admin
    elseif ($_SESSION['role'] == 'admin') {
        header("Location: ../dashboard/admin/?already_logined");
        exit;
    }
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        header("Location: login.php?error=invalid");
        exit;
    }
    
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Gunakan Prepared Statement (SECURE)
    $sql = "SELECT * FROM user WHERE username = ?";
    $stmt = $conn->prepare($sql);
    
    if ($stmt === false) {
        die('Prepare failed: ' . htmlspecialchars($conn->error));
    }
    
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];

        if (isset($user['id_cabang']) && $user['id_cabang'] != null) {
            // Gunakan Prepared Statement untuk query cabang juga
            $sqlCabang = "SELECT nama_cabang FROM kantor_cabang WHERE id = ?";
            $stmtCabang = $conn->prepare($sqlCabang);
            
            if ($stmtCabang !== false) {
                $stmtCabang->bind_param("i", $user['id_cabang']);
                $stmtCabang->execute();
                $resultCabang = $stmtCabang->get_result();
                
                if ($row = $resultCabang->fetch_assoc()) {
                    $_SESSION['id_cabang'] = $user['id_cabang'];
                    $_SESSION['cabang'] = $row['nama_cabang'];
                }
                $stmtCabang->close();
            }
        }

        if ($user['role'] == 'superSuperAdmin') {
            header("Location: ../dashboard/superSuperAdmin/");
            exit;
        } elseif ($user['role'] == 'superAdmin') {
            header("Location: ../dashboard/superAdmin/");
            exit;
        } elseif ($user['role'] == 'admin') {
            header("Location: ../dashboard/admin/");
            exit;
        }
    } else {
        header("Location: login.php?error=invalid");
    }
    
    $stmt->close();
}

?>
<?php
$title = "Login - Cendana Kargo";
include '../templates/header.php';
?>
<div class="container d-flex justify-content-center align-items-center min-vh-100">
    <div class="card shadow p-4" style="width: 350px; border-top: 5px solid #dc3545;">
        <a href="../" class="mx-auto">
            <img src="../assets/logo.jpg" alt="Logo Cendana Lintas Kargo" width="100px" />
        </a>
        <h3 class="text-center text-danger mb-4 fw-bold">Login</h3>
        <form action="login" method="post">
            <?php if (isset($_GET['error']) && $_GET['error'] == 'invalid') {
                $type = "danger";
                $message = "Invalid username or password.";
                include '../components/alert.php';
            } ?>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">
            <div class="mb-3">
                <label for="username" class="form-label fw-semibold">Username</label>
                <input type="text" class="form-control" id="username" name="username" placeholder="Masukkan username"
                    required>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label fw-semibold">Password</label>
                <input type="password" class="form-control" id="password" name="password"
                    placeholder="Masukkan password" required>
            </div>
            <div class="d-grid">
                <button type="submit" class="btn btn-danger fw-bold">Login</button>
            </div>
        </form>
    </div>
</div>
<?php
include '../templates/footer.php';
?>