<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

mattrics_require_method('POST');

$config = mattrics_load_config();
$apiKey = trim((string) ($config['anthropic_api_key'] ?? ''));
$model = trim((string) ($config['anthropic_model'] ?? 'claude-sonnet-4-20250514'));

if ($apiKey === '') {
    mattrics_send_json(['error' => 'AI is not configured on the server.'], 503);
}

$body = mattrics_read_json_body();
$recent = $body['recent'] ?? [];

if (!is_array($recent)) {
    mattrics_send_json(['error' => 'recent must be an array.'], 400);
}

$summary = trim((string) ($body['summary'] ?? ''));
$fatigueSummary = trim((string) ($body['fatigueSummary'] ?? ''));
$fatigueRegions = $body['fatigueRegions'] ?? [];

if (!is_array($fatigueRegions)) {
    mattrics_send_json(['error' => 'fatigueRegions must be an array.'], 400);
}

$fatigueLines = [];
foreach ($fatigueRegions as $region) {
    if (!is_array($region)) {
        continue;
    }

    $label = trim((string) ($region['label'] ?? ''));
    if ($label === '') {
        continue;
    }

    $score = (int) ($region['fatigueScore'] ?? 0);
    $tier = trim((string) ($region['tier'] ?? ''));
    $lastWorkedLabel = trim((string) ($region['lastWorkedLabel'] ?? ''));
    $recoveryLabel = trim((string) ($region['recoveryLabel'] ?? ''));
    $line = '- ' . $label . ': ' . $score . '/100';

    if ($tier !== '') {
        $line .= ' (' . $tier;
        if ($lastWorkedLabel !== '') {
            $line .= ', ' . $lastWorkedLabel;
        }
        if ($recoveryLabel !== '') {
            $line .= ', ' . $recoveryLabel;
        }
        $line .= ')';
    } elseif ($lastWorkedLabel !== '' || $recoveryLabel !== '') {
        $parts = array_values(array_filter([$lastWorkedLabel, $recoveryLabel], static fn($value) => $value !== ''));
        $line .= ' (' . implode(', ', $parts) . ')';
    }

    $fatigueLines[] = $line;
}

$prompt = "You are a smart training coach. This athlete has a varied active lifestyle: they canoe rivers for days at a time, do serious gym work (tracked in Hevy), run (including a half marathon), practice yoga regularly, hike, and row on a Concept2. They have a shoulder injury they're managing with targeted rehab (scapular pulls, external rotation, face pulls).\n\nRecent activity (last 10 days):\n";
$prompt .= $summary !== '' ? $summary : "(no recent data — suggest a good general session)";
$prompt .= "\n\nCurrent muscle fatigue estimate (fixed last 10 days):\n";
$prompt .= $fatigueSummary !== '' ? $fatigueSummary : 'No meaningful recent muscle fatigue signal.';
if ($fatigueLines !== []) {
    $prompt .= "\n" . implode("\n", $fatigueLines);
}
$prompt .= "\n\nSuggest ONE specific workout for today. Avoid heavily loading muscle groups that are currently highly fatigued. Prefer fresher muscle groups, cardio, mobility, rehab, or recovery work when that fits better. Be concrete — if strength, give exercises with sets/reps/weights. If cardio, give distance/duration/intensity. Keep it brief.\n\nFormat:\n**Why this:** (1 sentence based on what they've been doing)\n\n**Session:**\n(the workout, specific and actionable)\n\n**Shoulder note:** (only if relevant)";

$anthropic = mattrics_fetch_json(
    'https://api.anthropic.com/v1/messages',
    [
        'Content-Type: application/json',
        'x-api-key: ' . $apiKey,
        'anthropic-version: 2023-06-01',
    ],
    'POST',
    json_encode([
        'model' => $model,
        'max_tokens' => 900,
        'messages' => [
            ['role' => 'user', 'content' => $prompt],
        ],
    ], JSON_UNESCAPED_SLASHES)
);

$text = '';
foreach (($anthropic['content'] ?? []) as $item) {
    if (is_array($item) && isset($item['text'])) {
        $text .= (string) $item['text'];
    }
}

mattrics_send_json([
    'text' => trim($text) !== '' ? trim($text) : 'No response.',
]);
