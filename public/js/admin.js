// TEAMIT Cambodia - admin interactions
(function () {
    const $ = (s, el = document) => el.querySelector(s);
    const $$ = (s, el = document) => Array.from(el.querySelectorAll(s));

    // Sidebar on small screens
    const sidebar = $('[data-sidebar]');
    const scrim = $('[data-scrim]');
    const setSidebar = (open) => {
        sidebar?.classList.toggle('open', open);
        if (scrim) scrim.hidden = !open;
    };
    $('[data-sidebar-toggle]')?.addEventListener('click', () => setSidebar(!sidebar.classList.contains('open')));
    scrim?.addEventListener('click', () => setSidebar(false));

    // User menu closes when clicking elsewhere
    document.addEventListener('click', (e) => {
        $$('details.user-menu[open]').forEach((d) => { if (!d.contains(e.target)) d.open = false; });
    });

    // Keyboard: "/" focuses search, Ctrl/Cmd+S saves the main form
    document.addEventListener('keydown', (e) => {
        const typing = /INPUT|TEXTAREA|SELECT/.test(document.activeElement?.tagName) || document.activeElement?.isContentEditable;
        if (e.key === '/' && !typing) { e.preventDefault(); $('[data-search]')?.focus(); }
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
            const form = $('form[data-main-form]');
            if (form) { e.preventDefault(); window.tinymce?.triggerSave(); form.requestSubmit(); }
        }
        if (e.key === 'Escape') setSidebar(false);
    });

    // Toasts disappear by themselves
    $$('[data-toast]').forEach((toast) => {
        const close = () => { toast.classList.add('leaving'); setTimeout(() => toast.remove(), 260); };
        $('[data-toast-close]', toast)?.addEventListener('click', close);
        setTimeout(close, 4500);
    });

    // Confirm dialog for destructive forms: <form data-confirm="Delete this course?">
    const dialog = $('[data-confirm-dialog]');
    $$('form[data-confirm]').forEach((form) => {
        form.addEventListener('submit', (e) => {
            if (form.dataset.confirmed === '1' || !dialog?.showModal) return;
            e.preventDefault();
            $('[data-confirm-text]', dialog).textContent = form.dataset.confirm;
            dialog.returnValue = '';
            dialog.showModal();
            dialog.addEventListener('close', () => {
                if (dialog.returnValue === 'ok') { form.dataset.confirmed = '1'; form.requestSubmit(); }
            }, { once: true });
        });
    });

    // Image drop zones: preview the picked / dropped file. A file the server would refuse
    // (type not in accept=".jpg,…", bigger than data-max-kb) is let go at once with a message,
    // so it does not stop the rest of the form from saving.
    $$('[data-dropzone]').forEach((zone) => {
        const field = zone.parentElement;
        const input = $('input[type=file]', zone);
        const img = $('img', zone);
        const name = field.querySelector('[data-dz-name]');
        const error = field.querySelector('[data-dz-error]');
        const saved = { src: img.getAttribute('src'), hidden: img.hidden, has: zone.classList.contains('has-image'), name: name?.textContent };
        const types = input.accept.split(',').map((t) => t.trim().toLowerCase()).filter((t) => t.startsWith('.'));
        const maxKb = +input.dataset.maxKb || 0;
        const problem = (file) => {
            const dot = file.name.lastIndexOf('.');
            if (types.length && dot > 0 && !types.includes(file.name.slice(dot).toLowerCase())) {
                // .jfif / .jpe are other names for JPG: allowed, but not worth listing.
                const listed = types.filter((t) => t !== '.jfif' && t !== '.jpe');
                return file.name + ' cannot be used here. Use ' + listed.map((t) => t.slice(1).toUpperCase()).join(', ') + ' (save iPhone HEIC photos as JPG first).';
            }
            if (maxKb && file.size > maxKb * 1024) {
                return file.name + ' is ' + (file.size / 1048576).toFixed(1) + ' MB; the limit is ' + Math.round(maxKb / 102.4) / 10 + ' MB. Choose a smaller picture.';
            }
            return '';
        };
        const show = (file) => {
            if (!file) return;
            const message = problem(file);
            field.classList.toggle('has-error', !!message);
            if (error) { error.textContent = message; error.hidden = !message; }
            if (message) {
                input.value = '';
                if (saved.src) img.src = saved.src; else img.removeAttribute('src');
                img.hidden = saved.hidden;
                zone.classList.toggle('has-image', saved.has);
                if (name) name.textContent = saved.name;
                return;
            }
            img.src = URL.createObjectURL(file);
            img.hidden = false;
            zone.classList.add('has-image');
            if (name) name.textContent = file.name + ' · ' + Math.round(file.size / 1024) + ' KB';
            const remove = zone.parentElement.querySelector('[data-dz-remove]');
            if (remove) remove.checked = false;
        };
        input.addEventListener('change', () => show(input.files[0]));
        ['dragenter', 'dragover'].forEach((t) => zone.addEventListener(t, (e) => { e.preventDefault(); zone.classList.add('drag'); }));
        ['dragleave', 'drop'].forEach((t) => zone.addEventListener(t, () => zone.classList.remove('drag')));
        zone.addEventListener('drop', (e) => {
            e.preventDefault();
            if (e.dataTransfer.files[0]) { input.files = e.dataTransfer.files; show(e.dataTransfer.files[0]); }
        });
    });

    // Other file inputs with data-max-kb (lesson video, practice files): a file that is too big is let go at once.
    $$('input[type=file][data-max-kb]').forEach((input) => {
        if (input.closest('[data-dropzone]')) return;
        const note = document.createElement('span');
        note.className = 'error-text';
        note.hidden = true;
        input.after(note);
        input.addEventListener('change', () => {
            const max = +input.dataset.maxKb * 1024;
            const big = [...input.files].find((file) => file.size > max);
            note.hidden = !big;
            if (!big) return;
            note.textContent = big.name + ' is ' + (big.size / 1048576).toFixed(1) + ' MB; the limit here is ' + Math.round(max / 104857.6) / 10 + ' MB.';
            input.value = '';
        });
    });

    // PHP throws a whole save away when its files add up to more than post_max_size: say so before sending.
    const postMax = +$('meta[name=post-max-bytes]')?.content || 0;
    $$('form[enctype="multipart/form-data"]').forEach((form) => {
        form.addEventListener('submit', (e) => {
            const total = $$('input[type=file]', form).reduce((sum, input) => sum + [...input.files].reduce((s, file) => s + file.size, 0), 0);
            if (!postMax || total <= postMax) return;
            e.preventDefault();
            e.stopImmediatePropagation();
            window.alert('The files you picked add up to ' + (total / 1048576).toFixed(1) + ' MB, but this server takes ' + Math.round(postMax / 1048576) + ' MB per save. Save with some of them first, then add the rest.');
        });
    });

    // Character counters: <textarea data-max="160">
    $$('[data-max]').forEach((el) => {
        const out = el.closest('.field')?.querySelector('[data-counter]');
        if (!out) return;
        const max = +el.dataset.max;
        const update = () => {
            const n = [...el.value].length;
            out.textContent = n + ' / ' + max;
            out.classList.toggle('over', n > max);
        };
        el.addEventListener('input', update);
        update();
    });

    // Slug preview follows the title until the slug is edited by hand
    $$('[data-slug-source]').forEach((title) => {
        const slug = $('[data-slug]');
        if (!slug) return;
        let touched = slug.value !== '';
        slug.addEventListener('input', () => { touched = slug.value !== ''; });
        title.addEventListener('input', () => {
            if (touched) return;
            slug.placeholder = title.value.toLowerCase().trim().replace(/[^\p{L}\p{M}\p{N}]+/gu, '-').replace(/^-+|-+$/g, '');
        });
    });

    // Warn before leaving a form with unsaved changes
    $$('form[data-main-form]').forEach((form) => {
        let dirty = false;
        form.addEventListener('input', () => { dirty = true; });
        form.addEventListener('change', () => { dirty = true; });
        form.addEventListener('submit', () => { dirty = false; });
        window.addEventListener('beforeunload', (e) => {
            if (dirty || window.tinymce?.activeEditor?.isDirty()) { e.preventDefault(); e.returnValue = ''; }
        });
    });

    // Column chart tooltips (bars carry data-label / data-value)
    $$('[data-chart]').forEach((chart) => {
        const tip = $('.tooltip', chart);
        const strong = $('strong', tip);
        const label = $('span', tip);
        const show = (hit) => {
            strong.textContent = hit.dataset.value;
            label.textContent = hit.dataset.label;
            const box = hit.getBoundingClientRect();
            const host = chart.getBoundingClientRect();
            tip.style.left = (box.left - host.left + box.width / 2) + 'px';
            tip.style.top = (+hit.dataset.top / +chart.dataset.height) * $('svg', chart).getBoundingClientRect().height + 'px';
            tip.hidden = false;
        };
        $$('.hit', chart).forEach((hit) => {
            hit.addEventListener('pointerenter', () => show(hit));
            hit.addEventListener('focus', () => show(hit));
            hit.addEventListener('pointerleave', () => { tip.hidden = true; });
            hit.addEventListener('blur', () => { tip.hidden = true; });
        });
    });

    // Course › Students › Give access: pick an existing student from a searchable list (or add a new
    // one), pick a plan to fill days + amount, and the button says who gets the course.
    $$('[data-grant]').forEach((form) => {
        const people = JSON.parse($('[data-grant-people]', form)?.textContent || '[]');
        const mode = $('[data-grant-mode]', form);
        const emailHidden = $('[data-grant-email]', form);
        const newName = $('[data-grant-new-name]', form);
        const newEmail = $('[data-grant-new-email]', form);
        const search = $('[data-grant-search]', form);
        const list = $('[data-grant-list]', form);
        const empty = $('[data-grant-empty]', form);
        const picker = $('[data-grant-picker]', form);
        const picked = $('[data-grant-picked]', form);
        const submitText = $('[data-grant-submit-text]', form);
        let active = 0;
        let shown = [];

        const label = () => {
            const who = mode.value === 'new' ? newName.value.trim() : (people.find((p) => p.email === emailHidden.value)?.name || '');
            submitText.textContent = who ? 'Give access to ' + who : 'Give access';
        };

        const setMode = (value) => {
            mode.value = value;
            $$('[data-grant-tab]', form).forEach((tab) => {
                tab.classList.toggle('on', tab.dataset.grantTab === value);
                tab.setAttribute('aria-selected', tab.dataset.grantTab === value ? 'true' : 'false');
            });
            $$('[data-grant-pane]', form).forEach((pane) => { pane.hidden = pane.dataset.grantPane !== value; });
            // Only the visible "email" is sent.
            emailHidden.disabled = value === 'new';
            newEmail.disabled = newName.disabled = value !== 'new';
            label();
        };

        const choose = (person) => {
            emailHidden.value = person ? person.email : '';
            picked.hidden = !person;
            picker.hidden = !!person;
            if (person) {
                $('[data-picked-initials]', picked).textContent = person.initials;
                $('[data-picked-name]', picked).textContent = person.name;
                $('[data-picked-meta]', picked).textContent = [person.email, person.phone].filter(Boolean).join(' · ');
            }
            label();
        };

        const row = (person, index) => {
            const li = document.createElement('li');
            li.setAttribute('role', 'option');
            li.className = 'picker-item' + (index === active ? ' active' : '');
            li.innerHTML = '<span class="avatar"></span><span class="picker-text"><b></b><small></small></span>';
            $('.avatar', li).textContent = person.initials;
            $('b', li).textContent = person.name;
            $('small', li).textContent = [person.email, person.phone].filter(Boolean).join(' · ');
            if (person.renew) {
                const tag = document.createElement('span');
                tag.className = 'picker-tag';
                tag.textContent = 'Had it before';
                li.appendChild(tag);
            }
            li.addEventListener('mousedown', (e) => { e.preventDefault(); choose(person); });
            return li;
        };

        const render = () => {
            const q = search.value.trim().toLowerCase();
            shown = people.filter((p) => !q || (p.name + ' ' + p.email + ' ' + p.phone).toLowerCase().includes(q)).slice(0, 50);
            active = Math.min(active, Math.max(0, shown.length - 1));
            list.replaceChildren(...shown.map(row));
            empty.hidden = shown.length > 0;
            list.hidden = shown.length === 0;
            $('[data-grant-query]', form).textContent = search.value.trim();
        };

        search.addEventListener('input', () => { active = 0; render(); });
        search.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                e.preventDefault();
                active = (active + (e.key === 'ArrowDown' ? 1 : -1) + shown.length) % Math.max(1, shown.length);
                render();
                list.children[active]?.scrollIntoView({ block: 'nearest' });
            } else if (e.key === 'Enter') {
                e.preventDefault(); // never submit half-filled
                if (shown[active]) choose(shown[active]);
            }
        });
        $('[data-grant-change]', form).addEventListener('click', () => { choose(null); search.focus(); render(); });

        // "No match": carry what was typed over to the new-student form.
        $('[data-grant-new]', form).addEventListener('click', () => {
            const typed = search.value.trim();
            setMode('new');
            if (typed.includes('@')) newEmail.value = typed; else newName.value = typed;
            (typed.includes('@') ? newName : newEmail).focus();
        });
        $$('[data-grant-tab]', form).forEach((tab) => tab.addEventListener('click', () => setMode(tab.dataset.grantTab)));
        newName.addEventListener('input', label);

        // Nothing picked yet: say so instead of letting the server answer.
        form.addEventListener('submit', (e) => {
            if (mode.value === 'existing' && !emailHidden.value) {
                e.preventDefault();
                search.focus();
                picker.classList.add('shake');
                setTimeout(() => picker.classList.remove('shake'), 500);
            }
        });

        // Coming back with an error: show what was chosen.
        if (emailHidden.value) choose(people.find((p) => p.email === emailHidden.value) || null);
        render();
        setMode(mode.value);
    });

    // Classes › Add students: search filters the list, "Tick all shown", the button counts the ticks.
    $$('[data-add-students]').forEach((box) => {
        const search = $('[data-pick-search]', box);
        if (!search) return;
        const rows = $$('[data-pick-row]', box);
        const button = $('[data-pick-submit]', box);
        const label = $('[data-pick-label]', box);
        const count = () => {
            const n = rows.filter((row) => $('input', row).checked).length;
            button.disabled = n === 0;
            label.textContent = n ? 'Add ' + n + ' student' + (n === 1 ? '' : 's') : 'Add students';
        };
        search.addEventListener('input', () => {
            const q = search.value.trim().toLowerCase();
            rows.forEach((row) => { row.hidden = q !== '' && !row.dataset.search.includes(q); });
            $('[data-pick-empty]', box).hidden = rows.some((row) => !row.hidden);
        });
        search.addEventListener('keydown', (e) => { if (e.key === 'Enter') e.preventDefault(); });
        $('[data-pick-all]', box).addEventListener('click', () => {
            const shown = rows.filter((row) => !row.hidden);
            const tick = shown.some((row) => !$('input', row).checked);
            shown.forEach((row) => { $('input', row).checked = tick; });
            count();
        });
        box.addEventListener('change', count);
        count();
    });

    // Messages › Reply: "Reply" jumps to the box, the signature's Edit opens it (preview follows
    // the typing), Ctrl/Cmd + Enter sends.
    $$('[data-reply-form]').forEach((form) => {
        const body = $('[data-reply-body]', form);
        $('[data-reply-jump]')?.addEventListener('click', (e) => {
            e.preventDefault();
            form.closest('.card').scrollIntoView({ behavior: 'smooth', block: 'start' });
            setTimeout(() => body.focus({ preventScroll: true }), 350);
        });
        const box = $('[data-signature]', form);
        const preview = $('[data-signature-preview]', box);
        const editor = $('[data-signature-editor]', box);
        const input = $('[data-signature-input]', box);
        const edit = $('[data-signature-edit]', box);
        edit.addEventListener('click', () => {
            const open = editor.hidden;
            editor.hidden = !open;
            preview.hidden = open;
            edit.lastChild.textContent = open ? ' Done' : ' Edit';
            if (open) input.focus();
        });
        input.addEventListener('input', () => { preview.textContent = input.value; });
        form.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) { e.preventDefault(); form.requestSubmit(); }
        });
        if (location.hash === '#reply' || form.querySelector('.has-error, .alert-error[role=alert]')) body.focus();
    });

    // Give access (course page, student page, Add student): plan buttons fill days + amount, a
    // Lifetime plan sends lifetime=1, typing days un-picks the plan; the plans follow the course picked.
    $$('[data-access-form]').forEach((form) => {
        const days = $('[data-days]', form);
        const lifetime = $('[data-lifetime]', form);
        const amount = $('[name=amount]', form);
        const course = $('[data-course-select]', form);
        if (!days) return;
        const unpick = () => $$('[data-plan]', form).forEach((chip) => { chip.classList.remove('on'); chip.setAttribute('aria-pressed', 'false'); });

        $$('[data-plan]', form).forEach((chip) => chip.addEventListener('click', () => {
            unpick();
            chip.classList.add('on');
            chip.setAttribute('aria-pressed', 'true');
            days.value = chip.dataset.days || '';
            lifetime.value = chip.dataset.days ? '0' : '1';
            if (amount) amount.value = chip.dataset.price;
            const method = $('[name=payment_method][value=' + (+chip.dataset.price === 0 ? 'free' : 'cash') + ']', form);
            if (method) method.checked = true;
        }));
        days.addEventListener('input', () => { lifetime.value = '0'; unpick(); });

        const showPlans = () => {
            $$('[data-plans-for]', form).forEach((group) => { group.hidden = !!course && group.dataset.plansFor !== course.value; });
            // "No course yet" (Add student): nothing to set for access or payment.
            $$('[data-needs-course]', form).forEach((step) => { step.hidden = !!course && !course.value; });
        };
        course?.addEventListener('change', () => {
            unpick();
            days.value = '';
            lifetime.value = '0';
            if (amount) amount.value = '';
            showPlans();
        });
        showPlans();
    });

    // Forms that send an email take a few seconds: say so and lock the button, so an impatient
    // second click cannot send it twice (or give a course, and its invoice, twice).
    $$('[data-reply-form], [data-access-form]').forEach((form) => {
        const button = $('button:not([type=button])', form);
        if (!button) return;
        const label = button.querySelector('[data-grant-submit-text]') || button.lastChild;
        const idle = label.textContent;
        const unlock = () => { button.disabled = false; button.classList.remove('is-busy'); label.textContent = idle; };
        form.addEventListener('submit', (e) => {
            if (e.defaultPrevented) return; // e.g. no student picked yet
            label.textContent = ' Sending…';
            button.classList.add('is-busy');
            // After this moment, so the browser still sends the button's own value.
            setTimeout(() => { button.disabled = true; });
        });
        // Back button: the browser may restore the page exactly as it was left.
        window.addEventListener('pageshow', (e) => { if (e.persisted) unlock(); });
    });
})();
