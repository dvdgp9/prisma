<?php
require_once __DIR__ . '/../includes/ai-assistant-http.php';
try {
    $db = assistant_http_start();
    $store = assistant_store($db);
    $method = $_SERVER['REQUEST_METHOD'];
    if ($method === 'GET') {
        if (($_GET['action'] ?? '') === 'context') {
            $context = json_decode($_GET['context'] ?? '{}', true);
            $info = $store->describe($context);
            unset($info['source']);
            success_response($info);
        }
        if (isset($_GET['id'])) success_response($store->detail((int) $_GET['id'], (int) ($_GET['before'] ?? 0)));
        success_response($store->listing((int) ($_GET['before'] ?? 0)));
    }
    $input = get_json_input();
    if (!is_array($input)) throw new PrismaAssistantException('La petición no es válida.');
    if ($method === 'POST') success_response($store->create($input['context'] ?? []));
    if ($method === 'PUT') success_response($store->rename((int) ($input['id'] ?? 0), $input['title'] ?? '', $input['revision'] ?? 0));
    if ($method === 'DELETE') success_response($store->delete((int) ($input['id'] ?? 0)));
    throw new PrismaAssistantException('Método no permitido.', 405);
} catch (Throwable $e) { assistant_http_error($e); }
