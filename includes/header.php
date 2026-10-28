<?php
/**
 * Header & Navbar Komponen
 * SiDinar Regina - Sistem Informasi & Presensi Putra-Putri Altar
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';

$current_user = current_user();
$page_title = $page_title ?? 'SiDinar Regina';
$flash = get_flash();
$base = base_url();
?>
<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($page_title) ?> - SiDinar Regina</title>
  <link rel="stylesheet" href="<?= $base ?>/assets/css/style.css">
  <script>
    window.SIDINAR_BASE_URL = '<?= $base ?>';
  </script>
</head>
<body>

<!-- Navbar Utama -->
<header class="navbar">
  <div class="nav-container">
    <a href="<?= $base ?>/index.php" class="brand-link">
      <div class="brand-logo">⛪</div>
      <div>
        <div class="brand-name">SiDinar Regina</div>
        <div class="brand-sub">Putra-Putri Altar SPMR</div>
      </div>
    </a>

    <?php if (is_logged_in()): ?>
    <nav class="nav-menu">
      <?php if (is_pengurus()): ?>
        <a href="<?= $base ?>/pengurus/index.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'pengurus/index.php') !== false ? 'active' : '' ?>">📊 Ringkasan</a>
        <a href="<?= $base ?>/pengurus/jadwal.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'pengurus/jadwal.php') !== false ? 'active' : '' ?>">📅 Jadwal Misa</a>
        <a href="<?= $base ?>/pengurus/presensi.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'pengurus/presensi.php') !== false ? 'active' : '' ?>">📋 Presensi Sakristi</a>
        <a href="<?= $base ?>/pengurus/anggota.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'pengurus/anggota.php') !== false ? 'active' : '' ?>">👥 Anggota</a>
        <a href="<?= $base ?>/pengurus/warta.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'pengurus/warta.php') !== false ? 'active' : '' ?>">📢 Warta</a>
      <?php else: ?>
        <a href="<?= $base ?>/anggota/index.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'anggota/index.php') !== false ? 'active' : '' ?>">🏠 Beranda</a>
        <a href="<?= $base ?>/anggota/riwayat.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'anggota/riwayat.php') !== false ? 'active' : '' ?>">📜 Riwayat Tugas</a>
        <a href="<?= $base ?>/anggota/profil.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'anggota/profil.php') !== false ? 'active' : '' ?>">👤 Profil</a>
      <?php endif; ?>
    </nav>

    <div class="nav-actions">
      <!-- Theme Switcher -->
      <button type="button" id="btn-theme-toggle" class="btn-icon" onclick="toggleTheme()" title="Ubah Tema">🌙</button>

      <!-- Notification Bell -->
      <div style="position:relative;">
        <button type="button" class="btn-icon" onclick="openModal('modal-notifications')" title="Notifikasi Pengingat Tugas">
          🔔
          <span id="nav-unread-count" class="badge badge-red badge-sm" style="position:absolute; top:-4px; right:-4px; padding:2px 5px; font-size:9px; display:none;">0</span>
        </button>
      </div>

      <!-- User Info Badge & Logout -->
      <div style="display:flex; align-items:center; gap:0.5rem; margin-left:0.25rem;">
        <span class="badge <?= is_pengurus() ? 'badge-amber' : 'badge-emerald' ?>">
          <?= htmlspecialchars($current_user['nama_panggilan'] ?? 'Akun') ?> (<?= ucfirst($current_user['role'] ?? '') ?>)
        </span>
        <a href="<?= $base ?>/logout.php" class="btn btn-secondary btn-sm" onclick="return confirm('Keluar dari sistem SiDinar?')" title="Keluar">
          Keluar
        </a>
      </div>
    </div>
    <?php else: ?>
    <div class="nav-actions">
      <button type="button" id="btn-theme-toggle" class="btn-icon" onclick="toggleTheme()" title="Ubah Tema">🌙</button>
      <a href="<?= $base ?>/login.php" class="btn btn-primary btn-sm">Masuk Akun</a>
      <a href="<?= $base ?>/register.php" class="btn btn-secondary btn-sm">Daftar Anggota</a>
    </div>
    <?php endif; ?>
  </div>
</header>

<!-- Wrapper Halaman Utama -->
<main class="main-wrapper">
  <?php if ($flash): ?>
    <div class="alert alert-<?= htmlspecialchars($flash['type']) ?>">
      <span><?= $flash['type'] === 'success' ? '✅' : ($flash['type'] === 'error' ? '⚠️' : 'ℹ️') ?></span>
      <div><?= htmlspecialchars($flash['message']) ?></div>
    </div>
  <?php endif; ?>
