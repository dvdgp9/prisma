-- IA1: base del asistente. Aditiva; no activa interfaz ni altera datos existentes.
-- Ejecutar en la BD de Prisma; las claves foráneas requieren InnoDB.
-- LONGTEXT contiene JSON validado por servidor (compatible con MySQL 5.7/MariaDB).

CREATE TABLE IF NOT EXISTS ai_conversations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    company_id INT NULL,
    title VARCHAR(200) NOT NULL DEFAULT 'Nueva conversación',
    context_json LONGTEXT NOT NULL,
    revision INT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ai_conversations_owner (user_id, updated_at, id),
    CONSTRAINT fk_ai_conversation_owner FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ai_messages (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    conversation_id BIGINT UNSIGNED NOT NULL,
    role VARCHAR(16) NOT NULL,
    client_key VARCHAR(64) NOT NULL,
    content LONGTEXT NOT NULL,
    context_json LONGTEXT NOT NULL,
    model VARCHAR(150) NULL,
    usage_json TEXT NULL,
    turn_revision INT UNSIGNED NULL,
    state VARCHAR(24) NOT NULL DEFAULT 'complete',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_ai_message_retry (conversation_id, client_key),
    UNIQUE KEY uq_ai_message_scope (id, conversation_id),
    CONSTRAINT fk_ai_message_conversation FOREIGN KEY (conversation_id) REFERENCES ai_conversations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Una dependencia por fuente consultada. Las respuestas heredan las fuentes del
-- historial que se envía al proveedor. Nunca guardar aquí copias de sus contenidos.
CREATE TABLE IF NOT EXISTS ai_message_sources (
    message_id BIGINT UNSIGNED NOT NULL,
    conversation_id BIGINT UNSIGNED NOT NULL,
    entity_type VARCHAR(24) NOT NULL,
    entity_id BIGINT UNSIGNED NOT NULL,
    access_fingerprint CHAR(64) NOT NULL,
    PRIMARY KEY (message_id, entity_type, entity_id),
    INDEX idx_ai_source_entity (entity_type, entity_id),
    CONSTRAINT fk_ai_source_message FOREIGN KEY (message_id, conversation_id) REFERENCES ai_messages(id, conversation_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Una fila por revisión de tarjeta. Reintentar usa la misma execution_key;
-- resultado + mutación de dominio se confirman en la misma transacción en IA4.
CREATE TABLE IF NOT EXISTS ai_proposals (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    conversation_id BIGINT UNSIGNED NOT NULL,
    message_id BIGINT UNSIGNED NOT NULL,
    card_key CHAR(32) NOT NULL,
    revision INT UNSIGNED NOT NULL,
    action_type VARCHAR(40) NOT NULL,
    payload_json LONGTEXT NOT NULL,
    expected_json LONGTEXT NULL,
    execution_key CHAR(64) NOT NULL,
    state VARCHAR(24) NOT NULL DEFAULT 'pending',
    result_json LONGTEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    applied_at DATETIME NULL,
    UNIQUE KEY uq_ai_proposal_revision (conversation_id, card_key, revision),
    UNIQUE KEY uq_ai_proposal_execution (execution_key),
    INDEX idx_ai_proposal_message (message_id, conversation_id),
    CONSTRAINT fk_ai_proposal_message FOREIGN KEY (message_id, conversation_id) REFERENCES ai_messages(id, conversation_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
