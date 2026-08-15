<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

function getDb(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        if (!extension_loaded('pdo_mysql')) {
            throw new RuntimeException(
                'PHP pdo_mysql eklentisi yüklü değil. IIS PHP Manager / php.ini içinde extension=pdo_mysql açın.'
            );
        }

        $host = $_ENV['DB_HOST'] ?? '127.0.0.1';
        $port = $_ENV['DB_PORT'] ?? '3306';
        $name = $_ENV['DB_NAME'] ?? '';
        $user = $_ENV['DB_USER'] ?? '';
        $pass = $_ENV['DB_PASS'] ?? '';

        if ($name === '' || $user === '') {
            throw new RuntimeException('config/.env içinde DB_NAME ve DB_USER tanımlı olmalı.');
        }

        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";

        try {
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            throw new RuntimeException('Veritabanı bağlantısı başarısız: ' . $e->getMessage(), 0, $e);
        }
    }

    return $pdo;
}
