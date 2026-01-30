<?php
session_start();
if(!isset($_SESSION['username']) || !isset($_SESSION['user_id'])){
    header("Location: ../../../auth/login");
    exit;
}

if(isset($_SESSION['role']) && $_SESSION['role'] !== 'superAdmin'){
    header("Location: ../../../?error=unauthorized");
    exit;
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

include '../../../config/database.php';

$title = "Scanner Barcode - Cendana Kargo";

$cabang_admin = $_SESSION['cabang'] ?? '';
if (empty($cabang_admin)) {
    die("Cabang admin tidak ditemukan di session. Pastikan diset saat login.");
}

$scanned_data = null;
$scan_error = null;
$scan_success = null;
$scan_warning = null;

// Process status update (confirm arrival)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $scan_error = 'Token tidak valid. Silakan coba lagi.';
    } else {
        $resi_id = (int)($_POST['id'] ?? 0);
        $status_baru = trim((string)($_POST['status'] ?? ''));
        $keterangan = trim((string)($_POST['keterangan'] ?? ''));
        
        if ($resi_id > 0 && $status_baru !== '') {
            // Get old status
            $stmt_old = $conn->prepare('SELECT status, no_resi FROM pengiriman WHERE id = ?');
            $stmt_old->bind_param('i', $resi_id);
            $stmt_old->execute();
            $result_old = $stmt_old->get_result();
            
            if ($result_old->num_rows > 0) {
                $data_old = $result_old->fetch_assoc();
                $status_lama = $data_old['status'];
                $no_resi = $data_old['no_resi'];
                
                // Update status
                $stmt = $conn->prepare('UPDATE pengiriman SET status = ? WHERE id = ?');
                $stmt->bind_param('si', $status_baru, $resi_id);
                
                if ($stmt->execute()) {
                    // Insert log
                    $id_user_update = $_SESSION['user_id'] ?? null;
                    $stmt_log = $conn->prepare('INSERT INTO log_status_pengiriman (id_pengiriman, status_lama, status_baru, keterangan, diubah_oleh) VALUES (?, ?, ?, ?, ?)');
                    if ($stmt_log) {
                        $stmt_log->bind_param('isssi', $resi_id, $status_lama, $status_baru, $keterangan, $id_user_update);
                        $stmt_log->execute();
                        $stmt_log->close();
                    }
                    
                    $scan_success = "Status pengiriman <strong>" . htmlspecialchars($no_resi) . "</strong> berhasil diupdate menjadi <strong>Sampai Tujuan</strong>";
                } else {
                    $scan_error = "Gagal mengupdate status pengiriman";
                }
                $stmt->close();
            }
            $stmt_old->close();
        }
    }
}

// Process barcode scan
if (isset($_POST['barcode']) && !empty(trim($_POST['barcode'])) && !isset($_POST['update_status'])) {
    $barcode = trim($_POST['barcode']);
    
    // Search for the shipment by resi number
    $stmt = $conn->prepare("
        SELECT * FROM pengiriman 
        WHERE no_resi = ? 
        AND cabang_penerima = ?
        LIMIT 1
    ");
    $stmt->bind_param('ss', $barcode, $cabang_admin);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $scanned_data = $result->fetch_assoc();
        
        // Cek apakah status sudah "Sampai Tujuan"
        if (strtolower($scanned_data['status']) === 'sampai tujuan') {
            $scan_warning = "Pengiriman dengan No. Resi <strong>" . htmlspecialchars($barcode) . "</strong> sudah berstatus <strong>Sampai Tujuan</strong>.";
            $scanned_data = null; // Jangan tampilkan data
        }elseif (strtolower($scanned_data['status']) === 'pod') {
            $scan_warning = "Pengiriman dengan No. Resi <strong>" . htmlspecialchars($barcode) . "</strong> sudah berstatus <strong>POD</strong>.";
            $scanned_data = null; // Jangan tampilkan data
        }elseif (strtolower($scanned_data['status']) === 'bkd') {
            $scan_warning = "Pengiriman dengan No. Resi <strong>" . htmlspecialchars($barcode) . "</strong> berstatus <strong>BKD</strong>. Silakan proses BKD terlebih dahulu.";
            $scanned_data = null; // Jangan tampilkan data
        }
    } else {
        $scan_error = "Data dengan No. Resi <strong>" . htmlspecialchars($barcode) . "</strong> tidak ditemukan di cabang " . htmlspecialchars($cabang_admin);
    }
    $stmt->close();
}

$page = "barang_masuk";
include '../../../templates/header.php';
include '../../../components/navDashboard.php';
include '../../../components/sidebar_offcanvas.php';
?>

