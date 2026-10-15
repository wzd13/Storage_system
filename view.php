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

$extension = strtolower(pathinfo($file['original_name'], PATHINFO_EXTENSION));
$previewable = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'pdf', 'txt'];

if (!in_array($extension, $previewable, true)) {
    redirect('/download.php?id=' . $fileId);
}

$mime = $file['mime_type'] ?: 'application/octet-stream';
if (in_array($extension, ['png', 'jpg', 'jpeg', 'gif', 'webp'], true)) {
    header('Content-Type: image/' . ($extension === 'jpg' ? 'jpeg' : $extension));
    readfile($file['storage_path']);
    exit;
}

if ($extension === 'pdf') {
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . basename($file['original_name']) . '"');
    readfile($file['storage_path']);
    exit;
}

header('Content-Type: text/plain; charset=UTF-8');
header('Content-Disposition: inline; filename="' . basename($file['original_name']) . '"');
readfile($file['storage_path']);
exit;
