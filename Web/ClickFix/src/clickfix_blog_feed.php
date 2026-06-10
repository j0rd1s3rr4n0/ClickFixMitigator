<?php
declare(strict_types=1);

require_once __DIR__ . '/clickfix_core.php';

function clickfix_blog_feed_sources(): array
{
    $sources = [];
    $inteltrackerUrl = trim((string) clickfix_env('CLICKFIX_BLOG_INTELTRACKER_URL', 'https://inteltracker.jordiserrano.me'));
    $blogUrl = trim((string) clickfix_env('CLICKFIX_BLOG_MAIN_URL', 'https://jordiserrano.me'));
    $inteltrackerEnabled = clickfix_env_truthy('CLICKFIX_BLOG_INTELTRACKER_ENABLED', true);
    $blogEnabled = clickfix_env_truthy('CLICKFIX_BLOG_MAIN_ENABLED', true);
    if ($inteltrackerEnabled && $inteltrackerUrl !== '') {
        $sources[] = ['url' => $inteltrackerUrl, 'label' => 'IntelTracker', 'feed_paths' => ['/feed/', '/rss/', '/feed.xml', '/rss.xml', '/wp-json/wp/v2/posts?per_page=10', '/index.xml', '/atom.xml']];
    }
    if ($blogEnabled && $blogUrl !== '') {
        $sources[] = ['url' => $blogUrl, 'label' => 'Jordi Serrano Blog', 'feed_paths' => ['/feed/', '/rss/', '/feed.xml', '/rss.xml', '/wp-json/wp/v2/posts?per_page=10', '/index.xml', '/atom.xml']];
    }
    return $sources;
}

function clickfix_blog_feed_fetch_url(string $url): ?string
{
    return clickfix_http_fetch($url, ['method' => 'GET', 'timeout' => 12]);
}

function clickfix_blog_feed_parse_rss(string $xml): array
{
    $items = [];
    libxml_use_internal_errors(true);
    $doc = simplexml_load_string($xml);
    if ($doc === false) {
        return [];
    }
    $namespaces = $doc->getNamespaces(true);
    $isAtom = $doc->getName() === 'feed';
    if ($isAtom) {
        foreach ($doc->entry as $entry) {
            $items[] = [
                'title' => (string) ($entry->title ?? ''),
                'link' => (string) ($entry->link['href'] ?? $entry->link ?? ''),
                'description' => strip_tags((string) ($entry->summary ?? $entry->content ?? '')),
                'pub_date' => (string) ($entry->updated ?? $entry->published ?? ''),
                'author' => (string) ($entry->author->name ?? ''),
            ];
        }
        return $items;
    }
    $channel = $doc->channel;
    if ($channel === null) {
        return [];
    }
    foreach ($channel->item as $item) {
        $dc = $item->children('http://purl.org/dc/elements/1.1/');
        $content = $item->children('http://purl.org/rss/1.0/modules/content/');
        $description = (string) ($content->encoded ?? $item->description ?? '');
        $items[] = [
            'title' => (string) ($item->title ?? ''),
            'link' => (string) ($item->link ?? ''),
            'description' => strip_tags($description),
            'pub_date' => (string) ($item->pubDate ?? $dc->date ?? ''),
            'author' => (string) ($dc->creator ?? $item->author ?? ''),
            'categories' => [],
        ];
    }
    return $items;
}

function clickfix_blog_feed_parse_wp_json(string $json): array
{
    $data = json_decode($json, true);
    if (!is_array($data)) {
        return [];
    }
    $items = [];
    foreach ($data as $post) {
        if (!is_array($post)) {
            continue;
        }
        $items[] = [
            'title' => (string) ($post['title']['rendered'] ?? $post['title'] ?? ''),
            'link' => (string) ($post['link'] ?? ''),
            'description' => strip_tags((string) ($post['excerpt']['rendered'] ?? $post['excerpt'] ?? '')),
            'pub_date' => (string) ($post['date'] ?? $post['modified'] ?? ''),
            'author' => (string) ($post['_embedded']['author'][0]['name'] ?? ''),
            'categories' => [],
        ];
    }
    return $items;
}

function clickfix_blog_feed_discover_and_fetch(string $baseUrl): array
{
    $paths = ['/feed/', '/rss/', '/feed.xml', '/rss.xml', '/wp-json/wp/v2/posts?per_page=10', '/index.xml', '/atom.xml'];
    foreach ($paths as $path) {
        $fullUrl = rtrim($baseUrl, '/') . $path;
        $response = clickfix_blog_feed_fetch_url($fullUrl);
        if ($response === null || trim($response) === '') {
            continue;
        }
        $trimmed = trim($response);
        if (str_starts_with($trimmed, '{') || str_starts_with($trimmed, '[')) {
            $items = clickfix_blog_feed_parse_wp_json($response);
        } elseif (str_starts_with($trimmed, '<')) {
            $items = clickfix_blog_feed_parse_rss($response);
        } else {
            continue;
        }
        if (!empty($items)) {
            return ['ok' => true, 'feed_url' => $fullUrl, 'items' => $items];
        }
    }
    return ['ok' => false, 'error' => 'no_feed_found', 'items' => []];
}

