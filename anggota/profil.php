<?php
/**
 * Profil Saya & Pengaturan Akun (Anggota / Pengurus)
 * SiDinar Regina - Sistem Informasi & Presensi Putra-Putri Altar
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();

$user = current_user();
$pdo = get_db();

// Handle Form Submissions
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. Perbarui Biodata Profil
    if ($action === 'update_profile') {
        $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
        $nama_panggilan = trim($_POST['nama_panggilan'] ?? '');
        $no_telepon = trim($_POST['no_telepon'] ?? '');
        $jenjang = $_POST['jenjang'] ?? 'SMA/K';
        $wilayah = trim($_POST['asal_wilayah_teks'] ?? '');

        if ($nama_lengkap && $nama_panggilan && $no_telepon) {
            $stmt = $pdo->prepare("
                UPDATE profiles 
                SET nama_lengkap = ?, nama_panggilan = ?, no_telepon = ?, jenjang = ?, asal_wilayah_teks = ?
                WHERE id = ?
            ");
            $stmt->execute([$nama_lengkap, $nama_panggilan, $no_telepon, $jenjang, $wilayah, $user['id']]);

            // Refresh data session
            $_SESSION['user']['nama_lengkap'] = $nama_lengkap;
            $_SESSION['user']['nama_panggilan'] = $nama_panggilan;
            $_SESSION['user']['no_telepon'] = $no_telepon;
            $_SESSION['user']['jenjang'] = $jenjang;
            $_SESSION['user']['asal_wilayah_teks'] = $wilayah;

            set_flash('success', 'Profil biodata berhasil diperbarui!');
            header('Location: profil.php');
            exit;
        } else {
            set_flash('error', 'Semua bidang wajib diisi.');
        }
    }

    // 2. Ganti Kata Sandi
    if ($action === 'change_password') {
        $old_pass = $_POST['old_password'] ?? '';
        $new_pass = $_POST['new_password'] ?? '';
        $confirm_pass = $_POST['confirm_password'] ?? '';

        if (!$old_pass || !$new_pass) {
            set_flash('error', 'Kata sandi saat ini dan kata sandi baru wajib diisi.');
        } elseif ($new_pass !== $confirm_pass) {
            set_flash('error', 'Konfirmasi kata sandi baru tidak cocok.');
        } elseif (strlen($new_pass) < 6) {
            set_flash('error', 'Kata sandi baru minimal 6 karakter.');
        } else {
            // Verifikasi password lama
            $stmt = $pdo->prepare("SELECT password FROM profiles WHERE id = ?");
            $stmt->execute([$user['id']]);
            $current_hash = $stmt->fetchColumn();

            if (!password_verify($old_pass, $current_hash)) {
                set_flash('error', 'Kata sandi saat ini yang Anda masukkan salah.');
            } else {
                $new_hash = password_hash($new_pass, PASSWORD_BCRYPT);
                $update = $pdo->prepare("UPDATE profiles SET password = ? WHERE id = ?");
                $update->execute([$new_hash, $user['id']]);

                set_flash('success', 'Kata sandi berhasil diubah! Gunakan kata sandi baru untuk login berikutnya.');
                header('Location: profil.php');
                exit;
            }
        }
    }
}

// Ambil profil segar dari database
$uStmt = $pdo->prepare("SELECT * FROM profiles WHERE id = ?");
$uStmt->execute([$user['id']]);
$profile = $uStmt->fetch();

$page_title = 'Profil Saya';
require_once __DIR__ . '/../includes/header.php';
?>

<div style="margin-bottom: 1.5rem;">
  <h1 style="font-size: 1.6rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.25rem;">
    👤 Pengaturan Profil & Akun
  </h1>
  <p style="color: var(--text-muted); font-size: 0.95rem;">
    Perbarui data diri, nomor kontak WhatsApp, dan kata sandi keamanan Anda.
  </p>
</div>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; align-items: start;">
  
  <!-- FORM 1: BIODATA ANGGOTA -->
  <div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
      <h3 style="font-size: 1.15rem; font-weight: 700;">Biodata Misdinar</h3>
      <span class="badge <?= $profile['role'] === 'pengurus' ? 'badge-amber' : 'badge-emerald' ?>">
        <?= ucfirst($profile['role']) ?> &bull; <?= ucfirst($profile['status']) ?>
      </span>
    </div>

    <form method="POST" action="profil.php">
      <input type="hidden" name="action" value="update_profile">

      <div style="display: flex; flex-direction: column; gap: 1rem;">
        <div>
          <label class="form-label">Email Terdaftar (Akun Login)</label>
          <input type="email" class="form-input" value="<?= htmlspecialchars($profile['email']) ?>" disabled style="background: var(--surface-low); opacity: 0.8;">
          <small style="color: var(--text-dim); font-size: 0.75rem;">Email akun tidak dapat diubah sendiri.</small>
        </div>

        <div style="display: grid; grid-template-columns: 1.4fr 1fr; gap: 0.75rem;">
          <div>
            <label class="form-label">Nama Lengkap *</label>
            <input type="text" name="nama_lengkap" class="form-input" value="<?= htmlspecialchars($profile['nama_lengkap']) ?>" required>
          </div>
          <div>
            <label class="form-label">Nama Panggilan *</label>
            <input type="text" name="nama_panggilan" class="form-input" value="<?= htmlspecialchars($profile['nama_panggilan']) ?>" required>
          </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
          <div>
            <label class="form-label">Nomor WhatsApp *</label>
            <input type="tel" name="no_telepon" class="form-input" value="<?= htmlspecialchars($profile['no_telepon']) ?>" required>
          </div>
          <div>
            <label class="form-label">Jenjang Pendidikan</label>
            <select name="jenjang" class="form-select" required>
              <?php foreach (JENJANG_OPTIONS as $j): ?>
                <option value="<?= $j ?>" <?= $profile['jenjang'] === $j ? 'selected' : '' ?>><?= $j ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div>
          <label class="form-label">Asal Wilayah / Lingkungan Gereja</label>
          <input type="text" name="asal_wilayah_teks" class="form-input" value="<?= htmlspecialchars($profile['asal_wilayah_teks']) ?>" placeholder="Contoh: Jebres Barat, St. Theresia" required>
        </div>

        <button type="submit" class="btn btn-primary" style="margin-top: 0.5rem;">
          💾 Simpan Perubahan Profil
        </button>
      </div>
    </form>
  </div>

  <!-- FORM 2: GANTI KATA SANDI -->
  <div class="card">
    <h3 style="font-size: 1.15rem; font-weight: 700; margin-bottom: 1.25rem;">Keamanan & Kata Sandi</h3>

    <form method="POST" action="profil.php">
      <input type="hidden" name="action" value="change_password">

      <div style="display: flex; flex-direction: column; gap: 1rem;">
        <div>
          <label class="form-label">Kata Sandi Saat Ini *</label>
          <input type="password" name="old_password" class="form-input" placeholder="Masukkan kata sandi lama Anda" required>
        </div>

        <div>
          <label class="form-label">Kata Sandi Baru *</label>
          <input type="password" name="new_password" class="form-input" placeholder="Minimal 6 karakter" required minlength="6">
        </div>

        <div>
          <label class="form-label">Ulangi Kata Sandi Baru *</label>
          <input type="password" name="confirm_password" class="form-input" placeholder="Ketik ulang kata sandi baru" required minlength="6">
        </div>

        <button type="submit" class="btn btn-secondary" style="margin-top: 0.5rem;">
          🔑 Ubah Kata Sandi
        </button>
      </div>
    </form>
  </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
