<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/clickfix_core.php';
require_once __DIR__ . '/../src/clickfix_auto_investigation.php';
require_once __DIR__ . '/../src/clickfix_blog_feed.php';
require_once __DIR__ . '/../src/clickfix_domain_feeds.php';
require_once __DIR__ . '/../src/clickfix_socdefenders.php';
require_once __DIR__ . '/../src/clickfix_abusech.php';

$pdo = clickfix_open_db(true);
clickfix_llm_ensure_table($pdo);
clickfix_domain_feeds_ensure_table($pdo);

$mode = strtolower(trim((string) ($argv[1] ?? 'auto')));

if ($mode === 'auto' || $mode === 'all') {
    echo "[auto_inv] Starting auto-investigation batch...\n";
    $result = clickfix_auto_investigation_worker_batch($pdo);
    echo "[auto_inv] Done. New alerts: {$result['new_alerts']}, Enqueued: {$result['jobs_enqueued']}, Processed: {$result['jobs_processed']}\n";
}

if ($mode === 'domains' || $mode === 'all') {
    echo "[domains] Fetching ClickFix domain feeds (Gist + Carson)...\n";
    $results = clickfix_domain_feeds_fetch_all($pdo);
    foreach ($results as $r) {
        echo "[domains] {$r['source']}: " . ($r['ok'] ? "OK ({$r['items']} items, {$r['new']} new)" : "ERROR: {$r['error']}") . "\n";
    }
    echo "[domains] Fetching Carson detail pages...\n";
    $details = clickfix_domain_feeds_fetch_carson_detail_for_recent($pdo, 5);
    echo "[domains] Details fetched: " . count($details) . "\n";
    echo "[abusech] Fetching abuse.ch ClickFix/ClearFake/commandline tags...\n";
    $abuseResult = clickfix_abusech_fetch_clickfix_tags($pdo);
    echo "[abusech] Done. Found: {$abuseResult['domains_found']}, Imported: {$abuseResult['imported']}\n";
    echo "[socdefenders] Fetching SOC Defenders ClickFix IOCs...\n";
    $sdResult = clickfix_socdefenders_fetch_clickfix_iocs($pdo);
    echo "[socdefenders] Done. Found: {$sdResult['domains_found']}, Imported: {$sdResult['imported']}\n";
}

if ($mode === 'blog' || $mode === 'all') {
    echo "[blog] Refreshing blog feeds...\n";
    $results = clickfix_blog_feed_refresh($pdo);
    $cleaned = clickfix_blog_feed_cache_cleanup($pdo);
    foreach ($results as $r) {
        echo "[blog] {$r['source']}: " . ($r['ok'] ? "OK ({$r['items']} items)" : "FAILED") . "\n";
    }
    echo "[blog] Cleaned {$cleaned} expired entries.\n";
}

if ($mode === 'seo') {
    require_once __DIR__ . '/../src/clickfix_seo.php';
    $sitemap = clickfix_seo_generate_sitemap_xml($pdo);
    $sitemapPath = __DIR__ . '/../sitemap.xml';
    file_put_contents($sitemapPath, $sitemap);
    echo "[seo] Sitemap generated (" . strlen($sitemap) . " bytes)\n";
}

if (!in_array($mode, ['auto', 'domains', 'blog', 'seo', 'all'], true)) {
    echo "Usage: php worker.php [auto|domains|blog|seo|all]\n";
}

if ($mode === 'domains' || $mode === 'all') {
    echo "[domains] Fetching ClickFix domain feeds...\n";
    $results = clickfix_domain_feeds_fetch_all($pdo);
    foreach ($results as $r) {
        echo "[domains] {$r['source']}: " . ($r['ok'] ? "OK ({$r['items']} items, {$r['new']} new)" : "ERROR: {$r['error']}") . "\n";
    }
    echo "[domains] Fetching Carson detail pages for recent entries...\n";
    $details = clickfix_domain_feeds_fetch_carson_detail_for_recent($pdo, 5);
    echo "[domains] Details fetched: " . count($details) . "\n";
}

if ($mode === 'blog' || $mode === 'all') {
    echo "[blog] Refreshing blog feeds...\n";
    $results = clickfix_blog_feed_refresh($pdo);
    $cleaned = clickfix_blog_feed_cache_cleanup($pdo);
    foreach ($results as $r) {
        echo "[blog] {$r['source']}: " . ($r['ok'] ? "OK ({$r['items']} items)" : "FAILED") . "\n";
    }
    echo "[blog] Cleaned {$cleaned} expired entries.\n";
}

if ($mode === 'seo') {
    require_once __DIR__ . '/../src/clickfix_seo.php';
    $sitemap = clickfix_seo_generate_sitemap_xml($pdo);
    $sitemapPath = __DIR__ . '/../sitemap.xml';
    file_put_contents($sitemapPath, $sitemap);
    echo "[seo] Sitemap generated at {$sitemapPath} (" . strlen($sitemap) . " bytes)\n";
}

if (!in_array($mode, ['auto', 'domains', 'blog', 'seo', 'all'], true)) {
    echo "Usage: php worker.php [auto|domains|blog|seo|all]\n";
    echo "  auto    - Run auto-investigation batch\n";
    echo "  domains - Fetch ClickFix domain feeds (Gist + Carson)\n";
    echo "  blog    - Refresh blog feeds from configured sources\n";
    echo "  seo     - Regenerate sitemap.xml with investigations\n";
    echo "  all     - Run all tasks\n";
}
echo "[worker] Complete.\n";
