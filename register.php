<?php
/**
 * Halaman Registrasi Anggota Baru
 * SiDinar Regina - Sistem Informasi & Presensi Putra-Putri Altar
 */
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    header('Location: ' . (is_pengurus() ? 'pengurus/index.php' : 'anggota/index.php'));
    exit;
}

$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
    $nama_panggilan = trim($_POST['nama_panggilan'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $no_telepon = trim($_POST['no_telepon'] ?? '');
    $asal_wilayah = trim($_POST['asal_wilayah_teks'] ?? '');
    $jenjang = $_POST['jenjang'] ?? 'SMA/K';

    if (empty($nama_lengkap) || empty($nama_panggilan) || empty($email) || empty($password) || empty($no_telepon) || empty($asal_wilayah)) {
        $error = 'Semua kolom formulir pendaftaran wajib diisi.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format alamat email tidak valid.';
    } elseif (strlen($password) < 6) {
        $error = 'Kata sandi minimal terdiri dari 6 karakter.';
    } elseif ($password !== $confirm_password) {
        $error = 'Konfirmasi kata sandi tidak cocok.';
    } else {
        $pdo = get_db();

        // Cek duplikasi email
        $check = $pdo->prepare("SELECT id FROM profiles WHERE email = ?");
        $check->execute([$email]);
        if ($check->fetch()) {
            $error = 'Alamat email sudah terdaftar. Silakan gunakan email lain atau langsung masuk.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $insert = $pdo->prepare("
                INSERT INTO profiles 
                (email, password, nama_lengkap, nama_panggilan, no_telepon, asal_wilayah_teks, jenjang, role, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'anggota', 'pending')
            ");
            $insert->execute([$email, $hash, $nama_lengkap, $nama_panggilan, $no_telepon, $asal_wilayah, $jenjang]);

            set_flash('success', 'Pendaftaran berhasil! Akun Anda telah disimpan dan menunggu persetujuan (approval) dari Pengurus Sakristi.');
            header('Location: login.php');
            exit;
        }
    }
}

$page_title = 'Pendaftaran Anggota Baru';
require_once __DIR__ . '/includes/header.php';
?>

<div style="max-width: 580px; margin: 2rem auto 2rem;">
  <div class="card" style="padding: 2rem;">
    <div style="text-align: center; margin-bottom: 1.5rem;">
      <div class="brand-logo" style="width: 50px; height: 50px; font-size: 1.6rem; margin: 0 auto 0.75rem;">🕊️</div>
      <h2 style="font-size: 1.35rem; color: var(--primary);">Pendaftaran Putra-Putri Altar</h2>
      <p style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.25rem;">
        Isi data diri dengan lengkap untuk verifikasi sakristi Paroki Purbowardayan
      </p>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-error">
        <span>⚠️</span>
        <div><?= htmlspecialchars($error) ?></div>
      </div>
    <?php endif; ?>

    <form method="POST" action="">
      <div class="grid grid-cols-2" style="gap: 1rem;">
        <div class="form-group">
          <label class="form-label" for="nama_lengkap">Nama Lengkap (Sesuai Akta/Baptis)</label>
          <input type="text" id="nama_lengkap" name="nama_lengkap" class="form-control" placeholder="cth: Stefanus Natalino" value="<?= htmlspecialchars($_POST['nama_lengkap'] ?? '') ?>" required>
        </div>

        <div class="form-group">
          <label class="form-label" for="nama_panggilan">Nama Panggilan</label>
          <input type="text" id="nama_panggilan" name="nama_panggilan" class="form-control" placeholder="cth: Inok" value="<?= htmlspecialchars($_POST['nama_panggilan'] ?? '') ?>" required>
        </div>
      </div>

      <div class="grid grid-cols-2" style="gap: 1rem;">
        <div class="form-group">
          <label class="form-label" for="email">Alamat Email</label>
          <input type="email" id="email" name="email" class="form-control" placeholder="inok@gmail.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
        </div>

        <div class="form-group">
          <label class="form-label" for="no_telepon">No. WhatsApp Aktif</label>
          <input type="text" id="no_telepon" name="no_telepon" class="form-control" placeholder="081234567890" value="<?= htmlspecialchars($_POST['no_telepon'] ?? '') ?>" required>
        </div>
      </div>

      <div class="grid grid-cols-2" style="gap: 1rem;">
        <div class="form-group">
          <label class="form-label" for="asal_wilayah_teks">Asal Wilayah &amp; Lingkungan</label>
          <input type="text" id="asal_wilayah_teks" name="asal_wilayah_teks" class="form-control" placeholder="cth: Jebres Barat, St. Rosa" value="<?= htmlspecialchars($_POST['asal_wilayah_teks'] ?? '') ?>" required>
        </div>

        <div class="form-group">
          <label class="form-label" for="jenjang">Jenjang Pendidikan Saat Ini</label>
          <select id="jenjang" name="jenjang" class="form-control">
            <?php foreach (JENJANG_OPTIONS as $opt): ?>
              <option value="<?= $opt ?>" <?= ($_POST['jenjang'] ?? 'SMA/K') === $opt ? 'selected' : '' ?>><?= $opt ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="grid grid-cols-2" style="gap: 1rem;">
        <div class="form-group">
          <label class="form-label" for="password">Kata Sandi (Min. 6 Karakter)</label>
          <input type="password" id="password" name="password" class="form-control" placeholder="••••••••" required>
        </div>

        <div class="form-group">
          <label class="form-label" for="confirm_password">Ulangi Kata Sandi</label>
          <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="••••••••" required>
        </div>
      </div>

      <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.75rem; font-size: 0.9rem; margin-top: 0.75rem;">
        Kirim Pendaftaran
      </button>
    </form>

    <div style="margin-top: 1.25rem; text-align: center; font-size: 0.8rem; color: var(--text-muted);">
      Sudah memiliki akun terverifikasi? 
      <a href="login.php" style="font-weight: 700;">Masuk di Sini</a>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
