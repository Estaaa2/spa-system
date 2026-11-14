// Customer ↔ spa chat widget (Nov 2026). Markup and styles:
// resources/views/partials/chat-widget.blade.php. Server rules:
// App\Services\Chat\ChatService.
//
// One file for both sides, read from data-mode on #lcRoot:
//   customer  landing page. Threads are keyed by spa id. The floating button
//             shows once you have a conversation; the spa window's Message
//             button ([data-open-spa-chat]) opens that spa's thread directly.
//   spa       staff pages. Threads are keyed by conversation id; the floating
//             button is always there, with the shared inbox's unread count.
//
// New messages come by polling (no WebSockets on the server): every few
// seconds while a thread is open, less often for the badge, never while the
// tab is hidden.

const root = document.getElementById('lcRoot');

if (root) {
    const mode      = root.dataset.mode;
    const isSpa     = mode === 'spa';
    const endpoints = JSON.parse(root.dataset.endpoints || '{}');
    const maxLength = parseInt(root.dataset.max || '1000', 10);

    const POLL_THREAD_MS = 4000;
    const POLL_LIST_MS   = isSpa ? 15000 : 20000;

    const el = (id) => document.getElementById(id);
    const launcher   = el('lcLauncher');
    const badge      = el('lcLauncherBadge');
    const panel      = el('lcPanel');
    const title      = el('lcTitle');
    const subtitle   = el('lcSubtitle');
    const backBtn    = el('lcBack');
    const listView   = el('lcListView');
    const list       = el('lcList');
    const listEmpty  = el('lcListEmpty');
    const threadView = el('lcThreadView');
    const messagesEl = el('lcMessages');
    const form       = el('lcForm');
    const input      = el('lcInput');
    const sendBtn    = el('lcSend');
    const errorEl    = el('lcError');
    const listTitle  = title.textContent;

    const state = {
        open: false,
        view: 'list',          // 'list' | 'thread'
        thread: null,          // { key, title, subtitle, branchId }
        lastId: 0,
        lastDay: null,
        rows: [],
        unreadBySpa: {},       // customer: spa id → unread
        sending: false,
        threadTimer: null,
        listTimer: null,
        returnFocus: null,
        spaContext: null,      // customer: the spa window's spa
    };

    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
    const url  = (name, id) => endpoints[name].replace('__ID__', encodeURIComponent(id));

    async function getJson(u) {
        const res = await fetch(u, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        return res.json();
    }

    // ── Badges ──────────────────────────────────────────────────────────────

    function setBadge(node, count) {
        if (!node) return;
        node.textContent = count > 99 ? '99+' : String(count);
        node.classList.toggle('hidden', !count);
    }

    function updateBadges(total) {
        setBadge(badge, total);
        launcher.setAttribute('aria-label', total ? `${listTitle}, ${total} unread` : listTitle);

        if (!isSpa) {
            // The button appears once you have a conversation.
            launcher.classList.toggle('hidden', state.rows.length === 0);
            updateSpaButtonBadge();
        }
    }

    function updateSpaButtonBadge() {
        const count = state.spaContext ? (state.unreadBySpa[state.spaContext.id] || 0) : 0;
        document.querySelectorAll('[data-spa-chat-badge]').forEach((b) => setBadge(b, count));
    }

    // ── Conversation list ───────────────────────────────────────────────────

    function normalise(c) {
        return isSpa
            ? { key: c.id, title: c.customer, initial: c.initial, sub: c.branch ? `From ${c.branch}` : '',
                preview: c.preview, fromMe: c.from_me, time: c.time, unread: c.unread }
            : { key: c.spa_id, title: c.spa_name, initial: (c.spa_name || 'S').charAt(0).toUpperCase(), sub: '',
                preview: c.preview, fromMe: c.from_me, time: c.time, unread: c.unread, branchId: c.branch_id };
    }

    async function loadList() {
        try {
            const data = await getJson(endpoints.list);
            state.rows = (data.conversations || []).map(normalise);
            state.unreadBySpa = {};
            if (!isSpa) state.rows.forEach((r) => { state.unreadBySpa[r.key] = r.unread; });
            updateBadges(data.unread || 0);
            if (state.open && state.view === 'list') renderList();
        } catch (e) {
            // Badge only; try again on the next tick.
        }
    }

    async function loadUnreadOnly() {
        try {
            const data = await getJson(endpoints.unread);
            updateBadges(data.unread || 0);
        } catch (e) { /* next tick */ }
    }

    function renderList() {
        list.replaceChildren();
        listEmpty.classList.toggle('hidden', state.rows.length > 0);

        state.rows.forEach((r) => {
            const li  = document.createElement('li');
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = `lc-row${r.unread ? ' is-unread' : ''}`;
            btn.addEventListener('click', () => openThread({ key: r.key, title: r.title, subtitle: r.sub, branchId: r.branchId }));

            const avatar = document.createElement('span');
            avatar.className = 'lc-avatar';
            avatar.setAttribute('aria-hidden', 'true');
            avatar.textContent = r.initial;

            const main = document.createElement('span');
            main.className = 'lc-row-main';
            const top = document.createElement('span');
            top.className = 'lc-row-top';
            const name = document.createElement('span');
            name.className = 'lc-row-name';
            name.textContent = r.title;
            const time = document.createElement('span');
            time.className = 'lc-row-time';
            time.textContent = r.time || '';
            top.append(name, time);
            main.append(top);
            if (r.sub) {
                const sub = document.createElement('span');
                sub.className = 'lc-row-sub';
                sub.style.display = 'block';
                sub.textContent = r.sub;
                main.append(sub);
            }
            const preview = document.createElement('span');
            preview.className = 'lc-row-preview';
            preview.style.display = 'block';
            preview.textContent = (r.fromMe ? 'You: ' : '') + (r.preview || '');
            main.append(preview);

            btn.append(avatar, main);
            if (r.unread) {
                const count = document.createElement('span');
                count.className = 'lc-count';
                count.textContent = r.unread > 99 ? '99+' : String(r.unread);
                count.setAttribute('aria-label', `${r.unread} unread`);
                btn.append(count);
            }

            li.append(btn);
            list.append(li);
        });
    }

    // ── Thread ──────────────────────────────────────────────────────────────

    function appendMessage(m, pending = false) {
        if (m.date !== state.lastDay) {
            state.lastDay = m.date;
            const day = document.createElement('p');
            day.className = 'lc-day';
            day.textContent = m.date;
            messagesEl.append(day);
        }

        const wrap = document.createElement('div');
        wrap.className = `lc-msg${m.mine ? ' is-mine' : ''}${pending ? ' is-pending' : ''}`;
        const bubble = document.createElement('div');
        bubble.className = 'lc-bubble';
        bubble.textContent = m.body;
        const meta = document.createElement('div');
        meta.className = 'lc-meta';
        // Customers see the spa's name on replies. Staff see which teammate
        // replied, and which branch's page the customer wrote from.
        const parts = [];
        if (isSpa ? m.mine : !m.mine) parts.push(m.from);
        if (m.branch) parts.push(`from ${m.branch}`);
        parts.push(m.time);
        meta.textContent = parts.filter(Boolean).join(' · ');
        wrap.append(bubble, meta);
        messagesEl.append(wrap);
        return wrap;
    }

    function scrollToEnd() {
        messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    function nearEnd() {
        return messagesEl.scrollHeight - messagesEl.scrollTop - messagesEl.clientHeight < 80;
    }

    async function loadThread(initial = false) {
        if (!state.thread) return;
        const key = state.thread.key;
        try {
            const data = await getJson(`${url('thread', key)}?after=${initial ? 0 : state.lastId}`);
            if (!state.thread || state.thread.key !== key) return;   // switched meanwhile

            if (initial) {
                messagesEl.replaceChildren();
                state.lastDay = null;
                if (!data.messages.length) {
                    const note = document.createElement('p');
                    note.className = 'lc-note';
                    note.id = 'lcEmptyNote';
                    note.textContent = isSpa
                        ? 'No messages yet.'
                        : `Ask ${state.thread.title} about services, prices or available times. They usually reply during opening hours.`;
                    messagesEl.append(note);
                }
            }

            const stick = initial || nearEnd();
            if (data.messages.length) el('lcEmptyNote')?.remove();
            data.messages.forEach((m) => {
                if (m.id <= state.lastId) return;
                appendMessage(m);
                state.lastId = m.id;
            });
            if (stick) scrollToEnd();

            // Reading clears the badges.
            if (data.messages.length) loadList();
        } catch (e) {
            if (initial) {
                messagesEl.replaceChildren();
                const note = document.createElement('p');
                note.className = 'lc-note';
                note.textContent = "Couldn't load the messages. Check your connection and try again.";
                messagesEl.append(note);
            }
        }
    }

    function openThread(thread) {
        state.thread = thread;
        state.lastId = 0;
        state.view = 'thread';
        if (!state.open) openPanel(false);
        showView();
        input.value = '';
        autoGrow();
        hideError();
        messagesEl.replaceChildren();
        loadThread(true);
        restartThreadTimer();
        requestAnimationFrame(() => input.focus());
    }

    async function send(e) {
        e.preventDefault();
        if (state.sending || !state.thread) return;

        const body = input.value.trim();
        if (!body) return;
        if (body.length > maxLength) {
            showError(`Keep it under ${maxLength} characters.`);
            return;
        }
        if (!isSpa && !state.thread.branchId) {
            showError('Open the spa again from its card to send a message.');
            return;
        }

        hideError();
        state.sending = true;
        sendBtn.disabled = true;
        el('lcEmptyNote')?.remove();

        // Show it straight away; replace it with the server's copy.
        const now = new Date();
        const pending = appendMessage({
            mine: true, body, from: '', branch: null,
            time: now.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' }),
            date: state.lastDay ?? now.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }),
        }, true);
        input.value = '';
        autoGrow();
        scrollToEnd();

        try {
            const payload = isSpa ? { body } : { body, branch_id: state.thread.branchId };
            const res = await fetch(url('send', state.thread.key), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
                body: JSON.stringify(payload),
            });
            const data = await res.json().catch(() => ({}));

            if (!res.ok) {
                pending.remove();
                input.value = body;
                autoGrow();
                showError(data.message || (res.status === 429
                    ? 'You\'re sending too fast. Wait a moment, then try again.'
                    : 'Your message wasn\'t sent. Please try again.'));
                return;
            }

            pending.remove();
            if (data.message.id > state.lastId) {
                appendMessage(data.message);
                state.lastId = data.message.id;
            }
            scrollToEnd();
            loadList();
        } catch (err) {
            pending.remove();
            input.value = body;
            autoGrow();
            showError('Network error. Your message wasn\'t sent.');
        } finally {
            state.sending = false;
            sendBtn.disabled = false;
            input.focus();
        }
    }

    function showError(text) {
        errorEl.textContent = text;
        errorEl.classList.remove('hidden');
    }

    function hideError() {
        errorEl.classList.add('hidden');
    }

    function autoGrow() {
        input.style.height = 'auto';
        input.style.height = `${Math.min(input.scrollHeight, 128)}px`;
    }

    // ── Panel ───────────────────────────────────────────────────────────────

    function showView() {
        const thread = state.view === 'thread';
        listView.classList.toggle('hidden', thread);
        threadView.classList.toggle('hidden', !thread);
        backBtn.classList.toggle('hidden', !thread);
        title.textContent = thread ? state.thread.title : listTitle;
        subtitle.textContent = thread ? (state.thread.subtitle || '') : '';
        subtitle.classList.toggle('hidden', !(thread && state.thread.subtitle));
        if (!thread) renderList();
    }

    function openPanel(toList = true) {
        state.returnFocus = document.activeElement;
        state.open = true;
        panel.classList.remove('hidden');
        launcher.setAttribute('aria-expanded', 'true');
        if (toList) {
            state.view = 'list';
            state.thread = null;
            stopThreadTimer();
            showView();
            loadList();
            requestAnimationFrame(() => title.focus?.());
        }
        restartListTimer();
    }

    function closePanel() {
        state.open = false;
        state.thread = null;
        panel.classList.add('hidden');
        launcher.setAttribute('aria-expanded', 'false');
        stopThreadTimer();
        restartListTimer();
        if (state.returnFocus && document.contains(state.returnFocus)) state.returnFocus.focus();
    }

    function backToList() {
        state.view = 'list';
        state.thread = null;
        stopThreadTimer();
        showView();
        loadList();
    }

    // ── Polling ─────────────────────────────────────────────────────────────

    function stopThreadTimer() {
        clearInterval(state.threadTimer);
        state.threadTimer = null;
    }

    function restartThreadTimer() {
        stopThreadTimer();
        state.threadTimer = setInterval(() => {
            if (!document.hidden && state.open && state.view === 'thread') loadThread(false);
        }, POLL_THREAD_MS);
    }

    function restartListTimer() {
        clearInterval(state.listTimer);
        state.listTimer = setInterval(() => {
            if (document.hidden) return;
            // Staff: a light count while closed, the full list while open.
            if (isSpa && !state.open) loadUnreadOnly();
            else loadList();
        }, POLL_LIST_MS);
    }

    document.addEventListener('visibilitychange', () => {
        if (document.hidden) return;
        if (state.open && state.view === 'thread') loadThread(false);
        else if (isSpa && !state.open) loadUnreadOnly();
        else loadList();
    });

    // ── Wiring ──────────────────────────────────────────────────────────────

    title.setAttribute('tabindex', '-1');
    launcher.addEventListener('click', () => (state.open ? closePanel() : openPanel(true)));
    el('lcClose').addEventListener('click', closePanel);
    backBtn.addEventListener('click', backToList);
    form.addEventListener('submit', send);
    input.addEventListener('input', autoGrow);
    input.addEventListener('keydown', (e) => {
        // Enter sends; Shift+Enter adds a line.
        if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) {
            e.preventDefault();
            form.requestSubmit();
        }
    });

    // Escape closes the chat first, before any window underneath it.
    document.addEventListener('keydown', (e) => {
        if (e.key !== 'Escape' || !state.open) return;
        e.stopImmediatePropagation();
        if (state.view === 'thread') backToList();
        else closePanel();
    }, true);

    if (!isSpa) {
        // welcome.js openSpaModal() says which spa window is open.
        document.addEventListener('levictas:spa-opened', (e) => {
            const s = e.detail || {};
            state.spaContext = { id: s.id, branchId: s.branch_id, name: s.name };
            updateSpaButtonBadge();
        });

        // The spa window's Message button.
        document.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-open-spa-chat]');
            if (!btn || !state.spaContext) return;
            openThread({
                key: state.spaContext.id,
                title: state.spaContext.name || 'Spa',
                subtitle: '',
                branchId: state.spaContext.branchId,
            });
        });
    }

    // First look, then keep the badge fresh.
    if (isSpa) loadUnreadOnly(); else loadList();
    restartListTimer();
}
