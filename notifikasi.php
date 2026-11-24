<?php
/**
 * Halaman Pusat Notifikasi & Warta Sakristi - SiDinar Regina
 * Menampilkan seluruh warta pengumuman, pengingat tugas misa, dan audio chime
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$page_title = 'Pusat Notifikasi & Warta Sakristi';
require_once __DIR__ . '/includes/header.php';

$pdo = get_db();
$today = date('Y-m-d');
$user = current_user();

$duties = [];
if ($user) {
    if ($user['role'] === 'pengurus') {
        $stmt = $pdo->prepare("
            SELECT b.*, s.judul_misa, s.tanggal_misa, s.jam_mulai, s.kategori,
                   p.nama_lengkap, p.nama_panggilan, p.no_telepon
            FROM bookings b
            JOIN mass_schedules s ON b.schedule_id = s.id
            JOIN profiles p ON b.user_id = p.id
            WHERE s.tanggal_misa >= ?
            ORDER BY s.tanggal_misa ASC, s.jam_mulai ASC LIMIT 20
        ");
        $stmt->execute([$today]);
        $duties = $stmt->fetchAll();
    } else {
        $stmt = $pdo->prepare("
            SELECT b.*, s.judul_misa, s.tanggal_misa, s.jam_mulai, s.kategori, s.qr_token
            FROM bookings b
            JOIN mass_schedules s ON b.schedule_id = s.id
            WHERE b.user_id = ? AND s.tanggal_misa >= ?
            ORDER BY s.tanggal_misa ASC, s.jam_mulai ASC
        ");
        $stmt->execute([$user['id'], $today]);
        $duties = $stmt->fetchAll();
    }
}

// Ambil semua warta pengumuman
$wartaStmt = $pdo->query("
    SELECT a.*, p.nama_lengkap as nama_pembuat
    FROM announcements a
    LEFT JOIN profiles p ON a.created_by = p.id
    ORDER BY a.is_pinned DESC, a.created_at DESC
");
$announcements = $wartaStmt->fetchAll();
?>

<div class="flex flex-col gap-8 pb-10">

  <!-- Header Banner -->
  <div class="p-6 sm:p-8 rounded-3xl bg-surface-container-lowest border border-surface-container shadow-xs flex flex-col md:flex-row md:items-center justify-between gap-6">
    <div class="flex items-start gap-4">
      <div class="w-14 h-14 rounded-2xl bg-primary-fixed flex items-center justify-center text-primary shrink-0 shadow-xs">
        <span class="material-symbols-outlined text-3xl">notifications_active</span>
      </div>
      <div class="flex flex-col gap-1">
        <div class="flex items-center gap-2">
          <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase bg-primary-fixed text-primary">
            Sakristi Alert System
          </span>
          <span class="text-xs text-on-surface-variant">• Terkoneksi Real-time</span>
        </div>
        <h1 class="font-headline font-extrabold text-2xl sm:text-3xl text-primary tracking-tight">
          Pusat Notifikasi &amp; Pengingat
        </h1>
        <p class="text-xs sm:text-sm text-on-surface-variant max-w-xl leading-relaxed">
          Pusat pemantauan warta sakristi terkini, pengingat tugas misa mendatang, serta uji audio lonceng liturgis otomatis.
        </p>
      </div>
    </div>

    <!-- Quick Action Buttons -->
    <div class="flex flex-wrap items-center gap-2.5 self-start md:self-center">
      <button
        type="button"
        onclick="showToastBanner('🔔 Pengujian Lonceng Sakristi', 'Lonceng altar digital berbunyi normal! Sistem notifikasi Anda aktif.');"
        class="px-4 py-2.5 rounded-xl bg-primary text-on-primary hover:bg-primary-container text-xs font-headline font-bold transition-all shadow-xs flex items-center gap-2"
      >
        <span class="material-symbols-outlined text-base">volume_up</span>
        <span>Uji Bunyi Lonceng</span>
      </button>

      <?php if (is_pengurus()): ?>
      <a
        href="<?= $base ?>/pengurus/warta.php"
        class="px-4 py-2.5 rounded-xl bg-surface-container-high hover:bg-surface-container-highest text-on-surface text-xs font-semibold transition-colors flex items-center gap-1.5"
      >
        <span class="material-symbols-outlined text-base">campaign</span>
        <span>Kelola Pengumuman</span>
      </a>
      <?php endif; ?>
    </div>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

    <!-- Kolom Kiri: Pengingat Jadwal Tugas Liturgi (2 Kolom di Layar Lebar) -->
    <div class="lg:col-span-2 flex flex-col gap-5">
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-2.5">
          <div class="w-8 h-8 rounded-xl bg-primary/10 flex items-center justify-center text-primary">
            <span class="material-symbols-outlined text-xl">event_upcoming</span>
          </div>
          <div>
            <h2 class="font-headline font-bold text-lg text-primary">
              <?= is_pengurus() ? 'Jadwal Tugas Petugas Altar Mendatang' : 'Pengingat Tugas Altar Anda' ?>
            </h2>
            <p class="text-[11px] text-on-surface-variant">
              <?= is_pengurus() ? 'Daftar misdinar yang terjadwal melayani perayaan Ekaristi' : 'Pastikan hadir di sakristi paling lambat 30 menit sebelum perayaan' ?>
            </p>
          </div>
        </div>
        <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-surface-container-high text-on-surface">
          <?= count($duties) ?> Tugas Terdaftar
        </span>
      </div>

      <?php if (!is_logged_in()): ?>
        <div class="p-8 rounded-3xl bg-surface-container-lowest border border-surface-container text-center flex flex-col items-center gap-3">
          <span class="material-symbols-outlined text-4xl text-primary opacity-60">lock</span>
          <h3 class="font-headline font-bold text-base text-on-surface">Masuk untuk Melihat Jadwal Tugas</h3>
          <p class="text-xs text-on-surface-variant max-w-md">
            Silakan masuk ke akun misdinar atau pengurus sakristi Anda untuk melihat pengingat penugasan personal secara real-time.
          </p>
          <a href="<?= $base ?>/login.php" class="mt-2 px-5 py-2.5 rounded-xl bg-primary text-on-primary text-xs font-bold shadow-xs">
            Masuk ke Akun
          </a>
        </div>
      <?php elseif (empty($duties)): ?>
        <div class="p-8 rounded-3xl bg-surface-container-lowest border border-surface-container text-center flex flex-col items-center gap-3">
          <span class="material-symbols-outlined text-4xl text-outline">calendar_today</span>
          <h3 class="font-headline font-bold text-sm text-on-surface">Belum ada tugas terjadwal</h3>
          <p class="text-xs text-on-surface-variant">
            <?= is_pengurus() ? 'Belum ada pendaftaran tugas untuk misa yang akan datang.' : 'Anda belum memiliki jadwal penugasan altar aktif saat ini.' ?>
          </p>
          <?php if (!is_pengurus()): ?>
            <a href="<?= $base ?>/anggota/index.php" class="mt-2 px-4 py-2 rounded-xl bg-primary text-on-primary text-xs font-semibold">
              Pilih Jadwal Misa
            </a>
          <?php endif; ?>
        </div>
      <?php else: ?>
        <div class="flex flex-col gap-3">
          <?php foreach ($duties as $d): ?>
            <div class="p-4 sm:p-5 rounded-2xl bg-surface-container-lowest border border-surface-container hover:border-primary/40 shadow-xs transition-all flex flex-col sm:flex-row sm:items-center justify-between gap-4">
              <div class="flex flex-col gap-1.5">
                <div class="flex items-center gap-2">
                  <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase bg-secondary-fixed/50 text-secondary">
                    Misa <?= ucfirst($d['kategori']) ?>
                  </span>
                  <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase bg-primary-fixed text-primary">
                    Posisi: <?= htmlspecialchars($d['posisi_tugas']) ?>
                  </span>
                  <?php if (!empty($d['wa_reminder_sent'])): ?>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800">
                      ✓ WA Terkirim
                    </span>
                  <?php endif; ?>
                </div>

                <h3 class="font-headline font-bold text-base text-primary">
                  <?= htmlspecialchars($d['judul_misa']) ?>
                </h3>

                <div class="flex flex-wrap items-center gap-3 text-xs text-on-surface-variant">
                  <span class="flex items-center gap-1 font-medium">
                    📅 <?= format_indo_date($d['tanggal_misa']) ?>
                  </span>
                  <span>•</span>
                  <span class="flex items-center gap-1 font-bold text-on-surface">
                    ⏰ Pukul <?= substr($d['jam_mulai'], 0, 5) ?> WIB
                  </span>
                  <?php if (is_pengurus()): ?>
                    <span>•</span>
                    <span class="font-semibold text-primary">
                      Petugas: <?= htmlspecialchars($d['nama_panggilan'] ?: $d['nama_lengkap']) ?>
                    </span>
                  <?php endif; ?>
                </div>
              </div>

              <div class="flex items-center gap-2 shrink-0">
                <?php if (is_pengurus()): ?>
                  <a
                    href="<?= $base ?>/pengurus/jadwal.php?schedule_id=<?= $d['schedule_id'] ?>"
                    class="px-3.5 py-2 rounded-xl text-xs font-semibold bg-surface-container-high hover:bg-surface-container-highest text-on-surface transition-colors"
                  >
                    Buka Roster
                  </a>
                <?php else: ?>
                  <a
                    href="<?= $base ?>/anggota/index.php"
                    class="px-3.5 py-2 rounded-xl text-xs font-semibold bg-primary text-on-primary hover:bg-primary-container transition-colors shadow-xs"
                  >
                    Presensi &amp; QR
                  </a>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- Kolom Kanan: Warta Sakristi & Pengumuman -->
    <div class="flex flex-col gap-5">
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-2.5">
          <div class="w-8 h-8 rounded-xl bg-secondary/10 flex items-center justify-center text-secondary">
            <span class="material-symbols-outlined text-xl">campaign</span>
          </div>
          <div>
            <h2 class="font-headline font-bold text-lg text-secondary">
              Warta Sakristi
            </h2>
            <p class="text-[11px] text-on-surface-variant">Pengumuman resmi liturgi &amp; latihan</p>
          </div>
        </div>
        <span class="px-2 py-0.5 rounded-full text-xs font-bold bg-secondary-fixed/40 text-secondary">
          <?= count($announcements) ?> Warta
        </span>
      </div>

      <?php if (empty($announcements)): ?>
        <div class="p-8 rounded-3xl bg-surface-container-lowest border border-surface-container text-center flex flex-col items-center gap-3">
          <span class="material-symbols-outlined text-4xl text-outline">campaign</span>
          <h3 class="font-headline font-bold text-sm text-on-surface">Belum ada warta sakristi</h3>
          <p class="text-xs text-on-surface-variant">Belum ada pengumuman yang disematkan saat ini.</p>
        </div>
      <?php else: ?>
        <div class="flex flex-col gap-3">
          <?php foreach ($announcements as $a): ?>
            <div class="p-4 sm:p-5 rounded-2xl bg-surface-container-lowest border <?= $a['is_pinned'] ? 'border-primary-fixed-dim ring-1 ring-primary/20 bg-primary-fixed/10' : 'border-surface-container' ?> shadow-xs flex flex-col gap-2">
              <div class="flex items-center justify-between gap-2">
                <div class="flex items-center gap-1.5">
                  <?php if ($a['is_pinned']): ?>
                    <span class="text-xs">📌</span>
                    <span class="text-[10px] font-bold text-primary uppercase tracking-wider">Disematkan</span>
                  <?php endif; ?>
                </div>
                <span class="text-[10px] text-on-surface-variant">
                  <?= $a['created_at'] ? date('d M Y', strtotime($a['created_at'])) : '' ?>
                </span>
              </div>

              <h4 class="font-headline font-bold text-sm text-on-surface">
                <?= htmlspecialchars($a['judul']) ?>
              </h4>

              <p class="text-xs text-on-surface-variant leading-relaxed whitespace-pre-line">
                <?= htmlspecialchars($a['isi']) ?>
              </p>

              <?php if (!empty($a['nama_pembuat'])): ?>
                <div class="pt-2 border-t border-surface-container text-[10px] text-on-surface-variant flex items-center justify-between">
                  <span>Oleh: <?= htmlspecialchars($a['nama_pembuat']) ?></span>
                  <span class="text-primary font-semibold">Pengurus Sakristi</span>
                </div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
