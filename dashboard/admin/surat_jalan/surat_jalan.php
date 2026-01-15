<?php
session_start();
if (!isset($_SESSION['username']) || !isset($_SESSION['user_id'])) {
    header("Location: ../../../auth/login");
    exit;
}

if (isset($_SESSION['role']) && $_SESSION['role'] !== 'admin') {
    header("Location: ../../../?error=unauthorized");
    exit;
}

include '../../../config/database.php';

$id_surat_jalan = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id_surat_jalan == 0) {
    header("Location: index.php?error=not_found");
    exit;
}

// Ambil data surat jalan
$sql_sj = "SELECT * FROM surat_jalan WHERE id = ?";
$stmt_sj = $conn->prepare($sql_sj);
$stmt_sj->bind_param("i", $id_surat_jalan);
$stmt_sj->execute();
$result_sj = $stmt_sj->get_result();
$sj = $result_sj->fetch_assoc();

if (!$sj) {
    header("Location: index.php?error=not_found");
    exit;
}

// Ambil detail pengiriman (maksimal 20)
$sql_detail = "SELECT p.* FROM pengiriman p 
               JOIN detail_surat_jalan d ON p.id = d.id_pengiriman 
               WHERE d.id_surat_jalan = ? 
               ORDER BY p.no_resi ASC 
               LIMIT 20";
$stmt_detail = $conn->prepare($sql_detail);
$stmt_detail->bind_param("i", $id_surat_jalan);
$stmt_detail->execute();
$result_detail = $stmt_detail->get_result();
$pengirimens = ($result_detail->num_rows > 0) ? $result_detail->fetch_all(MYSQLI_ASSOC) : [];

// Fungsi untuk kapitalisasi setiap kata
function capitalizeWords($string) {
    return mb_convert_case(mb_strtolower($string), MB_CASE_TITLE, "UTF-8");
}

// Fungsi untuk memotong teks panjang
function truncateText($text, $maxLength = 30) {
    $text = trim($text);
    if (mb_strlen($text) <= $maxLength) {
        return $text;
    }
    return mb_substr($text, 0, $maxLength - 3) . '...';
}

