<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

session_start();

$dbPath = __DIR__ . '/data/clickfix.sqlite';
$schemaPath = null;
$preferredSchema = __DIR__ . '/data/clickfix.sql';
if (is_readable($preferredSchema)) {
    $schemaPath = $preferredSchema;
} else {
    $schemaCandidates = glob(__DIR__ . '/data/*.sql') ?: [];
    foreach ($schemaCandidates as $candidate) {
        if (is_readable($candidate)) {
            $schemaPath = $candidate;
            break;
        }
    }
}

$defaultSchemaSql = <<<SQL
CREATE TABLE IF NOT EXISTS reports (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    received_at TEXT NOT NULL,
    url TEXT,
    hostname TEXT,
    message TEXT,
    detected_content TEXT,
    full_context TEXT,
    signals_json TEXT,
    blocked INTEGER DEFAULT 0,
    user_agent TEXT,
    ip TEXT,
    country TEXT
);

CREATE TABLE IF NOT EXISTS stats (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    received_at TEXT NOT NULL,
    enabled INTEGER,
    alert_count INTEGER,
    block_count INTEGER,
    manual_sites_json TEXT,
    country TEXT
);

CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    created_at TEXT NOT NULL,
    username TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    role TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS appeals (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    created_at TEXT NOT NULL,
    domain TEXT NOT NULL,
    reason TEXT NOT NULL,
    contact TEXT,
    status TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS list_actions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    created_at TEXT NOT NULL,
    user_id INTEGER,
    action TEXT NOT NULL,
    list_type TEXT NOT NULL,
    domain TEXT NOT NULL,
    reason TEXT NOT NULL
);
SQL;

$stats = [
    'total_alerts' => 0,
    'total_blocks' => 0,
    'manual_sites' => [],
    'countries' => [],
    'last_update' => null,
    'extension_enabled' => null,
    'recent_count' => 0,
    'alert_sites' => []
];

$recentDetections = [];
$alertSites = [];
$alertsitesFile = __DIR__ . '/alertsites';
$blocklistFile = __DIR__ . '/clickfixlist';
$allowlistFile = __DIR__ . '/clickfixallowlist';
$reportLogEntries = [];
$debugLogEntries = [];
$flashErrors = [];
$flashNotices = [];
$currentUser = null;
$adminCode = getenv('CLICKFIX_ADMIN_CODE') ?: '';
$chartData = [
    'daily' => [],
    'hourly' => array_fill(0, 24, 0),
    'countries' => [],
    'signals' => []
];

$signalLabels = [
    'mismatch' => 'Discrepancia',
    'commandMatch' => 'Comando',
    'winRHint' => 'Win + R',
    'winXHint' => 'Win + X',
    'browserErrorHint' => 'Error navegador',
    'fixActionHint' => 'Acción de arreglo',
    'captchaHint' => 'Captcha',
    'consoleHint' => 'Consola',
    'shellHint' => 'Shell',
    'pasteSequenceHint' => 'Secuencia pegado',
    'fileExplorerHint' => 'Explorador',
    'copyTriggerHint' => 'Disparador copia',
    'evasionHint' => 'Evasión'
];

function loadListFile(string $path): array
{
    if (!is_readable($path)) {
        return [];
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES) ?: [];
    $items = [];
    foreach ($lines as $line) {
        $line = trim((string) $line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        $items[] = $line;
    }
    return array_values(array_unique($items));
}

function loadLogEntries(string $path, int $limit = 50): array
{
    if (!is_readable($path)) {
        return [];
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES) ?: [];
    if ($limit > 0) {
        $lines = array_slice($lines, -$limit);
    }
    $entries = [];
    foreach ($lines as $line) {
        $decoded = json_decode($line, true);
        if (is_array($decoded)) {
            unset($decoded['ip']);
            $entries[] = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            continue;
        }
        $entries[] = $line;
    }
    return array_reverse($entries);
}

function ensureAdminTables(PDO $pdo): void
{
    $statements = [
        'CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            created_at TEXT NOT NULL,
            username TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            role TEXT NOT NULL
        )',
        'CREATE TABLE IF NOT EXISTS appeals (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            created_at TEXT NOT NULL,
            domain TEXT NOT NULL,
            reason TEXT NOT NULL,
            contact TEXT,
            status TEXT NOT NULL
        )',
        'CREATE TABLE IF NOT EXISTS list_actions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            created_at TEXT NOT NULL,
            user_id INTEGER,
            action TEXT NOT NULL,
            list_type TEXT NOT NULL,
            domain TEXT NOT NULL,
            reason TEXT NOT NULL
        )'
    ];
    foreach ($statements as $statement) {
        $pdo->exec($statement);
    }

    $columns = $pdo->query('PRAGMA table_info(users)')->fetchAll(PDO::FETCH_ASSOC);
    $existing = [];
    foreach ($columns as $column) {
        $existing[(string) ($column['name'] ?? '')] = true;
    }
    if (!isset($existing['created_at'])) {
        $pdo->exec('ALTER TABLE users ADD COLUMN created_at TEXT');
    }
    if (!isset($existing['role'])) {
        $pdo->exec('ALTER TABLE users ADD COLUMN role TEXT');
    }

    $columns = $pdo->query('PRAGMA table_info(appeals)')->fetchAll(PDO::FETCH_ASSOC);
    $existing = [];
    foreach ($columns as $column) {
        $existing[(string) ($column['name'] ?? '')] = true;
    }
    if (!isset($existing['contact'])) {
        $pdo->exec('ALTER TABLE appeals ADD COLUMN contact TEXT');
    }
    if (!isset($existing['status'])) {
        $pdo->exec('ALTER TABLE appeals ADD COLUMN status TEXT');
    }
}

