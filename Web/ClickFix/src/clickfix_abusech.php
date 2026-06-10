<?php
declare(strict_types=1);

require_once __DIR__ . '/clickfix_core.php';

function clickfix_abusech_urlhaus_by_tag(string $tag, int $limit = 200): array
{
    $url = 'https://urlhaus-api.abuse.ch/v1/tag/';
    $body = 'tag=' . urlencode($tag);
    $response = clickfix_http_fetch($url, ['method' => 'POST', 'body' => $body, 'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'], 'timeout' => 20]);
    if ($response === null) {
        return ['ok' => false, 'error' => 'http_failed', 'domains' => []];
    }
    $data = json_decode($response, true);
    if (!is_array($data) || (($data['query_status'] ?? '') !== 'ok' && ($data['query_status'] ?? '') !== 'no_results')) {
        $errDetail = is_array($data) ? (json_encode($data) ?: 'invalid_json') : substr($response ?? '', 0, 200);
        return ['ok' => false, 'error' => ($data['query_status'] ?? 'api_error') . ' | ' . $errDetail, 'domains' => []];
    }
    $response = clickfix_http_fetch($url, ['method' => 'POST', 'body' => $payload, 'headers' => ['Content-Type' => 'application/json'], 'timeout' => 20]);
    if ($response === null) {
        return ['ok' => false, 'error' => 'http_failed', 'domains' => []];
    }
    $data = json_decode($response, true);
    if (!is_array($data) || (($data['query_status'] ?? '') !== 'ok' && ($data['query_status'] ?? '') !== 'no_results')) {
        $errDetail = is_array($data) ? (json_encode($data) ?: 'invalid_json') : substr($response ?? '', 0, 300);
        return ['ok' => false, 'error' => ($data['query_status'] ?? 'api_error') . ' | ' . $errDetail, 'domains' => []];
    }
    $domains = []; $seen = [];
    $urls = is_array($data['urls'] ?? null) ? $data['urls'] : [];
    foreach ($urls as $entry) {
        $entryUrl = (string) ($entry['url'] ?? '');
        $entryHost = (string) ($entry['host'] ?? '');
        if ($entryUrl !== '') { $parsedHost = parse_url($entryUrl, PHP_URL_HOST); if (is_string($parsedHost) && $parsedHost !== '') { $entryHost = $parsedHost; } }
        $domain = clickfix_normalize_domain($entryHost);
        if ($domain !== '' && !isset($seen[$domain])) { $seen[$domain] = true; $domains[] = ['domain' => $domain, 'url' => $entryUrl !== '' ? $entryUrl : 'https://' . $domain, 'threat' => (string) ($entry['threat'] ?? ''), 'tags' => is_array($entry['tags'] ?? null) ? $entry['tags'] : [$tag], 'date_added' => (string) ($entry['date_added'] ?? $entry['dateadded'] ?? ''), 'url_status' => (string) ($entry['url_status'] ?? '')]; }
    }
    return ['ok' => true, 'domains' => $domains, 'count' => count($domains)];
}

function clickfix_abusech_urlhaus_recent(int $limit = 100): array
{
    $url = 'https://urlhaus-api.abuse.ch/v1/urls/recent/limit/' . max(1, min(1000, $limit)) . '/';
    $response = clickfix_http_fetch($url, ['method' => 'GET', 'timeout' => 20]);
    if ($response === null) { return ['ok' => false, 'error' => 'http_failed', 'domains' => []]; }
    $data = json_decode($response, true);
    if (!is_array($data) || ($data['query_status'] ?? '') !== 'ok') { return ['ok' => false, 'error' => $data['query_status'] ?? 'api_error', 'domains' => []]; }
    $domains = []; $seen = [];
    foreach (($data['urls'] ?? []) as $entry) {
        $entryUrl = (string) ($entry['url'] ?? '');
        $host = parse_url($entryUrl, PHP_URL_HOST);
        $domain = clickfix_normalize_domain(is_string($host) ? $host : (string) ($entry['host'] ?? ''));
        if ($domain !== '' && !isset($seen[$domain])) { $seen[$domain] = true; $domains[] = ['domain' => $domain, 'url' => $entryUrl, 'threat' => (string) ($entry['threat'] ?? ''), 'tags' => is_array($entry['tags'] ?? null) ? $entry['tags'] : [], 'date_added' => (string) ($entry['date_added'] ?? $entry['dateadded'] ?? '')]; }
    }
    return ['ok' => true, 'domains' => $domains, 'count' => count($domains)];
}

