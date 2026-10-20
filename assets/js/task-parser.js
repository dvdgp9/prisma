/**
 * Parser de creación rápida de tareas (lenguaje natural, en español).
 * Detecta, dentro del texto escrito, una fecha límite, una aplicación y la
 * persona a la que se asigna, devolviendo además los fragmentos de texto que
 * las generaron para poder limpiarlos del título o conservarlos si el usuario
 * descarta la detección.
 *
 * Sin dependencias externas. Resolución de fechas en horario local.
 */

(function (global) {
    'use strict';

    // Quita acentos y pasa a minúsculas para comparaciones tolerantes.
    function normalize(str) {
        return (str || '')
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '');
    }

    // Fecha local (sin componente horario) a partir de año/mes/día.
    function localDate(y, m, d) {
        return new Date(y, m, d);
    }

    function startOfToday() {
        const n = new Date();
        return localDate(n.getFullYear(), n.getMonth(), n.getDate());
    }

    function toISO(date) {
        const y = date.getFullYear();
        const m = String(date.getMonth() + 1).padStart(2, '0');
        const d = String(date.getDate()).padStart(2, '0');
        return `${y}-${m}-${d}`;
    }

    function addDays(date, days) {
        const r = new Date(date.getTime());
        r.setDate(r.getDate() + days);
        return r;
    }

    // Días de la semana: nombre/abreviatura normalizados -> índice (0 = domingo).
    const WEEKDAYS = {
        domingo: 0, dom: 0,
        lunes: 1, lun: 1,
        martes: 2, mar: 2,
        miercoles: 3, mie: 3,
        jueves: 4, jue: 4,
        viernes: 5, vie: 5,
        sabado: 6, sab: 6
    };

    // Meses por nombre normalizado -> índice (0 = enero).
    const MONTHS = {
        enero: 0, febrero: 1, marzo: 2, abril: 3, mayo: 4, junio: 5,
        julio: 6, agosto: 7, septiembre: 8, setiembre: 8,
        octubre: 9, noviembre: 10, diciembre: 11
    };

    // Prefijo opcional de día de la semana (para absorberlo en fechas explícitas:
    // "martes 30 junio" / "viernes 15/07" se limpian enteros del título).
    const WD_PREFIX = '(?:(?:lunes|martes|mi[ée]rcoles|jueves|viernes|s[áa]bado|domingo|lun|mar|mie|mié|jue|vie|sab|s[áa]b|dom)\\s+)?';

    // Patrones de fecha en orden de prioridad (el primero que casa, gana).
    // Cada entrada: { re, resolve(matchArray, today) -> Date|null }
    const DATE_PATTERNS = [
        {
            // Fecha con mes por nombre: "30 junio", "30 de junio", "1 de julio de 2026".
            // Absorbe un día de la semana previo opcional ("martes 30 junio").
            re: new RegExp('\\b' + WD_PREFIX + '(\\d{1,2})\\s+(?:de\\s+)?(enero|febrero|marzo|abril|mayo|junio|julio|agosto|septiembre|setiembre|octubre|noviembre|diciembre)(?:\\s+(?:de\\s+)?(\\d{4}))?\\b', 'i'),
            resolve: function (m, today) {
                const day = parseInt(m[1], 10);
                const month = MONTHS[normalize(m[2])];
                if (month === undefined || day < 1 || day > 31) return null;
                let year = m[3] ? parseInt(m[3], 10) : today.getFullYear();
                let date = localDate(year, month, day);
                if (date.getMonth() !== month) return null; // día inválido (ej. 31 de febrero)
                if (!m[3] && date < today) date = localDate(year + 1, month, day);
                return date;
            }
        },
        {
            // Fecha numérica: 15/07, 15-7, 15/07/2026 (con día de semana previo opcional).
            re: new RegExp('\\b' + WD_PREFIX + '(\\d{1,2})[\\/\\-](\\d{1,2})(?:[\\/\\-](\\d{2,4}))?\\b', 'i'),
            resolve: function (m, today) {
                const day = parseInt(m[1], 10);
                const month = parseInt(m[2], 10);
                let year = m[3] ? parseInt(m[3], 10) : today.getFullYear();
                if (m[3] && m[3].length === 2) year += 2000;
                if (month < 1 || month > 12 || day < 1 || day > 31) return null;
                let date = localDate(year, month - 1, day);
                if (date.getMonth() !== month - 1) return null; // día inválido (ej. 31/02)
                // Sin año explícito y ya pasó: lo entendemos como el año que viene.
                if (!m[3] && date < today) date = localDate(year + 1, month - 1, day);
                return date;
            }
        },
        {
            re: /\bpasado\s+ma(?:ñ|n)ana\b/i,
            resolve: function (m, today) { return addDays(today, 2); }
        },
        {
            re: /\bma(?:ñ|n)ana\b/i,
            resolve: function (m, today) { return addDays(today, 1); }
        },
        {
            re: /\bhoy\b/i,
            resolve: function (m, today) { return today; }
        },
        {
            // "en 3 días", "en 2 semanas"
            re: /\ben\s+(\d{1,3})\s+(d[ií]as?|semanas?)\b/i,
            resolve: function (m, today) {
                const n = parseInt(m[1], 10);
                const unit = normalize(m[2]);
                return addDays(today, unit.indexOf('semana') === 0 ? n * 7 : n);
            }
        },
        {
            re: /\b(?:la\s+)?(?:pr[óo]xima\s+semana|semana\s+que\s+viene)\b/i,
            resolve: function (m, today) {
                // Lunes de la próxima semana.
                const dow = today.getDay();
                const daysToNextMonday = ((8 - dow) % 7) || 7;
                return addDays(today, daysToNextMonday);
            }
        },
        {
            // Día de la semana (nombre o abreviatura), opcional "el"/"este"/"próximo".
            re: /\b(?:el\s+|este\s+|pr[óo]ximo\s+)?(lunes|martes|mi[ée]rcoles|jueves|viernes|s[áa]bado|domingo|lun|mar|mie|mié|jue|vie|sab|s[áa]b|dom)\b/i,
            resolve: function (m, today) {
                const key = normalize(m[1]);
                if (!(key in WEEKDAYS)) return null;
                const target = WEEKDAYS[key];
                let diff = (target - today.getDay() + 7) % 7;
                if (diff === 0) diff = 7; // mismo día -> la semana que viene ("hoy" cubre hoy)
                return addDays(today, diff);
            }
        }
    ];

    // Busca una app por término capturado tras @ o #.
    function matchApp(term, apps) {
        if (!term || !apps || !apps.length) return null;
        const t = normalize(term);
        if (!t) return null;
        // Prioridad: prefijo exacto del nombre; luego inclusión.
        let found = apps.find(a => normalize(a.name).startsWith(t));
        if (!found) found = apps.find(a => normalize(a.name).indexOf(t) !== -1);
        return found || null;
    }

    // Una sola persona que encaje; con varias, ninguna (hay que escribir más letras).
    function uniqueMember(list) {
        return list.length === 1 ? list[0] : null;
    }

    /**
     * Persona a la que se asigna la tarea:
     * - "para Rebecca": el nombre de pila entero (y el apellido si va detrás), para
     *   que "para la reunión" no sea Laura.
     * - "+rebe": el principio del nombre o, si no, del apellido ("+soler").
     * Gana la primera que aparece en el texto.
     */
    function detectPerson(text, members) {
        if (!members || !members.length) return null;
        const people = members
            .map(m => {
                const name = m.full_name || m.username || '';
                return { id: parseInt(m.id, 10), name, words: normalize(name).split(/\s+/).filter(Boolean) };
            })
            .filter(p => p.words.length);
        const candidates = [];
        let m;

        const plusRe = /(^|\s)\+([\p{L}\d_-]+)/gu;
        while ((m = plusRe.exec(text))) {
            const term = normalize(m[2]);
            const byFirstName = people.filter(p => p.words[0].startsWith(term));
            const person = byFirstName.length
                ? uniqueMember(byFirstName)
                : uniqueMember(people.filter(p => p.words.some(w => w.startsWith(term))));
            if (person) candidates.push({ index: m.index + m[1].length, match: '+' + m[2], person });
        }

        const paraRe = /(^|[\s(])(para\s+)(\p{L}+)/giu;
        while ((m = paraRe.exec(text))) {
            const person = uniqueMember(people.filter(p => p.words[0] === normalize(m[3])));
            if (!person) continue;
            let match = m[2] + m[3];
            const next = /^\s+(\p{L}+)/u.exec(text.slice(m.index + m[0].length));
            if (next && person.words[1] && normalize(next[1]) === person.words[1]) match += next[0];
            candidates.push({ index: m.index + m[1].length, match, person });
        }

        candidates.sort((a, b) => a.index - b.index);
        return candidates[0] || null;
    }

    /**
     * parseQuickTask(text, apps, members)
     * @param {string} text     Texto escrito por el usuario.
     * @param {Array}  apps     [{id, name}] aplicaciones del usuario.
     * @param {Array}  members  [{id, full_name, username}] equipo (opcional, para asignar).
     * @returns {{ date:   {value:string, match:string}|null,
     *            app:    {id:number, name:string, match:string}|null,
     *            person: {id:number, name:string, match:string}|null }}
     */
    function parseQuickTask(text, apps, members) {
        const result = { date: null, app: null, person: null };
        if (!text) return result;
        const today = startOfToday();

        // --- Persona: primero, y se tapa su fragmento para que "+mar" no se lea como "martes" ---
        let rest = text;
        const found = detectPerson(text, members);
        if (found) {
            result.person = { id: found.person.id, name: found.person.name, match: found.match };
            rest = text.slice(0, found.index) + '|'.repeat(found.match.length) + text.slice(found.index + found.match.length);
        }

        // --- Fecha ---
        for (const p of DATE_PATTERNS) {
            const m = rest.match(p.re);
            if (!m) continue;
            const date = p.resolve(m, today);
            if (date) {
                result.date = { value: toISO(date), match: m[0] };
                break;
            }
        }

        // --- App: @term o #term ---
        const appMatch = rest.match(/[@#]([\p{L}\d_-]+)/u);
        if (appMatch) {
            const app = matchApp(appMatch[1], apps);
            if (app) {
                result.app = { id: parseInt(app.id, 10), name: app.name, match: appMatch[0] };
            }
        }

        return result;
    }

    // Elimina la primera aparición de un fragmento y colapsa espacios.
    function stripMatch(text, match) {
        if (!match) return text;
        const idx = text.indexOf(match);
        if (idx === -1) return text;
        const out = text.slice(0, idx) + text.slice(idx + match.length);
        return out.replace(/\s{2,}/g, ' ').trim();
    }

    // Título sin los fragmentos detectados (persona, fecha y app) que no se han descartado.
    // Si no queda nada, el texto tal cual.
    function quickTaskTitle(raw, parsed, ignore) {
        const skip = ignore || {};
        let title = raw;
        if (parsed.person && !skip.person) title = stripMatch(title, parsed.person.match);
        if (parsed.date && !skip.date) title = stripMatch(title, parsed.date.match);
        if (parsed.app && !skip.app) title = stripMatch(title, parsed.app.match);
        title = title.trim();
        return title || raw.trim();
    }

    function capitalizeFirstLetter(text) {
        if (!text) return text;
        return text.charAt(0).toUpperCase() + text.slice(1);
    }

    /**
     * Convierte títulos técnicos de mejoras en títulos más naturales para tareas.
     * No inventa contenido: solo retira metadatos habituales y limpia puntuación.
     */
    function normalizeRequestTitleForTask(title) {
        const original = (title || '').trim();
        if (!original) return '';

        let cleaned = original
            .replace(/\s+/g, ' ')
            .replace(/^[\s:;,.|/\\-]+/, '')
            .trim();

        // Metadatos iniciales tipo "[BUG]", "[Puri]", "(fix)".
        cleaned = cleaned
            .replace(/^(\[[^\]]{1,32}\]|\([^)()]{1,32}\))\s*[:\-–—|]?\s*/i, '')
            .trim();

        // Prefijos técnicos o de commit al inicio del título.
        cleaned = cleaned
            .replace(/^(?:add|añadir|agregar|fix|arreglar|corregir|bug|hotfix|feat|feature|update|actualizar|improve|mejorar|mejora|change|cambiar|remove|eliminar|delete|borrar|refactor|style|ui|ux|task|tarea)\s*(?:\([^)]{1,32}\))?\s*[:\-–—|]\s*/i, '')
            .trim();

        // Limpieza final por si el prefijo deja separadores sueltos.
        cleaned = cleaned
            .replace(/^[\s:;,.|/\\-]+/, '')
            .replace(/\s{2,}/g, ' ')
            .trim();

        if (!cleaned) cleaned = original;
        return capitalizeFirstLetter(cleaned);
    }

    // Fechas de los atajos de "Posponer" a partir de un día local.
    // "Próximo lunes" nunca es hoy: si hoy es lunes, es el de la semana siguiente.
    function taskDateShortcuts(today) {
        const base = localDate(today.getFullYear(), today.getMonth(), today.getDate());
        const daysToMonday = ((8 - base.getDay()) % 7) || 7;
        return {
            today: toISO(base),
            tomorrow: toISO(addDays(base, 1)),
            nextMonday: toISO(addDays(base, daysToMonday)),
            nextWeek: toISO(addDays(base, 7))
        };
    }

    // Búsqueda tolerante (sin acentos ni mayúsculas) en título, descripción y app.
    // Todas las palabras de la búsqueda tienen que aparecer.
    function matchesTaskSearch(task, query) {
        const words = normalize(query).split(/\s+/).filter(Boolean);
        if (words.length === 0) return true;
        const haystack = normalize([task.title, task.description, task.app_name].filter(Boolean).join(' '));
        return words.every(word => haystack.indexOf(word) !== -1);
    }

    // Id de la mejora de origen si la tarea se creó desde una ("Creada desde mejora #142: ...").
    function taskSourceRequestId(description) {
        const match = /Creada desde mejora #(\d+)/.exec(description || '');
        return match ? parseInt(match[1], 10) : null;
    }

    // "hoy" / "ayer" / "hace N días" para una fecha "YYYY-MM-DD HH:MM:SS" (horario local).
    function taskRelativeDay(dateTime, today) {
        if (!dateTime) return '';
        const [y, m, d] = dateTime.slice(0, 10).split('-').map(Number);
        const base = localDate(today.getFullYear(), today.getMonth(), today.getDate());
        const days = Math.round((base - localDate(y, m - 1, d)) / 86400000);
        if (days <= 0) return 'hoy';
        if (days === 1) return 'ayer';
        return `hace ${days} días`;
    }

    global.parseQuickTask = parseQuickTask;
    global.taskSourceRequestId = taskSourceRequestId;
    global.taskRelativeDay = taskRelativeDay;
    // Ids de "con quién se comparte" tal como los da la API (GROUP_CONCAT: "6,8"), sin repetidos.
    function parseShareIds(value) {
        const ids = String(value || '').split(',')
            .map(part => parseInt(part.trim(), 10))
            .filter(id => Number.isFinite(id) && id > 0);
        return ids.filter((id, index) => ids.indexOf(id) === index);
    }

    // Con quién se comparte una tarea: etiqueta corta, lista completa (title) y frase
    // para "Compartida …" ("contigo y con Luis"). "Tú" primero y el resto por nombre;
    // quien ya no está en el equipo cuenta como "más".
    function taskShareSummary(ids, members, meId) {
        let includesMe = false;
        let unknown = 0;
        const names = [];

        (ids || []).forEach(id => {
            if (Number(id) === Number(meId)) {
                includesMe = true;
                return;
            }
            const member = (members || []).find(m => Number(m.id) === Number(id));
            if (member) names.push(member.full_name || member.username);
            else unknown++;
        });

        names.sort((a, b) => a.localeCompare(b, 'es', { sensitivity: 'base' }));
        if (includesMe) names.unshift('Tú');

        if (!names.length) {
            const label = unknown ? `${unknown} ${unknown === 1 ? 'persona' : 'personas'}` : '';
            return { label, title: label, phrase: label ? `con ${label}` : '' };
        }

        const joinNames = parts => parts.length <= 1 ? parts.join('') : `${parts.slice(0, -1).join(', ')} y ${parts[parts.length - 1]}`;
        const total = names.length + unknown;
        let label;
        if (total === 1) label = names[0];
        else if (total === 2 && !unknown) label = `${names[0]} y ${names[1]}`;
        else label = `${names[0]} y ${total - 1} más`;

        const title = joinNames(unknown ? names.concat(`${unknown} más`) : names);
        const others = includesMe ? names.slice(1) : names;
        const othersText = joinNames(unknown ? others.concat(`${unknown} más`) : others);
        const phrase = includesMe
            ? (othersText ? `contigo y con ${othersText}` : 'contigo')
            : `con ${othersText}`;

        return { label, title, phrase };
    }

    // Para quién es una tarea: etiqueta de la tarjeta (y de la vista "Todo" de la Vista global).
    // kind: me (tuya y privada) | people (compartida con personas) | team | owner (privada de otra persona).
    function taskForWhom(task, members, meId) {
        if (Number(task.is_shared) === 1) {
            return { kind: 'team', label: 'Equipo', count: 'Equipo', title: 'Compartida con todo el equipo', icon: 'iconoir-group' };
        }

        const ids = Array.isArray(task.shared_user_ids) ? task.shared_user_ids : null;
        const shareCount = Number(task.share_count || 0);
        if ((ids && ids.length) || shareCount > 0) {
            const summary = taskShareSummary(ids || [], members, meId);
            const total = ids && ids.length ? ids.length : shareCount;
            const countLabel = `${total} ${total === 1 ? 'persona' : 'personas'}`;
            return {
                kind: 'people',
                label: summary.label || countLabel,
                count: String(total),
                title: `Compartida ${summary.phrase || `con ${countLabel}`}`,
                icon: 'iconoir-user'
            };
        }

        if (Number(task.user_id) === Number(meId)) {
            return { kind: 'me', label: 'Tú', count: 'Tú', title: 'Solo para ti', icon: 'iconoir-user' };
        }

        const owner = task.creator_name || task.creator_username || 'Otra persona';
        return { kind: 'owner', label: owner, count: owner, title: `De ${owner}`, icon: 'iconoir-user' };
    }

    // Personas con las que se comparte por nombre y cuándo la vio cada una (API: share_status).
    function taskShareStatus(task) {
        return (Array.isArray(task && task.share_status) ? task.share_status : [])
            .map(s => ({ userId: Number(s.user_id), seenAt: s.seen_at || null }));
    }

    // Te la han asignado y todavía no la has visto: lleva "Nueva".
    function taskIsNewForMe(task, meId) {
        if (Number(task.user_id) === Number(meId)) return false;
        return taskShareStatus(task).some(s => s.userId === Number(meId) && !s.seenAt);
    }

    // "hoy a las 10:42", "ayer a las 09:05" o "el 14 oct a las 18:30" ("YYYY-MM-DD HH:MM:SS" local).
    function seenWhen(seenAt, now) {
        const [datePart, timePart = '00:00'] = String(seenAt).split(' ');
        const [y, m, d] = datePart.split('-').map(Number);
        const day = localDate(y, m - 1, d);
        const today = localDate(now.getFullYear(), now.getMonth(), now.getDate());
        const days = Math.round((today - day) / 86400000);
        const time = timePart.slice(0, 5);
        if (days <= 0) return `hoy a las ${time}`;
        if (days === 1) return `ayer a las ${time}`;
        return `el ${day.toLocaleDateString('es-ES', { day: 'numeric', month: 'short' })} a las ${time}`;
    }

    // Para quien asigna una tarea abierta: ✓ si alguien no la ha visto, ✓✓ cuando la han visto todas.
    // null en tareas de otra persona, de todo el equipo, completadas o sin personas.
    function taskSeenReceipt(task, members, meId, now) {
        if (Number(task.user_id) !== Number(meId) || Number(task.is_shared) === 1 || Number(task.is_completed) === 1) return null;
        const people = taskShareStatus(task).filter(s => s.userId !== Number(meId));
        if (!people.length) return null;

        const lines = people.map(s => {
            const member = (members || []).find(m => Number(m.id) === s.userId);
            const name = member ? (member.full_name || member.username) : 'Otra persona';
            return { name, text: s.seenAt ? `${name} la vio ${seenWhen(s.seenAt, now)}` : `${name} aún no la ha visto` };
        });
        lines.sort((a, b) => a.name.localeCompare(b.name, 'es', { sensitivity: 'base' }));

        const seen = people.filter(s => s.seenAt).length;
        const state = seen === people.length ? 'seen' : seen === 0 ? 'unseen' : 'partial';
        return {
            state,
            icon: state === 'seen' ? 'iconoir-double-check' : 'iconoir-check',
            label: state === 'seen' ? 'Vista' : state === 'unseen' ? 'Sin ver' : `Vista por ${seen} de ${people.length}`,
            title: lines.map(l => l.text).join(' · ')
        };
    }

    global.taskForWhom = taskForWhom;
    global.taskIsNewForMe = taskIsNewForMe;
    global.taskSeenReceipt = taskSeenReceipt;
    global.parseShareIds = parseShareIds;
    global.taskShareSummary = taskShareSummary;
    global.stripQuickMatch = stripMatch;
    global.quickTaskTitle = quickTaskTitle;
    global.normalizeRequestTitleForTask = normalizeRequestTitleForTask;
    global.taskDateShortcuts = taskDateShortcuts;
    global.matchesTaskSearch = matchesTaskSearch;
})(window);
