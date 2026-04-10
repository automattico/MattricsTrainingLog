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

// If credentials already exist, user must be authenticated to add another
if ($store !== null && empty($_SESSION['mattrics_authed'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Must be authenticated to add another passkey.']);
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

    $data = $webAuthn->processCreate(
        mattrics_b64url_decode((string) ($body['clientDataJSON'] ?? '')),
        mattrics_b64url_decode((string) ($body['attestationObject'] ?? '')),
        $challenge,
        true,   // requireUserVerification
        true,   // requireUserPresent
        false   // failIfRootMismatch — shared hosting has no CA bundle
    );

    // Accept name from request body, fall back to "Default"
    $name = trim((string) ($body['name'] ?? 'Default'));
    if ($name === '') $name = 'Default';

    $newEntry = [
        'internalId'          => bin2hex(random_bytes(16)),
        'name'                => $name,
        'credentialId'        => base64_encode((string) $data->credentialId),
        'credentialPublicKey' => $data->credentialPublicKey,
        'signatureCounter'    => (int) ($data->signatureCounter ?? 0),
        'registeredAt'        => gmdate('c'),
    ];

    if ($store === null) {
        $store = [
            'userId'      => $_SESSION['webauthn_user_id'] ?? '',
            'credentials' => [],
        ];
    }

    $store['credentials'][] = $newEntry;
    mattrics_save_credentials($store);

    unset($_SESSION['webauthn_challenge'], $_SESSION['webauthn_user_id']);

    echo json_encode(['ok' => true]);

} catch (\Throwable $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
