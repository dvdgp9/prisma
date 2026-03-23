-- Migration: Add "programador" role to users.role enum
-- Run this migration to allow assigning the Programador role from admin panel

ALTER TABLE users
MODIFY COLUMN role ENUM('superadmin', 'admin', 'programador', 'user') NOT NULL DEFAULT 'user';
