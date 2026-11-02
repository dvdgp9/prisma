<?php
/** Persistence and turn lifecycle. Resolver returns current access, never cached titles. */
class PrismaAssistantException extends RuntimeException
{
    public $status;
    public function __construct($message, $status = 400) { parent::__construct($message); $this->status = $status; }
}

function assistant_context($input, $role)
{
    if (!is_array($input)) throw new PrismaAssistantException('El contexto no es válido.');
    $scope = $input['scope'] ?? 'mine';
    if (!in_array($scope, ['mine', 'team', 'app', 'request', 'task', 'note'], true)) throw new PrismaAssistantException('El contexto no es válido.');
    if ($scope === 'team' && !in_array($role, ['programador', 'admin', 'superadmin'], true)) throw new PrismaAssistantException('No tienes acceso al trabajo del equipo.', 403);
    $id = null;
    if (!in_array($scope, ['mine', 'team'], true)) {
        $id = filter_var($input['entity_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) throw new PrismaAssistantException('Selecciona un registro válido.');
    }
    $timezone = $input['timezone'] ?? 'Europe/Madrid';
    if (!is_string($timezone) || !in_array($timezone, DateTimeZone::listIdentifiers(), true)) throw new PrismaAssistantException('La zona horaria no es válida.');
    $filters = $input['filters'] ?? [];
    if ($filters instanceof stdClass) $filters = get_object_vars($filters);
    if (!is_array($filters)) throw new PrismaAssistantException('Los filtros no son válidos.');
    $clean = [];
    foreach ($filters as $key => $value) {
        if ($value === '' || $value === null || $value === 'all') continue;
        if ($key === 'person') {
            if ($value === 'none') $clean[$key] = 'none';
            elseif (filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) !== false) $clean[$key] = (int) $value;
            else throw new PrismaAssistantException('La persona no es válida.');
        } elseif ($key === 'status' && in_array($value, ['pending', 'in_progress', 'completed', 'discarded'], true)) $clean[$key] = $value;
        elseif (in_array($key, ['from', 'to'], true) && is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value) && DateTime::createFromFormat('!Y-m-d', $value)->format('Y-m-d') === $value) $clean[$key] = $value;
        else throw new PrismaAssistantException('El filtro no es válido.');
    }
    if (isset($clean['from'], $clean['to']) && $clean['from'] > $clean['to']) throw new PrismaAssistantException('El rango de fechas no es válido.');
    ksort($clean);
    return ['version' => 1, 'scope' => $scope, 'entity_id' => $id, 'filters' => $clean ?: new stdClass(), 'timezone' => $timezone, 'fixed' => !empty($input['fixed'])];
}