function ensureDatabase(string $dbPath, ?string $schemaPath, string $schemaSqlFallback): void
{
    if (file_exists($dbPath)) {
        return;
    }
    $dataDir = dirname($dbPath);
    if (!is_dir($dataDir)) {
        @mkdir($dataDir, 0775, true);
    }
    try {
        $pdo = new PDO('sqlite:' . $dbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $schemaSql = null;
        if ($schemaPath !== null) {
            $schemaSql = file_get_contents($schemaPath);
        }
        if (!is_string($schemaSql) || $schemaSql === '') {
            $schemaSql = $schemaSqlFallback;
        }
        $pdo->exec($schemaSql);
        ensureAdminTables($pdo);
    } catch (Throwable $exception) {
        return;
    }
}

function normalizeDomain(string $domain): string
{
    $trimmed = trim($domain);
    if ($trimmed === '') {
        return '';
    }
    $trimmed = strtolower($trimmed);
    $trimmed = preg_replace('/^https?:\/\//', '', $trimmed);
    $trimmed = preg_replace('/\/.*$/', '', $trimmed);
    return $trimmed ?? '';
}

function isValidDomain(string $domain): bool
{
    return $domain !== '' && preg_match('/^[a-z0-9.-]+$/i', $domain) === 1;
}

function ensureListFile(string $path): void
{
    if (is_readable($path)) {
        return;
    }
    @file_put_contents($path, "# Managed ClickFix list\n", FILE_APPEND | LOCK_EX);
}

function updateListFile(string $path, string $domain, string $mode): bool
{
    ensureListFile($path);
    $lines = is_readable($path) ? (file($path, FILE_IGNORE_NEW_LINES) ?: []) : [];
    $normalized = strtolower($domain);
    $existing = [];
    foreach ($lines as $line) {
        $line = trim((string) $line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        $existing[strtolower($line)] = $line;
    }
    if ($mode === 'add') {
        if (isset($existing[$normalized])) {
            return true;
        }
        $lines[] = $domain;
    } else {
        if (!isset($existing[$normalized])) {
            return true;
        }
        $lines = array_filter($lines, static function ($line) use ($normalized) {
            $line = trim((string) $line);
            if ($line === '' || str_starts_with($line, '#')) {
                return true;
            }
            return strtolower($line) !== $normalized;
        });
    }
    $content = implode(PHP_EOL, $lines);
    if ($content !== '') {
        $content .= PHP_EOL;
    }
    return file_put_contents($path, $content, LOCK_EX) !== false;
}

function requireCsrfToken(): ?string
{
    return $_SESSION['csrf_token'] ?? null;
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

$alertSites = loadListFile($alertsitesFile);
$stats['alert_sites'] = $alertSites;
$reportLogEntries = loadLogEntries(__DIR__ . '/clickfix-report.log', 60);
$debugLogEntries = loadLogEntries(__DIR__ . '/clickfix-debug.log', 60);

ensureDatabase($dbPath, $schemaPath, $defaultSchemaSql);

$pdo = null;
if (is_readable($dbPath)) {
    try {
        $pdo = new PDO('sqlite:' . $dbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        ensureAdminTables($pdo);
    } catch (Throwable $exception) {
        $pdo = null;
    }
}

if ($pdo instanceof PDO && isset($_SESSION['user_id'])) {
    $statement = $pdo->prepare('SELECT id, username, role FROM users WHERE id = :id');
    $statement->execute([':id' => (int) $_SESSION['user_id']]);
    $currentUser = $statement->fetch(PDO::FETCH_ASSOC) ?: null;
    if (!$currentUser) {
        unset($_SESSION['user_id']);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = (string) ($_POST['csrf_token'] ?? '');
    if (!hash_equals(requireCsrfToken() ?? '', $csrfToken)) {
        $flashErrors[] = 'Sesión inválida, recarga la página.';
    } else {
        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'register' && $pdo instanceof PDO) {
            $username = trim((string) ($_POST['username'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');
            $adminInput = trim((string) ($_POST['admin_code'] ?? ''));
            if ($username === '' || $password === '') {
                $flashErrors[] = 'Usuario y contraseña son obligatorios.';
            } else {
                $role = ($adminCode !== '' && hash_equals($adminCode, $adminInput)) ? 'admin' : 'analyst';
                try {
                    $statement = $pdo->prepare(
                        'INSERT INTO users (created_at, username, password_hash, role)
                         VALUES (:created_at, :username, :password_hash, :role)'
                    );
                    $statement->execute([
                        ':created_at' => gmdate('c'),
                        ':username' => $username,
                        ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
                        ':role' => $role
                    ]);
                    $flashNotices[] = 'Registro completado. Ahora puedes iniciar sesión.';
                } catch (Throwable $exception) {
                    $flashErrors[] = 'No se pudo registrar. Usa otro usuario.';
                }
            }
        } elseif ($action === 'login' && $pdo instanceof PDO) {
            $username = trim((string) ($_POST['username'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');
            $statement = $pdo->prepare('SELECT id, username, role, password_hash FROM users WHERE username = :username');
            $statement->execute([':username' => $username]);
            $user = $statement->fetch(PDO::FETCH_ASSOC);
            if ($user && password_verify($password, (string) $user['password_hash'])) {
                $_SESSION['user_id'] = (int) $user['id'];
                $currentUser = ['id' => $user['id'], 'username' => $user['username'], 'role' => $user['role']];
                $flashNotices[] = 'Sesión iniciada.';
            } else {
                $flashErrors[] = 'Credenciales inválidas.';
            }
        } elseif ($action === 'logout') {
            unset($_SESSION['user_id']);
            $currentUser = null;
            $flashNotices[] = 'Sesión cerrada.';
        } elseif ($action === 'appeal' && $pdo instanceof PDO) {
            $domain = normalizeDomain((string) ($_POST['domain'] ?? ''));
            $reason = trim((string) ($_POST['reason'] ?? ''));
            $contact = trim((string) ($_POST['contact'] ?? ''));
            if (!isValidDomain($domain) || $reason === '') {
                $flashErrors[] = 'Dominio y motivo son obligatorios.';
            } else {
                $statement = $pdo->prepare(
                    'INSERT INTO appeals (created_at, domain, reason, contact, status)
                     VALUES (:created_at, :domain, :reason, :contact, :status)'
                );
                $statement->execute([
                    ':created_at' => gmdate('c'),
                    ':domain' => $domain,
                    ':reason' => $reason,
                    ':contact' => $contact,
                    ':status' => 'open'
                ]);
                $flashNotices[] = 'Desistimiento enviado. Revisaremos tu solicitud.';
            }
        } elseif ($action === 'list_action' && $pdo instanceof PDO) {
            $isAdmin = $currentUser && ($currentUser['role'] ?? '') === 'admin';
            $domain = normalizeDomain((string) ($_POST['domain'] ?? ''));
            $reason = trim((string) ($_POST['reason'] ?? ''));
            $listType = (string) ($_POST['list_type'] ?? '');
            $mode = (string) ($_POST['mode'] ?? '');
            if (!$isAdmin) {
                $flashErrors[] = 'Se requiere un usuario administrador.';
            } elseif (!isValidDomain($domain) || $reason === '') {
                $flashErrors[] = 'Dominio y motivo son obligatorios.';
            } else {
                $listPath = $listType === 'allow' ? $allowlistFile : $blocklistFile;
                $ok = updateListFile($listPath, $domain, $mode === 'remove' ? 'remove' : 'add');
                if ($ok) {
                    $statement = $pdo->prepare(
                        'INSERT INTO list_actions (created_at, user_id, action, list_type, domain, reason)
                         VALUES (:created_at, :user_id, :action, :list_type, :domain, :reason)'
                    );
                    $statement->execute([
                        ':created_at' => gmdate('c'),
                        ':user_id' => (int) ($currentUser['id'] ?? 0),
                        ':action' => $mode === 'remove' ? 'remove' : 'add',
                        ':list_type' => $listType,
                        ':domain' => $domain,
                        ':reason' => $reason
                    ]);
                    $flashNotices[] = 'Lista actualizada.';
                } else {
                    $flashErrors[] = 'No se pudo actualizar la lista.';
                }
            }
        } elseif ($action === 'appeal_resolve' && $pdo instanceof PDO) {
            $isAdmin = $currentUser && ($currentUser['role'] ?? '') === 'admin';
            $appealId = (int) ($_POST['appeal_id'] ?? 0);
            if (!$isAdmin) {
                $flashErrors[] = 'Se requiere un usuario administrador.';
            } else {
                $statement = $pdo->prepare('UPDATE appeals SET status = :status WHERE id = :id');
                $statement->execute([':status' => 'resolved', ':id' => $appealId]);
                $flashNotices[] = 'Desistimiento actualizado.';
            }
        }
    }
}

if (is_readable($dbPath)) {
    try {
        if (!$pdo instanceof PDO) {
            $pdo = new PDO('sqlite:' . $dbPath);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }
        $statsRows = $pdo->query(
            'SELECT received_at, enabled, alert_count, block_count, manual_sites_json, country
             FROM stats
             ORDER BY received_at DESC
             LIMIT 50'
        )->fetchAll(PDO::FETCH_ASSOC);

        foreach ($statsRows as $entry) {
            $stats['total_alerts'] = max($stats['total_alerts'], (int) ($entry['alert_count'] ?? 0));
            $stats['total_blocks'] = max($stats['total_blocks'], (int) ($entry['block_count'] ?? 0));
            if ($stats['extension_enabled'] === null && isset($entry['enabled'])) {
                $stats['extension_enabled'] = (bool) $entry['enabled'];
            }
            $manualSites = json_decode((string) ($entry['manual_sites_json'] ?? ''), true);
            if (is_array($manualSites)) {
                $stats['manual_sites'] = array_unique(array_merge($stats['manual_sites'], $manualSites));
            }
            $country = (string) ($entry['country'] ?? '');
            if ($country !== '') {
                $stats['countries'][$country] = ($stats['countries'][$country] ?? 0) + 1;
            }
            if ($stats['last_update'] === null && !empty($entry['received_at'])) {
                $stats['last_update'] = (string) $entry['received_at'];
            }
        }
        try {
            $reportRows = $pdo->query(
                'SELECT received_at, url, hostname, message, detected_content, full_context, signals_json, blocked, country
                 FROM reports
                 ORDER BY received_at DESC
                 LIMIT 200'
            )->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $exception) {
            $reportRows = $pdo->query(
                'SELECT received_at, url, hostname, message, detected_content, signals_json, country
                 FROM reports
                 ORDER BY received_at DESC
                 LIMIT 200'
            )->fetchAll(PDO::FETCH_ASSOC);
        }

        foreach ($reportRows as $entry) {
            $detected = trim((string) ($entry['detected_content'] ?? ''));
            $message = trim((string) ($entry['message'] ?? ''));
            if ($detected === '' && $message === '') {
                continue;
            }

            $timestamp = strtotime((string) ($entry['received_at'] ?? ''));
            if ($timestamp !== false) {
                $dateKey = date('Y-m-d', $timestamp);
                $hourKey = (int) date('G', $timestamp);
                $chartData['daily'][$dateKey] = ($chartData['daily'][$dateKey] ?? 0) + 1;
                if (isset($chartData['hourly'][$hourKey])) {
                    $chartData['hourly'][$hourKey] += 1;
                }
            }

            $country = (string) ($entry['country'] ?? '');
            if ($country !== '') {
                $chartData['countries'][$country] = ($chartData['countries'][$country] ?? 0) + 1;
            }

            $signals = json_decode((string) ($entry['signals_json'] ?? ''), true);
            $signalList = [];
            if (is_array($signals)) {
                foreach ($signals as $signal => $enabled) {
                    if ($enabled) {
                        $chartData['signals'][$signal] = ($chartData['signals'][$signal] ?? 0) + 1;
                        $signalList[] = $signalLabels[$signal] ?? $signal;
                    }
                }
            }

            $hostname = trim((string) ($entry['hostname'] ?? ''));
            if ($hostname === '') {
                $url = (string) ($entry['url'] ?? '');
                if ($url !== '') {
                    $parsedUrl = parse_url($url);
                    $hostname = (string) ($parsedUrl['host'] ?? '');
                }
            }

            $recentDetections[] = [
                'hostname' => $hostname !== '' ? $hostname : 'Sin dominio',
                'url' => (string) ($entry['url'] ?? ''),
                'timestamp' => (string) ($entry['received_at'] ?? ''),
                'message' => $message,
                'detected' => $detected,
                'full_context' => trim((string) ($entry['full_context'] ?? '')),
                'blocked' => (bool) ($entry['blocked'] ?? false),
                'signals' => $signalList
            ];
        }
    } catch (Throwable $exception) {
        $stats = $stats;
    }
    $recentDetections = array_slice($recentDetections, 0, 50);
    $stats['recent_count'] = count($recentDetections);
}

$blocklistItems = loadListFile($blocklistFile);
$allowlistItems = loadListFile($allowlistFile);
$appeals = [];
if ($pdo instanceof PDO) {
    try {
        $appeals = $pdo->query(
            'SELECT id, created_at, domain, reason, contact, status
             FROM appeals
             ORDER BY created_at DESC
             LIMIT 100'
        )->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $exception) {
        $appeals = [];
    }
}

ksort($chartData['daily']);
arsort($chartData['countries']);
arsort($chartData['signals']);

$signalChartLabels = [];
$signalChartValues = [];
foreach ($chartData['signals'] as $signal => $count) {
    $signalChartLabels[] = $signalLabels[$signal] ?? $signal;
    $signalChartValues[] = $count;
}

  $chartPayload = [
    'daily' => [
        'labels' => array_keys($chartData['daily']),
        'values' => array_values($chartData['daily'])
    ],
    'hourly' => [
        'labels' => range(0, 23),
        'values' => array_values($chartData['hourly'])
    ],
    'countries' => [
        'labels' => array_keys($chartData['countries']),
        'values' => array_values($chartData['countries'])
    ],
    'signals' => [
        'labels' => $signalChartLabels,
        'values' => $signalChartValues
    ]
];
?>
<!doctype html>
<html lang="es">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>ClickFix Dashboard</title>
    <style>
      body {
        font-family: "Segoe UI", system-ui, sans-serif;
        margin: 0;
        background: radial-gradient(circle at top, #eef2ff 0%, #f8fafc 50%, #f1f5f9 100%);
        color: #0f172a;
      }
      .page {
        max-width: 1200px;
        margin: 0 auto;
        padding: 32px 24px 48px;
      }
      header {
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
        margin-bottom: 24px;
      }
      h1 {
        margin: 0 0 8px;
        font-size: 30px;
        letter-spacing: -0.02em;
      }
      .muted {
        color: #64748b;
        font-size: 14px;
      }
      .status-pill {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 6px 12px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 600;
        background: #e2e8f0;
        color: #1e293b;
      }
      .status-pill.enabled {
        background: #dcfce7;
        color: #166534;
      }
      .status-pill.disabled {
        background: #fee2e2;
        color: #991b1b;
      }
      .grid {
        display: grid;
        gap: 16px;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      }
      .card {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 16px;
        box-shadow: 0 10px 30px -20px rgba(15, 23, 42, 0.35);
      }
      .stat-card {
        display: flex;
        flex-direction: column;
        gap: 8px;
        padding: 18px;
        border-radius: 16px;
        background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
        border: 1px solid #e2e8f0;
        box-shadow: 0 14px 30px -24px rgba(15, 23, 42, 0.45);
      }
      .stat-label {
        font-size: 13px;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.08em;
      }
      .stat-value {
        font-size: 32px;
        font-weight: 700;
        color: #0f172a;
      }
      .stat-footnote {
        font-size: 12px;
        color: #94a3b8;
      }
      .layout {
        display: grid;
        gap: 20px;
        grid-template-columns: minmax(0, 2fr) minmax(280px, 1fr);
        margin-top: 20px;
      }
      .section-title {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 12px;
      }
      .chart-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 16px;
      }
      .chart-card {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 12px;
        max-height: 150pt;
      }
      .chart-card h3 {
        margin: 0 0 10px;
        font-size: 15px;
        color: #0f172a;
      }
      .chip-list {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
      }
      .chip {
        padding: 6px 10px;
        background: #e0e7ff;
        color: #312e81;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 600;
      }
      .list-block {
        max-height: 240px;
        overflow-y: auto;
        padding-right: 6px;
      }
      .badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 10px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.03em;
        text-transform: uppercase;
        background: #e2e8f0;
        color: #0f172a;
      }
      .badge-blocked {
        background: #fee2e2;
        color: #991b1b;
      }
      .meta-row {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        align-items: center;
      }
      .signal-chip {
        background: #e0f2fe;
        color: #0369a1;
      }
      h2 {
        font-size: 18px;
        margin: 0 0 12px;
      }
      h3 {
        font-size: 16px;
        margin: 0 0 8px;
      }
      ul {
        list-style: none;
        padding: 0;
        margin: 0;
      }
      li {
        padding: 6px 0;
        border-bottom: 1px solid #e2e8f0;
      }
      li:last-child {
        border-bottom: none;
      }
      pre {
        white-space: pre-wrap;
        background: #0f172a;
        color: #e2e8f0;
        padding: 12px;
        border-radius: 10px;
        font-size: 13px;
      }
      .report-card {
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 12px 14px;
        margin-bottom: 12px;
        background: #f8fafc;
      }
      .report-card summary {
        cursor: pointer;
        font-weight: 600;
        display: flex;
        flex-direction: column;
        gap: 4px;
      }
      .report-meta {
        font-size: 12px;
        color: #64748b;
      }
      .report-section {
        margin-top: 10px;
      }
      .context-panel {
        margin-top: 8px;
        border: 1px dashed #cbd5f5;
        border-radius: 10px;
        padding: 10px;
        background: #eef2ff;
      }
      .context-panel pre {
        background: #111827;
      }
      .log-grid {
        display: grid;
        gap: 16px;
      }
      .log-entry {
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 12px;
        background: #fff;
      }
      .log-entry pre {
        margin: 0;
        font-size: 12px;
      }
      .form-grid {
        display: grid;
        gap: 12px;
      }
      .form-grid input,
      .form-grid textarea,
      .form-grid select {
        width: 100%;
        padding: 8px 10px;
        border-radius: 8px;
        border: 1px solid #cbd5f5;
        font-family: inherit;
      }
      .form-grid textarea {
        min-height: 90px;
      }
      .form-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
      }
      .button-primary {
        background: #0f172a;
        color: #fff;
        border: none;
        border-radius: 8px;
        padding: 8px 12px;
        cursor: pointer;
      }
      .button-secondary {
        background: #64748b;
        color: #fff;
        border: none;
        border-radius: 8px;
        padding: 8px 12px;
        cursor: pointer;
      }
      .alert-box {
        padding: 10px 12px;
        border-radius: 8px;
        margin-bottom: 12px;
        font-size: 14px;
      }
      .alert-box.error {
        background: #fee2e2;
        color: #991b1b;
      }
      .alert-box.notice {
        background: #dcfce7;
        color: #166534;
      }
      @media (max-width: 960px) {
        .layout {
          grid-template-columns: 1fr;
        }
      }
      @media (max-width: 640px) {
        .page {
          padding: 24px 16px 40px;
        }
        h1 {
          font-size: 24px;
        }
        .stat-value {
          font-size: 26px;
        }
      }
    </style>
  </head>
  <body>
    <div class="page">
      <header>
        <div>
          <h1>ClickFix Dashboard</h1>
          <div class="muted">Última actualización: <?= htmlspecialchars((string) ($stats['last_update'] ?? 'N/D'), ENT_QUOTES, 'UTF-8'); ?></div>
        </div>
        <?php
          $extensionStatus = $stats['extension_enabled'];
          $statusClass = $extensionStatus === null ? '' : ($extensionStatus ? 'enabled' : 'disabled');
          $statusLabel = $extensionStatus === null ? 'Estado extensión: sin datos' : ($extensionStatus ? 'Extensión activa' : 'Extensión pausada');
        ?>
        <span class="status-pill <?= htmlspecialchars($statusClass, ENT_QUOTES, 'UTF-8'); ?>">
          <?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8'); ?>
        </span>
      </header>

      <?php foreach ($flashErrors as $error): ?>
        <div class="alert-box error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
      <?php endforeach; ?>
      <?php foreach ($flashNotices as $notice): ?>
        <div class="alert-box notice"><?= htmlspecialchars($notice, ENT_QUOTES, 'UTF-8'); ?></div>
      <?php endforeach; ?>

      <section class="card">
        <div class="section-title">
          <h2>Acceso y registro</h2>
          <?php if ($currentUser): ?>
            <span class="muted">Sesión: <?= htmlspecialchars((string) $currentUser['username'], ENT_QUOTES, 'UTF-8'); ?> (<?= htmlspecialchars((string) $currentUser['role'], ENT_QUOTES, 'UTF-8'); ?>)</span>
          <?php else: ?>
            <span class="muted">Accede para administrar listas</span>
          <?php endif; ?>
        </div>
        <div class="grid">
          <form method="post" class="form-grid">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
            <input type="hidden" name="action" value="login" />
            <label>
              Usuario
              <input type="text" name="username" required />
            </label>
            <label>
              Contraseña
              <input type="password" name="password" required />
            </label>
            <div class="form-actions">
              <button class="button-primary" type="submit">Iniciar sesión</button>
            </div>
          </form>
          <form method="post" class="form-grid">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
            <input type="hidden" name="action" value="register" />
            <label>
              Usuario
              <input type="text" name="username" required />
            </label>
            <label>
              Contraseña
              <input type="password" name="password" required />
            </label>
            <label>
              Código administrador (opcional)
              <input type="text" name="admin_code" />
            </label>
            <div class="form-actions">
              <button class="button-secondary" type="submit">Registrarse</button>
            </div>
          </form>
          <?php if ($currentUser): ?>
            <form method="post" class="form-grid">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
              <input type="hidden" name="action" value="logout" />
              <div class="form-actions">
                <button class="button-secondary" type="submit">Cerrar sesión</button>
              </div>
            </form>
          <?php endif; ?>
        </div>
      </section>

      <section class="grid">
        <div class="stat-card">
          <span class="stat-label">Alertas totales</span>
          <div class="stat-value"><?= (int) $stats['total_alerts']; ?></div>
          <span class="stat-footnote">Histórico de la extensión</span>
        </div>
        <div class="stat-card">
          <span class="stat-label">Bloqueos totales</span>
          <div class="stat-value"><?= (int) $stats['total_blocks']; ?></div>
          <span class="stat-footnote">Prevenciones confirmadas</span>
        </div>
        <div class="stat-card">
          <span class="stat-label">Sitios manuales</span>
          <div class="stat-value"><?= (int) count($stats['manual_sites']); ?></div>
          <span class="stat-footnote">Dominios cargados manualmente</span>
        </div>
        <div class="stat-card">
          <span class="stat-label">Alertas recientes</span>
          <div class="stat-value"><?= (int) $stats['recent_count']; ?></div>
          <span class="stat-footnote">Últimos eventos visibles</span>
        </div>
        <div class="stat-card">
          <span class="stat-label">Sitios alertados</span>
          <div class="stat-value"><?= (int) count($stats['alert_sites']); ?></div>
          <span class="stat-footnote">Pendientes de revisión</span>
        </div>
      </section>

      <div class="layout">
        <div>
          <section class="card">
            <div class="section-title">
              <h2>Listas públicas</h2>
              <span class="muted">Visibles para todos</span>
            </div>
            <div class="grid">
              <div>
                <h3>Allowlist</h3>
                <?php if (empty($allowlistItems)): ?>
                  <div class="muted">Sin dominios.</div>
                <?php else: ?>
                  <div class="list-block">
                    <ul>
                      <?php foreach ($allowlistItems as $domain): ?>
                        <li><?= htmlspecialchars($domain, ENT_QUOTES, 'UTF-8'); ?></li>
                      <?php endforeach; ?>
                    </ul>
                  </div>
                <?php endif; ?>
              </div>
              <div>
                <h3>Blocklist</h3>
                <?php if (empty($blocklistItems)): ?>
                  <div class="muted">Sin dominios.</div>
                <?php else: ?>
                  <div class="list-block">
                    <ul>
                      <?php foreach ($blocklistItems as $domain): ?>
                        <li><?= htmlspecialchars($domain, ENT_QUOTES, 'UTF-8'); ?></li>
                      <?php endforeach; ?>
                    </ul>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          </section>

          <section class="card" style="margin-top: 24px;">
            <h2>¿Está tu dominio bloqueado? Realiza el desistimiento aquí</h2>
            <form method="post" class="form-grid">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
              <input type="hidden" name="action" value="appeal" />
              <label>
                Dominio
                <input type="text" name="domain" placeholder="ejemplo.com" required />
              </label>
              <label>
                Motivo del desistimiento
                <textarea name="reason" required></textarea>
              </label>
              <label>
                Contacto (opcional)
                <input type="text" name="contact" placeholder="correo@dominio.com" />
              </label>
              <div class="form-actions">
                <button class="button-primary" type="submit">Enviar desistimiento</button>
              </div>
            </form>
          </section>

          <section class="card" style="margin-top: 24px;">
            <div class="section-title">
              <h2>Administrar listas</h2>
              <span class="muted">Solo administradores</span>
            </div>
            <form method="post" class="form-grid">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
              <input type="hidden" name="action" value="list_action" />
              <label>
                Dominio
                <input type="text" name="domain" placeholder="ejemplo.com" required />
              </label>
              <label>
                Tipo de lista
                <select name="list_type">
                  <option value="allow">Allowlist</option>
                  <option value="block">Blocklist</option>
                </select>
              </label>
              <label>
                Motivo
                <textarea name="reason" required></textarea>
              </label>
              <div class="form-actions">
                <button class="button-primary" type="submit" name="mode" value="add">Agregar</button>
                <button class="button-secondary" type="submit" name="mode" value="remove">Quitar</button>
              </div>
            </form>
          </section>

          <section class="card">
            <div class="section-title">
              <h2>Analítica de alertas</h2>
              <span class="muted">Últimos reportes registrados</span>
            </div>
            <div class="chart-grid">
              <div class="chart-card">
                <h3>Alertas por día</h3>
                <canvas id="chart-alerts-day" height="140"></canvas>
              </div>
              <div class="chart-card">
                <h3>Alertas por hora</h3>
                <canvas id="chart-alerts-hour" height="140"></canvas>
              </div>
              <div class="chart-card">
                <h3>Distribución por país</h3>
                <canvas id="chart-alerts-country" height="140"></canvas>
              </div>
              <div class="chart-card">
                <h3>Tipos de señales</h3>
                <canvas id="chart-alerts-signals" height="140"></canvas>
              </div>
            </div>
          </section>

          <section class="card" style="margin-top: 24px;">
            <h2>Detecciones recientes</h2>
            <?php if (empty($recentDetections)): ?>
              <div class="muted">Sin detecciones con contenido registrado.</div>
            <?php else: ?>
              <?php foreach ($recentDetections as $entry): ?>
                <details class="report-card">
                  <summary>
                    <?= htmlspecialchars($entry['hostname'], ENT_QUOTES, 'UTF-8'); ?>
                    <?php if ($entry['blocked']): ?>
                      <span class="badge badge-blocked">Bloqueado</span>
                    <?php endif; ?>
                    <span class="report-meta">
                      <?= htmlspecialchars($entry['timestamp'], ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                  </summary>
                  <?php if (!empty($entry['url'])): ?>
                    <div class="report-section">
                      <strong>URL</strong>
                      <div class="muted">
                        <a href="<?= htmlspecialchars($entry['url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">
                          <?= htmlspecialchars($entry['url'], ENT_QUOTES, 'UTF-8'); ?>
                        </a>
                      </div>
                    </div>
                  <?php endif; ?>
                  <?php if (!empty($entry['message'])): ?>
                    <div class="report-section">
                      <strong>Resumen</strong>
                      <div class="muted"><?= htmlspecialchars($entry['message'], ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>
                  <?php endif; ?>
                  <?php if (!empty($entry['signals'])): ?>
                    <div class="report-section">
                      <strong>Señales detectadas</strong>
                      <div class="chip-list">
                        <?php foreach ($entry['signals'] as $signalLabel): ?>
                          <span class="chip signal-chip"><?= htmlspecialchars($signalLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php endforeach; ?>
                      </div>
                    </div>
                  <?php endif; ?>
                  <?php if (!empty($entry['detected'])): ?>
                    <div class="report-section">
                      <strong>Contenido detectado</strong>
                      <pre><?= htmlspecialchars($entry['detected'], ENT_QUOTES, 'UTF-8'); ?></pre>
                    </div>
                  <?php endif; ?>
                  <?php if (!empty($entry['full_context'])): ?>
                    <div class="report-section context-panel">
                      <strong>Contexto completo</strong>
                      <pre><?= htmlspecialchars($entry['full_context'], ENT_QUOTES, 'UTF-8'); ?></pre>
                    </div>
                  <?php endif; ?>
                </details>
              <?php endforeach; ?>
            <?php endif; ?>
          </section>

          <section class="card" style="margin-top: 24px;">
            <h2>Desistimientos recientes</h2>
            <?php if (empty($appeals)): ?>
              <div class="muted">Sin solicitudes.</div>
            <?php else: ?>
              <?php foreach ($appeals as $appeal): ?>
                <details class="report-card">
                  <summary>
                    <?= htmlspecialchars((string) ($appeal['domain'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                    <span class="report-meta">
                      <?= htmlspecialchars((string) ($appeal['created_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                  </summary>
                  <div class="report-section">
                    <strong>Motivo</strong>
                    <div class="muted"><?= htmlspecialchars((string) ($appeal['reason'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                  </div>
                  <?php if (!empty($appeal['contact'])): ?>
                    <div class="report-section">
                      <strong>Contacto</strong>
                      <div class="muted"><?= htmlspecialchars((string) ($appeal['contact'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>
                  <?php endif; ?>
                  <div class="report-section">
                    <strong>Estado</strong>
                    <div class="muted"><?= htmlspecialchars((string) ($appeal['status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                  </div>
                  <?php if ($currentUser && ($currentUser['role'] ?? '') === 'admin' && ($appeal['status'] ?? '') !== 'resolved'): ?>
                    <form method="post" class="form-actions" style="margin-top: 12px;">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                      <input type="hidden" name="action" value="appeal_resolve" />
                      <input type="hidden" name="appeal_id" value="<?= (int) ($appeal['id'] ?? 0); ?>" />
                      <button class="button-secondary" type="submit">Marcar como resuelto</button>
                    </form>
                  <?php endif; ?>
                </details>
              <?php endforeach; ?>
            <?php endif; ?>
          </section>

          <section class="card" style="margin-top: 24px;">
            <h2>Logs recientes</h2>
            <div class="log-grid">
              <div class="log-entry">
                <div class="section-title">
                  <h3>clickfix-report.log</h3>
                  <span class="muted">Últimas entradas</span>
                </div>
                <?php if (empty($reportLogEntries)): ?>
                  <div class="muted">Sin registros.</div>
                <?php else: ?>
                  <?php foreach ($reportLogEntries as $logLine): ?>
                    <pre><?= htmlspecialchars($logLine, ENT_QUOTES, 'UTF-8'); ?></pre>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
              <div class="log-entry">
                <div class="section-title">
                  <h3>clickfix-debug.log</h3>
                  <span class="muted">Últimas entradas</span>
                </div>
                <?php if (empty($debugLogEntries)): ?>
                  <div class="muted">Sin registros.</div>
                <?php else: ?>
                  <?php foreach ($debugLogEntries as $logLine): ?>
                    <pre><?= htmlspecialchars($logLine, ENT_QUOTES, 'UTF-8'); ?></pre>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
            </div>
          </section>
        </div>

        <aside>
          <section class="card">
            <h2>Países</h2>
            <?php if (empty($stats['countries'])): ?>
              <div class="muted">Sin datos.</div>
            <?php else: ?>
              <div class="list-block">
                <ul>
                  <?php foreach ($stats['countries'] as $country => $count): ?>
                    <li><?= htmlspecialchars($country, ENT_QUOTES, 'UTF-8'); ?>: <?= (int) $count; ?></li>
                  <?php endforeach; ?>
                </ul>
              </div>
            <?php endif; ?>
          </section>

          <section class="card" style="margin-top: 20px;">
            <h2>Sitios manuales</h2>
            <?php if (empty($stats['manual_sites'])): ?>
              <div class="muted">Sin sitios.</div>
            <?php else: ?>
              <div class="chip-list">
                <?php foreach ($stats['manual_sites'] as $site): ?>
                  <span class="chip"><?= htmlspecialchars($site, ENT_QUOTES, 'UTF-8'); ?></span>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </section>

          <section class="card" style="margin-top: 20px;">
            <h2>Sitios alertados</h2>
            <?php if (empty($stats['alert_sites'])): ?>
              <div class="muted">Sin sitios.</div>
            <?php else: ?>
              <div class="list-block">
                <ul>
                  <?php foreach ($stats['alert_sites'] as $site): ?>
                    <li><?= htmlspecialchars($site, ENT_QUOTES, 'UTF-8'); ?></li>
                  <?php endforeach; ?>
                </ul>
              </div>
            <?php endif; ?>
          </section>
        </aside>
      </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
      const chartPayload = <?= json_encode($chartPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
      const createChart = (id, type, data, options = {}) => {
        const canvas = document.getElementById(id);
        if (!canvas) {
          return;
        }
        // eslint-disable-next-line no-new
        new Chart(canvas.getContext("2d"), {
          type,
          data,
          options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
              legend: { display: false }
            },
            ...options
          }
        });
      };

      createChart("chart-alerts-day", "line", {
        labels: chartPayload.daily.labels,
        datasets: [{
          label: "Alertas",
          data: chartPayload.daily.values,
          borderColor: "#2563eb",
          backgroundColor: "rgba(37, 99, 235, 0.2)",
          tension: 0.3,
          fill: true
        }]
      });

      createChart("chart-alerts-hour", "bar", {
        labels: chartPayload.hourly.labels,
        datasets: [{
          label: "Alertas",
          data: chartPayload.hourly.values,
          backgroundColor: "#f97316"
        }]
      }, {
        scales: { x: { ticks: { stepSize: 1 } } }
      });

      createChart("chart-alerts-country", "doughnut", {
        labels: chartPayload.countries.labels,
        datasets: [{
          data: chartPayload.countries.values,
          backgroundColor: ["#0ea5e9", "#22c55e", "#a855f7", "#facc15", "#ef4444", "#14b8a6", "#f97316"]
        }]
      });

      createChart("chart-alerts-signals", "bar", {
        labels: chartPayload.signals.labels,
        datasets: [{
          label: "Señales",
          data: chartPayload.signals.values,
          backgroundColor: "#6366f1"
        }]
      }, {
        indexAxis: "y"
      });
    </script>
  </body>
</html>
