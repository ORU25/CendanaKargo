<?php
session_start();
if(!isset($_SESSION['username'])){
    header("Location: ../../../auth/login.php");
    exit;
}

if(isset($_SESSION['role']) && $_SESSION['role'] !== 'superAdmin'){
    header("Location: ../../../?error=unauthorized");
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

include '../../../config/database.php';
$title = "Dashboard - Cendana Kargo";

$sqlCabang = "SELECT * FROM kantor_cabang ORDER BY id ASC";
$resultCabang = $conn->query($sqlCabang);
$cabangs = $resultCabang->num_rows > 0 ? $resultCabang->fetch_all(MYSQLI_ASSOC) : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        header("Location: create?error=failed");
        exit;
    }

    $asal = trim($_POST['id_cabang_asal']);
    $tujuan = trim($_POST['id_cabang_tujuan']);
    $nama_pengirim = trim($_POST['nama_pengirim']);
    $telp_pengirim = trim($_POST['telp_pengirim']);
    $nama_penerima = trim($_POST['nama_penerima']);
    $telp_penerima = trim($_POST['telp_penerima']);
    $nama_barang = trim($_POST['nama_barang']);
    $berat = (float) trim($_POST['berat']);
    $jumlah = (int) trim($_POST['jumlah']);
    $diskon = isset($_POST['diskon']) && $_POST['diskon'] !== '' ? (float) trim($_POST['diskon']) : 0;
    $pembayaran = trim($_POST['jasa_pengiriman']); // masih menggunakan field jasa_pengiriman

    // Validasi diskon
    if ($diskon < 0 || $diskon > 100) {
        header("Location: create?error=invalid_diskon");
        exit;
    }

    // Validasi nomor telepon (10–15 digit angka)
    if (!preg_match('/^[0-9]{10,15}$/', $telp_pengirim)) {
        header("Location: create?error=invalid_phone_pengirim");
        exit;
    }
    if (!preg_match('/^[0-9]{10,15}$/', $telp_penerima)) {
        header("Location: create?error=invalid_phone_penerima");
        exit;
    }

    // Cek tarif pengiriman
    $checkTarif = $conn->prepare("SELECT * FROM tarif_pengiriman WHERE id_cabang_asal = ? AND id_cabang_tujuan = ?");
    $checkTarif->bind_param("ii", $asal, $tujuan);
    $checkTarif->execute();
    $checkResult = $checkTarif->get_result();

    if (!$row = $checkResult->fetch_assoc()) {
        header("Location: create?error=tarif_not_found");
        exit;
    }

    $data_tarif = $row;
    $tarif_dasar = (float) $data_tarif['tarif_dasar'];
    $batas_berat = (float) $data_tarif['batas_berat_dasar'];
    $tarif_tambahan = (float) $data_tarif['tarif_tambahan_perkg'];

    // Hitung total tarif
    $tarif_sebelum_diskon = $berat <= $batas_berat
        ? $tarif_dasar
        : $tarif_dasar + (($berat - $batas_berat) * $tarif_tambahan);

    $total_tarif = $diskon > 0
        ? $tarif_sebelum_diskon - (($tarif_sebelum_diskon * $diskon) / 100)
        : $tarif_sebelum_diskon;

    // Dapatkan data cabang
    $getCabang = $conn->prepare("SELECT id, nama_cabang, kode_cabang FROM kantor_cabang WHERE id IN (?, ?)");
    $getCabang->bind_param("ii", $asal, $tujuan);
    $getCabang->execute();
    $resultCabangData = $getCabang->get_result();

    $nama_cabang_asal = "";
    $nama_cabang_tujuan = "";
    $kode_cabang_asal = "";

    while ($r = $resultCabangData->fetch_assoc()) {
        if ($r['id'] == $asal) {
            $nama_cabang_asal = $r['nama_cabang'];
            $kode_cabang_asal = strtoupper($r['kode_cabang']);
        } else {
            $nama_cabang_tujuan = $r['nama_cabang'];
        }
    }

    $qUrut = $conn->prepare("SELECT COUNT(*) AS total FROM pengiriman WHERE id_cabang_pengirim = ?");
    $qUrut->bind_param("i", $asal);
    $qUrut->execute();
    $rUrut = $qUrut->get_result()->fetch_assoc();
    $urutan = $rUrut['total'] + 1;
    $no_resi = "{$kode_cabang_asal}{$urutan}";

    $id_user = $_SESSION['id'] ?? 1;
    $username = $_SESSION['username'];

    $stmt = $conn->prepare("
        INSERT INTO pengiriman 
        (id_user, id_cabang_pengirim, id_cabang_penerima, id_tarif, user, cabang_pengirim, cabang_penerima, 
        no_resi, nama_pengirim, telp_pengirim, nama_penerima, telp_penerima, nama_barang, 
        berat, jumlah, pembayaran, diskon, total_tarif)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->bind_param(
        "iiiisssssssssdisdd",
        $id_user, $asal, $tujuan, $data_tarif['id'], $username,
        $nama_cabang_asal, $nama_cabang_tujuan, $no_resi,
        $nama_pengirim, $telp_pengirim, $nama_penerima, $telp_penerima,
        $nama_barang, $berat, $jumlah, $pembayaran, $diskon, $total_tarif
    );

    if ($stmt->execute()) {
        $id_pengiriman_baru = $conn->insert_id;
        $status_awal = 'bkd';
        $keterangan_awal = 'Pengiriman dibuat oleh ' . $username;

        $stmt_log = $conn->prepare('INSERT INTO log_status_pengiriman (id_pengiriman, status_lama, status_baru, keterangan, diubah_oleh) VALUES (?, NULL, ?, ?, ?)');
        if ($stmt_log) {
            $stmt_log->bind_param('issi', $id_pengiriman_baru, $status_awal, $keterangan_awal, $id_user);
            $stmt_log->execute();
            $stmt_log->close();
        }

        header("Location: index?success=created&resi=$no_resi");
        exit;
    } else {
        header("Location: create?error=failed");
        exit;
    }
}

