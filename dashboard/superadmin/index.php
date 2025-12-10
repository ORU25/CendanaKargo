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

  // Ambil cabang user yang login
  $cabang_superadmin = $_SESSION['cabang'];

  // === TOTAL DATA (hanya untuk cabang user, tidak termasuk systemOwner) ===
  $total_pengiriman = $conn->query("
    SELECT COUNT(*) AS total 
    FROM pengiriman p
    LEFT JOIN user u ON p.id_user = u.id
    WHERE $where_clause 
      AND p.cabang_pengirim = '$cabang_superadmin'
      AND (u.role != 'systemOwner' OR u.role IS NULL)
  ")->fetch_assoc()['total'] ?? 0;
  $total_surat_jalan = $conn->query("
    SELECT COUNT(*) AS total 
    FROM surat_jalan s 
    JOIN kantor_cabang kc ON s.id_cabang_pengirim = kc.id 
    LEFT JOIN user u ON s.id_user = u.id
    WHERE $where_clause 
      AND kc.nama_cabang = '$cabang_superadmin'
      AND (u.role != 'systemOwner' OR u.role IS NULL)
  ")->fetch_assoc()['total'] ?? 0;
  
  // Total pendapatan: cash + transfer dari cabang + invoice POD ke cabang (tidak termasuk systemOwner)
  $total_pendapatan = $conn->query("
    SELECT 
      (SUM(CASE WHEN p.cabang_pengirim = '$cabang_superadmin' AND p.pembayaran = 'cash' AND p.status != 'dibatalkan' THEN p.total_tarif ELSE 0 END) +
       SUM(CASE WHEN p.cabang_pengirim = '$cabang_superadmin' AND p.pembayaran = 'transfer' AND p.status != 'dibatalkan' THEN p.total_tarif ELSE 0 END) +
       SUM(CASE WHEN p.cabang_penerima = '$cabang_superadmin' AND p.pembayaran = 'invoice' AND p.status = 'pod' THEN p.total_tarif ELSE 0 END)) AS total
    FROM pengiriman p
    LEFT JOIN user u ON p.id_user = u.id
    WHERE $where_clause
      AND (u.role != 'systemOwner' OR u.role IS NULL)
  ")->fetch_assoc()['total'] ?? 0;


  // Helper function untuk format rupiah
  function format_rupiah($number) {
      return 'Rp ' . number_format($number, 0, ',', '.');
  }

  // Ambil semua user di cabang ini
  $stmt_users = $conn->prepare("SELECT id, username FROM user WHERE id_cabang = (SELECT id FROM kantor_cabang WHERE nama_cabang = ?) AND role != 'systemOwner' ORDER BY username ASC");
  $stmt_users->bind_param('s', $cabang_superadmin);
  $stmt_users->execute();
  $all_users_result = $stmt_users->get_result();
  $all_users = [];
  while($row = $all_users_result->fetch_assoc()) {
      $all_users[] = $row;
  }
  $stmt_users->close();

  // === PENDAPATAN PER ADMIN (dengan LEFT JOIN agar semua user muncul) ===
  $pendapatan_data = [];
  
  // Buat where clause dengan prefix p. untuk main query
  $where_clause_p = str_replace('tanggal', 'p.tanggal', $where_clause);
  $where_clause_p2 = str_replace('tanggal', 'p2.tanggal', $where_clause);
  $where_clause_pengambilan = str_replace('tanggal', 'pg.tanggal', $where_clause);
  
$sql_pendapatan = "
    SELECT u.id, u.username,
        (SUM(CASE 
            WHEN p.pembayaran = 'cash' 
                 AND p.id IS NOT NULL 
                 AND $where_clause_p 
                 AND p.status != 'dibatalkan'
            THEN p.total_tarif ELSE 0 END) +
         COALESCE(
            (SELECT SUM(p2.total_tarif)
             FROM pengiriman p2 
             JOIN pengambilan pg ON p2.no_resi = pg.no_resi
             WHERE pg.id_user = u.id
               AND p2.cabang_penerima = ? 
               AND p2.pembayaran = 'invoice' 
               AND p2.status = 'pod' 
               AND $where_clause_pengambilan), 0
         )) AS cash,
        SUM(CASE 
            WHEN p.pembayaran = 'transfer' 
                 AND p.id IS NOT NULL 
                 AND $where_clause_p 
                 AND p.status != 'dibatalkan'
            THEN p.total_tarif ELSE 0 END) AS transfer,
        SUM(CASE 
            WHEN p.pembayaran = 'invoice' 
                 AND p.id IS NOT NULL 
                 AND $where_clause_p 
                 AND p.status != 'dibatalkan'
            THEN p.total_tarif ELSE 0 END) AS invoice
    FROM user u
    LEFT JOIN pengiriman p ON u.id = p.id_user
    WHERE u.id_cabang = (SELECT id FROM kantor_cabang WHERE nama_cabang = ?) 
        AND u.role != 'systemOwner'
    GROUP BY u.id, u.username
    ORDER BY u.username
";

  $stmt_pendapatan = $conn->prepare($sql_pendapatan);
  $stmt_pendapatan->bind_param('ss', $cabang_superadmin, $cabang_superadmin);
  $stmt_pendapatan->execute();
  $result_pendapatan = $stmt_pendapatan->get_result();
  while ($row = $result_pendapatan->fetch_assoc()) {
      $row['total'] = $row['cash'] + $row['transfer'];
      $pendapatan_data[$row['id']] = $row;
  }
  $stmt_pendapatan->close();

  // === PENGIRIMAN PER ADMIN (dengan LEFT JOIN agar semua user muncul) ===
  $pengiriman_data = [];
  $sql_pengiriman = "
      SELECT u.id, u.username,
          SUM(CASE WHEN p.status = 'bkd' AND p.id IS NOT NULL AND $where_clause_p THEN 1 ELSE 0 END) AS bkd,
          SUM(CASE WHEN p.status = 'dalam pengiriman' AND p.id IS NOT NULL AND $where_clause_p THEN 1 ELSE 0 END) AS perjalanan,
          SUM(CASE WHEN p.status = 'sampai tujuan' AND p.id IS NOT NULL AND $where_clause_p THEN 1 ELSE 0 END) AS sampai,
          SUM(CASE WHEN p.status = 'pod' AND p.id IS NOT NULL AND $where_clause_p THEN 1 ELSE 0 END) AS pod,
          SUM(CASE WHEN p.status = 'dibatalkan' AND p.id IS NOT NULL AND $where_clause_p THEN 1 ELSE 0 END) AS batal,
          SUM(CASE WHEN p.id IS NOT NULL AND $where_clause_p THEN 1 ELSE 0 END) AS total
      FROM user u
      LEFT JOIN pengiriman p ON u.id = p.id_user
      WHERE u.id_cabang = (SELECT id FROM kantor_cabang WHERE nama_cabang = ?) 

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
  $where_clause_s = str_replace('tanggal', 's.tanggal', $where_clause);
  $sql_surat_jalan = "
      SELECT u.id, u.username,
          SUM(CASE WHEN s.status = 'draft' AND s.id IS NOT NULL AND $where_clause_s THEN 1 ELSE 0 END) AS draft,
          SUM(CASE WHEN s.status = 'diberangkatkan' AND s.id IS NOT NULL AND $where_clause_s THEN 1 ELSE 0 END) AS diberangkatkan,
          SUM(CASE WHEN s.id IS NOT NULL AND $where_clause_s THEN 1 ELSE 0 END) AS total
      FROM user u
      LEFT JOIN surat_jalan s ON u.id = s.id_user
      WHERE u.id_cabang = (SELECT id FROM kantor_cabang WHERE nama_cabang = ?) 

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
              <h1 class="h3 mb-1 fw-bold">Dashboard SuperAdmin</h1>
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
            <h5 class="mb-0 fw-bold">Laporan Pendapatan per Admin</h5>
          </div>
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-striped table-hover align-middle mb-0 small" style="font-size: 0.8rem;">
                <thead class="table-light">
                  <tr>
                    <th class="px-3" style="white-space: nowrap;">No.</th>
                    <th style="white-space: nowrap;">Username</th>
                    <th class="text-end" style="white-space: nowrap;">Total</th>
                    <th class="text-end" style="white-space: nowrap;">Cash + Invoice</th>
                    <th class="text-end" style="white-space: nowrap;">Transfer</th>
                    <th class="text-end" style="white-space: nowrap;">Invoice</th>
                    <th class="text-center" style="white-space: nowrap;">Cetak Data</th>
                  </tr>
                </thead>
                <tbody>
                  <?php 
                  $no = 1;
                  foreach ($all_users as $user):
                    $data = $pendapatan_data[$user['id']] ?? [
                      'cash' => 0, 'transfer' => 0, 'invoice' => 0, 'total' => 0
                    ];
                    // Build export URL with filter params
                    $export_params = 'username=' . urlencode($user['username']);
                    $export_params .= '&filter_type=' . urlencode($filter_type);
                    if (!empty($filter_value)) {
                        $export_params .= '&filter_value=' . urlencode($filter_value);
                    }
                  ?>
                    <tr>
                      <td class="px-3" style="white-space: nowrap;"><?= $no++; ?></td>
                      <td class="fw-bold" style="white-space: nowrap;"><?= htmlspecialchars($user['username']); ?></td>
                      <td class="text-end fw-bold" style="white-space: nowrap;"><?= format_rupiah($data['total']); ?></td>
                      <td class="text-end" style="white-space: nowrap;"><?= format_rupiah($data['cash']); ?></td>
                      <td class="text-end" style="white-space: nowrap;"><?= format_rupiah($data['transfer']); ?></td>
                      <td class="text-end text-danger" style="white-space: nowrap;"><?= format_rupiah($data['invoice']); ?></td>
                      <td class="d-flex justify-content-center" style="white-space: nowrap;">
                        <a href="export/export.php?<?php echo $export_params; ?>" 
                           class="btn btn-sm btn-outline-success">
                          <i class="fa-solid fa-file-export me-1"></i> 
                        </a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
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
                    <th class="text-center">Diberangkatkan</th>
                  </tr>
                </thead>
                <tbody>
                  <?php 
                  $no = 1;
                  foreach ($all_users as $user):
                    $data = $surat_jalan_data[$user['id']] ?? [
                      'total' => 0, 'draft' => 0, 'diberangkatkan' => 0
                    ];
                  ?>
                    <tr>
                      <td class="px-3"><?= $no++; ?></td>
                      <td class="fw-bold"><?= htmlspecialchars($user['username']); ?></td>
                      <td class="text-center fw-bold"><?= $data['total']; ?></td>
                      <td class="text-center"><?= $data['draft']; ?></td>
                      <td class="text-center"><?= $data['diberangkatkan']; ?></td>
                    </tr>
                  <?php endforeach; ?>
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
  </div>
</div>

<?php include '../../templates/footer.php'; ?>
