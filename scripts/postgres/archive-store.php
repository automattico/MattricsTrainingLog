<?php
declare(strict_types=1);

require_once __DIR__ . '/runtime.php';

function local_archive_refresh_review(PDO $pdo): void
{
    $pdo->exec(<<<'SQL'
WITH records AS MATERIALIZED (
    SELECT r.id, r.user_id, l.activity_id, s.source_kind, r.fit_sha256, r.started_at, r.ended_at,
           r.raw_payload->>'sport' AS sport,
           (r.raw_payload->'session'->>'total_elapsed_time')::numeric AS elapsed
    FROM mattrics.mattrics_source_records r
    JOIN mattrics.mattrics_data_sources s ON s.id = r.data_source_id
    JOIN mattrics.mattrics_activity_source_links l ON l.source_record_id = r.id
    WHERE jsonb_exists(r.raw_payload, 'archive_evidence')
), candidates AS (
    SELECT g.user_id, g.id AS garmin_record_id, s.id AS strava_record_id,
           g.fit_sha256 IS NOT NULL AND (g.fit_sha256, g.started_at, g.ended_at) =
               (s.fit_sha256, s.started_at, s.ended_at) AS exact_fit,
           abs(extract(epoch FROM g.started_at - s.started_at)) AS start_delta,
           abs(g.elapsed - s.elapsed) AS elapsed_delta,
           count(*) OVER (PARTITION BY g.id) AS strava_choices,
           count(*) OVER (PARTITION BY s.id) AS garmin_choices
    FROM records g JOIN records s
      ON g.user_id = s.user_id AND g.activity_id <> s.activity_id
     AND s.started_at BETWEEN g.started_at - interval '90 seconds' AND g.started_at + interval '90 seconds'
    WHERE g.source_kind = 'garmin' AND s.source_kind = 'strava'
      AND ((g.fit_sha256, g.started_at, g.ended_at) = (s.fit_sha256, s.started_at, s.ended_at)
        OR (g.sport = s.sport AND g.elapsed > 0 AND s.elapsed > 0
            AND abs(g.elapsed - s.elapsed) <= greatest(60, least(g.elapsed, s.elapsed) * 0.05)))
), refreshed AS (
    INSERT INTO mattrics.mattrics_archive_match_review
        (user_id, garmin_record_id, strava_record_id, reason, start_delta_seconds, elapsed_delta_seconds)
    SELECT user_id, garmin_record_id, strava_record_id,
           CASE WHEN exact_fit THEN 'conflicting_exact'
                WHEN strava_choices > 1 OR garmin_choices > 1 THEN 'ambiguous' ELSE 'probable' END,
           start_delta, elapsed_delta
    FROM candidates
    ON CONFLICT (garmin_record_id, strava_record_id) DO UPDATE SET
        (reason, start_delta_seconds, elapsed_delta_seconds) =
        (EXCLUDED.reason, EXCLUDED.start_delta_seconds, EXCLUDED.elapsed_delta_seconds)
    RETURNING garmin_record_id, strava_record_id
)
DELETE FROM mattrics.mattrics_archive_match_review r
WHERE r.decision IS NULL AND NOT EXISTS (
    SELECT 1 FROM refreshed WHERE (garmin_record_id, strava_record_id) = (r.garmin_record_id, r.strava_record_id)
)
SQL);
}

