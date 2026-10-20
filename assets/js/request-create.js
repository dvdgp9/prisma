/**
 * Nueva mejora: modal de creación con el lenguaje del modal de tareas
 * (barra de chips, título grande, descripción que crece, adjuntos con
 * arrastrar o pegar, foco atrapado, Esc y Ctrl/Cmd+Enter).
 *
 * Depende de main.js (apps, availableUsers, loadAvailableUsers, currentView,
 * currentAppId, userRole, showToast, formatFileSize, getFileIcon,
 * loadRequests, updateRequesterDot), de work-helpers.js
 * (workPriorityMenuOptions, workDateMenuOptions, requestDueInfo) y de
 * request-create-helpers.js (lo puro: payload, etiquetas, filtros).
 */

// Lo elegido con los chips, los archivos y los pasos; el texto vive en sus campos.
let newRequestState = newRequestDraft({});
// Menú abierto de un chip: { menu, anchor, chip, cleanup }.
let newRequestMenuState = null;
let newRequestReturnFocus = null;
let newRequestSubmitting = false;
// Se intentó crear sin aplicación: el chip se marca en rojo hasta elegirla.
let newRequestAppInvalid = false;
// Miniaturas de las imágenes elegidas (URL.createObjectURL), para liberarlas al quitar o cerrar.
const newRequestPreviewUrls = new Map();

document.addEventListener('DOMContentLoaded', setupNewRequestModal);

function newRequestEl(id) {
    return document.getElementById(id);
}

// escapeHtml de main.js no escapa comillas: aquí se pinta también dentro de atributos.
function newRequestEscape(text) {
    return String(text ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function isNewRequestModalOpen() {
    const modal = newRequestEl('new-request-modal');
    return !!modal && modal.classList.contains('active');
}

function newRequestFields() {
    return {
        title: newRequestEl('request-title').value,
        description: newRequestEl('request-description').value,
        requesterName: newRequestEl('new-request-requester-name').value,
        requesterEmail: newRequestEl('new-request-requester-email').value
    };
}

function newRequestCan() {
    return newRequestPermissions(typeof userRole !== 'undefined' ? userRole : '');
}

function setupNewRequestModal() {
    const modal = newRequestEl('new-request-modal');
    if (!modal) return;
    const content = newRequestEl('new-request-content');
    const upload = newRequestEl('new-request-upload');
    const fileInput = newRequestEl('new-request-file-input');

    upload.addEventListener('click', () => fileInput.click());
    upload.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            fileInput.click();
        }
    });
    fileInput.addEventListener('change', () => {
        addNewRequestFiles(fileInput.files);
        fileInput.value = '';
    });

    // Soltar archivos en cualquier parte del modal.
    const hasFiles = (e) => !!e.dataTransfer && Array.from(e.dataTransfer.types || []).includes('Files');
    let dragDepth = 0;
    content.addEventListener('dragenter', (e) => {
        if (!hasFiles(e) || !newRequestCan().attach) return;
        e.preventDefault();
        dragDepth++;
        content.classList.add('is-dragging');
    });
    content.addEventListener('dragover', (e) => {
        if (hasFiles(e) && newRequestCan().attach) e.preventDefault();
    });
    content.addEventListener('dragleave', () => {
        dragDepth = Math.max(0, dragDepth - 1);
        if (dragDepth === 0) content.classList.remove('is-dragging');
    });
    content.addEventListener('drop', (e) => {
        if (!hasFiles(e) || !newRequestCan().attach) return;
        e.preventDefault();
        dragDepth = 0;
        content.classList.remove('is-dragging');
        addNewRequestFiles(e.dataTransfer.files);
    });

    content.addEventListener('paste', handleNewRequestPaste);

    // Clic en el fondo: solo si empieza y termina fuera del contenido (así no se cierra al
    // seleccionar texto y soltar fuera). Se corta aquí para que el cierre genérico de
    // main.js no lo cierre sin avisar de lo escrito.
    let pressedOnBackdrop = false;
    modal.addEventListener('mousedown', (e) => { pressedOnBackdrop = e.target === modal; });
    modal.addEventListener('click', (e) => {
        if (e.target !== modal) return;
        e.stopPropagation();
        if (pressedOnBackdrop) requestCloseNewRequestModal();
        pressedOnBackdrop = false;
    });

    document.addEventListener('keydown', handleNewRequestKeydown);

    newRequestEl('request-title').addEventListener('input', (e) => e.target.setCustomValidity(''));
    newRequestEl('request-description').addEventListener('input', autoGrowNewRequestDescription);
    newRequestEl('new-requester-toggle').addEventListener('click', toggleNewRequester);

    newRequestEl('new-request-bar').addEventListener('click', (e) => {
        const chip = e.target.closest('[data-chip]');
        if (chip) openNewRequestChipMenu(chip.dataset.chip, chip);
    });

    const checklistInput = newRequestEl('new-checklist-input');
    checklistInput.addEventListener('keydown', (e) => {
        // Enter añade el paso; Ctrl/Cmd+Enter sigue creando la mejora.
        if (e.key === 'Enter' && !e.metaKey && !e.ctrlKey) {
            e.preventDefault();
            addNewRequestChecklistFromInput();
        }
    });
    newRequestEl('new-checklist-add').addEventListener('click', addNewRequestChecklistFromInput);
    newRequestEl('new-checklist-list').addEventListener('click', (e) => {
        const btn = e.target.closest('[data-checklist-index]');
        if (!btn) return;
        newRequestState.checklist = newRequestState.checklist.filter((_, i) => i !== Number(btn.dataset.checklistIndex));
        renderNewRequestChecklist();
        checklistInput.focus();
    });
    newRequestEl('new-request-file-list').addEventListener('click', (e) => {
        const btn = e.target.closest('[data-file-index]');
        if (btn) removeNewRequestFile(Number(btn.dataset.fileIndex));
    });

    if (/Mac|iPhone|iPad/.test(navigator.platform || navigator.userAgent || '')) {
        newRequestEl('new-request-mod-key').textContent = '⌘';
    }
}

