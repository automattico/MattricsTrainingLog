<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap-auth.php';
require_once mattrics_lib_root() . '/WebAuthn/src/WebAuthn.php';

mattrics_auth_session_start();

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';
$rpId   = mattrics_rp_id();

if ($action === 'register') {
    $store = mattrics_load_credentials();

    // If a store already exists, the user must be authenticated to add another passkey
    if ($store !== null && empty($_SESSION['mattrics_authed'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Must be authenticated to add another passkey.']);
        exit;
    }

    $webAuthn = new lbuchs\WebAuthn\WebAuthn('Mattrics', $rpId, ['none', 'packed', 'apple'], true);

    // Reuse existing userId so all credentials belong to the same WebAuthn user handle
    $userId     = $store !== null ? base64_decode((string) $store['userId']) : random_bytes(16);
    // Exclude already-registered credential IDs so the same authenticator cannot be re-registered
    $excludeIds = $store !== null
        ? array_map(fn($c) => base64_decode((string) $c['credentialId']), $store['credentials'])
        : [];

    $createArgs = $webAuthn->getCreateArgs(
        $userId,
        'owner',
        'Mattrics Owner',
        60,
        true,       // requireResidentKey — passkey stored on device
        true,       // requireUserVerification
        null,       // crossPlatformAttachment — allow any
        $excludeIds
    );

    $_SESSION['webauthn_challenge'] = base64_encode($webAuthn->getChallenge()->getBinaryString());
    $_SESSION['webauthn_user_id']   = base64_encode($userId);

    echo json_encode($createArgs);
    exit;
}

if ($action === 'login') {
    $store = mattrics_load_credentials();
    if ($store === null) {
        http_response_code(404);
        echo json_encode(['error' => 'No credential registered.']);
        exit;
    }

    // Pass all registered credential IDs so any registered passkey can authenticate
    $credentialIds = array_map(
        fn($c) => base64_decode((string) $c['credentialId']),
        $store['credentials']
    );

    $webAuthn = new lbuchs\WebAuthn\WebAuthn('Mattrics', $rpId, ['none', 'packed', 'apple'], true);
    $getArgs  = $webAuthn->getGetArgs(
        $credentialIds,
        60,
        true, true, true, true, true, // allow all transports
        true  // requireUserVerification
    );

    $_SESSION['webauthn_challenge'] = base64_encode($webAuthn->getChallenge()->getBinaryString());

    echo json_encode($getArgs);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Invalid action.']);
