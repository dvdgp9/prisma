<?php

// Release Planner en móvil (<= 640 px): formulario apilado, pestañas en varias filas,
// Lista por defecto, calendario con scroll horizontal, tarjetas y modal a una columna.

function fail($message)
{
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
}

function pass($message)
{
    fwrite(STDOUT, "PASS: {$message}\n");
}

$page = file_get_contents(__DIR__ . '/../releases.php');

$mediaStart = strpos($page, '@media (max-width: 640px)');
if ($mediaStart === false) {
    fail('releases.php has no mobile media query');
}
$mobileCss = substr($page, $mediaStart, strpos($page, '</style>', $mediaStart) - $mediaStart);
pass('releases.php has a mobile media query');

foreach ([
    '.quick-add-title' => 'title field spans the whole row',
    '.filter-tabs' => 'filter tabs adapt',
    'flex-wrap: wrap' => 'tabs/cards/footer can wrap',
    'repeat(7, minmax(' => 'calendar days keep a minimum width',
    'overflow-x: auto' => 'calendar scrolls horizontally instead of being cut',
    '.release-actions' => 'list card actions move below the content',
    '.form-row' => 'modal fields go to one column',
] as $needle => $what) {
    if (strpos($mobileCss, $needle) === false) {
        fail("mobile CSS is missing {$needle} ({$what})");
    }
    pass("mobile CSS: {$what}");
}

foreach (['class="form-group quick-add-title"', 'class="form-group quick-add-actions"'] as $needle) {
    if (strpos($page, $needle) === false) {
        fail("quick add form is missing {$needle}");
    }
}
foreach (['style="flex: 1;"', 'style="display: flex; align-items: end;'] as $needle) {
    if (strpos($page, $needle) !== false) {
        fail("quick add form still has inline style {$needle}");
    }
}
pass('quick add form uses classes instead of inline styles');

foreach (["matchMedia('(max-width: 640px)')", 'prisma_releases_view', 'function chooseView(', "onclick=\"chooseView('list')\""] as $needle) {
    if (strpos($page, $needle) === false) {
        fail("view selection is missing {$needle}");
    }
}
pass('narrow screens open the List view unless the person chose a view');

fwrite(STDOUT, "Release Planner mobile contract completed successfully.\n");
