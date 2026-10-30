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
})();
