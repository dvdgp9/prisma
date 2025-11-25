-- Add fields for public requests (external submissions)
ALTER TABLE `requests` 
ADD COLUMN `requester_name` VARCHAR(100) NULL DEFAULT NULL AFTER `created_by`,
ADD COLUMN `requester_email` VARCHAR(100) NULL DEFAULT NULL AFTER `requester_name`,
ADD COLUMN `is_public_request` BOOLEAN DEFAULT 0 AFTER `requester_email`,
ADD COLUMN `approved_by` INT NULL DEFAULT NULL AFTER `is_public_request`,
ADD COLUMN `approved_at` TIMESTAMP NULL DEFAULT NULL AFTER `approved_by`;

-- Add foreign key for approved_by
ALTER TABLE `requests`
ADD CONSTRAINT `fk_requests_approved_by` 
FOREIGN KEY (`approved_by`) REFERENCES `users`(`id`) ON DELETE SET NULL;

-- Add index for filtering public requests
CREATE INDEX `idx_is_public_request` ON `requests` (`is_public_request`);
CREATE INDEX `idx_approved_by` ON `requests` (`approved_by`);

-- Create rate limiting table for public submissions
CREATE TABLE `public_request_limits` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `ip_address` VARCHAR(45) NOT NULL,
    `company_id` INT NOT NULL,
    `request_count` INT DEFAULT 1,
    `window_start` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_ip_company` (`ip_address`, `company_id`),
    KEY `idx_window_start` (`window_start`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
