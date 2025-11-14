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

// =======================================================
// FUNGSI HELPER UNTUK BAHASA INDONESIA
// =======================================================
function format_tanggal_indonesia($timestamp) {
    $bulan_indonesia = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
    ];
    
    $tanggal = date('d', $timestamp);
    $bulan = $bulan_indonesia[(int)date('m', $timestamp)];
    $tahun = date('Y', $timestamp);
    
    return "$tanggal $bulan $tahun";
}

function format_bulan_tahun_indonesia($timestamp) {
    $bulan_indonesia = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
    ];
    
    $bulan = $bulan_indonesia[(int)date('m', $timestamp)];
    $tahun = date('Y', $timestamp);
    
    return "$bulan $tahun";
}

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

// =======================================================
// FILTERING LOGIC - Single Smart Filter
// =======================================================
$filter_type = 'hari_ini'; // default
$filter_value = '';
$selected_date_display = '';
$where_clause = '';

// Cek parameter filter dari form
if (isset($_GET['filter_type'])) {
    $filter_type = htmlspecialchars($_GET['filter_type']);
}
if (isset($_GET['filter_value'])) {
    $filter_value = htmlspecialchars($_GET['filter_value']);
}

// Proses filter berdasarkan tipe
switch ($filter_type) {
    case 'hari_ini':
        $where_clause = 'tanggal >= CURDATE() AND tanggal < CURDATE() + INTERVAL 1 DAY';
        $selected_date_display = 'Hari Ini - ' . format_tanggal_indonesia(time());
        break;
    
    case 'bulan_ini':
        $where_clause = "MONTH(tanggal) = MONTH(CURDATE()) AND YEAR(tanggal) = YEAR(CURDATE())";
        $selected_date_display = 'Bulan Ini - ' . format_bulan_tahun_indonesia(time());
        break;
    
    case 'tanggal_spesifik':
        if (!empty($filter_value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filter_value)) {
            $where_clause = "DATE(tanggal) = '" . $filter_value . "'";
            $selected_date_display = 'Tanggal - ' . format_tanggal_indonesia(strtotime($filter_value));
        } else {
            // fallback ke bulan ini jika tanggal tidak valid
            $filter_type = 'bulan_ini';
            $where_clause = "MONTH(tanggal) = MONTH(CURDATE()) AND YEAR(tanggal) = YEAR(CURDATE())";
            $selected_date_display = 'Bulan Ini - ' . format_bulan_tahun_indonesia(time());
        }
        break;
    
    case 'bulan_spesifik':
        if (!empty($filter_value) && preg_match('/^\d{4}-\d{2}$/', $filter_value)) {
            $where_clause = "DATE_FORMAT(tanggal, '%Y-%m') = '" . $filter_value . "'";
            $selected_date_display = 'Bulan - ' . format_bulan_tahun_indonesia(strtotime($filter_value . '-01'));
        } else {
            // fallback ke bulan ini jika bulan tidak valid
            $filter_type = 'bulan_ini';
            $where_clause = "MONTH(tanggal) = MONTH(CURDATE()) AND YEAR(tanggal) = YEAR(CURDATE())";
            $selected_date_display = 'Bulan Ini - ' . format_bulan_tahun_indonesia(time());
        }
        break;
    
    default:
        // Default: bulan ini
        $where_clause = "MONTH(tanggal) = MONTH(CURDATE()) AND YEAR(tanggal) = YEAR(CURDATE())";
        $selected_date_display = 'Bulan Ini - ' . format_bulan_tahun_indonesia(time());
        break;
}

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

// === TOTAL PENDAPATAN & PEMBAYARAN (khusus admin login) ===
$id_admin = $_SESSION['user_id'];

