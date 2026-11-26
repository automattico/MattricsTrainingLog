<?php
declare(strict_types=1);

require_once __DIR__ . '/archive-store.php';

function local_archive_review_queue(PDO $pdo): array
{
    return $pdo->query(<<<'SQL'
SELECT garmin_record_id, strava_record_id, reason, start_delta_seconds, elapsed_delta_seconds, decision
FROM mattrics.mattrics_archive_match_review
ORDER BY decision NULLS FIRST, reason, garmin_record_id, strava_record_id
SQL)->fetchAll();
}

function local_archive_review_exact_pairs(PDO $pdo): array
{
    return $pdo->query(<<<'SQL'
SELECT g.source_record_id AS garmin_record_id, s.source_record_id AS strava_record_id
FROM mattrics.mattrics_activity_source_links g
JOIN mattrics.mattrics_activity_source_links s ON s.activity_id = g.activity_id AND s.link_kind = 'exact_fit'
JOIN mattrics.mattrics_source_records gr ON gr.id = g.source_record_id
JOIN mattrics.mattrics_source_records sr ON sr.id = s.source_record_id
JOIN mattrics.mattrics_data_sources gd ON gd.id = gr.data_source_id AND gd.source_kind = 'garmin'
JOIN mattrics.mattrics_data_sources sd ON sd.id = sr.data_source_id AND sd.source_kind = 'strava'
WHERE g.link_kind = 'primary' AND jsonb_exists(gr.raw_payload, 'archive_evidence')
    AND jsonb_exists(sr.raw_payload, 'archive_evidence')
ORDER BY g.source_record_id, s.source_record_id
SQL)->fetchAll();
}

function local_archive_review_pair(PDO $pdo, string $garminId, string $stravaId): array
{
    $params = ['garmin' => $garminId, 'strava' => $stravaId];
    $rows = local_postgres_query($pdo, <<<'SQL'
SELECT r.*, l.activity_id, l.link_kind, ds.source_kind,
       (SELECT count(*) FROM mattrics.mattrics_activity_source_links x WHERE x.activity_id = l.activity_id) AS link_count,
       EXISTS (SELECT 1 FROM mattrics.mattrics_activity_exercises e WHERE e.activity_id = l.activity_id) AS has_children
FROM mattrics.mattrics_source_records r
JOIN mattrics.mattrics_activity_source_links l ON l.source_record_id = r.id
JOIN mattrics.mattrics_data_sources ds ON ds.id = r.data_source_id
WHERE jsonb_exists(r.raw_payload, 'archive_evidence')
  AND ((r.id = :garmin AND ds.source_kind = 'garmin') OR (r.id = :strava AND ds.source_kind = 'strava'))
ORDER BY ds.source_kind
SQL, $params)->fetchAll();
    if (count($rows) !== 2 || $rows[0]['user_id'] !== $rows[1]['user_id']) {
        throw new InvalidArgumentException('Expected two archive records for the same user.');
    }
    return $rows;
}

function local_archive_restore_strava(PDO $pdo, array $strava, string $activityId): void
{
    local_postgres_query($pdo, 'DELETE FROM mattrics.mattrics_activity_source_links WHERE source_record_id = :id',
        ['id' => $strava['id']]);
    local_workout_persist(
        $pdo,
        array_intersect_key($strava, array_flip(['user_id', 'data_source_id', 'import_batch_id'])),
        'strava',
        local_workout_adapt('strava', json_decode($strava['raw_payload'], true, 512, JSON_THROW_ON_ERROR), 'Europe/Berlin'),
        $activityId
    );
}

