<?php
  session_start();
  if(!isset($_SESSION['username'] )|| !isset($_SESSION['user_id'])){
      header("Location: ../../auth/login");
      exit;
  }

  if(isset($_SESSION['role']) && $_SESSION['role'] !== 'superAdmin'){
      header("Location: ../../?error=unauthorized");
      exit;
  }

  include '../../config/database.php';

  $current_month = date('m');
  $current_year = date('Y');
  $current_date = date('Y-m-d');

  $filter = isset($_GET['filter']) ? $_GET['filter'] : 'bulan';
  $where_clause = ($filter === 'hari')
      ? "tanggal >= CURDATE() AND tanggal < CURDATE() + INTERVAL 1 DAY"
      : "MONTH(tanggal) = '$current_month' AND YEAR(tanggal) = '$current_year'";


  // === variabel untuk ditampilkan di kotak info ===
  $selected_date_display = ($filter === 'hari') ? " (" . date('d F Y') . ")" : " " . date('F Y');

  // Ambil cabang user yang login
  $cabang_superadmin = $_SESSION['cabang'];

  // === TOTAL DATA (hanya untuk cabang user) ===
  $total_pengiriman = $conn->query("SELECT COUNT(*) AS total FROM pengiriman WHERE $where_clause AND cabang_pengirim = '$cabang_superadmin'")->fetch_assoc()['total'] ?? 0;
  $total_surat_jalan = $conn->query("SELECT COUNT(*) AS total FROM surat_jalan s JOIN kantor_cabang kc ON s.id_cabang_pengirim = kc.id WHERE $where_clause AND kc.nama_cabang = '$cabang_superadmin'")->fetch_assoc()['total'] ?? 0;
  $total_pendapatan = $conn->query("SELECT SUM(total_tarif) AS total FROM pengiriman WHERE $where_clause AND cabang_pengirim = '$cabang_superadmin'AND status != 'dibatalkan'")->fetch_assoc()['total'] ?? 0;


  // Helper function untuk format rupiah
  function format_rupiah($number) {
      return 'Rp ' . number_format($number, 0, ',', '.');
  }

  // Ambil semua user di cabang ini
  $stmt_users = $conn->prepare("SELECT id, username FROM User WHERE id_cabang = (SELECT id FROM Kantor_cabang WHERE nama_cabang = ?) AND role != 'superSuperAdmin' ORDER BY username ASC");
  $stmt_users->bind_param('s', $cabang_superadmin);
  $stmt_users->execute();
  $all_users_result = $stmt_users->get_result();
  $all_users = [];
  while($row = $all_users_result->fetch_assoc()) {
      $all_users[] = $row;
  }
  $stmt_users->close();

  // === AMBIL DATA SUPERSUPERADMIN UNTUK CABANG INI ===
  $supersuperadmin_data = [
      'id' => null,
      'username' => 'SuperSuperAdmin',
      'pendapatan' => ['cash' => 0, 'transfer' => 0, 'cod' => 0, 'total' => 0],
      'pengiriman' => ['bkd' => 0, 'perjalanan' => 0, 'sampai' => 0, 'pod' => 0, 'batal' => 0, 'total' => 0],
      'surat_jalan' => ['draft' => 0, 'perjalanan' => 0, 'sampai' => 0, 'batal' => 0, 'total' => 0]
  ];

  // Query untuk mengambil data pengiriman & pendapatan dari superSuperAdmin
  $sql_supersuperadmin = "
      SELECT 
          SUM(CASE WHEN pembayaran = 'cash' AND $where_clause AND status != 'dibatalkan' THEN total_tarif ELSE 0 END) AS cash,
          SUM(CASE WHEN pembayaran = 'transfer' AND $where_clause AND status != 'dibatalkan' THEN total_tarif ELSE 0 END) AS transfer,
          SUM(CASE WHEN pembayaran = 'bayar di tempat' AND $where_clause AND status != 'dibatalkan' THEN total_tarif ELSE 0 END) AS cod,
          SUM(CASE WHEN $where_clause AND status != 'dibatalkan' THEN total_tarif ELSE 0 END) AS total,
          SUM(CASE WHEN status = 'bkd' AND $where_clause THEN 1 ELSE 0 END) AS bkd,
          SUM(CASE WHEN status = 'dalam pengiriman' AND $where_clause THEN 1 ELSE 0 END) AS perjalanan,
          SUM(CASE WHEN status = 'sampai tujuan' AND $where_clause THEN 1 ELSE 0 END) AS sampai,
          SUM(CASE WHEN status = 'pod' AND $where_clause THEN 1 ELSE 0 END) AS pod,
          SUM(CASE WHEN status = 'dibatalkan' AND $where_clause THEN 1 ELSE 0 END) AS batal,
          COUNT(CASE WHEN $where_clause THEN 1 END) AS total_pengiriman
      FROM pengiriman p
      JOIN User u ON p.id_user = u.id
      WHERE u.role = 'superSuperAdmin' 
        AND p.cabang_pengirim = ?
  ";
  $stmt_supersuperadmin = $conn->prepare($sql_supersuperadmin);
  $stmt_supersuperadmin->bind_param('s', $cabang_superadmin);
  $stmt_supersuperadmin->execute();
  $result_supersuperadmin = $stmt_supersuperadmin->get_result();
  if ($row = $result_supersuperadmin->fetch_assoc()) {
      $supersuperadmin_data['pendapatan']['cash'] = $row['cash'] ?? 0;
      $supersuperadmin_data['pendapatan']['transfer'] = $row['transfer'] ?? 0;
      $supersuperadmin_data['pendapatan']['cod'] = $row['cod'] ?? 0;
      $supersuperadmin_data['pendapatan']['total'] = $row['total'] ?? 0;
      $supersuperadmin_data['pengiriman']['bkd'] = $row['bkd'] ?? 0;
      $supersuperadmin_data['pengiriman']['perjalanan'] = $row['perjalanan'] ?? 0;
      $supersuperadmin_data['pengiriman']['sampai'] = $row['sampai'] ?? 0;
      $supersuperadmin_data['pengiriman']['pod'] = $row['pod'] ?? 0;
      $supersuperadmin_data['pengiriman']['batal'] = $row['batal'] ?? 0;
      $supersuperadmin_data['pengiriman']['total'] = $row['total_pengiriman'] ?? 0;
  }
  $stmt_supersuperadmin->close();

  // Query untuk surat jalan dari superSuperAdmin
  $sql_sj_supersuperadmin = "
      SELECT 
          SUM(CASE WHEN s.status = 'draft' AND $where_clause THEN 1 ELSE 0 END) AS draft,
          SUM(CASE WHEN s.status = 'dalam perjalanan' AND $where_clause THEN 1 ELSE 0 END) AS perjalanan,
          SUM(CASE WHEN s.status = 'sampai tujuan' AND $where_clause THEN 1 ELSE 0 END) AS sampai,
          SUM(CASE WHEN s.status = 'dibatalkan' AND $where_clause THEN 1 ELSE 0 END) AS batal,
          COUNT(CASE WHEN $where_clause THEN 1 END) AS total
      FROM surat_jalan s
      JOIN User u ON s.id_user = u.id
      JOIN kantor_cabang kc ON s.id_cabang_pengirim = kc.id
      WHERE u.role = 'superSuperAdmin' 
        AND kc.nama_cabang = ?
  ";
  $stmt_sj_supersuperadmin = $conn->prepare($sql_sj_supersuperadmin);
  $stmt_sj_supersuperadmin->bind_param('s', $cabang_superadmin);
  $stmt_sj_supersuperadmin->execute();
  $result_sj_supersuperadmin = $stmt_sj_supersuperadmin->get_result();
  if ($row = $result_sj_supersuperadmin->fetch_assoc()) {
      $supersuperadmin_data['surat_jalan']['draft'] = $row['draft'] ?? 0;
      $supersuperadmin_data['surat_jalan']['perjalanan'] = $row['perjalanan'] ?? 0;
      $supersuperadmin_data['surat_jalan']['sampai'] = $row['sampai'] ?? 0;
      $supersuperadmin_data['surat_jalan']['batal'] = $row['batal'] ?? 0;
      $supersuperadmin_data['surat_jalan']['total'] = $row['total'] ?? 0;
  }
  $stmt_sj_supersuperadmin->close();

  // === PENDAPATAN PER ADMIN (dengan LEFT JOIN agar semua user muncul) ===
  $pendapatan_data = [];
