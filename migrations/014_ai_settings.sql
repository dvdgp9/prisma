-- AI Inbox: tabla genérica de ajustes de aplicación (clave/valor)
-- La API key de OpenRouter se guarda cifrada (AES-256-CBC vía includes/encryption.php)

CREATE TABLE IF NOT EXISTS `app_settings` (
    `setting_key` VARCHAR(100) NOT NULL PRIMARY KEY,
    `setting_value` TEXT NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Modelo por defecto (la key se guarda desde el panel de admin, nunca en SQL plano)
INSERT INTO `app_settings` (`setting_key`, `setting_value`)
VALUES ('ai_model', 'google/gemini-3.1-flash-lite')
ON DUPLICATE KEY UPDATE `setting_key` = `setting_key`;