<style>
    .scanner-section {
        position: sticky;
        top: 1rem;
    }
    
    .barcode-input {
        font-size: 1.25rem;
        padding: 0.875rem 1rem;
        text-align: center;
        letter-spacing: 2px;
        font-family: 'Courier New', monospace;
        font-weight: bold;
        border: 2px solid #dee2e6;
        transition: all 0.3s ease;
    }
    
    .barcode-input:focus {
        border-color: #667eea;
        box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
    }
    
    .scanner-header {

        border-radius: 12px;
        padding: 1.5rem;
    }
    
    .scanner-header .icon-circle {
        width: 60px;
        height: 60px;
        background: rgba(255,255,255,0.2);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .scanner-header .icon-circle i {
        font-size: 1.75rem;
    }
    
    .result-card {
        border-left: 4px solid #0d6efd;
        transition: all 0.3s ease;
    }
    
    .result-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.1) !important;
    }
    
    .info-label {
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #6c757d;
        margin-bottom: 0.15rem;
    }
    
    .info-value {
        font-weight: 600;
        color: #212529;
        font-size: 0.9rem;
    }
    
    .scan-animation {
        animation: pulse 2s infinite;
    }
    
    @keyframes pulse {
        0% { box-shadow: 0 0 0 0 rgba(102, 126, 234, 0.4); }
        70% { box-shadow: 0 0 0 10px rgba(102, 126, 234, 0); }
        100% { box-shadow: 0 0 0 0 rgba(102, 126, 234, 0); }
    }
    
    .empty-result {
        min-height: 400px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        color: #adb5bd;
    }
    
    .empty-result i {
        font-size: 4rem;
        margin-bottom: 1rem;
        opacity: 0.5;
    }
    
    .tips-list {
        font-size: 0.85rem;
    }
    
    .tips-list li {
        margin-bottom: 0.5rem;
    }
    
    .resi-display {
        font-family: 'Courier New', monospace;
        font-size: 1.25rem;
        letter-spacing: 1px;
    }
</style>

