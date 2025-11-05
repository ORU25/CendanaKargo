<?php
// File: dashboard/superSuperAdmin/export/export.php
include '../../../config/database.php';

// Ambil parameter cabang & filter
$cabang = isset($_GET['cabang']) ? trim($_GET['cabang']) : '';
$periode = isset($_GET['periode']) ? trim($_GET['periode']) : '';
$bulan = isset($_GET['bulan']) ? trim($_GET['bulan']) : '';

if (empty($cabang)) {
    die("❌ Nama cabang tidak ditemukan.");
}

// Query utama: ambil data pengiriman untuk cabang tersebut
$query = "SELECT 
            id AS id,
            no_resi,
            nama_barang,
            nama_pengirim,
            nama_penerima,
            cabang_penerima AS tujuan,
            cabang_pengirim AS cabang,
            pembayaran AS metode_pembayaran,
            total_tarif AS total,
            tanggal,
            status
          FROM Pengiriman
          WHERE cabang_pengirim = ?
            AND status != 'dibatalkan'";

$params = [$cabang];
$types = 's';

// Filter berdasarkan bulan (jika ada)
if (!empty($bulan)) {
    $query .= " AND DATE_FORMAT(tanggal, '%Y-%m') = ?";
    $params[] = $bulan;
    $types .= 's';
}

$query .= " ORDER BY tanggal DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("⚠️ Tidak ada data pendapatan untuk cabang <b>$cabang</b>.");
}

// === Header untuk file Excel ===
$filename = "Laporan_Pendapatan_{$cabang}_" . ($bulan ?: date('Y-m')) . ".xls";
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename={$filename}");
header("Pragma: no-cache");
header("Expires: 0");

// === Buat tabel Excel ===
echo "<table border='1' cellspacing='0' cellpadding='5'>";

// Judul laporan
echo "<tr style='background:#dc3545; color:white; font-weight:bold;'>
        <th colspan='11'>LAPORAN PENDAPATAN CABANG " . strtoupper($cabang) . "</th>
      </tr>";
if (!empty($bulan)) {
    echo "<tr><td colspan='11' style='background:#f8d7da;'>Periode: " . htmlspecialchars($bulan) . "</td></tr>";
}

// Header kolom
echo "<tr style='background:#f2f2f2; font-weight:bold;'>
        <th>No</th>
        <th>No Resi</th>
        <th>Nama Barang</th>
        <th>Pengirim</th>
        <th>Penerima</th>
        <th>Tujuan</th>
        <th>Metode Pembayaran</th>
        <th>Total (Rp)</th>
        <th>Tanggal</th>
        <th>Status</th>
        <th>Cabang</th>
      </tr>";

$no = 1;
$totalAll = $totalCash = $totalTransfer = $totalCOD = 0;

while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>{$no}</td>";
    echo "<td>" . htmlspecialchars($row['no_resi']) . "</td>";
    echo "<td>" . htmlspecialchars($row['nama_barang']) . "</td>";
    echo "<td>" . htmlspecialchars($row['nama_pengirim']) . "</td>";
    echo "<td>" . htmlspecialchars($row['nama_penerima']) . "</td>";
    echo "<td>" . htmlspecialchars($row['tujuan']) . "</td>";
    echo "<td>" . htmlspecialchars($row['metode_pembayaran']) . "</td>";
    echo "<td align='right'>" . number_format($row['total'], 0, ',', '.') . "</td>";
    echo "<td>" . date('d/m/Y H:i', strtotime($row['tanggal'])) . "</td>";
    echo "<td>" . htmlspecialchars($row['status']) . "</td>";
    echo "<td>" . htmlspecialchars($row['cabang']) . "</td>";
    echo "</tr>";

    // Akumulasi total
    $totalAll += $row['total'];
    if ($row['metode_pembayaran'] === 'cash') $totalCash += $row['total'];
    if ($row['metode_pembayaran'] === 'transfer') $totalTransfer += $row['total'];
    if ($row['metode_pembayaran'] === 'bayar di tempat') $totalCOD += $row['total'];
    $no++;
}

// === Baris total ===
echo "<tr style='font-weight:bold; background:#f9f9f9;'>
        <td colspan='7' align='right'>Total Cash</td>
        <td align='right'>" . number_format($totalCash, 0, ',', '.') . "</td>
        <td colspan='3'></td>
      </tr>";
echo "<tr style='font-weight:bold; background:#f9f9f9;'>
        <td colspan='7' align='right'>Total Transfer</td>
        <td align='right'>" . number_format($totalTransfer, 0, ',', '.') . "</td>
        <td colspan='3'></td>
      </tr>";
echo "<tr style='font-weight:bold; background:#f9f9f9;'>
        <td colspan='7' align='right'>Total Bayar di Tempat</td>
        <td align='right'>" . number_format($totalCOD, 0, ',', '.') . "</td>
        <td colspan='3'></td>
      </tr>";
echo "<tr style='font-weight:bold; background:#e9ecef;'>
        <td colspan='7' align='right'>TOTAL PENDAPATAN</td>
        <td align='right'>" . number_format($totalAll, 0, ',', '.') . "</td>
        <td colspan='3'></td>
      </tr>";

echo "</table>";

$stmt->close();
$conn->close();
?>
