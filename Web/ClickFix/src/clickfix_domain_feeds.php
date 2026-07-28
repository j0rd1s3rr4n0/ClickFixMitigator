<?php
declare(strict_types=1);

require_once __DIR__ . '/clickfix_core.php';

function clickfix_domain_feeds_sources(): array
{
    return [
        [
            'key' => 'github_gist',
            'label' => 'GitHub Gist (cdup07)',
            'url' => 'https://gist.githubusercontent.com/cdup07/9f563dfb78a06fad5db794f33ba93a3f/raw/b53ea737272f8e9b8ebe3405f5bd2ef0b3bff591/clickfix_domains.txt',
            'type' => 'text_list',
            'interval_hours' => 24,
        ],
        [
            'key' => 'carson_list',
            'label' => 'Carson ClickFix',
            'url' => 'https://clickfix.carsonww.com/domains?limit=50',
            'type' => 'paginated_html',
            'detail_url_template' => 'https://clickfix.carsonww.com/domains/{domain}',
            'interval_hours' => 6,
        ],
    ];
}

function clickfix_domain_feeds_ensure_table(PDO $pdo): void
{
    if (!clickfix_has_table($pdo, 'domain_feed_entries')) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS domain_feed_entries (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            source_key TEXT NOT NULL DEFAULT '',
            source_label TEXT NOT NULL DEFAULT '',
            domain TEXT NOT NULL DEFAULT '',
            url TEXT NOT NULL DEFAULT '',
            first_seen TEXT NOT NULL DEFAULT '',
            last_seen TEXT NOT NULL DEFAULT '',
            hits INTEGER NOT NULL DEFAULT 0,
            details_json TEXT NOT NULL DEFAULT '{}',
            fetched_at TEXT NOT NULL DEFAULT '',
            imported_to_blocklist INTEGER NOT NULL DEFAULT 0,
            UNIQUE(source_key, domain)
        )");
    }
    if (!clickfix_has_table($pdo, 'domain_feed_fetch_log')) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS domain_feed_fetch_log (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            source_key TEXT NOT NULL DEFAULT '',
            status TEXT NOT NULL DEFAULT 'ok',
            items_fetched INTEGER NOT NULL DEFAULT 0,
            items_new INTEGER NOT NULL DEFAULT 0,
            error TEXT NOT NULL DEFAULT '',
            fetched_at TEXT NOT NULL DEFAULT ''
        )");
    }
}

function clickfix_domain_feeds_http_get(string $url, array $headers = [], int $timeout = 30): ?string
{
    $headerMap = [];
    foreach ($headers as $h) {
        $parts = explode(':', $h, 2);
        if (count($parts) === 2) {
            $headerMap[trim($parts[0])] = trim($parts[1]);
        }
    }
    return clickfix_http_fetch($url, ['method' => 'GET', 'headers' => $headerMap, 'timeout' => min(15, $timeout)]);
}

function clickfix_domain_feeds_fetch_gist(PDO $pdo, array $source): array
{
    $url = (string) ($source['url'] ?? '');
    $key = (string) ($source['key'] ?? '');
    $label = (string) ($source['label'] ?? '');
    if ($url === '') {
        return ['ok' => false, 'error' => 'no_url', 'items' => 0, 'new' => 0];
    }
    $response = clickfix_domain_feeds_http_get($url, ['Accept: text/plain'], 30);
    if ($response === null) {
        clickfix_domain_feeds_log($pdo, $key, 'error', 0, 0, 'HTTP fetch failed');
        return ['ok' => false, 'error' => 'http_fetch_failed', 'items' => 0, 'new' => 0];
    }
    $lines = explode("\n", $response);
    $domains = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || str_starts_with($line, '//')) {
            continue;
        }
        $domain = clickfix_normalize_domain($line);
        if ($domain !== '') {
            $domains[$domain] = true;
        }
    }
    $total = count($domains);
    $new = 0;
    $now = gmdate('c');
    $stmt = $pdo->prepare('INSERT OR IGNORE INTO domain_feed_entries (source_key, source_label, domain, url, first_seen, last_seen, hits, details_json, fetched_at) VALUES (:sk, :sl, :d, :u, :fs, :ls, :h, :dj, :fa)');
    foreach ($domains as $domain => $_) {
        $stmt->execute([
            ':sk' => $key,
            ':sl' => $label,
            ':d' => $domain,
            ':u' => '',
            ':fs' => $now,
            ':ls' => $now,
            ':h' => 1,
            ':dj' => '{}',
            ':fa' => $now,
        ]);
        if ($stmt->rowCount() > 0) {
            $new++;
        }
    }
    $pdo->prepare('UPDATE domain_feed_entries SET last_seen = :ls, hits = hits + 1, fetched_at = :fa WHERE source_key = :sk AND domain = :d AND last_seen < :ls2')
        ->execute([':ls' => $now, ':fa' => $now, ':sk' => $key, ':d' => '', ':ls2' => $now]);
    foreach ($domains as $domain => $_) {
        $pdo->prepare('UPDATE domain_feed_entries SET last_seen = :ls, hits = hits + 1, fetched_at = :fa WHERE source_key = :sk AND domain = :d')
            ->execute([':ls' => $now, ':fa' => $now, ':sk' => $key, ':d' => $domain]);
    }
    clickfix_domain_feeds_log($pdo, $key, 'ok', $total, $new, '');
    return ['ok' => true, 'items' => $total, 'new' => $new];
}

