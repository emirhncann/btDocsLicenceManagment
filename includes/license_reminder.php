<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mail.php';
require_once __DIR__ . '/functions.php';

/**
 * Yaklaşan bitiş tarihleri için hatırlatma maili gönderir.
 * Aynı eşik için tekrar göndermez (LisansHareketleri action = reminder_Xd).
 *
 * @param bool $force Aynı eşik daha önce gönderilmiş olsa da tekrar at
 * @param int|null $onlyLisansId Sadece bu lisans
 * @param bool $ignoreWindow Eşik penceresini yok say (test / manuel)
 * @return list<array{lisans_id:int,firma:string,email:string,kalan_gun:int,status:string,message:string}>
 */
function runLicenseReminders(bool $force = false, ?int $onlyLisansId = null, bool $ignoreWindow = false): array
{
    $pdo = getDb();
    $daysList = parseReminderDays($_ENV['LICENSE_REMINDER_DAYS'] ?? '30,14,7,3,1');
    $results = [];

    // Süresi geçmiş aktifleri işaretle
    $expired = $pdo->query(
        "SELECT id FROM Lisanslar WHERE status = 'aktif' AND bitis_tarihi < CURDATE()"
    )->fetchAll();
    foreach ($expired as $row) {
        $pdo->prepare("UPDATE Lisanslar SET status = 'suresi_dolmus' WHERE id = ?")->execute([$row['id']]);
        $exists = $pdo->prepare(
            "SELECT id FROM LisansHareketleri WHERE lisans_id = ? AND action = 'expired' LIMIT 1"
        );
        $exists->execute([$row['id']]);
        if (!$exists->fetch()) {
            $pdo->prepare(
                "INSERT INTO LisansHareketleri (lisans_id, action, detay) VALUES (?, 'expired', 'Otomatik: süresi doldu')"
            )->execute([$row['id']]);
        }
    }

    $maxDays = max($daysList);
    $sql = "SELECT l.id, l.bitis_tarihi, l.status, f.firma_adi, f.email
            FROM Lisanslar l
            INNER JOIN Firmalar f ON f.id = l.firma_id
            WHERE l.status = 'aktif'
              AND f.is_active = 1";
    $params = [];

    if (!$ignoreWindow) {
        $sql .= ' AND l.bitis_tarihi BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)';
        $params[] = $maxDays;
    } else {
        $sql .= ' AND l.bitis_tarihi >= CURDATE()';
    }

    if ($onlyLisansId !== null) {
        $sql .= ' AND l.id = ?';
        $params[] = $onlyLisansId;
    }

    $sql .= ' ORDER BY l.bitis_tarihi ASC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $lisanslar = $stmt->fetchAll();

    $bugun = new DateTime('today');

    foreach ($lisanslar as $lisans) {
        $bitis = new DateTime($lisans['bitis_tarihi']);
        if ($bitis < $bugun) {
            continue;
        }

        $kalan = (int) $bugun->diff($bitis)->days;

        if ($ignoreWindow) {
            $action = 'reminder_manual';
        } else {
            $esikler = $daysList;
            sort($esikler, SORT_NUMERIC);
            $esik = null;
            foreach ($esikler as $d) {
                if ($kalan <= $d) {
                    $esik = $d;
                    break;
                }
            }

            if ($esik === null) {
                continue;
            }
            $action = 'reminder_' . $esik . 'd';
        }

        if (!$force) {
            $check = $pdo->prepare(
                'SELECT id FROM LisansHareketleri WHERE lisans_id = ? AND action = ? LIMIT 1'
            );
            $check->execute([(int) $lisans['id'], $action]);
            if ($check->fetch()) {
                $results[] = [
                    'lisans_id' => (int) $lisans['id'],
                    'firma' => $lisans['firma_adi'],
                    'email' => $lisans['email'],
                    'kalan_gun' => $kalan,
                    'status' => 'skip',
                    'message' => "Zaten gönderilmiş ({$action})",
                ];
                continue;
            }
        }

        $subject = sprintf(
            'btDocs lisans hatırlatması — %s (%d gün)',
            $lisans['firma_adi'],
            $kalan
        );

        $html = buildReminderEmailHtml([
            'firma_adi' => $lisans['firma_adi'],
            'bitis_tarihi' => $lisans['bitis_tarihi'],
            'kalan_gun' => $kalan,
        ]);

        try {
            sendMail($lisans['email'], $subject, $html);

            $logAction = $force && !$ignoreWindow ? ($action . '_manual') : $action;
            $pdo->prepare(
                'INSERT INTO LisansHareketleri (lisans_id, action, detay) VALUES (?, ?, ?)'
            )->execute([
                (int) $lisans['id'],
                $logAction,
                "Hatırlatma maili gönderildi → {$lisans['email']} (kalan {$kalan} gün)",
            ]);

            $results[] = [
                'lisans_id' => (int) $lisans['id'],
                'firma' => $lisans['firma_adi'],
                'email' => $lisans['email'],
                'kalan_gun' => $kalan,
                'status' => 'sent',
                'message' => 'Gönderildi',
            ];
        } catch (Throwable $e) {
            $results[] = [
                'lisans_id' => (int) $lisans['id'],
                'firma' => $lisans['firma_adi'],
                'email' => $lisans['email'],
                'kalan_gun' => $kalan,
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }

    return $results;
}

/**
 * Panel özeti: kaç lisans eşikte, en yakın bitişler.
 *
 * @return array{aktif:int,esikte:int,max_days:int,yakindakiler:list<array<string,mixed>>}
 */
function getReminderOverview(): array
{
    $pdo = getDb();
    $daysList = parseReminderDays($_ENV['LICENSE_REMINDER_DAYS'] ?? '30,14,7,3,1');
    $maxDays = max($daysList);

    $aktif = (int) $pdo->query(
        "SELECT COUNT(*) FROM Lisanslar l
         INNER JOIN Firmalar f ON f.id = l.firma_id
         WHERE l.status = 'aktif' AND f.is_active = 1 AND l.bitis_tarihi >= CURDATE()"
    )->fetchColumn();

    $esikStmt = $pdo->prepare(
        "SELECT COUNT(*) FROM Lisanslar l
         INNER JOIN Firmalar f ON f.id = l.firma_id
         WHERE l.status = 'aktif' AND f.is_active = 1
           AND l.bitis_tarihi BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)"
    );
    $esikStmt->execute([$maxDays]);
    $esikte = (int) $esikStmt->fetchColumn();

    $yakin = $pdo->query(
        "SELECT l.id, l.bitis_tarihi, f.firma_adi, f.email,
                DATEDIFF(l.bitis_tarihi, CURDATE()) AS kalan_gun
         FROM Lisanslar l
         INNER JOIN Firmalar f ON f.id = l.firma_id
         WHERE l.status = 'aktif' AND f.is_active = 1 AND l.bitis_tarihi >= CURDATE()
         ORDER BY l.bitis_tarihi ASC
         LIMIT 10"
    )->fetchAll();

    return [
        'aktif' => $aktif,
        'esikte' => $esikte,
        'max_days' => $maxDays,
        'yakindakiler' => $yakin,
    ];
}

/** @return list<int> */
function parseReminderDays(string $raw): array
{
    $parts = preg_split('/\s*,\s*/', trim($raw)) ?: [];
    $days = [];
    foreach ($parts as $p) {
        if ($p === '' || !ctype_digit($p)) {
            continue;
        }
        $n = (int) $p;
        if ($n > 0) {
            $days[] = $n;
        }
    }
    $days = array_values(array_unique($days));
    return $days !== [] ? $days : [30, 14, 7, 3, 1];
}

/** @param array{firma_adi:string,bitis_tarihi:string,kalan_gun:int} $data */
function buildReminderEmailHtml(array $data): string
{
    $firma = e($data['firma_adi']);
    $bitis = e(formatDate($data['bitis_tarihi']));
    $kalan = (int) $data['kalan_gun'];
    $yil = date('Y');

    // Outlook / Word uyumlu: tablo layout, inline CSS, VML buton yok (basit metin)
    return <<<HTML
<!DOCTYPE html>
<html lang="tr" xmlns="http://www.w3.org/1999/xhtml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="x-apple-disable-message-reformatting">
  <title>btDocs Lisans Hatırlatması</title>
  <!--[if mso]>
  <noscript>
    <xml>
      <o:OfficeDocumentSettings>
        <o:PixelsPerInch>96</o:PixelsPerInch>
      </o:OfficeDocumentSettings>
    </xml>
  </noscript>
  <style>
    table { border-collapse: collapse; }
    td, th { font-family: Arial, Helvetica, sans-serif; }
  </style>
  <![endif]-->
  <style type="text/css">
    body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
    table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
    img { -ms-interpolation-mode: bicubic; border: 0; outline: none; text-decoration: none; }
    body { margin: 0 !important; padding: 0 !important; width: 100% !important; }
  </style>
</head>
<body style="margin:0;padding:0;background-color:#f0f2f5;font-family:Arial,Helvetica,sans-serif;">
  <div style="display:none;font-size:1px;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;mso-hide:all;">
    {$firma} lisansınızın bitişine {$kalan} gün kaldı ({$bitis}).
  </div>

  <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color:#f0f2f5;">
    <tr>
      <td align="center" style="padding:24px 12px;">
        <!--[if mso]>
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="600"><tr><td>
        <![endif]-->
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="max-width:600px;background-color:#ffffff;border:1px solid #e5e7eb;">

          <!-- Header -->
          <tr>
            <td bgcolor="#6b0a1c" style="background-color:#6b0a1c;padding:22px 28px;">
              <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                <tr>
                  <td style="font-family:Arial,Helvetica,sans-serif;font-size:20px;font-weight:bold;color:#ffffff;letter-spacing:-0.3px;">
                    btDocs
                  </td>
                </tr>
                <tr>
                  <td style="padding-top:4px;font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#f3d4da;">
                    Lisans Hatırlatması
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- Accent line -->
          <tr>
            <td bgcolor="#830b24" style="background-color:#830b24;height:4px;font-size:0;line-height:0;">&nbsp;</td>
          </tr>

          <!-- Body -->
          <tr>
            <td style="padding:28px 28px 8px 28px;font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.55;color:#1f2937;">
              <p style="margin:0 0 14px 0;">Sayın Yetkili,</p>
              <p style="margin:0 0 18px 0;">
                <strong style="color:#6b0a1c;">{$firma}</strong> firmasına ait
                <strong>btDocs</strong> lisansınızın süresi yaklaşıyor.
                Kesintisiz kullanım için yenileme sürecini başlatmanızı öneririz.
              </p>
            </td>
          </tr>

          <!-- Info box -->
          <tr>
            <td style="padding:0 28px 24px 28px;">
              <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color:#faf7f8;border:1px solid #ecd6db;">
                <tr>
                  <td style="padding:16px 18px;font-family:Arial,Helvetica,sans-serif;">
                    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                      <tr>
                        <td width="50%" valign="top" style="padding:6px 8px 6px 0;font-size:12px;color:#6b7280;text-transform:uppercase;letter-spacing:0.4px;">
                          Bitiş tarihi
                          <br>
                          <span style="display:inline-block;margin-top:4px;font-size:18px;font-weight:bold;color:#1f2937;text-transform:none;letter-spacing:0;">{$bitis}</span>
                        </td>
                        <td width="50%" valign="top" style="padding:6px 0 6px 8px;font-size:12px;color:#6b7280;text-transform:uppercase;letter-spacing:0.4px;border-left:1px solid #ecd6db;">
                          Kalan süre
                          <br>
                          <span style="display:inline-block;margin-top:4px;font-size:18px;font-weight:bold;color:#6b0a1c;text-transform:none;letter-spacing:0;">{$kalan} gün</span>
                        </td>
                      </tr>
                    </table>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <tr>
            <td style="padding:0 28px 28px 28px;font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.55;color:#4b5563;">
              Yenileme veya sorularınız için aşağıdaki iletişim bilgilerinden bize ulaşabilirsiniz.
            </td>
          </tr>

          <!-- Divider -->
          <tr>
            <td style="padding:0 28px;">
              <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                <tr>
                  <td style="border-top:1px solid #e5e7eb;font-size:0;line-height:0;height:1px;">&nbsp;</td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- Company footer -->
          <tr>
            <td style="padding:22px 28px 26px 28px;background-color:#fafafa;">
              <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                <tr>
                  <td style="font-family:Arial,Helvetica,sans-serif;font-size:13px;font-weight:bold;color:#6b0a1c;line-height:1.45;padding-bottom:8px;">
                    Bolu Bilgi Teknolojileri<br>
                    Sanayi ve Ticaret Limited Şirketi
                  </td>
                </tr>
                <tr>
                  <td style="font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#374151;line-height:1.5;">
                    Tel:
                    <a href="tel:+903746060665" style="color:#6b0a1c;text-decoration:none;font-weight:bold;">0374 606 06 65</a>
                  </td>
                </tr>
                <tr>
                  <td style="padding-top:12px;font-family:Arial,Helvetica,sans-serif;font-size:11px;color:#9ca3af;line-height:1.4;">
                    Bu ileti btDocs Lisans Paneli tarafından otomatik olarak gönderilmiştir.<br>
                    &copy; {$yil} Bolu Bilgi Teknolojileri San. ve Tic. Ltd. Şti.
                  </td>
                </tr>
              </table>
            </td>
          </tr>

        </table>
        <!--[if mso]>
        </td></tr></table>
        <![endif]-->
      </td>
    </tr>
  </table>
</body>
</html>
HTML;
}
