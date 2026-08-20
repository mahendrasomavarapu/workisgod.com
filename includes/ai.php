<?php

declare(strict_types=1);

function ai_enabled(): bool
{
    return true;
}

function ai_improve_resume(string $source, string $mode = 'improve'): string
{
    $source = trim($source);
    if ($source === '') {
        throw new InvalidArgumentException('Paste or upload resume notes before using the AI agent.');
    }
    if (setting('ai_enabled', '1') !== '1') {
        throw new RuntimeException('The AI agent is turned off in admin settings.');
    }
    $harder = ($mode === 'harder' || $mode === 'from_profile');
    if (rate_limited('ai-ip:' . client_ip(), 80, 3600) || rate_limited('ai-user:' . (string) ($_SESSION['user_id'] ?? '0'), 30, 3600)) {
        throw new RuntimeException('Too many AI requests. Try again in a little while.');
    }

    @ini_set('max_execution_time', '120');
    if ($mode === 'from_profile') {
        $system = ai_profile_prompt();
        $user = "Build a complete resume from this imported profile data. Prefer facts from LinkedIn/PDF/URL. Use extra notes only if they add facts.\n\n---\n" . $source;
    } elseif ($mode === 'harder') {
        $system = ai_harder_prompt();
        $user = "This is a later pass. Think harder. Deepen and tighten the draft below without adding new facts.\n\n---\n" . $source;
    } else {
        $system = ai_improve_prompt();
        $user = "Improve this resume from the notes below.\n\n---\n" . $source;
    }
    $provider = '';
    $text = '';
    try {
        [$text, $provider] = llm_generate($system, $user, $harder);
    } catch (Throwable $e) {
        error_log('LLM improve failed: ' . $e->getMessage());
    }

    $text = strip_ai_fences($text);
    if ($text === '' || strlen($text) < 40) {
        $text = local_improve_resume($source, $harder);
        $provider = $provider !== '' ? $provider : 'local';
    }
    if (strlen($text) > RESUME_MAX_CHARS) {
        $text = substr($text, 0, RESUME_MAX_CHARS);
    }
    $GLOBALS['ai_last_provider'] = $provider;
    $GLOBALS['ai_last_changed'] = !text_similar($source, $text);
    return $text;
}

function ai_last_provider(): string
{
    return (string) ($GLOBALS['ai_last_provider'] ?? '');
}

function ai_last_changed(): bool
{
    return (bool) ($GLOBALS['ai_last_changed'] ?? false);
}

function text_similar(string $a, string $b): bool
{
    $norm = static function (string $s): string {
        $s = strtolower(trim($s));
        $s = preg_replace('/\s+/', ' ', $s) ?? $s;
        return $s;
    };
    return $norm($a) === $norm($b);
}

function ai_improve_prompt(): string
{
    return <<<'PROMPT'
You are the Work is God resume agent. Turn the user's notes into a stronger resume.

Output ONLY plain text in this exact shape (omit a section if the source has no facts for it):

Name
Title
email | phone | city | website

SUMMARY
2–4 sentences. Specific, calm, no clichés.

EXPERIENCE
Company | Role | Dates
- Achievement with a real outcome
- Achievement

EDUCATION
School | Degree | Year

SKILLS
comma-separated skills

Rules:
- Use only facts present in the source. Do not invent employers, titles, dates, degrees, metrics, or tools.
- You may tighten wording, group related points, and fix grammar.
- Keep names, companies, and numbers exactly as given.
- No markdown fences, no title like "Resume", no commentary before or after the resume.
PROMPT;
}

function ai_profile_prompt(): string
{
    return <<<'PROMPT'
You are the Work is God resume agent. The user imported a LinkedIn page, a PDF, or another public profile URL. Turn that captured text into a finished resume.

Output ONLY plain text in this exact shape (omit a section if the source has no facts for it):

Name
Title
email | phone | city | website

SUMMARY
2–4 sentences. Specific, calm, no clichés.

EXPERIENCE
Company | Role | Dates
- Achievement with a real outcome
- Achievement

EDUCATION
School | Degree | Year

SKILLS
comma-separated skills

Rules:
- Use only facts present in the imported text. Do not invent employers, titles, dates, degrees, metrics, or tools.
- Ignore login walls, cookie banners, navigation, ads, and “Join LinkedIn” copy.
- Keep names, companies, and numbers exactly as given.
- No markdown fences, no commentary before or after the resume.
PROMPT;
}

