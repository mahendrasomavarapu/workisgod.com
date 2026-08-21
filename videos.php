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
    echo '<p class="lede">The prince’s video door is not receiving visitors just now.</p>';
    echo '<p><a class="button" href="/">Return home</a></p></main>';
    render_footer();
    exit;
}

$items = videos_items();
$public = [];
foreach ($items as $item) {
    $public[] = [
        'title' => (string) ($item['title'] ?? 'Video'),
        'platform' => (string) ($item['platform'] ?? ''),
        'embed' => (string) ($item['embed'] ?? ''),
        'watch' => (string) ($item['watch'] ?? ''),
    ];
    if (count($public) >= videos_max()) {
        break;
    }
}

render_header('Videos', [
    'body' => 'page-videos',
    'description' => 'A royal door of open-web video embeds: YouTube, Vimeo, Dailymotion, and more. Nothing is downloaded. Up to 1,000 links slide in an infinite loop.',
    'path' => '/videos',
    'type' => 'CollectionPage',
]);
?>
<main id="main" class="wrap videos-page">
    <header class="videos-hero">
        <p class="eyebrow">Gallery</p>
        <h1>The prince’s door.</h1>
        <p class="lede">Each film stays on its home platform. We only embed. The reel holds at most 1,000 links, then the loop starts again — forever, one door at a time.</p>
    </header>

    <script type="application/json" id="video-playlist"><?= str_replace('</', '<\/', (string) json_encode($public, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?></script>
    <div id="video-deck" class="video-deck" data-interval="12000">
        <div class="royal-door" aria-live="polite">
            <div class="royal-lintel">
                <span>Work is God</span>
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
                    </div>
                </div>
                <span class="royal-post royal-post-r" aria-hidden="true"></span>
            </div>
            <div class="royal-sill">
                <p id="video-title" class="royal-title">Loading the door…</p>
                <p class="fine">Embed only. The file never touches this server.</p>
            </div>
        </div>
        <div class="video-controls">
            <button type="button" id="video-prev" class="secondary">Previous</button>
            <button type="button" id="video-toggle">Pause</button>
            <button type="button" id="video-next" class="secondary">Next</button>
            <a id="video-watch" class="text-link" href="#" rel="noopener noreferrer" target="_blank">Open on the platform →</a>
        </div>
    </div>

    <p class="fine videos-note">Platforms include YouTube, Vimeo, Dailymotion, Facebook, Instagram, TikTok, Twitch, and the Internet Archive — whatever public embed the source allows. Auto-advance is muted. Use Pause if you would rather stay.</p>
</main>
<?php
render_footer(['scripts' => ['/assets/js/videos.js']]);
