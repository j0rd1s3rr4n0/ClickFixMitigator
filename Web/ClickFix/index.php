<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    $isHttps = !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}
if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('Vary: Cookie');
}
require_once __DIR__ . '/src/clickfix_core.php';
clickfix_bootstrap();
$monetization = clickfix_monetization_config();
$indexViewer = clickfix_current_user();
$indexViewerRole = $indexViewer ? clickfix_normalize_role((string) ($indexViewer['role'] ?? 'guest')) : 'guest';
$indexAdAudienceRoles = ['guest', 'analyst_jr', 'analyst_mid'];
$indexAdsAudienceEnabled = in_array($indexViewerRole, $indexAdAudienceRoles, true);
$indexShowMonetizationSupport = !empty($monetization['enabled']);

const ACCESS_REQUEST_FLASH_KEY = 'clickfix_access_request_flash';
const ACCESS_REQUEST_CSRF_KEY = 'clickfix_access_request_csrf';
const ACCESS_REQUEST_LAST_SUBMIT_KEY = 'clickfix_access_request_last_submit';
const ACCESS_REQUEST_RATE_LIMIT_SECONDS = 30;

function getClientIpAddress(): string
{
    $headerCandidates = [
        'HTTP_CF_CONNECTING_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_REAL_IP',
        'REMOTE_ADDR',
    ];
    foreach ($headerCandidates as $key) {
        $value = $_SERVER[$key] ?? '';
        if (!is_string($value) || $value === '') {
            continue;
        }
        $candidate = trim(explode(',', $value)[0]);
        if (filter_var($candidate, FILTER_VALIDATE_IP)) {
            return $candidate;
        }
    }
    return '0.0.0.0';
}

function normalizeLanguage(?string $language): string
{
    $allowed = ['es', 'en', 'pt', 'fr', 'de', 'nl', 'ca', 'it', 'ru', 'ja', 'ko', 'zh', 'hi', 'ar', 'he'];
    $value = strtolower(trim((string) $language));
    return in_array($value, $allowed, true) ? $value : 'en';
}

function detectRegionLanguage(array $geo): string
{
    $countryCode = strtoupper(trim((string) ($geo['countryCode'] ?? '')));
    $region = strtoupper(trim((string) ($geo['region'] ?? '')));
    $regionName = strtolower(trim((string) ($geo['regionName'] ?? '')));

    if ($countryCode === 'CA' && $region === 'QC') {
        return 'fr';
    }
    if ($countryCode === 'ES' && ($region === 'CT' || str_contains($regionName, 'catal'))) {
        return 'ca';
    }
    if ($countryCode === 'BE') {
        if (in_array($region, ['BRU', 'BR', 'WAL', 'WBR', 'WHT', 'WLG', 'WLX', 'WNA'], true) || str_contains($regionName, 'wall')) {
            return 'fr';
        }
        if (in_array($region, ['VAN', 'VBR', 'VLG', 'VLI', 'VWV', 'VOV'], true) || str_contains($regionName, 'fland')) {
            return 'nl';
        }
    }

    $countryMap = [
        'AR' => 'es', 'BO' => 'es', 'CL' => 'es', 'CO' => 'es', 'CR' => 'es', 'CU' => 'es', 'DO' => 'es',
        'EC' => 'es', 'ES' => 'es', 'GQ' => 'es', 'GT' => 'es', 'HN' => 'es', 'MX' => 'es', 'NI' => 'es',
        'PA' => 'es', 'PE' => 'es', 'PR' => 'es', 'PY' => 'es', 'SV' => 'es', 'UY' => 'es', 'VE' => 'es',
        'PT' => 'pt', 'BR' => 'pt', 'AO' => 'pt', 'MZ' => 'pt', 'CV' => 'pt', 'GW' => 'pt', 'ST' => 'pt', 'TL' => 'pt',
        'FR' => 'fr', 'MC' => 'fr', 'LU' => 'fr', 'MA' => 'fr', 'DZ' => 'fr', 'TN' => 'fr',
        'IT' => 'it',
        'DE' => 'de', 'AT' => 'de', 'LI' => 'de',
        'NL' => 'nl',
        'AD' => 'ca',
        'RU' => 'ru', 'BY' => 'ru', 'KZ' => 'ru',
        'JP' => 'ja',
        'KR' => 'ko',
        'CN' => 'zh', 'TW' => 'zh', 'HK' => 'zh', 'MO' => 'zh', 'SG' => 'zh',
        'IN' => 'hi',
        'EG' => 'ar', 'SA' => 'ar', 'AE' => 'ar', 'QA' => 'ar', 'KW' => 'ar', 'BH' => 'ar', 'OM' => 'ar',
        'JO' => 'ar', 'LB' => 'ar', 'IQ' => 'ar', 'YE' => 'ar', 'LY' => 'ar', 'SD' => 'ar',
        'IL' => 'he',
    ];

    return $countryMap[$countryCode] ?? 'en';
}

function fetchClientGeoContext(): array
{
    $clientIp = getClientIpAddress();
    if (
        !filter_var(
            $clientIp,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        )
    ) {
        return [];
    }

    $cacheKey = 'clickfix_geo_context_lookup';
    $cached = $_SESSION[$cacheKey] ?? null;
    $now = time();
    if (
        is_array($cached) &&
        (string) ($cached['ip'] ?? '') === $clientIp &&
        isset($cached['data'], $cached['checked_at']) &&
        is_array($cached['data']) &&
        ($now - (int) $cached['checked_at']) < 86400
    ) {
        return $cached['data'];
    }

    $fields = 'status,message,continent,continentCode,country,countryCode,region,regionName,city,district,zip,lat,lon,timezone,offset,currency,isp,org,as,asname,reverse,mobile,proxy,hosting,query';
    $response = clickfix_http_request(
        'http://ip-api.com/json/' . rawurlencode($clientIp) . '?fields=' . rawurlencode($fields),
        'GET',
        [
            'User-Agent' => 'ClickFixMitigator/1.0',
            'Accept' => 'application/json',
        ],
        null,
        4
    );
    $context = [];
    if (!empty($response['ok']) && (int) ($response['status'] ?? 0) === 200) {
        $json = json_decode((string) ($response['body'] ?? ''), true);
        if (is_array($json) && strtolower(trim((string) ($json['status'] ?? ''))) === 'success') {
            $json['detected_lang'] = detectRegionLanguage($json);
            $context = $json;
        }
    }

    $_SESSION[$cacheKey] = [
        'ip' => $clientIp,
        'data' => $context,
        'checked_at' => $now,
    ];

    return $context;
}

function detectInitialLanguage(?array $geoContext = null): string
{
    $queryLang = normalizeLanguage((string) ($_GET['lang'] ?? ''));
    if ($queryLang !== 'en' || strtolower(trim((string) ($_GET['lang'] ?? ''))) === 'en') {
        return $queryLang;
    }

    $context = is_array($geoContext) ? $geoContext : fetchClientGeoContext();
    if (
        isset($context['detected_lang']) &&
        is_string($context['detected_lang']) &&
        $context['detected_lang'] !== ''
    ) {
        return normalizeLanguage($context['detected_lang']);
    }

    if (is_array($context) && strtolower(trim((string) ($context['status'] ?? ''))) === 'success') {
        return detectRegionLanguage($context);
    }

    return 'en';
}

function buildPublicClientGeoContext(array $geo, string $selectedLanguage): array
{
    $isAvailable = strtolower(trim((string) ($geo['status'] ?? ''))) === 'success';
    if (!$isAvailable) {
        return [
            'available' => false,
            'lang' => normalizeLanguage($selectedLanguage),
        ];
    }

    $city = trim((string) ($geo['city'] ?? ''));
    $district = trim((string) ($geo['district'] ?? ''));
    $regionName = trim((string) ($geo['regionName'] ?? ''));
    $country = trim((string) ($geo['country'] ?? ''));
    $countryCode = strtoupper(trim((string) ($geo['countryCode'] ?? '')));
    $timezone = trim((string) ($geo['timezone'] ?? ''));
    $isp = trim((string) ($geo['isp'] ?? ''));
    $org = trim((string) ($geo['org'] ?? ''));
    $asName = trim((string) ($geo['asname'] ?? ''));
    $networkProfile = 'direct';
    if (!empty($geo['proxy'])) {
        $networkProfile = 'proxy';
    } elseif (!empty($geo['hosting'])) {
        $networkProfile = 'hosting';
    } elseif (!empty($geo['mobile'])) {
        $networkProfile = 'mobile';
    }

    $placeParts = array_values(array_filter([$city, $district, $regionName, $country], static function ($value): bool {
        return is_string($value) && $value !== '';
    }));
    $placeLabel = implode(', ', array_slice($placeParts, 0, 3));
    if ($placeLabel === '') {
        $placeLabel = $country !== '' ? $country : ($countryCode !== '' ? $countryCode : '--');
    }

    return [
        'available' => true,
        'lang' => normalizeLanguage($selectedLanguage),
        'continent' => trim((string) ($geo['continent'] ?? '')),
        'continent_code' => strtoupper(trim((string) ($geo['continentCode'] ?? ''))),
        'country' => $country,
        'country_code' => $countryCode,
        'region' => trim((string) ($geo['region'] ?? '')),
        'region_name' => $regionName,
        'city' => $city,
        'district' => $district,
        'zip' => trim((string) ($geo['zip'] ?? '')),
        'lat' => isset($geo['lat']) ? (float) $geo['lat'] : null,
        'lon' => isset($geo['lon']) ? (float) $geo['lon'] : null,
        'timezone' => $timezone,
        'offset' => isset($geo['offset']) ? (int) $geo['offset'] : 0,
        'currency' => trim((string) ($geo['currency'] ?? '')),
        'isp' => $isp,
        'org' => $org,
        'as' => trim((string) ($geo['as'] ?? '')),
        'asname' => $asName,
        'mobile' => !empty($geo['mobile']),
        'proxy' => !empty($geo['proxy']),
        'hosting' => !empty($geo['hosting']),
        'network_profile' => $networkProfile,
        'place_label' => $placeLabel,
        'display_network' => $asName !== '' ? $asName : ($org !== '' ? $org : $isp),
    ];
}

function index_static_text(string $key, string $language): string
{
    $dict = [
        'en' => [
            'geo_eyebrow' => 'Regional context',
            'geo_summary_empty' => 'Automatic regional context unavailable.',
            'geo_region' => 'Region',
            'geo_timezone' => 'Timezone',
            'geo_network' => 'Network',
            'geo_language' => 'Language',
            'footer_credit_prefix' => 'Made with 🤍by',
            'public_evidence_none' => 'No public evidence approved yet',
            'public_evidence_featured' => 'ausgewaehlt',
        ],
        'es' => [
            'geo_eyebrow' => 'Contexto regional',
            'geo_summary_empty' => 'Contexto regional automatico no disponible.',
            'geo_region' => 'Region',
            'geo_timezone' => 'Zona horaria',
            'geo_network' => 'Red',
            'geo_language' => 'Idioma',
            'footer_credit_prefix' => 'Made with 🤍by',
            'public_evidence_none' => 'Todavia no hay evidencia publica aprobada',
            'public_evidence_featured' => 'destacada',
        ],
        'pt' => [
            'geo_eyebrow' => 'Contexto regional',
            'geo_summary_empty' => 'Contexto regional automatico indisponivel.',
            'geo_region' => 'Regiao',
            'geo_timezone' => 'Fuso horario',
            'geo_network' => 'Rede',
            'geo_language' => 'Idioma',
            'footer_credit_prefix' => 'Made with 🤍by',
            'public_evidence_none' => 'Ainda nao ha evidencia publica aprovada',
            'public_evidence_featured' => 'destacada',
        ],
        'fr' => [
            'geo_eyebrow' => 'Contexte regional',
            'geo_summary_empty' => 'Contexte regional automatique indisponible.',
            'geo_region' => 'Region',
            'geo_timezone' => 'Fuseau horaire',
            'geo_network' => 'Reseau',
            'geo_language' => 'Langue',
            'footer_credit_prefix' => 'Made with 🤍by',
            'public_evidence_none' => 'Aucune preuve publique approuvee pour le moment',
            'public_evidence_featured' => 'vedette',
        ],
        'de' => [
            'geo_eyebrow' => 'Regionaler Kontext',
            'geo_summary_empty' => 'Automatischer regionaler Kontext nicht verfuegbar.',
            'geo_region' => 'Region',
            'geo_timezone' => 'Zeitzone',
            'geo_network' => 'Netzwerk',
            'geo_language' => 'Sprache',
            'footer_credit_prefix' => 'Made with 🤍by',
            'public_evidence_none' => 'Noch keine oeffentlichen Beweise freigegeben',
            'public_evidence_featured' => 'featured',
        ],
        'nl' => [
            'geo_eyebrow' => 'Regionale context',
            'geo_summary_empty' => 'Automatische regionale context niet beschikbaar.',
            'geo_region' => 'Regio',
            'geo_timezone' => 'Tijdzone',
            'geo_network' => 'Netwerk',
            'geo_language' => 'Taal',
            'footer_credit_prefix' => 'Made with 🤍by',
            'public_evidence_none' => 'Nog geen publiek goedgekeurd bewijs beschikbaar',
            'public_evidence_featured' => 'uitgelicht',
        ],
        'ca' => [
            'geo_eyebrow' => 'Context regional',
            'geo_summary_empty' => 'Context regional automatic no disponible.',
            'geo_region' => 'Regio',
            'geo_timezone' => 'Zona horaria',
            'geo_network' => 'Xarxa',
            'geo_language' => 'Idioma',
            'footer_credit_prefix' => 'Made with 🤍by',
            'public_evidence_none' => 'Encara no hi ha evidencia publica aprovada',
            'public_evidence_featured' => 'destacada',
        ],
    ];
    $language = normalizeLanguage($language);
    return (string) (($dict[$language][$key] ?? null) ?: ($dict['en'][$key] ?? ''));
}

function persistAccessRequestFallback(string $email, string $language, string $linkedinUrl, string $companyWebsite = ''): bool
{
    $record = [
        'email' => $email,
        'request_lang' => $language,
        'linkedin_url' => $linkedinUrl,
        'company_website' => $companyWebsite,
        'request_ip' => getClientIpAddress(),
        'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
        'created_at' => gmdate('c'),
    ];
    if (function_exists('clickfix_access_request_fallback_write')) {
        return clickfix_access_request_fallback_write($record);
    }

    $path = __DIR__ . '/data/access_requests.ndjson';
    $dir = dirname($path);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $line = json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($line) || $line === '') {
        return false;
    }
    return file_put_contents($path, $line . PHP_EOL, FILE_APPEND | LOCK_EX) !== false;
}

function persistAccessRequest(string $email, string $language, string $linkedinUrl, string $companyWebsite = ''): bool
{
    try {
        $pdo = clickfix_open_db(true);
        return clickfix_store_access_request($pdo, $email, $language, $linkedinUrl, $companyWebsite);
    } catch (Throwable $exception) {
        // Fall back to local persistence when DB write is unavailable.
        return persistAccessRequestFallback($email, $language, $linkedinUrl, $companyWebsite);
    }
}

if (!isset($_SESSION[ACCESS_REQUEST_CSRF_KEY]) || !is_string($_SESSION[ACCESS_REQUEST_CSRF_KEY])) {
    try {
        $_SESSION[ACCESS_REQUEST_CSRF_KEY] = bin2hex(random_bytes(32));
    } catch (Throwable $exception) {
        $_SESSION[ACCESS_REQUEST_CSRF_KEY] = hash('sha256', uniqid((string) mt_rand(), true));
    }
}
$accessRequestCsrfToken = $_SESSION[ACCESS_REQUEST_CSRF_KEY];
$csrfCookieName = 'clickfix_access_request_csrf';
$csrfCookieToken = (string) ($_COOKIE[$csrfCookieName] ?? '');
if ($csrfCookieToken === '' || !hash_equals($csrfCookieToken, $accessRequestCsrfToken)) {
    $cookieParams = [
        'expires' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off'),
        'httponly' => false,
        'samesite' => 'Lax',
    ];
    setcookie($csrfCookieName, $accessRequestCsrfToken, $cookieParams);
    $_COOKIE[$csrfCookieName] = $accessRequestCsrfToken;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['form_action'] ?? '') === 'request_access') {
    $status = 'error';
    $errorCode = 'unknown';
    $submittedToken = (string) ($_POST['csrf_token'] ?? '');
    $cookieToken = (string) ($_COOKIE[$csrfCookieName] ?? '');
    if ($cookieToken !== '') {
        $submittedToken = $cookieToken;
    }
    $honeypot = trim((string) ($_POST['company_website_hp'] ?? ''));
    $submittedEmail = strtolower(trim((string) ($_POST['access_email'] ?? '')));
    $submittedLinkedIn = trim((string) ($_POST['access_linkedin'] ?? ''));
    $submittedCompanyWebsite = trim((string) ($_POST['company_website'] ?? ''));
    $submittedLanguage = normalizeLanguage((string) ($_POST['request_lang'] ?? 'en'));
    $lastSubmit = (int) ($_SESSION[ACCESS_REQUEST_LAST_SUBMIT_KEY] ?? 0);
    $now = time();

    if ($honeypot !== '') {
        $status = 'ok';
    } elseif (
        !hash_equals($accessRequestCsrfToken, $submittedToken)
        && ($cookieToken === '' || !hash_equals($cookieToken, $submittedToken))
    ) {
        $status = 'error';
        $errorCode = 'invalid_csrf';
    } elseif (($now - $lastSubmit) < ACCESS_REQUEST_RATE_LIMIT_SECONDS) {
        $status = 'rate_limited';
    } elseif (
        $submittedEmail === '' ||
        strlen($submittedEmail) > 190 ||
        filter_var($submittedEmail, FILTER_VALIDATE_EMAIL) === false
    ) {
        $status = 'error';
        $errorCode = 'invalid_email';
    } elseif (persistAccessRequest($submittedEmail, $submittedLanguage, $submittedLinkedIn, $submittedCompanyWebsite)) {
        $_SESSION[ACCESS_REQUEST_LAST_SUBMIT_KEY] = $now;
        $status = 'ok';
    } else {
        $status = 'error';
        $errorCode = 'store_failed';
    }

    if ($status === 'error') {
        $status = 'error:' . $errorCode;
    }
    $_SESSION[ACCESS_REQUEST_FLASH_KEY] = $status;
    header('Location: index.php#dashboard-preview', true, 303);
    exit;
}

$accessRequestFlash = null;
if (isset($_SESSION[ACCESS_REQUEST_FLASH_KEY]) && is_string($_SESSION[ACCESS_REQUEST_FLASH_KEY])) {
    $accessRequestFlash = $_SESSION[ACCESS_REQUEST_FLASH_KEY];
    unset($_SESSION[ACCESS_REQUEST_FLASH_KEY]);
}
$clientGeoContext = fetchClientGeoContext();
$initialLang = detectInitialLanguage($clientGeoContext);

$publicLatestScan = null;
$publicLatestScanAssets = ['before' => null, 'after' => null];
$publicFeaturedInvestigations = [];
$indexInternalAds = [];
$publicEvidenceLabel = '';
try {
    $previewPdo = clickfix_open_db(true);
    $previewOverview = clickfix_analytics_overview($previewPdo, 30);
    if (is_array($previewOverview['latest_scan'] ?? null)) {
        $publicLatestScan = $previewOverview['latest_scan'];
    }
    if (is_array($previewOverview['latest_scan_assets'] ?? null)) {
        $publicLatestScanAssets = $previewOverview['latest_scan_assets'];
    }
    $publicFeaturedInvestigations = clickfix_featured_home_investigations($previewPdo, 3, true);
    if (is_array($publicLatestScan) && !empty($publicLatestScan['hostname'])) {
        $publicEvidenceLabel = 'scan #' . (int) ($publicLatestScan['id'] ?? 0) . ' | ' . (string) ($publicLatestScan['hostname'] ?? '-');
    } else {
        foreach ($publicFeaturedInvestigations as $featuredInvestigation) {
            $featuredAssets = is_array($featuredInvestigation['scan_assets'] ?? null) ? $featuredInvestigation['scan_assets'] : ['before' => null, 'after' => null];
            if (empty($featuredAssets['before']) && empty($featuredAssets['after'])) {
                continue;
            }
            $publicLatestScan = [
                'id' => (int) ($featuredInvestigation['source_report_id'] ?? 0),
                'hostname' => (string) ($featuredInvestigation['site_domain'] ?? '-'),
                'title' => (string) ($featuredInvestigation['title'] ?? $featuredInvestigation['site_domain'] ?? 'Investigation'),
            ];
            $publicLatestScanAssets = $featuredAssets;
            $publicEvidenceLabel = index_static_text('public_evidence_featured', $initialLang) . ' | ' . (string) ($featuredInvestigation['site_domain'] ?? '-');
            break;
        }
    }
    $indexInternalAds = clickfix_internal_ads_for_context($previewPdo, 'index', $indexViewerRole, 4);
} catch (Throwable $exception) {
    $publicLatestScan = null;
    $publicLatestScanAssets = ['before' => null, 'after' => null];
    $publicFeaturedInvestigations = [];
    $indexInternalAds = [];
    $publicEvidenceLabel = index_static_text('public_evidence_none', $initialLang);
}
$publicEvidenceLabel = $publicEvidenceLabel !== '' ? $publicEvidenceLabel : index_static_text('public_evidence_none', $initialLang);
$indexShowInternalAds = $indexAdsAudienceEnabled && !empty($indexInternalAds);
$indexShowMonetizationAds = $indexAdsAudienceEnabled && !empty($monetization['show_ads']);

foreach ($publicFeaturedInvestigations as &$publicFeaturedInvestigation) {
    $graph = is_array($publicFeaturedInvestigation['graph'] ?? null) ? $publicFeaturedInvestigation['graph'] : ['nodes' => [], 'edges' => []];
    $nodes = is_array($graph['nodes'] ?? null) ? $graph['nodes'] : [];
    $edges = is_array($graph['edges'] ?? null) ? $graph['edges'] : [];
    $tags = is_array($publicFeaturedInvestigation['tags'] ?? null) ? $publicFeaturedInvestigation['tags'] : [];
    $scanAssets = is_array($publicFeaturedInvestigation['scan_assets'] ?? null) ? $publicFeaturedInvestigation['scan_assets'] : ['before' => null, 'after' => null];

    $iocCounts = [
        'domains' => 0,
        'ips' => 0,
        'urls' => 0,
        'commands' => 0,
    ];
    foreach ($nodes as $node) {
        $type = strtolower(trim((string) ($node['type'] ?? '')));
        $label = trim((string) ($node['label'] ?? ''));
        if ($label === '' && $type === '') {
            continue;
        }
        if ($type !== '' && strpos($type, 'ip') !== false) {
            $iocCounts['ips']++;
            continue;
        }
        if ($label !== '' && filter_var($label, FILTER_VALIDATE_IP)) {
            $iocCounts['ips']++;
            continue;
        }
        if (($type !== '' && strpos($type, 'url') !== false) || preg_match('~https?://~i', $label)) {
            $iocCounts['urls']++;
            continue;
        }
        if (($type !== '' && strpos($type, 'domain') !== false) || preg_match('/(?:[a-z0-9-]+\.)+[a-z]{2,}$/i', $label)) {
            $iocCounts['domains']++;
            continue;
        }
        if (($type !== '' && strpos($type, 'command') !== false) || preg_match('/powershell|cmd(?:\.exe)?|bash|curl|wget|mshta|rundll32/i', $label)) {
            $iocCounts['commands']++;
        }
    }

    $evidenceCount = (!empty($scanAssets['before']) ? 1 : 0) + (!empty($scanAssets['after']) ? 1 : 0);
    $nodeCount = count($nodes);
    $edgeCount = count($edges);
    $tagCount = count($tags);
    $iocSpread = $iocCounts['domains'] + $iocCounts['ips'] + $iocCounts['urls'] + $iocCounts['commands'];
    $graphScore = min(100, (int) round(($nodeCount * 9) + ($edgeCount * 6)));
    $iocScore = min(100, (int) round(($iocSpread * 14) + ($tagCount * 4)));
    $evidenceScore = min(100, $evidenceCount * 50);
    $updatedAtRaw = trim((string) ($publicFeaturedInvestigation['updated_at'] ?? ''));
    $freshnessScore = 58;
    if ($updatedAtRaw !== '') {
        $updatedTs = strtotime($updatedAtRaw);
        if ($updatedTs !== false) {
            $ageHours = max(0, (time() - $updatedTs) / 3600);
            $freshnessScore = (int) max(18, min(100, round(100 - min(84, $ageHours) * 0.95)));
        }
    }
    $headlineScore = (int) round(($graphScore + $iocScore + $evidenceScore + $freshnessScore) / 4);
    $summaryRaw = trim((string) ($publicFeaturedInvestigation['summary'] ?? ''));
    $summaryExcerpt = $summaryRaw;
    if ($summaryExcerpt !== '') {
        if (function_exists('mb_substr')) {
            $summaryExcerpt = mb_substr($summaryExcerpt, 0, 320);
        } else {
            $summaryExcerpt = substr($summaryExcerpt, 0, 320);
        }
    }

    $publicFeaturedInvestigation['home_showcase'] = [
        'score' => $headlineScore,
        'graph_score' => $graphScore,
        'ioc_score' => $iocScore,
        'evidence_score' => $evidenceScore,
        'freshness_score' => $freshnessScore,
        'nodes' => $nodeCount,
        'edges' => $edgeCount,
        'tags' => $tagCount,
        'iocs' => $iocSpread,
        'evidence_count' => $evidenceCount,
        'updated_label' => $updatedAtRaw !== '' ? date('Y-m-d H:i', strtotime($updatedAtRaw) ?: time()) . ' UTC' : 'UTC',
        'summary_excerpt' => $summaryExcerpt,
        'chart' => [
            'keys' => ['featured_case_graph', 'featured_case_iocs', 'featured_case_evidence', 'featured_case_freshness'],
            'values' => [$graphScore, $iocScore, $evidenceScore, $freshnessScore],
        ],
        'bars' => [
            ['key' => 'graph', 'label' => 'Graph', 'value' => $graphScore, 'tone' => 'graph'],
            ['key' => 'iocs', 'label' => 'IOCs', 'value' => $iocScore, 'tone' => 'ioc'],
            ['key' => 'evidence', 'label' => 'Evidence', 'value' => $evidenceScore, 'tone' => 'evidence'],
            ['key' => 'freshness', 'label' => 'Freshness', 'value' => $freshnessScore, 'tone' => 'freshness'],
        ],
    ];
}
unset($publicFeaturedInvestigation);

$seoDefaultTitle = 'ClickFix Mitigator | Defense-first anti ClickFix';
$seoDefaultDescription = 'ClickFix Mitigator: defense-first extension to stop ClickFix. Detects, disrupts, and logs social engineering command execution attempts.';
$seoKeywords = 'ClickFix, browser security, SOC, phishing defense, clipboard protection, social engineering, extension security';
$githubRepoUrl = 'https://github.com/j0rd1s3rr4n0/ClickFixMitigator';
$seoLangs = ['ar', 'ca', 'de', 'en', 'es', 'fr', 'he', 'hi', 'it', 'ja', 'ko', 'nl', 'pt', 'ru', 'zh'];
$seoLocaleByLang = [
    'ar' => 'ar_AR',
    'ca' => 'ca_ES',
    'de' => 'de_DE',
    'en' => 'en_US',
    'es' => 'es_ES',
    'fr' => 'fr_FR',
    'it' => 'it_IT',
    'he' => 'he_IL',
    'hi' => 'hi_IN',
    'ja' => 'ja_JP',
    'ko' => 'ko_KR',
    'nl' => 'nl_NL',
    'pt' => 'pt_PT',
    'ru' => 'ru_RU',
    'zh' => 'zh_CN',
];
$clientGeoPublicContext = buildPublicClientGeoContext($clientGeoContext, $initialLang);
$initialDir = in_array($initialLang, ['ar', 'he'], true) ? 'rtl' : 'ltr';
$initialSeoLocale = $seoLocaleByLang[$initialLang] ?? 'en_US';
$heroWorldGeoBootstrap = null;
$heroWorldGeoPath = __DIR__ . '/assets/vendor/leaflet/data/world-countries.geo.json';
if (is_file($heroWorldGeoPath)) {
    $heroWorldGeoRaw = @file_get_contents($heroWorldGeoPath);
    if (is_string($heroWorldGeoRaw) && $heroWorldGeoRaw !== '') {
        $heroWorldDecoded = json_decode($heroWorldGeoRaw, true);
        if (is_array($heroWorldDecoded) && !empty($heroWorldDecoded['features']) && is_array($heroWorldDecoded['features'])) {
            $heroWorldGeoBootstrap = $heroWorldDecoded;
        }
    }
}

if (isset($previewPdo) && $previewPdo instanceof PDO) {
    try {
        clickfix_log_public_page_hit($previewPdo, [
            'path' => (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/index.php'), PHP_URL_PATH) ?: '/index.php'),
            'lang' => $initialLang,
            'ip' => getClientIpAddress(),
            'country_code' => (string) ($clientGeoPublicContext['country_code'] ?? ''),
            'country_name' => (string) ($clientGeoPublicContext['country'] ?? ''),
            'region' => (string) ($clientGeoPublicContext['region'] ?? ''),
            'region_name' => (string) ($clientGeoPublicContext['region_name'] ?? ''),
            'city' => (string) ($clientGeoPublicContext['city'] ?? ''),
            'timezone' => (string) ($clientGeoPublicContext['timezone'] ?? ''),
            'isp' => (string) ($clientGeoPublicContext['isp'] ?? ''),
            'org' => (string) ($clientGeoPublicContext['org'] ?? ''),
            'asn' => (string) ($clientGeoPublicContext['as'] ?? ''),
            'asname' => (string) ($clientGeoPublicContext['asname'] ?? ''),
            'referrer_url' => (string) ($_SERVER['HTTP_REFERER'] ?? ''),
            'user_agent' => (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''),
            'mobile' => !empty($clientGeoPublicContext['mobile']),
            'proxy' => !empty($clientGeoPublicContext['proxy']),
            'hosting' => !empty($clientGeoPublicContext['hosting']),
            'lat' => $clientGeoPublicContext['lat'] ?? null,
            'lon' => $clientGeoPublicContext['lon'] ?? null,
        ], 1800);
    } catch (Throwable $exception) {
        // Keep public landing resilient if tracking insert fails.
    }
}

$isHttpsRequest = !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';
$hostRaw = trim((string) ($_SERVER['HTTP_HOST'] ?? 'clickfix.jordiserrano.me'));
$host = preg_replace('/[^a-zA-Z0-9.\-:]/', '', $hostRaw) ?: 'clickfix.jordiserrano.me';
$requestPath = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/index.php'), PHP_URL_PATH) ?: '/index.php');
$requestDir = str_replace('\\', '/', dirname($requestPath));
if ($requestDir === '.' || $requestDir === '/') {
    $requestDir = '';
}
$detectedBaseUrl = ($isHttpsRequest ? 'https://' : 'http://') . $host . $requestDir;
$configuredBaseUrl = trim((string) clickfix_env('CLICKFIX_PUBLIC_BASE_URL', ''));
$siteBaseUrl = rtrim($configuredBaseUrl !== '' ? $configuredBaseUrl : $detectedBaseUrl, '/');
if ($siteBaseUrl === '') {
    $siteBaseUrl = 'https://clickfix.jordiserrano.me';
}
$canonicalUrl = $siteBaseUrl . '/index.php';
$ogImageUrl = $siteBaseUrl . '/favicon.ico';

$websiteSchema = [
    '@context' => 'https://schema.org',
    '@type' => 'WebSite',
    'name' => 'ClickFix Mitigator',
    'url' => $siteBaseUrl . '/',
    'inLanguage' => 'en',
    'description' => $seoDefaultDescription,
];
$organizationSchema = [
    '@context' => 'https://schema.org',
    '@type' => 'Organization',
    'name' => 'ClickFix Mitigator',
    'url' => $siteBaseUrl . '/',
    'logo' => $ogImageUrl,
    'sameAs' => [$githubRepoUrl],
];
$softwareSchema = [
    '@context' => 'https://schema.org',
    '@type' => 'SoftwareApplication',
    'name' => 'ClickFix Mitigator',
    'applicationCategory' => 'SecurityApplication',
    'operatingSystem' => 'Chrome',
    'description' => $seoDefaultDescription,
    'url' => $siteBaseUrl . '/',
    'offers' => [
        '@type' => 'Offer',
        'price' => '0',
        'priceCurrency' => 'USD',
    ],
];
$sourceCodeSchema = [
    '@context' => 'https://schema.org',
    '@type' => 'SoftwareSourceCode',
    'name' => 'ClickFixMitigator',
    'codeRepository' => $githubRepoUrl,
    'url' => $githubRepoUrl,
    'runtimePlatform' => 'PHP, JavaScript',
];
?>
<!doctype html>
<html lang="<?= htmlspecialchars($initialLang, ENT_QUOTES, 'UTF-8'); ?>" dir="<?= htmlspecialchars($initialDir, ENT_QUOTES, 'UTF-8'); ?>">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title data-i18n="title"><?= htmlspecialchars($seoDefaultTitle, ENT_QUOTES, 'UTF-8'); ?></title>
  <meta name="description" content="<?= htmlspecialchars($seoDefaultDescription, ENT_QUOTES, 'UTF-8'); ?>" data-i18n="description" />
  <meta name="keywords" content="<?= htmlspecialchars($seoKeywords, ENT_QUOTES, 'UTF-8'); ?>" />
  <meta name="robots" content="index,follow,max-image-preview:large,max-snippet:-1,max-video-preview:-1" />
  <meta name="googlebot" content="index,follow,max-image-preview:large,max-snippet:-1,max-video-preview:-1" />
  <meta name="author" content="j0rd1s3rr4n0" />
  <meta name="application-name" content="ClickFix Mitigator" />
  <meta name="theme-color" content="#05070b" />
  <meta name="referrer" content="strict-origin-when-cross-origin" />

  <link rel="canonical" href="<?= htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8'); ?>" />
  <link rel="icon" type="image/x-icon" href="favicon.ico" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" />
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" media="print" onload="this.media='all'" />
  <noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" /></noscript>
  <link rel="stylesheet" href="assets/vendor/leaflet/leaflet.css" />
<?php foreach ($seoLangs as $seoLang): ?>
  <link rel="alternate" hreflang="<?= htmlspecialchars($seoLang, ENT_QUOTES, 'UTF-8'); ?>" href="<?= htmlspecialchars($canonicalUrl . '?lang=' . $seoLang, ENT_QUOTES, 'UTF-8'); ?>" />
<?php endforeach; ?>
  <link rel="alternate" hreflang="x-default" href="<?= htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8'); ?>" />

  <meta property="og:type" content="website" />
  <meta property="og:site_name" content="ClickFix Mitigator" />
  <meta property="og:title" content="<?= htmlspecialchars($seoDefaultTitle, ENT_QUOTES, 'UTF-8'); ?>" />
  <meta property="og:description" content="<?= htmlspecialchars($seoDefaultDescription, ENT_QUOTES, 'UTF-8'); ?>" />
  <meta property="og:url" content="<?= htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8'); ?>" />
  <meta property="og:image" content="<?= htmlspecialchars($ogImageUrl, ENT_QUOTES, 'UTF-8'); ?>" />
  <meta property="og:image:alt" content="ClickFix Mitigator" />
  <meta property="og:locale" content="<?= htmlspecialchars($initialSeoLocale, ENT_QUOTES, 'UTF-8'); ?>" />
<?php foreach ($seoLocaleByLang as $seoLocale): ?>
  <?php if ($seoLocale !== 'en_US'): ?>
  <meta property="og:locale:alternate" content="<?= htmlspecialchars($seoLocale, ENT_QUOTES, 'UTF-8'); ?>" />
  <?php endif; ?>
<?php endforeach; ?>

  <meta name="twitter:card" content="summary_large_image" />
  <meta name="twitter:title" content="<?= htmlspecialchars($seoDefaultTitle, ENT_QUOTES, 'UTF-8'); ?>" />
  <meta name="twitter:description" content="<?= htmlspecialchars($seoDefaultDescription, ENT_QUOTES, 'UTF-8'); ?>" />
  <meta name="twitter:image" content="<?= htmlspecialchars($ogImageUrl, ENT_QUOTES, 'UTF-8'); ?>" />

  <script type="application/ld+json" id="seo-website-jsonld"><?= json_encode($websiteSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?></script>
  <script type="application/ld+json"><?= json_encode($organizationSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?></script>
  <script type="application/ld+json"><?= json_encode($softwareSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?></script>
  <script type="application/ld+json"><?= json_encode($sourceCodeSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?></script>
  <style>
    :root {
      color-scheme: dark;
      --bg: #081019;
      --bg-2: #0f1a24;
      --bg-3: #131f2c;
      --ink: #edf5f7;
      --muted: #92a6b2;
      --accent: #51d3b7;
      --accent-2: #46baf1;
      --accent-3: #d7ea7f;
      --stroke: rgba(121, 145, 167, 0.18);
      --stroke-strong: rgba(70, 186, 241, 0.3);
      --glass: rgba(10, 16, 24, 0.82);
      --glass-strong: rgba(12, 19, 28, 0.92);
      --panel: rgba(13, 20, 30, 0.94);
      --shadow: 0 24px 60px rgba(3, 8, 15, 0.42);
      --shadow-soft: 0 18px 36px rgba(3, 8, 15, 0.24);
      --glow: 0 0 0 rgba(0, 0, 0, 0);
    }

    * { box-sizing: border-box; }
    html {
      -webkit-text-size-adjust: 100%;
    }
    body {
      margin: 0;
      min-height: 100vh;
      font-family: 'JetBrains Mono', 'Consolas', monospace;
      color: var(--ink);
      background:
        radial-gradient(circle at 12% 18%, rgba(70, 186, 241, 0.14), transparent 32%),
        radial-gradient(circle at 88% 16%, rgba(81, 211, 183, 0.12), transparent 30%),
        radial-gradient(circle at 52% 100%, rgba(215, 234, 127, 0.06), transparent 30%),
        linear-gradient(180deg, rgba(255, 255, 255, 0.028) 0 1px, transparent 1px),
        linear-gradient(90deg, rgba(255, 255, 255, 0.022) 0 1px, transparent 1px),
        linear-gradient(160deg, var(--bg), var(--bg-2) 46%, #172433 100%);
      background-size: auto, auto, auto, 100% 112px, 112px 100%, auto;
      position: relative;
      overflow-x: hidden;
    }

    body::before,
    body::after {
      content: "";
      position: fixed;
      width: 460px;
      height: 460px;
      border-radius: 999px;
      background: radial-gradient(circle, rgba(70, 186, 241, 0.14), transparent 68%);
      filter: blur(72px);
      opacity: 0.44;
      z-index: 0;
      animation: drift 24s ease-in-out infinite;
      pointer-events: none;
    }

    body::after {
      background: radial-gradient(circle, rgba(81, 211, 183, 0.14), transparent 68%);
      inset: auto -15% -25% auto;
      animation-delay: -8s;
    }

    @keyframes drift {
      0%, 100% { transform: translate3d(0, 0, 0) scale(1); }
      50% { transform: translate3d(40px, -20px, 0) scale(1.05); }
    }

    a { color: inherit; text-decoration: none; }

    .page {
      position: relative;
      z-index: 1;
      display: flex;
      flex-direction: column;
      gap: 88px;
      width: min(1680px, 100%);
      padding:
        calc(32px + env(safe-area-inset-top))
        max(16px, env(safe-area-inset-right), clamp(16px, 4vw, 72px))
        calc(160px + env(safe-area-inset-bottom))
        max(16px, env(safe-area-inset-left), clamp(16px, 4vw, 72px));
      margin: 0 auto;
    }

    .nav {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 20px;
      padding: 14px 18px;
      border-radius: 22px;
      background: linear-gradient(180deg, rgba(12, 18, 28, 0.92), rgba(10, 16, 24, 0.88));
      border: 1px solid rgba(121, 145, 167, 0.16);
      box-shadow: var(--shadow-soft);
      backdrop-filter: blur(16px);
      position: sticky;
      top: 16px;
      z-index: 2;
    }

    .brand {
      display: flex;
      align-items: center;
      gap: 16px;
      font-family: 'Sora', sans-serif;
      letter-spacing: 0.4px;
      min-width: 0;
    }

    .brand-copy {
      min-width: 0;
      display: grid;
      gap: 4px;
    }

    .brand-title {
      font-family: 'Sora', sans-serif;
      font-size: 20px;
      font-weight: 600;
      line-height: 1.06;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      letter-spacing: -0.03em;
    }

    .brand-mark {
      width: 50px;
      height: 50px;
      border-radius: 16px;
      background: linear-gradient(180deg, rgba(15, 23, 33, 0.96), rgba(9, 16, 24, 0.96));
      border: 1px solid rgba(70, 186, 241, 0.24);
      box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.04);
      display: grid;
      place-items: center;
      padding: 6px;
    }

    .brand-mark img {
      width: 100%;
      height: 100%;
      object-fit: contain;
      filter: drop-shadow(0 4px 10px rgba(70, 186, 241, 0.2));
    }

    .lang {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
      align-items: center;
    }

    .lang-label {
      font-size: 11px;
      text-transform: uppercase;
      letter-spacing: 0.2em;
      color: var(--muted);
    }

    .lang select {
      background: rgba(11, 17, 25, 0.92);
      color: var(--ink);
      border: 1px solid rgba(121, 145, 167, 0.2);
      border-radius: 12px;
      padding: 8px 12px;
      font-family: 'JetBrains Mono', monospace;
      font-size: 12px;
      cursor: pointer;
      outline: none;
      box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.02);
    }

    .lang-fab {
      position: fixed;
      top: calc(14px + env(safe-area-inset-top));
      right: max(12px, env(safe-area-inset-right));
      z-index: 6;
      display: none;
      align-items: flex-end;
      flex-direction: column;
      gap: 8px;
    }

    .lang-fab-button {
      width: 42px;
      height: 42px;
      border-radius: 999px;
      border: 1px solid rgba(121, 145, 167, 0.22);
      background: rgba(11, 17, 25, 0.92);
      box-shadow: 0 10px 28px rgba(3, 10, 30, 0.22);
      backdrop-filter: blur(12px);
      color: var(--ink);
      font: 700 11px 'JetBrains Mono', monospace;
      letter-spacing: 0.08em;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 0;
    }

    .lang-fab-panel {
      display: none;
      min-width: 132px;
      padding: 10px;
      border-radius: 16px;
      border: 1px solid rgba(121, 145, 167, 0.2);
      background: rgba(11, 17, 25, 0.96);
      box-shadow: 0 14px 30px rgba(3, 10, 30, 0.26);
      backdrop-filter: blur(12px);
    }

    .lang-fab.is-open .lang-fab-panel {
      display: block;
    }

    .lang-fab-panel select {
      width: 100%;
      font-size: 16px;
      background: rgba(4, 10, 16, 0.88);
    }

    .hero {
      display: grid;
      gap: 28px;
      position: relative;
    }

    .hero-grid {
      display: grid;
      gap: 40px;
      grid-template-columns: minmax(0, 1.04fr) minmax(420px, 0.96fr);
      align-items: start;
    }

    .hero-copy {
      display: grid;
      gap: 20px;
      align-content: start;
      min-height: 100%;
      padding: 28px 32px 32px;
      border-radius: 28px;
      border: 1px solid rgba(121, 145, 167, 0.12);
      background:
        linear-gradient(180deg, rgba(15, 22, 31, 0.48), rgba(8, 13, 20, 0)),
        linear-gradient(135deg, rgba(70, 186, 241, 0.03), rgba(81, 211, 183, 0.015));
      box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.025);
      position: relative;
      overflow: hidden;
    }

    .hero-copy::before {
      content: "";
      position: absolute;
      top: 28px;
      left: 0;
      width: 4px;
      height: 112px;
      border-radius: 0 999px 999px 0;
      background: linear-gradient(180deg, var(--accent-2), rgba(81, 211, 183, 0.85));
      box-shadow: 0 0 22px rgba(70, 186, 241, 0.22);
    }

    .hero-copy::after {
      content: "";
      position: absolute;
      inset: auto -8% -22% auto;
      width: 280px;
      height: 280px;
      border-radius: 999px;
      background: radial-gradient(circle, rgba(70, 186, 241, 0.08), transparent 68%);
      pointer-events: none;
    }

    .eyebrow {
      text-transform: uppercase;
      font-family: 'Sora', sans-serif;
      letter-spacing: 0.22em;
      color: #7fd5f8;
      font-size: 11px;
      font-weight: 600;
    }

    h1 {
      font-family: 'Sora', sans-serif;
      font-size: clamp(40px, 5.4vw, 78px);
      line-height: 0.96;
      letter-spacing: -0.055em;
      margin: 0;
      max-width: 12ch;
      text-wrap: balance;
    }

    .hero p {
      font-size: clamp(17px, 1.8vw, 22px);
      color: var(--muted);
      margin: 0;
      max-width: 690px;
      line-height: 1.62;
    }

    .hero-tags {
      margin-top: 6px;
      gap: 12px;
    }

    .cta {
      display: flex;
      gap: 14px;
      flex-wrap: wrap;
      align-items: center;
    }

    .cta-note {
      font-family: 'Sora', sans-serif;
      font-size: 13px;
      color: var(--muted);
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      align-items: center;
    }

    .cta-note a {
      color: var(--accent-2);
      text-decoration: underline;
      text-underline-offset: 3px;
    }

    .button {
      padding: 13px 22px;
      border-radius: 999px;
      font-family: 'Sora', sans-serif;
      font-weight: 600;
      letter-spacing: 0.01em;
      border: 1px solid transparent;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease, background 0.18s ease;
      min-height: 44px;
    }

    .button.primary {
      background: linear-gradient(135deg, rgba(70, 186, 241, 0.96), rgba(81, 211, 183, 0.96));
      color: #061018;
      box-shadow: 0 12px 28px rgba(70, 186, 241, 0.18);
    }

    .button.secondary {
      background: rgba(12, 18, 27, 0.84);
      border-color: rgba(121, 145, 167, 0.22);
      color: var(--ink);
    }

    .button:hover {
      transform: translateY(-1px);
      box-shadow: 0 16px 34px rgba(3, 10, 30, 0.22);
    }

    .button.cta-pulse {
      animation: none;
    }

    .grid {
      display: grid;
      gap: 22px;
    }

    .grid.two { grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); }
    .grid.three { grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); }

    .card {
      background: linear-gradient(180deg, rgba(12, 18, 26, 0.9), rgba(10, 16, 24, 0.84));
      border: 1px solid rgba(121, 145, 167, 0.16);
      border-radius: 26px;
      padding: 28px;
      box-shadow: var(--shadow);
      backdrop-filter: blur(16px);
    }

    .card h3 {
      font-family: 'Sora', sans-serif;
      margin: 0 0 10px;
      font-size: 20px;
    }

    .card p {
      color: var(--muted);
      margin: 0;
      line-height: 1.6;
    }

    .tags {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
    }

    .tag {
      padding: 8px 12px;
      border-radius: 999px;
      border: 1px solid rgba(121, 145, 167, 0.16);
      color: #a6b7c1;
      font-family: 'JetBrains Mono', monospace;
      font-size: 11px;
      background: rgba(11, 17, 25, 0.68);
    }

    .timeline {
      display: grid;
      gap: 18px;
    }

    .step {
      display: grid;
      grid-template-columns: auto 1fr;
      gap: 14px;
      align-items: start;
    }

    .step-number {
      width: 34px;
      height: 34px;
      border-radius: 12px;
      background: rgba(24, 229, 255, 0.12);
      border: 1px solid rgba(24, 229, 255, 0.35);
      display: grid;
      place-items: center;
      font-family: 'Sora', sans-serif;
      font-weight: 600;
      color: var(--accent-3);
    }

    .section-title {
      font-family: 'Sora', sans-serif;
      font-size: clamp(28px, 3vw, 42px);
      margin: 0 0 10px;
      letter-spacing: -0.04em;
      line-height: 1.02;
      max-width: 18ch;
    }

    .section-sub {
      color: var(--muted);
      margin: 0 0 24px;
      max-width: 760px;
      line-height: 1.7;
    }

    .pill {
      display: inline-flex;
      align-items: center;
      gap: 10px;
      border-radius: 999px;
      padding: 8px 14px;
      background: rgba(11, 17, 25, 0.72);
      border: 1px solid rgba(121, 145, 167, 0.18);
      font-family: 'JetBrains Mono', monospace;
      font-size: 11px;
      letter-spacing: 0.6px;
      text-transform: uppercase;
      color: #a9dcf3;
    }

    .hero-panel {
      background: linear-gradient(180deg, rgba(11, 17, 25, 0.95), rgba(9, 14, 21, 0.94));
      border: 1px solid rgba(121, 145, 167, 0.18);
      border-radius: 28px;
      padding: 22px;
      box-shadow: 0 34px 70px rgba(2, 6, 12, 0.48);
      display: grid;
      gap: 14px;
      position: relative;
      overflow: hidden;
    }

    .hero-panel::before {
      content: "";
      position: absolute;
      left: 22px;
      right: 22px;
      top: 0;
      height: 1px;
      background: linear-gradient(90deg, rgba(70, 186, 241, 0.9), rgba(81, 211, 183, 0.28), rgba(0, 0, 0, 0));
      opacity: 0.9;
    }

    .hero-panel::after {
      content: "";
      position: absolute;
      inset: 0;
      background:
        radial-gradient(circle at 100% 0, rgba(70, 186, 241, 0.08), transparent 26%),
        linear-gradient(180deg, rgba(255, 255, 255, 0.02), transparent 24%);
      pointer-events: none;
    }

    .hero-panel-header {
      display: inline-flex;
      align-items: center;
      gap: 10px;
      font-family: 'JetBrains Mono', monospace;
      font-size: 11px;
      text-transform: uppercase;
      letter-spacing: 0.2em;
      color: var(--muted);
    }

    .status-dot {
      width: 9px;
      height: 9px;
      border-radius: 999px;
      background: var(--accent);
      box-shadow: 0 0 0 6px rgba(81, 211, 183, 0.08);
    }

    .hero-panel-grid {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 12px;
    }

    .hero-metric {
      padding: 14px;
      border-radius: 16px;
      border: 1px solid rgba(121, 145, 167, 0.14);
      background: linear-gradient(180deg, rgba(18, 27, 39, 0.72), rgba(12, 18, 27, 0.82));
      display: grid;
      gap: 8px;
      min-height: 92px;
      align-content: space-between;
      box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.02);
    }

    .hero-metric span {
      font-size: 11px;
      text-transform: uppercase;
      letter-spacing: 0.16em;
      color: var(--muted);
    }

    .hero-metric strong {
      font-family: 'Sora', sans-serif;
      font-size: clamp(20px, 2vw, 28px);
      letter-spacing: -0.04em;
      color: var(--ink);
    }

    .hero-geo-card {
      justify-self: end;
      width: min(100%, 420px);
      padding: 10px 12px;
      border-radius: 16px;
      border: 1px solid rgba(121, 145, 167, 0.12);
      background: rgba(9, 15, 22, 0.7);
      display: grid;
      gap: 8px;
      opacity: 0.72;
    }

    .hero-geo-card-head {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
    }

    .hero-geo-card-copy {
      display: grid;
      gap: 1px;
      min-width: 0;
    }

    .hero-geo-eyebrow,
    .hero-geo-item span {
      font: 9px 'JetBrains Mono', monospace;
      text-transform: uppercase;
      letter-spacing: 0.16em;
      color: var(--muted);
    }

    .hero-geo-card-copy strong,
    .hero-geo-item strong {
      color: var(--ink);
      font-family: 'Sora', sans-serif;
      font-size: 12px;
      line-height: 1.2;
      word-break: break-word;
    }

    .hero-geo-lang {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-width: 24px;
      padding: 2px 5px;
      border-radius: 999px;
      border: 1px solid rgba(24, 229, 255, 0.25);
      background: rgba(6, 16, 24, 0.76);
      color: #b9f3ff;
      font: 600 7px 'JetBrains Mono', monospace;
      letter-spacing: 0.06em;
      text-transform: uppercase;
    }

    .hero-geo-summary {
      margin: 0;
      color: #9fb9c8;
      font-size: 10px;
      line-height: 1.25;
    }

    @media (min-width: 721px) {
      .hero-geo-summary,
      .hero-geo-item:nth-child(n+3),
      .hero-geo-tags {
        display: none;
      }
    }

    .hero-geo-grid {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 6px;
    }

    .hero-geo-item {
      padding: 7px 8px;
      border-radius: 10px;
      border: 1px solid rgba(121, 145, 167, 0.12);
      background: rgba(14, 21, 30, 0.7);
      display: grid;
      gap: 3px;
      min-width: 0;
    }

    .hero-geo-item span {
      font-size: 8px;
      letter-spacing: 0.08em;
    }

    .hero-geo-item strong {
      font-size: 10px;
    }

    .hero-geo-tags {
      display: flex;
      flex-wrap: wrap;
      gap: 4px;
    }

    .hero-geo-tag {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      padding: 1px 5px;
      border-radius: 999px;
      border: 1px solid rgba(24, 229, 255, 0.18);
      background: rgba(9, 18, 26, 0.74);
      color: #bfefff;
      font: 7px 'JetBrains Mono', monospace;
      letter-spacing: 0.04em;
      text-transform: uppercase;
    }

    .hero-geo-tag.is-warn {
      border-color: rgba(255, 185, 92, 0.28);
      color: #ffd5a8;
    }

    .hero-flow {
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 10px;
    }

    .hero-flow-step {
      display: grid;
      grid-template-columns: minmax(0, 1fr) auto;
      align-items: center;
      gap: 12px;
      padding: 12px 14px;
      border-radius: 16px;
      background: rgba(13, 20, 29, 0.82);
      border: 1px solid rgba(121, 145, 167, 0.14);
      font-size: 13px;
      color: var(--muted);
      min-height: 72px;
    }

    .hero-flow-step span {
      line-height: 1.45;
    }

    .hero-flow-step strong {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-width: 34px;
      height: 34px;
      padding: 0 10px;
      border-radius: 999px;
      background: rgba(70, 186, 241, 0.1);
      border: 1px solid rgba(70, 186, 241, 0.18);
      color: #d9f4ff;
      font: 700 11px 'Sora', sans-serif;
      letter-spacing: 0.08em;
    }

    .surveillance-hud {
      display: grid;
      gap: 12px;
      grid-template-columns: minmax(0, 1fr);
      padding: 10px;
      border-radius: 20px;
      border: 1px solid rgba(121, 145, 167, 0.16);
      background: linear-gradient(180deg, rgba(10, 16, 24, 0.84), rgba(8, 14, 20, 0.8));
      position: relative;
      overflow: hidden;
    }

    .surveillance-radar {
      position: relative;
      border-radius: 20px;
      overflow: hidden;
      border: 1px solid rgba(70, 186, 241, 0.22);
      background: linear-gradient(180deg, rgba(12, 25, 39, 0.9), rgba(7, 12, 19, 0.98));
      min-height: 500px;
      display: grid;
      place-items: center;
      box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.02);
    }

    .surveillance-radar::before {
      content: "";
      position: absolute;
      inset: 0;
      background:
        radial-gradient(circle at 25% 12%, rgba(70, 186, 241, 0.12), transparent 34%),
        linear-gradient(180deg, rgba(255, 255, 255, 0.03) 0 1px, transparent 1px),
        linear-gradient(90deg, rgba(255, 255, 255, 0.03) 0 1px, transparent 1px);
      background-size: auto, 100% 16px, 16px 100%;
      opacity: 0.48;
      pointer-events: none;
    }

    .hero-leaflet-map {
      width: 100%;
      height: 500px;
      display: block;
      position: relative;
      z-index: 1;
      cursor: grab;
      touch-action: none;
    }

    .hero-leaflet-map.leaflet-container {
      background: linear-gradient(180deg, rgba(11, 38, 58, 0.92), rgba(5, 16, 26, 0.96));
      font-family: 'JetBrains Mono', 'Consolas', monospace;
      cursor: grab;
    }

    .hero-leaflet-map.leaflet-container:active {
      cursor: grabbing;
    }

    .hero-leaflet-map .leaflet-control-attribution,
    .hero-leaflet-map .leaflet-control-zoom {
      display: none !important;
    }

    .hero-leaflet-map .leaflet-popup-content-wrapper {
      background: rgba(6, 16, 24, 0.92);
      color: var(--ink);
      border: 1px solid rgba(24, 229, 255, 0.3);
      border-radius: 10px;
      box-shadow: 0 8px 22px rgba(0, 0, 0, 0.45);
    }

    .hero-leaflet-map .leaflet-popup-tip {
      background: rgba(6, 16, 24, 0.92);
    }

    .hero-detection-marker {
      background: transparent;
      border: 0;
    }

    .hero-detection-dot {
      display: inline-block;
      width: var(--dot-size, 7px);
      height: var(--dot-size, 7px);
      border-radius: 999px;
      background: radial-gradient(circle at 32% 28%, #d9fff5 0, #59f08d 35%, #12c8d5 100%);
      box-shadow: 0 0 0 1px rgba(11, 22, 32, 0.45), 0 0 8px rgba(89, 240, 141, 0.55);
      position: relative;
      animation: heroMarkerPulse 2.8s ease-out infinite;
      animation-delay: var(--pulse-delay, 0s);
    }

    .hero-detection-dot.hero-dot-domain {
      background: radial-gradient(circle at 32% 28%, #fff9d9 0, #f2ff7a 38%, #18e5ff 100%);
      box-shadow: 0 0 0 1px rgba(11, 22, 32, 0.45), 0 0 8px rgba(242, 255, 122, 0.55);
    }

    .hero-detection-dot.hero-dot-viewer {
      background: radial-gradient(circle at 32% 28%, #eef8ff 0, #8bd4f7 34%, #18e5ff 100%);
      box-shadow: 0 0 0 1px rgba(11, 22, 32, 0.45), 0 0 10px rgba(24, 229, 255, 0.65);
    }

    .hero-detection-dot::after {
      content: "";
      position: absolute;
      inset: -2px;
      border-radius: 999px;
      border: 1px solid rgba(89, 240, 141, 0.55);
      transform: scale(1);
      opacity: 0.75;
      animation: heroMarkerRing 2.8s ease-out infinite;
      animation-delay: var(--pulse-delay, 0s);
    }

    .hero-detection-dot.hero-dot-domain::after {
      border-color: rgba(242, 255, 122, 0.62);
    }

    .hero-detection-dot.hero-dot-viewer::after {
      border-color: rgba(139, 212, 247, 0.68);
    }

    .hero-map-badge {
      position: absolute;
      top: 16px;
      right: 16px;
      z-index: 2;
      display: grid;
      gap: 4px;
      min-width: 180px;
      max-width: min(66%, 260px);
      padding: 10px 12px;
      border-radius: 16px;
      border: 1px solid rgba(121, 145, 167, 0.18);
      background: rgba(8, 14, 20, 0.72);
      backdrop-filter: blur(12px);
      color: #d9faff;
      font: 11px 'JetBrains Mono', monospace;
      letter-spacing: 0.03em;
      pointer-events: none;
    }

    .hero-map-badge strong {
      font: 600 12px 'Sora', sans-serif;
      color: #ffffff;
    }

    .radar-caption {
      position: absolute;
      top: 16px;
      left: 16px;
      z-index: 2;
      font: 700 10px 'JetBrains Mono', monospace;
      color: #ecfbff;
      letter-spacing: 0.16em;
      text-transform: uppercase;
      background: rgba(8, 14, 20, 0.7);
      border: 1px solid rgba(121, 145, 167, 0.16);
      box-shadow: 0 10px 20px rgba(1, 8, 14, 0.18);
      padding: 7px 12px;
      border-radius: 999px;
      text-shadow: 0 1px 0 rgba(0, 0, 0, 0.35);
    }

    .stream-title {
      font: 600 10px 'JetBrains Mono', monospace;
      color: var(--muted);
      letter-spacing: 0.18em;
      text-transform: uppercase;
    }

    .stream-marquee {
      border-radius: 14px;
      border: 1px solid rgba(121, 145, 167, 0.14);
      background: rgba(9, 14, 20, 0.92);
      overflow: hidden;
      position: relative;
      min-height: 42px;
      display: flex;
      align-items: center;
    }

    .stream-track {
      white-space: nowrap;
      color: #8bd4f7;
      font: 12px 'JetBrains Mono', monospace;
      padding-left: 100%;
      animation: marquee 18s linear infinite;
      will-change: transform;
    }

    @keyframes marquee {
      from { transform: translateX(0); }
      to { transform: translateX(-100%); }
    }

    @keyframes mapGlowDrift {
      0%, 100% { transform: translate3d(0, 0, 0) scale(1); }
      50% { transform: translate3d(-12px, 8px, 0) scale(1.03); }
    }

    @keyframes heroMarkerPulse {
      0%, 100% { transform: scale(1); }
      45% { transform: scale(1.2); }
    }

    @keyframes heroMarkerRing {
      0% { transform: scale(1); opacity: 0.75; }
      100% { transform: scale(2.2); opacity: 0; }
    }

    .preview-section {
      display: grid;
      gap: 26px;
      position: relative;
      overflow: hidden;
    }

    .preview-header {
      display: grid;
      gap: 12px;
    }

    .preview-onboarding {
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 12px;
    }

    .quick-action-card {
      padding: 18px;
      border-radius: 20px;
      border: 1px solid rgba(121, 145, 167, 0.15);
      background: linear-gradient(180deg, rgba(14, 21, 31, 0.86), rgba(10, 16, 24, 0.9));
      display: grid;
      gap: 12px;
      position: relative;
      overflow: hidden;
    }

    .quick-action-card::before,
    .metric-card::before,
    .preview-spotlight::before,
    .chart-card::before,
    .preview-list::before,
    .preview-cta::before,
    .proof-card::before,
    .support-card::before {
      content: "";
      position: absolute;
      left: 18px;
      right: 18px;
      top: 0;
      height: 1px;
      background: linear-gradient(90deg, rgba(70, 186, 241, 0.8), rgba(81, 211, 183, 0.15), rgba(0, 0, 0, 0));
      pointer-events: none;
    }

    .quick-action-head {
      display: flex;
      align-items: center;
      gap: 10px;
      font-family: 'Sora', sans-serif;
      color: var(--ink);
      font-size: 15px;
      letter-spacing: -0.02em;
    }

    .quick-action-step {
      width: 26px;
      height: 26px;
      border-radius: 999px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: 12px;
      font-weight: 700;
      background: rgba(70, 186, 241, 0.12);
      border: 1px solid rgba(70, 186, 241, 0.18);
      color: #b9ebff;
      flex: 0 0 auto;
    }

    .quick-action-note {
      margin: 0;
      color: var(--muted);
      font-size: 13px;
      line-height: 1.6;
      min-height: 42px;
    }

    .quick-action-card .button {
      width: 100%;
      justify-content: center;
    }

    .demo-link-stack {
      margin-top: 8px;
      display: grid;
      gap: 8px;
    }

    .demo-link-stack .button {
      width: 100%;
      justify-content: center;
    }

    .preview-grid {
      display: grid;
      grid-template-columns: minmax(0, 1.15fr) minmax(260px, 0.85fr);
      gap: 18px;
      align-items: start;
    }

    .preview-left-column {
      display: grid;
      gap: 12px;
      align-content: start;
    }

    .preview-metrics {
      display: grid;
      gap: 12px;
      grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    }

    .metric-card {
      padding: 18px;
      border-radius: 20px;
      border: 1px solid rgba(121, 145, 167, 0.14);
      background: linear-gradient(180deg, rgba(14, 20, 29, 0.84), rgba(10, 15, 22, 0.92));
      display: grid;
      gap: 10px;
      min-height: 118px;
      position: relative;
      overflow: hidden;
    }

    .metric-label {
      font-size: 11px;
      text-transform: uppercase;
      letter-spacing: 0.14em;
      color: var(--muted);
      font-family: 'Sora', sans-serif;
    }

    .metric-value {
      font-family: 'Sora', sans-serif;
      font-size: clamp(28px, 2.5vw, 38px);
      color: var(--ink);
      letter-spacing: -0.05em;
    }

    .metric-foot {
      font-size: 12px;
      color: var(--muted);
    }

    .preview-charts {
      display: grid;
      gap: 12px;
    }

    .preview-spotlight {
      padding: 22px;
      border-radius: 24px;
      border: 1px solid rgba(121, 145, 167, 0.14);
      background: linear-gradient(180deg, rgba(14, 20, 29, 0.84), rgba(10, 15, 22, 0.9));
      display: grid;
      gap: 14px;
      min-height: 220px;
      position: relative;
      overflow: hidden;
    }

    .preview-spotlight-list {
      display: grid;
      gap: 10px;
    }

    .preview-spotlight-item {
      border: 1px solid rgba(121, 145, 167, 0.12);
      border-radius: 16px;
      background: rgba(11, 17, 25, 0.82);
      padding: 14px;
      display: grid;
      gap: 10px;
    }

    .preview-spotlight-item strong {
      color: var(--ink);
      font-family: 'Sora', sans-serif;
      font-size: 15px;
    }

    .preview-spotlight-meta {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 12px;
      flex-wrap: wrap;
      font-size: 11px;
      color: var(--muted);
      text-transform: uppercase;
      letter-spacing: 0.12em;
    }

    .preview-spotlight-summary {
      color: var(--muted);
      font-size: 13px;
      line-height: 1.6;
      display: -webkit-box;
      -webkit-line-clamp: 3;
      -webkit-box-orient: vertical;
      overflow: hidden;
    }

    .featured-showcase {
      display: grid;
      gap: 18px;
      grid-template-columns: 1fr;
    }

    .section-head {
      display: flex;
      justify-content: space-between;
      align-items: end;
      gap: 18px;
      margin-bottom: 20px;
    }

    .featured-case-card {
      display: grid;
      gap: 22px;
      min-height: 100%;
      border-radius: 28px;
      border: 1px solid rgba(121, 145, 167, 0.14);
      background:
        radial-gradient(circle at top right, rgba(70, 186, 241, 0.08), transparent 28%),
        linear-gradient(180deg, rgba(14, 20, 29, 0.9), rgba(10, 15, 22, 0.96));
      padding: 30px;
      position: relative;
      overflow: hidden;
      grid-template-columns: minmax(0, 1.35fr) minmax(320px, 0.9fr);
      grid-template-areas:
        "head side"
        "layout side"
        "actions side";
      align-items: start;
    }

    .featured-case-card::before {
      content: "";
      position: absolute;
      left: 22px;
      right: 22px;
      top: 0;
      height: 1px;
      background: linear-gradient(90deg, rgba(70, 186, 241, 0.85), rgba(81, 211, 183, 0.12), rgba(0, 0, 0, 0));
      pointer-events: none;
    }

    .featured-case-head {
      display: grid;
      gap: 12px;
      grid-area: head;
    }

    .featured-case-meta {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      flex-wrap: wrap;
    }

    .featured-case-kicker {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 6px 12px;
      border-radius: 999px;
      border: 1px solid rgba(70, 186, 241, 0.16);
      background: rgba(12, 21, 31, 0.78);
      color: #c8eafd;
      font: 700 10px 'JetBrains Mono', monospace;
      letter-spacing: 0.16em;
      text-transform: uppercase;
    }

    .featured-case-kicker::before {
      content: "";
      width: 8px;
      height: 8px;
      border-radius: 999px;
      background: linear-gradient(135deg, var(--accent-2), var(--accent));
      box-shadow: 0 0 14px rgba(70, 186, 241, 0.42);
    }

    .featured-case-updated {
      color: var(--muted);
      font: 11px 'JetBrains Mono', monospace;
      letter-spacing: 0.08em;
      text-transform: uppercase;
    }

    .featured-case-domain {
      color: #8fdcf7;
      font: 600 12px 'JetBrains Mono', monospace;
      letter-spacing: 0.08em;
      text-transform: uppercase;
    }

    .featured-case-card h3 {
      margin: 0;
      font: 700 clamp(22px, 2vw, 30px) 'Sora', sans-serif;
      letter-spacing: -0.04em;
      line-height: 1.05;
      max-width: 18ch;
    }

    .featured-case-summary {
      color: var(--muted);
      font-size: 14px;
      line-height: 1.7;
      display: -webkit-box;
      -webkit-line-clamp: 4;
      -webkit-box-orient: vertical;
      overflow: hidden;
      margin: 0;
    }

    .featured-case-layout {
      display: grid;
      gap: 16px;
      grid-template-columns: minmax(0, 1.1fr) minmax(180px, 0.9fr);
      align-items: stretch;
      grid-area: layout;
    }

    .featured-case-chart {
      border: 1px solid rgba(121, 145, 167, 0.12);
      border-radius: 20px;
      background: linear-gradient(180deg, rgba(11, 17, 25, 0.92), rgba(8, 12, 18, 0.96));
      padding: 14px;
      display: grid;
      gap: 12px;
      min-height: 290px;
      position: relative;
      box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.03);
    }

    .featured-case-chart-head {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
      flex-wrap: wrap;
      color: var(--muted);
      font: 10px 'JetBrains Mono', monospace;
      letter-spacing: 0.16em;
      text-transform: uppercase;
    }

    .featured-case-score {
      display: inline-flex;
      align-items: baseline;
      gap: 6px;
      padding: 6px 10px;
      border-radius: 999px;
      border: 1px solid rgba(121, 145, 167, 0.18);
      background: rgba(9, 14, 20, 0.8);
      box-shadow: 0 6px 14px rgba(4, 10, 18, 0.32);
    }

    .featured-case-chart-value {
      color: var(--ink);
      font: 700 16px 'JetBrains Mono', monospace;
      letter-spacing: 0.03em;
    }

    .featured-case-score-max {
      color: var(--muted);
      font: 600 11px 'JetBrains Mono', monospace;
      letter-spacing: 0.08em;
    }

    .featured-case-score-body {
      display: grid;
      gap: 12px;
    }

    .featured-case-canvas {
      width: 100%;
      height: 170px;
      border-radius: 16px;
      border: 1px solid rgba(121, 145, 167, 0.1);
      background: linear-gradient(180deg, rgba(9, 14, 20, 0.96), rgba(14, 21, 31, 0.82));
      display: block;
    }

    .featured-case-bars {
      display: grid;
      gap: 10px;
    }

    .featured-case-bar {
      display: grid;
      grid-template-columns: minmax(88px, 0.9fr) minmax(0, 1.6fr) auto;
      gap: 10px;
      align-items: center;
      color: var(--muted);
      font: 11px 'JetBrains Mono', monospace;
    }

    .featured-case-bar strong {
      color: var(--ink);
      font: 700 12px 'JetBrains Mono', monospace;
      letter-spacing: 0.04em;
    }

    .featured-case-bar-track {
      position: relative;
      height: 9px;
      border-radius: 999px;
      background: rgba(17, 28, 41, 0.92);
      border: 1px solid rgba(121, 145, 167, 0.12);
      overflow: hidden;
    }

    .featured-case-bar-fill {
      position: absolute;
      inset: 0 auto 0 0;
      width: var(--featured-fill, 0%);
      border-radius: 999px;
      background: linear-gradient(90deg, rgba(70, 186, 241, 0.88), rgba(81, 211, 183, 0.92));
      box-shadow: 0 0 18px rgba(70, 186, 241, 0.18);
    }

    .featured-case-bar-fill--graph {
      background: linear-gradient(90deg, rgba(70, 186, 241, 0.92), rgba(126, 222, 255, 0.92));
    }

    .featured-case-bar-fill--ioc {
      background: linear-gradient(90deg, rgba(80, 214, 184, 0.9), rgba(120, 246, 171, 0.9));
    }

    .featured-case-bar-fill--evidence {
      background: linear-gradient(90deg, rgba(255, 208, 102, 0.9), rgba(255, 237, 160, 0.9));
    }

    .featured-case-bar-fill--freshness {
      background: linear-gradient(90deg, rgba(194, 154, 255, 0.88), rgba(134, 192, 255, 0.88));
    }

    .featured-case-kpis {
      display: grid;
      gap: 10px;
      align-content: start;
    }

    .featured-case-kpi {
      border: 1px solid rgba(121, 145, 167, 0.12);
      border-radius: 18px;
      padding: 14px;
      background: rgba(11, 17, 25, 0.82);
      display: grid;
      gap: 8px;
      box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.03);
    }

    .featured-case-kpi span {
      color: var(--muted);
      font: 10px 'JetBrains Mono', monospace;
      letter-spacing: 0.14em;
      text-transform: uppercase;
    }

    .featured-case-kpi strong {
      color: var(--ink);
      font: 700 22px 'Sora', sans-serif;
      letter-spacing: -0.04em;
    }

    .featured-case-kpi small {
      color: #9fc3d6;
      font: 11px 'JetBrains Mono', monospace;
      line-height: 1.5;
    }

    .featured-case-evidence {
      display: grid;
      grid-template-columns: 1fr;
      gap: 12px;
      grid-area: side;
      align-content: start;
    }

    .featured-case-evidence-item {
      display: grid;
      gap: 8px;
      padding: 12px;
      border-radius: 18px;
      border: 1px solid rgba(121, 145, 167, 0.12);
      background: rgba(11, 17, 25, 0.8);
    }

    .featured-case-evidence-item b {
      color: var(--muted);
      font: 700 10px 'JetBrains Mono', monospace;
      letter-spacing: 0.16em;
      text-transform: uppercase;
    }

    .featured-case-evidence-item img {
      width: 100%;
      aspect-ratio: 16 / 10;
      object-fit: cover;
      border-radius: 14px;
      border: 1px solid rgba(121, 145, 167, 0.12);
      background: rgba(4, 8, 12, 0.8);
    }

    .featured-case-actions {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
      grid-area: actions;
    }

    .chart-card {
      padding: 20px;
      border-radius: 22px;
      border: 1px solid rgba(121, 145, 167, 0.14);
      background: linear-gradient(180deg, rgba(14, 20, 29, 0.84), rgba(10, 15, 22, 0.9));
      display: grid;
      gap: 12px;
      min-height: 160px;
      position: relative;
      overflow: hidden;
    }

    .chart-card::after {
      content: "";
      position: absolute;
      inset: 0;
      background: linear-gradient(180deg, rgba(70, 186, 241, 0.02), rgba(81, 211, 183, 0.015));
      pointer-events: none;
    }

    .chart-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      font-size: 11px;
      text-transform: uppercase;
      letter-spacing: 0.18em;
      color: var(--muted);
      font-family: 'Sora', sans-serif;
    }

    .chart-value {
      font-family: 'JetBrains Mono', monospace;
      font-size: 14px;
      color: var(--ink);
      letter-spacing: 0.04em;
    }

    .sparkline {
      width: 100%;
      height: 70px;
      border-radius: 16px;
      background: rgba(9, 14, 20, 0.82);
      border: 1px solid rgba(121, 145, 167, 0.12);
      display: grid;
      place-items: center;
      overflow: hidden;
      position: relative;
    }

    .sparkline::after {
      content: "";
      position: absolute;
      inset: -20% -35%;
      background: linear-gradient(90deg, rgba(255, 255, 255, 0) 0%, rgba(73, 210, 255, 0.08) 45%, rgba(115, 255, 212, 0.22) 50%, rgba(255, 255, 255, 0) 56%);
      transform: translateX(-56%);
      animation: previewSweep 7.2s linear infinite;
      pointer-events: none;
      mix-blend-mode: screen;
    }

    .sparkline svg {
      width: 100%;
      height: 100%;
      position: relative;
      z-index: 1;
      animation: sparklineFloat 8.5s ease-in-out infinite;
    }

    .sparkline .sparkline-area {
      opacity: 0.9;
      animation: sparklineAreaPulse 6.8s ease-in-out infinite;
      transform-origin: center bottom;
    }

    .sparkline .sparkline-line {
      filter: drop-shadow(0 0 10px rgba(24, 229, 255, 0.24));
    }

    .sparkline .sparkline-line-glow {
      animation: sparklineDashMove 5.4s linear infinite;
      filter: drop-shadow(0 0 12px rgba(112, 247, 207, 0.18));
    }

    .sparkline.is-refreshing .sparkline-line {
      animation: sparklineReveal 1.05s cubic-bezier(0.22, 1, 0.36, 1);
    }

    .activity-bars {
      display: grid;
      gap: 8px;
      align-content: start;
    }

    .activity-bar-row {
      display: grid;
      grid-template-columns: 58px minmax(0, 1fr) 100px;
      gap: 8px;
      align-items: center;
      font-size: 11px;
      color: var(--muted);
    }

    .activity-day {
      font-family: 'JetBrains Mono', monospace;
    }

    .activity-count {
      text-align: right;
      font-family: 'JetBrains Mono', monospace;
      color: var(--ink);
    }

    .activity-track {
      position: relative;
      height: 10px;
      border-radius: 999px;
      background: rgba(16, 26, 34, 0.85);
      border: 1px solid rgba(24, 229, 255, 0.16);
      overflow: hidden;
    }

    .activity-track::after {
      content: "";
      position: absolute;
      inset: -20% -45%;
      background: linear-gradient(90deg, rgba(255, 255, 255, 0), rgba(98, 222, 255, 0.06), rgba(99, 255, 211, 0.18), rgba(255, 255, 255, 0));
      transform: translateX(-48%);
      animation: previewSweep 6.4s linear infinite;
      pointer-events: none;
      mix-blend-mode: screen;
    }

    .activity-alert {
      position: absolute;
      inset: 0 auto 0 0;
      height: 100%;
      background: linear-gradient(90deg, rgba(24, 229, 255, 0.5), rgba(24, 229, 255, 0.88));
      width: 100%;
      transform-origin: left center;
      transform: scaleX(var(--scale, 0));
      transition: transform 880ms cubic-bezier(0.22, 1, 0.36, 1);
      box-shadow: 0 0 16px rgba(24, 229, 255, 0.18);
    }

    .activity-block {
      position: absolute;
      inset: 0 auto 0 0;
      height: 100%;
      background: linear-gradient(90deg, rgba(89, 240, 141, 0.45), rgba(89, 240, 141, 0.9));
      mix-blend-mode: screen;
      width: 100%;
      transform-origin: left center;
      transform: scaleX(var(--scale, 0));
      transition: transform 1040ms cubic-bezier(0.22, 1, 0.36, 1);
      box-shadow: 0 0 18px rgba(89, 240, 141, 0.16);
    }

    .heat-matrix {
      display: grid;
      grid-template-columns: repeat(7, minmax(0, 1fr));
      gap: 6px;
      align-content: start;
      min-height: 78px;
    }

    .heat-cell {
      border-radius: 8px;
      border: 1px solid rgba(24, 229, 255, 0.18);
      min-height: 34px;
      background: rgba(8, 20, 28, 0.78);
      position: relative;
      overflow: hidden;
    }

    .heat-cell::before {
      content: "";
      position: absolute;
      inset: 0;
      background: linear-gradient(140deg, rgba(24, 229, 255, calc(var(--a, 0.05))), rgba(89, 240, 141, calc(var(--b, 0.04))));
      opacity: 0.96;
      transform: scale(0.88);
      transform-origin: center center;
      transition: transform 820ms cubic-bezier(0.22, 1, 0.36, 1), filter 820ms ease;
      animation: heatCellPulse 5.8s ease-in-out infinite;
      animation-delay: calc(var(--index, 0) * 0.14s);
    }

    .heat-cell.is-live::before {
      transform: scale(1);
      filter: saturate(1.08) brightness(1.04);
    }

    .heat-cell::after {
      content: attr(data-label);
      position: absolute;
      right: 4px;
      bottom: 3px;
      font: 9px 'JetBrains Mono', monospace;
      color: rgba(235, 246, 255, 0.66);
    }

    @keyframes previewSweep {
      0% {
        transform: translateX(-56%);
      }
      100% {
        transform: translateX(56%);
      }
    }

    @keyframes sparklineFloat {
      0%, 100% {
        transform: translateY(0);
      }
      50% {
        transform: translateY(-1px);
      }
    }

    @keyframes sparklineAreaPulse {
      0%, 100% {
        opacity: 0.78;
      }
      50% {
        opacity: 0.98;
      }
    }

    @keyframes sparklineDashMove {
      from {
        stroke-dashoffset: 40;
      }
      to {
        stroke-dashoffset: -40;
      }
    }

    @keyframes sparklineReveal {
      0% {
        opacity: 0.2;
        transform: translateY(5px);
      }
      100% {
        opacity: 1;
        transform: translateY(0);
      }
    }

    @keyframes heatCellPulse {
      0%, 100% {
        opacity: 0.82;
      }
      50% {
        opacity: 1;
      }
    }

    .preview-row {
      display: grid;
      grid-template-columns: minmax(0, 1.2fr) minmax(260px, 0.8fr);
      gap: 18px;
      align-items: stretch;
    }

    .preview-list {
      padding: 22px;
      border-radius: 24px;
      border: 1px solid rgba(121, 145, 167, 0.14);
      background: linear-gradient(180deg, rgba(14, 20, 29, 0.84), rgba(10, 15, 22, 0.9));
      display: grid;
      gap: 14px;
      min-height: 180px;
      position: relative;
      overflow: hidden;
    }

    .recent-list {
      display: grid;
      gap: 10px;
    }

    .recent-item {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 16px;
      padding: 12px 14px;
      border-radius: 16px;
      border: 1px solid rgba(121, 145, 167, 0.12);
      background: rgba(11, 17, 25, 0.82);
      font-size: 12px;
      color: var(--muted);
    }

    .recent-item strong {
      color: var(--ink);
      font-size: 13px;
      font-family: 'Sora', sans-serif;
    }

    .preview-cta {
      padding: 24px;
      border-radius: 24px;
      border: 1px solid rgba(81, 211, 183, 0.18);
      background: linear-gradient(180deg, rgba(14, 21, 30, 0.9), rgba(10, 16, 24, 0.94));
      display: grid;
      gap: 16px;
      box-shadow: 0 18px 40px rgba(5, 15, 24, 0.34);
      position: relative;
      overflow: hidden;
    }

    .preview-cta h3 {
      margin: 0;
      font-family: 'Sora', sans-serif;
      font-size: 22px;
    }

    .preview-cta p {
      margin: 0;
      color: var(--muted);
      line-height: 1.6;
    }

    .preview-actions {
      display: flex;
      flex-wrap: wrap;
      gap: 12px;
    }

    .scan-preview {
      display: grid;
      gap: 12px;
    }

    .scan-preview-grid {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 12px;
    }

    .scan-preview-item {
      border: 1px solid rgba(24, 229, 255, 0.18);
      border-radius: 14px;
      background: rgba(7, 12, 18, 0.75);
      padding: 10px;
      display: grid;
      gap: 8px;
    }

    .scan-preview-item b {
      font-size: 12px;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      color: var(--muted);
    }

    .scan-preview-item img {
      width: 100%;
      height: auto;
      object-fit: contain;
      aspect-ratio: 16 / 9;
      border-radius: 10px;
      border: 1px solid rgba(24, 229, 255, 0.25);
      background: rgba(0, 0, 0, 0.2);
    }

    .access-request-form {
      display: grid;
      gap: 10px;
      margin-top: 4px;
    }

    .access-request-field {
      display: grid;
      gap: 8px;
    }

    .access-request-label {
      font-size: 12px;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      color: var(--muted);
    }

    .access-request-input {
      width: 100%;
      border-radius: 14px;
      border: 1px solid rgba(24, 229, 255, 0.22);
      background: rgba(5, 8, 12, 0.85);
      color: var(--ink);
      padding: 12px 14px;
      font-family: 'JetBrains Mono', monospace;
      font-size: 13px;
      outline: none;
      transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }

    .access-request-input,
    .lang select,
    button,
    input,
    select,
    textarea {
      max-width: 100%;
    }

    .access-request-input:focus {
      border-color: rgba(89, 240, 141, 0.6);
      box-shadow: 0 0 0 3px rgba(89, 240, 141, 0.12);
    }

    .access-request-submit {
      justify-self: start;
    }

    .access-request-feedback {
      margin-top: 10px;
      border-radius: 12px;
      padding: 10px 12px;
      font-size: 13px;
      border: 1px solid transparent;
    }

    .access-request-feedback.success {
      background: rgba(16, 185, 129, 0.12);
      border-color: rgba(16, 185, 129, 0.35);
      color: #9ef5d5;
    }

    .access-request-feedback.warn {
      background: rgba(245, 158, 11, 0.12);
      border-color: rgba(245, 158, 11, 0.4);
      color: #fcd58a;
    }

    .access-request-feedback.error {
      background: rgba(239, 68, 68, 0.12);
      border-color: rgba(239, 68, 68, 0.35);
      color: #ffc2c2;
    }

    .access-request-honeypot {
      position: absolute;
      left: -9999px;
      opacity: 0;
      pointer-events: none;
    }

    .internal-ad-section {
      display: grid;
      gap: 18px;
    }

    .internal-ad-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
      gap: 14px;
    }

    .internal-ad-card {
      border-radius: 20px;
      border: 1px solid rgba(121, 145, 167, 0.16);
      background: rgba(11, 17, 25, 0.82);
      padding: 18px;
      display: grid;
      gap: 12px;
    }

    .internal-ad-card.cyan {
      border-color: rgba(24, 229, 255, 0.28);
      background: linear-gradient(155deg, rgba(8, 19, 28, 0.92), rgba(8, 28, 42, 0.84));
    }

    .internal-ad-card.lime {
      border-color: rgba(89, 240, 141, 0.26);
      background: linear-gradient(155deg, rgba(8, 19, 28, 0.92), rgba(14, 36, 30, 0.84));
    }

    .internal-ad-card.amber {
      border-color: rgba(242, 176, 83, 0.3);
      background: linear-gradient(155deg, rgba(18, 15, 12, 0.92), rgba(44, 27, 10, 0.84));
    }

    .internal-ad-card.fuchsia {
      border-color: rgba(213, 110, 255, 0.3);
      background: linear-gradient(155deg, rgba(20, 12, 25, 0.92), rgba(41, 16, 47, 0.84));
    }

    .internal-ad-kicker {
      font-size: 11px;
      text-transform: uppercase;
      letter-spacing: 0.14em;
      color: var(--muted);
    }

    .internal-ad-card h3 {
      margin: 0;
      font-family: 'Sora', sans-serif;
      font-size: 18px;
      line-height: 1.25;
    }

    .internal-ad-card p {
      margin: 0;
      color: #b8cad2;
      line-height: 1.55;
    }

    .monetization-section {
      display: grid;
      gap: 18px;
    }

    .monetization-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
      gap: 14px;
    }

    .support-card {
      border-radius: 20px;
      border: 1px solid rgba(121, 145, 167, 0.16);
      background: rgba(11, 17, 25, 0.82);
      padding: 18px;
      display: grid;
      gap: 12px;
      position: relative;
      overflow: hidden;
    }

    .support-card h3 {
      margin: 0;
      font-family: 'Sora', sans-serif;
      font-size: 18px;
    }

    .support-card p {
      margin: 0;
      color: var(--muted);
      line-height: 1.55;
    }

    .support-actions {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
    }

    .ad-shell {
      min-height: 120px;
      border-radius: 14px;
      border: 1px dashed rgba(24, 229, 255, 0.35);
      background: rgba(5, 8, 12, 0.78);
      display: grid;
      place-items: center;
      overflow: hidden;
      padding: 6px;
    }

    .market-proof {
      display: grid;
      gap: 18px;
    }

    .proof-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: 12px;
    }

    .proof-card {
      border-radius: 20px;
      border: 1px solid rgba(121, 145, 167, 0.14);
      background: rgba(11, 17, 25, 0.82);
      padding: 18px;
      display: grid;
      gap: 10px;
      min-height: 116px;
      position: relative;
      overflow: hidden;
    }

    .proof-card-label {
      font: 600 11px 'JetBrains Mono', monospace;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      color: var(--muted);
    }

    .proof-card-value {
      font: 700 24px 'Sora', sans-serif;
      color: var(--accent-2);
      letter-spacing: 0.01em;
    }

    .proof-card-note {
      color: var(--muted);
      font-size: 13px;
      line-height: 1.45;
    }

    .market-list {
      margin: 0;
      padding-left: 18px;
      display: grid;
      gap: 8px;
      color: var(--muted);
      line-height: 1.5;
    }

    .market-list li::marker {
      color: var(--accent-2);
    }

    .market-cta-row {
      display: flex;
      gap: 12px;
      flex-wrap: wrap;
      margin-top: 4px;
    }

    .marketing-bar {
      position: fixed;
      left: 50%;
      bottom: 14px;
      transform: translateX(-50%);
      width: min(1120px, calc(100vw - 20px));
      border-radius: 18px;
      border: 1px solid rgba(121, 145, 167, 0.16);
      background: linear-gradient(145deg, rgba(10, 16, 24, 0.96), rgba(13, 20, 29, 0.96));
      box-shadow: 0 18px 40px rgba(3, 9, 15, 0.32);
      backdrop-filter: blur(12px);
      z-index: 5;
      padding: 12px 14px;
      display: grid;
      grid-template-columns: minmax(0, 1.2fr) minmax(220px, 0.8fr);
      gap: 12px;
      align-items: center;
    }

    .marketing-bar.is-hidden {
      display: none;
    }

    .marketing-bar-main {
      display: grid;
      gap: 4px;
      min-width: 0;
    }

    .marketing-bar strong {
      font-family: 'Sora', sans-serif;
      color: var(--ink);
      display: block;
      margin-bottom: 2px;
    }

    .marketing-bar span {
      color: var(--muted);
      font-size: 13px;
      line-height: 1.4;
    }

    .marketing-bar-actions {
      display: flex;
      gap: 8px;
      justify-content: flex-end;
      flex-wrap: wrap;
    }

    .marketing-bar-actions .button {
      padding: 9px 14px;
      font-size: 12px;
    }

    .marketing-bar-dismiss {
      width: 34px;
      height: 34px;
      border-radius: 999px;
      border: 1px solid rgba(24, 229, 255, 0.22);
      background: rgba(7, 12, 18, 0.74);
      color: var(--muted);
      display: inline-flex;
      align-items: center;
      justify-content: center;
      flex: 0 0 auto;
      font: 700 16px/1 'Sora', sans-serif;
      cursor: pointer;
      transition: border-color 0.2s ease, color 0.2s ease, transform 0.2s ease;
    }

    .marketing-bar-dismiss:hover {
      color: var(--ink);
      border-color: rgba(24, 229, 255, 0.4);
      transform: translateY(-1px);
    }

    .footer {
      border-top: 1px solid var(--stroke);
      padding-top: 28px;
      color: var(--muted);
      display: flex;
      flex-wrap: wrap;
      gap: 18px;
      justify-content: space-between;
      font-size: 14px;
    }
    .footer-credit {
      width: 100%;
      margin-top: 4px;
      color: #b8cfd8;
    }
    .footer-credit a {
      color: var(--accent-2);
      text-decoration: underline;
      text-underline-offset: 2px;
    }

    .lang-block { display: none; }
    .lang-inline { display: none; }

    body.lang-ar [data-lang="ar"].lang-block,
    body.lang-ca [data-lang="ca"].lang-block,
    body.lang-de [data-lang="de"].lang-block,
    body.lang-en [data-lang="en"].lang-block,
    body.lang-es [data-lang="es"].lang-block,
    body.lang-fr [data-lang="fr"].lang-block,
    body.lang-he [data-lang="he"].lang-block,
    body.lang-hi [data-lang="hi"].lang-block,
    body.lang-ja [data-lang="ja"].lang-block,
    body.lang-ko [data-lang="ko"].lang-block,
    body.lang-nl [data-lang="nl"].lang-block,
    body.lang-pt [data-lang="pt"].lang-block,
    body.lang-ru [data-lang="ru"].lang-block,
    body.lang-zh [data-lang="zh"].lang-block {
      display: block;
    }
    body.lang-it [data-lang="en"].lang-block {
      display: block;
    }

    body.lang-ar [data-lang="ar"].lang-inline,
    body.lang-ca [data-lang="ca"].lang-inline,
    body.lang-de [data-lang="de"].lang-inline,
    body.lang-en [data-lang="en"].lang-inline,
    body.lang-es [data-lang="es"].lang-inline,
    body.lang-fr [data-lang="fr"].lang-inline,
    body.lang-he [data-lang="he"].lang-inline,
    body.lang-hi [data-lang="hi"].lang-inline,
    body.lang-ja [data-lang="ja"].lang-inline,
    body.lang-ko [data-lang="ko"].lang-inline,
    body.lang-nl [data-lang="nl"].lang-inline,
    body.lang-pt [data-lang="pt"].lang-inline,
    body.lang-ru [data-lang="ru"].lang-inline,
    body.lang-zh [data-lang="zh"].lang-inline {
      display: inline;
    }
    body.lang-it [data-lang="en"].lang-inline {
      display: inline;
    }

    body.lang-ar,
    body.lang-he {
      direction: rtl;
    }

    body.lang-ar .nav,
    body.lang-he .nav {
      flex-direction: row-reverse;
    }

    .reveal {
      opacity: 0;
      transform: translateY(12px);
      animation: rise 0.8s ease forwards;
    }

    .reveal.delay-1 { animation-delay: 0.1s; }
    .reveal.delay-2 { animation-delay: 0.2s; }
    .reveal.delay-3 { animation-delay: 0.3s; }

    @keyframes rise {
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    @media (max-width: 1180px) {
      .hero-grid {
        grid-template-columns: 1fr;
      }
      .hero-copy {
        padding: 24px 26px 28px;
      }
      .hero-flow {
        grid-template-columns: repeat(3, minmax(0, 1fr));
      }
      .preview-onboarding {
        grid-template-columns: repeat(2, minmax(0, 1fr));
      }
      .preview-grid,
      .preview-row {
        grid-template-columns: 1fr;
      }
      .marketing-bar {
        grid-template-columns: 1fr;
      }
      .marketing-bar-actions {
        justify-content: flex-start;
      }
    }

    @media (max-width: 920px) {
      .page {
        gap: 52px;
      }
      .nav {
        top: calc(10px + env(safe-area-inset-top));
        padding: 10px 12px;
        gap: 12px;
      }
      .brand {
        width: 100%;
        gap: 12px;
      }
      .lang select,
      .access-request-input,
      input,
      select,
      textarea,
      button {
        font-size: 16px;
      }
      .hero-panel,
      .preview-list,
      .preview-cta,
      .preview-spotlight,
      .quick-action-card,
      .chart-card {
        padding: 16px;
      }
      .hero-copy {
        padding: 22px 22px 24px;
      }
      .hero-flow {
        grid-template-columns: 1fr;
      }
      .hero-geo-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
      }
      .preview-metrics {
        grid-template-columns: repeat(2, minmax(0, 1fr));
      }
      .section-head,
      .featured-case-meta,
      .featured-case-chart-head {
        align-items: flex-start;
      }
      .featured-case-layout {
        grid-template-columns: 1fr;
      }
      .recent-item {
        align-items: flex-start;
      }
      .hero-map-badge {
        max-width: calc(100% - 20px);
      }
      .surveillance-radar,
      .hero-leaflet-map {
        min-height: 400px;
        height: 400px;
      }
    }

    @media (max-width: 720px) {
      .page {
        gap: 40px;
        padding:
          calc(18px + env(safe-area-inset-top))
          max(12px, env(safe-area-inset-right), 4vw)
          calc(148px + env(safe-area-inset-bottom))
          max(12px, env(safe-area-inset-left), 4vw);
      }
      .nav {
        top: calc(8px + env(safe-area-inset-top));
        padding: 8px 10px;
        gap: 10px;
        min-height: 60px;
      }
      .brand {
        width: auto;
        gap: 10px;
      }
      .brand-copy {
        gap: 2px;
      }
      .brand-copy .pill {
        display: none;
      }
      .brand-title {
        font-size: 15px;
        max-width: calc(100vw - 110px);
      }
      .brand-mark {
        width: 38px;
        height: 38px;
        border-radius: 12px;
        padding: 5px;
      }
      .lang-desktop {
        display: none;
      }
      .lang-fab {
        display: flex;
      }
      .page {
        padding-top: calc(14px + env(safe-area-inset-top));
      }
      .hero-grid { grid-template-columns: 1fr; }
      .hero-copy {
        padding: 18px 18px 22px;
        border-radius: 22px;
      }
      .hero-copy::before {
        top: 18px;
        height: 72px;
      }
      .preview-onboarding {
        grid-template-columns: 1fr;
      }
      .preview-grid,
      .preview-row {
        grid-template-columns: 1fr;
      }
      .scan-preview-grid {
        grid-template-columns: 1fr;
      }
      .preview-metrics,
      .hero-panel-grid,
      .hero-geo-grid {
        grid-template-columns: 1fr;
      }
      .section-head,
      .featured-case-meta,
      .featured-case-chart-head {
        flex-direction: column;
        align-items: flex-start;
      }
      .hero-flow {
        grid-template-columns: 1fr;
      }
      .hero-geo-card {
        padding: 7px 8px;
        gap: 6px;
        width: 100%;
        justify-self: stretch;
      }
      .hero-geo-summary {
        font-size: 10px;
      }
      .hero-flow-step,
      .chart-header,
      .recent-item,
      .hero-geo-card-head {
        flex-direction: column;
        align-items: flex-start;
      }
      .activity-bar-row {
        grid-template-columns: 1fr;
      }
      .activity-count {
        text-align: left;
      }
      .featured-case-bar,
      .featured-case-evidence {
        grid-template-columns: 1fr;
      }
      .heat-matrix {
        grid-template-columns: repeat(4, minmax(0, 1fr));
      }
      .recent-item span,
      .recent-item strong,
      .preview-spotlight-summary,
      .metric-foot {
        word-break: break-word;
      }
      .cta { width: 100%; }
      .button { width: 100%; justify-content: center; }
      .step { grid-template-columns: 1fr; }
      .step-number { margin-bottom: 4px; }
      .marketing-bar {
        width: calc(100vw - 16px);
        bottom: 8px;
        padding: 10px;
        grid-template-columns: 1fr auto;
        align-items: start;
      }
      .marketing-bar-actions {
        width: 100%;
        grid-column: 1 / -1;
      }
      .marketing-bar-actions .button {
        width: 100%;
        justify-content: center;
      }
      .marketing-bar-main {
        padding-right: 4px;
      }
    }

    @media (max-width: 520px) {
      .nav {
        border-radius: 16px;
      }
      .brand-title {
        font-size: 14px;
        max-width: calc(100vw - 98px);
      }
      .surveillance-radar,
      .hero-leaflet-map {
        min-height: 320px;
        height: 320px;
      }
      .hero-map-badge {
        position: static;
        margin: 10px;
        max-width: none;
      }
      .radar-caption {
        top: auto;
        bottom: 8px;
        left: 8px;
        padding: 5px 8px;
        font-size: 9px;
      }
      .heat-matrix {
        grid-template-columns: repeat(3, minmax(0, 1fr));
      }
      .preview-actions,
      .marketing-bar-actions {
        gap: 10px;
      }
      .marketing-bar-dismiss {
        width: 32px;
        height: 32px;
      }
    }
  </style>
</head>
<body class="lang-<?= htmlspecialchars($initialLang, ENT_QUOTES, 'UTF-8'); ?>">
  <div class="page">
    <header class="nav">
      <div class="brand">
        <div class="brand-mark">
          <img src="favicon.ico" alt="" data-i18n-alt="brand_icon_alt" width="50" height="50" decoding="async" />
        </div>
        <div class="brand-copy">
          <div class="pill">
            <span class="lang-inline" data-lang="es">Defensa primero</span>
            <span class="lang-inline" data-lang="en">Defense-first</span>
            <span class="lang-inline" data-lang="pt">Defesa primeiro</span>
            <span class="lang-inline" data-lang="fr">Defense d abord</span>
            <span class="lang-inline" data-lang="de">Defense zuerst</span>
            <span class="lang-inline" data-lang="nl">Defense eerst</span>
            <span class="lang-inline" data-lang="ca">Defensa primer</span>
            <span class="lang-inline" data-lang="ru">Защита прежде всего</span>
            <span class="lang-inline" data-lang="ja">防御優先</span>
            <span class="lang-inline" data-lang="ko">방어 우선</span>
            <span class="lang-inline" data-lang="zh">防御优先</span>
            <span class="lang-inline" data-lang="hi">डिफेंस फर्स्ट</span>
            <span class="lang-inline" data-lang="ar">الدفاع اولا</span>
            <span class="lang-inline" data-lang="he">הגנה תחילה</span>
          </div>
          <div class="brand-title">
            <span class="lang-inline" data-lang="es">ClickFix Mitigator</span>
            <span class="lang-inline" data-lang="en">ClickFix Mitigator</span>
            <span class="lang-inline" data-lang="pt">ClickFix Mitigator</span>
            <span class="lang-inline" data-lang="fr">ClickFix Mitigator</span>
            <span class="lang-inline" data-lang="de">ClickFix Mitigator</span>
            <span class="lang-inline" data-lang="nl">ClickFix Mitigator</span>
            <span class="lang-inline" data-lang="ca">ClickFix Mitigator</span>
            <span class="lang-inline" data-lang="ru">ClickFix Mitigator</span>
            <span class="lang-inline" data-lang="ja">ClickFix Mitigator</span>
            <span class="lang-inline" data-lang="ko">ClickFix Mitigator</span>
            <span class="lang-inline" data-lang="zh">ClickFix Mitigator</span>
            <span class="lang-inline" data-lang="hi">ClickFix Mitigator</span>
            <span class="lang-inline" data-lang="ar">ClickFix Mitigator</span>
            <span class="lang-inline" data-lang="he">ClickFix Mitigator</span>
          </div>
        </div>
      </div>
      <div class="lang lang-desktop" data-i18n-aria="language_selector">
        <span class="lang-label" data-i18n="language_label"></span>
        <select id="lang-select" data-lang-select="desktop" data-i18n-aria="language_select">
          <option value="en" data-lang-option="en"></option>
          <option value="es" data-lang-option="es"></option>
          <option value="pt" data-lang-option="pt"></option>
          <option value="fr" data-lang-option="fr"></option>
          <option value="it" data-lang-option="it"></option>
          <option value="de" data-lang-option="de"></option>
          <option value="nl" data-lang-option="nl"></option>
          <option value="ca" data-lang-option="ca"></option>
          <option value="ru" data-lang-option="ru"></option>
          <option value="ja" data-lang-option="ja"></option>
          <option value="ko" data-lang-option="ko"></option>
          <option value="zh" data-lang-option="zh"></option>
          <option value="hi" data-lang-option="hi"></option>
          <option value="ar" data-lang-option="ar"></option>
          <option value="he" data-lang-option="he"></option>
        </select>
      </div>
    </header>
    <main class="main" id="main-content">
    <div class="lang-fab" id="lang-fab" data-i18n-aria="language_selector">
      <button class="lang-fab-button" type="button" id="lang-fab-toggle" data-i18n-aria="language_selector">EN</button>
      <div class="lang-fab-panel" id="lang-fab-panel">
        <select id="lang-select-mobile" data-lang-select="mobile" data-i18n-aria="language_select">
          <option value="en" data-lang-option="en"></option>
          <option value="es" data-lang-option="es"></option>
          <option value="pt" data-lang-option="pt"></option>
          <option value="fr" data-lang-option="fr"></option>
          <option value="it" data-lang-option="it"></option>
          <option value="de" data-lang-option="de"></option>
          <option value="nl" data-lang-option="nl"></option>
          <option value="ca" data-lang-option="ca"></option>
          <option value="ru" data-lang-option="ru"></option>
          <option value="ja" data-lang-option="ja"></option>
          <option value="ko" data-lang-option="ko"></option>
          <option value="zh" data-lang-option="zh"></option>
          <option value="hi" data-lang-option="hi"></option>
          <option value="ar" data-lang-option="ar"></option>
          <option value="he" data-lang-option="he"></option>
        </select>
      </div>
    </div>

    <section class="hero">
      <div class="hero-grid">
        <div class="hero-copy">
      <div class="eyebrow">
        <span class="lang-inline" data-lang="es">Extension lista para instalar</span>
        <span class="lang-inline" data-lang="en">Install-ready extension</span>
        <span class="lang-inline" data-lang="pt">Extensao pronta para instalar</span>
        <span class="lang-inline" data-lang="fr">Extension prete a installer</span>
        <span class="lang-inline" data-lang="de">Installationsbereite Erweiterung</span>
        <span class="lang-inline" data-lang="nl">Installatieklare extensie</span>
        <span class="lang-inline" data-lang="ca">Extensio llesta per instalar</span>
        <span class="lang-inline" data-lang="ru">Расширение готово к установке</span>
        <span class="lang-inline" data-lang="ja">すぐにインストール可能な拡張機能</span>
        <span class="lang-inline" data-lang="ko">바로 설치 가능한 확장 프로그램</span>
        <span class="lang-inline" data-lang="zh">可直接安装的扩展</span>
        <span class="lang-inline" data-lang="hi">इंस्टॉल के लिए तैयार एक्सटेंशन</span>
        <span class="lang-inline" data-lang="ar">امتداد جاهز للتثبيت</span>
        <span class="lang-inline" data-lang="he">תוסף מוכן להתקנה</span>
      </div>
      <h1 class="reveal">
        <span class="lang-inline" data-lang="es">Instala la extension que corta ClickFix antes del ultimo click.</span>
        <span class="lang-inline" data-lang="en">Install the extension that cuts ClickFix before the final click.</span>
        <span class="lang-inline" data-lang="pt">Instale a extensao que corta o ClickFix antes do clique final.</span>
        <span class="lang-inline" data-lang="fr">Installez l extension qui coupe ClickFix avant le clic final.</span>
        <span class="lang-inline" data-lang="de">Installiere die Erweiterung, die ClickFix vor dem letzten Klick stoppt.</span>
        <span class="lang-inline" data-lang="nl">Installeer de extensie die ClickFix stopt voor de laatste klik.</span>
        <span class="lang-inline" data-lang="ca">Instal la extensio que talla ClickFix abans de l ultim clic.</span>
        <span class="lang-inline" data-lang="ru">Установите расширение, которое останавливает ClickFix до последнего клика.</span>
        <span class="lang-inline" data-lang="ja">最後のクリックの前にClickFixを止める拡張機能をインストール。</span>
        <span class="lang-inline" data-lang="ko">마지막 클릭 전에 ClickFix를 차단하는 확장 프로그램을 설치하세요.</span>
        <span class="lang-inline" data-lang="zh">安装在最后一次点击前阻止 ClickFix 的扩展。</span>
        <span class="lang-inline" data-lang="hi">वह एक्सटेंशन इंस्टॉल करें जो आखिरी क्लिक से पहले ClickFix रोक देता है।</span>
        <span class="lang-inline" data-lang="ar">ثبّت الامتداد الذي يوقف ClickFix قبل النقرة الأخيرة.</span>
        <span class="lang-inline" data-lang="he">התקן את התוסף שעוצר את ClickFix לפני הלחיצה האחרונה.</span>
      </h1>
      <p class="lang-block reveal delay-1" data-lang="es">
        ClickFix Mitigator blinda la navegacion con deteccion contextual, bloqueo inteligente y evidencia clara para el equipo
        de seguridad.
      </p>
      <p class="lang-block reveal delay-1" data-lang="en">
        ClickFix Mitigator hardens browsing with contextual detection, smart blocking, and clear evidence for security teams.
      </p>
      <p class="lang-block reveal delay-1" data-lang="pt">
        ClickFix Mitigator reforca a navegacao com deteccao contextual, bloqueio inteligente e evidencia clara para times de
        seguranca.
      </p>
      <p class="lang-block reveal delay-1" data-lang="fr">
        ClickFix Mitigator renforce la navigation avec detection contextuelle, blocage intelligent et preuves claires pour les
        equipes de securite.
      </p>
      <p class="lang-block reveal delay-1" data-lang="de">
        ClickFix Mitigator sichert das Browsen mit kontextueller Erkennung, intelligentem Blocking und klaren Nachweisen fuer
        Sicherheitsteams.
      </p>
      <p class="lang-block reveal delay-1" data-lang="nl">
        ClickFix Mitigator versterkt het browsen met contextdetectie, slimme blokkering en duidelijke bewijslast voor
        securityteams.
      </p>
      <p class="lang-block reveal delay-1" data-lang="ca">
        ClickFix Mitigator blinda la navegacio amb deteccio contextual, bloqueig intel·ligent i evidencies clares per als equips
        de seguretat.
      </p>
      <p class="lang-block reveal delay-1" data-lang="ru">
        ClickFix Mitigator усиливает браузинг за счет контекстного обнаружения, умного блокирования и четких доказательств для
        команд безопасности.
      </p>
      <p class="lang-block reveal delay-1" data-lang="ja">
        ClickFix Mitigator は、文脈検知、スマートなブロック、セキュリティチーム向けの明確な証跡でブラウジングを強化します。
      </p>
      <p class="lang-block reveal delay-1" data-lang="ko">
        ClickFix Mitigator는 문맥 기반 탐지, 스마트 차단, 보안 팀을 위한 명확한 증거로 브라우징을 강화합니다.
      </p>
      <p class="lang-block reveal delay-1" data-lang="zh">
        ClickFix Mitigator 通过情境检测、智能阻断和清晰证据来强化浏览安全。
      </p>
      <p class="lang-block reveal delay-1" data-lang="hi">
        ClickFix Mitigator संदर्भ-आधारित डिटेक्शन, स्मार्ट ब्लॉकिंग और सुरक्षा टीमों के लिए स्पष्ट प्रमाण के साथ ब्राउज़िंग को
        मजबूत करता है।
      </p>
      <p class="lang-block reveal delay-1" data-lang="ar">
        يعزز ClickFix Mitigator التصفح عبر كشف سياقي وحظر ذكي وأدلة واضحة لفرق الامن.
      </p>
      <p class="lang-block reveal delay-1" data-lang="he">
        ClickFix Mitigator מחזק את הגלישה באמצעות זיהוי הקשרי, חסימה חכמה וראיות ברורות לצוותי האבטחה.
      </p>
      <div class="tags hero-tags reveal delay-1">
        <span class="tag lang-inline" data-lang="es">Extension MV3</span>
        <span class="tag lang-inline" data-lang="es">Escudo de portapapeles</span>
        <span class="tag lang-inline" data-lang="es">Alertas en tiempo real</span>
        <span class="tag lang-inline" data-lang="en">MV3 Extension</span>
        <span class="tag lang-inline" data-lang="en">Clipboard Shield</span>
        <span class="tag lang-inline" data-lang="en">Real-time Alerts</span>
        <span class="tag lang-inline" data-lang="pt">Extensao MV3</span>
        <span class="tag lang-inline" data-lang="pt">Escudo de clipboard</span>
        <span class="tag lang-inline" data-lang="pt">Alertas em tempo real</span>
        <span class="tag lang-inline" data-lang="fr">Extension MV3</span>
        <span class="tag lang-inline" data-lang="fr">Bouclier presse-papiers</span>
        <span class="tag lang-inline" data-lang="fr">Alertes temps reel</span>
        <span class="tag lang-inline" data-lang="de">MV3-Erweiterung</span>
        <span class="tag lang-inline" data-lang="de">Zwischenablage-Schutz</span>
        <span class="tag lang-inline" data-lang="de">Echtzeitwarnungen</span>
        <span class="tag lang-inline" data-lang="nl">MV3-extensie</span>
        <span class="tag lang-inline" data-lang="nl">Klembordbescherming</span>
        <span class="tag lang-inline" data-lang="nl">Realtime waarschuwingen</span>
        <span class="tag lang-inline" data-lang="ca">Extensio MV3</span>
        <span class="tag lang-inline" data-lang="ca">Escut del porta-retalls</span>
        <span class="tag lang-inline" data-lang="ca">Alertes en temps real</span>
        <span class="tag lang-inline" data-lang="ru">Расширение MV3</span>
        <span class="tag lang-inline" data-lang="ru">Защита буфера обмена</span>
        <span class="tag lang-inline" data-lang="ru">Оповещения в реальном времени</span>
        <span class="tag lang-inline" data-lang="ja">MV3拡張</span>
        <span class="tag lang-inline" data-lang="ja">クリップボード保護</span>
        <span class="tag lang-inline" data-lang="ja">リアルタイム警告</span>
        <span class="tag lang-inline" data-lang="ko">MV3 확장</span>
        <span class="tag lang-inline" data-lang="ko">클립보드 보호</span>
        <span class="tag lang-inline" data-lang="ko">실시간 경고</span>
        <span class="tag lang-inline" data-lang="zh">MV3 扩展</span>
        <span class="tag lang-inline" data-lang="zh">剪贴板防护</span>
        <span class="tag lang-inline" data-lang="zh">实时警报</span>
        <span class="tag lang-inline" data-lang="hi">MV3 एक्सटेंशन</span>
        <span class="tag lang-inline" data-lang="hi">क्लिपबोर्ड सुरक्षा</span>
        <span class="tag lang-inline" data-lang="hi">रीयल-टाइम अलर्ट</span>
        <span class="tag lang-inline" data-lang="ar">امتداد MV3</span>
        <span class="tag lang-inline" data-lang="ar">حماية الحافظة</span>
        <span class="tag lang-inline" data-lang="ar">تنبيهات فورية</span>
        <span class="tag lang-inline" data-lang="he">הרחבת MV3</span>
        <span class="tag lang-inline" data-lang="he">הגנת לוח גזירים</span>
        <span class="tag lang-inline" data-lang="he">התראות בזמן אמת</span>
      </div>
      <div class="cta reveal delay-2">
        <a class="button primary cta-pulse" href="https://chromewebstore.google.com/detail/clickfix-mitigator/nmldafmgfcfopjoigbmmlmcnininifaa" target="_blank" rel="noopener">
          <span class="lang-inline" data-lang="es">Instalar extension</span>
          <span class="lang-inline" data-lang="en">Install Extension</span>
          <span class="lang-inline" data-lang="pt">Instalar extensao</span>
          <span class="lang-inline" data-lang="fr">Installer l extension</span>
          <span class="lang-inline" data-lang="de">Erweiterung installieren</span>
          <span class="lang-inline" data-lang="nl">Extensie installeren</span>
          <span class="lang-inline" data-lang="ca">Instal·lar extensio</span>
          <span class="lang-inline" data-lang="ru">Установить расширение</span>
          <span class="lang-inline" data-lang="ja">拡張機能をインストール</span>
          <span class="lang-inline" data-lang="ko">확장 프로그램 설치</span>
          <span class="lang-inline" data-lang="zh">安装扩展</span>
          <span class="lang-inline" data-lang="hi">एक्सटेंशन इंस्टॉल करें</span>
          <span class="lang-inline" data-lang="ar">تثبيت الامتداد</span>
          <span class="lang-inline" data-lang="he">התקן תוסף</span>
        </a>
        <a class="button secondary" href="https://addons.mozilla.org/en-GB/firefox/addon/clickfix-mitigator/" target="_blank" rel="noopener">
          <span class="lang-inline" data-lang="es">Instalar en Firefox</span>
          <span class="lang-inline" data-lang="en">Install on Firefox</span>
        </a>
        <a class="button secondary" href="#components">
          <span class="lang-inline" data-lang="es">Ver Componentes</span>
          <span class="lang-inline" data-lang="en">See Components</span>
          <span class="lang-inline" data-lang="pt">Ver Componentes</span>
          <span class="lang-inline" data-lang="fr">Voir les Composants</span>
          <span class="lang-inline" data-lang="de">Komponenten ansehen</span>
          <span class="lang-inline" data-lang="nl">Componenten bekijken</span>
          <span class="lang-inline" data-lang="ca">Veure components</span>
          <span class="lang-inline" data-lang="ru">Посмотреть компоненты</span>
          <span class="lang-inline" data-lang="ja">コンポーネントを見る</span>
          <span class="lang-inline" data-lang="ko">구성 요소 보기</span>
          <span class="lang-inline" data-lang="zh">查看组件</span>
          <span class="lang-inline" data-lang="hi">कंपोनेंट्स देखें</span>
          <span class="lang-inline" data-lang="ar">عرض المكونات</span>
          <span class="lang-inline" data-lang="he">הצג רכיבים</span>
        </a>
      </div>
      <div class="cta-note reveal delay-2">
        <span class="lang-inline" data-lang="es">Ya tienes backend?</span>
        <span class="lang-inline" data-lang="en">Already have the backend?</span>
        <span class="lang-inline" data-lang="pt">Ja tem o backend?</span>
        <span class="lang-inline" data-lang="fr">Vous avez deja le backend?</span>
        <span class="lang-inline" data-lang="de">Hast du bereits das Backend?</span>
        <span class="lang-inline" data-lang="nl">Heb je al de backend?</span>
        <span class="lang-inline" data-lang="ca">Ja tens backend?</span>
        <span class="lang-inline" data-lang="ru">У вас уже есть бэкенд?</span>
        <span class="lang-inline" data-lang="ja">すでにバックエンドがありますか？</span>
        <span class="lang-inline" data-lang="ko">이미 백엔드가 있나요?</span>
        <span class="lang-inline" data-lang="zh">已经有后端了吗？</span>
        <span class="lang-inline" data-lang="hi">क्या आपके पास पहले से backend है?</span>
        <span class="lang-inline" data-lang="ar">لديك بالفعل الواجهة الخلفية؟</span>
        <span class="lang-inline" data-lang="he">כבר יש לך backend?</span>
        <a href="dashboard.php">
          <span class="lang-inline" data-lang="es">Abrir dashboard</span>
          <span class="lang-inline" data-lang="en">Open dashboard</span>
          <span class="lang-inline" data-lang="pt">Abrir dashboard</span>
          <span class="lang-inline" data-lang="fr">Ouvrir le dashboard</span>
          <span class="lang-inline" data-lang="de">Dashboard oeffnen</span>
          <span class="lang-inline" data-lang="nl">Dashboard openen</span>
          <span class="lang-inline" data-lang="ca">Obrir dashboard</span>
          <span class="lang-inline" data-lang="ru">Открыть дашборд</span>
          <span class="lang-inline" data-lang="ja">ダッシュボードを開く</span>
          <span class="lang-inline" data-lang="ko">대시보드 열기</span>
          <span class="lang-inline" data-lang="zh">打开仪表板</span>
          <span class="lang-inline" data-lang="hi">डैशबोर्ड खोलें</span>
          <span class="lang-inline" data-lang="ar">فتح لوحة التحكم</span>
          <span class="lang-inline" data-lang="he">פתח דשבורד</span>
        </a>
      </div>
        </div>
        <div class="hero-panel reveal delay-3">
          <div class="hero-panel-header">
            <span class="status-dot"></span>
            <span class="lang-inline" data-lang="es">Telemetria en vivo</span>
            <span class="lang-inline" data-lang="en">Live telemetry</span>
            <span class="lang-inline" data-lang="pt">Telemetria ao vivo</span>
            <span class="lang-inline" data-lang="fr">Telemetrie en direct</span>
            <span class="lang-inline" data-lang="de">Live Telemetrie</span>
            <span class="lang-inline" data-lang="nl">Live telemetrie</span>
            <span class="lang-inline" data-lang="ca">Telemetria en directe</span>
            <span class="lang-inline" data-lang="ru">Онлайн телеметрия</span>
            <span class="lang-inline" data-lang="ja">ライブテレメトリ</span>
            <span class="lang-inline" data-lang="ko">실시간 텔레메트리</span>
            <span class="lang-inline" data-lang="zh">实时遥测</span>
            <span class="lang-inline" data-lang="hi">लाइव टेलीमेट्री</span>
            <span class="lang-inline" data-lang="ar">قياس عن بعد مباشر</span>
            <span class="lang-inline" data-lang="he">טלמטריה חיה</span>
          </div>
          <div class="hero-panel-grid">
            <div class="hero-metric">
              <span class="lang-inline" data-lang="es">Señales / 24h</span>
              <span class="lang-inline" data-lang="en">Signals / 24h</span>
              <span class="lang-inline" data-lang="pt">Sinais / 24h</span>
              <span class="lang-inline" data-lang="fr">Signaux / 24h</span>
              <span class="lang-inline" data-lang="de">Signale / 24h</span>
              <span class="lang-inline" data-lang="nl">Signalen / 24u</span>
              <span class="lang-inline" data-lang="ca">Senyals / 24h</span>
              <span class="lang-inline" data-lang="ru">Сигналы / 24ч</span>
              <span class="lang-inline" data-lang="ja">シグナル / 24h</span>
              <span class="lang-inline" data-lang="ko">신호 / 24h</span>
              <span class="lang-inline" data-lang="zh">信号 / 24h</span>
              <span class="lang-inline" data-lang="hi">सिग्नल / 24h</span>
              <span class="lang-inline" data-lang="ar">اشارات / 24س</span>
              <span class="lang-inline" data-lang="he">סיגנלים / 24ש</span>
              <strong data-telemetry-value="alerts_24h">--</strong>
            </div>
            <div class="hero-metric">
              <span class="lang-inline" data-lang="es">Bloqueos de portapapeles</span>
              <span class="lang-inline" data-lang="en">Clipboard blocks</span>
              <span class="lang-inline" data-lang="pt">Bloqueios de area de transferencia</span>
              <span class="lang-inline" data-lang="fr">Blocages presse-papiers</span>
              <span class="lang-inline" data-lang="de">Zwischenablage-Blocks</span>
              <span class="lang-inline" data-lang="nl">Klembordblokkades</span>
              <span class="lang-inline" data-lang="ca">Bloquejos del portapapers</span>
              <span class="lang-inline" data-lang="ru">Блокировки буфера</span>
              <span class="lang-inline" data-lang="ja">クリップボード遮断</span>
              <span class="lang-inline" data-lang="ko">클립보드 차단</span>
              <span class="lang-inline" data-lang="zh">剪贴板阻断</span>
              <span class="lang-inline" data-lang="hi">क्लिपबोर्ड ब्लॉक</span>
              <span class="lang-inline" data-lang="ar">حظر الحافظة</span>
              <span class="lang-inline" data-lang="he">חסימות לוח</span>
              <strong data-telemetry-value="blocks_24h">--</strong>
            </div>
            <div class="hero-metric">
              <span class="lang-inline" data-lang="es">Regiones en riesgo</span>
              <span class="lang-inline" data-lang="en">Risk regions</span>
              <span class="lang-inline" data-lang="pt">Regioes de risco</span>
              <span class="lang-inline" data-lang="fr">Regions a risque</span>
              <span class="lang-inline" data-lang="de">Risikoregionen</span>
              <span class="lang-inline" data-lang="nl">Risicogebieden</span>
              <span class="lang-inline" data-lang="ca">Regions de risc</span>
              <span class="lang-inline" data-lang="ru">Риск-регионы</span>
              <span class="lang-inline" data-lang="ja">リスク地域</span>
              <span class="lang-inline" data-lang="ko">위험 지역</span>
              <span class="lang-inline" data-lang="zh">风险区域</span>
              <span class="lang-inline" data-lang="hi">जोखिम क्षेत्र</span>
              <span class="lang-inline" data-lang="ar">مناطق عالية المخاطر</span>
              <span class="lang-inline" data-lang="he">אזורי סיכון</span>
              <strong data-telemetry-value="countries_count">--</strong>
            </div>
            <div class="hero-metric">
              <span class="lang-inline" data-lang="es">Dominios observados</span>
              <span class="lang-inline" data-lang="en">Observed domains</span>
              <span class="lang-inline" data-lang="pt">Dominios observados</span>
              <span class="lang-inline" data-lang="fr">Domaines observes</span>
              <span class="lang-inline" data-lang="de">Beobachtete Domains</span>
              <span class="lang-inline" data-lang="nl">Waargenomen domeinen</span>
              <span class="lang-inline" data-lang="ca">Dominis observats</span>
              <span class="lang-inline" data-lang="ru">Активные агенты</span>
              <span class="lang-inline" data-lang="ja">アクティブエージェント</span>
              <span class="lang-inline" data-lang="ko">활성 에이전트</span>
              <span class="lang-inline" data-lang="zh">活跃代理</span>
              <span class="lang-inline" data-lang="hi">सक्रिय एजेंट</span>
              <span class="lang-inline" data-lang="ar">وكلاء نشطون</span>
              <span class="lang-inline" data-lang="he">סוכנים פעילים</span>
              <strong data-telemetry-value="unique_hosts">--</strong>
            </div>
          </div>
          <div class="surveillance-hud">
            <div class="surveillance-radar">
              <canvas id="hero-leaflet-map" class="hero-leaflet-map" aria-label="Live global detections map"></canvas>
              <div class="hero-map-badge" id="hero-map-badge"></div>
              <div class="radar-caption" data-i18n-text="radar_feed_live">GLOBAL DETECTION MAP</div>
            </div>
            <div class="stream-title" data-i18n-text="threat_stream_title">Latest detections</div>
            <div class="stream-marquee">
              <div class="stream-track" id="hero-threat-stream" data-i18n-text="threat_no_data">no telemetry yet</div>
            </div>
          </div>
          <div class="hero-geo-card" id="hero-geo-card">
            <div class="hero-geo-card-head">
              <div class="hero-geo-card-copy">
                <span class="hero-geo-eyebrow" data-geo-text="eyebrow"><?= htmlspecialchars(index_static_text('geo_eyebrow', $initialLang), ENT_QUOTES, 'UTF-8'); ?></span>
                <strong id="hero-geo-place">--</strong>
              </div>
              <span class="hero-geo-lang" id="hero-geo-lang">EN</span>
            </div>
            <p class="hero-geo-summary" id="hero-geo-summary"><?= htmlspecialchars(index_static_text('geo_summary_empty', $initialLang), ENT_QUOTES, 'UTF-8'); ?></p>
            <div class="hero-geo-grid">
              <div class="hero-geo-item">
                <span data-geo-text="label_region"><?= htmlspecialchars(index_static_text('geo_region', $initialLang), ENT_QUOTES, 'UTF-8'); ?></span>
                <strong id="hero-geo-region">--</strong>
              </div>
              <div class="hero-geo-item">
                <span data-geo-text="label_timezone"><?= htmlspecialchars(index_static_text('geo_timezone', $initialLang), ENT_QUOTES, 'UTF-8'); ?></span>
                <strong id="hero-geo-timezone">--</strong>
              </div>
              <div class="hero-geo-item">
                <span data-geo-text="label_network"><?= htmlspecialchars(index_static_text('geo_network', $initialLang), ENT_QUOTES, 'UTF-8'); ?></span>
                <strong id="hero-geo-network">--</strong>
              </div>
              <div class="hero-geo-item">
                <span data-geo-text="label_language"><?= htmlspecialchars(index_static_text('geo_language', $initialLang), ENT_QUOTES, 'UTF-8'); ?></span>
                <strong id="hero-geo-language">--</strong>
              </div>
            </div>
            <div class="hero-geo-tags" id="hero-geo-tags"></div>
          </div>
          <div class="hero-flow">
            <div class="hero-flow-step">
              <span class="lang-inline" data-lang="es">Detecta comandos contextuales</span>
              <span class="lang-inline" data-lang="en">Detect contextual commands</span>
              <span class="lang-inline" data-lang="pt">Detecte comandos contextuais</span>
              <span class="lang-inline" data-lang="fr">Detecter les commandes contextuelles</span>
              <span class="lang-inline" data-lang="de">Kontextbezogene Befehle erkennen</span>
              <span class="lang-inline" data-lang="nl">Contextuele commando s detecteren</span>
              <span class="lang-inline" data-lang="ca">Detecta comandes contextuals</span>
              <span class="lang-inline" data-lang="ru">Выявлять контекстные команды</span>
              <span class="lang-inline" data-lang="ja">文脈コマンドを検知</span>
              <span class="lang-inline" data-lang="ko">맥락 명령 감지</span>
              <span class="lang-inline" data-lang="zh">检测上下文命令</span>
              <span class="lang-inline" data-lang="hi">संदर्भीय कमांड पहचानें</span>
              <span class="lang-inline" data-lang="ar">كشف اوامر سياقية</span>
              <span class="lang-inline" data-lang="he">זיהוי פקודות הקשר</span>
              <strong>01</strong>
            </div>
            <div class="hero-flow-step">
              <span class="lang-inline" data-lang="es">Interrumpe y alerta</span>
              <span class="lang-inline" data-lang="en">Interrupt & alert</span>
              <span class="lang-inline" data-lang="pt">Interrompa e alerte</span>
              <span class="lang-inline" data-lang="fr">Interrompre et alerter</span>
              <span class="lang-inline" data-lang="de">Unterbrechen und warnen</span>
              <span class="lang-inline" data-lang="nl">Onderbreek en waarschuw</span>
              <span class="lang-inline" data-lang="ca">Interromp i alerta</span>
              <span class="lang-inline" data-lang="ru">Прервать и предупредить</span>
              <span class="lang-inline" data-lang="ja">遮断と警告</span>
              <span class="lang-inline" data-lang="ko">차단 및 경고</span>
              <span class="lang-inline" data-lang="zh">阻断并告警</span>
              <span class="lang-inline" data-lang="hi">रोकें और अलर्ट करें</span>
              <span class="lang-inline" data-lang="ar">ايقاف وتنبيه</span>
              <span class="lang-inline" data-lang="he">עצירה והתראה</span>
              <strong>02</strong>
            </div>
            <div class="hero-flow-step">
              <span class="lang-inline" data-lang="es">Captura evidencia</span>
              <span class="lang-inline" data-lang="en">Capture evidence</span>
              <span class="lang-inline" data-lang="pt">Capture evidencias</span>
              <span class="lang-inline" data-lang="fr">Capturer les preuves</span>
              <span class="lang-inline" data-lang="de">Beweise erfassen</span>
              <span class="lang-inline" data-lang="nl">Bewijs vastleggen</span>
              <span class="lang-inline" data-lang="ca">Captura evidencia</span>
              <span class="lang-inline" data-lang="ru">Собрать доказательства</span>
              <span class="lang-inline" data-lang="ja">証拠を取得</span>
              <span class="lang-inline" data-lang="ko">증거 수집</span>
              <span class="lang-inline" data-lang="zh">捕获证据</span>
              <span class="lang-inline" data-lang="hi">साक्ष्य कैप्चर करें</span>
              <span class="lang-inline" data-lang="ar">التقاط الادلة</span>
              <span class="lang-inline" data-lang="he">איסוף ראיות</span>
              <strong>03</strong>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="card preview-section reveal" id="dashboard-preview">
      <div class="preview-header">
        <div class="pill">
          <span class="lang-inline" data-lang="es">Vista previa del dashboard</span>
          <span class="lang-inline" data-lang="en">Dashboard preview</span>
          <span class="lang-inline" data-lang="pt">Previa do dashboard</span>
          <span class="lang-inline" data-lang="fr">Apercu du tableau</span>
          <span class="lang-inline" data-lang="de">Dashboard-Vorschau</span>
          <span class="lang-inline" data-lang="nl">Dashboard-voorproef</span>
          <span class="lang-inline" data-lang="ca">Vista previa del dashboard</span>
          <span class="lang-inline" data-lang="ru">Предпросмотр панели</span>
          <span class="lang-inline" data-lang="ja">ダッシュボードのプレビュー</span>
          <span class="lang-inline" data-lang="ko">대시보드 미리보기</span>
          <span class="lang-inline" data-lang="zh">仪表板预览</span>
          <span class="lang-inline" data-lang="hi">डैशबोर्ड प्रीव्यू</span>
          <span class="lang-inline" data-lang="ar">معاينة لوحة التحكم</span>
          <span class="lang-inline" data-lang="he">תצוגה מקדימה ללוח</span>
        </div>
        <h2 class="section-title lang-block" data-lang="es">Pulso público con claridad para analistas.</h2>
        <h2 class="section-title lang-block" data-lang="en">Public pulse with analyst-grade clarity.</h2>
        <h2 class="section-title lang-block" data-lang="pt">Pulso publico com clareza para analistas.</h2>
        <h2 class="section-title lang-block" data-lang="fr">Pouls public avec clarte pour les analystes.</h2>
        <h2 class="section-title lang-block" data-lang="de">Oeffentlicher Puls mit Klarheit fuer Analysten.</h2>
        <h2 class="section-title lang-block" data-lang="nl">Publieke pulse met duidelijkheid voor analisten.</h2>
        <h2 class="section-title lang-block" data-lang="ca">Pols public amb claredat per analistes.</h2>
        <h2 class="section-title lang-block" data-lang="ru">Публичный пульс с ясностью для аналитиков.</h2>
        <h2 class="section-title lang-block" data-lang="ja">アナリスト向けの明瞭な公開パルス。</h2>
        <h2 class="section-title lang-block" data-lang="ko">분석가를 위한 명확한 공개 펄스.</h2>
        <h2 class="section-title lang-block" data-lang="zh">面向分析师的清晰公共脉冲。</h2>
        <h2 class="section-title lang-block" data-lang="hi">एनालिस्ट के लिए स्पष्ट पब्लिक पल्स।</h2>
        <h2 class="section-title lang-block" data-lang="ar">نبض عام بوضوح للمحللين.</h2>
        <h2 class="section-title lang-block" data-lang="he">דופק ציבורי עם בהירות לאנליסטים.</h2>
        <p class="section-sub lang-block" data-lang="es">Snapshot agregado y seguro. Solicita acceso para intel completo, alertas y nuevos dominios maliciosos.</p>
        <p class="section-sub lang-block" data-lang="en">Aggregated, safe snapshot. Request access for full intel, alerts, and new malicious domains.</p>
        <p class="section-sub lang-block" data-lang="pt">Snapshot agregado e seguro. Solicite acesso para intel completa, alertas e novos dominios maliciosos.</p>
        <p class="section-sub lang-block" data-lang="fr">Instantane agrege et securise. Demandez l acces pour l intel complete, les alertes et les nouveaux domaines malveillants.</p>
        <p class="section-sub lang-block" data-lang="de">Aggregierter, sicherer Snapshot. Zugang anfragen fuer volle Intel, Alerts und neue boesartige Domains.</p>
        <p class="section-sub lang-block" data-lang="nl">Geaggregeerde, veilige snapshot. Vraag toegang voor volledige intel, alerts en nieuwe kwaadaardige domeinen.</p>
        <p class="section-sub lang-block" data-lang="ca">Snapshot agregat i segur. Demana acces per intel completa, alertes i nous dominis maliciosos.</p>
        <p class="section-sub lang-block" data-lang="ru">Безопасный агрегированный снимок. Запросите доступ к полной разведке, алертам и новым доменам.</p>
        <p class="section-sub lang-block" data-lang="ja">安全に集約されたスナップショット。完全なインテリジェンス、アラート、新規悪性ドメインにアクセス申請。</p>
        <p class="section-sub lang-block" data-lang="ko">안전하게 집계된 스냅샷. 전체 인텔, 알림, 신규 악성 도메인 접근을 요청하세요.</p>
        <p class="section-sub lang-block" data-lang="zh">安全聚合快照。申请访问完整情报、警报和新恶意域名。</p>
        <p class="section-sub lang-block" data-lang="hi">सुरक्षित समेकित स्नैपशॉट। पूर्ण इंटेल, अलर्ट और नए दुर्भावनापूर्ण डोमेन के लिए एक्सेस मांगें।</p>
        <p class="section-sub lang-block" data-lang="ar">لقطة مجمعة وآمنة. اطلب الوصول لاستخبارات كاملة وتنبيهات ونطاقات خبيثة جديدة.</p>
        <p class="section-sub lang-block" data-lang="he">תמונה מצטברת ובטוחה. בקש גישה למודיעין מלא, התראות ודומיינים זדוניים חדשים.</p>
      </div>

      <div class="preview-onboarding">
        <article class="quick-action-card">
          <div class="quick-action-head"><span class="quick-action-step">1</span><span data-i18n-text="quick_install_title">Install extension</span></div>
          <p class="quick-action-note" data-i18n-text="quick_install_note">Deploy quickly from Chrome Web Store in managed environments.</p>
          <a class="button secondary" href="https://chromewebstore.google.com/detail/clickfix-mitigator/nmldafmgfcfopjoigbmmlmcnininifaa" target="_blank" rel="noopener" data-i18n-text="quick_install_button">Install extension</a>
          <a class="button secondary" href="https://addons.mozilla.org/en-GB/firefox/addon/clickfix-mitigator/" target="_blank" rel="noopener">
            <span class="lang-inline" data-lang="es">Instalar en Firefox</span>
            <span class="lang-inline" data-lang="en">Install on Firefox</span>
          </a>
        </article>
        <article class="quick-action-card">
          <div class="quick-action-head"><span class="quick-action-step">2</span><span data-i18n-text="quick_access_title">Request access</span></div>
          <p class="quick-action-note" data-i18n-text="quick_access_note">Request analyst onboarding to work alerts, events, and evidence.</p>
          <a class="button secondary" href="#dashboard-preview-access" data-i18n-text="quick_access_button">Request access</a>
        </article>
        <article class="quick-action-card">
          <div class="quick-action-head"><span class="quick-action-step">3</span><span data-i18n-text="quick_login_title">Open console login</span></div>
          <p class="quick-action-note" data-i18n-text="quick_login_note">If you already have an account, go straight to the authenticated console.</p>
          <a class="button primary" href="dashboard.php?page=access&amp;public=1" data-i18n-text="quick_login_button">Open login</a>
          <a class="button secondary" href="demo/index.php" target="_blank" rel="noopener" data-i18n-text="quick_demos_button">View demos</a>
        </article>
      </div>

      <div class="preview-grid">
        <div class="preview-left-column">
          <div class="preview-metrics">
          <div class="metric-card">
            <span class="metric-label lang-inline" data-lang="es">Alertas totales</span>
            <span class="metric-label lang-inline" data-lang="en">Total alerts</span>
            <span class="metric-label lang-inline" data-lang="pt">Alertas totais</span>
            <span class="metric-label lang-inline" data-lang="fr">Alertes totales</span>
            <span class="metric-label lang-inline" data-lang="de">Gesamtwarnungen</span>
            <span class="metric-label lang-inline" data-lang="nl">Totaal alerts</span>
            <span class="metric-label lang-inline" data-lang="ca">Alertes totals</span>
            <span class="metric-label lang-inline" data-lang="ru">Всего алертов</span>
            <span class="metric-label lang-inline" data-lang="ja">総アラート数</span>
            <span class="metric-label lang-inline" data-lang="ko">총 경고</span>
            <span class="metric-label lang-inline" data-lang="zh">警报总数</span>
            <span class="metric-label lang-inline" data-lang="hi">कुल अलर्ट</span>
            <span class="metric-label lang-inline" data-lang="ar">اجمالي التنبيهات</span>
            <span class="metric-label lang-inline" data-lang="he">סך התראות</span>
            <div class="metric-value" data-preview-metric="total_alerts">--</div>
          </div>
          <div class="metric-card">
            <span class="metric-label lang-inline" data-lang="es">Bloqueos totales</span>
            <span class="metric-label lang-inline" data-lang="en">Total blocks</span>
            <span class="metric-label lang-inline" data-lang="pt">Bloqueios totais</span>
            <span class="metric-label lang-inline" data-lang="fr">Blocages totaux</span>
            <span class="metric-label lang-inline" data-lang="de">Gesamtblockierungen</span>
            <span class="metric-label lang-inline" data-lang="nl">Totaal blokkeringen</span>
            <span class="metric-label lang-inline" data-lang="ca">Bloquejos totals</span>
            <span class="metric-label lang-inline" data-lang="ru">Всего блокировок</span>
            <span class="metric-label lang-inline" data-lang="ja">総ブロック数</span>
            <span class="metric-label lang-inline" data-lang="ko">총 차단</span>
            <span class="metric-label lang-inline" data-lang="zh">阻断总数</span>
            <span class="metric-label lang-inline" data-lang="hi">कुल ब्लॉक</span>
            <span class="metric-label lang-inline" data-lang="ar">اجمالي الحظر</span>
            <span class="metric-label lang-inline" data-lang="he">סך חסימות</span>
            <div class="metric-value" data-preview-metric="total_blocks">--</div>
          </div>
          <div class="metric-card">
            <span class="metric-label lang-inline" data-lang="es">Nuevos dominios / 24h</span>
            <span class="metric-label lang-inline" data-lang="en">New domains / 24h</span>
            <span class="metric-label lang-inline" data-lang="pt">Novos dominios / 24h</span>
            <span class="metric-label lang-inline" data-lang="fr">Nouveaux domaines / 24h</span>
            <span class="metric-label lang-inline" data-lang="de">Neue Domains / 24h</span>
            <span class="metric-label lang-inline" data-lang="nl">Nieuwe domeinen / 24h</span>
            <span class="metric-label lang-inline" data-lang="ca">Dominis nous / 24h</span>
            <span class="metric-label lang-inline" data-lang="ru">Уникальные пользователи</span>
            <span class="metric-label lang-inline" data-lang="ja">ユニークユーザー</span>
            <span class="metric-label lang-inline" data-lang="ko">고유 사용자</span>
            <span class="metric-label lang-inline" data-lang="zh">唯一用户</span>
            <span class="metric-label lang-inline" data-lang="hi">यूनिक यूजर</span>
            <span class="metric-label lang-inline" data-lang="ar">مستخدمون فريدون</span>
            <span class="metric-label lang-inline" data-lang="he">משתמשים ייחודיים</span>
            <div class="metric-value" data-preview-metric="new_domains_24h">--</div>
            <div class="metric-foot">
              <span class="lang-inline" data-lang="es">Primer avistamiento reciente</span>
              <span class="lang-inline" data-lang="en">Recent first sightings</span>
              <span class="lang-inline" data-lang="pt">Primeiros avistamentos recentes</span>
              <span class="lang-inline" data-lang="fr">Premiers apercus recents</span>
              <span class="lang-inline" data-lang="de">Aktuelle Erstsichtungen</span>
              <span class="lang-inline" data-lang="nl">Recente eerste waarnemingen</span>
              <span class="lang-inline" data-lang="ca">Primers avistaments recents</span>
              <span class="lang-inline" data-lang="ru">Последние 24ч</span>
              <span class="lang-inline" data-lang="ja">直近24時間</span>
              <span class="lang-inline" data-lang="ko">최근 24시간</span>
              <span class="lang-inline" data-lang="zh">最近24小时</span>
              <span class="lang-inline" data-lang="hi">पिछले 24h</span>
              <span class="lang-inline" data-lang="ar">آخر 24 ساعة</span>
              <span class="lang-inline" data-lang="he">24 השעות האחרונות</span>
            </div>
          </div>
          <div class="metric-card">
            <span class="metric-label lang-inline" data-lang="es">Dominios únicos</span>
            <span class="metric-label lang-inline" data-lang="en">Unique domains</span>
            <span class="metric-label lang-inline" data-lang="pt">Dominios unicos</span>
            <span class="metric-label lang-inline" data-lang="fr">Domaines uniques</span>
            <span class="metric-label lang-inline" data-lang="de">Einzigartige Domains</span>
            <span class="metric-label lang-inline" data-lang="nl">Unieke domeinen</span>
            <span class="metric-label lang-inline" data-lang="ca">Dominis unics</span>
            <span class="metric-label lang-inline" data-lang="ru">Уникальные домены</span>
            <span class="metric-label lang-inline" data-lang="ja">ユニークドメイン</span>
            <span class="metric-label lang-inline" data-lang="ko">고유 도메인</span>
            <span class="metric-label lang-inline" data-lang="zh">唯一域名</span>
            <span class="metric-label lang-inline" data-lang="hi">यूनिक डोमेन</span>
            <span class="metric-label lang-inline" data-lang="ar">نطاقات فريدة</span>
            <span class="metric-label lang-inline" data-lang="he">דומיינים ייחודיים</span>
            <div class="metric-value" data-preview-metric="unique_hosts">--</div>
          </div>
          <div class="metric-card">
            <span class="metric-label lang-inline" data-lang="es">Tasa de bloqueo</span>
            <span class="metric-label lang-inline" data-lang="en">Block rate</span>
            <span class="metric-label lang-inline" data-lang="pt">Taxa de bloqueio</span>
            <span class="metric-label lang-inline" data-lang="fr">Taux de blocage</span>
            <span class="metric-label lang-inline" data-lang="de">Blockrate</span>
            <span class="metric-label lang-inline" data-lang="nl">Blokkeringsgraad</span>
            <span class="metric-label lang-inline" data-lang="ca">Taxa de bloqueig</span>
            <span class="metric-label lang-inline" data-lang="ru">Доля блокировок</span>
            <span class="metric-label lang-inline" data-lang="ja">ブロック率</span>
            <span class="metric-label lang-inline" data-lang="ko">차단 비율</span>
            <span class="metric-label lang-inline" data-lang="zh">拦截率</span>
            <span class="metric-label lang-inline" data-lang="hi">ब्लॉक रेट</span>
            <span class="metric-label lang-inline" data-lang="ar">معدل الحظر</span>
            <span class="metric-label lang-inline" data-lang="he">שיעור חסימה</span>
            <div class="metric-value" data-preview-metric="block_rate">--</div>
            <div class="metric-foot">
              <span class="lang-inline" data-lang="es">Ultima actualizacion</span>
              <span class="lang-inline" data-lang="en">Last update</span>
              <span class="lang-inline" data-lang="pt">Ultima atualizacao</span>
              <span class="lang-inline" data-lang="fr">Derniere mise a jour</span>
              <span class="lang-inline" data-lang="de">Letztes Update</span>
              <span class="lang-inline" data-lang="nl">Laatste update</span>
              <span class="lang-inline" data-lang="ca">Ultima actualitzacio</span>
              <span class="lang-inline" data-lang="ru">Последнее обновление</span>
              <span class="lang-inline" data-lang="ja">最終更新</span>
              <span class="lang-inline" data-lang="ko">마지막 업데이트</span>
              <span class="lang-inline" data-lang="zh">最后更新</span>
              <span class="lang-inline" data-lang="hi">आखिरी अपडेट</span>
              <span class="lang-inline" data-lang="ar">آخر تحديث</span>
              <span class="lang-inline" data-lang="he">עדכון אחרון</span>
              <span data-preview-metric="last_update">--</span>
            </div>
          </div>
          </div>

          <div class="preview-spotlight">
            <div class="chart-header">
              <span class="lang-inline" data-lang="es">Investigaciones públicas</span>
              <span class="lang-inline" data-lang="en">Public investigations</span>
              <span class="lang-inline" data-lang="pt">Investigacoes publicas</span>
              <span class="lang-inline" data-lang="fr">Investigations publiques</span>
              <span class="lang-inline" data-lang="de">Oeffentliche Untersuchungen</span>
              <span class="lang-inline" data-lang="nl">Publieke onderzoeken</span>
              <span class="lang-inline" data-lang="ca">Investigacions publiques</span>
              <span class="lang-inline" data-lang="ru">Публичные исследования</span>
              <span class="lang-inline" data-lang="ja">公開調査</span>
              <span class="lang-inline" data-lang="ko">공개 조사</span>
              <span class="lang-inline" data-lang="zh">公开调查</span>
              <span class="lang-inline" data-lang="hi">सार्वजनिक जांच</span>
              <span class="lang-inline" data-lang="ar">تحقيقات عامة</span>
              <span class="lang-inline" data-lang="he">חקירות פומביות</span>
              <span class="chart-value"><?= count($publicFeaturedInvestigations); ?></span>
            </div>
            <?php if (!empty($publicFeaturedInvestigations)): ?>
              <div class="preview-spotlight-list">
                <?php foreach (array_slice($publicFeaturedInvestigations, 0, 2) as $featuredInvestigation): ?>
                  <?php
                    $spotTitle = trim((string) ($featuredInvestigation['title'] ?? $featuredInvestigation['site_domain'] ?? ''));
                    $spotDomain = trim((string) ($featuredInvestigation['site_domain'] ?? '-'));
                    $spotSummary = trim((string) ($featuredInvestigation['summary'] ?? ''));
                    $spotShareUrl = '';
                    if (!empty($featuredInvestigation['share_token'])) {
                        $spotShareUrl = 'dashboard.php?page=investigation&share=' . urlencode((string) ($featuredInvestigation['share_token'] ?? ''));
                    }
                  ?>
                  <article class="preview-spotlight-item">
                    <div class="preview-spotlight-meta">
                      <span><?= htmlspecialchars($spotDomain, ENT_QUOTES, 'UTF-8'); ?></span>
                      <span><?= htmlspecialchars((string) ($featuredInvestigation['updated_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <strong><?= htmlspecialchars($spotTitle !== '' ? $spotTitle : $spotDomain, ENT_QUOTES, 'UTF-8'); ?></strong>
                    <div class="preview-spotlight-summary">
                      <?php if ($spotSummary !== ''): ?>
                        <?= htmlspecialchars($spotSummary, ENT_QUOTES, 'UTF-8'); ?>
                      <?php else: ?>
                        <span data-i18n-text="featured_spotlight_empty">Case summary pending publication.</span>
                      <?php endif; ?>
                    </div>
                    <?php if ($spotShareUrl !== ''): ?>
                      <div class="preview-actions">
                        <a class="button secondary" href="<?= htmlspecialchars($spotShareUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">
                          <span class="lang-inline" data-lang="es">Abrir investigación</span>
                          <span class="lang-inline" data-lang="en">Open investigation</span>
                          <span class="lang-inline" data-lang="pt">Abrir investigacao</span>
                          <span class="lang-inline" data-lang="fr">Ouvrir l investigation</span>
                          <span class="lang-inline" data-lang="de">Untersuchung oeffnen</span>
                          <span class="lang-inline" data-lang="nl">Onderzoek openen</span>
                          <span class="lang-inline" data-lang="ca">Obrir investigacio</span>
                          <span class="lang-inline" data-lang="ru">Открыть исследование</span>
                          <span class="lang-inline" data-lang="ja">調査を開く</span>
                          <span class="lang-inline" data-lang="ko">조사 열기</span>
                          <span class="lang-inline" data-lang="zh">打开调查</span>
                          <span class="lang-inline" data-lang="hi">जांच खोलें</span>
                          <span class="lang-inline" data-lang="ar">فتح التحقيق</span>
                          <span class="lang-inline" data-lang="he">פתח חקירה</span>
                        </a>
                      </div>
                    <?php endif; ?>
                  </article>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <div class="preview-spotlight-item">
                <strong>
                  <span class="lang-inline" data-lang="es">Casos públicos verificados</span>
                  <span class="lang-inline" data-lang="en">Verified public cases</span>
                  <span class="lang-inline" data-lang="pt">Casos publicos verificados</span>
                  <span class="lang-inline" data-lang="fr">Cas publics verifies</span>
                  <span class="lang-inline" data-lang="de">Verifizierte oeffentliche Faelle</span>
                  <span class="lang-inline" data-lang="nl">Geverifieerde publieke cases</span>
                  <span class="lang-inline" data-lang="ca">Casos publics verificats</span>
                  <span class="lang-inline" data-lang="ru">Проверенные публичные кейсы</span>
                  <span class="lang-inline" data-lang="ja">公開検証ケース</span>
                  <span class="lang-inline" data-lang="ko">검증된 공개 케이스</span>
                  <span class="lang-inline" data-lang="zh">已验证公开案例</span>
                  <span class="lang-inline" data-lang="hi">सत्यापित सार्वजनिक केस</span>
                  <span class="lang-inline" data-lang="ar">حالات عامة موثقة</span>
                  <span class="lang-inline" data-lang="he">מקרים פומביים מאומתים</span>
                </strong>
                <div class="preview-spotlight-summary">
                  <span class="lang-inline" data-lang="es">Cuando haya investigaciones aprobadas y compartidas, aparecerán aquí con su resumen operativo y acceso directo.</span>
                  <span class="lang-inline" data-lang="en">When approved public investigations are available, they will appear here with an operational summary and direct access.</span>
                  <span class="lang-inline" data-lang="pt">Quando houver investigacoes publicas aprovadas, aparecerao aqui com resumo operacional e acesso direto.</span>
                  <span class="lang-inline" data-lang="fr">Lorsque des investigations publiques approuvees seront disponibles, elles apparaitront ici avec resume et acces direct.</span>
                  <span class="lang-inline" data-lang="de">Sobald freigegebene oeffentliche Untersuchungen verfuegbar sind, erscheinen sie hier mit Kurzuebersicht und Direktzugriff.</span>
                  <span class="lang-inline" data-lang="nl">Zodra goedgekeurde publieke onderzoeken beschikbaar zijn, verschijnen ze hier met samenvatting en directe toegang.</span>
                  <span class="lang-inline" data-lang="ca">Quan hi hagi investigacions publiques aprovades, apareixeran aqui amb resum operatiu i acces directe.</span>
                  <span class="lang-inline" data-lang="ru">Когда появятся одобренные публичные расследования, они будут показаны здесь с кратким описанием и прямым доступом.</span>
                  <span class="lang-inline" data-lang="ja">承認された公開調査が利用可能になると、ここに概要と直接アクセスが表示されます。</span>
                  <span class="lang-inline" data-lang="ko">승인된 공개 조사가 생기면 여기에서 요약과 바로가기를 확인할 수 있습니다.</span>
                  <span class="lang-inline" data-lang="zh">一旦有经过批准的公开调查，它们会显示在这里并附带摘要和直接入口。</span>
                  <span class="lang-inline" data-lang="hi">जब स्वीकृत सार्वजनिक जांच उपलब्ध होंगी, वे यहां सारांश और सीधे एक्सेस के साथ दिखाई देंगी।</span>
                  <span class="lang-inline" data-lang="ar">عند توفر تحقيقات عامة معتمدة ستظهر هنا مع ملخص تشغيلي ووصول مباشر.</span>
                  <span class="lang-inline" data-lang="he">כאשר יהיו חקירות פומביות מאושרות הן יופיעו כאן עם תקציר וגישה ישירה.</span>
                </div>
                <div class="preview-actions">
                  <a class="button secondary" href="#dashboard-preview-access">
                    <span class="lang-inline" data-lang="es">Solicitar acceso</span>
                    <span class="lang-inline" data-lang="en">Request access</span>
                    <span class="lang-inline" data-lang="pt">Solicitar acesso</span>
                    <span class="lang-inline" data-lang="fr">Demander l acces</span>
                    <span class="lang-inline" data-lang="de">Zugang anfragen</span>
                    <span class="lang-inline" data-lang="nl">Toegang aanvragen</span>
                    <span class="lang-inline" data-lang="ca">Demanar acces</span>
                    <span class="lang-inline" data-lang="ru">Запросить доступ</span>
                    <span class="lang-inline" data-lang="ja">アクセスを申請</span>
                    <span class="lang-inline" data-lang="ko">접근 요청</span>
                    <span class="lang-inline" data-lang="zh">申请访问</span>
                    <span class="lang-inline" data-lang="hi">एक्सेस मांगें</span>
                    <span class="lang-inline" data-lang="ar">طلب الوصول</span>
                    <span class="lang-inline" data-lang="he">בקש גישה</span>
                  </a>
                  <a class="button secondary" href="demo/index.php" target="_blank" rel="noopener">
                    <span class="lang-inline" data-lang="es">Ver demos</span>
                    <span class="lang-inline" data-lang="en">View demos</span>
                    <span class="lang-inline" data-lang="pt">Ver demos</span>
                    <span class="lang-inline" data-lang="fr">Voir les demos</span>
                    <span class="lang-inline" data-lang="de">Demos ansehen</span>
                    <span class="lang-inline" data-lang="nl">Demos bekijken</span>
                    <span class="lang-inline" data-lang="ca">Veure demos</span>
                    <span class="lang-inline" data-lang="ru">Смотреть демо</span>
                    <span class="lang-inline" data-lang="ja">デモを見る</span>
                    <span class="lang-inline" data-lang="ko">데모 보기</span>
                    <span class="lang-inline" data-lang="zh">查看演示</span>
                    <span class="lang-inline" data-lang="hi">डेमो देखें</span>
                    <span class="lang-inline" data-lang="ar">عرض العروض</span>
                    <span class="lang-inline" data-lang="he">צפה בהדגמות</span>
                  </a>
                </div>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <div class="preview-charts">
          <div class="chart-card">
            <div class="chart-header">
              <span class="lang-inline" data-lang="es">Alertas (7d)</span>
              <span class="lang-inline" data-lang="en">Alerts (7d)</span>
              <span class="lang-inline" data-lang="pt">Alertas (7d)</span>
              <span class="lang-inline" data-lang="fr">Alertes (7j)</span>
              <span class="lang-inline" data-lang="de">Warnungen (7t)</span>
              <span class="lang-inline" data-lang="nl">Alerts (7d)</span>
              <span class="lang-inline" data-lang="ca">Alertes (7d)</span>
              <span class="lang-inline" data-lang="ru">Алерты (7д)</span>
              <span class="lang-inline" data-lang="ja">アラート (7日)</span>
              <span class="lang-inline" data-lang="ko">경고 (7일)</span>
              <span class="lang-inline" data-lang="zh">警报 (7天)</span>
              <span class="lang-inline" data-lang="hi">अलर्ट (7 दिन)</span>
              <span class="lang-inline" data-lang="ar">تنبيهات (7 ايام)</span>
              <span class="lang-inline" data-lang="he">התראות (7 ימים)</span>
              <span class="chart-value" data-preview-chart-value="daily">--</span>
            </div>
            <div class="sparkline" data-preview-chart="daily"></div>
          </div>
          <div class="chart-card">
            <div class="chart-header">
              <span class="lang-inline" data-lang="es">Bloqueos (7d)</span>
              <span class="lang-inline" data-lang="en">Blocks (7d)</span>
              <span class="lang-inline" data-lang="pt">Bloqueios (7d)</span>
              <span class="lang-inline" data-lang="fr">Blocages (7j)</span>
              <span class="lang-inline" data-lang="de">Blockierungen (7t)</span>
              <span class="lang-inline" data-lang="nl">Blokkeringen (7d)</span>
              <span class="lang-inline" data-lang="ca">Bloquejos (7d)</span>
              <span class="lang-inline" data-lang="ru">Блокировки (7д)</span>
              <span class="lang-inline" data-lang="ja">ブロック (7日)</span>
              <span class="lang-inline" data-lang="ko">차단 (7일)</span>
              <span class="lang-inline" data-lang="zh">阻断 (7天)</span>
              <span class="lang-inline" data-lang="hi">ब्लॉक (7 दिन)</span>
              <span class="lang-inline" data-lang="ar">حظر (7 ايام)</span>
              <span class="lang-inline" data-lang="he">חסימות (7 ימים)</span>
              <span class="chart-value" data-preview-chart-value="dailyBlocks">--</span>
            </div>
            <div class="sparkline" data-preview-chart="dailyBlocks"></div>
          </div>
          <div class="chart-card">
            <div class="chart-header">
              <span data-i18n-text="chart_activity_title">7d activity (alerts vs blocks)</span>
              <span class="chart-value" data-preview-chart-value="activityBars">--</span>
            </div>
            <div class="activity-bars" data-preview-chart="activityBars"></div>
            <div class="metric-foot" data-preview-chart-foot="activityBars" data-i18n-text="chart_activity_empty">No activity in the last 7 days.</div>
          </div>
          <div class="chart-card">
            <div class="chart-header">
              <span data-i18n-text="chart_heat_title">Surveillance matrix (7d)</span>
              <span class="chart-value" data-preview-chart-value="heatMatrix">--</span>
            </div>
            <div class="heat-matrix" data-preview-chart="heatMatrix"></div>
            <div class="metric-foot" data-preview-chart-foot="heatMatrix" data-i18n-text="chart_heat_empty">Waiting for telemetry to render the matrix.</div>
          </div>
        </div>
      </div>

      <div class="preview-row">
        <div class="preview-list">
          <div class="chart-header">
            <span class="lang-inline" data-lang="es">Radar reciente</span>
            <span class="lang-inline" data-lang="en">Recent radar</span>
            <span class="lang-inline" data-lang="pt">Radar recente</span>
            <span class="lang-inline" data-lang="fr">Radar recent</span>
            <span class="lang-inline" data-lang="de">Aktuelles Radar</span>
            <span class="lang-inline" data-lang="nl">Recente radar</span>
            <span class="lang-inline" data-lang="ca">Radar recent</span>
            <span class="lang-inline" data-lang="ru">Недавний радар</span>
            <span class="lang-inline" data-lang="ja">最近のレーダー</span>
            <span class="lang-inline" data-lang="ko">최근 레이더</span>
            <span class="lang-inline" data-lang="zh">最近雷达</span>
            <span class="lang-inline" data-lang="hi">हालिया रडार</span>
            <span class="lang-inline" data-lang="ar">رادار حديث</span>
            <span class="lang-inline" data-lang="he">רדאר אחרון</span>
          </div>
          <div class="recent-list" data-preview-recent></div>
          <div class="metric-foot" data-preview-empty>
            <span class="lang-inline" data-lang="es">Sin datos recientes.</span>
            <span class="lang-inline" data-lang="en">No recent data.</span>
            <span class="lang-inline" data-lang="pt">Sem dados recentes.</span>
            <span class="lang-inline" data-lang="fr">Aucune donnee recente.</span>
            <span class="lang-inline" data-lang="de">Keine aktuellen Daten.</span>
            <span class="lang-inline" data-lang="nl">Geen recente data.</span>
            <span class="lang-inline" data-lang="ca">Sense dades recents.</span>
            <span class="lang-inline" data-lang="ru">Нет свежих данных.</span>
            <span class="lang-inline" data-lang="ja">最近のデータはありません。</span>
            <span class="lang-inline" data-lang="ko">최근 데이터 없음.</span>
            <span class="lang-inline" data-lang="zh">暂无最近数据。</span>
            <span class="lang-inline" data-lang="hi">हालिया डेटा नहीं.</span>
            <span class="lang-inline" data-lang="ar">لا توجد بيانات حديثة.</span>
            <span class="lang-inline" data-lang="he">אין נתונים אחרונים.</span>
          </div>
        </div>
        <div class="preview-cta">
          <h3 class="lang-block" data-lang="es">¿Eres analista o investigador?</h3>
          <h3 class="lang-block" data-lang="en">Security analyst or researcher?</h3>
          <h3 class="lang-block" data-lang="pt">Analista ou pesquisador?</h3>
          <h3 class="lang-block" data-lang="fr">Analyste ou chercheur securite ?</h3>
          <h3 class="lang-block" data-lang="de">Security Analyst oder Researcher?</h3>
          <h3 class="lang-block" data-lang="nl">Security-analist of onderzoeker?</h3>
          <h3 class="lang-block" data-lang="ca">Analista o investigador?</h3>
          <h3 class="lang-block" data-lang="ru">Вы аналитик или исследователь?</h3>
          <h3 class="lang-block" data-lang="ja">アナリストまたは研究者ですか？</h3>
          <h3 class="lang-block" data-lang="ko">분석가 또는 연구자이신가요?</h3>
          <h3 class="lang-block" data-lang="zh">你是分析师或研究员吗？</h3>
          <h3 class="lang-block" data-lang="hi">क्या आप विश्लेषक या शोधकर्ता हैं?</h3>
          <h3 class="lang-block" data-lang="ar">هل انت محلل او باحث؟</h3>
          <h3 class="lang-block" data-lang="he">אנליסט או חוקר?</h3>
          <p class="lang-block" data-lang="es">Recibe updates, señales y nuevos dominios maliciosos en tiempo real. Solicita acceso al panel completo.</p>
          <p class="lang-block" data-lang="en">Get real-time updates, signals, and new malicious domains. Request access to the full console.</p>
          <p class="lang-block" data-lang="pt">Receba updates, sinais e novos dominios maliciosos em tempo real. Solicite acesso ao painel completo.</p>
          <p class="lang-block" data-lang="fr">Recevez des mises a jour, signaux et nouveaux domaines malveillants en temps reel. Demandez l acces au panneau complet.</p>
          <p class="lang-block" data-lang="de">Erhalte Echtzeit-Updates, Signale und neue boesartige Domains. Zugang zur Konsole anfragen.</p>
          <p class="lang-block" data-lang="nl">Ontvang realtime updates, signalen en nieuwe kwaadaardige domeinen. Vraag toegang aan tot de volledige console.</p>
          <p class="lang-block" data-lang="ca">Rep actualitzacions, senyals i nous dominis maliciosos en temps real. Demana acces al panell complet.</p>
          <p class="lang-block" data-lang="ru">Получайте обновления, сигналы и новые домены в реальном времени. Запросите доступ к полной консоли.</p>
          <p class="lang-block" data-lang="ja">リアルタイム更新、シグナル、新規悪性ドメインを受け取る。フルコンソールのアクセスを申請。</p>
          <p class="lang-block" data-lang="ko">실시간 업데이트, 신호, 신규 악성 도메인을 받아보세요. 전체 콘솔 접근을 요청하세요.</p>
          <p class="lang-block" data-lang="zh">获取实时更新、信号和新恶意域名。申请访问完整控制台。</p>
          <p class="lang-block" data-lang="hi">रीयल-टाइम अपडेट, सिग्नल और नए दुर्भावनापूर्ण डोमेन पाएं। पूरी कंसोल के लिए एक्सेस मांगें।</p>
          <p class="lang-block" data-lang="ar">احصل على تحديثات واشارات ونطاقات خبيثة جديدة بالوقت الحقيقي. اطلب الوصول الى لوحة كاملة.</p>
          <p class="lang-block" data-lang="he">קבל עדכונים בזמן אמת, אותות ודומיינים זדוניים חדשים. בקש גישה לקונסולה המלאה.</p>
          <form class="access-request-form" id="dashboard-preview-access" method="post" action="#dashboard-preview">
            <input type="hidden" name="form_action" value="request_access" />
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($accessRequestCsrfToken, ENT_QUOTES, 'UTF-8'); ?>" />
            <input type="hidden" name="request_lang" id="request-lang-field" value="<?= htmlspecialchars($initialLang, ENT_QUOTES, 'UTF-8'); ?>" />
            <div class="access-request-honeypot" aria-hidden="true">
              <label for="company-website-hp" data-i18n-text="company_website_label">Company Website</label>
              <input id="company-website-hp" type="text" name="company_website_hp" autocomplete="off" tabindex="-1" />
            </div>
            <div class="access-request-field">
              <label class="access-request-label" for="access-linkedin" data-i18n-text="linkedin_label">Professional LinkedIn</label>
              <input
                class="access-request-input"
                id="access-linkedin"
                type="url"
                name="access_linkedin"
                maxlength="255"
                placeholder="https://www.linkedin.com/in/..."
                autocomplete="url"
                required
              />
            </div>
            <div class="access-request-field">
              <label class="access-request-label" for="company-website-visible" data-i18n-text="company_website_label">Company Website</label>
              <input
                class="access-request-input"
                id="company-website-visible"
                type="url"
                name="company_website"
                maxlength="255"
                placeholder="https://company.com"
                autocomplete="url"
              />
            </div>
            <div class="access-request-field">
              <label class="access-request-label" for="access-email">
                <span class="lang-inline" data-lang="es">Email profesional</span>
                <span class="lang-inline" data-lang="en">Work email</span>
                <span class="lang-inline" data-lang="pt">Email profissional</span>
                <span class="lang-inline" data-lang="fr">Email professionnel</span>
                <span class="lang-inline" data-lang="de">Arbeits-E-Mail</span>
                <span class="lang-inline" data-lang="nl">Werk e-mail</span>
                <span class="lang-inline" data-lang="ca">Correu professional</span>
                <span class="lang-inline" data-lang="ru">??????? ?????</span>
                <span class="lang-inline" data-lang="ja">??????</span>
                <span class="lang-inline" data-lang="ko">??? ???</span>
                <span class="lang-inline" data-lang="zh">????</span>
                <span class="lang-inline" data-lang="hi">????? ????</span>
                <span class="lang-inline" data-lang="ar">???? ?????</span>
                <span class="lang-inline" data-lang="he">?????? ?????</span>
              </label>
              <input
                class="access-request-input"
                id="access-email"
                type="email"
                name="access_email"
                maxlength="190"
                placeholder="name@company.com"
                autocomplete="email"
                required
              />
            </div>
            <div class="preview-actions">
              <button class="button primary access-request-submit" type="submit">
                <span class="lang-inline" data-lang="es">Solicitar acceso</span>
                <span class="lang-inline" data-lang="en">Request access</span>
                <span class="lang-inline" data-lang="pt">Solicitar acesso</span>
                <span class="lang-inline" data-lang="fr">Demander l acces</span>
                <span class="lang-inline" data-lang="de">Zugang anfragen</span>
                <span class="lang-inline" data-lang="nl">Toegang aanvragen</span>
                <span class="lang-inline" data-lang="ca">Demanar acces</span>
                <span class="lang-inline" data-lang="ru">????????? ??????</span>
                <span class="lang-inline" data-lang="ja">??????</span>
                <span class="lang-inline" data-lang="ko">?? ??</span>
                <span class="lang-inline" data-lang="zh">????</span>
                <span class="lang-inline" data-lang="hi">?????? ??????</span>
                <span class="lang-inline" data-lang="ar">??? ??????</span>
                <span class="lang-inline" data-lang="he">???? ????</span>
              </button>
            </div>
          </form>
          <script>
            (function () {
              function getCookie(name) {
                const needle = name + "=";
                const parts = String(document.cookie || "").split(";");
                for (let i = 0; i < parts.length; i++) {
                  const part = parts[i].trim();
                  if (part.indexOf(needle) === 0) {
                    return decodeURIComponent(part.substring(needle.length));
                  }
                }
                return "";
              }
              const token = getCookie("clickfix_access_request_csrf");
              if (!token) return;
              const form = document.getElementById("dashboard-preview-access");
              if (!form) return;
              const input = form.querySelector("input[name='csrf_token']");
              if (input) {
                input.value = token;
              }
            })();
          </script>
          <?php if ($accessRequestFlash !== null): ?>
            <?php
              $feedbackClass = 'error';
              $accessErrorCode = '';
              if ($accessRequestFlash === 'ok') {
                  $feedbackClass = 'success';
              } elseif ($accessRequestFlash === 'rate_limited') {
                  $feedbackClass = 'warn';
              } elseif (str_starts_with((string) $accessRequestFlash, 'error:')) {
                  $accessErrorCode = substr((string) $accessRequestFlash, 6);
              }
            ?>
            <div class="access-request-feedback <?= htmlspecialchars($feedbackClass, ENT_QUOTES, 'UTF-8'); ?>">
              <?php if ($accessRequestFlash === 'ok'): ?>
                <span class="lang-inline" data-lang="es">Solicitud enviada. Revisaremos el acceso y te contactaremos por email.</span>
                <span class="lang-inline" data-lang="en">Request submitted. We will review access and contact you by email.</span>
                <span class="lang-inline" data-lang="pt">Solicitacao enviada. Vamos revisar o acesso e contactar por email.</span>
                <span class="lang-inline" data-lang="fr">Demande envoyee. Nous verifierons l acces et vous contacterons par email.</span>
                <span class="lang-inline" data-lang="de">Anfrage gesendet. Wir pruefen den Zugriff und melden uns per E-Mail.</span>
                <span class="lang-inline" data-lang="nl">Aanvraag verzonden. We beoordelen de toegang en mailen je.</span>
                <span class="lang-inline" data-lang="ca">Sol?licitud enviada. Revisarem l acces i et contactarem per correu.</span>
                <span class="lang-inline" data-lang="ru">?????? ?????????. ?? ???????? ?????? ? ???????? ?? ?????.</span>
                <span class="lang-inline" data-lang="ja">????????????????????????</span>
                <span class="lang-inline" data-lang="ko">??? ???????. ?? ? ???? ????????.</span>
                <span class="lang-inline" data-lang="zh">????????????????????</span>
                <span class="lang-inline" data-lang="hi">?????? ??? ???? ??? ??? ??????? ?? ??? ???? ?? ?????? ???????</span>
                <span class="lang-inline" data-lang="ar">?? ????? ?????. ?????? ?????? ??????? ??? ??????.</span>
                <span class="lang-inline" data-lang="he">????? ?????. ????? ???? ?????? ?????.</span>
              <?php elseif ($accessRequestFlash === 'rate_limited'): ?>
                <span class="lang-inline" data-lang="es">Espera unos segundos antes de enviar otra solicitud.</span>
                <span class="lang-inline" data-lang="en">Please wait a few seconds before sending another request.</span>
                <span class="lang-inline" data-lang="pt">Aguarde alguns segundos antes de enviar outro pedido.</span>
                <span class="lang-inline" data-lang="fr">Patientez quelques secondes avant d envoyer une nouvelle demande.</span>
                <span class="lang-inline" data-lang="de">Bitte warten Sie einige Sekunden vor einer neuen Anfrage.</span>
                <span class="lang-inline" data-lang="nl">Wacht enkele seconden voordat je opnieuw aanvraagt.</span>
                <span class="lang-inline" data-lang="ca">Espera uns segons abans d enviar una altra sol?licitud.</span>
                <span class="lang-inline" data-lang="ru">????????? ????????? ?????? ????? ????????? ????????.</span>
                <span class="lang-inline" data-lang="ja">?????????????????</span>
                <span class="lang-inline" data-lang="ko">?? ???? ?? ? ? ??? ???.</span>
                <span class="lang-inline" data-lang="zh">???????????</span>
                <span class="lang-inline" data-lang="hi">?????? ????? ?? ???? ??? ????? ????????? ?????</span>
                <span class="lang-inline" data-lang="ar">???? ???????? ??? ???? ??? ????? ??? ???.</span>
                <span class="lang-inline" data-lang="he">?? ?????? ??? ????? ???? ???? ?????.</span>
              <?php else: ?>
                <span class="lang-inline" data-lang="es">No se pudo enviar la solicitud. Revisa el email e intentalo de nuevo.</span>
                <span class="lang-inline" data-lang="en">Could not submit the request. Check the email and try again.</span>
                <span class="lang-inline" data-lang="pt">Nao foi possivel enviar. Verifique o email e tente novamente.</span>
                <span class="lang-inline" data-lang="fr">Envoi impossible. Verifiez l email et reessayez.</span>
                <span class="lang-inline" data-lang="de">Anfrage konnte nicht gesendet werden. E-Mail pruefen und erneut versuchen.</span>
                <span class="lang-inline" data-lang="nl">Verzenden mislukt. Controleer het e-mailadres en probeer opnieuw.</span>
                <span class="lang-inline" data-lang="ca">No s ha pogut enviar. Revisa el correu i torna-ho a provar.</span>
                <span class="lang-inline" data-lang="ru">?? ??????? ????????? ??????. ????????? email ? ?????????? ?????.</span>
                <span class="lang-inline" data-lang="ja">?????????????????????????????</span>
                <span class="lang-inline" data-lang="ko">??? ?? ? ????. ???? ?? ? ?? ?????.</span>
                <span class="lang-inline" data-lang="zh">??????????????</span>
                <span class="lang-inline" data-lang="hi">?????? ???? ???? ?? ???? ???? ?????? ?? ??? ?????? ?????</span>
                <span class="lang-inline" data-lang="ar">???? ????? ?????. ???? ?? ?????? ????? ?????.</span>
                <span class="lang-inline" data-lang="he">?? ???? ????? ????. ???? ?? ??????? ???? ???.</span>
                <?php if ($accessErrorCode !== ''): ?>
                  <span class="lang-inline" data-lang="es">Codigo: <span class="mono"><?= htmlspecialchars($accessErrorCode, ENT_QUOTES, 'UTF-8'); ?></span></span>
                  <span class="lang-inline" data-lang="en">Error code: <span class="mono"><?= htmlspecialchars($accessErrorCode, ENT_QUOTES, 'UTF-8'); ?></span></span>
                <?php endif; ?>
              <?php endif; ?>
            </div>
          <?php endif; ?>
          <div class="preview-actions">
            <a class="button secondary" href="home.php">
              <span class="lang-inline" data-lang="es">Ver panel publico</span>
              <span class="lang-inline" data-lang="en">View public dashboard</span>
              <span class="lang-inline" data-lang="pt">Ver painel publico</span>
              <span class="lang-inline" data-lang="fr">Voir le panneau public</span>
              <span class="lang-inline" data-lang="de">Oeffentliches Dashboard</span>
              <span class="lang-inline" data-lang="nl">Publiek dashboard</span>
              <span class="lang-inline" data-lang="ca">Veure panell public</span>
              <span class="lang-inline" data-lang="ru">????????? ??????</span>
              <span class="lang-inline" data-lang="ja">?????????</span>
              <span class="lang-inline" data-lang="ko">?? ????</span>
              <span class="lang-inline" data-lang="zh">??????</span>
              <span class="lang-inline" data-lang="hi">?????? ????????</span>
              <span class="lang-inline" data-lang="ar">??? ?????? ??????</span>
              <span class="lang-inline" data-lang="he">??? ??????</span>
            </a>
            <a class="button secondary" href="dashboard.php?page=access&amp;public=1">
              <span data-i18n-text="quick_login_button">Open login</span>
            </a>
            <a class="button secondary" href="https://chromewebstore.google.com/detail/clickfix-mitigator/nmldafmgfcfopjoigbmmlmcnininifaa" target="_blank" rel="noopener">
              <span data-i18n-text="quick_install_button">Install extension</span>
            </a>
          </div>
        </div>
      </div>
      <div class="scan-preview">
        <div class="chart-header">
          <span data-i18n-text="public_evidence_title">ClickFix evidence (Before server snapshot / After extension alert)</span>
          <span class="chart-value"><?= htmlspecialchars($publicEvidenceLabel, ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
        <?php if (empty($publicLatestScanAssets['before']) && empty($publicLatestScanAssets['after'])): ?>
          <div class="scan-preview-item">
            <b data-i18n-text="public_evidence_workflow_title">Public evidence workflow</b>
            <div class="metric-foot" data-i18n-text="public_evidence_workflow_body">There is no public evidence available yet. Screenshots only appear here after they are captured, reviewed, and approved by an administrator.</div>
          </div>
        <?php else: ?>
          <div class="scan-preview-grid">
            <div class="scan-preview-item">
              <b data-i18n-text="evidence_before">Before</b>
              <?php if (!empty($publicLatestScanAssets['before'])): ?>
                <img src="<?= htmlspecialchars((string) $publicLatestScanAssets['before'], ENT_QUOTES, 'UTF-8'); ?>" alt="" data-i18n-alt="evidence_before_alt" loading="lazy" decoding="async" width="1280" height="720" />
              <?php else: ?>
                <div class="metric-foot" data-i18n-text="evidence_before_pending">Before is not publicly approved yet for this case.</div>
              <?php endif; ?>
            </div>
            <div class="scan-preview-item">
              <b data-i18n-text="evidence_after">After</b>
              <?php if (!empty($publicLatestScanAssets['after'])): ?>
                <img src="<?= htmlspecialchars((string) $publicLatestScanAssets['after'], ENT_QUOTES, 'UTF-8'); ?>" alt="" data-i18n-alt="evidence_after_alt" loading="lazy" decoding="async" width="1280" height="720" />
              <?php else: ?>
                <div class="metric-foot" data-i18n-text="evidence_after_pending">After is not publicly approved yet for this case.</div>
              <?php endif; ?>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </section>

    <section class="reveal" id="featured-investigations">
      <div class="section-head">
        <div>
          <div class="pill" data-i18n-text="featured_investigations_title">Featured investigations</div>
          <h2 class="section-title" data-i18n-text="featured_showcase_title">Public investigations with analyst-ready visual context.</h2>
          <p class="section-sub" data-i18n-text="featured_showcase_intro">Only investigations explicitly shared by administrators are shown here, with graph posture, IOC density, evidence status, and direct access to the public case.</p>
        </div>
      </div>
      <?php if (empty($publicFeaturedInvestigations)): ?>
        <div class="card reveal">
          <h3 data-i18n-text="featured_investigations_title">Featured investigations</h3>
          <p data-i18n-text="featured_investigations_empty">No public featured investigations are available yet.</p>
        </div>
      <?php else: ?>
        <div class="featured-showcase">
          <?php foreach ($publicFeaturedInvestigations as $featuredInvestigation): ?>
            <?php
              $featuredTitle = trim((string) ($featuredInvestigation['title'] ?? $featuredInvestigation['site_domain'] ?? ''));
              $featuredDomain = trim((string) ($featuredInvestigation['site_domain'] ?? '-'));
              $featuredAssets = is_array($featuredInvestigation['scan_assets'] ?? null) ? $featuredInvestigation['scan_assets'] : ['before' => null, 'after' => null];
              $featuredShareUrl = '';
              $featuredShowcase = is_array($featuredInvestigation['home_showcase'] ?? null) ? $featuredInvestigation['home_showcase'] : [];
              $featuredBars = is_array($featuredShowcase['bars'] ?? null) ? $featuredShowcase['bars'] : [];
              $featuredScoreValue = max(0, min(100, (int) ($featuredShowcase['score'] ?? 0)));
              if (!empty($featuredInvestigation['share_token'])) {
                  $featuredShareUrl = 'dashboard.php?page=investigation&share=' . urlencode((string) $featuredInvestigation['share_token']);
              }
            ?>
            <article class="featured-case-card reveal">
              <div class="featured-case-head">
                <div class="featured-case-meta">
                  <span class="featured-case-kicker" data-i18n-text="featured_case_public">Public investigation</span>
                  <span class="featured-case-updated">
                    <span data-i18n-text="featured_case_updated">Updated</span>
                    <?= htmlspecialchars((string) ($featuredShowcase['updated_label'] ?? 'UTC'), ENT_QUOTES, 'UTF-8'); ?>
                  </span>
                </div>
                <div class="featured-case-domain"><?= htmlspecialchars($featuredDomain, ENT_QUOTES, 'UTF-8'); ?></div>
                <h3><?= htmlspecialchars($featuredTitle, ENT_QUOTES, 'UTF-8'); ?></h3>
                <p class="featured-case-summary">
                  <?php if (!empty($featuredShowcase['summary_excerpt'])): ?>
                    <?= nl2br(htmlspecialchars((string) $featuredShowcase['summary_excerpt'], ENT_QUOTES, 'UTF-8')); ?>
                  <?php else: ?>
                    <span data-i18n-text="featured_summary_empty">No public summary available yet.</span>
                  <?php endif; ?>
                </p>
              </div>
              <div class="featured-case-layout">
                <div class="featured-case-chart">
                  <div class="featured-case-chart-head">
                    <span data-i18n-text="featured_case_score_title">Case posture</span>
                    <span class="featured-case-score" aria-label="<?= htmlspecialchars((string) ($featuredScoreValue . ' / 100'), ENT_QUOTES, 'UTF-8'); ?>">
                      <span class="featured-case-chart-value"><?= (int) $featuredScoreValue; ?></span>
                      <span class="featured-case-score-max">/100</span>
                    </span>
                  </div>
                  <div class="featured-case-score-body">
                    <canvas
                      class="featured-case-canvas"
                      width="320"
                      height="180"
                      data-featured-chart="<?= htmlspecialchars(json_encode($featuredShowcase['chart'] ?? ['labels' => [], 'values' => []], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>"
                    ></canvas>
                    <div class="featured-case-bars">
                      <?php foreach ($featuredBars as $featuredBar): ?>
                        <?php
                          $barKey = (string) ($featuredBar['key'] ?? 'graph');
                          $barValue = (int) ($featuredBar['value'] ?? 0);
                          $barValueSafe = max(0, min(100, $barValue));
                          $barLabel = (string) ($featuredBar['label'] ?? ucfirst($barKey));
                        ?>
                        <div class="featured-case-bar" role="group" aria-label="<?= htmlspecialchars($barLabel . ' ' . $barValueSafe . ' / 100', ENT_QUOTES, 'UTF-8'); ?>">
                          <span data-i18n-text="featured_case_<?= htmlspecialchars($barKey, ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars($barLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                          <div class="featured-case-bar-track">
                            <span class="featured-case-bar-fill featured-case-bar-fill--<?= htmlspecialchars((string) ($featuredBar['tone'] ?? 'graph'), ENT_QUOTES, 'UTF-8'); ?>" style="--featured-fill: <?= $barValueSafe; ?>%"></span>
                          </div>
                          <strong><?= $barValueSafe; ?></strong>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  </div>
                </div>
                <div class="featured-case-kpis">
                  <div class="featured-case-kpi">
                    <span data-i18n-text="featured_case_nodes">Nodes</span>
                    <strong><?= (int) ($featuredShowcase['nodes'] ?? 0); ?></strong>
                    <small data-i18n-text="featured_case_nodes_hint">Entities represented in the graph.</small>
                  </div>
                  <div class="featured-case-kpi">
                    <span data-i18n-text="featured_case_edges">Edges</span>
                    <strong><?= (int) ($featuredShowcase['edges'] ?? 0); ?></strong>
                    <small data-i18n-text="featured_case_edges_hint">Observed relations across the case.</small>
                  </div>
                  <div class="featured-case-kpi">
                    <span data-i18n-text="featured_case_ioc_total">IOCs</span>
                    <strong><?= (int) ($featuredShowcase['iocs'] ?? 0); ?></strong>
                    <small><?= (int) ($featuredShowcase['tags'] ?? 0); ?> <span data-i18n-text="featured_case_tags_suffix">tags attached</span></small>
                  </div>
                </div>
              </div>
              <div class="featured-case-evidence">
                <div class="featured-case-evidence-item">
                  <b data-i18n-text="evidence_before">Before</b>
                  <?php if (!empty($featuredAssets['before'])): ?>
                    <img src="<?= htmlspecialchars((string) $featuredAssets['before'], ENT_QUOTES, 'UTF-8'); ?>" alt="" data-i18n-alt="featured_before_alt" loading="lazy" decoding="async" width="1280" height="720" />
                  <?php else: ?>
                    <div class="metric-foot" data-i18n-text="featured_before_missing">No approved before capture linked.</div>
                  <?php endif; ?>
                </div>
                <div class="featured-case-evidence-item">
                  <b data-i18n-text="evidence_after">After</b>
                  <?php if (!empty($featuredAssets['after'])): ?>
                    <img src="<?= htmlspecialchars((string) $featuredAssets['after'], ENT_QUOTES, 'UTF-8'); ?>" alt="" data-i18n-alt="featured_after_alt" loading="lazy" decoding="async" width="1280" height="720" />
                  <?php else: ?>
                    <div class="metric-foot" data-i18n-text="featured_after_missing">No approved after capture linked.</div>
                  <?php endif; ?>
                </div>
              </div>
              <div class="featured-case-actions">
                <?php if ($featuredShareUrl !== ''): ?>
                  <a class="button primary" href="<?= htmlspecialchars($featuredShareUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener" data-i18n-text="featured_open_investigation">Open investigation</a>
                <?php endif; ?>
                <a class="button secondary" href="dashboard.php?page=access&amp;public=1" data-i18n-text="featured_request_access">Request analyst access</a>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>

    <section class="grid three">
      <div class="card reveal">
        <h3 class="lang-block" data-lang="es">Instalacion directa</h3>
        <h3 class="lang-block" data-lang="en">Direct install</h3>
        <h3 class="lang-block" data-lang="pt">Instalacao direta</h3>
        <h3 class="lang-block" data-lang="fr">Installation directe</h3>
        <h3 class="lang-block" data-lang="de">Direkte Installation</h3>
        <h3 class="lang-block" data-lang="nl">Directe installatie</h3>
        <h3 class="lang-block" data-lang="ca">Instal·lacio directa</h3>
        <h3 class="lang-block" data-lang="ru">Прямая установка</h3>
        <h3 class="lang-block" data-lang="ja">直接インストール</h3>
        <h3 class="lang-block" data-lang="ko">직접 설치</h3>
        <h3 class="lang-block" data-lang="zh">直接安装</h3>
        <h3 class="lang-block" data-lang="hi">सीधा इंस्टॉल</h3>
        <h3 class="lang-block" data-lang="ar">تثبيت مباشر</h3>
        <h3 class="lang-block" data-lang="he">התקנה ישירה</h3>
        <p class="lang-block" data-lang="es">Disponible en Chrome Web Store para despliegues rapidos y controlados.</p>
        <p class="lang-block" data-lang="en">Available on the Chrome Web Store for quick, controlled rollouts.</p>
        <p class="lang-block" data-lang="pt">Disponivel na Chrome Web Store para implantacoes rapidas e controladas.</p>
        <p class="lang-block" data-lang="fr">Disponible sur le Chrome Web Store pour des deploiements rapides et controles.</p>
        <p class="lang-block" data-lang="de">Im Chrome Web Store verfuegbar fuer schnelle, kontrollierte Rollouts.</p>
        <p class="lang-block" data-lang="nl">Beschikbaar in de Chrome Web Store voor snelle, gecontroleerde uitrol.</p>
        <p class="lang-block" data-lang="ca">Disponible a la Chrome Web Store per a desplegaments rapids i controlats.</p>
        <p class="lang-block" data-lang="ru">Доступно в Chrome Web Store для быстрого и контролируемого развертывания.</p>
        <p class="lang-block" data-lang="ja">Chrome Web Store で提供。迅速で管理された展開に最適。</p>
        <p class="lang-block" data-lang="ko">Chrome Web Store에서 제공되어 빠르고 통제된 배포가 가능합니다.</p>
        <p class="lang-block" data-lang="zh">在 Chrome 网上应用店提供，便于快速且可控的部署。</p>
        <p class="lang-block" data-lang="hi">Chrome Web Store पर उपलब्ध, तेज़ और नियंत्रित रोलआउट के लिए।</p>
        <p class="lang-block" data-lang="ar">متاح في متجر Chrome للتوزيعات السريعة والمضبوطة.</p>
        <p class="lang-block" data-lang="he">זמין בחנות Chrome לפריסות מהירות ומבוקרות.</p>
      </div>
      <div class="card reveal delay-1">
        <h3 class="lang-block" data-lang="es">Bloqueo con contexto</h3>
        <h3 class="lang-block" data-lang="en">Context-aware blocking</h3>
        <h3 class="lang-block" data-lang="pt">Bloqueio com contexto</h3>
        <h3 class="lang-block" data-lang="fr">Blocage contextuel</h3>
        <h3 class="lang-block" data-lang="de">Kontextbasiertes Blockieren</h3>
        <h3 class="lang-block" data-lang="nl">Contextbewuste blokkering</h3>
        <h3 class="lang-block" data-lang="ca">Bloqueig amb context</h3>
        <h3 class="lang-block" data-lang="ru">Контекстная блокировка</h3>
        <h3 class="lang-block" data-lang="ja">文脈に基づくブロック</h3>
        <h3 class="lang-block" data-lang="ko">상황 인지 차단</h3>
        <h3 class="lang-block" data-lang="zh">情境化阻断</h3>
        <h3 class="lang-block" data-lang="hi">संदर्भ-आधारित ब्लॉकिंग</h3>
        <h3 class="lang-block" data-lang="ar">حظر معتمد على السياق</h3>
        <h3 class="lang-block" data-lang="he">חסימה מודעת להקשר</h3>
        <p class="lang-block" data-lang="es">Detecta señales de ClickFix, protege el portapapeles y detiene el flujo.</p>
        <p class="lang-block" data-lang="en">Detects ClickFix signals, protects the clipboard, and stops the flow.</p>
        <p class="lang-block" data-lang="pt">Detecta sinais de ClickFix, protege o clipboard e para o fluxo.</p>
        <p class="lang-block" data-lang="fr">Detecte les signaux ClickFix, protege le presse-papiers et stoppe le flux.</p>
        <p class="lang-block" data-lang="de">Erkennt ClickFix-Signale, schuetzt die Zwischenablage und stoppt den Ablauf.</p>
        <p class="lang-block" data-lang="nl">Detecteert ClickFix-signalen, beschermt het klembord en stopt de flow.</p>
        <p class="lang-block" data-lang="ca">Detecta senyals de ClickFix, protegeix el porta-retalls i atura el flux.</p>
        <p class="lang-block" data-lang="ru">Обнаруживает сигналы ClickFix, защищает буфер обмена и останавливает поток.</p>
        <p class="lang-block" data-lang="ja">ClickFixの兆候を検知し、クリップボードを保護してフローを停止します。</p>
        <p class="lang-block" data-lang="ko">ClickFix 신호를 감지하고 클립보드를 보호하며 흐름을 차단합니다.</p>
        <p class="lang-block" data-lang="zh">检测 ClickFix 信号，保护剪贴板并阻断流程。</p>
        <p class="lang-block" data-lang="hi">ClickFix संकेतों का पता लगाता है, क्लिपबोर्ड की सुरक्षा करता है और फ्लो रोकता है।</p>
        <p class="lang-block" data-lang="ar">يرصد اشارات ClickFix، يحمي الحافظة ويوقف التدفق.</p>
        <p class="lang-block" data-lang="he">מזהה אותות ClickFix, מגן על לוח הגזירים ועוצר את הזרימה.</p>
      </div>
      <div class="card reveal delay-2">
        <h3 class="lang-block" data-lang="es">Evidencia para SOC</h3>
        <h3 class="lang-block" data-lang="en">Evidence for SOC</h3>
        <h3 class="lang-block" data-lang="pt">Evidencia para SOC</h3>
        <h3 class="lang-block" data-lang="fr">Preuves pour SOC</h3>
        <h3 class="lang-block" data-lang="de">Nachweise fuer SOC</h3>
        <h3 class="lang-block" data-lang="nl">Bewijs voor SOC</h3>
        <h3 class="lang-block" data-lang="ca">Evidencia per a SOC</h3>
        <h3 class="lang-block" data-lang="ru">Доказательства для SOC</h3>
        <h3 class="lang-block" data-lang="ja">SOC向け証拠</h3>
        <h3 class="lang-block" data-lang="ko">SOC용 증거</h3>
        <h3 class="lang-block" data-lang="zh">SOC 证据</h3>
        <h3 class="lang-block" data-lang="hi">SOC के लिए प्रमाण</h3>
        <h3 class="lang-block" data-lang="ar">ادلة لـ SOC</h3>
        <h3 class="lang-block" data-lang="he">ראיות ל‑SOC</h3>
        <p class="lang-block" data-lang="es">Reportes claros para analistas, blue teams y respuesta rapida.</p>
        <p class="lang-block" data-lang="en">Clear reports for analysts, blue teams, and rapid response.</p>
        <p class="lang-block" data-lang="pt">Relatorios claros para analistas, blue teams e resposta rapida.</p>
        <p class="lang-block" data-lang="fr">Rapports clairs pour analystes, blue teams et reponse rapide.</p>
        <p class="lang-block" data-lang="de">Klare Reports fuer Analysten, Blue Teams und schnelle Reaktion.</p>
        <p class="lang-block" data-lang="nl">Duidelijke rapporten voor analisten, blue teams en snelle respons.</p>
        <p class="lang-block" data-lang="ca">Informes clars per a analistes, blue teams i resposta rapida.</p>
        <p class="lang-block" data-lang="ru">Четкие отчеты для аналитиков, blue teams и быстрого реагирования.</p>
        <p class="lang-block" data-lang="ja">アナリストやBlue Team向けの明確なレポートで迅速対応。</p>
        <p class="lang-block" data-lang="ko">분석가와 블루팀을 위한 명확한 리포트로 신속 대응.</p>
        <p class="lang-block" data-lang="zh">为分析师和蓝队提供清晰报告，支持快速响应。</p>
        <p class="lang-block" data-lang="hi">विश्लेषकों, ब्लू टीमों और तेज़ प्रतिक्रिया के लिए स्पष्ट रिपोर्ट.</p>
        <p class="lang-block" data-lang="ar">تقارير واضحة للمحللين وفرق الدفاع والاستجابة السريعة.</p>
        <p class="lang-block" data-lang="he">דוחות ברורים לאנליסטים, צוותי כחול ותגובה מהירה.</p>
      </div>
    </section>

    <section class="grid two">
      <div class="card reveal">
        <h3 class="lang-block" data-lang="es">Que es ClickFix Mitigator</h3>
        <h3 class="lang-block" data-lang="en">What ClickFix Mitigator Is</h3>
        <h3 class="lang-block" data-lang="pt">O que e ClickFix Mitigator</h3>
        <h3 class="lang-block" data-lang="fr">Qu est ce que ClickFix Mitigator</h3>
        <h3 class="lang-block" data-lang="de">Was ClickFix Mitigator ist</h3>
        <h3 class="lang-block" data-lang="nl">Wat ClickFix Mitigator is</h3>
        <h3 class="lang-block" data-lang="ca">Que es ClickFix Mitigator</h3>
        <h3 class="lang-block" data-lang="ru">Что такое ClickFix Mitigator</h3>
        <h3 class="lang-block" data-lang="ja">ClickFix Mitigator とは</h3>
        <h3 class="lang-block" data-lang="ko">ClickFix Mitigator란</h3>
        <h3 class="lang-block" data-lang="zh">什么是 ClickFix Mitigator</h3>
        <h3 class="lang-block" data-lang="hi">ClickFix Mitigator क्या है</h3>
        <h3 class="lang-block" data-lang="ar">ما هو ClickFix Mitigator</h3>
        <h3 class="lang-block" data-lang="he">מהו ClickFix Mitigator</h3>
        <p class="lang-block" data-lang="es">Un kit completo con extension, agente Windows y backend PHP+SQLite para frenar ataques de ingenieria social basados en comandos.</p>
        <p class="lang-block" data-lang="en">A full kit with browser extension, Windows agent, and PHP+SQLite backend to stop command-based social engineering attacks.</p>
        <p class="lang-block" data-lang="pt">Um kit completo com extensao, agente Windows e backend PHP+SQLite para frear ataques de engenharia social baseados em comandos.</p>
        <p class="lang-block" data-lang="fr">Un kit complet avec extension, agent Windows et backend PHP+SQLite pour stopper les attaques basees sur des commandes.</p>
        <p class="lang-block" data-lang="de">Ein Komplettset mit Browser-Erweiterung, Windows-Agent und PHP+SQLite-Backend, um befehlbasierte Social-Engineering-Angriffe zu stoppen.</p>
        <p class="lang-block" data-lang="nl">Een complete set met browserextensie, Windows-agent en PHP+SQLite-backend om commando-gebaseerde social-engineeringaanvallen te stoppen.</p>
        <p class="lang-block" data-lang="ca">Un kit complet amb extensio, agent Windows i backend PHP+SQLite per frenar atacs d'enginyeria social basats en comandes.</p>
        <p class="lang-block" data-lang="ru">Полный набор с расширением браузера, агентом Windows и backend на PHP+SQLite для остановки командных social engineering атак.</p>
        <p class="lang-block" data-lang="ja">ブラウザ拡張、Windowsエージェント、PHP+SQLiteバックエンドを備えた、コマンド型ソーシャルエンジニアリング攻撃を止めるフルキット。</p>
        <p class="lang-block" data-lang="ko">브라우저 확장, Windows 에이전트, PHP+SQLite 백엔드로 구성된 전체 키트로, 명령 기반 소셜 엔지니어링 공격을 차단합니다.</p>
        <p class="lang-block" data-lang="zh">包含浏览器扩展、Windows 代理和 PHP+SQLite 后端的完整套件，用于阻止基于命令的社会工程攻击。</p>
        <p class="lang-block" data-lang="hi">ब्राउज़र एक्सटेंशन, Windows एजेंट और PHP+SQLite बैकएंड वाला पूरा किट, जो कमांड-आधारित सोशल इंजीनियरिंग हमलों को रोकता है।</p>
        <p class="lang-block" data-lang="ar">مجموعة كاملة تشمل امتداد المتصفح ووكيل Windows وواجهة خلفية PHP+SQLite لإيقاف هجمات الهندسة الاجتماعية القائمة على الاوامر.</p>
        <p class="lang-block" data-lang="he">ערכה מלאה עם תוסף דפדפן, סוכן Windows ו־backend PHP+SQLite כדי לעצור מתקפות הנדסה חברתית מבוססות פקודות.</p>
      </div>
      <div class="card reveal delay-1">
        <h3 class="lang-block" data-lang="es">Su funcion principal</h3>
        <h3 class="lang-block" data-lang="en">Primary Mission</h3>
        <h3 class="lang-block" data-lang="pt">Funcao principal</h3>
        <h3 class="lang-block" data-lang="fr">Mission principale</h3>
        <h3 class="lang-block" data-lang="de">Hauptaufgabe</h3>
        <h3 class="lang-block" data-lang="nl">Hoofdfunctie</h3>
        <h3 class="lang-block" data-lang="ca">Funcio principal</h3>
        <h3 class="lang-block" data-lang="ru">Основная миссия</h3>
        <h3 class="lang-block" data-lang="ja">主な目的</h3>
        <h3 class="lang-block" data-lang="ko">핵심 목적</h3>
        <h3 class="lang-block" data-lang="zh">核心任务</h3>
        <h3 class="lang-block" data-lang="hi">मुख्य उद्देश्य</h3>
        <h3 class="lang-block" data-lang="ar">المهمة الرئيسية</h3>
        <h3 class="lang-block" data-lang="he">המשימה הראשית</h3>
        <p class="lang-block" data-lang="es">Detectar ClickFix en tiempo real, interrumpir el flujo y dejar trazabilidad para analistas y blue teams.</p>
        <p class="lang-block" data-lang="en">Detect ClickFix in real time, interrupt the flow, and provide traceability for analysts and blue teams.</p>
        <p class="lang-block" data-lang="pt">Detectar ClickFix em tempo real, interromper o fluxo e manter rastreabilidade para analistas e blue teams.</p>
        <p class="lang-block" data-lang="fr">Detecter ClickFix en temps reel, interrompre le flux et fournir une tracabilite pour les analysts et blue teams.</p>
        <p class="lang-block" data-lang="de">ClickFix in Echtzeit erkennen, den Ablauf unterbrechen und Nachvollziehbarkeit fuer Analysten und Blue Teams schaffen.</p>
        <p class="lang-block" data-lang="nl">ClickFix in realtime detecteren, de flow onderbreken en traceerbaarheid bieden voor analisten en blue teams.</p>
        <p class="lang-block" data-lang="ca">Detectar ClickFix en temps real, interrompre el flux i donar tracabilitat per a analistes i blue teams.</p>
        <p class="lang-block" data-lang="ru">В реальном времени обнаруживать ClickFix, прерывать поток и обеспечивать трассируемость для аналитиков и blue teams.</p>
        <p class="lang-block" data-lang="ja">ClickFixをリアルタイムで検知し、フローを遮断してアナリストとBlue Team向けのトレーサビリティを提供。</p>
        <p class="lang-block" data-lang="ko">ClickFix를 실시간으로 탐지하고 흐름을 중단하며 분석가와 블루팀을 위한 추적성을 제공합니다.</p>
        <p class="lang-block" data-lang="zh">实时检测 ClickFix，打断流程，并为分析师和蓝队提供可追溯性。</p>
        <p class="lang-block" data-lang="hi">ClickFix को रियल-टाइम में detect करना, फ्लो रोकना और विश्लेषकों व ब्लू टीमों के लिए ट्रेसबिलिटी देना।</p>
        <p class="lang-block" data-lang="ar">اكتشاف ClickFix في الوقت الحقيقي، قطع التدفق، وتوفير قابلية تتبع للمحللين وفرق الدفاع.</p>
        <p class="lang-block" data-lang="he">לזהות ClickFix בזמן אמת, לקטוע את הזרימה ולספק עקיבות לאנליסטים ולצוותי כחול.</p>
      </div>
    </section>

    <section class="card reveal">
      <div class="pill">
        <span class="lang-inline" data-lang="es">Inteligencia de señales</span>
        <span class="lang-inline" data-lang="en">Signal Intelligence</span>
        <span class="lang-inline" data-lang="pt">Inteligencia de sinais</span>
        <span class="lang-inline" data-lang="fr">Renseignement de signaux</span>
        <span class="lang-inline" data-lang="de">Signal-Intelligenz</span>
        <span class="lang-inline" data-lang="nl">Signaalinlichtingen</span>
        <span class="lang-inline" data-lang="ca">Intelligencia de senyals</span>
        <span class="lang-inline" data-lang="ru">Сигнальная разведка</span>
        <span class="lang-inline" data-lang="ja">シグナル・インテリジェンス</span>
        <span class="lang-inline" data-lang="ko">신호 인텔리전스</span>
        <span class="lang-inline" data-lang="zh">信号情报</span>
        <span class="lang-inline" data-lang="hi">सिग्नल इंटेलिजेंस</span>
        <span class="lang-inline" data-lang="ar">استخبارات الاشارات</span>
        <span class="lang-inline" data-lang="he">מודיעין אותות</span>
      </div>
      <h2 class="section-title lang-block" data-lang="es">Señales que monitorea</h2>
      <h2 class="section-title lang-block" data-lang="en">Signals It Watches</h2>
      <h2 class="section-title lang-block" data-lang="pt">Sinais monitorados</h2>
      <h2 class="section-title lang-block" data-lang="fr">Signaux surveilles</h2>
      <h2 class="section-title lang-block" data-lang="de">Signale, die es beobachtet</h2>
      <h2 class="section-title lang-block" data-lang="nl">Signalen die het bewaakt</h2>
      <h2 class="section-title lang-block" data-lang="ca">Senyals que monitora</h2>
      <h2 class="section-title lang-block" data-lang="ru">Сигналы, которые отслеживает</h2>
      <h2 class="section-title lang-block" data-lang="ja">監視するシグナル</h2>
      <h2 class="section-title lang-block" data-lang="ko">감시하는 신호</h2>
      <h2 class="section-title lang-block" data-lang="zh">监测的信号</h2>
      <h2 class="section-title lang-block" data-lang="hi">यह जिन संकेतों पर नजर रखता है</h2>
      <h2 class="section-title lang-block" data-lang="ar">الاشارات التي يراقبها</h2>
      <h2 class="section-title lang-block" data-lang="he">האותות שהוא מנטר</h2>
      <p class="section-sub lang-block" data-lang="es">Detecta patrones de comandos, discrepancias del portapapeles y contextos tipicos de engaño (Win+R, prompts falsos, fake CAPTCHA).</p>
      <p class="section-sub lang-block" data-lang="en">Detects command patterns, clipboard mismatches, and common deception context (Win+R, fake prompts, fake CAPTCHA).</p>
      <p class="section-sub lang-block" data-lang="pt">Detecta padroes de comando, discrepancias do clipboard e contextos tipicos de engano (Win+R, prompts falsos, fake CAPTCHA).</p>
      <p class="section-sub lang-block" data-lang="fr">Detecte les patterns de commande, les ecarts du presse-papiers et les contextes typiques de piege (Win+R, faux prompts, fake CAPTCHA).</p>
      <p class="section-sub lang-block" data-lang="de">Erkennt Befehlsmuster, Zwischenablage-Abweichungen und typische Tauschungs-Kontexte (Win+R, falsche Prompts, fake CAPTCHA).</p>
      <p class="section-sub lang-block" data-lang="nl">Detecteert commandopatronen, klembordafwijkingen en typische misleidingscontext (Win+R, nep-prompts, fake CAPTCHA).</p>
      <p class="section-sub lang-block" data-lang="ca">Detecta patrons de comandes, discrepancies del porta-retalls i contextos tipics d'engany (Win+R, prompts falsos, fake CAPTCHA).</p>
      <p class="section-sub lang-block" data-lang="ru">Обнаруживает шаблоны команд, несоответствия буфера обмена и типичные контексты обмана (Win+R, поддельные приглашения, fake CAPTCHA).</p>
      <p class="section-sub lang-block" data-lang="ja">コマンドのパターン、クリップボードの不一致、典型的な詐欺文脈（Win+R、偽プロンプト、偽CAPTCHA）を検知します。</p>
      <p class="section-sub lang-block" data-lang="ko">명령 패턴, 클립보드 불일치, 전형적인 기만 맥락(Win+R, 가짜 프롬프트, 가짜 CAPTCHA)을 탐지합니다.</p>
      <p class="section-sub lang-block" data-lang="zh">检测命令模式、剪贴板不一致以及典型欺骗场景（Win+R、虚假提示、假 CAPTCHA）。</p>
      <p class="section-sub lang-block" data-lang="hi">कमांड पैटर्न, क्लिपबोर्ड मिसमैच और सामान्य धोखा संदर्भ (Win+R, नकली prompts, fake CAPTCHA) का पता लगाता है।</p>
      <p class="section-sub lang-block" data-lang="ar">يرصد انماط الاوامر، اختلافات الحافظة، وسياقات الخداع الشائعة (Win+R، مطالبات مزيفة، fake CAPTCHA).</p>
      <p class="section-sub lang-block" data-lang="he">מזהה דפוסי פקודות, אי-התאמות בלוח הגזירים, והקשרים אופייניים להטעיה (Win+R, פרומפטים מזויפים, fake CAPTCHA).</p>
      <div class="tags">
        <span class="tag">PowerShell</span>
        <span class="tag">cmd.exe</span>
        <span class="tag">mshta</span>
        <span class="tag">rundll32</span>
        <span class="tag">Clipboard mismatch</span>
        <span class="tag">Fullscreen trap</span>
        <span class="tag">Nested iframes</span>
        <span class="tag">Win+R guidance</span>
      </div>
    </section>

    <section class="grid two" id="components">
      <div class="card reveal">
        <div class="pill">
          <span class="lang-inline" data-lang="es">Como funciona</span>
          <span class="lang-inline" data-lang="en">How It Works</span>
          <span class="lang-inline" data-lang="pt">Como funciona</span>
          <span class="lang-inline" data-lang="fr">Comment ca marche</span>
          <span class="lang-inline" data-lang="de">So funktioniert es</span>
          <span class="lang-inline" data-lang="nl">Hoe het werkt</span>
          <span class="lang-inline" data-lang="ca">Com funciona</span>
          <span class="lang-inline" data-lang="ru">Как это работает</span>
          <span class="lang-inline" data-lang="ja">仕組み</span>
          <span class="lang-inline" data-lang="ko">작동 방식</span>
          <span class="lang-inline" data-lang="zh">工作原理</span>
          <span class="lang-inline" data-lang="hi">कैसे काम करता है</span>
          <span class="lang-inline" data-lang="ar">كيف يعمل</span>
          <span class="lang-inline" data-lang="he">איך זה עובד</span>
        </div>
        <h2 class="section-title lang-block" data-lang="es">Flujo de defensa</h2>
        <h2 class="section-title lang-block" data-lang="en">Defense Flow</h2>
        <h2 class="section-title lang-block" data-lang="pt">Fluxo de defesa</h2>
        <h2 class="section-title lang-block" data-lang="fr">Flux de defense</h2>
        <h2 class="section-title lang-block" data-lang="de">Abwehr-Flow</h2>
        <h2 class="section-title lang-block" data-lang="nl">Verdedigingsflow</h2>
        <h2 class="section-title lang-block" data-lang="ca">Flux de defensa</h2>
        <h2 class="section-title lang-block" data-lang="ru">Поток защиты</h2>
        <h2 class="section-title lang-block" data-lang="ja">防御フロー</h2>
        <h2 class="section-title lang-block" data-lang="ko">방어 흐름</h2>
        <h2 class="section-title lang-block" data-lang="zh">防御流程</h2>
        <h2 class="section-title lang-block" data-lang="hi">डिफेंस फ्लो</h2>
        <h2 class="section-title lang-block" data-lang="ar">تدفق الحماية</h2>
        <h2 class="section-title lang-block" data-lang="he">זרימת הגנה</h2>
        <div class="timeline">
          <div class="step">
            <div class="step-number">1</div>
            <div>
              <div class="lang-block" data-lang="es">Extension y agente detectan el intento.</div>
              <div class="lang-block" data-lang="en">Extension and agent detect the attempt.</div>
              <div class="lang-block" data-lang="pt">Extensao e agente detectam a tentativa.</div>
              <div class="lang-block" data-lang="fr">Extension et agent detectent la tentative.</div>
              <div class="lang-block" data-lang="de">Erweiterung und Agent erkennen den Versuch.</div>
              <div class="lang-block" data-lang="nl">Extensie en agent detecteren de poging.</div>
              <div class="lang-block" data-lang="ca">L extensio i l agent detecten l intent.</div>
              <div class="lang-block" data-lang="ru">Расширение и агент обнаруживают попытку.</div>
              <div class="lang-block" data-lang="ja">拡張機能とエージェントが試行を検知。</div>
              <div class="lang-block" data-lang="ko">확장 프로그램과 에이전트가 시도를 감지합니다.</div>
              <div class="lang-block" data-lang="zh">扩展和代理检测到尝试。</div>
              <div class="lang-block" data-lang="hi">एक्सटेंशन और एजेंट प्रयास का पता लगाते हैं।</div>
              <div class="lang-block" data-lang="ar">يرصد الامتداد والوكيل المحاولة.</div>
              <div class="lang-block" data-lang="he">התוסף והסוכן מזהים את הניסיון.</div>
            </div>
          </div>
          <div class="step">
            <div class="step-number">2</div>
            <div>
              <div class="lang-block" data-lang="es">Se bloquea el flujo o se alerta al usuario.</div>
              <div class="lang-block" data-lang="en">Flow is blocked or the user is warned.</div>
              <div class="lang-block" data-lang="pt">O fluxo e bloqueado ou o usuario e alertado.</div>
              <div class="lang-block" data-lang="fr">Le flux est bloque ou l utilisateur est alerte.</div>
              <div class="lang-block" data-lang="de">Der Ablauf wird blockiert oder der Nutzer wird gewarnt.</div>
              <div class="lang-block" data-lang="nl">De flow wordt geblokkeerd of de gebruiker wordt gewaarschuwd.</div>
              <div class="lang-block" data-lang="ca">Es bloqueja el flux o s alerta l usuari.</div>
              <div class="lang-block" data-lang="ru">Поток блокируется или пользователь получает предупреждение.</div>
              <div class="lang-block" data-lang="ja">フローがブロックされるか、ユーザーに警告します。</div>
              <div class="lang-block" data-lang="ko">흐름이 차단되거나 사용자에게 경고합니다.</div>
              <div class="lang-block" data-lang="zh">流程被阻断或向用户发出警告。</div>
              <div class="lang-block" data-lang="hi">फ्लो ब्लॉक किया जाता है या यूज़र को चेतावनी दी जाती है।</div>
              <div class="lang-block" data-lang="ar">يتم حظر التدفق او تنبيه المستخدم.</div>
              <div class="lang-block" data-lang="he">הזרימה נחסמת או שהמשתמש מקבל אזהרה.</div>
            </div>
          </div>
          <div class="step">
            <div class="step-number">3</div>
            <div>
              <div class="lang-block" data-lang="es">El backend guarda evidencia y dashboards para analisis.</div>
              <div class="lang-block" data-lang="en">Backend stores evidence and dashboards for analysis.</div>
              <div class="lang-block" data-lang="pt">O backend guarda evidencias e dashboards para analise.</div>
              <div class="lang-block" data-lang="fr">Le backend conserve les preuves et dashboards pour analyse.</div>
              <div class="lang-block" data-lang="de">Das Backend speichert Beweise und Dashboards fuer die Analyse.</div>
              <div class="lang-block" data-lang="nl">De backend slaat bewijsmateriaal en dashboards op voor analyse.</div>
              <div class="lang-block" data-lang="ca">El backend desa evidencies i dashboards per a l analisi.</div>
              <div class="lang-block" data-lang="ru">Бэкенд сохраняет доказательства и дашборды для анализа.</div>
              <div class="lang-block" data-lang="ja">バックエンドが証跡とダッシュボードを保存して分析します。</div>
              <div class="lang-block" data-lang="ko">백엔드가 증거와 대시보드를 저장해 분석합니다.</div>
              <div class="lang-block" data-lang="zh">后端保存证据和仪表板以供分析。</div>
              <div class="lang-block" data-lang="hi">बैकएंड विश्लेषण के लिए सबूत और डैशबोर्ड सहेजता है।</div>
              <div class="lang-block" data-lang="ar">يحفظ الـbackend الادلة ولوحات التحكم للتحليل.</div>
              <div class="lang-block" data-lang="he">ה־backend שומר ראיות ודשבורדים לניתוח.</div>
            </div>
          </div>
        </div>
      </div>
      <div class="card reveal delay-1">
        <div class="pill">
          <span class="lang-inline" data-lang="es">Componentes</span>
          <span class="lang-inline" data-lang="en">Components</span>
          <span class="lang-inline" data-lang="pt">Componentes</span>
          <span class="lang-inline" data-lang="fr">Composants</span>
          <span class="lang-inline" data-lang="de">Komponenten</span>
          <span class="lang-inline" data-lang="nl">Componenten</span>
          <span class="lang-inline" data-lang="ca">Components</span>
          <span class="lang-inline" data-lang="ru">Компоненты</span>
          <span class="lang-inline" data-lang="ja">コンポーネント</span>
          <span class="lang-inline" data-lang="ko">구성 요소</span>
          <span class="lang-inline" data-lang="zh">组件</span>
          <span class="lang-inline" data-lang="hi">कंपोनेंट्स</span>
          <span class="lang-inline" data-lang="ar">المكونات</span>
          <span class="lang-inline" data-lang="he">רכיבים</span>
        </div>
        <h2 class="section-title lang-block" data-lang="es">Piezas clave</h2>
        <h2 class="section-title lang-block" data-lang="en">Core Components</h2>
        <h2 class="section-title lang-block" data-lang="pt">Componentes</h2>
        <h2 class="section-title lang-block" data-lang="fr">Composants</h2>
        <h2 class="section-title lang-block" data-lang="de">Kernkomponenten</h2>
        <h2 class="section-title lang-block" data-lang="nl">Kerncomponenten</h2>
        <h2 class="section-title lang-block" data-lang="ca">Components clau</h2>
        <h2 class="section-title lang-block" data-lang="ru">Ключевые компоненты</h2>
        <h2 class="section-title lang-block" data-lang="ja">主要コンポーネント</h2>
        <h2 class="section-title lang-block" data-lang="ko">핵심 구성 요소</h2>
        <h2 class="section-title lang-block" data-lang="zh">核心组件</h2>
        <h2 class="section-title lang-block" data-lang="hi">मुख्य घटक</h2>
        <h2 class="section-title lang-block" data-lang="ar">المكونات الاساسية</h2>
        <h2 class="section-title lang-block" data-lang="he">רכיבים מרכזיים</h2>
        <div class="grid two">
          <div>
            <div style="font-family: 'Sora', sans-serif;">
              <span class="lang-inline" data-lang="es">Extension de navegador</span>
              <span class="lang-inline" data-lang="en">Browser Extension</span>
              <span class="lang-inline" data-lang="pt">Extensao de navegador</span>
              <span class="lang-inline" data-lang="fr">Extension navigateur</span>
              <span class="lang-inline" data-lang="de">Browser-Erweiterung</span>
              <span class="lang-inline" data-lang="nl">Browserextensie</span>
              <span class="lang-inline" data-lang="ca">Extensio del navegador</span>
              <span class="lang-inline" data-lang="ru">Расширение браузера</span>
              <span class="lang-inline" data-lang="ja">ブラウザ拡張</span>
              <span class="lang-inline" data-lang="ko">브라우저 확장</span>
              <span class="lang-inline" data-lang="zh">浏览器扩展</span>
              <span class="lang-inline" data-lang="hi">ब्राउज़र एक्सटेंशन</span>
              <span class="lang-inline" data-lang="ar">امتداد المتصفح</span>
              <span class="lang-inline" data-lang="he">תוסף דפדפן</span>
            </div>
            <p class="section-sub lang-block" data-lang="es" style="margin: 6px 0 12px;">Detector MV3 y bloqueo contextual.</p>
            <p class="section-sub lang-block" data-lang="en" style="margin: 6px 0 12px;">MV3 detector and contextual blocking.</p>
            <p class="section-sub lang-block" data-lang="pt" style="margin: 6px 0 12px;">Detector MV3 e bloqueio contextual.</p>
            <p class="section-sub lang-block" data-lang="fr" style="margin: 6px 0 12px;">Detecteur MV3 et blocage contextuel.</p>
            <p class="section-sub lang-block" data-lang="de" style="margin: 6px 0 12px;">MV3-Detektor und kontextuelles Blockieren.</p>
            <p class="section-sub lang-block" data-lang="nl" style="margin: 6px 0 12px;">MV3-detector en contextuele blokkering.</p>
            <p class="section-sub lang-block" data-lang="ca" style="margin: 6px 0 12px;">Detector MV3 i bloqueig contextual.</p>
            <p class="section-sub lang-block" data-lang="ru" style="margin: 6px 0 12px;">MV3-детектор и контекстная блокировка.</p>
            <p class="section-sub lang-block" data-lang="ja" style="margin: 6px 0 12px;">MV3検知と文脈ブロック.</p>
            <p class="section-sub lang-block" data-lang="ko" style="margin: 6px 0 12px;">MV3 탐지 및 문맥 차단.</p>
            <p class="section-sub lang-block" data-lang="zh" style="margin: 6px 0 12px;">MV3 检测与情境阻断。</p>
            <p class="section-sub lang-block" data-lang="hi" style="margin: 6px 0 12px;">MV3 डिटेक्शन और संदर्भ-आधारित ब्लॉकिंग।</p>
            <p class="section-sub lang-block" data-lang="ar" style="margin: 6px 0 12px;">كشف MV3 وحظر سياقي.</p>
            <p class="section-sub lang-block" data-lang="he" style="margin: 6px 0 12px;">זיהוי MV3 וחסימה הקשרית.</p>
          </div>
          <div>
            <div style="font-family: 'Sora', sans-serif;">
              <span class="lang-inline" data-lang="es">Agente Windows</span>
              <span class="lang-inline" data-lang="en">Windows Agent</span>
              <span class="lang-inline" data-lang="pt">Agente Windows</span>
              <span class="lang-inline" data-lang="fr">Agent Windows</span>
              <span class="lang-inline" data-lang="de">Windows-Agent</span>
              <span class="lang-inline" data-lang="nl">Windows-agent</span>
              <span class="lang-inline" data-lang="ca">Agent Windows</span>
              <span class="lang-inline" data-lang="ru">Агент Windows</span>
              <span class="lang-inline" data-lang="ja">Windows エージェント</span>
              <span class="lang-inline" data-lang="ko">Windows 에이전트</span>
              <span class="lang-inline" data-lang="zh">Windows 代理</span>
              <span class="lang-inline" data-lang="hi">Windows एजेंट</span>
              <span class="lang-inline" data-lang="ar">وكيل Windows</span>
              <span class="lang-inline" data-lang="he">סוכן Windows</span>
            </div>
            <p class="section-sub lang-block" data-lang="es" style="margin: 6px 0 12px;">Observa portapapeles y ejecucion.</p>
            <p class="section-sub lang-block" data-lang="en" style="margin: 6px 0 12px;">Watches clipboard and execution.</p>
            <p class="section-sub lang-block" data-lang="pt" style="margin: 6px 0 12px;">Observa clipboard e execucao.</p>
            <p class="section-sub lang-block" data-lang="fr" style="margin: 6px 0 12px;">Surveille presse-papiers et execution.</p>
            <p class="section-sub lang-block" data-lang="de" style="margin: 6px 0 12px;">Ueberwacht Zwischenablage und Ausfuehrung.</p>
            <p class="section-sub lang-block" data-lang="nl" style="margin: 6px 0 12px;">Houdt klembord en uitvoering in de gaten.</p>
            <p class="section-sub lang-block" data-lang="ca" style="margin: 6px 0 12px;">Observa el porta-retalls i l execucio.</p>
            <p class="section-sub lang-block" data-lang="ru" style="margin: 6px 0 12px;">Отслеживает буфер обмена и выполнение.</p>
            <p class="section-sub lang-block" data-lang="ja" style="margin: 6px 0 12px;">クリップボードと実行を監視。</p>
            <p class="section-sub lang-block" data-lang="ko" style="margin: 6px 0 12px;">클립보드와 실행을 관찰합니다.</p>
            <p class="section-sub lang-block" data-lang="zh" style="margin: 6px 0 12px;">监视剪贴板和执行。</p>
            <p class="section-sub lang-block" data-lang="hi" style="margin: 6px 0 12px;">क्लिपबोर्ड और निष्पादन पर नज़र रखता है।</p>
            <p class="section-sub lang-block" data-lang="ar" style="margin: 6px 0 12px;">يراقب الحافظة والتنفيذ.</p>
            <p class="section-sub lang-block" data-lang="he" style="margin: 6px 0 12px;">מנטר לוח גזירים וביצוע.</p>
          </div>
          <div>
            <div style="font-family: 'Sora', sans-serif;">
              <span class="lang-inline" data-lang="es">PHP + SQLite</span>
              <span class="lang-inline" data-lang="en">PHP + SQLite</span>
              <span class="lang-inline" data-lang="pt">PHP + SQLite</span>
              <span class="lang-inline" data-lang="fr">PHP + SQLite</span>
              <span class="lang-inline" data-lang="de">PHP + SQLite</span>
              <span class="lang-inline" data-lang="nl">PHP + SQLite</span>
              <span class="lang-inline" data-lang="ca">PHP + SQLite</span>
              <span class="lang-inline" data-lang="ru">PHP + SQLite</span>
              <span class="lang-inline" data-lang="ja">PHP + SQLite</span>
              <span class="lang-inline" data-lang="ko">PHP + SQLite</span>
              <span class="lang-inline" data-lang="zh">PHP + SQLite</span>
              <span class="lang-inline" data-lang="hi">PHP + SQLite</span>
              <span class="lang-inline" data-lang="ar">PHP + SQLite</span>
              <span class="lang-inline" data-lang="he">PHP + SQLite</span>
            </div>
            <p class="section-sub lang-block" data-lang="es" style="margin: 6px 0 12px;">Backend para reportes y auditoria.</p>
            <p class="section-sub lang-block" data-lang="en" style="margin: 6px 0 12px;">Backend for reports and audit trails.</p>
            <p class="section-sub lang-block" data-lang="pt" style="margin: 6px 0 12px;">Backend para relatorios e auditoria.</p>
            <p class="section-sub lang-block" data-lang="fr" style="margin: 6px 0 12px;">Backend pour rapports et audit.</p>
            <p class="section-sub lang-block" data-lang="de" style="margin: 6px 0 12px;">Backend fuer Reports und Audits.</p>
            <p class="section-sub lang-block" data-lang="nl" style="margin: 6px 0 12px;">Backend voor rapporten en audits.</p>
            <p class="section-sub lang-block" data-lang="ca" style="margin: 6px 0 12px;">Backend per a informes i auditoria.</p>
            <p class="section-sub lang-block" data-lang="ru" style="margin: 6px 0 12px;">Backend для отчетов и аудита.</p>
            <p class="section-sub lang-block" data-lang="ja" style="margin: 6px 0 12px;">レポートと監査のためのバックエンド。</p>
            <p class="section-sub lang-block" data-lang="ko" style="margin: 6px 0 12px;">보고서 및 감사용 백엔드.</p>
            <p class="section-sub lang-block" data-lang="zh" style="margin: 6px 0 12px;">用于报告和审计的后端。</p>
            <p class="section-sub lang-block" data-lang="hi" style="margin: 6px 0 12px;">रिपोर्ट और ऑडिट के लिए बैकएंड।</p>
            <p class="section-sub lang-block" data-lang="ar" style="margin: 6px 0 12px;">Backend للتقارير والتدقيق.</p>
            <p class="section-sub lang-block" data-lang="he" style="margin: 6px 0 12px;">Backend לדוחות ולביקורת.</p>
          </div>
          <div>
            <div style="font-family: 'Sora', sans-serif;">
              <span class="lang-inline" data-lang="es">Dashboard de operaciones</span>
              <span class="lang-inline" data-lang="en">Ops Dashboard</span>
              <span class="lang-inline" data-lang="pt">Dashboard de operacoes</span>
              <span class="lang-inline" data-lang="fr">Dashboard ops</span>
              <span class="lang-inline" data-lang="de">Ops-Dashboard</span>
              <span class="lang-inline" data-lang="nl">Ops-dashboard</span>
              <span class="lang-inline" data-lang="ca">Dashboard d operacions</span>
              <span class="lang-inline" data-lang="ru">Ops-дэшборд</span>
              <span class="lang-inline" data-lang="ja">Ops ダッシュボード</span>
              <span class="lang-inline" data-lang="ko">운영 대시보드</span>
              <span class="lang-inline" data-lang="zh">运维仪表板</span>
              <span class="lang-inline" data-lang="hi">ऑप्स डैशबोर्ड</span>
              <span class="lang-inline" data-lang="ar">لوحة عمليات</span>
              <span class="lang-inline" data-lang="he">דשבורד תפעול</span>
            </div>
            <p class="section-sub lang-block" data-lang="es" style="margin: 6px 0 12px;">Triage, roles y analitica.</p>
            <p class="section-sub lang-block" data-lang="en" style="margin: 6px 0 12px;">Triage, roles, and analytics.</p>
            <p class="section-sub lang-block" data-lang="pt" style="margin: 6px 0 12px;">Triage, roles e analitica.</p>
            <p class="section-sub lang-block" data-lang="fr" style="margin: 6px 0 12px;">Triage, roles et analytics.</p>
            <p class="section-sub lang-block" data-lang="de" style="margin: 6px 0 12px;">Triage, Rollen und Analytics.</p>
            <p class="section-sub lang-block" data-lang="nl" style="margin: 6px 0 12px;">Triage, rollen en analytics.</p>
            <p class="section-sub lang-block" data-lang="ca" style="margin: 6px 0 12px;">Triage, rols i analitica.</p>
            <p class="section-sub lang-block" data-lang="ru" style="margin: 6px 0 12px;">Триаж, роли и аналитика.</p>
            <p class="section-sub lang-block" data-lang="ja" style="margin: 6px 0 12px;">トリアージ、役割、分析。</p>
            <p class="section-sub lang-block" data-lang="ko" style="margin: 6px 0 12px;">트리아지, 역할, 분석.</p>
            <p class="section-sub lang-block" data-lang="zh" style="margin: 6px 0 12px;">分诊、角色与分析。</p>
            <p class="section-sub lang-block" data-lang="hi" style="margin: 6px 0 12px;">ट्रायएज, रोल्स और एनालिटिक्स।</p>
            <p class="section-sub lang-block" data-lang="ar" style="margin: 6px 0 12px;">فرز، ادوار وتحليلات.</p>
            <p class="section-sub lang-block" data-lang="he" style="margin: 6px 0 12px;">טריאז', תפקידים ואנליטיקה.</p>
          </div>
        </div>
      </div>
    </section>

    <section class="grid three">
      <div class="card reveal">
        <h3 class="lang-block" data-lang="es">Para blue teams</h3>
        <h3 class="lang-block" data-lang="en">For Blue Teams</h3>
        <h3 class="lang-block" data-lang="pt">Para blue teams</h3>
        <h3 class="lang-block" data-lang="fr">Pour les blue teams</h3>
        <h3 class="lang-block" data-lang="de">Fuer Blue Teams</h3>
        <h3 class="lang-block" data-lang="nl">Voor blue teams</h3>
        <h3 class="lang-block" data-lang="ca">Per a blue teams</h3>
        <h3 class="lang-block" data-lang="ru">Для blue teams</h3>
        <h3 class="lang-block" data-lang="ja">Blue Team向け</h3>
        <h3 class="lang-block" data-lang="ko">블루팀을 위해</h3>
        <h3 class="lang-block" data-lang="zh">面向蓝队</h3>
        <h3 class="lang-block" data-lang="hi">ब्लू टीमों के लिए</h3>
        <h3 class="lang-block" data-lang="ar">لفرق الدفاع</h3>
        <h3 class="lang-block" data-lang="he">לצוותי כחול</h3>
        <p class="lang-block" data-lang="es">Entrena, analiza y valida defensas contra flujos reales de ClickFix.</p>
        <p class="lang-block" data-lang="en">Train, analyze, and validate defenses against real ClickFix flows.</p>
        <p class="lang-block" data-lang="pt">Treine, analise e valide defesas contra fluxos ClickFix reais.</p>
        <p class="lang-block" data-lang="fr">Entrainez, analysez et validez les defenses contre des flux ClickFix reels.</p>
        <p class="lang-block" data-lang="de">Trainieren, analysieren und validieren Sie Abwehrmassnahmen gegen reale ClickFix-Flows.</p>
        <p class="lang-block" data-lang="nl">Train, analyseer en valideer verdedigingen tegen echte ClickFix-flows.</p>
        <p class="lang-block" data-lang="ca">Entrena, analitza i valida defenses contra fluxos reals de ClickFix.</p>
        <p class="lang-block" data-lang="ru">Тренируйте, анализируйте и валидируйте защиты против реальных потоков ClickFix.</p>
        <p class="lang-block" data-lang="ja">実際のClickFixフローに対する防御を訓練・分析・検証。</p>
        <p class="lang-block" data-lang="ko">실제 ClickFix 흐름에 대한 방어를 훈련, 분석, 검증.</p>
        <p class="lang-block" data-lang="zh">训练、分析并验证对真实 ClickFix 流程的防御。</p>
        <p class="lang-block" data-lang="hi">वास्तविक ClickFix फ्लो के खिलाफ सुरक्षा को ट्रेन, विश्लेषण और वैलिडेट करें।</p>
        <p class="lang-block" data-lang="ar">درّب وحلل وحقق الدفاعات ضد تدفقات ClickFix الحقيقية.</p>
        <p class="lang-block" data-lang="he">אמן, נתח ואמת הגנות מול זרימות ClickFix אמיתיות.</p>
      </div>
      <div class="card reveal delay-1">
        <h3 class="lang-block" data-lang="es">Para SOC y IR</h3>
        <h3 class="lang-block" data-lang="en">For SOC and IR</h3>
        <h3 class="lang-block" data-lang="pt">Para SOC e IR</h3>
        <h3 class="lang-block" data-lang="fr">Pour SOC et IR</h3>
        <h3 class="lang-block" data-lang="de">Fuer SOC und IR</h3>
        <h3 class="lang-block" data-lang="nl">Voor SOC en IR</h3>
        <h3 class="lang-block" data-lang="ca">Per a SOC i IR</h3>
        <h3 class="lang-block" data-lang="ru">Для SOC и IR</h3>
        <h3 class="lang-block" data-lang="ja">SOCとIR向け</h3>
        <h3 class="lang-block" data-lang="ko">SOC 및 IR용</h3>
        <h3 class="lang-block" data-lang="zh">面向 SOC 和 IR</h3>
        <h3 class="lang-block" data-lang="hi">SOC और IR के लिए</h3>
        <h3 class="lang-block" data-lang="ar">لـ SOC و IR</h3>
        <h3 class="lang-block" data-lang="he">ל‑SOC ו‑IR</h3>
        <p class="lang-block" data-lang="es">Alertas estructuradas y evidencia lista para investigacion.</p>
        <p class="lang-block" data-lang="en">Structured alerts and evidence ready for investigation.</p>
        <p class="lang-block" data-lang="pt">Alertas estruturados e evidencia pronta para investigacao.</p>
        <p class="lang-block" data-lang="fr">Alertes structurees et preuves pretes pour investigation.</p>
        <p class="lang-block" data-lang="de">Strukturierte Alerts und Beweise, bereit fuer Untersuchungen.</p>
        <p class="lang-block" data-lang="nl">Gestructureerde alerts en bewijs dat klaar is voor onderzoek.</p>
        <p class="lang-block" data-lang="ca">Alertes estructurades i evidencia llesta per a investigacio.</p>
        <p class="lang-block" data-lang="ru">Структурированные оповещения и доказательства, готовые для расследования.</p>
        <p class="lang-block" data-lang="ja">調査に使える構造化アラートと証拠。</p>
        <p class="lang-block" data-lang="ko">조사를 위한 구조화된 경고와 증거.</p>
        <p class="lang-block" data-lang="zh">结构化警报和可用于调查的证据。</p>
        <p class="lang-block" data-lang="hi">जांच के लिए तैयार संरचित अलर्ट और प्रमाण.</p>
        <p class="lang-block" data-lang="ar">تنبيهات منظمة وادلة جاهزة للتحقيق.</p>
        <p class="lang-block" data-lang="he">התראות מובנות וראיות מוכנות לחקירה.</p>
      </div>
      <div class="card reveal delay-2">
        <h3 class="lang-block" data-lang="es">Para demos seguras</h3>
        <h3 class="lang-block" data-lang="en">For Safe Demos</h3>
        <h3 class="lang-block" data-lang="pt">Para demos seguras</h3>
        <h3 class="lang-block" data-lang="fr">Pour demos securisees</h3>
        <h3 class="lang-block" data-lang="de">Fuer sichere Demos</h3>
        <h3 class="lang-block" data-lang="nl">Voor veilige demo's</h3>
        <h3 class="lang-block" data-lang="ca">Per a demos segures</h3>
        <h3 class="lang-block" data-lang="ru">Для безопасных демо</h3>
        <h3 class="lang-block" data-lang="ja">安全なデモ向け</h3>
        <h3 class="lang-block" data-lang="ko">안전한 데모용</h3>
        <h3 class="lang-block" data-lang="zh">用于安全演示</h3>
        <h3 class="lang-block" data-lang="hi">सुरक्षित डेमो के लिए</h3>
        <h3 class="lang-block" data-lang="ar">لعروض تجريبية امنة</h3>
        <h3 class="lang-block" data-lang="he">להדגמות בטוחות</h3>
        <p class="lang-block" data-lang="es">Reproduce ataques sin riesgo, con controles y telemetria.</p>
        <p class="lang-block" data-lang="en">Reproduce attacks safely with control and telemetry.</p>
        <div class="demo-link-stack">
          <a class="button secondary" href="demo/index.php" target="_blank" rel="noopener" data-i18n-text="demo_catalog_button">Demo catalog</a>
          <a class="button secondary" href="demo/attacker-sample.php" target="_blank" rel="noopener" data-i18n-text="demo_attacker_button">Demo: Attacker sample</a>
          <a class="button secondary" href="demo/iframed.html" target="_blank" rel="noopener" data-i18n-text="demo_iframed_button">Demo: Iframed flow</a>
        </div>
        <p class="lang-block" data-lang="pt">Reproduza ataques com seguranca, controle e telemetria.</p>
        <p class="lang-block" data-lang="fr">Reproduisez des attaques en securite avec controle et telemetrie.</p>
        <p class="lang-block" data-lang="de">Reproduzieren Sie Angriffe risikofrei mit Kontrollen und Telemetrie.</p>
        <p class="lang-block" data-lang="nl">Boots aanvallen veilig na met controle en telemetrie.</p>
        <p class="lang-block" data-lang="ca">Reprodueix atacs sense risc, amb controls i telemetria.</p>
        <p class="lang-block" data-lang="ru">Воспроизводите атаки безопасно с контролем и телеметрией.</p>
        <p class="lang-block" data-lang="ja">制御とテレメトリで安全に攻撃を再現。</p>
        <p class="lang-block" data-lang="ko">통제와 텔레메트리로 안전하게 공격을 재현합니다.</p>
        <p class="lang-block" data-lang="zh">在控制和遥测下安全复现攻击。</p>
        <p class="lang-block" data-lang="hi">नियंत्रण और टेलीमेट्री के साथ सुरक्षित रूप से हमले पुन: उत्पन्न करें।</p>
        <p class="lang-block" data-lang="ar">اعادة تمثيل الهجمات بأمان مع ضوابط وتليمترى.</p>
        <p class="lang-block" data-lang="he">שחזר תקיפות בבטחה עם בקרה וטלמטריה.</p>
      </div>
    </section>

    <?php if ($indexShowInternalAds): ?>
      <section class="card reveal internal-ad-section" id="sponsored-research-slots">
        <div class="pill" data-i18n-text="internal_ads_badge">Sponsored</div>
        <h2 class="section-title" data-i18n-text="internal_ads_title">Sponsored slots for analysts and deployments</h2>
        <p class="section-sub" data-i18n-text="internal_ads_subtitle">Non-intrusive messages for public, junior, and mid audiences. Senior and admin profiles do not see these blocks.</p>
        <div class="internal-ad-grid">
          <?php foreach ($indexInternalAds as $adRow): ?>
            <?php
              $indexAdTheme = clickfix_internal_ad_theme((string) ($adRow['theme'] ?? 'cyan'));
              $indexAdUrl = clickfix_sanitize_http_url((string) ($adRow['cta_url'] ?? ''));
              $indexAdLabel = trim((string) ($adRow['cta_label'] ?? ''));
              $indexAdPlacement = strtoupper((string) ($adRow['placement'] ?? 'INDEX'));
            ?>
            <article class="internal-ad-card <?= htmlspecialchars($indexAdTheme, ENT_QUOTES, 'UTF-8'); ?>">
              <span class="internal-ad-kicker"><span data-i18n-text="internal_ads_test_prefix">test ad</span> | <?= htmlspecialchars($indexAdPlacement, ENT_QUOTES, 'UTF-8'); ?></span>
              <h3>
                <?php if (trim((string) ($adRow['title'] ?? '')) !== ''): ?>
                  <?= htmlspecialchars((string) $adRow['title'], ENT_QUOTES, 'UTF-8'); ?>
                <?php else: ?>
                  <span data-i18n-text="internal_ads_slot_fallback">Sponsored slot</span>
                <?php endif; ?>
              </h3>
              <p><?= nl2br(htmlspecialchars((string) ($adRow['body'] ?? ''), ENT_QUOTES, 'UTF-8')); ?></p>
              <?php if ($indexAdUrl !== ''): ?>
                <a class="button secondary" href="<?= htmlspecialchars($indexAdUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">
                  <?php if ($indexAdLabel !== ''): ?>
                    <?= htmlspecialchars($indexAdLabel, ENT_QUOTES, 'UTF-8'); ?>
                  <?php else: ?>
                    <span data-i18n-text="internal_ads_open_default">Open</span>
                  <?php endif; ?>
                </a>
              <?php endif; ?>
            </article>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>

    <section class="card reveal">
      <div class="pill">
        <span class="lang-inline" data-lang="es">Nota de defensa</span>
        <span class="lang-inline" data-lang="en">Defense Note</span>
        <span class="lang-inline" data-lang="pt">Nota de defesa</span>
        <span class="lang-inline" data-lang="fr">Note de defense</span>
        <span class="lang-inline" data-lang="de">Verteidigungshinweis</span>
        <span class="lang-inline" data-lang="nl">Verdedigingsnotitie</span>
        <span class="lang-inline" data-lang="ca">Nota de defensa</span>
        <span class="lang-inline" data-lang="ru">Примечание по защите</span>
        <span class="lang-inline" data-lang="ja">防御メモ</span>
        <span class="lang-inline" data-lang="ko">방어 노트</span>
        <span class="lang-inline" data-lang="zh">防御说明</span>
        <span class="lang-inline" data-lang="hi">डिफेंस नोट</span>
        <span class="lang-inline" data-lang="ar">ملاحظة دفاع</span>
        <span class="lang-inline" data-lang="he">הערת הגנה</span>
      </div>
      <h2 class="section-title lang-block" data-lang="es">Uso responsable</h2>
      <h2 class="section-title lang-block" data-lang="en">Responsible Use</h2>
      <h2 class="section-title lang-block" data-lang="pt">Uso responsavel</h2>
      <h2 class="section-title lang-block" data-lang="fr">Usage responsable</h2>
      <h2 class="section-title lang-block" data-lang="de">Verantwortungsvoller Einsatz</h2>
      <h2 class="section-title lang-block" data-lang="nl">Verantwoord gebruik</h2>
      <h2 class="section-title lang-block" data-lang="ca">Us responsable</h2>
      <h2 class="section-title lang-block" data-lang="ru">Ответственное использование</h2>
      <h2 class="section-title lang-block" data-lang="ja">責任ある利用</h2>
      <h2 class="section-title lang-block" data-lang="ko">책임 있는 사용</h2>
      <h2 class="section-title lang-block" data-lang="zh">负责任的使用</h2>
      <h2 class="section-title lang-block" data-lang="hi">जिम्मेदार उपयोग</h2>
      <h2 class="section-title lang-block" data-lang="ar">استخدام مسؤول</h2>
      <h2 class="section-title lang-block" data-lang="he">שימוש אחראי</h2>
      <p class="section-sub lang-block" data-lang="es">Este proyecto es educativo y de referencia. Ajusta las politicas, reglas y flujo de bloqueo a tu entorno antes de desplegar.</p>
      <p class="section-sub lang-block" data-lang="en">This project is educational and reference-grade. Adapt policies, rules, and blocking flow before deploying in production.</p>
      <p class="section-sub lang-block" data-lang="pt">Este projeto e educacional e de referencia. Ajuste politicas, regras e fluxo de bloqueio antes de implantar.</p>
      <p class="section-sub lang-block" data-lang="fr">Ce projet est educatif et de reference. Ajustez les politiques, regles et flux de blocage avant de deployer.</p>
      <p class="section-sub lang-block" data-lang="de">Dieses Projekt ist lehrreich und als Referenz gedacht. Passen Sie Richtlinien, Regeln und den Blockier-Flow vor dem Einsatz an.</p>
      <p class="section-sub lang-block" data-lang="nl">Dit project is educatief en als referentie bedoeld. Pas beleid, regels en de blokkeerflow aan voordat je uitrolt.</p>
      <p class="section-sub lang-block" data-lang="ca">Aquest projecte es educatiu i de referencia. Ajusta politiques, regles i el flux de bloqueig abans de desplegar.</p>
      <p class="section-sub lang-block" data-lang="ru">Этот проект учебный и справочный. Настройте политики, правила и поток блокировок перед развертыванием.</p>
      <p class="section-sub lang-block" data-lang="ja">このプロジェクトは教育目的のリファレンスです。導入前にポリシー、ルール、ブロックフローを調整してください。</p>
      <p class="section-sub lang-block" data-lang="ko">이 프로젝트는 교육용 레퍼런스입니다. 배포 전에 정책, 규칙, 차단 흐름을 조정하세요.</p>
      <p class="section-sub lang-block" data-lang="zh">该项目是教育用途的参考实现。部署前请调整策略、规则和阻断流程。</p>
      <p class="section-sub lang-block" data-lang="hi">यह प्रोजेक्ट शैक्षिक और संदर्भ-ग्रेड है। डिप्लॉय से पहले नीतियाँ, नियम और ब्लॉकिंग फ्लो अनुकूलित करें।</p>
      <p class="section-sub lang-block" data-lang="ar">هذا المشروع تعليمي ومرجعي. عدل السياسات والقواعد وتدفق الحظر قبل النشر.</p>
      <p class="section-sub lang-block" data-lang="he">זהו פרויקט חינוכי וייחוס. התאימו מדיניות, כללים וזרימת חסימה לפני פריסה.</p>
    </section>

    <?php if ($indexShowMonetizationSupport): ?>
      <section class="card reveal monetization-section" id="support-project">
        <div class="pill" data-i18n-text="support_badge">Support</div>
        <h2 class="section-title" data-i18n-text="support_title_public">Support ClickFix Mitigator</h2>
        <p class="section-sub" data-i18n-text="support_sub_public">If the project delivers value, you can support it with donations or sponsorship. Monetization is optional and transparent.</p>
        <div class="monetization-grid">
          <article class="support-card">
            <h3 data-i18n-text="support_donations_title">Donations</h3>
            <p data-i18n-text="support_donations_sub">Help fund maintenance, new detection rules, and improvements for the extension.</p>
            <div class="support-actions">
              <?php if (!empty($monetization['donation_paypal_url'])): ?>
                <a class="button secondary" href="<?= htmlspecialchars((string) $monetization['donation_paypal_url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">PayPal</a>
              <?php endif; ?>
              <?php if (!empty($monetization['donation_kofi_url'])): ?>
                <a class="button secondary" href="<?= htmlspecialchars((string) $monetization['donation_kofi_url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">Ko-fi</a>
              <?php endif; ?>
              <?php if (!empty($monetization['donation_stripe_url'])): ?>
                <a class="button secondary" href="<?= htmlspecialchars((string) $monetization['donation_stripe_url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">Stripe</a>
              <?php endif; ?>
              <?php if (empty($monetization['has_donations'])): ?>
                <a class="button secondary" href="mailto:security@jordiserrano.me" data-i18n-text="support_contact_sponsor">Contact for sponsorship</a>
              <?php endif; ?>
            </div>
          </article>
          <article class="support-card">
            <h3 data-i18n-text="support_sponsor_title">Want to sponsor?</h3>
            <p data-i18n-text="support_sponsor_sub">Sponsor the project to gain visible placement on the public site, support research, and help fund defensive improvements without intrusive advertising.</p>
            <div class="support-actions">
              <a class="button secondary" href="mailto:security@jordiserrano.me?subject=ClickFix%20Mitigator%20Sponsorship" data-i18n-text="support_sponsor_cta">Become a sponsor</a>
              <a class="button secondary" href="<?= htmlspecialchars($githubRepoUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer" data-i18n-text="support_sponsor_repo">Review the project</a>
            </div>
          </article>
          <article class="support-card">
            <h3 data-i18n-text="support_ads_title_public">Sponsorship / ads</h3>
            <?php if ($indexAdsAudienceEnabled): ?>
              <p data-i18n-text="support_ads_enabled_sub">Area for sponsors or non-intrusive ads. It is only enabled when you configure the ad provider in your environment.</p>
            <?php else: ?>
              <p data-i18n-text="support_ads_hidden_sub">This ad space is hidden for senior or admin profiles. It is reserved for public, junior, and mid audiences.</p>
            <?php endif; ?>
            <?php if ($indexShowMonetizationAds): ?>
              <div class="ad-shell">
                <ins class="adsbygoogle"
                     style="display:block;width:100%;min-height:90px"
                     data-ad-client="<?= htmlspecialchars((string) $monetization['adsense_client'], ENT_QUOTES, 'UTF-8'); ?>"
                     data-ad-slot="<?= htmlspecialchars((string) $monetization['adsense_slot'], ENT_QUOTES, 'UTF-8'); ?>"
                     data-ad-format="auto"
                     data-full-width-responsive="true"></ins>
              </div>
            <?php else: ?>
              <div class="ad-shell">
                <span style="color:var(--muted);font-size:13px;" data-i18n-text="<?= htmlspecialchars($indexAdsAudienceEnabled ? 'support_ads_available' : 'support_ads_hidden_profile', ENT_QUOTES, 'UTF-8'); ?>"><?= $indexAdsAudienceEnabled ? 'Sponsorship space available' : 'Ad slot hidden for this profile'; ?></span>
              </div>
            <?php endif; ?>
          </article>
        </div>
      </section>
    <?php endif; ?>

    <section class="marketing-bar" id="marketing-bar" data-i18n-aria="marketing_bar_aria">
      <div class="marketing-bar-main">
        <strong data-i18n-text="marketing_bar_title">Start in under 10 minutes</strong>
        <span data-i18n-text="marketing_bar_subtitle">Install the extension, request access, and show value from your first investigation.</span>
      </div>
      <button class="marketing-bar-dismiss" id="marketing-bar-dismiss" type="button" data-i18n-aria="marketing_close">×</button>
      <div class="marketing-bar-actions">
        <a class="button primary cta-pulse" href="https://chromewebstore.google.com/detail/clickfix-mitigator/nmldafmgfcfopjoigbmmlmcnininifaa" target="_blank" rel="noopener" data-i18n-text="marketing_install">Install</a>
        <a class="button secondary" href="dashboard.php?page=access&amp;public=1" data-i18n-text="marketing_access">Access</a>
        <a class="button secondary" href="demo/index.php" target="_blank" rel="noopener" data-i18n-text="marketing_demos">Demos</a>
      </div>
    </section>

    </main>
    <footer class="footer">
      <div>
        <div class="lang-block" data-lang="es">ClickFix Mitigator | Defensa contra ClickFix</div>
        <div class="lang-block" data-lang="en">ClickFix Mitigator | Defense-first anti ClickFix</div>
        <div class="lang-block" data-lang="pt">ClickFix Mitigator | Defesa contra ClickFix</div>
        <div class="lang-block" data-lang="fr">ClickFix Mitigator | Defense contre ClickFix</div>
        <div class="lang-block" data-lang="de">ClickFix Mitigator | Abwehr gegen ClickFix</div>
        <div class="lang-block" data-lang="nl">ClickFix Mitigator | Verdediging tegen ClickFix</div>
        <div class="lang-block" data-lang="ca">ClickFix Mitigator | Defensa contra ClickFix</div>
        <div class="lang-block" data-lang="ru">ClickFix Mitigator | Защита от ClickFix</div>
        <div class="lang-block" data-lang="ja">ClickFix Mitigator | ClickFix対策</div>
        <div class="lang-block" data-lang="ko">ClickFix Mitigator | ClickFix 방어</div>
        <div class="lang-block" data-lang="zh">ClickFix Mitigator | ClickFix 防护</div>
        <div class="lang-block" data-lang="hi">ClickFix Mitigator | ClickFix रक्षा</div>
        <div class="lang-block" data-lang="ar">ClickFix Mitigator | دفاع ضد ClickFix</div>
        <div class="lang-block" data-lang="he">ClickFix Mitigator | הגנה נגד ClickFix</div>
      </div>
      <div>
        <a href="<?= htmlspecialchars($githubRepoUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer" data-i18n-text="footer_github">GitHub Repository</a>
      </div>
      <div>
        <a href="PrivacyPolicy.html">
          <span class="lang-inline" data-lang="es">Política de privacidad</span>
          <span class="lang-inline" data-lang="en">Privacy Policy</span>
          <span class="lang-inline" data-lang="pt">Política de privacidade</span>
          <span class="lang-inline" data-lang="fr">Politique de confidentialite</span>
          <span class="lang-inline" data-lang="de">Datenschutzerklaerung</span>
          <span class="lang-inline" data-lang="nl">Privacybeleid</span>
          <span class="lang-inline" data-lang="ca">Politica de privacitat</span>
          <span class="lang-inline" data-lang="ru">Политика конфиденциальности</span>
          <span class="lang-inline" data-lang="ja">プライバシーポリシー</span>
          <span class="lang-inline" data-lang="ko">개인정보 처리방침</span>
          <span class="lang-inline" data-lang="zh">隐私政策</span>
          <span class="lang-inline" data-lang="hi">गोपनीयता नीति</span>
          <span class="lang-inline" data-lang="ar">سياسة الخصوصية</span>
          <span class="lang-inline" data-lang="he">מדיניות פרטיות</span>
        </a>
        <a href="TermsAndConditions.html">
          <span class="lang-inline" data-lang="es">Terminos y condiciones</span>
          <span class="lang-inline" data-lang="en">Terms &amp; Conditions</span>
          <span class="lang-inline" data-lang="pt">Termos e condicoes</span>
          <span class="lang-inline" data-lang="fr">Conditions d utilisation</span>
          <span class="lang-inline" data-lang="de">AGB</span>
          <span class="lang-inline" data-lang="nl">Algemene voorwaarden</span>
          <span class="lang-inline" data-lang="ca">Termes i condicions</span>
          <span class="lang-inline" data-lang="ru">Ð£ÑÐ»Ð¾Ð²Ð¸Ñ Ð¸ÑÐ¿Ð¾Ð»ÑŒÐ·Ð¾Ð²Ð°Ð½Ð¸Ñ</span>
          <span class="lang-inline" data-lang="ja">åˆ©ç”¨è¦ç´„</span>
          <span class="lang-inline" data-lang="ko">ì´ìš© ì•½ê´€</span>
          <span class="lang-inline" data-lang="zh">æ¡æ¬¾ä¸Žæ¡ä»¶</span>
          <span class="lang-inline" data-lang="hi">à¤¨à¤¿à¤¯à¤® à¤”à¤° à¤¶à¤°à¥à¤¤à¥‡à¤‚</span>
          <span class="lang-inline" data-lang="ar">Ø§Ù„Ø´Ø±ÙˆØ· ÙˆØ§Ù„Ø£Ø­ÙƒØ§Ù…</span>
          <span class="lang-inline" data-lang="he">×ª× ××™× ×•×”×’×‘×œ×•×ª</span>
        </a>
        <a class="button secondary" href="#dashboard-preview-access" data-i18n-text="join_analyst">Join as analyst</a>
      </div>
      <div class="footer-credit"><?= htmlspecialchars(index_static_text('footer_credit_prefix', $initialLang), ENT_QUOTES, 'UTF-8'); ?> <a href="https://jordiserrano.me" target="_blank" rel="noopener noreferrer">j0rd1s3rr4n0</a></div>
    </footer>
  </div>

  <script src="assets/vendor/leaflet/leaflet.js"></script>
  <script>
    (function() {
      var supported = ['ar', 'ca', 'de', 'en', 'es', 'fr', 'he', 'hi', 'it', 'ja', 'ko', 'nl', 'pt', 'ru', 'zh'];
      var languageOptionLabels = {
        ar: 'AR - العربية',
        ca: 'CA - Català',
        de: 'DE - Deutsch',
        en: 'EN - English',
        es: 'ES - Español',
        fr: 'FR - Français',
        it: 'IT - Italiano',
        he: 'HE - עברית',
        hi: 'HI - हिंदी',
        ja: 'JA - 日本語',
        ko: 'KO - 한국어',
        nl: 'NL - Nederlands',
        pt: 'PT - Português',
        ru: 'RU - Русский',
        zh: 'ZH - 中文'
      };
      function runIdle(task, timeout) {
        if (typeof window.requestIdleCallback === 'function') {
          return window.requestIdleCallback(task, { timeout: timeout || 1500 });
        }
        return window.setTimeout(task, 120);
      }
      var uiStrings = {
        es: {
          title: 'ClickFix Mitigator | Defensa contra ClickFix',
          description: 'ClickFix Mitigator: extension defense-first para frenar ClickFix. Detecta, interrumpe y registra intentos de ingenieria social basados en ejecucion de comandos.',
          language_label: 'Idioma',
          language_selector: 'Selector de idioma',
          language_select: 'Seleccionar idioma',
          brand_icon_alt: 'Icono de ClickFix',
          marketing_bar_aria: 'Acciones rapidas',
          marketing_bar_title: 'Empieza en menos de 10 minutos',
          marketing_bar_subtitle: 'Instala la extension, solicita acceso y demuestra valor real desde tu primera investigacion.',
          marketing_install: 'Instalar',
          marketing_access: 'Acceso',
          marketing_demos: 'Demos',
          join_analyst: 'Unirme como analista',
          radar_feed_live: 'Mapa global de detecciones',
          threat_stream_title: 'Detecciones recientes',
          loading_telemetry: 'Inicializando telemetria en vivo...',
          threat_alerts_24h: 'alertas/24h',
          threat_blocks_24h: 'bloqueos/24h',
          threat_domain: 'dominio',
          threat_no_data: 'monitoreo activo // esperando nuevas detecciones',
          chart_activity_title: 'Actividad 7d (alertas vs bloqueos)',
          chart_heat_title: 'Matriz de vigilancia (7d)',
          chart_activity_empty: 'Sin actividad en los ultimos 7 dias.',
          chart_heat_empty: 'Esperando telemetria para pintar la matriz.',
          chart_block_rate_7d: 'Tasa de bloqueo 7d',
          chart_heat_footnote: 'A/B = alertas vs bloqueos de los ultimos 7 dias.',
          activity_abbr_alert: 'A',
          activity_abbr_block: 'B',
          recent_unknown: 'Desconocido',
          quick_install_title: 'Instalar extension',
          quick_install_note: 'Despliegue rapido desde Chrome Web Store en entornos gestionados.',
          quick_install_button: 'Instalar extension',
          quick_access_title: 'Solicitar acceso',
          quick_access_note: 'Pide alta de analista para trabajar alertas, eventos y evidencias.',
          quick_access_button: 'Solicitar acceso',
          quick_login_title: 'Acceder a la consola',
          quick_login_note: 'Si ya tienes cuenta, entra al panel autenticado directamente.',
          quick_login_button: 'Abrir login',
          quick_demos_button: 'Ver demos',
          featured_showcase_title: 'Investigaciones publicas con contexto visual para analistas.',
          featured_showcase_intro: 'Aqui solo aparecen investigaciones marcadas por administracion como publicas y visibles en Inicio, con postura del grafo, densidad IOC, estado de evidencia y acceso directo al caso.',
          featured_case_public: 'Investigacion publica',
          featured_case_updated: 'Actualizado',
          featured_case_score_title: 'Postura del caso',
          featured_case_graph: 'Grafo',
          featured_case_iocs: 'IOCs',
          featured_case_evidence: 'Evidencia',
          featured_case_freshness: 'Recencia',
          featured_case_nodes: 'Nodos',
          featured_case_nodes_hint: 'Entidades representadas en el grafo.',
          featured_case_edges: 'Relaciones',
          featured_case_edges_hint: 'Conexiones observadas dentro del caso.',
          featured_case_ioc_total: 'IOCs',
          featured_case_tags_suffix: 'tags asociados'
        },
        en: {
          title: 'ClickFix Mitigator | Defense-first anti ClickFix',
          description: 'ClickFix Mitigator: defense-first extension to stop ClickFix. Detects, disrupts, and logs social engineering command execution attempts.',
          language_label: 'Language',
          language_selector: 'Language selector',
          language_select: 'Select language',
          brand_icon_alt: 'ClickFix icon',
          marketing_bar_aria: 'Quick actions',
          marketing_bar_title: 'Start in under 10 minutes',
          marketing_bar_subtitle: 'Install the extension, request access, and show value from your first investigation.',
          marketing_install: 'Install',
          marketing_access: 'Access',
          marketing_demos: 'Demos',
          join_analyst: 'Join as analyst',
          radar_feed_live: 'GLOBAL DETECTION MAP',
          threat_stream_title: 'Latest detections',
          loading_telemetry: 'Initializing live telemetry...',
          threat_alerts_24h: 'alerts/24h',
          threat_blocks_24h: 'blocks/24h',
          threat_domain: 'domain',
          threat_no_data: 'monitoring active // waiting for fresh detections',
          chart_activity_title: '7d activity (alerts vs blocks)',
          chart_heat_title: 'Surveillance matrix (7d)',
          chart_activity_empty: 'No activity in the last 7 days.',
          chart_heat_empty: 'Waiting for telemetry to render the matrix.',
          chart_block_rate_7d: 'Block rate 7d',
          chart_heat_footnote: 'A/B = alerts vs blocks in the last 7 days.',
          activity_abbr_alert: 'A',
          activity_abbr_block: 'B',
          recent_unknown: 'Unknown',
          quick_install_title: 'Install extension',
          quick_install_note: 'Deploy quickly from Chrome Web Store in managed environments.',
          quick_install_button: 'Install extension',
          quick_access_title: 'Request access',
          quick_access_note: 'Request analyst onboarding to work alerts, events, and evidence.',
          quick_access_button: 'Request access',
          quick_login_title: 'Open console login',
          quick_login_note: 'If you already have an account, go straight to the authenticated console.',
          quick_login_button: 'Open login',
          quick_demos_button: 'View demos',
          featured_showcase_title: 'Public investigations with analyst-ready visual context.',
          featured_showcase_intro: 'Only investigations explicitly marked by administrators as public and visible on the homepage are shown here, with graph posture, IOC density, evidence status, and direct access to the case.',
          featured_case_public: 'Public investigation',
          featured_case_updated: 'Updated',
          featured_case_score_title: 'Case posture',
          featured_case_graph: 'Graph',
          featured_case_iocs: 'IOCs',
          featured_case_evidence: 'Evidence',
          featured_case_freshness: 'Freshness',
          featured_case_nodes: 'Nodes',
          featured_case_nodes_hint: 'Entities represented in the graph.',
          featured_case_edges: 'Edges',
          featured_case_edges_hint: 'Observed relations across the case.',
          featured_case_ioc_total: 'IOCs',
          featured_case_tags_suffix: 'tags attached'
        },
        pt: {
          title: 'ClickFix Mitigator | Defesa contra ClickFix',
          description: 'ClickFix Mitigator: extensao defense-first para travar ClickFix. Detecta, interrompe e registra tentativas de engenharia social com comandos.',
          language_label: 'Idioma',
          language_selector: 'Seletor de idioma',
          language_select: 'Selecionar idioma',
          brand_icon_alt: 'Icone do ClickFix'
        },
        fr: {
          title: 'ClickFix Mitigator | Defense contre ClickFix',
          description: 'ClickFix Mitigator : extension defense-first pour stopper ClickFix. Detecte, bloque et journalise les tentatives de social engineering par commandes.',
          language_label: 'Langue',
          language_selector: 'Selecteur de langue',
          language_select: 'Selectionner la langue',
          brand_icon_alt: 'Icone ClickFix'
        },
        de: {
          title: 'ClickFix Mitigator | Abwehr gegen ClickFix',
          description: 'ClickFix Mitigator: Defense-first Erweiterung gegen ClickFix. Erkennt, stoppt und protokolliert Social-Engineering-Kommandos.',
          language_label: 'Sprache',
          language_selector: 'Sprachauswahl',
          language_select: 'Sprache waehlen',
          brand_icon_alt: 'ClickFix-Icon'
        },
        nl: {
          title: 'ClickFix Mitigator | Verdediging tegen ClickFix',
          description: 'ClickFix Mitigator: defense-first extensie om ClickFix te stoppen. Detecteert, onderbreekt en logt social engineering-commando s.',
          language_label: 'Taal',
          language_selector: 'Taalkeuze',
          language_select: 'Taal selecteren',
          brand_icon_alt: 'ClickFix pictogram'
        },
        ca: {
          title: 'ClickFix Mitigator | Defensa contra ClickFix',
          description: 'ClickFix Mitigator: extensio defense-first per frenar ClickFix. Detecta, interromp i registra intents d enginyeria social amb comandes.',
          language_label: 'Idioma',
          language_selector: 'Selector d idioma',
          language_select: 'Seleccionar idioma',
          brand_icon_alt: 'Icona de ClickFix'
        },
        ru: {
          title: 'ClickFix Mitigator | Защита от ClickFix',
          description: 'ClickFix Mitigator: defense-first расширение для остановки ClickFix. Обнаруживает, прерывает и фиксирует попытки социнженерии через команды.',
          language_label: 'Язык',
          language_selector: 'Выбор языка',
          language_select: 'Выбрать язык',
          brand_icon_alt: 'Иконка ClickFix'
        },
        ja: {
          title: 'ClickFix Mitigator | ClickFix対策',
          description: 'ClickFix Mitigator: ClickFixを止める防御優先の拡張。検知し、遮断し、コマンド型の詐欺を記録します。',
          language_label: '言語',
          language_selector: '言語セレクター',
          language_select: '言語を選択',
          brand_icon_alt: 'ClickFixアイコン'
        },
        ko: {
          title: 'ClickFix Mitigator | ClickFix 방어',
          description: 'ClickFix Mitigator: ClickFix를 차단하는 방어 우선 확장. 탐지, 차단, 기록을 수행합니다.',
          language_label: '언어',
          language_selector: '언어 선택기',
          language_select: '언어 선택',
          brand_icon_alt: 'ClickFix 아이콘'
        },
        zh: {
          title: 'ClickFix Mitigator | ClickFix 防护',
          description: 'ClickFix Mitigator：防御优先的扩展，用于拦截 ClickFix。检测、阻断并记录命令型社会工程尝试。',
          language_label: '语言',
          language_selector: '语言选择器',
          language_select: '选择语言',
          brand_icon_alt: 'ClickFix 图标'
        },
        hi: {
          title: 'ClickFix Mitigator | ClickFix रक्षा',
          description: 'ClickFix Mitigator: रक्षा-प्रधान एक्सटेंशन जो ClickFix रोकता है। पहचानता, बाधित करता और कमांड आधारित सोशल इंजीनियरिंग लॉग करता है।',
          language_label: 'भाषा',
          language_selector: 'भाषा चयन',
          language_select: 'भाषा चुनें',
          brand_icon_alt: 'ClickFix आइकन'
        },
        ar: {
          title: 'ClickFix Mitigator | دفاع ضد ClickFix',
          description: 'ClickFix Mitigator: امتداد دفاع اولا لايقاف ClickFix. يكتشف ويعطل ويسجل محاولات الهندسة الاجتماعية عبر الاوامر.',
          language_label: 'اللغة',
          language_selector: 'محدد اللغة',
          language_select: 'اختر اللغة',
          brand_icon_alt: 'ايقونة ClickFix'
        },
        he: {
          title: 'ClickFix Mitigator | הגנה נגד ClickFix',
          description: 'ClickFix Mitigator: תוסף הגנה תחילה לעצירת ClickFix. מזהה, חוסם ומתעד נסיונות הנדסה חברתית עם פקודות.',
          language_label: 'שפה',
          language_selector: 'בורר שפה',
          language_select: 'בחר שפה',
          brand_icon_alt: 'סמל ClickFix'
        }
      };
      var uiStringEnhancements = {
        es: {
          company_website_label: 'Sitio web de la empresa',
          linkedin_label: 'LinkedIn profesional',
          marketing_close: 'Cerrar',
          public_evidence_title: 'Evidencia ClickFix (Before server snapshot / After extension alert)',
          public_evidence_workflow_title: 'Flujo de evidencia publica',
          public_evidence_workflow_body: 'Todavia no hay evidencia publica disponible. Las capturas solo aparecen aqui despues de ser capturadas, revisadas y aprobadas por un administrador.',
          evidence_before: 'Before',
          evidence_after: 'After',
          evidence_before_pending: 'La captura before aun no esta aprobada publicamente para este caso.',
          evidence_after_pending: 'La captura after aun no esta aprobada publicamente para este caso.',
          evidence_before_alt: 'Evidencia before de ClickFix',
          evidence_after_alt: 'Evidencia after de ClickFix',
          featured_investigations_title: 'Investigaciones destacadas',
          featured_investigations_empty: 'Todavia no hay investigaciones publicas destacadas disponibles.',
          featured_spotlight_empty: 'Resumen del caso pendiente de publicacion.',
          featured_summary_empty: 'Todavia no hay resumen publico disponible.',
          featured_before_missing: 'No hay captura before aprobada vinculada.',
          featured_after_missing: 'No hay captura after aprobada vinculada.',
          featured_before_alt: 'Evidencia before destacada',
          featured_after_alt: 'Evidencia after destacada',
          featured_open_investigation: 'Abrir investigacion',
          featured_request_access: 'Solicitar acceso de analista',
          demo_catalog_button: 'Catalogo de demos',
          demo_attacker_button: 'Demo: muestra atacante',
          demo_iframed_button: 'Demo: flujo con iframe',
          internal_ads_badge: 'Patrocinado',
          internal_ads_title: 'Espacios patrocinados para analistas y despliegues',
          internal_ads_subtitle: 'Mensajes no intrusivos para audiencias publicas, junior y mid. Los perfiles senior y admin no ven estos bloques.',
          internal_ads_test_prefix: 'anuncio de prueba',
          internal_ads_slot_fallback: 'Espacio patrocinado',
          internal_ads_open_default: 'Abrir',
          support_badge: 'Soporte',
          support_title_public: 'Apoya ClickFix Mitigator',
          support_sub_public: 'Si te aporta valor, puedes apoyar el proyecto con donaciones o patrocinio. La monetizacion es opcional y transparente.',
          support_donations_title: 'Donaciones',
          support_donations_sub: 'Ayuda a financiar mantenimiento, nuevas reglas de deteccion y mejoras para la extension.',
          support_sponsor_title: 'Quieres ser sponsor?',
          support_sponsor_sub: 'Patrocina el proyecto para tener presencia visible en la web publica, apoyar la investigacion y financiar mejoras defensivas sin publicidad intrusiva.',
          support_sponsor_cta: 'Quiero patrocinar',
          support_sponsor_repo: 'Revisar el proyecto',
          support_contact_sponsor: 'Contactar para patrocinar',
          support_ads_title_public: 'Patrocinio / anuncios',
          support_ads_enabled_sub: 'Zona para patrocinadores o anuncios no intrusivos. Se activa solo cuando configuras el proveedor de anuncios en el entorno.',
          support_ads_hidden_sub: 'Este espacio publicitario no se muestra a perfiles senior o admin. Se reserva para audiencias publicas, junior y mid.',
          support_ads_available: 'Espacio de patrocinio disponible',
          support_ads_hidden_profile: 'Bloque publicitario oculto para este perfil',
          footer_github: 'Repositorio en GitHub'
        },
        en: {
          company_website_label: 'Company Website',
          linkedin_label: 'Professional LinkedIn',
          marketing_close: 'Close',
          public_evidence_title: 'ClickFix evidence (Before server snapshot / After extension alert)',
          public_evidence_workflow_title: 'Public evidence workflow',
          public_evidence_workflow_body: 'There is no public evidence available yet. Screenshots only appear here after they are captured, reviewed, and approved by an administrator.',
          evidence_before: 'Before',
          evidence_after: 'After',
          evidence_before_pending: 'Before is not publicly approved yet for this case.',
          evidence_after_pending: 'After is not publicly approved yet for this case.',
          evidence_before_alt: 'ClickFix before evidence',
          evidence_after_alt: 'ClickFix after evidence',
          featured_investigations_title: 'Featured investigations',
          featured_investigations_empty: 'No public featured investigations are available yet.',
          featured_spotlight_empty: 'Case summary pending publication.',
          featured_summary_empty: 'No public summary available yet.',
          featured_before_missing: 'No approved before capture linked.',
          featured_after_missing: 'No approved after capture linked.',
          featured_before_alt: 'Featured before evidence',
          featured_after_alt: 'Featured after evidence',
          featured_open_investigation: 'Open investigation',
          featured_request_access: 'Request analyst access',
          demo_catalog_button: 'Demo catalog',
          demo_attacker_button: 'Demo: attacker sample',
          demo_iframed_button: 'Demo: iframed flow',
          internal_ads_badge: 'Sponsored',
          internal_ads_title: 'Sponsored slots for analysts and deployments',
          internal_ads_subtitle: 'Non-intrusive messages for public, junior, and mid audiences. Senior and admin profiles do not see these blocks.',
          internal_ads_test_prefix: 'test ad',
          internal_ads_slot_fallback: 'Sponsored slot',
          internal_ads_open_default: 'Open',
          support_badge: 'Support',
          support_title_public: 'Support ClickFix Mitigator',
          support_sub_public: 'If the project delivers value, you can support it with donations or sponsorship. Monetization is optional and transparent.',
          support_donations_title: 'Donations',
          support_donations_sub: 'Help fund maintenance, new detection rules, and improvements for the extension.',
          support_sponsor_title: 'Want to sponsor?',
          support_sponsor_sub: 'Sponsor the project to gain visible placement on the public site, support research, and help fund defensive improvements without intrusive advertising.',
          support_sponsor_cta: 'Become a sponsor',
          support_sponsor_repo: 'Review the project',
          support_contact_sponsor: 'Contact for sponsorship',
          support_ads_title_public: 'Sponsorship / ads',
          support_ads_enabled_sub: 'Area for sponsors or non-intrusive ads. It is only enabled when you configure the ad provider in your environment.',
          support_ads_hidden_sub: 'This ad space is hidden for senior or admin profiles. It is reserved for public, junior, and mid audiences.',
          support_ads_available: 'Sponsorship space available',
          support_ads_hidden_profile: 'Ad slot hidden for this profile',
          footer_github: 'GitHub Repository'
        },
        pt: {
          company_website_label: 'Site da empresa',
          linkedin_label: 'LinkedIn profissional',
          marketing_close: 'Fechar',
          public_evidence_title: 'Evidencia ClickFix (Before server snapshot / After extension alert)',
          public_evidence_workflow_title: 'Fluxo de evidencia publica',
          public_evidence_workflow_body: 'Ainda nao ha evidencia publica disponivel. As capturas so aparecem aqui apos serem capturadas, revistas e aprovadas por um administrador.',
          evidence_before: 'Before',
          evidence_after: 'After',
          evidence_before_pending: 'A captura before ainda nao esta aprovada publicamente para este caso.',
          evidence_after_pending: 'A captura after ainda nao esta aprovada publicamente para este caso.',
          featured_investigations_title: 'Investigacoes em destaque',
          featured_investigations_empty: 'Ainda nao ha investigacoes publicas em destaque disponiveis.',
          featured_spotlight_empty: 'Resumo do caso pendente de publicacao.',
          featured_summary_empty: 'Ainda nao ha resumo publico disponivel.',
          featured_before_missing: 'Nenhuma captura before aprovada vinculada.',
          featured_after_missing: 'Nenhuma captura after aprovada vinculada.',
          featured_open_investigation: 'Abrir investigacao',
          featured_request_access: 'Solicitar acesso de analista',
          demo_catalog_button: 'Catalogo de demos',
          demo_attacker_button: 'Demo: amostra de atacante',
          demo_iframed_button: 'Demo: fluxo com iframe',
          internal_ads_badge: 'Patrocinado',
          internal_ads_title: 'Espacos patrocinados para analistas e implantacoes',
          internal_ads_subtitle: 'Mensagens nao intrusivas para publico, junior e mid. Perfis senior e admin nao veem estes blocos.',
          internal_ads_test_prefix: 'anuncio de teste',
          internal_ads_slot_fallback: 'Espaco patrocinado',
          internal_ads_open_default: 'Abrir',
          support_badge: 'Suporte',
          support_title_public: 'Apoie o ClickFix Mitigator',
          support_sub_public: 'Se o projeto traz valor, voce pode apoiar com doacoes ou patrocinio. A monetizacao e opcional e transparente.',
          support_donations_title: 'Doacoes',
          support_donations_sub: 'Ajude a financiar manutencao, novas regras de deteccao e melhorias da extensao.',
          support_sponsor_title: 'Quer ser sponsor?',
          support_sponsor_sub: 'Patrocine o projeto para ter presenca visivel no site publico, apoiar a investigacao e financiar melhorias defensivas sem publicidade intrusiva.',
          support_sponsor_cta: 'Quero patrocinar',
          support_sponsor_repo: 'Rever o projeto',
          support_contact_sponsor: 'Contactar para patrocinio',
          support_ads_title_public: 'Patrocinio / anuncios',
          support_ads_enabled_sub: 'Area para patrocinadores ou anuncios nao intrusivos. So e ativada quando configura o provedor de anuncios no ambiente.',
          support_ads_hidden_sub: 'Este espaco publicitario nao e mostrado a perfis senior ou admin. E reservado para publico, junior e mid.',
          support_ads_available: 'Espaco de patrocinio disponivel',
          support_ads_hidden_profile: 'Bloco publicitario oculto para este perfil',
          footer_github: 'Repositorio GitHub'
        },
        fr: {
          company_website_label: 'Site web de l entreprise',
          linkedin_label: 'LinkedIn professionnel',
          marketing_close: 'Fermer',
          public_evidence_title: 'Preuves ClickFix (Before server snapshot / After extension alert)',
          public_evidence_workflow_title: 'Flux de preuves publiques',
          public_evidence_workflow_body: 'Aucune preuve publique n est encore disponible. Les captures n apparaissent ici qu apres capture, revue et approbation par un administrateur.',
          evidence_before: 'Before',
          evidence_after: 'After',
          evidence_before_pending: 'La capture before n est pas encore approuvee publiquement pour ce cas.',
          evidence_after_pending: 'La capture after n est pas encore approuvee publiquement pour ce cas.',
          featured_investigations_title: 'Investigations en vedette',
          featured_investigations_empty: 'Aucune investigation publique en vedette n est disponible pour le moment.',
          featured_spotlight_empty: 'Resume du cas en attente de publication.',
          featured_summary_empty: 'Aucun resume public disponible pour le moment.',
          featured_before_missing: 'Aucune capture before approuvee liee.',
          featured_after_missing: 'Aucune capture after approuvee liee.',
          featured_open_investigation: 'Ouvrir l investigation',
          featured_request_access: 'Demander un acces analyste',
          demo_catalog_button: 'Catalogue des demos',
          demo_attacker_button: 'Demo : echantillon attaquant',
          demo_iframed_button: 'Demo : flux iframe',
          internal_ads_badge: 'Sponsorise',
          internal_ads_title: 'Espaces sponsorises pour analystes et deploiements',
          internal_ads_subtitle: 'Messages non intrusifs pour les audiences publiques, junior et mid. Les profils senior et admin ne voient pas ces blocs.',
          internal_ads_test_prefix: 'annonce test',
          internal_ads_slot_fallback: 'Emplacement sponsorise',
          internal_ads_open_default: 'Ouvrir',
          support_badge: 'Support',
          support_title_public: 'Soutenir ClickFix Mitigator',
          support_sub_public: 'Si le projet vous apporte de la valeur, vous pouvez le soutenir par don ou sponsoring. La monetisation est optionnelle et transparente.',
          support_donations_title: 'Dons',
          support_donations_sub: 'Aidez a financer la maintenance, de nouvelles regles de detection et les ameliorations de l extension.',
          support_sponsor_title: 'Vous voulez sponsoriser ?',
          support_sponsor_sub: 'Sponsorisez le projet pour beneficier d une presence visible sur le site public, soutenir la recherche et financer des ameliorations defensives sans publicite intrusive.',
          support_sponsor_cta: 'Devenir sponsor',
          support_sponsor_repo: 'Voir le projet',
          support_contact_sponsor: 'Contacter pour sponsoriser',
          support_ads_title_public: 'Sponsoring / annonces',
          support_ads_enabled_sub: 'Zone pour sponsors ou annonces non intrusives. Elle ne s active que lorsque le fournisseur d annonces est configure.',
          support_ads_hidden_sub: 'Cet espace publicitaire est masque pour les profils senior ou admin. Il est reserve aux audiences publiques, junior et mid.',
          support_ads_available: 'Espace sponsor disponible',
          support_ads_hidden_profile: 'Bloc publicitaire masque pour ce profil',
          footer_github: 'Depot GitHub'
        },
        de: {
          company_website_label: 'Unternehmenswebsite',
          linkedin_label: 'Berufliches LinkedIn',
          marketing_close: 'Schliessen',
          public_evidence_title: 'ClickFix-Beweise (Before server snapshot / After extension alert)',
          public_evidence_workflow_title: 'Oeffentlicher Beweisablauf',
          public_evidence_workflow_body: 'Derzeit sind noch keine oeffentlichen Beweise verfuegbar. Screenshots erscheinen hier erst nach Erfassung, Pruefung und Freigabe durch einen Administrator.',
          evidence_before: 'Before',
          evidence_after: 'After',
          evidence_before_pending: 'Die Before-Aufnahme ist fuer diesen Fall noch nicht oeffentlich freigegeben.',
          evidence_after_pending: 'Die After-Aufnahme ist fuer diesen Fall noch nicht oeffentlich freigegeben.',
          featured_investigations_title: 'Ausgewaehlte Untersuchungen',
          featured_investigations_empty: 'Derzeit sind noch keine oeffentlichen Untersuchungen verfuegbar.',
          featured_spotlight_empty: 'Fallzusammenfassung wartet auf Freigabe.',
          featured_summary_empty: 'Derzeit ist noch keine oeffentliche Zusammenfassung verfuegbar.',
          featured_before_missing: 'Keine freigegebene Before-Aufnahme verknuepft.',
          featured_after_missing: 'Keine freigegebene After-Aufnahme verknuepft.',
          featured_open_investigation: 'Untersuchung oeffnen',
          featured_request_access: 'Analystenzugang anfragen',
          demo_catalog_button: 'Demo-Katalog',
          demo_attacker_button: 'Demo: Angreiferbeispiel',
          demo_iframed_button: 'Demo: Iframe-Ablauf',
          internal_ads_badge: 'Gesponsert',
          internal_ads_title: 'Gesponserte Flaechen fuer Analysten und Rollouts',
          internal_ads_subtitle: 'Nicht aufdringliche Hinweise fuer oeffentliche, Junior- und Mid-Zielgruppen. Senior- und Admin-Profile sehen diese Bloecke nicht.',
          internal_ads_test_prefix: 'testanzeige',
          internal_ads_slot_fallback: 'Gesponserter Slot',
          internal_ads_open_default: 'Oeffnen',
          support_badge: 'Support',
          support_title_public: 'ClickFix Mitigator unterstuetzen',
          support_sub_public: 'Wenn das Projekt Mehrwert liefert, kannst du es mit Spenden oder Sponsoring unterstuetzen. Monetarisierung ist optional und transparent.',
          support_donations_title: 'Spenden',
          support_donations_sub: 'Hilf dabei, Wartung, neue Erkennungsregeln und Verbesserungen der Erweiterung zu finanzieren.',
          support_sponsor_title: 'Sponsor werden?',
          support_sponsor_sub: 'Sponsere das Projekt fuer sichtbare Praesenz auf der oeffentlichen Seite, zur Unterstuetzung der Forschung und zur Finanzierung defensiver Verbesserungen ohne aufdringliche Werbung.',
          support_sponsor_cta: 'Sponsor werden',
          support_sponsor_repo: 'Projekt ansehen',
          support_contact_sponsor: 'Fuer Sponsoring kontaktieren',
          support_ads_title_public: 'Sponsoring / Anzeigen',
          support_ads_enabled_sub: 'Bereich fuer Sponsoren oder nicht aufdringliche Anzeigen. Er wird nur aktiviert, wenn der Anzeigenanbieter in deiner Umgebung konfiguriert ist.',
          support_ads_hidden_sub: 'Dieser Anzeigenbereich ist fuer Senior- oder Admin-Profile ausgeblendet. Er ist fuer oeffentliche, Junior- und Mid-Zielgruppen vorgesehen.',
          support_ads_available: 'Sponsoring-Flaeche verfuegbar',
          support_ads_hidden_profile: 'Anzeigenblock fuer dieses Profil ausgeblendet',
          footer_github: 'GitHub-Repository'
        },
        nl: {
          company_website_label: 'Bedrijfswebsite',
          linkedin_label: 'Professionele LinkedIn',
          marketing_close: 'Sluiten',
          public_evidence_title: 'ClickFix-bewijs (Before server snapshot / After extension alert)',
          public_evidence_workflow_title: 'Publieke bewijsworkflow',
          public_evidence_workflow_body: 'Er is nog geen publiek bewijs beschikbaar. Screenshots verschijnen hier pas nadat ze zijn vastgelegd, beoordeeld en goedgekeurd door een beheerder.',
          evidence_before: 'Before',
          evidence_after: 'After',
          evidence_before_pending: 'De before-capture is nog niet publiek goedgekeurd voor deze case.',
          evidence_after_pending: 'De after-capture is nog niet publiek goedgekeurd voor deze case.',
          featured_investigations_title: 'Uitgelichte onderzoeken',
          featured_investigations_empty: 'Er zijn nog geen publieke uitgelichte onderzoeken beschikbaar.',
          featured_spotlight_empty: 'Casesamenvatting wacht op publicatie.',
          featured_summary_empty: 'Nog geen publieke samenvatting beschikbaar.',
          featured_before_missing: 'Geen goedgekeurde before-capture gekoppeld.',
          featured_after_missing: 'Geen goedgekeurde after-capture gekoppeld.',
          featured_open_investigation: 'Onderzoek openen',
          featured_request_access: 'Analisttoegang aanvragen',
          demo_catalog_button: 'Demo-catalogus',
          demo_attacker_button: 'Demo: aanvallerstaal',
          demo_iframed_button: 'Demo: iframe-flow',
          internal_ads_badge: 'Gesponsord',
          internal_ads_title: 'Gesponsorde slots voor analisten en uitrol',
          internal_ads_subtitle: 'Niet-opdringerige berichten voor publieke, junior- en mid-doelgroepen. Senior- en adminprofielen zien deze blokken niet.',
          internal_ads_test_prefix: 'testadvertentie',
          internal_ads_slot_fallback: 'Gesponsorde plek',
          internal_ads_open_default: 'Openen',
          support_badge: 'Support',
          support_title_public: 'Ondersteun ClickFix Mitigator',
          support_sub_public: 'Als het project waarde levert, kun je het steunen met donaties of sponsoring. Monetisatie is optioneel en transparant.',
          support_donations_title: 'Donaties',
          support_donations_sub: 'Help onderhoud, nieuwe detectieregels en verbeteringen voor de extensie financieren.',
          support_sponsor_title: 'Sponsor worden?',
          support_sponsor_sub: 'Sponsor het project voor zichtbare plaatsing op de publieke site, steun het onderzoek en help defensieve verbeteringen financieren zonder opdringerige advertenties.',
          support_sponsor_cta: 'Ik wil sponsoren',
          support_sponsor_repo: 'Project bekijken',
          support_contact_sponsor: 'Contact voor sponsoring',
          support_ads_title_public: 'Sponsoring / advertenties',
          support_ads_enabled_sub: 'Ruimte voor sponsors of niet-opdringerige advertenties. Deze wordt alleen geactiveerd wanneer de advertentieprovider is ingesteld.',
          support_ads_hidden_sub: 'Deze advertentieruimte is verborgen voor senior- of adminprofielen. Ze is bedoeld voor publieke, junior- en mid-doelgroepen.',
          support_ads_available: 'Sponsoringruimte beschikbaar',
          support_ads_hidden_profile: 'Advertentieblok verborgen voor dit profiel',
          footer_github: 'GitHub-repository'
        },
        ca: {
          company_website_label: 'Web de l empresa',
          linkedin_label: 'LinkedIn professional',
          marketing_close: 'Tancar',
          public_evidence_title: 'Evidencia ClickFix (Before server snapshot / After extension alert)',
          public_evidence_workflow_title: 'Flux d evidencia publica',
          public_evidence_workflow_body: 'Encara no hi ha evidencia publica disponible. Les captures nomes apareixen aqui despres de ser capturades, revisades i aprovades per un administrador.',
          evidence_before: 'Before',
          evidence_after: 'After',
          evidence_before_pending: 'La captura before encara no esta aprovada publicament per a aquest cas.',
          evidence_after_pending: 'La captura after encara no esta aprovada publicament per a aquest cas.',
          featured_investigations_title: 'Investigacions destacades',
          featured_investigations_empty: 'Encara no hi ha investigacions publiques destacades disponibles.',
          featured_spotlight_empty: 'Resum del cas pendent de publicacio.',
          featured_summary_empty: 'Encara no hi ha resum public disponible.',
          featured_before_missing: 'No hi ha captura before aprovada vinculada.',
          featured_after_missing: 'No hi ha captura after aprovada vinculada.',
          featured_open_investigation: 'Obrir investigacio',
          featured_request_access: 'Demanar acces d analista',
          demo_catalog_button: 'Cataleg de demos',
          demo_attacker_button: 'Demo: mostra atacant',
          demo_iframed_button: 'Demo: flux amb iframe',
          internal_ads_badge: 'Patrocinat',
          internal_ads_title: 'Espais patrocinats per a analistes i desplegaments',
          internal_ads_subtitle: 'Missatges no intrusius per a audiencies publiques, junior i mid. Els perfils senior i admin no veuen aquests blocs.',
          internal_ads_test_prefix: 'anunci de prova',
          internal_ads_slot_fallback: 'Espai patrocinat',
          internal_ads_open_default: 'Obrir',
          support_badge: 'Suport',
          support_title_public: 'Dona suport a ClickFix Mitigator',
          support_sub_public: 'Si et aporta valor, pots donar suport al projecte amb donacions o patroci. La monetitzacio es opcional i transparent.',
          support_donations_title: 'Donacions',
          support_donations_sub: 'Ajuda a finançar manteniment, noves regles de deteccio i millores per a l extensio.',
          support_sponsor_title: 'Vols ser sponsor?',
          support_sponsor_sub: 'Patrocina el projecte per tenir presencia visible al web public, donar suport a la investigacio i finançar millores defensives sense publicitat intrusiva.',
          support_sponsor_cta: 'Vull patrocinar',
          support_sponsor_repo: 'Revisar el projecte',
          support_contact_sponsor: 'Contactar per patrocini',
          support_ads_title_public: 'Patrocini / anuncis',
          support_ads_enabled_sub: 'Zona per a patrocinadors o anuncis no intrusius. Nomes s activa quan configures el proveidor d anuncis a l entorn.',
          support_ads_hidden_sub: 'Aquest espai publicitari no es mostra a perfils senior o admin. Es reserva per a audiencies publiques, junior i mid.',
          support_ads_available: 'Espai de patrocini disponible',
          support_ads_hidden_profile: 'Bloc publicitari ocult per a aquest perfil',
          footer_github: 'Repositori a GitHub'
        }
      };
      Object.keys(uiStringEnhancements).forEach(function(langKey) {
        uiStrings[langKey] = Object.assign({}, uiStrings[langKey] || {}, uiStringEnhancements[langKey]);
      });
      var uiStringCoverage = {
        pt: {
          marketing_bar_aria: 'Acoes rapidas',
          marketing_bar_title: 'Comece em menos de 10 minutos',
          marketing_bar_subtitle: 'Instale a extensao, solicite acesso e mostre valor ja na primeira investigacao.',
          marketing_install: 'Instalar',
          marketing_access: 'Acesso',
          marketing_demos: 'Demos',
          join_analyst: 'Entrar como analista',
          radar_feed_live: 'Mapa global de deteccoes',
          threat_stream_title: 'Deteccoes recentes',
          loading_telemetry: 'Inicializando telemetria ao vivo...',
          threat_alerts_24h: 'alertas/24h',
          threat_blocks_24h: 'bloqueios/24h',
          threat_domain: 'dominio',
          threat_no_data: 'monitoramento ativo // aguardando novas deteccoes',
          chart_activity_title: 'Atividade 7d (alertas vs bloqueios)',
          chart_heat_title: 'Matriz de monitoramento (7d)',
          chart_activity_empty: 'Sem atividade nos ultimos 7 dias.',
          chart_heat_empty: 'Aguardando telemetria para renderizar a matriz.',
          chart_block_rate_7d: 'Taxa de bloqueio 7d',
          chart_heat_footnote: 'A/B = alertas vs bloqueios dos ultimos 7 dias.',
          activity_abbr_alert: 'A',
          activity_abbr_block: 'B',
          recent_unknown: 'Desconhecido',
          quick_install_title: 'Instalar extensao',
          quick_install_note: 'Implantacao rapida a partir da Chrome Web Store em ambientes geridos.',
          quick_install_button: 'Instalar extensao',
          quick_access_title: 'Solicitar acesso',
          quick_access_note: 'Solicite onboarding de analista para trabalhar alertas, eventos e evidencias.',
          quick_access_button: 'Solicitar acesso',
          quick_login_title: 'Abrir login da consola',
          quick_login_note: 'Se ja tem conta, entre diretamente no painel autenticado.',
          quick_login_button: 'Abrir login',
          quick_demos_button: 'Ver demos',
          featured_showcase_title: 'Investigacoes publicas com contexto visual pronto para analistas.',
          featured_showcase_intro: 'Aqui so aparecem investigacoes marcadas pela administracao como publicas e visiveis na pagina inicial, com postura do grafo, densidade IOC, estado da evidencia e acesso direto ao caso.',
          featured_case_public: 'Investigacao publica',
          featured_case_updated: 'Atualizado',
          featured_case_score_title: 'Postura do caso',
          featured_case_graph: 'Grafo',
          featured_case_iocs: 'IOCs',
          featured_case_evidence: 'Evidencia',
          featured_case_freshness: 'Recencia',
          featured_case_nodes: 'Nos',
          featured_case_nodes_hint: 'Entidades representadas no grafo.',
          featured_case_edges: 'Relacoes',
          featured_case_edges_hint: 'Ligacoes observadas no caso.',
          featured_case_ioc_total: 'IOCs',
          featured_case_tags_suffix: 'tags associadas',
          public_evidence_title: 'Evidencia ClickFix (captura previa del servidor / captura posterior de la extensao)',
          evidence_before: 'Antes',
          evidence_after: 'Depois',
          evidence_before_pending: 'A captura anterior ainda nao esta aprovada publicamente para este caso.',
          evidence_after_pending: 'A captura posterior ainda nao esta aprovada publicamente para este caso.'
        },
        fr: {
          marketing_bar_aria: 'Actions rapides',
          marketing_bar_title: 'Commencez en moins de 10 minutes',
          marketing_bar_subtitle: 'Installez l extension, demandez l acces et montrez de la valeur des la premiere investigation.',
          marketing_install: 'Installer',
          marketing_access: 'Acces',
          marketing_demos: 'Demos',
          join_analyst: 'Rejoindre comme analyste',
          radar_feed_live: 'Carte globale des detections',
          threat_stream_title: 'Detections recentes',
          loading_telemetry: 'Initialisation de la telemetrie temps reel...',
          threat_alerts_24h: 'alertes/24h',
          threat_blocks_24h: 'blocages/24h',
          threat_domain: 'domaine',
          threat_no_data: 'monitoring actif // en attente de nouvelles detections',
          chart_activity_title: 'Activite 7j (alertes vs blocages)',
          chart_heat_title: 'Matrice de monitoring (7j)',
          chart_activity_empty: 'Aucune activite sur les 7 derniers jours.',
          chart_heat_empty: 'En attente de telemetrie pour afficher la matrice.',
          chart_block_rate_7d: 'Taux de blocage 7j',
          chart_heat_footnote: 'A/B = alertes vs blocages des 7 derniers jours.',
          activity_abbr_alert: 'A',
          activity_abbr_block: 'B',
          recent_unknown: 'Inconnu',
          quick_install_title: 'Installer l extension',
          quick_install_note: 'Deploiement rapide depuis le Chrome Web Store dans les environnements geres.',
          quick_install_button: 'Installer l extension',
          quick_access_title: 'Demander l acces',
          quick_access_note: 'Demandez l onboarding analyste pour travailler les alertes, evenements et preuves.',
          quick_access_button: 'Demander l acces',
          quick_login_title: 'Ouvrir le login console',
          quick_login_note: 'Si vous avez deja un compte, ouvrez directement la console authentifiee.',
          quick_login_button: 'Ouvrir le login',
          quick_demos_button: 'Voir les demos',
          featured_showcase_title: 'Investigations publiques avec contexte visuel pret pour les analystes.',
          featured_showcase_intro: 'Seules les investigations marquees par les administrateurs comme publiques et visibles sur l accueil apparaissent ici, avec posture du graphe, densite IOC, etat des preuves et acces direct au cas.',
          featured_case_public: 'Investigation publique',
          featured_case_updated: 'Mis a jour',
          featured_case_score_title: 'Posture du cas',
          featured_case_graph: 'Graphe',
          featured_case_iocs: 'IOCs',
          featured_case_evidence: 'Preuves',
          featured_case_freshness: 'Recence',
          featured_case_nodes: 'Noeuds',
          featured_case_nodes_hint: 'Entites representees dans le graphe.',
          featured_case_edges: 'Relations',
          featured_case_edges_hint: 'Connexions observees dans le cas.',
          featured_case_ioc_total: 'IOCs',
          featured_case_tags_suffix: 'tags associes',
          public_evidence_title: 'Preuves ClickFix (capture avant serveur / capture apres extension)',
          evidence_before: 'Avant',
          evidence_after: 'Apres',
          evidence_before_pending: 'La capture avant n est pas encore approuvee publiquement pour ce cas.',
          evidence_after_pending: 'La capture apres n est pas encore approuvee publiquement pour ce cas.'
        },
        de: {
          marketing_bar_aria: 'Schnellaktionen',
          marketing_bar_title: 'Starte in weniger als 10 Minuten',
          marketing_bar_subtitle: 'Installiere die Erweiterung, fordere Zugriff an und zeige schon in der ersten Untersuchung echten Mehrwert.',
          marketing_install: 'Installieren',
          marketing_access: 'Zugang',
          marketing_demos: 'Demos',
          join_analyst: 'Als Analyst beitreten',
          radar_feed_live: 'Globale Detektionskarte',
          threat_stream_title: 'Neueste Detektionen',
          loading_telemetry: 'Live-Telemetrie wird initialisiert...',
          threat_alerts_24h: 'alarme/24h',
          threat_blocks_24h: 'blockierungen/24h',
          threat_domain: 'domain',
          threat_no_data: 'monitoring aktiv // warte auf neue detektionen',
          chart_activity_title: '7d Aktivitat (Alarme vs Blockierungen)',
          chart_heat_title: 'Monitoring-Matrix (7d)',
          chart_activity_empty: 'Keine Aktivitat in den letzten 7 Tagen.',
          chart_heat_empty: 'Warte auf Telemetrie, um die Matrix darzustellen.',
          chart_block_rate_7d: 'Blockierungsrate 7d',
          chart_heat_footnote: 'A/B = Alarme vs Blockierungen der letzten 7 Tage.',
          activity_abbr_alert: 'A',
          activity_abbr_block: 'B',
          recent_unknown: 'Unbekannt',
          quick_install_title: 'Erweiterung installieren',
          quick_install_note: 'Schnelle Bereitstellung ueber den Chrome Web Store in verwalteten Umgebungen.',
          quick_install_button: 'Erweiterung installieren',
          quick_access_title: 'Zugang anfragen',
          quick_access_note: 'Fordere Analysten-Onboarding an, um mit Alarmen, Events und Beweisen zu arbeiten.',
          quick_access_button: 'Zugang anfragen',
          quick_login_title: 'Konsolen-Login oeffnen',
          quick_login_note: 'Wenn du bereits ein Konto hast, oeffne direkt die authentifizierte Konsole.',
          quick_login_button: 'Login oeffnen',
          quick_demos_button: 'Demos ansehen',
          featured_showcase_title: 'Oeffentliche Untersuchungen mit visuellem Kontext fuer Analysten.',
          featured_showcase_intro: 'Hier erscheinen nur Untersuchungen, die von Administratoren als oeffentlich und auf der Startseite sichtbar markiert wurden, inklusive Graph-Posture, IOC-Dichte, Evidenzstatus und Direktzugriff auf den Fall.',
          featured_case_public: 'Oeffentliche Untersuchung',
          featured_case_updated: 'Aktualisiert',
          featured_case_score_title: 'Fall-Posture',
          featured_case_graph: 'Graph',
          featured_case_iocs: 'IOCs',
          featured_case_evidence: 'Evidenz',
          featured_case_freshness: 'Aktualitat',
          featured_case_nodes: 'Knoten',
          featured_case_nodes_hint: 'Im Graph dargestellte Entitaten.',
          featured_case_edges: 'Kanten',
          featured_case_edges_hint: 'Beobachtete Verbindungen im Fall.',
          featured_case_ioc_total: 'IOCs',
          featured_case_tags_suffix: 'zugeordnete Tags',
          public_evidence_title: 'ClickFix-Evidenz (Vorher-Serveraufnahme / Nachher-Erweiterungsaufnahme)',
          evidence_before: 'Vorher',
          evidence_after: 'Nachher',
          evidence_before_pending: 'Die Vorher-Aufnahme ist fuer diesen Fall noch nicht oeffentlich freigegeben.',
          evidence_after_pending: 'Die Nachher-Aufnahme ist fuer diesen Fall noch nicht oeffentlich freigegeben.'
        },
        nl: {
          marketing_bar_aria: 'Snelle acties',
          marketing_bar_title: 'Start in minder dan 10 minuten',
          marketing_bar_subtitle: 'Installeer de extensie, vraag toegang aan en toon waarde vanaf je eerste onderzoek.',
          marketing_install: 'Installeren',
          marketing_access: 'Toegang',
          marketing_demos: 'Demos',
          join_analyst: 'Meedoen als analist',
          radar_feed_live: 'Globale detectiekaart',
          threat_stream_title: 'Laatste detecties',
          loading_telemetry: 'Live telemetrie wordt gestart...',
          threat_alerts_24h: 'alerts/24h',
          threat_blocks_24h: 'blokkeringen/24h',
          threat_domain: 'domein',
          threat_no_data: 'monitoring actief // wacht op nieuwe detecties',
          chart_activity_title: '7d activiteit (alerts vs blokkeringen)',
          chart_heat_title: 'Monitoringmatrix (7d)',
          chart_activity_empty: 'Geen activiteit in de laatste 7 dagen.',
          chart_heat_empty: 'Wachten op telemetrie om de matrix te tonen.',
          chart_block_rate_7d: 'Blokkeringsratio 7d',
          chart_heat_footnote: 'A/B = alerts vs blokkeringen van de laatste 7 dagen.',
          activity_abbr_alert: 'A',
          activity_abbr_block: 'B',
          recent_unknown: 'Onbekend',
          quick_install_title: 'Extensie installeren',
          quick_install_note: 'Snel uitrollen via de Chrome Web Store in beheerde omgevingen.',
          quick_install_button: 'Extensie installeren',
          quick_access_title: 'Toegang aanvragen',
          quick_access_note: 'Vraag analyst onboarding aan om met alerts, events en bewijs te werken.',
          quick_access_button: 'Toegang aanvragen',
          quick_login_title: 'Console-login openen',
          quick_login_note: 'Als je al een account hebt, ga dan direct naar de geauthenticeerde console.',
          quick_login_button: 'Login openen',
          quick_demos_button: 'Demos bekijken',
          featured_showcase_title: 'Publieke onderzoeken met visuele context voor analisten.',
          featured_showcase_intro: 'Hier verschijnen alleen onderzoeken die door beheerders expliciet als publiek en zichtbaar op de homepage zijn gemarkeerd, met graph-posture, IOC-dichtheid, bewijsstatus en directe toegang tot de case.',
          featured_case_public: 'Publiek onderzoek',
          featured_case_updated: 'Bijgewerkt',
          featured_case_score_title: 'Case-posture',
          featured_case_graph: 'Graaf',
          featured_case_iocs: 'IOCs',
          featured_case_evidence: 'Bewijs',
          featured_case_freshness: 'Versheid',
          featured_case_nodes: 'Nodes',
          featured_case_nodes_hint: 'Entiteiten die in de graaf staan.',
          featured_case_edges: 'Relaties',
          featured_case_edges_hint: 'Waargenomen verbanden in de case.',
          featured_case_ioc_total: 'IOCs',
          featured_case_tags_suffix: 'gekoppelde tags',
          public_evidence_title: 'ClickFix-bewijs (vooraf-servercapture / achteraf-extensiecapture)',
          evidence_before: 'Vooraf',
          evidence_after: 'Achteraf',
          evidence_before_pending: 'De vooraf-capture is voor deze case nog niet publiek goedgekeurd.',
          evidence_after_pending: 'De achteraf-capture is voor deze case nog niet publiek goedgekeurd.'
        },
        ca: {
          marketing_bar_aria: 'Accions rapides',
          marketing_bar_title: 'Comenca en menys de 10 minuts',
          marketing_bar_subtitle: 'Instal la extensio, demana acces i demostra valor des de la primera investigacio.',
          marketing_install: 'Instal lar',
          marketing_access: 'Acces',
          marketing_demos: 'Demos',
          join_analyst: 'Unir-me com a analista',
          radar_feed_live: 'Mapa global de deteccions',
          threat_stream_title: 'Deteccions recents',
          loading_telemetry: 'Inicialitzant la telemetria en viu...',
          threat_alerts_24h: 'alertes/24h',
          threat_blocks_24h: 'bloquejos/24h',
          threat_domain: 'domini',
          threat_no_data: 'monitoratge actiu // esperant deteccions noves',
          chart_activity_title: 'Activitat 7d (alertes vs bloquejos)',
          chart_heat_title: 'Matriu de monitoratge (7d)',
          chart_activity_empty: 'Sense activitat en els ultims 7 dies.',
          chart_heat_empty: 'Esperant telemetria per renderitzar la matriu.',
          chart_block_rate_7d: 'Taxa de bloqueig 7d',
          chart_heat_footnote: 'A/B = alertes vs bloquejos dels ultims 7 dies.',
          activity_abbr_alert: 'A',
          activity_abbr_block: 'B',
          recent_unknown: 'Desconegut',
          quick_install_title: 'Instal lar extensio',
          quick_install_note: 'Desplegament rapid des de Chrome Web Store en entorns gestionats.',
          quick_install_button: 'Instal lar extensio',
          quick_access_title: 'Demanar acces',
          quick_access_note: 'Demana alta d analista per treballar alertes, esdeveniments i evidencies.',
          quick_access_button: 'Demanar acces',
          quick_login_title: 'Obrir login de la consola',
          quick_login_note: 'Si ja tens compte, entra directament al panell autenticat.',
          quick_login_button: 'Obrir login',
          quick_demos_button: 'Veure demos',
          featured_showcase_title: 'Investigacions publiques amb context visual pensat per a analistes.',
          featured_showcase_intro: 'Aqui nomes apareixen investigacions marcades per administracio com a publiques i visibles a l inici, amb postura del graf, densitat IOC, estat de l evidencia i acces directe al cas.',
          featured_case_public: 'Investigacio publica',
          featured_case_updated: 'Actualitzat',
          featured_case_score_title: 'Postura del cas',
          featured_case_graph: 'Graf',
          featured_case_iocs: 'IOCs',
          featured_case_evidence: 'Evidencia',
          featured_case_freshness: 'Recencia',
          featured_case_nodes: 'Nodes',
          featured_case_nodes_hint: 'Entitats representades al graf.',
          featured_case_edges: 'Relacions',
          featured_case_edges_hint: 'Connexions observades dins del cas.',
          featured_case_ioc_total: 'IOCs',
          featured_case_tags_suffix: 'tags associats',
          public_evidence_title: 'Evidencia ClickFix (captura previa del servidor / captura posterior de l extensio)',
          evidence_before: 'Abans',
          evidence_after: 'Despres',
          evidence_before_pending: 'La captura abans encara no esta aprovada publicament per a aquest cas.',
          evidence_after_pending: 'La captura despres encara no esta aprovada publicament per a aquest cas.'
        }
      };
      Object.keys(uiStringCoverage).forEach(function(langKey) {
        uiStrings[langKey] = Object.assign({}, uiStrings[langKey] || {}, uiStringCoverage[langKey]);
      });
      var serverInitialLang = <?= json_encode($initialLang, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
      var clientGeoContext = <?= json_encode($clientGeoPublicContext, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
      var heroWorldDataBootstrap = <?= json_encode($heroWorldGeoBootstrap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
      var geoUiStrings = {
        en: {
          eyebrow: 'Regional context',
          label_region: 'Region',
          label_timezone: 'Timezone',
          label_network: 'Network',
          label_language: 'Language',
          summary_ready: 'Map and content tuned to your region.',
          summary_empty: 'Automatic regional context unavailable. Defaulting to English and global view.',
          network_direct: 'Direct access',
          network_proxy: 'Proxy/VPN',
          network_hosting: 'Hosting/DC',
          network_mobile: 'Mobile network',
          tag_currency: 'Currency',
          tag_proxy: 'Proxy route',
          tag_hosting: 'Hosted ASN',
          tag_mobile: 'Mobile carrier',
          tag_regional: 'Regional lens',
          map_global: 'Global monitoring view',
          map_viewer: 'Viewer region',
          map_network: 'Network',
          stream_region: 'Viewer region:',
          stream_network: 'Network:'
        },
        es: {
          eyebrow: 'Contexto regional',
          label_region: 'Region',
          label_timezone: 'Zona horaria',
          label_network: 'Red',
          label_language: 'Idioma',
          summary_ready: 'Mapa y contenido ajustados a tu region.',
          summary_empty: 'Contexto regional automatico no disponible. Se usa ingles y vista global.',
          network_direct: 'Acceso directo',
          network_proxy: 'Proxy/VPN',
          network_hosting: 'Hosting/DC',
          network_mobile: 'Red movil',
          tag_currency: 'Moneda',
          tag_proxy: 'Ruta proxy',
          tag_hosting: 'ASN hospedado',
          tag_mobile: 'Operador movil',
          tag_regional: 'Lente regional',
          map_global: 'Vista global de monitoreo',
          map_viewer: 'Region del visitante',
          map_network: 'Red',
          stream_region: 'Region visitante:',
          stream_network: 'Red:'
        },
        ca: {
          eyebrow: 'Context regional',
          label_region: 'Regio',
          label_timezone: 'Fus horari',
          label_network: 'Xarxa',
          label_language: 'Idioma',
          summary_ready: 'Mapa i contingut ajustats a la teva regio.',
          summary_empty: 'Context regional automatic no disponible. Es fa servir angles i vista global.',
          network_direct: 'Acces directe',
          network_proxy: 'Proxy/VPN',
          network_hosting: 'Hosting/DC',
          network_mobile: 'Xarxa mobil',
          tag_currency: 'Moneda',
          tag_proxy: 'Ruta proxy',
          tag_hosting: 'ASN allotjat',
          tag_mobile: 'Operador mobil',
          tag_regional: 'Focus regional',
          map_global: 'Vista global de monitoratge',
          map_viewer: 'Regio del visitant',
          map_network: 'Xarxa',
          stream_region: 'Regio visitant:',
          stream_network: 'Xarxa:'
        },
        pt: {
          eyebrow: 'Contexto regional',
          label_region: 'Regiao',
          label_timezone: 'Fuso horario',
          label_network: 'Rede',
          label_language: 'Idioma',
          summary_ready: 'Mapa e conteudo ajustados para sua regiao.',
          summary_empty: 'Contexto regional automatico indisponivel. Padrao em ingles e visao global.',
          network_direct: 'Acesso direto',
          network_proxy: 'Proxy/VPN',
          network_hosting: 'Hosting/DC',
          network_mobile: 'Rede movel',
          tag_currency: 'Moeda',
          tag_proxy: 'Rota proxy',
          tag_hosting: 'ASN hospedado',
          tag_mobile: 'Operadora movel',
          tag_regional: 'Lente regional',
          map_global: 'Visao global de monitoramento',
          map_viewer: 'Regiao do visitante',
          map_network: 'Rede',
          stream_region: 'Regiao visitante:',
          stream_network: 'Rede:'
        },
        fr: {
          eyebrow: 'Contexte regional',
          label_region: 'Region',
          label_timezone: 'Fuseau horaire',
          label_network: 'Reseau',
          label_language: 'Langue',
          summary_ready: 'Carte et contenu ajustes a votre region.',
          summary_empty: 'Contexte regional indisponible. Bascule sur anglais et vue globale.',
          network_direct: 'Acces direct',
          network_proxy: 'Proxy/VPN',
          network_hosting: 'Hebergement/DC',
          network_mobile: 'Reseau mobile',
          tag_currency: 'Devise',
          tag_proxy: 'Route proxy',
          tag_hosting: 'ASN heberge',
          tag_mobile: 'Operateur mobile',
          tag_regional: 'Vue regionale',
          map_global: 'Vue globale de monitoring',
          map_viewer: 'Region du visiteur',
          map_network: 'Reseau',
          stream_region: 'Region visiteur :',
          stream_network: 'Reseau :'
        },
        de: {
          eyebrow: 'Regionalkontext',
          label_region: 'Region',
          label_timezone: 'Zeitzone',
          label_network: 'Netz',
          label_language: 'Sprache',
          summary_ready: 'Karte und Inhalte sind auf deine Region abgestimmt.',
          summary_empty: 'Kein Regional-Kontext verfugbar. Fallback auf Englisch und globale Ansicht.',
          network_direct: 'Direkter Zugriff',
          network_proxy: 'Proxy/VPN',
          network_hosting: 'Hosting/DC',
          network_mobile: 'Mobilfunknetz',
          tag_currency: 'Wahrung',
          tag_proxy: 'Proxy-Route',
          tag_hosting: 'Gehostetes ASN',
          tag_mobile: 'Mobilfunk',
          tag_regional: 'Regionaler Fokus',
          map_global: 'Globale Monitoring-Ansicht',
          map_viewer: 'Region des Besuchers',
          map_network: 'Netz',
          stream_region: 'Besucherregion:',
          stream_network: 'Netz:'
        },
        nl: {
          eyebrow: 'Regionale context',
          label_region: 'Regio',
          label_timezone: 'Tijdzone',
          label_network: 'Netwerk',
          label_language: 'Taal',
          summary_ready: 'Kaart en content afgestemd op jouw regio.',
          summary_empty: 'Geen regionale context beschikbaar. Fallback naar Engels en globaal beeld.',
          network_direct: 'Directe toegang',
          network_proxy: 'Proxy/VPN',
          network_hosting: 'Hosting/DC',
          network_mobile: 'Mobiel netwerk',
          tag_currency: 'Valuta',
          tag_proxy: 'Proxy-route',
          tag_hosting: 'Gehost ASN',
          tag_mobile: 'Mobiele provider',
          tag_regional: 'Regionale lens',
          map_global: 'Globale monitorweergave',
          map_viewer: 'Regio van bezoeker',
          map_network: 'Netwerk',
          stream_region: 'Bezoekersregio:',
          stream_network: 'Netwerk:'
        }
      };
      var saved = localStorage.getItem('cfm_lang');
      var initial = saved && supported.includes(saved) ? saved : (supported.includes(serverInitialLang) ? serverInitialLang : 'en');
      try {
        var queryLang = new URLSearchParams(window.location.search).get('lang');
        if (queryLang && supported.includes(queryLang)) {
          initial = queryLang;
        }
      } catch (error) {
        // Ignore URL parsing failures.
      }

      var seoLocaleByLang = {
        ar: 'ar_AR',
        ca: 'ca_ES',
        de: 'de_DE',
        en: 'en_US',
        es: 'es_ES',
        fr: 'fr_FR',
        he: 'he_IL',
        hi: 'hi_IN',
        ja: 'ja_JP',
        ko: 'ko_KR',
        nl: 'nl_NL',
        pt: 'pt_PT',
        ru: 'ru_RU',
        zh: 'zh_CN'
      };

      function uiDictFor(lang) {
        return uiStrings[lang] || uiStrings.en || {};
      }

      function uiText(key, lang) {
        if (!key) return '';
        var dict = uiDictFor(lang || document.documentElement.lang || 'en');
        if (Object.prototype.hasOwnProperty.call(dict, key) && dict[key]) {
          return dict[key];
        }
        var fallback = uiStrings.en || {};
        if (Object.prototype.hasOwnProperty.call(fallback, key) && fallback[key]) {
          return fallback[key];
        }
        return '';
      }

      function geoDictFor(lang) {
        return geoUiStrings[lang] || geoUiStrings.en || {};
      }

      function geoText(key, lang) {
        if (!key) return '';
        var dict = geoDictFor(lang || document.documentElement.lang || 'en');
        if (Object.prototype.hasOwnProperty.call(dict, key) && dict[key]) {
          return dict[key];
        }
        var fallback = geoUiStrings.en || {};
        if (Object.prototype.hasOwnProperty.call(fallback, key) && fallback[key]) {
          return fallback[key];
        }
        return '';
      }

      function escapeHtml(value) {
        return String(value || '').replace(/[&<>\"']/g, function(char) {
          return {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;'
          }[char] || char;
        });
      }

      function currentLanguageLabel(lang) {
        return languageOptionLabels[lang] || String(lang || 'en').toUpperCase();
      }

      function networkProfileLabel(profile, lang) {
        if (profile === 'proxy') return geoText('network_proxy', lang);
        if (profile === 'hosting') return geoText('network_hosting', lang);
        if (profile === 'mobile') return geoText('network_mobile', lang);
        return geoText('network_direct', lang);
      }

      function setMetaByName(name, content) {
        var node = document.querySelector('meta[name="' + name + '"]');
        if (node && typeof content === 'string' && content !== '') {
          node.setAttribute('content', content);
        }
      }

      function setMetaByProperty(prop, content) {
        var node = document.querySelector('meta[property="' + prop + '"]');
        if (node && typeof content === 'string' && content !== '') {
          node.setAttribute('content', content);
        }
      }

      function updateSeoJsonLd(dict, lang) {
        var node = document.getElementById('seo-website-jsonld');
        if (!node || !dict) {
          return;
        }
        try {
          var payload = JSON.parse(node.textContent || '{}');
          payload.name = dict.title || payload.name || 'ClickFix Mitigator';
          payload.description = dict.description || payload.description || '';
          payload.inLanguage = lang || payload.inLanguage || 'en';
          node.textContent = JSON.stringify(payload);
        } catch (error) {
          // Ignore JSON-LD update errors.
        }
      }

      function applyUiStrings(lang) {
        var titleValue = uiText('title', lang);
        var descriptionValue = uiText('description', lang);
        document.title = titleValue;
        var meta = document.querySelector('meta[name="description"]');
        if (meta) {
          meta.setAttribute('content', descriptionValue);
        }
        setMetaByProperty('og:title', titleValue);
        setMetaByProperty('og:description', descriptionValue);
        setMetaByProperty('og:locale', seoLocaleByLang[lang] || 'en_US');
        setMetaByName('twitter:title', titleValue);
        setMetaByName('twitter:description', descriptionValue);
        updateSeoJsonLd({ title: titleValue, description: descriptionValue }, lang);
        var label = document.querySelector('[data-i18n="language_label"]');
        if (label) {
          label.textContent = uiText('language_label', lang);
        }
        document.querySelectorAll('[data-i18n-aria]').forEach(function(node) {
          var key = node.getAttribute('data-i18n-aria');
          var text = uiText(key, lang);
          if (key && text) {
            node.setAttribute('aria-label', text);
          }
        });
        document.querySelectorAll('[data-i18n-alt]').forEach(function(node) {
          var key = node.getAttribute('data-i18n-alt');
          var text = uiText(key, lang);
          if (key && text) {
            node.setAttribute('alt', text);
          }
        });
        document.querySelectorAll('[data-i18n-text]').forEach(function(node) {
          var key = node.getAttribute('data-i18n-text');
          var text = uiText(key, lang);
          if (key && text) {
            node.textContent = text;
          }
        });
        document.querySelectorAll('[data-lang-option]').forEach(function(option) {
          var code = option.getAttribute('data-lang-option');
          if (code && languageOptionLabels[code]) {
            option.textContent = languageOptionLabels[code];
          }
        });
      }

      function setLang(lang) {
        if (!supported.includes(lang)) return;
        document.body.classList.remove(
          'lang-ar',
          'lang-ca',
          'lang-de',
          'lang-en',
          'lang-es',
          'lang-fr',
          'lang-it',
          'lang-he',
          'lang-hi',
          'lang-ja',
          'lang-ko',
          'lang-nl',
          'lang-pt',
          'lang-ru',
          'lang-zh'
        );
        document.body.classList.add('lang-' + lang);
        document.documentElement.lang = lang;
        document.documentElement.dir = (lang === 'ar' || lang === 'he') ? 'rtl' : 'ltr';
        var requestLangField = document.getElementById('request-lang-field');
        if (requestLangField) {
          requestLangField.value = lang;
        }
        document.querySelectorAll('[data-lang-select]').forEach(function(node) {
          if (node.value !== lang) {
            node.value = lang;
          }
        });
        var fabToggle = document.getElementById('lang-fab-toggle');
        if (fabToggle) {
          fabToggle.textContent = String(lang || 'en').toUpperCase();
        }
        localStorage.setItem('cfm_lang', lang);
        applyUiStrings(lang);
        renderFeaturedCaseCharts();
        if (typeof refreshPreviewLocale === 'function') {
          refreshPreviewLocale();
        }
      }

      document.querySelectorAll('[data-lang-select]').forEach(function(selectNode) {
        selectNode.value = initial;
        selectNode.addEventListener('change', function() {
          setLang(selectNode.value);
        });
      });

      var langFab = document.getElementById('lang-fab');
      var langFabToggle = document.getElementById('lang-fab-toggle');
      if (langFab && langFabToggle) {
        langFabToggle.addEventListener('click', function() {
          langFab.classList.toggle('is-open');
        });
        document.addEventListener('click', function(event) {
          if (!langFab.contains(event.target)) {
            langFab.classList.remove('is-open');
          }
        });
      }

      setLang(initial);
      renderGeoContextCard();
      renderHeroMapBadge();
      renderFeaturedCaseCharts();
      updateThreatStream({
        stats: {},
        recent_domains: [],
        geo_points: []
      });

      var marketingBar = document.getElementById('marketing-bar');
      var marketingBarDismiss = document.getElementById('marketing-bar-dismiss');
      var marketingBarStorageKey = 'cfm_marketing_bar_hidden_v1';
      if (marketingBar && localStorage.getItem(marketingBarStorageKey) === '1') {
        marketingBar.classList.add('is-hidden');
      }
      if (marketingBar && marketingBarDismiss) {
        marketingBarDismiss.addEventListener('click', function() {
          marketingBar.classList.add('is-hidden');
          localStorage.setItem(marketingBarStorageKey, '1');
        });
      }
      window.addEventListener('resize', renderFeaturedCaseCharts);

      var previewPayload = null;
      var previewFetchInFlight = false;
      var previewRefreshTimer = 0;
      var previewChartSequence = 0;
      var mapContainer = document.getElementById('hero-leaflet-map');
      var threatStreamNode = document.getElementById('hero-threat-stream');
      var heroCanvasCtx = mapContainer && typeof mapContainer.getContext === 'function'
        ? mapContainer.getContext('2d')
        : null;
      var heroMap = heroCanvasCtx ? { kind: 'canvas-globe' } : null;
      var heroMapStarted = false;
      var heroGeoJsonPromise = null;
      var heroWorldData = heroWorldDataBootstrap || null;
      var heroMapOrbitLon = 8;
      var heroMapOrbitLatBase = 18;
      var heroMapOrbitPhase = 0;
      var heroMapUserPauseUntil = 0;
      var heroMapAnimationFrame = 0;
      var heroMapOrbitTimer = 0;
      var heroMapLastTimestamp = 0;
      var heroMapPulse = 0;
      var heroMapPoints = [];
      var heroMapConnections = [];
      var heroMapViewerPoint = null;
      var heroMapDragging = null;
      var heroMapHasFocusedPoints = false;
      var heroGlobeLabels = [
        { name: 'NORTH AMERICA', lat: 45, lon: -104 },
        { name: 'SOUTH AMERICA', lat: -16, lon: -60 },
        { name: 'EUROPE', lat: 51, lon: 16 },
        { name: 'AFRICA', lat: 8, lon: 20 },
        { name: 'ASIA', lat: 36, lon: 92 },
        { name: 'OCEANIA', lat: -23, lon: 138 }
      ];

      function queueHeroGlobeResize() {
        if (!mapContainer || !heroCanvasCtx) {
          return;
        }
        window.requestAnimationFrame(function() {
          heroDrawGlobe();
        });
      }

      function resizeHeroCanvas() {
        if (!mapContainer || !heroCanvasCtx) {
          return null;
        }
        var rect = mapContainer.getBoundingClientRect();
        var width = Math.max(320, Math.round(rect.width || mapContainer.clientWidth || 0));
        var height = Math.max(240, Math.round(rect.height || mapContainer.clientHeight || 0));
        var dpr = Math.max(1, Math.min(2, window.devicePixelRatio || 1));
        var targetWidth = Math.round(width * dpr);
        var targetHeight = Math.round(height * dpr);
        if (mapContainer.width !== targetWidth || mapContainer.height !== targetHeight) {
          mapContainer.width = targetWidth;
          mapContainer.height = targetHeight;
        }
        heroCanvasCtx.setTransform(dpr, 0, 0, dpr, 0, 0);
        var radius = Math.max(92, Math.min(width, height) * 0.42);
        var cx = width * 0.5;
        var cy = height * 0.53;
        return {
          width: width,
          height: height,
          cx: cx,
          cy: cy,
          radius: radius
        };
      }

      function heroProjectPoint(lon, lat, centerLon, centerLat, viewport) {
        var lambda = lon * Math.PI / 180;
        var phi = lat * Math.PI / 180;
        var lambda0 = centerLon * Math.PI / 180;
        var phi0 = centerLat * Math.PI / 180;
        var cosPhi = Math.cos(phi);
        var sinPhi = Math.sin(phi);
        var cosPhi0 = Math.cos(phi0);
        var sinPhi0 = Math.sin(phi0);
        var delta = lambda - lambda0;
        var cosDelta = Math.cos(delta);
        var sinDelta = Math.sin(delta);
        var x = cosPhi * sinDelta;
        var y = cosPhi0 * sinPhi - sinPhi0 * cosPhi * cosDelta;
        var z = sinPhi0 * sinPhi + cosPhi0 * cosPhi * cosDelta;
        return {
          x: viewport.cx + viewport.radius * x,
          y: viewport.cy - viewport.radius * y,
          z: z,
          visible: z > 0
        };
      }

      function heroInterpolateCoord(a, b, t) {
        return [
          a[0] + (b[0] - a[0]) * t,
          a[1] + (b[1] - a[1]) * t
        ];
      }

      function heroProjectHorizonPoint(a, b, centerLon, centerLat, viewport) {
        var low = 0;
        var high = 1;
        var result = null;
        for (var i = 0; i < 16; i += 1) {
          var mid = (low + high) / 2;
          var coord = heroInterpolateCoord(a, b, mid);
          var projected = heroProjectPoint(coord[0], coord[1], centerLon, centerLat, viewport);
          result = projected;
          var aVisible = heroProjectPoint(a[0], a[1], centerLon, centerLat, viewport).visible;
          if (projected.visible === aVisible) {
            low = mid;
          } else {
            high = mid;
          }
        }
        return result;
      }

      function heroTraceProjectedRing(ctx, ring, centerLon, centerLat, viewport, closePath) {
        if (!Array.isArray(ring) || ring.length < 2) {
          return false;
        }
        var drew = false;
        var prevCoord = ring[0];
        var prevPoint = heroProjectPoint(prevCoord[0], prevCoord[1], centerLon, centerLat, viewport);
        var subpathOpen = false;

        for (var i = 1; i < ring.length; i += 1) {
          var currCoord = ring[i];
          var currPoint = heroProjectPoint(currCoord[0], currCoord[1], centerLon, centerLat, viewport);
          if (prevPoint.visible && currPoint.visible) {
            if (!subpathOpen) {
              ctx.moveTo(prevPoint.x, prevPoint.y);
              subpathOpen = true;
              drew = true;
            }
            ctx.lineTo(currPoint.x, currPoint.y);
          } else if (prevPoint.visible !== currPoint.visible) {
            var edgePoint = heroProjectHorizonPoint(prevCoord, currCoord, centerLon, centerLat, viewport);
            if (!edgePoint) {
              prevCoord = currCoord;
              prevPoint = currPoint;
              continue;
            }
            if (prevPoint.visible) {
              if (!subpathOpen) {
                ctx.moveTo(prevPoint.x, prevPoint.y);
                subpathOpen = true;
                drew = true;
              }
              ctx.lineTo(edgePoint.x, edgePoint.y);
              if (closePath) {
                ctx.closePath();
              }
              subpathOpen = false;
            } else {
              ctx.moveTo(edgePoint.x, edgePoint.y);
              ctx.lineTo(currPoint.x, currPoint.y);
              subpathOpen = true;
              drew = true;
            }
          }
          prevCoord = currCoord;
          prevPoint = currPoint;
        }
        if (subpathOpen && closePath) {
          ctx.closePath();
        }
        return drew;
      }

      function heroDrawGraticule(ctx, viewport, centerLon, centerLat) {
        ctx.save();
        ctx.strokeStyle = 'rgba(108, 181, 214, 0.12)';
        ctx.lineWidth = 1;
        for (var lat = -60; lat <= 60; lat += 30) {
          ctx.beginPath();
          var started = false;
          for (var lon = -180; lon <= 180; lon += 4) {
            var point = heroProjectPoint(lon, lat, centerLon, centerLat, viewport);
            if (!point.visible) {
              started = false;
              continue;
            }
            if (!started) {
              ctx.moveTo(point.x, point.y);
              started = true;
            } else {
              ctx.lineTo(point.x, point.y);
            }
          }
          ctx.stroke();
        }
        for (var meridian = -150; meridian <= 180; meridian += 30) {
          ctx.beginPath();
          var meridianStarted = false;
          for (var latitude = -88; latitude <= 88; latitude += 3) {
            var meridianPoint = heroProjectPoint(meridian, latitude, centerLon, centerLat, viewport);
            if (!meridianPoint.visible) {
              meridianStarted = false;
              continue;
            }
            if (!meridianStarted) {
              ctx.moveTo(meridianPoint.x, meridianPoint.y);
              meridianStarted = true;
            } else {
              ctx.lineTo(meridianPoint.x, meridianPoint.y);
            }
          }
          ctx.stroke();
        }
        ctx.restore();
      }

      function heroDrawLandmass(ctx, viewport, centerLon, centerLat) {
        if (!heroWorldData || !Array.isArray(heroWorldData.features)) {
          return;
        }
        ctx.save();
        ctx.shadowBlur = 10;
        ctx.shadowColor = 'rgba(170, 220, 255, 0.08)';
        ctx.fillStyle = 'rgba(78, 88, 96, 0.98)';
        ctx.strokeStyle = 'rgba(214, 228, 236, 0.42)';
        ctx.lineWidth = 1.05;
        heroWorldData.features.forEach(function(feature) {
          var geometry = feature && feature.geometry ? feature.geometry : null;
          if (!geometry) {
            return;
          }
          var polygons = [];
          if (geometry.type === 'Polygon') {
            polygons = [geometry.coordinates];
          } else if (geometry.type === 'MultiPolygon') {
            polygons = geometry.coordinates;
          }
          polygons.forEach(function(polygon) {
            ctx.beginPath();
            var drewAny = false;
            polygon.forEach(function(ring) {
              if (heroTraceProjectedRing(ctx, ring, centerLon, centerLat, viewport, true)) {
                drewAny = true;
              }
            });
            if (drewAny) {
              ctx.fill();
              ctx.stroke();
            }
          });
        });
        ctx.restore();
      }

      function heroDrawLabels(ctx, viewport, centerLon, centerLat) {
        ctx.save();
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.font = '10px "JetBrains Mono", monospace';
        ctx.fillStyle = 'rgba(234, 245, 255, 0.26)';
        heroGlobeLabels.forEach(function(label) {
          var projected = heroProjectPoint(label.lon, label.lat, centerLon, centerLat, viewport);
          if (!projected.visible || projected.z < 0.18) {
            return;
          }
          ctx.fillText(label.name, projected.x, projected.y);
        });
        ctx.restore();
      }

      function heroPointStyle(kind) {
        if (kind === 'viewer') {
          return {
            fill: '#a5e9ff',
            glow: 'rgba(24, 229, 255, 0.62)'
          };
        }
        if (kind === 'domain') {
          return {
            fill: '#fff48f',
            glow: 'rgba(255, 244, 143, 0.62)'
          };
        }
        return {
          fill: '#72f7a3',
          glow: 'rgba(89, 240, 141, 0.68)'
        };
      }

      function heroDrawPoint(ctx, point, viewport, centerLon, centerLat, pulseSeed, kindOverride) {
        var projected = heroProjectPoint(point.lon, point.lat, centerLon, centerLat, viewport);
        if (!projected.visible || projected.z < 0.05) {
          return;
        }
        var kind = kindOverride || point.kind || 'alert';
        var style = heroPointStyle(kind);
        var baseSize = point.size || Math.max(6, Math.min(14, Math.round(5 + Math.log((point.hits || 1) + 1) * 2.2)));
        var pulse = 0.9 + 0.42 * Math.sin(heroMapPulse + pulseSeed);
        var size = baseSize * pulse * (0.6 + projected.z * 0.72);

        ctx.save();
        ctx.shadowBlur = 24;
        ctx.shadowColor = style.glow;
        ctx.fillStyle = style.fill;
        ctx.beginPath();
        ctx.arc(projected.x, projected.y, size, 0, Math.PI * 2);
        ctx.fill();
        ctx.shadowBlur = 0;
        ctx.strokeStyle = 'rgba(255,255,255,0.32)';
        ctx.lineWidth = 1.2;
        ctx.beginPath();
        ctx.arc(projected.x, projected.y, size + 2.8, 0, Math.PI * 2);
        ctx.stroke();
        ctx.strokeStyle = style.glow;
        ctx.lineWidth = 1;
        ctx.beginPath();
        ctx.arc(projected.x, projected.y, size + 6.2 + Math.sin(heroMapPulse + pulseSeed) * 1.4, 0, Math.PI * 2);
        ctx.stroke();
        ctx.restore();
      }

      function heroDrawConnection(ctx, connection, viewport, centerLon, centerLat) {
        if (!connection || !connection.from || !connection.to) {
          return;
        }
        var from = heroProjectPoint(connection.from.lon, connection.from.lat, centerLon, centerLat, viewport);
        var to = heroProjectPoint(connection.to.lon, connection.to.lat, centerLon, centerLat, viewport);
        if (!from.visible || !to.visible || from.z < 0.12 || to.z < 0.12) {
          return;
        }
        var midX = (from.x + to.x) / 2;
        var midY = (from.y + to.y) / 2;
        var bendX = midX + ((midX - viewport.cx) * 0.18);
        var bendY = midY + ((midY - viewport.cy) * 0.18) - 18;
        ctx.save();
        ctx.strokeStyle = connection.kind === 'domain'
          ? 'rgba(255, 232, 138, 0.28)'
          : 'rgba(73, 216, 255, 0.24)';
        ctx.lineWidth = 1.2;
        ctx.shadowBlur = 10;
        ctx.shadowColor = connection.kind === 'domain'
          ? 'rgba(255, 232, 138, 0.24)'
          : 'rgba(73, 216, 255, 0.22)';
        ctx.beginPath();
        ctx.moveTo(from.x, from.y);
        ctx.quadraticCurveTo(bendX, bendY, to.x, to.y);
        ctx.stroke();
        ctx.restore();
      }

      function heroBuildConnections(points) {
        var list = Array.isArray(points) ? points.slice() : [];
        if (list.length < 2) {
          return [];
        }
        list.sort(function(a, b) {
          return Number(b && b.hits ? b.hits : 0) - Number(a && a.hits ? a.hits : 0);
        });
        var anchor = list[0];
        var connections = [];
        list.slice(1, 8).forEach(function(point) {
          connections.push({
            from: anchor,
            to: point,
            kind: point.kind || 'alert'
          });
        });
        return connections;
      }

      function heroFocusOnPoints(points) {
        if (!Array.isArray(points) || !points.length) {
          return;
        }
        var totalWeight = 0;
        var sumLat = 0;
        var sumSin = 0;
        var sumCos = 0;
        points.slice(0, 18).forEach(function(point) {
          var weight = Math.max(1, Number(point.hits || 1));
          var lonRad = (Number(point.lon) || 0) * Math.PI / 180;
          var lat = Number(point.lat) || 0;
          totalWeight += weight;
          sumLat += lat * weight;
          sumSin += Math.sin(lonRad) * weight;
          sumCos += Math.cos(lonRad) * weight;
        });
        if (totalWeight <= 0) {
          return;
        }
        heroMapOrbitLatBase = Math.max(-32, Math.min(32, sumLat / totalWeight));
        heroMapOrbitLon = Math.atan2(sumSin / totalWeight, sumCos / totalWeight) * 180 / Math.PI;
      }

      function heroDrawGlobe() {
        if (!mapContainer || !heroCanvasCtx) {
          return;
        }
        var viewport = resizeHeroCanvas();
        if (!viewport) {
          return;
        }
        var centerLon = heroMapOrbitLon;
        var centerLat = heroMapOrbitLatBase + Math.sin(heroMapOrbitPhase) * 3.5;
        var ctx = heroCanvasCtx;
        ctx.clearRect(0, 0, viewport.width, viewport.height);

        var glow = ctx.createRadialGradient(
          viewport.cx,
          viewport.cy,
          viewport.radius * 0.2,
          viewport.cx,
          viewport.cy,
          viewport.radius * 1.18
        );
        glow.addColorStop(0, 'rgba(22, 54, 74, 0.18)');
        glow.addColorStop(0.65, 'rgba(8, 18, 28, 0.08)');
        glow.addColorStop(1, 'rgba(0, 0, 0, 0)');
        ctx.fillStyle = glow;
        ctx.beginPath();
        ctx.arc(viewport.cx, viewport.cy, viewport.radius * 1.18, 0, Math.PI * 2);
        ctx.fill();

        ctx.save();
        ctx.beginPath();
        ctx.arc(viewport.cx, viewport.cy, viewport.radius, 0, Math.PI * 2);
        ctx.clip();

        var ocean = ctx.createRadialGradient(
          viewport.cx - viewport.radius * 0.24,
          viewport.cy - viewport.radius * 0.34,
          viewport.radius * 0.15,
          viewport.cx,
          viewport.cy,
          viewport.radius * 1.08
        );
        ocean.addColorStop(0, '#223645');
        ocean.addColorStop(0.46, '#101922');
        ocean.addColorStop(1, '#06090d');
        ctx.fillStyle = ocean;
        ctx.fillRect(viewport.cx - viewport.radius, viewport.cy - viewport.radius, viewport.radius * 2, viewport.radius * 2);

        heroDrawGraticule(ctx, viewport, centerLon, centerLat);
        heroDrawLandmass(ctx, viewport, centerLon, centerLat);
        heroDrawLabels(ctx, viewport, centerLon, centerLat);

        var shadow = ctx.createRadialGradient(
          viewport.cx - viewport.radius * 0.62,
          viewport.cy - viewport.radius * 0.06,
          viewport.radius * 0.12,
          viewport.cx - viewport.radius * 0.14,
          viewport.cy,
          viewport.radius * 1.06
        );
        shadow.addColorStop(0, 'rgba(0, 0, 0, 0.66)');
        shadow.addColorStop(0.52, 'rgba(0, 0, 0, 0.28)');
        shadow.addColorStop(1, 'rgba(0, 0, 0, 0)');
        ctx.fillStyle = shadow;
        ctx.fillRect(viewport.cx - viewport.radius, viewport.cy - viewport.radius, viewport.radius * 2, viewport.radius * 2);

        heroMapConnections.forEach(function(connection) {
          heroDrawConnection(ctx, connection, viewport, centerLon, centerLat);
        });
        heroMapPoints.forEach(function(point, index) {
          heroDrawPoint(ctx, point, viewport, centerLon, centerLat, index * 0.7);
        });
        if (heroMapViewerPoint) {
          heroDrawPoint(ctx, heroMapViewerPoint, viewport, centerLon, centerLat, 1.8, 'viewer');
        }
        ctx.restore();

        ctx.save();
        ctx.strokeStyle = 'rgba(206, 240, 255, 0.24)';
        ctx.lineWidth = 1.35;
        ctx.beginPath();
        ctx.arc(viewport.cx, viewport.cy, viewport.radius, 0, Math.PI * 2);
        ctx.stroke();
        ctx.restore();
      }

      function ensureHeroOrbitTimer() {
        if (heroMapOrbitTimer) {
          return;
        }
        heroMapOrbitTimer = window.setInterval(function() {
          if (!heroMapStarted) {
            return;
          }
          if (heroMapDragging) {
            return;
          }
          if (heroMapAnimationFrame) {
            return;
          }
          heroMapOrbitLon += 0.7;
          if (heroMapOrbitLon > 180) {
            heroMapOrbitLon -= 360;
          }
          heroMapOrbitPhase += 0.018;
          heroMapPulse += 0.06;
          heroDrawGlobe();
        }, 40);
      }

      function heroAnimateGlobeFrame(timestamp) {
        if (!heroMapStarted) {
          heroMapAnimationFrame = 0;
          return;
        }
        if (!heroMapLastTimestamp) {
          heroMapLastTimestamp = timestamp;
        }
        var delta = Math.max(16, Math.min(48, timestamp - heroMapLastTimestamp));
        heroMapLastTimestamp = timestamp;
        heroMapPulse += delta * 0.004;
        if (!heroMapDragging) {
          heroMapOrbitLon += delta * 0.022;
          if (heroMapOrbitLon > 180) {
            heroMapOrbitLon -= 360;
          }
          heroMapOrbitPhase += delta * 0.0023;
        }
        heroDrawGlobe();
        heroMapAnimationFrame = window.requestAnimationFrame(heroAnimateGlobeFrame);
      }

      function heroBindInteractions() {
        if (!mapContainer) {
          return;
        }
        mapContainer.addEventListener('pointerdown', function(event) {
          heroMapDragging = {
            x: event.clientX,
            y: event.clientY,
            lon: heroMapOrbitLon,
            lat: heroMapOrbitLatBase
          };
          if (typeof mapContainer.setPointerCapture === 'function') {
            try {
              mapContainer.setPointerCapture(event.pointerId);
            } catch (error) {
              // Ignore pointer capture failures.
            }
          }
        });
        mapContainer.addEventListener('pointermove', function(event) {
          if (!heroMapDragging) {
            return;
          }
          var deltaX = event.clientX - heroMapDragging.x;
          var deltaY = event.clientY - heroMapDragging.y;
          heroMapOrbitLon = heroMapDragging.lon - deltaX * 0.22;
          heroMapOrbitLatBase = Math.max(-42, Math.min(42, heroMapDragging.lat + deltaY * 0.12));
          heroDrawGlobe();
        });
        mapContainer.addEventListener('pointerup', function() {
          heroMapDragging = null;
          heroMapUserPauseUntil = Date.now() + 1200;
        });
        mapContainer.addEventListener('pointerleave', function() {
          heroMapDragging = null;
          heroMapUserPauseUntil = Date.now() + 1200;
        });
        window.addEventListener('resize', queueHeroGlobeResize);
      }

      function formatNumber(value) {
        var lang = document.documentElement.lang || 'en';
        try {
          return new Intl.NumberFormat(lang).format(value);
        } catch (error) {
          return String(value);
        }
      }

      function formatPercent(value) {
        var lang = document.documentElement.lang || 'en';
        try {
          return new Intl.NumberFormat(lang, { minimumFractionDigits: 1, maximumFractionDigits: 1 }).format(value) + '%';
        } catch (error) {
          return String(value) + '%';
        }
      }

      function formatDate(value) {
        if (!value) return '--';
        var date = new Date(value);
        if (Number.isNaN(date.getTime())) return value;
        var lang = document.documentElement.lang || 'en';
        try {
          return date.toLocaleString(lang, { dateStyle: 'medium', timeStyle: 'short' });
        } catch (error) {
          return date.toISOString();
        }
      }

      function compactLabel(value) {
        var raw = String(value || '').trim();
        if (!raw) return '--';
        if (/^\d{4}-\d{2}-\d{2}$/.test(raw)) {
          return raw.slice(5);
        }
        return raw.length > 8 ? raw.slice(0, 8) : raw;
      }

      function getViewerMapFocus() {
        if (clientGeoContext && clientGeoContext.available) {
          var lat = Number(clientGeoContext.lat);
          var lon = Number(clientGeoContext.lon);
          if (Number.isFinite(lat) && Number.isFinite(lon)) {
            return {
              lat: Math.max(-30, Math.min(34, lat)),
              lon: Math.max(-180, Math.min(180, lon))
            };
          }
        }
        return { lat: 18, lon: 8 };
      }

      function applyGeoStaticLabels() {
        var lang = document.documentElement.lang || 'en';
        document.querySelectorAll('[data-geo-text]').forEach(function(node) {
          var key = node.getAttribute('data-geo-text');
          var text = geoText(key, lang);
          if (key && text) {
            node.textContent = text;
          }
        });
      }

      function renderGeoContextCard() {
        applyGeoStaticLabels();
        var lang = document.documentElement.lang || 'en';
        var card = document.getElementById('hero-geo-card');
        if (!card) {
          return;
        }
        var placeNode = document.getElementById('hero-geo-place');
        var summaryNode = document.getElementById('hero-geo-summary');
        var regionNode = document.getElementById('hero-geo-region');
        var timezoneNode = document.getElementById('hero-geo-timezone');
        var networkNode = document.getElementById('hero-geo-network');
        var languageNode = document.getElementById('hero-geo-language');
        var languageBadge = document.getElementById('hero-geo-lang');
        var tagsNode = document.getElementById('hero-geo-tags');

        if (!clientGeoContext || !clientGeoContext.available) {
          if (placeNode) placeNode.textContent = '--';
          if (summaryNode) summaryNode.textContent = geoText('summary_empty', lang);
          if (regionNode) regionNode.textContent = '--';
          if (timezoneNode) timezoneNode.textContent = '--';
          if (networkNode) networkNode.textContent = '--';
          if (languageNode) languageNode.textContent = currentLanguageLabel(lang);
          if (languageBadge) languageBadge.textContent = String(lang || 'en').toUpperCase();
        if (tagsNode) tagsNode.innerHTML = '';
        card.classList.add('is-empty');
        setTimeout(function() {
          queueHeroGlobeResize();
        }, 40);
        return;
      }

        var placeLabel = clientGeoContext.place_label || clientGeoContext.country || '--';
        var regionParts = [];
        if (clientGeoContext.city) regionParts.push(clientGeoContext.city);
        if (clientGeoContext.region_name) regionParts.push(clientGeoContext.region_name);
        if (clientGeoContext.country_code) regionParts.push(clientGeoContext.country_code);
        var regionLabel = regionParts.join(' / ') || placeLabel;
        var networkLabel = networkProfileLabel(clientGeoContext.network_profile, lang);
        var displayNetwork = clientGeoContext.display_network ? String(clientGeoContext.display_network) : networkLabel;
        var tags = [];

        tags.push('<span class="hero-geo-tag">' + escapeHtml(geoText('tag_regional', lang)) + '</span>');
        if (clientGeoContext.currency) {
          tags.push('<span class="hero-geo-tag">' + escapeHtml(geoText('tag_currency', lang)) + ': ' + escapeHtml(clientGeoContext.currency) + '</span>');
        }
        if (clientGeoContext.proxy) {
          tags.push('<span class="hero-geo-tag is-warn">' + escapeHtml(geoText('tag_proxy', lang)) + '</span>');
        } else if (clientGeoContext.hosting) {
          tags.push('<span class="hero-geo-tag is-warn">' + escapeHtml(geoText('tag_hosting', lang)) + '</span>');
        } else if (clientGeoContext.mobile) {
          tags.push('<span class="hero-geo-tag">' + escapeHtml(geoText('tag_mobile', lang)) + '</span>');
        }
        tags = tags.slice(0, 2);

        if (placeNode) placeNode.textContent = placeLabel;
        if (summaryNode) summaryNode.textContent = geoText('summary_ready', lang);
        if (regionNode) regionNode.textContent = regionLabel;
        if (timezoneNode) timezoneNode.textContent = clientGeoContext.timezone || '--';
        if (networkNode) networkNode.textContent = displayNetwork;
        if (languageNode) languageNode.textContent = currentLanguageLabel(lang);
        if (languageBadge) languageBadge.textContent = String(lang || 'en').toUpperCase();
        if (tagsNode) tagsNode.innerHTML = tags.join('');
        card.classList.remove('is-empty');
        setTimeout(function() {
          queueHeroGlobeResize();
        }, 40);
      }

      function renderHeroMapBadge() {
        var lang = document.documentElement.lang || 'en';
        var node = document.getElementById('hero-map-badge');
        if (!node) {
          return;
        }
        if (!clientGeoContext || !clientGeoContext.available) {
          node.innerHTML = '<strong>' + escapeHtml(geoText('map_global', lang)) + '</strong>';
          return;
        }
        var place = clientGeoContext.place_label || clientGeoContext.country || '--';
        var network = clientGeoContext.display_network || networkProfileLabel(clientGeoContext.network_profile, lang);
        node.innerHTML =
          '<strong>' + escapeHtml(place) + '</strong>' +
          '<span>' + escapeHtml(geoText('map_network', lang)) + ': ' + escapeHtml(network) + '</span>';
      }

      function animateNumberNode(node, target) {
        if (!node) return;
        var numericTarget = Number(target);
        if (!Number.isFinite(numericTarget)) {
          node.textContent = String(target);
          return;
        }
        var start = Number(String(node.textContent || '').replace(/[^\d.-]/g, ''));
        if (!Number.isFinite(start)) start = 0;
        var delta = numericTarget - start;
        if (Math.abs(delta) < 0.5) {
          node.textContent = formatNumber(numericTarget);
          return;
        }
        var duration = 550;
        var begin = null;
        function frame(timestamp) {
          if (begin === null) begin = timestamp;
          var t = Math.min(1, (timestamp - begin) / duration);
          var eased = 1 - Math.pow(1 - t, 3);
          var current = start + delta * eased;
          node.textContent = formatNumber(Math.round(current));
          if (t < 1) {
            requestAnimationFrame(frame);
          }
        }
        requestAnimationFrame(frame);
      }

      function updateThreatStream(payload) {
        if (!threatStreamNode) return;
        var lang = document.documentElement.lang || 'en';
        var recent = Array.isArray(payload && payload.recent_domains) ? payload.recent_domains : [];
        var geoPoints = Array.isArray(payload && payload.geo_points) ? payload.geo_points : [];
        var stats = payload && payload.stats ? payload.stats : {};
        var alerts = Number(stats.alerts_24h || 0);
        var blocks = Number(stats.blocks_24h || 0);
        var domains = recent.slice(0, 6).map(function(entry) {
          return entry && entry.domain ? String(entry.domain) : '';
        }).filter(Boolean);
        var parts = [];
        if (alerts <= 0 && blocks <= 0 && domains.length === 0 && geoPoints.length === 0) {
          parts.push(uiText('threat_no_data', lang));
        } else {
          if (clientGeoContext && clientGeoContext.available) {
            parts.push(geoText('stream_region', lang) + ' ' + (clientGeoContext.place_label || clientGeoContext.country || '--'));
            parts.push(geoText('stream_network', lang) + ' ' + networkProfileLabel(clientGeoContext.network_profile, lang));
          }
          parts.push(uiText('threat_alerts_24h', lang) + ' ' + formatNumber(alerts));
          parts.push(uiText('threat_blocks_24h', lang) + ' ' + formatNumber(blocks));
          geoPoints.slice(0, 3).forEach(function(point) {
            var code = point && point.country_code ? String(point.country_code) : '';
            var hits = Number(point && point.hits ? point.hits : 0);
            if (!code) return;
            parts.push(code + ' ' + formatNumber(hits));
          });
          domains.forEach(function(domain) {
            parts.push(uiText('threat_domain', lang) + ' ' + domain);
          });
        }
        var line = parts.join('  //  ');
        threatStreamNode.textContent = line + '  //  ' + line;
      }

      function getHeroMapGeoPoints(payload) {
        function normalizePoints(source, kind) {
          var list = Array.isArray(source) ? source : [];
          return list.map(function(entry) {
            var lat = Number(entry && entry.lat ? entry.lat : 0);
            var lon = Number(entry && entry.lon ? entry.lon : 0);
            var hits = Number(entry && entry.hits ? entry.hits : 0);
            var countryCode = entry && entry.country_code ? String(entry.country_code).toUpperCase().slice(0, 2) : '';
            var countryName = entry && entry.country_name ? String(entry.country_name) : countryCode;
            var hostname = entry && entry.hostname ? String(entry.hostname) : '';
            return {
              kind: kind,
              lat: lat,
              lon: lon,
              hits: Number.isFinite(hits) ? Math.max(1, hits) : 1,
              country_code: countryCode,
              country_name: countryName,
              hostname: hostname
            };
          }).filter(function(point) {
            return Number.isFinite(point.lat) &&
              Number.isFinite(point.lon) &&
              Math.abs(point.lat) <= 90 &&
              Math.abs(point.lon) <= 180 &&
              !(Math.abs(point.lat) < 0.01 && Math.abs(point.lon) < 0.01);
          });
        }

        function applyPerCountryLimit(points, enabled, limit) {
          if (!enabled) {
            return points;
          }
          var seen = Object.create(null);
          return points.filter(function(point) {
            var code = point && point.country_code ? String(point.country_code).toUpperCase().slice(0, 2) : '__UNK__';
            seen[code] = (seen[code] || 0) + 1;
            return seen[code] <= limit;
          });
        }

        var alertPoints = normalizePoints(
          (payload && (payload.geo_points_alerts || payload.geo_points)) || [],
          'alert'
        );
        var domainPoints = normalizePoints(
          (payload && payload.geo_points_domains) || [],
          'domain'
        );
        var settings = payload && payload.map_settings ? payload.map_settings : {};
        var limitEnabled = !!settings.limit_points_per_country;
        var perCountryLimit = Math.max(1, Math.min(12, Number(settings.max_points_per_country || 2) || 2));
        var combined = alertPoints.slice(0, 26).concat(domainPoints.slice(0, 24));
        return applyPerCountryLimit(combined, limitEnabled, perCountryLimit);
      }

      function loadHeroMapWorldGeoJson() {
        if (heroWorldData && Array.isArray(heroWorldData.features) && heroWorldData.features.length) {
          return Promise.resolve(heroWorldData);
        }
        if (heroGeoJsonPromise) {
          return heroGeoJsonPromise;
        }
        var urls = [
          'assets/vendor/leaflet/data/world-countries.geo.json',
          './assets/vendor/leaflet/data/world-countries.geo.json',
          '/assets/vendor/leaflet/data/world-countries.geo.json'
        ];
        heroGeoJsonPromise = (async function() {
          for (var i = 0; i < urls.length; i += 1) {
            try {
              var response = await fetch(urls[i], { cache: 'force-cache' });
              if (!response.ok) {
                continue;
              }
              var data = await response.json();
              if (data && (data.type === 'FeatureCollection' || Array.isArray(data.features))) {
                return data;
              }
            } catch (error) {
              // Try next URL.
            }
          }
          return null;
        })();
        return heroGeoJsonPromise;
      }

      function renderHeroViewerMarker() {
        if (!clientGeoContext || !clientGeoContext.available) {
          heroMapViewerPoint = null;
          queueHeroGlobeResize();
          return;
        }
        var lat = Number(clientGeoContext.lat);
        var lon = Number(clientGeoContext.lon);
        if (!Number.isFinite(lat) || !Number.isFinite(lon)) {
          heroMapViewerPoint = null;
          queueHeroGlobeResize();
          return;
        }
        heroMapViewerPoint = {
          lat: lat,
          lon: lon,
          hits: 1,
          size: 5,
          kind: 'viewer'
        };
        queueHeroGlobeResize();
      }

      function renderHeroMapPoints(payload) {
        heroMapPoints = getHeroMapGeoPoints(payload).map(function(point, index) {
          return {
            lat: point.lat + (point.kind === 'domain' ? ((index % 3) - 1) * 0.28 : 0),
            lon: point.lon + (point.kind === 'domain' ? ((index % 4) - 1.5) * 0.28 : 0),
            hits: point.hits,
            kind: point.kind,
            country_code: point.country_code,
            country_name: point.country_name,
            hostname: point.hostname,
            size: Math.max(6, Math.min(13, Math.round(5 + Math.log(point.hits + 1) * 2.05)))
          };
        });
        heroMapConnections = heroBuildConnections(heroMapPoints);
        if (!heroMapHasFocusedPoints && heroMapPoints.length) {
          heroFocusOnPoints(heroMapPoints);
          heroMapHasFocusedPoints = true;
        }
        queueHeroGlobeResize();
      }

      function initMonitoringMap(payload) {
        renderHeroMapPoints(payload || {});
        renderHeroViewerMarker();
        renderHeroMapBadge();
        if (heroMapStarted || !mapContainer || !heroCanvasCtx) {
          return;
        }
        heroMapStarted = true;
        var focus = getViewerMapFocus();
        heroMapOrbitLon = focus.lon;
        heroMapOrbitLatBase = focus.lat;
        heroBindInteractions();
        queueHeroGlobeResize();
        ensureHeroOrbitTimer();
        if (!heroMapAnimationFrame) {
          heroMapAnimationFrame = window.requestAnimationFrame(heroAnimateGlobeFrame);
        }
        loadHeroMapWorldGeoJson().then(function(data) {
          heroWorldData = data;
          queueHeroGlobeResize();
        }).catch(function() {
          // Keep globe running even if the land dataset is unavailable.
        });
      }

      function updatePreviewMetrics(stats) {
        if (!stats) return;
        var nodes = document.querySelectorAll('[data-preview-metric]');
        nodes.forEach(function(node) {
          var key = node.getAttribute('data-preview-metric');
          if (!key || !(key in stats)) return;
          var value = stats[key];
          if (key === 'block_rate') {
            node.textContent = formatPercent(value);
          } else if (key === 'last_update') {
            node.textContent = formatDate(value);
          } else {
            animateNumberNode(node, value);
          }
        });
      }

      function updateTelemetry(stats) {
        if (!stats) return;
        var telemetryNodes = document.querySelectorAll('[data-telemetry-value]');
        telemetryNodes.forEach(function(node) {
          var key = node.getAttribute('data-telemetry-value');
          if (!key || !(key in stats)) return;
          var value = stats[key];
          if (value === null || value === undefined) return;
          animateNumberNode(node, value);
        });
      }

      function renderSparkline(container, series) {
        if (!container || !series || !Array.isArray(series.values)) return;
        var values = series.values.slice(-14);
        if (!values.length) return;
        var max = Math.max.apply(null, values);
        var min = Math.min.apply(null, values);
        var width = 260;
        var height = 70;
        var span = max - min || 1;
        var chartId = 'spark-' + String(++previewChartSequence);
        var points = values.map(function(value, index) {
          var x = (index / (values.length - 1 || 1)) * (width - 12) + 6;
          var y = height - 6 - ((value - min) / span) * (height - 16);
          return x.toFixed(1) + ',' + y.toFixed(1);
        }).join(' ');
        var fillPoints = '6,' + (height - 6) + ' ' + points + ' ' + (width - 6) + ',' + (height - 6);
        container.innerHTML = '<svg viewBox="0 0 ' + width + ' ' + height + '" preserveAspectRatio="none">' +
          '<defs>' +
            '<linearGradient id="' + chartId + '-fill" x1="0%" y1="0%" x2="0%" y2="100%">' +
              '<stop offset="0%" stop-color="#18e5ff" stop-opacity="0.28" />' +
              '<stop offset="100%" stop-color="#18e5ff" stop-opacity="0.02" />' +
            '</linearGradient>' +
          '</defs>' +
          '<polygon class="sparkline-area" points="' + fillPoints + '" fill="url(#' + chartId + '-fill)" />' +
          '<polyline class="sparkline-line" points="' + points + '" fill="none" stroke="rgba(24,229,255,0.82)" stroke-width="2.15" stroke-linecap="round" stroke-linejoin="round" />' +
          '<polyline class="sparkline-line-glow" points="' + points + '" fill="none" stroke="rgba(137,247,215,0.72)" stroke-width="1.15" stroke-linecap="round" stroke-linejoin="round" stroke-dasharray="18 12" />' +
          '</svg>';
        container.classList.remove('is-refreshing');
        void container.offsetWidth;
        container.classList.add('is-refreshing');
      }

      function updateChartValue(key, series) {
        var node = document.querySelector('[data-preview-chart-value="' + key + '"]');
        if (!node || !series || !Array.isArray(series.values)) return;
        var values = series.values.slice(-7);
        var latest = values.length ? values[values.length - 1] : 0;
        node.textContent = formatNumber(latest);
      }

      function renderActivityBars(alertSeries, blockSeries) {
        var lang = document.documentElement.lang || 'en';
        var container = document.querySelector('[data-preview-chart="activityBars"]');
        var valueNode = document.querySelector('[data-preview-chart-value="activityBars"]');
        var footNode = document.querySelector('[data-preview-chart-foot="activityBars"]');
        if (!container || !alertSeries || !Array.isArray(alertSeries.values)) return;

        var labels = Array.isArray(alertSeries.labels) ? alertSeries.labels.slice(-7) : [];
        var alerts = alertSeries.values.slice(-7).map(function(value) {
          var parsed = Number(value);
          return Number.isFinite(parsed) ? Math.max(0, parsed) : 0;
        });
        var blocks = (blockSeries && Array.isArray(blockSeries.values) ? blockSeries.values : []).slice(-7).map(function(value) {
          var parsed = Number(value);
          return Number.isFinite(parsed) ? Math.max(0, parsed) : 0;
        });

        if (!alerts.length) {
          container.innerHTML = '';
          if (valueNode) valueNode.textContent = '--';
          if (footNode) footNode.textContent = uiText('chart_activity_empty', lang);
          return;
        }

        var maxValue = 1;
        alerts.forEach(function(value) { maxValue = Math.max(maxValue, value); });
        blocks.forEach(function(value) { maxValue = Math.max(maxValue, value); });

        container.innerHTML = '';
        var totalAlerts = 0;
        var totalBlocks = 0;

        alerts.forEach(function(alertValue, index) {
          var blockValue = blocks[index] || 0;
          totalAlerts += alertValue;
          totalBlocks += blockValue;

          var row = document.createElement('div');
          row.className = 'activity-bar-row';

          var day = document.createElement('span');
          day.className = 'activity-day';
          day.textContent = compactLabel(labels[index] || ('d' + String(index + 1)));

          var track = document.createElement('div');
          track.className = 'activity-track';
          var alertBar = document.createElement('span');
          alertBar.className = 'activity-alert';
          var alertScale = Math.max(0, Math.min(1, alertValue / maxValue));
          var blockScale = Math.max(0, Math.min(1, blockValue / maxValue));
          alertBar.style.setProperty('--scale', '0');
          var blockBar = document.createElement('span');
          blockBar.className = 'activity-block';
          blockBar.style.setProperty('--scale', '0');
          track.appendChild(alertBar);
          track.appendChild(blockBar);

          var count = document.createElement('span');
          count.className = 'activity-count';
          count.textContent = uiText('activity_abbr_alert', lang) + ' ' + formatNumber(alertValue) + ' / ' + uiText('activity_abbr_block', lang) + ' ' + formatNumber(blockValue);

          row.appendChild(day);
          row.appendChild(track);
          row.appendChild(count);
          container.appendChild(row);

          window.requestAnimationFrame(function() {
            alertBar.style.setProperty('--scale', alertScale.toFixed(4));
            blockBar.style.setProperty('--scale', blockScale.toFixed(4));
          });
        });

        if (valueNode) {
          valueNode.textContent = formatNumber(totalBlocks) + '/' + formatNumber(totalAlerts);
        }
        if (footNode) {
          if (totalAlerts > 0) {
            footNode.textContent = uiText('chart_block_rate_7d', lang) + ': ' + formatPercent((totalBlocks / totalAlerts) * 100);
          } else {
            footNode.textContent = uiText('chart_activity_empty', lang);
          }
        }
      }

      function renderHeatMatrix(alertSeries, blockSeries) {
        var lang = document.documentElement.lang || 'en';
        var container = document.querySelector('[data-preview-chart="heatMatrix"]');
        var valueNode = document.querySelector('[data-preview-chart-value="heatMatrix"]');
        var footNode = document.querySelector('[data-preview-chart-foot="heatMatrix"]');
        if (!container || !alertSeries || !Array.isArray(alertSeries.values)) return;

        var labels = Array.isArray(alertSeries.labels) ? alertSeries.labels.slice(-7) : [];
        var alerts = alertSeries.values.slice(-7).map(function(value) {
          var parsed = Number(value);
          return Number.isFinite(parsed) ? Math.max(0, parsed) : 0;
        });
        var blocks = (blockSeries && Array.isArray(blockSeries.values) ? blockSeries.values : []).slice(-7).map(function(value) {
          var parsed = Number(value);
          return Number.isFinite(parsed) ? Math.max(0, parsed) : 0;
        });

        if (!alerts.length) {
          container.innerHTML = '';
          if (valueNode) valueNode.textContent = '--';
          if (footNode) footNode.textContent = uiText('chart_heat_empty', lang);
          return;
        }

        var maxAlert = Math.max.apply(null, alerts.concat([1]));
        var maxBlock = Math.max.apply(null, blocks.concat([1]));
        container.innerHTML = '';

        alerts.forEach(function(alertValue, index) {
          var day = compactLabel(labels[index] || ('d' + String(index + 1)));
          var blockValue = blocks[index] || 0;
          var cell = document.createElement('div');
          cell.className = 'heat-cell';
          var alertIntensity = (0.08 + (alertValue / maxAlert) * 0.88).toFixed(3);
          var blockIntensity = (0.05 + (blockValue / maxBlock) * 0.92).toFixed(3);
          cell.style.setProperty('--a', '0.04');
          cell.style.setProperty('--b', '0.03');
          cell.style.setProperty('--index', String(index));
          cell.setAttribute('data-label', day);
          cell.title = uiText('activity_abbr_alert', lang) + ' ' + formatNumber(alertValue) + ' / ' + uiText('activity_abbr_block', lang) + ' ' + formatNumber(blockValue);
          container.appendChild(cell);
          (function(targetCell, aValue, bValue) {
            window.requestAnimationFrame(function() {
              targetCell.style.setProperty('--a', aValue);
              targetCell.style.setProperty('--b', bValue);
              targetCell.classList.add('is-live');
            });
          })(cell, alertIntensity, blockIntensity);
        });

        var totalA = alerts.reduce(function(sum, value) { return sum + value; }, 0);
        var totalB = blocks.reduce(function(sum, value) { return sum + value; }, 0);
        if (valueNode) {
          valueNode.textContent = formatNumber(totalA) + ' / ' + formatNumber(totalB);
        }
        if (footNode) {
          footNode.textContent = uiText('chart_heat_footnote', lang);
        }
      }

      function renderFeaturedCaseCharts() {
        document.querySelectorAll('[data-featured-chart]').forEach(function(canvasNode) {
          if (!canvasNode || typeof canvasNode.getContext !== 'function') {
            return;
          }
          var payloadRaw = canvasNode.getAttribute('data-featured-chart') || '{}';
          var payload = {};
          try {
            payload = JSON.parse(payloadRaw);
          } catch (error) {
            payload = {};
          }
          var values = Array.isArray(payload.values) ? payload.values.map(function(value) {
            var parsed = Number(value);
            return Number.isFinite(parsed) ? Math.max(0, Math.min(100, parsed)) : 0;
          }) : [];
          var labels = Array.isArray(payload.keys) ? payload.keys.map(function(key) {
            return uiText(key, document.documentElement.lang || 'en') || String(key || '');
          }) : (Array.isArray(payload.labels) ? payload.labels : []);
          if (!values.length) {
            return;
          }
          var ctx = canvasNode.getContext('2d');
          var width = canvasNode.width;
          var height = canvasNode.height;
          var cx = width / 2;
          var cy = height / 2 + 2;
          var radius = Math.min(width, height) * 0.28;
          ctx.clearRect(0, 0, width, height);

          for (var ring = 1; ring <= 4; ring += 1) {
            var ringRadius = radius * (ring / 4);
            ctx.beginPath();
            for (var i = 0; i < values.length; i += 1) {
              var angle = (-Math.PI / 2) + (Math.PI * 2 * i / values.length);
              var x = cx + Math.cos(angle) * ringRadius;
              var y = cy + Math.sin(angle) * ringRadius;
              if (i === 0) {
                ctx.moveTo(x, y);
              } else {
                ctx.lineTo(x, y);
              }
            }
            ctx.closePath();
            ctx.strokeStyle = 'rgba(128, 171, 197, 0.16)';
            ctx.lineWidth = 1;
            ctx.stroke();
          }

          values.forEach(function(_, index) {
            var angle = (-Math.PI / 2) + (Math.PI * 2 * index / values.length);
            ctx.beginPath();
            ctx.moveTo(cx, cy);
            ctx.lineTo(cx + Math.cos(angle) * radius, cy + Math.sin(angle) * radius);
            ctx.strokeStyle = 'rgba(128, 171, 197, 0.14)';
            ctx.lineWidth = 1;
            ctx.stroke();
          });

          ctx.beginPath();
          values.forEach(function(value, index) {
            var angle = (-Math.PI / 2) + (Math.PI * 2 * index / values.length);
            var pointRadius = radius * (value / 100);
            var x = cx + Math.cos(angle) * pointRadius;
            var y = cy + Math.sin(angle) * pointRadius;
            if (index === 0) {
              ctx.moveTo(x, y);
            } else {
              ctx.lineTo(x, y);
            }
          });
          ctx.closePath();
          var fillGradient = ctx.createLinearGradient(0, 18, width, height);
          fillGradient.addColorStop(0, 'rgba(63, 190, 247, 0.34)');
          fillGradient.addColorStop(1, 'rgba(100, 245, 184, 0.18)');
          ctx.fillStyle = fillGradient;
          ctx.strokeStyle = 'rgba(135, 227, 255, 0.9)';
          ctx.lineWidth = 2;
          ctx.fill();
          ctx.stroke();

          values.forEach(function(value, index) {
            var angle = (-Math.PI / 2) + (Math.PI * 2 * index / values.length);
            var pointRadius = radius * (value / 100);
            var x = cx + Math.cos(angle) * pointRadius;
            var y = cy + Math.sin(angle) * pointRadius;
            ctx.beginPath();
            ctx.arc(x, y, 4, 0, Math.PI * 2);
            ctx.fillStyle = '#eaf7ff';
            ctx.shadowBlur = 12;
            ctx.shadowColor = 'rgba(85, 210, 255, 0.42)';
            ctx.fill();
            ctx.shadowBlur = 0;
          });

          ctx.fillStyle = 'rgba(204, 224, 236, 0.76)';
          ctx.font = '10px "JetBrains Mono", monospace';
          ctx.textAlign = 'center';
          values.forEach(function(_, index) {
            var label = String(labels[index] || '');
            var angle = (-Math.PI / 2) + (Math.PI * 2 * index / values.length);
            var labelRadius = radius + 24;
            ctx.fillText(label, cx + Math.cos(angle) * labelRadius, cy + Math.sin(angle) * labelRadius);
          });
        });
      }

      function updateCharts(charts) {
        if (!charts) return;
        ['daily', 'dailyBlocks'].forEach(function(key) {
          var container = document.querySelector('[data-preview-chart="' + key + '"]');
          if (!container || !charts[key]) return;
          renderSparkline(container, charts[key]);
          updateChartValue(key, charts[key]);
        });
        renderActivityBars(charts.daily || null, charts.dailyBlocks || null);
        renderHeatMatrix(charts.daily || null, charts.dailyBlocks || null);
      }

      function updateRecent(domains) {
        var list = document.querySelector('[data-preview-recent]');
        var empty = document.querySelector('[data-preview-empty]');
        if (!list) return;
        list.innerHTML = '';
        if (!Array.isArray(domains) || domains.length === 0) {
          if (empty) empty.style.display = 'block';
          return;
        }
        if (empty) empty.style.display = 'none';
        domains.slice(0, 4).forEach(function(entry) {
          var row = document.createElement('div');
          row.className = 'recent-item';
          var domain = (entry && entry.domain) ? entry.domain : uiText('recent_unknown');
          var dateValue = entry && entry.date ? formatDate(entry.date) : '--';
          var domainNode = document.createElement('strong');
          domainNode.textContent = domain;
          var dateNode = document.createElement('span');
          dateNode.textContent = dateValue;
          row.appendChild(domainNode);
          row.appendChild(dateNode);
          list.appendChild(row);
        });
      }

      function applyPreviewPayload(payload) {
        if (!payload || typeof payload !== 'object') return;
        previewPayload = payload;
        updatePreviewMetrics(payload.stats || {});
        updateTelemetry(payload.stats || {});
        updateCharts(payload.charts || {});
        updateRecent(payload.recent_domains || []);
        updateThreatStream(payload);
        initMonitoringMap(payload);
      }

      function refreshPreviewLocale() {
        renderGeoContextCard();
        renderHeroMapBadge();
        renderHeroViewerMarker();
        if (!previewPayload) return;
        updatePreviewMetrics(previewPayload.stats || {});
        updateTelemetry(previewPayload.stats || {});
        updateCharts(previewPayload.charts || {});
        updateRecent(previewPayload.recent_domains || []);
        updateThreatStream(previewPayload);
      }

      runIdle(function() {
        initMonitoringMap(previewPayload || {});
      }, 1200);

      function schedulePreviewRefresh(delay) {
        if (previewRefreshTimer) {
          window.clearTimeout(previewRefreshTimer);
        }
        previewRefreshTimer = window.setTimeout(fetchPreviewData, delay);
      }

      function fetchPreviewData() {
        if (previewFetchInFlight) {
          return Promise.resolve(null);
        }
        previewFetchInFlight = true;
        return fetch('dashboard.php?public=1&format=live', { cache: 'no-store' })
          .then(function(response) {
            if (!response.ok) throw new Error('Preview fetch failed');
            return response.json();
          })
          .then(function(payload) {
            applyPreviewPayload(payload);
            return payload;
          })
          .catch(function() {
            return null;
          })
          .finally(function() {
            previewFetchInFlight = false;
            schedulePreviewRefresh(document.hidden ? 18000 : 10000);
          });
      }

      document.addEventListener('visibilitychange', function() {
        if (!document.hidden) {
          fetchPreviewData();
        }
      });

      runIdle(function() {
        fetchPreviewData();
      }, 1600);
    })();
  </script>
  <?php if ($indexShowMonetizationAds): ?>
    <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=<?= urlencode((string) $monetization['adsense_client']); ?>" crossorigin="anonymous"></script>
    <script>
      (adsbygoogle = window.adsbygoogle || []).push({});
    </script>
  <?php endif; ?>
</body>
</html>
