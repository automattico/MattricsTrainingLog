CREATE EXTENSION IF NOT EXISTS pgcrypto;

CREATE SCHEMA IF NOT EXISTS mattrics;

CREATE TABLE IF NOT EXISTS mattrics.mattrics_schema_migrations (
    version text PRIMARY KEY,
    checksum text NOT NULL,
    applied_at timestamptz NOT NULL DEFAULT timezone('utc', now())
);

CREATE OR REPLACE FUNCTION mattrics.set_updated_at()
RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
    NEW.updated_at = timezone('utc', now());
    RETURN NEW;
END;
$$;

CREATE TABLE IF NOT EXISTS mattrics.mattrics_users (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    external_key text NOT NULL,
    display_name text NOT NULL,
    timezone_name text NOT NULL DEFAULT 'UTC',
    is_active boolean NOT NULL DEFAULT true,
    created_at timestamptz NOT NULL DEFAULT timezone('utc', now()),
    updated_at timestamptz NOT NULL DEFAULT timezone('utc', now()),
    CONSTRAINT mattrics_users_external_key_unique UNIQUE (external_key),
    CONSTRAINT mattrics_users_external_key_not_blank CHECK (btrim(external_key) <> ''),
    CONSTRAINT mattrics_users_display_name_not_blank CHECK (btrim(display_name) <> ''),
    CONSTRAINT mattrics_users_timezone_name_not_blank CHECK (btrim(timezone_name) <> '')
);

CREATE TABLE IF NOT EXISTS mattrics.mattrics_data_sources (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id uuid NOT NULL REFERENCES mattrics.mattrics_users (id) ON DELETE CASCADE,
    source_key text NOT NULL,
    source_kind text NOT NULL,
    display_name text NOT NULL,
    connection_status text NOT NULL DEFAULT 'active',
    is_active boolean NOT NULL DEFAULT true,
    last_synced_at timestamptz NULL,
    notes text NULL,
    created_at timestamptz NOT NULL DEFAULT timezone('utc', now()),
    updated_at timestamptz NOT NULL DEFAULT timezone('utc', now()),
    CONSTRAINT mattrics_data_sources_user_source_key_unique UNIQUE (user_id, source_key),
    CONSTRAINT mattrics_data_sources_source_key_not_blank CHECK (btrim(source_key) <> ''),
    CONSTRAINT mattrics_data_sources_display_name_not_blank CHECK (btrim(display_name) <> ''),
    CONSTRAINT mattrics_data_sources_source_kind_check CHECK (
        source_kind IN ('google_sheet', 'hevy', 'concept2', 'garmin', 'strava', 'manual', 'legacy_import')
    ),
    CONSTRAINT mattrics_data_sources_connection_status_check CHECK (
        connection_status IN ('active', 'paused', 'error', 'deprecated')
    )
);

CREATE TABLE IF NOT EXISTS mattrics.mattrics_import_batches (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id uuid NOT NULL REFERENCES mattrics.mattrics_users (id) ON DELETE CASCADE,
    data_source_id uuid NOT NULL REFERENCES mattrics.mattrics_data_sources (id) ON DELETE CASCADE,
    batch_kind text NOT NULL,
    status text NOT NULL DEFAULT 'pending',
    source_batch_key text NULL,
    private_payload_path text NULL,
    started_at timestamptz NULL,
    finished_at timestamptz NULL,
    imported_row_count integer NOT NULL DEFAULT 0,
    applied_activity_count integer NOT NULL DEFAULT 0,
    error_summary text NULL,
    created_at timestamptz NOT NULL DEFAULT timezone('utc', now()),
    updated_at timestamptz NOT NULL DEFAULT timezone('utc', now()),
    CONSTRAINT mattrics_import_batches_kind_check CHECK (
        batch_kind IN (
            'google_sheet_snapshot',
            'hevy_export',
            'hevy_live_api_sync',
            'concept2_logbook_export',
            'garmin_export',
            'garmin_live_sync',
            'manual_seed',
            'compatibility_backfill'
        )
    ),
    CONSTRAINT mattrics_import_batches_status_check CHECK (
        status IN ('pending', 'running', 'succeeded', 'partial', 'failed')
    ),
    CONSTRAINT mattrics_import_batches_imported_row_count_check CHECK (imported_row_count >= 0),
    CONSTRAINT mattrics_import_batches_applied_activity_count_check CHECK (applied_activity_count >= 0)
);

