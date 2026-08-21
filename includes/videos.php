<?php

declare(strict_types=1);

/**
 * Public video door: embed-only links from open platforms.
 * Nothing is downloaded or stored as media — only titles and embed URLs.
 */

function videos_max(): int
{
    return 1000;
}

function videos_enabled(): bool
{
    return setting('videos_enabled', '1') === '1';
}

function videos_default_sources(): string
{
    return implode("\n", [
        'https://www.youtube.com/feeds/videos.xml?channel_id=UCAuUUnT6oDeKwE6v1NGQxug',
        'https://www.youtube.com/feeds/videos.xml?channel_id=UCLA_DiR1FfKNvjuUpBHmylQ',
        'https://www.youtube.com/feeds/videos.xml?channel_id=UCsXVk37bltHxD1rDPwtNM8Q',
        'https://www.youtube.com/feeds/videos.xml?channel_id=UCHnyfMqiRRG1u-2MsSQLbXA',
        'https://www.youtube.com/feeds/videos.xml?channel_id=UC4a-Gbdw7vOaccHmFo40b9g',
        'https://www.youtube.com/feeds/videos.xml?channel_id=UC8butISFwT-Wl7EV0hUK0BQ',
        'https://www.youtube.com/feeds/videos.xml?channel_id=UCYO_jab_esuFRV4b17AKv3w',
        'https://www.youtube.com/feeds/videos.xml?channel_id=UC7_gcs09iThXybpVgjHZ_7g',
        'https://www.youtube.com/feeds/videos.xml?channel_id=UC6nSFpj9HTCZ5t-N3RmMg1w',
        'https://www.youtube.com/feeds/videos.xml?channel_id=UCEIwxahdLz7v8J5PVdoBfng',
        'https://www.youtube.com/feeds/videos.xml?channel_id=UCvjgXvBlbQiydffZU7m1_aw',
        'https://www.youtube.com/feeds/videos.xml?channel_id=UC9-y-6csu5WGmIcKI-x9Bhg',
        'https://www.youtube.com/feeds/videos.xml?channel_id=UC6107grRI4m0o2-emgoDnAA',
        'https://www.youtube.com/feeds/videos.xml?channel_id=UC6n8I1UDTKP1IWjQMg6_TwA',
        'https://www.youtube.com/feeds/videos.xml?channel_id=UCV6mNrW8FrmUKtF8HJ9q8gQ',
        'https://vimeo.com/channels/staffpicks/videos/rss',
        'https://vimeo.com/channels/documentaryfilm/videos/rss',
        'https://www.dailymotion.com/rss/user/dailymotion',
        'https://www.dailymotion.com/rss/user/NASA',
        'https://www.dailymotion.com/rss/user/tedtalks',
    ]);
}

function videos_source_text(): string
{
    $saved = trim(setting('video_sources', ''));
    return $saved !== '' ? $saved : videos_default_sources();
}

function videos_parse_admin_sources(string $text): array
{
    $urls = [];
    foreach (preg_split('/\R/u', $text) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (!preg_match('#^https?://#i', $line)) {
            $line = 'https://' . $line;
        }
        $url = news_normalize_source_url($line);
        if ($url === '' || in_array($url, $urls, true)) {
            continue;
        }
        $urls[] = $url;
        if (count($urls) >= 40) {
            break;
        }
    }
    return $urls;
}

function videos_source_list(): array
{
    return videos_parse_admin_sources(videos_source_text());
}

function videos_cache_path(): string
{
    return rtrim(DATA_DIR, '/') . '/videos-cache.json';
}

function videos_read_cache(): array
{
    $path = videos_cache_path();
    if (!is_file($path)) {
        return ['fetched_at' => 0, 'items' => []];
    }
    $raw = @file_get_contents($path);
    $data = is_string($raw) ? json_decode($raw, true) : null;
    if (!is_array($data) || !isset($data['items']) || !is_array($data['items'])) {
        return ['fetched_at' => 0, 'items' => []];
    }
    return $data;
}

