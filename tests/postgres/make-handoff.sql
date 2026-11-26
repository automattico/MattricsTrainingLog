-- Model the Make branch's writes and its Postgres checkpoint in a disposable database.
BEGIN;
CREATE TEMP TABLE make_rehearsal_checkpoint (
    user_key text PRIMARY KEY,
    covered_through timestamptz NOT NULL
);

DO $$
DECLARE
    owner constant text := 'make-handoff-test';
    original_fetch constant timestamptz := clock_timestamp() - interval '2 hours';
    edited_fetch constant timestamptz := original_fetch + interval '1 hour';
    older constant jsonb := '{"started_at":"2026-01-01T10:00:00Z","title":"Original","distance_m":8300,"moving_s":3200}';
    edited constant jsonb := '{"started_at":"2026-01-01T10:05:00Z","title":"Renamed","distance_m":6600,"moving_s":2600}';
BEGIN
    INSERT INTO make_rehearsal_checkpoint VALUES (owner, original_fetch);
    PERFORM mattrics.receive_make_workout(owner, 'older', original_fetch, older);

    BEGIN
        PERFORM mattrics.receive_make_workout(owner, 'older', edited_fetch, edited);
        PERFORM mattrics.receive_make_workout(owner, 'late-arrival', edited_fetch, '[]');
        UPDATE make_rehearsal_checkpoint SET covered_through = edited_fetch WHERE user_key = owner;
        RAISE EXCEPTION 'Expected a payload constraint failure';
    EXCEPTION WHEN check_violation THEN
        NULL;
    END;

    IF (SELECT covered_through FROM make_rehearsal_checkpoint WHERE user_key = owner) <> original_fetch
        OR (SELECT workout FROM mattrics.make_workouts WHERE user_key = owner AND activity_id = 'older') IS DISTINCT FROM older
        OR EXISTS (SELECT FROM mattrics.make_workouts WHERE user_key = owner AND activity_id = 'late-arrival')
    THEN RAISE EXCEPTION 'Failed delivery changed a row or its checkpoint'; END IF;

    PERFORM mattrics.receive_make_workout(owner, 'older', edited_fetch, edited);
    PERFORM mattrics.receive_make_workout(owner, 'late-arrival', edited_fetch, '{"started_at":"2026-01-02T10:00:00Z"}');
    UPDATE make_rehearsal_checkpoint SET covered_through = edited_fetch WHERE user_key = owner;
END
$$;
COMMIT;

-- The preceding statement has committed. Replay after an ambiguous acknowledgement,
-- then redeliver the original observation out of order with its original fetch time.
DO $$
DECLARE
    owner constant text := 'make-handoff-test';
    saved mattrics.make_workouts%ROWTYPE;
    covered timestamptz;
BEGIN
    SELECT * INTO STRICT saved FROM mattrics.make_workouts WHERE user_key = owner AND activity_id = 'older';
    SELECT covered_through INTO STRICT covered FROM make_rehearsal_checkpoint WHERE user_key = owner;
    IF covered <> saved.revised_at OR saved.revised_at <= saved.received_at
        OR saved.workout <> '{"started_at":"2026-01-01T10:05:00Z","title":"Renamed","distance_m":6600,"moving_s":2600}'
        OR (SELECT count(*) FROM mattrics.current_make_workouts WHERE user_key = owner) <> 2
    THEN RAISE EXCEPTION 'Successful retry did not commit the rename, trim and late arrival'; END IF;

    PERFORM mattrics.receive_make_workout(owner, saved.activity_id, covered, saved.workout);
    PERFORM mattrics.receive_make_workout(owner, saved.activity_id, saved.received_at, '{"title":"Original"}');
    UPDATE make_rehearsal_checkpoint SET covered_through = greatest(covered_through, saved.received_at) WHERE user_key = owner;
    IF (SELECT to_jsonb(w) FROM mattrics.make_workouts w WHERE user_key = owner AND activity_id = saved.activity_id)
        IS DISTINCT FROM to_jsonb(saved)
        OR (SELECT covered_through FROM make_rehearsal_checkpoint WHERE user_key = owner) <> covered
        OR (SELECT count(*) FROM mattrics.current_make_workouts WHERE user_key = owner) <> 2
    THEN RAISE EXCEPTION 'Replay altered a stable delivery or moved the checkpoint'; END IF;
END
$$;
