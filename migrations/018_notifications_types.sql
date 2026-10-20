-- Migration 018 · Notificaciones: más tipos de aviso y avisos que no son de una mejora
-- En producción `type` es ENUM('mention','comment','assignment') y `request_id` es NOT NULL,
-- pero la app ya crea avisos 'completion', 'status_change', 'note_share' y 'task_assignment',
-- y los de notas y tareas no tienen mejora. Con el ENUM, MySQL los guardaba con el tipo
-- vacío ('') y, los de notas, con request_id = 0.
-- No borra nada. Se puede ejecutar más de una vez sin problema.

-- 1. Tipos libres y avisos sin mejora.
ALTER TABLE notifications
    MODIFY COLUMN type VARCHAR(64) NOT NULL,
    MODIFY COLUMN request_id INT(11) NULL;

-- 2. Reparar los avisos que se guardaron mal (se reconocen por su nota o por su mensaje).
UPDATE notifications SET request_id = NULL WHERE request_id = 0;

UPDATE notifications SET type = 'note_share'
WHERE type = '' AND note_id IS NOT NULL;

UPDATE notifications SET type = 'completion'
WHERE type = '' AND message LIKE '% ha completado la mejora';

UPDATE notifications SET type = 'status_change'
WHERE type = '' AND (
    message LIKE '% ha movido la mejora a pendiente'
    OR message LIKE '% ha puesto la mejora en curso'
    OR message LIKE '% ha descartado la mejora'
);
