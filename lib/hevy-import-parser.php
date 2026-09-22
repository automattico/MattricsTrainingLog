<?php
declare(strict_types=1);

const MATTRICS_FOUNDATION_ESTIMATED_BODYWEIGHT_KG = 75.0;
const MATTRICS_FOUNDATION_BODYWEIGHT_LOAD_FACTOR = 0.4;
const MATTRICS_FOUNDATION_DEFAULT_RPE = 7.5;

function mattrics_foundation_hevy_is_description(?string $description): bool
{
    $firstLine = preg_split('/\R/', trim((string) $description), 2)[0] ?? '';
    return preg_match('/\bhevy(?:[ \t]*app(?:\.com)?)?\b/i', $firstLine) === 1;
}

function mattrics_foundation_strip_hevy_header(?string $description): string
{
    $text = trim((string) $description);
    if (!mattrics_foundation_hevy_is_description($text)) {
        return $text;
    }

    $lines = preg_split('/\R/', $text, 2);
    return trim((string) ($lines[1] ?? ''));
}

function mattrics_foundation_parse_hevy_description(?string $description): ?array
{
    $text = trim((string) $description);
    if (!mattrics_foundation_hevy_is_description($text)) {
        return null;
    }

    $body = trim(mattrics_foundation_strip_hevy_header($text));
    if ($body === '') {
        return [];
    }

    $blocks = preg_split('/\n\s*\n/', $body) ?: [];
    $parsed = [];
    foreach ($blocks as $block) {
        $lines = array_values(array_filter(array_map(
            static fn(string $line): string => trim($line),
            preg_split('/\R/', trim((string) $block)) ?: []
        ), static fn(string $line): bool => $line !== ''));

        if ($lines === []) {
            continue;
        }

        $parsed[] = [
            'name' => $lines[0],
            'sets' => array_slice($lines, 1),
        ];
    }

    return $parsed;
}

