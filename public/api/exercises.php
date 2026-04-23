<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/exercise-config-repository.php';
require_once __DIR__ . '/exercise-config-ai.php';

mattrics_require_auth();

function mattrics_exercises_request_path(): string
{
    $path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
    if ($path === '') {
        return '/api/exercises.php';
    }
    return $path;
}

function mattrics_build_exercise_config_payload(array $metaExtra = []): array
{
    return [
        'exercises' => mattrics_read_exercise_config_records(),
        'activityTypes' => mattrics_read_activity_type_config_records(),
        'unknowns' => mattrics_prune_resolved_unknown_exercise_records(),
        'meta' => array_merge(mattrics_exercise_config_meta(), $metaExtra),
    ];
}

function mattrics_merge_preview_match_terms(array ...$lists): array
{
    return mattrics_normalize_unique_name_list(array_merge(...$lists));
}

function mattrics_build_unknown_preview_suggestion(array $unknown, array $suggestion, string $source): array
{
    $normalizedCanonical = mattrics_normalize_config_name((string) ($suggestion['canonicalName'] ?? ''));
    $aliases = array_values(array_filter(
        mattrics_merge_unique_name_lists($suggestion['aliases'] ?? [], $unknown['rawNames'] ?? []),
        static fn(string $alias): bool => mattrics_normalize_config_name($alias) !== $normalizedCanonical
    ));

    return [
        'canonicalName' => (string) ($suggestion['canonicalName'] ?? ''),
        'aliases' => $aliases,
        'matchTerms' => mattrics_merge_preview_match_terms(
            [(string) ($unknown['normalizedName'] ?? '')],
            $unknown['rawNames'] ?? []
        ),
        'muscleWeights' => $suggestion['muscleWeights'] ?? [],
        'fatigueMultiplier' => (float) ($suggestion['fatigueMultiplier'] ?? 1),
        'bodyweightEligible' => (bool) ($suggestion['bodyweightEligible'] ?? false),
        'setTypeHandling' => (string) ($suggestion['setTypeHandling'] ?? 'weight_reps'),
        'source' => $source,
        'confidence' => $suggestion['confidence'] ?? null,
        'shortReason' => $suggestion['shortReason'] ?? '',
    ];
}

function mattrics_build_existing_exercise_preview_suggestion(array $exercise, array $suggestion): array
{
    $normalizedCanonical = mattrics_normalize_config_name((string) ($suggestion['canonicalName'] ?? ''));
    $aliases = array_values(array_filter(
        mattrics_merge_unique_name_lists($exercise['aliases'] ?? [], $suggestion['aliases'] ?? []),
        static fn(string $alias): bool => mattrics_normalize_config_name($alias) !== $normalizedCanonical
    ));

    return [
        'canonicalName' => (string) ($suggestion['canonicalName'] ?? ''),
        'aliases' => $aliases,
        'matchTerms' => mattrics_merge_preview_match_terms(
            [(string) ($exercise['normalizedName'] ?? '')],
            $exercise['aliases'] ?? [],
            $exercise['matchTerms'] ?? []
        ),
        'muscleWeights' => $suggestion['muscleWeights'] ?? [],
        'fatigueMultiplier' => (float) ($suggestion['fatigueMultiplier'] ?? 1),
        'bodyweightEligible' => (bool) ($suggestion['bodyweightEligible'] ?? false),
        'setTypeHandling' => (string) ($suggestion['setTypeHandling'] ?? 'weight_reps'),
        'source' => 'ai_suggested',
        'confidence' => $suggestion['confidence'] ?? null,
        'shortReason' => $suggestion['shortReason'] ?? '',
    ];
}

