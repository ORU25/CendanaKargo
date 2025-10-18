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
    
    // Handle update status
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
        $id_update = (int)($_POST['id'] ?? 0);
        $status_baru = trim((string)($_POST['status'] ?? ''));
        if ($id_update > 0 && $status_baru !== '') {
            $stmt = $conn->prepare('UPDATE pengiriman SET status = ? WHERE id = ?');
            if ($stmt) {
                $stmt->bind_param('si', $status_baru, $id_update);
                if ($stmt->execute()) {
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
            }
            $stmt->close();
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
    <div class="col-lg-2 d-none d-lg-block bg-light border-end vh-100">
      <div class="list-group list-group-flush">
        <a href="<?= BASE_URL; ?>dashboard/superadmin/" class="list-group-item list-group-item-action">Dashboard</a>
        <a href="<?= BASE_URL; ?>dashboard/superadmin/pengiriman/" class="list-group-item list-group-item-action fw-bold text-danger">Pengiriman</a>
        <a href="<?= BASE_URL; ?>dashboard/superadmin/surat_jalan/" class="list-group-item list-group-item-action">Surat Jalan</a>
        <a href="<?= BASE_URL; ?>dashboard/superadmin/tarif/" class="list-group-item list-group-item-action">Tarif</a>
        <a href="<?= BASE_URL; ?>dashboard/superadmin/kantor_cabang/" class="list-group-item list-group-item-action">Kantor Cabang</a>
        <a href="<?= BASE_URL; ?>dashboard/superadmin/user/" class="list-group-item list-group-item-action">User</a>
      </div>
        <a href="<?= BASE_URL; ?>auth/logout.php" class="btn btn-outline-danger mt-3">Logout</a>
    </div>

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
                    <p class="text-muted small mb-0">No. Resi: <span class="fw-semibold"><?= htmlspecialchars($pengiriman['no_resi']); ?></span></p>
                </div>
                <div class="d-flex gap-2 mt-2 mt-md-0">
                    <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#updateStatusModal">
                        Update Status
                    </button>
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
                                <div class="col-6 col-md-3">
                                    <small class="opacity-75 d-block">Berat</small>
                                    <strong><?= number_format($pengiriman['berat'], 1); ?> kg</strong>
                                </div>
                                <div class="col-6 col-md-3">
                                    <small class="opacity-75 d-block">Jumlah</small>
                                    <strong><?= (int)$pengiriman['jumlah']; ?> item</strong>
                                </div>
                                <div class="col-6 col-md-3">
                                    <small class="opacity-75 d-block">Jasa</small>
                                    <strong><?= htmlspecialchars($pengiriman['jasa_pengiriman']); ?></strong>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mt-3 mt-md-0 text-md-end">
                            <small class="opacity-75 d-block mb-2">Total Tarif</small>
                            <h3 class="fw-bold mb-2">Rp <?= number_format($pengiriman['total_tarif'], 0, ',', '.'); ?></h3>
                            <?php
                                $badgeClass = 'secondary';
                                switch(strtolower($pengiriman['status'])) {
                                    case 'dalam proses':
                                        $badgeClass = 'warning';
                                        break;
                                    case 'dalam pengiriman':
                                        $badgeClass = 'primary';
                                        break;
                                    case 'sampai tujuan':
                                        $badgeClass = 'info';
                                        break;
                                    case 'selesai':
                                        $badgeClass = 'success';
                                        break;
                                    case 'dibatalkan':
                                        $badgeClass = 'danger';
                                        break;
                                }
                            ?>
                            <span class="px-3 py-2 badge rounded-pill text-bg-<?= $badgeClass; ?>"><?= htmlspecialchars($pengiriman['status']); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Detail Cards -->
            <div class="row g-3 text-capitalize">
                <!-- Pengirim -->
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center mb-3">
                                <div class="bg-primary bg-opacity-10 rounded p-2 me-3">
                                    <svg width="20" height="20" fill="currentColor" class="text-primary">
                                        <path d="M8 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6zm2-3a2 2 0 1 1-4 0 2 2 0 0 1 4 0zm4 8c0 1-1 1-1 1H3s-1 0-1-1 1-4 6-4 6 3 6 4zm-1-.004c-.001-.246-.154-.986-.832-1.664C11.516 10.68 10.289 10 8 10c-2.29 0-3.516.68-4.168 1.332-.678.678-.83 1.418-.832 1.664h10z"/>
                                    </svg>
                                </div>
                                <h6 class="mb-0 fw-semibold">Data Pengirim</h6>
                            </div>
                            <div class="mb-3">
                                <small class="text-muted d-block mb-1">Nama</small>
                                <p class="mb-0 fw-semibold"><?= htmlspecialchars($pengiriman['nama_pengirim']); ?></p>
                            </div>
                            <div>
                                <small class="text-muted d-block mb-1">Telepon</small>
                                <p class="mb-0"><?= htmlspecialchars($pengiriman['telp_pengirim'] ?? '-'); ?></p>
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
                                    <svg width="20" height="20" fill="currentColor" class="text-success">
                                        <path d="M15 14s1 0 1-1-1-4-5-4-5 3-5 4 1 1 1 1h8zm-7.978-1A.261.261 0 0 1 7 12.996c.001-.264.167-1.03.76-1.72C8.312 10.629 9.282 10 11 10c1.717 0 2.687.63 3.24 1.276.593.69.758 1.457.76 1.72l-.008.002a.274.274 0 0 1-.014.002H7.022zM11 7a2 2 0 1 0 0-4 2 2 0 0 0 0 4zm3-2a3 3 0 1 1-6 0 3 3 0 0 1 6 0z"/>
                                    </svg>
                                </div>
                                <h6 class="mb-0 fw-semibold">Data Penerima</h6>
                            </div>
                            <div class="mb-3">
                                <small class="text-muted d-block mb-1">Nama</small>
                                <p class="mb-0 fw-semibold"><?= htmlspecialchars($pengiriman['nama_penerima']); ?></p>
                            </div>
                            <div>
                                <small class="text-muted d-block mb-1">Telepon</small>
                                <p class="mb-0"><?= htmlspecialchars($pengiriman['telp_penerima'] ?? '-'); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
  </div>

  <!-- Modal Update Status -->
  <div class="modal fade" id="updateStatusModal" tabindex="-1" aria-labelledby="updateStatusModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0 shadow-lg">
        <form method="POST" action="">
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
                        case 'dalam proses':
                            $currentBadgeClass = 'warning';
                            break;
                        case 'dalam pengiriman':
                            $currentBadgeClass = 'primary';
                            break;
                        case 'sampai tujuan':
                            $currentBadgeClass = 'info';
                            break;
                        case 'selesai':
                            $currentBadgeClass = 'success';
                            break;
                        case 'dibatalkan':
                            $currentBadgeClass = 'danger';
                            break;
                    }
                ?>
                <span class="badge bg-<?= $currentBadgeClass; ?>"><?= htmlspecialchars($pengiriman['status']); ?></span>
              </div>
            </div>
            <div class="mb-3">
              <label for="status" class="form-label fw-semibold">Status Baru <span class="text-danger">*</span></label>
              <select class="form-select form-select-lg" id="status" name="status" required>
                <option value="">-- Pilih Status --</option>
                <option value="dalam proses">Dalam Proses</option>
                <option value="dalam pengiriman">Dalam Pengiriman</option>
                <option value="sampai tujuan">Sampai Tujuan</option>
                <option value="selesai">Selesai</option>
                <option value="dibatalkan">Dibatalkan</option>
              </select>
              <small class="form-text text-muted">Pilih status baru untuk tracking pengiriman.</small>
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