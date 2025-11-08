<?php
    session_start();
    if(!isset($_SESSION['username'] )|| !isset($_SESSION['user_id'])){
        header("Location: ../../../auth/login");
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
    
    // Handle cancel shipment (only for BKD status)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_shipment'])) {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            header('Location: detail?id=' . intval($_GET['id']) . '&error=cancel_failed');
            exit;
        }
        $id_update = (int)($_POST['id'] ?? 0);
        $keterangan = trim((string)($_POST['keterangan'] ?? ''));

        if ($id_update > 0) {
            // Get current status
            $stmt_check = $conn->prepare('SELECT status, cabang_pengirim FROM pengiriman WHERE id = ? LIMIT 1');
            if ($stmt_check) {
                $stmt_check->bind_param('i', $id_update);
                $stmt_check->execute();
                $result_check = $stmt_check->get_result();
                if ($result_check->num_rows > 0) {
                    $check_data = $result_check->fetch_assoc();
                    $status_lama = strtolower($check_data['status']);
                    
                    // Check if user has permission
                    if($check_data['cabang_pengirim'] != $_SESSION['cabang']){
                        header("Location: ./?error=unauthorized");
                        exit;
                    }

                    // Only allow cancellation if status is BKD
                    if ($status_lama !== 'bkd') {
                        header('Location: detail?id=' . $id_update . '&error=cannot_cancel');
                        exit;
                    }

                    if ($status_lama === 'dibatalkan') {
                        header('Location: detail?id=' . $id_update . '&error=already_cancelled');
                        exit;
                    }

                    // Update status pengiriman to 'dibatalkan'
                    $status_baru = 'dibatalkan';
                    $stmt = $conn->prepare('UPDATE pengiriman SET status = ? WHERE id = ?');
                    if ($stmt) {
                        $stmt->bind_param('si', $status_baru, $id_update);
                        if ($stmt->execute()) {
                            // Insert log perubahan status
                            $id_user_update = $_SESSION['user_id'] ?? null;
                            $stmt_log = $conn->prepare('INSERT INTO log_status_pengiriman (id_pengiriman, status_lama, status_baru, keterangan, diubah_oleh) VALUES (?, ?, ?, ?, ?)');
                            if ($stmt_log) {
                                $stmt_log->bind_param('isssi', $id_update, $status_lama, $status_baru, $keterangan, $id_user_update);
                                $stmt_log->execute();
                                $stmt_log->close();
                            }
                            header('Location: detail?id=' . $id_update . '&success=cancelled');
                            exit;
                        }
                        $stmt->close();
                    }
                }
                $stmt_check->close();
            }
        }
        header('Location: detail?error=cancel_failed');
        exit;
    }
    
    // Ambil data pengiriman
    $pengiriman = null;
    if(isset($_GET['id'])) {
        $id = intval($_GET['id']);
        $stmt = $conn->prepare('SELECT * FROM pengiriman WHERE id = ? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $pengiriman = $result->fetch_assoc();
            }
            $stmt->close();
        }

        if($pengiriman['cabang_pengirim'] != $_SESSION['cabang']){
            header("Location: ./?error=not_found");
            exit;
        }

        //ambil data user
        if ($pengiriman) {
            $stmt = $conn->prepare('SELECT username FROM user WHERE id = ? LIMIT 1');
            if ($stmt) {
                $stmt->bind_param('i', $pengiriman['id_user']);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result->num_rows > 0) {
                    $userData = $result->fetch_assoc();
                    $pengiriman['user'] = $userData['username'];
                }
                $stmt->close();
            }
        }

        // log perubahan status
        $logs = [];
        if ($pengiriman) {
            $stmt_logs = $conn->prepare('
                SELECT l.*, u.username 
                FROM log_status_pengiriman l 
                LEFT JOIN user u ON l.diubah_oleh = u.id 
                WHERE l.id_pengiriman = ? 
                ORDER BY l.waktu_perubahan DESC
            ');
            if ($stmt_logs) {
                $stmt_logs->bind_param('i', $id);
                $stmt_logs->execute();
                $result_logs = $stmt_logs->get_result();
                while ($row = $result_logs->fetch_assoc()) {
                    $logs[] = $row;
                }
                $stmt_logs->close();
            }
        }
    }



    if (!$pengiriman) {
        header("Location: ./?error=not_found");
        exit;
    }

    $title = "Detail Pengiriman - Cendana Kargo";