function mattrics_handle_unknown_suggestion_request(string $unknownId): void
{
    mattrics_require_csrf();

    $unknown = mattrics_find_unknown_exercise_record($unknownId);
    if ($unknown === null) {
        mattrics_send_json(['error' => 'Unknown exercise not found.'], 404);
    }

    if (($unknown['sourceType'] ?? '') !== 'exercise') {
        mattrics_send_json(['error' => 'AI suggestion currently supports exercise unknowns only.'], 422);
    }

    $existing = mattrics_find_exercise_config_match((string) ($unknown['normalizedName'] ?? ''));
    if ($existing !== null) {
        mattrics_remove_unknown_exercise_record($unknownId);
        mattrics_send_json([
            'error' => 'A config already exists for this unknown exercise.',
            'exercise' => $existing,
            'unknowns' => mattrics_read_unknown_exercise_records(),
        ], 409);
    }

    $datasetMatch = mattrics_find_exercise_dataset_match($unknown);
    if ($datasetMatch !== null) {
        try {
            $preview = mattrics_build_unknown_preview_suggestion($unknown, [
                'canonicalName' => (string) ($datasetMatch['canonicalName'] ?? ''),
                'aliases' => $datasetMatch['aliases'] ?? [],
                'muscleWeights' => $datasetMatch['muscleWeights'] ?? [],
                'fatigueMultiplier' => (float) ($datasetMatch['fatigueMultiplier'] ?? 1),
                'bodyweightEligible' => (bool) ($datasetMatch['bodyweightEligible'] ?? false),
                'setTypeHandling' => (string) ($datasetMatch['setTypeHandling'] ?? 'weight_reps'),
                'confidence' => 0.96,
                'shortReason' => 'Matched the external exercise dataset.',
            ], 'external_dataset');
            $updatedUnknown = mattrics_update_unknown_exercise_ai_status($unknownId, 'succeeded');

            mattrics_log_exercise_ai_request(mattrics_build_exercise_ai_log_context($unknown, [
                'outcome' => 'dataset_match',
                'unknownId' => $unknownId,
                'model' => 'external_dataset',
                'resultStatus' => 'preview_ready',
                'configCreated' => false,
                'matchedVia' => 'external_dataset',
            ]));

            mattrics_send_json(array_merge(
                mattrics_build_exercise_config_payload([
                    'syncedAt' => gmdate('c'),
                    'matchedVia' => 'external_dataset',
                ]),
                [
                    'ok' => true,
                    'suggestion' => $preview,
                    'unknown' => $updatedUnknown,
                ]
            ));
        } catch (RuntimeException $exception) {
            mattrics_send_json(['error' => $exception->getMessage()], 422);
        }
    }

    try {
        $aiResponse = mattrics_request_openai_suggestion($unknown);
        $suggestion = mattrics_decode_unknown_suggestion_output((string) ($aiResponse['outputText'] ?? ''));
        $preview = mattrics_build_unknown_preview_suggestion($unknown, $suggestion, 'ai_suggested');
        $updatedUnknown = mattrics_update_unknown_exercise_ai_status($unknownId, 'succeeded');

        mattrics_log_exercise_ai_request(mattrics_build_exercise_ai_log_context($unknown, [
            'outcome' => 'success',
            'unknownId' => $unknownId,
            'model' => (string) ($aiResponse['model'] ?? MATTRICS_OPENAI_DEFAULT_MODEL),
            'resultStatus' => 'preview_ready',
            'configCreated' => false,
        ]));

        mattrics_send_json(array_merge(
            mattrics_build_exercise_config_payload([
                'syncedAt' => gmdate('c'),
                'confidence' => $suggestion['confidence'],
                'shortReason' => $suggestion['shortReason'],
                'model' => (string) ($aiResponse['model'] ?? MATTRICS_OPENAI_DEFAULT_MODEL),
            ]),
            [
                'ok' => true,
                'suggestion' => $preview,
                'unknown' => $updatedUnknown,
            ]
        ));
    } catch (RuntimeException $exception) {
        $status = str_contains($exception->getMessage(), 'configured on the server')
            ? 'failed'
            : 'invalid_response';
        mattrics_update_unknown_exercise_ai_status($unknownId, $status);
        mattrics_log_exercise_ai_request(mattrics_build_exercise_ai_log_context($unknown, [
            'outcome' => 'failure',
            'unknownId' => $unknownId,
            'model' => mattrics_openai_config()['model'],
            'resultStatus' => $status,
            'configCreated' => false,
            'message' => $exception->getMessage(),
        ]));

        $httpStatus = $status === 'invalid_response' ? 502 : 503;
        mattrics_send_json([
            'error' => $exception->getMessage(),
            'unknown' => mattrics_find_unknown_exercise_record($unknownId),
        ], $httpStatus);
    }
}

function mattrics_handle_exercise_create_request(?array $body = null): void
{
    mattrics_require_csrf();

    $body = is_array($body) ? $body : mattrics_read_json_body();

    try {
        $created = mattrics_create_exercise_config_record($body);
        $unknowns = mattrics_prune_resolved_unknown_exercise_records();
    } catch (RuntimeException $exception) {
        mattrics_send_json(['error' => $exception->getMessage()], 422);
    }

    mattrics_send_json(array_merge(
        mattrics_build_exercise_config_payload([
            'syncedAt' => gmdate('c'),
            'action' => 'created',
        ]),
        [
            'ok' => true,
            'exercise' => $created,
            'unknowns' => $unknowns,
            'recalculatedAt' => gmdate('c'),
        ]
    ), 201);
}

