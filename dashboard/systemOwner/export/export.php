<?php
// File: dashboard/systemOwner/export/export.php
include '../../../config/database.php';

// Ambil parameter cabang & filter baru
$cabang = isset($_GET['cabang']) ? trim($_GET['cabang']) : '';
$filter_type = isset($_GET['filter_type']) ? trim($_GET['filter_type']) : 'bulan_ini';
$filter_value = isset($_GET['filter_value']) ? trim($_GET['filter_value']) : '';

if (empty($cabang)) {
    header("Location: ../index.php?error=no_data");
    exit();
}

// Setup kondisi WHERE berdasarkan filter
$date_condition = '';
$periode_display = '';

switch ($filter_type) {
    case 'hari_ini':
        $date_condition = "AND DATE(tanggal) = CURDATE()";
        $periode_display = date('d F Y');
        break;
    
    case 'bulan_ini':
        $date_condition = "AND DATE_FORMAT(tanggal, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')";
        $periode_display = date('F Y');
        break;
    
    case 'tanggal_spesifik':
        if (!empty($filter_value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filter_value)) {
            $date_condition = "AND DATE(tanggal) = '" . $filter_value . "'";
            $periode_display = date('d F Y', strtotime($filter_value));
        } else {
            $date_condition = "AND DATE_FORMAT(tanggal, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')";
            $periode_display = date('F Y');
        }
        break;
    
    case 'bulan_spesifik':
        if (!empty($filter_value) && preg_match('/^\d{4}-\d{2}$/', $filter_value)) {
            $date_condition = "AND DATE_FORMAT(tanggal, '%Y-%m') = '" . $filter_value . "'";
            $periode_display = date('F Y', strtotime($filter_value . '-01'));
        } else {
            $date_condition = "AND DATE_FORMAT(tanggal, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')";
            $periode_display = date('F Y');
        }
        break;
    
    default:
        $date_condition = "AND DATE_FORMAT(tanggal, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')";
        $periode_display = date('F Y');
        break;
}

// Query utama: ambil data pengiriman untuk cabang tersebut + username pembuat
$query = "SELECT 
            p.id AS id,
            p.no_resi,
            p.nama_barang,
            p.nama_pengirim,
            p.nama_penerima,
            p.cabang_penerima AS tujuan,
            p.cabang_pengirim AS cabang,
            p.pembayaran AS metode_pembayaran,
            p.total_tarif AS total,
            p.tanggal,
            p.status,
            u.username AS dibuat_oleh
          FROM Pengiriman p
          LEFT JOIN User u ON p.id_user = u.id
          WHERE p.cabang_pengirim = ?
            AND p.status != 'dibatalkan'
            $date_condition
          ORDER BY p.tanggal ASC";

$stmt = $conn->prepare($query);
$stmt->bind_param('s', $cabang);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: ../index.php?error=no_data");
    exit();
}

// === Header untuk file Excel ===
$filename = "Laporan_Pendapatan_{$cabang}_" . date('Y-m-d_His') . ".xls";
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename={$filename}");
header("Pragma: no-cache");
header("Expires: 0");

// === Buat tabel Excel ===
echo "<table border='1' cellspacing='0' cellpadding='5'>";

// Judul laporan
echo "<tr style='background:#dc3545; color:white; font-weight:bold;'>
        <th colspan='12'>LAPORAN PENDAPATAN CABANG " . strtoupper($cabang) . "</th>
      </tr>";
echo "<tr><td colspan='12' style='background:#f8d7da;'>Periode: " . htmlspecialchars($periode_display) . "</td></tr>";

// Header kolom
echo "<tr style='background:#f2f2f2; font-weight:bold;'>
        <th>No</th>
        <th>No Resi</th>
        <th>Tanggal</th>
        <th>Nama Barang</th>
        <th>Pengirim</th>
        <th>Penerima</th>
        <th>Asal</th>
        <th>Tujuan</th>
        <th>Status</th>
        <th>Metode Pembayaran</th>
        <th>Dibuat Oleh</th>
        <th>Total (Rp)</th>
      </tr>";

$no = 1;
$totalAll = $totalCash = $totalTransfer = $totalCOD = 0;

while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>{$no}</td>";
    echo "<td>" . htmlspecialchars($row['no_resi']) . "</td>";
    echo "<td>" . date('d/m/Y H:i', strtotime($row['tanggal'])) . "</td>";
    echo "<td>" . htmlspecialchars($row['nama_barang']) . "</td>";
    echo "<td>" . htmlspecialchars($row['nama_pengirim']) . "</td>";
    echo "<td>" . htmlspecialchars($row['nama_penerima']) . "</td>";
    echo "<td>" . htmlspecialchars($row['cabang']) . "</td>";
    echo "<td>" . htmlspecialchars($row['tujuan']) . "</td>";
    echo "<td>" . htmlspecialchars($row['status']) . "</td>";
    echo "<td>" . htmlspecialchars($row['metode_pembayaran']) . "</td>";
    echo "<td>" . htmlspecialchars($row['dibuat_oleh'] ?? '-') . "</td>";
    echo "<td align='right' style='mso-number-format:\"#,##0\"'>" . $row['total'] . "</td>";
    echo "</tr>";

    // Akumulasi total
    $totalAll += (float)$row['total'];
    if ($row['metode_pembayaran'] === 'cash') $totalCash += (float)$row['total'];
    if ($row['metode_pembayaran'] === 'transfer') $totalTransfer += (float)$row['total'];
    if ($row['metode_pembayaran'] === 'bayar di tempat') $totalCOD += (float)$row['total'];
    $no++;
}

// === Baris total ===
echo "<tr style='font-weight:bold; background:#f9f9f9;'>
        <td colspan='11' align='right'>Total Cash</td>
        <td align='right' style='mso-number-format:\"#,##0\"'>" . $totalCash . "</td>
      </tr>";
echo "<tr style='font-weight:bold; background:#f9f9f9;'>
        <td colspan='11' align='right'>Total Transfer</td>
        <td align='right' style='mso-number-format:\"#,##0\"'>" . $totalTransfer . "</td>
      </tr>";
echo "<tr style='font-weight:bold; background:#f9f9f9;'>
        <td colspan='11' align='right'>Total Bayar di Tempat</td>
        <td align='right' style='mso-number-format:\"#,##0\"'>" . $totalCOD . "</td>
      </tr>";
echo "<tr style='font-weight:bold; background:#e9ecef;'>
        <td colspan='11' align='right'>TOTAL PENDAPATAN</td>
        <td align='right' style='mso-number-format:\"#,##0\"'>" . $totalAll . "</td>
      </tr>";

echo "</table>";

$stmt->close();
$conn->close();
?>
