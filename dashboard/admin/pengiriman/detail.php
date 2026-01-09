<?php
    session_start();
    if(!isset($_SESSION['username'] )|| !isset($_SESSION['user_id'])){
        header("Location: ../../../auth/login");
        exit;
    }

    if(isset($_SESSION['role']) && $_SESSION['role'] !== 'admin'){
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
                if ($pengiriman['pembayaran'] === 'bayar_ditempat') {
                    $pengiriman['pembayaran'] = 'Bayar Ditempat';
                }
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
            <?php if(isset($_GET['success']) && $_GET['success'] == 'updated'): ?>
                <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                    <strong>✓ Berhasil!</strong> Status pengiriman berhasil diperbarui.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if(isset($_GET['error']) && $_GET['error'] == 'update_failed'): ?>
                <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                    <strong>✗ Gagal!</strong> Tidak dapat memperbarui status pengiriman.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Header -->
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h4 mb-1 fw-bold">Detail Pengiriman</h1>
                    <p class="text-muted small mb-0">Dibuat oleh:  <span class="fw-semibold"><?= htmlspecialchars($pengiriman['user']); ?></span></p>
                    <p class="text-muted small mb-0">No. Resi: <span class="fw-semibold"><?= htmlspecialchars($pengiriman['no_resi']); ?></span></p>
                </div>
                <div class="d-flex gap-2 mt-2 mt-md-0">
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
                                    <small class="opacity-75 d-block">Tarif Lintas Tujuan</small>
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
</div>

<?php
    include '../../../templates/footer.php';
?>