function mattrics_handle_exercise_update_request(string $exerciseId): void
{
    mattrics_require_csrf();

    if (mattrics_find_exercise_config_by_id($exerciseId) === null) {
        mattrics_send_json(['error' => 'Exercise config not found.'], 404);
    }

    $body = mattrics_read_json_body();

    try {
        $updated = mattrics_update_exercise_config_record($exerciseId, $body);
    } catch (RuntimeException $exception) {
        mattrics_send_json(['error' => $exception->getMessage()], 422);
    }

    mattrics_send_json(array_merge(
        mattrics_build_exercise_config_payload([
            'syncedAt' => gmdate('c'),
            'action' => 'updated',
        ]),
        [
            'ok' => true,
            'exercise' => $updated,
            'recalculatedAt' => gmdate('c'),
        ]
    ));
}

function mattrics_handle_exercise_suggestion_request(string $exerciseId): void
{
    mattrics_require_csrf();

    $exercise = mattrics_find_exercise_config_by_id($exerciseId);
    if ($exercise === null) {
        mattrics_send_json(['error' => 'Exercise config not found.'], 404);
    }

    try {
        $aiResponse = mattrics_request_openai_existing_exercise_suggestion($exercise);
        $suggestion = mattrics_decode_unknown_suggestion_output((string) ($aiResponse['outputText'] ?? ''));
        $preview = mattrics_build_existing_exercise_preview_suggestion($exercise, $suggestion);
    } catch (RuntimeException $exception) {
        $httpStatus = str_contains($exception->getMessage(), 'configured on the server') ? 503 : 502;
        mattrics_send_json(['error' => $exception->getMessage()], $httpStatus);
    }

    mattrics_log_exercise_ai_request(mattrics_build_exercise_ai_log_context([
        'normalizedName' => (string) ($exercise['normalizedName'] ?? ''),
        'rawNames' => [(string) ($exercise['canonicalName'] ?? '')],
        'sourceType' => 'exercise',
    ], [
        'outcome' => 'success',
        'exerciseId' => $exerciseId,
        'model' => (string) ($aiResponse['model'] ?? MATTRICS_OPENAI_DEFAULT_MODEL),
        'resultStatus' => 'preview_ready',
        'configCreated' => false,
    ]));

    mattrics_send_json(array_merge(
        mattrics_build_exercise_config_payload([
            'syncedAt' => gmdate('c'),
            'action' => 'suggested',
            'confidence' => $suggestion['confidence'],
            'shortReason' => $suggestion['shortReason'],
            'model' => (string) ($aiResponse['model'] ?? MATTRICS_OPENAI_DEFAULT_MODEL),
        ]),
        [
            'ok' => true,
            'suggestion' => $preview,
        ]
    ));
}

function mattrics_handle_exercise_merge_request(string $exerciseId): void
{
    mattrics_require_csrf();

    if (mattrics_find_exercise_config_by_id($exerciseId) === null) {
        mattrics_send_json(['error' => 'Exercise config not found.'], 404);
    }

    $body = mattrics_read_json_body();
    $targetExerciseId = trim((string) ($body['targetExerciseId'] ?? ''));
    if ($targetExerciseId === '') {
        mattrics_send_json(['error' => 'targetExerciseId is required.'], 422);
    }

    try {
        $updated = mattrics_merge_exercise_config_record_as_alias($exerciseId, $targetExerciseId);
    } catch (RuntimeException $exception) {
        mattrics_send_json(['error' => $exception->getMessage()], 422);
    }

    mattrics_send_json(array_merge(
        mattrics_build_exercise_config_payload([
            'syncedAt' => gmdate('c'),
            'action' => 'merged',
        ]),
        [
            'ok' => true,
            'exercise' => $updated,
            'mergedExerciseId' => $exerciseId,
            'recalculatedAt' => gmdate('c'),
        ]
    ));
}

function mattrics_handle_unknown_merge_request(string $unknownId): void
{
    mattrics_require_csrf();

    $unknown = mattrics_find_unknown_exercise_record($unknownId);
    if ($unknown === null) {
        mattrics_send_json(['error' => 'Unknown exercise not found.'], 404);
    }

    $body = mattrics_read_json_body();
    $targetExerciseId = trim((string) ($body['targetExerciseId'] ?? ''));
    if ($targetExerciseId === '') {
        mattrics_send_json(['error' => 'targetExerciseId is required.'], 422);
    }

    try {
        $updated = mattrics_merge_unknown_exercise_record_as_alias($unknownId, $targetExerciseId);
    } catch (RuntimeException $exception) {
        mattrics_send_json(['error' => $exception->getMessage()], 422);
    }

    mattrics_send_json(array_merge(
        mattrics_build_exercise_config_payload([
            'syncedAt' => gmdate('c'),
            'action' => 'merged',
        ]),
        [
            'ok' => true,
            'exercise' => $updated,
            'mergedUnknownId' => $unknownId,
            'recalculatedAt' => gmdate('c'),
        ]
    ));
}

