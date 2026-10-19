<?php

function assert_true($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }

    fwrite(STDOUT, "PASS: {$message}\n");
}

$path = __DIR__ . '/../migrations/015_notes.sql';
assert_true(is_file($path), 'migration 015_notes.sql exists');

// Normaliza espacios para comprobar fragmentos sin depender del formato.
$sql = preg_replace('/\s+/', ' ', file_get_contents($path));
$has = fn($fragment) => stripos($sql, $fragment) !== false;

// notes: dueño, empresa, app opcional, compartir con el equipo, fijar y quién editó por última vez.
assert_true($has('CREATE TABLE IF NOT EXISTS notes ('), 'creates the notes table idempotently');
foreach ([
    'company_id INT NOT NULL',
    'user_id INT NOT NULL',
    'app_id INT NULL',
    'title VARCHAR(255) NOT NULL',
    'content MEDIUMTEXT',
    'is_shared TINYINT(1) NOT NULL DEFAULT 0',
    'is_pinned TINYINT(1) NOT NULL DEFAULT 0',
    'updated_by INT NULL',
    'updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
    'FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE',
    'FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE',
    'FOREIGN KEY (app_id) REFERENCES apps(id) ON DELETE SET NULL',
    'FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL',
    'INDEX idx_company_updated (company_id, updated_at)',
] as $fragment) {
    assert_true($has($fragment), "notes has: {$fragment}");
}

// note_shares: con quién se comparte cada nota (una fila por persona).
assert_true($has('CREATE TABLE IF NOT EXISTS note_shares ('), 'creates the note_shares table idempotently');
foreach ([
    'UNIQUE KEY uniq_note_user (note_id, user_id)',
    'FOREIGN KEY (note_id) REFERENCES notes(id) ON DELETE CASCADE',
    'FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE',
] as $fragment) {
    assert_true($has($fragment), "note_shares has: {$fragment}");
}

// Avisos de "te han compartido una nota".
assert_true($has('ALTER TABLE notifications ADD COLUMN note_id INT NULL'), 'notifications gets a nullable note_id');
assert_true($has('ADD INDEX idx_note (note_id)'), 'notifications.note_id is indexed');

assert_true(!$has('DROP TABLE') && !$has('DELETE FROM') && !$has('TRUNCATE'), 'migration is additive only');
assert_true(substr_count($sql, 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci') === 2, 'both tables use InnoDB + utf8mb4_unicode_ci');

$readme = file_get_contents(__DIR__ . '/../migrations/README.md');
assert_true(strpos($readme, '015_notes.sql') !== false, 'migrations README documents 015_notes.sql');

fwrite(STDOUT, "Notes migration contract completed successfully.\n");
