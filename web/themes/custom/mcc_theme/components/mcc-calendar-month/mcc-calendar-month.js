/**
 * MCC Calendar Month — progressive enhancement.
 *
 * Without JS, each day cell is an anchor to its detail panel, revealed via the
 * CSS :target selector. This script upgrades that into an in-place toggle: one
 * panel open at a time, smooth scroll, focus management and Escape-to-close —
 * without changing the URL hash.
 */
(function () {
  'use strict';

  function initCalendar(root) {
    if (root.dataset.calReady === 'true') {
      return;
    }
    root.dataset.calReady = 'true';

    var days = root.querySelectorAll('a.mcc-calendar__day[data-day]');
    var lastFocused = null;

    function panelFor(key) {
      return root.querySelector('#day-' + (window.CSS && CSS.escape ? CSS.escape(key) : key));
    }

    function closeOpen() {
      var open = root.querySelector('.mcc-calendar__detail.is-open');
      if (open) {
        open.classList.remove('is-open');
      }
      var selected = root.querySelector('a.mcc-calendar__day.is-selected');
      if (selected) {
        selected.classList.remove('is-selected');
        selected.setAttribute('aria-expanded', 'false');
      }
    }

    function openDay(dayEl) {
      var key = dayEl.getAttribute('data-day');
      var panel = panelFor(key);
      if (!panel) {
        return;
      }
      var alreadyOpen = panel.classList.contains('is-open');
      closeOpen();
      if (alreadyOpen) {
        return;
      }
      panel.classList.add('is-open');
      dayEl.classList.add('is-selected');
      dayEl.setAttribute('aria-expanded', 'true');
      lastFocused = dayEl;
      panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      // Defer focus so it doesn't fight the smooth scroll.
      window.setTimeout(function () { panel.focus({ preventScroll: true }); }, 60);
    }

    days.forEach(function (dayEl) {
      dayEl.setAttribute('aria-expanded', 'false');
      dayEl.addEventListener('click', function (e) {
        e.preventDefault();
        openDay(dayEl);
      });
    });

    root.querySelectorAll('[data-detail-close]').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        closeOpen();
        if (lastFocused) {
          lastFocused.focus();
        }
      });
    });

    root.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && root.querySelector('.mcc-calendar__detail.is-open')) {
        closeOpen();
        if (lastFocused) {
          lastFocused.focus();
        }
      }
    });
  }

  function initAll() {
    document.querySelectorAll('[data-mcc-calendar]').forEach(initCalendar);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAll);
  } else {
    initAll();
  }

  // Re-run after Drupal AJAX / BigPipe insertions when available.
  if (window.Drupal && Drupal.behaviors) {
    Drupal.behaviors.mccCalendarMonth = { attach: initAll };
  }
})();