$page = "pengiriman";
include '../../../templates/header.php';
include '../../../components/navDashboard.php';
include '../../../components/sidebar_offcanvas.php';
?>

<div class="container-fluid">
  <div class="row">
    <?php include '../../../components/sidebar.php'; ?>

    <div class="col-lg-10 py-4 px-5 row">
      <div class="card shadow-sm p-4 col-lg-7">
        <h4 class="fw-bold mb-4 text-danger">Tambah Pengiriman</h4>

        <?php if(isset($_GET['error']) && $_GET['error'] == 'failed'){
            $type = "danger"; $message = "Gagal menambahkan pengiriman baru";
            include '../../../components/alert.php';
        }?>
        <?php if(isset($_GET['error']) && $_GET['error'] == 'tarif_not_found'){
            $type = "danger"; $message = "Tarif untuk cabang asal dan tujuan tidak ditemukan";
            include '../../../components/alert.php';
        }?>
        <?php if(isset($_GET['error']) && $_GET['error'] == 'invalid_phone_pengirim'){
            $type = "danger"; $message = "Format nomor telepon pengirim tidak valid. Harus 10-15 digit angka.";
            include '../../../components/alert.php';
        }?>
        <?php if(isset($_GET['error']) && $_GET['error'] == 'invalid_phone_penerima'){
            $type = "danger"; $message = "Format nomor telepon penerima tidak valid. Harus 10-15 digit angka.";
            include '../../../components/alert.php';
        }?>
        <?php if(isset($_GET['error']) && $_GET['error'] == 'invalid_diskon'){
            $type = "danger"; $message = "Diskon tidak valid. Harus antara 0-100%.";
            include '../../../components/alert.php';
        }?>

        <form method="POST" action="create">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">

          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label fw-semibold">Cabang Asal</label>
              <input type="text" class="form-control" value="<?= $_SESSION['cabang']; ?>" readonly>
              <input type="hidden" name="id_cabang_asal" value="<?= $_SESSION['id_cabang']; ?>">
            </div>

            <div class="col-md-6">
              <label class="form-label fw-semibold" for="tujuan">Cabang Tujuan</label>
              <select id="tujuan" name="id_cabang_tujuan" class="form-select" required>
                <option value="">-- Pilih Cabang Tujuan --</option>
                <?php foreach($cabangs as $c): ?>
                  <option value="<?= $c['id']; ?>"><?= htmlspecialchars($c['nama_cabang']); ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-md-6">
              <label for="nama_pengirim" class="form-label fw-semibold">Nama Pengirim</label>
              <input type="text" class="form-control" id="nama_pengirim" name="nama_pengirim" required>
            </div>

            <div class="col-md-6">
              <label for="telp_pengirim" class="form-label fw-semibold">No Telp Pengirim</label>
              <input type="tel" class="form-control" id="telp_pengirim" name="telp_pengirim"
                     pattern="[0-9]{10,15}"
                     title="Nomor telepon harus 10-15 digit angka (contoh: 081234567890)"
                     placeholder="contoh: 081234567890" required>
            </div>

            <div class="col-md-6">
              <label for="nama_penerima" class="form-label fw-semibold">Nama Penerima</label>
              <input type="text" class="form-control" id="nama_penerima" name="nama_penerima" required>
            </div>

            <div class="col-md-6">
              <label for="telp_penerima" class="form-label fw-semibold">No Telp Penerima</label>
              <input type="tel" class="form-control" id="telp_penerima" name="telp_penerima"
                     pattern="[0-9]{10,15}"
                     title="Nomor telepon harus 10-15 digit angka (contoh: 081234567890)"
                     placeholder="contoh: 081234567890" required>
            </div>

            <div class="col-md-6">
              <label for="telp_penerima" class="form-label fw-semibold">No Telp Penerima</label>
              <input type="text" class="form-control" id="telp_penerima" name="telp_penerima" required>
            </div>

            <div class="col-md-6">
              <label for="nama_barang" class="form-label fw-semibold">Nama Barang</label>
              <input type="text" class="form-control" id="nama_barang" name="nama_barang" required>
            </div>

            <div class="col-md-6">
              <label for="berat" class="form-label fw-semibold">Berat (kg)</label>
              <input type="number" class="form-control" id="berat" name="berat" required>
            </div>

            <div class="col-md-6">
              <label for="jumlah" class="form-label fw-semibold">Jumlah</label>
              <input type="number" class="form-control" id="jumlah" name="jumlah" required>
            </div>

            <div class="col-md-6">
              <label for="diskon" class="form-label fw-semibold">Diskon % (opsional)</label>
              <input type="number" class="form-control" id="diskon" name="diskon" 
                     min="0" max="100" step="0.01"
                     placeholder="Masukkan diskon 0-100%">
              <small class="text-muted">Kosongkan jika tidak ada diskon</small>
            </div>

            <div class="col-md-6">
              <label for="jasa_pengiriman" class="form-label fw-semibold">Jasa Pengiriman</label>
              <select name="jasa_pengiriman" id="jasa_pengiriman" class="form-select" required>
                <option value="">-- Pilih Jasa Pengiriman --</option>
                <option value="Transfer">Transfer</option>
                <option value="Cash">Cash</option>
                <option value="Bayar di Tempat">Bayar di Tempat</option>
              </select>
            </div>
          </div>

          <div class="d-flex justify-content-start mt-4">
            <button type="submit" class="btn btn-danger fw-semibold" style="width: 120px;">Tambah</button>
          </div>
        </form>
      </div>

      <!-- Estimasi Biaya -->
      <div class="col-12 col-lg-5 px-0 px-lg-2 mt-4 mt-lg-0">
        <div id="estimasiBiaya" class="card shadow-sm p-4 ">
          <h5 class="fw-bold mb-4 text-danger">Estimasi Biaya</h5>
          <div id="error_not_found" class="alert alert-danger mt-3 w-100" role="alert" style="display: none;">
            Rute pengiriman tidak ditemukan
          </div>
          <div class="row g-2">
            <div class="col-md-6">
              <small class="text-muted d-block">Tarif Dasar (<span id="est_batas_berat">-</span> kg pertama)</small>
              <strong class="text-dark" id="est_tarif_dasar">Rp -</strong>
            </div>
            <div class="col-md-6">
              <small class="text-muted d-block">Tarif Tambahan /kg </small>
              <strong class="text-dark" id="est_tarif_tambahan">Rp -</strong>
            </div>
            <div class="col-md-6">
              <small class="text-muted d-block">Biaya Tambahan</small>
              <strong class="text-dark" id="est_biaya_tambahan">Rp -</strong>
            </div>
            <div class="col-md-6">
              <small class="text-muted d-block">Berat Kiriman</small>
              <strong class="text-dark" id="est_berat">- kg</strong>
            </div>
            <div class="col-md-6">
              <small class="text-muted d-block">Subtotal</small>
              <strong class="text-dark" id="est_subtotal">Rp -</strong>
            </div>
          </div>
          <div class="row mt-4">
                        <div class="col-md-6">
              <small class="text-muted d-block">Diskon (<span id="est_persen_diskon">0</span>%)</small>
              <strong class="text-success" id="est_nominal_diskon">Rp -</strong>
            </div>
            <div class="col-md-6">
              <small class="text-muted d-block">Total Bayar</small>
              <h4 class="fw-bold text-danger mb-0" id="est_total">Rp -</h4>
            </div>
          </div>
          <small class="text-muted d-inline-block mt-4">
            <i class="fa-solid fa-circle-info"></i> Estimasi ini adalah perhitungan sementara
          </small>
        </div>  
      </div>
    </div>
  </div>
