<?php
session_start();
// 1. Otorisasi dan Otentikasi
if (!isset($_SESSION['username'])) {
    header('Location: ../../auth/login');
    exit;
}

// Pastikan hanya role superSuperAdmin yang bisa mengakses
if (isset($_SESSION['role']) && $_SESSION['role'] !== 'superSuperAdmin') {
    header('Location: ../../?error=unauthorized');
    exit;
}

include '../../config/database.php';

// =======================================================
// FILTERING LOGIC (Support: today / month / specific date / month-only via YYYY-MM)
// =======================================================
$filter = isset($_GET['filter']) && in_array($_GET['filter'], ['today', 'month'])
          ? htmlspecialchars($_GET['filter'])
          : 'month'; // Default: month (Bulan Ini)

$selected_date_display = '';
$date_condition = '';       // akan diisi sesuai pilihan
$date_condition_sj = '';    // akan diisi sesuai pilihan

// Prioritas:
// 1) Jika ada parameter periode (YYYY-MM-DD) -> gunakan itu (tanggal spesifik).
// 2) Jika tidak ada periode tapi ada parameter bulan (YYYY-MM) -> gunakan itu (bulanan).
// 3) Jika tidak ada, gunakan ?filter=today atau default bulan ini.
if (isset($_GET['periode']) && $_GET['periode'] !== '') {
    // Bersihkan input
    $periode_raw = trim($_GET['periode']);
    // jika format YYYY-MM-DD (tanggal spesifik)
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $periode_raw)) {
        $selected_date = $periode_raw;
        // gunakan filter per tanggal
        $date_condition = "DATE(p.tanggal) = '" . $selected_date . "'";
        $date_condition_sj = "DATE(sj.tanggal) = '" . $selected_date . "'";
        $selected_date_display = date('d F Y', strtotime($selected_date));
    }
    // jika format YYYY-MM (user mengetik tanpa -DD) treat as month
    elseif (preg_match('/^\d{4}-\d{2}$/', $periode_raw)) {
        $selected_month = $periode_raw;
        $date_condition = "DATE_FORMAT(p.tanggal, '%Y-%m') = '" . $selected_month . "'";
        $date_condition_sj = "DATE_FORMAT(sj.tanggal, '%Y-%m') = '" . $selected_month . "'";
        $selected_date_display = date('F Y', strtotime($selected_month . '-01'));
    }
    // jika bukan format di atas, fallback ke bulan ini
    else {
        $date_condition = "DATE_FORMAT(p.tanggal, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')";
        $date_condition_sj = "DATE_FORMAT(sj.tanggal, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')";
        $selected_date_display = date('F Y');
    }
} elseif (isset($_GET['bulan']) && preg_match('/^\d{4}-\d{2}$/', $_GET['bulan'])) {
    // Jika user pilih bulan (YYYY-MM) via input type=month
    $selected_month = $_GET['bulan'];
    $date_condition = "DATE_FORMAT(p.tanggal, '%Y-%m') = '" . $selected_month . "'";
    $date_condition_sj = "DATE_FORMAT(sj.tanggal, '%Y-%m') = '" . $selected_month . "'";
    $selected_date_display = date('F Y', strtotime($selected_month . '-01'));
} elseif ($filter === 'today') {
    // Data hari ini
    $date_condition = 'DATE(p.tanggal) = CURDATE()';
    $date_condition_sj = 'DATE(sj.tanggal) = CURDATE()';
    $selected_date_display = date('d F Y');
} else {
    // Default: data bulan ini
    $date_condition = "DATE_FORMAT(p.tanggal, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')";
    $date_condition_sj = "DATE_FORMAT(sj.tanggal, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')";
    $selected_date_display = date('F Y');
}

// =======================================================
// HELPER FUNCTIONS
// =======================================================

/**
 * Helper function untuk mendapatkan total count dari sebuah tabel untuk data ALL-TIME.
 */
function get_count_stat($conn, $table, $condition = '')
{
    $sql = "SELECT COUNT(*) as total FROM $table";
    if ($condition) {
        $sql .= " WHERE $condition";
    }

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        error_log("Database Error: Failed to prepare statement for table: $table.");

        return 0;
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $count = 0;

    if ($result && $result->num_rows > 0) {
        $count = $result->fetch_assoc()['total'];
    }

    $stmt->close();

    return $count;
}

