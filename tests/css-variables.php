<?php

// Una variable CSS sin definir invalida toda la declaración que la usa (padding,
// gap, borde... vuelven al valor inicial). Este test falla si alguna hoja de
// estilos usa var(--x) sin fallback y --x no está definida en ninguna de ellas.

function assert_same($expected, $actual, $message)
{
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: {$message}\n");
        fwrite(STDERR, '  expected: ' . var_export($expected, true) . "\n");
        fwrite(STDERR, '  actual:   ' . var_export($actual, true) . "\n");
        exit(1);
    }

    fwrite(STDOUT, "PASS: {$message}\n");
}

$sheets = glob(__DIR__ . '/../assets/css/*.css');
$defined = [];
foreach ($sheets as $file) {
    preg_match_all('/(--[\w-]+)\s*:/', file_get_contents($file), $m);
    $defined = array_merge($defined, $m[1]);
}
$defined = array_unique($defined);

foreach ($sheets as $file) {
    preg_match_all('/var\(\s*(--[\w-]+)\s*\)/', file_get_contents($file), $used);
    $missing = array_values(array_diff(array_unique($used[1]), $defined));
    sort($missing);
    assert_same([], $missing, basename($file) . ' only uses defined CSS variables');
}

// Estilos inline o embebidos en páginas PHP y plantillas JS (los harness preview-*.html no cuentan).
$root = __DIR__ . '/..';
$inlineSources = array_merge(glob("$root/*.php"), glob("$root/includes/*.php"), glob("$root/assets/js/*.js"));
$inlineMissing = [];
foreach ($inlineSources as $file) {
    preg_match_all('/var\(\s*(--[\w-]+)\s*\)/', file_get_contents($file), $used);
    foreach (array_diff(array_unique($used[1]), $defined) as $name) {
        $inlineMissing[] = basename($file) . ': ' . $name;
    }
}
sort($inlineMissing);
assert_same([], $inlineMissing, 'PHP pages and JS templates only use defined CSS variables');

$tokens = file_get_contents(__DIR__ . '/../assets/css/tokens.css');
$expected = [
    '--sp-14: 14px;',
    '--fw-bold:   700;',
    '--bg-hover:',
    '--border-light:',
    '--font-weight-medium:',
    '--font-weight-semibold:',
    '--success:',
    '--transition-fast:',
];
$absent = array_values(array_filter($expected, fn($needle) => strpos($tokens, $needle) === false));
assert_same([], $absent, 'tokens.css defines the aliases used by styles.css');

fwrite(STDOUT, "CSS variables check completed successfully.\n");
