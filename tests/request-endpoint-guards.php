<?php

function assert_file_contains($relativePath, $needles)
{
    $path = __DIR__ . '/../' . $relativePath;
    $contents = file_get_contents($path);

    foreach ($needles as $needle) {
        if (strpos($contents, $needle) === false) {
            fwrite(STDERR, "FAIL: {$relativePath} is missing guard: {$needle}\n");
            exit(1);
        }
    }

    fwrite(STDOUT, "PASS: {$relativePath} contains its request-scope guards\n");
}

assert_file_contains('api/comments.php', [
    "require_request_capability(\$request_id, 'view')",
    "require_request_capability(\$request_id, 'comment')",
    "require_request_capability((int) \$comment['request_id'], 'comment')",
]);
assert_file_contains('api/attachments.php', [
    "require_request_capability(\$request_id, 'view')",
    "require_request_capability(\$attachment['request_id'], 'edit')",
]);
assert_file_contains('api/file.php', ["require_request_capability(\$row['request_id'], 'view')"]);
assert_file_contains('api/upload.php', ["require_request_capability(\$request_id, 'edit')"]);
assert_file_contains('api/assignments.php', [
    "require_request_capability(\$requestId, 'view')",
    "require_request_capability(\$requestId, 'edit')",
    'Uno o más usuarios no tienen acceso a esta petición',
]);
assert_file_contains('api/votes.php', ["require_request_capability(\$request_id, 'view')"]);
assert_file_contains('api/users-list.php', [
    "require_request_capability((int) \$_GET['request_id'], 'comment')",
    "if (!can_edit_requests())",
]);
assert_file_contains('api/requests.php', [
    "require_request_capability(\$_GET['id'], 'view')",
    "require_request_capability(\$input['id'], 'edit')",
    "require_request_capability(\$input['id'], 'delete')",
    'sanitize_request_for_capabilities',
]);
assert_file_contains('api/notifications.php', ['get_user_apps()', '$scopeSql']);
assert_file_contains('api/request-checklist.php', [
    "require_request_capability(\$requestId, 'view')",
    "require_request_capability(\$requestId, 'checklist')",
    "require_request_capability((int) \$item['request_id'], 'checklist')",
]);

fwrite(STDOUT, "All request endpoint guards are present.\n");