class PrismaAssistantStore
{
    private $db;
    private $user;
    private $resolve;
    public function __construct($db, array $user, callable $resolve) { $this->db = $db; $this->user = $user; $this->resolve = $resolve; }
    private function json($value) { return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR); }
    private function lock() { return $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : ''; }
    private function query($sql, $args = [])
    {
        $stmt = $this->db->prepare($sql);
        foreach (array_values($args) as $i => $value) $stmt->bindValue($i + 1, $value, $value === null ? PDO::PARAM_NULL : (is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR));
        $stmt->execute();
        return $stmt;
    }
    private function transaction(callable $fn)
    {
        $this->db->beginTransaction();
        try { $result = $fn(); $this->db->commit(); return $result; }
        catch (Throwable $e) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $e; }
    }
    private function owned($id)
    {
        $row = $this->query('SELECT * FROM ai_conversations WHERE id = ? AND user_id = ? AND COALESCE(company_id, 0) = ?' . $this->lock(), [(int) $id, $this->user['id'], (int) $this->user['company_id']])->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new PrismaAssistantException('Conversación no encontrada.', 404);
        return $row;
    }
    private function resolve($type, $id)
    {
        $result = ($this->resolve)($type, (int) $id);
        return $result && isset($result['label'], $result['access_fingerprint']) ? $result : null;
    }
    private function contextSource($context)
    {
        $type = in_array($context['scope'], ['mine', 'team'], true) ? 'scope_' . $context['scope'] : $context['scope'];
        return ['entity_type' => $type, 'entity_id' => $context['entity_id'] ?? (int) $this->user['id']];
    }
    public function describe($input)
    {
        $context = assistant_context($input, $this->user['role']);
        $source = $this->contextSource($context);
        $access = $this->resolve($source['entity_type'], $source['entity_id']);
        if (!$access) throw new PrismaAssistantException('Ya no tienes acceso a este contexto.', 403);
        return ['context' => $context, 'label' => $access['label'], 'source' => $source + ['access_fingerprint' => $access['access_fingerprint']]];
    }
    /** Redact everything derived before returning titles, messages or model context. */
    private function revalidate($row)
    {
        $sources = $this->query('SELECT DISTINCT entity_type, entity_id, access_fingerprint FROM ai_message_sources WHERE conversation_id = ?', [$row['id']])->fetchAll(PDO::FETCH_ASSOC);
        $context = json_decode($row['context_json'], true);
        $source = $this->contextSource($context);
        $valid = $this->resolve($source['entity_type'], $source['entity_id']) !== null;
        foreach ($sources as $ref) {
            $access = $this->resolve($ref['entity_type'], $ref['entity_id']);
            if (!$access) { $valid = false; break; }
            if (!hash_equals($ref['access_fingerprint'], $access['access_fingerprint'])) {
                if (in_array($ref['access_fingerprint'], $access['legacy_fingerprints'] ?? [], true)) {
                    $this->query('UPDATE ai_message_sources SET access_fingerprint = ? WHERE conversation_id = ? AND entity_type = ? AND entity_id = ? AND access_fingerprint = ?', [$access['access_fingerprint'], $row['id'], $ref['entity_type'], $ref['entity_id'], $ref['access_fingerprint']]);
                } else { $valid = false; break; }
            }
        }
        if ($valid) return $row;
        $reset = $this->json(assistant_context([], $this->user['role']));
        $this->query("UPDATE ai_messages SET content = '', context_json = ?, state = 'redacted', usage_json = NULL, metadata_json = NULL WHERE conversation_id = ?", [$reset, $row['id']]);
        $this->query("UPDATE ai_proposals SET payload_json = '{}', expected_json = NULL, result_json = NULL, state = 'superseded' WHERE conversation_id = ?", [$row['id']]);
        $this->query('DELETE FROM ai_message_sources WHERE conversation_id = ?', [$row['id']]);
        $this->query("UPDATE ai_conversations SET title = 'Conversación sin acceso', context_json = ?, revision = revision + 1, updated_at = CURRENT_TIMESTAMP WHERE id = ?", [$reset, $row['id']]);
        return $this->owned($row['id']);
    }
    private function summary($row)
    {
        $context = json_decode($row['context_json'], true);
        $source = $this->contextSource($context);
        $label = $this->resolve($source['entity_type'], $source['entity_id']);
        $title = $row['title'];
        if ($title === 'Nueva conversación') {
            // Derive a readable label for existing chats too, after their access was revalidated.
            $first = $this->query("SELECT content FROM ai_messages WHERE conversation_id = ? AND role = 'user' AND state <> 'redacted' ORDER BY id ASC LIMIT 1", [$row['id']])->fetchColumn();
            if (is_string($first) && trim($first) !== '') {
                $plain = trim(preg_replace('/\s+/u', ' ', $first));
                $title = mb_strlen($plain) > 80 ? mb_substr($plain, 0, 79) . '…' : $plain;
            }
        }
        return ['id' => (int) $row['id'], 'title' => $title, 'revision' => (int) $row['revision'], 'context' => $context, 'context_label' => $label['label'] ?? 'Mi trabajo', 'updated_at' => $row['updated_at']];
    }
    public function create($context)
    {
        $info = $this->describe($context);
        return $this->transaction(function () use ($info) {
            $this->query("INSERT INTO ai_conversations (user_id, company_id, title, context_json) VALUES (?, ?, 'Nueva conversación', ?)", [$this->user['id'], $this->user['company_id'], $this->json($info['context'])]);
            return $this->summary($this->owned($this->db->lastInsertId()));
        });
    }
    public function listing($before = 0)
    {
        // Stable creation order: opening/updating a chat does not skip rows between pages.
        $rows = $this->query('SELECT id FROM ai_conversations WHERE user_id = ? AND COALESCE(company_id, 0) = ? AND (? = 0 OR id < ?) ORDER BY id DESC LIMIT 21', [$this->user['id'], (int) $this->user['company_id'], (int) $before, (int) $before])->fetchAll(PDO::FETCH_COLUMN);
        $more = count($rows) > 20;
        $items = [];
        foreach (array_slice($rows, 0, 20) as $id) {
            $items[] = $this->transaction(function () use ($id) { return $this->summary($this->revalidate($this->owned($id))); });
        }
        return ['items' => $items, 'next_cursor' => $more ? end($items)['id'] : null];
    }
    public function detail($id, $before = 0)
    {
        return $this->transaction(function () use ($id, $before) {
            $row = $this->revalidate($this->owned($id));
            $this->expirePending($id);
            $messages = $this->query('SELECT id, role, content, state, context_json, metadata_json, created_at, client_key FROM ai_messages WHERE conversation_id = ? AND (? = 0 OR id < ?) ORDER BY id DESC LIMIT 51', [$id, (int) $before, (int) $before])->fetchAll(PDO::FETCH_ASSOC);
            $more = count($messages) > 50;
            $messages = array_reverse(array_slice($messages, 0, 50));
            foreach ($messages as &$message) {
                $message['id'] = (int) $message['id'];
                $message['context'] = json_decode($message['context_json'], true);
                $message['metadata'] = json_decode($message['metadata_json'] ?? 'null', true);
                unset($message['context_json'], $message['metadata_json']);
            }
            return ['conversation' => $this->summary($row), 'messages' => $messages, 'next_cursor' => $more ? $messages[0]['id'] : null];
        });
    }
    public function rename($id, $title, $revision)
    {
        if (!is_string($title) || trim($title) === '' || mb_strlen(trim($title)) > 200) throw new PrismaAssistantException('El nombre debe tener entre 1 y 200 caracteres.');
        return $this->transaction(function () use ($id, $title, $revision) {
            $row = $this->revalidate($this->owned($id));
            if ((int) $row['revision'] !== (int) $revision) throw new PrismaAssistantException('La conversación ha cambiado. Recárgala y vuelve a intentarlo.', 409);
            $this->query('UPDATE ai_conversations SET title = ?, revision = revision + 1, updated_at = CURRENT_TIMESTAMP WHERE id = ?', [trim($title), $id]);
            return $this->summary($this->owned($id));
        });
    }
    public function delete($id)
    {
        return $this->transaction(function () use ($id) { $this->owned($id); $this->query('DELETE FROM ai_conversations WHERE id = ?', [$id]); return null; });
    }
    private function expirePending($id)
    {
        $now = $this->query('SELECT CURRENT_TIMESTAMP')->fetchColumn();
        foreach ($this->query("SELECT id, created_at FROM ai_messages WHERE conversation_id = ? AND state = 'pending'", [$id])->fetchAll(PDO::FETCH_ASSOC) as $pending) {
            if (strtotime($now) - strtotime($pending['created_at']) > 90) $this->query("UPDATE ai_messages SET state = 'failed' WHERE id = ? AND state = 'pending'", [$pending['id']]);
        }
    }
    public function reserve($id, $content, $key, $inputContext, $revision)
    {
        if (!is_string($content) || trim($content) === '' || mb_strlen($content) > 10000) throw new PrismaAssistantException('Escribe un mensaje de hasta 10.000 caracteres.');
        if (!is_string($key) || !preg_match('/^[a-zA-Z0-9_-]{16,48}$/D', $key)) throw new PrismaAssistantException('El identificador del mensaje no es válido.');
        return $this->transaction(function () use ($id, $content, $key, $inputContext, $revision) {
            $row = $this->revalidate($this->owned($id));
            $this->expirePending($id);
            $existing = $this->query('SELECT * FROM ai_messages WHERE conversation_id = ? AND client_key = ?', [$id, $key])->fetch(PDO::FETCH_ASSOC);
            if ($existing && ($existing['content'] !== trim($content) || $existing['state'] === 'redacted')) throw new PrismaAssistantException('Este envío ya no corresponde al mensaje original.', 409);
            if ($existing && $existing['state'] === 'complete') return ['replay' => true];
            if ($existing && $this->query('SELECT id FROM ai_messages WHERE conversation_id = ? AND id > ? LIMIT 1', [$id, $existing['id']])->fetchColumn()) throw new PrismaAssistantException('Continúa con un mensaje nuevo; esta conversación ya ha avanzado.', 409);
            if ($this->query("SELECT id FROM ai_messages WHERE conversation_id = ? AND state = 'pending' LIMIT 1", [$id])->fetchColumn()) throw new PrismaAssistantException('Hay una respuesta en curso en esta conversación.', 409);
            if ((int) $row['revision'] !== (int) $revision) throw new PrismaAssistantException('La conversación ha cambiado. Recárgala antes de enviar.', 409);
            $info = $this->describe($existing ? json_decode($existing['context_json'], true) : $inputContext);
            $contextJson = $this->json($info['context']);
            if ($existing) {
                $messageId = (int) $existing['id'];
                $this->query("UPDATE ai_messages SET state = 'pending', turn_revision = ?, created_at = CURRENT_TIMESTAMP WHERE id = ?", [(int) $row['revision'] + 1, $messageId]);
            } else {
                $this->query("INSERT INTO ai_messages (conversation_id, role, client_key, content, context_json, state, turn_revision) VALUES (?, 'user', ?, ?, ?, 'pending', ?)", [$id, $key, trim($content), $contextJson, (int) $row['revision'] + 1]);
                $messageId = (int) $this->db->lastInsertId();
            }
            // All prior dependencies are inherited; a conversation is invalidated as a unit.
            $refs = $this->query('SELECT DISTINCT entity_type, entity_id, access_fingerprint FROM ai_message_sources WHERE conversation_id = ?', [$id])->fetchAll(PDO::FETCH_ASSOC);
            $refs[] = $info['source'];
            $unique = [];
            foreach ($refs as $ref) $unique[$ref['entity_type'] . ':' . $ref['entity_id']] = $ref;
            $this->query('DELETE FROM ai_message_sources WHERE message_id = ?', [$messageId]);
            foreach ($unique as $ref) $this->query('INSERT INTO ai_message_sources (message_id, conversation_id, entity_type, entity_id, access_fingerprint) VALUES (?, ?, ?, ?, ?)', [$messageId, $id, $ref['entity_type'], $ref['entity_id'], $ref['access_fingerprint']]);
            $this->query('UPDATE ai_conversations SET context_json = ?, revision = revision + 1, updated_at = CURRENT_TIMESTAMP WHERE id = ?', [$contextJson, $id]);
            $history = $this->query("SELECT role, content, metadata_json FROM ai_messages WHERE conversation_id = ? AND (state = 'complete' OR id = ?) ORDER BY id DESC LIMIT 20", [$id, $messageId])->fetchAll(PDO::FETCH_ASSOC);
            $history = array_reverse($history);
            foreach ($history as &$historyMessage) {
                $metadata = json_decode($historyMessage['metadata_json'] ?? 'null', true);
                if ($metadata) $historyMessage['content'] .= "\n\nReferencias y consulta de esta respuesta (datos del servidor): " . $this->json($metadata);
                unset($historyMessage['metadata_json']);
            }
            unset($historyMessage);
            $bytes = 0;
            $bounded = [];
            foreach (array_reverse($history) as $message) {
                $bytes += mb_strlen($message['content']);
                if ($bytes > 60000) break;
                array_unshift($bounded, $message);
            }
            return ['replay' => false, 'message_id' => $messageId, 'revision' => (int) $row['revision'] + 1, 'client_key' => $key, 'context' => $info['context'], 'label' => $info['label'], 'history' => $bounded];
        });
    }
    /** Register every retrieved dependency before sending its content to the provider. */
    public function attachSources($id, $turn, array $sources)
    {
        return $this->transaction(function () use ($id, $turn, $sources) {
            $row = $this->revalidate($this->owned($id));
            $message = $this->query('SELECT state, turn_revision FROM ai_messages WHERE id = ? AND conversation_id = ?', [$turn['message_id'], $id])->fetch(PDO::FETCH_ASSOC);
            if (!$message || $message['state'] !== 'pending' || (int) $message['turn_revision'] !== $turn['revision'] || (int) $row['revision'] !== $turn['revision']) return false;
            foreach ($sources as $source) {
                if (!in_array($source['entity_type'] ?? '', ['request', 'task', 'note', 'app'], true) || empty($source['entity_id']) || !preg_match('/^[a-f0-9]{64}$/D', $source['access_fingerprint'] ?? '')) throw new PrismaAssistantException('Referencia de consulta no válida.');
                $exists = $this->query('SELECT access_fingerprint FROM ai_message_sources WHERE message_id = ? AND entity_type = ? AND entity_id = ?', [$turn['message_id'], $source['entity_type'], (int) $source['entity_id']])->fetchColumn();
                if (!$exists) $this->query('INSERT INTO ai_message_sources (message_id, conversation_id, entity_type, entity_id, access_fingerprint) VALUES (?, ?, ?, ?, ?)', [$turn['message_id'], $id, $source['entity_type'], (int) $source['entity_id'], $source['access_fingerprint']]);
            }
            $checked = $this->revalidate($this->owned($id));
            return (int) $checked['revision'] === $turn['revision'];
        });
    }
    public function finish($id, $turn, $result = null)
    {
        return $this->transaction(function () use ($id, $turn, $result) {
            $row = $this->revalidate($this->owned($id));
            $message = $this->query('SELECT * FROM ai_messages WHERE id = ? AND conversation_id = ?', [$turn['message_id'], $id])->fetch(PDO::FETCH_ASSOC);
            if (!$message || $message['state'] !== 'pending' || (int) $row['revision'] !== $turn['revision']) {
                if ($message && $message['state'] === 'pending' && (int) $message['turn_revision'] === $turn['revision']) $this->query("UPDATE ai_messages SET state = 'failed' WHERE id = ?", [$message['id']]);
                return false; // Redaction or a newer turn won; never revive its response.
            }
            if ($result === null) { $this->query("UPDATE ai_messages SET state = 'failed' WHERE id = ?", [$message['id']]); return true; }
            $this->query("INSERT INTO ai_messages (conversation_id, role, client_key, content, context_json, model, usage_json, metadata_json) VALUES (?, 'assistant', ?, ?, ?, ?, ?, ?)", [$id, 'reply_' . $turn['client_key'], $result['content'], $message['context_json'], $result['model'], $this->json($result['usage']), isset($result['metadata']) ? $this->json($result['metadata']) : null]);
            $replyId = (int) $this->db->lastInsertId();
            $this->query('INSERT INTO ai_message_sources (message_id, conversation_id, entity_type, entity_id, access_fingerprint) SELECT ?, conversation_id, entity_type, entity_id, access_fingerprint FROM ai_message_sources WHERE message_id = ?', [$replyId, $message['id']]);
            $this->query("UPDATE ai_messages SET state = 'complete' WHERE id = ?", [$message['id']]);
            $this->query('UPDATE ai_conversations SET revision = revision + 1, updated_at = CURRENT_TIMESTAMP WHERE id = ?', [$id]);
            return true;
        });
    }
}
