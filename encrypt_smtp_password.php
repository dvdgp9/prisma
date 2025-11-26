<?php
/**
 * Script to encrypt SMTP password for EBone company
 * Run this once to set the encrypted password
 */

require_once __DIR__ . '/includes/encryption.php';
require_once __DIR__ . '/config/database.php';

$password = '851pM9h^h';
$encrypted = encrypt($password);

echo "Encrypted password: " . $encrypted . "\n\n";
echo "Run this SQL:\n\n";
echo "UPDATE companies SET smtp_password = '{$encrypted}' WHERE LOWER(name) = 'ebone';\n\n";

// Auto-update if you want
$db = getDB();
$stmt = $db->prepare("UPDATE companies SET smtp_password = ? WHERE LOWER(name) = 'ebone'");
$stmt->execute([$encrypted]);

echo "✅ Password encrypted and updated in database!\n";
