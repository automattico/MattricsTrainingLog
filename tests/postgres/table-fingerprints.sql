SET TIME ZONE 'UTC';
BEGIN ISOLATION LEVEL REPEATABLE READ READ ONLY;
SELECT format(
    'SELECT %L, count(*), md5(coalesce(jsonb_agg(row_data ORDER BY row_data)::text, %L))
    FROM (SELECT to_jsonb(t) AS row_data FROM mattrics.%I t) rows',
    tablename, '[]', tablename
)
FROM pg_catalog.pg_tables
WHERE schemaname = 'mattrics'
ORDER BY tablename
\gexec
COMMIT;
