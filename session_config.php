<?php
// ============================================
// SESSION CONFIGURATION FILE
// ============================================
// File ini harus di-include di awal sebelum session_start()

// Pastikan session handler menggunakan file (default)
ini_set('session.save_handler', 'files');
ini_set('session.save_path', sys_get_temp_dir());

// Set session timeout 30 menit
ini_set('session.gc_maxlifetime', 1800);

// Cookie settings
ini_set('session.cookie_lifetime', 0);  // Session cookie (hilang saat browser tutup)
ini_set('session.cookie_path', '/');
ini_set('session.cookie_httponly', 1);   // Tidak bisa diakses via JavaScript
ini_set('session.use_strict_mode', 1);  // Strict session ID validation

// Prevent session fixation
ini_set('session.cookie_samesite', 'Lax');

// Use only cookies, not URL-based session ID
ini_set('session.use_only_cookies', 1);

// Disable transparent session ID
ini_set('session.use_trans_sid', 0);
?>
