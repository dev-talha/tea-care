(() => {
  const pet = document.getElementById('tc-monkey-pet');
  if (!pet) return;

  const todayStr = pet.dataset.today || new Date().toISOString().slice(0, 10);
  const csrfToken = pet.dataset.csrf || '';
  const getPosKey = () => (window.innerWidth >= 768 ? 'tc_monkey_pos_desktop_v1' : 'tc_monkey_pos_mobile_v1');
  const DISMISS_KEY = 'tc_monkey_dismissed_v1';
  const NEXT_VISIT_KEY = 'tc_monkey_next_visit_v1';
  const EPISODE_IDX_KEY = 'tc_monkey_ep_idx_v1';
  const COOLDOWN_MS = 120000; // Exactly 2 minutes between appearances

  try {
    if (localStorage.getItem(DISMISS_KEY) === todayStr) {
      pet.remove();
      return;
    }
  } catch (_) {}

  const styleEl = document.createElement('style');
  styleEl.textContent = `
    @keyframes mBounceFast { 0%,100%{transform:translateY(0) scale(1)} 25%{transform:translateY(-9px) rotate(-4deg) scale(1.04)} 50%{transform:translateY(0) scale(.98)} 75%{transform:translateY(-8px) rotate(4deg) scale(1.04)} }
    @keyframes mGentleBob { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-3px)} }
    @keyframes mSipTilt { 0%,100%{transform:rotate(0)} 45%,70%{transform:translate(-18px,-18px) rotate(-42deg)} }
    @keyframes mEmptyShake { 0%,100%{transform:translate(0,0) rotate(165deg)} 25%{transform:translate(-3px,-3px) rotate(185deg)} 50%{transform:translate(3px,2px) rotate(150deg)} 75%{transform:translate(-2px,-2px) rotate(180deg)} }
    @keyframes mEmptyUpside { 0%,100%{transform:translate(-4px,4px) rotate(172deg)} 50%{transform:translate(-4px,6px) rotate(168deg)} }
    @keyframes mSpillTilt { 0%,100%{transform:rotate(0)} 45%,75%{transform:rotate(72deg) translate(4px,-4px)} }
    @keyframes mWaveArm { 0%,100%{transform:rotate(0)} 50%{transform:rotate(-38deg) translateY(-10px)} }
    @keyframes mCookieDunk { 0%,100%{transform:rotate(0)} 50%{transform:rotate(28deg) translate(16px,-10px)} }
    @keyframes mTailWag { 0%,100%{transform:rotate(0)} 50%{transform:rotate(14deg)} }
    @keyframes mSadSlump { 0%,100%{transform:translateY(3px) rotate(-2deg)} 50%{transform:translateY(5px) rotate(2deg)} }
    @keyframes mSnooze { 0%,100%{transform:rotate(-3deg) translateY(0)} 50%{transform:rotate(4deg) translateY(3px)} }
    @keyframes mCoolLean { 0%,100%{transform:rotate(-4deg) translateY(0)} 50%{transform:rotate(3deg) translateY(-2px)} }
    @keyframes mHyperWiggle { 0%,100%{transform:translate(0,0) rotate(-6deg)} 25%{transform:translate(-4px,-7px) rotate(6deg)} 50%{transform:translate(4px,-3px) rotate(-5deg)} 75%{transform:translate(0,-8px) rotate(5deg)} }
    @keyframes mHotFan { 0%,100%{transform:rotate(0)} 50%{transform:rotate(-28deg) translateY(-12px)} }
    @keyframes mBusyNod { 0%,100%{transform:translateY(0)} 50%{transform:translateY(2px)} }
    @keyframes mZenFloat { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-8px)} }
    @keyframes mSugarJump { 0%,100%{transform:translateY(0) scale(1)} 50%{transform:translateY(-11px) scale(1.06)} }
    @keyframes mFloatFx { 0%,100%{opacity:.4;transform:translateY(0)} 50%{opacity:1;transform:translateY(-5px)} }
    @keyframes mJuggleCup { 0%,100%{transform:translate(81px,68px)} 40%{transform:translate(78px,50px) rotate(-15deg)} 65%{transform:translate(83px,56px) rotate(11deg)} }
    #tc-monkey-pet.m-act-changa #tc-monkey-svg { animation: mBounceFast .7s ease-in-out infinite; }
    #tc-monkey-pet.m-act-changa #m-tail-group { transform-origin: 30px 88px; animation: mTailWag .3s ease-in-out infinite; }
    #tc-monkey-pet.m-act-sip #m-arm-right-cup { transform-origin: 80px 76px; animation: mSipTilt 3.2s ease-in-out infinite; }
    #tc-monkey-pet.m-act-sip #m-head-group { transform-origin: 56px 52px; animation: mGentleBob 1.7s ease-in-out infinite; }
    #tc-monkey-pet.m-act-check #m-arm-right-cup { transform-origin: 86px 78px; animation: mEmptyShake .7s ease-in-out infinite; }
    #tc-monkey-pet.m-act-sad #m-head-group { transform-origin: 56px 52px; animation: mSadSlump 2.6s ease-in-out infinite; }
    #tc-monkey-pet.m-act-sad #m-cup-group { transform-origin: 9px 12px; animation: mEmptyUpside 2.4s ease-in-out infinite; }
    #tc-monkey-pet.m-act-sleepy #m-head-group { transform-origin: 56px 56px; animation: mSnooze 3s ease-in-out infinite; }
    #tc-monkey-pet.m-act-aroma #tc-monkey-svg { animation: mGentleBob 1.9s ease-in-out infinite; }
    #tc-monkey-pet.m-act-wave #m-arm-left { transform-origin: 36px 74px; animation: mWaveArm .55s ease-in-out infinite; }
    #tc-monkey-pet.m-act-juggle #m-cup-group { animation: mJuggleCup 1.15s ease-in-out infinite; }
    #tc-monkey-pet.m-act-cool #tc-monkey-svg { animation: mCoolLean 2.2s ease-in-out infinite; }
    #tc-monkey-pet.m-act-cookie #m-arm-left { transform-origin: 36px 74px; animation: mCookieDunk 1.8s ease-in-out infinite; }
    #tc-monkey-pet.m-act-hyper #tc-monkey-svg { animation: mHyperWiggle .65s ease-in-out infinite; }
    #tc-monkey-pet.m-act-hyper #m-tail-group { transform-origin: 30px 88px; animation: mTailWag .25s ease-in-out infinite; }
    #tc-monkey-pet.m-act-wink #m-head-group { transform-origin: 56px 52px; animation: mGentleBob 1.4s ease-in-out infinite; }
    #tc-monkey-pet.m-act-sugar #tc-monkey-svg { animation: mSugarJump .48s ease-in-out infinite; }
    #tc-monkey-pet.m-act-spill #m-arm-right-cup { transform-origin: 80px 76px; animation: mSpillTilt 2.1s ease-in-out infinite; }
    #tc-monkey-pet.m-act-hot #m-arm-left { transform-origin: 36px 74px; animation: mHotFan .28s ease-in-out infinite; }
    #tc-monkey-pet.m-act-hot #m-head-group { transform-origin: 56px 52px; animation: mHyperWiggle .45s ease-in-out infinite; }
    #tc-monkey-pet.m-act-boss #m-head-group { transform-origin: 56px 52px; animation: mBusyNod .35s ease-in-out infinite; }
    #tc-monkey-pet.m-act-zen #tc-monkey-svg { animation: mZenFloat 2.8s ease-in-out infinite; }
    #tc-monkey-pet.m-act-magic #m-arm-left { transform-origin: 36px 74px; animation: mWaveArm .75s ease-in-out infinite; }
    #tc-monkey-pet #m-fx-zzz, #tc-monkey-pet #m-fx-sparks, #tc-monkey-pet #m-fx-question, #tc-monkey-pet #m-fx-heart, #tc-monkey-pet #m-fx-music, #tc-monkey-pet #m-fx-drops, #tc-monkey-pet #m-fx-heat {
      animation: mFloatFx 1.1s ease-in-out infinite;
    }
  `;
  document.head.appendChild(styleEl);

  const bubble = document.getElementById('tc-monkey-bubble');
  const dragEl = document.getElementById('tc-monkey-drag');
  const closeBtn = document.getElementById('tc-monkey-close');

  const els = {
    eyesOpen: document.getElementById('m-eyes-open'),
    eyesHappy: document.getElementById('m-eyes-happy'),
    eyesWink: document.getElementById('m-eyes-wink'),
    eyesSleepy: document.getElementById('m-eyes-sleepy'),
    eyesSad: document.getElementById('m-eyes-sad'),
    eyesSquint: document.getElementById('m-eyes-squint'),
    eyesStar: document.getElementById('m-eyes-star'),
    shades: document.getElementById('m-fx-shades'),
    headset: document.getElementById('m-fx-headset'),
    biscuit: document.getElementById('m-prop-biscuit'),
    sugar: document.getElementById('m-prop-sugar'),
    mouthSmile: document.getElementById('m-mouth-smile'),
    mouthOpen: document.getElementById('m-mouth-open'),
    mouthSad: document.getElementById('m-mouth-sad'),
    mouthBig: document.getElementById('m-mouth-big'),
    mouthTongue: document.getElementById('m-mouth-tongue'),
    liquid: document.getElementById('m-cup-liquid'),
    steam: document.getElementById('m-cup-steam'),
    fxZzz: document.getElementById('m-fx-zzz'),
    fxSparks: document.getElementById('m-fx-sparks'),
    fxQuestion: document.getElementById('m-fx-question'),
    fxHeart: document.getElementById('m-fx-heart'),
    fxMusic: document.getElementById('m-fx-music'),
    fxDrops: document.getElementById('m-fx-drops'),
    fxHeat: document.getElementById('m-fx-heat'),
  };

  const setGroup = (groupMap, activeKey) => {
    Object.entries(groupMap).forEach(([k, el]) => {
      if (el) el.classList.toggle('hidden', k !== activeKey);
    });
  };

  const showBubble = (txt, ms = 5000) => {
    if (!bubble) return;
    if (!txt) {
      bubble.classList.add('opacity-0', 'translate-y-1');
      return;
    }
    bubble.textContent = txt;
    bubble.classList.remove('opacity-0', 'translate-y-1');
    clearTimeout(bubble._hideTimer);
    bubble._hideTimer = setTimeout(() => {
      bubble.classList.add('opacity-0', 'translate-y-1');
    }, ms);
  };

  const clearActivityClasses = () => {
    pet.className = pet.className
      .split(/\s+/)
      .filter((c) => c && !c.startsWith('m-act-'))
      .join(' ');
  };

  // 18 Unique Hilarious Activities!
  const ACTIVITIES = {
    sneak_sip: {
      cls: 'm-act-sip', eyes: 'happy', mouth: 'open', hasDrink: true, fx: 'heart',
      shades: false, headset: false, biscuit: false, sugar: false, text: 'Mmm... hot cha sip!',
    },
    hot_tongue: {
      cls: 'm-act-hot', eyes: 'squint', mouth: 'tongue', hasDrink: true, fx: 'heat',
      shades: false, headset: false, biscuit: false, sugar: false, text: 'OUCH! Too hot to sip!',
    },
    changa_dance: {
      cls: 'm-act-changa', eyes: 'open', mouth: 'big', hasDrink: true, fx: 'sparks',
      shades: false, headset: false, biscuit: false, sugar: false, text: '5s later: 100% Changa!',
    },
    empty_cup_check: {
      cls: 'm-act-check', eyes: 'squint', mouth: 'open', hasDrink: false, fx: 'question',
      shades: false, headset: false, biscuit: false, sugar: false, text: 'Wait... Cup is EMPTY?!',
    },
    sad_pout: {
      cls: 'm-act-sad', eyes: 'sad', mouth: 'sad', hasDrink: false, fx: null,
      shades: false, headset: false, biscuit: false, sugar: false, text: 'No coffee left... mon kharap',
    },
    yawn_sleepy: {
      cls: 'm-act-sleepy', eyes: 'sleepy', mouth: 'open', hasDrink: true, fx: 'zzz',
      shades: false, headset: false, biscuit: false, sugar: false, text: 'Sleepy... need more cha',
    },
    aroma_sniff: {
      cls: 'm-act-aroma', eyes: 'happy', mouth: 'smile', hasDrink: true, fx: 'heart',
      shades: false, headset: false, biscuit: false, sugar: false, text: 'Ooh, ginger tea aroma!',
    },
    cookie_dunk: {
      cls: 'm-act-cookie', eyes: 'open', mouth: 'big', hasDrink: true, fx: 'heart',
      shades: false, headset: false, biscuit: true, sugar: false, text: 'Cha + biscuit time!',
    },
    spill_oops: {
      cls: 'm-act-spill', eyes: 'open', mouth: 'open', hasDrink: true, fx: 'drops',
      shades: false, headset: false, biscuit: false, sugar: false, text: 'Oops! Spilled a drop... shh!',
    },
    headset_focus: {
      cls: 'm-act-boss', eyes: 'squint', mouth: 'smile', hasDrink: true, fx: null,
      shades: false, headset: true, biscuit: false, sugar: false, text: 'Boss coming! Act busy!',
    },
    cool_shades: {
      cls: 'm-act-cool', eyes: 'open', mouth: 'smile', hasDrink: true, fx: 'sparks',
      shades: true, headset: false, biscuit: false, sugar: false, text: 'Boss mode: Coffee loaded',
    },
    sugar_rush: {
      cls: 'm-act-sugar', eyes: 'star', mouth: 'big', hasDrink: true, fx: 'sparks',
      shades: false, headset: false, biscuit: false, sugar: true, text: 'Extra sugar?! Turbo speed!',
    },
    cup_juggle: {
      cls: 'm-act-juggle', eyes: 'open', mouth: 'big', hasDrink: true, fx: 'sparks',
      shades: false, headset: false, biscuit: false, sugar: false, text: 'Look! Tea acrobat trick!',
    },
    hyper_spin: {
      cls: 'm-act-hyper', eyes: 'open', mouth: 'big', hasDrink: true, fx: 'music',
      shades: false, headset: false, biscuit: false, sugar: false, text: 'Caffeine groove! La la~',
    },
    yoga_zen: {
      cls: 'm-act-zen', eyes: 'happy', mouth: 'smile', hasDrink: true, fx: 'heart',
      shades: false, headset: false, biscuit: false, sugar: false, text: 'Inner peace... tea zen',
    },
    magician_cup: {
      cls: 'm-act-magic', eyes: 'star', mouth: 'smile', hasDrink: true, fx: 'sparks',
      shades: false, headset: false, biscuit: false, sugar: false, text: 'Abracadabra! Free refill?!',
    },
    peek_wave: {
      cls: 'm-act-wave', eyes: 'open', mouth: 'smile', hasDrink: true, fx: 'heart',
      shades: false, headset: false, biscuit: false, sugar: false, text: 'Hello team! Stay refreshed',
    },
    cheeky_wink: {
      cls: 'm-act-wink', eyes: 'wink', mouth: 'smile', hasDrink: true, fx: 'sparks',
      shades: false, headset: false, biscuit: false, sugar: false, text: 'Psst... ordered tea yet?',
    },
  };

  // 4 Mini-Episodes (Each lasts 30 seconds total on screen -> then hides for 2 minutes!)
  const EPISODES = [
    [
      { key: 'sneak_sip', ms: 5500 },
      { key: 'hot_tongue', ms: 5000 },
      { key: 'changa_dance', ms: 6500 },
      { key: 'empty_cup_check', ms: 6500 },
      { key: 'sad_pout', ms: 6500 },
    ],
    [
      { key: 'aroma_sniff', ms: 5500 },
      { key: 'cookie_dunk', ms: 6000 },
      { key: 'spill_oops', ms: 6000 },
      { key: 'headset_focus', ms: 6500 },
      { key: 'cool_shades', ms: 6000 },
    ],
    [
      { key: 'yawn_sleepy', ms: 6000 },
      { key: 'sugar_rush', ms: 6000 },
      { key: 'hyper_spin', ms: 6000 },
      { key: 'yoga_zen', ms: 6000 },
      { key: 'peek_wave', ms: 6000 },
    ],
    [
      { key: 'magician_cup', ms: 6000 },
      { key: 'cup_juggle', ms: 6000 },
      { key: 'cheeky_wink', ms: 6000 },
      { key: 'empty_cup_check', ms: 6000 },
      { key: 'changa_dance', ms: 6000 },
    ],
  ];

  const applyActivity = (key, bubbleMs = 5200) => {
    const act = ACTIVITIES[key];
    if (!act) return;

    clearActivityClasses();
    pet.classList.add(act.cls);

    setGroup({
      open: els.eyesOpen,
      happy: els.eyesHappy,
      wink: els.eyesWink,
      sleepy: els.eyesSleepy,
      sad: els.eyesSad,
      squint: els.eyesSquint,
      star: els.eyesStar,
    }, act.eyes);

    setGroup({
      smile: els.mouthSmile,
      open: els.mouthOpen,
      sad: els.mouthSad,
      big: els.mouthBig,
      tongue: els.mouthTongue,
    }, act.mouth);

    if (els.shades) els.shades.classList.toggle('hidden', !act.shades);
    if (els.headset) els.headset.classList.toggle('hidden', !act.headset);
    if (els.biscuit) els.biscuit.classList.toggle('hidden', !act.biscuit);
    if (els.sugar) els.sugar.classList.toggle('hidden', !act.sugar);
    if (els.liquid) els.liquid.classList.toggle('hidden', !act.hasDrink);
    if (els.steam) els.steam.classList.toggle('hidden', !act.hasDrink);

    setGroup({
      zzz: els.fxZzz,
      sparks: els.fxSparks,
      question: els.fxQuestion,
      heart: els.fxHeart,
      music: els.fxMusic,
      drops: els.fxDrops,
      heat: els.fxHeat,
    }, act.fx);

    showBubble(act.text, bubbleMs);
  };

  // Position & Clamping
  const resetDefaultPos = () => {
    pet.classList.remove('tc-monkey-dragged');
    pet.style.position = '';
    pet.style.left = '';
    pet.style.top = '';
    pet.style.right = '';
    pet.style.bottom = '';
  };

  const applyClampedPos = (left, top) => {
    const w = pet.offsetWidth || 112;
    const h = pet.offsetHeight || 116;
    const maxL = Math.max(8, window.innerWidth - w - 8);
    const maxT = Math.max(58, window.innerHeight - h - 12);
    const clampedL = Math.min(Math.max(8, left), maxL);
    const clampedT = Math.min(Math.max(58, top), maxT);

    pet.classList.add('tc-monkey-dragged');
    pet.style.position = 'fixed';
    pet.style.left = clampedL + 'px';
    pet.style.top = clampedT + 'px';
    pet.style.right = 'auto';
    pet.style.bottom = 'auto';
  };

  const restorePosition = () => {
    try {
      const saved = JSON.parse(localStorage.getItem(getPosKey()) || 'null');
      if (saved && typeof saved.left === 'number' && typeof saved.top === 'number') {
        applyClampedPos(saved.left, saved.top);
        return;
      }
    } catch (_) {}
    resetDefaultPos();
  };

  window.addEventListener('resize', () => {
    if (!pet.classList.contains('hidden')) {
      restorePosition();
    }
  });

  // Visit Scheduler (stays 30s, then hides for 2 minutes)
  let stepTimers = [];
  let exitTimer = null;
  let cooldownTimer = null;
  let hideFadeTimer = null;
  let isVisible = false;
  let isDragging = false;
  let wantsExitAfterDrag = false;

  const clearStepTimers = () => {
    stepTimers.forEach((t) => clearTimeout(t));
    stepTimers = [];
  };

  const hideForCooldown = () => {
    clearStepTimers();
    clearTimeout(exitTimer);
    clearTimeout(hideFadeTimer);
    if (isDragging) {
      wantsExitAfterDrag = true;
      return;
    }
    wantsExitAfterDrag = false;
    isVisible = false;
    showBubble('', 0);
    pet.style.transition = 'opacity 280ms ease, transform 280ms ease';
    pet.style.opacity = '0';
    pet.style.transform = 'translateY(14px) scale(0.85)';
    hideFadeTimer = setTimeout(() => {
      if (!isVisible) {
        pet.classList.add('hidden');
      }
    }, 300);

    const nextTs = Date.now() + COOLDOWN_MS;
    try { localStorage.setItem(NEXT_VISIT_KEY, String(nextTs)); } catch (_) {}
    scheduleCooldownCheck(COOLDOWN_MS);
  };

  const startVisitEpisode = () => {
    clearStepTimers();
    clearTimeout(exitTimer);
    clearTimeout(cooldownTimer);

    let epIdx = 0;
    try {
      epIdx = parseInt(localStorage.getItem(EPISODE_IDX_KEY) || '0', 10) || 0;
      localStorage.setItem(EPISODE_IDX_KEY, String((epIdx + 1) % EPISODES.length));
    } catch (_) {}

    const episode = EPISODES[epIdx % EPISODES.length];
    isVisible = true;
    pet.classList.remove('hidden');
    restorePosition();
    pet.style.transition = 'opacity 260ms ease, transform 260ms ease';
    pet.style.opacity = '0';
    pet.style.transform = 'translateY(14px) scale(0.85)';
    requestAnimationFrame(() => {
      pet.style.opacity = '1';
      pet.style.transform = 'translateY(0) scale(1)';
    });

    let elapsed = 0;
    episode.forEach((step, idx) => {
      if (idx === 0) {
        applyActivity(step.key, step.ms - 400);
      } else {
        const t = setTimeout(() => {
          if (!isVisible) return;
          applyActivity(step.key, step.ms - 400);
        }, elapsed);
        stepTimers.push(t);
      }
      elapsed += step.ms;
    });

    exitTimer = setTimeout(hideForCooldown, elapsed);
  };

  const scheduleCooldownCheck = (ms) => {
    clearTimeout(cooldownTimer);
    cooldownTimer = setTimeout(() => {
      try {
        if (localStorage.getItem(DISMISS_KEY) === todayStr) return;
      } catch (_) {}
      startVisitEpisode();
    }, Math.max(500, ms));
  };

  // Drag + Tap Interaction
  if (dragEl) {
    let moved = false;
    let startX = 0;
    let startY = 0;
    let origLeft = 0;
    let origTop = 0;

    dragEl.addEventListener('pointerdown', (e) => {
      if (e.button && e.button !== 0) return;
      isDragging = true;
      moved = false;
      dragEl.setPointerCapture(e.pointerId);
      const rect = pet.getBoundingClientRect();
      startX = e.clientX;
      startY = e.clientY;
      origLeft = rect.left;
      origTop = rect.top;
    });

    dragEl.addEventListener('pointermove', (e) => {
      if (!isDragging) return;
      const dx = e.clientX - startX;
      const dy = e.clientY - startY;
      if (Math.abs(dx) > 5 || Math.abs(dy) > 5) {
        moved = true;
        applyClampedPos(origLeft + dx, origTop + dy);
      }
    });

    const endDrag = (e) => {
      if (!isDragging) return;
      isDragging = false;
      try { dragEl.releasePointerCapture(e.pointerId); } catch (_) {}
      if (moved) {
        const rect = pet.getBoundingClientRect();
        try { localStorage.setItem(getPosKey(), JSON.stringify({ left: rect.left, top: rect.top })); } catch (_) {}
        if (wantsExitAfterDrag) {
          exitTimer = setTimeout(hideForCooldown, 2000);
        }
      } else {
        // Tap triggers a surprise instant trick from all 18 activities
        clearTimeout(hideFadeTimer);
        clearStepTimers();
        isVisible = true;
        pet.classList.remove('hidden');
        pet.style.opacity = '1';
        pet.style.transform = 'translateY(0) scale(1)';
        const keys = Object.keys(ACTIVITIES);
        const randKey = keys[Math.floor(Math.random() * keys.length)];
        applyActivity(randKey, 5500);
        clearTimeout(exitTimer);
        exitTimer = setTimeout(hideForCooldown, 8000);
      }
    };

    dragEl.addEventListener('pointerup', endDrag);
    dragEl.addEventListener('pointercancel', endDrag);
  }

  // Close Button -> Hide for Today Only
  if (closeBtn) {
    closeBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      clearStepTimers();
      clearTimeout(exitTimer);
      clearTimeout(cooldownTimer);
      isVisible = false;
      try { localStorage.setItem(DISMISS_KEY, todayStr); } catch (_) {}
      pet.style.transition = 'opacity 180ms ease, transform 180ms ease';
      pet.style.opacity = '0';
      pet.style.transform = 'scale(0.85)';
      setTimeout(() => pet.remove(), 200);

      const body = new URLSearchParams();
      body.set('_csrf', csrfToken);
      fetch('/api/monkey-dismiss', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
        body: body.toString(),
      }).catch(() => {});
    });
  }

  // Start first visit right away or resume 2-min cooldown
  let nextTs = 0;
  try {
    nextTs = parseInt(localStorage.getItem(NEXT_VISIT_KEY) || '0', 10) || 0;
  } catch (_) {}

  const now = Date.now();
  if (nextTs === 0 || now >= nextTs || (nextTs - now) > (COOLDOWN_MS + 15000)) {
    setTimeout(startVisitEpisode, 1200);
  } else {
    scheduleCooldownCheck(nextTs - now);
  }
})();
