/**
 * Trabajo (mejoras y tareas): funciones puras de fechas y orden, sin DOM ni red.
 * Las usan main.js (Mejoras) y la Vista global. Se prueban con Node en tests/.
 */

(function (global) {
    'use strict';

    const MONTHS = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    const WEEKDAYS = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
    const DAY_MS = 86400000;

    // "AAAA-MM-DD" (DATE de MySQL) como fecha local a medianoche; null si no es válida.
    function parseDueDate(value) {
        const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(value || ''));
        if (!m) return null;
        const date = new Date(+m[1], +m[2] - 1, +m[3]);
        return date.getMonth() === +m[2] - 1 ? date : null;
    }

    function startOfDay(date) {
        return new Date(date.getFullYear(), date.getMonth(), date.getDate());
    }

    /**
     * Texto y tono de la fecha objetivo de una mejora respecto a hoy.
     * tone: overdue | today | soon (1-7 días) | later | none
     */
    function requestDueInfo(value, today) {
        const due = parseDueDate(value);
        if (!due) return { tone: 'none', label: 'Sin fecha', days: null };

        const ref = startOfDay(today || new Date());
        const days = Math.round((due - ref) / DAY_MS);
        const dayMonth = `${due.getDate()} ${MONTHS[due.getMonth()]}`;
        const fullDate = due.getFullYear() === ref.getFullYear() ? dayMonth : `${dayMonth} ${due.getFullYear()}`;

        if (days < 0) {
            return { tone: 'overdue', label: days === -1 ? 'Venció ayer' : `Venció el ${fullDate}`, days };
        }
        if (days === 0) return { tone: 'today', label: 'Para hoy', days };
        if (days === 1) return { tone: 'soon', label: 'Para mañana', days };
        if (days < 7) return { tone: 'soon', label: `Para el ${WEEKDAYS[due.getDay()]}`, days };
        if (days === 7) return { tone: 'soon', label: `Para el ${fullDate}`, days };
        return { tone: 'later', label: `Para el ${fullDate}`, days };
    }

    // ===== "Mi trabajo" (Vista global): tareas y mejoras en una sola lista =====

    const PRIORITY_RANK = { critical: 0, high: 1, medium: 2, low: 3 };
    const TASK_RANK = PRIORITY_RANK.medium; // las tareas no tienen prioridad: cuentan como media

    // Orden fijo de los grupos. "urgent" = mejoras críticas sin fecha (decisión del usuario).
    const WORK_GROUPS = [
        { key: 'overdue', label: 'Atrasado' },
        { key: 'today', label: 'Hoy' },
        { key: 'urgent', label: 'Urgente sin fecha', hint: 'Ponles fecha para planificarlas' },
        { key: 'week', label: 'Próximos 7 días' },
        { key: 'later', label: 'Más adelante' },
        { key: 'nodate', label: 'Sin fecha' }
    ];

    // Ids de las personas con las que se comparte una tarea: la API los da como "6,8".
    function taskShareIdList(task) {
        if (Array.isArray(task.shared_user_ids)) return task.shared_user_ids.map(Number);
        return global.parseShareIds(task.share_user_ids);
    }

    /**
     * "Para mí": no es de equipo y, si se comparte con personas, tú estás entre ellas;
     * si no se comparte, es tuya. Sin ids (API antigua) pero compartida: es para ti
     * si te la ha pasado otra persona, y delegada si es tuya.
     */
    function isTaskForViewer(task, viewerId) {
        if (Number(task.is_shared) === 1) return false;
        const ids = taskShareIdList(task);
        if (ids.length) return ids.includes(viewerId);
        if (Number(task.share_count || 0) > 0) return Number(task.user_id) !== viewerId;
        return Number(task.user_id) === viewerId;
    }

    /**
     * Tareas (ya filtradas por la API para quien mira) + mejoras activas, en un formato común.
     * viewer: { id, role, members } (members = equipo de /api/task-shares.php, para los nombres).
     * scope:
     *   'mine' (por defecto): tus tareas y las que te han pasado; mejoras asignadas a ti (también superadmin).
     *   'all': todas las tareas que llegan (delegadas y de equipo incluidas) y todas las mejoras activas.
     * Cada item dice para quién es (forWhom), sus personas (personIds) y quién te la pasó (from).
     */
    function buildWorkItems(tasks, requests, viewer, scope) {
        const viewerId = Number(viewer && viewer.id);
        const members = (viewer && viewer.members) || [];
        const all = scope === 'all';

        const taskItems = (tasks || [])
            .filter(task => Number(task.is_completed) !== 1)
            .filter(task => all || isTaskForViewer(task, viewerId))
            .map(task => {
                const ids = taskShareIdList(task);
                const forWhom = global.taskForWhom(Object.assign({}, task, { shared_user_ids: ids }), members, viewerId);
                let personIds = [];
                if (forWhom.kind === 'me' || forWhom.kind === 'owner') personIds = [Number(task.user_id)];
                if (forWhom.kind === 'people') personIds = ids;
                const fromOther = Number(task.user_id) !== viewerId;
                return {
                    key: `task-${task.id}`,
                    type: 'task',
                    id: Number(task.id),
                    title: task.title || '',
                    dueDate: parseDueDate(task.due_date) ? String(task.due_date).slice(0, 10) : null,
                    priority: null,
                    status: null,
                    appName: task.app_name || null,
                    assignees: [],
                    forWhom: { kind: forWhom.kind, label: forWhom.label, title: forWhom.title },
                    personIds,
                    from: fromOther ? (task.creator_name || task.creator_username || null) : null,
                    createdAt: task.created_at || '',
                    raw: task
                };
            });

        const requestItems = (requests || [])
            .filter(request => request.status === 'pending' || request.status === 'in_progress')
            .filter(request => all || (request.assignments || []).some(a => Number(a.id) === viewerId))
            .map(request => {
                const assignments = request.assignments || [];
                const personIds = assignments.map(a => Number(a.id));
                const summary = global.taskShareSummary(personIds, assignments, viewerId);
                return {
                    key: `request-${request.id}`,
                    type: 'request',
                    id: Number(request.id),
                    title: request.title || '',
                    dueDate: parseDueDate(request.due_date) ? String(request.due_date).slice(0, 10) : null,
                    priority: request.priority || 'medium',
                    status: request.status,
                    appName: request.app_name || null,
                    assignees: assignments.map(a => a.full_name || a.username || ''),
                    forWhom: personIds.length
                        ? { kind: 'people', label: summary.label, title: `Asignada a ${summary.title}` }
                        : { kind: 'unassigned', label: 'Sin asignar', title: 'Nadie la tiene asignada' },
                    personIds,
                    from: null,
                    createdAt: request.created_at || '',
                    raw: request
                };
            });

        return taskItems.concat(requestItems);
    }

    /**
     * Personas para el filtro de "Todo": tú primero ("Tú") y el resto por nombre, cada una
     * con cuántos items tiene; unassigned = mejoras sin nadie. Las tareas de equipo no
     * cuentan para nadie en concreto (solo salen en "Todas").
     */
    function workPeople(items, viewer) {
        const viewerId = Number(viewer && viewer.id);
        const names = {};
        ((viewer && viewer.members) || []).forEach(m => { names[Number(m.id)] = m.full_name || m.username; });
        (items || []).forEach(item => {
            if (item.type === 'request') {
                (item.raw.assignments || []).forEach(a => { names[Number(a.id)] = names[Number(a.id)] || a.full_name || a.username; });
            }
            if (item.type === 'task' && item.forWhom && item.forWhom.kind === 'owner') {
                names[Number(item.raw.user_id)] = names[Number(item.raw.user_id)] || item.forWhom.label;
            }
        });

        const counts = {};
        let unassigned = 0;
        (items || []).forEach(item => {
            if (item.type === 'request' && !item.personIds.length) unassigned++;
            item.personIds.forEach(id => { counts[id] = (counts[id] || 0) + 1; });
        });

        const people = Object.keys(counts).map(id => ({
            key: String(id),
            label: Number(id) === viewerId ? 'Tú' : (names[id] || `Persona ${id}`),
            count: counts[id]
        }));
        people.sort((a, b) => {
            if (a.key === String(viewerId)) return -1;
            if (b.key === String(viewerId)) return 1;
            return a.label.localeCompare(b.label, 'es', { sensitivity: 'base' });
        });

        return { people, unassigned };
    }

    // person: 'all' | 'none' (mejoras sin asignar) | id de la persona como texto.
    function filterWorkItemsByPerson(items, person) {
        if (!person || person === 'all') return (items || []).slice();
        if (person === 'none') return (items || []).filter(item => item.type === 'request' && !item.personIds.length);
        const id = Number(person);
        return (items || []).filter(item => item.personIds.includes(id));
    }

    function workGroupKey(item, today) {
        const due = parseDueDate(item.dueDate);
        if (!due) {
            return item.type === 'request' && item.priority === 'critical' ? 'urgent' : 'nodate';
        }
        const days = Math.round((due - startOfDay(today)) / DAY_MS);
        if (days < 0) return 'overdue';
        if (days === 0) return 'today';
        if (days <= 7) return 'week';
        return 'later';
    }

    // Fecha (sin fecha al final), prioridad, antigüedad (las más antiguas primero) y tipo/id para que sea estable.
    function compareWorkItems(a, b) {
        if (a.dueDate !== b.dueDate) {
            if (!a.dueDate) return 1;
            if (!b.dueDate) return -1;
            return a.dueDate < b.dueDate ? -1 : 1;
        }
        const rankA = a.type === 'task' ? TASK_RANK : (PRIORITY_RANK[a.priority] ?? TASK_RANK);
        const rankB = b.type === 'task' ? TASK_RANK : (PRIORITY_RANK[b.priority] ?? TASK_RANK);
        if (rankA !== rankB) return rankA - rankB;
        if (a.createdAt !== b.createdAt) return a.createdAt < b.createdAt ? -1 : 1;
        if (a.type !== b.type) return a.type === 'request' ? -1 : 1;
        return a.id - b.id;
    }

    /**
     * Grupos no vacíos en orden fijo. filter: 'all' | 'task' | 'request'.
     */
    function groupWorkItems(items, today, filter) {
        const ref = today || new Date();
        const wanted = filter === 'task' || filter === 'request' ? filter : null;
        const buckets = {};

        (items || []).forEach(item => {
            if (wanted && item.type !== wanted) return;
            const key = workGroupKey(item, ref);
            (buckets[key] = buckets[key] || []).push(item);
        });

        return WORK_GROUPS
            .filter(group => buckets[group.key])
            .map(group => Object.assign({}, group, { items: buckets[group.key].sort(compareWorkItems) }));
    }

    function countWorkItems(items) {
        const list = items || [];
        const task = list.filter(item => item.type === 'task').length;
        return { all: list.length, task, request: list.length - task };
    }

    // ===== Menú de fecha de cada fila (tareas y mejoras) =====

    const WEEKDAYS_SHORT = ['dom', 'lun', 'mar', 'mié', 'jue', 'vie', 'sáb'];

    function shortDayLabel(value) {
        const date = parseDueDate(value);
        return date ? `${WEEKDAYS_SHORT[date.getDay()]} ${date.getDate()} ${MONTHS[date.getMonth()]}` : '';
    }

    /**
     * Atajos del menú de fecha. Reutiliza taskDateShortcuts (task-parser.js),
     * los mismos que "Posponer" en Tareas. Si "Próximo lunes" y "En una semana"
     * caen el mismo día (hoy es lunes), se deja uno. "Quitar fecha" solo si hay.
     */
    function workDateMenuOptions(today, currentDue) {
        const shortcuts = global.taskDateShortcuts(today || new Date());
        const options = [
            { key: 'today', label: 'Hoy', value: shortcuts.today },
            { key: 'tomorrow', label: 'Mañana', value: shortcuts.tomorrow },
            { key: 'nextMonday', label: 'Próximo lunes', value: shortcuts.nextMonday }
        ];
        if (shortcuts.nextWeek !== shortcuts.nextMonday) {
            options.push({ key: 'nextWeek', label: 'En una semana', value: shortcuts.nextWeek });
        }
        options.forEach(option => {
            option.hint = shortDayLabel(option.value);
            option.active = option.value === currentDue;
        });

        options.push({ key: 'pick', label: 'Elegir fecha…', value: null, active: false });
        if (currentDue) options.push({ key: 'clear', label: 'Quitar fecha', value: '', active: false });
        return options;
    }

    // ===== Menú de urgencia de cada mejora =====

    const PRIORITY_OPTIONS = [
        { key: 'critical', label: 'Crítica' },
        { key: 'high', label: 'Alta' },
        { key: 'medium', label: 'Media' },
        { key: 'low', label: 'Baja' }
    ];

    // Las cuatro urgencias, de más a menos, con la actual marcada.
    function workPriorityMenuOptions(current) {
        return PRIORITY_OPTIONS.map(option => Object.assign({}, option, { active: option.key === current }));
    }

    global.parseDueDate = parseDueDate;
    global.workDateMenuOptions = workDateMenuOptions;
    global.workPriorityMenuOptions = workPriorityMenuOptions;
    global.requestDueInfo = requestDueInfo;
    global.buildWorkItems = buildWorkItems;
    global.workPeople = workPeople;
    global.filterWorkItemsByPerson = filterWorkItemsByPerson;
    global.groupWorkItems = groupWorkItems;
    global.countWorkItems = countWorkItems;
})(window);
