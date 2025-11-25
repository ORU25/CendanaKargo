<?php
session_start();

// 1. Otorisasi
if (!isset($_SESSION['username'])) {
    header('Location: ../../../auth/login');
    exit;
}
if (isset($_SESSION['role']) && $_SESSION['role'] !== 'systemOwner') {
    header('Location: ../../../?error=unauthorized');
    exit;
}

// 2. Setup File JSON
$dataFile = 'content.json';

// Data Default
$defaultData = [
    'settings' => [
        'navLogo' => 'assets/logo.jpg',
        'footerLogo' => 'assets/clk.png'
    ],
    'sliders' => [
        'slider1' => 'assets/slider/slider1.jpg',
        'slider2' => 'assets/slider/slider2.jpg',
        'slider3' => 'assets/slider/slider3.jpg'
    ],
    'id' => [
        'heroTitle' => "Solusi Pengiriman Cepat, Aman, dan Terpercaya",
        'heroText' => "Kami melayani pengiriman barang ke seluruh Indonesia dengan tarif bersahabat dan layanan terbaik.",
        'whyTitle' => "Mengapa Memilih Cendana Lintas Kargo?",
        'whyText' => "Kami berkomitmen memberikan layanan terbaik dengan pengiriman tepat waktu, sistem pelacakan canggih, serta tarif transparan dan bersahabat untuk seluruh pelanggan kami.",
        'layanan1' => "Pengiriman Cepat",
        'layanan1desc' => "Barang Anda dikirim dengan estimasi waktu akurat dan pengantaran cepat serta aman.",
        'layanan2' => "Aman & Terpercaya",
        'layanan2desc' => "Keamanan paket Anda menjadi prioritas kami dengan sistem tracking real-time.",
        'layanan3' => "Tarif Terjangkau",
        'layanan3desc' => "Nikmati tarif pengiriman hemat tanpa mengorbankan kualitas layanan kami.",
        'ctaTitle' => "Kirim Barang Sekarang Bersama Kami!",
        'ctaText' => "Keamanan, kecepatan, dan kepuasan pelanggan adalah prioritas utama kami.",
        'footerDesc' => "Partner logistik terpercaya untuk setiap pengiriman Anda, cepat, aman, dan hemat.",
        'footerAddress' => "Jl. Cendana No. 88, Samarinda",
        'footerPhone' => "(0541) 123456",
        'footerEmail' => "info@cendanakargo.com"
    ],
    'en' => [
        'heroTitle' => "Fast, Safe, and Reliable Shipping Solutions",
        'heroText' => "We deliver goods across Indonesia with affordable rates and trusted service.",
        'whyTitle' => "Why Choose Cendana Lintas Kargo?",
        'whyText' => "We provide on-time delivery, advanced tracking systems, and transparent rates for all customers.",
        'layanan1' => "Fast Delivery",
        'layanan1desc' => "Your goods are shipped quickly and safely with accurate estimates.",
        'layanan2' => "Secure & Trusted",
        'layanan2desc' => "Your package safety is our top priority with real-time tracking.",
        'layanan3' => "Affordable Rates",
        'layanan3desc' => "Enjoy low-cost delivery without sacrificing quality.",
        'ctaTitle' => "Ship With Us Now!",
        'ctaText' => "Security, speed, and satisfaction are our top priorities.",
        'footerDesc' => "Your trusted logistics partner for every delivery, fast, safe, and affordable.",
        'footerAddress' => "Jl. Cendana No. 88, Samarinda",
        'footerPhone' => "(0541) 123456",
        'footerEmail' => "info@cendanakargo.com"
    ]
];

// Load Data Existing
if (file_exists($dataFile)) {
    $currentData = json_decode(file_get_contents($dataFile), true);
    $currentData = array_replace_recursive($defaultData, $currentData);
} else {
    $currentData = $defaultData;
}

