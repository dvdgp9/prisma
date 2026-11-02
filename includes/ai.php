<?php
/** Shared, bounded OpenRouter client. No authentication, output or DB writes here. */
require_once __DIR__ . '/encryption.php';

const PRISMA_AI_DEFAULT_MODEL = 'google/gemini-3.1-flash-lite';
const PRISMA_AI_MAX_RESPONSE_BYTES = 1048576;

class PrismaAIException extends RuntimeException
{
    private $httpStatus;

    public function __construct($message, $httpStatus = 502)
    {
        parent::__construct($message);
        $this->httpStatus = $httpStatus;
    }

    public function httpStatus()
    {
        return $this->httpStatus;
    }
}

function prisma_ai_config($db)
{
    $stmt = $db->prepare("SELECT setting_key, setting_value FROM app_settings WHERE setting_key IN ('openrouter_api_key', 'ai_model')");
    $stmt->execute();
    $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    if (empty($settings['openrouter_api_key'])) {
        throw new PrismaAIException('La IA no está configurada. Un superadmin debe añadir la API key en el panel de administración.', 503);
    }
    $key = decrypt($settings['openrouter_api_key']);
    if (!is_string($key) || trim($key) === '') {
        throw new PrismaAIException('No se pudo leer la configuración de IA. Revisa la clave en administración.', 503);
    }
    return ['api_key' => $key, 'model' => trim($settings['ai_model'] ?? '') ?: PRISMA_AI_DEFAULT_MODEL];
}

/** Release the PHP session lock before a slow provider call, after resolving user/context. */
function prisma_ai_release_session()
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
}

function prisma_ai_http($payload, $apiKey)
{
    $body = '';
    $ch = curl_init('https://openrouter.ai/api/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
            'X-OpenRouter-Title: Prisma'
        ],
        CURLOPT_WRITEFUNCTION => static function ($handle, $chunk) use (&$body) {
            if (strlen($body) + strlen($chunk) > PRISMA_AI_MAX_RESPONSE_BYTES) {
                return 0;
            }
            $body .= $chunk;
            return strlen($chunk);
        }
    ]);
    $ok = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    // Never expose provider bodies, curl errors or authorization headers to clients/logs.
    return ['status' => $status, 'body' => $body, 'failed' => $ok === false];
}

/**
 * Validates the JSON Schema subset used by Prisma. Decode as objects so {} and []
 * stay distinct. Unsupported assertions fail closed; extend deliberately when needed.
 */
function prisma_ai_schema_matches($value, array $schema, $depth = 0)
{
    if ($depth > 24) return false;
    $supported = ['type', 'properties', 'required', 'additionalProperties', 'items', 'enum',
        'minItems', 'maxItems', 'minLength', 'maxLength', 'minimum', 'maximum', 'description', 'title'];
    if (array_diff(array_keys($schema), $supported)) return false;
    $types = (array) ($schema['type'] ?? []);
    $actual = $value === null ? 'null' : (is_object($value) ? 'object' :
        (is_array($value) ? 'array' : (is_int($value) ? 'integer' :
        (is_float($value) ? 'number' : (is_bool($value) ? 'boolean' : 'string')))));
    if (!in_array($actual, $types, true) && !($actual === 'integer' && in_array('number', $types, true))) return false;
    if (isset($schema['enum']) && !in_array($value, $schema['enum'], true)) return false;
    if ($actual === 'object') {
        $properties = get_object_vars($value);
        foreach ($schema['required'] ?? [] as $key) {
            if (!array_key_exists($key, $properties)) return false;
        }
        foreach ($properties as $key => $child) {
            if (!array_key_exists($key, $schema['properties'] ?? [])) {
                if (($schema['additionalProperties'] ?? true) === false) return false;
                continue;
            }
            if (!prisma_ai_schema_matches($child, $schema['properties'][$key], $depth + 1)) return false;
        }
    } elseif ($actual === 'array') {
        if (count($value) < ($schema['minItems'] ?? 0) || count($value) > ($schema['maxItems'] ?? PHP_INT_MAX)) return false;
        foreach ($value as $child) {
            if (!prisma_ai_schema_matches($child, $schema['items'] ?? [], $depth + 1)) return false;
        }
    } elseif ($actual === 'string') {
        if (mb_strlen($value) < ($schema['minLength'] ?? 0) || mb_strlen($value) > ($schema['maxLength'] ?? PHP_INT_MAX)) return false;
    } elseif ($actual === 'number' || $actual === 'integer') {
        if (isset($schema['minimum']) && $value < $schema['minimum']) return false;
        if (isset($schema['maximum']) && $value > $schema['maximum']) return false;
    }
    return true;
}

