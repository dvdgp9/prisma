<?php

function assert_file_contains($relativePath, $needles, $message)
{
    $contents = file_get_contents(__DIR__ . '/../' . $relativePath);

    foreach ($needles as $needle) {
        if (strpos($contents, $needle) === false) {
            fwrite(STDERR, "FAIL: {$message} ({$relativePath} is missing: {$needle})\n");
            exit(1);
        }
    }

    fwrite(STDOUT, "PASS: {$message}\n");
}

assert_file_contains('includes/sidebar.php', ['id="tasks-count"', '/assets/js/sidebar.js?v=1.9'], 'sidebar has the tasks badge and loads the new sidebar.js');
assert_file_contains('assets/js/sidebar.js', [
    'function countTasksDue(tasks, localToday)',
    'async function loadTasksCount()',
    "fetch('/api/tasks.php?completed=0&shared=0')",
    "document.getElementById('tasks-count')",
    "classList.toggle('nav-count--accent', counts.overdue > 0)",
    "document.addEventListener('DOMContentLoaded', loadTasksCount)",
], 'sidebar.js counts overdue and today tasks on every page');
assert_file_contains('assets/js/tasks.js', ["if (typeof loadTasksCount === 'function') loadTasksCount();"], 'tasks page refreshes the badge after reloading the list');
assert_file_contains('assets/js/main.js', ["if (typeof loadTasksCount === 'function') loadTasksCount();"], 'home refreshes the badge after creating a task');

fwrite(STDOUT, "Tasks sidebar count contract completed successfully.\n");
