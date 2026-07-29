/**
 * @file
 * Builds the level-3 "On this page" sidebar from the body's own `<h2>`s and
 * drives its scroll-spy.
 *
 * The body is a plain WYSIWYG field — there is no separate "sections" data
 * structure to read server-side, and the content text format doesn't allow
 * authoring an `id` attribute on a heading. So the table of contents is
 * derived entirely here: find the `<h2>`s, slugify their text into ids, and
 * build the nav from that. A page with fewer than two headings (there isn't
 * enough to navigate) leaves the sidebar hidden and the body full width.
 */
((Drupal, once) => {
  'use strict';

  function slugify(text, used) {
    let slug = text
      .toLowerCase()
      .trim()
      .replace(/['"]/g, '')
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/^-+|-+$/g, '') || 'section';
    let candidate = slug;
    let n = 2;
    while (used.has(candidate)) {
      candidate = `${slug}-${n}`;
      n += 1;
    }
    used.add(candidate);
    return candidate;
  }

  function buildToc(root) {
    const content = root.querySelector('[data-mcc-toc-content]');
    const sidebar = root.querySelector('[data-mcc-toc-sidebar]');
    const nav = root.querySelector('[data-mcc-toc]');
    if (!content || !sidebar || !nav) {
      return null;
    }

    const headings = Array.from(content.querySelectorAll('h2'));
    if (headings.length < 2) {
      return null;
    }

    const used = new Set();
    const links = [];
    headings.forEach((heading) => {
      if (!heading.id) {
        heading.id = slugify(heading.textContent || '', used);
      } else {
        used.add(heading.id);
      }
      const link = document.createElement('a');
      link.href = `#${heading.id}`;
      link.className = 'mcc-page__toc-link';
      link.textContent = heading.textContent;
      nav.appendChild(link);
      links.push({ heading, link });
    });

    sidebar.hidden = false;
    return links;
  }

  function setActive(links, activeLink) {
    links.forEach(({ link }) => {
      const isActive = link === activeLink;
      link.classList.toggle('is-active', isActive);
      if (isActive) {
        link.setAttribute('aria-current', 'true');
      } else {
        link.removeAttribute('aria-current');
      }
    });
  }

  function attachScrollSpy(links) {
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function currentActive() {
      const nearBottom = window.innerHeight + window.scrollY
        >= document.documentElement.scrollHeight - 80;
      if (nearBottom) {
        return links[links.length - 1].link;
      }
      let active = links[0].link;
      links.forEach(({ heading, link }) => {
        if (heading.getBoundingClientRect().top <= 140) {
          active = link;
        }
      });
      return active;
    }

    let ticking = false;
    function onScroll() {
      if (ticking) {
        return;
      }
      ticking = true;
      window.requestAnimationFrame(() => {
        setActive(links, currentActive());
        ticking = false;
      });
    }

    setActive(links, currentActive());
    window.addEventListener('scroll', onScroll, { passive: true });

    links.forEach(({ heading, link }) => {
      link.addEventListener('click', (event) => {
        event.preventDefault();
        const top = heading.getBoundingClientRect().top + window.scrollY - 96;
        window.scrollTo({ top, behavior: reduceMotion ? 'auto' : 'smooth' });
        setActive(links, link);
      });
    });
  }

  Drupal.behaviors.mccPageBody = {
    attach(context) {
      once('mcc-page-body-toc', '[data-mcc-section-nav]', context).forEach((root) => {
        const links = buildToc(root);
        if (links) {
          attachScrollSpy(links);
        }
      });
    },
  };
})(Drupal, once);
