// Prisma AI Inbox - Nota rápida con IA

let aiItems = []; // items propuestos por la IA (estado editable)

document.addEventListener('DOMContentLoaded', function () {
    const input = document.getElementById('ai-note-input');
    const counter = document.getElementById('ai-note-counter');
    input.addEventListener('input', () => {
        counter.textContent = `${input.value.length.toLocaleString('es-ES')} / 10.000`;
    });
});

function showStep(step) {
    ['note', 'loading', 'review', 'done'].forEach(s => {
        document.getElementById('ai-step-' + s).hidden = (s !== step);
    });
}

// ========== PASO 1: ANALIZAR ==========

async function analyzeNote() {
    const note = document.getElementById('ai-note-input').value.trim();
    const errorEl = document.getElementById('ai-note-error');
    errorEl.hidden = true;

    if (!note) {
        errorEl.textContent = 'Escribe o pega una nota antes de analizar.';
        errorEl.hidden = false;
        return;
    }

    showStep('loading');

    try {
        const response = await fetch('/api/ai-inbox.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ note })
        });
        const data = await response.json();

        if (!data.success) {
            throw new Error(data.error || 'Error desconocido al analizar la nota');
        }

        aiItems = data.data.items.map((item, i) => ({ ...item, _id: i, included: true }));
        renderReview();
        showStep('review');
    } catch (error) {
        showStep('note');
        errorEl.textContent = error.message;
        errorEl.hidden = false;
    }
}

function backToNote() {
    showStep('note');
}

function resetFlow() {
    aiItems = [];
    document.getElementById('ai-note-input').value = '';
    document.getElementById('ai-note-counter').textContent = '0 / 10.000';
    showStep('note');
}

// ========== PASO 2: REVISIÓN ==========

function renderReview() {
    const list = document.getElementById('ai-review-list');
    const summary = document.getElementById('ai-review-summary');

    const nMejoras = aiItems.filter(i => i.tipo === 'mejora').length;
    const nTareas = aiItems.filter(i => i.tipo === 'tarea').length;
    const parts = [];
    if (nMejoras) parts.push(`${nMejoras} ${nMejoras === 1 ? 'mejora' : 'mejoras'}`);
    if (nTareas) parts.push(`${nTareas} ${nTareas === 1 ? 'tarea rápida' : 'tareas rápidas'}`);
    summary.textContent = parts.length
        ? `La IA ha detectado ${parts.join(' y ')} en tu nota.`
        : 'La IA no ha detectado elementos accionables en tu nota.';

    if (aiItems.length === 0) {
        list.innerHTML = `
            <div class="ai-empty-card">
                <i class="iconoir-page-search"></i>
                <p>No se ha encontrado nada accionable. Prueba a reformular la nota o añade más detalle
                (qué hay que hacer y, si lo sabes, en qué aplicación).</p>
            </div>`;
        document.getElementById('ai-confirm-btn').disabled = true;
        return;
    }

    list.innerHTML = aiItems.map(renderItemCard).join('');
    updateConfirmButton();
}

function appOptions(selectedId) {
    const none = `<option value="" ${selectedId === null ? 'selected' : ''}>⚠ Elegir aplicación...</option>`;
    return none + AI_USER_APPS.map(a =>
        `<option value="${a.id}" ${a.id === selectedId ? 'selected' : ''}>${escapeAiHtml(a.name)} (${escapeAiHtml(a.company || '')})</option>`
    ).join('');
}

