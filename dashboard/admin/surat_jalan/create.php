<?php
session_start();

if (!isset($_SESSION['username'])) {
    header("Location: ../../../auth/login.php");
    exit;
}

$user_role = $_SESSION['role'] ?? '';
$user_id = $_SESSION['user_id'] ?? null;
$username = $_SESSION['username'] ?? '';
$user_cabang_id = $_SESSION['id_cabang'] ?? null;

if (!in_array($user_role, ['superSuperAdmin', 'superAdmin', 'admin'])) {
    header("Location: ../../../?error=unauthorized_global");
    exit;
}

if ($user_role === 'superSuperAdmin' && $user_cabang_id !== null) {
    header("Location: ../../../?error=unauthorized_global");
    exit;
}
if ($user_role !== 'superSuperAdmin' && ($user_cabang_id === null || $user_cabang_id == 0)) {
    header("Location: ../../../?error=unauthorized_global");
    exit;
}

include '../../../config/database.php';

// ===================================================================
// BAGIAN LOGIKA SIMPAN (POST)
// ===================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('CSRF token validation failed.');
    }

    $no_surat_jalan = trim($_POST['no_surat_jalan']);
    $driver = trim($_POST['driver']);
    $id_cabang_pengirim = intval($_POST['id_cabang_pengirim']);
    $id_cabang_penerima = intval($_POST['id_cabang_penerima']);
    $cabang_pengirim_nama = trim($_POST['cabang_pengirim_nama']);
    $cabang_penerima_nama = trim($_POST['cabang_penerima_nama']);
    $asal_kode_get = trim($_POST['asal_kode']);
    $tujuan_kode_get = trim($_POST['tujuan_cabang_kode']);
    $pengiriman_ids = $_POST['pengiriman_ids'] ?? [];

    if (empty($no_surat_jalan) || empty($driver) || empty($pengiriman_ids) || empty($id_cabang_pengirim) || empty($id_cabang_penerima)) {
        header("Location: create.php?asal=$asal_kode_get&tujuan=$tujuan_kode_get&error=" . urlencode("Data formulir tidak lengkap."));
        exit;
    }

    mysqli_begin_transaction($conn);

    try {
        $sql_insert_sj = "INSERT INTO Surat_jalan 
(id_user, id_cabang_pengirim, id_cabang_penerima, no_surat_jalan, user, cabang_pengirim, cabang_penerima, driver, status, tanggal) 
VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'dalam perjalanan', NOW())";

        $stmt_sj = $conn->prepare($sql_insert_sj);
        $stmt_sj->bind_param(
            "iissssss",
            $user_id,
            $id_cabang_pengirim,
            $id_cabang_penerima,
            $no_surat_jalan,
            $username,
            $cabang_pengirim_nama,
            $cabang_penerima_nama,
            $driver
        );
        $stmt_sj->execute();
        $id_surat_jalan_baru = mysqli_insert_id($conn);

        if ($id_surat_jalan_baru == 0) {
            throw new Exception("Gagal membuat surat jalan. Cek No. Surat Jalan duplikat.");
        }

        $sql_insert_detail = "INSERT INTO detail_surat_jalan (id_surat_jalan, id_pengiriman) VALUES (?, ?)";
        $stmt_detail = $conn->prepare($sql_insert_detail);
        $sql_update_pengiriman = "UPDATE Pengiriman SET status = 'dalam pengiriman' WHERE id = ? AND status = 'bkd'";
        $stmt_update = $conn->prepare($sql_update_pengiriman);

        foreach ($pengiriman_ids as $id_pengiriman) {
            $stmt_detail->bind_param("ii", $id_surat_jalan_baru, $id_pengiriman);
            $stmt_detail->execute();
            $stmt_update->bind_param("i", $id_pengiriman);
            $stmt_update->execute();
        }

        mysqli_commit($conn);
        unset($_SESSION['csrf_token']);
        header("Location: index.php?success=sj_created");
        exit;

    } catch (Exception $e) {
        mysqli_rollback($conn);
        $error_message = $e->getMessage();
        header("Location: create.php?asal=$asal_kode_get&tujuan=$tujuan_kode_get&error=" . urlencode($error_message));
        exit;
    }
}
// ===================================================================
// AKHIR BAGIAN LOGIKA SIMPAN (POST)
// ===================================================================

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$tujuan_filter_kode = isset($_GET['tujuan']) ? htmlspecialchars($_GET['tujuan']) : '';
$asal_filter_kode = isset($_GET['asal']) ? htmlspecialchars($_GET['asal']) : ''; // Untuk SSA, ini dari GET

