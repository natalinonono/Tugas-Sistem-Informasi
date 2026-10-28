<?php
/**
 * Presensi Sakristi & QR Code Station (Pengurus)
 * SiDinar Regina - Sistem Informasi & Presensi Putra-Putri Altar
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_pengurus();

$pdo = get_db();

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

            set_flash('success', "Status presensi berhasil diubah menjadi: " . strtoupper($new_status));
            header("Location: presensi.php?schedule_id={$schedule_id}");
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

// Tentukan jadwal yang dipilih (default jadwal teratas / hari ini)
$selected_id = (int)($_GET['schedule_id'] ?? 0);
if (!$selected_id && !empty($all_schedules)) {
    // Cari yang paling dekat dengan hari ini
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

$selected_schedule = null;
$roster = [];
$stats = [
    'total' => 0,
    'hadir' => 0,
    'terlambat' => 0,
    'izin' => 0,
    'alpa' => 0,
    'terdaftar' => 0
];

if ($selected_id) {
    $stmt = $pdo->prepare("SELECT * FROM mass_schedules WHERE id = ?");
    $stmt->execute([$selected_id]);
    $selected_schedule = $stmt->fetch();

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

        $stats['total'] = count($roster);
        foreach ($roster as $r) {
            $st = $r['status_kehadiran'] ?? 'terdaftar';
            if (isset($stats[$st])) {
                $stats[$st]++;
            }
        }
    }
}

$qr_token = $selected_schedule['qr_token'] ?? ('misa-' . ($selected_schedule['id'] ?? 0));
$qr_api_url = "https://api.qrserver.com/v1/create-qr-code/?size=260x260&margin=10&data=" . urlencode($qr_token);

$page_title = 'Presensi Sakristi & QR Code';
require_once __DIR__ . '/../includes/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem;">
  <div>
    <h1 style="font-size: 1.6rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.25rem;">
      📋 Presensi Sakristi & Station QR Check-in
    </h1>
    <p style="color: var(--text-muted); font-size: 0.95rem;">
      Tampilkan QR Code untuk dipindai oleh misdinar di sakristi, atau kelola status kehadiran secara real-time.
    </p>
  </div>

  <div style="display: flex; gap: 0.5rem; align-items: center;">
    <label style="font-weight: 600; font-size: 0.9rem; color: var(--text-muted);">Pilih Misa:</label>
    <select class="form-select" style="min-width: 260px;" onchange="window.location.href='presensi.php?schedule_id=' + this.value">
      <?php foreach ($all_schedules as $item): ?>
        <option value="<?= $item['id'] ?>" <?= $item['id'] == $selected_id ? 'selected' : '' ?>>
          <?= format_indo_date($item['tanggal_misa']) ?> - <?= htmlspecialchars($item['judul_misa']) ?> (<?= format_indo_time($item['jam_mulai']) ?>)
        </option>
      <?php endforeach; ?>
    </select>
  </div>
</div>

<?php if (!$selected_schedule): ?>
  <div class="card" style="text-align: center; padding: 3rem 1.5rem;">
    <div style="font-size: 3rem; margin-bottom: 0.5rem;">📅</div>
    <h3 style="font-size: 1.2rem; font-weight: 700;">Belum Ada Jadwal Misa</h3>
    <p style="color: var(--text-muted); margin-bottom: 1rem;">Silakan tambahkan jadwal terlebih dahulu di menu Jadwal Misa.</p>
    <a href="jadwal.php" class="btn btn-primary">Buka Menu Jadwal</a>
  </div>
<?php else: ?>

  <!-- Ringkasan Statistik Presensi -->
  <div class="stats-grid" style="margin-bottom: 1.5rem;">
    <div class="stat-card">
      <div class="stat-icon" style="background: var(--primary-container); color: var(--primary);">👥</div>
      <div class="stat-info">
        <div class="stat-value"><?= $stats['total'] ?> / <?= $selected_schedule['kuota_maksimal'] ?></div>
        <div class="stat-label">Total Terdaftar</div>
      </div>
    </div>

    <div class="stat-card">
      <div class="stat-icon" style="background: var(--emerald-bg); color: var(--emerald-text);">✅</div>
      <div class="stat-info">
        <div class="stat-value" style="color: var(--emerald);"><?= $stats['hadir'] ?></div>
        <div class="stat-label">Hadir Tepat Waktu</div>
      </div>
    </div>

    <div class="stat-card">
      <div class="stat-icon" style="background: var(--amber-bg); color: var(--amber-text);">⚠️</div>
      <div class="stat-info">
        <div class="stat-value" style="color: var(--amber);"><?= $stats['terlambat'] ?></div>
        <div class="stat-label">Terlambat</div>
      </div>
    </div>

    <div class="stat-card">
      <div class="stat-icon" style="background: var(--surface-high); color: var(--text-muted);">⏳</div>
      <div class="stat-info">
        <div class="stat-value"><?= $stats['terdaftar'] ?></div>
        <div class="stat-label">Belum Presensi</div>
      </div>
    </div>
  </div>

  <!-- Layout 2 Kolom: QR Code Card & Live Attendance Manager -->
  <div style="display: grid; grid-template-columns: 320px 1fr; gap: 1.5rem; align-items: start;">
    
    <!-- KOLOM KIRI: STATION QR CODE -->
    <div class="card" style="text-align: center; position: sticky; top: 80px;">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
        <span class="badge badge-emerald">Live QR Station</span>
        <?= get_liturgical_badge($selected_schedule['warna_liturgi']) ?>
      </div>

      <h3 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 0.25rem;">
        <?= htmlspecialchars($selected_schedule['judul_misa']) ?>
      </h3>
      <div style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1rem;">
        <?= format_indo_date($selected_schedule['tanggal_misa']) ?><br>
        <strong>Pukul <?= format_indo_time($selected_schedule['jam_mulai']) ?></strong>
      </div>

      <!-- QR Code Image -->
      <div style="background: #ffffff; padding: 12px; border-radius: var(--radius-md); box-shadow: var(--shadow-sm); display: inline-block; border: 1px solid var(--border);">
        <img src="<?= $qr_api_url ?>" alt="QR Code Presensi" style="width: 220px; height: 220px; display: block;" onerror="this.onerror=null; this.src='https://chart.googleapis.com/chart?cht=qr&chs=220x220&chl=<?= urlencode($qr_token) ?>';">
      </div>

      <!-- Token Manual Text Code -->
      <div style="margin-top: 1rem; background: var(--surface-low); padding: 0.75rem; border-radius: var(--radius-sm);">
        <div style="font-size: 0.78rem; color: var(--text-muted); margin-bottom: 0.2rem;">KODE TOKEN PRESENSI:</div>
        <div style="font-family: monospace; font-size: 1.05rem; font-weight: 700; letter-spacing: 2px; color: var(--primary);">
          <?= htmlspecialchars($qr_token) ?>
        </div>
      </div>

      <div style="font-size: 0.78rem; color: var(--text-dim); margin-top: 0.75rem; line-height: 1.4;">
        Arahkan kamera HP misdinar atau masukkan token di atas pada menu Beranda Anggota untuk presensi otomatis.
      </div>
    </div>

    <!-- KOLOM KANAN: TABEL PRESENSI REALTIME & KONTROL MANUAL -->
    <div class="card">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 0.5rem;">
        <div>
          <h3 style="font-size: 1.15rem; font-weight: 700;">Daftar Petugas Altar (<?= count($roster) ?> Misdinar)</h3>
          <div style="font-size: 0.85rem; color: var(--text-muted);">Ubah status kehadiran secara manual jika misdinar tidak membawa ponsel.</div>
        </div>

        <div style="display: flex; gap: 0.5rem;">
          <a href="cetak_jadwal.php?id=<?= $selected_schedule['id'] ?>" target="_blank" class="btn btn-secondary btn-sm">
            🖨️ Cetak Presensi
          </a>
          <button type="button" class="btn btn-secondary btn-sm" onclick="window.location.reload()">
            🔄 Muat Ulang
          </button>
        </div>
      </div>

      <?php if (empty($roster)): ?>
        <div style="text-align: center; padding: 2.5rem 1rem; color: var(--text-muted);">
          <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">👥</div>
          <p>Belum ada misdinar yang terdaftar di jadwal misa ini.</p>
          <a href="jadwal.php?view_roster=<?= $selected_schedule['id'] ?>" class="btn btn-primary btn-sm" style="margin-top: 0.5rem;">
            + Alokasikan Petugas Sekarang
          </a>
        </div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table">
            <thead>
              <tr>
                <th>Petugas</th>
                <th>Posisi Tugas</th>
                <th>Status Kehadiran</th>
                <th>Waktu Presensi</th>
                <th style="text-align: right;">Ubah Status Manual</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($roster as $p): ?>
                <tr>
                  <td>
                    <strong><?= htmlspecialchars($p['nama_panggilan']) ?></strong>
                    <div style="font-size: 0.78rem; color: var(--text-dim);">
                      <?= htmlspecialchars($p['nama_lengkap']) ?> &bull; <?= $p['jenjang'] ?>
                    </div>
                  </td>
                  <td>
                    <span class="badge badge-light" style="font-weight: 600;">
                      <?= htmlspecialchars($p['posisi_tugas']) ?>
                    </span>
                  </td>
                  <td>
                    <?= get_attendance_badge($p['status_kehadiran']) ?>
                  </td>
                  <td style="font-size: 0.82rem; color: var(--text-muted);">
                    <?= $p['waktu_checkin'] ? date('H:i:s', strtotime($p['waktu_checkin'])) . ' WIB' : '-' ?>
                  </td>
                  <td style="text-align: right;">
                    <div style="display: inline-flex; gap: 0.25rem;">
                      <!-- Set Hadir -->
                      <form method="POST" action="presensi.php" style="margin: 0;">
                        <input type="hidden" name="action" value="update_attendance">
                        <input type="hidden" name="booking_id" value="<?= $p['id'] ?>">
                        <input type="hidden" name="schedule_id" value="<?= $selected_schedule['id'] ?>">
                        <input type="hidden" name="status_kehadiran" value="hadir">
                        <button type="submit" class="btn btn-sm <?= $p['status_kehadiran'] === 'hadir' ? 'btn-primary' : 'btn-secondary' ?>" title="Set Hadir Tepat Waktu">
                          ✅ Hadir
                        </button>
                      </form>

                      <!-- Set Terlambat -->
                      <form method="POST" action="presensi.php" style="margin: 0;">
                        <input type="hidden" name="action" value="update_attendance">
                        <input type="hidden" name="booking_id" value="<?= $p['id'] ?>">
                        <input type="hidden" name="schedule_id" value="<?= $selected_schedule['id'] ?>">
                        <input type="hidden" name="status_kehadiran" value="terlambat">
                        <button type="submit" class="btn btn-sm <?= $p['status_kehadiran'] === 'terlambat' ? 'btn-primary' : 'btn-secondary' ?>" title="Set Terlambat">
                          ⚠️ Lambat
                        </button>
                      </form>

                      <!-- Set Izin -->
                      <form method="POST" action="presensi.php" style="margin: 0;">
                        <input type="hidden" name="action" value="update_attendance">
                        <input type="hidden" name="booking_id" value="<?= $p['id'] ?>">
                        <input type="hidden" name="schedule_id" value="<?= $selected_schedule['id'] ?>">
                        <input type="hidden" name="status_kehadiran" value="izin">
                        <button type="submit" class="btn btn-sm <?= $p['status_kehadiran'] === 'izin' ? 'btn-primary' : 'btn-secondary' ?>" title="Set Izin">
                          📩 Izin
                        </button>
                      </form>

                      <!-- Set Alpa -->
                      <form method="POST" action="presensi.php" style="margin: 0;">
                        <input type="hidden" name="action" value="update_attendance">
                        <input type="hidden" name="booking_id" value="<?= $p['id'] ?>">
                        <input type="hidden" name="schedule_id" value="<?= $selected_schedule['id'] ?>">
                        <input type="hidden" name="status_kehadiran" value="alpa">
                        <button type="submit" class="btn btn-sm <?= $p['status_kehadiran'] === 'alpa' ? 'btn-primary' : 'btn-secondary' ?>" title="Set Alpa">
                          ❌ Alpa
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

<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
