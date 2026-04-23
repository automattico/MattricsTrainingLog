<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/exercise-config-repository.php';

const MATTRICS_EXERCISE_AI_LOG_FILE = 'exercise-ai.log';
const MATTRICS_OPENAI_DEFAULT_MODEL = 'gpt-5.4-mini';
const MATTRICS_EXERCISE_AI_PROMPT_VERSION = 'exercise-config-admin-v1';

function mattrics_exercise_ai_log_path(): string
{
    return mattrics_private_root() . '/' . MATTRICS_EXERCISE_AI_LOG_FILE;
}

function mattrics_log_exercise_ai_request(array $context): void
{
    $entry = array_merge([
        'ts' => gmdate('c'),
        'session' => function_exists('mattrics_session_fingerprint') ? mattrics_session_fingerprint() : null,
        'ip' => function_exists('mattrics_ip_fingerprint') ? mattrics_ip_fingerprint() : null,
    ], $context);

    $path = mattrics_exercise_ai_log_path();
    $dir = dirname($path);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    @file_put_contents($path, json_encode($entry, JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND | LOCK_EX);
}

function mattrics_build_exercise_ai_log_context(array $unknown, array $overrides = []): array
{
    $rawName = trim((string) (($unknown['rawNames'][0] ?? '') ?: ($unknown['normalizedName'] ?? '')));
    $normalizedName = trim((string) ($unknown['normalizedName'] ?? ''));
    $sourceType = trim((string) ($unknown['sourceType'] ?? 'exercise')) ?: 'exercise';
    $resultStatus = trim((string) ($overrides['resultStatus'] ?? $overrides['status'] ?? 'unknown')) ?: 'unknown';
    $context = [
        'event' => 'exercise_ai_suggestion',
        'rawName' => $rawName,
        'normalizedName' => $normalizedName,
        'sourceType' => $sourceType,
        'model' => trim((string) ($overrides['model'] ?? MATTRICS_OPENAI_DEFAULT_MODEL)) ?: MATTRICS_OPENAI_DEFAULT_MODEL,
        'promptVersion' => trim((string) ($overrides['promptVersion'] ?? MATTRICS_EXERCISE_AI_PROMPT_VERSION)) ?: MATTRICS_EXERCISE_AI_PROMPT_VERSION,
        'resultStatus' => $resultStatus,
        'status' => $resultStatus,
        'configCreated' => (bool) ($overrides['configCreated'] ?? false),
    ];

    unset($overrides['resultStatus'], $overrides['status'], $overrides['configCreated'], $overrides['model'], $overrides['promptVersion']);
    return array_merge($context, $overrides);
}

function mattrics_openai_config(): array
{
    $config = mattrics_load_config();
    return [
        'apiKey' => trim((string) ($config['openai_api_key'] ?? '')),
        'model' => trim((string) ($config['openai_model'] ?? MATTRICS_OPENAI_DEFAULT_MODEL)),
    ];
}

function mattrics_build_unknown_suggestion_prompt(array $unknown): array
{
    $allowedMuscles = implode(', ', MATTRICS_EXERCISE_CONFIG_ALLOWED_MUSCLES);
    $rawNames = implode(', ', array_slice($unknown['rawNames'] ?? [], 0, 12));

    $system = implode("\n", [
        'You are a fitness exercise classification assistant.',
        'Return only valid JSON matching the requested schema.',
        'Use only the allowed muscle keys exactly as provided.',
        'Prefer conservative, review-friendly suggestions over aggressive guesses.',
    ]);

    $user = implode("\n", [
        'Classify this unknown training item.',
        'sourceType: ' . (string) ($unknown['sourceType'] ?? ''),
        'rawNames: ' . ($rawNames !== '' ? $rawNames : '(none)'),
        'normalizedName: ' . (string) ($unknown['normalizedName'] ?? ''),
        'Allowed muscles: ' . $allowedMuscles,
        'Use the semantic ladder weights 0.08, 0.20, 0.45, 0.65, and 1.00 when assigning muscleWeights.',
        'Allowed setTypeHandling: ' . implode(', ', MATTRICS_EXERCISE_CONFIG_ALLOWED_SET_TYPES),
        'Return a JSON object with fields:',
        '- canonicalName: string',
        '- aliases: string[]',
        '- muscleWeights: object keyed only by allowed muscles, numeric values, at least one value > 0',
        '- fatigueMultiplier: number',
        '- bodyweightEligible: boolean',
        '- setTypeHandling: one of ' . implode(', ', MATTRICS_EXERCISE_CONFIG_ALLOWED_SET_TYPES),
        '- confidence: number between 0 and 1',
        '- shortReason: short string',
    ]);

    return [
        'system' => $system,
        'user' => $user,
    ];
}

function mattrics_build_existing_exercise_suggestion_prompt(array $exercise): array
{
    $allowedMuscles = implode(', ', MATTRICS_EXERCISE_CONFIG_ALLOWED_MUSCLES);
    $aliases = implode(', ', array_slice($exercise['aliases'] ?? [], 0, 12));
    $matchTerms = implode(', ', array_slice($exercise['matchTerms'] ?? [], 0, 12));
    $muscleSummary = [];
    foreach (($exercise['muscleWeights'] ?? []) as $muscle => $weight) {
        if (!is_string($muscle) || !is_numeric($weight) || (float) $weight <= 0) {
            continue;
        }
        $muscleSummary[] = $muscle . ':' . number_format((float) $weight, 2, '.', '');
    }

    $system = implode("\n", [
        'You are a fitness exercise classification assistant.',
        'Return only valid JSON matching the requested schema.',
        'Use only the allowed muscle keys exactly as provided.',
        'Improve the current mapping conservatively unless the current config is clearly wrong.',
    ]);

    $user = implode("\n", [
        'Refine this existing exercise mapping.',
        'canonicalName: ' . (string) ($exercise['canonicalName'] ?? ''),
        'aliases: ' . ($aliases !== '' ? $aliases : '(none)'),
        'matchTerms: ' . ($matchTerms !== '' ? $matchTerms : '(none)'),
        'currentMuscleWeights: ' . ($muscleSummary !== [] ? implode(', ', $muscleSummary) : '(none)'),
        'currentFatigueMultiplier: ' . (string) ($exercise['fatigueMultiplier'] ?? 1),
        'currentBodyweightEligible: ' . ((bool) ($exercise['bodyweightEligible'] ?? false) ? 'true' : 'false'),
        'currentSetTypeHandling: ' . (string) ($exercise['setTypeHandling'] ?? 'weight_reps'),
        'Allowed muscles: ' . $allowedMuscles,
        'Use the semantic ladder weights 0.08, 0.20, 0.45, 0.65, and 1.00 when assigning muscleWeights.',
        'Allowed setTypeHandling: ' . implode(', ', MATTRICS_EXERCISE_CONFIG_ALLOWED_SET_TYPES),
        'Return a JSON object with fields:',
        '- canonicalName: string',
        '- aliases: string[]',
        '- muscleWeights: object keyed only by allowed muscles, numeric values, at least one value > 0',
        '- fatigueMultiplier: number',
        '- bodyweightEligible: boolean',
        '- setTypeHandling: one of ' . implode(', ', MATTRICS_EXERCISE_CONFIG_ALLOWED_SET_TYPES),
        '- confidence: number between 0 and 1',
        '- shortReason: short string',
    ]);

    return [
        'system' => $system,
        'user' => $user,
    ];
}

function mattrics_openai_suggestion_schema(): array
{
    $muscleProperties = [];
    foreach (MATTRICS_EXERCISE_CONFIG_ALLOWED_MUSCLES as $muscle) {
        $muscleProperties[$muscle] = ['type' => 'number'];
    }

    return [
        'type' => 'object',
        'additionalProperties' => false,
        'required' => [
            'canonicalName',
            'aliases',
            'muscleWeights',
            'fatigueMultiplier',
            'bodyweightEligible',
            'setTypeHandling',
            'confidence',
            'shortReason',
        ],
        'properties' => [
            'canonicalName' => ['type' => 'string'],
            'aliases' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
            ],
            'muscleWeights' => [
                'type' => 'object',
                'additionalProperties' => false,
                'required' => MATTRICS_EXERCISE_CONFIG_ALLOWED_MUSCLES,
                'properties' => $muscleProperties,
            ],
            'fatigueMultiplier' => ['type' => 'number'],
            'bodyweightEligible' => ['type' => 'boolean'],
            'setTypeHandling' => [
                'type' => 'string',
                'enum' => MATTRICS_EXERCISE_CONFIG_ALLOWED_SET_TYPES,
            ],
            'confidence' => ['type' => 'number'],
            'shortReason' => ['type' => 'string'],
        ],
    ];
}