// ========== Abrir y cerrar ==========

function openNewRequestModal() {
    if (isNewRequestModalOpen()) return;
    newRequestReturnFocus = document.activeElement;
    resetNewRequestModal();
    newRequestState.appId = newRequestDefaultApp(apps, currentView, currentAppId);
    newRequestEl('new-request-attachments').hidden = !newRequestCan().attach;
    renderNewRequestBar();

    newRequestEl('new-request-modal').classList.add('active');
    // El "+" de tarea rápida tiene el mismo z-index y taparía el pie (en móvil, "Crear mejora").
    document.body.classList.add('is-creating-request');
    autoGrowNewRequestDescription();
    newRequestEl('request-title').focus();
}

// Deja el modal vacío (también lo llama closeModal() de main.js).
function resetNewRequestModal() {
    closeNewRequestMenu();
    newRequestPreviewUrls.forEach(url => URL.revokeObjectURL(url));
    newRequestPreviewUrls.clear();
    newRequestState = newRequestDraft({});
    newRequestAppInvalid = false;

    newRequestEl('new-request-form').reset();
    newRequestEl('request-title').setCustomValidity('');
    newRequestEl('request-description').style.height = '';
    newRequestEl('new-requester-section').classList.remove('active');
    newRequestEl('new-requester-toggle').setAttribute('aria-expanded', 'false');
    newRequestEl('new-request-content').classList.remove('is-dragging');
    if (!isNewRequestModalOpen()) document.body.classList.remove('is-creating-request');
    updateRequesterDot('new');
    setNewRequestSubmitting(false);

    renderNewRequestBar();
    renderNewRequestFiles();
    renderNewRequestChecklist();
}

// Cerrar desde X, Cancelar, Esc o el fondo: avisa si hay algo escrito.
function requestCloseNewRequestModal() {
    if (newRequestSubmitting) return;
    if (newRequestIsDirty(newRequestState, newRequestFields())
        && !confirm('¿Descartar esta mejora? Se perderá lo que has escrito.')) {
        return;
    }
    closeNewRequestModal();
}

function closeNewRequestModal() {
    newRequestEl('new-request-modal').classList.remove('active');
    document.body.classList.remove('is-creating-request');
    resetNewRequestModal();

    const returnFocus = newRequestReturnFocus;
    newRequestReturnFocus = null;
    if (returnFocus && document.contains(returnFocus) && typeof returnFocus.focus === 'function') {
        returnFocus.focus({ preventScroll: true });
    }
}

