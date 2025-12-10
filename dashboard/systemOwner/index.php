<?php
session_start();
// 1. Otorisasi dan Otentikasi
if (!isset($_SESSION['username'])) {
    header('Location: ../../auth/login');
    exit;
}

// Pastikan hanya role systemOwner yang bisa mengakses
if (isset($_SESSION['role']) && $_SESSION['role'] !== 'systemOwner') {
    header('Location: ../../?error=unauthorized');
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

// =======================================================
// FILTERING LOGIC - Single Smart Filter
// =======================================================
$filter_type = 'bulan_ini'; // default
$filter_value = '';
$selected_date_display = '';
$date_condition = '';
$date_condition_sj = '';

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
        $date_condition = 'DATE(p.tanggal) = CURDATE()';
        $date_condition_sj = 'DATE(sj.tanggal) = CURDATE()';
        $selected_date_display = 'Hari Ini - ' . format_tanggal_indonesia(time());
        break;
    
    case 'bulan_ini':
        $date_condition = "DATE_FORMAT(p.tanggal, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')";
        $date_condition_sj = "DATE_FORMAT(sj.tanggal, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')";
        $selected_date_display = 'Bulan Ini - ' . format_bulan_tahun_indonesia(time());
        break;
    
    case 'tanggal_spesifik':
        if (!empty($filter_value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filter_value)) {
            $date_condition = "DATE(p.tanggal) = '" . $filter_value . "'";
            $date_condition_sj = "DATE(sj.tanggal) = '" . $filter_value . "'";
            $selected_date_display = 'Tanggal - ' . format_tanggal_indonesia(strtotime($filter_value));
        } else {
            // fallback ke bulan ini jika tanggal tidak valid
            $filter_type = 'bulan_ini';
            $date_condition = "DATE_FORMAT(p.tanggal, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')";
            $date_condition_sj = "DATE_FORMAT(sj.tanggal, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')";
            $selected_date_display = 'Bulan Ini - ' . format_bulan_tahun_indonesia(time());
        }
        break;
    
    case 'bulan_spesifik':
        if (!empty($filter_value) && preg_match('/^\d{4}-\d{2}$/', $filter_value)) {
            $date_condition = "DATE_FORMAT(p.tanggal, '%Y-%m') = '" . $filter_value . "'";
            $date_condition_sj = "DATE_FORMAT(sj.tanggal, '%Y-%m') = '" . $filter_value . "'";
            $selected_date_display = 'Bulan - ' . format_bulan_tahun_indonesia(strtotime($filter_value . '-01'));
        } else {
            // fallback ke bulan ini jika bulan tidak valid
            $filter_type = 'bulan_ini';
            $date_condition = "DATE_FORMAT(p.tanggal, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')";
            $date_condition_sj = "DATE_FORMAT(sj.tanggal, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')";
            $selected_date_display = 'Bulan Ini - ' . format_bulan_tahun_indonesia(time());
        }
        break;
    
    default:
        // Default: bulan ini
        $date_condition = "DATE_FORMAT(p.tanggal, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')";
        $date_condition_sj = "DATE_FORMAT(sj.tanggal, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')";
        $selected_date_display = 'Bulan Ini - ' . format_bulan_tahun_indonesia(time());
        break;
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
    // Buat date condition untuk subquery dengan prefix p2
    $date_condition_p2 = str_replace('p.tanggal', 'p2.tanggal', $date_condition);
    
    $sql = "
        SELECT
            kc.nama_cabang,
            (SUM(CASE WHEN p.pembayaran = 'cash' AND p.cabang_pengirim = kc.nama_cabang AND p.status != 'dibatalkan' AND p.id IS NOT NULL AND $date_condition THEN p.total_tarif ELSE 0 END) +
             COALESCE((SELECT SUM(p2.total_tarif)
                       FROM pengiriman p2 
                       JOIN pengambilan pg ON p2.no_resi = pg.no_resi
                       WHERE p2.cabang_penerima = kc.nama_cabang
                         AND p2.pembayaran = 'invoice' 
                         AND p2.status = 'pod' 
                         AND $date_condition_p2), 0)
            ) AS cash_revenue,
            SUM(CASE WHEN p.pembayaran = 'transfer' AND p.cabang_pengirim = kc.nama_cabang AND p.status != 'dibatalkan' AND p.id IS NOT NULL AND $date_condition THEN p.total_tarif ELSE 0 END) AS transfer_revenue,
            SUM(CASE WHEN p.pembayaran = 'invoice' AND p.cabang_pengirim = kc.nama_cabang AND p.status != 'dibatalkan' AND p.id IS NOT NULL AND $date_condition THEN p.total_tarif ELSE 0 END) AS cod_revenue
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
        $row['total_revenue'] = $row['cash_revenue'] + $row['transfer_revenue'];
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
    // Catatan: sampai_tujuan dan POD dihitung berdasarkan cabang_penerima (cabang tujuan)
    $sql = "
            SELECT
                kc.nama_cabang,
                SUM(CASE WHEN p.id IS NOT NULL AND $date_condition THEN 1 ELSE 0 END) AS total_shipments,
                SUM(CASE WHEN p.status = 'bkd' AND p.id IS NOT NULL AND $date_condition THEN 1 ELSE 0 END) AS count_proses,
                SUM(CASE WHEN p.status = 'dalam pengiriman' AND p.id IS NOT NULL AND $date_condition THEN 1 ELSE 0 END) AS count_pengiriman,
                (SELECT COUNT(*) FROM pengiriman WHERE cabang_penerima = kc.nama_cabang AND status = 'sampai tujuan' AND $date_condition) AS count_sampai_tujuan,
                (SELECT COUNT(*) FROM pengiriman WHERE cabang_penerima = kc.nama_cabang AND status = 'pod' AND $date_condition) AS count_selesai,
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
                SUM(CASE WHEN sj.status = 'diberangkatkan' AND sj.id IS NOT NULL AND $date_condition_sj THEN 1 ELSE 0 END) AS count_diberangkatkan
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
            'count_draft' => $row['count_draft'],
            'count_diberangkatkan' => $row['count_diberangkatkan'],
        ];
    }
    $stmt->close();

    return $data;
}

