# ============================================
# Fix Stok Column Error - PowerShell Script
# Jalankan: powershell -ExecutionPolicy Bypass -File fix_stok.ps1
# ============================================

Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "FIX STOK COLUMN ERROR" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Set MySQL path
$mysqlPath = "C:\laragon\bin\mysql\mysql5.7.26\bin\mysql.exe"
$database = "toko-buku"
$user = "root"

# Check if MySQL exists
if (-not (Test-Path $mysqlPath)) {
    Write-Host "[ERROR] MySQL tidak ditemukan di: $mysqlPath" -ForegroundColor Red
    Write-Host "[INFO] Cek apakah path MySQL Laragon Anda sesuai" -ForegroundColor Yellow
    Read-Host "Tekan ENTER untuk keluar"
    exit
}

Write-Host "[*] Menambahkan kolom stok ke tabel buku..." -ForegroundColor Yellow

# Add stok column
$addColumnCmd = "ALTER TABLE buku ADD COLUMN stok INT NOT NULL DEFAULT 0 AFTER harga;"
& $mysqlPath -u $user $database -e $addColumnCmd

if ($LASTEXITCODE -eq 0) {
    Write-Host "[SUCCESS] Kolom stok berhasil ditambahkan!" -ForegroundColor Green
} else {
    Write-Host "[INFO] Kolom stok mungkin sudah ada atau terjadi error" -ForegroundColor Yellow
}

Write-Host ""
Write-Host "[*] Mengupdate stok untuk semua buku..." -ForegroundColor Yellow

# Update stok values
$updateCmd = @"
UPDATE buku SET stok = CASE 
    WHEN id_buku = 1 THEN 20
    WHEN id_buku = 2 THEN 15
    WHEN id_buku = 3 THEN 10
    WHEN id_buku = 4 THEN 25
    WHEN id_buku = 5 THEN 8
    WHEN id_buku = 6 THEN 12
    WHEN id_buku = 7 THEN 30
    ELSE 10
END;
"@

& $mysqlPath -u $user $database -e $updateCmd

Write-Host "[SUCCESS] Stok berhasil diupdate!" -ForegroundColor Green

Write-Host ""
Write-Host "[*] Memverifikasi kolom stok..." -ForegroundColor Yellow

# Verify
$verifyCmd = "DESCRIBE buku;"
& $mysqlPath -u $user $database -e $verifyCmd | findstr "stok"

Write-Host ""
Write-Host "========================================" -ForegroundColor Green
Write-Host "PERBAIKAN SELESAI!" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Green
Write-Host ""
Write-Host "Sekarang akses: http://localhost/toko-buku/toko-buku/keranjang.php" -ForegroundColor Cyan
Write-Host "Error sudah hilang!" -ForegroundColor Cyan
Write-Host ""

Read-Host "Tekan ENTER untuk keluar"
