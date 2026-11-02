<?php
/** Isolated assistant schema. MySQL runs the actual migration; SQLite is the default. */
function assistant_test_db($dsn = null)
{
    $dsn = $dsn ?? getenv('PRISMA_ASSISTANT_TEST_DSN') ?: 'sqlite::memory:';
    if (strpos($dsn, 'mysql:') === 0 && !preg_match('/dbname=prisma_ia2_test(?:;|$)/', $dsn)) throw new RuntimeException('Use only the isolated prisma_ia2_test database.');
    $db = new PDO($dsn, strpos($dsn, 'mysql:') === 0 ? 'root' : null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
    $mysql = $db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    if ($mysql) {
        foreach (['ai_proposals', 'ai_message_sources', 'ai_messages', 'ai_conversations', 'users'] as $table) $db->exec('DROP TABLE IF EXISTS ' . $table);
        $db->exec('CREATE TABLE users (id INT PRIMARY KEY) ENGINE=InnoDB');
        $db->exec('INSERT INTO users VALUES (7), (8), (9)');
        $migration = file_get_contents(__DIR__ . '/../../migrations/020_ai_assistant.sql');
        $db->exec($migration);
        $db->exec($migration); // Re-running additive migration is harmless.
    } else {
        $db->exec('PRAGMA foreign_keys = ON');
        $db->exec('CREATE TABLE users (id INTEGER PRIMARY KEY)');
        $db->exec('INSERT INTO users VALUES (7), (8), (9)');
        $db->exec("CREATE TABLE ai_conversations (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE, company_id INTEGER NULL, title TEXT DEFAULT 'Nueva conversación', context_json TEXT NOT NULL, revision INTEGER DEFAULT 1, created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP)");
        $db->exec("CREATE TABLE ai_messages (id INTEGER PRIMARY KEY AUTOINCREMENT, conversation_id INTEGER NOT NULL REFERENCES ai_conversations(id) ON DELETE CASCADE, role TEXT NOT NULL, client_key TEXT NOT NULL, content TEXT NOT NULL, context_json TEXT NOT NULL, model TEXT NULL, usage_json TEXT NULL, turn_revision INTEGER NULL, state TEXT DEFAULT 'complete', created_at TEXT DEFAULT CURRENT_TIMESTAMP, UNIQUE(conversation_id, client_key), UNIQUE(id, conversation_id))");
        $db->exec('CREATE TABLE ai_message_sources (message_id INTEGER NOT NULL, conversation_id INTEGER NOT NULL, entity_type TEXT NOT NULL, entity_id INTEGER NOT NULL, access_fingerprint TEXT NOT NULL, PRIMARY KEY (message_id, entity_type, entity_id), FOREIGN KEY (message_id, conversation_id) REFERENCES ai_messages(id, conversation_id) ON DELETE CASCADE)');
        $db->exec("CREATE TABLE ai_proposals (id INTEGER PRIMARY KEY AUTOINCREMENT, conversation_id INTEGER NOT NULL, message_id INTEGER NOT NULL, card_key TEXT NOT NULL, revision INTEGER NOT NULL, action_type TEXT NOT NULL, payload_json TEXT NOT NULL, expected_json TEXT NULL, execution_key TEXT NOT NULL UNIQUE, state TEXT DEFAULT 'pending', result_json TEXT NULL, created_at TEXT DEFAULT CURRENT_TIMESTAMP, applied_at TEXT NULL, UNIQUE(conversation_id, card_key, revision), FOREIGN KEY (message_id, conversation_id) REFERENCES ai_messages(id, conversation_id) ON DELETE CASCADE)");
    }
    return $db;
}
