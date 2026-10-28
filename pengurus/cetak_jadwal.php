<?php
/**
 * Lembar Cetak Penugasan Altar Resmi (Print-Ready Document)
 * SiDinar Regina - Sistem Informasi & Presensi Putra-Putri Altar
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_pengurus();

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    die("ID Jadwal Misa tidak valid.");
}

$pdo = get_db();

// Ambil info jadwal misa
$stmt = $pdo->prepare("SELECT * FROM mass_schedules WHERE id = ?");
$stmt->execute([$id]);
$schedule = $stmt->fetch();

if (!$schedule) {
    die("Jadwal misa tidak ditemukan.");
}

// Ambil daftar petugas
$bStmt = $pdo->prepare("
    SELECT b.*, p.nama_lengkap, p.nama_panggilan, p.no_telepon, p.jenjang, p.asal_wilayah_teks
    FROM bookings b
    JOIN profiles p ON b.user_id = p.id
    WHERE b.schedule_id = ?
    ORDER BY b.posisi_tugas ASC, p.nama_panggilan ASC
");
$bStmt->execute([$id]);
$roster = $bStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Lembar Tugas Altar - <?= htmlspecialchars($schedule['judul_misa']) ?></title>
  <style>
    @page {
      size: A4 portrait;
      margin: 1.5cm;
    }
    body {
      font-family: 'Times New Roman', Times, serif;
      color: #111;
      background: #fff;
      margin: 0;
      padding: 20px;
      line-height: 1.4;
    }
    .no-print {
      margin-bottom: 20px;
      padding: 12px 18px;
      background: #f0f4f8;
      border: 1px solid #c9d6df;
      border-radius: 8px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-family: system-ui, sans-serif;
    }
    .btn {
      padding: 8px 16px;
      font-size: 14px;
      font-weight: 600;
      border-radius: 6px;
      cursor: pointer;
      text-decoration: none;
      display: inline-block;
    }
    .btn-primary {
      background: #006C4C;
      color: #fff;
      border: none;
    }
    .btn-secondary {
      background: #e2e8f0;
      color: #1e293b;
      border: 1px solid #cbd5e1;
    }
    @media print {
      .no-print {
        display: none !important;
      }
      body {
        padding: 0;
      }
    }

    /* KOP SURAT */
    .kop-header {
      text-align: center;
      border-bottom: 3px double #111;
      padding-bottom: 12px;
      margin-bottom: 18px;
    }
    .kop-church {
      font-size: 18pt;
      font-weight: bold;
      letter-spacing: 1px;
      text-transform: uppercase;
    }
    .kop-sub {
      font-size: 13pt;
      font-weight: bold;
      margin-top: 2px;
    }
    .kop-meta {
      font-size: 9.5pt;
      color: #333;
      margin-top: 4px;
      font-family: Arial, sans-serif;
    }

    .doc-title {
      text-align: center;
      font-size: 14pt;
      font-weight: bold;
      text-decoration: underline;
      margin-bottom: 16px;
      text-transform: uppercase;
    }

    /* INFO JADWAL */
    .info-table {
      width: 100%;
      margin-bottom: 16px;
      font-size: 11pt;
    }
    .info-table td {
      padding: 3px 6px;
      vertical-align: top;
    }
    .info-table td.label {
      width: 160px;
      font-weight: bold;
    }

    /* TABEL PETUGAS */
    .table-roster {
      width: 100%;
      border-collapse: collapse;
      margin-top: 10px;
      margin-bottom: 24px;
      font-size: 10.5pt;
    }
    .table-roster th, .table-roster td {
      border: 1px solid #333;
      padding: 6px 8px;
    }
    .table-roster th {
      background: #f2f2f2;
      text-align: center;
      font-weight: bold;
    }

    /* TANDA TANGAN */
    .sig-section {
      margin-top: 30px;
      display: flex;
      justify-content: space-between;
      page-break-inside: avoid;
    }
    .sig-box {
      width: 240px;
      text-align: center;
    }
    .sig-space {
      height: 70px;
    }
  </style>
