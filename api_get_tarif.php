<?php
/**
 * API Get Tarif Pengiriman
 * GET /api_get_tarif.php?kurir=JNE
 */
session_start();
include 'koneksi.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $kurir = $_GET['kurir'] ?? '';
    
    if (empty($kurir)) {
        echo json_encode([
            'success' => false,
            'message' => 'Kurir parameter required'
        ]);
        exit;
    }
    
    // Get tarif pengiriman
    $stmt = $conn->prepare("SELECT id_tarif, kurir, biaya_pengiriman, format_resi FROM tarif_pengiriman WHERE kurir = ?");
    $stmt->bind_param("s", $kurir);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        echo json_encode([
            'success' => true,
            'data' => $row
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Tarif not found'
        ]);
    }
    $stmt->close();
} else if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'get_all') {
    // Get all tarif
    $result = $conn->query("SELECT id_tarif, kurir, biaya_pengiriman, format_resi FROM tarif_pengiriman ORDER BY kurir");
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    echo json_encode([
        'success' => true,
        'data' => $data
    ]);
}

header('Content-Type: application/json');
mysqli_close($conn);
?>
