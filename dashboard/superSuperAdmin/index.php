<?php
    session_start();
    // 1. Otorisasi dan Otentikasi
    if(!isset($_SESSION['username'])){
        header("Location: ../../auth/login");
        exit;
    }

    // Pastikan hanya role superSuperAdmin yang bisa mengakses
    if(isset($_SESSION['role']) && $_SESSION['role'] !== 'superSuperAdmin'){
        header("Location: ../../?error=unauthorized");
        exit;
    }

    include '../../config/database.php';

    // =======================================================
    // FILTERING LOGIC (Simple Toggle: Today vs Month)
    // =======================================================
    $filter = isset($_GET['filter']) && in_array($_GET['filter'], ['today', 'month']) 
              ? htmlspecialchars($_GET['filter']) 
              : 'month'; // Default: month (Bulan Ini)

    $selected_date_display = '';

    // Tentukan klausa kondisi tanggal untuk injeksi langsung ke SQL (tanpa prepared statement untuk tanggal)
    if ($filter === 'today') {
        // Data hari ini
        $date_condition = "DATE(p.tanggal) = CURDATE()";
        $date_condition_sj = "DATE(sj.tanggal) = CURDATE()";
        $selected_date_display = date('d F Y');
    } else { // monthly (default: current month)
        // Data bulan ini
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
    function get_count_stat($conn, $table, $condition = "") {
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
            $row = $result->fetch_assoc();
            $count = isset($row['total']) ? (int)$row['total'] : 0;
        }
        
        $stmt->close();
        return $count;
    }

    /**
     * Helper function untuk mendapatkan total SUM dari sebuah kolom untuk data ALL-TIME.
     */
    function get_total_revenue_all_time($conn) {
        $sql = "SELECT SUM(total_tarif) AS total_revenue FROM pengiriman";
        
        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            error_log("Database Error: Failed to prepare statement for all-time revenue.");
            return 0; 
        }

        $stmt->execute();
        $result = $stmt->get_result();
        $revenue = 0;
        
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $revenue = isset($row['total_revenue']) ? (float)$row['total_revenue'] : 0;
        }
        
        $stmt->close();
        return $revenue;
    }

    /**
     * Helper function untuk format mata uang Rupiah
     */
    function format_rupiah($number) {
        $number = $number ?? 0;
        return 'Rp ' . number_format((float)$number, 0, ',', '.');
    }

    // =======================================================
    // DATA FETCHING FUNCTIONS (Filtered Data)
    // Menggunakan Conditional Aggregation untuk memastikan semua cabang tampil
    // =======================================================

    /**
     * Mengambil data pendapatan per cabang berdasarkan filter tanggal.
     * Mengembalikan array indexed by nama_cabang dengan fields:
     * total_revenue, cash_revenue, transfer_revenue, bayar_ditempat_revenue, dibatalkan_revenue
     */
    function get_branch_revenue_data($conn, $date_condition) {
        $data = [];
        // Kondisi tanggal diterapkan di dalam SUM/CASE untuk menjaga LEFT JOIN
        $sql = "
            SELECT
                kc.nama_cabang,
                SUM(CASE WHEN p.id IS NOT NULL AND $date_condition THEN p.total_tarif ELSE 0 END) AS total_revenue,
                SUM(CASE WHEN p.pembayaran = 'cash' AND p.id IS NOT NULL AND $date_condition THEN p.total_tarif ELSE 0 END) AS cash_revenue,
                SUM(CASE WHEN p.pembayaran = 'transfer' AND p.id IS NOT NULL AND $date_condition THEN p.total_tarif ELSE 0 END) AS transfer_revenue,
                SUM(CASE WHEN p.pembayaran = 'bayar di tempat' AND p.id IS NOT NULL AND $date_condition THEN p.total_tarif ELSE 0 END) AS bayar_ditempat_revenue,
                SUM(CASE WHEN LOWER(COALESCE(p.status,'')) = 'dibatalkan' AND p.id IS NOT NULL AND $date_condition THEN p.total_tarif ELSE 0 END) AS dibatalkan_revenue
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
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $data[$row['nama_cabang']] = [
                'nama_cabang' => $row['nama_cabang'],
                'total_revenue' => (float)($row['total_revenue'] ?? 0),
                'cash_revenue' => (float)($row['cash_revenue'] ?? 0),
                'transfer_revenue' => (float)($row['transfer_revenue'] ?? 0),
                'bayar_ditempat_revenue' => (float)($row['bayar_ditempat_revenue'] ?? 0),
                'dibatalkan_revenue' => (float)($row['dibatalkan_revenue'] ?? 0)
            ];
        }
        $stmt->close();
        return $data;
    }

    /**
     * Mengambil data jumlah pengiriman per cabang berdasarkan filter tanggal.
     */
    function get_branch_shipment_data($conn, $date_condition) {
        $data = [];
        // Kondisi tanggal diterapkan di dalam SUM/CASE untuk menjaga LEFT JOIN
        $sql = "
            SELECT
                kc.nama_cabang,
                SUM(CASE WHEN p.id IS NOT NULL AND $date_condition THEN 1 ELSE 0 END) AS total_shipments,
                SUM(CASE WHEN LOWER(COALESCE(p.status,'')) = 'bkd' AND p.id IS NOT NULL AND $date_condition THEN 1 ELSE 0 END) AS count_proses,
                SUM(CASE WHEN LOWER(COALESCE(p.status,'')) = 'dalam pengiriman' AND p.id IS NOT NULL AND $date_condition THEN 1 ELSE 0 END) AS count_pengiriman,
                SUM(CASE WHEN (LOWER(COALESCE(p.status,'')) = 'sampai tujuan' OR LOWER(COALESCE(p.status,'')) = 'pod') AND p.id IS NOT NULL AND $date_condition THEN 1 ELSE 0 END) AS count_selesai,
                SUM(CASE WHEN LOWER(COALESCE(p.status,'')) = 'dibatalkan' AND p.id IS NOT NULL AND $date_condition THEN 1 ELSE 0 END) AS count_dibatalkan
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
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $data[$row['nama_cabang']] = [
                'nama_cabang' => $row['nama_cabang'],
                'total_shipments' => (int)($row['total_shipments'] ?? 0),
                'count_proses' => (int)($row['count_proses'] ?? 0),
                'count_pengiriman' => (int)($row['count_pengiriman'] ?? 0),
                'count_selesai' => (int)($row['count_selesai'] ?? 0),
                'count_dibatalkan' => (int)($row['count_dibatalkan'] ?? 0)
            ];
        }
        $stmt->close();
        return $data;
    }

    /**
     * Mengambil data surat jalan per cabang berdasarkan filter tanggal.
     * Memastikan status 'Dibatalkan' juga terhitung, serta toleran terhadap variasi case/spelling.
     */
    function get_branch_manifest_data($conn, $date_condition_sj) {
        $data = [];

        $sql = "
            SELECT
                kc.nama_cabang,
                SUM(CASE WHEN sj.id IS NOT NULL AND $date_condition_sj THEN 1 ELSE 0 END) AS total_manifests,
                SUM(CASE WHEN LOWER(COALESCE(sj.status,'')) = 'draft' AND sj.id IS NOT NULL AND $date_condition_sj THEN 1 ELSE 0 END) AS count_draft,
                SUM(CASE WHEN LOWER(COALESCE(sj.status,'')) = 'dalam perjalanan' AND sj.id IS NOT NULL AND $date_condition_sj THEN 1 ELSE 0 END) AS count_dalam_perjalanan,
                SUM(CASE WHEN LOWER(COALESCE(sj.status,'')) = 'sampai tujuan' AND sj.id IS NOT NULL AND $date_condition_sj THEN 1 ELSE 0 END) AS count_sampai_tujuan,
                SUM(CASE WHEN LOWER(COALESCE(sj.status,'')) IN ('dibatalkan','batal','cancel') AND sj.id IS NOT NULL AND $date_condition_sj THEN 1 ELSE 0 END) AS count_dibatalkan
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
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $data[$row['nama_cabang']] = [
                'nama_cabang' => $row['nama_cabang'],
                'total_manifests' => (int)($row['total_manifests'] ?? 0),
                'count_dibuat' => (int)($row['count_draft'] ?? 0),
                'count_berangkat' => (int)($row['count_dalam_perjalanan'] ?? 0),
                'count_sampai_tujuan' => (int)($row['count_sampai_tujuan'] ?? 0),
                'count_dibatalkan' => (int)($row['count_dibatalkan'] ?? 0)
            ];
        }
        $stmt->close();
        return $data;
    }


    // =======================================================
    // EXECUTION & DATA AGGREGATION
    // =======================================================

    // 1. Fetch Core Statistics (non-shipment, All Time)
    $total_cabang     = get_count_stat($conn, "Kantor_cabang");
    $total_user       = get_count_stat($conn, "User");
    $total_tarif      = get_count_stat($conn, "Tarif_pengiriman", "status = 'aktif'");
    $total_pengiriman_all_time = get_count_stat($conn, "pengiriman"); // Total Pengiriman All Time
    $total_pendapatan_all_time = get_total_revenue_all_time($conn); // Total Pendapatan All Time
    
    // 2. Fetch Detailed Branch Data (Filtered by date) - Menggunakan $date_condition
    $revenue_data = get_branch_revenue_data($conn, $date_condition);
    $shipment_data = get_branch_shipment_data($conn, $date_condition);
    $manifest_data = get_branch_manifest_data($conn, $date_condition_sj);

    // Build laporan_cabang (dipakai di tabel Laporan Pendapatan per Cabang)
    $laporan_cabang = [];
    if (!empty($revenue_data)) {
        foreach ($revenue_data as $rc) {
            $laporan_cabang[] = [
                'nama_cabang' => $rc['nama_cabang'],
                'total_pendapatan' => $rc['total_revenue'],
                'cash' => $rc['cash_revenue'],
                'transfer' => $rc['transfer_revenue'],
                'bayar_ditempat' => $rc['bayar_ditempat_revenue'],
                'dibatalkan' => $rc['dibatalkan_revenue']
            ];
        }
    }

    // 3. Aggregate Filtered Counts for Top Cards
    $total_pengiriman_filtered = 0;
    $dalam_proses_filtered     = 0; 
    $dalam_pengiriman_filtered = 0;
    $selesai_filtered          = 0;
    $total_revenue_filtered    = 0; 

    foreach ($shipment_data as $data) {
        $total_pengiriman_filtered += $data['total_shipments'];
        $dalam_proses_filtered     += $data['count_proses'];
        $dalam_pengiriman_filtered += $data['count_pengiriman'];
        $selesai_filtered          += $data['count_selesai'];
    }
    
    // Calculate Total Revenue from revenue_data
    foreach ($revenue_data as $data) {
        $total_revenue_filtered += $data['total_revenue'];
    }
    
    // Calculate Total Manifests
    $total_surat_jalan = 0;
    foreach($manifest_data as $data) {
        $total_surat_jalan += $data['total_manifests'];
    }

    // Set variabel kartu statistik teratas ke nilai yang sudah difilter
    $total_pengiriman = $total_pengiriman_filtered;
    $dalam_proses     = $dalam_proses_filtered; 
    $dalam_pengiriman = $dalam_pengiriman_filtered;
    $selesai          = $selesai_filtered; 
    $total_pendapatan = $total_revenue_filtered; 


    // 4. Get recent shipments (Filtered by date)
    $where_clause_recent = ($filter === 'today') 
                       ? "WHERE DATE(tanggal) = CURDATE()" 
                       : "WHERE DATE_FORMAT(tanggal, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')";

    $stmt = $conn->prepare("SELECT * FROM Pengiriman $where_clause_recent ORDER BY id DESC LIMIT 5");
    $stmt->execute();
    $recent_shipments = $stmt->get_result();
    $stmt->close();


    // Mengambil daftar semua cabang untuk perulangan tabel
    $stmt = $conn->prepare("SELECT nama_cabang FROM Kantor_cabang ORDER BY nama_cabang ASC");
    $stmt->execute();
    $all_branches_result = $stmt->get_result();
    $all_branches = [];
    while($row = $all_branches_result->fetch_assoc()) {
        $all_branches[] = $row['nama_cabang'];
    }
    $stmt->close();
