<?php
declare(strict_types=1);

require_authenticated();
require_once MATTWARDEN_SITE_DIR . '/lib/bootstrap.php';
require_once MATTWARDEN_SITE_DIR . '/lib/exercise-config-repository.php';
require_once MATTWARDEN_SITE_DIR . '/lib/exercise-config-ai.php';
require_once MATTWARDEN_SITE_DIR . '/lib/foundation-read.php';
require_once MATTWARDEN_SITE_DIR . '/lib/foundation-write.php';

function mattrics_exercises_request_path(): string
{
    $pathInfo = (string) ($_SERVER['PATH_INFO'] ?? '');
    return '/api/exercises' . ($pathInfo !== '' ? '/' . ltrim($pathInfo, '/') : '');
}

function mattrics_use_canonical_exercise_config_source(): bool
{
    return mattrics_foundation_write_available();
}

function mattrics_join_exercise_config_warning(?string ...$parts): ?string
{
    $clean = array_values(array_filter(array_map(
        static fn(?string $part): string => trim((string) $part),
        $parts
    )));

    if ($clean === []) {
        return null;
    }

    return implode(' ', $clean);
}

function mattrics_find_exercise_config_by_id_for_active_source(string $id): ?array
{
    if (mattrics_use_canonical_exercise_config_source()) {
        return mattrics_foundation_write_find_exercise_by_id($id);
    }

    return mattrics_find_exercise_config_by_id($id);
}

function mattrics_find_activity_type_config_by_id_for_active_source(string $id): ?array
{
    if (mattrics_use_canonical_exercise_config_source()) {
        return mattrics_foundation_write_find_activity_type_by_id($id);
    }

    return mattrics_find_activity_type_config_by_id($id);
}

function mattrics_find_exercise_config_match_for_active_source(string $normalizedName): ?array
{
    if (mattrics_use_canonical_exercise_config_source()) {
        return mattrics_foundation_write_find_exercise_match($normalizedName);
    }

    return mattrics_find_exercise_config_match($normalizedName);
}

function mattrics_find_activity_type_config_match_for_active_source(string $normalizedName): ?array
{
    if (mattrics_use_canonical_exercise_config_source()) {
        return mattrics_foundation_write_find_activity_type_match($normalizedName);
    }

    return mattrics_find_activity_type_config_match($normalizedName);
}

function mattrics_filter_unknown_exercise_records_for_active_source(array $records): array
{
    return array_values(array_filter($records, static function (array $record): bool {
        $sourceType = (string) ($record['sourceType'] ?? '');
        $normalizedName = (string) ($record['normalizedName'] ?? '');
        if ($normalizedName === '') {
            return false;
        }

        if ($sourceType === 'exercise') {
            return mattrics_find_exercise_config_match_for_active_source($normalizedName) === null;
        }

        if ($sourceType === 'activityType') {
            return mattrics_find_activity_type_config_match_for_active_source($normalizedName) === null;
        }

        return true;
    }));
}

function mattrics_prune_resolved_unknown_exercise_records_for_active_source(): array
{
    $records = mattrics_read_unknown_exercise_records();
    $remaining = mattrics_filter_unknown_exercise_records_for_active_source($records);

    if (count($remaining) !== count($records)) {
        mattrics_write_unknown_exercise_records($remaining);
    }

    return $remaining;
}

