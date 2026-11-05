// TEAMIT Cambodia - chat, on the student's page and the admin's: send without reloading, and ask
// for new messages while the page is open and in view.
(function () {
    const box = document.querySelector('[data-chat]');
    if (!box) return;
    const thread = box.querySelector('[data-chat-thread]');
    const form = box.querySelector('[data-chat-form]');
    const body = form.querySelector('[data-chat-body]');
    const after = form.querySelector('[data-chat-after]');
    const error = box.querySelector('[data-chat-error]');
    const button = form.querySelector('button');
    const canHover = matchMedia('(hover: hover)').matches; // a real keyboard, not a phone's
    const QUICK = 4000;
    const SLOW = 15000;
    let delay = QUICK;
    let timer;
    let sending = false;

    const toBottom = () => { thread.scrollTop = thread.scrollHeight; };
    const nearBottom = () => thread.scrollHeight - thread.scrollTop - thread.clientHeight < 120;
    const showError = (text) => { error.textContent = text || ''; error.hidden = !text; };
    const grow = () => { body.style.height = 'auto'; body.style.height = `${Math.min(body.scrollHeight + 2, 160)}px`; };

    // A message can arrive twice (a check was on its way while one was sent): keep the first.
    const add = (data, forceDown) => {
        if (!data || !data.html) return false;
        const stay = forceDown || nearBottom();
        const holder = document.createElement('template');
        holder.innerHTML = data.html;
        let added = false;
        holder.content.querySelectorAll('[data-id]').forEach((message) => {
            if (!thread.querySelector(`[data-id="${message.dataset.id}"]`)) { thread.appendChild(message); added = true; }
        });
        box.querySelector('[data-chat-empty]')?.remove();
        after.value = Math.max(+after.value, data.last_id || 0);
        if (stay) toBottom();
        return added;
    };

    // Quick while the conversation is alive, slower while nothing happens: every check is a request to the server.
    const check = async () => {
        clearTimeout(timer);
        let got = false;
        if (!document.hidden && !sending) {
            try {
                const res = await fetch(`${box.dataset.pollUrl}?after=${after.value}`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                if (res.ok) got = add(await res.json());
            } catch (e) { /* offline: the next check tries again */ }
        }
        delay = got ? QUICK : Math.min(delay * 1.3, SLOW);
        timer = setTimeout(check, delay);
    };
    const wake = () => { delay = QUICK; check(); };

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (sending || !body.value.trim()) return;
        sending = true;
        button.disabled = true;
        showError('');
        try {
            const res = await fetch(form.action, { method: 'POST', body: new FormData(form), headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            if (res.ok) {
                add(await res.json(), true);
                body.value = '';
                body.style.height = '';
            } else if (res.status === 401 || res.status === 419) {
                location.reload(); // signed out, or the page is too old: the sign-in page takes over
            } else {
                const data = res.status === 422 ? await res.json() : null;
                showError(data?.message || form.dataset.errorText);
            }
        } catch (err) {
            showError(form.dataset.errorText);
        }
        sending = false;
        button.disabled = false;
        body.focus();
        delay = QUICK;
    });

    // Enter sends, Shift + Enter makes a new line. On phones the keyboard's Enter stays a new line.
    body.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey && !e.isComposing && canHover) { e.preventDefault(); form.requestSubmit(); }
    });
    body.addEventListener('input', () => { grow(); if (delay > QUICK) wake(); });

    // Admin: Chat and Email tabs, without losing what was typed.
    const tabs = document.querySelectorAll('[data-chat-tab]');
    const openPane = (name) => {
        tabs.forEach((tab) => tab.classList.toggle('on', tab.dataset.chatTab === name));
        document.querySelectorAll('[data-chat-pane]').forEach((pane) => { pane.hidden = pane.dataset.chatPane !== name; });
    };
    const focusPane = (name) => {
        const pane = document.querySelector(`[data-chat-pane="${name}"]`);
        pane?.querySelector('input:not([type=hidden]), textarea')?.focus({ preventScroll: true });
    };
    tabs.forEach((tab) => tab.addEventListener('click', (e) => {
        e.preventDefault();
        openPane(tab.dataset.chatTab);
        focusPane(tab.dataset.chatTab);
        history.replaceState(null, '', tab.href);
    }));

    document.addEventListener('visibilitychange', () => { if (!document.hidden) wake(); });
    if (body.value) grow();
    toBottom();
    if (canHover) focusPane(document.querySelector('[data-chat-tab].on')?.dataset.chatTab || 'chat');
    if (canHover && !tabs.length) body.focus({ preventScroll: true });
    timer = setTimeout(check, delay);
})();
