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
// CEK STATUS CLOSING HARI INI
// =======================================================
$today = date('Y-m-d');
$stmt_closing = $conn->prepare("SELECT id, waktu_closing FROM Closing WHERE id_user = ? AND tanggal_closing = ?");
$stmt_closing->bind_param('is', $_SESSION['user_id'], $today);
$stmt_closing->execute();
$result_closing = $stmt_closing->get_result();
$is_closed_today = $result_closing->num_rows > 0;
$data_closing = $is_closed_today ? $result_closing->fetch_assoc() : null;
$stmt_closing->close();

// =======================================================
// PROSES CLOSING
// =======================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'closing') {
    if (!$is_closed_today) {
        // Hitung data hari ini untuk closing
        $stmt_data_closing = $conn->prepare("
            SELECT 
                COUNT(*) as total_pengiriman,
                SUM(CASE WHEN pembayaran = 'cash' AND status != 'dibatalkan' THEN total_tarif ELSE 0 END) as total_cash,
                SUM(CASE WHEN pembayaran = 'transfer' AND status != 'dibatalkan' THEN total_tarif ELSE 0 END) as total_transfer,
                SUM(CASE WHEN pembayaran = 'bayar di tempat' AND status != 'dibatalkan' THEN total_tarif ELSE 0 END) as total_cod,
                SUM(CASE WHEN status != 'dibatalkan' THEN total_tarif ELSE 0 END) as total_pendapatan
            FROM Pengiriman
            WHERE id_user = ? AND DATE(tanggal) = CURDATE()
        ");
        $stmt_data_closing->bind_param('i', $_SESSION['user_id']);
        $stmt_data_closing->execute();
        $data_hari_ini = $stmt_data_closing->get_result()->fetch_assoc();
        $stmt_data_closing->close();
        
        // Insert data closing
        $stmt_insert = $conn->prepare("
            INSERT INTO Closing 
            (id_user, id_cabang, tanggal_closing, total_pengiriman, total_cash, total_transfer, total_cod, total_pendapatan)
            VALUES (?, ?, CURDATE(), ?, ?, ?, ?, ?)
        ");
        
        $stmt_insert->bind_param(
            'iiidddd',
            $_SESSION['user_id'],
            $id_cabang_admin,
            $data_hari_ini['total_pengiriman'],
            $data_hari_ini['total_cash'],
            $data_hari_ini['total_transfer'],
            $data_hari_ini['total_cod'],
            $data_hari_ini['total_pendapatan'],
        );
        
        if ($stmt_insert->execute()) {
            $stmt_insert->close();
            header('Location: ?success=closing');
            exit;
        } else {
            $stmt_insert->close();
            header('Location: ?error=closing_failed');
            exit;
        }
    }
}

// =======================================================
// DATA HARI INI SAJA (TIDAK ADA FILTER)
// =======================================================
$where_clause = 'DATE(tanggal) = CURDATE()';
$selected_date_display = 'Hari Ini - ' . format_tanggal_indonesia(time());

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
        <?php if(isset($_GET['success']) && $_GET['success'] == 'closing'){
            $type = "success";
            $message = "Closing berhasil! Anda tidak dapat membuat pengiriman baru sampai besok.";
            include '../../components/alert.php';
        }?>
        <?php if(isset($_GET['error']) && $_GET['error'] == 'closing_failed'){
            $type = "danger";
            $message = "Gagal melakukan closing. Silakan coba lagi.";
            include '../../components/alert.php';
        }?>
        <?php if(isset($_GET['error']) && $_GET['error'] == 'sudah_closing'){
            $type = "warning";
            $message = "Anda sudah melakukan closing hari ini. Tidak dapat membuat pengiriman baru sampai besok.";
            include '../../components/alert.php';
        }?>
        <?php if(isset($_GET['error']) && $_GET['error'] == 'belum_closing'){
            $type = "warning";
            $message = "Anda harus melakukan closing terlebih dahulu sebelum export data.";
            include '../../components/alert.php';
        }?>
        <?php if($is_closed_today): ?>
        <div class="alert alert-info border-0 shadow-sm alert-dismissible fade show" role="alert">
            <div class="d-flex align-items-center">
                <i class="fa-solid fa-lock fs-4 me-3"></i>
                <div>
                    <strong>Status Closing:</strong> Anda sudah melakukan closing hari ini (<?php echo date('H:i', strtotime($data_closing['waktu_closing'])); ?> WIB).<br>
                    <small>Tidak dapat membuat pengiriman baru sampai besok.</small>
                </div>
            </div>
        </div>
        <?php endif; ?>

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
          <div class="d-flex flex-column flex-md-row justify-content-between align-items-stretch align-items-md-center gap-3 mb-3">
            <div class="alert alert-info mb-0 py-2 px-3 d-inline-flex align-items-center" style="font-size: 0.9rem;">
              <i class="fa-solid fa-info-circle me-2"></i>
              <span>Menampilkan data: <strong><?php echo htmlspecialchars($selected_date_display); ?></strong></span>
            </div>
            
            <!-- Tombol Closing -->
            <?php if(!$is_closed_today): ?>
            <div>
              <button type="button" class="btn btn-danger fw-semibold" data-bs-toggle="modal" data-bs-target="#modalClosing">
                <i class="fa-solid fa-lock me-2"></i>Lakukan Closing
              </button>
            </div>
            <?php endif; ?>
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
            <?php if($is_closed_today): ?>
            <a href="export/export.php" class="btn btn-sm btn-outline-success">
              <i class="fa-solid fa-file-export me-1"></i> Export Data Hari Ini
            </a>
            <?php else: ?>
            <button class="btn btn-sm btn-outline-secondary" disabled title="Lakukan closing terlebih dahulu">
              <i class="fa-solid fa-lock me-1"></i> Export Data (Belum Closing)
            </button>
            <?php endif; ?>
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

<!-- Modal Konfirmasi Closing -->
<div class="modal fade" id="modalClosing" tabindex="-1" aria-labelledby="modalClosingLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title" id="modalClosingLabel">
          <i class="fa-solid fa-lock me-2"></i>Konfirmasi Closing
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" action="index.php" id="formClosing">
        <input type="hidden" name="action" value="closing">
        <div class="modal-body">
          <div class="alert alert-warning" role="alert">
            <i class="fa-solid fa-exclamation-triangle me-2"></i>
            <strong>Perhatian!</strong> Setelah closing, Anda tidak dapat membuat pengiriman baru sampai besok.
          </div>
          
          <div class="mb-3">
            <h6 class="fw-bold mb-3">Ringkasan Hari Ini:</h6>
            <div class="row g-2">
              <div class="col-6">
                <small class="text-muted d-block">Total Pendapatan</small>
                <strong class="text-success"><?= format_rupiah($total_pendapatan ?? 0); ?></strong>
              </div>
              <div class="col-6">
                <small class="text-muted d-block">Total Pengiriman</small>
                <strong><?= $total_pengiriman ?? 0; ?> kiriman</strong>
              </div>
              <div class="col-4">
                <small class="text-muted d-block">Cash</small>
                <strong><?= format_rupiah($total_cash ?? 0); ?></strong>
              </div>
              <div class="col-4">
                <small class="text-muted d-block">Transfer</small>
                <strong><?= format_rupiah($total_transfer ?? 0); ?></strong>
              </div>
              <div class="col-4">
                <small class="text-muted d-block">COD</small>
                <strong><?= format_rupiah($total_cod ?? 0); ?></strong>
              </div>
            </div>
          </div>

          <div class="form-check mt-4">
            <input class="form-check-input" type="checkbox" id="konfirmasi" required>
            <label class="form-check-label" for="konfirmasi">
              Saya memastikan semua data sudah benar dan ingin melakukan closing hari ini.
            </label>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
            <i class="fa-solid fa-times me-2"></i>Batal
          </button>
          <button type="submit" class="btn btn-danger">
            <i class="fa-solid fa-lock me-2"></i>Ya, Lakukan Closing
          </button>
        </div>
      </form>
    </div>
  </div>
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

</script>
    </main>
  </div>
</div>

<?php include '../../templates/footer.php'; ?>
