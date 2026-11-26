-- Retain the Foundation table and column names used by the dashboard read adapter.
CREATE TABLE mattrics.mattrics_users (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    external_key text NOT NULL UNIQUE,
    display_name text NOT NULL,
    timezone_name text NOT NULL DEFAULT 'UTC',
    is_active boolean NOT NULL DEFAULT true
);

CREATE TABLE mattrics.mattrics_data_sources (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id uuid NOT NULL REFERENCES mattrics.mattrics_users (id),
    source_key text NOT NULL,
    source_kind text NOT NULL,
    account_key text NOT NULL CHECK (btrim(account_key) <> ''),
    UNIQUE (user_id, source_kind, account_key),
    UNIQUE (user_id, id)
);

CREATE TABLE mattrics.mattrics_import_batches (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id uuid NOT NULL,
    data_source_id uuid NOT NULL,
    status text NOT NULL DEFAULT 'running' CHECK (status IN ('running', 'succeeded', 'failed')),
    imported_row_count integer NOT NULL DEFAULT 0 CHECK (imported_row_count >= 0),
    applied_activity_count integer NOT NULL DEFAULT 0 CHECK (applied_activity_count >= 0),
    error_summary text,
    started_at timestamptz NOT NULL DEFAULT now(),
    finished_at timestamptz,
    updated_at timestamptz NOT NULL DEFAULT now(),
    created_at timestamptz NOT NULL DEFAULT now(),
    FOREIGN KEY (user_id, data_source_id) REFERENCES mattrics.mattrics_data_sources (user_id, id),
    UNIQUE (data_source_id, id)
);

CREATE TABLE mattrics.mattrics_source_records (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id uuid NOT NULL,
    data_source_id uuid NOT NULL,
    import_batch_id uuid NOT NULL,
    external_id text NOT NULL CHECK (btrim(external_id) <> ''),
    original_started_at text NOT NULL,
    original_ended_at text,
    original_units jsonb NOT NULL,
    raw_payload jsonb NOT NULL,
    fit_sha256 text CHECK (fit_sha256 ~ '^[0-9a-f]{64}$'),
    started_at timestamptz NOT NULL,
    ended_at timestamptz CHECK (ended_at >= started_at),
    CHECK (fit_sha256 IS NULL OR (ended_at IS NOT NULL AND ended_at > started_at)),
    UNIQUE (data_source_id, external_id),
    UNIQUE (user_id, id),
    FOREIGN KEY (user_id, data_source_id) REFERENCES mattrics.mattrics_data_sources (user_id, id),
    FOREIGN KEY (data_source_id, import_batch_id) REFERENCES mattrics.mattrics_import_batches (data_source_id, id)
);

CREATE INDEX mattrics_source_records_fit_session
    ON mattrics.mattrics_source_records (user_id, fit_sha256, started_at, ended_at)
    WHERE fit_sha256 IS NOT NULL;

CREATE TABLE mattrics.mattrics_activities (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id uuid NOT NULL,
    data_source_id uuid NOT NULL,
    import_batch_id uuid NOT NULL,
    activity_type_id uuid,
    activity_date date NOT NULL,
    started_at timestamptz NOT NULL,
    ended_at timestamptz CHECK (ended_at >= started_at),
    activity_name text NOT NULL CHECK (btrim(activity_name) <> ''),
    activity_type_name text NOT NULL CHECK (btrim(activity_type_name) <> ''),
    source_activity_id uuid GENERATED ALWAYS AS (id) STORED,
    source_activity_id_raw uuid GENERATED ALWAYS AS (id) STORED,
    distance_km numeric CHECK (distance_km >= 0),
    duration_minutes numeric CHECK (duration_minutes >= 0),
    timer_seconds numeric CHECK (timer_seconds >= 0),
    elevation_gain_m integer CHECK (elevation_gain_m >= 0),
    avg_hr integer CHECK (avg_hr >= 0),
    max_hr integer CHECK (max_hr >= 0),
    avg_pace_min_per_km numeric,
    avg_speed_kmh numeric,
    avg_cadence numeric,
    description text,
    device_name text,
    created_at timestamptz NOT NULL DEFAULT now(),
    updated_at timestamptz NOT NULL DEFAULT now(),
    UNIQUE (user_id, id),
    FOREIGN KEY (user_id, data_source_id) REFERENCES mattrics.mattrics_data_sources (user_id, id),
    FOREIGN KEY (data_source_id, import_batch_id) REFERENCES mattrics.mattrics_import_batches (data_source_id, id)
);

CREATE INDEX mattrics_activities_user_date ON mattrics.mattrics_activities (user_id, activity_date DESC);

CREATE TABLE mattrics.mattrics_activity_source_links (
    user_id uuid NOT NULL,
    source_record_id uuid PRIMARY KEY,
    activity_id uuid NOT NULL,
    link_kind text NOT NULL CHECK (link_kind IN ('primary', 'exact_fit')),
    match_evidence jsonb,
    CHECK ((link_kind = 'exact_fit') = (match_evidence IS NOT NULL)),
    FOREIGN KEY (user_id, source_record_id) REFERENCES mattrics.mattrics_source_records (user_id, id) ON DELETE CASCADE,
    FOREIGN KEY (user_id, activity_id) REFERENCES mattrics.mattrics_activities (user_id, id) ON DELETE CASCADE
);

CREATE UNIQUE INDEX mattrics_activity_source_links_primary
    ON mattrics.mattrics_activity_source_links (activity_id) WHERE link_kind = 'primary';
CREATE INDEX mattrics_activity_source_links_activity ON mattrics.mattrics_activity_source_links (activity_id);

-- The existing child reader supports optional resolution to a canonical exercise.
CREATE TABLE mattrics.mattrics_exercises (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    canonical_name text NOT NULL
);

CREATE TABLE mattrics.mattrics_activity_exercises (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    activity_id uuid NOT NULL REFERENCES mattrics.mattrics_activities (id) ON DELETE CASCADE,
    exercise_id uuid REFERENCES mattrics.mattrics_exercises (id) ON DELETE SET NULL,
    exercise_order integer NOT NULL CHECK (exercise_order > 0),
    provider_template_id text,
    source_exercise_name text NOT NULL,
    normalized_source_exercise_name text NOT NULL,
    UNIQUE (activity_id, exercise_order)
);

CREATE TABLE mattrics.mattrics_activity_sets (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    activity_exercise_id uuid NOT NULL REFERENCES mattrics.mattrics_activity_exercises (id) ON DELETE CASCADE,
    set_order integer NOT NULL CHECK (set_order > 0),
    set_type text NOT NULL,
    parsed_kind text NOT NULL CHECK (parsed_kind IN ('parsed', 'time', 'unknown')),
    source_set_text text,
    reps integer CHECK (reps >= 0),
    weight_kg numeric CHECK (weight_kg >= 0),
    duration_minutes numeric CHECK (duration_minutes >= 0),
    distance_km numeric CHECK (distance_km >= 0),
    rpe numeric CHECK (rpe BETWEEN 0 AND 10),
    effort_factor numeric,
    computed_load numeric,
    notes text,
    UNIQUE (activity_exercise_id, set_order)
);
