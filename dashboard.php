<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';

requireLogin();

$user = currentUser();
$userId = (int) $user['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $folderId = isset($_POST['folder_id']) ? (int) $_POST['folder_id'] : null;

    if ($action === 'new_folder') {
        $folderName = trim((string) ($_POST['folder_name'] ?? ''));
        if ($folderName !== '') {
            $stmt = appConnection()->prepare('INSERT INTO folders (user_id, parent_id, name) VALUES (:user_id, :parent_id, :name)');
            $stmt->execute([
                ':user_id' => $userId,
                ':parent_id' => $folderId ?: null,
                ':name' => $folderName,
            ]);
            logActivity($userId, 'folder_created', 'Created folder: ' . $folderName);
            setFlash('success', 'Folder created successfully.');
        }
    }

    if ($action === 'upload') {
        if (!empty($_FILES['upload_file']['name'])) {
            $file = $_FILES['upload_file'];
            $originalName = $file['name'];
            $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            $allowed = array_map('trim', explode(',', strtolower(settingValue('allowed_extensions', 'pdf,jpg,png,zip'))));
            $maxUploadSize = (int) settingValue('max_upload_size', '104857600');

            if ($file['error'] !== UPLOAD_ERR_OK) {
                setFlash('danger', 'Upload failed. Please try again.');
            } elseif ($file['size'] > $maxUploadSize) {
                setFlash('danger', 'File exceeds the maximum upload size.');
            } elseif (!in_array($extension, $allowed, true)) {
                setFlash('danger', 'This file type is not allowed.');
            } else {
                ensureUserStorageDirectory($userId);
                $targetRoot = STORAGE_ROOT . $userId . DIRECTORY_SEPARATOR;
                $folderRoot = $targetRoot . ($folderId ?: 'root') . DIRECTORY_SEPARATOR;
                if (!is_dir($folderRoot)) {
                    mkdir($folderRoot, 0775, true);
                }

                $safeName = safeFileName($originalName);
                $fileName = time() . '-' . $safeName;
                $destination = $folderRoot . $fileName;

                if (move_uploaded_file($file['tmp_name'], $destination)) {
                    $pdo = appConnection();
                    $stmt = $pdo->prepare('INSERT INTO files (user_id, folder_id, name, original_name, mime_type, size_bytes, storage_path, version_count) VALUES (:user_id, :folder_id, :name, :original_name, :mime_type, :size_bytes, :storage_path, 1)');
                    $stmt->execute([
                        ':user_id' => $userId,
                        ':folder_id' => $folderId ?: null,
                        ':name' => $fileName,
                        ':original_name' => $originalName,
                        ':mime_type' => mime_content_type($destination) ?: 'application/octet-stream',
                        ':size_bytes' => (int) $file['size'],
                        ':storage_path' => $destination,
                    ]);

                    $fileId = (int) $pdo->lastInsertId();
                    $versionStmt = $pdo->prepare('INSERT INTO file_versions (file_id, version_name, storage_path) VALUES (:file_id, :version_name, :storage_path)');
                    $versionStmt->execute([
                        ':file_id' => $fileId,
                        ':version_name' => $fileName,
                        ':storage_path' => $destination,
                    ]);

                    logActivity($userId, 'file_uploaded', 'Uploaded file: ' . $originalName);
                    setFlash('success', 'File uploaded successfully.');
                }
            }
        }
    }

    if ($action === 'rename') {
        $itemType = $_POST['item_type'] ?? 'file';
        $itemId = (int) ($_POST['item_id'] ?? 0);
        $newName = trim((string) ($_POST['new_name'] ?? ''));

        if ($newName !== '' && $itemId > 0) {
            if ($itemType === 'folder') {
                $stmt = appConnection()->prepare('UPDATE folders SET name = :name WHERE id = :id AND user_id = :user_id');
                $stmt->execute([':name' => $newName, ':id' => $itemId, ':user_id' => $userId]);
                logActivity($userId, 'folder_renamed', 'Renamed folder to ' . $newName);
            } else {
                $stmt = appConnection()->prepare('UPDATE files SET original_name = :name WHERE id = :id AND user_id = :user_id');
                $stmt->execute([':name' => $newName, ':id' => $itemId, ':user_id' => $userId]);
                logActivity($userId, 'file_renamed', 'Renamed file to ' . $newName);
            }
            setFlash('success', 'Item renamed successfully.');
        }
    }

    if ($action === 'move') {
        $itemType = $_POST['item_type'] ?? 'file';
        $itemId = (int) ($_POST['item_id'] ?? 0);
        $newParent = isset($_POST['new_parent']) ? (int) $_POST['new_parent'] : null;

        if ($itemType === 'folder') {
            $stmt = appConnection()->prepare('UPDATE folders SET parent_id = :parent_id WHERE id = :id AND user_id = :user_id');
            $stmt->execute([':parent_id' => $newParent ?: null, ':id' => $itemId, ':user_id' => $userId]);
        } else {
            $stmt = appConnection()->prepare('UPDATE files SET folder_id = :folder_id WHERE id = :id AND user_id = :user_id');
            $stmt->execute([':folder_id' => $newParent ?: null, ':id' => $itemId, ':user_id' => $userId]);
        }

        setFlash('success', 'Item moved successfully.');
    }

    if ($action === 'delete') {
        $itemType = $_POST['item_type'] ?? 'file';
        $itemId = (int) ($_POST['item_id'] ?? 0);

        if ($itemType === 'folder') {
            $stmt = appConnection()->prepare('UPDATE folders SET is_deleted = 1, deleted_at = NOW() WHERE id = :id AND user_id = :user_id');
            $stmt->execute([':id' => $itemId, ':user_id' => $userId]);
        } else {
            $stmt = appConnection()->prepare('UPDATE files SET is_deleted = 1, deleted_at = NOW() WHERE id = :id AND user_id = :user_id');
            $stmt->execute([':id' => $itemId, ':user_id' => $userId]);
        }

        setFlash('success', 'Item sent to trash.');
    }

    if ($action === 'restore') {
        $itemType = $_POST['item_type'] ?? 'file';
        $itemId = (int) ($_POST['item_id'] ?? 0);

        if ($itemType === 'folder') {
            $stmt = appConnection()->prepare('UPDATE folders SET is_deleted = 0, deleted_at = NULL WHERE id = :id AND user_id = :user_id');
            $stmt->execute([':id' => $itemId, ':user_id' => $userId]);
        } else {
            $stmt = appConnection()->prepare('UPDATE files SET is_deleted = 0, deleted_at = NULL WHERE id = :id AND user_id = :user_id');
            $stmt->execute([':id' => $itemId, ':user_id' => $userId]);
        }

        setFlash('success', 'Item restored successfully.');
    }

    $redirectTarget = appUrl('/dashboard.php');
    if (!empty($_POST['return_folder'])) {
        $redirectTarget .= '?folder_id=' . (int) $_POST['return_folder'];
    }

    redirect($redirectTarget);
}

