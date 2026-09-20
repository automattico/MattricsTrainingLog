ALTER TABLE mattrics.mattrics_import_batches
    DROP CONSTRAINT IF EXISTS mattrics_import_batches_kind_check;

ALTER TABLE mattrics.mattrics_import_batches
    ADD CONSTRAINT mattrics_import_batches_kind_check CHECK (
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
    );
