/**
 * Notas (notes.php): lista a la izquierda, lector/editor a la derecha.
 * API en /api/notes.php; textos de presentación en notes-helpers.js.
 *
 * Modos del panel: empty | loading | read | edit | new | missing | error
 */

const currentUserId = Number(document.body.dataset.userId);
const NOTE_APPS = JSON.parse(document.getElementById('notes-apps-data')?.textContent || '[]');
const IS_MAC = /Mac|iPhone|iPad/.test(navigator.platform || navigator.userAgent);

const notesState = {
    list: [],
    loading: true,
    error: false,
    filter: 'all',
    appId: '',
    q: '',
    selectedId: null,
    current: null,      // nota abierta, tal como la devuelve la API
    mode: 'empty',
    draft: null,        // { title, content, app_id, is_shared, shared_user_ids } al editar
    original: null,     // copia del borrador al empezar, para saber si hay cambios
    baseRevision: null, // revisión sobre la que se editó (aviso de conflicto)
    conflict: null,     // versión del servidor tras un 409
    saving: false,
    preview: false
};

let teamMembers = [];
let searchTimer = null;
let shareSaveChain = Promise.resolve();
let activeMenu = null;
let lastPaneKey = '';

document.addEventListener('DOMContentLoaded', initNotes);

function initNotes() {
    document.querySelectorAll('.notes-filter-btn').forEach(btn => {
        btn.addEventListener('click', () => setFilter(btn.dataset.filter));
    });

    document.getElementById('notes-app-filter')?.addEventListener('change', (e) => {
        notesState.appId = e.target.value;
        loadNotes();
    });

    const search = document.getElementById('notes-search');
    search.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => {
            notesState.q = search.value.trim();
            loadNotes();
        }, 250);
    });
    search.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && search.value) {
            e.stopPropagation();
            search.value = '';
            notesState.q = '';
            loadNotes();
        }
    });

    document.getElementById('notes-new-btn').addEventListener('click', startNewNote);
    document.getElementById('notes-list').addEventListener('click', handleListClick);

    const pane = document.getElementById('note-pane');
    pane.addEventListener('click', handlePaneClick);
    pane.addEventListener('input', handleEditorInput);
    pane.addEventListener('change', handleEditorInput);
    pane.addEventListener('keydown', handleEditorKeydown);

    document.addEventListener('keydown', handleGlobalKeydown);
    window.addEventListener('beforeunload', (e) => {
        if (isDirty()) {
            e.preventDefault();
            e.returnValue = '';
        }
    });

    loadTeam();
    loadNotes();

    const openId = Number(new URLSearchParams(window.location.search).get('id'));
    if (openId > 0) {
        selectNote(openId);
    } else {
        renderPane();
    }
}

// ========== Lista ==========

function listKey() {
    const params = new URLSearchParams({ filter: notesState.filter });
    if (notesState.appId) params.set('app_id', notesState.appId);
    if (notesState.q) params.set('q', notesState.q);
    return params.toString();
}

async function loadNotes() {
    const key = listKey();
    notesState.error = false;
    document.getElementById('notes-list').classList.add('is-refreshing');
    renderNoteList();

    try {
        const response = await fetch(`/api/notes.php?${key}`);
        const data = await response.json();
        if (key !== listKey()) return; // llegó tarde: ya se pidió otra lista
        if (!data.success) throw new Error(`${response.status} ${data.error}`);
        notesState.list = data.data;
    } catch (error) {
        if (key !== listKey()) return;
        console.error('Error loading notes', key, error);
        notesState.error = true;
    }

    notesState.loading = false;
    document.getElementById('notes-list').classList.remove('is-refreshing');
    renderNoteList();
    if (notesState.mode === 'empty') renderPane();
}

function setFilter(filter) {
    if (filter === notesState.filter) return;
    notesState.filter = filter;
    document.querySelectorAll('.notes-filter-btn').forEach(btn => {
        const active = btn.dataset.filter === filter;
        btn.classList.toggle('is-active', active);
        btn.setAttribute('aria-selected', active ? 'true' : 'false');
    });
    loadNotes();
}

function clearListFilters() {
    const search = document.getElementById('notes-search');
    const app = document.getElementById('notes-app-filter');
    search.value = '';
    if (app) app.value = '';
    notesState.q = '';
    notesState.appId = '';
    if (notesState.filter !== 'all') {
        setFilter('all');
    } else {
        loadNotes();
    }
}

function hasListFilters() {
    return !!(notesState.q || notesState.appId || notesState.filter !== 'all');
}

