-- Migration: Add pin support to app_resources (notes)
-- Adds is_pinned flag and pinned_at timestamp so notes can be pinned to the top.

ALTER TABLE app_resources
    ADD COLUMN is_pinned TINYINT(1) NOT NULL DEFAULT 0 AFTER content,
    ADD COLUMN pinned_at TIMESTAMP NULL DEFAULT NULL AFTER is_pinned;

-- Update schema.sql accordingly when applying in production.
