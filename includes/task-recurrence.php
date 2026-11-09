<?php
/** Completion-based recurrence. Persistence helpers run inside the caller's transaction. */
function normalize_task_recurrence(array $fields, array $current = [])
{
    $unit = $fields['recurrence_unit'] ?? $current['recurrence_unit'] ?? null;
    if (array_key_exists('recurrence_unit', $fields) && $fields['recurrence_unit'] === null) {
        return ['recurrence_unit' => null, 'recurrence_interval' => null];
    }
    if ($unit === null) {
        if (!empty($fields['recurrence_interval'])) {
            throw new InvalidArgumentException('Elige la unidad de repetición');
        }
        return ['recurrence_unit' => null, 'recurrence_interval' => null];
    }
    if (!in_array($unit, ['day', 'week', 'month', 'year'], true)) {
        throw new InvalidArgumentException('Unidad de repetición no válida');
    }
    $value = array_key_exists('recurrence_interval', $fields) ? $fields['recurrence_interval'] : ($current['recurrence_interval'] ?? 1);
    $interval = filter_var($value,
        FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 365]]);
    if ($interval === false || is_bool($value) || is_float($value)) {
        throw new InvalidArgumentException('La repetición debe ser un número entero entre 1 y 365');
    }
    return ['recurrence_unit' => $unit, 'recurrence_interval' => $interval];
}

function task_recurrence_due_date($unit, $interval, DateTimeImmutable $completed)
{
    if ($unit === 'day' || $unit === 'week') {
        return $completed->modify('+' . ($interval * ($unit === 'week' ? 7 : 1)) . ' days')->format('Y-m-d');
    }
    // Clamp month ends (31 January + 1 month = 28/29 February), including leap years.
    $months = $interval * ($unit === 'year' ? 12 : 1);
    $target = $completed->modify('first day of this month')->modify("+{$months} months");
    return $target->setDate((int) $target->format('Y'), (int) $target->format('m'),
        min((int) $completed->format('d'), (int) $target->format('t')))->format('Y-m-d');
}

// A generated occurrence can be removed by Undo only while its content is untouched.
function task_recurrence_snapshot($db, array $task)
{
    $fields = [];
    foreach (['user_id', 'company_id', 'app_id', 'title', 'description', 'due_date',
        'is_shared', 'recurrence_unit', 'recurrence_interval'] as $key) {
        $fields[$key] = (string) ($task[$key] ?? '');
    }
    $stmt = $db->prepare('SELECT user_id FROM task_shares WHERE task_id = ? ORDER BY user_id');
    $stmt->execute([$task['id']]);
    $fields['shares'] = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    return hash('sha256', json_encode($fields));
}

function create_next_task_occurrence($db, array $task, DateTimeImmutable $completed)
{
    if (empty($task['recurrence_unit'])) return null;
    $rule = normalize_task_recurrence($task);
    $dueDate = task_recurrence_due_date($rule['recurrence_unit'], $rule['recurrence_interval'], $completed);
    $stmt = $db->prepare('INSERT INTO tasks
        (user_id, company_id, app_id, title, description, due_date, is_shared,
         recurrence_unit, recurrence_interval, recurrence_parent_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([$task['user_id'], $task['company_id'], $task['app_id'], $task['title'],
        $task['description'], $dueDate, $task['is_shared'], $rule['recurrence_unit'],
        $rule['recurrence_interval'], $task['id']]);
    $nextId = (int) $db->lastInsertId();
    $stmt = $db->prepare('INSERT INTO task_shares (task_id, user_id)
        SELECT ?, user_id FROM task_shares WHERE task_id = ?');
    $stmt->execute([$nextId, $task['id']]);
    $next = array_merge($task, $rule, ['id' => $nextId, 'due_date' => $dueDate]);
    $stmt = $db->prepare('UPDATE tasks SET recurrence_snapshot = ? WHERE id = ?');
    $stmt->execute([task_recurrence_snapshot($db, $next), $nextId]);
    return $nextId;
}

function undo_next_task_occurrence($db, $taskId)
{
    $lock = $db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
    $stmt = $db->prepare('SELECT * FROM tasks WHERE recurrence_parent_id = ?' . $lock);
    $stmt->execute([$taskId]);
    $next = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$next) return;
    $stmt = $db->prepare('SELECT COUNT(*) FROM task_attachments WHERE task_id = ?');
    $stmt->execute([$next['id']]);
    if ($next['is_completed'] || (int) $stmt->fetchColumn() > 0 ||
        !hash_equals((string) $next['recurrence_snapshot'], task_recurrence_snapshot($db, $next))) {
        throw new InvalidArgumentException('No se puede deshacer: la siguiente repetición ya se ha modificado o completado');
    }
    $stmt = $db->prepare('DELETE FROM notifications WHERE task_id = ?');
    $stmt->execute([$next['id']]);
    $stmt = $db->prepare('DELETE FROM task_shares WHERE task_id = ?');
    $stmt->execute([$next['id']]);
    $stmt = $db->prepare('DELETE FROM tasks WHERE id = ?');
    $stmt->execute([$next['id']]);
}
