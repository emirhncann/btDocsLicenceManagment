<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$lisansId = (int) ($_GET['lisans_id'] ?? 0);
if ($lisansId <= 0) {
    header('Location: firmalar.php');
    exit;
}

$pdo = getDb();

$stmt = $pdo->prepare(
    'SELECT l.*, f.firma_adi, f.id AS firma_id
     FROM Lisanslar l
     INNER JOIN Firmalar f ON f.id = l.firma_id
     WHERE l.id = ?'
);
$stmt->execute([$lisansId]);
$lisans = $stmt->fetch();

if (!$lisans) {
    http_response_code(404);
    echo 'Lisans bulunamadı.';
    exit;
}

$hareketStmt = $pdo->prepare(
    'SELECT * FROM LisansHareketleri WHERE lisans_id = ? ORDER BY id DESC'
);
$hareketStmt->execute([$lisansId]);
$hareketler = $hareketStmt->fetchAll();

adminLayoutStart('Lisans hareketleri', 'firmalar');
?>

<div class="mb-3">
  <a href="firma_detay.php?id=<?= (int) $lisans['firma_id'] ?>">
    &larr; <?= e($lisans['firma_adi']) ?>
  </a>
</div>

<div class="card mb-3">
  <div class="card-body">
    <div class="datagrid">
      <div class="datagrid-item">
        <div class="datagrid-title">Lisans ID</div>
        <div class="datagrid-content">#<?= (int) $lisans['id'] ?></div>
      </div>
      <div class="datagrid-item">
        <div class="datagrid-title">Firma</div>
        <div class="datagrid-content"><?= e($lisans['firma_adi']) ?></div>
      </div>
      <div class="datagrid-item">
        <div class="datagrid-title">Dönem</div>
        <div class="datagrid-content">
          <?= e(formatDate($lisans['baslangic_tarihi'])) ?>
          —
          <?= e(formatDate($lisans['bitis_tarihi'])) ?>
        </div>
      </div>
      <div class="datagrid-item">
        <div class="datagrid-title">Durum</div>
        <div class="datagrid-content"><?= statusBadge($lisans['status']) ?></div>
      </div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <h3 class="card-title">Hareket geçmişi</h3>
  </div>
  <div class="table-responsive">
    <table class="table table-vcenter card-table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Action</th>
          <th>Detay</th>
          <th>Tarih</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$hareketler): ?>
          <tr><td colspan="4" class="text-secondary">Hareket yok.</td></tr>
        <?php else: ?>
          <?php foreach ($hareketler as $h): ?>
            <tr>
              <td><?= (int) $h['id'] ?></td>
              <td><code><?= e($h['action']) ?></code></td>
              <td><?= e($h['detay'] ?? '') ?></td>
              <td><?= e(formatDateTime($h['created_at'])) ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php adminLayoutEnd(); ?>