/**
 * Helper function untuk mendapatkan total SUM dari sebuah kolom untuk data ALL-TIME.
 */
function get_total_revenue_all_time($conn)
{
$sql = "SELECT SUM(total_tarif) AS total_revenue 
        FROM pengiriman 
        WHERE status != 'dibatalkan'";

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        error_log('Database Error: Failed to prepare statement for all-time revenue.');

        return 0;
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $revenue = 0;

    if ($result && $result->num_rows > 0) {
        // Perhatikan nama field SUM di query: total_revenue, tapi di fetch kita guard dengan 'total_tarif' fallback
        $assoc = $result->fetch_assoc();
        $revenue = isset($assoc['total_revenue']) ? $assoc['total_revenue'] : ($assoc['total_tarif'] ?? 0);
    }

    $stmt->close();

    return $revenue;
}

/**
 * Helper function untuk format mata uang Rupiah.
 */
function format_rupiah($number)
{
    return 'Rp '.number_format($number, 0, ',', '.');
}

// =======================================================
// DATA FETCHING FUNCTIONS (Filtered Data)
// Menggunakan Conditional Aggregation untuk memastikan semua cabang tampil
// =======================================================

/**
 * Mengambil data pendapatan per cabang berdasarkan filter tanggal.
 */
function get_branch_revenue_data($conn, $date_condition)
{
    $data = [];
    $sql = "
        SELECT
            kc.nama_cabang,
            SUM(CASE WHEN p.id IS NOT NULL AND p.status != 'dibatalkan' AND $date_condition THEN p.total_tarif ELSE 0 END) AS total_revenue,
            SUM(CASE WHEN p.pembayaran = 'cash' AND p.status != 'dibatalkan' AND p.id IS NOT NULL AND $date_condition THEN p.total_tarif ELSE 0 END) AS cash_revenue,
            SUM(CASE WHEN p.pembayaran = 'transfer' AND p.status != 'dibatalkan' AND p.id IS NOT NULL AND $date_condition THEN p.total_tarif ELSE 0 END) AS transfer_revenue,
            SUM(CASE WHEN p.pembayaran = 'bayar di tempat' AND p.status != 'dibatalkan' AND p.id IS NOT NULL AND $date_condition THEN p.total_tarif ELSE 0 END) AS cod_revenue
        FROM
            kantor_cabang kc
        LEFT JOIN
            pengiriman p ON kc.nama_cabang = p.cabang_pengirim 
        GROUP BY
            kc.nama_cabang
        ORDER BY
            kc.nama_cabang
    ";

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        error_log('Database Error: Failed to prepare statement for branch revenue.');
        return $data;
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $data[$row['nama_cabang']] = $row;
    }
    $stmt->close();

    return $data;
}

/**
 * Mengambil data jumlah pengiriman per cabang berdasarkan filter tanggal.
 */
function get_branch_shipment_data($conn, $date_condition) // Hapus $date_param
{$data = [];
    // Kondisi tanggal diterapkan di dalam SUM/CASE untuk menjaga LEFT JOIN
    $sql = "
            SELECT
                kc.nama_cabang,
                SUM(CASE WHEN p.id IS NOT NULL AND $date_condition THEN 1 ELSE 0 END) AS total_shipments,
                SUM(CASE WHEN p.status = 'bkd' AND p.id IS NOT NULL AND $date_condition THEN 1 ELSE 0 END) AS count_proses,
                SUM(CASE WHEN p.status = 'dalam pengiriman' AND p.id IS NOT NULL AND $date_condition THEN 1 ELSE 0 END) AS count_pengiriman,
                SUM(CASE WHEN (p.status = 'sampai tujuan' OR p.status = 'pod') AND p.id IS NOT NULL AND $date_condition THEN 1 ELSE 0 END) AS count_selesai,
                SUM(CASE WHEN p.status = 'dibatalkan' AND p.id IS NOT NULL AND $date_condition THEN 1 ELSE 0 END) AS count_dibatalkan
            FROM
                kantor_cabang kc
            LEFT JOIN
                pengiriman p ON kc.nama_cabang = p.cabang_pengirim
            GROUP BY
                kc.nama_cabang
            ORDER BY
                kc.nama_cabang
        ";

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        error_log('Database Error: Failed to prepare statement for branch shipment.');
        return $data;
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $data[$row['nama_cabang']] = $row;
    }
    $stmt->close();

    return $data;
}

