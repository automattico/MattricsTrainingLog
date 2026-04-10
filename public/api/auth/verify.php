<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap-auth.php';
require_once mattrics_lib_root() . '/WebAuthn/src/WebAuthn.php';

mattrics_auth_session_start();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

$store = mattrics_load_credentials();
if ($store === null) {
    http_response_code(400);
    echo json_encode(['error' => 'No credential registered.']);
    exit;
}

if (empty($_SESSION['webauthn_challenge'])) {
    http_response_code(400);
    echo json_encode(['error' => 'No challenge in session. Request a challenge first.']);
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
    $challenge = base64_decode((string) $_SESSION['webauthn_challenge']);

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
        http_response_code(401);
        echo json_encode(['error' => 'Unknown credential.']);
        exit;
    }

    $matched = $store['credentials'][$matchIndex];

    $webAuthn->processGet(
        mattrics_b64url_decode((string) ($body['clientDataJSON'] ?? '')),
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
        mattrics_save_credentials($store);
    }

    unset($_SESSION['webauthn_challenge']);

    // Establish authenticated session
    session_regenerate_id(true);
    $_SESSION['mattrics_authed']    = true;
    $_SESSION['mattrics_authed_at'] = time();

    echo json_encode(['ok' => true]);

} catch (\Throwable $e) {
    http_response_code(401);
    echo json_encode(['error' => $e->getMessage()]);
}
