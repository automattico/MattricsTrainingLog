<?php
declare(strict_types=1);

function mattrics_postgres_target(string $databaseUrl): array
{
    $parts = parse_url(trim($databaseUrl));
    $host = $parts['host'] ?? '';
    $database = ltrim($parts['path'] ?? '', '/');
    $user = rawurldecode($parts['user'] ?? '');
    $password = rawurldecode($parts['pass'] ?? '');
    $port = $parts['port'] ?? 5432;
    if (($parts['scheme'] ?? '') !== 'postgres' || isset($parts['query']) || isset($parts['fragment'])
        || !preg_match('/^(?:[A-Za-z0-9][A-Za-z0-9.-]*|\[::1\])$/D', $host)
        || !preg_match('/^[A-Za-z0-9_.-]+$/D', $database) || $user === '' || $port < 1
        || str_contains($user, "\0") || str_contains($password, "\0")) {
        throw new RuntimeException('Expected a postgres:// connection with a host, database, and username.');
    }

    return [trim($host, '[]'), $port, $database, $user, $password];
}

function mattrics_foundation_connect(string $databaseUrl, ?string $caFile = null): PDO
{
    [$host, $port, $database, $user, $password] = mattrics_postgres_target($databaseUrl);
    if (!extension_loaded('pdo_pgsql')) {
        throw new RuntimeException('The pdo_pgsql PHP extension is required.');
    }

    $loopback = in_array($host, ['127.0.0.1', '::1'], true);
    if ($caFile === null && defined('MATTWARDEN_SITE_DIR')) {
        $privateCa = MATTWARDEN_SITE_DIR . '/private/postgres-ca.pem';
        if (!$loopback || file_exists($privateCa)) {
            $caFile = $privateCa;
        }
    }
    $dsn = sprintf('pgsql:host=%s;port=%d;dbname=%s;connect_timeout=8;gssencmode=disable', $host, $port, $database);
    if ($caFile === null && $loopback) {
        $dsn .= ';sslmode=disable';
    } else {
        if ($caFile === null || !str_starts_with($caFile, '/') || preg_match('/[;\x00-\x1f]/', $caFile)
            || !is_file($caFile) || !is_readable($caFile)) {
            throw new RuntimeException('Hosted Postgres requires a readable private CA file.');
        }
        $dsn .= ";sslmode=verify-full;sslrootcert='" . addcslashes($caFile, "\\'") . "'";
    }

    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec("SET TIME ZONE 'UTC'");
    return $pdo;
}
