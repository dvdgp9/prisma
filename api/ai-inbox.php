<?php
/**
 * AI Inbox API - Analiza una nota en bruto con OpenRouter y propone items
 *
 * POST { note: "texto" } -> { items: [ { tipo, app_id, app_name, title,
 *   description, priority, subtasks[], reasoning } ] }
 *
 * Solo analiza: NO escribe nada en BD. La creación se hace desde el frontend
 * con las APIs existentes (requests.php, request-checklist.php, tasks.php).
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/encryption.php';

header('Content-Type: application/json');

require_login();
$user = get_logged_user();
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_response('Method not allowed', 405);
}

$input = get_json_input();
$note = trim($input['note'] ?? '');

if ($note === '') {
    error_response('La nota está vacía');
}
if (mb_strlen($note) > 10000) {
    error_response('La nota es demasiado larga (máximo 10.000 caracteres)');
}

// --- Configuración IA ---
$stmt = $db->prepare("SELECT setting_key, setting_value FROM app_settings WHERE setting_key IN ('openrouter_api_key', 'ai_model')");
$stmt->execute();
$settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

if (empty($settings['openrouter_api_key'])) {
    error_response('La IA no está configurada. Un superadmin debe añadir la API key en el panel de administración.', 503);
}
$apiKey = decrypt($settings['openrouter_api_key']);
$model = $settings['ai_model'] ?? 'google/gemini-3.1-flash-lite';

// --- Apps del usuario para que el modelo clasifique contra apps reales ---
$apps = get_user_apps();
if (empty($apps)) {
    error_response('No tienes acceso a ninguna aplicación.', 403);
}

$appsList = implode("\n", array_map(function ($a) {
    $desc = trim($a['description'] ?? '');
    return "- id={$a['id']} | {$a['name']} (empresa: {$a['company_name']})" . ($desc !== '' ? " — {$desc}" : '');
}, $apps));

$validAppIds = array_map('intval', array_column($apps, 'id'));

// --- Prompt ---
$systemPrompt = <<<PROMPT
Eres el asistente de Prisma, una herramienta de gestión de mejoras y tareas de desarrollo.
El usuario pegará una nota en bruto (normalmente apuntes de una reunión, en español).
Tu trabajo: extraer los elementos accionables y clasificarlos.

Tipos posibles:
- "mejora": una petición/mejora/bug ligada a una aplicación concreta. Puede tener subtareas (pasos concretos).
- "tarea": una tarea rápida personal del usuario, no ligada necesariamente a una app (recados, gestiones, recordatorios).

Aplicaciones disponibles (usa EXACTAMENTE estos id):
{$appsList}

Reglas:
- Si un elemento menciona claramente una app de la lista, asigna su app_id. Si no está claro, usa app_id null (el usuario lo decidirá).
- Para "tarea", app_id puede ser null.
- No inventes elementos que no estén en la nota. Ignora saludos, contexto irrelevante o decisiones ya cerradas que no requieren acción.
- Títulos cortos y accionables (máx ~80 caracteres). Descripción con el contexto útil de la nota.
- Prioridad: usa "high"/"critical" solo si la nota lo sugiere (urgente, bloqueante, "para ya"); por defecto "medium".
- subtasks: solo si la nota describe pasos concretos; si no, array vacío.
- reasoning: déjalo VACÍO ("") en la mayoría de casos. Escribe una frase corta en español SOLO si: la clasificación es dudosa (no estás seguro de la app o del tipo), falta información relevante en la nota, o hay algo importante que el usuario deba saber antes de crear el elemento. No expliques clasificaciones obvias.
- Todo el texto de salida en español.
PROMPT;

$schema = [
    'type' => 'object',
    'properties' => [
        'items' => [
            'type' => 'array',
            'items' => [
                'type' => 'object',
                'properties' => [
                    'tipo' => ['type' => 'string', 'enum' => ['mejora', 'tarea'], 'description' => 'mejora = request de una app; tarea = tarea rápida personal'],
                    'app_id' => ['type' => ['integer', 'null'], 'description' => 'ID de la app de la lista, o null si no está claro'],
                    'title' => ['type' => 'string', 'description' => 'Título corto y accionable'],
                    'description' => ['type' => 'string', 'description' => 'Contexto útil extraído de la nota'],
                    'priority' => ['type' => 'string', 'enum' => ['low', 'medium', 'high', 'critical']],
                    'subtasks' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Pasos concretos si la nota los describe'],
                    'reasoning' => ['type' => 'string', 'description' => 'Vacío salvo clasificación dudosa, información que falta o aviso importante; entonces una frase en español']
                ],
                'required' => ['tipo', 'app_id', 'title', 'description', 'priority', 'subtasks', 'reasoning'],
                'additionalProperties' => false
            ]
        ]
    ],
    'required' => ['items'],
    'additionalProperties' => false
];

$payload = json_encode([
    'model' => $model,
    'messages' => [
        ['role' => 'system', 'content' => $systemPrompt],
        ['role' => 'user', 'content' => $note]
    ],
    'response_format' => [
        'type' => 'json_schema',
        'json_schema' => [
            'name' => 'inbox_items',
            'strict' => true,
            'schema' => $schema
        ]
    ]
]);

// --- Llamada a OpenRouter ---
$ch = curl_init('https://openrouter.ai/api/v1/chat/completions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_TIMEOUT => 60,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey,
        'X-OpenRouter-Title: Prisma'
    ]
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($response === false) {
    error_response('No se pudo conectar con el servicio de IA: ' . $curlError, 502);
}
if ($httpCode !== 200) {
    $body = json_decode($response, true);
    $msg = $body['error']['message'] ?? ('HTTP ' . $httpCode);
    error_response('El servicio de IA devolvió un error: ' . $msg, 502);
}

$body = json_decode($response, true);
$content = $body['choices'][0]['message']['content'] ?? null;
$parsed = $content ? json_decode($content, true) : null;

if (!is_array($parsed) || !isset($parsed['items']) || !is_array($parsed['items'])) {
    error_response('La IA devolvió una respuesta no válida. Vuelve a intentarlo.', 502);
}

// --- Validación servidor: app_ids reales y campos saneados ---
$items = [];
$appNames = [];
foreach ($apps as $a) {
    $appNames[(int) $a['id']] = $a['name'];
}

foreach ($parsed['items'] as $item) {
    $appId = isset($item['app_id']) && $item['app_id'] !== null ? (int) $item['app_id'] : null;
    if ($appId !== null && !in_array($appId, $validAppIds, true)) {
        $appId = null; // app inventada por el modelo -> el usuario decide
    }
    $title = trim($item['title'] ?? '');
    if ($title === '') {
        continue;
    }
    $items[] = [
        'tipo' => ($item['tipo'] ?? 'mejora') === 'tarea' ? 'tarea' : 'mejora',
        'app_id' => $appId,
        'app_name' => $appId !== null ? $appNames[$appId] : null,
        'title' => mb_substr($title, 0, 200),
        'description' => trim($item['description'] ?? ''),
        'priority' => in_array($item['priority'] ?? '', ['low', 'medium', 'high', 'critical'], true) ? $item['priority'] : 'medium',
        'subtasks' => array_values(array_filter(array_map('trim', $item['subtasks'] ?? []))),
        'reasoning' => trim($item['reasoning'] ?? '')
    ];
}

success_response(['items' => $items, 'model' => $model]);