function renderItemCard(item) {
    const isMejora = item.tipo === 'mejora';
    return `
    <div class="ai-item-card ${item.included ? '' : 'is-discarded'}" id="ai-item-${item._id}">
        <div class="ai-item-header">
            <label class="ai-item-include" title="${item.included ? 'Incluido: se creará al confirmar' : 'Descartado: no se creará'}">
                <input type="checkbox" ${item.included ? 'checked' : ''} onchange="toggleItem(${item._id}, this.checked)">
                <span>${item.included ? 'Se creará' : 'Descartado'}</span>
            </label>
            <select class="ai-item-type" onchange="updateItem(${item._id}, 'tipo', this.value)" ${item.included ? '' : 'disabled'}>
                <option value="mejora" ${isMejora ? 'selected' : ''}>Mejora de una app</option>
                <option value="tarea" ${!isMejora ? 'selected' : ''}>Tarea rápida</option>
            </select>
        </div>

        <div class="ai-item-body" ${item.included ? '' : 'style="opacity:.45;pointer-events:none"'}>
            <div class="form-group">
                <label>Título</label>
                <input type="text" value="${escapeAiAttr(item.title)}" maxlength="200"
                    onchange="updateItem(${item._id}, 'title', this.value)">
            </div>
            <div class="form-group">
                <label>Descripción</label>
                <textarea rows="2" onchange="updateItem(${item._id}, 'description', this.value)">${escapeAiHtml(item.description)}</textarea>
            </div>
            <div class="ai-item-row">
                <div class="form-group">
                    <label>Aplicación ${isMejora ? '' : '(opcional)'}</label>
                    <select onchange="updateItem(${item._id}, 'app_id', this.value ? parseInt(this.value) : null)">
                        ${appOptions(item.app_id)}
                    </select>
                </div>
                <div class="form-group">
                    <label>Prioridad</label>
                    <select onchange="updateItem(${item._id}, 'priority', this.value)" ${isMejora ? '' : 'disabled title="Las tareas rápidas no tienen prioridad"'}>
                        <option value="low" ${item.priority === 'low' ? 'selected' : ''}>Baja</option>
                        <option value="medium" ${item.priority === 'medium' ? 'selected' : ''}>Media</option>
                        <option value="high" ${item.priority === 'high' ? 'selected' : ''}>Alta</option>
                        <option value="critical" ${item.priority === 'critical' ? 'selected' : ''}>Crítica</option>
                    </select>
                </div>
            </div>

            ${isMejora ? renderSubtasks(item) : ''}

            ${item.reasoning ? `
            <div class="ai-item-reasoning">
                <i class="iconoir-sparks"></i>
                <span>${escapeAiHtml(item.reasoning)}</span>
            </div>` : ''}
        </div>
    </div>`;
}

function renderSubtasks(item) {
    const rows = item.subtasks.map((st, idx) => `
        <div class="ai-subtask-row">
            <i class="iconoir-check-circle"></i>
            <input type="text" value="${escapeAiAttr(st)}" onchange="updateSubtask(${item._id}, ${idx}, this.value)">
            <button type="button" class="ai-subtask-remove" title="Quitar subtarea" onclick="removeSubtask(${item._id}, ${idx})">
                <i class="iconoir-xmark"></i>
            </button>
        </div>`).join('');

    return `
    <div class="form-group">
        <label>Subtareas (checklist de la mejora)</label>
        ${rows || '<p class="text-muted ai-subtasks-empty">Sin subtareas. Puedes añadir pasos concretos:</p>'}
        <button type="button" class="btn btn-sm btn-outline ai-subtask-add" onclick="addSubtask(${item._id})">
            <i class="iconoir-plus"></i> Añadir subtarea
        </button>
    </div>`;
}

function getItem(id) {
    return aiItems.find(i => i._id === id);
}

function toggleItem(id, included) {
    getItem(id).included = included;
    renderReview();
}

function updateItem(id, field, value) {
    getItem(id)[field] = value;
    if (field === 'tipo') renderReview(); // cambia campos visibles (subtareas/prioridad)
}

function updateSubtask(id, idx, value) {
    getItem(id).subtasks[idx] = value;
}

function removeSubtask(id, idx) {
    getItem(id).subtasks.splice(idx, 1);
    renderReview();
}

function addSubtask(id) {
    getItem(id).subtasks.push('');
    renderReview();
}

function updateConfirmButton() {
    const included = aiItems.filter(i => i.included);
    const btn = document.getElementById('ai-confirm-btn');
    const label = document.getElementById('ai-confirm-label');
    btn.disabled = included.length === 0;
    label.textContent = included.length === 0
        ? 'Nada seleccionado'
        : `Crear ${included.length} ${included.length === 1 ? 'elemento' : 'elementos'}`;
}