if ($user_role !== 'superSuperAdmin') {
    // INI LOGIKA UNTUK ADMIN & SUPERADMIN
    if (empty($tujuan_filter_kode)) {
        header("Location: index.php?error=missing_params");
        exit;
    }
    // $asal_filter_kode akan kosong, kita isi nanti dari data session
} else {
    // INI LOGIKA UNTUK SUPER SUPER ADMIN
    if (empty($tujuan_filter_kode) || empty($asal_filter_kode)) {
        header("Location: index.php?error=missing_params");
        exit;
    }
}

$cabang_asal_id = null;
$cabang_asal_nama = '';
$cabang_tujuan_id = null;
$cabang_tujuan_nama = '';

$sql_cabang = "SELECT id, kode_cabang, nama_cabang FROM Kantor_cabang ORDER BY nama_cabang ASC";
$result_cabang = $conn->query($sql_cabang);
$cabangs = ($result_cabang->num_rows > 0) ? $result_cabang->fetch_all(MYSQLI_ASSOC) : [];

if ($user_role !== 'superSuperAdmin') {
    // INI LOGIKA UNTUK ADMIN & SUPERADMIN
    $cabang_asal_id = $user_cabang_id;
    $cabang_asal_nama = $_SESSION['nama_cabang'] ?? '';
    
    // Kita cari kode cabang (BTG, SMD, dll) berdasarkan ID dari session
    foreach ($cabangs as $cabang) {
        if ($cabang['id'] == $cabang_asal_id) {
            $asal_filter_kode = $cabang['kode_cabang']; // $asal_filter_kode sekarang terisi
            break;
        }
    }

} else {
    // INI LOGIKA UNTUK SUPER SUPER ADMIN
    foreach ($cabangs as $cabang) {
        if ($cabang['kode_cabang'] === $asal_filter_kode) {
            $cabang_asal_id = $cabang['id'];
            $cabang_asal_nama = $cabang['nama_cabang'];
        }
    }
}

// Logika ini sama untuk semua role
foreach ($cabangs as $cabang) {
    if ($cabang['kode_cabang'] === $tujuan_filter_kode) {
        $cabang_tujuan_id = $cabang['id'];
        $cabang_tujuan_nama = $cabang['nama_cabang'];
    }
}

if ($cabang_asal_id == 0 || $cabang_tujuan_id == 0) {
    header("Location: index.php?error=invalid_cabang");
    exit;
}

$sql_pengiriman = "SELECT p.*, kc.nama_cabang AS nama_cabang_asal, kc.kode_cabang AS kode_cabang_asal
FROM Pengiriman p 
JOIN Kantor_cabang kc ON p.id_cabang_pengirim = kc.id 
WHERE 
p.id_cabang_penerima = ?
AND p.id_cabang_pengirim = ?
AND p.status = 'bkd' 
ORDER BY p.tanggal DESC";

$stmt = $conn->prepare($sql_pengiriman);
$stmt->bind_param("ii", $cabang_tujuan_id, $cabang_asal_id);
$stmt->execute();
$result_pengiriman = $stmt->get_result();
$pengirimens = ($result_pengiriman->num_rows > 0) ? $result_pengiriman->fetch_all(MYSQLI_ASSOC) : [];

