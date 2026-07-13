<?php

function read_project_file($relativePath)
{
    return file_get_contents(__DIR__ . '/../' . $relativePath);
}

function assert_contains_ux($contents, $needle, $message)
{
    if (strpos($contents, $needle) === false) {
        fwrite(STDERR, "FAIL: {$message}\nMissing: {$needle}\n");
        exit(1);
    }

    fwrite(STDOUT, "PASS: {$message}\n");
}

function assert_not_contains_ux($contents, $needle, $message)
{
    if (strpos($contents, $needle) !== false) {
        fwrite(STDERR, "FAIL: {$message}\nUnexpected: {$needle}\n");
        exit(1);
    }

    fwrite(STDOUT, "PASS: {$message}\n");
}

$index = read_project_file('index.php');
$main = read_project_file('assets/js/main.js');
$sidebar = read_project_file('includes/sidebar.php');
$sidebarJs = read_project_file('assets/js/sidebar.js');
$pwa = read_project_file('assets/js/pwa.js');
$styles = read_project_file('assets/css/styles.css');

assert_contains_ux($index, 'class="requests-toolbar-shell is-advanced"', 'advanced filters are expanded by default');
assert_not_contains_ux($index, 'id="toolbar-more-btn"', 'the redundant more-filters toggle is removed');
assert_contains_ux($index, '<span>Mis asignadas</span>', 'my assignments remains directly visible');
assert_contains_ux($index, 'Buscar por título, descripción o aplicación', 'user search copy does not promise requester data');
assert_contains_ux($index, 'id="summary-assigned-count"', 'user summary includes assigned improvements');
assert_contains_ux($index, 'id="summary-activity-count"', 'user summary includes unread activity');
assert_contains_ux($index, 'id="summary-recent-completed-count"', 'user summary includes recent completions');
assert_contains_ux($index, "if (\$user['role'] !== 'user')", 'floating task creation is hidden only from user');

assert_contains_ux($sidebar, 'id="pwa-install-menu-item"', 'user can install the app from the profile menu');
assert_contains_ux($pwa, "userRole === 'user'", 'PWA install presentation is role-aware');
assert_contains_ux($pwa, 'pwa-install-menu-item', 'user install prompt moves into the profile menu');

assert_contains_ux($main, "widget.classList.toggle('is-clear'", 'empty task widget switches to a compact state');
assert_contains_ux($main, 'Todo al día', 'empty task widget explains the zero state');
assert_contains_ux($main, 'request-card-detail-cta', 'user cards expose a text detail action');
assert_contains_ux($main, "showFinished || currentQuickView === 'completed'", 'completed filter reveals completed cards directly');
assert_contains_ux($main, 'Creada hace', 'request age is explicitly labelled');
assert_contains_ux($main, 'Responsable:', 'request ownership is explicitly labelled');
assert_contains_ux($main, 'prisma:notifications-updated', 'user activity summary reacts to notification state');
assert_contains_ux($sidebarJs, 'prisma:notifications-updated', 'notification updates are shared with the dashboard');
assert_contains_ux($styles, '.tasks-widget.is-clear', 'compact empty-task state has dedicated styles');
assert_contains_ux($styles, '.request-card-detail-cta', 'text detail action has dedicated styles');
assert_contains_ux($styles, 'min-height: 34px;', 'detail action has a comfortable hit area');
assert_contains_ux($styles, 'margin-left: var(--sp-8);', 'detail action is visually separated from voting controls');
assert_contains_ux($styles, '.request-card-detail-cta:focus-visible', 'detail action has a keyboard focus state');

assert_contains_ux($index, '<span>En curso</span>', 'status quick filter uses En curso');
assert_contains_ux($index, '<span>Completadas</span>', 'completed quick filter uses consistent terminology');

fwrite(STDOUT, "User dashboard UX contract completed successfully.\n");
