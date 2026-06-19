/**
 * Parser de creación rápida de tareas (lenguaje natural, en español).
 * Detecta, dentro del texto escrito, una fecha límite y una aplicación,
 * devolviendo además los fragmentos de texto que las generaron para poder
 * limpiarlos del título o conservarlos si el usuario descarta la detección.
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

    // Patrones de fecha en orden de prioridad (el primero que casa, gana).
    // Cada entrada: { re, resolve(matchArray, today) -> Date|null }
    const DATE_PATTERNS = [
        {
            // Fecha numérica: 15/07, 15-7, 15/07/2026
            re: /\b(\d{1,2})[\/\-](\d{1,2})(?:[\/\-](\d{2,4}))?\b/i,
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

    /**
     * parseQuickTask(text, apps)
     * @param {string} text  Texto escrito por el usuario.
     * @param {Array}  apps  [{id, name}] aplicaciones del usuario.
     * @returns {{ date: {value:string, match:string}|null,
     *            app:  {id:number, name:string, match:string}|null }}
     */
    function parseQuickTask(text, apps) {
        const result = { date: null, app: null };
        if (!text) return result;
        const today = startOfToday();

        // --- Fecha ---
        for (const p of DATE_PATTERNS) {
            const m = text.match(p.re);
            if (!m) continue;
            const date = p.resolve(m, today);
            if (date) {
                result.date = { value: toISO(date), match: m[0] };
                break;
            }
        }

        // --- App: @term o #term ---
        const appMatch = text.match(/[@#]([\p{L}\d_-]+)/u);
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

    global.parseQuickTask = parseQuickTask;
    global.stripQuickMatch = stripMatch;
})(window);
