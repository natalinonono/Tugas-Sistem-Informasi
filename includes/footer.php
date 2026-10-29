<?php
/**
 * Footer & Modal Notifikasi Global - SiDinar Regina
 * Tampilan & Desain Identik dengan Web Next.js
 */
$base = base_url();
?>
</main> <!-- End main container -->

<!-- Footer Identik Next.js -->
<footer class="w-full bg-surface-container-lowest border-t border-surface-container py-6 text-center text-xs text-on-surface-variant mt-auto">
  <div class="max-w-7xl mx-auto px-4 flex flex-col items-center gap-1">
    <p class="font-medium text-on-surface">
      &copy; 2026 <span class="font-bold text-primary">SiDinar Regina</span> • Sistem Informasi Putra-Putri Altar Paroki Santa Perawan Maria Regina Purbowardayan
    </p>
    <p class="text-[11px] text-on-surface-variant opacity-80">
      Arsitektur Liturgis Material 3 • Versi 1.2.6 (Edisi Standar Pelayanan Altar)
    </p>
  </div>
</footer>

<!-- Modal Pusat Notifikasi Identik Next.js NotificationCenter -->
<div id="modal-notifications" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs hidden items-center justify-center p-4">
  <div class="bg-surface-container-lowest border border-surface-container rounded-3xl max-w-lg w-full shadow-2xl overflow-hidden flex flex-col max-h-[85vh] animate-scaleUp">
    <!-- Header Modal -->
    <div class="p-5 border-b border-surface-container flex items-center justify-between bg-surface-container-low">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-primary-fixed flex items-center justify-center text-primary">
          <span class="material-symbols-outlined text-xl">notifications_active</span>
        </div>
        <div>
          <h3 class="font-headline font-bold text-base text-primary">Pusat Notifikasi &amp; Pengingat</h3>
          <p class="text-[11px] text-on-surface-variant">Warta sakristi dan pengingat jadwal tugas altar</p>
        </div>
      </div>
      <button type="button" onclick="closeModal('modal-notifications')" class="w-8 h-8 rounded-full flex items-center justify-center hover:bg-surface-container text-on-surface-variant">
        <span class="material-symbols-outlined text-lg">close</span>
      </button>
    </div>

    <!-- Body Modal -->
    <div class="p-5 overflow-y-auto flex flex-col gap-4">
      <!-- Tester Banner -->
      <div class="p-3.5 rounded-2xl bg-primary-fixed/40 border border-primary-fixed-dim flex items-center justify-between gap-3">
        <div>
          <div class="text-xs font-bold text-primary">Pengujian Notifikasi Web</div>
          <div class="text-[11px] text-on-surface-variant">Uji bunyi lonceng sakristi &amp; banner pop-up di layar.</div>
        </div>
        <button
          type="button"
          onclick="showToastBanner('🔔 Pengingat Tugas Misdinar!', 'Pengurus sakristi mengingatkan jadwal tugas altar Anda. Harap hadir H-30 menit!'); closeModal('modal-notifications');"
          class="px-3 py-1.5 rounded-xl bg-primary text-on-primary text-[11px] font-bold hover:bg-primary-container shrink-0 transition-colors"
        >
          Uji Bunyi
        </button>
      </div>

      <div id="notification-modal-list" class="flex flex-col gap-2">
        <div class="py-8 text-center text-xs text-on-surface-variant flex flex-col items-center gap-2">
          <span class="material-symbols-outlined text-3xl text-primary animate-spin">progress_activity</span>
          <span>Memuat warta &amp; pengingat tugas...</span>
        </div>
      </div>
    </div>

    <!-- Footer Modal -->
    <div class="p-4 border-t border-surface-container bg-surface-container-low flex justify-end">
      <button type="button" onclick="closeModal('modal-notifications')" class="px-4 py-2 rounded-xl text-xs font-semibold bg-surface-container-high hover:bg-surface-container-highest text-on-surface transition-colors">
        Tutup Jendela
      </button>
    </div>
  </div>
</div>

<!-- Inline Utilities Script for Clock, Theme & Mobile Menu -->
<script>
  // Live WIB Clock
  function updateWibClock() {
    const el = document.getElementById('live-wib-clock');
    if (!el) return;
    const now = new Date();
    const timeStr = now.toLocaleTimeString('id-ID', {
      timeZone: 'Asia/Jakarta',
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit',
    });
    const dateStr = now.toLocaleDateString('id-ID', {
      timeZone: 'Asia/Jakarta',
      weekday: 'short',
      day: 'numeric',
      month: 'short',
    });
    el.innerText = `${dateStr} • ${timeStr} WIB`;
  }
  updateWibClock();
  setInterval(updateWibClock, 1000);

  // Mobile Menu Drawer Toggle
  function toggleMobileMenu() {
    const drawer = document.getElementById('mobile-menu-drawer');
    if (drawer) {
      drawer.classList.toggle('hidden');
    }
  }

  // Generic Modal Helpers
  function openModal(id) {
    const el = document.getElementById(id);
    if (el) {
      el.classList.remove('hidden');
      el.classList.add('flex');
    }
  }
  function closeModal(id) {
    const el = document.getElementById(id);
    if (el) {
      el.classList.remove('flex');
      el.classList.add('hidden');
    }
  }
</script>

<script src="<?= $base ?>/assets/js/app.js"></script>
</body>
</html>
