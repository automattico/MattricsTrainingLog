<?php
declare(strict_types=1);

require_once __DIR__ . '/linked-workout-import.php';

/** Private config enables each source separately after verification and approval. */
function mattrics_refresh_providers(array $config): array
{
    if (($config['foundation_linked_workouts'] ?? false) !== true) {
        return [];
    }
    return array_values(array_intersect(['hevy', 'garmin'], $config['direct_refresh_providers'] ?? []));
}

function mattrics_refresh_state(PDO $pdo, string $user, string $provider): array
{
    $params = ['user' => $user, 'provider' => $provider];
    $row = local_postgres_query($pdo, 'SELECT status, last_success, cursor_at FROM mattrics.direct_refresh
        WHERE user_key = :user AND provider = :provider', $params)->fetch();
    return $row ?: ['status' => 'pending', 'last_success' => null, 'cursor_at' => null];
}

/** Match the existing archive account and keep its evidence and external ID on edits. */
function mattrics_refresh_changes(PDO $pdo, string $user, string $provider, string $account, array $records): array
{
    if ($records === []) {
        return [];
    }
    $sql = "SELECT r.external_id, r.raw_payload
        FROM mattrics.mattrics_source_records r JOIN mattrics.mattrics_data_sources d ON d.id=r.data_source_id
        JOIN mattrics.mattrics_users u ON u.id=d.user_id
        WHERE u.external_key=:user AND d.source_kind=:provider AND d.account_key=:account";
    $params = compact('user', 'provider', 'account');
    if ($provider === 'hevy') {
        $sql .= " AND r.external_id IN (SELECT jsonb_array_elements_text(CAST(:ids AS jsonb)))";
        $params['ids'] = json_encode(array_column($records, 'id'), JSON_THROW_ON_ERROR);
    }
    $rows = local_postgres_query($pdo, $sql, $params);
    $prior = [];
    $garminIds = [];
    foreach ($rows as $row) {
        $raw = json_decode($row['raw_payload'], true, 512, JSON_THROW_ON_ERROR);
        $prior[$row['external_id']] = $raw;
        if ($provider === 'garmin') {
            $evidence = $raw['archive_evidence'] ?? [];
            $id = $raw['direct_activity_id'] ?? $evidence['garmin_summary']['activityId'] ?? null;
            if ($id !== null) {
                $leg = $raw['direct_session'] ?? $evidence['session_number'] ?? 1;
                $garminIds[$id . ':' . $leg][] = $row['external_id'];
            }
        }
    }
    $changes = [];
    foreach ($records as $raw) {
        if ($provider === 'garmin' && isset($raw['direct_activity_id'])) {
            $ids = $garminIds[$raw['direct_activity_id'] . ':' . $raw['direct_session']] ?? [];
            if (count($ids) > 1 || ($ids && isset($prior[$raw['id']]) && $raw['id'] !== $ids[0])) {
                throw new RuntimeException('Garmin identity needs review');
            }
            $raw['id'] = $ids[0] ?? $raw['id'];
            $old = $prior[$raw['id']] ?? [];
            if ($old && (($old['direct_sessions'] ?? $old['archive_evidence']['session_count'] ?? 1) !== $raw['direct_sessions']
                || (!empty($old['fit_sha256']) && $old['fit_sha256'] !== $raw['fit_sha256']))) {
                throw new RuntimeException('Changed recording needs match review');
            }
            if (isset($old['archive_evidence'])) {
                $raw['archive_evidence'] = $old['archive_evidence'];
            }
        }
        if (!isset($prior[$raw['id']]) || $prior[$raw['id']] != $raw) {
            $changes[] = $raw;
        }
    }
    return $changes;
}

function mattrics_refresh_run(PDO $pdo, string $user, string $provider, callable $fetch): array
{
    $params = ['user' => $user, 'provider' => $provider];
    $lock = ['key' => json_encode([$user, $provider], JSON_THROW_ON_ERROR)];
    if (!local_postgres_query($pdo, 'SELECT pg_try_advisory_lock(484311, hashtext(:key))', $lock)->fetchColumn()) {
        return ['status' => 'checking'] + mattrics_refresh_state($pdo, $user, $provider);
    }
    try {
        $prior = mattrics_refresh_state($pdo, $user, $provider);
        $start = gmdate('c');
        local_postgres_query($pdo, "INSERT INTO mattrics.direct_refresh (user_key, provider, status)
            VALUES (:user, :provider, 'checking') ON CONFLICT (user_key, provider) DO UPDATE SET status = 'checking'", $params);
        try {
            $nextPage = $provider === 'hevy' ? (int) local_postgres_query($pdo, 'SELECT next_hevy_page
                FROM mattrics.direct_refresh WHERE user_key = :user AND provider = :provider', $params)->fetchColumn() : 1;
            $fetched = $fetch($prior['cursor_at'], $start, $nextPage);
            $account = $provider === 'garmin' ? 'historical-archive' : 'direct';
            $records = mattrics_refresh_changes($pdo, $user, $provider, $account, $fetched['records']);
            $advance = static function () use ($pdo, $params, $start, $fetched): void {
                local_postgres_query($pdo, "UPDATE mattrics.direct_refresh SET status = 'success', cursor_at = :start,
                    next_hevy_page = :page, last_success = clock_timestamp() WHERE user_key = :user AND provider = :provider",
                    $params + ['start' => $start, 'page' => $fetched['next_hevy_page'] ?? 1]);
            };
            if ($records === []) {
                $advance();
            } else {
                local_workout_import($pdo, $user, $provider, $account, $records, false, $advance);
            }
        } catch (Throwable $failure) {
            $reason = in_array($failure->getMessage(), ['credentials', 'timeout'], true) ? $failure->getMessage() : 'failed';
            local_postgres_query($pdo, 'UPDATE mattrics.direct_refresh SET status = :reason
                WHERE user_key = :user AND provider = :provider', $params + ['reason' => $reason]);
        }
        return mattrics_refresh_state($pdo, $user, $provider);
    } finally {
        local_postgres_query($pdo, 'SELECT pg_advisory_unlock(484311, hashtext(:key))', $lock);
    }
}
