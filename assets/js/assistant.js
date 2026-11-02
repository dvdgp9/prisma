/* Shared assistant. Page state stays on the page; private drafts are scoped to user/company. */
(() => {
    'use strict';
    const panel = document.getElementById('prisma-assistant');
    if (!panel) return;
    const $ = id => document.getElementById(id);
    const input = $('assistant-input');
    const messages = $('assistant-messages');
    const trigger = $('assistant-nav-btn');
    const identity = `${panel.dataset.userId}:${panel.dataset.companyId}`;
    const prefix = `prisma_assistant:${identity}:`;
    const mobile = matchMedia('(max-width: 767px)');
    const state = { open: false, conversation: null, messages: [], cursor: null, historyCursor: null, generation: 0, busy: false, loading: false, pending: false, fixed: false, context: null, label: 'Mi trabajo', page: null, contextEpoch: 0, stopped: false, poll: null, returnFocus: null };
    const baseContext = () => ({ version: 1, scope: 'mine', entity_id: null, filters: {}, timezone: Intl.DateTimeFormat().resolvedOptions().timeZone || 'Europe/Madrid', fixed: false });
    function read(key, fallback) { try { return JSON.parse(localStorage.getItem(prefix + key)) ?? fallback; } catch { return fallback; } }
    function write(key, value) { if (state.stopped) return; try { localStorage.setItem(prefix + key, JSON.stringify(value)); } catch { $('assistant-save-state').textContent = 'Este navegador no permite conservar el borrador.'; } }
    function clearPrivate() {
        try { for (const key of Object.keys(localStorage)) if (key.startsWith('prisma_assistant:')) localStorage.removeItem(key); } catch { /* Private browsing */ }
    }
    try {
        if (localStorage.getItem('prisma_assistant_actor') !== identity) clearPrivate();
        localStorage.setItem('prisma_assistant_actor', identity);
    } catch { /* The panel still works without storage. */ }
    function draftKey() { return `draft:${state.conversation?.id || 'new'}`; }
    function saveDraft() { write(draftKey(), input.value); }
    function saveUI() { write('ui', { open: state.open, id: state.conversation?.id || null, fixed: state.fixed, context: state.context }); }
    function restoreDraft() { input.value = read(draftKey(), ''); updateSend(); }
    function element(tag, className, text) { const el = document.createElement(tag); if (className) el.className = className; if (text !== undefined) el.textContent = text; return el; }
    function textButton(text, action) { const b = element('button', 'assistant-text-button', text); b.type = 'button'; b.addEventListener('click', action); return b; }
    function error(message, retry) {
        const box = $('assistant-error'); box.replaceChildren(); box.hidden = !message;
        if (!message) return;
        box.append(document.createTextNode(message));
        if (retry) box.append(textButton('Reintentar', retry));
    }
    async function api(path, options = {}) {
        if (state.stopped) throw new Error('Vuelve a entrar en Prisma para continuar.');
        const response = await fetch(path, { credentials: 'same-origin', cache: 'no-store', ...options, headers: { 'Content-Type': 'application/json', 'X-Prisma-CSRF': panel.dataset.csrf, ...options.headers } });
        let body;
        try { body = await response.json(); } catch { throw new Error('No se pudo leer la respuesta. Comprueba la conexión.'); }
        if (response.status === 401) { clearPrivate(); state.stopped = true; throw new Error('Tu sesión ha caducado. Vuelve a entrar en Prisma.'); }
        if (state.stopped) throw new Error('Vuelve a entrar en Prisma para continuar.');
        if (!response.ok || !body.success) { const e = new Error(body.error || 'No se pudo completar la operación.'); e.status = response.status; throw e; }
        return body.data;
    }
    function pageContext() {
        const c = baseContext(); let label = 'Mi trabajo';
        if ($('edit-request-modal')?.classList.contains('active') && Number($('edit-request-id')?.value)) {
            c.scope = 'request'; c.entity_id = Number($('edit-request-id').value); label = 'Mejora · ' + ($('edit-request-title')?.value || `#${c.entity_id}`);
        } else if ($('task-modal')?.classList.contains('active') && typeof currentTaskId !== 'undefined' && currentTaskId) {
            c.scope = 'task'; c.entity_id = Number(currentTaskId); label = 'Tarea abierta';
        } else if (typeof notesState !== 'undefined' && notesState.current?.id && ['read', 'edit'].includes(notesState.mode)) {
            c.scope = 'note'; c.entity_id = Number(notesState.current.id); label = 'Nota · ' + notesState.current.title;
        } else if (typeof currentView !== 'undefined' && currentView === 'app' && currentAppId) {
            c.scope = 'app'; c.entity_id = Number(currentAppId); label = 'Aplicación · ' + ($('page-title')?.textContent || '');
        } else if (typeof myWork !== 'undefined' && myWork.scope === 'all' && typeof currentView !== 'undefined' && currentView === 'home') {
            c.scope = 'team'; label = 'Trabajo del equipo';
            if (myWork.person && myWork.person !== 'all') c.filters.person = myWork.person;
        }
        return { context: c, label };
    }
    function contextKey(c) { return JSON.stringify([c?.scope, c?.entity_id, Object.entries(c?.filters || {}).sort(([a], [b]) => a.localeCompare(b))]); }
    function renderContext() {
        const select = $('assistant-context-select'); select.replaceChildren();
        const choices = [{ context: baseContext(), label: 'Mi trabajo' }];
        if (['programador', 'admin', 'superadmin'].includes(panel.dataset.role)) choices.push({ context: { ...baseContext(), scope: 'team' }, label: 'Trabajo del equipo' });
        for (const item of [state.context ? { context: state.context, label: state.label } : null, state.page]) {
            if (item && !choices.some(x => contextKey(x.context) === contextKey(item.context))) choices.push(item);
        }
        choices.forEach((item, i) => { const opt = new Option(item.label, String(i)); opt.selected = contextKey(item.context) === contextKey(state.context); select.add(opt); });
        select._choices = choices;
        $('assistant-pin').setAttribute('aria-pressed', String(state.fixed));
        $('assistant-pin').querySelector('span').textContent = state.fixed ? 'Fijado' : 'Fijar';
        $('assistant-context-caption').textContent = state.fixed ? 'Este contexto se mantiene mientras navegas.' : 'Sigue la pantalla que estás viendo.';
    }
    async function setContext(info, validate = true) {
        const epoch = ++state.contextEpoch;
        state.context = { ...info.context, fixed: state.fixed }; state.label = info.label;
        renderContext(); saveUI();
        if (!validate || !state.open) return;
        try {
            const data = await api('/api/ai-conversations.php?action=context&context=' + encodeURIComponent(JSON.stringify(state.context)));
            if (epoch !== state.contextEpoch || state.stopped) return;
            state.context = data.context; state.label = data.label; renderContext(); saveUI();
        } catch (e) { if (epoch === state.contextEpoch) error(e.message); }
    }
    function syncPage() {
        state.page = pageContext();
        if (!state.fixed && contextKey(state.context) !== contextKey(state.page.context)) setContext(state.page);
        else renderContext();
    }
    function updateSend() {
        $('assistant-send').disabled = state.busy || state.loading || state.pending || state.stopped || !input.value.trim();
        $('assistant-composer').setAttribute('aria-busy', String(state.busy || state.loading || state.pending));
        input.disabled = state.loading || state.stopped;
    }
    function trustedReference(ref) {
        return ref && /^(request|task|note):[1-9][0-9]*$/.test(ref.key) && /^\/(index\.php\?open_request=|tasks\.php\?task=|notes\.php\?id=)[1-9][0-9]*$/.test(ref.url);
    }
    function messageText(message) {
        const paragraph = element('p', 'assistant-message-text');
        const refs = new Map((message.metadata?.references || []).filter(trustedReference).map(ref => [ref.key, ref]));
        const pattern = /\[\[((?:request|task|note):[1-9][0-9]*)\]\]/g;
        let end = 0;
        for (const match of message.content.matchAll(pattern)) {
            paragraph.append(document.createTextNode(message.content.slice(end, match.index)));
            const ref = refs.get(match[1]);
            if (ref) {
                const link = element('a', 'assistant-inline-reference', `${{ request: 'Mejora', task: 'Tarea', note: 'Nota' }[ref.type]} #${ref.id}`);
                link.href = ref.url; link.title = ref.title; paragraph.append(link);
            } else paragraph.append(document.createTextNode('[referencia no disponible]'));
            end = match.index + match[0].length;
        }
        paragraph.append(document.createTextNode(message.content.slice(end)));
        return paragraph;
    }
    function appendEvidence(article, metadata) {
        if (!metadata) return;
        const refs = (metadata.references || []).filter(trustedReference);
        if (refs.length) {
            const links = element('div', 'assistant-references'); links.setAttribute('aria-label', 'Registros de esta respuesta');
            for (const ref of refs) {
                const link = element('a', '', `${{ request: 'Mejora', task: 'Tarea', note: 'Nota' }[ref.type]} #${ref.id} · ${ref.title}`);
                link.href = ref.url; links.append(link);
            }
            article.append(links);
        }
        if (metadata.coverage?.length) {
            const count = metadata.coverage.reduce((sum, page) => sum + page.reviewed, 0);
            const hasMore = metadata.coverage.some(page => page.has_more);
            const laterPage = metadata.coverage.some(page => page.offset > 0);
            const suffix = hasMore ? ' · Consulta parcial. Puedes afinarla o pedir los siguientes resultados.' : metadata.partial ? (laterPage ? ' · Consulta acotada a esta página.' : ' · Parte del contenido se ha recortado.') : ' en esta consulta.';
            const text = `${count} ${count === 1 ? 'registro revisado' : 'registros revisados'}` + suffix;
            article.append(element('p', 'assistant-coverage', text));
        }
    }
    function renderMessages(scroll = true) {
        messages.replaceChildren();
        if (state.cursor) messages.append(textButton('Ver mensajes anteriores', olderMessages));
        if (!state.messages.length) {
            const empty = element('div', 'assistant-empty');
            empty.append(element('span', 'assistant-eyebrow', 'TU ESPACIO DE TRABAJO'), element('h3', '', 'Dale forma a tu próximo paso.'), element('p', '', 'Pregunta por tu trabajo, consulta una mejora o comparte tus apuntes. Las respuestas enlazan los registros consultados.'));
            const contextual = { request: ['Resumir esta mejora', 'Resume esta mejora, sus comentarios y los próximos pasos.'], task: ['Revisar esta tarea', 'Resume esta tarea y propón el siguiente paso.'], note: ['Resumir esta nota', 'Resume esta nota y separa las acciones pendientes.'], app: ['Ver pendientes de esta app', '¿Qué tenemos pendiente en esta aplicación?'], team: ['Revisar el trabajo del equipo', 'Resume los pendientes del equipo y destaca qué necesita atención.'], mine: ['Qué necesita mi atención', '¿Qué necesita mi atención hoy?'] }[state.context?.scope || 'mine'];
            for (const [label, prompt] of [contextual, ['Ordenar mis apuntes', 'Ayúdame a ordenar estos apuntes y separar las acciones pendientes:\n\n']]) {
                const b = element('button', 'assistant-suggestion', label); b.type = 'button'; b.append(element('i', 'iconoir-arrow-up-right')); b.addEventListener('click', () => { input.value = prompt; saveDraft(); updateSend(); input.focus(); }); empty.append(b);
            }
            const legacy = element('a', 'assistant-text-button', 'Crear mejoras y tareas desde apuntes'); legacy.href = '/ai-inbox.php'; empty.append(legacy);
        messages.append(empty);
        }
        for (const message of state.messages) {
            const article = element('article', `assistant-message assistant-message--${message.role}`);
            const label = element('div', 'assistant-message-label', message.role === 'user' ? 'Tú' : 'Prisma');
            if (message.role === 'assistant') label.prepend(element('i', 'iconoir-sparks'));
            article.append(label);
            if (message.state === 'redacted') article.append(element('p', 'assistant-message-state', 'Este mensaje ya no está disponible porque cambió el acceso a su contexto.'));
            else {
                article.append(messageText(message));
                appendEvidence(article, message.metadata);
                if (message.role === 'user') article.append(element('p', 'assistant-message-context', contextLabel(message.context)));
                if (message.state === 'pending') article.append(element('p', 'assistant-message-state', 'Preparando la respuesta… Puedes seguir navegando.'));
                if (message.state === 'failed') {
                    article.append(element('p', 'assistant-message-state', 'La respuesta no se completó.'));
                    // Retry only the last turn; older failed turns are not reordered into history.
                    if (message.id === state.messages.at(-1)?.id) article.append(textButton('Reintentar este mensaje', () => send(message)));
                }
            }
            messages.append(article);
        }
        if (state.pending || state.busy) {
            const skeleton = element('div', 'assistant-loading'); skeleton.setAttribute('aria-label', 'Preparando respuesta');
            for (let i = 0; i < 3; i++) skeleton.append(element('span')); messages.append(skeleton);
        }
        if (scroll) messages.scrollTop = messages.scrollHeight;
        const conversation = state.conversation;
        $('assistant-conversation-bar').hidden = !conversation;
        $('assistant-conversation-title').textContent = conversation?.title || '';
        updateSend();
    }
    function contextLabel(context) {
        const labels = { mine: 'Mi trabajo', team: 'Trabajo del equipo', app: 'Aplicación', request: 'Mejora', task: 'Tarea', note: 'Nota' };
        return (labels[context?.scope] || 'Mi trabajo') + (context?.entity_id ? ` #${context.entity_id}` : '');
    }
    function applyDetail(data, scroll = true) {
        state.conversation = data.conversation; state.messages = data.messages; state.cursor = data.next_cursor;
        if (data.messages.at(-1)?.state === 'redacted') {
            state.fixed = false; state.context = baseContext(); state.label = 'Mi trabajo'; ++state.contextEpoch;
            write(`attempt:${data.conversation.id}`, null); renderContext();
        }
        state.pending = data.messages.some(m => m.state === 'pending');
        renderMessages(scroll); saveUI(); pollPending();
    }
    function pollPending() {
        clearTimeout(state.poll);
        if (!state.pending || !state.open || !state.conversation || state.busy) return;
        const id = state.conversation.id, epoch = state.generation;
        state.poll = setTimeout(async () => {
            try { const data = await api(`/api/ai-conversations.php?id=${id}`); if (epoch === state.generation && state.conversation?.id === id) applyDetail(data, false); }
            catch (e) { error(e.message, () => loadConversation(id)); }
        }, 2500);
    }
    async function loadConversation(id, restoreContext = false) {
        saveDraft(); const epoch = ++state.generation; clearTimeout(state.poll); state.busy = false; state.loading = true; updateSend();
        $('assistant-manage').hidden = true; error('');
        messages.replaceChildren(element('p', 'assistant-message-state', 'Abriendo conversación…'));
        try {
            const data = await api(`/api/ai-conversations.php?id=${id}`);
            if (epoch !== state.generation) return;
            state.loading = false; applyDetail(data); restoreDraft();
            if (restoreContext && state.fixed) await setContext({ context: data.conversation.context, label: data.conversation.context_label });
        } catch (e) {
            if (epoch !== state.generation) return;
            if (e.status === 404) { state.conversation = null; state.messages = []; state.pending = false; state.cursor = null; restoreDraft(); renderMessages(); saveUI(); }
            state.loading = false; updateSend(); error(e.message, e.status === 404 ? null : () => loadConversation(id));
        }
    }
    async function olderMessages() {
        if (!state.cursor || !state.conversation) return;
        const epoch = state.generation, id = state.conversation.id, cursor = state.cursor;
        try {
            const data = await api(`/api/ai-conversations.php?id=${id}&before=${cursor}`);
            if (epoch !== state.generation) return;
            // A redaction can happen between pages. Reload rather than combining old private text.
            if (data.conversation.revision !== state.conversation.revision || data.messages.some(m => m.state === 'redacted')) { await loadConversation(id); return; }
            state.messages = [...data.messages, ...state.messages]; state.cursor = data.next_cursor; renderMessages(false);
        } catch (e) { error(e.message, olderMessages); }
    }
    async function showHistory(append = false) {
        saveDraft(); $('assistant-history').hidden = false; $('assistant-chat').hidden = true; $('assistant-composer').hidden = true;
        panel.querySelector('[data-assistant-action="history"]').setAttribute('aria-expanded', 'true');
        const list = $('assistant-history-list');
        if (!append) { list.replaceChildren(element('p', 'assistant-message-state', 'Cargando conversaciones…')); state.historyCursor = null; }
        try {
            const data = await api('/api/ai-conversations.php' + (append && state.historyCursor ? `?before=${state.historyCursor}` : ''));
            if (!append) list.replaceChildren();
            if (!data.items.length && !append) list.append(element('p', 'assistant-message-state', 'Tus conversaciones aparecerán aquí cuando empieces una.'));
            for (const conversation of data.items) {
                const button = element('button', 'assistant-history-item'); button.type = 'button';
                button.append(element('strong', '', conversation.title), element('span', '', conversation.context_label));
                button.addEventListener('click', () => { hideHistory(); loadConversation(conversation.id, true); }); list.append(button);
            }
            state.historyCursor = data.next_cursor; $('assistant-history-more').hidden = !data.next_cursor;
        } catch (e) { list.replaceChildren(); error(e.message, () => showHistory()); }
    }
    function hideHistory() { $('assistant-history').hidden = true; $('assistant-chat').hidden = false; $('assistant-composer').hidden = false; panel.querySelector('[data-assistant-action="history"]').setAttribute('aria-expanded', 'false'); }
    function newConversation() {
        saveDraft(); ++state.generation; clearTimeout(state.poll); state.conversation = null; state.messages = []; state.cursor = null; state.pending = false; state.busy = false; state.loading = false;
        $('assistant-manage').hidden = true; hideHistory(); error(''); restoreDraft(); renderMessages(); saveUI(); input.focus();
    }
    async function send(retry = null) {
        if (state.busy || state.loading || state.pending || state.stopped) return;
        const content = retry ? retry.content : input.value.trim(); if (!content) return;
        const epoch = state.generation; let target = state.conversation?.id;
        const previous = target ? read(`attempt:${target}`, null) : null;
        const key = retry?.client_key || (previous?.content === content ? previous.key : crypto.randomUUID());
        const context = retry?.context || (previous?.content === content ? previous.context : { ...state.context, fixed: state.fixed });
        state.busy = true; error(''); renderMessages(); saveDraft();
        try {
            if (!target) {
                const conversation = await api('/api/ai-conversations.php', { method: 'POST', body: JSON.stringify({ context }) });
                if (epoch !== state.generation) return;
                state.conversation = conversation; target = conversation.id;
                write(`draft:${target}`, input.value); write('draft:new', ''); saveUI();
            }
            write(`attempt:${target}`, { key, content, context });
            const data = await api('/api/ai-messages.php', { method: 'POST', body: JSON.stringify({ conversation_id: target, content, client_key: key, context, revision: state.conversation.revision }) });
            if (epoch !== state.generation || state.conversation?.id !== target) return;
            state.busy = false; write(`attempt:${target}`, null); applyDetail(data);
            if (!retry && input.value.trim() === content) { input.value = ''; saveDraft(); } updateSend();
        } catch (e) {
            if (epoch !== state.generation) return;
            state.busy = false;
            // If the response was lost, read the stored turn before offering a retry.
            if (target) {
                try { const data = await api(`/api/ai-conversations.php?id=${target}`); if (epoch !== state.generation) return; applyDetail(data); }
                catch { /* Keep original error and the local draft. */ }
            }
            const saved = state.messages.find(m => m.client_key === key);
            if (saved?.state === 'complete') { write(`attempt:${target}`, null); if (!retry && input.value.trim() === content) { input.value = ''; saveDraft(); } error(''); }
            else error(e.message, saved?.state === 'failed' ? () => send(saved) : null);
            renderMessages();
        } finally { if (epoch === state.generation) { state.busy = false; updateSend(); pollPending(); } }
    }
    async function rename(event) {
        event.preventDefault(); if (!state.conversation) return;
        const id = state.conversation.id, epoch = state.generation;
        try {
            const data = await api('/api/ai-conversations.php', { method: 'PUT', body: JSON.stringify({ id, title: $('assistant-rename').value, revision: state.conversation.revision }) });
            if (epoch !== state.generation) return;
            state.conversation = data; $('assistant-manage').hidden = true; renderMessages(false); error(''); saveUI();
        } catch (e) { error(e.message, () => loadConversation(id)); }
    }
    async function deleteConversation() {
        if (!state.conversation) return;
        const id = state.conversation.id, epoch = state.generation;
        try {
            await api('/api/ai-conversations.php', { method: 'DELETE', body: JSON.stringify({ id }) });
            if (epoch !== state.generation) return;
            input.value = ''; write(`draft:${id}`, ''); newConversation();
        } catch (e) { error(e.message); }
    }
    const inertBefore = new Map();
    function mobileMode() {
        for (const [el, value] of inertBefore) el.inert = value;
        inertBefore.clear();
        if (mobile.matches && state.open) {
            let child = panel;
            while (child.parentElement) {
                for (const sibling of child.parentElement.children) {
                    if (sibling === child || ['SCRIPT', 'LINK', 'STYLE'].includes(sibling.tagName)) continue;
                    inertBefore.set(sibling, sibling.inert); sibling.inert = true;
                }
                child = child.parentElement; if (child === document.body) break;
            }
        }
        if (mobile.matches && state.open) { panel.setAttribute('role', 'dialog'); panel.setAttribute('aria-modal', 'true'); }
        else { panel.setAttribute('role', 'complementary'); panel.removeAttribute('aria-modal'); }
    }
    function close() { saveDraft(); state.open = false; panel.hidden = true; document.body.classList.remove('assistant-open'); mobileMode(); trigger?.setAttribute('aria-expanded', 'false'); clearTimeout(state.poll); saveUI(); state.returnFocus?.focus?.({ preventScroll: true }); }
    async function open() {
        state.returnFocus = document.activeElement; state.open = true; panel.hidden = false; document.body.classList.add('assistant-open'); trigger?.setAttribute('aria-expanded', 'true');
        // Close the notification overlay so two panels cannot cover each other.
        if ($('inbox-panel')?.classList.contains('open') && typeof toggleInbox === 'function') toggleInbox();
        mobileMode(); syncPage(); saveUI(); input.focus();
        setContext({ context: state.context, label: state.label });
        if (state.conversation) await loadConversation(state.conversation.id); else renderMessages();
    }
    window.PrismaAssistant = Object.freeze({ open, close });
    document.querySelectorAll('[data-assistant-open]').forEach(button => button.addEventListener('click', () => open()));
    trigger?.addEventListener('click', e => { e.preventDefault(); state.open ? close() : open(); });
    panel.addEventListener('click', e => {
        const action = e.target.closest('[data-assistant-action]')?.dataset.assistantAction;
        if (action === 'close') close();
        if (action === 'new') newConversation();
        if (action === 'history') $('assistant-history').hidden ? showHistory() : hideHistory();
        if (action === 'manage') { $('assistant-manage').hidden = !$('assistant-manage').hidden; $('assistant-rename').value = state.conversation?.title || ''; $('assistant-delete-confirm').hidden = true; if (!$('assistant-manage').hidden) $('assistant-rename').focus(); }
        if (action === 'delete') $('assistant-delete-confirm').hidden = false;
        if (action === 'confirm-delete') deleteConversation();
    });
    $('assistant-manage').addEventListener('submit', rename);
    $('assistant-history-more').addEventListener('click', () => showHistory(true));
    $('assistant-composer').addEventListener('submit', e => { e.preventDefault(); send(); });
    panel.addEventListener('keydown', e => e.stopPropagation());
    input.addEventListener('input', () => { saveDraft(); updateSend(); });
    input.addEventListener('keydown', e => { if (e.key === 'Enter' && (e.ctrlKey || e.metaKey) && !e.isComposing) { e.preventDefault(); send(); } });
    $('assistant-pin').addEventListener('click', () => { state.fixed = !state.fixed; if (!state.fixed) syncPage(); else setContext({ context: state.context, label: state.label }); renderContext(); saveUI(); });
    $('assistant-context-select').addEventListener('change', e => { const choice = e.target._choices[Number(e.target.value)]; if (choice) { state.fixed = true; setContext(choice); } });
    // Capture shortcuts so Escape inside the assistant doesn't close an underlying editor.
    document.addEventListener('keydown', e => {
        if (!state.open) return;
        if (e.key === 'Escape' && (panel.contains(e.target) || mobile.matches)) { e.preventDefault(); e.stopImmediatePropagation(); close(); return; }
        if (e.key === 'Tab' && mobile.matches) {
            const focusable = [...panel.querySelectorAll('button, input, textarea, select, a[href]')].filter(el => !el.disabled && el.getClientRects().length);
            const first = focusable[0], last = focusable.at(-1);
            if (e.shiftKey && (document.activeElement === first || !panel.contains(document.activeElement))) { e.preventDefault(); last?.focus(); }
            else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first?.focus(); }
        }
    }, true);
    document.addEventListener('focusin', e => { if (state.open && mobile.matches && !panel.contains(e.target)) input.focus(); });
    document.addEventListener('click', e => {
        if (e.target.closest('a[href="/logout.php"]')) { saveDraft(); clearPrivate(); try { localStorage.setItem('prisma_assistant_logout', String(Date.now())); } catch { /* no storage */ } state.stopped = true; }
        if (!panel.contains(e.target)) setTimeout(syncPage, 0);
    });
    document.addEventListener('change', e => { if (!panel.contains(e.target)) setTimeout(syncPage, 0); });
    window.addEventListener('pagehide', () => { saveDraft(); saveUI(); });
    window.addEventListener('pageshow', e => { if (e.persisted && state.open && state.conversation) loadConversation(state.conversation.id); });
    window.addEventListener('online', () => { if (state.open && state.conversation) loadConversation(state.conversation.id); });
    window.addEventListener('storage', e => {
        if (e.key === 'prisma_assistant_logout' || (e.key === 'prisma_assistant_actor' && e.newValue !== identity)) {
            clearPrivate(); ++state.generation; state.stopped = true; state.messages = []; state.conversation = null; input.value = ''; close(); messages.replaceChildren();
        }
    });
    mobile.addEventListener('change', mobileMode);
    const observer = new MutationObserver(() => syncPage());
    for (const id of ['page-title', 'my-work-title', 'note-pane']) if ($(id)) observer.observe($(id), { childList: true, subtree: true, characterData: true });
    for (const id of ['task-modal', 'edit-request-modal']) if ($(id)) observer.observe($(id), { attributes: true, attributeFilter: ['class'] });
    const saved = read('ui', {}); state.fixed = Boolean(saved.fixed); state.context = saved.context || baseContext(); state.label = contextLabel(state.context);
    if (saved.id) state.conversation = { id: Number(saved.id) };
    restoreDraft(); syncPage(); renderMessages();
    if (saved.open) open();
})();
