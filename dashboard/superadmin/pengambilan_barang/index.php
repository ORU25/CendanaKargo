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

$id_cabang_user = $_SESSION['id_cabang'];
$cabang_user = $_SESSION['cabang'];

include '../../../config/database.php';

$title = "Pengambilan Barang - Cendana Kargo (Super Admin)";

$limit = 10;
$page_num = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? (int)$_GET['page'] : 1;
$offset = ($page_num - 1) * $limit;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$status_filter = ["sampai tujuan", "pod"];

if ($search !== '') {
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total 
        FROM pengiriman 
        WHERE (no_resi LIKE ? OR nama_barang LIKE ? OR nama_pengirim LIKE ? OR nama_penerima LIKE ?)
        AND status IN (?, ?)
        AND id_cabang_penerima = ?
    ");
    $searchParam = "%$search%";
    $stmt->bind_param('ssssssi', $searchParam, $searchParam, $searchParam, $searchParam, $status_filter[0], $status_filter[1], $id_cabang_user);
} else {
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total 
        FROM pengiriman 
        WHERE status IN (?, ?)
        AND id_cabang_penerima = ?
    ");
    $stmt->bind_param('ssi', $status_filter[0], $status_filter[1], $id_cabang_user);
}
$stmt->execute();
$result = $stmt->get_result();
$total_records = $result->fetch_assoc()['total'] ?? 0;
$stmt->close();

$total_pages = ceil($total_records / $limit);

