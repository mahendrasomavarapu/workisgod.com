<?php

declare(strict_types=1);

function capture_from_url(string $url): array
{
    $url = trim($url);
    if ($url === '') {
        throw new InvalidArgumentException('Enter a LinkedIn or other public profile URL.');
    }
    if (!preg_match('#^https?://#i', $url)) {
        $url = 'https://' . $url;
    }
    $parts = parse_url($url);
    if (!$parts || empty($parts['scheme']) || empty($parts['host'])) {
        throw new InvalidArgumentException('That URL is not valid.');
    }
    $scheme = strtolower((string) $parts['scheme']);
    if (!in_array($scheme, ['http', 'https'], true)) {
        throw new InvalidArgumentException('Only http and https URLs are allowed.');
    }
    $host = strtolower((string) $parts['host']);
    if (!is_public_hostname($host)) {
        throw new InvalidArgumentException('That host cannot be fetched.');
    }

    $isLinkedIn = (bool) preg_match('/(^|\.)linkedin\.com$/i', $host);
    $label = $isLinkedIn ? 'LinkedIn' : $host;
    $text = '';
    $via = '';

    if ($isLinkedIn) {
        try {
            $text = fetch_url_text('https://r.jina.ai/' . $url, 25);
            $via = 'reader';
        } catch (Throwable $e) {
            error_log('Jina LinkedIn fetch failed: ' . $e->getMessage());
        }
        if (capture_too_thin($text) || looks_like_login_wall($text)) {
            try {
                $direct = fetch_url_text($url, 20);
                if (!capture_too_thin($direct) && !looks_like_login_wall($direct)) {
                    $text = $direct;
                    $via = 'direct';
                }
            } catch (Throwable $e) {
                error_log('Direct LinkedIn fetch failed: ' . $e->getMessage());
            }
        }
    } else {
        try {
            $text = fetch_url_text($url, 20);
            $via = 'direct';
        } catch (Throwable $e) {
            error_log('Direct URL fetch failed: ' . $e->getMessage());
        }
        if (capture_too_thin($text)) {
            try {
                $text = fetch_url_text('https://r.jina.ai/' . $url, 25);
                $via = 'reader';
            } catch (Throwable $e) {
                if ($text === '') {
                    throw $e;
                }
            }
        }
    }

    $text = clean_captured_text($text);
    if (looks_like_login_wall($text) || capture_too_thin($text)) {
        throw new RuntimeException(
            $isLinkedIn
                ? 'LinkedIn blocked the public page. Open the profile, use Save as PDF, then upload that PDF here.'
                : 'Could not read enough text from that URL. Try a public page or upload a PDF.'
        );
    }
    return ['text' => $text, 'label' => $label, 'via' => $via];
}

function capture_from_pdf(string $path, string $filename = 'upload.pdf'): array
{
    if (!is_file($path)) {
        throw new InvalidArgumentException('Could not read the PDF.');
    }
    $size = filesize($path);
    if ($size === false || $size < 100) {
        throw new InvalidArgumentException('That PDF is empty.');
    }
    if ($size > 6 * 1024 * 1024) {
        throw new InvalidArgumentException('PDF is too large. Keep it under 6 MB.');
    }
    $binary = file_get_contents($path);
    if ($binary === false || strncmp($binary, '%PDF', 4) !== 0) {
        throw new InvalidArgumentException('Upload a valid PDF file.');
    }
    $text = pdf_binary_to_text($binary);
    $text = clean_captured_text($text);
    if (capture_too_thin($text)) {
        throw new RuntimeException('Could not extract text from that PDF. Try a text-based PDF (not a scan).');
    }
    return ['text' => $text, 'label' => $filename !== '' ? $filename : 'PDF', 'via' => 'pdf'];
}

function is_public_hostname(string $host): bool
{
    if ($host === 'localhost' || str_ends_with($host, '.localhost')) {
        return false;
    }
    $ips = @gethostbynamel($host) ?: [];
    if (!$ips) {
        $packed = @inet_pton($host);
        if ($packed !== false) {
            $ips = [$host];
        }
    }
    if (!$ips) {
        return false;
    }
    foreach ($ips as $ip) {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }
    }
    return true;
}

function fetch_url_text(string $url, int $timeout = 20): string
{
    if (!function_exists('curl_init')) {
        throw new RuntimeException('PHP curl is required to fetch URLs.');
    }
    $ch = curl_init($url);
    if ($ch === false) {
        throw new RuntimeException('Could not start the fetch.');
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 4,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; WorkIsGodBot/1.0; +https://workisgod.com/)',
        CURLOPT_HTTPHEADER => [
            'Accept: text/html,application/xhtml+xml,application/pdf,text/plain;q=0.9,*/*;q=0.8',
        ],
        CURLOPT_MAXFILESIZE => 2_000_000,
    ]);
    $raw = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $ctype = strtolower((string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE));
    $err = curl_error($ch);
    curl_close($ch);
    if ($raw === false) {
        throw new RuntimeException($err !== '' ? $err : 'Fetch failed');
    }
    if ($code >= 400) {
        throw new RuntimeException('Could not open that page (HTTP ' . $code . ').');
    }
    $raw = (string) $raw;
    if (strlen($raw) > 1_800_000) {
        $raw = substr($raw, 0, 1_800_000);
    }
    if (str_contains($ctype, 'pdf') || strncmp($raw, '%PDF', 4) === 0) {
        return pdf_binary_to_text($raw);
    }
    if (str_contains($ctype, 'json')) {
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? capture_flatten($decoded) : $raw;
    }
    return html_to_visible_text($raw);
}

