<?php
/** Read-only, bounded retrieval. SQL and links are authored here, never by the model. */
require_once __DIR__ . '/ai-assistant.php';

function assistant_query_schema()
{
    $properties = [
        'mode' => ['type' => 'string', 'enum' => ['chat', 'search', 'detail', 'clarify']],
        'resource' => ['type' => 'string', 'enum' => ['work', 'all', 'request', 'task', 'note']],
        'id' => ['type' => ['integer', 'null'], 'minimum' => 1],
        'query' => ['type' => 'string', 'maxLength' => 150],
        'app_name' => ['type' => 'string', 'maxLength' => 100],
        'person' => ['type' => 'string', 'maxLength' => 100],
        'status' => ['type' => 'string', 'enum' => ['open', 'all', 'pending', 'in_progress', 'completed', 'discarded']],
        'date_field' => ['type' => 'string', 'enum' => ['due', 'created', 'updated', 'completed']],
        'from' => ['type' => ['string', 'null']],
        'to' => ['type' => ['string', 'null']],
        'due' => ['type' => 'string', 'enum' => ['all', 'overdue', 'today', 'week']],
        'sort' => ['type' => 'string', 'enum' => ['recent', 'oldest', 'due', 'priority']],
        'offset' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 200],
        'question' => ['type' => 'string', 'maxLength' => 500]
    ];
    return ['type' => 'object', 'properties' => $properties, 'required' => array_keys($properties), 'additionalProperties' => false];
}

function assistant_answer_schema()
{
    return ['type' => 'object', 'properties' => [
        'text' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 12000],
        'source_keys' => ['type' => 'array', 'maxItems' => 12, 'items' => ['type' => 'string', 'maxLength' => 40]]
    ], 'required' => ['text', 'source_keys'], 'additionalProperties' => false];
}

