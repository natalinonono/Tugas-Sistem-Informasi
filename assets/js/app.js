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

// 4. Modal Helpers
function openModal(id) {
  const modal = document.getElementById(id);
  if (modal) modal.classList.add('active');
}

function closeModal(id) {
  const modal = document.getElementById(id);
  if (modal) modal.classList.remove('active');
}

// 5. Realtime Notification Poller
let lastRemindedBookingId = null;

function checkNotifications() {
  const apiPath = window.SIDINAR_BASE_URL ? window.SIDINAR_BASE_URL + '/api/notifications.php' : '../api/notifications.php';
  fetch(apiPath)
    .then(res => res.json())
    .then(data => {
      if (data && data.success) {
        // Update badge count
        const badge = document.getElementById('nav-unread-count');
        if (badge) {
          if (data.unread_count > 0) {
            badge.innerText = data.unread_count;
            badge.style.display = 'inline-flex';
          } else {
            badge.style.display = 'none';
          }
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
