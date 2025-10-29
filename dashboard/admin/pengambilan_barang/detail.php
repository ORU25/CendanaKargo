<?php
session_start();
if(!isset($_SESSION['username'])){
    header("Location: ../../../auth/login.php");
    exit;
}

if(isset($_SESSION['role']) && $_SESSION['role'] !== 'admin'){
    header("Location: ../../../?error=unauthorized");
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

include '../../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        header('Location: detail?id=' . intval($_GET['id']) . '&error=invalid_csrf');
        exit;
    }

    $id_update = (int)($_POST['id'] ?? 0);
    $nama_pengambil = trim($_POST['nama_pengambil'] ?? '');
    $telp_pengambil = trim($_POST['telp_pengambil'] ?? '');
    $id_user = $_SESSION['id'] ?? null;

    if ($id_update > 0 && $nama_pengambil !== '' && $telp_pengambil !== '') {
        $stmt = $conn->prepare("SELECT no_resi FROM pengiriman WHERE id = ?");
        $stmt->bind_param("i", $id_update);
        $stmt->execute();
        $result = $stmt->get_result();
        $data_pengiriman = $result->fetch_assoc();
        $stmt->close();

        if (!$data_pengiriman) {
            header("Location: detail?error=not_found");
            exit;
        }

        $no_resi = $data_pengiriman['no_resi'];
        $status_baru = 'pod';

        $stmt = $conn->prepare("INSERT INTO pengambilan (id_user, no_resi, nama_pengambil, telp_pengambil) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $id_user, $no_resi, $nama_pengambil, $telp_pengambil);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("UPDATE pengiriman SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status_baru, $id_update);
        if ($stmt->execute()) {
            header("Location: index?success=pod_updated");
            exit;
        }
        $stmt->close();
    }

    header('Location: detail?error=update_failed');
    exit;
}

$pengiriman = null;
if(isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $stmt = $conn->prepare('SELECT * FROM pengiriman WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $pengiriman = $result->fetch_assoc();
    $stmt->close();

    if(!$pengiriman || $pengiriman['status'] !== 'sampai tujuan' || $pengiriman['cabang_penerima'] != $_SESSION['cabang']){
        header("Location: ./?error=not_found");
        exit;
    }
}

$title = "Detail Pengambilan Barang - Cendana Kargo";
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
                    <h1 class="h4 fw-bold mb-1">Detail Pengambilan Barang</h1>
                    <p class="text-muted small mb-0">No Resi: 
                        <span class="fw-semibold"><?= htmlspecialchars($pengiriman['no_resi']); ?></span>
                    </p>
                </div>
                <div class="d-flex gap-2 mt-2 mt-md-0">
                    <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#updateStatusModal">
                        Tandai Selesai (POD)
                    </button>
                    <a href="./" class="btn btn-sm btn-outline-secondary">Kembali</a>
                </div>
            </div>

            <div class="card border-0 shadow mb-4 text-capitalize">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3"><?= htmlspecialchars($pengiriman['nama_barang']); ?></h5>
                    <p><strong>Pengirim:</strong> <?= htmlspecialchars($pengiriman['nama_pengirim']); ?></p>
                    <p><strong>Penerima:</strong> <?= htmlspecialchars($pengiriman['nama_penerima']); ?></p>
                    <p><strong>Tanggal:</strong> <?= date('d/m/Y', strtotime($pengiriman['tanggal'])); ?></p>
                    <p><strong>Total Tarif:</strong> Rp <?= number_format($pengiriman['total_tarif'], 0, ',', '.'); ?></p>
                    <span class="badge text-bg-info text-uppercase"><?= htmlspecialchars($pengiriman['status']); ?></span>
                </div>
            </div>

            <!-- Modal konfirmasi update status + input nama & telp -->
            <div class="modal fade" id="updateStatusModal" tabindex="-1" aria-labelledby="updateStatusModalLabel" aria-hidden="true">
              <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow">
                  <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="id" value="<?= (int)$pengiriman['id']; ?>">
                    <input type="hidden" name="update_status" value="1">

                    <div class="modal-header border-0 pb-0">
                      <h5 class="modal-title fw-bold">Konfirmasi Pengambilan</h5>
                      <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body">
                      <p>Isi data berikut untuk konfirmasi pengambilan barang:</p>

                      <div class="mb-3">
                        <label class="form-label fw-semibold">Nama Pengambil <span class="text-danger">*</span></label>
                        <input type="text" name="nama_pengambil" class="form-control" required>
                      </div>

                      <div class="mb-3">
                        <label class="form-label fw-semibold">Nomor Telepon <span class="text-danger">*</span></label>
                        <input type="text" name="telp_pengambil" class="form-control" required pattern="[0-9+ ]+">
                      </div>

                      <p class="small text-muted mb-0">
                        Setelah disimpan, status akan diubah menjadi <strong>"POD (Proof of Delivery)"</strong>.
                      </p>
                    </div>

                    <div class="modal-footer border-0 pt-0">
                      <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                      <button type="submit" class="btn btn-success">Simpan & Tandai POD</button>
                    </div>
                  </form>
                </div>
              </div>
            </div>

        </div>
    </div>
  </div>
</div>

<?php include '../../../templates/footer.php'; ?>