/**
 * Mengambil data surat jalan per cabang berdasarkan filter tanggal.
 */
function get_branch_manifest_data($conn, $date_condition_sj) // Hapus $date_param
{$data = [];
    // Kondisi tanggal diterapkan di dalam SUM/CASE untuk menjaga LEFT JOIN
    $sql = "
            SELECT
                kc.nama_cabang,
                SUM(CASE WHEN sj.id IS NOT NULL AND $date_condition_sj THEN 1 ELSE 0 END) AS total_manifests,
                SUM(CASE WHEN sj.status = 'draft' AND sj.id IS NOT NULL AND $date_condition_sj THEN 1 ELSE 0 END) AS count_draft,
                SUM(CASE WHEN sj.status = 'dalam perjalanan' AND sj.id IS NOT NULL AND $date_condition_sj THEN 1 ELSE 0 END) AS count_dalam_perjalanan,
                SUM(CASE WHEN sj.status = 'sampai tujuan' AND sj.id IS NOT NULL AND $date_condition_sj THEN 1 ELSE 0 END) AS count_sampai_tujuan,
                SUM(CASE WHEN sj.status = 'dibatalkan' AND sj.id IS NOT NULL AND $date_condition_sj THEN 1 ELSE 0 END) AS count_dibatalkan
            FROM
                kantor_cabang kc
            LEFT JOIN
                surat_jalan sj ON kc.id = sj.id_cabang_pengirim
            GROUP BY
                kc.nama_cabang
            ORDER BY
                kc.nama_cabang
        ";

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        error_log('Database Error: Failed to prepare statement for branch manifest.');
        return $data;
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $data[$row['nama_cabang']] = [
            'nama_cabang' => $row['nama_cabang'],
            'total_manifests' => $row['total_manifests'],
            'count_dibuat' => $row['count_draft'],
            'count_berangkat' => $row['count_dalam_perjalanan'],
            'count_sampai_tujuan' => $row['count_sampai_tujuan'],
            'count_dibatalkan' => $row['count_dibatalkan'],
        ];
    }
    $stmt->close();

    return $data;
}

// =======================================================
// EXECUTION & DATA AGGREGATION
// =======================================================

// 1. Fetch Core Statistics (non-shipment, All Time)
$total_cabang = get_count_stat($conn, 'Kantor_cabang');
$total_user = get_count_stat($conn, 'User');
$total_tarif = get_count_stat($conn, 'Tarif_pengiriman', "status = 'aktif'");
$total_pengiriman_all_time = get_count_stat($conn, 'pengiriman'); // Total Pengiriman All Time
$total_pendapatan_all_time = get_total_revenue_all_time($conn); // Total Pendapatan All Time

// 2. Fetch Detailed Branch Data (Filtered by date) - Menggunakan $date_condition
$revenue_data = get_branch_revenue_data($conn, $date_condition);
$shipment_data = get_branch_shipment_data($conn, $date_condition);
$manifest_data = get_branch_manifest_data($conn, $date_condition_sj);

// 3. Aggregate Filtered Counts for Top Cards
$total_pengiriman_filtered = 0;
$dalam_proses_filtered = 0;
$dalam_pengiriman_filtered = 0;
$selesai_filtered = 0;
$total_revenue_filtered = 0;

foreach ($shipment_data as $data) {
    $total_pengiriman_filtered += $data['total_shipments'];
    $dalam_proses_filtered += $data['count_proses'];
    $dalam_pengiriman_filtered += $data['count_pengiriman'];
    $selesai_filtered += $data['count_selesai'];
}

// Calculate Total Revenue from revenue_data
foreach ($revenue_data as $data) {
    $total_revenue_filtered += $data['total_revenue'];
}

