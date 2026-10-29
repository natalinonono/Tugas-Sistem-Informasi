<?php
/**
 * Halaman Pendaftaran Anggota Baru - SiDinar Regina
 * Tampilan & Desain Identik 100% dengan Next.js Web UI
 */
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    header('Location: ' . (is_pengurus() ? 'pengurus/index.php' : 'anggota/index.php'));
    exit;
}

$error = '';
$success = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
    $nama_panggilan = trim($_POST['nama_panggilan'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $no_telepon = trim($_POST['no_telepon'] ?? '');
    $asal_wilayah = trim($_POST['asal_wilayah_teks'] ?? '');
    $jenjang = $_POST['jenjang'] ?? 'SMP';
    $commitment = isset($_POST['commitment']);

    if (!$commitment) {
        $error = 'Anda wajib mencentang persetujuan komitmen pelayanan sakristi.';
    } elseif (empty($nama_lengkap) || empty($nama_panggilan) || empty($email) || empty($password) || empty($no_telepon) || empty($asal_wilayah)) {
        $error = 'Semua kolom formulir pendaftaran wajib diisi.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format alamat email tidak valid.';
    } elseif (strlen($password) < 6) {
        $error = 'Kata sandi minimal terdiri dari 6 karakter.';
    } elseif ($password !== $confirm_password) {
        $error = 'Konfirmasi kata sandi tidak cocok.';
    } else {
        $pdo = get_db();

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

<div class="relative w-full flex items-center justify-center py-6 sm:py-12 overflow-hidden">
  <!-- Marian Decorative Ambient Accent Blurs identik Next.js -->
  <div class="absolute -top-24 -right-24 w-80 h-80 bg-primary-fixed/30 rounded-full blur-3xl pointer-events-none"></div>
  <div class="absolute -bottom-24 -left-24 w-72 h-72 bg-secondary-fixed/40 rounded-full blur-3xl pointer-events-none"></div>

  <div class="w-full max-w-lg relative z-10">
    <div class="overflow-hidden bg-surface-container-lowest rounded-3xl shadow-xl border border-surface-container">
      <div class="p-6 sm:p-8 flex flex-col gap-6">

        <!-- Header Brand -->
        <div class="flex flex-col items-center text-center space-y-2">
          <div class="w-16 h-16 rounded-full bg-surface-container-lowest shadow-md p-1.5 flex items-center justify-center border border-surface-container">
            <img
              src="<?= $base ?>/assets/img/logo.png"
              alt="Logo Misdinar SPMR"
              class="w-full h-full object-contain rounded-full"
            />
          </div>
          <h1 class="font-headline font-bold text-xl text-primary tracking-tight">
            Pendaftaran Anggota SiDinar
          </h1>
          <p class="text-xs text-on-surface-variant">
            Putra-Putri Altar Paroki SPMR Purbowardayan
          </p>
        </div>

        <!-- Segmented Tab Navigation identik Next.js -->
        <div class="grid grid-cols-2 p-1 bg-surface-container rounded-xl">
          <a
            href="<?= $base ?>/login.php"
            class="py-2 text-center rounded-lg text-xs font-headline font-medium text-on-surface-variant hover:text-primary transition-all flex items-center justify-center"
          >
            Masuk
          </a>
          <button
            type="button"
            class="py-2 text-center rounded-lg text-xs font-headline font-bold bg-surface-container-lowest text-primary shadow-xs transition-all"
          >
            Daftar Akun Baru
          </button>
        </div>

        <?php if ($error): ?>
          <div class="p-3.5 rounded-2xl bg-error-container text-on-error-container text-xs flex items-center gap-2 border border-error/20">
            <span class="material-symbols-outlined text-lg text-error">error</span>
            <span><?= htmlspecialchars($error) ?></span>
          </div>
        <?php endif; ?>

        <!-- Form Registrasi -->
        <form method="POST" action="" class="flex flex-col gap-4">
          <!-- Nama Lengkap & Panggilan -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label for="nama_lengkap" class="block text-xs font-semibold text-on-surface-variant mb-1">
                Nama Lengkap Sesuai Akta *
              </label>
              <input
                type="text"
                id="nama_lengkap"
                name="nama_lengkap"
                required
                value="<?= htmlspecialchars($_POST['nama_lengkap'] ?? '') ?>"
                placeholder="Yohanes Baptista..."
                class="w-full px-3.5 py-2.5 rounded-xl border border-outline-variant bg-surface-container-lowest text-on-surface text-xs focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all"
              />
            </div>
            <div>
              <label for="nama_panggilan" class="block text-xs font-semibold text-on-surface-variant mb-1">
                Nama Panggilan Sakristi *
              </label>
              <input
                type="text"
                id="nama_panggilan"
                name="nama_panggilan"
                required
                value="<?= htmlspecialchars($_POST['nama_panggilan'] ?? '') ?>"
                placeholder="Baptista"
                class="w-full px-3.5 py-2.5 rounded-xl border border-outline-variant bg-surface-container-lowest text-on-surface text-xs focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all"
              />
            </div>
          </div>

          <!-- Email & No Telepon -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label for="email" class="block text-xs font-semibold text-on-surface-variant mb-1">
                Alamat Email Login *
              </label>
              <input
                type="email"
                id="email"
                name="email"
                required
                value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                placeholder="misdinar@example.com"
                class="w-full px-3.5 py-2.5 rounded-xl border border-outline-variant bg-surface-container-lowest text-on-surface text-xs focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all"
              />
            </div>
            <div>
              <label for="no_telepon" class="block text-xs font-semibold text-on-surface-variant mb-1">
                No. WhatsApp Aktif *
              </label>
              <input
                type="tel"
                id="no_telepon"
                name="no_telepon"
                required
                value="<?= htmlspecialchars($_POST['no_telepon'] ?? '') ?>"
                placeholder="08xxxxxxxxxx"
                class="w-full px-3.5 py-2.5 rounded-xl border border-outline-variant bg-surface-container-lowest text-on-surface text-xs focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all"
              />
            </div>
          </div>

          <!-- Asal Wilayah & Jenjang -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label for="asal_wilayah_teks" class="block text-xs font-semibold text-on-surface-variant mb-1">
                Lingkungan / Wilayah *
              </label>
              <input
                type="text"
                id="asal_wilayah_teks"
                name="asal_wilayah_teks"
                required
                value="<?= htmlspecialchars($_POST['asal_wilayah_teks'] ?? '') ?>"
                placeholder="Jebres, Wilayah St. Ignatius"
                class="w-full px-3.5 py-2.5 rounded-xl border border-outline-variant bg-surface-container-lowest text-on-surface text-xs focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all"
              />
            </div>
            <div>
              <label for="jenjang" class="block text-xs font-semibold text-on-surface-variant mb-1">
                Jenjang Pendidikan *
              </label>
              <select
                id="jenjang"
                name="jenjang"
                required
                class="w-full px-3 py-2.5 rounded-xl border border-outline-variant bg-surface-container-lowest text-on-surface text-xs focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all"
              >
                <?php
                $jenjang_opts = ['SD', 'SMP', 'SMA/K', 'Mahasiswa', 'Umum'];
                $curr_jenjang = $_POST['jenjang'] ?? 'SMP';
                foreach ($jenjang_opts as $j):
                ?>
                  <option value="<?= $j ?>" <?= $curr_jenjang === $j ? 'selected' : '' ?>><?= $j ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <!-- Kata Sandi & Konfirmasi -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label for="password" class="block text-xs font-semibold text-on-surface-variant mb-1">
                Kata Sandi *
              </label>
              <input
                type="password"
                id="password"
                name="password"
                required
                minlength="6"
                placeholder="Min. 6 karakter"
                class="w-full px-3.5 py-2.5 rounded-xl border border-outline-variant bg-surface-container-lowest text-on-surface text-xs focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all"
              />
            </div>
            <div>
              <label for="confirm_password" class="block text-xs font-semibold text-on-surface-variant mb-1">
                Ulangi Kata Sandi *
              </label>
              <input
                type="password"
                id="confirm_password"
                name="confirm_password"
                required
                minlength="6"
                placeholder="Ulangi sandi"
                class="w-full px-3.5 py-2.5 rounded-xl border border-outline-variant bg-surface-container-lowest text-on-surface text-xs focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all"
              />
            </div>
          </div>

          <!-- Komitmen Checkbox -->
          <div class="pt-2">
            <label class="flex items-start gap-2.5 cursor-pointer">
              <input
                type="checkbox"
                name="commitment"
                id="commitment"
                required
                class="mt-0.5 rounded border-outline-variant text-primary focus:ring-primary"
              />
              <span class="text-[11px] text-on-surface-variant leading-relaxed">
                Saya menyatakan siap melayani sebagai Putra-Putri Altar SPMR Purbowardayan dengan setia, tertib, dan mematuhi tata tertib sakristi.
              </span>
            </label>
          </div>

          <button
            type="submit"
            class="w-full py-3 px-4 rounded-xl font-headline font-bold text-xs bg-primary text-on-primary hover:bg-primary-container shadow-md transition-all flex items-center justify-center gap-2 mt-2"
          >
            <span class="material-symbols-outlined text-lg">app_registration</span>
            <span>Kirim Pendaftaran Anggota</span>
          </button>
        </form>

        <div class="text-center text-xs text-on-surface-variant border-t border-surface-container pt-3">
          Sudah punya akun misdinar?{' '}
          <a href="<?= $base ?>/login.php" class="font-bold text-primary hover:underline">
            Masuk Sekarang &rarr;
          </a>
        </div>

      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
