<?php
session_start();

// Cek autentikasi
if (!isset($_SESSION['username'])) {
    header("Location: ../../../auth/login.php");
    exit;
}

$user_role = $_SESSION['role'] ?? '';
$user_cabang_id = $_SESSION['id_cabang'] ?? null;

// Cek role
if (!in_array($user_role, ['superAdmin', 'admin'])) {
    header("Location: ../../../?error=unauthorized_global");
    exit;
}

// Admin dan SuperAdmin harus punya cabang_id
if ($user_cabang_id === null || $user_cabang_id == 0) {
    header("Location: ../../../?error=unauthorized_global");
    exit;
}

include '../../../config/database.php';

// Ambil ID dari URL
$id_surat_jalan = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id_surat_jalan == 0) {
    header("Location: index.php?error=invalid_id");
    exit;
}

// Query 1: Ambil data Surat Jalan
// Filter: hanya surat jalan dari cabang user (asal atau tujuan)
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

// Query 2: Ambil data Pengiriman yang terkait
$sql_detail = "SELECT p.*
FROM Pengiriman p
JOIN detail_surat_jalan d ON p.id = d.id_pengiriman
WHERE d.id_surat_jalan = ?
ORDER BY p.tanggal DESC";
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
                        Status Surat Jalan berhasil di-update!
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
                        <small class="text-muted">
                            <strong><?= htmlspecialchars($sj['no_surat_jalan']); ?></strong>
                        </small>
                    </div>
                    <div>
                        <a href="surat_jalan.php?id=<?= $sj['id']; ?>" class="btn btn-sm btn-info text-white">
                            <i class="fa-solid fa-print me-2"></i>Cetak
                        </a>
                        <a href="update.php?id=<?= $sj['id']; ?>" class="btn btn-sm btn-warning">
                            <i class="fa-solid fa-pen-to-square me-2"></i>Update Status
                        </a>
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
                                if ($status == 'dalam perjalanan') $status_badge = 'primary';
                                if ($status == 'sampai tujuan') $status_badge = 'success';
                                if ($status == 'dibatalkan') $status_badge = 'danger';
                                ?>
                                <span class="badge bg-<?= $status_badge; ?> fs-6"><?= htmlspecialchars($status); ?></span>
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
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($pengirimens)): ?>
                                        <tr>
                                            <td colspan="5" class="text-center text-muted py-5">
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

<?php include '../../../templates/footer.php'; ?>