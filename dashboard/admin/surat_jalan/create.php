<?php
session_start();
if(!isset($_SESSION['username']) || !isset($_SESSION['user_id'])){
    header("Location: ../../../auth/login.php");
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

$cabang_user = $_SESSION['cabang'];
$id_cabang_user = $_SESSION['id_cabang'];

// Handle pembuatan surat jalan baru dengan POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_surat_jalan'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        header("Location: index.php?error=invalid_token");
        exit;
    }
    
    $cabang_asal = $cabang_user; // Gunakan cabang dari user yang login
    $cabang_tujuan = trim($_POST['tujuan'] ?? '');
    
    if (empty($cabang_asal) || empty($cabang_tujuan)) {
        header("Location: index.php?error=cabang_required");
        exit;
    }
    
    // Validasi bahwa cabang asal adalah cabang user (untuk keamanan)
    $stmt_validate = $conn->prepare("SELECT id, kode_cabang, nama_cabang FROM Kantor_cabang WHERE id = ?");
    $stmt_validate->bind_param('i', $id_cabang_user);
    $stmt_validate->execute();
    $result_validate = $stmt_validate->get_result();
    
    if ($result_validate->num_rows === 0) {
        header("Location: index.php?error=invalid_cabang");
        exit;
    }
    
    $cabang_asal_data = $result_validate->fetch_assoc();
    $stmt_validate->close();
    
    // Validasi cabang tujuan
    $stmt_tujuan = $conn->prepare("SELECT id, kode_cabang, nama_cabang FROM Kantor_cabang WHERE kode_cabang = ?");
    $stmt_tujuan->bind_param('s', $cabang_tujuan);
    $stmt_tujuan->execute();
    $result_tujuan = $stmt_tujuan->get_result();
    
    if ($result_tujuan->num_rows === 0) {
        header("Location: index.php?error=invalid_cabang");
        exit;
    }
    
    $cabang_tujuan_data = $result_tujuan->fetch_assoc();
    $stmt_tujuan->close();
    
    // Buat surat jalan baru dengan nomor otomatis
    $prefix = strtoupper($cabang_asal_data['kode_cabang']) . strtoupper($cabang_tujuan_data['kode_cabang']);
    
    $stmt_last = $conn->prepare("
        SELECT no_surat_jalan 
        FROM Surat_jalan 
        WHERE no_surat_jalan LIKE ?
        ORDER BY id DESC 
        LIMIT 1
    ");
    $search_pattern = $prefix . '%';
    $stmt_last->bind_param('s', $search_pattern);
    $stmt_last->execute();
    $result_last = $stmt_last->get_result();
    
    $next_number = 1;
    if ($result_last->num_rows > 0) {
        $last_row = $result_last->fetch_assoc();
        $last_no = $last_row['no_surat_jalan'];
        $last_number = (int)preg_replace('/[^0-9]/', '', substr($last_no, strlen($prefix)));
        $next_number = $last_number + 1;
    }
    $stmt_last->close();
    
    $no_surat_jalan = $prefix . $next_number;
    $username = $_SESSION['username'];
    
    $stmt_insert = $conn->prepare("
        INSERT INTO Surat_jalan 
        (id_user, id_cabang_pengirim, id_cabang_penerima, no_surat_jalan, user, cabang_pengirim, cabang_penerima, driver, status) 
        VALUES (?, ?, ?, ?, ?, ?, ?, '', 'draft')
    ");
    $stmt_insert->bind_param(
        'iiissss',
        $_SESSION['user_id'],
        $cabang_asal_data['id'],
        $cabang_tujuan_data['id'],
        $no_surat_jalan,
        $username,
        $cabang_asal_data['nama_cabang'],
        $cabang_tujuan_data['nama_cabang']
    );
    
    if ($stmt_insert->execute()) {
        $id_surat_jalan = $conn->insert_id;
        $stmt_insert->close();
        header("Location: create.php?id=$id_surat_jalan&success=created");
        exit;
    } else {
        $stmt_insert->close();
        header("Location: index.php?error=create_failed");
        exit;
    }
}

// Ambil ID surat jalan dari URL
$id_surat_jalan = isset($_GET['id']) ? (int)$_GET['id'] : null;

if (!$id_surat_jalan) {
    header("Location: index.php?error=no_id");
    exit;
}

// Ambil data surat jalan (filter berdasarkan cabang)
$stmt_sj = $conn->prepare("SELECT * FROM Surat_jalan WHERE id = ? AND status = 'draft' AND id_cabang_pengirim = ?");
$stmt_sj->bind_param('ii', $id_surat_jalan, $id_cabang_user);
$stmt_sj->execute();
$result_sj = $stmt_sj->get_result();

if ($result_sj->num_rows === 0) {
    $stmt_sj->close();
    header("Location: index.php?error=not_found");
    exit;
}

$surat_jalan = $result_sj->fetch_assoc();
$stmt_sj->close();

// Ambil data cabang
$stmt_cabang = $conn->prepare("SELECT id, kode_cabang, nama_cabang FROM Kantor_cabang WHERE id IN (?, ?)");
$stmt_cabang->bind_param('ii', $surat_jalan['id_cabang_pengirim'], $surat_jalan['id_cabang_penerima']);
$stmt_cabang->execute();
$result_cabang = $stmt_cabang->get_result();

$cabangs = [];
while ($row = $result_cabang->fetch_assoc()) {
    $cabangs[$row['id']] = $row;
}
$stmt_cabang->close();

$cabang_asal_data = $cabangs[$surat_jalan['id_cabang_pengirim']] ?? null;
$cabang_tujuan_data = $cabangs[$surat_jalan['id_cabang_penerima']] ?? null;

if (!$cabang_asal_data || !$cabang_tujuan_data) {
    header("Location: index.php?error=invalid_cabang");
    exit;
}

// Handle tambah resi ke surat jalan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_resi'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        header("Location: create.php?id=$id_surat_jalan&error=invalid_token");
        exit;
    }
    
    $id_pengiriman = (int)($_POST['id_pengiriman'] ?? 0);
    
    if ($id_pengiriman > 0) {
        // Cek jumlah resi yang sudah ditambahkan
        $stmt_count = $conn->prepare("SELECT COUNT(*) as total FROM detail_surat_jalan WHERE id_surat_jalan = ?");
        $stmt_count->bind_param('i', $id_surat_jalan);
        $stmt_count->execute();
        $result_count = $stmt_count->get_result();
        $count_data = $result_count->fetch_assoc();
        $stmt_count->close();
        
        if ($count_data['total'] >= 15) {
            header("Location: create.php?id=$id_surat_jalan&error=max_limit");
            exit;
        }
        
        // Cek apakah resi sudah ada di surat jalan ini
        $stmt_check_resi = $conn->prepare("
            SELECT id FROM detail_surat_jalan 
            WHERE id_surat_jalan = ? AND id_pengiriman = ?
        ");
        $stmt_check_resi->bind_param('ii', $id_surat_jalan, $id_pengiriman);
        $stmt_check_resi->execute();
        $result_check_resi = $stmt_check_resi->get_result();
        
        if ($result_check_resi->num_rows > 0) {
            header("Location: create.php?id=$id_surat_jalan&error=already_added");
            exit;
        }
        $stmt_check_resi->close();
        
        // Tambahkan resi ke surat jalan
        $stmt_add = $conn->prepare("INSERT INTO detail_surat_jalan (id_surat_jalan, id_pengiriman) VALUES (?, ?)");
        $stmt_add->bind_param('ii', $id_surat_jalan, $id_pengiriman);
        
        if ($stmt_add->execute()) {
            header("Location: create.php?id=$id_surat_jalan&success=added");
            exit;
        }
        $stmt_add->close();
    }
    
    header("Location: create.php?id=$id_surat_jalan&error=add_failed");
    exit;
}