$sql_pendapatan = "
    SELECT u.id, u.username,
        SUM(CASE 
            WHEN p.pembayaran = 'cash' 
                 AND p.id IS NOT NULL 
                 AND $where_clause 
                 AND p.status != 'dibatalkan'
            THEN p.total_tarif ELSE 0 END) AS cash,
        SUM(CASE 
            WHEN p.pembayaran = 'transfer' 
                 AND p.id IS NOT NULL 
                 AND $where_clause 
                 AND p.status != 'dibatalkan'
            THEN p.total_tarif ELSE 0 END) AS transfer,
        SUM(CASE 
            WHEN p.pembayaran = 'bayar di tempat' 
                 AND p.id IS NOT NULL 
                 AND $where_clause 
                 AND p.status != 'dibatalkan'
            THEN p.total_tarif ELSE 0 END) AS cod,
        SUM(CASE 
            WHEN p.id IS NOT NULL 
                 AND $where_clause 
                 AND p.status != 'dibatalkan'
            THEN p.total_tarif ELSE 0 END) AS total
    FROM User u
    LEFT JOIN pengiriman p ON u.id = p.id_user
    WHERE u.id_cabang = (SELECT id FROM Kantor_cabang WHERE nama_cabang = ?) 
        AND u.role != 'superSuperAdmin'
    GROUP BY u.id, u.username
    ORDER BY u.username
