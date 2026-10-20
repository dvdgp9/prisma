/**
 * Nueva mejora: funciones puras del modal de creación (sin DOM).
 * Las usa request-create.js y se prueban con Node (tests/request-create-modal.php).
 */
(function (global) {
    // El límite de api/upload.php.
    const NEW_REQUEST_MAX_FILE_BYTES = 15 * 1024 * 1024;

    const PRIORITY_LABELS = { critical: 'crítica', high: 'alta', medium: 'media', low: 'baja' };
    const DIFFICULTY_OPTIONS = [
        { key: '', label: 'Sin definir' },
        { key: 'low', label: 'Baja' },
        { key: 'medium', label: 'Media' },
        { key: 'high', label: 'Alta' }
    ];
    // Los mismos roles que la capacidad "edit" de auth.php (asignar, poner fecha y subir archivos la exigen).
    const EDIT_ROLES = ['programador', 'admin', 'superadmin'];

    // Lo que se elige con los chips; título, descripción y solicitante viven en sus campos.
    function newRequestDraft(options) {
        return {
            appId: (options && options.appId) || null,
            priority: 'medium',
            difficulty: '',
            dueDate: '',
            assignees: [],
            files: [],
            checklist: []
        };
    }

    // Cuerpo de POST /api/requests.php. El solicitante y la fecha solo se mandan si hay.
    function newRequestPayload(draft, fields) {
        const payload = {
            app_id: draft.appId,
            title: (fields.title || '').trim(),
            description: (fields.description || '').trim(),
            priority: draft.priority || 'medium',
            difficulty: draft.difficulty || null
        };
        const requesterName = (fields.requesterName || '').trim();
        const requesterEmail = (fields.requesterEmail || '').trim();
        if (requesterName) payload.requester_name = requesterName;
        if (requesterEmail) payload.requester_email = requesterEmail;
        if (draft.dueDate) payload.due_date = draft.dueDate;
        return payload;
    }

    // Lo obligatorio que falta, en el orden en que se pide: título y aplicación.
    function newRequestMissing(draft, fields) {
        const missing = [];
        if (!(fields.title || '').trim()) missing.push('title');
        if (!draft.appId) missing.push('app');
        return missing;
    }

    // Hay algo que se perdería al cerrar: texto escrito, archivos o pasos (los chips no cuentan).
    function newRequestIsDirty(draft, fields) {
        const typed = ['title', 'description', 'requesterName', 'requesterEmail']
            .some(key => (fields[key] || '').trim() !== '');
        return typed || draft.files.length > 0 || draft.checklist.length > 0;
    }

    function newRequestPriorityLabel(priority) {
        return `Prioridad ${PRIORITY_LABELS[priority] || PRIORITY_LABELS.medium}`;
    }

    function newRequestDifficultyLabel(difficulty) {
        const option = DIFFICULTY_OPTIONS.find(o => o.key && o.key === difficulty);
        return option ? `Dificultad ${option.label.toLowerCase()}` : 'Dificultad';
    }

    function newRequestDifficultyOptions(current) {
        return DIFFICULTY_OPTIONS.map(option => Object.assign({}, option, { active: option.key === (current || '') }));
    }

    function personName(person) {
        return person.full_name || person.username || '';
    }

    // Chip de asignados: la primera persona es la responsable ("Resp." en las tarjetas).
    function newRequestAssigneesLabel(assignees) {
        if (!assignees.length) return { label: 'Asignar', title: 'Sin asignar', empty: true };
        const [owner, ...others] = assignees.map(personName);
        return {
            label: others.length ? `${owner} +${others.length}` : owner,
            title: `Responsable: ${owner}${others.length ? ` · También: ${others.join(', ')}` : ''}`,
            empty: false
        };
    }

    // Añade al final o quita (por id): el orden de elección decide quién es la responsable.
    function toggleNewRequestAssignee(assignees, user) {
        const id = Number(user.id);
        return assignees.some(a => Number(a.id) === id)
            ? assignees.filter(a => Number(a.id) !== id)
            : assignees.concat([user]);
    }

    function normalizeSearch(text) {
        return String(text || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim();
    }

    function filterNewRequestPeople(users, query) {
        const q = normalizeSearch(query);
        if (!q) return users.slice();
        return users.filter(u => normalizeSearch(u.full_name).includes(q) || normalizeSearch(u.username).includes(q));
    }

    // Apps agrupadas por empresa, en el orden en que llegan de la API.
    function groupNewRequestApps(apps) {
        const groups = [];
        apps.forEach(app => {
            const company = app.company_name || '';
            let group = groups.find(g => g.company === company);
            if (!group) {
                group = { company, apps: [] };
                groups.push(group);
            }
            group.apps.push(app);
        });
        return groups;
    }

    // App elegida al abrir: la que estás viendo o, si solo tienes una, esa.
    function newRequestDefaultApp(apps, view, currentAppId) {
        if (view === 'app' && currentAppId && apps.some(a => Number(a.id) === Number(currentAppId))) {
            return Number(currentAppId);
        }
        return apps.length === 1 ? Number(apps[0].id) : null;
    }

    // Las capturas pegadas llegan como "image.png": se les da un nombre reconocible.
    function pastedFileName(name, mimeType, now, index, total) {
        if (name && name !== 'image.png') return name;
        const pad = (n) => String(n).padStart(2, '0');
        const stamp = `${now.getFullYear()}${pad(now.getMonth() + 1)}${pad(now.getDate())}-${pad(now.getHours())}${pad(now.getMinutes())}`;
        const ext = ((mimeType || '').split('/')[1] || 'png').replace('jpeg', 'jpg');
        return `captura-${stamp}${total > 1 ? `-${index + 1}` : ''}.${ext}`;
    }

    function splitNewRequestFiles(files, maxBytes) {
        const limit = maxBytes || NEW_REQUEST_MAX_FILE_BYTES;
        return {
            accepted: files.filter(f => f.size <= limit),
            rejected: files.filter(f => f.size > limit)
        };
    }

    function addNewRequestChecklistItem(list, text) {
        const title = String(text || '').trim();
        return title ? list.concat([title]) : list;
    }

    // Qué partes del modal se ven según el rol (la API rechaza el resto).
    function newRequestPermissions(role) {
        const canEdit = EDIT_ROLES.includes(role);
        return { plan: canEdit, assign: canEdit, attach: canEdit };
    }

    global.NEW_REQUEST_MAX_FILE_BYTES = NEW_REQUEST_MAX_FILE_BYTES;
    global.newRequestDraft = newRequestDraft;
    global.newRequestPayload = newRequestPayload;
    global.newRequestMissing = newRequestMissing;
    global.newRequestIsDirty = newRequestIsDirty;
    global.newRequestPriorityLabel = newRequestPriorityLabel;
    global.newRequestDifficultyLabel = newRequestDifficultyLabel;
    global.newRequestDifficultyOptions = newRequestDifficultyOptions;
    global.newRequestPersonName = personName;
    global.newRequestAssigneesLabel = newRequestAssigneesLabel;
    global.toggleNewRequestAssignee = toggleNewRequestAssignee;
    global.filterNewRequestPeople = filterNewRequestPeople;
    global.groupNewRequestApps = groupNewRequestApps;
    global.newRequestDefaultApp = newRequestDefaultApp;
    global.pastedFileName = pastedFileName;
    global.splitNewRequestFiles = splitNewRequestFiles;
    global.addNewRequestChecklistItem = addNewRequestChecklistItem;
    global.newRequestPermissions = newRequestPermissions;
})(window);
