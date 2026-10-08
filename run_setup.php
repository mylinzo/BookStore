<?php
/**
 * Setup Runner - Jalankan ini di browser untuk initialize database
 * http://localhost/toko-buku/toko-buku/run_setup.php
 */

// Suppress output buffering issues
ob_start();

// Run setup
include 'setup_database.php';
include 'auto_setup_tables.php';

// Output the results
ob_end_flush();
?>