function local_archive_import(PDO $pdo, string $userKey, array $payload, ?int $maxBatches = null): array
{
    $provider = $payload['provider'];
    $digest = $payload['archive_sha256'];
    $records = $payload['records'];
    if (!in_array($provider, ['garmin', 'strava'], true) || !preg_match('/^[0-9a-f]{64}$/D', $digest)
        || !array_is_list($records) || $records === [] || count($records) > 20000
        || count(array_unique(array_column($records, 'id'))) !== count($records)) {
        throw new InvalidArgumentException('Invalid archive manifest.');
    }
    $manifest = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    $key = ['provider' => $provider, 'archive_sha256' => $digest];
    // Serialize history imports and review refreshes on this dedicated connection.
    $pdo->query('SELECT pg_advisory_lock(481871, 2)');
    try {
        local_postgres_query($pdo, <<<'SQL'
INSERT INTO mattrics.mattrics_archive_checkpoints
    (provider, archive_sha256, manifest_sha256, total_records, diagnostics, issues)
VALUES (:provider, :archive_sha256, :manifest, :total, :diagnostics, :issues)
ON CONFLICT (provider, archive_sha256) DO NOTHING
SQL, $key + [
            'manifest' => $manifest,
            'total' => count($records),
            'diagnostics' => json_encode($payload['report'], JSON_THROW_ON_ERROR),
            'issues' => json_encode($payload['issues'], JSON_THROW_ON_ERROR),
        ]);
        $checkpoint = local_postgres_query($pdo, <<<'SQL'
SELECT manifest_sha256, next_record FROM mattrics.mattrics_archive_checkpoints
WHERE provider = :provider AND archive_sha256 = :archive_sha256
SQL, $key)->fetch();
        if (!hash_equals($checkpoint['manifest_sha256'], $manifest)) {
            throw new RuntimeException('The decoded manifest changed for this archive. Use a fresh isolated preview for the new decoder.');
        }
        $cursor = (int) $checkpoint['next_record'];
        $resumedAt = $cursor;
        $batches = 0;
        while ($cursor < count($records) && ($maxBatches === null || $batches < $maxBatches)) {
            $chunk = array_slice($records, $cursor, 100);
            $next = $cursor + count($chunk);
            local_workout_import($pdo, $userKey, $provider, 'historical-archive', $chunk, false,
                static function () use ($pdo, $next, $key): void {
                    local_postgres_query($pdo, <<<'SQL'
UPDATE mattrics.mattrics_archive_checkpoints SET next_record = :next
WHERE provider = :provider AND archive_sha256 = :archive_sha256
SQL, $key + ['next' => $next]);
                    local_archive_refresh_review($pdo);
                });
            $cursor = $next;
            $batches++;
        }
        return ['resumed_at' => $resumedAt, 'checkpoint' => $cursor, 'total' => count($records), 'batches' => $batches];
    } finally {
        $pdo->query('SELECT pg_advisory_unlock(481871, 2)');
    }
}

function local_archive_counts(PDO $pdo): array
{
    $json = $pdo->query(<<<'SQL'
WITH sourced AS (
    SELECT a.id, a.activity_date, a.activity_type_name AS sport, l.link_kind, r.raw_payload->>'sport' AS source_sport, ds.source_kind
    FROM mattrics.mattrics_activities a
    JOIN mattrics.mattrics_activity_source_links l ON l.activity_id = a.id
    JOIN mattrics.mattrics_source_records r ON r.id = l.source_record_id
    JOIN mattrics.mattrics_data_sources ds ON ds.id = r.data_source_id
    WHERE jsonb_exists(r.raw_payload, 'archive_evidence')
), canonical AS (
    SELECT id, activity_date, coalesce(max(source_sport) FILTER (WHERE source_kind = 'strava'), max(sport)) AS sport
    FROM sourced GROUP BY id, activity_date
), years AS (
    SELECT extract(year FROM activity_date)::integer AS year, sport, count(*) AS count
    FROM canonical GROUP BY 1, 2 ORDER BY 1, 2
), review AS (
    SELECT reason, count(*) AS count FROM mattrics.mattrics_archive_match_review
    WHERE decision IS NULL GROUP BY reason
)
SELECT jsonb_build_object(
    'source_records', (SELECT count(*) FROM sourced),
    'canonical_workouts', (SELECT count(*) FROM canonical),
    'exact_links', (SELECT count(*) FROM sourced WHERE link_kind = 'exact_fit'),
    'manual_links', (SELECT count(*) FROM sourced WHERE link_kind = 'manual'),
    'unlinked_workouts', (SELECT count(*) FROM (SELECT id FROM sourced GROUP BY id HAVING count(*) = 1) singles),
    'oldest_day', (SELECT min(activity_date) FROM canonical),
    'newest_day', (SELECT max(activity_date) FROM canonical),
    'year_sport', (SELECT coalesce(jsonb_agg(to_jsonb(years)), '[]') FROM years),
    'review_pairs', (SELECT coalesce(jsonb_object_agg(reason, count), '{}') FROM review),
    'review_workouts', (
        SELECT count(DISTINCT l.activity_id)
        FROM mattrics.mattrics_archive_match_review q
        JOIN mattrics.mattrics_activity_source_links l ON l.source_record_id IN (q.garmin_record_id, q.strava_record_id)
        WHERE q.decision IS NULL
    )
)
SQL)->fetchColumn();
    return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
}
