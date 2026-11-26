-- Decisions refer to stable source UUIDs. Undo removes a decision from the
-- projection; provider-owned records, measurements and children stay intact.
CREATE TABLE mattrics.workout_reviews (
    revision bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    user_id uuid NOT NULL REFERENCES mattrics.mattrics_users,
    action text NOT NULL CHECK (action IN ('link','separate','prefer','later')),
    left_sources uuid[] NOT NULL CHECK (cardinality(left_sources) > 0),
    right_sources uuid[] NOT NULL,
    preferred uuid,
    undone boolean NOT NULL DEFAULT false,
    FOREIGN KEY (user_id, preferred) REFERENCES mattrics.mattrics_source_records(user_id,id)
);
CREATE INDEX workout_reviews_user ON mattrics.workout_reviews(user_id,revision);

-- Remember independently corroborated recording identity across subsequent edits.
CREATE TABLE mattrics.workout_auto_pairs (
    user_id uuid NOT NULL,
    garmin uuid NOT NULL,
    strava uuid NOT NULL,
    PRIMARY KEY(user_id,garmin,strava),
    FOREIGN KEY(user_id,garmin) REFERENCES mattrics.mattrics_source_records(user_id,id),
    FOREIGN KEY(user_id,strava) REFERENCES mattrics.mattrics_source_records(user_id,id)
);
