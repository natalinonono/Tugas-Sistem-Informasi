<?php
/**
 * Manajemen Warta Sakristi / Pengumuman (Pengurus)
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

// Ambil semua warta sakristi
$stmt = $pdo->query("
    SELECT a.*, p.nama_lengkap, p.nama_panggilan 
    FROM announcements a
    LEFT JOIN profiles p ON a.created_by = p.id
    ORDER BY a.is_pinned DESC, a.created_at DESC
");
$announcements = $stmt->fetchAll();

$page_title = 'Warta Sakristi & Pengumuman';
require_once __DIR__ . '/../includes/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem;">
  <div>
    <h1 style="font-size: 1.6rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.25rem;">
      📢 Warta Sakristi & Pengumuman
    </h1>
    <p style="color: var(--text-muted); font-size: 0.95rem;">
      Publikasikan informasi penting, jadwal gladi liturgi, tata tertib sakristi, dan warta kegiatan misdinar.
    </p>
  </div>

  <button type="button" class="btn btn-primary" onclick="openModal('modal-tambah-warta')">
    ➕ Terbitkan Warta Baru
  </button>
</div>

<!-- Daftar Warta -->
<?php if (empty($announcements)): ?>
  <div class="card" style="text-align: center; padding: 3rem 1.5rem; color: var(--text-muted);">
    <div style="font-size: 3rem; margin-bottom: 0.5rem;">📢</div>
    <h3 style="font-size: 1.2rem; font-weight: 700;">Belum Ada Warta Sakristi</h3>
    <p style="margin-bottom: 1rem;">Mulai dengan menerbitkan pengumuman tata tertib atau informasi latihan liturgi untuk anggota.</p>
    <button type="button" class="btn btn-primary" onclick="openModal('modal-tambah-warta')">
      Terbitkan Warta Pertama
    </button>
  </div>
<?php else: ?>
  <div style="display: flex; flex-direction: column; gap: 1.25rem;">
    <?php foreach ($announcements as $w): ?>
      <div class="card" style="<?= $w['is_pinned'] ? 'border-left: 5px solid var(--amber); background: linear-gradient(to right, rgba(255, 184, 107, 0.08), var(--surface));' : '' ?>">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; flex-wrap: wrap; margin-bottom: 0.75rem;">
          <div>
            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem;">
              <?php if ($w['is_pinned']): ?>
                <span class="badge badge-amber">📌 Disematkan</span>
              <?php endif; ?>
              <span style="font-size: 0.8rem; color: var(--text-dim);">
                📅 <?= format_indo_date(date('Y-m-d', strtotime($w['created_at']))) ?> &bull; <?= date('H:i', strtotime($w['created_at'])) ?> WIB
              </span>
            </div>
            <h3 style="font-size: 1.2rem; font-weight: 700; color: var(--text-main);">
              <?= htmlspecialchars($w['judul']) ?>
            </h3>
          </div>

          <div style="display: flex; gap: 0.4rem; align-items: center;">
            <!-- Pin / Unpin Button -->
            <form method="POST" action="warta.php" style="margin: 0;">
              <input type="hidden" name="action" value="toggle_pin">
              <input type="hidden" name="id" value="<?= $w['id'] ?>">
              <input type="hidden" name="is_pinned" value="<?= $w['is_pinned'] ?>">
              <button type="submit" class="btn btn-secondary btn-sm" title="<?= $w['is_pinned'] ? 'Lepaskan sematan' : 'Sematkan ke paling atas' ?>">
                <?= $w['is_pinned'] ? '📌 Batal Pin' : '📍 Sematkan' ?>
              </button>
            </form>

            <!-- Delete Button -->
            <form method="POST" action="warta.php" onsubmit="return confirm('Hapus warta ini secara permanen?');" style="margin: 0;">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= $w['id'] ?>">
              <button type="submit" class="btn btn-secondary btn-sm" style="color: var(--error);" title="Hapus Warta">
                🗑️ Hapus
              </button>
            </form>
          </div>
        </div>

        <div style="font-size: 0.95rem; line-height: 1.6; color: var(--text-muted); white-space: pre-line; margin-bottom: 0.75rem;">
          <?= htmlspecialchars($w['isi']) ?>
        </div>

        <div style="border-top: 1px solid var(--border-light); padding-top: 0.5rem; font-size: 0.78rem; color: var(--text-dim); display: flex; justify-content: space-between;">
          <span>✍️ Diterbitkan oleh: <strong><?= htmlspecialchars($w['nama_panggilan'] ?: 'Pengurus Sakristi') ?></strong></span>
          <span>SiDinar Regina Paroki Purbowardayan</span>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<!-- ======================================================== -->
<!-- MODAL: Tambah Warta Baru                                 -->
<!-- ======================================================== -->
<div class="modal" id="modal-tambah-warta">
  <div class="modal-card" style="max-width: 560px;">
    <div class="modal-header">
      <div class="modal-title">📢 Terbitkan Warta Sakristi Baru</div>
      <button type="button" class="btn-icon" onclick="closeModal('modal-tambah-warta')">&times;</button>
    </div>
    <form method="POST" action="warta.php">
      <input type="hidden" name="action" value="create_announcement">
      <div class="modal-body" style="display: flex; flex-direction: column; gap: 1rem;">
        <div>
          <label class="form-label">Judul Warta / Pengumuman *</label>
          <input type="text" name="judul" class="form-input" placeholder="Contoh: Tata Tertib Sakristi & Gladi Bersih Misa Paskah" required>
        </div>

        <div>
          <label class="form-label">Isi Warta / Pengumuman Lengkap *</label>
          <textarea name="isi" class="form-input" rows="6" placeholder="Tuliskan detail pengumuman, tanggal pelaksanaan latihan, pembagian tugas, dan pesan penting untuk seluruh anggota misdinar..." required></textarea>
        </div>

        <div style="display: flex; align-items: center; gap: 0.5rem;">
          <input type="checkbox" id="is_pinned" name="is_pinned" value="1" style="width: 18px; height: 18px; cursor: pointer;">
          <label for="is_pinned" style="cursor: pointer; font-size: 0.9rem; font-weight: 600;">
            📌 Sematkan warta ini di posisi teratas (Pinned Announcement)
          </label>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-tambah-warta')">Batal</button>
        <button type="submit" class="btn btn-primary">Publikasikan Sekarang</button>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