function renderNoteList() {
    const container = document.getElementById('notes-list');

    if (notesState.loading) {
        container.innerHTML = Array.from({ length: 5 }, () => `
            <div class="note-skeleton" aria-hidden="true">
                <span class="skeleton-line skeleton-line--title"></span>
                <span class="skeleton-line"></span>
                <span class="skeleton-line skeleton-line--short"></span>
            </div>`).join('');
        container.setAttribute('aria-busy', 'true');
        return;
    }
    container.removeAttribute('aria-busy');

    if (notesState.error) {
        container.innerHTML = listMessageHtml({
            icon: 'iconoir-warning-circle',
            title: 'No se pudieron cargar las notas',
            text: 'Comprueba la conexión e inténtalo de nuevo.',
            action: { name: 'retry-list', label: 'Reintentar' }
        });
        return;
    }

    if (!notesState.list.length) {
        container.innerHTML = hasListFilters()
            ? listMessageHtml({
                icon: 'iconoir-search',
                title: 'Ninguna nota coincide',
                text: notesState.q ? `No hay resultados para "${escapeHtml(notesState.q)}".` : 'Prueba con otro filtro.',
                action: { name: 'clear-filters', label: 'Quitar filtros' }
            })
            : listMessageHtml({
                icon: 'iconoir-page',
                title: 'Todavía no hay notas',
                text: 'Guarda aquí procedimientos, actas o datos que el equipo consulta a menudo.',
                action: { name: 'new', label: 'Nueva nota', primary: true }
            });
        return;
    }

    const pinned = notesState.list.filter(n => Number(n.is_pinned) === 1);
    const rest = notesState.list.filter(n => Number(n.is_pinned) !== 1);
    const grouped = pinned.length > 0 && rest.length > 0;

    container.innerHTML = `
        ${grouped ? '<p class="notes-group-label">Fijadas</p>' : ''}
        ${pinned.length ? `<ul class="notes-items">${pinned.map(noteItemHtml).join('')}</ul>` : ''}
        ${grouped ? '<p class="notes-group-label">Otras notas</p>' : ''}
        ${rest.length ? `<ul class="notes-items">${rest.map(noteItemHtml).join('')}</ul>` : ''}
    `;
}

function listMessageHtml({ icon, title, text, action }) {
    return `
        <div class="notes-list-message">
            <span class="notes-message-icon"><i class="${icon}" aria-hidden="true"></i></span>
            <p class="notes-message-title">${title}</p>
            <p class="notes-message-text">${text}</p>
            ${action ? `<button type="button" class="btn ${action.primary ? 'btn-primary' : 'btn-secondary'} btn-sm" data-action="${action.name}">${action.label}</button>` : ''}
        </div>`;
}

function noteItemHtml(note) {
    const isOwner = Number(note.user_id) === currentUserId;
    const share = noteShareSummary(note, isOwner);
    const excerpt = noteExcerpt(note.excerpt, 120);
    const selected = Number(note.id) === Number(notesState.selectedId);
    const sharedIcon = share.icon !== 'iconoir-lock'
        ? `<span class="note-item-share" title="${share.label}"><i class="${share.icon}" aria-hidden="true"></i><span class="visually-hidden">${share.label}</span></span>`
        : '';

    return `
        <li>
            <button type="button" class="note-item${selected ? ' is-selected' : ''}" data-note-id="${note.id}"${selected ? ' aria-current="true"' : ''}>
                <span class="note-item-head">
                    <span class="note-item-title">${escapeHtml(note.title)}</span>
                    ${Number(note.is_pinned) === 1 ? '<i class="iconoir-pin note-item-pin" aria-hidden="true"></i><span class="visually-hidden">Fijada</span>' : ''}
                </span>
                <span class="note-item-excerpt${excerpt ? '' : ' is-empty'}">${excerpt ? escapeHtml(excerpt) : 'Sin contenido'}</span>
                <span class="note-item-meta">
                    <span title="${formatNoteDateTime(note.updated_at)}">${formatNoteTime(note.updated_at, serverClock(note))}</span>
                    ${!isOwner ? `<span class="note-item-author">${escapeHtml(note.author_name || '')}</span>` : ''}
                    ${note.app_name ? `<span class="note-item-app">${escapeHtml(note.app_name)}</span>` : ''}
                    ${sharedIcon}
                </span>
            </button>
        </li>`;
}

function handleListClick(e) {
    const item = e.target.closest('[data-note-id]');
    if (item) {
        selectNote(Number(item.dataset.noteId));
        return;
    }
    const action = e.target.closest('[data-action]')?.dataset.action;
    if (action === 'retry-list') loadNotes();
    if (action === 'clear-filters') clearListFilters();
    if (action === 'new') startNewNote();
}

function markSelectedInList() {
    document.querySelectorAll('#notes-list [data-note-id]').forEach(item => {
        const selected = Number(item.dataset.noteId) === Number(notesState.selectedId);
        item.classList.toggle('is-selected', selected);
        if (selected) item.setAttribute('aria-current', 'true');
        else item.removeAttribute('aria-current');
    });
}

// ========== Abrir, crear, editar ==========

async function selectNote(id) {
    if (id === Number(notesState.selectedId) && notesState.mode === 'read') {
        setReadingMode(true);
        return;
    }
    if (!confirmDiscardChanges()) return;

    notesState.selectedId = id;
    notesState.mode = 'loading';
    notesState.draft = null;
    notesState.conflict = null;
    markSelectedInList();
    setReadingMode(true);
    renderPane();

    try {
        const response = await fetch(`/api/notes.php?id=${id}`);
        const data = await response.json();
        if (Number(notesState.selectedId) !== id) return;

        if (response.status === 404) {
            notesState.current = null;
            notesState.mode = 'missing';
            updateUrl(null);
        } else if (!data.success) {
            throw new Error(`${response.status} ${data.error}`);
        } else {
            notesState.current = data.data;
            notesState.mode = 'read';
            updateUrl(id);
        }
    } catch (error) {
        console.error('Error loading note', id, error);
        notesState.mode = 'error';
    }

    renderPane();
    if (notesState.mode === 'read') focusPaneHeading();
}

