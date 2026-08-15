<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$apiKey = $_GET['api_key'] ?? null;

if (!$apiKey) {
    http_response_code(400);
    echo json_encode(['error' => 'api_key gerekli'], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt = getDb()->prepare('SELECT * FROM Lisanslar WHERE api_key = ? LIMIT 1');
$stmt->execute([$apiKey]);
$lisans = $stmt->fetch();

if (!$lisans) {
    http_response_code(404);
    echo json_encode([
        'valid' => false,
        'error' => 'Geçersiz lisans anahtarı',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($lisans['status'] === 'pasif') {
    echo json_encode([
        'valid' => false,
        'reason' => 'pasif',
        'bitis_tarihi' => $lisans['bitis_tarihi'],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$bugun = new DateTime('today');
$bitis = new DateTime($lisans['bitis_tarihi']);

if ($bitis < $bugun) {
    if ($lisans['status'] !== 'suresi_dolmus') {
        getDb()->prepare("UPDATE Lisanslar SET status = 'suresi_dolmus' WHERE id = ?")
            ->execute([$lisans['id']]);
    }

    echo json_encode([
        'valid' => false,
        'reason' => 'expired',
        'bitis_tarihi' => $lisans['bitis_tarihi'],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$kalanGun = (int) $bugun->diff($bitis)->days;

echo json_encode([
    'valid' => true,
    'bitis_tarihi' => $lisans['bitis_tarihi'],
    'kalan_gun' => $kalanGun,
], JSON_UNESCAPED_UNICODE);
