/**
 * Notas: funciones puras de presentación (sin DOM ni red), para notes.js.
 * Se prueban con Node en tests/notes-page.php.
 */

(function (global) {
    'use strict';

    const MONTHS = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

    // Texto plano a partir de Markdown, para el extracto de la lista.
    function noteExcerpt(markdown, max) {
        const limit = max || 160;
        const text = String(markdown || '')
            .replace(/```[^\n]*\n?/g, ' ')                               // vallas de código
            .replace(/`([^`]*)`/g, '$1')                                 // código en línea
            .replace(/!\[([^\]]*)\]\([^)]*\)/g, '$1')                    // imágenes: su texto alternativo
            .replace(/\[([^\]]*)\]\([^)]*\)/g, '$1')                     // enlaces: su texto
            .replace(/<[^>]+>/g, ' ')                                    // HTML
            .replace(/^\s{0,3}#{1,6}\s+/gm, '')                          // títulos
            .replace(/^\s{0,3}>\s?/gm, '')                               // citas
            .replace(/^\s*([-*_])(\s*\1){2,}\s*$/gm, ' ')                // separadores
            .replace(/^\s*(?:[-*+]|\d+[.)])\s+(?:\[[ xX]\]\s+)?/gm, '')  // listas y casillas
            .replace(/\*\*(.+?)\*\*/g, '$1')
            .replace(/~~(.+?)~~/g, '$1')
            .replace(/\*(?=\S)(.+?)\*/g, '$1')
            .replace(/(^|\s)__?(?=\S)(.+?)__?(?=\s|$|[.,;:!?])/g, '$1$2')
            .replace(/\|/g, ' ')
            .replace(/\s+/g, ' ')
            .trim();

        if (text.length <= limit) return text;

        const cut = text.slice(0, limit + 1);
        const lastSpace = cut.lastIndexOf(' ');
        const short = lastSpace > limit * 0.5 ? cut.slice(0, lastSpace) : text.slice(0, limit);
        return short.replace(/[\s.,;:]+$/, '') + '…';
    }

    // "AAAA-MM-DD HH:MM:SS" de MySQL en hora local.
    function parseNoteDate(value) {
        const m = /^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})(?::(\d{2}))?/.exec(String(value || ''));
        if (!m) return null;
        return new Date(+m[1], +m[2] - 1, +m[3], +m[4], +m[5], +(m[6] || 0));
    }

    // Tiempo relativo corto: "Ahora mismo", "Hace 25 min", "Hace 3 h", "Ayer", "Hace 4 días", "2 mar".
    function formatNoteTime(value, now) {
        const date = parseNoteDate(value);
        if (!date) return '';
        const ref = now || new Date();

        const minutes = Math.floor((ref - date) / 60000);
        if (minutes < 1) return 'Ahora mismo';
        if (minutes < 60) return `Hace ${minutes} min`;

        const startOfRef = new Date(ref.getFullYear(), ref.getMonth(), ref.getDate());
        const startOfDate = new Date(date.getFullYear(), date.getMonth(), date.getDate());
        const days = Math.round((startOfRef - startOfDate) / 86400000);
        if (days === 0) return `Hace ${Math.floor(minutes / 60)} h`;
        if (days === 1) return 'Ayer';
        if (days < 7) return `Hace ${days} días`;

        const label = `${date.getDate()} ${MONTHS[date.getMonth()]}`;
        return date.getFullYear() === ref.getFullYear() ? label : `${label} ${date.getFullYear()}`;
    }

    // Fecha completa para el title: "19 oct 2026, 12:05".
    function formatNoteDateTime(value) {
        const date = parseNoteDate(value);
        if (!date) return '';
        const time = `${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}`;
        return `${date.getDate()} ${MONTHS[date.getMonth()]} ${date.getFullYear()}, ${time}`;
    }

    // Con quién se comparte, contado desde quien mira la nota.
    function noteShareSummary(note, isOwner) {
        if (Number(note.is_shared) === 1) return { icon: 'iconoir-group', label: 'Todo el equipo' };
        if (!isOwner) return { icon: 'iconoir-user', label: 'Compartida contigo' };

        const count = Number(note.share_count || 0);
        if (count > 0) return { icon: 'iconoir-user', label: count === 1 ? '1 persona' : `${count} personas` };
        return { icon: 'iconoir-lock', label: 'Solo yo' };
    }

    global.noteExcerpt = noteExcerpt;
    global.parseNoteDate = parseNoteDate;
    global.formatNoteTime = formatNoteTime;
    global.formatNoteDateTime = formatNoteDateTime;
    global.noteShareSummary = noteShareSummary;
})(window);
