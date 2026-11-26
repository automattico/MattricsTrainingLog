CREATE TABLE mattrics.mattrics_archive_checkpoints (
    provider text NOT NULL CHECK (provider IN ('garmin', 'strava')),
    archive_sha256 text NOT NULL CHECK (archive_sha256 ~ '^[0-9a-f]{64}$'),
    manifest_sha256 text NOT NULL CHECK (manifest_sha256 ~ '^[0-9a-f]{64}$'),
    total_records integer NOT NULL CHECK (total_records > 0),
    next_record integer NOT NULL DEFAULT 0 CHECK (next_record BETWEEN 0 AND total_records),
    diagnostics jsonb NOT NULL,
    issues jsonb NOT NULL,
    PRIMARY KEY (provider, archive_sha256)
);

CREATE TABLE mattrics.mattrics_archive_match_review (
    user_id uuid NOT NULL,
    garmin_record_id uuid NOT NULL,
    strava_record_id uuid NOT NULL,
    reason text NOT NULL CHECK (reason IN ('conflicting_exact', 'ambiguous', 'probable')),
    start_delta_seconds numeric NOT NULL,
    elapsed_delta_seconds numeric,
    decision text CHECK (decision = 'keep_separate'),
    PRIMARY KEY (garmin_record_id, strava_record_id),
    FOREIGN KEY (user_id, garmin_record_id) REFERENCES mattrics.mattrics_source_records (user_id, id) ON DELETE CASCADE,
    FOREIGN KEY (user_id, strava_record_id) REFERENCES mattrics.mattrics_source_records (user_id, id) ON DELETE CASCADE
);
