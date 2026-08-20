<?php

declare(strict_types=1);

function send_otp_email(string $to, string $code): bool
{
    $subject = 'Your ' . SITE_NAME . ' login code';
    $safeCode = h($code);
    $minutes = (string) (int) (OTP_TTL_SECONDS / 60);
    $html = '<!DOCTYPE html><html><body style="font-family:Georgia,serif;background:#f7f3ea;padding:24px;color:#1a1a1a;">'
        . '<div style="max-width:480px;margin:0 auto;background:#fff;padding:32px;border:1px solid #e6dcc3;">'
        . '<p style="letter-spacing:.18em;text-transform:uppercase;font-size:12px;color:#8a7a4b;margin:0 0 16px;">' . h(SITE_NAME) . '</p>'
        . '<p style="margin:0 0 8px;">Your login code is</p>'
        . '<p style="font-size:32px;letter-spacing:.28em;margin:0 0 16px;font-weight:bold;">' . $safeCode . '</p>'
        . '<p style="color:#555;font-size:14px;margin:0;">It expires in ' . $minutes . ' minutes. If you did not request this, you can ignore the email.</p>'
        . '</div></body></html>';
    $text = "Your " . SITE_NAME . " login code is {$code}. It expires in {$minutes} minutes.";
    return send_mail($to, $subject, $html, $text);
}

function send_mail(string $to, string $subject, string $html, string $text): bool
{
    if (SMTP_HOST !== '' && SMTP_PASS !== '') {
        try {
            return smtp_send($to, $subject, $html, $text);
        } catch (Throwable $e) {
            error_log('SMTP send failed: ' . $e->getMessage());
        }
    }

    $headers = implode("\r\n", [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . sprintf('"%s" <%s>', MAIL_FROM_NAME, MAIL_FROM),
        'Reply-To: ' . MAIL_FROM,
        'X-Mailer: PHP/' . PHP_VERSION,
    ]);
    $params = '-f' . MAIL_FROM;
    return @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $html, $headers, $params);
}

function smtp_send(string $to, string $subject, string $html, string $text): bool
{
    $host = SMTP_HOST;
    $port = SMTP_PORT;
    $secure = strtolower(SMTP_SECURE);
    $prefix = $secure === 'ssl' ? 'ssl://' : 'tcp://';
    $remote = $prefix . $host . ':' . $port;

    $context = stream_context_create([
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
            'allow_self_signed' => false,
        ],
    ]);

    $fp = @stream_socket_client($remote, $errno, $errstr, 20, STREAM_CLIENT_CONNECT, $context);
    if (!$fp) {
        throw new RuntimeException("SMTP connect failed: {$errstr} ({$errno})");
    }
    stream_set_timeout($fp, 20);

    $expect = static function ($fp, int $code) {
        $line = '';
        while (!feof($fp)) {
            $chunk = fgets($fp, 2048);
            if ($chunk === false) {
                break;
            }
            $line .= $chunk;
            if (isset($chunk[3]) && $chunk[3] === ' ') {
                break;
            }
        }
        if (!str_starts_with($line, (string) $code)) {
            throw new RuntimeException('SMTP unexpected reply: ' . trim($line));
        }
        return $line;
    };
    $cmd = static function ($fp, string $data) {
        fwrite($fp, $data . "\r\n");
    };

    $expect($fp, 220);
    $cmd($fp, 'EHLO workisgod.com');
    $expect($fp, 250);

    if ($secure === 'tls') {
        $cmd($fp, 'STARTTLS');
        $expect($fp, 220);
        if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            throw new RuntimeException('STARTTLS failed');
        }
        $cmd($fp, 'EHLO workisgod.com');
        $expect($fp, 250);
    }

    $cmd($fp, 'AUTH LOGIN');
    $expect($fp, 334);
    $cmd($fp, base64_encode(SMTP_USER));
    $expect($fp, 334);
    $cmd($fp, base64_encode(SMTP_PASS));
    $expect($fp, 235);

    $cmd($fp, 'MAIL FROM:<' . MAIL_FROM . '>');
    $expect($fp, 250);
    $cmd($fp, 'RCPT TO:<' . $to . '>');
    $expect($fp, 250);
    $cmd($fp, 'DATA');
    $expect($fp, 354);

    $boundary = 'b' . bin2hex(random_bytes(8));
    $headers = [
        'Date: ' . date('r'),
        'From: "' . MAIL_FROM_NAME . '" <' . MAIL_FROM . '>',
        'To: <' . $to . '>',
        'Subject: =?UTF-8?B?' . base64_encode($subject) . '?=',
        'MIME-Version: 1.0',
        'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        '',
        '--' . $boundary,
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
        '',
        $text,
        '',
        '--' . $boundary,
        'Content-Type: text/html; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
        '',
        $html,
        '',
        '--' . $boundary . '--',
        '.',
    ];
    fwrite($fp, implode("\r\n", $headers) . "\r\n");
    $expect($fp, 250);
    $cmd($fp, 'QUIT');
    fclose($fp);
    return true;
}
