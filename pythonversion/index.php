<?php
/**
 * Namecheap front door for the Python edition.
 * Runs app.cgi with python3 when LiteSpeed CGI is not enabled.
 */
declare(strict_types=1);

$root = __DIR__;
$script = $root . '/app.cgi';
$pythons = [
    '/opt/alt/python312/bin/python3',
    '/opt/alt/python311/bin/python3',
    '/opt/alt/python310/bin/python3',
    '/opt/alt/python39/bin/python3',
    '/opt/alt/python38/bin/python3',
    '/usr/bin/python3',
    'python3',
];

$disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
$can_proc = function_exists('proc_open') && !in_array('proc_open', $disabled, true);

if (!$can_proc) {
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Python edition</title></head><body>';
    echo '<h1>Python edition</h1><p>PHP cannot start Python (<code>proc_open</code> is disabled). ';
    echo 'Enable CGI for <code>app.cgi</code> or use cPanel → Setup Python App with startup file <code>passenger_wsgi.py</code>.</p>';
    echo '</body></html>';
    exit;
}

$python = null;
foreach ($pythons as $bin) {
    if ($bin[0] === '/' && is_executable($bin)) {
        $python = $bin;
        break;
    }
}
if ($python === null) {
    $python = 'python3';
}

$env = [];
foreach ($_SERVER as $k => $v) {
    if (is_string($v) || is_int($v) || is_float($v)) {
        $env[(string) $k] = (string) $v;
    }
}
$env['PATH'] = getenv('PATH') ?: '/usr/local/bin:/usr/bin:/bin';
$env['HOME'] = getenv('HOME') ?: '/home/workqojj';
$env['LANG'] = getenv('LANG') ?: 'en_US.UTF-8';
$env['PYTHONPATH'] = $root;
$env['PYTHONUNBUFFERED'] = '1';
$env['GATEWAY_INTERFACE'] = 'CGI/1.1';
$env['SCRIPT_FILENAME'] = $script;
$env['SCRIPT_NAME'] = '/pythonversion/app.cgi';
$env['REDIRECT_STATUS'] = '200';
$uri = (string) ($_SERVER['REQUEST_URI'] ?? '/pythonversion/');
$env['REQUEST_URI'] = $uri;
$path = (string) (parse_url($uri, PHP_URL_PATH) ?: '/pythonversion/');
$prefix = '/pythonversion';
$info = '/';
if (str_starts_with($path, $prefix)) {
    $rest = substr($path, strlen($prefix));
    $info = ($rest === '' || $rest === false) ? '/' : $rest;
}
$env['PATH_INFO'] = $info === '' ? '/' : $info;
$env['QUERY_STRING'] = (string) ($_SERVER['QUERY_STRING'] ?? '');
$env['REQUEST_METHOD'] = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    $env['HTTPS'] = 'on';
}

$stdin = file_get_contents('php://input') ?: '';
$env['CONTENT_LENGTH'] = (string) strlen($stdin);
if (!empty($_SERVER['CONTENT_TYPE'])) {
    $env['CONTENT_TYPE'] = (string) $_SERVER['CONTENT_TYPE'];
}

$spec = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
$proc = @proc_open([$python, $script], $spec, $pipes, $root, $env);
if (!is_resource($proc)) {
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    echo '<h1>Python edition</h1><p>Could not start <code>' . htmlspecialchars($python) . '</code>.</p>';
    exit;
}

fwrite($pipes[0], $stdin);
fclose($pipes[0]);
$out = stream_get_contents($pipes[1]);
$err = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
$exit = proc_close($proc);

if ($err !== '') {
    error_log('[pythonversion] ' . $err);
}

$headers = '';
$body = (string) $out;
if (preg_match('/^(HTTP\/|Status:|Content-Type:)/i', $body)) {
    $parts = preg_split("/\r\n\r\n|\n\n/", $body, 2);
    if (is_array($parts) && count($parts) === 2) {
        $headers = $parts[0];
        $body = $parts[1];
    }
}

$status = 200;
foreach (preg_split("/\r\n|\n/", $headers) as $line) {
    $line = trim($line);
    if ($line === '') {
        continue;
    }
    if (stripos($line, 'Status:') === 0) {
        $status = (int) trim(substr($line, 7));
        continue;
    }
    if (stripos($line, 'HTTP/') === 0) {
        $bits = explode(' ', $line, 3);
        if (isset($bits[1])) {
            $status = (int) $bits[1];
        }
        continue;
    }
    header($line, false);
}

if ($body === '' && ($exit !== 0 || $err !== '')) {
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Python edition error</title></head><body>';
    echo '<h1>Python edition error</h1><pre>' . htmlspecialchars($err !== '' ? $err : 'python exited ' . $exit) . '</pre>';
    echo '</body></html>';
    exit;
}

http_response_code($status > 0 ? $status : 200);
echo $body;
