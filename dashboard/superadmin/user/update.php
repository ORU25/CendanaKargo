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
    if ($_SERVER['REQUEST_METHOD'] === 'POST' ) {
        $id = intval($_POST['id']);
        $username = trim($_POST['username']);
        $role = $_POST['role'];
        $id_cabang = $_POST['id_cabang'];

        # Cek username duplikat (selain user saat ini)
        $username_safe = mysqli_real_escape_string($conn, $username);
        $check = "SELECT id FROM user WHERE username = '$username_safe' AND id != $id";
        $result = $conn->query($check);
        if ($result->num_rows > 0) {
            header("Location: update?id=$id&error=username_taken");
            exit;
        }

        # Ambil data user saat ini
        $sql = "SELECT * FROM user WHERE id = $id";
        $result = $conn->query($sql);
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
        } else {
            header("Location: ./?error=not_found");
            exit;
        }
        
        # Cek password
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

        # Update data
        $stmt = $conn->prepare("UPDATE user SET username=?, password=?, role=?, id_cabang=? WHERE id=?");
        $stmt->bind_param("ssssi", $username, $password_hash, $role, $id_cabang, $id);

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
                <select class="form-select" id="cabang" name="id_cabang" required>
                    <option value="">Select Cabang</option>
                    <?php foreach ($cabangs as $cabang): ?>
                        <option 
                            value="<?= $cabang['id']; ?>" 
                            <?= (isset($user['id_cabang']) && $user['id_cabang'] == $cabang['id']) ? 'selected' : '' ?>
                        >
                            <?= htmlspecialchars($cabang['nama_cabang']); ?>
                        </option>
                    <?php endforeach; ?>
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