CREATE UNIQUE INDEX IF NOT EXISTS mattrics_import_batches_source_batch_key_unique
    ON mattrics.mattrics_import_batches (data_source_id, source_batch_key)
    WHERE source_batch_key IS NOT NULL;

CREATE TABLE IF NOT EXISTS mattrics.mattrics_exercises (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id uuid NOT NULL REFERENCES mattrics.mattrics_users (id) ON DELETE CASCADE,
    legacy_config_id text NULL,
    canonical_name text NOT NULL,
    normalized_name text NOT NULL,
    fatigue_impact text NOT NULL DEFAULT 'normal',
    fatigue_multiplier numeric(8, 3) NOT NULL DEFAULT 1.000,
    bodyweight_eligible boolean NOT NULL DEFAULT false,
    set_type_handling text NOT NULL DEFAULT 'weight_reps',
    config_source text NOT NULL DEFAULT 'manual',
    last_updated_at timestamptz NULL,
    last_updated_type text NOT NULL DEFAULT 'manual',
    exercise_family text NOT NULL,
    fatigue_archetype text NOT NULL,
    chest_weight numeric(8, 3) NOT NULL DEFAULT 0,
    deltoids_weight numeric(8, 3) NOT NULL DEFAULT 0,
    trapezius_weight numeric(8, 3) NOT NULL DEFAULT 0,
    upper_back_weight numeric(8, 3) NOT NULL DEFAULT 0,
    triceps_weight numeric(8, 3) NOT NULL DEFAULT 0,
    biceps_weight numeric(8, 3) NOT NULL DEFAULT 0,
    abs_weight numeric(8, 3) NOT NULL DEFAULT 0,
    obliques_weight numeric(8, 3) NOT NULL DEFAULT 0,
    lower_back_weight numeric(8, 3) NOT NULL DEFAULT 0,
    gluteal_weight numeric(8, 3) NOT NULL DEFAULT 0,
    adductors_weight numeric(8, 3) NOT NULL DEFAULT 0,
    quadriceps_weight numeric(8, 3) NOT NULL DEFAULT 0,
    hamstrings_weight numeric(8, 3) NOT NULL DEFAULT 0,
    calves_weight numeric(8, 3) NOT NULL DEFAULT 0,
    created_at timestamptz NOT NULL DEFAULT timezone('utc', now()),
    updated_at timestamptz NOT NULL DEFAULT timezone('utc', now()),
    CONSTRAINT mattrics_exercises_user_normalized_name_unique UNIQUE (user_id, normalized_name),
    CONSTRAINT mattrics_exercises_canonical_name_not_blank CHECK (btrim(canonical_name) <> ''),
    CONSTRAINT mattrics_exercises_normalized_name_not_blank CHECK (btrim(normalized_name) <> ''),
    CONSTRAINT mattrics_exercises_fatigue_impact_check CHECK (fatigue_impact IN ('normal', 'none')),
    CONSTRAINT mattrics_exercises_set_type_handling_check CHECK (
        set_type_handling IN ('weight_reps', 'bodyweight_reps', 'time_based_ignore', 'time_duration')
    ),
    CONSTRAINT mattrics_exercises_config_source_check CHECK (
        config_source IN ('manual', 'ai_suggested', 'external_dataset', 'merged')
    ),
    CONSTRAINT mattrics_exercises_last_updated_type_check CHECK (last_updated_type IN ('ai', 'manual')),
    CONSTRAINT mattrics_exercises_exercise_family_check CHECK (
        exercise_family IN (
            'horizontal_press',
            'vertical_press',
            'horizontal_pull',
            'vertical_pull',
            'squat',
            'hinge',
            'hip_dominant',
            'knee_isolation',
            'hip_isolation',
            'arm_isolation',
            'shoulder_isolation',
            'calf',
            'core',
            'conditioning_lower'
        )
    ),
    CONSTRAINT mattrics_exercises_fatigue_archetype_check CHECK (
        fatigue_archetype IN (
            'isolation',
            'machine_compound',
            'freeweight_compound',
            'hinge_squat',
            'conditioning_hybrid'
        )
    ),
    CONSTRAINT mattrics_exercises_fatigue_multiplier_check CHECK (fatigue_multiplier >= 0),
    CONSTRAINT mattrics_exercises_muscle_weights_non_negative CHECK (
        chest_weight >= 0
        AND deltoids_weight >= 0
        AND trapezius_weight >= 0
        AND upper_back_weight >= 0
        AND triceps_weight >= 0
        AND biceps_weight >= 0
        AND abs_weight >= 0
        AND obliques_weight >= 0
        AND lower_back_weight >= 0
        AND gluteal_weight >= 0
        AND adductors_weight >= 0
        AND quadriceps_weight >= 0
        AND hamstrings_weight >= 0
        AND calves_weight >= 0
    )
);