function handleNewRequestKeydown(e) {
    if (!isNewRequestModalOpen() || e.defaultPrevented) return;
    // Los menús de los chips gestionan sus propias teclas.
    if (newRequestMenuState) return;

    if (e.key === 'Escape') {
        e.preventDefault();
        requestCloseNewRequestModal();
    } else if (e.key === 'Enter' && (e.metaKey || e.ctrlKey)) {
        e.preventDefault();
        newRequestEl('new-request-form').requestSubmit();
    } else if (e.key === 'Tab') {
        trapNewRequestFocus(e);
    }
}

// Mantiene el foco dentro del modal con Tab / Shift+Tab.
function trapNewRequestFocus(e) {
    const focusables = Array.from(document.querySelectorAll(
        '#new-request-content button:not([disabled]), #new-request-content a[href], #new-request-content input:not([type="hidden"]):not(.request-create-file-input), #new-request-content textarea, #new-request-content [tabindex="0"]'
    )).filter(el => el.offsetParent !== null);
    if (!focusables.length) return;

    const first = focusables[0];
    const last = focusables[focusables.length - 1];
    if (!focusables.includes(document.activeElement)) {
        e.preventDefault();
        first.focus();
    } else if (e.shiftKey && document.activeElement === first) {
        e.preventDefault();
        last.focus();
    } else if (!e.shiftKey && document.activeElement === last) {
        e.preventDefault();
        first.focus();
    }
}

// La descripción crece con el texto (hasta la mitad de la pantalla).
function autoGrowNewRequestDescription() {
    const textarea = newRequestEl('request-description');
    textarea.style.height = 'auto';
    textarea.style.height = `${Math.min(textarea.scrollHeight + 2, window.innerHeight * 0.5)}px`;
}

function toggleNewRequester() {
    const section = newRequestEl('new-requester-section');
    const open = section.classList.toggle('active');
    newRequestEl('new-requester-toggle').setAttribute('aria-expanded', String(open));
    if (open) newRequestEl('new-request-requester-name').focus();
}

// ========== Barra de chips ==========

function newRequestChipHtml(chip) {
    let lead;
    if (chip.dot) {
        lead = `<span class="work-menu-dot work-menu-dot--${chip.dot}" aria-hidden="true"></span>`;
    } else if (chip.level !== undefined) {
        lead = `<span class="request-create-bars is-level-${chip.level}" aria-hidden="true"><span></span><span></span><span></span></span>`;
    } else {
        lead = `<i class="${chip.icon}" aria-hidden="true"></i>`;
    }
    const classes = ['request-create-chip'];
    if (chip.empty) classes.push('is-empty');
    if (chip.invalid) classes.push('is-invalid');
    if (chip.tone) classes.push(`is-${chip.tone}`);

    return `<button type="button" class="${classes.join(' ')}" data-chip="${chip.key}" aria-haspopup="menu" aria-expanded="false"
                title="${newRequestEscape(chip.title)}" aria-label="${newRequestEscape(chip.aria || chip.label)}"${chip.invalid ? ' aria-describedby="new-request-app-error"' : ''}>
                ${lead}<span>${newRequestEscape(chip.label)}</span>
            </button>`;
}