function mattrics_foundation_parse_hevy_set_line(?string $setText, ?array $exerciseConfig = null, ?string $exerciseName = null): array
{
    $rawText = trim((string) $setText);
    if ($rawText === '') {
        return ['kind' => 'unknown'];
    }

    $lowerText = strtolower($rawText);
    $exercise = strtolower(trim((string) $exerciseName));
    $isBodyweightExercise = (bool) ($exerciseConfig['bodyweightEligible'] ?? false)
        || preg_match('/(push ?up|pull ?up|chin ?up|dip|sit ?up|crunch|leg raise|bodyweight|bw|air squat|pistol squat)/i', $exercise) === 1;
    $isTimeBased = preg_match('/\b\d+\s*(?:sec|secs|second|seconds|min|mins|minute|minutes|hr|hrs|hour|hours)\b/i', $lowerText) === 1
        || preg_match('/\b\d{1,2}:\d{2}\b/', $lowerText) === 1
        || preg_match('/\bfor time\b/i', $lowerText) === 1
        || preg_match('/\btime\b/i', $lowerText) === 1;

    $rpe = MATTRICS_FOUNDATION_DEFAULT_RPE;
    if (preg_match('/(?:\brpe\b\s*[:@]?\s*|@\s*)(\d+(?:[.,]\d+)?)/i', $rawText, $matches) === 1) {
        $parsedRpe = mattrics_foundation_parse_decimal($matches[1]);
        if ($parsedRpe !== null) {
            $rpe = $parsedRpe;
        }
    }
    $effortFactor = 0.5 + ($rpe / 10);

    if ($isTimeBased) {
        $distanceKm = 0.0;
        if (preg_match('/(\d+(?:[.,]\d+)?)\s*km\b/i', $rawText, $distanceMatch) === 1) {
            $distanceKm = mattrics_foundation_parse_decimal($distanceMatch[1]) ?? 0.0;
        }

        return [
            'kind' => 'time',
            'minutes' => mattrics_foundation_parse_duration_minutes($rawText),
            'distanceKm' => $distanceKm,
        ];
    }

    $weightKg = 0.0;
    $reps = 0;
    $bodyweightTag = preg_match('/\b(?:bw|body ?weight)\b/i', $rawText) === 1;

    if (preg_match('/(\d+(?:[.,]\d+)?)\s*(kg|kgs|lb|lbs)\s*(?:x|×)\s*(\d+)/i', $rawText, $match) === 1) {
        $weightKg = mattrics_foundation_convert_weight_kg($match[1], $match[2]);
        $reps = (int) $match[3];
    } elseif (preg_match('/(\d+(?:[.,]\d+)?)\s*(kg|kgs|lb|lbs)\s*(?:for|-|–|—)?\s*(\d+)\s*reps?\b/i', $rawText, $match) === 1) {
        $weightKg = mattrics_foundation_convert_weight_kg($match[1], $match[2]);
        $reps = (int) $match[3];
    } elseif (preg_match('/(\d+)\s*(?:reps?)?\s*(?:x|×|@)\s*(\d+(?:[.,]\d+)?)\s*(kg|kgs|lb|lbs)/i', $rawText, $match) === 1) {
        $reps = (int) $match[1];
        $weightKg = mattrics_foundation_convert_weight_kg($match[2], $match[3]);
    } elseif (preg_match('/(\d+)\s*reps?\s*(?:at|with|-|–|—)?\s*(\d+(?:[.,]\d+)?)\s*(kg|kgs|lb|lbs)\b/i', $rawText, $match) === 1) {
        $reps = (int) $match[1];
        $weightKg = mattrics_foundation_convert_weight_kg($match[2], $match[3]);
    } else {
        preg_match('/(\d+(?:[.,]\d+)?)\s*(kg|kgs|lb|lbs)/i', $rawText, $weightOnly);
        preg_match('/(?:^|[^\d])(\d+)\s*(?:reps?|x)?(?:$|[^\d])/i', $rawText, $repsOnly);

        if (($bodyweightTag || $isBodyweightExercise) && $repsOnly !== []) {
            $reps = (int) $repsOnly[1];
            $weightKg = MATTRICS_FOUNDATION_ESTIMATED_BODYWEIGHT_KG * MATTRICS_FOUNDATION_BODYWEIGHT_LOAD_FACTOR;
        } elseif ($weightOnly !== [] && preg_match('/\bx\b|×|\breps?\b/i', $rawText) === 1 && $repsOnly !== []) {
            $reps = (int) $repsOnly[1];
            $weightKg = mattrics_foundation_convert_weight_kg($weightOnly[1], $weightOnly[2]);
        }
    }

    if ($weightKg <= 0 || $reps <= 0) {
        return ['kind' => 'unknown'];
    }

    return [
        'kind' => 'parsed',
        'reps' => $reps,
        'weightKg' => $weightKg,
        'rpe' => $rpe,
        'effortFactor' => $effortFactor,
        'load' => $weightKg * $reps * $effortFactor,
    ];
}

function mattrics_foundation_parse_duration_minutes(string $rawText): float
{
    $total = 0.0;
    if (preg_match_all('/(\d+(?:[.,]\d+)?)\s*(hr|hrs|hour|hours|min|mins|minute|minutes|sec|secs|second|seconds)\b/i', $rawText, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $amount = mattrics_foundation_parse_decimal($match[1]);
            if ($amount === null) {
                continue;
            }
            $unit = strtolower($match[2]);
            if (str_starts_with($unit, 'h')) {
                $total += $amount * 60;
            } elseif (str_starts_with($unit, 's')) {
                $total += $amount / 60;
            } else {
                $total += $amount;
            }
        }
    }

    if ($total > 0) {
        return $total;
    }

    if (preg_match('/\b(\d{1,2}):(\d{2})(?::(\d{2}))?\b/', $rawText, $clockMatch) !== 1) {
        return 0.0;
    }

    $first = (int) $clockMatch[1];
    $second = (int) $clockMatch[2];
    $third = isset($clockMatch[3]) ? (int) $clockMatch[3] : 0;

    if (isset($clockMatch[3])) {
        return ($first * 60) + $second + ($third / 60);
    }

    return $first + ($second / 60);
}

function mattrics_foundation_parse_decimal(string $value): ?float
{
    $normalized = str_replace(',', '.', trim($value));
    if (!is_numeric($normalized)) {
        return null;
    }

    return (float) $normalized;
}

function mattrics_foundation_convert_weight_kg(string $value, string $unit): float
{
    $amount = mattrics_foundation_parse_decimal($value);
    if ($amount === null) {
        return 0.0;
    }

    return preg_match('/^lb/i', $unit) === 1 ? $amount * 0.453592 : $amount;
}
