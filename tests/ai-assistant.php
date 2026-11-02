<?php
require_once __DIR__ . '/../includes/ai-assistant.php';
require_once __DIR__ . '/fixtures/assistant-db.php';
function same($expected, $actual, $label) { if ($expected !== $actual) throw new RuntimeException($label . ': ' . var_export($actual, true)); echo "PASS: {$label}\n"; }
function fails($fn, $status, $label) { try { $fn(); } catch (PrismaAssistantException $e) { same($status, $e->status, $label); return; } throw new RuntimeException('Expected failure: ' . $label); }
$db = assistant_test_db();
$user = ['id' => 7, 'company_id' => 1, 'role' => 'admin'];
$rights = ['note:12' => 'read-write', 'app:3' => 'read', 'request:45' => 'edit'];
$resolver = static function ($type, $id) use (&$rights) {
    $scope = in_array($type, ['scope_mine', 'scope_team'], true);
    $access = $scope ? 'scope' : ($rights[$type . ':' . $id] ?? null);
    return $access ? ['label' => $type . ' ' . $id, 'access_fingerprint' => hash('sha256', $access)] : null;
};
$store = new PrismaAssistantStore($db, $user, $resolver);
$other = new PrismaAssistantStore($db, ['id' => 8, 'company_id' => 1, 'role' => 'superadmin'], $resolver);
$company = new PrismaAssistantStore($db, ['id' => 7, 'company_id' => 2, 'role' => 'admin'], $resolver);
$context = ['scope' => 'note', 'entity_id' => 12];
$chat = $store->create($context); $id = $chat['id'];
same('Nueva conversación', $chat['title'], 'new conversation stores a safe default title');
same(1, count($store->listing()['items']), 'owner sees conversation');
same([], $other->listing()['items'], 'superadmin cannot list another user conversations');
fails(fn() => $other->detail($id), 404, 'another user cannot read a conversation');
fails(fn() => $company->detail($id), 404, 'another company cannot read the conversation');
fails(fn() => $other->delete($id), 404, 'another user cannot delete the conversation');
fails(fn() => $store->create(['scope' => 'note', 'entity_id' => 999]), 403, 'context validated before creation');
fails(fn() => assistant_context(['scope' => 'team'], 'user'), 403, 'team scope respects role');
fails(fn() => assistant_context(['scope' => 'app', 'entity_id' => -1], 'admin'), 400, 'invalid entity rejected');
fails(fn() => assistant_context(['timezone' => 'Invalid'], 'admin'), 400, 'invalid timezone rejected');
fails(fn() => assistant_context(['filters' => ['from' => '2026-02-30']], 'admin'), 400, 'invalid calendar date rejected');
fails(fn() => assistant_context(['filters' => ['raw_sql' => 'SELECT 1']], 'admin'), 400, 'unknown filter rejected');
$turn = $store->reserve($id, 'Organiza estos apuntes', 'turn_000000000001', $context, 1);
same(1, count($turn['history']), 'user turn stored before calling provider');
same('Organiza estos apuntes', $store->detail($id)['conversation']['title'], 'untitled chat uses the first question as a readable label');
same('Organiza estos apuntes', $store->listing()['items'][0]['title'], 'history exposes the same readable label');
same('note', $turn['context']['scope'], 'turn has immutable context');
same(true, $store->detail($id)['messages'][0]['state'] === 'pending', 'pending turn survives reload');
fails(fn() => $store->reserve($id, 'Otro mensaje', 'turn_000000000002', [], 2), 409, 'second tab cannot reserve another concurrent turn');
$result = ['content' => 'Primero revisa el alcance.', 'model' => 'fixture', 'usage' => ['total_tokens' => 12]];
same(true, $store->finish($id, $turn, $result), 'provider result completes reserved turn');
same(false, $store->finish($id, $turn, $result), 'late repeated completion cannot duplicate reply');
$detail = $store->detail($id);
same(2, count($detail['messages']), 'one user message and one reply persisted');
same(['replay' => true], $store->reserve($id, 'Organiza estos apuntes', 'turn_000000000001', [], 1), 'retry of completed key replays without another provider call');
fails(fn() => $store->reserve($id, 'Cambiar el contenido', 'turn_000000000001', [], 3), 409, 'same key cannot substitute another message');
fails(fn() => $store->reserve($id, 'Más', 'turn_000000000003', [], 1), 409, 'stale conversation revision rejected');
$renamed = $store->rename($id, 'Reunión de diseño', $detail['conversation']['revision']);
same('Reunión de diseño', $renamed['title'], 'conversation renamed');
same('Reunión de diseño', $store->detail($id)['conversation']['title'], 'custom title takes priority over the first question');
$second = $store->reserve($id, 'Prepara un esquema', 'turn_000000000004', ['scope' => 'app', 'entity_id' => 3], $renamed['revision']);
same(true, $store->finish($id, $second), 'provider failure marks the turn failed');
same('failed', $store->detail($id)['messages'][2]['state'], 'failed turn retained for retry');
$retry = $store->reserve($id, 'Prepara un esquema', 'turn_000000000004', [], $second['revision']);
same(3, count($store->detail($id)['messages']), 'retry reuses the existing user turn');
same('app', $retry['context']['scope'], 'retry keeps original context instead of current screen');
same(false, $store->finish($id, $second, $result), 'older attempt cannot complete a retried turn');
same(true, $store->finish($id, $retry, $result), 'new retry can complete');
same('Reunión de diseño', $store->detail($id)['conversation']['title'], 'later turns cannot replace a custom title');
$rows = $db->query('SELECT DISTINCT entity_type FROM ai_message_sources ORDER BY entity_type')->fetchAll(PDO::FETCH_COLUMN);
same(['app', 'note'], $rows, 'later turns inherit earlier dependencies');

