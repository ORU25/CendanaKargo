<?php
session_start();

if (!isset($_SESSION['username'])) {
    header("Location: ../../../auth/login.php");
    exit;
}

$user_role = $_SESSION['role'] ?? '';
$user_cabang_id = $_SESSION['id_cabang'] ?? null;

if (!in_array($user_role, ['superAdmin', 'admin'])) {
    header("Location: ../../../?error=unauthorized_global");
    exit;
}

if ($user_cabang_id === null || $user_cabang_id == 0) {
    header("Location: ../../../?error=unauthorized_global");
    exit;
}

include '../../../config/database.php';

$id_surat_jalan = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id_surat_jalan == 0) {
    header("Location: index.php?error=invalid_id");
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

    $sql_check = "SELECT status FROM Surat_jalan WHERE id = ? AND (id_cabang_pengirim = ? OR id_cabang_penerima = ?)";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->bind_param("iii", $id_surat_jalan, $user_cabang_id, $user_cabang_id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    $sj_current = $result_check->fetch_assoc();

    if (!$sj_current) {
        header("Location: index.php?error=not_found");
        exit;
    }

    if ($sj_current['status'] === 'dibatalkan') {
        header("Location: detail.php?id=$id_surat_jalan&error=" . urlencode("Surat yang dibatalkan tidak bisa di-update"));
        exit;
    }

    mysqli_begin_transaction($conn);

    try {
        $sql_update_sj = "UPDATE Surat_jalan SET status = ? WHERE id = ?";
        $stmt_update_sj = $conn->prepare($sql_update_sj);
        
        if (!$stmt_update_sj) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt_update_sj->bind_param("si", $status_baru, $id_surat_jalan);
        
        if (!$stmt_update_sj->execute()) {
            throw new Exception("Execute failed: " . $stmt_update_sj->error . " | Status: " . $status_baru . " | Length: " . strlen($status_baru));
        }

        if ($status_baru === 'dalam perjalanan') {
            $sql_update_pengiriman = "UPDATE Pengiriman SET status = 'dalam pengiriman' WHERE id IN (SELECT id_pengiriman FROM detail_surat_jalan WHERE id_surat_jalan = ?)";
            $stmt_update_pengiriman = $conn->prepare($sql_update_pengiriman);
            $stmt_update_pengiriman->bind_param("i", $id_surat_jalan);
            $stmt_update_pengiriman->execute();
        } elseif ($status_baru === 'sampai tujuan') {
            $sql_update_pengiriman = "UPDATE Pengiriman SET status = 'sampai tujuan' WHERE id IN (SELECT id_pengiriman FROM detail_surat_jalan WHERE id_surat_jalan = ?)";
            $stmt_update_pengiriman = $conn->prepare($sql_update_pengiriman);
            $stmt_update_pengiriman->bind_param("i", $id_surat_jalan);
            $stmt_update_pengiriman->execute();
        } elseif ($status_baru === 'dibatalkan') {
            $sql_update_pengiriman = "UPDATE Pengiriman SET status = 'bkd' WHERE id IN (SELECT id_pengiriman FROM detail_surat_jalan WHERE id_surat_jalan = ?)";
            $stmt_update_pengiriman = $conn->prepare($sql_update_pengiriman);
            $stmt_update_pengiriman->bind_param("i", $id_surat_jalan);
            $stmt_update_pengiriman->execute();
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

$sql_sj = "SELECT * FROM Surat_jalan WHERE id = ? AND (id_cabang_pengirim = ? OR id_cabang_penerima = ?)";
$stmt_sj = $conn->prepare($sql_sj);
$stmt_sj->bind_param("iii", $id_surat_jalan, $user_cabang_id, $user_cabang_id);
$stmt_sj->execute();
$result_sj = $stmt_sj->get_result();
$sj = $result_sj->fetch_assoc();

if (!$sj) {
    header("Location: index.php?error=not_found");
    exit;
}

$sql_detail = "SELECT p.* FROM Pengiriman p JOIN detail_surat_jalan d ON p.id = d.id_pengiriman WHERE d.id_surat_jalan = ? ORDER BY p.tanggal DESC";
$stmt_detail = $conn->prepare($sql_detail);
$stmt_detail->bind_param("i", $id_surat_jalan);
$stmt_detail->execute();
$result_detail = $stmt_detail->get_result();
$pengirimens = ($result_detail->num_rows > 0) ? $result_detail->fetch_all(MYSQLI_ASSOC) : [];

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

                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                    <div>
                        <a href="index.php" class="btn btn-outline-secondary btn-sm mb-2">
                            <i class="fa-solid fa-arrow-left me-2"></i>Kembali
                        </a>
                        <h4 class="fw-bold text-danger mb-1">
                            <i class="fa-solid fa-file-invoice me-2"></i>Detail Surat Jalan
                        </h4>
                        <small class="text-muted"><strong><?= htmlspecialchars($sj['no_surat_jalan']); ?></strong></small>
                    </div>
                    <div>
                        <a href="surat_jalan.php?id=<?= $sj['id']; ?>" class="btn btn-sm btn-info text-white">
                            <i class="fa-solid fa-print me-2"></i>Cetak
                        </a>
                        <?php if ($sj['status'] !== 'dibatalkan'): ?>
                            <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#modalUpdateStatus">
                                <i class="fa-solid fa-pen-to-square me-2"></i>Update Status
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card shadow-sm border-0 rounded-3 mb-4">
                    <div class="card-body p-4">
                        <div class="row">
                            <div class="col-md-6 col-lg-3 mb-3">
                                <small class="text-muted d-block">No. Surat Jalan</small>
                                <strong class="fs-5"><?= htmlspecialchars($sj['no_surat_jalan']); ?></strong>
                            </div>
                            <div class="col-md-6 col-lg-3 mb-3">
                                <small class="text-muted d-block">Driver</small>
                                <strong class="fs-5"><?= htmlspecialchars($sj['driver']); ?></strong>
                            </div>
                            <div class="col-md-6 col-lg-3 mb-3">
                                <small class="text-muted d-block">Tanggal Dibuat</small>
                                <strong class="fs-5"><?= htmlspecialchars(date('d M Y H:i', strtotime($sj['tanggal']))); ?></strong>
                            </div>
                            <div class="col-md-6 col-lg-3 mb-3">
                                <small class="text-muted d-block">Status</small>
                                <?php
                                $status = $sj['status'];
                                $status_badge = 'secondary';
                                if ($status == 'draft') $status_badge = 'warning';
                                if ($status == 'dalam perjalanan') $status_badge = 'primary';
                                if ($status == 'sampai tujuan') $status_badge = 'success';
                                if ($status == 'dibatalkan') $status_badge = 'danger';
                                ?>
                                <span class="badge bg-<?= $status_badge; ?> fs-6"><?= htmlspecialchars(ucfirst($status)); ?></span>
                            </div>
                            <div class="col-md-6 col-lg-3">
                                <small class="text-muted d-block">Cabang Asal</small>
                                <strong><?= htmlspecialchars($sj['cabang_pengirim']); ?></strong>
                            </div>
                            <div class="col-md-6 col-lg-3">
                                <small class="text-muted d-block">Cabang Tujuan</small>
                                <strong><?= htmlspecialchars($sj['cabang_penerima']); ?></strong>
                            </div>
                            <div class="col-md-6 col-lg-3">
                                <small class="text-muted d-block">Dibuat Oleh</small>
                                <strong><?= htmlspecialchars($sj['user']); ?></strong>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm border-0 rounded-3">
                    <div class="card-header bg-white border-bottom py-3">
                        <h6 class="fw-bold text-danger mb-0">
                            <i class="fa-solid fa-box me-2"></i>Daftar Pengiriman
                            <span class="badge bg-danger"><?= count($pengirimens); ?></span>
                        </h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive border rounded-3">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="px-4">No. Resi</th>
                                        <th>Nama Barang</th>
                                        <th>Penerima</th>
                                        <th class="text-center">Berat (Kg)</th>
                                        <th class="text-center">Jumlah</th>
                                        <th class="text-center">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($pengirimens)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center text-muted py-5">
                                                <p class="mb-0 fw-semibold">Tidak ada data pengiriman</p>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($pengirimens as $p): ?>
                                            <tr>
                                                <td class="px-4 fw-semibold"><?= htmlspecialchars($p['no_resi']); ?></td>
                                                <td><?= htmlspecialchars($p['nama_barang']); ?></td>
                                                <td>
                                                    <div>
                                                        <strong><?= htmlspecialchars($p['nama_penerima']); ?></strong>
                                                        <br>
                                                        <small class="text-muted"><?= htmlspecialchars($p['telp_penerima']); ?></small>
                                                    </div>
                                                </td>
                                                <td class="text-center"><?= htmlspecialchars($p['berat']); ?> Kg</td>
                                                <td class="text-center"><?= htmlspecialchars($p['jumlah']); ?> Koli</td>
                                                <td class="text-center">
                                                    <?php
                                                    $p_status = $p['status'];
                                                    $p_badge = 'secondary';
                                                    if ($p_status == 'bkd') $p_badge = 'success';
                                                    if ($p_status == 'dalam pengiriman') $p_badge = 'primary';
                                                    if ($p_status == 'sampai tujuan') $p_badge = 'success';
                                                    ?>
                                                    <span class="badge bg-<?= $p_badge; ?>"><?= htmlspecialchars(ucfirst($p_status)); ?></span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<!-- Modal Update Status -->
<div class="modal fade" id="modalUpdateStatus" tabindex="-1" aria-labelledby="modalUpdateStatusLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-3">
            <form method="POST" action="detail.php?id=<?= $id_surat_jalan; ?>">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">
                
                <div class="modal-header border-bottom">
                    <h5 class="modal-title fw-bold" id="modalUpdateStatusLabel">
                        <i class="fa-solid fa-pen-to-square me-2"></i>Update Status Surat Jalan
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <div class="modal-body p-4">
                    <div class="mb-4">
                        <label class="form-label fw-semibold small mb-2">Status Saat Ini</label>
                        <input type="text" class="form-control border-2 bg-light" value="<?= htmlspecialchars(ucfirst($sj['status'])); ?>" disabled>
                    </div>

                    <div class="mb-4">
                        <label for="statusBaru" class="form-label fw-semibold small mb-2">Status Baru</label>
                        <select class="form-select border-2" id="statusBaru" name="status_baru" required>
                            <option value="">-- Pilih Status Baru --</option>
                            <?php
                            $current_status = $sj['status'];
                            
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
                    </div>

                    <div class="alert alert-info border-0 rounded-2 small mb-0">
                        <i class="fa-solid fa-info-circle me-2"></i>
                        <strong>Info:</strong> Perubahan status akan memperbarui status pengiriman secara otomatis.
                    </div>
                </div>
                
                <div class="modal-footer border-top p-4">
                    <button type="button" class="btn btn-outline-secondary fw-semibold" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success fw-semibold">
                        <i class="fa-solid fa-save me-2"></i>Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../../../templates/footer.php'; ?>