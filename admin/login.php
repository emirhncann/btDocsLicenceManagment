<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if (isset($_SESSION['admin_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Kullanıcı adı ve şifre gerekli.';
    } else {
        try {
            if (doLogin($username, $password)) {
                header('Location: dashboard.php');
                exit;
            }
            $error = 'Geçersiz kullanıcı adı veya şifre.';
        } catch (Throwable $e) {
            // Login formunda gerçek sebebi göster (500 yerine)
            $error = 'Sistem hatası: ' . $e->getMessage();
            error_log('[login] ' . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Giriş — btDocs Lisans</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta20/dist/css/tabler.min.css">
  <style>
    :root {
      --tblr-primary: #6b0a1c;
      --tblr-primary-rgb: 107, 10, 28;
    }
    body {
      min-height: 100vh;
      background:
        radial-gradient(ellipse at 20% 0%, rgba(107, 10, 28, 0.12), transparent 50%),
        radial-gradient(ellipse at 80% 100%, rgba(131, 11, 36, 0.1), transparent 45%),
        #f4f6f9;
    }
    .login-brand {
      font-size: 1.75rem;
      font-weight: 700;
      letter-spacing: -0.03em;
      color: #6b0a1c;
    }
  </style>
</head>
<body class="d-flex flex-column">
  <div class="page page-center">
    <div class="container container-tight py-4">
      <div class="text-center mb-4">
        <div class="login-brand">btDocs Lisans</div>
        <div class="text-secondary mt-1">Yönetim paneli girişi</div>
      </div>
      <div class="card card-md">
        <div class="card-body">
          <?php if ($error !== ''): ?>
            <div class="alert alert-danger" role="alert"><?= e($error) ?></div>
          <?php endif; ?>
          <form method="post" autocomplete="off">
            <div class="mb-3">
              <label class="form-label" for="username">Kullanıcı adı</label>
              <input type="text" class="form-control" id="username" name="username"
                     value="<?= e($_POST['username'] ?? '') ?>" required autofocus>
            </div>
            <div class="mb-3">
              <label class="form-label" for="password">Şifre</label>
              <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <div class="form-footer">
              <button type="submit" class="btn btn-primary w-100">Giriş yap</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</body>
</html>
