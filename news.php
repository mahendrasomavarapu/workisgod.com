<?php

declare(strict_types=1);

require __DIR__ . '/includes/init.php';

if (!news_enabled()) {
    http_response_code(404);
    render_header('News is closed', [
        'body' => 'page-404',
        'path' => '/news',
        'robots' => 'noindex,nofollow',
        'description' => 'The technical news desk is not open.',
    ]);
    echo '<main id="main" class="wrap prose"><p class="eyebrow">News</p><h1>This desk is closed.</h1>';
    echo '<p class="lede">The technical news room is not taking visitors just now.</p>';
    echo '<p><a class="button" href="/">Return home</a></p></main>';
    render_footer();
    exit;
}

$sectors = news_sectors();
$requested = strtolower(trim((string) ($_GET['sector'] ?? '')));
$sector = news_normalize_sector($requested);

if ($requested !== '' && $sector === '') {
    http_response_code(404);
    render_header('News not found', [
        'body' => 'page-404',
        'path' => '/news.php',
        'robots' => 'noindex,nofollow',
        'description' => 'That news desk does not exist.',
    ]);
    echo '<main id="main" class="wrap prose"><p class="eyebrow">News</p><h1>No such desk.</h1>';
    echo '<p class="lede">Technical news lives under telecom and banking.</p>';
    echo '<p><a class="button" href="/news">All news</a></p></main>';
    render_footer();
    exit;
}

if ($sector === '') {
    $path = '/news';
    $title = 'Technical news';
    $description = 'Latest technical news in telecom and banking: 5G, Open RAN, ISO 20022, FedNow, and core rails. Each item has a highlight summary and a link to the original publisher.';
    render_header($title, [
        'body' => 'page-news',
        'description' => $description,
        'path' => $path,
        'type' => 'CollectionPage',
    ]);
    ?>
<main id="main" class="wrap news-page">
    <header class="news-hero">
        <p class="eyebrow">Desk</p>
        <h1>Technical news.</h1>
        <p class="lede">Two rooms only: telecom and banking. Each story is a highlight plus the publisher’s URL. We pull live RSS from allow-listed sources and keep a short desk of the pieces that actually change how networks and money move.</p>
        <p class="fine">Original articles stay on the publisher’s site. Headlines are cached for about 30 minutes so the page stays light on shared hosting.</p>
    </header>
    <nav class="news-switch" aria-label="News desks">
        <a class="is-on" href="/news">All</a>
        <?php foreach ($sectors as $s): ?>
            <a href="<?= h($s['path']) ?>"><?= h($s['name']) ?></a>
        <?php endforeach; ?>
    </nav>
    <div class="news-sector-grid">
        <?php foreach ($sectors as $s): ?>
            <a class="news-sector-card" href="<?= h($s['path']) ?>">
                <span class="eyebrow"><?= h($s['name']) ?></span>
                <strong><?= h($s['name']) ?> latest</strong>
                <small><?= h($s['blurb']) ?></small>
                <span class="text-link">Open →</span>
            </a>
        <?php endforeach; ?>
    </div>
    <div class="news-home-cols">
        <?php foreach ($sectors as $id => $s): ?>
            <section aria-labelledby="news-<?= h($id) ?>">
                <h2 id="news-<?= h($id) ?>"><?= h($s['name']) ?></h2>
                <?php render_news_list(news_items($id, 6)); ?>
                <p><a class="text-link" href="<?= h($s['path']) ?>">Full <?= h(strtolower($s['name'])) ?> desk →</a></p>
            </section>
        <?php endforeach; ?>
    </div>
</main>
    <?php
    render_footer();
    exit;
}

$meta = $sectors[$sector];
$items = news_items($sector, 16);
$path = $meta['path'];
render_header($meta['name'] . ' news', [
    'body' => 'page-news',
    'description' => 'Latest ' . strtolower($meta['name']) . ' technical news with highlight summaries and links to the original reporting. ' . $meta['blurb'],
    'path' => $path,
    'type' => 'CollectionPage',
    'jsonld' => [
        '@type' => 'ItemList',
        'name' => $meta['name'] . ' technical news',
        'url' => rtrim(SITE_URL, '/') . $path,
        'numberOfItems' => count($items),
        'itemListElement' => array_map(static function (array $item, int $i): array {
            return [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'url' => $item['url'],
                'name' => $item['title'],
                'description' => $item['summary'],
            ];
        }, $items, array_keys($items)),
    ],
]);
?>
<main id="main" class="wrap news-page">
    <header class="news-hero">
        <p class="eyebrow">Technical news</p>
        <h1><?= h($meta['name']) ?>.</h1>
        <p class="lede"><?= h($meta['blurb']) ?> Each item is a highlight, then the reference link. Read the original before you act on it.</p>
    </header>
    <nav class="news-switch" aria-label="News desks">
        <a href="/news">All</a>
        <?php foreach ($sectors as $id => $s): ?>
            <a href="<?= h($s['path']) ?>" class="<?= $id === $sector ? 'is-on' : '' ?>"><?= h($s['name']) ?></a>
        <?php endforeach; ?>
    </nav>
    <?php render_news_list($items); ?>
    <p class="fine">Wire items come from publisher RSS. Desk notes are selected technical developments with a citation. Neither is investment, legal, or operational advice.</p>
</main>
<?php
render_footer();