// Revocation hides the title and all derived messages before returning them.
$rights['note:12'] = null;
$redacted = $store->detail($id);
same('Conversación sin acceso', $redacted['conversation']['title'], 'source revocation redacts derived title');
same([''], array_values(array_unique(array_column($redacted['messages'], 'content'))), 'source revocation erases derived content');
same(['redacted'], array_values(array_unique(array_column($redacted['messages'], 'state'))), 'revocation marks history unavailable');

// A permission downgrade with view still allowed must also hide privileged fields.
$chat2 = $store->create(['scope' => 'request', 'entity_id' => 45]);
$third = $store->reserve($chat2['id'], 'Resume', 'turn_000000000005', ['scope' => 'request', 'entity_id' => 45], 1);
$rights['request:45'] = 'view';
same(false, $store->finish($chat2['id'], $third, $result), 'access downgrade during HTTP discards result');
same('redacted', $store->detail($chat2['id'])['messages'][0]['state'], 'downgrade redacts original turn');

// Expired pending turn is recoverable, without keeping a DB transaction open during HTTP.
$chat3 = $store->create([]);
$stale = $store->reserve($chat3['id'], 'Pendiente', 'turn_000000000006', [], 1);
$db->exec("UPDATE ai_messages SET created_at = '2020-01-01 00:00:00' WHERE id = " . $stale['message_id']);
same('failed', $store->detail($chat3['id'])['messages'][0]['state'], 'abandoned request becomes retryable');
same(false, $store->finish($chat3['id'], $stale, $result), 'expired response cannot revive a failed turn');
$store->delete($chat3['id']);
same(0, (int) $db->query('SELECT COUNT(*) FROM ai_messages WHERE conversation_id = ' . $chat3['id'])->fetchColumn(), 'deleting conversation cascades to messages');
fails(fn() => $store->finish($chat3['id'], $stale, $result), 404, 'deleted conversation never revived by a late response');