function clickfix_domain_feeds_parse_carson_page(string $html): array
{
    $domains = [];
    $seen = [];
    $pattern = '#href=["\']\/domains\/([a-z0-9][a-z0-9._-]*\.[a-z]{2,63})["\']#i';
    if (preg_match_all($pattern, $html, $matches)) {
        foreach ((array) ($matches[1] ?? []) as $match) {
            $d = clickfix_normalize_domain((string) $match);
            if ($d !== '' && !isset($seen[$d])) {
                $seen[$d] = true;
                $domains[] = ['domain' => $d, 'first_seen' => '', 'last_seen' => '', 'hits' => 1];
            }
        }
    }
    if (empty($domains)) {
        $pattern2 = '#\b([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.(?:com|net|org|io|info|biz|xyz|top|shop|online|site|click|me|dev|app|ru|cn|tk|ml|ga|cf|gq))\b#i';
        if (preg_match_all($pattern2, $html, $matches2)) {
            foreach ((array) ($matches2[1] ?? []) as $match) {
                $d = clickfix_normalize_domain((string) $match);
                if ($d !== '' && !isset($seen[$d])) {
                    $seen[$d] = true;
                    $domains[] = ['domain' => $d, 'first_seen' => '', 'last_seen' => '', 'hits' => 1];
                }
            }
        }
    }
    return $domains;
}

function clickfix_domain_feeds_fetch_carson_detail(string $domain): ?array
{
    $url = 'https://clickfix.carsonww.com/domains/' . urlencode($domain);
    $response = clickfix_domain_feeds_http_get($url, ['Accept: text/html'], 15);
    if ($response === null) {
        return null;
    }
    $details = ['url' => $url];
    if (preg_match('#<title>(.*?)</title>#i', $response, $m)) {
        $details['page_title'] = trim(strip_tags($m[1]));
    }
    if (preg_match_all('#<img[^>]+src=["\']([^"\']*(?:screenshot|screen|capture|image)[^"\']*)["\']#i', $response, $imgMatches)) {
        $screenshots = [];
        foreach ($imgMatches[1] as $src) {
            if (!str_starts_with($src, 'http')) {
                $src = 'https://clickfix.carsonww.com' . (str_starts_with($src, '/') ? '' : '/') . $src;
            }
            $screenshots[] = $src;
        }
        if (!empty($screenshots)) { $details['screenshots'] = array_slice($screenshots, 0, 5); }
    }
    if (preg_match_all('#\d{4}-\d{2}-\d{2}#', $response, $dateMatches)) {
        $dates = array_unique($dateMatches[0]);
        sort($dates);
        if (!empty($dates)) {
            $details['first_seen'] = $dates[0];
            $details['last_seen'] = end($dates);
        }
    }
    $textContent = trim(strip_tags($response));
    $textContent = preg_replace('/\s+/', ' ', $textContent);
    $details['text_preview'] = substr($textContent, 0, 1500);
    return $details;
}

