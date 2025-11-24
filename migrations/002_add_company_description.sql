-- Migración: Agregar campo description a companies
-- Ejecutar en phpMyAdmin

ALTER TABLE `companies` 
ADD COLUMN `description` TEXT NULL AFTER `name`;
