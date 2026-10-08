<?php
session_start();
include 'koneksi.php';

// Check jika user adalah pembeli yang sudah login
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['role'] !== 'pembeli') {
    header('Location: login.php');
    exit;
}

$id_pembeli = $_SESSION['id_user'];
$nama_pembeli = $_SESSION['nama'] ?? 'Pembeli';

// Fetch laporan data
$query = "
    SELECT 
        p.id_pesanan,
        p.id_pembeli,
        p.id_penjual,
        p.total_harga,
        p.metode_pembayaran,
        p.status,
        p.created_at,
        pr.nama_buku,
        pr.harga as harga_satuan,
        p.quantity,
        ps.nama as nama_penjual,
        pb.nama as nama_pembeli,
        p.alamat_pengiriman,
        p.nomor_resi,
        p.bukti_pembayaran
    FROM pesanan p
    LEFT JOIN produk pr ON p.id_produk = pr.id_produk
    LEFT JOIN users ps ON p.id_penjual = ps.id_user
    LEFT JOIN users pb ON p.id_pembeli = pb.id_user
    WHERE p.id_pembeli = ?
    ORDER BY p.created_at DESC
";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $id_pembeli);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$orders = [];
while ($row = mysqli_fetch_assoc($result)) {
    $orders[] = $row;
}
mysqli_stmt_close($stmt);

// Status mapping
function getStatusIndonesia($status) {
    $statusMap = [
        'menunggu_pembayaran' => 'Menunggu Pembayaran',
        'dibayar' => 'Dibayar',
        'diproses' => 'Diproses',
        'dikirim' => 'Dikirim',
        'selesai' => 'Selesai',
        'dibatalkan' => 'Dibatalkan',
        'ditolak' => 'Ditolak'
    ];
    return $statusMap[$status] ?? $status;
}

// Format currency
function formatCurrency($amount) {
    return 'Rp ' . number_format((int)$amount, 0, ',', '.');
}

