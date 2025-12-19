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
$date_condition_p2 = "AND DATE(p2.tanggal) = CURDATE()";
$periode_display = date('d F Y');

// Dapatkan cabang admin
$stmt_cabang = $conn->prepare("SELECT kc.nama_cabang FROM user u JOIN kantor_cabang kc ON u.id_cabang = kc.id WHERE u.id = ?");
$stmt_cabang->bind_param('i', $user_id);
$stmt_cabang->execute();
$result_cabang = $stmt_cabang->get_result();
$cabang_admin = $result_cabang->fetch_assoc()['nama_cabang'] ?? '';
$stmt_cabang->close();

// === Query 1: Pendapatan Cash ===
// Cash dari admin + bayar_ditempat POD yang di-ACC oleh admin ini (melalui tabel pengambilan)
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
          WHERE p.id_user = ?
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
            'bayar_ditempat_pod_acc' AS tipe_cash
          FROM pengiriman p2
          JOIN pengambilan pg ON p2.no_resi = pg.no_resi
          LEFT JOIN user u2 ON p2.id_user = u2.id
          LEFT JOIN detail_surat_jalan dsj2 ON p2.id = dsj2.id_pengiriman
          LEFT JOIN surat_jalan sj2 ON dsj2.id_surat_jalan = sj2.id AND sj2.status = 'diberangkatkan'
          WHERE pg.id_user = ?
            AND p2.cabang_penerima = ?
            AND p2.pembayaran = 'bayar_ditempat'
            AND p2.status = 'pod'
            $date_condition_pengambilan
          
          ORDER BY tanggal ASC";

$stmtCash = $conn->prepare($queryCash);
$stmtCash->bind_param('iis', $user_id, $user_id, $cabang_admin);
$stmtCash->execute();
$resultCash = $stmtCash->get_result();

// === Query 2: Pendapatan Transfer + Invoice Lunas ===
// Buat date condition untuk invoice paid (berdasarkan tanggal_pembayaran dengan prefix p2)
$date_condition_invoice_paid = str_replace('p.tanggal', 'p2.tanggal_pembayaran', $date_condition);

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
            sj.driver AS nama_driver,
            'transfer' AS tipe_transfer
          FROM pengiriman p
          LEFT JOIN user u ON p.id_user = u.id
          LEFT JOIN detail_surat_jalan dsj ON p.id = dsj.id_pengiriman
          LEFT JOIN surat_jalan sj ON dsj.id_surat_jalan = sj.id AND sj.status = 'diberangkatkan'
          WHERE p.id_user = ?
            AND p.pembayaran = 'transfer'
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
            'invoice_lunas' AS tipe_transfer
          FROM pengiriman p2
          LEFT JOIN user u2 ON p2.id_user = u2.id
          LEFT JOIN detail_surat_jalan dsj2 ON p2.id = dsj2.id_pengiriman
          LEFT JOIN surat_jalan sj2 ON dsj2.id_surat_jalan = sj2.id AND sj2.status = 'diberangkatkan'
          WHERE p2.id_user = ?
            AND p2.pembayaran = 'invoice'
            AND p2.status_pembayaran = 'Sudah Dibayar'
            AND p2.status != 'dibatalkan'
            $date_condition_invoice_paid
          
          ORDER BY tanggal ASC";

$stmtTransfer = $conn->prepare($queryTransfer);
$stmtTransfer->bind_param('ii', $user_id, $user_id);
$stmtTransfer->execute();
$resultTransfer = $stmtTransfer->get_result();

// === Query 3: Pendapatan bayar_ditempat (belum POD) ===
$querybayar_ditempat = "SELECT 
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
          WHERE p.id_user = ?
            AND p.pembayaran = 'bayar_ditempat'
            AND p.status != 'dibatalkan'
            $date_condition
          ORDER BY p.tanggal ASC";

$stmtbayar_ditempat = $conn->prepare($querybayar_ditempat);
$stmtbayar_ditempat->bind_param('i', $user_id);
$stmtbayar_ditempat->execute();
$resultbayar_ditempat = $stmtbayar_ditempat->get_result();

// === Query 4: Invoice Belum Dibayar ===
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
          WHERE p.id_user = ?
            AND p.pembayaran = 'invoice'
            AND p.status_pembayaran = 'Belum Dibayar'
            AND p.status != 'dibatalkan'
            $date_condition
          ORDER BY p.tanggal ASC";

$stmtInvoice = $conn->prepare($queryInvoice);
$stmtInvoice->bind_param('i', $user_id);
$stmtInvoice->execute();
$resultInvoice = $stmtInvoice->get_result();

