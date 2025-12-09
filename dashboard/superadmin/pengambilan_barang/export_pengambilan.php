<?php
session_start();

if (!isset($_SESSION['username']) || !isset($_SESSION['user_id'])) {
    header("Location: ../../../auth/login");
    exit;
}

if (isset($_SESSION['role']) && $_SESSION['role'] !== 'superAdmin') {
    header("Location: ../../../?error=unauthorized");
    exit;
}

include '../../../config/database.php';

// Ambil data user yang login
$cabang_user = $_SESSION['cabang'] ?? '';
$id_cabang_user = $_SESSION['id_cabang'] ?? 0;

if (empty($cabang_user) || $id_cabang_user == 0) {
    die("Cabang user tidak ditemukan di session.");
}

// Ambil parameter tanggal
$tanggal = isset($_GET['tanggal']) ? $_GET['tanggal'] : date('Y-m-d');

// Validasi format tanggal
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
    die("Format tanggal tidak valid.");
}

// Query data pengambilan barang berdasarkan tanggal
$stmt = $conn->prepare("
    SELECT 
        pg.no_resi,
        pg.nama_pengambil,
        pg.telp_pengambil,
        pg.tanggal as tanggal_pengambilan,
        p.nama_barang,
        p.berat,
        p.jumlah,
        p.nama_pengirim,
        p.telp_pengirim,
        p.nama_penerima,
        p.telp_penerima,
        p.cabang_pengirim,
        p.cabang_penerima,
        p.pembayaran,
        p.total_tarif,
        p.status,
        u.username as user_pengambil
    FROM pengambilan pg
    INNER JOIN pengiriman p ON pg.no_resi = p.no_resi
    LEFT JOIN user u ON pg.id_user = u.id
    WHERE DATE(pg.tanggal) = ?
    AND p.id_cabang_penerima = ?
    ORDER BY pg.tanggal DESC
");

$stmt->bind_param('si', $tanggal, $id_cabang_user);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Cek jika tidak ada data
if (count($data) == 0) {
    header("Location: ./?error=no_data&tanggal=" . urlencode($tanggal));
    exit;
}

// Set header untuk download Excel
$filename = "Pengambilan_Barang_" . $cabang_user . "_" . date('d-m-Y', strtotime($tanggal)) . ".xls";
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");

// Format tanggal untuk tampilan
$tanggal_display = date('d F Y', strtotime($tanggal));

// Buat tabel Excel
echo "<table border='1' cellspacing='0' cellpadding='5'>";

// Judul laporan
echo "<tr style='background:#dc3545; color:white; font-weight:bold;'>
        <th colspan='18'>LAPORAN PENGAMBILAN BARANG - " . strtoupper(htmlspecialchars($cabang_user)) . "</th>
      </tr>";
echo "<tr><td colspan='18' style='background:#f8d7da;'>Periode: " . htmlspecialchars($tanggal_display) . "</td></tr>";

// Header kolom
echo "<tr style='background:#f2f2f2; font-weight:bold;'>
        <th>No</th>
        <th>No Resi</th>
        <th>Nama Pengambil</th>
        <th>Telp Pengambil</th>
        <th>Waktu Pengambilan</th>
        <th>Nama Barang</th>
        <th>Berat (kg)</th>
        <th>Jumlah</th>
        <th>Pengirim</th>
        <th>Telp Pengirim</th>
        <th>Penerima</th>
        <th>Telp Penerima</th>
        <th>Asal</th>
        <th>Tujuan</th>
        <th>Pembayaran</th>
        <th>Total Tarif</th>
        <th>Status</th>
        <th>User</th>
      </tr>";

$no = 1;
$total_berat = 0;
$total_tarif = 0;

foreach ($data as $row) {
    echo "<tr>";
    echo "<td>{$no}</td>";
    echo "<td>" . htmlspecialchars($row['no_resi']) . "</td>";
    echo "<td>" . htmlspecialchars($row['nama_pengambil']) . "</td>";
    echo "<td style='mso-number-format:\"\@\"'>" . htmlspecialchars($row['telp_pengambil']) . "</td>";
    echo "<td>" . date('d/m/Y H:i', strtotime($row['tanggal_pengambilan'])) . "</td>";
    echo "<td>" . htmlspecialchars($row['nama_barang']) . "</td>";
    echo "<td align='right'>" . number_format($row['berat'], 2) . "</td>";
    echo "<td align='center'>" . $row['jumlah'] . "</td>";
    echo "<td>" . htmlspecialchars($row['nama_pengirim']) . "</td>";
    echo "<td style='mso-number-format:\"\@\"'>" . htmlspecialchars($row['telp_pengirim']) . "</td>";
    echo "<td>" . htmlspecialchars($row['nama_penerima']) . "</td>";
    echo "<td style='mso-number-format:\"\@\"'>" . htmlspecialchars($row['telp_penerima']) . "</td>";
    echo "<td>" . htmlspecialchars($row['cabang_pengirim']) . "</td>";
    echo "<td>" . htmlspecialchars($row['cabang_penerima']) . "</td>";
    
    // Format pembayaran
    switch ($row['pembayaran']) {
        case 'cash':
            $metode = 'Cash';
            break;
        case 'transfer':
            $metode = 'TF';
            break;
        case 'invoice':
            $metode = 'BT';
            break;
        default:
            $metode = ucfirst($row['pembayaran']);
            break;
    }
    echo "<td align='center'>" . $metode . "</td>";
    
    echo "<td align='right' style='mso-number-format:\"#,##0\"'>" . $row['total_tarif'] . "</td>";
    
    // Format status
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
    echo "<td align='center'>" . $status . "</td>";
    echo "<td>" . htmlspecialchars($row['user_pengambil'] ?? '-') . "</td>";
    echo "</tr>";
    
    $total_berat += $row['berat'];
    $total_tarif += $row['total_tarif'];
    $no++;
}


echo "</table>";