// =======================================================
// EXECUTION & DATA AGGREGATION
// =======================================================

// 1. Fetch Core Statistics (non-shipment, All Time)
$total_cabang = get_count_stat($conn, 'kantor_cabang');
$total_user = get_count_stat($conn, 'user');
$total_tarif = get_count_stat($conn, 'tarif_pengiriman', "status = 'aktif'");
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
$stmt = $conn->prepare('SELECT nama_cabang FROM kantor_cabang ORDER BY nama_cabang ASC');
$stmt->execute();
$all_branches_result = $stmt->get_result();
$all_branches = [];
while ($row = $all_branches_result->fetch_assoc()) {
    $all_branches[] = $row['nama_cabang'];
}
$stmt->close();
?>

<?php
$title = 'Dashboard systemOwner - Cendana Kargo';
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
                            <h1 class="h3 mb-1 fw-bold">Dashboard System Owner</h1>
                            <p class="text-muted small mb-0">
                                Selamat datang, <span class="fw-semibold"><?php echo htmlspecialchars($_SESSION['username']); ?></span>!
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

                    <!-- Smart Filter Card - Single Popup Concept -->
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

                <!-- === LACAK PAKET=== -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body">
                        <h5 class="fw-bold text-danger mb-3">
                        <i class="fa-solid fa-truck-fast me-2 text-danger"></i>Lacak Paket
                        </h5>

                        <!-- Input & Tombol -->
                        <div class="row g-3 align-items-center">
                        <div class="col-md-6 col-lg-5">
                            <input type="text" id="resiSuper" class="form-control border-danger" placeholder="Masukkan nomor resi..."/>
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
                                        <th class="text-end">Cash + Invoice</th>
                                        <th class="text-end">Transfer</th>
                                        <th class="text-end">Invoice</th>
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
                                        // Build export URL with new filter params
                                        $export_params = 'cabang=' . urlencode($branch_name);
                                        $export_params .= '&filter_type=' . urlencode($filter_type);
                                        if (!empty($filter_value)) {
                                            $export_params .= '&filter_value=' . urlencode($filter_value);
                                        }
                                    ?>
                                        <tr>
                                            <td class="px-3" style="white-space: nowrap;"><?php echo $no++; ?></td>
                                            <td class="fw-bold" style="white-space: nowrap;"><?php echo htmlspecialchars($branch_name); ?></td>
                                            <td class="text-end fw-bold" style="white-space: nowrap;"><?php echo format_rupiah($data['total_revenue']); ?></td>
                                            <td class="text-end" style="white-space: nowrap;"><?php echo format_rupiah($data['cash_revenue']); ?></td>
                                            <td class="text-end" style="white-space: nowrap;"><?php echo format_rupiah($data['transfer_revenue']); ?></td>
                                            <td class="text-end text-danger" style="white-space: nowrap;"><?php echo format_rupiah($data['cod_revenue']); ?></td>
                                            <td class="d-flex justify-content-center" style="white-space: nowrap;">
                                                <div>
                                                    <a href="export/export.php?<?php echo $export_params; ?>" 
                                                    class="btn btn-sm btn-outline-success">
                                                    <i class="fa-solid fa-file-export me-1"></i> 
                                                    </a>
                                                </div>
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
                                        <th class="text-center">sampai Tujuan</th>
                                        <th class="text-center">POD</th>
                                        <th class="text-center">Dibatalkan</th>
                                        <th class="text-center">Export Pengambilan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $no = 1;
                                    foreach ($all_branches as $branch_name) {
                                    $data = $shipment_data[$branch_name] ?? [
                                        'total_shipments' => 0, 'count_proses' => 0,
                                        'count_pengiriman' => 0, 'cont_sampai_tujuan' => 0,
                                        'count_selesai' => 0, 'count_dibatalkan' => 0,
                                    ];
                                    ?>
                                        <tr>
                                            <td class="px-3"><?php echo $no++; ?></td>
                                            <td class="fw-bold"><?php echo htmlspecialchars($branch_name); ?></td>
                                            <td class="fw-bold text-center"><?php echo $data['total_shipments']; ?></td>
                                            <td class="text-center"><?php echo $data['count_proses']; ?></td>
                                            <td class="text-center"><?php echo $data['count_pengiriman']; ?></td>
                                            <td class="text-center"><?php echo $data['count_sampai_tujuan']; ?></td>
                                            <td class="text-center"><?php echo $data['count_selesai']; ?></td>
                                            <td class="text-center"><?php echo $data['count_dibatalkan']; ?></td>
                                            <td class="d-flex justify-content-center" style="white-space: nowrap;">
                                                <div>
                                                    <?php
                                                    // Build export URL with current dashboard filter
                                                    $export_pengambilan_params = 'cabang=' . urlencode($branch_name);
                                                    $export_pengambilan_params .= '&filter_type=' . urlencode($filter_type);
                                                    if (!empty($filter_value)) {
                                                        $export_pengambilan_params .= '&filter_value=' . urlencode($filter_value);
                                                    }
                                                    ?>
                                                    <a href="export/export_pengambilan.php?<?php echo $export_pengambilan_params; ?>" 
                                                       class="btn btn-sm btn-outline-success">
                                                        <i class="fa-solid fa-file-excel"></i>
                                                    </a>
                                                </div>
                                            </td>
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
                                        <th class="text-center">Diberangkatkan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $no = 1;
                                    foreach ($all_branches as $branch_name) {
                                    $data = $manifest_data[$branch_name] ?? [
                                    'total_manifests' => 0, 'count_draft' => 0,
                                    'count_diberangkatkan' => 0,
                                    ];
                                    ?>
                                        <tr>
                                            <td class="px-3"><?php echo $no++; ?></td>
                                            <td class="fw-bold"><?php echo htmlspecialchars($branch_name); ?></td>
                                            <td class="fw-bold text-center"><?php echo $data['total_manifests']; ?></td>
                                            <td class="text-center"><?php echo $data['count_draft']; ?></td>
                                            <td class="text-center"><?php echo $data['count_diberangkatkan']; ?></td>
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



<!-- Smart Filter Script -->
<script>
// Smart Filter - Dynamic show/hide input based on filter type
const filterTypeSelect = document.getElementById('filter_type');
const filterValueContainer = document.getElementById('filter_value_container');
const filterValueInput = document.getElementById('filter_value');
const filterValueLabel = document.getElementById('filter_value_label');
const resetFilterBtn = document.getElementById('resetFilterBtn');

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
</script>

<!-- Lacak Paket Script -->
<script>
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
