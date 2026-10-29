<?php
/**
 * Presensi Sakristi & Live Monitor - SiDinar Regina
 * Tampilan & Desain Identik 100% dengan Web Next.js (/pengurus/presensi)
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_pengurus();

$pdo = get_db();
auto_update_expired_attendance($pdo);

// Handle Update Status Presensi Manual oleh Pengurus
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_attendance') {
        $booking_id = (int)($_POST['booking_id'] ?? 0);
        $new_status = $_POST['status_kehadiran'] ?? 'hadir';
        $schedule_id = (int)($_POST['schedule_id'] ?? 0);

        if ($booking_id && in_array($new_status, ['terdaftar', 'hadir', 'terlambat', 'izin', 'alpa'])) {
            $waktu = in_array($new_status, ['hadir', 'terlambat']) ? date('Y-m-d H:i:s') : null;
            $stmt = $pdo->prepare("UPDATE bookings SET status_kehadiran = ?, waktu_checkin = ? WHERE id = ?");
            $stmt->execute([$new_status, $waktu, $booking_id]);

            set_flash('success', "Status presensi misdinar berhasil diubah menjadi: " . strtoupper($new_status));
            header("Location: presensi.php" . ($schedule_id ? "?schedule_id={$schedule_id}" : ""));
            exit;
        }
    }
}

// Ambil daftar jadwal misa mendatang atau hari ini
$schedules_stmt = $pdo->query("
    SELECT id, judul_misa, tanggal_misa, jam_mulai, kategori, warna_liturgi 
    FROM mass_schedules 
    ORDER BY tanggal_misa DESC, jam_mulai DESC 
    LIMIT 30
");
$all_schedules = $schedules_stmt->fetchAll();

// Filter & Jadwal Terpilih
$selected_id = (int)($_GET['schedule_id'] ?? 0);
if (!$selected_id && !empty($all_schedules)) {
    $today = date('Y-m-d');
    foreach ($all_schedules as $s) {
        if ($s['tanggal_misa'] >= $today) {
            $selected_id = (int)$s['id'];
        }
    }
    if (!$selected_id) {
        $selected_id = (int)$all_schedules[0]['id'];
    }
}

$status_filter = $_GET['status'] ?? 'semua';
$search = trim($_GET['q'] ?? '');

$selected_schedule = null;
$roster = [];
$counters = [
    'total' => 0,
    'hadir' => 0,
    'terlambat' => 0,
    'terdaftar' => 0,
    'izin' => 0,
    'alpa' => 0,
];

if ($selected_id) {
    $stmt = $pdo->prepare("SELECT * FROM mass_schedules WHERE id = ?");
    $stmt->execute([$selected_id]);
    $selected_schedule = $stmt->fetch();

    if ($selected_schedule) {
        $sql = "
            SELECT b.*, p.nama_lengkap, p.nama_panggilan, p.no_telepon, p.jenjang, p.asal_wilayah_teks, s.judul_misa, s.tanggal_misa, s.jam_mulai, s.warna_liturgi
            FROM bookings b
            JOIN profiles p ON b.user_id = p.id
            JOIN mass_schedules s ON b.schedule_id = s.id
            WHERE b.schedule_id = ?
        ";
        $params = [$selected_id];

        if ($status_filter !== 'semua' && in_array($status_filter, ['terdaftar', 'hadir', 'terlambat', 'izin', 'alpa'])) {
            $sql .= " AND b.status_kehadiran = ?";
            $params[] = $status_filter;
        }

        if ($search) {
            $sql .= " AND (p.nama_lengkap LIKE ? OR p.nama_panggilan LIKE ? OR b.posisi_tugas LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        $sql .= " ORDER BY b.posisi_tugas ASC, p.nama_panggilan ASC";
        $rStmt = $pdo->prepare($sql);
        $rStmt->execute($params);
        $roster = $rStmt->fetchAll();

        // Ambil counter lengkap untuk jadwal ini
        $cStmt = $pdo->prepare("SELECT status_kehadiran, COUNT(*) as cnt FROM bookings WHERE schedule_id = ? GROUP BY status_kehadiran");
        $cStmt->execute([$selected_id]);
        $cRows = $cStmt->fetchAll();

        $all_cnt = 0;
        foreach ($cRows as $cr) {
            $st = $cr['status_kehadiran'];
            $cnt = (int)$cr['cnt'];
            $all_cnt += $cnt;
            if (isset($counters[$st])) {
                $counters[$st] = $cnt;
            }
        }
        $counters['total'] = $all_cnt;
    }
}

$qr_token = $selected_schedule['qr_token'] ?? ('misa-' . ($selected_schedule['id'] ?? 0));
$qr_api_url = "https://api.qrserver.com/v1/create-qr-code/?size=260x260&margin=10&data=" . urlencode($qr_token);

$page_title = 'Monitor Presensi Digital';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex flex-col gap-6">
  <!-- Top Header identik Next.js -->
  <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div class="flex flex-col">
      <div class="flex items-center gap-2">
        <span class="material-symbols-outlined text-primary text-xl" style="font-variation-settings: 'FILL' 1">
          qr_code_scanner
        </span>
        <span class="text-xs uppercase font-bold text-secondary tracking-wider">
          Live Monitor Sakristi SPMR
        </span>
      </div>
      <h1 class="font-headline font-bold text-2xl text-primary tracking-tight">
        Monitor Presensi Digital &amp; Kedisiplinan
      </h1>
      <p class="text-xs text-on-surface-variant">
        Pelacakan kehadiran misdinar real-time, toleransi presensi (+45m pasca misa), dan override manual pengurus.
      </p>
    </div>

    <div class="flex items-center gap-2 self-start sm:self-auto">
      <button
        type="button"
        onclick="window.print()"
        class="px-4 py-2.5 rounded-xl bg-surface-container text-on-surface hover:bg-surface-container-high text-xs font-semibold flex items-center gap-1.5 transition-colors"
      >
        <span class="material-symbols-outlined text-base text-primary">print</span>
        <span>Cetak Rekap Presensi</span>
      </button>
    </div>
  </div>

  <!-- Dropdown Selector Jadwal Misa -->
  <div class="p-4 rounded-3xl bg-surface-container-lowest border border-surface-container shadow-xs flex flex-col sm:flex-row sm:items-center justify-between gap-3">
    <div class="flex items-center gap-2 text-xs font-semibold text-on-surface">
      <span class="material-symbols-outlined text-primary text-lg">event_available</span>
      <span>Pilih Jadwal Misa:</span>
    </div>
    <form method="GET" action="" class="flex-1 max-w-xl">
      <select
        name="schedule_id"
        onchange="this.form.submit()"
        class="w-full px-3.5 py-2.5 rounded-xl border border-outline-variant bg-surface-container-low text-on-surface text-xs font-medium focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all"
      >
        <?php foreach ($all_schedules as $s): ?>
          <option value="<?= $s['id'] ?>" <?= $selected_id == $s['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($s['judul_misa']) ?> • <?= format_indo_date($s['tanggal_misa']) ?> (<?= substr($s['jam_mulai'], 0, 5) ?> WIB) - [<?= $s['warna_liturgi'] ?>]
          </option>
        <?php endforeach; ?>
      </select>
    </form>
  </div>

  <!-- Summary Stat Pills identik Next.js (6 grid) -->
  <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
    <a href="?schedule_id=<?= $selected_id ?>&status=semua" class="p-3.5 rounded-2xl bg-surface-container-lowest border <?= $status_filter === 'semua' ? 'border-primary ring-2 ring-primary/20' : 'border-surface-container' ?> text-center flex flex-col hover:bg-surface-container-low transition-colors">
      <span class="text-[10px] text-on-surface-variant uppercase font-medium">Total Terdaftar</span>
      <span class="font-headline font-bold text-xl text-primary"><?= $counters['total'] ?></span>
    </a>
    <a href="?schedule_id=<?= $selected_id ?>&status=hadir" class="p-3.5 rounded-2xl bg-surface-container-lowest border <?= $status_filter === 'hadir' ? 'border-emerald-600 ring-2 ring-emerald-500/20' : 'border-surface-container' ?> text-center flex flex-col hover:bg-surface-container-low transition-colors">
      <span class="text-[10px] text-emerald-800 uppercase font-medium">Tepat Waktu</span>
      <span class="font-headline font-bold text-xl text-emerald-700"><?= $counters['hadir'] ?></span>
    </a>
    <a href="?schedule_id=<?= $selected_id ?>&status=terlambat" class="p-3.5 rounded-2xl bg-surface-container-lowest border <?= $status_filter === 'terlambat' ? 'border-amber-600 ring-2 ring-amber-500/20' : 'border-surface-container' ?> text-center flex flex-col hover:bg-surface-container-low transition-colors">
      <span class="text-[10px] text-amber-800 uppercase font-medium">Terlambat</span>
      <span class="font-headline font-bold text-xl text-amber-600"><?= $counters['terlambat'] ?></span>
    </a>
    <a href="?schedule_id=<?= $selected_id ?>&status=terdaftar" class="p-3.5 rounded-2xl bg-surface-container-lowest border <?= $status_filter === 'terdaftar' ? 'border-primary ring-2 ring-primary/20' : 'border-surface-container' ?> text-center flex flex-col hover:bg-surface-container-low transition-colors">
      <span class="text-[10px] text-primary uppercase font-medium">Belum Presensi</span>
      <span class="font-headline font-bold text-xl text-primary"><?= $counters['terdaftar'] ?></span>
    </a>
    <a href="?schedule_id=<?= $selected_id ?>&status=izin" class="p-3.5 rounded-2xl bg-surface-container-lowest border <?= $status_filter === 'izin' ? 'border-purple-600 ring-2 ring-purple-500/20' : 'border-surface-container' ?> text-center flex flex-col hover:bg-surface-container-low transition-colors">
      <span class="text-[10px] text-purple-800 uppercase font-medium">Izin Sakristi</span>
      <span class="font-headline font-bold text-xl text-purple-700"><?= $counters['izin'] ?></span>
    </a>
    <a href="?schedule_id=<?= $selected_id ?>&status=alpa" class="p-3.5 rounded-2xl bg-surface-container-lowest border <?= $status_filter === 'alpa' ? 'border-error ring-2 ring-error/20' : 'border-surface-container' ?> text-center flex flex-col hover:bg-surface-container-low transition-colors">
      <span class="text-[10px] text-error uppercase font-medium">Alpa (Toleransi +45m)</span>
      <span class="font-headline font-bold text-xl text-error"><?= $counters['alpa'] ?></span>
    </a>
  </div>

  <!-- QR Station & Roster Table Grid -->
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- QR Code Station Card (Kiri) -->
    <div class="p-6 rounded-3xl bg-surface-container-lowest border border-surface-container shadow-xs flex flex-col items-center text-center gap-4">
      <div class="flex items-center gap-2">
        <span class="material-symbols-outlined text-primary text-xl">qr_code_2</span>
        <h3 class="font-headline font-bold text-base text-primary">Station QR Code Presensi</h3>
      </div>
      <p class="text-[11px] text-on-surface-variant max-w-xs">
        Letakkan layar ini di meja sakristi atau cetak untuk dipindai oleh misdinar bertugas.
      </p>

      <div class="p-4 bg-white rounded-2xl border-2 border-primary-fixed shadow-md">
        <img
          src="<?= $qr_api_url ?>"
          alt="QR Code Presensi Sakristi"
          class="w-48 h-48 object-contain"
        />
      </div>

      <div class="flex flex-col gap-1 w-full pt-1">
        <span class="text-xs font-bold text-on-surface"><?= htmlspecialchars($selected_schedule['judul_misa'] ?? 'Misa') ?></span>
        <span class="text-[11px] text-on-surface-variant">
          <?= format_indo_date($selected_schedule['tanggal_misa'] ?? date('Y-m-d')) ?> • <?= substr($selected_schedule['jam_mulai'] ?? '00:00', 0, 5) ?> WIB
        </span>
        <span class="text-[10px] font-mono bg-surface-container-low px-2 py-1 rounded text-outline mt-1 truncate">
          Token: <?= htmlspecialchars($qr_token) ?>
        </span>
      </div>
    </div>

    <!-- Live Roster & Manual Override Table (Kanan, 2 Kolom) -->
    <div class="lg:col-span-2 p-6 rounded-3xl bg-surface-container-lowest border border-surface-container shadow-xs flex flex-col gap-4">
      <!-- Search & Status Filter Filter Bar -->
      <form method="GET" action="" class="flex flex-col sm:flex-row items-center justify-between gap-3">
        <input type="hidden" name="schedule_id" value="<?= $selected_id ?>">
        <div class="relative w-full sm:w-72">
          <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline text-lg">
            search
          </span>
          <input
            type="text"
            name="q"
            value="<?= htmlspecialchars($search) ?>"
            placeholder="Cari nama atau peran..."
            class="w-full pl-9 pr-3 py-2 rounded-xl bg-surface-container-low text-xs border border-surface-container focus:outline-none"
          />
        </div>

        <div class="flex items-center gap-1.5 self-start sm:self-auto">
          <span class="text-xs text-on-surface-variant font-medium">Filter:</span>
          <select
            name="status"
            onchange="this.form.submit()"
            class="px-2.5 py-1.5 rounded-xl bg-surface-container-low text-xs font-semibold border border-surface-container text-on-surface"
          >
            <option value="semua" <?= $status_filter === 'semua' ? 'selected' : '' ?>>Semua Status</option>
            <option value="terdaftar" <?= $status_filter === 'terdaftar' ? 'selected' : '' ?>>Belum Presensi</option>
            <option value="hadir" <?= $status_filter === 'hadir' ? 'selected' : '' ?>>Tepat Waktu</option>
            <option value="terlambat" <?= $status_filter === 'terlambat' ? 'selected' : '' ?>>Terlambat</option>
            <option value="izin" <?= $status_filter === 'izin' ? 'selected' : '' ?>>Izin</option>
            <option value="alpa" <?= $status_filter === 'alpa' ? 'selected' : '' ?>>Alpa</option>
          </select>
        </div>
      </form>

      <?php if (empty($roster)): ?>
        <div class="py-14 text-center text-xs text-on-surface-variant flex flex-col items-center gap-2">
          <span class="material-symbols-outlined text-4xl opacity-40">group_off</span>
          <span>Belum ada misdinar yang terdaftar di jadwal ini.</span>
        </div>
      <?php else: ?>
        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs border-collapse">
            <thead>
              <tr class="border-b border-surface-container text-on-surface-variant font-semibold">
                <th class="py-3 px-3">Petugas Altar</th>
                <th class="py-3 px-3">Status</th>
                <th class="py-3 px-3">Waktu Check-in</th>
                <th class="py-3 px-3 text-right">Ubah Presensi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-surface-container">
              <?php foreach ($roster as $r): 
                $st = $r['status_kehadiran'] ?? 'terdaftar';
                $badge_class = match($st) {
                  'hadir' => 'bg-emerald-100 text-emerald-800',
                  'terlambat' => 'bg-amber-100 text-amber-800',
                  'izin' => 'bg-purple-100 text-purple-800',
                  'alpa' => 'bg-red-100 text-red-800',
                  default => 'bg-surface-container text-on-surface-variant'
                };
              ?>
              <tr class="hover:bg-surface-container-low transition-colors">
                <td class="py-3 px-3">
                  <div class="flex items-center gap-2.5">
                    <div class="w-7 h-7 rounded-full bg-primary-fixed text-primary font-bold text-[11px] flex items-center justify-center shrink-0">
                      <?= strtoupper(substr($r['nama_panggilan'] ?: $r['nama_lengkap'], 0, 2)) ?>
                    </div>
                    <div>
                      <div class="font-bold text-on-surface"><?= htmlspecialchars($r['nama_lengkap']) ?></div>
                      <div class="text-[10px] text-on-surface-variant"><?= htmlspecialchars($r['asal_wilayah_teks']) ?></div>
                    </div>
                  </div>
                </td>
                <td class="py-3 px-3">
                  <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-bold <?= $badge_class ?> uppercase">
                    <?= $st ?>
                  </span>
                </td>
                <td class="py-3 px-3 font-mono text-[11px] text-on-surface-variant">
                  <?= $r['waktu_checkin'] ? date('H:i:s', strtotime($r['waktu_checkin'])) . ' WIB' : 'Belum absen' ?>
                </td>
                <td class="py-3 px-3 text-right">
                  <form method="POST" action="" class="inline-flex items-center gap-1">
                    <input type="hidden" name="action" value="update_attendance">
                    <input type="hidden" name="booking_id" value="<?= $r['id'] ?>">
                    <input type="hidden" name="schedule_id" value="<?= $selected_id ?>">
                    <select
                      name="status_kehadiran"
                      onchange="this.form.submit()"
                      class="px-2 py-1 rounded-lg text-[11px] font-semibold bg-surface-container-high border border-surface-container text-on-surface cursor-pointer"
                    >
                      <option value="terdaftar" <?= $st === 'terdaftar' ? 'selected' : '' ?>>Terdaftar</option>
                      <option value="hadir" <?= $st === 'hadir' ? 'selected' : '' ?>>Hadir</option>
                      <option value="terlambat" <?= $st === 'terlambat' ? 'selected' : '' ?>>Terlambat</option>
                      <option value="izin" <?= $st === 'izin' ? 'selected' : '' ?>>Izin</option>
                      <option value="alpa" <?= $st === 'alpa' ? 'selected' : '' ?>>Alpa</option>
                    </select>
                  </form>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
