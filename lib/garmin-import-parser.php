<?php
declare(strict_types=1);

const MATTRICS_FOUNDATION_GARMIN_CSV_REQUIRED_HEADERS = [
    'Activity Type',
    'Date',
    'Title',
    'Distance',
    'Time',
    'Avg HR',
    'Max HR',
    'Avg Run Cadence',
    'Avg Pace',
    'Total Ascent',
];

function mattrics_foundation_validate_garmin_csv_headers(array $headers): array
{
    $missingHeaders = [];
    foreach (MATTRICS_FOUNDATION_GARMIN_CSV_REQUIRED_HEADERS as $requiredHeader) {
        if (!in_array($requiredHeader, $headers, true)) {
            $missingHeaders[] = $requiredHeader;
        }
    }

    if ($missingHeaders !== []) {
        throw new RuntimeException(
            'Garmin CSV headers are missing required fields: ' . implode(', ', $missingHeaders) . '.'
        );
    }

    return $headers;
}

function mattrics_foundation_build_garmin_activity_id(
    string $localStartText,
    string $title,
    string $activityType,
    ?float $durationMinutes,
    ?float $distanceKm
): string {
    $signature = [
        'kind' => 'garmin-activity-csv-v1',
        'localStart' => $localStartText,
        'title' => mattrics_normalize_config_name($title),
        'activityType' => mattrics_normalize_config_name($activityType),
        'durationMinutesRounded' => $durationMinutes !== null ? round($durationMinutes, 2) : null,
        'distanceKmRounded' => $distanceKm !== null ? round($distanceKm, 3) : null,
    ];

    return 'garmin-csv-' . hash(
        'sha256',
        json_encode($signature, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            ?: implode('|', array_map(static fn(mixed $value): string => (string) $value, $signature))
    );
}

function mattrics_foundation_parse_garmin_number(mixed $value): ?float
{
    $text = trim((string) $value);
    if ($text === '' || $text === '--') {
        return null;
    }

    $normalized = str_replace(',', '', $text);
    if (!is_numeric($normalized)) {
        throw new RuntimeException('Expected Garmin numeric value, got: ' . $text);
    }

    return (float) $normalized;
}

function mattrics_foundation_parse_garmin_int(mixed $value): ?int
{
    $number = mattrics_foundation_parse_garmin_number($value);
    return $number === null ? null : (int) round($number);
}

function mattrics_foundation_parse_garmin_duration_minutes(mixed $value, string $field, int $rowNumber): ?float
{
    $text = trim((string) $value);
    if ($text === '' || $text === '--') {
        return null;
    }

    if (preg_match('/^(\d{2}):(\d{2}):(\d{2}(?:\.\d+)?)$/', $text, $matches) === 1) {
        $hours = (int) $matches[1];
        $minutes = (int) $matches[2];
        $seconds = (float) $matches[3];

        return ($hours * 60) + $minutes + ($seconds / 60);
    }

    if (preg_match('/^(\d+):(\d{2}(?:\.\d+)?)$/', $text, $matches) === 1) {
        $minutes = (int) $matches[1];
        $seconds = (float) $matches[2];

        return $minutes + ($seconds / 60);
    }

    throw new RuntimeException('Garmin CSV ' . $field . ' has an unsupported duration at row ' . $rowNumber . '.');
}

function mattrics_foundation_parse_garmin_pace_minutes(mixed $value, string $field, int $rowNumber): ?float
{
    $text = trim((string) $value);
    if ($text === '' || $text === '--') {
        return null;
    }

    if (preg_match('/^(\d+):(\d{2})(?::(\d{2}(?:\.\d+)?))?$/', $text, $matches) !== 1) {
        return null;
    }

    if (isset($matches[3])) {
        $hours = (int) $matches[1];
        $minutes = (int) $matches[2];
        $seconds = (float) $matches[3];
        return ($hours * 60) + $minutes + ($seconds / 60);
    }

    $minutes = (int) $matches[1];
    $seconds = (float) $matches[2];
    return $minutes + ($seconds / 60);
}

function mattrics_foundation_parse_garmin_datetime(string $value, string $timezone, int $rowNumber): DateTimeImmutable
{
    $dateTime = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value, new DateTimeZone($timezone));
    $errors = DateTimeImmutable::getLastErrors();
    $hasErrors = is_array($errors)
        && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0);

    if (!$dateTime instanceof DateTimeImmutable || $hasErrors) {
        throw new RuntimeException('Garmin CSV Date is invalid at row ' . $rowNumber . '.');
    }

    return $dateTime;
}

