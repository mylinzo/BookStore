# 📦 ALUR SISTEM PESANAN - PUSTAKA ARUNA

## Diagram Alur Pesanan

```
┌─────────────────────────────────────────────────────────────┐
│                    PEMBELI                                  │
└─────────────────────────────────────────────────────────────┘
         │
         ▼
┌───────────────────────────────────────────────────────────────┐
│ 1. LIHAT PRODUK (Dashboard Pembeli)                           │
│    Status: [BROWSE]                                           │
│    • Tampilkan produk dari database                           │
│    • File: dashboard_pembeli.php                             │
│    • API: api_get_products.php                              │
└───────────────────────────────────────────────────────────────┘
         │
         ├─ "Pesan" ───────────────────────────────┐
         │                                         │
         ▼                                         │
┌───────────────────────────────────────────────────────────────┐
│ 2. TAMBAH KE KERANJANG                                       │
│    Status: [KERANJANG]                                       │
│    • Form pilih quantity                                     │
│    • File: tambah_keranjang.php                             │
│    • Database: keranjang_produk (INSERT)                    │
│    • Redirect: keranjang_produk.php                         │
└───────────────────────────────────────────────────────────────┘
         │
         ▼
┌───────────────────────────────────────────────────────────────┐
│ 3. LIHAT KERANJANG                                            │
│    Status: [KERANJANG]                                       │
│    • Daftar produk di keranjang                              │
│    • Edit quantity atau hapus item                           │
│    • File: keranjang_produk.php                             │
│    • API: api_checkout.php (remove/update)                 │
└───────────────────────────────────────────────────────────────┘
         │
         ├─ "Checkout" ─────────────────────────────┐
         │                                         │
         ▼                                         │
┌───────────────────────────────────────────────────────────────┐
│ 4. CHECKOUT (Konfirmasi & Pembayaran)                         │
│    Status: [MENUNGGU PEMBAYARAN]                             │
│    • Buat pesanan dari keranjang                            │
│    • Database: pesanan (INSERT)                             │
│    • Kosongkan keranjang: keranjang_produk (DELETE)          │
│    • API: api_checkout.php (action=checkout)               │
│    • Redirect: pesanan_pembeli.php                          │
│    • Pesanan Status: menunggu_pembayaran                    │
└───────────────────────────────────────────────────────────────┘
         │
         ▼
┌───────────────────────────────────────────────────────────────┐
│ 5. UPLOAD BUKTI PEMBAYARAN ⭐ PENTING                         │
│    Status: [DIBAYAR] (setelah upload)                        │
│    • Pembeli upload screenshot transfer/QRIS                │
│    • File: transaksi_pembeli.php                            │
│    • API: api_order_status.php (action=upload)             │
│    • Database: pesanan (UPDATE status='dibayar')            │
│    • Penjual akan verifikasi bukti pembayaran               │
└───────────────────────────────────────────────────────────────┘
         │
         ▼
         ⏳ MENUNGGU PENJUAL MENYETUJUI PEMBAYARAN
         │
         ├─ Penjual Verifikasi ──────────────────────┐
         │                                           │
         ▼                                           │
┌───────────────────────────────────────────────────────────────┐
│ 6. PESANAN DIPROSES                                           │
│    Status: [DIPROSES]                                        │
│    • Penjual mengemas & menyiapkan pesanan                  │
│    • (Update manual oleh penjual di dashboard penjual)      │
└───────────────────────────────────────────────────────────────┘
         │
         ▼
┌───────────────────────────────────────────────────────────────┐
│ 7. PESANAN DIKIRIM                                            │
│    Status: [DIKIRIM]                                         │
│    • Penjual input nomor resi                                │
│    • (Update manual oleh penjual)                            │
│    • Pembeli bisa lihat tracking                             │
└───────────────────────────────────────────────────────────────┘
         │
         ▼
┌───────────────────────────────────────────────────────────────┐
│ 8. TERIMA PESANAN ✓ PEMBELI CONFIRM                          │
│    Status: [SELESAI]                                         │
│    • Pembeli klik "Terima"                                   │
│    • API: api_order_status.php (action=confirm_delivery)    │
│    • Database: pesanan (UPDATE status='selesai')            │
│    • File: pesanan_pembeli.php                              │
└───────────────────────────────────────────────────────────────┘
         │
         ▼
    ✅ PESANAN SELESAI
```

---

## File-File Penting

