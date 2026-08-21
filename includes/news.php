<?php

declare(strict_types=1);

/**
 * Technical news desk for telecom and banking.
 * Live headlines come from allow-listed publisher RSS. Editorial notes
 * stay in the repo so the page still works if a feed is down.
 */

function news_sectors(): array
{
    return [
        'telecom' => [
            'id' => 'telecom',
            'name' => 'Telecom',
            'blurb' => '5G SA, Open RAN, fiber, spectrum, and telco AI — from the operators and the labs.',
            'path' => '/news/telecom',
        ],
        'banking' => [
            'id' => 'banking',
            'name' => 'Banking',
            'blurb' => 'ISO 20022, instant payments, core modernisation, and bank-grade rails.',
            'path' => '/news/banking',
        ],
    ];
}

function news_enabled(): bool
{
    return setting('news_enabled', '1') === '1';
}

function news_default_site_text(string $sector): string
{
    if ($sector === 'telecom') {
        return "https://rcrwireless.com/feed\nhttps://www.lightreading.com/rss.xml\nhttps://www.gsma.com/feed/";
    }
    return "https://www.finextra.com/rss/headlines.aspx\nhttps://www.finextra.com/rss/channel.aspx?channel=payments\nhttps://www.federalreserve.gov/feeds/press_all.xml";
}

function news_site_text(string $sector): string
{
    $sector = news_normalize_sector($sector);
    if ($sector === '') {
        return '';
    }
    $saved = trim(setting('news_' . $sector . '_sites', ''));
    return $saved !== '' ? $saved : news_default_site_text($sector);
}

function news_parse_site_lines(string $text): array
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
        if (count($urls) >= 12) {
            break;
        }
    }
    return $urls;
}

function news_normalize_source_url(string $url): string
{
    $url = trim($url);
    $parts = parse_url($url);
    if (!$parts || empty($parts['host'])) {
        return '';
    }
    $scheme = strtolower((string) ($parts['scheme'] ?? 'https'));
    if ($scheme !== 'https') {
        return '';
    }
    $host = strtolower((string) $parts['host']);
    if ($host === '' || isset($parts['user']) || isset($parts['pass'])) {
        return '';
    }
    if (!empty($parts['port']) && (int) $parts['port'] !== 443) {
        return '';
    }
    if (!is_public_hostname($host)) {
        return '';
    }
    $path = $parts['path'] ?? '/';
    if ($path === '') {
        $path = '/';
    }
    $built = 'https://' . $host . $path;
    if (!empty($parts['query'])) {
        $built .= '?' . $parts['query'];
    }
    return $built;
}

function news_source_label(string $url): string
{
    $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?? ''));
    $host = preg_replace('/^www\./', '', $host) ?? $host;
    return $host !== '' ? $host : 'source';
}

function news_feeds(): array
{
    $out = ['telecom' => [], 'banking' => []];
    foreach (['telecom', 'banking'] as $sector) {
        foreach (news_parse_site_lines(news_site_text($sector)) as $url) {
            $out[$sector][] = [
                'url' => $url,
                'source' => news_source_label($url),
            ];
        }
    }
    return $out;
}

