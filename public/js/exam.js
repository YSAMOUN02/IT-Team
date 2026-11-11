// Quiz / exam page: progress bar and question chips that fill as you answer,
// a countdown ring with auto-submit, answers kept across reloads, A–D keys,
// a "submit?" box listing unanswered questions, and instant checking in
// practice mode (with a running score). Ordering and drag & drop questions
// become pieces to drag (mouse, finger) or move with buttons and taps.
(function () {
    const form = document.getElementById('exam');
    if (!form) return;

    const storeKey = 'exam-answers-' + form.dataset.attempt;
    const questions = Array.from(form.querySelectorAll('[data-question]'));
    const chip = (q) => form.querySelector(`[data-nav="${q.dataset.question}"]`);
    const type = (q) => q.dataset.qtype || 'single';
    const counter = form.querySelector('[data-answered] b');
    const progress = form.querySelector('.exam-progress');
    const bar = form.querySelector('[data-progress-bar]');
    const changed = (el) => el.dispatchEvent(new Event('change', { bubbles: true }));
    let submitting = false;
    let current = questions[0];

    // Answered: a choice ticked, text typed, every list picked, every blank filled, or the order touched.
    const isAnswered = (q) => {
        switch (type(q)) {
            case 'short': return q.querySelector('.q-short input').value.trim() !== '';
            case 'matching':
            case 'dragdrop': return Array.from(q.querySelectorAll('select')).every((s) => s.value !== '');
            case 'ordering': return q.dataset.touched === '1';
            default: return !!q.querySelector('input:checked');
        }
    };

    // The answer as the server wants it (also what is kept across a reload).
    const answerOf = (q) => {
        switch (type(q)) {
            case 'short': return q.querySelector('.q-short input').value;
            case 'matching':
            case 'dragdrop': {
                const out = {};
                q.querySelectorAll('select').forEach((s) => { if (s.value) out[s.name.match(/\[(\d+)\]$/)[1]] = s.value; });
                return out;
            }
            case 'ordering': return Array.from(q.querySelectorAll('.q-order-item')).map((li) => li.dataset.id);
            default: return Array.from(q.querySelectorAll('input:checked')).map((i) => i.value);
        }
    };

    const save = () => {
        const data = {};
        questions.forEach((q) => { data[q.dataset.question] = { a: answerOf(q), t: q.dataset.touched === '1' }; });
        try { localStorage.setItem(storeKey, JSON.stringify(data)); } catch (e) { /* storage unavailable */ }
    };

    const restore = () => {
        let data = {};
        try { data = JSON.parse(localStorage.getItem(storeKey) || '{}'); } catch (e) { /* ignore */ }
        Object.entries(data).forEach(([id, saved]) => {
            const q = form.querySelector(`[data-question="${id}"]`);
            if (!q || saved == null) return;
            const value = Array.isArray(saved) ? saved : saved.a; // older pages kept a plain list
            switch (type(q)) {
                case 'short': q.querySelector('.q-short input').value = value || ''; break;
                case 'matching':
                case 'dragdrop':
                    Object.entries(value || {}).forEach(([key, v]) => {
                        const select = q.querySelector(`select[name$="[${key}]"]`);
                        if (select) select.value = v;
                    });
                    break;
                case 'ordering': {
                    const list = q.querySelector('.q-order');
                    (value || []).forEach((itemId) => { const li = list.querySelector(`[data-id="${itemId}"]`); if (li) list.append(li); });
                    if (saved.t) q.dataset.touched = '1';
                    break;
                }
                default:
                    (value || []).forEach((v) => {
                        const input = q.querySelector(`input[value="${v}"]`);
                        if (input) input.checked = true;
                    });
            }
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

    // ---- Ordering: drag an item (mouse or finger) or use its arrows ----
    const renumber = (list) => list.querySelectorAll('.q-order-item').forEach((li, i) => {
        li.querySelector('.q-order-num').textContent = i + 1;
    });
    const ordered = (q) => { q.dataset.touched = '1'; renumber(q.querySelector('.q-order')); changed(q.querySelector('.q-order')); };

    form.querySelectorAll('[data-qtype="ordering"]').forEach((q) => {
        const list = q.querySelector('.q-order');
        renumber(list);
        list.addEventListener('click', (e) => {
            const move = e.target.closest('[data-move]');
            if (!move) return;
            const li = move.closest('.q-order-item');
            const other = move.dataset.move === '-1' ? li.previousElementSibling : li.nextElementSibling;
            if (!other) return;
            move.dataset.move === '-1' ? other.before(li) : other.after(li);
            ordered(q);
            move.focus();
        });
        list.addEventListener('pointerdown', (e) => {
            const li = e.target.closest('.q-order-item');
            if (!li || e.target.closest('button') || e.button > 0) return;
            e.preventDefault();
            // Listen on the page, not with pointer capture: moving the item in the list would drop the capture.
            li.classList.add('is-dragging');
            const move = (ev) => {
                const over = document.elementFromPoint(ev.clientX, ev.clientY)?.closest('.q-order-item');
                if (!over || over === li || over.parentElement !== list) return;
                const box = over.getBoundingClientRect();
                ev.clientY < box.top + box.height / 2 ? over.before(li) : over.after(li);
                renumber(list);
            };
            const drop = () => {
                li.classList.remove('is-dragging');
                document.removeEventListener('pointermove', move);
                document.removeEventListener('pointerup', drop);
                document.removeEventListener('pointercancel', drop);
                ordered(q);
            };
            document.addEventListener('pointermove', move);
            document.addEventListener('pointerup', drop);
            document.addEventListener('pointercancel', drop);
        });
    });

    // ---- Drag & drop: words into blanks. Drag a word, or tap a word then a blank; tap a full blank to empty it ----
    form.querySelectorAll('[data-qtype="dragdrop"]').forEach((q) => {
        const bank = q.querySelector('[data-bank]');
        const blanks = Array.from(q.querySelectorAll('[data-blank]'));
        let picked = null;

        blanks.forEach((blank) => {
            const slot = document.createElement('button');
            slot.type = 'button';
            slot.className = 'q-slot';
            slot.dataset.slot = blank.dataset.blank;
            blank.append(slot);
            blank.classList.add('is-enhanced');
        });

        const paint = () => {
            const used = new Set();
            blanks.forEach((blank) => {
                const select = blank.querySelector('select');
                const slot = blank.querySelector('.q-slot');
                const word = select.value ? select.selectedOptions[0].textContent : '';
                if (select.value) used.add(select.value);
                slot.textContent = word || blank.dataset.blank;
                slot.classList.toggle('is-filled', !!word);
                slot.setAttribute('aria-label', select.getAttribute('aria-label') + (word ? ': ' + word : ''));
            });
            bank.querySelectorAll('[data-chip]').forEach((c) => {
                c.hidden = used.has(c.dataset.chip);
                c.classList.toggle('is-picked', c === picked);
                c.setAttribute('aria-pressed', c === picked ? 'true' : 'false');
            });
            q.classList.toggle('is-picking', !!picked);
        };
        const place = (blank, wordId) => {
            const select = blank.querySelector('select');
            select.value = wordId || '';
            picked = null;
            paint();
            changed(select);
        };
        q.addEventListener('change', paint);

        bank.addEventListener('click', (e) => {
            const c = e.target.closest('[data-chip]');
            if (!c || e.detail > 0) return; // pointer taps and drags are handled below; this is Enter or Space
            picked = picked === c ? null : c;
            paint();
        });
        q.addEventListener('click', (e) => {
            const slot = e.target.closest('.q-slot');
            if (!slot) return;
            const blank = slot.closest('[data-blank]');
            if (picked) place(blank, picked.dataset.chip);
            else if (blank.querySelector('select').value) place(blank, '');
        });

        // Dragging a word: a copy follows the pointer; let go over a blank to put it there.
        bank.addEventListener('pointerdown', (e) => {
            const c = e.target.closest('[data-chip]');
            if (!c || e.button > 0) return;
            e.preventDefault();
            const start = { x: e.clientX, y: e.clientY };
            let ghost = null;
            const move = (ev) => {
                if (!ghost && Math.hypot(ev.clientX - start.x, ev.clientY - start.y) < 6) return;
                if (!ghost) {
                    ghost = c.cloneNode(true);
                    ghost.classList.add('q-chip-ghost');
                    document.body.append(ghost);
                    c.classList.add('is-lifted');
                }
                ghost.style.transform = `translate(${ev.clientX - ghost.offsetWidth / 2}px, ${ev.clientY - ghost.offsetHeight / 2}px)`;
                blanks.forEach((b) => b.classList.remove('is-over'));
                document.elementFromPoint(ev.clientX, ev.clientY)?.closest('[data-blank]')?.classList.add('is-over');
            };
            const end = (ev) => {
                document.removeEventListener('pointermove', move);
                document.removeEventListener('pointerup', end);
                document.removeEventListener('pointercancel', end);
                blanks.forEach((b) => b.classList.remove('is-over'));
                if (!ghost) { picked = picked === c ? null : c; paint(); return; } // a tap picks the word
                ghost.remove();
                c.classList.remove('is-lifted');
                const blank = document.elementFromPoint(ev.clientX, ev.clientY)?.closest('[data-blank]');
                if (blank && q.contains(blank)) place(blank, c.dataset.chip);
            };
            document.addEventListener('pointermove', move);
            document.addEventListener('pointerup', end);
            document.addEventListener('pointercancel', end);
        });
        paint();
    });

    restore();
    form.querySelectorAll('[data-qtype="ordering"] .q-order').forEach(renumber);
    form.querySelectorAll('[data-qtype="dragdrop"]').forEach((q) => changed(q.querySelector('select')));
    update();
    const onEdit = (e) => {
        save();
        update();
        const q = e.target.closest('[data-question]');
        if (q) {
            current = q;
            bump(e.target.closest('.q-option'));
        }
    };
    form.addEventListener('change', onEdit);
    form.addEventListener('input', (e) => { if (e.target.closest('.q-short')) onEdit(e); });

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
        q.querySelector('input:not([type=hidden]), select, button')?.focus({ preventScroll: true });
    }));

    // Keyboard: A–H (or 1–8) picks an answer in the question on screen (choice questions only).
    document.addEventListener('keydown', (e) => {
        if (e.ctrlKey || e.metaKey || e.altKey || e.target.closest('input[type=text], textarea, select, dialog[open]')) return;
        const key = e.key.toUpperCase();
        const index = /^[A-H]$/.test(key) ? key.charCodeAt(0) - 65 : (/^[1-8]$/.test(key) ? Number(key) - 1 : -1);
        const input = index >= 0 ? current?.querySelectorAll('.q-option input')[index] : null;
        if (!input) return;
        e.preventDefault();
        input.checked = input.type === 'checkbox' ? !input.checked : true;
        changed(input);
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
                const answer = answerOf(q);
                const empty = Array.isArray(answer) ? !answer.length : (typeof answer === 'string' ? !answer.trim() : !Object.keys(answer).length);
                if (empty) {
                    q.classList.remove('is-shaking');
                    void q.offsetWidth;
                    q.classList.add('is-shaking');
                    return;
                }
                const choice = !q.dataset.qtype || ['single', 'multiple', 'true_false'].includes(q.dataset.qtype);
                button.disabled = true;
                button.classList.add('is-loading');
                try {
                    const res = await fetch(form.dataset.checkUrl.replace('__Q__', q.dataset.question), {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
                        body: JSON.stringify(choice ? { chosen: answer } : { answer }),
                    });
                    const data = await res.json();
                    q.querySelectorAll('[data-option]').forEach((opt) => {
                        const id = Number(opt.dataset.option);
                        opt.classList.toggle('is-right', data.correct_ids.includes(id));
                        opt.classList.toggle('is-wrong', answer.includes(String(id)) && !data.correct_ids.includes(id));
                    });
                    // Ordering: the parts are places, in the order now on screen.
                    q.querySelectorAll('.q-order-item').forEach((li, i) => { li.dataset.part = i; });
                    Object.entries(data.parts || {}).forEach(([key, right]) => {
                        q.querySelectorAll(`[data-part="${key}"]`).forEach((part) => {
                            part.classList.toggle('is-right', right);
                            part.classList.toggle('is-wrong', !right);
                        });
                    });
                    box.replaceChildren();
                    const head = document.createElement('strong');
                    head.textContent = (data.correct ? '✓ ' : '✗ ') + (data.correct ? box.dataset.correct : box.dataset.incorrect);
                    box.append(head);
                    if (!data.correct && data.solution) {
                        const p = document.createElement('p');
                        p.textContent = box.dataset.solution + ': ' + data.solution;
                        box.append(p);
                    }
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
