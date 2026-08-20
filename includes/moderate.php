<?php

declare(strict_types=1);

function moderate_profile_text(string $text): array
{
    $text = trim($text);
    $result = [
        'allow' => true,
        'alerts' => [],
        'reason' => '',
        'via' => 'local',
    ];
    if ($text === '') {
        return $result;
    }

    $local = moderate_local_scan($text);
    if ($local) {
        $result['allow'] = false;
        $result['alerts'] = $local;
        $result['reason'] = 'This draft uses language that cannot be published. Please remove the flagged wording.';
        return $result;
    }

    if (rate_limited('mod-ip:' . client_ip(), 40, 3600)) {
        $result['allow'] = false;
        $result['reason'] = 'Too many safety checks. Wait a moment and try again.';
        return $result;
    }

    try {
        $ai = moderate_gemini_scan($text);
        $result['via'] = $ai['via'];
        $result['alerts'] = $ai['alerts'];
        if (!empty($ai['harmful']) || !empty($ai['censored'])) {
            $result['allow'] = false;
            $result['reason'] = $ai['reason'] !== ''
                ? $ai['reason']
                : 'Gemini flagged wording that is not safe to publish. Please revise.';
        } elseif (empty($ai['profile'])) {
            $result['allow'] = false;
            $result['reason'] = $ai['reason'] !== ''
                ? $ai['reason']
                : 'This does not read as a professional profile or résumé. Keep the text about work, education, and skills.';
        }
    } catch (Throwable $e) {
        error_log('Gemini moderation failed: ' . $e->getMessage());
        $result['via'] = 'local-fallback';
    }

    return $result;
}

function moderate_local_scan(string $text): array
{
    $lower = strtolower($text);
    $hits = [];
    $needles = [
        'kill yourself', 'kys', 'rape', 'child porn', 'child sexual',
        'nazi', 'white power', 'bomb instructions', 'how to make a bomb',
        'credit card number', 'social security number', 'ssn:',
    ];
    foreach ($needles as $n) {
        if (str_contains($lower, $n)) {
            $hits[] = 'Blocked phrase: “' . $n . '”';
        }
    }
    if (preg_match('/\b(fuck|shit|bitch|asshole|cunt)\b/i', $text, $m)) {
        $hits[] = 'Coarse language: “' . strtolower($m[1]) . '”';
    }
    return array_values(array_unique($hits));
}

function moderate_gemini_scan(string $text): array
{
    $sample = $text;
    if (strlen($sample) > 8000) {
        $sample = substr($sample, 0, 8000);
    }
    $prompt = <<<PROMPT
You are a safety reviewer for Work is God, a résumé website.
Decide if the text is a professional profile/resume and if it is safe to publish.

Return ONLY JSON:
{"profile":true,"harmful":false,"censored":[],"reason":""}

Rules:
- profile=true only if the text is about a person's work, education, skills, or career.
- harmful=true for hate, harassment, violence, self-harm, sexual exploitation, scams, or instructions to harm.
- censored: list rude, abusive, or disallowed words/phrases found (empty if none).
- Do not invent extra issues. Be strict on harm, fair on ordinary résumé language.
- reason: one short sentence for the user if you reject.

TEXT:
PROMPT
        . $sample;

    $raw = gemini_json($prompt);
    $profile = !empty($raw['profile']);
    $harmful = !empty($raw['harmful']);
    $censored = [];
    if (!empty($raw['censored']) && is_array($raw['censored'])) {
        foreach ($raw['censored'] as $item) {
            if (is_string($item) && trim($item) !== '') {
                $censored[] = trim($item);
            }
        }
    }
    $reason = is_string($raw['reason'] ?? null) ? trim((string) $raw['reason']) : '';
    $alerts = [];
    if ($harmful) {
        $alerts[] = 'Harmful content';
    }
    if (!$profile) {
        $alerts[] = 'Not a professional profile';
    }
    foreach ($censored as $c) {
        $alerts[] = 'Flagged: “' . $c . '”';
    }
    return [
        'profile' => $profile,
        'harmful' => $harmful,
        'censored' => $censored,
        'alerts' => $alerts,
        'reason' => $reason,
        'via' => (string) ($GLOBALS['gemini_last_via'] ?? 'gemini'),
    ];
}

function gemini_json(string $prompt): array
{
    $errors = [];
    if (defined('GEMINI_API_KEY') && GEMINI_API_KEY !== '') {
        foreach (['gemini-2.0-flash', 'gemini-1.5-flash'] as $model) {
            try {
                $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent?key=' . rawurlencode(GEMINI_API_KEY);
                $data = ai_http_json($url, [
                    'contents' => [['parts' => [['text' => $prompt]]]],
                    'generationConfig' => [
                        'temperature' => 0,
                        'responseMimeType' => 'application/json',
                    ],
                ], []);
                if (!empty($data['promptFeedback']['blockReason'])) {
                    return ['profile' => false, 'harmful' => true, 'censored' => [], 'reason' => 'Gemini blocked this text as unsafe.'];
                }
                $txt = (string) ($data['candidates'][0]['content']['parts'][0]['text'] ?? '');
                $parsed = json_decode(strip_ai_fences($txt), true);
                if (is_array($parsed)) {
                    $GLOBALS['gemini_last_via'] = 'gemini-google';
                    return $parsed;
                }
            } catch (Throwable $e) {
                $errors[] = $e->getMessage();
            }
        }
    }

    $data = ai_http_json(
        'https://api.llm7.io/v1/chat/completions',
        [
            'model' => 'gemini-3.1-flash-lite',
            'temperature' => 0,
            'messages' => [
                ['role' => 'system', 'content' => 'Return only valid JSON. No markdown.'],
                ['role' => 'user', 'content' => $prompt],
            ],
        ],
        []
    );
    $txt = openai_content($data);
    $parsed = json_decode(strip_ai_fences($txt), true);
    if (!is_array($parsed)) {
        throw new RuntimeException('Gemini returned an unreadable safety verdict. ' . implode(' ', $errors));
    }
    $GLOBALS['gemini_last_via'] = 'gemini-flash';
    return $parsed;
}

function moderation_message(array $mod): string
{
    $parts = [];
    if ($mod['reason'] !== '') {
        $parts[] = $mod['reason'];
    }
    if (!empty($mod['alerts'])) {
        $parts[] = implode(' · ', $mod['alerts']);
    }
    return implode(' ', $parts);
}
