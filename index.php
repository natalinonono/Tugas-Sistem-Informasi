<?php
/**
 * Portal Utama (Landing Page / Auto-Redirect)
 * SiDinar Regina - Sistem Informasi & Presensi Putra-Putri Altar
 */
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    header('Location: ' . (is_pengurus() ? 'pengurus/index.php' : 'anggota/index.php'));
    exit;
}

$page_title = 'Beranda Utama';
require_once __DIR__ . '/includes/header.php';
?>

<div style="max-width: 900px; margin: 2rem auto;">
  <!-- Hero Section -->
  <div class="card-hero" style="text-align: center; padding: 3.5rem 1.5rem;">
    <div class="brand-logo" style="width: 64px; height: 64px; font-size: 2.2rem; margin: 0 auto 1rem;">⛪</div>
    <h1 style="font-size: 2.2rem; line-height: 1.15; margin-bottom: 0.75rem;">
      Sistem Informasi &amp; Presensi Digital<br>Putra-Putri Altar SPMR Purbowardayan
    </h1>
    <p style="font-size: 0.95rem; opacity: 0.92; max-width: 640px; margin: 0 auto 1.75rem;">
      Platform pelayanan sakristi modern untuk penjadwalan misa kudus, alokasi peran petugas altar, presensi digital QR Code, dan warta sakristi real-time.
    </p>

    <div style="display: flex; gap: 0.75rem; justify-content: center; flex-wrap: wrap;">
      <a href="login.php" class="btn btn-secondary" style="padding: 0.75rem 1.75rem; font-size: 0.92rem; font-weight: 700; background: #FFF; color: var(--primary);">
        Masuk Akun Petugas
      </a>
      <a href="register.php" class="btn btn-primary" style="padding: 0.75rem 1.75rem; font-size: 0.92rem; font-weight: 700; background: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.4);">
        Daftar Anggota Baru
      </a>
    </div>
  </div>

  <!-- 3 Pilar Fitur Unggulan -->
  <div class="grid grid-cols-3" style="margin-top: 2rem;">
    <div class="card" style="text-align: center; padding: 1.5rem;">
      <div style="font-size: 2rem; margin-bottom: 0.5rem;">📅</div>
      <h3 style="font-size: 1rem; color: var(--primary); margin-bottom: 0.35rem;">Penjadwalan Otomatis</h3>
      <p style="font-size: 0.78rem; color: var(--text-muted);">
        Penataan kuota misdinar untuk Misa Harian &amp; Misa Mingguan dengan alokasi peran liturgi lengkap.
      </p>
    </div>

    <div class="card" style="text-align: center; padding: 1.5rem;">
      <div style="font-size: 2rem; margin-bottom: 0.5rem;">📱</div>
      <h3 style="font-size: 1rem; color: var(--primary); margin-bottom: 0.35rem;">Presensi QR Code</h3>
      <p style="font-size: 0.78rem; color: var(--text-muted);">
        Absensi mandiri di sakristi dengan pemindaian QR Code dan klasifikasi tepat waktu vs terlambat otomatis.
      </p>
    </div>

    <div class="card" style="text-align: center; padding: 1.5rem;">
      <div style="font-size: 2rem; margin-bottom: 0.5rem;">🔔</div>
      <h3 style="font-size: 1rem; color: var(--primary); margin-bottom: 0.35rem;">Pengingat Real-Time</h3>
      <p style="font-size: 0.78rem; color: var(--text-muted);">
        Notifikasi lonceng altar &amp; pesan pengingat resmi WhatsApp otomatis ke setiap anggota yang bertugas.
      </p>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
