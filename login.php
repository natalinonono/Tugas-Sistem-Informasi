<?php
/**
 * Halaman Masuk Akun (Login)
 * SiDinar Regina - Sistem Informasi & Presensi Putra-Putri Altar
 */
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    header('Location: ' . (is_pengurus() ? 'pengurus/index.php' : 'anggota/index.php'));
    exit;
}

$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Email dan kata sandi wajib diisi.';
    } else {
        $pdo = get_db();
        $stmt = $pdo->prepare("SELECT * FROM profiles WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            if ($user['status'] === 'pending') {
                $error = 'Akun Anda (' . htmlspecialchars($user['nama_lengkap']) . ') masih berstatus PENDING. Harap menunggu verifikasi persetujuan dari Pengurus Sakristi.';
            } elseif ($user['status'] === 'nonaktif') {
                $error = 'Akun Anda dinonaktifkan oleh pengurus. Hubungi sakristi untuk informasi lebih lanjut.';
            } else {
                // Sesi valid
                unset($user['password']);
                $_SESSION['user'] = $user;
                set_flash('success', 'Selamat datang kembali, ' . ($user['nama_panggilan'] ?: $user['nama_lengkap']) . '! Berkah Dalem. 🙏');
                header('Location: ' . ($user['role'] === 'pengurus' ? 'pengurus/index.php' : 'anggota/index.php'));
                exit;
            }
        } else {
            $error = 'Email atau kata sandi tidak cocok. Silakan periksa kembali.';
        }
    }
}

$page_title = 'Masuk Akun';
require_once __DIR__ . '/includes/header.php';
?>

<div style="max-width: 440px; margin: 2.5rem auto 1rem;">
  <div class="card" style="padding: 2rem;">
    <div style="text-align: center; margin-bottom: 1.5rem;">
      <div class="brand-logo" style="width: 54px; height: 54px; font-size: 1.8rem; margin: 0 auto 0.75rem;">⛪</div>
      <h2 style="font-size: 1.35rem; color: var(--primary);">Masuk ke SiDinar Regina</h2>
      <p style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.25rem;">
        Sistem Informasi &amp; Presensi Digital Putra-Putri Altar SPMR
      </p>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-error">
        <span>⚠️</span>
        <div><?= htmlspecialchars($error) ?></div>
      </div>
    <?php endif; ?>

    <form method="POST" action="">
      <div class="form-group">
        <label class="form-label" for="email">Alamat Email</label>
        <input type="email" id="email" name="email" class="form-control" placeholder="nama@sidinar.org" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autofocus>
      </div>

      <div class="form-group">
        <label class="form-label" for="password">Kata Sandi</label>
        <input type="password" id="password" name="password" class="form-control" placeholder="••••••••" required>
      </div>

      <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.75rem; font-size: 0.9rem; margin-top: 0.5rem;">
        Masuk Akun
      </button>
    </form>

    <!-- Tombol Akses Cepat Demo (Sangat membantu guru/penguji saat demo ujian) -->
    <div style="margin-top: 1.5rem; padding-top: 1.25rem; border-top: 1px dashed var(--border); text-align: center;">
      <div style="font-size: 0.72rem; color: var(--text-dim); text-transform: uppercase; font-weight: 700; margin-bottom: 0.6rem;">
        ⚡ Akses Cepat Demo Pengujian:
      </div>
      <div style="display: flex; gap: 0.5rem; justify-content: center;">
        <button type="button" class="btn btn-secondary btn-sm" onclick="fillDemo('pengurus@sidinar.org')">
          👤 Akun Pengurus
        </button>
        <button type="button" class="btn btn-secondary btn-sm" onclick="fillDemo('misdinar@sidinar.org')">
          ⛪ Akun Anggota
        </button>
      </div>
    </div>

    <div style="margin-top: 1.25rem; text-align: center; font-size: 0.8rem; color: var(--text-muted);">
      Belum memiliki akun misdinar? 
      <a href="register.php" style="font-weight: 700;">Daftar Akun Baru</a>
    </div>
  </div>
</div>

<script>
function fillDemo(email) {
  document.getElementById('email').value = email;
  document.getElementById('password').value = 'password123';
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