function startNewNote() {
    if (notesState.mode === 'new' && !isDirty()) {
        document.getElementById('note-title-input')?.focus();
        return;
    }
    if (!confirmDiscardChanges()) return;

    notesState.mode = 'new';
    notesState.selectedId = null;
    notesState.current = null;
    notesState.conflict = null;
    notesState.preview = false;
    notesState.draft = {
        title: '',
        content: '',
        app_id: notesState.appId || '',
        is_shared: false,
        shared_user_ids: []
    };
    notesState.original = { ...notesState.draft };
    markSelectedInList();
    updateUrl(null);
    setReadingMode(true);
    renderPane();
    document.getElementById('note-title-input')?.focus();
}

function startEdit() {
    const note = notesState.current;
    if (!note || !(note.capabilities || {}).edit) return;

    notesState.mode = 'edit';
    notesState.preview = false;
    notesState.conflict = null;
    notesState.baseRevision = note.revision;
    notesState.draft = {
        title: note.title || '',
        content: note.content || '',
        app_id: note.app_id ? String(note.app_id) : ''
    };
    notesState.original = { ...notesState.draft };
    renderPane();

    const content = document.getElementById('note-content-input');
    if (content) {
        content.focus();
        content.setSelectionRange(content.value.length, content.value.length);
    }
}

function cancelEdit() {
    if (!confirmDiscardChanges()) return;
    const wasNew = notesState.mode === 'new';
    notesState.draft = null;
    notesState.conflict = null;

    if (wasNew || !notesState.current) {
        notesState.mode = 'empty';
        setReadingMode(false);
    } else {
        notesState.mode = 'read';
    }
    renderPane();
}

function isDirty() {
    if (!notesState.draft || !notesState.original) return false;
    if (notesState.mode !== 'edit' && notesState.mode !== 'new') return false;
    return ['title', 'content', 'app_id'].some(key => String(notesState.draft[key] ?? '') !== String(notesState.original[key] ?? ''));
}

// true si se puede seguir (no hay cambios o la persona acepta perderlos).
function confirmDiscardChanges() {
    if (!isDirty()) return true;
    const ok = window.confirm('Tienes cambios sin guardar en esta nota. ¿Descartarlos?');
    if (ok) notesState.draft = null;
    return ok;
}

// ========== Guardar ==========

async function saveNote() {
    if (notesState.saving || !notesState.draft) return;
    const draft = notesState.draft;
    const title = draft.title.trim();

    if (!title) {
        showTitleError(true);
        document.getElementById('note-title-input')?.focus();
        return;
    }

    const isNew = notesState.mode === 'new';
    const payload = { title, content: draft.content, app_id: draft.app_id ? Number(draft.app_id) : null };
    if (isNew) {
        payload.is_shared = !!draft.is_shared;
        payload.shared_user_ids = draft.shared_user_ids || [];
    } else {
        payload.id = notesState.current.id;
        payload.expected_revision = notesState.baseRevision;
    }

    setSaving(true);
    try {
        const response = await fetch('/api/notes.php', {
            method: isNew ? 'POST' : 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await response.json();

        if (response.status === 409 && data.conflict) {
            // Otra persona guardó antes: se conserva el texto y se pregunta qué hacer.
            notesState.conflict = data.data;
            renderPane();
            return;
        }
        if (!data.success) {
            console.error('Error saving note', payload.id || 'new', response.status, data.error);
            showErrorToast(data.error || 'No se pudo guardar la nota');
            return;
        }

        notesState.current = data.data;
        notesState.selectedId = Number(data.data.id);
        notesState.mode = 'read';
        notesState.draft = null;
        notesState.conflict = null;
        updateUrl(notesState.selectedId);
        renderPane();
        loadNotes();
        showToast({ title: isNew ? 'Nota creada' : 'Nota guardada', icon: 'iconoir-check' });
    } catch (error) {
        console.error('Error saving note', payload.id || 'new', error);
        showErrorToast('No se pudo guardar la nota. Revisa la conexión.');
    } finally {
        setSaving(false);
    }
}

// choice: 'theirs' (ver la versión guardada y descartar la mía) | 'mine' (guardar encima)
function resolveConflict(choice) {
    const server = notesState.conflict;
    if (!server) return;

    if (choice === 'theirs') {
        notesState.current = server;
        notesState.mode = 'read';
        notesState.draft = null;
        notesState.conflict = null;
        renderPane();
        loadNotes();
        return;
    }

    notesState.current = server;
    notesState.baseRevision = server.revision;
    notesState.conflict = null;
    renderPane();
    saveNote();
}

function setSaving(saving) {
    notesState.saving = saving;
    const btn = document.getElementById('note-save-btn');
    if (!btn) return;
    btn.disabled = saving;
    btn.querySelector('span').textContent = saving ? 'Guardando…' : 'Guardar';
}

function showTitleError(show) {
    const input = document.getElementById('note-title-input');
    const error = document.getElementById('note-title-error');
    if (!input || !error) return;
    error.hidden = !show;
    if (show) input.setAttribute('aria-invalid', 'true');
    else input.removeAttribute('aria-invalid');
}

// ========== Fijar y eliminar ==========

async function togglePin() {
    const note = notesState.current;
    if (!note) return;
    const pinned = Number(note.is_pinned) !== 1;

    try {
        const response = await fetch('/api/notes.php', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: note.id, is_pinned: pinned })
        });
        const data = await response.json();
        if (!data.success) {
            console.error('Error pinning note', note.id, response.status, data.error);
            showErrorToast(data.error || 'No se pudo fijar la nota');
            return;
        }
        notesState.current = data.data;
        renderPane();
        loadNotes();
    } catch (error) {
        console.error('Error pinning note', note.id, error);
        showErrorToast('No se pudo fijar la nota');
    }
}