function mattrics_run_canonical_config_post_write_sync(): array
{
    $warnings = [];

    try {
        mattrics_foundation_write_shadow_sync_legacy_catalogs();
    } catch (Throwable $throwable) {
        $warnings[] = 'Canonical write succeeded, but legacy config shadow sync failed.';
    }

    try {
        $unknowns = mattrics_prune_resolved_unknown_exercise_records_for_active_source();
    } catch (Throwable $throwable) {
        $warnings[] = 'Canonical write succeeded, but unknown review sync failed.';
        $unknowns = mattrics_filter_unknown_exercise_records_for_active_source(
            mattrics_read_unknown_exercise_records()
        );
    }

    return [
        'unknowns' => $unknowns,
        'warning' => mattrics_join_exercise_config_warning(...$warnings),
    ];
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

function mattrics_build_legacy_exercise_config_get_payload(?string $warning = null): array
{
    return mattrics_build_exercise_config_payload([
        'source' => 'legacy',
        'warning' => $warning,
        'lastSuccessfulSyncAt' => null,
    ]);
}

function mattrics_build_canonical_exercise_config_payload(array $metaExtra = []): array
{
    return [
        'exercises' => mattrics_foundation_read_exercises(),
        'activityTypes' => mattrics_foundation_read_activity_types(),
        'unknowns' => mattrics_prune_resolved_unknown_exercise_records_for_active_source(),
        'meta' => array_merge(mattrics_exercise_config_meta(), [
            'source' => 'canonical',
            'lastSuccessfulSyncAt' => mattrics_foundation_read_latest_successful_sync_at(),
            'warning' => null,
        ], $metaExtra),
    ];
}

function mattrics_build_active_exercise_config_payload(array $metaExtra = []): array
{
    if (mattrics_use_canonical_exercise_config_source()) {
        return mattrics_build_canonical_exercise_config_payload($metaExtra);
    }

    return mattrics_build_exercise_config_payload($metaExtra);
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
        'fatigueImpact' => (string) ($suggestion['fatigueImpact'] ?? 'normal'),
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
        'fatigueImpact' => (string) ($suggestion['fatigueImpact'] ?? 'normal'),
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
    mattwarden_require_same_origin();
    mattwarden_require_csrf();

    $unknown = mattrics_find_unknown_exercise_record($unknownId);
    if ($unknown === null) {
        mattrics_send_json(['error' => 'Unknown exercise not found.'], 404);
    }

    if (($unknown['sourceType'] ?? '') !== 'exercise') {
        mattrics_send_json(['error' => 'AI suggestion currently supports exercise unknowns only.'], 422);
    }

    $existing = mattrics_find_exercise_config_match_for_active_source((string) ($unknown['normalizedName'] ?? ''));
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
                'fatigueImpact' => (string) ($datasetMatch['fatigueImpact'] ?? 'normal'),
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
                mattrics_build_active_exercise_config_payload([
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
            mattrics_build_active_exercise_config_payload([
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
    mattwarden_require_same_origin();
    mattwarden_require_csrf();

    $body = is_array($body) ? $body : mattrics_read_json_body();
    $configType = (string) ($body['configType'] ?? 'exercise');
    $warning = null;

    try {
        if (mattrics_use_canonical_exercise_config_source()) {
            if ($configType === 'activityType') {
                $created = mattrics_foundation_write_create_activity_type_config_record($body);
            } else {
                $created = mattrics_foundation_write_create_exercise_config_record($body);
            }
            $sync = mattrics_run_canonical_config_post_write_sync();
            $unknowns = $sync['unknowns'];
            $warning = $sync['warning'];
        } elseif ($configType === 'activityType') {
            $created = mattrics_create_activity_type_config_record($body);
        } else {
            $created = mattrics_create_exercise_config_record($body);
        }
        if (!isset($unknowns)) {
            $unknowns = mattrics_prune_resolved_unknown_exercise_records();
        }
    } catch (RuntimeException $exception) {
        mattrics_send_json(['error' => $exception->getMessage()], 422);
    }

    mattrics_send_json(array_merge(
        mattrics_build_active_exercise_config_payload([
            'syncedAt' => gmdate('c'),
            'action' => 'created',
            'warning' => $warning,
        ]),
        [
            'ok' => true,
            'configType' => $configType,
            ...($configType === 'activityType' ? ['activityType' => $created] : ['exercise' => $created]),
            'unknowns' => $unknowns,
            'recalculatedAt' => gmdate('c'),
        ]
    ), 201);
}

function mattrics_handle_exercise_update_request(string $exerciseId): void
{
    mattwarden_require_same_origin();
    mattwarden_require_csrf();

    $body = mattrics_read_json_body();
    $exercise = mattrics_find_exercise_config_by_id_for_active_source($exerciseId);
    $activityType = $exercise === null ? mattrics_find_activity_type_config_by_id_for_active_source($exerciseId) : null;
    if ($exercise === null && $activityType === null) {
        mattrics_send_json(['error' => 'Config not found.'], 404);
    }

    $warning = null;
    try {
        if (mattrics_use_canonical_exercise_config_source()) {
            if ($exercise !== null) {
                $updated = mattrics_foundation_write_update_exercise_config_record($exerciseId, $body);
                $configType = 'exercise';
            } else {
                $updated = mattrics_foundation_write_update_activity_type_config_record($exerciseId, $body);
                $configType = 'activityType';
            }
            $warning = mattrics_run_canonical_config_post_write_sync()['warning'];
        } elseif ($exercise !== null) {
            $updated = mattrics_update_exercise_config_record($exerciseId, $body);
            $configType = 'exercise';
        } else {
            $updated = mattrics_update_activity_type_config_record($exerciseId, $body);
            $configType = 'activityType';
        }
    } catch (RuntimeException $exception) {
        mattrics_send_json(['error' => $exception->getMessage()], 422);
    }

    mattrics_send_json(array_merge(
        mattrics_build_active_exercise_config_payload([
            'syncedAt' => gmdate('c'),
            'action' => 'updated',
            'warning' => $warning,
        ]),
        [
            'ok' => true,
            'configType' => $configType,
            ...($configType === 'activityType' ? ['activityType' => $updated] : ['exercise' => $updated]),
            'recalculatedAt' => gmdate('c'),
        ]
    ));
}

function mattrics_handle_exercise_suggestion_request(string $exerciseId): void
{
    mattwarden_require_same_origin();
    mattwarden_require_csrf();

    $exercise = mattrics_find_exercise_config_by_id_for_active_source($exerciseId);
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
        mattrics_build_active_exercise_config_payload([
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
    mattwarden_require_same_origin();
    mattwarden_require_csrf();

    if (mattrics_find_exercise_config_by_id_for_active_source($exerciseId) === null) {
        mattrics_send_json(['error' => 'Exercise config not found.'], 404);
    }

    $body = mattrics_read_json_body();
    $targetExerciseId = trim((string) ($body['targetExerciseId'] ?? ''));
    if ($targetExerciseId === '') {
        mattrics_send_json(['error' => 'targetExerciseId is required.'], 422);
    }

    $warning = null;
    try {
        if (mattrics_use_canonical_exercise_config_source()) {
            $updated = mattrics_foundation_write_merge_exercise_config_record_as_alias($exerciseId, $targetExerciseId);
            $warning = mattrics_run_canonical_config_post_write_sync()['warning'];
        } else {
            $updated = mattrics_merge_exercise_config_record_as_alias($exerciseId, $targetExerciseId);
        }
    } catch (RuntimeException $exception) {
        mattrics_send_json(['error' => $exception->getMessage()], 422);
    }

    mattrics_send_json(array_merge(
        mattrics_build_active_exercise_config_payload([
            'syncedAt' => gmdate('c'),
            'action' => 'merged',
            'warning' => $warning,
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
    mattwarden_require_same_origin();
    mattwarden_require_csrf();

    $unknown = mattrics_find_unknown_exercise_record($unknownId);
    if ($unknown === null) {
        mattrics_send_json(['error' => 'Unknown exercise not found.'], 404);
    }

    $body = mattrics_read_json_body();
    $targetExerciseId = trim((string) ($body['targetExerciseId'] ?? ''));
    if ($targetExerciseId === '') {
        mattrics_send_json(['error' => 'targetExerciseId is required.'], 422);
    }

    $warning = null;
    try {
        if (mattrics_use_canonical_exercise_config_source()) {
            $updated = mattrics_foundation_write_merge_unknown_exercise_record_as_alias($unknownId, $targetExerciseId);
            mattrics_remove_unknown_exercise_record($unknownId);
            $warning = mattrics_run_canonical_config_post_write_sync()['warning'];
        } else {
            $updated = mattrics_merge_unknown_exercise_record_as_alias($unknownId, $targetExerciseId);
        }
    } catch (RuntimeException $exception) {
        mattrics_send_json(['error' => $exception->getMessage()], 422);
    }

    mattrics_send_json(array_merge(
        mattrics_build_active_exercise_config_payload([
            'syncedAt' => gmdate('c'),
            'action' => 'merged',
            'warning' => $warning,
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
    mattwarden_require_same_origin();
    mattwarden_require_csrf();
    $exercise = mattrics_find_exercise_config_by_id_for_active_source($exerciseId);
    $activityType = $exercise === null ? mattrics_find_activity_type_config_by_id_for_active_source($exerciseId) : null;
    if ($exercise === null && $activityType === null) {
        mattrics_send_json(['error' => 'Config not found.'], 404);
    }

    $warning = null;
    try {
        if (mattrics_use_canonical_exercise_config_source()) {
            if ($exercise !== null) {
                $deleted = mattrics_foundation_write_delete_exercise_config_record($exerciseId);
                $configType = 'exercise';
            } else {
                $deleted = mattrics_foundation_write_delete_activity_type_config_record($exerciseId);
                $configType = 'activityType';
            }
            $warning = mattrics_run_canonical_config_post_write_sync()['warning'];
        } elseif ($exercise !== null) {
            $deleted = mattrics_delete_exercise_config_record($exerciseId);
            $configType = 'exercise';
        } else {
            $deleted = mattrics_delete_activity_type_config_record($exerciseId);
            $configType = 'activityType';
        }
    } catch (RuntimeException $exception) {
        mattrics_send_json(['error' => $exception->getMessage()], 422);
    }

    mattrics_send_json(array_merge(
        mattrics_build_active_exercise_config_payload([
            'syncedAt' => gmdate('c'),
            'action' => 'deleted',
            'warning' => $warning,
        ]),
        [
            'ok' => true,
            'configType' => $configType,
            ...($configType === 'activityType' ? ['deletedActivityType' => $deleted] : ['deletedExercise' => $deleted]),
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
        if (mattrics_foundation_read_enabled()) {
            try {
                mattrics_send_json(mattrics_build_canonical_exercise_config_payload());
            } catch (Throwable $throwable) {
                mattrics_send_json(mattrics_build_legacy_exercise_config_get_payload(
                    'Canonical read failed. Showing legacy exercise config data instead.'
                ));
            }
        }

        mattrics_send_json(mattrics_build_legacy_exercise_config_get_payload());
    }

    if ($method === 'POST') {
        mattwarden_require_same_origin();
        mattwarden_require_csrf();
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
    error_log('Mattrics exercise endpoint failed: ' . $exception->getMessage());
    mattrics_send_json(['error' => 'Exercise configuration is temporarily unavailable.'], 500);
}
