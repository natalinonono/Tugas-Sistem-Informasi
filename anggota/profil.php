<?php
/**
 * Profil Saya & Pengaturan Akun (Anggota / Pengurus)
 * SiDinar Regina - Sistem Informasi & Presensi Putra-Putri Altar
 * Desain & UI Identik 100% dengan Next.js Dashboard
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

<div class="flex flex-col gap-6">
  <!-- Page Header Identik Next.js -->
  <div class="flex flex-col gap-1">
    <div class="flex items-center gap-2">
      <span class="material-symbols-outlined text-primary text-xl" style="font-variation-settings: 'FILL' 1;">
        badge
      </span>
      <span class="text-xs uppercase font-bold text-secondary tracking-wider">
        Pengaturan Akun &amp; Identitas
      </span>
    </div>
    <h1 class="font-headline font-bold text-2xl text-primary tracking-tight">
      Profil Saya
    </h1>
    <p class="text-xs text-on-surface-variant">
      Kelola data identitas misdinar, nomor kontak WhatsApp, dan kata sandi keamanan Anda.
    </p>
  </div>

  <!-- User Card Identity Banner -->
  <div class="p-6 rounded-3xl bg-primary text-on-primary shadow-sm relative overflow-hidden flex flex-col sm:flex-row items-center sm:items-start gap-5">
    <div class="absolute -right-10 -bottom-10 w-48 h-48 bg-white/5 rounded-full blur-xl pointer-events-none"></div>

    <div class="w-20 h-20 rounded-full bg-white/10 border-2 border-white/20 flex items-center justify-center shrink-0 text-white font-headline font-bold text-2xl shadow-inner">
      <?= strtoupper(substr($profile['nama_panggilan'] ?: $profile['nama_lengkap'], 0, 2)) ?>
    </div>

    <div class="flex-1 text-center sm:text-left">
      <div class="flex flex-wrap items-center justify-center sm:justify-start gap-2 mb-1">
        <h2 class="font-headline font-bold text-xl sm:text-2xl tracking-tight">
          <?= htmlspecialchars($profile['nama_lengkap']) ?>
        </h2>
        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider <?= $profile['role'] === 'pengurus' ? 'bg-secondary text-on-secondary' : 'bg-white/20 text-white' ?>">
          <?= $profile['role'] === 'pengurus' ? 'Pengurus Inti' : 'Misdinar Aktif' ?>
        </span>
      </div>
      <p class="text-xs text-on-primary-container font-medium mb-3">
        <?= htmlspecialchars($profile['email']) ?> &bull; Nama Panggilan: <strong><?= htmlspecialchars($profile['nama_panggilan']) ?></strong>
      </p>

      <div class="flex flex-wrap items-center justify-center sm:justify-start gap-2 text-xs">
        <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full bg-white/10 text-white border border-white/15">
          <span class="material-symbols-outlined text-[14px]">school</span>
          Jenjang <?= htmlspecialchars($profile['jenjang']) ?>
        </span>
        <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full bg-white/10 text-white border border-white/15">
          <span class="material-symbols-outlined text-[14px]">church</span>
          <?= htmlspecialchars($profile['asal_wilayah_teks']) ?>
        </span>
        <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full bg-white/10 text-white border border-white/15">
          <span class="material-symbols-outlined text-[14px]">call</span>
          <?= htmlspecialchars($profile['no_telepon']) ?>
        </span>
      </div>
    </div>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">
    <!-- FORM 1: BIODATA MISDINAR -->
    <div class="p-6 rounded-3xl bg-surface-container-lowest border border-surface-container shadow-sm flex flex-col gap-5">
      <div class="flex items-center justify-between pb-3 border-b border-surface-container">
        <div class="flex items-center gap-2">
          <span class="material-symbols-outlined text-primary text-xl">contact_page</span>
          <h3 class="font-headline font-bold text-base text-primary">Biodata Anggota</h3>
        </div>
        <span class="inline-block px-2.5 py-0.5 rounded-full text-[11px] font-bold uppercase <?= $profile['status'] === 'aktif' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' ?>">
          Status: <?= ucfirst($profile['status']) ?>
        </span>
      </div>

      <form method="POST" action="profil.php" class="flex flex-col gap-4">
        <input type="hidden" name="action" value="update_profile">

        <div>
          <label class="block text-xs font-bold text-on-surface uppercase tracking-wider mb-1.5">
            Email Akun (Login)
          </label>
          <div class="relative">
            <span class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-on-surface-variant text-base">mail</span>
            <input type="email" value="<?= htmlspecialchars($profile['email']) ?>" disabled class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-surface-container bg-surface-container-low text-xs text-on-surface-variant font-mono cursor-not-allowed">
          </div>
          <span class="text-[11px] text-on-surface-variant/70 mt-1 block">Email akun terikat dan tidak dapat diubah sendiri.</span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-bold text-on-surface uppercase tracking-wider mb-1.5">
              Nama Lengkap *
            </label>
            <div class="relative">
              <span class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-on-surface-variant text-base">person</span>
              <input type="text" name="nama_lengkap" value="<?= htmlspecialchars($profile['nama_lengkap']) ?>" required class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-surface-container focus:border-primary focus:ring-1 focus:ring-primary text-xs text-on-surface">
            </div>
          </div>
          <div>
            <label class="block text-xs font-bold text-on-surface uppercase tracking-wider mb-1.5">
              Nama Panggilan *
            </label>
            <div class="relative">
              <span class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-on-surface-variant text-base">sentiment_satisfied</span>
              <input type="text" name="nama_panggilan" value="<?= htmlspecialchars($profile['nama_panggilan']) ?>" required class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-surface-container focus:border-primary focus:ring-1 focus:ring-primary text-xs text-on-surface">
            </div>
          </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-bold text-on-surface uppercase tracking-wider mb-1.5">
              Nomor WhatsApp *
            </label>
            <div class="relative">
              <span class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-on-surface-variant text-base">call</span>
              <input type="tel" name="no_telepon" value="<?= htmlspecialchars($profile['no_telepon']) ?>" required class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-surface-container focus:border-primary focus:ring-1 focus:ring-primary text-xs text-on-surface">
            </div>
          </div>
          <div>
            <label class="block text-xs font-bold text-on-surface uppercase tracking-wider mb-1.5">
              Jenjang Pendidikan *
            </label>
            <div class="relative">
              <span class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-on-surface-variant text-base">school</span>
              <select name="jenjang" required class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-surface-container focus:border-primary focus:ring-1 focus:ring-primary text-xs text-on-surface bg-white">
                <?php foreach (JENJANG_OPTIONS as $j): ?>
                  <option value="<?= $j ?>" <?= $profile['jenjang'] === $j ? 'selected' : '' ?>><?= $j ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
        </div>

        <div>
          <label class="block text-xs font-bold text-on-surface uppercase tracking-wider mb-1.5">
            Asal Wilayah / Lingkungan Gereja *
          </label>
          <div class="relative">
            <span class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-on-surface-variant text-base">church</span>
            <input type="text" name="asal_wilayah_teks" value="<?= htmlspecialchars($profile['asal_wilayah_teks']) ?>" placeholder="Contoh: Jebres Barat, St. Theresia" required class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-surface-container focus:border-primary focus:ring-1 focus:ring-primary text-xs text-on-surface">
          </div>
        </div>

        <button type="submit" class="mt-2 w-full sm:w-auto self-start inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl bg-primary text-on-primary text-xs font-semibold hover:bg-primary/90 transition-all shadow-xs">
          <span class="material-symbols-outlined text-sm">save</span>
          Simpan Perubahan Profil
        </button>
      </form>
    </div>

    <!-- FORM 2: GANTI KATA SANDI -->
    <div class="p-6 rounded-3xl bg-surface-container-lowest border border-surface-container shadow-sm flex flex-col gap-5">
      <div class="flex items-center justify-between pb-3 border-b border-surface-container">
        <div class="flex items-center gap-2">
          <span class="material-symbols-outlined text-primary text-xl">lock_reset</span>
          <h3 class="font-headline font-bold text-base text-primary">Keamanan &amp; Kata Sandi</h3>
        </div>
      </div>

      <form method="POST" action="profil.php" class="flex flex-col gap-4">
        <input type="hidden" name="action" value="change_password">

        <div>
          <label class="block text-xs font-bold text-on-surface uppercase tracking-wider mb-1.5">
            Kata Sandi Saat Ini *
          </label>
          <div class="relative">
            <span class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-on-surface-variant text-base">lock</span>
            <input type="password" name="old_password" required placeholder="Masukkan kata sandi lama Anda" class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-surface-container focus:border-primary focus:ring-1 focus:ring-primary text-xs text-on-surface">
          </div>
        </div>

        <div>
          <label class="block text-xs font-bold text-on-surface uppercase tracking-wider mb-1.5">
            Kata Sandi Baru *
          </label>
          <div class="relative">
            <span class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-on-surface-variant text-base">key</span>
            <input type="password" name="new_password" required minlength="6" placeholder="Minimal 6 karakter baru" class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-surface-container focus:border-primary focus:ring-1 focus:ring-primary text-xs text-on-surface">
          </div>
        </div>

        <div>
          <label class="block text-xs font-bold text-on-surface uppercase tracking-wider mb-1.5">
            Ulangi Kata Sandi Baru *
          </label>
          <div class="relative">
            <span class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-on-surface-variant text-base">verified_user</span>
            <input type="password" name="confirm_password" required minlength="6" placeholder="Ketik ulang kata sandi baru" class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-surface-container focus:border-primary focus:ring-1 focus:ring-primary text-xs text-on-surface">
          </div>
        </div>

        <button type="submit" class="mt-2 w-full sm:w-auto self-start inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl bg-surface-container-high hover:bg-surface-container-highest text-primary text-xs font-semibold transition-all border border-surface-container">
          <span class="material-symbols-outlined text-sm">vpn_key</span>
          Ubah Kata Sandi
        </button>
      </form>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
