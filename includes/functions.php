<?php

declare(strict_types=1);

function generateApiKey(): string
{
    return bin2hex(random_bytes(32));
}

function formatDate(?string $date, string $format = 'd.m.Y'): string
{
    if ($date === null || $date === '') {
        return '—';
    }

    try {
        return (new DateTime($date))->format($format);
    } catch (Exception) {
        return $date;
    }
}

function formatDateTime(?string $date, string $format = 'd.m.Y H:i'): string
{
    return formatDate($date, $format);
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function statusBadge(string $status): string
{
    $map = [
        'aktif' => 'bg-success',
        'pasif' => 'bg-secondary',
        'suresi_dolmus' => 'bg-danger',
    ];
    $class = $map[$status] ?? 'bg-secondary';
    $label = match ($status) {
        'aktif' => 'Aktif',
        'pasif' => 'Pasif',
        'suresi_dolmus' => 'Süresi Dolmuş',
        default => $status,
    };

    return '<span class="badge ' . $class . '">' . e($label) . '</span>';
}

/**
 * Ortak admin sayfa başlığı + sol menü (Tabler CDN).
 */
function adminLayoutStart(string $title, string $active = ''): void
{
    $username = e(currentAdminUsername());
    $pageTitle = e($title);

    $nav = static function (string $key, string $href, string $label) use ($active): string {
        $cls = $key === $active ? 'nav-link active' : 'nav-link';
        return '<li class="nav-item"><a class="' . $cls . '" href="' . $href . '">' . $label . '</a></li>';
    };

    echo <<<HTML
<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{$pageTitle} — btDocs Lisans</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta20/dist/css/tabler.min.css">
  <style>
    :root {
      --tblr-primary: #6b0a1c;
      --tblr-primary-rgb: 107, 10, 28;
      --tblr-link-color: #6b0a1c;
      --tblr-link-hover-color: #830b24;
    }
    .navbar-brand-text { font-weight: 700; letter-spacing: -0.02em; }
    .api-key-box {
      font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
      font-size: 1.05rem;
      word-break: break-all;
      background: #f6f8fb;
      border: 1px dashed #c8d0dc;
      border-radius: .75rem;
      padding: 1rem 1.25rem;
    }
  </style>
</head>
<body>
  <div class="page">
    <header class="navbar navbar-expand-md d-print-none">
      <div class="container-xl">
        <h1 class="navbar-brand navbar-brand-autodark pe-0 pe-md-3">
          <a href="dashboard.php" class="navbar-brand-text text-decoration-none text-reset">btDocs Lisans</a>
        </h1>
        <div class="navbar-nav flex-row order-md-last">
          <div class="nav-item d-none d-md-flex me-3">
            <span class="nav-link px-0">{$username}</span>
          </div>
          <div class="nav-item">
            <a href="logout.php" class="btn btn-outline-danger btn-sm">Çıkış</a>
          </div>
        </div>
      </div>
    </header>
    <div class="navbar-expand-md">
      <div class="collapse navbar-collapse" id="navbar-menu">
        <div class="navbar">
          <div class="container-xl">
            <ul class="navbar-nav">
HTML;
    echo $nav('dashboard', 'dashboard.php', 'Dashboard');
    echo $nav('firmalar', 'firmalar.php', 'Firmalar');
    echo $nav('hatirlatmalar', 'hatirlatmalar.php', 'Hatırlatmalar');
    echo <<<HTML
            </ul>
          </div>
        </div>
      </div>
    </div>
    <div class="page-wrapper">
      <div class="page-header d-print-none">
        <div class="container-xl">
          <div class="row g-2 align-items-center">
            <div class="col">
              <h2 class="page-title">{$pageTitle}</h2>
            </div>
          </div>
        </div>
      </div>
      <div class="page-body">
        <div class="container-xl">
HTML;
}

function adminLayoutEnd(): void
{
    echo <<<HTML
        </div>
      </div>
    </div>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta20/dist/js/tabler.min.js"></script>
</body>
</html>
HTML;
}
