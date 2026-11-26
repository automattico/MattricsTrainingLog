CREATE TABLE mattrics.direct_refresh (
    user_key text NOT NULL,
    provider text NOT NULL CHECK (provider IN ('garmin', 'hevy', 'concept2')),
    status text NOT NULL CHECK (status IN ('checking', 'success', 'credentials', 'timeout', 'failed')),
    cursor_at timestamptz,
    last_success timestamptz,
    PRIMARY KEY (user_key, provider)
);
