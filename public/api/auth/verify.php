<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap-auth.php';
require_once mattrics_lib_root() . '/WebAuthn/src/WebAuthn.php';

mattrics_auth_session_start();
mattrics_require_https_if_needed();
mattrics_enforce_rate_limit('verify');

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

$store = mattrics_load_credentials();
if ($store === null) {
    http_response_code(400);
    echo json_encode(['error' => 'Authentication is not available.']);
    exit;
}

$body = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($body)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON body.']);
    exit;
}

try {
    $rpId      = mattrics_rp_id();
    $webAuthn  = new lbuchs\WebAuthn\WebAuthn('Mattrics', $rpId, ['none', 'packed', 'apple'], true);
    $clientDataJSON = mattrics_b64url_decode((string) ($body['clientDataJSON'] ?? ''));
    $challengeRecord = mattrics_validate_challenge_for_client_data('login', $clientDataJSON);
    if ($challengeRecord === null) {
        throw new RuntimeException('Invalid login challenge.');
    }
    $challenge = mattrics_challenge_from_client_data($clientDataJSON);

    // Find which credential the browser used (it echoes back its credentialId as $body['id'])
    $usedCredId = base64_encode(mattrics_b64url_decode((string) ($body['id'] ?? '')));
    $matchIndex = null;
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
        mattrics_b64url_decode((string) ($body['authenticatorData'] ?? '')),
        mattrics_b64url_decode((string) ($body['signature'] ?? '')),
        (string) $matched['credentialPublicKey'],
        $challenge,
        (int) ($matched['signatureCounter'] ?? 0),
        true,  // requireUserVerification
        true   // requireUserPresent
    );

    // Update signature counter for this specific credential (clone detection)
    $newCounter = $webAuthn->getSignatureCounter();
    if ($newCounter !== null) {
        $store['credentials'][$matchIndex]['signatureCounter'] = $newCounter;
    }
    $store['credentials'][$matchIndex]['last_used_at'] = gmdate('c');
    mattrics_save_credentials($store);

    mattrics_consume_challenge('login', $challenge);

    // Establish authenticated session
    session_regenerate_id(true);
    $_SESSION['mattrics_authed']    = true;
    $_SESSION['mattrics_authed_at'] = time();
    $_SESSION['mattrics_last_seen_at'] = time();
    mattrics_csrf_token();
    mattrics_audit_log('login_success', ['outcome' => 'success', 'credential' => $matched['internalId'] ?? null]);

    echo json_encode(['ok' => true]);

} catch (\Throwable $e) {
    http_response_code(401);
    mattrics_audit_log('login_failure', ['outcome' => 'failure']);
    echo json_encode(['error' => 'Passkey sign-in failed. Please try again.']);
}
