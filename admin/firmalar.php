<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$pdo = getDb();
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $vkn = trim((string) ($_POST['vkn'] ?? ''));
    $firmaAdi = trim((string) ($_POST['firma_adi'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));

    if ($vkn === '' || $firmaAdi === '' || $email === '') {
        $error = 'VKN, firma adı ve e-posta zorunludur.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Geçerli bir e-posta girin.';
    } else {
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO Firmalar (vkn, firma_adi, email, is_active) VALUES (?, ?, ?, 1)'
            );
            $stmt->execute([$vkn, $firmaAdi, $email]);
            $success = 'Firma eklendi.';
        } catch (PDOException $ex) {
            $error = 'Kayıt eklenemedi: ' . $ex->getMessage();
        }
    }
}

$q = trim((string) ($_GET['q'] ?? ''));

if ($q !== '') {
    $like = '%' . $q . '%';
    $stmt = $pdo->prepare(
        'SELECT * FROM Firmalar
         WHERE firma_adi LIKE ? OR vkn LIKE ? OR email LIKE ?
         ORDER BY id DESC'
    );
    $stmt->execute([$like, $like, $like]);
    $firmalar = $stmt->fetchAll();
} else {
    $firmalar = $pdo->query('SELECT * FROM Firmalar ORDER BY id DESC')->fetchAll();
}

adminLayoutStart('Firmalar', 'firmalar');
?>

<?php if ($success !== ''): ?>
  <div class="alert alert-success"><?= e($success) ?></div>
<?php endif; ?>
<?php if ($error !== ''): ?>
  <div class="alert alert-danger"><?= e($error) ?></div>
<?php endif; ?>

<div class="row row-cards">
  <div class="col-lg-4">
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">Yeni firma</h3>
      </div>
      <div class="card-body">
        <form method="post">
          <div class="mb-3">
            <label class="form-label" for="vkn">VKN</label>
            <input type="text" class="form-control" id="vkn" name="vkn" required maxlength="20"
                   value="<?= e($_POST['vkn'] ?? '') ?>">
          </div>
          <div class="mb-3">
            <label class="form-label" for="firma_adi">Firma adı</label>
            <input type="text" class="form-control" id="firma_adi" name="firma_adi" required maxlength="200"
                   value="<?= e($_POST['firma_adi'] ?? '') ?>">
          </div>
          <div class="mb-3">
            <label class="form-label" for="email">E-posta</label>
            <input type="email" class="form-control" id="email" name="email" required maxlength="200"
                   value="<?= e($_POST['email'] ?? '') ?>">
          </div>
          <button type="submit" class="btn btn-primary w-100">Kaydet</button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-8">
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">Firma listesi</h3>
        <div class="card-actions">
          <form method="get" class="d-flex gap-2">
            <input type="search" name="q" class="form-control form-control-sm"
                   placeholder="Ara (ad, VKN, e-posta)" value="<?= e($q) ?>">
            <button class="btn btn-sm btn-outline-primary" type="submit">Ara</button>
            <?php if ($q !== ''): ?>
              <a class="btn btn-sm" href="firmalar.php">Temizle</a>
            <?php endif; ?>
          </form>
        </div>
      </div>
      <div class="table-responsive">
        <table class="table table-vcenter card-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Firma</th>
              <th>VKN</th>
              <th>E-posta</th>
              <th>Durum</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$firmalar): ?>
              <tr><td colspan="6" class="text-secondary">Kayıt yok.</td></tr>
            <?php else: ?>
              <?php foreach ($firmalar as $f): ?>
                <tr>
                  <td><?= (int) $f['id'] ?></td>
                  <td><?= e($f['firma_adi']) ?></td>
                  <td><?= e($f['vkn']) ?></td>
                  <td><?= e($f['email']) ?></td>
                  <td>
                    <?= ((int) $f['is_active'] === 1)
                      ? '<span class="badge bg-success">Aktif</span>'
                      : '<span class="badge bg-secondary">Pasif</span>' ?>
                  </td>
                  <td class="text-end">
                    <a href="firma_detay.php?id=<?= (int) $f['id'] ?>">Detay</a>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php adminLayoutEnd(); ?>