$total_pendapatan = $conn->query("
    SELECT SUM(total_tarif) AS total 
    FROM pengiriman 
    WHERE $where_clause 
      AND id_cabang_pengirim = '$id_cabang_admin'
      AND id_user = '$id_admin'
      AND status != 'dibatalkan'
")->fetch_assoc()['total'] ?? 0;

$total_transfer = $conn->query("
    SELECT SUM(total_tarif) AS total 
    FROM pengiriman 
    WHERE $where_clause 
      AND id_cabang_pengirim = '$id_cabang_admin'
      AND id_user = '$id_admin'
      AND pembayaran = 'transfer'
      AND status != 'dibatalkan'
")->fetch_assoc()['total'] ?? 0;

$total_cash = $conn->query("
    SELECT SUM(total_tarif) AS total 
    FROM pengiriman 
    WHERE $where_clause 
      AND id_cabang_pengirim = '$id_cabang_admin'
      AND id_user = '$id_admin'
      AND pembayaran = 'cash'
      AND status != 'dibatalkan'
")->fetch_assoc()['total'] ?? 0;

$total_cod = $conn->query("
    SELECT SUM(total_tarif) AS total 
    FROM pengiriman 
    WHERE $where_clause 
      AND id_cabang_pengirim = '$id_cabang_admin'
      AND id_user = '$id_admin'
      AND pembayaran = 'bayar di tempat'
      AND status != 'dibatalkan'
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
      AND $where_clause
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

        <?php if (isset($_GET['already_logined'])){
            $type = "info";
            $message = "Anda sudah login sebelumnya";
            include '../../components/alert.php';
        }?>
        <?php if(isset($_GET['error']) && $_GET['error'] == 'no_data'){
            $type = "danger";
            $message = "Data tidak ditemukan";
            include '../../components/alert.php';
        }?>

        <!-- Header -->
        <div class="mb-4">
          <div class="d-flex justify-content-between align-items-start mb-3">
            <div>
              <h1 class="h3 mb-1 fw-bold">Dashboard Admin</h1>
              <p class="text-muted small mb-0">
                Selamat datang, <span class="fw-semibold"><?php echo htmlspecialchars($_SESSION['username']); ?></span>!
              </p>
              <p class="text-muted small mb-0">
                Cabang: <span class="fw-semibold"><?php echo htmlspecialchars($nama_cabang_admin); ?></span>
              </p>
            </div>
          </div>

          <!-- Display Current Period -->
          <div class="col-12 mb-3">
            <div class="alert alert-info mb-0 py-2 px-3 d-inline-flex align-items-center" style="font-size: 0.9rem;">
              <i class="fa-solid fa-info-circle me-2"></i>
              <span>Menampilkan data: <strong><?php echo htmlspecialchars($selected_date_display); ?></strong></span>
            </div>
          </div>

          <!-- Smart Filter Card -->  
          <div class="card border-0 shadow-sm mb-3">
            <div class="card-body p-3">
              <form method="GET" id="smartFilterForm" class="row g-3 align-items-end">
                
                <!-- Tipe Filter - Dropdown -->
                <div class="col-lg-3 col-md-4">
                  <label for="filter_type" class="form-label small text-muted mb-2 fw-semibold">
                    <i class="fa-solid fa-filter me-1"></i>Filter
                  </label>
                  <select class="form-select form-select-sm" id="filter_type" name="filter_type">
                    <option value="hari_ini" <?php echo $filter_type === 'hari_ini' ? 'selected' : ''; ?>>
                      📅 Hari Ini
                    </option>
                    <option value="bulan_ini" <?php echo $filter_type === 'bulan_ini' ? 'selected' : ''; ?>>
                      📆 Bulan Ini
                    </option>
                    <option value="tanggal_spesifik" <?php echo $filter_type === 'tanggal_spesifik' ? 'selected' : ''; ?>>
                      🗓️ Pilih Tanggal
                    </option>
                    <option value="bulan_spesifik" <?php echo $filter_type === 'bulan_spesifik' ? 'selected' : ''; ?>>
                      🗓️ Pilih Bulan
                    </option>
                  </select>
                </div>

                <!-- Input Value - Conditional Display -->
                <div class="col-lg-3 col-md-4" id="filter_value_container" style="<?php echo in_array($filter_type, ['tanggal_spesifik', 'bulan_spesifik']) ? '' : 'display:none;'; ?>">
                  <label for="filter_value" class="form-label small text-muted mb-1 fw-semibold">
                    <i class="fa-solid fa-calendar-check me-1"></i>
                    <span id="filter_value_label">
                      <?php echo $filter_type === 'bulan_spesifik' ? 'Pilih Bulan' : 'Pilih Tanggal'; ?>
                    </span>
                  </label>
                  <input type="<?php echo $filter_type === 'bulan_spesifik' ? 'month' : 'date'; ?>" 
                         class="form-control form-control-sm" 
                         id="filter_value" 
                         name="filter_value"
                         value="<?php echo htmlspecialchars($filter_value); ?>">
                </div>

                <!-- Action Buttons -->
                <div class="col-lg-auto col-md-4">
                  <button type="submit" class="btn btn-sm btn-success me-1">
                    <i class="fa-solid fa-check me-1"></i>Terapkan
                  </button>
                  <button type="button" id="resetFilterBtn" class="btn btn-sm btn-outline-secondary">
                    <i class="fa-solid fa-rotate-left me-1"></i>Reset
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>


        <!-- === CARD TOTAL PENDAPATAN === -->
        <div class="row g-4 mb-4">
          <div class="col-xl-12 col-md-6">
            <div class="card border-0 shadow-sm h-100 bg-success bg-opacity-10">
              <div class="card-body">
                <p class="text-success mb-1 small fw-bold">TOTAL PENDAPATAN</p>
                <div class="d-flex justify-content-between align-items-center">
                  <div>
                    <h4 class="mb-0 fw-bold text-success">
                      <?= format_rupiah($total_pendapatan ?? 0); ?>
                    </h4>
                    <small class="text-muted">Periode: <?= $selected_date_display; ?></small>
                  </div>
                  <i class="fa-solid fa-money-bill-wave text-success opacity-50" style="font-size:1.8rem;"></i>
                </div>
              </div>
            </div>
          </div>
        </div>
          <!-- === CARD RINCIAN METODE PEMBAYARAN === -->
        <div class="row g-4 mb-4">
          <!-- Transfer -->
          <div class="col-xl-4 col-md-6">
            <div class="card border-0 shadow-sm h-100 bg-info bg-opacity-10">
              <div class="card-body">
                <p class="text-info mb-1 small fw-bold">TOTAL TRANSFER</p>
                <div class="d-flex justify-content-between align-items-center">
                  <div>
                    <h4 class="mb-0 fw-bold text-info"><?= format_rupiah($total_transfer ?? 0); ?></h4>
                    <small class="text-muted">Periode: <?= $selected_date_display; ?></small>
                  </div>
                  <i class="fa-solid fa-credit-card text-info opacity-50" style="font-size:1.8rem;"></i>
                </div>
              </div>
            </div>
          </div>

          <!-- Cash -->
          <div class="col-xl-4 col-md-6">
            <div class="card border-0 shadow-sm h-100 bg-warning bg-opacity-10">
              <div class="card-body">
                <p class="text-warning mb-1 small fw-bold">TOTAL CASH</p>
                <div class="d-flex justify-content-between align-items-center">
                  <div>
                    <h4 class="mb-0 fw-bold text-warning"><?= format_rupiah($total_cash ?? 0); ?></h4>
                    <small class="text-muted">Periode: <?= $selected_date_display; ?></small>
                  </div>
                  <i class="fa-solid fa-money-bill-1-wave text-warning opacity-50" style="font-size:1.8rem;"></i>
                </div>
              </div>
            </div>
          </div>

          <!-- Bayar di Tempat -->
          <div class="col-xl-4 col-md-6">
            <div class="card border-0 shadow-sm h-100 bg-danger bg-opacity-10">
              <div class="card-body">
                <p class="text-danger mb-1 small fw-bold">BAYAR DI TEMPAT (COD)</p>
                <div class="d-flex justify-content-between align-items-center">
                  <div>
                    <h4 class="mb-0 fw-bold text-danger"><?= format_rupiah($total_cod ?? 0); ?></h4>
                    <small class="text-muted">Periode: <?= $selected_date_display; ?></small>
                  </div>
                  <i class="fa-solid fa-truck-ramp-box text-danger opacity-50" style="font-size:1.8rem;"></i>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="text-end">
            <?php
              // Build export URL with filter params
              $export_params = 'filter_type=' . urlencode($filter_type);
              if (!empty($filter_value)) {
                  $export_params .= '&filter_value=' . urlencode($filter_value);
              }
            ?>
            <a href="export/export.php?<?php echo $export_params; ?>" class="btn btn-sm btn-outline-success">
              <i class="fa-solid fa-file-export me-1"></i> Export Data
            </a>
        </div>


        <!-- === CARD STATUS PENGIRIMAN === -->
        <div class="row g-4 mb-4">
          <!-- === JUDUL UNTUK STATUS PENGIRIMAN === -->
          <h5 class="fw-bold text-dark mb-0 mt-5"> Status Pengiriman Cabang <?=$_SESSION['cabang']?></h5>
          <!-- BKD -->
          <div class="col-xl-4 col-md-6">
            <div class="card border-0 shadow-sm h-100 bg-warning bg-opacity-10">
              <div class="card-body">
                <p class="text-secondary mb-1 small fw-bold">BKD</p>
                <div class="d-flex justify-content-between align-items-center">
                  <div>
                    <h4 class="mb-0 fw-bold text-secondary"><?= $status_counts['bkd']; ?> Kiriman</h4>
                  </div>
                  <i class="fa-solid fa-box-open text-secondary opacity-50" style="font-size: 1.8rem;"></i>
                </div>
              </div>
            </div>
          </div>

          <!-- Dalam Pengiriman -->
          <div class="col-xl-4 col-md-6">
            <div class="card border-0 shadow-sm h-100 bg-primary bg-opacity-10">
              <div class="card-body">
                <p class="text-primary mb-1 small fw-bold">DALAM PENGIRIMAN</p>
                <div class="d-flex justify-content-between align-items-center">
                  <div>
                    <h4 class="mb-0 fw-bold text-primary"><?= $status_counts['dalam pengiriman']; ?> Kiriman</h4>
                  </div>
                  <i class="fa-solid fa-truck-moving text-primary opacity-50" style="font-size: 1.8rem;"></i>
                </div>
              </div>
            </div>
          </div>

          <!-- Sampai Tujuan -->
          <div class="col-xl-4 col-md-6">
            <div class="card border-0 shadow-sm h-100 bg-info bg-opacity-10">
              <div class="card-body">
                <p class="text-info mb-1 small fw-bold">SAMPAI TUJUAN</p>
                <div class="d-flex justify-content-between align-items-center">
                  <div>
                    <h4 class="mb-0 fw-bold text-info"><?= $status_counts['sampai tujuan']; ?> Kiriman</h4>
                  </div>
                  <i class="fa-solid fa-location-dot text-info opacity-50" style="font-size: 1.8rem;"></i>
                </div>
              </div>
            </div>
          </div>

          <!-- POD -->
          <div class="col-xl-4 col-md-6">
            <div class="card border-0 shadow-sm h-100 bg-success bg-opacity-10">
              <div class="card-body">
                <p class="text-success mb-1 small fw-bold">POD</p>
                <div class="d-flex justify-content-between align-items-center">
                  <div>
                    <h4 class="mb-0 fw-bold text-success"><?= $status_counts['pod']; ?> Kiriman</h4>
                  </div>
                  <i class="fa-solid fa-file-circle-check text-success opacity-50" style="font-size: 1.8rem;"></i>
                </div>
              </div>
            </div>
          </div>

          <!-- Dibatalkan -->
          <div class="col-xl-4 col-md-6">
            <div class="card border-0 shadow-sm h-100 bg-danger bg-opacity-10">
              <div class="card-body">
                <p class="text-danger mb-1 small fw-bold">DIBATALKAN</p>
                <div class="d-flex justify-content-between align-items-center">
                  <div>
                    <h4 class="mb-0 fw-bold text-danger"><?= $status_counts['dibatalkan']; ?> Kiriman</h4>
                  </div>
                  <i class="fa-solid fa-circle-xmark text-danger opacity-50" style="font-size: 1.8rem;"></i>
                </div>
              </div>
            </div>
          </div>

        </div>


        <!-- === LACAK PAKET === -->
        <div class="card border-0 shadow-sm mb-4">
          <div class="card-body">
            <h5 class="fw-bold text-dark mb-3">
              <i class="fa-solid fa-truck-fast me-2 text-danger"></i>Lacak Paket
            </h5>

            <!-- Input & Tombol -->
            <div class="row g-3 align-items-center">
              <div class="col-md-6 col-lg-5">
                <input type="text" id="resiAdmin" class="form-control" placeholder="Masukkan nomor resi..." />
              </div>
              <div class="col-md-auto">
                <button id="btnLacakAdmin" class="btn btn-danger">
                  <i class="fa-solid fa-magnifying-glass"></i> Lacak Paket
                </button>
                <button id="btnHapusPencarian" class="btn btn-outline-danger btn-sm ms-2" style="display:none;">
                  <i class="fa-solid fa-eraser me-1"></i> Hapus
                </button>
              </div>
            </div>

            <!-- Alert -->
            <div id="alertLacakAdmin" 
                class="mt-3" 
                style="display:none; padding:10px; border-radius:8px; font-size:14px;">
            </div>

            <!-- Hasil -->
            <div id="resultLacakAdmin" 
                style="display:none; margin-top:20px;" 
                class="p-3 rounded-3 border-start border-4 border-danger bg-light-subtle">
              <h6 class="fw-bold mb-3 text-danger">
                <i class="fa-solid fa-circle-check me-1"></i>Informasi Pengiriman
              </h6>
              <div class="table-responsive">
                <table class="table table-sm table-borderless mb-0">
                  <tr><th style="width:30%">No. Resi</th><td id="displayResiAdmin">-</td></tr>
                  <tr><th>Nama Pengirim</th><td id="displayPengirimAdmin">-</td></tr>
                  <tr><th>Nama Penerima</th><td id="displayPenerimaAdmin">-</td></tr>
                  <tr><th>Asal</th><td id="displayAsalAdmin">-</td></tr>
                  <tr><th>Tujuan</th><td id="displayTujuanAdmin">-</td></tr>
                  <tr><th>Total Tarif</th><td id="displayTarifAdmin">-</td></tr>
                  <tr>
                    <th>Status</th>
                    <td id="displayStatusAdmin">
                      <span style="padding:6px 12px; border-radius:20px; font-size:13px; font-weight:600;">-</span>
                    </td>
                  </tr>
                </table>
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
                <h5 class="fw-bold text-dark mb-0">Pengiriman Keluar</h5>
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
                <h5 class="fw-bold text-dark mb-0">Pengiriman Masuk (Dalam Pengiriman)</h5>
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

        </div>

      </div><!-- end row -->
    </div>
<script>
const btnLacakAdmin = document.getElementById('btnLacakAdmin');
const inputResiAdmin = document.getElementById('resiAdmin');
const alertLacakAdmin = document.getElementById('alertLacakAdmin');
const resultLacakAdmin = document.getElementById('resultLacakAdmin');
const btnHapusPencarian = document.getElementById('btnHapusPencarian');

// ===== Alert helper =====
function showAlertAdmin(message, type) {
  alertLacakAdmin.style.display = 'block';
  alertLacakAdmin.textContent = message;
  if (type === 'error') {
    alertLacakAdmin.style.backgroundColor = '#f8d7da';
    alertLacakAdmin.style.color = '#721c24';
    alertLacakAdmin.style.border = '1px solid #f5c6cb';
  } else if (type === 'success') {
    alertLacakAdmin.style.backgroundColor = '#d4edda';
    alertLacakAdmin.style.color = '#155724';
    alertLacakAdmin.style.border = '1px solid #c3e6cb';
  }
}
function hideAlertAdmin() {
  alertLacakAdmin.style.display = 'none';
}

// ===== Tampilkan hasil pencarian =====
function displayResultAdmin(data) {
  document.getElementById('displayResiAdmin').textContent = data.no_resi;
  document.getElementById('displayPengirimAdmin').textContent = data.nama_pengirim;
  document.getElementById('displayPenerimaAdmin').textContent = data.nama_penerima;
  document.getElementById('displayAsalAdmin').textContent = data.asal;
  document.getElementById('displayTujuanAdmin').textContent = data.tujuan;
  document.getElementById('displayTarifAdmin').textContent = 'Rp ' + data.total_tarif;

  const spanStatus = document.getElementById('displayStatusAdmin').querySelector('span');
  const s = data.status.toLowerCase();

  let bg = '#e2e3e5', text = '#383d41', label = data.status;
  switch (s) {
    case 'bkd': bg='#fff3cd'; text='#856404'; label='BKD'; break;
    case 'dalam pengiriman': bg='#cce5ff'; text='#004085'; label='Dalam Pengiriman'; break;
    case 'sampai tujuan': bg='#d1ecf1'; text='#0c5460'; label='Sampai Tujuan'; break;
    case 'pod': bg='#d4edda'; text='#155724'; label='POD'; break;
    case 'dibatalkan': bg='#f8d7da'; text='#721c24'; label='Dibatalkan'; break;
  }

  spanStatus.textContent = label;
  spanStatus.style.backgroundColor = bg;
  spanStatus.style.color = text;

  resultLacakAdmin.style.display = 'block';
  btnHapusPencarian.style.display = 'inline-block'; // tampilkan tombol hapus
}

// ===== Tombol Lacak ditekan =====
btnLacakAdmin.addEventListener('click', () => {
  const resi = inputResiAdmin.value.trim();
  hideAlertAdmin();
  resultLacakAdmin.style.display = 'none';
  btnHapusPencarian.style.display = 'none';

  if (!resi) {
    showAlertAdmin('Nomor resi tidak boleh kosong', 'error');
    return;
  }

  btnLacakAdmin.disabled = true;
  btnLacakAdmin.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Mencari...';

  fetch('../../utils/cekResi.php?no_resi=' + encodeURIComponent(resi))
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        hideAlertAdmin();
        displayResultAdmin(data.data);
      } else {
        let msg = data.message || 'Nomor resi tidak ditemukan';
        showAlertAdmin(msg, 'error');
      }
    })
    .catch(err => {
      console.error(err);
      showAlertAdmin('Terjadi kesalahan. Silakan coba lagi.', 'error');
    })
    .finally(() => {
      btnLacakAdmin.disabled = false;
      btnLacakAdmin.innerHTML = '<i class="fa-solid fa-magnifying-glass"></i> Lacak Paket';
    });
});

// ===== Enter untuk submit =====
inputResiAdmin.addEventListener('keypress', e => {
  if (e.key === 'Enter') btnLacakAdmin.click();
});

// ===== Tombol Hapus Pencarian =====
btnHapusPencarian.addEventListener('click', function() {
  inputResiAdmin.value = '';
  resultLacakAdmin.style.display = 'none';
  hideAlertAdmin();
  btnHapusPencarian.style.display = 'none';

  // Reset tampilan data
  document.getElementById('displayResiAdmin').textContent = '-';
  document.getElementById('displayPengirimAdmin').textContent = '-';
  document.getElementById('displayPenerimaAdmin').textContent = '-';
  document.getElementById('displayAsalAdmin').textContent = '-';
  document.getElementById('displayTujuanAdmin').textContent = '-';
  document.getElementById('displayTarifAdmin').textContent = '-';

  const spanStatus = document.getElementById('displayStatusAdmin').querySelector('span');
  spanStatus.textContent = '-';
  spanStatus.style.backgroundColor = '';
  spanStatus.style.color = '';
});

// Smart Filter - Dynamic show/hide input based on filter type
const filterTypeSelect = document.getElementById('filter_type');
const filterValueContainer = document.getElementById('filter_value_container');
const filterValueInput = document.getElementById('filter_value');
const filterValueLabel = document.getElementById('filter_value_label');
const resetFilterBtn = document.getElementById('resetFilterBtn');

if (filterTypeSelect) {
  // Handle filter type change
  filterTypeSelect.addEventListener('change', function() {
      const selectedType = this.value;
      
      if (selectedType === 'tanggal_spesifik') {
          filterValueContainer.style.display = 'block';
          filterValueInput.type = 'date';
          filterValueLabel.textContent = 'Pilih Tanggal';
          filterValueInput.required = true;
      } else if (selectedType === 'bulan_spesifik') {
          filterValueContainer.style.display = 'block';
          filterValueInput.type = 'month';
          filterValueLabel.textContent = 'Pilih Bulan';
          filterValueInput.required = true;
      } else {
          // Hari ini atau Bulan ini - tidak perlu input tambahan
          filterValueContainer.style.display = 'none';
          filterValueInput.required = false;
          filterValueInput.value = '';
      }
  });

  // Reset button
  resetFilterBtn.addEventListener('click', function() {
      window.location.href = window.location.pathname;
  });

  // Form validation
  document.getElementById('smartFilterForm').addEventListener('submit', function(e) {
      const filterType = filterTypeSelect.value;
      
      if ((filterType === 'tanggal_spesifik' || filterType === 'bulan_spesifik') && !filterValueInput.value) {
          e.preventDefault();
          alert('Silakan pilih ' + (filterType === 'tanggal_spesifik' ? 'tanggal' : 'bulan') + ' terlebih dahulu!');
      }
  });
}
</script>
    </main>
  </div>
</div>

<?php include '../../templates/footer.php'; ?>
