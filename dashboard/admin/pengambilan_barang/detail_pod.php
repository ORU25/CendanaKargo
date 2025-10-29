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

include '../../../config/database.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: ./?error=invalid_id");
    exit;
}

$id_pengiriman = (int)$_GET['id'];

$stmt = $conn->prepare("SELECT no_resi, nama_barang, nama_pengirim, nama_penerima, total_tarif, tanggal, cabang_pengirim, cabang_penerima, status 
                        FROM pengiriman WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $id_pengiriman);
$stmt->execute();
$result = $stmt->get_result();
$pengiriman = $result->fetch_assoc();
$stmt->close();

if (!$pengiriman) {
    header("Location: ./?error=data_not_found");
    exit;
}

$stmt = $conn->prepare("SELECT * FROM pengambilan WHERE no_resi = ? ORDER BY id DESC LIMIT 1");
$stmt->bind_param("s", $pengiriman['no_resi']);
$stmt->execute();
$result = $stmt->get_result();
$pengambilan = $result->fetch_assoc();
$stmt->close();

if (!$pengambilan) {
    header("Location: ./?error=no_pod_data");
    exit;
}

$title = "Detail POD - Cendana Kargo";
$page = "pengambilan_barang";

include '../../../templates/header.php';
include '../../../components/navDashboard.php';
include '../../../components/sidebar_offcanvas.php';
?>

<div class="container-fluid">
  <div class="row">
    <?php include '../../../components/sidebar.php'; ?>

    <div class="col-lg-10 bg-light">
      <div class="container-fluid p-4">

        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
          <div>
            <h1 class="h4 fw-bold mb-1">Detail Proof of Delivery (POD)</h1>
            <p class="text-muted small mb-0">
              No Resi: <span class="fw-semibold"><?= htmlspecialchars($pengiriman['no_resi']); ?></span>
            </p>
          </div>
          <div class="d-flex gap-2 mt-2 mt-md-0">
            <a href="./" class="btn btn-sm btn-outline-secondary">Kembali</a>
          </div>
        </div>

        <!-- Informasi pengiriman -->
        <div class="card border-0 shadow-sm mb-4">
          <div class="card-body text-capitalize">
            <h5 class="fw-bold mb-3"><?= htmlspecialchars($pengiriman['nama_barang']); ?></h5>
            <p><strong>Pengirim:</strong> <?= htmlspecialchars($pengiriman['nama_pengirim']); ?></p>
            <p><strong>Penerima:</strong> <?= htmlspecialchars($pengiriman['nama_penerima']); ?></p>
            <p><strong>Asal:</strong> <?= htmlspecialchars($pengiriman['cabang_pengirim']); ?></p>
            <p><strong>Tujuan:</strong> <?= htmlspecialchars($pengiriman['cabang_penerima']); ?></p>
            <p><strong>Tanggal Pengiriman:</strong> <?= date('d/m/Y', strtotime($pengiriman['tanggal'])); ?></p>
            <p><strong>Total Tarif:</strong> Rp <?= number_format($pengiriman['total_tarif'], 0, ',', '.'); ?></p>
            <span class="badge text-bg-success text-uppercase">POD</span>
          </div>
        </div>

        <!-- Informasi pengambil -->
        <div class="card border-0 shadow">
          <div class="card-header bg-primary text-white fw-semibold">
            Data Pengambil Barang
          </div>
          <div class="card-body">
            <div class="row mb-3">
              <div class="col-md-6">
                <p><strong>Nama Pengambil:</strong><br><?= htmlspecialchars($pengambilan['nama_pengambil']); ?></p>
              </div>
              <div class="col-md-6">
                <p><strong>No. Telepon Pengambil:</strong><br><?= htmlspecialchars($pengambilan['telp_pengambil']); ?></p>
              </div>
            </div>
            <p><strong>Tanggal Pengambilan:</strong> <?= date('d/m/Y H:i', strtotime($pengambilan['tanggal'])); ?></p>
            <p class="text-muted small mb-0">
              Data di atas tercatat saat admin menandai status menjadi <strong>POD</strong>.
            </p>
          </div>
        </div>

      </div>
    </div>
  </div>
</div>

<?php include '../../../templates/footer.php'; ?>
