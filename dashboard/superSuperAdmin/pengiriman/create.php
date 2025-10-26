<?php
session_start();
if(!isset($_SESSION['username'])){
    header("Location: ../../../auth/login.php");
    exit;
}

if(isset($_SESSION['role']) && $_SESSION['role'] !== 'superSuperAdmin'){
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
    $pembayaran = trim($_POST['pembayaran']);

    // Validasi diskon (0-100%)
    if ($diskon < 0 || $diskon > 100) {
        header("Location: create?error=invalid_diskon");
        exit;
    }

    if (!preg_match('/^[0-9]{10,15}$/', $telp_pengirim)) {
        header("Location: create?error=invalid_phone_pengirim");
        exit;
    }
    if (!preg_match('/^[0-9]{10,15}$/', $telp_penerima)) {
        header("Location: create?error=invalid_phone_penerima");
        exit;
    }

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

    // Hitung tarif sebelum diskon
    if ($berat <= $batas_berat) {
        $tarif_sebelum_diskon = $tarif_dasar;
    } else {
        $lebih = $berat - $batas_berat;
        $tarif_sebelum_diskon = $tarif_dasar + ($lebih * $tarif_tambahan);
    }

    // Hitung total tarif setelah diskon
    if ($diskon > 0) {
        $nominal_diskon = ($tarif_sebelum_diskon * $diskon) / 100;
        $total_tarif = $tarif_sebelum_diskon - $nominal_diskon;
    } else {
        $total_tarif = $tarif_sebelum_diskon;
    }

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

    $stmt = $conn->prepare("
        INSERT INTO pengiriman 
        (id_user, id_cabang_pengirim, id_cabang_penerima, id_tarif, user, cabang_pengirim, cabang_penerima, 
        no_resi, nama_pengirim, telp_pengirim, nama_penerima, telp_penerima, nama_barang, 
        berat, jumlah, pembayaran, tanggal, diskon, total_tarif)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), ?, ?)
    ");

    $username = $_SESSION['username'];
    $stmt->bind_param(
        "iiiisssssssssdisdd",
        $id_user, $asal, $tujuan, $data_tarif['id'], $username,
        $nama_cabang_asal, $nama_cabang_tujuan, $no_resi,
        $nama_pengirim, $telp_pengirim, $nama_penerima, $telp_penerima,
        $nama_barang, $berat, $jumlah, $pembayaran, $diskon, $total_tarif
    );

    if ($stmt->execute()) {
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

    <!-- Konten utama -->
    <div class="col-lg-10 py-4 px-5">
      <div class="card shadow-sm p-4" style="max-width: 800px;">
        <h4 class="fw-bold mb-4 text-danger">Tambah Pengiriman</h4>

        <?php if(isset($_GET['error']) && $_GET['error'] == 'failed'){
            $type = "danger";
            $message = "Gagal menambahkan pengiriman baru";
            include '../../../components/alert.php';
        }?>
        <?php if(isset($_GET['error']) && $_GET['error'] == 'tarif_not_found'){
            $type = "danger";
            $message = "Tarif untuk cabang asal dan tujuan tidak ditemukan";
            include '../../../components/alert.php';
        }?>
        <?php if(isset($_GET['error']) && $_GET['error'] == 'invalid_phone_pengirim'){
            $type = "danger";
            $message = "Format nomor telepon pengirim tidak valid. Harus 10-15 digit angka.";
            include '../../../components/alert.php';
        }?>
        <?php if(isset($_GET['error']) && $_GET['error'] == 'invalid_phone_penerima'){
            $type = "danger";
            $message = "Format nomor telepon penerima tidak valid. Harus 10-15 digit angka.";
            include '../../../components/alert.php';
        }?>
        <?php if(isset($_GET['error']) && $_GET['error'] == 'invalid_diskon'){
            $type = "danger";
            $message = "Diskon tidak valid. Harus antara 0-100%.";
            include '../../../components/alert.php';
        }?>


        <form method="POST" action="create">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">

          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label fw-semibold" for="asal">Cabang Asal</label>
              <select id="asal" name="id_cabang_asal" class="form-select" required>
                <option value="">-- Pilih Cabang Asal --</option>
                <?php foreach($cabangs as $c): ?>
                  <option value="<?= $c['id']; ?>"><?= htmlspecialchars($c['nama_cabang']); ?></option>
                <?php endforeach; ?>
              </select>
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
                     placeholder="contoh: 081234567890"
                     required>
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
                     placeholder="contoh: 081234567890"
                     required>
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
              <label for="pembayaran" class="form-label fw-semibold">Metode Pembayaran</label>
              <select name="pembayaran" id="pembayaran" class="form-select" required>
                <option value="">-- Pilih Metode Pembayaran --</option>
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
    </div>
  </div>
</div>

<?php
include '../../../templates/footer.php';
?>