function videos_write_cache(array $items, int $fetchedAt = 0): void
{
    $dir = dirname(videos_cache_path());
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $payload = json_encode([
        'fetched_at' => $fetchedAt > 0 ? $fetchedAt : time(),
        'items' => array_values($items),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($payload !== false) {
        @file_put_contents(videos_cache_path(), $payload, LOCK_EX);
    }
}

function videos_items(): array
{
    $items = videos_read_cache()['items'] ?? [];
    if ($items) {
        return array_slice($items, 0, videos_max());
    }
    return array_slice(videos_bootstrap(), 0, videos_max());
}

function videos_refresh_report(): array
{
    $raw = setting('videos_refresh_report', '');
    $data = $raw !== '' ? json_decode($raw, true) : null;
    if (is_array($data)) {
        return $data;
    }
    $cache = videos_read_cache();
    return [
        'at' => (int) ($cache['fetched_at'] ?? 0),
        'count' => count($cache['items'] ?? []),
        'ok' => 0,
        'failed' => [],
    ];
}

function videos_embed_of(string $url, string $title = ''): ?array
{
    $url = trim($url);
    if ($url === '' || !preg_match('#^https://#i', $url)) {
        return null;
    }
    $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?? ''));
    $host = preg_replace('/^www\./', '', $host) ?? $host;
    $path = (string) (parse_url($url, PHP_URL_PATH) ?? '');
    $query = [];
    parse_str((string) (parse_url($url, PHP_URL_QUERY) ?? ''), $query);

    $item = null;
    if (str_contains($host, 'youtube.com') || $host === 'youtu.be' || str_contains($host, 'youtube-nocookie.com')) {
        $id = '';
        if ($host === 'youtu.be') {
            $id = trim($path, '/');
        } elseif (!empty($query['v'])) {
            $id = (string) $query['v'];
        } elseif (preg_match('#/(?:embed|shorts|live|v)/([A-Za-z0-9_-]{11})#', $path, $m)) {
            $id = $m[1];
        }
        $id = substr($id, 0, 11);
        if (preg_match('/^[A-Za-z0-9_-]{11}$/', $id)) {
            $short = str_contains($path, '/shorts/');
            $item = [
                'platform' => 'youtube',
                'id' => $id,
                'kind' => $short ? 'short' : 'video',
                'thumb' => 'https://i.ytimg.com/vi/' . $id . '/mqdefault.jpg',
                'watch' => $short
                    ? 'https://www.youtube.com/shorts/' . $id
                    : 'https://www.youtube.com/watch?v=' . $id,
                'embed' => 'https://www.youtube-nocookie.com/embed/' . $id,
            ];
        }
    } elseif (str_contains($host, 'vimeo.com') && preg_match('#/(\d{6,12})#', $path, $m)) {
        $id = $m[1];
        $item = [
            'platform' => 'vimeo',
            'id' => $id,
            'kind' => 'video',
            'thumb' => '',
            'watch' => 'https://vimeo.com/' . $id,
            'embed' => 'https://player.vimeo.com/video/' . $id,
        ];
    } elseif (str_contains($host, 'dailymotion.com') && preg_match('#/(?:video|embed/video)/([a-zA-Z0-9]+)#', $path, $m)) {
        $id = $m[1];
        $item = [
            'platform' => 'dailymotion',
            'id' => $id,
            'kind' => 'video',
            'thumb' => 'https://www.dailymotion.com/thumbnail/video/' . $id,
            'watch' => 'https://www.dailymotion.com/video/' . $id,
            'embed' => 'https://www.dailymotion.com/embed/video/' . $id,
        ];
    } elseif ((str_contains($host, 'facebook.com') || $host === 'fb.watch') && preg_match('#(/videos/\d+|/watch/?|/reel/|/share/)#', $path . $url)) {
        $watch = strtok($url, '?') ?: $url;
        $item = [
            'platform' => 'facebook',
            'id' => substr(sha1($watch), 0, 12),
            'kind' => str_contains($path, '/reel/') ? 'short' : 'video',
            'thumb' => '',
            'watch' => $watch,
            'embed' => 'https://www.facebook.com/plugins/video.php?href=' . rawurlencode($watch) . '&show_text=false',
        ];
    } elseif (str_contains($host, 'instagram.com') && preg_match('#/(p|reel|tv)/([A-Za-z0-9_-]+)#', $path, $m)) {
        $code = $m[2];
        $item = [
            'platform' => 'instagram',
            'id' => $code,
            'kind' => $m[1] === 'p' ? 'video' : 'short',
            'thumb' => '',
            'watch' => 'https://www.instagram.com/' . $m[1] . '/' . $code . '/',
            'embed' => 'https://www.instagram.com/' . $m[1] . '/' . $code . '/embed',
        ];
    } elseif (str_contains($host, 'tiktok.com') && preg_match('#/video/(\d+)#', $path, $m)) {
        $id = $m[1];
        $item = [
            'platform' => 'tiktok',
            'id' => $id,
            'kind' => 'short',
            'thumb' => '',
            'watch' => $url,
            'embed' => 'https://www.tiktok.com/embed/v2/' . $id,
        ];
    } elseif ($host === 'archive.org' && preg_match('#/details/([^/]+)#', $path, $m)) {
        $id = rawurlencode($m[1]);
        $item = [
            'platform' => 'archive',
            'id' => $m[1],
            'kind' => 'video',
            'thumb' => '',
            'watch' => 'https://archive.org/details/' . $m[1],
            'embed' => 'https://archive.org/embed/' . $id,
        ];
    } elseif (str_contains($host, 'twitch.tv') && preg_match('#/videos/(\d+)#', $path, $m)) {
        $id = $m[1];
        $parent = (string) (parse_url(SITE_URL, PHP_URL_HOST) ?: 'workisgod.com');
        $item = [
            'platform' => 'twitch',
            'id' => $id,
            'kind' => 'video',
            'thumb' => '',
            'watch' => 'https://www.twitch.tv/videos/' . $id,
            'embed' => 'https://player.twitch.tv/?video=' . $id . '&parent=' . rawurlencode($parent) . '&autoplay=false',
        ];
    }

    if (!$item) {
        return null;
    }
    $item['kind'] = $item['kind'] ?? 'video';
    $item['thumb'] = $item['thumb'] ?? '';
    $item['title'] = $title !== '' ? $title : ($item['platform'] . ' · ' . $item['id']);
    $item['published'] = 0;
    return $item;
}

