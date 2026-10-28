<?php
/**
 * Footer & Modal Notifikasi Global
 * SiDinar Regina - Sistem Informasi & Presensi Putra-Putri Altar
 */
$base = base_url();
?>
</main> <!-- End .main-wrapper -->

<!-- Modal Pusat Notifikasi & Uji Suara -->
<div id="modal-notifications" class="modal-overlay">
  <div class="modal-dialog">
    <div class="modal-header">
      <div style="display:flex; align-items:center; gap:0.5rem;">
        <span style="font-size:1.2rem;">🔔</span>
        <h3 class="card-title" style="margin:0;">Pusat Notifikasi &amp; Pengingat</h3>
      </div>
      <button type="button" class="btn-icon" onclick="closeModal('modal-notifications')" style="border:none;">&times;</button>
    </div>
    <div class="modal-body">
      <!-- Tombol Uji Suara & Banner -->
      <div style="background:var(--primary-container); color:var(--on-primary-container); padding:0.85rem; border-radius:var(--radius-sm); margin-bottom:1rem; display:flex; align-items:center; justify-content:space-between; gap:0.5rem;">
        <div>
          <div style="font-size:0.82rem; font-weight:700;">Pengujian Notifikasi Web</div>
          <div style="font-size:0.72rem; opacity:0.85;">Uji bunyi lonceng altar &amp; pop-up banner di layar browser Anda.</div>
        </div>
        <button type="button" class="btn btn-primary btn-sm" onclick="showToastBanner('🔔 Pengingat Tugas Misdinar!', 'Pengurus sakristi mengingatkan jadwal tugas altar Anda. Harap hadir H-30 menit!'); closeModal('modal-notifications');">
          Uji Bunyi
        </button>
      </div>

      <div id="notification-modal-list">
        <p style="font-size:0.8rem; color:var(--text-muted); text-align:center; padding:1.5rem 0;">
          Memuat warta &amp; pengingat tugas...
        </p>
      </div>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-secondary btn-sm" onclick="closeModal('modal-notifications')">Tutup</button>
    </div>
  </div>
</div>

<footer style="margin-top:auto; padding:2rem 1.25rem; border-top:1px solid var(--border); background:var(--surface); text-align:center; font-size:0.75rem; color:var(--text-muted);">
  <div style="max-width:1200px; margin:0 auto; display:flex; flex-direction:column; gap:0.35rem; align-items:center;">
    <div style="font-weight:700; color:var(--primary);">SiDinar Regina &copy; 2026</div>
    <div>Sistem Informasi &amp; Presensi Digital Putra-Putri Altar SPMR Purbowardayan</div>
    <div style="font-size:0.7rem; color:var(--text-dim);">Dibuat dengan PHP Native &amp; Vanilla CSS (Material 3 Liturgical Design)</div>
  </div>
</footer>

<script src="<?= $base ?>/assets/js/app.js"></script>
</body>
</html>