// Generate HTML that can be printed to PDF via browser
$html = '
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Pesanan - Pustaka Aruna</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --primary-light: #3b82f6;
            --primary-bg: #eff6ff;
            --secondary: #64748b;
            --success: #22c55e;
            --success-bg: #f0fdf4;
            --error: #ef4444;
            --error-bg: #fef2f2;
            --warning: #f59e0b;
            --warning-bg: #fffbeb;
            --background: #f8fafc;
            --surface: #ffffff;
            --border: #e2e8f0;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1);
            --radius: 12px;
            --radius-sm: 8px;
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: "Inter", "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            color: var(--text-primary);
            background: var(--surface);
            line-height: 1.5;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 3px solid var(--primary);
            padding-bottom: 20px;
            background: linear-gradient(135deg, var(--primary-bg), var(--surface));
            border-radius: var(--radius);
            padding: 25px;
        }
        .header h1 {
            font-size: 28px;
            color: var(--primary);
            margin-bottom: 10px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .header p {
            font-size: 14px;
            color: var(--text-secondary);
        }
        .info-section {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
            padding: 15px 20px;
            background: var(--primary-bg);
            border-radius: var(--radius-sm);
            border-left: 4px solid var(--primary);
            border: 1px solid var(--border);
        }
        .info-item {
            flex: 1;
        }
        .info-item label {
            font-weight: 600;
            color: var(--primary);
            display: block;
            margin-bottom: 5px;
            font-size: 0.9rem;
        }
        .info-item span {
            color: var(--text-primary);
            font-weight: 500;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
            background: var(--surface);
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border);
        }
        thead {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
        }
        th {
            padding: 14px 16px;
            text-align: left;
            font-weight: 600;
            border-bottom: 2px solid var(--primary-dark);
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        td {
            padding: 14px 16px;
            border-bottom: 1px solid var(--border);
            vertical-align: top;
            font-size: 0.9rem;
        }
        tbody tr:nth-child(even) {
            background: var(--background);
        }
        tbody tr:hover {
            background: var(--primary-bg);
            transition: background 0.2s;
        }
        .status-badge {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            border: 1px solid;
        }
        .status-selesai { background: var(--success-bg); color: #166534; border-color: var(--success); }
        .status-dikirim { background: var(--primary-bg); color: #1e40af; border-color: var(--primary); }
        .status-diproses { background: var(--info-bg); color: #0e7490; border-color: #06b6d4; }
        .status-dibayar { background: var(--background); color: var(--text-primary); border-color: var(--border); }
        .status-menunggu_pembayaran { background: var(--warning-bg); color: #92400e; border-color: var(--warning); }
        .status-ditolak { background: var(--error-bg); color: #991b1b; border-color: var(--error); }
        .status-dibatalkan { background: var(--error-bg); color: #991b1b; border-color: var(--error); }
        .total-row {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            font-weight: 700;
        }
        .total-row td {
            border-color: var(--primary-dark);
            font-size: 1.1rem;
        }
        .currency {
            text-align: right;
            font-family: monospace;
            font-weight: 600;
        }
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 2px dashed var(--border);
            text-align: center;
            font-size: 0.8rem;
            color: var(--text-secondary);
            background: var(--background);
            padding: 20px;
            border-radius: var(--radius);
        }
        .no-data {
            text-align: center;
            padding: 40px;
            color: var(--text-secondary);
            font-style: italic;
            background: var(--surface);
            border-radius: var(--radius);
            border: 1px solid var(--border);
        }
        @media print {
            body { background: white; }
            .container { margin: 0; padding: 20px; }
            .no-print { display: none !important; }
            tbody tr:hover { background: transparent !important; }
            table { box-shadow: none; border: 1px solid #ddd; }
        }
        .print-btn {
            background: var(--primary);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: var(--radius-sm);
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 20px;
            margin-right: 10px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
            box-shadow: var(--shadow-sm);
        }
        .print-btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        .download-btn {
            background: var(--success);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: var(--radius-sm);
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 20px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
            box-shadow: var(--shadow-sm);
        }
        .download-btn:hover {
            background: #16a34a;
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        .btn-icon {
            font-size: 1rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="no-print" style="margin-bottom: 20px;">
            <button class="print-btn" onclick="window.print()">
                <i class="fas fa-print btn-icon"></i> Print
            </button>
            <button class="download-btn" onclick="downloadPDF()">
                <i class="fas fa-download btn-icon"></i> Download PDF
            </button>
        </div>

        <div class="header">
            <h1><i class="fas fa-book-open"></i> LAPORAN PESANAN</h1>
            <p>Pustaka Aruna - Platform Jual Beli Buku Online</p>
        </div>

        <div class="info-section">
            <div class="info-item">
                <label>Nama Pembeli:</label>
                <span>' . htmlspecialchars($nama_pembeli) . '</span>
            </div>
            <div class="info-item">
                <label>Total Pesanan:</label>
                <span>' . count($orders) . ' pesanan</span>
            </div>
            <div class="info-item">
                <label>Tanggal Export:</label>
                <span>' . date('d F Y, H:i:s') . '</span>
            </div>
        </div>

        ';

// Build table content if orders exist
if (count($orders) > 0) {
    $html .= '
        <table>
            <thead>
                <tr>
                    <th style="width: 5%;">No</th>
                    <th style="width: 12%;">Kode Order</th>
                    <th style="width: 20%;">Produk</th>
                    <th style="width: 15%;">Penjual</th>
                    <th style="width: 5%;">Qty</th>
                    <th style="width: 15%;">Harga Total</th>
                    <th style="width: 12%;">Status</th>
                    <th style="width: 16%;">Metode Pembayaran</th>
                </tr>
            </thead>
            <tbody>
            ';

    $grand_total = 0;
    $no = 1;

    foreach ($orders as $order) {
        $grand_total += $order['total_harga'];
        $status = $order['status'];
        $status_class = 'status-' . str_replace(' ', '_', strtolower($status));
        
        $html .= '
            <tr>
                <td>' . $no++ . '</td>
                <td><strong>' . htmlspecialchars($order['id_pesanan']) . '</strong></td>
                <td>' . htmlspecialchars($order['nama_buku'] ?? '-') . '</td>
                <td>' . htmlspecialchars($order['nama_penjual'] ?? '-') . '</td>
                <td>' . ($order['quantity'] ?? '-') . '</td>
                <td class="currency">' . formatCurrency($order['total_harga']) . '</td>
                <td><span class="status-badge ' . $status_class . '">' . getStatusIndonesia($status) . '</span></td>
                <td>' . htmlspecialchars($order['metode_pembayaran'] ?? '-') . '</td>
            </tr>
            ';
    }

    $html .= '
            <tr class="total-row">
                <td colspan="5" style="text-align: right;">GRAND TOTAL:</td>
                <td class="currency">' . formatCurrency($grand_total) . '</td>
                <td colspan="2"></td>
            </tr>
        </tbody>
    </table>
        ';
} else {
    $html .= '
        <div class="no-data">
            <p><i class="fas fa-inbox" style="font-size: 2rem; margin-bottom: 15px; color: var(--text-secondary);"></i></p>
            <p>Tidak ada pesanan untuk ditampilkan.</p>
        </div>
        ';
}

$html .= '

        <div class="footer">
            <p><strong style="color: var(--primary);"><i class="fas fa-book-open"></i> Pustaka Aruna</strong></p>
            <p>Laporan ini dibuat secara otomatis oleh sistem.</p>
            <p>Dicetak pada: ' . date('d F Y H:i:s') . '</p>
            <p style="margin-top: 10px; font-size: 0.75rem; color: var(--text-secondary);">
                <i class="fas fa-headset"></i> Untuk pertanyaan atau bantuan, hubungi customer support kami di support@pustaka-aruna.com
            </p>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script>
        function downloadPDF() {
            const element = document.querySelector(".container");
            const controls = document.querySelector(".no-print");
            const filename = "Laporan_Pesanan_" + new Date().toISOString().slice(0,10).replace(/-/g,"") + ".pdf";
            
            // temporarily hide buttons so html2pdf does not render them
            if (controls) {
                controls.style.display = "none";
            }

            const options = {
                margin: [10, 10, 10, 10],
                filename: filename,
                image: { type: "jpeg", quality: 0.98 },
                html2canvas: { scale: 2, useCORS: true },
                jsPDF: { orientation: "portrait", unit: "mm", format: "a4" }
            };
            
            html2pdf().set(options).from(element).save().then(() => {
                // restore visibility after PDF generated
                if (controls) {
                    controls.style.display = "";
                }
            });
        }
    </script>
</body>
</html>
';

header('Content-Type: text/html; charset=utf-8');
echo $html;
?>