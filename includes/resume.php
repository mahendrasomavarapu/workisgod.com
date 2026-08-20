<?php

declare(strict_types=1);

function user_resume(int $userId): ?array
{
    $stmt = db()->prepare('SELECT * FROM resumes WHERE user_id = ?');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function resume_by_slug(string $slug): ?array
{
    $stmt = db()->prepare('SELECT * FROM resumes WHERE slug = ?');
    $stmt->execute([$slug]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function unique_slug(string $desired, ?int $ignoreUserId = null): string
{
    $reserved = ['demo', 'admin', 'login', 'logout', 'editor', 'preview', 'share', 'api', 'assets', 'r', 'account', 'about', 'safety', 'resumes', 'waiting'];
    $base = slugify($desired);
    if ($base === '' || !is_valid_slug($base) || in_array($base, $reserved, true)) {
        $base = ($base !== '' && !in_array($base, $reserved, true)) ? $base : 'resume-' . bin2hex(random_bytes(3));
        if (in_array($base, $reserved, true)) {
            $base = 'resume-' . bin2hex(random_bytes(3));
        }
    }
    $slug = $base;
    $i = 2;
    while (true) {
        $stmt = db()->prepare('SELECT user_id FROM resumes WHERE slug = ?');
        $stmt->execute([$slug]);
        $row = $stmt->fetch();
        if (!$row || ($ignoreUserId && (int) $row['user_id'] === $ignoreUserId)) {
            return $slug;
        }
        $slug = $base . '-' . $i;
        $i++;
        if (strlen($slug) > SLUG_MAX) {
            $slug = rtrim(substr($base, 0, SLUG_MAX - 3), '-') . '-' . $i;
        }
    }
}

function save_user_resume(int $userId, string $slug, string $theme, string $text, string $sourceText = '', bool $aiUsed = false): array
{
    $text = trim($text);
    if ($text === '') {
        throw new InvalidArgumentException('Paste or upload your resume text first.');
    }
    if (strlen($text) > RESUME_MAX_CHARS) {
        throw new InvalidArgumentException('Resume is too long. Keep it under 50,000 characters.');
    }
    if (!is_valid_theme($theme)) {
        $theme = 'classic';
    }
    $sourceText = trim($sourceText);
    if ($sourceText === '') {
        $sourceText = $text;
    }
    $slug = unique_slug($slug, $userId);
    $existing = user_resume($userId);
    $pdo = db();
    if ($existing) {
        $pdo->prepare('UPDATE resumes SET slug = ?, theme = ?, raw_text = ?, source_text = ?, ai_used = ?, updated_at = ? WHERE user_id = ?')
            ->execute([$slug, $theme, $text, $sourceText, $aiUsed ? 1 : 0, iso_now(), $userId]);
    } else {
        $pdo->prepare('INSERT INTO resumes (user_id, slug, theme, raw_text, source_text, ai_used, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([$userId, $slug, $theme, $text, $sourceText, $aiUsed ? 1 : 0, iso_now(), iso_now()]);
    }
    $saved = user_resume($userId);
    if (!$saved) {
        throw new RuntimeException('Could not save resume.');
    }
    return $saved;
}

function sample_resume_text(): string
{
    return <<<'TXT'
Alex Rivera
Product Engineer
alex@example.com | https://workisgod.com | San Francisco

SUMMARY
Builder who treats craft as a daily practice. I ship careful software, write clearly, and keep systems simple enough to hold in one head.

EXPERIENCE
Northstar Labs | Staff Engineer | 2022 – Present
- Led the rebuild of the member workspace used by 40k weekly actives
- Cut p95 API latency from 480ms to 90ms by removing hidden N+1 queries
- Mentored six engineers; introduced design reviews that caught issues before launch

Harbor & Co. | Software Engineer | 2018 – 2022
- Owned billing and identity services for a B2B SaaS platform
- Wrote the runbooks that made on-call quieter for the whole team

EDUCATION
University of Michigan | B.S. Computer Science | 2018

SKILLS
PHP, SQL, HTML/CSS, systems design, technical writing
TXT;
}
