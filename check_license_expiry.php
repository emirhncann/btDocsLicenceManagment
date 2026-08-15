<?php

declare(strict_types=1);

/**
 * CLI / cron: php check_license_expiry.php
 * Opsiyonel: php check_license_expiry.php --force
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Bu script yalnızca CLI'dan çalıştırılır. Panilden: admin/hatirlatmalar.php\n");
    exit(1);
}

require_once __DIR__ . '/includes/license_reminder.php';

$force = in_array('--force', $argv, true);

echo "Lisans hatırlatmaları çalışıyor...\n";
$results = runLicenseReminders($force);

if ($results === []) {
    echo "Gönderilecek lisans yok.\n";
    exit(0);
}

$sent = 0;
$skip = 0;
$err = 0;

foreach ($results as $r) {
    $line = sprintf(
        "[%s] #%d %s <%s> kalan=%d — %s\n",
        $r['status'],
        $r['lisans_id'],
        $r['firma'],
        $r['email'],
        $r['kalan_gun'],
        $r['message']
    );
    echo $line;

    if ($r['status'] === 'sent') {
        $sent++;
    } elseif ($r['status'] === 'skip') {
        $skip++;
    } else {
        $err++;
    }
}

echo "Özet: sent={$sent} skip={$skip} error={$err}\n";
exit($err > 0 ? 1 : 0);
