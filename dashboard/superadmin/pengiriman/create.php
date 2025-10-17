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

    include '../../../config/database.php';
    $title = "Dashboard - Cendana Kargo";

    $sqlCabang = "SELECT * FROM kantor_cabang ORDER BY id ASC";
    $resultCabang = $conn->query($sqlCabang);
    if ($resultCabang->num_rows > 0) {
        $cabangs = $resultCabang->fetch_all(MYSQLI_ASSOC);
    } else {
        $cabangs = [];
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // 🔹 Ambil inputan dari form
        $asal = trim($_POST['id_cabang_asal']);
        $tujuan = trim($_POST['id_cabang_tujuan']);
        $nama_pengirim = trim($_POST['nama_pengirim']);
        $telp_pengirim = trim($_POST['telp_pengirim']);
        $nama_penerima = trim($_POST['nama_penerima']);
        $telp_penerima = trim($_POST['telp_penerima']);
        $nama_barang = trim($_POST['nama_barang']);
        $berat = (float) trim($_POST['berat']);
        $jumlah = (int) trim($_POST['jumlah']);
        $jasa_pengiriman = trim($_POST['jasa_pengiriman']);

        // 🔹 Cek tarif untuk asal & tujuan
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

        // 🔹 Hitung total tarif
        if ($berat <= $batas_berat) {
            $total_tarif = $tarif_dasar;
        } else {
            $lebih = $berat - $batas_berat;
            $total_tarif = $tarif_dasar + ($lebih * $tarif_tambahan);
        }

        // 🔹 Ambil data cabang asal & tujuan (nama + kode)
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

        // 🔹 Generate nomor resi (kode cabang + nomor urut tanpa nol)
        $qUrut = $conn->prepare("SELECT COUNT(*) AS total FROM pengiriman WHERE id_cabang_pengirim = ?");
        $qUrut->bind_param("i", $asal);
        $qUrut->execute();
        $rUrut = $qUrut->get_result()->fetch_assoc();
        $urutan = $rUrut['total'] + 1;

        $no_resi = "{$kode_cabang_asal}{$urutan}"; // contoh: BTG1, BTG2, dst.

        // 🔹 Ambil user id (penginput)
        $id_user = $_SESSION['id'] ?? 1; // fallback ke 1 jika belum diset di session

        // 🔹 Insert data ke tabel pengiriman
        $stmt = $conn->prepare("
            INSERT INTO pengiriman 
            (id_user, id_cabang_pengirim, id_cabang_penerima, id_tarif, user, cabang_pengirim, cabang_penerima, 
            no_resi, nama_pengirim, telp_pengirim, nama_penerima, telp_penerima, nama_barang, 
            berat, jumlah, jasa_pengiriman, tanggal, total_tarif)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), ?)
        ");

        $user = $_SESSION['username'];
        $stmt->bind_param(
            "iiiisssssssssidss",
            $id_user,             // i
            $asal,                // i
            $tujuan,              // i
            $data_tarif['id'],    // i
            $username,            // s
            $namaCabangAsal,      // s
            $namaCabangTujuan,    // s
            $no_resi,             // s
            $nama_pengirim,       // s
            $telp_pengirim,       // s
            $nama_penerima,       // s
            $telp_penerima,       // s
            $nama_barang,         // s
            $berat,               // d
            $jumlah,              // i
            $jasa_pengiriman,     // s
            $total_tarif          // d
        );

        if ($stmt->execute()) {
            header("Location: index?success=created&resi=$no_resi");
            exit;
        } else {
            header("Location: create?error=failed");
            exit;
        }
    }

?>

<?php  
    $page = "pengiriman";
    include '../../../templates/header.php';
    include '../../../components/navDashboard.php';
    include '../../../components/sidebar_offcanvas.php';
