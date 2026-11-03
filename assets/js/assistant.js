/* Shared assistant. Page state stays on the page; private drafts are scoped to user/company. */
(() => {
    'use strict';
    const panel = document.getElementById('prisma-assistant');
    if (!panel) return;
    const $ = id => document.getElementById(id);
    const input = $('assistant-input');
    const messages = $('assistant-messages');
    const trigger = $('assistant-toggle');
    const triggers = [...document.querySelectorAll('[data-assistant-toggle], [data-assistant-open]')];
    const isMac = /Mac|iPhone|iPad/.test(navigator.platform || navigator.userAgent);
    const shortcut = isMac ? '⌘ J' : 'Ctrl J';
    document.querySelectorAll('[data-assistant-shortcut]').forEach(key => { key.textContent = shortcut; });
    if (trigger) trigger.title = `Abrir el asistente en un panel lateral (${shortcut})`;
    triggers.forEach(button => { button.setAttribute('aria-controls', 'prisma-assistant'); button.setAttribute('aria-expanded', 'false'); });
    const identity = `${panel.dataset.userId}:${panel.dataset.companyId}`;
    const prefix = `prisma_assistant:${identity}:`;
    const mobile = matchMedia('(max-width: 767px)');
    const state = { open: false, conversation: null, messages: [], cursor: null, historyCursor: null, historyEpoch: 0, historyBusy: false, historyItems: [], historyConfirm: null, outgoing: null, composeMode: 'question', generation: 0, busy: false, loading: false, pending: false, busySince: 0, waitTimer: null, scopeOpen: false, fixed: false, context: null, label: 'Mi trabajo', page: null, contextEpoch: 0, stopped: false, poll: null, returnFocus: null };
    const TYPE = { request: 'Mejora', task: 'Tarea', note: 'Nota' };
    const TYPE_ICON = { request: 'iconoir-light-bulb', task: 'iconoir-task-list', note: 'iconoir-page' };
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
    function saveDraft() { write(draftKey(), input.value); write(draftKey() + ':mode', state.composeMode); }
    function saveUI() { write('ui', { open: state.open, id: state.conversation?.id || null, fixed: state.fixed, context: state.context }); }
    function restoreDraft() { input.value = read(draftKey(), ''); state.composeMode = read(draftKey() + ':mode', 'question') === 'notes' ? 'notes' : 'question'; updateSend(); }
    function element(tag, className, text) { const el = document.createElement(tag); if (className) el.className = className; if (text !== undefined) el.textContent = text; return el; }
    function textButton(text, action) { const b = element('button', 'assistant-text-button', text); b.type = 'button'; b.addEventListener('click', action); return b; }
    function icon(name) { const i = element('i', name); i.setAttribute('aria-hidden', 'true'); return i; }
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
        const group = $('assistant-scope-options');
        const choices = [{ context: baseContext(), label: 'Mi trabajo', fixed: true }];
        if (['programador', 'admin', 'superadmin'].includes(panel.dataset.role)) choices.push({ context: { ...baseContext(), scope: 'team' }, label: 'Equipo', fixed: true });
        const page = state.page;
        if (page && !choices.some(choice => contextKey(choice.context) === contextKey(page.context))) {
            choices.push({ ...page, label: { note: 'Esta nota', task: 'Esta tarea', request: 'Esta mejora', app: 'Esta app' }[page.context.scope] || 'Esta vista', fixed: false });
        }
        if (state.context && !choices.some(choice => contextKey(choice.context) === contextKey(state.context))) {
            choices.push({ context: state.context, label: { note: 'Nota elegida', task: 'Tarea elegida', request: 'Mejora elegida', app: 'App elegida' }[state.context.scope] || 'Vista elegida', fixed: true });
        }
        group._choices = choices;
        const signature = JSON.stringify(choices.map(choice => [contextKey(choice.context), choice.label]));
        // Keep native radio focus stable when validation only updates the selected record's label.
        if (group.dataset.signature !== signature) {
            group.dataset.signature = signature; group.replaceChildren();
            choices.forEach((choice, i) => {
                const label = element('label', 'assistant-scope-choice');
                const radio = element('input'); radio.type = 'radio'; radio.name = 'assistant-scope'; radio.value = String(i);
                radio.addEventListener('change', () => {
                    const selected = group._choices[Number(radio.value)];
                    state.fixed = selected.fixed; setContext(selected);
                });
                label.append(radio, element('span', '', choice.label)); group.append(label);
            });
        }
        group.querySelectorAll('input').forEach((radio, i) => { radio.checked = contextKey(choices[i].context) === contextKey(state.context); radio.disabled = state.loading || state.stopped; });
        $('assistant-scope-chip').textContent = choices.find(choice => contextKey(choice.context) === contextKey(state.context))?.label || state.label;
        const scope = state.context?.scope || 'mine';
        $('assistant-scope-help').textContent = scope === 'mine' ? 'Tus tareas y mejoras asignadas; tus notas accesibles.'
            : scope === 'team' ? (state.context.filters?.person ? 'Trabajo del equipo con el filtro de persona de esta vista.' : 'Tareas, mejoras y notas del equipo que tienes permiso para ver.')
            : state.label + (scope === 'app' ? '. Trabajo de esta aplicación.' : '. Puedes consultar este registro y trabajo relacionado de su app.');
        if (!state.messages.length && !state.loading && !state.busy && !state.pending) renderMessages(false);
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
        const busy = state.busy || state.loading || state.pending || state.stopped;
        const notes = state.composeMode === 'notes';
        const history = !$('assistant-history').hidden;
        const start = !state.messages.length && !state.busy && !state.pending && !state.loading && !history;
        panel.classList.toggle('assistant-start', start);
        panel.classList.toggle('assistant-notes-mode', notes);
        // The scope stays visible before the first question; afterwards it folds into a chip so the conversation keeps the space.
        $('assistant-context').hidden = history || notes || (!start && !state.scopeOpen);
        $('assistant-scope-toggle').hidden = history || notes || start;
        $('assistant-scope-toggle').setAttribute('aria-expanded', String(!$('assistant-context').hidden));
        $('assistant-send').disabled = busy || !input.value.trim();
        $('assistant-send').setAttribute('aria-label', notes ? 'Organizar apuntes' : 'Enviar pregunta');
        $('assistant-send').querySelector('span').textContent = notes ? 'Organizar' : 'Enviar';
        $('assistant-composer').setAttribute('aria-busy', String(busy));
        $('assistant-input-label').textContent = notes ? 'Tus apuntes' : 'Tu pregunta';
        input.placeholder = notes ? 'Pega aquí el texto que quieres organizar…' : 'Pregunta sobre tu trabajo…';
        $('assistant-notes-create').hidden = !notes;
        $('assistant-cancel-notes').hidden = !notes;
        $('assistant-text-mode').hidden = notes;
        panel.querySelectorAll('[data-assistant-prompt]').forEach(button => { button.disabled = busy; });
        input.disabled = state.loading || state.stopped;
        $('assistant-text-mode').disabled = $('assistant-scope-toggle').disabled = state.loading || state.stopped;
        $('assistant-scope-options').querySelectorAll('input').forEach(radio => { radio.disabled = state.loading || state.stopped; });
        input.style.height = 'auto'; input.style.height = `${Math.max(notes ? 120 : 46, input.scrollHeight)}px`;
        $('assistant-save-state').textContent = state.busy || state.pending ? 'Preparando respuesta…' : mobile.matches ? 'La IA puede equivocarse. Revisa sus respuestas.' : notes ? `${isMac ? '⌘' : 'Ctrl'}+Enter organiza · La IA puede equivocarse` : 'Enter envía · Mayús+Enter, nueva línea · La IA puede equivocarse';
    }
    function composeMode(mode) {
        state.composeMode = mode; saveDraft(); renderMessages(false); updateSend(); input.focus();
    }
    function quickActions(scope) {
        const actions = {
            mine: [
                ['Qué vence hoy', 'Tareas y mejoras con vencimiento hoy.', '¿Qué tareas y mejoras mías vencen hoy?'],
                ['Vencidas', 'Trabajo abierto con el vencimiento superado.', '¿Qué tareas y mejoras mías están vencidas?'],
                ['Ver mis pendientes', 'Tareas y mejoras que siguen abiertas.', '¿Qué tareas y mejoras tengo pendientes?'],
                ['Cambios recientes', 'Actividad de los últimos siete días.', '¿Qué tareas y mejoras mías han tenido actividad en los últimos siete días?']
            ],
            team: [
                ['Pendientes del equipo', 'Trabajo abierto y quién lo tiene asignado.', 'Resume el trabajo pendiente del equipo con sus responsables.'],
                ['Vencidas del equipo', 'Trabajo abierto con el vencimiento superado.', '¿Qué tareas y mejoras del equipo están vencidas y quién las tiene?'],
                ['Próximos vencimientos', 'Qué vence durante los próximos siete días.', '¿Qué tareas y mejoras del equipo vencen durante los próximos siete días?'],
                ['Actividad reciente', 'Movimientos de los últimos siete días.', '¿Qué tareas y mejoras del equipo han tenido actividad en los últimos siete días?']
            ],
            note: [
                ['Resumir esta nota', 'Ideas principales y decisiones.', 'Resume esta nota.'],
                ['Extraer próximos pasos', 'Acciones que se desprenden del texto.', 'Extrae los próximos pasos de esta nota.']
            ],
            task: [
                ['Revisar esta tarea', 'Qué hay que hacer y qué falta aclarar.', 'Resume esta tarea y lo que falta aclarar.'],
                ['Proponer el siguiente paso', 'Una propuesta basada en su descripción.', 'Propón el siguiente paso para esta tarea.']
            ],
            request: [
                ['Resumir esta mejora', 'Decisiones, comentarios y pendientes.', 'Resume esta mejora, sus comentarios y checklist.'],
                ['Revisar qué falta', 'Próximos pasos apoyados en los registros.', '¿Qué queda por hacer en esta mejora?']
            ],
            app: [
                ['Ver pendientes de esta app', 'Tareas y mejoras abiertas.', '¿Qué tareas y mejoras hay pendientes en esta aplicación?'],
                ['Vencidas en esta app', 'Trabajo abierto con el vencimiento superado.', '¿Qué tareas y mejoras de esta aplicación están vencidas?'],
                ['Mejoras más urgentes', 'Mejoras abiertas ordenadas por prioridad.', '¿Qué mejoras abiertas de esta aplicación tienen más prioridad?'],
                ['Revisar actividad reciente', 'Las mejoras con movimientos más recientes.', 'Resume la actividad reciente de las mejoras de esta aplicación.']
            ]
        };
        return actions[scope] || actions.mine;
    }
    function trustedReference(ref) {
        return ref && /^(request|task|note):[1-9][0-9]*$/.test(ref.key) && /^\/(index\.php\?open_request=|tasks\.php\?task=|notes\.php\?id=)[1-9][0-9]*$/.test(ref.url);
    }
    function lastNotes() { return [...state.messages].reverse().find(message => message.role === 'user' && message.metadata?.input_mode === 'notes' && message.state !== 'redacted'); }
    function referencesOf(message) { return new Map((message.metadata?.references || []).filter(trustedReference).map(ref => [ref.key, ref])); }
    function appendInline(parent, tokens, refs) {
        for (const token of tokens) {
            if (token.type === 'text') parent.append(document.createTextNode(token.text));
            else if (token.type === 'strong') { const strong = element('strong'); appendInline(strong, token.children, refs); parent.append(strong); }
            else {
                const ref = refs.get(token.key);
                if (!ref) { parent.append(document.createTextNode('[referencia no disponible]')); continue; }
                const link = element('a', 'assistant-inline-reference'); link.href = ref.url;
                link.title = `${TYPE[ref.type]} #${ref.id}: ${ref.title}`; link.setAttribute('aria-label', link.title);
                // Answers usually name the record already; the chip adds type and a tappable ID without repeating the title.
                link.append(icon(TYPE_ICON[ref.type]), element('span', '', `#${ref.id}`));
                parent.append(link);
            }
        }
    }
    function messageText(message) {
        const box = element('div', 'assistant-message-text');
        // People's own text stays literal; only assistant answers get the small formatting subset.
        if (message.role !== 'assistant') { box.classList.add('assistant-message-text--plain'); box.textContent = message.content; return box; }
        const refs = referencesOf(message);
        for (const block of PrismaAssistantFormat.parse(message.content)) {
            if (block.type === 'heading') { const heading = element('p', 'assistant-answer-heading'); appendInline(heading, block.inline, refs); box.append(heading); }
            else if (block.type === 'list') {
                const list = element(block.ordered ? 'ol' : 'ul');
                for (const item of block.items) { const li = element('li'); appendInline(li, item, refs); list.append(li); }
                box.append(list);
            } else {
                const paragraph = element('p');
                block.lines.forEach((line, i) => { if (i) paragraph.append(element('br')); appendInline(paragraph, line, refs); });
                box.append(paragraph);
            }
        }
        return box;
    }
    function appendEvidence(article, metadata) {
        if (!metadata) return;
        const refs = (metadata.references || []).filter(trustedReference);
        if (refs.length) {
            const disclosure = element('details', 'assistant-references');
            const summary = element('summary', '', `${refs.length} ${refs.length === 1 ? 'fuente consultada' : 'fuentes consultadas'}`);
            summary.prepend(icon('iconoir-link'));
            const links = element('div', 'assistant-reference-links'); links.setAttribute('aria-label', 'Registros de esta respuesta');
            for (const ref of refs) {
                const link = element('a'); link.href = ref.url;
                link.append(icon(TYPE_ICON[ref.type]), element('span', 'assistant-reference-title', ref.title), element('span', 'assistant-reference-id', `${TYPE[ref.type]} #${ref.id}`));
                links.append(link);
            }
            disclosure.append(summary, links); article.append(disclosure);
        }
        if (metadata.coverage?.length) {
            const count = metadata.coverage.reduce((sum, page) => sum + page.reviewed, 0);
            const hasMore = metadata.coverage.some(page => page.has_more);
            const laterPage = metadata.coverage.some(page => page.offset > 0);
            const suffix = hasMore ? '. Consulta parcial: puedes afinarla o pedir más resultados.' : metadata.partial ? (laterPage ? '. Consulta acotada a esta página.' : '. Parte del contenido se ha recortado.') : ' en esta consulta.';
            const text = `${count} ${count === 1 ? 'registro revisado' : 'registros revisados'}` + suffix;
            const coverage = element('p', 'assistant-coverage', text);
            const sources = article.querySelector('.assistant-references');
            if (sources && !hasMore && !metadata.partial) sources.append(coverage); else article.append(coverage);
        }
    }
    function actionButton(label, iconName, action) {
        const button = element('button', 'assistant-action'); button.type = 'button';
        if (iconName) button.append(icon(iconName));
        button.append(element('span', '', label)); button.addEventListener('click', action); return button;
    }
    function createLink(className, ...children) {
        const link = element('a', className); link.href = '/ai-inbox.php#desde-asistente'; link.dataset.assistantCreate = '';
        link.append(...children); return link;
    }
    async function copyMessage(message, button) {
        const labels = {};
        for (const ref of referencesOf(message).values()) labels[ref.key] = `(${TYPE[ref.type]} #${ref.id})`;
        try {
            await navigator.clipboard.writeText(PrismaAssistantFormat.plainText(message.content, labels));
            const label = button.querySelector('span'); label.textContent = 'Copiado';
            setTimeout(() => { label.textContent = 'Copiar'; }, 2000);
        } catch { error('No se pudo copiar. Selecciona el texto y cópialo manualmente.'); }
    }
    // Follow-ups keep the answer's own scope, even if the page changed since.
    function followUps(message, question) {
        if (question?.metadata?.input_mode === 'notes') return [createLink('assistant-action assistant-action--primary', icon('iconoir-plus'), element('span', '', 'Crear tareas y mejoras con estos apuntes'))];
        const context = message.context || question?.context;
        const chips = [];
        const ask = (label, prompt) => chips.push(actionButton(label, null, () => send(null, prompt, context)));
        if (message.metadata?.coverage?.some(page => page.has_more)) ask('Ver más resultados', 'Muestra los siguientes resultados.');
        if (referencesOf(message).size >= 2 && ['mine', 'team', 'app'].includes(context?.scope)) {
            ask('Ordenar por prioridad', 'Ordena estos resultados por prioridad.');
            ask('Solo lo que vence esta semana', 'De estos resultados, ¿cuáles vencen durante los próximos siete días?');
        }
        chips.forEach(chip => { chip.dataset.assistantPrompt = ''; chip.classList.add('assistant-action--chip'); });
        return chips;
    }
    function suggestion(title, description, iconName) {
        const button = element('button', 'assistant-suggestion'); button.type = 'button';
        const copy = element('span', 'assistant-suggestion-copy'); copy.append(element('strong', '', title), element('span', '', description));
        button.append(copy, icon(iconName)); return button;
    }
    function emptyState() {
        const empty = element('div', 'assistant-empty');
        if (state.composeMode === 'notes') {
            empty.append(element('h3', 'assistant-empty-title', 'Ordenar un texto'), element('p', 'assistant-empty-text', 'Pega tus apuntes abajo y pulsa Organizar. Recibirás un resumen y los próximos pasos, y después podrás crear tareas y mejoras con ellos.'));
            return empty;
        }
        empty.append(element('h3', 'assistant-empty-title', 'Busca, resume y aclara tu trabajo'), element('p', 'assistant-empty-text', 'Consulta tareas, mejoras y notas con sus fuentes. Las consultas no modifican registros.'));
        empty.append(element('h4', 'assistant-actions-label', 'Consulta en un clic'));
        for (const [title, description, prompt] of quickActions(state.context?.scope || 'mine')) {
            const button = suggestion(title, description, 'iconoir-arrow-right'); button.dataset.assistantPrompt = prompt;
            button.addEventListener('click', () => send(null, prompt)); empty.append(button);
        }
        empty.append(element('h4', 'assistant-actions-label', 'Con tu texto'));
        const notes = suggestion('Ordenar apuntes', 'Resumen y próximos pasos de un texto que pegues.', 'iconoir-align-left');
        notes.addEventListener('click', () => composeMode('notes'));
        const copy = element('span', 'assistant-suggestion-copy'); copy.append(element('strong', '', 'Crear tareas y mejoras'), element('span', '', 'Abre el editor para revisarlas antes de guardar.'));
        const create = createLink('assistant-suggestion', copy, icon('iconoir-arrow-up-right'));
        empty.append(notes, create);
        return empty;
    }
    function waitStatus() {
        const box = element('div', 'assistant-loading');
        const status = element('p', 'assistant-message-state'); status.id = 'assistant-wait'; status.setAttribute('role', 'status');
        const notes = (state.outgoing || state.messages.at(-1))?.metadata?.input_mode === 'notes';
        status.append(document.createTextNode(notes ? 'Organizando tus apuntes…' : 'Consultando Prisma…'));
        const time = element('span', 'assistant-wait-time'); time.setAttribute('aria-hidden', 'true'); status.append(time);
        const note = element('p', 'assistant-wait-note', 'Las consultas amplias pueden tardar hasta un minuto.'); note.hidden = true;
        box.append(status, note);
        for (let i = 0; i < 3; i++) { const line = element('span'); line.setAttribute('aria-hidden', 'true'); box.append(line); }
        return box;
    }
    function updateWait() {
        const time = messages.querySelector('.assistant-wait-time');
        if (!time) { clearInterval(state.waitTimer); state.waitTimer = null; return; }
        const seconds = Math.floor((Date.now() - state.busySince) / 1000);
        time.textContent = seconds >= 8 ? ` ${seconds} s` : '';
        messages.querySelector('.assistant-wait-note').hidden = seconds < 8;
    }
    function renderMessages(scroll = true) {
        messages.replaceChildren();
        if (state.cursor) messages.append(textButton('Ver mensajes anteriores', olderMessages));
        if (!state.messages.length && !state.busy && !state.pending) messages.append(emptyState());
        let previousScope = null;
        state.messages.forEach((message, index) => {
            const article = element('article', `assistant-message assistant-message--${message.role}`);
            const notes = message.metadata?.input_mode === 'notes';
            const label = element('div', 'assistant-message-label', message.role === 'user' ? (notes ? 'Tus apuntes' : 'Tú') : 'Prisma');
            if (message.role === 'assistant') label.prepend(icon('iconoir-sparks'));
            article.append(label);
            if (message.state === 'redacted') article.append(element('p', 'assistant-message-state', 'Este mensaje ya no está disponible porque cambió el acceso a su contexto.'));
            else {
                article.append(messageText(message));
                appendEvidence(article, message.metadata);
                // Show a question's scope only when it differs from the previous one; «Mi trabajo» is the default.
                if (message.role === 'user' && !notes) {
                    const scope = contextKey(message.context);
                    if (scope !== previousScope && (previousScope !== null || message.context?.scope !== 'mine')) article.append(element('p', 'assistant-message-context', contextLabel(message.context)));
                    previousScope = scope;
                }
                if (message.state === 'failed') {
                    article.append(element('p', 'assistant-message-state', 'La respuesta no se completó.'));
                    // Retry only the last turn; older failed turns are not reordered into history.
                    if (message.id === state.messages.at(-1)?.id) article.append(textButton('Reintentar este mensaje', () => send(message)));
                }
                if (message.role === 'assistant' && message.state === 'complete') {
                    const actions = element('div', 'assistant-message-actions');
                    const copy = actionButton('Copiar', 'iconoir-copy', () => copyMessage(message, copy)); actions.append(copy);
                    if (index === state.messages.length - 1 && !state.busy && !state.pending) actions.append(...followUps(message, state.messages[index - 1]));
                    article.append(actions);
                }
            }
            messages.append(article);
        });
        if (state.outgoing && state.busy && !state.messages.some(message => message.client_key === state.outgoing.key)) {
            const outgoing = element('article', 'assistant-message assistant-message--user');
            outgoing.append(element('div', 'assistant-message-label', state.outgoing.metadata?.input_mode === 'notes' ? 'Tus apuntes' : 'Tú'), messageText(state.outgoing));
            messages.append(outgoing);
        }
        if (state.pending || state.busy) {
            state.busySince ||= Date.now();
            messages.append(waitStatus()); updateWait();
            state.waitTimer ||= setInterval(updateWait, 1000);
        } else { state.busySince = 0; clearInterval(state.waitTimer); state.waitTimer = null; }
        // The start screen reads from the top; a conversation follows its latest message.
        if (scroll || !state.messages.length) messages.scrollTop = state.messages.length || state.busy || state.pending ? messages.scrollHeight : 0;
        const conversation = state.conversation;
        $('assistant-conversation-bar').hidden = !conversation;
        $('assistant-conversation-title').textContent = conversation?.title || '';
        updateSend(); updateLatest();
    }
    function updateLatest() { $('assistant-latest').hidden = !state.messages.length || messages.scrollHeight - messages.scrollTop - messages.clientHeight < 120; }
    function contextLabel(context) {
        const labels = { mine: 'Mi trabajo', team: 'Trabajo del equipo', app: 'Aplicación', request: 'Mejora', task: 'Tarea', note: 'Nota' };
        return (labels[context?.scope] || 'Mi trabajo') + (context?.entity_id ? ` #${context.entity_id}` : '');
    }
    function applyDetail(data, scroll = true) {
        state.conversation = data.conversation; state.messages = data.messages; state.cursor = data.next_cursor;
        const attempt = read(`attempt:${data.conversation.id}`, null);
        if (attempt && data.messages.some(message => message.client_key === attempt.key && message.state === 'complete')) write(`attempt:${data.conversation.id}`, null);
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
        saveDraft(); const epoch = ++state.generation; clearTimeout(state.poll); state.busy = false; state.outgoing = null; state.loading = true; updateSend();
        $('assistant-manage').hidden = true; error('');
        messages.replaceChildren(element('p', 'assistant-message-state', 'Abriendo conversación…')); $('assistant-latest').hidden = true;
        try {
            const data = await api(`/api/ai-conversations.php?id=${id}`);
            if (epoch !== state.generation) return;
            state.loading = false; applyDetail(data); restoreDraft();
            if (restoreContext && state.fixed) await setContext({ context: data.conversation.context, label: data.conversation.context_label });
            if (state.open && document.activeElement === document.body) (mobile.matches ? panel.querySelector('[data-assistant-action="close"]') : input).focus();
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
            const oldHeight = messages.scrollHeight, oldTop = messages.scrollTop;
            state.messages = [...data.messages, ...state.messages]; state.cursor = data.next_cursor; renderMessages(false);
            messages.scrollTop = oldTop + messages.scrollHeight - oldHeight; updateLatest();
        } catch (e) { error(e.message, olderMessages); }
    }
    function historyView(visible) {
        if (visible) $('assistant-manage').hidden = true;
        $('assistant-history').hidden = !visible; $('assistant-chat').hidden = visible;
        for (const id of ['assistant-composer', 'assistant-context']) $(id).hidden = visible;
        panel.querySelector('[data-assistant-action="history"]').setAttribute('aria-pressed', String(visible));
        updateSend();
    }
    async function showHistory(append = false) {
        if (append && state.historyBusy) return;
        saveDraft(); historyView(true);
        if ($('assistant-error').contains(document.activeElement)) panel.querySelector('[data-assistant-action="history"]').focus();
        error('');
        const epoch = ++state.historyEpoch, list = $('assistant-history-list');
        state.historyBusy = true; list.setAttribute('aria-busy', 'true'); $('assistant-history-more').disabled = true;
        if (!append) {
            list.replaceChildren(element('p', 'assistant-message-state', 'Cargando conversaciones…'));
            state.historyCursor = null; state.historyItems = []; state.historyConfirm = null; $('assistant-history-more').hidden = true;
        }
        try {
            const data = await api('/api/ai-conversations.php' + (append && state.historyCursor ? `?before=${state.historyCursor}` : ''));
            if (epoch !== state.historyEpoch) return;
            state.historyItems.push(...data.items); state.historyCursor = data.next_cursor; renderHistory();
        } catch (e) {
            if (epoch !== state.historyEpoch) return;
            if (!append) list.replaceChildren();
            error(e.message, () => showHistory(append));
        } finally {
            if (epoch === state.historyEpoch) { state.historyBusy = false; list.setAttribute('aria-busy', 'false'); $('assistant-history-more').disabled = false; }
        }
    }
    function searchable(text) { return String(text || '').normalize('NFD').replace(/\p{Diacritic}/gu, '').toLowerCase(); }
    function historyDate(conversation) { const date = new Date(String(conversation.updated_at).replace(' ', 'T')); return Number.isNaN(date.getTime()) ? null : date; }
    function historyGroup(date) {
        if (!date) return 'Anteriores';
        const today = new Date(); today.setHours(0, 0, 0, 0);
        const days = Math.floor((today - new Date(date.getFullYear(), date.getMonth(), date.getDate())) / 86400000);
        return days <= 0 ? 'Hoy' : days === 1 ? 'Ayer' : days < 7 ? 'Últimos 7 días' : 'Anteriores';
    }
    function historyRow(conversation) {
        const row = element('div', 'assistant-history-row');
        if (state.historyConfirm === conversation.id) {
            row.classList.add('assistant-history-row--confirm');
            const confirm = element('button', 'assistant-history-confirm', 'Eliminar'); confirm.type = 'button'; confirm.dataset.confirmDelete = '';
            confirm.addEventListener('click', () => deleteFromHistory(conversation.id));
            const cancel = element('button', '', 'Cancelar'); cancel.type = 'button';
            cancel.addEventListener('click', () => cancelHistoryDelete(conversation.id));
            row.append(element('p', '', `¿Eliminar «${conversation.title}» y sus mensajes?`), confirm, cancel);
            return row;
        }
        const date = historyDate(conversation);
        const when = !date ? '' : historyGroup(date) === 'Hoy' ? new Intl.DateTimeFormat('es', { hour: '2-digit', minute: '2-digit' }).format(date) : new Intl.DateTimeFormat('es', { day: 'numeric', month: 'short' }).format(date);
        const open = element('button', 'assistant-history-item'); open.type = 'button'; open.dataset.conversation = conversation.id;
        open.setAttribute('aria-current', String(conversation.id === state.conversation?.id));
        open.append(element('strong', '', conversation.title), element('span', '', conversation.context_label + (when ? ' · ' + when : '')));
        open.addEventListener('click', () => { hideHistory(); loadConversation(conversation.id, true); });
        const remove = element('button', 'assistant-history-delete'); remove.type = 'button'; remove.title = 'Eliminar';
        remove.setAttribute('aria-label', `Eliminar «${conversation.title}»`); remove.append(icon('iconoir-trash'));
        remove.addEventListener('click', () => { state.historyConfirm = conversation.id; renderHistory(); $('assistant-history-list').querySelector('[data-confirm-delete]')?.focus(); });
        row.append(open, remove);
        return row;
    }
    function renderHistory() {
        const list = $('assistant-history-list'), query = searchable($('assistant-history-filter').value.trim());
        list.replaceChildren();
        $('assistant-history-more').hidden = !state.historyCursor;
        if (!state.historyItems.length) {
            list.append(element('p', 'assistant-message-state', 'Aquí encontrarás las conversaciones que empieces con Prisma.'), textButton('Empezar una conversación', newConversation));
            return;
        }
        const items = state.historyItems.filter(c => !query || searchable(`${c.title} ${c.context_label}`).includes(query));
        if (!items.length) list.append(element('p', 'assistant-message-state', 'Ninguna conversación cargada coincide.' + (state.historyCursor ? ' Carga más para seguir buscando.' : '')));
        let group = null;
        for (const conversation of items) {
            const name = historyGroup(historyDate(conversation));
            if (name !== group) { group = name; list.append(element('h4', 'assistant-history-group', name)); }
            list.append(historyRow(conversation));
        }
    }
    function cancelHistoryDelete(id) {
        state.historyConfirm = null; renderHistory();
        $('assistant-history-list').querySelector(`[data-conversation="${id}"]`)?.parentElement.querySelector('.assistant-history-delete')?.focus();
    }
    async function deleteFromHistory(id) {
        try {
            await api('/api/ai-conversations.php', { method: 'DELETE', body: JSON.stringify({ id }) });
            state.historyItems = state.historyItems.filter(c => c.id !== id); state.historyConfirm = null; write(`draft:${id}`, '');
            if (state.conversation?.id === id) resetConversation();
            renderHistory(); $('assistant-history-filter').focus();
        } catch (e) { error(e.message); }
    }
    function hideHistory() { error(''); ++state.historyEpoch; state.historyBusy = false; state.historyConfirm = null; historyView(false); updateSend(); updateLatest(); }
    function resetConversation() {
        ++state.generation; clearTimeout(state.poll); state.conversation = null; state.messages = []; state.cursor = null; state.pending = false; state.busy = false; state.loading = false; state.outgoing = null; state.scopeOpen = false;
        $('assistant-manage').hidden = true; restoreDraft(); renderMessages(); saveUI();
    }
    function newConversation() {
        saveDraft(); resetConversation(); hideHistory(); error(''); (mobile.matches ? panel.querySelector('[data-assistant-action="new"]') : input).focus();
    }
    async function send(retry = null, quickPrompt = null, quickContext = null) {
        if (state.busy || state.loading || state.pending || state.stopped) return;
        const submittedDraft = input.value, submittedMode = state.composeMode;
        const content = retry ? retry.content : quickPrompt || input.value.trim();
        if ((!retry && !quickPrompt && !input.value.trim()) || !content) return;
        if (Array.from(content).length > 10000) { error('El texto debe ocupar como máximo 10.000 caracteres.'); return; }
        const epoch = state.generation; let target = state.conversation?.id;
        const inputMode = retry ? (retry.metadata?.input_mode || 'question') : quickPrompt ? 'question' : submittedMode;
        const desiredContext = retry?.context || (inputMode === 'notes' ? baseContext() : quickContext || { ...state.context, fixed: state.fixed });
        const previous = target ? read(`attempt:${target}`, null) : null;
        const sameAttempt = previous?.content === content && (previous.input_mode || 'question') === inputMode && contextKey(previous.context) === contextKey(desiredContext);
        const key = retry?.client_key || (sameAttempt ? previous.key : crypto.randomUUID());
        const context = sameAttempt && !retry ? previous.context : desiredContext;
        state.busy = true; state.scopeOpen = false; state.outgoing = retry ? null : { role: 'user', content, context, key, metadata: submittedMode === 'notes' && !quickPrompt ? { input_mode: 'notes' } : null }; error(''); renderMessages(); saveDraft(); (mobile.matches && quickPrompt ? messages : input).focus({ preventScroll: true });
        try {
            if (!target) {
                const conversation = await api('/api/ai-conversations.php', { method: 'POST', body: JSON.stringify({ context }) });
                if (epoch !== state.generation) return;
                state.conversation = conversation; target = conversation.id;
                write(`draft:${target}`, input.value); write(`draft:${target}:mode`, state.composeMode); write('draft:new', ''); write('draft:new:mode', 'question'); saveUI();
            }
            write(`attempt:${target}`, { key, content, context, input_mode: inputMode });
            const data = await api('/api/ai-messages.php', { method: 'POST', body: JSON.stringify({ conversation_id: target, content, input_mode: inputMode, client_key: key, context, revision: state.conversation.revision }) });
            if (epoch !== state.generation || state.conversation?.id !== target) return;
            state.busy = false; state.outgoing = null; write(`attempt:${target}`, null); applyDetail(data);
            if (!retry && !quickPrompt && input.value === submittedDraft && state.composeMode === submittedMode) { input.value = ''; state.composeMode = 'question'; saveDraft(); } updateSend();
        } catch (e) {
            if (epoch !== state.generation) return;
            state.busy = false; state.outgoing = null;
            // If the response was lost, read the stored turn before offering a retry.
            if (target) {
                try { const data = await api(`/api/ai-conversations.php?id=${target}`); if (epoch !== state.generation) return; applyDetail(data); }
                catch { /* Keep original error and the local draft. */ }
            }
            const saved = state.messages.find(m => m.client_key === key);
            if (saved?.state === 'complete') { write(`attempt:${target}`, null); if (!retry && !quickPrompt && input.value === submittedDraft && state.composeMode === submittedMode) { input.value = ''; state.composeMode = 'question'; saveDraft(); } error(''); }
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
            state.conversation = data; $('assistant-manage').hidden = true; renderMessages(false); error(''); saveUI(); panel.querySelector('[data-assistant-action="manage"]').focus();
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
        if (trigger) {
            trigger.setAttribute('aria-label', mobile.matches ? 'Asistente de Prisma' : 'Preguntar a Prisma');
            trigger.title = mobile.matches ? `Abrir el asistente (${shortcut})` : `Abrir el asistente en un panel lateral (${shortcut})`;
        }
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
        updateSend(); updateLatest();
    }
    function close() { $('assistant-manage').hidden = true; ++state.historyEpoch; state.historyBusy = false; saveDraft(); state.open = false; panel.hidden = true; document.body.classList.remove('assistant-open'); mobileMode(); triggers.forEach(button => button.setAttribute('aria-expanded', 'false')); clearTimeout(state.poll); saveUI(); state.returnFocus?.focus?.({ preventScroll: true }); }
    async function open() {
        state.returnFocus = document.activeElement; state.open = true; panel.hidden = false; document.body.classList.add('assistant-open'); triggers.forEach(button => button.setAttribute('aria-expanded', 'true'));
        // Close the notification overlay so two panels cannot cover each other.
        if ($('inbox-panel')?.classList.contains('open') && typeof toggleInbox === 'function') toggleInbox();
        hideHistory(); mobileMode(); syncPage(); saveUI(); (mobile.matches ? panel.querySelector('[data-assistant-action="close"]') : input).focus();
        setContext({ context: state.context, label: state.label });
        if (state.conversation) await loadConversation(state.conversation.id); else renderMessages();
    }
    function takeCreateDraft() {
        if (state.stopped || location.hash !== '#desde-asistente') return '';
        const key = prefix + 'create-handoff';
        try {
            const raw = sessionStorage.getItem(key); sessionStorage.removeItem(key);
            const draft = JSON.parse(raw);
            return draft && typeof draft.text === 'string' && draft.text.length <= 10000 && Date.now() - draft.at >= 0 && Date.now() - draft.at < 600000 ? draft.text : '';
        } catch { return ''; }
    }
    window.PrismaAssistant = Object.freeze({ open, close, takeCreateDraft });
    document.addEventListener('keydown', e => {
        if ((e.metaKey || e.ctrlKey) && !e.altKey && !e.isComposing && e.key.toLowerCase() === 'j') {
            e.preventDefault(); e.stopImmediatePropagation();
            if (!e.repeat) state.open ? close() : open();
        }
    }, true);
    document.querySelectorAll('[data-assistant-open]').forEach(button => button.addEventListener('click', () => open()));
    trigger?.addEventListener('click', e => { e.preventDefault(); state.open ? close() : open(); });
    panel.addEventListener('click', e => {
        const action = e.target.closest('[data-assistant-action]')?.dataset.assistantAction;
        if (action === 'close') close();
        if (action === 'new') newConversation();
        if (action === 'history') showHistory();
        if (action === 'chat') { hideHistory(); error(''); (mobile.matches ? panel.querySelector('[data-assistant-action="history"]') : input).focus(); }
        if (action === 'cancel-manage') { $('assistant-manage').hidden = true; panel.querySelector('[data-assistant-action="manage"]').focus(); }
        if (action === 'manage') { $('assistant-manage').hidden = !$('assistant-manage').hidden; $('assistant-rename').value = state.conversation?.title || ''; $('assistant-delete-confirm').hidden = true; if (!$('assistant-manage').hidden) $('assistant-rename').focus(); }
        if (action === 'delete') $('assistant-delete-confirm').hidden = false;
        if (action === 'confirm-delete') deleteConversation();
    });
    $('assistant-manage').addEventListener('submit', rename);
    $('assistant-history-more').addEventListener('click', () => showHistory(true));
    $('assistant-composer').addEventListener('submit', e => { e.preventDefault(); send(); input.focus(); });
    panel.addEventListener('keydown', e => e.stopPropagation());
    // Create links live in the start screen, the notes composer and after organised notes.
    panel.addEventListener('click', event => {
        if (!event.target.closest('[data-assistant-create]') || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        const text = state.composeMode === 'notes' && input.value.trim() ? input.value : lastNotes()?.content || '';
        try { sessionStorage.setItem(prefix + 'create-handoff', JSON.stringify({ text, at: Date.now() })); }
        catch { if (text.trim()) { event.preventDefault(); error('No se pudo trasladar el texto. Copia tus apuntes antes de abrir el editor.'); return; } }
        close();
    });
    $('assistant-text-mode').addEventListener('click', () => composeMode('notes'));
    $('assistant-cancel-notes').addEventListener('click', () => composeMode('question'));
    $('assistant-scope-toggle').addEventListener('click', () => {
        state.scopeOpen = !state.scopeOpen; updateSend();
        if (state.scopeOpen) $('assistant-scope-options').querySelector('input:checked')?.focus();
    });
    $('assistant-history-filter').addEventListener('input', () => { state.historyConfirm = null; renderHistory(); });
    messages.addEventListener('scroll', updateLatest);
    $('assistant-latest').addEventListener('click', () => { messages.scrollTop = messages.scrollHeight; updateLatest(); input.focus({ preventScroll: true }); });
    input.addEventListener('input', () => { saveDraft(); updateSend(); });
    input.addEventListener('keydown', e => { if (e.key === 'Enter' && (e.ctrlKey || e.metaKey || (state.composeMode === 'question' && !mobile.matches)) && !e.shiftKey && !e.altKey && !e.isComposing && e.keyCode !== 229) { e.preventDefault(); send(); } });
    // Capture shortcuts so Escape inside the assistant doesn't close an underlying editor.
    document.addEventListener('keydown', e => {
        if (!state.open) return;
        if (e.key === 'Escape' && (panel.contains(e.target) || mobile.matches)) {
            e.preventDefault(); e.stopImmediatePropagation();
            if (!$('assistant-manage').hidden) { $('assistant-manage').hidden = true; panel.querySelector('[data-assistant-action="manage"]').focus(); }
            else if (!$('assistant-history').hidden && state.historyConfirm) cancelHistoryDelete(state.historyConfirm);
            else if (!$('assistant-history').hidden) { hideHistory(); panel.querySelector('[data-assistant-action="history"]').focus(); }
            else if (state.scopeOpen && !$('assistant-scope-toggle').hidden) { state.scopeOpen = false; updateSend(); $('assistant-scope-toggle').focus(); }
            else if (state.composeMode === 'notes') composeMode('question');
            else close();
            return;
        }
        if (e.key === 'Tab' && mobile.matches) {
            const focusable = [...panel.querySelectorAll('button, input, textarea, select, summary, a[href]')].filter(el => !el.disabled && el.getClientRects().length);
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
    restoreDraft(); syncPage(); renderMessages(); mobileMode();
    if (saved.open) open();
})();