// Cek apakah ada data
if ($resultCash->num_rows === 0 && $resultTransfer->num_rows === 0 && $resultbayar_ditempat->num_rows === 0 && $resultInvoice->num_rows === 0) {
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
echo "<tr><td colspan='15' style='background:#f8d7da;'>Periode: " . htmlspecialchars($periode_display) . " (Hari Ini)</td></tr>";
echo "<tr><td colspan='15' style='background:#fff3cd; font-style:italic;'>Cash langsung + BT POD yang di-ACC</td></tr>";

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
        case 'bayar_ditempat': $metode = 'bayar_ditempat'; break;
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
        <th colspan='15'>LAPORAN PENDAPATAN TRANSFER + INVOICE LUNAS - ADMIN " . strtoupper($username) . "</th>
      </tr>";
echo "<tr><td colspan='15' style='background:#cfe2ff;'>Periode: " . htmlspecialchars($periode_display) . " (Hari Ini)</td></tr>";
echo "<tr><td colspan='15' style='background:#fff3cd; font-style:italic;'>Transfer dari admin ini + Invoice yang dilunaskan (berdasarkan tanggal pelunasan)</td></tr>";

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
    
    // Tampilkan metode pembayaran dengan keterangan tipe
    $metode_display = ($row['metode_pembayaran'] === 'invoice') ? 'Invoice Lunas' : 'TF';
    echo "<td>" . $metode_display . "</td>";
    echo "<td>" . htmlspecialchars($row['dibuat_oleh'] ?? '-') . "</td>";
    echo "<td>" . htmlspecialchars($row['nama_driver'] ?? '') . "</td>";
    echo "<td align='right' style='mso-number-format:\"#,##0\"'>" . $row['total'] . "</td>";
    echo "</tr>";

    $totalTransfer += (float)$row['total'];
    $no++;
}

// Total Transfer + Invoice Lunas
echo "<tr style='font-weight:bold; background:#cfe2ff;'>
        <td colspan='14' align='right'>TOTAL PENDAPATAN TRANSFER + INVOICE LUNAS</td>
        <td align='right' style='mso-number-format:\"#,##0\"'>" . $totalTransfer . "</td>
      </tr>";

echo "</table>";

// === TABEL 3: PENDAPATAN bayar_ditempat ===
echo "<br><br>";
echo "<table border='1' cellspacing='0' cellpadding='5'>";

echo "<tr style='background:#ffc107; color:black; font-weight:bold;'>
        <th colspan='15'>LAPORAN PENDAPATAN BAYAR DITEMPAT - ADMIN " . strtoupper($username) . "</th>
      </tr>";
echo "<tr><td colspan='15' style='background:#fff3cd;'>Periode: " . htmlspecialchars($periode_display) . " (Hari Ini)</td></tr>";
echo "<tr><td colspan='15' style='background:#fff3cd; font-style:italic;'>Bayar Ditempat dari admin ini (semua status)</td></tr>";

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
$totalbayar_ditempat = 0;

while ($row = $resultbayar_ditempat->fetch_assoc()) {
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
    echo "<td>Bayar Ditempat</td>";
    echo "<td>" . htmlspecialchars($row['dibuat_oleh'] ?? '-') . "</td>";
    echo "<td>" . htmlspecialchars($row['nama_driver'] ?? '') . "</td>";
    echo "<td align='right' style='mso-number-format:\"#,##0\"'>" . $row['total'] . "</td>";
    echo "</tr>";

    $totalbayar_ditempat += (float)$row['total'];
    $no++;
}

// Total bayar_ditempat
echo "<tr style='font-weight:bold; background:#fff3cd;'>
        <td colspan='14' align='right'>TOTAL PENDAPATAN BAYAR DITEMPAT</td>
        <td align='right' style='mso-number-format:\"#,##0\"'>" . $totalbayar_ditempat . "</td>
      </tr>";

echo "</table>";

// === TABEL 4: INVOICE BELUM DIBAYAR ===
echo "<br><br>";
echo "<table border='1' cellspacing='0' cellpadding='5'>";

echo "<tr style='background:#dc3545; color:white; font-weight:bold;'>
        <th colspan='15'>LAPORAN INVOICE BELUM DIBAYAR - ADMIN " . strtoupper($username) . "</th>
      </tr>";
echo "<tr><td colspan='15' style='background:#f8d7da;'>Periode: " . htmlspecialchars($periode_display) . " (Hari Ini)</td></tr>";
echo "<tr><td colspan='15' style='background:#fff3cd; font-style:italic;'>Invoice dari admin ini yang belum dilunaskan (hutang)</td></tr>";

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
    echo "<td align='right' style='mso-number-format:\"#,##0\"; font-weight:bold;'>" . $row['total'] . "</td>";
    echo "</tr>";

    $totalInvoice += (float)$row['total'];
    $no++;
}

// Total Invoice Belum Dibayar
echo "<tr style='font-weight:bold; background:#f8d7da;'>
        <td colspan='14' align='right'>TOTAL HUTANG INVOICE</td>
        <td align='right' style='mso-number-format:\"#,##0\"'>" . $totalInvoice . "</td>
      </tr>";

echo "</table>";

$stmtCash->close();
$stmtTransfer->close();
$stmtbayar_ditempat->close();
$stmtInvoice->close();
$conn->close();
?>