if ($search !== '') {
    $stmt = $conn->prepare("
        SELECT * FROM pengiriman 
        WHERE (no_resi LIKE ? OR nama_barang LIKE ? OR nama_pengirim LIKE ? OR nama_penerima LIKE ?)
        AND status IN (?, ?)
        AND id_cabang_penerima = ?
        ORDER BY FIELD(status, 'sampai tujuan', 'pod'), id DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bind_param('sssssssii', $searchParam, $searchParam, $searchParam, $searchParam, $status_filter[0], $status_filter[1], $id_cabang_user, $limit, $offset);
} else {
    $stmt = $conn->prepare("
        SELECT * FROM pengiriman 
        WHERE status IN (?, ?)
        AND id_cabang_penerima = ?
        ORDER BY FIELD(status, 'sampai tujuan', 'pod'), id DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bind_param('ssiii', $status_filter[0], $status_filter[1], $id_cabang_user, $limit, $offset);
}
$stmt->execute();
$result = $stmt->get_result();
$pengambilan_barang = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

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
        <?php if(isset($_GET['error']) && $_GET['error'] == 'not_found'){
            $type = "danger";
            $message = "Pengiriman tidak ditemukan";
            include '../../../components/alert.php';
        }?>
        
        <?php if(isset($_GET['error']) && $_GET['error'] == 'no_data'){
            $tanggal_filter = isset($_GET['tanggal']) ? date('d F Y', strtotime($_GET['tanggal'])) : '';
            $type = "warning";
            $message = "Tidak ada data pengambilan barang pada tanggal " . htmlspecialchars($tanggal_filter);
            include '../../../components/alert.php';
        }?>

        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
            <div>
            <h1 class="h4 mb-1 fw-bold">Daftar Pengambilan Barang (<?= htmlspecialchars($cabang_user); ?>)</h1>
            <p class="text-muted small mb-0">
                Menampilkan <?= count($pengambilan_barang); ?> dari <?= $total_records; ?> data
                <?php if ($total_pages > 1): ?>
                (Halaman <?= $page_num; ?> dari <?= $total_pages; ?>)
                <?php endif; ?>
            </p>
            </div>
            <div class="mt-2 mt-md-0">
              <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#exportModal">
                <i class="fa-solid fa-file-excel"></i> Export Excel
              </button>
            </div>
        </div>

        <!-- Modal Export -->
        <div class="modal fade" id="exportModal" tabindex="-1" aria-labelledby="exportModalLabel" aria-hidden="true">
          <div class="modal-dialog">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title" id="exportModalLabel">Export Data Pengambilan Barang</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <form action="export_pengambilan.php" method="GET" target="_blank">
                <div class="modal-body">
                  <div class="mb-3">
                    <label for="tanggal_export" class="form-label">Pilih Tanggal</label>
                    <input type="date" class="form-control" id="tanggal_export" name="tanggal" value="<?= date('Y-m-d'); ?>" required>
                    <small class="text-muted">Data yang sudah diambil pada tanggal yang dipilih</small>
                  </div>
                </div>
                <div class="modal-footer">
                  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                  <button type="submit" class="btn btn-success">
                    <i class="fa-solid fa-download"></i> Download Excel
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>

        <!-- Pencarian -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-3">
            <form method="GET" action="" class="row g-2 align-items-center">
                <div class="col-md-10">
                <input type="text" name="search" class="form-control" placeholder="Cari No Resi, Nama Barang, Pengirim, atau Penerima..." value="<?= htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fa-solid fa-magnifying-glass"></i> Cari
                </button>
                </div>
                <?php if ($search): ?>
                <div class="col-12">
                <a href="./" class="btn btn-sm btn-outline-secondary mt-2">
                    <i class="fa-solid fa-x"></i> Hapus Pencarian
                </a>
                </div>
                <?php endif; ?>
            </form>
            </div>
        </div>

        <!-- Tabel data -->
        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                    <th class="px-4">No Resi</th>
                    <th>Nama Barang</th>
                    <th>Pengirim</th>
                    <th>Penerima</th>
                    <th>Asal</th>
                    <th>Tujuan</th>
                    <th class="text-end">Total Tarif</th>
                    <th>Tanggal</th>
                    <th>Status</th>
                    <th class="text-center" style="width:100px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($pengambilan_barang)): ?>
                    <tr>
                    <td colspan="11" class="text-center py-5 text-muted">
                        <i class="fa-solid fa-boxes-packing fa-lg"></i>
                        <p class="mb-0">Belum ada data pengambilan barang<?= $search ? ' yang cocok dengan pencarian' : ''; ?>.</p>
                    </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($pengambilan_barang as $b): ?>
                    <tr class="text-capitalize">
                    <td class="px-4"><span class="badge bg-dark"><?= htmlspecialchars($b['no_resi']); ?></span></td>
                    <td class="fw-semibold"><?= htmlspecialchars($b['nama_barang']); ?></td>
                    <td class="small"><?= htmlspecialchars($b['nama_pengirim']); ?></td>
                    <td class="small"><?= htmlspecialchars($b['nama_penerima']); ?></td>
                    <td class="small"><?= htmlspecialchars($b['cabang_pengirim']); ?></td>
                    <td class="small"><?= htmlspecialchars($b['cabang_penerima']); ?></td>
                    <td class="text-end fw-semibold">Rp <?= number_format($b['total_tarif'], 0, ',', '.'); ?></td>
                    <td class="small"><?= date('d/m/Y', strtotime($b['tanggal'])); ?></td>
                    <td>
                        <span class="badge text-bg-<?= $b['status'] === 'pod' ? 'success' : 'info'; ?>">
                            <?= htmlspecialchars($b['status']); ?>
                        </span>
                    </td>
                    <td class="text-center">
                        <a href="detail.php?id=<?= (int)$b['id']; ?>" 
                        class="btn btn-sm btn-outline-primary" 
                        title="Lihat Detail Pengambilan Barang">
                            <i class="fa-solid fa-eye text-primary"></i>
                        </a>
                    </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
                </table>
            </div>
            </div>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="d-flex justify-content-between align-items-center mt-4">
            <div class="text-muted small">
            Menampilkan <?= $offset + 1; ?> - <?= min($offset + $limit, $total_records); ?> dari <?= $total_records; ?> data
            </div>
            <nav>
            <ul class="pagination pagination-sm mb-0">
                <li class="page-item <?= $page_num <= 1 ? 'disabled' : ''; ?>">
                <a class="page-link" href="?page=<?= $page_num - 1; ?><?= $search ? '&search=' . urlencode($search) : ''; ?>">&laquo;</a>
                </li>
                <?php 
                $range = 2;
                $start = max(1, $page_num - $range);
                $end = min($total_pages, $page_num + $range);
                
                if ($start > 1): ?>
                  <li class="page-item">
                    <a class="page-link" href="?page=1<?= $search ? '&search=' . urlencode($search) : ''; ?>">1</a>
                  </li>
                  <?php if ($start > 2): ?>
                    <li class="page-item disabled"><span class="page-link">...</span></li>
                  <?php endif; ?>
                <?php endif; ?>
                
                <?php for ($i = $start; $i <= $end; $i++): ?>
                  <li class="page-item <?= $i == $page_num ? 'active' : ''; ?>">
                    <a class="page-link" href="?page=<?= $i; ?><?= $search ? '&search=' . urlencode($search) : ''; ?>"><?= $i; ?></a>
                  </li>
                <?php endfor; ?>
                
                <?php if ($end < $total_pages): ?>
                  <?php if ($end < $total_pages - 1): ?>
                    <li class="page-item disabled"><span class="page-link">...</span></li>
                  <?php endif; ?>
                  <li class="page-item">
                    <a class="page-link" href="?page=<?= $total_pages; ?><?= $search ? '&search=' . urlencode($search) : ''; ?>"><?= $total_pages; ?></a>
                  </li>
                <?php endif; ?>
                
            <li class="page-item <?= $page_num >= $total_pages ? 'disabled' : ''; ?>">
                <a class="page-link" href="?page=<?= $page_num + 1; ?><?= $search ? '&search=' . urlencode($search) : ''; ?>">&raquo;</a>
            </li>
            </ul>
            </nav>
        </div>
        <?php endif; ?>

        </div>
    </div>
    </div>
</div>

<?php include '../../../templates/footer.php'; ?>
