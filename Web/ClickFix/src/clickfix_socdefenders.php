<?php
declare(strict_types=1);

require_once __DIR__ . '/clickfix_core.php';

function clickfix_socdefenders_api_key(): string
{
    return trim((string) clickfix_env('CLICKFIX_SOCDEFENDERS_API_KEY', ''));
}

function clickfix_socdefenders_has_key(): bool
{
    return clickfix_socdefenders_api_key() !== '';
}

function clickfix_socdefenders_request(string $path, array $params = [], int $timeout = 25): ?array
{
    $apiKey = clickfix_socdefenders_api_key();
    if ($apiKey === '') {
        return null;
    }
    $url = 'https://socdefenders.ai/api/v1/' . ltrim($path, '/');
    if (!empty($params)) {
        $url .= '?' . http_build_query($params);
    }
    return clickfix_http_fetch_json($url, [
        'method' => 'GET',
        'headers' => ['Authorization' => 'Bearer ' . $apiKey],
        'timeout' => $timeout,
    ]);
}

function clickfix_socdefenders_fetch_articles(string $query, int $limit = 30): array
{
    $params = ['q' => $query, 'limit' => min(100, max(1, $limit)), 'has_iocs' => 'true'];
    $result = clickfix_socdefenders_request('articles', $params);
    if (!is_array($result) || !isset($result['data'])) {
        return ['ok' => false, 'error' => 'api_error', 'articles' => [], 'iocs' => []];
    }
    $articles = [];
    $allIocs = [];
    foreach (($result['data'] ?? []) as $art) {
        $articleId = (string) ($art['id'] ?? '');
        $articles[] = [
            'id' => $articleId,
            'title' => (string) ($art['title'] ?? ''),
            'url' => (string) ($art['url'] ?? ''),
            'source' => (string) ($art['source'] ?? ''),
            'published_at' => (string) ($art['published_at'] ?? ''),
            'categories' => is_array($art['categories'] ?? null) ? $art['categories'] : [],
            'severity' => (string) ($art['severity'] ?? ''),
            'has_iocs' => !empty($art['has_iocs']),
        ];
        if (!empty($art['has_iocs']) && $articleId !== '') {
            $detail = clickfix_socdefenders_request('articles/' . urlencode($articleId));
            if (is_array($detail) && isset($detail['iocs'])) {
                foreach (($detail['iocs'] ?? []) as $ioc) {
                    $iocType = (string) ($ioc['type'] ?? '');
                    $iocValue = (string) ($ioc['value'] ?? '');
                    if ($iocType === 'domain' && $iocValue !== '') {
                        $allIocs[] = [
                            'type' => $iocType,
                            'value' => clickfix_normalize_domain($iocValue),
                            'confidence' => (string) ($ioc['confidence'] ?? 'medium'),
                            'source' => 'socdefenders_article',
                            'article_id' => $articleId,
                            'article_title' => (string) ($art['title'] ?? ''),
                        ];
                    }
                }
            }
        }
    }
    return ['ok' => true, 'articles' => $articles, 'iocs' => $allIocs, 'total' => (int) ($result['meta']['total'] ?? 0)];
}

function clickfix_socdefenders_fetch_iocs(string $type = '', int $limit = 100, int $offset = 0, string $since = ''): array
{
    $params = ['limit' => min(100, max(1, $limit)), 'offset' => max(0, $offset)];
    if ($type !== '') {
        $params['type'] = $type;
    }
    if ($since !== '') {
        $params['since'] = $since;
    }
    $result = clickfix_socdefenders_request('iocs', $params);
    if (!is_array($result) || !isset($result['data'])) {
        return ['ok' => false, 'error' => 'api_error', 'iocs' => [], 'total' => 0];
    }
    $iocs = [];
    foreach (($result['data'] ?? []) as $ioc) {
        $iocType = (string) ($ioc['type'] ?? '');
        $iocValue = (string) ($ioc['value'] ?? '');
        if ($iocValue === '') {
            continue;
        }
        $iocs[] = [
            'type' => $iocType,
            'value' => $iocType === 'domain' ? clickfix_normalize_domain($iocValue) : $iocValue,
            'confidence' => (string) ($ioc['confidence'] ?? 'medium'),
            'source_feed' => (string) ($ioc['source']['feed_name'] ?? 'SOC Defenders'),
            'source_category' => (string) ($ioc['source']['category'] ?? ''),
        ];
    }
    return ['ok' => true, 'iocs' => $iocs, 'total' => (int) ($result['meta']['total'] ?? 0)];
}

function clickfix_socdefenders_fetch_clickfix_iocs(PDO $pdo): array
{
    if (!clickfix_socdefenders_has_key()) {
        return ['ok' => false, 'error' => 'no_api_key', 'imported' => 0];
    }
    $imported = 0;
    $domains = [];
    $result = clickfix_socdefenders_fetch_iocs('domain', 100, 0);
    if ($result['ok']) {
        foreach ($result['iocs'] as $ioc) {
            $domain = (string) ($ioc['value'] ?? '');
            if ($domain !== '' && !isset($domains[$domain])) {
                $domains[$domain] = $ioc;
            }
        }
        $total = $result['total'];
        $pages = (int) ceil($total / 100);
        for ($p = 1; $p < min($pages, 5); $p++) {
            $pageResult = clickfix_socdefenders_fetch_iocs('domain', 100, $p * 100);
            if ($pageResult['ok']) {
                foreach ($pageResult['iocs'] as $ioc) {
                    $domain = (string) ($ioc['value'] ?? '');
                    if ($domain !== '' && !isset($domains[$domain])) {
                        $domains[$domain] = $ioc;
                    }
                }
            }
        }
    }
    $clickfixArticles = clickfix_socdefenders_fetch_articles('ClickFix', 30);
    if ($clickfixArticles['ok']) {
        foreach ($clickfixArticles['iocs'] as $ioc) {
            $domain = (string) ($ioc['value'] ?? '');
            if ($domain !== '' && !isset($domains[$domain])) {
                $domains[$domain] = $ioc;
            }
        }
    }
    if (clickfix_has_table($pdo, 'domain_feed_entries')) {
        $now = gmdate('c');
        $stmt = $pdo->prepare('INSERT OR IGNORE INTO domain_feed_entries (source_key, source_label, domain, url, first_seen, last_seen, hits, details_json, fetched_at) VALUES (:sk, :sl, :d, :u, :fs, :ls, :h, :dj, :fa)');
        foreach ($domains as $domain => $info) {
            $details = ['confidence' => $info['confidence'] ?? 'medium', 'source_feed' => $info['source_feed'] ?? '', 'source_category' => $info['source_category'] ?? ''];
            $stmt->execute([
                ':sk' => 'socdefenders',
                ':sl' => 'SOC Defenders',
                ':d' => $domain,
                ':u' => 'https://www.socdefenders.ai/?search=' . urlencode($domain),
                ':fs' => $now, ':ls' => $now, ':h' => 1,
                ':dj' => json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ':fa' => $now,
            ]);
            if ($stmt->rowCount() > 0) {
                $imported++;
            }
        }
    }
    return ['ok' => true, 'domains_found' => count($domains), 'imported' => $imported];
}