CREATE UNIQUE INDEX IF NOT EXISTS mattrics_exercises_legacy_config_id_unique
    ON mattrics.mattrics_exercises (user_id, legacy_config_id)
    WHERE legacy_config_id IS NOT NULL;

CREATE TABLE IF NOT EXISTS mattrics.mattrics_exercise_aliases (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id uuid NOT NULL REFERENCES mattrics.mattrics_users (id) ON DELETE CASCADE,
    exercise_id uuid NOT NULL REFERENCES mattrics.mattrics_exercises (id) ON DELETE CASCADE,
    alias_kind text NOT NULL,
    alias_name text NOT NULL,
    normalized_alias_name text NOT NULL,
    created_at timestamptz NOT NULL DEFAULT timezone('utc', now()),
    updated_at timestamptz NOT NULL DEFAULT timezone('utc', now()),
    CONSTRAINT mattrics_exercise_aliases_kind_check CHECK (alias_kind IN ('alias', 'match_term')),
    CONSTRAINT mattrics_exercise_aliases_alias_name_not_blank CHECK (btrim(alias_name) <> ''),
    CONSTRAINT mattrics_exercise_aliases_normalized_alias_name_not_blank CHECK (btrim(normalized_alias_name) <> '')
);

CREATE UNIQUE INDEX IF NOT EXISTS mattrics_exercise_aliases_lookup_unique
    ON mattrics.mattrics_exercise_aliases (user_id, alias_kind, normalized_alias_name);

CREATE UNIQUE INDEX IF NOT EXISTS mattrics_exercise_aliases_exercise_term_unique
    ON mattrics.mattrics_exercise_aliases (exercise_id, alias_kind, normalized_alias_name);

