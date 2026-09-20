CREATE EXTENSION IF NOT EXISTS pgcrypto;

CREATE SCHEMA IF NOT EXISTS mattrics;

CREATE TABLE IF NOT EXISTS mattrics.mattrics_schema_migrations (
    version text PRIMARY KEY,
    checksum text NOT NULL,
    applied_at timestamptz NOT NULL DEFAULT timezone('utc', now())
);

ALTER TABLE mattrics.mattrics_data_sources
    DROP CONSTRAINT IF EXISTS mattrics_data_sources_source_kind_check;

ALTER TABLE mattrics.mattrics_data_sources
    ADD CONSTRAINT mattrics_data_sources_source_kind_check CHECK (
        source_kind IN ('google_sheet', 'hevy', 'concept2', 'garmin', 'strava', 'manual', 'legacy_import')
    );

ALTER TABLE mattrics.mattrics_import_batches
    DROP CONSTRAINT IF EXISTS mattrics_import_batches_kind_check;

ALTER TABLE mattrics.mattrics_import_batches
    ADD CONSTRAINT mattrics_import_batches_kind_check CHECK (
        batch_kind IN (
            'google_sheet_snapshot',
            'hevy_export',
            'concept2_logbook_export',
            'garmin_export',
            'manual_seed',
            'compatibility_backfill'
        )
    );
