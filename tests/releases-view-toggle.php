<?php

// El selector Calendario/Lista del Release Planner reutiliza la clase .view-toggle-btn,
// que en styles.css es un botón de solo icono de 32x28 px (Vista global, Mis tareas).
// Con texto, ese tamaño fijo hacía que los dos botones se montaran uno encima del otro.

$page = file_get_contents(__DIR__ . '/../releases.php');

if (!preg_match('/\.view-toggle-btn\s*\{([^}]*)\}/', $page, $match)) {
    fwrite(STDERR, "FAIL: releases.php has no .view-toggle-btn rule\n");
    exit(1);
}

foreach (['width: auto;', 'height: auto;'] as $needle) {
    if (strpos($match[1], $needle) === false) {
        fwrite(STDERR, "FAIL: releases.php .view-toggle-btn must reset the global icon-only size ({$needle})\n");
        exit(1);
    }
}
fwrite(STDOUT, "PASS: Release Planner view toggle sizes its buttons to their text\n");

$styles = file_get_contents(__DIR__ . '/../assets/css/styles.css');
if (strpos($styles, "width: 32px;\n    height: 28px;") === false) {
    fwrite(STDERR, "FAIL: the global icon-only .view-toggle-btn size changed; recheck Vista global and Mis tareas\n");
    exit(1);
}
fwrite(STDOUT, "PASS: global icon-only toggle (Vista global, Mis tareas) is untouched\n");