// Handle hapus resi dari surat jalan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_resi'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        header("Location: create.php?id=$id_surat_jalan&error=invalid_token");
        exit;
    }
    
    $id_detail = (int)($_POST['id_detail'] ?? 0);
    
    if ($id_detail > 0) {
        $stmt_remove = $conn->prepare("DELETE FROM detail_surat_jalan WHERE id = ? AND id_surat_jalan = ?");
        $stmt_remove->bind_param('ii', $id_detail, $id_surat_jalan);
        
        if ($stmt_remove->execute()) {
            header("Location: create.php?id=$id_surat_jalan&success=removed");
            exit;
        }
        $stmt_remove->close();
    }
    
    header("Location: create.php?id=$id_surat_jalan&error=remove_failed");
    exit;
}

// Handle berangkatkan surat jalan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['berangkatkan_surat_jalan'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        header("Location: create.php?id=$id_surat_jalan&error=invalid_token");
        exit;
    }
    
    $driver = trim($_POST['driver'] ?? '');
    
    if (empty($driver)) {
        header("Location: create.php?id=$id_surat_jalan&error=driver_required");
        exit;
    }
    
    // Cek jumlah resi yang sudah ditambahkan
    $stmt_count = $conn->prepare("SELECT COUNT(*) as total FROM detail_surat_jalan WHERE id_surat_jalan = ?");
    $stmt_count->bind_param('i', $id_surat_jalan);
    $stmt_count->execute();
    $result_count = $stmt_count->get_result();
    $count_data = $result_count->fetch_assoc();
    $stmt_count->close();
    
    if ($count_data['total'] == 0) {
        header("Location: create.php?id=$id_surat_jalan&error=no_resi");
        exit;
    }
    
    // Mulai transaksi
    $conn->begin_transaction();
    
    try {
        // Update surat jalan: set driver dan ubah status jadi 'diberangkatkan'
        $stmt_update_sj = $conn->prepare("UPDATE Surat_jalan SET driver = ?, status = 'diberangkatkan' WHERE id = ?");
        $stmt_update_sj->bind_param('si', $driver, $id_surat_jalan);
        $stmt_update_sj->execute();
        $stmt_update_sj->close();
        
        // Ambil semua ID pengiriman dari surat jalan ini
        $stmt_get_resi = $conn->prepare("SELECT id_pengiriman FROM detail_surat_jalan WHERE id_surat_jalan = ?");
        $stmt_get_resi->bind_param('i', $id_surat_jalan);
        $stmt_get_resi->execute();
        $result_resi = $stmt_get_resi->get_result();
        
        $pengiriman_ids = [];
        while ($row = $result_resi->fetch_assoc()) {
            $pengiriman_ids[] = $row['id_pengiriman'];
        }
        $stmt_get_resi->close();
        
        // Update status semua pengiriman menjadi 'dalam pengiriman'
        if (!empty($pengiriman_ids)) {
            $placeholders = str_repeat('?,', count($pengiriman_ids) - 1) . '?';
            $stmt_update_pengiriman = $conn->prepare("UPDATE Pengiriman SET status = 'dalam pengiriman' WHERE id IN ($placeholders)");
            $stmt_update_pengiriman->bind_param(str_repeat('i', count($pengiriman_ids)), ...$pengiriman_ids);
            $stmt_update_pengiriman->execute();
            $stmt_update_pengiriman->close();
            
            // Catat log status pengiriman untuk setiap resi
            foreach ($pengiriman_ids as $id_pengiriman) {
                $stmt_log = $conn->prepare("
                    INSERT INTO log_status_pengiriman 
                    (id_pengiriman, status_lama, status_baru, keterangan, diubah_oleh) 
                    VALUES (?, 'bkd', 'dalam pengiriman', 'Surat jalan diberangkatkan', ?)
                ");
                $stmt_log->bind_param('ii', $id_pengiriman, $_SESSION['user_id']);
                $stmt_log->execute();
                $stmt_log->close();
            }
        }
        
        // Commit transaksi
        $conn->commit();
        
        header("Location: detail.php?id=$id_surat_jalan&success=diberangkatkan");
        exit;
        
    } catch (Exception $e) {
        // Rollback jika terjadi error
        $conn->rollback();
        header("Location: create.php?id=$id_surat_jalan&error=berangkatkan_failed");
        exit;
    }
}