$message = "";
$messageType = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $section = $_POST['section_type'] ?? '';
    $isUpdated = false;

    // A. Handle Text Update
    foreach ($_POST as $key => $value) {
        if (strpos($key, 'id_') === 0) {
            $realKey = substr($key, 3);
            $currentData['id'][$realKey] = htmlspecialchars($value);
            $isUpdated = true;
        } elseif (strpos($key, 'en_') === 0) {
            $realKey = substr($key, 3);
            $currentData['en'][$realKey] = htmlspecialchars($value);
            $isUpdated = true;
        }
    }

    // B. Handle Image Upload (Hero, Logo, Services)
    if (!empty($_FILES)) {
        $uploadDir = '../../../assets/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $sliderDir = '../../../assets/slider/';
        if (!file_exists($sliderDir)) {
            mkdir($sliderDir, 0777, true);
        }

        // Helper Upload Function with DELETE Logic
        function handleUpload($fileInputName, $targetDir, &$dataRef, $jsonKey, &$updatedFlag) {
            if (isset($_FILES[$fileInputName]) && $_FILES[$fileInputName]['error'] === 0) {
                $fileTmp = $_FILES[$fileInputName]['tmp_name'];
                $fileName = $_FILES[$fileInputName]['name'];
                $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'webp', 'svg'];

                if (in_array($fileExt, $allowed)) {
                    // Nama file baru unik
                    $newFileName = $jsonKey . '_' . time() . '.' . $fileExt;
                    $destPath = $targetDir . $newFileName;
                    
                    // Determine relative path for JSON
                    $relPath = (strpos($targetDir, 'slider') !== false) ? 'assets/slider/' : 'assets/';

                    if (move_uploaded_file($fileTmp, $destPath)) {
                        if (isset($dataRef[$jsonKey]) && !empty($dataRef[$jsonKey])) {
                            $oldFilePath = '../../../' . $dataRef[$jsonKey]; // Path relatif dari file ini ke root
                            if (file_exists($oldFilePath) && strpos($dataRef[$jsonKey], 'assets/') !== false) {
                                unlink($oldFilePath);
                            }
                        }
                        // ==========================================

                        // Update JSON dengan path baru
                        $dataRef[$jsonKey] = $relPath . $newFileName;
                        $updatedFlag = true;
                    }
                }
            }
        }

        // Upload Logic per Section
        if ($section === 'settings') {
            handleUpload('navLogo', $uploadDir, $currentData['settings'], 'navLogo', $isUpdated);
            handleUpload('footerLogo', $uploadDir, $currentData['settings'], 'footerLogo', $isUpdated);
        }
        
        if ($section === 'hero') {
            handleUpload('slider1', $sliderDir, $currentData['sliders'], 'slider1', $isUpdated);
            handleUpload('slider2', $sliderDir, $currentData['sliders'], 'slider2', $isUpdated);
            handleUpload('slider3', $sliderDir, $currentData['sliders'], 'slider3', $isUpdated);
        }
    }

    if ($isUpdated) {
        if (file_put_contents($dataFile, json_encode($currentData, JSON_PRETTY_PRINT))) {
            $message = "Bagian <strong>" . strtoupper(str_replace('_', ' ', $section)) . "</strong> berhasil diperbarui!";
            $messageType = "success";
        } else {
            $message = "Gagal menyimpan data.";
            $messageType = "danger";
        }
    }
}

// Helpers
function getVal($data, $lang, $key) {
    return isset($data[$lang][$key]) ? $data[$lang][$key] : '';
}
function getImg($data, $group, $key) {
    // Fallback logic
    $default = 'assets/logo.jpg'; // Generic fallback
    if($group == 'sliders') $default = 'assets/slider/slider1.jpg';
    if($key == 'footerLogo') $default = 'assets/clk.png';
    
    return isset($data[$group][$key]) ? $data[$group][$key] : $default;
}