function clickfix_domain_feeds_fetch_carson_list(PDO $pdo, array $source): array
{
    $baseUrl = rtrim((string) ($source['url'] ?? ''), '/');
    $key = (string) ($source['key'] ?? '');
    $label = (string) ($source['label'] ?? '');
    if ($baseUrl === '') {
        return ['ok' => false, 'error' => 'no_url', 'items' => 0, 'new' => 0];
    }
    $allDomains = [];
    $maxPages = 10;
    $fetchedPages = 0;
    for ($page = 1; $page <= $maxPages; $page++) {
        $pageUrl = $baseUrl . (str_contains($baseUrl, '?') ? '&' : '?') . 'page=' . $page;
        $response = clickfix_domain_feeds_http_get($pageUrl, ['Accept: text/html'], 12);
        if ($response === null) {
            if ($page === 1) {
                clickfix_domain_feeds_log($pdo, $key, 'error', 0, 0, 'Carson HTTP fetch failed');
                return ['ok' => false, 'error' => 'http_fetch_failed', 'items' => 0, 'new' => 0];
            }
            break;
        }
        $domains = clickfix_domain_feeds_parse_carson_page($response);
        if (empty($domains)) {
            if ($page === 1) {
                clickfix_domain_feeds_log($pdo, $key, 'error', 0, 0, 'No domains parsed from Carson');
                return ['ok' => false, 'error' => 'no_domains_parsed', 'items' => 0, 'new' => 0];
            }
            break;
        }
        $newOnPage = 0;
        foreach ($domains as $d) {
            $domain = $d['domain'];
            if (!isset($allDomains[$domain])) { $allDomains[$domain] = $d; $newOnPage++; }
        }
        if ($newOnPage === 0) break;
        $fetchedPages++;
    }
    $total = count($allDomains);
    $new = 0;
    $now = gmdate('c');
    $stmt = $pdo->prepare('INSERT OR IGNORE INTO domain_feed_entries (source_key, source_label, domain, url, first_seen, last_seen, hits, details_json, fetched_at) VALUES (:sk, :sl, :d, :u, :fs, :ls, :h, :dj, :fa)');
    foreach ($allDomains as $domain => $info) {
        $detailUrl = str_replace('{domain}', urlencode($domain), (string) ($source['detail_url_template'] ?? ''));
        $stmt->execute([':sk' => $key, ':sl' => $label, ':d' => $domain, ':u' => $detailUrl, ':fs' => $info['first_seen'] !== '' ? $info['first_seen'] : $now, ':ls' => $info['last_seen'] !== '' ? $info['last_seen'] : $now, ':h' => max(1, (int) ($info['hits'] ?? 1)), ':dj' => '{}', ':fa' => $now]);
        if ($stmt->rowCount() > 0) { $new++; }
    }
    foreach ($allDomains as $domain => $info) {
        $pdo->prepare('UPDATE domain_feed_entries SET last_seen = :ls, hits = hits + 1, fetched_at = :fa WHERE source_key = :sk AND domain = :d')->execute([':ls' => $now, ':fa' => $now, ':sk' => $key, ':d' => $domain]);
    }
    clickfix_domain_feeds_log($pdo, $key, 'ok', $total, $new, "pages: {$fetchedPages}");
    return ['ok' => true, 'items' => $total, 'new' => $new, 'pages' => $fetchedPages];
}

function clickfix_domain_feeds_fetch_all(PDO $pdo): array
{
    clickfix_domain_feeds_ensure_table($pdo);
    $results = [];
    foreach (clickfix_domain_feeds_sources() as $source) {
        $type = (string) ($source['type'] ?? '');
        if ($type === 'text_list') {
            $results[] = array_merge(['source' => $source['label']], clickfix_domain_feeds_fetch_gist($pdo, $source));
        } elseif ($type === 'paginated_html') {
            $results[] = array_merge(['source' => $source['label']], clickfix_domain_feeds_fetch_carson_list($pdo, $source));
        }
    }
    return $results;
}

function clickfix_domain_feeds_get_entries(PDO $pdo, int $limit = 200, string $sourceKey = '', string $search = ''): array
{
    if (!clickfix_has_table($pdo, 'domain_feed_entries')) {
        return [];
    }
    $sql = 'SELECT * FROM domain_feed_entries WHERE 1=1';
    $params = [];
    if ($sourceKey !== '') {
        $sql .= ' AND source_key = :sk';
        $params[':sk'] = $sourceKey;
    }
    if ($search !== '') {
        $sql .= ' AND domain LIKE :q';
        $params[':q'] = '%' . $search . '%';
    }
    $sql .= ' ORDER BY last_seen DESC, id DESC LIMIT :limit';
    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v, PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', max(1, min(10000, $limit)), PDO::PARAM_INT);
    $stmt->execute();
    $entries = [];
    while ($row = $stmt->fetch()) {
        $details = json_decode((string) ($row['details_json'] ?? '{}'), true) ?: [];
        $entries[] = [
            'id' => (int) ($row['id'] ?? 0),
            'source_key' => (string) ($row['source_key'] ?? ''),
            'source_label' => (string) ($row['source_label'] ?? ''),
            'domain' => (string) ($row['domain'] ?? ''),
            'url' => (string) ($row['url'] ?? ''),
            'first_seen' => (string) ($row['first_seen'] ?? ''),
            'last_seen' => (string) ($row['last_seen'] ?? ''),
            'hits' => (int) ($row['hits'] ?? 0),
            'details' => is_array($details) ? $details : [],
            'fetched_at' => (string) ($row['fetched_at'] ?? ''),
            'imported_to_blocklist' => !empty($row['imported_to_blocklist']),
        ];
    }
    return $entries;
}

