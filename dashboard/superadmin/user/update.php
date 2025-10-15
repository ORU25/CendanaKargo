<?php
    session_start();
    if(!isset($_SESSION['username'])){
        header("Location: ../../../auth/login.php");
        exit;
    }

    if(isset($_SESSION['role']) && $_SESSION['role'] !== 'superAdmin'){
        header("Location: ../../../?error=unauthorized");
        exit;
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

?>

<?php
    $page = "user";
    include '../../../templates/header.php';
    include '../../../components/navDashboard.php';
    include '../../../components/sidebar_offcanvas.php';
?>
<!-- Sidebar Offcanvas -->
<div class="offcanvas offcanvas-start" tabindex="-1" id="sidebarOffcanvas" aria-labelledby="sidebarOffcanvasLabel">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="sidebarOffcanvasLabel">Menu</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body p-0">
        <div class="list-group list-group-flush">
            <a href="<?= BASE_URL; ?>dashboard/superadmin/" class="list-group-item list-group-item-action">Dashboard</a>
            <a href="<?= BASE_URL; ?>dashboard/superadmin/pengiriman/" class="list-group-item list-group-item-action">Pengiriman</a>
            <a href="<?= BASE_URL; ?>dashboard/superadmin/surat_jalan/" class="list-group-item list-group-item-action">Surat Jalan</a>
            <a href="<?= BASE_URL; ?>dashboard/superadmin/tarif/" class="list-group-item list-group-item-action">Tarif</a>
            <a href="<?= BASE_URL; ?>dashboard/superadmin/kantor_cabang/" class="list-group-item list-group-item-action">Kantor Cabang</a>
            <a href="<?= BASE_URL; ?>dashboard/superadmin/user/" class="list-group-item list-group-item-action fw-bold text-danger">User</a>
        </div>
        <a href="<?= BASE_URL; ?>auth/logout.php" class="btn btn-outline-danger mt-3 ms-3">Logout</a>
    </div>
</div>

<div class="container-fluid">
  <div class="row">
    <div class="col-lg-2 d-none d-lg-block bg-light border-end vh-100">
      <div class="list-group list-group-flush">
        <a href="<?= BASE_URL; ?>dashboard/superadmin/" class="list-group-item list-group-item-action">Dashboard</a>
        <a href="<?= BASE_URL; ?>dashboard/superadmin/pengiriman/" class="list-group-item list-group-item-action">Pengiriman</a>
        <a href="<?= BASE_URL; ?>dashboard/superadmin/surat_jalan/" class="list-group-item list-group-item-action">Surat Jalan</a>
        <a href="<?= BASE_URL; ?>dashboard/superadmin/tarif/" class="list-group-item list-group-item-action">Tarif</a>
        <a href="<?= BASE_URL; ?>dashboard/superadmin/kantor_cabang/" class="list-group-item list-group-item-action">Kantor Cabang</a>
        <a href="<?= BASE_URL; ?>dashboard/superadmin/user/" class="list-group-item list-group-item-action fw-bold text-danger">User</a>
      </div>
        <a href="<?= BASE_URL; ?>auth/logout.php" class="btn btn-outline-danger mt-3">Logout</a>
    </div>

    <!-- Konten utama -->
    <div class="col-lg-10">
        <?php if(isset($_GET['error']) && $_GET['error'] == 'password_mismatch'){
            $type = "danger";
            $message = "Password dan Confirm Password tidak sesuai";
            include '../../../components/alert.php';
        }?>
        <?php if(isset($_GET['error']) && $_GET['error'] == 'failed'){
            $type = "danger";
            $message = "Gagal memperbarui user ";
            include '../../../components/alert.php';
        }?>
        <?php if(isset($_GET['error']) && $_GET['error'] == 'username_taken'){
            $type = "danger";
            $message = "Username sudah digunakan, silakan pilih username lain";
            include '../../../components/alert.php';
        }?>
        <h1>Update <?= isset($user['username']) ? htmlspecialchars($user['username']) : '' ?></h1>
        <form action="update" method="POST" class="col-5">
            <input type="hidden" name="id" value="<?= $user['id'] ?? '' ?>">
            <div class="mb-3">
                <label for="username" class="form-label">Username</label>
                <input type="text" class="form-control" id="username" name="username" value="<?= isset($user['username']) ? htmlspecialchars($user['username']) : "" ?>" required>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" name="password" >
            </div>
            <div class="mb-3">
                <label for="confirm_pasword" class="form-label">Confirm Password</label>
                <input type="password" class="form-control" id="confirm_pasword" name="confirm_pasword" >
            </div>
            <div class="mb-3">
                <label for="role" class="form-label">Role</label>
                <select class="form-select" id="role" name="role" required>
                    <option value="">Select Role</option>
                    <option value="admin" <?= ($user['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Admin</option>
                    <option value="superAdmin" <?= ($user['role'] ?? '') === 'superAdmin' ? 'selected' : '' ?>>Super Admin</option>
                </select>
            </div>
           <div class="mb-3">
                <label for="cabang" class="form-label">Kantor Cabang</label>
                <select class="form-select" id="cabang" name="id_cabang">
                    <option value="">Tidak ada (Pusat)</option>
                    <?php if (!empty($cabangs)): ?>
                        <?php foreach ($cabangs as $cabang): ?>
                            <option 
                                value="<?= htmlspecialchars($cabang['id']); ?>" 
                                <?= (!empty($user['id_cabang']) && $user['id_cabang'] == $cabang['id']) ? 'selected' : '' ?>
                            >
                                <?= htmlspecialchars($cabang['nama_cabang']); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <option disabled>Tidak ada cabang tersedia</option>
                    <?php endif; ?>
                </select>
            </div>
            <p class="text-danger">Kosongkan password jika tidak ingin mengganti</p>
            <button type="submit" class="btn btn-primary">Create User</button>
        </form>
    </div>
  </div>
</div>
<?php
    include '../../../templates/footer.php';
?>