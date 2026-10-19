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

    /**
     * Tareas (ya filtradas por la API para quien mira) + mejoras activas, en un
     * formato común. Superadmin ve todas las mejoras activas; el resto, las suyas.
     * viewer: { id, role }
     */
    function buildWorkItems(tasks, requests, viewer) {
        const viewerId = Number(viewer && viewer.id);
        const seeAll = viewer && viewer.role === 'superadmin';

        const taskItems = (tasks || [])
            .filter(task => Number(task.is_completed) !== 1)
            .map(task => ({
                key: `task-${task.id}`,
                type: 'task',
                id: Number(task.id),
                title: task.title || '',
                dueDate: parseDueDate(task.due_date) ? String(task.due_date).slice(0, 10) : null,
                priority: null,
                status: null,
                appName: task.app_name || null,
                assignees: [],
                createdAt: task.created_at || '',
                raw: task
            }));

        const requestItems = (requests || [])
            .filter(request => request.status === 'pending' || request.status === 'in_progress')
            .filter(request => seeAll || (request.assignments || []).some(a => Number(a.id) === viewerId))
            .map(request => ({
                key: `request-${request.id}`,
                type: 'request',
                id: Number(request.id),
                title: request.title || '',
                dueDate: parseDueDate(request.due_date) ? String(request.due_date).slice(0, 10) : null,
                priority: request.priority || 'medium',
                status: request.status,
                appName: request.app_name || null,
                assignees: (request.assignments || []).map(a => a.full_name || a.username || ''),
                createdAt: request.created_at || '',
                raw: request
            }));

        return taskItems.concat(requestItems);
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

    global.parseDueDate = parseDueDate;
    global.workDateMenuOptions = workDateMenuOptions;
    global.requestDueInfo = requestDueInfo;
    global.buildWorkItems = buildWorkItems;
    global.groupWorkItems = groupWorkItems;
    global.countWorkItems = countWorkItems;
})(window);