async function deleteCurrentNote() {
    const note = notesState.current;
    if (!note) return;
    if (!window.confirm(`¿Eliminar "${note.title}"? No se puede deshacer.`)) return;

    try {
        const response = await fetch('/api/notes.php', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: note.id })
        });
        const data = await response.json();
        if (!data.success) {
            console.error('Error deleting note', note.id, response.status, data.error);
            showErrorToast(data.error || 'No se pudo eliminar la nota');
            return;
        }
        notesState.current = null;
        notesState.selectedId = null;
        notesState.mode = 'empty';
        updateUrl(null);
        setReadingMode(false);
        renderPane();
        loadNotes();
        showToast({ title: 'Nota eliminada', icon: 'iconoir-trash' });
    } catch (error) {
        console.error('Error deleting note', note.id, error);
        showErrorToast('No se pudo eliminar la nota');
    }
}

// ========== Panel derecho ==========

function renderPane() {
    const pane = document.getElementById('note-pane');
    const mode = notesState.mode;
    const key = `${mode}:${notesState.current?.id || ''}`;
    const entering = key !== lastPaneKey;
    lastPaneKey = key;

    let html;
    if (mode === 'loading') html = paneLoadingHtml();
    else if (mode === 'read') html = readerHtml(notesState.current);
    else if (mode === 'edit' || mode === 'new') html = editorHtml();
    else if (mode === 'missing') html = paneMessageHtml('iconoir-eye-closed', 'Esta nota no está disponible', 'Puede que se haya borrado o que ya no esté compartida contigo.', null);
    else if (mode === 'error') html = paneMessageHtml('iconoir-warning-circle', 'No se pudo abrir la nota', 'Comprueba la conexión e inténtalo de nuevo.', { name: 'retry-note', label: 'Reintentar' });
    else html = emptyPaneHtml();

    pane.innerHTML = `<div class="note-pane-inner${entering ? ' is-entering' : ''}">${html}</div>`;

    if (mode === 'edit' || mode === 'new') hydrateEditor();
}

function emptyPaneHtml() {
    const hasNotes = notesState.list.length > 0 || hasListFilters();
    return `
        <div class="note-placeholder">
            <span class="notes-message-icon"><i class="iconoir-page" aria-hidden="true"></i></span>
            <p class="notes-message-title">${hasNotes ? 'Elige una nota de la lista' : 'Aquí se abrirán tus notas'}</p>
            <p class="notes-message-text">Pulsa <kbd class="notes-kbd">N</kbd> para crear una nueva.</p>
        </div>`;
}

function paneLoadingHtml() {
    return `
        <div class="note-pane-skeleton" aria-busy="true" aria-label="Cargando nota">
            <span class="skeleton-line skeleton-line--heading"></span>
            <span class="skeleton-line skeleton-line--short"></span>
            <span class="skeleton-line"></span>
            <span class="skeleton-line"></span>
            <span class="skeleton-line skeleton-line--medium"></span>
        </div>`;
}

function paneMessageHtml(icon, title, text, action) {
    return `
        ${backButtonHtml()}
        <div class="note-placeholder">
            <span class="notes-message-icon"><i class="${icon}" aria-hidden="true"></i></span>
            <p class="notes-message-title">${title}</p>
            <p class="notes-message-text">${text}</p>
            ${action ? `<button type="button" class="btn btn-secondary btn-sm" data-action="${action.name}">${action.label}</button>` : ''}
        </div>`;
}

function backButtonHtml() {
    return `<button type="button" class="note-back" data-action="back"><i class="iconoir-nav-arrow-left" aria-hidden="true"></i> Notas</button>`;
}

function shareChipHtml(target, canShare) {
    const isOwner = canShare;
    const count = Array.isArray(target.shared_users) ? target.shared_users.length : (target.shared_user_ids || []).length;
    const share = noteShareSummary({ is_shared: target.is_shared ? 1 : 0, share_count: count }, isOwner);

    if (!canShare) {
        return `<span class="chip chip--lg note-chip" title="Solo quien la creó cambia con quién se comparte"><i class="${share.icon}" aria-hidden="true"></i>${share.label}</span>`;
    }
    return `
        <button type="button" class="chip chip--lg note-chip note-chip--button" data-action="share" aria-haspopup="menu" aria-expanded="false" title="Cambiar con quién se comparte">
            <i class="${share.icon}" aria-hidden="true"></i>
            <span>${share.label}</span>
            <i class="iconoir-nav-arrow-down note-chip-caret" aria-hidden="true"></i>
        </button>`;
}

