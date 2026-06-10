<?php
/**
 * AI Settings API - Configuración de OpenRouter (superadmin only)
 *
 * GET  -> { model, key_set } (la key nunca se devuelve)
 * POST -> { api_key?, model? } guarda ajustes (key cifrada)
 * POST ?action=test -> llamada real mínima a OpenRouter para validar la key
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/encryption.php';

header('Content-Type: application/json');

require_role('superadmin');

$method = $_SERVER['REQUEST_METHOD'];
$db = getDB();

function get_setting($db, $key)
{
    $stmt = $db->prepare('SELECT setting_value FROM app_settings WHERE setting_key = ?');
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    return $row ? $row['setting_value'] : null;
}

function set_setting($db, $key, $value)
{
    $stmt = $db->prepare('
        INSERT INTO app_settings (setting_key, setting_value) VALUES (?, ?)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
    ');
    $stmt->execute([$key, $value]);
}

switch ($method) {
    case 'GET':
        try {
            success_response([
                'model' => get_setting($db, 'ai_model') ?? 'google/gemini-3.1-flash-lite',
                'key_set' => !empty(get_setting($db, 'openrouter_api_key'))
            ]);
        } catch (Exception $e) {
            error_response('Failed to fetch AI settings: ' . $e->getMessage(), 500);
        }
        break;

    case 'POST':
        if (($_GET['action'] ?? '') === 'test') {
            // Probar conexión con una llamada mínima real
            try {
                $encrypted = get_setting($db, 'openrouter_api_key');
                if (empty($encrypted)) {
                    error_response('No hay API key guardada. Guárdala primero.');
                }
                $apiKey = decrypt($encrypted);
                $model = get_setting($db, 'ai_model') ?? 'google/gemini-3.1-flash-lite';

                $payload = json_encode([
                    'model' => $model,
                    'messages' => [['role' => 'user', 'content' => 'Responde solo: OK']],
                    'max_tokens' => 10
                ]);

                $ch = curl_init('https://openrouter.ai/api/v1/chat/completions');
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => $payload,
                    CURLOPT_TIMEOUT => 30,
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
                    error_response('Error de conexión con OpenRouter: ' . $curlError, 502);
                }
                if ($httpCode !== 200) {
                    $body = json_decode($response, true);
                    $msg = $body['error']['message'] ?? ('HTTP ' . $httpCode);
                    error_response('OpenRouter rechazó la petición: ' . $msg, 502);
                }

                success_response(['model' => $model], 'Conexión correcta con ' . $model);
            } catch (Exception $e) {
                error_response('Test failed: ' . $e->getMessage(), 500);
            }
            break;
        }

        // Guardar ajustes
        $input = get_json_input();
        try {
            if (!empty($input['api_key'])) {
                set_setting($db, 'openrouter_api_key', encrypt(trim($input['api_key'])));
            }
            if (!empty($input['model'])) {
                set_setting($db, 'ai_model', trim($input['model']));
            }
            success_response([], 'Ajustes de IA guardados');
        } catch (Exception $e) {
            error_response('Failed to save AI settings: ' . $e->getMessage(), 500);
        }
        break;

    default:
        error_response('Method not allowed', 405);
}
