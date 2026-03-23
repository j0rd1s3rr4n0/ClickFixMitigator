<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Run this script in CLI mode.\n");
    exit(1);
}

$options = getopt('', [
    'force',
    'domain::',
    'path::',
    'tier::',
    'rpm::',
    'no-embed-license'
]);

$force = array_key_exists('force', $options);
$domain = trim((string) ($options['domain'] ?? 'clickfix.jordiserrano.me'));
$domain = preg_replace('#^https?://#i', '', $domain);
$domain = preg_replace('#/.*$#', '', $domain);
$domain = strtolower(trim((string) $domain));
if ($domain === '') {
    $domain = 'clickfix.jordiserrano.me';
}
$tier = strtolower(trim((string) ($options['tier'] ?? 'premium')));
$rpm = (int) ($options['rpm'] ?? 600);
$embedLicense = !array_key_exists('no-embed-license', $options);

if (!in_array($tier, ['basic', 'premium', 'enterprise'], true)) {
    $tier = 'premium';
}
$rpm = max(30, min(2000, $rpm));

$webDir = dirname(__DIR__);
$repoDir = dirname(dirname($webDir));
$keysDir = $webDir . '/keys';
$envPath = $webDir . '/.env.security';
$privatePath = $keysDir . '/clickfix_sign_private.pem';
$publicPath = $keysDir . '/clickfix_sign_public.pem';
$premiumConfigPath = $webDir . '/clickfix-score-config-premium.json';
$extensionBackgroundPath = $repoDir . '/browser-extension/background.js';

if (!is_dir($keysDir) && !@mkdir($keysDir, 0700, true) && !is_dir($keysDir)) {
    fwrite(STDERR, "[error] Cannot create keys directory: {$keysDir}\n");
    exit(1);
}

function parse_env_file(string $path): array
{
    if (!is_readable($path)) {
        return [];
    }
    $result = [];
    $lines = file($path, FILE_IGNORE_NEW_LINES) ?: [];
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
            $result[$k] = $v;
        }
    }
    return $result;
}

function write_private_file(string $path, string $content): void
{
    file_put_contents($path, $content);
    if (strncasecmp(PHP_OS, 'WIN', 3) !== 0) {
        @chmod($path, 0600);
    }
}

function random_hex(int $bytes): string
{
    return bin2hex(random_bytes($bytes));
}

function normalize_deploy_path(string $path): string
{
    $clean = trim($path);
    if ($clean === '' || $clean === '/') {
        return '';
    }
    $clean = preg_replace('#^/+|/+$#', '', $clean);
    return $clean === '' ? '' : '/' . $clean;
}

function generate_license_key(): string
{
    $segments = [];
    for ($i = 0; $i < 4; $i += 1) {
        $segments[] = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
    }
    return 'LIC-' . implode('-', $segments);
}

function extract_public_key_from_private(string $privatePem): string
{
    $res = openssl_pkey_get_private($privatePem);
    if ($res === false) {
        throw new RuntimeException('Cannot parse generated private key.');
    }
    $details = openssl_pkey_get_details($res);
    openssl_pkey_free($res);
    if (!is_array($details) || empty($details['key'])) {
        throw new RuntimeException('Cannot extract public key details.');
    }
    return (string) $details['key'];
}

$privatePem = '';
$publicPem = '';

if (!$force && is_readable($privatePath) && is_readable($publicPath)) {
    $privatePem = (string) file_get_contents($privatePath);
    $publicPem = (string) file_get_contents($publicPath);
}

if ($privatePem === '' || $publicPem === '') {
    $keyResource = openssl_pkey_new([
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
        'private_key_bits' => 3072
    ]);
    if ($keyResource === false) {
        fwrite(STDERR, "[error] OpenSSL key generation failed.\n");
        exit(1);
    }
    $exported = openssl_pkey_export($keyResource, $generatedPrivate);
    if (!$exported || !is_string($generatedPrivate) || $generatedPrivate === '') {
        fwrite(STDERR, "[error] Cannot export generated private key.\n");
        exit(1);
    }
    $generatedPublic = extract_public_key_from_private($generatedPrivate);
    write_private_file($privatePath, $generatedPrivate);
    file_put_contents($publicPath, $generatedPublic);
    if (strncasecmp(PHP_OS, 'WIN', 3) !== 0) {
        @chmod($publicPath, 0644);
    }
    $privatePem = $generatedPrivate;
    $publicPem = $generatedPublic;
}

$existingEnv = parse_env_file($envPath);
$jwtSecret = (!$force && isset($existingEnv['CLICKFIX_API_JWT_SECRET']) && trim($existingEnv['CLICKFIX_API_JWT_SECRET']) !== '')
    ? trim($existingEnv['CLICKFIX_API_JWT_SECRET'])
    : random_hex(48);
$pepper = (!$force && isset($existingEnv['CLICKFIX_SECRET_PEPPER']) && trim($existingEnv['CLICKFIX_SECRET_PEPPER']) !== '')
    ? trim($existingEnv['CLICKFIX_SECRET_PEPPER'])
    : random_hex(32);
