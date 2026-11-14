// Customer ↔ spa chat widget (Nov 2026). Markup and styles:
// resources/views/partials/chat-widget.blade.php. Server rules:
// App\Services\Chat\ChatService.
//
// One file for both sides, read from data-mode on #lcRoot:
//   customer  landing page. One thread per branch (Nov 15), keyed by branch
//             id. The floating button shows once you have a conversation; the
//             spa window's Message button ([data-open-spa-chat]) opens that
//             branch's thread directly. A thread shows its branch in a bar
//             with "View branch" (opens the spa window); a branch the public
//             can't see keeps its history but takes no new messages (Q7a).
//   spa       staff pages. Threads are keyed by conversation id; the floating
//             button is always there, with the shared inbox's unread count.
//             The inbox holds every branch and ignores the branch switcher;
//             rows show their branch, chips filter by branch, and a thread from
//             another branch than the one being viewed says so.
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
        unreadByBranch: {},    // customer: branch id → unread
        branches: [],          // spa: [{ id, name, label }] for the chips
        currentBranchId: null, // spa: the branch being viewed (switcher)
        filter: null,          // spa: branch id the list is filtered to, null = all
        sending: false,
        threadTimer: null,
        listTimer: null,
        returnFocus: null,
        spaContext: null,      // customer: the spa window's branch
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
        const count = state.spaContext ? (state.unreadByBranch[state.spaContext.branchId] || 0) : 0;
        document.querySelectorAll('[data-spa-chat-badge]').forEach((b) => setBadge(b, count));
    }

    // ── Conversation list ───────────────────────────────────────────────────

    function normalise(c) {
        return isSpa
            ? { key: c.id, title: c.customer, initial: c.initial, sub: c.branch_label || '',
                preview: c.preview, fromMe: c.from_me, time: c.time, unread: c.unread, branchId: c.branch_id }
            : { key: c.branch_id, title: c.spa_name, initial: (c.spa_name || 'S').charAt(0).toUpperCase(),
                sub: c.branch_label || '', preview: c.preview, fromMe: c.from_me, time: c.time,
                unread: c.unread, branchId: c.branch_id, photo: c.branch_photo || null };
    }

    async function loadList() {
        try {
            const data = await getJson(endpoints.list);
            state.rows = (data.conversations || []).map(normalise);
            state.unreadByBranch = {};
            if (!isSpa) state.rows.forEach((r) => { state.unreadByBranch[r.key] = r.unread; });
            if (isSpa) {
                state.branches = data.branches || [];
                state.currentBranchId = data.current_branch_id ?? null;
                // A filter for a branch that no longer exists falls back to all.
                if (state.filter && !state.branches.some((b) => b.id === state.filter)) state.filter = null;
            }
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

    // Branch filter chips (staff, 2+ branches). Counts are unread per branch.
    function renderChips() {
        const chips = el('lcChips');
        if (!chips) return;
        const show = state.branches.length > 1 && state.rows.length > 0;
        chips.classList.toggle('hidden', !show);
        chips.classList.toggle('flex', show);
        if (!show) return;

        const unread = {};
        let total = 0;
        state.rows.forEach((r) => {
            unread[r.branchId] = (unread[r.branchId] || 0) + (r.unread || 0);
            total += r.unread || 0;
        });

        const chip = (id, text, count) => {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'lc-chip';
            b.setAttribute('aria-pressed', String(state.filter === id));
            b.textContent = text;
            if (count) {
                const c = document.createElement('span');
                c.className = 'lc-chip-count';
                c.textContent = count > 99 ? '99+' : String(count);
                c.setAttribute('aria-label', `${count} unread`);
                b.append(c);
            }
            b.addEventListener('click', () => {
                state.filter = id;
                renderList();
            });
            return b;
        };

        chips.replaceChildren(
            chip(null, 'All', total),
            ...state.branches.map((b) => chip(b.id, b.name, unread[b.id] || 0)),
        );
    }

    function renderList() {
        list.replaceChildren();
        renderChips();
        const rows = isSpa && state.filter ? state.rows.filter((r) => r.branchId === state.filter) : state.rows;
        listEmpty.classList.toggle('hidden', rows.length > 0);
        if (isSpa && state.filter && !rows.length && state.rows.length) {
            // Filtered to a branch with no chats: say so instead of "No messages yet".
            listEmpty.classList.add('hidden');
            const li = document.createElement('li');
            li.className = 'lc-note';
            li.textContent = 'No chats with this branch yet.';
            list.append(li);
        }

        rows.forEach((r) => {
            const li  = document.createElement('li');
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = `lc-row${r.unread ? ' is-unread' : ''}`;
            btn.addEventListener('click', () => openThread({ key: r.key, title: r.title, subtitle: isSpa ? r.sub : '', branchId: r.branchId }));
            if (isSpa) btn.setAttribute('aria-label', `${r.title}, ${r.sub || 'branch unknown'}${r.unread ? `, ${r.unread} unread` : ''}`);

            let avatar = document.createElement('span');
            avatar.className = 'lc-avatar';
            avatar.setAttribute('aria-hidden', 'true');
            avatar.textContent = r.initial;
            if (r.photo) {
                // Customer: the branch's photo, falling back to the initial.
                const img = document.createElement('img');
                img.className = 'lc-avatar lc-avatar-photo';
                img.alt = '';
                img.src = r.photo;
                img.addEventListener('error', () => img.replaceWith(avatar));
                avatar = img;
            }

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
                if (isSpa && r.branchId && r.branchId === state.currentBranchId && state.branches.length > 1) {
                    const here = document.createElement('span');
                    here.className = 'lc-row-here';
                    here.textContent = 'current branch';
                    sub.append(here);
                }
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
        // Customers see the spa's name on replies; staff see which teammate
        // replied. The branch is the thread's own (header), not per message.
        const parts = [];
        if (isSpa ? m.mine : !m.mine) parts.push(m.from);
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
                if (isSpa) showBranchNotice(data); else showBranchBar(data);
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

    // Staff opened a thread with another branch than the one being viewed (Q3a).
    function showBranchNotice(data) {
        const notice = el('lcNotice');
        if (!notice) return;
        const other = data.branch_id && data.current_branch_id && data.branch_id !== data.current_branch_id;
        notice.classList.toggle('hidden', !other);
        if (!other) return;
        const viewing = state.branches.find((b) => b.id === data.current_branch_id);
        el('lcNoticeText').textContent = `This chat is with ${data.branch_label || 'another branch'}. `
            + `You're viewing ${viewing ? viewing.label : 'another branch'}; your reply still goes to this customer's chat with ${data.branch_label || 'that branch'}.`;
    }

    // Customer: the branch bar and, for a branch the public can't see, the
    // read-only state (Q7a). The server refuses those messages either way.
    function showBranchBar(data) {
        const bar = el('lcCtx');
        if (!bar || !state.thread) return;
        state.thread.public = data.branch_public !== false;
        el('lcCtxBranch').textContent = data.branch_label || data.branch_name || 'Branch';
        el('lcCtxAddress').textContent = data.branch_address || '';
        const photo = el('lcCtxPhoto');
        photo.classList.toggle('hidden', !data.branch_photo);
        if (data.branch_photo) photo.src = data.branch_photo;
        bar.classList.remove('hidden');
        setReadOnly(!state.thread.public);
    }

    const UNAVAILABLE = "This branch isn't available to the public right now, so it can't take new messages. Your chat history stays here.";

    function setReadOnly(readOnly, text = UNAVAILABLE) {
        const note = el('lcCtxNote');
        if (note) {
            note.textContent = readOnly ? text : '';
            note.classList.toggle('hidden', !readOnly);
        }
        input.disabled = readOnly;
        sendBtn.disabled = readOnly;
        input.placeholder = readOnly ? 'Messaging is unavailable for this branch' : 'Type a message…';
    }

    async function viewBranch() {
        if (!state.thread || isSpa) return;
        const btn = el('lcCtxView');
        if (state.thread.public === false) {
            const note = el('lcCtxNote');
            note?.classList.remove('hidden');
            note?.focus();
            return;
        }
        btn.disabled = true;
        try {
            const data = await getJson(url('card', state.thread.key));
            if (!data.public) {
                if (state.thread) state.thread.public = false;
                setReadOnly(true, data.message ? `${data.message} It can't take new messages; your chat history stays here.` : UNAVAILABLE);
                return;
            }
            closePanel();
            document.dispatchEvent(new CustomEvent('levictas:open-spa', { detail: data.spa }));
        } catch (e) {
            showError("Couldn't open the branch. Check your connection and try again.");
        } finally {
            btn.disabled = false;
        }
    }

    function openThread(thread) {
        el('lcNotice')?.classList.add('hidden');
        el('lcCtx')?.classList.add('hidden');
        if (!isSpa) setReadOnly(false);
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
        if (state.sending || !state.thread || input.disabled) return;

        const body = input.value.trim();
        if (!body) return;
        if (body.length > maxLength) {
            showError(`Keep it under ${maxLength} characters.`);
            return;
        }
        if (!isSpa && !state.thread.key) {
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
            const payload = { body };
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
                // The branch stopped being public since the thread opened (Q7a).
                if (!isSpa && res.status === 422 && /accepting messages/i.test(data.message || '')) {
                    if (state.thread) state.thread.public = false;
                    setReadOnly(true);
                    return;
                }
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
            sendBtn.disabled = input.disabled;
            if (!input.disabled) input.focus();
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
    el('lcCtxView')?.addEventListener('click', viewBranch);
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
            state.spaContext = { id: s.id, branchId: s.branch_id, name: s.name, branchName: s.branch_name };
            updateSpaButtonBadge();
        });

        // The spa window's Message button.
        document.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-open-spa-chat]');
            if (!btn || !state.spaContext) return;
            if (!state.spaContext.branchId) return;
            openThread({
                key: state.spaContext.branchId,
                title: state.spaContext.name || 'Spa',
                subtitle: '',   // the branch bar under the header names the branch
                branchId: state.spaContext.branchId,
            });
        });
    }

    // First look, then keep the badge fresh.
    if (isSpa) loadUnreadOnly(); else loadList();
    restartListTimer();
}
