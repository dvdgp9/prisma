<?php
/**
 * Migration: Add due_date field to tasks table
 * Run this file once to add the new field
 */

require_once __DIR__ . '/config/database.php';

try {
    $db = getDB();
    
    echo "Adding due_date field to tasks table...\n";
    
    $db->exec("
        ALTER TABLE tasks 
        ADD COLUMN due_date DATE NULL AFTER description,
        ADD INDEX idx_due_date (due_date)
    ");
    
    echo "✅ Migration completed successfully!\n";
    echo "Tasks table now has due_date field.\n";
    
} catch (Exception $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
