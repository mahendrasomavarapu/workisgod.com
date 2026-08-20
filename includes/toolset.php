<?php

declare(strict_types=1);

/**
 * Catalog of the public, browser-only operations bench.
 * All twenty tools run in the visitor’s browser. Nothing is posted to the server.
 *
 * @return list<array{id:string,name:string,cli:string,blurb:string}>
 */
function ops_tools(): array
{
    return [
        ['id' => 'json', 'name' => 'JSON', 'cli' => 'jq', 'blurb' => 'Format, validate, and minify JSON.'],
        ['id' => 'base64', 'name' => 'Base64', 'cli' => 'base64', 'blurb' => 'Encode or decode Base64, including URL-safe.'],
        ['id' => 'url', 'name' => 'URL encode', 'cli' => 'printf %s | jq -sRr @uri', 'blurb' => 'Percent-encode and decode URLs and form values.'],
        ['id' => 'hash', 'name' => 'Hash / HMAC', 'cli' => 'shasum / openssl dgst', 'blurb' => 'SHA-1, SHA-256, SHA-512, MD5, and HMAC.'],
        ['id' => 'uuid', 'name' => 'UUID', 'cli' => 'uuidgen', 'blurb' => 'Mint UUID v4 identifiers, one or a handful.'],
        ['id' => 'time', 'name' => 'Timestamp', 'cli' => 'date +%s', 'blurb' => 'Unix seconds, milliseconds, ISO-8601, and relative time.'],
        ['id' => 'jwt', 'name' => 'JWT decode', 'cli' => 'jq after cut -d. -f2', 'blurb' => 'Inspect header and payload. Signature is not verified.'],
        ['id' => 'regex', 'name' => 'Regex', 'cli' => 'grep -E / perl -pe', 'blurb' => 'Test a pattern, list groups, and run a replace.'],
        ['id' => 'diff', 'name' => 'Diff', 'cli' => 'diff -u', 'blurb' => 'Line-level unified diff of two texts.'],
        ['id' => 'query', 'name' => 'URL / query', 'cli' => 'python -m urllib.parse', 'blurb' => 'Split a URL or query string into parts and params.'],
        ['id' => 'cron', 'name' => 'Cron', 'cli' => 'crontab', 'blurb' => 'Read a 5-field expression and the next fire times.'],
        ['id' => 'http', 'name' => 'HTTP codes', 'cli' => 'man http', 'blurb' => 'Look up status codes used in APIs and curl output.'],
        ['id' => 'cidr', 'name' => 'CIDR', 'cli' => 'ipcalc / sipcalc', 'blurb' => 'Network, mask, hosts, and wildcard for IPv4 prefixes.'],
        ['id' => 'password', 'name' => 'Secrets', 'cli' => 'openssl rand -base64', 'blurb' => 'Generate passwords and random tokens locally.'],
        ['id' => 'html', 'name' => 'HTML entities', 'cli' => 'recode / python html', 'blurb' => 'Escape and unescape HTML, XML, and numeric entities.'],
        ['id' => 'case', 'name' => 'Case / slug', 'cli' => 'tr / sed', 'blurb' => 'Upper, lower, title, snake, kebab, camel, and URL slug.'],
        ['id' => 'hex', 'name' => 'Hex', 'cli' => 'xxd / hexdump', 'blurb' => 'UTF-8 text to hex and back, with a byte dump.'],
        ['id' => 'bases', 'name' => 'Number bases', 'cli' => 'printf / bc', 'blurb' => 'Convert among binary, octal, decimal, and hex.'],
        ['id' => 'pem', 'name' => 'PEM decoder', 'cli' => 'openssl x509 -text', 'blurb' => 'Read certificates and keys: type, fingerprint, subject.'],
        ['id' => 'curl', 'name' => 'HTTP / curl', 'cli' => 'curl -i', 'blurb' => 'Build a curl command and send it from this browser.'],
    ];
}

function render_tools_promo(string $heading = 'The ops bench, in the browser.'): void
{
    ?>
    <section class="tools-promo" aria-labelledby="tools-promo-h">
        <div class="tools-promo-copy">
            <p class="eyebrow">Tools</p>
            <h2 id="tools-promo-h"><?= h($heading) ?></h2>
            <p>Twenty stand-ins for curl, jq, openssl, base64, date, and uuidgen. They run in your browser. We do not receive the text.</p>
            <p><a class="text-link" href="/tools.php">Open the full bench →</a></p>
        </div>
        <div class="tools-grid">
            <?php foreach (ops_tools() as $tool): ?>
                <a class="tool-card" href="/tools.php#<?= h($tool['id']) ?>">
                    <span class="cli"><code><?= h($tool['cli']) ?></code></span>
                    <strong><?= h($tool['name']) ?></strong>
                    <small><?= h($tool['blurb']) ?></small>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php
}
