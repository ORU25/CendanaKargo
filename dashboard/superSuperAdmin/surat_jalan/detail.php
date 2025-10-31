<?php
session_start();

if(!isset($_SESSION['username'] )|| !isset($_SESSION['user_id'])){
    header("Location: ../../../auth/login.php");
    exit;
}

if(isset($_SESSION['role']) && $_SESSION['role'] !== 'superSuperAdmin'){
    header("Location: ../../../?error=unauthorized");
    exit;
}

$user_role = $_SESSION['role'];
$user_cabang_id = $_SESSION['id_cabang'] ?? null;


include '../../../config/database.php';

$id_surat_jalan = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id_surat_jalan == 0) {
    header("Location: index?error=not_found");
    exit;
}

// ===== UPDATE STATUS (POST) =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('CSRF token validation failed.');
    }

    $status_baru = $_POST['status_baru'] ?? '';

    $valid_statuses = ['draft', 'dalam perjalanan', 'sampai tujuan', 'dibatalkan'];
    if (empty($status_baru) || !in_array($status_baru, $valid_statuses, true)) {
        header("Location: detail.php?id=$id_surat_jalan&error=" . urlencode("Status tidak valid"));
        exit;
    }

    // Query disesuaikan untuk SuperSuperAdmin
    if ($user_role === 'superSuperAdmin') {
        $sql_check = "SELECT status, no_surat_jalan FROM Surat_jalan WHERE id = ?";
        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->bind_param("i", $id_surat_jalan);
    } else {
        $sql_check = "SELECT status, no_surat_jalan FROM Surat_jalan WHERE id = ? AND (id_cabang_pengirim = ? OR id_cabang_penerima = ?)";
        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->bind_param("iii", $id_surat_jalan, $user_cabang_id, $user_cabang_id);
    }
    
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    $sj_current = $result_check->fetch_assoc();

    if (!$sj_current) {
        header("Location: index?error=not_found");
        exit;
    }

    if ($sj_current['status'] === 'dibatalkan') {
        header("Location: detail.php?id=$id_surat_jalan&error=" . urlencode("Surat yang dibatalkan tidak bisa di-update"));
        exit;
    }

    mysqli_begin_transaction($conn);

    try {
        $status_lama = $sj_current['status'];
        $no_surat_jalan = $sj_current['no_surat_jalan'];
        $keterangan = trim($_POST['keterangan'] ?? '');
        $user_id = $_SESSION['user_id'];
        $username = $_SESSION['username'];

        $sql_update_sj = "UPDATE Surat_jalan SET status = ? WHERE id = ?";
        $stmt_update_sj = $conn->prepare($sql_update_sj);
        
        if (!$stmt_update_sj) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt_update_sj->bind_param("si", $status_baru, $id_surat_jalan);
        
        if (!$stmt_update_sj->execute()) {
            throw new Exception("Execute failed: " . $stmt_update_sj->error);
        }

        // Insert log status surat jalan
        $sql_log = "INSERT INTO log_surat_jalan (id_surat_jalan, id_user, status_lama, status_baru, keterangan, username) 
                    VALUES (?, ?, ?, ?, ?, ?)";
        $stmt_log = $conn->prepare($sql_log);
        $stmt_log->bind_param("iissss", $id_surat_jalan, $user_id, $status_lama, $status_baru, $keterangan, $username);
        $stmt_log->execute();

        // Update status pengiriman dan insert log untuk setiap pengiriman
        if ($status_baru === 'dalam perjalanan') {
            // Ambil semua pengiriman dalam surat jalan dengan status saat ini
            $sql_get_pengiriman = "SELECT p.id, p.status FROM Pengiriman p 
                                    JOIN detail_surat_jalan d ON p.id = d.id_pengiriman 
                                    WHERE d.id_surat_jalan = ?";
            $stmt_get = $conn->prepare($sql_get_pengiriman);
            $stmt_get->bind_param("i", $id_surat_jalan);
            $stmt_get->execute();
            $result_pengiriman = $stmt_get->get_result();
            
            while ($pengiriman = $result_pengiriman->fetch_assoc()) {
                $id_pengiriman = $pengiriman['id'];
                $status_pengiriman_lama = $pengiriman['status'];
                $status_pengiriman_baru = 'dalam pengiriman';
                
                // Update status pengiriman
                $sql_update = "UPDATE Pengiriman SET status = ? WHERE id = ?";
                $stmt_update = $conn->prepare($sql_update);
                $stmt_update->bind_param("si", $status_pengiriman_baru, $id_pengiriman);
                $stmt_update->execute();
                
                // Insert log status pengiriman
                $log_keterangan = "Status diubah otomatis dari surat jalan #" . $no_surat_jalan;
                $sql_log_pengiriman = "INSERT INTO log_status_pengiriman (id_pengiriman, status_lama, status_baru, keterangan, diubah_oleh) 
                                        VALUES (?, ?, ?, ?, ?)";
                $stmt_log_p = $conn->prepare($sql_log_pengiriman);
                $stmt_log_p->bind_param("isssi", $id_pengiriman, $status_pengiriman_lama, $status_pengiriman_baru, $log_keterangan, $user_id);
                $stmt_log_p->execute();
            }
        } elseif ($status_baru === 'sampai tujuan') {
            // Ambil semua pengiriman dalam surat jalan dengan status saat ini
            $sql_get_pengiriman = "SELECT p.id, p.status FROM Pengiriman p 
                                    JOIN detail_surat_jalan d ON p.id = d.id_pengiriman 
                                    WHERE d.id_surat_jalan = ?";
            $stmt_get = $conn->prepare($sql_get_pengiriman);
            $stmt_get->bind_param("i", $id_surat_jalan);
            $stmt_get->execute();
            $result_pengiriman = $stmt_get->get_result();
            
            while ($pengiriman = $result_pengiriman->fetch_assoc()) {
                $id_pengiriman = $pengiriman['id'];
                $status_pengiriman_lama = $pengiriman['status'];
                $status_pengiriman_baru = 'sampai tujuan';
                
                // Update status pengiriman
                $sql_update = "UPDATE Pengiriman SET status = ? WHERE id = ?";
                $stmt_update = $conn->prepare($sql_update);
                $stmt_update->bind_param("si", $status_pengiriman_baru, $id_pengiriman);
                $stmt_update->execute();
                
                // Insert log status pengiriman
                $log_keterangan = "Status diubah otomatis dari surat jalan #" . $no_surat_jalan;
                $sql_log_pengiriman = "INSERT INTO log_status_pengiriman (id_pengiriman, status_lama, status_baru, keterangan, diubah_oleh) 
                                        VALUES (?, ?, ?, ?, ?)";
                $stmt_log_p = $conn->prepare($sql_log_pengiriman);
                $stmt_log_p->bind_param("isssi", $id_pengiriman, $status_pengiriman_lama, $status_pengiriman_baru, $log_keterangan, $user_id);
                $stmt_log_p->execute();
            }
        } elseif ($status_baru === 'dibatalkan') {
            // Ambil semua pengiriman dalam surat jalan dengan status saat ini
            $sql_get_pengiriman = "SELECT p.id, p.status FROM Pengiriman p 
                                    JOIN detail_surat_jalan d ON p.id = d.id_pengiriman 
                                    WHERE d.id_surat_jalan = ?";
            $stmt_get = $conn->prepare($sql_get_pengiriman);
            $stmt_get->bind_param("i", $id_surat_jalan);
            $stmt_get->execute();
            $result_pengiriman = $stmt_get->get_result();
            
            while ($pengiriman = $result_pengiriman->fetch_assoc()) {
                $id_pengiriman = $pengiriman['id'];
                $status_pengiriman_lama = $pengiriman['status'];
                $status_pengiriman_baru = 'bkd';
                
                // Update status pengiriman
                $sql_update = "UPDATE Pengiriman SET status = ? WHERE id = ?";
                $stmt_update = $conn->prepare($sql_update);
                $stmt_update->bind_param("si", $status_pengiriman_baru, $id_pengiriman);
                $stmt_update->execute();
                
                // Insert log status pengiriman
                $log_keterangan = "Status dikembalikan ke BKD karena surat jalan #" . $no_surat_jalan . " dibatalkan";
                $sql_log_pengiriman = "INSERT INTO log_status_pengiriman (id_pengiriman, status_lama, status_baru, keterangan, diubah_oleh) 
                                        VALUES (?, ?, ?, ?, ?)";
                $stmt_log_p = $conn->prepare($sql_log_pengiriman);
                $stmt_log_p->bind_param("isssi", $id_pengiriman, $status_pengiriman_lama, $status_pengiriman_baru, $log_keterangan, $user_id);
                $stmt_log_p->execute();
            }
        }

        mysqli_commit($conn);
        unset($_SESSION['csrf_token']);
        header("Location: detail.php?id=$id_surat_jalan&success=status_updated");
        exit;

    } catch (Exception $e) {
        mysqli_rollback($conn);
        header("Location: detail.php?id=$id_surat_jalan&error=" . urlencode($e->getMessage()));
        exit;
    }
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}


