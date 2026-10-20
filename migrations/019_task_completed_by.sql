-- Migration 019 · Tareas: quién completó cada una
-- Para el panel de avance del equipo ("Hecho": qué se ha completado, quién y cuándo) y para
-- el aviso "X ha completado una tarea". Una tarea compartida la puede completar cualquiera
-- de sus personas, así que hace falta guardarlo.
-- Solo añade: no borra datos. Ejecutar una sola vez (sin IF NOT EXISTS, igual en MySQL y MariaDB).

ALTER TABLE tasks
    ADD COLUMN completed_by INT NULL;

-- Las tareas privadas ya completadas solo pudo completarlas quien las creó.
-- Las compartidas completadas antes de esta migración se quedan sin persona (no se sabe quién fue).
-- updated_at = updated_at: que no cambie la fecha de última modificación.
UPDATE tasks t
SET t.completed_by = t.user_id, t.updated_at = t.updated_at
WHERE t.is_completed = 1
  AND t.completed_by IS NULL
  AND t.is_shared = 0
  AND NOT EXISTS (SELECT 1 FROM task_shares s WHERE s.task_id = t.id);
