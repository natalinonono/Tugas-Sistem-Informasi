/**
 * SiDinar Regina - Core Client Engine
 * Audio Chimes, Real-time Polling, Toast Banners, Theme Switcher
 */

// 1. Audio Context Synthesizer (Lonceng Altar Digital)
let audioCtx = null;

function getAudioContext() {
  if (!audioCtx) {
    const AudioContextClass = window.AudioContext || window.webkitAudioContext;
    if (AudioContextClass) {
      audioCtx = new AudioContextClass();
    }
  }
  if (audioCtx && audioCtx.state === 'suspended') {
    audioCtx.resume().catch(() => {});
  }
  return audioCtx;
}

function playNotificationChime() {
  try {
    const ctx = getAudioContext();
    if (!ctx) return;

    const now = ctx.currentTime;
    // Tone 1: E5 (659.25 Hz)
    const osc1 = ctx.createOscillator();
    const gain1 = ctx.createGain();
    osc1.type = 'sine';
    osc1.frequency.setValueAtTime(659.25, now);
    gain1.gain.setValueAtTime(0.2, now);
    gain1.gain.exponentialRampToValueAtTime(0.001, now + 0.35);
    osc1.connect(gain1);
    gain1.connect(ctx.destination);
    osc1.start(now);
    osc1.stop(now + 0.35);

    // Tone 2: A5 (880 Hz)
    const osc2 = ctx.createOscillator();
    const gain2 = ctx.createGain();
    osc2.type = 'sine';
    osc2.frequency.setValueAtTime(880, now + 0.12);
    gain2.gain.setValueAtTime(0.25, now + 0.12);
    gain2.gain.exponentialRampToValueAtTime(0.001, now + 0.75);
    osc2.connect(gain2);
    gain2.connect(ctx.destination);
    osc2.start(now + 0.12);
    osc2.stop(now + 0.75);
  } catch (e) {
    console.warn('Audio chime warning:', e);
  }
}

// 2. Floating Heads-Up Banner Notification Toast
function showToastBanner(title, message, actionUrl = '', actionText = 'Lihat Detail') {
  playNotificationChime();

  const existing = document.getElementById('active-toast-banner');
  if (existing) existing.remove();

  const toast = document.createElement('div');
  toast.id = 'active-toast-banner';
  toast.className = 'toast-banner';
  toast.innerHTML = `
    <div class="toast-icon">🔔</div>
    <div class="toast-content">
      <div style="display:flex; justify-content:space-between; align-items:center;">
        <div class="toast-title">${title}</div>
        <button onclick="document.getElementById('active-toast-banner').remove()" style="background:none; border:none; color:var(--text-muted); cursor:pointer; font-size:16px;">&times;</button>
      </div>
      <div class="toast-body">${message}</div>
      <div class="toast-actions">
        ${actionUrl ? `<a href="${actionUrl}" class="btn btn-primary btn-sm">${actionText}</a>` : ''}
        <button onclick="document.getElementById('active-toast-banner').remove()" class="btn btn-secondary btn-sm">Tutup</button>
      </div>
    </div>
  `;

  document.body.appendChild(toast);

  // Auto remove after 9 seconds
  setTimeout(() => {
    const el = document.getElementById('active-toast-banner');
    if (el) el.remove();
  }, 9000);
}

// 3. Theme Toggle (Light / Dark)
function initTheme() {
  const saved = localStorage.getItem('sidinar_theme') || 'light';
  document.documentElement.setAttribute('data-theme', saved);
  updateThemeIcon(saved);
}

function toggleTheme() {
  const current = document.documentElement.getAttribute('data-theme') || 'light';
  const next = current === 'dark' ? 'light' : 'dark';
  document.documentElement.setAttribute('data-theme', next);
  localStorage.setItem('sidinar_theme', next);
  updateThemeIcon(next);
}

function updateThemeIcon(theme) {
  const btn = document.getElementById('btn-theme-toggle');
  if (btn) {
    btn.innerHTML = theme === 'dark' ? '☀️' : '🌙';
    btn.title = theme === 'dark' ? 'Ganti ke Mode Terang' : 'Ganti ke Mode Gelap';
  }
}

