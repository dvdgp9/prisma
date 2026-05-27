-- Migration 013 · Tablas y columnas que existían en producción pero no en schema.sql
-- Detectado durante auditoría 27 May 2026. Idempotente.

-- requests.difficulty
ALTER TABLE requests
    ADD COLUMN IF NOT EXISTS difficulty ENUM('low','medium','high') NULL DEFAULT NULL AFTER status;

CREATE TABLE IF NOT EXISTS request_assignments (
    id INT(11) NOT NULL AUTO_INCREMENT,
    request_id INT(11) NOT NULL,
    user_id INT(11) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_request_user (request_id, user_id),
    KEY idx_request (request_id),
    KEY idx_user (user_id),
    CONSTRAINT fk_ra_request FOREIGN KEY (request_id) REFERENCES requests(id) ON DELETE CASCADE,
    CONSTRAINT fk_ra_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS request_comments (
    id INT(11) NOT NULL AUTO_INCREMENT,
    request_id INT(11) NOT NULL,
    user_id INT(11) NOT NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_request (request_id),
    KEY idx_user (user_id),
    CONSTRAINT fk_rc_request FOREIGN KEY (request_id) REFERENCES requests(id) ON DELETE CASCADE,
    CONSTRAINT fk_rc_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS comment_mentions (
    id INT(11) NOT NULL AUTO_INCREMENT,
    comment_id INT(11) NOT NULL,
    mentioned_user_id INT(11) NOT NULL,
    PRIMARY KEY (id),
    KEY idx_comment (comment_id),
    KEY idx_user (mentioned_user_id),
    CONSTRAINT fk_cm_comment FOREIGN KEY (comment_id) REFERENCES request_comments(id) ON DELETE CASCADE,
    CONSTRAINT fk_cm_user FOREIGN KEY (mentioned_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS request_checklist_items (
    id INT(11) NOT NULL AUTO_INCREMENT,
    request_id INT(11) NOT NULL,
    title VARCHAR(500) NOT NULL,
    is_completed TINYINT(1) NOT NULL DEFAULT 0,
    position INT(11) NOT NULL DEFAULT 0,
    created_by INT(11) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_request (request_id),
    CONSTRAINT fk_rci_request FOREIGN KEY (request_id) REFERENCES requests(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notifications (
    id INT(11) NOT NULL AUTO_INCREMENT,
    user_id INT(11) NOT NULL,
    type VARCHAR(64) NOT NULL,
    request_id INT(11) NULL,
    triggered_by INT(11) NULL,
    message TEXT,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_user (user_id),
    KEY idx_request (request_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- requests.assigned_to (legacy primary-assignment column still used by API joins)
ALTER TABLE requests
    ADD COLUMN IF NOT EXISTS assigned_to INT(11) NULL AFTER created_by,
    ADD KEY idx_assigned_to (assigned_to);