</div>

<script>
// Data tarif dari database
const tarifData = <?= json_encode($conn->query("SELECT id_cabang_asal, id_cabang_tujuan, tarif_dasar, batas_berat_dasar, tarif_tambahan_perkg FROM tarif_pengiriman WHERE status = 'aktif'")->fetch_all(MYSQLI_ASSOC)); ?>;

function formatRupiah(angka) {
    return 'Rp ' + new Intl.NumberFormat('id-ID').format(angka);
}

function hitungEstimasi() {
    const cabangAsal = document.getElementById('asal').value;
    const cabangTujuan = document.getElementById('tujuan').value;
    const berat = parseFloat(document.getElementById('berat').value) || 0;
    const diskon = parseFloat(document.getElementById('diskon').value) || 0;
    
    // Cari tarif yang sesuai
    const tarif = tarifData.find(t => 
        t.id_cabang_asal == cabangAsal && t.id_cabang_tujuan == cabangTujuan
    );
    
    if (!tarif && cabangTujuan && cabangAsal) {
        document.getElementById('error_not_found').style.display = 'block';
        document.getElementById('est_batas_berat').textContent = '-';
        document.getElementById('est_tarif_dasar').textContent = 'Rp -';
        document.getElementById('est_tarif_tambahan').textContent = 'Rp -';
        document.getElementById('est_biaya_tambahan').textContent = 'Rp -';
        document.getElementById('est_subtotal').textContent = 'Rp -';
        document.getElementById('est_nominal_diskon').textContent = 'Rp -';
        document.getElementById('est_total').textContent = 'Rp -';
        document.getElementById('est_persen_diskon').textContent = '0';

        return;
    }else{
        document.getElementById('error_not_found').style.display = 'none';
    }
    
    const tarifDasar = parseFloat(tarif.tarif_dasar);
    const batasBerat = parseFloat(tarif.batas_berat_dasar);
    const tarifTambahan = parseFloat(tarif.tarif_tambahan_perkg);
    
    // Hitung biaya
    let biayaTambahan = 0;
    let subtotal = tarifDasar;
    
    if (berat > batasBerat) {
        const beratLebih = berat - batasBerat;
        biayaTambahan = beratLebih * tarifTambahan;
        subtotal = tarifDasar + biayaTambahan;
    }
    
    // Hitung diskon
    const nominalDiskon = (subtotal * diskon) / 100;
    const totalBayar = subtotal - nominalDiskon;
    
    // Tampilkan estimasi
    document.getElementById('error_not_found').style.display = 'none';
    document.getElementById('est_batas_berat').textContent = batasBerat;
    document.getElementById('est_tarif_dasar').textContent = formatRupiah(tarifDasar);
    document.getElementById('est_tarif_tambahan').textContent = formatRupiah(tarifTambahan);
    document.getElementById('est_berat').textContent = berat + ' kg';
    document.getElementById('est_biaya_tambahan').textContent = formatRupiah(biayaTambahan);
    document.getElementById('est_subtotal').textContent = formatRupiah(subtotal);
    document.getElementById('est_persen_diskon').textContent = diskon;
    document.getElementById('est_nominal_diskon').textContent = '- ' + formatRupiah(nominalDiskon);
    document.getElementById('est_total').textContent = formatRupiah(totalBayar);
    
    document.getElementById('estimasiBiaya').style.display = 'block';
}

// Event listeners
document.getElementById('asal').addEventListener('change', hitungEstimasi);
document.getElementById('tujuan').addEventListener('change', hitungEstimasi);
document.getElementById('berat').addEventListener('input', hitungEstimasi);
document.getElementById('diskon').addEventListener('input', hitungEstimasi);
</script>

<?php
include '../../../templates/footer.php';
?>