function news_refresh_report(): array
{
    $raw = setting('news_refresh_report', '');
    if ($raw === '') {
        $cache = news_read_cache();
        $at = is_array($cache) ? (int) ($cache['fetched_at'] ?? 0) : 0;
        return [
            'at' => $at,
            'telecom' => is_array($cache) ? count($cache['sectors']['telecom'] ?? []) : 0,
            'banking' => is_array($cache) ? count($cache['sectors']['banking'] ?? []) : 0,
            'ok' => 0,
            'failed' => [],
        ];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : ['at' => 0, 'telecom' => 0, 'banking' => 0, 'ok' => 0, 'failed' => []];
}

function news_editorial(): array
{
    return [
        [
            'sector' => 'telecom',
            'title' => 'EE launches UK commercial 5G SA network slicing',
            'summary' => 'BT’s mobile arm opened Fast Lane, a paid 5G Standalone slice for consumers and small firms, on a network it says reaches 54 million people (78% of the UK). Slicing is no longer only a lab demo in Britain.',
            'url' => 'https://www.lightreading.com/5g/eurobites-ee-puts-5g-in-fast-lane-with-network-slicing',
            'source' => 'Light Reading',
            'published' => strtotime('2026-08-20'),
            'kind' => 'desk',
        ],
        [
            'sector' => 'telecom',
            'title' => 'EE Fast Lane: a priced 5G slice, not a slogan',
            'summary' => 'The Register’s read of the same launch: Fast Lane is a premium monthly add-on that reserves a priority lane on 5G SA. That is the commercial test of whether slicing can actually bill.',
            'url' => 'https://www.theregister.com/networks/2026/08/21/ee-invites-mobile-users-to-live-life-in-the-5g-fast-lane-for-a-price/',
            'source' => 'The Register',
            'published' => strtotime('2026-08-21'),
            'kind' => 'desk',
        ],
        [
            'sector' => 'telecom',
            'title' => 'Dell’Oro: RAN grew again in 2Q 2026',
            'summary' => 'A third consecutive quarter of modest RAN growth. Open RAN, cloud RAN, and multi-vendor RAN are no longer one story — Dell’Oro now splits their paths, with multi-vendor share still expected to stay small this decade.',
            'url' => 'https://www.lightreading.com/open-ran/ran-market-growth-continues-in-2q-2026-dell-oro',
            'source' => 'Light Reading / Dell’Oro',
            'published' => strtotime('2026-08-18'),
            'kind' => 'desk',
        ],
        [
            'sector' => 'telecom',
            'title' => 'O-RAN Alliance: 59 specs since March, AI-RAN in the hall',
            'summary' => 'Work groups published 59 technical documents since March 2026 (157 unique titles). The 2026 brief is consolidation on live 5G, not a greenfield 6G rewrite. Operators at MWC talked industrial-scale AI-RAN, not more trials.',
            'url' => 'https://www.o-ran.org/announcements',
            'source' => 'O-RAN Alliance',
            'published' => strtotime('2026-08-01'),
            'kind' => 'desk',
        ],
        [
            'sector' => 'telecom',
            'title' => 'KT ships an on-prem NPU LLM station for sovereign telco AI',
            'summary' => 'Korea Telecom boxed a Korean NPU, model, and platform that does not need a public cloud. That is the direction operators want for RAN intelligence and customer models that cannot leave the country.',
            'url' => 'https://rcrwireless.com/20260820/ai/kt-npu-llm-station',
            'source' => 'RCR Wireless',
            'published' => strtotime('2026-08-20'),
            'kind' => 'desk',
        ],
        [
            'sector' => 'telecom',
            'title' => 'Open RAN’s 2026 score: Palau yes, global share still thin',
            'summary' => 'The Quad is backing a first commercial Open RAN build in Palau for early 2027. GSMA and Omdia still show slow operator conversion; Dell’Oro now puts multi-vendor RAN under 5% of the market by 2030. Performance, not press releases, is the gate.',
            'url' => 'https://www.orfonline.org/expert-speak/open-ran-2026-the-gap-between-promise-and-practice',
            'source' => 'ORF',
            'published' => strtotime('2026-08-18'),
            'kind' => 'desk',
        ],
        [
            'sector' => 'banking',
            'title' => 'HSBC and Standard Chartered settle live on Swift’s blockchain ledger',
            'summary' => 'The first live tokenised-deposit transaction on Swift’s new ledger ran between two G-SIBs. The point is not a new island chain — it is Swift keeping the correspondent network while changing the settlement fabric underneath.',
            'url' => 'https://www.finextra.com/newsarticle/48270/hsbc-and-standard-chartered-execute-first-live-transaction-on-swifts-blockchain-based-ledger',
            'source' => 'Finextra',
            'published' => strtotime('2026-08-19'),
            'kind' => 'desk',
        ],
        [
            'sector' => 'banking',
            'title' => 'November 2026: ISO 20022 unstructured addresses are turned off',
            'summary' => 'Swift CBPR+ drops unstructured postal addresses this November. Hybrid (town + country) was the 2025 bridge; native MX with structured or hybrid address is now the bar. Payments that still carry a free-text address will fail screening and STP.',
            'url' => 'https://www.swift.com/news-events/news/iso-20022-milestone-november-2026-unstructured-addresses-be-removed',
            'source' => 'Swift',
            'published' => strtotime('2026-06-15'),
            'kind' => 'desk',
        ],
        [
            'sector' => 'banking',
            'title' => 'Fedwire is on ISO 20022; CHIPS and Swift close the loop in November',
            'summary' => 'Fedwire Funds moved in July 2025. By November 2026 the three large-value US/cross-border systems — Fedwire, CHIPS, and Swift CBPR+ — are expected to run native ISO 20022 only. This is a data-model change, not a coat of XML.',
            'url' => 'https://www.frbservices.org/news/fed360/issues/120225/wires-iso-20022-new-era-global-payments-infrastructure',
            'source' => 'Federal Reserve Financial Services',
            'published' => strtotime('2025-12-02'),
            'kind' => 'desk',
        ],
        [
            'sector' => 'banking',
            'title' => 'FedNow: 1,800 institutions, volume up 85% quarter-on-quarter',
            'summary' => 'Seven of the ten largest US banks are on the rail. About half of US checking and savings accounts can now send or receive. The Fed is piloting request-for-payment and the US leg of cross-border; uptake still needs use cases, not only connectivity.',
            'url' => 'https://www.paymentsdive.com/news/fednow-advances-over-hurdles/825071/',
            'source' => 'Payments Dive',
            'published' => strtotime('2026-07-13'),
            'kind' => 'desk',
        ],
        [
            'sector' => 'banking',
            'title' => 'StanChart issues $200m digitally native notes on Euroclear D-FMI',
            'summary' => 'A three-year floating-rate digitally native note from a G-SIB, issued on Euroclear’s digital FMI. That is post-trade market infrastructure, not a marketing NFT — settlement, not a side chain demo.',
            'url' => 'https://www.finextra.com/pressarticle/110698/standard-chartered-issues-digitally-native-notes-on-euroclear-d-fmi',
            'source' => 'Finextra',
            'published' => strtotime('2026-08-20'),
            'kind' => 'desk',
        ],
        [
            'sector' => 'banking',
            'title' => 'Barclays and Deutsche Bank take Ant’s AI cash-flow model',
            'summary' => 'Ant International’s forecasting model is in use at large correspondent banks for cash-flow and FX liquidity. Treasury AI is leaving the slide deck and sitting next to nostro balances.',
            'url' => 'https://www.finextra.com/newsarticle/48280/global-banks-sign-for-ant-international-ai-forecasting-model',
            'source' => 'Finextra',
            'published' => strtotime('2026-08-21'),
            'kind' => 'desk',
        ],
        [
            'sector' => 'banking',
            'title' => 'Monzo failed over to a backup bank during an app outage',
            'summary' => 'Thousands could not open the app; Monzo switched to an independent backup bank and restored service the same afternoon. Operational resilience is now a product feature, not only a regulatory memo.',
            'url' => 'https://www.finextra.com/newsarticle/48272/monzo-deploys-backup-bank-in-face-of-outage',
            'source' => 'Finextra',
            'published' => strtotime('2026-08-19'),
            'kind' => 'desk',
        ],
    ];
}

function news_cache_path(): string
{
    return rtrim(DATA_DIR, '/') . '/news-cache.json';
}

function news_items(string $sector, int $limit = 12, bool $fetch = true): array
{
    $sector = news_normalize_sector($sector);
    if ($sector === '') {
        return [];
    }
    $bundle = news_bundle($fetch);
    $items = $bundle[$sector] ?? [];
    if ($limit > 0) {
        $items = array_slice($items, 0, $limit);
    }
    return $items;
}

function news_normalize_sector(string $sector): string
{
    $sector = strtolower(trim($sector));
    return isset(news_sectors()[$sector]) ? $sector : '';
}

function news_bundle(bool $fetch = true): array
{
    static $memo = null;
    if (is_array($memo)) {
        return $memo;
    }
    $cached = news_read_cache();
    $fresh = is_array($cached) && (($cached['fetched_at'] ?? 0) > (time() - 1800));
    $stale = is_array($cached) ? ($cached['sectors'] ?? []) : [];
    if ($fresh || !$fetch) {
        $memo = news_merge_editorial($stale);
        return $memo;
    }
    $live = news_refresh_live($stale);
    $memo = news_merge_editorial($live);
    return $memo;
}

function news_read_cache(): ?array
{
    $path = news_cache_path();
    if (!is_file($path)) {
        return null;
    }
    $raw = @file_get_contents($path);
    if (!is_string($raw) || $raw === '') {
        return null;
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : null;
}

function news_write_cache(array $sectors): void
{
    $payload = json_encode([
        'fetched_at' => time(),
        'sectors' => $sectors,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($payload === false) {
        return;
    }
    $path = news_cache_path();
    $dir = dirname($path);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    @file_put_contents($path, $payload, LOCK_EX);
}

function news_force_refresh(): array
{
    @set_time_limit(50);
    $live = news_refresh_live([], true);
    return news_refresh_report();
}

function news_refresh_live(array $stale, bool $force = false): array
{
    $jobs = [];
    foreach (news_feeds() as $sector => $feeds) {
        foreach ($feeds as $feed) {
            $jobs[] = $feed + ['sector' => $sector];
        }
    }
    $failed = [];
    $ok = 0;
    $out = ['telecom' => [], 'banking' => []];
    $bodies = news_http_multi(array_column($jobs, 'url'));
    $follow = [];
    foreach ($jobs as $job) {
        $url = $job['url'];
        $body = $bodies[$url] ?? '';
        if ($body === '') {
            $failed[] = $url;
            continue;
        }
        if (news_looks_like_feed($body)) {
            $parsed = news_parse_feed($body, $job['source'], $job['sector']);
            if ($parsed) {
                $ok++;
                foreach ($parsed as $item) {
                    $out[$job['sector']][] = $item;
                }
            } else {
                $failed[] = $url;
            }
            continue;
        }
        $found = news_feeds_from_html($body, $url);
        if (!$found) {
            $found = news_guess_feed_urls($url);
        }
        $found = array_slice($found, 0, 2);
        if (!$found) {
            $failed[] = $url;
            continue;
        }
        foreach ($found as $feedUrl) {
            $follow[] = [
                'url' => $feedUrl,
                'source' => $job['source'],
                'sector' => $job['sector'],
                'parent' => $url,
            ];
        }
    }
    if ($follow) {
        $more = news_http_multi(array_column($follow, 'url'));
        $parentOk = [];
        foreach ($follow as $job) {
            $xml = $more[$job['url']] ?? '';
            if ($xml === '' || !news_looks_like_feed($xml)) {
                continue;
            }
            $parsed = news_parse_feed($xml, $job['source'], $job['sector']);
            if (!$parsed) {
                continue;
            }
            $parentOk[$job['parent']] = true;
            foreach ($parsed as $item) {
                $out[$job['sector']][] = $item;
            }
        }
        $ok += count($parentOk);
        foreach ($follow as $job) {
            if (empty($parentOk[$job['parent']]) && !in_array($job['parent'], $failed, true)) {
                $failed[] = $job['parent'];
            }
        }
    }
    foreach ($out as $sector => $items) {
        if (!$force && !$items && !empty($stale[$sector])) {
            $out[$sector] = $stale[$sector];
        } else {
            $out[$sector] = news_dedupe($items);
        }
    }
    news_write_cache($out);
    setting_set('news_refresh_report', (string) json_encode([
        'at' => time(),
        'telecom' => count($out['telecom']),
        'banking' => count($out['banking']),
        'ok' => $ok,
        'failed' => array_values(array_unique($failed)),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    return $out;
}

function news_looks_like_feed(string $body): bool
{
    $head = ltrim(substr($body, 0, 800));
    return (bool) preg_match('/<(rss|feed|rdf:RDF)\b/i', $head)
        || (str_contains($body, '<channel') && str_contains($body, '<item'));
}

function news_absolutize(string $base, string $href): string
{
    $href = trim(html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    if ($href === '') {
        return '';
    }
    if (preg_match('#^https://#i', $href)) {
        return news_normalize_source_url($href);
    }
    if (str_starts_with($href, '//')) {
        return news_normalize_source_url('https:' . $href);
    }
    $parts = parse_url($base);
    if (!$parts || empty($parts['host'])) {
        return '';
    }
    $origin = 'https://' . strtolower((string) $parts['host']);
    if (str_starts_with($href, '/')) {
        return news_normalize_source_url($origin . $href);
    }
    $dir = $parts['path'] ?? '/';
    $dir = preg_replace('#/[^/]*$#', '/', $dir) ?? '/';
    return news_normalize_source_url($origin . $dir . $href);
}

function news_feeds_from_html(string $html, string $base): array
{
    $found = [];
    if (!preg_match_all('#<link\b[^>]*>#i', substr($html, 0, 80_000), $m)) {
        return [];
    }
    foreach ($m[0] as $tag) {
        if (!preg_match('#type=["\']application/(?:rss|atom)\+xml#i', $tag)) {
            continue;
        }
        if (!preg_match('#href=["\']([^"\']+)#i', $tag, $h)) {
            continue;
        }
        $url = news_absolutize($base, $h[1]);
        if ($url !== '' && !in_array($url, $found, true)) {
            $found[] = $url;
        }
    }
    return $found;
}

function news_guess_feed_urls(string $pageUrl): array
{
    $parts = parse_url($pageUrl);
    if (!$parts || empty($parts['host'])) {
        return [];
    }
    $origin = 'https://' . strtolower((string) $parts['host']);
    $guesses = ['/feed', '/rss', '/rss.xml', '/atom.xml', '/index.xml', '/feed.xml'];
    $out = [];
    foreach ($guesses as $path) {
        $url = news_normalize_source_url($origin . $path);
        if ($url !== '') {
            $out[] = $url;
        }
        if (count($out) >= 2) {
            break;
        }
    }
    return $out;
}

function news_merge_editorial(array $live): array
{
    $out = ['telecom' => [], 'banking' => []];
    foreach (news_editorial() as $item) {
        $out[$item['sector']][] = $item;
    }
    foreach (['telecom', 'banking'] as $sector) {
        foreach ($live[$sector] ?? [] as $item) {
            $out[$sector][] = $item;
        }
        $out[$sector] = news_dedupe($out[$sector]);
        usort($out[$sector], static function (array $a, array $b): int {
            return ($b['published'] ?? 0) <=> ($a['published'] ?? 0);
        });
    }
    return $out;
}

function news_dedupe(array $items): array
{
    $seen = [];
    $out = [];
    foreach ($items as $item) {
        $url = news_canonical_url((string) ($item['url'] ?? ''));
        $title = strtolower(trim((string) ($item['title'] ?? '')));
        if ($url === '' || $title === '') {
            continue;
        }
        $key = $url !== '#' ? $url : $title;
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $item['url'] = $url;
        $out[] = $item;
    }
    return $out;
}

function news_canonical_url(string $url): string
{
    $url = trim($url);
    if ($url === '' || !preg_match('#^https://#i', $url)) {
        return '';
    }
    $parts = parse_url($url);
    if (!$parts || empty($parts['host']) || empty($parts['scheme'])) {
        return '';
    }
    $query = [];
    if (!empty($parts['query'])) {
        parse_str((string) $parts['query'], $query);
        foreach (array_keys($query) as $k) {
            if (str_starts_with(strtolower((string) $k), 'utm_')) {
                unset($query[$k]);
            }
        }
    }
    $host = strtolower((string) $parts['host']);
    $path = $parts['path'] ?? '/';
    $built = 'https://' . $host . $path;
    if ($query) {
        $built .= '?' . http_build_query($query);
    }
    return $built;
}

function news_http_multi(array $urls): array
{
    $urls = array_values(array_unique(array_filter($urls)));
    $out = array_fill_keys($urls, '');
    if (!$urls || !function_exists('curl_multi_init')) {
        return $out;
    }
    $mh = curl_multi_init();
    if ($mh === false) {
        return $out;
    }
    $handles = [];
    foreach ($urls as $url) {
        if (!news_feed_url_ok($url)) {
            continue;
        }
        $ch = curl_init($url);
        if ($ch === false) {
            continue;
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_USERAGENT => 'WorkIsGodNews/1.0 (+https://workisgod.com/news)',
            CURLOPT_HTTPHEADER => [
                'Accept: application/rss+xml, application/atom+xml, application/xml, text/xml, text/html;q=0.8, */*;q=0.1',
            ],
            CURLOPT_MAXFILESIZE => 800_000,
        ]);
        curl_multi_add_handle($mh, $ch);
        $handles[(int) $ch] = [$ch, $url];
    }
    if (!$handles) {
        curl_multi_close($mh);
        return $out;
    }
    $running = 0;
    do {
        $status = curl_multi_exec($mh, $running);
        if ($running) {
            curl_multi_select($mh, 1.0);
        }
    } while ($running && $status === CURLM_OK);

    foreach ($handles as [$ch, $url]) {
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $final = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        $body = curl_multi_getcontent($ch);
        if ($code >= 200 && $code < 400 && is_string($body) && $body !== '' && news_feed_url_ok($final !== '' ? $final : $url)) {
            $out[$url] = strlen($body) > 750_000 ? substr($body, 0, 750_000) : $body;
        }
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
    }
    curl_multi_close($mh);
    return $out;
}

function news_feed_url_ok(string $url): bool
{
    return news_normalize_source_url($url) !== '';
}

function news_parse_feed(string $xml, string $source, string $sector): array
{
    $items = [];
    if (preg_match_all('#<item\b[\s\S]*?</item>#i', $xml, $m)) {
        foreach ($m[0] as $chunk) {
            $item = news_from_chunk($chunk, $source, $sector, false);
            if ($item) {
                $items[] = $item;
            }
        }
    }
    if (preg_match_all('#<entry\b[\s\S]*?</entry>#i', $xml, $m)) {
        foreach ($m[0] as $chunk) {
            $item = news_from_chunk($chunk, $source, $sector, true);
            if ($item) {
                $items[] = $item;
            }
        }
    }
    return $items;
}

function news_from_chunk(string $chunk, string $source, string $sector, bool $atom): ?array
{
    $title = news_inner_tag($chunk, 'title');
    $link = news_extract_link($chunk);
    $summary = news_inner_tag($chunk, $atom ? 'summary' : 'description');
    if ($summary === '') {
        $summary = news_inner_tag($chunk, 'content');
    }
    $dateRaw = news_inner_tag($chunk, $atom ? 'updated' : 'pubDate');
    if ($dateRaw === '' && $atom) {
        $dateRaw = news_inner_tag($chunk, 'published');
    }
    $title = news_plain($title, 180);
    $summary = news_plain($summary, 320);
    $link = news_canonical_url($link);
    if ($title === '' || $link === '' || news_should_skip($link, $title, $summary)) {
        return null;
    }
    $ts = strtotime($dateRaw) ?: 0;
    if ($ts > time() + 86400) {
        $ts = time();
    }
    return [
        'sector' => $sector,
        'title' => $title,
        'summary' => $summary !== '' ? $summary : $title,
        'url' => $link,
        'source' => $source,
        'published' => $ts,
        'kind' => 'wire',
    ];
}

function news_inner_tag(string $chunk, string $tag): string
{
    if (!preg_match('#<' . preg_quote($tag, '#') . '\b[^>]*>([\s\S]*?)</' . preg_quote($tag, '#') . '>#i', $chunk, $m)) {
        return '';
    }
    $inner = $m[1];
    if (preg_match('#<!\[CDATA\[([\s\S]*?)\]\]>#', $inner, $c)) {
        $inner = $c[1];
    }
    return $inner;
}

function news_extract_link(string $chunk): string
{
    if (preg_match('#<link\b[^>]*href=["\']([^"\']+)#i', $chunk, $m)) {
        return html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    $inner = news_inner_tag($chunk, 'link');
    if ($inner !== '') {
        return html_entity_decode($inner, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    if (preg_match('#<guid\b[^>]*>([\s\S]*?)</guid>#i', $chunk, $m)) {
        $g = trim(strip_tags($m[1]));
        if (preg_match('#^https://#i', $g)) {
            return $g;
        }
    }
    return '';
}

function news_plain(string $text, int $max): string
{
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = strip_tags($text);
    $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
    $text = trim($text);
    $len = function_exists('mb_strlen') ? mb_strlen($text) : strlen($text);
    if ($max > 0 && $len > $max) {
        if (function_exists('mb_substr')) {
            $cut = mb_substr($text, 0, $max);
            $sp = mb_strrpos($cut, ' ');
            $text = trim($sp ? mb_substr($cut, 0, $sp) : $cut) . '…';
        } else {
            $cut = substr($text, 0, $max);
            $sp = strrpos($cut, ' ');
            $text = trim($sp ? substr($cut, 0, $sp) : $cut) . '…';
        }
    }
    return $text;
}

function news_should_skip(string $url, string $title, string $summary): bool
{
    $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?? ''));
    $path = strtolower((string) (parse_url($url, PHP_URL_PATH) ?? ''));
    if (in_array($host, ['content.rcrwireless.com', 'pages.rcrtech.com', 'www.ericsson.com', 'ericsson.com'], true)) {
        return true;
    }
    foreach (['/pressarticle/', '/videoarticle/', '/event-info/', '/forms/', '/whitepaper'] as $bad) {
        if (str_contains($path, $bad)) {
            return true;
        }
    }
    $blob = strtolower($title . ' ' . $summary);
    foreach (['download the report', 'sign me up', 'subscribe and receive', 'advertorial'] as $noise) {
        if (str_contains($blob, $noise)) {
            return true;
        }
    }
    return false;
}

function news_when(int $ts): string
{
    if ($ts <= 0) {
        return '';
    }
    return gmdate('j M Y', $ts);
}

function render_news_list(array $items): void
{
    if (!$items) {
        echo '<p class="fine">The wire is quiet just now. The desk notes above still stand. Refresh in a few minutes.</p>';
        return;
    }
    echo '<ol class="news-list">';
    foreach ($items as $item) {
        $kind = ($item['kind'] ?? '') === 'desk' ? 'Desk' : 'Wire';
        $when = news_when((int) ($item['published'] ?? 0));
        echo '<li class="news-item">';
        echo '<p class="news-meta"><span>' . h($kind) . '</span> · <span>' . h((string) $item['source']) . '</span>';
        if ($when !== '') {
            echo ' · <time datetime="' . h(gmdate('c', (int) $item['published'])) . '">' . h($when) . '</time>';
        }
        echo '</p>';
        echo '<h3><a href="' . h((string) $item['url']) . '" rel="noopener noreferrer" target="_blank">' . h((string) $item['title']) . '</a></h3>';
        echo '<p class="news-summary">' . h((string) $item['summary']) . '</p>';
        echo '<p class="news-ref"><a href="' . h((string) $item['url']) . '" rel="noopener noreferrer" target="_blank">Reference → ' . h((string) $item['source']) . '</a></p>';
        echo '</li>';
    }
    echo '</ol>';
}

function render_news_promo(): void
{
    $sectors = news_sectors();
    $telecom = news_items('telecom', 3, false);
    $banking = news_items('banking', 3, false);
    ?>
    <section class="news-promo" aria-labelledby="news-promo-h">
        <div class="news-promo-copy">
            <p class="eyebrow">Technical news</p>
            <h2 id="news-promo-h">Telecom and banking, as they actually move.</h2>
            <p>Latest technical headlines with a short highlight and a link to the original. We do not rewrite the article. The publisher remains the source of record.</p>
        </div>
        <div class="news-sector-grid">
            <?php foreach ($sectors as $sector): ?>
                <a class="news-sector-card" href="<?= h($sector['path']) ?>">
                    <span class="eyebrow"><?= h($sector['name']) ?></span>
                    <strong>Latest <?= h(strtolower($sector['name'])) ?> news</strong>
                    <small><?= h($sector['blurb']) ?></small>
                    <span class="text-link">Open the desk →</span>
                </a>
            <?php endforeach; ?>
        </div>
        <div class="news-home-cols">
            <div>
                <h3>Telecom, just in</h3>
                <?php render_news_list($telecom); ?>
                <p><a class="text-link" href="/news/telecom">All telecom news →</a></p>
            </div>
            <div>
                <h3>Banking, just in</h3>
                <?php render_news_list($banking); ?>
                <p><a class="text-link" href="/news/banking">All banking news →</a></p>
            </div>
        </div>
    </section>
    <?php
}
