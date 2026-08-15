<?php

declare(strict_types=1);

/**
 * CLI: php seed_admin.php
 * Varsayılan admin kullanıcısını ekler (varsa atlar).
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Bu script yalnızca CLI'dan çalıştırılır.\n");
    exit(1);
}

require_once __DIR__ . '/includes/db.php';

$username = 'admin';
$password = 'Ozt129103@';

$pdo = getDb();

$check = $pdo->prepare('SELECT id FROM AdminUsers WHERE username = ? LIMIT 1');
$check->execute([$username]);

if ($check->fetch()) {
    echo "Admin kullanıcısı zaten var, atlandı.\n";
    exit(0);
}

$hash = password_hash($password, PASSWORD_DEFAULT);
$ins = $pdo->prepare('INSERT INTO AdminUsers (username, password_hash) VALUES (?, ?)');
$ins->execute([$username, $hash]);

echo "Admin oluşturuldu: {$username}\n";
