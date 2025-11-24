-- Schema Update: User-App Permissions
-- Run this SQL in phpMyAdmin to add the new permissions table

-- Table to manage which users can see which apps
CREATE TABLE IF NOT EXISTS user_app_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    app_id INT NOT NULL,
    can_view BOOLEAN DEFAULT TRUE,
    can_create BOOLEAN DEFAULT TRUE,
    can_edit BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (app_id) REFERENCES apps(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_app (user_id, app_id),
    INDEX idx_user (user_id),
    INDEX idx_app (app_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Grant default permissions to existing user (admin) for all apps
INSERT INTO user_app_permissions (user_id, app_id, can_view, can_create, can_edit)
SELECT 1, id, TRUE, TRUE, TRUE FROM apps;

-- Note: Superadmins bypass permissions and can see/edit everything
-- Admins can see all apps in their company
-- Regular users only see apps they have permissions for
