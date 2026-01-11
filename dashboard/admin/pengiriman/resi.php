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
<script src="https://cdn.tailwindcss.com"></script>
<link rel="icon" type="image/x-icon" href="../../../assets/favicon.ico">
<title>Resi Pengiriman Barang</title>
<style>
    @page {
        size: A4 portrait;
        margin: 0;
    }
    
    @media print {
        body { 
            margin: 0; 
            padding: 0; 
        }
        .container { 
            width: 210mm; 
            max-width: none; 
            margin: 0; 
            padding: 0; 
        }
    }
    
    .page-break {
        page-break-after: always;
    }
    
    .page-break:last-child {
        page-break-after: auto;
    }
</style>
<script>
    tailwind.config = {
        theme: {
            extend: {
                colors: {
                    primary: '#d03542',
                    secondary: '#8B0000',
                }
            }
        }
    }
</script>
</head>
<body class="bg-gray-300 font-sans">

<div class="w-screen max-w-[210mm] h-[297mm] bg-white mx-auto relative page-break overflow-hidden flex flex-col">

    <!-- RESI 1 -->
    <div class="w-full flex-1 flex flex-col border-2 border-black overflow-hidden">
        <!-- Header -->
        <div class="flex items-start border-b border-black">
            <div class="w-[70px] h-[70px] flex-shrink-0 flex items-center justify-center bg-white ms-4 mb-1">
                <img src="../../../assets/logo.jpg" alt="Logo Perusahaan" class="w-20 h-auto rounded-full">
            </div>
            <div class="flex-grow py-1.5">
                <div class="text-base font-bold text-primary text-center tracking-wide mb-0.5">PT. CENDANA LINTAS KARGO</div>
                <div class="flex justify-around text-[7px] leading-tight">
                    <div class="text-left">
                        <div><strong>Kantor Pusat:</strong> Jl. Cendana No.8 Samarinda</div>
                        <div><strong>HP:</strong> 082120406688</div>
                        <div><strong>Balikpapan:</strong> 081211220404 - 081127744474</div>
                        <div><strong>Sangatta:</strong> 082151224404 - 081250500026</div>
                    </div>
                    <div class="text-left">
                        <div><strong>Melak:</strong> 081251084448</div>
                        <div><strong>Ma. Wahau:</strong> 082350058686 - 082350058822</div>
                        <div><strong>Palaran:</strong> 08121367671</div>
                        <div><strong>Lambung:</strong> 082255102626</div>
                    </div>
                    <div class="text-left">
                        <div><strong>Lempake:</strong> 085393932050 - 085393932030</div>
                        <div><strong>Tenggarong:</strong> 08115802377 - 08115802399</div>
                        <div><strong>Jl. Jakarta:</strong> 081378888795</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Form Data -->
        <div class="flex justify-between px-3 py-1">
            <div class="flex flex-col gap-1 font-semibold">
                <div class="flex items-baseline gap-1 text-[11px] leading-none capitalize">
                    <span class="font-semibold min-w-[45px]">Dari</span>: <?=$pengiriman['nama_pengirim'] ?>
                </div>
                <div class="flex items-baseline gap-1 text-[11px] leading-none">
                    <span class="font-semibold min-w-[45px]">Alamat</span>: <?=$pengiriman['cabang_pengirim'] ?>
                </div>
                <div class="flex items-baseline gap-1 text-[11px] leading-none">
                    <span class="font-semibold min-w-[45px]">Telp/HP</span>: <?=$pengiriman['telp_pengirim']?>
                </div>
            </div>
            <div class="text-center">
                <div class="text-sm font-bold text-primary tracking-wide mb-1">SURAT PENGIRIMAN BARANG</div>
                <div class="flex items-center justify-center gap-2 text-xs font-bold">
                    <span class="text-primary">No. Resi:</span>
                    <span class="text-secondary text-base"><?=$pengiriman['no_resi']?></span>
                </div>
            </div>
            <div class="flex flex-col gap-1 font-semibold">
                <div class="flex items-baseline gap-1 text-[11px] leading-none capitalize">
                    <span class="font-semibold min-w-[45px]">Kepada</span>: <?=$pengiriman['nama_penerima']?>
                </div>
                <div class="flex items-baseline gap-1 text-[11px] leading-none">
                    <span class="font-semibold min-w-[45px]">Alamat</span>: <?=$pengiriman['cabang_penerima']?>
                </div>
                <div class="flex items-baseline gap-1 text-[11px] leading-none">
                    <span class="font-semibold min-w-[45px]">Telp/HP</span>: <?=$pengiriman['telp_penerima']?>
                </div>
            </div>
        </div>

        <!-- Table -->
        <div class="px-2 py-0.5 border-b border-black overflow-hidden">
            <table class="w-full border-collapse text-xs">
                <thead>
                    <tr>
                        <th class="border border-black px-1 py-0.5 bg-gray-100 font-bold text-center text-[7px]" style="width:6%;">No</th>
                        <th class="border border-black px-1 py-0.5 bg-gray-100 font-bold text-center text-[7px]" style="width:44%;">Nama Barang</th>
                        <th class="border border-black px-1 py-0.5 bg-gray-100 font-bold text-center text-[7px]" style="width:13%;">Berat</th>
                        <th class="border border-black px-1 py-0.5 bg-gray-100 font-bold text-center text-[7px]" style="width:13%;">Jumlah</th>
                        <th class="border border-black px-1 py-0.5 bg-gray-100 font-bold text-center text-[7px]" style="width:24%;">Metode Pembayaran</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="capitalize">
                        <td class="border border-black px-1 py-0.5 text-center h-[30px] align-top">1</td>
                        <td class="border border-black px-1 py-0.5 text-center h-[30px] align-top"><?=$pengiriman['nama_barang']?></td>
                        <td class="border border-black px-1 py-0.5 text-center h-[30px] align-top"><?=$pengiriman['berat']?> Kg</td>
                        <td class="border border-black px-1 py-0.5 text-center h-[30px] align-top"><?=$pengiriman['jumlah']?></td>
                        <td class="border border-black px-1 py-0.5 text-center h-[30px] align-top"><?=$pengiriman['pembayaran']?></td>
                    </tr>
                    <tr>
                        <td colspan="4" class="border border-black px-0.5 py-1 text-center font-semibold text-[11px]">Diskon</td>
                        <td class="border border-black px-0.5 py-1 text-center font-semibold text-[11px]"><?=$pengiriman['diskon']? $pengiriman['diskon']: 0 ?> %</td>
                    </tr>
                    <tr>
                        <td colspan="4" class="border border-black px-0.5 py-1 text-center font-bold text-[11px]">Total</td>
                        <td class="border border-black px-0.5 py-1 text-center font-bold text-[11px] total_tarif"><?=$pengiriman['total_tarif']?></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Bottom Section -->
        <div class="px-2 py-2 pb-1 flex gap-1.5 flex-grow overflow-hidden">
            <div class="flex-[0_0_45%] border border-black p-2 bg-white overflow-hidden flex flex-col justify-start">
                <ol class="list-decimal ml-2 text-[7px] leading-relaxed mb-0">
                    <li class="mb-0.5 text-justify">Barang rusak/pecah di ganti sesuai berapa % tingkat kerusakan dan barang hilang di ganti 10x biaya kirim max Rp. 2.000.000,-</li>
                    <li class="mb-0.5 text-justify">Tidak di perkenan kan membagi foto resi selain kepada penerima atau yang di beri wewenang di karena kan bersifat rahasia</li>
                    <li class="mb-0.5 text-justify">Pengiriman barang diambil di kantor perwakilan tujuan</li>
                    <li class="mb-0.5 text-justify">Dilarang mengirim Paket (barang) Terlarang, bisa dilaporkan ke pihak berwajib dan dikenakan sanksi pidana</li>
                </ol>
            </div>
            <div class="flex-1 grid grid-cols-3 gap-1">
                <div class="border border-black p-1 flex flex-col justify-between">
                    <div class="text-[6.5px] leading-tight flex-grow">
                        <strong>Kirim Tgl : <?=$pengiriman['tanggal']?></strong><br><br>
                        <strong>Pengirim,</strong><br><br>
                        <strong>HP/Telp : <?=$pengiriman['telp_pengirim']?></strong>
                    </div>
                    <div class="border-t border-black mt-1 h-5"></div>
                </div>
                <div class="border border-black p-1 flex flex-col justify-between">
                    <div class="font-bold text-[7px] text-center">Driver,</div>
                    <div class="border-t border-black mt-1 h-5"></div>
                </div>
                <div class="border border-black p-1 flex flex-col justify-between">
                    <div class="text-[6.5px] leading-tight flex-grow">
                        <strong>Diterima :</strong><br><br>
                        <strong>Penerima,</strong><br><br>
                        <strong>HP/Telp : <?=$pengiriman['telp_penerima']?></strong>
                    </div>
                    <div class="border-t border-black mt-1 h-5"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- RESI 2 -->
    <div class="w-full flex-1 flex flex-col border-2 border-black overflow-hidden">
        <!-- Header -->
        <div class="flex items-start border-b border-black">
            <div class="w-[70px] h-[70px] flex-shrink-0 flex items-center justify-center bg-white ms-4 mb-1">
                <img src="../../../assets/logo.jpg" alt="Logo Perusahaan" class="w-20 h-auto rounded-full">
            </div>
            <div class="flex-grow py-1.5">
                <div class="text-base font-bold text-primary text-center tracking-wide mb-0.5">PT. CENDANA LINTAS KARGO</div>
                <div class="flex justify-around text-[7px] leading-tight">
                    <div class="text-left">
                        <div><strong>Kantor Pusat:</strong> Jl. Cendana No.8 Samarinda</div>
                        <div><strong>HP:</strong> 082120406688</div>
                        <div><strong>Balikpapan:</strong> 081211220404 - 081127744474</div>
                        <div><strong>Sangatta:</strong> 082151224404 - 081250500026</div>
                    </div>
                    <div class="text-left">
                        <div><strong>Melak:</strong> 081251084448</div>
                        <div><strong>Ma. Wahau:</strong> 082350058686 - 082350058822</div>
                        <div><strong>Palaran:</strong> 08121367671</div>
                        <div><strong>Lambung:</strong> 082255102626</div>
                    </div>
                    <div class="text-left">
                        <div><strong>Lempake:</strong> 085393932050 - 085393932030</div>
                        <div><strong>Tenggarong:</strong> 08115802377 - 08115802399</div>
                        <div><strong>Jl. Jakarta:</strong> 081378888795</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Form Data -->
        <div class="flex justify-between px-3 py-1">
            <div class="flex flex-col gap-1 font-semibold">
                <div class="flex items-baseline gap-1 text-[11px] leading-none capitalize">
                    <span class="font-semibold min-w-[45px]">Dari</span>: <?=$pengiriman['nama_pengirim'] ?>
                </div>
                <div class="flex items-baseline gap-1 text-[11px] leading-none">
                    <span class="font-semibold min-w-[45px]">Alamat</span>: <?=$pengiriman['cabang_pengirim'] ?>
                </div>
                <div class="flex items-baseline gap-1 text-[11px] leading-none">
                    <span class="font-semibold min-w-[45px]">Telp/HP</span>: <?=$pengiriman['telp_pengirim']?>
                </div>
            </div>
            <div class="text-center">
                <div class="text-sm font-bold text-primary tracking-wide mb-1">SURAT PENGIRIMAN BARANG</div>
                <div class="flex items-center justify-center gap-2 text-xs font-bold">
                    <span class="text-primary">No. Resi:</span>
                    <span class="text-secondary text-base"><?=$pengiriman['no_resi']?></span>
                </div>
            </div>
            <div class="flex flex-col gap-1 font-semibold">
                <div class="flex items-baseline gap-1 text-[11px] leading-none capitalize">
                    <span class="font-semibold min-w-[45px]">Kepada</span>: <?=$pengiriman['nama_penerima']?>
                </div>
                <div class="flex items-baseline gap-1 text-[11px] leading-none">
                    <span class="font-semibold min-w-[45px]">Alamat</span>: <?=$pengiriman['cabang_penerima']?>
                </div>
                <div class="flex items-baseline gap-1 text-[11px] leading-none">
                    <span class="font-semibold min-w-[45px]">Telp/HP</span>: <?=$pengiriman['telp_penerima']?>
                </div>
            </div>
        </div>

        <!-- Table -->
        <div class="px-2 py-0.5 border-b border-black overflow-hidden">
            <table class="w-full border-collapse text-xs">
                <thead>
                    <tr>
                        <th class="border border-black px-1 py-0.5 bg-gray-100 font-bold text-center text-[7px]" style="width:6%;">No</th>
                        <th class="border border-black px-1 py-0.5 bg-gray-100 font-bold text-center text-[7px]" style="width:44%;">Nama Barang</th>
                        <th class="border border-black px-1 py-0.5 bg-gray-100 font-bold text-center text-[7px]" style="width:13%;">Berat</th>
                        <th class="border border-black px-1 py-0.5 bg-gray-100 font-bold text-center text-[7px]" style="width:13%;">Jumlah</th>
                        <th class="border border-black px-1 py-0.5 bg-gray-100 font-bold text-center text-[7px]" style="width:24%;">Metode Pembayaran</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="capitalize">
                        <td class="border border-black px-1 py-0.5 text-center h-[30px] align-top">1</td>
                        <td class="border border-black px-1 py-0.5 text-center h-[30px] align-top"><?=$pengiriman['nama_barang']?></td>
                        <td class="border border-black px-1 py-0.5 text-center h-[30px] align-top"><?=$pengiriman['berat']?> Kg</td>
                        <td class="border border-black px-1 py-0.5 text-center h-[30px] align-top"><?=$pengiriman['jumlah']?></td>
                        <td class="border border-black px-1 py-0.5 text-center h-[30px] align-top"><?=$pengiriman['pembayaran']?></td>
                    </tr>
                    <tr>
                        <td colspan="4" class="border border-black px-0.5 py-1 text-center font-semibold text-[11px]">Diskon</td>
                        <td class="border border-black px-0.5 py-1 text-center font-semibold text-[11px]"><?=$pengiriman['diskon']? $pengiriman['diskon']: 0 ?> %</td>
                    </tr>
                    <tr>
                        <td colspan="4" class="border border-black px-0.5 py-1 text-center font-bold text-[11px]">Total</td>
                        <td class="border border-black px-0.5 py-1 text-center font-bold text-[11px] total_tarif"><?=$pengiriman['total_tarif']?></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Bottom Section -->
        <div class="px-2 py-2 pb-1 flex gap-1.5 flex-grow overflow-hidden">
            <div class="flex-[0_0_45%] border border-black p-2 bg-white overflow-hidden flex flex-col justify-start">
                <ol class="list-decimal ml-2 text-[7px] leading-relaxed mb-0">
                    <li class="mb-0.5 text-justify">Barang rusak/pecah di ganti sesuai berapa % tingkat kerusakan dan barang hilang di ganti 10x biaya kirim max Rp. 2.000.000,-</li>
                    <li class="mb-0.5 text-justify">Tidak di perkenan kan membagi foto resi selain kepada penerima atau yang di beri wewenang di karena kan bersifat rahasia</li>
                    <li class="mb-0.5 text-justify">Pengiriman barang diambil di kantor perwakilan tujuan</li>
                    <li class="mb-0.5 text-justify">Dilarang mengirim Paket (barang) Terlarang, bisa dilaporkan ke pihak berwajib dan dikenakan sanksi pidana</li>
                </ol>
            </div>
            <div class="flex-1 grid grid-cols-3 gap-1">
                <div class="border border-black p-1 flex flex-col justify-between">
                    <div class="text-[6.5px] leading-tight flex-grow">
                        <strong>Kirim Tgl : <?=$pengiriman['tanggal']?></strong><br><br>
                        <strong>Pengirim,</strong><br><br>
                        <strong>HP/Telp : <?=$pengiriman['telp_pengirim']?></strong>
                    </div>
                    <div class="border-t border-black mt-1 h-5"></div>
                </div>
                <div class="border border-black p-1 flex flex-col justify-between">
                    <div class="font-bold text-[7px] text-center">Driver,</div>
                    <div class="border-t border-black mt-1 h-5"></div>
                </div>
                <div class="border border-black p-1 flex flex-col justify-between">
                    <div class="text-[6.5px] leading-tight flex-grow">
                        <strong>Diterima :</strong><br><br>
                        <strong>Penerima,</strong><br><br>
                        <strong>HP/Telp : <?=$pengiriman['telp_penerima']?></strong>
                    </div>
                    <div class="border-t border-black mt-1 h-5"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- RESI 3 -->
    <div class="w-full flex-1 flex flex-col border-2 border-black overflow-hidden">
        <!-- Header -->
        <div class="flex items-start border-b border-black">
            <div class="w-[70px] h-[70px] flex-shrink-0 flex items-center justify-center bg-white ms-4 mb-1">
                <img src="../../../assets/logo.jpg" alt="Logo Perusahaan" class="w-20 h-auto rounded-full">
            </div>
            <div class="flex-grow py-1.5">
                <div class="text-base font-bold text-primary text-center tracking-wide mb-0.5">PT. CENDANA LINTAS KARGO</div>
                <div class="flex justify-around text-[7px] leading-tight">
                    <div class="text-left">
                        <div><strong>Kantor Pusat:</strong> Jl. Cendana No.8 Samarinda</div>
                        <div><strong>HP:</strong> 082120406688</div>
                        <div><strong>Balikpapan:</strong> 081211220404 - 081127744474</div>
                        <div><strong>Sangatta:</strong> 082151224404 - 081250500026</div>
                    </div>
                    <div class="text-left">
                        <div><strong>Melak:</strong> 081251084448</div>
                        <div><strong>Ma. Wahau:</strong> 082350058686 - 082350058822</div>
                        <div><strong>Palaran:</strong> 08121367671</div>
                        <div><strong>Lambung:</strong> 082255102626</div>
                    </div>
                    <div class="text-left">
                        <div><strong>Lempake:</strong> 085393932050 - 085393932030</div>
                        <div><strong>Tenggarong:</strong> 08115802377 - 08115802399</div>
                        <div><strong>Jl. Jakarta:</strong> 081378888795</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Form Data -->
        <div class="flex justify-between px-3 py-1">
            <div class="flex flex-col gap-1 font-semibold">
                <div class="flex items-baseline gap-1 text-[11px] leading-none capitalize">
                    <span class="font-semibold min-w-[45px]">Dari</span>: <?=$pengiriman['nama_pengirim'] ?>
                </div>
                <div class="flex items-baseline gap-1 text-[11px] leading-none">
                    <span class="font-semibold min-w-[45px]">Alamat</span>: <?=$pengiriman['cabang_pengirim'] ?>
                </div>
                <div class="flex items-baseline gap-1 text-[11px] leading-none">
                    <span class="font-semibold min-w-[45px]">Telp/HP</span>: <?=$pengiriman['telp_pengirim']?>
                </div>
            </div>
            <div class="text-center">
                <div class="text-sm font-bold text-primary tracking-wide mb-1">SURAT PENGIRIMAN BARANG</div>
                <div class="flex items-center justify-center gap-2 text-xs font-bold">
                    <span class="text-primary">No. Resi:</span>
                    <span class="text-secondary text-base"><?=$pengiriman['no_resi']?></span>
                </div>
            </div>
            <div class="flex flex-col gap-1 font-semibold">
                <div class="flex items-baseline gap-1 text-[11px] leading-none capitalize">
                    <span class="font-semibold min-w-[45px]">Kepada</span>: <?=$pengiriman['nama_penerima']?>
                </div>
                <div class="flex items-baseline gap-1 text-[11px] leading-none">
                    <span class="font-semibold min-w-[45px]">Alamat</span>: <?=$pengiriman['cabang_penerima']?>
                </div>
                <div class="flex items-baseline gap-1 text-[11px] leading-none">
                    <span class="font-semibold min-w-[45px]">Telp/HP</span>: <?=$pengiriman['telp_penerima']?>
                </div>
            </div>
        </div>

        <!-- Table -->
        <div class="px-2 py-0.5 border-b border-black overflow-hidden">
            <table class="w-full border-collapse text-xs">
                <thead>
                    <tr>
                        <th class="border border-black px-1 py-0.5 bg-gray-100 font-bold text-center text-[7px]" style="width:6%;">No</th>
                        <th class="border border-black px-1 py-0.5 bg-gray-100 font-bold text-center text-[7px]" style="width:44%;">Nama Barang</th>
                        <th class="border border-black px-1 py-0.5 bg-gray-100 font-bold text-center text-[7px]" style="width:13%;">Berat</th>
                        <th class="border border-black px-1 py-0.5 bg-gray-100 font-bold text-center text-[7px]" style="width:13%;">Jumlah</th>
                        <th class="border border-black px-1 py-0.5 bg-gray-100 font-bold text-center text-[7px]" style="width:24%;">Metode Pembayaran</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="capitalize">
                        <td class="border border-black px-1 py-0.5 text-center h-[30px] align-top">1</td>
                        <td class="border border-black px-1 py-0.5 text-center h-[30px] align-top"><?=$pengiriman['nama_barang']?></td>
                        <td class="border border-black px-1 py-0.5 text-center h-[30px] align-top"><?=$pengiriman['berat']?> Kg</td>
                        <td class="border border-black px-1 py-0.5 text-center h-[30px] align-top"><?=$pengiriman['jumlah']?></td>
                        <td class="border border-black px-1 py-0.5 text-center h-[30px] align-top"><?=$pengiriman['pembayaran']?></td>
                    </tr>
                    <tr>
                        <td colspan="4" class="border border-black px-0.5 py-1 text-center font-semibold text-[11px]">Diskon</td>
                        <td class="border border-black px-0.5 py-1 text-center font-semibold text-[11px]"><?=$pengiriman['diskon']? $pengiriman['diskon']: 0 ?> %</td>
                    </tr>
                    <tr>
                        <td colspan="4" class="border border-black px-0.5 py-1 text-center font-bold text-[11px]">Total</td>
                        <td class="border border-black px-0.5 py-1 text-center font-bold text-[11px] total_tarif"><?=$pengiriman['total_tarif']?></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Bottom Section -->
        <div class="px-2 py-2 pb-1 flex gap-1.5 flex-grow overflow-hidden">
            <div class="flex-[0_0_45%] border border-black p-2 bg-white overflow-hidden flex flex-col justify-start">
                <ol class="list-decimal ml-2 text-[7px] leading-relaxed mb-0">
                    <li class="mb-0.5 text-justify">Barang rusak/pecah di ganti sesuai berapa % tingkat kerusakan dan barang hilang di ganti 10x biaya kirim max Rp. 2.000.000,-</li>
                    <li class="mb-0.5 text-justify">Tidak di perkenan kan membagi foto resi selain kepada penerima atau yang di beri wewenang di karena kan bersifat rahasia</li>
                    <li class="mb-0.5 text-justify">Pengiriman barang diambil di kantor perwakilan tujuan</li>
                    <li class="mb-0.5 text-justify">Dilarang mengirim Paket (barang) Terlarang, bisa dilaporkan ke pihak berwajib dan dikenakan sanksi pidana</li>
                </ol>
            </div>
            <div class="flex-1 grid grid-cols-3 gap-1">
                <div class="border border-black p-1 flex flex-col justify-between">
                    <div class="text-[6.5px] leading-tight flex-grow">
                        <strong>Kirim Tgl : <?=$pengiriman['tanggal']?></strong><br><br>
                        <strong>Pengirim,</strong><br><br>
                        <strong>HP/Telp : <?=$pengiriman['telp_pengirim']?></strong>
                    </div>
                    <div class="border-t border-black mt-1 h-5"></div>
                </div>
                <div class="border border-black p-1 flex flex-col justify-between">
                    <div class="font-bold text-[7px] text-center">Driver,</div>
                    <div class="border-t border-black mt-1 h-5"></div>
                </div>
                <div class="border border-black p-1 flex flex-col justify-between">
                    <div class="text-[6.5px] leading-tight flex-grow">
                        <strong>Diterima :</strong><br><br>
                        <strong>Penerima,</strong><br><br>
                        <strong>HP/Telp : <?=$pengiriman['telp_penerima']?></strong>
                    </div>
                    <div class="border-t border-black mt-1 h-5"></div>
                </div>
            </div>
        </div>
    </div>

</div>

<script>
    // Format total tarif ke Rupiah
    const hargaElements = document.querySelectorAll('.total_tarif');
    hargaElements.forEach(el => {
        let value = parseFloat(el.textContent);
        let formatted = value.toLocaleString('id-ID', {
            style: 'currency',
            currency: 'IDR'
        });
        el.textContent = formatted;
    });
    
    // Auto print on load (uncomment if needed)
    window.addEventListener('load', () => {
        window.print();
    });
</script>
</body>
</html>