<div class="container-fluid">
    <div class="row">
        <?php include '../../../components/sidebar.php'; ?>

        <div class="col-lg-10 bg-light">
            <div class="container-fluid p-4">

                <!-- Header -->
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
                    <div>
                        <h1 class="h4 mb-0 fw-bold">
                            <i class="fa-solid fa-barcode me-2"></i>Scanner Barcode
                        </h1>
                    </div>
                    <div class="mt-2 mt-md-0">
                        <a href="./" class="btn btn-outline-secondary">
                            <i class="fa-solid fa-arrow-left"></i> Kembali
                        </a>
                    </div>
                </div>

                <!-- Main Content - Left Right Layout -->
                <div class="row g-4">
                    
                    <!-- LEFT SIDE - Scanner Section -->
                    <div class="col-lg-4">
                        <div class="scanner-section">
                            <div class="card border-0 shadow-sm">
                                <div class="card-body p-4">
                                    <!-- Scanner Header -->
                                    <div class="scanner-header text-center mb-4">
                                        <div class="icon-circle mx-auto mb-3">
                                            <i class="fa-solid fa-barcode"></i>
                                        </div>
                                        <h5 class="mb-1">Scan Barcode 1D</h5>
                                        <p class="mb-0 opacity-75 small">Arahkan scanner ke barcode</p>
                                    </div>
                                    
                                    <!-- Scanner Form -->
                                    <form method="POST" action="" id="scannerForm">
                                        <div class="mb-3">
                                            <label for="barcode" class="form-label fw-semibold small">
                                                <i class="fa-solid fa-hashtag me-1"></i>Nomor Resi
                                            </label>
                                            <input 
                                                type="text" 
                                                class="form-control barcode-input scan-animation" 
                                                id="barcode" 
                                                name="barcode" 
                                                placeholder="Scan barcode..."
                                                autocomplete="off"
                                                autofocus
                                                required
                                            >
                                        </div>
                                        <div class="d-grid">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fa-solid fa-search me-2"></i>Cari Data
                                            </button>
                                        </div>
                                    </form>
                                    
                                    <!-- Tips -->
                                    <div class="mt-4 pt-3 border-top">
                                        <h6 class="fw-bold small mb-2">
                                            <i class="fa-solid fa-lightbulb me-1 text-warning"></i>Tips
                                        </h6>
                                        <ul class="tips-list mb-0 ps-3 text-muted">
                                            <li>Beri jarak minimal 20cm</li>
                                            <li>Bisa ketik manual jika tidak berhasil</li>
                                            <li>Hanya cabang <strong><?= htmlspecialchars($cabang_admin); ?></strong></li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- RIGHT SIDE - Result Section -->
                    <div class="col-lg-8">
                        
                        <!-- Success Message -->
                        <?php if ($scan_success): ?>
                        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                            <i class="fa-solid fa-check-circle me-2"></i><?= $scan_success; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Warning Message -->
                        <?php if ($scan_warning): ?>
                        <div class="alert alert-warning alert-dismissible fade show mb-4" role="alert">
                            <i class="fa-solid fa-exclamation-triangle me-2"></i><?= $scan_warning; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Error Message -->
                        <?php if ($scan_error): ?>
                        <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                            <i class="fa-solid fa-exclamation-circle me-2"></i><?= $scan_error; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($scanned_data): ?>
                        <!-- Scan Result Card -->
                        <div class="card border-0 shadow-sm result-card">
                            <div class="card-header bg-white py-3">
                                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                                    <div class="d-flex align-items-center">
                                        <h6 class="mb-0 fw-bold">
                                            <i class="fa-solid fa-box me-2 text-primary"></i>Data Pengiriman
                                        </h6>
                                        <?php
                                        $badgeClass = 'secondary';
                                        switch(strtolower($scanned_data['status'])) {
                                            case 'bkd': $badgeClass = 'warning'; break;
                                            case 'dalam pengiriman': $badgeClass = 'primary'; break;
                                            case 'sampai tujuan': $badgeClass = 'success'; break;
                                            case 'pod': $badgeClass = 'info'; break;
                                        }
                                        ?>
                                        <span class="badge bg-<?= $badgeClass; ?> text-uppercase ms-2"><?= htmlspecialchars($scanned_data['status']); ?></span>
                                    </div>
                                    
                                    <div class="d-flex gap-2">
                                        <?php if (strtolower($scanned_data['status']) === 'dalam pengiriman'): ?>
                                        <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#updateStatusModal">
                                            <i class="fa-solid fa-check-circle me-1"></i>Konfirmasi Sampai
                                        </button>
                                        <?php endif; ?>
                                        <a href="detail?id=<?= (int)$scanned_data['id']; ?>" class="btn btn-outline-primary btn-sm">
                                            <i class="fa-solid fa-eye me-1"></i>Detail
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body p-4 text-capitalize">
                                
                                <!-- Resi & Total -->
                                <div class="row mb-4 pb-3 border-bottom">
                                    <div class="col-md-6">
                                        <div class="info-label">No. Resi</div>
                                        <div class="resi-display fw-bold text-dark"><?= htmlspecialchars($scanned_data['no_resi']); ?></div>
                                    </div>
                                    <div class="col-md-6 text-md-end mt-2 mt-md-0">
                                        <div class="info-label">Total Tarif</div>
                                        <div class="h4 mb-0 fw-bold text-success">Rp <?= number_format($scanned_data['total_tarif'], 0, ',', '.'); ?></div>
                                    </div>
                                </div>

                                <div class="row g-4">
                                    <!-- Barang Info -->
                                    <div class="col-md-6">
                                        <div class="p-3 bg-light rounded h-100">
                                            <h6 class="fw-bold mb-3 text-primary small">
                                                <i class="fa-solid fa-cube me-2"></i>Info Barang
                                            </h6>
                                            <div class="mb-2">
                                                <div class="info-label">Nama Barang</div>
                                                <div class="info-value"><?= htmlspecialchars($scanned_data['nama_barang']); ?></div>
                                            </div>
                                            <div class="row">
                                                <div class="col-6">
                                                    <div class="info-label">Berat</div>
                                                    <div class="info-value"><?= number_format($scanned_data['berat'] ?? 0, 1); ?> kg</div>
                                                </div>
                                                <div class="col-6">
                                                    <div class="info-label">Jumlah</div>
                                                    <div class="info-value"><?= (int)($scanned_data['jumlah'] ?? 1); ?> item</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Pengirim Info -->
                                    <div class="col-md-6">
                                        <div class="p-3 bg-light rounded h-100">
                                            <h6 class="fw-bold mb-3 text-warning small">
                                                <i class="fa-solid fa-user me-2"></i>Pengirim
                                            </h6>
                                            <div class="mb-2">
                                                <div class="info-label">Nama</div>
                                                <div class="info-value"><?= htmlspecialchars($scanned_data['nama_pengirim']); ?></div>
                                            </div>
                                            <div class="mb-2">
                                                <div class="info-label">Telepon</div>
                                                <div class="info-value"><?= htmlspecialchars($scanned_data['telp_pengirim'] ?? '-'); ?></div>
                                            </div>
                                            <div>
                                                <div class="info-label">Cabang Asal</div>
                                                <span class="badge bg-primary"><?= htmlspecialchars($scanned_data['cabang_pengirim']); ?></span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Penerima Info -->
                                    <div class="col-md-6">
                                        <div class="p-3 bg-light rounded h-100">
                                            <h6 class="fw-bold mb-3 text-info small">
                                                <i class="fa-solid fa-user-check me-2"></i>Penerima
                                            </h6>
                                            <div class="mb-2">
                                                <div class="info-label">Nama</div>
                                                <div class="info-value"><?= htmlspecialchars($scanned_data['nama_penerima']); ?></div>
                                            </div>
                                            <div class="mb-2">
                                                <div class="info-label">Telepon</div>
                                                <div class="info-value"><?= htmlspecialchars($scanned_data['telp_penerima'] ?? '-'); ?></div>
                                            </div>
                                            <div>
                                                <div class="info-label">Cabang Tujuan</div>
                                                <span class="badge bg-success"><?= htmlspecialchars($scanned_data['cabang_penerima']); ?></span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Tanggal Info -->
                                    <div class="col-md-6">
                                        <div class="p-3 bg-light rounded h-100">
                                            <h6 class="fw-bold mb-3 text-secondary small">
                                                <i class="fa-solid fa-calendar me-2"></i>Info Lainnya
                                            </h6>
                                            <div class="mb-2">
                                                <div class="info-label">Tanggal Kirim</div>
                                                <div class="info-value"><?= date('d F Y', strtotime($scanned_data['tanggal'])); ?></div>
                                            </div>
                                            <div class="mb-2">
                                                <div class="info-label">Pembayaran</div>
                                                <div class="info-value"><?= htmlspecialchars($scanned_data['pembayaran'] ?? '-'); ?></div>
                                            </div>
                                            <div>
                                                <div class="info-label">Keterangan</div>
                                                <div class="info-value"><?= htmlspecialchars($scanned_data['keterangan'] ?? '-'); ?></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <?php else: ?>
                        <!-- Empty State -->
                        <div class="card border-0 shadow-sm">
                            <div class="card-body empty-result">
                                <i class="fa-solid fa-qrcode"></i>
                                <h5 class="text-muted mb-2">Belum Ada Data</h5>
                                <p class="text-muted mb-0">Scan barcode untuk menampilkan data pengiriman</p>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                    </div>
                    
                </div>

            </div>
        </div>
    </div>