function html_to_visible_text(string $html): string
{
    $html = preg_replace('#<script\b[^>]*>.*?</script>#is', ' ', $html) ?? $html;
    $html = preg_replace('#<style\b[^>]*>.*?</style>#is', ' ', $html) ?? $html;
    $html = preg_replace('#<noscript\b[^>]*>.*?</noscript>#is', ' ', $html) ?? $html;
    $bits = [];
    if (preg_match('/<meta[^>]+property=["\']og:title["\'][^>]+content=["\']([^"\']+)/i', $html, $m)) {
        $bits[] = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    if (preg_match('/<meta[^>]+property=["\']og:description["\'][^>]+content=["\']([^"\']+)/i', $html, $m)) {
        $bits[] = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    if (preg_match_all('#<script[^>]+type=["\']application/ld\+json["\'][^>]*>(.*?)</script>#is', $html, $ld)) {
        foreach ($ld[1] as $json) {
            $data = json_decode(html_entity_decode(trim($json), ENT_QUOTES | ENT_HTML5, 'UTF-8'), true);
            if (is_array($data)) {
                $bits[] = capture_flatten($data);
            }
        }
    }
    $html = preg_replace('#<(br|p|div|li|h1|h2|h3|h4|tr|section)/?>#i', "\n", $html) ?? $html;
    $bits[] = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return implode("\n", $bits);
}

function capture_flatten(array $data): string
{
    $keep = ['name', 'headline', 'title', 'description', 'jobTitle', 'worksFor', 'alumniOf', 'address', 'email', 'telephone', 'url', 'knowsAbout', 'hasOccupation'];
    $out = [];
    $walk = static function ($node) use (&$walk, &$out, $keep): void {
        if (!is_array($node)) {
            return;
        }
        foreach ($node as $k => $v) {
            if (is_array($v)) {
                $walk($v);
                continue;
            }
            if (is_string($k) && in_array((string) $k, $keep, true) && is_string($v) && trim($v) !== '') {
                $out[] = $k . ': ' . trim($v);
            } elseif (is_string($v) && strlen($v) > 40 && strlen($v) < 400) {
                $out[] = trim($v);
            }
        }
    };
    $walk($data);
    return implode("\n", array_unique($out));
}

function pdf_binary_to_text(string $binary): string
{
    $chunks = [];
    if (preg_match_all('/stream\s*\r?\n(.*?)endstream/s', $binary, $matches)) {
        foreach ($matches[1] as $stream) {
            $decoded = @gzuncompress($stream);
            if ($decoded === false) {
                $decoded = @gzinflate($stream);
            }
            if ($decoded === false) {
                $decoded = $stream;
            }
            $chunks[] = pdf_strings_from($decoded);
        }
    }
    $chunks[] = pdf_strings_from($binary);
    return implode("\n", $chunks);
}

function pdf_strings_from(string $data): string
{
    $out = [];
    if (preg_match_all('/\\((?:\\\\.|[^\\\\)])*\\)/', $data, $m)) {
        foreach ($m[0] as $lit) {
            $s = substr($lit, 1, -1);
            $s = str_replace(['\\n', '\\r', '\\t', '\\(', '\\)', '\\\\'], ["\n", "\r", "\t", '(', ')', '\\'], $s);
            if (trim($s) !== '') {
                $out[] = $s;
            }
        }
    }
    if (preg_match_all('/<([0-9A-Fa-f]{4,})>/', $data, $hex)) {
        foreach ($hex[1] as $h) {
            if (strlen($h) % 2 === 1) {
                $h .= '0';
            }
            $raw = @hex2bin($h);
            if ($raw === false) {
                continue;
            }
            if (str_starts_with($raw, "\xfe\xff") && function_exists('mb_convert_encoding')) {
                $raw = mb_convert_encoding(substr($raw, 2), 'UTF-8', 'UTF-16BE') ?: '';
            }
            $raw = preg_replace('/[^\P{C}\n]+/u', '', $raw) ?? $raw;
            if (trim($raw) !== '') {
                $out[] = $raw;
            }
        }
    }
    return implode(' ', $out);
}

function clean_captured_text(string $text): string
{
    $text = str_replace("\x00", '', $text);
    $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
    $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;
    $lines = [];
    foreach (preg_split("/\r\n|\n|\r/", $text) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || strlen($line) > 500) {
            if ($line !== '' && strlen($line) > 500) {
                $lines[] = substr($line, 0, 500);
            }
            continue;
        }
        $lines[] = $line;
    }
    $text = implode("\n", $lines);
    if (strlen($text) > 25000) {
        $text = substr($text, 0, 25000);
    }
    return trim($text);
}

function capture_too_thin(string $text): bool
{
    $plain = preg_replace('/\s+/', '', $text) ?? '';
    return strlen($plain) < 180;
}

function looks_like_login_wall(string $text): bool
{
    $t = strtolower($text);
    $hits = 0;
    foreach (['join linkedin', 'sign in', 'agree & join', 'authwall', 'login to linkedin', 'create an account'] as $needle) {
        if (str_contains($t, $needle)) {
            $hits++;
        }
    }
    return $hits >= 2 && strlen($text) < 2500;
}
