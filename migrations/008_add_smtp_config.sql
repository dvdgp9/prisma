-- Add SMTP configuration fields to companies table
ALTER TABLE `companies` 
ADD COLUMN `smtp_host` VARCHAR(255) NULL AFTER `name`,
ADD COLUMN `smtp_port` INT DEFAULT 587 AFTER `smtp_host`,
ADD COLUMN `smtp_username` VARCHAR(255) NULL AFTER `smtp_port`,
ADD COLUMN `smtp_password` VARCHAR(255) NULL AFTER `smtp_username`,
ADD COLUMN `smtp_from_email` VARCHAR(255) NULL AFTER `smtp_password`,
ADD COLUMN `smtp_from_name` VARCHAR(255) DEFAULT 'Prisma' AFTER `smtp_from_email`,
ADD COLUMN `smtp_encryption` ENUM('tls', 'ssl') DEFAULT 'tls' AFTER `smtp_from_name`,
ADD COLUMN `smtp_enabled` BOOLEAN DEFAULT 0 AFTER `smtp_encryption`;

-- Update EBone company with SMTP credentials
UPDATE `companies` 
SET 
    smtp_host = 'grupoebone.es',
    smtp_port = 465,
    smtp_username = 'prisma@grupoebone.es',
    smtp_password = AES_ENCRYPT('851pM9h^h', 'prisma_smtp_key_2024'),
    smtp_from_email = 'prisma@grupoebone.es',
    smtp_from_name = 'Prisma - Grupo EBone',
    smtp_encryption = 'ssl',
    smtp_enabled = 1
WHERE LOWER(name) = 'ebone';
