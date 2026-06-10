<?php
declare(strict_types=1);

require_once __DIR__ . '/clickfix_core.php';

function clickfix_seo_canonical_url(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') ? 'https' : 'http';
    $host = strtolower(trim((string) ($_SERVER['HTTP_HOST'] ?? 'clickfix.jordiserrano.me')));
    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    return $scheme . '://' . $host . $uri;
}

function clickfix_seo_meta_tags(string $title, string $description, string $canonical = '', string $image = '', string $type = 'website'): string
{
    $canonical = $canonical !== '' ? $canonical : clickfix_seo_canonical_url();
    $image = $image !== '' ? $image : 'https://clickfix.jordiserrano.me/assets/corona/images/clickfix-og.png';
    $tags = [];
    $tags[] = '<title>' . clickfix_h($title) . '</title>';
    $tags[] = '<meta name="description" content="' . clickfix_h($description) . '">';
    $tags[] = '<meta name="robots" content="index,follow,max-image-preview:large,max-snippet:-1,max-video-preview:-1">';
    $tags[] = '<meta name="googlebot" content="index,follow,max-image-preview:large,max-snippet:-1,max-video-preview:-1">';
    $tags[] = '<link rel="canonical" href="' . clickfix_h($canonical) . '">';
    $tags[] = '<meta property="og:title" content="' . clickfix_h($title) . '">';
    $tags[] = '<meta property="og:description" content="' . clickfix_h($description) . '">';
    $tags[] = '<meta property="og:url" content="' . clickfix_h($canonical) . '">';
    $tags[] = '<meta property="og:image" content="' . clickfix_h($image) . '">';
    $tags[] = '<meta property="og:type" content="' . clickfix_h($type) . '">';
    $tags[] = '<meta property="og:site_name" content="ClickFix Mitigator">';
    $tags[] = '<meta name="twitter:card" content="summary_large_image">';
    $tags[] = '<meta name="twitter:title" content="' . clickfix_h($title) . '">';
    $tags[] = '<meta name="twitter:description" content="' . clickfix_h($description) . '">';
    $tags[] = '<meta name="twitter:image" content="' . clickfix_h($image) . '">';
    return implode("\n", $tags);
}

function clickfix_seo_investigation_structured_data(array $investigation): string
{
    $title = (string) ($investigation['title'] ?? 'Untitled Investigation');
    $summary = (string) ($investigation['summary'] ?? '');
    $domain = (string) ($investigation['site_domain'] ?? '');
    $verdict = (string) ($investigation['verdict'] ?? 'unknown');
    $updatedAt = (string) ($investigation['updated_at'] ?? '');
    $shareToken = (string) ($investigation['share_token'] ?? '');
    $url = '';
    if ($shareToken !== '') {
        $url = 'https://clickfix.jordiserrano.me/dashboard.php?page=investigation&share=' . urlencode($shareToken);
    }
    $data = [
        '@context' => 'https://schema.org',
        '@type' => 'Article',
        'headline' => $title,
        'description' => substr($summary, 0, 300),
        'dateModified' => $updatedAt,
        'author' => [
            '@type' => 'Organization',
            'name' => 'ClickFix Mitigator',
            'url' => 'https://clickfix.jordiserrano.me',
        ],
        'publisher' => [
            '@type' => 'Organization',
            'name' => 'ClickFix Mitigator',
            'url' => 'https://clickfix.jordiserrano.me',
        ],
        'about' => [
            '@type' => 'Thing',
            'name' => $domain !== '' ? $domain : $title,
        ],
    ];
    if ($url !== '') {
        $data['url'] = $url;
        $data['mainEntityOfPage'] = ['@type' => 'WebPage', '@id' => $url];
    }
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    if ($json === false) {
        return '';
    }
    return '<script type="application/ld+json">' . $json . '</script>';
}

function clickfix_seo_breadcrumb_jsonld(array $crumbs): string
{
    if (empty($crumbs)) {
        return '';
    }
    $items = [];
    $position = 1;
    foreach ($crumbs as $name => $url) {
        $items[] = [
            '@type' => 'ListItem',
            'position' => $position,
            'name' => (string) $name,
            'item' => (string) $url,
        ];
        $position++;
    }
    $json = json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => $items,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return '';
    }
    return '<script type="application/ld+json">' . $json . '</script>';
}

