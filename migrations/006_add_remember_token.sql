-- Add remember me token columns to users table
ALTER TABLE `users` 
ADD COLUMN `remember_token` VARCHAR(64) NULL DEFAULT NULL AFTER `password`,
ADD COLUMN `remember_token_expiry` TIMESTAMP NULL DEFAULT NULL AFTER `remember_token`;

-- Add index for token lookup
CREATE INDEX `idx_remember_token` ON `users` (`remember_token`);
