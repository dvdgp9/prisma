<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/ai-assistant.php';

function assistant_actor($db)
{
    $sessionUser = get_logged_user();
    if (!$sessionUser) throw new PrismaAssistantException('Tu sesión ha caducado. Vuelve a entrar en Prisma.', 401);
    $stmt = $db->prepare('SELECT id, role, company_id, is_active FROM users WHERE id = ?');
    $stmt->execute([$sessionUser['id']]);
    $current = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$current || !$current['is_active']) throw new PrismaAssistantException('Tu sesión ha caducado. Vuelve a entrar en Prisma.', 401);
    // Existing capability helpers read the session; refresh role/company from the DB.
    $_SESSION['role'] = $current['role'];
    $_SESSION['company_id'] = $current['company_id'];
    return array_merge($sessionUser, $current);
}

function assistant_access_resolver($db, $user)
{
    return static function ($type, $id) use ($db, $user) {
        $caps = [];
        if ($type === 'scope_mine' || $type === 'scope_team') {
            if ($id !== (int) $user['id']) return null;
            if ($type === 'scope_team' && !in_array($user['role'], ['programador', 'admin', 'superadmin'], true)) return null;
            $label = $type === 'scope_mine' ? 'Mi trabajo' : 'Trabajo del equipo';
            $caps = [$user['role'], $user['company_id']];
        } elseif ($type === 'app') {
            if (!can_access_app($id)) return null;
            $stmt = $db->prepare('SELECT name, company_id, is_active FROM apps WHERE id = ?');
            $stmt->execute([$id]);
            $app = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$app || !$app['is_active']) return null;
            $label = 'Aplicación · ' . $app['name'];
            $caps = [$app['company_id'], $user['role']];
        } elseif ($type === 'request') {
            $access = get_request_capabilities($id, $db);
            if (!$access || !$access['capabilities']['view']) return null;
            $stmt = $db->prepare('SELECT title FROM requests WHERE id = ?');
            $stmt->execute([$id]);
            $label = 'Mejora · ' . $stmt->fetchColumn();
            $caps = [$access['request']['app_id'], $access['capabilities']];
        } elseif ($type === 'task' || $type === 'note') {
            $access = $type === 'task' ? get_task_access($id, $db) : get_note_access($id, $db);
            if (!$access) return null;
            $label = ($type === 'task' ? 'Tarea · ' : 'Nota · ') . $access[$type]['title'];
            $caps = [$access[$type]['company_id'], $access['capabilities']];
        } else return null;
        return ['label' => $label, 'access_fingerprint' => hash('sha256', json_encode([$type, $id, $user['id'], $user['company_id'], $user['role'], $caps]))];
    };
}

function assistant_store($db)
{
    $actor = assistant_actor($db);
    return new PrismaAssistantStore($db, $actor, assistant_access_resolver($db, $actor));
}

function assistant_http_start()
{
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, private');
    if (!is_logged_in()) throw new PrismaAssistantException('Tu sesión ha caducado. Vuelve a entrar en Prisma.', 401);
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        $sent = $_SERVER['HTTP_X_PRISMA_CSRF'] ?? '';
        if (!is_string($sent) || empty($_SESSION['assistant_csrf']) || !hash_equals($_SESSION['assistant_csrf'], $sent)) throw new PrismaAssistantException('Recarga Prisma antes de guardar los cambios.', 403);
    }
    $db = getDB();
    // Fail gracefully when deploying the UI before migration 020.
    try { $db->query('SELECT id FROM ai_proposals LIMIT 0'); }
    catch (PDOException $e) { throw new PrismaAssistantException('El asistente estará disponible cuando termine su configuración.', 503); }
    return $db;
}

function assistant_http_error($e)
{
    if ($e instanceof PrismaAssistantException) error_response($e->getMessage(), $e->status);
    if ($e instanceof PrismaAIException) error_response($e->getMessage(), $e->httpStatus());
    // No DB/provider bodies or user content in public responses or logs.
    error_log('Prisma assistant: ' . get_class($e));
    error_response('No se pudo completar la operación. Vuelve a intentarlo.', 500);
}
