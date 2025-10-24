<?php
session_start();
if(!isset($_SESSION['username'])){
    header("Location: ../../../auth/login.php");
    exit;
}

if(isset($_SESSION['role']) && $_SESSION['role'] !== 'admin'){
    header("Location: ../../../?error=unauthorized");
    exit;
}

// Token CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

include '../../../config/database.php';

$title = "Tambah Pengiriman - Cendana Kargo";

$stmt = $conn->prepare("
    SELECT u.id_cabang, k.nama_cabang, k.kode_cabang 
    FROM user u 
    JOIN kantor_cabang k ON u.id_cabang = k.id 
    WHERE u.id = ?
");
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$rowCab = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$rowCab) {
    header("Location: ../../../?error=no_branch");
    exit;
}

$id_cabang_admin = (int)$rowCab['id_cabang'];
$nama_cabang_admin = $rowCab['nama_cabang'];
$kode_cabang_admin = strtoupper($rowCab['kode_cabang']);

// Ambil semua cabang tujuan
$sqlCabang = "SELECT * FROM kantor_cabang WHERE id != $id_cabang_admin ORDER BY id ASC";
$resultCabang = $conn->query($sqlCabang);
$cabangs = $resultCabang->num_rows > 0 ? $resultCabang->fetch_all(MYSQLI_ASSOC) : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        header("Location: create?error=failed");
        exit;
    }

    $asal = $id_cabang_admin;
    $tujuan = trim($_POST['id_cabang_tujuan']);
    $nama_pengirim = trim($_POST['nama_pengirim']);
    $telp_pengirim = trim($_POST['telp_pengirim']);
    $nama_penerima = trim($_POST['nama_penerima']);
    $telp_penerima = trim($_POST['telp_penerima']);
    $nama_barang = trim($_POST['nama_barang']);
    $berat = (float) trim($_POST['berat']);
    $jumlah = (int) trim($_POST['jumlah']);
    $jasa_pengiriman = trim($_POST['jasa_pengiriman']);

    // Cek tarif dua arah
    $checkTarif = $conn->prepare("
        SELECT * FROM tarif_pengiriman 
        WHERE (id_cabang_asal = ? AND id_cabang_tujuan = ?)
           OR (id_cabang_asal = ? AND id_cabang_tujuan = ?)
    ");
    $checkTarif->bind_param("iiii", $asal, $tujuan, $tujuan, $asal);
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

    $total_tarif = ($berat <= $batas_berat)
        ? $tarif_dasar
        : $tarif_dasar + (($berat - $batas_berat) * $tarif_tambahan);

    // Nama cabang tujuan
    $getCabang = $conn->prepare("SELECT nama_cabang FROM kantor_cabang WHERE id = ?");
    $getCabang->bind_param("i", $tujuan);
    $getCabang->execute();
    $nama_cabang_tujuan = $getCabang->get_result()->fetch_assoc()['nama_cabang'] ?? '';
    $getCabang->close();

    // Buat nomor resi unik
    $qUrut = $conn->prepare("SELECT COUNT(*) AS total FROM pengiriman WHERE id_cabang_pengirim = ?");
    $qUrut->bind_param("i", $asal);
    $qUrut->execute();
    $urutan = $qUrut->get_result()->fetch_assoc()['total'] + 1;
    $no_resi = "{$kode_cabang_admin}{$urutan}";
    $qUrut->close();

    $id_user = $_SESSION['user_id'] ?? 1;
    $username = $_SESSION['username'];

    // Insert pengiriman
    $stmt = $conn->prepare("
        INSERT INTO pengiriman 
        (id_user, id_cabang_pengirim, id_cabang_penerima, id_tarif, user, cabang_pengirim, cabang_penerima, 
         no_resi, nama_pengirim, telp_pengirim, nama_penerima, telp_penerima, nama_barang, 
         berat, jumlah, jasa_pengiriman, tanggal, total_tarif)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), ?)
    ");

    $stmt->bind_param(
        "iiiisssssssssdids",
        $id_user, $asal, $tujuan, $data_tarif['id'], $username,
        $nama_cabang_admin, $nama_cabang_tujuan, $no_resi,
        $nama_pengirim, $telp_pengirim, $nama_penerima, $telp_penerima,
        $nama_barang, $berat, $jumlah, $jasa_pengiriman, $total_tarif
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
include '../../../components/sidebar_offcanvas_admin.php';
?>

<div class="container-fluid">
  <div class="row">
    <?php include '../../../components/sidebar.php'; ?>

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

        <form method="POST" action="create">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">

          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label fw-semibold">Cabang Asal</label>
              <input type="text" class="form-control" value="<?= htmlspecialchars($nama_cabang_admin); ?>" readonly>
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
              <input type="text" class="form-control" id="telp_pengirim" name="telp_pengirim" required>
            </div>

            <div class="col-md-6">
              <label for="nama_penerima" class="form-label fw-semibold">Nama Penerima</label>
              <input type="text" class="form-control" id="nama_penerima" name="nama_penerima" required>
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
              <input type="number" class="form-control" id="berat" name="berat" step="0.1" required>
            </div>

            <div class="col-md-6">
              <label for="jumlah" class="form-label fw-semibold">Jumlah</label>
              <input type="number" class="form-control" id="jumlah" name="jumlah" required>
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
    </div>
  </div>
</div>

<?php include '../../../templates/footer.php'; ?>
