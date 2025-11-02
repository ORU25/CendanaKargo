<?php
session_start();

if(!isset($_SESSION['username'] )|| !isset($_SESSION['user_id'])){
    header("Location: ../../../auth/login.php");
    exit;
}

if(isset($_SESSION['role']) && $_SESSION['role'] !== 'superAdmin'){
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

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}


$sql_sj = "SELECT * FROM Surat_jalan WHERE id = ? AND id_cabang_pengirim = ?";
$stmt_sj = $conn->prepare($sql_sj);
$stmt_sj->bind_param("ii", $id_surat_jalan, $user_cabang_id);


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
                                if ($status == 'diberangkatkan') $status_badge = 'primary';
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


<?php include '../../../templates/footer.php'; ?>