function mattrics_foundation_read_garmin_csv_activities(string $path, string $timezone): array
{
    $handle = fopen($path, 'rb');
    if (!is_resource($handle)) {
        throw new RuntimeException('Failed to open Garmin CSV input.');
    }

    try {
        $header = fgetcsv($handle, 0, ',', '"', '\\');
        if (!is_array($header) || $header === []) {
            throw new RuntimeException('Garmin CSV file is empty.');
        }

        $header = array_map(static fn(mixed $value): string => (string) $value, $header);
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]) ?? $header[0];
        $header = mattrics_foundation_validate_garmin_csv_headers($header);

        $activities = [];
        $rowCount = 0;
        $csvRowNumber = 1;
        while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
            $csvRowNumber++;
            $rowCount++;

            if (!is_array($row)) {
                throw new RuntimeException('Garmin CSV row could not be read at row ' . $csvRowNumber . '.');
            }

            if (count($row) !== count($header)) {
                throw new RuntimeException('Garmin CSV row has the wrong number of columns at row ' . $csvRowNumber . '.');
            }

            $record = array_combine($header, $row);
            if (!is_array($record)) {
                throw new RuntimeException('Garmin CSV row could not be mapped at row ' . $csvRowNumber . '.');
            }

            $activityType = trim(mattrics_foundation_assert_utf8((string) ($record['Activity Type'] ?? ''), 'Activity Type', $csvRowNumber));
            $title = trim(mattrics_foundation_assert_utf8((string) ($record['Title'] ?? ''), 'Title', $csvRowNumber));
            $dateText = trim((string) ($record['Date'] ?? ''));

            if ($activityType === '' || $title === '' || $dateText === '') {
                throw new RuntimeException('Garmin CSV row is missing required activity fields at row ' . $csvRowNumber . '.');
            }

            $dateTime = mattrics_foundation_parse_garmin_datetime($dateText, $timezone, $csvRowNumber);
            $distanceKm = mattrics_foundation_parse_garmin_number($record['Distance'] ?? null);
            $durationMinutes = mattrics_foundation_parse_garmin_duration_minutes($record['Time'] ?? null, 'Time', $csvRowNumber);
            $avgPace = mattrics_foundation_parse_garmin_pace_minutes($record['Avg Pace'] ?? null, 'Avg Pace', $csvRowNumber);
            $sourceActivityId = mattrics_foundation_build_garmin_activity_id(
                $dateText,
                $title,
                $activityType,
                $durationMinutes,
                $distanceKm
            );

            $activities[] = [
                'activityType' => $activityType,
                'title' => $title,
                'activityDate' => $dateTime->format('Y-m-d'),
                'startedAt' => $dateTime->setTimezone(new DateTimeZone('UTC'))->format('c'),
                'sourceActivityIdRaw' => $sourceActivityId,
                'sourceActivityId' => $sourceActivityId,
                'distanceKm' => $distanceKm,
                'durationMinutes' => $durationMinutes,
                'avgHr' => mattrics_foundation_parse_garmin_int($record['Avg HR'] ?? null),
                'maxHr' => mattrics_foundation_parse_garmin_int($record['Max HR'] ?? null),
                'avgCadence' => mattrics_foundation_parse_garmin_number($record['Avg Run Cadence'] ?? null),
                'avgPaceMinPerKm' => $avgPace,
                'avgSpeedKmh' => ($distanceKm !== null && $durationMinutes !== null && $durationMinutes > 0)
                    ? round($distanceKm / ($durationMinutes / 60), 2)
                    : null,
                'elevationGainM' => mattrics_foundation_parse_garmin_int($record['Total Ascent'] ?? null),
            ];
        }

        return [
            'rowCount' => $rowCount,
            'activities' => $activities,
        ];
    } finally {
        fclose($handle);
    }
}
