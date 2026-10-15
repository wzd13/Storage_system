<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';

requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_user') {
        $firstName = trim((string) ($_POST['first_name'] ?? ''));
        $lastName = trim((string) ($_POST['last_name'] ?? ''));
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');
        $quota = (int) ($_POST['quota'] ?? 0);

        if ($firstName !== '' && $lastName !== '' && $email !== '' && strlen($password) >= 6) {
            $stmt = appConnection()->prepare('INSERT INTO users (first_name, last_name, email, password_hash, role, status, storage_quota) VALUES (:first_name, :last_name, :email, :password_hash, :role, :status, :quota)');
            $stmt->execute([
                ':first_name' => $firstName,
                ':last_name' => $lastName,
                ':email' => $email,
                ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
                ':role' => 'user',
                ':status' => 'active',
                ':quota' => $quota > 0 ? $quota : 5368709120,
            ]);
            logActivity((int) currentUser()['id'], 'user_created', 'Created user account for ' . $email);
            setFlash('success', 'User account created successfully.');
        } else {
            setFlash('danger', 'Please fill in all user fields with a valid password.');
        }
    }

    if ($action === 'save_settings') {
        $extensions = trim((string) ($_POST['allowed_extensions'] ?? ''));
        $maxUploadSize = (int) ($_POST['max_upload_size'] ?? 104857600);
        $trashRetention = (int) ($_POST['trash_retention_days'] ?? 30);

        updateSetting('allowed_extensions', $extensions);
        updateSetting('max_upload_size', (string) $maxUploadSize);
        updateSetting('trash_retention_days', (string) $trashRetention);
        logActivity((int) currentUser()['id'], 'settings_updated', 'Updated system settings.');
        setFlash('success', 'System settings updated successfully.');
    }

    redirect('/admin.php');
}

$totalUsers = appConnection()->query('SELECT COUNT(*) FROM users')->fetchColumn();
$activeUsers = appConnection()->query('SELECT COUNT(*) FROM users WHERE status = "active"')->fetchColumn();
$totalFiles = appConnection()->query('SELECT COUNT(*) FROM files WHERE is_deleted = 0')->fetchColumn();
$totalSize = appConnection()->query('SELECT COALESCE(SUM(size_bytes), 0) FROM files WHERE is_deleted = 0')->fetchColumn();
$users = appConnection()->query('SELECT * FROM users ORDER BY created_at DESC')->fetchAll();
$settings = [
    'allowed_extensions' => settingValue('allowed_extensions', 'pdf,jpg,png,zip'),
    'max_upload_size' => settingValue('max_upload_size', '104857600'),
    'trash_retention_days' => settingValue('trash_retention_days', '30'),
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel | NimbusVault</title>
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
                <small>Administration</small>
            </div>
        </div>

        <nav class="nav-menu">
            <a class="nav-item" href="<?= h(appUrl('/dashboard.php')) ?>"><i class="fa-solid fa-table-columns"></i> Dashboard</a>
            <a class="nav-item active" href="<?= h(appUrl('/admin.php')) ?>"><i class="fa-solid fa-shield-halved"></i> Admin</a>
            <a class="nav-item" href="<?= h(appUrl('/logout.php')) ?>"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
        </nav>
    </aside>

    <main class="main-panel">
        <header class="topbar">
            <div>
                <p class="mb-0 muted-label">Control center</p>
                <h1 class="page-title">System management</h1>
            </div>
        </header>

        <?php $flash = getFlash(); if ($flash): ?>
            <div class="alert alert-<?= h($flash['type']) ?> mt-3" role="alert"><?= h($flash['message']) ?></div>
        <?php endif; ?>

        <section class="stats-grid mt-4">
            <div class="stat-card primary">
                <div class="stat-icon"><i class="fa-solid fa-users"></i></div>
                <div>
                    <small>Total users</small>
                    <h3><?= (int) $totalUsers ?></h3>
                </div>
            </div>
            <div class="stat-card success">
                <div class="stat-icon"><i class="fa-solid fa-user-check"></i></div>
                <div>
                    <small>Active users</small>
                    <h3><?= (int) $activeUsers ?></h3>
                </div>
            </div>
            <div class="stat-card info">
                <div class="stat-icon"><i class="fa-solid fa-file-lines"></i></div>
                <div>
                    <small>Stored files</small>
                    <h3><?= (int) $totalFiles ?></h3>
                </div>
            </div>
            <div class="stat-card warning">
                <div class="stat-icon"><i class="fa-solid fa-database"></i></div>
                <div>
                    <small>Total size</small>
                    <h3><?= formatBytes((int) $totalSize) ?></h3>
                </div>
            </div>
        </section>

        <section class="row mt-4">
            <div class="col-lg-7">
                <div class="panel-box">
                    <div class="panel-header"><h2 class="section-title">User management</h2></div>
                    <table class="table table-striped align-middle">
                        <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Quota</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($users as $userRow): ?>
                            <tr>
                                <td><?= h($userRow['first_name'] . ' ' . $userRow['last_name']) ?></td>
                                <td><?= h($userRow['email']) ?></td>
                                <td><?= h($userRow['role']) ?></td>
                                <td><span class="badge bg-<?= $userRow['status'] === 'active' ? 'success' : 'secondary' ?>"><?= h($userRow['status']) ?></span></td>
                                <td><?= formatBytes((int) $userRow['storage_quota']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="panel-box">
                    <div class="panel-header"><h2 class="section-title">Create user</h2></div>
                    <form method="post" class="mt-3">
                        <input type="hidden" name="action" value="create_user">
                        <div class="row g-2">
                            <div class="col-md-6">
                                <input type="text" class="form-control" name="first_name" placeholder="First name" required>
                            </div>
                            <div class="col-md-6">
                                <input type="text" class="form-control" name="last_name" placeholder="Last name" required>
                            </div>
                            <div class="col-12">
                                <input type="email" class="form-control" name="email" placeholder="Email" required>
                            </div>
                            <div class="col-12">
                                <input type="password" class="form-control" name="password" placeholder="Password (min 6 chars)" required>
                            </div>
                            <div class="col-12">
                                <input type="number" class="form-control" name="quota" value="5368709120" placeholder="Quota in bytes" required>
                            </div>
                            <div class="col-12">
                                <button class="btn btn-primary w-100" type="submit">Create account</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </section>

        <section class="panel-box mt-4">
            <div class="panel-header"><h2 class="section-title">System settings</h2></div>
            <form method="post" class="row g-3 mt-2">
                <input type="hidden" name="action" value="save_settings">
                <div class="col-md-6">
                    <label class="form-label">Allowed file extensions</label>
                    <input type="text" class="form-control" name="allowed_extensions" value="<?= h($settings['allowed_extensions']) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Max upload size (bytes)</label>
                    <input type="number" class="form-control" name="max_upload_size" value="<?= h($settings['max_upload_size']) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Trash retention (days)</label>
                    <input type="number" class="form-control" name="trash_retention_days" value="<?= h($settings['trash_retention_days']) ?>">
                </div>
                <div class="col-12">
                    <button class="btn btn-primary" type="submit">Save settings</button>
                </div>
            </form>
        </section>
    </main>
</div>
</body>
</html>
