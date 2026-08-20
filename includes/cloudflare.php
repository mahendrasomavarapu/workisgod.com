<?php

declare(strict_types=1);

function captcha_enabled(): bool
{
    return TURNSTILE_SITE_KEY !== '' && TURNSTILE_SECRET !== '';
}

function behind_cloudflare(): bool
{
    return !empty($_SERVER['HTTP_CF_RAY']) && !empty($_SERVER['HTTP_CF_CONNECTING_IP']);
}

function captcha_widget(): string
{
    if (!captcha_enabled()) {
        return '';
    }
    return '<div class="cf-turnstile captcha-box" data-sitekey="' . h(TURNSTILE_SITE_KEY) . '" data-theme="auto"></div>';
}

function captcha_verify(): string
{
    if (!captcha_enabled()) {
        return '';
    }
    $token = trim((string) ($_POST['cf-turnstile-response'] ?? ''));
    if ($token === '') {
        return 'Complete the captcha to continue.';
    }
    if (!function_exists('curl_init')) {
        return 'Captcha could not be checked (curl missing).';
    }
    $ch = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
    if ($ch === false) {
        return 'Captcha could not be checked.';
    }
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_POSTFIELDS => http_build_query([
            'secret' => TURNSTILE_SECRET,
            'response' => $token,
            'remoteip' => client_ip(),
        ]),
    ]);
    $raw = curl_exec($ch);
    curl_close($ch);
    $data = is_string($raw) ? json_decode($raw, true) : null;
    if (!is_array($data) || empty($data['success'])) {
        return 'Captcha failed. Refresh and try again.';
    }
    return '';
}

function ip_in_cidrs(string $ip, array $cidrs): bool
{
    $packed = @inet_pton($ip);
    if ($packed === false) {
        return false;
    }
    $v6 = strlen($packed) === 16;
    foreach ($cidrs as $cidr) {
        [$net, $bits] = array_pad(explode('/', $cidr, 2), 2, $v6 ? '128' : '32');
        $netPacked = @inet_pton($net);
        if ($netPacked === false || strlen($netPacked) !== strlen($packed)) {
            continue;
        }
        $bits = (int) $bits;
        $bytes = intdiv($bits, 8);
        $remain = $bits % 8;
        if ($bytes && substr($packed, 0, $bytes) !== substr($netPacked, 0, $bytes)) {
            continue;
        }
        if ($remain) {
            $mask = (~0 << (8 - $remain)) & 255;
            if ((ord($packed[$bytes]) & $mask) !== (ord($netPacked[$bytes]) & $mask)) {
                continue;
            }
        }
        return true;
    }
    return false;
}

function cloudflare_ip_ranges(): array
{
    return [
        '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22',
        '141.101.64.0/18', '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20',
        '197.234.240.0/22', '198.41.128.0/17', '162.158.0.0/15', '104.16.0.0/13',
        '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
        '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32',
        '2405:8100::/32', '2a06:98c0::/29', '2c0f:f248::/32',
    ];
}
