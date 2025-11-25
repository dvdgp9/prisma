-- Add completed_at timestamp for tracking when tasks are finished
ALTER TABLE `requests` 
ADD COLUMN `completed_at` TIMESTAMP NULL DEFAULT NULL AFTER `created_at`;

-- Add index for faster filtering/reporting by completion date
CREATE INDEX `idx_completed_at` ON `requests` (`completed_at`);
