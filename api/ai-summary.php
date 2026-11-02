<?php
/**
 * AI Summary API - Resume los comentarios de una petición con OpenRouter
 *
 * POST { request_id: 123 } -> { summary: "…", points: [ "…" ] }
 *
 * Solo lee: no escribe nada en BD. El resumen es efímero (no se guarda).
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/ai.php';

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

try {
    $config = prisma_ai_config($db);
    prisma_ai_release_session();
    $result = prisma_ai_structured($config, [
        ['role' => 'system', 'content' => $systemPrompt],
        ['role' => 'user', 'content' => $context . "\n\nComentarios:\n\n" . $transcript]
    ], 'comments_summary', $schema, 2048);
    $parsed = $result['data'];
    $model = $result['model'];
} catch (PrismaAIException $e) {
    error_response($e->getMessage(), $e->httpStatus());
}

$points = array_values(array_filter(array_map('trim', $parsed['points']), fn($p) => $p !== ''));
if (empty($points)) {
    error_response('La IA no pudo generar un resumen. Vuelve a intentarlo.', 502);
}

success_response(['points' => array_map(fn($p) => mb_substr($p, 0, 300), $points), 'model' => $model]);
