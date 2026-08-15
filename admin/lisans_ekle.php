<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$firmaId = (int) ($_GET['firma_id'] ?? $_POST['firma_id'] ?? 0);
if ($firmaId <= 0) {
    header('Location: firmalar.php');
    exit;
}

$pdo = getDb();

$stmt = $pdo->prepare('SELECT * FROM Firmalar WHERE id = ?');
$stmt->execute([$firmaId]);
$firma = $stmt->fetch();

if (!$firma) {
    http_response_code(404);
    echo 'Firma bulunamadı.';
    exit;
}

$error = '';
$createdApiKey = null;
$createdLisansId = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $baslangic = trim((string) ($_POST['baslangic_tarihi'] ?? ''));
    $bitis = trim((string) ($_POST['bitis_tarihi'] ?? ''));

    if ($baslangic === '' || $bitis === '') {
        $error = 'Başlangıç ve bitiş tarihi gerekli.';
    } elseif ($bitis < $baslangic) {
        $error = 'Bitiş tarihi başlangıçtan önce olamaz.';
    } else {
        try {
            $apiKey = generateApiKey();
            $pdo->beginTransaction();

            $ins = $pdo->prepare(
                'INSERT INTO Lisanslar
                   (firma_id, baslangic_tarihi, bitis_tarihi, api_key, status, created_by)
                 VALUES (?, ?, ?, ?, \'aktif\', ?)'
            );
            $ins->execute([
                $firmaId,
                $baslangic,
                $bitis,
                $apiKey,
                $_SESSION['admin_id'],
            ]);
            $lisansId = (int) $pdo->lastInsertId();

            $hareket = $pdo->prepare(
                'INSERT INTO LisansHareketleri (lisans_id, action, detay)
                 VALUES (?, \'created\', ?)'
            );
            $hareket->execute([
                $lisansId,
                'Lisans oluşturuldu. Başlangıç: ' . $baslangic . ', Bitiş: ' . $bitis,
            ]);

            $pdo->commit();
            $createdApiKey = $apiKey;
            $createdLisansId = $lisansId;
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = 'Lisans oluşturulamadı: ' . $ex->getMessage();
        }
    }
}

adminLayoutStart('Lisans tanımla', 'firmalar');
?>

<div class="mb-3">
  <a href="firma_detay.php?id=<?= $firmaId ?>">&larr; <?= e($firma['firma_adi']) ?></a>
</div>

<?php if ($createdApiKey !== null): ?>
  <div class="card mb-3 border-success">
    <div class="card-body">
      <h3 class="card-title text-success">Lisans oluşturuldu (#<?= (int) $createdLisansId ?>)</h3>
      <p class="text-secondary">
        API anahtarını şimdi kopyalayın. Bu ekrandan sonra tam anahtar listede kısaltılmış görünür.
      </p>
      <div class="api-key-box mb-3" id="apiKeyBox"><?= e($createdApiKey) ?></div>
      <button type="button" class="btn btn-primary" id="copyBtn">Kopyala</button>
      <a class="btn" href="firma_detay.php?id=<?= $firmaId ?>">Firmaya dön</a>
      <a class="btn" href="lisans_hareketleri.php?lisans_id=<?= (int) $createdLisansId ?>">Hareketler</a>
    </div>
  </div>
  <script>
    document.getElementById('copyBtn').addEventListener('click', async function () {
      const text = document.getElementById('apiKeyBox').textContent.trim();
      try {
        await navigator.clipboard.writeText(text);
        this.textContent = 'Kopyalandı';
        setTimeout(() => { this.textContent = 'Kopyala'; }, 1500);
      } catch (e) {
        alert('Kopyalama başarısız. Anahtarı elle seçip kopyalayın.');
      }
    });
  </script>
<?php else: ?>
  <?php if ($error !== ''): ?>
    <div class="alert alert-danger"><?= e($error) ?></div>
  <?php endif; ?>

  <div class="card">
    <div class="card-header">
      <h3 class="card-title"><?= e($firma['firma_adi']) ?> için yeni lisans</h3>
    </div>
    <div class="card-body">
      <form method="post">
        <input type="hidden" name="firma_id" value="<?= $firmaId ?>">
        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label" for="baslangic_tarihi">Başlangıç tarihi</label>
            <input type="date" class="form-control" id="baslangic_tarihi" name="baslangic_tarihi"
                   value="<?= e($_POST['baslangic_tarihi'] ?? date('Y-m-d')) ?>" required>
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label" for="bitis_tarihi">Bitiş tarihi</label>
            <input type="date" class="form-control" id="bitis_tarihi" name="bitis_tarihi"
                   value="<?= e($_POST['bitis_tarihi'] ?? date('Y-m-d', strtotime('+1 year'))) ?>" required>
          </div>
        </div>
        <button type="submit" class="btn btn-primary">Oluştur</button>
        <a href="firma_detay.php?id=<?= $firmaId ?>" class="btn">İptal</a>
      </form>
    </div>
  </div>
<?php endif; ?>

<?php adminLayoutEnd(); ?>
