<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../bootstrap-auth.php';
require_once mattrics_lib_root() . '/WebAuthn/src/WebAuthn.php';

mattrics_require_auth();
header('Content-Type: application/json; charset=utf-8');

$store = mattrics_load_credentials();
if ($store === null) {
    mattrics_send_json(['error' => 'No credentials found.'], 404);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $list = array_map(fn($c) => [
        'internalId'   => $c['internalId'],
        'name'         => $c['name'],
        'registeredAt' => $c['registeredAt'],
        'created_at'   => $c['created_at'] ?? $c['registeredAt'] ?? null,
        'last_used_at' => $c['last_used_at'] ?? null,
    ], $store['credentials']);
    mattrics_send_json([
        'passkeys' => $list,
        'recovery' => mattrics_recovery_status($store),
        'csrfToken' => mattrics_csrf_token(),
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    mattrics_require_csrf();
    $body   = mattrics_read_json_body();
    $action = (string) ($body['action'] ?? '');
    $id     = (string) ($body['id'] ?? '');

    if ($action === 'regenerate_recovery') {
        $codes = mattrics_generate_recovery_codes($store);
        mattrics_save_credentials($store);
        mattrics_audit_log('recovery_codes_rotated', ['outcome' => 'success']);
        mattrics_send_json([
            'ok' => true,
            'recoveryCodes' => $codes,
            'recovery' => mattrics_recovery_status($store),
        ]);
    }

    // Find the credential by internalId
    $idx = null;
    foreach ($store['credentials'] as $i => $c) {
        if ($c['internalId'] === $id) {
            $idx = $i;
            break;
        }
    }
    if ($idx === null) {
        mattrics_send_json(['error' => 'Passkey not found.'], 404);
    }

    if ($action === 'rename') {
        $name = trim((string) ($body['name'] ?? ''));
        if ($name === '') {
            mattrics_send_json(['error' => 'Name is required.'], 400);
        }
        if (mb_strlen($name) > 64) {
            mattrics_send_json(['error' => 'Name too long (max 64 characters).'], 400);
        }
        $store['credentials'][$idx]['name'] = $name;
        $store['credentials'][$idx]['device_label'] = $name;
        mattrics_save_credentials($store);
        mattrics_audit_log('passkey_renamed', ['outcome' => 'success', 'credential' => $id]);
        mattrics_send_json(['ok' => true]);
    }

    if ($action === 'delete') {
        mattrics_enforce_rate_limit('verify');
        if (count($store['credentials']) <= 1) {
            mattrics_send_json(['error' => 'Cannot delete the last passkey. Add another passkey first.'], 409);
        }

        try {
            $rpId      = mattrics_rp_id();
            $webAuthn  = new lbuchs\WebAuthn\WebAuthn('Mattrics', $rpId, ['none', 'packed', 'apple'], true);
            $clientDataJSON = mattrics_b64url_decode((string) ($body['assertion']['clientDataJSON'] ?? ''));
            $challengeRecord = mattrics_validate_challenge_for_client_data('delete', $clientDataJSON);
            if ($challengeRecord === null) {
                throw new RuntimeException('Invalid delete challenge.');
            }
            $challenge = mattrics_challenge_from_client_data($clientDataJSON);

            // Find which credential was used to authenticate the deletion
            $usedCredId  = base64_encode(mattrics_b64url_decode((string) ($body['assertion']['id'] ?? '')));
            $matchIndex  = null;
            foreach ($store['credentials'] as $i => $cred) {
                if ($cred['credentialId'] === $usedCredId) {
                    $matchIndex = $i;
                    break;
                }
            }
            if ($matchIndex === null) {
                throw new RuntimeException('Unknown credential.');
            }

            $matched = $store['credentials'][$matchIndex];

            $webAuthn->processGet(
                $clientDataJSON,
                mattrics_b64url_decode((string) ($body['assertion']['authenticatorData'] ?? '')),
                mattrics_b64url_decode((string) ($body['assertion']['signature'] ?? '')),
                (string) $matched['credentialPublicKey'],
                $challenge,
                (int) ($matched['signatureCounter'] ?? 0),
                true,
                true
            );

            // Update counter for the credential used to authenticate
            $newCounter = $webAuthn->getSignatureCounter();
            if ($newCounter !== null) {
                $store['credentials'][$matchIndex]['signatureCounter'] = $newCounter;
            }
            $store['credentials'][$matchIndex]['last_used_at'] = gmdate('c');
            mattrics_consume_challenge('delete', $challenge);

        } catch (\Throwable $e) {
            mattrics_audit_log('passkey_delete_failed', ['outcome' => 'failure', 'credential' => $id]);
            mattrics_send_json(['error' => 'Passkey confirmation failed. Please try again.'], 401);
        }

        array_splice($store['credentials'], $idx, 1);
        mattrics_save_credentials($store);
        mattrics_audit_log('passkey_deleted', ['outcome' => 'success', 'credential' => $id]);
        mattrics_send_json(['ok' => true]);
    }

    mattrics_send_json(['error' => 'Unknown action.'], 400);
}

mattrics_send_json(['error' => 'Method not allowed.'], 405);
