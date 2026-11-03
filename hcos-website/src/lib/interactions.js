/* ==========================================================================
   HCOS Services — Homepage interactions
   Motion is deliberately restrained and fully opt-out via prefers-reduced-motion.
   ========================================================================== */

export default function initializeInteractions() {
  'use strict';

  var cleanups = [];
  function listen(target, type, handler, options) {
    target.addEventListener(type, handler, options);
    cleanups.push(function () { target.removeEventListener(type, handler, options); });
  }

  var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ---------- Navigation: scroll state ------------------------------------ */

  var nav = document.getElementById('nav');
  var ticking = false;

  function syncNav() {
    nav.classList.toggle('is-scrolled', window.scrollY > 40);
    ticking = false;
  }

  listen(window, 'scroll', function () {
    if (!ticking) {
      window.requestAnimationFrame(syncNav);
      ticking = true;
    }
  }, { passive: true });

  syncNav();

  /* ---------- Navigation: mobile panel ------------------------------------ */

  var toggle = document.getElementById('navToggle');
  var panel = document.getElementById('navPanel');

  function closePanel() {
    panel.classList.remove('is-open');
    document.body.classList.remove('nav-open');
    toggle.setAttribute('aria-expanded', 'false');
    toggle.setAttribute('aria-label', 'Open menu');
  }

  listen(toggle, 'click', function () {
    var open = panel.classList.toggle('is-open');
    document.body.classList.toggle('nav-open', open);
    toggle.setAttribute('aria-expanded', String(open));
    toggle.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
  });

  listen(panel, 'click', function (e) {
    if (e.target.tagName === 'A') closePanel();
  });

  listen(document, 'keydown', function (e) {
    if (e.key === 'Escape' && panel.classList.contains('is-open')) closePanel();
  });

  /* ---------- Hero: slide rotator ----------------------------------------- */

  var slidesWrap = document.getElementById('heroSlides');
  var dotsWrap = document.getElementById('heroDots');

  if (slidesWrap && dotsWrap) {
    var slides = Array.prototype.slice.call(slidesWrap.querySelectorAll('.hero__slide'));
    var index = 0;
    var timer = null;
    var INTERVAL = 5200; // keep in step with the dotFill duration in styles.css

    slides.forEach(function (slide, i) {
      var li = document.createElement('li');
      var btn = document.createElement('button');
      btn.className = 'hero__dot' + (i === 0 ? ' is-active' : '');
      btn.type = 'button';
      btn.setAttribute('aria-label', 'Show slide ' + (i + 1) + ' of ' + slides.length);
      listen(btn, 'click', function () { go(i); restart(); });
      li.appendChild(btn);
      dotsWrap.appendChild(li);
      cleanups.push(function () { li.remove(); });
    });

    var dots = Array.prototype.slice.call(dotsWrap.querySelectorAll('.hero__dot'));

    function go(next) {
      if (next === index) return;
      slides[index].classList.remove('is-active');
      dots[index].classList.remove('is-active');
      index = next;
      slides[index].classList.add('is-active');
      // restart the dot progress animation
      var dot = dots[index];
      dot.classList.remove('is-active');
      void dot.offsetWidth;
      dot.classList.add('is-active');
    }

    function tick() { go((index + 1) % slides.length); }

    function start() {
      if (reduceMotion || timer !== null) return;
      timer = window.setInterval(tick, INTERVAL);
    }

    function stop() { window.clearInterval(timer); timer = null; }
    function restart() { stop(); start(); }

    var hero = slidesWrap.closest('.hero');
    listen(hero, 'mouseenter', stop);
    listen(hero, 'mouseleave', start);
    listen(hero, 'focusin', stop);

    listen(document, 'visibilitychange', function () {
      if (document.hidden) { stop(); } else { start(); }
    });

    start();
    cleanups.push(stop);
  }

  /* ---------- Clients marquee: duplicate the track for a seamless loop ----- */

  var group = document.getElementById('marqueeGroup');
  if (group) {
    var clone = group.cloneNode(true);
    clone.removeAttribute('id');
    clone.setAttribute('aria-hidden', 'true');
    clone.querySelectorAll('img').forEach(function (img) { img.alt = ''; });
    group.parentNode.appendChild(clone);
    cleanups.push(function () { clone.remove(); });
  }

  /* ---------- Company video: click-to-play facade -------------------------
     The poster + play button are plain HTML; the <video> element is created on
     click with preload="none", so nothing downloads until someone presses play. */

  var videoFigure = document.getElementById('companyVideo');

  if (videoFigure && videoFigure.dataset.video) {
    var playBtn = videoFigure.querySelector('.welcome__play');
    var poster = videoFigure.querySelector('img');

    cleanups.push(function () {
      var player = videoFigure.querySelector('.welcome__player');
      if (player) player.remove();
      videoFigure.classList.remove('is-playing');
      if (!playBtn.isConnected) videoFigure.appendChild(playBtn);
    });
    listen(playBtn, 'click', function () {
      var video = document.createElement('video');
      video.src = videoFigure.dataset.video;
      video.poster = poster ? poster.currentSrc || poster.src : '';
      video.controls = true;
      video.playsInline = true;
      video.preload = 'none';
      video.className = 'welcome__player';
      video.setAttribute('aria-label', 'HCOS company introduction video');

      videoFigure.classList.add('is-playing');
      videoFigure.appendChild(video);
      playBtn.remove();
      video.play().catch(function () { /* user can press the native control */ });
    });
  }

  /* ---------- Forms: contact + event registration --------------------------
     Every form carries data-endpoint / data-fallback / data-subject from the
     build. With an endpoint set, submissions POST there (Web3Forms, Formspree
     and similar all accept multipart FormData with Accept: application/json).
     With no endpoint — the state until a mailbox is connected — the form shows
     an honest notice and a pre-filled mailto so nothing fails silently.
     ------------------------------------------------------------------------ */

  document.querySelectorAll('form[data-endpoint]').forEach(function (form) {
    var notice = form.querySelector('.form__notice');
    var button = form.querySelector('button[type="submit"]');
    var endpoint = (form.dataset.endpoint || '').trim();
    var fallback = form.dataset.fallback || 'info@hcosservices.com';
    var subject = form.dataset.subject || 'Website enquiry';

    var say = function (html, kind) {
      notice.innerHTML = html;
      notice.hidden = false;
      notice.className = 'form__notice' + (kind ? ' form__notice--' + kind : '');
      notice.scrollIntoView({ block: 'nearest' });
    };

    var summary = function () {
      var lines = [];
      form.querySelectorAll('input:not([type=hidden]):not([type=file]), textarea, select').forEach(function (el) {
        var v = el.value.trim();
        if (v) lines.push((el.placeholder || el.name) + ': ' + v);
      });
      return lines.join('\n');
    };

    var invalid = function () {
      var bad = [];
      form.querySelectorAll('[required]').forEach(function (el) {
        var invalidValue = el.type === 'file' ? !el.files.length : !el.value.trim();
        if (invalidValue || (el.type !== 'file' && !el.validity.valid)) bad.push(el);
      });
      return bad;
    };

    listen(form, 'submit', function (e) {
      e.preventDefault();

      var bad = invalid();
      if (bad.length) {
        form.querySelectorAll('[aria-invalid]').forEach(function (el) { el.removeAttribute('aria-invalid'); });
        bad.forEach(function (el) { el.setAttribute('aria-invalid', 'true'); });
        bad[0].focus();
        say('Please complete the required fields with valid information.', 'error');
        return;
      }
      form.querySelectorAll('[aria-invalid]').forEach(function (el) { el.removeAttribute('aria-invalid'); });
      var linkedin = form.elements.namedItem('linkedin_url');
      if (linkedin) {
        var profile;
        try { profile = new URL(linkedin.value); } catch (err) {}
        if (!profile || profile.protocol !== 'https:' || !/(^|\.)linkedin\.com$/i.test(profile.hostname) || !/^\/(in|pub)\/[^/\s]+\/?$/.test(profile.pathname) || profile.username || profile.password || profile.port) {
          linkedin.setAttribute('aria-invalid', 'true'); linkedin.focus();
          say('Enter the complete HTTPS link to your personal LinkedIn profile.', 'error'); return;
        }
      }
      var headshot = form.elements.namedItem('headshot');
      if (headshot && headshot.files.length && (!['image/jpeg', 'image/png', 'image/webp'].includes(headshot.files[0].type) || headshot.files[0].size > Number(headshot.dataset.maxBytes))) {
        headshot.setAttribute('aria-invalid', 'true'); headshot.focus();
        say('Choose a JPG, PNG or WebP headshot no larger than 3 MB.', 'error'); return;
      }

      if (!endpoint) {
        if (headshot) { say('Online registration is unavailable in this preview. Headshots are accepted only through the live registration form.', 'error'); return; }
        var mailto = 'mailto:' + fallback + '?subject=' + encodeURIComponent(subject) + '&body=' + encodeURIComponent(summary());
        say('Online sending is not connected yet. <a href="' + mailto + '">Open this in your email app</a> or write to <a href="mailto:' + fallback + '">' + fallback + '</a>.');
        return;
      }

      var data = new FormData(form);
      if (!data.has('name') && (data.has('firstName') || data.has('lastName'))) {
        data.set('name', [data.get('firstName'), data.get('lastName')].filter(Boolean).join(' ').trim());
      }
      data.append('subject', subject);
      button.disabled = true;

      fetch(endpoint, { method: 'POST', body: data, headers: { Accept: 'application/json' } })
        .then(function (r) { return r.ok ? r.json().catch(function () { return {}; }) : Promise.reject(r); })
        .then(function (data) {
          var redirect = data && typeof data.redirect === 'string' && data.redirect.charAt(0) === '/' && data.redirect.charAt(1) !== '/' ? data.redirect : '';
          var confirmation = form.id === 'registerForm' ? document.querySelector('.registration-success') : null;
          if (confirmation) {
            form.reset();
            say('Thank you for registering. Our Delegate Acquisition Team will reach out to you shortly.', 'ok');
            if (typeof confirmation.showModal === 'function') {
              listen(confirmation, 'close', function () { if (redirect) window.location.href = redirect; }, { once: true });
              confirmation.showModal();
            } else {
              window.alert(notice.textContent);
              if (redirect) window.location.href = redirect;
            }
            return;
          }
          if (redirect) { window.location.href = redirect; return; }
          form.reset();
          say('Thank you — your message has been sent. We will be in touch shortly.', 'ok');
          if (form.dataset.reload) setTimeout(function () { window.location.reload(); }, 900);
        })
        .catch(function (r) {
          // A JSON error from our own API is worth showing as-is.
          if (r && typeof r.json === 'function') {
            return r.json().then(function (d) { throw new Error((d && d.error) || ''); }, function () { throw new Error(''); });
          }
          throw r;
        })
        .catch(function (err) {
          var reason = err && err.message ? err.message.replace(/[<>&]/g, '') : '';
          if (headshot) { say(reason || 'Your registration could not be completed. Please try again.', 'error'); return; }
          var mailto = 'mailto:' + fallback + '?subject=' + encodeURIComponent(subject) + '&body=' + encodeURIComponent(summary());
          say((reason ? reason + ' ' : 'Sorry, that did not go through. ') + '<a href="' + mailto + '">Send it by email instead</a>.', 'error');
        })
        .finally(function () { button.disabled = false; });
    });
  });

  /* ---------- Visitor counter ----------------------------------------------
     The footer ships with the seed number already in the HTML. One POST per
     browser session bumps the live count; on any failure (self-hosted without
     the API, store not connected) the seed stays, so nothing ever blanks out. */

  var counter = document.getElementById('visitorCounter');

  if (counter) {
    var counted = false;
    try { counted = sessionStorage.getItem('hcos-counted') === '1'; } catch (e) {}

    fetch('/api/visits', { method: counted ? 'GET' : 'POST' })
      .then(function (r) { return r.ok ? r.json() : Promise.reject(r); })
      .then(function (data) {
        if (!data || typeof data.count !== 'number') return;
        var digits = String(data.count).split('');
        counter.innerHTML = digits.map(function (d) { return '<span>' + d + '</span>'; }).join('');
        counter.classList.toggle('counter--live', !!data.live);
        if (!counted && data.live) { try { sessionStorage.setItem('hcos-counted', '1'); } catch (e) {} }
      })
      .catch(function () { /* keep the seeded digits */ });
  }

  /* ---------- Scroll reveal ----------------------------------------------- */

  var revealables = document.querySelectorAll('.reveal, .reveal-clip');

  if (reduceMotion || !('IntersectionObserver' in window)) {
    revealables.forEach(function (el) { el.classList.add('is-in'); });
  } else {
    var observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-in');
          observer.unobserve(entry.target);
        }
      });
    }, { rootMargin: '0px 0px -12% 0px', threshold: 0.08 });

    revealables.forEach(function (el) { observer.observe(el); });
    cleanups.push(function () { observer.disconnect(); });
  }

  return function () {
    cleanups.forEach(function (cleanup) { cleanup(); });
    closePanel();
  };
}
