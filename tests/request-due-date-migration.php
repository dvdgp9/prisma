<?php

function assert_true($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }

    fwrite(STDOUT, "PASS: {$message}\n");
}

$path = __DIR__ . '/../migrations/016_request_due_date.sql';
assert_true(is_file($path), 'migration 016_request_due_date.sql exists');

// Sin comentarios y con espacios normalizados, para comprobar solo el SQL.
$raw = file_get_contents($path);
$sql = preg_replace('/\s+/', ' ', preg_replace('/^\s*--.*$/m', '', $raw));
$has = fn($fragment) => stripos($sql, $fragment) !== false;

// Fecha objetivo opcional de cada mejora (MT1 del plan "Mi trabajo").
assert_true($has('ALTER TABLE requests'), 'alters the requests table');
assert_true($has('ADD COLUMN due_date DATE NULL'), 'adds an optional due_date (DATE, no time)');
assert_true($has('ADD INDEX idx_due_date (due_date)'), 'due_date is indexed');

// Compatible con MySQL y MariaDB: sin IF NOT EXISTS en ADD COLUMN (solo existe en MariaDB).
assert_true(!$has('IF NOT EXISTS'), 'no MariaDB-only IF NOT EXISTS');
assert_true(!$has('DROP ') && !$has('DELETE ') && !$has('TRUNCATE') && !$has('UPDATE ') && !$has('MODIFY ') && !$has('CHANGE '), 'migration is additive only');
assert_true(substr_count($sql, ';') === 1, 'a single statement');

$readme = file_get_contents(__DIR__ . '/../migrations/README.md');
assert_true(strpos($readme, '016_request_due_date.sql') !== false, 'migrations README documents 016');

fwrite(STDOUT, "Request due date migration contract completed successfully.\n");
