<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';

if (currentUser()) {
    redirect('/dashboard.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');

    $stmt = appConnection()->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash']) && $user['status'] === 'active') {
        $_SESSION['user'] = [
            'id' => (int) $user['id'],
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name'],
            'email' => $user['email'],
            'role' => $user['role'],
        ];

        logActivity((int) $user['id'], 'login', 'User signed in successfully.');
        redirect('/dashboard.php');
    }

    $error = 'Invalid email or password. Please try again.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | NimbusVault</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= h(appUrl('/assets/app.css')) ?>">
</head>
<body class="login-body">
    <div class="container-fluid min-vh-100 d-flex align-items-center justify-content-center">
        <div class="row w-100 justify-content-center">
            <div class="col-lg-5 col-md-7">
                <div class="login-card">
                    <div class="brand mb-4">
                        <div class="brand-mark"><i class="fa-solid fa-cloud"></i></div>
                        <div>
                            <h1 class="brand-name">NimbusVault</h1>
                            <p class="brand-subtitle">Digital file management</p>
                        </div>
                    </div>

                    <h2 class="page-title text-center mb-4">Welcome back</h2>

                    <?php if ($error !== ''): ?>
                        <div class="alert alert-danger" role="alert"><?= h($error) ?></div>
                    <?php endif; ?>

                    <form method="post" class="needs-validation" novalidate>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email address</label>
                            <input type="email" class="form-control form-control-lg" id="email" name="email" value="admin@cloudvault.local" required>
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control form-control-lg" id="password" name="password" value="admin123" required>
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg w-100 mb-3">Sign in</button>
                    </form>

                    <div class="demo-box">
                        <div class="demo-title"><i class="fa-solid fa-key"></i> Demo accounts</div>
                        <div class="demo-row">
                            <span>Admin</span>
                            <strong>admin@cloudvault.local / admin123</strong>
                        </div>
                        <div class="demo-row">
                            <span>User</span>
                            <strong>demo@cloudvault.local / demo123</strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
