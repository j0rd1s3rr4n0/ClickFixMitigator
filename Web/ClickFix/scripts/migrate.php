<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must run in CLI mode.\n");
    exit(1);
}

require_once dirname(__DIR__) . '/src/clickfix_core.php';

$options = getopt('', ['source::', 'target::', 'no-backup', 'dry-run']);
$dryRun = array_key_exists('dry-run', $options);
$noBackup = array_key_exists('no-backup', $options);

$baseDir = dirname(__DIR__);
$defaultTarget = $baseDir . '/data/clickfix.sqlite';
$source = (string) ($options['source'] ?? clickfix_resolve_db_path());
$target = (string) ($options['target'] ?? $defaultTarget);

$source = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $source);
$target = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $target);

$targetDir = dirname($target);
if (!is_dir($targetDir)) {
    if ($dryRun) {
        echo "[dry-run] create directory: {$targetDir}\n";
    } else {
        @mkdir($targetDir, 0775, true);
    }
}

$sourceExists = is_file($source) && filesize($source) > 0;
$targetExists = is_file($target) && filesize($target) > 0;

echo "Source DB: {$source}" . ($sourceExists ? " (ok)\n" : " (missing/empty)\n");
echo "Target DB: {$target}" . ($targetExists ? " (ok)\n" : " (missing/empty)\n");

if (!$targetExists && $sourceExists && realpath($source) !== realpath($target)) {
    if ($dryRun) {
        echo "[dry-run] copy source to target\n";
    } else {
        if (!@copy($source, $target)) {
            fwrite(STDERR, "[error] failed to copy source DB to target.\n");
            exit(1);
        }
        echo "[ok] copied source DB to target\n";
    }
}

if (!$noBackup && is_file($target) && filesize($target) > 0) {
    $backupDir = $baseDir . '/data/backups';
    $backupFile = $backupDir . '/clickfix-' . gmdate('Ymd-His') . '.sqlite';
    if ($dryRun) {
        echo "[dry-run] backup target to {$backupFile}\n";
    } else {
        if (!is_dir($backupDir)) {
            @mkdir($backupDir, 0775, true);
        }
        if (@copy($target, $backupFile)) {
            echo "[ok] backup created: {$backupFile}\n";
        } else {
            fwrite(STDERR, "[warn] could not create backup file.\n");
        }
    }
}

if ($dryRun) {
    echo "[dry-run] migration finished without applying changes.\n";
    exit(0);
}

try {
    $pdo = new PDO('sqlite:' . $target);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    clickfix_run_migrations($pdo);
    echo "[ok] schema migrations applied.\n";

    $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
    echo "[ok] tables: " . implode(', ', $tables) . "\n";

    $counts = [
        'reports',
        'stats',
        'users',
        'appeals',
        'list_actions',
        'list_suggestions',
        'access_requests',
        'investigation_graphs',
        'api_clients',
        'api_user_keys',
        'api_refresh_tokens',
        'api_rate_limits',
        'extension_messages',
        'report_schedules',
        'user_extension_links',
        'investigation_events',
        'geo_country_cache',
        'domain_intel_cache',
        'whatweb_cache',
        'ml_keyword_enrichment_cache',
        'scan_image_reviews',
        'investigation_votes',
        'user_reputation_events',
    ];
    foreach ($counts as $table) {
        $exists = $pdo->prepare("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name = :name");
        $exists->execute([':name' => $table]);
        if ((int) $exists->fetchColumn() !== 1) {
            continue;
        }
        $total = (int) $pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
        echo "  - {$table}: {$total}\n";
    }
} catch (Throwable $exception) {
    fwrite(STDERR, "[error] migration failed: {$exception->getMessage()}\n");
    exit(1);
}

echo "[done] migration complete.\n";
