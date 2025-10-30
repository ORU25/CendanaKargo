<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: ../../../auth/login.php");
    exit;
}

$user_role = $_SESSION['role'] ?? '';
$user_id = $_SESSION['user_id'] ?? null;
$user_cabang_id = $_SESSION['id_cabang'] ?? null;

if (!in_array($user_role, ['superSuperAdmin', 'superAdmin', 'admin'])) {
    header("Location: ../../../?error=unauthorized_global");
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

include '../../../config/database.php';

$sql_sj = "SELECT * FROM Surat_jalan ORDER BY tanggal DESC";
$result_sj = $conn->query($sql_sj);
$surat_jalans = ($result_sj->num_rows > 0) ? $result_sj->fetch_all(MYSQLI_ASSOC) : [];

$sql_cabang_modal = "SELECT id, kode_cabang, nama_cabang FROM Kantor_cabang ORDER BY nama_cabang ASC";
$result_cabang_modal = $conn->query($sql_cabang_modal);
$cabangs_modal = ($result_cabang_modal->num_rows > 0) ? $result_cabang_modal->fetch_all(MYSQLI_ASSOC) : [];

$page = "surat_jalan";
$title = "Surat Jalan - Cendana Kargo";
?>

<?php include '../../../templates/header.php'; ?>
<?php include '../../../components/navDashboard.php'; ?>
<?php include '../../../components/sidebar_offcanvas.php'; ?>

<div class="container-fluid">
    <div class="row">
        <?php include '../../../components/sidebar.php'; ?>
        
        <div class="col-lg-10 col-12 p-4">
            <div class="container-fluid">
                
                <div class="mb-4">
                    <h5 class="fw-bold mb-2">Surat Jalan</h5>
                    <div class="d-flex justify-content-between align-items-center">
                        <div></div>
                        <button type="button" class="btn btn-success fw-semibold px-4" data-bs-toggle="modal" data-bs-target="#modalPilihCabang">
                            <i class="fa-solid fa-plus me-2"></i>Tambah Surat Jalan
                        </button>
                    </div>
                </div>

                <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fa-solid fa-check-circle me-2"></i>Surat Jalan berhasil dibuat!
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="card border-0 rounded-3 shadow-sm">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light border-bottom">
                                    <tr>
                                        <th class="px-4 py-3" style="width: 5%;">ID</th>
                                        <th class="px-3 py-3">No Surat</th>
                                        <th class="px-3 py-3">Asal</th>
                                        <th class="px-3 py-3">Tujuan</th>
                                        <th class="px-3 py-3">Driver</th>
                                        <th class="px-3 py-3">Tanggal</th>
                                        <th class="px-3 py-3 text-center">Status</th>
                                        <th class="px-3 py-3 text-center" style="width: 60px;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($surat_jalans)): ?>
                                        <?php $no = 1; foreach ($surat_jalans as $sj): ?>
                                            <tr>
                                                <td class="px-4 py-3 text-muted small"><?= $no++; ?></td>
                                                <td class="px-3 py-3 fw-semibold"><?= htmlspecialchars($sj['no_surat_jalan']); ?></td>
                                                <td class="px-3 py-3"><?= htmlspecialchars($sj['cabang_pengirim']); ?></td>
                                                <td class="px-3 py-3"><?= htmlspecialchars($sj['cabang_penerima']); ?></td>
                                                <td class="px-3 py-3"><?= htmlspecialchars($sj['driver']); ?></td>
                                                <td class="px-3 py-3"><?= htmlspecialchars(date('Y-m-d', strtotime($sj['tanggal']))); ?></td>
                                                <td class="px-3 py-3 text-center">
                                                    <?php
                                                    $status = $sj['status'];
                                                    $status_badge = 'secondary';
                                                    $status_text = $status;
                                                    if ($status == 'dalam perjalanan') {
                                                        $status_badge = 'primary';
                                                        $status_text = 'Dikirim';
                                                    }
                                                    if ($status == 'sampai tujuan') {
                                                        $status_badge = 'success';
                                                        $status_text = 'Terima';
                                                    }
                                                    if ($status == 'dibatalkan') {
                                                        $status_badge = 'danger';
                                                        $status_text = 'Batal';
                                                    }
                                                    ?>
                                                    <span class="badge bg-<?= $status_badge; ?> text-white" style="padding: 6px 12px; font-size: 12px;">
                                                        <?= htmlspecialchars($status_text); ?>
                                                    </span>
                                                </td>
                                                <td class="px-3 py-3 text-center">
                                                    <a href="detail.php?id=<?= $sj['id']; ?>" class="btn btn-sm btn-link text-primary text-decoration-none p-0" title="Lihat Detail" style="width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center;">
                                                        <i class="fa-solid fa-eye fs-5"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="8" class="text-center text-muted py-5">
                                                <i class="fa-solid fa-inbox fa-3x mb-3 d-block opacity-50"></i>
                                                <p class="mb-0 fw-semibold">Belum ada surat jalan</p>
                                                <small>Mulai dengan menambahkan surat jalan baru</small>
                                            </td>
                                        </tr>
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

<div class="modal fade" id="modalPilihCabang" tabindex="-1" aria-labelledby="modalPilihCabangLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-3">
            <form id="formPilihCabang" method="GET" action="create.php">
                <div class="modal-header border-bottom">
                    <h5 class="modal-title fw-bold" id="modalPilihCabangLabel">
                        <i class="fa-solid fa-location-dot me-2 text-danger"></i>Pilih Cabang Tujuan
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    
                    <?php if ($user_role === 'superSuperAdmin'): ?>
                        <p class="text-muted small mb-4">
                            Pilih cabang asal dan tujuan untuk memfilter daftar pengiriman.
                        </p>
                        
                        <div class="mb-4">
                            <label for="asal_cabang" class="form-label fw-semibold mb-2">Pilih Cabang Asal</label>
                            <select class="form-select border-2" id="asal_cabang" name="asal" required>
                                <option value="">-- Pilih Cabang Asal --</option>
                                <?php foreach ($cabangs_modal as $cabang): ?>
                                    <option value="<?= htmlspecialchars($cabang['kode_cabang']); ?>">
                                        <?= htmlspecialchars($cabang['kode_cabang']); ?> - <?= htmlspecialchars($cabang['nama_cabang']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-4">
                            <label for="tujuan_cabang" class="form-label fw-semibold mb-2">Pilih Cabang Tujuan</label>
                            <select class="form-select border-2" id="tujuan_cabang" name="tujuan" required>
                                <option value="">-- Pilih Cabang Tujuan --</option>
                                <?php foreach ($cabangs_modal as $cabang): ?>
                                    <option value="<?= htmlspecialchars($cabang['kode_cabang']); ?>">
                                        <?= htmlspecialchars($cabang['kode_cabang']); ?> - <?= htmlspecialchars($cabang['nama_cabang']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    
                    <?php else: ?>
                        <p class="text-muted small mb-4">
                            Pilih cabang tujuan. Cabang asal akan otomatis sesuai cabang Anda.
                        </p>
                        
                        <div class="mb-4">
                            <label class="form-label fw-semibold mb-2">Cabang Asal</label>
                            <input type="text" class="form-control border-2 bg-light" value="<?= htmlspecialchars($_SESSION['nama_cabang'] ?? 'Cabang Anda'); ?>" disabled>
                        </div>
                        
                        <div class="mb-4">
                            <label for="tujuan_cabang" class="form-label fw-semibold mb-2">Pilih Cabang Tujuan</label>
                            <select class="form-select border-2" id="tujuan_cabang" name="tujuan" required>
                                <option value="">-- Pilih Cabang Tujuan --</option>
                                <?php foreach ($cabangs_modal as $cabang): ?>
                                    <?php if ($cabang['id'] !== $user_cabang_id): ?>
                                        <option value="<?= htmlspecialchars($cabang['kode_cabang']); ?>">
                                            <?= htmlspecialchars($cabang['kode_cabang']); ?> - <?= htmlspecialchars($cabang['nama_cabang']); ?>
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    
                    <?php endif; ?>
                    
                </div>
                <div class="modal-footer border-top p-4">
                    <button type="button" class="btn btn-outline-secondary fw-semibold" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success fw-semibold">
                        <i class="fa-solid fa-arrow-right me-2"></i>Lanjut
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../../../templates/footer.php'; ?>