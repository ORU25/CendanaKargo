<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: ../../../auth/login.php");
    exit;
}

if (isset($_SESSION['role']) && $_SESSION['role'] !== 'admin') {
    header("Location: ../../../?error=unauthorized");
    exit;
}

// Token CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

include '../../../config/database.php';
$title = "Edit Status Pengiriman - Cendana Kargo";

// Ambil data pengiriman berdasarkan ID
if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $sql = "SELECT * FROM pengiriman WHERE id = $id";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        $pengiriman = $result->fetch_assoc();
    } else {
        header("Location: ./?error=not_found");
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        header("Location: update?id=" . intval($_GET['id']) . "&error=failed");
        exit;
    }

    $id = intval($_POST['id']);
    $status = trim($_POST['status']);
    $status_safe = mysqli_real_escape_string($conn, $status);

    $sql = "UPDATE pengiriman SET status = '$status_safe' WHERE id = $id";

    if ($conn->query($sql) === TRUE) {
        header("Location: ./?success=updated");
        exit;
    } else {
        header("Location: update?id=$id&error=failed");
        exit;
    }
}
?>

<?php  
$page = "barang_masuk";
include '../../../templates/header.php';
include '../../../components/navDashboard.php';
include '../../../components/sidebar_offcanvas.php';
?>

<div class="container-fluid">
  <div class="row">
    <?php include '../../../components/sidebar.php'; ?>

    <!-- Konten utama -->
    <div class="col-lg-10 d-flex align-items-start justify-content-start py-4 px-5">
      <div class="card shadow-sm p-4" style="width: 100%; max-width: 600px;">
        <?php if (isset($_GET['error']) && $_GET['error'] == 'failed') {
            $type = "danger";
            $message = "Gagal memperbarui status pengiriman.";
            include '../../../components/alert.php';
        } ?>
        <?php if (isset($_GET['error']) && $_GET['error'] == 'not_found') {
            $type = "danger";
            $message = "Data tidak ditemukan.";
            include '../../../components/alert.php';
        } ?>

        <h3 class="text-danger fw-bold mb-4">Edit Status Pengiriman</h3>

        <form action="update.php" method="POST">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">
          <input type="hidden" name="id" value="<?= $pengiriman['id']; ?>">

          <!-- No Resi -->
          <div class="mb-3">
            <label class="form-label fw-semibold">No. Resi</label>
            <input type="text" class="form-control" value="<?= htmlspecialchars($pengiriman['no_resi']); ?>" disabled>
          </div>

          <!-- Nama Penerima -->
          <div class="mb-3">
            <label class="form-label fw-semibold">Nama Penerima</label>
            <input type="text" class="form-control" value="<?= htmlspecialchars($pengiriman['nama_penerima']); ?>" disabled>
          </div>

          <!-- Status -->
          <div class="mb-4">
            <label for="status" class="form-label fw-semibold">Status</label>
            <select class="form-select" id="status" name="status" required>
              <option value="selesai" <?= ($pengiriman['status'] == 'selesai') ? 'selected' : '' ?>>Selesai</option>
            </select>
          </div>

          <button type="submit" class="btn btn-danger fw-semibold px-4">Simpan</button>
        </form>
      </div>
    </div>
  </div>
</div>

<?php
include '../../../templates/footer.php';
?>