| File | Fungsi | Method |
|------|--------|--------|
| **dashboard_pembeli.php** | Tampil daftar produk | GET - loadProducts() |
| **api_get_products.php** | API fetch produk | GET |
| **tambah_keranjang.php** | Form tambah ke keranjang | GET/POST |
| **keranjang_produk.php** | Halaman keranjang | GET/POST |
| **api_checkout.php** | API checkout & cart management | POST |
| **pesanan_pembeli.php** | Halaman daftar pesanan | GET |
| **transaksi_pembeli.php** | Form upload bukti pembayaran | GET/POST |
| **api_order_status.php** | API status pesanan | POST |

---

## API Endpoints

### 1. GET PRODUK
```
GET api_get_products.php
Response: {
  success: true,
  data: [{id_produk, nama_buku, harga, stok, ...}],
  pagination: {...}
}
```

### 2. CHECKOUT (Buat Pesanan)
```
POST api_checkout.php
Body: action=checkout
Response: {
  success: true,
  pesanan_ids: [...],
  redirect: "pesanan_pembeli.php"
}
```

### 3. UPLOAD BUKTI PEMBAYARAN
```
POST api_order_status.php
Body: 
  - action=upload_payment_proof
  - order_id
  - metode_pembayaran
  - file: bukti_pembayaran
```

### 4. CONFIRM DELIVERY (Terima Pesanan)
```
POST api_order_status.php
Body: 
  - action=confirm_delivery
  - order_id
Response: {success: true, message: "..."}
```

---

## Database Tables

### produk
```
id_produk, nama_buku, id_penjual, id_kategori, harga, stok, 
deskripsi, foto_produk, created_at, updated_at
```

### keranjang_produk
```
id_keranjang, id_user, id_produk, id_penjual, quantity,
created_at, updated_at
```

### pesanan
```
id_pesanan, id_pembeli, id_penjual, id_produk, quantity,
total_harga, status (menunggu_pembayaran|dibayar|diproses|dikirim|selesai),
metode_pembayaran, bukti_pembayaran, nomor_resi, alamat_pengiriman,
created_at, updated_at
```

---

## Status Pesanan & Aksi Pembeli

| Status | Aksi Pembeli | File |
|--------|-------------|------|
| **menunggu_pembayaran** | Upload bukti pembayaran | transaksi_pembeli.php |
| **dibayar** | Tunggu verifikasi penjual | pesanan_pembeli.php |
| **diproses** | Tunggu dikirim | pesanan_pembeli.php |
| **dikirim** | **Klik "Terima"** ✓ | pesanan_pembeli.php |
| **selesai** | Selesai ✅ | pesanan_pembeli.php |
| **dibatalkan** | - | pesanan_pembeli.php |

---

## Cara Testing Alur Pesanan

### 1. Pastikan Produk Ada
```
Akses: http://localhost/toko-buku/toko-buku/auto_setup_produk.php
(Jalankan jika belum ada produk)
```

### 2. Login Sebagai Pembeli
```
Buka: dashboard_pembeli.php
Lihat produk yang ditampilkan
```

### 3. Tambah ke Keranjang
```
Klik "Pesan" pada produk
Pilih quantity
Klik "Tambah ke Keranjang"
```

### 4. Checkout
```
Buka: keranjang_produk.php
Klik "Checkout"
→ Pesanan dibuat (status: menunggu_pembayaran)
```

### 5. Upload Bukti Pembayaran
```
Buka: pesanan_pembeli.php
Klik "Upload Bukti" pada pesanan
Pilih metode pembayaran
Upload file
→ Status berubah ke "dibayar"
```

### 6. Confirm Delivery
```
(Tunggu penjual update status ke "dikirim")
Klik "Terima" sambil status "dikirim"
→ Status berubah ke "selesai" ✅
```

---

## Troubleshooting

### Error: "Gagal mengkonfirmasi pesanan"
**Solusi:** 
- Pastikan `api_order_status.php` mengirim `action` dengan FormData, bukan JSON
- Cek console browser (F12) untuk error message
- Pesanan harus dalam status `dikirim`

### Error: "Produk tidak muncul"
**Solusi:**
- Jalankan `auto_setup_produk.php`
- Periksa database tabel `produk` punya data
- Cek `api_get_products.php` response

### Pesanan tidak terbuat saat checkout
**Solusi:**
- Pastikan keranjang tidak kosong
- Cek database `keranjang_produk` data ada
- Lihat error dari `api_checkout.php`

---

## Perubahan Terbaru

✅ **Diperbaiki:**
1. `api_order_status.php` - Handle FormData POST dengan benar
2. `pesanan_pembeli.php` - JavaScript confirmDelivery menggunakan FormData
3. `transaksi_pembeli.php` - Halaman upload bukti pembayaran
4. `api_checkout.php` - API buat pesanan dari keranjang
5. Dokumentasi alur sistem lengkap

**Testing:** Jalankan alur di atas dan test setiap tahap ✓
