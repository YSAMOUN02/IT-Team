// Quiz / exam page: progress bar and question chips that fill as you answer,
// a countdown ring with auto-submit, answers kept across reloads, A–D keys,
// a "submit?" box listing unanswered questions, and instant checking in
// practice mode (with a running score).
(function () {
    const form = document.getElementById('exam');
    if (!form) return;

    const storeKey = 'exam-answers-' + form.dataset.attempt;
    const questions = Array.from(form.querySelectorAll('[data-question]'));
    const chip = (q) => form.querySelector(`[data-nav="${q.dataset.question}"]`);
    const isAnswered = (q) => !!q.querySelector('input:checked');
    const counter = form.querySelector('[data-answered] b');
    const progress = form.querySelector('.exam-progress');
    const bar = form.querySelector('[data-progress-bar]');
    let submitting = false;
    let current = questions[0];

    const save = () => {
        const data = {};
        questions.forEach((q) => {
            data[q.dataset.question] = Array.from(q.querySelectorAll('input:checked')).map((i) => i.value);
        });
        try { localStorage.setItem(storeKey, JSON.stringify(data)); } catch (e) { /* storage unavailable */ }
    };

    const restore = () => {
        let data = {};
        try { data = JSON.parse(localStorage.getItem(storeKey) || '{}'); } catch (e) { /* ignore */ }
        Object.entries(data).forEach(([id, values]) => {
            values.forEach((v) => {
                const input = form.querySelector(`[data-question="${id}"] input[value="${v}"]`);
                if (input) input.checked = true;
            });
        });
    };

    const bump = (el) => {
        if (!el) return;
        el.classList.remove('is-bumping');
        void el.offsetWidth; // restart the animation
        el.classList.add('is-bumping');
    };

    const update = () => {
        const done = questions.filter(isAnswered).length;
        questions.forEach((q) => {
            q.classList.toggle('is-answered', isAnswered(q));
            chip(q)?.classList.toggle('is-answered', isAnswered(q));
        });
        if (counter && counter.textContent !== String(done)) {
            counter.textContent = done;
            bump(counter.parentElement);
        }
        if (bar) bar.style.width = (questions.length ? (done / questions.length) * 100 : 0) + '%';
        progress?.setAttribute('aria-valuenow', done);
        form.classList.toggle('is-complete', done === questions.length);
        return done;
    };

    restore();
    update();
    form.addEventListener('change', (e) => {
        save();
        update();
        const q = e.target.closest('[data-question]');
        if (q) {
            current = q;
            bump(e.target.closest('.q-option'));
        }
    });

    // Highlight the chip of the question on screen; keep it visible in the chip row.
    if ('IntersectionObserver' in window) {
        const seen = new Map();
        const io = new IntersectionObserver((entries) => {
            entries.forEach((entry) => seen.set(entry.target, entry.intersectionRatio));
            const top = questions.reduce((best, q) => ((seen.get(q) || 0) > (seen.get(best) || 0) ? q : best), questions[0]);
            if (!top || !(seen.get(top) > 0)) return;
            current = top;
            questions.forEach((q) => chip(q)?.classList.toggle('is-current', q === top));
            const c = chip(top);
            if (c && c.parentElement.scrollWidth > c.parentElement.clientWidth) {
                c.parentElement.scrollTo({ left: c.offsetLeft - c.parentElement.clientWidth / 2, behavior: 'smooth' });
            }
        }, { threshold: [0, 0.25, 0.5, 0.75, 1], rootMargin: '-140px 0px -35% 0px' });
        questions.forEach((q) => io.observe(q));
    }

    // Chips jump to their question below the sticky bar.
    form.querySelectorAll('[data-nav]').forEach((link) => link.addEventListener('click', (e) => {
        const q = document.getElementById(link.getAttribute('href').slice(1));
        if (!q) return;
        e.preventDefault();
        const offset = (form.querySelector('.exam-bar')?.offsetHeight || 0) + 16;
        window.scrollTo({ top: q.getBoundingClientRect().top + window.scrollY - offset, behavior: 'smooth' });
        q.classList.remove('is-flash');
        void q.offsetWidth;
        q.classList.add('is-flash');
        q.querySelector('input')?.focus({ preventScroll: true });
    }));

    // Keyboard: A–H (or 1–8) picks an answer in the question on screen.
    document.addEventListener('keydown', (e) => {
        if (e.ctrlKey || e.metaKey || e.altKey || e.target.closest('input[type=text], textarea, dialog[open]')) return;
        const key = e.key.toUpperCase();
        const index = /^[A-H]$/.test(key) ? key.charCodeAt(0) - 65 : (/^[1-8]$/.test(key) ? Number(key) - 1 : -1);
        const input = index >= 0 ? current?.querySelectorAll('.q-option input')[index] : null;
        if (!input) return;
        e.preventDefault();
        input.checked = input.type === 'checkbox' ? !input.checked : true;
        input.dispatchEvent(new Event('change', { bubbles: true }));
    });

    // Submit: show what is still unanswered first.
    const dialog = form.querySelector('[data-submit-dialog]');
    const reallySubmit = () => {
        submitting = true;
        try { localStorage.removeItem(storeKey); } catch (err) { /* ignore */ }
        const go = dialog?.querySelector('[data-dialog-confirm]');
        if (go) { go.disabled = true; go.textContent = go.dataset.busy; }
        form.querySelectorAll('button[type=submit]').forEach((b) => { b.disabled = true; });
        form.submit();
    };

    form.addEventListener('submit', (e) => {
        if (submitting) return;
        e.preventDefault();
        const done = update();
        if (!dialog || typeof dialog.showModal !== 'function') {
            if (done === questions.length || confirm(form.dataset.confirmText)) reallySubmit();
            return;
        }
        const count = dialog.querySelector('[data-dialog-count]');
        count.textContent = count.dataset.template.replace(':done', done).replace(':total', questions.length);
        dialog.querySelector('[data-dialog-bar]').style.width = (done / questions.length) * 100 + '%';
        const missing = questions.filter((q) => !isAnswered(q));
        const chips = dialog.querySelector('[data-dialog-chips]');
        chips.replaceChildren(...missing.map((q) => {
            const a = document.createElement('a');
            a.href = '#' + q.id;
            a.textContent = chip(q)?.textContent || '?';
            a.addEventListener('click', (ev) => { ev.preventDefault(); dialog.close(); chip(q)?.click(); });
            return a;
        }));
        dialog.querySelector('[data-dialog-missing]').hidden = missing.length === 0;
        dialog.querySelector('[data-dialog-all]').hidden = missing.length > 0;
        dialog.showModal();
    });
    dialog?.querySelector('[data-dialog-cancel]')?.addEventListener('click', () => dialog.close());
    dialog?.querySelector('[data-dialog-confirm]')?.addEventListener('click', reallySubmit);

    // Countdown ring; the server also enforces the deadline.
    const timer = form.querySelector('[data-timer]');
    const ring = form.querySelector('[data-timer-ring]');
    if (timer && form.dataset.seconds !== '') {
        const total = Number(form.dataset.totalSeconds) || Number(form.dataset.seconds) || 1;
        const end = Date.now() + Number(form.dataset.seconds) * 1000;
        const box = timer.closest('.exam-timer');
        const tick = () => {
            const left = Math.max(0, Math.round((end - Date.now()) / 1000));
            const m = Math.floor(left / 60);
            const s = left % 60;
            timer.textContent = `${m}:${String(s).padStart(2, '0')}`;
            ring?.style.setProperty('--left', Math.min(1, left / total));
            box.classList.toggle('is-warn', left <= total * 0.25 && left > 60);
            box.classList.toggle('is-low', left <= 60);
            if (left === 0) {
                if (dialog?.open) dialog.close();
                reallySubmit();
                return;
            }
            setTimeout(tick, 500);
        };
        tick();
    }

    // Practice mode: check one question at a time, with a running score.
    if (form.dataset.practice === '1') {
        const token = form.querySelector('input[name=_token]').value;
        const score = form.querySelector('[data-score]');
        const results = new Map();
        const showScore = () => {
            const right = [...results.values()].filter(Boolean).length;
            if (!score) return;
            score.hidden = results.size === 0;
            score.querySelector('b').textContent = right;
            bump(score);
        };

        questions.forEach((q) => {
            const button = q.querySelector('[data-check]');
            const box = q.querySelector('[data-feedback]');
            button?.addEventListener('click', async () => {
                const chosen = Array.from(q.querySelectorAll('input:checked')).map((i) => i.value);
                if (!chosen.length) {
                    q.classList.remove('is-shaking');
                    void q.offsetWidth;
                    q.classList.add('is-shaking');
                    return;
                }
                button.disabled = true;
                button.classList.add('is-loading');
                try {
                    const res = await fetch(form.dataset.checkUrl.replace('__Q__', q.dataset.question), {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
                        body: JSON.stringify({ chosen }),
                    });
                    const data = await res.json();
                    q.querySelectorAll('[data-option]').forEach((opt) => {
                        const id = Number(opt.dataset.option);
                        opt.classList.toggle('is-right', data.correct_ids.includes(id));
                        opt.classList.toggle('is-wrong', chosen.includes(String(id)) && !data.correct_ids.includes(id));
                    });
                    box.replaceChildren();
                    const head = document.createElement('strong');
                    head.textContent = (data.correct ? '✓ ' : '✗ ') + (data.correct ? box.dataset.correct : box.dataset.incorrect);
                    box.append(head);
                    if (data.explanation) {
                        const p = document.createElement('p');
                        p.textContent = data.explanation;
                        box.append(p);
                    }
                    box.className = 'q-feedback ' + (data.correct ? 'is-right' : 'is-wrong');
                    box.hidden = false;

                    q.classList.remove('is-correct', 'is-incorrect', 'is-shaking', 'is-celebrating');
                    void q.offsetWidth;
                    q.classList.add(data.correct ? 'is-correct' : 'is-incorrect', data.correct ? 'is-celebrating' : 'is-shaking');
                    chip(q)?.classList.remove('is-right', 'is-wrong');
                    chip(q)?.classList.add(data.correct ? 'is-right' : 'is-wrong');
                    results.set(q.dataset.question, data.correct);
                    showScore();
                } finally {
                    button.disabled = false;
                    button.classList.remove('is-loading');
                }
            });
        });
    }
})();
