ALTER TABLE mattrics.direct_refresh
    ADD COLUMN next_hevy_page integer NOT NULL DEFAULT 1 CHECK (next_hevy_page > 0);
