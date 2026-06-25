<?php
/**
 * File serving API - Streams attachments through a permission check and with
 * the correct original filename.
 *
 * Why this exists: files live under /uploads/ with a random on-disk name
 * (uniqid()_time.ext). Linking to them directly forced the browser to download
 * with that random name, and in the standalone PWA target="_blank" popped the
 * file out into a loose browser window. Serving here lets us send the real
 * filename and an inline disposition so images/PDFs can be previewed in-app.
 *
 * Params:
 *   type     request | task | appfile   (which attachment table)
 *   id       attachment id
 *   download 1 to force "attachment" disposition (download), otherwise inline
 */

require_once __DIR__ . '/../includes/auth.php';

require_login();

$db = getDB();
$user = get_logged_user();

$type = $_GET['type'] ?? '';
$id = $_GET['id'] ?? null;
$forceDownload = !empty($_GET['download']);

/**
 * Send a plain-text HTTP error and stop. We avoid JSON here because the
 * consumer is an <img>/<iframe>/<a>, not an AJAX caller.
 */
function file_error($message, $status = 400) {
    http_response_code($status);
    header('Content-Type: text/plain; charset=UTF-8');
    echo $message;
    exit;
}

if (!$id || !ctype_digit((string)$id)) {
    file_error('Identificador de archivo no válido', 400);
}

// Resolve the attachment row and verify the user may access it.
$file = null;

switch ($type) {
    case 'request':
        // Mirrors api/attachments.php: any logged-in user may view request files.
        $stmt = $db->prepare("SELECT original_filename, file_path, mime_type FROM attachments WHERE id = ?");
        $stmt->execute([$id]);
        $file = $stmt->fetch(PDO::FETCH_ASSOC);
        break;

    case 'task':
        // Access if owner, a shared task in the user's company, or shared directly with the user.
        $stmt = $db->prepare("
            SELECT ta.original_filename, ta.file_path, ta.mime_type
            FROM task_attachments ta
            JOIN tasks t ON ta.task_id = t.id
            WHERE ta.id = :aid
              AND (
                    t.user_id = :uid
                 OR (t.is_shared = 1 AND t.company_id = :cid)
                 OR EXISTS (SELECT 1 FROM task_shares ts WHERE ts.task_id = t.id AND ts.user_id = :uid2)
              )
        ");
        $stmt->execute([':aid' => $id, ':uid' => $user['id'], ':cid' => $user['company_id'], ':uid2' => $user['id']]);
        $file = $stmt->fetch(PDO::FETCH_ASSOC);
        break;

    case 'appfile':
        $stmt = $db->prepare("SELECT original_filename, file_path, mime_type, app_id FROM app_files WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && can_access_app($row['app_id'])) {
            $file = $row;
        }
        break;

    default:
        file_error('Tipo de archivo no válido', 400);
}

if (!$file) {
    file_error('Archivo no encontrado o sin permiso', 404);
}

// Resolve and confine the path inside /uploads/ to prevent traversal.
$uploadsRoot = realpath(__DIR__ . '/../uploads');
$absolutePath = realpath(__DIR__ . '/../' . $file['file_path']);

if ($uploadsRoot === false || $absolutePath === false || strpos($absolutePath, $uploadsRoot . DIRECTORY_SEPARATOR) !== 0) {
    file_error('Archivo no disponible', 404);
}

if (!is_file($absolutePath)) {
    file_error('Archivo no disponible', 404);
}

// Build a safe filename for the Content-Disposition header.
$originalName = $file['original_filename'] ?: basename($absolutePath);
$asciiName = preg_replace('/[\r\n"\\\\]+/', '_', $originalName);
$disposition = $forceDownload ? 'attachment' : 'inline';

$mime = $file['mime_type'] ?: 'application/octet-stream';

// Clean any buffered output so the binary stream is not corrupted.
while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: ' . $mime);
header('Content-Disposition: ' . $disposition . '; filename="' . $asciiName . '"; filename*=UTF-8\'\'' . rawurlencode($originalName));
header('Content-Length: ' . filesize($absolutePath));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=0, must-revalidate');

readfile($absolutePath);
exit;