</head>
<body>

  <!-- Tombol Aksi di Layar Browser -->
  <div class="no-print">
    <div>
      <strong>Mode Cetak Dokumen Resmi Penugasan Altar</strong>
      <div style="font-size: 12px; color: #64748b;">Gunakan tombol cetak atau tekan Ctrl + P untuk mencetak ke kertas / PDF</div>
    </div>
    <div style="display: flex; gap: 8px;">
      <button onclick="window.print()" class="btn btn-primary">🖨️ Cetak Dokumen</button>
      <a href="jadwal.php?view_roster=<?= $schedule['id'] ?>" class="btn btn-secondary">Kembali</a>
    </div>
  </div>

  <!-- KOP SURAT PAROKI -->
  <div class="kop-header">
    <div class="kop-church">PAGUYUBAN PUTRA-PUTRI ALTAR (MISDINAR)</div>
    <div class="kop-sub">GEREJA KATOLIK SANTA PERAWAN MARIA DIANGKAT KE SURGA</div>
    <div class="kop-meta">
      Paroki Purbowardayan - Surakarta &bull; Jl. Ahmad Yani No. 10 &bull; Telp. (0271) 642211
    </div>
  </div>

  <div class="doc-title">LEMBAR PENUGASAN LITURGI & PRESENSI SAKRISTI</div>

  <!-- META DATA MISA -->
  <table class="info-table">
    <tr>
      <td class="label">Perayaan Ekaristi</td>
      <td>: <?= htmlspecialchars($schedule['judul_misa']) ?></td>
      <td class="label">Kategori / Minggu</td>
      <td>: <?= ucfirst($schedule['kategori']) ?> (Minggu Ke-<?= $schedule['minggu_ke'] ?> / <?= $schedule['tahun'] ?>)</td>
    </tr>
    <tr>
      <td class="label">Hari / Tanggal</td>
      <td>: <?= format_indo_date($schedule['tanggal_misa']) ?></td>
      <td class="label">Tahun Liturgi</td>
      <td>: <?= htmlspecialchars($schedule['tahun_liturgi']) ?></td>
    </tr>
    <tr>
      <td class="label">Waktu Perayaan</td>
      <td>: Pukul <?= format_indo_time($schedule['jam_mulai']) ?></td>
      <td class="label">Target Hadir di Sakristi</td>
      <td>: Paling lambat H-30 Menit</td>
    </tr>
  </table>

  <!-- DAFTAR PETUGAS -->
  <table class="table-roster">
    <thead>
      <tr>
        <th style="width: 35px;">No</th>
        <th>Posisi Tugas Liturgi</th>
        <th>Nama Misdinar</th>
        <th>Jenjang / Wilayah</th>
        <th>No. Telepon / WA</th>
        <th style="width: 90px;">Kehadiran</th>
        <th style="width: 90px;">Tanda Tangan</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($roster)): ?>
        <tr>
          <td colspan="7" style="text-align: center; padding: 20px; font-style: italic;">
            Belum ada misdinar yang terdaftar dalam jadwal tugas ini.
          </td>
        </tr>
      <?php else: ?>
        <?php $no = 1; foreach ($roster as $p): ?>
          <tr>
            <td style="text-align: center;"><?= $no++ ?></td>
            <td><strong><?= htmlspecialchars($p['posisi_tugas']) ?></strong></td>
            <td><?= htmlspecialchars($p['nama_lengkap']) ?> (<em><?= htmlspecialchars($p['nama_panggilan']) ?></em>)</td>
            <td><?= htmlspecialchars($p['jenjang']) ?> / <?= htmlspecialchars($p['asal_wilayah_teks']) ?></td>
            <td><?= htmlspecialchars($p['no_telepon']) ?></td>
            <td style="text-align: center;">
              <?php if ($p['status_kehadiran'] === 'hadir'): ?>
                <strong>[ HADIR ]</strong>
              <?php elseif ($p['status_kehadiran'] === 'terlambat'): ?>
                <em>[ TERLAMBAT ]</em>
              <?php else: ?>
                [ &nbsp; &nbsp; &nbsp; &nbsp; ]
              <?php endif; ?>
            </td>
            <td style="text-align: center; height: 32px;"></td>
          </tr>
        <?php endforeach; ?>
        <?php 
          // Isi baris kosong cadangan jika kuota belum terpenuhi
          $sisa = max(0, min(4, $schedule['kuota_maksimal'] - count($roster)));
          for ($i = 1; $i <= $sisa; $i++):
        ?>
          <tr>
            <td style="text-align: center; color: #888;"><?= $no++ ?></td>
            <td style="color: #888;">Petugas Cadangan / Altar</td>
            <td></td>
            <td></td>
            <td></td>
            <td style="text-align: center;">[ &nbsp; &nbsp; &nbsp; &nbsp; ]</td>
            <td></td>
          </tr>
        <?php endfor; ?>
      <?php endif; ?>
    </tbody>
  </table>

  <!-- CATATAN PETUGAS SAKRISTI -->
  <div style="border: 1px dashed #444; padding: 8px 12px; font-size: 9.5pt; margin-bottom: 20px;">
    <strong>Ketentuan Petugas Altar:</strong>
    <ol style="margin: 4px 0 0 16px; padding: 0;">
      <li>Petugas wajib hadir di sakristi paling lambat 30 menit sebelum perayaan dimulai dalam kondisi rapi, bersih, dan bersepatu hitam pantofel/tertutup.</li>
      <li>Mengenakan jubah dan singel yang sesuai dengan ukuran serta warna liturgi hari bersangkutan.</li>
      <li>Bagi petugas yang berhalangan hadir wajib melapor ke Pengurus Sakristi paling lambat H-1 untuk pencarian petugas pengganti.</li>
    </ol>
  </div>

  <!-- BLOK TANDA TANGAN -->
  <div class="sig-section">
    <div class="sig-box">
      Mengetahui,<br>
      <strong>Pembina / Pendamping</strong>
      <div class="sig-space"></div>
      ( .................................................... )
    </div>

    <div class="sig-box">
      Surakarta, <?= format_indo_date(date('Y-m-d')) ?><br>
      <strong>Pengurus Sakristi / Ketua Misdinar</strong>
      <div class="sig-space"></div>
      ( .................................................... )
    </div>
  </div>

</body>
</html>
