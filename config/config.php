<?php

declare(strict_types=1);

function loadEnv(string $path): void
{
    if (!is_file($path)) {
        throw new RuntimeException('.env bulunamadı: ' . $path);
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        throw new RuntimeException('.env okunamadı: ' . $path);
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (!str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);

        // Çift/tek tırnakları kaldır
        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        $_ENV[$key] = $value;
        putenv($key . '=' . $value);
    }
}

loadEnv(__DIR__ . '/.env');

date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'Europe/Istanbul');
