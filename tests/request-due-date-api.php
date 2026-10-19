<?php

require_once __DIR__ . '/../includes/auth.php';

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

foreach (['request_capability_for_update', 'normalize_request_due_date'] as $fn) {
    if (!function_exists($fn)) {
        fwrite(STDERR, "FAIL: includes/auth.php is missing {$fn}()\n");
        exit(1);
    }
}

// --- Rol usuario: solo lectura (decisión del usuario, 19 oct 2026). Revierte el permiso del 13 jul.
assert_same(false, request_capabilities_for_role('user', true)['update_status'], 'user role can no longer change status or date');
assert_same(true, request_capabilities_for_role('user', true)['comment'], 'user role still comments');
assert_same(true, request_capabilities_for_role('user', true)['checklist'], 'user role still uses the checklist');
foreach (['programador', 'admin', 'superadmin'] as $role) {
    assert_same(true, request_capabilities_for_role($role, true)['update_status'], "{$role} can change status and date");
}

// --- Qué permiso exige cada cambio: estado y fecha son planificar; lo demás es editar.
assert_same('update_status', request_capability_for_update(['id' => 1, 'status' => 'in_progress']), 'status only needs update_status');
assert_same('update_status', request_capability_for_update(['id' => 1, 'due_date' => '2026-10-24']), 'due date only needs update_status');
assert_same('update_status', request_capability_for_update(['id' => 1, 'status' => 'pending', 'due_date' => null]), 'status + due date needs update_status');
assert_same('edit', request_capability_for_update(['id' => 1, 'due_date' => '2026-10-24', 'priority' => 'high']), 'due date with other fields needs edit');
assert_same('edit', request_capability_for_update(['id' => 1, 'title' => 'x']), 'title needs edit');

// --- Validación de la fecha: opcional, día real del calendario, sin hora.
assert_same(['value' => '2026-10-24', 'error' => null], normalize_request_due_date('2026-10-24'), 'valid date is kept');
assert_same(['value' => '2028-02-29', 'error' => null], normalize_request_due_date('2028-02-29'), 'leap day is valid');
assert_same(['value' => null, 'error' => null], normalize_request_due_date(null), 'null clears the date');
assert_same(['value' => null, 'error' => null], normalize_request_due_date(''), 'empty string clears the date');
foreach (['2026-02-30', '2026-13-01', '24/10/2026', '2026-10-24 10:00:00', 'mañana', 20261024, true] as $bad) {
    assert_same('Fecha no válida (usa AAAA-MM-DD)', normalize_request_due_date($bad)['error'], 'rejects ' . var_export($bad, true));
}

// --- Endpoint
assert_file_contains('api/requests.php', [
    'require_request_capability($input[\'id\'], request_capability_for_update($input))',
    'normalize_request_due_date($input[\'due_date\'])',
    "\$fields[] = 'due_date = ?';",
    'normalize_request_due_date($input[\'due_date\'] ?? null)',
    'No tienes permiso para poner fecha a una mejora',
], 'requests API validates and stores the due date with the right capability');

$api = file_get_contents(__DIR__ . '/../api/requests.php');
assert_same(1, preg_match('/INSERT INTO requests \([^)]*due_date/s', $api), 'new requests can be created with a due date');
assert_same(false, strpos($api, '$isStatusOnlyChange') !== false, 'old status-only check is gone');

fwrite(STDOUT, "Request due date API completed successfully.\n");
