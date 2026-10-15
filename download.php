<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';

requireLogin();

$fileId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$stmt = appConnection()->prepare('SELECT * FROM files WHERE id = :id AND user_id = :user_id LIMIT 1');
$stmt->execute([':id' => $fileId, ':user_id' => currentUser()['id']]);
$file = $stmt->fetch();

if (!$file || !is_file($file['storage_path'])) {
    setFlash('danger', 'The requested file could not be found.');
    redirect('/dashboard.php');
}

header('Content-Type: ' . ($file['mime_type'] ?: 'application/octet-stream'));
header('Content-Length: ' . filesize($file['storage_path']));
header('Content-Disposition: attachment; filename="' . basename($file['original_name']) . '"');
readfile($file['storage_path']);
exit;
