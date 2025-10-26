<?php
session_start();
if(!isset($_SESSION['username'])){
    header("Location: ../../../auth/login.php");
    exit;
}

if(isset($_SESSION['role']) && $_SESSION['role'] !== 'superSuperAdmin'){
    header("Location: ../../../?error=unauthorized");
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

include '../../../config/database.php';
$title = "Dashboard - Cendana Kargo";

# --- Ambil data kantor cabang ---
$sqlCabang = "SELECT * FROM kantor_cabang ORDER BY id ASC";
$resultCabang = $conn->query($sqlCabang);
$cabangs = $resultCabang->num_rows > 0 ? $resultCabang->fetch_all(MYSQLI_ASSOC) : [];

# --- Ambil data user berdasarkan ID ---
if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    if($id == $_SESSION['user_id']){
        header("Location: ./?error=not_found");
        exit;
    }
    $sql = "SELECT * FROM user WHERE id = $id";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
    } else {
        header("Location: ./?error=not_found");
        exit;
    }
}

# --- Proses update ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        header("Location: update?id=" . intval($_GET['id']) . "&error=failed");
        exit;
    }

    $id = intval($_POST['id']);
    $username = trim($_POST['username']);
    $role = $_POST['role'];
    $id_cabang = !empty($_POST['id_cabang']) ? $_POST['id_cabang'] : null;

    $check_stmt = $conn->prepare("SELECT id FROM user WHERE username = ? AND id != ?");
    $check_stmt->bind_param("si", $username, $id);
    $check_stmt->execute();
    $check_stmt->store_result();

    if ($check_stmt->num_rows > 0) {
        header("Location: update?id=$id&error=username_taken");
        exit;
    }
    $check_stmt->close();

    $get_stmt = $conn->prepare("SELECT password FROM user WHERE id = ?");
    $get_stmt->bind_param("i", $id);
    $get_stmt->execute();
    $result = $get_stmt->get_result();

    if ($result->num_rows === 0) {
        header("Location: ./?error=not_found");
        exit;
    }

    $user = $result->fetch_assoc();
    $get_stmt->close();

    if (!empty($_POST['password']) && !empty($_POST['confirm_pasword'])) {
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_pasword'];

        if ($password !== $confirm_password) {
            header("Location: update?id=$id&error=password_mismatch");
            exit;
        } else {
            $password_hash = password_hash($password, PASSWORD_BCRYPT);
        }
    } else {
        $password_hash = $user['password'];
    }

    $id_cabang_param = $id_cabang ? (int)$id_cabang : null;

    $stmt = $conn->prepare("UPDATE user SET username = ?, password = ?, role = ?, id_cabang = ? WHERE id = ?");
    $stmt->bind_param("sssii", $username, $password_hash, $role, $id_cabang_param, $id);

    if ($stmt->execute()) {
        header("Location: ./?success=updated");
        exit;
    } else {
        header("Location: update?id=$id&error=failed");
        exit;
    }
}

$page = "user";
include '../../../templates/header.php';
include '../../../components/navDashboard.php';
include '../../../components/sidebar_offcanvas.php';
?>

<div class="container-fluid">
  <div class="row">
    <?php include '../../../components/sidebar.php'; ?>

    <!-- Konten utama -->
    <div class="col-lg-10 d-flex align-items-start justify-content-start py-4 px-5">
        <div class="card shadow-sm p-4" style="width: 100%; max-width: 750px;">
            <h3 class="text-danger fw-bold mb-4">Update <?= isset($user['username']) ? htmlspecialchars($user['username']) : '' ?></h3>
            <?php if(isset($_GET['error']) && $_GET['error'] == 'password_mismatch'){
                $type = "danger";
                $message = "Password dan Confirm Password tidak sesuai";
                include '../../../components/alert.php';
            }?>
            <?php if(isset($_GET['error']) && $_GET['error'] == 'failed'){
                $type = "danger";
                $message = "Gagal memperbarui user";
                include '../../../components/alert.php';
            }?>
            <?php if(isset($_GET['error']) && $_GET['error'] == 'username_taken'){
                $type = "danger";
                $message = "Username sudah digunakan, silakan pilih username lain";
                include '../../../components/alert.php';
            }?>


            <form action="update" method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">
            <input type="hidden" name="id" value="<?= $user['id'] ?? '' ?>">

            <!-- Baris 1: Username & Role -->
            <div class="row mb-3">
                <div class="col-md-6">
                <label for="username" class="form-label fw-semibold">Username</label>
                <input type="text" class="form-control" id="username" name="username"
                        value="<?= isset($user['username']) ? htmlspecialchars($user['username']) : "" ?>" required>
                </div>
                <div class="col-md-6">
                <label for="role" class="form-label fw-semibold">Role</label>
                <select class="form-select" id="role" name="role" required>
                    <option value="">Select Role</option>
                    <option value="admin" <?= ($user['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Admin</option>
                    <option value="superAdmin" <?= ($user['role'] ?? '') === 'superAdmin' ? 'selected' : '' ?>>Super Admin</option>
                </select>
                </div>
            </div>

            <!-- Baris 2: Password & Confirm Password -->
            <div class="row mb-3">
                <div class="col-md-6">
                <label for="password" class="form-label fw-semibold">Password</label>
                <input type="password" class="form-control" id="password" name="password">
                </div>
                <div class="col-md-6">
                <label for="confirm_pasword" class="form-label fw-semibold">Confirm Password</label>
                <input type="password" class="form-control" id="confirm_pasword" name="confirm_pasword">
                </div>
            </div>

            <!-- Baris 3: Kantor Cabang -->
            <div class="mb-3">
                <label for="cabang" class="form-label fw-semibold">Kantor Cabang</label>
                <select class="form-select" id="cabang" name="id_cabang">
                <?php if (!empty($cabangs)): ?>
                    <?php foreach ($cabangs as $cabang): ?>
                        <option 
                            value="<?= htmlspecialchars($cabang['id']); ?>" 
                            <?= (!empty($user['id_cabang']) && $user['id_cabang'] == $cabang['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cabang['nama_cabang']); ?>
                        </option>
                    <?php endforeach; ?>
                <?php else: ?>
                    <option disabled>Tidak ada cabang tersedia</option>
                <?php endif; ?>
                </select>
            </div>

            <p class="text-danger small">Kosongkan password jika tidak ingin mengganti</p>

            <div class="d-grid">
                <button type="submit" class="btn btn-danger fw-semibold mt-2">Update User</button>
            </div>
            </form>
        </div>
    </div>
  </div>
</div>

<?php include '../../../templates/footer.php'; ?>
