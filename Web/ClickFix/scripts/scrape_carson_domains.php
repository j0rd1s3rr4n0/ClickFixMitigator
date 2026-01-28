<?php
declare(strict_types=1);

$baseUrl = getenv('CLICKFIX_CARSON_BASE') ?: 'https://clickfix.carsonww.com';
$limit = (int) (getenv('CLICKFIX_CARSON_LIMIT') ?: 50);
$maxPages = (int) (getenv('CLICKFIX_CARSON_MAX_PAGES') ?: 200);
$delayMs = (int) (getenv('CLICKFIX_CARSON_DELAY_MS') ?: 350);
$endpointOverride = getenv('CLICKFIX_CARSON_ENDPOINT') ?: '';

$dataDir = __DIR__ . '/../data';
$outputJson = $dataDir . '/carson-domains.json';
$outputTxt = $dataDir . '/carson-domains.txt';
$metaPath = $dataDir . '/carson-scrape.json';

if (!is_dir($dataDir)) {
    @mkdir($dataDir, 0775, true);
}

function writeMeta(string $path, array $payload): void
{
    $payload['updated_at'] = gmdate('c');
    @file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
}

function httpGet(string $url, int $timeoutSeconds = 15): array
{
    $body = '';
    $status = 0;
    $contentType = '';
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => $timeoutSeconds,
            CURLOPT_HTTPHEADER => [
                'User-Agent: ClickFixDashboard/1.0',
                'Accept: */*'
            ]
        ]);
        $body = (string) curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);
    } else {
        $context = stream_context_create([
            'http' => [
                'timeout' => $timeoutSeconds,
                'header' => "User-Agent: ClickFixDashboard/1.0\r\nAccept: */*\r\n"
            ]
        ]);
        $body = (string) (@file_get_contents($url, false, $context) ?: '');
    }
    return [
        'status' => $status,
        'content_type' => $contentType,
        'body' => $body
    ];
}

function normalizeDomain(string $domain): string
{
    $domain = strtolower(trim($domain));
    $domain = rtrim($domain, '.');
    return $domain;
}

