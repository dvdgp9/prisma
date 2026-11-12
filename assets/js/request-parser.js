/** Creación rápida de mejoras. Reutiliza las fechas y personas del parser de tareas. */
(function (global) {
    const normalize = value => String(value).normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
    const levels = { critica: 'critical', alta: 'high', media: 'medium', baja: 'low', urgente: 'critical' };

    function findQuickRequestApp(term, apps) {
        const query = normalize(term);
        const exact = apps.filter(app => normalize(app.name) === query);
        const prefix = apps.filter(app => normalize(app.name).startsWith(query));
        const matches = exact.length ? exact : prefix.length ? prefix : apps.filter(app => normalize(app.name).includes(query));
        return matches.length === 1 ? matches[0] : null;
    }

    // Solo la primera línea lleva atajos. El resto es descripción literal, salvo
    // Solicitante:, Email: y pasos explícitos «- [ ] …». Descartar un chip conserva su texto.
    function parseQuickRequest(raw, apps, members, options = {}) {
        const ignore = options.ignore || {};
        const canPlan = options.canPlan !== false;
        const lines = String(raw || '').trim().split(/\r?\n/);
        let header = lines.shift() || '';
        const inlineDescription = header.search(/\s+(?:::|--)\s+/);
        if (inlineDescription !== -1) {
            const separator = header.slice(inlineDescription).match(/^\s+(?:::|--)\s+/)[0];
            lines.unshift(header.slice(inlineDescription + separator.length));
            header = header.slice(0, inlineDescription);
        }
        const result = {
            title: '', description: '', app: null, date: null, people: [],
            priority: null, difficulty: null, requesterName: '', requesterEmail: '',
            checklist: [], chips: [], errors: []
        };
        const spans = [];
        let rest = header;
        function consume(kind, match, index, label) {
            if (!ignore[kind]) {
                spans.push({ index, length: match.length });
                result.chips.push({ kind, label });
            }
            // Tapar incluso lo descartado evita que «@mañana» se convierta en una fecha.
            rest = rest.slice(0, index) + '|'.repeat(match.length) + rest.slice(index + match.length);
            return !ignore[kind];
        }

        const appRe = /(^|\s)[@#](?:"([^"]+)"|'([^']+)'|([\p{L}\d_-]+))/gu;
        let match;
        while ((match = appRe.exec(header))) {
            const token = match[0].slice(match[1].length);
            const index = match.index + match[1].length;
            const app = findQuickRequestApp(match[2] || match[3] || match[4], apps);
            if (!app && !ignore.app) result.errors.push(`La aplicación ${token} no existe o coincide con varias. Escribe su nombre completo.`);
            if (result.app && !ignore.app) result.errors.push('Indica una sola aplicación por mejora.');
            if (consume('app', token, index, app ? app.name : token) && app) result.app = app;
        }

        const properties = [
            ['priority', /\bprioridad\s*:?\s*(cr[ií]tica|alta|media|baja)\b|(?:^|\s)!(cr[ií]tica|alta|media|baja|urgente)\b/i, 'Prioridad'],
            ['difficulty', /\bdificultad\s*:?\s*(alta|media|baja)\b/i, 'Dificultad']
        ];
        properties.forEach(([kind, re, label]) => {
            const found = rest.match(re);
            if (!found) return;
            const value = normalize(found[1] || found[2]);
            if (consume(kind, found[0], found.index, `${label} ${value}`)) result[kind] = levels[value];
        });

        if (canPlan) {
            // Se repite para admitir varios +nombres; el orden decide la responsable.
            let parsed = global.parseQuickTask(rest, [], members);
            while (parsed.person) {
                const person = parsed.person;
                const index = rest.indexOf(person.match);
                if (consume('person', person.match, index, person.name)
                    && !result.people.some(p => Number(p.id) === person.id)) result.people.push(person);
                parsed = global.parseQuickTask(rest, [], members);
            }
            // Un nombre explícito desconocido o ambiguo nunca se asigna por aproximación.
            const unknownPeople = rest.match(/(?:^|\s)\+[\p{L}\d_-]+/gu) || [];
            if (!ignore.person) unknownPeople.forEach(name => result.errors.push(`No se ha identificado ${name.trim()}. Escribe más letras del nombre o apellido.`));
            if (parsed.date) {
                const date = parsed.date;
                let index = rest.indexOf(date.match);
                const prefix = rest.slice(0, index).match(/\bpara\s+$/i);
                const fragment = prefix ? prefix[0] + date.match : date.match;
                if (prefix) index -= prefix[0].length;
                if (consume('date', fragment, index, `Fecha: ${date.value}`)) result.date = date;
            }
        }

        // Los índices pertenecen al original: retirar de derecha a izquierda conserva los límites.
        let title = header;
        spans.sort((a, b) => b.index - a.index).forEach(span => {
            title = title.slice(0, span.index) + title.slice(span.index + span.length);
        });
        result.title = title.replace(/\s{2,}/g, ' ').trim();
        const description = [];
        lines.forEach(line => {
            const requester = line.match(/^\s*(Solicitante|Email)\s*:\s*(.+)\s*$/i);
            const step = line.match(/^\s*-\s*\[ \]\s+(.+)$/);
            if (requester && !ignore.requester) {
                const key = normalize(requester[1]) === 'email' ? 'requesterEmail' : 'requesterName';
                result[key] = requester[2].trim();
            } else if (step && !ignore.checklist) {
                const text = step[1].trim();
                if (Array.from(text).length > 500) result.errors.push('Cada paso puede tener como máximo 500 caracteres.');
                result.checklist.push(text);
            } else description.push(line);
        });
        result.description = description.join('\n').trim();
        if (result.requesterName || result.requesterEmail) result.chips.push({ kind: 'requester', label: `Solicitante: ${result.requesterName || result.requesterEmail}` });
        if (result.requesterEmail && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(result.requesterEmail)) result.errors.push('Revisa el email del solicitante.');
        if (result.checklist.length) result.chips.push({ kind: 'checklist', label: `${result.checklist.length} ${result.checklist.length === 1 ? 'paso' : 'pasos'}` });
        return result;
    }

    function quickRequestData(parsed, fallbackAppId) {
        const draft = global.newRequestDraft({ appId: parsed.app ? Number(parsed.app.id) : fallbackAppId });
        draft.priority = parsed.priority || draft.priority;
        draft.difficulty = parsed.difficulty || '';
        draft.dueDate = parsed.date ? parsed.date.value : '';
        draft.assignees = parsed.people.map(p => ({ id: p.id, full_name: p.name }));
        draft.checklist = parsed.checklist.slice();
        return {
            draft,
            fields: { title: parsed.title, description: parsed.description, requesterName: parsed.requesterName, requesterEmail: parsed.requesterEmail }
        };
    }

    global.parseQuickRequest = parseQuickRequest;
    global.quickRequestData = quickRequestData;
})(window);
