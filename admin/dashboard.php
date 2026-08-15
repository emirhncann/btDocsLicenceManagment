<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$pdo = getDb();

$firmaSayisi = (int) $pdo->query('SELECT COUNT(*) FROM Firmalar')->fetchColumn();
$aktifLisans = (int) $pdo->query("SELECT COUNT(*) FROM Lisanslar WHERE status = 'aktif'")->fetchColumn();
$pasifLisans = (int) $pdo->query("SELECT COUNT(*) FROM Lisanslar WHERE status = 'pasif'")->fetchColumn();
$suresiDolmus = (int) $pdo->query("SELECT COUNT(*) FROM Lisanslar WHERE status = 'suresi_dolmus'")->fetchColumn();

$yaklasan = $pdo->query("
    SELECT l.id, l.bitis_tarihi, l.status, f.firma_adi
    FROM Lisanslar l
    INNER JOIN Firmalar f ON f.id = l.firma_id
    WHERE l.status = 'aktif'
      AND l.bitis_tarihi BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
    ORDER BY l.bitis_tarihi ASC
    LIMIT 10
")->fetchAll();

adminLayoutStart('Dashboard', 'dashboard');
?>

<div class="row row-deck row-cards mb-3">
  <div class="col-sm-6 col-lg-3">
    <div class="card">
      <div class="card-body">
        <div class="subheader">Firma</div>
        <div class="h1 mb-0"><?= $firmaSayisi ?></div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="card">
      <div class="card-body">
        <div class="subheader">Aktif lisans</div>
        <div class="h1 mb-0 text-success"><?= $aktifLisans ?></div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="card">
      <div class="card-body">
        <div class="subheader">Pasif lisans</div>
        <div class="h1 mb-0 text-secondary"><?= $pasifLisans ?></div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="card">
      <div class="card-body">
        <div class="subheader">Süresi dolmuş</div>
        <div class="h1 mb-0 text-danger"><?= $suresiDolmus ?></div>
      </div>
    </div>
  </div>
</div>

<div class="mb-3">
  <a href="hatirlatmalar.php" class="btn btn-outline-primary">Hatırlatma maillerini çalıştır</a>
</div>

<div class="card">
  <div class="card-header">
    <h3 class="card-title">30 gün içinde bitecek lisanslar</h3>
  </div>
  <div class="table-responsive">
    <table class="table table-vcenter card-table">
      <thead>
        <tr>
          <th>Firma</th>
          <th>Bitiş</th>
          <th>Durum</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$yaklasan): ?>
          <tr><td colspan="4" class="text-secondary">Yaklaşan bitiş yok.</td></tr>
        <?php else: ?>
          <?php foreach ($yaklasan as $row): ?>
            <tr>
              <td><?= e($row['firma_adi']) ?></td>
              <td><?= e(formatDate($row['bitis_tarihi'])) ?></td>
              <td><?= statusBadge($row['status']) ?></td>
              <td class="text-end">
                <a href="lisans_hareketleri.php?lisans_id=<?= (int) $row['id'] ?>">Hareketler</a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php adminLayoutEnd(); ?>