$page = "surat_jalan";
$title = "Tambah Surat Jalan - Cendana Kargo";
$max_selection = 15;
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
                    <a href="index.php" class="btn btn-outline-secondary btn-sm mb-3">
                        <i class="fa-solid fa-arrow-left me-2"></i>Belum ada surat
                    </a>
                    <div class="d-flex justify-content-between align-items-start gap-3">
                        <div>
                            <h5 class="fw-bold mb-2">Tambah Surat Jalan</h5>
                            <p class="text-muted small">Pilih resi yang ingin diberangkatkan (max 15 / surat)</p>
                        </div>
                        <button type="button" class="btn btn-success fw-semibold px-4" id="btnBuatSuratTop" disabled data-bs-toggle="modal" data-bs-target="#modalBuatSurat">
                            Buat Surat
                        </button>
                    </div>
                </div>

                <?php if (isset($_GET['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                        <i class="fa-solid fa-circle-exclamation me-2"></i><?= htmlspecialchars(urldecode($_GET['error'])); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <form id="formSuratJalan" method="POST" action="create.php">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="id_cabang_pengirim" value="<?= htmlspecialchars($cabang_asal_id); ?>">
                    <input type="hidden" name="id_cabang_penerima" value="<?= htmlspecialchars($cabang_tujuan_id); ?>">
                    <input type="hidden" name="cabang_pengirim_nama" value="<?= htmlspecialchars($cabang_asal_nama); ?>">
                    <input type="hidden" name="cabang_penerima_nama" value="<?= htmlspecialchars($cabang_tujuan_nama); ?>">
                    <input type="hidden" name="asal_kode" value="<?= htmlspecialchars($asal_filter_kode); ?>">
                    <input type="hidden" name="tujuan_cabang_kode" value="<?= htmlspecialchars($tujuan_filter_kode); ?>">
                    <input type="hidden" name="no_surat_jalan" id="hiddenNoSurat" value="">
                    <input type="hidden" name="driver" id="hiddenDriver" value="">
                    <div id="hidden-inputs-container"></div>

                    <div class="card border-0 rounded-3 shadow-sm">
                        <div class="card-body p-4">
                            <h6 class="fw-bold text-dark mb-3">
                                <i class="fa-solid fa-list-check me-2"></i>Pilih resi (Status: BKD, Asal: <?= htmlspecialchars($asal_filter_kode); ?>) - <?= count($pengirimens); ?>/<?= $max_selection; ?>
                            </h6>

                            <?php if (empty($pengirimens)): ?>
                                <div class="alert alert-info border-0 rounded-3 d-flex align-items-center gap-3" role="alert">
                                    <i class="fa-solid fa-circle-info fa-2x"></i>
                                    <div>
                                        <strong>Tidak ada pengiriman menunggu</strong>
                                        <p class="mb-0 small">Tidak ada pengiriman berstatus 'bkd' dari <strong><?= htmlspecialchars($cabang_asal_nama); ?></strong> ke <strong><?= htmlspecialchars($cabang_tujuan_nama); ?></strong>.</p>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0 small">
                                        <thead class="table-light border-bottom">
                                            <tr>
                                                <th class="px-3 py-3" style="width: 5%;"><input class="form-check-input" type="checkbox" id="checkAll"></th>
                                                <th class="px-3 py-3">No Resi</th>
                                                <th class="px-3 py-3">Asal</th>
                                                <th class="px-3 py-3">Tujuan</th>
                                                <th class="px-3 py-3">Tanggal</th>
                                                <th class="px-3 py-3 text-center">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($pengirimens as $p): ?>
                                                <tr id="row-<?= $p['id']; ?>" data-id="<?= $p['id']; ?>" data-resi="<?= htmlspecialchars($p['no_resi']); ?>" data-penerima="<?= htmlspecialchars($p['nama_penerima']); ?>" data-barang="<?= htmlspecialchars($p['nama_barang']); ?>">
                                                    <td class="px-3 py-3">
                                                        <input class="form-check-input pengiriman-checkbox" type="checkbox" value="<?= $p['id']; ?>">
                                                    </td>
                                                    <td class="px-3 py-3 fw-semibold"><?= htmlspecialchars($p['no_resi']); ?></td>
                                                    <td class="px-3 py-3"><?= htmlspecialchars($p['kode_cabang_asal'] ?? 'N/A'); ?></td>
                                                    <td class="px-3 py-3"><?= htmlspecialchars($cabang_tujuan_nama); ?></td>
                                                    <td class="px-3 py-3"><?= htmlspecialchars(date('Y-m-d', strtotime($p['tanggal']))); ?></td>
                                                    <td class="px-3 py-3 text-center">
                                                        <span class="badge bg-success text-white" style="padding: 6px 12px; font-size: 11px;">BKD</span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>

            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalBuatSurat" tabindex="-1" aria-labelledby="modalBuatSuratLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-3">
            <div class="modal-header border-bottom">
                <h5 class="modal-title fw-bold" id="modalBuatSuratLabel">
                    <i class="fa-solid fa-clipboard me-2"></i>Info Surat Jalan
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-4">
                    <label class="form-label fw-semibold small mb-2">Pilih Cabang Asal</label>
                    <input type="text" class="form-control border-2 bg-light" value="<?= htmlspecialchars($cabang_asal_nama); ?>" disabled>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-semibold small mb-2">Nomor Surat Berikutnya:</label>
                    <p class="mb-0 text-primary fw-semibold fs-5"><?= htmlspecialchars($asal_filter_kode); ?>1</p>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-semibold small mb-2">No. Surat Jalan</label>
                    <input type="text" class="form-control border-2" id="inputNoSurat" placeholder="Contoh: BTG1" required>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-semibold small mb-2">Nama Driver</label>
                    <input type="text" class="form-control border-2" id="inputDriver" placeholder="Masukkan nama driver" required>
                </div>

                <div class="alert alert-info border-0 rounded-2 small mb-0">
                    <i class="fa-solid fa-info-circle me-2"></i>
                    Total resi dipilih: <strong id="totalResiModal">0</strong>
                </div>
            </div>
            <div class="modal-footer border-top p-4">
                <button type="button" class="btn btn-outline-secondary fw-semibold" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-success fw-semibold" id="btnSubmitSurat">
                    <i class="fa-solid fa-save me-2"></i>Simpan
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const maxSelection = <?= $max_selection; ?>;
        const checkboxes = document.querySelectorAll('.pengiriman-checkbox');
        const checkAll = document.getElementById('checkAll');
        const btnBuatSuratTop = document.getElementById('btnBuatSuratTop');
        const hiddenInputsContainer = document.getElementById('hidden-inputs-container');
        const form = document.getElementById('formSuratJalan');
        const inputNoSurat = document.getElementById('inputNoSurat');
        const inputDriver = document.getElementById('inputDriver');
        const btnSubmitSurat = document.getElementById('btnSubmitSurat');
        const totalResiModal = document.getElementById('totalResiModal');
        const hiddenNoSurat = document.getElementById('hiddenNoSurat');
        const hiddenDriver = document.getElementById('hiddenDriver');

        function updateSelection() {
            let checkedCount = 0;
            hiddenInputsContainer.innerHTML = '';

            checkboxes.forEach(cb => {
                if (cb.checked) {
                    checkedCount++;
                    const row = cb.closest('tr');
                    const id = row.getAttribute('data-id');

                    const hiddenInput = document.createElement('input');
                    hiddenInput.type = 'hidden';
                    hiddenInput.name = 'pengiriman_ids[]';
                    hiddenInput.value = id;
                    hiddenInputsContainer.appendChild(hiddenInput);
                }
            });

            btnBuatSuratTop.disabled = (checkedCount === 0);
            totalResiModal.textContent = checkedCount;

            if (checkAll) {
                checkAll.checked = (checkedCount === checkboxes.length) && (checkboxes.length > 0);
            }

            checkboxes.forEach(cb => {
                const row = cb.closest('tr');
                if (!cb.checked && checkedCount >= maxSelection) {
                    cb.disabled = true;
                    row.classList.add('opacity-50');
                } else {
                    cb.disabled = false;
                    row.classList.remove('opacity-50');
                }
            });
        }

        if (checkAll) {
            checkAll.addEventListener('change', function () {
                const shouldCheck = this.checked;
                checkboxes.forEach((cb, index) => {
                    if (shouldCheck && index < maxSelection) {
                        cb.checked = true;
                    } else {
                        cb.checked = false;
                    }
                });
                updateSelection();
            });
        }

        checkboxes.forEach(cb => {
            cb.addEventListener('change', updateSelection);
        });

        btnSubmitSurat.addEventListener('click', function () {
            if (!inputNoSurat.value.trim()) {
                alert('No. Surat Jalan harus diisi');
                inputNoSurat.focus();
                return;
            }
            if (!inputDriver.value.trim()) {
                alert('Nama Driver harus diisi');
                inputDriver.focus();
                return;
            }

            hiddenNoSurat.value = inputNoSurat.value;
            hiddenDriver.value = inputDriver.value;

            form.submit();
        });

        updateSelection();
    });
</script>

<?php include '../../../templates/footer.php'; ?>