CREATE TABLE IF NOT EXISTS mattrics.mattrics_activity_types (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id uuid NOT NULL REFERENCES mattrics.mattrics_users (id) ON DELETE CASCADE,
    legacy_config_id text NULL,
    canonical_name text NOT NULL,
    normalized_name text NOT NULL,
    status text NOT NULL DEFAULT 'approved',
    review_needed boolean NOT NULL DEFAULT false,
    config_source text NOT NULL DEFAULT 'manual',
    last_updated_at timestamptz NULL,
    last_updated_type text NOT NULL DEFAULT 'manual',
    exercise_family text NOT NULL,
    fatigue_archetype text NOT NULL,
    fatigue_multiplier numeric(8, 3) NOT NULL DEFAULT 1.000,
    chest_weight numeric(8, 3) NOT NULL DEFAULT 0,
    deltoids_weight numeric(8, 3) NOT NULL DEFAULT 0,
    trapezius_weight numeric(8, 3) NOT NULL DEFAULT 0,
    upper_back_weight numeric(8, 3) NOT NULL DEFAULT 0,
    triceps_weight numeric(8, 3) NOT NULL DEFAULT 0,
    biceps_weight numeric(8, 3) NOT NULL DEFAULT 0,
    abs_weight numeric(8, 3) NOT NULL DEFAULT 0,
    obliques_weight numeric(8, 3) NOT NULL DEFAULT 0,
    lower_back_weight numeric(8, 3) NOT NULL DEFAULT 0,
    gluteal_weight numeric(8, 3) NOT NULL DEFAULT 0,
    adductors_weight numeric(8, 3) NOT NULL DEFAULT 0,
    quadriceps_weight numeric(8, 3) NOT NULL DEFAULT 0,
    hamstrings_weight numeric(8, 3) NOT NULL DEFAULT 0,
    calves_weight numeric(8, 3) NOT NULL DEFAULT 0,
    created_at timestamptz NOT NULL DEFAULT timezone('utc', now()),
    updated_at timestamptz NOT NULL DEFAULT timezone('utc', now()),
    CONSTRAINT mattrics_activity_types_user_normalized_name_unique UNIQUE (user_id, normalized_name),
    CONSTRAINT mattrics_activity_types_canonical_name_not_blank CHECK (btrim(canonical_name) <> ''),
    CONSTRAINT mattrics_activity_types_normalized_name_not_blank CHECK (btrim(normalized_name) <> ''),
    CONSTRAINT mattrics_activity_types_status_check CHECK (status IN ('draft_active', 'approved')),
    CONSTRAINT mattrics_activity_types_config_source_check CHECK (
        config_source IN ('manual', 'ai_suggested', 'external_dataset', 'merged')
    ),
    CONSTRAINT mattrics_activity_types_last_updated_type_check CHECK (last_updated_type IN ('ai', 'manual')),
    CONSTRAINT mattrics_activity_types_exercise_family_check CHECK (
        exercise_family IN (
            'horizontal_press',
            'vertical_press',
            'horizontal_pull',
            'vertical_pull',
            'squat',
            'hinge',
            'hip_dominant',
            'knee_isolation',
            'hip_isolation',
            'arm_isolation',
            'shoulder_isolation',
            'calf',
            'core',
            'conditioning_lower'
        )
    ),
    CONSTRAINT mattrics_activity_types_fatigue_archetype_check CHECK (
        fatigue_archetype IN (
            'isolation',
            'machine_compound',
            'freeweight_compound',
            'hinge_squat',
            'conditioning_hybrid'
        )
    ),
    CONSTRAINT mattrics_activity_types_fatigue_multiplier_check CHECK (fatigue_multiplier >= 0),
    CONSTRAINT mattrics_activity_types_muscle_weights_non_negative CHECK (
        chest_weight >= 0
        AND deltoids_weight >= 0
        AND trapezius_weight >= 0
        AND upper_back_weight >= 0
        AND triceps_weight >= 0
        AND biceps_weight >= 0
        AND abs_weight >= 0
        AND obliques_weight >= 0
        AND lower_back_weight >= 0
        AND gluteal_weight >= 0
        AND adductors_weight >= 0
        AND quadriceps_weight >= 0
        AND hamstrings_weight >= 0
        AND calves_weight >= 0
    )
);

CREATE UNIQUE INDEX IF NOT EXISTS mattrics_activity_types_legacy_config_id_unique
    ON mattrics.mattrics_activity_types (user_id, legacy_config_id)
    WHERE legacy_config_id IS NOT NULL;

CREATE TABLE IF NOT EXISTS mattrics.mattrics_activity_type_aliases (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id uuid NOT NULL REFERENCES mattrics.mattrics_users (id) ON DELETE CASCADE,
    activity_type_id uuid NOT NULL REFERENCES mattrics.mattrics_activity_types (id) ON DELETE CASCADE,
    alias_name text NOT NULL,
    normalized_alias_name text NOT NULL,
    created_at timestamptz NOT NULL DEFAULT timezone('utc', now()),
    updated_at timestamptz NOT NULL DEFAULT timezone('utc', now()),
    CONSTRAINT mattrics_activity_type_aliases_alias_name_not_blank CHECK (btrim(alias_name) <> ''),
    CONSTRAINT mattrics_activity_type_aliases_normalized_alias_name_not_blank CHECK (btrim(normalized_alias_name) <> '')
);

CREATE UNIQUE INDEX IF NOT EXISTS mattrics_activity_type_aliases_lookup_unique
    ON mattrics.mattrics_activity_type_aliases (user_id, normalized_alias_name);

CREATE UNIQUE INDEX IF NOT EXISTS mattrics_activity_type_aliases_type_term_unique
    ON mattrics.mattrics_activity_type_aliases (activity_type_id, normalized_alias_name);

