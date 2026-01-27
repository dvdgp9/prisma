-- Migration: Add app_resources table for links and notes
-- Run this migration to add support for links and notes in app files section

CREATE TABLE IF NOT EXISTS app_resources (
    id INT AUTO_INCREMENT PRIMARY KEY,
    app_id INT NOT NULL,
    type ENUM('link', 'note') NOT NULL,
    title VARCHAR(255) NOT NULL,
    content TEXT,  -- URL for links, text content for notes
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (app_id) REFERENCES apps(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_app (app_id),
    INDEX idx_type (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
