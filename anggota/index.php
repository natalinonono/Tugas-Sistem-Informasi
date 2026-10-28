<?php
/**
 * Beranda Anggota Misdinar (Dashboard & Booking Mandiri)
 * SiDinar Regina - Sistem Informasi & Presensi Putra-Putri Altar
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();

$user = current_user();
$pdo = get_db();

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
            // Cek jadwal
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

// 1. Ambil Penugasan Aktif Terdekat untuk User Ini
$today = date('Y-m-d');
$active_duty_stmt = $pdo->prepare("
    SELECT b.*, s.judul_misa, s.kategori, s.tanggal_misa, s.jam_mulai, s.warna_liturgi, s.nama_selebran, s.qr_token
    FROM bookings b
    JOIN mass_schedules s ON b.schedule_id = s.id
    WHERE b.user_id = ? AND s.tanggal_misa >= ?
    ORDER BY s.tanggal_misa ASC, s.jam_mulai ASC
    LIMIT 1
");
$active_duty_stmt->execute([$user['id'], $today]);
$active_duty = $active_duty_stmt->fetch();

// 2. Ambil Jadwal Misa Mendatang yang Terbuka
$upcoming_stmt = $pdo->prepare("
    SELECT s.*,
           COUNT(b.id) as terisi,
           MAX(CASE WHEN b.user_id = ? THEN b.id ELSE NULL END) as my_booking_id,
           MAX(CASE WHEN b.user_id = ? THEN b.posisi_tugas ELSE NULL END) as my_position,
           MAX(CASE WHEN b.user_id = ? THEN b.status_kehadiran ELSE NULL END) as my_status
    FROM mass_schedules s
    LEFT JOIN bookings b ON s.id = b.schedule_id
    WHERE s.tanggal_misa >= ?
    GROUP BY s.id
    ORDER BY s.tanggal_misa ASC, s.jam_mulai ASC
    LIMIT 20
");
$upcoming_stmt->execute([$user['id'], $user['id'], $user['id'], $today]);
$open_schedules = $upcoming_stmt->fetchAll();

// 3. Ambil Warta Sakristi Terbaru
$warta_stmt = $pdo->query("
    SELECT a.*, p.nama_panggilan
    FROM announcements a
    LEFT JOIN profiles p ON a.created_by = p.id
    ORDER BY a.is_pinned DESC, a.created_at DESC
    LIMIT 5
");
$announcements = $warta_stmt->fetchAll();

$page_title = 'Beranda Misdinar';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Sambutan & Status Akun -->
<div style="margin-bottom: 1.5rem;">
  <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem;">
    <div>
      <h1 style="font-size: 1.6rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.25rem;">
        Berkah Dalem, <?= htmlspecialchars($user['nama_panggilan']) ?>! 👋
      </h1>
      <p style="color: var(--text-muted); font-size: 0.95rem;">
        Selamat datang di portal pelayanan altar SiDinar Regina Paroki Purbowardayan.
      </p>
    </div>

    <div style="display: flex; align-items: center; gap: 0.5rem;">
      <span class="badge <?= $user['status'] === 'aktif' ? 'badge-emerald' : 'badge-amber' ?>" style="font-size: 0.85rem; padding: 6px 12px;">
        Status Akun: <?= ucfirst($user['status']) ?>
      </span>
      <span class="badge badge-light" style="font-size: 0.85rem; padding: 6px 12px;">
        Jenjang: <?= htmlspecialchars($user['jenjang']) ?>
      </span>
    </div>
  </div>
</div>

<?php if ($user['status'] === 'pending'): ?>
  <!-- Warning Akun Menunggu Verifikasi -->
  <div class="card" style="background: var(--amber-bg); border-left: 5px solid var(--amber); margin-bottom: 2rem;">
    <div style="display: flex; gap: 1rem; align-items: center;">
      <span style="font-size: 2.2rem;">⏳</span>
      <div>
        <h3 style="font-size: 1.1rem; font-weight: 700; color: var(--amber-text); margin-bottom: 0.25rem;">
          Akun Anda Sedang Dalam Proses Verifikasi
        </h3>
        <p style="color: var(--amber-text); font-size: 0.9rem; margin: 0;">
          Pengurus sakristi sedang memverifikasi identitas Anda. Setelah disetujui, Anda akan dapat memilih jadwal tugas misa mandiri dan melakukan presensi digital.
        </p>
      </div>
    </div>
  </div>
<?php endif; ?>

<!-- ======================================================== -->
<!-- HERO CARD: TUGAS AKTIF TERDEKAT                           -->
<!-- ======================================================== -->
<?php if ($active_duty): ?>
  <div class="card" style="margin-bottom: 2rem; border-left: 6px solid var(--emerald); background: linear-gradient(135deg, rgba(0, 108, 76, 0.05) 0%, var(--surface) 100%);">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem; margin-bottom: 1rem;">
      <div>
        <div style="margin-bottom: 0.4rem;">
          <span class="badge badge-emerald">✨ Tugas Altar Terdekat Anda</span>
        </div>
        <h2 style="font-size: 1.45rem; font-weight: 800; color: var(--text-main);">
          <?= htmlspecialchars($active_duty['judul_misa']) ?>
        </h2>
      </div>

      <div>
        <?= get_attendance_badge($active_duty['status_kehadiran']) ?>
      </div>
    </div>

    <!-- Info Detail Misa -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; background: var(--surface-low); padding: 1rem 1.25rem; border-radius: var(--radius-md); margin-bottom: 1.25rem;">
      <div>
        <div style="font-size: 0.78rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600;">Hari & Tanggal</div>
        <div style="font-size: 1rem; font-weight: 700; color: var(--text-main); margin-top: 2px;">
          📅 <?= format_indo_date($active_duty['tanggal_misa']) ?>
        </div>
      </div>

      <div>
        <div style="font-size: 0.78rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600;">Waktu Misa</div>
        <div style="font-size: 1rem; font-weight: 700; color: var(--text-main); margin-top: 2px;">
          ⏰ <?= format_indo_time($active_duty['jam_mulai']) ?>
        </div>
      </div>

      <div>
        <div style="font-size: 0.78rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600;">Posisi Tugas Liturgi</div>
        <div style="font-size: 1rem; font-weight: 700; color: var(--primary); margin-top: 2px;">
          🛡️ <?= htmlspecialchars($active_duty['posisi_tugas']) ?>
        </div>
      </div>
    </div>

    <!-- Tombol Presensi & Status Action -->
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
      <div style="font-size: 0.85rem; color: var(--text-muted);">
        ⏱️ Wajib hadir di sakristi paling lambat <strong>H-30 menit</strong> dengan jubah rapi.
      </div>

      <div style="display: flex; gap: 0.5rem;">
        <?php if ($active_duty['status_kehadiran'] === 'terdaftar'): ?>
          <button type="button" class="btn btn-primary" onclick="openCheckinModal(<?= $active_duty['id'] ?>, '<?= htmlspecialchars($active_duty['judul_misa']) ?>')">
            📱 Presensi Digital / Masukkan Kode QR
          </button>
        <?php else: ?>
          <button type="button" class="btn btn-secondary" disabled>
            ✅ Presensi Sudah Tercatat (<?= ucfirst($active_duty['status_kehadiran']) ?>)
          </button>
        <?php endif; ?>
      </div>
    </div>
  </div>
<?php endif; ?>

<!-- ======================================================== -->
<!-- SECTION: JADWAL MISA TERBUKA & PENDAFTARAN TUGAS         -->
<!-- ======================================================== -->
<div style="margin-bottom: 2rem;">
  <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 0.5rem;">
    <div>
      <h2 style="font-size: 1.35rem; font-weight: 700; color: var(--text-main);">
        📅 Jadwal Misa Terbuka untuk Petugas
      </h2>
      <p style="color: var(--text-muted); font-size: 0.88rem;">
        Pilih perayaan Ekaristi dan daftarkan diri Anda untuk melayani di meja altar Tuhan.
      </p>
    </div>
  </div>

  <?php if (empty($open_schedules)): ?>
    <div class="card" style="text-align: center; padding: 2.5rem 1rem; color: var(--text-muted);">
      <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">⛪</div>
      <p>Belum ada jadwal misa terbuka untuk pendaftaran saat ini.</p>
    </div>
  <?php else: ?>
    <div class="grid" style="grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 1.25rem;">
      <?php foreach ($open_schedules as $s): ?>
        <?php 
          $isMyBooking = !empty($s['my_booking_id']);
          $isFull = $s['terisi'] >= $s['kuota_maksimal'];
        ?>
        <div class="card" style="display: flex; flex-direction: column; justify-content: space-between; border-left: 4px solid var(--primary);">
          <div>
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.5rem;">
              <div>
                <span class="badge <?= $s['kategori'] === 'harian' ? 'badge-blue' : 'badge-amber' ?>" style="font-size: 0.72rem; text-transform: uppercase;">
                  <?= $s['kategori'] ?>
                </span>
              </div>
              <span style="font-size: 0.8rem; font-weight: 600; color: <?= $isFull ? 'var(--error)' : 'var(--emerald)' ?>;">
                <?= $s['terisi'] ?> / <?= $s['kuota_maksimal'] ?> Petugas
              </span>
            </div>

            <h3 style="font-size: 1.15rem; font-weight: 700; margin-bottom: 0.4rem; color: var(--text-main);">
              <?= htmlspecialchars($s['judul_misa']) ?>
            </h3>

            <div style="font-size: 0.88rem; color: var(--text-muted); margin-bottom: 1rem; display: flex; flex-direction: column; gap: 0.25rem;">
              <div>📅 <strong><?= format_indo_date($s['tanggal_misa']) ?></strong></div>
              <div>⏰ Pukul <?= format_indo_time($s['jam_mulai']) ?></div>
            </div>
          </div>

          <!-- Bagian Pendaftaran / Status -->
          <div style="border-top: 1px solid var(--border-light); padding-top: 0.75rem;">
            <?php if ($isMyBooking): ?>
              <div style="background: var(--surface-low); padding: 0.75rem; border-radius: var(--radius-sm); margin-bottom: 0.5rem;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                  <span style="font-size: 0.82rem; font-weight: 600; color: var(--emerald);">✅ Anda Terdaftar:</span>
                  <?= get_attendance_badge($s['my_status']) ?>
                </div>
                <div style="font-size: 0.9rem; font-weight: 700; color: var(--text-main); margin-top: 2px;">
                  Posisi: <?= htmlspecialchars($s['my_position']) ?>
                </div>
              </div>

              <?php if ($s['my_status'] === 'terdaftar'): ?>
                <div style="display: flex; gap: 0.5rem;">
                  <button type="button" class="btn btn-primary btn-sm" style="flex: 1;" onclick="openCheckinModal(<?= $s['my_booking_id'] ?>, '<?= htmlspecialchars($s['judul_misa']) ?>')">
                    📱 Presensi
                  </button>
                  <form method="POST" action="index.php" onsubmit="return confirm('Batalkan penugasan pada misa ini?');" style="margin: 0;">
                    <input type="hidden" name="action" value="cancel_booking">
                    <input type="hidden" name="booking_id" value="<?= $s['my_booking_id'] ?>">
                    <button type="submit" class="btn btn-secondary btn-sm" style="color: var(--error);" title="Batalkan Tugas">
                      Batal
                    </button>
                  </form>
                </div>
              <?php endif; ?>

            <?php elseif ($user['status'] === 'aktif'): ?>
              <?php if ($isFull): ?>
                <button type="button" class="btn btn-secondary btn-sm" style="width: 100%;" disabled>
                  🔒 Kuota Penuh
                </button>
              <?php else: ?>
                <!-- Form Booking Mandiri -->
                <form method="POST" action="index.php" style="display: flex; gap: 0.4rem; align-items: center; margin: 0;">
                  <input type="hidden" name="action" value="book_schedule">
                  <input type="hidden" name="schedule_id" value="<?= $s['id'] ?>">

                  <select name="posisi_tugas" class="form-select" style="font-size: 0.82rem; padding: 4px 8px; flex: 1;" required>
                    <?php foreach (LITURGICAL_POSITIONS as $pos): ?>
                      <option value="<?= $pos ?>"><?= $pos ?></option>
                    <?php endforeach; ?>
                  </select>

                  <button type="submit" class="btn btn-primary btn-sm">
                    Daftar Tugas
                  </button>
                </form>
              <?php endif; ?>
            <?php else: ?>
              <button type="button" class="btn btn-secondary btn-sm" style="width: 100%;" disabled>
                Menunggu Verifikasi Akun
              </button>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<!-- ======================================================== -->
<!-- SECTION: WARTA SAKRISTI TERBARU                          -->
<!-- ======================================================== -->
<div style="margin-bottom: 2rem;">
  <h2 style="font-size: 1.35rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.75rem;">
    📢 Warta Sakristi & Pengumuman Penting
  </h2>

  <?php if (empty($announcements)): ?>
    <div class="card" style="text-align: center; padding: 2rem 1rem; color: var(--text-muted);">
      Belum ada warta sakristi yang diterbitkan pengurus.
    </div>
  <?php else: ?>
    <div style="display: flex; flex-direction: column; gap: 1rem;">
      <?php foreach ($announcements as $w): ?>
        <div class="card" style="<?= $w['is_pinned'] ? 'border-left: 5px solid var(--amber); background: linear-gradient(to right, rgba(255, 184, 107, 0.08), var(--surface));' : '' ?>">
          <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem;">
            <?php if ($w['is_pinned']): ?>
              <span class="badge badge-amber">📌 Penting</span>
            <?php endif; ?>
            <span style="font-size: 0.8rem; color: var(--text-dim);">
              <?= format_indo_date(date('Y-m-d', strtotime($w['created_at']))) ?>
            </span>
          </div>
          <h3 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 0.4rem; color: var(--text-main);">
            <?= htmlspecialchars($w['judul']) ?>
          </h3>
          <p style="font-size: 0.9rem; color: var(--text-muted); line-height: 1.5; white-space: pre-line; margin: 0;">
            <?= htmlspecialchars($w['isi']) ?>
          </p>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<!-- ======================================================== -->
<!-- MODAL: Check-in Presensi Digital                         -->
<!-- ======================================================== -->
<div class="modal" id="modal-checkin">
  <div class="modal-card" style="max-width: 440px; text-align: center;">
    <div class="modal-header">
      <div class="modal-title">📱 Presensi Digital Sakristi</div>
      <button type="button" class="btn-icon" onclick="closeModal('modal-checkin')">&times;</button>
    </div>
    <div class="modal-body" style="padding: 1.5rem 1rem;">
      <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">⛪</div>
      <h3 id="checkin-modal-misa-title" style="font-size: 1.1rem; font-weight: 700; margin-bottom: 0.25rem;">
        Misa Kudus
      </h3>
      <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1.25rem;">
        Masukkan kode token presensi yang tertera pada layar stasiun presensi sakristi atau konfirmasi kehadiran Anda.
      </p>

      <input type="hidden" id="checkin-booking-id" value="">

      <div style="margin-bottom: 1.25rem;">
        <label class="form-label" style="text-align: left;">Kode Token Presensi / QR Token</label>
        <input type="text" id="checkin-token-input" class="form-input" placeholder="Contoh: misa-1-..." style="text-align: center; font-size: 1rem; letter-spacing: 1px; font-family: monospace;">
        <small style="color: var(--text-dim); font-size: 0.78rem; display: block; margin-top: 4px; text-align: left;">
          *Petugas pengurus sakristi menampilkan kode ini di meja absensi.
        </small>
      </div>

      <div id="checkin-alert-box" style="display: none; padding: 0.75rem; border-radius: var(--radius-sm); margin-bottom: 1rem; font-size: 0.88rem;"></div>

      <button type="button" id="btn-submit-checkin" class="btn btn-primary" style="width: 100%;" onclick="submitCheckin()">
        ✅ Kirim Presensi Sekarang
      </button>
    </div>
    <div class="modal-footer" style="justify-content: center;">
      <button type="button" class="btn btn-secondary" onclick="closeModal('modal-checkin')">Tutup</button>
    </div>
  </div>
</div>

<script>
function openCheckinModal(bookingId, misaTitle) {
  document.getElementById('checkin-booking-id').value = bookingId;
  document.getElementById('checkin-modal-misa-title').innerText = misaTitle;
  document.getElementById('checkin-token-input').value = '';
  document.getElementById('checkin-alert-box').style.display = 'none';
  openModal('modal-checkin');
}

function submitCheckin() {
  const bookingId = document.getElementById('checkin-booking-id').value;
  const token = document.getElementById('checkin-token-input').value.trim();
  const alertBox = document.getElementById('checkin-alert-box');
  const btn = document.getElementById('btn-submit-checkin');

  if (!bookingId) {
    alert('ID Penugasan tidak valid.');
    return;
  }

  btn.disabled = true;
  btn.innerText = 'Memproses...';

  const formData = new URLSearchParams();
  formData.append('booking_id', bookingId);
  formData.append('qr_token', token);

  fetch(getApiUrl('checkin.php'), {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: formData.toString()
  })
  .then(res => res.json())
  .then(data => {
    btn.disabled = false;
    btn.innerText = '✅ Kirim Presensi Sekarang';

    if (data.success) {
      playNotificationChime();
      alertBox.className = 'alert alert-success';
      alertBox.style.display = 'block';
      alertBox.innerHTML = `<strong>Berhasil!</strong> ${data.message}`;

      setTimeout(() => {
        window.location.reload();
      }, 1500);
    } else {
      alertBox.className = 'alert alert-error';
      alertBox.style.display = 'block';
      alertBox.innerHTML = `<strong>Gagal:</strong> ${data.error || 'Terjadi kesalahan sistem'}`;
    }
  })
  .catch(err => {
    btn.disabled = false;
    btn.innerText = '✅ Kirim Presensi Sekarang';
    alertBox.className = 'alert alert-error';
    alertBox.style.display = 'block';
    alertBox.innerHTML = 'Gagal menghubungi server.';
  });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
