<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

if (isset($_SESSION['admin_id'])) {
    header('Location: admin/dashboard.php');
} else {
    header('Location: admin/login.php');
}
exit;
