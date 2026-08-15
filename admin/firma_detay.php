<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: firmalar.php');
    exit;
}

$pdo = getDb();

$stmt = $pdo->prepare('SELECT * FROM Firmalar WHERE id = ?');
$stmt->execute([$id]);
$firma = $stmt->fetch();

if (!$firma) {
    http_response_code(404);
    echo 'Firma bulunamadı.';
    exit;
}

$lisansStmt = $pdo->prepare(
    'SELECT * FROM Lisanslar WHERE firma_id = ? ORDER BY id DESC'
);
$lisansStmt->execute([$id]);
$lisanslar = $lisansStmt->fetchAll();

adminLayoutStart('Firma detayı', 'firmalar');
?>

<div class="mb-3">
  <a href="firmalar.php">&larr; Firmalara dön</a>
</div>

<div class="card mb-3">
  <div class="card-header">
    <h3 class="card-title"><?= e($firma['firma_adi']) ?></h3>
    <div class="card-actions">
      <a href="lisans_ekle.php?firma_id=<?= $id ?>" class="btn btn-primary btn-sm">Lisans tanımla</a>
    </div>
  </div>
  <div class="card-body">
    <div class="datagrid">
      <div class="datagrid-item">
        <div class="datagrid-title">VKN</div>
        <div class="datagrid-content"><?= e($firma['vkn']) ?></div>
      </div>
      <div class="datagrid-item">
        <div class="datagrid-title">E-posta</div>
        <div class="datagrid-content"><?= e($firma['email']) ?></div>
      </div>
      <div class="datagrid-item">
        <div class="datagrid-title">Durum</div>
        <div class="datagrid-content">
          <?= ((int) $firma['is_active'] === 1) ? 'Aktif' : 'Pasif' ?>
        </div>
      </div>
      <div class="datagrid-item">
        <div class="datagrid-title">Kayıt</div>
        <div class="datagrid-content"><?= e(formatDateTime($firma['created_at'])) ?></div>
      </div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <h3 class="card-title">Lisanslar</h3>
  </div>
  <div class="table-responsive">
    <table class="table table-vcenter card-table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Başlangıç</th>
          <th>Bitiş</th>
          <th>Durum</th>
          <th>API Key</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$lisanslar): ?>
          <tr><td colspan="6" class="text-secondary">Bu firmaya ait lisans yok.</td></tr>
        <?php else: ?>
          <?php foreach ($lisanslar as $l): ?>
            <tr>
              <td><?= (int) $l['id'] ?></td>
              <td><?= e(formatDate($l['baslangic_tarihi'])) ?></td>
              <td><?= e(formatDate($l['bitis_tarihi'])) ?></td>
              <td><?= statusBadge($l['status']) ?></td>
              <td><code class="small"><?= e(substr($l['api_key'], 0, 12)) ?>…</code></td>
              <td class="text-end">
                <a href="lisans_hareketleri.php?lisans_id=<?= (int) $l['id'] ?>">Hareketler</a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php adminLayoutEnd(); ?>