// Calculate Total Manifests
$total_surat_jalan = 0;
foreach ($manifest_data as $data) {
    $total_surat_jalan += $data['total_manifests'];
}

// Set variabel kartu statistik teratas ke nilai yang sudah difilter
$total_pengiriman = $total_pengiriman_filtered;
$dalam_proses = $dalam_proses_filtered;
$dalam_pengiriman = $dalam_pengiriman_filtered;
$selesai = $selesai_filtered;
$total_pendapatan = $total_revenue_filtered;

// Mengambil daftar semua cabang untuk perulangan tabel
$stmt = $conn->prepare('SELECT nama_cabang FROM Kantor_cabang ORDER BY nama_cabang ASC');
$stmt->execute();
$all_branches_result = $stmt->get_result();
$all_branches = [];
while ($row = $all_branches_result->fetch_assoc()) {
    $all_branches[] = $row['nama_cabang'];
}
$stmt->close();
?>

<?php
$title = 'Dashboard SuperSuperAdmin - Cendana Kargo';
$page = 'dashboard';
include '../../templates/header.php';
include '../../components/navDashboard.php';
include '../../components/sidebar_offcanvas.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include '../../components/sidebar.php'; ?>
        <!-- Konten utama -->
        <div class="col-lg-10 bg-light">
            <div class="container-fluid p-4">
                <!-- Header -->
                <div class="mb-4 d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="h3 mb-1 fw-bold">Dashboard SuperSuperAdmin</h1>
                        <p class="text-muted small mb-2">
                            Selamat datang, <?php echo htmlspecialchars($_SESSION['username']); ?>!
                        </p>

                        <!-- Data Agregat Info Box -->
                        <!-- Modified: add date + month picker; if both present, date (periode) prioritized -->
                        <form method="GET" class="d-flex align-items-center gap-2" id="periodeForm">
                            <div class="px-3 py-2 rounded-3 d-inline-block" 
                                style="background-color: #d9f6fa; border: 1px solid #bde9ee;">
                                <label for="periode" class="fw-normal text-secondary" style="font-size: 0.9rem; margin-right:8px;">
                                    Data untuk periode:
                                </label>

                                <!-- date picker (specific day). If periode present, it will be used. -->
                                <input type="date" id="periode" name="periode" 
                                    value="<?php
                                        if (isset($_GET['periode']) && $_GET['periode'] !== '') {
                                            echo htmlspecialchars($_GET['periode']);
                                        } else {
                                            // default: empty (so default logic shows bulan ini)
                                            echo '';
                                        }
                                    ?>"
                                    class="form-control form-control-sm d-inline-block" 
                                    style="width: 150px; display:inline-block;">

                                <span class="fw-normal text-secondary mx-2" style="font-size: 0.9rem;">atau</span>

                                <!-- month picker (YYYY-MM). Only used if periode is not set. -->
                                <input type="month" id="bulan" name="bulan"
                                    value="<?php
                                        if (isset($_GET['bulan']) && $_GET['bulan'] !== '') {
                                            echo htmlspecialchars($_GET['bulan']);
                                        } else {
                                            echo '';
                                        }
                                    ?>"
                                    class="form-control form-control-sm d-inline-block" 
                                    style="width: 150px; display:inline-block;">
                            </div>
                            <!-- changed button to type="button" and added id so JS can control which param to send -->
                            <button type="button" id="tampilkanBtn" class="btn btn-sm btn-primary">Tampilkan</button>
                            <button type="button" id="resetPeriodeBtn" class="btn btn-sm btn-outline-secondary">Reset</button>
                        </form>

                        <!-- Small description of selected period -->
                        <div class="mt-2">
                            <small class="text-muted">Menampilkan: <strong><?php echo htmlspecialchars($selected_date_display); ?></strong></small>
                        </div>
                    </div>

                    <!-- Filter Tombol -->
                    <div>
                        <span class="badge text-bg-secondary me-2 align-self-center">Periode Cepat:</span>
                        <div class="btn-group" role="group" aria-label="Filter data">
                            <a href="?filter=month" 
                            class="btn btn-sm <?php echo $filter === 'month' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                            Bulan Ini
                            </a>
                            <a href="?filter=today" 
                            class="btn btn-sm <?php echo $filter === 'today' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                            Hari Ini
                            </a>
                        </div>
                    </div>
                </div>

                <?php if (isset($_GET['already_logined'])) { ?>
                    <div class="alert alert-info alert-dismissible fade show" role="alert">
                        <strong>Info!</strong> Anda sudah login sebelumnya.
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php } ?>
                
                <!-- Periode Tampilan -->
                <div class="row g-4 mb-4">
                    <!-- Ringkasan Pendapatan -->
                    <div class="col-xl-4 col-md-6">
                        <div class="card border-0 shadow-sm h-100 bg-success bg-opacity-10">
                            <div class="card-body">
                                <p class="text-success mb-1 small fw-bold">TOTAL PENDAPATAN</p>
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h4 class="mb-0 fw-bold text-success"><?php echo format_rupiah($total_pendapatan); ?></h4>
                                    </div>
                                    <i class="fa-solid fa-money-bill-wave text-success opacity-50" style="font-size: 1.8rem;"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Ringkasan Pengiriman -->
                    <div class="col-xl-4 col-md-6">
                        <div class="card border-0 shadow-sm h-100 bg-primary bg-opacity-10">
                            <div class="card-body">
                                <p class="text-primary mb-1 small fw-bold">TOTAL PENGIRIMAN</p>
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h4 class="mb-0 fw-bold text-primary"><?php echo $total_pengiriman; ?> Kiriman</h4>
                                    </div>
                                    <i class="fa-solid fa-truck text-primary opacity-50" style="font-size: 1.8rem;"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Total Surat Jalan -->
                    <div class="col-xl-4 col-md-6">
                        <div class="card border-0 shadow-sm h-100 bg-warning bg-opacity-25">
                            <div class="card-body">
                                <p class="text-secondary mb-1 small fw-bold">TOTAL SURAT JALAN</p>
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h4 class="mb-0 fw-bold text-secondary"><?php echo $total_surat_jalan; ?></h4>
                                    </div>
                                    <i class="fa-solid fa-file-invoice text-secondary opacity-50" style="font-size: 1.8rem;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- END NEW TOP SECTION -->
            
