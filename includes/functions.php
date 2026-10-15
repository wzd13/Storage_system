<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function appUrl(string $path = ''): string
{
    $path = '/' . ltrim($path, '/');
    $baseUrl = rtrim(APP_BASE_URL, '/');

    if ($path === $baseUrl || str_starts_with($path, $baseUrl . '/')) {
        return $path;
    }

    return $baseUrl . $path;
}

function redirect(string $path): void
{
    $location = $path;
    if (!str_starts_with($location, 'http')) {
        $location = appUrl($location);
    }

    header('Location: ' . $location);
    exit;
}

function setFlash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array
{
    if (!isset($_SESSION['flash'])) {
        return null;
    }

    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);

    return $flash;
}

function currentUser(): ?array
{
    return $_SESSION['user'] ?? null;
}

function currentUserId(): ?int
{
    $user = currentUser();
    return $user['id'] ?? null;
}

function requireLogin(): void
{
    if (!currentUser()) {
        redirect('/login.php');
    }
}

function requireRole(string $role): void
{
    requireLogin();

    if ((currentUser()['role'] ?? '') !== $role) {
        setFlash('danger', 'You do not have permission to access this page.');
        redirect('/dashboard.php');
    }
}

function formatBytes(int|float $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $value = max($bytes, 0);
    $unitIndex = 0;

    while ($value >= 1024 && $unitIndex < count($units) - 1) {
        $value /= 1024;
        ++$unitIndex;
    }

    if ($unitIndex === 0) {
        return number_format((float) $value, 0, '.', ',') . ' ' . $units[$unitIndex];
    }

    return number_format((float) $value, 2, '.', ',') . ' ' . $units[$unitIndex];
}

function settingValue(string $key, string $default = ''): string
{
    $row = appConnection()->prepare('SELECT setting_value FROM settings WHERE setting_key = :key LIMIT 1');
    $row->execute([':key' => $key]);
    $value = $row->fetchColumn();

    return $value !== false ? (string) $value : $default;
}

function updateSetting(string $key, string $value): void
{
    $stmt = appConnection()->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (:key, :value) ON DUPLICATE KEY UPDATE setting_value = :value');
    $stmt->execute([':key' => $key, ':value' => $value]);
}

function logActivity(?int $userId, string $action, string $details): void
{
    $stmt = appConnection()->prepare('INSERT INTO activities (user_id, action, details) VALUES (:user_id, :action, :details)');
    $stmt->execute([
        ':user_id' => $userId,
        ':action' => $action,
        ':details' => $details,
    ]);
}

function userStorageUsed(int $userId): int
{
    $stmt = appConnection()->prepare('SELECT COALESCE(SUM(size_bytes), 0) FROM files WHERE user_id = :user_id AND is_deleted = 0');
    $stmt->execute([':user_id' => $userId]);

    return (int) $stmt->fetchColumn();
}

function getFolderBreadcrumbs(?int $folderId, int $userId): array
{
    $crumbs = [];

    while ($folderId) {
        $stmt = appConnection()->prepare('SELECT id, name, parent_id FROM folders WHERE id = :id AND user_id = :user_id LIMIT 1');
        $stmt->execute([':id' => $folderId, ':user_id' => $userId]);
        $folder = $stmt->fetch();

        if (!$folder) {
            break;
        }

        $crumbs[] = ['id' => (int) $folder['id'], 'name' => $folder['name']];
        $folderId = (int) $folder['parent_id'];
    }

    return array_reverse($crumbs);
}

function ensureUserStorageDirectory(int $userId): void
{
    $path = STORAGE_ROOT . $userId;
    if (!is_dir($path)) {
        mkdir($path, 0775, true);
    }
}

function safeFileName(string $fileName): string
{
    $base = preg_replace('/[^A-Za-z0-9._-]+/', '-', $fileName);
    return trim((string) $base, '-_.');
}