// Ambil resi yang sudah ditambahkan
$stmt_added = $conn->prepare("
    SELECT dsj.id as detail_id, p.* 
    FROM detail_surat_jalan dsj
    JOIN Pengiriman p ON dsj.id_pengiriman = p.id
    WHERE dsj.id_surat_jalan = ?
    ORDER BY dsj.id DESC
");
$stmt_added->bind_param('i', $id_surat_jalan);
$stmt_added->execute();
$result_added = $stmt_added->get_result();
$resi_added = [];
while ($row = $result_added->fetch_assoc()) {
    $resi_added[] = $row;
}
$stmt_added->close();

// Hitung jumlah resi yang sudah ditambahkan
$jumlah_resi = count($resi_added);

// Pagination dan Search untuk resi yang tersedia
$search = $_GET['search'] ?? '';
$pagePengiriman = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 10;
$offset = ($pagePengiriman - 1) * $limit;

// Ambil semua ID pengiriman yang sudah ada di SEMUA draft surat jalan (bukan hanya surat jalan ini)
$stmt_all_draft = $conn->prepare("
    SELECT DISTINCT dsj.id_pengiriman 
    FROM detail_surat_jalan dsj
    JOIN Surat_jalan sj ON dsj.id_surat_jalan = sj.id
    WHERE sj.status = 'draft'
");
$stmt_all_draft->execute();
$result_all_draft = $stmt_all_draft->get_result();
$all_draft_ids = [];
while ($row = $result_all_draft->fetch_assoc()) {
    $all_draft_ids[] = $row['id_pengiriman'];
}
$stmt_all_draft->close();

$excluded_ids_str = !empty($all_draft_ids) ? implode(',', $all_draft_ids) : '0';

// Query untuk menghitung total resi yang tersedia
$count_query = "
    SELECT COUNT(*) as total
    FROM Pengiriman p
    WHERE p.cabang_pengirim = ? 
    AND p.cabang_penerima = ? 
    AND p.status = 'bkd'
    AND p.id NOT IN ($excluded_ids_str)
";

if (!empty($search)) {
    $count_query .= " AND (p.no_resi LIKE ? OR p.nama_pengirim LIKE ? OR p.nama_penerima LIKE ?)";
}

$stmt_count = $conn->prepare($count_query);

if (!empty($search)) {
    $search_param = "%$search%";
    $stmt_count->bind_param('sssss', $cabang_asal_data['nama_cabang'], $cabang_tujuan_data['nama_cabang'], $search_param, $search_param, $search_param);
} else {
    $stmt_count->bind_param('ss', $cabang_asal_data['nama_cabang'], $cabang_tujuan_data['nama_cabang']);
}

$stmt_count->execute();
$result_count = $stmt_count->get_result();
$total_data = $result_count->fetch_assoc()['total'];
$stmt_count->close();

$total_pages = ceil($total_data / $limit);

// Query untuk mengambil resi yang tersedia
$query = "
    SELECT p.*
    FROM Pengiriman p
    WHERE p.cabang_pengirim = ? 
    AND p.cabang_penerima = ? 
    AND p.status = 'bkd'
    AND p.id NOT IN ($excluded_ids_str)
";

if (!empty($search)) {
    $query .= " AND (p.no_resi LIKE ? OR p.nama_pengirim LIKE ? OR p.nama_penerima LIKE ?)";
}

$query .= " ORDER BY p.tanggal DESC LIMIT ? OFFSET ?";

$stmt_available = $conn->prepare($query);

if (!empty($search)) {
    $search_param = "%$search%";
    $stmt_available->bind_param('sssssii', $cabang_asal_data['nama_cabang'], $cabang_tujuan_data['nama_cabang'], $search_param, $search_param, $search_param, $limit, $offset);
} else {
    $stmt_available->bind_param('ssii', $cabang_asal_data['nama_cabang'], $cabang_tujuan_data['nama_cabang'], $limit, $offset);
}

$stmt_available->execute();
$result_available = $stmt_available->get_result();
$resi_available = [];
while ($row = $result_available->fetch_assoc()) {
    $resi_available[] = $row;
}
$stmt_available->close();

$page = "surat_jalan";
$title = "Tambah Surat Jalan - Cendana Kargo";
?>

<?php include '../../../templates/header.php'; ?>
<?php include '../../../components/navDashboard.php'; ?>
<?php include '../../../components/sidebar_offcanvas.php'; ?>

<div class="container-fluid">
    <div class="row">
        <?php include '../../../components/sidebar.php'; ?>
        
        <div class="col-lg-10 col-12 p-4 bg-light">
            <div class="container-fluid">

                <!-- Alerts -->
                <?php if(isset($_GET['success']) && $_GET['success'] == 'created'){
                    $type = "success";
                    $message = "Surat jalan baru berhasil dibuat! Silakan tambahkan resi pengiriman.";
                    include '../../../components/alert.php';
                }?>
                <?php if(isset($_GET['info']) && $_GET['info'] == 'existing_draft'){
                    $type = "info";
                    $message = "Anda memiliki draft yang belum selesai untuk rute ini. Melanjutkan draft sebelumnya.";
                    include '../../../components/alert.php';
                }?>
                <?php if(isset($_GET['success']) && $_GET['success'] == 'added'){
                    $type = "success";
                    $message = "Resi berhasil ditambahkan ke surat jalan";
                    include '../../../components/alert.php';
                }?>
                <?php if(isset($_GET['success']) && $_GET['success'] == 'removed'){
                    $type = "success";
                    $message = "Resi berhasil dihapus dari surat jalan";
                    include '../../../components/alert.php';
                }?>
                <?php if(isset($_GET['error']) && $_GET['error'] == 'max_limit'){
                    $type = "danger";
                    $message = "Maksimal 15 resi per surat jalan";
                    include '../../../components/alert.php';
                }?>
                <?php if(isset($_GET['error']) && $_GET['error'] == 'already_added'){
                    $type = "warning";
                    $message = "Resi sudah ditambahkan ke surat jalan ini";
                    include '../../../components/alert.php';
                }?>
                <?php if(isset($_GET['error']) && $_GET['error'] == 'add_failed'){
                    $type = "danger";
                    $message = "Gagal menambahkan resi ke surat jalan";
                    include '../../../components/alert.php';
                }?>
                <?php if(isset($_GET['error']) && $_GET['error'] == 'remove_failed'){
                    $type = "danger";
                    $message = "Gagal menghapus resi dari surat jalan";
                    include '../../../components/alert.php';
                }?>
                <?php if(isset($_GET['error']) && $_GET['error'] == 'driver_required'){
                    $type = "danger";
                    $message = "Nama driver harus diisi";
                    include '../../../components/alert.php';
                }?>
                <?php if(isset($_GET['error']) && $_GET['error'] == 'no_resi'){
                    $type = "danger";
                    $message = "Tidak dapat memberangkatkan surat jalan tanpa resi";
                    include '../../../components/alert.php';
                }?>
                <?php if(isset($_GET['error']) && $_GET['error'] == 'berangkatkan_failed'){
                    $type = "danger";
                    $message = "Gagal memberangkatkan surat jalan";
                    include '../../../components/alert.php';
                }?>

                <!-- Header -->
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
                    <div>
                        <h1 class="h4 mb-1 fw-bold">Buat Surat Jalan</h1>
                        <p class="text-muted small mb-0">No. Surat Jalan: <span class="fw-semibold"><?= htmlspecialchars($surat_jalan['no_surat_jalan']); ?></span></p>
                    </div>
                    <div class="d-flex gap-2 mt-2 mt-md-0">
                        <a href="index.php" class="btn btn-sm btn-outline-secondary">
                            <i class="fa-solid fa-arrow-left me-1"></i> Kembali
                        </a>
                        <?php if ($jumlah_resi > 0): ?>
                        <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#modalBerangkatkan">
                            <i class="fa-solid fa-truck me-1"></i> Berangkatkan
                        </button>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Info Card -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body p-4">
                        <div class="row">
                            <div class="col-md-3">
                                <small class="text-muted d-block mb-1">Cabang Asal</small>
                                <p class="mb-0 fw-semibold"><?= htmlspecialchars($cabang_asal_data['nama_cabang']); ?></p>
                                <small class="text-muted"><?= htmlspecialchars($cabang_asal_data['kode_cabang']); ?></small>
                            </div>
                            <div class="col-md-3">
                                <small class="text-muted d-block mb-1">Cabang Tujuan</small>
                                <p class="mb-0 fw-semibold"><?= htmlspecialchars($cabang_tujuan_data['nama_cabang']); ?></p>
                                <small class="text-muted"><?= htmlspecialchars($cabang_tujuan_data['kode_cabang']); ?></small>
                            </div>
                            <div class="col-md-3">
                                <small class="text-muted d-block mb-1">Status</small>
                                <span class="badge bg-secondary">Draft</span>
                            </div>
                            <div class="col-md-3">
                                <small class="text-muted d-block mb-1">Resi Ditambahkan</small>
                                <p class="mb-0 fw-bold">
                                    <span class="<?= $jumlah_resi >= 15 ? 'text-danger' : 'text-success'; ?>">
                                        <?= $jumlah_resi; ?>
                                    </span> / 15
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-4">
                    <!-- Resi yang Sudah Ditambahkan -->
                    <div class="col-lg-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-white border-bottom py-3">
                                <h6 class="mb-0 fw-bold">
                                    <i class="fa-solid fa-check-circle text-success me-2"></i>
                                    Resi Terpilih (<?= $jumlah_resi; ?>)
                                </h6>
                            </div>
                            <div class="card-body p-0" style="max-height: 600px; overflow-y: auto;">
                                <?php if (!empty($resi_added)): ?>
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($resi_added as $resi): ?>
                                            <div class="list-group-item">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div class="flex-grow-1">
                                                        <h6 class="mb-1 fw-semibold"><?= htmlspecialchars($resi['no_resi']); ?></h6>
                                                        <p class="mb-1 small text-muted">
                                                            <i class="fa-solid fa-user me-1"></i>
                                                            <?= htmlspecialchars($resi['nama_pengirim']); ?> → <?= htmlspecialchars($resi['nama_penerima']); ?>
                                                        </p>
                                                        <p class="mb-0 small">
                                                            <i class="fa-solid fa-box me-1"></i>
                                                            <?= htmlspecialchars($resi['nama_barang']); ?> (<?= number_format($resi['berat'], 1); ?> kg)
                                                        </p>
                                                    </div>
                                                    <form method="POST" class="ms-2" onsubmit="return confirm('Hapus resi ini dari surat jalan?');">
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                        <input type="hidden" name="id_detail" value="<?= $resi['detail_id']; ?>">
                                                        <button type="submit" name="remove_resi" class="btn btn-sm btn-outline-danger">
                                                            <i class="fa-solid fa-times"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center text-muted py-5">
                                        <i class="fa-solid fa-inbox fa-3x mb-3 opacity-50"></i>
                                        <p class="mb-0">Belum ada resi yang ditambahkan</p>
                                        <small>Pilih resi dari daftar sebelah kanan</small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Resi yang Tersedia -->
                    <div class="col-lg-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-white border-bottom py-3">
                                <h6 class="mb-0 fw-bold">
                                    <i class="fa-solid fa-list text-primary me-2"></i>
                                    Resi Tersedia
                                </h6>
                            </div>
                            <div class="card-body p-3">
                                <!-- Search Form -->
                                <form method="GET" class="mb-3">
                                    <input type="hidden" name="id" value="<?= $id_surat_jalan; ?>">
                                    <div class="input-group">
                                        <input type="text" class="form-control" name="search" placeholder="Cari no resi, pengirim, atau penerima..." value="<?= htmlspecialchars($search); ?>">
                                        <button class="btn btn-primary" type="submit">
                                            <i class="fa-solid fa-search"></i>
                                        </button>
                                        <?php if (!empty($search)): ?>
                                            <a href="create.php?id=<?= $id_surat_jalan; ?>" class="btn btn-outline-secondary">
                                                <i class="fa-solid fa-times"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </form>

                                <div style="max-height: 450px; overflow-y: auto;">
                                    <?php if (!empty($resi_available)): ?>
                                        <div class="list-group">
                                            <?php foreach ($resi_available as $resi): ?>
                                                <div class="list-group-item">
                                                    <div class="d-flex justify-content-between align-items-start">
                                                        <div class="flex-grow-1">
                                                            <h6 class="mb-1 fw-semibold"><?= htmlspecialchars($resi['no_resi']); ?></h6>
                                                            <p class="mb-1 small text-muted">
                                                                <i class="fa-solid fa-user me-1"></i>
                                                                <?= htmlspecialchars($resi['nama_pengirim']); ?> → <?= htmlspecialchars($resi['nama_penerima']); ?>
                                                            </p>
                                                            <p class="mb-0 small">
                                                                <i class="fa-solid fa-box me-1"></i>
                                                                <?= htmlspecialchars($resi['nama_barang']); ?> (<?= number_format($resi['berat'], 1); ?> kg)
                                                            </p>
                                                            <small class="text-muted"><?= date('d/m/Y', strtotime($resi['tanggal'])); ?></small>
                                                        </div>
                                                        <form method="POST" class="ms-2">
                                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                            <input type="hidden" name="id_pengiriman" value="<?= $resi['id']; ?>">
                                                            <button 
                                                                type="submit" 
                                                                name="add_resi" 
                                                                class="btn btn-sm btn-success"
                                                                <?= $jumlah_resi >= 15 ? 'disabled' : ''; ?>
                                                            >
                                                                <i class="fa-solid fa-plus"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php elseif (!empty($search)): ?>
                                        <div class="text-center text-muted py-5">
                                            <i class="fa-solid fa-search fa-3x mb-3 opacity-50"></i>
                                            <p class="mb-0">Tidak ada resi yang cocok</p>
                                            <small>Coba kata kunci lain</small>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-center text-muted py-5">
                                            <i class="fa-solid fa-inbox fa-3x mb-3 opacity-50"></i>
                                            <p class="mb-0">Tidak ada resi tersedia</p>
                                            <small>Semua resi sudah ditambahkan atau tidak ada resi BKD</small>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Pagination -->
                                <?php if ($total_pages > 1): ?>
                                    <nav class="mt-3">
                                        <ul class="pagination pagination-sm justify-content-center mb-0">
                                            <?php if ($pagePengiriman > 1): ?>
                                                <li class="page-item">
                                                    <a class="page-link" href="?id=<?= $id_surat_jalan; ?>&search=<?= htmlspecialchars($search); ?>&page=<?= $pagePengiriman - 1; ?>">
                                                        <i class="fa-solid fa-chevron-left"></i>
                                                    </a>
                                                </li>
                                            <?php endif; ?>

                                            <?php
                                            $start = max(1, $pagePengiriman - 2);
                                            $end = min($total_pages, $pagePengiriman + 2);
                                            
                                            for ($i = $start; $i <= $end; $i++):
                                            ?>
                                                <li class="page-item <?= $i == $pagePengiriman ? 'active' : ''; ?>">
                                                    <a class="page-link" href="?id=<?= $id_surat_jalan; ?>&search=<?= htmlspecialchars($search); ?>&page=<?= $i; ?>">
                                                        <?= $i; ?>
                                                    </a>
                                                </li>
                                            <?php endfor; ?>

                                            <?php if ($pagePengiriman < $total_pages): ?>
                                                <li class="page-item">
                                                    <a class="page-link" href="?id=<?= $id_surat_jalan; ?>&search=<?= htmlspecialchars($search); ?>&page=<?= $pagePengiriman + 1; ?>">
                                                        <i class="fa-solid fa-chevron-right"></i>
                                                    </a>
                                                </li>
                                            <?php endif; ?>
                                        </ul>
                                    </nav>
                                    <p class="text-center text-muted small mb-0 mt-2">
                                        Halaman <?= $pagePengiriman; ?> dari <?= $total_pages; ?> (Total: <?= $total_data; ?> resi)
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<!-- Modal Berangkatkan -->
<div class="modal fade" id="modalBerangkatkan" tabindex="-1" aria-labelledby="modalBerangkatkanLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalBerangkatkanLabel">
                    <i class="fa-solid fa-truck me-2"></i>Berangkatkan Surat Jalan
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <div class="alert alert-info mb-3">
                        <i class="fa-solid fa-info-circle me-2"></i>
                        <small>
                            Surat jalan akan diberangkatkan dengan <strong><?= $jumlah_resi; ?> resi</strong>. 
                            Status surat jalan akan berubah menjadi <strong>Diberangkatkan</strong> 
                            dan semua resi akan berstatus <strong>Dalam Pengiriman</strong>.
                        </small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="driver" class="form-label fw-semibold">
                            Nama Driver <span class="text-danger">*</span>
                        </label>
                        <input 
                            type="text" 
                            class="form-control" 
                            id="driver" 
                            name="driver" 
                            placeholder="Masukkan nama driver" 
                            required
                            autofocus
                        >
                        <small class="text-muted">Driver yang akan membawa surat jalan ini</small>
                    </div>
                    
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fa-solid fa-times me-1"></i> Batal
                    </button>
                    <button type="submit" name="berangkatkan_surat_jalan" class="btn btn-success">
                        <i class="fa-solid fa-truck me-1"></i> Berangkatkan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../../../templates/footer.php'; ?>