function renderNewRequestBar() {
    const bar = newRequestEl('new-request-bar');
    if (!bar) return;
    const state = newRequestState;
    const can = newRequestCan();
    const app = (typeof apps !== 'undefined' ? apps : []).find(a => Number(a.id) === Number(state.appId));
    const levels = { low: 1, medium: 2, high: 3 };

    const chips = [
        {
            key: 'app', icon: 'iconoir-app-window',
            label: app ? app.name : 'Elegir aplicación',
            aria: app ? `Aplicación: ${app.name}` : 'Elegir aplicación (obligatoria)',
            title: 'Aplicación (obligatoria)',
            empty: !app, invalid: newRequestAppInvalid && !app
        },
        { key: 'priority', dot: state.priority, label: newRequestPriorityLabel(state.priority), title: 'Prioridad' },
        {
            key: 'difficulty', level: levels[state.difficulty] || 0,
            label: newRequestDifficultyLabel(state.difficulty),
            title: 'Dificultad estimada', empty: !state.difficulty
        }
    ];
    if (can.plan) {
        const due = requestDueInfo(state.dueDate);
        chips.push({
            key: 'due', icon: 'iconoir-calendar-check',
            label: state.dueDate ? due.label : 'Poner fecha',
            aria: state.dueDate ? `Fecha objetivo: ${due.label}` : 'Poner fecha objetivo',
            title: 'Fecha objetivo', empty: !state.dueDate, tone: state.dueDate ? due.tone : ''
        });
    }
    if (can.assign) {
        const assigned = newRequestAssigneesLabel(state.assignees);
        chips.push({
            key: 'assign', icon: assigned.empty ? 'iconoir-user-plus' : 'iconoir-user',
            label: assigned.label, aria: assigned.empty ? 'Asignar' : `Asignada a ${assigned.label}`,
            title: assigned.title, empty: assigned.empty
        });
    }

    // Repintar sustituye los chips: el foco vuelve al mismo chip (p. ej. tras elegir en su menú).
    const focusedChip = bar.contains(document.activeElement) ? document.activeElement.dataset.chip : null;
    bar.innerHTML = `
        <div class="request-meta-bar__controls">${chips.map(newRequestChipHtml).join('')}</div>
        ${newRequestAppInvalid && !app ? '<p class="request-create-error" id="new-request-app-error" role="alert">Elige la aplicación de la mejora</p>' : ''}`;
    if (focusedChip) {
        const chip = bar.querySelector(`[data-chip="${focusedChip}"]`);
        if (chip) chip.focus({ preventScroll: true });
    }

    // El menú abierto sigue anclado a su chip (el repintado lo sustituye).
    if (newRequestMenuState) {
        const chip = bar.querySelector(`[data-chip="${newRequestMenuState.chip}"]`);
        if (chip) {
            newRequestMenuState.anchor = chip;
            chip.setAttribute('aria-expanded', 'true');
        }
    }
}

async function openNewRequestChipMenu(key, anchor) {
    const state = newRequestState;

    if (key === 'app') {
        const list = typeof apps !== 'undefined' ? apps : [];
        openNewRequestMenu(anchor, key, {
            title: 'Aplicación',
            search: list.length > 7 ? 'Buscar aplicación' : '',
            items: (query) => {
                const q = (query || '').trim().toLowerCase();
                const groups = groupNewRequestApps(list.filter(a => !q || a.name.toLowerCase().includes(q)));
                return groups.flatMap(group => [
                    ...(groups.length > 1 ? [{ type: 'group', label: group.company || 'Sin empresa' }] : []),
                    ...group.apps.map(a => ({ key: a.id, label: a.name, active: Number(a.id) === Number(state.appId) }))
                ]);
            },
            onSelect: (value) => {
                newRequestState.appId = Number(value);
                newRequestAppInvalid = false;
                renderNewRequestBar();
            }
        });
    } else if (key === 'priority') {
        openNewRequestMenu(anchor, key, {
            title: 'Prioridad',
            items: () => workPriorityMenuOptions(state.priority).map(o => ({ key: o.key, label: o.label, dot: o.key, active: o.active })),
            onSelect: (value) => {
                newRequestState.priority = value;
                renderNewRequestBar();
            }
        });
    } else if (key === 'difficulty') {
        openNewRequestMenu(anchor, key, {
            title: 'Dificultad estimada',
            items: () => newRequestDifficultyOptions(state.difficulty),
            onSelect: (value) => {
                newRequestState.difficulty = value;
                renderNewRequestBar();
            }
        });
    } else if (key === 'due') {
        openNewRequestDueMenu(anchor);
    } else if (key === 'assign') {
        if (!availableUsers.length && typeof loadAvailableUsers === 'function') await loadAvailableUsers();
        openNewRequestMenu(anchor, key, {
            title: 'Asignar a',
            multi: true,
            search: availableUsers.length > 7 ? 'Buscar persona' : '',
            items: (query) => filterNewRequestPeople(availableUsers, query).map(user => {
                const index = newRequestState.assignees.findIndex(a => Number(a.id) === Number(user.id));
                const name = newRequestPersonName(user);
                return {
                    key: user.id, label: name, avatar: name.charAt(0).toUpperCase(),
                    active: index !== -1, hint: index === 0 ? 'Resp.' : ''
                };
            }),
            onSelect: (value) => {
                const user = availableUsers.find(u => Number(u.id) === Number(value));
                if (!user) return;
                newRequestState.assignees = toggleNewRequestAssignee(newRequestState.assignees, {
                    id: Number(user.id), username: user.username, full_name: user.full_name
                });
                renderNewRequestBar();
            }
        });
    }
}