";

  $stmt_pendapatan = $conn->prepare($sql_pendapatan);
  $stmt_pendapatan->bind_param('s', $cabang_superadmin);
  $stmt_pendapatan->execute();
  $result_pendapatan = $stmt_pendapatan->get_result();
  while ($row = $result_pendapatan->fetch_assoc()) {
      $pendapatan_data[$row['id']] = $row;
  }
  $stmt_pendapatan->close();

  // === PENGIRIMAN PER ADMIN (dengan LEFT JOIN agar semua user muncul) ===
  $pengiriman_data = [];
  $sql_pengiriman = "
      SELECT u.id, u.username,
          SUM(CASE WHEN p.status = 'bkd' AND p.id IS NOT NULL AND $where_clause THEN 1 ELSE 0 END) AS bkd,
          SUM(CASE WHEN p.status = 'dalam pengiriman' AND p.id IS NOT NULL AND $where_clause THEN 1 ELSE 0 END) AS perjalanan,
          SUM(CASE WHEN p.status = 'sampai tujuan' AND p.id IS NOT NULL AND $where_clause THEN 1 ELSE 0 END) AS sampai,
          SUM(CASE WHEN p.status = 'pod' AND p.id IS NOT NULL AND $where_clause THEN 1 ELSE 0 END) AS pod,
          SUM(CASE WHEN p.status = 'dibatalkan' AND p.id IS NOT NULL AND $where_clause THEN 1 ELSE 0 END) AS batal,
          SUM(CASE WHEN p.id IS NOT NULL AND $where_clause THEN 1 ELSE 0 END) AS total
      FROM User u
      LEFT JOIN pengiriman p ON u.id = p.id_user
      WHERE u.id_cabang = (SELECT id FROM Kantor_cabang WHERE nama_cabang = ?) 
          AND u.role != 'superSuperAdmin'
      GROUP BY u.id, u.username
      ORDER BY u.username
  ";
  $stmt_pengiriman = $conn->prepare($sql_pengiriman);
  $stmt_pengiriman->bind_param('s', $cabang_superadmin);
  $stmt_pengiriman->execute();
  $result_pengiriman = $stmt_pengiriman->get_result();
  while ($row = $result_pengiriman->fetch_assoc()) {
      $pengiriman_data[$row['id']] = $row;
  }
  $stmt_pengiriman->close();

  // === SURAT JALAN PER ADMIN (dengan LEFT JOIN agar semua user muncul) ===
  $surat_jalan_data = [];
  $sql_surat_jalan = "
      SELECT u.id, u.username,
          SUM(CASE WHEN s.status = 'draft' AND s.id IS NOT NULL AND $where_clause THEN 1 ELSE 0 END) AS draft,
          SUM(CASE WHEN s.status = 'dalam perjalanan' AND s.id IS NOT NULL AND $where_clause THEN 1 ELSE 0 END) AS perjalanan,
          SUM(CASE WHEN s.status = 'sampai tujuan' AND s.id IS NOT NULL AND $where_clause THEN 1 ELSE 0 END) AS sampai,
          SUM(CASE WHEN s.status = 'dibatalkan' AND s.id IS NOT NULL AND $where_clause THEN 1 ELSE 0 END) AS batal,
          SUM(CASE WHEN s.id IS NOT NULL AND $where_clause THEN 1 ELSE 0 END) AS total
      FROM User u
      LEFT JOIN surat_jalan s ON u.id = s.id_user
      WHERE u.id_cabang = (SELECT id FROM Kantor_cabang WHERE nama_cabang = ?) 
          AND u.role != 'superSuperAdmin'
      GROUP BY u.id, u.username
      ORDER BY u.username
  ";
  $stmt_surat_jalan = $conn->prepare($sql_surat_jalan);
  $stmt_surat_jalan->bind_param('s', $cabang_superadmin);
  $stmt_surat_jalan->execute();
  $result_surat_jalan = $stmt_surat_jalan->get_result();
  while ($row = $result_surat_jalan->fetch_assoc()) {
      $surat_jalan_data[$row['id']] = $row;
  }
  $stmt_surat_jalan->close();
