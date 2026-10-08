<?php
session_start();
include 'koneksi.php';
require_once 'notificationsHelper.php';
// Check user login and role
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['role'] !== 'penjual') {
    header("Location: login.php");
    exit;
}
$user_id = $_SESSION['id_user'];
$user_name = $_SESSION['nama'];
// Get user data untuk avatar
$user_data = [];
$stmt_user = mysqli_prepare($conn, "SELECT * FROM users WHERE id_user = ?");
mysqli_stmt_bind_param($stmt_user, "i", $user_id);
mysqli_stmt_execute($stmt_user);
$user_query = mysqli_stmt_get_result($stmt_user);
$user_data = mysqli_fetch_assoc($user_query);
mysqli_stmt_close($stmt_user);
// ===== NOTIFIKASI - GUNAKAN HELPER YANG STANDARDIZED =====
$notifications = getNotifications($conn, $user_id, 'penjual');
// Get filter parameters
$tahun = isset($_GET['tahun']) ? intval($_GET['tahun']) : date('Y');
$bulan = isset($_GET['bulan']) ? intval($_GET['bulan']) : date('n');
$download = isset($_GET['download']) ? true : false;
// Get sales report
$query_report = "SELECT
pes.id_pesanan AS id_transaksi,
pes.created_at AS tanggal,
pes.total_harga,
pes.status,
pes.nomor_resi AS resi,
pr.nama_buku,
pr.modal,
pr.margin,
pr.keuntungan,
pr.harga as harga_jual,
pes.quantity AS jumlah,
u.nama as pembeli_nama
FROM pesanan pes
JOIN produk pr ON pes.id_produk = pr.id_produk
JOIN users u ON pes.id_pembeli = u.id_user
WHERE pr.id_penjual = ?
AND pes.status NOT IN ('dibatalkan', 'ditolak', 'refund')
AND YEAR(pes.created_at) = ?
";

if ($bulan > 0) {
    $query_report .= "AND MONTH(pes.created_at) = ? ";
}
$query_report .= "ORDER BY pes.created_at DESC";

