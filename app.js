(() => {
  "use strict";

  const form = document.getElementById("orderForm");
  const statusEl = form ? form.querySelector(".form-status") : null;
  const openButtons = document.querySelectorAll("[data-open-form]");

  // Плавный скролл к якорям с учетом sticky header
  function scrollToTarget(targetId) {
    if (targetId === "#top") {
      window.scrollTo({ top: 0, behavior: "smooth" });
      return;
    }

    const target = document.querySelector(targetId);
    if (!target) return;

    const header = document.querySelector(".site-header");
    const headerHeight = header ? header.getBoundingClientRect().height : 0;
    const top = window.scrollY + target.getBoundingClientRect().top - headerHeight - 10;

    window.scrollTo({ top, behavior: "smooth" });
  }

  document.addEventListener("click", (e) => {
    const link = e.target && e.target.closest ? e.target.closest('a[href^="#"]') : null;
    if (!link) return;
    const href = link.getAttribute("href");
    if (!href || href === "#") return;

    // Если это якорь, сделаем скролл через JS, чтобы отступ от header был корректным
    e.preventDefault();
    scrollToTarget(href);
  });

  openButtons.forEach((btn) => {
    btn.addEventListener("click", () => {
      scrollToTarget("#contacts");
      const phoneInput = form && form.querySelector('input[name="phone"]');
      if (phoneInput) setTimeout(() => phoneInput.focus(), 250);
    });
  });

  const galleryRoot = document.querySelector("[data-gallery-carousel]");
  if (galleryRoot) {
    const galleryTrack = galleryRoot.querySelector("[data-gallery-track]");
    const galleryCaption = document.getElementById("aboutGalleryCaption");
    const galleryPrev = galleryRoot.querySelector("[data-gallery-prev]");
    const galleryNext = galleryRoot.querySelector("[data-gallery-next]");
    const galleryViewport = galleryRoot.querySelector(".gallery-carousel__viewport");
    const gallerySlideEls = galleryRoot.querySelectorAll(".gallery-carousel__slide");

    const gallerySlidesData = [
      { caption: "Бокс / ремонт" },
      { caption: "Процесс ремонта" },
      { caption: "Оборудование цеха" }
    ];

    let galleryIndex = 0;

    function showGallerySlide(i) {
      const n = gallerySlidesData.length;
      galleryIndex = (i + n) % n;
      if (galleryTrack) {
        galleryTrack.style.transform = `translateX(calc(-${galleryIndex} * 100% / ${n}))`;
      }
      const s = gallerySlidesData[galleryIndex];
      if (galleryCaption) galleryCaption.textContent = s.caption;
      gallerySlideEls.forEach((el, idx) => {
        el.setAttribute("aria-hidden", idx === galleryIndex ? "false" : "true");
      });
      galleryRoot.setAttribute("aria-label", `Галерея ремонта, фото ${galleryIndex + 1} из ${n}`);
    }

    if (galleryPrev) galleryPrev.addEventListener("click", () => showGallerySlide(galleryIndex - 1));
    if (galleryNext) galleryNext.addEventListener("click", () => showGallerySlide(galleryIndex + 1));

    galleryRoot.addEventListener("keydown", (e) => {
      if (e.key === "ArrowLeft") {
        e.preventDefault();
        showGallerySlide(galleryIndex - 1);
      } else if (e.key === "ArrowRight") {
        e.preventDefault();
        showGallerySlide(galleryIndex + 1);
      }
    });

    let galleryTouchX = null;
    if (galleryViewport) {
      galleryViewport.addEventListener(
        "touchstart",
        (e) => {
          if (e.changedTouches && e.changedTouches.length) galleryTouchX = e.changedTouches[0].screenX;
        },
        { passive: true }
      );
      galleryViewport.addEventListener(
        "touchend",
        (e) => {
          if (galleryTouchX == null || !e.changedTouches || !e.changedTouches.length) return;
          const dx = e.changedTouches[0].screenX - galleryTouchX;
          galleryTouchX = null;
          if (Math.abs(dx) < 48) return;
          if (dx > 0) showGallerySlide(galleryIndex - 1);
          else showGallerySlide(galleryIndex + 1);
        },
        { passive: true }
      );
    }

    showGallerySlide(0);
  }

  function onlyDigits(str) {
    return String(str).replace(/\D/g, "");
  }

  // Маска телефона +7 (999) 999-99-99 — без «ломания» строки padEnd
  function formatPhone(value) {
    let digits = onlyDigits(value).slice(0, 11);
    if (digits.startsWith("8")) digits = "7" + digits.slice(1);
    if (digits.length === 10) digits = "7" + digits;
    if (!digits.length) return "";

    const s = digits.length <= 11 ? digits : digits.slice(0, 11);
    const partA = s.slice(1, 4);
    const partB = s.slice(4, 7);
    const partC = s.slice(7, 9);
    const partD = s.slice(9, 11);

    let out = "+7";
    if (partA.length) out += ` (${partA}`;
    if (partA.length === 3) out += ")";
    if (partB.length) out += ` ${partB}`;
    if (partB.length === 3) out += "-";
    if (partC.length) out += partC;
    if (partC.length === 2) out += "-";
    if (partD.length) out += partD;
    return out.trim();
  }

  function todayIsoLocal() {
    const d = new Date();
    const pad = (n) => String(n).padStart(2, "0");
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
  }

  function refreshMeetingDateMin(meetingDateInput) {
    if (!meetingDateInput) return;
    const min = todayIsoLocal();
    meetingDateInput.min = min;
    if (meetingDateInput.value && meetingDateInput.value < min) {
      meetingDateInput.value = min;
    }
  }

  function normalizeMeetingTime(str) {
    const t = String(str).trim();
    if (/^\d{2}:\d{2}:\d{2}$/.test(t)) return t.slice(0, 5);
    return t;
  }

  function phoneIsValid(maskedPhone) {
    // Проверяем по цифрам: должно быть 11 цифр (7 + 10) или можно допустить 10/11 с учетом ввода
    const digits = onlyDigits(maskedPhone);
    return digits.length === 11 || digits.length === 10;
  }

  function setStatus(text, variant) {
    if (!statusEl) return;
    statusEl.textContent = text;
    statusEl.dataset.variant = variant || "";
  }

  if (form) {
    const phoneInput = form.querySelector('input[name="phone"]');
    if (phoneInput) {
      phoneInput.addEventListener("input", () => {
        const raw = phoneInput.value;
        const masked = formatPhone(raw);
        phoneInput.value = masked;
      });

      phoneInput.addEventListener("blur", () => {
        // При потере фокуса обновим формат до "чистого" маскированного
        phoneInput.value = formatPhone(phoneInput.value);
      });
    }

    const meetingDateInput = form.querySelector("#meetingDate");
    const meetingTimeInput = form.querySelector("#meetingTime");

    if (meetingDateInput) {
      refreshMeetingDateMin(meetingDateInput);
      meetingDateInput.addEventListener("focus", () => refreshMeetingDateMin(meetingDateInput));
      document.addEventListener("visibilitychange", () => {
        if (document.visibilityState === "visible") refreshMeetingDateMin(meetingDateInput);
      });
      window.addEventListener("pageshow", () => refreshMeetingDateMin(meetingDateInput));
    }

    form.addEventListener("submit", async (e) => {
      e.preventDefault();

      setStatus("", "");

      const data = {
        name: form.elements["name"] ? form.elements["name"].value.trim() : "",
        phone: form.elements["phone"] ? form.elements["phone"].value.trim() : "",
        brand: form.elements["brand"] ? form.elements["brand"].value.trim() : "",
        message: form.elements["message"] ? form.elements["message"].value.trim() : "",
        meeting_date: form.elements["meeting_date"] ? form.elements["meeting_date"].value.trim() : "",
        meeting_time: normalizeMeetingTime(
          form.elements["meeting_time"] ? form.elements["meeting_time"].value.trim() : ""
        ),
        website: form.elements["website"] ? form.elements["website"].value.trim() : ""
      };

      if (!data.name || !data.phone || !data.brand || !data.message || !data.meeting_date || !data.meeting_time) {
        setStatus("Заполните все поля, включая дату и время встречи.", "error");
        return;
      }

      if (!phoneIsValid(data.phone)) {
        setStatus("Проверьте номер телефона (формат +7 ...).", "error");
        return;
      }

      // Отправка через Fetch API
      try {
        setStatus("Отправляем заявку...", "");

        const res = await fetch("send.php", {
          method: "POST",
          headers: { "Content-Type": "application/json; charset=utf-8" },
          body: JSON.stringify(data)
        });

        if (!res.ok) {
          let extra = "";
          try {
            const txt = await res.text();
            if (txt) extra = " Ответ: " + txt.slice(0, 120);
          } catch {}
          setStatus(`Ошибка сервера (${res.status}).${extra ? extra : ""}`, "error");
          return;
        }

        const json = await res.json().catch(() => null);
        if (json && json.ok) {
          setStatus(json.message || "Заявка успешно отправлена! Мы скоро свяжемся.", "success");
          form.reset();
          refreshMeetingDateMin(meetingDateInput);
          if (meetingTimeInput) meetingTimeInput.value = "";
        } else {
          setStatus((json && json.message) || "Не удалось отправить заявку. Попробуйте еще раз.", "error");
        }
      } catch (err) {
        setStatus("Сеть недоступна или произошла ошибка. Попробуйте позже.", "error");
      }
    });
  }

  // Admin login modal
  const adminBackdrop = document.getElementById("adminBackdrop");
  const adminForm = document.getElementById("adminLoginForm");
  const adminStatusEl = document.getElementById("adminStatus");
  const adminOpenButtons = document.querySelectorAll("[data-open-admin]");
  const adminCloseButtons = document.querySelectorAll("[data-close-admin]");
  let lastFocus = null;

  function setAdminStatus(text) {
    if (!adminStatusEl) return;
    adminStatusEl.textContent = text || "";
  }

  function openAdminModal() {
    if (!adminBackdrop) return;
    lastFocus = document.activeElement;
    adminBackdrop.classList.add("is-open");
    adminBackdrop.setAttribute("aria-hidden", "false");

    const passInput = adminForm ? adminForm.querySelector('input[name="password"]') : null;
    if (passInput) setTimeout(() => passInput.focus(), 0);
  }

  function closeAdminModal() {
    if (!adminBackdrop) return;
    adminBackdrop.classList.remove("is-open");
    adminBackdrop.setAttribute("aria-hidden", "true");
    setAdminStatus("");
    if (lastFocus && lastFocus.focus) lastFocus.focus();
  }

  adminOpenButtons.forEach((btn) => {
    btn.addEventListener("click", (e) => {
      // Предотвращаем переход на admin.php (иначе можно увидеть исходник PHP,
      // если страница открывается без PHP-сервера).
      e.preventDefault();
      openAdminModal();
    });
  });

  adminCloseButtons.forEach((btn) => {
    btn.addEventListener("click", () => closeAdminModal());
  });

  if (adminBackdrop) {
    adminBackdrop.addEventListener("click", (e) => {
      if (e.target === adminBackdrop) closeAdminModal();
    });
  }

  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape" && adminBackdrop && adminBackdrop.classList.contains("is-open")) {
      closeAdminModal();
    }
  });

  if (adminForm) {
    adminForm.addEventListener("submit", async (e) => {
      e.preventDefault();
      setAdminStatus("");

      const passInput = adminForm.querySelector('input[name="password"]');
      const password = passInput ? passInput.value : "";
      if (!password.trim()) {
        setAdminStatus("Введите пароль.");
        return;
      }

      const submitBtn = adminForm.querySelector('button[type="submit"]');
      if (submitBtn) submitBtn.disabled = true;

      try {
        setAdminStatus("Проверяем пароль...");
        const res = await fetch("admin_auth.php", {
          method: "POST",
          headers: { "Content-Type": "application/json; charset=utf-8" },
          body: JSON.stringify({ password })
        });

        let json = null;
        try {
          json = await res.json();
        } catch (e) {
          // Если PHP не выполняется, либо ответ не JSON — будет сюда.
          json = null;
        }

        if (res.ok && json && json.ok) {
          // После успешной проверки сессия будет установлена на сервере.
          window.location.href = "admin.php";
          return;
        }

        if (!res.ok) {
          setAdminStatus(`Ошибка сервера: ${res.status}.`);
          return;
        }

        setAdminStatus(
          (json && json.message) ||
          "Не удалось войти: ответ не JSON. Проверьте, что `admin_auth.php` выполняется PHP-сервером."
        );
      } catch (err) {
        setAdminStatus("Сервер не отвечает. Проверьте, что проект открыт через PHP-сервер (не через file://).");
      } finally {
        if (submitBtn) submitBtn.disabled = false;
      }
    });
  }

  (function () {
    const buttons = Array.from(document.querySelectorAll("button.btn, a.btn"));
    if (!buttons.length) return;
    const i = (buttons.length * 11 + 4) % buttons.length;
    buttons[i].classList.add("has-easter-egg");
  })();

  // ========= FX: частицы + звуки =========
  // Отключено по требованию: убрать визуальные эффекты и шум
  if (false) {
  const fxCanvas = document.getElementById("fxCanvas");
  const reduceMotion = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  const effectsWidget = document.getElementById("effectsWidget");
  const effectsToggleBtn = document.getElementById("effectsToggle");
  const soundToggleBtn = document.getElementById("soundToggle");

  // Версию хранимых настроек поднимаем, чтобы сбросить "старые" значения.
  const storageKey = "macrotrans_fx_v2";
  const initial = (() => {
    try {
      const raw = localStorage.getItem(storageKey);
      if (!raw) return null;
      return JSON.parse(raw);
    } catch {
      return null;
    }
  })();

  let fxEnabled = true;
  let soundEnabled = true;
  if (initial && typeof initial.fxEnabled === "boolean") fxEnabled = initial.fxEnabled;
  if (initial && typeof initial.soundEnabled === "boolean") soundEnabled = initial.soundEnabled;

  function saveFxSettings() {
    try {
      localStorage.setItem(storageKey, JSON.stringify({ fxEnabled, soundEnabled }));
    } catch {
      // ignore
    }
  }

  function updateWidgetLabels() {
    if (effectsToggleBtn) effectsToggleBtn.textContent = `Эффекты: ${fxEnabled ? "вкл" : "выкл"}`;
    if (soundToggleBtn) soundToggleBtn.textContent = `Звук: ${soundEnabled ? "вкл" : "выкл"}`;
    if (effectsWidget && !fxEnabled) {
      effectsWidget.style.opacity = ".86";
    }
  }

  updateWidgetLabels();

  if (effectsToggleBtn) {
    effectsToggleBtn.addEventListener("click", () => {
      fxEnabled = !fxEnabled;
      saveFxSettings();
      updateWidgetLabels();
    });
  }

  // Звук может играть только после первого клика пользователя.
  let audioCtx = null;
  let audioUnlocked = false;
  let lastSoundAt = 0;
  let noiseBuffer = null;

  function unlockAudio() {
    if (audioUnlocked) return;
    if (!soundEnabled) return;
    try {
      const Ctx = window.AudioContext || window.webkitAudioContext;
      if (!Ctx) return;
      audioCtx = new Ctx();
      // В некоторых браузерах нужно явное resume()
      if (audioCtx && audioCtx.state === "suspended" && audioCtx.resume) {
        audioCtx.resume().catch(() => {});
      }

      // Буфер шума для "взрывов" (создаём один раз)
      try {
        const length = Math.floor(audioCtx.sampleRate * 0.08);
        noiseBuffer = audioCtx.createBuffer(1, length, audioCtx.sampleRate);
        const data = noiseBuffer.getChannelData(0);
        for (let i = 0; i < length; i++) data[i] = Math.random() * 2 - 1;
      } catch {
        noiseBuffer = null;
      }

      audioUnlocked = true;
    } catch {
      audioUnlocked = false;
    }
  }

  function playApocalypseSound(intensity) {
    if (!soundEnabled) return;
    if (!audioUnlocked) {
      unlockAudio();
      if (!audioUnlocked) return;
    }
    if (!audioCtx) return;
    if (audioCtx && audioCtx.state === "suspended" && audioCtx.resume) {
      audioCtx.resume().catch(() => {});
    }

    const now = performance.now();
    if (now - lastSoundAt < 180) return; // глобальный лимит шума
    lastSoundAt = now;

    const t0 = audioCtx.currentTime;

    const gain = audioCtx.createGain();
    gain.gain.setValueAtTime(0.0001, t0);
    gain.gain.exponentialRampToValueAtTime(0.45 + (intensity || 0) * 0.25, t0 + 0.015);
    gain.gain.exponentialRampToValueAtTime(0.0001, t0 + 0.22);

    // Компонента шума (взрывной "грохот")
    if (noiseBuffer) {
      const src = audioCtx.createBufferSource();
      src.buffer = noiseBuffer;
      const filter = audioCtx.createBiquadFilter();
      filter.type = "lowpass";
      filter.frequency.setValueAtTime(420 + (intensity || 0) * 160, t0);

      src.connect(filter);
      filter.connect(gain);
      gain.connect(audioCtx.destination);

      src.start(t0);
      src.stop(t0 + 0.08);
    } else {
      // Fallback
      const osc = audioCtx.createOscillator();
      osc.type = "sawtooth";
      const base = 80 + (intensity || 0) * 50;
      osc.frequency.setValueAtTime(base, t0);
      osc.frequency.exponentialRampToValueAtTime(20, t0 + 0.18);
      osc.connect(gain);
      gain.connect(audioCtx.destination);
      osc.start(t0);
      osc.stop(t0 + 0.2);
    }
  }

  if (soundToggleBtn) {
    soundToggleBtn.addEventListener("click", () => {
      soundEnabled = !soundEnabled;
      saveFxSettings();
      updateWidgetLabels();
      if (soundEnabled) {
        // Дадим подсказку: звук включится после клика
        unlockAudio();
      }
    });
  }

  // Универсальная разблокировка по первому действию пользователя.
  document.addEventListener("pointerdown", () => unlockAudio(), { once: true });
  document.addEventListener("click", () => unlockAudio(), { once: true });

  if (!fxCanvas) {
    // Без canvas эффектов не будет.
    fxEnabled = false;
  } else {
    const ctx = fxCanvas.getContext("2d");
    let w = 0;
    let h = 0;
    let dpr = 1;

    function resize() {
      dpr = Math.max(1, Math.min(2, window.devicePixelRatio || 1));
      w = Math.floor(window.innerWidth);
      h = Math.floor(window.innerHeight);
      fxCanvas.width = Math.floor(w * dpr);
      fxCanvas.height = Math.floor(h * dpr);
      fxCanvas.style.width = w + "px";
      fxCanvas.style.height = h + "px";
      if (ctx) ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    }

    resize();
    window.addEventListener("resize", resize);

    const particles = [];

    function rand(min, max) {
      return min + Math.random() * (max - min);
    }

    const apocColors = ["#ff2d2d", "#ffb000", "#ffd700", "#00e5ff", "#a100ff", "#ffffff"];
    const apocColorsRainbow = ["#ff2d2d", "#ffcc00", "#44e6ff", "#38ff7a", "#b100ff"];

    let shake = 0;
    let banner = null;

    function spawnBurst(x, y, fxType) {
      if (!fxEnabled) return;
      const type = fxType || "burst";

      const reduced = reduceMotion ? 0.55 : 1;
      const count = Math.floor((type === "rainbow" ? 72 : 52) * reduced);
      const lifeBase = Math.floor((type === "rainbow" ? 860 : 650) * reduced);

      for (let i = 0; i < count; i++) {
        const angle = rand(-Math.PI, Math.PI);
        const speed = type === "rainbow" ? rand(1.2, 4.2) : rand(1.0, 3.8);

        // Более агрессивный апокалиптический всплеск
        const upBias = type === "rainbow" ? -0.6 : -0.3;
        const vx = Math.cos(angle) * speed + rand(-0.6, 0.6);
        const vy = Math.sin(angle) * speed + rand(-0.5, 0.5) + upBias;

        const color =
          type === "rainbow"
            ? apocColorsRainbow[Math.floor(Math.random() * apocColorsRainbow.length)]
            : apocColors[Math.floor(Math.random() * 3)];

        particles.push({
          x,
          y,
          vx,
          vy,
          size: rand(2, type === "rainbow" ? 6 : 5),
          rot: rand(0, Math.PI * 2),
          vr: rand(-0.2, 0.2),
          life: lifeBase + rand(-140, 140),
          born: performance.now(),
          color,
          alpha: 1,
          line: Math.random() < 0.33,
          drag: rand(0.982, 0.996)
        });
      }

      shake = Math.min(18, shake + (type === "rainbow" ? 12 : 9));
      const t = performance.now();
      if (!banner || t - banner.born > 1200) {
        banner = { x, y, born: t, life: 900, text: "APOCALYPSE" };
      }
    }

    function draw(now) {
      if (!ctx) return;
      ctx.clearRect(0, 0, w, h);

      const sx = shake > 0 ? rand(-shake, shake) : 0;
      const sy = shake > 0 ? rand(-shake, shake) : 0;
      if (shake > 0) shake *= 0.92;

      ctx.save();
      ctx.translate(sx, sy);

      // Эффект "взрывного" хвоста
      for (let i = particles.length - 1; i >= 0; i--) {
        const p = particles[i];
        const age = now - p.born;
        if (age > p.life) {
          particles.splice(i, 1);
          continue;
        }

        // обновление физики
        p.vx *= p.drag;
        p.vy *= p.drag;
        p.x += p.vx;
        p.y += p.vy;
        p.vy += 0.035; // гравитация
        p.rot += p.vr;

        const t = 1 - age / p.life; // 1 -> 0
        const a = Math.max(0, Math.min(1, t)) * 0.95;
        p.alpha = a;

        ctx.save();
        ctx.globalAlpha = p.alpha;
        ctx.translate(p.x, p.y);
        ctx.rotate(p.rot);

        if (p.line) {
          ctx.strokeStyle = p.color;
          ctx.lineWidth = 2;
          ctx.beginPath();
          ctx.moveTo(-p.size, 0);
          ctx.lineTo(p.size, 0);
          ctx.stroke();
        } else {
          ctx.fillStyle = p.color;
          ctx.fillRect(-p.size / 2, -p.size / 2, p.size, p.size);
        }
        ctx.restore();
      }

      // Текстовый баннер
      if (banner) {
        const age = now - banner.born;
        if (age < banner.life) {
          const tt = 1 - age / banner.life;
          const a = Math.max(0, Math.min(1, tt));
          ctx.globalAlpha = 0.22 * a;
          ctx.fillStyle = "rgba(255,180,0,1)";
          ctx.font = `900 ${Math.max(18, Math.floor(18 + 18 * a))}px Montserrat, sans-serif`;
          ctx.textAlign = "center";
          ctx.fillText(banner.text, banner.x, banner.y);
        } else {
          banner = null;
        }
      }

      ctx.restore();

      // Лимит на количество частиц
      if (particles.length > 650) particles.splice(0, particles.length - 650);

      requestAnimationFrame(draw);
    }

    requestAnimationFrame(draw);

    // Троттлинг на элемент, чтобы не спамить частицами.
    const lastSpawn = new WeakMap();

    function shouldSpawn(el) {
      const now = performance.now();
      const prev = lastSpawn.get(el) || 0;
      if (now - prev < 650) return false;
      lastSpawn.set(el, now);
      return true;
    }

    function getFxType(el) {
      if (!el) return "burst";
      const fx = el.getAttribute && el.getAttribute("data-fx");
      if (fx === "rainbow") return "rainbow";
      return "apocalypse";
    }

    document.addEventListener(
      "mouseover",
      (e) => {
        if (!fxEnabled) return;
        unlockAudio(); // попробуем разлочить звук даже если пользователь только наводит

        const t = e.target;
        if (!t || !t.closest) return;

        const el =
          t.closest(".card, .service-card, .brand-logo, .btn-primary, .btn-ghost, .btn, .map") || null;
        if (!el) return;
        if (!shouldSpawn(el)) return;

        const rect = el.getBoundingClientRect();
        const x = Math.max(0, Math.min(w, e.clientX || (rect.left + rect.width / 2)));
        const y = Math.max(0, Math.min(h, e.clientY || (rect.top + rect.height / 2)));

        const fxType = getFxType(el);
        spawnBurst(x, y, fxType);

        playApocalypseSound(fxType === "rainbow" ? 1 : 0);
      },
      { passive: true }
    );
  }

  // Reveal on scroll (лёгкая анимация появления)
  if (!reduceMotion) {
    const revealItems = document.querySelectorAll(
      ".card, .service-card, .brand-logo, .adv-card, .b2b-card, .gallery-item, .cta-inner, .footer-col"
    );
    revealItems.forEach((el) => el.classList.add("reveal-item"));

    if ("IntersectionObserver" in window) {
      const io = new IntersectionObserver(
        (entries) => {
          entries.forEach((en) => {
            if (en.isIntersecting) {
              en.target.classList.add("is-visible");
              io.unobserve(en.target);
            }
          });
        },
        { threshold: 0.18 }
      );

      revealItems.forEach((el) => io.observe(el));
    } else {
      // старые браузеры: просто показываем
      revealItems.forEach((el) => el.classList.add("is-visible"));
    }
  }

  // ========= Unicorsn + Rainbow Poop =========
  // Делаем отдельный "режим": единороги бегают по экрану,
  // а раз в 10 секунд оставляют радужную "какашечку".
  const unicornLayer = document.getElementById("unicornLayer");
  let unicorns = [];
  let unicornAnimId = 0;
  let poopTimerId = 0;

  if (unicornLayer) {
    const UNICORN_COUNT = 4;
    const animationsAllowed = !reduceMotion;

    function uRand(min, max) {
      return min + Math.random() * (max - min);
    }

    function createUnicorn(i) {
      const el = document.createElement("div");
      el.className = "unicorn" + (animationsAllowed ? " unicorn-bob" : "");
      // Псевдо-спрайт: используем emoji (легкий вариант без картинок).
      el.textContent = "🦄";
      unicornLayer.appendChild(el);

      const w = window.innerWidth;
      const h = window.innerHeight;
      const size = 44;

      const x = uRand(0, Math.max(0, w - size));
      const y = uRand(0, Math.max(0, h - size - 20));

      const speed = uRand(1.1, 2.6);
      const angle = uRand(0, Math.PI * 2);

      const u = {
        el,
        x,
        y,
        vx: Math.cos(angle) * speed,
        vy: Math.sin(angle) * speed,
        speedBase: speed,
        turnT: performance.now() + uRand(900, 2400),
        jitter: uRand(0.4, 1.0)
      };
      // Стартовая позиция
      el.style.left = `${u.x}px`;
      el.style.top = `${u.y}px`;
      return u;
    }

    unicorns = Array.from({ length: UNICORN_COUNT }, (_, i) => createUnicorn(i));

    function spawnPoop() {
      if (!fxEnabled) return;
      if (!unicorns.length) return;

      const pick = unicorns[Math.floor(Math.random() * unicorns.length)];
      if (!pick) return;

      const el = document.createElement("div");
      el.className = "poop";
      unicornLayer.appendChild(el);

      // Позиционируем чуть ниже ножек "единорога"
      const x = pick.x + 9;
      const y = pick.y + 30;
      el.style.left = `${x}px`;
      el.style.top = `${y}px`;
      // спавним
      requestAnimationFrame(() => el.classList.add("is-spawned"));

      // Удаляем после анимации
      window.setTimeout(() => {
        try {
          el.remove();
        } catch {}
      }, 2200);
    }

    poopTimerId = window.setInterval(spawnPoop, 10000);

    function tick() {
      if (!fxEnabled) {
        unicorns.forEach((u) => u.el.classList.add("is-hidden"));
        unicornAnimId = requestAnimationFrame(tick);
        return;
      }

      unicorns.forEach((u) => u.el.classList.remove("is-hidden"));

      const w = window.innerWidth;
      const h = window.innerHeight;
      const size = 44;
      const padTop = 50;
      const padBottom = 90;

      const now = performance.now();

      unicorns.forEach((u) => {
        // Иногда немного меняем направление (бег хаотичный)
        if (now > u.turnT) {
          const a = uRand(-0.8, 0.8) * u.jitter;
          const cos = Math.cos(a);
          const sin = Math.sin(a);
          const nvx = u.vx * cos - u.vy * sin;
          const nvy = u.vx * sin + u.vy * cos;
          u.vx = nvx;
          u.vy = nvy;
          u.turnT = now + uRand(900, 2600);
        }

        u.x += u.vx;
        u.y += u.vy;

        // Bounce от краёв
        if (u.x <= 0) {
          u.x = 0;
          u.vx = Math.abs(u.vx);
        } else if (u.x >= w - size) {
          u.x = w - size;
          u.vx = -Math.abs(u.vx);
        }

        if (u.y <= padTop) {
          u.y = padTop;
          u.vy = Math.abs(u.vy);
        } else if (u.y >= h - padBottom - size) {
          u.y = h - padBottom - size;
          u.vy = -Math.abs(u.vy);
        }

        u.el.style.left = `${u.x}px`;
        u.el.style.top = `${u.y}px`;
      });

      unicornAnimId = requestAnimationFrame(tick);
    }

    unicornAnimId = requestAnimationFrame(tick);

    window.addEventListener("resize", () => {
      // подрезаем позиции при ресайзе
      const w = window.innerWidth;
      const h = window.innerHeight;
      const size = 44;
      const padTop = 50;
      const padBottom = 90;
      unicorns.forEach((u) => {
        u.x = Math.max(0, Math.min(w - size, u.x));
        u.y = Math.max(padTop, Math.min(h - padBottom - size, u.y));
        u.el.style.left = `${u.x}px`;
        u.el.style.top = `${u.y}px`;
      });
    });
  } else if (unicornLayer) {
    unicornLayer.innerHTML = "";
  }
  }
})();

