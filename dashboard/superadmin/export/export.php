<?php
// File: dashboard/superadmin/export/export.php
include '../../../config/database.php';

// Ambil parameter username & filter
$username = isset($_GET['username']) ? trim($_GET['username']) : '';
$filter_type = isset($_GET['filter_type']) ? trim($_GET['filter_type']) : 'bulan_ini';
$filter_value = isset($_GET['filter_value']) ? trim($_GET['filter_value']) : '';

if (empty($username)) {
    header("Location: ../?error=no_data");
    exit();
}

// Setup kondisi WHERE berdasarkan filter
$date_condition = '';
$date_condition_p2 = '';
$periode_display = '';

switch ($filter_type) {
    case 'hari_ini':
        $date_condition = "AND DATE(p.tanggal) = CURDATE()";
        $date_condition_p2 = "AND DATE(p2.tanggal) = CURDATE()";
        $periode_display = date('d F Y');
        break;
    
    case 'bulan_ini':
        $date_condition = "AND MONTH(p.tanggal) = MONTH(CURDATE()) AND YEAR(p.tanggal) = YEAR(CURDATE())";
        $date_condition_p2 = "AND MONTH(p2.tanggal) = MONTH(CURDATE()) AND YEAR(p2.tanggal) = YEAR(CURDATE())";
        $periode_display = date('F Y');
        break;
    
    case 'tanggal_spesifik':
        if (!empty($filter_value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filter_value)) {
            $date_condition = "AND DATE(p.tanggal) = '" . $filter_value . "'";
            $date_condition_p2 = "AND DATE(p2.tanggal) = '" . $filter_value . "'";
            $periode_display = date('d F Y', strtotime($filter_value));
        } else {
            $date_condition = "AND MONTH(p.tanggal) = MONTH(CURDATE()) AND YEAR(p.tanggal) = YEAR(CURDATE())";
            $date_condition_p2 = "AND MONTH(p2.tanggal) = MONTH(CURDATE()) AND YEAR(p2.tanggal) = YEAR(CURDATE())";
            $periode_display = date('F Y');
        }
        break;
    
    case 'bulan_spesifik':
        if (!empty($filter_value) && preg_match('/^\d{4}-\d{2}$/', $filter_value)) {
            $date_condition = "AND DATE_FORMAT(p.tanggal, '%Y-%m') = '" . $filter_value . "'";
            $date_condition_p2 = "AND DATE_FORMAT(p2.tanggal, '%Y-%m') = '" . $filter_value . "'";
            $periode_display = date('F Y', strtotime($filter_value . '-01'));
        } else {
            $date_condition = "AND MONTH(p.tanggal) = MONTH(CURDATE()) AND YEAR(p.tanggal) = YEAR(CURDATE())";
            $date_condition_p2 = "AND MONTH(p2.tanggal) = MONTH(CURDATE()) AND YEAR(p2.tanggal) = YEAR(CURDATE())";
            $periode_display = date('F Y');
        }
        break;
    
    default:
        $date_condition = "AND MONTH(p.tanggal) = MONTH(CURDATE()) AND YEAR(p.tanggal) = YEAR(CURDATE())";
        $date_condition_p2 = "AND MONTH(p2.tanggal) = MONTH(CURDATE()) AND YEAR(p2.tanggal) = YEAR(CURDATE())";
        $periode_display = date('F Y');
        break;
}

// Dapatkan cabang admin
$stmt_cabang = $conn->prepare("SELECT kc.nama_cabang FROM user u JOIN kantor_cabang kc ON u.id_cabang = kc.id WHERE u.username = ?");
$stmt_cabang->bind_param('s', $username);
$stmt_cabang->execute();
$result_cabang = $stmt_cabang->get_result();
$cabang_admin = $result_cabang->fetch_assoc()['nama_cabang'] ?? '';
$stmt_cabang->close();

// Dapatkan ID user dari username
$stmt_user_id = $conn->prepare("SELECT id FROM user WHERE username = ?");
$stmt_user_id->bind_param('s', $username);
$stmt_user_id->execute();
$result_user_id = $stmt_user_id->get_result();
$user_id = $result_user_id->fetch_assoc()['id'] ?? 0;
$stmt_user_id->close();

// === Query 1: Pendapatan Cash ===
// Cash dari admin + Invoice POD yang di-ACC oleh admin ini (melalui tabel pengambilan)
// Buat date condition untuk pengambilan
$date_condition_pengambilan = str_replace('p2.tanggal', 'pg.tanggal', $date_condition_p2);