function ai_harder_prompt(): string
{
    return <<<'PROMPT'
You are a senior resume editor doing a harder thinking pass. The input is already a draft. Improve it again.

Think carefully, then output ONLY the rewritten resume in this shape:

Name
Title
email | phone | city | website

SUMMARY
2–4 sentences. Sharper than the draft. No clichés.

EXPERIENCE
Company | Role | Dates
- Stronger bullets. Lead with a verb. Keep real outcomes.
- Cut repetition. Merge weak lines.

EDUCATION
School | Degree | Year

SKILLS
comma-separated skills, most relevant first

Hard rules:
- Do not invent employers, titles, dates, degrees, metrics, tools, or awards.
- Keep every real name, company, and number.
- Prefer fewer, stronger bullets over more filler.
- No markdown fences and no commentary.
PROMPT;
}

function llm_generate(string $system, string $user, bool $harder = false): array
{
    $messages = [
        ['role' => 'system', 'content' => $system],
        ['role' => 'user', 'content' => $user],
    ];
    $attempts = [];
    if (defined('GROQ_API_KEY') && GROQ_API_KEY !== '') {
        $attempts[] = [
            'label' => 'groq',
            'url' => 'https://api.groq.com/openai/v1/chat/completions',
            'payload' => [
                'model' => $harder ? 'llama-3.3-70b-versatile' : 'llama-3.1-8b-instant',
                'temperature' => $harder ? 0.45 : 0.3,
                'messages' => $messages,
            ],
            'headers' => ['Authorization: Bearer ' . GROQ_API_KEY],
        ];
    }
    $attempts[] = [
        'label' => 'llm7',
        'url' => 'https://api.llm7.io/v1/chat/completions',
        'payload' => [
            'model' => $harder ? 'gpt-oss:20b' : 'gemini-3.1-flash-lite',
            'messages' => $messages,
        ],
        'headers' => [],
    ];
    $attempts[] = [
        'label' => 'llm7-alt',
        'url' => 'https://api.llm7.io/v1/chat/completions',
        'payload' => [
            'model' => $harder ? 'gemini-3.1-flash-lite' : 'gpt-oss:20b',
            'messages' => $messages,
        ],
        'headers' => [],
    ];
    $attempts[] = [
        'label' => 'pollinations',
        'url' => 'https://text.pollinations.ai/openai',
        'payload' => ['model' => 'openai', 'messages' => $messages],
        'headers' => [],
    ];

    $errors = [];
    foreach ($attempts as $attempt) {
        try {
            $data = ai_http_json($attempt['url'], $attempt['payload'], $attempt['headers']);
            $text = openai_content($data);
            if ($text !== '' && strlen($text) >= 40) {
                return [$text, $attempt['label']];
            }
            $errors[] = $attempt['label'] . ': empty response';
        } catch (Throwable $e) {
            $errors[] = $attempt['label'] . ': ' . $e->getMessage();
        }
    }
    throw new RuntimeException(implode(' | ', $errors));
}

function openai_content(array $data): string
{
    $content = $data['choices'][0]['message']['content'] ?? '';
    if (is_array($content)) {
        $bits = [];
        foreach ($content as $part) {
            if (is_string($part)) {
                $bits[] = $part;
            } elseif (is_array($part) && isset($part['text'])) {
                $bits[] = (string) $part['text'];
            }
        }
        $content = implode("\n", $bits);
    }
    return is_string($content) ? trim($content) : '';
}

function ai_http_json(string $url, array $payload, array $extraHeaders): array
{
    if (!function_exists('curl_init')) {
        throw new RuntimeException('PHP curl is required for the online AI agent.');
    }
    $headers = array_merge(['Content-Type: application/json'], $extraHeaders);
    $ch = curl_init($url);
    if ($ch === false) {
        throw new RuntimeException('Could not start the AI request.');
    }
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 75,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_USERAGENT => 'WorkIsGodResumeAgent/1.0',
    ]);
    $raw = curl_exec($ch);
    $err = curl_error($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($raw === false) {
        throw new RuntimeException($err !== '' ? $err : 'AI request failed');
    }
    $data = json_decode((string) $raw, true);
    if ($code >= 400) {
        $msg = is_array($data) ? (string) ($data['error']['message'] ?? $data['error'] ?? ('HTTP ' . $code)) : ('HTTP ' . $code);
        throw new RuntimeException($msg);
    }
    if (!is_array($data)) {
        throw new RuntimeException('AI returned an unreadable response.');
    }
    return $data;
}

