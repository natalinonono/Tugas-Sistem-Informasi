<?php
/**
 * Manajemen Anggota & Verifikasi Pendaftaran - SiDinar Regina
 * Tampilan & Desain Identik 100% dengan Web Next.js (/pengurus/anggota)
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
            header('Location: anggota.php?status=aktif');
            exit;
        }
    }

    // 2. Ubah Status (Aktif / Nonaktif)
    if ($action === 'toggle_status') {
        $user_id = (int)($_POST['user_id'] ?? 0);
        $status = $_POST['status'] ?? 'aktif';
        if ($user_id && in_array($status, ['aktif', 'nonaktif', 'pending'])) {
            $stmt = $pdo->prepare("UPDATE profiles SET status = ? WHERE id = ?");
            $stmt->execute([$status, $user_id]);
            set_flash('success', "Status anggota berhasil diubah menjadi: " . ucfirst($status));
            header('Location: anggota.php?status=' . $status);
            exit;
        }
    }

    // 3. Tolak / Hapus Anggota
    if ($action === 'delete') {
        $user_id = (int)($_POST['user_id'] ?? 0);
        if ($user_id && $user_id !== (int)$current_user['id']) {
            $stmt = $pdo->prepare("DELETE FROM profiles WHERE id = ?");
            $stmt->execute([$user_id]);
            set_flash('success', 'Data anggota berhasil dihapus dari sistem.');
            header('Location: anggota.php');
            exit;
        } else {
            set_flash('error', 'Anda tidak dapat menghapus akun Anda sendiri.');
        }
    }
}

$status_filter = $_GET['status'] ?? 'aktif';
$search = trim($_GET['q'] ?? '');

$sql = "SELECT * FROM profiles WHERE 1=1";
$params = [];

if ($status_filter !== 'semua' && in_array($status_filter, ['pending', 'aktif', 'nonaktif'])) {
    $sql .= " AND status = ?";
    $params[] = $status_filter;
}

if ($search) {
    $sql .= " AND (nama_lengkap LIKE ? OR nama_panggilan LIKE ? OR email LIKE ? OR asal_wilayah_teks LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$members = $stmt->fetchAll();

// Counters
$cntPending = (int)$pdo->query("SELECT COUNT(*) FROM profiles WHERE status = 'pending'")->fetchColumn();
$cntAktif = (int)$pdo->query("SELECT COUNT(*) FROM profiles WHERE status = 'aktif'")->fetchColumn();
$cntNonaktif = (int)$pdo->query("SELECT COUNT(*) FROM profiles WHERE status = 'nonaktif'")->fetchColumn();
$cntSemua = (int)$pdo->query("SELECT COUNT(*) FROM profiles")->fetchColumn();

$page_title = 'Direktori & Master Data Anggota';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex flex-col gap-6">
  <!-- Top Header identik Next.js -->
  <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div class="flex flex-col">
      <div class="flex items-center gap-2">
        <span class="material-symbols-outlined text-primary text-xl" style="font-variation-settings: 'FILL' 1">
          badge
        </span>
        <span class="text-xs uppercase font-bold text-secondary tracking-wider">
          Master Database Putra-Putri Altar
        </span>
      </div>
      <h1 class="font-headline font-bold text-2xl text-primary tracking-tight">
        Direktori Anggota Misdinar
      </h1>
      <p class="text-xs text-on-surface-variant">
        Daftar seluruh putra-putri altar, informasi kontak, dan pengelolaan status keaktifan sakristi.
      </p>
    </div>

    <!-- Filter Segmented Control identik Next.js -->
    <div class="inline-flex p-1 bg-surface-container rounded-2xl self-start sm:self-auto gap-1">
      <a
        href="?status=aktif"
        class="px-3 py-1.5 rounded-xl text-xs font-headline transition-all <?= $status_filter === 'aktif' ? 'bg-surface-container-lowest text-primary font-bold shadow-xs' : 'text-on-surface-variant hover:text-primary' ?>"
      >
        Aktif (<?= $cntAktif ?>)
      </a>
      <a
        href="?status=semua"
        class="px-3 py-1.5 rounded-xl text-xs font-headline transition-all <?= $status_filter === 'semua' ? 'bg-surface-container-lowest text-primary font-bold shadow-xs' : 'text-on-surface-variant hover:text-primary' ?>"
      >
        Semua (<?= $cntSemua ?>)
      </a>
      <?php if ($cntPending > 0): ?>
      <a
        href="?status=pending"
        class="px-3 py-1.5 rounded-xl text-xs font-headline transition-all flex items-center gap-1.5 <?= $status_filter === 'pending' ? 'bg-surface-container-lowest text-primary font-bold shadow-xs' : 'text-on-surface-variant hover:text-primary' ?>"
      >
        <span>Pending</span>
        <span class="px-1.5 py-0.2 rounded-full text-[10px] font-bold bg-amber-500 text-white"><?= $cntPending ?></span>
      </a>
      <?php endif; ?>
      <a
        href="?status=nonaktif"
        class="px-3 py-1.5 rounded-xl text-xs font-headline transition-all <?= $status_filter === 'nonaktif' ? 'bg-surface-container-lowest text-primary font-bold shadow-xs' : 'text-on-surface-variant hover:text-primary' ?>"
      >
        Nonaktif (<?= $cntNonaktif ?>)
      </a>
    </div>
  </div>

  <!-- Search Input Bar -->
  <form method="GET" action="" class="flex items-center gap-3 p-3 rounded-2xl bg-surface-container-lowest border border-surface-container">
    <input type="hidden" name="status" value="<?= htmlspecialchars($status_filter) ?>">
    <div class="relative flex-1">
      <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline text-lg">
        search
      </span>
      <input
        type="text"
        name="q"
        value="<?= htmlspecialchars($search) ?>"
        placeholder="Cari misdinar berdasarkan nama, email, atau wilayah..."
        class="w-full pl-9 pr-3.5 py-2 rounded-xl bg-surface-container-low text-xs border border-surface-container focus:outline-none focus:ring-2 focus:ring-primary/20"
      />
    </div>
    <button type="submit" class="px-4 py-2 rounded-xl bg-primary text-on-primary text-xs font-bold hover:bg-primary-container transition-colors">
      Cari
    </button>
  </form>

  <!-- Members Directory Grid -->
  <?php if (empty($members)): ?>
    <div class="p-16 rounded-3xl bg-surface-container-lowest border border-surface-container text-center flex flex-col items-center gap-3">
      <span class="material-symbols-outlined text-4xl text-outline">group_off</span>
      <h3 class="font-headline font-bold text-sm text-on-surface">Tidak ada anggota yang ditemukan</h3>
      <p class="text-xs text-on-surface-variant">Tidak ada data misdinar pada filter atau pencarian saat ini.</p>
    </div>
  <?php else: ?>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      <?php foreach ($members as $m): 
        $status = $m['status'];
        $status_badge = match($status) {
          'aktif' => 'bg-emerald-100 text-emerald-800',
          'pending' => 'bg-amber-100 text-amber-800',
          default => 'bg-error-container text-on-error-container'
        };
      ?>
      <div class="p-5 rounded-3xl bg-surface-container-lowest border border-surface-container shadow-xs flex flex-col justify-between gap-4 hover:shadow-md transition-shadow">
        <div class="flex flex-col gap-3">
          <!-- Top Row: Avatar & Badges -->
          <div class="flex items-start justify-between gap-3">
            <div class="flex items-center gap-3">
              <div class="w-11 h-11 rounded-2xl bg-primary-fixed text-primary font-bold text-sm flex items-center justify-center shrink-0 border border-primary-fixed-dim">
                <?= strtoupper(substr($m['nama_panggilan'] ?: $m['nama_lengkap'], 0, 2)) ?>
              </div>
              <div class="flex flex-col">
                <span class="font-headline font-bold text-sm text-on-surface leading-tight">
                  <?= htmlspecialchars($m['nama_lengkap']) ?>
                </span>
                <span class="text-xs text-secondary font-semibold">
                  "<?= htmlspecialchars($m['nama_panggilan']) ?>"
                </span>
              </div>
            </div>

            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider <?= $status_badge ?>">
              <?= $status ?>
            </span>
          </div>

          <!-- Member Details -->
          <div class="p-3 rounded-2xl bg-surface-container-low border border-surface-container flex flex-col gap-1.5 text-xs">
            <div class="flex items-center gap-2 text-on-surface-variant">
              <span class="material-symbols-outlined text-sm text-primary">mail</span>
              <span class="truncate"><?= htmlspecialchars($m['email']) ?></span>
            </div>
            <div class="flex items-center gap-2 text-on-surface-variant">
              <span class="material-symbols-outlined text-sm text-emerald-700">chat</span>
              <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $m['no_telepon']) ?>" target="_blank" class="text-primary hover:underline font-mono">
                <?= htmlspecialchars($m['no_telepon']) ?>
              </a>
            </div>
            <div class="flex items-center gap-2 text-on-surface-variant">
              <span class="material-symbols-outlined text-sm text-secondary">location_on</span>
              <span class="truncate"><?= htmlspecialchars($m['asal_wilayah_teks']) ?></span>
            </div>
            <div class="flex items-center gap-2 text-on-surface-variant">
              <span class="material-symbols-outlined text-sm text-outline">school</span>
              <span>Jenjang <?= htmlspecialchars($m['jenjang']) ?></span>
            </div>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="pt-2 border-t border-surface-container flex items-center gap-2">
          <?php if ($status === 'pending'): ?>
            <form method="POST" action="" class="flex-1">
              <input type="hidden" name="action" value="approve">
              <input type="hidden" name="user_id" value="<?= $m['id'] ?>">
              <button
                type="submit"
                class="w-full py-2.5 px-3 rounded-xl bg-primary text-on-primary hover:bg-primary-container text-xs font-bold transition-colors flex items-center justify-center gap-1.5 shadow-xs"
              >
                <span class="material-symbols-outlined text-sm">check_circle</span>
                <span>Setujui Anggota</span>
              </button>
            </form>
          <?php elseif ($status === 'aktif'): ?>
            <form method="POST" action="" class="flex-1">
              <input type="hidden" name="action" value="toggle_status">
              <input type="hidden" name="user_id" value="<?= $m['id'] ?>">
              <input type="hidden" name="status" value="nonaktif">
              <button
                type="submit"
                class="w-full py-2 px-3 rounded-xl bg-surface-container text-on-surface-variant hover:bg-error-container hover:text-error text-xs font-semibold transition-colors"
                onclick="return confirm('Nonaktifkan akun misdinar ini?')"
              >
                Nonaktifkan
              </button>
            </form>
          <?php else: ?>
            <form method="POST" action="" class="flex-1">
              <input type="hidden" name="action" value="toggle_status">
              <input type="hidden" name="user_id" value="<?= $m['id'] ?>">
              <input type="hidden" name="status" value="aktif">
              <button
                type="submit"
                class="w-full py-2 px-3 rounded-xl bg-emerald-100 text-emerald-800 hover:bg-emerald-200 text-xs font-semibold transition-colors"
              >
                Aktifkan Kembali
              </button>
            </form>
          <?php endif; ?>

          <?php if ((int)$m['id'] !== (int)$current_user['id']): ?>
          <form method="POST" action="" onsubmit="return confirm('Hapus permanen akun misdinar ini?')" class="inline">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="user_id" value="<?= $m['id'] ?>">
            <button
              type="submit"
              class="w-9 h-9 rounded-xl flex items-center justify-center bg-surface-container-high hover:bg-error-container text-on-surface-variant hover:text-error transition-colors"
              title="Hapus Akun"
            >
              <span class="material-symbols-outlined text-base">delete</span>
            </button>
          </form>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