function looksLikeDomain(string $value): bool
{
    if ($value === '') {
        return false;
    }
    $value = trim($value);
    if (!filter_var($value, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
        return false;
    }
    return strpos($value, '.') !== false;
}

function extractDomainsFromHtml(string $html, string $baseUrl): array
{
    $domains = [];
    if ($html === '') {
        return $domains;
    }
    $pattern = '~href=["\'](?:' . preg_quote($baseUrl, '~') . ')?/domains/([^"\'/?#]+)~i';
    if (preg_match_all($pattern, $html, $matches)) {
        foreach ($matches[1] as $value) {
            $value = normalizeDomain($value);
            if (looksLikeDomain($value)) {
                $domains[$value] = true;
            }
        }
    }
    return array_keys($domains);
}

function extractDomainsFromJsonPayload($payload): array
{
    $domains = [];
    if (is_string($payload)) {
        if (looksLikeDomain($payload)) {
            $domains[normalizeDomain($payload)] = true;
        }
        return array_keys($domains);
    }
    if (is_array($payload)) {
        foreach ($payload as $value) {
            foreach (extractDomainsFromJsonPayload($value) as $domain) {
                $domains[$domain] = true;
            }
        }
    }
    return array_keys($domains);
}

function parseTotalPagesFromHtml(string $html): int
{
    if (preg_match('/Page\\s+\\d+\\s+of\\s+(\\d+)/i', $html, $matches)) {
        return max(1, (int) $matches[1]);
    }
    return 1;
}

function extractDomainsFromJson(string $json): array
{
    $decoded = json_decode($json, true);
    if ($decoded === null) {
        return [];
    }
    return extractDomainsFromJsonPayload($decoded);
}

function detectTotalPagesFromJson(string $json, int $limit): int
{
    $decoded = json_decode($json, true);
    if (!is_array($decoded)) {
        return 1;
    }
    $keys = [
        'total_pages',
        'totalPages',
        'pages',
        'pageCount'
    ];
    foreach ($keys as $key) {
        if (isset($decoded[$key])) {
            return max(1, (int) $decoded[$key]);
        }
    }
    if (isset($decoded['total']) && $limit > 0) {
        $total = (int) $decoded['total'];
        return max(1, (int) ceil($total / $limit));
    }
    return 1;
}

function shouldTreatAsJson(array $response): bool
{
    if (!empty($response['content_type']) && stripos($response['content_type'], 'application/json') !== false) {
        return true;
    }
    $body = trim((string) ($response['body'] ?? ''));
    return $body !== '' && ($body[0] === '{' || $body[0] === '[');
}

function scrapeHtmlPages(string $baseUrl, int $limit, int $maxPages, int $delayMs): array
{
    $domains = [];
    $listUrl = $baseUrl . '/domains?limit=' . $limit;
    $first = httpGet($listUrl);
    $firstDomains = extractDomainsFromHtml((string) $first['body'], $baseUrl);
    foreach ($firstDomains as $domain) {
        $domains[$domain] = true;
    }
    if (empty($firstDomains)) {
        return ['domains' => [], 'pages' => 0, 'source' => $listUrl];
    }
    $totalPages = parseTotalPagesFromHtml((string) $first['body']);
    $totalPages = min($totalPages, $maxPages);
    for ($page = 2; $page <= $totalPages; $page++) {
        $url = $baseUrl . '/domains?limit=' . $limit . '&page=' . $page;
        $response = httpGet($url);
        $pageDomains = extractDomainsFromHtml((string) $response['body'], $baseUrl);
        foreach ($pageDomains as $domain) {
            $domains[$domain] = true;
        }
        usleep(max(0, $delayMs) * 1000);
    }
    return ['domains' => array_keys($domains), 'pages' => $totalPages, 'source' => $listUrl];
}

function scrapeJsonPages(string $endpointPattern, int $limit, int $maxPages, int $delayMs): array
{
    $domains = [];
    $page = 1;
    $pagesTotal = 1;
    $firstUrl = sprintf($endpointPattern, $page);
    $first = httpGet($firstUrl);
    $body = (string) $first['body'];
    $firstDomains = extractDomainsFromJson($body);
    foreach ($firstDomains as $domain) {
        $domains[$domain] = true;
    }
    $pagesTotal = detectTotalPagesFromJson($body, $limit);
    $pagesTotal = min($pagesTotal, $maxPages);
    if ($pagesTotal <= 1 && empty($firstDomains)) {
        return ['domains' => [], 'pages' => 0, 'source' => $firstUrl];
    }
    for ($page = 2; $page <= $pagesTotal; $page++) {
        $url = sprintf($endpointPattern, $page);
        $response = httpGet($url);
        $pageDomains = extractDomainsFromJson((string) $response['body']);
        foreach ($pageDomains as $domain) {
            $domains[$domain] = true;
        }
        usleep(max(0, $delayMs) * 1000);
    }
    return ['domains' => array_keys($domains), 'pages' => $pagesTotal, 'source' => $firstUrl];
}

try {
    $domains = [];
    $sourceUrl = '';
    $pagesTotal = 0;

    if ($endpointOverride !== '') {
        $pattern = strpos($endpointOverride, '%d') !== false ? $endpointOverride : $endpointOverride . '&page=%d';
        $jsonResult = scrapeJsonPages($pattern, $limit, $maxPages, $delayMs);
        $domains = $jsonResult['domains'];
        $pagesTotal = $jsonResult['pages'];
        $sourceUrl = $jsonResult['source'];
    } else {
        $htmlResult = scrapeHtmlPages($baseUrl, $limit, $maxPages, $delayMs);
        if (!empty($htmlResult['domains'])) {
            $domains = $htmlResult['domains'];
            $pagesTotal = $htmlResult['pages'];
            $sourceUrl = $htmlResult['source'];
        } else {
            $candidates = [
                $baseUrl . '/api/domains?limit=' . $limit . '&page=%d',
                $baseUrl . '/api/v1/domains?limit=' . $limit . '&page=%d',
                $baseUrl . '/domains.json?limit=' . $limit . '&page=%d',
                $baseUrl . '/api/domains?limit=' . $limit . '&offset=%d'
            ];
            foreach ($candidates as $pattern) {
                $probe = httpGet(sprintf($pattern, 1));
                if (!shouldTreatAsJson($probe)) {
                    continue;
                }
                $probeDomains = extractDomainsFromJson((string) $probe['body']);
                if (!empty($probeDomains)) {
                    $jsonResult = scrapeJsonPages($pattern, $limit, $maxPages, $delayMs);
                    $domains = $jsonResult['domains'];
                    $pagesTotal = $jsonResult['pages'];
                    $sourceUrl = $jsonResult['source'];
                    break;
                }
            }
        }
    }

    sort($domains);
    $payload = [
        'source' => $sourceUrl,
        'total' => count($domains),
        'pages' => $pagesTotal,
        'domains' => array_values($domains),
        'fetched_at' => gmdate('c')
    ];
    @file_put_contents($outputJson, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
    @file_put_contents($outputTxt, implode("\n", $domains), LOCK_EX);

    writeMeta($metaPath, [
        'status' => empty($domains) ? 'empty' : 'ok',
        'last_run' => gmdate('c'),
        'count' => count($domains),
        'pages' => $pagesTotal,
        'source' => $sourceUrl
    ]);
} catch (Throwable $error) {
    writeMeta($metaPath, [
        'status' => 'error',
        'last_run' => gmdate('c'),
        'error' => $error->getMessage()
    ]);
    exit(1);
}

