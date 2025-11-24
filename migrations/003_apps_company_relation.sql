-- Migración: Relacionar apps con empresas
-- Ejecutar en phpMyAdmin

-- 1. Agregar columna company_id a apps
ALTER TABLE `apps` 
ADD COLUMN `company_id` INT NULL AFTER `id`,
ADD INDEX `idx_company` (`company_id`);

-- 2. Agregar foreign key
ALTER TABLE `apps`
ADD CONSTRAINT `fk_app_company` 
FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) 
ON DELETE SET NULL;

-- 3. Asignar apps existentes a la empresa por defecto (ID=1)
UPDATE `apps` SET `company_id` = 1 WHERE `company_id` IS NULL;

-- Nota: Ahora las apps pertenecen a empresas
-- Los usuarios solo verán apps de su empresa
-- user_app_permissions es para control granular adicional
