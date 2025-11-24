<?php
session_start();

if (!isset($_SESSION['username'])) {
    header('Location: ../../../auth/login');
    exit;
}
if (isset($_SESSION['role']) && $_SESSION['role'] !== 'systemOwner') {
    header('Location: ../../../?error=unauthorized');
    exit;
}

$dataFile = 'content.json'; 

// Data Default (Termasuk default gambar slider)
$defaultData = [
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
        'ctaTitle' => "Kirim Barang Sekarang Bersama Kami!",
        'ctaText' => "Keamanan, kecepatan, dan kepuasan pelanggan adalah prioritas utama kami.",
        'footerDesc' => "Partner logistik terpercaya untuk setiap pengiriman Anda, cepat, aman, dan hemat.",
        'footerAddress' => "Jl. Cendana No. 88, Samarinda"
    ],
    'en' => [
        'heroTitle' => "Fast, Safe, and Reliable Shipping Solutions",
        'heroText' => "We deliver goods across Indonesia with affordable rates and trusted service.",
        'whyTitle' => "Why Choose Cendana Lintas Kargo?",
        'whyText' => "We provide on-time delivery, advanced tracking systems, and transparent rates for all customers.",
        'ctaTitle' => "Ship With Us Now!",
        'ctaText' => "Security, speed, and satisfaction are our top priorities.",
        'footerDesc' => "Your trusted logistics partner for every delivery, fast, safe, and affordable.",
        'footerAddress' => "Jl. Cendana No. 88, Samarinda"
    ]
];

// Load Data Existing
if (file_exists($dataFile)) {
    $currentData = json_decode(file_get_contents($dataFile), true);
    // Merge dengan default untuk memastikan key slider ada jika file json lama
    $currentData = array_replace_recursive($defaultData, $currentData);
} else {
    $currentData = $defaultData;
}

$message = "";
$messageType = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $section = $_POST['section_type'] ?? '';
    $isUpdated = false;

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

    // B. Handle Image Upload (Khusus Section Hero)
    if ($section === 'hero' && !empty($_FILES)) {
        $uploadDir = '../../../assets/slider/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        for ($i = 1; $i <= 3; $i++) {
            $inputName = 'slider' . $i;
            if (isset($_FILES[$inputName]) && $_FILES[$inputName]['error'] === 0) {
                $fileTmp = $_FILES[$inputName]['tmp_name'];
                $fileName = $_FILES[$inputName]['name'];
                $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'webp'];

                if (in_array($fileExt, $allowed)) {
                    $newFileName = 'slider' . $i . '_' . time() . '.' . $fileExt;
                    $destPath = $uploadDir . $newFileName;

                    if (move_uploaded_file($fileTmp, $destPath)) {
                        $oldFile = isset($currentData['sliders'][$inputName]) ? $currentData['sliders'][$inputName] : '';
                        if($oldFile && file_exists('../../../' . $oldFile) && strpos($oldFile, 'assets/slider/') !== false) {
                        }

                        $currentData['sliders'][$inputName] = 'assets/slider/' . $newFileName;
                        $isUpdated = true;
                    }
                } else {
                    $message = "Format gambar harus JPG, PNG, atau WEBP.";
                    $messageType = "warning";
                }
            }
        }
    }

    if ($isUpdated) {
        if (file_put_contents($dataFile, json_encode($currentData, JSON_PRETTY_PRINT))) {
            $message = "Bagian <strong>" . strtoupper($section) . "</strong> berhasil diperbarui!";
            $messageType = "success";
        } else {
            $message = "Gagal menyimpan data ke file JSON.";
            $messageType = "danger";
        }
    }
}

