<?php
/**
 * Riwayat Tugas & Rekap Kehadiran Misdinar (Anggota)
 * SiDinar Regina - Sistem Informasi & Presensi Putra-Putri Altar
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();

$user = current_user();
$pdo = get_db();

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
}

$sql .= " ORDER BY s.tanggal_misa DESC, s.jam_mulai DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$history = $stmt->fetchAll();

// Hitung Statistik Pribadi
$total_tugas = count($history);
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

<div style="margin-bottom: 1.5rem;">
  <h1 style="font-size: 1.6rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.25rem;">
    📜 Riwayat Pelayanan Altar Saya
  </h1>
  <p style="color: var(--text-muted); font-size: 0.95rem;">
    Pantau rekapitulasi kehadiran tugas misa dan catatan presensi sakristi Anda.
  </p>
</div>

<!-- Stats Grid Pribadi -->
<div class="stats-grid" style="margin-bottom: 1.5rem;">
  <div class="stat-card">
    <div class="stat-icon" style="background: var(--primary-container); color: var(--primary);">🛡️</div>
    <div class="stat-info">
      <div class="stat-value"><?= $all_count ?></div>
      <div class="stat-label">Total Penugasan</div>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-icon" style="background: var(--emerald-bg); color: var(--emerald-text);">✅</div>
    <div class="stat-info">
      <div class="stat-value" style="color: var(--emerald);"><?= $total_hadir ?></div>
      <div class="stat-label">Tepat Waktu</div>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-icon" style="background: var(--amber-bg); color: var(--amber-text);">⚠️</div>
    <div class="stat-info">
      <div class="stat-value" style="color: var(--amber);"><?= $total_terlambat ?></div>
      <div class="stat-label">Terlambat</div>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-icon" style="background: var(--surface-high); color: var(--primary);">📊</div>
    <div class="stat-info">
      <div class="stat-value"><?= $persen_kehadiran ?>%</div>
      <div class="stat-label">Tingkat Kehadiran</div>
    </div>
  </div>
</div>

<!-- Filter Status -->
<div class="card" style="margin-bottom: 1.5rem; padding: 0.75rem 1.25rem;">
  <div style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
    <span style="font-weight: 600; font-size: 0.88rem; color: var(--text-muted);">Filter Kehadiran:</span>
    <a href="riwayat.php?status=semua" class="btn btn-sm <?= $status_filter === 'semua' ? 'btn-primary' : 'btn-secondary' ?>">Semua Riwayat</a>
    <a href="riwayat.php?status=hadir" class="btn btn-sm <?= $status_filter === 'hadir' ? 'btn-primary' : 'btn-secondary' ?>">Hadir Tepat Waktu</a>
    <a href="riwayat.php?status=terlambat" class="btn btn-sm <?= $status_filter === 'terlambat' ? 'btn-primary' : 'btn-secondary' ?>">Terlambat</a>
    <a href="riwayat.php?status=izin" class="btn btn-sm <?= $status_filter === 'izin' ? 'btn-primary' : 'btn-secondary' ?>">Izin</a>
  </div>
</div>

<!-- Tabel Riwayat -->
<div class="card">
  <?php if (empty($history)): ?>
    <div style="text-align: center; padding: 3rem 1rem; color: var(--text-muted);">
      <div style="font-size: 3rem; margin-bottom: 0.5rem;">📜</div>
      <h3 style="font-size: 1.15rem; font-weight: 700; margin-bottom: 0.25rem;">Belum Ada Riwayat Tugas</h3>
      <p style="margin-bottom: 1rem;">Anda belum memiliki riwayat tugas yang tercatat dengan filter ini.</p>
      <a href="index.php" class="btn btn-primary btn-sm">Daftar Jadwal Tugas Misa</a>
    </div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table">
        <thead>
          <tr>
            <th style="width: 35px;">No</th>
            <th>Perayaan Misa</th>
            <th>Waktu & Tanggal</th>
            <th>Posisi Liturgi</th>
            <th>Status Kehadiran</th>
            <th>Waktu Presensi</th>
          </tr>
        </thead>
        <tbody>
          <?php $no = 1; foreach ($history as $h): ?>
            <tr>
              <td><?= $no++ ?></td>
              <td>
                <div style="margin-bottom: 2px;">
                  <strong style="font-size: 0.95rem; color: var(--text-main);"><?= htmlspecialchars($h['judul_misa']) ?></strong>
                </div>
                <div style="font-size: 0.78rem; color: var(--text-dim);">
                  Imam: <?= htmlspecialchars($h['nama_selebran'] ?: 'Romo Paroki') ?>
                </div>
              </td>
              <td>
                <div>📅 <?= format_indo_date($h['tanggal_misa']) ?></div>
                <div style="font-size: 0.8rem; color: var(--text-muted);">⏰ <?= format_indo_time($h['jam_mulai']) ?></div>
              </td>
              <td>
                <span class="badge badge-light" style="font-weight: 600;">
                  <?= htmlspecialchars($h['posisi_tugas']) ?>
                </span>
              </td>
              <td>
                <?= get_attendance_badge($h['status_kehadiran']) ?>
              </td>
              <td style="font-size: 0.85rem; color: var(--text-muted);">
                <?php if ($h['waktu_checkin']): ?>
                  <?= date('d/m/Y H:i:s', strtotime($h['waktu_checkin'])) ?> WIB
                <?php else: ?>
                  <em>Belum check-in</em>
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