class PrismaAssistantQueries
{
    private $db;
    private $user;
    private $appIds;
    private $resolve;
    private $refs = [];
    private $partial = false;
    private $budget = 28000;
    public function __construct($db, array $user, array $appIds, callable $resolve)
    {
        $this->db = $db; $this->user = $user; $this->appIds = array_values(array_unique(array_map('intval', $appIds))); $this->resolve = $resolve;
    }
    private function query($sql, $args = [])
    {
        $stmt = $this->db->prepare($sql);
        foreach (array_values($args) as $i => $value) $stmt->bindValue($i + 1, $value, $value === null ? PDO::PARAM_NULL : (is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR));
        $stmt->execute(); return $stmt;
    }
    private function like($text) { return '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $text) . '%'; }
    private function clip($text, $max)
    {
        $text = (string) ($text ?? '');
        if (mb_strlen($text) > $max) { $this->partial = true; return mb_substr($text, 0, $max) . ' […]'; }
        return $text;
    }
    private function source($type, $row)
    {
        $access = ($this->resolve)($type, (int) $row['id']);
        if (!$access) return false;
        $key = $type . ':' . $row['id'];
        $url = $type === 'request' ? '/index.php?open_request=' : ($type === 'task' ? '/tasks.php?task=' : '/notes.php?id=');
        $this->refs[$key] = ['key' => $key, 'entity_type' => $type, 'entity_id' => (int) $row['id'], 'access_fingerprint' => $access['access_fingerprint'], 'title' => $this->clip($row['title'], 200), 'url' => $url . (int) $row['id']];
        return $key;
    }
    private function normalize($plan, $context)
    {
        $defaults = ['mode' => 'search', 'resource' => 'work', 'id' => null, 'query' => '', 'app_name' => '', 'person' => '', 'status' => 'open', 'date_field' => 'due', 'from' => null, 'to' => null, 'due' => 'all', 'sort' => 'due', 'offset' => 0, 'question' => ''];
        if (array_diff(array_keys($plan), array_keys($defaults))) throw new PrismaAssistantException('La búsqueda contiene campos no permitidos.');
        $plan = array_merge($defaults, $plan);
        foreach (['query' => 150, 'app_name' => 100, 'person' => 100, 'question' => 500] as $field => $length) {
            if (!is_string($plan[$field]) || mb_strlen($plan[$field]) > $length) throw new PrismaAssistantException('La búsqueda no es válida.');
            $plan[$field] = trim($plan[$field]);
        }
        foreach (['mode', 'resource', 'status', 'date_field', 'due', 'sort'] as $field) if (!in_array($plan[$field], assistant_query_schema()['properties'][$field]['enum'], true)) throw new PrismaAssistantException('La búsqueda no es válida.');
        if ($plan['id'] !== null && filter_var($plan['id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) throw new PrismaAssistantException('El registro no es válido.');
        $plan['offset'] = max(0, min(200, (int) $plan['offset']));
        $filters = (array) $context['filters'];
        foreach (['from', 'to'] as $field) {
            $plan[$field] = $filters[$field] ?? $plan[$field];
            if ($plan[$field] !== null) {
                $value = $plan[$field];
                if (!is_string($value) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D', $value, $m) || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])) throw new PrismaAssistantException('La fecha de búsqueda no es válida.');
            }
        }
        if ($plan['from'] && $plan['to'] && $plan['from'] > $plan['to']) throw new PrismaAssistantException('El rango de búsqueda no es válido.');
        $plan['status'] = $filters['status'] ?? $plan['status'];
        $plan['person_id'] = $filters['person'] ?? null;
        if ($plan['mode'] === 'detail' && !in_array($plan['resource'], ['request', 'task', 'note'], true)) throw new PrismaAssistantException('Selecciona qué registro quieres consultar.');
        if ($plan['mode'] === 'detail' && $plan['id'] === null) {
            if ($plan['resource'] === $context['scope']) $plan['id'] = $context['entity_id'];
            else throw new PrismaAssistantException('Falta el número del registro.');
        }
        return $plan;
    }
    private function focusApp($context)
    {
        if ($context['scope'] === 'app') return (int) $context['entity_id'];
        if (in_array($context['scope'], ['request', 'task', 'note'], true)) {
            // Context has already been resolved. Recheck before querying its app relation.
            if (!(($this->resolve)($context['scope'], $context['entity_id']))) throw new PrismaAssistantException('Ya no tienes acceso al contexto.', 403);
            $table = ['request' => 'requests', 'task' => 'tasks', 'note' => 'notes'][$context['scope']];
            $id = $this->query('SELECT app_id FROM ' . $table . ' WHERE id = ?', [$context['entity_id']])->fetchColumn();
            return $id ? (int) $id : -1;
        }
        return null;
    }
    private function requestActivity()
    {
        $comments = '(SELECT MAX(activity.created_at) FROM request_comments activity WHERE activity.request_id = r.id)';
        return "CASE WHEN COALESCE($comments, r.updated_at) > r.updated_at THEN $comments ELSE r.updated_at END";
    }
    private function where($type, $plan, $context, $today)
    {
        $alias = ['request' => 'r', 'task' => 't', 'note' => 'n'][$type];
        $where = []; $args = []; $uid = (int) $this->user['id']; $company = (int) $this->user['company_id'];
        if ($type === 'request') {
            if (!$this->appIds) $where[] = '1=0';
            else { $where[] = 'r.app_id IN (' . implode(',', array_fill(0, count($this->appIds), '?')) . ')'; $args = array_merge($args, $this->appIds); }
            if ($context['scope'] === 'mine') { $where[] = 'EXISTS (SELECT 1 FROM request_assignments mine WHERE mine.request_id = r.id AND mine.user_id = ?)'; $args[] = $uid; }
        } else {
            $shares = $type === 'task' ? 'task_shares' : 'note_shares';
            $fk = $type . '_id';
            $direct = $plan['mode'] === 'detail' && $context['scope'] === $type && (int) $context['entity_id'] === (int) $plan['id'];
            if (!$direct) { $where[] = "$alias.company_id = ?"; $args[] = $company; }
            $where[] = "($alias.user_id = ? OR $alias.is_shared = 1 OR EXISTS (SELECT 1 FROM $shares visible WHERE visible.$fk = $alias.id AND visible.user_id = ?))";
            array_push($args, $uid, $uid);
            if ($type === 'task' && $context['scope'] === 'mine') {
                $where[] = '(t.is_shared = 0 AND (EXISTS (SELECT 1 FROM task_shares mine WHERE mine.task_id = t.id AND mine.user_id = ?) OR (t.user_id = ? AND NOT EXISTS (SELECT 1 FROM task_shares delegated WHERE delegated.task_id = t.id))))';
                array_push($args, $uid, $uid);
            }
        }
        $focus = $this->focusApp($context);
        if ($focus === -1) {
            $where[] = $type === $context['scope'] ? "$alias.id = ?" : '1=0';
            if ($type === $context['scope']) $args[] = $context['entity_id'];
        } elseif ($focus !== null) { $where[] = "$alias.app_id = ?"; $args[] = $focus; }
        if ($plan['mode'] === 'detail') { $where[] = "$alias.id = ?"; $args[] = (int) $plan['id']; }
        else {
            if ($plan['query'] !== '') {
                $column = $type === 'note' ? 'content' : 'description';
                $condition = "($alias.title LIKE ? ESCAPE '!' OR $alias.$column LIKE ? ESCAPE '!'";
                array_push($args, $this->like($plan['query']), $this->like($plan['query']));
                if ($type === 'request') { $condition .= " OR EXISTS (SELECT 1 FROM request_comments search_comment WHERE search_comment.request_id = r.id AND search_comment.content LIKE ? ESCAPE '!')"; $args[] = $this->like($plan['query']); }
                $where[] = $condition . ')';
            }
            if ($plan['app_name'] !== '') { $where[] = "a.name LIKE ? ESCAPE '!'"; $args[] = $this->like($plan['app_name']); }
            if ($type === 'request') {
                if ($plan['status'] === 'open') $where[] = "r.status IN ('pending','in_progress')";
                elseif ($plan['status'] !== 'all') { $where[] = 'r.status = ?'; $args[] = $plan['status']; }
            } elseif ($type === 'task') {
                if ($plan['status'] === 'discarded') $where[] = '1=0';
                elseif ($plan['status'] !== 'all') { $where[] = 't.is_completed = ?'; $args[] = $plan['status'] === 'completed' ? 1 : 0; }
            }
            if ($type !== 'note' && $plan['due'] !== 'all') {
                $date = new DateTimeImmutable($today);
                if ($plan['due'] === 'overdue') { $where[] = "$alias.due_date < ?"; $args[] = $today; }
                else { $where[] = "$alias.due_date >= ? AND $alias.due_date <= ?"; array_push($args, $today, $plan['due'] === 'today' ? $today : $date->modify('+6 days')->format('Y-m-d')); }
            }
            $dateField = ['due' => 'due_date', 'created' => 'created_at', 'updated' => 'updated_at', 'completed' => 'completed_at'][$plan['date_field']];
            if ($type === 'note' && in_array($plan['date_field'], ['due', 'completed'], true)) $dateField = 'updated_at';
            $dateColumn = $type === 'request' && $plan['date_field'] === 'updated' ? $this->requestActivity() : "$alias.$dateField";
            foreach (['from' => '>=', 'to' => '<='] as $bound => $operator) if ($plan[$bound] !== null) { $where[] = "DATE($dateColumn) $operator ?"; $args[] = $plan[$bound]; }
        }
        $personId = $plan['person_id']; $person = $plan['person'];
        if ($personId !== null || $person !== '') {
            if ($type === 'request') {
                if ($personId === 'none') $where[] = 'NOT EXISTS (SELECT 1 FROM request_assignments nobody WHERE nobody.request_id = r.id)';
                elseif ($personId !== null) { $where[] = 'EXISTS (SELECT 1 FROM request_assignments person WHERE person.request_id = r.id AND person.user_id = ?)'; $args[] = (int) $personId; }
                else { $where[] = "EXISTS (SELECT 1 FROM request_assignments person JOIN users who ON who.id = person.user_id WHERE person.request_id = r.id AND (who.full_name LIKE ? ESCAPE '!' OR who.username LIKE ? ESCAPE '!'))"; array_push($args, $this->like($person), $this->like($person)); }
            } elseif ($type === 'task') {
                if ($personId === 'none') $where[] = '1=0';
                else {
                    // Team tasks count for no named person, matching workPeople().
                    $where[] = 't.is_shared = 0';
                    $predicate = $personId !== null ? 'who.id = ?' : "(who.full_name LIKE ? ESCAPE '!' OR who.username LIKE ? ESCAPE '!')";
                    $where[] = "EXISTS (SELECT 1 FROM users who WHERE ($predicate) AND (EXISTS (SELECT 1 FROM task_shares person WHERE person.task_id = t.id AND person.user_id = who.id) OR (t.user_id = who.id AND NOT EXISTS (SELECT 1 FROM task_shares any_person WHERE any_person.task_id = t.id))))";
                    if ($personId !== null) $args[] = (int) $personId; else array_push($args, $this->like($person), $this->like($person));
                }
            } else {
                if ($personId === 'none') $where[] = '1=0';
                elseif ($personId !== null) { $where[] = 'n.user_id = ?'; $args[] = (int) $personId; }
                else { $where[] = "EXISTS (SELECT 1 FROM users who WHERE who.id = n.user_id AND (who.full_name LIKE ? ESCAPE '!' OR who.username LIKE ? ESCAPE '!'))"; array_push($args, $this->like($person), $this->like($person)); }
            }
        }
        return [implode(' AND ', $where), $args];
    }
    private function record($type, $row, $detail)
    {
        $record = ['key' => $type . ':' . $row['id'], 'type' => $type, 'id' => (int) $row['id'], 'title' => $this->clip($row['title'], 200), 'app' => $row['app_name'], 'app_id' => $row['app_id'] === null ? null : (int) $row['app_id'], 'created_at' => $row['created_at'], 'updated_at' => $row['updated_at']];
        $record['last_activity_at'] = $row['last_activity_at'] ?? $row['updated_at'];
        if ($type === 'note') {
            $record['content'] = $this->clip($row['content'], $detail ? 12000 : 600);
            $record['author'] = $this->query("SELECT COALESCE(NULLIF(full_name, ''), username) FROM users WHERE id = ?", [$row['user_id']])->fetchColumn() ?: 'Usuario';
        } else {
            $record['description'] = $this->clip($row['description'], $detail ? 3000 : 600);
            $record['due_date'] = $row['due_date']; $record['completed_at'] = $row['completed_at'];
            $record['status'] = $type === 'request' ? $row['status'] : ($row['is_completed'] ? 'completed' : 'pending');
            if ($type === 'request') {
                $record['priority'] = $row['priority'];
                $record['assignees'] = $this->query("SELECT u.id, COALESCE(NULLIF(u.full_name, ''), u.username) AS name FROM request_assignments ra JOIN users u ON u.id = ra.user_id WHERE ra.request_id = ? ORDER BY ra.id LIMIT 50", [$row['id']])->fetchAll(PDO::FETCH_ASSOC);
                if ($detail) {
                    $comments = $this->query("SELECT c.id, c.content, c.created_at, COALESCE(NULLIF(u.full_name, ''), u.username) AS author FROM request_comments c JOIN users u ON u.id = c.user_id WHERE c.request_id = ? ORDER BY c.id DESC LIMIT 21", [$row['id']])->fetchAll(PDO::FETCH_ASSOC);
                    if (count($comments) > 20) $this->partial = true;
                    $record['comments'] = [];
                    foreach (array_reverse(array_slice($comments, 0, 20)) as $comment) { $comment['content'] = $this->clip($comment['content'], 400); $comment['author'] = $this->clip($comment['author'], 80); $record['comments'][] = $comment; }
                    $checklist = $this->query('SELECT title, is_completed FROM request_checklist_items WHERE request_id = ? ORDER BY position, id LIMIT 31', [$row['id']])->fetchAll(PDO::FETCH_ASSOC);
                    if (count($checklist) > 30) $this->partial = true;
                    $record['checklist'] = array_slice($checklist, 0, 30);
                    foreach ($record['checklist'] as &$step) $step['title'] = $this->clip($step['title'], 160);
                    unset($step);
                }
            } else {
                $record['for'] = $row['is_shared'] ? 'Equipo' : $this->query("SELECT u.id, COALESCE(NULLIF(u.full_name, ''), u.username) AS name FROM task_shares ts JOIN users u ON u.id = ts.user_id WHERE ts.task_id = ? ORDER BY u.id LIMIT 50", [$row['id']])->fetchAll(PDO::FETCH_ASSOC);
                $record['creator'] = $this->query("SELECT COALESCE(NULLIF(full_name, ''), username) FROM users WHERE id = ?", [$row['user_id']])->fetchColumn() ?: 'Usuario';
                if (!$record['for']) $record['for'] = [['id' => (int) $row['user_id'], 'name' => $record['creator']]];
            }
        }
        return $record;
    }
    public function run(array $plan, array $context, $today)
    {
        $context = assistant_context($context, $this->user['role']);
        $sourceType = in_array($context['scope'], ['mine', 'team'], true) ? 'scope_' . $context['scope'] : $context['scope'];
        if (!(($this->resolve)($sourceType, $context['entity_id'] ?? (int) $this->user['id']))) throw new PrismaAssistantException('Ya no tienes acceso al contexto.', 403);
        $plan = $this->normalize($plan, $context);
        $this->refs = []; $this->partial = false; $this->budget = 28000;
        $records = []; $coverage = [];
        $types = in_array($plan['resource'], ['all', 'work'], true) ? ($plan['resource'] === 'work' ? ['request', 'task'] : ['request', 'task', 'note']) : [$plan['resource']];
        if (in_array($plan['mode'], ['chat', 'clarify'], true)) $types = [];
        foreach ($types as $type) {
            [$where, $args] = $this->where($type, $plan, $context, $today);
            $table = ['request' => 'requests', 'task' => 'tasks', 'note' => 'notes'][$type];
            $alias = ['request' => 'r', 'task' => 't', 'note' => 'n'][$type];
            // Explicit fields exclude requester contact data and every credential column.
            $columns = ['request' => 'r.id,r.app_id,r.title,r.description,r.status,r.priority,r.due_date,r.created_at,r.updated_at,r.completed_at', 'task' => 't.id,t.app_id,t.title,t.description,t.user_id,t.is_shared,t.due_date,t.is_completed,t.created_at,t.updated_at,t.completed_at', 'note' => 'n.id,n.app_id,n.title,n.content,n.user_id,n.created_at,n.updated_at'][$type];
            $offset = $plan['mode'] === 'detail' ? 0 : $plan['offset'];
            if ($type === 'request') $columns .= ', ' . $this->requestActivity() . ' AS last_activity_at';
            $activityColumn = $type === 'request' ? 'last_activity_at' : "$alias.updated_at";
            $order = "$activityColumn DESC, $alias.id DESC";
            if ($plan['sort'] === 'oldest') $order = "$activityColumn ASC, $alias.id ASC";
            if ($type !== 'note' && $plan['sort'] === 'due') $order = "CASE WHEN $alias.due_date IS NULL THEN 1 ELSE 0 END, $alias.due_date ASC, $order";
            if ($type === 'request' && $plan['sort'] === 'priority') $order = "CASE r.priority WHEN 'critical' THEN 0 WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END, $order";
            $rows = $this->query("SELECT $columns, a.name AS app_name FROM $table $alias LEFT JOIN apps a ON a.id = $alias.app_id WHERE $where ORDER BY $order LIMIT 21 OFFSET " . (int) $offset, $args)->fetchAll(PDO::FETCH_ASSOC);
            $reviewed = 0; $more = count($rows) > 20;
            foreach ($rows as $index => $row) {
                // Even the extra row that establishes has_more is an access dependency.
                if (!$this->source($type, $row)) { $this->partial = true; continue; }
                if ($index >= 20) continue;
                $record = $this->record($type, $row, $plan['mode'] === 'detail');
                $length = mb_strlen(json_encode($record, JSON_UNESCAPED_UNICODE));
                if ($length > $this->budget) { $this->partial = true; $more = true; break; }
                $this->budget -= $length; $records[] = $record; $reviewed++;
            }
            $coverage[] = ['type' => $type, 'reviewed' => $reviewed, 'has_more' => $more, 'offset' => $offset, 'next_offset' => $more && $offset < 200 ? $offset + 20 : null];
            if ($more || $offset > 0) $this->partial = true;
        }
        return ['records' => $records, 'sources' => array_values($this->refs), 'coverage' => $coverage, 'partial' => $this->partial, 'query' => $plan, 'scope' => $context['scope'], 'as_of' => $today];
    }
}
