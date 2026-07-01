/**
 * BENGKELIN - Main JavaScript Module
 */

const App = {

  // ── TOAST ─────────────────────────────────────────
  toast(message, type = 'success', duration = 3500) {
    const container = document.getElementById('toast-container') || this._createToastContainer();
    const icons = { success: 'fa-check-circle', error: 'fa-times-circle', warning: 'fa-exclamation-circle', info: 'fa-info-circle' };
    const t = document.createElement('div');
    t.className = 'toast';
    t.innerHTML = `
      <i class="fas ${icons[type] || icons.success} toast-icon ${type}"></i>
      <span>${message}</span>
      <span class="toast-close" onclick="this.parentElement.remove()">×</span>`;
    container.appendChild(t);
    requestAnimationFrame(() => t.classList.add('show'));
    setTimeout(() => { t.classList.remove('show'); setTimeout(() => t.remove(), 300); }, duration);
  },

  _createToastContainer() {
    const c = document.createElement('div');
    c.id = 'toast-container'; c.className = 'toast-container';
    document.body.appendChild(c); return c;
  },

  // ── MODAL ─────────────────────────────────────────
  openModal(id) {
    const overlay = document.getElementById(id);
    if (!overlay) return;
    overlay.classList.add('active');
    document.body.style.overflow = 'hidden';
  },

  closeModal(id) {
    const overlay = document.getElementById(id);
    if (!overlay) return;
    overlay.classList.remove('active');
    document.body.style.overflow = '';
  },

  closeModalOnOverlay(e) {
    if (e.target === e.currentTarget) App.closeModal(e.currentTarget.id);
  },

  // ── DROPDOWN ──────────────────────────────────────
  toggleDropdown(id) {
    const menu = document.getElementById(id);
    if (!menu) return;
    const isOpen = menu.classList.contains('show');
    document.querySelectorAll('.dropdown-menu.show').forEach(m => m.classList.remove('show'));
    if (!isOpen) menu.classList.add('show');
  },

  // ── CONFIRM ───────────────────────────────────────
  confirm(message, callback) {
    // Custom beautiful confirm modal instead of native window.confirm
    const overlayId = 'custom-confirm-overlay';
    let overlay = document.getElementById(overlayId);
    
    if (!overlay) {
      overlay = document.createElement('div');
      overlay.id = overlayId;
      overlay.className = 'modal-overlay';
      overlay.innerHTML = `
        <div class="modal" style="max-width: 400px; text-align: center; padding-top: 32px;">
          <div class="modal-body">
            <div style="width: 64px; height: 64px; background: #FEF2F2; color: #EF4444; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 28px; margin: 0 auto 16px;">
              <i class="fas fa-exclamation-triangle"></i>
            </div>
            <h3 style="font-size: 18px; margin-bottom: 8px;">Konfirmasi</h3>
            <p id="custom-confirm-message" style="color: var(--text-secondary); font-size: 14px; margin-bottom: 24px;"></p>
            <div style="display: flex; gap: 12px; justify-content: center;">
              <button class="btn btn-outline" id="custom-confirm-cancel" style="flex: 1;">Batal</button>
              <button class="btn btn-danger" id="custom-confirm-ok" style="flex: 1;">Ya, Lanjutkan</button>
            </div>
          </div>
        </div>
      `;
      document.body.appendChild(overlay);
    }

    document.getElementById('custom-confirm-message').textContent = message;
    
    const btnCancel = document.getElementById('custom-confirm-cancel');
    const btnOk = document.getElementById('custom-confirm-ok');
    
    const close = () => {
      overlay.classList.remove('active');
      btnCancel.onclick = null;
      btnOk.onclick = null;
    };

    btnCancel.onclick = close;
    btnOk.onclick = () => {
      close();
      callback();
    };

    // Show modal using animation frame trick
    requestAnimationFrame(() => overlay.classList.add('active'));
  },

  // ── LOADING ───────────────────────────────────────
  loading(show = true) {
    const el = document.getElementById('loading-overlay');
    if (el) el.style.display = show ? 'flex' : 'none';
  },

  // ── CURRENCY FORMAT ───────────────────────────────
  currency(amount) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(amount);
  },

  formatNumber(n) {
    return new Intl.NumberFormat('id-ID').format(n);
  },

  // ── INPUT FORMATTER ───────────────────────────────
  initCurrencyInput() {
    document.querySelectorAll('.currency-input').forEach(input => {
      // Format on load
      if (input.value) {
        let val = input.value.replace(/\D/g, '');
        if (val) input.value = App.formatNumber(val);
      }
      
      input.addEventListener('input', function(e) {
        let val = this.value.replace(/\D/g, '');
        if (val === '') {
          this.value = '';
          return;
        }
        this.value = App.formatNumber(val);
      });
      
      // Remove formatting before form submit if needed
      const form = input.closest('form');
      if (form) {
        form.addEventListener('submit', () => {
          input.value = input.value.replace(/\D/g, '');
        });
      }
    });
  },

  // ── DARK MODE ─────────────────────────────────────
  initDarkMode() {
    const isDark = localStorage.getItem('bengkelin_dark_mode') === 'true';
    if (isDark) document.body.classList.add('dark-mode');
    
    const toggleBtn = document.getElementById('dark-mode-toggle');
    if (toggleBtn) {
      toggleBtn.innerHTML = isDark ? '<i class="fas fa-sun"></i>' : '<i class="fas fa-moon"></i>';
      toggleBtn.addEventListener('click', () => {
        document.body.classList.toggle('dark-mode');
        const nowDark = document.body.classList.contains('dark-mode');
        localStorage.setItem('bengkelin_dark_mode', nowDark);
        toggleBtn.innerHTML = nowDark ? '<i class="fas fa-sun"></i>' : '<i class="fas fa-moon"></i>';
      });
    }
  },

  // ── FETCH HELPER ──────────────────────────────────
  async fetch(url, options = {}) {
    this.loading(true);
    try {
      const res = await fetch(url, {
        headers: { 'Content-Type': 'application/json', ...options.headers },
        ...options
      });
      const data = await res.json();
      return data;
    } catch (err) {
      this.toast('Terjadi kesalahan koneksi', 'error');
      return null;
    } finally {
      this.loading(false);
    }
  },

  async postForm(url, formData) {
    this.loading(true);
    try {
      const res = await fetch(url, { method: 'POST', body: formData });
      return await res.json();
    } catch (err) {
      this.toast('Terjadi kesalahan koneksi', 'error');
      return null;
    } finally {
      this.loading(false);
    }
  },

  // ── TABLE SEARCH ──────────────────────────────────
  tableSearch(inputId, tableId) {
    const input = document.getElementById(inputId);
    const table = document.getElementById(tableId);
    if (!input || !table) return;
    input.addEventListener('input', function () {
      const q = this.value.toLowerCase();
      table.querySelectorAll('tbody tr').forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
      });
    });
  },

  // ── ANIMATE COUNTERS ─────────────────────────────
  animateCounters() {
    document.querySelectorAll('.stat-value').forEach(el => {
      const text = el.textContent.trim();
      // Only animate pure numbers or Rp values
      const match = text.match(/^Rp\s*([\d.,]+)$/);
      const pureNum = text.match(/^(\d+)$/);
      
      if (match) {
        const target = parseInt(match[1].replace(/\./g, '').replace(/,/g, ''));
        if (isNaN(target) || target === 0) return;
        el.textContent = 'Rp 0';
        this._countUp(el, 0, target, 800, v => 'Rp ' + new Intl.NumberFormat('id-ID').format(v));
      } else if (pureNum) {
        const target = parseInt(pureNum[1]);
        if (isNaN(target) || target === 0) return;
        el.textContent = '0';
        this._countUp(el, 0, target, 600, v => v.toString());
      }
    });
  },

  _countUp(el, start, end, duration, formatter) {
    const startTime = performance.now();
    const step = (now) => {
      const progress = Math.min((now - startTime) / duration, 1);
      const eased = 1 - Math.pow(1 - progress, 3); // easeOutCubic
      const current = Math.floor(start + (end - start) * eased);
      el.textContent = formatter(current);
      if (progress < 1) requestAnimationFrame(step);
    };
    requestAnimationFrame(step);
  },

  // ── AUTO DISMISS FLASH ──────────────────────────
  autoDismissFlash() {
    document.querySelectorAll('.alert').forEach(alert => {
      setTimeout(() => {
        alert.style.transition = 'opacity .4s, transform .4s';
        alert.style.opacity = '0';
        alert.style.transform = 'translateY(-8px)';
        setTimeout(() => alert.remove(), 400);
      }, 4000);
    });
  },

  // ── INIT ──────────────────────────────────────────
  init() {
    // Close dropdowns on outside click
    document.addEventListener('click', e => {
      if (!e.target.closest('.dropdown')) {
        document.querySelectorAll('.dropdown-menu.show').forEach(m => m.classList.remove('show'));
      }
    });

    // Sidebar toggle (mobile)
    const toggle = document.getElementById('sidebar-toggle');
    const sidebar = document.getElementById('app-sidebar');
    if (toggle && sidebar) {
      toggle.addEventListener('click', () => sidebar.classList.toggle('open'));
      document.addEventListener('click', e => {
        if (!e.target.closest('#app-sidebar') && !e.target.closest('#sidebar-toggle')) {
          sidebar.classList.remove('open');
        }
      });
    }

    // Animate stat counters
    this.animateCounters();

    // Auto-dismiss flash messages
    this.autoDismissFlash();

    // Initialize UI Enhancements
    this.initDarkMode();
    this.initCurrencyInput();
  }
};

document.addEventListener('DOMContentLoaded', () => App.init());