<!-- === LACAK PAKET=== -->
<div class="card border-0 shadow-sm mb-4">
  <div class="card-body">
    <h5 class="fw-bold text-danger mb-3">
      <i class="fa-solid fa-truck-fast me-2 text-danger"></i>Lacak Paket
    </h5>

    <!-- Input & Tombol -->
    <div class="row g-3 align-items-center">
      <div class="col-md-6 col-lg-5">
        <input type="text" id="resiSuper" class="form-control border-danger" placeholder="Masukkan nomor resi..." />
      </div>
      <div class="col-md-auto">
        <button id="btnLacakSuper" class="btn btn-danger">
          <i class="fa-solid fa-magnifying-glass"></i> Lacak Paket
        </button>
        <button id="btnHapusSuper" class="btn btn-outline-danger btn-sm ms-2" style="display:none;">
          <i class="fa-solid fa-eraser me-1"></i> Hapus
        </button>
      </div>
    </div>

    <!-- Alert -->
    <div id="alertSuper" 
        class="mt-3 bg-danger bg-opacity-10 border border-danger text-danger fw-semibold rounded-3 p-3" 
        style="display:none; font-size:14px;">
    </div>

    <!-- Hasil -->
    <div id="resultSuper" 
        style="display:none; margin-top:20px;" 
        class="p-3 rounded-3 border-start border-4 border-danger bg-light bg-opacity-10">
      <h6 class="fw-bold mb-3 text-danger">
        <i class="fa-solid fa-circle-check me-1"></i>Informasi Pengiriman
      </h6>
      <div class="table-responsive">
        <table class="table table-sm table-borderless mb-0">
          <tr><th style="width:30%">No. Resi</th><td id="displayResiSuper">-</td></tr>
          <tr><th>Nama Pengirim</th><td id="displayPengirimSuper">-</td></tr>
          <tr><th>Nama Penerima</th><td id="displayPenerimaSuper">-</td></tr>
          <tr><th>Asal</th><td id="displayAsalSuper">-</td></tr>
          <tr><th>Tujuan</th><td id="displayTujuanSuper">-</td></tr>
          <tr><th>Total Tarif</th><td id="displayTarifSuper">-</td></tr>
          <tr>
            <th>Status</th>
            <td id="displayStatusSuper">
              <span class="bg-danger text-white px-3 py-1 rounded-pill fw-semibold small">-</span>
            </td>
          </tr>
        </table>
      </div>
    </div>
  </div>
