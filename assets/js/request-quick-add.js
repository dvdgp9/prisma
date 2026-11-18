/** Entrada rápida del listado de mejoras; usa el mismo guardado que el modal. */
let quickRequestIgnore = {};
let quickRequestSubmitting = false;
let quickRequestContext = null;
let quickRequestFallbackAppId = null;

document.addEventListener('DOMContentLoaded', setupQuickRequest);

function quickRequestEl(name) {
    return document.getElementById(`request-quick-${name}`);
}

function setupQuickRequest() {
    const form = quickRequestEl('form');
    if (!form) return;
    const input = quickRequestEl('input');
    form.addEventListener('submit', submitQuickRequest);
    input.addEventListener('input', () => {
        if (!input.value.trim()) quickRequestIgnore = {};
        input.style.height = 'auto';
        input.style.height = `${Math.min(input.scrollHeight + 2, 280)}px`;
        quickRequestEl('app').removeAttribute('aria-invalid');
        renderQuickRequestPreview();
    });
    input.addEventListener('keydown', event => {
        if (event.isComposing || event.key !== 'Enter' || event.shiftKey) return;
        if (event.ctrlKey || event.metaKey || !input.value.includes('\n')) {
            event.preventDefault();
            form.requestSubmit();
        }
    });
    quickRequestEl('app').addEventListener('change', event => {
        quickRequestFallbackAppId = Number(event.target.value) || null;
        event.target.removeAttribute('aria-invalid');
        setQuickRequestError('');
        renderQuickRequestPreview();
    });
    quickRequestEl('chips').addEventListener('click', event => {
        const button = event.target.closest('[data-dismiss]');
        if (!button || quickRequestSubmitting) return;
        quickRequestIgnore[button.dataset.dismiss] = true;
        renderQuickRequestPreview();
        input.focus();
    });
    quickRequestEl('details').addEventListener('click', openQuickRequestDetails);
    syncQuickRequestContext();
}

function syncQuickRequestContext() {
    const form = quickRequestEl('form');
    if (!form) return;
    const context = `${currentView}:${currentAppId}:${currentCompanyId}`;
    const scopedApps = currentView === 'company'
        ? apps.filter(app => Number(app.company_id) === Number(currentCompanyId))
        : apps;
    if (context !== quickRequestContext) {
        quickRequestContext = context;
        form.hidden = true;
        document.getElementById('request-quick-toggle')?.setAttribute('aria-expanded', 'false');
        quickRequestFallbackAppId = null;
    }
    if (!quickRequestFallbackAppId) {
        quickRequestFallbackAppId = newRequestDefaultApp(scopedApps, currentView, currentAppId);
    }
    const select = quickRequestEl('app');
    select.replaceChildren(new Option('Elige una aplicación', ''));
    // Se permite elegir otra app sin perder el borrador, incluso desde una vista de empresa.
    apps.forEach(app => {
        const company = appsGrouped.find(group => (group.apps || []).some(a => Number(a.id) === Number(app.id)));
        select.add(new Option(`${company ? company.name + ' · ' : ''}${app.name}`, String(app.id)));
    });
    renderQuickRequestPreview();
}

function parseQuickRequestInput() {
    return parseQuickRequest(quickRequestEl('input').value, apps, availableUsers, {
        ignore: quickRequestIgnore,
        canPlan: newRequestCan().plan
    });
}

function setQuickRequestError(message) {
    const error = quickRequestEl('error');
    error.textContent = message;
    error.hidden = !message;
}

function renderQuickRequestPreview() {
    if (!quickRequestEl('form')) return;
    const parsed = parseQuickRequestInput();
    const chips = quickRequestEl('chips');
    chips.replaceChildren();
    parsed.chips.forEach(chip => {
        const item = document.createElement('span');
        item.className = 'request-quick-chip';
        const label = document.createElement('span');
        label.textContent = chip.label;
        const remove = document.createElement('button');
        remove.type = 'button';
        remove.dataset.dismiss = chip.kind;
        remove.setAttribute('aria-label', `Conservar como texto: ${chip.label}`);
        remove.title = 'Conservar como texto';
        remove.disabled = quickRequestSubmitting;
        const icon = document.createElement('i');
        icon.className = 'iconoir-xmark';
        icon.setAttribute('aria-hidden', 'true');
        remove.append(icon);
        item.append(label, remove);
        chips.append(item);
    });
    chips.hidden = !parsed.chips.length;
    const title = quickRequestEl('title');
    title.textContent = `Título: ${parsed.title || 'Escribe qué hay que mejorar'}`;
    title.hidden = !quickRequestEl('input').value.trim();
    const select = quickRequestEl('app');
    select.value = parsed.app ? String(parsed.app.id) : String(quickRequestFallbackAppId || '');
    select.disabled = quickRequestSubmitting || !!parsed.app;
    setQuickRequestError(parsed.errors.join(' '));
    return parsed;
}

function quickRequestCurrentData(requireComplete) {
    const parsed = renderQuickRequestPreview();
    if (parsed.errors.length) {
        quickRequestEl('input').focus();
        return null;
    }
    const data = quickRequestData(parsed, quickRequestFallbackAppId);
    if (requireComplete) {
        const missing = newRequestMissing(data.draft, data.fields);
        if (missing.includes('title')) {
            setQuickRequestError('Escribe un título para la mejora.');
            quickRequestEl('input').focus();
            return null;
        }
        if (missing.includes('app')) {
            setQuickRequestError('Indica la aplicación con @nombre o elígela en el selector.');
            quickRequestEl('app').setAttribute('aria-invalid', 'true');
            quickRequestEl('app').focus();
            return null;
        }
    }
    return data;
}

function clearQuickRequest() {
    quickRequestEl('input').value = '';
    quickRequestEl('input').style.height = '';
    quickRequestIgnore = {};
    renderQuickRequestPreview();
}

function setQuickRequestSubmitting(submitting) {
    quickRequestSubmitting = submitting;
    quickRequestEl('form').setAttribute('aria-busy', String(submitting));
    quickRequestEl('input').disabled = submitting;
    quickRequestEl('details').disabled = submitting;
    quickRequestEl('submit').disabled = submitting;
    quickRequestEl('submit').querySelector('span').textContent = submitting ? 'Creando…' : 'Crear mejora';
    renderQuickRequestPreview();
}

async function submitQuickRequest(event) {
    event.preventDefault();
    if (quickRequestSubmitting) return;
    const data = quickRequestCurrentData(true);
    if (!data) return;
    setQuickRequestSubmitting(true);
    let created;
    try {
        created = await persistNewRequest(data.draft, data.fields);
    } catch (error) {
        setQuickRequestSubmitting(false);
        setQuickRequestError(error.message);
        quickRequestEl('input').focus();
        return;
    }
    setQuickRequestSubmitting(false);
    clearQuickRequest();
    quickRequestEl('input').focus();
    showNewRequestCreated(created);
    loadRequests();
}

function openQuickRequestDetails() {
    if (quickRequestSubmitting) return;
    const data = quickRequestCurrentData(false);
    if (!data) return;
    // Cancelar deja la entrada original disponible; solo se limpia cuando se ha creado.
    data.onCreated = clearQuickRequest;
    openNewRequestModal(data);
}