$currentFolderId = isset($_GET['folder_id']) ? (int) $_GET['folder_id'] : null;
$parentFolderId = null;
if ($currentFolderId) {
    $parentStmt = appConnection()->prepare('SELECT parent_id FROM folders WHERE id = :id AND user_id = :user_id LIMIT 1');
    $parentStmt->execute([':id' => $currentFolderId, ':user_id' => $userId]);
    $parentFolderId = $parentStmt->fetchColumn();
    $parentFolderId = $parentFolderId !== false ? (int) $parentFolderId : null;
}
$breadcrumbs = getFolderBreadcrumbs($currentFolderId, $userId);

$folderStmt = appConnection()->prepare('SELECT * FROM folders WHERE user_id = :user_id AND parent_id ' . ($currentFolderId ? '= :folder_id' : 'IS NULL') . ' AND is_deleted = 0 ORDER BY name ASC');
if ($currentFolderId) {
    $folderStmt->execute([':user_id' => $userId, ':folder_id' => $currentFolderId]);
} else {
    $folderStmt->execute([':user_id' => $userId]);
}
$folders = $folderStmt->fetchAll();

$fileStmt = appConnection()->prepare('SELECT * FROM files WHERE user_id = :user_id AND folder_id ' . ($currentFolderId ? '= :folder_id' : 'IS NULL') . ' AND is_deleted = 0 ORDER BY created_at DESC');
if ($currentFolderId) {
    $fileStmt->execute([':user_id' => $userId, ':folder_id' => $currentFolderId]);
} else {
    $fileStmt->execute([':user_id' => $userId]);
}
$files = $fileStmt->fetchAll();

$activityStmt = appConnection()->prepare('SELECT action, details, created_at FROM activities WHERE user_id = :user_id ORDER BY created_at DESC LIMIT 6');
$activityStmt->execute([':user_id' => $userId]);
$recentActivity = $activityStmt->fetchAll();

$storageUsed = userStorageUsed($userId);

$folderOptions = appConnection()->prepare('SELECT id, name FROM folders WHERE user_id = :user_id AND is_deleted = 0 ORDER BY name ASC');
$folderOptions->execute([':user_id' => $userId]);
$folderChoices = $folderOptions->fetchAll();