// Helper URL API Dinamis
function getApiUrl(endpoint) {
  const base = typeof window.SIDINAR_BASE_URL === 'string' ? window.SIDINAR_BASE_URL.replace(/\/$/, '') : '';
  return base + '/api/' + endpoint.replace(/^\//, '');
}

// 4. Modal Helpers
function openModal(id) {
  const modal = document.getElementById(id);
  if (modal) {
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    modal.classList.add('active');
  }
}

function closeModal(id) {
  const modal = document.getElementById(id);
  if (modal) {
    modal.classList.remove('flex');
    modal.classList.remove('active');
    modal.classList.add('hidden');
  }
}

// 5. Realtime Notification Poller
let lastRemindedBookingId = null;

function checkNotifications() {
  const apiPath = getApiUrl('notifications.php');
  fetch(apiPath)
    .then(res => res.json())
    .then(data => {
      if (data && data.success) {
        // Update badge count
        const badge = document.getElementById('nav-unread-count');
        const badgeMobile = document.getElementById('nav-unread-count-mobile');
        if (badge) {
          if (data.unread_count > 0) {
            badge.innerText = data.unread_count;
            badge.style.display = 'inline-flex';
          } else {
            badge.style.display = 'none';
          }
        }
        if (badgeMobile) {
          if (data.unread_count > 0) {
            badgeMobile.innerText = data.unread_count;
            badgeMobile.style.display = 'inline-block';
          } else {
            badgeMobile.style.display = 'none';
          }
        }

        // Render isi daftar notifikasi ke dalam modal
        const modalList = document.getElementById('notification-modal-list');
        if (modalList) {
          let html = '';
          if (data.reminders && data.reminders.length > 0) {
            html += '<div class="flex flex-col gap-2 mb-3"><div class="text-[11px] font-bold text-secondary uppercase tracking-wider">Pengingat Jadwal Tugas</div>';
            data.reminders.forEach(r => {
              html += `
                <div class="p-3.5 rounded-2xl bg-surface-container-low border-l-4 border-primary border border-surface-container flex flex-col gap-1.5">
                  <div class="font-bold font-headline text-xs text-primary">${r.title}</div>
                  <div class="text-[11px] text-on-surface-variant leading-relaxed">${r.message}</div>
                  ${r.url ? `<div class="pt-1"><a href="${r.url}" class="inline-flex items-center gap-1 px-3 py-1 rounded-xl bg-surface-container-high hover:bg-surface-container-highest text-primary font-bold text-[10px] transition-colors">Lihat Jadwal &rarr;</a></div>` : ''}
                </div>
              `;
            });
            html += '</div>';
          }
          if (data.announcements && data.announcements.length > 0) {
            html += '<div class="flex flex-col gap-2"><div class="text-[11px] font-bold text-secondary uppercase tracking-wider">Warta & Pengumuman Sakristi</div>';
            data.announcements.forEach(a => {
              html += `
                <div class="p-3.5 rounded-2xl bg-surface-container-low border-l-4 border-secondary border border-surface-container flex flex-col gap-1.5">
                  <div class="flex items-center justify-between gap-2">
                    <div class="font-bold font-headline text-xs text-on-surface">${a.is_pinned ? '📌 ' : ''}${a.judul}</div>
                    <span class="text-[10px] text-on-surface-variant shrink-0">${a.created_at ? a.created_at.substring(0, 10) : ''}</span>
                  </div>
                  <div class="text-[11px] text-on-surface-variant leading-relaxed whitespace-pre-line">${a.isi}</div>
                </div>
              `;
            });
            html += '</div>';
          }
          if (!html) {
            html = '<div class="p-8 text-center text-xs text-on-surface-variant flex flex-col items-center gap-2"><span class="material-symbols-outlined text-3xl opacity-50">notifications_off</span><span>Belum ada pengingat atau warta baru saat ini.</span></div>';
          }
          modalList.innerHTML = html;
        }

        // Trigger banner jika ada pengingat baru yang belum dilihat sesi ini
        if (data.latest_reminder && data.latest_reminder.id !== lastRemindedBookingId) {
          if (lastRemindedBookingId !== null) {
            showToastBanner(data.latest_reminder.title, data.latest_reminder.message, data.latest_reminder.url);
          }
          lastRemindedBookingId = data.latest_reminder.id;
        }
      }
    })
    .catch(() => {});
}

// 6. Global DOM Ready Init
document.addEventListener('DOMContentLoaded', () => {
  initTheme();

  // Unlock Web Audio on first user interaction
  document.addEventListener('click', () => {
    getAudioContext();
  }, { once: true });

  // Polling notifications every 6 seconds
  checkNotifications();
  setInterval(checkNotifications, 6000);
});