CREATE TABLE IF NOT EXISTS mattrics.mattrics_activities (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id uuid NOT NULL REFERENCES mattrics.mattrics_users (id) ON DELETE CASCADE,
    data_source_id uuid NOT NULL REFERENCES mattrics.mattrics_data_sources (id) ON DELETE CASCADE,
    import_batch_id uuid NULL REFERENCES mattrics.mattrics_import_batches (id) ON DELETE SET NULL,
    activity_type_id uuid NULL REFERENCES mattrics.mattrics_activity_types (id) ON DELETE SET NULL,
    source_row_number integer NULL,
    activity_date date NOT NULL,
    started_at timestamptz NULL,
    activity_name text NOT NULL,
    activity_type_name text NOT NULL,
    source_activity_id text NULL,
    source_activity_id_raw text NULL,
    distance_km numeric(10, 3) NULL,
    duration_minutes numeric(10, 2) NULL,
    elevation_gain_m integer NULL,
    avg_hr integer NULL,
    max_hr integer NULL,
    avg_pace_min_per_km numeric(10, 4) NULL,
    avg_speed_kmh numeric(10, 3) NULL,
    avg_cadence numeric(10, 3) NULL,
    description text NULL,
    device_name text NULL,
    created_at timestamptz NOT NULL DEFAULT timezone('utc', now()),
    updated_at timestamptz NOT NULL DEFAULT timezone('utc', now()),
    CONSTRAINT mattrics_activities_activity_name_not_blank CHECK (btrim(activity_name) <> ''),
    CONSTRAINT mattrics_activities_activity_type_name_not_blank CHECK (btrim(activity_type_name) <> ''),
    CONSTRAINT mattrics_activities_source_row_number_check CHECK (source_row_number IS NULL OR source_row_number > 0),
    CONSTRAINT mattrics_activities_distance_km_check CHECK (distance_km IS NULL OR distance_km >= 0),
    CONSTRAINT mattrics_activities_duration_minutes_check CHECK (duration_minutes IS NULL OR duration_minutes >= 0),
    CONSTRAINT mattrics_activities_elevation_gain_m_check CHECK (elevation_gain_m IS NULL OR elevation_gain_m >= 0),
    CONSTRAINT mattrics_activities_avg_hr_check CHECK (avg_hr IS NULL OR avg_hr >= 0),
    CONSTRAINT mattrics_activities_max_hr_check CHECK (max_hr IS NULL OR max_hr >= 0),
    CONSTRAINT mattrics_activities_avg_pace_check CHECK (avg_pace_min_per_km IS NULL OR avg_pace_min_per_km >= 0),
    CONSTRAINT mattrics_activities_avg_speed_check CHECK (avg_speed_kmh IS NULL OR avg_speed_kmh >= 0),
    CONSTRAINT mattrics_activities_avg_cadence_check CHECK (avg_cadence IS NULL OR avg_cadence >= 0)
);

CREATE UNIQUE INDEX IF NOT EXISTS mattrics_activities_source_activity_id_raw_unique
    ON mattrics.mattrics_activities (data_source_id, source_activity_id_raw)
    WHERE source_activity_id_raw IS NOT NULL;

CREATE UNIQUE INDEX IF NOT EXISTS mattrics_activities_batch_row_unique
    ON mattrics.mattrics_activities (import_batch_id, source_row_number)
    WHERE import_batch_id IS NOT NULL AND source_row_number IS NOT NULL;

CREATE INDEX IF NOT EXISTS mattrics_activities_user_date_idx
    ON mattrics.mattrics_activities (user_id, activity_date DESC);

CREATE TABLE IF NOT EXISTS mattrics.mattrics_activity_exercises (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id uuid NOT NULL REFERENCES mattrics.mattrics_users (id) ON DELETE CASCADE,
    activity_id uuid NOT NULL REFERENCES mattrics.mattrics_activities (id) ON DELETE CASCADE,
    exercise_id uuid NULL REFERENCES mattrics.mattrics_exercises (id) ON DELETE SET NULL,
    exercise_order integer NOT NULL,
    source_exercise_name text NOT NULL,
    normalized_source_exercise_name text NOT NULL,
    created_at timestamptz NOT NULL DEFAULT timezone('utc', now()),
    updated_at timestamptz NOT NULL DEFAULT timezone('utc', now()),
    CONSTRAINT mattrics_activity_exercises_order_check CHECK (exercise_order > 0),
    CONSTRAINT mattrics_activity_exercises_source_name_not_blank CHECK (btrim(source_exercise_name) <> ''),
    CONSTRAINT mattrics_activity_exercises_normalized_name_not_blank CHECK (btrim(normalized_source_exercise_name) <> '')
);

