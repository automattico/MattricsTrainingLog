DO $$
DECLARE
    stamp timestamptz := clock_timestamp() - interval '1 hour';
BEGIN
    PERFORM mattrics.receive_make_workout('retention-test','1',stamp,'{"title":"first"}');
    PERFORM mattrics.receive_make_workout('retention-test','1',stamp + interval '1 minute','{"title":"edit"}');
    PERFORM mattrics.receive_make_workout('retention-test','1',stamp,'{"title":"first"}');
    IF NOT EXISTS (SELECT FROM mattrics.current_make_workouts WHERE user_key='retention-test'
        AND received_at=stamp AND workout->>'title'='edit') THEN RAISE EXCEPTION 'Replay changed edit or expiry'; END IF;
    PERFORM mattrics.receive_make_workout('retention-test','expired',stamp - interval '7 days','{}');
    IF EXISTS (SELECT FROM mattrics.make_workouts WHERE activity_id='expired') THEN RAISE EXCEPTION 'Expired replay retained'; END IF;
    UPDATE mattrics.make_workouts SET received_at=stamp - interval '7 days' WHERE user_key='retention-test';
    IF EXISTS (SELECT FROM mattrics.current_make_workouts WHERE user_key='retention-test') THEN RAISE EXCEPTION 'Expired row visible'; END IF;
    PERFORM mattrics.set_make_retention('retention-test', false);
    IF EXISTS (SELECT FROM mattrics.make_workouts WHERE user_key='retention-test') THEN RAISE EXCEPTION 'Expired row resurrected'; END IF;
    PERFORM mattrics.receive_make_workout('retention-test','old',stamp - interval '30 days','{}');
    PERFORM mattrics.set_make_retention('another-owner', false);
    PERFORM mattrics.receive_make_workout('another-owner','old',stamp - interval '30 days','{}');
    PERFORM mattrics.prune_make_workouts('retention-test');
    IF (SELECT count(*) FROM mattrics.current_make_workouts WHERE activity_id='old') <> 2 THEN RAISE EXCEPTION 'Indefinite retention failed'; END IF;
    PERFORM mattrics.set_make_retention('retention-test', true);
    IF EXISTS (SELECT FROM mattrics.make_workouts WHERE user_key='retention-test') OR
       NOT EXISTS (SELECT FROM mattrics.make_workouts WHERE user_key='another-owner') THEN RAISE EXCEPTION 'Re-enabled expiry failed'; END IF;
END
$$;