function videos_bootstrap(): array
{
    $seed = [
        ['https://www.youtube.com/watch?v=8jPQjjsBbIc', 'NASA: Earth from ISS'],
        ['https://www.youtube.com/watch?v=hY7m5jjJ9mM', 'Kurzgesagt: The immune system'],
        ['https://www.youtube.com/watch?v=zSgiXGELjbc', 'TED: How to spot a liar'],
        ['https://www.youtube.com/watch?v=w7ejDZ8SWv8', 'Traversy: React crash course'],
        ['https://www.youtube.com/watch?v=aircAruvnKk', '3Blue1Brown: Neural networks'],
        ['https://www.youtube.com/shorts/aircAruvnKk', 'Neural nets short'],
        ['https://www.youtube.com/shorts/hY7m5jjJ9mM', 'Immune short'],
    ];
    $out = [];
    foreach ($seed as [$url, $title]) {
        $item = videos_embed_of($url, $title);
        if ($item) {
            $out[] = $item;
        }
    }
    return $out;
}

function videos_force_refresh(): array
{
    @set_time_limit(55);
    $sources = videos_source_list();
    $failed = [];
    $ok = 0;
    $found = [];

    $round1 = [];
    foreach ($sources as $src) {
        $direct = videos_embed_of($src);
        if ($direct) {
            $found[] = $direct;
            $ok++;
            continue;
        }
        $rss = videos_source_to_rss($src);
        $round1[] = ['src' => $src, 'fetch' => $rss !== '' ? $rss : $src];
    }

    $bodies = $round1 ? news_http_multi(array_column($round1, 'fetch')) : [];
    $follow = [];
    foreach ($round1 as $job) {
        $body = $bodies[$job['fetch']] ?? '';
        if ($body === '') {
            $failed[] = $job['src'];
            continue;
        }
        if (news_looks_like_feed($body)) {
            $got = videos_from_feed($body);
            if ($got) {
                $ok++;
                foreach ($got as $item) {
                    $found[] = $item;
                }
            } else {
                $failed[] = $job['src'];
            }
            continue;
        }
        $extra = videos_from_html($body, $job['src']);
        $rssLinks = news_feeds_from_html($body, $job['src']);
        if ($extra) {
            $ok++;
            foreach ($extra as $item) {
                $found[] = $item;
            }
        }
        foreach (array_slice($rssLinks, 0, 2) as $rssUrl) {
            $follow[] = ['src' => $job['src'], 'fetch' => $rssUrl];
        }
        if (!$extra && !$rssLinks) {
            $guess = videos_guess_rss($job['src']);
            if ($guess !== '') {
                $follow[] = ['src' => $job['src'], 'fetch' => $guess];
            } else {
                $failed[] = $job['src'];
            }
        }
    }

    if ($follow) {
        $more = news_http_multi(array_column($follow, 'fetch'));
        $parentOk = [];
        foreach ($follow as $job) {
            $xml = $more[$job['fetch']] ?? '';
            if ($xml === '') {
                continue;
            }
            $got = news_looks_like_feed($xml) ? videos_from_feed($xml) : videos_from_html($xml, $job['fetch']);
            if (!$got) {
                continue;
            }
            $parentOk[$job['src']] = true;
            foreach ($got as $item) {
                $found[] = $item;
            }
        }
        $ok += count($parentOk);
        foreach ($follow as $job) {
            if (empty($parentOk[$job['src']]) && !in_array($job['src'], $failed, true)) {
                $failed[] = $job['src'];
            }
        }
    }

    $merged = videos_merge_pool($found, videos_read_cache()['items'] ?? []);
    if (!$merged) {
        $merged = videos_bootstrap();
    }
    videos_write_cache($merged);
    $report = [
        'at' => time(),
        'count' => count($merged),
        'ok' => $ok,
        'failed' => array_values(array_unique($failed)),
    ];
    setting_set('videos_refresh_report', (string) json_encode($report, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    return $report;
}

function videos_source_to_rss(string $url): string
{
    $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?? ''));
    $path = (string) (parse_url($url, PHP_URL_PATH) ?? '');
    $query = [];
    parse_str((string) (parse_url($url, PHP_URL_QUERY) ?? ''), $query);
    if (str_contains($host, 'youtube.com')) {
        if (str_contains($path, '/feeds/videos.xml')) {
            return $url;
        }
        if (!empty($query['list'])) {
            return 'https://www.youtube.com/feeds/videos.xml?playlist_id=' . rawurlencode((string) $query['list']);
        }
        if (preg_match('#/channel/(UC[\w-]{20,})#', $path, $m)) {
            return 'https://www.youtube.com/feeds/videos.xml?channel_id=' . $m[1];
        }
        if (preg_match('#/playlist#', $path) && !empty($query['list'])) {
            return 'https://www.youtube.com/feeds/videos.xml?playlist_id=' . rawurlencode((string) $query['list']);
        }
        return '';
    }
    if (str_contains($host, 'vimeo.com')) {
        if (str_ends_with($path, '/rss') || str_ends_with($path, '/videos/rss')) {
            return $url;
        }
        if (preg_match('#/channels/([^/]+)#', $path, $m)) {
            return 'https://vimeo.com/channels/' . rawurlencode($m[1]) . '/videos/rss';
        }
    }
    if (str_contains($host, 'dailymotion.com')) {
        if (str_contains($path, '/rss/')) {
            return $url;
        }
        if (preg_match('#^/([^/]+)/?$#', $path, $m) && $m[1] !== 'video') {
            return 'https://www.dailymotion.com/rss/user/' . rawurlencode($m[1]);
        }
    }
    return '';
}