function clickfix_domain_feeds_get_stats(PDO $pdo): array
{
    if (!clickfix_has_table($pdo, 'domain_feed_entries')) {
        return ['total' => 0, 'by_source' => [], 'imported' => 0, 'not_imported' => 0];
    }
    $total = (int) ($pdo->query('SELECT COUNT(*) FROM domain_feed_entries')->fetchColumn() ?: 0);
    $imported = (int) ($pdo->query("SELECT COUNT(*) FROM domain_feed_entries WHERE imported_to_blocklist = 1")->fetchColumn() ?: 0);
    $bySource = $pdo->query('SELECT source_key, source_label, COUNT(*) as cnt FROM domain_feed_entries GROUP BY source_key, source_label')->fetchAll();
    return ['total' => $total, 'imported' => $imported, 'not_imported' => $total - $imported, 'by_source' => $bySource];
}

function clickfix_domain_feeds_import_to_blocklist(PDO $pdo, int $entryId, int $actorId = 0): bool
{
    if (!clickfix_has_table($pdo, 'domain_feed_entries')) {
        return false;
    }
    $stmt = $pdo->prepare('SELECT domain FROM domain_feed_entries WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $entryId]);
    $domain = $stmt->fetchColumn();
    if (!is_string($domain) || $domain === '') {
        return false;
    }
    $ok = clickfix_apply_list_action($pdo, $actorId, 'blocklist', 'add', $domain, 'imported from domain feed #' . $entryId);
    if ($ok) {
        $pdo->prepare('UPDATE domain_feed_entries SET imported_to_blocklist = 1 WHERE id = :id')->execute([':id' => $entryId]);
    }
    return $ok;
}

function clickfix_domain_feeds_import_all_new(PDO $pdo, int $actorId = 0, string $sourceKey = ''): array
{
    if (!clickfix_has_table($pdo, 'domain_feed_entries')) {
        return ['ok' => false, 'error' => 'no_table', 'imported' => 0];
    }
    $sql = "SELECT id, domain FROM domain_feed_entries WHERE imported_to_blocklist = 0";
    $params = [];
    if ($sourceKey !== '') {
        $sql .= ' AND source_key = :sk';
        $params[':sk'] = $sourceKey;
    }
    $sql .= ' ORDER BY id ASC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $imported = 0;
    while ($row = $stmt->fetch()) {
        $entryId = (int) ($row['id'] ?? 0);
        $domain = (string) ($row['domain'] ?? '');
        if ($domain === '') {
            continue;
        }
        if (clickfix_apply_list_action($pdo, $actorId, 'blocklist', 'add', $domain, 'bulk import from domain feed')) {
            $pdo->prepare('UPDATE domain_feed_entries SET imported_to_blocklist = 1 WHERE id = :id')->execute([':id' => $entryId]);
            $imported++;
        }
    }
    return ['ok' => true, 'imported' => $imported];
}

function clickfix_domain_feeds_log(PDO $pdo, string $sourceKey, string $status, int $items, int $new, string $error = ''): void
{
    if (!clickfix_has_table($pdo, 'domain_feed_fetch_log')) {
        return;
    }
    $stmt = $pdo->prepare('INSERT INTO domain_feed_fetch_log (source_key, status, items_fetched, items_new, error, fetched_at) VALUES (:sk, :s, :i, :n, :e, :at)');
    $stmt->execute([':sk' => $sourceKey, ':s' => $status, ':i' => $items, ':n' => $new, ':e' => substr($error, 0, 500), ':at' => gmdate('c')]);
}

function clickfix_domain_feeds_log_recent(PDO $pdo, int $limit = 10): array
{
    if (!clickfix_has_table($pdo, 'domain_feed_fetch_log')) {
        return [];
    }
    $stmt = $pdo->prepare('SELECT * FROM domain_feed_fetch_log ORDER BY id DESC LIMIT :limit');
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function clickfix_domain_feeds_fetch_carson_detail_for_recent(PDO $pdo, int $limit = 5, string $sourceKey = 'carson_list'): array
{
    if (!clickfix_has_table($pdo, 'domain_feed_entries')) {
        return [];
    }
    $stmt = $pdo->prepare("SELECT id, domain FROM domain_feed_entries WHERE source_key = :sk AND (details_json = '{}' OR details_json IS NULL) ORDER BY id DESC LIMIT :lim");
    $stmt->execute([':sk' => $sourceKey, ':lim' => $limit]);
    $results = [];
    while ($row = $stmt->fetch()) {
        $domain = (string) ($row['domain'] ?? '');
        $entryId = (int) ($row['id'] ?? 0);
        if ($domain === '') {
            continue;
        }
        $details = clickfix_domain_feeds_fetch_carson_detail($domain);
        if ($details !== null) {
            $pdo->prepare('UPDATE domain_feed_entries SET details_json = :dj WHERE id = :id')->execute([':dj' => json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ':id' => $entryId]);
            $results[] = ['domain' => $domain, 'entry_id' => $entryId, 'details' => $details];
        }
    }
    return $results;
}
