<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

mattrics_require_auth();

const VALID_RPE_VALUES = [6.0, 7.0, 7.5, 8.0, 8.5, 9.0, 9.5, 10.0];
const VALID_SEX_VALUES = ['Male', 'Female', 'Prefer not to say'];
const VALID_EXPERIENCE_VALUES = ['Beginner', 'Intermediate', 'Advanced'];

function mattrics_settings_path(): string
{
    return mattrics_private_root() . '/user-settings.json';
}

function mattrics_default_settings(): array
{
    return [
        'bodyWeightKg'    => null,
        'defaultRpe'      => null,
        'birthday'        => null,
        'sex'             => null,
        'heightCm'        => null,
        'experienceLevel' => null,
    ];
}

function mattrics_read_settings(): array
{
    $path = mattrics_settings_path();
    if (!is_file($path)) {
        return mattrics_default_settings();
    }
    $raw = @file_get_contents($path);
    if ($raw === false) {
        return mattrics_default_settings();
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return mattrics_default_settings();
    }
    return array_merge(mattrics_default_settings(), $decoded);
}

function mattrics_write_settings(array $settings): void
{
    $dir = mattrics_private_root();
    mattrics_ensure_dir($dir);

    $path = mattrics_settings_path();
    $temp = $dir . '/user-settings.' . bin2hex(random_bytes(6)) . '.tmp';
    $json = json_encode($settings, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

    if ($json === false) {
        mattrics_send_json(['error' => 'Failed to encode settings.'], 500);
    }
    if (@file_put_contents($temp, $json, LOCK_EX) === false) {
        mattrics_send_json(['error' => 'Failed to write settings file.'], 500);
    }
    if (!@rename($temp, $path)) {
        @unlink($temp);
        mattrics_send_json(['error' => 'Failed to publish settings file.'], 500);
    }
}

function mattrics_validate_settings(array $body): array
{
    $errors = [];
    $out = mattrics_default_settings();

    // bodyWeightKg — required, float 20–300, max 1 decimal
    if (!array_key_exists('bodyWeightKg', $body) || $body['bodyWeightKg'] === null || $body['bodyWeightKg'] === '') {
        $errors['bodyWeightKg'] = 'Body weight is required.';
    } else {
        $val = filter_var($body['bodyWeightKg'], FILTER_VALIDATE_FLOAT);
        if ($val === false) {
            $errors['bodyWeightKg'] = 'Body weight must be a number.';
        } elseif ($val < 20 || $val > 300) {
            $errors['bodyWeightKg'] = 'Body weight must be between 20 and 300 kg.';
        } elseif (round($val, 1) !== round($val, 10)) {
            $errors['bodyWeightKg'] = 'Body weight may have at most 1 decimal place.';
        } else {
            $out['bodyWeightKg'] = round($val, 1);
        }
    }

    // defaultRpe — required, one of valid values
    if (!array_key_exists('defaultRpe', $body) || $body['defaultRpe'] === null || $body['defaultRpe'] === '') {
        $errors['defaultRpe'] = 'Default RPE is required.';
    } else {
        $val = filter_var($body['defaultRpe'], FILTER_VALIDATE_FLOAT);
        if ($val === false || !in_array($val, VALID_RPE_VALUES, true)) {
            $errors['defaultRpe'] = 'Default RPE must be one of: 6, 7, 7.5, 8, 8.5, 9, 9.5, 10.';
        } else {
            $out['defaultRpe'] = $val;
        }
    }

    // birthday — optional, ISO YYYY-MM-DD, past date, age 8–130
    if (isset($body['birthday']) && $body['birthday'] !== null && $body['birthday'] !== '') {
        $raw = trim((string) $body['birthday']);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
            $errors['birthday'] = 'Birthday must be in YYYY-MM-DD format.';
        } else {
            $dob = \DateTimeImmutable::createFromFormat('Y-m-d', $raw);
            if ($dob === false) {
                $errors['birthday'] = 'Birthday is not a valid date.';
            } else {
                $today = new \DateTimeImmutable('today');
                if ($dob >= $today) {
                    $errors['birthday'] = 'Birthday must be a past date.';
                } else {
                    $age = (int) $today->diff($dob)->y;
                    if ($age < 8) {
                        $errors['birthday'] = 'Age must be at least 8 years.';
                    } elseif ($age > 130) {
                        $errors['birthday'] = 'Age must be 130 years or less.';
                    } else {
                        $out['birthday'] = $raw;
                    }
                }
            }
        }
    }

    // sex — optional, one of valid values
    if (isset($body['sex']) && $body['sex'] !== null && $body['sex'] !== '') {
        $val = (string) $body['sex'];
        if (!in_array($val, VALID_SEX_VALUES, true)) {
            $errors['sex'] = 'Sex must be Male, Female, or Prefer not to say.';
        } else {
            $out['sex'] = $val;
        }
    }

    // heightCm — optional, integer 100–250
    if (isset($body['heightCm']) && $body['heightCm'] !== null && $body['heightCm'] !== '') {
        $val = filter_var($body['heightCm'], FILTER_VALIDATE_INT);
        if ($val === false) {
            $errors['heightCm'] = 'Height must be a whole number.';
        } elseif ($val < 100 || $val > 250) {
            $errors['heightCm'] = 'Height must be between 100 and 250 cm.';
        } else {
            $out['heightCm'] = $val;
        }
    }

    // experienceLevel — required
    if (!array_key_exists('experienceLevel', $body) || $body['experienceLevel'] === null || $body['experienceLevel'] === '') {
        $errors['experienceLevel'] = 'Training experience is required.';
    } else {
        $val = (string) $body['experienceLevel'];
        if (!in_array($val, VALID_EXPERIENCE_VALUES, true)) {
            $errors['experienceLevel'] = 'Training experience must be Beginner, Intermediate, or Advanced.';
        } else {
            $out['experienceLevel'] = $val;
        }
    }

    return ['errors' => $errors, 'settings' => $out];
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $settings = mattrics_read_settings();
    mattrics_send_json(['settings' => $settings]);
}

if ($method === 'POST') {
    $body = mattrics_read_json_body();
    $result = mattrics_validate_settings($body);

    if (!empty($result['errors'])) {
        mattrics_send_json(['errors' => $result['errors']], 422);
    }

    mattrics_write_settings($result['settings']);
    mattrics_send_json([
        'settings' => $result['settings'],
        'savedAt'  => gmdate('c'),
    ]);
}

header('Allow: GET, POST');
mattrics_send_json(['error' => 'Method not allowed.'], 405);
