<?php
session_start();
if(!isset($_SESSION['username'] )|| !isset($_SESSION['user_id'])){
    header("Location: ../../../auth/login");
    exit;
}

if(isset($_SESSION['role']) && $_SESSION['role'] !== 'systemOwner'){
    header("Location: ../../../?error=unauthorized");
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

include '../../../config/database.php';
$title = "Dashboard - Cendana Kargo";

// Ambil data driver berdasarkan ID
if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $sql = "SELECT * FROM driver WHERE id = $id";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        $driver = $result->fetch_assoc();
    } else {
        header("Location: ./?error=not_found");
        exit;
    }
}

// Proses update driver
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        header("Location: update?id=" . intval($_POST['id']) . "&error=failed");
        exit;
    }

    $id = intval($_POST['id']);
    $nama_driver = trim($_POST['nama_driver'] ?? '');
    $telp_driver = trim($_POST['telp_driver'] ?? '');

    if (empty($nama_driver) || empty($telp_driver)) {
        header("Location: update?id=$id&error=missing_fields");
        exit;
    }

    // Validasi format nomor telepon (10-15 digit angka)
    if (!preg_match('/^[0-9]{10,15}$/', $telp_driver)) {
        header("Location: update?id=$id&error=invalid_phone");
        exit;
    }

    // Cek duplikat nama driver (selain dirinya sendiri)
    $check_stmt = $conn->prepare("SELECT id FROM driver WHERE nama_driver = ? AND id != ?");
    $check_stmt->bind_param('si', $nama_driver, $id);
    $check_stmt->execute();
    $check_stmt->store_result();
    if ($check_stmt->num_rows > 0) {
        $check_stmt->close();
        header("Location: update?id=$id&error=duplicate_name");
        exit;
    }
    $check_stmt->close();

    $stmt = $conn->prepare("UPDATE driver SET nama_driver = ?, telp_driver = ? WHERE id = ?");
    $stmt->bind_param('ssi', $nama_driver, $telp_driver, $id);

    if ($stmt->execute()) {
        header("Location: ./?success=updated");
        exit;
    } else {
        header("Location: update?id=$id&error=failed");
        exit;
    }
}

$page = "driver";
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
            <h3 class="text-danger fw-bold mb-4">Update Driver <?= isset($driver['nama_driver']) ? htmlspecialchars($driver['nama_driver']) : '' ?></h3>
            <?php if(isset($_GET['error']) && $_GET['error'] == 'missing_fields'){
                $type = "danger";
                $message = "Nama atau telepon driver tidak boleh kosong";
                include '../../../components/alert.php';
            }?>
            <?php if(isset($_GET['error']) && $_GET['error'] == 'invalid_phone'){
                $type = "danger";
                $message = "Format nomor telepon tidak valid. Harus 10-15 digit angka.";
                include '../../../components/alert.php';
            }?>
            <?php if(isset($_GET['error']) && $_GET['error'] == 'failed'){
                $type = "danger";
                $message = "Gagal memperbarui driver";
                include '../../../components/alert.php';
            }?>
            <?php if(isset($_GET['error']) && $_GET['error'] == 'duplicate_name'){
                $type = "danger";
                $message = "Nama driver sudah terdaftar. Gunakan nama yang berbeda.";
                include '../../../components/alert.php';
            }?>


            <form action="update" method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">
            <input type="hidden" name="id" value="<?= $driver['id'] ?? '' ?>">

            <div class="mb-3">
                <label for="nama_driver" class="form-label fw-semibold">Nama Driver</label>
                <input type="text" class="form-control" id="nama_driver" name="nama_driver"
                        value="<?= isset($driver['nama_driver']) ? htmlspecialchars($driver['nama_driver']) : "" ?>" required>
            </div>

            <div class="mb-3">
                <label for="telp_driver" class="form-label fw-semibold">No. Telepon</label>
        <input type="tel" class="form-control" id="telp_driver" name="telp_driver"
            value="<?= isset($driver['telp_driver']) ? htmlspecialchars($driver['telp_driver']) : "" ?>" required
            pattern="[0-9]{10,15}"
            title="Nomor telepon harus 10-15 digit angka (contoh: 081234567890)">
            </div>

            <div class="d-grid">
                <button type="submit" class="btn btn-danger fw-semibold mt-2">Update Driver</button>
            </div>
            </form>
        </div>
    </div>
  </div>
</div>

<?php include '../../../templates/footer.php'; ?>
