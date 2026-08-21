<?php

declare(strict_types=1);

require __DIR__ . '/includes/init.php';

if (!videos_enabled()) {
    http_response_code(404);
    render_header('Video door is closed', [
        'body' => 'page-404',
        'path' => '/videos',
        'robots' => 'noindex,nofollow',
        'description' => 'The video door is not open.',
    ]);
    echo '<main id="main" class="wrap prose"><p class="eyebrow">Videos</p><h1>This door is closed.</h1>';
    echo '<p><a class="button" href="/">Return home</a></p></main>';
    render_footer();
    exit;
}

$items = videos_items();
$public = [];
foreach ($items as $item) {
    $public[] = [
        'platform' => (string) ($item['platform'] ?? ''),
        'id' => (string) ($item['id'] ?? ''),
        'kind' => (($item['kind'] ?? '') === 'short') ? 'short' : 'video',
        'thumb' => (string) ($item['thumb'] ?? ''),
        'embed' => (string) ($item['embed'] ?? ''),
        'watch' => (string) ($item['watch'] ?? ''),
    ];
    if (count($public) >= videos_max()) {
        break;
    }
}

render_header('Videos', [
    'body' => 'page-videos',
    'description' => 'Swipe a royal door of open-web video embeds. Left/right next film, up changes provider, down opens shorts. Nothing is downloaded.',
    'path' => '/videos',
    'type' => 'CollectionPage',
]);
?>
<main id="main" class="wrap videos-page">
    <script type="application/json" id="video-playlist"><?= str_replace('</', '<\/', (string) json_encode($public, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?></script>
    <div id="video-deck" class="video-deck" data-interval="12000">
        <div class="royal-door" aria-live="polite">
            <div class="royal-lintel">
                <span id="video-provider">all</span>
                <span id="video-mode" class="royal-mode" hidden>shorts</span>
                <span class="royal-count" id="video-count">0 / 0</span>
            </div>
            <div class="royal-posts">
                <span class="royal-post royal-post-l" aria-hidden="true"></span>
                <div class="royal-well">
                    <div class="royal-glass">
                        <iframe
                            id="video-frame"
                            title="Embedded video"
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; fullscreen"
                            allowfullscreen
                            referrerpolicy="strict-origin-when-cross-origin"
                            loading="lazy"
                        ></iframe>
                        <div id="video-swipe" class="royal-swipe" aria-hidden="true"></div>
                    </div>
                </div>
                <span class="royal-post royal-post-r" aria-hidden="true"></span>
            </div>
            <div class="royal-sill">
                <div id="video-thumbs" class="video-thumbs" role="listbox" aria-label="Videos"></div>
            </div>
        </div>
    </div>
</main>
<?php
render_footer(['scripts' => ['/assets/js/videos.js']]);
