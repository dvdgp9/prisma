-- Migration: Scheduled Releases (Release Planner para Superadmin)
-- Fecha: 22 Enero 2026
-- Descripción: Tabla para programar anuncios de funcionalidades completadas

CREATE TABLE IF NOT EXISTS scheduled_releases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    internal_notes TEXT,
    link VARCHAR(500),
    completed_at DATE NOT NULL,
    announce_at DATE NOT NULL,
    status ENUM('draft','scheduled','announced') DEFAULT 'scheduled',
    app_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (app_id) REFERENCES apps(id) ON DELETE SET NULL,
    INDEX idx_announce_at (announce_at),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
