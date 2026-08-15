<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/license_reminder.php';

requireLogin();

$results = null;
$error = '';
$force = false;
$ignoreWindow = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $force = isset($_POST['force']);
    $ignoreWindow = isset($_POST['ignore_window']);
    $onlyId = isset($_POST['lisans_id']) && $_POST['lisans_id'] !== ''
        ? (int) $_POST['lisans_id']
        : null;

    try {
        $results = runLicenseReminders($force, $onlyId, $ignoreWindow);
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$smtpConfigured = trim((string) ($_ENV['SMTP_HOST'] ?? '')) !== ''
    && trim((string) ($_ENV['SMTP_FROM_EMAIL'] ?? '')) !== '';

$days = $_ENV['LICENSE_REMINDER_DAYS'] ?? '30,14,7,3,1';
$overview = getReminderOverview();

adminLayoutStart('Lisans hatırlatmaları', 'hatirlatmalar');
?>

<?php if (!$smtpConfigured): ?>
  <div class="alert alert-warning">
    SMTP henüz ayarlı değil. <code>config/.env</code> içine
    <code>SMTP_HOST</code>, <code>SMTP_PORT</code>, <code>SMTP_USER</code>,
    <code>SMTP_PASS</code>, <code>SMTP_FROM_EMAIL</code> doldurun.
  </div>
<?php endif; ?>

<?php if ($error !== ''): ?>
  <div class="alert alert-danger"><?= e($error) ?></div>
<?php endif; ?>

<div class="row row-cards mb-3">
  <div class="col-sm-4">
    <div class="card">
      <div class="card-body">
        <div class="subheader">Aktif lisans</div>
        <div class="h1 mb-0"><?= (int) $overview['aktif'] ?></div>
      </div>
    </div>
  </div>
  <div class="col-sm-4">
    <div class="card">
      <div class="card-body">
        <div class="subheader">Eşik içinde (≤<?= (int) $overview['max_days'] ?> gün)</div>
        <div class="h1 mb-0 <?= $overview['esikte'] > 0 ? 'text-warning' : '' ?>">
          <?= (int) $overview['esikte'] ?>
        </div>
      </div>
    </div>
  </div>
  <div class="col-sm-4">
    <div class="card">
      <div class="card-body">
        <div class="subheader">Eşikler</div>
        <div class="h3 mb-0"><code><?= e($days) ?></code></div>
      </div>
    </div>
  </div>
</div>

<?php if ($overview['esikte'] === 0): ?>
  <div class="alert alert-info">
    <strong>0 sonuç normal.</strong>
    Mail yalnızca bitime <?= (int) $overview['max_days'] ?> gün (veya daha az) kala otomatik gider.
    Yeni lisanslar genelde 1 yıl olduğu için eşikte görünmezler.
    Test için aşağıdan lisans ID yazıp
    <strong>«Eşik dışındakileri de dahil et»</strong> işaretleyin.
  </div>
<?php endif; ?>

<div class="card mb-3">
  <div class="card-header">
    <h3 class="card-title">En yakın bitişler</h3>
  </div>
  <div class="table-responsive">
    <table class="table table-vcenter card-table table-sm">
      <thead>
        <tr>
          <th>ID</th>
          <th>Firma</th>
          <th>E-posta</th>
          <th>Bitiş</th>
          <th>Kalan</th>
          <th>Eşikte?</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$overview['yakindakiler']): ?>
          <tr><td colspan="6" class="text-secondary">Aktif lisans yok.</td></tr>
        <?php else: ?>
          <?php foreach ($overview['yakindakiler'] as $y): ?>
            <?php $kalan = (int) $y['kalan_gun']; ?>
            <tr>
              <td>#<?= (int) $y['id'] ?></td>
              <td><?= e($y['firma_adi']) ?></td>
              <td><?= e($y['email']) ?></td>
              <td><?= e(formatDate($y['bitis_tarihi'])) ?></td>
              <td><?= $kalan ?> gün</td>
              <td>
                <?= $kalan <= $overview['max_days']
                  ? '<span class="badge bg-warning">Evet</span>'
                  : '<span class="badge bg-secondary">Hayır</span>' ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card mb-3">
  <div class="card-body">
    <p class="text-secondary mb-3">
      Varsayılan: sadece eşik içindeki lisanslara mail atar.
      Aynı eşik bir kez gönderilir (<code>reminder_30d</code> vb.).
    </p>
    <form method="post" class="row g-3 align-items-end">
      <div class="col-md-3">
        <label class="form-label" for="lisans_id">Sadece lisans ID (opsiyonel)</label>
        <input type="number" class="form-control" name="lisans_id" id="lisans_id"
               placeholder="Örn. 1" min="1"
               value="<?= e($_POST['lisans_id'] ?? '') ?>">
      </div>
      <div class="col-md-4">
        <label class="form-check">
          <input class="form-check-input" type="checkbox" name="ignore_window" value="1"
            <?= $ignoreWindow ? 'checked' : '' ?>>
          <span class="form-check-label">Eşik dışındakileri de dahil et (test / manuel)</span>
        </label>
        <label class="form-check mt-2">
          <input class="form-check-input" type="checkbox" name="force" value="1"
            <?= $force ? 'checked' : '' ?>>
          <span class="form-check-label">Force (daha önce gönderilmiş olsa da tekrar at)</span>
        </label>
      </div>
      <div class="col-md-3">
        <button type="submit" class="btn btn-primary" <?= $smtpConfigured ? '' : 'disabled' ?>>
          Şimdi çalıştır
        </button>
      </div>
    </form>
  </div>
</div>

<div class="card mb-3">
  <div class="card-header">
    <h3 class="card-title">Cron (günlük)</h3>
  </div>
  <div class="card-body">
    <pre class="mb-0">0 9 * * * php <?= e(dirname(__DIR__) . '/check_license_expiry.php') ?></pre>
  </div>
</div>

<?php if (is_array($results)): ?>
  <div class="card">
    <div class="card-header">
      <h3 class="card-title">Sonuç (<?= count($results) ?> satır)</h3>
    </div>
    <div class="table-responsive">
      <table class="table table-vcenter card-table">
        <thead>
          <tr>
            <th>Durum</th>
            <th>Lisans</th>
            <th>Firma</th>
            <th>E-posta</th>
            <th>Kalan</th>
            <th>Mesaj</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($results === []): ?>
            <tr>
              <td colspan="6" class="text-secondary">
                İşlenecek lisans yok.
                <?php if (!$ignoreWindow): ?>
                  Eşik dışı test için «Eşik dışındakileri de dahil et» kutusunu işaretleyin
                  ve mümkünse lisans ID girin.
                <?php endif; ?>
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($results as $r): ?>
              <?php
                $badge = match ($r['status']) {
                    'sent' => 'bg-success',
                    'skip' => 'bg-secondary',
                    default => 'bg-danger',
                };
              ?>
              <tr>
                <td><span class="badge <?= $badge ?>"><?= e($r['status']) ?></span></td>
                <td>
                  <a href="lisans_hareketleri.php?lisans_id=<?= (int) $r['lisans_id'] ?>">
                    #<?= (int) $r['lisans_id'] ?>
                  </a>
                </td>
                <td><?= e($r['firma']) ?></td>
                <td><?= e($r['email']) ?></td>
                <td><?= (int) $r['kalan_gun'] ?> gün</td>
                <td><?= e($r['message']) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<?php adminLayoutEnd(); ?>
