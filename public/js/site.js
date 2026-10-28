// TEAMIT Cambodia - small UI behaviours (menu, search, dropdowns, sticky header)
(function () {
    const $ = (s, el = document) => el.querySelector(s);
    const $$ = (s, el = document) => Array.from(el.querySelectorAll(s));

    function toggleLayer(layer, open, focusEl) {
        if (!layer) return;
        layer.hidden = !open;
        document.body.style.overflow = open ? 'hidden' : '';
        if (open && focusEl) focusEl.focus();
    }

    // Mobile menu
    const menu = $('[data-menu]');
    const menuBtn = $('[data-menu-open]');
    menuBtn?.addEventListener('click', () => {
        toggleLayer(menu, true, $('[data-menu-close]'));
        menuBtn.setAttribute('aria-expanded', 'true');
    });
    $('[data-menu-close]')?.addEventListener('click', () => closeMenu());
    menu?.addEventListener('click', (e) => { if (e.target === menu) closeMenu(); });
    function closeMenu() {
        toggleLayer(menu, false);
        menuBtn?.setAttribute('aria-expanded', 'false');
        menuBtn?.focus();
    }

    // Search overlay
    const search = $('[data-search]');
    $$('[data-search-open]').forEach((btn) => btn.addEventListener('click', () => toggleLayer(search, true, $('input', search))));
    $('[data-search-close]')?.addEventListener('click', () => toggleLayer(search, false));
    search?.addEventListener('click', (e) => { if (e.target === search) toggleLayer(search, false); });

    document.addEventListener('keydown', (e) => {
        if (e.key !== 'Escape') return;
        if (search && !search.hidden) toggleLayer(search, false);
        if (menu && !menu.hidden) closeMenu();
        $$('.has-children.open').forEach((li) => li.classList.remove('open'));
    });

    // Drop-down menus (click / keyboard; hover is handled in CSS)
    $$('[data-dropdown]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const li = btn.closest('.has-children');
            const open = !li.classList.contains('open');
            li.classList.toggle('open', open);
            btn.setAttribute('aria-expanded', String(open));
        });
    });
    document.addEventListener('click', (e) => {
        $$('.has-children.open').forEach((li) => { if (!li.contains(e.target)) li.classList.remove('open'); });
    });

    // Header shadow once the page scrolls
    const header = $('[data-header]');
    const onScroll = () => header?.classList.toggle('is-scrolled', window.scrollY > 8);
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });

    // ---- Motion (the <head> adds .motion unless the visitor prefers reduced motion) ----
    const motion = document.documentElement.classList.contains('motion') && 'IntersectionObserver' in window;

    // Count numbers up from 0 when they come into view: <span data-count="250">
    const countUp = (el) => {
        const target = Number(el.dataset.count);
        if (!motion || !target) return;
        const start = performance.now();
        const step = (now) => {
            const t = Math.min(1, (now - start) / 1100);
            el.textContent = Math.round(target * (1 - Math.pow(1 - t, 3))).toLocaleString();
            if (t < 1) requestAnimationFrame(step);
        };
        el.textContent = '0';
        requestAnimationFrame(step);
    };

    // Fade blocks up as they scroll into view. Only blocks still below the fold
    // are hidden first, so nothing on screen flickers at load.
    const revealSelector = [
        '.section-head', '.grid > *', '.split-copy', '.split-media', '.partners li', '.partners-title',
        '.feature-group', '.cur-module', '.contact-card', '.form-card', '.benefits .checklist li',
        '.quiz-intro', '.course-dash > *', '.featured-image', '.lesson-files', '.empty-box',
        '.cred-grid li', '.who-exp', '.who-story', '.offer-grid li',
    ].join(',');

    if (motion) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                const el = entry.target;
                observer.unobserve(el);
                if (el.dataset.count !== undefined) { countUp(el); return; }
                el.classList.add('is-in');
                // Hand transitions back to the element (card hovers etc.) once revealed.
                const delay = parseInt(el.style.getPropertyValue('--reveal-delay'), 10) || 0;
                setTimeout(() => el.classList.remove('reveal', 'is-in'), 800 + delay);
            });
        }, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });

        $$(revealSelector).forEach((el) => {
            if (el.getBoundingClientRect().top < window.innerHeight * 0.92) return;
            const siblings = Array.from(el.parentElement.children).filter((s) => s.matches(revealSelector));
            el.style.setProperty('--reveal-delay', Math.min(siblings.indexOf(el), 5) * 80 + 'ms');
            el.classList.add('reveal');
            observer.observe(el);
        });
        $$('[data-count]').forEach((el) => observer.observe(el));
    }

    // Reading progress bar on lessons and articles
    const bar = $('[data-read-progress] span');
    if (bar) {
        const target = $('[data-read-target]') || $('.player-main') || document.body;
        const update = () => {
            const box = target.getBoundingClientRect();
            const total = box.height - window.innerHeight;
            const done = total > 0 ? Math.min(1, Math.max(0, -box.top / total)) : 1;
            bar.style.transform = `scaleX(${done})`;
        };
        update();
        window.addEventListener('scroll', update, { passive: true });
        window.addEventListener('resize', update);
    }

    // "Lesson complete" toast slides away after a few seconds
    const toast = $('.learn-toast');
    if (toast) {
        setTimeout(() => { toast.classList.add('is-leaving'); setTimeout(() => toast.remove(), 450); }, 4500);
    }

    // Completing a lesson: the button turns green while the next page loads
    $$('.lesson-complete').forEach((form) => form.addEventListener('submit', (e) => {
        const button = e.submitter;
        if (button && button.name !== 'undo') {
            button.classList.add('is-completing');
            button.textContent = '✓ ' + button.textContent.replace(/^✓\s*/, '').replace(/\s*→$/, '');
        }
    }));

    // Lesson screenshots open full size on click; click again or press Esc to close
    const shots = $$('.lesson-body img').filter((img) => !img.closest('a'));
    if (shots.length) {
        const box = document.createElement('div');
        box.className = 'shot-zoom';
        box.hidden = true;
        box.innerHTML = '<img alt="">';
        document.body.appendChild(box);
        const close = () => { box.hidden = true; document.body.classList.remove('is-zoomed'); };
        shots.forEach((img) => img.addEventListener('click', () => {
            box.firstChild.src = img.currentSrc || img.src;
            box.hidden = false;
            document.body.classList.add('is-zoomed');
        }));
        box.addEventListener('click', close);
        document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !box.hidden) close(); });
    }

    // Website statistics (Admin > Visitors): say the page was opened, then how
    // long it was actually on screen when the visitor leaves or switches tab.
    const track = document.documentElement.dataset.track;
    if (track) {
        const form = (data) => {
            const body = new FormData();
            Object.entries(data).forEach(([key, value]) => body.append(key, value ?? ''));
            return body;
        };
        let viewId = null;
        let shownMs = 0;
        let shownSince = document.visibilityState === 'visible' ? Date.now() : null;
        const utm = new URLSearchParams(location.search).get('utm_source');
        fetch(track, {
            method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json' },
            body: form({ path: location.pathname, title: document.title, referrer: document.referrer, utm }),
        }).then((r) => (r.status === 200 ? r.json() : null)).then((json) => { viewId = json?.id ?? null; }).catch(() => {});

        const report = () => {
            if (shownSince) { shownMs += Date.now() - shownSince; shownSince = null; }
            if (viewId && navigator.sendBeacon) {
                navigator.sendBeacon(document.documentElement.dataset.trackLeave, form({ id: viewId, seconds: Math.round(shownMs / 1000) }));
            }
        };
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'hidden') report(); else shownSince = Date.now();
        });
        window.addEventListener('pagehide', report);
    }
})();