function local_archive_attach_strava(PDO $pdo, array $garmin, array $strava, bool $exact): void
{
    if ($garmin['link_kind'] !== 'primary' || $strava['link_kind'] !== 'primary'
        || (int) $garmin['link_count'] !== 1 || (int) $strava['link_count'] !== 1
        || $strava['has_children'] || $garmin['activity_id'] === $strava['activity_id']) {
        throw new RuntimeException('Link requires two independent archive workouts.');
    }
    $evidence = $exact
        ? array_intersect_key($strava, array_flip(['fit_sha256', 'started_at', 'ended_at']))
        : ['decision' => 'link'];
    local_postgres_query($pdo, <<<'SQL'
UPDATE mattrics.mattrics_activity_source_links
SET activity_id = :garmin_activity, link_kind = :kind, match_evidence = :evidence
WHERE source_record_id = :strava
SQL, [
        'garmin_activity' => $garmin['activity_id'], 'strava' => $strava['id'],
        'kind' => $exact ? 'exact_fit' : 'manual', 'evidence' => json_encode($evidence, JSON_THROW_ON_ERROR),
    ]);
    local_postgres_query($pdo, 'DELETE FROM mattrics.mattrics_activities WHERE id = :id',
        ['id' => $strava['activity_id']]);
}

function local_archive_decide(PDO $pdo, string $action, string $garminId, string $stravaId): void
{
    $pdo->beginTransaction();
    try {
        $pdo->query('SELECT pg_advisory_xact_lock(481871, 2)');
        [$garmin, $strava] = local_archive_review_pair($pdo, $garminId, $stravaId);
        $params = ['garmin' => $garminId, 'strava' => $stravaId];
        $candidate = local_postgres_query($pdo, <<<'SQL'
SELECT decision, original_strava_activity_id FROM mattrics.mattrics_archive_match_review
WHERE garmin_record_id = :garmin AND strava_record_id = :strava
SQL, $params)->fetch();
        $decision = $candidate['decision'] ?? null;
        $original = null;
        if ($action === 'undo' && $decision !== null) {
            if ($decision === 'link') {
                if ($strava['link_kind'] !== 'manual' || $strava['activity_id'] !== $garmin['activity_id']) {
                    throw new RuntimeException('The reviewed link has changed.');
                }
                local_archive_restore_strava($pdo, $strava, $candidate['original_strava_activity_id']);
            } elseif ($decision === 'split') {
                if ($garmin['fit_sha256'] === null || $garmin['fit_sha256'] !== $strava['fit_sha256']
                    || $garmin['started_at'] !== $strava['started_at'] || $garmin['ended_at'] !== $strava['ended_at']) {
                    throw new RuntimeException('The recordings no longer have the same FIT session.');
                }
                local_archive_attach_strava($pdo, $garmin, $strava, true);
            }
            $action = null;
        } elseif ($action === 'split' && $decision === null && $garmin['activity_id'] === $strava['activity_id']
            && $garmin['link_kind'] === 'primary' && $strava['link_kind'] === 'exact_fit' && (int) $garmin['link_count'] === 2) {
            $activityId = $pdo->query('SELECT gen_random_uuid()')->fetchColumn();
            local_archive_restore_strava($pdo, $strava, $activityId);
        } elseif (in_array($action, ['keep_separate', 'link'], true) && $candidate !== false && $decision === null) {
            if ($action === 'link') {
                $original = $strava['activity_id'];
                local_archive_attach_strava($pdo, $garmin, $strava, false);
            }
        } else {
            throw new InvalidArgumentException('Decision does not apply to this pair.');
        }
        local_postgres_query($pdo, <<<'SQL'
INSERT INTO mattrics.mattrics_archive_match_review
    (user_id, garmin_record_id, strava_record_id, reason, start_delta_seconds, elapsed_delta_seconds,
     decision, original_strava_activity_id)
VALUES (:user, :garmin, :strava, 'conflicting_exact', 0, 0, :decision, :original)
ON CONFLICT (garmin_record_id, strava_record_id) DO UPDATE
SET decision = EXCLUDED.decision, original_strava_activity_id = EXCLUDED.original_strava_activity_id
SQL, $params + ['user' => $garmin['user_id'], 'decision' => $action, 'original' => $original]);
        local_archive_refresh_review($pdo);
        $pdo->commit();
    } catch (Throwable $failure) {
        $pdo->rollBack();
        throw $failure;
    }
}
