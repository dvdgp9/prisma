<?php
/**
 * Upload API - File upload handler for request attachments
 */

require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

// All operations require login
require_login();

$db = getDB();
$user = get_logged_user();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_response('Method not allowed', 405);
}

// Check if request_id is provided
if (empty($_POST['request_id'])) {
    error_response('Request ID is required');
}

$request_id = $_POST['request_id'];

// Verify request exists
$stmt = $db->prepare("SELECT id FROM requests WHERE id = ?");
$stmt->execute([$request_id]);
if (!$stmt->fetch()) {
    error_response('Request not found', 404);
}

// Check if file was uploaded
if (empty($_FILES['file'])) {
    error_response('No file uploaded');
}

$file = $_FILES['file'];

// Check for upload errors
if ($file['error'] !== UPLOAD_ERR_OK) {
    error_response('File upload failed with error code: ' . $file['error']);
}

// Validate file size (15MB max)
$max_size = 15 * 1024 * 1024; // 15MB
if ($file['size'] > $max_size) {
    error_response('File size exceeds 15MB limit');
}

// Validate file type
$allowed_types = [
    'image/jpeg',
    'image/png',
    'image/gif',
    'image/webp',
    'application/pdf',
    'text/plain',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'application/zip'
];

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mime_type, $allowed_types)) {
    error_response('File type not allowed. Allowed: images, PDF, text, Word, Excel, ZIP');
}

// Create uploads directory if it doesn't exist
$upload_dir = __DIR__ . '/../uploads/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Generate unique filename
$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$unique_filename = uniqid() . '_' . time() . '.' . $extension;
$file_path = $upload_dir . $unique_filename;

// Move uploaded file
if (!move_uploaded_file($file['tmp_name'], $file_path)) {
    error_response('Failed to save uploaded file', 500);
}

// Save to database
try {
    $stmt = $db->prepare("
        INSERT INTO attachments (request_id, filename, original_filename, file_path, file_size, mime_type, uploaded_by)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $request_id,
        $unique_filename,
        $file['name'],
        'uploads/' . $unique_filename,
        $file['size'],
        $mime_type,
        $user['id']
    ]);

    $attachment_id = $db->lastInsertId();

    success_response([
        'id' => $attachment_id,
        'filename' => $unique_filename,
        'original_filename' => $file['name'],
        'file_size' => $file['size'],
        'mime_type' => $mime_type
    ], 'File uploaded successfully');

} catch (Exception $e) {
    // Clean up uploaded file if database insert fails
    if (file_exists($file_path)) {
        unlink($file_path);
    }
    error_response('Failed to save file metadata: ' . $e->getMessage(), 500);
}