$sql_sj = "SELECT * FROM Surat_jalan WHERE id = ?";
$stmt_sj = $conn->prepare($sql_sj);
$stmt_sj->bind_param("i", $id_surat_jalan);


$stmt_sj->execute();
$result_sj = $stmt_sj->get_result();
$sj = $result_sj->fetch_assoc();

if (!$sj) {
    header("Location: index?error=not_found");
    exit;
}

$sql_detail = "SELECT p.* FROM Pengiriman p JOIN detail_surat_jalan d ON p.id = d.id_pengiriman WHERE d.id_surat_jalan = ? ORDER BY p.tanggal DESC";
$stmt_detail = $conn->prepare($sql_detail);
$stmt_detail->bind_param("i", $id_surat_jalan);
$stmt_detail->execute();
$result_detail = $stmt_detail->get_result();
$pengirimens = ($result_detail->num_rows > 0) ? $result_detail->fetch_all(MYSQLI_ASSOC) : [];

// Query log status surat jalan
$sql_logs = "SELECT * FROM log_surat_jalan WHERE id_surat_jalan = ? ORDER BY tanggal DESC";
$stmt_logs = $conn->prepare($sql_logs);
$stmt_logs->bind_param("i", $id_surat_jalan);
$stmt_logs->execute();
$result_logs = $stmt_logs->get_result();
$logs_sj = ($result_logs->num_rows > 0) ? $result_logs->fetch_all(MYSQLI_ASSOC) : [];