</div>

<!-- Modal Update Status -->
<?php if ($scanned_data && strtolower($scanned_data['status']) === 'dalam pengiriman'): ?>
<div class="modal fade" id="updateStatusModal" tabindex="-1" aria-labelledby="updateStatusModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="id" value="<?= (int)$scanned_data['id']; ?>">
                <input type="hidden" name="update_status" value="1">
                <input type="hidden" name="barcode" value="<?= htmlspecialchars($scanned_data['no_resi']); ?>">
                
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold" id="updateStatusModalLabel">
                        <i class="fa-solid fa-check-circle text-success me-2"></i>Konfirmasi Sampai Tujuan
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body px-4 py-3">
                    <div class="mb-3">
                        <label class="form-label small text-muted">No. Resi</label>
                        <div class="p-3 bg-light rounded">
                            <span class="fw-bold font-monospace"><?= htmlspecialchars($scanned_data['no_resi']); ?></span>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small text-muted">Status Saat Ini</label>
                        <div class="p-3 bg-light rounded">
                            <span class="badge bg-primary text-uppercase"><?= htmlspecialchars($scanned_data['status']); ?></span>
                            <i class="fa-solid fa-arrow-right mx-2 text-muted"></i>
                            <span class="badge bg-info text-uppercase">Sampai Tujuan</span>
                        </div>
                    </div>
                    <div class="mb-3">
                        <input type="hidden" name="status" value="sampai tujuan">
                    </div>
                    <div class="mb-3">
                        <label for="keterangan" class="form-label fw-semibold">Keterangan (Opsional)</label>
                        <textarea class="form-control" id="keterangan" name="keterangan" rows="3" placeholder="Tambahkan catatan atau keterangan..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fa-solid fa-check me-1"></i>Konfirmasi
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const barcodeInput = document.getElementById('barcode');
    
    // Keep focus on barcode input
    barcodeInput.focus();
    
    // Refocus when clicking anywhere except interactive elements
    document.addEventListener('click', function(e) {
        if (!e.target.closest('a, button, .btn, input, textarea, .modal')) {
            barcodeInput.focus();
        }
    });
    
    // Clear input untuk scan berikutnya setelah hasil muncul
    <?php if ($scanned_data): ?>
    barcodeInput.value = '';
    <?php endif; ?>
});
</script>

<?php include '../../../templates/footer.php'; ?>