function getVal($data, $lang, $key) {
    return isset($data[$lang][$key]) ? $data[$lang][$key] : '';
}
function getImg($data, $key) {
    return isset($data['sliders'][$key]) ? $data['sliders'][$key] : 'assets/slider/slider1.jpg';
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
                        <p class="text-muted small mb-0">Kelola teks dan gambar halaman utama per bagian.</p>
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
                    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold text-danger"><i class="fa-solid fa-images me-2"></i>Bagian Hero & Slider</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" enctype="multipart/form-data">
                            <input type="hidden" name="section_type" value="hero">
                            
                            <div class="row mb-4">
                                <h6 class="fw-bold text-muted mb-3">Gambar Slider (Background)</h6>
                                
                                <div class="col-md-4 mb-3">
                                    <div class="border rounded p-3 text-center bg-light h-100">
                                        <p class="small fw-bold mb-2">Slider 1</p>
                                        <img src="../../../<?= getImg($currentData, 'slider1') ?>" class="img-fluid rounded mb-2 shadow-sm" style="height: 120px; object-fit: cover; width: 100%;">
                                        <input type="file" class="form-control form-control-sm" name="slider1" accept="image/*">
                                        <div class="form-text small">Biarkan kosong jika tidak diubah.</div>
                                    </div>
                                </div>

                                <div class="col-md-4 mb-3">
                                    <div class="border rounded p-3 text-center bg-light h-100">
                                        <p class="small fw-bold mb-2">Slider 2</p>
                                        <img src="../../../<?= getImg($currentData, 'slider2') ?>" class="img-fluid rounded mb-2 shadow-sm" style="height: 120px; object-fit: cover; width: 100%;">
                                        <input type="file" class="form-control form-control-sm" name="slider2" accept="image/*">
                                    </div>
                                </div>

                                <div class="col-md-4 mb-3">
                                    <div class="border rounded p-3 text-center bg-light h-100">
                                        <p class="small fw-bold mb-2">Slider 3</p>
                                        <img src="../../../<?= getImg($currentData, 'slider3') ?>" class="img-fluid rounded mb-2 shadow-sm" style="height: 120px; object-fit: cover; width: 100%;">
                                        <input type="file" class="form-control form-control-sm" name="slider3" accept="image/*">
                                    </div>
                                </div>
                            </div>

                            <hr>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <h6 class="text-muted mb-2">🇮🇩 Bahasa Indonesia</h6>
                                    <div class="mb-3">
                                        <label class="form-label small fw-bold">Judul Utama</label>
                                        <input type="text" class="form-control" name="id_heroTitle" value="<?= getVal($currentData, 'id', 'heroTitle') ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label small fw-bold">Deskripsi</label>
                                        <textarea class="form-control" rows="3" name="id_heroText" required><?= getVal($currentData, 'id', 'heroText') ?></textarea>
                                    </div>
                                </div>
                                <div class="col-md-6 border-start">
                                    <h6 class="text-muted mb-2">🇬🇧 English</h6>
                                    <div class="mb-3">
                                        <label class="form-label small fw-bold">Main Title</label>
                                        <input type="text" class="form-control" name="en_heroTitle" value="<?= getVal($currentData, 'en', 'heroTitle') ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label small fw-bold">Description</label>
                                        <textarea class="form-control" rows="3" name="en_heroText" required><?= getVal($currentData, 'en', 'heroText') ?></textarea>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-end mt-3">
                                <button type="submit" class="btn btn-danger px-4">
                                    <i class="fa-solid fa-save me-2"></i>Simpan Hero & Slider
                                </button>
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
                                        <input type="text" class="form-control" name="id_whyTitle" value="<?= getVal($currentData, 'id', 'whyTitle') ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label small fw-bold">Teks Penjelasan (ID)</label>
                                        <textarea class="form-control" rows="3" name="id_whyText" required><?= getVal($currentData, 'id', 'whyText') ?></textarea>
                                    </div>
                                </div>
                                <div class="col-md-6 border-start">
                                    <div class="mb-3">
                                        <label class="form-label small fw-bold">Section Title (EN)</label>
                                        <input type="text" class="form-control" name="en_whyTitle" value="<?= getVal($currentData, 'en', 'whyTitle') ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label small fw-bold">Explanation Text (EN)</label>
                                        <textarea class="form-control" rows="3" name="en_whyText" required><?= getVal($currentData, 'en', 'whyText') ?></textarea>
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex justify-content-end mt-3">
                                <button type="submit" class="btn btn-danger px-4">
                                    <i class="fa-solid fa-save me-2"></i>Simpan Why Us
                                </button>
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
                                    <h6 class="text-muted fw-bold">Call To Action (Banner Bawah)</h6>
                                    <div class="mb-2">
                                        <label class="form-label small">Judul (ID)</label>
                                        <input type="text" class="form-control form-control-sm" name="id_ctaTitle" value="<?= getVal($currentData, 'id', 'ctaTitle') ?>">
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label small">Teks (ID)</label>
                                        <input type="text" class="form-control form-control-sm" name="id_ctaText" value="<?= getVal($currentData, 'id', 'ctaText') ?>">
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label small">Title (EN)</label>
                                        <input type="text" class="form-control form-control-sm" name="en_ctaTitle" value="<?= getVal($currentData, 'en', 'ctaTitle') ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label small">Text (EN)</label>
                                        <input type="text" class="form-control form-control-sm" name="en_ctaText" value="<?= getVal($currentData, 'en', 'ctaText') ?>">
                                    </div>
                                </div>
                                <div class="col-md-6 border-start">
                                    <h6 class="text-muted fw-bold">Footer & Kontak</h6>
                                    <div class="mb-2">
                                        <label class="form-label small">Deskripsi Footer (ID)</label>
                                        <textarea class="form-control form-control-sm" rows="2" name="id_footerDesc"><?= getVal($currentData, 'id', 'footerDesc') ?></textarea>
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label small">Deskripsi Footer (EN)</label>
                                        <textarea class="form-control form-control-sm" rows="2" name="en_footerDesc"><?= getVal($currentData, 'en', 'footerDesc') ?></textarea>
                                    </div>
                                    <div class="row">
                                        <div class="col-12 mb-2">
                                            <label class="form-label small">Alamat</label>
                                            <input type="text" class="form-control form-control-sm" name="id_footerAddress" value="<?= getVal($currentData, 'id', 'footerAddress') ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex justify-content-end mt-3">
                                <button type="submit" class="btn btn-danger px-4">
                                    <i class="fa-solid fa-save me-2"></i>Simpan Footer
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<?php include '../../../templates/footer.php'; ?>