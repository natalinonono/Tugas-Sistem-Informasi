<?php
/**
 * Manajemen Jadwal Misa & Penugasan Altar (Pengurus)
 * SiDinar Regina - Sistem Informasi & Presensi Putra-Putri Altar
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_pengurus();

$pdo = get_db();

// Auto-update status pendaftaran yang telah melewati batas toleransi menjadi alpa
auto_update_expired_attendance($pdo);
$flash_msg = null;
$flash_type = 'success';

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
        $selebran = trim($_POST['nama_selebran'] ?? '');
        $tahun_liturgi = trim($_POST['tahun_liturgi'] ?? 'Tahun B / Liturgi Biasa');

        if ($judul && $tanggal && $jam) {
            $minggu_ke = (int)date('W', strtotime($tanggal));
            $tahun = (int)date('Y', strtotime($tanggal));
            $token = md5(uniqid("misa-{$tanggal}-", true));

            $stmt = $pdo->prepare("
                INSERT INTO mass_schedules 
                (judul_misa, kategori, tanggal_misa, jam_mulai, kuota_maksimal, minggu_ke, tahun, tahun_liturgi, warna_liturgi, nama_selebran, qr_token)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$judul, $kategori, $tanggal, $jam, $kuota, $minggu_ke, $tahun, $tahun_liturgi, $warna, $selebran, $token]);
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
            // Cek apakah user sudah terdaftar di misa ini
            $chk = $pdo->prepare("SELECT id FROM bookings WHERE schedule_id = ? AND user_id = ?");
            $chk->execute([$schedule_id, $user_id]);
            if ($chk->fetch()) {
                set_flash('error', 'Misdinar tersebut sudah terdaftar pada jadwal misa ini.');
            } else {
                // Ambil info jadwal
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
            header("Location: jadwal.php?view_roster={$schedule_id}");
            exit;
        }
    }

    // 4. Hapus / Batal Penugasan Petugas
    if ($action === 'remove_booking') {
        $booking_id = (int)($_POST['booking_id'] ?? 0);
        $schedule_id = (int)($_POST['schedule_id'] ?? 0);
        if ($booking_id) {
            $stmt = $pdo->prepare("DELETE FROM bookings WHERE id = ?");
            $stmt->execute([$booking_id]);
            set_flash('success', 'Penugasan petugas berhasil dibatalkan.');
            header("Location: jadwal.php?view_roster={$schedule_id}");
            exit;
        }
    }
}

// Filter Parameter
$kategori_filter = $_GET['kategori'] ?? 'semua';
$search_query = trim($_GET['q'] ?? '');

$sql = "
    SELECT s.*, 
           COUNT(b.id) as terisi,
           SUM(CASE WHEN b.status_kehadiran IN ('hadir', 'terlambat') THEN 1 ELSE 0 END) as hadir_count
    FROM mass_schedules s
    LEFT JOIN bookings b ON s.id = b.schedule_id
    WHERE 1=1
";
$params = [];

if ($kategori_filter === 'harian') {
    $sql .= " AND s.kategori = 'harian'";
} elseif ($kategori_filter === 'mingguan') {
    $sql .= " AND s.kategori = 'mingguan'";
}

if ($search_query !== '') {
    $sql .= " AND s.judul_misa LIKE ?";
    $params[] = "%{$search_query}%";
}

$sql .= " GROUP BY s.id ORDER BY s.tanggal_misa ASC, s.jam_mulai ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$schedules = $stmt->fetchAll();

// Ambil daftar anggota aktif untuk pilihan modal tambah petugas
$members_stmt = $pdo->query("SELECT id, nama_lengkap, nama_panggilan, jenjang FROM profiles WHERE status = 'aktif' ORDER BY nama_panggilan ASC");
$active_members = $members_stmt->fetchAll();

// Cek apakah ada jadwal spesifik yang sedang dibuka rostrenya
$active_roster_id = (int)($_GET['view_roster'] ?? 0);
$roster_schedule = null;
$roster_bookings = [];

if ($active_roster_id) {
    $rStmt = $pdo->prepare("SELECT * FROM mass_schedules WHERE id = ?");
    $rStmt->execute([$active_roster_id]);
    $roster_schedule = $rStmt->fetch();

    if ($roster_schedule) {
        $bStmt = $pdo->prepare("
            SELECT b.*, p.nama_lengkap, p.nama_panggilan, p.no_telepon, p.jenjang
            FROM bookings b
            JOIN profiles p ON b.user_id = p.id
            WHERE b.schedule_id = ?
            ORDER BY b.posisi_tugas ASC, p.nama_panggilan ASC
        ");
        $bStmt->execute([$active_roster_id]);
        $roster_bookings = $bStmt->fetchAll();
    }
}

$page_title = 'Kelola Jadwal Misa & Penugasan';
require_once __DIR__ . '/../includes/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem;">
  <div>
    <h1 style="font-size: 1.6rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.25rem;">
      📅 Manajemen Jadwal Misa & Petugas
    </h1>
    <p style="color: var(--text-muted); font-size: 0.95rem;">
      Atur jadwal kalender liturgi, alokasi penugasan misdinar, dan broadcast pengingat WhatsApp.
    </p>
  </div>

  <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
    <button type="button" class="btn btn-primary" onclick="openModal('modal-tambah-jadwal')">
      ➕ Tambah Jadwal Misa
    </button>
    <button type="button" class="btn btn-secondary" id="btn-generate-schedules" onclick="triggerAutoGenerate()">
      ⚡ Auto-Generate 30 Hari
    </button>
  </div>
</div>

<!-- Filter Bar -->
<div class="card" style="margin-bottom: 1.5rem; padding: 1rem 1.25rem;">
  <form method="GET" action="jadwal.php" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: center; justify-content: space-between;">
    <div style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
      <span style="font-weight: 600; font-size: 0.9rem; color: var(--text-muted);">Kategori:</span>
      <a href="jadwal.php?kategori=semua" class="btn btn-sm <?= $kategori_filter === 'semua' ? 'btn-primary' : 'btn-secondary' ?>">Semua</a>
      <a href="jadwal.php?kategori=harian" class="btn btn-sm <?= $kategori_filter === 'harian' ? 'btn-primary' : 'btn-secondary' ?>">Misa Harian</a>
      <a href="jadwal.php?kategori=mingguan" class="btn btn-sm <?= $kategori_filter === 'mingguan' ? 'btn-primary' : 'btn-secondary' ?>">Misa Mingguan</a>
    </div>

    <div style="display: flex; gap: 0.5rem; align-items: center; flex: 1; max-width: 380px;">
      <input type="text" name="q" class="form-input" placeholder="Cari perayaan misa..." value="<?= htmlspecialchars($search_query) ?>" style="padding: 0.4rem 0.75rem; font-size: 0.88rem;">
      <button type="submit" class="btn btn-secondary btn-sm">Cari</button>
      <?php if ($search_query || $kategori_filter !== 'semua'): ?>
        <a href="jadwal.php" class="btn btn-secondary btn-sm" title="Reset filter">Reset</a>
      <?php endif; ?>
    </div>
  </form>
</div>

<!-- Daftar Jadwal Misa -->
<?php if (empty($schedules)): ?>
  <div class="card" style="text-align: center; padding: 3rem 1.5rem;">
    <div style="font-size: 3rem; margin-bottom: 0.75rem;">⛪</div>
    <h3 style="font-size: 1.2rem; font-weight: 700; margin-bottom: 0.5rem;">Tidak Ada Jadwal Misa</h3>
    <p style="color: var(--text-muted); max-width: 450px; margin: 0 auto 1.5rem auto;">
      Belum ada jadwal misa yang sesuai dengan filter. Anda dapat menambahkan jadwal baru atau mengklik tombol <strong>Auto-Generate 30 Hari</strong> untuk membuat otomatis.
    </p>
    <button type="button" class="btn btn-primary" onclick="triggerAutoGenerate()">
      ⚡ Generate Jadwal 30 Hari Sekarang
    </button>
  </div>
<?php else: ?>
  <div class="grid" style="grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 1.25rem;">
    <?php foreach ($schedules as $item): ?>
      <?php 
        $persen = $item['kuota_maksimal'] > 0 ? min(100, round(($item['terisi'] / $item['kuota_maksimal']) * 100)) : 0;
        $isPast = strtotime($item['tanggal_misa'] . ' ' . $item['jam_mulai']) < time();
      ?>
      <div class="card" style="display: flex; flex-direction: column; justify-content: space-between; border-left: 4px solid var(--primary); opacity: <?= $isPast ? '0.85' : '1' ?>;">
        <div>
          <!-- Header Card -->
          <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 0.5rem; margin-bottom: 0.75rem;">
            <div>
              <span class="badge <?= $item['kategori'] === 'harian' ? 'badge-blue' : 'badge-amber' ?>" style="font-size: 0.72rem; text-transform: uppercase;">
                <?= $item['kategori'] === 'harian' ? 'Harian' : 'Mingguan' ?>
              </span>
            </div>
            <span style="font-size: 0.8rem; font-weight: 600; color: var(--text-dim);">
              M-<?= $item['minggu_ke'] ?> / <?= $item['tahun'] ?>
            </span>
          </div>

          <h3 style="font-size: 1.15rem; font-weight: 700; margin-bottom: 0.4rem; color: var(--text-main);">
            <?= htmlspecialchars($item['judul_misa']) ?>
          </h3>

          <div style="display: flex; flex-direction: column; gap: 0.35rem; font-size: 0.88rem; color: var(--text-muted); margin-bottom: 1rem;">
            <div>📅 <strong><?= format_indo_date($item['tanggal_misa']) ?></strong></div>
            <div>⏰ Pukul <?= format_indo_time($item['jam_mulai']) ?></div>
          </div>

          <!-- Progress Kuota Petugas -->
          <div style="background: var(--surface-low); padding: 0.75rem; border-radius: var(--radius-sm); margin-bottom: 1rem;">
            <div style="display: flex; justify-content: space-between; font-size: 0.82rem; margin-bottom: 0.4rem;">
              <span style="font-weight: 600;">Petugas Terdaftar:</span>
              <span style="font-weight: 700; color: <?= $persen >= 100 ? 'var(--emerald)' : 'var(--primary)' ?>;">
                <?= $item['terisi'] ?> / <?= $item['kuota_maksimal'] ?> Misdinar
              </span>
            </div>
            <div style="width: 100%; height: 6px; background: var(--border); border-radius: 3px; overflow: hidden;">
              <div style="width: <?= $persen ?>%; height: 100%; background: <?= $persen >= 100 ? 'var(--emerald)' : 'var(--primary)' ?>; transition: width 0.3s ease;"></div>
            </div>
            <div style="display: flex; justify-content: space-between; font-size: 0.78rem; color: var(--text-dim); margin-top: 0.35rem;">
              <span>Kehadiran: <?= (int)$item['hadir_count'] ?> hadir</span>
              <span><?= $item['kuota_maksimal'] - $item['terisi'] > 0 ? ($item['kuota_maksimal'] - $item['terisi']) . ' slot kosong' : 'Kuota Penuh' ?></span>
            </div>
          </div>
        </div>

        <!-- Footer Card Action Buttons -->
        <div style="border-top: 1px solid var(--border-light); padding-top: 0.75rem; display: flex; flex-direction: column; gap: 0.5rem;">
          <div style="display: flex; gap: 0.4rem;">
            <a href="jadwal.php?view_roster=<?= $item['id'] ?>" class="btn btn-primary btn-sm" style="flex: 1; text-align: center;">
              👥 Roster Petugas (<?= $item['terisi'] ?>)
            </a>
            <a href="cetak_jadwal.php?id=<?= $item['id'] ?>" target="_blank" class="btn btn-secondary btn-sm" title="Cetak Lembar Penugasan Altar">
              🖨️ Cetak
            </a>
          </div>

          <div style="display: flex; justify-content: space-between; align-items: center; gap: 0.5rem;">
            <button type="button" class="btn btn-secondary btn-sm" style="font-size: 0.78rem; flex: 1;" onclick="remindAllMembers(<?= $item['id'] ?>)">
              📢 Ingatkan Semua WA
            </button>
            <form method="POST" action="jadwal.php" onsubmit="return confirm('Apakah Anda yakin ingin menghapus jadwal ini beserta penugasan yang ada?');" style="margin: 0;">
              <input type="hidden" name="action" value="delete_schedule">
              <input type="hidden" name="schedule_id" value="<?= $item['id'] ?>">
              <button type="submit" class="btn btn-secondary btn-sm" style="color: var(--error);" title="Hapus Jadwal">
                🗑️
              </button>
            </form>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<!-- ======================================================== -->
<!-- MODAL: Tambah Jadwal Misa Baru                           -->
<!-- ======================================================== -->
<div class="modal" id="modal-tambah-jadwal">
  <div class="modal-card" style="max-width: 540px;">
    <div class="modal-header">
      <div class="modal-title">➕ Tambah Jadwal Misa Baru</div>
      <button type="button" class="btn-icon" onclick="closeModal('modal-tambah-jadwal')">&times;</button>
    </div>
    <form method="POST" action="jadwal.php">
      <input type="hidden" name="action" value="create_schedule">
      <div class="modal-body" style="display: flex; flex-direction: column; gap: 1rem;">
        <div>
          <label class="form-label">Judul / Perayaan Misa *</label>
          <input type="text" name="judul_misa" class="form-input" placeholder="Contoh: Misa Hari Minggu Pagi I" required>
        </div>

        <input type="hidden" name="warna_liturgi" value="Hijau">
        <div>
          <label class="form-label">Kategori Misa *</label>
          <select name="kategori" class="form-select" required>
            <option value="mingguan">Misa Mingguan</option>
            <option value="harian">Misa Harian</option>
          </select>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
          <div>
            <label class="form-label">Tanggal Misa *</label>
            <input type="date" name="tanggal_misa" class="form-input" value="<?= date('Y-m-d') ?>" required>
          </div>
          <div>
            <label class="form-label">Jam Mulai *</label>
            <input type="time" name="jam_mulai" class="form-input" value="06:30" required>
          </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
          <div>
            <label class="form-label">Kuota Misdinar *</label>
            <input type="number" name="kuota_maksimal" class="form-input" value="12" min="1" max="50" required>
          </div>
          <div>
            <label class="form-label">Tahun Liturgi</label>
            <input type="text" name="tahun_liturgi" class="form-input" value="Tahun B / Liturgi Biasa">
          </div>
        </div>

        <input type="hidden" name="nama_selebran" value="">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-tambah-jadwal')">Batal</button>
        <button type="submit" class="btn btn-primary">Simpan Jadwal</button>
      </div>
    </form>
  </div>
</div>

<!-- ======================================================== -->
<!-- MODAL: Kelola Roster Petugas Misdinar                    -->
<!-- ======================================================== -->
<?php if ($roster_schedule): ?>
<div class="modal active" id="modal-roster-view">
  <div class="modal-card" style="max-width: 720px;">
    <div class="modal-header">
      <div>
        <div class="modal-title">👥 Roster Petugas Altar</div>
        <div style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.2rem;">
          <?= htmlspecialchars($roster_schedule['judul_misa']) ?> &bull; <?= format_indo_date($roster_schedule['tanggal_misa']) ?> (<?= format_indo_time($roster_schedule['jam_mulai']) ?>)
        </div>
      </div>
      <a href="jadwal.php" class="btn-icon" style="text-decoration: none; text-align: center; line-height: 28px;">&times;</a>
    </div>

    <div class="modal-body">
      <!-- Section Form Tambah Petugas Manual -->
      <div style="background: var(--surface-low); padding: 1rem; border-radius: var(--radius-md); margin-bottom: 1.5rem;">
        <h4 style="font-size: 0.95rem; font-weight: 700; margin-bottom: 0.75rem;">➕ Alokasikan Petugas Misdinar</h4>
        <form method="POST" action="jadwal.php" style="display: grid; grid-template-columns: 1.5fr 1.2fr auto; gap: 0.75rem; align-items: flex-end;">
          <input type="hidden" name="action" value="assign_member">
          <input type="hidden" name="schedule_id" value="<?= $roster_schedule['id'] ?>">

          <div>
            <label class="form-label" style="font-size: 0.8rem;">Pilih Misdinar Aktif</label>
            <select name="user_id" class="form-select" required>
              <option value="">-- Pilih Misdinar --</option>
              <?php foreach ($active_members as $m): ?>
                <option value="<?= $m['id'] ?>">
                  <?= htmlspecialchars($m['nama_panggilan']) ?> - <?= htmlspecialchars($m['nama_lengkap']) ?> (<?= $m['jenjang'] ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div>
            <label class="form-label" style="font-size: 0.8rem;">Posisi Tugas Liturgi</label>
            <select name="posisi_tugas" class="form-select" required>
              <?php foreach (LITURGICAL_POSITIONS as $pos): ?>
                <option value="<?= $pos ?>"><?= $pos ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <button type="submit" class="btn btn-primary" style="height: 38px;">Tugaskan</button>
        </form>
      </div>

      <!-- Tabel Daftar Petugas -->
      <h4 style="font-size: 0.95rem; font-weight: 700; margin-bottom: 0.75rem; display: flex; justify-content: space-between; align-items: center;">
        <span>Daftar Petugas Terdaftar (<?= count($roster_bookings) ?> / <?= $roster_schedule['kuota_maksimal'] ?>)</span>
        <button type="button" class="btn btn-secondary btn-sm" onclick="remindAllMembers(<?= $roster_schedule['id'] ?>)">
          📢 Ingatkan Semua WA
        </button>
      </h4>

      <?php if (empty($roster_bookings)): ?>
        <p style="color: var(--text-muted); font-size: 0.9rem; text-align: center; padding: 2rem 1rem;">
          Belum ada misdinar yang terdaftar pada jadwal ini. Anda dapat menambahkan manual melalui formulir di atas atau menunggu anggota mendaftar mandiri.
        </p>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table">
            <thead>
              <tr>
                <th>No</th>
                <th>Nama Misdinar</th>
                <th>Posisi Tugas</th>
                <th>Status Presensi</th>
                <th>Aksi & Pengingat</th>
              </tr>
            </thead>
            <tbody>
              <?php $no = 1; foreach ($roster_bookings as $b): ?>
                <?php 
                  $waData = generate_whatsapp_reminder_link($roster_schedule, $b);
                ?>
                <tr>
                  <td><?= $no++ ?></td>
                  <td>
                    <strong><?= htmlspecialchars($b['nama_panggilan']) ?></strong>
                    <div style="font-size: 0.78rem; color: var(--text-dim);"><?= htmlspecialchars($b['nama_lengkap']) ?> (<?= $b['jenjang'] ?>)</div>
                  </td>
                  <td>
                    <span class="badge badge-light" style="font-weight: 600;">
                      <?= htmlspecialchars($b['posisi_tugas']) ?>
                    </span>
                  </td>
                  <td>
                    <?= get_attendance_badge($b['status_kehadiran']) ?>
                    <?php if ($b['waktu_checkin']): ?>
                      <div style="font-size: 0.75rem; color: var(--text-dim); margin-top: 2px;">
                        <?= date('H:i', strtotime($b['waktu_checkin'])) ?> WIB
                      </div>
                    <?php endif; ?>
                  </td>
                  <td>
                    <div style="display: flex; gap: 0.4rem; align-items: center;">
                      <!-- Link WA Reminder -->
                      <a href="<?= $waData['url'] ?>" target="_blank" class="btn btn-secondary btn-sm" style="color: #25D366;" title="Kirim Chat WhatsApp Manual">
                        📱 WA
                      </a>

                      <!-- Broadcast Bell Reminder -->
                      <button type="button" class="btn btn-secondary btn-sm" onclick="remindSingleBooking(<?= $b['id'] ?>)" title="Kirim Notifikasi Lonceng ke Akun Misdinar">
                        🔔 Notif
                      </button>

                      <!-- Hapus Booking -->
                      <form method="POST" action="jadwal.php" onsubmit="return confirm('Hapus penugasan misdinar ini?');" style="margin: 0;">
                        <input type="hidden" name="action" value="remove_booking">
                        <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                        <input type="hidden" name="schedule_id" value="<?= $roster_schedule['id'] ?>">
                        <button type="submit" class="btn btn-secondary btn-sm" style="color: var(--error);" title="Batal Tugas">
                          ✕
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

    <div class="modal-footer">
      <a href="cetak_jadwal.php?id=<?= $roster_schedule['id'] ?>" target="_blank" class="btn btn-secondary">
        🖨️ Cetak Lembar Tugas
      </a>
      <a href="jadwal.php" class="btn btn-primary">Selesai</a>
    </div>
  </div>
</div>
<?php endif; ?>

<script>
// 1. AJAX Broadcast Remind All
function remindAllMembers(scheduleId) {
  if (!confirm('Kirim notifikasi pengingat ke seluruh misdinar yang terdaftar pada misa ini?')) return;

  fetch(getApiUrl('reminder.php'), {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'schedule_id=' + encodeURIComponent(scheduleId)
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      playNotificationChime();
      alert('✅ ' + data.message);
    } else {
      alert('⚠️ Gagal: ' + (data.error || 'Terjadi kesalahan sistem'));
    }
  })
  .catch(err => {
    alert('⚠️ Gagal menghubungi server.');
  });
}

// 2. AJAX Broadcast Single Booking
function remindSingleBooking(bookingId) {
  fetch(getApiUrl('reminder.php'), {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'booking_id=' + encodeURIComponent(bookingId)
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      playNotificationChime();
      alert('✅ ' + data.message);
    } else {
      alert('⚠️ Gagal: ' + (data.error || 'Terjadi kesalahan'));
    }
  })
  .catch(() => alert('⚠️ Gagal memproses pengingat.'));
}

// 3. Auto Generate 30 Hari
function triggerAutoGenerate() {
  if (!confirm('Apakah Anda ingin men-generate jadwal misa harian dan mingguan otomatis untuk 30 hari ke depan?')) return;

  const btn = document.getElementById('btn-generate-schedules');
  if (btn) btn.disabled = true;

  fetch(getApiUrl('generate_schedules.php'), { method: 'POST' })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        alert('🎉 ' + data.message);
        window.location.reload();
      } else {
        alert('⚠️ Gagal: ' + (data.error || 'Terjadi masalah'));
        if (btn) btn.disabled = false;
      }
    })
    .catch(() => {
      alert('⚠️ Gagal menghubungi endpoint generator.');
      if (btn) btn.disabled = false;
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
