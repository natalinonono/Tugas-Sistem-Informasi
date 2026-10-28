<?php
/**
 * Vercel Serverless Entry Point & Front Controller
 * SiDinar Regina - Sistem Informasi & Presensi Putra-Putri Altar
 */

// 1. Ambil path dari URL yang diminta
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$uri = urldecode($uri);

// 2. Tentukan root folder proyek
$rootDir = realpath(__DIR__ . '/..');

// 3. Normalisasi target file
if ($uri === '/' || $uri === '' || $uri === '/index.php') {
    $file = $rootDir . '/index.php';
} else {
    $file = $rootDir . $uri;
    if (is_dir($file)) {
        $file = rtrim($file, '/') . '/index.php';
    } elseif (!str_ends_with($file, '.php')) {
        if (file_exists($file . '.php')) {
            $file = $file . '.php';
        }
    }
}

// 4. Pastikan file berada di dalam root directory dan berformat .php
$realFile = realpath($file);
if ($realFile && str_starts_with($realFile, $rootDir) && file_exists($realFile)) {
    // Hindari rekursi memanggil diri sendiri
    if ($realFile === __FILE__) {
        http_response_code(400);
        exit('Invalid Request');
    }

    // Set server script vars
    $_SERVER['SCRIPT_FILENAME'] = $realFile;
    $_SERVER['SCRIPT_NAME'] = '/' . ltrim(str_replace('\\', '/', str_replace($rootDir, '', $realFile)), '/');
    chdir(dirname($realFile));
    require $realFile;
    exit;
}

// 5. Jika tidak ditemukan
http_response_code(404);
echo "404 Not Found: " . htmlspecialchars($uri);