function mattrics_extract_openai_output_text(array $response): string
{
    $direct = trim((string) ($response['output_text'] ?? ''));
    if ($direct !== '') {
        return $direct;
    }

    $chunks = [];
    foreach (($response['output'] ?? []) as $item) {
        if (!is_array($item)) {
            continue;
        }
        foreach (($item['content'] ?? []) as $content) {
            if (!is_array($content)) {
                continue;
            }
            if (isset($content['text']) && is_string($content['text'])) {
                $chunks[] = $content['text'];
                continue;
            }
            if (($content['type'] ?? '') === 'output_text' && isset($content['text']) && is_string($content['text'])) {
                $chunks[] = $content['text'];
            }
        }
    }

    return trim(implode("\n", $chunks));
}

function mattrics_request_openai_suggestion_for_prompt(array $prompt): array
{
    $mockResponse = getenv('MATTRICS_OPENAI_MOCK_RESPONSE');
    if (is_string($mockResponse) && trim($mockResponse) !== '') {
        $decoded = json_decode($mockResponse, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Mock OpenAI response was not valid JSON.');
        }
        return $decoded;
    }

    $openAi = mattrics_openai_config();
    if ($openAi['apiKey'] === '') {
        throw new RuntimeException('AI suggestion is not configured on the server.');
    }
    $schema = mattrics_openai_suggestion_schema();

    $response = mattrics_fetch_json(
        'https://api.openai.com/v1/responses',
        [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $openAi['apiKey'],
        ],
        'POST',
        json_encode([
            'model' => $openAi['model'],
            'input' => [
                ['role' => 'system', 'content' => $prompt['system']],
                ['role' => 'user', 'content' => $prompt['user']],
            ],
            'text' => [
                'format' => [
                    'type' => 'json_schema',
                    'name' => 'exercise_suggestion',
                    'strict' => true,
                    'schema' => $schema,
                ],
            ],
        ], JSON_UNESCAPED_SLASHES)
    );

    return [
        'model' => $response['model'] ?? $openAi['model'],
        'outputText' => mattrics_extract_openai_output_text($response),
    ];
}

