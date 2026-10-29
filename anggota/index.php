<?php
/**
 * Beranda Anggota Misdinar (Dashboard & Booking Mandiri) - SiDinar Regina
 * Tampilan & Desain Identik 100% dengan Web Next.js (/anggota)
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();

$user = current_user();
$pdo = get_db();
auto_update_expired_attendance($pdo);

// Handle Form Submissions (Pendaftaran Tugas Mandiri & Pembatalan)
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($user['status'] !== 'aktif') {
        set_flash('error', 'Akun Anda belum aktif. Harap tunggu verifikasi pengurus sakristi.');
        header('Location: index.php');
        exit;
    }

    // 1. Booking Jadwal Tugas Mandiri
    if ($action === 'book_schedule') {
        $schedule_id = (int)($_POST['schedule_id'] ?? 0);
        $posisi = trim($_POST['posisi_tugas'] ?? 'Petugas Altar');

        if ($schedule_id) {
            $sStmt = $pdo->prepare("SELECT * FROM mass_schedules WHERE id = ?");
            $sStmt->execute([$schedule_id]);
            $sch = $sStmt->fetch();

            if (!$sch) {
                set_flash('error', 'Jadwal misa tidak ditemukan.');
                header('Location: index.php');
                exit;
            }

            // Cek apakah sudah terdaftar
            $cStmt = $pdo->prepare("SELECT id FROM bookings WHERE schedule_id = ? AND user_id = ?");
            $cStmt->execute([$schedule_id, $user['id']]);
            if ($cStmt->fetch()) {
                set_flash('error', 'Anda sudah terdaftar pada misa ini.');
                header('Location: index.php');
                exit;
            }

            // Cek sisa kuota
            $kStmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE schedule_id = ?");
            $kStmt->execute([$schedule_id]);
            $current_count = (int)$kStmt->fetchColumn();

            if ($current_count >= (int)$sch['kuota_maksimal']) {
                set_flash('error', 'Maaf, kuota petugas untuk misa ini sudah penuh.');
                header('Location: index.php');
                exit;
            }

            // Daftarkan booking
            $ins = $pdo->prepare("
                INSERT INTO bookings (schedule_id, user_id, kategori_misa, minggu_ke, tahun, posisi_tugas, status_kehadiran)
                VALUES (?, ?, ?, ?, ?, ?, 'terdaftar')
            ");
            $ins->execute([$schedule_id, $user['id'], $sch['kategori'], $sch['minggu_ke'], $sch['tahun'], $posisi]);

            set_flash('success', "Pendaftaran tugas '{$sch['judul_misa']}' sebagai {$posisi} berhasil!");
            header('Location: index.php');
            exit;
        }
    }

    // 2. Batalkan Pendaftaran Tugas
    if ($action === 'cancel_booking') {
        $booking_id = (int)($_POST['booking_id'] ?? 0);
        if ($booking_id) {
            $del = $pdo->prepare("DELETE FROM bookings WHERE id = ? AND user_id = ? AND status_kehadiran = 'terdaftar'");
            $del->execute([$booking_id, $user['id']]);
            set_flash('success', 'Pendaftaran tugas misa berhasil dibatalkan.');
            header('Location: index.php');
            exit;
        }
    }
}

$today = date('Y-m-d');
$current_week = (int)date('W');
$current_year = (int)date('Y');

// 1. Ambil Penugasan Aktif Terdekat untuk User Ini
$active_duty_stmt = $pdo->prepare("
    SELECT b.*, s.judul_misa, s.kategori, s.tanggal_misa, s.jam_mulai, s.warna_liturgi, s.qr_token
    FROM bookings b
    JOIN mass_schedules s ON b.schedule_id = s.id
    WHERE b.user_id = ? AND s.tanggal_misa >= ?
    ORDER BY s.tanggal_misa ASC, s.jam_mulai ASC
    LIMIT 1
");
$active_duty_stmt->execute([$user['id'], $today]);
$active_duty = $active_duty_stmt->fetch();

// 2. Hitung Kuota Tugas Pekan Ini (Harian & Mingguan)
$quota_stmt = $pdo->prepare("
    SELECT kategori_misa, COUNT(*) as cnt 
    FROM bookings 
    WHERE user_id = ? AND minggu_ke = ? AND tahun = ?
    GROUP BY kategori_misa
");
$quota_stmt->execute([$user['id'], $current_week, $current_year]);
$quota_rows = $quota_stmt->fetchAll();

$has_harian = false;
$has_mingguan = false;
foreach ($quota_rows as $qr) {
    if ($qr['kategori_misa'] === 'harian' && (int)$qr['cnt'] > 0) $has_harian = true;
    if ($qr['kategori_misa'] === 'mingguan' && (int)$qr['cnt'] > 0) $has_mingguan = true;
}

// 3. Warta Sakristi Terbaru
$warta_stmt = $pdo->query("SELECT * FROM announcements ORDER BY is_pinned DESC, created_at DESC LIMIT 3");
$recent_warta = $warta_stmt->fetchAll();

// 4. Jadwal Misa yang Tersedia
$kat_filter = $_GET['kat'] ?? 'semua';
$sch_sql = "
    SELECT s.*, 
           (SELECT COUNT(*) FROM bookings WHERE schedule_id = s.id) as terisi,
           (SELECT status_kehadiran FROM bookings WHERE schedule_id = s.id AND user_id = ?) as my_status,
           (SELECT id FROM bookings WHERE schedule_id = s.id AND user_id = ?) as my_booking_id
    FROM mass_schedules s
    WHERE s.tanggal_misa >= ?
";
$sch_params = [$user['id'], $user['id'], $today];

if ($kat_filter === 'harian' || $kat_filter === 'mingguan') {
    $sch_sql .= " AND s.kategori = ?";
    $sch_params[] = $kat_filter;
}

$sch_sql .= " ORDER BY s.tanggal_misa ASC, s.jam_mulai ASC LIMIT 20";
$sch_stmt = $pdo->prepare($sch_sql);
$sch_stmt->execute($sch_params);
$available_schedules = $sch_stmt->fetchAll();

$liturgical_positions = [
    'Petugas Altar',
    'Thurifer (Dupa)',
    'Navis (Wadah Dupa)',
    'Krusifer (Salib)',
    'Akolit Lilin 1',
    'Akolit Lilin 2',
    'Petugas Lektor Altar'
];

$page_title = 'Beranda Misdinar';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex flex-col gap-8">
  <!-- 1. Header & Identity Section identik Next.js -->
  <section class="relative overflow-hidden rounded-3xl bg-surface-container-lowest p-6 sm:p-8 shadow-xs border border-surface-container">
    <!-- Ambient Accent Blurs -->
    <div class="ambient-accent-top"></div>
    <div class="ambient-accent-bottom"></div>

    <div class="relative z-10 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
      <div class="flex flex-col gap-2 max-w-2xl">
        <div class="flex flex-wrap items-center gap-2">
          <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold <?= $user['status'] === 'aktif' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' ?>">
            <span class="material-symbols-outlined text-[14px]" style="font-variation-settings: 'FILL' 1">
              <?= $user['status'] === 'aktif' ? 'check_circle' : 'hourglass_top' ?>
            </span>
            <span><?= $user['status'] === 'aktif' ? 'Anggota Aktif' : 'Menunggu Persetujuan Pengurus' ?></span>
          </span>

          <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-primary-fixed text-on-primary-fixed text-xs font-semibold">
            <span class="material-symbols-outlined text-[14px]">church</span>
            <span><?= htmlspecialchars($user['asal_wilayah_teks'] ?: 'Paroki Purbowardayan') ?></span>
          </span>

          <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-surface-container-high text-on-surface-variant text-xs font-medium">
            <span class="material-symbols-outlined text-[14px]">school</span>
            <span>Jenjang <?= htmlspecialchars($user['jenjang'] ?: 'SMP') ?></span>
          </span>
        </div>

        <h1 class="font-headline font-bold text-2xl sm:text-3xl text-primary tracking-tight">
          Berkah Dalem, <?= htmlspecialchars($user['nama_panggilan'] ?: $user['nama_lengkap']) ?>! 🙏
        </h1>
        <p class="text-xs sm:text-sm text-on-surface-variant leading-relaxed">
          Selamat melayani di altar suci Tuhan Yesus Kristus Paroki SPMR Purbowardayan. Silakan daftarkan jadwal tugas altar Anda untuk pekan ini.
        </p>
      </div>

      <!-- Quota Tracker Cards identik Next.js -->
      <div class="flex flex-wrap sm:flex-nowrap items-center gap-3">
        <div class="p-3.5 rounded-2xl bg-surface-container-low border border-surface-container flex flex-col gap-1 w-36">
          <span class="text-[10px] text-on-surface-variant uppercase font-medium">Misa Harian</span>
          <span class="font-headline font-bold text-base <?= $has_harian ? 'text-secondary' : 'text-emerald-700' ?>">
            <?= $has_harian ? '1 / 1 (Terpakai)' : '0 / 1 (Tersedia)' ?>
          </span>
        </div>
        <div class="p-3.5 rounded-2xl bg-surface-container-low border border-surface-container flex flex-col gap-1 w-36">
          <span class="text-[10px] text-on-surface-variant uppercase font-medium">Misa Mingguan</span>
          <span class="font-headline font-bold text-base <?= $has_mingguan ? 'text-secondary' : 'text-emerald-700' ?>">
            <?= $has_mingguan ? '1 / 1 (Terpakai)' : '0 / 1 (Tersedia)' ?>
          </span>
        </div>
      </div>
    </div>
  </section>

  <!-- 2. Upcoming Active Duty Card (Jika User Memiliki Jadwal Tugas Aktif) -->
  <?php if ($active_duty): 
    $duty_warna_badge = match($active_duty['warna_liturgi']) {
      'Putih' => 'badge-liturgi-putih',
      'Merah' => 'badge-liturgi-merah',
      'Ungu' => 'badge-liturgi-ungu',
      'Merah Muda' => 'badge-liturgi-merah-muda',
      default => 'badge-liturgi-hijau'
    };
    $st_duty = $active_duty['status_kehadiran'];
    $st_duty_badge = match($st_duty) {
      'hadir' => 'bg-emerald-100 text-emerald-800',
      'terlambat' => 'bg-amber-100 text-amber-800',
      'alpa' => 'bg-red-100 text-red-800',
      default => 'bg-primary-fixed text-primary'
    };
  ?>
  <section class="p-6 rounded-3xl bg-surface-container-lowest border-2 border-primary-fixed shadow-sm flex flex-col gap-4">
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-2">
        <span class="material-symbols-outlined text-primary text-xl" style="font-variation-settings: 'FILL' 1">event_upcoming</span>
        <h2 class="font-headline font-bold text-base text-primary">Tugas Altar Anda Mendatang</h2>
      </div>
      <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase <?= $st_duty_badge ?>">
        <?= $st_duty ?>
      </span>
    </div>

    <div class="p-4 rounded-2xl bg-surface-container-low border border-surface-container flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div class="flex flex-col gap-1.5">
        <div class="flex items-center gap-2">
          <span class="badge-pill <?= $duty_warna_badge ?> text-[10px]">
            <?= $active_duty['warna_liturgi'] ?>
          </span>
          <span class="text-xs font-semibold text-secondary uppercase tracking-wider">
            Misa <?= ucfirst($active_duty['kategori']) ?>
          </span>
        </div>
        <h3 class="font-headline font-bold text-lg text-primary">
          <?= htmlspecialchars($active_duty['judul_misa']) ?>
        </h3>
        <div class="flex items-center gap-2 text-xs text-on-surface-variant">
          <span>📅 <?= format_indo_date($active_duty['tanggal_misa']) ?></span>
          <span>•</span>
          <span class="font-bold text-on-surface">⏰ <?= substr($active_duty['jam_mulai'], 0, 5) ?> WIB</span>
          <span>•</span>
          <span>🛡️ Peran: <strong><?= htmlspecialchars($active_duty['posisi_tugas']) ?></strong></span>
        </div>
      </div>

      <div class="flex items-center gap-2 self-start sm:self-auto">
        <?php if ($st_duty === 'terdaftar'): ?>
          <button
            type="button"
            onclick="openCheckinModal(<?= $active_duty['id'] ?>, '<?= htmlspecialchars($active_duty['judul_misa']) ?>', '<?= $active_duty['tanggal_misa'] ?>', '<?= substr($active_duty['jam_mulai'], 0, 5) ?>', '<?= htmlspecialchars($active_duty['posisi_tugas']) ?>')"
            class="px-4 py-2 rounded-xl bg-primary text-on-primary text-xs font-headline font-bold hover:bg-primary-container transition-colors flex items-center gap-1.5 shadow-xs"
          >
            <span class="material-symbols-outlined text-sm">how_to_reg</span>
            <span>Presensi Sakristi</span>
          </button>
          <form method="POST" action="" onsubmit="return confirm('Apakah Anda yakin ingin membatalkan tugas misa ini?')">
            <input type="hidden" name="action" value="cancel_booking">
            <input type="hidden" name="booking_id" value="<?= $active_duty['id'] ?>">
            <button type="submit" class="px-3 py-2 rounded-xl bg-surface-container-high hover:bg-error-container hover:text-error text-xs font-semibold transition-colors">
              Batalkan
            </button>
          </form>
        <?php else: ?>
          <span class="text-xs font-bold text-emerald-800 flex items-center gap-1">
            <span class="material-symbols-outlined text-base">verified</span>
            <span>Presensi Tercatat (<?= strtoupper($st_duty) ?>)</span>
          </span>
        <?php endif; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <!-- 3. Warta Sakristi Pinned Announcement Section -->
  <?php if (!empty($recent_warta)): ?>
  <section class="flex flex-col gap-3">
    <div class="flex items-center gap-2">
      <span class="material-symbols-outlined text-secondary text-xl" style="font-variation-settings: 'FILL' 1">campaign</span>
      <h2 class="font-headline font-bold text-base text-primary">Warta &amp; Pengumuman Sakristi</h2>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <?php foreach ($recent_warta as $w): ?>
      <div class="p-4 rounded-3xl bg-surface-container-lowest border border-surface-container shadow-xs flex flex-col gap-2 hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between gap-2">
          <?php if ($w['is_pinned']): ?>
            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-900 border border-amber-300 flex items-center gap-0.5">
              <span class="material-symbols-outlined text-xs" style="font-variation-settings: 'FILL' 1">push_pin</span>
              Pin
            </span>
          <?php endif; ?>
          <span class="text-[10px] text-on-surface-variant font-medium ml-auto"><?= format_indo_date($w['created_at']) ?></span>
        </div>
        <h3 class="font-headline font-bold text-xs text-primary line-clamp-1"><?= htmlspecialchars($w['judul']) ?></h3>
        <p class="text-[11px] text-on-surface-variant line-clamp-3 leading-relaxed"><?= htmlspecialchars($w['isi']) ?></p>
      </div>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <!-- 4. Mass Schedule Browser identik Next.js -->
  <section class="flex flex-col gap-4">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div class="flex flex-col">
        <h2 class="font-headline font-bold text-xl text-primary">Daftar Jadwal Misa Tersedia</h2>
        <p class="text-xs text-on-surface-variant">Pilih jadwal perayaan Ekaristi dan daftarkan peran pelayanan Anda.</p>
      </div>

      <!-- Segmented Buttons identik Next.js -->
      <div class="inline-flex p-1 bg-surface-container rounded-2xl self-start sm:self-auto gap-1">
        <a
          href="?kat=semua"
          class="px-3.5 py-1.5 rounded-xl text-xs font-headline transition-all <?= $kat_filter === 'semua' ? 'bg-surface-container-lowest text-primary font-bold shadow-xs' : 'text-on-surface-variant hover:text-primary' ?>"
        >
          Semua Jadwal
        </a>
        <a
          href="?kat=harian"
          class="px-3.5 py-1.5 rounded-xl text-xs font-headline transition-all <?= $kat_filter === 'harian' ? 'bg-surface-container-lowest text-primary font-bold shadow-xs' : 'text-on-surface-variant hover:text-primary' ?>"
        >
          Misa Harian
        </a>
        <a
          href="?kat=mingguan"
          class="px-3.5 py-1.5 rounded-xl text-xs font-headline transition-all <?= $kat_filter === 'mingguan' ? 'bg-surface-container-lowest text-primary font-bold shadow-xs' : 'text-on-surface-variant hover:text-primary' ?>"
        >
          Misa Mingguan
        </a>
      </div>
    </div>

    <?php if (empty($available_schedules)): ?>
      <div class="p-16 rounded-3xl bg-surface-container-lowest border border-surface-container text-center flex flex-col items-center gap-3">
        <span class="material-symbols-outlined text-4xl text-outline">calendar_today</span>
        <h3 class="font-headline font-bold text-sm text-on-surface">Tidak ada jadwal misa</h3>
        <p class="text-xs text-on-surface-variant">Belum ada jadwal misa yang dibuka untuk pendaftaran saat ini.</p>
      </div>
    <?php else: ?>
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
        <?php foreach ($available_schedules as $s): 
          $s_warna = match($s['warna_liturgi']) {
            'Putih' => 'badge-liturgi-putih',
            'Merah' => 'badge-liturgi-merah',
            'Ungu' => 'badge-liturgi-ungu',
            'Merah Muda' => 'badge-liturgi-merah-muda',
            default => 'badge-liturgi-hijau'
          };
          $terisi = (int)$s['terisi'];
          $kuota = (int)$s['kuota_maksimal'];
          $persen = round(($terisi / max(1, $kuota)) * 100);
          $is_full = ($terisi >= $kuota);
          $my_status = $s['my_status'];
        ?>
        <div class="p-5 rounded-3xl bg-surface-container-lowest border border-surface-container shadow-xs flex flex-col justify-between gap-4 hover:shadow-md transition-shadow">
          <div class="flex flex-col gap-3">
            <div class="flex items-center justify-between gap-2">
              <span class="text-[10px] font-semibold text-secondary uppercase tracking-wider px-2 py-0.5 rounded-full bg-secondary-fixed/50">
                <?= $s['kategori'] === 'harian' ? 'Harian' : 'Mingguan' ?>
              </span>
              <span class="badge-pill <?= $s_warna ?> text-[10px]">
                <?= $s['warna_liturgi'] ?>
              </span>
            </div>

            <h3 class="font-headline font-bold text-base text-primary line-clamp-1">
              <?= htmlspecialchars($s['judul_misa']) ?>
            </h3>

            <div class="flex flex-col gap-1 text-xs text-on-surface-variant">
              <div class="flex items-center gap-1.5">
                <span class="material-symbols-outlined text-sm text-primary">calendar_month</span>
                <span><?= format_indo_date($s['tanggal_misa']) ?></span>
              </div>
              <div class="flex items-center gap-1.5">
                <span class="material-symbols-outlined text-sm text-secondary">schedule</span>
                <span class="font-bold text-on-surface"><?= substr($s['jam_mulai'], 0, 5) ?> WIB</span>
              </div>
            </div>

            <!-- Kuota Progress Bar -->
            <div class="flex flex-col gap-1 pt-1">
              <div class="flex items-center justify-between text-[11px]">
                <span class="text-on-surface-variant font-medium">Kapasitas Petugas:</span>
                <span class="font-bold <?= $is_full ? 'text-error' : 'text-primary' ?>">
                  <?= $terisi ?> / <?= $kuota ?>
                </span>
              </div>
              <div class="w-full bg-surface-container-high rounded-full h-2 overflow-hidden">
                <div class="bg-primary h-2 rounded-full" style="width: <?= min(100, $persen) ?>%;"></div>
              </div>
            </div>
          </div>

          <!-- Action Button -->
          <div class="pt-2 border-t border-surface-container">
            <?php if ($my_status): ?>
              <div class="p-2.5 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-bold text-center flex items-center justify-center gap-1">
                <span class="material-symbols-outlined text-sm">check_circle</span>
                <span>Anda Terdaftar (<?= strtoupper($my_status) ?>)</span>
              </div>
            <?php elseif ($is_full): ?>
              <button disabled class="w-full py-2.5 px-3 rounded-xl bg-surface-container text-outline text-xs font-bold cursor-not-allowed text-center">
                Kuota Penuh
              </button>
            <?php elseif ($user['status'] !== 'aktif'): ?>
              <button disabled class="w-full py-2.5 px-3 rounded-xl bg-surface-container text-amber-800 text-xs font-semibold cursor-not-allowed text-center">
                Menunggu Verifikasi Akun
              </button>
            <?php else: ?>
              <button
                type="button"
                onclick="openBookingModal(<?= $s['id'] ?>, '<?= htmlspecialchars($s['judul_misa']) ?>', '<?= format_indo_date($s['tanggal_misa']) ?>', '<?= substr($s['jam_mulai'], 0, 5) ?>')"
                class="w-full py-2.5 px-3 rounded-xl bg-primary text-on-primary hover:bg-primary-container text-xs font-headline font-bold transition-all shadow-xs flex items-center justify-center gap-1.5"
              >
                <span class="material-symbols-outlined text-sm">edit_calendar</span>
                <span>Daftar Tugas Altar</span>
              </button>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
</div>

<!-- Modal Booking Mandiri -->
<div id="modal-book-schedule" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs hidden items-center justify-center p-4">
  <div class="bg-surface-container-lowest border border-surface-container rounded-3xl max-w-md w-full shadow-2xl overflow-hidden animate-scaleUp">
    <div class="p-5 border-b border-surface-container flex items-center justify-between bg-surface-container-low">
      <div class="flex items-center gap-2">
        <span class="material-symbols-outlined text-primary text-xl">event_available</span>
        <h3 class="font-headline font-bold text-base text-primary">Konfirmasi Pendaftaran Tugas</h3>
      </div>
      <button type="button" onclick="closeModal('modal-book-schedule')" class="w-8 h-8 rounded-full flex items-center justify-center hover:bg-surface-container text-on-surface-variant">
        <span class="material-symbols-outlined text-lg">close</span>
      </button>
    </div>

    <form method="POST" action="" class="p-5 flex flex-col gap-4 text-xs">
      <input type="hidden" name="action" value="book_schedule">
      <input type="hidden" name="schedule_id" id="book-schedule-id" value="">

      <div class="p-3.5 rounded-2xl bg-surface-container-low border border-surface-container flex flex-col gap-1">
        <span class="text-[10px] text-on-surface-variant uppercase font-medium">Jadwal Terpilih</span>
        <div id="book-modal-title" class="font-headline font-bold text-sm text-primary">--</div>
        <div id="book-modal-time" class="text-[11px] text-on-surface-variant">--</div>
      </div>

      <div>
        <label class="block font-semibold text-on-surface-variant mb-1">Pilih Peran Liturgi Altar *</label>
        <select name="posisi_tugas" required class="w-full px-3.5 py-2.5 rounded-xl border border-outline-variant bg-surface-container-lowest text-on-surface text-xs focus:outline-none focus:ring-2 focus:ring-primary/20">
          <?php foreach ($liturgical_positions as $pos): ?>
            <option value="<?= $pos ?>"><?= $pos ?></option>
          <?php endforeach; ?>
        </select>
        <span class="text-[11px] text-on-surface-variant mt-1 block">
          *Pastikan hadir paling lambat H-30 menit di sakristi dalam kondisi rapi.
        </span>
      </div>

      <div class="p-3 border-t border-surface-container mt-2 flex justify-end gap-2">
        <button type="button" onclick="closeModal('modal-book-schedule')" class="px-4 py-2 rounded-xl bg-surface-container-high text-on-surface font-semibold">Batal</button>
        <button type="submit" class="px-5 py-2 rounded-xl bg-primary text-on-primary font-bold hover:bg-primary-container transition-colors">Daftar Sekarang</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Presensi Sakristi Digital -->
<div id="modal-checkin" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs hidden items-center justify-center p-4">
  <div class="bg-surface-container-lowest border border-surface-container rounded-3xl max-w-md w-full shadow-2xl overflow-hidden animate-scaleUp">
    <div class="p-5 border-b border-surface-container flex items-center justify-between bg-surface-container-low">
      <div class="flex items-center gap-2">
        <span class="material-symbols-outlined text-primary text-xl">qr_code_scanner</span>
        <h3 class="font-headline font-bold text-base text-primary">Presensi Sakristi Misdinar</h3>
      </div>
      <button type="button" onclick="closeModal('modal-checkin')" class="w-8 h-8 rounded-full flex items-center justify-center hover:bg-surface-container text-on-surface-variant">
        <span class="material-symbols-outlined text-lg">close</span>
      </button>
    </div>

    <div class="p-5 flex flex-col gap-4 text-xs text-center items-center">
      <div class="p-3.5 rounded-2xl bg-surface-container-low border border-surface-container w-full text-left">
        <div id="checkin-title" class="font-bold text-sm text-primary">--</div>
        <div id="checkin-time" class="text-[11px] text-on-surface-variant">--</div>
        <div id="checkin-peran" class="text-[11px] font-semibold text-secondary mt-1">--</div>
      </div>

      <div class="p-4 bg-white rounded-2xl border-2 border-primary-fixed shadow-md my-1">
        <img id="checkin-qr-img" src="" alt="QR Presensi" class="w-40 h-40 object-contain mx-auto">
      </div>

      <p class="text-[11px] text-on-surface-variant">
        Tunjukkan QR Code ini ke scanner sakristi, atau klik tombol presensi langsung di bawah jika berada di gereja.
      </p>

      <form method="POST" action="<?= $base ?>/api/checkin.php" id="direct-checkin-form" class="w-full">
        <input type="hidden" name="booking_id" id="checkin-booking-id" value="">
        <button
          type="button"
          onclick="submitDirectCheckin()"
          class="w-full py-3 px-4 rounded-xl font-headline font-bold text-xs bg-primary text-on-primary hover:bg-primary-container shadow-md transition-all flex items-center justify-center gap-2"
        >
          <span class="material-symbols-outlined text-lg">how_to_reg</span>
          <span>Presensi Sakristi Sekarang</span>
        </button>
      </form>
    </div>

    <div class="p-3 border-t border-surface-container bg-surface-container-low flex justify-end">
      <button type="button" onclick="closeModal('modal-checkin')" class="px-4 py-2 rounded-xl text-xs font-semibold bg-surface-container-high text-on-surface">Tutup</button>
    </div>
  </div>
</div>

<script>
  function openBookingModal(id, title, date, time) {
    document.getElementById('book-schedule-id').value = id;
    document.getElementById('book-modal-title').innerText = title;
    document.getElementById('book-modal-time').innerText = date + ' • ' + time + ' WIB';
    openModal('modal-book-schedule');
  }

  function openCheckinModal(bookingId, title, date, time, peran) {
    document.getElementById('checkin-booking-id').value = bookingId;
    document.getElementById('checkin-title').innerText = title;
    document.getElementById('checkin-time').innerText = date + ' • ' + time + ' WIB';
    document.getElementById('checkin-peran').innerText = 'Peran: ' + peran;
    
    // QR generator URL
    const qrPayload = 'SIDINAR:' + bookingId + ':<?= $user['id'] ?>:' + date;
    document.getElementById('checkin-qr-img').src = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&margin=5&data=' + encodeURIComponent(qrPayload);
    
    openModal('modal-checkin');
  }

  function submitDirectCheckin() {
    const bookingId = document.getElementById('checkin-booking-id').value;
    const formData = new FormData();
    formData.append('booking_id', bookingId);

    fetch('<?= $base ?>/api/checkin.php', {
      method: 'POST',
      body: formData
    })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        alert(data.message || 'Presensi Sakristi Berhasil!');
        location.reload();
      } else {
        alert(data.error || 'Gagal melakukan presensi.');
      }
    })
    .catch(() => {
      alert('Terjadi kesalahan jaringan.');
    });
  }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