function clickfix_seo_sitemap_investigations(PDO $pdo): array
{
    if (!clickfix_has_table($pdo, 'investigation_graphs')) {
        return [];
    }
    $stmt = $pdo->query("SELECT id, title, site_domain, verdict, workflow_status, updated_at, is_public, share_token FROM investigation_graphs WHERE deleted = 0 AND is_public = 1 AND share_token IS NOT NULL AND TRIM(share_token) != '' ORDER BY id DESC LIMIT 500");
    $entries = [];
    while ($row = $stmt->fetch()) {
        $shareToken = trim((string) ($row['share_token'] ?? ''));
        if ($shareToken === '') {
            continue;
        }
        $entries[] = [
            'loc' => 'https://clickfix.jordiserrano.me/dashboard.php?page=investigation&share=' . urlencode($shareToken),
            'lastmod' => (string) ($row['updated_at'] ?? gmdate('c')),
            'changefreq' => 'weekly',
            'priority' => '0.7',
            'title' => (string) ($row['title'] ?? ''),
        ];
    }
    return $entries;
}

function clickfix_seo_generate_sitemap_xml(PDO $pdo): string
{
    $baseUrl = 'https://clickfix.jordiserrano.me';
    $staticPages = [
        ['loc' => $baseUrl . '/', 'changefreq' => 'daily', 'priority' => '1.0'],
        ['loc' => $baseUrl . '/dashboard.php?page=home&public=1', 'changefreq' => 'daily', 'priority' => '0.9'],
        ['loc' => $baseUrl . '/dashboard.php?page=search&public=1', 'changefreq' => 'daily', 'priority' => '0.8'],
        ['loc' => $baseUrl . '/dashboard.php?page=about&public=1', 'changefreq' => 'monthly', 'priority' => '0.6'],
        ['loc' => $baseUrl . '/dashboard.php?page=coverage&public=1', 'changefreq' => 'weekly', 'priority' => '0.7'],
        ['loc' => $baseUrl . '/dashboard.php?page=access&public=1', 'changefreq' => 'monthly', 'priority' => '0.5'],
        ['loc' => $baseUrl . '/PrivacyPolicy.html', 'changefreq' => 'monthly', 'priority' => '0.3'],
        ['loc' => $baseUrl . '/TermsAndConditions.html', 'changefreq' => 'monthly', 'priority' => '0.3'],
    ];
    $investigations = clickfix_seo_sitemap_investigations($pdo);
    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:news="http://www.google.com/schemas/sitemap-news/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";
    foreach ($staticPages as $page) {
        $xml .= '  <url>' . "\n";
        $xml .= '    <loc>' . clickfix_h($page['loc']) . '</loc>' . "\n";
        $xml .= '    <changefreq>' . $page['changefreq'] . '</changefreq>' . "\n";
        $xml .= '    <priority>' . $page['priority'] . '</priority>' . "\n";
        $xml .= '  </url>' . "\n";
    }
    foreach ($investigations as $inv) {
        $xml .= '  <url>' . "\n";
        $xml .= '    <loc>' . clickfix_h($inv['loc']) . '</loc>' . "\n";
        $xml .= '    <lastmod>' . clickfix_h($inv['lastmod']) . '</lastmod>' . "\n";
        $xml .= '    <changefreq>' . $inv['changefreq'] . '</changefreq>' . "\n";
        $xml .= '    <priority>' . $inv['priority'] . '</priority>' . "\n";
        if (!empty($inv['title'])) {
            $xml .= '    <news:news>' . "\n";
            $xml .= '      <news:publication>' . "\n";
            $xml .= '        <news:name>ClickFix Mitigator</news:name>' . "\n";
            $xml .= '        <news:language>en</news:language>' . "\n";
            $xml .= '      </news:publication>' . "\n";
            $xml .= '      <news:title>' . clickfix_h($inv['title']) . '</news:title>' . "\n";
            $xml .= '      <news:publication_date>' . clickfix_h($inv['lastmod']) . '</news:publication_date>' . "\n";
            $xml .= '    </news:news>' . "\n";
        }
        $xml .= '  </url>' . "\n";
    }
    $xml .= '</urlset>';
    return $xml;
}
