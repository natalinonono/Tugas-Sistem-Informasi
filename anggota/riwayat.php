<?php
/**
 * Riwayat Tugas & Rekap Kehadiran Misdinar (Anggota)
 * SiDinar Regina - Sistem Informasi & Presensi Putra-Putri Altar
 * Desain & UI Identik 100% dengan Next.js Dashboard
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();

$user = current_user();
$pdo = get_db();

// Auto-update status pendaftaran yang telah melewati batas toleransi (+45 menit setelah misa selesai) menjadi alpa
auto_update_expired_attendance($pdo);

// Filter Parameter
$status_filter = $_GET['status'] ?? 'semua';

$sql = "
    SELECT b.*, s.judul_misa, s.kategori, s.tanggal_misa, s.jam_mulai, s.warna_liturgi, s.nama_selebran
    FROM bookings b
    JOIN mass_schedules s ON b.schedule_id = s.id
    WHERE b.user_id = ?
";
$params = [$user['id']];

if ($status_filter === 'hadir') {
    $sql .= " AND b.status_kehadiran = 'hadir'";
} elseif ($status_filter === 'terlambat') {
    $sql .= " AND b.status_kehadiran = 'terlambat'";
} elseif ($status_filter === 'izin') {
    $sql .= " AND b.status_kehadiran = 'izin'";
} elseif ($status_filter === 'alpa') {
    $sql .= " AND b.status_kehadiran = 'alpa'";
}

$sql .= " ORDER BY s.tanggal_misa DESC, s.jam_mulai DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$history = $stmt->fetchAll();

// Hitung Statistik Pribadi
$total_hadir = 0;
$total_terlambat = 0;
$total_izin = 0;
$total_alpa = 0;

$allStatsStmt = $pdo->prepare("SELECT status_kehadiran, COUNT(*) as cnt FROM bookings WHERE user_id = ? GROUP BY status_kehadiran");
$allStatsStmt->execute([$user['id']]);
$statsRaw = $allStatsStmt->fetchAll();

$all_count = 0;
foreach ($statsRaw as $sr) {
    $all_count += (int)$sr['cnt'];
    if ($sr['status_kehadiran'] === 'hadir') $total_hadir = (int)$sr['cnt'];
    if ($sr['status_kehadiran'] === 'terlambat') $total_terlambat = (int)$sr['cnt'];
    if ($sr['status_kehadiran'] === 'izin') $total_izin = (int)$sr['cnt'];
    if ($sr['status_kehadiran'] === 'alpa') $total_alpa = (int)$sr['cnt'];
}

$persen_kehadiran = $all_count > 0 ? round((($total_hadir + $total_terlambat) / $all_count) * 100) : 0;

$page_title = 'Riwayat Tugas Altar';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex flex-col gap-6">
  <!-- Page Header Identik Next.js -->
  <div class="flex flex-col gap-1">
    <div class="flex items-center gap-2">
      <span class="material-symbols-outlined text-primary text-xl" style="font-variation-settings: 'FILL' 1;">
        history_edu
      </span>
      <span class="text-xs uppercase font-bold text-secondary tracking-wider">
        Rekapitulasi Pelayanan
      </span>
    </div>
    <h1 class="font-headline font-bold text-2xl text-primary tracking-tight">
      Riwayat Tugas &amp; Presensi Altar
    </h1>
    <p class="text-xs text-on-surface-variant">
      Catatan seluruh perayaan Ekaristi yang telah Anda layani di Paroki SPMR Purbowardayan.
    </p>
  </div>

  <!-- Stats Grid Cards Identik Next.js Dashboard -->
  <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
    <!-- Card 1: Total Penugasan -->
    <div class="p-4 sm:p-5 rounded-2xl bg-surface-container-lowest border border-surface-container shadow-sm flex items-center justify-between">
      <div>
        <span class="text-[11px] font-bold text-on-surface-variant uppercase tracking-wider block mb-1">Total Penugasan</span>
        <div class="font-headline font-bold text-2xl text-primary"><?= $all_count ?></div>
        <span class="text-[11px] text-on-surface-variant">Tercatat di sistem</span>
      </div>
      <div class="w-12 h-12 rounded-2xl bg-primary-fixed text-primary flex items-center justify-center shrink-0">
        <span class="material-symbols-outlined text-2xl">military_tech</span>
      </div>
    </div>

    <!-- Card 2: Tepat Waktu -->
    <div class="p-4 sm:p-5 rounded-2xl bg-surface-container-lowest border border-surface-container shadow-sm flex items-center justify-between">
      <div>
        <span class="text-[11px] font-bold text-on-surface-variant uppercase tracking-wider block mb-1">Tepat Waktu</span>
        <div class="font-headline font-bold text-2xl text-emerald-700"><?= $total_hadir ?></div>
        <span class="text-[11px] text-emerald-600 font-medium">Hadir sebelum misa</span>
      </div>
      <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-700 flex items-center justify-center shrink-0">
        <span class="material-symbols-outlined text-2xl">check_circle</span>
      </div>
    </div>

    <!-- Card 3: Terlambat / Izin -->
    <div class="p-4 sm:p-5 rounded-2xl bg-surface-container-lowest border border-surface-container shadow-sm flex items-center justify-between">
      <div>
        <span class="text-[11px] font-bold text-on-surface-variant uppercase tracking-wider block mb-1">Terlambat / Alpa</span>
        <div class="font-headline font-bold text-2xl text-amber-700"><?= $total_terlambat + $total_alpa ?></div>
        <span class="text-[11px] text-amber-600 font-medium"><?= $total_terlambat ?> terlambat, <?= $total_alpa ?> alpa</span>
      </div>
      <div class="w-12 h-12 rounded-2xl bg-amber-50 text-amber-700 flex items-center justify-center shrink-0">
        <span class="material-symbols-outlined text-2xl">warning</span>
      </div>
    </div>

    <!-- Card 4: Persentase Kehadiran -->
    <div class="p-4 sm:p-5 rounded-2xl bg-surface-container-lowest border border-surface-container shadow-sm flex items-center justify-between">
      <div>
        <span class="text-[11px] font-bold text-on-surface-variant uppercase tracking-wider block mb-1">Kehadiran</span>
        <div class="font-headline font-bold text-2xl text-primary"><?= $persen_kehadiran ?>%</div>
        <span class="text-[11px] text-on-surface-variant">Rasio keaktifan tugas</span>
      </div>
      <div class="w-12 h-12 rounded-2xl bg-primary-fixed text-primary flex items-center justify-center shrink-0">
        <span class="material-symbols-outlined text-2xl">analytics</span>
      </div>
    </div>
  </div>

  <!-- Filter Status Segmented Buttons Identik Next.js -->
  <div class="flex items-center gap-2 overflow-x-auto pb-1">
    <a href="riwayat.php?status=semua" class="px-4 py-2 rounded-xl text-xs font-semibold shrink-0 transition-all <?= $status_filter === 'semua' ? 'bg-primary text-on-primary shadow-xs' : 'bg-surface-container-lowest border border-surface-container text-on-surface-variant hover:bg-surface-container' ?>">
      Semua Riwayat (<?= $all_count ?>)
    </a>
    <a href="riwayat.php?status=hadir" class="px-4 py-2 rounded-xl text-xs font-semibold shrink-0 transition-all <?= $status_filter === 'hadir' ? 'bg-emerald-600 text-white shadow-xs' : 'bg-surface-container-lowest border border-surface-container text-on-surface-variant hover:bg-surface-container' ?>">
      Hadir Tepat Waktu (<?= $total_hadir ?>)
    </a>
    <a href="riwayat.php?status=terlambat" class="px-4 py-2 rounded-xl text-xs font-semibold shrink-0 transition-all <?= $status_filter === 'terlambat' ? 'bg-amber-600 text-white shadow-xs' : 'bg-surface-container-lowest border border-surface-container text-on-surface-variant hover:bg-surface-container' ?>">
      Terlambat (<?= $total_terlambat ?>)
    </a>
    <a href="riwayat.php?status=izin" class="px-4 py-2 rounded-xl text-xs font-semibold shrink-0 transition-all <?= $status_filter === 'izin' ? 'bg-blue-600 text-white shadow-xs' : 'bg-surface-container-lowest border border-surface-container text-on-surface-variant hover:bg-surface-container' ?>">
      Izin (<?= $total_izin ?>)
    </a>
    <a href="riwayat.php?status=alpa" class="px-4 py-2 rounded-xl text-xs font-semibold shrink-0 transition-all <?= $status_filter === 'alpa' ? 'bg-rose-600 text-white shadow-xs' : 'bg-surface-container-lowest border border-surface-container text-on-surface-variant hover:bg-surface-container' ?>">
      Alpa (<?= $total_alpa ?>)
    </a>
  </div>

  <!-- Tabel Riwayat Tugas Identik Next.js -->
  <?php if (empty($history)): ?>
    <div class="p-12 rounded-3xl bg-surface-container-lowest border border-surface-container text-center flex flex-col items-center gap-3">
      <span class="material-symbols-outlined text-4xl text-outline">receipt_long</span>
      <h3 class="font-headline font-bold text-base text-primary">Belum Ada Riwayat Tugas</h3>
      <p class="text-xs text-on-surface-variant max-w-sm">
        Anda belum memiliki catatan riwayat tugas pelayanan untuk kategori filter ini.
      </p>
      <a href="index.php" class="mt-2 inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-primary text-on-primary text-xs font-semibold hover:bg-primary/90 transition-colors shadow-xs">
        <span class="material-symbols-outlined text-sm">calendar_month</span>
        Pilih Jadwal Tugas Misa
      </a>
    </div>
  <?php else: ?>
    <div class="overflow-x-auto rounded-2xl bg-surface-container-lowest border border-surface-container shadow-sm">
      <table class="w-full text-left text-xs">
        <thead class="bg-surface-container-low text-on-surface-variant uppercase tracking-wider font-semibold border-b border-surface-container">
          <tr>
            <th class="py-3.5 px-4">Jadwal Misa</th>
            <th class="py-3.5 px-4">Tanggal &amp; Waktu</th>
            <th class="py-3.5 px-4">Posisi Liturgi</th>
            <th class="py-3.5 px-4">Status Kehadiran</th>
            <th class="py-3.5 px-4">Waktu Presensi</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-surface-container">
          <?php foreach ($history as $h): ?>
            <tr class="hover:bg-surface-container-low/50 transition-colors">
              <td class="py-3.5 px-4">
                <div class="font-bold text-primary font-headline text-sm">
                  <?= htmlspecialchars($h['judul_misa']) ?>
                </div>
                <div class="flex items-center gap-1.5 mt-0.5">
                  <span class="inline-block w-2 h-2 rounded-full <?= get_liturgy_bg_class($h['warna_liturgi'] ?? 'Hijau') ?>"></span>
                  <span class="text-[11px] text-on-surface-variant">
                    <?= htmlspecialchars($h['warna_liturgi'] ?? 'Hijau') ?> &bull; <?= htmlspecialchars($h['kategori']) ?>
                  </span>
                </div>
              </td>
              <td class="py-3.5 px-4 font-mono">
                <div class="font-bold text-on-surface"><?= format_indo_date($h['tanggal_misa']) ?></div>
                <div class="text-[11px] text-on-surface-variant font-sans">
                  Pukul <?= substr($h['jam_mulai'], 0, 5) ?> WIB
                </div>
              </td>
              <td class="py-3.5 px-4">
                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-surface-container text-primary border border-surface-container-high">
                  <span class="material-symbols-outlined text-[13px]">person</span>
                  <?= htmlspecialchars($h['posisi_tugas'] ?? 'Petugas Altar') ?>
                </span>
              </td>
              <td class="py-3.5 px-4">
                <?php
                  $st = $h['status_kehadiran'];
                  if ($st === 'hadir'):
                ?>
                  <span class="inline-block font-bold text-[11px] uppercase px-2.5 py-0.5 rounded-full bg-emerald-100 text-emerald-800">
                    Hadir Tepat Waktu
                  </span>
                <?php elseif ($st === 'terlambat'): ?>
                  <span class="inline-block font-bold text-[11px] uppercase px-2.5 py-0.5 rounded-full bg-amber-100 text-amber-800">
                    Terlambat
                  </span>
                <?php elseif ($st === 'izin'): ?>
                  <span class="inline-block font-bold text-[11px] uppercase px-2.5 py-0.5 rounded-full bg-blue-100 text-blue-800">
                    Izin Sakristi
                  </span>
                <?php elseif ($st === 'alpa'): ?>
                  <span class="inline-block font-bold text-[11px] uppercase px-2.5 py-0.5 rounded-full bg-rose-100 text-rose-800">
                    Alpa (Tidak Hadir)
                  </span>
                <?php else: ?>
                  <span class="inline-block font-bold text-[11px] uppercase px-2.5 py-0.5 rounded-full bg-surface-container text-on-surface-variant">
                    Terdaftar
                  </span>
                <?php endif; ?>
              </td>
              <td class="py-3.5 px-4 font-mono text-on-surface-variant">
                <?php if ($h['waktu_checkin']): ?>
                  <div class="flex items-center gap-1 text-emerald-700 font-semibold">
                    <span class="material-symbols-outlined text-xs">schedule</span>
                    <?= date('d/m/Y H:i:s', strtotime($h['waktu_checkin'])) ?> WIB
                  </div>
                <?php else: ?>
                  <span class="text-on-surface-variant/70 italic text-[11px]">- Belum check-in -</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
