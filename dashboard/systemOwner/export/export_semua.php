<?php
session_start();

if (!isset($_SESSION['username'])) {
    header('Location: ../../../auth/login');
    exit;
}

if (isset($_SESSION['role']) && $_SESSION['role'] !== 'haykalSysAdmin') {
    header('Location: ../../../?error=unauthorized');
    exit;
}

/** @var mysqli $conn */
include '../../../config/database.php';
session_write_close();

$nama_bulan_list = [
    '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
    '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
    '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember',
];

$bulan = isset($_GET['bulan']) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $_GET['bulan']) ? $_GET['bulan'] : date('Y-m');
$nama_bulan = $nama_bulan_list[substr($bulan, 5, 2)];
$periode_display = $nama_bulan . ' ' . substr($bulan, 0, 4);

// Range tanggal (lebih ramah index daripada DATE_FORMAT)
$start = $bulan . '-01';
$end = date('Y-m-d', strtotime($start . ' +1 month'));

function fetch_rows($conn, $sql, $params)
{
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        return [];
    }
    if ($params) {
        $stmt->bind_param(str_repeat('s', count($params)), ...$params);
    }
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

$cols = "p.no_resi, p.nama_barang, p.nama_pengirim, p.telp_pengirim, p.nama_penerima, p.telp_penerima,
         p.cabang_penerima AS tujuan, p.cabang_pengirim AS cabang, p.pembayaran AS metode_pembayaran,
         p.status_pembayaran, p.total_tarif AS total, p.tanggal, p.status,
         u.username AS dibuat_oleh, sj.driver AS nama_driver";
$joins = "LEFT JOIN user u ON p.id_user = u.id
          LEFT JOIN detail_surat_jalan dsj ON p.id = dsj.id_pengiriman
          LEFT JOIN surat_jalan sj ON dsj.id_surat_jalan = sj.id AND sj.status = 'diberangkatkan'";