for ($i = 0; $i < 22; $i++) $store->create([]);
$page = $store->listing();
same(20, count($page['items']), 'history bounded to 20 conversations');
$page2 = $store->listing($page['next_cursor']);
same([], array_values(array_intersect(array_column($page['items'], 'id'), array_column($page2['items'], 'id'))), 'history cursor does not repeat rows');
same(24, count($page['items']) + count($page2['items']), 'history pagination retains every conversation');
// Message pagination returns older pages without duplicating the newest turns.
$long = $store->create([]);
for ($i = 0; $i < 27; $i++) {
    $revision = $store->detail($long['id'])['conversation']['revision'];
    $t = $store->reserve($long['id'], 'Mensaje ' . $i, 'pagination_' . str_pad((string) $i, 16, '0', STR_PAD_LEFT), [], $revision);
    $store->finish($long['id'], $t, $result);
}
$latest = $store->detail($long['id']);
$older = $store->detail($long['id'], $latest['next_cursor']);
same(50, count($latest['messages']), 'message response bounded to 50 rows');
same(4, count($older['messages']), 'older messages retrievable');
same([], array_values(array_intersect(array_column($latest['messages'], 'id'), array_column($older['messages'], 'id'))), 'message pages do not repeat rows');
// Retrieved sources are persisted before generation, not only when the answer cites them.
$extra = $store->create([]);
$extraTurn = $store->reserve($extra['id'], 'Buscar mejoras', 'turn_000000000007', [], 1);
$refs = [['entity_type'=>'app','entity_id'=>3,'access_fingerprint'=>hash('sha256','read')]];
same(true, $store->attachSources($extra['id'], $extraTurn, $refs), 'retrieved source attached before provider');
$rights['app:3'] = null;
same(false, $store->finish($extra['id'], $extraTurn, $result), 'revoked retrieved source discards answer even when navigation was personal');
$legacyValue = 'current';
$legacyResolver = static function($type,$id) use (&$legacyValue) { return ['label'=>'Contexto','access_fingerprint'=>hash('sha256',$legacyValue),'legacy_fingerprints'=>[hash('sha256','ia2')]]; };
$legacyStore = new PrismaAssistantStore($db, $user, $legacyResolver);
$legacyChat = $legacyStore->create([]);
$legacyTurn = $legacyStore->reserve($legacyChat['id'], 'Hola', 'turn_000000000008', [], 1);
$withRefs = $result;
$withRefs['metadata'] = ['references'=>[['key'=>'request:11','type'=>'request','id'=>11,'title'=>'Demo','url'=>'/index.php?open_request=11']],'coverage'=>[],'partial'=>false];
$legacyStore->finish($legacyChat['id'], $legacyTurn, $withRefs);
same('request:11', $legacyStore->detail($legacyChat['id'])['messages'][1]['metadata']['references'][0]['key'], 'verified answer metadata survives reload');
$s=$db->prepare('UPDATE ai_message_sources SET access_fingerprint=? WHERE conversation_id=?');$s->execute([hash('sha256','ia2'),$legacyChat['id']]);
same('complete', $legacyStore->detail($legacyChat['id'])['messages'][1]['state'], 'IA2 scope fingerprints upgrade without destroying history');
$legacyValue='revoked';
same('redacted', $legacyStore->detail($legacyChat['id'])['messages'][1]['state'], 'upgraded scope still detects later access changes');
same(null, $legacyStore->detail($legacyChat['id'])['messages'][1]['metadata'], 'redaction also erases verified-reference metadata');
$labelChat = $legacyStore->create([]);
$labelText = "  Revisión\n\n" . str_repeat('área técnica ', 12);
$legacyStore->reserve($labelChat['id'], $labelText, 'turn_000000000009', [], 1);
$autoTitle = $legacyStore->detail($labelChat['id'])['conversation']['title'];
same(80, mb_strlen($autoTitle), 'derived title is bounded in Unicode characters');
same(false, str_contains($autoTitle, "\n"), 'derived title normalizes whitespace');
same('…', mb_substr($autoTitle, -1), 'long derived titles indicate truncation');
same('Nueva conversación', $db->query('SELECT title FROM ai_conversations WHERE id=' . (int) $labelChat['id'])->fetchColumn(), 'derived labels do not rewrite existing conversation records');
$textChat = $legacyStore->create([]);
$textContent = "Revisar el formulario en móvil.";
$textTurn = $legacyStore->reserve($textChat['id'], $textContent, 'turn_000000000010', [], 1, 'notes');
same('notes', $textTurn['input_mode'], 'notes intent is part of the reserved turn');
same($textContent, $textTurn['history'][0]['content'], 'internal input mode does not pollute provider history');
same('notes', $legacyStore->detail($textChat['id'])['messages'][0]['metadata']['input_mode'], 'notes intent survives retrieval');
same('Apuntes: Revisar el formulario en móvil.', $legacyStore->detail($textChat['id'])['conversation']['title'], 'notes chat has a title based on its content');
$legacyStore->finish($textChat['id'], $textTurn);
$textRetry = $legacyStore->reserve($textChat['id'], $textContent, 'turn_000000000010', [], $textTurn['revision'], 'question');
same('notes', $textRetry['input_mode'], 'retry preserves original notes intent even when caller sends a different mode');
same(1, count($legacyStore->detail($textChat['id'])['messages']), 'notes retry does not duplicate the user message');
fails(fn() => $legacyStore->reserve($textChat['id'], 'Texto', 'turn_000000000011', [], $textRetry['revision'], 'unknown'), 400, 'unknown input mode is rejected');
$db->exec('DELETE FROM users WHERE id=7');
same(0, (int) $db->query('SELECT COUNT(*) FROM ai_messages')->fetchColumn(), 'owner deletion cascades through conversation history');
same(0, (int) $db->query('SELECT COUNT(*) FROM ai_message_sources')->fetchColumn(), 'owner deletion clears source dependencies');
echo 'Assistant lifecycle passed on ' . $db->getAttribute(PDO::ATTR_DRIVER_NAME) . ".\n";
