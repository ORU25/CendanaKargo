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


<!-- === CARD TOTAL PENDAPATAN (baru ditambahkan) === -->
<div class="row g-4 mb-4">
  <div class="col-xl-4 col-md-6">
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

<!-- === CARD STATUS PENGIRIMAN === -->
<div class="row g-4 mb-4">
<!-- === JUDUL UNTUK STATUS PENGIRIMAN === -->
<h5 class="fw-bold text-dark mb-3 mt-4"> Status Pengiriman </h5>
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
</script>
    </main>
  </div>
</div>

<?php include '../../templates/footer.php'; ?>
