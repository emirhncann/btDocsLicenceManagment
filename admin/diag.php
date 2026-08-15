<?php

declare(strict_types=1);

/**
 * Geçici teşhis — çalışınca silin veya web'den kapatın.
 * https://.../admin/diag.php
 */

header('Content-Type: text/plain; charset=utf-8');

echo "PHP: " . PHP_VERSION . "\n";
echo "SAPI: " . PHP_SAPI . "\n";
echo "pdo: " . (extension_loaded('pdo') ? 'yes' : 'NO') . "\n";
echo "pdo_mysql: " . (extension_loaded('pdo_mysql') ? 'yes' : 'NO') . "\n";

$envPath = __DIR__ . '/../config/.env';
echo ".env: " . (is_file($envPath) ? 'found' : 'MISSING') . "\n";

try {
    require_once __DIR__ . '/../includes/db.php';
    echo "DB_HOST: " . ($_ENV['DB_HOST'] ?? '?') . "\n";
    echo "DB_NAME: " . ($_ENV['DB_NAME'] ?? '?') . "\n";
    echo "DB_USER: " . ($_ENV['DB_USER'] ?? '?') . "\n";

    $pdo = getDb();
    echo "connect: OK\n";

    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    echo "tables: " . implode(', ', $tables) . "\n";

    $n = (int) $pdo->query('SELECT COUNT(*) FROM AdminUsers')->fetchColumn();
    echo "AdminUsers count: {$n}\n";

    // Tek seferlik: ?seed=1 ile admin oluştur
    if (isset($_GET['seed']) && $_GET['seed'] === '1') {
        $username = 'admin';
        $password = 'Ozt129103@';
        $check = $pdo->prepare('SELECT id FROM AdminUsers WHERE username = ? LIMIT 1');
        $check->execute([$username]);
        if ($check->fetch()) {
            echo "seed: admin zaten var\n";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $ins = $pdo->prepare('INSERT INTO AdminUsers (username, password_hash) VALUES (?, ?)');
            $ins->execute([$username, $hash]);
            echo "seed: admin oluşturuldu (admin / Ozt129103@)\n";
        }
    } elseif ($n === 0) {
        echo "HINT: Admin yok. Aç: diag.php?seed=1\n";
    }
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
