<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/lib/direct-refresh.php';
require_once dirname(__DIR__, 2) . '/lib/direct-providers.php';

function test_direct_refresh(PDO $pdo, array $config, array $hevy): void
{
    foreach (['synthetic.jwt.token', '{"access_token":"synthetic.jwt.token"}'] as $reply) {
        $calls = [];
        $records = mattrics_direct_garmin('fixture', null, '2026-11-24T18:00:00Z',
            static function (string $url, string $header, bool $post = false) use ($reply, &$calls): string {
                $calls[] = [$header, $post];
                return $post ? $reply : '[]';
            });
        check($records === [] && $calls === [['Authorization: ApiKey fixture', true],
            ['Authorization: Bearer synthetic.jwt.token', false]], 'Garmin accepts token response and authenticates activity listing');
    }

    $fixture = shell_exec('python3 ' . escapeshellarg(__DIR__ . '/archive-fixtures.py') . ' --fit-download');
    check(is_string($fixture) && count(mattrics_decode_garmin_download($fixture)['sessions']) === 1,
        'bounded FIT decoder processes a valid Garmin download');

    $decoderBin = sys_get_temp_dir() . '/mattrics-slow-decoder-' . bin2hex(random_bytes(6));
    mkdir($decoderBin, 0700);
    $fakeDecoder = $decoderBin . '/python3';
    file_put_contents($fakeDecoder, "#!/bin/sh\nexec " . escapeshellarg(PHP_BINARY) . " -r 'usleep(5000000);'\n");
    chmod($fakeDecoder, 0700);
    $priorPath = getenv('PATH');
    try {
        putenv('PATH=' . $decoderBin . PATH_SEPARATOR . $priorPath);
        $start = hrtime(true);
        try {
            mattrics_decode_garmin_download('synthetic FIT', $start + 150_000_000);
            throw new LogicException('Expected a stalled decoder to time out.');
        } catch (RuntimeException $error) {
            check($error->getMessage() === 'timeout' && hrtime(true) - $start < 2_000_000_000,
                'stalled FIT decoder is stopped and reaped within the request budget');
        }
    } finally {
        putenv($priorPath === false ? 'PATH' : 'PATH=' . $priorPath);
        unlink($fakeDecoder);
        rmdir($decoderBin);
    }

    $runGarmin = static fn(callable $fetch): array => mattrics_refresh_run($pdo, 'garmin-budget-test', 'garmin',
        static fn(?string $cursor, string $start): array => ['records' => mattrics_direct_garmin('fixture', $cursor, $start, $fetch, 100)]);
    $garminState = $runGarmin(static fn(string $url, string $header, bool $post): string => $post ? 'synthetic.jwt.token' : '[]');
    $timeouts = [];
    $beforeGarmin = snapshot($pdo, true);
    $timedOut = $runGarmin(static function (string $url, string $header, bool $post, int $timeoutMs) use (&$timeouts): string {
        $timeouts[] = $timeoutMs;
        if ($post) {
            usleep(20_000);
            return 'synthetic.jwt.token';
        }
        usleep(110_000);
        return '[{"activityId":1}]';
    });
    check($garminState['status'] === 'success' && $timedOut['status'] === 'timeout'
        && $timedOut['cursor_at'] === $garminState['cursor_at'] && $timedOut['last_success'] === $garminState['last_success']
        && count($timeouts) === 2 && $timeouts[0] <= 100 && $timeouts[1] < $timeouts[0]
        && snapshot($pdo, true) === $beforeGarmin,
        'whole Garmin deadline reduces each call budget, prevents further downloads and retains the committed checkpoint');

    $user = 'direct-refresh-test';
    $pdo->exec("INSERT INTO mattrics.mattrics_users (external_key, display_name, timezone_name, is_active)
        VALUES ('direct-refresh-test', 'Existing owner', 'America/Los_Angeles', false)");
    $hevy['start_time'] = '2026-10-06T02:30:00Z';
    $hevy['end_time'] = '2026-10-06T03:15:00Z';
    $run = static fn(callable $fetch): array => mattrics_refresh_run($pdo, $user, 'hevy', $fetch);
    $fetch = static fn(?string $cursor, string $start, int $page): array => mattrics_direct_hevy(['enabled' => true, 'apiKey' => 'fixture'], $page,
        static fn(): string => json_encode(['workouts' => [$hevy], 'page' => 1, 'page_count' => 1], JSON_THROW_ON_ERROR));
    $success = $run($fetch);
    check($success['status'] === 'success', 'Hevy on-demand page imported');
    $owner = $pdo->query("SELECT u.display_name, u.timezone_name, u.is_active, a.activity_date
        FROM mattrics.mattrics_users u JOIN mattrics.mattrics_activities a ON a.user_id=u.id
        WHERE u.external_key='direct-refresh-test'")->fetch();
    check($owner === ['display_name' => 'Existing owner', 'timezone_name' => 'America/Los_Angeles',
        'is_active' => false, 'activity_date' => '2026-10-05'],
        'direct refresh preserves owner profile and assigns workout dates in the saved timezone');
    $before = snapshot($pdo, true);
    $run($fetch);
    check($before === snapshot($pdo, true), 'exact direct replay skips writing batches, workouts and children');
    $state = mattrics_refresh_state($pdo, $user, 'hevy');
    foreach (['timeout', 'credentials', 'interrupted'] as $error) {
        $result = $run(static fn() => throw new RuntimeException($error));
        check($result['status'] === ($error === 'interrupted' ? 'failed' : $error)
            && $result['last_success'] === $state['last_success'] && $result['cursor_at'] === $state['cursor_at'],
            "direct refresh preserves cursor and success after $error");
    }
    $other = mattrics_foundation_connect($config['foundation_database_url']);
    $key = ['key' => json_encode([$user, 'hevy'])];
    local_postgres_query($other, 'SELECT pg_advisory_lock(484311, hashtext(:key))', $key);
    check($run(static fn() => throw new LogicException())['status'] === 'checking', 'concurrent refresh coalesces per provider');
    unset($other);
    check($run($fetch)['status'] === 'success', 'provider lock released by disconnected runner and replay succeeds');

    $edited = $hevy;
    $edited['title'] = 'Edited during history scan';
    $run(static fn(): array => ['records' => [$edited], 'next_hevy_page' => 21]);
    $readPage = static fn(): int => (int) $pdo->query("SELECT next_hevy_page FROM mattrics.direct_refresh
        WHERE user_key='direct-refresh-test' AND provider='hevy'")->fetchColumn();
    $source = linked_record($pdo, 'hevy', 'direct', $hevy['id']);
    check($readPage() === 21 && json_decode($source['raw_payload'], true)['title'] === $edited['title'],
        'direct refresh commits the Hevy continuation and edited workout together');
    $before = snapshot($pdo);
    $invalid = $hevy;
    $invalid['id'] = 'invalid-date';
    $invalid['start_time'] = 'invalid';
    $replayPage = null;
    $failed = $run(static function (?string $cursor, string $start, int $page) use ($hevy, $invalid, &$replayPage): array {
        $replayPage = $page;
        return ['records' => [$hevy, $invalid], 'next_hevy_page' => 40];
    });
    check($failed['status'] === 'failed' && $replayPage === 21 && $readPage() === 21 && snapshot($pdo) === $before,
        'failed Hevy batch rolls back changed records and retains the same continuation for retry');

    $requests = [];
    $history = static function (string $url, string $header) use (&$requests): string {
        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        $page = (int) $query['page'];
        $requests[] = $page;
        $first = ($page - 1) * 10 + 1;
        $workouts = [];
        for ($id = $first; $id <= min($first + 9, 1001); $id++) {
            $workouts[] = ['id' => "history-$id", 'exercises' => []];
        }
        return json_encode(['workouts' => $workouts, 'page' => $page, 'page_count' => 101], JSON_THROW_ON_ERROR);
    };
    $page = 1;
    $seen = [];
    $bounded = true;
    for ($runNumber = 0; $runNumber < 10; $runNumber++) {
        $requests = [];
        $batch = mattrics_direct_hevy(['enabled' => true, 'apiKey' => 'fixture'], $page, $history);
        $bounded = $bounded && $requests[0] === 1 && count($requests) <= 20;
        $seen += array_column($batch['records'], null, 'id');
        $page = $batch['next_hevy_page'];
        if ($page === 1) break;
    }
    check($bounded && $page === 1 && count($seen) === 1001 && isset($seen['history-1001']),
        'bounded refreshes revisit the newest page and reach past page 100 without missing workouts');
}
