<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Run this script in CLI mode.\n");
    exit(1);
}

require_once dirname(__DIR__) . '/src/clickfix_core.php';

$options = getopt('', ['strict']);
$strict = array_key_exists('strict', $options);

$baseDir = dirname(__DIR__);
$checks = [];

$addCheck = static function (string $name, bool $ok, string $details, string $severity = 'error') use (&$checks): void {
    $checks[] = [
        'name' => $name,
        'ok' => $ok,
        'details' => $details,
        'severity' => $severity,
    ];
};

$phpOk = version_compare(PHP_VERSION, '8.0.0', '>=');
$addCheck('php_version', $phpOk, 'Detected ' . PHP_VERSION . ' (required >= 8.0.0)');

foreach (['pdo_sqlite', 'openssl', 'json'] as $ext) {
    $addCheck('ext_' . $ext, extension_loaded($ext), 'Extension ' . $ext . ' must be loaded');
}

foreach ([
    'dashboard.php',
    'clickfix-report.php',
    'src/clickfix_core.php',
    'scripts/migrate.php',
    'scripts/generate_keys.php',
] as $relPath) {
    $full = $baseDir . '/' . $relPath;
    $addCheck('file_' . $relPath, is_file($full), 'Expected file: ' . $relPath);
}

foreach ([
    'data',
    'data/backups',
    'data/sessions',
    'data/logs',
    'keys',
] as $relPath) {
    $full = $baseDir . '/' . $relPath;
    $exists = is_dir($full);
    $writable = $exists && is_writable($full);
    $addCheck('dir_' . $relPath, $exists, 'Directory exists: ' . $relPath);
    $addCheck('write_' . $relPath, $writable, 'Directory writable: ' . $relPath);
}

foreach ([
    'clickfixlist',
    'clickfixallowlist',
    'alertsites',
] as $relPath) {
    $full = $baseDir . '/' . $relPath;
    $exists = is_file($full);
    $writable = $exists ? is_writable($full) : is_writable($baseDir);
    $addCheck('file_exists_' . $relPath, $exists, 'List file exists: ' . $relPath, 'warn');
    $addCheck('file_write_' . $relPath, $writable, 'List file writable (or base writable): ' . $relPath);
}

$envPath = $baseDir . '/.env.security';
$envExists = is_file($envPath) && is_readable($envPath);
$addCheck('env_exists', $envExists, '.env.security must exist and be readable');

$env = [];
if ($envExists) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach ($lines as $line) {
        $line = trim((string) $line);
        if ($line === '' || substr($line, 0, 1) === '#') {
            continue;
        }
        $eq = strpos($line, '=');
        if ($eq === false) {
            continue;
        }
        $k = trim(substr($line, 0, $eq));
        $v = trim(substr($line, $eq + 1));
        if ($k !== '') {
            $env[$k] = $v;
        }
    }
}

$jwt = (string) ($env['CLICKFIX_API_JWT_SECRET'] ?? '');
$pepper = (string) ($env['CLICKFIX_SECRET_PEPPER'] ?? '');
$licenses = (string) ($env['CLICKFIX_API_LICENSE_KEYS'] ?? '');
$addCheck('env_jwt', strlen($jwt) >= 64 && !str_contains($jwt, 'replace_with_'), 'JWT secret length >= 64 and not placeholder');
$addCheck('env_pepper', strlen($pepper) >= 32 && !str_contains($pepper, 'replace_with_'), 'Pepper length >= 32 and not placeholder');
$addCheck('env_licenses', $licenses !== '' && !str_contains($licenses, 'XXXXXX'), 'At least one license key configured');

$privatePathRaw = (string) ($env['CLICKFIX_SIGN_PRIVATE_KEY'] ?? 'keys/clickfix_sign_private.pem');
$privatePath = clickfix_resolve_env_path($privatePathRaw);
$addCheck('private_key_readable', is_file($privatePath) && is_readable($privatePath), 'Private signing key readable at ' . $privatePathRaw);

$dbPath = clickfix_resolve_db_path();
$dbReadable = is_file($dbPath) ? is_readable($dbPath) : is_writable(dirname($dbPath));
$addCheck('db_access', $dbReadable, 'Database path usable: ' . $dbPath);

try {
    $pdo = clickfix_open_db(false);
    $ok = (int) $pdo->query('SELECT 1')->fetchColumn() === 1;
    $addCheck('db_query', $ok, 'SQLite query test');
} catch (Throwable $exception) {
    $addCheck('db_query', false, 'SQLite query failed: ' . $exception->getMessage());
}

$errors = 0;
$warnings = 0;

echo "ClickFix preflight\n";
echo "Base dir: {$baseDir}\n\n";

foreach ($checks as $check) {
    $ok = (bool) $check['ok'];
    $severity = (string) $check['severity'];
    $tag = $ok ? 'OK' : ($severity === 'warn' ? 'WARN' : 'ERROR');
    if (!$ok) {
        if ($severity === 'warn') {
            $warnings += 1;
        } else {
            $errors += 1;
        }
    }
    echo '[' . $tag . '] ' . $check['name'] . ' - ' . $check['details'] . "\n";
}

echo "\nSummary: errors={$errors}, warnings={$warnings}\n";

if ($errors > 0 || ($strict && $warnings > 0)) {
    exit(1);
}
exit(0);