function mattrics_request_openai_suggestion(array $unknown): array
{
    return mattrics_request_openai_suggestion_for_prompt(mattrics_build_unknown_suggestion_prompt($unknown));
}

function mattrics_request_openai_existing_exercise_suggestion(array $exercise): array
{
    return mattrics_request_openai_suggestion_for_prompt(mattrics_build_existing_exercise_suggestion_prompt($exercise));
}

function mattrics_validate_unknown_suggestion_payload(array $payload): array
{
    $canonicalName = trim((string) ($payload['canonicalName'] ?? ''));
    if ($canonicalName === '') {
        throw new RuntimeException('AI suggestion canonicalName was empty.');
    }

    $aliases = $payload['aliases'] ?? null;
    if (!is_array($aliases) || !array_is_list($aliases)) {
        throw new RuntimeException('AI suggestion aliases must be an array.');
    }

    $cleanAliases = [];
    foreach ($aliases as $alias) {
        if (!is_string($alias)) {
            throw new RuntimeException('AI suggestion aliases must contain only strings.');
        }
        $trimmed = trim($alias);
        if ($trimmed !== '') {
            $cleanAliases[] = $trimmed;
        }
    }
    $cleanAliases = array_values(array_unique($cleanAliases));

    $muscleWeights = mattrics_normalize_semantic_muscle_weights($payload, 'AI suggestion');
    $fatigueMultiplier = $payload['fatigueMultiplier'] ?? null;
    if (!is_numeric($fatigueMultiplier)) {
        throw new RuntimeException('AI suggestion fatigueMultiplier must be numeric.');
    }

    $bodyweightEligible = $payload['bodyweightEligible'] ?? null;
    if (!is_bool($bodyweightEligible)) {
        throw new RuntimeException('AI suggestion bodyweightEligible must be boolean.');
    }

    $setTypeHandling = (string) ($payload['setTypeHandling'] ?? '');
    if (!in_array($setTypeHandling, MATTRICS_EXERCISE_CONFIG_ALLOWED_SET_TYPES, true)) {
        throw new RuntimeException('AI suggestion setTypeHandling was invalid.');
    }

    $confidence = $payload['confidence'] ?? null;
    if (!is_numeric($confidence)) {
        throw new RuntimeException('AI suggestion confidence must be numeric.');
    }
    $confidence = max(0.0, min(1.0, (float) $confidence));

    $shortReason = trim((string) ($payload['shortReason'] ?? ''));
    if ($shortReason === '') {
        throw new RuntimeException('AI suggestion shortReason was empty.');
    }

    return [
        'canonicalName' => $canonicalName,
        'aliases' => $cleanAliases,
        'muscleWeights' => $muscleWeights,
        'fatigueMultiplier' => (float) $fatigueMultiplier,
        'bodyweightEligible' => $bodyweightEligible,
        'setTypeHandling' => $setTypeHandling,
        'confidence' => $confidence,
        'shortReason' => $shortReason,
    ];
}

function mattrics_decode_unknown_suggestion_output(string $outputText): array
{
    if (trim($outputText) === '') {
        throw new RuntimeException('AI suggestion response was empty.');
    }

    $decoded = json_decode($outputText, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('AI suggestion response was not valid JSON.');
    }

    return mattrics_validate_unknown_suggestion_payload($decoded);
}
