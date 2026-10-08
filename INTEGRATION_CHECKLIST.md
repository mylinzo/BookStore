# ✅ Checklist Integrasi Notification System v1.1

## 📦 Files yang Sudah Dibuat

✅ `api_get_notifications.php` - Ambil notifikasi  
✅ `api_check_new_notifications.php` - Real-time checking  
✅ `api_mark_notifications_read.php` - Mark as read  
✅ `api_get_notifications_count.php` - Ambil badge count  
✅ `api_update_activity.php` - Session keep-alive  
✅ `notification_header.php` - Reusable component  
✅ `navbar_template.php` - Ready-to-use navbar with notifications  

---

## 🚀 Cara Integrase ke Halaman Sidebar

### Step 1: Di File Head PHP
Tambahkan 2 baris ini setelah `include 'koneksi.php'`:

```php
<?php
session_start();
include 'koneksi.php';

// === ADD THIS ===
require 'navbar_template.php';  // <- TAMBAH INI
// === END ===

// ... rest of ini PHP code ...
?>
```

### Step 2: Ganti Navbar HTML Lama

**HAPUS** navbar HTML yang lama (mencari tag `<nav class="navbar">`)  
**GANTI** dengan output dari `navbar_template.php` (sudah auto-include)

---

## 📋 Halaman yang Perlu Di-Update

Halaman di sidebar penjual:
- [ ] penjual_dashboard.php
- [ ] penjual_produk.php
- [ ] pesanan_penjual.php
- [ ] penjual_laporan.php
- [ ] penjual_chat.php
- [ ] penjual_kategori.php
- [ ] penjual_account.php
- [ ] penjual_management.php
- [ ] penjual_verifikasi_pembayaran.php
- [ ] penjual_setup_qris.php

Halaman di sidebar pembeli:
- [ ] dashboard_pembeli.php
- [ ] keranjang.php
- [ ] pesanan_pembeli.php
- [ ] halaman_notifikasi.php
- [ ] pembeli_cht.php
- [ ] halaman_pembeli.php
- [ ] dll

---

## 🔄 Update Process

### Untuk penjual_produk.php:

```php
<?php
session_start();
include 'koneksi.php';
require 'navbar_template.php';  // <- ADD THIS

// Check user login and role
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['role'] !== 'penjual') {
    header("Location: login.php");
    exit;
}

// ... rest of existing code ...
?>
```

Lalu **HAPUS navbar HTML lama** dan biarkan `navbar_template.php` yang handle.

### Untuk penjual_laporan.php:

Sama seperti di atas - cukup 2 langkah:
1. Tambah `require 'navbar_template.php';` setelah koneksi
2. Hapus navbar HTML lama

---

## ⚙️ API Endpoints yang Digunakan

```
GET  /api_get_notifications.php
     → Return: list of notifications
     → Body: JSON dengan notifications array

GET  /api_check_new_notifications.php?t=timestamp
     → Return: count of notifications
     → Body: JSON dengan total_count, chat_count, order_count

POST /api_mark_notifications_read.php
     → Return: success status
     → Body: JSON (empty)

GET  /api_get_notifications_count.php
     → Return: badge counts
     → Body: JSON dengan chat_count, order_count

POST /api_update_activity.php
     → Return: success status
     → Body: JSON (empty)
```

---

## 🧪 Testing Checklist

- [ ] Dashboard - notification bell tampil
- [ ] Dashboard - clicking bell load notifikasi
- [ ] Dashboard - dropdown auto-update setiap 10 detik
- [ ] Produk page - notification bell tampil
- [ ] Pesanan page - notification bell tampil
- [ ] Laporan page - notification bell tampil
- [ ] Chat page - notification bell tampil
  
- [ ] Send chat → receiver lihat badge update
- [ ] Buyer order → seller lihat badge update
- [ ] Click "Tandai semua dibaca" → badge hilang
- [ ] Refresh page → badge still ada jika ada notifikasi baru

---

## 🐛 Troubleshooting

| Issue | Solution |
|-------|----------|
| Notification bell tidak tampil | Pastikan Font Awesome CSS di-load, cek `navbar_template.php` di-include |
| Badge count tidak update | Buka DevTools (F12) → Console, lihat ada error atau tidak |
| API respond dengan error | Cek user session, cek database connection, debug API file |
| Notifikasi kosong padahal ada chat | Cek field `dibaca` di tabel `chat`, pastikan sudah UPDATE |

---

## 📊 Database Fields Required

### Table: `chat`
```sql
- id_chat
- id_pengirim
- id_penerima
- pesan
- dibaca (TINYINT 0 or 1) ← REQUIRED!
- created_at
```

### Table: `transaksi`
```sql
- id_transaksi
- id_pembeli
- status (enum: 'menunggu_pembayaran', 'approve', 'dikirim', etc)
- tanggal
```

### Table: `detail_transaksi`
```sql
- id_detail
- id_transaksi
- id_produk
```

### Table: `produk`
```sql
- id_produk
- id_penjual
- nama_buku
```

---

## 💡 Tips

- Jangan ubah nama API files
- Notification system auto-sync dengan database
- Real-time check setiap 10 detik (bisa di-change di JavaScript)
- Session keep-alive setiap 5 menit
- CSS variables bisa di-customize lewat :root

---

## 📝 Next Steps

1. Include `navbar_template.php` di penjual_produk.php dulu
2. Test di dashboard - pastikan notification bell kerja
3. Repeat untuk semua halaman sidebar lainnya
4. Test end-to-end: chat & order notifications

---

**Last Updated:** February 21, 2026  
**Status:** Ready for Production ✅
