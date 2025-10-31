<?php
session_start();
if(!isset($_SESSION['username']) || !isset($_SESSION['user_id'])){
    header("Location: ../../../auth/login.php");
    exit;
}

if(isset($_SESSION['role']) && $_SESSION['role'] !== 'superAdmin'){
    header("Location: ../../../?error=unauthorized");
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

include '../../../config/database.php';

// === Ambil data pengiriman ===
$pengiriman = null;
if(isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $stmt = $conn->prepare('SELECT * FROM pengiriman WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $pengiriman = $result->fetch_assoc();
    $stmt->close();

    if(!$pengiriman){
        header("Location: ./?error=not_found");
        exit;
    }

    // Ambil username pembuat data
    $stmt = $conn->prepare('SELECT username FROM user WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $pengiriman['id_user']);
    $stmt->execute();
    $resultUser = $stmt->get_result();
    $userData = $resultUser->fetch_assoc();
    $stmt->close();
    $pengiriman['user'] = $userData['username'] ?? '-';
}

// === Ambil log perubahan status ===
$logs = [];
$stmt_logs = $conn->prepare('
    SELECT l.*, u.username 
    FROM log_status_pengiriman l 
    LEFT JOIN user u ON l.diubah_oleh = u.id 
    WHERE l.id_pengiriman = ? 
    ORDER BY l.waktu_perubahan DESC
');
$stmt_logs->bind_param('i', $id);
$stmt_logs->execute();
$result_logs = $stmt_logs->get_result();
while ($row = $result_logs->fetch_assoc()) {
    $logs[] = $row;
}
$stmt_logs->close();

// === Ambil data pengambilan ===
$pengambilanData = null;
$stmt_pengambilan = $conn->prepare("
    SELECT nama_pengambil, telp_pengambil, tanggal 
    FROM pengambilan 
    WHERE no_resi = ? 
    ORDER BY tanggal DESC 
    LIMIT 1
");
$stmt_pengambilan->bind_param('s', $pengiriman['no_resi']);
$stmt_pengambilan->execute();
$result_pengambilan = $stmt_pengambilan->get_result();
if ($result_pengambilan->num_rows > 0) {
    $pengambilanData = $result_pengambilan->fetch_assoc();
}
$stmt_pengambilan->close();

// === Proses konfirmasi POD ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        header("Location: detail?id=$id&error=invalid_csrf");
        exit;
    }

    $id_update = (int)($_POST['id'] ?? 0);
    $nama_pengambil = trim($_POST['nama_pengambil'] ?? '');
    $telp_pengambil = trim($_POST['telp_pengambil'] ?? '');
    $id_user = $_SESSION['user_id'];

    if ($nama_pengambil === '') {
        header("Location: detail?id=$id_update&error=empty_name");
        exit;
    }
    if (!preg_match('/^(\+62|0)[0-9]{9,14}$/', $telp_pengambil)) {
        header("Location: detail?id=$id_update&error=invalid_phone");
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO pengambilan (id_user, no_resi, nama_pengambil, telp_pengambil, tanggal) VALUES (?, ?, ?, ?, NOW())");
    $stmt->bind_param("isss", $id_user, $pengiriman['no_resi'], $nama_pengambil, $telp_pengambil);
    $stmt->execute();
    $stmt->close();

    $status_baru = 'pod';
    $stmt = $conn->prepare("UPDATE pengiriman SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $status_baru, $id_update);
    if ($stmt->execute()) {
        $stmt->close();

        $stmt_log = $conn->prepare("
            INSERT INTO log_status_pengiriman (id_pengiriman, status_lama, status_baru, keterangan, diubah_oleh)
            VALUES (?, ?, ?, ?, ?)
        ");
        $status_lama = $pengiriman['status'];
        $keterangan = 'Barang telah diambil dan dikonfirmasi sebagai POD oleh Superadmin.';
        $stmt_log->bind_param("isssi", $id_update, $status_lama, $status_baru, $keterangan, $id_user);
        $stmt_log->execute();
        $stmt_log->close();

        header("Location: detail?id=$id_update&success=pod_updated");
        exit;
    }
}

$title = "Detail Pengambilan Barang - Superadmin";
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

            <!-- Header -->
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h4 mb-1 fw-bold">Detail Pengambilan Barang</h1>
                    <p class="text-muted small mb-0">Dibuat oleh:  
                        <span class="fw-semibold"><?= htmlspecialchars($pengiriman['user']); ?></span>
                    </p>
                    <p class="text-muted small mb-0">No. Resi: 
                        <span class="fw-semibold"><?= htmlspecialchars($pengiriman['no_resi']); ?></span>
                    </p>
                </div>

                <div class="d-flex gap-2 mt-2 mt-md-0">
                    <?php if ($pengiriman['status'] === 'sampai tujuan'): ?>
                        <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#updateStatusModal">
                            Tandai Selesai (POD)
                        </button>
                    <?php endif; ?>
                    <a href="./" class="btn btn-sm btn-outline-secondary">Kembali</a>
                </div>
            </div>

            <!-- Info Barang -->
            <div class="card border-0 shadow mb-4 text-capitalize">
                <div class="card-body p-4">
                    <div class="row">
                        <div class="col-md-8">
                            <h5 class="fw-bold mb-3"><?= htmlspecialchars($pengiriman['nama_barang']); ?></h5>
                            <div class="row g-3">
                                <div class="col-6 col-md-3">
                                    <small class="opacity-75 d-block">Tanggal</small>
                                    <strong><?= date('d/m/Y', strtotime($pengiriman['tanggal'])); ?></strong>
                                </div>
                                <div class="col-6 col-md-2">
                                    <small class="opacity-75 d-block">Berat</small>
                                    <strong><?= number_format($pengiriman['berat'], 1); ?> kg</strong>
                                </div>
                                <div class="col-6 col-md-2">
                                    <small class="opacity-75 d-block">Jumlah</small>
                                    <strong><?= (int)$pengiriman['jumlah']; ?> item</strong>
                                </div>
                                <div class="col-6 col-md-2">
                                    <small class="opacity-75 d-block">Diskon</small>
                                    <strong><?= $pengiriman['diskon'] ? number_format($pengiriman['diskon'], 1).'%' : '-'; ?></strong>
                                </div>
                                <div class="col-6 col-md-3">
                                    <small class="opacity-75 d-block">Pembayaran</small>
                                    <strong><?= htmlspecialchars($pengiriman['pembayaran']); ?></strong>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mt-3 mt-md-0 text-md-end">
                            <small class="opacity-75 d-block mb-2">Total Tarif</small>
                            <h3 class="fw-bold mb-2">Rp <?= number_format($pengiriman['total_tarif'], 0, ',', '.'); ?></h3>
                            <span class="badge rounded-pill text-uppercase text-bg-info">
                                <?= htmlspecialchars($pengiriman['status']); ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Data Pengirim & Penerima -->
            <div class="row g-3 text-capitalize mb-4">
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body p-4">
                            <h6 class="fw-semibold mb-3"><i class="fa-solid fa-user me-2"></i>Data Pengirim</h6>
                            <p><strong>Nama:</strong> <?= htmlspecialchars($pengiriman['nama_pengirim']); ?></p>
                            <p><strong>Telepon:</strong> <?= htmlspecialchars($pengiriman['telp_pengirim'] ?? '-'); ?></p>
                            <p><strong>Cabang Asal:</strong> 
                                <span class="badge bg-primary"><?= htmlspecialchars($pengiriman['cabang_pengirim']); ?></span>
                            </p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body p-4">
                            <h6 class="fw-semibold mb-3"><i class="fa-solid fa-user me-2"></i>Data Penerima</h6>
                            <p><strong>Nama:</strong> <?= htmlspecialchars($pengiriman['nama_penerima']); ?></p>
                            <p><strong>Telepon:</strong> <?= htmlspecialchars($pengiriman['telp_penerima'] ?? '-'); ?></p>
                            <p><strong>Cabang Tujuan:</strong> 
                                <span class="badge bg-success"><?= htmlspecialchars($pengiriman['cabang_penerima']); ?></span>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Timeline + Pengambilan -->
            <div class="row g-3 mb-4 text-capitalize d-flex align-items-stretch">
                <?php include '../../../components/logStatusPengiriman.php'; ?>
                <div class="col-md-6 d-flex">
                    <div class="card border-0 shadow-sm h-100 flex-fill">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center mb-3">
                                <div class="bg-info bg-opacity-10 rounded p-2 me-3">
                                    <i class="fa-solid fa-box-open"></i>
                                </div>
                                <h6 class="mb-0 fw-semibold">Data Pengambilan Barang</h6>
                            </div>

                            <?php if ($pengambilanData): ?>
                                <div class="mb-3">
                                    <small class="text-muted d-block mb-1">Nama Pengambil</small>
                                    <p class="mb-0 fw-semibold"><?= htmlspecialchars($pengambilanData['nama_pengambil']); ?></p>
                                </div>
                                <div class="mb-3">
                                    <small class="text-muted d-block mb-1">Nomor Telepon</small>
                                    <p class="mb-0"><?= htmlspecialchars($pengambilanData['telp_pengambil']); ?></p>
                                </div>
                                <div>
                                    <small class="text-muted d-block mb-1">Tanggal Pengambilan</small>
                                    <p class="mb-0"><?= date('d/m/Y H:i', strtotime($pengambilanData['tanggal'])); ?></p>
                                </div>
                            <?php else: ?>
                                <div class="text-muted text-center pt-4">
                                    <i class="fa-solid fa-circle-info mb-2 d-block"></i>
                                    <p class="mb-0">Belum ada data pengambilan barang.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
  </div>
</div>

<!-- Modal -->
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
            <input type="text" name="nama_pengambil" class="form-control" required placeholder="Masukkan nama pengambil">
          </div>

          <div class="mb-3">
            <label class="form-label fw-semibold">Nomor Telepon <span class="text-danger">*</span></label>
            <input type="tel" name="telp_pengambil" class="form-control" 
                   required pattern="^(\+62|0)[0-9]{9,14}$"
                   title="Nomor telepon harus diawali +62 atau 0 dan terdiri dari 10–15 digit angka"
                   placeholder="contoh: 081234567890">
          </div>
        </div>

        <div class="modal-footer border-0 pt-0">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-success">Simpan & Tandai POD</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include '../../../templates/footer.php'; ?>
