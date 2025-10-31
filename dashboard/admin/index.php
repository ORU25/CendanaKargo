<?php
session_start();
if (!isset($_SESSION['username']) || !isset($_SESSION['user_id'])) {
    header("Location: ../../auth/login");
    exit;
}

if (isset($_SESSION['role']) && $_SESSION['role'] !== 'admin') {
    header("Location: ../../?error=unauthorized");
    exit;
}

include '../../config/database.php';

// === Ambil ID & cabang admin login ===
$stmt = $conn->prepare("
    SELECT kc.id, kc.nama_cabang 
    FROM User u 
    JOIN Kantor_cabang kc ON u.id_cabang = kc.id 
    WHERE u.id = ?
");
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$cabang_row = $stmt->get_result()->fetch_assoc();
$stmt->close();

$id_cabang_admin = $cabang_row['id'] ?? 0;
$nama_cabang_admin = $cabang_row['nama_cabang'] ?? 'Tidak diketahui';

// === Filter waktu ===
$current_month = date('m');
$current_year  = date('Y');
$current_date  = date('Y-m-d');

$filter = isset($_GET['filter']) ? $_GET['filter'] : 'bulan';
$where_clause = ($filter === 'hari')
    ? "DATE(tanggal) = '$current_date'"
    : "MONTH(tanggal) = '$current_month' AND YEAR(tanggal) = '$current_year'";

$selected_date_display = ($filter === 'hari') ? " (" . date('d F Y') . ")" : " " . date('F Y');

// === Hitung total pengiriman, surat jalan, dan pendapatan (berdasarkan filter) ===
$total_pengiriman = $conn->query("
    SELECT COUNT(*) AS total 
    FROM pengiriman 
    WHERE $where_clause AND id_cabang_pengirim = '$id_cabang_admin'
")->fetch_assoc()['total'] ?? 0;

$total_surat_jalan = $conn->query("
    SELECT COUNT(*) AS total 
    FROM surat_jalan 
    WHERE $where_clause AND id_cabang_pengirim = '$id_cabang_admin'
")->fetch_assoc()['total'] ?? 0;

$total_pendapatan = $conn->query("
    SELECT SUM(total_tarif) AS total 
    FROM pengiriman 
    WHERE $where_clause AND id_cabang_pengirim = '$id_cabang_admin'
")->fetch_assoc()['total'] ?? 0;

// Helper format rupiah
function format_rupiah($angka) {
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

// === Hitung jumlah pengiriman berdasarkan status (per cabang admin) ===
$status_counts = [
    'bkd' => 0,
    'dalam pengiriman' => 0,
    'sampai tujuan' => 0,
    'pod' => 0,
    'dibatalkan' => 0
];

$stmt = $conn->prepare("
    SELECT status, COUNT(*) AS jumlah
    FROM pengiriman
    WHERE id_cabang_pengirim = ? 
    GROUP BY status
");
$stmt->bind_param('i', $id_cabang_admin);
$stmt->execute();
$result_status = $stmt->get_result();
while ($row = $result_status->fetch_assoc()) {
    $key = strtolower($row['status']);
    if (isset($status_counts[$key])) {
        $status_counts[$key] = $row['jumlah'];
    }
}
$stmt->close();


// === Ambil pengiriman keluar (8 terbaru) ===
$stmt = $conn->prepare("
    SELECT * FROM pengiriman 
    WHERE id_cabang_pengirim = ? 
    ORDER BY tanggal DESC, id DESC 
    LIMIT 8
");
$stmt->bind_param('i', $id_cabang_admin);
$stmt->execute();
$pengiriman_keluar = $stmt->get_result();
$stmt->close();

// === Ambil pengiriman masuk (8 terbaru, hanya status 'dalam pengiriman') ===
$stmt = $conn->prepare("
    SELECT * FROM pengiriman 
    WHERE id_cabang_penerima = ? 
    AND LOWER(status) = 'dalam pengiriman'
    ORDER BY tanggal DESC, id DESC 
    LIMIT 8
");
$stmt->bind_param('i', $id_cabang_admin);
$stmt->execute();
$pengiriman_masuk = $stmt->get_result();
$stmt->close();

$title = "Dashboard - Cendana Kargo";
$page  = "dashboard";

include '../../templates/header.php';
include '../../components/navDashboard.php';
include '../../components/sidebar_offcanvas.php';
?>

<div class="container-fluid">
  <div class="row">
    <?php include '../../components/sidebar.php'; ?>

    <main class="col-lg-10 bg-light">
      <div class="container-fluid p-4">

        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
          <div>
            <h1 class="h3 fw-bold mb-1">Dashboard Admin</h1>
            <p class="text-muted small mb-1">
              Selamat datang, <?= htmlspecialchars($_SESSION['username']); ?>!
            </p>
            <p class="text-muted small fw-semibold mb-2">
              Cabang: <?= htmlspecialchars($nama_cabang_admin); ?>
            </p>

            <div class="px-3 py-2 rounded-3 d-inline-block" 
                style="background-color: #d9f6fa; border: 1px solid #bde9ee;">
                <span class="fw-normal text-secondary" style="font-size: 0.9rem;">
                    Data untuk periode: 
                    <strong class="text-dark"><?= $selected_date_display; ?></strong>
                </span>
            </div>
          </div>

          <!-- Filter -->
          <div>
            <span class="badge text-bg-secondary me-2">Periode Data:</span>
            <div class="btn-group" role="group">
              <a href="?filter=bulan" class="btn btn-sm <?= $filter === 'bulan' ? 'btn-primary' : 'btn-outline-primary'; ?>">Bulan Ini</a>
              <a href="?filter=hari" class="btn btn-sm <?= $filter === 'hari' ? 'btn-primary' : 'btn-outline-primary'; ?>">Hari Ini</a>
            </div>
          </div>
        </div>

        <!-- Kartu Statistik -->
        <div class="row g-4 mb-4">
          <div class="col-xl-4 col-md-6">
            <div class="card border-0 shadow-sm h-100 bg-success bg-opacity-10">
              <div class="card-body">
                <p class="text-success mb-1 small fw-bold">TOTAL PENDAPATAN</p>
                <div class="d-flex justify-content-between align-items-center">
                  <h4 class="fw-bold text-success mb-0"><?= format_rupiah($total_pendapatan); ?></h4>
                  <i class="fa-solid fa-money-bill-wave text-success opacity-50 fs-4"></i>
                </div>
              </div>
            </div>
          </div>

          <div class="col-xl-4 col-md-6">
            <div class="card border-0 shadow-sm h-100 bg-primary bg-opacity-10">
              <div class="card-body">
                <p class="text-primary mb-1 small fw-bold">TOTAL PENGIRIMAN</p>
                <div class="d-flex justify-content-between align-items-center">
                  <h4 class="fw-bold text-primary mb-0"><?= $total_pengiriman; ?> Kiriman</h4>
                  <i class="fa-solid fa-truck text-primary opacity-50 fs-4"></i>
                </div>
              </div>
            </div>
          </div>

          <div class="col-xl-4 col-md-6">
            <div class="card border-0 shadow-sm h-100 bg-warning bg-opacity-25">
              <div class="card-body">
                <p class="text-secondary mb-1 small fw-bold">TOTAL SURAT JALAN</p>
                <div class="d-flex justify-content-between align-items-center">
                  <h4 class="fw-bold text-secondary mb-0"><?= $total_surat_jalan; ?></h4>
                  <i class="fa-solid fa-file-invoice text-secondary opacity-50 fs-4"></i>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Kartu Status Pengiriman -->
        <h5 class="fw-bold mb-3 mt-4">Status Pengiriman</h5>
        <div class="row g-4 mb-4">

        <!-- BKD -->
        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="card border-0 shadow-sm h-100 bg-warning bg-opacity-10">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                <p class="text-dark mb-1 small fw-bold">BKD</p>
                <h4 class="fw-bold text-dark mb-0"><?= $status_counts['bkd']; ?></h4>
                </div>
                <i class="fa-solid fa-box-open text-dark opacity-50 fs-3"></i>
            </div>
            </div>
        </div>

        <!-- Dalam Pengiriman -->
        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="card border-0 shadow-sm h-100 bg-info bg-opacity-10">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                <p class="text-dark mb-1 small fw-bold">Dalam Pengiriman</p>
                <h4 class="fw-bold text-dark mb-0"><?= $status_counts['dalam pengiriman']; ?></h4>
                </div>
                <i class="fa-solid fa-truck-moving text-dark opacity-50 fs-3"></i>
            </div>
            </div>
        </div>

        <!-- Sampai Tujuan -->
        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="card border-0 shadow-sm h-100 bg-success bg-opacity-10">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                <p class="text-dark mb-1 small fw-bold">Sampai Tujuan</p>
                <h4 class="fw-bold text-dark mb-0"><?= $status_counts['sampai tujuan']; ?></h4>
                </div>
                <i class="fa-solid fa-location-dot text-dark opacity-50 fs-3"></i>
            </div>
            </div>
        </div>

        <!-- POD -->
        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="card border-0 shadow-sm h-100 bg-primary bg-opacity-10">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                <p class="text-dark mb-1 small fw-bold">POD</p>
                <h4 class="fw-bold text-dark mb-0"><?= $status_counts['pod']; ?></h4>
                </div>
                <i class="fa-solid fa-file-circle-check text-dark opacity-50 fs-3"></i>
            </div>
            </div>
        </div>

        <!-- Dibatalkan -->
        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="card border-0 shadow-sm h-100 bg-danger bg-opacity-10">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                <p class="text-dark mb-1 small fw-bold">Dibatalkan</p>
                <h4 class="fw-bold text-dark mb-0"><?= $status_counts['dibatalkan']; ?></h4>
                </div>
                <i class="fa-solid fa-circle-xmark text-dark opacity-50 fs-3"></i>
            </div>
            </div>
        </div>

        </div>
        <!-- Dua Card Tabel -->
        <div class="row g-4 mb-5">

          <!-- Pengiriman Keluar -->
          <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
              <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
                <h5 class="fw-bold text-primary mb-0">Pengiriman Keluar</h5>
                <a href="pengiriman/?tipe=keluar" class="btn btn-sm btn-outline-primary">Lihat Semua</a>
              </div>
              <div class="card-body p-0">
                <div class="table-responsive">
                  <table class="table table-hover align-middle mb-0 small">
                    <thead class="table-light">
                      <tr>
                        <th class="px-3">No. Resi</th>
                        <th>Tujuan</th>
                        <th>Status</th>
                        <th class="text-center">Detail</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php if ($pengiriman_keluar->num_rows > 0): ?>
                        <?php while ($row = $pengiriman_keluar->fetch_assoc()): ?>
                          <tr>
                            <td class="px-3 fw-semibold"><?= htmlspecialchars($row['no_resi']); ?></td>
                            <td><?= htmlspecialchars($row['cabang_penerima']); ?></td>
                            <td>
                              <?php
                                $statusClass = match(strtolower($row['status'])) {
                                  'bkd' => 'warning',
                                  'dalam pengiriman' => 'info',
                                  'sampai tujuan' => 'success',
                                  'pod' => 'primary',
                                  'dibatalkan' => 'danger',
                                  default => 'secondary'
                                };
                              ?>
                              <span class="badge text-bg-<?= $statusClass; ?>"><?= htmlspecialchars($row['status']); ?></span>
                            </td>
                            <td class="text-center">
                              <a href="pengiriman/detail.php?id=<?= $row['id']; ?>" class="btn btn-sm btn-outline-info">
                                <i class="fa-solid fa-eye"></i>
                              </a>
                            </td>
                          </tr>
                        <?php endwhile; ?>
                      <?php else: ?>
                        <tr><td colspan="4" class="text-center py-4 text-muted">Belum ada pengiriman keluar.</td></tr>
                      <?php endif; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>

<!-- Pengiriman Masuk -->
<div class="col-lg-6">
  <div class="card border-0 shadow-sm h-100">
    <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
      <h5 class="fw-bold text-success mb-0">Pengiriman Masuk (Dalam Pengiriman)</h5>
      <a href="barang_masuk/index.php" class="btn btn-sm btn-outline-success">Lihat Semua</a>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
          <thead class="table-light">
            <tr>
              <th class="px-3">No. Resi</th>
              <th>Asal</th>
              <th>Status</th>
              <th class="text-center">Detail</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($pengiriman_masuk->num_rows > 0): ?>
              <?php while ($row = $pengiriman_masuk->fetch_assoc()): ?>
                <tr>
                  <td class="px-3 fw-semibold"><?= htmlspecialchars($row['no_resi']); ?></td>
                  <td><?= htmlspecialchars($row['cabang_pengirim']); ?></td>
                  <td>
                    <span class="badge text-bg-primary"><?= htmlspecialchars($row['status']); ?></span>
                  </td>
                  <td class="text-center">
                    <a href="barang_masuk/detail.php?id=<?= $row['id']; ?>" class="btn btn-sm btn-outline-success">
                      <i class="fa-solid fa-eye"></i>
                    </a>
                  </td>
                </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr>
                <td colspan="4" class="text-center py-4 text-muted">
                  Belum ada pengiriman masuk yang sedang dalam perjalanan.
                </td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>


        </div><!-- end row -->
      </div>
    </main>
  </div>
</div>

<?php include '../../templates/footer.php'; ?>
