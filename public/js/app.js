/**
 * PustakaRapi — Modern Interactive Vanilla Client
 * Zero Framework Dependencies, High Performance, Accessible
 */

'use strict';

document.addEventListener('DOMContentLoaded', () => {

  /* =========================================================================
     1. Toast Notification System
     ========================================================================= */
  const toastContainer = document.getElementById('toast-container');

  window.showToast = function(type, message) {
    if (!toastContainer) return;
    
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    
    let iconSvg = '';
    if (type === 'success' || type === 'ok') {
      iconSvg = '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>';
    } else if (type === 'error' || type === 'danger') {
      iconSvg = '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>';
    } else {
      iconSvg = '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>';
    }

    toast.innerHTML = `
      <span>${iconSvg}</span>
      <span style="flex:1;">${message}</span>
      <button type="button" class="btn-ghost" style="color:rgba(255,255,255,0.7); padding:2px; min-height:auto;" aria-label="Tutup">&times;</button>
    `;

    toast.querySelector('button').addEventListener('click', () => {
      toast.remove();
    });

    toastContainer.appendChild(toast);

    setTimeout(() => {
      toast.style.transition = 'opacity 250ms, transform 250ms';
      toast.style.opacity = '0';
      toast.style.transform = 'translateY(10px)';
      setTimeout(() => toast.remove(), 250);
    }, 4500);
  };

  /* =========================================================================
     2. Modals & Dialogs
     ========================================================================= */
  window.openModal = function(modalId, data = null) {
    const backdrop = document.getElementById(modalId);
    if (!backdrop) return;

    if (data) {
      const form = backdrop.querySelector('form');
      if (form) {
        form.reset();
        for (const [key, value] of Object.entries(data)) {
          const input = form.querySelector(`[name="${key}"]`);
          if (input) {
            input.value = value;
          }
        }
        // Custom text updates for titles
        const modalTitle = backdrop.querySelector('.modal-title');
        if (modalTitle && (data.action_title || data.nama || data.nama_satuan || data.nama_vendor)) {
          modalTitle.textContent = data.action_title || (data.id || data.idbarang || data.idsatuan || data.idvendor ? 'Ubah Data' : 'Tambah Data');
        }
      }
    }

    backdrop.classList.add('is-open');
    document.body.style.overflow = 'hidden';

    const firstInput = backdrop.querySelector('input:not([type="hidden"]), select, textarea');
    if (firstInput) {
      setTimeout(() => firstInput.focus(), 100);
    }
  };

  window.closeModal = function(modalId) {
    const backdrop = typeof modalId === 'string' ? document.getElementById(modalId) : modalId;
    if (!backdrop) return;
    backdrop.classList.remove('is-open');
    document.body.style.overflow = '';
  };

  // Trigger modal via data-modal="modal-id"
  document.querySelectorAll('[data-open-modal]').forEach(btn => {
    btn.addEventListener('click', () => {
      const targetId = btn.getAttribute('data-open-modal');
      let data = null;
      const rawData = btn.getAttribute('data-item');
      if (rawData) {
        try {
          data = JSON.parse(rawData);
        } catch (e) {
          data = null;
        }
      }
      openModal(targetId, data);
    });
  });

  // Close modal via data-close-modal
  document.querySelectorAll('[data-close-modal]').forEach(btn => {
    btn.addEventListener('click', () => {
      const modal = btn.closest('.modal-backdrop');
      if (modal) closeModal(modal);
    });
  });

  // Close on backdrop click
  document.querySelectorAll('.modal-backdrop').forEach(backdrop => {
    backdrop.addEventListener('click', (e) => {
      if (e.target === backdrop) {
        closeModal(backdrop);
      }
    });
  });

  // Close on Escape key
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      const openModalEl = document.querySelector('.modal-backdrop.is-open');
      if (openModalEl) {
        closeModal(openModalEl);
      }
    }
  });

  /* =========================================================================
     3. Confirmation Modal Dialog
     ========================================================================= */
  const confirmModal = document.getElementById('modal-confirm-delete');
  let pendingConfirmForm = null;

  document.querySelectorAll('form[data-confirm]').forEach(form => {
    form.addEventListener('submit', (e) => {
      e.preventDefault();
      pendingConfirmForm = form;
      const promptText = form.getAttribute('data-confirm') || 'Apakah Anda yakin ingin menghapus data ini?';
      const promptEl = document.getElementById('confirm-delete-message');
      if (promptEl) {
        promptEl.textContent = promptText;
      }
      openModal('modal-confirm-delete');
    });
  });

  const confirmActionBtn = document.getElementById('btn-confirm-delete-yes');
  if (confirmActionBtn) {
    confirmActionBtn.addEventListener('click', () => {
      closeModal('modal-confirm-delete');
      if (pendingConfirmForm) {
        pendingConfirmForm.submit();
        pendingConfirmForm = null;
      }
    });
  }

  /* =========================================================================
     4. Search Debounce
     ========================================================================= */
  const autoSubmitForms = document.querySelectorAll('.form-search-auto');
  autoSubmitForms.forEach(form => {
    const input = form.querySelector('input[type="search"], input[name="q"]');
    if (!input) return;

    let timer = null;
    input.addEventListener('input', () => {
      clearTimeout(timer);
      timer = setTimeout(() => {
        if (typeof form.requestSubmit === 'function') {
          form.requestSubmit();
        } else {
          form.submit();
        }
      }, 380);
    });
  });

  /* =========================================================================
     5. Mobile Menu Drawer Toggle
     ========================================================================= */
  const mobileToggle = document.getElementById('mobile-menu-toggle');
  const sidebar = document.getElementById('app-sidebar');

  if (mobileToggle && sidebar) {
    mobileToggle.addEventListener('click', () => {
      sidebar.classList.toggle('is-open');
    });

    document.addEventListener('click', (e) => {
      if (sidebar.classList.contains('is-open') && 
          !sidebar.contains(e.target) && 
          !mobileToggle.contains(e.target)) {
        sidebar.classList.remove('is-open');
      }
    });
  }

  /* =========================================================================
     6. Password Visibility Toggle
     ========================================================================= */
  document.querySelectorAll('.btn-toggle-password').forEach(btn => {
    btn.addEventListener('click', () => {
      const wrap = btn.closest('.password-field-wrap');
      if (!wrap) return;
      const input = wrap.querySelector('input');
      if (!input) return;

      if (input.type === 'password') {
        input.type = 'text';
        btn.innerHTML = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>';
      } else {
        input.type = 'password';
        btn.innerHTML = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
      }
    });
  });

  /* =========================================================================
     7. Receipt Print Helper
     ========================================================================= */
  window.printReceipt = function(elemId) {
    const elem = document.getElementById(elemId);
    if (!elem) {
      window.print();
      return;
    }
    const printWindow = window.open('', '', 'width=420,height=600');
    printWindow.document.write('<html><head><title>Struk Penjualan · PustakaRapi</title>');
    printWindow.document.write('<link rel="stylesheet" href="public/css/app.css">');
    printWindow.document.write('<style>body{background:#fff;padding:15px;}</style>');
    printWindow.document.write('</head><body>');
    printWindow.document.write(elem.innerHTML);
    printWindow.document.write('</body></html>');
    printWindow.document.close();
    printWindow.focus();
    setTimeout(() => {
      printWindow.print();
      printWindow.close();
    }, 400);
  };

});