function strip_ai_fences(string $text): string
{
    $text = trim($text);
    if (preg_match('/^```(?:text|markdown|md)?\s*(.*?)\s*```$/s', $text, $m)) {
        return trim($m[1]);
    }
    return $text;
}

function local_improve_resume(string $source, bool $harder = false): string
{
    $doc = parse_resume($source);
    $out = [];

    $name = trim($doc['name']);
    $headline = trim($doc['headline']);
    $contacts = array_values(array_filter(array_map('trim', $doc['contacts'])));

    if ($name !== '') {
        $out[] = $name;
    }
    if ($headline !== '') {
        $out[] = rtrim($headline, '.');
    }
    if ($contacts) {
        $out[] = implode(' | ', $contacts);
    }
    if ($out) {
        $out[] = '';
    }

    $sections = $doc['sections'];
    if (!$sections && trim($source) !== '') {
        $sections[] = ['title' => 'SUMMARY', 'blocks' => [['type' => 'p', 'text' => trim($source)]]];
    }

    foreach ($sections as $section) {
        $title = strtoupper(trim($section['title']));
        $title = preg_replace('/^PROFILE$/', 'SUMMARY', $title) ?? $title;
        $out[] = $title;
        $wrote = false;
        foreach ($section['blocks'] as $block) {
            if ($block['type'] === 'spacer') {
                continue;
            }
            if ($block['type'] === 'list') {
                foreach ($block['items'] as $item) {
                    $out[] = '- ' . polish_resume_line((string) $item, true, $harder);
                    $wrote = true;
                }
                continue;
            }
            if ($block['type'] === 'meta') {
                $parts = array_map(static fn($p) => polish_resume_line((string) $p, false, $harder), $block['parts']);
                $out[] = implode(' | ', array_filter($parts));
                $wrote = true;
                continue;
            }
            $text = polish_resume_line((string) ($block['text'] ?? ''), false, $harder);
            if ($text === '') {
                continue;
            }
            if (preg_match('/[.!;] /', $text) && in_array($title, ['EXPERIENCE', 'PROJECTS', 'WORK'], true)) {
                foreach (preg_split('/(?<=[.!;])\s+/', $text) ?: [] as $sentence) {
                    $sentence = polish_resume_line($sentence, true, $harder);
                    if ($sentence !== '') {
                        $out[] = '- ' . $sentence;
                        $wrote = true;
                    }
                }
            } else {
                $out[] = $text;
                $wrote = true;
            }
        }
        if (!$wrote) {
            array_pop($out);
        } else {
            $out[] = '';
        }
    }

    $text = trim(implode("\n", $out));
    return $text !== '' ? $text : $source;
}

function polish_resume_line(string $text, bool $asBullet, bool $harder = false): string
{
    $text = trim($text);
    $text = trim($text, "*• \t");
    $text = preg_replace('/\s+/', ' ', $text) ?? $text;
    $text = preg_replace('/^(I |I\'m |I am |I was |I have |We |We have )/i', '', $text) ?? $text;
    $text = trim($text);
    if ($text === '') {
        return '';
    }
    if ($asBullet) {
        $text = rtrim($text, '.');
        if ($harder) {
            $text = stronger_verb_line($text);
        }
        $text = ucfirst($text);
    }
    return $text;
}

function stronger_verb_line(string $text): string
{
    $map = [
        'helped' => 'Supported',
        'worked on' => 'Built',
        'worked with' => 'Partnered with',
        'responsible for' => 'Owned',
        'handled' => 'Managed',
        'did' => 'Delivered',
        'made' => 'Created',
        'used' => 'Applied',
        'assisted' => 'Supported',
        'participated in' => 'Contributed to',
    ];
    foreach ($map as $from => $to) {
        if (stripos($text, $from) === 0) {
            return $to . substr($text, strlen($from));
        }
    }
    return $text;
}