function readerHtml(note) {
    const caps = note.capabilities || {};
    const isOwner = !!caps.share;
    const pinned = Number(note.is_pinned) === 1;
    const body = renderNoteMarkdown(note.content);

    return `
        <div class="note-toolbar">
            ${backButtonHtml()}
            <div class="note-toolbar-meta">
                ${shareChipHtml(note, isOwner)}
                ${note.app_name ? `<span class="chip chip--lg note-chip"><i class="iconoir-app-window" aria-hidden="true"></i>${escapeHtml(note.app_name)}</span>` : ''}
            </div>
            <div class="note-toolbar-actions">
                ${caps.edit ? `
                    <button type="button" class="note-icon-btn${pinned ? ' is-on' : ''}" data-action="pin" aria-pressed="${pinned ? 'true' : 'false'}" title="${pinned ? 'Quitar de fijadas' : 'Fijar arriba'}">
                        <i class="iconoir-pin" aria-hidden="true"></i><span class="visually-hidden">${pinned ? 'Quitar de fijadas' : 'Fijar arriba'}</span>
                    </button>` : ''}
                ${caps.delete ? `
                    <button type="button" class="note-icon-btn note-icon-btn--danger" data-action="delete" title="Eliminar nota">
                        <i class="iconoir-trash" aria-hidden="true"></i><span class="visually-hidden">Eliminar nota</span>
                    </button>` : ''}
                ${caps.edit ? `
                    <button type="button" class="btn btn-secondary btn-sm" data-action="edit" title="Editar (E)">
                        <i class="iconoir-edit-pencil" aria-hidden="true"></i> Editar
                    </button>` : ''}
            </div>
        </div>
        <article class="note-article">
            <h2 class="note-title" id="note-heading" tabindex="-1">${escapeHtml(note.title)}</h2>
            <p class="note-byline">${bylineHtml(note, isOwner)}</p>
            ${body
                ? `<div class="note-body markdown-body">${body}</div>`
                : `<p class="note-body-empty">Esta nota está vacía.${caps.edit ? ' Pulsa Editar para escribir en ella.' : ''}</p>`}
        </article>`;
}

function bylineHtml(note, isOwner) {
    const author = isOwner ? 'Creada por ti' : `De ${escapeHtml(note.author_name || 'otra persona')}`;
    const when = formatNoteTime(note.updated_at, serverClock(note));
    if (!when) return author;

    const relative = /^\d/.test(when) ? `el ${when}` : when.charAt(0).toLowerCase() + when.slice(1);
    const editor = Number(note.updated_by) && Number(note.updated_by) !== currentUserId && note.updated_by_name
        ? ` por ${escapeHtml(note.updated_by_name)}`
        : '';
    return `${author}<span class="note-byline-sep" aria-hidden="true"></span><span title="${formatNoteDateTime(note.updated_at)}">Editada ${relative}${editor}</span>`;
}

function editorHtml() {
    const isNew = notesState.mode === 'new';
    const note = notesState.current;
    const canShare = isNew || !!(note?.capabilities || {}).share;
    const shareTarget = isNew ? notesState.draft : note;
    const conflict = notesState.conflict;
    const shortcut = IS_MAC ? '⌘ Enter' : 'Ctrl + Enter';

    return `
        <div class="note-toolbar">
            ${backButtonHtml()}
            <div class="note-toolbar-meta">
                ${shareChipHtml(shareTarget, canShare)}
                ${NOTE_APPS.length ? `
                    <label class="visually-hidden" for="note-app-select">Aplicación</label>
                    <select id="note-app-select" class="note-app-select">
                        <option value="">Sin aplicación</option>
                        ${NOTE_APPS.map(app => `<option value="${app.id}">${escapeHtml(app.name)}</option>`).join('')}
                    </select>` : ''}
            </div>
            <div class="note-editor-tabs" role="tablist" aria-label="Modo del editor">
                <button type="button" class="notes-filter-btn${notesState.preview ? '' : ' is-active'}" data-action="write" role="tab" aria-selected="${notesState.preview ? 'false' : 'true'}">Escribir</button>
                <button type="button" class="notes-filter-btn${notesState.preview ? ' is-active' : ''}" data-action="preview" role="tab" aria-selected="${notesState.preview ? 'true' : 'false'}">Vista previa</button>
            </div>
        </div>
        ${conflict ? `
            <div class="note-conflict" role="alert">
                <i class="iconoir-warning-triangle" aria-hidden="true"></i>
                <div class="note-conflict-body">
                    <p class="note-conflict-title">${escapeHtml(conflict.updated_by_name || 'Otra persona')} ha cambiado esta nota mientras la editabas.</p>
                    <p class="note-conflict-text">Tu texto sigue aquí. Elige con qué versión te quedas.</p>
                    <div class="note-conflict-actions">
                        <button type="button" class="btn btn-secondary btn-sm" data-action="conflict-theirs" title="Descarta tus cambios">Ver su versión</button>
                        <button type="button" class="btn btn-primary btn-sm" data-action="conflict-mine" title="Sustituye su versión por la tuya">Guardar la mía</button>
                    </div>
                </div>
            </div>` : ''}
        <div class="note-editor">
            <label class="visually-hidden" for="note-title-input">Título</label>
            <input type="text" id="note-title-input" class="note-title-input" maxlength="255" placeholder="Título" autocomplete="off" aria-describedby="note-title-error">
            <p class="note-field-error" id="note-title-error" hidden>Ponle un título a la nota.</p>
            <label class="visually-hidden" for="note-content-input">Contenido</label>
            <textarea id="note-content-input" class="note-content-input" placeholder="Escribe aquí. Admite Markdown: # títulos, **negrita**, listas con -" ${notesState.preview ? 'hidden' : ''}></textarea>
            <div class="note-preview markdown-body" id="note-preview" ${notesState.preview ? '' : 'hidden'}></div>
        </div>
        <div class="note-editor-footer">
            <span class="note-editor-hint"><kbd class="notes-kbd">${shortcut}</kbd> para guardar</span>
            <span class="note-unsaved" id="note-unsaved" hidden>Cambios sin guardar</span>
            <button type="button" class="btn btn-ghost" data-action="cancel">Cancelar</button>
            <button type="button" class="btn btn-primary" id="note-save-btn" data-action="save">
                <i class="iconoir-check" aria-hidden="true"></i><span>Guardar</span>
            </button>
        </div>`;
}

