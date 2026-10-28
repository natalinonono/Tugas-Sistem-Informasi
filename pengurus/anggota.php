<?php
/**
 * Manajemen Anggota & Verifikasi Pendaftaran (Pengurus)
 * SiDinar Regina - Sistem Informasi & Presensi Putra-Putri Altar
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_pengurus();

$pdo = get_db();
$current_user = current_user();

// Handle Form Submissions
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. Setujui Anggota (Pending -> Aktif)
    if ($action === 'approve') {
        $user_id = (int)($_POST['user_id'] ?? 0);
        if ($user_id) {
            $stmt = $pdo->prepare("UPDATE profiles SET status = 'aktif' WHERE id = ?");
            $stmt->execute([$user_id]);
            set_flash('success', 'Anggota berhasil diverifikasi dan diaktifkan!');
            header('Location: anggota.php');
            exit;
        }
    }

    // 2. Tolak / Hapus Anggota
    if ($action === 'delete') {
        $user_id = (int)($_POST['user_id'] ?? 0);
        $curr = current_user();
        if ($user_id && $user_id !== (int)$curr['id']) {
            $stmt = $pdo->prepare("DELETE FROM profiles WHERE id = ?");
            $stmt->execute([$user_id]);
            set_flash('success', 'Data anggota berhasil dihapus dari sistem.');
            header('Location: anggota.php');
            exit;
        } else {
            set_flash('error', 'Anda tidak dapat menghapus akun Anda sendiri.');
        }
    }

    // 3. Ubah Status (Aktif / Nonaktif)
    if ($action === 'toggle_status') {
        $user_id = (int)($_POST['user_id'] ?? 0);
        $status = $_POST['status'] ?? 'aktif';
        if ($user_id && in_array($status, ['aktif', 'nonaktif', 'pending'])) {
            $stmt = $pdo->prepare("UPDATE profiles SET status = ? WHERE id = ?");
            $stmt->execute([$status, $user_id]);
            set_flash('success', "Status anggota berhasil diperbarui menjadi: " . ucfirst($status));
            header('Location: anggota.php');
            exit;
        }
    }

    // 4. Ubah Role (Pengurus / Anggota)
    if ($action === 'toggle_role') {
        $user_id = (int)($_POST['user_id'] ?? 0);
        $new_role = $_POST['role'] ?? 'anggota';
        if ($user_id && in_array($new_role, ['anggota', 'pengurus'])) {
            $stmt = $pdo->prepare("UPDATE profiles SET role = ? WHERE id = ?");
            $stmt->execute([$new_role, $user_id]);
            set_flash('success', "Hak akses (Role) berhasil diubah menjadi: " . ucfirst($new_role));
            header('Location: anggota.php');
            exit;
        }
    }

    // 5. Reset Password ke Default: password123
    if ($action === 'reset_password') {
        $user_id = (int)($_POST['user_id'] ?? 0);
        if ($user_id) {
            $defaultHash = password_hash('password123', PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("UPDATE profiles SET password = ? WHERE id = ?");
            $stmt->execute([$defaultHash, $user_id]);
            set_flash('success', 'Kata sandi berhasil di-reset menjadi: password123');
            header('Location: anggota.php');
            exit;
        }
    }

    // 6. Tambah Anggota Baru Manual oleh Pengurus
    if ($action === 'create_member') {
        $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
        $nama_panggilan = trim($_POST['nama_panggilan'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $telepon = trim($_POST['no_telepon'] ?? '');
        $wilayah = trim($_POST['asal_wilayah_teks'] ?? '');
        $jenjang = $_POST['jenjang'] ?? 'SMA/K';
        $role = $_POST['role'] ?? 'anggota';
        $password = trim($_POST['password'] ?? 'password123');

        if ($nama_lengkap && $nama_panggilan && $email && $telepon) {
            // Cek duplikasi email
            $chk = $pdo->prepare("SELECT id FROM profiles WHERE email = ?");
            $chk->execute([$email]);
            if ($chk->fetch()) {
                set_flash('error', 'Alamat email sudah terdaftar.');
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $ins = $pdo->prepare("
                    INSERT INTO profiles (email, password, nama_lengkap, nama_panggilan, no_telepon, asal_wilayah_teks, jenjang, role, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'aktif')
                ");
                $ins->execute([$email, $hash, $nama_lengkap, $nama_panggilan, $telepon, $wilayah, $jenjang, $role]);
                set_flash('success', "Anggota {$nama_panggilan} berhasil didaftarkan langsung dengan status Aktif!");
                header('Location: anggota.php');
                exit;
            }
        } else {
            set_flash('error', 'Semua data wajib diisi.');
        }
    }
}

// Filter & Parameter
$status_filter = $_GET['status'] ?? 'semua';
$search_query = trim($_GET['q'] ?? '');

$sql = "
    SELECT p.*,
           COUNT(b.id) as total_tugas,
           SUM(CASE WHEN b.status_kehadiran IN ('hadir', 'terlambat') THEN 1 ELSE 0 END) as total_hadir
    FROM profiles p
    LEFT JOIN bookings b ON p.id = b.user_id
    WHERE 1=1
";
$params = [];

if ($status_filter === 'pending') {
    $sql .= " AND p.status = 'pending'";
} elseif ($status_filter === 'aktif') {
    $sql .= " AND p.status = 'aktif'";
} elseif ($status_filter === 'nonaktif') {
    $sql .= " AND p.status = 'nonaktif'";
}

if ($search_query !== '') {
    $sql .= " AND (p.nama_lengkap LIKE ? OR p.nama_panggilan LIKE ? OR p.email LIKE ? OR p.no_telepon LIKE ? OR p.asal_wilayah_teks LIKE ?)";
    $params[] = "%{$search_query}%";
    $params[] = "%{$search_query}%";
    $params[] = "%{$search_query}%";
    $params[] = "%{$search_query}%";
    $params[] = "%{$search_query}%";
}

$sql .= " GROUP BY p.id ORDER BY (p.status = 'pending') DESC, p.nama_panggilan ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$members = $stmt->fetchAll();

// Cek jumlah pending untuk notifikasi banner
$pCountStmt = $pdo->query("SELECT COUNT(*) FROM profiles WHERE status = 'pending'");
$pending_count = (int)$pCountStmt->fetchColumn();

$page_title = 'Kelola Anggota & Verifikasi Pendaftaran';
require_once __DIR__ . '/../includes/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem;">
  <div>
    <h1 style="font-size: 1.6rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.25rem;">
      👥 Manajemen Putra-Putri Altar & Verifikasi
    </h1>
    <p style="color: var(--text-muted); font-size: 0.95rem;">
      Verifikasi calon anggota baru, kelola data pengurus & misdinar, dan pantau riwayat keaktifan.
    </p>
  </div>

  <button type="button" class="btn btn-primary" onclick="openModal('modal-tambah-anggota')">
    ➕ Tambah Anggota Manual
  </button>
</div>

<!-- Banner Notifikasi Pendaftaran Pending -->
<?php if ($pending_count > 0): ?>
  <div class="card" style="margin-bottom: 1.5rem; background: var(--amber-bg); border-left: 5px solid var(--amber); padding: 1rem 1.25rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
      <div style="display: flex; align-items: center; gap: 0.75rem;">
        <span style="font-size: 1.75rem;">🔔</span>
        <div>
          <div style="font-weight: 700; color: var(--amber-text); font-size: 1rem;">
            <?= $pending_count ?> Calon Anggota Menunggu Verifikasi Pengurus!
          </div>
          <div style="font-size: 0.85rem; color: var(--amber-text); opacity: 0.9;">
            Calon misdinar yang baru mendaftar mandiri memerlukan persetujuan pengurus sakristi sebelum dapat memilih jadwal tugas.
          </div>
        </div>
      </div>
      <a href="anggota.php?status=pending" class="btn btn-sm btn-primary" style="background: var(--amber); border-color: var(--amber); color: #fff;">
        Tinjau Pendaftaran (<?= $pending_count ?>)
      </a>
    </div>
  </div>
<?php endif; ?>

<!-- Filter & Search Bar -->
<div class="card" style="margin-bottom: 1.5rem; padding: 1rem 1.25rem;">
  <form method="GET" action="anggota.php" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: center; justify-content: space-between;">
    <div style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
      <span style="font-weight: 600; font-size: 0.9rem; color: var(--text-muted);">Status:</span>
      <a href="anggota.php?status=semua" class="btn btn-sm <?= $status_filter === 'semua' ? 'btn-primary' : 'btn-secondary' ?>">Semua</a>
      <a href="anggota.php?status=pending" class="btn btn-sm <?= $status_filter === 'pending' ? 'btn-primary' : 'btn-secondary' ?>">
        Menunggu (<?= $pending_count ?>)
      </a>
      <a href="anggota.php?status=aktif" class="btn btn-sm <?= $status_filter === 'aktif' ? 'btn-primary' : 'btn-secondary' ?>">Aktif</a>
      <a href="anggota.php?status=nonaktif" class="btn btn-sm <?= $status_filter === 'nonaktif' ? 'btn-primary' : 'btn-secondary' ?>">Nonaktif</a>
    </div>

    <div style="display: flex; gap: 0.5rem; align-items: center; flex: 1; max-width: 400px;">
      <input type="text" name="q" class="form-input" placeholder="Cari nama, wilayah, WA..." value="<?= htmlspecialchars($search_query) ?>" style="padding: 0.4rem 0.75rem; font-size: 0.88rem;">
      <button type="submit" class="btn btn-secondary btn-sm">Cari</button>
      <?php if ($search_query || $status_filter !== 'semua'): ?>
        <a href="anggota.php" class="btn btn-secondary btn-sm" title="Reset filter">Reset</a>
      <?php endif; ?>
    </div>
  </form>
</div>

<!-- Tabel Anggota -->
<div class="card">
  <?php if (empty($members)): ?>
    <div style="text-align: center; padding: 3rem 1.5rem; color: var(--text-muted);">
      <div style="font-size: 3rem; margin-bottom: 0.5rem;">👥</div>
      <p>Tidak ditemukan anggota yang sesuai dengan filter.</p>
    </div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table">
        <thead>
          <tr>
            <th style="width: 35px;">No</th>
            <th>Nama & Panggilan</th>
            <th>Jenjang & Wilayah</th>
            <th>Kontak & WhatsApp</th>
            <th>Role</th>
            <th>Status Akun</th>
            <th>Keaktifan</th>
            <th style="text-align: right;">Aksi & Kontrol</th>
          </tr>
        </thead>
        <tbody>
          <?php $no = 1; foreach ($members as $m): ?>
            <?php 
              $cleanPhone = preg_replace('/[^0-9]/', '', $m['no_telepon']);
              if (str_starts_with($cleanPhone, '0')) $cleanPhone = '62' . substr($cleanPhone, 1);
              $waUrl = "https://wa.me/{$cleanPhone}?text=" . urlencode("Halo {$m['nama_panggilan']}, Berkah Dalem dari Pengurus Sakristi SiDinar Regina.");
            ?>
            <tr style="<?= $m['status'] === 'pending' ? 'background: rgba(255, 184, 107, 0.15);' : '' ?>">
              <td><?= $no++ ?></td>
              <td>
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                  <strong style="font-size: 0.95rem;"><?= htmlspecialchars($m['nama_panggilan']) ?></strong>
                  <?php if ($m['role'] === 'pengurus'): ?>
                    <span class="badge badge-amber" style="font-size: 0.7rem;">Pengurus</span>
                  <?php endif; ?>
                </div>
                <div style="font-size: 0.78rem; color: var(--text-dim);"><?= htmlspecialchars($m['nama_lengkap']) ?></div>
                <div style="font-size: 0.75rem; color: var(--text-muted);"><?= htmlspecialchars($m['email']) ?></div>
              </td>
              <td>
                <span class="badge badge-light"><?= htmlspecialchars($m['jenjang']) ?></span>
                <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 3px;">
                  <?= htmlspecialchars($m['asal_wilayah_teks']) ?>
                </div>
              </td>
              <td>
                <a href="<?= $waUrl ?>" target="_blank" class="btn btn-secondary btn-sm" style="color: #25D366; font-size: 0.82rem; padding: 3px 8px;" title="Chat WhatsApp">
                  📱 <?= htmlspecialchars($m['no_telepon']) ?>
                </a>
              </td>
              <td>
                <!-- Toggle Role Form -->
                <form method="POST" action="anggota.php" style="margin: 0;">
                  <input type="hidden" name="action" value="toggle_role">
                  <input type="hidden" name="user_id" value="<?= $m['id'] ?>">
                  <select name="role" class="form-select" style="padding: 2px 6px; font-size: 0.8rem; height: 28px;" onchange="this.form.submit()">
                    <option value="anggota" <?= $m['role'] === 'anggota' ? 'selected' : '' ?>>Anggota</option>
                    <option value="pengurus" <?= $m['role'] === 'pengurus' ? 'selected' : '' ?>>Pengurus</option>
                  </select>
                </form>
              </td>
              <td>
                <?php if ($m['status'] === 'pending'): ?>
                  <span class="badge badge-amber">Menunggu</span>
                <?php elseif ($m['status'] === 'aktif'): ?>
                  <span class="badge badge-emerald">Aktif</span>
                <?php else: ?>
                  <span class="badge badge-red">Nonaktif</span>
                <?php endif; ?>
              </td>
              <td>
                <div style="font-size: 0.82rem; font-weight: 600;">
                  <?= (int)$m['total_hadir'] ?> / <?= (int)$m['total_tugas'] ?> Misa
                </div>
                <div style="font-size: 0.74rem; color: var(--text-dim);">
                  <?= $m['total_tugas'] > 0 ? round(($m['total_hadir'] / $m['total_tugas']) * 100) . '% hadir' : 'Belum bertugas' ?>
                </div>
              </td>
              <td style="text-align: right;">
                <div style="display: inline-flex; gap: 0.35rem; align-items: center;">
                  <?php if ($m['status'] === 'pending'): ?>
                    <!-- Tombol Cepat Setujui -->
                    <form method="POST" action="anggota.php" style="margin: 0;">
                      <input type="hidden" name="action" value="approve">
                      <input type="hidden" name="user_id" value="<?= $m['id'] ?>">
                      <button type="submit" class="btn btn-primary btn-sm" title="Setujui pendaftaran anggota">
                        ✅ Setujui
                      </button>
                    </form>
                  <?php else: ?>
                    <!-- Toggle Aktif/Nonaktif -->
                    <form method="POST" action="anggota.php" style="margin: 0;">
                      <input type="hidden" name="action" value="toggle_status">
                      <input type="hidden" name="user_id" value="<?= $m['id'] ?>">
                      <input type="hidden" name="status" value="<?= $m['status'] === 'aktif' ? 'nonaktif' : 'aktif' ?>">
                      <button type="submit" class="btn btn-secondary btn-sm" title="<?= $m['status'] === 'aktif' ? 'Nonaktifkan akun' : 'Aktifkan akun' ?>">
                        <?= $m['status'] === 'aktif' ? '⏸️' : '▶️' ?>
                      </button>
                    </form>
                  <?php endif; ?>

                  <!-- Reset Password -->
                  <form method="POST" action="anggota.php" onsubmit="return confirm('Reset kata sandi pengguna ini ke default (password123)?');" style="margin: 0;">
                    <input type="hidden" name="action" value="reset_password">
                    <input type="hidden" name="user_id" value="<?= $m['id'] ?>">
                    <button type="submit" class="btn btn-secondary btn-sm" title="Reset Password ke: password123">
                      🔑
                    </button>
                  </form>

                  <!-- Hapus Akun -->
                  <?php if ((int)$m['id'] !== (int)$current_user['id']): ?>
                    <form method="POST" action="anggota.php" onsubmit="return confirm('Apakah Anda yakin ingin menghapus akun ini secara permanen?');" style="margin: 0;">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="user_id" value="<?= $m['id'] ?>">
                      <button type="submit" class="btn btn-secondary btn-sm" style="color: var(--error);" title="Hapus Akun">
                        🗑️
                      </button>
                    </form>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<!-- ======================================================== -->
<!-- MODAL: Tambah Anggota Manual                             -->
<!-- ======================================================== -->
<div class="modal" id="modal-tambah-anggota">
  <div class="modal-card" style="max-width: 520px;">
    <div class="modal-header">
      <div class="modal-title">➕ Tambah Anggota Misdinar Baru</div>
      <button type="button" class="btn-icon" onclick="closeModal('modal-tambah-anggota')">&times;</button>
    </div>
    <form method="POST" action="anggota.php">
      <input type="hidden" name="action" value="create_member">
      <div class="modal-body" style="display: flex; flex-direction: column; gap: 0.9rem;">
        <div style="display: grid; grid-template-columns: 1.4fr 1fr; gap: 0.75rem;">
          <div>
            <label class="form-label">Nama Lengkap *</label>
            <input type="text" name="nama_lengkap" class="form-input" placeholder="Contoh: Stefanus Bagas Pratama" required>
          </div>
          <div>
            <label class="form-label">Nama Panggilan *</label>
            <input type="text" name="nama_panggilan" class="form-input" placeholder="Bagas" required>
          </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
          <div>
            <label class="form-label">Email Akun *</label>
            <input type="email" name="email" class="form-input" placeholder="bagas@gmail.com" required>
          </div>
          <div>
            <label class="form-label">Nomor WhatsApp *</label>
            <input type="tel" name="no_telepon" class="form-input" placeholder="08123456789" required>
          </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
          <div>
            <label class="form-label">Jenjang Sekolah / Kuliah</label>
            <select name="jenjang" class="form-select" required>
              <?php foreach (JENJANG_OPTIONS as $j): ?>
                <option value="<?= $j ?>"><?= $j ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="form-label">Hak Akses (Role)</label>
            <select name="role" class="form-select" required>
              <option value="anggota">Anggota Misdinar</option>
              <option value="pengurus">Pengurus Sakristi</option>
            </select>
          </div>
        </div>

        <div>
          <label class="form-label">Asal Wilayah / Lingkungan *</label>
          <input type="text" name="asal_wilayah_teks" class="form-input" placeholder="Contoh: Wilayah Jebres, St. Theresia" required>
        </div>

        <div>
          <label class="form-label">Kata Sandi Awal</label>
          <input type="text" name="password" class="form-input" value="password123">
          <small style="color: var(--text-dim); font-size: 0.75rem;">Default: password123 (anggota dapat mengganti nanti)</small>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-tambah-anggota')">Batal</button>
        <button type="submit" class="btn btn-primary">Simpan & Aktifkan</button>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
