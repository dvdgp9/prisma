<?php
/**
 * AI Summary API - Resume los comentarios de una petición con OpenRouter
 *
 * POST { request_id: 123 } -> { summary: "…", points: [ "…" ] }
 *
 * Solo lee: no escribe nada en BD. El resumen es efímero (no se guarda).
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/encryption.php';

header('Content-Type: application/json');

require_login();
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_response('Method not allowed', 405);
}

$input = get_json_input();
$requestId = (int) ($input['request_id'] ?? 0);
if ($requestId <= 0) {
    error_response('Request ID is required');
}

require_request_capability($requestId, 'view');

// --- Petición y comentarios (siempre desde BD, nunca del cliente) ---
$stmt = $db->prepare("SELECT title, description FROM requests WHERE id = ?");
$stmt->execute([$requestId]);
$request = $stmt->fetch();
if (!$request) {
    error_response('Petición no encontrada', 404);
}

$stmt = $db->prepare("
    SELECT c.content, c.created_at, COALESCE(NULLIF(u.full_name, ''), u.username) AS author
    FROM request_comments c
    INNER JOIN users u ON c.user_id = u.id
    WHERE c.request_id = ?
    ORDER BY c.created_at ASC
");
$stmt->execute([$requestId]);
$comments = $stmt->fetchAll();

if (count($comments) < 2) {
    error_response('No hay suficientes comentarios para resumir');
}

// --- Configuración IA (mismo patrón que ai-inbox.php) ---
$stmt = $db->prepare("SELECT setting_key, setting_value FROM app_settings WHERE setting_key IN ('openrouter_api_key', 'ai_model')");
$stmt->execute();
$settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

if (empty($settings['openrouter_api_key'])) {
    error_response('La IA no está configurada. Un superadmin debe añadir la API key en el panel de administración.', 503);
}
$apiKey = decrypt($settings['openrouter_api_key']);
$model = $settings['ai_model'] ?? 'google/gemini-3.1-flash-lite';

// --- Transcript acotado (los comentarios más recientes pesan más) ---
$lines = [];
foreach ($comments as $c) {
    $date = date('d/m/Y H:i', strtotime($c['created_at']));
    $lines[] = "[{$date}] {$c['author']}: " . trim($c['content']);
}
$transcript = implode("\n\n", $lines);
if (mb_strlen($transcript) > 20000) {
    $transcript = '[…comentarios antiguos omitidos…]' . "\n\n" . mb_substr($transcript, -20000);
}

$context = "Petición: " . trim($request['title']);
$desc = trim($request['description'] ?? '');
if ($desc !== '') {
    $context .= "\nDescripción: " . mb_substr($desc, 0, 1000);
}

$systemPrompt = <<<PROMPT
Eres el asistente de Prisma, una herramienta de gestión de mejoras y tareas de desarrollo.
Recibirás el hilo de comentarios de una petición (mejora/bug) con autor y fecha. Tu trabajo: resumirlo.

Primero identifica el tipo de hilo:
- Si es un REGISTRO DE TRABAJO (los comentarios van marcando acciones realizadas: "hecho X", "subido Y", "corregido Z"), el resumen debe ser una lista de puntos con las acciones realizadas, en orden cronológico, indicando quién hizo cada cosa si hay varios autores.
- Si es una CONVERSACIÓN (debate, dudas, decisiones), resume en puntos: qué se ha decidido, qué problemas se han detectado y qué queda pendiente (y de quién, si se nombra).

Reglas:
- Devuelve entre 2 y 8 puntos, cortos y concretos (máx ~140 caracteres cada uno). Sin frases de relleno.
- No inventes nada que no esté en los comentarios. No repitas la descripción de la petición.
- Si hay algo pendiente o bloqueado, ponlo siempre como último punto empezando por "Pendiente:".
- Todo en español.
PROMPT;

$schema = [
    'type' => 'object',
    'properties' => [
        'points' => [
            'type' => 'array',
            'items' => ['type' => 'string'],
            'description' => 'Puntos del resumen, cortos y concretos, en orden'
        ]
    ],
    'required' => ['points'],
    'additionalProperties' => false
];

$payload = json_encode([
    'model' => $model,
    'messages' => [
        ['role' => 'system', 'content' => $systemPrompt],
        ['role' => 'user', 'content' => $context . "\n\nComentarios:\n\n" . $transcript]
    ],
    'response_format' => [
        'type' => 'json_schema',
        'json_schema' => [
            'name' => 'comments_summary',
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

if (!is_array($parsed) || !isset($parsed['points']) || !is_array($parsed['points'])) {
    error_response('La IA devolvió una respuesta no válida. Vuelve a intentarlo.', 502);
}

$points = array_values(array_filter(array_map('trim', $parsed['points']), fn($p) => $p !== ''));
if (empty($points)) {
    error_response('La IA no pudo generar un resumen. Vuelve a intentarlo.', 502);
}

success_response(['points' => array_map(fn($p) => mb_substr($p, 0, 300), $points), 'model' => $model]);
