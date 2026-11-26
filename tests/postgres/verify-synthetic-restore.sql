SET TIME ZONE 'UTC';
BEGIN ISOLATION LEVEL REPEATABLE READ READ ONLY;

-- Require the seeded relationships and set values before comparing snapshots.
SELECT
    (SELECT count(*) FROM mattrics.mattrics_activities) = 2
    AND (SELECT count(*) FROM mattrics.mattrics_source_records) = 2
    AND (SELECT count(*) FROM mattrics.mattrics_activity_source_links) = 2
    AND (SELECT count(*) FROM mattrics.mattrics_activity_exercises) = 2
    AND (SELECT count(*) FROM mattrics.mattrics_activity_sets) = 3
    AND (
        SELECT count(*) = 2
        FROM mattrics.mattrics_activity_source_links l
        JOIN mattrics.mattrics_source_records r ON r.id = l.source_record_id
        JOIN mattrics.mattrics_activities a ON a.id = l.activity_id
        WHERE l.link_kind = 'primary' AND (
            (r.external_id = 'synthetic-garmin-run' AND a.activity_name = 'Synthetic riverside run')
            OR (r.external_id = 'synthetic-hevy-workout' AND a.activity_name = 'Synthetic strength')
        )
    )
    AND (
        SELECT count(*) = 3
        FROM mattrics.mattrics_activity_exercises e
        JOIN mattrics.mattrics_activity_sets s ON s.activity_exercise_id = e.id
        JOIN mattrics.mattrics_activities a ON a.id = e.activity_id
        WHERE a.activity_name = 'Synthetic strength' AND (
            (e.provider_template_id = 'SYNTH-BENCH' AND s.weight_kg = 20 AND s.reps = 12 AND s.set_type = 'warmup')
            OR (e.provider_template_id = 'SYNTH-BENCH' AND s.weight_kg = 62.5 AND s.reps = 8 AND s.rpe = 8.5)
            OR (e.provider_template_id = 'SYNTH-TREAD' AND s.duration_minutes = 5.5 AND s.distance_km = 0.9)
        )
    ) AS fixture_ok
\gset
\if :fixture_ok
\else
DO $$ BEGIN RAISE EXCEPTION 'Synthetic staging fixtures did not match expected counts and values'; END $$;
\endif

-- Compare all rows, including identifiers and source evidence, in the small
-- synthetic schema without writing payloads to the verification output.
SELECT format(
    'SELECT %L, count(*), md5(coalesce(jsonb_agg(row_data ORDER BY row_data)::text, %L))
    FROM (SELECT to_jsonb(t) AS row_data FROM mattrics.%I t) rows',
    tablename, '[]', tablename
)
FROM pg_catalog.pg_tables
WHERE schemaname = 'mattrics'
ORDER BY tablename
\gexec
COMMIT;
