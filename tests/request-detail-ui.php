<?php

function assert_contains($relativePath, $needle, $message)
{
    $contents = file_get_contents(__DIR__ . '/../' . $relativePath);
    if (strpos($contents, $needle) === false) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }

    fwrite(STDOUT, "PASS: {$message}\n");
}

assert_contains('assets/js/main.js', 'function openRequestDetailModal(requestId)', 'neutral detail opener exists');
assert_contains('assets/js/main.js', 'configureRequestModalMode(request)', 'modal mode is configured from backend capabilities');
assert_contains('assets/js/main.js', "request.capabilities?.edit === true", 'edit mode uses backend capability');
assert_contains('assets/js/main.js', "openRequestDetailModal(\${request.id})", 'request cards and table expose detail action');
assert_contains('assets/js/main.js', 'currentRequestCapabilities.edit', 'mutable child controls use current request capability');
assert_contains('index.php', 'id="request-modal-mode"', 'modal exposes a mode label');
assert_contains('index.php', 'id="edit-checklist-add-row"', 'checklist creation row can be hidden');
assert_contains('index.php', 'id="edit-request-submit"', 'request submit can be hidden');
assert_contains('index.php', 'id="edit-request-close"', 'close action can be relabelled');
assert_contains('assets/css/styles.css', '.request-modal--readonly', 'read-only mode has dedicated styles');
assert_contains('assets/css/styles.css', '.request-card-detail-trigger', 'card detail trigger has accessible interaction styles');

fwrite(STDOUT, "Request detail UI contract completed successfully.\n");
