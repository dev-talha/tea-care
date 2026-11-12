(() => {
  // Register Service Worker
  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/sw.js').catch(() => {});
  }

  // Central Floating Dismissible Alert System (10s auto-dismiss + manual close)
  const ALERT_DURATION_MS = 10000;
  const alertIcons = {
    success: 'ti-circle-check',
    warning: 'ti-alert-triangle',
    info: 'ti-info-circle',
    error: 'ti-alert-circle'
  };

  const ensureAlertStack = () => {
    let stack = document.getElementById('tc-alert-stack');
    if (!stack) {
      stack = document.createElement('div');
      stack.id = 'tc-alert-stack';
      stack.setAttribute('role', 'region');
      stack.setAttribute('aria-live', 'polite');
      stack.setAttribute('aria-label', 'Notifications');
      document.body.appendChild(stack);
    } else if (stack.parentElement !== document.body) {
      document.body.appendChild(stack);
    }
    return stack;
  };

  const dismissAlert = (el) => {
    if (!el || el.dataset.closing === '1') return;
    el.dataset.closing = '1';
    if (el._tcTimer) {
      clearTimeout(el._tcTimer);
      el._tcTimer = null;
    }
    el.classList.add('is-closing');
    setTimeout(() => {
      if (el.parentNode) el.remove();
    }, 240);
  };

  const bindAlertLifecycle = (el, durationMs = ALERT_DURATION_MS) => {
    if (!el || el.dataset.tcBound === '1') return;
    el.dataset.tcBound = '1';

    const closeBtn = el.querySelector('[data-tc-alert-close]');
    if (closeBtn) {
      closeBtn.addEventListener('click', () => dismissAlert(el));
    }

    if (durationMs > 0) {
      const prog = el.querySelector('.tc-alert-progress');
      if (prog) {
        prog.style.animationDuration = `${durationMs}ms`;
      }
      let remaining = durationMs;
      let startedAt = Date.now();
      const schedule = (ms) => {
        if (el._tcTimer) clearTimeout(el._tcTimer);
        startedAt = Date.now();
        el._tcTimer = setTimeout(() => dismissAlert(el), ms);
      };
      schedule(remaining);

      el.addEventListener('mouseenter', () => {
        if (el.dataset.closing === '1') return;
        remaining = Math.max(0, remaining - (Date.now() - startedAt));
        if (el._tcTimer) {
          clearTimeout(el._tcTimer);
          el._tcTimer = null;
        }
      });
      el.addEventListener('mouseleave', () => {
        if (el.dataset.closing === '1') return;
        if (remaining <= 0) {
          dismissAlert(el);
        } else {
          schedule(remaining);
        }
      });
    }
  };

  const showAlert = (message, type = 'info', durationMs = ALERT_DURATION_MS) => {
    if (!message) return null;
    const normType = type === 'danger' ? 'error' : (alertIcons[type] ? type : 'info');
    const stack = ensureAlertStack();
    const item = document.createElement('div');
    item.className = `tc-alert-item tc-alert-${normType}`;
    item.setAttribute('role', 'alert');
    item.dataset.duration = String(durationMs);

    const row = document.createElement('div');
    row.className = 'tc-alert-row';

    const icon = document.createElement('i');
    icon.className = `ti ${alertIcons[normType]} tc-alert-icon`;

    const msg = document.createElement('div');
    msg.className = 'tc-alert-msg';
    msg.textContent = String(message);

    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'tc-alert-close';
    btn.setAttribute('data-tc-alert-close', '1');
    btn.setAttribute('aria-label', 'Close alert');
    btn.innerHTML = '<i class="ti ti-x"></i>';

    row.appendChild(icon);
    row.appendChild(msg);
    row.appendChild(btn);
    item.appendChild(row);

    if (durationMs > 0) {
      const bar = document.createElement('div');
      bar.className = 'tc-alert-bar';
      const prog = document.createElement('span');
      prog.className = 'tc-alert-progress';
      bar.appendChild(prog);
      item.appendChild(bar);
    }

    stack.appendChild(item);
    bindAlertLifecycle(item, durationMs);
    return item;
  };

  window.TeaCareAlert = { show: showAlert, dismiss: dismissAlert };
  document.querySelectorAll('#tc-alert-stack .tc-alert-item').forEach((el) => {
    const dur = parseInt(el.dataset.duration || String(ALERT_DURATION_MS), 10);
    bindAlertLifecycle(el, Number.isFinite(dur) ? dur : ALERT_DURATION_MS);
  });

  // Offline indicator & order submit protection
  const offlineBanner = document.getElementById('offline-banner');
  const syncOnlineState = () => {
    if (!offlineBanner) return;
    if (navigator.onLine) {
      offlineBanner.classList.add('hidden');
    } else {
      offlineBanner.classList.remove('hidden');
    }
  };
  window.addEventListener('online', syncOnlineState);
  window.addEventListener('offline', syncOnlineState);
  syncOnlineState();

  // Prevent double-taps & offline form submits
  document.addEventListener('submit', (e) => {
    const form = e.target;
    if (!(form instanceof HTMLFormElement)) return;

    if (form.dataset.requireOnline === '1' && !navigator.onLine) {
      e.preventDefault();
      showAlert(form.dataset.offlineMsg || 'You are offline. Connect to the internet to submit your order.', 'warning');
      return;
    }

    if (form.dataset.submitting === '1') {
      e.preventDefault();
      return;
    }

    if (form.dataset.onceSubmit === '1') {
      form.dataset.submitting = '1';
      const btn = form.querySelector('button[type="submit"]');
      if (btn) {
        btn.disabled = true;
        btn.classList.add('opacity-70');
      }
    }
  });

  // Quantity Stepper (+/-)
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-step-delta]');
    if (!btn) return;
    const targetId = btn.getAttribute('data-step-target');
    const input = targetId ? document.getElementById(targetId) : btn.parentElement.querySelector('input[type="number"]');
    if (!input) return;
    const min = parseInt(input.min || '1', 10);
    const max = parseInt(input.max || '50', 10);
    const delta = parseInt(btn.getAttribute('data-step-delta') || '0', 10);
    const next = Math.min(max, Math.max(min, (parseInt(input.value || '1', 10) || min) + delta));
    input.value = String(next);
    input.dispatchEvent(new Event('change', { bubbles: true }));
  });

  // Segmented Radio Visual Sync
  document.addEventListener('change', (e) => {
    const input = e.target;
    if (!(input instanceof HTMLInputElement)) return;
    if (input.type === 'radio' && input.closest('[data-seg-group]')) {
      const group = input.closest('[data-seg-group]');
      group.querySelectorAll('label').forEach((lbl) => {
        const r = lbl.querySelector('input[type="radio"]');
        if (r && r.checked) {
          lbl.classList.add('bg-tea-600', 'text-white', 'border-tea-600', 'shadow-sm');
          lbl.classList.remove('bg-white', 'text-slate-700', 'border-stone-200');
        } else {
          lbl.classList.remove('bg-tea-600', 'text-white', 'border-tea-600', 'shadow-sm');
          lbl.classList.add('bg-white', 'text-slate-700', 'border-stone-200');
        }
      });
    }
  });

  // PWA Install Prompt
  let deferredPrompt = null;
  const installBanner = document.getElementById('pwa-install-banner');
  const installBtn = document.getElementById('pwa-install-btn');
  const iosGuide = document.getElementById('pwa-ios-guide');
  const dismissBtn = document.getElementById('pwa-dismiss-btn');

  const isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
  const isDismissed = localStorage.getItem('teacare_pwa_dismissed') === '1';
  const isIos = /iphone|ipad|ipod/i.test(navigator.userAgent);

  if (!isStandalone && !isDismissed && installBanner) {
    if (isIos && iosGuide) {
      installBanner.classList.remove('hidden');
      iosGuide.classList.remove('hidden');
    }
  }

  window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    deferredPrompt = e;
    if (!isStandalone && !isDismissed && installBanner && installBtn) {
      installBanner.classList.remove('hidden');
      installBtn.classList.remove('hidden');
    }
  });

  if (installBtn) {
    installBtn.addEventListener('click', async () => {
      if (!deferredPrompt) return;
      deferredPrompt.prompt();
      await deferredPrompt.userChoice;
      deferredPrompt = null;
      if (installBanner) installBanner.classList.add('hidden');
    });
  }

  if (dismissBtn && installBanner) {
    dismissBtn.addEventListener('click', () => {
      localStorage.setItem('teacare_pwa_dismissed', '1');
      installBanner.classList.add('hidden');
    });
  }

  // Push Helper Functions
  const urlBase64ToUint8Array = (base64String) => {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);
    for (let i = 0; i < rawData.length; ++i) {
      outputArray[i] = rawData.charCodeAt(i);
    }
    return outputArray;
  };

  window.TeaCarePush = {
    async enable(csrfToken) {
      if (!('Notification' in window) || !('serviceWorker' in navigator) || !('PushManager' in window)) {
        throw new Error('Push notifications are not supported on this browser.');
      }
      const perm = await Notification.requestPermission();
      if (perm !== 'granted') {
        throw new Error('Notification permission was denied.');
      }
      const keyRes = await fetch('/api/push/vapid-key');
      const keyData = await keyRes.json();
      if (!keyData.ok || !keyData.publicKey) {
        throw new Error('Server VAPID key is not configured.');
      }
      const reg = await navigator.serviceWorker.ready;
      const sub = await reg.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: urlBase64ToUint8Array(keyData.publicKey)
      });
      const saveRes = await fetch('/api/push/subscribe', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': csrfToken
        },
        body: JSON.stringify({ subscription: sub.toJSON() })
      });
      const saveData = await saveRes.json();
      if (!saveData.ok) {
        throw new Error(saveData.error || 'Failed to save subscription');
      }
      return saveData;
    },
    async test(csrfToken) {
      const res = await fetch('/api/push/test', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
        body: JSON.stringify({})
      });
      return res.json();
    }
  };

  // Smooth page navigation progress indicator
  const navBar = document.getElementById('nav-progress');
  const startNavProgress = () => {
    if (!navBar) return;
    navBar.style.opacity = '1';
    navBar.style.width = '68%';
  };
  const resetNavProgress = () => {
    if (!navBar) return;
    navBar.style.opacity = '0';
    navBar.style.width = '0%';
  };
  window.addEventListener('pageshow', resetNavProgress);
  document.addEventListener('click', (e) => {
    const a = e.target.closest('a[href]');
    if (!a) return;
    const href = a.getAttribute('href') || '';
    if (
      href.startsWith('/') &&
      !href.includes('export=csv') &&
      !a.hasAttribute('download') &&
      a.target !== '_blank' &&
      !e.metaKey && !e.ctrlKey && !e.shiftKey
    ) {
      startNavProgress();
    }
  });
  document.addEventListener('submit', (e) => {
    const form = e.target;
    if (form instanceof HTMLFormElement && !e.defaultPrevented && !form.hasAttribute('data-ajax-action')) {
      startNavProgress();
    }
  });

  // Personal Cup Lightbox Popup Modal + Staff Room Details Modal + Staff Employee Search Modal
  const cupModal = document.getElementById('cup-photo-modal');
  const roomModal = document.getElementById('staff-room-modal');
  const empSearchModal = document.getElementById('staff-emp-search-modal');
  const syncBodyScrollLock = () => {
    const cupOpen = cupModal && !cupModal.classList.contains('hidden');
    const roomOpen = roomModal && !roomModal.classList.contains('hidden');
    const empOpen = empSearchModal && !empSearchModal.classList.contains('hidden');
    document.body.style.overflow = (cupOpen || roomOpen || empOpen) ? 'hidden' : '';
  };
  const closeCupModal = () => {
    if (!cupModal) return;
    cupModal.classList.add('hidden');
    syncBodyScrollLock();
  };
  const closeRoomModal = () => {
    if (!roomModal) return;
    roomModal.classList.add('hidden');
    roomModal.removeAttribute('data-room-key');
    syncBodyScrollLock();
  };
  let empCloseTimer = null;
  const openEmpSearchModal = () => {
    if (!empSearchModal) return;
    clearTimeout(empCloseTimer);
    empSearchModal.classList.remove('hidden');
    void empSearchModal.offsetWidth;
    empSearchModal.classList.add('is-open');
    syncBodyScrollLock();
  };
  const closeEmpSearchModal = () => {
    if (!empSearchModal) return;
    empSearchModal.classList.remove('is-open');
    clearTimeout(empCloseTimer);
    empCloseTimer = setTimeout(() => {
      if (!empSearchModal.classList.contains('is-open')) {
        empSearchModal.classList.add('hidden');
        syncBodyScrollLock();
      }
    }, 200);
  };

  document.addEventListener('click', (e) => {
    const roomTrigger = roomModal ? e.target.closest('[data-room-modal-open]') : null;
    if (roomTrigger) {
      e.preventDefault();
      const key = roomTrigger.getAttribute('data-room-modal-open') || '';
      const title = roomTrigger.getAttribute('data-room-title') || 'Room Details';
      const srcEl = key ? document.getElementById('room-modal-src-' + key) : null;
      const titleEl = document.getElementById('staff-room-modal-title');
      const bodyEl = document.getElementById('staff-room-modal-body');
      if (srcEl && bodyEl) {
        if (titleEl) titleEl.textContent = title;
        bodyEl.innerHTML = srcEl.innerHTML;
        bodyEl.scrollTop = 0;
        roomModal.setAttribute('data-room-key', key);
        roomModal.classList.remove('hidden');
        syncBodyScrollLock();
      }
      return;
    }

    if (cupModal) {
      const trigger = e.target.closest('[data-cup-preview]');
      if (trigger) {
        e.preventDefault();
        const imgEl = document.getElementById('cup-modal-img');
        const nameEl = document.getElementById('cup-modal-name');
        const empIdEl = document.getElementById('cup-modal-empid');
        const locEl = document.getElementById('cup-modal-loc');
        const drinkEl = document.getElementById('cup-modal-drink');

        const src = trigger.getAttribute('data-cup-src') || '';
        const name = trigger.getAttribute('data-cup-name') || 'Employee';
        const empId = trigger.getAttribute('data-cup-empid') || '';
        const dept = trigger.getAttribute('data-cup-dept') || '';
        const room = trigger.getAttribute('data-cup-room') || '';
        const drink = trigger.getAttribute('data-cup-drink') || '';

        if (imgEl) imgEl.src = src;
        if (nameEl) nameEl.textContent = name;
        if (empIdEl) {
          if (empId) {
            empIdEl.textContent = empId;
            empIdEl.classList.remove('hidden');
          } else {
            empIdEl.classList.add('hidden');
          }
        }
        if (locEl) {
          const parts = [];
          if (room) parts.push('Room: ' + room);
          if (dept) parts.push('Dept: ' + dept);
          locEl.textContent = parts.join('  •  ');
        }
        if (drinkEl) {
          if (drink) {
            drinkEl.textContent = drink;
            drinkEl.classList.remove('hidden');
          } else {
            drinkEl.classList.add('hidden');
          }
        }

        cupModal.classList.remove('hidden');
        syncBodyScrollLock();
        return;
      }

      if (e.target.closest('[data-cup-modal-close]') || e.target === cupModal) {
        closeCupModal();
        return;
      }
    }

    if (roomModal && (e.target.closest('[data-room-modal-close]') || e.target === roomModal)) {
      closeRoomModal();
    }
  });

  document.addEventListener('keydown', (e) => {
    if (e.key !== 'Escape') return;
    if (cupModal && !cupModal.classList.contains('hidden')) {
      closeCupModal();
    } else if (empSearchModal && !empSearchModal.classList.contains('hidden')) {
      closeEmpSearchModal();
    } else if (roomModal && !roomModal.classList.contains('hidden')) {
      closeRoomModal();
    }
  });

  // Staff Top Bar Employee Search & Today Preference Lookup
  const empSearchBtn = document.getElementById('staff-emp-search-btn');
  const empSearchInput = document.getElementById('staff-emp-search-input');
  const empSearchClear = document.getElementById('staff-emp-search-clear');
  const empSearchBody = document.getElementById('staff-emp-search-body');

  if (empSearchBtn && empSearchModal && empSearchInput && empSearchBody) {
    let empSearchTimer = null;
    let empSearchAbort = null;
    let empDetailAbort = null;
    let lastEmpQuery = '';
    let lastEmpList = [];

    const escHtml = (val) => String(val ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');

    const statusBadgeClass = (key) => {
      switch (key) {
        case 'delivered': return 'bg-emerald-100 text-emerald-700 border-emerald-200';
        case 'ordered': return 'bg-tea-100 text-tea-800 border-tea-200';
        case 'custom': return 'bg-amberwarm-100 text-amberwarm-700 border-amberwarm-200';
        case 'skipped':
        case 'paused':
        case 'cancelled': return 'bg-rose-50 text-rose-700 border-rose-200';
        case 'disabled':
        case 'system_cancelled': return 'bg-stone-100 text-slate-600 border-stone-200';
        default: return 'bg-tea-50 text-tea-700 border-tea-200';
      }
    };

    const renderEmpList = (employees, query) => {
      if (!employees || employees.length === 0) {
        empSearchBody.innerHTML = `<div class="rounded-xl border border-stone-200 bg-stone-50 p-6 text-center text-xs text-slate-500">No employees found${query ? ` matching "<strong>${escHtml(query)}</strong>"` : ''}.</div>`;
        return;
      }

      const rows = employees.map((emp) => {
        const pausedTag = emp.is_paused ? '<span class="px-2 py-0.5 rounded-lg bg-rose-50 border border-rose-200 text-rose-700 text-[10px] font-bold shrink-0">Paused</span>' : '';
        return `
          <button type="button" data-emp-detail-id="${emp.id}" class="w-full text-left py-2.5 px-3 flex items-center justify-between gap-2.5 hover:bg-cream-50 transition rounded-xl">
            <div class="flex items-center gap-2.5 min-w-0 flex-1">
              <img src="${escHtml(emp.cup_url)}" alt="" class="w-10 h-10 rounded-xl object-cover border border-stone-200 bg-stone-50 shrink-0">
              <div class="min-w-0 flex-1">
                <div class="font-bold text-sm text-slate-800 truncate">${escHtml(emp.full_name)} <span class="text-xs font-semibold text-slate-400">(${escHtml(emp.employee_id)})</span></div>
                <div class="text-[11px] text-slate-500 truncate">${escHtml(emp.room_name)} &bull; ${escHtml(emp.department_name)}</div>
              </div>
            </div>
            <div class="flex items-center gap-1.5 shrink-0">
              ${pausedTag}
              <i class="ti ti-chevron-right text-slate-400 text-base"></i>
            </div>
          </button>`;
      }).join('');

      empSearchBody.innerHTML = `<div class="divide-y divide-stone-100 rounded-xl border border-stone-200 bg-white p-1">${rows}</div>`;
    };

    const renderSlotCard = (slotData, iconClass) => {
      const hasDrink = Boolean(slotData.beverage_label);
      const isCoffee = slotData.beverage === 'coffee';
      const drinkIcon = isCoffee ? 'ti-coffee text-amberwarm-600' : 'ti-cup text-tea-700';
      const badgeCls = statusBadgeClass(slotData.status_key);
      const saved = slotData.saved_default;
      const savedText = saved
        ? (saved.enabled && saved.beverage_label
            ? `Saved Default: ${saved.quantity}× ${escHtml(saved.beverage_label)} • ${escHtml(saved.sugar_label || '')}`
            : 'Saved Default: Disabled')
        : 'Saved Default: Auto-Default (1× Tea • Normal Sugar)';

      return `
        <div class="rounded-2xl border border-stone-200 bg-stone-50 p-3.5 space-y-2.5 flex flex-col justify-between">
          <div class="space-y-2">
            <div class="flex items-center justify-between gap-2">
              <div class="inline-flex items-center gap-1.5 min-w-0">
                <i class="ti ${iconClass} text-base text-tea-700 shrink-0"></i>
                <span class="font-extrabold text-sm text-slate-800 truncate">${escHtml(slotData.slot_label)}</span>
                ${slotData.service_time ? `<span class="text-[11px] text-slate-500 font-semibold shrink-0">(${escHtml(slotData.service_time)})</span>` : ''}
              </div>
              <span class="px-2 py-0.5 rounded-lg border text-[11px] font-bold shrink-0 ${badgeCls}">${escHtml(slotData.status_label)}</span>
            </div>

            ${hasDrink ? `
              <div class="rounded-xl bg-white border border-stone-200 p-2.5 space-y-1">
                <div class="text-sm font-extrabold text-tea-800 flex items-center gap-1.5 flex-wrap">
                  <i class="ti ${drinkIcon} text-base shrink-0"></i>
                  <span>${Number(slotData.quantity || 1)}&times; ${escHtml(slotData.beverage_label)} &bull; ${escHtml(slotData.sugar_label || 'Normal Sugar')}</span>
                </div>
                ${slotData.note ? `<div class="text-xs text-slate-600 break-words">"${escHtml(slotData.note)}"</div>` : ''}
              </div>
            ` : `
              <div class="rounded-xl bg-white border border-stone-200 p-2.5 text-xs font-semibold text-slate-500">
                No drink scheduled for this session.
              </div>
            `}
          </div>

          <div class="text-[11px] text-slate-500 font-medium pt-1 border-t border-stone-200">${savedText}</div>
        </div>`;
    };

    const renderEmpDetail = (emp) => {
      const drinkSummary = [
        emp.morning?.beverage_label ? `AM: ${emp.morning.quantity}× ${emp.morning.beverage_label}` : `AM: ${emp.morning?.status_label || '-'}`,
        emp.afternoon?.beverage_label ? `PM: ${emp.afternoon.quantity}× ${emp.afternoon.beverage_label}` : `PM: ${emp.afternoon?.status_label || '-'}`,
      ].join(' | ');

      empSearchBody.innerHTML = `
        <div class="space-y-3.5">
          <div class="flex items-center justify-between gap-2">
            <button type="button" id="staff-emp-back-btn" class="px-3 py-1.5 rounded-xl bg-stone-100 hover:bg-stone-200 text-slate-700 text-xs font-bold inline-flex items-center gap-1.5">
              <i class="ti ti-arrow-left text-sm"></i><span>Back to Results</span>
            </button>
            <span class="text-[11px] font-semibold text-slate-500">Today: ${escHtml(emp.today_date)}</span>
          </div>

          <div class="rounded-2xl border border-stone-200 bg-white p-3.5 flex items-center gap-3">
            <button
              type="button"
              data-cup-preview="1"
              data-cup-src="${escHtml(emp.cup_url)}"
              data-cup-name="${escHtml(emp.full_name)}"
              data-cup-empid="${escHtml(emp.employee_id)}"
              data-cup-dept="${escHtml(emp.department_name)}"
              data-cup-room="${escHtml(emp.room_name)}"
              data-cup-drink="${escHtml(drinkSummary)}"
              class="relative shrink-0 rounded-xl overflow-hidden border border-stone-200 bg-stone-50"
              title="Tap to zoom cup photo">
              <img src="${escHtml(emp.cup_url)}" alt="" class="w-14 h-14 object-cover">
            </button>
            <div class="min-w-0 flex-1">
              <div class="flex items-center gap-2 flex-wrap">
                <span class="font-extrabold text-base text-slate-800 truncate">${escHtml(emp.full_name)}</span>
                <span class="px-2 py-0.5 rounded-lg bg-stone-100 text-slate-700 text-[11px] font-bold">${escHtml(emp.employee_id)}</span>
                ${emp.is_paused ? '<span class="px-2 py-0.5 rounded-lg bg-rose-50 border border-rose-200 text-rose-700 text-[11px] font-bold">Paused / Away</span>' : ''}
              </div>
              <div class="text-xs font-semibold text-slate-500 mt-0.5 truncate">${escHtml(emp.room_name)} &bull; ${escHtml(emp.department_name)}</div>
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            ${renderSlotCard(emp.morning, 'ti-sun')}
            ${renderSlotCard(emp.afternoon, 'ti-clock')}
          </div>
        </div>`;
    };

    const runEmpSearch = async (query) => {
      if (empSearchAbort) empSearchAbort.abort();
      empSearchAbort = new AbortController();
      lastEmpQuery = query;
      empSearchBody.innerHTML = '<div class="text-center text-xs text-slate-500 py-6">Searching...</div>';
      try {
        const res = await fetch('/staff/employee-search?q=' + encodeURIComponent(query), {
          headers: { 'Accept': 'application/json' },
          credentials: 'same-origin',
          signal: empSearchAbort.signal
        });
        if (!res.ok) return;
        const data = await res.json();
        lastEmpList = data.employees || [];
        renderEmpList(lastEmpList, query);
      } catch (err) {
        if (err && err.name === 'AbortError') return;
        empSearchBody.innerHTML = '<div class="text-center text-xs text-rose-600 py-6">Failed to load employees.</div>';
      }
    };

    const loadEmpDetails = async (userId) => {
      if (empDetailAbort) empDetailAbort.abort();
      empDetailAbort = new AbortController();
      empSearchBody.innerHTML = '<div class="text-center text-xs text-slate-500 py-6">Loading today\'s preferences...</div>';
      try {
        const res = await fetch('/staff/employee-details?user_id=' + encodeURIComponent(String(userId)), {
          headers: { 'Accept': 'application/json' },
          credentials: 'same-origin',
          signal: empDetailAbort.signal
        });
        if (!res.ok) {
          empSearchBody.innerHTML = '<div class="text-center text-xs text-rose-600 py-6">Employee details not found.</div>';
          return;
        }
        const data = await res.json();
        if (data.employee) renderEmpDetail(data.employee);
      } catch (err) {
        if (err && err.name === 'AbortError') return;
        empSearchBody.innerHTML = '<div class="text-center text-xs text-rose-600 py-6">Failed to load employee details.</div>';
      }
    };

    empSearchBtn.addEventListener('click', (e) => {
      e.preventDefault();
      openEmpSearchModal();
      runEmpSearch(empSearchInput.value.trim());
      window.setTimeout(() => empSearchInput.focus(), 60);
    });

    empSearchInput.addEventListener('input', () => {
      const val = empSearchInput.value.trim();
      if (empSearchClear) empSearchClear.classList.toggle('hidden', val === '');
      if (empSearchTimer) window.clearTimeout(empSearchTimer);
      empSearchTimer = window.setTimeout(() => runEmpSearch(val), 180);
    });

    if (empSearchClear) {
      empSearchClear.addEventListener('click', () => {
        empSearchInput.value = '';
        empSearchClear.classList.add('hidden');
        runEmpSearch('');
        empSearchInput.focus();
      });
    }

    empSearchModal.addEventListener('click', (e) => {
      if (e.target.closest('[data-emp-modal-close]') || e.target === empSearchModal) {
        closeEmpSearchModal();
        return;
      }
      const rowBtn = e.target.closest('[data-emp-detail-id]');
      if (rowBtn) {
        const uid = Number(rowBtn.getAttribute('data-emp-detail-id') || '0');
        if (uid > 0) loadEmpDetails(uid);
        return;
      }
      if (e.target.closest('#staff-emp-back-btn')) {
        renderEmpList(lastEmpList, lastEmpQuery);
      }
    });
  }

  // Real-time AJAX Status & New Order Sync + Instant Action Forms
  const isStaffLive = Boolean(document.getElementById('staff-live-panel'));
  const isEmployeeLive = Boolean(document.getElementById('employee-live-root'));

  if (isStaffLive || isEmployeeLive) {
    let lastFp = null;
    let lastUrgentCnt = null;
    let syncing = false;
    let pollTimer = null;
    let unchangedCount = 0;
    let errorCount = 0;
    let lastPollAt = 0;
    let lastInteractionAt = Date.now();
    const BASE_POLL_MS = isStaffLive ? 8000 : 15000;
    const MAX_IDLE_MS = isStaffLive ? 20000 : 30000;

    ['pointerdown', 'keydown', 'touchstart'].forEach((evt) => {
      document.addEventListener(evt, () => { lastInteractionAt = Date.now(); }, { passive: true });
    });

    const getNextPollDelay = () => {
      if (errorCount > 0) {
        return Math.min(60000, BASE_POLL_MS * Math.pow(2, Math.min(errorCount, 3)));
      }
      if (!document.hasFocus() || (Date.now() - lastInteractionAt > 120000)) {
        return MAX_IDLE_MS;
      }
      if (unchangedCount >= 3) {
        return Math.min(MAX_IDLE_MS, BASE_POLL_MS + (unchangedCount - 2) * 3000);
      }
      return BASE_POLL_MS;
    };

    const scheduleNextPoll = () => {
      if (pollTimer) {
        clearTimeout(pollTimer);
        pollTimer = null;
      }
      if (document.visibilityState === 'hidden' || !navigator.onLine) return;
      pollTimer = setTimeout(() => runLivePoll(false), getNextPollDelay());
    };

    const pingNewOrder = () => {
      try {
        const AudioCtx = window.AudioContext || window.webkitAudioContext;
        if (!AudioCtx) return;
        const ctx = new AudioCtx();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.type = 'sine';
        osc.frequency.value = 740;
        gain.gain.value = 0.04;
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.start();
        setTimeout(() => { osc.stop(); ctx.close().catch(() => {}); }, 140);
      } catch (e) {}
    };

    let syncAbortCtrl = null;
    let syncSeq = 0;
    let pendingForcedRefresh = false;

    const syncHtmlFromCurrentUrl = async () => {
      if (syncAbortCtrl) {
        syncAbortCtrl.abort();
      }
      syncAbortCtrl = new AbortController();
      const seq = ++syncSeq;
      const res = await fetch(window.location.href, {
        headers: { 'X-TeaCare-Live': '1' },
        credentials: 'same-origin',
        signal: syncAbortCtrl.signal
      });
      if (!res.ok || seq !== syncSeq) return;
      const html = await res.text();
      if (seq !== syncSeq) return;
      const doc = new DOMParser().parseFromString(html, 'text/html');

      if (isStaffLive) {
        ['staff-live-top', 'staff-live-tabs', 'staff-live-panel'].forEach((id) => {
          const cur = document.getElementById(id);
          const nxt = doc.getElementById(id);
          if (cur && nxt) cur.innerHTML = nxt.innerHTML;
        });

        if (roomModal && !roomModal.classList.contains('hidden')) {
          const openKey = roomModal.getAttribute('data-room-key') || '';
          const freshSrc = openKey ? document.getElementById('room-modal-src-' + openKey) : null;
          const modalBody = document.getElementById('staff-room-modal-body');
          if (freshSrc && modalBody) {
            const prevScroll = modalBody.scrollTop;
            modalBody.innerHTML = freshSrc.innerHTML;
            modalBody.scrollTop = prevScroll;
          } else {
            closeRoomModal();
          }
        }
      } else if (isEmployeeLive) {
        const curRoot = document.getElementById('employee-live-root');
        const nxtDocRoot = doc.getElementById('employee-live-root');
        if (!curRoot || !nxtDocRoot) return;

        const openEditor = curRoot.querySelector('[id^="edit-"]:not(.hidden)');
        if (!openEditor) {
          curRoot.innerHTML = nxtDocRoot.innerHTML;
        } else {
          const curSlots = document.getElementById('employee-slots-grid');
          const nxtSlots = doc.getElementById('employee-slots-grid');
          if (curSlots && nxtSlots) curSlots.className = nxtSlots.className;

          const curReq = document.getElementById('employee-live-requests');
          const nxtReq = doc.getElementById('employee-live-requests');
          if (curReq && nxtReq) {
            curReq.innerHTML = nxtReq.innerHTML;
          } else if (!curReq && nxtReq) {
            curRoot.prepend(document.importNode(nxtReq, true));
          } else if (curReq && !nxtReq) {
            curReq.remove();
          }
        }
      }
    };

    const runLivePoll = async (forceRefresh = false) => {
      if (!navigator.onLine) return;
      if (syncing) {
        if (forceRefresh) pendingForcedRefresh = true;
        return;
      }
      if (!forceRefresh && document.visibilityState === 'hidden') return;
      if (!forceRefresh && document.activeElement && document.activeElement.tagName === 'SELECT' && document.activeElement.closest('#staff-live-panel')) {
        scheduleNextPoll();
        return;
      }

      syncing = true;
      lastPollAt = Date.now();
      try {
        if (forceRefresh) {
          await syncHtmlFromCurrentUrl();
        }
        const endpoint = isStaffLive ? '/staff/poll' : '/today/poll';
        const url = lastFp ? `${endpoint}?fp=${encodeURIComponent(lastFp)}` : endpoint;
        const res = await fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
        if (!res.ok) {
          errorCount++;
          return;
        }
        const data = await res.json();
        if (!data.ok || !data.fingerprint) {
          errorCount++;
          return;
        }
        errorCount = 0;

        if (isStaffLive) {
          if (typeof data.urgent === 'number') {
            if (lastUrgentCnt !== null && data.urgent > lastUrgentCnt) {
              pingNewOrder();
            }
            lastUrgentCnt = data.urgent;
          }
          if (typeof data.urgent_pending === 'number') {
            const navBadge = document.getElementById('nav-urgent-badge');
            if (navBadge) {
              if (data.urgent_pending > 0) {
                navBadge.textContent = data.urgent_pending > 99 ? '99+' : String(data.urgent_pending);
                navBadge.classList.remove('hidden');
                navBadge.classList.add('inline-flex');
              } else {
                navBadge.classList.add('hidden');
                navBadge.classList.remove('inline-flex');
              }
            }
          }
        }

        if (lastFp === null) {
          lastFp = data.fingerprint;
          if (!forceRefresh) return;
        }

        if (!forceRefresh && data.changed && data.fingerprint !== lastFp) {
          lastFp = data.fingerprint;
          unchangedCount = 0;
          await syncHtmlFromCurrentUrl();
        } else if (forceRefresh) {
          lastFp = data.fingerprint;
          unchangedCount = 0;
        } else {
          unchangedCount++;
        }
      } catch (e) {
        if (!e || e.name !== 'AbortError') {
          errorCount++;
        }
      } finally {
        syncing = false;
        if (pendingForcedRefresh) {
          pendingForcedRefresh = false;
          runLivePoll(true);
        } else {
          scheduleNextPoll();
        }
      }
    };

    document.addEventListener('submit', async (e) => {
      const form = e.target;
      if (!(form instanceof HTMLFormElement) || form.dataset.ajaxAction !== '1' || e.defaultPrevented) return;
      e.preventDefault();
      if (!navigator.onLine) {
        showAlert('You appear to be offline. Action requires an active connection.', 'warning');
        return;
      }

      const btn = form.querySelector('button[type="submit"]');
      if (btn) {
        btn.disabled = true;
        btn.classList.add('opacity-70');
      }

      let postCompleted = false;
      try {
        const res = await fetch(form.action, {
          method: 'POST',
          body: new FormData(form),
          headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
          credentials: 'same-origin'
        });
        postCompleted = true;
        const data = await res.json().catch(() => ({ ok: res.ok }));
        if (!data.ok && data.error) {
          showAlert(data.error, 'error');
        } else if (data.ok && data.message) {
          showAlert(data.message, 'success');
        }
      } catch (err) {
        if (!postCompleted) {
          form.submit();
        }
      } finally {
        resetNavProgress();
        if (btn) {
          btn.disabled = false;
          btn.classList.remove('opacity-70');
        }
      }

      if (postCompleted) {
        try {
          await runLivePoll(true);
        } catch (_) {}
      }
    });

    runLivePoll(false);
    document.addEventListener('visibilitychange', () => {
      if (document.visibilityState === 'visible') {
        unchangedCount = 0;
        lastInteractionAt = Date.now();
        runLivePoll(false);
      } else if (pollTimer) {
        clearTimeout(pollTimer);
        pollTimer = null;
      }
    });
    window.addEventListener('focus', () => {
      unchangedCount = 0;
      lastInteractionAt = Date.now();
      if (document.visibilityState === 'visible' && (Date.now() - lastPollAt) >= 3000) {
        runLivePoll(false);
      }
    });
    window.addEventListener('online', () => {
      unchangedCount = 0;
      errorCount = 0;
      lastInteractionAt = Date.now();
      runLivePoll(false);
    });
  }
})();