$page = "surat_jalan";
$title = "Detail Surat Jalan - Cendana Kargo";
?>

<?php include '../../../templates/header.php'; ?>
<?php include '../../../components/navDashboard.php'; ?>
<?php include '../../../components/sidebar_offcanvas.php'; ?>

<div class="container-fluid">
    <div class="row">
        <?php include '../../../components/sidebar.php'; ?>

        <div class="col-lg-10 col-12 p-4">
            <div class="container-fluid">

                <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fa-solid fa-check-circle me-2"></i>Status Surat Jalan berhasil di-update!
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($_GET['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fa-solid fa-circle-exclamation me-2"></i><?= htmlspecialchars(urldecode($_GET['error'])); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <!-- Header -->
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
                    <div>
                        <h1 class="h4 mb-1 fw-bold">Detail Surat Jalan</h1>
                        <p class="text-muted small mb-0">Dibuat oleh: <span class="fw-semibold"><?= htmlspecialchars($sj['user']); ?></span></p>
                        <p class="text-muted small mb-0">No. Surat Jalan: <span class="fw-semibold"><?= htmlspecialchars($sj['no_surat_jalan']); ?></span></p>
                    </div>
                    <div class="d-flex gap-2 mt-2 mt-md-0">
                        <?php if ($sj['status'] !== 'dibatalkan'): ?>
                            <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#modalUpdateStatus">
                                Update Status
                            </button>
                        <?php endif; ?>
                        <a href="index.php" class="btn btn-sm btn-outline-secondary">Kembali</a>
                    </div>
                </div>

                <!-- Hero Card -->
                <div class="card border-0 shadow mb-4 text-capitalize">
                    <div class="card-body p-4">
                        <div class="row">
                            <div class="col-md-8">
                                <h5 class="fw-bold mb-3">Surat Jalan #<?= htmlspecialchars($sj['no_surat_jalan']); ?></h5>
                                <div class="row g-3">
                                    <div class="col-6 col-md-3">
                                        <small class="opacity-75 d-block">Tanggal</small>
                                        <strong><?= date('d/m/Y', strtotime($sj['tanggal'])); ?></strong>
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <small class="opacity-75 d-block">Driver</small>
                                        <strong><?= htmlspecialchars($sj['driver']); ?></strong>
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <small class="opacity-75 d-block">Cabang Asal</small>
                                        <strong><?= htmlspecialchars($sj['cabang_pengirim']); ?></strong>
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <small class="opacity-75 d-block">Cabang Tujuan</small>
                                        <strong><?= htmlspecialchars($sj['cabang_penerima']); ?></strong>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 mt-3 mt-md-0 text-md-end">
                                <small class="opacity-75 d-block mb-2">Total Pengiriman</small>
                                <h3 class="fw-bold mb-2"><?= count($pengirimens); ?> Items</h3>
                                <?php
                                $status = $sj['status'];
                                $status_badge = 'secondary';
                                if ($status == 'draft') $status_badge = 'warning';
                                if ($status == 'dalam perjalanan') $status_badge = 'primary';
                                if ($status == 'sampai tujuan') $status_badge = 'success';
                                if ($status == 'dibatalkan') $status_badge = 'danger';
                                ?>
                                <span class="text-uppercase px-3 py-2 badge rounded-pill text-bg-<?= $status_badge; ?>"><?= htmlspecialchars($status); ?></span>
                            </div>
                        </div>
                    </div>
                </div>


                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-0 py-3">
                        <h6 class="mb-0 fw-semibold">
                            <i class="fa-solid fa-box me-2"></i>Daftar Pengiriman
                            <span class="badge bg-primary"><?= count($pengirimens); ?></span>
                        </h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="px-4">No. Resi</th>
                                        <th>Nama Barang</th>
                                        <th>Penerima</th>
                                        <th class="text-center">Berat (Kg)</th>
                                        <th class="text-center">Jumlah</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($pengirimens)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center text-muted py-5">
                                                <i class="fa-solid fa-box-open fs-1 mb-3 opacity-25"></i>
                                                <p class="mb-0 fw-semibold">Tidak ada data pengiriman</p>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($pengirimens as $p): ?>
                                            <tr>
                                                <td class="px-4">
                                                    <span class="fw-bold"><?= htmlspecialchars($p['no_resi']); ?></span>
                                                </td>
                                                <td><?= htmlspecialchars($p['nama_barang']); ?></td>
                                                <td>
                                                    <div>
                                                        <strong><?= htmlspecialchars($p['nama_penerima']); ?></strong>
                                                        <br>
                                                        <small class="text-muted"><?= htmlspecialchars($p['telp_penerima']); ?></small>
                                                    </div>
                                                </td>
                                                <td class="text-center"><?= htmlspecialchars($p['berat']); ?> Kg</td>
                                                <td class="text-center"><?= htmlspecialchars($p['jumlah']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mb-4 text-capitalize">
                    <!-- Timeline Log Status -->
                    <?php include '../../../components/logStatusSuratJalan.php'; ?>
                </div>

                <div class="d-flex justify-content-end">
                    <a href="surat?id=<?= (int)$sj['id']; ?>" class="btn btn-md btn-secondary" target="_blank">
                        <i class="fa-solid fa-receipt"></i>
                        Cetak Surat
                    </a>
                </div>

            </div>
        </div>
    </div>
</div>

<!-- Modal Update Status -->
<div class="modal fade" id="modalUpdateStatus" tabindex="-1" aria-labelledby="modalUpdateStatusLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <form method="POST" action="detail.php?id=<?= $id_surat_jalan; ?>">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">
                
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold" id="modalUpdateStatusLabel">Update Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <div class="modal-body px-4 py-3">
                    <div class="mb-3">
                        <label class="form-label small text-muted">Status Saat Ini</label>
                        <div class="p-3 bg-light rounded">
                            <?php
                            $current_status = $sj['status'];
                            $currentBadgeClass = 'secondary';
                            if ($current_status === 'draft') $currentBadgeClass = 'warning';
                            if ($current_status === 'dalam perjalanan') $currentBadgeClass = 'primary';
                            if ($current_status === 'sampai tujuan') $currentBadgeClass = 'success';
                            if ($current_status === 'dibatalkan') $currentBadgeClass = 'danger';
                            ?>
                            <span class="text-uppercase badge bg-<?= $currentBadgeClass; ?>"><?= htmlspecialchars($sj['status']); ?></span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="statusBaru" class="form-label fw-semibold">Status Baru <span class="text-danger">*</span></label>
                        <select class="form-select form-select-lg" id="statusBaru" name="status_baru" required>
                            <option value="">-- Pilih Status --</option>
                            <?php
                            if ($current_status === 'draft') {
                                echo '<option value="dalam perjalanan">Dalam Perjalanan</option>';
                                echo '<option value="sampai tujuan">Sampai Tujuan</option>';
                                echo '<option value="dibatalkan">Dibatalkan</option>';
                            } elseif ($current_status === 'dalam perjalanan') {
                                echo '<option value="sampai tujuan">Sampai Tujuan</option>';
                                echo '<option value="dibatalkan">Dibatalkan</option>';
                            } elseif ($current_status === 'sampai tujuan') {
                                echo '<option value="dibatalkan">Dibatalkan</option>';
                            }
                            ?>
                        </select>
                        <small class="form-text text-muted">Pilih status baru untuk surat jalan.</small>
                    </div>

                    <div class="mb-3">
                        <label for="keterangan" class="form-label fw-semibold">Keterangan (Opsional)</label>
                        <textarea class="form-control" id="keterangan" name="keterangan" rows="3" placeholder="Tambahkan catatan atau keterangan..."></textarea>
                    </div>
                </div>
                
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-warning">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../../../templates/footer.php'; ?>