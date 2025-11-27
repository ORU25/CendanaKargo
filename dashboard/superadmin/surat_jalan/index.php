<?php
session_start();
if(!isset($_SESSION['username'] )|| !isset($_SESSION['user_id'])){
    header("Location: ../../../auth/login");
    exit;
}

if(isset($_SESSION['role']) && $_SESSION['role'] !== 'superAdmin'){
    header("Location: ../../../?error=unauthorized");
    exit;
}

$user_role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];
$cabang_user = $_SESSION['cabang'];
$id_cabang_user = $_SESSION['id_cabang'];


if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

include '../../../config/database.php';

// Handle hapus draft surat jalan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_draft'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        header("Location: index.php?error=invalid_token");
        exit;
    }
    
    $id_surat_jalan = (int)($_POST['id_surat_jalan'] ?? 0);
    
    if ($id_surat_jalan > 0) {
        // Cek apakah surat jalan masih draft dan dari cabang yang sama
        $stmt_check = $conn->prepare("SELECT status FROM surat_jalan WHERE id = ? AND id_cabang_pengirim = ?");
        $stmt_check->bind_param('ii', $id_surat_jalan, $id_cabang_user);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        
        if ($result_check->num_rows > 0) {
            $sj = $result_check->fetch_assoc();
            if ($sj['status'] == 'draft') {
                // Hapus surat jalan (detail akan terhapus otomatis karena ON DELETE CASCADE)
                $stmt_delete = $conn->prepare("DELETE FROM surat_jalan WHERE id = ?");
                $stmt_delete->bind_param('i', $id_surat_jalan);
                
                if ($stmt_delete->execute()) {
                    $stmt_delete->close();
                    header("Location: index.php?success=deleted");
                    exit;
                }
                $stmt_delete->close();
            }
        }
        $stmt_check->close();
    }
    
    header("Location: index.php?error=delete_failed");
    exit;
}

// Query untuk draft surat jalan (filter berdasarkan cabang)
$sql_draft = "SELECT * FROM surat_jalan WHERE status = 'draft' AND id_cabang_pengirim = ? ORDER BY tanggal DESC";
$stmt_draft = $conn->prepare($sql_draft);
$stmt_draft->bind_param('i', $id_cabang_user);
$stmt_draft->execute();
$result_draft = $stmt_draft->get_result();
$drafts = ($result_draft->num_rows > 0) ? $result_draft->fetch_all(MYSQLI_ASSOC) : [];
$stmt_draft->close();

// Pagination dan Search untuk surat jalan yang sudah diberangkatkan
$search = $_GET['search'] ?? '';
$current_page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 10;
$offset = ($current_page - 1) * $limit;

// Query untuk menghitung total surat jalan diberangkatkan (filter berdasarkan cabang)
$count_query = "SELECT COUNT(*) as total FROM surat_jalan WHERE status = 'diberangkatkan' AND id_cabang_pengirim = ?";
if (!empty($search)) {
    $count_query .= " AND (no_surat_jalan LIKE ? OR cabang_pengirim LIKE ? OR cabang_penerima LIKE ? OR driver LIKE ?)";
}

$stmt_count = $conn->prepare($count_query);
if (!empty($search)) {
    $search_param = "%$search%";
    $stmt_count->bind_param('issss', $id_cabang_user, $search_param, $search_param, $search_param, $search_param);
} else {
    $stmt_count->bind_param('i', $id_cabang_user);
}
$stmt_count->execute();
$result_count = $stmt_count->get_result();
$total_data = $result_count->fetch_assoc()['total'];
$stmt_count->close();

$total_pages = ceil($total_data / $limit);

// Query untuk mengambil surat jalan diberangkatkan (filter berdasarkan cabang)
$query = "SELECT * FROM surat_jalan WHERE status = 'diberangkatkan' AND id_cabang_pengirim = ?";
if (!empty($search)) {
    $query .= " AND (no_surat_jalan LIKE ? OR cabang_pengirim LIKE ? OR cabang_penerima LIKE ? OR driver LIKE ?)";
}
$query .= " ORDER BY tanggal DESC LIMIT ? OFFSET ?";

