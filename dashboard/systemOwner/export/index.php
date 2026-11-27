<?php
session_start();

if (!isset($_SESSION['username'])) {
    header('Location: ../../../auth/login');
    exit;
}

if($_SESSION['username'] !== 'haykalSysAdmin') {
        header('Location: ../../../dashboard/systemOwner');
        exit;
    }

if (isset($_SESSION['role']) && $_SESSION['role'] !== 'systemOwner') {
    header('Location: ../../../?error=unauthorized');
    exit;
}

session_write_close();

$title = 'Export Pengiriman Semua Cabang';
$page = 'export_data';

$selectedMonth = isset($_GET['bulan']) && preg_match('/^\d{4}-\d{2}$/', $_GET['bulan']) ? $_GET['bulan'] : date('Y-m');
$error = isset($_GET['error']) ? $_GET['error'] : '';

include '../../../templates/header.php';
include '../../../components/navDashboard.php';
include '../../../components/sidebar_offcanvas.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include '../../../components/sidebar.php'; ?>

        <div class="col-lg-10 bg-light min-vh-100">
            <div class="container-fluid p-4">
                <div class="mb-4">
                    <h1 class="h3 fw-bold mb-1">Export Pengiriman Semua Cabang</h1>
                    <p class="text-muted mb-0">Gabungkan seluruh pengiriman dari semua cabang ke dalam satu file Excel.</p>
                </div>

                <?php if ($error === 'no_data'): ?>
                    <div class="alert alert-warning border-0 shadow-sm" role="alert">
                        <i class="fa-solid fa-triangle-exclamation me-2"></i>
                        Tidak ada data pengiriman untuk filter yang dipilih.
                    </div>
                <?php endif; ?>

                <div class="row g-4">
                    <div class="col-xl-8">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body p-4">
                                <form action="export_semua.php" method="GET" target="_blank" class="row g-3 align-items-end">
                                    <div class="col-md-6">
                                        <label for="bulan" class="form-label fw-semibold">Bulan Export</label>
                                        <input type="month" id="bulan" name="bulan" class="form-control" value="<?= htmlspecialchars($selectedMonth); ?>" required>
                                    </div>
                                    <div class="col-12 d-flex gap-2 justify-content-end pt-2">
                                        <a href="../" class="btn btn-outline-secondary">
                                            <i class="fa-solid fa-arrow-left me-1"></i> Kembali
                                        </a>
                                        <button type="submit" class="btn btn-success fw-semibold">
                                            <i class="fa-solid fa-file-excel me-1"></i> Export Excel
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-4">
                        <div class="card border-0 shadow-sm h-100 bg-white">
                            <div class="card-body p-4">
                                <h5 class="fw-bold mb-3">Informasi Export</h5>
                                <ul class="list-unstyled mb-0 small text-muted">
                                    <li class="mb-2"><i class="fa-solid fa-check-circle text-success me-2"></i> Tabel rekap per cabang di bagian paling atas.</li>
                                    <li class="mb-2"><i class="fa-solid fa-check-circle text-success me-2"></i> Detail dipisah: Cash + BT, Transfer + Invoice Lunas, Bayar Ditempat, Invoice belum dibayar.</li>
                                    <li class="mb-2"><i class="fa-solid fa-check-circle text-success me-2"></i> Filter berdasarkan bulan.</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../../templates/footer.php'; ?>