// ========== PASO 3: CONFIRMAR Y CREAR ==========

async function confirmItems() {
    const errorEl = document.getElementById('ai-review-error');
    errorEl.hidden = true;

    const included = aiItems.filter(i => i.included);

    // Validación: las mejoras necesitan app
    const sinApp = included.filter(i => i.tipo === 'mejora' && !i.app_id);
    if (sinApp.length > 0) {
        errorEl.textContent = `Hay ${sinApp.length} ${sinApp.length === 1 ? 'mejora sin aplicación asignada' : 'mejoras sin aplicación asignada'}. Elige una app en cada una (selector con ⚠) o descártalas.`;
        errorEl.hidden = false;
        sinApp.forEach(i => document.getElementById('ai-item-' + i._id).classList.add('needs-app'));
        return;
    }

    const btn = document.getElementById('ai-confirm-btn');
    btn.disabled = true;
    document.getElementById('ai-confirm-label').textContent = 'Creando...';

    const results = [];
    for (const item of included) {
        try {
            if (item.tipo === 'mejora') {
                const res = await fetch('/api/requests.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        app_id: item.app_id,
                        title: item.title,
                        description: item.description || null,
                        priority: item.priority
                    })
                });
                const data = await res.json();
                if (!data.success) throw new Error(data.error || 'Error al crear la mejora');
                const requestId = data.data.id;

                // Subtareas como checklist
                const subtasks = item.subtasks.map(s => s.trim()).filter(Boolean);
                for (const st of subtasks) {
                    await fetch('/api/request-checklist.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ request_id: requestId, title: st })
                    });
                }
                results.push({ ok: true, item, requestId });
            } else {
                const res = await fetch('/api/tasks.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        title: item.title,
                        description: item.description || null,
                        app_id: item.app_id || null
                    })
                });
                const data = await res.json();
                if (!data.success) throw new Error(data.error || 'Error al crear la tarea');
                results.push({ ok: true, item });
            }
        } catch (error) {
            results.push({ ok: false, item, error: error.message });
        }
    }

    renderDone(results);
    showStep('done');
}

function renderDone(results) {
    const oks = results.filter(r => r.ok);
    const fails = results.filter(r => !r.ok);
    const title = document.getElementById('ai-done-title');
    const list = document.getElementById('ai-done-list');

    title.textContent = fails.length === 0
        ? `¡Hecho! ${oks.length} ${oks.length === 1 ? 'elemento creado' : 'elementos creados'}.`
        : `Creados ${oks.length} de ${results.length} elementos`;

    list.innerHTML = results.map(r => {
        const icon = r.ok ? 'iconoir-check-circle' : 'iconoir-warning-triangle';
        const cls = r.ok ? 'is-ok' : 'is-fail';
        const appName = r.item.app_id ? (AI_USER_APPS.find(a => a.id === r.item.app_id)?.name || '') : '';
        const where = r.item.tipo === 'mejora'
            ? `Mejora en ${escapeAiHtml(appName)}`
            : 'Tarea rápida en Mis tareas';
        const link = r.ok
            ? (r.item.tipo === 'mejora'
                ? `<a href="/index.php?app_id=${r.item.app_id}">Ver app</a>`
                : `<a href="/tasks.php">Ver tareas</a>`)
            : `<span class="ai-done-error">${escapeAiHtml(r.error)}</span>`;
        return `
        <div class="ai-done-row ${cls}">
            <i class="${icon}"></i>
            <div>
                <strong>${escapeAiHtml(r.item.title)}</strong>
                <span class="text-muted">${where}${r.item.subtasks?.length && r.item.tipo === 'mejora' ? ` · ${r.item.subtasks.filter(s => s.trim()).length} subtareas` : ''}</span>
            </div>
            ${link}
        </div>`;
    }).join('');
}

// ========== UTILS ==========

function escapeAiHtml(str) {
    const div = document.createElement('div');
    div.textContent = str ?? '';
    return div.innerHTML;
}

function escapeAiAttr(str) {
    return escapeAiHtml(str).replace(/"/g, '&quot;');
}
