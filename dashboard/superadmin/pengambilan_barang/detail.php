<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: ../../../auth/login.php");
    exit;
}

if ($_SESSION['role'] !== 'superAdmin') {
    header("Location: ../../../?error=unauthorized");
    exit;
}

include '../../../config/database.php';

$title = "Detail Pengambilan Barang - Cendana Kargo";
$page = "pengambilan_barang";

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php?error=invalid_id");
    exit;
}

$id = (int)$_GET['id'];

$stmt = $conn->prepare("SELECT * FROM pengiriman WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$pengiriman = $result->fetch_assoc();
$stmt->close();

if (!$pengiriman) {
    header("Location: index.php?error=data_not_found");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_pengambil = trim($_POST['nama_pengambil']);
    $telp_pengambil = trim($_POST['telp_pengambil']);
    $id_user = $_SESSION['id'];

    if (empty($nama_pengambil) || empty($telp_pengambil)) {
        $error_message = "Nama dan nomor telepon pengambil wajib diisi.";
    } else {
        $stmt = $conn->prepare("
            INSERT INTO pengambilan (id_user, no_resi, nama_pengambil, telp_pengambil)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->bind_param("isss", $id_user, $pengiriman['no_resi'], $nama_pengambil, $telp_pengambil);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("UPDATE pengiriman SET status = 'selesai' WHERE id = ?");
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            header("Location: index.php?success=status_updated");
            exit;
        } else {
            $error_message = "Gagal memperbarui status.";
        }

        $stmt->close();
    }
}

include '../../../templates/header.php';
include '../../../components/navDashboard.php';
include '../../../components/sidebar_offcanvas.php';
?>

<div class="container-fluid">
<div class="row">
    <?php include '../../../components/sidebar.php'; ?>

    <div class="col-lg-10 bg-light min-vh-100">
        <div class="container py-4">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h4 fw-bold mb-0">Detail Pengambilan Barang</h1>
            <a href="index.php" class="btn btn-secondary btn-sm">
                <i class="fa-solid fa-arrow-left"></i> Kembali
            </a>
        </div>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <h6 class="fw-bold text-danger mb-2">Informasi Pengiriman</h6>
                        <p><strong>No Resi:</strong> <?= htmlspecialchars($pengiriman['no_resi']); ?></p>
                        <p><strong>Nama Barang:</strong> <?= htmlspecialchars($pengiriman['nama_barang']); ?></p>
                        <p><strong>Berat:</strong> <?= htmlspecialchars($pengiriman['berat']); ?> Kg</p>
                        <p><strong>Total Tarif:</strong> Rp <?= number_format($pengiriman['total_tarif'], 0, ',', '.'); ?></p>
                        <p><strong>Status:</strong> 
                            <span class="badge <?= $pengiriman['status'] == 'sampai tujuan' ? 'bg-info' : 'bg-success'; ?>">
                                <?= htmlspecialchars($pengiriman['status']); ?>
                            </span>
                        </p>
                    </div>
                    <div class="col-md-6">
                        <h6 class="fw-bold text-danger mb-2">Informasi Cabang</h6>
                        <p><strong>Cabang Pengirim:</strong> <?= htmlspecialchars($pengiriman['cabang_pengirim']); ?></p>
                        <p><strong>Cabang Penerima:</strong> <?= htmlspecialchars($pengiriman['cabang_penerima']); ?></p>
                        <p><strong>Tanggal:</strong> <?= date('d F Y', strtotime($pengiriman['tanggal'])); ?></p>
                    </div>
                </div>

                <hr>

                <div class="row">
                    <div class="col-md-6">
                        <h6 class="fw-bold text-danger mb-2">Data Pengirim</h6>
                        <p><strong>Nama:</strong> <?= htmlspecialchars($pengiriman['nama_pengirim']); ?></p>
                        <p><strong>Alamat:</strong> <?= htmlspecialchars($pengiriman['alamat_pengirim']); ?></p>
                        <p><strong>No. Telepon:</strong> <?= htmlspecialchars($pengiriman['no_telp_pengirim']); ?></p>
                    </div>
                    <div class="col-md-6">
                        <h6 class="fw-bold text-danger mb-2">Data Penerima</h6>
                        <p><strong>Nama:</strong> <?= htmlspecialchars($pengiriman['nama_penerima']); ?></p>
                        <p><strong>Alamat:</strong> <?= htmlspecialchars($pengiriman['alamat_penerima']); ?></p>
                        <p><strong>No. Telepon:</strong> <?= htmlspecialchars($pengiriman['no_telp_penerima']); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($pengiriman['status'] === 'sampai tujuan'): ?>
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <form method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menyelesaikan pengambilan barang ini?')">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Nama Pengambil</label>
                            <input type="text" name="nama_pengambil" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">No. Telepon Pengambil</label>
                            <input type="text" name="telp_pengambil" class="form-control" required>
                        </div>
                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-success">
                                <i class="fa-solid fa-check"></i> Tandai Selesai
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-success mt-3">
                <i class="fa-solid fa-circle-check"></i> Barang ini sudah selesai diambil.
            </div>
        <?php endif; ?>

        </div>
    </div>
</div>
</div>

<?php include '../../../templates/footer.php'; ?>