?>
<div class="container-fluid">
  <div class="row">
    <div class="col-lg-2 d-none d-lg-block bg-light border-end vh-min-100">
      <div class="list-group list-group-flush">
        <a href="<?= BASE_URL; ?>dashboard/superadmin/" class="list-group-item list-group-item-action">Dashboard</a>
        <a href="<?= BASE_URL; ?>dashboard/superadmin/pengiriman/" class="list-group-item list-group-item-action fw-bold text-danger">Pengiriman</a>
        <a href="<?= BASE_URL; ?>dashboard/superadmin/surat_jalan/" class="list-group-item list-group-item-action">Surat Jalan</a>
        <a href="<?= BASE_URL; ?>dashboard/superadmin/tarif/" class="list-group-item list-group-item-action">Tarif</a>
        <a href="<?= BASE_URL; ?>dashboard/superadmin/kantor_cabang/" class="list-group-item list-group-item-action ">Kantor Cabang</a>
        <a href="<?= BASE_URL; ?>dashboard/superadmin/user/" class="list-group-item list-group-item-action">User</a>
      </div>
        <a href="<?= BASE_URL; ?>auth/logout.php" class="btn btn-outline-danger mt-3">Logout</a>
    </div>

    <!-- Konten utama -->
    <div class="col-lg-10">
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
        <h1>Tambah Pengiriman</h1>
        <form method="POST" action="create" class="col-5">
            <div class="mb-3">
                <label  class="form-label" for="asal">Cabang Asal</label>
                <select id="asal" name="id_cabang_asal" class="form-control" required>
                    <option value="">-- Pilih Cabang Asal --</option>
                    <?php foreach($cabangs as $c): ?>
                        <option value="<?= $c['id']; ?>"><?= htmlspecialchars($c['nama_cabang']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label  class="form-label" for="tujuan">Cabang Tujuan</label>
                <select id="tujuan" name="id_cabang_tujuan" class="form-control" required>
                    <option value="">-- Pilih Cabang Tujuan --</option>
                    <?php foreach($cabangs as $c): ?>
                        <option value="<?= $c['id']; ?>"><?= htmlspecialchars($c['nama_cabang']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label for="nama_pengirim" class="form-label">Nama Pengirim</label>
                <input type="text" class="form-control" id="nama_pengirim" name="nama_pengirim" required>
            </div>
            <div class="mb-3">
                <label for="telp_pengirim" class="form-label">No Telp Pengirim</label>
                <input type="text" class="form-control" id="telp_pengirim" name="telp_pengirim" required>
            </div>
            <div class="mb-3">
                <label for="nama_penerima" class="form-label">Nama Penerima</label>
                <input type="text" class="form-control" id="nama_penerima" name="nama_penerima" required>
            </div>
            <div class="mb-3">
                <label for="telp_penerima" class="form-label">No Telp Penerima</label>
                <input type="text" class="form-control" id="telp_penerima" name="telp_penerima" required>
            </div>
            <div class="mb-3">
                <label for="nama_barang" class="form-label">Nama Barang</label>
                <input type="text" class="form-control" id="nama_barang" name="nama_barang" required>
            </div>
            <div class="mb-3">
                <label for="berat" class="form-label">Berat (kg)</label>
                <input type="number" class="form-control" id="berat" name="berat" required>
            </div>
            <div class="mb-3">
                <label for="jumlah" class="form-label">Jumlah</label>
                <input type="number" class="form-control" id="jumlah" name="jumlah" required>
            </div>
            <div class="mb-3">
                <label for="jasa_pengiriman" class="form-label">Jasa Pengiriman</label>
                <select name="jasa_pengiriman" id="jasa_pengiriman" class="form-control" required>
                    <option value="">-- Pilih Jasa Pengiriman --</option>
                    <option value="Transfer">Transfer</option>
                    <option value="Cash">Cash</option>
                    <option value="Bayar di Tempat">Bayar di Tempat</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary mt-3">Buat Pengiriman</button>
        </form>
    </div>
  </div>
</div>
<?php
    include '../../../templates/footer.php';
?>