function clickfix_abusech_threatfox_query(string $queryName, array $queryParams = [], int $limit = 200): array
{
    $url = 'https://threatfox-api.abuse.ch/api/v1/';
    $payload = json_encode(array_merge(['query' => $queryName], $queryParams, ['limit' => min(500, max(1, $limit))]));
    if ($payload === false) { return ['ok' => false, 'error' => 'json_encode_failed', 'domains' => []]; }
    $headers = ['Content-Type' => 'application/json'];
    $tfKey = trim((string) clickfix_env('CLICKFIX_THREATFOX_API_KEY', ''));
    if ($tfKey !== '') { $headers['Auth-Key'] = $tfKey; }
    $response = clickfix_http_fetch($url, ['method' => 'POST', 'body' => $payload, 'headers' => $headers, 'timeout' => 20]);
    if ($response === null) { return ['ok' => false, 'error' => 'http_failed', 'domains' => []]; }
    $data = json_decode($response, true);
    if (!is_array($data) || ($data['query_status'] ?? '') !== 'ok') { return ['ok' => false, 'error' => $data['query_status'] ?? 'api_error', 'domains' => []]; }
    $domains = []; $seen = [];
    foreach (($data['data'] ?? []) as $entry) {
        $iocType = strtolower((string) ($entry['ioc_type'] ?? ''));
        $iocValue = (string) ($entry['ioc'] ?? '');
        $domain = '';
        if ($iocType === 'domain') { $domain = clickfix_normalize_domain($iocValue); }
        elseif ($iocType === 'url') { $host = parse_url($iocValue, PHP_URL_HOST); $domain = clickfix_normalize_domain(is_string($host) ? $host : $iocValue); }
        elseif ($iocType === 'ip:port') { $parts = explode(':', $iocValue); $domain = clickfix_normalize_domain($parts[0] ?? ''); }
        if ($domain !== '' && !isset($seen[$domain])) { $seen[$domain] = true; $malware = (string) ($entry['malware_printable'] ?? $entry['malware'] ?? ''); $domains[] = ['domain' => $domain, 'ioc_value' => $iocValue, 'ioc_type' => $iocType, 'threat_type' => (string) ($entry['threat_type'] ?? ''), 'malware' => $malware, 'tags' => is_array($entry['tags'] ?? null) ? $entry['tags'] : [], 'confidence_level' => (int) ($entry['confidence_level'] ?? 50), 'first_seen' => (string) ($entry['first_seen'] ?? $entry['first_seen_utc'] ?? ''), 'reporter' => (string) ($entry['reporter'] ?? '')]; }
    }
    return ['ok' => true, 'domains' => $domains, 'count' => count($domains)];
}

function clickfix_abusech_threatfox_search(string $searchTerm, string $searchType = 'ioc', int $limit = 200): array
{
    return clickfix_abusech_threatfox_query('search_ioc', ['search_term' => $searchTerm, 'type' => $searchType], $limit);
}

function clickfix_abusech_threatfox_recent(int $limit = 200): array
{
    return clickfix_abusech_threatfox_query('recent', ['days' => 7], $limit);
}

function clickfix_abusech_fetch_clickfix_tags(PDO $pdo): array
{
    $tags = ['ClickFix', 'ClearFake', 'clickfix', 'clearfake'];
    $allDomains = [];
    $seen = [];
    foreach ($tags as $tag) {
        $result = clickfix_abusech_urlhaus_by_tag($tag, 200);
        if ($result['ok']) {
            foreach ($result['domains'] as $d) {
                $domain = $d['domain'];
                if (!isset($seen[$domain])) {
                    $seen[$domain] = true;
                    $d['source'] = 'urlhaus_tag_' . $tag;
                    $allDomains[] = $d;
                }
            }
        }
        $tfoxResult = clickfix_abusech_threatfox_search($tag, 'ioc', 200);
        if ($tfoxResult['ok']) {
            foreach ($tfoxResult['domains'] as $d) {
                $domain = $d['domain'];
                if (!isset($seen[$domain])) {
                    $seen[$domain] = true;
                    $d['threat'] = $d['malware'] ?? $tag;
                    $d['source'] = 'threatfox_tag_' . $tag;
                    $d['url'] = 'https://' . $domain;
                    $allDomains[] = $d;
                }
            }
        }
        sleep(1);
    }
    $imported = 0;
    if (clickfix_has_table($pdo, 'domain_feed_entries')) {
        $now = gmdate('c');
        $stmt = $pdo->prepare('INSERT OR IGNORE INTO domain_feed_entries (source_key, source_label, domain, url, first_seen, last_seen, hits, details_json, fetched_at) VALUES (:sk, :sl, :d, :u, :fs, :ls, :h, :dj, :fa)');
        foreach ($allDomains as $d) {
            $sourceKey = (string) ($d['source'] ?? '');
            $details = ['threat' => $d['threat'] ?? '', 'tags' => $d['tags'] ?? [], 'date_added' => $d['date_added'] ?? $d['first_seen'] ?? ''];
            $stmt->execute([
                ':sk' => $sourceKey !== '' ? $sourceKey : 'abusech',
                ':sl' => 'abuse.ch (' . ($sourceKey !== '' ? $sourceKey : 'ClickFix') . ')',
                ':d' => $d['domain'],
                ':u' => $d['url'] ?? '',
                ':fs' => $d['first_seen'] !== '' ? $d['first_seen'] : $now,
                ':ls' => $now, ':h' => 1,
                ':dj' => json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ':fa' => $now,
            ]);
            if ($stmt->rowCount() > 0) {
                $imported++;
            }
        }
    }
    return ['ok' => true, 'domains_found' => count($allDomains), 'imported' => $imported];
}
