<?php
declare(strict_types=1);

// Fixed origins, no redirects. Provider payloads and transport errors stay private.
function mattrics_provider_request(string $url, string $header, bool $post = false): string
{
    $handle = curl_init($url);
    $body = '';
    curl_setopt_array($handle, [
        CURLOPT_HTTPHEADER => [$header], CURLOPT_POST => $post, CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => 12, CURLOPT_CONNECTTIMEOUT => 4, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$body): int {
            if (strlen($body) + strlen($chunk) > 32 * 1024 * 1024) {
                return 0;
            }
            $body .= $chunk;
            return strlen($chunk);
        },
    ]);
    $ok = curl_exec($handle);
    $code = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    if ($ok === false || $code !== 200) {
        throw new RuntimeException(match (true) {
            $code === 401, $code === 403 => 'credentials',
            curl_errno($handle) === CURLE_OPERATION_TIMEDOUT => 'timeout',
            default => 'provider request failed',
        });
    }
    return $body;
}

/** Read the newest page and a bounded slice of the rolling history scan. */
function mattrics_direct_hevy(array $record, int $nextPage, ?callable $request = null): array
{
    $request ??= 'mattrics_provider_request';
    if (empty($record['enabled']) || empty($record['apiKey'])) {
        throw new RuntimeException('credentials');
    }
    $workouts = [];
    $readPage = static function (int $page, ?int $expectedPages = null) use ($record, $request, &$workouts): int {
        $json = json_decode($request("https://api.hevyapp.com/v1/workouts?page=$page&pageSize=10",
            'api-key: ' . $record['apiKey']), true, 512, JSON_THROW_ON_ERROR);
        $items = $json['workouts'] ?? null;
        $pages = $json['page_count'] ?? null;
        if (!is_array($items) || !array_is_list($items) || !is_int($pages) || $pages < 0
            || ($json['page'] ?? null) !== $page || ($expectedPages !== null && $pages !== $expectedPages)
            || ($page < $pages && $items === [])) {
            throw new RuntimeException('Incomplete Hevy page');
        }
        foreach ($items as $item) {
            if (empty($item['id']) || isset($workouts[$item['id']]) || !is_array($item['exercises'] ?? null)) {
                throw new RuntimeException('Incomplete Hevy workout');
            }
            $workouts[$item['id']] = $item;
        }
        return $pages;
    };

    $deadline = microtime(true) + 18;
    $pages = $readPage(1);
    if ($pages <= 1) {
        return ['records' => array_values($workouts), 'next_hevy_page' => 1];
    }

    // Revisit the preceding page in case new or deleted workouts shifted the boundary.
    // Finish at least one new page before yielding, even if the head and overlap were slow.
    $boundary = min($nextPage, $pages);
    $page = max(2, $boundary - 1);
    for ($fetched = 2; ; $page++, $fetched++) {
        $readPage($page, $pages);
        if ($page === $pages || ($page >= $boundary && ($fetched >= 20 || microtime(true) >= $deadline))) {
            return ['records' => array_values($workouts), 'next_hevy_page' => $page === $pages ? 1 : $page + 1];
        }
    }
}

function mattrics_decode_garmin_download(string $bytes): array
{
    $input = tmpfile();
    if (!$input || fwrite($input, $bytes) !== strlen($bytes) || !rewind($input)) {
        throw new RuntimeException('FIT input unavailable');
    }
    $proc = proc_open(['python3', '-IB', __DIR__ . '/fit_activity.py'],
        [0 => $input, 1 => ['pipe', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes);
    if (!is_resource($proc)) {
        throw new RuntimeException('FIT decoder unavailable');
    }
    $output = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $exit = proc_close($proc);
    fclose($input);
    if ($exit !== 0) {
        throw new RuntimeException('FIT decode failed');
    }
    return json_decode($output, true, 512, JSON_THROW_ON_ERROR);
}

function mattrics_direct_garmin(string $key, ?string $cursor, string $start, ?callable $request = null): array
{
    $request ??= 'mattrics_provider_request';
    if ($key === '') {
        throw new RuntimeException('credentials');
    }
    $base = 'https://personal.connectapi.garmin.com';
    $reply = trim($request($base . '/personalapitoken-service/personal-api/personal-api-key/token',
        'Authorization: ApiKey ' . $key, true));
    // Personal API can return the bearer JWT as the response body.
    $token = preg_match('/^[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+$/D', $reply)
        ? $reply : (json_decode($reply, true)['access_token'] ?? null);
    if (!is_string($token) || $token === '') {
        throw new RuntimeException('credentials');
    }
    $auth = 'Authorization: Bearer ' . $token;
    $from = (new DateTimeImmutable($cursor ?? $start))->modify('-14 days')->format('Y-m-d');
    $until = substr($start, 0, 10);
    $seen = [];
    $records = [];
    for ($page = 0; $page < 100; $page++) {
        $query = http_build_query(['start' => $page * 20, 'limit' => 20, 'startDate' => $from, 'endDate' => $until]);
        $items = json_decode($request($base . '/activitylist-service/activities/search/activities?' . $query, $auth), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($items) || !array_is_list($items)) {
            throw new RuntimeException('Incomplete Garmin list');
        }
        foreach ($items as $item) {
            $id = (string) ($item['activityId'] ?? '');
            if (!ctype_digit($id) || isset($seen[$id])) {
                throw new RuntimeException('Unstable Garmin page');
            }
            $seen[$id] = true;
            $fit = mattrics_decode_garmin_download($request($base . '/download-service/files/activity/' . $id, $auth));
            foreach ($fit['sessions'] as $i => $leg) {
                $records[] = $leg + ['id' => $fit['digest'] . ':' . ($i + 1), 'fit_sha256' => $fit['digest'],
                    'title' => count($fit['sessions']) === 1 ? ($item['activityName'] ?? $leg['sport']) : $leg['sport'],
                    'direct_activity_id' => $id, 'direct_session' => $i + 1, 'direct_sessions' => count($fit['sessions'])];
            }
        }
        if (count($items) < 20) {
            return $records;
        }
    }
    throw new RuntimeException('Garmin page limit');
}
