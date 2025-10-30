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
            $count = $result->fetch_assoc()['total'];
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
            $revenue = $result->fetch_assoc()['total_tarif'] ?? 0;
        }
        
        $stmt->close();
        return $revenue;
    }

    /**
     * Helper function untuk format mata uang Rupiah
     */
    function format_rupiah($number) {
        return 'Rp ' . number_format($number, 0, ',', '.');
    }

    // =======================================================
    // DATA FETCHING FUNCTIONS (Filtered Data)
    // Menggunakan Conditional Aggregation untuk memastikan semua cabang tampil
    // =======================================================

    /**
     * Mengambil data pendapatan per cabang berdasarkan filter tanggal.
     */
    function get_branch_revenue_data($conn, $date_condition) { // Hapus $date_param
        $data = [];
        // Kondisi tanggal diterapkan di dalam SUM/CASE untuk menjaga LEFT JOIN
        $sql = "
            SELECT
                kc.nama_cabang,
                SUM(CASE WHEN p.id IS NOT NULL AND $date_condition THEN p.total_tarif ELSE 0 END) AS total_revenue,
                SUM(CASE WHEN p.pembayaran IN ('cash', 'bayar di tempat') AND p.id IS NOT NULL AND $date_condition THEN p.total_tarif ELSE 0 END) AS tunai_revenue,
                SUM(CASE WHEN p.pembayaran = 'transfer' AND p.id IS NOT NULL AND $date_condition THEN p.total_tarif ELSE 0 END) AS transfer_revenue
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
            $data[$row['nama_cabang']] = $row;
        }
        $stmt->close();
        return $data;
    }

    /**
     * Mengambil data jumlah pengiriman per cabang berdasarkan filter tanggal.
     */
    function get_branch_shipment_data($conn, $date_condition) { // Hapus $date_param
        $data = [];
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
    function get_branch_manifest_data($conn, $date_condition_sj) { // Hapus $date_param
        $data = [];
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
    $total_cabang     = get_count_stat($conn, "Kantor_cabang");
    $total_user       = get_count_stat($conn, "User");
    $total_tarif      = get_count_stat($conn, "Tarif_pengiriman", "status = 'aktif'");
    $total_pengiriman_all_time = get_count_stat($conn, "pengiriman"); // Total Pengiriman All Time
    $total_pendapatan_all_time = get_total_revenue_all_time($conn); // Total Pendapatan All Time
    
    // 2. Fetch Detailed Branch Data (Filtered by date) - Menggunakan $date_condition
    $revenue_data = get_branch_revenue_data($conn, $date_condition);
    $shipment_data = get_branch_shipment_data($conn, $date_condition);
    $manifest_data = get_branch_manifest_data($conn, $date_condition_sj);

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
                                Data untuk periode: 
                                <strong class="text-dark"><?= $selected_date_display; ?></strong>
                            </span>
                        </div>
                    </div>

                    <!-- Filter Tombol -->
                     <div>
                         <span class="badge text-bg-secondary me-2 align-self-center">Periode Data:</span>
                         <div class="btn-group" role="group" aria-label="Filter data">
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
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-0 py-3">
                        <h5 class="mb-0 fw-bold">Laporan Pendapatan per Cabang </h5>
                        <p class="small text-muted mb-0">Total Pendapatan: <?= format_rupiah($total_pendapatan); ?></p>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover align-middle mb-0 small">
                                <thead class="table-light">
                                    <tr>
                                        <th class="px-3">No.</th>
                                        <th>Nama Cabang</th>
                                        <th>Total Pendapatan</th>
                                        <th class="text-center">Tunai (Cash/COD)</th>
                                        <th class="text-center">Transfer</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $no = 1; 
                                    foreach ($all_branches as $branch_name): 
                                        $data = $revenue_data[$branch_name] ?? ['total_revenue' => 0, 'tunai_revenue' => 0, 'transfer_revenue' => 0];
                                    ?>
                                        <tr>
                                            <td class="px-3"><?= $no++; ?></td>
                                            <td class="fw-bold"><?= htmlspecialchars($branch_name); ?></td>
                                            <td class="fw-bold text-success"><?= format_rupiah($data['total_revenue']); ?></td>
                                            <td class="text-center text-primary"><?= format_rupiah($data['tunai_revenue']); ?></td>
                                            <td class="text-center text-info"><?= format_rupiah($data['transfer_revenue']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
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
                                    foreach ($all_branches as $branch_name): 
                                        $data = $shipment_data[$branch_name] ?? [
                                            'total_shipments' => 0, 'count_proses' => 0, 
                                            'count_pengiriman' => 0, 'count_selesai' => 0, 
                                            'count_dibatalkan' => 0
                                        ];
                                    ?>
                                        <tr>
                                            <td class="px-3"><?= $no++; ?></td>
                                            <td class="fw-bold"><?= htmlspecialchars($branch_name); ?></td>
                                            <td class="fw-bold text-primary text-center"><?= $data['total_shipments']; ?></td>
                                            <td class="text-center text-warning"><?= $data['count_proses']; ?></td>
                                            <td class="text-center text-info"><?= $data['count_pengiriman']; ?></td>
                                            <td class="text-center text-success"><?= $data['count_selesai']; ?></td>
                                            <td class="text-center text-danger"><?= $data['count_dibatalkan']; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- 3. Manifest/Surat Jalan Report per Branch -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-0 py-3">
                        <h5 class="mb-0 fw-bold">Laporan Surat Jalan  per Cabang </h5>
                        <p class="small text-muted mb-0">Status Surat Jalan: Draft, Dalam Perjalanan, Sampai Tujuan.</p>
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
                                    foreach ($all_branches as $branch_name): 
                                        $data = $manifest_data[$branch_name] ?? [
                                            'total_manifests' => 0, 'count_dibuat' => 0, 
                                            'count_berangkat' => 0, 'count_sampai_tujuan' => 0,
                                            'count_dibatalkan' => 0
                                        ];
                                    ?>
                                        <tr>
                                            <td class="px-3"><?= $no++; ?></td>
                                            <td class="fw-bold"><?= htmlspecialchars($branch_name); ?></td>
                                            <td class="fw-bold text-primary text-center"><?= $data['total_manifests']; ?></td>
                                            <td class="text-center text-secondary"><?= $data['count_dibuat']; ?></td>
                                            <td class="text-center text-info"><?= $data['count_berangkat']; ?></td>
                                            <td class="text-center text-success"><?= $data['count_sampai_tujuan']; ?></td>
                                            <td class="text-center text-danger"><?= $data['count_dibatalkan']; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
    include '../../templates/footer.php';
?>