// Rellena el editor desde el borrador (sin pasar los textos por el HTML).
function hydrateEditor() {
    const draft = notesState.draft;
    if (!draft) return;
    const title = document.getElementById('note-title-input');
    const content = document.getElementById('note-content-input');
    const app = document.getElementById('note-app-select');

    title.value = draft.title;
    content.value = draft.content;
    if (app) app.value = draft.app_id || '';
    if (notesState.preview) renderPreview();
    autoGrow(content);
    updateDirtyHint();
}

function renderPreview() {
    const preview = document.getElementById('note-preview');
    if (!preview) return;
    const html = renderNoteMarkdown(notesState.draft?.content);
    preview.innerHTML = html || '<p class="note-body-empty">Nada que mostrar todavía.</p>';
}

function setPreview(on) {
    notesState.preview = on;
    document.querySelectorAll('.note-editor-tabs .notes-filter-btn').forEach(btn => {
        const active = (btn.dataset.action === 'preview') === on;
        btn.classList.toggle('is-active', active);
        btn.setAttribute('aria-selected', active ? 'true' : 'false');
    });
    document.getElementById('note-content-input').hidden = on;
    document.getElementById('note-preview').hidden = !on;
    if (on) renderPreview();
    else document.getElementById('note-content-input').focus();
}

function handlePaneClick(e) {
    const action = e.target.closest('[data-action]');
    if (!action) return;

    switch (action.dataset.action) {
        case 'back': goBackToList(); break;
        case 'edit': startEdit(); break;
        case 'pin': togglePin(); break;
        case 'delete': deleteCurrentNote(); break;
        case 'share': openNoteShareMenu(action); break;
        case 'save': saveNote(); break;
        case 'cancel': cancelEdit(); break;
        case 'write': setPreview(false); break;
        case 'preview': setPreview(true); break;
        case 'conflict-theirs': resolveConflict('theirs'); break;
        case 'conflict-mine': resolveConflict('mine'); break;
        case 'retry-note': if (notesState.selectedId) selectNote(Number(notesState.selectedId)); break;
    }
}

function handleEditorInput(e) {
    const draft = notesState.draft;
    if (!draft) return;

    if (e.target.id === 'note-title-input') {
        draft.title = e.target.value;
        if (draft.title.trim()) showTitleError(false);
    } else if (e.target.id === 'note-content-input') {
        draft.content = e.target.value;
        autoGrow(e.target);
    } else if (e.target.id === 'note-app-select') {
        draft.app_id = e.target.value;
    } else {
        return;
    }
    updateDirtyHint();
}

function handleEditorKeydown(e) {
    if (notesState.mode !== 'edit' && notesState.mode !== 'new') return;
    const mod = e.metaKey || e.ctrlKey;

    if (mod && (e.key === 'Enter' || e.key.toLowerCase() === 's')) {
        e.preventDefault();
        saveNote();
    } else if (e.key === 'Enter' && e.target.id === 'note-title-input') {
        e.preventDefault();
        document.getElementById('note-content-input')?.focus();
    } else if (e.key === 'Escape' && !activeMenu) {
        e.preventDefault();
        cancelEdit();
    }
}

function updateDirtyHint() {
    const hint = document.getElementById('note-unsaved');
    if (hint) hint.hidden = !isDirty();
}

function autoGrow(textarea) {
    if (!textarea) return;
    textarea.style.height = 'auto';
    textarea.style.height = `${textarea.scrollHeight + 2}px`;
}

function goBackToList() {
    if (notesState.mode === 'edit' || notesState.mode === 'new') {
        if (!confirmDiscardChanges()) return;
        notesState.draft = null;
        notesState.conflict = null;
        notesState.mode = notesState.current ? 'read' : 'empty';
        renderPane();
    }
    setReadingMode(false);
}

// En móvil se ve la lista o la nota, no las dos (y se baja hasta ella).
function setReadingMode(on) {
    const workspace = document.getElementById('notes-workspace');
    const wasReading = workspace.classList.contains('is-reading');
    workspace.classList.toggle('is-reading', on);
    if (on && !wasReading && window.matchMedia('(max-width: 900px)').matches) {
        workspace.scrollIntoView({ block: 'start' });
    }
}

