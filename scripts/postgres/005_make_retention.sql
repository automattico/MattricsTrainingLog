-- Make deliveries are kept separately from archive imports and direct providers.
CREATE TABLE mattrics.make_retention (
    user_key text PRIMARY KEY CHECK (btrim(user_key) <> ''),
    delete_after_week boolean NOT NULL DEFAULT true
);

CREATE TABLE mattrics.make_workouts (
    user_key text NOT NULL REFERENCES mattrics.make_retention,
    activity_id text NOT NULL CHECK (btrim(activity_id) <> ''),
    received_at timestamptz NOT NULL,
    revised_at timestamptz NOT NULL,
    workout jsonb NOT NULL CHECK (jsonb_typeof(workout) = 'object'),
    PRIMARY KEY (user_key, activity_id)
);
CREATE INDEX make_workouts_expiry ON mattrics.make_workouts (user_key, received_at);

CREATE VIEW mattrics.current_make_workouts AS
SELECT w.* FROM mattrics.make_workouts w JOIN mattrics.make_retention r USING (user_key)
WHERE NOT r.delete_after_week OR w.received_at > statement_timestamp() - interval '168 hours';

CREATE FUNCTION mattrics.prune_make_workouts(owner_key text) RETURNS void LANGUAGE plpgsql AS $$
BEGIN
    PERFORM 1 FROM mattrics.make_retention WHERE user_key = owner_key AND delete_after_week FOR UPDATE;
    IF FOUND THEN
        DELETE FROM mattrics.make_workouts WHERE user_key = owner_key
          AND received_at <= clock_timestamp() - interval '168 hours';
    END IF;
END
$$;

CREATE FUNCTION mattrics.set_make_retention(owner_key text, expire boolean) RETURNS void LANGUAGE plpgsql AS $$
BEGIN
    INSERT INTO mattrics.make_retention(user_key) VALUES (owner_key) ON CONFLICT DO NOTHING;
    PERFORM 1 FROM mattrics.make_retention WHERE user_key = owner_key FOR UPDATE;
    -- Apply the old rule first so disabling expiry cannot bring expired rows back.
    PERFORM mattrics.prune_make_workouts(owner_key);
    UPDATE mattrics.make_retention SET delete_after_week = expire WHERE user_key = owner_key;
    PERFORM mattrics.prune_make_workouts(owner_key);
END
$$;

CREATE FUNCTION mattrics.receive_make_workout(owner_key text, source_id text, fetched_at timestamptz, payload jsonb)
RETURNS void LANGUAGE plpgsql AS $$
DECLARE
    expire boolean;
BEGIN
    IF fetched_at IS NULL OR fetched_at > clock_timestamp() THEN
        RAISE EXCEPTION 'Expected the original UTC fetch timestamp';
    END IF;
    INSERT INTO mattrics.make_retention(user_key) VALUES (owner_key) ON CONFLICT DO NOTHING;
    SELECT delete_after_week INTO STRICT expire FROM mattrics.make_retention WHERE user_key = owner_key FOR UPDATE;
    PERFORM mattrics.prune_make_workouts(owner_key);
    IF expire AND fetched_at <= clock_timestamp() - interval '168 hours' THEN
        RETURN;
    END IF;
    INSERT INTO mattrics.make_workouts AS w VALUES (owner_key, source_id, fetched_at, fetched_at, payload)
    ON CONFLICT (user_key, activity_id) DO UPDATE SET workout = EXCLUDED.workout,
        revised_at = EXCLUDED.revised_at
    WHERE EXCLUDED.revised_at > w.revised_at;
END
$$;