CREATE UNIQUE INDEX IF NOT EXISTS mattrics_activity_exercises_activity_order_unique
    ON mattrics.mattrics_activity_exercises (activity_id, exercise_order);

CREATE INDEX IF NOT EXISTS mattrics_activity_exercises_lookup_idx
    ON mattrics.mattrics_activity_exercises (user_id, normalized_source_exercise_name);

CREATE TABLE IF NOT EXISTS mattrics.mattrics_activity_sets (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id uuid NOT NULL REFERENCES mattrics.mattrics_users (id) ON DELETE CASCADE,
    activity_exercise_id uuid NOT NULL REFERENCES mattrics.mattrics_activity_exercises (id) ON DELETE CASCADE,
    set_order integer NOT NULL,
    parsed_kind text NOT NULL,
    source_set_text text NULL,
    reps integer NULL,
    weight_kg numeric(10, 3) NULL,
    duration_minutes numeric(10, 3) NULL,
    distance_km numeric(10, 3) NULL,
    rpe numeric(4, 2) NULL,
    effort_factor numeric(6, 3) NULL,
    computed_load numeric(12, 4) NULL,
    notes text NULL,
    created_at timestamptz NOT NULL DEFAULT timezone('utc', now()),
    updated_at timestamptz NOT NULL DEFAULT timezone('utc', now()),
    CONSTRAINT mattrics_activity_sets_order_check CHECK (set_order > 0),
    CONSTRAINT mattrics_activity_sets_parsed_kind_check CHECK (parsed_kind IN ('parsed', 'time', 'unknown')),
    CONSTRAINT mattrics_activity_sets_reps_check CHECK (reps IS NULL OR reps > 0),
    CONSTRAINT mattrics_activity_sets_weight_kg_check CHECK (weight_kg IS NULL OR weight_kg >= 0),
    CONSTRAINT mattrics_activity_sets_duration_minutes_check CHECK (duration_minutes IS NULL OR duration_minutes >= 0),
    CONSTRAINT mattrics_activity_sets_distance_km_check CHECK (distance_km IS NULL OR distance_km >= 0),
    CONSTRAINT mattrics_activity_sets_rpe_check CHECK (rpe IS NULL OR (rpe >= 0 AND rpe <= 10)),
    CONSTRAINT mattrics_activity_sets_effort_factor_check CHECK (effort_factor IS NULL OR effort_factor >= 0),
    CONSTRAINT mattrics_activity_sets_computed_load_check CHECK (computed_load IS NULL OR computed_load >= 0)
);

CREATE UNIQUE INDEX IF NOT EXISTS mattrics_activity_sets_exercise_order_unique
    ON mattrics.mattrics_activity_sets (activity_exercise_id, set_order);

CREATE INDEX IF NOT EXISTS mattrics_import_batches_user_created_idx
    ON mattrics.mattrics_import_batches (user_id, created_at DESC);

DO $$
BEGIN
    IF EXISTS (
        SELECT 1
        FROM pg_trigger
        WHERE tgname = 'set_updated_at_mattrics_users'
    ) THEN
        EXECUTE 'DROP TRIGGER set_updated_at_mattrics_users ON mattrics.mattrics_users';
    END IF;
END
$$;

CREATE TRIGGER set_updated_at_mattrics_users
BEFORE UPDATE ON mattrics.mattrics_users
FOR EACH ROW
EXECUTE FUNCTION mattrics.set_updated_at();

DO $$
BEGIN
    IF EXISTS (
        SELECT 1
        FROM pg_trigger
        WHERE tgname = 'set_updated_at_mattrics_data_sources'
    ) THEN
        EXECUTE 'DROP TRIGGER set_updated_at_mattrics_data_sources ON mattrics.mattrics_data_sources';
    END IF;
END
$$;

CREATE TRIGGER set_updated_at_mattrics_data_sources
BEFORE UPDATE ON mattrics.mattrics_data_sources
FOR EACH ROW
EXECUTE FUNCTION mattrics.set_updated_at();

DO $$
BEGIN
    IF EXISTS (
        SELECT 1
        FROM pg_trigger
        WHERE tgname = 'set_updated_at_mattrics_import_batches'
    ) THEN
        EXECUTE 'DROP TRIGGER set_updated_at_mattrics_import_batches ON mattrics.mattrics_import_batches';
    END IF;
END
$$;

