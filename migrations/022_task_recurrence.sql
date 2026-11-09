-- Run once before deploying completion-based task recurrence.
-- Existing tasks remain non-recurring. A source can generate at most one successor.
ALTER TABLE tasks
    ADD COLUMN recurrence_unit ENUM('day', 'week', 'month', 'year') NULL,
    ADD COLUMN recurrence_interval SMALLINT UNSIGNED NULL,
    ADD COLUMN recurrence_parent_id INT NULL,
    ADD COLUMN recurrence_snapshot CHAR(64) NULL,
    ADD UNIQUE KEY uniq_task_recurrence_parent (recurrence_parent_id),
    ADD CONSTRAINT fk_task_recurrence_parent FOREIGN KEY (recurrence_parent_id)
        REFERENCES tasks(id) ON DELETE SET NULL;