function focusPaneHeading() {
    const heading = document.getElementById('note-heading');
    if (heading) heading.focus({ preventScroll: true });
}

function updateUrl(id) {
    history.replaceState(null, '', id ? `/notes.php?id=${id}` : '/notes.php');
}

// ========== Compartir ==========

async function loadTeam() {
    try {
        const response = await fetch('/api/task-shares.php');
        const data = await response.json();
        if (!data.success) throw new Error(`${response.status} ${data.error}`);
        teamMembers = data.data;
    } catch (error) {
        console.error('Error loading team members:', error);
    }
}

function openNoteShareMenu(anchor) {
    if (activeMenu) {
        closeNotesMenu();
        return;
    }

    const isDraft = notesState.mode === 'new';
    const note = notesState.current;
    const state = isDraft
        ? { all: !!notesState.draft.is_shared, users: (notesState.draft.shared_user_ids || []).slice() }
        : { all: Number(note.is_shared) === 1, users: (note.shared_users || []).map(u => Number(u.id)) };

    const items = [
        { key: 'private', icon: 'iconoir-lock', label: 'Solo yo' },
        { key: 'all', icon: 'iconoir-group', label: 'Todo el equipo' }
    ];
    const members = teamMembers.map(m => ({ key: `user-${m.id}`, id: Number(m.id), label: m.full_name || m.username }));

    const menu = document.createElement('div');
    menu.className = 'notes-menu';
    menu.setAttribute('role', 'menu');
    menu.setAttribute('aria-label', 'Compartir con');
    menu.innerHTML = `
        <p class="notes-menu-title">Compartir con</p>
        ${items.map(item => menuItemHtml(item.key, `<i class="${item.icon}" aria-hidden="true"></i>`, item.label)).join('')}
        <div class="notes-menu-sep" role="separator"></div>
        ${members.length
            ? members.map(m => menuItemHtml(m.key, `<span class="notes-menu-avatar" aria-hidden="true">${escapeHtml(getInitials(m.label))}</span>`, escapeHtml(m.label))).join('')
            : '<p class="notes-menu-note">No hay más personas en el equipo</p>'}
    `;
    document.body.appendChild(menu);
    positionMenu(menu, anchor);

    const refresh = () => {
        menu.querySelectorAll('[data-key]').forEach(btn => {
            const key = btn.dataset.key;
            const checked = key === 'private' ? (!state.all && state.users.length === 0)
                : key === 'all' ? state.all
                : state.users.includes(Number(key.replace('user-', '')));
            btn.classList.toggle('is-active', checked);
            btn.setAttribute('aria-checked', checked ? 'true' : 'false');
        });
    };

    const change = (next) => {
        Object.assign(state, next);
        if (isDraft) {
            notesState.draft.is_shared = state.all;
            notesState.draft.shared_user_ids = state.users.slice();
        } else {
            note.is_shared = state.all ? 1 : 0;
            note.shared_users = state.all ? [] : teamMembers.filter(m => state.users.includes(Number(m.id)));
            saveShareState(note.id, { all: state.all, users: state.users.slice() });
        }
        refresh();
        refreshShareChip();
    };

    menu.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-key]');
        if (!btn) return;
        const key = btn.dataset.key;
        if (key === 'private') change({ all: false, users: [] });
        else if (key === 'all') change({ all: !state.all, users: [] });
        else {
            const id = Number(key.replace('user-', ''));
            change({ all: false, users: state.users.includes(id) ? state.users.filter(u => u !== id) : state.users.concat(id) });
        }
    });

    menu.addEventListener('keydown', (e) => {
        const buttons = Array.from(menu.querySelectorAll('[data-key]'));
        const index = buttons.indexOf(document.activeElement);
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            e.preventDefault();
            const next = e.key === 'ArrowDown' ? (index + 1) % buttons.length : (index - 1 + buttons.length) % buttons.length;
            buttons[next].focus();
        } else if (e.key === 'Escape' || e.key === 'Tab') {
            if (e.key === 'Escape') e.preventDefault();
            closeNotesMenu(true);
        }
    });

    const onOutside = (e) => {
        if (!menu.contains(e.target) && !anchor.contains(e.target)) closeNotesMenu();
    };
    setTimeout(() => document.addEventListener('mousedown', onOutside), 0);
    window.addEventListener('resize', closeNotesMenu, { once: true });

    activeMenu = { menu, anchor, onOutside };
    anchor.setAttribute('aria-expanded', 'true');
    refresh();
    menu.querySelector('[data-key]')?.focus();
}

function menuItemHtml(key, lead, label) {
    return `
        <button type="button" class="notes-menu-item" role="menuitemcheckbox" aria-checked="false" data-key="${key}">
            ${lead}<span class="notes-menu-label">${label}</span>
            <i class="iconoir-check notes-menu-check" aria-hidden="true"></i>
        </button>`;
}

function positionMenu(menu, anchor) {
    const rect = anchor.getBoundingClientRect();
    const width = menu.offsetWidth;
    const left = Math.max(12, Math.min(rect.left, window.innerWidth - width - 12));
    const spaceBelow = window.innerHeight - rect.bottom;
    menu.style.left = `${left}px`;
    if (spaceBelow < Math.min(menu.offsetHeight, 320) + 16 && rect.top > spaceBelow) {
        menu.style.bottom = `${window.innerHeight - rect.top + 6}px`;
    } else {
        menu.style.top = `${rect.bottom + 6}px`;
    }
}

