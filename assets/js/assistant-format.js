/**
 * Formato de respuestas del asistente: subconjunto mínimo de Markdown (párrafos, listas,
 * negrita, títulos y referencias [[tipo:ID]]) convertido a bloques. Sin DOM ni HTML:
 * assistant.js crea los nodos con textContent. Se prueba con Node en tests/.
 */

(function (global) {
    'use strict';

    const TOKEN = /\*\*(.+?)\*\*|\[\[((?:request|task|note):[1-9][0-9]*)\]\]/g;
    const BULLET = /^\s*[-*•]\s+(.*)$/;
    const NUMBERED = /^\s*\d{1,3}[.)]\s+(.*)$/;
    const HASH_HEADING = /^#{1,4}\s+(.+)$/;
    const BOLD_HEADING = /^\*\*([^*]+?)\*\*:?$/;

    function pushText(list, text) {
        if (!text) return;
        const last = list[list.length - 1];
        if (last && last.type === 'text') last.text += text;
        else list.push({ type: 'text', text });
    }

    function inline(text) {
        const out = [];
        let end = 0;
        for (const match of text.matchAll(TOKEN)) {
            pushText(out, text.slice(end, match.index));
            if (match[2]) out.push({ type: 'ref', key: match[2] });
            else if (match[1].trim()) out.push({ type: 'strong', children: inline(match[1]) });
            else pushText(out, match[0]);
            end = match.index + match[0].length;
        }
        pushText(out, text.slice(end));
        return out;
    }

    function heading(line) {
        const hash = HASH_HEADING.exec(line);
        if (hash) {
            const bold = BOLD_HEADING.exec(hash[1].trim());
            return inline(bold ? bold[1] : hash[1].trim());
        }
        const bold = BOLD_HEADING.exec(line);
        return bold ? inline(bold[1]) : null;
    }

    function parse(text) {
        const blocks = [];
        let paragraph = null, list = null;
        for (const raw of String(text || '').replace(/\r\n?/g, '\n').split('\n')) {
            const line = raw.trim();
            if (!line) { paragraph = null; continue; }
            const bullet = BULLET.exec(raw), numbered = bullet ? null : NUMBERED.exec(raw);
            if (bullet || numbered) {
                const ordered = Boolean(numbered);
                if (!list || list.ordered !== ordered) { list = { type: 'list', ordered, items: [] }; blocks.push(list); }
                list.items.push(inline((bullet || numbered)[1].trim()));
                paragraph = null;
                continue;
            }
            list = null;
            const title = heading(line);
            if (title) { blocks.push({ type: 'heading', inline: title }); paragraph = null; continue; }
            if (!paragraph) { paragraph = { type: 'paragraph', lines: [] }; blocks.push(paragraph); }
            paragraph.lines.push(inline(line));
        }
        return blocks;
    }

    // Texto para copiar: sin marcas y con el nombre legible de cada referencia.
    function plainText(text, labels) {
        return String(text || '').replace(/\r\n?/g, '\n').split('\n').map(raw => {
            let line = raw.replace(HASH_HEADING, '$1').replace(/^(\s*)[*•]\s+/, '$1- ');
            line = line.replace(TOKEN, (match, bold, key) => key ? (labels && labels[key]) || '[referencia no disponible]' : bold);
            return line.replace(/\s+$/, '');
        }).join('\n').trim();
    }

    global.PrismaAssistantFormat = Object.freeze({ parse, plainText });
})(window);
