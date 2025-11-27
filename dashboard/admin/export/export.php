<?php
// File: dashboard/admin/export/export.php
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

// Ambil data user yang login
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Cek apakah sudah closing hari ini
$today = date('Y-m-d');
$stmt_closing_check = $conn->prepare("SELECT id FROM closing WHERE id_user = ? AND tanggal_closing = ?");
$stmt_closing_check->bind_param('is', $user_id, $today);
$stmt_closing_check->execute();
$is_closed = $stmt_closing_check->get_result()->num_rows > 0;
$stmt_closing_check->close();

// Jika belum closing, redirect dengan error
if (!$is_closed) {
    header("Location: ../index.php?error=belum_closing");
    exit;
}

// Export hanya data hari ini
$date_condition = "AND DATE(p.tanggal) = CURDATE()";
$periode_display = date('d F Y');

// Query utama: ambil data pengiriman berdasarkan user_id (admin yang login) + nama driver
$query = "SELECT 
            p.id AS id,
            p.no_resi,
            p.nama_barang,
            p.nama_pengirim,
            p.telp_pengirim,
            p.nama_penerima,
            p.telp_penerima,
            p.cabang_penerima AS tujuan,
            p.cabang_pengirim AS cabang,
            p.pembayaran AS metode_pembayaran,
            p.total_tarif AS total,
            p.tanggal,
            p.status,
            u.username AS dibuat_oleh,
            sj.driver AS nama_driver
          FROM pengiriman p
          LEFT JOIN user u ON p.id_user = u.id
          LEFT JOIN detail_surat_jalan dsj ON p.id = dsj.id_pengiriman
          LEFT JOIN surat_jalan sj ON dsj.id_surat_jalan = sj.id AND sj.status = 'diberangkatkan'
          WHERE p.id_user = ?
            AND p.status != 'dibatalkan'
            $date_condition
          ORDER BY p.tanggal ASC";

$stmt = $conn->prepare($query);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: ../index.php?error=no_data");
    exit();
}

// === Header untuk file Excel ===
$filename = "Laporan_Pendapatan_{$username}_" . date('Y-m-d_His') . ".xls";
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename={$filename}");
header("Pragma: no-cache");
header("Expires: 0");

// === Buat tabel Excel ===
echo "<table border='1' cellspacing='0' cellpadding='5'>";

// Judul laporan
echo "<tr style='background:#dc3545; color:white; font-weight:bold;'>
        <th colspan='15'>LAPORAN PENDAPATAN ADMIN " . strtoupper($username) . "</th>
      </tr>";
echo "<tr><td colspan='15' style='background:#f8d7da;'>Periode: " . htmlspecialchars($periode_display) . "</td></tr>";

// Header kolom
echo "<tr style='background:#f2f2f2; font-weight:bold;'>
        <th>No</th>
        <th>Resi</th>
        <th>Tanggal</th>
        <th>Nama Barang</th>
        <th>Pengirim</th>
        <th>Telp Pengirim</th>
        <th>Penerima</th>
        <th>Telp Penerima</th>
        <th>Asal</th>
        <th>Tujuan</th>
        <th>Status</th>
        <th>Pembayaran</th>
        <th>Dibuat</th>
        <th>Driver</th>
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
    echo "<td style='mso-number-format:\"\@\"'>" . htmlspecialchars($row['telp_pengirim']) . "</td>";
    echo "<td>" . htmlspecialchars($row['nama_penerima']) . "</td>";
    echo "<td style='mso-number-format:\"\@\"'>" . htmlspecialchars($row['telp_penerima']) . "</td>";
    echo "<td>" . htmlspecialchars($row['cabang']) . "</td>";
    echo "<td>" . htmlspecialchars($row['tujuan']) . "</td>";
    switch ($row['status']) {
        case 'bkd':
            $status = 'BKD';
            break;
        case 'dalam pengiriman':
            $status = 'DP';
            break;
        case 'sampai tujuan':
            $status = 'ST';
            break;
        case 'pod':
            $status = 'POD';
            break;
        case 'dibatalkan':
            $status = 'Batal';
            break;
        default:
            $status = ucfirst($row['status']);
            break;
    }
    echo "<td>" . $status . "</td>";
    switch ($row['metode_pembayaran']) {
        case 'cash':
            $metode = 'Cash';
            break;
        case 'transfer':
            $metode = 'TF';
            break;
        case 'bayar di tempat':
            $metode = 'BT';
            break;
        default:
            $metode = ucfirst($row['metode_pembayaran']);
            break;
    }
    echo "<td>" . $metode . "</td>";
    echo "<td>" . htmlspecialchars($row['dibuat_oleh'] ?? '-') . "</td>";
    echo "<td>" . htmlspecialchars($row['nama_driver'] ?? '') . "</td>";
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
        <td colspan='14' align='right'>Total Cash</td>
        <td align='right' style='mso-number-format:\"#,##0\"'>" . $totalCash . "</td>
      </tr>";
echo "<tr style='font-weight:bold; background:#f9f9f9;'>
        <td colspan='14' align='right'>Total Transfer</td>
        <td align='right' style='mso-number-format:\"#,##0\"'>" . $totalTransfer . "</td>
      </tr>";
echo "<tr style='font-weight:bold; background:#f9f9f9;'>
        <td colspan='14' align='right'>Total Bayar di Tempat</td>
        <td align='right' style='mso-number-format:\"#,##0\"'>" . $totalCOD . "</td>
      </tr>";
echo "<tr style='font-weight:bold; background:#e9ecef;'>
        <td colspan='14' align='right'>TOTAL PENDAPATAN</td>
        <td align='right' style='mso-number-format:\"#,##0\"'>" . $totalAll . "</td>
      </tr>";

echo "</table>";

$stmt->close();
$conn->close();
?>
