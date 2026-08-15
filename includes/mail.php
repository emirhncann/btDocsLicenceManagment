<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

/**
 * Basit SMTP gönderici (PHPMailer / Composer gerekmez).
 * STARTTLS (587) ve ssl:// (465) destekler.
 */
function sendMail(string $to, string $subject, string $htmlBody, ?string $textBody = null): void
{
    $host = trim((string) ($_ENV['SMTP_HOST'] ?? ''));
    $port = (int) ($_ENV['SMTP_PORT'] ?? 587);
    $user = trim((string) ($_ENV['SMTP_USER'] ?? ''));
    $pass = (string) ($_ENV['SMTP_PASS'] ?? '');
    $fromEmail = trim((string) ($_ENV['SMTP_FROM_EMAIL'] ?? ''));
    $fromName = trim((string) ($_ENV['SMTP_FROM_NAME'] ?? 'btDocs Lisans'));

    if ($host === '' || $fromEmail === '') {
        throw new RuntimeException('SMTP ayarları eksik (SMTP_HOST, SMTP_FROM_EMAIL). config/.env kontrol edin.');
    }

    if ($textBody === null) {
        $textBody = trim(html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], "\n", $htmlBody)), ENT_QUOTES, 'UTF-8'));
    }

    $enc = strtolower(trim((string) ($_ENV['SMTP_ENCRYPTION'] ?? '')));
    if ($enc === '') {
        $enc = $port === 465 ? 'ssl' : 'tls';
    }

    $remote = ($enc === 'ssl' ? 'ssl://' : '') . $host;
    $errno = 0;
    $errstr = '';
    $socket = @stream_socket_client(
        "{$remote}:{$port}",
        $errno,
        $errstr,
        30,
        STREAM_CLIENT_CONNECT
    );

    if (!$socket) {
        throw new RuntimeException("SMTP bağlantısı kurulamadı: {$errstr} ({$errno})");
    }

    stream_set_timeout($socket, 30);

    try {
        smtpExpect($socket, [220]);
        smtpCommand($socket, 'EHLO lisans-panel.local', [250]);

        if ($enc === 'tls') {
            smtpCommand($socket, 'STARTTLS', [220]);
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('STARTTLS başarısız.');
            }
            smtpCommand($socket, 'EHLO lisans-panel.local', [250]);
        }

        if ($user !== '') {
            smtpCommand($socket, 'AUTH LOGIN', [334]);
            smtpCommand($socket, base64_encode($user), [334]);
            smtpCommand($socket, base64_encode($pass), [235]);
        }

        smtpCommand($socket, 'MAIL FROM:<' . $fromEmail . '>', [250]);
        smtpCommand($socket, 'RCPT TO:<' . $to . '>', [250, 251]);
        smtpCommand($socket, 'DATA', [354]);

        $boundary = 'b_' . bin2hex(random_bytes(8));
        $headers = [
            'Date: ' . date('r'),
            'From: ' . smtpEncodeAddress($fromEmail, $fromName),
            'To: <' . $to . '>',
            'Subject: ' . smtpEncodeHeader($subject),
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        ];

        $body = implode("\r\n", $headers) . "\r\n\r\n";
        $body .= '--' . $boundary . "\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= chunk_split(base64_encode($textBody)) . "\r\n";
        $body .= '--' . $boundary . "\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= chunk_split(base64_encode($htmlBody)) . "\r\n";
        $body .= '--' . $boundary . "--\r\n";

        // DATA içindeki satır başı noktaları kaçır
        $body = preg_replace('/^\./m', '..', $body) ?? $body;

        fwrite($socket, $body . "\r\n.\r\n");
        smtpExpect($socket, [250]);
        smtpCommand($socket, 'QUIT', [221, 250]);
    } finally {
        fclose($socket);
    }
}

function smtpEncodeAddress(string $email, string $name): string
{
    if ($name === '') {
        return '<' . $email . '>';
    }
    return smtpEncodeHeader($name) . ' <' . $email . '>';
}

function smtpEncodeHeader(string $value): string
{
    if (preg_match('/^[\x20-\x7E]*$/', $value)) {
        return $value;
    }
    return '=?UTF-8?B?' . base64_encode($value) . '?=';
}

/** @param resource $socket */
function smtpCommand($socket, string $command, array $okCodes): void
{
    fwrite($socket, $command . "\r\n");
    smtpExpect($socket, $okCodes);
}

/** @param resource $socket @param list<int> $okCodes */
function smtpExpect($socket, array $okCodes): string
{
    $response = '';
    while (($line = fgets($socket, 515)) !== false) {
        $response .= $line;
        if (isset($line[3]) && $line[3] === ' ') {
            break;
        }
    }

    $code = (int) substr($response, 0, 3);
    if (!in_array($code, $okCodes, true)) {
        throw new RuntimeException('SMTP beklenmeyen yanıt (' . $code . '): ' . trim($response));
    }

    return $response;
}