function mattrics_handle_exercise_delete_request(string $exerciseId): void
{
    mattrics_require_csrf();

    if (mattrics_find_exercise_config_by_id($exerciseId) === null) {
        mattrics_send_json(['error' => 'Exercise config not found.'], 404);
    }

    try {
        $deleted = mattrics_delete_exercise_config_record($exerciseId);
    } catch (RuntimeException $exception) {
        mattrics_send_json(['error' => $exception->getMessage()], 422);
    }

    mattrics_send_json(array_merge(
        mattrics_build_exercise_config_payload([
            'syncedAt' => gmdate('c'),
            'action' => 'deleted',
        ]),
        [
            'ok' => true,
            'deletedExercise' => $deleted,
            'recalculatedAt' => gmdate('c'),
        ]
    ));
}

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $path = mattrics_exercises_request_path();

    if ($method === 'POST' && preg_match('#/api/exercises(?:\.php)?/unknowns/([^/]+)/suggest$#', $path, $matches)) {
        mattrics_handle_unknown_suggestion_request(urldecode((string) $matches[1]));
    }

    if ($method === 'POST' && preg_match('#/api/exercises(?:\.php)?/unknowns/([^/]+)/merge-alias$#', $path, $matches)) {
        mattrics_handle_unknown_merge_request(urldecode((string) $matches[1]));
    }

    if ($method === 'POST' && preg_match('#/api/exercises(?:\.php)?/([^/]+)/suggest$#', $path, $matches)) {
        mattrics_handle_exercise_suggestion_request(urldecode((string) $matches[1]));
    }

    if ($method === 'POST' && preg_match('#/api/exercises(?:\.php)?/([^/]+)/merge-alias$#', $path, $matches)) {
        mattrics_handle_exercise_merge_request(urldecode((string) $matches[1]));
    }

    if ($method === 'PATCH' && preg_match('#/api/exercises(?:\.php)?/([^/]+)$#', $path, $matches)) {
        mattrics_handle_exercise_update_request(urldecode((string) $matches[1]));
    }

    if ($method === 'DELETE' && preg_match('#/api/exercises(?:\.php)?/([^/]+)$#', $path, $matches)) {
        mattrics_handle_exercise_delete_request(urldecode((string) $matches[1]));
    }

    if ($method === 'GET') {
        mattrics_send_json(mattrics_build_exercise_config_payload());
    }

    if ($method === 'POST') {
        mattrics_require_csrf();
        $body = mattrics_read_json_body();
        if (preg_match('#/api/exercises(?:\.php)?$#', $path)) {
            if (array_key_exists('unknowns', $body)) {
                $incoming = $body['unknowns'] ?? null;
                if (!is_array($incoming) || !array_is_list($incoming)) {
                    mattrics_send_json(['error' => 'unknowns must be a JSON array.'], 422);
                }

                try {
                    $validated = [];
                    foreach ($incoming as $record) {
                        if (!is_array($record)) {
                            mattrics_send_json(['error' => 'Each unknown sync record must be an object.'], 422);
                        }
                        $validated[] = mattrics_validate_unknown_sync_input($record);
                    }
                } catch (RuntimeException $exception) {
                    mattrics_send_json(['error' => $exception->getMessage()], 422);
                }

                $synced = mattrics_sync_unknown_exercise_records($validated);
                mattrics_send_json([
                    'unknowns' => $synced,
                    'meta' => [
                        'syncedAt' => gmdate('c'),
                    ],
                ]);
            }

            mattrics_handle_exercise_create_request($body);
        }

        $incoming = $body['unknowns'] ?? null;
        if (!is_array($incoming) || !array_is_list($incoming)) {
            mattrics_send_json(['error' => 'unknowns must be a JSON array.'], 422);
        }

        try {
            $validated = [];
            foreach ($incoming as $record) {
                if (!is_array($record)) {
                    mattrics_send_json(['error' => 'Each unknown sync record must be an object.'], 422);
                }
                $validated[] = mattrics_validate_unknown_sync_input($record);
            }
        } catch (RuntimeException $exception) {
            mattrics_send_json(['error' => $exception->getMessage()], 422);
        }

        $synced = mattrics_sync_unknown_exercise_records($validated);
        mattrics_send_json([
            'unknowns' => $synced,
            'meta' => [
                'syncedAt' => gmdate('c'),
            ],
        ]);
    }

    header('Allow: GET, POST, PATCH, DELETE');
    mattrics_send_json(['error' => 'Method not allowed.'], 405);
} catch (RuntimeException $exception) {
    mattrics_send_json(['error' => $exception->getMessage()], 500);
}
