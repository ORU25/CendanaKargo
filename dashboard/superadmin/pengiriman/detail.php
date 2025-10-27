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
        
            // Handle update status
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
            if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
                header('Location: detail?id=' . intval($_GET['id']) . '&error=update_failed');
                exit;
            }
            $id_update = (int)($_POST['id'] ?? 0);
            $status_baru = trim((string)($_POST['status'] ?? ''));

            if ($pengiriman['status'] === $status_baru) {
                header('Location: detail?id=' . $id_update . '&error=same_status');
                exit;
            }

            $keterangan = trim((string)($_POST['keterangan'] ?? ''));
            if ($id_update > 0 && $status_baru !== '') {
                $stmt = $conn->prepare('UPDATE pengiriman SET status = ? WHERE id = ?');
                if ($stmt) {
                    $stmt->bind_param('si', $status_baru, $id_update);
                    if ($stmt->execute()) {
                        // Insert log perubahan status
                        $id_user_update = $_SESSION['user_id'] ?? null;
                        $status_lama = $pengiriman['status'];
                        $stmt_log = $conn->prepare('INSERT INTO log_status_pengiriman (id_pengiriman, status_lama, status_baru, keterangan, diubah_oleh) VALUES (?, ?, ?, ?, ?)');
                        if ($stmt_log) {
                            $stmt_log->bind_param('isssi', $id_update, $status_lama, $status_baru, $keterangan, $id_user_update);
                            $stmt_log->execute();
                            $stmt_log->close();
                        }
                        header('Location: detail?id=' . $id_update . '&success=updated');
                        exit;
                    }
                    $stmt->close();
                }
            }
            header('Location: detail?error=update_failed');
            exit;
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
            <?php if(isset($_GET['success']) && $_GET['success'] == 'updated'){
                $type = "success";
                $message = "Status pengiriman berhasil diperbarui";
                include '../../../components/alert.php';}
            ?>
            <?php if(isset($_GET['error']) && $_GET['error'] == 'same_status'){
                $type = "danger";
                $message = "Status baru tidak boleh sama dengan status lama";
                include '../../../components/alert.php';}    
            ?>
            <?php if(isset($_GET['error']) && $_GET['error'] == 'update_failed'){
                $type = "danger";
                $message = "Gagal memperbarui status pengiriman";
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
                    <?php if($pengiriman['status'] == 'bkd'): ?>
                    <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#updateStatusModal">
                        Update Status
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

            <!-- Timeline Log Perubahan Status -->
            <?php include '../../../components/logStatusPengiriman.php'; ?>

            <div class="d-flex justify-content-end">
                <a href="resi?id=<?= (int)$pengiriman['id']; ?>" class="btn btn-md btn-secondary" target="_blank">
                    <i class="fa-solid fa-receipt"></i>
                    Cetak Resi
                </a>
            </div>

        </div>
    </div>
  </div>

  <!-- Modal Update Status -->
  <div class="modal fade" id="updateStatusModal" tabindex="-1" aria-labelledby="updateStatusModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0 shadow-lg">
        <form method="POST" action="">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">
          <input type="hidden" name="id" value="<?= (int)$pengiriman['id']; ?>">
          <input type="hidden" name="update_status" value="1">
          
          <div class="modal-header border-0 pb-0">
            <h5 class="modal-title fw-bold" id="updateStatusModalLabel">Update Status</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body px-4 py-3">
            <div class="mb-3">
              <label class="form-label small text-muted">Status Saat Ini</label>
              <div class="p-3 bg-light rounded">
                <?php
                    $currentBadgeClass = 'secondary';
                    switch(strtolower($pengiriman['status'])) {
                        case 'bkd':
                            $currentBadgeClass = 'warning';
                            break;
                        case 'dalam pengiriman':
                            $currentBadgeClass = 'primary';
                            break;
                        case 'sampai tujuan':
                            $currentBadgeClass = 'info';
                            break;
                        case 'pod':
                            $currentBadgeClass = 'success';
                            break;
                        case 'dibatalkan':
                            $currentBadgeClass = 'danger';
                            break;
                    }
                ?>
                <span class="text-uppercase badge bg-<?= $currentBadgeClass; ?>"><?= htmlspecialchars($pengiriman['status']); ?></span>
              </div>
            </div>
            <div class="mb-3">
                <label for="status" class="form-label fw-semibold">Status Baru <span class="text-danger">*</span></label>
                <select class="form-select form-select-lg" id="status" name="status" required>
                    <option value="">-- Pilih Status --</option>
                    <option value="bkd">Booked (BKD)</option>
                    <option value="dibatalkan">Dibatalkan</option>
                </select>
                <small class="form-text text-muted">Pilih status baru untuk tracking pengiriman.</small>
                <div class="mb-3">
                    <label for="keterangan" class="form-label fw-semibold">Keterangan (Opsional)</label>
                    <textarea class="form-control" id="keterangan" name="keterangan" rows="3" placeholder="Tambahkan catatan atau keterangan..."></textarea>
                </div>
            </div>
          </div>
          <div class="modal-footer border-0 pt-0">
            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
            <button type="submit" class="btn btn-warning">Simpan</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<?php
    include '../../../templates/footer.php';
?>