$queryCash = "SELECT 
            p.id,
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
            sj.driver AS nama_driver,
            'cash_langsung' AS tipe_cash
          FROM pengiriman p
          LEFT JOIN user u ON p.id_user = u.id
          LEFT JOIN detail_surat_jalan dsj ON p.id = dsj.id_pengiriman
          LEFT JOIN surat_jalan sj ON dsj.id_surat_jalan = sj.id AND sj.status = 'diberangkatkan'
          WHERE u.username = ?
            AND p.pembayaran = 'cash'
            AND p.status != 'dibatalkan'
            $date_condition
          
          UNION ALL
          
          SELECT 
            p2.id,
            p2.no_resi,
            p2.nama_barang,
            p2.nama_pengirim,
            p2.telp_pengirim,
            p2.nama_penerima,
            p2.telp_penerima,
            p2.cabang_penerima AS tujuan,
            p2.cabang_pengirim AS cabang,
            p2.pembayaran AS metode_pembayaran,
            p2.total_tarif AS total,
            p2.tanggal,
            p2.status,
            u2.username AS dibuat_oleh,
            sj2.driver AS nama_driver,
            'invoice_pod_acc' AS tipe_cash
          FROM pengiriman p2
          JOIN pengambilan pg ON p2.no_resi = pg.no_resi
          LEFT JOIN user u2 ON p2.id_user = u2.id
          LEFT JOIN detail_surat_jalan dsj2 ON p2.id = dsj2.id_pengiriman
          LEFT JOIN surat_jalan sj2 ON dsj2.id_surat_jalan = sj2.id AND sj2.status = 'diberangkatkan'
          WHERE pg.id_user = ?
            AND p2.cabang_penerima = ?
            AND p2.pembayaran = 'invoice'
            AND p2.status = 'pod'
            $date_condition_pengambilan
          
          ORDER BY tanggal ASC";

$stmtCash = $conn->prepare($queryCash);
$stmtCash->bind_param('sis', $username, $user_id, $cabang_admin);
$stmtCash->execute();
$resultCash = $stmtCash->get_result();

// === Query 2: Pendapatan Transfer ===
$queryTransfer = "SELECT 
            p.id,
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
          WHERE u.username = ?
            AND p.pembayaran = 'transfer'
            AND p.status != 'dibatalkan'
            $date_condition
          ORDER BY p.tanggal ASC";

$stmtTransfer = $conn->prepare($queryTransfer);
$stmtTransfer->bind_param('s', $username);
$stmtTransfer->execute();
$resultTransfer = $stmtTransfer->get_result();

// === Query 3: Pendapatan Invoice (belum POD) ===
$queryInvoice = "SELECT 
            p.id,
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
          WHERE u.username = ?
            AND p.pembayaran = 'invoice'
            AND p.status != 'dibatalkan'
            $date_condition
          ORDER BY p.tanggal ASC";

$stmtInvoice = $conn->prepare($queryInvoice);
$stmtInvoice->bind_param('s', $username);
$stmtInvoice->execute();
$resultInvoice = $stmtInvoice->get_result();

// Cek apakah ada data
if ($resultCash->num_rows === 0 && $resultTransfer->num_rows === 0 && $resultInvoice->num_rows === 0) {
    header("Location: ../index.php?error=no_data");
    exit();
}

// === Header untuk file Excel ===
$filename = "Laporan_Pendapatan_{$username}_" . date('Y-m-d_His') . ".xls";
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename={$filename}");
header("Pragma: no-cache");
header("Expires: 0");

// === TABEL 1: PENDAPATAN CASH ===
echo "<table border='1' cellspacing='0' cellpadding='5'>";

// Judul laporan
echo "<tr style='background:#dc3545; color:white; font-weight:bold;'>
        <th colspan='15'>LAPORAN PENDAPATAN CASH - ADMIN " . strtoupper($username) . "</th>
      </tr>";
echo "<tr><td colspan='15' style='background:#f8d7da;'>Periode: " . htmlspecialchars($periode_display) . "</td></tr>";
echo "<tr><td colspan='15' style='background:#fff3cd; font-style:italic;'>Cash dari admin ini + Invoice POD yang dibuat admin ini</td></tr>";

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
$totalCash = 0;

while ($row = $resultCash->fetch_assoc()) {
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
        case 'bkd': $status = 'BKD'; break;
        case 'dalam pengiriman': $status = 'DP'; break;
        case 'sampai tujuan': $status = 'ST'; break;
        case 'pod': $status = 'POD'; break;
        case 'dibatalkan': $status = 'Batal'; break;
        default: $status = ucfirst($row['status']); break;
    }
    echo "<td>" . $status . "</td>";
    
    switch ($row['metode_pembayaran']) {
        case 'cash': $metode = 'Cash'; break;
        case 'transfer': $metode = 'TF'; break;
        case 'invoice': $metode = 'Invoice'; break;
        default: $metode = ucfirst($row['metode_pembayaran']); break;
    }
    echo "<td>" . $metode . "</td>";
    echo "<td>" . htmlspecialchars($row['dibuat_oleh'] ?? '-') . "</td>";
    echo "<td>" . htmlspecialchars($row['nama_driver'] ?? '') . "</td>";
    echo "<td align='right' style='mso-number-format:\"#,##0\"'>" . $row['total'] . "</td>";
    echo "</tr>";

    $totalCash += (float)$row['total'];
    $no++;
}