function clickfix_blog_feed_cache_store(PDO $pdo, string $sourceUrl, string $sourceLabel, array $items): void
{
    if (!clickfix_has_table($pdo, 'blog_feed_cache')) {
        return;
    }
    $ttl = max(300, min(86400, (int) clickfix_env('CLICKFIX_BLOG_FEED_CACHE_TTL', '3600')));
    $expiresAt = gmdate('c', time() + $ttl);
    $pdo->prepare('DELETE FROM blog_feed_cache WHERE source_url = :url')->execute([':url' => $sourceUrl]);
    $stmt = $pdo->prepare('INSERT OR IGNORE INTO blog_feed_cache (source_url, source_label, title, link, description, pub_date, author, categories_json, fetched_at, expires_at) VALUES (:su, :sl, :t, :l, :d, :pd, :a, :c, :fa, :ea)');
    foreach ($items as $item) {
        $categories = is_array($item['categories'] ?? null) ? $item['categories'] : [];
        $stmt->execute([
            ':su' => $sourceUrl,
            ':sl' => $sourceLabel,
            ':t' => substr((string) ($item['title'] ?? ''), 0, 500),
            ':l' => substr((string) ($item['link'] ?? ''), 0, 2000),
            ':d' => substr((string) ($item['description'] ?? ''), 0, 2000),
            ':pd' => (string) ($item['pub_date'] ?? ''),
            ':a' => substr((string) ($item['author'] ?? ''), 0, 200),
            ':c' => json_encode($categories, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':fa' => gmdate('c'),
            ':ea' => $expiresAt,
        ]);
    }
}

function clickfix_blog_feed_cache_get(PDO $pdo, string $sourceUrl = '', int $limit = 20): array
{
    if (!clickfix_has_table($pdo, 'blog_feed_cache')) {
        return [];
    }
    $sql = "SELECT * FROM blog_feed_cache WHERE expires_at > :now";
    $params = [':now' => gmdate('c')];
    if ($sourceUrl !== '') {
        $sql .= ' AND source_url = :url';
        $params[':url'] = $sourceUrl;
    }
    $sql .= ' ORDER BY pub_date DESC, id DESC LIMIT :limit';
    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $items = [];
    while ($row = $stmt->fetch()) {
        $categories = json_decode((string) ($row['categories_json'] ?? '[]'), true) ?: [];
        $items[] = [
            'id' => (int) ($row['id'] ?? 0),
            'source_url' => (string) ($row['source_url'] ?? ''),
            'source_label' => (string) ($row['source_label'] ?? ''),
            'title' => (string) ($row['title'] ?? ''),
            'link' => (string) ($row['link'] ?? ''),
            'description' => (string) ($row['description'] ?? ''),
            'pub_date' => (string) ($row['pub_date'] ?? ''),
            'author' => (string) ($row['author'] ?? ''),
            'categories' => is_array($categories) ? $categories : [],
        ];
    }
    return $items;
}

function clickfix_blog_feed_refresh(PDO $pdo): array
{
    clickfix_llm_ensure_table($pdo);
    $sources = clickfix_blog_feed_sources();
    $results = [];
    foreach ($sources as $source) {
        $url = (string) ($source['url'] ?? '');
        $label = (string) ($source['label'] ?? '');
        if ($url === '') {
            continue;
        }
        $fetched = clickfix_blog_feed_discover_and_fetch($url);
        if ($fetched['ok'] && !empty($fetched['items'])) {
            clickfix_blog_feed_cache_store($pdo, $url, $label, $fetched['items']);
        }
        $results[] = [
            'source' => $label,
            'url' => $url,
            'ok' => $fetched['ok'],
            'feed_url' => $fetched['feed_url'] ?? '',
            'items' => count($fetched['items'] ?? []),
        ];
    }
    return $results;
}

function clickfix_blog_feed_cache_cleanup(PDO $pdo): int
{
    if (!clickfix_has_table($pdo, 'blog_feed_cache')) {
        return 0;
    }
    $stmt = $pdo->prepare('DELETE FROM blog_feed_cache WHERE expires_at <= :now');
    $stmt->execute([':now' => gmdate('c')]);
    return $stmt->rowCount();
}

function clickfix_blog_feed_crosslink_investigations(PDO $pdo): array
{
    if (!clickfix_has_table($pdo, 'investigation_graphs') || !clickfix_has_table($pdo, 'blog_feed_cache')) {
        return [];
    }
    $stmt = $pdo->query("SELECT id, title, site_domain, summary, verdict FROM investigation_graphs WHERE deleted = 0 AND is_public = 1 AND workflow_status IN ('verified_public', 'verified_internal') ORDER BY id DESC LIMIT 50");
    $investigations = $stmt->fetchAll();
    $blogItems = clickfix_blog_feed_cache_get($pdo, '', 100);
    $crosslinks = [];
    foreach ($investigations as $inv) {
        $domain = strtolower(trim((string) ($inv['site_domain'] ?? '')));
        $title = strtolower(trim((string) ($inv['title'] ?? '')));
        if ($domain === '' && $title === '') {
            continue;
        }
        foreach ($blogItems as $blog) {
            $blogTitle = strtolower((string) ($blog['title'] ?? ''));
            $blogDesc = strtolower((string) ($blog['description'] ?? ''));
            $blogText = $blogTitle . ' ' . $blogDesc;
            $score = 0;
            if ($domain !== '' && str_contains($blogText, $domain)) {
                $score += 5;
            }
            $titleWords = array_filter(explode(' ', $title), static function ($w) { return strlen($w) > 3; });
            foreach ($titleWords as $word) {
                if (stripos($blogText, $word) !== false) {
                    $score += 1;
                }
            }
            if ($score >= 3) {
                $crosslinks[] = [
                    'investigation_id' => (int) ($inv['id'] ?? 0),
                    'investigation_title' => (string) ($inv['title'] ?? ''),
                    'blog_title' => (string) ($blog['title'] ?? ''),
                    'blog_link' => (string) ($blog['link'] ?? ''),
                    'relevance_score' => $score,
                ];
            }
        }
    }
    usort($crosslinks, static function (array $a, array $b): int {
        return ($b['relevance_score'] ?? 0) <=> ($a['relevance_score'] ?? 0);
    });
    return array_slice($crosslinks, 0, 30);
}