$stmt_sj = $conn->prepare($query);
if (!empty($search)) {
    $search_param = "%$search%";
    $stmt_sj->bind_param('issssii', $id_cabang_user, $search_param, $search_param, $search_param, $search_param, $limit, $offset);
} else {
    $stmt_sj->bind_param('iii', $id_cabang_user, $limit, $offset);
}
$stmt_sj->execute();
$result_sj = $stmt_sj->get_result();
$surat_jalans = ($result_sj->num_rows > 0) ? $result_sj->fetch_all(MYSQLI_ASSOC) : [];
$stmt_sj->close();

$sql_cabang_modal = "SELECT id, kode_cabang, nama_cabang FROM kantor_cabang ORDER BY nama_cabang ASC";
$result_cabang_modal = $conn->query($sql_cabang_modal);
$cabangs_modal = ($result_cabang_modal->num_rows > 0) ? $result_cabang_modal->fetch_all(MYSQLI_ASSOC) : [];

$page = "surat_jalan"; // untuk sidebar
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

                <?php if(isset($_GET['success']) && $_GET['success'] == 'created' ){
                    $type = "success";
                    $message = "Surat jalan berhasil ditambahkan";
                    include '../../../components/alert.php';
                }?>
                <?php if(isset($_GET['success']) && $_GET['success'] == 'deleted' ){
                    $type = "success";
                    $message = "Draft surat jalan berhasil dihapus";
                    include '../../../components/alert.php';
                }?>
                <?php if(isset($_GET['error']) && $_GET['error'] == 'not_found'){
                    $type = "danger";
                    $message = "Surat jalan tidak ditemukan";
                    include '../../../components/alert.php';
                }?>
                <?php if(isset($_GET['error']) && $_GET['error'] == 'delete_failed'){
                    $type = "danger";
                    $message = "Gagal menghapus draft surat jalan";
                    include '../../../components/alert.php';
                }?>
                <?php if(isset($_GET['success']) && $_GET['success'] == 'diberangkatkan'){
                    $type = "success";
                    $message = "Surat jalan berhasil diberangkatkan";
                    include '../../../components/alert.php';
                }?>
                

                <!-- Header -->
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
                    <div>
                        <h1 class="h4 mb-1 fw-bold">Daftar Surat Jalan</h1>
                    </div>
                    <div class="d-flex gap-2 mt-2 mt-md-0">
                        <button type="button" class="btn btn-success fw-semibold px-4" data-bs-toggle="modal" data-bs-target="#modalPilihCabang">
                            <i class="fa-solid fa-plus me-2"></i>Tambah Surat Jalan
                        </button>
                    </div>
                </div>

                <!-- Tabel Draft Surat Jalan -->
                <div class="card border-0 rounded-3 shadow-sm mb-4">
                    <div class="card-header bg-white border-bottom py-3">
                        <h5 class="mb-0 fw-bold text-secondary">
                            <i class="fa-solid fa-file-lines me-2"></i>Draft Surat Jalan
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light border-bottom">
                                    <tr>
                                        <th class="px-4 py-3" style="width: 5%;">No</th>
                                        <th class="px-3 py-3">No Surat</th>
                                        <th class="px-3 py-3">Asal</th>
                                        <th class="px-3 py-3">Tujuan</th>
                                        <th class="px-3 py-3">Tanggal</th>
                                        <th class="px-3 py-3">Status</th>
                                        <th class="px-3 py-3 text-center" style="width: 100px;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($drafts)): ?>
                                        <?php $no = 1; foreach ($drafts as $draft): ?>
                                            <tr>
                                                <td class="px-4 py-3 text-muted small"><?= $no++; ?></td>
                                                <td class="px-3 py-3 fw-semibold"><?= htmlspecialchars($draft['no_surat_jalan']); ?></td>
                                                <td class="px-3 py-3"><?= htmlspecialchars($draft['cabang_pengirim']); ?></td>
                                                <td class="px-3 py-3"><?= htmlspecialchars($draft['cabang_penerima']); ?></td>
                                                <td class="px-3 py-3"><?= date('d/m/Y H:i', strtotime($draft['tanggal'])); ?></td>
                                                <td class="px-3 py-3">
                                                    <span class="badge bg-secondary text-capitalize">Draft</span>
                                                </td>
                                                <td class="">
                                                    <div class="">
                                                        <a href="create.php?id=<?= $draft['id']; ?>" class="btn btn-sm btn-outline-primary" title="Edit">
                                                            <i class="fa-solid fa-pencil"></i>
                                                        </a>
                                                        <button type="button" class="btn btn-sm btn-outline-danger" 
                                                                onclick="confirmDelete(<?= $draft['id']; ?>, '<?= htmlspecialchars($draft['no_surat_jalan']); ?>')"
                                                                title="Hapus">
                                                            <i class="fa-solid fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" class="text-center text-muted py-4">
                                                <i class="fa-solid fa-inbox fa-2x mb-2 opacity-50"></i>
                                                <p class="mb-0">Tidak ada draft surat jalan</p>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Tabel Surat Jalan Diberangkatkan -->
                <div class="card border-0 rounded-3 shadow-sm">
                    <div class="card-header bg-white border-bottom py-3">
                        <div class="d-flex flex-wrap justify-content-between align-items-center">
                            <h5 class="mb-0 fw-semibold">
                                <i class="fa-solid fa-truck me-2"></i>Surat Jalan Diberangkatkan
                            </h5>
                            <!-- Search Form -->
                            <form method="GET" class="d-flex gap-2" >
                                <input type="text" class="form-control form-control" name="search" 
                                       placeholder="Cari no surat, cabang, atau driver..." 
                                       value="<?= htmlspecialchars($search); ?>">
                                <button class="btn btn-sm btn-primary" type="submit">
                                    <i class="fa-solid fa-search"></i>
                                </button>
                                <?php if (!empty($search)): ?>
                                    <a href="index.php" class="btn btn-sm btn-outline-secondary">
                                        <i class="fa-solid fa-times"></i>
                                    </a>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light border-bottom">
                                    <tr>
                                        <th class="px-4 py-3" style="width: 5%;">No</th>
                                        <th class="px-3 py-3">No Surat</th>
                                        <th class="px-3 py-3">Asal</th>
                                        <th class="px-3 py-3">Tujuan</th>
                                        <th class="px-3 py-3">Driver</th>
                                        <th class="px-3 py-3">Tanggal</th>
                                        <th class="px-3 py-3">Status</th>
                                        <th class="px-3 py-3 text-center" style="width: 100px;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($surat_jalans)): ?>
                                        <?php 
                                        $no = $offset + 1; 
                                        foreach ($surat_jalans as $sj): 
                                        ?>
                                            <tr>
                                                <td class="px-4 py-3 text-muted small"><?= $no++; ?></td>
                                                <td class="px-3 py-3 fw-semibold"><?= htmlspecialchars($sj['no_surat_jalan']); ?></td>
                                                <td class="px-3 py-3"><?= htmlspecialchars($sj['cabang_pengirim']); ?></td>
                                                <td class="px-3 py-3"><?= htmlspecialchars($sj['cabang_penerima']); ?></td>
                                                <td class="px-3 py-3"><?= htmlspecialchars($sj['driver']); ?></td>
                                                <td class="px-3 py-3"><?= date('d/m/Y H:i', strtotime($sj['tanggal'])); ?></td>
                                                <td class="px-3 py-3">
                                                    <span class="badge bg-primary text-capitalize">Diberangkatkan</span>
                                                </td>
                                                <td class="text-center">
                                                    <a href="detail.php?id=<?= $sj['id']; ?>" class="btn btn-sm btn-outline-primary" title="Lihat Detail">
                                                        <i class="fa-solid fa-eye"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php elseif (!empty($search)): ?>
                                        <tr>
                                            <td colspan="8" class="text-center text-muted py-4">
                                                <i class="fa-solid fa-search fa-2x mb-2 opacity-50"></i>
                                                <p class="mb-0">Tidak ada hasil untuk pencarian "<?= htmlspecialchars($search); ?>"</p>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="8" class="text-center text-muted py-4">
                                                <i class="fa-solid fa-inbox fa-2x mb-2 opacity-50"></i>
                                                <p class="mb-0">Belum ada surat jalan yang diberangkatkan</p>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                        <div class="card-footer bg-white border-top py-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <p class="text-muted small mb-0">
                                    Menampilkan <?= $offset + 1; ?> - <?= min($offset + $limit, $total_data); ?> dari <?= $total_data; ?> data
                                </p>
                                <nav>
                                    <ul class="pagination pagination-sm mb-0">
                                        <?php if ($current_page > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?search=<?= htmlspecialchars($search); ?>&page=<?= $current_page - 1; ?>">
                                                    <i class="fa-solid fa-chevron-left"></i>
                                                </a>
                                            </li>
                                        <?php endif; ?>

                                        <?php
                                        $start = max(1, $current_page - 2);
                                        $end = min($total_pages, $current_page + 2);
                                        
                                        for ($i = $start; $i <= $end; $i++):
                                        ?>
                                            <li class="page-item <?= $i == $current_page ? 'active' : ''; ?>">
                                                <a class="page-link" href="?search=<?= htmlspecialchars($search); ?>&page=<?= $i; ?>">
                                                    <?= $i; ?>
                                                </a>
                                            </li>
                                        <?php endfor; ?>

                                        <?php if ($current_page < $total_pages): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?search=<?= htmlspecialchars($search); ?>&page=<?= $current_page + 1; ?>">
                                                    <i class="fa-solid fa-chevron-right"></i>
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Pilih Cabang -->
<div class="modal fade" id="modalPilihCabang" tabindex="-1" aria-labelledby="modalPilihCabangLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-3">
            <form id="formPilihCabang" method="POST" action="create.php">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="create_surat_jalan" value="1">
                
                <div class="modal-header border-bottom">
                    <h5 class="modal-title fw-bold" id="modalPilihCabangLabel">
                        <i class="fa-solid fa-location-dot me-2 text-danger"></i>Pilih Cabang Tujuan
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <p class="text-muted small mb-4">
                        Pilih cabang tujuan untuk membuat surat jalan baru. Cabang asal otomatis dari cabang Anda: <strong><?= htmlspecialchars($cabang_user); ?></strong>
                    </p>
                    
                    <div class="mb-4">
                        <label for="tujuan_cabang" class="form-label fw-semibold mb-2">Pilih Cabang Tujuan</label>
                        <select class="form-select border-2" id="tujuan_cabang" name="tujuan" required>
                            <option value="">-- Pilih Cabang Tujuan --</option>
                            <?php foreach ($cabangs_modal as $cabang): ?>
                                <?php if ($cabang['id'] != $id_cabang_user): ?>
                                    <option value="<?= htmlspecialchars($cabang['kode_cabang']); ?>">
                                        <?= htmlspecialchars($cabang['kode_cabang']); ?> - <?= htmlspecialchars($cabang['nama_cabang']); ?>
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                </div>
                <div class="modal-footer border-top p-4">
                    <button type="button" class="btn btn-outline-secondary fw-semibold" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger fw-semibold">
                        <i class="fa-solid fa-plus me-2"></i>Buat Surat Jalan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Konfirmasi Hapus -->
<div class="modal fade" id="modalHapusDraft" tabindex="-1" aria-labelledby="modalHapusDraftLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-3">
            <div class="modal-header bg-danger text-white border-0">
                <h5 class="modal-title" id="modalHapusDraftLabel">
                    <i class="fa-solid fa-trash me-2"></i>Konfirmasi Hapus
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formHapusDraft" method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="delete_draft" value="1">
                <input type="hidden" name="id_surat_jalan" id="delete_id">
                
                <div class="modal-body p-4">
                    <div class="text-center mb-3">
                        <i class="fa-solid fa-triangle-exclamation fa-3x text-warning"></i>
                    </div>
                    <p class="text-center mb-0">
                        Apakah Anda yakin ingin menghapus draft surat jalan <strong id="delete_no_surat"></strong>?
                    </p>
                    <p class="text-center text-muted small mb-0 mt-2">
                        Semua resi yang sudah ditambahkan akan dilepas dari surat jalan ini.
                    </p>
                </div>
                <div class="modal-footer border-top p-4">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fa-solid fa-trash me-2"></i>Hapus
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function confirmDelete(id, noSurat) {
    document.getElementById('delete_id').value = id;
    document.getElementById('delete_no_surat').textContent = noSurat;
    const modal = new bootstrap.Modal(document.getElementById('modalHapusDraft'));
    modal.show();
}
</script>

<?php include '../../../templates/footer.php'; ?>