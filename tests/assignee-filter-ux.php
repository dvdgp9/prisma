<?php

function read_assignee_filter_file($relativePath)
{
    return file_get_contents(__DIR__ . '/../' . $relativePath);
}

function assert_assignee_filter_contains($contents, $needle, $message)
{
    if (strpos($contents, $needle) === false) {
        fwrite(STDERR, "FAIL: {$message}\nMissing: {$needle}\n");
        exit(1);
    }

    fwrite(STDOUT, "PASS: {$message}\n");
}

$index = read_assignee_filter_file('index.php');
$main = read_assignee_filter_file('assets/js/main.js');
$styles = read_assignee_filter_file('assets/css/styles.css');

// Existing shortcuts remain available.
assert_assignee_filter_contains($index, '<span>Mis asignadas</span>', 'my assignments shortcut remains visible');
assert_assignee_filter_contains($index, '<span>Sin asignar</span>', 'unassigned shortcut remains visible');

// A labelled single-person selector is part of the shared request toolbar, so it
// works with both the global dataset and the dataset loaded for a specific app.
assert_assignee_filter_contains($index, 'for="assignee-filter"', 'assignee selector has an accessible label');
assert_assignee_filter_contains($index, 'id="assignee-filter"', 'single-person assignee selector exists');
assert_assignee_filter_contains($index, 'onchange="handleAssigneeFilterChange()"', 'selector reacts to a person change');

// The selector is populated from assignments already returned for the current
// authorized request dataset. This avoids opening a broader users endpoint.
assert_assignee_filter_contains($main, 'function populateAssigneeFilter()', 'assignee options are rebuilt from visible requests');
assert_assignee_filter_contains($main, 'request.assignments || []', 'assignee options use request assignment data');
assert_assignee_filter_contains($main, 'function handleAssigneeFilterChange()', 'assignee selection has an explicit handler');
assert_assignee_filter_contains($main, 'selectedAssigneeId', 'selected person has dedicated filter state');

// Person selection composes with the existing search/status quick views and is
// reset by the shared clear-filters action.
assert_assignee_filter_contains($main, 'matchesSelectedAssignee', 'request filtering checks the selected person');
assert_assignee_filter_contains($main, 'selectedAssigneeId = null', 'clear filters resets the selected person');
assert_assignee_filter_contains($main, 'populateAssigneeFilter();', 'each request reload refreshes assignee options for the current scope');

// Styling stays in the central stylesheet and includes keyboard focus.
assert_assignee_filter_contains($styles, '.assignee-filter-control', 'assignee selector has centralized styles');
assert_assignee_filter_contains($styles, '.assignee-filter-control:focus-visible', 'assignee selector has a visible keyboard focus state');

fwrite(STDOUT, "Assignee filter UX contract completed successfully.\n");
