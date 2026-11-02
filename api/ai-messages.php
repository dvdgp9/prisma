<?php
require_once __DIR__ . '/../includes/ai-assistant-http.php';
require_once __DIR__ . '/../includes/ai-assistant-response.php';
$turn = null;
try {
    $db = assistant_http_start();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new PrismaAssistantException('Método no permitido.', 405);
    $input = get_json_input();
    if (!is_array($input)) throw new PrismaAssistantException('La petición no es válida.');
    $store = assistant_store($db);
    $id = (int) ($input['conversation_id'] ?? 0);
    $config = prisma_ai_config($db);
    $turn = $store->reserve($id, $input['content'] ?? '', $input['client_key'] ?? '', $input['context'] ?? [], $input['revision'] ?? 0);
    if (!$turn['replay']) {
        $actor = assistant_actor($db);
        $now = new DateTimeImmutable('now', new DateTimeZone($turn['context']['timezone']));
        $date = $now->format('Y-m-d H:i T');
        prisma_ai_release_session();
        $started = microtime(true);
        $planning = assistant_query_plan($config, $turn, $date, ['id' => (int) $actor['id'], 'name' => $actor['full_name'] ?: $actor['username']]);
        $actor = assistant_actor($db);
        $apps = array_map('intval', array_column(get_user_apps(), 'id')); sort($apps, SORT_NUMERIC);
        $resolver = assistant_access_resolver($db, $actor, $apps);
        $queries = new PrismaAssistantQueries($db, $actor, $apps, $resolver);
        $retrieval = $queries->run($planning['data'], $turn['context'], $now->format('Y-m-d'));
        $store = assistant_store($db);
        if (!$store->attachSources($id, $turn, $retrieval['sources'])) throw new PrismaAssistantException('El acceso a la consulta ha cambiado. Recarga la conversación.', 409);
        if ($planning['data']['mode'] === 'clarify') {
            $result = ['content' => $planning['data']['question'] ?: 'Indica qué registro o aplicación quieres consultar.', 'model' => $planning['model'], 'usage' => [], 'metadata' => ['references' => [], 'coverage' => [], 'partial' => false]];
        } else {
            $remaining = max(1, min(50, (int) (75 - (microtime(true) - $started))));
            $result = assistant_grounded_answer($config, $turn, $retrieval, $date, null, $remaining);
        }
        foreach ($planning['usage'] as $field => $tokens) $result['usage'][$field] = ($result['usage'][$field] ?? 0) + $tokens;
        $store = assistant_store($db);
        if (!$store->finish($id, $turn, $result)) throw new PrismaAssistantException('La conversación ha cambiado. Recárgala para continuar.', 409);
    }
    success_response($store->detail($id));
} catch (Throwable $e) {
    if ($turn && empty($turn['replay'])) {
        try { assistant_store($db)->finish($id, $turn); } catch (Throwable $ignored) { /* Deleted or revoked; never revive it. */ }
    }
    assistant_http_error($e);
}
