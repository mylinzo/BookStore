-- Migration: add_alamat_penjual.sql
-- Adds alamat_penjual to users and produk if not exists

ALTER TABLE `users` ADD COLUMN `alamat_penjual` TEXT NULL;
ALTER TABLE `produk` ADD COLUMN `alamat_penjual` TEXT NULL;