// Total Cash
echo "<tr style='font-weight:bold; background:#d4edda;'>
        <td colspan='14' align='right'>TOTAL PENDAPATAN CASH</td>
        <td align='right' style='mso-number-format:\"#,##0\"'>" . $totalCash . "</td>
      </tr>";

echo "</table>";

// === TABEL 2: PENDAPATAN TRANSFER ===
echo "<br><br>";
echo "<table border='1' cellspacing='0' cellpadding='5'>";

echo "<tr style='background:#0d6efd; color:white; font-weight:bold;'>
        <th colspan='15'>LAPORAN PENDAPATAN TRANSFER - ADMIN " . strtoupper($username) . "</th>
      </tr>";
echo "<tr><td colspan='15' style='background:#cfe2ff;'>Periode: " . htmlspecialchars($periode_display) . "</td></tr>";
echo "<tr><td colspan='15' style='background:#fff3cd; font-style:italic;'>Transfer dari admin ini</td></tr>";

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
$totalTransfer = 0;

while ($row = $resultTransfer->fetch_assoc()) {
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
        case 'bkd': $status = 'BKD'; break;
        case 'dalam pengiriman': $status = 'DP'; break;
        case 'sampai tujuan': $status = 'ST'; break;
        case 'pod': $status = 'POD'; break;
        case 'dibatalkan': $status = 'Batal'; break;
        default: $status = ucfirst($row['status']); break;
    }
    echo "<td>" . $status . "</td>";
    echo "<td>TF</td>";
    echo "<td>" . htmlspecialchars($row['dibuat_oleh'] ?? '-') . "</td>";
    echo "<td>" . htmlspecialchars($row['nama_driver'] ?? '') . "</td>";
    echo "<td align='right' style='mso-number-format:\"#,##0\"'>" . $row['total'] . "</td>";
    echo "</tr>";

    $totalTransfer += (float)$row['total'];
    $no++;
}

// Total Transfer
echo "<tr style='font-weight:bold; background:#cfe2ff;'>
        <td colspan='14' align='right'>TOTAL PENDAPATAN TRANSFER</td>
        <td align='right' style='mso-number-format:\"#,##0\"'>" . $totalTransfer . "</td>
      </tr>";

echo "</table>";

// === TABEL 3: PENDAPATAN INVOICE ===
echo "<br><br>";
echo "<table border='1' cellspacing='0' cellpadding='5'>";

echo "<tr style='background:#ffc107; color:black; font-weight:bold;'>
        <th colspan='15'>LAPORAN PENDAPATAN INVOICE - ADMIN " . strtoupper($username) . "</th>
      </tr>";
echo "<tr><td colspan='15' style='background:#fff3cd;'>Periode: " . htmlspecialchars($periode_display) . "</td></tr>";
echo "<tr><td colspan='15' style='background:#fff3cd; font-style:italic;'>Invoice dari admin ini (semua status)</td></tr>";

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
$totalInvoice = 0;

while ($row = $resultInvoice->fetch_assoc()) {
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
        case 'bkd': $status = 'BKD'; break;
        case 'dalam pengiriman': $status = 'DP'; break;
        case 'sampai tujuan': $status = 'ST'; break;
        case 'pod': $status = 'POD'; break;
        case 'dibatalkan': $status = 'Batal'; break;
        default: $status = ucfirst($row['status']); break;
    }
    echo "<td>" . $status . "</td>";
    echo "<td>Invoice</td>";
    echo "<td>" . htmlspecialchars($row['dibuat_oleh'] ?? '-') . "</td>";
    echo "<td>" . htmlspecialchars($row['nama_driver'] ?? '') . "</td>";
    echo "<td align='right' style='mso-number-format:\"#,##0\"'>" . $row['total'] . "</td>";
    echo "</tr>";

    $totalInvoice += (float)$row['total'];
    $no++;
}

// Total Invoice
echo "<tr style='font-weight:bold; background:#fff3cd;'>
        <td colspan='14' align='right'>TOTAL PENDAPATAN INVOICE</td>
        <td align='right' style='mso-number-format:\"#,##0\"'>" . $totalInvoice . "</td>
      </tr>";

echo "</table>";

$stmtCash->close();
$stmtTransfer->close();
$stmtInvoice->close();
$conn->close();
?>
