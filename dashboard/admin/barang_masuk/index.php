<?php
session_start();
if(!isset($_SESSION['username'] )|| !isset($_SESSION['user_id'])){
    header("Location: ../../../auth/login");
    exit;
}

if(isset($_SESSION['role']) && $_SESSION['role'] !== 'admin'){
    header("Location: ../../../?error=unauthorized");
    exit;
}

include '../../../config/database.php';

// Release session lock early to improve concurrency
session_write_close();

$title = "Barang Masuk - Cendana Kargo";

// Pastikan cabang admin terset di session
$cabang_admin = $_SESSION['cabang'] ?? ''; // misalnya: 'Balikpapan'
if (empty($cabang_admin)) {
    die("Cabang admin tidak ditemukan di session. Pastikan diset saat login.");
}

// Pagination
$limit = 10;
$page_num = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? (int)$_GET['page'] : 1;
$offset = ($page_num - 1) * $limit;

// Search
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Filter status khusus barang masuk
$status_filter = "dalam pengiriman";

// Hitung total data
if ($search !== '') {
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total 
        FROM pengiriman 
        WHERE (no_resi LIKE ? OR nama_barang LIKE ? OR nama_pengirim LIKE ? OR nama_penerima LIKE ?)
    AND status = ?
        AND cabang_penerima = ?
    ");
    $searchParam = "%$search%";
  $stmt->bind_param('ssssss', $searchParam, $searchParam, $searchParam, $searchParam, $status_filter, $cabang_admin);
} else {
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total 
        FROM pengiriman 
    WHERE status = ?
        AND cabang_penerima = ?
    ");
  $stmt->bind_param('ss', $status_filter, $cabang_admin);
}

$stmt->execute();
$result = $stmt->get_result();
$total_records = $result->fetch_assoc()['total'] ?? 0;
$stmt->close();

$total_pages = ceil($total_records / $limit);

// Ambil data
if ($search !== '') {
    $stmt = $conn->prepare("
        SELECT * FROM pengiriman 
        WHERE (no_resi LIKE ? OR nama_barang LIKE ? OR nama_pengirim LIKE ? OR nama_penerima LIKE ?)
    AND status = ?
        AND cabang_penerima = ?
        ORDER BY id DESC 
        LIMIT ? OFFSET ?
    ");
    $searchParam = "%$search%";
  $stmt->bind_param('ssssssii', $searchParam, $searchParam, $searchParam, $searchParam, $status_filter, $cabang_admin, $limit, $offset);
} else {
    $stmt = $conn->prepare("
        SELECT * FROM pengiriman 
    WHERE status = ?
        AND cabang_penerima = ?
        ORDER BY id DESC 
        LIMIT ? OFFSET ?
    ");
  $stmt->bind_param('ssii', $status_filter, $cabang_admin, $limit, $offset);
}

$stmt->execute();
$result = $stmt->get_result();
$barang_masuk = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$page = "barang_masuk";
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
            <h1 class="h4 mb-1 fw-bold">Daftar Barang Masuk (Cabang <?= htmlspecialchars($cabang_admin); ?>)</h1>
            <p class="text-muted small mb-0">
              Menampilkan <?= count($barang_masuk); ?> dari <?= $total_records; ?> data
              <?php if ($total_pages > 1): ?>
                  (Halaman <?= $page_num; ?> dari <?= $total_pages; ?>)
              <?php endif; ?>
            </p>
          </div>
          <div class="mt-2 mt-md-0">
            <a href="scanner.php" class="btn btn-primary">
              <i class="fa-solid fa-qrcode"></i> Scan Barcode
            </a>
          </div>
        </div>

       <?php if(isset($_GET['success']) && $_GET['success'] == 'updated'){
                $type = "success";
                $message = "Pengiriman berhasil sampai tujuan";
                include '../../../components/alert.php';
        }?>
        <?php if(isset($_GET['error']) && $_GET['error'] == 'not_found'){
            $type = "danger";
            $message = "Pengiriman tidak ditemukan";
            include '../../../components/alert.php';
        }?>

        <!-- Search -->
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

        <!-- Table -->
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
                <?php if (empty($barang_masuk)): ?>
                  <tr>
                    <td colspan="11" class="text-center py-5 text-muted">
                      <i class="fa-solid fa-box-open fa-lg"></i>
                      <p class="mb-0">Belum ada barang masuk<?= $search ? ' yang cocok dengan pencarian' : ''; ?> di cabang ini.</p>
                    </td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($barang_masuk as $b): 
                      $badgeClass = 'secondary';
                      switch(strtolower($b['status'])) {
                          case 'dalam pengiriman': $badgeClass = 'primary'; break;
                          case 'sampai tujuan': $badgeClass = 'info'; break;
                      }
                  ?>
                  <tr class="text-capitalize">
                    <td class="px-4"><span class="badge bg-dark"><?= htmlspecialchars($b['no_resi']); ?></span></td>
                    <td class="fw-semibold"><?= htmlspecialchars($b['nama_barang']); ?></td>
                    <td class="small"><?= htmlspecialchars($b['nama_pengirim']); ?></td>
                    <td class="small"><?= htmlspecialchars($b['nama_penerima']); ?></td>
                    <td class="small"><?= htmlspecialchars($b['cabang_pengirim']); ?></td>
                    <td class="small"><?= htmlspecialchars($b['cabang_penerima']); ?></td>
                    <td class="text-end fw-semibold">Rp <?= number_format($b['total_tarif'], 0, ',', '.'); ?></td>
                    <td class="small"><?= date('d/m/Y', strtotime($b['tanggal'])); ?></td>
                    <td><span class="text-uppercase badge text-bg-<?= $badgeClass; ?>"><?= htmlspecialchars($b['status']); ?></span></td>
                    <td class="text-center">
                      <div class="d-flex justify-content-center gap-2">
                          <a href="detail?id=<?= (int)$b['id']; ?>" class="btn btn-sm btn-outline-primary" title="Lihat Detail">
                              <i class="fa-solid fa-eye"></i>
                          </a>
                      </div>
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
          <nav aria-label="Navigasi halaman">
            <ul class="pagination pagination-sm mb-0">
              <li class="page-item <?= $page_num <= 1 ? 'disabled' : ''; ?>">
                <a class="page-link" href="?page=<?= $page_num - 1; ?><?= $search ? '&search=' . urlencode($search) : ''; ?>">&laquo;</a>
              </li>
              <?php
              $range = 2;
              for ($i = 1; $i <= $total_pages; $i++) {
                if ($i == 1 || $i == $total_pages || ($i >= $page_num - $range && $i <= $page_num + $range)) {
                  echo '<li class="page-item '.($i == $page_num ? 'active' : '').'"><a class="page-link" href="?page='.$i.($search ? '&search='.urlencode($search) : '').'">'.$i.'</a></li>';
                } elseif ($i == $page_num - $range - 1 || $i == $page_num + $range + 1) {
                  echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                }
              }
              ?>
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
