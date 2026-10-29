<?php
/**
 * Manajemen Jadwal Misa & Penugasan Altar - SiDinar Regina
 * Tampilan & Desain Identik 100% dengan Web Next.js (/pengurus/jadwal)
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_pengurus();

$pdo = get_db();
auto_update_expired_attendance($pdo);

// Handle Form Submissions
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. Tambah Jadwal Misa Baru
    if ($action === 'create_schedule') {
        $judul = trim($_POST['judul_misa'] ?? '');
        $kategori = $_POST['kategori'] ?? 'mingguan';
        $tanggal = $_POST['tanggal_misa'] ?? '';
        $jam = $_POST['jam_mulai'] ?? '';
        $kuota = (int)($_POST['kuota_maksimal'] ?? 12);
        $warna = $_POST['warna_liturgi'] ?? 'Hijau';
        $tahun_liturgi = trim($_POST['tahun_liturgi'] ?? 'Tahun B / Liturgi Biasa');

        if ($judul && $tanggal && $jam) {
            $minggu_ke = (int)date('W', strtotime($tanggal));
            $tahun = (int)date('Y', strtotime($tanggal));
            $token = md5(uniqid("misa-{$tanggal}-", true));

            $stmt = $pdo->prepare("
                INSERT INTO mass_schedules 
                (judul_misa, kategori, tanggal_misa, jam_mulai, kuota_maksimal, minggu_ke, tahun, tahun_liturgi, warna_liturgi, nama_selebran, qr_token)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, '', ?)
            ");
            $stmt->execute([$judul, $kategori, $tanggal, $jam, $kuota, $minggu_ke, $tahun, $tahun_liturgi, $warna, $token]);
            set_flash('success', "Jadwal '{$judul}' berhasil ditambahkan ke kalender liturgi!");
            header('Location: jadwal.php');
            exit;
        } else {
            set_flash('error', 'Semua bidang wajib diisi dengan benar.');
        }
    }

    // 2. Hapus Jadwal Misa
    if ($action === 'delete_schedule') {
        $schedule_id = (int)($_POST['schedule_id'] ?? 0);
        if ($schedule_id) {
            $stmt = $pdo->prepare("DELETE FROM mass_schedules WHERE id = ?");
            $stmt->execute([$schedule_id]);
            set_flash('success', 'Jadwal misa berhasil dihapus dari sistem.');
            header('Location: jadwal.php');
            exit;
        }
    }

    // 3. Tambah Petugas Manual oleh Pengurus
    if ($action === 'assign_member') {
        $schedule_id = (int)($_POST['schedule_id'] ?? 0);
        $user_id = (int)($_POST['user_id'] ?? 0);
        $posisi = trim($_POST['posisi_tugas'] ?? 'Petugas Altar');

        if ($schedule_id && $user_id) {
            $chk = $pdo->prepare("SELECT id FROM bookings WHERE schedule_id = ? AND user_id = ?");
            $chk->execute([$schedule_id, $user_id]);
            if ($chk->fetch()) {
                set_flash('error', 'Misdinar tersebut sudah terdaftar pada jadwal misa ini.');
            } else {
                $sInfo = $pdo->prepare("SELECT kategori, minggu_ke, tahun FROM mass_schedules WHERE id = ?");
                $sInfo->execute([$schedule_id]);
                $sch = $sInfo->fetch();

                $ins = $pdo->prepare("
                    INSERT INTO bookings (schedule_id, user_id, kategori_misa, minggu_ke, tahun, posisi_tugas, status_kehadiran)
                    VALUES (?, ?, ?, ?, ?, ?, 'terdaftar')
                ");
                $ins->execute([$schedule_id, $user_id, $sch['kategori'], $sch['minggu_ke'], $sch['tahun'], $posisi]);
                set_flash('success', 'Petugas misdinar berhasil ditambahkan ke jadwal!');
            }
            header("Location: jadwal.php?schedule_id={$schedule_id}");
            exit;
        }
    }

    // 4. Ubah Posisi Petugas
    if ($action === 'update_position') {
        $booking_id = (int)($_POST['booking_id'] ?? 0);
        $schedule_id = (int)($_POST['schedule_id'] ?? 0);
        $posisi = trim($_POST['posisi_tugas'] ?? 'Petugas Altar');
        if ($booking_id && $posisi) {
            $stmt = $pdo->prepare("UPDATE bookings SET posisi_tugas = ? WHERE id = ?");
            $stmt->execute([$posisi, $booking_id]);
            set_flash('success', 'Peran petugas berhasil diperbarui.');
            header("Location: jadwal.php?schedule_id={$schedule_id}");
            exit;
        }
    }

    // 5. Hapus / Batal Penugasan Petugas
    if ($action === 'remove_booking') {
        $booking_id = (int)($_POST['booking_id'] ?? 0);
        $schedule_id = (int)($_POST['schedule_id'] ?? 0);
        if ($booking_id) {
            $stmt = $pdo->prepare("DELETE FROM bookings WHERE id = ?");
            $stmt->execute([$booking_id]);
            set_flash('success', 'Penugasan petugas berhasil dibatalkan.');
            header("Location: jadwal.php?schedule_id={$schedule_id}");
            exit;
        }
    }
}

// Ambil semua jadwal
$schedules_stmt = $pdo->query("
    SELECT s.*, 
           (SELECT COUNT(*) FROM bookings WHERE schedule_id = s.id) as terisi
    FROM mass_schedules s
    ORDER BY s.tanggal_misa ASC, s.jam_mulai ASC
");
$all_schedules = $schedules_stmt->fetchAll();

// Tentukan jadwal yang sedang dipilih
$selected_id = (int)($_GET['schedule_id'] ?? 0);
if (!$selected_id && !empty($all_schedules)) {
    $today = date('Y-m-d');
    foreach ($all_schedules as $s) {
        if ($s['tanggal_misa'] >= $today) {
            $selected_id = (int)$s['id'];
            break;
        }
    }
    if (!$selected_id) {
        $selected_id = (int)$all_schedules[0]['id'];
    }
}

$selected_schedule = null;
$roster = [];
if ($selected_id) {
    foreach ($all_schedules as $s) {
        if ($s['id'] == $selected_id) {
            $selected_schedule = $s;
            break;
        }
    }
    if ($selected_schedule) {
        $rStmt = $pdo->prepare("
            SELECT b.*, p.nama_lengkap, p.nama_panggilan, p.no_telepon, p.jenjang, p.asal_wilayah_teks
            FROM bookings b
            JOIN profiles p ON b.user_id = p.id
            WHERE b.schedule_id = ?
            ORDER BY b.posisi_tugas ASC, p.nama_panggilan ASC
        ");
        $rStmt->execute([$selected_id]);
        $roster = $rStmt->fetchAll();
    }
}

// Daftar misdinar aktif untuk modal assign
$membersStmt = $pdo->query("SELECT id, nama_lengkap, nama_panggilan, asal_wilayah_teks FROM profiles WHERE role = 'anggota' AND status = 'aktif' ORDER BY nama_lengkap ASC");
$active_members = $membersStmt->fetchAll();

$page_title = 'Kelola Jadwal Misa & Petugas';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex flex-col gap-6">
  <!-- Top Header identik Next.js -->
  <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div class="flex flex-col">
      <div class="flex items-center gap-2">
        <span class="material-symbols-outlined text-primary text-xl" style="font-variation-settings: 'FILL' 1">
          calendar_month
        </span>
        <span class="text-xs uppercase font-bold text-secondary tracking-wider">
          Manajemen Petugas &amp; Jadwal Misa
        </span>
      </div>
      <h1 class="font-headline font-bold text-2xl text-primary tracking-tight">
        Penataan Petugas Altar &amp; Jadwal
      </h1>
      <p class="text-xs text-on-surface-variant">
        Atur jadwal misa, alokasi peran liturgi misdinar, dan kelola kapasitas kuota sakristi.
      </p>
    </div>

    <button
      type="button"
      onclick="openModal('modal-add-schedule')"
      class="px-4 py-2.5 rounded-xl bg-primary text-on-primary hover:bg-primary-container text-xs font-headline font-bold transition-all shadow-xs flex items-center gap-2 self-start sm:self-auto"
    >
      <span class="material-symbols-outlined text-base">add</span>
      <span>Tambah Jadwal Baru</span>
    </button>
  </div>

  <!-- 2-Column Main Layout: Left Schedule Selector & Right Duty Allocation Deck -->
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Left Column: List of Schedules -->
    <div class="flex flex-col gap-3">
      <div class="flex items-center justify-between">
        <h2 class="text-xs uppercase font-bold text-on-surface-variant tracking-wider">
          Pilih Jadwal Misa (<?= count($all_schedules) ?>)
        </h2>
      </div>

      <div class="flex flex-col gap-2.5 max-h-[750px] overflow-y-auto pr-1">
        <?php foreach ($all_schedules as $s): 
          $isSelected = ($selected_schedule && $selected_schedule['id'] == $s['id']);
          $warna_badge = match($s['warna_liturgi']) {
            'Putih' => 'badge-liturgi-putih',
            'Merah' => 'badge-liturgi-merah',
            'Ungu' => 'badge-liturgi-ungu',
            'Merah Muda' => 'badge-liturgi-merah-muda',
            default => 'badge-liturgi-hijau'
          };
        ?>
        <a
          href="?schedule_id=<?= $s['id'] ?>"
          class="p-4 rounded-2xl border text-xs transition-all block text-left <?= $isSelected ? 'bg-surface-container-lowest border-primary shadow-md ring-1 ring-primary/30' : 'bg-surface-container-low border-surface-container hover:bg-surface-container-lowest' ?>"
        >
          <div class="flex items-center justify-between gap-2 mb-1.5">
            <span class="text-[10px] font-semibold text-secondary uppercase tracking-wider px-2 py-0.5 rounded-full bg-secondary-fixed/50">
              <?= $s['kategori'] === 'harian' ? 'Harian' : 'Mingguan' ?>
            </span>
            <span class="badge-pill <?= $warna_badge ?> text-[10px]">
              <?= $s['warna_liturgi'] ?>
            </span>
          </div>

          <h3 class="font-headline font-bold text-primary text-sm truncate"><?= htmlspecialchars($s['judul_misa']) ?></h3>
          
          <div class="flex items-center justify-between text-on-surface-variant text-[11px] mt-2">
            <span>📅 <?= format_indo_date($s['tanggal_misa']) ?></span>
            <span class="font-bold text-on-surface">⏰ <?= substr($s['jam_mulai'], 0, 5) ?> WIB</span>
          </div>

          <div class="mt-2.5 flex items-center justify-between text-[11px]">
            <span class="font-semibold <?= $s['terisi'] >= $s['kuota_maksimal'] ? 'text-error' : 'text-primary' ?>">
              <?= $s['terisi'] ?> / <?= $s['kuota_maksimal'] ?> Petugas
            </span>
            <span class="text-[10px] text-outline">Minggu ke-<?= $s['minggu_ke'] ?></span>
          </div>
        </a>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Right Column: Selected Schedule Details & Roster Deck -->
    <div class="lg:col-span-2 flex flex-col gap-5">
      <?php if ($selected_schedule): 
        $warna_badge = match($selected_schedule['warna_liturgi']) {
          'Putih' => 'badge-liturgi-putih',
          'Merah' => 'badge-liturgi-merah',
          'Ungu' => 'badge-liturgi-ungu',
          'Merah Muda' => 'badge-liturgi-merah-muda',
          default => 'badge-liturgi-hijau'
        };
      ?>
      <div class="p-6 rounded-3xl bg-surface-container-lowest border border-surface-container shadow-xs flex flex-col gap-6">
        <!-- Selected Schedule Banner -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-5 rounded-2xl bg-surface-container-low border border-surface-container">
          <div class="flex flex-col gap-1.5">
            <div class="flex items-center gap-2">
              <span class="badge-pill <?= $warna_badge ?> text-xs">
                Liturgi <?= $selected_schedule['warna_liturgi'] ?>
              </span>
              <span class="text-xs font-semibold text-secondary uppercase tracking-wider">
                Misa <?= ucfirst($selected_schedule['kategori']) ?>
              </span>
            </div>
            <h2 class="font-headline font-bold text-xl text-primary">
              <?= htmlspecialchars($selected_schedule['judul_misa']) ?>
            </h2>
            <div class="flex items-center gap-2 text-xs text-on-surface-variant">
              <span>📅 <?= format_indo_date($selected_schedule['tanggal_misa']) ?></span>
              <span>•</span>
              <span class="font-bold text-on-surface">⏰ <?= substr($selected_schedule['jam_mulai'], 0, 5) ?> WIB</span>
              <span>•</span>
              <span>Kuota Maks: <?= $selected_schedule['kuota_maksimal'] ?></span>
            </div>
          </div>

          <div class="flex items-center gap-2 self-start sm:self-auto">
            <button
              type="button"
              onclick="openModal('modal-assign-member')"
              class="px-3.5 py-2 rounded-xl bg-primary text-on-primary hover:bg-primary-container text-xs font-bold transition-colors flex items-center gap-1.5"
            >
              <span class="material-symbols-outlined text-sm">person_add</span>
              <span>Tambah Petugas</span>
            </button>
            <form method="POST" action="" onsubmit="return confirm('Hapus jadwal misa ini beserta seluruh pendaftaran di dalamnya?')">
              <input type="hidden" name="action" value="delete_schedule">
              <input type="hidden" name="schedule_id" value="<?= $selected_schedule['id'] ?>">
              <button type="submit" class="w-9 h-9 rounded-xl flex items-center justify-center bg-error-container text-error hover:bg-red-200 transition-colors" title="Hapus Jadwal">
                <span class="material-symbols-outlined text-base">delete</span>
              </button>
            </form>
          </div>
        </div>

        <!-- Roster List Table -->
        <div class="flex flex-col gap-3">
          <div class="flex items-center justify-between">
            <h3 class="font-headline font-bold text-sm text-primary flex items-center gap-2">
              <span class="material-symbols-outlined text-base">group</span>
              <span>Daftar Misdinar Bertugas (<?= count($roster) ?> / <?= $selected_schedule['kuota_maksimal'] ?>)</span>
            </h3>
            <span class="text-[11px] text-on-surface-variant">Batas Toleransi Presensi: +45 Menit</span>
          </div>

          <?php if (empty($roster)): ?>
            <div class="py-12 text-center text-xs text-on-surface-variant flex flex-col items-center gap-2 rounded-2xl bg-surface-container-low border border-dashed border-outline-variant">
              <span class="material-symbols-outlined text-3xl opacity-40">person_off</span>
              <span>Belum ada misdinar teralokasi pada jadwal ini. Klik 'Tambah Petugas' di atas.</span>
            </div>
          <?php else: ?>
            <div class="overflow-x-auto">
              <table class="w-full text-left text-xs border-collapse">
                <thead>
                  <tr class="border-b border-surface-container text-on-surface-variant font-semibold">
                    <th class="py-2.5 px-3">Nama Misdinar</th>
                    <th class="py-2.5 px-3">Status</th>
                    <th class="py-2.5 px-3 text-right">Aksi</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-surface-container">
                  <?php foreach ($roster as $r): 
                    $st = $r['status_kehadiran'];
                    $st_cls = match($st) {
                      'hadir' => 'bg-emerald-100 text-emerald-800',
                      'terlambat' => 'bg-amber-100 text-amber-800',
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
                          <div class="text-[10px] text-on-surface-variant">📱 <?= htmlspecialchars($r['no_telepon']) ?> • <?= htmlspecialchars($r['asal_wilayah_teks']) ?></div>
                        </div>
                      </div>
                    </td>
                    <td class="py-3 px-3">
                      <span class="px-2 py-0.5 rounded-full text-[10px] font-bold <?= $st_cls ?> uppercase">
                        <?= $st ?>
                      </span>
                    </td>
                    <td class="py-3 px-3 text-right">
                      <div class="inline-flex items-center gap-1.5">
                        <a
                          href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $r['no_telepon']) ?>?text=<?= rawurlencode("Halo {$r['nama_panggilan']}, pengurus sakristi mengingatkan tugas altar di {$selected_schedule['judul_misa']} pada " . format_indo_date($selected_schedule['tanggal_misa']) . " pukul " . substr($selected_schedule['jam_mulai'], 0, 5) . " WIB. Harap hadir H-30 menit!") ?>"
                          target="_blank"
                          class="w-7 h-7 rounded-lg flex items-center justify-center bg-emerald-100 text-emerald-800 hover:bg-emerald-200 transition-colors"
                          title="Kirim Pengingat WhatsApp"
                        >
                          <span class="material-symbols-outlined text-sm">chat</span>
                        </a>
                        <form method="POST" action="" onsubmit="return confirm('Batalkan penugasan misdinar ini?')" class="inline">
                          <input type="hidden" name="action" value="remove_booking">
                          <input type="hidden" name="booking_id" value="<?= $r['id'] ?>">
                          <input type="hidden" name="schedule_id" value="<?= $selected_schedule['id'] ?>">
                          <button type="submit" class="w-7 h-7 rounded-lg flex items-center justify-center bg-surface-container-high hover:bg-error-container hover:text-error text-on-surface-variant transition-colors" title="Batalkan Tugas">
                            <span class="material-symbols-outlined text-sm">close</span>
                          </button>
                        </form>
                      </div>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </div>
      <?php else: ?>
        <div class="p-12 rounded-3xl bg-surface-container-lowest border border-surface-container text-center text-xs text-on-surface-variant">
          Pilih salah satu jadwal misa di sebelah kiri untuk melihat dan mengatur petugas altar.
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Modal Tambah Jadwal Misa Baru -->
<div id="modal-add-schedule" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs hidden items-center justify-center p-4">
  <div class="bg-surface-container-lowest border border-surface-container rounded-3xl max-w-md w-full shadow-2xl overflow-hidden animate-scaleUp">
    <div class="p-5 border-b border-surface-container flex items-center justify-between bg-surface-container-low">
      <div class="flex items-center gap-2">
        <span class="material-symbols-outlined text-primary text-xl">add_circle</span>
        <h3 class="font-headline font-bold text-base text-primary">Tambah Jadwal Misa Baru</h3>
      </div>
      <button type="button" onclick="closeModal('modal-add-schedule')" class="w-8 h-8 rounded-full flex items-center justify-center hover:bg-surface-container text-on-surface-variant">
        <span class="material-symbols-outlined text-lg">close</span>
      </button>
    </div>

    <form method="POST" action="" class="p-5 flex flex-col gap-3.5 text-xs">
      <input type="hidden" name="action" value="create_schedule">
      
      <div>
        <label class="block font-semibold text-on-surface-variant mb-1">Judul Misa *</label>
        <input type="text" name="judul_misa" required placeholder="Contoh: Misa Mingguan Pagi I" class="w-full px-3 py-2 rounded-xl border border-outline-variant bg-surface-container-lowest text-on-surface text-xs focus:outline-none focus:ring-2 focus:ring-primary/20">
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block font-semibold text-on-surface-variant mb-1">Kategori *</label>
          <select name="kategori" class="w-full px-3 py-2 rounded-xl border border-outline-variant bg-surface-container-lowest text-on-surface text-xs">
            <option value="mingguan">Mingguan</option>
            <option value="harian">Harian</option>
          </select>
        </div>
        <div>
          <label class="block font-semibold text-on-surface-variant mb-1">Warna Liturgi *</label>
          <select name="warna_liturgi" class="w-full px-3 py-2 rounded-xl border border-outline-variant bg-surface-container-lowest text-on-surface text-xs">
            <option value="Hijau">Hijau</option>
            <option value="Putih">Putih</option>
            <option value="Merah">Merah</option>
            <option value="Ungu">Ungu</option>
            <option value="Merah Muda">Merah Muda</option>
          </select>
        </div>
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block font-semibold text-on-surface-variant mb-1">Tanggal Misa *</label>
          <input type="date" name="tanggal_misa" required value="<?= date('Y-m-d') ?>" class="w-full px-3 py-2 rounded-xl border border-outline-variant bg-surface-container-lowest text-on-surface text-xs">
        </div>
        <div>
          <label class="block font-semibold text-on-surface-variant mb-1">Jam Mulai (WIB) *</label>
          <input type="time" name="jam_mulai" required value="06:30" class="w-full px-3 py-2 rounded-xl border border-outline-variant bg-surface-container-lowest text-on-surface text-xs">
        </div>
      </div>

      <div>
        <label class="block font-semibold text-on-surface-variant mb-1">Kuota Maksimal Petugas *</label>
        <input type="number" name="kuota_maksimal" required min="1" max="30" value="12" class="w-full px-3 py-2 rounded-xl border border-outline-variant bg-surface-container-lowest text-on-surface text-xs">
      </div>

      <div class="p-3 border-t border-surface-container mt-2 flex justify-end gap-2">
        <button type="button" onclick="closeModal('modal-add-schedule')" class="px-4 py-2 rounded-xl bg-surface-container-high text-on-surface font-semibold">Batal</button>
        <button type="submit" class="px-4 py-2 rounded-xl bg-primary text-on-primary font-bold hover:bg-primary-container transition-colors">Simpan Jadwal</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Tambah Petugas Manual -->
<div id="modal-assign-member" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs hidden items-center justify-center p-4">
  <div class="bg-surface-container-lowest border border-surface-container rounded-3xl max-w-md w-full shadow-2xl overflow-hidden animate-scaleUp">
    <div class="p-5 border-b border-surface-container flex items-center justify-between bg-surface-container-low">
      <div class="flex items-center gap-2">
        <span class="material-symbols-outlined text-primary text-xl">person_add</span>
        <h3 class="font-headline font-bold text-base text-primary">Tugaskan Petugas Altar</h3>
      </div>
      <button type="button" onclick="closeModal('modal-assign-member')" class="w-8 h-8 rounded-full flex items-center justify-center hover:bg-surface-container text-on-surface-variant">
        <span class="material-symbols-outlined text-lg">close</span>
      </button>
    </div>

    <form method="POST" action="" class="p-5 flex flex-col gap-3.5 text-xs">
      <input type="hidden" name="action" value="assign_member">
      <input type="hidden" name="schedule_id" value="<?= $selected_schedule['id'] ?? 0 ?>">

      <div>
        <label class="block font-semibold text-on-surface-variant mb-1">Pilih Misdinar Aktif *</label>
        <select name="user_id" required class="w-full px-3 py-2.5 rounded-xl border border-outline-variant bg-surface-container-lowest text-on-surface text-xs">
          <option value="">-- Pilih Anggota --</option>
          <?php foreach ($active_members as $m): ?>
            <option value="<?= $m['id'] ?>">
              <?= htmlspecialchars($m['nama_lengkap']) ?> (<?= htmlspecialchars($m['nama_panggilan']) ?> - <?= htmlspecialchars($m['asal_wilayah_teks']) ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <input type="hidden" name="posisi_tugas" value="Petugas Altar">

      <div class="p-3 border-t border-surface-container mt-2 flex justify-end gap-2">
        <button type="button" onclick="closeModal('modal-assign-member')" class="px-4 py-2 rounded-xl bg-surface-container-high text-on-surface font-semibold">Batal</button>
        <button type="submit" class="px-4 py-2 rounded-xl bg-primary text-on-primary font-bold hover:bg-primary-container transition-colors">Tugaskan Sekarang</button>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