// Fungsi untuk menyingkat nama (khusus untuk nama orang)
function abbreviateName($name) {
    $name = trim($name);
    $words = preg_split('/\s+/', $name);
    $count = count($words);
    
    // Jika nama hanya 1-2 kata, kembalikan apa adanya
    if ($count <= 2) {
        return $name;
    }
    
    // Jika nama 3 kata atau lebih
    if ($count >= 3) {
        // Ambil 2 kata pertama utuh
        $result = $words[0] . ' ' . $words[1];
        
        // Sisanya disingkat dengan inisial + titik
        for ($i = 2; $i < $count; $i++) {
            $initial = mb_substr($words[$i], 0, 1);
            $result .= ' ' . $initial . '.';
        }
        
        return $result;
    }
    
    return $name;
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Surat Jalan - <?= htmlspecialchars($sj['no_surat_jalan']); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html,
        body {
            height: 100%;
        }

        body {
            font-family: Arial, sans-serif;
            background-color: #fff;
            margin: 0;
            padding: 0;
        }

        @page {
            size: A4 landscape;
            margin: 0;
        }

        .page-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0;
            width: 100%;
            height: 210mm;
            margin: 0;
            padding: 0;
            page-break-after: always;
            background: white;
        }

        .empty-column {
            background: white;
            border: none;
            order: 1; /* Kolom kosong di kiri */
        }

        .surat-jalan {
            border: 2px solid black;
            display: flex;
            flex-direction: column;
            height: 100%;
            padding: 8px;
            background: white;
            order: 2; /* Surat jalan di kanan */
        }

        .header {
            display: flex;
            align-items: center;
            gap: 8px;
            flex: 0 0 auto;
        }

        .logo img {
            width: 48px;
            height: auto;
        }

        .header-text {
            text-align: center;
            flex: 1 1 auto;
        }

        .header-text h1 {
            font-size: 14px;
            color: red;
            font-weight: bold;
            margin-bottom: 2px;
        }

        .header-text h2 {
            font-size: 12px;
            color: black;
            font-weight: bold;
            text-decoration: underline;
            margin-bottom: 2px;
        }

        .header-text p {
            font-size: 11px;
            color: red;
            margin: 0;
        }

        .form-info {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 6px;
            margin-top: 6px;
            margin-bottom: 6px;
            font-size: 10px;
            flex: 0 0 auto;
        }

        .form-info label {
            font-weight: bold;
            min-width: 40px;
        }

        .form-info .value {
            border: none;
            border-bottom: 1px solid #333;
            padding: 2px;
            font-size: 10px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .table-section {
            flex: 1 1 auto;
            display: flex;
            flex-direction: column;
            margin: 4px 0;
            min-height: 0;
        }

        .surat-jalan table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
            table-layout: fixed;
        }

        .surat-jalan th {
            border: 2px solid black;
            padding: 4px;
            background: #fff;
            color: black;
            font-weight: bold;
            text-align: left;
        }

        .surat-jalan td {
            border: 1px solid black;
            padding: 3px;
            vertical-align: middle;
            height: 26px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .surat-jalan thead {
            display: table-header-group;
        }

        .surat-jalan tbody {
            display: table-row-group;
        }

        .footer-section {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 6px;
            margin-top: 20px;
            flex: 0 0 auto;
            font-size: 9px;
        }

        .footer-item {
            text-align: center;
            position: relative;
        }

        .footer-item label {
            display: block;
            font-weight: bold;
            margin-bottom: 80px;
        }

        .footer-item .name {
            display: block;
            margin-bottom: 4px;
            font-weight: normal;
            min-height: 12px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            padding: 0 5px;
        }

        .signature-line {
            border-bottom: 1px solid #333;
            margin: 0 12px;
        }

        @media print {
            body {
                margin: 0;
                padding: 0;
                background: white;
            }

            .page-container {
                height: 210mm;
                margin: 0;
                padding: 0;
            }

            .surat-jalan {
                padding: 8px;
                box-sizing: border-box;
            }

            @page {
                size: A4 landscape;
                margin: 0;
            }
        }
    </style>
</head>

<body onload="window.print()">
    <div class="page-container">
        <div class="surat-jalan">
            <div class="header">
                <div class="logo"><img src="../../../assets/logo.jpg" alt="Logo"></div>
                <div class="header-text">
                    <h1>PT. CENDANA LINTAS KARGO</h1>
                    <h2>SURAT JALAN BARANG</h2>
                    <p>NO. : <?= htmlspecialchars($sj['no_surat_jalan']); ?></p>
                </div>
            </div>

            <div class="form-info">
                <div>
                    <label>TGL :</label>
                    <div class="value"><?= date('d/m/Y', strtotime($sj['tanggal'])); ?></div>
                </div>
                <div>
                    <label>DARI :</label>
                    <div class="value"><?= htmlspecialchars(capitalizeWords($sj['cabang_pengirim'])); ?></div>
                </div>
                <div>
                    <label>TUJUAN :</label>
                    <div class="value"><?= htmlspecialchars(capitalizeWords($sj['cabang_penerima'])); ?></div>
                </div>
            </div>

            <div class="table-section">
                <table>
                    <thead>
                        <tr>
                            <th style="width:6%;">NO</th>
                            <th style="width:20%;">NO. RESI</th>
                            <th style="width:54%;">NAMA BARANG</th>
                            <th style="width:20%;">BANYAKNYA</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $no = 1;
                        foreach ($pengirimens as $p):
                            ?>
                            <tr>
                                <td style="text-align: center;"><?= $no++; ?></td>
                                <td><?= htmlspecialchars($p['no_resi']); ?></td>
                                <td><?= htmlspecialchars(truncateText(capitalizeWords($p['nama_barang']), 60)); ?></td>
                                <td><?= htmlspecialchars($p['jumlah']); ?></td>
                            </tr>
                        <?php endforeach; ?>

                        <?php
                        // Tambahkan baris kosong sampai total 20 baris
                        $sisa_baris = 20 - count($pengirimens);
                        for ($i = 0; $i < $sisa_baris; $i++):
                            ?>
                            <tr>
                                <td>&nbsp;</td>
                                <td>&nbsp;</td>
                                <td>&nbsp;</td>
                                <td>&nbsp;</td>
                            </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
            </div>

            <div class="footer-section">
                <div class="footer-item">
                    <label>PENGIRIM</label>
                    <span class="name">&nbsp;</span>
                    <div class="signature-line"></div>
                </div>
                <div class="footer-item">
                    <label>SUPIR</label>
                    <span class="name"><?= htmlspecialchars(abbreviateName(capitalizeWords($sj['driver']))); ?></span>
                    <div class="signature-line"></div>
                </div>
                <div class="footer-item">
                    <label>PENERIMA</label>
                    <span class="name">&nbsp;</span>
                    <div class="signature-line"></div>
                </div>
            </div>
        </div>

        <div class="empty-column"></div>
    </div>
</body>

</html>