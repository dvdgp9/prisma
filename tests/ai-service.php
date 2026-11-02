<?php
require_once __DIR__ . '/../includes/ai.php';

function same($expected, $actual, $message)
{
    if ($expected !== $actual) throw new RuntimeException($message . ': ' . var_export($actual, true));
    echo "PASS: {$message}\n";
}

function ai_error($callback, $status, $message)
{
    try { $callback(); } catch (PrismaAIException $e) {
        same($status, $e->httpStatus(), $message);
        same(false, strpos($e->getMessage(), 'SECRET') !== false, 'error does not expose provider secrets');
        return;
    }
    throw new RuntimeException('Expected error: ' . $message);
}

$config = ['api_key' => 'SECRET', 'model' => 'test-model'];
$messages = [['role' => 'system', 'content' => 'Extrae puntos'], ['role' => 'user', 'content' => 'Reunión']];
$schema = ['type' => 'object', 'properties' => [
    'points' => ['type' => 'array', 'minItems' => 1, 'maxItems' => 3, 'items' => ['type' => 'string', 'maxLength' => 40]],
    'app_id' => ['type' => ['integer', 'null']]
], 'required' => ['points', 'app_id'], 'additionalProperties' => false];
$reply = static function ($content, $finish = 'stop') {
    return ['status' => 200, 'body' => json_encode([
        'choices' => [['finish_reason' => $finish, 'message' => ['content' => $content]]],
        'usage' => ['prompt_tokens' => 15, 'completion_tokens' => 6, 'total_tokens' => 21, 'secret' => 'SECRET']
    ])];
};
$sent = null;
$result = prisma_ai_structured($config, $messages, 'points', $schema, 99999,
    static function ($payload, $key) use (&$sent, $reply) {
        same('SECRET', $key, 'transport receives key separately');
        $sent = json_decode($payload, true);
        return $reply('{"points":["Decisión"],"app_id":null}');
    });
same(['points' => ['Decisión'], 'app_id' => null], $result['data'], 'valid structured response is decoded');
same(['prompt_tokens' => 15, 'completion_tokens' => 6, 'total_tokens' => 21], $result['usage'], 'only safe token counts are returned');
same(8192, $sent['max_tokens'], 'output tokens are bounded');
same(true, $sent['response_format']['json_schema']['strict'], 'provider receives strict schema');
same(false, isset($sent['api_key']), 'key is not in provider payload');

foreach ([
    'no json', '{"points": [1], "app_id": null}', '{"points": [], "app_id": null}',
    '{"points": ["ok"], "app_id": "2"}', '{"points": ["ok"], "app_id": null, "execute": true}',
    '{"points": ["ok"]}', '[]', '{"points": {}, "app_id": null}',
    '{"points": ["a","b","c","d"], "app_id": null}'
] as $invalid) {
    ai_error(static function () use ($config, $messages, $schema, $reply, $invalid) {
        prisma_ai_structured($config, $messages, 'points', $schema, 100, static function () use ($reply, $invalid) { return $reply($invalid); });
    }, 502, 'malformed/schema-invalid response is rejected');
}
foreach (['length', 'content_filter', 'tool_calls'] as $finish) {
    ai_error(static function () use ($config, $messages, $reply, $finish) {
        prisma_ai_complete($config, $messages, null, 100, static function () use ($reply, $finish) { return $reply('partial', $finish); });
    }, 502, 'incomplete/refused/tool response cannot become a proposal');
}
foreach ([0, 401, 429, 500] as $status) {
    ai_error(static function () use ($config, $messages, $status) {
        prisma_ai_complete($config, $messages, null, 100, static function () use ($status) { return ['status' => $status, 'body' => 'SECRET']; });
    }, $status === 429 ? 429 : 502, 'provider error mapped safely');
}
ai_error(static function () use ($config, $messages) {
    prisma_ai_complete($config, $messages, null, 100, static function () { throw new RuntimeException('SECRET'); });
}, 502, 'transport exception mapped safely');
ai_error(static function () use ($config, $messages) {
    prisma_ai_complete($config, $messages, null, 100, static function () { return ['status' => 200, 'body' => str_repeat('x', PRISMA_AI_MAX_RESPONSE_BYTES + 1)]; });
}, 502, 'oversized response rejected');
$never = static function () { throw new LogicException('transport must not be called'); };
ai_error(static function () use ($config, $never) { prisma_ai_complete($config, [['role' => 'user', 'content' => str_repeat('a', 100001)]], null, 100, $never); }, 400, 'oversized context rejected before HTTP');
ai_error(static function () use ($config, $never) { prisma_ai_complete($config, [['role' => 'tool', 'content' => 'x']], null, 100, $never); }, 400, 'unknown message role rejected');
ai_error(static function () use ($messages, $never) { prisma_ai_complete([], $messages, null, 100, $never); }, 503, 'missing configuration rejected');
ai_error(static function () use ($config, $never) { prisma_ai_complete($config, [['role' => 'user', 'content' => "\xB1"]], null, 100, $never); }, 400, 'invalid UTF-8 rejected before HTTP');
same(false, prisma_ai_schema_matches(json_decode('{"a":1}'), ['type' => 'object', 'unknownAssertion' => true]), 'unsupported assertions fail closed');
same(false, prisma_ai_schema_matches('other', ['type' => 'string', 'enum' => ['one', 'two']]), 'enum enforced');
same(true, prisma_ai_schema_matches(2, ['type' => 'number', 'minimum' => 1, 'maximum' => 2]), 'integer accepted for number');

$db = new PDO('sqlite::memory:');
$db->exec('CREATE TABLE app_settings (setting_key TEXT PRIMARY KEY, setting_value TEXT)');
ai_error(static function () use ($db) { prisma_ai_config($db); }, 503, 'empty DB configuration rejected');
$stmt = $db->prepare('INSERT INTO app_settings VALUES (?, ?)');
$stmt->execute(['openrouter_api_key', encrypt('test-key')]);
$stmt->execute(['ai_model', 'custom-model']);
same(['api_key' => 'test-key', 'model' => 'custom-model'], prisma_ai_config($db), 'existing encrypted settings reused');
$db->exec("UPDATE app_settings SET setting_value = '' WHERE setting_key = 'ai_model'");
same(PRISMA_AI_DEFAULT_MODEL, prisma_ai_config($db)['model'], 'empty model falls back to existing default');

// Session release is tested in a child process before output, without a provider.
$probe = 'require ' . var_export(__DIR__ . '/../includes/ai.php', true) . '; session_start(); prisma_ai_release_session(); echo session_status();';
same((string) PHP_SESSION_NONE, trim(shell_exec(escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($probe))), 'slow call does not retain the session lock');
echo "AI service completed successfully.\n";
