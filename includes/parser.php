<?php

declare(strict_types=1);

function resume_themes(): array
{
    return [
        'classic' => 'Classic · navy',
        'modern' => 'Modern · teal',
        'minimal' => 'Minimal · ink',
        'acid' => 'Acid · Gen Z',
        'brutal' => 'Brutal · Gen Z',
        'midnight' => 'Midnight · millennial',
        'peach' => 'Peach · millennial',
        'vapor' => 'Vapor · Gen Z',
    ];
}

function site_themes(): array
{
    return [
        'atelier' => 'Atelier · millennial',
        'acid' => 'Acid · Gen Z',
        'brutal' => 'Brutal · Gen Z',
        'midnight' => 'Midnight · millennial',
        'peach' => 'Peach · millennial',
        'vapor' => 'Vapor · Gen Z',
    ];
}

function is_valid_theme(string $theme): bool
{
    return array_key_exists($theme, resume_themes());
}

function parse_resume(string $text): array
{
    $lines = preg_split("/\R/", $text) ?: [];
    $doc = [
        'name' => '',
        'headline' => '',
        'contacts' => [],
        'sections' => [],
    ];

    $i = 0;
    $n = count($lines);
    while ($i < $n && trim($lines[$i]) === '') {
        $i++;
    }
    if ($i < $n) {
        $doc['name'] = trim_heading($lines[$i]);
        $i++;
    }
    while ($i < $n && trim($lines[$i]) !== '' && !is_section_header($lines[$i])) {
        $line = trim($lines[$i]);
        if ($doc['headline'] === '' && !is_contact_line($line)) {
            $doc['headline'] = $line;
        } else {
            foreach (split_contacts($line) as $c) {
                $doc['contacts'][] = $c;
            }
        }
        $i++;
    }

    $current = null;
    for (; $i < $n; $i++) {
        $raw = $lines[$i];
        $trim = trim($raw);
        if ($trim === '' && $current === null) {
            continue;
        }
        if (is_section_header($trim)) {
            if ($current) {
                $doc['sections'][] = $current;
            }
            $current = ['title' => trim_heading($trim), 'blocks' => []];
            continue;
        }
        if ($current === null) {
            $current = ['title' => 'Profile', 'blocks' => []];
        }
        if ($trim === '') {
            $current['blocks'][] = ['type' => 'spacer'];
            continue;
        }
        if (preg_match('/^\s*[-*•]\s+(.+)/', $raw, $m)) {
            $last = $current['blocks'][count($current['blocks']) - 1] ?? null;
            if ($last && $last['type'] === 'list') {
                $current['blocks'][count($current['blocks']) - 1]['items'][] = $m[1];
            } else {
                $current['blocks'][] = ['type' => 'list', 'items' => [$m[1]]];
            }
            continue;
        }
        if (substr_count($trim, '|') >= 1 && strlen($trim) < 180) {
            $current['blocks'][] = ['type' => 'meta', 'parts' => array_map('trim', explode('|', $trim))];
            continue;
        }
        $current['blocks'][] = ['type' => 'p', 'text' => $trim];
    }
    if ($current) {
        $doc['sections'][] = $current;
    }

    return $doc;
}

function is_section_header(string $line): bool
{
    $line = trim($line);
    if ($line === '') {
        return false;
    }
    if (preg_match('/^#{1,3}\s+\S/', $line)) {
        return true;
    }
    $plain = trim($line, "#: \t");
    if (strlen($plain) < 3 || strlen($plain) > 48) {
        return false;
    }
    if (str_ends_with($line, ':') && preg_match('/^[A-Za-z][A-Za-z &\/-]+:$/', $line)) {
        return true;
    }
    $letters = preg_replace('/[^A-Za-z]/', '', $plain) ?? '';
    return $letters !== '' && strtoupper($letters) === $letters && strlen($letters) >= 3;
}

function trim_heading(string $line): string
{
    $line = trim($line);
    $line = preg_replace('/^#{1,3}\s+/', '', $line) ?? $line;
    return rtrim($line, ':');
}

function is_contact_line(string $line): bool
{
    return (bool) preg_match('/@|https?:\/\/|\+?\d[\d\s().-]{6,}|\|/i', $line);
}

function split_contacts(string $line): array
{
    $parts = preg_split('/\s*[|•·]\s*/', $line) ?: [$line];
    return array_values(array_filter(array_map('trim', $parts)));
}

function inline_format(string $text): string
{
    $text = h($text);
    $text = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text) ?? $text;
    $text = preg_replace('/(?<!\*)\*(?!\*)(.+?)(?<!\*)\*(?!\*)/', '<em>$1</em>', $text) ?? $text;
    $text = preg_replace(
        '/\[(.+?)\]\((https?:\/\/[^\s)]+)\)/',
        '<a href="$2" rel="noopener noreferrer" target="_blank">$1</a>',
        $text
    ) ?? $text;
    $text = preg_replace(
        '/(?<!href="|">)(https?:\/\/[^\s<]+)/',
        '<a href="$1" rel="noopener noreferrer" target="_blank">$1</a>',
        $text
    ) ?? $text;
    $text = preg_replace(
        '/\b([A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,})\b/i',
        '<a href="mailto:$1">$1</a>',
        $text
    ) ?? $text;
    return $text;
}

function render_resume_html(array $doc, string $theme): string
{
    $theme = is_valid_theme($theme) ? $theme : 'classic';
    ob_start();
    ?>
    <article class="resume theme-<?= h($theme) ?>">
        <header class="resume-hero">
            <?php if ($doc['name'] !== ''): ?>
                <h1><?= inline_format($doc['name']) ?></h1>
            <?php endif; ?>
            <?php if ($doc['headline'] !== ''): ?>
                <p class="headline"><?= inline_format($doc['headline']) ?></p>
            <?php endif; ?>
            <?php if ($doc['contacts']): ?>
                <p class="contacts">
                    <?php foreach ($doc['contacts'] as $idx => $c): ?>
                        <?php if ($idx > 0): ?><span class="dot">·</span><?php endif; ?>
                        <span><?= inline_format($c) ?></span>
                    <?php endforeach; ?>
                </p>
            <?php endif; ?>
        </header>
        <?php foreach ($doc['sections'] as $section): ?>
            <section class="resume-section">
                <h2><?= inline_format($section['title']) ?></h2>
                <?php foreach ($section['blocks'] as $block): ?>
                    <?php if ($block['type'] === 'spacer'): ?>
                        <div class="gap"></div>
                    <?php elseif ($block['type'] === 'list'): ?>
                        <ul>
                            <?php foreach ($block['items'] as $item): ?>
                                <li><?= inline_format($item) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php elseif ($block['type'] === 'meta'): ?>
                        <p class="meta">
                            <?php foreach ($block['parts'] as $pi => $part): ?>
                                <?php if ($pi > 0): ?><span class="dot">·</span><?php endif; ?>
                                <span><?= inline_format($part) ?></span>
                            <?php endforeach; ?>
                        </p>
                    <?php else: ?>
                        <p><?= inline_format($block['text']) ?></p>
                    <?php endif; ?>
                <?php endforeach; ?>
            </section>
        <?php endforeach; ?>
    </article>
    <?php
    return (string) ob_get_clean();
}
