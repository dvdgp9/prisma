-- Migration 016 · Fecha objetivo de las mejoras (plan "Mi trabajo")
-- Fecha opcional (sin hora) para planificar una mejora; la misma para todos sus responsables.
-- Las mejoras existentes quedan sin fecha (NULL). Solo añade: no modifica ni borra datos.
-- Sin IF NOT EXISTS para que funcione igual en MySQL y MariaDB: ejecutar una sola vez.

ALTER TABLE requests
    ADD COLUMN due_date DATE NULL,
    ADD INDEX idx_due_date (due_date);
