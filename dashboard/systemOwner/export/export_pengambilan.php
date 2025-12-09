<?php
session_start();

if (!isset($_SESSION['username']) || !isset($_SESSION['user_id'])) {
    header("Location: ../../../auth/login");
    exit;
}

if (isset($_SESSION['role']) && $_SESSION['role'] !== 'systemOwner') {
    header("Location: ../../../?error=unauthorized");
    exit;
}

include '../../../config/database.php';

// Ambil parameter filter dari dashboard
$cabang = isset($_GET['cabang']) ? $_GET['cabang'] : '';
$filter_type = isset($_GET['filter_type']) ? $_GET['filter_type'] : 'bulan_ini';
$filter_value = isset($_GET['filter_value']) ? $_GET['filter_value'] : '';

// Tentukan kondisi tanggal berdasarkan filter_type
$date_condition = '';
switch ($filter_type) {
    case 'hari_ini':
        $date_condition = 'DATE(pg.tanggal) = CURDATE()';
        break;
    
    case 'bulan_ini':
        $date_condition = "DATE_FORMAT(pg.tanggal, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')";
        break;
    
    case 'tanggal_spesifik':
        if (!empty($filter_value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filter_value)) {
            $date_condition = "DATE(pg.tanggal) = '" . $filter_value . "'";
        } else {
            $date_condition = "DATE_FORMAT(pg.tanggal, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')";
        }
        break;
    
    case 'bulan_spesifik':
        if (!empty($filter_value) && preg_match('/^\d{4}-\d{2}$/', $filter_value)) {
            $date_condition = "DATE_FORMAT(pg.tanggal, '%Y-%m') = '" . $filter_value . "'";
        } else {
            $date_condition = "DATE_FORMAT(pg.tanggal, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')";
        }
        break;
    
    default:
        $date_condition = "DATE_FORMAT(pg.tanggal, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')";
        break;
}

// Validasi cabang
if (empty($cabang)) {
    die("Cabang tidak boleh kosong.");
}

// Ambil id_cabang dari nama cabang
$stmt_cabang = $conn->prepare("SELECT id FROM kantor_cabang WHERE nama_cabang = ? LIMIT 1");
$stmt_cabang->bind_param('s', $cabang);
$stmt_cabang->execute();
$result_cabang = $stmt_cabang->get_result();

if ($result_cabang->num_rows === 0) {
    die("Cabang tidak ditemukan.");
}

$id_cabang = $result_cabang->fetch_assoc()['id'];
$stmt_cabang->close();

// Query data pengambilan barang berdasarkan filter dan cabang
$sql = "
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
    WHERE $date_condition
    AND p.id_cabang_penerima = ?
    ORDER BY pg.tanggal DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $id_cabang);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Cek jika tidak ada data
if (count($data) == 0) {
    // Build redirect URL dengan filter parameters
    $redirect_url = '../?error=no_data';
    $redirect_url .= '&filter_type=' . urlencode($filter_type);
    if (!empty($filter_value)) {
        $redirect_url .= '&filter_value=' . urlencode($filter_value);
    }
    header("Location: " . $redirect_url);
    exit;
}

// Set header untuk download Excel
$filename = "Pengambilan_Barang_" . $cabang . "_" . date('d-m-Y') . ".xls";
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");

// Format periode untuk tampilan
$periode_display = '';
switch ($filter_type) {
    case 'hari_ini':
        $periode_display = 'Hari Ini - ' . date('d F Y');
        break;
    case 'bulan_ini':
        $periode_display = 'Bulan ' . date('F Y');
        break;
    case 'tanggal_spesifik':
        if (!empty($filter_value)) {
            $periode_display = date('d F Y', strtotime($filter_value));
        } else {
            $periode_display = 'Bulan ' . date('F Y');
        }
        break;
    case 'bulan_spesifik':
        if (!empty($filter_value)) {
            $periode_display = date('F Y', strtotime($filter_value . '-01'));
        } else {
            $periode_display = 'Bulan ' . date('F Y');
        }
        break;
    default:
        $periode_display = 'Bulan ' . date('F Y');
        break;
}

// Buat tabel Excel
echo "<table border='1' cellspacing='0' cellpadding='5'>";

// Judul laporan
echo "<tr style='background:#dc3545; color:white; font-weight:bold;'>
        <th colspan='18'>LAPORAN PENGAMBILAN BARANG - " . strtoupper(htmlspecialchars($cabang)) . "</th>
      </tr>";
echo "<tr><td colspan='18' style='background:#f8d7da;'>Periode: " . htmlspecialchars($periode_display) . "</td></tr>";

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

$conn->close();