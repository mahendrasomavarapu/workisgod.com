<?php

declare(strict_types=1);

require __DIR__ . '/includes/init.php';

$tools = ops_tools();

render_header('Tools', [
    'body' => 'page-tools',
    'description' => 'Twenty browser-based operations tools: JSON, Base64, hashes, JWT, regex, CIDR, curl builder, PEM decoder, and more. They run on your device. Nothing is sent to the server.',
    'path' => '/tools.php',
    'type' => 'WebPage',
    'jsonld' => [
        '@type' => 'ItemList',
        'name' => 'Work is God operations tools',
        'numberOfItems' => count($tools),
        'itemListElement' => array_map(static function (array $tool, int $i): array {
            return [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => $tool['name'],
                'url' => rtrim(SITE_URL, '/') . '/tools.php#' . $tool['id'],
                'description' => $tool['blurb'],
            ];
        }, $tools, array_keys($tools)),
    ],
]);
?>
<main id="main" class="wrap tools-page">
    <header class="tools-hero">
        <p class="eyebrow">Operations</p>
        <h1>Twenty tools, in the browser.</h1>
        <p class="lede">Daily stand-ins for <code>curl</code>, <code>jq</code>, <code>openssl</code>, <code>base64</code>, <code>date</code>, and <code>uuidgen</code>. They run in this tab. We do not receive your input. Use them on public data you already trust.</p>
        <label class="sr-only" for="tool-search">Filter tools</label>
        <input id="tool-search" type="search" placeholder="Filter tools — json, curl, cidr…" autocomplete="off">
        <p class="fine">Private keys, production tokens, and session cookies stay off this page. The HTTP tool can only reach sites that allow CORS from a browser.</p>
    </header>

    <noscript>
        <p class="alert-box">These tools need JavaScript. Enable it, or use the matching command-line program listed on each card.</p>
    </noscript>

    <div class="tools-layout">
        <nav class="tools-nav" id="tools-nav" aria-label="Tools">
            <?php foreach ($tools as $tool): ?>
                <a href="#<?= h($tool['id']) ?>" data-tool="<?= h($tool['id']) ?>">
                    <strong><?= h($tool['name']) ?></strong>
                    <span><?= h($tool['cli']) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
        <div class="tools-workspace" id="tools-workspace">
            <?php foreach ($tools as $i => $tool): ?>
                <section
                    id="<?= h($tool['id']) ?>"
                    class="tool-panel<?= $i === 0 ? ' is-on' : '' ?>"
                    data-tool="<?= h($tool['id']) ?>"
                    <?= $i === 0 ? '' : 'hidden' ?>
                >
                    <p class="eyebrow"><?= h($tool['cli']) ?></p>
                    <h2><?= h($tool['name']) ?></h2>
                    <p class="hint"><?= h($tool['blurb']) ?></p>
                    <div class="tool-body" data-mount="<?= h($tool['id']) ?>"></div>
                </section>
            <?php endforeach; ?>
        </div>
    </div>
</main>
<?php
render_footer(['scripts' => ['/assets/js/tools.js']]);
