<?php
/**
 * Header & Navbar Komponen - SiDinar Regina
 * Tampilan & Desain Identik dengan Web Next.js (Material 3 Liturgical Marian Design)
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';

$current_user = current_user();
$page_title = $page_title ?? 'SiDinar Regina';
$flash = get_flash();
$base = base_url();
$is_pengurus = is_pengurus();
$current_script = basename($_SERVER['PHP_SELF']);
$current_dir = basename(dirname($_SERVER['PHP_SELF']));
?>
<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($page_title) ?> - SiDinar Regina</title>
  <link rel="icon" type="image/png" href="<?= $base ?>/assets/img/logo.png">

  <!-- Google Fonts: Inter, Plus Jakarta Sans, & Material Symbols Outlined -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@500;600;700;800&family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap" rel="stylesheet">

  <!-- Tailwind CSS CDN dengan Theme Tokens Identik Next.js -->
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      darkMode: 'class',
      theme: {
        extend: {
          colors: {
            'primary': '#00236f',
            'primary-container': '#1e3a8a',
            'primary-fixed': '#dce1ff',
            'primary-fixed-dim': '#b6c4ff',
            'on-primary': '#ffffff',
            'on-primary-container': '#90a8ff',
            'on-primary-fixed': '#00164e',

            'secondary': '#904d00',
            'secondary-container': '#fe932c',
            'secondary-fixed': '#ffdcc3',
            'secondary-fixed-dim': '#ffb77d',
            'on-secondary': '#ffffff',
            'on-secondary-container': '#663500',

            'tertiary': '#00312c',
            'tertiary-container': '#004942',
            'tertiary-fixed': '#89f5e7',
            'tertiary-fixed-dim': '#6bd8cb',
            'on-tertiary': '#ffffff',
            'on-tertiary-fixed': '#00201d',

            'surface': '#f8f9ff',
            'surface-dim': '#cbdbf5',
            'surface-bright': '#f8f9ff',
            'surface-container-lowest': '#ffffff',
            'surface-container-low': '#eff4ff',
            'surface-container': '#e5eeff',
            'surface-container-high': '#dce9ff',
            'surface-container-highest': '#d3e4fe',

            'on-surface': '#0b1c30',
            'on-surface-variant': '#444651',
            'outline': '#757682',
            'outline-variant': '#c5c5d3',

            'background': '#f8f9ff',
            'on-background': '#0b1c30',

            'error': '#ba1a1a',
            'error-container': '#ffdad6',
            'on-error': '#ffffff',
            'on-error-container': '#93000a',
          },
          fontFamily: {
            sans: ['Inter', 'sans-serif'],
            headline: ['Plus Jakarta Sans', 'sans-serif'],
          }
        }
      }
    }
  </script>

  <!-- Fallback & Custom CSS -->
  <link rel="stylesheet" href="<?= $base ?>/assets/css/style.css">

  <script>
    window.SIDINAR_BASE_URL = '<?= $base ?>';
  </script>
</head>
<body class="min-h-screen bg-background text-on-surface antialiased flex flex-col font-sans">

<!-- Header / Sticky Navigation Bar Identik Next.js -->
<header class="sticky top-0 z-40 w-full bg-surface-container-lowest/95 backdrop-blur-md border-b border-surface-container shadow-xs">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="flex items-center justify-between h-16 sm:h-20 gap-4">

      <!-- Brand Logo & Name -->
      <a href="<?= $base ?>/index.php" class="flex items-center gap-3 group shrink-0">
        <div class="w-10 h-10 sm:w-12 sm:h-12 rounded-full overflow-hidden bg-white shadow-sm border border-surface-container p-0.5 group-hover:scale-105 transition-transform flex items-center justify-center">
          <img
            src="<?= $base ?>/assets/img/logo.png"
            alt="Logo Misdinar SPMR Purbowardayan"
            class="w-full h-full object-contain rounded-full"
          />
        </div>
        <div class="flex flex-col">
          <div class="flex items-center gap-1.5">
            <span class="font-headline font-bold text-base sm:text-lg text-primary tracking-tight">
              SiDinar Regina
            </span>
            <span class="bg-secondary text-on-secondary text-[10px] font-bold px-1.5 py-0.5 rounded-full shadow-xs leading-none">
              SPMR
            </span>
          </div>
          <span class="font-sans text-[11px] text-on-surface-variant hidden sm:inline">
            Misdinar Paroki Purbowardayan
          </span>
        </div>
      </a>

      <!-- Desktop Navigation Links -->
      <?php if (is_logged_in()): ?>
      <nav class="hidden md:flex items-center gap-1">
        <?php if ($is_pengurus): ?>
          <a href="<?= $base ?>/pengurus/index.php" class="px-3 py-2 rounded-xl text-xs font-semibold transition-colors flex items-center gap-1.5 <?= ($current_dir === 'pengurus' && $current_script === 'index.php') ? 'bg-primary text-on-primary shadow-xs' : 'text-on-surface-variant hover:bg-surface-container hover:text-primary' ?>">
            <span class="material-symbols-outlined text-[18px]">dashboard</span>
            <span>Pusat Kendali</span>
          </a>
          <a href="<?= $base ?>/pengurus/anggota.php" class="px-3 py-2 rounded-xl text-xs font-semibold transition-colors flex items-center gap-1.5 <?= ($current_script === 'anggota.php') ? 'bg-primary text-on-primary shadow-xs' : 'text-on-surface-variant hover:bg-surface-container hover:text-primary' ?>">
            <span class="material-symbols-outlined text-[18px]">how_to_reg</span>
            <span>Approval Anggota</span>
          </a>
          <a href="<?= $base ?>/pengurus/jadwal.php" class="px-3 py-2 rounded-xl text-xs font-semibold transition-colors flex items-center gap-1.5 <?= ($current_script === 'jadwal.php') ? 'bg-primary text-on-primary shadow-xs' : 'text-on-surface-variant hover:bg-surface-container hover:text-primary' ?>">
            <span class="material-symbols-outlined text-[18px]">calendar_month</span>
            <span>Kelola Jadwal</span>
          </a>
          <a href="<?= $base ?>/pengurus/presensi.php" class="px-3 py-2 rounded-xl text-xs font-semibold transition-colors flex items-center gap-1.5 <?= ($current_script === 'presensi.php') ? 'bg-primary text-on-primary shadow-xs' : 'text-on-surface-variant hover:bg-surface-container hover:text-primary' ?>">
            <span class="material-symbols-outlined text-[18px]">qr_code_scanner</span>
            <span>Monitor Presensi</span>
          </a>
          <a href="<?= $base ?>/pengurus/warta.php" class="px-3 py-2 rounded-xl text-xs font-semibold transition-colors flex items-center gap-1.5 <?= ($current_script === 'warta.php') ? 'bg-primary text-on-primary shadow-xs' : 'text-on-surface-variant hover:bg-surface-container hover:text-primary' ?>">
            <span class="material-symbols-outlined text-[18px]">campaign</span>
            <span>Pengumuman</span>
          </a>
        <?php else: ?>
          <a href="<?= $base ?>/anggota/index.php" class="px-3 py-2 rounded-xl text-xs font-semibold transition-colors flex items-center gap-1.5 <?= ($current_dir === 'anggota' && $current_script === 'index.php') ? 'bg-primary text-on-primary shadow-xs' : 'text-on-surface-variant hover:bg-surface-container hover:text-primary' ?>">
            <span class="material-symbols-outlined text-[18px]">event_available</span>
            <span>Jadwal Misa</span>
          </a>
          <a href="<?= $base ?>/anggota/riwayat.php" class="px-3 py-2 rounded-xl text-xs font-semibold transition-colors flex items-center gap-1.5 <?= ($current_script === 'riwayat.php') ? 'bg-primary text-on-primary shadow-xs' : 'text-on-surface-variant hover:bg-surface-container hover:text-primary' ?>">
            <span class="material-symbols-outlined text-[18px]">history</span>
            <span>Riwayat Tugas</span>
          </a>
          <a href="<?= $base ?>/anggota/profil.php" class="px-3 py-2 rounded-xl text-xs font-semibold transition-colors flex items-center gap-1.5 <?= ($current_script === 'profil.php') ? 'bg-primary text-on-primary shadow-xs' : 'text-on-surface-variant hover:bg-surface-container hover:text-primary' ?>">
            <span class="material-symbols-outlined text-[18px]">person</span>
            <span>Profil Saya</span>
          </a>
        <?php endif; ?>
      </nav>
      <?php endif; ?>

      <!-- Right Utility Bar: WIB Clock, Realtime Badge, User Badge, Theme & Logout -->
      <div class="flex items-center gap-2 sm:gap-3">
        <!-- Live WIB Clock -->
        <div class="hidden lg:flex flex-col text-right pr-1">
          <span id="live-wib-clock" class="text-xs font-mono font-semibold text-primary">
            --:--:-- WIB
          </span>
          <span class="text-[10px] text-on-surface-variant font-medium">Waktu Sakristi</span>
        </div>

        <!-- Theme Toggle -->
        <button
          type="button"
          onclick="toggleTheme()"
          id="btn-theme-toggle"
          class="w-9 h-9 rounded-xl flex items-center justify-center bg-surface-container-high hover:bg-surface-container-highest text-on-surface transition-colors"
          title="Ubah Tema (Terang / Gelap)"
        >
          <span class="material-symbols-outlined text-lg">dark_mode</span>
        </button>

        <?php if (is_logged_in()): ?>
        <!-- Notification Bell -->
        <div class="relative">
          <button
            type="button"
            onclick="openModal('modal-notifications')"
            class="w-9 h-9 rounded-xl flex items-center justify-center bg-surface-container-high hover:bg-surface-container-highest text-on-surface transition-colors relative"
            title="Pusat Notifikasi & Pengingat"
          >
            <span class="material-symbols-outlined text-lg text-primary">notifications</span>
            <span id="nav-unread-count" class="absolute -top-1 -right-1 px-1.5 py-0.5 rounded-full text-[9px] font-bold bg-error text-white hidden">0</span>
          </button>
        </div>

        <!-- User Profile Pill -->
        <div class="hidden sm:flex items-center gap-2 pl-1">
          <div class="flex flex-col text-right">
            <span class="text-xs font-bold font-headline text-on-surface leading-tight">
              <?= htmlspecialchars($current_user['nama_panggilan'] ?: $current_user['nama_lengkap']) ?>
            </span>
            <span class="text-[10px] font-semibold text-secondary uppercase tracking-wider">
              <?= $is_pengurus ? 'Pengurus Sakristi' : 'Misdinar Aktif' ?>
            </span>
          </div>
          <div class="w-9 h-9 rounded-full bg-primary-fixed text-primary font-bold text-xs flex items-center justify-center border border-primary-fixed-dim">
            <?= strtoupper(substr($current_user['nama_panggilan'] ?: $current_user['nama_lengkap'], 0, 2)) ?>
          </div>
        </div>

        <!-- Logout Button -->
        <a
          href="<?= $base ?>/logout.php"
          onclick="return confirm('Yakin ingin keluar dari sistem SiDinar Regina?')"
          class="hidden sm:inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold bg-surface-container-high hover:bg-error-container hover:text-error text-on-surface-variant transition-colors"
          title="Keluar Akun"
        >
          <span class="material-symbols-outlined text-base">logout</span>
          <span>Keluar</span>
        </a>

        <!-- Mobile Hamburger Button -->
        <button
          type="button"
          id="btn-mobile-menu"
          onclick="toggleMobileMenu()"
          class="md:hidden w-9 h-9 rounded-xl flex items-center justify-center bg-surface-container-high text-on-surface"
        >
          <span class="material-symbols-outlined text-xl">menu</span>
        </button>
        <?php else: ?>
        <a href="<?= $base ?>/login.php" class="px-4 py-2 rounded-xl bg-primary text-on-primary hover:bg-primary-container text-xs font-headline font-bold transition-all shadow-xs">
          Masuk
        </a>
        <a href="<?= $base ?>/register.php" class="hidden sm:inline-flex px-4 py-2 rounded-xl bg-surface-container-high hover:bg-surface-container-highest text-on-surface text-xs font-semibold transition-colors">
          Daftar
        </a>
        <?php endif; ?>
      </div>

    </div>

    <!-- Mobile Collapsible Menu -->
    <?php if (is_logged_in()): ?>
    <div id="mobile-menu-drawer" class="hidden md:hidden py-4 border-t border-surface-container flex flex-col gap-1.5 animate-fadeIn">
      <?php if ($is_pengurus): ?>
        <a href="<?= $base ?>/pengurus/index.php" class="px-3.5 py-2.5 rounded-xl text-xs font-semibold flex items-center gap-2.5 <?= ($current_dir === 'pengurus' && $current_script === 'index.php') ? 'bg-primary text-on-primary' : 'text-on-surface-variant hover:bg-surface-container' ?>">
          <span class="material-symbols-outlined text-lg">dashboard</span>
          <span>Pusat Kendali</span>
        </a>
        <a href="<?= $base ?>/pengurus/anggota.php" class="px-3.5 py-2.5 rounded-xl text-xs font-semibold flex items-center gap-2.5 <?= ($current_script === 'anggota.php') ? 'bg-primary text-on-primary' : 'text-on-surface-variant hover:bg-surface-container' ?>">
          <span class="material-symbols-outlined text-lg">how_to_reg</span>
          <span>Approval Anggota</span>
        </a>
        <a href="<?= $base ?>/pengurus/jadwal.php" class="px-3.5 py-2.5 rounded-xl text-xs font-semibold flex items-center gap-2.5 <?= ($current_script === 'jadwal.php') ? 'bg-primary text-on-primary' : 'text-on-surface-variant hover:bg-surface-container' ?>">
          <span class="material-symbols-outlined text-lg">calendar_month</span>
          <span>Kelola Jadwal</span>
        </a>
        <a href="<?= $base ?>/pengurus/presensi.php" class="px-3.5 py-2.5 rounded-xl text-xs font-semibold flex items-center gap-2.5 <?= ($current_script === 'presensi.php') ? 'bg-primary text-on-primary' : 'text-on-surface-variant hover:bg-surface-container' ?>">
          <span class="material-symbols-outlined text-lg">qr_code_scanner</span>
          <span>Monitor Presensi</span>
        </a>
        <a href="<?= $base ?>/pengurus/warta.php" class="px-3.5 py-2.5 rounded-xl text-xs font-semibold flex items-center gap-2.5 <?= ($current_script === 'warta.php') ? 'bg-primary text-on-primary' : 'text-on-surface-variant hover:bg-surface-container' ?>">
          <span class="material-symbols-outlined text-lg">campaign</span>
          <span>Pengumuman</span>
        </a>
      <?php else: ?>
        <a href="<?= $base ?>/anggota/index.php" class="px-3.5 py-2.5 rounded-xl text-xs font-semibold flex items-center gap-2.5 <?= ($current_dir === 'anggota' && $current_script === 'index.php') ? 'bg-primary text-on-primary' : 'text-on-surface-variant hover:bg-surface-container' ?>">
          <span class="material-symbols-outlined text-lg">event_available</span>
          <span>Jadwal Misa</span>
        </a>
        <a href="<?= $base ?>/anggota/riwayat.php" class="px-3.5 py-2.5 rounded-xl text-xs font-semibold flex items-center gap-2.5 <?= ($current_script === 'riwayat.php') ? 'bg-primary text-on-primary' : 'text-on-surface-variant hover:bg-surface-container' ?>">
          <span class="material-symbols-outlined text-lg">history</span>
          <span>Riwayat Tugas</span>
        </a>
        <a href="<?= $base ?>/anggota/profil.php" class="px-3.5 py-2.5 rounded-xl text-xs font-semibold flex items-center gap-2.5 <?= ($current_script === 'profil.php') ? 'bg-primary text-on-primary' : 'text-on-surface-variant hover:bg-surface-container' ?>">
          <span class="material-symbols-outlined text-lg">person</span>
          <span>Profil Saya</span>
        </a>
      <?php endif; ?>

      <div class="pt-2 border-t border-surface-container flex items-center justify-between px-3">
        <span class="text-xs font-semibold text-on-surface"><?= htmlspecialchars($current_user['nama_lengkap']) ?></span>
        <a href="<?= $base ?>/logout.php" onclick="return confirm('Keluar dari sistem?')" class="text-xs text-error font-bold flex items-center gap-1">
          <span class="material-symbols-outlined text-sm">logout</span> Keluar
        </a>
      </div>
    </div>
    <?php endif; ?>
  </div>
</header>

<!-- Main Body Container matching Next.js Layout -->
<main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8">
  <?php if ($flash): ?>
    <div class="mb-6 p-4 rounded-2xl flex items-center gap-3 border shadow-xs <?= $flash['type'] === 'success' ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : ($flash['type'] === 'error' ? 'bg-red-50 text-red-800 border-red-200' : 'bg-blue-50 text-blue-800 border-blue-200') ?>">
      <span class="material-symbols-outlined text-xl">
        <?= $flash['type'] === 'success' ? 'check_circle' : ($flash['type'] === 'error' ? 'error' : 'info') ?>
      </span>
      <div class="text-xs font-medium leading-relaxed"><?= htmlspecialchars($flash['message']) ?></div>
    </div>
  <?php endif; ?>
