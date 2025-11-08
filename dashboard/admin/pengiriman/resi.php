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

include '../../../config/database.php';

$id = $_GET['id'];
$sql = "SELECT * FROM pengiriman WHERE id = $id";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    $pengiriman = $result->fetch_assoc();
    if($pengiriman['id_cabang_pengirim'] != $_SESSION['id_cabang']){
        header("Location: ./?error=not_found");
        exit;
    }
}else{
    header("Location: ./?error=not_found");
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="icon" type="image/x-icon" href="../../../assets/favicon.ico">
<title>Resi Pengiriman Barang</title>
<style>
    @page {
        size: A4 portrait;
        margin: 0;
    }
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }
    body {
        font-family: Arial, sans-serif;
        background-color: #e0e0e0;
    }

    /* === PERBAIKAN LEBAR === */
    .container {
        width: 100vw;            /* isi penuh layar */
        max-width: 210mm;        /* batas maksimum sesuai A4 */
        height: 297mm;
        background-color: white;
        padding: 0;
        margin: 0 auto;
        position: relative;
        page-break-after: always;
        overflow: hidden;
        display: flex;
        flex-direction: column;
    }
    .container:last-child {
        page-break-after: auto;
    }
    /* ========================= */

    .resi {
        width: 100%;
        flex: 1;
        display: flex;
        flex-direction: column;
        border: 2px solid #000;
        overflow: hidden;
    }
    .header-top {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        padding: 6px 8px;
        border-bottom: 1px solid #000;
    }
    .logo {
        width: 45px;
        height: 45px;
        flex-shrink: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        background-color: white;
        font-size: 8px;
        font-weight: bold;
        text-align: center;
        color: #d03542;
    }
    .company-header { flex-grow: 1; }
    .company-name {
        font-size: 16px;
        font-weight: bold;
        color: #d03542;
        text-align: center;
        letter-spacing: 1px;
        margin-bottom: 2px;
    }
    .contact-info {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr;
        gap: 2px 6px;
        font-size: 6px;
        line-height: 1.3;
    }
    .contact-left, .contact-middle, .contact-right { 
        text-align: left;
    }

    .form-wrapper {
        display: grid;
        grid-template-columns: 1fr auto 1fr;
        gap: 6px;
        padding: 3px 8px;
        align-items: flex-start;
        border-bottom: 1px solid #000;
    }
    .form-left, .form-right {
        display: flex;
        flex-direction: column;
        gap: 2px;
    }
    .form-line {
        display: flex;
        align-items: baseline;
        gap: 3px;
        font-size: 8px;
    }
    .form-label { font-weight: bold; min-width: 45px; }
    .form-dots {
        flex-grow: 1;
        border-bottom: 1px dotted #000;
        height: 0.6em;
    }

    .title-center { text-align: center; }
    .title {
        font-size: 14px;
        font-weight: bold;
        color: #d03542;
        letter-spacing: 1px;
        margin-bottom: 4px;
    }
    .resi-line {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        font-size: 12px;
        font-weight: bold;
    }
    .resi-label { color: #d03542; }
    .resi-number { color: #8B0000; font-size: 16px; }

    .table-wrapper {
        padding: 3px 8px;
        border-bottom: 1px solid #000;
        overflow: hidden;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        font-size: 8px;
    }
    th, td {
        border: 1px solid #000;
        padding: 3px 4px;
        text-align: left;
    }
    th {
        background-color: #f0f0f0;
        font-weight: bold;
        text-align: center;
        font-size: 7px;
    }
    .item-row td {
        height: 30px;
        vertical-align: top;
    }
    .total-row td {
        text-align: center;
        font-weight: bold;
        font-size: 8px;
        height: auto;
        padding: 4px 2px;
    }

    .bottom-section {
        padding: 8px 8px 4px 8px;
        display: flex;
        gap: 6px;
        flex-grow: 1;
        overflow: hidden;
    }
    .notes {
        flex: 0 0 45%;
        border: 1px solid #000;
        padding: 8px 6px;
        background-color: white;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }
    .notes ol {
        margin-left: 16px;
        font-size: 8px;
        line-height: 1.4;
        margin-bottom: 0;
    }
    .signature-section {
        flex: 1;
        display: grid;
        grid-template-columns: 1fr 1fr 1fr;
        gap: 4px;
    }
    .sig-box {
        border: 1px solid #000;
        padding: 4px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }
    .sig-content { font-size: 6.5px; line-height: 1.2; flex-grow: 1; }
    .sig-line { border-top: 1px solid #000; margin-top: 4px; height: 20px; }
    .sig-title { font-weight: bold; font-size: 7px; text-align: center; }

    @media print {
        body { background-color: white; margin: 0; padding: 0; }
        .container { width: 210mm; max-width: none; margin: 0; padding: 0; }
    }
</style>
</head>
<body>

<div class="container">

    <!-- RESI 1 -->
    <div class="resi">
        <div class="header-top">
            <div class="logo ms-2">
                <img src="../../../assets/logo.jpg" alt="Logo Perusahaan" style="width: 80px; height: auto;" class="rounded-circle">
            </div>
            <div class="company-header">
                <div class="company-name">PT. CENDANA LINTAS KARGO</div>
                <div class="contact-info d-flex justify-content-evenly">
                    <div class="contact-left">
                        <div><strong>Kantor Pusat:</strong> Jl. Cendana No.8 Samarinda</div>
                        <div><strong>HP:</strong> 082120406688</div>
                        <div><strong>Balikpapan:</strong> 081211220404 - 081127744474</div>
                        <div><strong>Sangatta:</strong> 082151224404 - 081250500026</div>
                    </div>
                    <div class="contact-middle">
                        <div><strong>Melak:</strong> 081251084448</div>
                        <div><strong>Ma. Wahau:</strong> 082350058686 - 082350058822</div>
                        <div><strong>Palaran:</strong> 08121367672</div>
                        <div><strong>Lambung:</strong> 082255102626</div>
                    </div>
                    <div class="contact-right">
                        <div><strong>Lempake:</strong> 085393932050 - 085393932030</div>
                        <div><strong>Tenggarong:</strong> 08115802377 - 08115802399</div>
                        <div><strong>Jl. Jakarta:</strong> 081378888795</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- form -->
        <div class="d-flex justify-content-between px-3 py-1">
            <div class="form-left fw-bold">
                <div class="form-line text-capitalize"><span class="form-label">Dari</span>: <?=$pengiriman['nama_pengirim'] ?></div>
                <div class="form-line"><span class="form-label">Alamat</span>: <?=$pengiriman['cabang_pengirim'] ?></div>
                <div class="form-line"><span class="form-label">Telp/HP</span>: <?=$pengiriman['telp_pengirim']?></div>
            </div>
            <div class="title-center">
                <div class="title">SURAT PENGIRIMAN BARANG</div>
                <div class="resi-line"><span class="resi-label">No. Resi:</span><span class="resi-number"><?=$pengiriman['no_resi']?></span></div>
            </div>
            <div class="form-right fw-bold">
                <div class="form-line text-capitalize"><span class="form-label">Kepada</span>: <?=$pengiriman['nama_penerima']?></div>
                <div class="form-line"><span class="form-label">Alamat</span>: <?=$pengiriman['cabang_penerima']?></div>
                <div class="form-line"><span class="form-label">Telp/HP</span>: <?=$pengiriman['telp_penerima']?></div>
            </div>
        </div>

        <!-- tabel -->
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th style="width:6%;">No</th>
                        <th style="width:44%;">Nama Barang</th>
                        <th style="width:13%;">Berat</th>
                        <th style="width:13%;">Jumlah</th>
                        <th style="width:24%;">Metode Pembayaran</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="item-row text-capitalize">
                        <td class="text-center">1</td>
                        <td class="text-center"><?=$pengiriman['nama_barang']?></td>
                        <td class="text-center"><?=$pengiriman['berat']?> Kg</td>
                        <td class="text-center"><?=$pengiriman['jumlah']?></td>
                        <td class="text-center"><?=$pengiriman['pembayaran']?></td>
                    </tr>
                    <tr class="total-row">
                        <td colspan="4">Diskon</td>
                        <td class=""><?=$pengiriman['diskon']? $pengiriman['diskon']: 0 ?> %</td>
                    </tr>
                    <tr class="total-row">
                        <td colspan="4">Total</td>
                        <td class="total_tarif"><?=$pengiriman['total_tarif']?></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- bawah -->
        <div class="bottom-section">
            <div class="notes">
                <ol>
                    <li>Barang rusak/pecah di ganti sesuai berapa % tingkat kerusakan dan barang hilang di ganti 10x biaya kirim max Rp. 2.000.000,-</li>
                    <li>Tidak di perkenan kan membagi foto resi selain kepada penerima atau yang di beri wewenang di karena kan bersifat rahasia</li>
                    <li>Pengiriman barang diambil di kantor perwakilan tujuan</li>
                    <li>Dilarang mengirim Paket (barang) Terlarang, bisa dilaporkan ke pihak berwajib dan dikenakan sanksi pidana</li>
                </ol>
            </div>
            <div class="signature-section">
                <div class="sig-box">
                    <div class="sig-content">
                        <strong>Kirim Tgl : <?=$pengiriman['tanggal']?></strong><br><br>
                        <strong>Pengirim,</strong><br><br>
                        <strong>HP/Telp : <?=$pengiriman['telp_pengirim']?></strong>
                    </div>
                    <div class="sig-line"></div>
                </div>
                <div class="sig-box">
                    <div class="sig-title">Driver,</div>
                    <div class="sig-line"></div>
                </div>
                <div class="sig-box">
                    <div class="sig-content">
                        <strong>Diterima :</strong><br><br>
                        <strong>Penerima,</strong><br><br>
                        <strong>HP/Telp : <?=$pengiriman['telp_penerima']?></strong>
                    </div>
                    <div class="sig-line"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- RESI 2 -->
    <div class="resi">
        <div class="header-top">
            <div class="logo ms-2">
                <img src="../../../assets/logo.jpg" alt="Logo Perusahaan" style="width: 80px; height: auto;" class="rounded-circle">
            </div>
            <div class="company-header">
                <div class="company-name">PT. CENDANA LINTAS KARGO</div>
                <div class="contact-info d-flex justify-content-evenly">
                    <div class="contact-left">
                        <div><strong>Kantor Pusat:</strong> Jl. Cendana No.8 Samarinda</div>
                        <div><strong>HP:</strong> 082120406688</div>
                        <div><strong>Balikpapan:</strong> 081211220404 - 081127744474</div>
                        <div><strong>Sangatta:</strong> 082151224404 - 081250500026</div>
                    </div>
                    <div class="contact-middle">
                        <div><strong>Melak:</strong> 081251084448</div>
                        <div><strong>Ma. Wahau:</strong> 082350058686 - 082350058822</div>
                        <div><strong>Palaran:</strong> 08121367672</div>
                        <div><strong>Lambung:</strong> 082255102626</div>
                    </div>
                    <div class="contact-right">
                        <div><strong>Lempake:</strong> 085393932050 - 085393932030</div>
                        <div><strong>Tenggarong:</strong> 08115802377 - 08115802399</div>
                        <div><strong>Jl. Jakarta:</strong> 081378888795</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- form -->
        <div class="d-flex justify-content-between px-3 py-1">
            <div class="form-left fw-bold">
                <div class="form-line text-capitalize"><span class="form-label">Dari</span>: <?=$pengiriman['nama_pengirim'] ?></div>
                <div class="form-line"><span class="form-label">Alamat</span>: <?=$pengiriman['cabang_pengirim'] ?></div>
                <div class="form-line"><span class="form-label">Telp/HP</span>: <?=$pengiriman['telp_pengirim']?></div>
            </div>
            <div class="title-center">
                <div class="title">SURAT PENGIRIMAN BARANG</div>
                <div class="resi-line"><span class="resi-label">No. Resi:</span><span class="resi-number"><?=$pengiriman['no_resi']?></span></div>
            </div>
            <div class="form-right fw-bold">
                <div class="form-line text-capitalize"><span class="form-label">Kepada</span>: <?=$pengiriman['nama_penerima']?></div>
                <div class="form-line"><span class="form-label">Alamat</span>: <?=$pengiriman['cabang_penerima']?></div>
                <div class="form-line"><span class="form-label">Telp/HP</span>: <?=$pengiriman['telp_penerima']?></div>
            </div>
        </div>

        <!-- tabel -->
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th style="width:6%;">No</th>
                        <th style="width:44%;">Nama Barang</th>
                        <th style="width:13%;">Berat</th>
                        <th style="width:13%;">Jumlah</th>
                        <th style="width:24%;">Metode Pembayaran</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="item-row text-capitalize">
                        <td class="text-center">1</td>
                        <td class="text-center"><?=$pengiriman['nama_barang']?></td>
                        <td class="text-center"><?=$pengiriman['berat']?> Kg</td>
                        <td class="text-center"><?=$pengiriman['jumlah']?></td>
                        <td class="text-center"><?=$pengiriman['pembayaran']?></td>
                    </tr>
                    <tr class="total-row">
                        <td colspan="4">Diskon</td>
                        <td class=""><?=$pengiriman['diskon']? $pengiriman['diskon']: 0 ?> %</td>
                    </tr>
                    <tr class="total-row">
                        <td colspan="4">Total</td>
                        <td class="total_tarif"><?=$pengiriman['total_tarif']?></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- bawah -->
        <div class="bottom-section">
            <div class="notes">
                <ol>
                    <li>Barang rusak/pecah di ganti sesuai berapa % tingkat kerusakan dan barang hilang di ganti 10x biaya kirim max Rp. 2.000.000,-</li>
                    <li>Tidak di perkenan kan membagi foto resi selain kepada penerima atau yang di beri wewenang di karena kan bersifat rahasia</li>
                    <li>Pengiriman barang diambil di kantor perwakilan tujuan</li>
                    <li>Dilarang mengirim Paket (barang) Terlarang, bisa dilaporkan ke pihak berwajib dan dikenakan sanksi pidana</li>
                </ol>
            </div>
            <div class="signature-section">
                <div class="sig-box">
                    <div class="sig-content">
                        <strong>Kirim Tgl : <?=$pengiriman['tanggal']?></strong><br><br>
                        <strong>Pengirim,</strong><br><br>
                        <strong>HP/Telp : <?=$pengiriman['telp_pengirim']?></strong>
                    </div>
                    <div class="sig-line"></div>
                </div>
                <div class="sig-box">
                    <div class="sig-title">Driver,</div>
                    <div class="sig-line"></div>
                </div>
                <div class="sig-box">
                    <div class="sig-content">
                        <strong>Diterima :</strong><br><br>
                        <strong>Penerima,</strong><br><br>
                        <strong>HP/Telp : <?=$pengiriman['telp_penerima']?></strong>
                    </div>
                    <div class="sig-line"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- RESI 3 -->
    <div class="resi">
        <div class="header-top">
            <div class="logo ms-2">
                <img src="../../../assets/logo.jpg" alt="Logo Perusahaan" style="width: 80px; height: auto;" class="rounded-circle">
            </div>
            <div class="company-header">
                <div class="company-name">PT. CENDANA LINTAS KARGO</div>
                <div class="contact-info d-flex justify-content-evenly">
                    <div class="contact-left">
                        <div><strong>Kantor Pusat:</strong> Jl. Cendana No.8 Samarinda</div>
                        <div><strong>HP:</strong> 082120406688</div>
                        <div><strong>Balikpapan:</strong> 081211220404 - 081127744474</div>
                        <div><strong>Sangatta:</strong> 082151224404 - 081250500026</div>
                    </div>
                    <div class="contact-middle">
                        <div><strong>Melak:</strong> 081251084448</div>
                        <div><strong>Ma. Wahau:</strong> 082350058686 - 082350058822</div>
                        <div><strong>Palaran:</strong> 08121367672</div>
                        <div><strong>Lambung:</strong> 082255102626</div>
                    </div>
                    <div class="contact-right">
                        <div><strong>Lempake:</strong> 085393932050 - 085393932030</div>
                        <div><strong>Tenggarong:</strong> 08115802377 - 08115802399</div>
                        <div><strong>Jl. Jakarta:</strong> 081378888795</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- form -->
        <div class="d-flex justify-content-between px-3 py-1">
            <div class="form-left fw-bold">
                <div class="form-line text-capitalize"><span class="form-label">Dari</span>: <?=$pengiriman['nama_pengirim'] ?></div>
                <div class="form-line"><span class="form-label">Alamat</span>: <?=$pengiriman['cabang_pengirim'] ?></div>
                <div class="form-line"><span class="form-label">Telp/HP</span>: <?=$pengiriman['telp_pengirim']?></div>
            </div>
            <div class="title-center">
                <div class="title">SURAT PENGIRIMAN BARANG</div>
                <div class="resi-line"><span class="resi-label">No. Resi:</span><span class="resi-number"><?=$pengiriman['no_resi']?></span></div>
            </div>
            <div class="form-right fw-bold">
                <div class="form-line text-capitalize"><span class="form-label">Kepada</span>: <?=$pengiriman['nama_penerima']?></div>
                <div class="form-line"><span class="form-label">Alamat</span>: <?=$pengiriman['cabang_penerima']?></div>
                <div class="form-line"><span class="form-label">Telp/HP</span>: <?=$pengiriman['telp_penerima']?></div>
            </div>
        </div>

        <!-- tabel -->
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th style="width:6%;">No</th>
                        <th style="width:44%;">Nama Barang</th>
                        <th style="width:13%;">Berat</th>
                        <th style="width:13%;">Jumlah</th>
                        <th style="width:24%;">Metode Pembayaran</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="item-row text-capitalize">
                        <td class="text-center">1</td>
                        <td class="text-center"><?=$pengiriman['nama_barang']?></td>
                        <td class="text-center"><?=$pengiriman['berat']?> Kg</td>
                        <td class="text-center"><?=$pengiriman['jumlah']?></td>
                        <td class="text-center"><?=$pengiriman['pembayaran']?></td>
                    </tr>
                    <tr class="total-row">
                        <td colspan="4">Diskon</td>
                        <td class=""><?=$pengiriman['diskon']? $pengiriman['diskon']: 0 ?> %</td>
                    </tr>
                    <tr class="total-row">
                        <td colspan="4">Total</td>
                        <td class="total_tarif"><?=$pengiriman['total_tarif']?></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- bawah -->
        <div class="bottom-section">
            <div class="notes">
                <ol>
                    <li>Barang rusak/pecah di ganti sesuai berapa % tingkat kerusakan dan barang hilang di ganti 10x biaya kirim max Rp. 2.000.000,-</li>
                    <li>Tidak di perkenan kan membagi foto resi selain kepada penerima atau yang di beri wewenang di karena kan bersifat rahasia</li>
                    <li>Pengiriman barang diambil di kantor perwakilan tujuan</li>
                    <li>Dilarang mengirim Paket (barang) Terlarang, bisa dilaporkan ke pihak berwajib dan dikenakan sanksi pidana</li>
                </ol>
            </div>
            <div class="signature-section">
                <div class="sig-box">
                    <div class="sig-content">
                        <strong>Kirim Tgl : <?=$pengiriman['tanggal']?></strong><br><br>
                        <strong>Pengirim,</strong><br><br>
                        <strong>HP/Telp : <?=$pengiriman['telp_pengirim']?></strong>
                    </div>
                    <div class="sig-line"></div>
                </div>
                <div class="sig-box">
                    <div class="sig-title">Driver,</div>
                    <div class="sig-line"></div>
                </div>
                <div class="sig-box">
                    <div class="sig-content">
                        <strong>Diterima :</strong><br><br>
                        <strong>Penerima,</strong><br><br>
                        <strong>HP/Telp : <?=$pengiriman['telp_penerima']?></strong>
                    </div>
                    <div class="sig-line"></div>
                </div>
            </div>
        </div>
    </div>


    <script>
    // Ambil semua elemen dengan class "harga"
    const hargaElements = document.querySelectorAll('.total_tarif');

    hargaElements.forEach(el => {
        // Ambil nilai teks (contohnya "150000")
        let value = parseFloat(el.textContent);

        // Format ke Rupiah
        let formatted = value.toLocaleString('id-ID', {
        style: 'currency',
        currency: 'IDR'
        });

        // Masukkan hasil ke elemen
        el.textContent = formatted;
    });
    
    window.addEventListener('load', () => {
    window.print();
  });
</script>
</body>
</html>