?>

<?php
    $page = "pengiriman";
    include '../../../templates/header.php';
    include '../../../components/navDashboard.php';
    include '../../../components/sidebar_offcanvas.php';
?>

<div class="container-fluid">
  <div class="row">
    <?php include '../../../components/sidebar.php'; ?>

    <!-- Konten utama -->
    <div class="col-lg-10 bg-light">
        <div class="container-fluid p-4">
            <!-- Alerts -->
            <?php if(isset($_GET['success']) && $_GET['success'] == 'cancelled'){
                $type = "success";
                $message = "Pengiriman berhasil dibatalkan";
                include '../../../components/alert.php';}
            ?>
            <?php if(isset($_GET['error']) && $_GET['error'] == 'cannot_cancel'){
                $type = "danger";
                $message = "Pengiriman hanya dapat dibatalkan jika status masih BKD";
                include '../../../components/alert.php';}    
            ?>
            <?php if(isset($_GET['error']) && $_GET['error'] == 'already_cancelled'){
                $type = "danger";
                $message = "Pengiriman sudah dibatalkan sebelumnya";
                include '../../../components/alert.php';}    
            ?>
            <?php if(isset($_GET['error']) && $_GET['error'] == 'cancel_failed'){
                $type = "danger";
                $message = "Gagal membatalkan pengiriman";
                include '../../../components/alert.php';}    
            ?>

            <!-- Header -->
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h4 mb-1 fw-bold">Detail Pengiriman</h1>
                    <p class="text-muted small mb-0">Dibuat oleh:  <span class="fw-semibold"><?= htmlspecialchars($pengiriman['user']); ?></span></p>
                    <p class="text-muted small mb-0">No. Resi: <span class="fw-semibold"><?= htmlspecialchars($pengiriman['no_resi']); ?></span></p>
                </div>
                <div class="d-flex gap-2 mt-2 mt-md-0">
                    <?php if(strtolower($pengiriman['status']) == 'bkd'): ?>
                    <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#cancelShipmentModal">
                        <i class="fa-solid fa-ban"></i> Batalkan Pengiriman
                    </button>
                    <?php endif; ?>
                    <a href="./" class="btn btn-sm btn-outline-secondary">Kembali</a>
                </div>
            </div>

            <!-- Hero Card -->
            <div class="card border-0 shadow mb-4 text-capitalize">
                <div class="card-body p-4">
                    <div class="row">
                        <div class="col-md-8">
                            <h5 class="fw-bold mb-3"><?= htmlspecialchars($pengiriman['nama_barang']); ?></h5>
                            <div class="row g-3">
                                <div class="col-6 col-md-3">
                                    <small class="opacity-75 d-block">Tanggal</small>
                                    <strong><?= date('d/m/Y', strtotime($pengiriman['tanggal'])); ?></strong>
                                </div>
                                <div class="col-6 col-md-2">
                                    <small class="opacity-75 d-block">Berat</small>
                                    <strong><?= number_format($pengiriman['berat'], 1); ?> kg</strong>
                                </div>
                                <div class="col-6 col-md-2">
                                    <small class="opacity-75 d-block">Jumlah</small>
                                    <strong><?= (int)$pengiriman['jumlah']; ?> item</strong>
                                </div>
                                <div class="col-6 col-md-2">
                                    <small class="opacity-75 d-block">Diskon</small>
                                    <?php if($pengiriman['diskon'] == 0): ?>
                                        <strong>-</strong>
                                    <?php else: ?>
                                        <strong><?= number_format($pengiriman['diskon'], 1); ?>%</strong>
                                    <?php endif; ?>
                                </div>
                                <div class="col-6 col-md-3">
                                    <small class="opacity-75 d-block">Metode Pembayaran</small>
                                    <strong><?= htmlspecialchars($pengiriman['pembayaran']); ?></strong>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mt-3 mt-md-0 text-md-end">
                            <small class="opacity-75 d-block mb-2">Total Tarif</small>
                            <h3 class="fw-bold mb-2">Rp <?= number_format($pengiriman['total_tarif'], 0, ',', '.'); ?></h3>
                            <?php
                                $badgeClass = 'secondary';
                                switch(strtolower($pengiriman['status'])) {
                                    case 'bkd':
                                        $badgeClass = 'warning';
                                        break;
                                    case 'dalam pengiriman':
                                        $badgeClass = 'primary';
                                        break;
                                    case 'sampai tujuan':
                                        $badgeClass = 'info';
                                        break;
                                    case 'pod':
                                        $badgeClass = 'success';
                                        break;
                                    case 'dibatalkan':
                                        $badgeClass = 'danger';
                                        break;
                                }
                            ?>
                            <span class="text-uppercase px-3 py-2 badge rounded-pill text-bg-<?= $badgeClass; ?>"><?= htmlspecialchars($pengiriman['status']); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Detail Cards -->
            <div class="row g-3 text-capitalize mb-4">
                <!-- Pengirim -->
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center mb-3">
                                <div class="bg-primary bg-opacity-10 rounded p-2 me-3">
                                    <i class="fa-solid fa-user"></i>
                                </div>
                                <h6 class="mb-0 fw-semibold">Data Pengirim</h6>
                            </div>
                            <div class="mb-3">
                                <small class="text-muted d-block mb-1">Nama</small>
                                <p class="mb-0 fw-semibold"><?= htmlspecialchars($pengiriman['nama_pengirim']); ?></p>
                            </div>
                            <div class="mb-3">
                                <small class="text-muted d-block mb-1">Telepon</small>
                                <p class="mb-0"><?= htmlspecialchars($pengiriman['telp_pengirim'] ?? '-'); ?></p>
                            </div>
                            <div>
                                <small class="text-muted d-block mb-1">Cabang Asal</small>
                                <span class="badge bg-primary"><?= htmlspecialchars($pengiriman['cabang_pengirim']); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Penerima -->
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center mb-3">
                                <div class="bg-success bg-opacity-10 rounded p-2 me-3">
                                    <i class="fa-solid fa-user"></i>
                                </div>
                                <h6 class="mb-0 fw-semibold">Data Penerima</h6>
                            </div>
                            <div class="mb-3">
                                <small class="text-muted d-block mb-1">Nama</small>
                                <p class="mb-0 fw-semibold"><?= htmlspecialchars($pengiriman['nama_penerima']); ?></p>
                            </div>
                            <div class="mb-3">
                                <small class="text-muted d-block mb-1">Telepon</small>
                                <p class="mb-0"><?= htmlspecialchars($pengiriman['telp_penerima'] ?? '-'); ?></p>
                            </div>
                            <div>
                                <small class="text-muted d-block mb-1">Cabang Tujuan</small>
                                <span class="badge bg-success"><?= htmlspecialchars($pengiriman['cabang_penerima']); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Data Pengambilan Barang -->
            <?php
            $pengambilanData = null;
            $stmt_pengambilan = $conn->prepare("
                SELECT nama_pengambil, telp_pengambil, tanggal
                FROM pengambilan
                WHERE no_resi = ?
                ORDER BY tanggal DESC
                LIMIT 1
            ");
            if ($stmt_pengambilan) {
                $stmt_pengambilan->bind_param('s', $pengiriman['no_resi']);
                $stmt_pengambilan->execute();
                $result_pengambilan = $stmt_pengambilan->get_result();
                if ($result_pengambilan->num_rows > 0) {
                    $pengambilanData = $result_pengambilan->fetch_assoc();
                }
                $stmt_pengambilan->close();
            }
            ?>

            <!-- Data Pengambilan Barang -->
            <?php
            $pengambilanData = null;
            $stmt_pengambilan = $conn->prepare("
                SELECT nama_pengambil, telp_pengambil, tanggal
                FROM pengambilan
                WHERE no_resi = ?
                ORDER BY tanggal DESC
                LIMIT 1
            ");
            if ($stmt_pengambilan) {
                $stmt_pengambilan->bind_param('s', $pengiriman['no_resi']);
                $stmt_pengambilan->execute();
                $result_pengambilan = $stmt_pengambilan->get_result();
                if ($result_pengambilan->num_rows > 0) {
                    $pengambilanData = $result_pengambilan->fetch_assoc();
                }
                $stmt_pengambilan->close();
            }
            ?>

            <div class="row g-3 mb-4 text-capitalize d-flex align-items-stretch">
                <!-- Kiri: Log Status Pengiriman -->
                <?php include '../../../components/logStatusPengiriman.php'; ?>

                <!-- Kanan: Data Pengambilan Barang -->
                <div class="col-md-6 d-flex">
                    <div class="col-12">
                        <div class="card border-0 shadow-sm ">
                            <div class="card-body p-4">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="bg-info bg-opacity-10 rounded p-2 me-3">
                                        <i class="fa-solid fa-box-open"></i>
                                    </div>
                                    <h6 class="mb-0 fw-semibold">Data Pengambilan Barang</h6>
                                </div>
                                <?php if ($pengambilanData): ?>
                                <div class="mb-3">
                                    <small class="text-muted d-block mb-1">Nama Pengambil</small>
                                    <p class="mb-0 fw-semibold"><?= htmlspecialchars($pengambilanData['nama_pengambil'] ?? '-'); ?></p>
                                </div>
                                <div class="mb-3">
                                    <small class="text-muted d-block mb-1">Nomor Telepon</small>
                                    <p class="mb-0"><?= htmlspecialchars($pengambilanData['telp_pengambil'] ?? '-'); ?></p>
                                </div>
                                <div>
                                    <small class="text-muted d-block mb-1">Tanggal Pengambilan</small>
                                    <p class="mb-0"><?= date('d/m/Y H:i', strtotime($pengambilanData['tanggal'])); ?></p>
                                </div>
                                <?php else: ?>
                                    <div class="text-muted d-flex flex-column align-items-center justify-content-center py-5">
                                        <i class="fa-solid fa-circle-info mb-2 d-block"></i>
                                        <p class="mb-0">Belum ada data pengambilan barang.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>


            <div class="d-flex justify-content-end">
                <a href="resi?id=<?= (int)$pengiriman['id']; ?>" class="btn btn-md btn-secondary" target="_blank">
                    <i class="fa-solid fa-receipt"></i>
                    Cetak Resi
                </a>
            </div>

        </div>
    </div>
  </div>

  <!-- Modal Cancel Shipment -->
  <div class="modal fade" id="cancelShipmentModal" tabindex="-1" aria-labelledby="cancelShipmentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0 shadow-lg">
        <form method="POST" action="">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">
          <input type="hidden" name="id" value="<?= (int)$pengiriman['id']; ?>">
          <input type="hidden" name="cancel_shipment" value="1">
          
          <div class="modal-header">
            <h5 class="modal-title fw-bold text-danger" id="cancelShipmentModalLabel">
              <i class="fa-solid fa-triangle-exclamation me-2"></i>Batalkan Pengiriman
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body px-4 py-3">
            <div class="alert alert-warning" role="alert">
              <i class="fa-solid fa-circle-info me-2"></i>
              <strong>Perhatian!</strong> Tindakan ini akan membatalkan pengiriman dengan No. Resi <strong><?= htmlspecialchars($pengiriman['no_resi']); ?></strong>.
            </div>
            <div class="mb-3">
              <label for="keterangan" class="form-label fw-semibold">Alasan Pembatalan <span class="text-danger">*</span></label>
              <textarea class="form-control" id="keterangan" name="keterangan" rows="3" placeholder="Masukkan alasan pembatalan..." required></textarea>
              <small class="form-text text-muted">Jelaskan alasan pembatalan pengiriman ini.</small>
            </div>
          </div>
          <div class="modal-footer border-0 pt-0">
            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
            <button type="submit" class="btn btn-danger">
              <i class="fa-solid fa-ban me-1"></i> Batalkan Pengiriman
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<?php
    include '../../../templates/footer.php';
?>