$title = 'Kustomisasi Landing Page';
$page = 'customisasi';
include '../../../templates/header.php';
include '../../../components/navDashboard.php';
include '../../../components/sidebar_offcanvas.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include '../../../components/sidebar.php'; ?>
        
        <div class="col-lg-10 bg-light">
            <div class="container-fluid p-4">
                
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h1 class="h3 mb-1 fw-bold">Kustomisasi Landing Page</h1>
                        <p class="text-muted small mb-0">Kelola konten website utama secara dinamis.</p>
                    </div>
                    <a href="../../../index.php" target="_blank" class="btn btn-outline-primary btn-sm">
                        <i class="fa-solid fa-eye me-1"></i> Lihat Website
                    </a>
                </div>

                <?php if($message): ?>
                <div class="alert alert-<?= $messageType ?> alert-dismissible fade show shadow-sm" role="alert">
                    <i class="fa-solid fa-circle-info me-2"></i> <?= $message ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>

                <div class="card border-0 shadow-sm mb-5">
                    <div class="card-header bg-white py-3 border-bottom">
                        <h5 class="mb-0 fw-bold text-danger"><i class="fa-solid fa-gear me-2"></i>Pengaturan Umum (Logo)</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" enctype="multipart/form-data">
                            <input type="hidden" name="section_type" value="settings">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label small fw-bold">Logo Navbar (Atas)</label>
                                    <div class="d-flex align-items-center gap-3 border rounded p-3 bg-light">
                                        <img src="../../../<?= getImg($currentData, 'settings', 'navLogo') ?>" class="rounded bg-white p-1 border" style="height: 60px; width: 60px; object-fit: contain;">
                                        <div class="flex-grow-1">
                                            <input type="file" class="form-control form-control-sm" name="navLogo" accept="image/*">
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label small fw-bold">Logo Footer (Bawah)</label>
                                    <div class="d-flex align-items-center gap-3 border rounded p-3 bg-light">
                                        <img src="../../../<?= getImg($currentData, 'settings', 'footerLogo') ?>" class="rounded bg-white p-1 border" style="height: 60px; width: 60px; object-fit: contain;">
                                        <div class="flex-grow-1">
                                            <input type="file" class="form-control form-control-sm" name="footerLogo" accept="image/*">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex justify-content-end mt-2">
                                <button type="submit" class="btn btn-danger px-4"><i class="fa-solid fa-save me-2"></i>Simpan Logo</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card border-0 shadow-sm mb-5">
                    <div class="card-header bg-white py-3 border-bottom">
                        <h5 class="mb-0 fw-bold text-danger"><i class="fa-solid fa-images me-2"></i>Bagian Hero & Slider</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" enctype="multipart/form-data">
                            <input type="hidden" name="section_type" value="hero">
                            
                            <div class="row mb-4">
                                <h6 class="fw-bold text-muted mb-3 small text-uppercase">Gambar Slider Background</h6>
                                <?php for($i=1; $i<=3; $i++): ?>
                                <div class="col-md-4 mb-3">
                                    <div class="border rounded p-2 text-center bg-light h-100">
                                        <p class="small fw-bold mb-2">Slider <?= $i ?></p>
                                        <img src="../../../<?= getImg($currentData, 'sliders', 'slider'.$i) ?>" class="img-fluid rounded mb-2 shadow-sm" style="height: 100px; object-fit: cover; width: 100%;">
                                        <input type="file" class="form-control form-control-sm" name="slider<?= $i ?>" accept="image/*">
                                    </div>
                                </div>
                                <?php endfor; ?>
                            </div>
                            <hr>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label small fw-bold">Judul Utama (ID)</label>
                                        <input type="text" class="form-control" name="id_heroTitle" value="<?= getVal($currentData, 'id', 'heroTitle') ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label small fw-bold">Deskripsi (ID)</label>
                                        <textarea class="form-control" rows="2" name="id_heroText"><?= getVal($currentData, 'id', 'heroText') ?></textarea>
                                    </div>
                                </div>
                                <div class="col-md-6 border-start">
                                    <div class="mb-3">
                                        <label class="form-label small fw-bold">Main Title (EN)</label>
                                        <input type="text" class="form-control" name="en_heroTitle" value="<?= getVal($currentData, 'en', 'heroTitle') ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label small fw-bold">Description (EN)</label>
                                        <textarea class="form-control" rows="2" name="en_heroText"><?= getVal($currentData, 'en', 'heroText') ?></textarea>
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex justify-content-end mt-3">
                                <button type="submit" class="btn btn-danger px-4"><i class="fa-solid fa-save me-2"></i>Simpan Hero</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card border-0 shadow-sm mb-5">
                    <div class="card-header bg-white py-3 border-bottom">
                        <h5 class="mb-0 fw-bold text-danger"><i class="fa-solid fa-question-circle me-2"></i>Bagian Mengapa Kami</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="section_type" value="why_us">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label small fw-bold">Judul Section (ID)</label>
                                        <input type="text" class="form-control" name="id_whyTitle" value="<?= getVal($currentData, 'id', 'whyTitle') ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label small fw-bold">Teks Penjelasan (ID)</label>
                                        <textarea class="form-control" rows="2" name="id_whyText"><?= getVal($currentData, 'id', 'whyText') ?></textarea>
                                    </div>
                                </div>
                                <div class="col-md-6 border-start">
                                    <div class="mb-3">
                                        <label class="form-label small fw-bold">Section Title (EN)</label>
                                        <input type="text" class="form-control" name="en_whyTitle" value="<?= getVal($currentData, 'en', 'whyTitle') ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label small fw-bold">Explanation Text (EN)</label>
                                        <textarea class="form-control" rows="2" name="en_whyText"><?= getVal($currentData, 'en', 'whyText') ?></textarea>
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex justify-content-end mt-3">
                                <button type="submit" class="btn btn-danger px-4"><i class="fa-solid fa-save me-2"></i>Simpan Why Us</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card border-0 shadow-sm mb-5">
                    <div class="card-header bg-white py-3 border-bottom">
                        <h5 class="mb-0 fw-bold text-danger"><i class="fa-solid fa-truck-fast me-2"></i>Layanan Kami (Services)</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="section_type" value="services">
                            
                            <div class="border rounded p-3 mb-4 bg-light">
                                <h6 class="fw-bold text-dark mb-3 border-bottom pb-2">Layanan 1 (Kiri)</h6>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-2">
                                            <label class="form-label small fw-bold">Judul (ID)</label>
                                            <input type="text" class="form-control form-control-sm" name="id_layanan1" value="<?= getVal($currentData, 'id', 'layanan1') ?>">
                                        </div>
                                        <div class="mb-2">
                                            <label class="form-label small fw-bold">Deskripsi (ID)</label>
                                            <textarea class="form-control form-control-sm" rows="2" name="id_layanan1desc"><?= getVal($currentData, 'id', 'layanan1desc') ?></textarea>
                                        </div>
                                    </div>
                                    <div class="col-md-6 border-start">
                                        <div class="mb-2">
                                            <label class="form-label small fw-bold">Title (EN)</label>
                                            <input type="text" class="form-control form-control-sm" name="en_layanan1" value="<?= getVal($currentData, 'en', 'layanan1') ?>">
                                        </div>
                                        <div class="mb-2">
                                            <label class="form-label small fw-bold">Description (EN)</label>
                                            <textarea class="form-control form-control-sm" rows="2" name="en_layanan1desc"><?= getVal($currentData, 'en', 'layanan1desc') ?></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="border rounded p-3 mb-4 bg-light">
                                <h6 class="fw-bold text-dark mb-3 border-bottom pb-2">Layanan 2 (Tengah)</h6>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-2">
                                            <label class="form-label small fw-bold">Judul (ID)</label>
                                            <input type="text" class="form-control form-control-sm" name="id_layanan2" value="<?= getVal($currentData, 'id', 'layanan2') ?>">
                                        </div>
                                        <div class="mb-2">
                                            <label class="form-label small fw-bold">Deskripsi (ID)</label>
                                            <textarea class="form-control form-control-sm" rows="2" name="id_layanan2desc"><?= getVal($currentData, 'id', 'layanan2desc') ?></textarea>
                                        </div>
                                    </div>
                                    <div class="col-md-6 border-start">
                                        <div class="mb-2">
                                            <label class="form-label small fw-bold">Title (EN)</label>
                                            <input type="text" class="form-control form-control-sm" name="en_layanan2" value="<?= getVal($currentData, 'en', 'layanan2') ?>">
                                        </div>
                                        <div class="mb-2">
                                            <label class="form-label small fw-bold">Description (EN)</label>
                                            <textarea class="form-control form-control-sm" rows="2" name="en_layanan2desc"><?= getVal($currentData, 'en', 'layanan2desc') ?></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="border rounded p-3 mb-3 bg-light">
                                <h6 class="fw-bold text-dark mb-3 border-bottom pb-2">Layanan 3 (Kanan)</h6>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-2">
                                            <label class="form-label small fw-bold">Judul (ID)</label>
                                            <input type="text" class="form-control form-control-sm" name="id_layanan3" value="<?= getVal($currentData, 'id', 'layanan3') ?>">
                                        </div>
                                        <div class="mb-2">
                                            <label class="form-label small fw-bold">Deskripsi (ID)</label>
                                            <textarea class="form-control form-control-sm" rows="2" name="id_layanan3desc"><?= getVal($currentData, 'id', 'layanan3desc') ?></textarea>
                                        </div>
                                    </div>
                                    <div class="col-md-6 border-start">
                                        <div class="mb-2">
                                            <label class="form-label small fw-bold">Title (EN)</label>
                                            <input type="text" class="form-control form-control-sm" name="en_layanan3" value="<?= getVal($currentData, 'en', 'layanan3') ?>">
                                        </div>
                                        <div class="mb-2">
                                            <label class="form-label small fw-bold">Description (EN)</label>
                                            <textarea class="form-control form-control-sm" rows="2" name="en_layanan3desc"><?= getVal($currentData, 'en', 'layanan3desc') ?></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-end mt-3">
                                <button type="submit" class="btn btn-danger px-4"><i class="fa-solid fa-save me-2"></i>Simpan Layanan</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card border-0 shadow-sm mb-5">
                    <div class="card-header bg-white py-3 border-bottom">
                        <h5 class="mb-0 fw-bold text-danger"><i class="fa-solid fa-bullhorn me-2"></i>Call to Action & Kontak</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="section_type" value="cta_footer">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    
                                    <div class="mb-3">
                                        <label class="form-label small fw-bold">Judul CTA (ID)</label>
                                        <input type="text" class="form-control form-control-sm" name="id_ctaTitle" value="<?= getVal($currentData, 'id', 'ctaTitle') ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label small fw-bold">Teks CTA (ID)</label>
                                        <input type="text" class="form-control form-control-sm" name="id_ctaText" value="<?= getVal($currentData, 'id', 'ctaText') ?>">
                                    </div>
                                    
                                    <div class="mb-3 mt-4">
                                        <label class="form-label small fw-bold">Deskripsi Footer (ID)</label>
                                        <textarea class="form-control form-control-sm" rows="3" name="id_footerDesc"><?= getVal($currentData, 'id', 'footerDesc') ?></textarea>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label small fw-bold">Alamat (ID)</label>
                                        <input type="text" class="form-control form-control-sm" name="id_footerAddress" value="<?= getVal($currentData, 'id', 'footerAddress') ?>">
                                    </div>
                                </div>

                                <div class="col-md-6 border-start">
                                    
                                    <div class="mb-3">
                                        <label class="form-label small fw-bold">CTA Title (EN)</label>
                                        <input type="text" class="form-control form-control-sm" name="en_ctaTitle" value="<?= getVal($currentData, 'en', 'ctaTitle') ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label small fw-bold">CTA Text (EN)</label>
                                        <input type="text" class="form-control form-control-sm" name="en_ctaText" value="<?= getVal($currentData, 'en', 'ctaText') ?>">
                                    </div>

                                    <div class="mb-3 mt-4">
                                        <label class="form-label small fw-bold">Footer Description (EN)</label>
                                        <textarea class="form-control form-control-sm" rows="3" name="en_footerDesc"><?= getVal($currentData, 'en', 'footerDesc') ?></textarea>
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex justify-content-end mt-3">
                                <button type="submit" class="btn btn-danger px-4"><i class="fa-solid fa-save me-2"></i>Simpan Footer</button>
                            </div>
                        </form>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<?php include '../../../templates/footer.php'; ?>