/**
 * @file
 * Keeps the print sheet on one page.
 *
 * The controller already sizes the type down for a busy month, from the line
 * and lane counts it knows about. That estimate can't account for how a
 * particular event title happens to wrap, so this measures the sheet as
 * rendered and keeps shrinking until nothing inside it is clipped.
 *
 * Progressive enhancement, not a dependency: with JavaScript off the sheet
 * still prints at the server's estimate, which is deliberately conservative.
 */
((Drupal, once) => {
  'use strict';

  const MIN_SCALE = 0.55;
  const STEP = 0.03;

  /**
   * Anything inside the sheet whose content is taller or wider than its box.
   *
   * Elements that let content spill visibly are skipped — they aren't hiding
   * anything, and the sheet's own `overflow: hidden` is the backstop we
   * actually care about.
   */
  function clippedElements(sheet) {
    const clipped = [];
    for (const element of [sheet, ...sheet.querySelectorAll('*')]) {
      const style = getComputedStyle(element);
      if (style.overflowY === 'visible' && style.overflowX === 'visible') {
        continue;
      }
      // Screen-reader-only text is a 1px box by design, and always "overflows".
      if (element.clientWidth <= 1 || element.clientHeight <= 1) {
        continue;
      }
      if (element.scrollHeight - element.clientHeight > 1
        || element.scrollWidth - element.clientWidth > 1) {
        clipped.push(element);
      }
    }
    return clipped;
  }

  function fit(root) {
    const sheet = root.querySelector('.mcc-print-sheet');
    if (!sheet) {
      return;
    }

    let scale = parseFloat(root.style.getPropertyValue('--print-scale')) || 1;
    // Bounded: at some point the answer is the "weekly digest" layout, not
    // smaller type, and an unbounded loop here would freeze the page.
    while (clippedElements(sheet).length && scale > MIN_SCALE) {
      scale = Math.max(MIN_SCALE, scale - STEP);
      root.style.setProperty('--print-scale', scale.toFixed(2));
    }

    root.dataset.mccPrintFitted = clippedElements(sheet).length ? 'clipped' : 'ok';
  }

  Drupal.behaviors.mccCalendarPrint = {
    attach(context) {
      once('mcc-print-fit', '.mcc-print', context).forEach((root) => {
        fit(root);
        // Print styles change the sheet's box; re-check before the dialog opens.
        window.addEventListener('beforeprint', () => fit(root));

        const button = root.querySelector('[data-mcc-print]');
        if (button) {
          button.addEventListener('click', () => window.print());
        }
      });

      // Webfonts land after first paint and change how titles wrap.
      if (document.fonts && document.fonts.status !== 'loaded') {
        document.fonts.ready.then(() => {
          context.querySelectorAll?.('.mcc-print').forEach((root) => fit(root));
        });
      }
    },
  };
})(Drupal, once);
