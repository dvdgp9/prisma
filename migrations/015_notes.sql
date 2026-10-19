-- Migration 015 · Notas compartidas
-- Notas de equipo en Markdown: el autor las comparte con todo el equipo (is_shared)
-- o con personas concretas (note_shares), igual que las tareas. App opcional.
-- Solo añade: no modifica ni borra datos existentes.

CREATE TABLE IF NOT EXISTS notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    user_id INT NOT NULL,                 -- autor: único que borra la nota y cambia con quién se comparte
    app_id INT NULL,                      -- app relacionada (opcional)
    title VARCHAR(255) NOT NULL,
    content MEDIUMTEXT NULL,              -- Markdown
    is_shared TINYINT(1) NOT NULL DEFAULT 0,  -- 1 = visible para todo el equipo de la empresa
    is_pinned TINYINT(1) NOT NULL DEFAULT 0,
    updated_by INT NULL,                  -- última persona que la editó (para avisar de cambios ajenos)
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (app_id) REFERENCES apps(id) ON DELETE SET NULL,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_company_updated (company_id, updated_at),
    INDEX idx_user (user_id),
    INDEX idx_app (app_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS note_shares (
    id INT AUTO_INCREMENT PRIMARY KEY,
    note_id INT NOT NULL,
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_note_user (note_id, user_id),
    INDEX idx_user (user_id),
    FOREIGN KEY (note_id) REFERENCES notes(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Aviso "te han compartido una nota": las notificaciones apuntan a la nota.
-- Sin IF NOT EXISTS para que funcione igual en MySQL y MariaDB: ejecutar una sola vez.
ALTER TABLE notifications
    ADD COLUMN note_id INT NULL AFTER request_id,
    ADD INDEX idx_note (note_id);