/** Injectable transport for isolated tests. Result contains content, model, usage only. */
function prisma_ai_complete(array $config, array $messages, $format = null, $maxTokens = 4096, $transport = null)
{
    if (empty($config['api_key']) || empty($config['model'])) {
        throw new PrismaAIException('La IA no está configurada.', 503);
    }
    if (!$messages || count($messages) > 40) {
        throw new PrismaAIException('La conversación es demasiado larga para esta consulta.', 400);
    }
    $characters = 0;
    foreach ($messages as $message) {
        if (!is_array($message) || !in_array($message['role'] ?? '', ['system', 'user', 'assistant'], true) || !is_string($message['content'] ?? null)) {
            throw new PrismaAIException('El mensaje de IA no es válido.', 400);
        }
        $characters += mb_strlen($message['content']);
    }
    if ($characters > 100000) {
        throw new PrismaAIException('El contexto es demasiado largo para esta consulta.', 400);
    }
    $payload = ['model' => $config['model'], 'messages' => $messages, 'max_tokens' => max(1, min(8192, (int) $maxTokens))];
    if ($format !== null) $payload['response_format'] = $format;
    try {
        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        throw new PrismaAIException('El mensaje contiene texto no válido.', 400);
    }
    try {
        $response = ($transport ?? 'prisma_ai_http')($encoded, $config['api_key']);
    } catch (Throwable $e) {
        throw new PrismaAIException('No se pudo conectar con el servicio de IA. Vuelve a intentarlo.');
    }
    if (!empty($response['failed'])) {
        throw new PrismaAIException('No se pudo conectar con el servicio de IA. Vuelve a intentarlo.');
    }
    if (($response['status'] ?? 0) !== 200) {
        $limited = ($response['status'] ?? 0) === 429;
        throw new PrismaAIException($limited ? 'El servicio de IA está ocupado. Vuelve a intentarlo en unos instantes.' : 'El servicio de IA no pudo completar la consulta. Revisa la configuración o vuelve a intentarlo.', $limited ? 429 : 502);
    }
    if (!is_string($response['body'] ?? null) || strlen($response['body']) > PRISMA_AI_MAX_RESPONSE_BYTES) {
        throw new PrismaAIException('La IA devolvió una respuesta demasiado grande. Reduce la consulta.');
    }
    $body = json_decode($response['body'], true);
    $choice = $body['choices'][0] ?? [];
    $content = $choice['message']['content'] ?? null;
    if (($choice['finish_reason'] ?? '') !== 'stop' || !is_string($content) || trim($content) === '' || !empty($choice['message']['refusal'])) {
        throw new PrismaAIException('La IA no pudo completar una respuesta válida. Reduce la consulta o vuelve a intentarlo.');
    }
    $usage = [];
    foreach (['prompt_tokens', 'completion_tokens', 'total_tokens'] as $field) {
        if (isset($body['usage'][$field]) && is_numeric($body['usage'][$field])) {
            $usage[$field] = max(0, (int) $body['usage'][$field]);
        }
    }
    return ['content' => $content, 'model' => $config['model'], 'usage' => $usage];
}

function prisma_ai_structured(array $config, array $messages, $name, array $schema, $maxTokens = 4096, $transport = null)
{
    if (!preg_match('/^[a-zA-Z0-9_-]{1,64}$/D', $name)) {
        throw new InvalidArgumentException('Invalid AI schema name');
    }
    $result = prisma_ai_complete($config, $messages, [
        'type' => 'json_schema',
        'json_schema' => ['name' => $name, 'strict' => true, 'schema' => $schema]
    ], $maxTokens, $transport);
    $value = json_decode($result['content']);
    if (json_last_error() !== JSON_ERROR_NONE || !prisma_ai_schema_matches($value, $schema)) {
        throw new PrismaAIException('La IA devolvió una respuesta no válida. Vuelve a intentarlo.');
    }
    $result['data'] = json_decode($result['content'], true);
    unset($result['content']);
    return $result;
}