$licenseEntry = trim((string) ($existingEnv['CLICKFIX_API_LICENSE_KEYS'] ?? ''));
if ($force || $licenseEntry === '') {
    $licenseKey = generate_license_key();
    $licenseEntry = $licenseKey . ':' . $tier . ':' . $rpm;
} else {
    $first = explode(',', $licenseEntry)[0];
    $licenseKey = trim(explode(':', $first)[0] ?? '');
}
if ($licenseKey === '') {
    $licenseKey = generate_license_key();
    $licenseEntry = $licenseKey . ':' . $tier . ':' . $rpm;
}
$deployPath = normalize_deploy_path((string) ($options['path'] ?? '/'));

$keyId = (!$force && isset($existingEnv['CLICKFIX_SIGN_KEY_ID']) && trim($existingEnv['CLICKFIX_SIGN_KEY_ID']) !== '')
    ? trim($existingEnv['CLICKFIX_SIGN_KEY_ID'])
    : ('k' . gmdate('Ymd'));

$origins = ['https://' . $domain];
if (stripos($domain, 'www.') !== 0 && substr_count($domain, '.') === 1) {
    $origins[] = 'https://www.' . $domain;
}
$origins = array_values(array_unique($origins));
$allowedOrigins = implode(',', $origins);

$envLines = [
    '# Auto-generated by scripts/generate_keys.php',
    '# Do not commit this file to a public repository.',
    'CLICKFIX_API_JWT_SECRET=' . $jwtSecret,
    'CLICKFIX_SECRET_PEPPER=' . $pepper,
    'CLICKFIX_API_LICENSE_KEYS=' . $licenseEntry,
    'CLICKFIX_REPORT_REQUIRE_AUTH=1',
    'CLICKFIX_ALLOWED_ORIGINS=' . $allowedOrigins,
    'CLICKFIX_SIGN_PRIVATE_KEY=keys/clickfix_sign_private.pem',
    'CLICKFIX_SIGN_KEY_ID=' . $keyId,
    'CLICKFIX_PREMIUM_SCORE_CONFIG_PATH=clickfix-score-config-premium.json',
    ''
];
file_put_contents($envPath, implode(PHP_EOL, $envLines));

if (!is_file($premiumConfigPath)) {
    $premiumConfig = [
        'scoreConfig' => [
            'weights' => ['signals' => 0.55, 'clipboard' => 0.35, 'context' => 0.10],
            'contextBaseScore' => 52,
            'rules' => [
                'signals' => [
                    'commandMatch' => 24,
                    'shellHint' => 18,
                    'evasionHint' => 16,
                    'clipboardWarning' => 10,
                    'copyTriggerHint' => 7
                ],
                'clipboard' => [
                    'hasCommand' => 24,
                    'hasExecutionHint' => 20,
                    'hasBase64' => 14,
                    'hasHighEntropy' => 12
                ]
            ]
        ]
    ];
    file_put_contents(
        $premiumConfigPath,
        json_encode($premiumConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL
    );
}

$extensionUpdated = false;
if (is_readable($extensionBackgroundPath)) {
    $backgroundCode = (string) file_get_contents($extensionBackgroundPath);
    $publicPemEscaped = rtrim($publicPem);
    $replacementPemConst = "const SCORE_CONFIG_PUBLIC_KEY_PEM = `" . $publicPemEscaped . "`;";
    $backgroundCode = preg_replace(
        '/const SCORE_CONFIG_PUBLIC_KEY_PEM = `[\s\S]*?`;/',
        $replacementPemConst,
        $backgroundCode,
        1
    );
    $backgroundCode = preg_replace(
        '/const CLICKFIX_DEPLOY_ORIGIN = ".*?";/',
        'const CLICKFIX_DEPLOY_ORIGIN = "https://' . addslashes($domain) . '";',
        $backgroundCode,
        1
    );
    $backgroundCode = preg_replace(
        '/const CLICKFIX_DEPLOY_BASE_PATH = ".*?";/',
        'const CLICKFIX_DEPLOY_BASE_PATH = "' . addslashes($deployPath) . '";',
        $backgroundCode,
        1
    );
    if ($embedLicense) {
        $backgroundCode = preg_replace(
            '/apiLicenseKey:\s*"[^"]*",/',
            'apiLicenseKey: "' . $licenseKey . '",',
            $backgroundCode,
            1
        );
    }
    file_put_contents($extensionBackgroundPath, $backgroundCode);
    $extensionUpdated = true;
}

echo "[ok] Private key: {$privatePath}\n";
echo "[ok] Public key: {$publicPath}\n";
echo "[ok] Server env: {$envPath}\n";
echo "[ok] Premium config: {$premiumConfigPath}\n";
if ($extensionUpdated) {
    echo "[ok] Extension updated: {$extensionBackgroundPath}\n";
} else {
    echo "[warn] Extension not found, skipped extension update: {$extensionBackgroundPath}\n";
}
echo "[ok] License key: {$licenseKey}\n";
echo "[ok] Extension origin/path: https://{$domain}" . ($deployPath !== '' ? $deployPath : '/') . "\n";
echo "[ok] Tier/RPM: {$tier}/{$rpm}\n";
