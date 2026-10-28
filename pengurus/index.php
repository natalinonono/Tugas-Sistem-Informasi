<?php
/**
 * Dashboard Utama Pengurus Sakristi
 * SiDinar Regina - Sistem Informasi & Presensi Putra-Putri Altar
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_pengurus();

$pdo = get_db();
$today = date('Y-m-d');

// 1. Statistik
$countAnggota = $pdo->query("SELECT COUNT(*) FROM profiles WHERE role = 'anggota' AND status = 'aktif'")->fetchColumn();
$countPending = $pdo->query("SELECT COUNT(*) FROM profiles WHERE status = 'pending'")->fetchColumn();
$countJadwal = $pdo->prepare("SELECT COUNT(*) FROM mass_schedules WHERE tanggal_misa >= ?");
$countJadwal->execute([$today]);
$totalJadwal = $countJadwal->fetchColumn();

$countHadirHariIni = $pdo->prepare("
    SELECT COUNT(*) FROM bookings b 
    JOIN mass_schedules s ON b.schedule_id = s.id 
    WHERE s.tanggal_misa = ? AND b.status_kehadiran IN ('hadir', 'terlambat')
");
$countHadirHariIni->execute([$today]);
$totalHadir = $countHadirHariIni->fetchColumn();

// 2. Jadwal Mendatang
$upcomingStmt = $pdo->prepare("
    SELECT s.*, 
           (SELECT COUNT(*) FROM bookings WHERE schedule_id = s.id) AS terisi
    FROM mass_schedules s 
    WHERE s.tanggal_misa >= ? 
    ORDER BY s.tanggal_misa ASC, s.jam_mulai ASC 
    LIMIT 5
");
$upcomingStmt->execute([$today]);
$upcomingSchedules = $upcomingStmt->fetchAll();

// 3. Live Presensi Terkini
$presensiStmt = $pdo->prepare("
    SELECT b.*, s.judul_misa, s.jam_mulai, p.nama_lengkap, p.nama_panggilan
    FROM bookings b
    JOIN mass_schedules s ON b.schedule_id = s.id
    JOIN profiles p ON b.user_id = p.id
    WHERE s.tanggal_misa = ?
    ORDER BY b.waktu_checkin DESC, s.jam_mulai ASC
    LIMIT 6
");
$presensiStmt->execute([$today]);
$recentPresensi = $presensiStmt->fetchAll();

$page_title = 'Dashboard Pengurus';
require_once __DIR__ . '/../includes/header.php';
?>

<div style="display: flex; flex-direction: column; gap: 1.5rem;">
  <!-- Header & Quick Action -->
  <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem;">
    <div>
      <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem;">
        <span class="badge badge-amber">Admin Sakristi</span>
        <span style="font-size: 0.75rem; color: var(--text-muted);"><?= format_indo_date($today) ?></span>
      </div>
      <h1 style="font-size: 1.5rem; color: var(--primary);">Panel Manajemen Pengurus</h1>
      <p style="font-size: 0.82rem; color: var(--text-muted);">
        Pantau alokasi tugas altar, presensi misa real-time, dan status anggota misdinar.
      </p>
    </div>

    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
      <a href="jadwal.php" class="btn btn-primary btn-sm">📅 Kelola Jadwal</a>
      <button type="button" class="btn btn-emerald btn-sm" onclick="triggerAutoGenerate()">⚡ Generate 30 Hari</button>
      <a href="warta.php" class="btn btn-secondary btn-sm">📢 Buat Warta</a>
    </div>
  </div>

  <?php if ($countPending > 0): ?>
    <div class="alert alert-info" style="display: flex; justify-content: space-between; align-items: center;">
      <div style="display: flex; align-items: center; gap: 0.6rem;">
        <span style="font-size: 1.2rem;">👤</span>
        <div>
          <strong><?= $countPending ?> Calon Anggota Menunggu Persetujuan!</strong>
          <div style="font-size: 0.75rem; font-weight: normal;">Harap segera verifikasi data calon misdinar baru.</div>
        </div>
      </div>
      <a href="anggota.php" class="btn btn-primary btn-sm">Verifikasi Sekarang</a>
    </div>
  <?php endif; ?>

  <!-- 4 Kartu Statistik Ringkas -->
  <div class="grid grid-cols-4">
    <div class="card stat-card">
      <div class="stat-icon emerald">👥</div>
      <div class="stat-info">
        <div class="stat-value"><?= $countAnggota ?></div>
        <div class="stat-label">Anggota Aktif</div>
      </div>
    </div>

    <div class="card stat-card">
      <div class="stat-icon primary">📅</div>
      <div class="stat-info">
        <div class="stat-value"><?= $totalJadwal ?></div>
        <div class="stat-label">Jadwal Misa Aktif</div>
      </div>
    </div>

    <div class="card stat-card">
      <div class="stat-icon gold">✅</div>
      <div class="stat-info">
        <div class="stat-value"><?= $totalHadir ?></div>
        <div class="stat-label">Kehadiran Hari Ini</div>
      </div>
    </div>

    <div class="card stat-card">
      <div class="stat-icon red">⏳</div>
      <div class="stat-info">
        <div class="stat-value"><?= $countPending ?></div>
        <div class="stat-label">Pending Approval</div>
      </div>
    </div>
  </div>

  <!-- 2 Kolom: Jadwal Mendatang & Live Presensi -->
  <div class="grid grid-cols-2" style="align-items: start;">
    <!-- Kolom Kiri: Jadwal Misa Mendatang -->
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">📅 Jadwal Misa Terdekat</h3>
        <a href="jadwal.php" class="btn btn-secondary btn-sm">Lihat Semua</a>
      </div>

      <?php if (empty($upcomingSchedules)): ?>
        <p style="text-align: center; color: var(--text-muted); font-size: 0.8rem; padding: 1.5rem 0;">
          Belum ada jadwal misa aktif. Klik "Generate 30 Hari" untuk membuat jadwal otomatis.
        </p>
      <?php else: ?>
        <div style="display: flex; flex-direction: column; gap: 0.75rem;">
          <?php foreach ($upcomingSchedules as $s): ?>
            <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem; background: var(--surface-low); border-radius: var(--radius-sm); border-left: 4px solid var(--primary);">
              <div>
                <div style="margin-bottom: 0.2rem;">
                  <strong style="font-size: 0.85rem;"><?= htmlspecialchars($s['judul_misa']) ?></strong>
                </div>
                <div style="font-size: 0.74rem; color: var(--text-muted);">
                  <?= format_indo_date($s['tanggal_misa']) ?> • <?= format_indo_time($s['jam_mulai']) ?>
                </div>
              </div>
              <div style="text-align: right;">
                <span class="badge <?= $s['terisi'] >= $s['kuota_maksimal'] ? 'badge-amber' : 'badge-emerald' ?>">
                  <?= $s['terisi'] ?> / <?= $s['kuota_maksimal'] ?> Petugas
                </span>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- Kolom Kiri: Live Presensi Hari Ini -->
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">📋 Live Presensi Sakristi Hari Ini</h3>
        <a href="presensi.php" class="btn btn-secondary btn-sm">Buka Scanner QR</a>
      </div>

      <?php if (empty($recentPresensi)): ?>
        <div style="text-align: center; color: var(--text-muted); font-size: 0.8rem; padding: 1.5rem 0;">
          Belum ada catatan presensi misa untuk hari ini.
        </div>
      <?php else: ?>
        <div class="table-responsive">
          <table>
            <thead>
              <tr>
                <th>Nama Petugas</th>
                <th>Misa / Peran</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recentPresensi as $p): ?>
                <tr>
                  <td>
                    <strong><?= htmlspecialchars($p['nama_lengkap']) ?></strong>
                    <div style="font-size: 0.7rem; color: var(--text-muted);"><?= htmlspecialchars($p['nama_panggilan']) ?></div>
                  </td>
                  <td>
                    <div><?= htmlspecialchars($p['posisi_tugas']) ?></div>
                    <div style="font-size: 0.7rem; color: var(--text-muted);"><?= format_indo_time($p['jam_mulai']) ?></div>
                  </td>
                  <td>
                    <?= get_attendance_badge($p['status_kehadiran']) ?>
                    <?php if ($p['waktu_checkin']): ?>
                      <div style="font-size: 0.68rem; color: var(--text-dim);"><?= date('H:i', strtotime($p['waktu_checkin'])) ?> WIB</div>
                    <?php endif; ?>
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

<script>
function triggerAutoGenerate() {
  if (!confirm('Hasilkan jadwal Misa Harian & Mingguan otomatis untuk 30 hari ke depan?')) return;

  fetch(getApiUrl('generate_schedules.php'), {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'days=30'
  })
  .then(res => res.json())
  .then(data => {
    alert(data.message || 'Jadwal berhasil digenerate!');
    window.location.reload();
  })
  .catch(err => {
    alert('Terjadi kesalahan koneksi.');
  });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