</div>

                <!-- Statistics Cards Row 3 (Original, All Time) -->
                <div class="row g-4 mb-4">
                    <!-- Kantor Cabang -->
                    <div class="col-xl-4 col-md-6 mb-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <p class="text-muted mb-1 small">Total Kantor Cabang</p>
                                        <h2 class="mb-0 fw-bold"><?php echo $total_cabang; ?></h2>
                                        <a href="kantor_cabang/" class="text-decoration-none text-primary small mt-2 d-inline-block">
                                            Kelola Cabang →
                                        </a>
                                    </div>
                                    <div class="p-3 bg-success bg-opacity-25 rounded-3 d-flex align-items-center justify-content-center" style="min-width:60px; min-height:60px;">
                                        <i class="fa-solid fa-building text-success fs-4"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Total User -->
                    <div class="col-xl-4 col-md-6 mb-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <p class="text-muted mb-1 small">Total User</p>
                                        <h2 class="mb-0 fw-bold"><?php echo $total_user; ?></h2>
                                        <a href="user/" class="text-decoration-none text-primary small mt-2 d-inline-block">
                                            Kelola User →
                                        </a>
                                    </div>
                                    <div class="p-3 bg-primary bg-opacity-25 rounded-3 d-flex align-items-center justify-content-center" style="min-width:60px; min-height:60px;">
                                        <i class="fa-solid fa-user-tie text-primary fs-4"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Total Tarif -->
                    <div class="col-xl-4 col-md-6 mb-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <p class="text-muted mb-1 small">Tarif Aktif</p>
                                    <h2 class="mb-0 fw-bold"><?php echo $total_tarif; ?></h2>
                                    <a href="tarif/" class="text-decoration-none text-primary small mt-2 d-inline-block">
                                        Kelola Tarif →
                                    </a>
                                </div>
                                <div class="p-3 bg-warning bg-opacity-25 rounded-3 d-flex align-items-center justify-content-center" style="min-width:60px; min-height:60px;">
                                    <i class="fa-solid fa-dollar-sign text-warning fs-4"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    </div>
                </div>

                <!-- ======================================================= -->
                <!-- AGGREGATE REPORTS PER BRANCH (Filtered) -->
                <!-- ======================================================= -->

                <!-- 1. Revenue Report per Branch -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-0 py-3">
                        <h5 class="mb-0 fw-bold">Laporan Pendapatan per Cabang</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover align-middle mb-0 small">
                                <thead class="table-light">
                                    <tr>
                                        <th class="px-3">No.</th>
                                        <th>Nama Cabang</th>
                                        <th class="text-end">Total</th>
                                        <th class="text-end">Cash</th>
                                        <th class="text-end">Transfer</th>
                                        <th class="text-end">Bayar di Tempat</th>
                                        <th class="text-center">Cetak Data</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $no = 1;
                                    foreach ($all_branches as $branch_name) {
                                        $data = $revenue_data[$branch_name] ?? [
                                            'total_revenue' => 0, 'cash_revenue' => 0,
                                            'transfer_revenue' => 0, 'cod_revenue' => 0,
                                        ];
                                        $periode_param = isset($_GET['periode']) ? '&periode=' . urlencode($_GET['periode']) : '';
                                        $bulan_param = isset($_GET['bulan']) ? '&bulan=' . urlencode($_GET['bulan']) : '';
                                    ?>
                                        <tr>
                                            <td class="px-3"><?php echo $no++; ?></td>
                                            <td class="fw-bold"><?php echo htmlspecialchars($branch_name); ?></td>
                                            <td class="text-end fw-bold"><?php echo format_rupiah($data['total_revenue']); ?></td>
                                            <td class="text-end"><?php echo format_rupiah($data['cash_revenue']); ?></td>
                                            <td class="text-end"><?php echo format_rupiah($data['transfer_revenue']); ?></td>
                                            <td class="text-end"><?php echo format_rupiah($data['cod_revenue']); ?></td>
                                            <td class="text-center">
                                                <a href="export/export.php?cabang=<?php echo urlencode($branch_name) . $periode_param . $bulan_param; ?>" 
                                                class="btn btn-sm btn-outline-success">
                                                <i class="fa-solid fa-file-export me-1"></i> Ekspor
                                                </a>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- 2. Shipment Count Report per Branch -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-0 py-3">
                        <h5 class="mb-0 fw-bold">Laporan Jumlah Pengiriman per Cabang </h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover align-middle mb-0 small">
                                <thead class="table-light">
                                    <tr>
                                        <th class="px-3">No.</th>
                                        <th>Nama Cabang</th>
                                        <th class="text-center">Total Pengiriman</th>
                                        <th class="text-center">BKD</th>
                                        <th class="text-center">Dalam Pengiriman</th>
                                        <th class="text-center">POD</th>
                                        <th class="text-center">Dibatalkan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $no = 1;
                                    foreach ($all_branches as $branch_name) {
                                    $data = $shipment_data[$branch_name] ?? [
                                        'total_shipments' => 0, 'count_proses' => 0,
                                        'count_pengiriman' => 0, 'count_selesai' => 0,
                                        'count_dibatalkan' => 0,
                                    ];
                                    ?>
                                        <tr>
                                            <td class="px-3"><?php echo $no++; ?></td>
                                            <td class="fw-bold"><?php echo htmlspecialchars($branch_name); ?></td>
                                            <td class="fw-bold text-center"><?php echo $data['total_shipments']; ?></td>
                                            <td class="text-center"><?php echo $data['count_proses']; ?></td>
                                            <td class="text-center"><?php echo $data['count_pengiriman']; ?></td>
                                            <td class="text-center"><?php echo $data['count_selesai']; ?></td>
                                            <td class="text-center"><?php echo $data['count_dibatalkan']; ?></td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- 3. Manifest/Surat Jalan Report per Branch -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-0 py-3">
                        <h5 class="mb-0 fw-bold">Laporan Surat Jalan  per Cabang </h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover align-middle mb-0 small">
                                <thead class="table-light">
                                    <tr>
                                        <th class="px-3">No.</th>
                                        <th>Nama Cabang</th>
                                        <th class="text-center">Total SJ</th>
                                        <th class="text-center">Draft</th>
                                        <th class="text-center">Dalam Perjalanan</th>
                                        <th class="text-center">Sampai Tujuan</th>
                                        <th class="text-center">Dibatalkan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $no = 1;
                                    foreach ($all_branches as $branch_name) {
                                    $data = $manifest_data[$branch_name] ?? [
                                    'total_manifests' => 0, 'count_dibuat' => 0,
                                    'count_berangkat' => 0, 'count_sampai_tujuan' => 0,
                                    'count_dibatalkan' => 0,
                                    ];
                                    ?>
                                        <tr>
                                            <td class="px-3"><?php echo $no++; ?></td>
                                            <td class="fw-bold"><?php echo htmlspecialchars($branch_name); ?></td>
                                            <td class="fw-bold text-center"><?php echo $data['total_manifests']; ?></td>
                                            <td class="text-center"><?php echo $data['count_dibuat']; ?></td>
                                            <td class="text-center"><?php echo $data['count_berangkat']; ?></td>
                                            <td class="text-center"><?php echo $data['count_sampai_tujuan']; ?></td>
                                            <td class="text-center"><?php echo $data['count_dibatalkan']; ?></td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript to handle choose-one-of-two behavior for periode/month picker -->
<script>
    document.getElementById('tampilkanBtn').addEventListener('click', function () {
    const periode = document.getElementById('periode').value;
    const bulan = document.getElementById('bulan').value;
    let url = window.location.pathname + '?'; // ambil URL tanpa query sebelumnya

    if (periode) {
        url += 'periode=' + encodeURIComponent(periode);
    } else if (bulan) {
        url += 'bulan=' + encodeURIComponent(bulan);
    } else {
        url += 'filter=month'; // fallback ke bulan ini
    }

    window.location.href = url; // redirect dengan parameter filter yang benar
});

document.getElementById('resetPeriodeBtn').addEventListener('click', function () {
    // hapus query parameter dan reload default
    window.location.href = window.location.pathname;
});
// ===== Variabel =====
const btnLacakSuper = document.getElementById('btnLacakSuper');
const inputResiSuper = document.getElementById('resiSuper');
const alertSuper = document.getElementById('alertSuper');
const resultSuper = document.getElementById('resultSuper');
const btnHapusSuper = document.getElementById('btnHapusSuper');

// ===== Alert helper =====
function showAlertSuper(message) {
  alertSuper.style.display = 'block';
  alertSuper.textContent = message;
}
function hideAlertSuper() {
  alertSuper.style.display = 'none';
}

// ===== Tampilkan hasil =====
function displayResultSuper(data) {
  document.getElementById('displayResiSuper').textContent = data.no_resi;
  document.getElementById('displayPengirimSuper').textContent = data.nama_pengirim;
  document.getElementById('displayPenerimaSuper').textContent = data.nama_penerima;
  document.getElementById('displayAsalSuper').textContent = data.asal;
  document.getElementById('displayTujuanSuper').textContent = data.tujuan;
  document.getElementById('displayTarifSuper').textContent = 'Rp ' + data.total_tarif;

  const spanStatus = document.getElementById('displayStatusSuper').querySelector('span');
  const s = data.status.toLowerCase();
  let bg = 'bg-secondary', text = 'text-white', label = data.status;

  switch (s) {
    case 'bkd': bg = 'bg-warning text-dark'; label = 'BKD'; break;
    case 'dalam pengiriman': bg = 'bg-info text-dark'; label = 'Dalam Pengiriman'; break;
    case 'sampai tujuan': bg = 'bg-primary text-white'; label = 'Sampai Tujuan'; break;
    case 'pod': bg = 'bg-success text-white'; label = 'POD'; break;
    case 'dibatalkan': bg = 'bg-danger text-white'; label = 'Dibatalkan'; break;
  }

  spanStatus.className = `px-3 py-1 rounded-pill fw-semibold small ${bg}`;
  spanStatus.textContent = label;

  resultSuper.style.display = 'block';
  btnHapusSuper.style.display = 'inline-block';
}

// ===== Tombol Lacak =====
btnLacakSuper.addEventListener('click', () => {
  const resi = inputResiSuper.value.trim();
  hideAlertSuper();
  resultSuper.style.display = 'none';
  btnHapusSuper.style.display = 'none';

  if (!resi) {
    showAlertSuper('⚠️ Nomor resi tidak boleh kosong.');
    return;
  }

  btnLacakSuper.disabled = true;
  btnLacakSuper.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Mencari...';

  fetch('../../utils/cekResi.php?no_resi=' + encodeURIComponent(resi))
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        hideAlertSuper();
        displayResultSuper(data.data);
      } else {
        showAlertSuper('❌ Nomor resi tidak ditemukan.');
      }
    })
    .catch(() => {
      showAlertSuper('🚫 Terjadi kesalahan. Silakan coba lagi.');
    })
    .finally(() => {
      btnLacakSuper.disabled = false;
      btnLacakSuper.innerHTML = '<i class="fa-solid fa-magnifying-glass"></i> Lacak Paket';
    });
});

// ===== Enter untuk submit =====
inputResiSuper.addEventListener('keypress', e => {
  if (e.key === 'Enter') btnLacakSuper.click();
});

// ===== Tombol Hapus =====
btnHapusSuper.addEventListener('click', function() {
  inputResiSuper.value = '';
  resultSuper.style.display = 'none';
  hideAlertSuper();
  btnHapusSuper.style.display = 'none';
});
</script>


<?php
    include '../../templates/footer.php';
?>
