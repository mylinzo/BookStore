@echo off
REM ============================================
REM FIX STOK COLUMN ERROR - Windows Batch Script
REM Jalankan: fix_stok.bat
REM ============================================

echo.
echo [*] Connecting to MySQL...
echo [*] Running fix for missing stok column...
echo.

cd /d C:\laragon\bin\mysql\mysql5.7.26\bin

REM Run SQL command to add stok column
mysql -u root toko-buku -e "ALTER TABLE buku ADD COLUMN stok INT NOT NULL DEFAULT 0 AFTER harga; SELECT 'Kolom stok berhasil ditambahkan!' as status;"

if %ERRORLEVEL% EQU 0 (
    echo.
    echo [SUCCESS] Kolom stok berhasil ditambahkan ke tabel buku!
    echo.
    echo [*] Updating stok values...
    mysql -u root toko-buku -e "UPDATE buku SET stok = 20; SELECT COUNT(*) as 'Updated rows' FROM buku;"
    echo.
    echo [*] Verifying structure...
    mysql -u root toko-buku -e "DESCRIBE buku;" | findstr "stok"
    echo.
    echo [SUCCESS] Semuanya berhasil! Error sudah diperbaiki.
) else (
    echo.
    echo [ERROR] Gagal menjalankan command.
    echo [INFO] Kemungkinan penyebab:
    echo   - MySQL belum running
    echo   - Kolom stok sudah ada
    echo   - Path MySQL tidak sesuai
    echo.
)

pause