function closeNotesMenu(returnFocus) {
    if (!activeMenu) return;
    const { menu, anchor, onOutside } = activeMenu;
    document.removeEventListener('mousedown', onOutside);
    menu.remove();
    activeMenu = null;
    if (document.body.contains(anchor)) {
        anchor.setAttribute('aria-expanded', 'false');
        if (returnFocus === true) anchor.focus();
    }
}

// Cambia la etiqueta del chip sin repintar el panel (no se pierde lo que se está escribiendo).
function refreshShareChip() {
    const chip = document.querySelector('#note-pane [data-action="share"]');
    if (!chip) return;
    const target = notesState.mode === 'new' ? notesState.draft : notesState.current;
    const wrapper = document.createElement('div');
    wrapper.innerHTML = shareChipHtml(target, true);
    const fresh = wrapper.firstElementChild;
    fresh.setAttribute('aria-expanded', chip.getAttribute('aria-expanded'));
    chip.replaceWith(fresh);
    if (activeMenu) activeMenu.anchor = fresh;
}

function saveShareState(noteId, state) {
    const payload = { id: noteId, is_shared: state.all, shared_user_ids: state.users };

    shareSaveChain = shareSaveChain.then(async () => {
        try {
            const response = await fetch('/api/notes.php', {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await response.json();
            if (!data.success) throw new Error(`${response.status} ${data.error}`);
            if (notesState.current && Number(notesState.current.id) === Number(noteId)) {
                notesState.current.is_shared = data.data.is_shared;
                notesState.current.shared_users = data.data.shared_users;
            }
            loadNotes();
        } catch (error) {
            console.error('Error saving note shares', payload, error);
            showErrorToast('No se pudo cambiar con quién se comparte');
            if (notesState.current && Number(notesState.current.id) === Number(noteId) && notesState.mode === 'read') {
                selectNote(Number(noteId));
            }
        }
    });
}

// ========== Atajos ==========

function handleGlobalKeydown(e) {
    if (e.defaultPrevented || e.metaKey || e.ctrlKey || e.altKey) return;
    const tag = (e.target.tagName || '').toLowerCase();
    const typing = tag === 'input' || tag === 'textarea' || tag === 'select' || e.target.isContentEditable;

    if (e.key === 'Escape' && activeMenu) {
        closeNotesMenu(true);
        return;
    }
    if (typing) return;

    if (e.key === '/') {
        e.preventDefault();
        document.getElementById('notes-search').focus();
    } else if (e.key === 'n' || e.key === 'N') {
        e.preventDefault();
        startNewNote();
    } else if ((e.key === 'e' || e.key === 'E') && notesState.mode === 'read') {
        e.preventDefault();
        startEdit();
    }
}

// ========== Utilidades ==========

// Reloj de la base de datos (server_now): "Hace X" no depende de la zona horaria del servidor.
function serverClock(note) {
    return parseNoteDate(note && note.server_now) || new Date();
}

// Markdown seguro: DOMPurify limpia el HTML y los enlaces se abren en otra pestaña.
function renderNoteMarkdown(text) {
    const raw = text || '';
    if (!raw.trim()) return '';
    if (typeof marked === 'undefined' || typeof DOMPurify === 'undefined') {
        return `<p>${escapeHtml(raw).replace(/\n/g, '<br>')}</p>`;
    }
    try {
        const template = document.createElement('template');
        template.innerHTML = DOMPurify.sanitize(marked.parse(raw, { breaks: true, gfm: true }));
        template.content.querySelectorAll('a[href]').forEach(link => {
            link.target = '_blank';
            link.rel = 'noopener noreferrer';
        });
        return template.innerHTML;
    } catch (error) {
        console.error('Error rendering note markdown', error);
        return `<p>${escapeHtml(raw).replace(/\n/g, '<br>')}</p>`;
    }
}

function escapeHtml(text) {
    if (text === null || text === undefined) return '';
    const div = document.createElement('div');
    div.textContent = String(text);
    return div.innerHTML;
}

function getInitials(name) {
    if (!name) return '?';
    return name.trim().split(/\s+/).map(part => part[0]).join('').toUpperCase().substring(0, 2);
}

// config: { title, message, icon }
function showToast(config, className = '') {
    const container = document.getElementById('toast-container');
    if (!container) return;

    const toast = document.createElement('div');
    toast.className = `toast ${className}`;
    toast.setAttribute('role', className === 'toast-error' ? 'alert' : 'status');
    toast.innerHTML = `
        <div class="toast-icon"><i class="${config.icon || 'iconoir-check'}" aria-hidden="true"></i></div>
        <div class="toast-content">
            <div class="toast-title">${escapeHtml(config.title || '')}</div>
            ${config.message ? `<div class="toast-message">${escapeHtml(config.message)}</div>` : ''}
        </div>`;
    container.appendChild(toast);

    setTimeout(() => toast.classList.add('show'), 10);
    setTimeout(() => {
        toast.classList.add('toast-hide');
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

function showErrorToast(message) {
    showToast({ title: 'Error', message, icon: 'iconoir-warning-circle' }, 'toast-error');
}
