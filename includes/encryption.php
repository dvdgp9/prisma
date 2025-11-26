<?php
/**
 * Encryption Helper - Encrypt/Decrypt sensitive data
 */

// Encryption key - Store in environment variable in production
define('ENCRYPTION_KEY', 'prisma_smtp_key_2024_secure_32chars!!');

/**
 * Encrypt a string using OpenSSL
 */
function encrypt($data)
{
    if (empty($data))
        return null;

    $key = hash('sha256', ENCRYPTION_KEY);
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));

    $encrypted = openssl_encrypt($data, 'aes-256-cbc', $key, 0, $iv);

    // Concatenate IV and encrypted data, then base64 encode
    return base64_encode($iv . $encrypted);
}

/**
 * Decrypt a string using OpenSSL
 */
function decrypt($data)
{
    if (empty($data))
        return null;

    $key = hash('sha256', ENCRYPTION_KEY);
    $data = base64_decode($data);

    $iv_length = openssl_cipher_iv_length('aes-256-cbc');
    $iv = substr($data, 0, $iv_length);
    $encrypted = substr($data, $iv_length);

    return openssl_decrypt($encrypted, 'aes-256-cbc', $key, 0, $iv);
}
