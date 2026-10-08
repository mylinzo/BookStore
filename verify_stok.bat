@echo off
REM ============================================
REM VERIFY STOK COLUMN - Check if fix is working
REM Jalankan: verify_stok.bat
REM ============================================

echo.
echo [*] Checking MySQL connection...
cd /d C:\laragon\bin\mysql\mysql5.7.26\bin

echo.
echo ========================================
echo VERIFIKASI KOLOM STOK
echo ========================================
echo.

echo [1] Struktur Tabel BUKU:
echo.
mysql -u root toko-buku -e "DESCRIBE buku;"

echo.
echo [2] Cek Kolom STOK:
echo.
mysql -u root toko-buku -e "DESCRIBE buku;" | findstr "stok"

echo.
echo [3] Data BUKU dengan STOK:
echo.
mysql -u root toko-buku -e "SELECT id_buku, judul, harga, stok FROM buku LIMIT 10;"

echo.
echo [4] Jumlah Total Buku:
echo.
mysql -u root toko-buku -e "SELECT COUNT(*) as 'Total Buku' FROM buku;"

echo.
echo ========================================
if %ERRORLEVEL% EQU 0 (
    echo [SUCCESS] Verifikasi berhasil!
) else (
    echo [ERROR] Ada masalah dengan MySQL
)
echo ========================================
echo.

pause
