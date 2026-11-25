<?php
/**
 * Logout Script
 * SiDinar Regina - Sistem Informasi & Presensi Putra-Putri Altar
 */
require_once __DIR__ . '/includes/auth.php';

$_SESSION = [];
if (session_status() === PHP_SESSION_ACTIVE) {
    session_regenerate_id(true);
} else {
    session_start();
}
set_flash('info', 'Anda telah berhasil keluar dari akun. Berkah Dalem!');
header('Location: login.php');
exit;