$userQuota = appConnection()->prepare('SELECT storage_quota FROM users WHERE id = :id LIMIT 1');
$userQuota->execute([':id' => $userId]);
$quota = (int) $userQuota->fetchColumn();
$currentUsage = $storageUsed;
$usedPercent = $quota > 0 ? min(100, round(($currentUsage / $quota) * 100)) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | NimbusVault</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= h(appUrl('/assets/app.css')) ?>">
</head>
<body>
    <div class="app-shell">
        <aside class="sidebar">
            <div class="brand-box">
                <div class="brand-mark"><i class="fa-solid fa-cloud"></i></div>
                <div>
                    <div class="brand-name">NimbusVault</div>
                    <small>Workspace</small>
                </div>
            </div>

            <nav class="nav-menu">
                <a class="nav-item active" href="<?= h(appUrl('/dashboard.php')) ?>"><i class="fa-solid fa-table-columns"></i> Dashboard</a>
                <a class="nav-item" href="<?= h(appUrl('/dashboard.php?folder_id=')) ?>"><i class="fa-solid fa-folder-open"></i> My files</a>
                <?php if (($user['role'] ?? '') === 'admin'): ?>
                    <a class="nav-item" href="<?= h(appUrl('/admin.php')) ?>"><i class="fa-solid fa-shield-halved"></i> Admin</a>
                <?php endif; ?>
                <a class="nav-item" href="<?= h(appUrl('/logout.php')) ?>"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
            </nav>

            <div class="sidebar-card mt-4">
                <div class="card-title">Storage</div>
                <div class="progress mb-2">
                    <div class="progress-bar" role="progressbar" style="width: <?= $usedPercent ?>%"></div>
                </div>
                <div class="d-flex justify-content-between small-text">
                    <span><?= formatBytes($currentUsage) ?></span>
                    <span><?= formatBytes($quota) ?></span>
                </div>
            </div>
        </aside>

        <main class="main-panel">
            <header class="topbar">
                <div>
                    <p class="mb-0 muted-label">Overview</p>
                    <h1 class="page-title">Welcome, <?= h($user['first_name']) ?>!</h1>
                </div>
                <div class="topbar-actions">
                    <button class="btn btn-outline-secondary"><i class="fa-solid fa-bell"></i></button>
                    <div class="user-pill"><i class="fa-solid fa-user"></i> <?= h($user['role']) ?></div>
                </div>
            </header>

            <?php $flash = getFlash(); if ($flash): ?>
                <div class="alert alert-<?= h($flash['type']) ?> mt-3" role="alert"><?= h($flash['message']) ?></div>
            <?php endif; ?>

            <section class="stats-grid mt-4">
                <div class="stat-card primary">
                    <div class="stat-icon"><i class="fa-solid fa-folder"></i></div>
                    <div>
                        <small>Total folders</small>
                        <h3><?= appConnection()->query('SELECT COUNT(*) FROM folders WHERE user_id = ' . (int) $userId . ' AND is_deleted = 0')->fetchColumn() ?></h3>
                    </div>
                </div>
                <div class="stat-card success">
                    <div class="stat-icon"><i class="fa-solid fa-file-lines"></i></div>
                    <div>
                        <small>Total files</small>
                        <h3><?= appConnection()->query('SELECT COUNT(*) FROM files WHERE user_id = ' . (int) $userId . ' AND is_deleted = 0')->fetchColumn() ?></h3>
                    </div>
                </div>
                <div class="stat-card info">
                    <div class="stat-icon"><i class="fa-solid fa-share-nodes"></i></div>
                    <div>
                        <small>Shared items</small>
                        <h3><?= appConnection()->query('SELECT COUNT(*) FROM shares WHERE owner_user_id = ' . (int) $userId)->fetchColumn() ?></h3>
                    </div>
                </div>
            </section>

            <section class="toolbar mt-4">
                <div class="d-flex flex-wrap gap-3">
                    <form method="post" enctype="multipart/form-data" class="upload-form">
                        <input type="hidden" name="action" value="upload">
                        <input type="hidden" name="folder_id" value="<?= $currentFolderId ?: '' ?>">
                        <input type="hidden" name="return_folder" value="<?= $currentFolderId ?: '' ?>">
                        <input type="file" name="upload_file" class="form-control" required>
                        <button class="btn btn-primary" type="submit"><i class="fa-solid fa-upload"></i> Upload file</button>
                    </form>

                    <form method="post" class="d-flex align-items-center gap-2">
                        <input type="hidden" name="action" value="new_folder">
                        <input type="hidden" name="folder_id" value="<?= $currentFolderId ?: '' ?>">
                        <input type="hidden" name="return_folder" value="<?= $currentFolderId ?: '' ?>">
                        <input type="text" name="folder_name" class="form-control" placeholder="New folder name" required>
                        <button class="btn btn-outline-primary" type="submit"><i class="fa-solid fa-folder-plus"></i> Create</button>
                    </form>
                </div>
            </section>

            <section class="content-panel mt-4">
                <div class="panel-header">
                    <div>
                        <h2 class="section-title">Files</h2>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb mb-0">
                                <li class="breadcrumb-item"><a href="<?= h(appUrl('/dashboard.php')) ?>">Home</a></li>
                                <?php foreach ($breadcrumbs as $crumb): ?>
                                    <li class="breadcrumb-item"><a href="<?= h(appUrl('/dashboard.php?folder_id=' . (int) $crumb['id'])) ?>"><?= h($crumb['name']) ?></a></li>
                                <?php endforeach; ?>
                            </ol>
                        </nav>
                    </div>
                    <?php if ($currentFolderId && $parentFolderId !== null): ?>
                        <a class="btn btn-secondary btn-sm" href="<?= h(appUrl('/dashboard.php?folder_id=' . (int) $parentFolderId)) ?>">Back</a>
                    <?php endif; ?>
                </div>

                <div class="grid-view mt-4">
                    <?php foreach ($folders as $folder): ?>
                        <div class="item-card folder-card">
                            <div class="item-icon folder-bg"><i class="fa-solid fa-folder"></i></div>
                            <div class="item-body">
                                <h5><a href="<?= h(appUrl('/dashboard.php?folder_id=' . (int) $folder['id'])) ?>"><?= h($folder['name']) ?></a></h5>
                                <small class="text-muted">Folder</small>
                            </div>
                            <div class="item-actions">
                                <form method="post" class="inline-form">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="item_type" value="folder">
                                    <input type="hidden" name="item_id" value="<?= (int) $folder['id'] ?>">
                                    <input type="hidden" name="return_folder" value="<?= $currentFolderId ?: '' ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete"><i class="fa-solid fa-trash"></i></button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <?php foreach ($files as $file): ?>
                        <div class="item-card file-card">
                            <div class="item-icon file-bg"><i class="fa-solid fa-file"></i></div>
                            <div class="item-body">
                                <h5><?= h($file['original_name']) ?></h5>
                                <small class="text-muted"><?= formatBytes((int) $file['size_bytes']) ?></small>
                            </div>
                            <div class="item-actions">
                                <a class="btn btn-sm btn-outline-primary" href="<?= h(appUrl('/view.php?id=' . (int) $file['id'])) ?>" target="_blank"><i class="fa-solid fa-eye"></i></a>
                                <a class="btn btn-sm btn-outline-secondary" href="<?= h(appUrl('/download.php?id=' . (int) $file['id'])) ?>" target="_blank"><i class="fa-solid fa-download"></i></a>
                                <form method="post" class="inline-form">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="item_type" value="file">
                                    <input type="hidden" name="item_id" value="<?= (int) $file['id'] ?>">
                                    <input type="hidden" name="return_folder" value="<?= $currentFolderId ?: '' ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete"><i class="fa-solid fa-trash"></i></button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if (empty($folders) && empty($files)): ?>
                    <div class="empty-state mt-4">
                        <i class="fa-solid fa-folder-open"></i>
                        <h4>No items in this folder yet</h4>
                        <p>Upload a file or create a new folder to get started.</p>
                    </div>
                <?php endif; ?>
            </section>

            <section class="row mt-4">
                <div class="col-lg-7">
                    <div class="panel-box">
                        <div class="panel-header"><h2 class="section-title">Recent activity</h2></div>
                        <ul class="activity-list">
                            <?php foreach ($recentActivity as $entry): ?>
                                <li>
                                    <span class="activity-pill"><i class="fa-solid fa-circle-dot"></i></span>
                                    <div>
                                        <strong><?= h($entry['action']) ?></strong>
                                        <p><?= h($entry['details']) ?></p>
                                    </div>
                                    <small><?= h($entry['created_at']) ?></small>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>

                <div class="col-lg-5">
                    <div class="panel-box">
                        <div class="panel-header"><h2 class="section-title">Quick actions</h2></div>
                        <div class="quick-actions">
                            <form method="post" class="quick-action-form">
                                <input type="hidden" name="action" value="move">
                                <input type="hidden" name="return_folder" value="<?= $currentFolderId ?: '' ?>">
                                <label>Move selected file</label>
                                <select name="new_parent" class="form-select">
                                    <option value="">Root</option>
                                    <?php foreach ($folderChoices as $choice): ?>
                                        <option value="<?= (int) $choice['id'] ?>"><?= h($choice['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <select name="item_id" class="form-select mt-2">
                                    <?php foreach ($files as $file): ?>
                                        <option value="<?= (int) $file['id'] ?>"><?= h($file['original_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="hidden" name="item_type" value="file">
                                <button class="btn btn-outline-primary mt-3" type="submit">Move</button>
                            </form>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= h(appUrl('/assets/app.js')) ?>"></script>
</body>
</html>
