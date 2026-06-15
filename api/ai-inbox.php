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

// --- Usuarios asignables (para emparejar responsables nombrados en la nota) ---
$stmt = $db->query("SELECT id, username, full_name FROM users WHERE is_active = 1");
$assignableUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);

/**
 * Empareja un nombre suelto (lo que la IA detecta como responsable) con un usuario real.
 * Conservador: exige coincidencia exacta de username/nombre completo, o que el nombre
 * coincida con el primer nombre del usuario. Devuelve null si hay duda o ambigüedad.
 */
function match_assignee(string $name, array $users): ?array
{
    $needle = mb_strtolower(trim($name));
    if ($needle === '') return null;

    $matches = [];
    foreach ($users as $u) {
        $full = mb_strtolower(trim($u['full_name'] ?? ''));
        $username = mb_strtolower(trim($u['username'] ?? ''));
        $firstName = $full !== '' ? explode(' ', $full)[0] : '';

        if ($needle === $username || $needle === $full || ($firstName !== '' && $needle === $firstName)) {
            $matches[$u['id']] = $u;
        }
    }

    // Solo asignamos si la coincidencia es inequívoca (un único usuario).
    return count($matches) === 1 ? reset($matches) : null;
}

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
- assignee_name: nombre de la persona responsable de ese elemento, SOLO si la nota la nombra explícitamente como encargada (p.ej. "que lo haga Juan", "lo lleva María", "asignar a Pedro"). Copia el nombre tal cual aparece. Si no se nombra a nadie como responsable, deja "". NUNCA infieras ni inventes responsables.
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
                    'assignee_name' => ['type' => 'string', 'description' => 'Nombre del responsable SOLO si la nota lo nombra explícitamente; si no, ""'],
                    'reasoning' => ['type' => 'string', 'description' => 'Vacío salvo clasificación dudosa, información que falta o aviso importante; entonces una frase en español']
                ],
                'required' => ['tipo', 'app_id', 'title', 'description', 'priority', 'subtasks', 'assignee_name', 'reasoning'],
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
    $tipo = ($item['tipo'] ?? 'mejora') === 'tarea' ? 'tarea' : 'mejora';

    // Responsable: solo para mejoras y solo si la IA nombró a alguien que casa con un usuario real.
    $assigneeId = null;
    $assigneeName = null;
    if ($tipo === 'mejora' && !empty($item['assignee_name'])) {
        $matched = match_assignee($item['assignee_name'], $assignableUsers);
        if ($matched) {
            $assigneeId = (int) $matched['id'];
            $assigneeName = $matched['full_name'] ?: $matched['username'];
        }
    }

    $items[] = [
        'tipo' => $tipo,
        'app_id' => $appId,
        'app_name' => $appId !== null ? $appNames[$appId] : null,
        'title' => mb_substr($title, 0, 200),
        'description' => trim($item['description'] ?? ''),
        'priority' => in_array($item['priority'] ?? '', ['low', 'medium', 'high', 'critical'], true) ? $item['priority'] : 'medium',
        'subtasks' => array_values(array_filter(array_map('trim', $item['subtasks'] ?? []))),
        'assignee_id' => $assigneeId,
        'assignee_name' => $assigneeName,
        'reasoning' => trim($item['reasoning'] ?? '')
    ];
}

success_response(['items' => $items, 'model' => $model]);
