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

$expectedByRole = [
    'user' => ['view' => true, 'comment' => true, 'edit' => false, 'delete' => false],
    'programador' => ['view' => true, 'comment' => true, 'edit' => true, 'delete' => false],
    'admin' => ['view' => true, 'comment' => true, 'edit' => true, 'delete' => true],
    'superadmin' => ['view' => true, 'comment' => true, 'edit' => true, 'delete' => true],
];

foreach ($expectedByRole as $role => $expected) {
    assert_same(
        $expected,
        request_capabilities_for_role($role, true),
        "{$role} receives the expected capabilities inside an accessible app"
    );

    assert_same(
        ['view' => false, 'comment' => false, 'edit' => false, 'delete' => false],
        request_capabilities_for_role($role, false),
        "{$role} receives no capabilities outside the accessible app scope"
    );
}

assert_same(
    ['view' => false, 'comment' => false, 'edit' => false, 'delete' => false],
    request_capabilities_for_role('unknown', true),
    'unknown roles receive no capabilities'
);

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$db->exec('CREATE TABLE apps (id INTEGER PRIMARY KEY, company_id INTEGER NOT NULL)');
$db->exec('CREATE TABLE requests (id INTEGER PRIMARY KEY, app_id INTEGER NOT NULL, created_by INTEGER)');
$db->exec('INSERT INTO apps (id, company_id) VALUES (10, 1), (20, 2)');
$db->exec('INSERT INTO requests (id, app_id, created_by) VALUES (100, 10, 7), (200, 20, 8)');

assert_same(
    ['id' => 100, 'app_id' => 10, 'company_id' => 1, 'created_by' => 7],
    get_request_access_context(100, $db),
    'request context resolves its parent app and company'
);

assert_same(false, get_request_access_context(999, $db), 'missing request returns false');
assert_same(false, get_request_access_context(0, $db), 'invalid request ID returns false');

$_SESSION['user_id'] = 7;
$_SESSION['username'] = 'tenant-a-user';
$_SESSION['role'] = 'user';
$_SESSION['company_id'] = 1;
$_SESSION['company_name'] = 'Tenant A';
$_SESSION['full_name'] = 'Tenant A User';

$tenantAAccess = static function ($appId) {
    return (int) $appId === 10;
};

assert_same(
    $expectedByRole['user'],
    get_request_capabilities(100, $db, $tenantAAccess)['capabilities'],
    'tenant A user can view and comment on a request from tenant A'
);

assert_same(
    ['view' => false, 'comment' => false, 'edit' => false, 'delete' => false],
    get_request_capabilities(200, $db, $tenantAAccess)['capabilities'],
    'tenant A user receives no capabilities for a known request ID from tenant B'
);

$_SESSION['role'] = 'programador';
assert_same(
    $expectedByRole['programador'],
    get_request_capabilities(100, $db, $tenantAAccess)['capabilities'],
    'programador can edit only inside the accessible tenant scope'
);

$_SESSION['role'] = 'admin';
assert_same(
    $expectedByRole['admin'],
    get_request_capabilities(100, $db, $tenantAAccess)['capabilities'],
    'admin can delete only inside the accessible tenant scope'
);

assert_same(
    ['view' => false, 'comment' => false, 'edit' => false, 'delete' => false],
    get_request_capabilities(200, $db, $tenantAAccess)['capabilities'],
    'admin role does not bypass the tenant scope'
);

fwrite(STDOUT, "Authorization matrix completed successfully.\n");