?>

<?php
    $title = "Dashboard SuperSuperAdmin - Cendana Kargo"; 
    $page = "dashboard";
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
                            Selamat datang, <?= htmlspecialchars($_SESSION['username']); ?>! Kelola semua data sistem Cendana Kargo
                        </p>

                        <!-- Data Agregat Info Box (Tanpa Icon, Warna & Font Serasi) -->
                        <div class="px-3 py-2 rounded-3 d-inline-block" 
                            style="background-color: #d9f6fa; border: 1px solid #bde9ee;">
                            <span class="fw-normal text-secondary" style="font-size: 0.9rem;">
                                Data Agregat untuk periode: 
                                <strong class="text-dark"><?= $selected_date_display; ?></strong>
                            </span>
                        </div>
                    </div>

                    <!-- Filter Tombol -->
                    <div class="btn-group" role="group" aria-label="Filter data">
                        <span class="badge text-bg-secondary me-2 align-self-center">Periode Data:</span>
                        <a href="?filter=month" 
                        class="btn btn-sm <?= $filter === 'month' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                        Bulan Ini
                        </a>
                        <a href="?filter=today" 
                        class="btn btn-sm <?= $filter === 'today' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                        Hari Ini
                        </a>
                    </div>
                </div>

                <?php if(isset($_GET['already_logined'])){ ?>
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
                                <p class="text-success mb-1 small fw-bold">RINGKASAN PENDAPATAN</p>
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h4 class="mb-0 fw-bold text-success"><?= format_rupiah($total_pendapatan); ?></h4>
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
                                <p class="text-primary mb-1 small fw-bold">RINGKASAN PENGIRIMAN</p>
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h4 class="mb-0 fw-bold text-primary"><?= $total_pengiriman; ?> Kiriman</h4>
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
                                        <h4 class="mb-0 fw-bold text-secondary"><?= $total_surat_jalan; ?></h4>
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
                                    <h2 class="mb-0 fw-bold"><?= $total_cabang; ?></h2>
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
                                    <h2 class="mb-0 fw-bold"><?= $total_user; ?></h2>
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
                                    <h2 class="mb-0 fw-bold"><?= $total_tarif; ?></h2>
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

                <!-- ======================================================= -->
                <!-- AGGREGATE REPORTS PER BRANCH (Filtered) -->
                <!-- ======================================================= -->

                <!-- 1. Revenue Report per Branch -->
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h5 class="fw-bold">Laporan Pendapatan per Cabang 
                        </h5>

                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>No.</th>
                                        <th>Nama Cabang</th>
                                        <th>Total Pendapatan</th>
                                        <th>Cash</th>
                                        <th>Transfer</th>
                                        <th>Bayar di Tempat</th>
                                        <th>Dibatalkan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($laporan_cabang)) { 
                                        $no = 1;
                                        foreach ($laporan_cabang as $row) { ?>
                                            <tr>
                                                <td><?= $no++; ?></td>
                                                <td><?= htmlspecialchars($row['nama_cabang']); ?></td>
                                                <td><?= format_rupiah($row['total_pendapatan']); ?></td>
                                                <td><?= format_rupiah($row['cash']); ?></td>
                                                <td><?= format_rupiah($row['transfer']); ?></td>
                                                <td><?= format_rupiah($row['bayar_ditempat']); ?></td>
                                                <td><?= format_rupiah($row['dibatalkan']); ?></td>
                                            </tr>
                                        <?php } 
                                    } else { ?>
                                        <tr>
                                            <td colspan="7" class="text-center text-muted">Tidak ada data</td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- 2. Shipment Count Report per Branch -->
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h5 class="fw-bold">Laporan Jumlah Pengiriman per Cabang</h5>

                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>No.</th>
                                        <th>Nama Cabang</th>
                                        <th>BKD</th>
                                        <th>Dalam Perjalanan</th>
                                        <th>Sampai Tujuan</th>
                                        <th>Total Pengiriman</th>
                                        <th>POD</th>
                                        <th>Dibatalkan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($all_branches)) { 
                                        $no = 1;
                                        foreach ($all_branches as $branch_name): 
                                            $data = $shipment_data[$branch_name] ?? [
                                                'total_shipments' => 0, 
                                                'count_proses' => 0, 
                                                'count_pengiriman' => 0, 
                                                'count_selesai' => 0, 
                                                'count_dibatalkan' => 0
                                            ];
                                    ?>
                                        <tr>
                                            <td><?= $no++; ?></td>
                                            <td><?= htmlspecialchars($branch_name); ?></td>
                                            <td class="fw-bold text-primary"><?= $data['total_shipments']; ?></td>
                                            <td class="text-warning"><?= $data['count_proses']; ?></td>
                                            <td class="text-info"><?= $data['count_pengiriman']; ?></td>
                                            <td class="text-success"><?= $data['count_selesai']; ?></td>
                                            <td class="text-danger"><?= $data['count_dibatalkan']; ?></td>
                                        </tr>
                                    <?php 
                                        endforeach; 
                                    } else { ?>
                                        <tr>
                                            <td colspan="7" class="text-center text-muted bg-light py-3">Tidak ada data</td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- 3. Manifest/Surat Jalan Report per Branch -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body">
                        <h5 class="fw-bold">Laporan Surat Jalan per Cabang</h5>
                        <p class="small text-muted mb-3">Status Surat Jalan: Draft, Dalam Perjalanan, Sampai Tujuan.</p>

                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>No.</th>
                                        <th>Nama Cabang</th>
                                        <th>Total Surat Jalan</th>
                                        <th>Draft</th>
                                        <th>Dalam Perjalanan</th>
                                        <th>Sampai Tujuan</th>
                                        <th>Dibatalkan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    if (!empty($all_branches)) { 
                                        $no = 1; 
                                        foreach ($all_branches as $branch_name): 
                                            $data = $manifest_data[$branch_name] ?? [
                                                'total_manifests' => 0, 
                                                'count_dibuat' => 0, 
                                                'count_berangkat' => 0, 
                                                'count_sampai_tujuan' => 0
                                            ];
                                    ?>
                                        <tr>
                                            <td><?= $no++; ?></td>
                                            <td><?= htmlspecialchars($branch_name); ?></td>
                                            <td class="fw-bold text-primary"><?= $data['total_manifests']; ?></td>
                                            <td class="text-secondary"><?= $data['count_dibuat']; ?></td>
                                            <td class="text-info"><?= $data['count_berangkat']; ?></td>
                                            <td class="text-success"><?= $data['count_sampai_tujuan']; ?></td>
                                        </tr>
                                    <?php 
                                        endforeach; 
                                    } else { ?>
                                        <tr>
                                            <td colspan="6" class="text-center text-muted bg-light py-3">Tidak ada data</td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>


                <!-- Recent Shipments (Filtered by date) -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="fw-bold mb-0">Pengiriman Terbaru</h5>
                            <a href="pengiriman/" class="btn btn-sm btn-outline-primary">
                                Lihat Semua
                            </a>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>No. Resi</th>
                                        <th>Nama Barang</th>
                                        <th>Pengirim</th>
                                        <th>Penerima</th>
                                        <th>Rute</th>
                                        <th>Tanggal</th>
                                        <th>Status</th>
                                        <th class="text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if($recent_shipments->num_rows > 0): ?>
                                        <?php while($row = $recent_shipments->fetch_assoc()): ?>
                                            <tr class="text-capitalize">
                                                <td class="fw-bold">
                                                    <span class="badge bg-dark bg-opacity-75">
                                                        <?= htmlspecialchars($row['no_resi']); ?>
                                                    </span>
                                                </td>
                                                <td><?= htmlspecialchars($row['nama_barang']); ?></td>
                                                <td><?= htmlspecialchars($row['nama_pengirim']); ?></td>
                                                <td><?= htmlspecialchars($row['nama_penerima']); ?></td>
                                                <td>
                                                    <span class="badge bg-primary bg-opacity-75"><?= htmlspecialchars($row['cabang_pengirim']); ?></span>
                                                    →
                                                    <span class="badge bg-success bg-opacity-75"><?= htmlspecialchars($row['cabang_penerima']); ?></span>
                                                </td>
                                                <td><?= date('d/m/Y', strtotime($row['tanggal'])); ?></td>
                                                <td>
                                                    <?php
                                                    $badgeClass = match(strtolower($row['status'])) {
                                                        'bkd' => 'warning',
                                                        'dalam pengiriman' => 'primary',
                                                        'sampai tujuan' => 'info',
                                                        'pod' => 'success',
                                                        'dibatalkan' => 'danger',
                                                        default => 'secondary'
                                                    };
                                                    ?>
                                                    <span class="badge text-bg-<?= $badgeClass; ?> bg-opacity-75">
                                                        <?= htmlspecialchars($row['status']); ?>
                                                    </span>
                                                </td>
                                                <td class="text-center">
                                                    <a href="pengiriman/detail.php?id=<?= $row['id']; ?>" 
                                                    class="btn btn-sm btn-info text-white">
                                                        <i class="fa-solid fa-eye"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="8" class="text-center text-muted bg-light py-3">
                                                <i class="fa-solid fa-box mb-1"></i><br>
                                                Tidak ada data pengiriman
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

<?php
    include '../../templates/footer.php';
?>