// Mismos atajos que "Posponer" en Tareas y que la fecha de "Mi trabajo".
function openNewRequestDueMenu(anchor) {
    const options = workDateMenuOptions(new Date(), newRequestState.dueDate);
    const menu = openNewRequestMenu(anchor, 'due', {
        title: 'Fecha objetivo',
        items: () => [
            ...options.filter(o => o.key !== 'pick' && o.key !== 'clear')
                .map(o => ({ key: o.key, label: o.label, hint: o.hint, active: o.active })),
            { type: 'separator' },
            ...options.filter(o => o.key === 'pick' || o.key === 'clear')
                .map(o => ({ key: o.key, label: o.label, danger: o.key === 'clear', keepOpen: o.key === 'pick' }))
        ],
        extraHtml: `<input type="date" class="request-create-menu-date" tabindex="-1" aria-label="Elegir fecha" value="${newRequestEscape(newRequestState.dueDate)}">`,
        onSelect: (key) => {
            if (key === 'pick') {
                const input = newRequestMenuState && newRequestMenuState.menu.querySelector('.request-create-menu-date');
                if (!input) return;
                if (typeof input.showPicker === 'function') {
                    try {
                        input.showPicker();
                        return;
                    } catch (error) {
                        console.error('Nueva mejora: showPicker no disponible', error);
                    }
                }
                input.focus();
                return;
            }
            const option = options.find(o => o.key === key);
            newRequestState.dueDate = key === 'clear' || !option ? '' : option.value;
            renderNewRequestBar();
        }
    });
    if (!menu) return;

    const input = menu.querySelector('.request-create-menu-date');
    input.addEventListener('change', () => {
        if (!input.value) return;
        newRequestState.dueDate = input.value;
        closeNewRequestMenu(true);
        renderNewRequestBar();
    });
}

// ========== Menú de un chip (aspecto de los menús de "Mi trabajo") ==========

function newRequestMenuItemHtml(item, multi) {
    if (item.type === 'separator') return '<div class="work-menu-sep" role="separator"></div>';
    if (item.type === 'group') return `<p class="request-create-menu-group">${newRequestEscape(item.label)}</p>`;

    let lead = '';
    if (item.avatar) lead = `<span class="request-create-menu-avatar" aria-hidden="true">${newRequestEscape(item.avatar)}</span>`;
    else if (item.dot) lead = `<span class="work-menu-dot work-menu-dot--${item.dot}" aria-hidden="true"></span>`;

    const classes = ['work-menu-item'];
    if (item.active) classes.push('is-active');
    if (item.danger) classes.push('is-danger');
    const role = multi ? `role="menuitemcheckbox" aria-checked="${item.active ? 'true' : 'false'}"` : 'role="menuitem"';

    return `<button type="button" class="${classes.join(' ')}" ${role} data-key="${newRequestEscape(item.key)}"${item.keepOpen ? ' data-keep-open="1"' : ''}>
                ${lead}<span class="work-menu-label">${newRequestEscape(item.label)}</span>
                ${item.hint ? `<span class="work-menu-hint">${newRequestEscape(item.hint)}</span>` : ''}
                ${item.active ? '<i class="iconoir-check" aria-hidden="true"></i>' : ''}
            </button>`;
}

/**
 * Abre el menú de un chip y lo devuelve (null si ya estaba abierto: el chip lo cierra).
 * options: { title, items(query) => [...], onSelect(key), multi (no se cierra al marcar),
 *            search (placeholder del buscador; vacío = sin buscador), extraHtml }
 */
