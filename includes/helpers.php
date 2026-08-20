<?php

declare(strict_types=1);

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $path): never
{
    if (!preg_match('~^https?://~i', $path)) {
        $path = rtrim(SITE_URL, '/') . '/' . ltrim($path, '/');
    }
    header('Location: ' . $path);
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . h(csrf_token()) . '">';
}

function csrf_verify(): void
{
    $ok = isset($_POST['csrf'], $_SESSION['csrf'])
        && hash_equals((string) $_SESSION['csrf'], (string) $_POST['csrf']);
    if (!$ok) {
        http_response_code(403);
        exit('Invalid request. Please go back and try again.');
    }
}

function asset_url(string $path): string
{
    $file = dirname(__DIR__) . $path;
    $v = is_file($file) ? (string) filemtime($file) : '1';
    return $path . '?v=' . $v;
}

function is_private_page(): bool
{
    $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
    $base = basename($script);
    if (str_contains($script, '/admin/')) {
        return true;
    }
    return in_array($base, ['editor.php', 'account.php', 'login.php', 'preview.php', 'improve.php', 'capture.php', 'logout.php'], true);
}

function honeypot_field(): string
{
    return '<div class="hp" aria-hidden="true"><label>Leave blank<input type="text" name="website_hp" value="" tabindex="-1" autocomplete="off"></label></div>';
}

function honeypot_tripped(): bool
{
    return trim((string) ($_POST['website_hp'] ?? '')) !== '';
}

function client_ip(): string
{
    $remote = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    $cf = trim((string) ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? ''));
    if ($cf !== '' && filter_var($cf, FILTER_VALIDATE_IP) && function_exists('ip_in_cidrs') && ip_in_cidrs($remote, cloudflare_ip_ranges())) {
        return substr($cf, 0, 45);
    }
    return substr($remote, 0, 45);
}

function now(): int
{
    return time();
}

function iso_now(): string
{
    return gmdate('c');
}

function json_error(string $message, int $code = 400): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => $message]);
    exit;
}

function json_ok(array $data = []): never
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true] + $data);
    exit;
}

function flash_set(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function flash_get(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return is_array($flash) ? $flash : null;
}

function slugify(string $text): string
{
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
    $text = trim($text, '-');
    if (strlen($text) > SLUG_MAX) {
        $text = rtrim(substr($text, 0, SLUG_MAX), '-');
    }
    return $text;
}

function is_valid_slug(string $slug): bool
{
    $len = strlen($slug);
    return $len >= SLUG_MIN && $len <= SLUG_MAX && (bool) preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug);
}

function public_resume_url(string $slug): string
{
    return rtrim(SITE_URL, '/') . '/r/' . rawurlencode($slug);
}

function current_path(): string
{
    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    $path = parse_url($uri, PHP_URL_PATH);
    return is_string($path) ? $path : '/';
}
