-- Migration 017 · Tareas: "visto" y aviso en la campana
-- Quien asigna una tarea ve si cada persona ya la ha visto (✓ / ✓✓), y quien la recibe
-- tiene un aviso "X te ha asignado una tarea" en la campana.
-- Solo añade: no borra datos. Las asignaciones que ya existían se dan por vistas,
-- para que no salgan todas como "Nueva".
-- Sin IF NOT EXISTS para que funcione igual en MySQL y MariaDB: ejecutar una sola vez.

-- Cuándo vio la tarea cada persona (NULL = todavía no la ha visto).
ALTER TABLE task_shares
    ADD COLUMN seen_at DATETIME NULL DEFAULT NULL;

UPDATE task_shares SET seen_at = NOW() WHERE seen_at IS NULL;

-- Aviso "te han asignado una tarea": las notificaciones apuntan a la tarea.
-- Sin AFTER: no depende de que exista notifications.note_id (de la 015).
ALTER TABLE notifications
    ADD COLUMN task_id INT NULL,
    ADD INDEX idx_task (task_id);