// === Query 1: Cash semua cabang + BT POD (dikreditkan ke cabang penerima, berdasarkan tanggal pengambilan) ===
$rowsCash = fetch_rows($conn, "
    SELECT $cols, p.cabang_pengirim AS cabang_rekap
    FROM pengiriman p $joins
    WHERE p.pembayaran = 'cash' AND p.status != 'dibatalkan'
      AND p.tanggal >= ? AND p.tanggal < ?
    UNION ALL
    SELECT $cols, p.cabang_penerima AS cabang_rekap
    FROM pengiriman p
    JOIN pengambilan pg ON p.no_resi = pg.no_resi
    $joins
    WHERE p.pembayaran = 'bayar_ditempat' AND p.status = 'pod'
      AND pg.tanggal >= ? AND pg.tanggal < ?
    ORDER BY cabang_rekap ASC, tanggal ASC", [$start, $end, $start, $end]);

// === Query 2: Transfer + Invoice Lunas (berdasarkan tanggal pelunasan) ===
$rowsTransfer = fetch_rows($conn, "
    SELECT $cols, p.cabang_pengirim AS cabang_rekap
    FROM pengiriman p $joins
    WHERE p.pembayaran = 'transfer' AND p.status != 'dibatalkan'
      AND p.tanggal >= ? AND p.tanggal < ?
    UNION ALL
    SELECT $cols, p.cabang_pengirim AS cabang_rekap
    FROM pengiriman p $joins
    WHERE p.pembayaran = 'invoice' AND p.status_pembayaran = 'Sudah Dibayar' AND p.status != 'dibatalkan'
      AND p.tanggal_pembayaran >= ? AND p.tanggal_pembayaran < ?
    ORDER BY cabang_rekap ASC, tanggal ASC", [$start, $end, $start, $end]);

// === Query 3: Bayar Ditempat (semua status) ===
$rowsBT = fetch_rows($conn, "
    SELECT $cols, p.cabang_pengirim AS cabang_rekap
    FROM pengiriman p $joins
    WHERE p.pembayaran = 'bayar_ditempat' AND p.status != 'dibatalkan'
      AND p.tanggal >= ? AND p.tanggal < ?
    ORDER BY cabang_rekap ASC, p.tanggal ASC", [$start, $end]);

// === Query 4: Invoice Belum Dibayar ===
$rowsInvoice = fetch_rows($conn, "
    SELECT $cols, p.cabang_pengirim AS cabang_rekap
    FROM pengiriman p $joins
    WHERE p.pembayaran = 'invoice' AND p.status_pembayaran = 'Belum Dibayar' AND p.status != 'dibatalkan'
      AND p.tanggal >= ? AND p.tanggal < ?
    ORDER BY cabang_rekap ASC, p.tanggal ASC", [$start, $end]);

if (!$rowsCash && !$rowsTransfer && !$rowsBT && !$rowsInvoice) {
    $conn->close();
    header('Location: index.php?error=no_data&bulan=' . urlencode($bulan));
    exit();
}

// === Rekap per cabang ===
$rekap = [];
foreach (fetch_rows($conn, "SELECT nama_cabang FROM kantor_cabang ORDER BY nama_cabang ASC", []) as $c) {
    $rekap[$c['nama_cabang']] = ['jumlah' => 0, 'cash' => 0, 'transfer' => 0, 'bt' => 0, 'invoice' => 0];
}

// Jumlah pengiriman: semua status (termasuk dibatalkan), sama dengan dashboard
$jumlahRows = fetch_rows($conn, "
    SELECT cabang_pengirim, COUNT(*) AS jumlah
    FROM pengiriman
    WHERE tanggal >= ? AND tanggal < ?
    GROUP BY cabang_pengirim", [$start, $end]);

function tambah_rekap(&$rekap, $cabang, $key, $nilai)
{
    // Hanya cabang yang terdaftar di kantor_cabang, sama dengan dashboard
    if (isset($rekap[$cabang])) {
        $rekap[$cabang][$key] += $nilai;
    }
}

foreach ($jumlahRows as $r) tambah_rekap($rekap, $r['cabang_pengirim'], 'jumlah', (int) $r['jumlah']);
foreach ($rowsCash as $r) tambah_rekap($rekap, $r['cabang_rekap'], 'cash', (float) $r['total']);
foreach ($rowsTransfer as $r) tambah_rekap($rekap, $r['cabang_rekap'], 'transfer', (float) $r['total']);
foreach ($rowsBT as $r) tambah_rekap($rekap, $r['cabang_rekap'], 'bt', (float) $r['total']);
foreach ($rowsInvoice as $r) tambah_rekap($rekap, $r['cabang_rekap'], 'invoice', (float) $r['total']);

function format_status($status)
{
    $map = ['bkd' => 'BKD', 'dalam pengiriman' => 'DP', 'sampai tujuan' => 'ST', 'pod' => 'POD', 'dibatalkan' => 'Batal'];
    return $map[$status] ?? ucfirst((string) $status);
}

function format_metode($row)
{
    switch ($row['metode_pembayaran']) {
        case 'cash': return 'Cash';
        case 'transfer': return 'TF';
        case 'bayar_ditempat': return 'Bayar Ditempat';
        case 'invoice': return $row['status_pembayaran'] === 'Sudah Dibayar' ? 'Invoice Lunas' : 'Invoice';
        default: return ucfirst((string) $row['metode_pembayaran']);
    }
}

function render_detail($judul, $headStyle, $periodeBg, $keterangan, $rows, $labelTotal, $totalBg, $periode)
{
    echo "<br><br><table border='1' cellspacing='0' cellpadding='5'>";
    echo "<tr style='{$headStyle} font-weight:bold;'><th colspan='15'>{$judul} - SEMUA CABANG</th></tr>";
    echo "<tr><td colspan='15' style='background:{$periodeBg};'>Periode: " . htmlspecialchars($periode) . "</td></tr>";
    echo "<tr><td colspan='15' style='background:#fff3cd; font-style:italic;'>{$keterangan}</td></tr>";
    echo "<tr style='background:#f2f2f2; font-weight:bold;'>
            <th>No</th><th>Resi</th><th>Tanggal</th><th>Nama Barang</th><th>Pengirim</th><th>Telp Pengirim</th>
            <th>Penerima</th><th>Telp Penerima</th><th>Asal</th><th>Tujuan</th><th>Status</th><th>Pembayaran</th>
            <th>Dibuat Oleh</th><th>Driver</th><th>Total (Rp)</th>
          </tr>";

    $total = 0;
    foreach ($rows as $i => $row) {
        echo "<tr>";
        echo "<td>" . ($i + 1) . "</td>";
        echo "<td>" . htmlspecialchars($row['no_resi']) . "</td>";
        echo "<td>" . date('d/m/Y H:i', strtotime($row['tanggal'])) . "</td>";
        echo "<td>" . htmlspecialchars($row['nama_barang']) . "</td>";
        echo "<td>" . htmlspecialchars($row['nama_pengirim']) . "</td>";
        echo "<td style='mso-number-format:\"\@\"'>" . htmlspecialchars($row['telp_pengirim']) . "</td>";
        echo "<td>" . htmlspecialchars($row['nama_penerima']) . "</td>";
        echo "<td style='mso-number-format:\"\@\"'>" . htmlspecialchars($row['telp_penerima']) . "</td>";
        echo "<td>" . htmlspecialchars($row['cabang']) . "</td>";
        echo "<td>" . htmlspecialchars($row['tujuan']) . "</td>";
        echo "<td>" . format_status($row['status']) . "</td>";
        echo "<td>" . format_metode($row) . "</td>";
        echo "<td>" . htmlspecialchars($row['dibuat_oleh'] ?? '-') . "</td>";
        echo "<td>" . htmlspecialchars($row['nama_driver'] ?? '') . "</td>";
        echo "<td align='right' style='mso-number-format:\"#,##0\"'>" . (float) $row['total'] . "</td>";
        echo "</tr>";
        $total += (float) $row['total'];
    }

    echo "<tr style='font-weight:bold; background:{$totalBg};'>
            <td colspan='14' align='right'>{$labelTotal}</td>
            <td align='right' style='mso-number-format:\"#,##0\"'>{$total}</td>
          </tr>";
    echo "</table>";
}

// === Header untuk file Excel ===
$filename = 'Laporan_Pendapatan_Semua_Cabang_' . $bulan . '_' . date('Y-m-d_His') . '.xls';
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename=' . $filename);
header('Pragma: no-cache');
header('Expires: 0');

// === TABEL REKAP ===
$num = "mso-number-format:\"#,##0\"";
echo "<table border='1' cellspacing='0' cellpadding='5'>";
echo "<tr style='font-weight:bold;'>
        <th style='background:#f2f2f2; color:#44546a;'>" . htmlspecialchars($nama_bulan) . "</th>
        <th style='background:#4472c4; color:white;'>Cabang</th>
        <th style='background:#4472c4; color:white;'>Jumlah</th>
        <th style='background:#4472c4; color:white;'>Total</th>
        <th style='background:#4472c4; color:white;'>Cash + BT</th>
        <th style='background:#4472c4; color:white;'>Transfer + Invoice Lunas</th>
        <th style='background:#4472c4; color:white;'>Bayar Ditempat</th>
        <th style='background:#4472c4; color:white;'>Invoice (blm bayar)</th>
      </tr>";

$kolom_uang = ['total', 'cash', 'transfer', 'bt', 'invoice'];
$grand = ['jumlah' => 0, 'total' => 0, 'cash' => 0, 'transfer' => 0, 'bt' => 0, 'invoice' => 0];
$i = 0;
foreach ($rekap as $cabang => $r) {
    // Total = (Cash + BT POD) + (Transfer + Invoice Lunas), sama dengan total pendapatan dashboard
    $r['total'] = $r['cash'] + $r['transfer'];
    $bg = $i++ % 2 === 0 ? '#d9e1f2' : '#ffffff';
    echo "<tr><td></td>";
    echo "<td style='background:{$bg};'>" . htmlspecialchars($cabang) . "</td>";
    echo "<td style='background:{$bg};' align='right'>{$r['jumlah']}</td>";
    foreach ($kolom_uang as $k) {
        echo "<td style='background:{$bg}; {$num}' align='right'>{$r[$k]}</td>";
    }
    echo "</tr>";
    foreach ($grand as $k => $v) {
        $grand[$k] += $r[$k];
    }
}

echo "<tr style='font-weight:bold; background:#b4c6e7;'><td></td><td>TOTAL</td><td align='right'>{$grand['jumlah']}</td>";
foreach ($kolom_uang as $k) {
    echo "<td style='{$num}' align='right'>{$grand[$k]}</td>";
}
echo "</tr>";
echo "</table>";

// === TABEL DETAIL (format sama dengan export per cabang) ===
render_detail('LAPORAN PENDAPATAN CASH', 'background:#dc3545; color:white;', '#f8d7da',
    'Cash dari semua cabang + BT POD (dikreditkan ke cabang penerima)', $rowsCash,
    'TOTAL PENDAPATAN CASH', '#d4edda', $periode_display);

render_detail('LAPORAN PENDAPATAN TRANSFER + INVOICE LUNAS', 'background:#0d6efd; color:white;', '#cfe2ff',
    'Transfer dari semua cabang + Invoice yang dilunaskan (berdasarkan tanggal pelunasan)', $rowsTransfer,
    'TOTAL PENDAPATAN TRANSFER + INVOICE LUNAS', '#cfe2ff', $periode_display);

render_detail('LAPORAN PENDAPATAN BAYAR DITEMPAT', 'background:#ffc107; color:black;', '#fff3cd',
    'Bayar Ditempat dari semua cabang (semua status)', $rowsBT,
    'TOTAL PENDAPATAN BAYAR DITEMPAT', '#fff3cd', $periode_display);

render_detail('LAPORAN INVOICE BELUM DIBAYAR', 'background:#dc3545; color:white;', '#f8d7da',
    'Invoice dari semua cabang yang belum dilunaskan (hutang)', $rowsInvoice,
    'TOTAL HUTANG INVOICE', '#f8d7da', $periode_display);

$conn->close();
