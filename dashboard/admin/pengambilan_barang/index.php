<?php
session_start();
if(!isset($_SESSION['username']) || !isset($_SESSION['user_id'])){
    header("Location: ../../../auth/login.php");
    exit;
}

if(isset($_SESSION['role']) && $_SESSION['role'] !== 'admin'){
    header("Location: ../../../?error=unauthorized");
    exit;
}

include '../../../config/database.php';

$title = "Pengambilan Barang - Cendana Kargo";

$cabang_admin = $_SESSION['cabang'] ?? '';
$id_cabang_admin = $_SESSION['id_cabang'] ?? 0;

if (empty($cabang_admin) || $id_cabang_admin == 0) {
    die("Cabang admin tidak ditemukan di session. Pastikan diset saat login.");
}

$limit = 10;
$page_num = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? (int)$_GET['page'] : 1;
$offset = ($page_num - 1) * $limit;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = ["sampai tujuan", "pod"];

if ($search !== '') {
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total 
        FROM pengiriman 
        WHERE (no_resi LIKE ? OR nama_barang LIKE ? OR nama_pengirim LIKE ? OR nama_penerima LIKE ?)
        AND status IN (?, ?)
        AND id_cabang_penerima = ?
    ");
    $searchParam = "%$search%";
    $stmt->bind_param('ssssssi', $searchParam, $searchParam, $searchParam, $searchParam, $status_filter[0], $status_filter[1], $id_cabang_admin);
} else {
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total 
        FROM pengiriman 
        WHERE status IN (?, ?)
        AND id_cabang_penerima = ?
    ");
    $stmt->bind_param('ssi', $status_filter[0], $status_filter[1], $id_cabang_admin);
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
    $stmt->bind_param('sssssssii', $searchParam, $searchParam, $searchParam, $searchParam, $status_filter[0], $status_filter[1], $id_cabang_admin, $limit, $offset);
} else {
    $stmt = $conn->prepare("
        SELECT * FROM pengiriman 
        WHERE status IN (?, ?)
        AND id_cabang_penerima = ?
        ORDER BY FIELD(status, 'sampai tujuan', 'pod'), id DESC 
        LIMIT ? OFFSET ?
    ");
    $stmt->bind_param('ssiii', $status_filter[0], $status_filter[1], $id_cabang_admin, $limit, $offset);
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
        
        <div class="container-fluid p-4">
        <?php if(isset($_GET['error']) && $_GET['error'] == 'not_found'){
            $type = "danger";
            $message = "Pengiriman tidak ditemukan";
            include '../../../components/alert.php';
        }?>

        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
          <div>
            <h1 class="h4 mb-1 fw-bold">Daftar Pengambilan Barang (Cabang <?= htmlspecialchars($cabang_admin); ?>)</h1>
            <p class="text-muted small mb-0">
              Menampilkan <?= count($pengambilan_barang); ?> dari <?= $total_records; ?> data
              <?php if ($total_pages > 1): ?>
                  (Halaman <?= $page_num; ?> dari <?= $total_pages; ?>)
              <?php endif; ?>
            </p>
          </div>
        </div>

        <!-- form pencarian -->
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
                <a href="./" class="btn btn-sm btn-outline-secondary">
                  <i class="fa-solid fa-x"></i> Hapus Pencarian
                </a>
              </div>
              <?php endif; ?>
            </form>
          </div>
        </div>

        <!-- tabel utama -->
        <div class="card border-0 shadow-sm">
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th class="px-4">ID</th>
                    <th>No Resi</th>
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
                      <p class="mb-0">Belum ada barang yang siap diambil<?= $search ? ' yang cocok dengan pencarian' : ''; ?>.</p>
                    </td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($pengambilan_barang as $b): ?>
                  <tr class="text-capitalize">
                    <td class="px-4 fw-semibold"><?= (int)$b['id']; ?></td>
                    <td><span class="badge bg-dark"><?= htmlspecialchars($b['no_resi']); ?></span></td>
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

        <!-- pagination -->
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
              <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <li class="page-item <?= $i == $page_num ? 'active' : ''; ?>">
                  <a class="page-link" href="?page=<?= $i; ?><?= $search ? '&search=' . urlencode($search) : ''; ?>"><?= $i; ?></a>
                </li>
              <?php endfor; ?>
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
