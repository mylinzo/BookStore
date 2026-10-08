<?php
/**
 * Upload Diagnostic Test
 * Untuk debug masalah upload gambar
 */

echo "<h1>🔍 Diagnostik Upload Gambar</h1>";
echo "<hr>";

// 1. Check upload_dir
$upload_dir = 'uploads/produk/';
echo "<h2>1. Status Folder Upload</h2>";
if (is_dir($upload_dir)) {
    echo "✅ Folder <code>$upload_dir</code> ada<br>";
    
    if (is_writable($upload_dir)) {
        echo "✅ Folder writable (bisa tulis file)<br>";
    } else {
        echo "❌ Folder TIDAK writable - MASALAH! Ubah permissions ke 755 atau 777<br>";
    }
    
    // Count existing files
    $files = glob($upload_dir . '*');
    echo "📁 Jumlah file: " . count($files) . "<br>";
    if (count($files) > 0) {
        echo "📄 File terbaru:<br>";
        usort($files, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });
        for ($i = 0; $i < min(5, count($files)); $i++) {
            $size = filesize($files[$i]) / 1024; // KB
            echo "   - " . basename($files[$i]) . " (" . round($size, 2) . " KB)<br>";
        }
    }
} else {
    echo "❌ Folder <code>$upload_dir</code> TIDAK ADA<br>";
}

echo "<hr>";

// 2. Check PHP configuration
echo "<h2>2. Konfigurasi PHP Upload</h2>";
echo "Max file upload: <code>" . ini_get('upload_max_filesize') . "</code><br>";
echo "Max POST size: <code>" . ini_get('post_max_size') . "</code><br>";
echo "Temp dir: <code>" . ini_get('upload_tmp_dir') ?: sys_get_temp_dir() . "</code><br>";
echo "Memory limit: <code>" . ini_get('memory_limit') . "</code><br>";

// Parse sizes
$uploadMax = ini_get('upload_max_filesize');
$postMax = ini_get('post_max_size');

echo "<p>⚠️ Pastikan:</p>";
echo "<ul>";
echo "<li><code>upload_max_filesize</code> >= 50MB (current: $uploadMax)</li>";
echo "<li><code>post_max_size</code> >= 50MB (current: $postMax)</li>";
echo "</ul>";

echo "<hr>";

// 3. Check finfo
echo "<h2>3. MIME Type Detection</h2>";
if (function_exists('finfo_file')) {
    echo "✅ finfo_file() tersedia<br>";
    echo "Dapat mendeteksi MIME type dengan akurat<br>";
} else {
    echo "⚠️ finfo_file() TIDAK tersedia<br>";
    echo "Upload akan rely pada extension saja<br>";
}

echo "<hr>";

// 4. Test file permissions
echo "<h2>4. Test Baca-Tulis Folder</h2>";
$test_file = $upload_dir . '.test_' . time() . '.txt';
if (@file_put_contents($test_file, 'test')) {
    echo "✅ Bisa menulis file ke folder<br>";
    if (@unlink($test_file)) {
        echo "✅ Bisa menghapus file dari folder<br>";
    } else {
        echo "⚠️ Bisa tulis tapi tidak bisa hapus<br>";
    }
} else {
    echo "❌ TIDAK bisa menulis ke folder. Ubah permissions!<br>";
}

echo "<hr>";

// 5. Form upload test
echo "<h2>5. Test Upload File</h2>";
echo "<form method='post' enctype='multipart/form-data'>";
echo "Pilih gambar: <input type='file' name='test_image' accept='image/*'>";
echo " <button type='submit' name='test_upload'>Upload Test</button>";
echo "</form>";

if (isset($_POST['test_upload']) && isset($_FILES['test_image'])) {
    $file = $_FILES['test_image'];
    
    echo "<h3>Hasil Upload Test:</h3>";
    echo "Nama file: <code>" . $file['name'] . "</code><br>";
    echo "Tipe MIME: <code>" . $file['type'] . "</code><br>";
    echo "Ukuran: <code>" . round($file['size'] / 1024, 2) . " KB</code><br>";
    echo "Error: <code>" . ($file['error'] === 0 ? 'Tidak ada error' : 'Error #' . $file['error']) . "</code><br>";
    echo "Tmp file: <code>" . $file['tmp_name'] . "</code><br>";
    
    if ($file['error'] === 0) {
        // Get actual MIME type
        if (function_exists('finfo_file')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $actual_mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            echo "Actual MIME type: <code>$actual_mime</code><br>";
        }
        
        // Try upload
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $new_name = 'test_' . time() . '.' . $ext;
        $upload_path = $upload_dir . $new_name;
        
        if (move_uploaded_file($file['tmp_name'], $upload_path)) {
            echo "<p style='color: green;'><strong>✅ Upload BERHASIL!</strong></p>";
            echo "File tersimpan: <code>$upload_path</code><br>";
            echo "URL: <code><a href='" . htmlspecialchars($upload_path) . "' target='_blank'>" . htmlspecialchars($upload_path) . "</a></code>";
            
            // Test read back
            if (file_exists($upload_path)) {
                echo "<br>✅ File dapat dibaca kembali<br>";
                echo "Ukuran file: " . filesize($upload_path) . " bytes<br>";
                
                // Try to delete
                if (@unlink($upload_path)) {
                    echo "✅ File test dihapus";
                } else {
                    echo "⚠️ File test tidak bisa dihapus";
                }
            } else {
                echo "<br>❌ File tidak dapat dibaca setelah upload!";
            }
        } else {
            echo "<p style='color: red;'><strong>❌ Upload GAGAL!</strong></p>";
            echo "move_uploaded_file() gagal. Cek permissions folder!<br>";
        }
    } else {
        echo "<p style='color: red;'><strong>❌ Error upload file!</strong></p>";
    }
}

echo "<hr>";
echo "<p style='color: #666; font-size: 0.9rem;'>";
echo "Dibuat: " . date('Y-m-d H:i:s') . " | ";
echo "<a href='?'>Refresh</a> | ";
echo "<a href='../'>Kembali</a>";
echo "</p>";
?>