$stmt_report = mysqli_prepare($conn, $query_report);
if ($bulan > 0) {
    mysqli_stmt_bind_param($stmt_report, "iii", $user_id, $tahun, $bulan);
} else {
    mysqli_stmt_bind_param($stmt_report, "ii", $user_id, $tahun);
}
mysqli_stmt_execute($stmt_report);
$report = mysqli_stmt_get_result($stmt_report);
// Calculate totals
$total_modal = 0;
$total_penjualan = 0;
$total_keuntungan = 0;
while ($row = mysqli_fetch_assoc($report)) {
    $row_modal_total = $row['modal'] * $row['jumlah'];
    $row_penjualan_total = $row['harga_jual'] * $row['jumlah'];
    $row_keuntungan_total = ($row['harga_jual'] - $row['modal']) * $row['jumlah'];

    $total_modal += $row_modal_total;
    $total_penjualan += $row_penjualan_total;
    $total_keuntungan += $row_keuntungan_total;
}
// Reset pointer
mysqli_data_seek($report, 0);
// Get monthly data for chart
$monthly_data = [];
for ($i = 1; $i <= 12; $i++) {
    $stmt_monthly = mysqli_prepare($conn, "SELECT
    SUM((pr.harga - pr.modal) * pes.quantity) as total_keuntungan,
    SUM(pr.margin * pes.quantity) as total_margin,
    SUM(pr.harga * pes.quantity) as total_penjualan
    FROM pesanan pes
    JOIN produk pr ON pes.id_produk = pr.id_produk
    WHERE pr.id_penjual = ?
    AND pes.status NOT IN ('dibatalkan', 'ditolak', 'refund')
    AND YEAR(pes.created_at) = ?
    AND MONTH(pes.created_at) = ?");
    mysqli_stmt_bind_param($stmt_monthly, "iii", $user_id, $tahun, $i);
    mysqli_stmt_execute($stmt_monthly);
    $result_monthly = mysqli_stmt_get_result($stmt_monthly);
    $data = mysqli_fetch_assoc($result_monthly);
    mysqli_stmt_close($stmt_monthly);
    $monthly_data[] = [
        'keuntungan' => $data['total_keuntungan'] ?? 0,
        'margin' => $data['total_margin'] ?? 0,
        'penjualan' => $data['total_penjualan'] ?? 0
    ];
}
// Hitung jumlah status berdasarkan data `pesanan` (untuk stats cards)
$stmt_count = mysqli_prepare($conn, "SELECT COUNT(*) as count FROM pesanan pes JOIN produk pr ON pes.id_produk = pr.id_produk WHERE pr.id_penjual = ? AND pes.status = ?");
$status_values = ['dibayar', 'dikirim', 'selesai'];
$count_dibayar = ['count' => 0];
$count_dikirim = ['count' => 0];
$count_selesai = ['count' => 0];
foreach ($status_values as $status) {
    mysqli_stmt_bind_param($stmt_count, "is", $user_id, $status);
    mysqli_stmt_execute($stmt_count);
    $res = mysqli_stmt_get_result($stmt_count);
    $row = mysqli_fetch_assoc($res);
    if ($status === 'dibayar') $count_dibayar = $row ?: ['count' => 0];
    if ($status === 'dikirim') $count_dikirim = $row ?: ['count' => 0];
    if ($status === 'selesai') $count_selesai = $row ?: ['count' => 0];
}
mysqli_stmt_close($stmt_count);
// Handle download
if ($download) {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="laporan_penjualan_' . $tahun . '_' . $bulan . '.xls"');
    echo "<html>";
    echo "<meta charset=\"UTF-8\">";
    echo "<table border=\"1\">";
    echo "<tr><th colspan=\"8\">Laporan Penjualan - Pustaka Aruna</th></tr>";
    echo "<tr><th colspan=\"8\">Periode: " . date('F', mktime(0, 0, 0, $bulan, 1)) . " $tahun</th></tr>";
    echo "<tr><th colspan=\"8\"></th></tr>";
    echo "<tr>
    <th>Kode Pesanan</th>
    <th>Nama Pembeli</th>
    <th>Judul Buku</th>
    <th>QTY</th>
    <th>Total Modal</th>
    <th>Total Penjualan</th>
    <th>Total Keuntungan</th>
    <th>Tanggal</th>
    </tr>";
    mysqli_data_seek($report, 0);
    while ($row = mysqli_fetch_assoc($report)) {
        $modal_total = $row['modal'] * $row['jumlah'];
        echo "<tr>
        <td>#" . str_pad($row['id_transaksi'], 3, '0', STR_PAD_LEFT) . "</td>
        <td>" . htmlspecialchars($row['pembeli_nama']) . "</td>
        <td>" . htmlspecialchars($row['nama_buku']) . "</td>
        <td>" . $row['jumlah'] . "</td>
        <td>Rp " . number_format($modal_total, 0, ',', '.') . "</td>
        <td>Rp " . number_format(($row['harga_jual'] * $row['jumlah']), 0, ',', '.') . "</td>
        <td>Rp " . number_format((($row['harga_jual'] - $row['modal']) * $row['jumlah']), 0, ',', '.') . "</td>
        <td>" . date('d/m/Y', strtotime($row['tanggal'])) . "</td>
        </tr>";
    }
    echo "<tr><td colspan=\"8\"></td></tr>";
    echo "<tr>
    <td colspan=\"4\"><strong>TOTAL</strong></td>
    <td><strong>Rp " . number_format($total_modal, 0, ',', '.') . "</strong></td>
    <td><strong>Rp " . number_format($total_penjualan, 0, ',', '.') . "</strong></td>
    <td><strong>Rp " . number_format($total_keuntungan, 0, ',', '.') . "</strong></td>
    <td></td>
    </tr>";
    echo "</table>";
    echo "</html>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📊 Laporan Penjualan - Pustaka Aruna</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --primary-light: #3b82f6;
            --primary-lighter: #60a5fa;
            --primary-bg: #eff6ff;
            --secondary: #64748b;
            --secondary-light: #94a3b8;
            --success: #10b981;
            --success-bg: #d1fae5;
            --success-dark: #059669;
            --error: #ef4444;
            --error-bg: #fee2e2;
            --warning: #f59e0b;
            --warning-bg: #fef3c7;
            --info: #3b82f6;
            --info-bg: #dbeafe;
            --background: #f8fafc;
            --surface: #ffffff;
            --border: #e2e8f0;
            --border-light: #f1f5f9;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --text-muted: #94a3b8;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1);
            --shadow-xl: 0 20px 25px -5px rgb(0 0 0 / 0.1);
            --radius: 12px;
            --radius-sm: 8px;
            --radius-lg: 16px;
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background-color: var(--background);
            color: var(--text-primary);
            min-height: 100vh;
            line-height: 1.6;
        }
        /* Navbar */
        .navbar {
            background: var(--surface);
            padding: 1rem 2rem;
            border-radius: var(--radius-lg);
            margin: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--border);
            position: relative;
            overflow: hidden;
        }
        .navbar::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--primary-light), var(--primary));
        }
        .logo {
            font-size: 1.5rem;
            font-weight: 700;
            text-decoration: none;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 12px;
            font-family: 'Inter', sans-serif;
        }
        .logo i {
            color: var(--primary);
            font-size: 1.6rem;
        }
        .nav-menu {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }
        .nav-link {
            color: var(--text-secondary);
            text-decoration: none;
            padding: 10px 18px;
            border-radius: 25px;
            transition: all 0.3s;
            font-size: 0.9rem;
            font-weight: 500;
        }
        .nav-link:hover {
            background: var(--primary-bg);
            color: var(--primary);
        }
        .nav-link.active {
            background: var(--primary);
            color: white;
        }
        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
            background: var(--primary-bg);
            padding: 8px 18px;
            border-radius: 30px;
            cursor: pointer;
            transition: all 0.3s;
            border: 1px solid var(--border);
        }
        .user-info:hover {
            background: var(--info-bg);
            border-color: var(--primary-light);
        }
        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            overflow: hidden;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .user-avatar i {
            color: white;
            font-size: 1.1rem;
        }
        .user-info span {
            font-weight: 500;
            color: var(--text-primary);
        }
        /* Main Container */
        .main-container {
            display: grid;
            grid-template-columns: 260px 1fr;
            gap: 0;
            min-height: calc(100vh - 120px);
        }
        /* Sidebar */
        .sidebar {
            background: var(--surface);
            color: var(--text-primary);
            padding: 2rem 0;
            height: calc(100vh - 120px);
            overflow-y: auto;
            box-shadow: var(--shadow-sm);
            border-right: 1px solid var(--border);
        }
        .sidebar-header {
            padding: 0 1.5rem 1.5rem;
            border-bottom: 1px solid var(--border);
            margin-bottom: 1rem;
        }
        .sidebar-title {
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 1rem;
            padding: 0 1.5rem;
        }
        .sidebar-menu {
            list-style: none;
        }
        .sidebar-item {
            margin: 0;
        }
        .sidebar-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 1.5rem;
            color: var(--text-primary);
            text-decoration: none;
            transition: all 0.3s;
            border-left: 4px solid transparent;
            font-size: 0.9rem;
            font-weight: 500;
        }
        .sidebar-link:hover {
            background: var(--primary-bg);
            color: var(--primary);
            padding-left: 1.8rem;
        }
        .sidebar-link.active {
            background: var(--primary-bg);
            color: var(--primary);
            border-left-color: var(--primary);
        }
        .sidebar-link i {
            width: 20px;
            font-size: 1rem;
            color: var(--text-secondary);
        }
        .sidebar-badge {
            background: var(--error);
            color: white;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.7rem;
            margin-left: auto;
            font-weight: 600;
        }
        /* Main Content */
        .content {
            padding: 2rem;
            overflow-y: auto;
            background: var(--background);
        }
        /* Header */
        .header {
            background: var(--surface);
            padding: 2.5rem;
            border-radius: var(--radius-lg);
            margin-bottom: 2rem;
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--border);
            position: relative;
            overflow: hidden;
        }
        .header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--primary-light), var(--primary));
        }
        .header h1 {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 12px;
            font-family: 'Inter', sans-serif;
            margin-bottom: 0.5rem;
        }
        .header h1 i {
            color: var(--primary);
        }
        .header p {
            color: var(--text-secondary);
            font-size: 0.95rem;
        }
        /* Filter Section */
        .filter-section {
            background: var(--surface);
            padding: 2rem;
            border-radius: var(--radius-lg);
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
            border: 1px solid var(--border);
            box-shadow: var(--shadow-md);
        }
        .filter-form {
            display: flex;
            gap: 15px;
            align-items: flex-end;
            flex-wrap: wrap;
        }
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .filter-label {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 0.85rem;
        }
        .filter-select {
            padding: 12px 18px;
            border: 2px solid var(--border);
            border-radius: var(--radius-sm);
            font-size: 0.9rem;
            min-width: 160px;
            background: var(--surface);
            color: var(--text-primary);
            transition: all 0.3s;
            font-family: 'Inter', sans-serif;
        }
        .filter-select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.15);
        }
        .btn-apply {
            background: var(--primary);
            color: white;
            padding: 12px 28px;
            border-radius: 25px;
            border: none;
            cursor: pointer;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            font-family: 'Inter', sans-serif;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }
        .btn-apply:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(37, 99, 235, 0.4);
        }
        .download-section {
            display: flex;
            gap: 10px;
        }
        .btn-download {
            background: var(--success);
            color: white;
            padding: 12px 25px;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            font-family: 'Inter', sans-serif;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }
        .btn-download:hover {
            background: var(--success-dark);
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(16, 185, 129, 0.4);
        }
        .btn-reset {
            background: var(--surface);
            color: var(--text-secondary);
            padding: 12px 25px;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            border: 2px solid var(--border);
            font-family: 'Inter', sans-serif;
        }
        .btn-reset:hover {
            background: var(--background);
            border-color: var(--primary-light);
            color: var(--primary);
            transform: translateY(-2px);
        }
        /* Summary Cards */
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 20px;
            margin-bottom: 2rem;
        }
        .summary-card {
            background: var(--surface);
            padding: 2rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            display: flex;
            align-items: center;
            gap: 20px;
            transition: all 0.3s;
            border: 1px solid var(--border);
            position: relative;
            overflow: hidden;
        }
        .summary-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--primary-light));
            transform: scaleX(0);
            transition: transform 0.3s;
        }
        .summary-card:hover::before {
            transform: scaleX(1);
        }
        .summary-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-xl);
            border-color: var(--primary-light);
        }
        .summary-icon {
            width: 65px;
            height: 65px;
            border-radius: var(--radius);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.6rem;
            flex-shrink: 0;
        }
        .summary-icon.sales {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
        }
        .summary-icon.cost {
            background: linear-gradient(135deg, var(--secondary), var(--secondary-light));
            color: white;
        }
        .summary-icon.profit {
            background: linear-gradient(135deg, var(--success), var(--success-dark));
            color: white;
        }
        .summary-icon.margin {
            background: linear-gradient(135deg, var(--info), var(--primary));
            color: white;
        }
        .summary-content {
            flex: 1;
        }
        .summary-content h3 {
            font-size: 0.85rem;
            color: var(--text-secondary);
            font-weight: 500;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .summary-content .number {
            font-size: 1.6rem;
            font-weight: 700;
            color: var(--text-primary);
            line-height: 1.2;
            margin-bottom: 8px;
            font-family: 'Inter', sans-serif;
        }
        .summary-content .trend {
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            gap: 5px;
            font-weight: 500;
        }
        .trend.up {
            color: var(--success);
        }
        .trend.down {
            color: var(--error);
        }
        /* Chart Container */
        .chart-container {
            background: var(--surface);
            padding: 2rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            margin-bottom: 2rem;
            border: 1px solid var(--border);
        }
        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 15px;
        }
        .chart-header h2 {
            color: var(--text-primary);
            font-size: 1.2rem;
            font-weight: 600;
            font-family: 'Inter', sans-serif;
        }
        .chart-legend {
            display: flex;
            gap: 20px;
            align-items: center;
            flex-wrap: wrap;
        }
        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.85rem;
            color: var(--text-secondary);
            font-weight: 500;
        }
        .legend-color {
            width: 14px;
            height: 14px;
            border-radius: 4px;
        }
        .legend-sales {
            background: var(--primary);
        }
        .legend-profit {
            background: var(--success);
        }
        .legend-margin {
            background: var(--info);
        }
        .chart-wrapper {
            position: relative;
            height: 350px;
            width: 100%;
        }
        /* Report Table Container */
        .report-table-container {
            background: var(--surface);
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-md);
            margin-bottom: 2rem;
            border: 1px solid var(--border);
        }
        .table-header {
            padding: 1.5rem 2rem;
            border-bottom: 2px solid var(--border-light);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--background);
        }
        .table-header h2 {
            color: var(--text-primary);
            font-size: 1.2rem;
            font-weight: 600;
            font-family: 'Inter', sans-serif;
        }
        .table-period {
            color: var(--text-secondary);
            font-size: 0.85rem;
            font-weight: 500;
        }
        /* Table */
        table {
            width: 100%;
            border-collapse: collapse;
        }
        thead {
            background: var(--background);
        }
        th {
            padding: 16px 20px;
            text-align: left;
            font-weight: 600;
            font-size: 0.8rem;
            border-bottom: 2px solid var(--border);
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        td {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border-light);
            color: var(--text-primary);
            font-size: 0.9rem;
        }
        tbody tr {
            transition: all 0.3s;
        }
        tbody tr:hover {
            background: var(--primary-bg);
        }
        .positive {
            color: var(--success);
            font-weight: 600;
        }
        .negative {
            color: var(--error);
            font-weight: 600;
        }
        tfoot tr {
            background: var(--background);
            font-weight: 600;
        }
        tfoot td {
            border-top: 2px solid var(--border);
            padding: 18px 20px;
        }
        /* Monthly Stats */
        .monthly-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            margin-top: 2rem;
        }
        .month-card {
            background: var(--surface);
            padding: 1.5rem;
            border-radius: var(--radius);
            border-left: 4px solid var(--primary);
            border: 1px solid var(--border);
            transition: all 0.3s;
        }
        .month-card:hover {
            border-color: var(--primary-light);
            box-shadow: var(--shadow-md);
            transform: translateY(-3px);
        }
        .month-card h4 {
            color: var(--text-primary);
            margin-bottom: 12px;
            font-size: 0.95rem;
            font-weight: 600;
        }
        .month-values {
            display: flex;
            justify-content: space-between;
        }
        .month-value {
            display: flex;
            flex-direction: column;
        }
        .month-label {
            font-size: 0.75rem;
            color: var(--text-muted);
            font-weight: 500;
            text-transform: uppercase;
        }
        .month-number {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 0.9rem;
        }
        .month-profit {
            color: var(--success);
        }
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: var(--surface);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border);
        }
        .empty-state i {
            font-size: 4rem;
            color: var(--text-muted);
            margin-bottom: 1.5rem;
            opacity: 0.5;
        }
        .empty-state h3 {
            color: var(--text-primary);
            font-size: 1.3rem;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }
        .empty-state p {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        /* Print Button */
        .print-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: var(--primary);
            color: white;
            padding: 14px 30px;
            border-radius: 30px;
            border: none;
            cursor: pointer;
            font-weight: 600;
            box-shadow: var(--shadow-lg);
            z-index: 100;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s;
            font-family: 'Inter', sans-serif;
        }
        .print-btn:hover {
            background: var(--primary-dark);
            transform: translateY(-3px);
            box-shadow: var(--shadow-xl);
        }
        /* Scrollbar */
        .sidebar::-webkit-scrollbar,
        .content::-webkit-scrollbar {
            width: 6px;
        }
        .sidebar::-webkit-scrollbar-track,
        .content::-webkit-scrollbar-track {
            background: var(--background);
        }
        .sidebar::-webkit-scrollbar-thumb,
        .content::-webkit-scrollbar-thumb {
            background: var(--border);
            border-radius: 3px;
        }
        /* ===== PRINT STYLES ===== */
        @media print {
            * {
                background: transparent !important;
                background-color: white !important;
                color: black !important;
                box-shadow: none !important;
                text-shadow: none !important;
            }
            .navbar,
            .sidebar,
            .filter-section,
            .print-btn {
                display: none !important;
            }
            body {
                background: white !important;
                padding: 0.5in;
                font-size: 11pt;
            }
            .main-container {
                display: block !important;
            }
            .content {
                padding: 0 !important;
                margin: 0 !important;
            }
            .header {
                background: white !important;
                color: black !important;
                padding: 0 !important;
                margin: 0 0 20px 0 !important;
                border: none !important;
            }
            .header h1 {
                color: black !important;
                font-size: 18pt !important;
                border-bottom: 2px solid #333;
                padding-bottom: 5px;
            }
            .summary-cards {
                display: grid;
                grid-template-columns: repeat(4, 1fr);
                gap: 10px;
                margin: 20px 0;
            }
            .summary-card {
                background: white !important;
                border: 1px solid #ddd !important;
                padding: 10px !important;
            }
            table {
                border-collapse: collapse !important;
                font-size: 9pt !important;
            }
            th {
                background: #f0f0f0 !important;
                color: black !important;
                border: 1px solid #ddd !important;
            }
            td {
                border: 1px solid #ddd !important;
                color: black !important;
            }
            .positive {
                color: #059669 !important;
            }
            .negative {
                color: #ef4444 !important;
            }
            @page {
                size: A4;
                margin: 0.5in;
            }
        }
        /* Animations */
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .pulse-animation {
            animation: pulse 1s;
        }
        /* Responsive */
        @media (max-width: 992px) {
            .main-container {
                grid-template-columns: 1fr;
            }
            .sidebar {
                display: none;
            }
            .filter-section {
                flex-direction: column;
                align-items: stretch;
            }
            .chart-wrapper {
                height: 300px;
            }
        }
        @media (max-width: 768px) {
            .summary-cards {
                grid-template-columns: repeat(2, 1fr);
            }
            .filter-form {
                flex-direction: column;
                align-items: stretch;
            }
            .filter-select {
                width: 100%;
            }
            .btn-apply {
                align-self: stretch;
                justify-content: center;
            }
            .chart-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            .navbar {
                flex-wrap: wrap;
                padding: 1rem;
                margin: 10px;
            }
            .nav-menu {
                order: 3;
                width: 100%;
                margin: 15px 0 0;
                justify-content: center;
            }
            .user-info {
                display: none;
            }
            .header {
                flex-direction: column;
                text-align: center;
                gap: 15px;
                padding: 2rem 1.5rem;
            }
            .header h1 {
                font-size: 1.5rem;
            }
        }
        @media (max-width: 576px) {
            .summary-cards {
                grid-template-columns: 1fr;
            }
            .monthly-stats {
                grid-template-columns: repeat(2, 1fr);
            }
            .chart-wrapper {
                height: 250px;
            }
            .download-section {
                flex-direction: column;
            }
            .btn-download, .btn-reset {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar">
        <a href="penjual_dashboard.php" class="logo">
            <i class="fas fa-book-open"></i>
            Pustaka Aruna
        </a>
        <div class="nav-menu">
            <a href="penjual_dashboard.php" class="nav-link">
                <i class="fas fa-home"></i> Dashboard
            </a>
            <a href="penjual_produk.php" class="nav-link">
                <i class="fas fa-book"></i> Produk
            </a>
            <a href="pesanan_penjual.php" class="nav-link">
                <i class="fas fa-inbox"></i> Pesanan
            </a>
            <a href="penjual_laporan.php" class="nav-link active">
                <i class="fas fa-chart-bar"></i> Laporan
            </a>
            <a href="penjual_chat.php" class="nav-link">
                <i class="fas fa-comments"></i> Chat
            </a>
            <div class="user-info" onclick="location.href='penjual_account.php'">
                <div class="user-avatar">
                    <?php
                    $avatar_path = !empty($user_data['avatar']) ? 'uploads/avatars/' . $user_data['avatar'] : '';
                    if (!empty($avatar_path) && file_exists($avatar_path)):
                    ?>
                    <img src="<?php echo $avatar_path . '?t=' . time(); ?>" alt="Avatar">
                    <?php else: ?>
                    <i class="fas fa-user"></i>
                    <?php endif; ?>
                </div>
                <span><?php echo htmlspecialchars($user_name); ?></span>
            </div>
        </div>
    </nav>
    
    <!-- Main Container -->
    <div class="main-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div style="font-size: 1rem; font-weight: 600; margin-bottom: 5px; color: var(--text-primary);">PENJUAL BUKU</div>
                <div style="font-size: 0.85rem; color: var(--text-secondary);">@<?php echo htmlspecialchars($user_name); ?></div>
            </div>
            <div class="sidebar-title">Menu Utama</div>
            <ul class="sidebar-menu">
                <li class="sidebar-item">
                    <a href="penjual_dashboard.php" class="sidebar-link">
                        <i class="fas fa-chart-line"></i> Dashboard
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="penjual_produk.php" class="sidebar-link">
                        <i class="fas fa-book"></i> Produk Buku
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="pesanan_penjual.php" class="sidebar-link">
                        <i class="fas fa-inbox"></i> Pesanan Masuk
                        <?php if ($notifications['order'] > 0): ?>
                        <span class="sidebar-badge"><?php echo $notifications['order']; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="penjual_laporan.php" class="sidebar-link active">
                        <i class="fas fa-chart-bar"></i> Laporan
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="penjual_chat.php" class="sidebar-link" id="sidebarChat">
                        <i class="fas fa-comments"></i> Chat
                        <?php if ($notifications['chat'] > 0): ?>
                        <span class="sidebar-badge"><?php echo $notifications['chat']; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
            </ul>
            <div class="sidebar-title" style="margin-top: 2rem;">Akun Saya</div>
            <ul class="sidebar-menu">
                <li class="sidebar-item">
                    <a href="penjual_account.php" class="sidebar-link">
                        <i class="fas fa-user-circle"></i> Profil Saya
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="logout.php" class="sidebar-link">
                        <i class="fas fa-sign-out-alt"></i> Keluar
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="help.php" class="sidebar-link">
                        <i class="fas fa-question-circle"></i> Bantuan
                    </a>
                </li>
            </ul>
        </aside>
        
        <!-- Main Content -->
        <main class="content">
            <!-- Header -->
            <div class="header">
                <div>
                    <h1><i class="fas fa-chart-bar"></i> Laporan Penjualan</h1>
                    <p>Analisis penjualan, margin, dan keuntungan toko Anda. Data dapat di-download dalam format Excel.</p>
                </div>
            </div>
            
            <!-- Filter Section -->
            <div class="filter-section">
                <form method="GET" class="filter-form">
                    <div class="filter-group">
                        <label class="filter-label">Tahun</label>
                        <select name="tahun" class="filter-select">
                            <?php for ($y = date('Y'); $y >= 2020; $y--): ?>
                            <option value="<?php echo $y; ?>" <?php echo $tahun == $y ? 'selected' : ''; ?>>
                                <?php echo $y; ?>
                            </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">Bulan</label>
                        <select name="bulan" class="filter-select">
                            <option value="0" <?php echo $bulan == 0 ? 'selected' : ''; ?>>Semua Bulan</option>
                            <?php
                            $months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
                            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
                            foreach ($months as $index => $month):
                            ?>
                            <option value="<?php echo $index + 1; ?>" <?php echo $bulan == $index + 1 ? 'selected' : ''; ?>>
                                <?php echo $month; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn-apply">
                        <i class="fas fa-filter"></i> Terapkan Filter
                    </button>
                </form>
                <div class="download-section">
                    <a href="?tahun=<?php echo $tahun; ?>&bulan=<?php echo $bulan; ?>&download=1" class="btn-download">
                        <i class="fas fa-file-excel"></i> Download Excel
                    </a>
                    <a href="penjual_laporan.php" class="btn-reset">
                        <i class="fas fa-sync"></i> Reset
                    </a>
                </div>
            </div>
            
            <!-- Summary Cards -->
            <div class="summary-cards">
                <div class="summary-card">
                    <div class="summary-icon cost">
                        <i class="fas fa-calculator"></i>
                    </div>
                    <div class="summary-content">
                        <h3>Total Modal</h3>
                        <div class="number">Rp <?php echo number_format($total_modal, 0, ',', '.'); ?></div>
                        <div class="trend">
                            <?php echo $total_modal > 0 && $total_penjualan > 0 ? number_format(($total_modal / $total_penjualan * 100), 1) . '% dari penjualan' : '-'; ?>
                        </div>
                    </div>
                </div>
                <div class="summary-card">
                    <div class="summary-icon sales">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="summary-content">
                        <h3>Total Penjualan</h3>
                        <div class="number">Rp <?php echo number_format($total_penjualan, 0, ',', '.'); ?></div>
                        <div class="trend up">
                            <i class="fas fa-arrow-up"></i>
                            <?php
                            $prev_month = $bulan > 1 ? $bulan - 1 : 12;
                            $prev_year = $bulan > 1 ? $tahun : $tahun - 1;
                            $prev_query = mysqli_query($conn, "SELECT SUM(pes.total_harga) as total FROM pesanan pes
                            JOIN produk p ON pes.id_produk = p.id_produk
                            WHERE p.id_penjual = $user_id
                            AND pes.status NOT IN ('dibatalkan', 'ditolak', 'refund')
                            AND YEAR(pes.created_at) = $prev_year
                            AND MONTH(pes.created_at) = $prev_month");
                            $prev_total = mysqli_fetch_assoc($prev_query)['total'] ?? 0;
                            $growth = $prev_total > 0 ? (($total_penjualan - $prev_total) / $prev_total * 100) : 100;
                            echo number_format(abs($growth), 1) . '% dari bulan lalu';
                            ?>
                        </div>
                    </div>
                </div>
                <div class="summary-card">
                    <div class="summary-icon profit">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="summary-content">
                        <h3>Total Keuntungan</h3>
                        <div class="number">Rp <?php echo number_format($total_keuntungan, 0, ',', '.'); ?></div>
                        <div class="trend up">
                            <i class="fas fa-arrow-up"></i>
                            <?php echo $total_penjualan > 0 ? number_format(($total_keuntungan / $total_penjualan * 100), 1) . '% margin bersih' : '-'; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Chart Section -->
            <div class="chart-container">
                <div class="chart-header">
                    <h2>Grafik Penjualan <?php echo $tahun; ?></h2>
                    <div class="chart-legend">
                        <div class="legend-item">
                            <div class="legend-color legend-sales"></div>
                            <span>Penjualan</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color legend-profit"></div>
                            <span>Keuntungan</span>
                        </div>
                    </div>
                </div>
                <div class="chart-wrapper">
                    <canvas id="salesChart"></canvas>
                </div>
            </div>
            
            <!-- Report Table -->
            <div class="report-table-container">
                <div class="table-header">
                    <h2>Detail Transaksi</h2>
                    <div class="table-period">
                        Periode: <?php echo $bulan > 0 ? date('F', mktime(0, 0, 0, $bulan, 1)) . ' ' . $tahun : 'Tahun ' . $tahun; ?>
                    </div>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Kode Pesanan</th>
                            <th>Judul Buku</th>
                            <th>QTY</th>
                            <th>Total Modal</th>
                            <th>Total Penjualan</th>
                            <th>Total Keuntungan</th>
                            <th>Tanggal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($report) > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($report)):
                        $modal_total = $row['modal'] * $row['jumlah'];
                        $profit_class = $row['keuntungan'] >= 0 ? 'positive' : 'negative';
                        ?>
                        <tr>
                            <td><strong style="color: var(--primary);">#<?php echo str_pad($row['id_transaksi'], 3, '0', STR_PAD_LEFT); ?></strong></td>
                            <td><?php echo htmlspecialchars($row['nama_buku']); ?></td>
                            <td><?php echo $row['jumlah']; ?></td>
                            <td>Rp <?php echo number_format($modal_total, 0, ',', '.'); ?></td>
                            <td>Rp <?php echo number_format($row['total_harga'], 0, ',', '.'); ?></td>
                            <td class="<?php echo $profit_class; ?>">
                                Rp <?php echo number_format($row['keuntungan'] * $row['jumlah'], 0, ',', '.'); ?>
                            </td>
                            <td><?php echo date('d M Y', strtotime($row['tanggal'])); ?></td>
                        </tr>
                        <?php endwhile; ?>
                        <?php else: ?>
                        <tr>
                            <td colspan="7">
                                <div class="empty-state">
                                    <i class="fas fa-chart-line"></i>
                                    <h3>Tidak ada data transaksi</h3>
                                    <p>Tidak ada data transaksi untuk periode ini</p>
                                </div>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                    <?php if (mysqli_num_rows($report) > 0): ?>
                    <tfoot>
                        <tr>
                            <td colspan="3">TOTAL</td>
                            <td>Rp <?php echo number_format($total_modal, 0, ',', '.'); ?></td>
                            <td>Rp <?php echo number_format($total_penjualan, 0, ',', '.'); ?></td>
                            <td class="<?php echo $total_keuntungan >= 0 ? 'positive' : 'negative'; ?>">
                                Rp <?php echo number_format($total_keuntungan, 0, ',', '.'); ?>
                            </td>
                            <td></td>
                        </tr>
                    </tfoot>
                    <?php endif; ?>
                </table>
            </div>
            
            <!-- Monthly Stats -->
            <div class="chart-container">
                <h2 style="margin-bottom: 1.5rem; color: var(--text-primary); font-size: 1.2rem; font-weight: 600;">
                    Statistik Bulanan <?php echo $tahun; ?>
                </h2>
                <div class="monthly-stats">
                    <?php
                    $months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
                    foreach ($monthly_data as $index => $data):
                    ?>
                    <div class="month-card">
                        <h4><?php echo $months[$index]; ?></h4>
                        <div class="month-values">
                            <div class="month-value">
                                <span class="month-label">Penjualan</span>
                                <span class="month-number">Rp <?php echo number_format($data['penjualan'], 0, ',', '.'); ?></span>
                            </div>
                            <div class="month-value">
                                <span class="month-label">Keuntungan</span>
                                <span class="month-number month-profit">Rp <?php echo number_format($data['keuntungan'], 0, ',', '.'); ?></span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Print Button -->
    <button class="print-btn" onclick="printReport()">
        <i class="fas fa-print"></i> Print Laporan
    </button>
    
    <script>
        // ===== CHART INITIALIZATION =====
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('salesChart').getContext('2d');
            // Prepare data from PHP
            const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
            const salesData = <?php echo json_encode(array_column($monthly_data, 'penjualan')); ?>;
            const profitData = <?php echo json_encode(array_column($monthly_data, 'keuntungan')); ?>;
            
            const salesChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: months,
                    datasets: [
                        {
                            label: 'Penjualan (Rp)',
                            data: salesData,
                            borderColor: '#2563eb',
                            backgroundColor: 'rgba(37, 99, 235, 0.1)',
                            borderWidth: 2,
                            fill: true,
                            tension: 0.4
                        },
                        {
                            label: 'Keuntungan (Rp)',
                            data: profitData,
                            borderColor: '#10b981',
                            backgroundColor: 'rgba(16, 185, 129, 0.1)',
                            borderWidth: 2,
                            fill: true,
                            tension: 0.4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                            backgroundColor: 'rgba(30, 41, 59, 0.95)',
                            titleColor: '#ffffff',
                            bodyColor: '#e2e8f0',
                            borderColor: '#2563eb',
                            borderWidth: 1,
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    label += 'Rp ' + context.parsed.y.toLocaleString('id-ID');
                                    return label;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                color: '#64748b',
                                callback: function(value) {
                                    return 'Rp ' + value.toLocaleString('id-ID');
                                }
                            },
                            grid: {
                                color: 'rgba(226, 232, 240, 0.5)'
                            }
                        },
                        x: {
                            ticks: {
                                color: '#64748b'
                            },
                            grid: {
                                color: 'rgba(226, 232, 240, 0.5)'
                            }
                        }
                    },
                    interaction: {
                        intersect: false,
                        mode: 'nearest'
                    }
                }
            });
        });
        
        // ===== PRINT FUNCTIONALITY =====
        function printReport() {
            window.print();
        }
        
        // ===== NOTIFICATION SYSTEM =====
        document.addEventListener('DOMContentLoaded', function() {
            const notificationBell = document.getElementById('notificationBell');
            const notificationDropdown = document.getElementById('notificationDropdown');
            const notificationCount = document.getElementById('notificationCount');
            const notificationList = document.getElementById('notificationList');
            
            if (!notificationBell || !notificationDropdown || !notificationCount || !notificationList) {
                console.warn('Notification elements not found.');
                return;
            }
            
            // Toggle dropdown
            notificationBell.addEventListener('click', function(e) {
                e.stopPropagation();
                e.preventDefault();
                notificationDropdown.classList.toggle('show');
                if (notificationDropdown.classList.contains('show')) {
                    loadNotifications();
                    markNotificationsAsRead();
                }
            });
            
            // Close on outside click
            document.addEventListener('click', function(e) {
                if (!notificationBell.contains(e.target) && !notificationDropdown.contains(e.target)) {
                    notificationDropdown.classList.remove('show');
                }
            });
            
            // Prevent dropdown from closing when clicking inside
            notificationDropdown.addEventListener('click', function(e) {
                e.stopPropagation();
            });
            
            function loadNotifications() {
                notificationList.innerHTML = '<div style="text-align: center; padding: 30px 20px;"><i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: var(--primary); margin-bottom: 10px;"></i><br>Memuat notifikasi...</div>';
                fetch('api_get_notifications.php?t=' + Date.now())
                    .then(response => {
                        if (!response.ok) throw new Error('Network error');
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            displayNotifications(data.notifications);
                            updateNotificationCount(data.unread_total);
                        } else {
                            showErrorNotification();
                        }
                    })
                    .catch(error => {
                        console.error('Error loading notifications:', error);
                        showErrorNotification();
                    });
            }
            
            function displayNotifications(notifications) {
                if (!notifications || notifications.length === 0) {
                    notificationList.innerHTML = '<div style="text-align: center; padding: 40px 20px;"><i class="fas fa-bell-slash" style="font-size: 3rem; color: var(--text-muted); margin-bottom: 15px;"></i><p>Tidak ada notifikasi</p></div>';
                    return;
                }
                let html = '';
                notifications.forEach(notif => {
                    const icon = notif.type === 'chat' ? 'fa-comment' : 'fa-shopping-cart';
                    const unreadClass = notif.dibaca == 0 ? 'unread' : '';
                    const time = timeAgo(notif.created_at);
                    html += `
                    <div class="notification-item ${unreadClass}" onclick="window.location.href='${notif.link}'">
                        <div class="notification-icon">
                            <i class="fas ${icon}"></i>
                        </div>
                        <div class="notification-content">
                            <div class="notification-title">${escapeHtml(notif.title)}</div>
                            <div class="notification-message">${escapeHtml(notif.message)}</div>
                            <div class="notification-time">${time}</div>
                        </div>
                    </div>
                    `;
                });
                notificationList.innerHTML = html;
            }
            
            function showErrorNotification() {
                notificationList.innerHTML = `
                <div style="text-align: center; padding: 40px 20px;">
                    <i class="fas fa-exclamation-circle" style="font-size: 3rem; color: var(--error); margin-bottom: 15px;"></i>
                    <p>Gagal memuat notifikasi</p>
                    <button onclick="location.reload()" style="margin-top: 10px; padding: 8px 20px; background: var(--primary); color: white; border: none; border-radius: 5px; cursor: pointer;">
                        <i class="fas fa-sync-alt"></i> Muat Ulang
                    </button>
                </div>
                `;
            }
            
            function escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }
            
            function timeAgo(timestamp) {
                const now = new Date();
                const past = new Date(timestamp);
                const diff = Math.floor((now - past) / 1000);
                if (diff < 60) return 'baru saja';
                if (diff < 3600) {
                    const minutes = Math.floor(diff / 60);
                    return minutes + ' menit yang lalu';
                }
                if (diff < 86400) {
                    const hours = Math.floor(diff / 3600);
                    return hours + ' jam yang lalu';
                }
                const days = Math.floor(diff / 86400);
                return days + ' hari yang lalu';
            }
            
            function updateNotificationCount(count) {
                notificationCount.textContent = count;
                if (count > 0) {
                    notificationCount.style.display = 'flex';
                    notificationBell.classList.add('pulse-animation');
                    setTimeout(() => notificationBell.classList.remove('pulse-animation'), 500);
                } else {
                    notificationCount.style.display = 'none';
                }
            }
            
            function markNotificationsAsRead() {
                fetch('api_mark_notifications_read.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateNotificationCount(0);
                        updateSidebarBadges();
                    }
                })
                .catch(error => console.error('Error marking as read:', error));
            }
            
            document.getElementById('markAllRead')?.addEventListener('click', function(e) {
                e.preventDefault();
                markNotificationsAsRead();
                setTimeout(() => loadNotifications(), 500);
            });
            
            function updateSidebarBadges() {
                fetch('api_get_notifications_count.php')
                .then(response => response.json())
                .then(data => {
                    const approveLink = document.querySelector('a[href="pesanan_penjual.php"] .sidebar-badge');
                    if (approveLink) {
                        if (data.order_count > 0) {
                            approveLink.textContent = data.order_count;
                            approveLink.style.display = 'inline-block';
                        } else {
                            approveLink.style.display = 'none';
                        }
                    }
                    const chatBadge = document.querySelector('#sidebarChat .sidebar-badge');
                    if (chatBadge) {
                        if (data.chat_count > 0) {
                            chatBadge.textContent = data.chat_count;
                            chatBadge.style.display = 'inline-block';
                        } else {
                            chatBadge.style.display = 'none';
                        }
                    }
                })
                .catch(error => console.error('Error updating badges:', error));
            }
            
            function checkNewNotifications() {
                fetch('api_check_new_notifications.php?t=' + Date.now())
                .then(response => response.json())
                .then(data => {
                    if (data.has_new) {
                        updateNotificationCount(data.total_unread);
                        if (notificationDropdown.classList.contains('show')) {
                            loadNotifications();
                        }
                        updateSidebarBadges();
                    }
                })
                .catch(error => console.error('Error checking notifications:', error));
            }
            
            setInterval(checkNewNotifications, 10000);
            updateSidebarBadges();
        });
    </script>
</body>
</html>