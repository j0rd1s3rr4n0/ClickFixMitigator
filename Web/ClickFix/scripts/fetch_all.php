<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/clickfix_core.php';
require_once __DIR__ . '/../src/clickfix_domain_feeds.php';
require_once __DIR__ . '/../src/clickfix_socdefenders.php';
require_once __DIR__ . '/../src/clickfix_abusech.php';

echo "=== ClickFix Force Fetch All ===\n";
echo "Time: " . gmdate('c') . "\n\n";

$pdo = clickfix_open_db(true);
clickfix_domain_feeds_ensure_table($pdo);

echo "[1/3] GitHub Gist... ";
try {
    $gistResult = clickfix_domain_feeds_fetch_gist($pdo, clickfix_domain_feeds_sources()[0]);
    echo $gistResult['ok'] ? "OK ({$gistResult['items']} domains, {$gistResult['new']} new)\n" : "FAILED: {$gistResult['error']}\n";
} catch (Throwable $e) { echo "ERROR: {$e->getMessage()}\n"; }

echo "      Carson... ";
try {
    $carsonResult = clickfix_domain_feeds_fetch_carson_list($pdo, clickfix_domain_feeds_sources()[1]);
    echo $carsonResult['ok'] ? "OK ({$carsonResult['items']} domains, {$carsonResult['new']} new)\n" : "FAILED: {$carsonResult['error']}\n";
} catch (Throwable $e) { echo "ERROR: {$e->getMessage()}\n"; }

echo "\n[2/3] abuse.ch (URLHaus tags: ClickFix, ClearFake, commandline)...\n";
try {
    foreach (['ClickFix', 'ClearFake'] as $tag) {
        $r = clickfix_abusech_urlhaus_by_tag($tag, 100);
        echo "  URLHaus tag '{$tag}': " . ($r['ok'] ? "OK ({$r['count']} urls)" : "FAILED: {$r['error']}") . "\n";
    }
    $abuseResult = clickfix_abusech_fetch_clickfix_tags($pdo);
    echo "  Imported to DB: {$abuseResult['imported']} new domains\n";
} catch (Throwable $e) { echo "  ERROR: {$e->getMessage()}\n"; }

echo "\n[3/3] SOC Defenders API (ClickFix IOCs)...\n";
try {
    $apiKey = clickfix_socdefenders_api_key();
    if ($apiKey === '') {
        echo "  SKIPPED: No SOC Defenders API key configured (set CLICKFIX_SOCDEFENDERS_API_KEY in .env.security)\n";
    } else {
        $sdResult = clickfix_socdefenders_fetch_clickfix_iocs($pdo);
        echo "  Found: {$sdResult['domains_found']}, Imported: {$sdResult['imported']}\n";
        if (!$sdResult['ok']) echo "  Error: {$sdResult['error']}\n";
    }
} catch (Throwable $e) { echo "  ERROR: {$e->getMessage()}\n"; }

echo "\n=== Done ===\n";
$total = $pdo->query('SELECT COUNT(*) FROM domain_feed_entries')->fetchColumn() ?: 0;
echo "Total domains across all sources: {$total}\n";
echo "View: https://clickfix.jordiserrano.me/dashboard.php?page=clickfix_domain_list&public=1\n";
echo "Import to blocklist: https://clickfix.jordiserrano.me/dashboard.php?page=domain_feeds\n";
