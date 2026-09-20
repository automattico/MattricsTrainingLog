<?php
declare(strict_types=1);

$repoRoot = dirname(__DIR__);
$passed = 0;
$failed = 0;

function contract_assert(bool $condition, string $message): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        return;
    }
    $failed++;
    fwrite(STDERR, "FAIL: {$message}\n");
}

function contract_files(string $root): array
{
    $files = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
        $root,
        FilesystemIterator::SKIP_DOTS
    ));
    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $files[] = $file->getPathname();
        }
    }
    sort($files);
    return $files;
}

$endpointMethods = [
    'session.php' => ['GET'],
    'data.php' => ['GET'],
    'settings.php' => ['GET', 'POST'],
    'connectors.php' => ['GET', 'POST'],
    'exercises.php' => ['GET', 'POST', 'PATCH', 'DELETE'],
    'ai.php' => ['POST'],
];

$apiFiles = glob($repoRoot . '/api/*.php') ?: [];
sort($apiFiles);
contract_assert(count($apiFiles) === count($endpointMethods), 'api/ contains exactly the six product endpoints');

foreach ($apiFiles as $file) {
    $source = (string) file_get_contents($file);
    $name = basename($file);
    contract_assert(
        preg_match('/^<\?php\Rdeclare\(strict_types=1\);\R\Rrequire_authenticated\(\);/', $source) === 1,
        $name . ' starts with strict types and require_authenticated()'
    );
    contract_assert(isset($endpointMethods[$name]), $name . ' is in the endpoint inventory');
    if (isset($endpointMethods[$name]) && array_intersect($endpointMethods[$name], ['POST', 'PUT', 'PATCH', 'DELETE']) !== []) {
        contract_assert(str_contains($source, 'mattwarden_require_same_origin();'), $name . ' calls the Mattwarden same-origin guard');
        contract_assert(str_contains($source, 'mattwarden_require_csrf();'), $name . ' calls the Mattwarden CSRF guard');
    }
}

$forbiddenPatterns = [
    '/\$_SESSION/' => 'application session access',
    '/\bsession_/' => 'application session functions',
    '/HTTP_HOST/' => 'request Host trust',
    '/X-Forwarded-Proto/i' => 'proxy-derived scheme trust',
    '/\blocalhost\b/i' => 'host-based development behavior',
    '/WebAuthn/i' => 'application WebAuthn code',
    '/passkey-credential|auth-challenges|auth-rate-limits|auth-audit/i' => 'application auth state artifacts',
];

foreach (['public', 'api', 'lib'] as $tree) {
    foreach (contract_files($repoRoot . '/' . $tree) as $file) {
        $source = (string) file_get_contents($file);
        foreach ($forbiddenPatterns as $pattern => $label) {
            contract_assert(preg_match($pattern, $source) !== 1, str_replace($repoRoot . '/', '', $file) . ' contains no ' . $label);
        }
    }
}

$allowedStaticExtensions = [
    'html', 'css', 'js', 'mjs', 'json', 'yaml', 'md', 'txt', 'csv', 'xml', 'webmanifest',
    'svg', 'png', 'jpg', 'gif', 'webp', 'avif', 'ico', 'woff', 'woff2', 'ttf', 'otf',
    'pdf', 'wasm', 'mp4', 'webm', 'mp3',
];
foreach (contract_files($repoRoot . '/public') as $file) {
    $relative = substr($file, strlen($repoRoot . '/public/'));
    contract_assert(!str_contains('/' . $relative, '/.'), $relative . ' is not a dotfile');
    contract_assert(in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), $allowedStaticExtensions, true), $relative . ' has a gate-allowlisted extension');
}

$shell = (string) file_get_contents($repoRoot . '/public/index.html');
contract_assert(preg_match('/<script(?![^>]*\bsrc=)/i', $shell) !== 1, 'the shell has no inline script blocks');
contract_assert(preg_match('/\son[a-z]+\s*=/i', $shell) !== 1, 'the shell has no inline event handlers');
contract_assert(preg_match('/javascript\s*:/i', $shell) !== 1, 'the shell has no javascript URLs');
contract_assert(preg_match('/<style\b/i', $shell) !== 1, 'the shell has no style blocks');
contract_assert(preg_match('/\sstyle\s*=/i', $shell) !== 1, 'the shell has no inline style attributes');

foreach (contract_files($repoRoot . '/public/assets/js') as $file) {
    $source = (string) file_get_contents($file);
    $relative = substr($file, strlen($repoRoot . '/') );
    contract_assert(preg_match('/\son[a-z]+\s*=/i', $source) !== 1, $relative . ' templates have no inline event handlers');
    contract_assert(preg_match('/\sstyle\s*=/i', $source) !== 1, $relative . ' templates have no inline style attributes');
    contract_assert(preg_match('/setAttribute\s*\(\s*[\'\"]style[\'\"]/i', $source) !== 1, $relative . ' does not set a style attribute');
    contract_assert(preg_match('/\beval\s*\(|\bnew\s+Function\b/', $source) !== 1, $relative . ' does not use dynamic code evaluation');
}

if ($failed > 0) {
    fwrite(STDERR, "mattwarden-contract-tests: {$passed} passed, {$failed} failed\n");
    exit(1);
}

fwrite(STDOUT, "mattwarden-contract-tests: {$passed} passed, 0 failed\n");
