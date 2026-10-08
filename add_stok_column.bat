@echo off
REM Script untuk menambahkan kolom stok ke tabel buku
SET MYSQL_PATH=C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe

REM Jalankan perintah SQL
echo Running SQL commands...
"%MYSQL_PATH%" -u root toko-buku -e "ALTER TABLE buku ADD COLUMN stok INT NOT NULL DEFAULT 0 AFTER harga;" 2>nul

echo.
echo Cek kolom stok sudah ditambahkan...
"%MYSQL_PATH%" -u root toko-buku -e "DESCRIBE buku;" | findstr "stok"

echo.
echo Update stok default ke 20 untuk semua buku...
"%MYSQL_PATH%" -u root toko-buku -e "UPDATE buku SET stok = 20;" 2>nul

echo.
echo SELESAI!
pause
