<?php
/**
 * Portal Utama (Landing Page) - SiDinar Regina
 * Desain Tampilan Identik dengan Web Next.js (Material 3 Liturgical Marian Design)
 */
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    header('Location: ' . (is_pengurus() ? 'pengurus/index.php' : 'anggota/index.php'));
    exit;
}

$page_title = 'Beranda Utama';
require_once __DIR__ . '/includes/header.php';
?>

<div class="flex flex-col gap-10 py-4 sm:py-8">
  <!-- Hero Section with Marian Gradient -->
  <section class="relative overflow-hidden rounded-3xl marian-gradient text-white p-8 sm:p-14 shadow-xl border border-primary-container text-center flex flex-col items-center gap-6">
    <!-- Ambient glow inside hero -->
    <div class="absolute -top-24 -right-24 w-80 h-80 rounded-full bg-white/10 blur-3xl pointer-events-none"></div>
    <div class="absolute -bottom-24 -left-24 w-72 h-72 rounded-full bg-secondary-container/20 blur-3xl pointer-events-none"></div>

    <!-- Official Logo -->
    <div class="w-24 h-24 sm:w-28 sm:h-28 rounded-full bg-white shadow-xl p-2 flex items-center justify-center border-2 border-primary-fixed/30 relative z-10">
      <img
        src="<?= $base ?>/assets/img/logo.png"
        alt="Logo Misdinar SPMR"
        class="w-full h-full object-contain rounded-full"
      />
    </div>

    <div class="flex flex-col items-center gap-2 relative z-10 max-w-2xl">
      <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-white/15 backdrop-blur-md text-[11px] font-bold tracking-wider uppercase text-primary-fixed border border-white/20">
        <span class="material-symbols-outlined text-sm" style="font-variation-settings: 'FILL' 1">church</span>
        Paroki Santa Perawan Maria Regina Purbowardayan
      </span>
      <h1 class="font-headline font-extrabold text-3xl sm:text-4xl tracking-tight leading-tight">
        Sistem Informasi &amp; Presensi Digital Putra-Putri Altar
      </h1>
      <p class="text-xs sm:text-sm text-primary-fixed opacity-95 max-w-xl mx-auto leading-relaxed">
        Platform sakristi cerdas untuk manajemen jadwal misa, batas toleransi kehadiran terukur, presensi QR Code, dan notifikasi pengingat H-1 tugas liturgi.
      </p>
      <p class="text-xs text-secondary-container font-serif italic mt-1">
        &ldquo;Misdinar: The Best Choice For Life&rdquo;
      </p>
    </div>

    <!-- CTA Buttons -->
    <div class="flex flex-wrap items-center justify-center gap-3 relative z-10 pt-2">
      <a
        href="<?= $base ?>/login.php"
        class="px-6 py-3 rounded-2xl bg-white text-primary hover:bg-surface-container font-headline font-bold text-xs shadow-md transition-all flex items-center gap-2"
      >
        <span class="material-symbols-outlined text-base">login</span>
        <span>Masuk ke Sakristi</span>
      </a>
      <a
        href="<?= $base ?>/register.php"
        class="px-6 py-3 rounded-2xl bg-white/20 hover:bg-white/30 text-white border border-white/30 font-headline font-bold text-xs backdrop-blur-sm transition-all flex items-center gap-2"
      >
        <span class="material-symbols-outlined text-base">app_registration</span>
        <span>Daftar Calon Anggota</span>
      </a>
    </div>
  </section>

  <!-- 3 Pillar Feature Cards identik Next.js -->
  <section class="grid grid-cols-1 md:grid-cols-3 gap-6">
    <div class="p-6 rounded-3xl bg-surface-container-lowest border border-surface-container shadow-xs flex flex-col gap-3">
      <div class="w-12 h-12 rounded-2xl bg-primary/10 flex items-center justify-center text-primary">
        <span class="material-symbols-outlined text-2xl">calendar_month</span>
      </div>
      <h3 class="font-headline font-bold text-base text-primary">Penjadwalan Liturgi</h3>
      <p class="text-xs text-on-surface-variant leading-relaxed">
        Pemesanan slot tugas untuk Misa Harian &amp; Mingguan dengan alokasi peran liturgi rapi (Thurifer, Navis, Salib, Lilin, Altar).
      </p>
    </div>

    <div class="p-6 rounded-3xl bg-surface-container-lowest border border-surface-container shadow-xs flex flex-col gap-3">
      <div class="w-12 h-12 rounded-2xl bg-secondary/10 flex items-center justify-center text-secondary">
        <span class="material-symbols-outlined text-2xl">qr_code_scanner</span>
      </div>
      <h3 class="font-headline font-bold text-base text-secondary">Presensi &amp; Toleransi 45m</h3>
      <p class="text-xs text-on-surface-variant leading-relaxed">
        Presensi tepat waktu (H-30/H-20) dan auto-alpa cerdas jika telah melewati batas toleransi 45 menit pasca perayaan Ekaristi.
      </p>
    </div>

    <div class="p-6 rounded-3xl bg-surface-container-lowest border border-surface-container shadow-xs flex flex-col gap-3">
      <div class="w-12 h-12 rounded-2xl bg-emerald-500/10 flex items-center justify-center text-emerald-700">
        <span class="material-symbols-outlined text-2xl">notifications_active</span>
      </div>
      <h3 class="font-headline font-bold text-base text-emerald-700">Warta Sakristi &amp; Notifikasi</h3>
      <p class="text-xs text-on-surface-variant leading-relaxed">
        Pengingat otomatis H-1 jadwal pelayanan serta warta pengumuman gladi bersih dan tata tertib sakristi terpusat.
      </p>
    </div>
  </section>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
