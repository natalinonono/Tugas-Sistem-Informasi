<?php
/**
 * Dashboard Utama Pengurus Sakristi - SiDinar Regina
 * Tampilan & Desain Identik 100% dengan Web Next.js (/pengurus)
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_pengurus();

$pdo = get_db();
auto_update_expired_attendance($pdo);

$today = date('Y-m-d');

// 1. Statistik
$countAnggota = (int)$pdo->query("SELECT COUNT(*) FROM profiles WHERE role = 'anggota' AND status = 'aktif'")->fetchColumn();
$countPending = (int)$pdo->query("SELECT COUNT(*) FROM profiles WHERE status = 'pending'")->fetchColumn();
$countSchedules = (int)$pdo->query("SELECT COUNT(*) FROM mass_schedules WHERE tanggal_misa >= '$today'")->fetchColumn();

// Total Tugas Terdaftar Pekan Ini
$current_week = (int)date('W');
$current_year = (int)date('Y');
$stmtTugas = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE minggu_ke = ? AND tahun = ?");
$stmtTugas->execute([$current_week, $current_year]);
$totalTugasPekanIni = (int)$stmtTugas->fetchColumn();

// Ketepatan Waktu %
$stmtKehadiran = $pdo->prepare("
    SELECT 
        SUM(CASE WHEN status_kehadiran = 'hadir' THEN 1 ELSE 0 END) AS tepat_waktu,
        SUM(CASE WHEN status_kehadiran IN ('hadir', 'terlambat') THEN 1 ELSE 0 END) AS total_hadir
    FROM bookings
    WHERE minggu_ke = ? AND tahun = ?
");
$stmtKehadiran->execute([$current_week, $current_year]);
$statsKehadiran = $stmtKehadiran->fetch();
$totalHadir = (int)($statsKehadiran['total_hadir'] ?? 0);
$tepatWaktu = (int)($statsKehadiran['tepat_waktu'] ?? 0);
$onTimeRate = $totalHadir > 0 ? round(($tepatWaktu / $totalHadir) * 100) : 100;

// 2. Daftar Pending Members
$stmtPendingList = $pdo->query("SELECT * FROM profiles WHERE status = 'pending' ORDER BY created_at DESC LIMIT 5");
$pendingMembers = $stmtPendingList->fetchAll();

// 3. Jadwal Misa Mendatang
$upcomingStmt = $pdo->prepare("
    SELECT s.*, 
           (SELECT COUNT(*) FROM bookings WHERE schedule_id = s.id) AS terisi
    FROM mass_schedules s 
    WHERE s.tanggal_misa >= ? 
    ORDER BY s.tanggal_misa ASC, s.jam_mulai ASC 
    LIMIT 6
");
$upcomingStmt->execute([$today]);
$upcomingSchedules = $upcomingStmt->fetchAll();

// 4. Live Presensi Terkini
$presensiStmt = $pdo->prepare("
    SELECT b.*, s.judul_misa, s.jam_mulai, s.warna_liturgi, p.nama_lengkap, p.nama_panggilan, p.asal_wilayah_teks
    FROM bookings b
    JOIN mass_schedules s ON b.schedule_id = s.id
    JOIN profiles p ON b.user_id = p.id
    WHERE s.tanggal_misa = ?
    ORDER BY b.waktu_checkin DESC, s.jam_mulai ASC
    LIMIT 6
");
$presensiStmt->execute([$today]);
$recentPresensi = $presensiStmt->fetchAll();

$page_title = 'Pusat Kendali Pengurus';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex flex-col gap-8">
  <!-- 1. Upper Command Deck Header identik Next.js -->
  <section class="p-6 sm:p-8 rounded-3xl bg-surface-container-lowest border border-surface-container shadow-xs flex flex-col gap-6 relative overflow-hidden">
    <!-- Ambient Accent Blur -->
    <div class="ambient-accent-top"></div>

    <div class="flex flex-col xl:flex-row xl:items-center justify-between gap-6 relative z-10">
      <div class="flex items-center gap-4">
        <div class="w-14 h-14 rounded-2xl bg-surface-container-low flex items-center justify-center p-1.5 shrink-0 shadow-xs border border-surface-container">
          <span class="material-symbols-outlined text-3xl text-primary" style="font-variation-settings: 'FILL' 1">
            shield_person
          </span>
        </div>
        <div class="flex flex-col">
          <div class="flex items-center gap-2">
            <span class="w-2.5 h-2.5 rounded-full bg-secondary-container"></span>
            <span class="text-xs uppercase font-bold text-secondary tracking-wider">
              Pusat Kendali Pengurus Misdinar
            </span>
          </div>
          <h1 class="font-headline font-bold text-2xl text-primary tracking-tight">
            SiDinar Regina Purbowardayan
          </h1>
          <p class="text-xs text-on-surface-variant">
            Paroki Santa Perawan Maria Regina Purbowardayan • Siklus Pekan ke-<?= $current_week ?>, <?= $current_year ?>
          </p>
        </div>
      </div>

      <!-- Quick Actions Deck -->
      <div class="flex flex-wrap items-center gap-2.5">
        <button
          type="button"
          onclick="triggerAutoGenerate()"
          class="px-4 py-2.5 rounded-xl bg-primary text-on-primary hover:bg-primary-container text-xs font-headline font-bold transition-all shadow-xs flex items-center gap-2 group"
        >
          <span class="material-symbols-outlined text-base">bolt</span>
          <span>Generate Jadwal 1 Bulan</span>
          <span class="hidden sm:inline-flex px-1.5 py-0.5 rounded text-[10px] bg-primary-fixed-dim text-on-primary-fixed font-bold">
            Otomatis
          </span>
        </button>

        <a
          href="presensi.php"
          class="px-4 py-2.5 rounded-xl bg-surface-container-high text-on-surface hover:bg-surface-container-highest text-xs font-semibold transition-colors flex items-center gap-1.5"
        >
          <span class="material-symbols-outlined text-base text-primary">qr_code_scanner</span>
          <span>Scanner Presensi</span>
        </a>

        <a
          href="warta.php"
          class="px-4 py-2.5 rounded-xl bg-surface-container-high text-on-surface hover:bg-surface-container-highest text-xs font-semibold transition-colors flex items-center gap-1.5"
        >
          <span class="material-symbols-outlined text-base text-secondary" style="font-variation-settings: 'FILL' 1">campaign</span>
          <span>Kelola Pengumuman</span>
        </a>

        <div class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-surface-container-low border border-surface-container">
          <span class="text-xs font-medium text-on-surface">Pendaftaran:</span>
          <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-emerald-100 text-emerald-800 text-[11px] font-bold">
            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
            DIBUKA
          </span>
        </div>
      </div>
    </div>

    <!-- Quick KPI Counters Grid identik Next.js -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 pt-2 relative z-10">
      <!-- KPI 1 -->
      <div class="p-4 rounded-2xl bg-surface-container-low border border-surface-container flex items-center gap-3.5">
        <div class="w-12 h-12 rounded-xl bg-primary/10 flex items-center justify-center text-primary shrink-0">
          <span class="material-symbols-outlined text-2xl">groups</span>
        </div>
        <div class="flex flex-col">
          <span class="text-[11px] uppercase tracking-wider text-on-surface-variant font-medium">
            Total Anggota Aktif
          </span>
          <span class="font-headline font-bold text-xl text-primary">
            <?= $countAnggota ?>
          </span>
          <span class="text-[10px] text-secondary font-medium">Lengkap Wilayah 1-7</span>
        </div>
      </div>

      <!-- KPI 2 -->
      <div class="p-4 rounded-2xl bg-surface-container-low border border-surface-container flex items-center gap-3.5">
        <div class="w-12 h-12 rounded-xl bg-primary-fixed flex items-center justify-center text-primary shrink-0">
          <span class="material-symbols-outlined text-2xl">event_available</span>
        </div>
        <div class="flex flex-col">
          <span class="text-[11px] uppercase tracking-wider text-on-surface-variant font-medium">
            Jadwal Misa Aktif
          </span>
          <span class="font-headline font-bold text-xl text-primary">
            <?= $countSchedules ?>
          </span>
          <a href="jadwal.php" class="text-[10px] text-primary font-semibold hover:underline">
            Kelola Jadwal &rarr;
          </a>
        </div>
      </div>

      <!-- KPI 3 -->
      <div class="p-4 rounded-2xl bg-surface-container-low border border-surface-container flex items-center gap-3.5">
        <div class="w-12 h-12 rounded-xl bg-secondary/10 flex items-center justify-center text-secondary shrink-0">
          <span class="material-symbols-outlined text-2xl" style="font-variation-settings: 'FILL' 1">
            church
          </span>
        </div>
        <div class="flex flex-col">
          <span class="text-[11px] uppercase tracking-wider text-on-surface-variant font-medium">
            Tugas Terdaftar
          </span>
          <span class="font-headline font-bold text-xl text-secondary">
            <?= $totalTugasPekanIni ?>
          </span>
          <span class="text-[10px] text-on-surface-variant">Pekan Berjalan</span>
        </div>
      </div>

      <!-- KPI 4 -->
      <div class="p-4 rounded-2xl bg-surface-container-low border border-surface-container flex items-center gap-3.5">
        <div class="w-12 h-12 rounded-xl bg-emerald-500/10 flex items-center justify-center text-emerald-700 shrink-0">
          <span class="material-symbols-outlined text-2xl">verified</span>
        </div>
        <div class="flex flex-col">
          <span class="text-[11px] uppercase tracking-wider text-on-surface-variant font-medium">
            Ketepatan Waktu
          </span>
          <span class="font-headline font-bold text-xl text-emerald-700">
            <?= $onTimeRate ?>%
          </span>
          <span class="text-[10px] text-on-surface-variant">Toleransi s/d +45m</span>
        </div>
      </div>
    </div>
  </section>

  <!-- 2. Pending Members Approval Section (High Priority) -->
  <?php if (!empty($pendingMembers)): ?>
  <section class="p-6 rounded-3xl bg-amber-50/70 border border-amber-200 shadow-xs flex flex-col gap-4">
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-2.5">
        <span class="material-symbols-outlined text-xl text-amber-700">pending_actions</span>
        <h2 class="font-headline font-bold text-base text-amber-900">
          Calon Misdinar Menunggu Verifikasi (<?= count($pendingMembers) ?>)
        </h2>
      </div>
      <a href="anggota.php" class="text-xs font-bold text-amber-800 hover:underline">
        Buka Manajemen Anggota &rarr;
      </a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
      <?php foreach ($pendingMembers as $m): ?>
      <div class="p-4 rounded-2xl bg-white border border-amber-200 shadow-xs flex flex-col justify-between gap-3">
        <div>
          <div class="flex items-center justify-between gap-2">
            <span class="font-bold text-xs text-on-surface"><?= htmlspecialchars($m['nama_lengkap']) ?></span>
            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800">Pending</span>
          </div>
          <div class="text-[11px] text-on-surface-variant mt-1 flex flex-col gap-0.5">
            <span>📱 <?= htmlspecialchars($m['no_telepon']) ?></span>
            <span>📍 <?= htmlspecialchars($m['asal_wilayah_teks']) ?> • Jenjang <?= htmlspecialchars($m['jenjang']) ?></span>
          </div>
        </div>

        <form method="POST" action="anggota.php">
          <input type="hidden" name="action" value="approve_member">
          <input type="hidden" name="member_id" value="<?= $m['id'] ?>">
          <button
            type="submit"
            class="w-full py-2 px-3 rounded-xl bg-primary text-on-primary text-xs font-bold hover:bg-primary-container transition-colors flex items-center justify-center gap-1.5"
          >
            <span class="material-symbols-outlined text-sm">check_circle</span>
            <span>Setujui Sebagai Anggota</span>
          </button>
        </form>
      </div>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <!-- 3. Jadwal Misa & Presensi Terkini Grid -->
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- Kolom Kiri: Jadwal Misa Mendatang -->
    <div class="p-6 rounded-3xl bg-surface-container-lowest border border-surface-container shadow-xs flex flex-col gap-4">
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-2">
          <span class="material-symbols-outlined text-primary text-xl">event_upcoming</span>
          <h2 class="font-headline font-bold text-base text-primary">Jadwal Misa Terdekat</h2>
        </div>
        <a href="jadwal.php" class="text-xs font-bold text-primary hover:underline">
          Lihat Semua &rarr;
        </a>
      </div>

      <?php if (empty($upcomingSchedules)): ?>
        <div class="py-10 text-center text-xs text-on-surface-variant flex flex-col items-center gap-2">
          <span class="material-symbols-outlined text-3xl opacity-50">calendar_month</span>
          <span>Belum ada jadwal misa aktif mendatang.</span>
        </div>
      <?php else: ?>
        <div class="flex flex-col gap-3">
          <?php foreach ($upcomingSchedules as $s): 
            $warna_cls = match($s['warna_liturgi']) {
              'Putih' => 'badge-liturgi-putih',
              'Merah' => 'badge-liturgi-merah',
              'Ungu' => 'badge-liturgi-ungu',
              'Merah Muda' => 'badge-liturgi-merah-muda',
              default => 'badge-liturgi-hijau'
            };
            $persen = round(($s['terisi'] / max(1, $s['kuota_maksimal'])) * 100);
          ?>
          <div class="p-3.5 rounded-2xl bg-surface-container-low border border-surface-container flex items-center justify-between gap-3 hover:bg-surface-container transition-colors">
            <div class="flex flex-col gap-1">
              <div class="flex items-center gap-2">
                <span class="badge-pill <?= $warna_cls ?> text-[10px]">
                  <?= $s['warna_liturgi'] ?>
                </span>
                <span class="text-xs font-bold text-on-surface font-headline"><?= htmlspecialchars($s['judul_misa']) ?></span>
              </div>
              <div class="text-[11px] text-on-surface-variant flex items-center gap-2">
                <span>📅 <?= format_indo_date($s['tanggal_misa']) ?></span>
                <span>•</span>
                <span>⏰ <?= substr($s['jam_mulai'], 0, 5) ?> WIB</span>
              </div>
            </div>

            <div class="flex flex-col items-end gap-1 shrink-0">
              <span class="text-xs font-bold <?= $s['terisi'] >= $s['kuota_maksimal'] ? 'text-error' : 'text-primary' ?>">
                <?= $s['terisi'] ?> / <?= $s['kuota_maksimal'] ?> Petugas
              </span>
              <div class="w-20 bg-surface-container-high rounded-full h-1.5 overflow-hidden">
                <div class="bg-primary h-1.5 rounded-full" style="width: <?= min(100, $persen) ?>%;"></div>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- Kolom Kanan: Presensi Sakristi Hari Ini -->
    <div class="p-6 rounded-3xl bg-surface-container-lowest border border-surface-container shadow-xs flex flex-col gap-4">
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-2">
          <span class="material-symbols-outlined text-secondary text-xl">qr_code_scanner</span>
          <h2 class="font-headline font-bold text-base text-primary">Presensi Sakristi Hari Ini</h2>
        </div>
        <a href="presensi.php" class="text-xs font-bold text-secondary hover:underline">
          Buka Live Presensi &rarr;
        </a>
      </div>

      <?php if (empty($recentPresensi)): ?>
        <div class="py-10 text-center text-xs text-on-surface-variant flex flex-col items-center gap-2">
          <span class="material-symbols-outlined text-3xl opacity-50">how_to_reg</span>
          <span>Belum ada presensi misdinar yang tercatat hari ini.</span>
        </div>
      <?php else: ?>
        <div class="flex flex-col gap-2.5">
          <?php foreach ($recentPresensi as $p): 
            $status = $p['status_kehadiran'];
            $status_cls = match($status) {
              'hadir' => 'bg-emerald-100 text-emerald-800',
              'terlambat' => 'bg-amber-100 text-amber-800',
              'alpa' => 'bg-red-100 text-red-800',
              default => 'bg-surface-container-high text-on-surface-variant'
            };
          ?>
          <div class="p-3 rounded-2xl bg-surface-container-low border border-surface-container flex items-center justify-between gap-3">
            <div class="flex items-center gap-3">
              <div class="w-8 h-8 rounded-full bg-primary-fixed text-primary font-bold text-xs flex items-center justify-center shrink-0">
                <?= strtoupper(substr($p['nama_panggilan'] ?: $p['nama_lengkap'], 0, 2)) ?>
              </div>
              <div class="flex flex-col">
                <span class="text-xs font-bold text-on-surface"><?= htmlspecialchars($p['nama_lengkap']) ?></span>
                <span class="text-[10px] text-on-surface-variant"><?= htmlspecialchars($p['judul_misa']) ?> • Pukul <?= substr($p['jam_mulai'], 0, 5) ?> WIB</span>
              </div>
            </div>

            <div class="flex flex-col items-end gap-0.5 shrink-0">
              <span class="px-2 py-0.5 rounded-full text-[10px] font-bold <?= $status_cls ?> uppercase">
                <?= $status ?>
              </span>
              <span class="text-[10px] font-mono text-on-surface-variant">
                <?= $p['waktu_checkin'] ? date('H:i', strtotime($p['waktu_checkin'])) . ' WIB' : '-' ?>
              </span>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
  function triggerAutoGenerate() {
    if (!confirm('Generate jadwal misa otomatis untuk 30 hari ke depan (Misa Harian 05.30 WIB & Misa Mingguan)?')) return;
    
    fetch('<?= $base ?>/api/generate_schedules.php', { method: 'POST' })
      .then(res => res.json())
      .then(data => {
        alert(data.message || (data.success ? 'Jadwal 30 hari berhasil dibuat!' : 'Gagal membuat jadwal.'));
        location.reload();
      })
      .catch(err => {
        alert('Terjadi kesalahan koneksi.');
      });
  }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