?>

<?php
  $title = "Dashboard - Cendana Kargo";
  $page = "dashboard";
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
            <h1 class="h3 fw-bold mb-1">Dashboard SuperAdmin</h1>
            <p class="text-muted small mb-2">Selamat datang, <?= htmlspecialchars($_SESSION['username']); ?>! Kelola semua data sistem Cendana Kargo</p>
            
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
               <a href="?filter=bulan" class="btn btn-sm <?= $filter === 'bulan' ? 'btn-primary' : 'btn-outline-primary'; ?>">Bulan Ini</a>
               <a href="?filter=hari" class="btn btn-sm <?= $filter === 'hari' ? 'btn-primary' : 'btn-outline-primary'; ?>">Hari Ini</a>
             </div>
           </div>
        </div>

        <!-- KARTU STATISTIK -->
        <div class="row g-4 mb-4">
          <!-- Total Pendapatan -->
          <div class="col-xl-4 col-md-6">
            <div class="card border-0 shadow-sm h-100 bg-success bg-opacity-10">
              <div class="card-body">
                <p class="text-success mb-1 small fw-bold">TOTAL PENDAPATAN</p>
                <div class="d-flex justify-content-between align-items-center">
                  <div>
                    <h4 class="mb-0 fw-bold text-success">Rp <?= number_format($total_pendapatan, 0, ',', '.'); ?></h4>
                  </div>
                  <i class="fa-solid fa-money-bill-wave text-success opacity-50" style="font-size: 1.8rem;"></i>
                </div>
              </div>
            </div>
          </div>
          
          <!-- Total Pengiriman -->
          <div class="col-xl-4 col-md-6">
            <div class="card border-0 shadow-sm h-100 bg-primary bg-opacity-10">
              <div class="card-body">
                <p class="text-primary mb-1 small fw-bold">TOTAL PENGIRIMAN</p>
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
                <!-- === FITUR LACAK PAKET=== -->
        <div class="card border-0 shadow-sm mb-4">
          <div class="card-body">
            <h5 class="fw-bold text-dark mb-3">
              <i class="fa-solid fa-truck-fast me-2 text-danger"></i>Lacak Paket
            </h5>

            <!-- Input & Tombol -->
            <div class="row g-3 align-items-center">
              <div class="col-md-6 col-lg-5">
                <input type="text" id="resiSuper" class="form-control" placeholder="Masukkan nomor resi..." />
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
                class="mt-3" 
                style="display:none; padding:10px; border-radius:8px; font-size:14px;">
            </div>

            <!-- Hasil -->
            <div id="resultSuper" 
                style="display:none; margin-top:20px;" 
                class="p-3 rounded-3 border-start border-4 border-danger bg-light-subtle">
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
                      <span style="padding:6px 12px; border-radius:20px; font-size:13px; font-weight:600;">-</span>
                    </td>
                  </tr>
                </table>
              </div>
            </div>
          </div>
        </div>
                <!-- TABEL PENDAPATAN -->
        <div class="card border-0 shadow-sm mb-4">
          <div class="card-header bg-white border-0 py-3">
            <h5 class="mb-0 fw-bold">Pendapatan per Admin</h5>
          </div>
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-striped table-hover align-middle mb-0 small">
                <thead class="table-light">
                  <tr>
                    <th class="px-3">No.</th>
                    <th>Username</th>
                    <th class="text-end">Total</th>
                    <th class="text-end">Cash</th>
                    <th class="text-end">Transfer</th>
                    <th class="text-end">Bayar di Tempat</th>
                  </tr>
                </thead>
                <tbody>
                  <?php 
                  $no = 1;
                  foreach ($all_users as $user):
                    $data = $pendapatan_data[$user['id']] ?? [
                      'cash' => 0, 'transfer' => 0, 'cod' => 0, 'total' => 0
                    ];
                  ?>
                    <tr>
                      <td class="px-3"><?= $no++; ?></td>
                      <td class="fw-bold"><?= htmlspecialchars($user['username']); ?></td>
                      <td class="text-end fw-bold"><?= format_rupiah($data['total']); ?></td>
                      <td class="text-end"><?= format_rupiah($data['cash']); ?></td>
                      <td class="text-end"><?= format_rupiah($data['transfer']); ?></td>
                      <td class="text-end"><?= format_rupiah($data['cod']); ?></td>
                    </tr>
                  <?php endforeach; ?>
                  
                  <?php if ($supersuperadmin_data['pendapatan']['total'] > 0): ?>
                    <tr class="table-warning">
                      <td class="px-3"><?= $no++; ?></td>
                      <td class="fw-bold">
                        <?= htmlspecialchars($supersuperadmin_data['username']); ?>
                      </td>
                      <td class="text-end fw-bold"><?= format_rupiah($supersuperadmin_data['pendapatan']['total']); ?></td>
                      <td class="text-end"><?= format_rupiah($supersuperadmin_data['pendapatan']['cash']); ?></td>
                      <td class="text-end"><?= format_rupiah($supersuperadmin_data['pendapatan']['transfer']); ?></td>
                      <td class="text-end"><?= format_rupiah($supersuperadmin_data['pendapatan']['cod']); ?></td>
                    </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- TABEL PENGIRIMAN -->
        <div class="card border-0 shadow-sm mb-4">
          <div class="card-header bg-white border-0 py-3">
            <h5 class="mb-0 fw-bold">Pengiriman per Admin</h5>
          </div>
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-striped table-hover align-middle mb-0 small">
                <thead class="table-light">
                  <tr>
                    <th class="px-3">No.</th>
                    <th>Username</th>
                    <th class="text-center">Total</th>
                    <th class="text-center">BKD</th>
                    <th class="text-center">Dalam Perjalanan</th>
                    <th class="text-center">Sampai Tujuan</th>
                    <th class="text-center">POD</th>
                    <th class="text-center">Dibatalkan</th>
                  </tr>
                </thead>
                <tbody>
                  <?php 
                  $no = 1;
                  foreach ($all_users as $user):
                    $data = $pengiriman_data[$user['id']] ?? [
                      'total' => 0, 'bkd' => 0, 'perjalanan' => 0, 
                      'sampai' => 0, 'pod' => 0, 'batal' => 0
                    ];
                  ?>
                    <tr>
                      <td class="px-3"><?= $no++; ?></td>
                      <td class="fw-bold"><?= htmlspecialchars($user['username']); ?></td>
                      <td class="text-center fw-bold"><?= $data['total']; ?></td>
                      <td class="text-center"><?= $data['bkd']; ?></td>
                      <td class="text-center"><?= $data['perjalanan']; ?></td>
                      <td class="text-center"><?= $data['sampai']; ?></td>
                      <td class="text-center"><?= $data['pod']; ?></td>
                      <td class="text-center"><?= $data['batal']; ?></td>
                    </tr>
                  <?php endforeach; ?>
                  
                  <?php if ($supersuperadmin_data['pengiriman']['total'] > 0): ?>
                    <tr class="table-warning">
                      <td class="px-3"><?= $no++; ?></td>
                      <td class="fw-bold">
                        <?= htmlspecialchars($supersuperadmin_data['username']); ?>
                      </td>
                      <td class="text-center fw-bold"><?= $supersuperadmin_data['pengiriman']['total']; ?></td>
                      <td class="text-center"><?= $supersuperadmin_data['pengiriman']['bkd']; ?></td>
                      <td class="text-center"><?= $supersuperadmin_data['pengiriman']['perjalanan']; ?></td>
                      <td class="text-center"><?= $supersuperadmin_data['pengiriman']['sampai']; ?></td>
                      <td class="text-center"><?= $supersuperadmin_data['pengiriman']['pod']; ?></td>
                      <td class="text-center"><?= $supersuperadmin_data['pengiriman']['batal']; ?></td>
                    </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- TABEL SURAT JALAN -->
        <div class="card border-0 shadow-sm mb-5">
          <div class="card-header bg-white border-0 py-3">
            <h5 class="mb-0 fw-bold">Surat Jalan per Admin</h5>
          </div>
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-striped table-hover align-middle mb-0 small">
                <thead class="table-light">
                  <tr>
                    <th class="px-3">No.</th>
                    <th>Username</th>
                    <th class="text-center">Total</th>
                    <th class="text-center">Draft</th>
                    <th class="text-center">Dalam Perjalanan</th>
                    <th class="text-center">Sampai Tujuan</th>
                    <th class="text-center">Dibatalkan</th>
                  </tr>
                </thead>
                <tbody>
                  <?php 
                  $no = 1;
                  foreach ($all_users as $user):
                    $data = $surat_jalan_data[$user['id']] ?? [
                      'total' => 0, 'draft' => 0, 'perjalanan' => 0, 
                      'sampai' => 0, 'batal' => 0
                    ];
                  ?>
                    <tr>
                      <td class="px-3"><?= $no++; ?></td>
                      <td class="fw-bold"><?= htmlspecialchars($user['username']); ?></td>
                      <td class="text-center fw-bold"><?= $data['total']; ?></td>
                      <td class="text-center"><?= $data['draft']; ?></td>
                      <td class="text-center"><?= $data['perjalanan']; ?></td>
                      <td class="text-center"><?= $data['sampai']; ?></td>
                      <td class="text-center"><?= $data['batal']; ?></td>
                    </tr>
                  <?php endforeach; ?>
                  
                  <?php if ($supersuperadmin_data['surat_jalan']['total'] > 0): ?>
                    <tr class="table-warning">
                      <td class="px-3"><?= $no++; ?></td>
                      <td class="fw-bold">
                        <?= htmlspecialchars($supersuperadmin_data['username']); ?>
                      </td>
                      <td class="text-center fw-bold"><?= $supersuperadmin_data['surat_jalan']['total']; ?></td>
                      <td class="text-center"><?= $supersuperadmin_data['surat_jalan']['draft']; ?></td>
                      <td class="text-center"><?= $supersuperadmin_data['surat_jalan']['perjalanan']; ?></td>
                      <td class="text-center"><?= $supersuperadmin_data['surat_jalan']['sampai']; ?></td>
                      <td class="text-center"><?= $supersuperadmin_data['surat_jalan']['batal']; ?></td>
                    </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

      </div>
    </main>
    <script>
