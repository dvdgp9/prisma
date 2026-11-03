<?php

// Revisión del panel (3 Nov 2026), R1: formato de respuestas del asistente
// (assets/js/assistant-format.js), con Node. El panel construye nodos DOM a partir de
// estos bloques; nunca usa innerHTML, así que el HTML del modelo se queda como texto.

$node = <<<'JS'
global.window = global;
require(process.argv[2]);
const F = window.PrismaAssistantFormat;
const out = {};
out.paragraphs = F.parse('Hola.\nSegunda línea.\n\nOtro párrafo.');
out.lists = F.parse('**Vencen esta semana**\n- Uno [[request:41]]\n* Dos\n\n1. Primero\n2) Segundo');
out.heading = F.parse('### Resumen\nTexto');
out.unclosed = F.parse('Un **asterisco suelto y 2 * 3');
out.html = F.parse('<script>alert(1)</script> **<b>x</b>**');
out.boldRef = F.parse('**Mira [[task:7]]**');
out.plain = F.plainText('**Hoy**\n- Llamar [[task:7]]\n- Revisar [[note:3]]', { 'task:7': 'Tarea 7: Llamar a Marta' });
out.emptyBold = F.parse('a **** b');
console.log(JSON.stringify(out));
JS;

$script = tempnam(sys_get_temp_dir(), 'assistant-format');
file_put_contents($script, $node);
$output = shell_exec('node ' . escapeshellarg($script) . ' ' . escapeshellarg(__DIR__ . '/../assets/js/assistant-format.js') . ' 2>&1');
unlink($script);
$r = json_decode((string) $output, true);
if (!is_array($r)) { fwrite(STDERR, "FAIL: node output\n" . $output); exit(1); }

function check($expected, $actual, $message)
{
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: {$message}\n  expected: " . json_encode($expected, JSON_UNESCAPED_UNICODE) . "\n  actual:   " . json_encode($actual, JSON_UNESCAPED_UNICODE) . "\n");
        exit(1);
    }
    fwrite(STDOUT, "PASS: {$message}\n");
}
$t = static fn ($text) => ['type' => 'text', 'text' => $text];

check([
    ['type' => 'paragraph', 'lines' => [[$t('Hola.')], [$t('Segunda línea.')]]],
    ['type' => 'paragraph', 'lines' => [[$t('Otro párrafo.')]]],
], $r['paragraphs'], 'blank lines split paragraphs; single newlines stay as line breaks');

check([
    ['type' => 'heading', 'inline' => [$t('Vencen esta semana')]],
    ['type' => 'list', 'ordered' => false, 'items' => [[$t('Uno '), ['type' => 'ref', 'key' => 'request:41']], [$t('Dos')]]],
    ['type' => 'list', 'ordered' => true, 'items' => [[$t('Primero')], [$t('Segundo')]]],
], $r['lists'], 'a bold-only line is a heading; - * 1. 2) become lists; references are kept');

check([
    ['type' => 'heading', 'inline' => [$t('Resumen')]],
    ['type' => 'paragraph', 'lines' => [[$t('Texto')]]],
], $r['heading'], 'markdown # headings become headings');

check([['type' => 'paragraph', 'lines' => [[$t('Un **asterisco suelto y 2 * 3')]]]], $r['unclosed'], 'unclosed bold stays literal');

check([['type' => 'paragraph', 'lines' => [[$t('<script>alert(1)</script> '), ['type' => 'strong', 'children' => [$t('<b>x</b>')]]]]]],
    $r['html'], 'HTML is returned as text, never as markup');

check([['type' => 'heading', 'inline' => [$t('Mira '), ['type' => 'ref', 'key' => 'task:7']]]], $r['boldRef'], 'references inside bold are kept');

check("Hoy\n- Llamar Tarea 7: Llamar a Marta\n- Revisar [referencia no disponible]", $r['plain'], 'plain text for copying drops markers and names references');

check([['type' => 'paragraph', 'lines' => [[$t('a **** b')]]]], $r['emptyBold'], 'empty bold stays literal');
