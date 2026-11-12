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

  // Personal Cup Lightbox Popup Modal
  const cupModal = document.getElementById('cup-photo-modal');
  const closeCupModal = () => {
    if (!cupModal) return;
    cupModal.classList.add('hidden');
    document.body.style.overflow = '';
  };

  document.addEventListener('click', (e) => {
    if (!cupModal) return;
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
      document.body.style.overflow = 'hidden';
      return;
    }

    if (e.target.closest('[data-cup-modal-close]') || e.target === cupModal) {
      closeCupModal();
    }
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && cupModal && !cupModal.classList.contains('hidden')) {
      closeCupModal();
    }
  });

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
