<?php

declare(strict_types=1);

/**
 * CLI: php database/migrate.php
 * migrations/*.sql dosyalarını isim sırasıyla çalıştırır.
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Bu script yalnızca CLI'dan çalıştırılır.\n");
    exit(1);
}

require_once __DIR__ . '/../includes/db.php';

$pdo = getDb();
$dir = __DIR__ . '/migrations';

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS Migrations (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        filename VARCHAR(255) NOT NULL,
        applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_migrations_filename (filename)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);

$done = $pdo->query('SELECT filename FROM Migrations')->fetchAll(PDO::FETCH_COLUMN);
$doneMap = array_fill_keys($done, true);

$files = glob($dir . '/*.sql') ?: [];
sort($files, SORT_STRING);

if ($files === []) {
    echo "Migration dosyası bulunamadı.\n";
    exit(0);
}

$applied = 0;

foreach ($files as $path) {
    $filename = basename($path);

    if (isset($doneMap[$filename])) {
        echo "[skip] {$filename}\n";
        continue;
    }

    $sql = file_get_contents($path);
    if ($sql === false || trim($sql) === '') {
        echo "[warn] {$filename} boş, atlandı.\n";
        continue;
    }

    try {
        $pdo->beginTransaction();
        $pdo->exec($sql);
        $ins = $pdo->prepare('INSERT INTO Migrations (filename) VALUES (?)');
        $ins->execute([$filename]);
        $pdo->commit();
        echo "[ok]   {$filename}\n";
        $applied++;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        fwrite(STDERR, "[fail] {$filename}: " . $e->getMessage() . "\n");
        exit(1);
    }
}

echo $applied === 0
    ? "Yeni migration yok.\n"
    : "{$applied} migration uygulandı.\n";
