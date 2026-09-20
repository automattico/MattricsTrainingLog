<?php
declare(strict_types=1);

function mattrics_fixture_bootstrap(
    string $method,
    string $pathInfo = '',
    bool $authenticated = true,
    bool $includeCsrf = true
): void {
    $siteDir = trim((string) getenv('MATTWARDEN_TEST_SITE_DIR'));
    if ($siteDir === '') {
        throw new RuntimeException('Fixture requires MATTWARDEN_TEST_SITE_DIR.');
    }
    $siteLib = $siteDir . '/lib';
    if (!file_exists($siteLib) && !is_link($siteLib)) {
        $repoLib = dirname(__DIR__, 2) . '/lib';
        if (!symlink($repoLib, $siteLib)) {
            throw new RuntimeException('Fixture could not attach the shared library directory.');
        }
    }
    putenv('MATTWARDEN_TEST_AUTHENTICATED=' . ($authenticated ? '1' : '0'));
    require_once dirname(__DIR__) . '/stubs/mattwarden.php';

    $_SERVER['REQUEST_METHOD'] = $method;
    if ($pathInfo !== '') {
        $_SERVER['PATH_INFO'] = $pathInfo;
    } else {
        unset($_SERVER['PATH_INFO']);
    }
    if (!in_array($method, ['GET', 'HEAD'], true)) {
        $_SERVER['HTTP_ORIGIN'] = MATTWARDEN_TEST_ORIGIN;
        $_SERVER['HTTP_X_CSRF_TOKEN'] = $includeCsrf ? MATTWARDEN_TEST_CSRF_TOKEN : '';
    }
}