function openNewRequestMenu(anchor, chipKey, options) {
    const wasOpenHere = newRequestMenuState && newRequestMenuState.chip === chipKey;
    closeNewRequestMenu();
    if (wasOpenHere) return null;

    const menu = document.createElement('div');
    menu.className = 'work-menu request-create-menu';
    menu.setAttribute('role', 'menu');
    menu.setAttribute('aria-label', options.title);
    menu.innerHTML = `
        <p class="work-menu-title">${newRequestEscape(options.title)}</p>
        ${options.search ? `<input type="search" class="request-create-menu-search" placeholder="${newRequestEscape(options.search)}" aria-label="${newRequestEscape(options.search)}" autocomplete="off">` : ''}
        <div class="request-create-menu-items"></div>
        ${options.extraHtml || ''}`;
    const list = menu.querySelector('.request-create-menu-items');
    const search = menu.querySelector('.request-create-menu-search');

    const paint = () => {
        const items = options.items(search ? search.value : '');
        list.innerHTML = items.length
            ? items.map(item => newRequestMenuItemHtml(item, options.multi)).join('')
            : '<p class="request-create-menu-empty">Sin resultados</p>';
    };
    paint();

    list.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-key]');
        if (!btn) return;
        const key = btn.dataset.key;
        const keepOpen = options.multi || btn.dataset.keepOpen === '1';
        if (!keepOpen) closeNewRequestMenu(true);
        options.onSelect(key);
        if (options.multi && newRequestMenuState && newRequestMenuState.menu === menu) {
            paint();
            const same = list.querySelector(`[data-key="${CSS.escape(key)}"]`);
            if (same) same.focus({ preventScroll: true });
        }
    });
    if (search) search.addEventListener('input', paint);

    document.body.appendChild(menu);
    positionNewRequestMenu(menu, anchor);

    const buttons = () => Array.from(list.querySelectorAll('[data-key]'));
    const onKeyDown = (e) => {
        if (e.key === 'Escape') {
            e.preventDefault();
            e.stopPropagation();
            closeNewRequestMenu(true);
        } else if (e.key === 'Tab') {
            e.preventDefault();
            closeNewRequestMenu(true);
        } else if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            e.preventDefault();
            const all = buttons();
            if (!all.length) return;
            const index = all.indexOf(document.activeElement);
            const step = e.key === 'ArrowDown' ? 1 : -1;
            const next = index === -1 ? (step === 1 ? 0 : all.length - 1) : (index + step + all.length) % all.length;
            all[next].focus();
        } else if (e.key === 'Enter' && search && document.activeElement === search) {
            e.preventDefault();
            const first = buttons()[0];
            if (first) first.click();
        }
    };
    const onPointerDown = (e) => {
        const current = newRequestMenuState;
        if (current && !menu.contains(e.target) && !current.anchor.contains(e.target)) closeNewRequestMenu();
    };
    const onScroll = (e) => {
        if (!menu.contains(e.target)) closeNewRequestMenu();
    };
    const onResize = () => closeNewRequestMenu();

    setTimeout(() => document.addEventListener('pointerdown', onPointerDown, true), 0);
    document.addEventListener('keydown', onKeyDown, true);
    window.addEventListener('scroll', onScroll, true);
    window.addEventListener('resize', onResize);

    newRequestMenuState = {
        menu, anchor, chip: chipKey,
        cleanup: () => {
            document.removeEventListener('pointerdown', onPointerDown, true);
            document.removeEventListener('keydown', onKeyDown, true);
            window.removeEventListener('scroll', onScroll, true);
            window.removeEventListener('resize', onResize);
        }
    };
    anchor.setAttribute('aria-expanded', 'true');

    const focusTarget = search || list.querySelector('.is-active[data-key]') || list.querySelector('[data-key]');
    if (focusTarget) focusTarget.focus({ preventScroll: true });
    return menu;
}

function closeNewRequestMenu(returnFocus) {
    const state = newRequestMenuState;
    if (!state) return;
    newRequestMenuState = null;
    state.cleanup();
    state.menu.remove();
    if (document.contains(state.anchor)) {
        state.anchor.setAttribute('aria-expanded', 'false');
        if (returnFocus === true) state.anchor.focus({ preventScroll: true });
    }
}

// Bajo el chip, dentro de la ventana (encima si no cabe debajo).
function positionNewRequestMenu(menu, anchor) {
    const gap = 6;
    const margin = 8;
    const rect = anchor.getBoundingClientRect();
    const width = menu.offsetWidth;
    const height = menu.offsetHeight;

    const left = Math.max(margin, Math.min(rect.left, window.innerWidth - width - margin));
    let top = rect.bottom + gap;
    if (top + height > window.innerHeight - margin) {
        top = Math.max(margin, rect.top - height - gap);
    }
    menu.style.left = `${left}px`;
    menu.style.top = `${top}px`;
}

// ========== Adjuntos ==========

// Pegar una captura (Ctrl/Cmd+V) la adjunta. Pegar texto en los campos funciona como siempre.
function handleNewRequestPaste(e) {
    const files = Array.from((e.clipboardData && e.clipboardData.files) || []);
    if (!files.length || !newRequestCan().attach) return;
    e.preventDefault();

    const now = new Date();
    addNewRequestFiles(files.map((file, i) => {
        const name = pastedFileName(file.name, file.type, now, i, files.length);
        return name === file.name ? file : new File([file], name, { type: file.type });
    }));
}