function videos_guess_rss(string $url): string
{
    $converted = videos_source_to_rss($url);
    if ($converted !== '') {
        return $converted;
    }
    $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?? ''));
    if (str_contains($host, 'youtube.com') && preg_match('#/@([^/]+)#', (string) parse_url($url, PHP_URL_PATH))) {
        return $url;
    }
    return '';
}

function videos_from_feed(string $xml): array
{
    $items = [];
    $chunks = [];
    if (preg_match_all('#<item\b[\s\S]*?</item>#i', $xml, $m)) {
        $chunks = array_merge($chunks, $m[0]);
    }
    if (preg_match_all('#<entry\b[\s\S]*?</entry>#i', $xml, $m)) {
        $chunks = array_merge($chunks, $m[0]);
    }
    foreach ($chunks as $chunk) {
        $title = trim(html_entity_decode(strip_tags(news_inner_tag($chunk, 'title')), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $link = news_extract_link($chunk);
        $yt = '';
        if (preg_match('#<yt:videoId>([^<]+)</yt:videoId>#i', $chunk, $m)) {
            $yt = trim($m[1]);
        }
        if ($yt !== '') {
            $link = 'https://www.youtube.com/watch?v=' . $yt;
        }
        $item = videos_embed_of($link, $title);
        if ($item) {
            $items[] = $item;
        }
    }
    return $items;
}

function videos_from_html(string $html, string $base): array
{
    $items = [];
    if (preg_match('/"channelId":"(UC[\w-]{20,})"/', $html, $m) || preg_match('/channelId["\s:]+"(UC[\w-]{20,})"/', $html, $m)) {
        $rss = news_http_multi(['https://www.youtube.com/feeds/videos.xml?channel_id=' . $m[1]]);
        $xml = $rss['https://www.youtube.com/feeds/videos.xml?channel_id=' . $m[1]] ?? '';
        if ($xml !== '') {
            $items = array_merge($items, videos_from_feed($xml));
        }
    }
    if (preg_match_all('#/shorts/([A-Za-z0-9_-]{11})#', $html, $m)) {
        foreach (array_unique($m[1]) as $id) {
            $item = videos_embed_of('https://www.youtube.com/shorts/' . $id);
            if ($item) {
                $items[] = $item;
            }
        }
    }
    if (preg_match_all('#(?:https://(?:www\.)?(?:youtube\.com/watch\?v=|youtu\.be/)|/watch\?v=)([A-Za-z0-9_-]{11})#', $html, $m)) {
        foreach (array_unique($m[1]) as $id) {
            $item = videos_embed_of('https://www.youtube.com/watch?v=' . $id);
            if ($item) {
                $items[] = $item;
            }
            if (count($items) >= 80) {
                break;
            }
        }
    }
    if (preg_match_all('#https://(?:player\.)?vimeo\.com/(?:video/)?(\d{6,12})#', $html, $m)) {
        foreach (array_unique($m[1]) as $id) {
            $item = videos_embed_of('https://vimeo.com/' . $id);
            if ($item) {
                $items[] = $item;
            }
        }
    }
    if (preg_match_all('#https://(?:www\.)?dailymotion\.com/video/([a-zA-Z0-9]+)#', $html, $m)) {
        foreach (array_unique($m[1]) as $id) {
            $item = videos_embed_of('https://www.dailymotion.com/video/' . $id);
            if ($item) {
                $items[] = $item;
            }
        }
    }
    unset($base);
    return $items;
}

function videos_merge_pool(array $fresh, array $old): array
{
    $seen = [];
    $out = [];
    foreach (array_merge($fresh, $old) as $item) {
        if (!is_array($item)) {
            continue;
        }
        $embed = (string) ($item['embed'] ?? '');
        if ($embed === '' || isset($seen[$embed])) {
            continue;
        }
        $seen[$embed] = true;
        $out[] = [
            'platform' => (string) ($item['platform'] ?? 'web'),
            'id' => (string) ($item['id'] ?? ''),
            'kind' => (($item['kind'] ?? '') === 'short') ? 'short' : 'video',
            'thumb' => (string) ($item['thumb'] ?? ''),
            'title' => (string) ($item['title'] ?? 'Video'),
            'watch' => (string) ($item['watch'] ?? $embed),
            'embed' => $embed,
        ];
        if (count($out) >= videos_max()) {
            break;
        }
    }
    return $out;
}

function videos_csp_frame_src(): string
{
    return "'self' https://www.youtube.com https://www.youtube-nocookie.com https://player.vimeo.com https://www.dailymotion.com https://geo.dailymotion.com https://www.facebook.com https://staticxx.facebook.com https://www.instagram.com https://www.tiktok.com https://archive.org https://player.twitch.tv https://challenges.cloudflare.com";
}

function render_videos_promo(): void
{
    $n = count(videos_items());
    ?>
    <section class="videos-promo" aria-labelledby="videos-promo-h">
        <div class="videos-promo-copy">
            <p class="eyebrow">The prince’s door</p>
            <h2 id="videos-promo-h">Open-web video, framed, never stored.</h2>
            <p>YouTube, Vimeo, Dailymotion, and the rest play as embeds only. We do not download a single file. Up to 1,000 links slide one by one, then the loop begins again.</p>
            <p><a class="text-link" href="/videos">Open the video door →</a><?php if ($n): ?> <span class="fine"> · <?= (int) $n ?> on the reel</span><?php endif; ?></p>
        </div>
        <a class="royal-door royal-door-mini" href="/videos" aria-label="Open the video door">
            <span class="royal-lintel">Work is God</span>
            <span class="royal-well"></span>
            <span class="royal-sill">Enter</span>
        </a>
    </section>
    <?php
}