// ===== Variabel =====
const btnLacakSuper = document.getElementById('btnLacakSuper');
const inputResiSuper = document.getElementById('resiSuper');
const alertSuper = document.getElementById('alertSuper');
const resultSuper = document.getElementById('resultSuper');
const btnHapusSuper = document.getElementById('btnHapusSuper');

// ===== Alert helper =====
function showAlertSuper(message, type) {
  alertSuper.style.display = 'block';
  alertSuper.textContent = message;
  if (type === 'error') {
    alertSuper.style.backgroundColor = '#f8d7da';
    alertSuper.style.color = '#721c24';
    alertSuper.style.border = '1px solid #f5c6cb';
  } else if (type === 'success') {
    alertSuper.style.backgroundColor = '#d4edda';
    alertSuper.style.color = '#155724';
    alertSuper.style.border = '1px solid #c3e6cb';
  }
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
    showAlertSuper('Nomor resi tidak boleh kosong', 'error');
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
        showAlertSuper(data.message || 'Nomor resi tidak ditemukan', 'error');
      }
    })
    .catch(err => {
      console.error(err);
      showAlertSuper('Terjadi kesalahan. Silakan coba lagi.', 'error');
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

  document.getElementById('displayResiSuper').textContent = '-';
  document.getElementById('displayPengirimSuper').textContent = '-';
  document.getElementById('displayPenerimaSuper').textContent = '-';
  document.getElementById('displayAsalSuper').textContent = '-';
  document.getElementById('displayTujuanSuper').textContent = '-';
  document.getElementById('displayTarifSuper').textContent = '-';
  const spanStatus = document.getElementById('displayStatusSuper').querySelector('span');
  spanStatus.textContent = '-';
  spanStatus.style.backgroundColor = '';
  spanStatus.style.color = '';
});
</script>
  </div>
</div>

<?php include '../../templates/footer.php'; ?>
