<?php
/**
 * Manajemen Warta Sakristi / Pengumuman - SiDinar Regina
 * Tampilan & Desain Identik 100% dengan Web Next.js (/pengurus/pengumuman)
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_pengurus();

$pdo = get_db();
$current_user = current_user();

// Handle Form Submissions
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. Tambah Warta Baru
    if ($action === 'create_announcement') {
        $judul = trim($_POST['judul'] ?? '');
        $isi = trim($_POST['isi'] ?? '');
        $is_pinned = isset($_POST['is_pinned']) ? 1 : 0;

        if ($judul && $isi) {
            $stmt = $pdo->prepare("
                INSERT INTO announcements (judul, isi, is_pinned, created_by)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$judul, $isi, $is_pinned, $current_user['id']]);
            set_flash('success', 'Warta sakristi berhasil diterbitkan ke seluruh anggota!');
            header('Location: warta.php');
            exit;
        } else {
            set_flash('error', 'Judul dan isi warta wajib diisi.');
        }
    }

    // 2. Hapus Warta
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $stmt = $pdo->prepare("DELETE FROM announcements WHERE id = ?");
            $stmt->execute([$id]);
            set_flash('success', 'Warta pengumuman berhasil dihapus.');
            header('Location: warta.php');
            exit;
        }
    }

    // 3. Toggle Sematkan / Pin
    if ($action === 'toggle_pin') {
        $id = (int)($_POST['id'] ?? 0);
        $current_pinned = (int)($_POST['is_pinned'] ?? 0);
        $new_pinned = $current_pinned ? 0 : 1;
        if ($id) {
            $stmt = $pdo->prepare("UPDATE announcements SET is_pinned = ? WHERE id = ?");
            $stmt->execute([$new_pinned, $id]);
            set_flash('success', $new_pinned ? 'Warta berhasil disematkan di posisi teratas!' : 'Sematkan warta dibatalkan.');
            header('Location: warta.php');
            exit;
        }
    }
}

// Ambil semua warta
$stmt = $pdo->query("
    SELECT a.*, p.nama_lengkap AS pembuat
    FROM announcements a
    LEFT JOIN profiles p ON a.created_by = p.id
    ORDER BY a.is_pinned DESC, a.created_at DESC
");
$announcements = $stmt->fetchAll();

$page_title = 'Warta Sakristi & Pengumuman';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex flex-col gap-6">
  <!-- Top Header identik Next.js -->
  <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div class="flex flex-col">
      <div class="flex items-center gap-2">
        <span class="material-symbols-outlined text-secondary text-xl" style="font-variation-settings: 'FILL' 1">
          campaign
        </span>
        <span class="text-xs uppercase font-bold text-secondary tracking-wider">
          Pusat Publikasi Sakristi SPMR
        </span>
      </div>
      <h1 class="font-headline font-bold text-2xl text-primary tracking-tight">
        Warta &amp; Pengumuman Sakristi
      </h1>
      <p class="text-xs text-on-surface-variant">
        Terbitkan warta liturgi, tata tertib, dan pengumuman gladi bersih untuk seluruh misdinar.
      </p>
    </div>

    <button
      type="button"
      onclick="openModal('modal-add-warta')"
      class="px-4 py-2.5 rounded-xl bg-primary text-on-primary hover:bg-primary-container text-xs font-headline font-bold transition-all shadow-xs flex items-center gap-2 self-start sm:self-auto"
    >
      <span class="material-symbols-outlined text-base">add</span>
      <span>Terbitkan Warta Baru</span>
    </button>
  </div>

  <!-- Announcements List Grid -->
  <?php if (empty($announcements)): ?>
    <div class="p-16 rounded-3xl bg-surface-container-lowest border border-surface-container text-center flex flex-col items-center gap-3">
      <span class="material-symbols-outlined text-4xl text-outline">campaign</span>
      <h3 class="font-headline font-bold text-sm text-on-surface">Belum ada warta pengumuman</h3>
      <p class="text-xs text-on-surface-variant">Klik 'Terbitkan Warta Baru' di atas untuk mempublikasikan pengumuman ke seluruh anggota.</p>
    </div>
  <?php else: ?>
    <div class="flex flex-col gap-4">
      <?php foreach ($announcements as $w): 
        $is_pinned = (bool)$w['is_pinned'];
      ?>
      <div class="p-6 rounded-3xl bg-surface-container-lowest border <?= $is_pinned ? 'border-amber-300 ring-1 ring-amber-400/40' : 'border-surface-container' ?> shadow-xs flex flex-col gap-4 hover:shadow-md transition-shadow relative overflow-hidden">
        <?php if ($is_pinned): ?>
          <div class="absolute top-0 right-0 w-16 h-16 pointer-events-none overflow-hidden">
            <div class="bg-secondary-container text-on-secondary-container text-[9px] font-bold py-1 text-center w-24 absolute top-3 -right-6 rotate-45 shadow-xs">
              PIN
            </div>
          </div>
        <?php endif; ?>

        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
          <div class="flex items-center gap-2.5">
            <?php if ($is_pinned): ?>
              <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-amber-100 text-amber-900 border border-amber-300 flex items-center gap-1">
                <span class="material-symbols-outlined text-xs" style="font-variation-settings: 'FILL' 1">push_pin</span>
                Disematkan
              </span>
            <?php endif; ?>
            <h2 class="font-headline font-bold text-base sm:text-lg text-primary">
              <?= htmlspecialchars($w['judul']) ?>
            </h2>
          </div>

          <div class="flex items-center gap-3 text-xs text-on-surface-variant">
            <span>📅 <?= format_indo_date($w['created_at']) ?></span>
            <span>•</span>
            <span>✍️ <?= htmlspecialchars($w['pembuat'] ?: 'Pengurus Sakristi') ?></span>
          </div>
        </div>

        <div class="p-4 rounded-2xl bg-surface-container-low text-xs leading-relaxed text-on-surface whitespace-pre-line border border-surface-container">
          <?= htmlspecialchars($w['isi']) ?>
        </div>

        <!-- Action Controls -->
        <div class="flex items-center justify-end gap-2 pt-2 border-t border-surface-container">
          <form method="POST" action="" class="inline">
            <input type="hidden" name="action" value="toggle_pin">
            <input type="hidden" name="id" value="<?= $w['id'] ?>">
            <input type="hidden" name="is_pinned" value="<?= $is_pinned ? 1 : 0 ?>">
            <button
              type="submit"
              class="px-3 py-1.5 rounded-xl text-xs font-semibold flex items-center gap-1.5 transition-colors <?= $is_pinned ? 'bg-amber-100 text-amber-900 hover:bg-amber-200' : 'bg-surface-container text-on-surface hover:bg-surface-container-high' ?>"
            >
              <span class="material-symbols-outlined text-sm">push_pin</span>
              <span><?= $is_pinned ? 'Lepas Sematan' : 'Sematkan ke Atas' ?></span>
            </button>
          </form>

          <form method="POST" action="" onsubmit="return confirm('Hapus warta ini?')" class="inline">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= $w['id'] ?>">
            <button
              type="submit"
              class="px-3 py-1.5 rounded-xl text-xs font-semibold bg-surface-container text-error hover:bg-error-container transition-colors flex items-center gap-1"
            >
              <span class="material-symbols-outlined text-sm">delete</span>
              <span>Hapus</span>
            </button>
          </form>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<!-- Modal Terbitkan Warta Baru -->
<div id="modal-add-warta" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs hidden items-center justify-center p-4">
  <div class="bg-surface-container-lowest border border-surface-container rounded-3xl max-w-lg w-full shadow-2xl overflow-hidden animate-scaleUp">
    <div class="p-5 border-b border-surface-container flex items-center justify-between bg-surface-container-low">
      <div class="flex items-center gap-2">
        <span class="material-symbols-outlined text-secondary text-xl" style="font-variation-settings: 'FILL' 1">campaign</span>
        <h3 class="font-headline font-bold text-base text-primary">Terbitkan Warta Sakristi Baru</h3>
      </div>
      <button type="button" onclick="closeModal('modal-add-warta')" class="w-8 h-8 rounded-full flex items-center justify-center hover:bg-surface-container text-on-surface-variant">
        <span class="material-symbols-outlined text-lg">close</span>
      </button>
    </div>

    <form method="POST" action="" class="p-5 flex flex-col gap-4 text-xs">
      <input type="hidden" name="action" value="create_announcement">

      <div>
        <label class="block font-semibold text-on-surface-variant mb-1">Judul Warta / Pengumuman *</label>
        <input
          type="text"
          name="judul"
          required
          placeholder="Contoh: Gladi Bersih Liturgi Hari Raya..."
          class="w-full px-3.5 py-2.5 rounded-xl border border-outline-variant bg-surface-container-lowest text-on-surface text-xs focus:outline-none focus:ring-2 focus:ring-primary/20"
        />
      </div>

      <div>
        <label class="block font-semibold text-on-surface-variant mb-1">Isi Pesan Pengumuman *</label>
        <textarea
          name="isi"
          rows="5"
          required
          placeholder="Tuliskan detail informasi warta sakristi di sini..."
          class="w-full px-3.5 py-2.5 rounded-xl border border-outline-variant bg-surface-container-lowest text-on-surface text-xs focus:outline-none focus:ring-2 focus:ring-primary/20 leading-relaxed"
        ></textarea>
      </div>

      <div>
        <label class="flex items-center gap-2 cursor-pointer">
          <input type="checkbox" name="is_pinned" class="rounded border-outline-variant text-primary focus:ring-primary">
          <span class="font-semibold text-on-surface">Sematkan di posisi teratas (Pinned Announcement)</span>
        </label>
      </div>

      <div class="p-3 border-t border-surface-container mt-2 flex justify-end gap-2">
        <button type="button" onclick="closeModal('modal-add-warta')" class="px-4 py-2 rounded-xl bg-surface-container-high text-on-surface font-semibold">Batal</button>
        <button type="submit" class="px-5 py-2 rounded-xl bg-primary text-on-primary font-bold hover:bg-primary-container transition-colors">Terbitkan Warta</button>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
