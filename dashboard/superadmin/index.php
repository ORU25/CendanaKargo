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
    ? "DATE(tanggal) = '$current_date'"
    : "MONTH(tanggal) = '$current_month' AND YEAR(tanggal) = '$current_year'";

// === variabel untuk ditampilkan di kotak info ===
 $selected_date_display = ($filter === 'hari') ? " (" . date('d F Y') . ")" : " " . date('F Y');

// Ambil cabang user yang login
$cabang_superadmin = $_SESSION['cabang'];

// === TOTAL DATA (hanya untuk cabang user) ===
 $total_pengiriman = $conn->query("SELECT COUNT(*) AS total FROM pengiriman WHERE $where_clause AND cabang_pengirim = '$cabang_superadmin'")->fetch_assoc()['total'] ?? 0;
 $total_surat_jalan = $conn->query("SELECT COUNT(*) AS total FROM surat_jalan s JOIN kantor_cabang kc ON s.id_cabang_pengirim = kc.id WHERE $where_clause AND kc.nama_cabang = '$cabang_superadmin'")->fetch_assoc()['total'] ?? 0;
 $total_pendapatan = $conn->query("SELECT SUM(total_tarif) AS total FROM pengiriman WHERE $where_clause AND cabang_pengirim = '$cabang_superadmin'")->fetch_assoc()['total'] ?? 0;

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

// === PENDAPATAN PER ADMIN (dengan LEFT JOIN agar semua user muncul) ===
$pendapatan_data = [];
$sql_pendapatan = "
    SELECT u.id, u.username,
        SUM(CASE WHEN p.pembayaran = 'cash' AND p.id IS NOT NULL AND $where_clause THEN p.total_tarif ELSE 0 END) AS cash,
        SUM(CASE WHEN p.pembayaran = 'transfer' AND p.id IS NOT NULL AND $where_clause THEN p.total_tarif ELSE 0 END) AS transfer,
        SUM(CASE WHEN p.pembayaran = 'bayar di tempat' AND p.id IS NOT NULL AND $where_clause THEN p.total_tarif ELSE 0 END) AS cod,
        SUM(CASE WHEN p.id IS NOT NULL AND $where_clause THEN p.total_tarif ELSE 0 END) AS total
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
                      <td class="text-end fw-bold text-success"><?= format_rupiah($data['total']); ?></td>
                      <td class="text-end text-primary"><?= format_rupiah($data['cash']); ?></td>
                      <td class="text-end text-info"><?= format_rupiah($data['transfer']); ?></td>
                      <td class="text-end text-warning"><?= format_rupiah($data['cod']); ?></td>
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
                      <td class="text-center fw-bold text-primary"><?= $data['total']; ?></td>
                      <td class="text-center text-warning"><?= $data['bkd']; ?></td>
                      <td class="text-center text-info"><?= $data['perjalanan']; ?></td>
                      <td class="text-center text-primary"><?= $data['sampai']; ?></td>
                      <td class="text-center text-success"><?= $data['pod']; ?></td>
                      <td class="text-center text-danger"><?= $data['batal']; ?></td>
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
                      <td class="text-center fw-bold text-primary"><?= $data['total']; ?></td>
                      <td class="text-center text-secondary"><?= $data['draft']; ?></td>
                      <td class="text-center text-info"><?= $data['perjalanan']; ?></td>
                      <td class="text-center text-success"><?= $data['sampai']; ?></td>
                      <td class="text-center text-danger"><?= $data['batal']; ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

      </div>
    </main>
  </div>
</div>

<?php include '../../templates/footer.php'; ?>
