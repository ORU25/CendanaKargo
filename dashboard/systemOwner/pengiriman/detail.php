<?php
    session_start();
    if(!isset($_SESSION['username'] )|| !isset($_SESSION['user_id'])){
        header("Location: ../../../auth/login");
        exit;
    }

    if(isset($_SESSION['role']) && $_SESSION['role'] !== 'systemOwner'){
        header("Location: ../../../?error=unauthorized");
        exit;
    }
    
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    include '../../../config/database.php';
    
    // Handle update status
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            header('Location: detail?id=' . intval($_GET['id']) . '&error=update_failed');
            exit;
        }
        $id_update = (int)($_POST['id'] ?? 0);
        $status_baru = trim((string)($_POST['status'] ?? ''));
        $keterangan = trim((string)($_POST['keterangan'] ?? ''));
        
        if ($id_update > 0 && $status_baru !== '') {
            // Ambil data pengiriman
            $stmt_old = $conn->prepare('SELECT status, no_resi FROM pengiriman WHERE id = ?');
            $stmt_old->bind_param('i', $id_update);
            $stmt_old->execute();
            $result_old = $stmt_old->get_result();
            $status_lama = null;
            $no_resi = null;
            
            if ($result_old->num_rows > 0) {
                $data_old = $result_old->fetch_assoc();
                $status_lama = strtolower($data_old['status']);
                $no_resi = $data_old['no_resi'];

                // Validasi perubahan status berdasarkan status lama
                $allowed = false;
                $error_message = '';
                
                switch($status_lama) {
                    case 'bkd':
                        if (in_array($status_baru, ['dalam pengiriman', 'dibatalkan'])) {
                            $allowed = true;
                        } else {
                            $error_message = 'Status BKD hanya dapat diubah ke Dalam Pengiriman atau Dibatalkan';
                        }
                        break;
                    case 'dalam pengiriman':
                        if ($status_baru === 'sampai tujuan') {
                            $allowed = true;
                        } else {
                            $error_message = 'Status Dalam Pengiriman hanya dapat diubah ke Sampai Tujuan';
                        }
                        break;
                    case 'sampai tujuan':
                        if ($status_baru === 'pod') {
                            // Validasi data pengambil
                            $nama_pengambil = trim((string)($_POST['nama_pengambil'] ?? ''));
                            $telp_pengambil = trim((string)($_POST['telp_pengambil'] ?? ''));
                            
                            if (empty($nama_pengambil) || empty($telp_pengambil)) {
                                header('Location: detail?id=' . $id_update . '&error=pengambil_required');
                                exit;
                            }
                            $allowed = true;
                        } else {
                            $error_message = 'Status Sampai Tujuan hanya dapat diubah ke POD';
                        }
                        break;
                    case 'pod':
                        $error_message = 'Status POD tidak dapat diubah lagi';
                        break;
                    case 'dibatalkan':
                        $error_message = 'Status Dibatalkan tidak dapat diubah lagi';
                        break;
                    default:
                        $error_message = 'Status tidak valid';
                }
                
                if (!$allowed) {
                    header('Location: detail?id=' . $id_update . '&error=invalid_status_change');
                    exit;
                }
                
                if ($status_lama === $status_baru) {
                    header('Location: detail?id=' . $id_update . '&error=same_status');
                    exit;
                }
            }
            $stmt_old->close();
            
            // Update status pengiriman
            $stmt = $conn->prepare('UPDATE pengiriman SET status = ? WHERE id = ?');
            if ($stmt) {
                $stmt->bind_param('si', $status_baru, $id_update);
                if ($stmt->execute()) {
                    // Insert log perubahan status
                    $id_user_update = $_SESSION['user_id'];
                    $stmt_log = $conn->prepare('INSERT INTO log_status_pengiriman (id_pengiriman, status_lama, status_baru, keterangan, diubah_oleh) VALUES (?, ?, ?, ?, ?)');
                    if ($stmt_log) {
                        $stmt_log->bind_param('isssi', $id_update, $status_lama, $status_baru, $keterangan, $id_user_update);
                        $stmt_log->execute();
                        $stmt_log->close();
                    }
                    
                    // Jika status baru adalah POD, insert data pengambilan
                    if ($status_baru === 'pod') {
                        $nama_pengambil = trim((string)($_POST['nama_pengambil'] ?? ''));
                        $telp_pengambil = trim((string)($_POST['telp_pengambil'] ?? ''));
                        
                        
                        $stmt_pengambilan = $conn->prepare('INSERT INTO pengambilan (id_user,no_resi, nama_pengambil, telp_pengambil) VALUES (?, ?, ?, ?)');
                        if ($stmt_pengambilan) {
                            $stmt_pengambilan->bind_param('isss',$id_user_update, $no_resi, $nama_pengambil, $telp_pengambil);
                            $stmt_pengambilan->execute();
                            $stmt_pengambilan->close();
                        }
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
                if ($pengiriman['pembayaran'] === 'bayar_ditempat') {
                    $pengiriman['pembayaran'] = 'Bayar Ditempat';
                }
            }
            $stmt->close();
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
            
            // Ambil data validator invoice jika ada
            if ($pengiriman['validasi_oleh']) {
                $stmt_validator = $conn->prepare('SELECT username FROM user WHERE id = ? LIMIT 1');
                if ($stmt_validator) {
                    $stmt_validator->bind_param('i', $pengiriman['validasi_oleh']);
                    $stmt_validator->execute();
                    $result_validator = $stmt_validator->get_result();
                    if ($result_validator->num_rows > 0) {
                        $validatorData = $result_validator->fetch_assoc();
                        $pengiriman['validator_username'] = $validatorData['username'];
                    }
                    $stmt_validator->close();
                }
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
            <?php if(isset($_GET['error']) && $_GET['error'] == 'invalid_status_change'){
                $type = "danger";
                $message = "Perubahan status tidak diizinkan. Periksa aturan perubahan status.";
                include '../../../components/alert.php';}    
            ?>
            <?php if(isset($_GET['error']) && $_GET['error'] == 'pengambil_required'){
                $type = "danger";
                $message = "Nama dan nomor telepon pengambil wajib diisi untuk status POD";
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
                    <?php 
                    $current_status = strtolower($pengiriman['status']);
                    // Tampilkan tombol update status jika status belum POD atau Dibatalkan
                    if (!in_array($current_status, ['sampai tujuan','pod', 'dibatalkan'])): 
                    ?>
                    <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#updateStatusModal">
                        <i class="fa-solid fa-arrows-rotate"></i> Update Status
                    </button>
                    <?php elseif ($current_status === 'sampai tujuan'): ?>
                        <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#podStatusModal">
                            <i class="fa-solid fa-check"></i> Tandai Selesai (POD)
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
                                <div class="col-6 col-md-3">
                                    <small class="opacity-75 d-block">Metode Pembayaran</small>
                                    <strong><?= htmlspecialchars($pengiriman['pembayaran']); ?></strong>
                                </div>
                                
                                <?php if(strtolower($pengiriman['pembayaran']) === 'invoice'): ?>
                                <div class="col-6 col-md-3">
                                    <small class="opacity-75 d-block">Status Pembayaran</small>
                                    <?php if($pengiriman['status_pembayaran'] === 'Sudah Dibayar'): ?>
                                        <strong class="text-success">
                                            <i class="fas fa-check-circle me-1"></i><?= htmlspecialchars($pengiriman['status_pembayaran']); ?>
                                        </strong>
                                    <?php else: ?>
                                        <strong class="text-warning">
                                            <i class="fas fa-clock me-1"></i><?= htmlspecialchars($pengiriman['status_pembayaran'] ?? 'Belum Dibayar'); ?>
                                        </strong>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if($pengiriman['tanggal_pembayaran']): ?>
                                <div class="col-6 col-md-3">
                                    <small class="opacity-75 d-block">Tanggal Pembayaran</small>
                                    <strong><?= date('d/m/Y H:i', strtotime($pengiriman['tanggal_pembayaran'])); ?></strong>
                                </div>
                                <?php endif; ?>
                                
                                <?php if(isset($pengiriman['validator_username'])): ?>
                                <div class="col-6 col-md-3">
                                    <small class="opacity-75 d-block">Divalidasi Oleh</small>
                                    <strong><?= htmlspecialchars($pengiriman['validator_username']); ?></strong>
                                </div>
                                <?php endif; ?>
                                <?php endif; ?>
                                
                                <div class="col-6 col-md-3">
                                    <small class="opacity-75 d-block">Tarif Manual</small>
                                    <?php if($pengiriman['tarif_manual'] > 0): ?>
                                    <strong>Rp <?= number_format($pengiriman['tarif_manual'], 0, ',', '.'); ?></strong>
                                    <?php else: ?>
                                    <strong>-</strong>
                                    <?php endif; ?>
                                </div>
                                <div class="col-6 col-md-3">
                                    <small class="opacity-75 d-block">Tarif Handling</small>
                                    <?php if($pengiriman['tarif_handling'] > 0): ?>
                                    <strong>Rp <?= number_format($pengiriman['tarif_handling'], 0, ',', '.'); ?></strong>
                                    <?php else: ?>
                                    <strong>-</strong>
                                    <?php endif; ?>
                                </div>
                                <div class="col-6 col-md-3">
                                    <small class="opacity-75 d-block">Tarif Lintas Cabang</small>
                                    <?php if($pengiriman['tarif_lintas_cabang'] > 0): ?>
                                    <strong>Rp <?= number_format($pengiriman['tarif_lintas_cabang'], 0, ',', '.'); ?></strong>
                                    <?php else: ?>
                                    <strong>-</strong>
                                    <?php endif; ?>
                                </div>
                                <div class="col-6 col-md-2">
                                    <small class="opacity-75 d-block">Diskon</small>
                                    <?php if($pengiriman['diskon'] == 0): ?>
                                        <strong>-</strong>
                                    <?php else: ?>
                                        <strong><?= number_format($pengiriman['diskon'], 1); ?>%</strong>
                                    <?php endif; ?>
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

  <!-- Modal Update Status -->
  <div class="modal fade" id="updateStatusModal" tabindex="-1" aria-labelledby="updateStatusModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0 shadow-lg">
        <form method="POST" action="" >
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">
          <input type="hidden" name="id" value="<?= (int)$pengiriman['id']; ?>">
          <input type="hidden" name="update_status" value="1">
          
          <div class="modal-header border-0 pb-0">
            <h5 class="modal-title fw-bold" id="updateStatusModalLabel">Update Status Pengiriman</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body px-4 py-3">
            <div class="mb-3">
              <label class="form-label small text-muted">Status Saat Ini</label>
              <div class="p-3 bg-light rounded">
                <?php
                    $currentBadgeClass = 'secondary';
                    $current_status_lower = strtolower($pengiriman['status']);
                    switch($current_status_lower) {
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
                <?php
                  $current_status_lower = strtolower($pengiriman['status']);
                  // BKD: hanya bisa ke dalam pengiriman atau dibatalkan
                  if ($current_status_lower === 'bkd') {
                      echo '<option value="dalam pengiriman">Dalam Pengiriman</option>';
                      echo '<option value="dibatalkan">Dibatalkan</option>';
                  }
                  // Dalam Pengiriman: hanya bisa ke sampai tujuan
                  elseif ($current_status_lower === 'dalam pengiriman') {
                      echo '<option value="sampai tujuan">Sampai Tujuan</option>';
                  }
                ?>
              </select>
              <small class="form-text text-muted">Pilih status baru sesuai alur pengiriman.</small>
            </div>            
            <div class="mb-3">
              <label for="keterangan" class="form-label fw-semibold">Keterangan</label>
              <textarea class="form-control" id="keterangan" name="keterangan" rows="3" placeholder="Tambahkan catatan atau keterangan..." required></textarea>
            </div>
          </div>
          <div class="modal-footer border-0 pt-0">
            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
            <button type="submit" class="btn btn-warning">
              <i class="fa-solid fa-save me-1"></i> Simpan Perubahan
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Modal Konfirmasi POD -->
<div class="modal fade" id="podStatusModal" tabindex="-1" aria-labelledby="updateStatusModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">
        <input type="hidden" name="id" value="<?= (int)$pengiriman['id']; ?>">
        <input type="hidden" name="status" value="pod">
        <input type="hidden" name="update_status" value="1">

        <div class="modal-header border-0 pb-0">
          <h5 class="modal-title fw-bold">Konfirmasi Pengambilan</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
          <p>Isi data berikut untuk konfirmasi pengambilan barang:</p>

          <div class="mb-3">
            <label class="form-label fw-semibold">Nama Pengambil <span class="text-danger">*</span></label>
            <input type="text" name="nama_pengambil" class="form-control" required placeholder="Masukkan nama pengambil">
          </div>

          <div class="mb-3">
            <label class="form-label fw-semibold">Nomor Telepon <span class="text-danger">*</span></label>
            <input type="tel" name="telp_pengambil" class="form-control" 
                   required pattern="^(\+62|0)[0-9]{9,14}$"
                   title="Nomor telepon harus diawali +62 atau 0 dan terdiri dari 10–15 digit angka"
                   placeholder="contoh: 081234567890">
          </div>
        </div>

        <div class="modal-footer border-0 pt-0">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-success">Simpan & Tandai POD</button>
        </div>
      </form>
    </div>
  </div>
</div>


</div>

<?php
    include '../../../templates/footer.php';
?>