// Se guarda una copia de los bytes al elegirlos: se suben al crear la mejora, y un archivo
// abierto desde el correo o una carpeta temporal puede dejar de poder leerse mientras tanto.
async function addNewRequestFiles(fileList) {
    if (!newRequestCan().attach) return;
    const { accepted, rejected } = splitNewRequestFiles(Array.from(fileList || []));

    if (rejected.length) {
        rejected.forEach(file => console.error('Nueva mejora: archivo de más de 15 MB', file.name, file.size));
        showToast({
            title: 'Archivo demasiado grande',
            message: `${rejected.map(f => newRequestEscape(f.name)).join(', ')}: el máximo son 15 MB`,
            icon: 'iconoir-warning-triangle',
            duration: 6000
        }, 'toast-discarded');
    }

    for (const file of accepted) {
        try {
            const buffer = await file.arrayBuffer();
            newRequestState.files.push(new File([buffer], file.name, { type: file.type || 'application/octet-stream' }));
        } catch (error) {
            console.error('Nueva mejora: no se pudo leer el archivo', file.name, error);
            showToast({
                title: 'No se pudo leer el archivo',
                message: `${newRequestEscape(file.name)}: guárdalo en una carpeta normal (como Descargas) y vuelve a añadirlo`,
                icon: 'iconoir-warning-triangle',
                duration: 8000
            }, 'toast-discarded');
        }
    }
    renderNewRequestFiles();
}

function removeNewRequestFile(index) {
    const file = newRequestState.files[index];
    if (!file) return;
    if (newRequestPreviewUrls.has(file)) {
        URL.revokeObjectURL(newRequestPreviewUrls.get(file));
        newRequestPreviewUrls.delete(file);
    }
    newRequestState.files = newRequestState.files.filter((_, i) => i !== index);
    renderNewRequestFiles();
    newRequestEl('new-request-upload').focus();
}

function renderNewRequestFiles() {
    const list = newRequestEl('new-request-file-list');
    const count = newRequestEl('new-request-file-count');
    if (!list) return;
    const files = newRequestState.files;

    count.textContent = files.length ? String(files.length) : '';
    count.hidden = files.length === 0;
    list.innerHTML = files.map((file, index) => {
        const isImage = (file.type || '').startsWith('image/');
        if (isImage && !newRequestPreviewUrls.has(file)) newRequestPreviewUrls.set(file, URL.createObjectURL(file));
        const name = newRequestEscape(file.name);
        return `
            <div class="request-create-file">
                ${isImage
                    ? `<img class="request-create-file-thumb" src="${newRequestPreviewUrls.get(file)}" alt="">`
                    : `<span class="request-create-file-icon" aria-hidden="true"><i class="${getFileIcon(file.type)}"></i></span>`}
                <span class="request-create-file-name" title="${name}">${name}</span>
                <span class="request-create-file-size">${formatFileSize(file.size)}</span>
                <button type="button" class="request-create-file-remove" data-file-index="${index}" title="Quitar" aria-label="Quitar ${name}">
                    <i class="iconoir-xmark"></i>
                </button>
            </div>`;
    }).join('');
}

// ========== Checklist ==========

function addNewRequestChecklistFromInput() {
    const input = newRequestEl('new-checklist-input');
    newRequestState.checklist = addNewRequestChecklistItem(newRequestState.checklist, input.value);
    input.value = '';
    input.focus();
    renderNewRequestChecklist();
}

function renderNewRequestChecklist() {
    const list = newRequestEl('new-checklist-list');
    const count = newRequestEl('new-checklist-count');
    if (!list) return;
    const items = newRequestState.checklist;

    count.textContent = items.length ? String(items.length) : '';
    count.hidden = items.length === 0;
    list.innerHTML = items.map((title, index) => `
        <div class="checklist-item checklist-item--draft">
            <span class="checklist-item-bullet" aria-hidden="true"><i class="iconoir-square"></i></span>
            <span class="checklist-item-text">${newRequestEscape(title)}</span>
            <button type="button" class="checklist-item-remove" data-checklist-index="${index}" title="Quitar" aria-label="Quitar el paso ${newRequestEscape(title)}">
                <i class="iconoir-xmark"></i>
            </button>
        </div>`).join('');
}

// ========== Crear ==========

