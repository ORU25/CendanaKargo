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

    include '../../../config/database.php';
    
    $title = "Pelunasan Invoice - Cendana Kargo";
    
    // Get all branches untuk filter
    $sqlCabang = "SELECT * FROM kantor_cabang ORDER BY nama_cabang ASC";
    $resultCabang = $conn->query($sqlCabang);
    $cabangs = $resultCabang->num_rows > 0 ? $resultCabang->fetch_all(MYSQLI_ASSOC) : [];
    
    // Filter parameters
    $filter_cabang = isset($_GET['cabang']) ? trim($_GET['cabang']) : '';
    $filter_bulan = isset($_GET['bulan']) ? trim($_GET['bulan']) : date('Y-m'); // Default bulan ini
    
    // Pagination settings
    $limit = 15; // Data per halaman
    $page_num = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? (int)$_GET['page'] : 1;
    $offset = ($page_num - 1) * $limit;
    
    // Build WHERE clause untuk invoice belum dibayar
    $where_conditions = [
        "pembayaran = 'invoice'",
        "status_pembayaran = 'Belum Dibayar'",
        "status != 'dibatalkan'"
    ];
    
    // Add filter cabang
    if (!empty($filter_cabang)) {
        $where_conditions[] = "cabang_pengirim = '" . $conn->real_escape_string($filter_cabang) . "'";
    }
    
    // Add filter bulan/tahun
    if (!empty($filter_bulan)) {
        $where_conditions[] = "DATE_FORMAT(tanggal, '%Y-%m') = '" . $conn->real_escape_string($filter_bulan) . "'";
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    // Get total records
    $sql_count = "SELECT COUNT(*) as total FROM pengiriman WHERE " . $where_clause;
    $result = $conn->query($sql_count);
    $total_records = $result->fetch_assoc()['total'];
    
    $total_pages = ceil($total_records / $limit);
    
    // Get paginated data
    $sql_data = "SELECT * FROM pengiriman WHERE " . $where_clause . " ORDER BY tanggal DESC, id DESC LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($sql_data);
    $stmt->bind_param('ii', $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    $pengirimans = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    // Hitung total invoice belum dibayar
    $sql_total = "SELECT SUM(total_tarif) as total_invoice FROM pengiriman WHERE " . $where_clause;
    $result_total = $conn->query($sql_total);
    $total_invoice = $result_total->fetch_assoc()['total_invoice'] ?? 0;
?>

<?php
    $page = "pelunasan";
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
            <?php if(isset($_GET['success']) && $_GET['success'] == 'validated'){
                $type = "success";
                $message = "Invoice berhasil divalidasi sebagai Sudah Dibayar";
                include '../../../components/alert.php';
            }?>
            <?php if(isset($_GET['success']) && $_GET['success'] == 'batch_validated'){
                $count = isset($_GET['count']) ? (int)$_GET['count'] : 0;
                $type = "success";
                $message = "Berhasil melunasi " . $count . " invoice sekaligus!";
                include '../../../components/alert.php';
            }?>
            <?php if(isset($_GET['error']) && $_GET['error'] == 'not_found'){
                $type = "danger";
                $message = "Invoice tidak ditemukan";
                include '../../../components/alert.php';
            }?>
            <?php if(isset($_GET['error']) && $_GET['error'] == 'batch_failed'){
                $type = "danger";
                $message = "Gagal melakukan pelunasan batch. Silakan coba lagi.";
                include '../../../components/alert.php';
            }?>
            <?php if(isset($_GET['error']) && $_GET['error'] == 'missing_cabang'){
                $type = "danger";
                $message = "Cabang harus dipilih untuk pelunasan batch";
                include '../../../components/alert.php';
            }?>
            <?php if(isset($_GET['error']) && $_GET['error'] == 'missing_bulan'){
                $type = "danger";
                $message = "Filter bulan harus dipilih untuk pelunasan batch";
                include '../../../components/alert.php';
            }?>

            <!-- Header -->
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h4 mb-1 fw-bold">
                        Pelunasan Invoice
                    </h1>
                    <p class="text-muted small mb-0">
                        Menampilkan <?= count($pengirimans); ?> dari <?= $total_records; ?> invoice belum dibayar
                        <?php if ($total_pages > 1): ?>
                            (Halaman <?= $page_num; ?> dari <?= $total_pages; ?>)
                        <?php endif; ?>
                    </p>
                </div>
                <div class="mt-2 mt-md-0 d-flex flex-column gap-2 align-items-end">
                    <div class="alert alert-warning mb-0 py-2 px-3">
                        <strong>Total Hutang: </strong>
                        <span class="fs-5">Rp <?= number_format($total_invoice, 0, ',', '.'); ?></span>
                    </div>
                    <?php if ($total_records > 0 && !empty($filter_cabang)): ?>
                    <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#batchValidateModal">
                        <i class="fa-solid fa-check-double"></i>
                        Lunasi Semua Invoice (<?= $total_records; ?>)
                    </button>
                    <?php elseif ($total_records > 0 && empty($filter_cabang)): ?>
                    <small class="text-muted fst-italic">
                        <i class="fa-solid fa-info-circle"></i>
                        Pilih cabang untuk pelunasan batch
                    </small>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Filter Card -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-3">
                    <form method="GET" action="" class="row g-3 align-items-end">
                        <div class="col-md-5">
                            <label class="form-label fw-semibold small mb-1">Cabang</label>
                            <select name="cabang" class="form-select">
                                <option value="">Semua Cabang</option>
                                <?php foreach($cabangs as $c): ?>
                                    <option value="<?= htmlspecialchars($c['nama_cabang']); ?>" 
                                            <?= $filter_cabang === $c['nama_cabang'] ? 'selected' : ''; ?>>
                                        <?= htmlspecialchars($c['nama_cabang']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label fw-semibold small mb-1">Bulan & Tahun</label>
                            <input type="month" name="bulan" class="form-control" value="<?= htmlspecialchars($filter_bulan); ?>">
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fa-solid fa-filter"></i>
                                Filter
                            </button>
                        </div>
                        <?php if ($filter_cabang || $filter_bulan != date('Y-m')): ?>
                        <div class="col-12">
                            <a href="./" class="btn btn-sm btn-outline-secondary">
                                <i class="fa-solid fa-rotate-left"></i>
                                Reset Filter
                            </a>
                        </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <!-- Table Card -->
            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="px-4">No Resi</th>
                                    <th>Cabang</th>
                                    <th>Pengirim</th>
                                    <th>Penerima</th>
                                    <th>Barang</th>
                                    <th class="text-end">Total Invoice</th>
                                    <th>Tanggal</th>
                                    <th>Status Kirim</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($pengirimans)): ?>
                                    <tr>
                                        <td colspan="9" class="text-center py-5 text-muted">
                                            <i class="fa-solid fa-file-invoice"></i>
                                            <p class="mb-0 mt-2">Tidak ada invoice yang belum dibayar
                                            <?php if ($filter_cabang || $filter_bulan != date('Y-m')): ?>
                                                <br><small>dengan filter yang dipilih</small>
                                            <?php endif; ?>
                                            </p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($pengirimans as $p): 
                                        // Tentukan warna badge status
                                        $badgeClass = 'secondary';
                                        switch(strtolower($p['status'])) {
                                            case 'bkd': $badgeClass = 'warning'; break;
                                            case 'dalam pengiriman': $badgeClass = 'primary'; break;
                                            case 'sampai tujuan': $badgeClass = 'info'; break;
                                            case 'pod': $badgeClass = 'success'; break;
                                            case 'dibatalkan': $badgeClass = 'danger'; break;
                                        }
                                    ?>
                                    <tr>
                                        <td class="px-4">
                                            <span class="badge bg-dark"><?= htmlspecialchars($p['no_resi']); ?></span>
                                        </td>
                                        <td class="fw-semibold small"><?= htmlspecialchars($p['cabang_pengirim']); ?></td>
                                        <td class="small">
                                            <div><?= htmlspecialchars($p['nama_pengirim']); ?></div>
                                            <small class="text-muted"><?= htmlspecialchars($p['telp_pengirim']); ?></small>
                                        </td>
                                        <td class="small">
                                            <div><?= htmlspecialchars($p['nama_penerima']); ?></div>
                                            <small class="text-muted"><?= htmlspecialchars($p['cabang_penerima']); ?></small>
                                        </td>
                                        <td class="small"><?= htmlspecialchars($p['nama_barang']); ?></td>
                                        <td class="text-end">
                                            <span class="fw-bold text-warning">Rp <?= number_format($p['total_tarif'], 0, ',', '.'); ?></span>
                                        </td>
                                        <td class="small"><?= date('d/m/Y', strtotime($p['tanggal'])); ?></td>
                                        <td>
                                            <span class="text-uppercase badge text-bg-<?= $badgeClass; ?> small">
                                                <?= htmlspecialchars($p['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="d-flex justify-content-between align-items-center mt-4">
                <div class="text-muted small">
                    Menampilkan <?= $offset + 1; ?> - <?= min($offset + $limit, $total_records); ?> dari <?= $total_records; ?> invoice
                </div>
                <nav aria-label="Navigasi halaman">
                    <ul class="pagination pagination-sm mb-0">
                        <?php 
                        // Build query string untuk pagination
                        $query_string = '';
                        if ($filter_cabang) $query_string .= '&cabang=' . urlencode($filter_cabang);
                        if ($filter_bulan) $query_string .= '&bulan=' . urlencode($filter_bulan);
                        ?>
                        
                        <!-- Previous Button -->
                        <li class="page-item <?= $page_num <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?= $page_num - 1; ?><?= $query_string; ?>" aria-label="Previous">
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        </li>

                        <?php
                        // Pagination logic: show first, last, current, and nearby pages
                        $range = 2; // Pages to show around current page
                        
                        for ($i = 1; $i <= $total_pages; $i++) {
                            // Always show first page, last page, current page, and pages within range
                            if ($i == 1 || $i == $total_pages || ($i >= $page_num - $range && $i <= $page_num + $range)) {
                                ?>
                                <li class="page-item <?= $i == $page_num ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?= $i; ?><?= $query_string; ?>"><?= $i; ?></a>
                                </li>
                                <?php
                            } elseif ($i == $page_num - $range - 1 || $i == $page_num + $range + 1) {
                                // Show ellipsis for gaps
                                ?>
                                <li class="page-item disabled">
                                    <span class="page-link">...</span>
                                </li>
                                <?php
                            }
                        }
                        ?>

                        <!-- Next Button -->
                        <li class="page-item <?= $page_num >= $total_pages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?= $page_num + 1; ?><?= $query_string; ?>" aria-label="Next">
                                <span aria-hidden="true">&raquo;</span>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>

        </div>
    </div>
  </div>

</div>
<!-- Modal Konfirmasi Pelunasan Batch -->
<div class="modal fade" id="batchValidateModal" tabindex="-1" aria-labelledby="batchValidateModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="batch_validate.php">
        <input type="hidden" name="cabang" value="<?= htmlspecialchars($filter_cabang); ?>">
        <input type="hidden" name="bulan" value="<?= htmlspecialchars($filter_bulan); ?>">
        
        <div class="modal-header bg-success text-white">
          <h5 class="modal-title" id="batchValidateModalLabel">
            <i class="fa-solid fa-check-double me-2"></i>
            Konfirmasi Pelunasan
          </h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        
        <div class="modal-body">
          <p class="mb-3">Anda akan melunasi <strong class="text-primary"><?= $total_records; ?> invoice</strong> dengan total <strong class="text-danger">Rp <?= number_format($total_invoice, 0, ',', '.'); ?></strong></p>
          
          <div class="mb-2">
            <strong>Cabang:</strong> <?= htmlspecialchars($filter_cabang); ?>
          </div>
          
          <?php if ($filter_bulan): 
              $bulan_arr = explode('-', $filter_bulan);
              $months = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
              $bulan_text = $months[(int)$bulan_arr[1]] . ' ' . $bulan_arr[0];
          ?>
          <div class="mb-3">
            <strong>Periode:</strong> <?= $bulan_text; ?>
          </div>
          <?php endif; ?>
          
          <div class="alert alert-warning small mb-0">
            <i class="fa-solid fa-exclamation-triangle me-1"></i>
            Semua invoice akan ditandai sebagai <strong>SUDAH DIBAYAR</strong>
          </div>
        </div>
        
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-success">
            <i class="fa-solid fa-check me-1"></i>
            Ya, Lunasi
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php
    include '../../../templates/footer.php';
?>