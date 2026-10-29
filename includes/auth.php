<?php
/**
 * Modul Autentikasi & Sesi
 * SiDinar Regina - Sistem Informasi & Presensi Putra-Putri Altar
 */

date_default_timezone_set('Asia/Jakarta');

if (session_status() === PHP_SESSION_NONE) {
    if (php_sapi_name() === 'cli') {
        @ini_set('session.save_path', sys_get_temp_dir());
    }
    @session_start();
}

require_once __DIR__ . '/../config/database.php';

function is_logged_in(): bool {
    return isset($_SESSION['user']) && !empty($_SESSION['user']['id']);
}

function current_user(): ?array {
    return $_SESSION['user'] ?? null;
}

function is_pengurus(): bool {
    return is_logged_in() && ($_SESSION['user']['role'] ?? '') === 'pengurus';
}

function is_anggota(): bool {
    return is_logged_in() && ($_SESSION['user']['role'] ?? '') === 'anggota';
}

function is_aktif(): bool {
    return is_logged_in() && ($_SESSION['user']['status'] ?? '') === 'aktif';
}

function require_login(?string $redirect_to = null): void {
    if (!is_logged_in()) {
        set_flash('error', 'Silakan masuk terlebih dahulu untuk mengakses sistem.');
        $dest = $redirect_to ?: (base_url('login.php') ?: '/login.php');
        header('Location: ' . $dest);
        exit;
    }
}

function require_pengurus(?string $redirect_to = null): void {
    require_login();
    if (!is_pengurus()) {
        set_flash('error', 'Akses ditolak. Halaman ini khusus untuk Pengurus Sakristi.');
        $dest = $redirect_to ?: (base_url('anggota/index.php') ?: '/anggota/index.php');
        header('Location: ' . $dest);
        exit;
    }
}

function require_anggota(?string $redirect_to = null): void {
    require_login();
}

function set_flash(string $type, string $message): void {
    $_SESSION['flash'] = [
        'type' => $type, // 'success', 'error', 'info', 'warning'
        'message' => $message
    ];
}

function get_flash(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function base_url(string $path = ''): string {
    if (php_sapi_name() === 'cli') {
        $trimmed = ltrim($path, '/');
        return $trimmed ? $trimmed : '.';
    }
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $script_dir = str_replace('\\', '/', dirname($script));
    $root = preg_replace('/(\/(pengurus|anggota|api))$/i', '', $script_dir);
    if ($root === '/' || $root === '.') {
        $root = '';
    }
    $trimmed = ltrim($path, '/');
    return $trimmed ? ($root . '/' . $trimmed) : $root;
}