function setNewRequestSubmitting(submitting, label) {
    newRequestSubmitting = submitting;
    const button = newRequestEl('new-request-submit');
    if (!button) return;
    button.disabled = submitting;
    newRequestEl('new-request-submit-label').textContent = submitting ? (label || 'Creando…') : 'Crear mejora';
}

// POST con JSON o FormData; devuelve el texto del problema o null si fue bien.
async function postNewRequestExtra(url, body, what) {
    try {
        const response = await fetch(url, body instanceof FormData
            ? { method: 'POST', body }
            : { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
        let data = null;
        try {
            data = await response.json();
        } catch (parseError) {
            console.error(`Nueva mejora: respuesta no válida al ${what}`, url, response.status, parseError);
        }
        if (data && data.success) return null;
        console.error(`Nueva mejora: no se pudo ${what}`, url, response.status, data && data.error);
        return `${what} (${(data && data.error) || `error ${response.status}`})`;
    } catch (error) {
        console.error(`Nueva mejora: fallo de red al ${what}`, url, error);
        return `${what} (sin conexión)`;
    }
}

async function submitNewRequest(event) {
    event.preventDefault();
    if (newRequestSubmitting) return;

    const fields = newRequestFields();
    const missing = newRequestMissing(newRequestState, fields);
    if (missing.includes('title')) {
        const title = newRequestEl('request-title');
        title.setCustomValidity('Escribe un título para la mejora');
        title.reportValidity();
        title.focus();
        return;
    }
    if (missing.includes('app')) {
        newRequestAppInvalid = true;
        renderNewRequestBar();
        const chip = newRequestEl('new-request-bar').querySelector('[data-chip="app"]');
        if (chip) openNewRequestChipMenu('app', chip);
        return;
    }

    const draft = newRequestState;
    const payload = newRequestPayload(draft, fields);
    setNewRequestSubmitting(true);

    let requestId;
    try {
        const response = await fetch('/api/requests.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await response.json();
        if (!data.success) {
            console.error('Nueva mejora: no se pudo crear', response.status, data.error, payload);
            setNewRequestSubmitting(false);
            showToast({ title: 'No se pudo crear la mejora', message: newRequestEscape(data.error || 'Inténtalo de nuevo'), icon: 'iconoir-warning-triangle', duration: 6000 }, 'toast-discarded');
            return;
        }
        requestId = data.data.id;
    } catch (error) {
        console.error('Nueva mejora: error al crear', error, payload);
        setNewRequestSubmitting(false);
        showToast({ title: 'No se pudo crear la mejora', message: 'Error de conexión. Inténtalo de nuevo.', icon: 'iconoir-warning-triangle', duration: 6000 }, 'toast-discarded');
        return;
    }

    // Lo que va aparte, ya con la mejora creada: asignados, pasos y archivos.
    const problems = [];
    if (draft.assignees.length) {
        const problem = await postNewRequestExtra('/api/assignments.php', {
            request_id: requestId,
            user_ids: draft.assignees.map(a => Number(a.id))
        }, 'asignar');
        if (problem) problems.push(problem);
    }
    for (const title of draft.checklist) {
        const problem = await postNewRequestExtra('/api/request-checklist.php', { request_id: requestId, title }, `añadir el paso "${title}"`);
        if (problem) problems.push(problem);
    }
    for (const [index, file] of draft.files.entries()) {
        setNewRequestSubmitting(true, draft.files.length > 1 ? `Subiendo ${index + 1} de ${draft.files.length}…` : 'Subiendo archivo…');
        const formData = new FormData();
        formData.append('request_id', requestId);
        formData.append('file', file, file.name);
        const problem = await postNewRequestExtra('/api/upload.php', formData, `subir ${file.name}`);
        if (problem) problems.push(problem);
    }

    setNewRequestSubmitting(false);
    closeNewRequestModal();
    loadRequests();

    if (problems.length) {
        showToast({
            title: `Mejora #${newRequestEscape(requestId)} creada con avisos`,
            message: `No se pudo ${problems.map(newRequestEscape).join('; ')}`,
            icon: 'iconoir-warning-triangle',
            duration: 9000
        }, 'toast-discarded');
    } else {
        showToast({
            title: 'Mejora creada',
            message: `#${newRequestEscape(requestId)} · ${newRequestEscape(payload.title)}`,
            icon: 'iconoir-check'
        }, 'toast-completed');
    }
}