CREATE TRIGGER set_updated_at_mattrics_import_batches
BEFORE UPDATE ON mattrics.mattrics_import_batches
FOR EACH ROW
EXECUTE FUNCTION mattrics.set_updated_at();

DO $$
BEGIN
    IF EXISTS (
        SELECT 1
        FROM pg_trigger
        WHERE tgname = 'set_updated_at_mattrics_exercises'
    ) THEN
        EXECUTE 'DROP TRIGGER set_updated_at_mattrics_exercises ON mattrics.mattrics_exercises';
    END IF;
END
$$;

CREATE TRIGGER set_updated_at_mattrics_exercises
BEFORE UPDATE ON mattrics.mattrics_exercises
FOR EACH ROW
EXECUTE FUNCTION mattrics.set_updated_at();

DO $$
BEGIN
    IF EXISTS (
        SELECT 1
        FROM pg_trigger
        WHERE tgname = 'set_updated_at_mattrics_exercise_aliases'
    ) THEN
        EXECUTE 'DROP TRIGGER set_updated_at_mattrics_exercise_aliases ON mattrics.mattrics_exercise_aliases';
    END IF;
END
$$;

CREATE TRIGGER set_updated_at_mattrics_exercise_aliases
BEFORE UPDATE ON mattrics.mattrics_exercise_aliases
FOR EACH ROW
EXECUTE FUNCTION mattrics.set_updated_at();

DO $$
BEGIN
    IF EXISTS (
        SELECT 1
        FROM pg_trigger
        WHERE tgname = 'set_updated_at_mattrics_activity_types'
    ) THEN
        EXECUTE 'DROP TRIGGER set_updated_at_mattrics_activity_types ON mattrics.mattrics_activity_types';
    END IF;
END
$$;

CREATE TRIGGER set_updated_at_mattrics_activity_types
BEFORE UPDATE ON mattrics.mattrics_activity_types
FOR EACH ROW
EXECUTE FUNCTION mattrics.set_updated_at();

DO $$
BEGIN
    IF EXISTS (
        SELECT 1
        FROM pg_trigger
        WHERE tgname = 'set_updated_at_mattrics_activity_type_aliases'
    ) THEN
        EXECUTE 'DROP TRIGGER set_updated_at_mattrics_activity_type_aliases ON mattrics.mattrics_activity_type_aliases';
    END IF;
END
$$;

CREATE TRIGGER set_updated_at_mattrics_activity_type_aliases
BEFORE UPDATE ON mattrics.mattrics_activity_type_aliases
FOR EACH ROW
EXECUTE FUNCTION mattrics.set_updated_at();

DO $$
BEGIN
    IF EXISTS (
        SELECT 1
        FROM pg_trigger
        WHERE tgname = 'set_updated_at_mattrics_activities'
    ) THEN
        EXECUTE 'DROP TRIGGER set_updated_at_mattrics_activities ON mattrics.mattrics_activities';
    END IF;
END
$$;

CREATE TRIGGER set_updated_at_mattrics_activities
BEFORE UPDATE ON mattrics.mattrics_activities
FOR EACH ROW
EXECUTE FUNCTION mattrics.set_updated_at();

DO $$
BEGIN
    IF EXISTS (
        SELECT 1
        FROM pg_trigger
        WHERE tgname = 'set_updated_at_mattrics_activity_exercises'
    ) THEN
        EXECUTE 'DROP TRIGGER set_updated_at_mattrics_activity_exercises ON mattrics.mattrics_activity_exercises';
    END IF;
END
$$;

CREATE TRIGGER set_updated_at_mattrics_activity_exercises
BEFORE UPDATE ON mattrics.mattrics_activity_exercises
FOR EACH ROW
EXECUTE FUNCTION mattrics.set_updated_at();

DO $$
BEGIN
    IF EXISTS (
        SELECT 1
        FROM pg_trigger
        WHERE tgname = 'set_updated_at_mattrics_activity_sets'
    ) THEN
        EXECUTE 'DROP TRIGGER set_updated_at_mattrics_activity_sets ON mattrics.mattrics_activity_sets';
    END IF;
END
$$;

CREATE TRIGGER set_updated_at_mattrics_activity_sets
BEFORE UPDATE ON mattrics.mattrics_activity_sets
FOR EACH ROW
EXECUTE FUNCTION mattrics.set_updated_at();
