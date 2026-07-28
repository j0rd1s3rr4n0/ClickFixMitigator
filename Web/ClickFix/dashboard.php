<?php
declare(strict_types=1);

require_once __DIR__ . '/src/clickfix_core.php';
clickfix_bootstrap();
header('Cache-Control: no-store, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Content-Type: text/html; charset=utf-8');
if ((string) ($_GET['debug'] ?? '') === '1') {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
}

function clickfix_dashboard_fatal(string $message, string $details = ''): void
{
    http_response_code(500);
    $safeMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    $safeDetails = htmlspecialchars($details, ENT_QUOTES, 'UTF-8');
    echo <<<HTML
<!doctype html>
<html lang="<?= clickfix_h($lang); ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>ClickFix - Error de despliegue</title>
  <style>
    body{margin:0;background:#08131d;color:#e6f4ff;font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif}
    .wrap{max-width:none;width:100%;margin:16px auto;padding:0 14px}
    .card{border:1px solid #3f83a955;border-radius:12px;background:#0d2231;padding:18px}
    h1{margin:0 0 10px;font-size:1.3rem}
    p{margin:0 0 8px;color:#b7d3e2}
    pre{margin:8px 0 0;padding:8px;border-radius:8px;background:#07141f;border:1px solid #3f83a944;overflow:auto;white-space:pre-wrap}
    code{background:#113247;padding:2px 6px;border-radius:6px}
    ul{margin:10px 0 0 18px;padding:0}
    li{margin:6px 0}
  </style>
</head>
<body>
  <main class="wrap">
    <section class="card">
      <h1>No se pudo iniciar ClickFix Dashboard</h1>
      <p>{$safeMessage}</p>
      <ul>
        <li>Ejecuta migracion una vez en servidor: <code>php scripts/migrate.php</code></li>
        <li>Da permisos de escritura a <code>data/</code>, <code>data/sessions/</code> y <code>clickfix.sqlite</code> para el usuario de Apache.</li>
        <li>Si despliegas en subruta, revisa mayusculas/minusculas exactas y que extension + backend usen el mismo prefijo.</li>
      </ul>
      <pre>{$safeDetails}</pre>
    </section>
  </main>
</body>
</html>
HTML;
    exit;
}

function clickfix_dashboard_redact_sensitive(string $input): string
{
    if ($input === '') {
        return '';
    }

    $value = $input;
    $value = preg_replace('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/iu', '[REDACTED_EMAIL]', $value) ?? $value;
    $value = preg_replace(
        '/((?:["\']?\b(?:password|passwd|pwd|pass|contraseÃ±a|contrase(?:n|Ã±)a|clave)\b["\']?)\s*[:=]\s*)(?:"[^"]*"|\'[^\']*\'|[^\s,;|]+)/iu',
        '$1[REDACTED_PASSWORD]',
        $value
    ) ?? $value;
    $value = preg_replace(
        '/((?:["\']?\b(?:username|usuario|user|login|nick|nickname)\b["\']?)\s*[:=]\s*)(?:"[^"]*"|\'[^\']*\'|[^\s,;|]+)/iu',
        '$1[REDACTED_USERNAME]',
        $value
    ) ?? $value;
    $value = preg_replace('/(?<![A-Za-z0-9])(?!\d{4}-\d{2}-\d{2})(?:\+?\d[\d().\s-]{7,}\d)(?![A-Za-z0-9])/u', '[REDACTED_PHONE]', $value) ?? $value;

    return $value;
}

function clickfix_dashboard_public_metrics(array $metrics): array
{
    $publicMetrics = $metrics;
    unset($publicMetrics['unique_users'], $publicMetrics['active_extension_clients_24h']);
    $publicMetrics['countries_count'] = (int) ($publicMetrics['countries_count'] ?? 0);
    $publicMetrics['pending_domains_outside_lists'] = (int) ($publicMetrics['pending_domains_outside_lists'] ?? 0);
    $publicMetrics['block_rate'] = (float) ($publicMetrics['block_rate_24h'] ?? 0.0);

    return $publicMetrics;
}

function clickfix_dashboard_geo_hint(): array
{
    $country = strtoupper(trim((string) ($_SERVER['HTTP_CF_IPCOUNTRY'] ?? '')));
    if ($country === '' || $country === 'XX') {
        $country = strtoupper(trim((string) ($_SERVER['HTTP_X_COUNTRY_CODE'] ?? '')));
    }
    if ($country === '') {
        $country = strtoupper(trim((string) ($_SERVER['HTTP_GEOIP_COUNTRY_CODE'] ?? '')));
    }
    if ($country === '') {
        $country = strtoupper(trim((string) ($_SERVER['HTTP_CLOUDFRONT_VIEWER_COUNTRY'] ?? '')));
    }
    $region = strtoupper(trim((string) ($_SERVER['HTTP_CF_REGION'] ?? $_SERVER['HTTP_X_REGION'] ?? $_SERVER['HTTP_GEOIP_REGION'] ?? '')));
    $regionName = strtolower(trim((string) ($_SERVER['HTTP_CF_REGION_NAME'] ?? $_SERVER['HTTP_X_REGION_NAME'] ?? $_SERVER['HTTP_GEOIP_REGION_NAME'] ?? '')));
    if ($country === '' && isset($_SESSION['clickfix_geo_context_lookup']) && is_array($_SESSION['clickfix_geo_context_lookup'])) {
        $cached = $_SESSION['clickfix_geo_context_lookup'];
        $country = strtoupper(trim((string) ($cached['countryCode'] ?? '')));
        $region = strtoupper(trim((string) ($cached['region'] ?? '')));
        $regionName = strtolower(trim((string) ($cached['regionName'] ?? '')));
    }
    if ($country === '') {
        return [];
    }
    return [
        'countryCode' => $country,
        'region' => $region,
        'regionName' => $regionName,
    ];
}

function clickfix_dashboard_detect_geo_language(): string
{
    $geo = clickfix_dashboard_geo_hint();
    if (empty($geo)) {
        return '';
    }
    $countryCode = strtoupper(trim((string) ($geo['countryCode'] ?? '')));
    $region = strtoupper(trim((string) ($geo['region'] ?? '')));
    $regionName = strtolower(trim((string) ($geo['regionName'] ?? '')));
    if ($countryCode === 'ES' && ($region === 'CT' || str_contains($regionName, 'catal'))) {
        return 'ca';
    }
    if ($countryCode === 'ES') {
        return 'es';
    }
    if ($countryCode === 'FR') {
        return 'fr';
    }
    if ($countryCode === 'IT') {
        return 'it';
    }
    if ($countryCode === 'DE') {
        return 'de';
    }
    return '';
}

function clickfix_dashboard_detect_accept_language(): string
{
    $header = strtolower((string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''));
    if ($header === '') {
        return '';
    }
    foreach (explode(',', $header) as $part) {
        $code = trim(explode(';', $part)[0] ?? '');
        if ($code === '') {
            continue;
        }
        $base = substr($code, 0, 2);
        if (in_array($base, ['es', 'ca', 'fr', 'de', 'it', 'en'], true)) {
            return $base;
        }
    }
    return '';
}

try {
    $pdo = clickfix_open_db(true);
} catch (Throwable $exception) {
    clickfix_dashboard_fatal(
        'Error al abrir la base de datos. Normalmente es permisos o ruta de despliegue.',
        $exception->getMessage()
    );
}
$user = clickfix_current_user();
$loggedIn = $user !== null;
$viewerRole = $loggedIn ? clickfix_normalize_role((string) ($user['role'] ?? 'analyst_jr')) : 'guest';
$viewerRoleLabel = $loggedIn ? clickfix_role_label($viewerRole) : 'Invitado';
$hasActiveSessionColumn = clickfix_has_column($pdo, 'users', 'active_session_id');
if ($loggedIn && $hasActiveSessionColumn) {
    $activeSessionStmt = $pdo->prepare('SELECT active_session_id FROM users WHERE id = :id LIMIT 1');
    $activeSessionStmt->execute([':id' => (int) ($user['id'] ?? 0)]);
    $activeSessionId = (string) ($activeSessionStmt->fetchColumn() ?: '');
    $currentSessionId = session_id();
    if ($activeSessionId !== '' && $currentSessionId !== '' && $activeSessionId !== $currentSessionId) {
        clickfix_flash('Tu SesiÃ³n ha sido cerrada porque se inicio en otro dispositivo.');
        unset($_SESSION['clickfix_user']);
        unset($_SESSION['clickfix_lang']);
        unset($_SESSION['clickfix_session_expires_at']);
        @session_regenerate_id(true);
        $loggedIn = false;
        $user = null;
        $viewerRole = 'guest';
        $viewerRoleLabel = 'Invitado';
    }
}
$redactSensitiveForViewer = $loggedIn
    && clickfix_user_has_min_role($user, 'analyst_jr')
    && !clickfix_user_has_min_role($user, 'analyst_sr');
$publicPages = ['home', 'search', 'about', 'coverage', 'access', 'investigation', 'profile', 'clickfix_domain_list'];
$pageAccess = [
    'settings' => 'analyst_jr',
    'ops' => 'analyst_jr',
    'analytics' => 'analyst_jr',
    'intel_stats' => 'analyst_jr',
    'intel' => 'analyst_jr',
    'community' => 'analyst_jr',
    'domain_feeds' => 'analyst_mid',
    'extensions' => 'analyst_sr',
    'lists' => 'analyst_sr',
    'requests' => 'analyst_sr',
    'messaging' => 'analyst_sr',
    'data_center' => 'analyst_sr',
    'configs' => 'admin',
    'reports' => 'admin',
    'users' => 'admin',
];
$privatePages = array_keys($pageAccess);
$allPages = array_merge($publicPages, $privatePages);
$langSupported = ['en', 'es', 'ca', 'de', 'fr', 'it'];
$langParam = strtolower(trim((string) ($_GET['lang'] ?? '')));
if (!in_array($langParam, $langSupported, true)) {
    $langParam = '';
}
if ($langParam !== '') {
    $_SESSION['clickfix_lang'] = $langParam;
}
$lang = strtolower(trim((string) ($_SESSION['clickfix_lang'] ?? '')));
if (!in_array($lang, $langSupported, true)) {
    $lang = '';
}
if ($lang === '' && $loggedIn) {
    $lang = clickfix_normalize_user_language((string) ($user['preferred_lang'] ?? 'en'));
    if (!in_array($lang, $langSupported, true)) {
        $lang = '';
    }
}
if ($lang === '') {
    $geoLang = clickfix_dashboard_detect_geo_language();
    if ($geoLang !== '' && in_array($geoLang, $langSupported, true)) {
        $lang = $geoLang;
    }
}
if ($lang === '') {
    $acceptLang = clickfix_dashboard_detect_accept_language();
    if ($acceptLang !== '' && in_array($acceptLang, $langSupported, true)) {
        $lang = $acceptLang;
    }
}
if ($lang === '') {
    $lang = 'en';
}
$_SESSION['clickfix_lang'] = $lang;
$page = strtolower(trim((string) ($_GET['page'] ?? 'home')));
if (!in_array($page, $allPages, true)) {
    $page = 'home';
}
$publicView = (string) ($_GET['public'] ?? '') === '1';
$focusReportId = (int) ($_GET['report_id'] ?? 0);
$postReturnPage = in_array($page, ['ops', 'home', 'search', 'analytics'], true) ? $page : 'ops';
if (in_array($page, $privatePages, true) && !$loggedIn) {
    $page = 'home';
}
if ($loggedIn && isset($pageAccess[$page]) && !clickfix_user_has_min_role($user, (string) $pageAccess[$page])) {
    clickfix_flash('Tu rol no tiene permisos para esa seccion.');
    $page = 'ops';
}
if ($loggedIn && $page === 'access') {
    $page = 'ops';
}

$sessionIdleMinutes = max(10, min(240, (int) clickfix_env('CLICKFIX_SESSION_IDLE_MINUTES', '60')));
$sessionExtendMinutes = max(10, min(240, (int) clickfix_env('CLICKFIX_SESSION_EXTEND_MINUTES', '30')));
$sessionWarningMinutes = max(2, min(30, (int) clickfix_env('CLICKFIX_SESSION_WARNING_MINUTES', '5')));
$sessionExpiresAt = 0;
if ($loggedIn) {
    if (!isset($_SESSION['clickfix_session_expires_at'])) {
        $_SESSION['clickfix_session_expires_at'] = time() + ($sessionIdleMinutes * 60);
    }
    $sessionExpiresAt = (int) ($_SESSION['clickfix_session_expires_at'] ?? 0);
    if ($sessionExpiresAt > 0 && time() > $sessionExpiresAt) {
        $logoutUser = clickfix_current_user();
        if ($logoutUser !== null) {
            if ($hasActiveSessionColumn) {
                $pdo->prepare('UPDATE users SET active_session_id = NULL, active_session_updated_at = :at WHERE id = :id AND active_session_id = :sid')
                    ->execute([
                        ':at' => gmdate('c'),
                        ':id' => (int) ($logoutUser['id'] ?? 0),
                        ':sid' => session_id(),
                    ]);
            }
            clickfix_log_user_session_event(
                $pdo,
                (int) ($logoutUser['id'] ?? 0),
                (string) ($logoutUser['username'] ?? ''),
                'logout',
                clickfix_client_ip(),
                substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 240),
                session_id()
            );
        }
        unset($_SESSION['clickfix_user']);
        unset($_SESSION['clickfix_lang']);
        unset($_SESSION['clickfix_session_expires_at']);
        @session_regenerate_id(true);
        clickfix_flash('SesiÃ³n expirada por inactividad.');
        clickfix_redirect('dashboard.php?page=access&public=1');
    }
}

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
    $action = trim((string) ($_POST['action'] ?? ''));
    $csrf = (string) ($_POST['csrf_token'] ?? '');
    if ($action === 'login') {
        $loginIp = clickfix_client_ip();
        if (!clickfix_api_rate_limit($pdo, 'login:ip:' . $loginIp, 30, 300)) {
            clickfix_flash('Demasiados intentos de login. Espera unos minutos.');
            clickfix_redirect('dashboard.php?page=access&public=1');
        }
        $auth = clickfix_authenticate($pdo, (string) ($_POST['username'] ?? ''), (string) ($_POST['password'] ?? ''));
        if ($auth) {
            @session_regenerate_id(true);
            $_SESSION['clickfix_user'] = $auth;
            $sessionLang = clickfix_normalize_user_language((string) ($auth['preferred_lang'] ?? 'en'));
            if (!in_array($sessionLang, $langSupported, true)) {
                $sessionLang = 'en';
            }
            $_SESSION['clickfix_lang'] = $sessionLang;
            $_SESSION['clickfix_session_expires_at'] = time() + ($sessionIdleMinutes * 60);
            if ($hasActiveSessionColumn) {
                $upd = $pdo->prepare('UPDATE users SET active_session_id = :sid, active_session_updated_at = :at WHERE id = :id');
                $upd->execute([
                    ':sid' => session_id(),
                    ':at' => gmdate('c'),
                    ':id' => (int) ($auth['id'] ?? 0),
                ]);
            }
            clickfix_log_user_session_event(
                $pdo,
                (int) ($auth['id'] ?? 0),
                (string) ($auth['username'] ?? ''),
                'login',
                $loginIp,
                (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''),
                session_id()
            );
            clickfix_flash('SesiÃ³n iniciada.');
            clickfix_redirect('dashboard.php?page=ops');
        }
        clickfix_flash('Credenciales incorrectas.');
        clickfix_redirect('dashboard.php?page=access&public=1');
    }
    if ($action === 'logout') {
        $logoutUser = clickfix_current_user();
        if ($logoutUser !== null) {
            if ($hasActiveSessionColumn) {
                $pdo->prepare('UPDATE users SET active_session_id = NULL, active_session_updated_at = :at WHERE id = :id AND active_session_id = :sid')
                    ->execute([
                        ':at' => gmdate('c'),
                        ':id' => (int) ($logoutUser['id'] ?? 0),
                        ':sid' => session_id(),
                    ]);
            }
            clickfix_log_user_session_event(
                $pdo,
                (int) ($logoutUser['id'] ?? 0),
                (string) ($logoutUser['username'] ?? ''),
                'logout',
                clickfix_client_ip(),
                (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''),
                session_id()
            );
        }
        unset($_SESSION['clickfix_user']);
        unset($_SESSION['clickfix_lang']);
        @session_regenerate_id(true);
        clickfix_flash('SesiÃ³n cerrada.');
        clickfix_redirect('dashboard.php?page=home&public=1');
    }
    if (!clickfix_verify_csrf($csrf)) {
        clickfix_flash('CSRF invÃ¡lido.');
        clickfix_redirect('dashboard.php?page=' . urlencode($page));
    }
    if ($action === 'session_extend') {
        if (!$loggedIn) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['status' => 'error', 'message' => 'auth_required']);
            exit;
        }
        $newExpires = time() + ($sessionExtendMinutes * 60);
        $_SESSION['clickfix_session_expires_at'] = $newExpires;
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'status' => 'ok',
            'expires_at' => $newExpires,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    if ($action === 'request_access') {
        $ok = clickfix_store_access_request(
            $pdo,
            (string) ($_POST['access_email'] ?? ''),
            (string) ($_POST['request_lang'] ?? 'en'),
            (string) ($_POST['access_linkedin'] ?? ''),
            (string) ($_POST['company_website'] ?? '')
        );
        clickfix_flash($ok ? 'Solicitud enviada.' : 'No se pudo enviar la solicitud. Revisa el email y vuelve a intentarlo.');
        clickfix_redirect('dashboard.php?page=access&public=1');
    }
    if ($action === 'submit_appeal') {
        clickfix_submit_appeal($pdo, (string) ($_POST['appeal_domain'] ?? ''), (string) ($_POST['appeal_reason'] ?? ''), (string) ($_POST['appeal_contact'] ?? ''));
        clickfix_flash('Desistimiento enviado.');
        clickfix_redirect('dashboard.php?page=access&public=1');
    }
    if (!$loggedIn) {
        clickfix_redirect('dashboard.php?page=home&public=1');
    }
    $actor = clickfix_current_user();
    $actorId = (int) ($actor['id'] ?? 0);
    $canReview = clickfix_user_has_min_role($actor, 'analyst_mid');
    $canManageLists = clickfix_user_has_min_role($actor, 'analyst_sr');
    $canManageRequests = clickfix_user_has_min_role($actor, 'analyst_sr');
    $canManageExtensionLinks = clickfix_user_has_min_role($actor, 'analyst_sr');
    $canManageMessaging = clickfix_user_has_min_role($actor, 'analyst_sr');
    $canAccessDataCenter = clickfix_user_has_min_role($actor, 'analyst_sr');
    $canManageUsers = clickfix_user_has_min_role($actor, 'admin');
    $canManageConfigs = clickfix_user_has_min_role($actor, 'admin');
    $canManageReports = clickfix_user_has_min_role($actor, 'admin');
    $canCommunityReviewMid = clickfix_user_has_min_role($actor, 'analyst_mid');
    $canCommunityReviewSenior = clickfix_user_has_min_role($actor, 'analyst_sr');
    $redactSensitiveForActor = clickfix_user_has_min_role($actor, 'analyst_jr')
        && !clickfix_user_has_min_role($actor, 'analyst_sr');
    $apiReturnPage = strtolower(trim((string) ($_POST['return_page'] ?? '')));
    if (!in_array($apiReturnPage, ['intel', 'settings'], true)) {
        $apiReturnPage = $page === 'settings' ? 'settings' : 'intel';
    }

    if ($action === 'user_self_update_lang') {
        $preferredLang = (string) ($_POST['self_lang'] ?? 'en');
        $ok = clickfix_user_update_preferences($pdo, $actorId, $preferredLang);
        if ($ok) {
            clickfix_user_reload_session($pdo, $actorId);
            $nextLang = clickfix_normalize_user_language($preferredLang);
            $_SESSION['clickfix_lang'] = in_array($nextLang, ['en', 'es', 'ca', 'de', 'fr'], true) ? $nextLang : 'en';
            clickfix_flash('Idioma por defecto actualizado.');
        } else {
            clickfix_flash('No se pudo actualizar el idioma por defecto.');
        }
        clickfix_redirect('dashboard.php?page=settings');
    }

    if ($action === 'user_self_update_account') {
        $preferredLang = (string) ($_POST['self_lang'] ?? 'en');
        $okLang = clickfix_user_update_preferences($pdo, $actorId, $preferredLang);
        $okProfile = clickfix_user_update_theme_avatar(
            $pdo,
            $actorId,
            (string) ($_POST['self_theme'] ?? 'default'),
            (string) ($_POST['self_avatar_url'] ?? '')
        );
        if ($okLang || $okProfile) {
            clickfix_user_reload_session($pdo, $actorId);
            $nextLang = clickfix_normalize_user_language($preferredLang);
            $_SESSION['clickfix_lang'] = in_array($nextLang, ['en', 'es', 'ca', 'de', 'fr'], true) ? $nextLang : 'en';
            clickfix_flash('Ajustes de cuenta actualizados.');
        } else {
            clickfix_flash('No se pudieron actualizar los ajustes de cuenta.');
        }
        clickfix_redirect('dashboard.php?page=settings');
    }

    if ($action === 'user_self_change_password') {
        $ok = clickfix_user_change_password(
            $pdo,
            $actorId,
            (string) ($_POST['self_current_password'] ?? ''),
            (string) ($_POST['self_new_password'] ?? '')
        );
        clickfix_flash($ok ? 'ContraseÃ±a actualizada.' : 'No se pudo cambiar la contraseÃ±a (revisa tu clave actual y el minimo de 10 caracteres).');
        clickfix_redirect('dashboard.php?page=settings');
    }


    if ($action === 'public_preview_settings_save') {
        if (!$canManageConfigs) {
            clickfix_flash('Permisos insuficientes: requiere Administrador.');
            clickfix_redirect('dashboard.php?page=settings');
        }
        $ok = clickfix_public_preview_settings_save($pdo, [
            'limit_points_per_country' => ((string) ($_POST['preview_limit_points_per_country'] ?? '0')) === '1',
            'max_points_per_country' => (int) ($_POST['preview_max_points_per_country'] ?? 2),
        ], $actorId);
        clickfix_flash($ok ? 'Mapa p?blico actualizado.' : 'No se pudo guardar la configuraci?n del mapa p?blico.');
        clickfix_redirect('dashboard.php?page=settings');
    }

    if ($action === 'user_self_profile_update') {
        $ok = clickfix_user_update_public_profile($pdo, $actorId, [
            'full_name' => (string) ($_POST['full_name'] ?? ''),
            'profile_email_public' => (string) ($_POST['profile_email_public'] ?? 0),
            'profile_threatrip_public' => (string) ($_POST['profile_threatrip_public'] ?? 0),
            'profile_threatrip_id' => (string) ($_POST['profile_threatrip_id'] ?? ''),
            'profile_vt_public' => (string) ($_POST['profile_vt_public'] ?? 0),
            'profile_vt_handle' => (string) ($_POST['profile_vt_handle'] ?? ''),
            'profile_abuseipdb_public' => (string) ($_POST['profile_abuseipdb_public'] ?? 0),
            'profile_abuseipdb_id' => (string) ($_POST['profile_abuseipdb_id'] ?? ''),
            'profile_github_public' => (string) ($_POST['profile_github_public'] ?? 0),
            'profile_github_handle' => (string) ($_POST['profile_github_handle'] ?? ''),
        ]);
        clickfix_flash($ok ? 'Perfil actualizado.' : 'No se pudo actualizar el perfil.');
        clickfix_redirect('dashboard.php?page=profile&user_id=' . $actorId);
    }

    if ($action === 'investigation_submit_community') {
        $graphId = (int) ($_POST['graph_id'] ?? 0);
        if ($graphId <= 0) {
            clickfix_flash('InvestigaciÃ³n no vÃ¡lida para enviar a comunidad.');
            clickfix_redirect('dashboard.php?page=intel');
        }
        $ok = clickfix_investigation_submit_community(
            $pdo,
            $graphId,
            $actorId,
            (string) ($actor['role'] ?? 'analyst_jr'),
            clickfix_is_admin()
        );
        clickfix_flash($ok ? 'InvestigaciÃ³n enviada a Community (fase JR).' : 'No se pudo enviar la investigaciÃ³n a Community.');
        clickfix_redirect('dashboard.php?page=community&graph_id=' . $graphId);
    }

    if ($action === 'investigation_workflow') {
        $graphId = (int) ($_POST['graph_id'] ?? 0);
        $nextStatus = clickfix_investigation_workflow_status((string) ($_POST['workflow_status'] ?? 'draft'));
        $note = (string) ($_POST['workflow_note'] ?? '');
        $communityRow = clickfix_get_investigation_any($pdo, $graphId);
        if ($communityRow === null || empty($communityRow['submitted_to_community'])) {
            clickfix_flash('Solo se pueden revisar investigaciones enviadas a Community.');
            clickfix_redirect('dashboard.php?page=community');
        }

        $midAllowed = ['mid_verified', 'sr_review'];
        $seniorAllowed = ['verified_public', 'verified_internal', 'rejected'];
        $allowed = false;
        if (in_array($nextStatus, $midAllowed, true) && $canCommunityReviewMid) {
            $allowed = true;
        }
        if (in_array($nextStatus, $seniorAllowed, true) && $canCommunityReviewSenior) {
            $allowed = true;
        }
        if (!$allowed) {
            clickfix_flash('Permisos insuficientes para este cambio de fase.');
            clickfix_redirect('dashboard.php?page=community&graph_id=' . $graphId);
        }

        $ok = clickfix_investigation_set_workflow($pdo, $graphId, $actorId, $nextStatus, $note);
        clickfix_flash($ok ? 'Workflow de Community actualizado.' : 'No se pudo actualizar el workflow de Community.');
        clickfix_redirect('dashboard.php?page=community&graph_id=' . $graphId);
    }

    if ($action === 'investigation_vote') {
        $graphId = (int) ($_POST['graph_id'] ?? 0);
        $vote = ((int) ($_POST['vote'] ?? 1)) > 0 ? 1 : -1;
        $communityRow = clickfix_get_investigation_any($pdo, $graphId);
        if ($communityRow === null || empty($communityRow['submitted_to_community'])) {
            clickfix_flash('Solo puedes puntuar investigaciones en Community.');
            clickfix_redirect('dashboard.php?page=community');
        }
        $ok = clickfix_investigation_vote($pdo, $graphId, $actorId, $vote);
        clickfix_flash($ok ? 'Voto registrado.' : 'No se pudo registrar tu voto.');
        clickfix_redirect('dashboard.php?page=community&graph_id=' . $graphId);
    }

    if ($action === 'report_quick_action') {
        $mode = strtolower(trim((string) ($_POST['quick_mode'] ?? '')));
        $reportId = (int) ($_POST['report_id'] ?? 0);
        if ($reportId <= 0) {
            clickfix_flash('No se pudo ejecutar la acciÃ³n: report_id invÃ¡lido.');
            clickfix_redirect('dashboard.php?page=' . urlencode($postReturnPage));
        }
        $report = clickfix_report_by_id($pdo, $reportId);
        if ($report === null) {
            clickfix_flash('No se encontrÃ³ la alerta para esa acciÃ³n rÃ¡pida.');
            clickfix_redirect('dashboard.php?page=' . urlencode($postReturnPage));
        }
        $domainCandidate = (string) ($report['hostname'] ?? '');
        if ($domainCandidate === '') {
            $domainCandidate = (string) ($report['url'] ?? '');
        }
        $domain = clickfix_normalize_domain($domainCandidate);

        if (in_array($mode, ['delete_report', 'delete_alert', 'delete_event', 'delete_detection'], true)) {
            if (!clickfix_user_has_min_role($actor, 'admin')) {
                clickfix_flash('Permisos insuficientes: eliminar detecciones requiere Administrador.');
                clickfix_redirect('dashboard.php?page=' . urlencode($postReturnPage) . '&report_id=' . $reportId);
            }
            $ok = clickfix_delete_report($pdo, $reportId);
            $entityLabel = $mode === 'delete_event'
                ? 'Evento'
                : ($mode === 'delete_alert' ? 'Alerta' : 'Deteccion');
            clickfix_flash($ok ? ($entityLabel . ' eliminado: #' . $reportId) : ('No se pudo eliminar ' . strtolower($entityLabel) . '.'));
            clickfix_redirect('dashboard.php?page=' . urlencode($postReturnPage));
        }

        if ($mode === 'block_domain') {
            if (!$canManageLists) {
                clickfix_flash('Permisos insuficientes: bloquear dominio requiere Analista Sr o superior.');
                clickfix_redirect('dashboard.php?page=' . urlencode($postReturnPage));
            }
            if ($domain === '') {
                clickfix_flash('No se pudo bloquear: la alerta no tiene dominio vÃ¡lido.');
                clickfix_redirect('dashboard.php?page=' . urlencode($postReturnPage) . '&report_id=' . $reportId);
            }
            $ok = clickfix_apply_list_action($pdo, $actorId, 'blocklist', 'add', $domain, 'quick action from report #' . $reportId);
            if ($ok) {
                // Reflect the action immediately in recent events.
                clickfix_set_report_blocked($pdo, $reportId, true);
            }
            clickfix_flash($ok ? ('Dominio bloqueado: ' . $domain) : 'No se pudo bloquear el dominio.');
            clickfix_redirect('dashboard.php?page=' . urlencode($postReturnPage) . '&report_id=' . $reportId);
        }

        if ($mode === 'send_investigation_list') {
            if (!$canManageLists) {
                clickfix_flash('Permisos insuficientes: investigatelist requiere Analista Sr o superior.');
                clickfix_redirect('dashboard.php?page=' . urlencode($postReturnPage) . '&report_id=' . $reportId);
            }
            if ($domain === '') {
                clickfix_flash('No se pudo aÃ±adir a investigatelist: dominio invÃ¡lido.');
                clickfix_redirect('dashboard.php?page=' . urlencode($postReturnPage) . '&report_id=' . $reportId);
            }
            $ok = clickfix_apply_list_action($pdo, $actorId, 'investigatelist', 'add', $domain, 'quick action from report #' . $reportId);
            clickfix_flash($ok ? ('Dominio enviado a investigaciÃ³n: ' . $domain) : 'No se pudo actualizar investigatelist.');
            clickfix_redirect('dashboard.php?page=' . urlencode($postReturnPage) . '&report_id=' . $reportId);
        }

        if ($mode === 'create_investigation') {
            if (!clickfix_user_has_min_role($actor, 'analyst_jr')) {
                clickfix_flash('Permisos insuficientes para crear investigaciones.');
                clickfix_redirect('dashboard.php?page=ops');
            }
            $domainForInvestigation = $domain !== '' ? $domain : 'unknown-domain.local';
            $url = trim((string) ($report['url'] ?? ''));
            $message = trim((string) ($report['message'] ?? ''));
            $receivedAt = trim((string) ($report['received_at'] ?? ''));
            $score = isset($report['score_total']) ? (int) $report['score_total'] : 0;
            $detected = trim((string) ($report['detected_content'] ?? ''));
            $fullContext = trim((string) ($report['full_context'] ?? ''));
            if ($redactSensitiveForActor) {
                $url = clickfix_dashboard_redact_sensitive($url);
                $message = clickfix_dashboard_redact_sensitive($message);
                $detected = clickfix_dashboard_redact_sensitive($detected);
                $fullContext = clickfix_dashboard_redact_sensitive($fullContext);
            }
            $summaryLines = [
                'InvestigaciÃ³n generada desde alerta #' . $reportId . '.',
                'Dominio: ' . $domainForInvestigation,
                'Fecha alerta: ' . ($receivedAt !== '' ? $receivedAt : gmdate('c')),
                'Score: ' . $score . '/100',
            ];
            if ($url !== '') {
                $summaryLines[] = 'URL: ' . $url;
            }
            if ($message !== '') {
                $summaryLines[] = 'Mensaje: ' . $message;
            }
            if ($detected !== '') {
                $summaryLines[] = 'Detectado: ' . substr($detected, 0, 500);
            }
            if ($fullContext !== '') {
                $summaryLines[] = 'Contexto: ' . substr($fullContext, 0, 1200);
            }
            $summary = implode(PHP_EOL, $summaryLines);

            $graphNodes = [
                [
                    'id' => 'n_alert_' . $reportId,
                    'label' => 'Alerta #' . $reportId,
                    'color' => '#e66a6a',
                    'x' => 200,
                    'y' => 140,
                    'tags' => ['alert', 'clickfix', 'auto'],
                    'notes' => $summary,
                ],
                [
                    'id' => 'n_domain_' . preg_replace('/[^a-z0-9]/', '_', strtolower($domainForInvestigation)),
                    'label' => $domainForInvestigation,
                    'color' => '#5dc8ff',
                    'x' => 500,
                    'y' => 160,
                    'tags' => ['domain'],
                    'notes' => $url !== '' ? ('URL observada: ' . $url) : '',
                ],
            ];
            $graphEdges = [
                [
                    'id' => 'e_alert_domain_' . $reportId,
                    'from' => 'n_alert_' . $reportId,
                    'to' => 'n_domain_' . preg_replace('/[^a-z0-9]/', '_', strtolower($domainForInvestigation)),
                    'label' => 'evento detectado',
                    'color' => '#94a3b8',
                ],
            ];
            if ($detected !== '') {
                $graphNodes[] = [
                    'id' => 'n_snippet_' . $reportId,
                    'label' => 'Snippet detectado',
                    'color' => '#ffd166',
                    'x' => 350,
                    'y' => 300,
                    'tags' => ['snippet', 'evidence'],
                    'notes' => substr($detected, 0, 400),
                ];
                $graphEdges[] = [
                    'id' => 'e_alert_snippet_' . $reportId,
                    'from' => 'n_alert_' . $reportId,
                    'to' => 'n_snippet_' . $reportId,
                    'label' => 'evidencia',
                    'color' => '#ffd166',
                ];
            }
            $savedId = clickfix_investigation_save(
                $pdo,
                $actorId,
                null,
                'InvestigaciÃ³n alerta #' . $reportId . ' - ' . $domainForInvestigation,
                $domainForInvestigation,
                'investigating',
                $summary,
                'auto, from-alert, clickfix',
                ['nodes' => $graphNodes, 'edges' => $graphEdges],
                clickfix_is_admin(),
                $reportId
            );
            if ($savedId !== null) {
                $queuedJobId = clickfix_investigation_enqueue_alert_correlation($pdo, (int) $savedId, $reportId, $actorId, 4);
                if ($queuedJobId !== null) {
                    clickfix_flash('InvestigaciÃ³n creada desde alerta #' . $reportId . ' y correlaciÃ³n encolada (#' . $queuedJobId . ').');
                } else {
                    clickfix_flash('InvestigaciÃ³n creada desde alerta #' . $reportId . '. No se pudo encolar la correlaciÃ³n automÃ¡tica.');
                }
                clickfix_redirect('dashboard.php?page=intel&graph_id=' . $savedId);
            }
            clickfix_flash('No se pudo crear la investigaciÃ³n automÃ¡tica.');
            clickfix_redirect('dashboard.php?page=' . urlencode($postReturnPage) . '&report_id=' . $reportId);
        }

        clickfix_flash('AcciÃ³n rÃ¡pida no vÃ¡lida.');
        clickfix_redirect('dashboard.php?page=' . urlencode($postReturnPage) . '&report_id=' . $reportId);
    }
    if ($action === 'report_quick_action_llm') {
        $reportId = (int) ($_POST['report_id'] ?? 0);
        $llmProfileId = max(0, (int) ($_POST['llm_profile_id'] ?? 0));
        if ($reportId <= 0) { clickfix_flash('report_id invalido.'); clickfix_redirect('dashboard.php?page=' . urlencode($postReturnPage)); }
        if (!clickfix_user_has_min_role($actor, 'analyst_jr')) { clickfix_flash('Permisos insuficientes para crear investigaciones con LLM.'); clickfix_redirect('dashboard.php?page=ops'); }
        $report = clickfix_report_by_id($pdo, $reportId);
        if ($report === null) { clickfix_flash('No se encontro la alerta.'); clickfix_redirect('dashboard.php?page=' . urlencode($postReturnPage)); }
        require_once __DIR__ . '/src/clickfix_llm.php';
        clickfix_llm_ensure_table($pdo);
        $domainCandidate = (string) ($report['hostname'] ?? '');
        if ($domainCandidate === '') { $domainCandidate = (string) ($report['url'] ?? ''); }
        $domain = clickfix_normalize_domain($domainCandidate);
        $domainForInv = $domain !== '' ? $domain : 'unknown-domain.local';
        $score = isset($report['score_total']) ? (int) $report['score_total'] : 0;
        $url = trim((string) ($report['url'] ?? ''));
        $detected = trim((string) ($report['detected_content'] ?? ''));
        $receivedAt = trim((string) ($report['received_at'] ?? ''));
        $summaryBase = "Inv. LLM desde alerta #{$reportId} | Dominio: {$domainForInv} | Score: {$score}/100 | " . ($receivedAt !== '' ? $receivedAt : gmdate('c'));
        $graphNodes = [
            ['id' => 'n_alert_' . $reportId, 'label' => 'Alerta #' . $reportId, 'color' => '#e66a6a', 'x' => 200, 'y' => 140, 'tags' => ['alert', 'llm'], 'notes' => $summaryBase],
            ['id' => 'n_domain_' . preg_replace('/[^a-z0-9]/', '_', strtolower($domainForInv)), 'label' => $domainForInv, 'color' => '#5dc8ff', 'x' => 500, 'y' => 160, 'tags' => ['domain'], 'notes' => $url !== '' ? ('URL: ' . $url) : ''],
        ];
        $graphEdges = [['id' => 'e_alert_domain_' . $reportId, 'from' => 'n_alert_' . $reportId, 'to' => 'n_domain_' . preg_replace('/[^a-z0-9]/', '_', strtolower($domainForInv)), 'label' => 'evento detectado', 'color' => '#94a3b8']];
        if ($detected !== '') {
            $graphNodes[] = ['id' => 'n_snippet_' . $reportId, 'label' => 'Snippet', 'color' => '#ffd166', 'x' => 350, 'y' => 300, 'tags' => ['snippet'], 'notes' => substr($detected, 0, 400)];
            $graphEdges[] = ['id' => 'e_alert_snippet_' . $reportId, 'from' => 'n_alert_' . $reportId, 'to' => 'n_snippet_' . $reportId, 'label' => 'evidencia', 'color' => '#ffd166'];
        }
        $savedId = clickfix_investigation_save($pdo, $actorId, null, 'Inv. LLM #' . $reportId . ' - ' . $domainForInv, $domainForInv, 'investigating', $summaryBase, 'llm, auto, from-alert', ['nodes' => $graphNodes, 'edges' => $graphEdges], clickfix_is_admin(), $reportId);
        if ($savedId === null) { clickfix_flash('No se pudo crear la investigacion.'); clickfix_redirect('dashboard.php?page=' . urlencode($postReturnPage) . '&report_id=' . $reportId); }
        clickfix_investigation_enqueue_alert_correlation($pdo, (int) $savedId, $reportId, $actorId, 4);
        $llmResult = clickfix_llm_investigate_alert($pdo, $reportId, $actorId, ['profile_id' => $llmProfileId]);
        $llmVerdict = 'investigating';
        if ($llmResult['ok'] && !empty($llmResult['content'])) {
            $content = $llmResult['content'];
            $lower = strtolower($content);
            if (str_contains($lower, 'malicious') || str_contains($lower, 'confirmed threat')) { $llmVerdict = 'confirmed_malicious'; }
            elseif (str_contains($lower, 'suspicious') || str_contains($lower, 'sospechoso') || str_contains($lower, 'likely malicious')) { $llmVerdict = 'suspicious'; }
            elseif (str_contains($lower, 'benign') || str_contains($lower, 'false positive') || str_contains($lower, 'legitimo') || str_contains($lower, 'limpio')) { $llmVerdict = 'false_positive'; }
            $fullSummary = "[VEREDICTO LLM: " . strtoupper(str_replace('_', ' ', $llmVerdict)) . "]\n\n" . substr($content, 0, 4000) . "\n\n[ORIGINAL]\n" . $summaryBase;
            $pdo->prepare('UPDATE investigation_graphs SET verdict = :v, summary = :s WHERE id = :id')->execute([':v' => $llmVerdict, ':s' => $fullSummary, ':id' => $savedId]);
            clickfix_flash('Investigacion creada + LLM. Veredicto: ' . strtoupper(str_replace('_', ' ', $llmVerdict)));
        } else {
            $err = $llmResult['error'] ?? 'no_response';
            clickfix_flash('Investigacion creada pero LLM no disponible: ' . $err . '. Configura tu perfil LLM en Settings > LLM Profiles.');
        }
        clickfix_redirect('dashboard.php?page=intel&graph_id=' . $savedId);
    }
    if ($action === 'extension_link_add') {
        if (!$canManageExtensionLinks) {
            clickfix_flash('Permisos insuficientes para asociar usuarios de extensi?n.');
            clickfix_redirect('dashboard.php?page=ops');
        }
        $targetClient = clickfix_normalize_client_id((string) ($_POST['link_client_id'] ?? ''));
        $ok = clickfix_link_user_extension_client(
            $pdo,
            $actorId,
            (int) ($_POST['link_user_id'] ?? 0),
            $targetClient,
            (string) ($_POST['link_note'] ?? '')
        );
        clickfix_flash($ok ? 'Asociaci?n guardada.' : 'No se pudo guardar la asociaci?n.');
        $redirect = 'dashboard.php?page=extensions';
        if ($targetClient !== '') {
            $redirect .= '&client_id=' . urlencode($targetClient);
        }
        clickfix_redirect($redirect);
    }
    if ($action === 'extension_link_remove') {
        if (!$canManageExtensionLinks) {
            clickfix_flash('Permisos insuficientes para editar asociaci?nes de extensi?n.');
            clickfix_redirect('dashboard.php?page=ops');
        }
        $ok = clickfix_unlink_user_extension_client($pdo, (int) ($_POST['link_id'] ?? 0));
        clickfix_flash($ok ? 'Asociaci?n desactivada.' : 'No se pudo desactivar la asociaci?n.');
        clickfix_redirect('dashboard.php?page=extensions');
    }
    if ($action === 'review') {
        if (!$canReview) {
            clickfix_flash('Permisos insuficientes: requiere Analista Mid o superior.');
            clickfix_redirect('dashboard.php?page=' . urlencode($postReturnPage));
        }
        $reportId = (int) ($_POST['report_id'] ?? 0);
        $status = (string) ($_POST['status'] ?? 'pending');
        $report = clickfix_report_by_id($pdo, $reportId);
        if ($report === null) {
            clickfix_flash('No se pudo actualizar la revisiÃ³n: alerta no encontrada.');
            clickfix_redirect('dashboard.php?page=' . urlencode($postReturnPage));
        }
        $ok = clickfix_update_report_review($pdo, $reportId, $status, $actorId);
        if ($ok && $status === 'accepted') {
            clickfix_flash('RevisiÃ³n actualizada. El dominio ha quedado bloqueado automÃ¡ticamente si era vÃ¡lido.');
        } elseif ($ok && $status === 'allowlisted') {
            clickfix_flash('RevisiÃ³n actualizada. El dominio se ha enviado automÃ¡ticamente a allowlist si era vÃ¡lido.');
        } else {
            clickfix_flash($ok ? 'RevisiÃ³n actualizada.' : 'No hubo cambios en la revisiÃ³n (verifica el evento y el estado).');
        }
        clickfix_redirect('dashboard.php?page=' . urlencode($postReturnPage) . '&report_id=' . $reportId);
    }
    if ($action === 'review_bulk') {
        if (!$canReview) {
            clickfix_flash('Permisos insuficientes: requiere Analista Mid o superior.');
            clickfix_redirect('dashboard.php?page=' . urlencode($postReturnPage));
        }
        $status = (string) ($_POST['status'] ?? 'pending');
        $rawReportIds = $_POST['report_ids'] ?? [];
        if (!is_array($rawReportIds)) {
            $rawReportIds = [$rawReportIds];
        }
        $reportIdsMap = [];
        foreach ($rawReportIds as $rawReportId) {
            $parsedId = (int) $rawReportId;
            if ($parsedId > 0) {
                $reportIdsMap[$parsedId] = true;
            }
            if (count($reportIdsMap) >= 500) {
                break;
            }
        }
        $reportIds = array_keys($reportIdsMap);
        if (empty($reportIds)) {
            clickfix_flash('Selecciona al menos una alerta para revisiÃ³n masiva.');
            clickfix_redirect('dashboard.php?page=' . urlencode($postReturnPage));
        }

        $updatedCount = 0;
        foreach ($reportIds as $reportId) {
            if (clickfix_update_report_review($pdo, (int) $reportId, $status, $actorId)) {
                $updatedCount++;
            }
        }
        $totalCount = count($reportIds);
        $failedCount = max(0, $totalCount - $updatedCount);
        $bulkMessage = 'RevisiÃ³n masiva aplicada: ' . $updatedCount . '/' . $totalCount . ($failedCount > 0 ? (' (' . $failedCount . ' sin cambios)') : '') . '.';
        if ($status === 'accepted' && $updatedCount > 0) {
            $bulkMessage .= ' Los dominios aceptados se han bloqueado automÃ¡ticamente cuando eran vÃ¡lidos.';
        } elseif ($status === 'allowlisted' && $updatedCount > 0) {
            $bulkMessage .= ' Los dominios seleccionados se han enviado automÃ¡ticamente a allowlist cuando eran vÃ¡lidos.';
        }
        clickfix_flash($bulkMessage);
        clickfix_redirect('dashboard.php?page=' . urlencode($postReturnPage));
    }
    if ($action === 'appeal_status') {
        if (!$canManageRequests) {
            clickfix_flash('Permisos insuficientes: requiere Analista Sr o superior.');
            clickfix_redirect('dashboard.php?page=ops');
        }
        clickfix_update_appeal_status($pdo, (int) ($_POST['appeal_id'] ?? 0), (string) ($_POST['status'] ?? 'pending'));
        clickfix_flash('Estado de desistimiento actualizado.');
        clickfix_redirect('dashboard.php?page=requests');
    }
    if ($action === 'access_request_status') {
        if (!$canManageRequests) {
            clickfix_flash('Permisos insuficientes: requiere Analista Sr o superior.');
            clickfix_redirect('dashboard.php?page=ops');
        }
        clickfix_update_access_request_status($pdo, (int) ($_POST['request_id'] ?? 0), (string) ($_POST['status'] ?? 'pending'));
        clickfix_flash('Solicitud de acceso actualizada.');
        clickfix_redirect('dashboard.php?page=requests');
    }
    if ($action === 'list_action') {
        if (!$canManageLists) {
            clickfix_flash('Permisos insuficientes: requiere Analista Sr o superior.');
            clickfix_redirect('dashboard.php?page=ops');
        }
        clickfix_apply_list_action(
            $pdo,
            $actorId,
            (string) ($_POST['list_type'] ?? 'blocklist'),
            (string) ($_POST['operation'] ?? 'add'),
            (string) ($_POST['domain'] ?? ''),
            (string) ($_POST['reason'] ?? 'dashboard')
        );
        clickfix_flash('Lista actualizada.');
        clickfix_redirect('dashboard.php?page=lists');
    }
    if ($action === 'list_bulk_action') {
        if (!$canManageLists) {
            clickfix_flash('Permisos insuficientes: requiere Analista Sr o superior.');
            clickfix_redirect('dashboard.php?page=ops');
        }
        $bulk = clickfix_apply_list_bulk_action(
            $pdo,
            $actorId,
            (string) ($_POST['list_type'] ?? 'blocklist'),
            (string) ($_POST['operation'] ?? 'add'),
            (string) ($_POST['domains_raw'] ?? ''),
            (string) ($_POST['reason'] ?? 'dashboard bulk')
        );
        clickfix_flash('AcciÃ³n masiva: ' . (int) ($bulk['applied'] ?? 0) . '/' . (int) ($bulk['total'] ?? 0) . ' aplicada.');
        clickfix_redirect('dashboard.php?page=lists');
    }
    if ($action === 'scan_image_review') {
        if (!$canManageReports) {
            clickfix_flash('Permisos insuficientes para revisar capturas.');
            clickfix_redirect('dashboard.php?page=ops');
        }
        $reportId = (int) ($_POST['scan_report_id'] ?? 0);
        $kind = (string) ($_POST['scan_kind'] ?? '');
        $status = (string) ($_POST['scan_status'] ?? 'pending');
        $note = (string) ($_POST['scan_note'] ?? '');
        $ok = clickfix_scan_image_set_review($pdo, $reportId, $kind, $status, $actorId, $note);
        clickfix_flash($ok ? 'RevisiÃ³n de captura actualizada.' : 'No se pudo actualizar la revisiÃ³n de captura.');
        $returnPage = strtolower(trim((string) ($_POST['return_page'] ?? 'analytics')));
        if (!in_array($returnPage, ['home', 'analytics'], true)) {
            $returnPage = 'analytics';
        }
        clickfix_redirect('dashboard.php?page=' . urlencode($returnPage));
    }
    if ($action === 'scan_image_delete') {
        if (!$canManageReports) {
            clickfix_flash('Permisos insuficientes para eliminar capturas.');
            clickfix_redirect('dashboard.php?page=ops');
        }
        $reportId = (int) ($_POST['scan_report_id'] ?? 0);
        $kind = (string) ($_POST['scan_kind'] ?? '');
        $returnPage = strtolower(trim((string) ($_POST['return_page'] ?? 'home')));
        if (!in_array($returnPage, ['home', 'analytics', 'ops', 'search'], true)) {
            $returnPage = 'home';
        }
        $ok = clickfix_delete_scan_image($pdo, $reportId, $kind);
        clickfix_flash($ok ? 'Captura eliminada.' : 'No se pudo eliminar la captura.');
        clickfix_redirect('dashboard.php?page=' . urlencode($returnPage));
    }
    if ($action === 'scan_image_swap') {
        if (!$canManageReports) {
            clickfix_flash('Permisos insuficientes para editar capturas.');
            clickfix_redirect('dashboard.php?page=ops');
        }
        $reportId = (int) ($_POST['scan_report_id'] ?? 0);
        $returnPage = strtolower(trim((string) ($_POST['return_page'] ?? 'home')));
        if (!in_array($returnPage, ['home', 'analytics', 'ops', 'search'], true)) {
            $returnPage = 'home';
        }
        $ok = clickfix_scan_swap_before_after($pdo, $reportId, $actorId);
        clickfix_flash($ok ? 'Capturas before/after intercambiadas.' : 'No se pudieron intercambiar las capturas.');
        clickfix_redirect('dashboard.php?page=' . urlencode($returnPage));
    }
    if ($action === 'scan_image_assign') {
        if (!$canManageReports) {
            clickfix_flash('Permisos insuficientes para reasignar capturas.');
            clickfix_redirect('dashboard.php?page=ops');
        }
        $reportId = (int) ($_POST['scan_report_id'] ?? 0);
        $sourceKind = (string) ($_POST['scan_source_kind'] ?? '');
        $targetKind = (string) ($_POST['scan_target_kind'] ?? '');
        $returnPage = strtolower(trim((string) ($_POST['return_page'] ?? 'home')));
        if (!in_array($returnPage, ['home', 'analytics', 'ops', 'search'], true)) {
            $returnPage = 'home';
        }
        $ok = clickfix_scan_assign_kind($pdo, $reportId, $sourceKind, $targetKind, $actorId, true);
        clickfix_flash($ok ? 'Captura reasignada correctamente a ' . strtoupper($targetKind) . '.' : 'No se pudo reasignar la captura.');
        clickfix_redirect('dashboard.php?page=' . urlencode($returnPage));
    }
    if ($action === 'scan_image_upload') {
        if (!$canManageReports) {
            clickfix_flash('Permisos insuficientes para subir capturas.');
            clickfix_redirect('dashboard.php?page=ops');
        }
        $reportId = (int) ($_POST['scan_report_id'] ?? 0);
        $kind = (string) ($_POST['scan_kind'] ?? '');
        $returnPage = strtolower(trim((string) ($_POST['return_page'] ?? 'home')));
        if (!in_array($returnPage, ['home', 'analytics', 'ops', 'search'], true)) {
            $returnPage = 'home';
        }
        $status = strtolower(trim((string) ($_POST['scan_upload_status'] ?? 'approved')));
        if (!in_array($status, ['pending', 'approved', 'rejected'], true)) {
            $status = 'approved';
        }
        $upload = $_FILES['scan_upload_file'] ?? null;
        if (!is_array($upload) || (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            clickfix_flash('No se recibiÃ³ un archivo vÃ¡lido para la captura.');
            clickfix_redirect('dashboard.php?page=' . urlencode($returnPage));
        }
        $tmpName = (string) ($upload['tmp_name'] ?? '');
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            clickfix_flash('Archivo temporal de subida no vÃ¡lido.');
            clickfix_redirect('dashboard.php?page=' . urlencode($returnPage));
        }
        $size = (int) ($upload['size'] ?? 0);
        if ($size <= 0 || $size > 8 * 1024 * 1024) {
            clickfix_flash('TamaÃ±o de captura invÃ¡lido (max 8 MB).');
            clickfix_redirect('dashboard.php?page=' . urlencode($returnPage));
        }
        $mime = '';
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $detected = finfo_file($finfo, $tmpName);
                if (is_string($detected)) {
                    $mime = strtolower(trim($detected));
                }
                finfo_close($finfo);
            }
        }
        $extByMime = [
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'image/bmp' => 'bmp',
            'image/x-ms-bmp' => 'bmp',
            'image/avif' => 'avif',
        ];
        $ext = (string) ($extByMime[$mime] ?? '');
        if ($ext === '') {
            $name = strtolower((string) ($upload['name'] ?? ''));
            if (preg_match('/\.png$/', $name)) {
                $ext = 'png';
            } elseif (preg_match('/\.(jpe?g)$/', $name)) {
                $ext = 'jpg';
            } elseif (preg_match('/\.webp$/', $name)) {
                $ext = 'webp';
            } elseif (preg_match('/\.gif$/', $name)) {
                $ext = 'gif';
            } elseif (preg_match('/\.bmp$/', $name)) {
                $ext = 'bmp';
            } elseif (preg_match('/\.avif$/', $name)) {
                $ext = 'avif';
            }
        }
        if ($ext === '') {
            clickfix_flash('Formato no permitido. Usa PNG, JPG, WEBP, GIF, BMP o AVIF.');
            clickfix_redirect('dashboard.php?page=' . urlencode($returnPage));
        }
        $bytes = @file_get_contents($tmpName);
        if (!is_string($bytes) || $bytes === '') {
            clickfix_flash('No se pudo leer el archivo subido.');
            clickfix_redirect('dashboard.php?page=' . urlencode($returnPage));
        }
        if (clickfix_scan_detect_image_info($bytes) === null) {
            clickfix_flash('La imagen no es valida o contiene un formato inseguro. No se permiten SVG ni contenidos ejecutables.');
            clickfix_redirect('dashboard.php?page=' . urlencode($returnPage));
        }
        $stored = clickfix_scan_write_asset_bytes($reportId, $kind, $bytes, $ext);
        if (!$stored) {
            clickfix_flash('No se pudo guardar la captura manual.');
            clickfix_redirect('dashboard.php?page=' . urlencode($returnPage));
        }
        clickfix_scan_image_set_review($pdo, $reportId, $kind, $status, $actorId, 'manual upload from dashboard');
        clickfix_flash('Captura manual subida correctamente (' . strtoupper($kind) . ').');
        clickfix_redirect('dashboard.php?page=' . urlencode($returnPage));
    }
    if ($action === 'message_send') {
        if (!$canManageMessaging) {
            clickfix_flash('Permisos insuficientes para mensajerÃ­a.');
            clickfix_redirect('dashboard.php?page=ops');
        }
        $dispatch = clickfix_dispatch_extension_message(
            $pdo,
            $actorId,
            (string) ($_POST['msg_scope'] ?? 'all'),
            (string) ($_POST['msg_client_id'] ?? ''),
            (int) ($_POST['msg_user_id'] ?? 0),
            (string) ($_POST['msg_title'] ?? ''),
            (string) ($_POST['msg_body'] ?? ''),
            (string) ($_POST['msg_severity'] ?? 'info'),
            (int) ($_POST['msg_expires_days'] ?? 7),
            (string) ($_POST['msg_expires_at'] ?? ''),
            (string) ($_POST['msg_client_ids_raw'] ?? ''),
            is_array($_POST['msg_user_ids'] ?? null) ? (array) $_POST['msg_user_ids'] : []
        );
        $scopeUsed = (string) ($dispatch['scope'] ?? '');
        $sent = (int) ($dispatch['sent'] ?? 0);
        $resolved = (int) ($dispatch['resolved_clients'] ?? 0);
        if (!empty($dispatch['ok'])) {
            if ($scopeUsed === 'user') {
                clickfix_flash('Mensaje enviado. Clientes asociados resueltos: ' . $resolved . ', notificaciones emitidas: ' . $sent . '.');
            } elseif ($scopeUsed === 'linked') {
                clickfix_flash('Mensaje enviado a extensiones con usuario asociado. Clientes resueltos: ' . $resolved . ', notificaciones emitidas: ' . $sent . '.');
            } elseif ($scopeUsed === 'unlinked') {
                clickfix_flash('Mensaje enviado a extensiones no asociadas. Clientes resueltos: ' . $resolved . ', notificaciones emitidas: ' . $sent . '.');
            } elseif ($scopeUsed === 'client') {
                clickfix_flash('Mensaje enviado a client_id objetivo. Clientes resueltos: ' . $resolved . ', notificaciones emitidas: ' . $sent . '.');
            } else {
                clickfix_flash('Mensaje enviado a extensiones.');
            }
        } else {
            if ($scopeUsed === 'user') {
                clickfix_flash('No se pudo enviar: ese usuario no tiene clientes de extension asociados.');
            } elseif ($scopeUsed === 'linked') {
                clickfix_flash('No se pudo enviar: no hay clientes de extension asociados a usuarios.');
            } elseif ($scopeUsed === 'unlinked') {
                clickfix_flash('No se pudo enviar: no hay clientes de extension no asociados disponibles.');
            } elseif ($scopeUsed === 'client') {
                clickfix_flash('No se pudo enviar: indica uno o varios client_id vÃ¡lidos.');
            } else {
                clickfix_flash('No se pudo enviar el mensaje.');
            }
        }
        clickfix_redirect('dashboard.php?page=messaging');
    }
    if ($action === 'message_delete') {
        if (!$canManageMessaging) {
            clickfix_flash('Permisos insuficientes para mensajerÃ­a.');
            clickfix_redirect('dashboard.php?page=ops');
        }
        $messageId = (int) ($_POST['message_id'] ?? 0);
        $ok = clickfix_deactivate_extension_message($pdo, $messageId);
        clickfix_flash($ok ? 'Entrega detenida: el mensaje ya no se enviara a mas extensiones.' : 'No se pudo detener la entrega del mensaje.');
        clickfix_redirect('dashboard.php?page=messaging');
    }
    if ($action === 'message_hard_delete') {
        if (!$canManageMessaging) {
            clickfix_flash('Permisos insuficientes para mensajerÃ­a.');
            clickfix_redirect('dashboard.php?page=ops');
        }
        $messageId = (int) ($_POST['message_id'] ?? 0);
        $ok = clickfix_delete_extension_message($pdo, $messageId);
        clickfix_flash($ok ? 'Mensaje eliminado de la plataforma.' : 'No se pudo eliminar el mensaje de la plataforma.');
        clickfix_redirect('dashboard.php?page=messaging');
    }
    if ($action === 'message_edit') {
        if (!$canManageMessaging) {
            clickfix_flash('Permisos insuficientes para mensajerÃ­a.');
            clickfix_redirect('dashboard.php?page=ops');
        }
        $messageId = (int) ($_POST['message_id'] ?? 0);
        $active = ((string) ($_POST['msg_active'] ?? 1)) === '1';
        $ok = clickfix_update_extension_message(
            $pdo,
            $messageId,
            (string) ($_POST['msg_title'] ?? ''),
            (string) ($_POST['msg_body'] ?? ''),
            (string) ($_POST['msg_severity'] ?? 'info'),
            (int) ($_POST['msg_expires_days'] ?? 7),
            (string) ($_POST['msg_expires_at'] ?? ''),
            $active
        );
        clickfix_flash($ok ? 'Mensaje rectificado.' : 'No se pudo rectificar el mensaje.');
        clickfix_redirect('dashboard.php?page=messaging');
    }
    if ($action === 'message_history_clear') {
        if (!$canManageMessaging) {
            clickfix_flash('Permisos insuficientes para mensajerÃ­a.');
            clickfix_redirect('dashboard.php?page=ops');
        }
        $clearMode = (string) ($_POST['clear_mode'] ?? 'inactive');
        $deleted = clickfix_purge_extension_messages($pdo, $clearMode);
        if (strtolower(trim($clearMode)) === 'all') {
            clickfix_flash('Historial limpiado por completo. Filas eliminadas: ' . $deleted . '.');
        } else {
            clickfix_flash('Historial limpiado (inactivos/expirados). Filas eliminadas: ' . $deleted . '.');
        }
        clickfix_redirect('dashboard.php?page=messaging');
    }
    if ($action === 'score_config_save') {
        if (!$canManageConfigs) {
            clickfix_flash('Permisos insuficientes: requiere Administrador.');
            clickfix_redirect('dashboard.php?page=ops');
        }
        $isPremium = ((string) ($_POST['config_tier'] ?? 'basic')) === 'premium';
        $error = null;
        $ok = clickfix_save_score_config($isPremium, (string) ($_POST['config_json'] ?? ''), $error);
        clickfix_flash($ok ? 'Configuracion de score guardada.' : ('Error al guardar configuracion: ' . (string) $error));
        clickfix_redirect('dashboard.php?page=configs');
    }
    if ($action === 'internal_ad_settings_save') {
        if (!$canManageConfigs) {
            clickfix_flash('Permisos insuficientes: requiere Administrador.');
            clickfix_redirect('dashboard.php?page=ops');
        }
        $ok = clickfix_internal_ad_settings_save($pdo, [
            'enabled_global' => ((string) ($_POST['ads_enabled_global'] ?? 0)) === '1',
            'show_guest' => ((string) ($_POST['ads_show_guest'] ?? 0)) === '1',
            'show_analyst_jr' => ((string) ($_POST['ads_show_analyst_jr'] ?? 0)) === '1',
            'show_analyst_mid' => ((string) ($_POST['ads_show_analyst_mid'] ?? 0)) === '1',
            'show_analyst_sr' => ((string) ($_POST['ads_show_analyst_sr'] ?? 0)) === '1',
            'show_admin' => ((string) ($_POST['ads_show_admin'] ?? 0)) === '1',
        ], $actorId);
        clickfix_flash($ok ? 'Politica global de anuncios actualizada.' : 'No se pudo guardar la politica global de anuncios.');
        clickfix_redirect('dashboard.php?page=configs');
    }
    if ($action === 'internal_ad_save') {
        if (!$canManageConfigs) {
            clickfix_flash('Permisos insuficientes: requiere Administrador.');
            clickfix_redirect('dashboard.php?page=ops');
        }
        $ok = clickfix_internal_ad_save($pdo, [
            'title' => (string) ($_POST['ad_title'] ?? ''),
            'body' => (string) ($_POST['ad_body'] ?? ''),
            'cta_label' => (string) ($_POST['ad_cta_label'] ?? ''),
            'cta_url' => (string) ($_POST['ad_cta_url'] ?? ''),
            'placement' => (string) ($_POST['ad_placement'] ?? 'both'),
            'theme' => (string) ($_POST['ad_theme'] ?? 'cyan'),
            'priority' => (int) ($_POST['ad_priority'] ?? 100),
            'starts_at' => (string) ($_POST['ad_starts_at'] ?? ''),
            'expires_at' => (string) ($_POST['ad_expires_at'] ?? ''),
            'active' => ((string) ($_POST['ad_active'] ?? 1)) === '1',
            'target_guest' => ((string) ($_POST['ad_target_guest'] ?? 0)) === '1',
            'target_analyst_jr' => ((string) ($_POST['ad_target_analyst_jr'] ?? 0)) === '1',
            'target_analyst_mid' => ((string) ($_POST['ad_target_analyst_mid'] ?? 0)) === '1',
            'target_analyst_sr' => ((string) ($_POST['ad_target_analyst_sr'] ?? 0)) === '1',
            'target_admin' => ((string) ($_POST['ad_target_admin'] ?? 0)) === '1',
        ], $actorId);
        clickfix_flash($ok ? 'Anuncio guardado.' : 'No se pudo guardar el anuncio. Revisa t?tulo, contenido, URL o targets.');
        clickfix_redirect('dashboard.php?page=configs');
    }
    if ($action === 'internal_ad_toggle') {
        if (!$canManageConfigs) {
            clickfix_flash('Permisos insuficientes: requiere Administrador.');
            clickfix_redirect('dashboard.php?page=ops');
        }
        $ok = clickfix_internal_ad_toggle($pdo, (int) ($_POST['ad_id'] ?? 0), ((string) ($_POST['ad_active'] ?? '0')) === '1');
        clickfix_flash($ok ? 'Estado del anuncio actualizado.' : 'No se pudo actualizar el estado del anuncio.');
        clickfix_redirect('dashboard.php?page=configs');
    }
    if ($action === 'internal_ad_delete') {
        if (!$canManageConfigs) {
            clickfix_flash('Permisos insuficientes: requiere Administrador.');
            clickfix_redirect('dashboard.php?page=ops');
        }
        $ok = clickfix_internal_ad_delete($pdo, (int) ($_POST['ad_id'] ?? 0));
        clickfix_flash($ok ? 'Anuncio eliminado.' : 'No se pudo eliminar el anuncio.');
        clickfix_redirect('dashboard.php?page=configs');
    }
    if ($action === 'internal_ads_seed_test') {
        if (!$canManageConfigs) {
            clickfix_flash('Permisos insuficientes: requiere Administrador.');
            clickfix_redirect('dashboard.php?page=ops');
        }
        $created = clickfix_internal_ads_seed_test($pdo, $actorId, true);
        clickfix_flash('Anuncios de test generados: ' . (int) $created . '.');
        clickfix_redirect('dashboard.php?page=configs');
    }
    if ($action === 'report_schedule_save') {
        if (!$canManageReports) {
            clickfix_flash('Permisos insuficientes: requiere Administrador.');
            clickfix_redirect('dashboard.php?page=ops');
        }
        $ok = clickfix_upsert_report_schedule(
            $pdo,
            (string) ($_POST['period'] ?? 'daily'),
            (string) ($_POST['recipient'] ?? ''),
            ((string) ($_POST['enabled'] ?? 1)) === '1'
        );
        clickfix_flash($ok ? 'Programacion de reporte actualizada.' : 'No se pudo guardar la programacion.');
        clickfix_redirect('dashboard.php?page=reports');
    }
    if ($action === 'report_run_now') {
        if (!$canManageReports) {
            clickfix_flash('Permisos insuficientes: requiere Administrador.');
            clickfix_redirect('dashboard.php?page=ops');
        }
        $results = clickfix_run_due_report_schedules($pdo, true);
        $total = count($results);
        $ok = 0;
        foreach ($results as $runRow) {
            if (!empty($runRow['ok'])) {
                $ok++;
            }
        }
        if ($total === 0) {
            clickfix_flash('No hay programaciones enabled para ejecutar.');
            clickfix_redirect('dashboard.php?page=reports');
        }
        clickfix_flash('Reportes ejecutados: ' . $ok . '/' . $total);
        clickfix_redirect('dashboard.php?page=reports');
    }
    if ($action === 'domain_purge') {
        if (!$canManageReports) {
            clickfix_flash('Permisos insuficientes: requiere Administrador.');
            clickfix_redirect('dashboard.php?page=ops');
        }
        $domainRaw = (string) ($_POST['purge_domain'] ?? '');
        $result = clickfix_delete_reports_by_domain($pdo, $domainRaw, [
            'include_subdomains' => ((string) ($_POST['purge_include_subdomains'] ?? 1)) === '1',
            'include_url' => ((string) ($_POST['purge_include_url'] ?? 1)) === '1',
            'include_previous_url' => ((string) ($_POST['purge_include_previous_url'] ?? 1)) === '1',
            'delete_caches' => ((string) ($_POST['purge_delete_caches'] ?? 1)) === '1',
            'delete_investigations' => ((string) ($_POST['purge_delete_investigations'] ?? 0)) === '1',
        ]);
        if (($result['host'] ?? '') === '') {
            clickfix_flash('Dominio invÃ¡lido. Usa un dominio o URL vÃ¡lida.');
            clickfix_redirect('dashboard.php?page=reports');
        }
        $summary = sprintf(
            'Dominio %s: reportes encontrados %d, borrados %d, fallidos %d, caches %d, investigaciones %d.',
            $result['host'],
            (int) ($result['matched'] ?? 0),
            (int) ($result['deleted'] ?? 0),
            (int) ($result['failed'] ?? 0),
            (int) ($result['cache_deleted'] ?? 0),
            (int) ($result['investigations_deleted'] ?? 0)
        );
        clickfix_flash($summary);
        clickfix_redirect('dashboard.php?page=reports');
    }
    if ($action === 'investigation_save') {
        if (!clickfix_user_has_min_role($actor, 'analyst_jr')) {
            clickfix_flash('Permisos insuficientes para guardar investigaciones.');
            clickfix_redirect('dashboard.php?page=home&public=1');
        }
        $autoSave = (string) ($_POST['auto_save'] ?? '') === '1';
        $graphId = (int) ($_POST['graph_id'] ?? 0);
        $existingInvestigation = $graphId > 0 ? clickfix_get_investigation_any($pdo, $graphId) : null;
        $canEditCommunityAsReviewer = is_array($existingInvestigation)
            && !empty($existingInvestigation['submitted_to_community'])
            && clickfix_user_has_min_role($actor, 'analyst_mid');
        $rawGraph = (string) ($_POST['graph_json'] ?? '');
        $decodedGraph = json_decode($rawGraph, true);
        if (!is_array($decodedGraph)) {
            $decodedGraph = ['nodes' => [], 'edges' => []];
        }
        $savedId = clickfix_investigation_save(
            $pdo,
            $actorId,
            $graphId > 0 ? $graphId : null,
            (string) ($_POST['title'] ?? ''),
            (string) ($_POST['site_domain'] ?? ''),
            (string) ($_POST['verdict'] ?? 'suspicious'),
            (string) ($_POST['summary'] ?? ''),
            (string) ($_POST['tags'] ?? ''),
            $decodedGraph,
            clickfix_is_admin() || $canEditCommunityAsReviewer
        );
        if ($savedId !== null) {
            if ($autoSave) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'status' => 'ok',
                    'graph_id' => (int) $savedId,
                    'saved_at' => gmdate('c'),
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                exit;
            }
            clickfix_flash('InvestigaciÃ³n guardada.');
            clickfix_redirect('dashboard.php?page=intel&graph_id=' . (int) $savedId);
        }
        if ($autoSave) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['status' => 'error', 'message' => 'save_failed']);
            exit;
        }
        clickfix_flash('No se pudo guardar la investigaciÃ³n.');
        clickfix_redirect('dashboard.php?page=intel');
    }
    if ($action === 'investigation_delete') {
        if (!clickfix_user_has_min_role($actor, 'analyst_jr')) {
            clickfix_flash('Permisos insuficientes para eliminar investigaciones.');
            clickfix_redirect('dashboard.php?page=home&public=1');
        }
        $graphId = (int) ($_POST['graph_id'] ?? 0);
        if (clickfix_investigation_delete($pdo, $graphId, $actorId, clickfix_is_admin())) {
            clickfix_flash('InvestigaciÃ³n eliminada.');
        } else {
            clickfix_flash('No se pudo eliminar la investigaciÃ³n.');
        }
        clickfix_redirect('dashboard.php?page=intel');
    }
    if ($action === 'investigation_home_feature') {
        if (!$canManageConfigs) {
            clickfix_flash('Permisos insuficientes: requiere Administrador.');
            clickfix_redirect('dashboard.php?page=intel');
        }
        $graphId = (int) ($_POST['graph_id'] ?? 0);
        $showOnHome = ((string) ($_POST['show_on_home'] ?? '0')) === '1';
        $homePosition = (int) ($_POST['home_position'] ?? 0);
        $sourceReportId = (int) ($_POST['source_report_id'] ?? 0);
        $ok = clickfix_investigation_set_home_feature(
            $pdo,
            $graphId,
            $actorId,
            $showOnHome,
            $homePosition,
            $sourceReportId,
            true
        );
        clickfix_flash($ok ? 'Configuracion de Inicio actualizada.' : 'No se pudo actualizar la configuracion de Inicio.');
        clickfix_redirect('dashboard.php?page=intel&graph_id=' . max(0, $graphId));
    }
    if ($action === 'investigation_share') {
        if (!clickfix_user_has_min_role($actor, 'analyst_jr')) {
            clickfix_flash('Permisos insuficientes para compartir investigaciones.');
            clickfix_redirect('dashboard.php?page=home&public=1');
        }
        $graphId = (int) ($_POST['graph_id'] ?? 0);
        $shareEnabled = ((string) ($_POST['share_mode'] ?? 'off')) === 'on';
        $token = clickfix_investigation_set_share($pdo, $graphId, $actorId, $shareEnabled, clickfix_is_admin());
        if ($shareEnabled && $token !== null) {
            clickfix_flash('Enlace PÃºblico generado.');
            clickfix_redirect('dashboard.php?page=intel&graph_id=' . $graphId);
        }
        clickfix_flash('Comparticion desactivada.');
        clickfix_redirect('dashboard.php?page=intel&graph_id=' . $graphId);
    }
    if ($action === 'investigation_api_key_save') {
        if (!clickfix_user_has_min_role($actor, 'analyst_jr')) {
            clickfix_flash('Permisos insuficientes para gestionar API keys de investigaciÃ³n.');
            clickfix_redirect('dashboard.php?page=home&public=1');
        }
        $graphId = (int) ($_POST['graph_id'] ?? 0);
        $provider = (string) ($_POST['provider'] ?? '');
        $apiKey = (string) ($_POST['api_key'] ?? '');
        $existingApiKey = clickfix_user_api_key_value($pdo, $actorId, $provider);
        if ($existingApiKey !== '' && trim($apiKey) !== '' && strpos($apiKey, '*') !== false) {
            $existingMasked = clickfix_mask_secret($existingApiKey);
            if ($existingMasked !== '' && hash_equals($existingMasked, trim($apiKey))) {
                // Keep current key when the masked placeholder was submitted.
                $apiKey = $existingApiKey;
            }
        }
        $note = (string) ($_POST['api_note'] ?? '');
        $ok = clickfix_user_api_key_upsert($pdo, $actorId, $provider, $apiKey, $note);
        clickfix_flash($ok ? 'API key guardada para tu usuario.' : 'No se pudo guardar la API key (proveedor o clave invalida).');
        $redirect = 'dashboard.php?page=' . urlencode($apiReturnPage);
        if ($apiReturnPage === 'intel' && $graphId > 0) {
            $redirect .= '&graph_id=' . $graphId;
        }
        clickfix_redirect($redirect);
    }
    if ($action === 'investigation_api_key_delete') {
        if (!clickfix_user_has_min_role($actor, 'analyst_jr')) {
            clickfix_flash('Permisos insuficientes para gestionar API keys de investigaciÃ³n.');
            clickfix_redirect('dashboard.php?page=home&public=1');
        }
        $graphId = (int) ($_POST['graph_id'] ?? 0);
        $provider = (string) ($_POST['provider'] ?? '');
        $ok = clickfix_user_api_key_delete($pdo, $actorId, $provider);
        clickfix_flash($ok ? 'API key eliminada para tu usuario.' : 'No se pudo eliminar la API key.');
        $redirect = 'dashboard.php?page=' . urlencode($apiReturnPage);
        if ($apiReturnPage === 'intel' && $graphId > 0) {
            $redirect .= '&graph_id=' . $graphId;
        }
        clickfix_redirect($redirect);
    }
    if ($action === 'platform_api_key_create') {
        if (!clickfix_user_has_min_role($actor, 'analyst_jr')) {
            clickfix_flash('Permisos insuficientes para crear API keys de plataforma.');
            clickfix_redirect('dashboard.php?page=home&public=1');
        }
        $graphId = (int) ($_POST['graph_id'] ?? 0);
        $label = (string) ($_POST['platform_api_label'] ?? '');
        $expiresDays = (int) ($_POST['platform_api_expires_days'] ?? 90);
        $maxRpm = (int) ($_POST['platform_api_max_rpm'] ?? 120);
        $createdKey = clickfix_user_platform_api_key_create(
            $pdo,
            $actorId,
            $label,
            $expiresDays,
            'intel:read',
            $maxRpm
        );
        if (is_array($createdKey) && !empty($createdKey['api_key'])) {
            if (!isset($_SESSION['clickfix_platform_api_key_once']) || !is_array($_SESSION['clickfix_platform_api_key_once'])) {
                $_SESSION['clickfix_platform_api_key_once'] = [];
            }
            $_SESSION['clickfix_platform_api_key_once'][$actorId] = $createdKey;
            clickfix_flash('API key de plataforma creada. Se mostrara una sola vez para copiarla.');
        } else {
            clickfix_flash('No se pudo crear la API key de plataforma. Verifica permisos o configuracion.');
        }
        $redirect = 'dashboard.php?page=' . urlencode($apiReturnPage);
        if ($apiReturnPage === 'intel' && $graphId > 0) {
            $redirect .= '&graph_id=' . $graphId;
        }
        clickfix_redirect($redirect);
    }
    if ($action === 'platform_api_key_revoke') {
        if (!clickfix_user_has_min_role($actor, 'analyst_jr')) {
            clickfix_flash('Permisos insuficientes para revocar API keys de plataforma.');
            clickfix_redirect('dashboard.php?page=home&public=1');
        }
        $graphId = (int) ($_POST['graph_id'] ?? 0);
        $keyId = (int) ($_POST['platform_api_key_id'] ?? 0);
        $ok = clickfix_user_platform_api_key_revoke($pdo, $actorId, $keyId);
        clickfix_flash($ok ? 'API key de plataforma revocada.' : 'No se pudo revocar la API key de plataforma.');
        $redirect = 'dashboard.php?page=' . urlencode($apiReturnPage);
        if ($apiReturnPage === 'intel' && $graphId > 0) {
            $redirect .= '&graph_id=' . $graphId;
        }
        clickfix_redirect($redirect);
    }
    if ($action === 'llm_profile_save') {
        if (!clickfix_user_has_min_role($actor, 'analyst_mid')) {
            clickfix_flash('Permisos insuficientes para gestionar perfiles LLM.');
            clickfix_redirect('dashboard.php?page=home&public=1');
        }
        require_once __DIR__ . '/src/clickfix_llm.php';
        clickfix_llm_ensure_table($pdo);
        $profileData = [
            'label' => (string) ($_POST['llm_label'] ?? ''),
            'provider' => (string) ($_POST['llm_provider'] ?? 'openai'),
            'base_url' => (string) ($_POST['llm_base_url'] ?? ''),
            'model' => (string) ($_POST['llm_model'] ?? ''),
            'api_key' => (string) ($_POST['llm_api_key'] ?? ''),
            'extra_headers' => json_decode((string) ($_POST['llm_extra_headers'] ?? '{}'), true) ?: [],
            'is_active' => ((string) ($_POST['llm_is_active'] ?? '1')) === '1',
        ];
        $saved = clickfix_llm_save_profile($pdo, $profileData, $actorId);
        clickfix_flash($saved !== null ? 'Perfil LLM guardado correctamente.' : 'No se pudo guardar el perfil LLM.');
        clickfix_redirect('dashboard.php?page=settings#settings-llm-profiles');
    }
    if ($action === 'llm_profile_delete') {
        if (!clickfix_user_has_min_role($actor, 'analyst_mid')) {
            clickfix_flash('Permisos insuficientes para gestionar perfiles LLM.');
            clickfix_redirect('dashboard.php?page=home&public=1');
        }
        require_once __DIR__ . '/src/clickfix_llm.php';
        clickfix_llm_ensure_table($pdo);
        $profileId = (int) ($_POST['profile_id'] ?? 0);
        $ok = clickfix_llm_delete_profile($pdo, $profileId, $actorId);
        clickfix_flash($ok ? 'Perfil LLM eliminado.' : 'No se pudo eliminar el perfil LLM.');
        clickfix_redirect('dashboard.php?page=settings#settings-llm-profiles');
    }
    if ($action === 'auto_investigation_settings_save') {
        if (!clickfix_user_has_min_role($actor, 'analyst_mid')) {
            clickfix_flash('Permisos insuficientes para configurar auto-investigacion.');
            clickfix_redirect('dashboard.php?page=home&public=1');
        }
        require_once __DIR__ . '/src/clickfix_auto_investigation.php';
        clickfix_llm_ensure_table($pdo);
        clickfix_auto_investigation_set_settings($pdo, [
            'enabled' => (string) ($_POST['auto_inv_enabled'] ?? '0'),
            'min_score' => (string) ($_POST['auto_inv_min_score'] ?? '60'),
            'max_depth' => (string) ($_POST['auto_inv_max_depth'] ?? '3'),
            'llm_enrich' => (string) ($_POST['auto_inv_llm_enrich'] ?? '0'),
            'llm_profile_id' => (string) ($_POST['auto_inv_llm_profile_id'] ?? '0'),
            'schedule_interval_minutes' => (string) ($_POST['auto_inv_schedule_interval'] ?? '15'),
        ]);
        clickfix_flash('Configuracion de auto-investigacion guardada.');
        clickfix_redirect('dashboard.php?page=settings#settings-auto-inv');
    }
    if ($action === 'domain_feed_fetch') {
        if (!clickfix_user_has_min_role($actor, 'analyst_mid')) { clickfix_flash('Permisos insuficientes.'); clickfix_redirect('dashboard.php?page=home&public=1'); }
        require_once __DIR__ . '/src/clickfix_domain_feeds.php';
        require_once __DIR__ . '/src/clickfix_socdefenders.php';
        require_once __DIR__ . '/src/clickfix_abusech.php';
        clickfix_domain_feeds_ensure_table($pdo);
        $r1 = clickfix_domain_feeds_fetch_all($pdo);
        $r2 = clickfix_abusech_fetch_clickfix_tags($pdo);
        $r3 = clickfix_socdefenders_fetch_clickfix_iocs($pdo);
        clickfix_flash('Domain feeds fetched. Gist/Carson: ' . count($r1) . ' sources, abuse.ch: ' . $r2['domains_found'] . ' domains, SOC Defenders: ' . $r3['domains_found'] . ' domains.');
        clickfix_redirect('dashboard.php?page=domain_feeds');
    }
    if ($action === 'domain_feed_import_one') {
        if (!clickfix_user_has_min_role($actor, 'analyst_sr')) { clickfix_flash('Permisos insuficientes para importar a blocklist.'); clickfix_redirect('dashboard.php?page=domain_feeds'); }
        require_once __DIR__ . '/src/clickfix_domain_feeds.php';
        clickfix_domain_feeds_ensure_table($pdo);
        $entryId = (int) ($_POST['feed_entry_id'] ?? 0);
        $ok = clickfix_domain_feeds_import_to_blocklist($pdo, $entryId, $actorId);
        clickfix_flash($ok ? 'Domain imported to blocklist.' : 'Import failed.');
        clickfix_redirect('dashboard.php?page=domain_feeds');
    }
    if ($action === 'domain_feed_import_all') {
        if (!clickfix_user_has_min_role($actor, 'analyst_sr')) { clickfix_flash('Permisos insuficientes para importar a blocklist.'); clickfix_redirect('dashboard.php?page=domain_feeds'); }
        require_once __DIR__ . '/src/clickfix_domain_feeds.php';
        clickfix_domain_feeds_ensure_table($pdo);
        $result = clickfix_domain_feeds_import_all_new($pdo, $actorId);
        clickfix_flash('Imported ' . $result['imported'] . ' domains to blocklist.');
        clickfix_redirect('dashboard.php?page=domain_feeds');
    }
    if ($action === 'investigation_api_lookup') {
        if (!clickfix_user_has_min_role($actor, 'analyst_jr')) {
            clickfix_flash('Permisos insuficientes para consultas de investigaciÃ³n.');
            clickfix_redirect('dashboard.php?page=home&public=1');
        }
        $graphId = (int) ($_POST['graph_id'] ?? 0);
        $provider = (string) ($_POST['provider'] ?? '');
        $target = (string) ($_POST['lookup_target'] ?? '');
        $apiKey = clickfix_user_api_key_value($pdo, $actorId, $provider);
        if ($apiKey === '') {
            $apiKey = clickfix_provider_service_api_key($provider);
        }
        if ($apiKey === '') {
            clickfix_flash('No hay credencial activa para ese proveedor.');
            $redirect = 'dashboard.php?page=' . urlencode($apiReturnPage);
            if ($apiReturnPage === 'intel' && $graphId > 0) {
                $redirect .= '&graph_id=' . $graphId;
            }
            clickfix_redirect($redirect);
        }
        $lookup = clickfix_user_api_lookup($provider, $apiKey, $target);
        $responseJson = clickfix_intel_pretty_json($lookup['response'] ?? '', 120000);
        if (!isset($_SESSION['clickfix_intel_api_lookup']) || !is_array($_SESSION['clickfix_intel_api_lookup'])) {
            $_SESSION['clickfix_intel_api_lookup'] = [];
        }
        $_SESSION['clickfix_intel_api_lookup'][$actorId] = [
            'captured_at' => gmdate('c'),
            'provider' => (string) ($lookup['provider'] ?? $provider),
            'target' => (string) ($lookup['target'] ?? $target),
            'status' => (int) ($lookup['status'] ?? 0),
            'ok' => !empty($lookup['ok']),
            'summary' => is_array($lookup['summary'] ?? null) ? $lookup['summary'] : [],
            'error' => (string) ($lookup['error'] ?? ''),
            'response_json' => $responseJson,
        ];
        $lookupStored = clickfix_investigation_api_lookup_store(
            $pdo,
            $actorId,
            $graphId,
            $lookup,
            $responseJson
        );
        if (!empty($lookup['ok'])) {
            clickfix_flash($lookupStored ? 'Consulta de proveedor completada y guardada en historial.' : 'Consulta de proveedor completada (no se pudo guardar historial).');
        } else {
            $baseError = (string) ($lookup['error'] ?? 'error');
            clickfix_flash($lookupStored ? ('Consulta de proveedor sin exito: ' . $baseError . ' (guardada en historial).') : ('Consulta de proveedor sin exito: ' . $baseError));
        }
        $redirect = 'dashboard.php?page=' . urlencode($apiReturnPage);
        if ($apiReturnPage === 'intel' && $graphId > 0) {
            $redirect .= '&graph_id=' . $graphId;
        }
        clickfix_redirect($redirect);
    }
    if ($action === 'investigation_add_ioc') {
        if (!clickfix_user_has_min_role($actor, 'analyst_jr')) {
            clickfix_flash('Permisos insuficientes para editar investigaciones.');
            clickfix_redirect('dashboard.php?page=home&public=1');
        }
        $graphId = (int) ($_POST['graph_id'] ?? 0);
        $rawInput = trim((string) ($_POST['manual_ioc'] ?? ''));
        $typeOverride = strtolower(trim((string) ($_POST['manual_ioc_type'] ?? 'auto')));
        if ($rawInput === '') {
            clickfix_flash('Escribe al menos un IOC (dominio, IP o URL).');
            clickfix_redirect('dashboard.php?page=intel&graph_id=' . $graphId);
        }
        $investigation = $graphId > 0 ? clickfix_get_investigation_any($pdo, $graphId) : null;
        if (!is_array($investigation)) {
            clickfix_flash('InvestigaciÃ³n no encontrada.');
            clickfix_redirect('dashboard.php?page=intel');
        }

        $graph = is_array($investigation['graph'] ?? null) ? $investigation['graph'] : ['nodes' => [], 'edges' => []];
        $nodes = is_array($graph['nodes'] ?? null) ? $graph['nodes'] : [];
        $existingLabels = [];
        foreach ($nodes as $node) {
            if (!is_array($node)) {
                continue;
            }
            $label = strtolower(trim((string) ($node['label'] ?? '')));
            if ($label !== '') {
                $existingLabels[$label] = true;
            }
        }

        $targets = [];
        if ($typeOverride !== '' && $typeOverride !== 'auto') {
            $parts = preg_split('/[\r\n,]+/', $rawInput) ?: [];
            foreach ($parts as $part) {
                $value = trim((string) $part);
                if ($value === '') {
                    continue;
                }
                $targets[] = ['value' => $value, 'type' => $typeOverride];
            }
        } else {
            $targets = cfintel_extract_targets_from_text($rawInput);
        }

        if (empty($targets)) {
            clickfix_flash('No se detectaron IOCs vÃ¡lidos (dominio, IP o URL).');
            clickfix_redirect('dashboard.php?page=intel&graph_id=' . $graphId);
        }

        $colorByType = [
            'domain' => '#2dd4bf',
            'ip' => '#60a5fa',
            'url' => '#f59e0b',
        ];
        $added = 0;
        $skipped = 0;
        $index = count($nodes);
        foreach ($targets as $target) {
            $value = trim((string) ($target['value'] ?? ''));
            $type = trim((string) ($target['type'] ?? 'unknown'));
            if ($value === '' || !in_array($type, ['domain', 'ip', 'url'], true)) {
                $skipped++;
                continue;
            }
            $labelKey = strtolower($value);
            if (isset($existingLabels[$labelKey])) {
                $skipped++;
                continue;
            }
            $existingLabels[$labelKey] = true;
            $col = $index % 4;
            $row = intdiv($index, 4);
            $nodes[] = [
                'id' => 'ioc_' . bin2hex(random_bytes(4)),
                'label' => $value,
                'color' => $colorByType[$type] ?? '#5dc8ff',
                'x' => 120 + ($col * 90),
                'y' => 120 + ($row * 70),
                'tags' => ['ioc', $type, 'manual'],
                'notes' => '',
            ];
            $index++;
            $added++;
        }

        if ($added === 0) {
            clickfix_flash('No se aÃ±adieron IOCs nuevos (duplicados o invÃ¡lidos).');
            clickfix_redirect('dashboard.php?page=intel&graph_id=' . $graphId);
        }

        $graph['nodes'] = $nodes;
        $tagsText = implode(', ', is_array($investigation['tags'] ?? null) ? $investigation['tags'] : []);
        $savedId = clickfix_investigation_save(
            $pdo,
            $actorId,
            $graphId,
            (string) ($investigation['title'] ?? ''),
            (string) ($investigation['site_domain'] ?? ''),
            (string) ($investigation['verdict'] ?? 'suspicious'),
            (string) ($investigation['summary'] ?? ''),
            $tagsText,
            $graph,
            clickfix_is_admin()
        );
        if ($savedId !== null) {
            $summary = 'IOC(s) a?adidos: ' . $added;
            if ($skipped > 0) {
                $summary .= ' | omitidos: ' . $skipped;
            }
            clickfix_flash($summary);
        } else {
            clickfix_flash('No se pudo guardar la investigaciÃ³n con los IOCs nuevos.');
        }
        clickfix_redirect('dashboard.php?page=intel&graph_id=' . $graphId);
    }
    if ($action === 'investigation_ioc_workbench') {
        if (!clickfix_user_has_min_role($actor, 'analyst_jr')) {
            clickfix_flash('Permisos insuficientes para enrichment de investigaciÃ³n.');
            clickfix_redirect('dashboard.php?page=home&public=1');
        }
        $graphId = (int) ($_POST['graph_id'] ?? 0);
        $inputRaw = trim((string) ($_POST['ioc_intake_text'] ?? ''));
        $inputRaw = substr($inputRaw, 0, 40000);
        if ($inputRaw === '') {
            clickfix_flash('Pega texto, HTML, comandos o IOCs antes de procesar el intake.');
            $redirect = 'dashboard.php?page=' . urlencode($apiReturnPage);
            if ($apiReturnPage === 'intel' && $graphId > 0) {
                $redirect .= '&graph_id=' . $graphId;
            }
            clickfix_redirect($redirect);
        }

        $providers = array_values(array_unique(array_filter(array_map(
            'clickfix_normalize_user_api_provider',
            is_array($_POST['batch_providers'] ?? null) ? (array) $_POST['batch_providers'] : []
        ))));
        $refanged = cfintel_refang_text($inputRaw);
        $artifacts = cfintel_extract_artifacts_from_text($inputRaw);
        $artifactCounts = [];
        foreach (cfintel_artifact_types() as $artifactType) {
            $artifactCounts[$artifactType] = 0;
        }
        foreach ($artifacts as $artifactRow) {
            $artifactType = (string) ($artifactRow['type'] ?? '');
            if (isset($artifactCounts[$artifactType])) {
                $artifactCounts[$artifactType]++;
            }
        }

        $decodeChainRaw = trim((string) ($_POST['decode_chain'] ?? ''));
        $decodeChains = cfintel_parse_decode_chains($decodeChainRaw);
        $decodeResults = [];
        $decodeSeen = [];
        $decodeCandidates = array_merge(
            [$inputRaw, $refanged],
            array_map(static function (array $row): string {
                return (string) ($row['value'] ?? '');
            }, $artifacts)
        );
        $decodeSuggestions = cfintel_decode_suggestions($decodeCandidates);
        foreach ($decodeCandidates as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate === '') {
                continue;
            }
            $decodeKey = strtolower($candidate);
            if (isset($decodeSeen[$decodeKey])) {
                continue;
            }
            $decodeSeen[$decodeKey] = true;
            $decoded = cfintel_decode_value($candidate);
            foreach ($decodeChains as $chain) {
                $chainLabel = implode('->', $chain);
                if ($chainLabel === '') {
                    continue;
                }
                $chainValue = cfintel_decode_chain($candidate, $chain);
                if ($chainValue !== '' && (!isset($decoded[$chainLabel]) || $decoded[$chainLabel] !== $chainValue)) {
                    $decoded[$chainLabel] = $chainValue;
                }
            }
            if (!empty($decoded)) {
                $decodeResults[] = [
                    'input' => $candidate,
                    'decoded' => $decoded,
                ];
            }
            if (count($decodeResults) >= 12) {
                break;
            }
        }

        $providerReadyTargets = [];
        $providerSeen = [];
        foreach ($artifacts as $artifactRow) {
            $type = (string) ($artifactRow['type'] ?? '');
            $value = trim((string) ($artifactRow['value'] ?? ''));
            if ($value === '' || !in_array($type, ['domain', 'ip', 'url'], true)) {
                continue;
            }
            $key = $type . '|' . strtolower($value);
            if (isset($providerSeen[$key])) {
                continue;
            }
            $providerSeen[$key] = true;
            $providerReadyTargets[] = ['type' => $type, 'value' => $value];
            if (count($providerReadyTargets) >= 15) {
                break;
            }
        }

        $batchResults = [];
        $batchCount = 0;
        foreach ($providers as $provider) {
            $apiKey = clickfix_user_api_key_value($pdo, $actorId, $provider);
            if ($apiKey === '') {
                $apiKey = clickfix_provider_service_api_key($provider);
            }
            foreach ($providerReadyTargets as $targetRow) {
                $targetType = (string) ($targetRow['type'] ?? '');
                $targetValue = (string) ($targetRow['value'] ?? '');
                $providerAllowed =
                    $provider === 'virustotal'
                    || ($provider === 'abuseipdb' && in_array($targetType, ['domain', 'ip'], true))
                    || ($provider === 'urlscan' && in_array($targetType, ['domain', 'url'], true))
                    || ($provider === 'threatrip' && $targetType === 'sha256');
                if (!$providerAllowed) {
                    continue;
                }
                if ($apiKey === '') {
                    $batchResults[] = [
                        'provider' => $provider,
                        'target' => $targetValue,
                        'target_type' => $targetType,
                        'ok' => false,
                        'status' => 0,
                        'error' => 'No hay credencial activa para ese proveedor.',
                        'summary' => [],
                    ];
                    continue;
                }
                $lookup = clickfix_user_api_lookup($provider, $apiKey, $targetValue);
                $responseJson = clickfix_intel_pretty_json($lookup['response'] ?? '', 120000);
                clickfix_investigation_api_lookup_store(
                    $pdo,
                    $actorId,
                    $graphId,
                    $lookup,
                    $responseJson
                );
                $batchResults[] = [
                    'provider' => (string) ($lookup['provider'] ?? $provider),
                    'target' => (string) ($lookup['target'] ?? $targetValue),
                    'target_type' => $targetType,
                    'ok' => !empty($lookup['ok']),
                    'status' => (int) ($lookup['status'] ?? 0),
                    'error' => (string) ($lookup['error'] ?? ''),
                    'summary' => is_array($lookup['summary'] ?? null) ? $lookup['summary'] : [],
                ];
                $batchCount++;
            }
        }

        if (!isset($_SESSION['clickfix_intel_workbench']) || !is_array($_SESSION['clickfix_intel_workbench'])) {
            $_SESSION['clickfix_intel_workbench'] = [];
        }
        $_SESSION['clickfix_intel_workbench'][$actorId] = [
            'captured_at' => gmdate('c'),
            'input' => $inputRaw,
            'refanged' => $refanged,
            'artifact_counts' => $artifactCounts,
            'artifacts' => $artifacts,
            'decoded' => $decodeResults,
            'decode_chain' => $decodeChainRaw,
            'decode_suggestions' => $decodeSuggestions,
            'batch_results' => $batchResults,
        ];

        $artifactTotal = array_sum($artifactCounts);
        $decodeCount = count($decodeResults);
        clickfix_flash('IOC intake procesado: ' . $artifactTotal . ' artefactos, ' . $decodeCount . ' decodificaciones y ' . $batchCount . ' consultas batch.');
        $redirect = 'dashboard.php?page=' . urlencode($apiReturnPage);
        if ($apiReturnPage === 'intel' && $graphId > 0) {
            $redirect .= '&graph_id=' . $graphId;
        }
        clickfix_redirect($redirect);
    }
    if ($action === 'user_create') {
        if (!$canManageUsers) {
            clickfix_flash('Permisos insuficientes: requiere Administrador.');
            clickfix_redirect('dashboard.php?page=ops');
        }
        $created = false;
        try {
            $created = clickfix_create_user(
                $pdo,
                (string) ($_POST['new_username'] ?? ''),
                (string) ($_POST['new_password'] ?? ''),
                (string) ($_POST['new_role'] ?? 'analyst_jr'),
                ((string) ($_POST['new_verified'] ?? 1)) === '1',
                (string) ($_POST['new_email'] ?? ''),
                (string) ($_POST['new_lang'] ?? 'en')
            );
        } catch (Throwable $exception) {
            $created = false;
        }
        clickfix_flash($created ? 'Usuario creado.' : 'No se pudo crear usuario (revisa username/email/password y duplicados).');
        clickfix_redirect('dashboard.php?page=users');
    }
    if ($action === 'user_update') {
        if (!$canManageUsers) {
            clickfix_flash('Permisos insuficientes: requiere Administrador.');
            clickfix_redirect('dashboard.php?page=ops');
        }
        $updated = clickfix_update_user_profile(
            $pdo,
            (int) ($_POST['edit_user_id'] ?? 0),
            (string) ($_POST['edit_role'] ?? 'analyst_jr'),
            ((string) ($_POST['edit_verified'] ?? 1)) === '1',
            (string) ($_POST['edit_password'] ?? ''),
            (string) ($_POST['edit_email'] ?? ''),
            (string) ($_POST['edit_lang'] ?? 'en'),
            isset($_POST['edit_reputation']) ? (int) $_POST['edit_reputation'] : null
        );
        if ($updated && (int) ($_POST['edit_user_id'] ?? 0) === $actorId) {
            clickfix_user_reload_session($pdo, $actorId);
            $selfLang = clickfix_normalize_user_language((string) ($_POST['edit_lang'] ?? 'en'));
            if (in_array($selfLang, ['en', 'es', 'ca', 'de', 'fr'], true)) {
                $_SESSION['clickfix_lang'] = $selfLang;
            }
        }
        clickfix_flash($updated ? 'Usuario actualizado.' : 'No se pudo actualizar usuario (revisa email/password/duplicados).');
        clickfix_redirect('dashboard.php?page=users');
    }
}

$metrics = clickfix_live_metrics($pdo);
$format = strtolower(trim((string) ($_GET['format'] ?? '')));
if ($format === 'live' || $format === 'json') {
    $includeRecent = $format === 'json' || (($_GET['include_recent'] ?? '') === '1');
    $responseMetrics = $publicView ? clickfix_dashboard_public_metrics($metrics) : $metrics;
    $payload = [
        'status' => 'ok',
        'generated_at' => gmdate('c'),
        'stats' => $responseMetrics,
    ];
    $publicPreview = clickfix_public_preview_payload($pdo, 14, 8);
    if (!empty($publicPreview['charts']) && is_array($publicPreview['charts'])) {
        $payload['charts'] = $publicPreview['charts'];
    }
    if (!empty($publicPreview['recent_domains']) && is_array($publicPreview['recent_domains'])) {
        $payload['recent_domains'] = $publicPreview['recent_domains'];
    } else {
        $payload['recent_domains'] = [];
    }
    if (!empty($publicPreview['geo_points']) && is_array($publicPreview['geo_points'])) {
        $payload['geo_points'] = $publicPreview['geo_points'];
    } else {
        $payload['geo_points'] = [];
    }
    if (!empty($publicPreview['geo_points_alerts']) && is_array($publicPreview['geo_points_alerts'])) {
        $payload['geo_points_alerts'] = $publicPreview['geo_points_alerts'];
    } else {
        $payload['geo_points_alerts'] = $payload['geo_points'];
    }
    if (!empty($publicPreview['geo_points_domains']) && is_array($publicPreview['geo_points_domains'])) {
        $payload['geo_points_domains'] = $publicPreview['geo_points_domains'];
    } else {
        $payload['geo_points_domains'] = [];
    }
    if ($includeRecent) {
        $recentRows = clickfix_recent_reports($pdo, 20);
        if ($redactSensitiveForViewer) {
            $recentRows = array_map(static function (array $row): array {
                foreach (['url', 'previous_url', 'message', 'detected_content', 'full_context'] as $sensitiveField) {
                    if (!isset($row[$sensitiveField]) || !is_scalar($row[$sensitiveField])) {
                        continue;
                    }
                    $row[$sensitiveField] = clickfix_dashboard_redact_sensitive((string) $row[$sensitiveField]);
                }
                if (isset($row['matched_snippets']) && is_array($row['matched_snippets'])) {
                    $row['matched_snippets'] = array_map(static function ($snippet): string {
                        return clickfix_dashboard_redact_sensitive((string) $snippet);
                    }, $row['matched_snippets']);
                }
                return $row;
            }, $recentRows);
        }
        $payload['recent'] = $recentRows;
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
if ($format === 'home_geo') {
    if (!$loggedIn) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'error', 'message' => 'auth_required']);
        exit;
    }
    $payload = [
        'status' => 'ok',
        'generated_at' => gmdate('c'),
        'data' => clickfix_home_maps_dataset($pdo, 50),
    ];
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
if ($format === 'related_reports') {
    if (!$loggedIn) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'error', 'message' => 'auth_required']);
        exit;
    }
    $reportId = (int) ($_GET['report_id'] ?? 0);
    if ($reportId <= 0) {
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'error', 'message' => 'invalid_report_id']);
        exit;
    }
    $sourceReport = clickfix_report_by_id($pdo, $reportId);
    if ($sourceReport === null) {
        http_response_code(404);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'error', 'message' => 'report_not_found']);
        exit;
    }
    $canSrForRelated = clickfix_user_has_min_role($user, 'analyst_sr');
    $sourceHost = clickfix_normalize_domain((string) ($sourceReport['hostname'] ?? ''));
    $sourceIp = $canSrForRelated ? clickfix_hostname_web_ip($pdo, $sourceHost, true) : '';
    $relatedRows = clickfix_related_reports($pdo, $reportId, $sourceHost, $sourceIp, 30);
    if ($redactSensitiveForViewer) {
        $relatedRows = array_map(static function (array $row): array {
            foreach (['url', 'previous_url', 'message', 'detected_content', 'full_context'] as $field) {
                if (isset($row[$field]) && is_scalar($row[$field])) {
                    $row[$field] = clickfix_dashboard_redact_sensitive((string) $row[$field]);
                }
            }
            if (isset($row['shared_snippets']) && is_array($row['shared_snippets'])) {
                $row['shared_snippets'] = array_map(static function ($snippet): string {
                    return clickfix_dashboard_redact_sensitive((string) $snippet);
                }, $row['shared_snippets']);
            }
            if (isset($row['shared_reasons']) && is_array($row['shared_reasons'])) {
                $row['shared_reasons'] = array_map(static function ($reason): string {
                    return clickfix_dashboard_redact_sensitive((string) $reason);
                }, $row['shared_reasons']);
            }
            if (isset($row['shared_signals']) && is_array($row['shared_signals'])) {
                $row['shared_signals'] = array_map(static function ($signal): string {
                    return clickfix_dashboard_redact_sensitive((string) $signal);
                }, $row['shared_signals']);
            }
            return $row;
        }, $relatedRows);
    }
    $responseRows = [];
    foreach ($relatedRows as $row) {
        $responseRows[] = [
            'id' => (int) ($row['id'] ?? 0),
            'received_at' => (string) ($row['received_at'] ?? ''),
            'last_seen' => (string) ($row['last_seen'] ?? ''),
            'activity_at' => (string) (($row['last_seen'] ?? '') !== '' ? ($row['last_seen'] ?? '') : ($row['received_at'] ?? '')),
            'hostname' => (string) ($row['hostname'] ?? ''),
            'url' => (string) ($row['url'] ?? ''),
            'ip' => $canSrForRelated ? (string) ($row['web_ip'] ?? '') : '',
            'score_total' => isset($row['score_total']) ? (int) $row['score_total'] : 0,
            'review_status' => (string) ($row['review_status'] ?? 'pending'),
            'blocked' => !empty($row['blocked']),
            'event_type' => (string) ($row['event_type'] ?? 'clickfix_alert'),
            'duplicate_count' => (int) ($row['duplicate_count'] ?? 1),
            'related_by_domain' => !empty($row['related_by_domain']),
            'related_by_ip' => $canSrForRelated && !empty($row['related_by_ip']),
            'related_by_ttp' => !empty($row['related_by_ttp']),
            'related_by_snippet' => !empty($row['related_by_snippet']),
            'shared_reasons' => array_values(array_slice((array) ($row['shared_reasons'] ?? []), 0, 6)),
            'shared_signals' => array_values(array_slice((array) ($row['shared_signals'] ?? []), 0, 6)),
            'shared_snippets' => array_values(array_slice((array) ($row['shared_snippets'] ?? []), 0, 4)),
        ];
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'status' => 'ok',
        'source' => [
            'report_id' => $reportId,
            'hostname' => $sourceHost,
            'ip' => $canSrForRelated ? $sourceIp : '',
        ],
        'count' => count($responseRows),
        'related' => $responseRows,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$search = trim((string) ($_GET['q'] ?? ''));
$domainFilter = trim((string) ($_GET['domain'] ?? ''));
$commandFilter = trim((string) ($_GET['command'] ?? ''));
$dateFromFilter = trim((string) ($_GET['date_from'] ?? ''));
$dateToFilter = trim((string) ($_GET['date_to'] ?? ''));

$canSrViewer = $loggedIn && clickfix_user_has_min_role($user, 'analyst_sr');
$canAdminViewer = $loggedIn && clickfix_user_has_min_role($user, 'admin');
$pageNeedsReports = in_array($page, ['ops', 'home', 'search'], true);
$pageNeedsFilteredReports = in_array($page, ['search', 'analytics', 'extensions'], true);
$pageNeedsAppealsData = $canSrViewer && $page === 'requests';
$pageNeedsRequestsData = ($canSrViewer && $page === 'requests') || ($canAdminViewer && $page === 'users');
$pageNeedsListData = $canSrViewer && $page === 'lists';
$pageNeedsUserDirectory = $canSrViewer && in_array($page, ['extensions', 'messaging'], true);
$pageNeedsUsersAdmin = $canAdminViewer && $page === 'users';
$pageNeedsMlInsights = ($loggedIn && clickfix_user_has_min_role($user, 'analyst_jr')) && $page === 'analytics';
$pageNeedsExtensionData = $canSrViewer && in_array($page, ['extensions', 'messaging'], true);
$pageNeedsExtensionMessages = $canSrViewer && $page === 'messaging';
$pageNeedsDataCenter = $canSrViewer && $page === 'data_center';
$pageNeedsReportPreview = $canAdminViewer && $page === 'reports';
$pageNeedsScanReviewQueue = $canAdminViewer && in_array($page, ['home', 'ops'], true);
$pageNeedsPendingOutsideData = $loggedIn && clickfix_user_has_min_role($user, 'analyst_jr') && in_array($page, ['home', 'analytics', 'lists'], true);
$pageNeedsAnomalyData = $loggedIn && clickfix_user_has_min_role($user, 'analyst_jr') && in_array($page, ['home', 'analytics'], true);
$pageNeedsProjectExposure = $canSrViewer && in_array($page, ['home', 'analytics'], true);
$pageNeedsVtReportedStats = $loggedIn && clickfix_user_has_min_role($user, 'analyst_jr') && $page === 'intel';
$pageNeedsApiSettings = $loggedIn && clickfix_user_has_min_role($user, 'analyst_jr') && in_array($page, ['intel', 'settings'], true);
$pageNeedsIntelStats = $loggedIn && clickfix_user_has_min_role($user, 'analyst_jr') && $page === 'intel_stats';
$hideApiUi = clickfix_env_truthy('CLICKFIX_HIDE_API_UI', false);
$showApiUi = $pageNeedsApiSettings && !$hideApiUi;

$reports = [];
if ($pageNeedsReports) {
    $reports = clickfix_recent_reports($pdo, $page === 'search' ? 120 : 20, $page === 'search' ? $search : null);
}
$filteredReports = [];
if ($pageNeedsFilteredReports) {
    $filteredReports = clickfix_filtered_reports($pdo, [
        'domain' => $domainFilter,
        'command' => $commandFilter,
        'date_from' => $dateFromFilter,
        'date_to' => $dateToFilter,
    ], 160);
}
$searchResults = ($page === 'search')
    ? (!empty($filteredReports) ? $filteredReports : $reports)
    : [];

$appeals = $pageNeedsAppealsData ? clickfix_recent_appeals($pdo, 20) : [];
$deleteRequests = $pageNeedsAppealsData ? clickfix_recent_delete_requests($pdo, 30) : [];
$requestsPending = $pageNeedsRequestsData ? clickfix_recent_access_requests($pdo, 30, 'pending') : [];
$requestsDenied = $pageNeedsRequestsData ? clickfix_recent_access_requests($pdo, 30, 'denied') : [];
$requests = $pageNeedsRequestsData ? clickfix_recent_access_requests($pdo, 60, null) : [];
$actions = $pageNeedsListData ? clickfix_recent_list_actions($pdo, 20) : [];
$lists = [
    'blocklist' => [],
    'allowlist' => [],
    'alertlist' => [],
    'investigatelist' => [],
];
if ($pageNeedsListData) {
    $lists = [
        'blocklist' => clickfix_load_list_file('blocklist'),
        'allowlist' => clickfix_load_list_file('allowlist'),
        'alertlist' => clickfix_load_list_file('alertlist'),
        'investigatelist' => clickfix_load_list_file('investigatelist'),
    ];
}
$allowlistSnapshot = $pageNeedsListData
    ? $lists['allowlist']
    : ($pageNeedsPendingOutsideData ? clickfix_load_list_file('allowlist') : []);
$blocklistSnapshot = $pageNeedsListData
    ? $lists['blocklist']
    : ($pageNeedsPendingOutsideData ? clickfix_load_list_file('blocklist') : []);
$unlistedAlertDomains = $pageNeedsListData
    ? clickfix_alert_domains_outside_lists($pdo, $allowlistSnapshot, $blocklistSnapshot, 120)
    : [];
$pendingOutsideSummary = ['alerts' => 0, 'domains' => 0];
$pendingOutsideReports = [];
if ($pageNeedsPendingOutsideData) {
    $pendingOutsideSummary = clickfix_pending_alerts_outside_lists($pdo, $allowlistSnapshot, $blocklistSnapshot);
    $pendingOutsideLimit = $page === 'lists' ? 260 : 80;
    $pendingOutsideReports = clickfix_pending_reports_outside_lists($pdo, $allowlistSnapshot, $blocklistSnapshot, $pendingOutsideLimit);
}
$pageNeedsPendingReviewData = $loggedIn && in_array($page, ['home', 'ops', 'analytics'], true);
$pendingReviewRows = $pageNeedsPendingReviewData ? clickfix_recent_reports($pdo, 80, null) : [];
$usersDirectory = $pageNeedsUserDirectory ? clickfix_recent_users($pdo, 400) : [];
$users = $pageNeedsUsersAdmin ? clickfix_recent_users($pdo, 200) : [];
$analyticsDays = ($page === 'home' || $page === 'analytics') ? 30 : 7;
$analyticsOverview = clickfix_analytics_overview($pdo, $analyticsDays);
$latestScanPreview = is_array($analyticsOverview['latest_scan'] ?? null) ? $analyticsOverview['latest_scan'] : null;
$latestScanAssetsApproved = is_array($analyticsOverview['latest_scan_assets'] ?? null)
    ? $analyticsOverview['latest_scan_assets']
    : ['before' => null, 'after' => null, 'before_exists' => false, 'after_exists' => false, 'before_status' => 'missing', 'after_status' => 'missing'];
$latestScanAssetsReview = $latestScanAssetsApproved;
if ($canAdminViewer && $latestScanPreview === null) {
    $adminLatestEvidence = clickfix_latest_scan_evidence($pdo, false, 500);
    if (is_array($adminLatestEvidence['report'] ?? null)) {
        $latestScanPreview = $adminLatestEvidence['report'];
    }
    if (is_array($adminLatestEvidence['assets'] ?? null)) {
        $latestScanAssetsReview = $adminLatestEvidence['assets'];
    }
}
if ($canAdminViewer && is_array($latestScanPreview) && !empty($latestScanPreview['id'])) {
    $latestScanAssetsReview = clickfix_scan_preview_assets($pdo, (int) $latestScanPreview['id'], false);
}
$featuredHomeInvestigations = $page === 'home' ? clickfix_featured_home_investigations($pdo, 6, false) : [];
$scanReviewQueue = $pageNeedsScanReviewQueue ? clickfix_scan_image_review_queue($pdo, 120) : [];
$mlInsights = $pageNeedsMlInsights ? clickfix_ml_insights($pdo, 300) : [];
$anomalyInsights = $pageNeedsAnomalyData ? clickfix_anomaly_detector($pdo, 35, 24) : [];
$intelCorrelationStats = $pageNeedsIntelStats ? clickfix_investigation_correlation_stats($pdo, 12) : [];
$projectExposureOverview = $pageNeedsProjectExposure ? clickfix_project_exposure_overview($pdo, 30) : [];
$vtReportedStats = $pageNeedsVtReportedStats ? clickfix_vt_reported_webs_stats($pdo, 30) : [];
$extensionClients = $pageNeedsExtensionData ? clickfix_recent_extension_clients($pdo, 200) : [];
$extensionUserLinks = $pageNeedsExtensionData ? clickfix_extension_user_links($pdo, 800) : [];
$selectedClientId = trim((string) ($_GET['client_id'] ?? ''));
$extensionClientEvents = ($pageNeedsExtensionData && $page === 'extensions' && $selectedClientId !== '')
    ? clickfix_extension_client_events($pdo, $selectedClientId, 200)
    : [];
$selectedClientBaselineHosts = ($pageNeedsExtensionData && $page === 'extensions' && $selectedClientId !== '')
    ? clickfix_extension_client_baseline_hosts($pdo, $selectedClientId, 60)
    : [];
$globalBaselineCandidates = $pageNeedsExtensionData ? clickfix_baseline_global_candidates($pdo, 50) : [];
$extensionLinksByClient = [];
$extensionTargetsByUser = [];
foreach ($extensionUserLinks as $linkRow) {
    $clientKey = (string) ($linkRow['client_id'] ?? '');
    if ($clientKey === '') {
        continue;
    }
    if (!isset($extensionLinksByClient[$clientKey])) {
        $extensionLinksByClient[$clientKey] = [];
    }
    $extensionLinksByClient[$clientKey][] = $linkRow;

    $userKey = (int) ($linkRow['user_id'] ?? 0);
    if ($userKey <= 0) {
        continue;
    }
    if (!isset($extensionTargetsByUser[$userKey])) {
        $extensionTargetsByUser[$userKey] = [
            'user_id' => $userKey,
            'username' => (string) ($linkRow['username'] ?? ''),
            'email' => (string) ($linkRow['email'] ?? ''),
            'role_label' => (string) ($linkRow['role_label'] ?? clickfix_role_label((string) ($linkRow['role'] ?? 'analyst_jr'))),
            'client_ids' => [],
        ];
    }
    $existingClientIds = $extensionTargetsByUser[$userKey]['client_ids'];
    if (!in_array($clientKey, $existingClientIds, true)) {
        $existingClientIds[] = $clientKey;
    }
    $extensionTargetsByUser[$userKey]['client_ids'] = $existingClientIds;
}
foreach ($extensionClients as &$ec) {
    $clientKey = (string) ($ec['client_id'] ?? '');
    $clientLinks = ($clientKey !== '' && isset($extensionLinksByClient[$clientKey])) ? $extensionLinksByClient[$clientKey] : [];
    $ec['linked_user_count'] = count($clientLinks);
    $linkedNames = [];
    foreach ($clientLinks as $linkRow) {
        $label = trim((string) ($linkRow['username'] ?? ''));
        if ($label === '') {
            $label = 'user#' . (int) ($linkRow['user_id'] ?? 0);
        }
        if (!in_array($label, $linkedNames, true)) {
            $linkedNames[] = $label;
        }
    }
    $ec['linked_users_label'] = implode(', ', array_slice($linkedNames, 0, 3));
}
unset($ec);
$selectedClientLinks = ($selectedClientId !== '' && isset($extensionLinksByClient[$selectedClientId])) ? $extensionLinksByClient[$selectedClientId] : [];
$extensionFingerprintGroups = [];
$extensionIpGroups = [];
$extensionVersionGroups = [];
$extensionFingerprintCounts = [];
foreach ($extensionClients as &$ec) {
    $version = trim((string) ($ec['extension_version'] ?? ''));
    $channel = trim((string) ($ec['install_channel'] ?? ''));
    $source = trim((string) ($ec['install_source'] ?? ''));
    $linkedCount = (int) ($ec['linked_user_count'] ?? 0);
    $userAgent = trim((string) ($ec['user_agent'] ?? ''));
    $fingerprintParts = array_filter([$version, $channel, $source, $userAgent], static fn($value): bool => trim((string) $value) !== '');
    $fingerprintSeed = implode('|', $fingerprintParts);
    $fingerprintKey = $fingerprintSeed !== '' ? substr(hash('sha1', $fingerprintSeed), 0, 12) : 'sin_firma';
    $ec['fingerprint_key'] = $fingerprintKey;
    $ec['fingerprint_label'] = $fingerprintSeed !== '' ? strtoupper($fingerprintKey) : 'Sin firma';

    if (!isset($extensionFingerprintGroups[$fingerprintKey])) {
        $extensionFingerprintGroups[$fingerprintKey] = [
            'fingerprint' => $ec['fingerprint_label'],
            'version' => $version !== '' ? $version : '-',
            'channel' => $channel !== '' ? $channel : '-',
            'source' => $source !== '' ? $source : '-',
            'client_count' => 0,
            'total_events' => 0,
            'total_blocks' => 0,
            'linked_users' => 0,
            'client_ids' => [],
        ];
    }
    $extensionFingerprintGroups[$fingerprintKey]['client_count']++;
    $extensionFingerprintGroups[$fingerprintKey]['total_events'] += (int) ($ec['total_events'] ?? 0);
    $extensionFingerprintGroups[$fingerprintKey]['total_blocks'] += (int) ($ec['total_blocks'] ?? 0);
    $extensionFingerprintGroups[$fingerprintKey]['linked_users'] += $linkedCount;
    $extensionFingerprintGroups[$fingerprintKey]['client_ids'][] = (string) ($ec['client_id'] ?? 'unknown');
    $extensionFingerprintCounts[$fingerprintKey] = (int) ($extensionFingerprintGroups[$fingerprintKey]['client_count'] ?? 0);

    $versionKey = $version !== '' ? $version : 'desconocida';
    if (!isset($extensionVersionGroups[$versionKey])) {
        $extensionVersionGroups[$versionKey] = [
            'version' => $versionKey,
            'client_count' => 0,
            'total_events' => 0,
            'total_blocks' => 0,
        ];
    }
    $extensionVersionGroups[$versionKey]['client_count']++;
    $extensionVersionGroups[$versionKey]['total_events'] += (int) ($ec['total_events'] ?? 0);
    $extensionVersionGroups[$versionKey]['total_blocks'] += (int) ($ec['total_blocks'] ?? 0);

    $ipHistoryRaw = trim((string) ($ec['ip_history'] ?? ''));
    if ($ipHistoryRaw !== '') {
        $seenIps = [];
        foreach (preg_split('/\s*,\s*/', $ipHistoryRaw) as $ipValue) {
            $ip = trim((string) $ipValue);
            if ($ip === '' || isset($seenIps[$ip])) {
                continue;
            }
            $seenIps[$ip] = true;
            if (!isset($extensionIpGroups[$ip])) {
                $extensionIpGroups[$ip] = [
                    'ip' => $ip,
                    'client_count' => 0,
                    'total_events' => 0,
                    'total_blocks' => 0,
                    'client_ids' => [],
                ];
            }
            $extensionIpGroups[$ip]['client_count']++;
            $extensionIpGroups[$ip]['total_events'] += (int) ($ec['total_events'] ?? 0);
            $extensionIpGroups[$ip]['total_blocks'] += (int) ($ec['total_blocks'] ?? 0);
            $extensionIpGroups[$ip]['client_ids'][] = (string) ($ec['client_id'] ?? 'unknown');
        }
    }
}
unset($ec);
usort($extensionClients, static function (array $a, array $b) use ($extensionFingerprintCounts): int {
    $countA = (int) ($extensionFingerprintCounts[(string) ($a['fingerprint_key'] ?? '')] ?? 0);
    $countB = (int) ($extensionFingerprintCounts[(string) ($b['fingerprint_key'] ?? '')] ?? 0);
    if ($countA !== $countB) {
        return $countB <=> $countA;
    }
    return strcmp((string) ($b['last_seen'] ?? ''), (string) ($a['last_seen'] ?? ''));
});
uasort($extensionFingerprintGroups, static function (array $a, array $b): int {
    $clientsCmp = (int) ($b['client_count'] ?? 0) <=> (int) ($a['client_count'] ?? 0);
    if ($clientsCmp !== 0) {
        return $clientsCmp;
    }
    return (int) ($b['total_events'] ?? 0) <=> (int) ($a['total_events'] ?? 0);
});
uasort($extensionIpGroups, static function (array $a, array $b): int {
    $clientsCmp = (int) ($b['client_count'] ?? 0) <=> (int) ($a['client_count'] ?? 0);
    if ($clientsCmp !== 0) {
        return $clientsCmp;
    }
    return (int) ($b['total_events'] ?? 0) <=> (int) ($a['total_events'] ?? 0);
});
uasort($extensionVersionGroups, static function (array $a, array $b): int {
    return (int) ($b['client_count'] ?? 0) <=> (int) ($a['client_count'] ?? 0);
});
$messagingUserTargets = array_values($extensionTargetsByUser);
usort($messagingUserTargets, static function (array $a, array $b): int {
    return strcmp((string) ($a['username'] ?? ''), (string) ($b['username'] ?? ''));
});
$extensionBlockedDomains = [];
if (!empty($extensionClientEvents)) {
    foreach ($extensionClientEvents as $eventRow) {
        if (empty($eventRow['blocked'])) {
            continue;
        }
        $host = strtolower(trim((string) ($eventRow['hostname'] ?? '')));
        if ($host === '') {
            $host = '-';
        }
        if (!isset($extensionBlockedDomains[$host])) {
            $extensionBlockedDomains[$host] = 0;
        }
        $extensionBlockedDomains[$host]++;
    }
    arsort($extensionBlockedDomains);
}
$extensionMessages = $pageNeedsExtensionMessages ? clickfix_recent_extension_messages($pdo, 120) : [];
$linkedExtensionClients = $pageNeedsExtensionMessages ? clickfix_extension_client_ids_linked($pdo) : [];
$linkedExtensionClientCount = count($linkedExtensionClients);
$unlinkedExtensionClients = $pageNeedsExtensionMessages ? clickfix_extension_client_ids_unlinked($pdo) : [];
$unlinkedExtensionClientCount = count($unlinkedExtensionClients);
$scoreConfigBasic = ($canAdminViewer && $page === 'configs') ? clickfix_load_score_config(false) : [];
$scoreConfigPremium = ($canAdminViewer && $page === 'configs') ? clickfix_load_score_config(true) : [];
$dataCenterSnapshot = $pageNeedsDataCenter ? clickfix_data_center_snapshot($pdo) : [];
$dataTable = trim((string) ($_GET['table'] ?? 'reports'));
$dataCenterRows = $pageNeedsDataCenter ? clickfix_table_recent($pdo, $dataTable, 80) : [];
$reportSchedules = $canAdminViewer ? clickfix_list_report_schedules($pdo) : [];
$reportPeriodPreview = strtolower(trim((string) ($_GET['period'] ?? 'daily')));
if (!in_array($reportPeriodPreview, ['daily', 'weekly', 'monthly'], true)) {
    $reportPeriodPreview = 'daily';
}
$reportPreview = $pageNeedsReportPreview ? clickfix_generate_period_report($pdo, $reportPeriodPreview) : [];
$purgeDomainCandidates = [];
if ($canAdminViewer && $page === 'reports') {
    $domainStmt = $pdo->prepare(
        "SELECT LOWER(TRIM(hostname)) AS hostname,
                COUNT(*) AS hits,
                MAX(received_at) AS last_seen
         FROM reports
         WHERE hostname IS NOT NULL
           AND TRIM(hostname) != ''
         GROUP BY LOWER(TRIM(hostname))
         ORDER BY hits DESC
         LIMIT 40"
    );
    $domainStmt->execute();
    foreach ($domainStmt->fetchAll() as $domainRow) {
        $host = clickfix_normalize_domain((string) ($domainRow['hostname'] ?? ''));
        if ($host === '') {
            continue;
        }
        $purgeDomainCandidates[] = [
            'hostname' => $host,
            'hits' => (int) ($domainRow['hits'] ?? 0),
            'last_seen' => (string) ($domainRow['last_seen'] ?? ''),
        ];
    }
}
$publicPreviewSettings = clickfix_public_preview_settings($pdo);
$internalAdSettings = clickfix_internal_ad_settings($pdo);
$internalAdsAdminList = ($canAdminViewer && $page === 'configs') ? clickfix_internal_ads_recent($pdo, 160) : [];
$dashboardAdRole = $loggedIn ? $viewerRole : 'guest';
$internalDashboardAds = clickfix_internal_ads_for_context($pdo, 'dashboard', $dashboardAdRole, 3);
$showInternalDashboardAdsPanel = !empty($internalDashboardAds);
$monetization = clickfix_monetization_config();
$monetizationDisplayRoles = ['analyst_jr', 'analyst_mid'];
$showMonetizationForGuest = !$loggedIn;
$showMonetizationForLogged = $loggedIn && in_array($viewerRole, $monetizationDisplayRoles, true);
$showMonetizationPanel = !empty($monetization['enabled']) && ($showMonetizationForGuest || $showMonetizationForLogged);
$focusInvestigationPages = ['intel', 'community', 'investigation'];
$investigationFocusMode = $loggedIn && in_array($page, $focusInvestigationPages, true);
$operationalAnnouncementRoles = ['analyst_jr', 'analyst_mid'];
$showOperationalAnnouncementAside = $loggedIn && in_array($viewerRole, $operationalAnnouncementRoles, true);
$showGuestAnnouncementAside = !$loggedIn && $showMonetizationPanel;
$showAnnouncementAside = $showOperationalAnnouncementAside || $showGuestAnnouncementAside;
$sidebarAnnouncements = [];
if ($showOperationalAnnouncementAside) {
    $recentAnnouncements = clickfix_recent_extension_messages($pdo, 80);
    $nowTs = time();
    foreach ($recentAnnouncements as $announcementRow) {
        if (empty($announcementRow['active'])) {
            continue;
        }
        $scope = strtolower(trim((string) ($announcementRow['target_scope'] ?? '')));
        if ($scope !== 'all') {
            continue;
        }
        $startsAt = trim((string) ($announcementRow['starts_at'] ?? ''));
        if ($startsAt !== '') {
            $startsAtTs = strtotime($startsAt);
            if ($startsAtTs !== false && $startsAtTs > $nowTs) {
                continue;
            }
        }
        $expiresAt = trim((string) ($announcementRow['expires_at'] ?? ''));
        if ($expiresAt !== '') {
            $expiresAtTs = strtotime($expiresAt);
            if ($expiresAtTs !== false && $expiresAtTs < $nowTs) {
                continue;
            }
        }
        $title = trim((string) ($announcementRow['title'] ?? ''));
        $body = trim((string) ($announcementRow['body'] ?? ''));
        if ($title === '' && $body === '') {
            continue;
        }
        $sidebarAnnouncements[] = $announcementRow;
        if (count($sidebarAnnouncements) >= 6) {
            break;
        }
    }
}
$ownerName = trim((string) clickfix_env('CLICKFIX_OWNER_NAME', 'Jordi Serrano'));
if ($ownerName === '') {
    $ownerName = 'ClickFix Team';
}
$contactEmail = strtolower(trim((string) clickfix_env('CLICKFIX_CONTACT_EMAIL', 'security@jordiserrano.me')));
if (!filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) {
    $contactEmail = '';
}
$contactWebsite = clickfix_sanitize_http_url(clickfix_env('CLICKFIX_CONTACT_WEBSITE', 'https://jordiserrano.me'));
$contactLinkedIn = clickfix_sanitize_http_url(clickfix_env('CLICKFIX_CONTACT_LINKEDIN', ''));
$contactX = clickfix_sanitize_http_url(clickfix_env('CLICKFIX_CONTACT_X', ''));
$contactGitHub = clickfix_sanitize_http_url(clickfix_env('CLICKFIX_CONTACT_GITHUB', ''));
$scoreConfigBasicJson = json_encode($scoreConfigBasic, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
if (!is_string($scoreConfigBasicJson) || $scoreConfigBasicJson === '') {
    $scoreConfigBasicJson = '{}';
}
$scoreConfigPremiumJson = json_encode($scoreConfigPremium, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
if (!is_string($scoreConfigPremiumJson) || $scoreConfigPremiumJson === '') {
    $scoreConfigPremiumJson = '{}';
}
$flash = clickfix_flash();
$csrf = clickfix_csrf_token();
$sharedGraph = null;
$shareToken = trim((string) ($_GET['share'] ?? ''));
if ($page === 'investigation' && $shareToken !== '') {
    $sharedGraph = clickfix_get_investigation_by_share($pdo, $shareToken);
}
$investigations = [];
$selectedInvestigation = null;
$investigationEvents = [];
$investigationQuickTargets = [];
$intelSelectionReports = [];
$intelRequestedGraphId = 0;
$intelComposeNew = false;
$intelWorkspaceActive = false;
$intelUserApiKeys = [];
$platformApiKeys = [];
$platformApiKeyJustCreated = null;
$intelApiLookupResult = null;
$intelApiLookupHistory = [];
$intelApiLookupMapRows = [];
$intelApiCommonKeywords = [];
$intelWorkbenchResult = null;
if ($loggedIn && $page === 'intel') {
    $investigations = clickfix_recent_investigations($pdo, (int) ($user['id'] ?? 0), clickfix_is_admin(), 120);
    $intelRequestedGraphId = (int) ($_GET['graph_id'] ?? 0);
    $intelComposeNew = (string) ($_GET['compose'] ?? '') === '1';
    if ($intelRequestedGraphId > 0) {
        $selectedInvestigation = clickfix_get_investigation($pdo, $intelRequestedGraphId, (int) ($user['id'] ?? 0), clickfix_is_admin());
        if ($selectedInvestigation === null && clickfix_user_has_min_role($user, 'analyst_mid')) {
            $candidate = clickfix_get_investigation_any($pdo, $intelRequestedGraphId);
            if (is_array($candidate) && !empty($candidate['submitted_to_community'])) {
                $selectedInvestigation = $candidate;
            }
        }
    }
    $intelWorkspaceActive = $selectedInvestigation !== null || $intelComposeNew;
    if ($selectedInvestigation !== null) {
        $investigationEvents = clickfix_investigation_events($pdo, (int) ($selectedInvestigation['id'] ?? 0), 220);
        $investigationQuickTargets = cfintel_collect_investigation_targets($selectedInvestigation);
    }
    if (!$intelWorkspaceActive) {
        $intelSelectionReports = clickfix_recent_reports($pdo, 18);
    }
    $llmProfiles = [];
    $autoInvJobs = [];
    if (clickfix_has_table($pdo, 'user_llm_profiles')) {
        require_once __DIR__ . '/src/clickfix_llm.php';
        clickfix_llm_ensure_table($pdo);
        $llmProfiles = clickfix_llm_configured_providers($pdo, (int) ($user['id'] ?? 0));
        require_once __DIR__ . '/src/clickfix_auto_investigation.php';
        $autoInvJobs = clickfix_auto_investigation_recent_jobs($pdo, 20);
    }
}
if ($pageNeedsApiSettings) {
    $intelUserApiKeys = clickfix_user_api_keys($pdo, (int) ($user['id'] ?? 0), $showApiUi);
    $platformApiKeys = clickfix_user_platform_api_keys($pdo, (int) ($user['id'] ?? 0));
    $lookupStore = (isset($_SESSION['clickfix_intel_api_lookup']) && is_array($_SESSION['clickfix_intel_api_lookup']))
        ? $_SESSION['clickfix_intel_api_lookup']
        : [];
    $workbenchStore = (isset($_SESSION['clickfix_intel_workbench']) && is_array($_SESSION['clickfix_intel_workbench']))
        ? $_SESSION['clickfix_intel_workbench']
        : [];
    $platformKeyStore = (isset($_SESSION['clickfix_platform_api_key_once']) && is_array($_SESSION['clickfix_platform_api_key_once']))
        ? $_SESSION['clickfix_platform_api_key_once']
        : [];
    $viewerId = (int) ($user['id'] ?? 0);
    if ($viewerId > 0 && isset($lookupStore[$viewerId]) && is_array($lookupStore[$viewerId])) {
        $intelApiLookupResult = $lookupStore[$viewerId];
        unset($_SESSION['clickfix_intel_api_lookup'][$viewerId]);
    }
    if ($viewerId > 0 && isset($workbenchStore[$viewerId]) && is_array($workbenchStore[$viewerId])) {
        $intelWorkbenchResult = $workbenchStore[$viewerId];
        unset($_SESSION['clickfix_intel_workbench'][$viewerId]);
    }
    if ($viewerId > 0 && isset($platformKeyStore[$viewerId]) && is_array($platformKeyStore[$viewerId])) {
        $platformApiKeyJustCreated = $platformKeyStore[$viewerId];
        unset($_SESSION['clickfix_platform_api_key_once'][$viewerId]);
    }
    if ($viewerId > 0 && !($page === 'intel' && $selectedInvestigation === null)) {
        $historyGraphId = ($page === 'intel' && $selectedInvestigation !== null) ? (int) ($selectedInvestigation['id'] ?? 0) : 0;
        $intelApiLookupHistory = clickfix_investigation_api_lookup_recent($pdo, $viewerId, 18, $historyGraphId);
        $intelApiLookupMapRows = array_map(static function (array $row): array {
            $summary = is_array($row['summary'] ?? null) ? $row['summary'] : [];
            $enriched = cfintel_lookup_compact_details($row);
            return [
                'id' => (int) ($row['id'] ?? 0),
                'created_at' => (string) ($row['created_at'] ?? ''),
                'provider' => strtolower(trim((string) ($row['provider'] ?? 'unknown'))),
                'target' => (string) ($row['target'] ?? ''),
                'target_type' => (string) ($row['target_type'] ?? 'unknown'),
                'status' => (int) ($row['status'] ?? 0),
                'ok' => !empty($row['ok']),
                'error' => (string) ($row['error'] ?? ''),
                'summary' => $summary,
                'details' => is_array($enriched['details'] ?? null) ? $enriched['details'] : [],
                'keywords' => is_array($enriched['keywords'] ?? null) ? $enriched['keywords'] : [],
            ];
        }, $intelApiLookupHistory);
        $intelApiCommonKeywords = cfintel_common_api_keywords($intelApiLookupMapRows, 16);
    }
}
if ($loggedIn && $page === 'intel' && $selectedInvestigation !== null) {
    $exportFormat = strtolower(trim((string) ($_GET['export_iocs'] ?? '')));
    if ($exportFormat !== '') {
        cfintel_output_ioc_export($selectedInvestigation, $investigationQuickTargets, $exportFormat);
    }
}
$communityInvestigations = ($loggedIn && clickfix_user_has_min_role($user, 'analyst_jr') && $page === 'community')
    ? clickfix_community_investigations($pdo, 240)
    : [];
$selectedCommunityInvestigation = null;
$selectedCommunityVote = ['score' => 0, 'upvotes' => 0, 'downvotes' => 0, 'classification' => 'neutral'];
if ($loggedIn && $page === 'community') {
    $requestedCommunityGraphId = (int) ($_GET['graph_id'] ?? 0);
    if ($requestedCommunityGraphId > 0) {
        $candidate = clickfix_get_investigation_any($pdo, $requestedCommunityGraphId);
        if (is_array($candidate) && !empty($candidate['submitted_to_community']) && empty($candidate['deleted'])) {
            $selectedCommunityInvestigation = $candidate;
        }
    }
    if ($selectedCommunityInvestigation === null && !empty($communityInvestigations)) {
        $selectedCommunityInvestigation = $communityInvestigations[0];
    }
    if ($selectedCommunityInvestigation !== null) {
        $selectedCommunityVote = clickfix_investigation_vote_totals($pdo, (int) ($selectedCommunityInvestigation['id'] ?? 0));
    }
}
$enableHomeGeoPanels = $loggedIn && $page === 'home';
$enableSidebarGeoMap = true;
$profileTargetId = (int) ($_GET['user_id'] ?? 0);
if ($page === 'profile' && $profileTargetId <= 0 && $loggedIn) {
    $profileTargetId = (int) ($user['id'] ?? 0);
}
$profileTab = strtolower(trim((string) ($_GET['tab'] ?? 'investigations')));
if (!in_array($profileTab, ['investigations', 'reports', 'sessions'], true)) {
    $profileTab = 'investigations';
}
$profileUser = null;
$profileInvestigations = [];
$profileReports = [];
$profileSessionHistory = [];
$profileCanViewPrivate = false;
$profileCanEdit = false;
if ($page === 'profile' && $profileTargetId > 0) {
    $viewerId = $loggedIn ? (int) ($user['id'] ?? 0) : 0;
    $viewerAdmin = $loggedIn && clickfix_is_admin();
        $profileUser = clickfix_user_profile($pdo, $profileTargetId, $viewerId, $viewerAdmin);
        if ($profileUser !== null) {
            $profileCanViewPrivate = !empty($profileUser['can_view_private']);
            $profileCanEdit = !empty($profileUser['is_owner']);
            $profileInvestigations = clickfix_user_profile_investigations($pdo, $profileTargetId, $profileCanViewPrivate, 200);
            $profileReports = $profileCanViewPrivate ? clickfix_user_profile_reports($pdo, $profileTargetId, 200) : [];
            $profileSessionHistory = $profileCanViewPrivate ? clickfix_user_session_history($pdo, $profileTargetId, 250) : [];
        }
    }

function cfurl(string $page, bool $public = false, array $extra = []): string
{
    global $lang;
    $q = ['page' => $page];
    if ($public) {
        $q['public'] = '1';
    }
    $q['lang'] = $lang;
    foreach ($extra as $k => $v) {
        $q[(string) $k] = (string) $v;
    }
    return 'dashboard.php?' . http_build_query($q);
}

function cfprofileurl(int $userId, array $extra = [], ?bool $public = null): string
{
    global $loggedIn;
    $params = array_merge(['user_id' => (string) $userId], $extra);
    $usePublic = $public === null ? !$loggedIn : $public;
    return cfurl('profile', $usePublic, $params);
}

function cfcan(?array $user, string $minimumRole): bool
{
    return clickfix_user_has_min_role($user, $minimumRole);
}

function cfintel_target_meta(string $target): array
{
    $value = trim($target);
    if ($value === '') {
        return ['type' => 'unknown', 'display' => '', 'domain' => '', 'ip' => '', 'url' => ''];
    }
    if (filter_var($value, FILTER_VALIDATE_IP) !== false) {
        return ['type' => 'ip', 'display' => $value, 'domain' => '', 'ip' => $value, 'url' => ''];
    }
    if ((bool) preg_match('/^https?:\/\//i', $value)) {
        $host = clickfix_normalize_domain((string) parse_url($value, PHP_URL_HOST));
        return ['type' => 'url', 'display' => $value, 'domain' => $host, 'ip' => '', 'url' => $value];
    }
    $domain = clickfix_normalize_domain($value);
    if ($domain !== '') {
        return ['type' => 'domain', 'display' => $domain, 'domain' => $domain, 'ip' => '', 'url' => ''];
    }
    return ['type' => 'unknown', 'display' => $value, 'domain' => '', 'ip' => '', 'url' => ''];
}

function cfintel_refang_text(string $text): string
{
    if ($text === '') {
        return '';
    }
    $refanged = preg_replace_callback('/hxxps?/i', static function (array $match): string {
        $value = strtolower((string) ($match[0] ?? 'hxxp'));
        return $value === 'hxxps' ? 'https' : 'http';
    }, $text);
    if (!is_string($refanged)) {
        $refanged = $text;
    }
    $replaceMap = [
        '[.]' => '.',
        '(.)' => '.',
        '[dot]' => '.',
        '[@]' => '@',
        '[:]' => ':',
    ];
    $refanged = str_ireplace(array_keys($replaceMap), array_values($replaceMap), $refanged);
    return $refanged;
}

function cfintel_artifact_types(): array
{
    return ['url', 'domain', 'ip', 'md5', 'sha1', 'sha256', 'email', 'cve'];
}

function cfintel_extract_artifacts_from_text(string $text): array
{
    $text = trim($text);
    if ($text === '') {
        return [];
    }
    $clean = cfintel_refang_text($text);
    $artifacts = [];
    $seen = [];
    $push = static function (string $type, string $value) use (&$artifacts, &$seen): void {
        $type = strtolower(trim($type));
        $value = trim($value);
        if ($type === '' || $value === '') {
            return;
        }
        $key = $type . '|' . strtolower($value);
        if (isset($seen[$key])) {
            return;
        }
        $seen[$key] = true;
        $artifacts[] = ['type' => $type, 'value' => $value];
    };

    if (preg_match_all('#https?://[^\s<>"\'`]+#i', $clean, $matches)) {
        foreach ((array) ($matches[0] ?? []) as $match) {
            $value = trim((string) $match);
            $value = rtrim($value, ".,;:!?)]]}>'\"");
            if ($value !== '') {
                $push('url', $value);
            }
        }
    }
    if (preg_match_all('/\b(?:\d{1,3}\.){3}\d{1,3}\b/', $clean, $matches)) {
        foreach ((array) ($matches[0] ?? []) as $match) {
            $value = (string) $match;
            if (filter_var($value, FILTER_VALIDATE_IP) !== false) {
                $push('ip', $value);
            }
        }
    }
    if (preg_match_all('/(?<!@)\b(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}\b/i', $clean, $matches)) {
        foreach ((array) ($matches[0] ?? []) as $match) {
            $domain = clickfix_normalize_domain((string) $match);
            if ($domain !== '') {
                $push('domain', $domain);
            }
        }
    }
    if (preg_match_all('/\b[a-f0-9]{64}\b/i', $clean, $matches)) {
        foreach ((array) ($matches[0] ?? []) as $match) {
            $push('sha256', strtolower((string) $match));
        }
    }
    $withoutSha256 = preg_replace('/\b[a-f0-9]{64}\b/i', ' ', $clean);
    if (!is_string($withoutSha256)) {
        $withoutSha256 = $clean;
    }
    if (preg_match_all('/\b[a-f0-9]{40}\b/i', $withoutSha256, $matches)) {
        foreach ((array) ($matches[0] ?? []) as $match) {
            $push('sha1', strtolower((string) $match));
        }
    }
    $withoutSha1 = preg_replace('/\b[a-f0-9]{40}\b/i', ' ', $withoutSha256);
    if (!is_string($withoutSha1)) {
        $withoutSha1 = $withoutSha256;
    }
    if (preg_match_all('/\b[a-f0-9]{32}\b/i', $withoutSha1, $matches)) {
        foreach ((array) ($matches[0] ?? []) as $match) {
            $push('md5', strtolower((string) $match));
        }
    }
    if (preg_match_all('/\b[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,63}\b/i', $clean, $matches)) {
        foreach ((array) ($matches[0] ?? []) as $match) {
            $push('email', strtolower((string) $match));
        }
    }
    if (preg_match_all('/\bCVE-\d{4}-\d{4,}\b/i', $clean, $matches)) {
        foreach ((array) ($matches[0] ?? []) as $match) {
            $push('cve', strtoupper((string) $match));
        }
    }

    usort($artifacts, static function (array $a, array $b): int {
        $typeCmp = strcmp((string) ($a['type'] ?? ''), (string) ($b['type'] ?? ''));
        if ($typeCmp !== 0) {
            return $typeCmp;
        }
        return strcmp((string) ($a['value'] ?? ''), (string) ($b['value'] ?? ''));
    });

    return $artifacts;
}

function cfintel_decode_is_useful_text(string $value): bool
{
    $trimmed = trim($value);
    if ($trimmed === '') {
        return false;
    }
    $length = strlen($trimmed);
    if ($length < 4) {
        return false;
    }
    $printable = preg_match_all('/[\P{C}\t\r\n]/u', $trimmed, $matches);
    if (!is_int($printable) || $printable <= 0) {
        return false;
    }
    return ($printable / max(1, $length)) >= 0.7;
}

function cfintel_decode_candidate_base64(string $value, bool $urlSafe = false): string
{
    $candidate = preg_replace('/\s+/', '', $value);
    if (!is_string($candidate) || $candidate === '' || strlen($candidate) < 12) {
        return '';
    }
    if ($urlSafe) {
        $candidate = strtr($candidate, '-_', '+/');
    }
    if (!preg_match('/^[A-Za-z0-9+\/]+=*$/', $candidate)) {
        return '';
    }
    $padding = strlen($candidate) % 4;
    if ($padding !== 0) {
        $candidate .= str_repeat('=', 4 - $padding);
    }
    $decoded = base64_decode($candidate, true);
    if (!is_string($decoded) || $decoded === '' || !cfintel_decode_is_useful_text($decoded)) {
        return '';
    }
    return $decoded;
}

function cfintel_decode_candidate_hex(string $value): string
{
    $clean = preg_replace('/[\s:]/', '', $value);
    if (!is_string($clean) || $clean === '' || strlen($clean) < 8 || (strlen($clean) % 2) !== 0) {
        return '';
    }
    if (!preg_match('/^[0-9a-f]+$/i', $clean)) {
        return '';
    }
    $decoded = @hex2bin($clean);
    if (!is_string($decoded) || $decoded === '' || !cfintel_decode_is_useful_text($decoded)) {
        return '';
    }
    return $decoded;
}

function cfintel_decode_suggestions(array $candidates): array
{
    $suggestions = [];
    $seen = [];
    $push = static function (string $value) use (&$suggestions, &$seen): void {
        $key = strtolower(trim($value));
        if ($key === '' || isset($seen[$key])) {
            return;
        }
        $seen[$key] = true;
        $suggestions[] = $value;
    };

    foreach ($candidates as $candidate) {
        $value = trim((string) $candidate);
        if ($value === '') {
            continue;
        }
        $urlDecoded = rawurldecode($value);
        if ($urlDecoded !== $value && cfintel_decode_is_useful_text($urlDecoded)) {
            $push('url');
            $b64AfterUrl = cfintel_decode_candidate_base64($urlDecoded, false);
            if ($b64AfterUrl !== '') {
                $push('url->base64');
            }
        }
        $b64 = cfintel_decode_candidate_base64($value, false);
        if ($b64 !== '') {
            $push('base64');
            $rot13After = str_rot13($b64);
            if ($rot13After !== $b64 && cfintel_decode_is_useful_text($rot13After)) {
                $push('base64->rot13');
            }
        }
        $b64Url = cfintel_decode_candidate_base64($value, true);
        if ($b64Url !== '') {
            $push('base64url');
        }
        $hex = cfintel_decode_candidate_hex($value);
        if ($hex !== '') {
            $push('hex');
        }
        $rot13 = str_rot13($value);
        if ($rot13 !== $value && cfintel_decode_is_useful_text($rot13)) {
            $push('rot13');
        }
    }
    return $suggestions;
}

function cfintel_parse_decode_chains(string $raw): array
{
    $raw = trim($raw);
    if ($raw === '') {
        return [];
    }
    $chunks = preg_split('/[\r\n;]+/', $raw) ?: [];
    $chains = [];
    foreach ($chunks as $chunk) {
        $chunk = trim((string) $chunk);
        if ($chunk === '') {
            continue;
        }
        $parts = preg_split('/\s*(?:->|>)\s*/', $chunk) ?: [];
        $tokens = [];
        foreach ($parts as $part) {
            $token = strtolower(trim((string) $part));
            if ($token === '') {
                continue;
            }
            $tokens[] = $token;
        }
        if (!empty($tokens)) {
            $chains[] = $tokens;
        }
    }
    return $chains;
}

function cfintel_decode_chain(string $value, array $chain): string
{
    $current = trim($value);
    if ($current === '' || empty($chain)) {
        return '';
    }
    foreach ($chain as $token) {
        switch ($token) {
            case 'url':
            case 'urldecode':
                $next = rawurldecode($current);
                break;
            case 'base64':
                $next = cfintel_decode_candidate_base64($current, false);
                break;
            case 'base64url':
            case 'b64url':
                $next = cfintel_decode_candidate_base64($current, true);
                break;
            case 'hex':
                $next = cfintel_decode_candidate_hex($current);
                break;
            case 'rot13':
                $next = str_rot13($current);
                break;
            default:
                return '';
        }
        if (!is_string($next) || $next === '') {
            return '';
        }
        $current = $next;
    }
    if (!cfintel_decode_is_useful_text($current)) {
        return '';
    }
    return $current;
}

function cfintel_decode_value(string $value): array
{
    $raw = trim($value);
    if ($raw === '') {
        return [];
    }
    $results = [];

    $urlDecoded = rawurldecode($raw);
    if ($urlDecoded !== $raw && cfintel_decode_is_useful_text($urlDecoded)) {
        $results['url_decoded'] = $urlDecoded;
    }

    $b64 = cfintel_decode_candidate_base64($raw, false);
    if ($b64 !== '') {
        $results['base64'] = $b64;
        $nested = cfintel_decode_candidate_base64(trim($b64), false);
        if ($nested !== '' && $nested !== $b64) {
            $results['base64_double'] = $nested;
        }
    }
    $b64Url = cfintel_decode_candidate_base64($raw, true);
    if ($b64Url !== '' && !isset($results['base64'])) {
        $results['base64url'] = $b64Url;
    }

    $hexDecoded = cfintel_decode_candidate_hex($raw);
    if ($hexDecoded !== '') {
        $results['hex'] = $hexDecoded;
    }

    $rot13 = str_rot13($raw);
    if ($rot13 !== $raw && cfintel_decode_is_useful_text($rot13)) {
        $results['rot13'] = $rot13;
    }

    $parts = explode('.', $raw);
    if (count($parts) === 3) {
        $jwt = [];
        $header = cfintel_decode_candidate_base64($parts[0], true);
        $payload = cfintel_decode_candidate_base64($parts[1], true);
        if ($header !== '') {
            $jwt['header'] = $header;
        }
        if ($payload !== '') {
            $jwt['payload'] = $payload;
        }
        if (!empty($jwt)) {
            $jwt['signature'] = trim((string) $parts[2]);
            $results['jwt'] = $jwt;
        }
    }

    return $results;
}

function cfintel_extract_targets_from_text(string $text): array
{
    $text = trim(cfintel_refang_text($text));
    if ($text === '') {
        return [];
    }
    $result = [];
    $seen = [];

    $push = static function (string $value) use (&$result, &$seen): void {
        $clean = trim($value);
        if ($clean === '') {
            return;
        }
        $clean = rtrim($clean, ".,;:!?)]]}>'\"");
        $meta = cfintel_target_meta($clean);
        if (($meta['type'] ?? 'unknown') === 'unknown' || ($meta['display'] ?? '') === '') {
            return;
        }
        $key = (string) ($meta['type'] ?? 'unknown') . '|' . (string) ($meta['display'] ?? '');
        if (isset($seen[$key])) {
            return;
        }
        $seen[$key] = true;
        $result[] = [
            'value' => (string) ($meta['display'] ?? ''),
            'type' => (string) ($meta['type'] ?? 'unknown'),
        ];
    };

    if (preg_match_all('#https?://[^\s<>"\'`]+#i', $text, $matches)) {
        foreach ((array) ($matches[0] ?? []) as $match) {
            $push((string) $match);
        }
    }
    if (preg_match_all('/\b(?:\d{1,3}\.){3}\d{1,3}\b/', $text, $matches)) {
        foreach ((array) ($matches[0] ?? []) as $match) {
            if (filter_var((string) $match, FILTER_VALIDATE_IP) !== false) {
                $push((string) $match);
            }
        }
    }
    if (preg_match_all('/(?<!@)\b(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}\b/i', $text, $matches)) {
        foreach ((array) ($matches[0] ?? []) as $match) {
            $push((string) $match);
        }
    }

    return $result;
}

function cfintel_collect_investigation_targets(?array $investigation): array
{
    if (!is_array($investigation)) {
        return [];
    }
    $targets = [];
    $seen = [];
    $appendTargets = static function (array $items, string $source) use (&$targets, &$seen): void {
        foreach ($items as $item) {
            $value = trim((string) ($item['value'] ?? ''));
            $type = trim((string) ($item['type'] ?? 'unknown'));
            if ($value === '' || $type === 'unknown') {
                continue;
            }
            $key = $type . '|' . strtolower($value);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $targets[] = [
                'value' => $value,
                'type' => $type,
                'source' => $source,
            ];
        }
    };

    $appendTargets(cfintel_extract_targets_from_text((string) ($investigation['site_domain'] ?? '')), 'dominio principal');
    $appendTargets(cfintel_extract_targets_from_text((string) ($investigation['summary'] ?? '')), 'resumen');

    $graph = is_array($investigation['graph'] ?? null) ? $investigation['graph'] : ['nodes' => [], 'edges' => []];
    foreach ((array) ($graph['nodes'] ?? []) as $node) {
        $nodeLabel = trim((string) ($node['label'] ?? ''));
        $nodeSource = $nodeLabel !== '' ? ('nodo: ' . $nodeLabel) : 'nodo';
        $appendTargets(cfintel_extract_targets_from_text($nodeLabel), $nodeSource);
        $appendTargets(cfintel_extract_targets_from_text((string) ($node['notes'] ?? '')), $nodeSource . ' / notas');
        foreach ((array) ($node['tags'] ?? []) as $tag) {
            $appendTargets(cfintel_extract_targets_from_text((string) $tag), $nodeSource . ' / tag');
        }
    }

    usort($targets, static function (array $a, array $b): int {
        $typeCmp = strcmp((string) ($a['type'] ?? ''), (string) ($b['type'] ?? ''));
        if ($typeCmp !== 0) {
            return $typeCmp;
        }
        return strcmp((string) ($a['value'] ?? ''), (string) ($b['value'] ?? ''));
    });

    return array_slice($targets, 0, 60);
}

function cfintel_export_rows(array $targets): array
{
    $rows = [];
    foreach ($targets as $target) {
        $value = trim((string) ($target['value'] ?? ''));
        $type = trim((string) ($target['type'] ?? 'unknown'));
        if ($value === '' || !in_array($type, ['domain', 'ip', 'url'], true)) {
            continue;
        }
        $rows[] = [
            'type' => $type,
            'value' => $value,
            'source' => trim((string) ($target['source'] ?? '')),
        ];
    }
    return $rows;
}

function cfintel_export_filename(array $investigation, string $format): string
{
    $base = trim((string) ($investigation['site_domain'] ?? $investigation['title'] ?? ('investigation-' . (int) ($investigation['id'] ?? 0))));
    $base = preg_replace('/[^a-z0-9._-]+/i', '-', strtolower($base)) ?? 'investigation';
    $base = trim($base, '-._');
    if ($base === '') {
        $base = 'investigation';
    }
    return $base . '-iocs.' . $format;
}

function cfintel_misp_event_payload(array $investigation, array $rows): array
{
    $info = trim((string) ($investigation['title'] ?? 'ClickFix investigation'));
    if ($info === '') {
        $info = 'ClickFix investigation';
    }
    $summary = trim((string) ($investigation['summary'] ?? ''));
    $eventDate = gmdate('Y-m-d');
    $updatedAt = trim((string) ($investigation['updated_at'] ?? ''));
    if ($updatedAt !== '') {
        $ts = strtotime($updatedAt);
        if ($ts !== false) {
            $eventDate = gmdate('Y-m-d', $ts);
        }
    }

    $attributes = [];
    foreach ($rows as $row) {
        $type = (string) ($row['type'] ?? '');
        $value = (string) ($row['value'] ?? '');
        if ($value === '') {
            continue;
        }
        $mispType = $type === 'ip' ? 'ip-dst' : $type;
        $comment = 'ClickFix Mitigator';
        $source = trim((string) ($row['source'] ?? ''));
        if ($source !== '') {
            $comment .= ' | source: ' . $source;
        }
        $attributes[] = [
            'type' => $mispType,
            'category' => 'Network activity',
            'to_ids' => true,
            'distribution' => '0',
            'value' => $value,
            'comment' => substr($comment, 0, 255),
        ];
    }

    $event = [
        'info' => substr($info, 0, 240),
        'date' => $eventDate,
        'threat_level_id' => '2',
        'analysis' => '1',
        'distribution' => '0',
        'Attribute' => $attributes,
        'Tag' => [
            ['name' => 'clickfix'],
            ['name' => 'investigation'],
        ],
    ];
    $domain = trim((string) ($investigation['site_domain'] ?? ''));
    if ($domain !== '') {
        $event['Tag'][] = ['name' => 'domain:' . $domain];
    }
    if ($summary !== '') {
        $event['EventReport'] = [[
            'name' => 'Investigation summary',
            'content' => substr($summary, 0, 20000),
            'distribution' => '0',
        ]];
    }

    return ['Event' => $event];
}

function cfintel_output_ioc_export(array $investigation, array $targets, string $format): void
{
    $rows = cfintel_export_rows($targets);
    if (empty($rows)) {
        clickfix_flash('No hay IOCs exportables en esta investigaciÃ³n.');
        clickfix_redirect('dashboard.php?page=intel&graph_id=' . (int) ($investigation['id'] ?? 0));
    }

    $format = strtolower(trim($format));
    $allowed = ['txt', 'csv', 'json', 'misp'];
    if (!in_array($format, $allowed, true)) {
        $format = 'json';
    }

    $filename = cfintel_export_filename($investigation, $format === 'misp' ? 'misp.json' : $format);
    $body = '';
    $contentType = 'application/json; charset=utf-8';

    if ($format === 'txt') {
        $contentType = 'text/plain; charset=utf-8';
        $lines = [];
        foreach ($rows as $row) {
            $lines[] = (string) ($row['value'] ?? '');
        }
        $body = implode("\r\n", $lines) . "\r\n";
    } elseif ($format === 'csv') {
        $contentType = 'text/csv; charset=utf-8';
        $fp = fopen('php://temp', 'r+');
        if ($fp !== false) {
            fputcsv($fp, ['type', 'value', 'source']);
            foreach ($rows as $row) {
                fputcsv($fp, [
                    (string) ($row['type'] ?? ''),
                    (string) ($row['value'] ?? ''),
                    (string) ($row['source'] ?? ''),
                ]);
            }
            rewind($fp);
            $body = (string) stream_get_contents($fp);
            fclose($fp);
        }
    } elseif ($format === 'misp') {
        $payload = cfintel_misp_event_payload($investigation, $rows);
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?: '{}';
    } else {
        $payload = [
            'status' => 'ok',
            'generated_at' => gmdate('c'),
            'investigation' => [
                'id' => (int) ($investigation['id'] ?? 0),
                'title' => (string) ($investigation['title'] ?? ''),
                'site_domain' => (string) ($investigation['site_domain'] ?? ''),
                'verdict' => (string) ($investigation['verdict'] ?? ''),
            ],
            'count' => count($rows),
            'iocs' => $rows,
        ];
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?: '{}';
    }

    header('Content-Type: ' . $contentType);
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store');
    echo $body;
    exit;
}

function cfintel_virustotal_gui_url(string $target): string
{
    $meta = cfintel_target_meta($target);
    if ($meta['type'] === 'ip' && $meta['ip'] !== '') {
        return 'https://www.virustotal.com/gui/ip-address/' . rawurlencode($meta['ip']) . '/detection';
    }
    if ($meta['type'] === 'url' && $meta['url'] !== '') {
        $urlId = rtrim(strtr(base64_encode($meta['url']), '+/', '-_'), '=');
        return 'https://www.virustotal.com/gui/url/' . rawurlencode($urlId) . '/detection';
    }
    if ($meta['type'] === 'domain' && $meta['domain'] !== '') {
        return 'https://www.virustotal.com/gui/domain/' . rawurlencode($meta['domain']) . '/detection';
    }
    return '';
}

function cfintel_virustotal_summary(array $summary): array
{
    $hasStats = isset($summary['malicious']) || isset($summary['suspicious']) || isset($summary['harmless']) || isset($summary['undetected']);
    if (!$hasStats) {
        return [];
    }
    return [
        'malicious' => (int) ($summary['malicious'] ?? 0),
        'suspicious' => (int) ($summary['suspicious'] ?? 0),
        'harmless' => (int) ($summary['harmless'] ?? 0),
        'undetected' => (int) ($summary['undetected'] ?? 0),
    ];
}

function cfintel_normalize_keyword(string $value): string
{
    $keyword = strtolower(trim($value));
    if ($keyword === '') {
        return '';
    }
    $keyword = preg_replace('/\s+/', '_', $keyword);
    if (!is_string($keyword) || $keyword === '') {
        return '';
    }
    $keyword = preg_replace('/[^a-z0-9._:-]/', '', $keyword);
    if (!is_string($keyword) || $keyword === '') {
        return '';
    }
    $keyword = trim($keyword, '._:-');
    if ($keyword === '' || strlen($keyword) < 3 || strlen($keyword) > 48) {
        return '';
    }
    return $keyword;
}

function cfintel_add_keyword(array &$keywords, array &$seen, string $value): void
{
    $keyword = cfintel_normalize_keyword($value);
    if ($keyword === '') {
        return;
    }
    if (isset($seen[$keyword])) {
        return;
    }
    $seen[$keyword] = true;
    $keywords[] = $keyword;
}

function cfintel_lookup_compact_details(array $row): array
{
    $provider = strtolower(trim((string) ($row['provider'] ?? 'unknown')));
    $summary = is_array($row['summary'] ?? null) ? $row['summary'] : [];
    $responseRaw = trim((string) ($row['response_json'] ?? ''));
    $response = json_decode($responseRaw, true);
    if (!is_array($response)) {
        $response = [];
    }

    $details = [
        'related_domain' => '',
        'related_ip' => '',
        'country_code' => '',
        'country_name' => '',
        'isp' => '',
        'usage_type' => '',
        'resolved_from' => '',
        'query_ip' => '',
        'abuse_score' => 0,
        'total_reports' => 0,
        'vt_reputation' => 0,
        'vt_registrar' => '',
        'vt_cert_issuer' => '',
        'vt_malicious_engines' => [],
        'vt_malicious_labels' => [],
        'hostnames' => [],
    ];
    $keywords = [];
    $seen = [];

    cfintel_add_keyword($keywords, $seen, $provider);
    cfintel_add_keyword($keywords, $seen, (string) ($row['target_type'] ?? 'unknown'));

    if ($provider === 'virustotal') {
        $malicious = (int) ($summary['malicious'] ?? 0);
        $suspicious = (int) ($summary['suspicious'] ?? 0);
        if ($malicious > 0) {
            cfintel_add_keyword($keywords, $seen, 'malicious');
        }
        if ($suspicious > 0) {
            cfintel_add_keyword($keywords, $seen, 'suspicious');
        }
        $data = is_array($response['data'] ?? null) ? $response['data'] : [];
        $attributes = is_array($data['attributes'] ?? null) ? $data['attributes'] : [];
        $domainId = clickfix_normalize_domain((string) ($data['id'] ?? ''));
        if ($domainId !== '') {
            $details['related_domain'] = $domainId;
        }
        $details['vt_reputation'] = (int) ($attributes['reputation'] ?? 0);
        $details['vt_registrar'] = (string) ($attributes['registrar'] ?? '');
        $cert = is_array($attributes['last_https_certificate'] ?? null) ? $attributes['last_https_certificate'] : [];
        $issuer = is_array($cert['issuer'] ?? null) ? $cert['issuer'] : [];
        $details['vt_cert_issuer'] = (string) ($issuer['CN'] ?? ($issuer['O'] ?? ''));

        $dnsRecords = is_array($attributes['last_dns_records'] ?? null) ? $attributes['last_dns_records'] : [];
        foreach ($dnsRecords as $dnsRow) {
            if (!is_array($dnsRow)) {
                continue;
            }
            $type = strtoupper(trim((string) ($dnsRow['type'] ?? '')));
            $value = trim((string) ($dnsRow['value'] ?? ''));
            if ($type === 'A' && filter_var($value, FILTER_VALIDATE_IP)) {
                $details['related_ip'] = $value;
                break;
            }
        }

        $analysis = is_array($attributes['last_analysis_results'] ?? null) ? $attributes['last_analysis_results'] : [];
        $malEngines = [];
        $malLabels = [];
        foreach ($analysis as $engineName => $analysisRow) {
            if (!is_array($analysisRow)) {
                continue;
            }
            $category = strtolower(trim((string) ($analysisRow['category'] ?? '')));
            $result = strtolower(trim((string) ($analysisRow['result'] ?? '')));
            if ($category === 'malicious') {
                $engine = trim((string) $engineName);
                if ($engine !== '' && count($malEngines) < 10 && !in_array($engine, $malEngines, true)) {
                    $malEngines[] = $engine;
                    cfintel_add_keyword($keywords, $seen, $engine);
                }
            }
            if ($result !== '' && !in_array($result, ['clean', 'unrated', 'malicious', 'suspicious'], true)) {
                if (count($malLabels) < 8 && !in_array($result, $malLabels, true)) {
                    $malLabels[] = $result;
                    cfintel_add_keyword($keywords, $seen, $result);
                }
            }
        }
        $details['vt_malicious_engines'] = $malEngines;
        $details['vt_malicious_labels'] = $malLabels;
        cfintel_add_keyword($keywords, $seen, (string) $details['vt_cert_issuer']);
        cfintel_add_keyword($keywords, $seen, (string) $details['vt_registrar']);
    } elseif ($provider === 'abuseipdb') {
        $details['abuse_score'] = (int) ($summary['abuseConfidenceScore'] ?? 0);
        $details['total_reports'] = (int) ($summary['totalReports'] ?? 0);
        $details['country_code'] = strtoupper(trim((string) ($summary['countryCode'] ?? '')));
        $details['isp'] = trim((string) ($summary['isp'] ?? ''));
        $details['query_ip'] = trim((string) ($summary['queryIp'] ?? ''));
        $details['resolved_from'] = clickfix_normalize_domain((string) ($summary['resolvedFrom'] ?? ''));
        if (filter_var($details['query_ip'], FILTER_VALIDATE_IP)) {
            $details['related_ip'] = $details['query_ip'];
        }
        if ($details['resolved_from'] !== '') {
            $details['related_domain'] = $details['resolved_from'];
        }

        $data = is_array($response['data'] ?? null) ? $response['data'] : [];
        $domain = clickfix_normalize_domain((string) ($data['domain'] ?? ''));
        if ($domain !== '') {
            $details['related_domain'] = $domain;
        }
        $dataIp = trim((string) ($data['ipAddress'] ?? ''));
        if (filter_var($dataIp, FILTER_VALIDATE_IP)) {
            $details['related_ip'] = $dataIp;
            $details['query_ip'] = $dataIp;
        }
        $countryName = trim((string) ($data['countryName'] ?? ''));
        if ($countryName !== '') {
            $details['country_name'] = $countryName;
        }
        $usageType = trim((string) ($data['usageType'] ?? ''));
        if ($usageType !== '') {
            $details['usage_type'] = $usageType;
        }
        $hostnames = is_array($data['hostnames'] ?? null) ? $data['hostnames'] : [];
        foreach ($hostnames as $host) {
            $normalizedHost = clickfix_normalize_domain((string) $host);
            if ($normalizedHost !== '' && !in_array($normalizedHost, $details['hostnames'], true) && count($details['hostnames']) < 8) {
                $details['hostnames'][] = $normalizedHost;
            }
        }
        if ($details['abuse_score'] >= 70) {
            cfintel_add_keyword($keywords, $seen, 'abuse_high');
        } elseif ($details['abuse_score'] > 0) {
            cfintel_add_keyword($keywords, $seen, 'abuse_positive');
        }
        if ($details['total_reports'] > 0) {
            cfintel_add_keyword($keywords, $seen, 'reported_ip');
        }
        if ($details['country_code'] !== '') {
            cfintel_add_keyword($keywords, $seen, 'cc_' . $details['country_code']);
        }
        cfintel_add_keyword($keywords, $seen, (string) $details['usage_type']);
        cfintel_add_keyword($keywords, $seen, (string) $details['isp']);
    } elseif ($provider === 'urlscan') {
        $total = (int) ($summary['total'] ?? 0);
        if ($total > 0) {
            cfintel_add_keyword($keywords, $seen, 'urlscan_hits');
        }
        $results = is_array($response['results'] ?? null) ? $response['results'] : [];
        if (!empty($results[0]) && is_array($results[0])) {
            $first = $results[0];
            $page = is_array($first['page'] ?? null) ? $first['page'] : [];
            $task = is_array($first['task'] ?? null) ? $first['task'] : [];
            $domain = clickfix_normalize_domain((string) ($page['domain'] ?? ($task['domain'] ?? '')));
            if ($domain !== '') {
                $details['related_domain'] = $domain;
            }
            $pageIp = trim((string) ($page['ip'] ?? ''));
            if (filter_var($pageIp, FILTER_VALIDATE_IP)) {
                $details['related_ip'] = $pageIp;
            }
            $country = trim((string) ($page['country'] ?? ''));
            if ($country !== '') {
                $details['country_code'] = strtoupper($country);
            }
        }
    }

    if ($details['related_domain'] !== '') {
        cfintel_add_keyword($keywords, $seen, $details['related_domain']);
    }
    if ($details['related_ip'] !== '') {
        cfintel_add_keyword($keywords, $seen, 'ip_' . str_replace('.', '_', $details['related_ip']));
    }
    if ($details['country_name'] !== '') {
        cfintel_add_keyword($keywords, $seen, $details['country_name']);
    }
    foreach ($details['hostnames'] as $hostname) {
        cfintel_add_keyword($keywords, $seen, $hostname);
    }

    return [
        'details' => $details,
        'keywords' => $keywords,
    ];
}

function cfintel_common_api_keywords(array $lookupRows, int $limit = 16): array
{
    $limit = max(5, min(50, $limit));
    if (empty($lookupRows)) {
        return [];
    }
    $stopwords = [
        'unknown' => true,
        'domain' => true,
        'url' => true,
        'ip' => true,
        'data' => true,
        'result' => true,
        'results' => true,
        'api' => true,
    ];
    $counts = [];
    foreach ($lookupRows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $rowKeywords = is_array($row['keywords'] ?? null) ? $row['keywords'] : [];
        if (empty($rowKeywords)) {
            $enriched = cfintel_lookup_compact_details($row);
            $rowKeywords = is_array($enriched['keywords'] ?? null) ? $enriched['keywords'] : [];
        }
        $seenRow = [];
        foreach ($rowKeywords as $keywordRaw) {
            $keyword = cfintel_normalize_keyword((string) $keywordRaw);
            if ($keyword === '' || isset($stopwords[$keyword])) {
                continue;
            }
            if (isset($seenRow[$keyword])) {
                continue;
            }
            $seenRow[$keyword] = true;
            $counts[$keyword] = (int) ($counts[$keyword] ?? 0) + 1;
        }
    }
    if (empty($counts)) {
        return [];
    }
    arsort($counts);
    $items = [];
    foreach ($counts as $keyword => $hits) {
        if ($hits <= 0) {
            continue;
        }
        $items[] = [
            'keyword' => (string) $keyword,
            'hits' => (int) $hits,
        ];
        if (count($items) >= $limit) {
            break;
        }
    }
    return $items;
}

$canViewExactEventContext = $loggedIn && cfcan($user, 'analyst_sr');

function cfreasonlabel(string $key): string
{
    $labels = [
        'alertMismatch' => 'Clipboard mismatch',
        'alertClipboardCommand' => 'Clipboard command pattern',
        'alertCommand' => 'Command pattern in page',
        'alertWinR' => 'Win+R instruction',
        'alertWinX' => 'Win+X instruction',
        'alertBrowserError' => 'Fake browser error lure',
        'alertFixAction' => 'Fake fix action',
        'alertCaptcha' => 'Fake captcha / verification lure',
        'alertConsole' => 'DevTools console instruction',
        'alertShell' => 'Shell or terminal instruction',
        'alertPasteSequence' => 'Paste + execute sequence',
        'alertFileExplorer' => 'File explorer execution lure',
        'alertCopyTrigger' => 'Forced copy trigger',
        'alertEvasion' => 'Obfuscation / evasion',
        'alertSnippet' => 'Detected snippet',
        'alertClipboardBlocked' => 'Clipboard content blocked',
        'alertConfidenceScore' => 'Confidence score',
    ];
    return $labels[$key] ?? $key;
}

function cft(string $key): string
{
    global $lang;
    $dict = [
        'es' => [
            'nav_home' => 'Inicio',
            'nav_search' => 'B?squeda',
            'nav_coverage' => 'Cobertura',
            'nav_about' => 'Acerca',
            'nav_access' => 'Acceso',
            'nav_profile' => 'Perfil',
            'nav_settings' => 'Ajustes',
            'nav_ops' => 'Operaciones',
            'nav_graphs' => 'GrÃ¡ficos',
            'nav_intel_stats' => 'Intel Stats',
            'nav_investigation' => 'InvestigaciÃ³n',
            'nav_community' => 'Community',
            'nav_extensions' => 'Extensiones',
            'nav_lists' => 'Listas',
            'nav_requests' => 'Solicitudes',
            'nav_messaging' => 'Mensajeria',
            'nav_data_center' => 'Centro Datos',
            'nav_score_config' => 'Score Config',
            'nav_reports' => 'Reportes',
            'nav_users' => 'Usuarios',
            'lang_label' => 'Idioma',
            'lang_es' => 'EspaÃ±ol',
            'lang_en' => 'English',
            'lang_ca' => 'Catala',
            'lang_de' => 'Aleman',
            'lang_fr' => 'Frances',
            'lang_it' => 'Italiano',
            'label_module' => 'MÃ³dulo',
            'label_role' => 'Rol',
            'dc_title' => 'Centro de datos',
            'dc_sub' => 'Estado de tablas, volumen y consulta rÃ¡pida del contenido operacional.',
            'msg_title' => 'Mensajeria para extensiones',
            'cfg_title' => 'Editor de score config',
            'reports_title' => 'Reportes automÃ¡ticos',
            'support_title' => 'Apoya el proyecto',
            'support_sub' => 'Ayuda a mantener ClickFix con donaciones o patrocinio.',
            'support_ads' => 'Patrocinado',
            'support_donations' => 'Donaciones',
            'support_paypal' => 'Donar con PayPal',
            'support_kofi' => 'Invitar un cafe (Ko-fi)',
            'support_stripe' => 'Aportar con Stripe',
            'intel_api_keys_title' => 'API keys privadas',
            'intel_api_keys_sub' => 'Solo tu usuario puede ver, modificar y usar estas claves en investigaciÃ³n.',
            'intel_api_keys_in_settings' => 'Las API keys privadas se gestionan en Ajustes.',
            'intel_api_key_save' => 'Guardar clave',
            'intel_api_key_delete' => 'Eliminar clave',
            'intel_api_key_masked' => 'Guardada',
            'intel_api_key_updated' => 'Actualizada',
            'intel_api_lookup_title' => 'Consulta IOC con tus APIs',
            'intel_api_lookup_sub' => 'Usa tu clave guardada para consultar dominio/IP/URL segun proveedor.',
            'intel_api_lookup_target' => 'Indicador (dominio, IP o URL)',
            'intel_api_lookup_button' => 'Consultar',
            'intel_api_lookup_result' => 'Resultado de consulta',
            'intel_iocs_title' => 'IOCs detectados en esta investigaciÃ³n',
            'intel_iocs_sub' => 'IPs, dominios y URLs extraidos del dominio principal, resumen, nodos, tags y notas. Puedes lanzarlos directamente a proveedores sin copiar/pegar.',
            'intel_iocs_empty' => 'No se han detectado IOCs reutilizables todavÃ­a dentro del grafo actual.',
            'intel_manual_ioc_title' => 'Anadir IOC manualmente',
            'intel_manual_ioc_sub' => 'Se guarda como nodo del grafo y aparece en esta lista si es dominio, IP o URL.',
            'intel_manual_ioc_label' => 'IOC',
            'intel_manual_ioc_type' => 'Tipo',
            'intel_manual_ioc_auto' => 'auto-detectar',
            'intel_manual_ioc_button' => 'Anadir IOC',
            'intel_lookup_fallback_title' => 'Consulta de proveedores de inteligencia',
            'intel_lookup_fallback_sub' => 'Selecciona proveedor e indicador (dominio, IP o URL).',
            'intel_briefing_kicker' => 'Briefing',
            'intel_briefing_title' => 'Contexto principal del caso',
            'intel_briefing_sub' => 'Define el foco, la narrativa analÃ­tica y los datos clave antes de enriquecer o compartir.',
            'intel_autosave_label' => 'Autosave:',
            'intel_briefing_title_label' => 'Título',
            'intel_briefing_domain_label' => 'Dominio principal',
            'intel_briefing_domain_placeholder' => 'ejemplo.com',
            'intel_briefing_verdict_label' => 'Veredicto',
            'intel_briefing_tags_label' => 'Tags globales',
            'intel_briefing_tags_placeholder' => 'phishing, fake-captcha, powershell',
            'intel_briefing_summary_label' => 'Resumen de la investigaciÃ³n',
            'intel_briefing_summary_placeholder' => 'Explica por que se considera malicioso o no.',
            'intel_briefing_save' => 'Guardar investigaciÃ³n',
            'intel_enrichment_kicker' => 'Enrichment',
            'intel_enrichment_title' => 'Fuentes de investigaciÃ³n',
            'intel_enrichment_sub_full' => 'Gestion de credenciales personales, pivotes y consultas historicas de enrichment.',
            'intel_enrichment_sub_lite' => 'Consulta proveedores de inteligencia sin exponer credenciales en interfaz.',
            'intel_platform_api_title' => 'API de plataforma (con API key)',
            'intel_platform_api_sub_line1' => 'Genera API keys personales para consumir /api/intel.php y /api/lookup.php.',
            'intel_platform_api_sub_line2' => 'Se guarda solo hash, se puede revocar y tiene expiracion/rate-limit por clave.',
            'intel_platform_api_sub_line3' => 'Documentacion:',
            'intel_platform_api_docs' => 'API INTEGRATIONS',
            'intel_platform_api_new_title' => 'API key nueva (se muestra una sola vez)',
            'intel_platform_api_new_sub' => 'CÃ³piala ahora y guÃ¡rdala en un vault seguro. No se volvera a mostrar completa.',
            'intel_platform_api_label' => 'Etiqueta',
            'intel_platform_api_expires' => 'Expira en dÃ­as (1-365)',
            'intel_platform_api_rpm' => 'Rate limit RPM (30-2000)',
            'intel_platform_api_generate' => 'Generar API key segura',
            'intel_platform_api_hidden' => 'La gestion manual de credenciales y la API de plataforma estan ocultas en esta vista.',
            'announce_title' => 'Anuncios operativos',
            'announce_sub' => 'Lateral, discreto y no intrusivo.',
            'announce_focus_note' => 'Modo investigaciÃ³n activo: el panel se muestra minimizado para mantener el foco.',
            'announce_empty' => 'No hay anuncios activos.',
            'announce_until' => 'hasta',
            'announce_guest_title' => 'Patrocinios discretos',
            'announce_guest_text' => 'Los anuncios se muestran solo en el lateral para no interrumpir an?lisis ni investigaciones.',
            'review_pending' => 'pending - pendiente de revisiÃ³n',
            'review_accepted' => 'accepted - malicioso confirmado (ilegitimo)',
            'review_rejected' => 'rejected - legitimo o falso positivo',
            'review_allowlisted' => 'allowlisted - enviar a lista blanca',
            'review_legend_title' => 'Guia de revisiÃ³n',
            'review_legend_pending' => 'pending: evento aÃºn no validado por analista.',
            'review_legend_accepted' => 'accepted: la alerta se confirma como amenaza real (evento ilegitimo/malicioso).',
            'review_legend_rejected' => 'rejected: la alerta se marca como legitima o falso positivo.',
            'review_legend_allowlisted' => 'allowlisted: el dominio se considera legitimo y se manda a allowlist.',
            'about_project_title' => 'Mision y enfoque',
            'about_project_intro' => 'ClickFix Mitigator es una plataforma defensiva para detectar, contener y analizar ataques basados en ingenieria social web (ClickFix y variantes de ejecucion guiada).',
            'about_project_p1' => 'Diseno orientado a operaciones reales: baja friccion para usuario final y alta trazabilidad para equipos SOC.',
            'about_project_p2' => 'Cobertura integral: extension, backend de correlaciÃ³n, workflows de triage, evidencias y analÃ­tica operacional.',
            'about_project_p3' => 'Modelo de madurez: desde prevenciÃ³n inmediata en navegador hasta investigaciÃ³n profunda con contexto histÃ³rico.',
            'about_project_p4' => 'Principio de seguridad: mÃ­nimo privilegio, control por roles, reducciÃ³n de superficie de ataque y auditorÃ­a de acciones.',
            'about_project_p5' => 'Objetivo de negocio: reducir riesgo operativo, acelerar respuesta a incidentes y mejorar la resiliencia de usuarios y organizaciones.',
            'about_owner_title' => 'Direccion del proyecto y contacto',
            'about_owner_text' => 'Proyecto mantenido activamente con foco en calidad t?cnica, hardening continuo y utilidad practica para investigaciÃ³n y defensa.',
            'about_contact_links' => 'Canales oficiales',
        ],
        'en' => [
            'nav_home' => 'Home',
            'nav_search' => 'Search',
            'nav_coverage' => 'Coverage',
            'nav_about' => 'About',
            'nav_access' => 'Access',
            'nav_profile' => 'Profile',
            'nav_settings' => 'Settings',
            'nav_ops' => 'Operations',
            'nav_graphs' => 'Analytics',
            'nav_intel_stats' => 'Intel Stats',
            'nav_investigation' => 'Investigation',
            'nav_community' => 'Community',
            'nav_extensions' => 'Extensions',
            'nav_lists' => 'Lists',
            'nav_requests' => 'Requests',
            'nav_messaging' => 'Messaging',
            'nav_data_center' => 'Data Center',
            'nav_score_config' => 'Score Config',
            'nav_reports' => 'Reports',
            'nav_users' => 'Users',
            'lang_label' => 'Language',
            'lang_es' => 'Spanish',
            'lang_en' => 'English',
            'lang_ca' => 'Catalan',
            'lang_de' => 'German',
            'lang_fr' => 'French',
            'lang_it' => 'Italian',
            'label_module' => 'Module',
            'label_role' => 'Role',
            'dc_title' => 'Data center',
            'dc_sub' => 'Table health, volume and quick query for operational data.',
            'msg_title' => 'Extension messaging',
            'cfg_title' => 'Score config editor',
            'reports_title' => 'Automated reports',
            'support_title' => 'Support the project',
            'support_sub' => 'Help sustain ClickFix with donations or sponsorship.',
            'support_ads' => 'Sponsored',
            'support_donations' => 'Donations',
            'support_paypal' => 'Donate with PayPal',
            'support_kofi' => 'Buy me a coffee (Ko-fi)',
            'support_stripe' => 'Contribute with Stripe',
            'intel_api_keys_title' => 'Private API keys',
            'intel_api_keys_sub' => 'Only your user can view, edit, and use these keys in investigation.',
            'intel_api_keys_in_settings' => 'Private API keys are managed in Settings.',
            'intel_api_key_save' => 'Save key',
            'intel_api_key_delete' => 'Delete key',
            'intel_api_key_masked' => 'Stored',
            'intel_api_key_updated' => 'Updated',
            'intel_api_lookup_title' => 'IOC lookup with your APIs',
            'intel_api_lookup_sub' => 'Use your saved key to query domain/IP/URL depending on provider.',
            'intel_api_lookup_target' => 'Indicator (domain, IP, or URL)',
            'intel_api_lookup_button' => 'Lookup',
            'intel_api_lookup_result' => 'Lookup result',
            'intel_iocs_title' => 'IOCs detected in this investigation',
            'intel_iocs_sub' => 'IPs, domains, and URLs extracted from the primary domain, summary, nodes, tags, and notes. You can launch them directly to providers without copy/paste.',
            'intel_iocs_empty' => 'No reusable IOCs detected yet in the current graph.',
            'intel_manual_ioc_title' => 'Add IOC manually',
            'intel_manual_ioc_sub' => 'Saved as a graph node and shown here when it is a domain, IP, or URL.',
            'intel_manual_ioc_label' => 'IOC',
            'intel_manual_ioc_type' => 'Type',
            'intel_manual_ioc_auto' => 'auto-detect',
            'intel_manual_ioc_button' => 'Add IOC',
            'intel_lookup_fallback_title' => 'Threat intel provider lookup',
            'intel_lookup_fallback_sub' => 'Select provider and indicator (domain, IP, or URL).',
            'intel_briefing_kicker' => 'Briefing',
            'intel_briefing_title' => 'Primary case context',
            'intel_briefing_sub' => 'Define focus, analytic narrative, and key facts before enrichment or sharing.',
            'intel_autosave_label' => 'Autosave:',
            'intel_briefing_title_label' => 'Title',
            'intel_briefing_domain_label' => 'Primary domain',
            'intel_briefing_domain_placeholder' => 'example.com',
            'intel_briefing_verdict_label' => 'Verdict',
            'intel_briefing_tags_label' => 'Global tags',
            'intel_briefing_tags_placeholder' => 'phishing, fake-captcha, powershell',
            'intel_briefing_summary_label' => 'Investigation summary',
            'intel_briefing_summary_placeholder' => 'Explain why it is considered malicious or not.',
            'intel_briefing_save' => 'Save investigation',
            'intel_enrichment_kicker' => 'Enrichment',
            'intel_enrichment_title' => 'Investigation sources',
            'intel_enrichment_sub_full' => 'Personal credentials, pivots, and historical enrichment lookups.',
            'intel_enrichment_sub_lite' => 'Query intel providers without exposing credentials in the UI.',
            'intel_platform_api_title' => 'Platform API (with API key)',
            'intel_platform_api_sub_line1' => 'Generate personal API keys to consume /api/intel.php and /api/lookup.php.',
            'intel_platform_api_sub_line2' => 'Only a hash is stored, it can be revoked, and has per-key expiry/rate limit.',
            'intel_platform_api_sub_line3' => 'Documentation:',
            'intel_platform_api_docs' => 'API INTEGRATIONS',
            'intel_platform_api_new_title' => 'New API key (shown once)',
            'intel_platform_api_new_sub' => 'Copy it now and store it in a secure vault. It will not be shown again in full.',
            'intel_platform_api_label' => 'Label',
            'intel_platform_api_expires' => 'Expires in days (1-365)',
            'intel_platform_api_rpm' => 'Rate limit RPM (30-2000)',
            'intel_platform_api_generate' => 'Generate secure API key',
            'intel_platform_api_hidden' => 'Manual credential management and the platform API are hidden in this view.',
            'announce_title' => 'Operational notices',
            'announce_sub' => 'Sidebar only, low-noise.',
            'announce_focus_note' => 'Investigation mode is active: panel is minimized by default to preserve focus.',
            'announce_empty' => 'No active notices.',
            'announce_until' => 'until',
            'announce_guest_title' => 'Low-noise sponsorship',
            'announce_guest_text' => 'Announcements remain in the sidebar only, so investigations stay uninterrupted.',
            'review_pending' => 'pending - pending analyst review',
            'review_accepted' => 'accepted - confirmed malicious (illegitimate)',
            'review_rejected' => 'rejected - legitimate or false positive',
            'review_allowlisted' => 'allowlisted - send to allowlist',
            'review_legend_title' => 'Review guide',
            'review_legend_pending' => 'pending: event has not been validated by an analyst yet.',
            'review_legend_accepted' => 'accepted: alert is confirmed as a real threat (illegitimate/malicious event).',
            'review_legend_rejected' => 'rejected: alert is considered legitimate activity or a false positive.',
            'review_legend_allowlisted' => 'allowlisted: domain is considered legitimate and sent to allowlist.',
            'about_project_title' => 'Mission and approach',
            'about_project_intro' => 'ClickFix Mitigator is a defensive platform to detect, contain, and investigate web social-engineering attacks (ClickFix and guided execution variants).',
            'about_project_p1' => 'Built for real operations: low friction for end users and high traceability for SOC teams.',
            'about_project_p2' => 'End-to-end coverage: browser extension, backend correlation, triage workflows, evidence handling, and operational analytics.',
            'about_project_p3' => 'Maturity model: from immediate browser-side prevention to deep investigation with historical context.',
            'about_project_p4' => 'Security principle: least privilege, role-based control, reduced attack surface, and auditable actions.',
            'about_project_p5' => 'Business objective: reduce operational risk, accelerate incident response, and improve resilience for users and organizations.',
            'about_owner_title' => 'Project leadership and contact',
            'about_owner_text' => 'Actively maintained with focus on technical quality, continuous hardening, and practical value for investigation and defense.',
            'about_contact_links' => 'Official channels',
        ],
    ];
    $dict['ca'] = array_merge($dict['es'], [
        'nav_home' => 'Inici',
        'nav_search' => 'Cerca',
        'nav_about' => 'Sobre',
        'nav_access' => 'Acces',
        'nav_settings' => 'Ajustos',
        'nav_ops' => 'Operacions',
        'nav_graphs' => 'Grafics',
        'nav_investigation' => 'Investigacio',
        'nav_community' => 'Comunitat',
        'nav_requests' => 'Sol-licituds',
        'nav_messaging' => 'Missatgeria',
        'nav_data_center' => 'Centre de dades',
        'nav_reports' => 'Informes',
        'lang_es' => 'Espanyol',
        'lang_en' => 'Angles',
        'lang_ca' => 'Catala',
        'lang_de' => 'Alemany',
        'lang_fr' => 'Frances',
        'lang_it' => 'Italia',
        'label_module' => 'Modul',
        'dc_title' => 'Centre de dades',
        'msg_title' => 'Missatgeria per a extensions',
        'support_title' => 'Dona suport al projecte',
        'intel_api_keys_title' => 'API keys privades',
        'intel_api_keys_in_settings' => 'Les API keys privades es gestionen a Ajustos.',
        'review_legend_title' => 'Guia de revisio',
        'about_project_title' => 'Missio i enfocament',
        'about_owner_title' => 'Direccio del projecte i contacte',
    ]);
    $dict['de'] = array_merge($dict['en'], [
        'nav_home' => 'Start',
        'nav_search' => 'Suche',
        'nav_coverage' => 'Abdeckung',
        'nav_about' => 'Uber uns',
        'nav_access' => 'Zugang',
        'nav_profile' => 'Profil',
        'nav_settings' => 'Einstellungen',
        'nav_ops' => 'Betrieb',
        'nav_graphs' => 'Analysen',
        'nav_investigation' => 'Untersuchung',
        'nav_extensions' => 'Erweiterungen',
        'nav_lists' => 'Listen',
        'nav_requests' => 'Anfragen',
        'nav_messaging' => 'Nachrichten',
        'nav_data_center' => 'Datenzentrum',
        'nav_reports' => 'Berichte',
        'nav_users' => 'Benutzer',
        'lang_label' => 'Sprache',
        'lang_es' => 'Spanisch',
        'lang_en' => 'Englisch',
        'lang_ca' => 'Katalanisch',
        'lang_de' => 'Deutsch',
        'lang_fr' => 'Franzosisch',
        'lang_it' => 'Italienisch',
        'label_role' => 'Rolle',
        'dc_title' => 'Datenzentrum',
        'msg_title' => 'Nachrichten fur Erweiterungen',
        'support_title' => 'Projekt unterstutzen',
        'intel_api_keys_title' => 'Private API-Schlussel',
        'intel_api_keys_in_settings' => 'Private API-Keys werden in Einstellungen verwaltet.',
        'review_legend_title' => 'Prufleitfaden',
        'about_project_title' => 'Mission und Ansatz',
        'about_owner_title' => 'Projektleitung und Kontakt',
    ]);
    $dict['fr'] = array_merge($dict['en'], [
        'nav_home' => 'Accueil',
        'nav_search' => 'Recherche',
        'nav_coverage' => 'Couverture',
        'nav_about' => 'A propos',
        'nav_access' => 'Acces',
        'nav_profile' => 'Profil',
        'nav_settings' => 'Parametres',
        'nav_ops' => 'Operations',
        'nav_graphs' => 'Analytique',
        'nav_community' => 'Communaute',
        'nav_requests' => 'Demandes',
        'nav_messaging' => 'Messagerie',
        'nav_data_center' => 'Centre de donnees',
        'nav_reports' => 'Rapports',
        'nav_users' => 'Utilisateurs',
        'lang_label' => 'Langue',
        'lang_es' => 'Espagnol',
        'lang_en' => 'Anglais',
        'lang_ca' => 'Catalan',
        'lang_de' => 'Allemand',
        'lang_fr' => 'Francais',
        'lang_it' => 'Italien',
        'label_role' => 'Role',
        'dc_title' => 'Centre de donnees',
        'msg_title' => 'Messagerie pour extensions',
        'support_title' => 'Soutenir le projet',
        'intel_api_keys_title' => 'Cles API privees',
        'intel_api_keys_in_settings' => 'Les API keys privees se gerent dans Parametres.',
        'review_legend_title' => 'Guide de revue',
        'about_project_title' => 'Mission et approche',
        'about_owner_title' => 'Direction du projet et contact',
    ]);
    if (isset($dict[$lang][$key])) {
        return (string) $dict[$lang][$key];
    }
    $fallbackByLang = ['ca' => 'es', 'de' => 'en', 'fr' => 'en', 'it' => 'en'];
    $effectiveLang = $fallbackByLang[$lang] ?? $lang;
    if (isset($dict[$effectiveLang][$key])) {
        return (string) $dict[$effectiveLang][$key];
    }
    if (isset($dict['es'][$key])) {
        return (string) $dict['es'][$key];
    }
    return $key;
}

function cfworkflowlabel(string $status, string $lang): string
{
    $status = clickfix_investigation_workflow_status($status);
    $lang = strtolower(trim($lang));
    $labels = [
        'en' => [
            'draft' => 'Draft',
            'jr_submitted' => 'JR Submitted',
            'mid_verified' => 'Mid Verified',
            'sr_review' => 'Senior Review',
            'verified_public' => 'Verified Public',
            'verified_internal' => 'Verified Internal',
            'rejected' => 'Rejected',
        ],
        'es' => [
            'draft' => 'Borrador',
            'jr_submitted' => 'Enviado por JR',
            'mid_verified' => 'Validado por Mid',
            'sr_review' => 'RevisiÃ³n Senior',
            'verified_public' => 'Verificada Publica',
            'verified_internal' => 'Verificada Interna',
            'rejected' => 'Rechazada',
        ],
        'ca' => [
            'draft' => 'Esborrany',
            'jr_submitted' => 'Enviat per JR',
            'mid_verified' => 'Validat per Mid',
            'sr_review' => 'Revisio Senior',
            'verified_public' => 'Verificada pÃºblica',
            'verified_internal' => 'Verificada interna',
            'rejected' => 'Rebutjada',
        ],
        'de' => [
            'draft' => 'Entwurf',
            'jr_submitted' => 'Von JR eingereicht',
            'mid_verified' => 'Von Mid validiert',
            'sr_review' => 'Senior Review',
            'verified_public' => 'Offentlich verifiziert',
            'verified_internal' => 'Intern verifiziert',
            'rejected' => 'Abgelehnt',
        ],
        'fr' => [
            'draft' => 'Brouillon',
            'jr_submitted' => 'Soumis par JR',
            'mid_verified' => 'Valide par Mid',
            'sr_review' => 'Revue Senior',
            'verified_public' => 'Verifie public',
            'verified_internal' => 'Verifie interne',
            'rejected' => 'Rejete',
        ],
    ];
    if (!isset($labels[$lang])) {
        $fallbackByLang = ['ca' => 'es', 'de' => 'en', 'fr' => 'en', 'it' => 'en'];
        $lang = $fallbackByLang[$lang] ?? 'en';
    }
    if (isset($labels[$lang][$status])) {
        return (string) $labels[$lang][$status];
    }
    return (string) ($labels['en'][$status] ?? $status);
}

function cfmalwarelabel(string $classification, string $lang): string
{
    $classification = strtolower(trim($classification));
    $lang = strtolower(trim($lang));
    $labels = [
        'en' => ['malware' => 'Malware', 'legit' => 'Legit', 'neutral' => 'Neutral'],
        'es' => ['malware' => 'Malware', 'legit' => 'Legitimo', 'neutral' => 'Neutral'],
        'ca' => ['malware' => 'Malware', 'legit' => 'Legitim', 'neutral' => 'Neutral'],
        'de' => ['malware' => 'Malware', 'legit' => 'Legitim', 'neutral' => 'Neutral'],
        'fr' => ['malware' => 'Malware', 'legit' => 'Legitime', 'neutral' => 'Neutre'],
    ];
    if (!isset($labels[$lang])) {
        $fallbackByLang = ['ca' => 'es', 'de' => 'en', 'fr' => 'en', 'it' => 'en'];
        $lang = $fallbackByLang[$lang] ?? 'en';
    }
    if (isset($labels[$lang][$classification])) {
        return (string) $labels[$lang][$classification];
    }
    return (string) ($labels['en']['neutral'] ?? 'Neutral');
}

function cfdashboardliteralmaps(): array
{
    static $maps = null;
    if ($maps !== null) {
        return $maps;
    }
    $maps = ['ca' => [], 'de' => [], 'fr' => []];
    $phrases = [
        'Cerrar SesiÃ³n' => ['ca' => 'Tancar sessio', 'de' => 'Abmelden', 'fr' => 'Se deconnecter'],
        'ClickFix Command Center' => ['ca' => 'ClickFix Centre de Comandament', 'de' => 'ClickFix Command Center', 'fr' => 'ClickFix Centre de Commandement'],
        'Deteccion, explicabilidad y respuesta en una sola superficie de control.' => ['ca' => 'Deteccio, explicabilitat i resposta en una sola superficie de control.', 'de' => 'Erkennung, Erklarbarkeit und Reaktion auf einer zentralen Oberflache.', 'fr' => 'Detection, explicabilite et reponse sur une surface unique de pilotage.'],
        'B?squeda forense' => ['ca' => 'Cerca forense', 'de' => 'Forensische Suche', 'fr' => 'Recherche forensique'],
        'Operaciones' => ['ca' => 'Operacions', 'de' => 'Betrieb', 'fr' => 'Operations'],
        'InvestigaciÃ³n' => ['ca' => 'Investigacio', 'de' => 'Untersuchung', 'fr' => 'Investigation'],
        'Inicio r?pido' => ['ca' => 'Inici rapid', 'de' => 'Schnellstart', 'fr' => 'Demarrage rapide'],
        'Workspace autenticado' => ['ca' => 'Workspace autenticat', 'de' => 'Authentifizierter Workspace', 'fr' => 'Workspace authentifie'],
        'Vista pÃºblica' => ['ca' => 'Vista pÃºblica', 'de' => 'Offentliche Ansicht', 'fr' => 'Vue publique'],
        'alertas totales' => ['ca' => 'alertes totals', 'de' => 'gesamtwarnungen', 'fr' => 'alertes totales'],
        'bloqueos totales' => ['ca' => 'bloquejos totals', 'de' => 'gesamtblockierungen', 'fr' => 'blocages totaux'],
        'dominios Ãºnicos' => ['ca' => 'dominis unics', 'de' => 'eindeutige Domains', 'fr' => 'domaines uniques'],
        'usuarios 24h' => ['ca' => 'usuaris 24h', 'de' => 'Benutzer 24h', 'fr' => 'utilisateurs 24h'],
        'alertas 24h' => ['ca' => 'alertes 24h', 'de' => 'Warnungen 24h', 'fr' => 'alertes 24h'],
        'bloqueos 24h' => ['ca' => 'bloquejos 24h', 'de' => 'Blockierungen 24h', 'fr' => 'blocages 24h'],
        'ratio bloqueo 24h' => ['ca' => 'ratio bloqueig 24h', 'de' => 'Blockierungsquote 24h', 'fr' => 'ratio de blocage 24h'],
        'alto riesgo 24h' => ['ca' => 'alt risc 24h', 'de' => 'hohes Risiko 24h', 'fr' => 'haut risque 24h'],
        'nuevos dominios 24h' => ['ca' => 'nous dominis 24h', 'de' => 'neue Domains 24h', 'fr' => 'nouveaux domaines 24h'],
        'clientes ext 24h' => ['ca' => 'clients ext 24h', 'de' => 'Erweiterungs-Clients 24h', 'fr' => 'clients extension 24h'],
        'revisadas' => ['ca' => 'revisades', 'de' => 'gepruft', 'fr' => 'revues'],
        'cobertura revisiÃ³n' => ['ca' => 'cobertura revisio', 'de' => 'Review-Abdeckung', 'fr' => 'couverture revue'],
        'sitios manuales' => ['ca' => 'llocs manuals', 'de' => 'manuelle Sites', 'fr' => 'sites manuels'],
        'pendientes' => ['ca' => 'pendents', 'de' => 'ausstehend', 'fr' => 'en attente'],
        'Pendientes reales (fuera de allowlist/blocklist)' => ['ca' => 'Pendents reals (fora de allowlist/blocklist)', 'de' => 'Reale offene Falle (ausserhalb allowlist/blocklist)', 'fr' => 'En attente reels (hors allowlist/blocklist)'],
        'Último escaneo' => ['ca' => 'Ultim escaneig', 'de' => 'Letzter Scan', 'fr' => 'Dernier scan'],
        'Sin capturas disponibles.' => ['ca' => 'Sense captures disponibles.', 'de' => 'Keine Screenshots verfugbar.', 'fr' => 'Aucune capture disponible.'],
        'Antes' => ['ca' => 'Abans', 'de' => 'Vorher', 'fr' => 'Avant'],
        'Despues' => ['ca' => 'Despres', 'de' => 'Nachher', 'fr' => 'Apres'],
        'Ver aqui (manual)' => ['ca' => 'Veure aqui (manual)', 'de' => 'Hier ansehen (manuell)', 'fr' => 'Voir ici (manuel)'],
        'Descargar' => ['ca' => 'Descarregar', 'de' => 'Herunterladen', 'fr' => 'Telecharger'],
        'Guardar revisiÃ³n' => ['ca' => 'Desar revisio', 'de' => 'Review speichern', 'fr' => 'Enregistrer la revue'],
        'Eliminar captura' => ['ca' => 'Eliminar captura', 'de' => 'Screenshot loschen', 'fr' => 'Supprimer capture'],
        'Aprobar y usar en PÃºblico' => ['ca' => 'Aprovar i usar en public', 'de' => 'Freigeben und offentlich nutzen', 'fr' => 'Approuver et utiliser en public'],
        'Mapa usuarios extension' => ['ca' => 'Mapa usuaris extensio', 'de' => 'Karte Erweiterungs-Benutzer', 'fr' => 'Carte utilisateurs extension'],
        'Mapa webs detectadas' => ['ca' => 'Mapa webs detectades', 'de' => 'Karte erkannter Webseiten', 'fr' => 'Carte des sites detectes'],
        'GrÃ¡ficos globales (14 das)' => ['ca' => 'Grafics globals (14 dies)', 'de' => 'Globale Diagramme (14 Tage)', 'fr' => 'Graphiques globaux (14 jours)'],
        'Tendencia diaria' => ['ca' => 'Tendencia diaria', 'de' => 'Taglicher Trend', 'fr' => 'Tendance quotidienne'],
        'Ratio de bloqueo por dÃ­a' => ['ca' => 'Ratio de bloqueig per dia', 'de' => 'Blockierungsquote pro Tag', 'fr' => 'Ratio de blocage par jour'],
        'No hay capturas pendientes.' => ['ca' => 'No hi ha captures pendents.', 'de' => 'Keine ausstehenden Screenshots.', 'fr' => 'Aucune capture en attente.'],
        'Fuentes de inteligencia' => ['ca' => 'Fonts dintel-ligencia', 'de' => 'Intelligence-Quellen', 'fr' => 'Sources de renseignement'],
        'Cobertura de amenazas' => ['ca' => 'Cobertura damenaces', 'de' => 'Bedrohungsabdeckung', 'fr' => 'Couverture des menaces'],
        'Acceso y login' => ['ca' => 'Acces i login', 'de' => 'Zugang und Login', 'fr' => 'Acces et connexion'],
        'Solicitar acceso' => ['ca' => 'Sol-licitar acces', 'de' => 'Zugang anfordern', 'fr' => 'Demander acces'],
        'Entrar' => ['ca' => 'Entrar', 'de' => 'Anmelden', 'fr' => 'Se connecter'],
        'Desistimiento' => ['ca' => 'Desistiment', 'de' => 'Widerspruch', 'fr' => 'Desistement'],
        'Enviar desistimiento' => ['ca' => 'Enviar desistiment', 'de' => 'Widerspruch senden', 'fr' => 'Envoyer desistement'],
        'Mi cuenta' => ['ca' => 'El meu compte', 'de' => 'Mein Konto', 'fr' => 'Mon compte'],
        'Idioma por defecto' => ['ca' => 'Idioma per defecte', 'de' => 'Standardsprache', 'fr' => 'Langue par defaut'],
        'Guardar idioma' => ['ca' => 'Desar idioma', 'de' => 'Sprache speichern', 'fr' => 'Enregistrer la langue'],
        'Cambiar mi contraseÃ±a' => ['ca' => 'Canviar la meva contrasenya', 'de' => 'Mein Passwort andern', 'fr' => 'Changer mon mot de passe'],
        'Perfil no encontrado' => ['ca' => 'Perfil no trobat', 'de' => 'Profil nicht gefunden', 'fr' => 'Profil introuvable'],
        'Editar perfil' => ['ca' => 'Editar perfil', 'de' => 'Profil bearbeiten', 'fr' => 'Modifier profil'],
        'Guardar perfil' => ['ca' => 'Desar perfil', 'de' => 'Profil speichern', 'fr' => 'Enregistrer profil'],
        'InvestigaciÃ³n no disponible' => ['ca' => 'Investigacio no disponible', 'de' => 'Untersuchung nicht verfugbar', 'fr' => 'Investigation non disponible'],
        'Grafo de investigaciÃ³n' => ['ca' => 'Graf dinvestigacio', 'de' => 'Untersuchungsgraph', 'fr' => 'Graphe dinvestigation'],
        'Detalle del nodo seleccionado' => ['ca' => 'Detall del node seleccionat', 'de' => 'Details des ausgewahlten Knotens', 'fr' => 'Detail du noeud selectionne'],
        'Sin nodo seleccionado.' => ['ca' => 'Cap node seleccionat.', 'de' => 'Kein Knoten ausgewahlt.', 'fr' => 'Aucun noeud selectionne.'],
        'Investigaciones de sitios' => ['ca' => 'Investigacions de llocs', 'de' => 'Site-Untersuchungen', 'fr' => 'Investigations de sites'],
        'Case queue' => ['ca' => 'Cua de casos', 'de' => 'Fall-Warteschlange', 'fr' => 'File de cas'],
        'Nueva investigaciÃ³n' => ['ca' => 'Nova investigacio', 'de' => 'Neue Untersuchung', 'fr' => 'Nouvelle investigation'],
        'Guardar investigaciÃ³n' => ['ca' => 'Desar investigacio', 'de' => 'Untersuchung speichern', 'fr' => 'Enregistrer investigation'],
        'Eventos recientes' => ['ca' => 'Esdeveniments recents', 'de' => 'Neueste Ereignisse', 'fr' => 'Evenements recents'],
        'Sin eventos recientes.' => ['ca' => 'Sense esdeveniments recents.', 'de' => 'Keine aktuellen Ereignisse.', 'fr' => 'Aucun evenement recent.'],
        'Motivos detectados' => ['ca' => 'Motius detectats', 'de' => 'Erkannte Grunde', 'fr' => 'Motifs detectes'],
        'Snippets detectados' => ['ca' => 'Snippets detectats', 'de' => 'Erkannte Snippets', 'fr' => 'Snippets detectes'],
        'Ver relacionadas' => ['ca' => 'Veure relacionades', 'de' => 'Verwandte anzeigen', 'fr' => 'Voir liees'],
        'Bloquear dominio' => ['ca' => 'Bloquejar domini', 'de' => 'Domain blockieren', 'fr' => 'Bloquer domaine'],
        'Mandar a investigaciÃ³n' => ['ca' => 'Enviar a investigacio', 'de' => 'Zur Untersuchung senden', 'fr' => 'Envoyer en investigation'],
        'Generar investigaciÃ³n' => ['ca' => 'Generar investigacio', 'de' => 'Untersuchung erzeugen', 'fr' => 'Generer investigation'],
        'Eliminar detecciÃ³n' => ['ca' => 'Eliminar detecciÃ³', 'de' => 'Erkennung lÃ¶schen', 'fr' => 'Supprimer la dÃ©tection'],
        'Capturas web (before/after)' => ['ca' => 'Captures web (before/after)', 'de' => 'Web-Screenshots (before/after)', 'fr' => 'Captures web (before/after)'],
        'Vista tabular clasica' => ['ca' => 'Vista tabular classica', 'de' => 'Klassische Tabellenansicht', 'fr' => 'Vue tabulaire classique'],
        'Solo pendientes' => ['ca' => 'Nomes pendents', 'de' => 'Nur ausstehend', 'fr' => 'Seulement en attente'],
        'Aplicar veredicto masivo' => ['ca' => 'Aplicar veredicte massiu', 'de' => 'Massenverdict anwenden', 'fr' => 'Appliquer verdict massif'],
        'GrÃ¡ficos y mÃ©tricas operativas' => ['ca' => 'Grafics i metriques operatives', 'de' => 'Operative Diagramme und Metriken', 'fr' => 'Graphiques et metriques operationnelles'],
        'Detector de anomalÃ­as (24h vs baseline)' => ['ca' => 'Detector danomalies (24h vs baseline)', 'de' => 'Anomalie-Detektor (24h vs Baseline)', 'fr' => 'Detecteur danomalies (24h vs baseline)'],
        'Keywords por ventana temporal' => ['ca' => 'Keywords per finestra temporal', 'de' => 'Keywords nach Zeitfenster', 'fr' => 'Keywords par fenetre temporelle'],
        'Predicciones de riesgo (Top)' => ['ca' => 'Prediccions de risc (Top)', 'de' => 'Risikovorhersagen (Top)', 'fr' => 'Predictions de risque (Top)'],
        'B?squeda avanzada' => ['ca' => 'Cerca avancada', 'de' => 'Erweiterte Suche', 'fr' => 'Recherche avancee'],
        'Gestion de listas' => ['ca' => 'Gestio de llistes', 'de' => 'Listenverwaltung', 'fr' => 'Gestion des listes'],
        'Historial de mensajes' => ['ca' => 'Historial de missatges', 'de' => 'Nachrichtenverlauf', 'fr' => 'Historique des messages'],
        'Limpiar historial' => ['ca' => 'Netejar historial', 'de' => 'Verlauf leeren', 'fr' => 'Nettoyer historique'],
        'Eliminar de plataforma' => ['ca' => 'Eliminar de plataforma', 'de' => 'Von Plattform loschen', 'fr' => 'Supprimer de la plateforme'],
        'Solicitudes de acceso' => ['ca' => 'Sol-licituds dacces', 'de' => 'Zugriffsanfragen', 'fr' => 'Demandes dacces'],
        'Nuevo usuario' => ['ca' => 'Nou usuari', 'de' => 'Neuer Benutzer', 'fr' => 'Nouvel utilisateur'],
        'Crear usuario' => ['ca' => 'Crear usuari', 'de' => 'Benutzer erstellen', 'fr' => 'Creer utilisateur'],
        'Radar rÃ¡pido' => ['ca' => 'Radar rapid', 'de' => 'Schnellradar', 'fr' => 'Radar rapide'],
        'Top pases' => ['ca' => 'Top paÃ­sos', 'de' => 'Top Lander', 'fr' => 'Top pays'],
        'Sin datos recientes' => ['ca' => 'Sense dades recents', 'de' => 'Keine aktuellen Daten', 'fr' => 'Aucune donnee recente'],
        'Copiar' => ['ca' => 'Copiar', 'de' => 'Kopieren', 'fr' => 'Copier'],
        'Copiado' => ['ca' => 'Copiat', 'de' => 'Kopiert', 'fr' => 'Copie'],
        'Error copia' => ['ca' => 'Error de copia', 'de' => 'Kopierfehler', 'fr' => 'Erreur de copie'],
        'Cargando captura...' => ['ca' => 'Carregant captura...', 'de' => 'Screenshot wird geladen...', 'fr' => 'Chargement de la capture...'],
        'No se pudo cargar la captura.' => ['ca' => 'No sha pogut carregar la captura.', 'de' => 'Screenshot konnte nicht geladen werden.', 'fr' => 'Impossible de charger la capture.'],
        'No se encontraron alertas relacionadas.' => ['ca' => 'No shan trobat alertes relacionades.', 'de' => 'Keine verwandten Warnungen gefunden.', 'fr' => 'Aucune alerte liee trouvee.'],
        'Sin contexto capturado.' => ['ca' => 'Sense context capturat.', 'de' => 'Kein erfasster Kontext.', 'fr' => 'Aucun contexte capture.'],
        'Cargando alertas relacionadas...' => ['ca' => 'Carregant alertes relacionades...', 'de' => 'Verwandte Warnungen werden geladen...', 'fr' => 'Chargement des alertes liees...'],
        'Selecciona al menos una alerta para revisiÃ³n masiva.' => ['ca' => 'Selecciona almenys una alerta per a revisio massiva.', 'de' => 'Bitte mindestens eine Warnung fur Massenreview auswahlen.', 'fr' => 'Selectionnez au moins une alerte pour la revue massive.'],
        'Mapa no disponible (Leaflet no cargado).' => ['ca' => 'Mapa no disponible (Leaflet no carregat).', 'de' => 'Karte nicht verfugbar (Leaflet nicht geladen).', 'fr' => 'Carte indisponible (Leaflet non charge).'],
        'No se pudo cargar geointeligencia de usuarios.' => ['ca' => 'No sha pogut carregar geointel-ligencia dusuaris.', 'de' => 'Geointelligence der Benutzer konnte nicht geladen werden.', 'fr' => 'Impossible de charger la geointelligence des utilisateurs.'],
        'No se pudo cargar geointeligencia de webs.' => ['ca' => 'No sha pogut carregar geointel-ligencia de webs.', 'de' => 'Geointelligence der Webseiten konnte nicht geladen werden.', 'fr' => 'Impossible de charger la geointelligence des sites.'],
        'Sin datos de geointeligencia.' => ['ca' => 'Sense dades de geointel-ligencia.', 'de' => 'Keine Geointelligence-Daten.', 'fr' => 'Aucune donnee de geointelligence.'],
    ];

    $normalizedPhrases = [];
    foreach ($phrases as $source => $targets) {
        $fixedTargets = [];
        foreach ($targets as $targetLang => $translated) {
            $fixedTargets[$targetLang] = clickfix_fix_locale_text((string) $translated);
        }
        $normalizedPhrases[clickfix_fix_locale_text((string) $source)] = $fixedTargets;
    }

    foreach ($normalizedPhrases as $source => $targets) {
        foreach ($maps as $targetLang => &$targetMap) {
            if (isset($targets[$targetLang])) {
                $targetMap[$source] = (string) $targets[$targetLang];
            }
        }
        unset($targetMap);
    }
    foreach ($maps as &$targetMap) {
        uksort($targetMap, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));
    }
    unset($targetMap);
    return $maps;
}

function cfdashboardtranslateoutput(string $output, string $lang): string
{
    $lang = strtolower(trim($lang));
    $protected = cfdashboardprotectblocks($output);
    $output = cfdashboardnormalizeoutputtext($protected['html']);
    if (!in_array($lang, ['ca', 'de', 'fr'], true)) {
        return cfdashboardrestoreblocks($output, $protected['blocks']);
    }
    $maps = cfdashboardliteralmaps();
    $map = $maps[$lang] ?? [];
    if (empty($map)) {
        return cfdashboardrestoreblocks($output, $protected['blocks']);
    }
    $translated = cfdashboardnormalizeoutputtext(strtr($output, $map));
    return cfdashboardrestoreblocks($translated, $protected['blocks']);
}

function cfdashboardprotectblocks(string $html): array
{
    $blocks = [];
    $index = 0;
    $masked = preg_replace_callback(
        '/<(script|style)\b[^>]*>.*?<\/\1>/is',
        static function (array $matches) use (&$blocks, &$index): string {
            $token = '__CFDASHBOARD_BLOCK_' . $index++ . '__';
            $blocks[$token] = (string) ($matches[0] ?? '');
            return $token;
        },
        $html
    );
    return [
        'html' => is_string($masked) ? $masked : $html,
        'blocks' => $blocks,
    ];
}

function cfdashboardrestoreblocks(string $html, array $blocks): string
{
    if ($html === '' || empty($blocks)) {
        return $html;
    }
    return strtr($html, $blocks);
}

function cfdashboardnormalizeoutputtext(string $output): string
{
    if ($output === '' || !class_exists('DOMDocument')) {
        return clickfix_fix_locale_text($output);
    }

    $dom = new DOMDocument('1.0', 'UTF-8');
    $previous = libxml_use_internal_errors(true);
    $loaded = $dom->loadHTML('<?xml encoding="UTF-8">' . $output, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    if (!$loaded) {
        return clickfix_fix_locale_text($output);
    }

    $xpath = new DOMXPath($dom);
    foreach ($xpath->query('//text()[not(ancestor::script) and not(ancestor::style)]') as $node) {
        $node->nodeValue = clickfix_fix_locale_text((string) $node->nodeValue);
    }

    $attributes = ['title', 'placeholder', 'aria-label', 'alt', 'value', 'label'];
    foreach ($xpath->query('//*[@title or @placeholder or @aria-label or @alt or @value or @label]') as $element) {
        if (!$element instanceof DOMElement) {
            continue;
        }
        foreach ($attributes as $attribute) {
            if ($element->hasAttribute($attribute)) {
                $element->setAttribute($attribute, clickfix_fix_locale_text($element->getAttribute($attribute)));
            }
        }
    }

    $normalized = $dom->saveHTML();
    $normalized = preg_replace('/^<\?xml encoding="UTF-8"\?>/i', '', (string) $normalized);
    return (string) $normalized;
}

function cfextractreasons(array $report): array
{
    $reasons = [];
    $entries = $report['reason_entries'] ?? [];
    if (is_array($entries)) {
        foreach ($entries as $entry) {
            if (!is_array($entry) || empty($entry['key'])) {
                continue;
            }
            $key = (string) $entry['key'];
            if ($key === 'alertConfidenceScore') {
                continue;
            }
            $label = cfreasonlabel($key);
            $value = isset($entry['value']) && $entry['value'] !== '' ? ': ' . (string) $entry['value'] : '';
            $item = trim($label . $value);
            if ($item !== '' && !in_array($item, $reasons, true)) {
                $reasons[] = $item;
            }
        }
    }

    if (!empty($reasons)) {
        return $reasons;
    }

    $signals = is_array($report['signals'] ?? null) ? $report['signals'] : [];
    $signalMap = [
        'commandMatch' => 'Command pattern in page',
        'winRHint' => 'Win+R instruction',
        'winXHint' => 'Win+X instruction',
        'browserErrorHint' => 'Fake browser error lure',
        'fixActionHint' => 'Fake fix action',
        'captchaHint' => 'Fake captcha / verification lure',
        'consoleHint' => 'DevTools console instruction',
        'shellHint' => 'Shell or terminal instruction',
        'pasteSequenceHint' => 'Paste + execute sequence',
        'fileExplorerHint' => 'File explorer execution lure',
        'copyTriggerHint' => 'Forced copy trigger',
        'evasionHint' => 'Obfuscation / evasion',
        'mismatch' => 'Clipboard mismatch',
        'clipboardWarning' => 'Clipboard command pattern',
    ];
    foreach ($signalMap as $key => $label) {
        if (!empty($signals[$key])) {
            $reasons[] = $label;
        }
    }
    return array_values(array_unique($reasons));
}

function cfextractsnippets(array $report): array
{
    $snippets = [];
    $addSnippet = static function (string $value) use (&$snippets): void {
        $trimmed = trim($value);
        if ($trimmed === '' || in_array($trimmed, $snippets, true)) {
            return;
        }
        $snippets[] = $trimmed;
    };

    $raw = $report['matched_snippets'] ?? [];
    if (is_array($raw)) {
        foreach ($raw as $snippet) {
            if (!is_scalar($snippet)) {
                continue;
            }
            $addSnippet((string) $snippet);
        }
    }

    if (empty($snippets) && !empty($report['detected_content'])) {
        $addSnippet((string) $report['detected_content']);
    }

    return $snippets;
}

function cfseverityclass($score): string
{
    $value = is_numeric($score) ? (int) $score : 0;
    if ($value > 40) {
        return 'critical';
    }
    if ($value >= 30) {
        return 'high';
    }
    if ($value > 15) {
        return 'medium';
    }
    return 'low';
}

$eventWorkbenchSourceReports = $page === 'search' ? $searchResults : $reports;
$reportBlockedHistory = [
    'hostnames' => [],
    'ips' => [],
];
if (!empty($eventWorkbenchSourceReports)) {
    $historyHostnames = [];
    $historyIps = [];
    foreach ($eventWorkbenchSourceReports as $historyRow) {
        $historyHost = trim((string) ($historyRow['hostname'] ?? ''));
        if ($historyHost !== '') {
            $historyHostnames[] = $historyHost;
        }
        if ($canSrViewer) {
            $historyIp = trim((string) ($historyRow['ip'] ?? ''));
            if ($historyIp !== '' && filter_var($historyIp, FILTER_VALIDATE_IP)) {
                $historyIps[] = $historyIp;
            }
        }
    }
    $reportBlockedHistory = clickfix_report_blocked_history($pdo, $historyHostnames, $historyIps);
}
$blockedHistoryByHostname = is_array($reportBlockedHistory['hostnames'] ?? null) ? $reportBlockedHistory['hostnames'] : [];
$blockedHistoryByIp = is_array($reportBlockedHistory['ips'] ?? null) ? $reportBlockedHistory['ips'] : [];

$eventWorkbenchRows = [];
foreach ($eventWorkbenchSourceReports as $reportRow) {
    $receivedAt = (string) ($reportRow['received_at'] ?? '');
    $lastSeenAt = (string) ($reportRow['last_seen'] ?? '');
    $activityAt = $lastSeenAt !== '' ? $lastSeenAt : $receivedAt;
    $eventUrl = (string) ($reportRow['url'] ?? '');
    $eventPrevUrl = (string) ($reportRow['previous_url'] ?? '');
    $eventMessage = (string) ($reportRow['message'] ?? '');
    $eventDetectedContent = (string) ($reportRow['detected_content'] ?? '');
    $eventFullContext = (string) ($reportRow['full_context'] ?? '');
    $eventType = strtolower((string) ($reportRow['event_type'] ?? 'clickfix_alert'));
    $eventIp = (string) ($reportRow['ip'] ?? '');
    $eventExtensionVersion = (string) ($reportRow['extension_version'] ?? clickfix_extract_extension_version((string) ($reportRow['user_agent'] ?? '')));
    $eventHostnameLookup = strtolower(trim((string) ($reportRow['hostname'] ?? '')));
    $eventHostnameHistory = is_array($blockedHistoryByHostname[$eventHostnameLookup] ?? null)
        ? $blockedHistoryByHostname[$eventHostnameLookup]
        : ['total_count' => 0, 'blocked_count' => 0, 'last_blocked_at' => ''];
    $eventIpLookup = trim($eventIp);
    $eventIpHistory = is_array($blockedHistoryByIp[$eventIpLookup] ?? null)
        ? $blockedHistoryByIp[$eventIpLookup]
        : ['total_count' => 0, 'blocked_count' => 0, 'last_blocked_at' => ''];
    $hostBlockedCount = max(0, (int) ($eventHostnameHistory['blocked_count'] ?? 0));
    $hostTotalCount = max(0, (int) ($eventHostnameHistory['total_count'] ?? 0));
    $hostBlockedBefore = $hostBlockedCount > 0;
    $ipBlockedCount = max(0, (int) ($eventIpHistory['blocked_count'] ?? 0));
    $ipTotalCount = max(0, (int) ($eventIpHistory['total_count'] ?? 0));
    $ipBlockedBefore = $eventIpLookup !== '' && $ipBlockedCount > 0;
    $eventSnippets = cfextractsnippets($reportRow);
    if ($redactSensitiveForViewer) {
        $eventUrl = clickfix_dashboard_redact_sensitive($eventUrl);
        $eventPrevUrl = clickfix_dashboard_redact_sensitive($eventPrevUrl);
        $eventMessage = clickfix_dashboard_redact_sensitive($eventMessage);
        $eventDetectedContent = clickfix_dashboard_redact_sensitive($eventDetectedContent);
        $eventFullContext = clickfix_dashboard_redact_sensitive($eventFullContext);
        $eventSnippets = array_map(static function ($snippet): string {
            return clickfix_dashboard_redact_sensitive((string) $snippet);
        }, $eventSnippets);
    }
    $downloadIoc = [];
    if ($eventType === 'unsafe_download') {
        $downloadIoc = [
            'hash' => '',
            'filename' => '',
            'path' => '',
            'site' => '',
            'url' => '',
        ];
        $contextData = null;
        $contextTrim = trim($eventFullContext);
        if ($contextTrim !== '' && ($contextTrim[0] === '{' || $contextTrim[0] === '[')) {
            $decoded = json_decode($contextTrim, true);
            if (is_array($decoded)) {
                $contextData = $decoded;
            }
        }
        if (is_array($contextData)) {
            $downloadIoc['hash'] = (string) ($contextData['sha256'] ?? $contextData['hash'] ?? '');
            $downloadIoc['filename'] = (string) ($contextData['download_filename'] ?? $contextData['filename'] ?? '');
            $downloadIoc['path'] = (string) ($contextData['download_path'] ?? '');
            $downloadIoc['url'] = (string) ($contextData['download_url'] ?? '');
            $downloadIoc['site'] = (string) ($contextData['download_host'] ?? $contextData['host'] ?? '');
        }
        if ($downloadIoc['filename'] === '') {
            $downloadIoc['filename'] = (string) ($reportRow['detected_content'] ?? '');
        }
        if ($downloadIoc['url'] === '') {
            $downloadIoc['url'] = (string) ($reportRow['url'] ?? '');
        }
        if ($downloadIoc['site'] === '') {
            $downloadIoc['site'] = (string) ($reportRow['hostname'] ?? '');
        }
        if ($redactSensitiveForViewer) {
            foreach ($downloadIoc as $k => $v) {
                $downloadIoc[$k] = clickfix_dashboard_redact_sensitive((string) $v);
            }
        }
    }
    $eventWorkbenchRows[] = [
        'id' => (int) ($reportRow['id'] ?? 0),
        'received_at' => $receivedAt,
        'last_seen' => $lastSeenAt,
        'activity_at' => $activityAt,
        'hostname' => (string) ($reportRow['hostname'] ?? ''),
        'url' => $eventUrl,
        'previous_url' => $eventPrevUrl,
        'message' => $eventMessage,
        'review_status' => (string) ($reportRow['review_status'] ?? 'pending'),
        'blocked' => !empty($reportRow['blocked']),
        'duplicate_count' => (int) ($reportRow['duplicate_count'] ?? 1),
        'score_total' => isset($reportRow['score_total']) ? (int) $reportRow['score_total'] : null,
        'country' => (string) ($reportRow['country'] ?? ''),
        'detected_content' => $eventDetectedContent,
        'full_context' => $canViewExactEventContext ? $eventFullContext : '',
        'event_type' => $eventType,
        'ip' => $canSrViewer ? $eventIp : '',
        'extension_version' => $canSrViewer ? $eventExtensionVersion : '',
        'host_blocked_before' => $hostBlockedBefore,
        'host_blocked_count' => $hostBlockedCount,
        'host_total_count' => $hostTotalCount,
        'host_last_blocked_at' => (string) ($eventHostnameHistory['last_blocked_at'] ?? ''),
        'ip_blocked_before' => $canSrViewer ? $ipBlockedBefore : false,
        'ip_blocked_count' => $canSrViewer ? $ipBlockedCount : 0,
        'ip_total_count' => $canSrViewer ? $ipTotalCount : 0,
        'ip_last_blocked_at' => $canSrViewer ? (string) ($eventIpHistory['last_blocked_at'] ?? '') : '',
        'reason_list' => cfextractreasons($reportRow),
        'snippets' => $eventSnippets,
        'signals' => is_array($reportRow['signals'] ?? null) ? $reportRow['signals'] : [],
        'score_details' => is_array($reportRow['score_details'] ?? null) ? $reportRow['score_details'] : null,
        'download_ioc' => $downloadIoc,
    ];
}
$eventDomainGroupsMap = [];
foreach ($eventWorkbenchRows as $eventRow) {
    $hostLabel = trim((string) ($eventRow['hostname'] ?? ''));
    if ($hostLabel === '') {
        $hostLabel = '(sin dominio)';
    }
    $hostKey = strtolower($hostLabel);
    $activityAt = (string) ($eventRow['activity_at'] ?? $eventRow['received_at'] ?? '');
    $activityTs = strtotime($activityAt);
    if ($activityTs === false) {
        $activityTs = 0;
    }
    $scoreValue = isset($eventRow['score_total']) && is_numeric($eventRow['score_total']) ? (int) $eventRow['score_total'] : 0;
    $duplicateCount = isset($eventRow['duplicate_count']) && is_numeric($eventRow['duplicate_count']) ? max(1, (int) $eventRow['duplicate_count']) : 1;
    if (!isset($eventDomainGroupsMap[$hostKey])) {
        $eventDomainGroupsMap[$hostKey] = [
            'hostname' => $hostLabel,
            'events' => 0,
            'duplicate_hits' => 0,
            'blocked' => 0,
            'max_score' => 0,
            'latest_activity_at' => $activityAt,
            'latest_activity_ts' => $activityTs,
        ];
    }
    $eventDomainGroupsMap[$hostKey]['events']++;
    $eventDomainGroupsMap[$hostKey]['duplicate_hits'] += $duplicateCount;
    if (!empty($eventRow['blocked'])) {
        $eventDomainGroupsMap[$hostKey]['blocked']++;
    }
    if ($scoreValue > (int) $eventDomainGroupsMap[$hostKey]['max_score']) {
        $eventDomainGroupsMap[$hostKey]['max_score'] = $scoreValue;
    }
    if ($activityTs >= (int) $eventDomainGroupsMap[$hostKey]['latest_activity_ts']) {
        $eventDomainGroupsMap[$hostKey]['latest_activity_ts'] = $activityTs;
        $eventDomainGroupsMap[$hostKey]['latest_activity_at'] = $activityAt;
    }
}
$eventDomainGroups = array_values($eventDomainGroupsMap);
usort($eventDomainGroups, static function (array $a, array $b): int {
    $tsA = (int) ($a['latest_activity_ts'] ?? 0);
    $tsB = (int) ($b['latest_activity_ts'] ?? 0);
    if ($tsA !== $tsB) {
        return $tsB <=> $tsA;
    }
    $eventsA = (int) ($a['events'] ?? 0);
    $eventsB = (int) ($b['events'] ?? 0);
    if ($eventsA !== $eventsB) {
        return $eventsB <=> $eventsA;
    }
    return strcmp((string) ($a['hostname'] ?? ''), (string) ($b['hostname'] ?? ''));
});
$eventWorkbenchJson = json_encode(
    $eventWorkbenchRows,
    JSON_UNESCAPED_UNICODE
    | JSON_UNESCAPED_SLASHES
    | JSON_HEX_TAG
    | JSON_HEX_AMP
    | JSON_HEX_APOS
    | JSON_HEX_QUOT
    | JSON_INVALID_UTF8_SUBSTITUTE
);
if ($eventWorkbenchJson === false) {
    $eventWorkbenchJson = '[]';
}
$selectedInvestigationJson = json_encode($selectedInvestigation, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
if ($selectedInvestigationJson === false) {
    $selectedInvestigationJson = 'null';
}
$sharedGraphJson = json_encode($sharedGraph, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
if ($sharedGraphJson === false) {
    $sharedGraphJson = 'null';
}
$intelApiLookupMapRowsJson = json_encode($intelApiLookupMapRows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
if ($intelApiLookupMapRowsJson === false) {
    $intelApiLookupMapRowsJson = '[]';
}
$intelApiCommonKeywordsJson = json_encode($intelApiCommonKeywords, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
if ($intelApiCommonKeywordsJson === false) {
    $intelApiCommonKeywordsJson = '[]';
}
$pageLabelMap = [
    'home' => cft('nav_home'),
    'search' => cft('nav_search'),
    'coverage' => cft('nav_coverage'),
    'about' => cft('nav_about'),
    'access' => cft('nav_access'),
    'settings' => cft('nav_settings'),
    'ops' => cft('nav_ops'),
    'analytics' => cft('nav_graphs'),
    'intel_stats' => cft('nav_intel_stats'),
    'intel' => cft('nav_investigation'),
    'community' => cft('nav_community'),
    'extensions' => cft('nav_extensions'),
    'lists' => cft('nav_lists'),
    'requests' => cft('nav_requests'),
    'messaging' => cft('nav_messaging'),
    'data_center' => cft('nav_data_center'),
    'configs' => cft('nav_score_config'),
    'reports' => cft('nav_reports'),
    'users' => cft('nav_users'),
    'investigation' => cft('nav_investigation'),
    'profile' => cft('nav_profile'),
];
$currentPageLabel = (string) ($pageLabelMap[$page] ?? ucfirst($page));
$scriptDirUrl = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/dashboard.php')));
if ($scriptDirUrl === '/' || $scriptDirUrl === '\\' || $scriptDirUrl === '.') {
    $scriptDirUrl = '';
}
$webRootUrl = ($scriptDirUrl === '') ? '' : rtrim(dirname($scriptDirUrl), '/');
if ($webRootUrl === '.' || $webRootUrl === '/') {
    $webRootUrl = '';
}
$templateBaseUrl = ($scriptDirUrl !== '' ? $scriptDirUrl : '') . '/assets/corona';
$leafletCssUrl = ($scriptDirUrl !== '' ? $scriptDirUrl : '') . '/assets/vendor/leaflet/leaflet.css';
$leafletJsUrl = ($scriptDirUrl !== '' ? $scriptDirUrl : '') . '/assets/vendor/leaflet/leaflet.js';
$leafletWorldGeoJsonUrl = ($scriptDirUrl !== '' ? $scriptDirUrl : '') . '/assets/vendor/leaflet/data/world-countries.geo.json';
$activeTheme = $loggedIn ? clickfix_profile_normalize_theme((string) ($user['profile_theme'] ?? 'default')) : 'default';
$bodyClass = 'theme-' . $activeTheme;
$bodyClass .= ' template-corona';
$bodyClass .= ' page-' . preg_replace('/[^a-z0-9_-]/i', '-', strtolower((string) $page));
$bodyClass .= $loggedIn ? ' state-authenticated' : ' state-public';
ob_start();
$pageTitles = ['home' => 'ClickFix Mitigator | Defense-first anti ClickFix', 'search' => 'Search', 'about' => 'About', 'coverage' => 'Coverage', 'access' => 'Access', 'investigation' => 'Investigation', 'profile' => 'Profile', 'clickfix_domain_list' => 'ClickFix Domain List', 'ops' => 'Operations', 'analytics' => 'Analytics', 'intel_stats' => 'Intel Stats', 'intel' => 'Intel Workspace', 'community' => 'Community', 'extensions' => 'Extensions', 'lists' => 'Lists', 'requests' => 'Requests', 'messaging' => 'Messaging', 'data_center' => 'Data Center', 'configs' => 'Configs', 'reports' => 'Reports', 'users' => 'Users', 'settings' => 'Settings', 'llm_settings' => 'LLM Profiles', 'auto_investigation' => 'Auto-Investigation', 'domain_feeds' => 'Domain Feeds'];
$pageDescriptions = ['home' => 'ClickFix Mitigator monitors, detects and blocks ClickFix social engineering attacks. Real-time browser extension, clipboard protection, SOC dashboard and threat intelligence feed.', 'search' => 'Search across all ClickFix detections, domains, IOCs, and threat intelligence.', 'about' => 'About ClickFix Mitigator - the defense-first platform against ClickFix attacks. Built by Jordi Serrano.', 'coverage' => 'Active coverage map and detection pipeline for ClickFix Mitigator.', 'access' => 'Request access to the ClickFix Mitigator dashboard for threat analysts.', 'investigation' => 'Detailed ClickFix threat investigation with graph analysis, IOCs, and MITRE ATT&CK mapping.', 'clickfix_domain_list' => 'Complete list of known ClickFix-related domains from URLHaus, ThreatFox, Carson, GitHub Gist, SOC Defenders and internal blocklists.'];
$pageTitle = $pageTitles[$page] ?? 'ClickFix Mitigator';
$pageDescription = $pageDescriptions[$page] ?? 'ClickFix Mitigator - Defense-first platform against ClickFix social engineering attacks. Real-time detection, investigation and mitigation.';
$canonicalUrl = 'https://clickfix.jordiserrano.me/dashboard.php?page=' . urlencode($page) . ($publicView ? '&public=1' : '');
if ($page === 'investigation' && !empty($sharedGraph)) {
    $pageTitle = clickfix_h((string) ($sharedGraph['title'] ?? 'Investigation')) . ' | ClickFix Mitigator';
    $pageDescription = clickfix_h(substr((string) ($sharedGraph['summary'] ?? 'ClickFix threat investigation with graph analysis.'), 0, 300));
    $canonicalUrl = 'https://clickfix.jordiserrano.me/dashboard.php?page=investigation&share=' . urlencode((string) ($sharedGraph['share_token'] ?? ''));
}
if ($page === 'profile' && $profileUser !== null) {
    $pageTitle = clickfix_h((string) ($profileUser['username'] ?? 'Profile')) . ' | ClickFix Mitigator';
}
$ogImage = 'https://clickfix.jordiserrano.me/assets/corona/images/clickfix-og.png';
?>
<!doctype html>
<html lang="<?= clickfix_h($lang); ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $pageTitle; ?></title>
  <meta name="description" content="<?= clickfix_h($pageDescription); ?>">
  <meta name="robots" content="<?= $publicView ? 'index,follow,max-image-preview:large' : 'noindex,nofollow'; ?>">
  <meta name="googlebot" content="<?= $publicView ? 'index,follow' : 'noindex,nofollow'; ?>">
  <link rel="canonical" href="<?= clickfix_h($canonicalUrl); ?>">
  <meta property="og:title" content="<?= $pageTitle; ?>">
  <meta property="og:description" content="<?= clickfix_h($pageDescription); ?>">
  <meta property="og:url" content="<?= clickfix_h($canonicalUrl); ?>">
  <meta property="og:image" content="<?= clickfix_h($ogImage); ?>">
  <meta property="og:type" content="<?= $page === 'investigation' ? 'article' : 'website'; ?>">
  <meta property="og:site_name" content="ClickFix Mitigator">
  <meta property="og:locale" content="<?= clickfix_h($lang === 'ca' ? 'ca_ES' : ($lang === 'es' ? 'es_ES' : ($lang === 'fr' ? 'fr_FR' : ($lang === 'de' ? 'de_DE' : ($lang === 'it' ? 'it_IT' : 'en_US'))))); ?>">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="<?= $pageTitle; ?>">
  <meta name="twitter:description" content="<?= clickfix_h($pageDescription); ?>">
  <meta name="twitter:image" content="<?= clickfix_h($ogImage); ?>">
  <meta name="twitter:site" content="@jordiserrano">
  <meta name="author" content="Jordi Serrano">
  <meta name="theme-color" content="#05070f">
  <?php if ($page === 'clickfix_domain_list' || $page === 'investigation'): ?>
  <script type="application/ld+json">
  {
    "@context":"https://schema.org",
    "@type":"<?= $page === 'investigation' ? 'Article' : 'WebPage'; ?>",
    "headline":"<?= $pageTitle; ?>",
    "description":"<?= clickfix_h($pageDescription); ?>",
    "url":"<?= clickfix_h($canonicalUrl); ?>",
    "author":{"@type":"Organization","name":"ClickFix Mitigator","url":"https://clickfix.jordiserrano.me"},
    "publisher":{"@type":"Organization","name":"ClickFix Mitigator","url":"https://clickfix.jordiserrano.me"}
  }
  </script>
  <?php endif; ?>
  <?php if ($enableHomeGeoPanels || $enableSidebarGeoMap): ?>
  <link rel="stylesheet" href="<?= clickfix_h($leafletCssUrl); ?>">
  <?php endif; ?>
  <link rel="stylesheet" href="<?= clickfix_h($templateBaseUrl); ?>/vendors/mdi/css/materialdesignicons.min.css">
  <link rel="stylesheet" href="<?= clickfix_h($templateBaseUrl); ?>/vendors/ti-icons/css/themify-icons.css">
  <link rel="stylesheet" href="<?= clickfix_h($templateBaseUrl); ?>/vendors/css/vendor.bundle.base.css">
  <link rel="stylesheet" href="<?= clickfix_h($templateBaseUrl); ?>/vendors/font-awesome/css/font-awesome.min.css">
  <link rel="stylesheet" href="<?= clickfix_h($templateBaseUrl); ?>/vendors/flag-icon-css/css/flag-icons.min.css">
  <link rel="stylesheet" href="<?= clickfix_h($templateBaseUrl); ?>/css/style.css">
  <?php require __DIR__ . '/partials/dashboard_style.php'; ?>
  <?php require __DIR__ . '/partials/dashboard_corona_overrides.php'; ?>
</head>
<body class="<?= clickfix_h($bodyClass); ?>">
  <canvas id="fx-node-bg" class="fx-node-bg" aria-hidden="true"></canvas>
  <div class="container-scroller">
    <?php require __DIR__ . '/partials/dashboard_sidebar.php'; ?>
    <div class="container-fluid page-body-wrapper">
      <?php require __DIR__ . '/partials/dashboard_header.php'; ?>
      <div class="main-panel">
        <div class="content-wrapper">
          <div class="wrap">
            <div class="dashboard-shell-row">
              <div class="dashboard-main-col">
                <main class="main-column">

    
    <?php if ($flash): ?><div class="flash"><?= clickfix_h($flash); ?></div><?php endif; ?>

    <?php if ($page === 'home'): ?>
      <div class="row">
        <div class="col-xl-8 col-lg-7 grid-margin stretch-card">
          <div class="card">
            <div class="card-body">
              <div class="d-flex align-items-start justify-content-between flex-wrap gap-2">
                <div>
                  <p class="text-muted mb-1">ClickFix Command Center</p>
                  <h2 class="mb-2">Deteccion, explicabilidad y respuesta en una sola superficie de control.</h2>
                  <p class="text-muted">FusiÃ³n entre la versiÃ³n anterior y la actual: inteligencia pÃºblica, operaciones privadas, investigaciÃ³n en grafo, listas de control y mensajerÃ­a con la extensión.</p>
                </div>
                <span class="badge <?= $loggedIn ? 'badge-outline-success' : 'badge-outline-warning'; ?>">
                  <?= clickfix_h($loggedIn ? 'Workspace autenticado' : 'Vista pÃºblica'); ?>
                </span>
              </div>
              <div class="row mt-4">
                <div class="col-md-6 mb-3">
                  <div class="d-flex gap-2">
                    <i class="mdi mdi-magnify text-info home-feature-icon"></i>
                    <div>
                      <h6 class="mb-1">B?squeda forense</h6>
                      <p class="text-muted mb-0">Filtra por dominio, comando, fecha y score para detectar patrones.</p>
                    </div>
                  </div>
                </div>
                <div class="col-md-6 mb-3">
                  <div class="d-flex gap-2">
                    <i class="mdi mdi-shield-check text-success home-feature-icon"></i>
                    <div>
                      <h6 class="mb-1">Operaciones</h6>
                      <p class="text-muted mb-0">Actualiza revisiÃ³n, bloquea dominios y manda casos a investigaciÃ³n.</p>
                    </div>
                  </div>
                </div>
                <div class="col-md-6 mb-3">
                  <div class="d-flex gap-2">
                    <i class="mdi mdi-graph text-warning home-feature-icon"></i>
                    <div>
                      <h6 class="mb-1">InvestigaciÃ³n</h6>
                      <p class="text-muted mb-0">Construye grafos con nodos, relaciones, notas y evidencias compartibles.</p>
                    </div>
                  </div>
                </div>
                <div class="col-md-6 mb-3">
                  <div class="d-flex gap-2">
                    <i class="mdi mdi-radar text-primary home-feature-icon"></i>
                    <div>
                      <h6 class="mb-1">Cobertura</h6>
                      <p class="text-muted mb-0">Revisa fuentes de inteligencia, listas y configuracion de scoring.</p>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="col-xl-4 col-lg-5 grid-margin stretch-card">
          <div class="card">
            <div class="card-body">
              <h4 class="card-title">Inicio r?pido</h4>
              <p class="text-muted mb-3">
                <?php if ($loggedIn): ?>
                  Empieza en Inicio para mÃ©tricas y mapas, sigue en Operaciones para triage y termina en InvestigaciÃ³n para documentar el caso.
                <?php else: ?>
                  Vista pÃºblica activa. Puedes consultar cobertura y buscar eventos; inicia SesiÃ³n para ejecutar acciones operativas.
                <?php endif; ?>
              </p>
              <ol class="text-muted ps-3 mb-0">
                <li>Revisa KPIs y alertas recientes.</li>
                <li>Filtra dominios/comandos sospechosos.</li>
                <li>Ejecuta bloqueo o abre investigaciÃ³n.</li>
              </ol>
            </div>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <?php if ($page === 'home'): ?>
    <div class="row">
      <div class="col-xl-3 col-sm-6 grid-margin stretch-card">
        <div class="card">
          <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <p class="text-muted mb-0">alertas totales</p>
              <i class="mdi mdi-alert text-danger kpi-icon"></i>
            </div>
            <h3 class="mb-0" data-live-metric="total_alerts"><?= (int) $metrics['total_alerts']; ?></h3>
          </div>
        </div>
      </div>
      <div class="col-xl-3 col-sm-6 grid-margin stretch-card">
        <div class="card">
          <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <p class="text-muted mb-0">bloqueos totales</p>
              <i class="mdi mdi-lock text-success kpi-icon"></i>
            </div>
            <h3 class="mb-0" data-live-metric="total_blocks"><?= (int) $metrics['total_blocks']; ?></h3>
          </div>
        </div>
      </div>
      <div class="col-xl-3 col-sm-6 grid-margin stretch-card">
        <div class="card">
          <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <p class="text-muted mb-0">dominios Ãºnicos</p>
              <i class="mdi mdi-earth text-info kpi-icon"></i>
            </div>
            <h3 class="mb-0" data-live-metric="unique_hosts"><?= (int) $metrics['unique_hosts']; ?></h3>
          </div>
        </div>
      </div>
      <div class="col-xl-3 col-sm-6 grid-margin stretch-card">
        <div class="card">
          <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <p class="text-muted mb-0">regiones monitorizadas</p>
              <i class="mdi mdi-map text-warning kpi-icon"></i>
            </div>
            <h3 class="mb-0" data-live-metric="countries_count"><?= (int) ($metrics['countries_count'] ?? 0); ?></h3>
          </div>
        </div>
      </div>
      <div class="col-xl-3 col-sm-6 grid-margin stretch-card">
        <div class="card">
          <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <p class="text-muted mb-0">alertas 24h</p>
              <i class="mdi mdi-timer text-danger kpi-icon"></i>
            </div>
            <h3 class="mb-0" data-live-metric="alerts_24h"><?= (int) $metrics['alerts_24h']; ?></h3>
          </div>
        </div>
      </div>
      <div class="col-xl-3 col-sm-6 grid-margin stretch-card">
        <div class="card">
          <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <p class="text-muted mb-0">bloqueos 24h</p>
              <i class="mdi mdi-shield-check text-success kpi-icon"></i>
            </div>
            <h3 class="mb-0" data-live-metric="blocks_24h"><?= (int) $metrics['blocks_24h']; ?></h3>
          </div>
        </div>
      </div>
      <div class="col-xl-3 col-sm-6 grid-margin stretch-card">
        <div class="card">
          <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <p class="text-muted mb-0">ratio bloqueo 24h</p>
              <i class="mdi mdi-chart-areaspline text-primary kpi-icon"></i>
            </div>
            <h3 class="mb-0" data-live-metric="block_rate_24h"><?= number_format((float) ($metrics['block_rate_24h'] ?? 0.0), 2); ?>%</h3>
          </div>
        </div>
      </div>
      <div class="col-xl-3 col-sm-6 grid-margin stretch-card">
        <div class="card">
          <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <p class="text-muted mb-0">alto riesgo 24h</p>
              <i class="mdi mdi-alert-circle text-warning kpi-icon"></i>
            </div>
            <h3 class="mb-0" data-live-metric="high_risk_24h"><?= (int) ($metrics['high_risk_24h'] ?? 0); ?></h3>
          </div>
        </div>
      </div>
      <div class="col-xl-3 col-sm-6 grid-margin stretch-card">
        <div class="card">
          <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <p class="text-muted mb-0">nuevos dominios 24h</p>
              <i class="mdi mdi-web text-info kpi-icon"></i>
            </div>
            <h3 class="mb-0" data-live-metric="new_domains_24h"><?= (int) ($metrics['new_domains_24h'] ?? 0); ?></h3>
          </div>
        </div>
      </div>
      <div class="col-xl-3 col-sm-6 grid-margin stretch-card">
        <div class="card">
          <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <p class="text-muted mb-0">pend. fuera listas</p>
              <i class="mdi mdi-playlist-alert text-warning kpi-icon"></i>
            </div>
            <h3 class="mb-0" data-live-metric="pending_domains_outside_lists"><?= (int) ($metrics['pending_domains_outside_lists'] ?? 0); ?></h3>
          </div>
        </div>
      </div>
      <div class="col-xl-3 col-sm-6 grid-margin stretch-card">
        <div class="card">
          <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <p class="text-muted mb-0">revisadas</p>
              <i class="mdi mdi-check-all text-success kpi-icon"></i>
            </div>
            <h3 class="mb-0" data-live-metric="reviewed_total"><?= (int) ($metrics['reviewed_total'] ?? 0); ?></h3>
          </div>
        </div>
      </div>
      <div class="col-xl-3 col-sm-6 grid-margin stretch-card">
        <div class="card">
          <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <p class="text-muted mb-0">cobertura revisiÃ³n</p>
              <i class="mdi mdi-radar text-primary kpi-icon"></i>
            </div>
            <h3 class="mb-0" data-live-metric="review_coverage_pct"><?= number_format((float) ($metrics['review_coverage_pct'] ?? 0.0), 2); ?>%</h3>
          </div>
        </div>
      </div>
      <div class="col-xl-3 col-sm-6 grid-margin stretch-card">
        <div class="card">
          <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <p class="text-muted mb-0">sitios manuales</p>
              <i class="mdi mdi-note-text text-info kpi-icon"></i>
            </div>
            <h3 class="mb-0" data-live-metric="manual_sites_count"><?= (int) $metrics['manual_sites_count']; ?></h3>
          </div>
        </div>
      </div>
      <div class="col-xl-3 col-sm-6 grid-margin stretch-card">
        <div class="card">
          <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <p class="text-muted mb-0">pend. revisiÃ³n</p>
              <i class="mdi mdi-timer-sand text-warning kpi-icon"></i>
            </div>
            <h3 class="mb-0" data-live-metric="pending_review_total"><?= (int) ($metrics['pending_review_total'] ?? 0); ?></h3>
          </div>
        </div>
      </div>
    </div>

    <?php if ($loggedIn): ?>
      <section class="card" style="margin-bottom:8px">
        <h2>Pendientes de revisiÃ³n (todas las alertas)</h2>
        <p class="mut">Incluye alertas que ya estan en listas (allow/block). Los pendientes fuera de listas son un subconjunto para triage r?pido.</p>
        <div class="analytics-kpi-grid" style="margin-bottom:10px">
          <div class="analytics-kpi"><div class="k">pendientes revisiÃ³n</div><div class="v"><?= (int) ($metrics['pending_review_total'] ?? 0); ?></div></div>
          <div class="analytics-kpi"><div class="k">pendientes fuera de listas</div><div class="v"><?= (int) ($pendingOutsideSummary['alerts'] ?? 0); ?></div></div>
        </div>
        <?php if (empty($pendingReviewRows)): ?>
          <p class="mut">No hay alertas pendientes de revisiÃ³n en este momento.</p>
        <?php else: ?>
          <div class="analytics-table-wrap">
            <table class="compact-table">
              <thead><tr><th>ID</th><th>Fecha</th><th>Dominio</th><th>Score</th><th>Tipo</th><th>Bloqueado</th><th>AcciÃ³n</th></tr></thead>
              <tbody>
                <?php foreach (array_slice($pendingReviewRows, 0, 20) as $pendingRow): ?>
                  <?php $pendingReportId = (int) ($pendingRow['id'] ?? 0); ?>
                  <tr>
                    <td class="mono"><?= $pendingReportId; ?></td>
                    <td class="mono"><?= clickfix_h((string) ($pendingRow['received_at'] ?? '')); ?></td>
                    <td class="mono"><?= clickfix_h((string) ($pendingRow['hostname'] ?? '')); ?></td>
                    <td class="mono"><?= (int) ($pendingRow['score_total'] ?? 0); ?></td>
                    <td class="mono"><?= clickfix_h((string) ($pendingRow['event_type'] ?? 'clickfix_alert')); ?></td>
                    <td class="mono"><?= !empty($pendingRow['blocked']) ? 'yes' : 'no'; ?></td>
                    <td><a class="btn" href="<?= clickfix_h(cfurl('ops', false, ['report_id' => (string) $pendingReportId])); ?>">Abrir</a></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </section>

      <section class="card" style="margin-bottom:8px">
        <h2>Pendientes reales (fuera de allowlist/blocklist)</h2>
        <p class="mut">Pendientes que siguen sin clasificar en listas, para priorizar triage operativo.</p>
        <div class="analytics-kpi-grid" style="margin-bottom:10px">
          <div class="analytics-kpi"><div class="k">alertas pendientes reales</div><div class="v"><?= (int) ($pendingOutsideSummary['alerts'] ?? 0); ?></div></div>
          <div class="analytics-kpi"><div class="k">dominios pendientes reales</div><div class="v"><?= (int) ($pendingOutsideSummary['domains'] ?? 0); ?></div></div>
        </div>
        <?php if (empty($pendingOutsideReports)): ?>
          <p class="mut">No hay pendientes fuera de listas en este momento.</p>
        <?php else: ?>
          <div class="analytics-table-wrap">
            <table class="compact-table">
              <thead><tr><th>ID</th><th>Fecha</th><th>Dominio</th><th>Score</th><th>Tipo</th><th>Bloqueado</th><th>AcciÃ³n</th></tr></thead>
              <tbody>
                <?php foreach (array_slice($pendingOutsideReports, 0, 20) as $pendingRow): ?>
                  <?php $pendingReportId = (int) ($pendingRow['id'] ?? 0); ?>
                  <tr>
                    <td class="mono"><?= $pendingReportId; ?></td>
                    <td class="mono"><?= clickfix_h((string) ($pendingRow['received_at'] ?? '')); ?></td>
                    <td class="mono"><?= clickfix_h((string) ($pendingRow['hostname'] ?? '')); ?></td>
                    <td class="mono"><?= (int) ($pendingRow['score_total'] ?? 0); ?></td>
                    <td class="mono"><?= clickfix_h((string) ($pendingRow['event_type'] ?? 'clickfix_alert')); ?></td>
                    <td class="mono"><?= !empty($pendingRow['blocked']) ? 'yes' : 'no'; ?></td>
                    <td><a class="btn" href="<?= clickfix_h(cfurl('ops', false, ['report_id' => (string) $pendingReportId])); ?>">Abrir</a></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </section>
    <?php endif; ?>

    <section class="card" style="margin-bottom:8px">
      <?php
        $scanViewerAdmin = $loggedIn && cfcan($user, 'admin');
        $scanLatest = is_array($latestScanPreview) ? $latestScanPreview : null;
        $scanApproved = is_array($latestScanAssetsApproved) ? $latestScanAssetsApproved : [];
        $scanReview = is_array($latestScanAssetsReview) ? $latestScanAssetsReview : [];
      ?>
      <h2>Último escaneo</h2>
      <p class="mut">Vista previa: <b>Después</b> llega desde la extensión al detectar alerta y <b>Antes</b> se genera en servidor (Site-Shot) tras recibir ese after.</p>
      <?php if ($scanLatest === null): ?>
        <p class="mut">Sin capturas disponibles.</p>
      <?php else: ?>
        <p class="mono">scan_id: <?= (int) ($scanLatest['id'] ?? 0); ?> | <?= clickfix_h((string) ($scanLatest['hostname'] ?? '-')); ?> | <?= clickfix_h((string) ($scanLatest['received_at'] ?? '')); ?></p>
        <div class="split">
          <?php foreach (['before' => 'Antes', 'after' => 'Despues'] as $scanKind => $scanLabel): ?>
            <?php
              $approvedUrl = (string) ($scanApproved[$scanKind] ?? '');
              $assetExists = !empty($scanReview[$scanKind . '_exists']);
              $assetStatus = (string) ($scanReview[$scanKind . '_status'] ?? 'missing');
              $scanReportId = (int) ($scanLatest['id'] ?? 0);
              $adminPreviewUrl = clickfix_scan_image_url($scanReportId, $scanKind, true);
              $adminDownloadUrl = $adminPreviewUrl . '&download=1';
              $publicApprovedUrl = $assetStatus === 'approved' ? clickfix_scan_image_url($scanReportId, $scanKind, false) : '';
              $inlineTarget = 'scan-inline-home-' . $scanKind;
            ?>
            <div>
              <h3 class="mono"><?= clickfix_h($scanLabel); ?></h3>
              <?php if (!$scanViewerAdmin && $approvedUrl !== ''): ?>
                <img src="<?= clickfix_h($approvedUrl); ?>" alt="<?= clickfix_h($scanKind . ' scan'); ?>" loading="lazy" style="max-width:100%;border-radius:10px;border:1px solid #5dc8ff33">
              <?php elseif ($scanViewerAdmin && $assetExists): ?>
                <p class="mut mono">estado: <?= clickfix_h($assetStatus); ?></p>
                <div style="display:flex;gap:6px;flex-wrap:wrap">
                  <button class="btn" type="button" data-scan-inline-src="<?= clickfix_h($adminPreviewUrl); ?>" data-scan-inline-target="<?= clickfix_h($inlineTarget); ?>">Ver aqui (manual)</button>
                  <a class="btn" href="<?= clickfix_h($adminPreviewUrl); ?>" target="_blank" rel="noopener noreferrer">Abrir pestaa</a>
                  <a class="btn" href="<?= clickfix_h($adminDownloadUrl); ?>">Descargar</a>
                  <button class="btn" type="button" data-copy-text="<?= clickfix_h($adminPreviewUrl); ?>">Copiar URL admin</button>
                  <?php if ($publicApprovedUrl !== ''): ?>
                    <a class="btn" href="<?= clickfix_h($publicApprovedUrl); ?>" target="_blank" rel="noopener noreferrer">Abrir URL pÃºblica</a>
                    <button class="btn" type="button" data-copy-text="<?= clickfix_h($publicApprovedUrl); ?>">Copiar URL pÃºblica</button>
                  <?php endif; ?>
                </div>
                <div id="<?= clickfix_h($inlineTarget); ?>" class="mut mono" style="margin-top:8px">Pulsa "Ver aqui (manual)" para cargar la captura en el panel.</div>
                <form method="post" style="margin-top:8px;display:inline-block">
                  <input type="hidden" name="action" value="scan_image_review">
                  <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                  <input type="hidden" name="scan_report_id" value="<?= $scanReportId; ?>">
                  <input type="hidden" name="scan_kind" value="<?= clickfix_h($scanKind); ?>">
                  <input type="hidden" name="scan_status" value="approved">
                  <input type="hidden" name="scan_note" value="approved from home quick-use">
                  <input type="hidden" name="return_page" value="home">
                  <button class="btn btn-primary btn-sm" type="submit">Aprobar y usar en PÃºblico</button>
                </form>
                <form method="post" style="margin-top:8px">
                  <input type="hidden" name="action" value="scan_image_review">
                  <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                  <input type="hidden" name="scan_report_id" value="<?= $scanReportId; ?>">
                  <input type="hidden" name="scan_kind" value="<?= clickfix_h($scanKind); ?>">
                  <input type="hidden" name="return_page" value="home">
                  <select name="scan_status">
                    <option value="pending"<?= $assetStatus === 'pending' ? ' selected' : ''; ?>>pending</option>
                    <option value="approved"<?= $assetStatus === 'approved' ? ' selected' : ''; ?>>approved</option>
                    <option value="rejected"<?= $assetStatus === 'rejected' ? ' selected' : ''; ?>>rejected</option>
                  </select>
                  <input type="text" name="scan_note" maxlength="500" placeholder="nota de revisiÃ³n (opcional)">
                  <button class="btn btn-primary btn-sm" type="submit">Guardar revisiÃ³n</button>
                </form>
                <form method="post" style="margin-top:8px" onsubmit="return confirm('Eliminar captura <?= clickfix_h($scanKind); ?> del scan #<?= $scanReportId; ?>?');">
                  <input type="hidden" name="action" value="scan_image_delete">
                  <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                  <input type="hidden" name="scan_report_id" value="<?= $scanReportId; ?>">
                  <input type="hidden" name="scan_kind" value="<?= clickfix_h($scanKind); ?>">
                  <input type="hidden" name="return_page" value="home">
                  <button class="btn btn-primary btn-sm" type="submit">Eliminar captura</button>
                </form>
                <form method="post" style="margin-top:8px">
                  <input type="hidden" name="action" value="scan_image_assign">
                  <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                  <input type="hidden" name="scan_report_id" value="<?= $scanReportId; ?>">
                  <input type="hidden" name="scan_source_kind" value="<?= clickfix_h($scanKind); ?>">
                  <input type="hidden" name="scan_target_kind" value="<?= $scanKind === 'before' ? 'after' : 'before'; ?>">
                  <input type="hidden" name="return_page" value="home">
                  <button class="btn btn-primary btn-sm" type="submit">Usar esta como <?= $scanKind === 'before' ? 'AFTER' : 'BEFORE'; ?></button>
                </form>
                <p class="mut" style="margin-top:6px">Cuando una captura queda en <b>approved</b>, se puede reutilizar en dashboard/index PÃºblico.</p>
              <?php else: ?>
                <p class="mut">Sin capturas disponibles.</p>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>

    <section class="card" style="margin-bottom:8px">
      <h2>Investigaciones destacadas en Inicio</h2>
      <p class="mut">Las define admin desde InvestigaciÃ³n. En la portada pÃºblica solo salen si tambiÃ©n estÃ¡n compartidas en PÃºblico.</p>
      <?php if (empty($featuredHomeInvestigations)): ?>
        <p class="mut">Sin investigaciones destacadas en este momento.</p>
      <?php else: ?>
        <?php foreach ($featuredHomeInvestigations as $featuredGraph): ?>
          <?php
            $featuredGraphId = (int) ($featuredGraph['id'] ?? 0);
            $featuredDomain = (string) ($featuredGraph['site_domain'] ?? '-');
            $featuredSourceReportId = (int) ($featuredGraph['source_report_id'] ?? 0);
            $featuredAssets = is_array($featuredGraph['scan_assets'] ?? null) ? $featuredGraph['scan_assets'] : ['before' => null, 'after' => null];
            $featuredSummary = trim((string) ($featuredGraph['summary'] ?? ''));
            if ($featuredSummary === '') {
                $featuredSummary = 'Sin resumen todavÃ­a.';
            }
          ?>
          <article class="card" style="margin-top:12px;padding:14px;border:1px solid #5dc8ff22;background:#07111a">
            <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;align-items:flex-start">
              <div style="flex:1 1 320px;min-width:280px">
                <h3 style="margin:0 0 6px 0"><?= clickfix_h((string) ($featuredGraph['title'] ?? ('InvestigaciÃ³n #' . $featuredGraphId))); ?></h3>
                <p class="mono" style="margin:0 0 8px 0">graph #<?= $featuredGraphId; ?> | dominio: <?= clickfix_h($featuredDomain); ?> | verdict: <?= clickfix_h((string) ($featuredGraph['verdict'] ?? 'unknown')); ?> | report_id: <?= $featuredSourceReportId > 0 ? $featuredSourceReportId : '-'; ?> | posici?n: <?= (int) ($featuredGraph['home_position'] ?? 0); ?></p>
                <div style="margin:0 0 10px 0;white-space:pre-line"><?= nl2br(clickfix_h(substr($featuredSummary, 0, 900))); ?></div>
                <div style="display:flex;gap:8px;flex-wrap:wrap">
                  <a class="btn" href="<?= clickfix_h(cfurl('intel', false, ['graph_id' => $featuredGraphId])); ?>">Abrir investigaciÃ³n</a>
                  <?php if (!empty($featuredGraph['is_public']) && !empty($featuredGraph['share_token'])): ?>
                    <a class="btn" href="<?= clickfix_h('dashboard.php?page=investigation&share=' . urlencode((string) $featuredGraph['share_token'])); ?>" target="_blank" rel="noreferrer">Abrir versiÃ³n pÃºblica</a>
                  <?php else: ?>
                    <span class="mono mut">Aun no es pÃºblica.</span>
                  <?php endif; ?>
                </div>
              </div>
              <div style="flex:1 1 360px;min-width:280px">
                <div class="split">
                  <?php foreach (['before' => 'Antes', 'after' => 'Despues'] as $featuredKind => $featuredLabel): ?>
                    <div>
                      <h3 class="mono"><?= clickfix_h($featuredLabel); ?></h3>
                      <?php if (!empty($featuredAssets[$featuredKind])): ?>
                        <img src="<?= clickfix_h((string) $featuredAssets[$featuredKind]); ?>" alt="<?= clickfix_h($featuredLabel . ' investigaciÃ³n'); ?>" loading="lazy" style="max-width:100%;border-radius:10px;border:1px solid #5dc8ff33">
                      <?php else: ?>
                        <p class="mut">Sin captura aprobada <?= strtolower($featuredLabel); ?>.</p>
                      <?php endif; ?>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      <?php endif; ?>
    </section>

    <?php endif; ?>

    <?php if ($enableHomeGeoPanels && $page === 'home'): ?>
      <?php
        $homeLabels = is_array($analyticsOverview['labels'] ?? null) ? $analyticsOverview['labels'] : [];
        $homeAlerts = is_array($analyticsOverview['alerts'] ?? null) ? $analyticsOverview['alerts'] : [];
        $homeBlocks = is_array($analyticsOverview['blocks'] ?? null) ? $analyticsOverview['blocks'] : [];
        $homeTrendMax = 1;
        foreach ($homeAlerts as $v) { if ((int) $v > $homeTrendMax) { $homeTrendMax = (int) $v; } }
        foreach ($homeBlocks as $v) { if ((int) $v > $homeTrendMax) { $homeTrendMax = (int) $v; } }
      ?>
      <section class="row">
        <article class="card">
          <h2>Mapa usuarios extensi?n</h2>
          <p class="geo-subtitle">Puntos rojos por paÃ­s con total de usuarios de la extensi?n.</p>
          <div id="home-extension-map" class="geo-map"></div>
          <div class="geo-map-legend">
            <span class="geo-chip"><b id="home-extension-total">0</b> usuarios geolocalizados</span>
            <span class="geo-chip"><b id="home-extension-countries">0</b> paÃ­ses con actividad</span>
          </div>
          <div class="geo-table-wrap">
            <table>
              <thead><tr><th>Pais</th><th>Codigo</th><th>Usuarios</th><th>Idiomas</th></tr></thead>
              <tbody id="home-extension-country-body"><tr><td colspan="4" class="mut">Cargando datos...</td></tr></tbody>
            </table>
          </div>
        </article>
        <article class="card">
          <h2>Mapa webs detectadas</h2>
          <p class="geo-subtitle">UbicaciÃ³n aproximada de webs detectadas con IP, ISP, paÃ­s e idioma principal.</p>
          <div id="home-web-map" class="geo-map"></div>
          <div class="geo-map-legend">
            <span class="geo-chip"><b id="home-web-count">0</b> webs con coordenadas</span>
            <span class="geo-chip"><b id="home-web-last">-</b> Ãšltima actualizaciÃ³n</span>
          </div>
          <div class="geo-table-wrap">
            <table>
              <thead><tr><th>Web</th><th>IP</th><th>ISP</th><th>Pais</th><th>Idioma</th><th>Servicios</th><th>Hits</th></tr></thead>
              <tbody id="home-web-body"><tr><td colspan="7" class="mut">Cargando datos...</td></tr></tbody>
            </table>
          </div>
        </article>
      </section>
      <?php if ($pageNeedsProjectExposure): ?>
        <?php
          $exposureSummary = is_array($projectExposureOverview['summary'] ?? null) ? $projectExposureOverview['summary'] : [];
          $exposureReferrers = is_array($projectExposureOverview['top_referrers'] ?? null) ? $projectExposureOverview['top_referrers'] : [];
          $exposureNetworks = is_array($projectExposureOverview['top_networks'] ?? null) ? $projectExposureOverview['top_networks'] : [];
          $exposureEvents = is_array($projectExposureOverview['events'] ?? null) ? $projectExposureOverview['events'] : [];
        ?>
              <section class="card analytics-hub">
        <div class="analytics-hub-head">
          <div>
          <h2>GrÃ¡ficos operativos</h2>
            <p class="mut-mini">Panel de telemetrÃ­a con foco SOC. Resumen 14 dÃ­as con KPIs y comparativas.</p>
          </div>
          <div class="analytics-hub-actions">
            <div class="range-chips">
              <button type="button" class="chip is-active">14 dÃ­as</button>
              <button type="button" class="chip">30 dÃ­as</button>
              <button type="button" class="chip">90 dÃ­as</button>
            </div>
            <div class="kpi-pill">alertas 24h <b><?= (int) ($metrics['alerts_24h'] ?? 0); ?></b></div>
            <div class="kpi-pill">bloqueos 24h <b><?= (int) ($metrics['blocks_24h'] ?? 0); ?></b></div>
            <div class="kpi-pill">ratio 24h <b><?= number_format($blockRate24h ?? 0, 2); ?>%</b></div>
          </div>
        </div>
        <div class="analytics-hub-grid">
          <div class="analytics-panel analytics-panel--wide">
            <div class="panel-head">
              <div>
                <h3>Tendencia diaria</h3>
                <span class="mut">Alertas vs bloqueos</span>
              </div>
              <div class="panel-tags">
                <span class="tag blue">Alertas</span>
                <span class="tag green">Bloqueos</span>
              </div>
            </div>
            <canvas
              id="home-trend-chart"
              class="chart-canvas"
              data-labels='<?= clickfix_h(json_encode($homeLabels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?>'
              data-alerts='<?= clickfix_h(json_encode($homeAlerts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?>'
              data-blocks='<?= clickfix_h(json_encode($homeBlocks, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?>'
            ></canvas>
            <div class="chart-legend">
              <span><i class="dot" style="background:#14b8ff"></i>alertas</span>
              <span><i class="dot" style="background:#38d17a"></i>bloqueos</span>
            </div>
          </div>
          <div class="analytics-panel">
            <div class="panel-head">
              <div>
                <h3>Ratio de bloqueo</h3>
                <span class="mut">% diario</span>
              </div>
              <div class="panel-tags">
                <span class="tag amber">% bloqueo</span>
              </div>
            </div>
            <canvas
              id="home-ratio-chart"
              class="chart-canvas"
              data-labels='<?= clickfix_h(json_encode($homeLabels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?>'
              data-alerts='<?= clickfix_h(json_encode($homeAlerts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?>'
              data-blocks='<?= clickfix_h(json_encode($homeBlocks, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?>'
            ></canvas>
            <div class="chart-legend">
              <span><i class="dot" style="background:#ffd166"></i>% bloqueo</span>
            </div>
          </div>
          <div class="analytics-panel">
            <div class="panel-head">
              <div>
                <h3>Estado operativo</h3>
                <span class="mut">KPI principal</span>
              </div>
            </div>
            <div class="panel-kpis">
              <div><span class="mut">Alertas totales</span><b><?= (int) $metrics['total_alerts']; ?></b></div>
              <div><span class="mut">Bloqueos totales</span><b><?= (int) $metrics['total_blocks']; ?></b></div>
              <div><span class="mut">Dominios ?nicos</span><b><?= (int) $metrics['unique_hosts']; ?></b></div>
              <div><span class="mut">Pend. revisiÃ³n</span><b><?= (int) ($metrics['pending_review_total'] ?? 0); ?></b></div>
            </div>
          </div>
          <div class="analytics-panel">
            <div class="panel-head">
              <div>
                <h3>Rendimiento</h3>
                <span class="mut">Comparativa diaria</span>
              </div>
            </div>
            <div class="panel-metrics">
              <div class="panel-metric"><span class="mut">M?x alertas</span><b><?= (int) ($analyticsOverview['max_alerts'] ?? 0); ?></b></div>
              <div class="panel-metric"><span class="mut">M?x bloqueos</span><b><?= (int) ($analyticsOverview['max_blocks'] ?? 0); ?></b></div>
              <div class="panel-metric"><span class="mut">DÃ­as sin alertas</span><b><?= (int) ($analyticsOverview['quiet_days'] ?? 0); ?></b></div>
              <div class="panel-metric"><span class="mut">Ratio medio</span><b><?= number_format($analyticsOverview['avg_block_rate'] ?? 0, 2); ?>%</b></div>
            </div>
          </div>
        </div>
        <div class="analytics-table-card">
          <div class="panel-head">
            <div>
              <h3>Detalle diario</h3>
              <span class="mut">Alertas vs bloqueos por d?a</span>
            </div>
          </div>
          <div class="analytics-table-wrap">
            <table class="table table-striped settings-table">
              <thead><tr><th>D?a</th><th>Alertas</th><th>Bloqueos</th><th>Tendencia</th></tr></thead>
              <tbody>
                <?php foreach ($homeLabels as $idx => $label): ?>
                  <?php
                    $a = isset($homeAlerts[$idx]) ? (int) $homeAlerts[$idx] : 0;
                    $b = isset($homeBlocks[$idx]) ? (int) $homeBlocks[$idx] : 0;
                    $aWidth = max(2, (int) round(($a / $homeTrendMax) * 100));
                    $bWidth = max(2, (int) round(($b / $homeTrendMax) * 100));
                  ?>
                  <tr>
                    <td class="mono"><?= clickfix_h((string) $label); ?></td>
                    <td class="mono"><?= $a; ?></td>
                    <td class="mono"><?= $b; ?></td>
                    <td>
                      <div class="trend-bar alerts"><span style="width:<?= $aWidth; ?>%"></span></div>
                      <div class="trend-bar blocks" style="margin-top:4px"><span style="width:<?= $bWidth; ?>%"></span></div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </section>

        <section class="exposure-grid">
          <article class="card">
            <h2>Top referrers</h2>
            <?php if (empty($exposureReferrers)): ?>
              <p class="mut">Sin referrers externos relevantes en la ventana actual.</p>
            <?php else: ?>
              <div class="analytics-table-wrap">
                <table class="compact-table">
                  <thead><tr><th>Host</th><th>Hits</th><th>Reportes</th><th>Overlap</th><th>Ãšltimo</th></tr></thead>
                  <tbody>
                    <?php foreach ($exposureReferrers as $refRow): ?>
                      <tr>
                        <td class="mono"><?= clickfix_h((string) ($refRow['host'] ?? '-')); ?></td>
                        <td class="mono"><?= (int) ($refRow['hits'] ?? 0); ?></td>
                        <td class="mono"><?= (int) ($refRow['reported_hits'] ?? 0); ?></td>
                        <td><?= !empty($refRow['overlap']) ? 'si' : 'no'; ?></td>
                        <td class="mono"><?= clickfix_h((string) ($refRow['last_seen'] ?? '-')); ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </article>
          <article class="card">
            <h2>Top redes / ASN</h2>
            <?php if (empty($exposureNetworks)): ?>
              <p class="mut">Sin redes destacables en la ventana actual.</p>
            <?php else: ?>
              <div class="analytics-table-wrap">
                <table class="compact-table">
                  <thead><tr><th>Red</th><th>Hits</th><th>IPs</th><th>Overlap</th><th>Sospechosos</th></tr></thead>
                  <tbody>
                    <?php foreach ($exposureNetworks as $networkRow): ?>
                      <tr>
                        <td><?= clickfix_h((string) ($networkRow['network'] ?? '-')); ?></td>
                        <td class="mono"><?= (int) ($networkRow['hits'] ?? 0); ?></td>
                        <td class="mono"><?= (int) ($networkRow['unique_ips'] ?? 0); ?></td>
                        <td class="mono"><?= (int) ($networkRow['overlap_hits'] ?? 0); ?></td>
                        <td class="mono"><?= (int) ($networkRow['suspicious_hits'] ?? 0); ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </article>
        </section>
        <section class="card">
          <h2>Eventos de exposici?n investigables</h2>
          <?php if (empty($exposureEvents)): ?>
            <p class="mut">No hay cruces suficientes para mostrar eventos investigables.</p>
          <?php else: ?>
            <div class="analytics-table-wrap">
              <table class="compact-table">
                <thead><tr><th>Fecha</th><th>Path</th><th>IP</th><th>UbicaciÃ³n</th><th>Red</th><th>Referrer</th><th>Flags</th><th>Dominios relacionados</th></tr></thead>
                <tbody>
                  <?php foreach ($exposureEvents as $eventRow): ?>
                    <?php
                      $locationParts = array_values(array_filter([
                          (string) ($eventRow['city'] ?? ''),
                          (string) ($eventRow['region_name'] ?? ''),
                          (string) ($eventRow['country_code'] ?? ''),
                      ]));
                      $locationLabel = $locationParts ? implode(', ', $locationParts) : ((string) ($eventRow['country_name'] ?? '-'));
                      $flagList = is_array($eventRow['flags'] ?? null) ? $eventRow['flags'] : [];
                      $domainsList = is_array($eventRow['matched_domains'] ?? null) ? $eventRow['matched_domains'] : [];
                    ?>
                    <tr>
                      <td class="mono"><?= clickfix_h((string) ($eventRow['created_at'] ?? '-')); ?></td>
                      <td class="mono"><?= clickfix_h((string) ($eventRow['path'] ?? '/')); ?></td>
                      <td class="mono"><?= clickfix_h((string) ($eventRow['ip'] ?? '-')); ?></td>
                      <td><?= clickfix_h($locationLabel); ?></td>
                      <td><?= clickfix_h((string) ($eventRow['network'] ?? '-')); ?></td>
                      <td class="mono"><?= clickfix_h((string) ($eventRow['referrer_host'] ?? '-')); ?></td>
                      <td>
                        <div class="exposure-flag-wrap">
                          <?php foreach ($flagList as $flag): ?>
                            <span class="exposure-flag <?= clickfix_h((string) $flag); ?>"><?= clickfix_h((string) $flag); ?></span>
                          <?php endforeach; ?>
                        </div>
                      </td>
                      <td><div class="exposure-domains"><?= clickfix_h(implode(', ', $domainsList)); ?></div></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
          </div>
      </section>
      <?php endif; ?>
      <section class="card">
        <h2>GrÃ¡ficos globales (14 dÃ­as)</h2>
        <p class="mut-mini">Vista resumida de alertas y bloqueos diarios para Inicio.</p>
        <div class="chart-stack">
          <div class="chart-card">
            <p class="chart-title">Tendencia diaria</p>
            <canvas
              id="home-trend-chart"
              class="chart-canvas"
              data-labels='<?= clickfix_h(json_encode($homeLabels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?>'
              data-alerts='<?= clickfix_h(json_encode($homeAlerts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?>'
              data-blocks='<?= clickfix_h(json_encode($homeBlocks, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?>'
            ></canvas>
            <div class="chart-legend">
              <span><i class="dot" style="background:#14b8ff"></i>alertas</span>
              <span><i class="dot" style="background:#38d17a"></i>bloqueos</span>
            </div>
          </div>
          <div class="chart-card">
            <p class="chart-title">Ratio de bloqueo por dÃ­a</p>
            <canvas
              id="home-ratio-chart"
              class="chart-canvas"
              data-labels='<?= clickfix_h(json_encode($homeLabels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?>'
              data-alerts='<?= clickfix_h(json_encode($homeAlerts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?>'
              data-blocks='<?= clickfix_h(json_encode($homeBlocks, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?>'
            ></canvas>
            <div class="chart-legend">
              <span><i class="dot" style="background:#ffd166"></i>% bloqueo</span>
            </div>
          </div>
        </div>
        <table class="table table-striped settings-table">
          <thead><tr><th>Dia</th><th>Alertas</th><th>Bloqueos</th><th>Tendencia</th></tr></thead>
          <tbody>
            <?php foreach ($homeLabels as $idx => $label): ?>
              <?php
                $a = isset($homeAlerts[$idx]) ? (int) $homeAlerts[$idx] : 0;
                $b = isset($homeBlocks[$idx]) ? (int) $homeBlocks[$idx] : 0;
                $aWidth = max(2, (int) round(($a / $homeTrendMax) * 100));
                $bWidth = max(2, (int) round(($b / $homeTrendMax) * 100));
              ?>
              <tr>
                <td class="mono"><?= clickfix_h((string) $label); ?></td>
                <td class="mono"><?= $a; ?></td>
                <td class="mono"><?= $b; ?></td>
                <td>
                  <div class="trend-bar alerts"><span style="width:<?= $aWidth; ?>%"></span></div>
                  <div class="trend-bar blocks" style="margin-top:4px"><span style="width:<?= $bWidth; ?>%"></span></div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </section>
      <?php if (!empty($scanViewerAdmin)): ?>
        <section class="card" style="margin-bottom:8px">
          <h2>Cola de aprobacion de capturas</h2>
          <?php if (empty($scanReviewQueue)): ?>
            <p class="mut">No hay capturas pendientes.</p>
          <?php else: ?>
            <table>
              <thead><tr><th>Report</th><th>Fecha</th><th>Dominio</th><th>Tipo</th><th>Preview</th><th>AcciÃ³n</th></tr></thead>
              <tbody>
                <?php foreach (array_slice($scanReviewQueue, 0, 80) as $pendingScan): ?>
                  <?php
                    $pendingReportId = (int) ($pendingScan['report_id'] ?? 0);
                    $pendingKind = (string) ($pendingScan['kind'] ?? '');
                    $pendingExists = !empty($pendingScan['asset_exists']);
                    $pendingPreviewUrl = (string) ($pendingScan['preview_url'] ?? '');
                    $pendingDownloadUrl = $pendingPreviewUrl !== '' ? ($pendingPreviewUrl . '&download=1') : '';
                  ?>
                  <tr>
                    <td class="mono"><?= $pendingReportId; ?></td>
                    <td class="mono"><?= clickfix_h((string) ($pendingScan['received_at'] ?? '')); ?></td>
                    <td class="mono"><?= clickfix_h((string) ($pendingScan['hostname'] ?? '-')); ?></td>
                    <td class="mono"><?= clickfix_h($pendingKind); ?></td>
                    <td>
                      <?php if ($pendingExists): ?>
                        <a class="btn" href="<?= clickfix_h($pendingPreviewUrl); ?>" target="_blank" rel="noopener noreferrer">Abrir (manual)</a>
                        <a class="btn" href="<?= clickfix_h($pendingDownloadUrl); ?>">Descargar</a>
                        <button class="btn" type="button" data-copy-text="<?= clickfix_h($pendingPreviewUrl); ?>">Copiar URL admin</button>
                      <?php else: ?>
                        <span class="mut">no disponible</span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <form method="post" style="margin-bottom:6px">
                        <input type="hidden" name="action" value="scan_image_review">
                        <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                        <input type="hidden" name="scan_report_id" value="<?= $pendingReportId; ?>">
                        <input type="hidden" name="scan_kind" value="<?= clickfix_h($pendingKind); ?>">
                        <input type="hidden" name="scan_status" value="approved">
                        <input type="hidden" name="scan_note" value="approved from queue quick-use">
                        <input type="hidden" name="return_page" value="home">
                        <button class="btn btn-primary btn-sm" type="submit">Aprobar y usar</button>
                      </form>
                      <form method="post">
                        <input type="hidden" name="action" value="scan_image_review">
                        <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                        <input type="hidden" name="scan_report_id" value="<?= $pendingReportId; ?>">
                        <input type="hidden" name="scan_kind" value="<?= clickfix_h($pendingKind); ?>">
                        <input type="hidden" name="return_page" value="home">
                        <select name="scan_status">
                          <option value="approved">approved</option>
                          <option value="rejected">rejected</option>
                          <option value="pending" selected>pending</option>
                        </select>
                        <button class="btn btn-primary btn-sm" type="submit">Aplicar</button>
                      </form>
                      <form method="post" style="margin-top:6px" onsubmit="return confirm('Eliminar captura <?= clickfix_h($pendingKind); ?> del report #<?= $pendingReportId; ?>?');">
                        <input type="hidden" name="action" value="scan_image_delete">
                        <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                        <input type="hidden" name="scan_report_id" value="<?= $pendingReportId; ?>">
                        <input type="hidden" name="scan_kind" value="<?= clickfix_h($pendingKind); ?>">
                        <input type="hidden" name="return_page" value="home">
                        <button class="btn btn-primary btn-sm" type="submit">Eliminar captura</button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
          </div>
      </section>
      <?php endif; ?>
    <?php endif; ?>

    <?php if ($page === 'about'): ?>
      <section class="row about-hero">
        <article class="col-12 col-xl-8 grid-margin stretch-card">
          <div class="card">
            <div class="card-body">
              <p class="text-muted mb-1">ClickFix Command Center</p>
              <h2 class="mb-2"><?= clickfix_h(cft('about_project_title')); ?></h2>
              <p class="text-muted mb-3"><?= clickfix_h(cft('about_project_intro')); ?></p>
              <div class="coverage-chip-row">
                <span class="badge badge-outline-info">Trustware</span>
                <span class="badge badge-outline-success">Ops-first</span>
                <span class="badge badge-outline-warning">Investigation</span>
                <span class="badge badge-outline-primary">Transparent</span>
              </div>
            </div>
          </div>
        </article>
        <article class="col-12 col-xl-4 grid-margin stretch-card">
          <div class="card">
            <div class="card-body">
              <h4 class="card-title">Impacto operativo</h4>
              <div class="row">
                <div class="col-6 mb-3">
                  <p class="text-muted mb-1">Alertas totales</p>
                  <h5 class="mb-0" data-live-metric="total_alerts"><?= (int) $metrics['total_alerts']; ?></h5>
                </div>
                <div class="col-6 mb-3">
                  <p class="text-muted mb-1">Bloqueos totales</p>
                  <h5 class="mb-0" data-live-metric="total_blocks"><?= (int) $metrics['total_blocks']; ?></h5>
                </div>
                <div class="col-6">
                  <p class="text-muted mb-1">dominios Ãºnicos</p>
                  <h5 class="mb-0" data-live-metric="unique_hosts"><?= (int) $metrics['unique_hosts']; ?></h5>
                </div>
                <div class="col-6">
                  <p class="text-muted mb-1">Regiones</p>
                  <h5 class="mb-0" data-live-metric="countries_count"><?= (int) ($metrics['countries_count'] ?? 0); ?></h5>
                </div>
              </div>
            </div>
          </div>
        </article>
      </section>

      <section class="row">
        <article class="col-12 col-lg-7 grid-margin stretch-card">
          <div class="card">
            <div class="card-body">
              <h4 class="card-title">Mision y capacidades</h4>
              <ul class="coverage-list">
                <li><span class="dot dot-primary"></span><?= clickfix_h(cft('about_project_p1')); ?></li>
                <li><span class="dot dot-info"></span><?= clickfix_h(cft('about_project_p2')); ?></li>
                <li><span class="dot dot-success"></span><?= clickfix_h(cft('about_project_p3')); ?></li>
                <li><span class="dot dot-warning"></span><?= clickfix_h(cft('about_project_p4')); ?></li>
                <li><span class="dot dot-danger"></span><?= clickfix_h(cft('about_project_p5')); ?></li>
              </ul>
            </div>
          </div>
        </article>
        <article class="col-12 col-lg-5 grid-margin stretch-card">
          <div class="card">
            <div class="card-body">
              <h4 class="card-title"><?= clickfix_h(cft('about_owner_title')); ?></h4>
              <p class="mb-1"><b><?= clickfix_h($ownerName); ?></b></p>
              <p class="text-muted"><?= clickfix_h(cft('about_owner_text')); ?></p>
              <p class="text-muted mb-2"><?= clickfix_h(cft('about_contact_links')); ?></p>
              <div class="tags">
                <?php if ($contactEmail !== ''): ?>
                  <a class="tag" href="mailto:<?= clickfix_h($contactEmail); ?>">Email</a>
                <?php endif; ?>
                <?php if ($contactWebsite !== ''): ?>
                  <a class="tag" href="<?= clickfix_h($contactWebsite); ?>" target="_blank" rel="noopener noreferrer">Web</a>
                <?php endif; ?>
                <?php if ($contactLinkedIn !== ''): ?>
                  <a class="tag" href="<?= clickfix_h($contactLinkedIn); ?>" target="_blank" rel="noopener noreferrer">LinkedIn</a>
                <?php endif; ?>
                <?php if ($contactX !== ''): ?>
                  <a class="tag" href="<?= clickfix_h($contactX); ?>" target="_blank" rel="noopener noreferrer">X</a>
                <?php endif; ?>
                <?php if ($contactGitHub !== ''): ?>
                  <a class="tag" href="<?= clickfix_h($contactGitHub); ?>" target="_blank" rel="noopener noreferrer">GitHub</a>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </article>
      </section>

      <section class="row">
        <article class="col-12 grid-margin stretch-card">
          <div class="card">
            <div class="card-body">
              <h4 class="card-title">Principios de disenio</h4>
              <div class="coverage-flow">
                <div class="flow-step">
                  <span class="step-index">01</span>
                  <div>
                    <b>Transparencia</b>
                    <p class="text-muted mb-0">Cada alerta tiene contexto explicable y trazable.</p>
                  </div>
                </div>
                <div class="flow-step">
                  <span class="step-index">02</span>
                  <div>
                    <b>Control operativo</b>
                    <p class="text-muted mb-0">Acciones rÃ¡pidas para bloquear, investigar y aprender.</p>
                  </div>
                </div>
                <div class="flow-step">
                  <span class="step-index">03</span>
                  <div>
                    <b>Privacidad</b>
                    <p class="text-muted mb-0">Minimizamos datos y hacemos redaction por rol.</p>
                  </div>
                </div>
                <div class="flow-step">
                  <span class="step-index">04</span>
                  <div>
                    <b>Iteracion</b>
                    <p class="text-muted mb-0">Feedback directo de analistas y respuesta en ciclos cortos.</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </article>
      </section>
    <?php elseif ($page === 'coverage'): ?>
      <section class="row coverage-hero">
        <article class="col-12 col-xl-8 grid-margin stretch-card">
          <div class="card">
            <div class="card-body">
              <p class="text-muted mb-1">Coverage Control Plane</p>
              <h2 class="mb-2">Cobertura activa, explicable y accionable para detecciÃ³n y contenciÃ³n.</h2>
              <p class="text-muted mb-3">Fusionamos telemetrÃ­a de extensión, scoring backend, inteligencia externa y workflow de investigaciÃ³n para acelerar el tiempo de detecciÃ³n y respuesta.</p>
              <div class="coverage-chip-row">
                <span class="badge badge-outline-info">Realtime</span>
                <span class="badge badge-outline-success">Operational</span>
                <span class="badge badge-outline-warning">Threat Intel</span>
                <span class="badge badge-outline-primary">Case-ready</span>
              </div>
            </div>
          </div>
        </article>
        <article class="col-12 col-xl-4 grid-margin stretch-card">
          <div class="card">
            <div class="card-body">
              <h4 class="card-title">M?tricas clave</h4>
              <div class="row">
                <div class="col-6 mb-3">
                  <p class="text-muted mb-1">Alertas 24h</p>
                  <h5 class="mb-0" data-live-metric="alerts_24h"><?= (int) ($metrics['alerts_24h'] ?? 0); ?></h5>
                </div>
                <div class="col-6 mb-3">
                  <p class="text-muted mb-1">Bloqueos 24h</p>
                  <h5 class="mb-0" data-live-metric="blocks_24h"><?= (int) ($metrics['blocks_24h'] ?? 0); ?></h5>
                </div>
                <div class="col-6">
                  <p class="text-muted mb-1">Cobertura revisiÃ³n</p>
                  <h5 class="mb-0"><?= number_format((float) ($metrics['review_coverage_pct'] ?? 0.0), 2); ?>%</h5>
                </div>
                <div class="col-6">
                  <p class="text-muted mb-1">pend. revisiÃ³n</p>
                  <h5 class="mb-0"><?= (int) ($metrics['pending_review_total'] ?? 0); ?></h5>
                </div>
              </div>
            </div>
          </div>
        </article>
      </section>

      <section class="row">
        <article class="col-12 grid-margin stretch-card">
          <div class="card">
            <div class="card-body">
              <h4 class="card-title">Fuentes de inteligencia</h4>
              <div class="table-responsive">
                <table class="table table-striped">
                  <thead><tr><th>Fuente</th><th>Cobertura</th><th>Uso</th><th>Estado</th></tr></thead>
                  <tbody>
                    <tr><td>Runtime Signals</td><td>ClickFix / command lures</td><td>Detecci?n inline en extensi?n</td><td><span class="badge badge-outline-success">Activo</span></td></tr>
                    <tr><td>Server Scoring</td><td>Correlacion de eventos</td><td>Veredicto backend por riesgo</td><td><span class="badge badge-outline-success">Activo</span></td></tr>
                    <tr><td>Listas de dominio</td><td>allow/block/alert/investigate</td><td>Contencion y excepciones</td><td><span class="badge badge-outline-info">Operativo</span></td></tr>
                    <tr><td>Investigaciones</td><td>Grafo explicativo</td><td>Trazabilidad analista por caso</td><td><span class="badge badge-outline-primary">Case-ready</span></td></tr>
                    <tr><td>YARA / reglas</td><td>Unsafe downloads</td><td>Prevencion pre-ejecucion</td><td><span class="badge badge-outline-warning">Endurecido</span></td></tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </article>
      </section>

      <section class="row">
        <article class="col-12 col-lg-6 grid-margin stretch-card">
          <div class="card">
            <div class="card-body">
              <h4 class="card-title">Cobertura de amenazas</h4>
              <ul class="coverage-list">
                <li><span class="dot dot-danger"></span>Phishing operativo y secuestro de confianza.</li>
                <li><span class="dot dot-warning"></span>ClickFix y secuencias copiar-pegar-ejecutar.</li>
                <li><span class="dot dot-info"></span>Descargas inseguras con reglas preventivas.</li>
                <li><span class="dot dot-primary"></span>Uso Shadow AI con riesgo de fuga de datos.</li>
                <li><span class="dot dot-success"></span>Abuso de infraestructura legitima y suplantacion.</li>
              </ul>
              <p class="text-muted mb-0">La cobertura se enriquece con nuevas reglas, listas y feedback de analistas.</p>
            </div>
          </div>
        </article>
        <article class="col-12 col-lg-6 grid-margin stretch-card">
          <div class="card">
            <div class="card-body">
              <h4 class="card-title">Flujo operativo</h4>
              <div class="coverage-flow">
                <div class="flow-step">
                  <span class="step-index">01</span>
                  <div>
                    <b>Deteccion</b>
                    <p class="text-muted mb-0">Captura seÃ±ales runtime y patrones de comando.</p>
                  </div>
                </div>
                <div class="flow-step">
                  <span class="step-index">02</span>
                  <div>
                    <b>Enriquecimiento</b>
                    <p class="text-muted mb-0">Cruce con listas, reputacion y fuentes externas.</p>
                  </div>
                </div>
                <div class="flow-step">
                  <span class="step-index">03</span>
                  <div>
                    <b>Respuesta</b>
                    <p class="text-muted mb-0">Bloqueo, investigaciÃ³n y comunicaciÃ³n con analistas.</p>
                  </div>
                </div>
                <div class="flow-step">
                  <span class="step-index">04</span>
                  <div>
                    <b>Aprendizaje</b>
                    <p class="text-muted mb-0">Feedback operativo para mejorar reglas y scoring.</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </article>
      </section>
    <?php elseif ($page === 'search'): ?>
      <section class="card search-forensic-card">
        <h2>B?squeda forense</h2>
        <form method="get" class="split search-forensic-form">
          <input type="hidden" name="public" value="1">
          <input type="hidden" name="page" value="search">
          <input name="q" maxlength="180" value="<?= clickfix_h($search); ?>" placeholder="texto libre">
          <input name="domain" maxlength="180" value="<?= clickfix_h($domainFilter); ?>" placeholder="dominio">
          <input name="command" maxlength="180" value="<?= clickfix_h($commandFilter); ?>" placeholder="comando / snippet">
          <input type="date" name="date_from" value="<?= clickfix_h($dateFromFilter); ?>">
          <input type="date" name="date_to" value="<?= clickfix_h($dateToFilter); ?>">
          <button class="btn btn-primary btn-sm search-submit-btn" type="submit">Buscar</button>
        </form>
        <div class="search-results-wrap" style="margin-top:10px">
          <?php
            $searchDetailBase = [
                'q' => $search,
                'domain' => $domainFilter,
                'command' => $commandFilter,
                'date_from' => $dateFromFilter,
                'date_to' => $dateToFilter,
            ];
          ?>
          <table class="table table-striped search-table">
            <thead><tr><th>Fecha</th><th>Dominio</th><th>Mensaje</th><th>Score</th><th>Detalle</th><th>Ver caso</th><th>Caso</th></tr></thead>
            <tbody>
              <?php foreach ($searchResults as $fr): ?>
                <?php
                  $detailParams = $searchDetailBase;
                  $detailParams['report_id'] = (int) ($fr['id'] ?? 0);
                  $detailUrl = cfurl('search', true, $detailParams) . '#event-workbench';
                ?>
                <tr>
                  <td class="mono"><?= clickfix_h((string) ($fr['received_at'] ?? '')); ?></td>
                  <td class="mono"><?= clickfix_h((string) ($fr['hostname'] ?? '-')); ?></td>
                  <?php
                    $rawMsg = (string) ($fr['message'] ?? '');
                    $truncated = mb_substr($rawMsg, 0, 350, 'UTF-8');
                    if (mb_strlen($rawMsg, 'UTF-8') > 350) {
                        $truncated .= 'Ã¢Ã¢â€šÂ¬Â¦';
                    }
                    $formatted = preg_replace('/(?:\\.\\s+|\\s{2,}|\\|\\s*)/', "\n", $truncated);
                  ?>
                  <td class="message-cell"><?= nl2br(clickfix_h($formatted ?? $truncated)); ?></td>
                  <td class="mono"><?= isset($fr['score_total']) ? (int) $fr['score_total'] : 0; ?></td>
                  <td class="mono"><a class="event-related-link" href="<?= clickfix_h($detailUrl); ?>">Ver detalle</a></td>
                  <td class="mono">
                    <?php if (!empty($fr['investigation_id'])): ?>
                      <a class="event-related-link" href="<?= clickfix_h(cfurl('intel', false, ['graph_id' => (int) $fr['investigation_id']])); ?>">Abrir caso</a>
                    <?php else: ?>
                      <span class="mut">-</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if ($loggedIn && clickfix_user_has_min_role($user, 'analyst_jr')): ?>
                      <form method="post">
                        <input type="hidden" name="action" value="report_quick_action">
                        <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                        <input type="hidden" name="quick_mode" value="create_investigation">
                        <input type="hidden" name="report_id" value="<?= (int) ($fr['id'] ?? 0); ?>">
                        <button class="btn btn-outline-light btn-sm" type="submit">Abrir caso</button>
                      </form>
                    <?php else: ?>
                      <span class="mut">-</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if (empty($searchResults)): ?>
                <tr><td colspan="5" class="mut">Sin resultados para los filtros actuales.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>
    <?php elseif ($page === 'access'): ?>
      <section class="access-layout">
        <article class="access-card access-card--half">
          <div class="card">
            <div class="card-body">
              <h4 class="card-title">Solicitar acceso</h4>
              <form method="post">
                <input type="hidden" name="action" value="request_access">
                <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                <div class="row">
                  <div class="col-md-6 mb-3">
                    <input class="form-control" type="url" name="access_linkedin" required placeholder="https://www.linkedin.com/in/...">
                  </div>
                  <div class="col-md-6 mb-3">
                    <input class="form-control" type="url" name="company_website" placeholder="https://company.com">
                  </div>
                  <div class="col-md-4 mb-3">
                    <input class="form-control" type="email" name="access_email" required placeholder="email">
                  </div>
                  <div class="col-md-3 mb-3">
                    <select class="form-control" name="request_lang">
                      <option value="en" selected>en</option>
                      <option value="es">es</option>
                      <option value="ca">ca</option>
                      <option value="fr">fr</option>
                      <option value="de">de</option>
                    </select>
                  </div>
                  <div class="col-md-5 mb-3 d-flex align-items-end">
                    <button class="btn btn-primary w-100" type="submit">Solicitar acceso</button>
                  </div>
                </div>
              </form>
            </div>
          </div>
        </article>
        <article class="access-card access-card--half">
          <div class="card">
            <div class="card-body">
              <h4 class="card-title">Login</h4>
              <form method="post">
                <input type="hidden" name="action" value="login">
                <div class="row">
                  <div class="col-md-6 mb-3">
                    <input class="form-control" type="text" name="username" required placeholder="usuario">
                  </div>
                  <div class="col-md-6 mb-3">
                    <input class="form-control" type="password" name="password" required placeholder="password">
                  </div>
                  <div class="col-12">
                    <button class="btn btn-success w-100" type="submit">Entrar</button>
                  </div>
                </div>
              </form>
              <?php if ($loggedIn): ?>
                <form method="post" class="mt-3">
                  <input type="hidden" name="action" value="logout">
                  <button class="btn btn-outline-light w-100" type="submit">Cerrar SesiÃ³n</button>
                </form>
              <?php endif; ?>
            </div>
          </div>
        </article>
        <article class="access-card access-card--full">
          <div class="card">
            <div class="card-body">
              <h4 class="card-title">Desistimiento</h4>
              <form method="post">
                <input type="hidden" name="action" value="submit_appeal">
                <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                <div class="row">
                  <div class="col-md-4 mb-3">
                    <input class="form-control" type="text" name="appeal_domain" required placeholder="dominio">
                  </div>
                  <div class="col-md-4 mb-3">
                    <input class="form-control" type="text" name="appeal_contact" placeholder="contacto opcional">
                  </div>
                  <div class="col-md-4 mb-3">
                    <input class="form-control" type="text" name="appeal_reason" required placeholder="motivo">
                  </div>
                  <div class="col-12">
                    <button class="btn btn-warning w-100" type="submit">Enviar desistimiento</button>
                  </div>
                </div>
              </form>
            </div>
          </div>
        </article>
        <?php if ($loggedIn): ?>
          <article class="col-12 grid-margin stretch-card">
            <div class="card">
              <div class="card-body">
                <h4 class="card-title">Mi cuenta</h4>
                <p class="text-muted">Los ajustes de cuenta estan separados para mantener el acceso limpio y r?pido.</p>
                <div class="row">
                  <div class="col-md-6 mb-3">
                    <p class="text-muted mb-1">Usuario</p>
                    <a class="d-block" href="<?= clickfix_h(cfprofileurl((int) ($user['id'] ?? 0), [], false)); ?>"><?= clickfix_h((string) ($user['username'] ?? '')); ?></a>
                  </div>
                  <div class="col-md-6 mb-3">
                    <p class="text-muted mb-1">Email</p>
                    <span><?= clickfix_h((string) ($user['email'] ?? '-')); ?></span>
                  </div>
                  <div class="col-md-6 mb-3">
                    <p class="text-muted mb-1">Rol</p>
                    <span><?= clickfix_h((string) ($user['role_label'] ?? '-')); ?></span>
                  </div>
                  <div class="col-md-6 mb-3">
                    <p class="text-muted mb-1">REP</p>
                    <span class="mono"><?= (int) ($user['reputation'] ?? 0); ?></span>
                  </div>
                </div>
                <div class="d-flex gap-2">
                  <a class="btn btn-outline-light" href="<?= clickfix_h(cfprofileurl((int) ($user['id'] ?? 0), [], false)); ?>">Abrir perfil</a>
                  <a class="btn btn-outline-light" href="<?= clickfix_h(cfurl('settings')); ?>">Abrir settings</a>
                </div>
              </div>
            </div>
          </article>
        <?php endif; ?>
      </section>
    <?php elseif ($page === 'profile'): ?>
      <section class="row profile-page">
        <?php if ($profileUser === null): ?>
          <div class="col-12">
            <div class="card">
              <div class="card-body">
                <h2>Perfil no encontrado</h2>
                <p class="mut">No existe el usuario solicitado o no hay datos disponibles.</p>
              </div>
            </div>
          </div>
        <?php else: ?>
          <?php
            $avatarSeed = strtoupper(substr((string) ($profileUser['username'] ?? 'U'), 0, 1));
            $profileAvatarUrl = (string) ($profileUser['profile_avatar_url'] ?? '');
            $tabInvestigationUrl = cfprofileurl((int) ($profileUser['id'] ?? 0), ['tab' => 'investigations']);
            $tabReportsUrl = cfprofileurl((int) ($profileUser['id'] ?? 0), ['tab' => 'reports']);
            $tabSessionsUrl = cfprofileurl((int) ($profileUser['id'] ?? 0), ['tab' => 'sessions']);
            $profilePublicEmail = (string) ($profileUser['email_visible'] !== '' ? $profileUser['email_visible'] : 'private');
          ?>
          <div class="col-12 col-xxl-4">
            <div class="card profile-hero">
              <div class="card-body">
                <div class="profile-hero-head">
                  <div class="profile-avatar-lg<?php if ($profileAvatarUrl !== ''): ?> has-image<?php endif; ?>">
                    <?php if ($profileAvatarUrl !== ''): ?>
                      <img src="<?= clickfix_h($profileAvatarUrl); ?>" alt="avatar">
                    <?php else: ?>
                      <?= clickfix_h($avatarSeed); ?>
                    <?php endif; ?>
                  </div>
                  <div class="profile-title">
                    <h2><?= clickfix_h((string) ($profileUser['display_name'] ?? '')); ?></h2>
                    <div class="text-muted">@<?= clickfix_h((string) ($profileUser['username'] ?? '')); ?></div>
                  </div>
                </div>
                <div class="profile-chips">
                  <span class="badge badge-outline-info">Rol: <?= clickfix_h((string) ($profileUser['role_label'] ?? '-')); ?></span>
                  <span class="badge badge-outline-success">REP <?= (int) ($profileUser['reputation'] ?? 0); ?></span>
                  <span class="badge badge-outline-primary"><?= clickfix_h((string) ($profileUser['preferred_lang'] ?? 'en')); ?></span>
                  <span class="badge badge-outline-warning"><?= clickfix_h((string) ($profileUser['profile_theme'] ?? 'default')); ?></span>
                </div>
                <div class="profile-stat-grid">
                  <div class="profile-stat">
                  <span>Investigaciones</span>
                    <strong><?= count($profileInvestigations); ?></strong>
                  </div>
                  <div class="profile-stat">
                    <span>Reportes</span>
                    <strong><?= count($profileReports); ?></strong>
                  </div>
                  <div class="profile-stat">
                    <span>SesiÃ³nes</span>
                    <strong><?= count($profileSessionHistory); ?></strong>
                  </div>
                  <div class="profile-stat">
                    <span>Email</span>
                    <strong class="mono"><?= clickfix_h($profilePublicEmail); ?></strong>
                  </div>
                </div>
                <div class="profile-kv">
                  <span class="label">Threat.rip</span>
                  <span class="value mono">
                    <?php if (!empty($profileUser['account_threatrip']['visible'])): ?>
                      <a class="user-link" target="_blank" rel="noreferrer" href="<?= clickfix_h((string) ($profileUser['account_threatrip']['url'] ?? '')); ?>">#<?= clickfix_h((string) ($profileUser['account_threatrip']['id'] ?? '')); ?></a>
                    <?php else: ?>
                      private
                    <?php endif; ?>
                  </span>
                </div>
                <div class="profile-kv">
                  <span class="label">VirusTotal</span>
                  <span class="value mono">
                    <?php if (!empty($profileUser['account_virustotal']['visible'])): ?>
                      <a class="user-link" target="_blank" rel="noreferrer" href="<?= clickfix_h((string) ($profileUser['account_virustotal']['url'] ?? '')); ?>"><?= clickfix_h((string) ($profileUser['account_virustotal']['handle'] ?? '')); ?></a>
                    <?php else: ?>
                      private
                    <?php endif; ?>
                  </span>
                </div>
                <div class="profile-kv">
                  <span class="label">AbuseIPDB</span>
                  <span class="value mono">
                    <?php if (!empty($profileUser['account_abuseipdb']['visible'])): ?>
                      <a class="user-link" target="_blank" rel="noreferrer" href="<?= clickfix_h((string) ($profileUser['account_abuseipdb']['url'] ?? '')); ?>">#<?= clickfix_h((string) ($profileUser['account_abuseipdb']['id'] ?? '')); ?></a>
                    <?php else: ?>
                      private
                    <?php endif; ?>
                  </span>
                </div>
                <div class="profile-kv">
                  <span class="label">GitHub</span>
                  <span class="value mono">
                    <?php if (!empty($profileUser['account_github']['visible'])): ?>
                      <a class="user-link" target="_blank" rel="noreferrer" href="<?= clickfix_h((string) ($profileUser['account_github']['url'] ?? '')); ?>"><?= clickfix_h((string) ($profileUser['account_github']['handle'] ?? '')); ?></a>
                    <?php else: ?>
                      private
                    <?php endif; ?>
                  </span>
                </div>
                <div class="profile-actions">
                  <?php if ($profileCanEdit): ?>
                    <a class="btn btn-outline-light" href="<?= clickfix_h(cfurl('settings')); ?>">Settings de cuenta</a>
                  <?php endif; ?>
                  <a class="btn btn-primary" href="<?= clickfix_h(cfprofileurl((int) ($profileUser['id'] ?? 0), [], true)); ?>">Compartir perfil</a>
                </div>
              </div>
            </div>
          </div>
          <div class="col-12 col-xxl-8">
            <div class="card profile-tabs-card">
              <div class="card-body profile-tabs-body">
                <div>
                  <h3 class="profile-section-title">Actividad y casos</h3>
                  <p class="text-muted mb-0">Historial operativo, casos abiertos y seÃ±ales compartidas por el usuario.</p>
                </div>
                <div class="nav nav-pills profile-tabs">
                  <a class="nav-link<?php if ($profileTab === 'investigations'): ?> active<?php endif; ?>" href="<?= clickfix_h($tabInvestigationUrl); ?>">Investigaciones (<?= count($profileInvestigations); ?>)</a>
                  <a class="nav-link<?php if ($profileTab === 'reports'): ?> active<?php endif; ?>" href="<?= clickfix_h($tabReportsUrl); ?>">Reportes (<?= count($profileReports); ?>)</a>
                  <a class="nav-link<?php if ($profileTab === 'sessions'): ?> active<?php endif; ?>" href="<?= clickfix_h($tabSessionsUrl); ?>">Sesines (<?= count($profileSessionHistory); ?>)</a>
                  <?php if ($profileCanEdit): ?>
                    <a class="nav-link" href="<?= clickfix_h(cfurl('settings')); ?>">Ir a settings</a>
                  <?php endif; ?>
                </div>
              </div>
            </div>
            <?php if ($profileCanEdit): ?>
              <div class="card profile-edit-card">
                <div class="card-body">
                  <h4 class="profile-section-title">Editar perfil pÃºblico</h4>
                  <p class="text-muted">Controla qu? informaciÃ³n de contacto y cuentas se expone pblicamente. El tema, idioma y contraseÃ±a se gestionan en Settings.</p>
                  <form method="post" class="profile-edit-form">
                    <input type="hidden" name="action" value="user_self_profile_update">
                    <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                    <div class="row g-3">
                      <div class="col-12">
                        <label class="form-label">Nombre visible</label>
                        <input class="form-control" type="text" name="full_name" maxlength="120" value="<?= clickfix_h((string) ($profileUser['full_name'] ?? '')); ?>" placeholder="Nombre visible">
                      </div>
                      <div class="col-md-6">
                        <label class="form-label">Threat.rip user ID</label>
                        <input class="form-control" type="text" name="profile_threatrip_id" value="<?= clickfix_h((string) ($profileUser['account_threatrip']['id'] ?? '')); ?>" placeholder="Threat.rip user ID">
                        <div class="form-check form-switch mt-2">
                          <input type="hidden" name="profile_threatrip_public" value="0">
                          <input class="form-check-input" type="checkbox" name="profile_threatrip_public" value="1"<?php if (!empty($profileUser['account_threatrip']['is_public'])): ?> checked<?php endif; ?>>
                          <label class="form-check-label">PÃºblico</label>
                        </div>
                      </div>
                      <div class="col-md-6">
                        <label class="form-label">VirusTotal handle</label>
                        <input class="form-control" type="text" name="profile_vt_handle" value="<?= clickfix_h((string) ($profileUser['account_virustotal']['handle'] ?? '')); ?>" placeholder="VirusTotal handle">
                        <div class="form-check form-switch mt-2">
                          <input type="hidden" name="profile_vt_public" value="0">
                          <input class="form-check-input" type="checkbox" name="profile_vt_public" value="1"<?php if (!empty($profileUser['account_virustotal']['is_public'])): ?> checked<?php endif; ?>>
                          <label class="form-check-label">PÃºblico</label>
                        </div>
                      </div>
                      <div class="col-md-6">
                        <label class="form-label">AbuseIPDB user ID</label>
                        <input class="form-control" type="text" name="profile_abuseipdb_id" value="<?= clickfix_h((string) ($profileUser['account_abuseipdb']['id'] ?? '')); ?>" placeholder="AbuseIPDB user ID">
                        <div class="form-check form-switch mt-2">
                          <input type="hidden" name="profile_abuseipdb_public" value="0">
                          <input class="form-check-input" type="checkbox" name="profile_abuseipdb_public" value="1"<?php if (!empty($profileUser['account_abuseipdb']['is_public'])): ?> checked<?php endif; ?>>
                          <label class="form-check-label">PÃºblico</label>
                        </div>
                      </div>
                      <div class="col-md-6">
                        <label class="form-label">GitHub handle</label>
                        <input class="form-control" type="text" name="profile_github_handle" value="<?= clickfix_h((string) ($profileUser['account_github']['handle'] ?? '')); ?>" placeholder="GitHub handle">
                        <div class="form-check form-switch mt-2">
                          <input type="hidden" name="profile_github_public" value="0">
                          <input class="form-check-input" type="checkbox" name="profile_github_public" value="1"<?php if (!empty($profileUser['account_github']['is_public'])): ?> checked<?php endif; ?>>
                          <label class="form-check-label">PÃºblico</label>
                        </div>
                      </div>
                      <div class="col-md-6">
                        <label class="form-label">Email pÃºblico</label>
                        <div class="form-check form-switch">
                          <input type="hidden" name="profile_email_public" value="0">
                          <input class="form-check-input" type="checkbox" name="profile_email_public" value="1"<?php if (!empty($profileUser['email_is_public'])): ?> checked<?php endif; ?>>
                          <label class="form-check-label">Mostrar email</label>
                        </div>
                      </div>
                    </div>
                    <div class="profile-edit-actions">
                      <button class="btn btn-primary" type="submit">Guardar perfil</button>
                      <a class="btn btn-outline-light" href="<?= clickfix_h(cfurl('settings')); ?>">Settings de cuenta</a>
                    </div>
                  </form>
                </div>
              </div>
            <?php endif; ?>
            <div class="card profile-panel">
              <div class="card-body">
                <?php if ($profileTab === 'sessions'): ?>
                  <h4 class="profile-section-title">Historial de SesiÃ³nes</h4>
                  <?php if (!$profileCanViewPrivate): ?>
                    <p class="mut">El historial de SesiÃ³nes es privado.</p>
                  <?php else: ?>
                    <div class="table-responsive">
                      <table class="table table-striped profile-table">
                        <thead><tr><th>Fecha</th><th>Evento</th><th>IP</th><th>SesiÃ³n</th><th>User-Agent</th></tr></thead>
                        <tbody>
                          <?php foreach ($profileSessionHistory as $sessionEvent): ?>
                            <?php
                              $sessionAction = strtolower((string) ($sessionEvent['event_type'] ?? 'unknown'));
                              $sessionActionLabel = $sessionAction === 'login' ? 'login' : ($sessionAction === 'logout' ? 'logout' : $sessionAction);
                            ?>
                            <tr>
                              <td class="mono"><?= clickfix_h((string) ($sessionEvent['created_at'] ?? '')); ?></td>
                              <td class="mono"><?= clickfix_h($sessionActionLabel); ?></td>
                              <td class="mono"><?= clickfix_h((string) ($sessionEvent['ip'] ?? '-')); ?></td>
                              <td class="mono"><?= clickfix_h((string) ($sessionEvent['session_id'] ?? '-')); ?></td>
                              <td class="text-wrap"><?= clickfix_h((string) ($sessionEvent['user_agent'] ?? '')); ?></td>
                            </tr>
                          <?php endforeach; ?>
                          <?php if (empty($profileSessionHistory)): ?><tr><td colspan="5" class="mut">Sin Sesines registradas.</td></tr><?php endif; ?>
                        </tbody>
                      </table>
                    </div>
                  <?php endif; ?>
                <?php elseif ($profileTab === 'reports'): ?>
                  <h4 class="profile-section-title">Reportes del usuario</h4>
                  <?php if (!$profileCanViewPrivate): ?>
                    <p class="mut">Los reportes del usuario son privados.</p>
                  <?php endif; ?>
                  <div class="table-responsive">
                    <table class="table table-striped profile-table">
                      <thead><tr><th>Fecha</th><th>Dominio</th><th>Mensaje</th><th>Estado</th><th>AcciÃ³n</th></tr></thead>
                      <tbody>
                        <?php foreach ($profileReports as $pr): ?>
                          <?php
                            $actionLabel = ((int) ($pr['reviewed_by'] ?? 0) === (int) ($profileUser['id'] ?? 0))
                                ? ('review:' . (string) ($pr['review_status'] ?? 'pending'))
                                : 'accepted';
                          ?>
                          <tr>
                            <td class="mono"><?= clickfix_h((string) ($pr['received_at'] ?? '')); ?></td>
                            <td class="mono"><?= clickfix_h((string) ($pr['hostname'] ?? '-')); ?></td>
                            <td class="text-wrap"><?= clickfix_h((string) ($pr['message'] ?? '')); ?></td>
                            <td class="mono"><?= clickfix_h((string) ($pr['review_status'] ?? 'pending')); ?></td>
                            <td class="mono"><?= clickfix_h($actionLabel); ?></td>
                          </tr>
                        <?php endforeach; ?>
                        <?php if (empty($profileReports)): ?><tr><td colspan="5" class="mut">Sin reportes asociados.</td></tr><?php endif; ?>
                      </tbody>
                    </table>
                  </div>
                <?php else: ?>
                  <h4 class="profile-section-title">Investigaciones del usuario</h4>
                  <div class="table-responsive">
                    <table class="table table-striped profile-table">
                      <thead><tr><th>Actualizada</th><th>TÃ­tulo</th><th>Dominio</th><th>Veredicto</th><th>Estado</th></tr></thead>
                      <tbody>
                        <?php foreach ($profileInvestigations as $pi): ?>
                          <tr>
                            <td class="mono"><?= clickfix_h((string) ($pi['updated_at'] ?? '')); ?></td>
                            <td><?= clickfix_h((string) ($pi['title'] ?? '')); ?></td>
                            <td class="mono"><?= clickfix_h((string) ($pi['site_domain'] ?? '-')); ?></td>
                            <td class="mono"><?= clickfix_h((string) ($pi['verdict'] ?? 'unknown')); ?></td>
                            <td class="mono"><?= clickfix_h((string) (!empty($pi['is_public']) ? 'public' : 'private')); ?></td>
                          </tr>
                        <?php endforeach; ?>
                        <?php if (empty($profileInvestigations)): ?><tr><td colspan="5" class="mut">Sin investigaciones visibles.</td></tr><?php endif; ?>
                      </tbody>
                    </table>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          </div>
        <?php endif; ?>
      </section>
    <?php elseif ($page === 'settings' && $loggedIn): ?>
      <?php
        $selfLang = clickfix_normalize_user_language((string) ($user['preferred_lang'] ?? 'en'));
        $selfTheme = clickfix_profile_normalize_theme((string) ($user['profile_theme'] ?? 'default'));
        $selfAvatarUrl = (string) ($user['profile_avatar_url'] ?? '');
        $settingsLookupProviderDefault = (string) ($intelApiLookupResult['provider'] ?? 'virustotal');
        $settingsLookupTargetDefault = trim((string) ($intelApiLookupResult['target'] ?? ''));
      ?>
      <section class="row settings-page">
        <div class="col-12 col-xl-7">
          <div class="card settings-card">
            <div class="card-body">
              <div class="settings-head">
                <div>
                  <h2>Settings de cuenta</h2>
                  <p class="text-muted">Gestiona idioma, tema visual y foto de usuario para tu cuenta.</p>
                </div>
                <span class="settings-pill">Cuenta</span>
              </div>
              <form method="post" class="settings-form">
                <input type="hidden" name="action" value="user_self_update_account">
                <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                <div class="row g-3">
                  <div class="col-md-6">
                    <label class="form-label" for="self-lang">Idioma por defecto</label>
                    <select id="self-lang" name="self_lang" class="form-select">
                      <option value="en"<?= $selfLang === 'en' ? ' selected' : ''; ?>>en</option>
                      <option value="es"<?= $selfLang === 'es' ? ' selected' : ''; ?>>es</option>
                      <option value="ca"<?= $selfLang === 'ca' ? ' selected' : ''; ?>>ca</option>
                      <option value="de"<?= $selfLang === 'de' ? ' selected' : ''; ?>>de</option>
                      <option value="fr"<?= $selfLang === 'fr' ? ' selected' : ''; ?>>fr</option>
                    </select>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label" for="self-theme">Tema visual</label>
                    <select id="self-theme" name="self_theme" class="form-select">
                      <option value="default"<?= $selfTheme === 'default' ? ' selected' : ''; ?>>default</option>
                      <option value="teal"<?= $selfTheme === 'teal' ? ' selected' : ''; ?>>teal</option>
                      <option value="sunset"<?= $selfTheme === 'sunset' ? ' selected' : ''; ?>>sunset</option>
                      <option value="mono"<?= $selfTheme === 'mono' ? ' selected' : ''; ?>>mono</option>
                    </select>
                  </div>
                  <div class="col-12">
                    <label class="form-label" for="self-avatar-url">Foto de usuario (URL)</label>
                    <input type="url" class="form-control" id="self-avatar-url" name="self_avatar_url" maxlength="420" value="<?= clickfix_h($selfAvatarUrl); ?>" placeholder="https://example.com/avatar.png">
                    <div class="settings-avatar-actions">
                      <button class="btn btn-outline-light btn-sm" type="button" data-avatar-source="github" data-avatar-handle="<?= clickfix_h((string) ($user['profile_github_handle'] ?? '')); ?>">Usar avatar GitHub</button>
                      <button class="btn btn-outline-light btn-sm" type="button" data-avatar-source="threatrip" data-avatar-handle="<?= clickfix_h((string) ($user['profile_threatrip_id'] ?? '')); ?>">Usar avatar ThreatRip</button>
                      <button class="btn btn-outline-light btn-sm" type="button" data-avatar-source="virustotal" data-avatar-handle="<?= clickfix_h((string) ($user['profile_vt_handle'] ?? '')); ?>">Usar avatar VirusTotal</button>
                      <span class="mut mini" id="avatar-source-help">Si ThreatRip/VirusTotal no exponen URL pÃºblica, pega la URL directamente.</span>
                    </div>
                  </div>
                </div>
                <div class="settings-actions">
                  <button class="btn btn-primary" type="submit">Guardar ajustes</button>
                </div>
              </form>
            </div>
          </div>
          <div class="card settings-card">
            <div class="card-body">
              <div class="settings-head">
                <div>
                  <h2>Seguridad</h2>
                  <p class="text-muted">Actualiza tu contraseÃ±a para proteger el acceso.</p>
                </div>
                <span class="settings-pill">Acceso</span>
              </div>
              <form method="post" class="settings-form">
                <input type="hidden" name="action" value="user_self_change_password">
                <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                <div class="row g-3">
                  <div class="col-12">
                    <label class="form-label">ContraseÃ±a actual</label>
                    <input type="password" class="form-control" name="self_current_password" required placeholder="ContraseÃ±a actual">
                  </div>
                  <div class="col-12">
                    <label class="form-label">Nueva contraseÃ±a</label>
                    <input type="password" class="form-control" name="self_new_password" minlength="10" required placeholder="Nueva contraseÃ±a (mÃ­nimo 10 caracteres)">
                  </div>
                </div>
                <div class="settings-actions">
                  <button class="btn btn-outline-light" type="submit">Cambiar contraseÃ±a</button>
                </div>
              </form>
            </div>
          </div>
        </div>
        <div class="col-12 col-xl-5">
          <div class="card settings-card settings-summary">
            <div class="card-body">
              <div class="settings-head">
                <div>
                  <h2>Resumen de cuenta</h2>
                  <p class="text-muted">Vista r?pida de tu estado dentro de la plataforma.</p>
                </div>
                <span class="settings-pill">Perfil</span>
              </div>
              <div class="settings-kv">
                <span>Usuario</span>
                <strong class="mono"><?= clickfix_h((string) ($user['username'] ?? '')); ?></strong>
              </div>
              <div class="settings-kv">
                <span>Rol</span>
                <strong><?= clickfix_h((string) ($user['role_label'] ?? '')); ?></strong>
              </div>
              <div class="settings-kv">
                <span>REP</span>
                <strong class="mono"><?= (int) ($user['reputation'] ?? 0); ?></strong>
              </div>
              <div class="settings-kv">
                <span>Idioma</span>
                <strong class="mono"><?= clickfix_h((string) ($user['preferred_lang'] ?? 'en')); ?></strong>
              </div>
              <div class="settings-kv">
                <span>Tema</span>
                <strong class="mono"><?= clickfix_h((string) ($user['profile_theme'] ?? 'default')); ?></strong>
              </div>
              <div class="settings-kv">
                <span>Estado</span>
                <strong><?= !empty($user['is_admin']) ? 'Administrador' : 'Operador'; ?></strong>
              </div>
              <div class="settings-actions">
                <a class="btn btn-outline-light" href="<?= clickfix_h(cfprofileurl((int) ($user['id'] ?? 0), [], false)); ?>">Abrir perfil</a>
              </div>
            </div>
          </div>
          <?php if ($canManageConfigs): ?>
          <div class="card settings-card">
            <div class="card-body">
              <div class="settings-head">
                <div>
                  <h2>Mapa p?blico</h2>
                  <p class="text-muted">Evita saturar el globo de <code>index.php</code> limitando cu?ntos puntos se representan por pa?s.</p>
                </div>
                <span class="settings-pill">Landing</span>
              </div>
              <form method="post" class="settings-form">
                <input type="hidden" name="action" value="public_preview_settings_save">
                <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                <div class="row g-3">
                  <div class="col-12">
                    <label class="form-check form-switch">
                      <input class="form-check-input" type="checkbox" name="preview_limit_points_per_country" value="1"<?= !empty($publicPreviewSettings['limit_points_per_country']) ? ' checked' : ''; ?>>
                      <span class="form-check-label">Limitar puntos por pa?s en el mapa global</span>
                    </label>
                    <div class="mut mini" style="margin-top:6px">Si est? activo, el globo mostrar? como m?ximo el n?mero indicado por pa?s para evitar sobrecarga visual.</div>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label" for="preview-max-points-per-country">M?ximo de puntos por pa?s</label>
                    <input type="number" class="form-control" id="preview-max-points-per-country" name="preview_max_points_per_country" min="1" max="12" value="<?= (int) ($publicPreviewSettings['max_points_per_country'] ?? 2); ?>">
                  </div>
                </div>
                <div class="settings-actions">
                  <button class="btn btn-primary" type="submit">Guardar mapa p?blico</button>
                </div>
              </form>
            </div>
          </div>
          <?php endif; ?>
        </div>
      </section>
      <?php if ($showApiUi): ?>
      <section class="card settings-api-card">
        <div class="card-body">
          <h2>APIs de investigaciÃ³n y plataforma</h2>
        <p class="mut">Las API keys son por usuario: solo tu puedes verlas, cambiarlas y usarlas.</p>
        <table class="table table-striped settings-table">
          <thead>
            <tr>
              <th>Proveedor</th>
              <th>API key</th>
              <th><?= clickfix_h(cft('intel_api_key_masked')); ?></th>
              <th><?= clickfix_h(cft('intel_api_key_updated')); ?></th>
              <th>AcciÃ³n</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($intelUserApiKeys as $apiRow): ?>
              <?php
                $providerKey = (string) ($apiRow['provider'] ?? '');
                $providerLabel = (string) ($apiRow['label'] ?? $providerKey);
                $providerHelp = (string) ($apiRow['help'] ?? '');
                $providerInputId = 'settings-api-key-' . preg_replace('/[^a-z0-9_]/', '_', strtolower($providerKey));
                $providerMasked = (string) ($apiRow['masked'] ?? '');
                $providerPlain = (string) ($apiRow['api_key'] ?? '');
              ?>
              <tr>
                <td>
                  <b><?= clickfix_h($providerLabel); ?></b>
                  <?php if ($providerHelp !== ''): ?><div class="mut-mini"><?= clickfix_h($providerHelp); ?></div><?php endif; ?>
                </td>
                <td>
                  <form method="post" class="api-key-row-form">
                    <input type="hidden" name="action" value="investigation_api_key_save">
                    <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                    <input type="hidden" name="return_page" value="settings">
                    <input type="hidden" name="graph_id" value="0">
                    <input type="hidden" name="provider" value="<?= clickfix_h($providerKey); ?>">
                    <input
                      type="text"
                      id="<?= clickfix_h($providerInputId); ?>"
                      class="form-control" name="api_key"
                      value="<?= clickfix_h($providerMasked); ?>"
                      data-api-key-masked="<?= clickfix_h($providerMasked); ?>"
                      data-api-key-plain="<?= clickfix_h($providerPlain); ?>"
                      data-api-key-revealed="0"
                      maxlength="600"
                      autocomplete="off"
                      placeholder="API key"
                    >
                    <button type="button" class="btn btn-outline-light btn-sm api-key-toggle" data-toggle-api-key="<?= clickfix_h($providerInputId); ?>">ver</button>
                    <button class="btn btn-primary btn-sm" type="submit"><?= clickfix_h(cft('intel_api_key_save')); ?></button>
                  </form>
                </td>
                <td class="mono"><?= !empty($apiRow['has_key']) ? clickfix_h((string) ($apiRow['masked'] ?? '')) : '-'; ?></td>
                <td class="mono"><?= clickfix_h((string) ($apiRow['updated_at'] ?? '')); ?></td>
                <td class="mono">
                  <?php if (!empty($apiRow['has_key'])): ?>
                    <form method="post" class="api-key-delete-form" onsubmit="return confirm('Eliminar API key de este proveedor?');">
                      <input type="hidden" name="action" value="investigation_api_key_delete">
                      <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                      <input type="hidden" name="return_page" value="settings">
                      <input type="hidden" name="graph_id" value="0">
                      <input type="hidden" name="provider" value="<?= clickfix_h($providerKey); ?>">
                      <button type="submit" class="btn btn-outline-light btn-sm"><?= clickfix_h(cft('intel_api_key_delete')); ?></button>
                    </form>
                  <?php else: ?>
                    <span class="mut">-</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>

        <h3 style="margin-top:14px">API de plataforma</h3>
        <p class="mut">Genera y revoca API keys para integraciones externas (OpenCTI, Mitaka, Sputnik, etc).</p>

        <?php if (is_array($platformApiKeyJustCreated) && !empty($platformApiKeyJustCreated['api_key'])): ?>
          <div class="api-result">
            <b>API key nueva (se muestra una sola vez)</b>
            <div class="mut">CÃ³piala ahora y guÃ¡rdala en un vault seguro.</div>
            <pre><?= clickfix_h((string) ($platformApiKeyJustCreated['api_key'] ?? '')); ?></pre>
          </div>
        <?php endif; ?>

        <form method="post" style="margin-top:8px">
          <input type="hidden" name="action" value="platform_api_key_create">
          <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
          <input type="hidden" name="return_page" value="settings">
          <input type="hidden" name="graph_id" value="0">
          <div class="intel-grid">
            <div>
              <label>Etiqueta</label>
              <input class="form-control" type="text" name="platform_api_label" maxlength="80" placeholder="opencti-main">
            </div>
            <div>
              <label>Expira en dÃ­as (1-365)</label>
              <input class="form-control" type="number" name="platform_api_expires_days" min="1" max="365" value="90">
            </div>
            <div>
              <label>Rate limit RPM (30-2000)</label>
              <input class="form-control" type="number" name="platform_api_max_rpm" min="30" max="2000" value="120">
            </div>
          </div>
          <div class="intel-toolbar">
            <button class="btn btn-primary btn-sm" type="submit">Generar API key segura</button>
          </div>
        </form>

        <div style="margin-top:10px;overflow:auto">
          <table class="table table-striped settings-table">
            <thead>
              <tr>
                <th>ID</th>
                <th>Etiqueta</th>
                <th>Prefijo</th>
                <th>Scopes</th>
                <th>RPM</th>
                <th>?Â¡ltimo uso</th>
                <th>Expira</th>
                <th>Estado</th>
                <th>AcciÃ³n</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($platformApiKeys)): ?>
                <?php foreach ($platformApiKeys as $platformKeyRow): ?>
                  <?php
                    $pkId = (int) ($platformKeyRow['id'] ?? 0);
                    $pkStatus = !empty($platformKeyRow['is_active']) ? 'active' : (!empty($platformKeyRow['is_revoked']) ? 'revoked' : 'expired');
                    $pkPrefix = (string) ($platformKeyRow['key_prefix'] ?? '');
                  ?>
                  <tr>
                    <td class="mono"><?= $pkId; ?></td>
                    <td><?= clickfix_h((string) ($platformKeyRow['label'] ?? '')); ?></td>
                    <td class="mono"><?= clickfix_h($pkPrefix !== '' ? ($pkPrefix . '***') : '-'); ?></td>
                    <td class="mono"><?= clickfix_h((string) ($platformKeyRow['scopes'] ?? '')); ?></td>
                    <td class="mono"><?= (int) ($platformKeyRow['max_rpm'] ?? 120); ?></td>
                    <td class="mono"><?= clickfix_h((string) ($platformKeyRow['last_used_at'] ?? '')); ?></td>
                    <td class="mono"><?= clickfix_h((string) ($platformKeyRow['expires_at'] ?? '')); ?></td>
                    <td class="mono"><?= clickfix_h($pkStatus); ?></td>
                    <td class="mono">
                      <?php if (!empty($platformKeyRow['is_active'])): ?>
                        <form method="post" onsubmit="return confirm('Revocar esta API key?');">
                          <input type="hidden" name="action" value="platform_api_key_revoke">
                          <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                          <input type="hidden" name="return_page" value="settings">
                          <input type="hidden" name="graph_id" value="0">
                          <input type="hidden" name="platform_api_key_id" value="<?= $pkId; ?>">
                          <button type="submit" class="btn btn-outline-light btn-sm">Revocar</button>
                        </form>
                      <?php else: ?>
                        <span class="mut">-</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr><td colspan="9" class="mut">No hay API keys de plataforma creadas.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
      </section>
      <section class="card settings-api-card">
        <div class="card-body">
          <h2>Consulta IOC (con tus APIs)</h2>
        <form method="post">
          <input type="hidden" name="action" value="investigation_api_lookup">
          <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
          <input type="hidden" name="return_page" value="settings">
          <input type="hidden" name="graph_id" value="0">
          <div class="intel-grid">
            <div>
              <label>Proveedor</label>
              <select name="provider" class="form-select">
                <?php foreach ($intelUserApiKeys as $apiRow): ?>
                  <?php
                    $providerKey = (string) ($apiRow['provider'] ?? '');
                    $providerLabel = (string) ($apiRow['label'] ?? $providerKey);
                  ?>
                  <option value="<?= clickfix_h($providerKey); ?>"<?= $settingsLookupProviderDefault === $providerKey ? ' selected' : ''; ?>><?= clickfix_h($providerLabel); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div>
              <label>Indicador (dominio, IP o URL)</label>
              <input class="form-control" type="text" name="lookup_target" maxlength="500" value="<?= clickfix_h($settingsLookupTargetDefault); ?>" placeholder="example.com | 1.2.3.4 | https://example.com/path">
            </div>
          </div>
          <div class="intel-toolbar">
            <button class="btn btn-primary btn-sm" type="submit">Consultar</button>
          </div>
        </form>
        <?php if (is_array($intelApiLookupResult)): ?>
          <div class="api-result">
            <b>?Â¡ltimo resultado</b>
            <div class="mono">provider: <?= clickfix_h((string) ($intelApiLookupResult['provider'] ?? '-')); ?> | status: <?= (int) ($intelApiLookupResult['status'] ?? 0); ?> | target: <?= clickfix_h((string) ($intelApiLookupResult['target'] ?? '')); ?> | at: <?= clickfix_h((string) ($intelApiLookupResult['captured_at'] ?? '')); ?></div>
            <?php if (!empty($intelApiLookupResult['error'])): ?>
              <div class="mut">error: <?= clickfix_h((string) ($intelApiLookupResult['error'] ?? '')); ?></div>
            <?php endif; ?>
            <pre><?= clickfix_h((string) ($intelApiLookupResult['response_json'] ?? '')); ?></pre>
          </div>
        <?php endif; ?>
        <?php if (!empty($intelApiLookupHistory)): ?>
          <h3>Historial reciente</h3>
          <table class="table table-striped settings-table">
            <thead><tr><th>Fecha</th><th>Proveedor</th><th>Target</th><th>Status</th><th>OK</th></tr></thead>
            <tbody>
              <?php foreach ($intelApiLookupHistory as $historyRow): ?>
                <tr>
                  <td class="mono"><?= clickfix_h((string) ($historyRow['created_at'] ?? '')); ?></td>
                  <td class="mono"><?= clickfix_h((string) ($historyRow['provider'] ?? '')); ?></td>
                  <td class="mono"><?= clickfix_h((string) ($historyRow['target'] ?? '')); ?></td>
                  <td class="mono"><?= (int) ($historyRow['status'] ?? 0); ?></td>
                  <td class="mono"><?= !empty($historyRow['ok']) ? 'yes' : 'no'; ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
        </div>
      </section>
      <?php else: ?>
      <section class="card settings-api-card">
        <div class="card-body">
          <h2>Integraciones de inteligencia</h2>
        <p class="mut">
          La gestion de credenciales e integraciones avanzadas esta oculta en esta interfaz.
          Los proveedores (por ejemplo VirusTotal, AbuseIPDB y URLScan) siguen operativos desde backend.
        </p>
        </div>
      </section>
      <?php endif; ?>
      <section class="card settings-api-card" id="settings-llm-profiles">
        <div class="card-body">
          <div class="settings-head">
            <div>
              <h2>LLM Profiles</h2>
              <p class="text-muted">Configure OpenAI, LM Studio, Anthropic or custom LLM providers for AI-powered investigation analysis. Supports custom Bearer tokens, User-Agent, and arbitrary headers.</p>
            </div>
            <span class="settings-pill">AI</span>
          </div>
          <div style="margin-top:14px">
            <?php
              $llmSettingsProfiles = [];
              if (clickfix_has_table($pdo, 'user_llm_profiles')) {
                  require_once __DIR__ . '/src/clickfix_llm.php';
                  clickfix_llm_ensure_table($pdo);
                  $llmSettingsProfiles = clickfix_llm_configured_providers($pdo, (int) ($user['id'] ?? 0));
              }
            ?>
            <h3>Saved Profiles</h3>
            <table class="table table-striped settings-table">
              <thead><tr><th>ID</th><th>Label</th><th>Provider</th><th>Base URL</th><th>Model</th><th>Active</th><th>Action</th></tr></thead>
              <tbody>
                <?php foreach ($llmSettingsProfiles as $lsp): ?>
                  <tr>
                    <td class="mono"><?= (int) ($lsp['id'] ?? 0); ?></td>
                    <td><?= clickfix_h((string) ($lsp['label'] ?? '')); ?></td>
                    <td><?= clickfix_h(clickfix_llm_provider_label((string) ($lsp['provider'] ?? 'custom'))); ?></td>
                    <td class="mono" style="max-width:200px;overflow:hidden;text-overflow:ellipsis"><?= clickfix_h((string) ($lsp['base_url'] ?? '')); ?></td>
                    <td class="mono"><?= clickfix_h((string) ($lsp['model'] ?? '-')); ?></td>
                    <td><?= !empty($lsp['is_active']) ? 'Yes' : 'No'; ?></td>
                    <td>
                      <?php if ((int) ($lsp['id'] ?? 0) > 0): ?>
                        <form method="post" onsubmit="return confirm('Delete this LLM profile?');">
                          <input type="hidden" name="action" value="llm_profile_delete">
                          <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                          <input type="hidden" name="profile_id" value="<?= (int) ($lsp['id'] ?? 0); ?>">
                          <button type="submit" class="btn btn-outline-light btn-sm">Delete</button>
                        </form>
                      <?php else: ?>
                        <span class="mut">env</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
                <?php if (empty($llmSettingsProfiles)): ?>
                  <tr><td colspan="7" class="mut">No LLM profiles configured. Add one below or set environment variables.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
            <h3 style="margin-top:18px">Add / Edit Profile</h3>
            <form method="post" style="margin-top:8px">
              <input type="hidden" name="action" value="llm_profile_save">
              <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
              <div class="intel-grid">
                <div><label>Label</label><input class="form-control" type="text" name="llm_label" maxlength="80" placeholder="My OpenAI Profile" required></div>
                <div><label>Provider</label><select name="llm_provider" class="form-select">
                  <option value="openai">OpenAI Compatible</option>
                  <option value="lmstudio">LM Studio</option>
                  <option value="anthropic">Anthropic Compatible</option>
                  <option value="custom">Custom Endpoint</option>
                </select></div>
                <div><label>Base URL</label><input class="form-control" type="url" name="llm_base_url" maxlength="400" placeholder="https://api.openai.com" required></div>
                <div><label>Model</label><input class="form-control" type="text" name="llm_model" maxlength="120" placeholder="gpt-4o"></div>
                <div><label>API Key</label><input class="form-control" type="password" name="llm_api_key" maxlength="400" placeholder="sk-..." autocomplete="off"></div>
                <div><label>Extra Headers (JSON)</label><input class="form-control" type="text" name="llm_extra_headers" maxlength="1000" placeholder='{"X-Custom":"value"}'></div>
                <div><label>Active</label><select name="llm_is_active" class="form-select"><option value="1">Yes</option><option value="0">No</option></select></div>
              </div>
              <div class="intel-toolbar" style="margin-top:10px">
                <button class="btn btn-primary btn-sm" type="submit">Save Profile</button>
              </div>
            </form>
          </div>
        </div>
      </section>
      <section class="card settings-api-card" id="settings-auto-inv">
        <div class="card-body">
          <div class="settings-head">
            <div>
              <h2>Auto-Investigation Engine</h2>
              <p class="text-muted">Automatic pipeline that scans pending alerts, creates investigations, runs correlation, and enriches with LLM.</p>
            </div>
            <span class="settings-pill">Automation</span>
          </div>
          <div style="margin-top:14px">
            <?php
              $autoInvEnabled = false;
              $autoInvMinScore = 60;
              $autoInvMaxDepth = 3;
              $autoInvLlmEnrich = false;
              $autoInvLlmProfileId = 0;
              $autoInvInterval = 15;
              if (clickfix_has_table($pdo, 'auto_investigation_settings')) {
                  require_once __DIR__ . '/src/clickfix_auto_investigation.php';
                  clickfix_llm_ensure_table($pdo);
                  $autoInvEnabled = clickfix_auto_investigation_is_enabled($pdo);
                  $autoInvMinScore = (int) clickfix_auto_investigation_setting($pdo, 'min_score', '60');
                  $autoInvMaxDepth = (int) clickfix_auto_investigation_setting($pdo, 'max_depth', '3');
                  $autoInvLlmEnrich = clickfix_auto_investigation_setting($pdo, 'llm_enrich', '0') === '1';
                  $autoInvLlmProfileId = (int) clickfix_auto_investigation_setting($pdo, 'llm_profile_id', '0');
                  $autoInvInterval = (int) clickfix_auto_investigation_setting($pdo, 'schedule_interval_minutes', '15');
              }
            ?>
            <form method="post">
              <input type="hidden" name="action" value="auto_investigation_settings_save">
              <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
              <div class="intel-grid">
                <div><label>Enabled</label><select name="auto_inv_enabled" class="form-select"><option value="1"<?= $autoInvEnabled ? ' selected' : ''; ?>>Yes</option><option value="0"<?= !$autoInvEnabled ? ' selected' : ''; ?>>No</option></select></div>
                <div><label>Min Alert Score</label><input class="form-control" type="number" name="auto_inv_min_score" min="0" max="100" value="<?= $autoInvMinScore; ?>"></div>
                <div><label>Max Correlation Depth</label><input class="form-control" type="number" name="auto_inv_max_depth" min="1" max="8" value="<?= $autoInvMaxDepth; ?>"></div>
                <div><label>LLM Enrichment</label><select name="auto_inv_llm_enrich" class="form-select"><option value="1"<?= $autoInvLlmEnrich ? ' selected' : ''; ?>>Yes</option><option value="0"<?= !$autoInvLlmEnrich ? ' selected' : ''; ?>>No</option></select></div>
                <div><label>LLM Profile</label><select name="auto_inv_llm_profile_id" class="form-select">
                  <option value="0">-- None --</option>
                  <?php foreach ($llmSettingsProfiles as $lsp): ?>
                    <option value="<?= (int) ($lsp['id'] ?? 0); ?>"<?= ($autoInvLlmProfileId === (int) ($lsp['id'] ?? 0)) ? ' selected' : ''; ?>><?= clickfix_h((string) ($lsp['label'] ?? 'Profile')); ?></option>
                  <?php endforeach; ?>
                </select></div>
                <div><label>Schedule Interval (min)</label><input class="form-control" type="number" name="auto_inv_schedule_interval" min="5" max="1440" value="<?= $autoInvInterval; ?>"></div>
              </div>
              <div class="intel-toolbar" style="margin-top:10px">
                <button class="btn btn-primary btn-sm" type="submit">Save Settings</button>
              </div>
            </form>
          </div>
        </div>
      </section>
    <?php endif; ?>

    <?php if ($page === 'clickfix_domain_list'): ?>
      <?php
        require_once __DIR__ . '/src/clickfix_domain_feeds.php';
        clickfix_domain_feeds_ensure_table($pdo);
        $domainListPage = max(1, (int) ($_GET['p'] ?? 1));
        $domainListPerPage = 100;
        $domainListSearch = trim((string) ($_GET['q'] ?? ''));
        $domainListSource = trim((string) ($_GET['source'] ?? ''));
        $blocklist = clickfix_load_list_file('blocklist');
        $allFeedEntries = clickfix_domain_feeds_get_entries($pdo, 5000, $domainListSource, $domainListSearch);
        $firstSeenCache = [];
        if (!empty($allFeedEntries)) {
            $lookupDomains = array_map(function($e) { return clickfix_normalize_domain((string) ($e['domain'] ?? '')); }, $allFeedEntries);
            $lookupDomains = array_unique(array_filter($lookupDomains));
            $lookupDomains = array_merge($lookupDomains, array_map(function($d) { return clickfix_normalize_domain((string) $d); }, $blocklist));
            $lookupDomains = array_unique(array_filter($lookupDomains));
            $placeholders = implode(',', array_fill(0, count($lookupDomains), '?'));
            if ($placeholders !== '') {
                $stmt = $pdo->prepare("SELECT LOWER(TRIM(hostname)) as d, MIN(received_at) as first_alert FROM reports WHERE LOWER(TRIM(hostname)) IN ({$placeholders}) GROUP BY LOWER(TRIM(hostname))");
                $stmt->execute(array_values($lookupDomains));
                while ($row = $stmt->fetch()) {
                    $firstSeenCache[strtolower(trim((string) ($row['d'] ?? '')))] = (string) ($row['first_alert'] ?? '');
                }
            }
        }
        $unifiedDomains = []; $seenDomains = [];
        $now = gmdate('c');
        foreach ($blocklist as $domain) {
            $d = clickfix_normalize_domain((string) $domain);
            if ($d === '' || isset($seenDomains[$d])) continue;
            if ($domainListSearch !== '' && stripos($d, $domainListSearch) === false) continue;
            $firstSeen = $firstSeenCache[strtolower($d)] ?? $firstSeenCache[$d] ?? $now;
            $seenDomains[$d] = true;
            $unifiedDomains[] = ['domain' => $d, 'sources' => ['Blocklist'], 'source_keys' => ['blocklist'], 'first_seen' => $firstSeen, 'threat' => '', 'details' => []];
        }
        foreach ($allFeedEntries as $entry) {
            $d = clickfix_normalize_domain((string) ($entry['domain'] ?? ''));
            if ($d === '' || $domainListSearch !== '' && stripos($d, $domainListSearch) === false) continue;
            $src = $entry['source_label'] ?? $entry['source_key'] ?? 'External';
            $firstSeen = $firstSeenCache[strtolower($d)] ?? $firstSeenCache[$d] ?? ($entry['first_seen'] !== '' ? $entry['first_seen'] : $now);
            $det = is_array($entry['details'] ?? null) ? $entry['details'] : [];
            $threat = (string) ($det['threat'] ?? $det['malware'] ?? $det['threat_type'] ?? '');
            $tags = is_array($det['tags'] ?? null) ? $det['tags'] : [];
            if (isset($seenDomains[$d])) {
                $existing = &$unifiedDomains[array_search($d, array_column($unifiedDomains, 'domain'), true)];
                if (!in_array($src, $existing['sources'])) { $existing['sources'][] = $src; $existing['source_keys'][] = $entry['source_key'] ?? ''; }
                if ($threat !== '' && $existing['threat'] === '') { $existing['threat'] = $threat; }
                if (!empty($tags) && empty($existing['details']['tags'])) { $existing['details'] = $det; }
            } else {
                $seenDomains[$d] = true;
                $unifiedDomains[] = ['domain' => $d, 'sources' => [$src], 'source_keys' => [$entry['source_key'] ?? ''], 'first_seen' => $firstSeen, 'threat' => $threat, 'details' => $det];
            }
        }
        usort($unifiedDomains, function($a, $b) { return strcmp($a['domain'], $b['domain']); });
        $domainListTotal = count($unifiedDomains);
        $domainListPages = max(1, (int) ceil($domainListTotal / $domainListPerPage));
        $domainListPage = min($domainListPage, $domainListPages);
        $domainListSlice = array_slice($unifiedDomains, ($domainListPage - 1) * $domainListPerPage, $domainListPerPage);
      ?>
      <div style="max-width:1200px;margin:0 auto;padding:20px 16px">
        <div class="intel-topbar" style="margin-bottom:18px">
          <div class="intel-topline"><div class="intel-title-wrap"><h1 style="font-size:1.4rem;margin:0">ClickFix Domain List</h1><p class="mut">Unified list of all known ClickFix-related domains from URLHaus, ThreatFox, GitHub, Carson, SOC Defenders and internal blocklists. <a href="<?= clickfix_h(cfurl('domain_feeds')); ?>" style="color:var(--brand)">Manage feeds &rarr;</a></p></div><div class="intel-chip-row"><span class="intel-chip ok"><?= $domainListTotal; ?> domains</span><span class="intel-chip">page <?= $domainListPage; ?>/<?= $domainListPages; ?></span></div></div>
        </div>
        <form method="get" style="display:flex;gap:8px;margin-bottom:14px;flex-wrap:wrap">
          <input type="hidden" name="page" value="clickfix_domain_list">
          <input type="search" name="q" value="<?= clickfix_h($domainListSearch); ?>" placeholder="Search domain..." style="padding:8px 14px;border:1px solid var(--line);border-radius:10px;background:var(--bg);color:var(--txt);max-width:280px">
          <select name="source" style="padding:8px 12px;border:1px solid var(--line);border-radius:10px;background:var(--bg);color:var(--txt)"><option value="">All Sources</option><option value="blocklist"<?= $domainListSource==='blocklist'?' selected':''; ?>>Blocklist</option><option value="github_gist"<?= $domainListSource==='github_gist'?' selected':''; ?>>GitHub Gist</option><option value="carson_list"<?= $domainListSource==='carson_list'?' selected':''; ?>>Carson</option><option value="socdefenders"<?= $domainListSource==='socdefenders'?' selected':''; ?>>SOC Defenders</option><option value="urlhaus"<?= str_starts_with($domainListSource,'urlhaus')?' selected':''; ?>>URLHaus</option><option value="threatfox"<?= str_starts_with($domainListSource,'threatfox')?' selected':''; ?>>ThreatFox</option></select>
          <button class="btn btn-primary btn-sm" type="submit">Filter</button>
          <?php if ($domainListSearch !== '' || $domainListSource !== ''): ?><a class="btn btn-sm" href="<?= clickfix_h(cfurl('clickfix_domain_list', true)); ?>">Clear</a><?php endif; ?>
        </form>
        <div class="analytics-table-wrap" style="max-height:70vh;overflow-y:auto">
          <table class="compact-table">
            <thead><tr><th>#</th><th>Domain</th><th>Sources</th><th>First Seen</th><th>Threat Info</th></tr></thead>
            <tbody>
              <?php $rowNum = ($domainListPage - 1) * $domainListPerPage; foreach ($domainListSlice as $row): $rowNum++; ?>
                <tr>
                  <td class="mono"><?= $rowNum; ?></td>
                  <td class="mono" style="word-break:break-all"><?= clickfix_h($row['domain']); ?></td>
                  <td style="font-size:.74rem">
                    <?php $srcs = $row['sources'] ?? [$row['source'] ?? 'Unknown']; foreach ($srcs as $i => $src): ?>
                      <span class="badge" style="margin:1px"><?= clickfix_h($src); ?></span>
                    <?php endforeach; ?>
                  </td>
                  <td class="mono"><?= clickfix_h((string) ($row['first_seen'] ?? '')); ?></td>
                  <td style="font-size:.74rem;max-width:260px">
                    <?= clickfix_h((string) ($row['threat'] ?? '')); ?>
                    <?php $det = is_array($row['details'] ?? null) ? $row['details'] : []; if (!empty($det['tags'])): ?><br><span class="mut"><?= clickfix_h(implode(', ', array_slice((array) $det['tags'], 0, 5))); ?></span><?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if (empty($domainListSlice)): ?><tr><td colspan="5" class="mut">No domains found. Fetch external feeds first.</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
        <?php if ($domainListPages > 1): ?>
        <div style="display:flex;gap:4px;justify-content:center;align-items:center;margin-top:18px;flex-wrap:wrap;font-size:.84rem">
          <?php if ($domainListPage > 1): ?><a class="btn btn-sm" href="<?= clickfix_h(cfurl('clickfix_domain_list', true, ['p' => $domainListPage - 1, 'q' => $domainListSearch, 'source' => $domainListSource])); ?>">&laquo; Prev</a><?php endif; ?>
          <?php
            $startP = max(1, $domainListPage - 2);
            $endP = min($domainListPages, $domainListPage + 2);
            if ($startP > 1) { echo '<a class="btn btn-sm" href="' . clickfix_h(cfurl('clickfix_domain_list', true, ['p' => 1, 'q' => $domainListSearch, 'source' => $domainListSource])) . '">1</a>'; if ($startP > 2) echo '<span class="mut">...</span>'; }
            for ($p = $startP; $p <= $endP; $p++):
          ?>
            <a class="btn btn-sm<?= $p === $domainListPage ? ' btn-primary' : ''; ?>" href="<?= clickfix_h(cfurl('clickfix_domain_list', true, ['p' => $p, 'q' => $domainListSearch, 'source' => $domainListSource])); ?>"><?= $p; ?></a>
          <?php endfor; ?>
          <?php if ($endP < $domainListPages): if ($endP < $domainListPages - 1) echo '<span class="mut">...</span>'; ?><a class="btn btn-sm" href="<?= clickfix_h(cfurl('clickfix_domain_list', true, ['p' => $domainListPages, 'q' => $domainListSearch, 'source' => $domainListSource])); ?>"><?= $domainListPages; ?></a><?php endif; ?>
          <?php if ($domainListPage < $domainListPages): ?><a class="btn btn-sm" href="<?= clickfix_h(cfurl('clickfix_domain_list', true, ['p' => $domainListPage + 1, 'q' => $domainListSearch, 'source' => $domainListSource])); ?>">Next &raquo;</a><?php endif; ?>
          <span class="mut" style="margin-left:8px"><?= $domainListTotal; ?> total</span>
        </div>
        <?php endif; ?>
      </div>
    <?php elseif ($page === 'investigation'): ?>
      <section class="intel-public">
        <?php if ($sharedGraph === null): ?>
          <article class="card"><h2>InvestigaciÃ³n no disponible</h2><p>El enlace compartido no existe o ha sido desactivado.</p></article>
        <?php else: ?>
          <?php
            $sharedGraphData = is_array($sharedGraph['graph'] ?? null) ? $sharedGraph['graph'] : ['nodes' => [], 'edges' => []];
            $sharedNodeCount = count(is_array($sharedGraphData['nodes'] ?? null) ? $sharedGraphData['nodes'] : []);
            $sharedEdgeCount = count(is_array($sharedGraphData['edges'] ?? null) ? $sharedGraphData['edges'] : []);
            $sharedInvestigationMitreSources = [];
            if (!empty($sharedGraph['title'])) {
                $sharedInvestigationMitreSources[] = (string) $sharedGraph['title'];
            }
            if (!empty($sharedGraph['site_domain'])) {
                $sharedInvestigationMitreSources[] = (string) $sharedGraph['site_domain'];
            }
            if (!empty($sharedGraph['summary'])) {
                $sharedInvestigationMitreSources[] = (string) $sharedGraph['summary'];
            }
            if (!empty($sharedGraph['notes'])) {
                $sharedInvestigationMitreSources[] = (string) $sharedGraph['notes'];
            }
            $sharedInvestigationMitreText = trim(implode("\n", array_filter($sharedInvestigationMitreSources)));
          ?>
          <div class="intel-public-grid">
            <div class="intel-public-main">
              <div class="intel-shell">
          <article class="intel-topbar">
            <div class="intel-topline">
              <div class="intel-title-wrap">
                <h2><?= clickfix_h((string) ($sharedGraph['title'] ?? 'InvestigaciÃ³n')); ?></h2>
                <p><?= clickfix_h((string) ($sharedGraph['summary'] ?? 'Sin resumen.')); ?></p>
              </div>
              <div class="intel-chip-row">
                <span class="intel-chip"><?= clickfix_h((string) ($sharedGraph['site_domain'] ?? '-')); ?></span>
                <span class="intel-chip warn"><?= clickfix_h((string) ($sharedGraph['verdict'] ?? 'unknown')); ?></span>
                <span class="intel-chip">public-share</span>
              </div>
            </div>
            <div class="intel-kpi-grid">
              <div class="intel-kpi"><b>Dominio</b><span class="mono"><?= clickfix_h((string) ($sharedGraph['site_domain'] ?? '-')); ?></span></div>
              <div class="intel-kpi"><b>Nodos</b><span><?= $sharedNodeCount; ?></span></div>
              <div class="intel-kpi"><b>Conexiones</b><span><?= $sharedEdgeCount; ?></span></div>
              <div class="intel-kpi"><b>Actualizado</b><span class="mono"><?= clickfix_h((string) ($sharedGraph['updated_at'] ?? '-')); ?></span></div>
            </div>
            <div class="mitre-blueprint" id="shared-mitre" data-mitre-source="<?= clickfix_h($sharedInvestigationMitreText); ?>">
              <h4>MITRE ATT&CK Blueprint (PÃºblico)</h4>
              <div class="mitre-blueprint-grid" id="shared-mitre-grid"></div>
              <div class="mitre-empty" id="shared-mitre-empty" hidden>Sin TTPs detectadas para esta investigaciÃ³n.</div>
            </div>
          </article>
          <article class="card">
            <h2>Grafo de investigaciÃ³n</h2>
            <div class="intel-map-shell">
              <div class="intel-map-toolbar">
                <div class="intel-map-toolbar-group">
                  <label for="shared-layout-mode">Layout</label>
                  <select id="shared-layout-mode">
                    <option value="force">Auto (gravity)</option>
                    <option value="tree-vertical">Arbol vertical</option>
                    <option value="tree-horizontal">Arbol horizontal</option>
                    <option value="cascade">Cascada</option>
                    <option value="radial">Radial</option>
                    <option value="grid">Grid</option>
                  </select>
                  <button type="button" class="btn" id="shared-layout-apply">Autoordenar</button>
                  <button type="button" class="btn" id="shared-fit-graph">Encajar</button>
                </div>
                <div class="intel-map-toolbar-group">
                  <span class="map-stat" id="shared-zoom-status">zoom 100%</span>
                  <button type="button" class="btn" id="shared-zoom-out">-</button>
                  <button type="button" class="btn" id="shared-zoom-reset">100%</button>
                  <button type="button" class="btn" id="shared-zoom-in">+</button>
                  <button type="button" class="btn" id="shared-fullscreen">Pantalla completa</button>
                </div>
              </div>
              <div class="intel-canvas-wrap" id="shared-canvas-wrap">
                <svg id="shared-svg"></svg>
                <div id="shared-node-layer" class="intel-node-layer"></div>
              </div>
            </div>
            <div class="intel-side" style="margin-top:10px">
              <div class="card-box" style="grid-column:1 / -1">
                <h3>Detalle del nodo seleccionado</h3>
                <div class="mut mono" id="shared-node-label">Sin nodo seleccionado.</div>
                <div class="mut mono" id="shared-node-tags"></div>
                <pre class="mono" id="shared-node-notes" style="margin-top:6px;max-height:170px;overflow:auto"></pre>
              </div>
            </div>
          </article>
              </div>
            </div>
            <aside class="intel-public-side">
              <div class="card intel-side-card">
                <div class="card-body">
                  <h3>Resumen operativo</h3>
                  <div class="intel-side-kv"><span>Dominio</span><b class="mono"><?= clickfix_h((string) ($sharedGraph['site_domain'] ?? '-')); ?></b></div>
                  <div class="intel-side-kv"><span>Veredicto</span><b><?= clickfix_h((string) ($sharedGraph['verdict'] ?? 'unknown')); ?></b></div>
                  <div class="intel-side-kv"><span>Actualizado</span><b class="mono"><?= clickfix_h((string) ($sharedGraph['updated_at'] ?? '-')); ?></b></div>
                  <div class="intel-side-kv"><span>Nodos</span><b><?= $sharedNodeCount; ?></b></div>
                  <div class="intel-side-kv"><span>Conexiones</span><b><?= $sharedEdgeCount; ?></b></div>
                </div>
              </div>
              <div class="card intel-side-card">
                <div class="card-body">
                  <h3>Acciones</h3>
                  <div class="intel-side-actions">
                    <button type="button" class="btn btn-primary btn-sm" id="shared-fit-graph-alt" onclick="var b=document.getElementById('shared-fit-graph'); if(b){b.click();}">Encajar grafo</button>
                    <button type="button" class="btn btn-outline-light btn-sm" id="shared-fullscreen-alt" onclick="var b=document.getElementById('shared-fullscreen'); if(b){b.click();}">Pantalla completa</button>
                    <?php if (!empty($sharedGraph['site_domain'])): ?>
                      <a class="btn btn-outline-light btn-sm" href="<?= clickfix_h(cfurl('search', false, ['q' => (string) $sharedGraph['site_domain']])); ?>">Buscar dominio</a>
                    <?php endif; ?>
                  </div>
                  <div class="mut" style="margin-top:8px">Usa los controles de layout para reorganizar el grafo y revisar dependencias.</div>
                </div>
              </div>
              <div class="card intel-side-card">
                <div class="card-body">
                  <h3>Contexto compartido</h3>
                  <div class="mut"><?= clickfix_h((string) ($sharedGraph['summary'] ?? 'Sin resumen disponible.')); ?></div>
                  <?php if (!empty($sharedGraph['notes'])): ?>
                    <div class="intel-side-note mono"><?= clickfix_h((string) $sharedGraph['notes']); ?></div>
                  <?php endif; ?>
                </div>
              </div>
            </aside>
          </div>
        <?php endif; ?>
      </section>
    <?php endif; ?>

    <?php if ($loggedIn && $page === 'intel'): ?>
      <?php
        $activeGraphId = (int) ($selectedInvestigation['id'] ?? 0);
        $activeGraphJson = json_encode(
            is_array($selectedInvestigation['graph'] ?? null) ? $selectedInvestigation['graph'] : ['nodes' => [], 'edges' => []],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        );
        if ($activeGraphJson === false) {
            $activeGraphJson = '{"nodes":[],"edges":[]}';
        }
        $shareUrl = '';
        if (!empty($selectedInvestigation['is_public']) && !empty($selectedInvestigation['share_token'])) {
            $shareUrl = 'dashboard.php?page=investigation&share=' . urlencode((string) $selectedInvestigation['share_token']);
        }
        $selectedInvestigationSourceReportId = (int) ($selectedInvestigation['source_report_id'] ?? 0);
      ?>
      <?php
        $activeGraphData = is_array($selectedInvestigation['graph'] ?? null) ? $selectedInvestigation['graph'] : ['nodes' => [], 'edges' => []];
        $activeNodeCount = count(is_array($activeGraphData['nodes'] ?? null) ? $activeGraphData['nodes'] : []);
        $activeEdgeCount = count(is_array($activeGraphData['edges'] ?? null) ? $activeGraphData['edges'] : []);
        $activeWorkflowStatus = clickfix_investigation_workflow_status((string) ($selectedInvestigation['workflow_status'] ?? 'draft'));
        $intelFocusLabel = $activeGraphId > 0 ? ('InvestigaciÃ³n #' . $activeGraphId) : 'Nueva investigaciÃ³n';
        $intelFocusDomain = trim((string) ($selectedInvestigation['site_domain'] ?? ''));
      ?>
      <section class="card intel-shell">
        <?php if (!$intelWorkspaceActive): ?>
          <div class="intel-selector-shell intel-selector-v2" data-intel-focus="1">
            <div class="intel-focus-hero">
              <div class="intel-focus-copy">
                <div class="intel-focus-eyebrow">Workspace de investigaciÃ³n</div>
                <h3>Selecciona el foco antes de investigar</h3>
                <p class="mut">Primero elige una investigaciÃ³n existente, una alerta para abrir un caso nuevo o un lienzo vacÃ­o. Hasta que no escojas foco no se cargan datos de otras investigaciones dentro del workspace.</p>
                <div class="intel-focus-steps">
                  <span class="intel-step active">1. Elegir foco</span>
                  <span class="intel-step">2. Enriquecer datos</span>
                  <span class="intel-step">3. Publicar o compartir</span>
                </div>
              </div>
              <div class="intel-focus-meta">
                <div class="intel-stat">
                  <div class="k">Investigaciones</div>
                  <div class="v"><?= count($investigations); ?></div>
                </div>
                <div class="intel-stat">
                  <div class="k">Alertas listas</div>
                  <div class="v"><?= count($intelSelectionReports); ?></div>
                </div>
                <label class="intel-focus-search">
                  <span class="mut">Buscar</span>
                  <input id="intel-focus-search" type="search" placeholder="Dominio, t?tulo, alerta, veredicto..." autocomplete="off">
                </label>
              </div>
            </div>
            <div class="intel-focus-tabs" role="tablist" aria-label="Seleccion de foco">
              <button type="button" class="intel-tab is-active" data-intel-tab="investigations" aria-selected="true">Continuar investigaciÃ³n</button>
              <button type="button" class="intel-tab" data-intel-tab="alerts" aria-selected="false">Crear desde alerta</button>
              <button type="button" class="intel-tab" data-intel-tab="new" aria-selected="false">Empezar desde cero</button>
            </div>
            <div class="intel-focus-panels">
              <section class="intel-focus-panel" data-intel-panel="investigations">
                <div class="intel-panel-head">
                  <div>
                    <h4>Continuar investigaciÃ³n</h4>
                    <p class="mut">Retoma un caso existente y entra directamente en su workspace aislado.</p>
                  </div>
                  <div class="intel-panel-chip">Mostrando <?= min(12, count($investigations)); ?> recientes</div>
                </div>
                <div class="intel-focus-list">
                  <?php if (!empty($investigations)): ?>
                    <?php foreach (array_slice($investigations, 0, 12) as $graphRow): ?>
                      <?php
                        $graphRowId = (int) ($graphRow['id'] ?? 0);
                        $graphTitle = (string) ($graphRow['title'] ?? 'InvestigaciÃ³n');
                        $graphDomain = (string) ($graphRow['site_domain'] ?? '-');
                        $graphVerdict = (string) ($graphRow['verdict'] ?? 'unknown');
                        $graphWorkflow = (string) ($graphRow['workflow_status'] ?? 'draft');
                        $graphSummary = (string) ($graphRow['summary'] ?? 'Sin resumen todavÃ­a.');
                        $graphSearch = strtolower(trim($graphTitle . ' ' . $graphDomain . ' ' . $graphVerdict . ' ' . $graphWorkflow . ' ' . $graphSummary));
                      ?>
                      <article class="intel-focus-card" data-search="<?= clickfix_h($graphSearch); ?>">
                        <div class="intel-focus-main">
                          <strong><?= clickfix_h($graphTitle); ?></strong>
                          <div class="intel-meta-row">
                            <span class="mono"><?= clickfix_h($graphDomain); ?></span>
                            <span class="intel-badge"><?= clickfix_h($graphVerdict); ?></span>
                            <span class="intel-badge soft"><?= clickfix_h(cfworkflowlabel($graphWorkflow, $lang)); ?></span>
                            <?php if (!empty($graphRow['submitted_to_community'])): ?>
                              <span class="intel-badge critical">community</span>
                            <?php endif; ?>
                          </div>
                        </div>
                        <div class="intel-focus-summary">
                          <div class="summary"><?= clickfix_h($graphSummary); ?></div>
                          <details class="intel-summary-details">
                            <summary>Ver resumen completo</summary>
                            <div class="mut"><?= clickfix_h($graphSummary); ?></div>
                          </details>
                        </div>
                        <div class="intel-focus-actions">
                          <a class="btn" href="<?= clickfix_h(cfurl('intel', false, ['graph_id' => $graphRowId])); ?>">Abrir workspace</a>
                        </div>
                      </article>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <div class="intel-empty-state">No tienes investigaciones guardadas todavÃ­a.</div>
                  <?php endif; ?>
                </div>
              </section>
              <section class="intel-focus-panel" data-intel-panel="alerts" hidden>
                <div class="intel-panel-head">
                  <div>
                    <h4>Crear desde alerta</h4>
                    <p class="mut">Convierte una alerta reciente en una investigaciÃ³n con contexto inicial y grafo base.</p>
                  </div>
                  <div class="intel-panel-chip">Alertas listas: <?= count($intelSelectionReports); ?></div>
                </div>
                <div class="intel-focus-list">
                  <?php if (!empty($intelSelectionReports)): ?>
                    <?php foreach ($intelSelectionReports as $reportRow): ?>
                      <?php
                        $reportId = (int) ($reportRow['id'] ?? 0);
                        $reportHost = trim((string) ($reportRow['hostname'] ?? ''));
                        $reportUrl = trim((string) ($reportRow['url'] ?? ''));
                        $reportPreview = trim((string) ($reportRow['message'] ?? ''));
                        $reportScore = (int) ($reportRow['score_total'] ?? 0);
                        $reportSearch = strtolower(trim($reportId . ' ' . $reportHost . ' ' . $reportUrl . ' ' . $reportPreview));
                      ?>
                      <article class="intel-focus-card" data-search="<?= clickfix_h($reportSearch); ?>">
                        <div class="intel-focus-main">
                          <strong>Alerta #<?= $reportId; ?></strong>
                          <div class="intel-meta-row">
                            <span class="mono"><?= clickfix_h($reportHost !== '' ? $reportHost : ($reportUrl !== '' ? $reportUrl : 'sin host')); ?></span>
                            <span class="intel-badge score">score <?= $reportScore; ?>/100</span>
                            <span class="intel-badge soft"><?= clickfix_h((string) ($reportRow['received_at'] ?? '')); ?></span>
                          </div>
                        </div>
                        <div class="intel-focus-summary">
                          <div class="summary"><?= clickfix_h($reportPreview !== '' ? $reportPreview : 'Sin mensaje resumido disponible.'); ?></div>
                          <?php if ($reportPreview !== ''): ?>
                            <details class="intel-summary-details">
                              <summary>Ver mensaje completo</summary>
                              <div class="mut"><?= clickfix_h($reportPreview); ?></div>
                            </details>
                          <?php endif; ?>
                        </div>
                        <div class="intel-focus-actions">
                          <form method="post">
                            <input type="hidden" name="action" value="report_quick_action">
                            <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                            <input type="hidden" name="return_page" value="intel">
                            <input type="hidden" name="report_id" value="<?= $reportId; ?>">
                            <input type="hidden" name="quick_mode" value="create_investigation">
                            <button class="btn btn-primary btn-sm" type="submit">Crear caso</button>
                          </form>
                        </div>
                      </article>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <div class="intel-empty-state">No hay alertas recientes pendientes listas para abrir un caso desde aqui.</div>
                  <?php endif; ?>
                </div>
              </section>
              <section class="intel-focus-panel" data-intel-panel="new" hidden>
                <div class="intel-panel-head">
                  <div>
                    <h4>Empezar desde cero</h4>
                    <p class="mut">Abre un lienzo vacÃ­o cuando quieras modelar una investigaciÃ³n sin depender de una alerta previa.</p>
                  </div>
                </div>
                <article class="intel-focus-card hero">
                  <div class="intel-focus-main">
                    <strong>InvestigaciÃ³n vacÃ­a</strong>
                    <div class="intel-meta-row">
                      <span class="intel-badge soft">workspace limpio</span>
                      <span class="intel-badge soft">sin timeline</span>
                      <span class="intel-badge soft">sin datos heredados</span>
                    </div>
                  </div>
                  <div class="intel-focus-summary">
                    <div class="summary">El editor se abre sin historial ni lookups cruzados para que construyas el caso desde cero.</div>
                  </div>
                  <div class="intel-focus-actions">
                    <a class="btn" href="<?= clickfix_h(cfurl('intel', false, ['compose' => 1])); ?>">Abrir lienzo</a>
                  </div>
                </article>
              </section>
            </div>
          </div>
        <?php else: ?>
          <div class="intel-workspace-grid">
            <div class="intel-workspace-main">
          <div class="intel-topbar">
            <div class="intel-topline">
              <div class="intel-title-wrap">
                <h2>Investigaciones de sitios</h2>
                <p class="mut">Workspace de an?lisis centrado en un unico caso, entidades, relaciones y evidencia trazable.</p>
              </div>
              <div class="intel-chip-row">
                <span class="intel-chip<?= $activeGraphId > 0 ? ' ok' : ''; ?>"><?= clickfix_h($intelFocusLabel); ?></span>
                <span class="intel-chip warn"><?= clickfix_h(cfworkflowlabel($activeWorkflowStatus, $lang)); ?></span>
                <?php if ($selectedInvestigationSourceReportId > 0): ?>
                  <span class="intel-chip">alerta origen #<?= $selectedInvestigationSourceReportId; ?></span>
                <?php endif; ?>
                <?php if (!empty($selectedInvestigation['submitted_to_community'])): ?>
                  <span class="intel-chip critical">community</span>
                <?php endif; ?>
              </div>
            </div>
            <div class="intel-kpi-grid">
              <div class="intel-kpi"><b>Dominio foco</b><span class="mono"><?= clickfix_h($intelFocusDomain !== '' ? $intelFocusDomain : '-'); ?></span></div>
              <div class="intel-kpi"><b>Nodos</b><span><?= $activeNodeCount; ?></span></div>
              <div class="intel-kpi"><b>Conexiones</b><span><?= $activeEdgeCount; ?></span></div>
              <div class="intel-kpi"><b>Eventos timeline</b><span><?= count($investigationEvents); ?></span></div>
            </div>
            <div class="intel-stage-bar">
              <div class="intel-stage<?= $activeWorkflowStatus === 'draft' ? ' active' : ''; ?>">draft</div>
              <div class="intel-stage<?= $activeWorkflowStatus === 'mid_verified' ? ' active' : ''; ?>">mid verified</div>
              <div class="intel-stage<?= $activeWorkflowStatus === 'sr_review' ? ' active' : ''; ?>">sr review</div>
              <div class="intel-stage<?= $activeWorkflowStatus === 'verified_public' ? ' active' : ''; ?>">verified public</div>
              <div class="intel-stage<?= in_array($activeWorkflowStatus, ['verified_internal', 'rejected'], true) ? ' active' : ''; ?>"><?= $activeWorkflowStatus === 'rejected' ? 'rejected' : 'verified internal'; ?></div>
            </div>
          </div>
          <div class="intel-focus-bar">
            <div class="intel-focus-main">
              <strong><?= clickfix_h((string) ($selectedInvestigation['title'] ?? 'Nueva investigacion')); ?></strong>
              <span><?= clickfix_h($intelFocusDomain !== '' ? $intelFocusDomain : 'Sin dominio principal definido'); ?><?php if ($activeGraphId > 0): ?> | grafo #<?= $activeGraphId; ?><?php endif; ?></span>
              <?php $activeVerdict = (string) ($selectedInvestigation['verdict'] ?? 'investigating'); $verdictIcons = ['confirmed_malicious' => '&#9762;','suspicious' => '&#9888;','investigating' => '&#128269;','false_positive' => '&#9989;','unknown' => '&#8265;']; ?>
              <span class="verdict-badge <?= clickfix_h($activeVerdict); ?>" style="margin-left:12px"><span class="verdict-icon"><?= $verdictIcons[$activeVerdict] ?? '&#8265;'; ?></span> <?= clickfix_h(strtoupper(str_replace('_', ' ', $activeVerdict))); ?></span>
            </div>
            <div class="intel-focus-actions">
              <a class="btn" href="<?= clickfix_h(cfurl('intel')); ?>">Cambiar foco</a>
              <button class="btn btn-primary btn-sm" type="submit" form="intel-save-form">Guardar</button>
              <button class="btn" type="button" id="intel-workspace-fullscreen">Pantalla completa</button>
              <?php if ($activeGraphId > 0): ?>
                <a class="btn" id="intel-export-json-link" href="<?= clickfix_h(cfurl('intel', false, ['graph_id' => $activeGraphId, 'export_iocs' => 'json'])); ?>">Export IOCs JSON</a>
              <?php endif; ?>
            </div>
          </div>
          <div class="intel-cockpit">
            <article class="intel-cockpit-card">
              <div class="k">Caso activo</div>
              <div class="v"><?= clickfix_h($intelFocusLabel); ?></div>
              <p class="mut"><?= clickfix_h((string) ($selectedInvestigation['title'] ?? 'Sin t?tulo')); ?></p>
            </article>
            <article class="intel-cockpit-card">
              <div class="k">Workflow</div>
              <div class="v"><?= clickfix_h(cfworkflowlabel($activeWorkflowStatus, $lang)); ?></div>
              <p class="mut">Veredicto: <?= clickfix_h((string) ($selectedInvestigation['verdict'] ?? 'unknown')); ?></p>
            </article>
            <article class="intel-cockpit-card">
              <div class="k">Enrichment</div>
              <div class="v"><?= count($intelApiLookupHistory); ?> consultas</div>
              <p class="mut"><?= count($investigationQuickTargets); ?> IOCs listos para pivote</p>
            </article>
            <article class="intel-cockpit-card">
              <div class="k">Grafo</div>
              <div class="v"><?= $activeNodeCount; ?> nodos / <?= $activeEdgeCount; ?> enlaces</div>
              <p class="mut"><?= count($investigationEvents); ?> eventos en timeline</p>
            </article>
            <article class="intel-cockpit-card">
              <div class="k">Origen</div>
              <div class="v"><?= $selectedInvestigationSourceReportId > 0 ? ('Alerta #' . $selectedInvestigationSourceReportId) : 'Manual'; ?></div>
              <p class="mut">Actualizado: <?= clickfix_h((string) ($selectedInvestigation['updated_at'] ?? '')); ?></p>
            </article>
            <article class="intel-cockpit-card actions">
              <div class="k">Acciones rÃ¡pidas</div>
              <div class="v">Cockpit operativo</div>
              <div class="intel-quick-grid">
                <button type="button" class="btn" data-scroll-target="intel-section-briefing">Briefing</button>
                <?php if ($selectedInvestigationSourceReportId > 0): ?>
                  <button type="button" class="btn" data-scroll-target="intel-section-source-alert">Alerta origen</button>
                <?php endif; ?>
                <button type="button" class="btn" data-scroll-target="intel-section-enrichment">Enrichment</button>
                <button type="button" class="btn" data-scroll-target="intel-section-graph">Grafo</button>
                <button type="button" class="btn" data-scroll-target="intel-section-actions">Acciones</button>
                <?php if ($activeGraphId > 0): ?>
                  <button type="button" class="btn" data-scroll-target="intel-section-timeline">Timeline</button>
                <?php endif; ?>
              </div>
            </article>
          </div>
          <div class="intel-workspace-nav" id="intel-workspace-nav">
            <button type="button" class="btn" data-scroll-target="intel-section-briefing">Briefing</button>
            <?php if ($selectedInvestigationSourceReportId > 0): ?>
              <button type="button" class="btn" data-scroll-target="intel-section-source-alert">Alerta origen</button>
            <?php endif; ?>
            <button type="button" class="btn" data-scroll-target="intel-section-enrichment">Enrichment</button>
            <button type="button" class="btn" data-scroll-target="intel-section-graph">Mapa relacional</button>
            <button type="button" class="btn" data-scroll-target="intel-section-entities">Entidades</button>
            <button type="button" class="btn" data-scroll-target="intel-section-actions">Distribucion</button>
            <?php if ($activeGraphId > 0): ?>
              <button type="button" class="btn" data-scroll-target="intel-section-auto-inv">Auto-Inv</button>
              <button type="button" class="btn" data-scroll-target="intel-section-timeline">Timeline</button>
            <?php endif; ?>
          </div>
          <div class="intel-layout workspace-only">
            <section class="intel-editor">
            <?php if ($selectedInvestigationSourceReportId > 0): ?>
              <?php
                $sourceReport = clickfix_report_by_id($pdo, $selectedInvestigationSourceReportId);
                $sourceEvent = null;
                if (is_array($sourceReport)) {
                    $sourceEventHost = strtolower(trim((string) ($sourceReport['hostname'] ?? '')));
                    $sourceEventIp = trim((string) ($sourceReport['ip'] ?? ''));
                    $sourceBlockedHistory = clickfix_report_blocked_history(
                        $pdo,
                        $sourceEventHost !== '' ? [$sourceEventHost] : [],
                        ($canSrViewer && $sourceEventIp !== '' && filter_var($sourceEventIp, FILTER_VALIDATE_IP)) ? [$sourceEventIp] : []
                    );
                    $sourceHostHistory = is_array($sourceBlockedHistory['hostnames'][$sourceEventHost] ?? null)
                        ? $sourceBlockedHistory['hostnames'][$sourceEventHost]
                        : ['total_count' => 0, 'blocked_count' => 0, 'last_blocked_at' => ''];
                    $sourceIpHistory = is_array($sourceBlockedHistory['ips'][$sourceEventIp] ?? null)
                        ? $sourceBlockedHistory['ips'][$sourceEventIp]
                        : ['total_count' => 0, 'blocked_count' => 0, 'last_blocked_at' => ''];
                    $sourceEvent = [
                        'id' => (int) ($sourceReport['id'] ?? 0),
                        'received_at' => (string) ($sourceReport['received_at'] ?? ''),
                        'hostname' => (string) ($sourceReport['hostname'] ?? ''),
                        'url' => (string) ($sourceReport['url'] ?? ''),
                        'previous_url' => (string) ($sourceReport['previous_url'] ?? ''),
                        'message' => (string) ($sourceReport['message'] ?? ''),
                        'detected_content' => (string) ($sourceReport['detected_content'] ?? ''),
                        'full_context' => (string) ($sourceReport['full_context'] ?? ''),
                        'score_total' => isset($sourceReport['score_total']) ? (int) $sourceReport['score_total'] : 0,
                        'review_status' => (string) ($sourceReport['review_status'] ?? 'pending'),
                        'blocked' => !empty($sourceReport['blocked']),
                        'duplicate_count' => (int) ($sourceReport['duplicate_count'] ?? 1),
                        'country' => (string) ($sourceReport['country'] ?? ''),
                        'event_type' => strtolower((string) ($sourceReport['event_type'] ?? 'clickfix_alert')),
                        'ip' => $canSrViewer ? $sourceEventIp : '',
                        'extension_version' => $canSrViewer ? clickfix_extract_extension_version((string) ($sourceReport['user_agent'] ?? '')) : '',
                        'host_blocked_before' => !empty($sourceHostHistory['blocked_count']),
                        'host_blocked_count' => (int) ($sourceHostHistory['blocked_count'] ?? 0),
                        'host_total_count' => (int) ($sourceHostHistory['total_count'] ?? 0),
                        'host_last_blocked_at' => (string) ($sourceHostHistory['last_blocked_at'] ?? ''),
                        'ip_blocked_before' => $canSrViewer && !empty($sourceIpHistory['blocked_count']),
                        'ip_blocked_count' => $canSrViewer ? (int) ($sourceIpHistory['blocked_count'] ?? 0) : 0,
                        'ip_total_count' => $canSrViewer ? (int) ($sourceIpHistory['total_count'] ?? 0) : 0,
                        'ip_last_blocked_at' => $canSrViewer ? (string) ($sourceIpHistory['last_blocked_at'] ?? '') : '',
                        'reason_list' => cfextractreasons($sourceReport),
                        'snippets' => cfextractsnippets($sourceReport),
                        'signals' => is_array($sourceReport['signals'] ?? null) ? $sourceReport['signals'] : [],
                    ];
                    if ($redactSensitiveForViewer) {
                        $sourceEvent['url'] = clickfix_dashboard_redact_sensitive($sourceEvent['url']);
                        $sourceEvent['previous_url'] = clickfix_dashboard_redact_sensitive($sourceEvent['previous_url']);
                        $sourceEvent['message'] = clickfix_dashboard_redact_sensitive($sourceEvent['message']);
                        $sourceEvent['detected_content'] = clickfix_dashboard_redact_sensitive($sourceEvent['detected_content']);
                        $sourceEvent['full_context'] = clickfix_dashboard_redact_sensitive($sourceEvent['full_context']);
                        $sourceEvent['snippets'] = array_map(static function ($snippet): string {
                            return clickfix_dashboard_redact_sensitive((string) $snippet);
                        }, $sourceEvent['snippets']);
                    }
                }
              ?>
              <div class="intel-editor-section" id="intel-section-source-alert" data-intel-section="intel-section-source-alert">
                <div class="intel-section-head">
                  <div>
                    <span class="intel-section-kicker">Alerta origen</span>
                    <h3>Detalle de la alerta usada para abrir este caso</h3>
                    <p class="mut">Mismo nivel de detalle que en Eventos recientes, pero anclado al caso activo.</p>
                  </div>
                </div>
                <?php if (!is_array($sourceEvent)): ?>
                  <p class="mut">No se encontrÃ³ la alerta origen o no hay permisos para verla.</p>
                <?php else: ?>
                  <?php
                    $sourceBadges = [
                        'score ' . (int) ($sourceEvent['score_total'] ?? 0) . '/100',
                        !empty($sourceEvent['blocked']) ? 'blocked' : 'alert-only',
                        (string) ($sourceEvent['review_status'] ?? 'pending'),
                        'x' . max(1, (int) ($sourceEvent['duplicate_count'] ?? 1)),
                        (string) ($sourceEvent['event_type'] ?? 'clickfix_alert'),
                    ];
                    if (!empty($sourceEvent['host_blocked_before'])) {
                        $sourceBadges[] = 'domain_blocked x' . (int) ($sourceEvent['host_blocked_count'] ?? 0);
                    }
                    if (!empty($sourceEvent['ip_blocked_before'])) {
                        $sourceBadges[] = 'ip_blocked x' . (int) ($sourceEvent['ip_blocked_count'] ?? 0);
                    }
                    $sourceMitreParts = array_filter([
                        (string) ($sourceEvent['message'] ?? ''),
                        (string) ($sourceEvent['detected_content'] ?? ''),
                        $canViewExactEventContext ? (string) ($sourceEvent['full_context'] ?? '') : '',
                        (string) ($sourceEvent['url'] ?? ''),
                        (string) ($sourceEvent['previous_url'] ?? ''),
                        implode("\n", is_array($sourceEvent['snippets'] ?? null) ? $sourceEvent['snippets'] : []),
                        implode("\n", is_array($sourceEvent['signals'] ?? null) ? $sourceEvent['signals'] : []),
                    ]);
                    $sourceMitreText = trim(implode("\n", $sourceMitreParts));
                    $sourceReasonList = is_array($sourceEvent['reason_list'] ?? null) && !empty($sourceEvent['reason_list'])
                        ? $sourceEvent['reason_list']
                        : [(string) ($sourceEvent['message'] ?? 'Sin motivo clasificado')];
                  ?>
                  <div class="event-detail-shell">
                    <div class="event-topline">
                      <h3 class="event-title"><?= clickfix_h((string) ($sourceEvent['hostname'] ?? '(sin dominio)')); ?></h3>
                      <div class="event-badges">
                        <?php foreach ($sourceBadges as $badgeLabel): ?>
                          <span class="event-chip"><?= clickfix_h($badgeLabel); ?></span>
                        <?php endforeach; ?>
                      </div>
                    </div>
                    <div class="event-grid">
                      <div class="event-kv"><b>Fecha</b><span><?= clickfix_h((string) ($sourceEvent['received_at'] ?? '')); ?></span></div>
                      <div class="event-kv"><b>Pais</b><span><?= clickfix_h((string) ($sourceEvent['country'] ?? '-')); ?></span></div>
                      <div class="event-kv"><b>URL</b><span><?= clickfix_h((string) ($sourceEvent['url'] ?? '-')); ?></span></div>
                      <div class="event-kv"><b>URL previa</b><span><?= clickfix_h((string) ($sourceEvent['previous_url'] ?? '-')); ?></span></div>
                      <div class="event-kv"><b>IP (manual report)</b><span><?= clickfix_h((string) ($sourceEvent['ip'] ?? '-')); ?></span></div>
                      <div class="event-kv"><b>ExtensiÃ³n (manual report)</b><span><?= clickfix_h((string) ($sourceEvent['extension_version'] ?? '-')); ?></span></div>
                      <div class="event-kv"><b>Dominio ya bloqueado</b><span>
                        <?= !empty($sourceEvent['host_blocked_before'])
                          ? ('SI (' . (int) ($sourceEvent['host_blocked_count'] ?? 0) . ' bloqueos / ' . (int) ($sourceEvent['host_total_count'] ?? 0) . ' reportes' . (!empty($sourceEvent['host_last_blocked_at']) ? ', Ãšltimo ' . (string) $sourceEvent['host_last_blocked_at'] : '') . ')')
                          : ('No (' . (int) ($sourceEvent['host_total_count'] ?? 0) . ' reportes)'); ?>
                      </span></div>
                      <?php if ($canSrViewer): ?>
                        <div class="event-kv"><b>IP ya bloqueada</b><span>
                          <?= !empty($sourceEvent['ip_blocked_before'])
                            ? ('SI (' . (int) ($sourceEvent['ip_blocked_count'] ?? 0) . ' bloqueos / ' . (int) ($sourceEvent['ip_total_count'] ?? 0) . ' reportes' . (!empty($sourceEvent['ip_last_blocked_at']) ? ', Ãšltimo ' . (string) $sourceEvent['ip_last_blocked_at'] : '') . ')')
                            : ('No (' . (int) ($sourceEvent['ip_total_count'] ?? 0) . ' reportes)'); ?>
                        </span></div>
                      <?php endif; ?>
                    </div>
                    <div class="event-columns">
                      <div>
                        <h3>Motivos detectados</h3>
                        <ul class="event-list">
                          <?php foreach ($sourceReasonList as $reason): ?>
                            <li><?= clickfix_h((string) $reason); ?></li>
                          <?php endforeach; ?>
                        </ul>
                      </div>
                      <div>
                        <h3>Snippets detectados</h3>
                        <?php if (!empty($sourceEvent['snippets'])): ?>
                          <?php foreach ($sourceEvent['snippets'] as $snippet): ?>
                            <div class="event-snippet"><strong><?= clickfix_h((string) $snippet); ?></strong></div>
                          <?php endforeach; ?>
                        <?php else: ?>
                          <div class="event-empty">Sin snippets almacenados.</div>
                        <?php endif; ?>
                      </div>
                    </div>
                    <div class="mitre-blueprint" id="source-alert-mitre" data-mitre-source="<?= clickfix_h($sourceMitreText); ?>">
                      <h4>MITRE ATT&CK Blueprint (alerta origen)</h4>
                      <div class="mitre-blueprint-grid" id="source-alert-mitre-grid"></div>
                      <div class="mitre-empty" id="source-alert-mitre-empty" hidden>Sin TTPs detectadas para esta alerta.</div>
                    </div>
                    <?php
                      $sourceContextText = $canViewExactEventContext
                          ? (string) ($sourceEvent['full_context'] ?? '')
                          : (string) ($sourceEvent['detected_content'] ?? '');
                      if ($sourceContextText === '') {
                          $sourceContextText = 'Sin contexto capturado.';
                      }
                    ?>
                    <div class="event-context<?= $canViewExactEventContext ? ' event-context-exact' : ''; ?>">
                      <h3><?= clickfix_h($canViewExactEventContext ? 'Contexto completo de pagina' : 'Contexto resaltado'); ?></h3>
                      <pre><?= clickfix_h($sourceContextText); ?></pre>
                    </div>
                  </div>
                <?php endif; ?>
              </div>
            <?php endif; ?>
            <div class="intel-editor-section" id="intel-section-briefing" data-intel-section="intel-section-briefing">
              <div class="intel-section-head">
                <div>
                  <span class="intel-section-kicker"><?= clickfix_h(cft('intel_briefing_kicker')); ?></span>
                  <h3><?= clickfix_h(cft('intel_briefing_title')); ?></h3>
                  <p class="mut"><?= clickfix_h(cft('intel_briefing_sub')); ?></p>
                </div>
                <div class="mut mono" id="intel-autosave-status"><?= clickfix_h(cft('intel_autosave_label')); ?> -</div>
              </div>
              <form id="intel-save-form" method="post">
                <input type="hidden" name="action" value="investigation_save">
                <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                <input type="hidden" name="graph_id" id="intel-graph-id" value="<?= $activeGraphId; ?>">
                <input type="hidden" name="graph_json" id="intel-graph-json" value="<?= clickfix_h($activeGraphJson); ?>">
                <div class="intel-grid">
                  <div><label><?= clickfix_h(cft('intel_briefing_title_label')); ?></label><input type="text" name="title" id="intel-title" maxlength="180" value="<?= clickfix_h((string) ($selectedInvestigation['title'] ?? '')); ?>" required></div>
                  <div><label><?= clickfix_h(cft('intel_briefing_domain_label')); ?></label><input type="text" name="site_domain" id="intel-domain" maxlength="180" value="<?= clickfix_h((string) ($selectedInvestigation['site_domain'] ?? '')); ?>" placeholder="<?= clickfix_h(cft('intel_briefing_domain_placeholder')); ?>"></div>
                  <div><label><?= clickfix_h(cft('intel_briefing_verdict_label')); ?></label><select name="verdict" id="intel-verdict">
                    <?php $v = (string) ($selectedInvestigation['verdict'] ?? 'suspicious'); ?>
                    <option value="investigating"<?= $v === 'investigating' ? ' selected' : ''; ?>>investigating</option>
                    <option value="malicious"<?= $v === 'malicious' ? ' selected' : ''; ?>>malicious</option>
                    <option value="suspicious"<?= $v === 'suspicious' ? ' selected' : ''; ?>>suspicious</option>
                    <option value="clean"<?= $v === 'clean' ? ' selected' : ''; ?>>clean</option>
                    <option value="unknown"<?= $v === 'unknown' ? ' selected' : ''; ?>>unknown</option>
                  </select></div>
                  <div><label><?= clickfix_h(cft('intel_briefing_tags_label')); ?></label><input type="text" name="tags" id="intel-tags" value="<?= clickfix_h(implode(', ', is_array($selectedInvestigation['tags'] ?? null) ? $selectedInvestigation['tags'] : [])); ?>" placeholder="<?= clickfix_h(cft('intel_briefing_tags_placeholder')); ?>"></div>
                  <div class="intel-grid-full"><label><?= clickfix_h(cft('intel_briefing_summary_label')); ?></label><textarea name="summary" id="intel-summary" maxlength="5000" placeholder="<?= clickfix_h(cft('intel_briefing_summary_placeholder')); ?>"><?= clickfix_h((string) ($selectedInvestigation['summary'] ?? '')); ?></textarea></div>
                </div>
                <div class="intel-toolbar">
                  <button class="btn btn-primary btn-sm" type="submit"><?= clickfix_h(cft('intel_briefing_save')); ?></button>
                </div>
              </form>
            </div>

            <div class="intel-editor-section" id="intel-section-enrichment" data-intel-section="intel-section-enrichment">
              <div class="intel-section-head">
                <div>
                  <span class="intel-section-kicker"><?= clickfix_h(cft('intel_enrichment_kicker')); ?></span>
                  <h3><?= clickfix_h(cft('intel_enrichment_title')); ?></h3>
                  <p class="mut"><?= $showApiUi ? clickfix_h(cft('intel_enrichment_sub_full')) : clickfix_h(cft('intel_enrichment_sub_lite')); ?></p>
                </div>
                <div class="intel-chip-row">
                  <span class="intel-chip">lookups: <?= count($intelApiLookupHistory); ?></span>
                  <span class="intel-chip">iocs: <?= count($investigationQuickTargets); ?></span>
                </div>
              </div>
              <div class="card-box" style="margin-bottom:10px">
              <?php if ($showApiUi): ?>
                <div class="mut" style="margin-bottom:10px">
                  <?= clickfix_h(cft('intel_api_keys_in_settings')); ?>
                  <a class="user-link" href="<?= clickfix_h(cfurl('settings')); ?>"><?= clickfix_h(cft('nav_settings')); ?></a>
                </div>
              <?php else: ?>
                <div class="mut" style="margin-bottom:10px">
                  <?= clickfix_h(cft('intel_platform_api_hidden')); ?>
                </div>
              <?php endif; ?>

                <?php
                  $workbenchArtifactCounts = is_array($intelWorkbenchResult['artifact_counts'] ?? null) ? $intelWorkbenchResult['artifact_counts'] : [];
                  $workbenchArtifacts = is_array($intelWorkbenchResult['artifacts'] ?? null) ? $intelWorkbenchResult['artifacts'] : [];
                  $workbenchDecoded = is_array($intelWorkbenchResult['decoded'] ?? null) ? $intelWorkbenchResult['decoded'] : [];
                  $workbenchBatchResults = is_array($intelWorkbenchResult['batch_results'] ?? null) ? $intelWorkbenchResult['batch_results'] : [];
                  $workbenchInputDefault = trim((string) ($intelWorkbenchResult['input'] ?? ''));
                  $workbenchDecodeChain = trim((string) ($intelWorkbenchResult['decode_chain'] ?? ''));
                  $workbenchDecodeSuggestions = is_array($intelWorkbenchResult['decode_suggestions'] ?? null) ? $intelWorkbenchResult['decode_suggestions'] : [];
                  if ($workbenchInputDefault === '') {
                      $workbenchInputDefault = trim(implode("\n", array_filter([
                          (string) ($selectedInvestigation['site_domain'] ?? ''),
                          (string) ($selectedInvestigation['summary'] ?? ''),
                      ])));
                  }
                ?>
                <div class="intel-workbench-panel">
                  <div class="intel-section-head" style="margin-bottom:0">
                    <div>
                      <span class="intel-section-kicker">IOC intake</span>
                      <h3>Intake, normalizacion y triage batch</h3>
                      <p class="mut">Pega texto libre, HTML, logs, URLs defang, hashes o comandos. El panel refang, extrae artefactos, intenta decodificar y puede lanzar pivotes batch sin exponer otra marca ni otra herramienta.</p>
                    </div>
                    <?php if (is_array($intelWorkbenchResult)): ?>
                      <div class="intel-chip-row">
                        <span class="intel-chip ok">capturado: <?= clickfix_h((string) ($intelWorkbenchResult['captured_at'] ?? '')); ?></span>
                        <span class="intel-chip">artefactos: <?= array_sum(array_map('intval', $workbenchArtifactCounts)); ?></span>
                        <span class="intel-chip warn">batch: <?= count($workbenchBatchResults); ?></span>
                      </div>
                    <?php endif; ?>
                  </div>
                  <form method="post">
                    <input type="hidden" name="action" value="investigation_ioc_workbench">
                    <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                    <input type="hidden" name="return_page" value="intel">
                    <input type="hidden" name="graph_id" value="<?= $activeGraphId; ?>">
                    <div class="intel-workbench-grid">
                      <div>
                        <label>Texto fuente / HTML / comandos / IOCs</label>
                        <textarea name="ioc_intake_text" maxlength="40000" placeholder="Pega aqui texto libre, HTML, logs, URLs hxxp://, dominios con [.] o artefactos ofuscados."><?= clickfix_h($workbenchInputDefault); ?></textarea>
                      </div>
                      <div class="intel-workbench-side">
                        <label>Proveedores para triage batch</label>
                        <div class="intel-workbench-provider-list">
                          <?php foreach ($intelUserApiKeys as $apiRow): ?>
                            <?php
                              $providerKey = (string) ($apiRow['provider'] ?? '');
                              $providerLabel = (string) ($apiRow['label'] ?? $providerKey);
                            ?>
                            <label>
                              <input type="checkbox" name="batch_providers[]" value="<?= clickfix_h($providerKey); ?>"<?= in_array($providerKey, ['virustotal', 'abuseipdb'], true) ? ' checked' : ''; ?>>
                              <span><?= clickfix_h($providerLabel); ?></span>
                            </label>
                          <?php endforeach; ?>
                        </div>
                        <details class="intel-workbench-decode">
                          <summary>Decodificacion avanzada</summary>
                          <label style="margin-top:8px">Cadena personalizada (opcional)</label>
                          <input
                            type="text"
                            name="decode_chain"
                            value="<?= clickfix_h($workbenchDecodeChain); ?>"
                            placeholder="base64->rot13 ; url->base64"
                          >
                          <p class="intel-workbench-note">Usa <span class="mono">-></span> para encadenar y <span class="mono">;</span> para separar cadenas. Ej: <span class="mono">base64->rot13</span></p>
                          <?php if (!empty($workbenchDecodeSuggestions)): ?>
                            <div class="intel-workbench-suggestions">
                              <span class="mut">Sugerencias detectadas:</span>
                              <div class="intel-chip-row">
                                <?php foreach ($workbenchDecodeSuggestions as $suggestion): ?>
                                  <span class="intel-chip"><?= clickfix_h((string) $suggestion); ?></span>
                                <?php endforeach; ?>
                              </div>
                            </div>
                          <?php endif; ?>
                        </details>
                        <p class="intel-workbench-note">El batch se limita a 15 IOCs reutilizables por ejecuci?n y solo usa proveedores compatibles con cada tipo. Los resultados se guardan tambi?n en tu historial de enrichment.</p>
                        <div class="intel-toolbar" style="margin-top:auto">
                          <button class="btn btn-primary btn-sm" type="submit">Procesar intake</button>
                        </div>
                      </div>
                    </div>
                  </form>

                  <?php if (is_array($intelWorkbenchResult)): ?>
                    <div class="intel-workbench-kpis">
                      <?php foreach (['url' => 'URLs', 'domain' => 'Dominios', 'ip' => 'IPs', 'sha256' => 'SHA256'] as $kpiType => $kpiLabel): ?>
                        <article class="intel-workbench-kpi">
                          <b><?= clickfix_h($kpiLabel); ?></b>
                          <span><?= (int) ($workbenchArtifactCounts[$kpiType] ?? 0); ?></span>
                        </article>
                      <?php endforeach; ?>
                    </div>

                    <?php if (trim((string) ($intelWorkbenchResult['refanged'] ?? '')) !== '' && trim((string) ($intelWorkbenchResult['refanged'] ?? '')) !== trim((string) ($intelWorkbenchResult['input'] ?? ''))): ?>
                      <div class="api-result">
                        <b>Texto normalizado / refang</b>
                        <pre><?= clickfix_h((string) ($intelWorkbenchResult['refanged'] ?? '')); ?></pre>
                      </div>
                    <?php endif; ?>

                    <div>
                      <h3>Artefactos extraidos</h3>
                      <?php if (empty($workbenchArtifacts)): ?>
                        <div class="mut">No se detectaron artefactos reutilizables en el intake actual.</div>
                      <?php else: ?>
                        <div class="intel-artifact-grid">
                          <?php foreach ($workbenchArtifacts as $artifactRow): ?>
                            <article class="intel-artifact-card">
                              <span class="intel-artifact-type"><?= clickfix_h((string) ($artifactRow['type'] ?? 'unknown')); ?></span>
                              <p class="intel-artifact-value"><?= clickfix_h((string) ($artifactRow['value'] ?? '')); ?></p>
                            </article>
                          <?php endforeach; ?>
                        </div>
                      <?php endif; ?>
                    </div>

                    <details class="intel-accordion">
                      <summary>Decodificaciones candidatas</summary>
                      <?php if (empty($workbenchDecoded)): ?>
                        <div class="mut">No aparecieron cadenas con una decodificacion suficientemente util en este intake.</div>
                      <?php else: ?>
                        <div class="intel-decode-grid" style="margin-top:10px">
                          <?php foreach ($workbenchDecoded as $decodeRow): ?>
                            <article class="intel-decode-card">
                              <div class="mono" style="font-size:12px;color:#d7ecff"><?= clickfix_h((string) ($decodeRow['input'] ?? '')); ?></div>
                              <?php foreach ((array) ($decodeRow['decoded'] ?? []) as $decodeType => $decodeValue): ?>
                                <div>
                                  <span class="intel-artifact-type"><?= clickfix_h((string) $decodeType); ?></span>
                                  <pre><?= clickfix_h(is_array($decodeValue) ? (json_encode($decodeValue, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?: '{}') : (string) $decodeValue); ?></pre>
                                </div>
                              <?php endforeach; ?>
                            </article>
                          <?php endforeach; ?>
                        </div>
                      <?php endif; ?>
                    </details>

                    <details class="intel-accordion" open>
                      <summary>Triage batch</summary>
                      <?php if (empty($workbenchBatchResults)): ?>
                        <div class="mut">No se ejecutaron consultas batch en esta pasada o no habia IOCs compatibles con los proveedores seleccionados.</div>
                      <?php else: ?>
                        <div class="intel-batch-grid" style="margin-top:10px">
                          <?php foreach ($workbenchBatchResults as $batchRow): ?>
                            <?php
                              $batchProvider = strtolower(trim((string) ($batchRow['provider'] ?? 'unknown')));
                              $batchSummary = is_array($batchRow['summary'] ?? null) ? $batchRow['summary'] : [];
                              $batchVtSummary = $batchProvider === 'virustotal' ? cfintel_virustotal_summary($batchSummary) : [];
                              $batchStatusOk = !empty($batchRow['ok']);
                            ?>
                            <article class="intel-batch-card">
                              <div class="intel-batch-head">
                                <div>
                                  <div class="mono" style="font-size:12px"><?= clickfix_h((string) ($batchRow['target'] ?? '')); ?></div>
                                  <div class="mut mono" style="font-size:11px"><?= clickfix_h((string) ($batchRow['target_type'] ?? 'unknown')); ?> | <?= clickfix_h($batchProvider); ?></div>
                                </div>
                                <span class="intel-batch-status<?= $batchStatusOk ? ' ok' : ' ko'; ?>">
                                  <?= $batchStatusOk ? 'ok' : 'error'; ?> | <?= (int) ($batchRow['status'] ?? 0); ?>
                                </span>
                              </div>
                              <?php if (!empty($batchVtSummary)): ?>
                                <div class="vt-stat-grid">
                                  <div class="vt-stat-chip vt-stat-malicious"><span>malicious</span><b><?= (int) ($batchVtSummary['malicious'] ?? 0); ?></b></div>
                                  <div class="vt-stat-chip vt-stat-suspicious"><span>suspicious</span><b><?= (int) ($batchVtSummary['suspicious'] ?? 0); ?></b></div>
                                  <div class="vt-stat-chip vt-stat-harmless"><span>harmless</span><b><?= (int) ($batchVtSummary['harmless'] ?? 0); ?></b></div>
                                  <div class="vt-stat-chip vt-stat-undetected"><span>undetected</span><b><?= (int) ($batchVtSummary['undetected'] ?? 0); ?></b></div>
                                </div>
                              <?php elseif (!empty($batchSummary)): ?>
                                <pre><?= clickfix_h(json_encode($batchSummary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?: '{}'); ?></pre>
                              <?php endif; ?>
                              <?php if (!$batchStatusOk && trim((string) ($batchRow['error'] ?? '')) !== ''): ?>
                                <div class="mut"><?= clickfix_h((string) ($batchRow['error'] ?? '')); ?></div>
                              <?php endif; ?>
                            </article>
                          <?php endforeach; ?>
                        </div>
                      <?php endif; ?>
                    </details>
                  <?php endif; ?>
                </div>

                <?php
                  $lookupProviderDefault = (string) ($intelApiLookupResult['provider'] ?? 'virustotal');
                  $lookupTargetDefault = trim((string) ($intelApiLookupResult['target'] ?? (string) ($selectedInvestigation['site_domain'] ?? '')));
                ?>
                <div style="margin-top:10px">
                  <h3><?= clickfix_h(cft('intel_iocs_title')); ?></h3>
                  <p class="mut"><?= clickfix_h(cft('intel_iocs_sub')); ?></p>
                  <?php if (clickfix_user_has_min_role($user, 'analyst_jr')): ?>
                    <div class="card-box" style="margin-bottom:14px">
                      <h4><?= clickfix_h(cft('intel_manual_ioc_title')); ?></h4>
                      <p class="mut"><?= clickfix_h(cft('intel_manual_ioc_sub')); ?></p>
                      <form method="post">
                        <input type="hidden" name="action" value="investigation_add_ioc">
                        <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                        <input type="hidden" name="graph_id" value="<?= $activeGraphId; ?>">
                        <div class="intel-grid">
                          <div>
                            <label><?= clickfix_h(cft('intel_manual_ioc_label')); ?></label>
                            <input type="text" name="manual_ioc" maxlength="600" placeholder="example.com, 1.2.3.4, https://example.com/path">
                          </div>
                          <div>
                            <label><?= clickfix_h(cft('intel_manual_ioc_type')); ?></label>
                            <select name="manual_ioc_type">
                              <option value="auto" selected><?= clickfix_h(cft('intel_manual_ioc_auto')); ?></option>
                              <option value="domain">domain</option>
                              <option value="ip">ip</option>
                              <option value="url">url</option>
                            </select>
                          </div>
                        </div>
                        <div class="intel-toolbar">
                          <button class="btn btn-primary btn-sm" type="submit"><?= clickfix_h(cft('intel_manual_ioc_button')); ?></button>
                        </div>
                      </form>
                    </div>
                  <?php endif; ?>
                  <?php if (empty($investigationQuickTargets)): ?>
                    <div class="mut" style="margin-bottom:12px"><?= clickfix_h(cft('intel_iocs_empty')); ?></div>
                  <?php else: ?>
                    <div style="display:grid;gap:8px;margin-bottom:14px">
                      <?php foreach ($investigationQuickTargets as $iocRow): ?>
                        <?php
                          $iocValue = (string) ($iocRow['value'] ?? '');
                          $iocType = (string) ($iocRow['type'] ?? 'unknown');
                          $iocSource = (string) ($iocRow['source'] ?? '');
                          $abuseAllowed = in_array($iocType, ['domain', 'ip'], true);
                        ?>
                        <div style="display:flex;justify-content:space-between;gap:10px;align-items:center;flex-wrap:wrap;padding:10px 12px;border:1px solid #284968;border-radius:12px;background:#0a1926">
                          <div style="min-width:240px;flex:1 1 320px">
                            <div class="mono" style="font-size:13px"><?= clickfix_h($iocValue); ?></div>
                            <div class="mut mono" style="font-size:11px"><?= clickfix_h($iocType); ?><?php if ($iocSource !== ''): ?> | origen: <?= clickfix_h($iocSource); ?><?php endif; ?></div>
                          </div>
                          <div style="display:flex;gap:8px;flex-wrap:wrap">
                            <form method="post" style="display:inline-flex;gap:6px;align-items:center">
                              <input type="hidden" name="action" value="investigation_api_lookup">
                              <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                              <input type="hidden" name="graph_id" value="<?= $activeGraphId; ?>">
                              <input type="hidden" name="provider" value="virustotal">
                              <input type="hidden" name="lookup_target" value="<?= clickfix_h($iocValue); ?>">
                              <button class="btn btn-primary btn-sm" type="submit">VT</button>
                            </form>
                            <?php if ($abuseAllowed): ?>
                              <form method="post" style="display:inline-flex;gap:6px;align-items:center">
                                <input type="hidden" name="action" value="investigation_api_lookup">
                                <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                                <input type="hidden" name="graph_id" value="<?= $activeGraphId; ?>">
                                <input type="hidden" name="provider" value="abuseipdb">
                                <input type="hidden" name="lookup_target" value="<?= clickfix_h($iocValue); ?>">
                                <button class="btn btn-primary btn-sm" type="submit">AbuseIPDB</button>
                              </form>
                            <?php endif; ?>
                            <?php if ($iocType === 'url' || $iocType === 'domain'): ?>
                              <form method="post" style="display:inline-flex;gap:6px;align-items:center">
                                <input type="hidden" name="action" value="investigation_api_lookup">
                                <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                                <input type="hidden" name="graph_id" value="<?= $activeGraphId; ?>">
                                <input type="hidden" name="provider" value="urlscan">
                                <input type="hidden" name="lookup_target" value="<?= clickfix_h($iocValue); ?>">
                                <button class="btn btn-primary btn-sm" type="submit">urlscan</button>
                              </form>
                            <?php endif; ?>
                          </div>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  <?php endif; ?>

                  <h3><?= $showApiUi ? clickfix_h(cft('intel_api_lookup_title')) : clickfix_h(cft('intel_lookup_fallback_title')); ?></h3>
                  <p class="mut"><?= $showApiUi ? clickfix_h(cft('intel_api_lookup_sub')) : clickfix_h(cft('intel_lookup_fallback_sub')); ?></p>
                  <form method="post">
                    <input type="hidden" name="action" value="investigation_api_lookup">
                    <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                    <input type="hidden" name="graph_id" value="<?= $activeGraphId; ?>">
                    <div class="intel-grid">
                      <div>
                        <label>Proveedor</label>
                        <select name="provider" class="form-select">
                          <?php foreach ($intelUserApiKeys as $apiRow): ?>
                            <?php
                              $providerKey = (string) ($apiRow['provider'] ?? '');
                              $providerLabel = (string) ($apiRow['label'] ?? $providerKey);
                            ?>
                            <option value="<?= clickfix_h($providerKey); ?>"<?= $lookupProviderDefault === $providerKey ? ' selected' : ''; ?>><?= clickfix_h($providerLabel); ?></option>
                          <?php endforeach; ?>
                        </select>
                      </div>
                      <div>
                        <label><?= clickfix_h(cft('intel_api_lookup_target')); ?></label>
                        <input class="form-control" type="text" name="lookup_target" maxlength="500" value="<?= clickfix_h($lookupTargetDefault); ?>" placeholder="example.com | 1.2.3.4 | https://example.com/path">
                      </div>
                    </div>
                    <div class="intel-toolbar">
                      <button class="btn btn-primary btn-sm" type="submit"><?= clickfix_h(cft('intel_api_lookup_button')); ?></button>
                    </div>
                  </form>
                </div>

                <?php if (is_array($intelApiLookupResult)): ?>
                  <?php
                    $lookupProvider = strtolower(trim((string) ($intelApiLookupResult['provider'] ?? '')));
                    $lookupTarget = (string) ($intelApiLookupResult['target'] ?? '');
                    $lookupMeta = cfintel_target_meta($lookupTarget);
                    $lookupSummary = is_array($intelApiLookupResult['summary'] ?? null) ? $intelApiLookupResult['summary'] : [];
                    $vtSummary = $lookupProvider === 'virustotal' ? cfintel_virustotal_summary($lookupSummary) : [];
                    $vtGuiUrl = $lookupProvider === 'virustotal' ? cfintel_virustotal_gui_url($lookupTarget) : '';
                    $vtTotal = !empty($vtSummary) ? array_sum(array_map('intval', $vtSummary)) : 0;
                  ?>
                  <div class="api-result">
                    <b><?= $showApiUi ? clickfix_h(cft('intel_api_lookup_result')) : 'Resultado de consulta'; ?></b>
                    <?php if ($showApiUi): ?>
                      <div class="mono">provider: <?= clickfix_h((string) ($intelApiLookupResult['provider'] ?? '-')); ?> | status: <?= (int) ($intelApiLookupResult['status'] ?? 0); ?> | target: <?= clickfix_h((string) ($intelApiLookupResult['target'] ?? '')); ?> | at: <?= clickfix_h((string) ($intelApiLookupResult['captured_at'] ?? '')); ?></div>
                    <?php endif; ?>
                    <?php if (!empty($vtSummary)): ?>
                      <div class="vt-summary">
                        <div class="vt-summary-head">
                          <span>
                            indicador: <b><?= clickfix_h((string) ($lookupMeta['type'] ?? 'unknown')); ?></b>
                            <?php if (!empty($lookupMeta['domain'])): ?>
                              | dominio: <b><?= clickfix_h((string) $lookupMeta['domain']); ?></b>
                            <?php elseif (!empty($lookupMeta['ip'])): ?>
                              | ip: <b><?= clickfix_h((string) $lookupMeta['ip']); ?></b>
                            <?php endif; ?>
                            | motores: <b><?= (int) $vtTotal; ?></b>
                          </span>
                          <?php if ($vtGuiUrl !== ''): ?>
                            <a class="user-link" href="<?= clickfix_h($vtGuiUrl); ?>" target="_blank" rel="noreferrer noopener">Abrir en VirusTotal</a>
                          <?php endif; ?>
                        </div>
                        <div class="vt-stat-grid">
                          <div class="vt-stat-chip vt-stat-malicious"><span>malicious</span><b><?= (int) ($vtSummary['malicious'] ?? 0); ?></b></div>
                          <div class="vt-stat-chip vt-stat-suspicious"><span>suspicious</span><b><?= (int) ($vtSummary['suspicious'] ?? 0); ?></b></div>
                          <div class="vt-stat-chip vt-stat-harmless"><span>harmless</span><b><?= (int) ($vtSummary['harmless'] ?? 0); ?></b></div>
                          <div class="vt-stat-chip vt-stat-undetected"><span>undetected</span><b><?= (int) ($vtSummary['undetected'] ?? 0); ?></b></div>
                        </div>
                      </div>
                    <?php endif; ?>
                    <?php if (!empty($intelApiLookupResult['error'])): ?>
                      <div class="mut" style="margin-top:6px"><?= clickfix_h((string) $intelApiLookupResult['error']); ?></div>
                    <?php endif; ?>
                    <?php if ($showApiUi && (!empty($lookupSummary) || trim((string) ($intelApiLookupResult['response_json'] ?? '')) !== '')): ?>
                      <details class="intel-accordion">
                        <summary>Raw JSON</summary>
                        <?php if (!empty($lookupSummary)): ?>
                          <div class="mut" style="margin-top:8px">summary</div>
                          <pre><?= clickfix_h(json_encode($lookupSummary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?: '{}'); ?></pre>
                        <?php endif; ?>
                        <?php if (trim((string) ($intelApiLookupResult['response_json'] ?? '')) !== ''): ?>
                          <div class="mut" style="margin-top:8px">response</div>
                          <pre><?= clickfix_h((string) ($intelApiLookupResult['response_json'] ?? '')); ?></pre>
                        <?php endif; ?>
                      </details>
                    <?php endif; ?>
                  </div>
                <?php endif; ?>

                <?php if ($showApiUi): ?>
                <details class="api-lookup-history" open>
                  <summary>Historial de consultas API (guardado automatico)</summary>
                  <?php if (!empty($intelApiLookupHistory)): ?>
                    <?php foreach ($intelApiLookupHistory as $historyRow): ?>
                      <?php
                        $historyProvider = strtolower(trim((string) ($historyRow['provider'] ?? '')));
                        $historyTarget = (string) ($historyRow['target'] ?? '');
                        $historyMeta = cfintel_target_meta($historyTarget);
                        $historySummary = is_array($historyRow['summary'] ?? null) ? $historyRow['summary'] : [];
                        $historyVtSummary = $historyProvider === 'virustotal' ? cfintel_virustotal_summary($historySummary) : [];
                        $historyVtGuiUrl = $historyProvider === 'virustotal' ? cfintel_virustotal_gui_url($historyTarget) : '';
                        $historyVtTotal = !empty($historyVtSummary) ? array_sum(array_map('intval', $historyVtSummary)) : 0;
                        $historySummaryPretty = '';
                        if (!empty($historySummary)) {
                            $historySummaryPretty = json_encode($historySummary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
                            if (!is_string($historySummaryPretty)) {
                                $historySummaryPretty = '';
                            }
                        }
                        $historyResponse = trim((string) ($historyRow['response_json'] ?? ''));
                        $historyStatusClass = !empty($historyRow['ok']) ? 'api-lookup-status-ok' : 'api-lookup-status-ko';
                      ?>
                      <details class="api-lookup-item">
                        <summary>
                          <div class="api-lookup-meta">#<?= (int) ($historyRow['id'] ?? 0); ?> | <?= clickfix_h((string) ($historyRow['provider'] ?? 'unknown')); ?> | <?= clickfix_h((string) ($historyRow['target'] ?? '')); ?></div>
                          <div class="api-lookup-meta">
                            <span class="<?= clickfix_h($historyStatusClass); ?>"><?= !empty($historyRow['ok']) ? 'ok' : 'error'; ?></span>
                            | status <?= (int) ($historyRow['status'] ?? 0); ?>
                            <?php if (!empty($historyRow['graph_id'])): ?>
                              | graph #<?= (int) ($historyRow['graph_id'] ?? 0); ?>
                            <?php endif; ?>
                            | <?= clickfix_h((string) ($historyRow['created_at'] ?? '')); ?>
                          </div>
                        </summary>
                        <div class="api-lookup-body">
                          <?php if (!empty($historyVtSummary)): ?>
                            <div class="vt-summary">
                              <div class="vt-summary-head">
                                <span>
                                  indicador: <b><?= clickfix_h((string) ($historyMeta['type'] ?? 'unknown')); ?></b>
                                  <?php if (!empty($historyMeta['domain'])): ?>
                                    | dominio: <b><?= clickfix_h((string) $historyMeta['domain']); ?></b>
                                  <?php elseif (!empty($historyMeta['ip'])): ?>
                                    | ip: <b><?= clickfix_h((string) $historyMeta['ip']); ?></b>
                                  <?php endif; ?>
                                  | motores: <b><?= (int) $historyVtTotal; ?></b>
                                </span>
                                <?php if ($historyVtGuiUrl !== ''): ?>
                                  <a class="user-link" href="<?= clickfix_h($historyVtGuiUrl); ?>" target="_blank" rel="noreferrer noopener">Abrir en VirusTotal</a>
                                <?php endif; ?>
                              </div>
                              <div class="vt-stat-grid">
                                <div class="vt-stat-chip vt-stat-malicious"><span>malicious</span><b><?= (int) ($historyVtSummary['malicious'] ?? 0); ?></b></div>
                                <div class="vt-stat-chip vt-stat-suspicious"><span>suspicious</span><b><?= (int) ($historyVtSummary['suspicious'] ?? 0); ?></b></div>
                                <div class="vt-stat-chip vt-stat-harmless"><span>harmless</span><b><?= (int) ($historyVtSummary['harmless'] ?? 0); ?></b></div>
                                <div class="vt-stat-chip vt-stat-undetected"><span>undetected</span><b><?= (int) ($historyVtSummary['undetected'] ?? 0); ?></b></div>
                              </div>
                            </div>
                          <?php endif; ?>
                          <?php if (!empty($historyRow['error'])): ?>
                            <div class="mut"><?= clickfix_h((string) $historyRow['error']); ?></div>
                          <?php endif; ?>
                          <?php if ($historySummaryPretty !== '' || $historyResponse !== ''): ?>
                            <details class="intel-accordion">
                              <summary>Raw JSON</summary>
                              <?php if ($historySummaryPretty !== ''): ?>
                                <div class="mut" style="margin-top:8px">summary</div>
                                <pre><?= clickfix_h($historySummaryPretty); ?></pre>
                              <?php endif; ?>
                              <?php if ($historyResponse !== ''): ?>
                                <div class="mut" style="margin-top:8px">response</div>
                                <pre><?= clickfix_h($historyResponse); ?></pre>
                              <?php endif; ?>
                            </details>
                          <?php endif; ?>
                        </div>
                      </details>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <div class="mut">Sin historial todavÃ­a para esta investigaciÃ³n.</div>
                  <?php endif; ?>
                </details>
                <?php endif; ?>

                <?php
                  $vtStats = is_array($vtReportedStats) ? $vtReportedStats : [];
                  $vtClassLabels = is_array($vtStats['class_labels'] ?? null) ? $vtStats['class_labels'] : [];
                  $vtClassValues = is_array($vtStats['class_values'] ?? null) ? $vtStats['class_values'] : [];
                  $vtEngineLabels = is_array($vtStats['engine_labels'] ?? null) ? $vtStats['engine_labels'] : [];
                  $vtEngineValues = is_array($vtStats['engine_values'] ?? null) ? $vtStats['engine_values'] : [];
                  $vtTopDomains = is_array($vtStats['top_domains'] ?? null) ? $vtStats['top_domains'] : [];
                ?>
                <div class="vt-reported-wrap">
                  <div style="display:flex;gap:8px;align-items:center;justify-content:space-between;flex-wrap:wrap">
                    <h4 style="margin:0">Resultados VT de webs reportadas</h4>
                    <button type="button" class="btn" id="vt-reported-generate">Generar gr?ficos VT</button>
                  </div>
                  <p class="mut" style="margin:0">Cruza webs reportadas en plataforma con el Ãšltimo lookup guardado de VirusTotal por dominio.</p>
                  <div id="vt-reported-panel" hidden>
                    <div class="vt-reported-kpis">
                      <div class="vt-reported-kpi"><div class="k">reportadas</div><div class="v"><?= (int) ($vtStats['reported_domains_total'] ?? 0); ?></div></div>
                      <div class="vt-reported-kpi"><div class="k">con VT</div><div class="v"><?= (int) ($vtStats['domains_with_vt'] ?? 0); ?></div></div>
                      <div class="vt-reported-kpi"><div class="k">sin VT</div><div class="v"><?= (int) ($vtStats['domains_without_vt'] ?? 0); ?></div></div>
                      <div class="vt-reported-kpi"><div class="k">detectadas</div><div class="v"><?= (int) ($vtStats['detected_any'] ?? 0); ?></div></div>
                    </div>
                    <div class="chart-stack">
                      <div class="chart-card">
                        <p class="chart-title">ClasificaciÃ³n por dominio reportado</p>
                        <canvas
                          id="vt-reported-class-chart"
                          class="chart-canvas"
                          data-labels='<?= clickfix_h(json_encode($vtClassLabels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?>'
                          data-counts='<?= clickfix_h(json_encode($vtClassValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?>'
                        ></canvas>
                        <div class="chart-legend">
                          <span><i class="dot" style="background:#63d9ff"></i>conteo por categoria</span>
                        </div>
                      </div>
                      <div class="chart-card">
                        <p class="chart-title">Suma de motores VT</p>
                        <canvas
                          id="vt-reported-engine-chart"
                          class="chart-canvas"
                          data-labels='<?= clickfix_h(json_encode($vtEngineLabels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?>'
                          data-counts='<?= clickfix_h(json_encode($vtEngineValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?>'
                        ></canvas>
                        <div class="chart-legend">
                          <span><i class="dot" style="background:#ffd166"></i>malicious/suspicious/harmless/undetected</span>
                        </div>
                      </div>
                    </div>
                    <div class="vt-reported-domain-table">
                      <table class="compact-table">
                        <thead><tr><th>Dominio</th><th>Veredicto VT</th><th>Mal</th><th>Sus</th><th>Har</th><th>Und</th><th>Motores</th><th>Ãšltimo lookup</th></tr></thead>
                        <tbody>
                          <?php foreach ($vtTopDomains as $vtRow): ?>
                            <?php $vtVerdict = (string) ($vtRow['verdict'] ?? 'harmless_or_undetected'); ?>
                            <tr>
                              <td class="mono"><?= clickfix_h((string) ($vtRow['domain'] ?? '')); ?></td>
                              <td><span class="vt-domain-badge <?= clickfix_h($vtVerdict); ?>"><?= clickfix_h($vtVerdict); ?></span></td>
                              <td class="mono"><?= (int) ($vtRow['malicious'] ?? 0); ?></td>
                              <td class="mono"><?= (int) ($vtRow['suspicious'] ?? 0); ?></td>
                              <td class="mono"><?= (int) ($vtRow['harmless'] ?? 0); ?></td>
                              <td class="mono"><?= (int) ($vtRow['undetected'] ?? 0); ?></td>
                              <td class="mono"><?= (int) ($vtRow['total'] ?? 0); ?></td>
                              <td class="mono"><?= clickfix_h((string) ($vtRow['last_lookup_at'] ?? '')); ?></td>
                            </tr>
                          <?php endforeach; ?>
                          <?php if (empty($vtTopDomains)): ?>
                            <tr><td colspan="8" class="mut">No hay lookups VT guardados para webs reportadas.</td></tr>
                          <?php endif; ?>
                        </tbody>
                      </table>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="intel-editor-section" id="intel-section-graph" data-intel-section="intel-section-graph">
              <div class="intel-section-head">
                <div>
                  <span class="intel-section-kicker">Graph</span>
                  <h3>Mapa relacional</h3>
                  <p class="mut">Visualiza la cadena de ataque, mueve entidades y modela el contexto operativo.</p>
                </div>
                <div class="intel-chip-row">
                  <span class="intel-chip">nodos: <?= $activeNodeCount; ?></span>
                  <span class="intel-chip">enlaces: <?= $activeEdgeCount; ?></span>
                </div>
              </div>
              <div class="intel-api-map-insights">
                <div class="intel-api-map-head">
                  <b>Resultados de proveedores en el mapa</b>
                  <span class="mono mut" id="intel-api-map-meta">Sin consultas recientes de proveedores para esta investigaciÃ³n.</span>
                </div>
                <div class="intel-api-keywords" id="intel-api-keywords">
                  <span class="mut">Sin keywords comunes detectadas.</span>
                </div>
              </div>
              <div class="intel-map-shell">
                <div class="intel-map-toolbar">
                  <div class="intel-map-toolbar-group">
                    <label for="intel-layout-mode">Layout</label>
                    <select id="intel-layout-mode">
                      <option value="force">Auto (gravity)</option>
                      <option value="tree-vertical">Arbol vertical</option>
                      <option value="tree-horizontal">Arbol horizontal</option>
                      <option value="cascade">Cascada</option>
                      <option value="radial">Radial</option>
                      <option value="grid">Grid</option>
                    </select>
                    <button type="button" class="btn" id="intel-layout-apply">Autoordenar</button>
                    <button type="button" class="btn" id="intel-fit-graph">Encajar</button>
                  </div>
                  <div class="intel-map-toolbar-group">
                    <span class="map-stat" id="intel-zoom-status">zoom 100%</span>
                    <button type="button" class="btn" id="intel-zoom-out">-</button>
                    <button type="button" class="btn" id="intel-zoom-reset">100%</button>
                    <button type="button" class="btn" id="intel-zoom-in">+</button>
                    <button type="button" class="btn" id="intel-fullscreen">Pantalla completa</button>
                  </div>
                </div>
                <div class="intel-canvas-wrap" id="intel-canvas-wrap">
                  <svg id="intel-svg"></svg>
                  <div id="intel-node-layer" class="intel-node-layer"></div>
                  <div class="intel-canvas-dock" id="intel-canvas-dock">
                    <div class="intel-canvas-dock-head">
                      <div class="intel-canvas-dock-title">
                        <b>Acciones del workspace</b>
                        <span>Siguen visibles tambi?n en pantalla completa.</span>
                      </div>
                      <span class="map-stat" id="intel-dock-zoom-status">zoom 100%</span>
                    </div>
                    <div class="intel-canvas-dock-actions">
                      <a class="btn" href="<?= clickfix_h(cfurl('intel')); ?>">Cambiar foco</a>
                      <button class="btn btn-primary btn-sm" type="submit" form="intel-save-form">Guardar</button>
                      <?php if ($activeGraphId > 0): ?>
                        <a class="btn" href="<?= clickfix_h(cfurl('intel', false, ['graph_id' => $activeGraphId, 'export_iocs' => 'json'])); ?>">Export JSON</a>
                      <?php endif; ?>
                    </div>
                    <div class="intel-canvas-dock-tools">
                      <button type="button" class="btn" id="intel-layout-cycle">Cambiar layout</button>
                      <button type="button" class="btn" id="intel-dock-fit">Encajar</button>
                      <button type="button" class="btn" id="intel-dock-zoom-out">-</button>
                      <button type="button" class="btn" id="intel-dock-zoom-reset">100%</button>
                      <button type="button" class="btn" id="intel-dock-zoom-in">+</button>
                      <button type="button" class="btn" id="intel-dock-fullscreen">Pantalla completa</button>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="intel-editor-section" id="intel-section-entities" data-intel-section="intel-section-entities">
              <div class="intel-section-head">
                <div>
                  <span class="intel-section-kicker">Entities</span>
                  <h3>Editor de entidades y relaciones</h3>
                  <p class="mut">Edita nodos, relaciones y notas sin salir del mismo caso.</p>
                </div>
              </div>
              <div class="intel-side">
                <div class="card-box">
                <h3>Nodos</h3>
                <input type="text" id="node-label" placeholder="Label del nodo">
                <input type="color" id="node-color" value="#5dc8ff">
                <input type="text" id="node-tags" placeholder="tags separadas por comas">
                <textarea id="node-notes" placeholder="Notas del nodo"></textarea>
                <button type="button" id="node-add">Anadir nodo</button>
                <button type="button" id="node-update">Actualizar nodo seleccionado</button>
                <button type="button" id="node-delete">Eliminar nodo seleccionado</button>
                <label for="node-list" class="mut" style="margin-top:8px;display:block">Lista de nodos</label>
                <select id="node-list" size="7"></select>
                <div class="mut mono" id="node-preview-label">Sin nodo seleccionado.</div>
                <div class="mut mono" id="node-preview-tags"></div>
                <pre class="mono" id="node-preview-notes" style="margin-top:6px;max-height:170px;overflow:auto"></pre>
              </div>
                <div class="card-box">
                  <h3>Conexiones</h3>
                  <select id="edge-from"></select>
                  <select id="edge-to"></select>
                  <input type="text" id="edge-label" placeholder="Relacion / evidencia">
                  <input type="color" id="edge-color" value="#94a3b8">
                  <button type="button" id="edge-add">Anadir conexion</button>
                  <select id="edge-list" size="6"></select>
                  <button type="button" id="edge-delete">Eliminar conexion seleccionada</button>
                </div>
              </div>
            </div>

            <?php if ($activeGraphId > 0): ?>
              <div class="intel-editor-section" id="intel-section-actions" data-intel-section="intel-section-actions">
                <div class="intel-section-head">
                  <div>
                    <span class="intel-section-kicker">Distribution</span>
                    <h3>Acciones, sharing y export</h3>
                    <p class="mut">Publica, comparte, exporta y promociona el caso sin salir del cockpit.</p>
                  </div>
                </div>
                <div class="intel-share">
                  <?php if ($shareUrl !== ''): ?>
                    <div><b>Enlace p?blico:</b> <a href="<?= clickfix_h($shareUrl); ?>" target="_blank" rel="noreferrer"><?= clickfix_h($shareUrl); ?></a></div>
                  <?php endif; ?>
                  <div class="intel-toolbar">
                    <form method="post" style="display:flex;gap:8px;align-items:center;width:auto;">
                      <input type="hidden" name="action" value="investigation_submit_community">
                      <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                      <input type="hidden" name="graph_id" value="<?= $activeGraphId; ?>">
                      <button class="btn btn-primary btn-sm" type="submit">
                        <?= !empty($selectedInvestigation['submitted_to_community']) ? 'Reenviar a Community' : 'Enviar a Community'; ?>
                      </button>
                    </form>
                    <form method="post" style="display:flex;gap:8px;align-items:center;width:auto;">
                      <input type="hidden" name="action" value="investigation_share">
                      <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                      <input type="hidden" name="graph_id" value="<?= $activeGraphId; ?>">
                      <input type="hidden" name="share_mode" value="on">
                      <button class="btn btn-primary btn-sm" type="submit">Compartir PÃºblico</button>
                    </form>
                    <form method="post" style="display:flex;gap:8px;align-items:center;width:auto;">
                      <input type="hidden" name="action" value="investigation_share">
                      <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                      <input type="hidden" name="graph_id" value="<?= $activeGraphId; ?>">
                      <input type="hidden" name="share_mode" value="off">
                      <button type="submit" class="btn btn-outline-light btn-sm">Quitar comparticion</button>
                    </form>
                    <form method="post" style="display:flex;gap:8px;align-items:center;width:auto;" onsubmit="return confirm('Eliminar investigaciÃ³n?');">
                      <input type="hidden" name="action" value="investigation_delete">
                      <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                      <input type="hidden" name="graph_id" value="<?= $activeGraphId; ?>">
                      <button type="submit" class="btn btn-outline-light btn-sm">Eliminar investigaciÃ³n</button>
                    </form>
                    <?php if ($canAdminViewer): ?>
                      <form method="post" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;width:auto;">
                        <input type="hidden" name="action" value="investigation_home_feature">
                        <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                        <input type="hidden" name="graph_id" value="<?= $activeGraphId; ?>">
                        <input type="hidden" name="show_on_home" value="0">
                        <label class="mono" style="display:flex;gap:6px;align-items:center">
                          <input type="checkbox" name="show_on_home" value="1"<?= !empty($selectedInvestigation['show_on_home']) ? ' checked' : ''; ?>>
                          Mostrar en Inicio
                        </label>
                        <input type="number" name="home_position" min="0" max="9999" value="<?= (int) ($selectedInvestigation['home_position'] ?? 0); ?>" placeholder="posici?n" style="width:96px">
                        <input type="number" name="source_report_id" min="0" value="<?= $selectedInvestigationSourceReportId; ?>" placeholder="report_id capturas" style="width:148px">
                        <button type="submit" class="btn btn-outline-light btn-sm">Guardar Inicio</button>
                      </form>
                    <?php endif; ?>
                  </div>
                  <div class="intel-toolbar" style="margin-top:8px">
                    <a class="btn" href="<?= clickfix_h(cfurl('intel', false, ['graph_id' => $activeGraphId, 'export_iocs' => 'txt'])); ?>">Export IOCs TXT</a>
                    <a class="btn" href="<?= clickfix_h(cfurl('intel', false, ['graph_id' => $activeGraphId, 'export_iocs' => 'csv'])); ?>">Export IOCs CSV</a>
                    <a class="btn" href="<?= clickfix_h(cfurl('intel', false, ['graph_id' => $activeGraphId, 'export_iocs' => 'json'])); ?>">Export IOCs JSON</a>
                    <a class="btn" href="<?= clickfix_h(cfurl('intel', false, ['graph_id' => $activeGraphId, 'export_iocs' => 'misp'])); ?>">Export MISP JSON</a>
                  </div>
                  <div class="mut mono" style="margin-top:8px">Exporta los IOCs deduplicados del caso actual en TXT, CSV, JSON o evento MISP listo para importar.</div>
                  <?php if ($canAdminViewer): ?>
                    <div class="mut mono" style="margin-top:8px">Inicio reutiliza las capturas aprobadas del `source_report_id`. Si la investigaciÃ³n no esta compartida en PÃºblico, solo se vera en el dashboard interno.</div>
                  <?php endif; ?>
                </div>
              </div>
            <?php endif; ?>

            <?php if ($activeGraphId > 0): ?>
              <div class="intel-editor-section" id="intel-section-auto-inv" data-intel-section="intel-section-auto-inv">
                <div class="intel-section-head">
                  <div>
                    <span class="intel-section-kicker">Automation</span>
                    <h3>Auto-Investigation Pipeline</h3>
                    <p class="mut">Motor automatico que escanea alertas pendientes, crea investigaciones, ejecuta correlacion y enriquece con LLM.</p>
                  </div>
                </div>
                <div class="auto-inv-panel" id="auto-inv-panel">
                  <div class="auto-inv-status-bar">
                    <div style="display:flex;align-items:center;gap:10px">
                      <span class="status-dot off" id="auto-inv-status-dot"></span>
                      <strong>Auto-Investigation Status</strong>
                    </div>
                    <div class="auto-inv-controls">
                      <button type="button" class="btn btn-primary btn-sm" id="auto-inv-toggle">Enable</button>
                      <button type="button" class="btn btn-sm" id="auto-inv-run">Run Now</button>
                      <button type="button" class="btn btn-sm" id="auto-inv-refresh">Refresh</button>
                    </div>
                  </div>
                  <div class="auto-inv-stats">
                    <div class="auto-inv-stat"><div class="k">Recent Jobs</div><div class="v" id="auto-inv-jobs-count"><?= count($autoInvJobs); ?></div></div>
                    <div class="auto-inv-stat"><div class="k">Min Score</div><div class="v"><?= (int) clickfix_auto_investigation_setting($pdo, 'min_score', '60'); ?>/100</div></div>
                    <div class="auto-inv-stat"><div class="k">Max Depth</div><div class="v"><?= (int) clickfix_auto_investigation_setting($pdo, 'max_depth', '3'); ?></div></div>
                    <div class="auto-inv-stat"><div class="k">LLM Enrich</div><div class="v"><?= clickfix_auto_investigation_setting($pdo, 'llm_enrich', '0') === '1' ? 'ON' : 'OFF'; ?></div></div>
                  </div>
                  <div class="auto-inv-jobs-list" id="auto-inv-jobs-list">
                    <?php foreach ($autoInvJobs as $job): ?>
                      <div class="auto-inv-job">
                        <span class="job-id">#<?= (int) ($job['id'] ?? 0); ?></span>
                        <span class="job-title"><?= clickfix_h((string) ($job['graph_title'] ?? $job['report_hostname'] ?? 'Job #' . (int) ($job['id'] ?? 0))); ?></span>
                        <span class="job-stage <?= clickfix_h((string) ($job['status'] ?? 'queued')); ?>"><?= clickfix_h((string) ($job['status'] ?? 'queued')); ?></span>
                        <?php if (!empty($job['report_score'])): ?>
                          <span class="job-score"><?= (int) ($job['report_score'] ?? 0); ?>/100</span>
                        <?php endif; ?>
                      </div>
                    <?php endforeach; ?>
                    <?php if (empty($autoInvJobs)): ?>
                      <div class="mut" style="padding:12px">No auto-investigation jobs yet. Enable the engine and click "Run Now".</div>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            <?php endif; ?>

            <?php if ($activeGraphId > 0): ?>
              <div class="intel-timeline" id="intel-section-timeline" data-intel-section="intel-section-timeline">
                <div class="intel-section-head">
                  <div>
                    <span class="intel-section-kicker">Timeline</span>
                    <h3>Timeline de investigaciÃ³n (que y cuando)</h3>
                    <p class="mut">Seguimiento cronologico de cambios, revisiÃ³nes y decisiones sobre este caso.</p>
                  </div>
                </div>
                <?php
                  $investigationMitreSources = [];
                  if (!empty($selectedInvestigation['title'])) {
                      $investigationMitreSources[] = (string) $selectedInvestigation['title'];
                  }
                  if (!empty($selectedInvestigation['site_domain'])) {
                      $investigationMitreSources[] = (string) $selectedInvestigation['site_domain'];
                  }
                  if (!empty($selectedInvestigation['summary'])) {
                      $investigationMitreSources[] = (string) $selectedInvestigation['summary'];
                  }
                  if (!empty($selectedInvestigation['notes'])) {
                      $investigationMitreSources[] = (string) $selectedInvestigation['notes'];
                  }
                  foreach ($investigationEvents as $event) {
                      if (!empty($event['action'])) {
                          $investigationMitreSources[] = (string) $event['action'];
                      }
                      if (!empty($event['details']) && is_array($event['details'])) {
                          $investigationMitreSources[] = json_encode($event['details'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
                      }
                  }
                  $investigationMitreText = trim(implode("\n", array_filter($investigationMitreSources)));
                ?>
                <div class="mitre-blueprint" id="investigation-mitre" data-mitre-source="<?= clickfix_h($investigationMitreText); ?>">
                  <h4>MITRE ATT&CK Blueprint (investigaciÃ³n)</h4>
                  <div class="mitre-blueprint-grid" id="investigation-mitre-grid"></div>
                  <div class="mitre-empty" id="investigation-mitre-empty" hidden>Sin TTPs detectadas para esta investigaciÃ³n.</div>
                </div>
                <div class="intel-timeline-list">
                  <?php foreach ($investigationEvents as $event): ?>
                    <?php
                      $details = is_array($event['details'] ?? null) ? $event['details'] : [];
                      $parts = [];
                      if (isset($details['verdict'])) {
                          $parts[] = 'verdict=' . (string) $details['verdict'];
                      }
                      if (isset($details['node_added']) || isset($details['node_removed']) || isset($details['node_updated'])) {
                          $parts[] = 'nodes +' . (int) ($details['node_added'] ?? 0) . ' / -' . (int) ($details['node_removed'] ?? 0) . ' / ~' . (int) ($details['node_updated'] ?? 0);
                      }
                      if (isset($details['edge_added']) || isset($details['edge_removed']) || isset($details['edge_updated'])) {
                          $parts[] = 'edges +' . (int) ($details['edge_added'] ?? 0) . ' / -' . (int) ($details['edge_removed'] ?? 0) . ' / ~' . (int) ($details['edge_updated'] ?? 0);
                      }
                      if (isset($details['graph_metrics']) && is_array($details['graph_metrics'])) {
                          $metricsLocal = $details['graph_metrics'];
                          $parts[] = 'graph n=' . (int) ($metricsLocal['nodes'] ?? 0) . ' e=' . (int) ($metricsLocal['edges'] ?? 0);
                      }
                      if (isset($details['after_metrics']) && is_array($details['after_metrics'])) {
                          $metricsAfter = $details['after_metrics'];
                          $parts[] = 'after n=' . (int) ($metricsAfter['nodes'] ?? 0) . ' e=' . (int) ($metricsAfter['edges'] ?? 0) . ' notes=' . (int) ($metricsAfter['nodes_with_notes'] ?? 0);
                      }
                      if (isset($details['share_token'])) {
                          $parts[] = 'share_token=' . substr((string) $details['share_token'], 0, 12) . '...';
                      }
                      if (isset($details['title'])) {
                          $parts[] = 'title=' . (string) $details['title'];
                      }
                      $detailText = implode(' | ', $parts);
                      if ($detailText === '') {
                          $detailText = '-';
                      }
                      $eventActor = (string) ($event['username'] ?? ('user#' . (int) ($event['user_id'] ?? 0)));
                    ?>
                    <article class="intel-event-card">
                      <div class="intel-event-head">
                        <span class="mono"><?= clickfix_h((string) ($event['created_at'] ?? '')); ?></span>
                        <span class="intel-event-action"><?= clickfix_h((string) ($event['action'] ?? 'update')); ?></span>
                        <span class="mono">
                          <?php if (!empty($event['user_id'])): ?>
                            <a class="user-link" href="<?= clickfix_h(cfprofileurl((int) ($event['user_id'] ?? 0))); ?>"><?= clickfix_h($eventActor); ?></a>
                          <?php else: ?>
                            <?= clickfix_h($eventActor); ?>
                          <?php endif; ?>
                        </span>
                      </div>
                      <div class="intel-event-detail"><?= clickfix_h($detailText); ?></div>
                    </article>
                  <?php endforeach; ?>
                  <?php if (empty($investigationEvents)): ?>
                    <article class="intel-event-card"><div class="intel-event-detail">Sin eventos de timeline aun.</div></article>
                  <?php endif; ?>
                </div>
              </div>
            <?php endif; ?>
          </section>
        </div>
            </div>
            <aside class="intel-workspace-side">
              <div class="card intel-side-card">
                <div class="card-body">
                  <h3>Estado del caso</h3>
                  <div class="intel-side-kv"><span>Workflow</span><b><?= clickfix_h(cfworkflowlabel($activeWorkflowStatus, $lang)); ?></b></div>
                  <div class="intel-side-kv"><span>Veredicto</span><b><?= clickfix_h((string) ($selectedInvestigation['verdict'] ?? 'unknown')); ?></b></div>
                  <div class="intel-side-kv"><span>Origen</span><b><?= $selectedInvestigationSourceReportId > 0 ? ('Alerta #' . $selectedInvestigationSourceReportId) : 'Manual'; ?></b></div>
                  <div class="intel-side-kv"><span>Actualizado</span><b class="mono"><?= clickfix_h((string) ($selectedInvestigation['updated_at'] ?? '')); ?></b></div>
                </div>
              </div>
              <div class="card intel-side-card">
                <div class="card-body">
                  <h3>M?tricas clave</h3>
                  <div class="intel-side-kpi-grid">
                    <div><span>Nodos</span><b><?= $activeNodeCount; ?></b></div>
                    <div><span>Conexiones</span><b><?= $activeEdgeCount; ?></b></div>
                    <div><span>Eventos</span><b><?= count($investigationEvents); ?></b></div>
                    <div><span>IOCs</span><b><?= count($investigationQuickTargets); ?></b></div>
                  </div>
                  <div class="mut" style="margin-top:8px">Monitorea la salud del caso y la cobertura de evidencias.</div>
                </div>
              </div>
              <div class="card intel-side-card">
                <div class="card-body">
                  <h3>Acciones rÃ¡pidas</h3>
                  <div class="intel-side-actions">
                    <button type="button" class="btn btn-primary btn-sm" data-scroll-target="intel-section-briefing">Ir a briefing</button>
                    <button type="button" class="btn btn-outline-light btn-sm" data-scroll-target="intel-section-graph">Ver grafo</button>
                    <button type="button" class="btn btn-outline-light btn-sm" data-scroll-target="intel-section-enrichment">Enrichment</button>
                    <?php if ($activeGraphId > 0): ?>
                      <a class="btn btn-outline-light btn-sm" href="<?= clickfix_h(cfurl('intel', false, ['graph_id' => $activeGraphId, 'export_iocs' => 'json'])); ?>">Export IOCs JSON</a>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
              <?php if (!empty($selectedInvestigation['summary'])): ?>
                <div class="card intel-side-card">
                  <div class="card-body">
                    <h3>Resumen</h3>
                    <div class="mut"><?= clickfix_h((string) $selectedInvestigation['summary']); ?></div>
                  </div>
                </div>
              <?php endif; ?>
              <div class="card intel-side-card">
                <div class="card-body">
                  <h3>Blog & Intel Feed</h3>
                  <div class="blog-feed-panel" id="blog-feed-panel">
                    <p class="mut" style="margin-top:0">Latest posts from inteltracker.jordiserrano.me and jordiserrano.me</p>
                    <div class="blog-feed-grid" id="blog-feed-grid" style="grid-template-columns:1fr;max-height:420px;overflow-y:auto">
                      <div class="mut">Loading feeds...</div>
                    </div>
                    <div class="blog-feed-crosslinks">
                      <h4>Related Blog Posts</h4>
                      <div id="blog-crosslinks-grid">
                        <div class="mut">Cross-linking investigations...</div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </aside>
          </div>
        <?php endif; ?>
      </section>
    <?php endif; ?>

    <?php if ($loggedIn && $page === 'community'): ?>
      <?php
        $communityActiveId = (int) ($selectedCommunityInvestigation['id'] ?? 0);
        $canMidReview = cfcan($user, 'analyst_mid');
        $canSeniorReview = cfcan($user, 'analyst_sr');
        $communityTotal = count($communityInvestigations);
        $communityMidVerified = 0;
        $communitySrReview = 0;
        $communityVerified = 0;
        $communitySelectedStatus = clickfix_investigation_workflow_status((string) ($selectedCommunityInvestigation['workflow_status'] ?? 'draft'));
        foreach ($communityInvestigations as $communityCounterRow) {
            $communityCounterStatus = clickfix_investigation_workflow_status((string) ($communityCounterRow['workflow_status'] ?? 'draft'));
            if ($communityCounterStatus === 'mid_verified') {
                $communityMidVerified++;
            } elseif ($communityCounterStatus === 'sr_review') {
                $communitySrReview++;
            } elseif (in_array($communityCounterStatus, ['verified_public', 'verified_internal'], true)) {
                $communityVerified++;
            }
        }
        $communityGraphData = is_array($selectedCommunityInvestigation['graph'] ?? null) ? $selectedCommunityInvestigation['graph'] : ['nodes' => [], 'edges' => []];
        $communityNodeCount = count(is_array($communityGraphData['nodes'] ?? null) ? $communityGraphData['nodes'] : []);
        $communityEdgeCount = count(is_array($communityGraphData['edges'] ?? null) ? $communityGraphData['edges'] : []);
        $communityShareUrl = (!empty($selectedCommunityInvestigation['is_public']) && !empty($selectedCommunityInvestigation['share_token']))
            ? ('dashboard.php?page=investigation&share=' . urlencode((string) $selectedCommunityInvestigation['share_token']))
            : '';
      ?>
      <section class="card intel-shell">
        <div class="community-grid">
          <div class="community-main">
            <div class="intel-topbar">
              <div class="intel-topline">
                <div class="intel-title-wrap">
                  <h2>Community Investigations</h2>
                  <p class="mut">Pipeline: [JR] envia a comunidad, [MID] valida y escala, [SR] verifica y decide [PUB]/[INT]. Todos los usuarios pueden votar [M+]/[L-].</p>
                </div>
                <div class="intel-chip-row">
                  <span class="intel-chip">queue: <?= $communityTotal; ?></span>
                  <span class="intel-chip warn">mid: <?= $communityMidVerified; ?></span>
                  <span class="intel-chip critical">sr: <?= $communitySrReview; ?></span>
                  <span class="intel-chip ok">verified: <?= $communityVerified; ?></span>
                </div>
              </div>
              <div class="intel-stage-bar">
                <div class="intel-stage<?= $communitySelectedStatus === 'draft' ? ' active' : ''; ?>">jr submit</div>
                <div class="intel-stage<?= $communitySelectedStatus === 'mid_verified' ? ' active' : ''; ?>">mid triage</div>
                <div class="intel-stage<?= $communitySelectedStatus === 'sr_review' ? ' active' : ''; ?>">sr review</div>
                <div class="intel-stage<?= $communitySelectedStatus === 'verified_public' ? ' active' : ''; ?>">verified public</div>
                <div class="intel-stage<?= in_array($communitySelectedStatus, ['verified_internal', 'rejected'], true) ? ' active' : ''; ?>"><?= $communitySelectedStatus === 'rejected' ? 'rejected' : 'verified internal'; ?></div>
              </div>
            </div>
            <div class="intel-layout">
          <aside class="intel-list">
            <div class="intel-list-head">
              <b>Community queue</b>
              <span>Votacion, verificaciÃ³n y trazabilidad por rol.</span>
            </div>
            <?php if (empty($communityInvestigations)): ?>
              <div class="intel-item active">
                <b>Sin investigaciones en Community</b>
                <div class="summary">EnvÃ­a una investigaciÃ³n desde el mÃ³dulo InvestigaciÃ³n.</div>
              </div>
            <?php endif; ?>
            <?php foreach ($communityInvestigations as $communityRow): ?>
              <?php
                $communityRowId = (int) ($communityRow['id'] ?? 0);
                $communityWorkflow = clickfix_investigation_workflow_status((string) ($communityRow['workflow_status'] ?? 'draft'));
                $communityClassification = (string) ($communityRow['malware_classification'] ?? 'neutral');
                $classificationSymbol = $communityClassification === 'malware'
                    ? 'M+'
                    : ($communityClassification === 'legit' ? 'L-' : 'N=');
              ?>
              <a class="intel-item<?= $communityRowId === $communityActiveId ? ' active' : ''; ?>" href="<?= clickfix_h(cfurl('community', false, ['graph_id' => $communityRowId])); ?>">
                <b><?= clickfix_h((string) ($communityRow['title'] ?? 'InvestigaciÃ³n')); ?></b>
                <div class="meta mono"><?= clickfix_h((string) ($communityRow['site_domain'] ?? '-')); ?></div>
                <div class="meta mono"><?= clickfix_h(cfworkflowlabel($communityWorkflow, $lang)); ?> | <?= clickfix_h(clickfix_role_label((string) ($communityRow['community_origin_role'] ?? 'analyst_jr'))); ?></div>
                <div class="meta mono"><?= $classificationSymbol; ?> | score <?= (int) ($communityRow['vote_score'] ?? 0); ?> | +<?= (int) ($communityRow['upvotes'] ?? 0); ?> / -<?= (int) ($communityRow['downvotes'] ?? 0); ?></div>
                <div class="meta mono">author REP: <?= (int) ($communityRow['author_reputation'] ?? 0); ?> | <a class="user-link" href="<?= clickfix_h(cfprofileurl((int) ($communityRow['user_id'] ?? 0))); ?>"><?= clickfix_h((string) ($communityRow['username'] ?? '')); ?></a></div>
              </a>
            <?php endforeach; ?>
          </aside>
          <section class="intel-editor">
            <?php if ($selectedCommunityInvestigation === null): ?>
              <div class="event-empty">Selecciona una investigaciÃ³n de la lista para revisar.</div>
            <?php else: ?>
              <?php
                $communityStatus = clickfix_investigation_workflow_status((string) ($selectedCommunityInvestigation['workflow_status'] ?? 'draft'));
                $communityClass = (string) ($selectedCommunityVote['classification'] ?? 'neutral');
                $classLabel = cfmalwarelabel($communityClass, $lang);
                $classSymbol = $communityClass === 'malware'
                    ? 'M+'
                    : ($communityClass === 'legit' ? 'L-'
                        : 'N=');
              ?>
              <div class="intel-editor-section">
                <div class="intel-grid">
                  <div><label>Título</label><div class="mono"><?= clickfix_h((string) ($selectedCommunityInvestigation['title'] ?? '')); ?></div></div>
                  <div><label>Dominio principal</label><div class="mono"><?= clickfix_h((string) ($selectedCommunityInvestigation['site_domain'] ?? '')); ?></div></div>
                  <div><label>Veredicto</label><div class="mono"><?= clickfix_h((string) ($selectedCommunityInvestigation['verdict'] ?? 'unknown')); ?></div></div>
                  <div><label>Estado workflow</label><div class="mono"><?= clickfix_h(cfworkflowlabel($communityStatus, $lang)); ?></div></div>
                  <div><label>Origen</label><div class="mono"><?= clickfix_h(clickfix_role_label((string) ($selectedCommunityInvestigation['community_origin_role'] ?? 'analyst_jr'))); ?></div></div>
                  <div><label>Autor</label><div class="mono"><a class="user-link" href="<?= clickfix_h(cfprofileurl((int) ($selectedCommunityInvestigation['user_id'] ?? 0))); ?>"><?= clickfix_h((string) ($selectedCommunityInvestigation['username'] ?? '')); ?></a> | REP <?= (int) ($selectedCommunityInvestigation['author_reputation'] ?? $selectedCommunityInvestigation['reputation'] ?? 0); ?></div></div>
                  <div><label>Malware scoring</label><div class="mono"><?= $classSymbol; ?> <?= clickfix_h($classLabel); ?> | score <?= (int) ($selectedCommunityVote['score'] ?? 0); ?> | +<?= (int) ($selectedCommunityVote['upvotes'] ?? 0); ?> / -<?= (int) ($selectedCommunityVote['downvotes'] ?? 0); ?></div></div>
                  <div><label>Actualizado</label><div class="mono"><?= clickfix_h((string) ($selectedCommunityInvestigation['updated_at'] ?? '')); ?></div></div>
                  <?php if (!empty($selectedCommunityInvestigation['is_public']) && !empty($selectedCommunityInvestigation['share_token'])): ?>
                    <div class="intel-grid-full"><label>Link pÃºblico</label><div class="mono"><a href="<?= clickfix_h(cfurl('investigation', true, ['share' => (string) $selectedCommunityInvestigation['share_token']])); ?>" target="_blank" rel="noreferrer">Abrir investigaciÃ³n pÃºblica</a></div></div>
                  <?php endif; ?>
                  <div class="intel-grid-full"><label>Resumen</label><pre class="mono"><?= clickfix_h((string) ($selectedCommunityInvestigation['summary'] ?? '')); ?></pre></div>
                </div>
                <div class="intel-toolbar">
                  <form method="post" style="display:flex;gap:8px;align-items:center;width:auto;">
                    <input type="hidden" name="action" value="investigation_vote">
                    <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                    <input type="hidden" name="graph_id" value="<?= $communityActiveId; ?>">
                    <input type="hidden" name="vote" value="1">
                    <button class="btn btn-primary btn-sm" type="submit">[M+] +1 Malware</button>
                  </form>
                  <form method="post" style="display:flex;gap:8px;align-items:center;width:auto;">
                    <input type="hidden" name="action" value="investigation_vote">
                    <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                    <input type="hidden" name="graph_id" value="<?= $communityActiveId; ?>">
                    <input type="hidden" name="vote" value="-1">
                    <button type="submit" class="btn btn-outline-light btn-sm">[L-] -1 Legit</button>
                  </form>
                  <a class="btn" href="<?= clickfix_h(cfurl('intel', false, ['graph_id' => $communityActiveId])); ?>">Abrir editor de investigaciÃ³n</a>
                </div>
              </div>
              <?php if ($canMidReview): ?>
                <div class="intel-editor-section">
                  <h3>RevisiÃ³n Mid</h3>
                  <div class="intel-toolbar">
                    <form method="post" style="display:flex;gap:8px;align-items:center;width:auto;">
                      <input type="hidden" name="action" value="investigation_workflow">
                      <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                      <input type="hidden" name="graph_id" value="<?= $communityActiveId; ?>">
                      <input type="hidden" name="workflow_status" value="mid_verified">
                      <input type="text" name="workflow_note" placeholder="nota de validacion mid (opcional)">
                      <button class="btn btn-primary btn-sm" type="submit">[MID] Validar</button>
                    </form>
                    <form method="post" style="display:flex;gap:8px;align-items:center;width:auto;">
                      <input type="hidden" name="action" value="investigation_workflow">
                      <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                      <input type="hidden" name="graph_id" value="<?= $communityActiveId; ?>">
                      <input type="hidden" name="workflow_status" value="sr_review">
                      <input type="text" name="workflow_note" placeholder="nota para senior (opcional)">
                      <button type="submit" class="btn btn-outline-light btn-sm">[MID->SR] Escalar</button>
                    </form>
                  </div>
                </div>
              <?php endif; ?>
              <?php if ($canSeniorReview): ?>
                <div class="intel-editor-section">
                  <h3>Decision Senior</h3>
                  <div class="intel-toolbar">
                    <form method="post" style="display:flex;gap:8px;align-items:center;width:auto;">
                      <input type="hidden" name="action" value="investigation_workflow">
                      <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                      <input type="hidden" name="graph_id" value="<?= $communityActiveId; ?>">
                      <input type="hidden" name="workflow_status" value="verified_public">
                      <input type="text" name="workflow_note" placeholder="nota de verificaciÃ³n pÃºblica (opcional)">
                      <button class="btn btn-primary btn-sm" type="submit">[SR][PUB] Verificar y pÃºblicar</button>
                    </form>
                    <form method="post" style="display:flex;gap:8px;align-items:center;width:auto;">
                      <input type="hidden" name="action" value="investigation_workflow">
                      <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                      <input type="hidden" name="graph_id" value="<?= $communityActiveId; ?>">
                      <input type="hidden" name="workflow_status" value="verified_internal">
                      <input type="text" name="workflow_note" placeholder="nota de verificaciÃ³n interna (opcional)">
                      <button type="submit" class="btn btn-outline-light btn-sm">[SR][INT] Verificar interno</button>
                    </form>
                    <form method="post" style="display:flex;gap:8px;align-items:center;width:auto;">
                      <input type="hidden" name="action" value="investigation_workflow">
                      <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                      <input type="hidden" name="graph_id" value="<?= $communityActiveId; ?>">
                      <input type="hidden" name="workflow_status" value="rejected">
                      <input type="text" name="workflow_note" placeholder="motivo de rechazo">
                      <button type="submit" class="btn btn-outline-light btn-sm">[SR][X] Rechazar</button>
                    </form>
                  </div>
                </div>
              <?php endif; ?>
            <?php endif; ?>
          </section>
            </div>
          </div>
          <aside class="community-side">
            <div class="card intel-side-card">
              <div class="card-body">
                <h3>Resumen de cola</h3>
                <div class="intel-side-kv"><span>Total</span><b><?= $communityTotal; ?></b></div>
                <div class="intel-side-kv"><span>Mid</span><b><?= $communityMidVerified; ?></b></div>
                <div class="intel-side-kv"><span>SR</span><b><?= $communitySrReview; ?></b></div>
                <div class="intel-side-kv"><span>Verificadas</span><b><?= $communityVerified; ?></b></div>
              </div>
            </div>
            <div class="card intel-side-card">
              <div class="card-body">
                <h3>Seleccion actual</h3>
                <div class="intel-side-kv"><span>Dominio</span><b class="mono"><?= clickfix_h((string) ($selectedCommunityInvestigation['site_domain'] ?? '-')); ?></b></div>
                <div class="intel-side-kv"><span>Veredicto</span><b><?= clickfix_h((string) ($selectedCommunityInvestigation['verdict'] ?? 'unknown')); ?></b></div>
                <div class="intel-side-kv"><span>Nodos</span><b><?= $communityNodeCount; ?></b></div>
                <div class="intel-side-kv"><span>Conexiones</span><b><?= $communityEdgeCount; ?></b></div>
                <div class="intel-side-kv"><span>Actualizado</span><b class="mono"><?= clickfix_h((string) ($selectedCommunityInvestigation['updated_at'] ?? '-')); ?></b></div>
              </div>
            </div>
            <div class="card intel-side-card">
              <div class="card-body">
                <h3>Acciones</h3>
                <div class="intel-side-actions">
                  <?php if ($communityActiveId > 0): ?>
                    <a class="btn btn-primary btn-sm" href="<?= clickfix_h(cfurl('intel', false, ['graph_id' => $communityActiveId])); ?>">Abrir en InvestigaciÃ³n</a>
                  <?php endif; ?>
                  <?php if ($communityShareUrl !== ''): ?>
                    <a class="btn btn-outline-light btn-sm" href="<?= clickfix_h($communityShareUrl); ?>" target="_blank" rel="noreferrer noopener">Abrir link p?blico</a>
                  <?php endif; ?>
                  <a class="btn btn-outline-light btn-sm" href="<?= clickfix_h(cfurl('community')); ?>">Refrescar cola</a>
                </div>
              </div>
            </div>
            <?php if (!empty($selectedCommunityInvestigation['summary'])): ?>
              <div class="card intel-side-card">
                <div class="card-body">
                  <h3>Resumen</h3>
                  <div class="mut"><?= clickfix_h((string) $selectedCommunityInvestigation['summary']); ?></div>
                </div>
              </div>
            <?php endif; ?>
          </aside>
        </div>
      </section>
    <?php endif; ?>

    <?php if ($page === 'ops' || $page === 'home' || $page === 'search'): ?>
      <section class="row">
        <article class="card" id="event-workbench">
          <div class="card-body">
            <div class="ops-header">
              <div>
                <h1 class="mb-1" style="font-size:1.3rem"><?= $page === 'search' ? 'Search Results' : 'Events'; ?></h1>
                <p class="text-muted mb-0">Triage workspace with event feed, detail panel, and quick actions.</p>
              </div>
              <div class="ops-badges">
                <span class="badge badge-outline-info">24h alerts <?= (int) ($metrics['alerts_24h'] ?? 0); ?></span>
                <span class="badge badge-outline-success">24h blocks <?= (int) ($metrics['blocks_24h'] ?? 0); ?></span>
                <span class="badge badge-outline-warning">pending <?= (int) ($metrics['pending_review_total'] ?? 0); ?></span>
              </div>
            </div>
            <?php if ($page === 'ops'): ?>
              <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin:10px 0 14px">
                <input type="search" id="ops-feed-search" placeholder="Filter by domain, snippet..." style="padding:8px 14px;border:1px solid var(--line);border-radius:10px;background:var(--bg);color:var(--txt);min-width:240px;font-size:.84rem">
                <div class="ops-sev-filter" id="ops-severity-filter" style="margin:0">
                  <button type="button" class="ops-sev-filter-btn is-active" data-ops-severity="all">All</button>
                  <button type="button" class="ops-sev-filter-btn" data-ops-severity="high">High (<?= $opsHigh; ?>)</button>
                  <button type="button" class="ops-sev-filter-btn" data-ops-severity="medium">Med (<?= $opsMed; ?>)</button>
                  <button type="button" class="ops-sev-filter-btn" data-ops-severity="low">Low (<?= $opsLow; ?>)</button>
                </div>
                <span class="mut" style="font-size:.78rem"><?= count($eventWorkbenchRows); ?> events in feed</span>
              </div>
              <?php
                $opsHigh = 0;
                $opsMed = 0;
                $opsLow = 0;
                foreach ($eventWorkbenchRows as $opsRow) {
                    $opsScore = isset($opsRow['score_total']) ? (int) $opsRow['score_total'] : 0;
                    if ($opsScore >= 70) {
                        $opsHigh++;
                    } elseif ($opsScore >= 40) {
                        $opsMed++;
                    } else {
                        $opsLow++;
                    }
                }
                $opsTopDomainGroups = array_slice($eventDomainGroups ?? [], 0, 6);
                $opsTopReasons = [];
                foreach ($eventWorkbenchRows as $opsRow) {
                    $reason = !empty($opsRow['reason_list']) ? (string) $opsRow['reason_list'][0] : (string) ($opsRow['message'] ?? '');
                    $reason = trim($reason);
                    if ($reason === '') {
                        continue;
                    }
                    if (!isset($opsTopReasons[$reason])) {
                        $opsTopReasons[$reason] = 0;
                    }
                    $opsTopReasons[$reason]++;
                }
                arsort($opsTopReasons);
                $opsTopReasons = array_slice($opsTopReasons, 0, 6, true);
              ?>
              <div class="ops-grid">
                <div class="ops-main">
                  <aside class="ops-side">
                    <div class="card ops-panel">
                      <div class="card-body">
                        <div class="ops-panel-head">
                          <h3>Resumen operativo</h3>
                          <span class="badge badge-outline-primary">Status</span>
                        </div>
                        <div class="ops-mini">
                          <div><span>Ãšltima actualizaciÃ³n</span><strong><?= clickfix_h((string) ($metrics['last_update'] ?? '')); ?></strong></div>
                          <div><span>PaÃ­ses activos</span><strong><?= is_array($metrics['countries'] ?? null) ? count($metrics['countries']) : 0; ?></strong></div>
                        </div>
                        <div class="ops-mini">
                          <div><span>Alertas 24h</span><strong><?= (int) ($metrics['alerts_24h'] ?? 0); ?></strong></div>
                          <div><span>Bloqueos 24h</span><strong><?= (int) ($metrics['blocks_24h'] ?? 0); ?></strong></div>
                        </div>
                      </div>
                    </div>
                    <div class="card ops-panel">
                      <div class="card-body">
                        <div class="ops-panel-head">
                          <h3>Radar operativo</h3>
                          <span class="badge badge-outline-info">Ops</span>
                        </div>
                        <div class="ops-kpi-grid">
                          <div class="ops-kpi"><span>alertas 24h</span><strong><?= (int) ($metrics['alerts_24h'] ?? 0); ?></strong></div>
                          <div class="ops-kpi"><span>bloqueos 24h</span><strong><?= (int) ($metrics['blocks_24h'] ?? 0); ?></strong></div>
                          <div class="ops-kpi"><span>dominios Ãºnicos</span><strong><?= (int) ($metrics['unique_hosts'] ?? 0); ?></strong></div>
                          <div class="ops-kpi"><span>pend. revisiÃ³n</span><strong><?= (int) ($metrics['pending_review_total'] ?? 0); ?></strong></div>
                        </div>
                        <div class="ops-mini">
                          <div><span>ratio bloqueo 24h</span><strong><?= number_format((float) ($metrics['block_rate'] ?? 0), 2); ?>%</strong></div>
                          <div><span>pend. fuera listas</span><strong><?= (int) ($metrics['pending_domains_outside_lists'] ?? 0); ?></strong></div>
                        </div>
                        <div class="ops-actions">
                          <a class="btn btn-outline-light btn-sm" href="<?= clickfix_h(cfurl('search')); ?>">Ir a bÃºsqueda</a>
                          <a class="btn btn-outline-light btn-sm" href="<?= clickfix_h(cfurl('coverage')); ?>">Ver cobertura</a>
                          <a class="btn btn-primary btn-sm" href="<?= clickfix_h(cfurl('intel')); ?>">Nueva investigaciÃ³n</a>
                        </div>
                      </div>
                    </div>
                    <div class="ops-side-grid">
                        <div class="card ops-panel">
                        <div class="card-body">
                          <div class="ops-panel-head">
                            <h3>Top Reasons</h3>
                            <span class="badge badge-outline-info">Signals</span>
                          </div>
                          <div class="ops-sev">
                            <div class="ops-sev-row high"><span>Alta</span><strong><?= $opsHigh; ?></strong></div>
                            <div class="ops-sev-row med"><span>Media</span><strong><?= $opsMed; ?></strong></div>
                            <div class="ops-sev-row low"><span>Baja</span><strong><?= $opsLow; ?></strong></div>
                          </div>
                          <div class="ops-sev-filter" id="ops-severity-filter">
                            <button type="button" class="ops-sev-filter-btn is-active" data-ops-severity="all">Todas</button>
                            <button type="button" class="ops-sev-filter-btn" data-ops-severity="high">Alta</button>
                            <button type="button" class="ops-sev-filter-btn" data-ops-severity="medium">Media</button>
                            <button type="button" class="ops-sev-filter-btn" data-ops-severity="low">Baja</button>
                          </div>
                          <p class="mut" style="margin:0;font-size:.8rem">Basado en score de la cola actual.</p>
                        </div>
                      </div>
                      <div class="card ops-panel">
                        <div class="card-body">
                          <div class="ops-panel-head">
                            <h3>Motivos dominantes</h3>
                            <span class="badge badge-outline-info">SeÃ±ales</span>
                          </div>
                          <?php if (empty($opsTopReasons)): ?>
                            <p class="mut">Sin motivos dominantes.</p>
                          <?php else: ?>
                            <div class="ops-domain-list">
                              <?php foreach ($opsTopReasons as $reason => $hits): ?>
                                <div class="ops-domain-item">
                                  <span class="mut"><?= clickfix_h($reason); ?></span>
                                  <span class="mono"><?= (int) $hits; ?>x</span>
                                </div>
                              <?php endforeach; ?>
                            </div>
                          <?php endif; ?>
                        </div>
                      </div>
                    </div>
                    <div class="card ops-panel">
                      <div class="card-body">
                        <div class="ops-panel-head">
                          <h3>Dominios reincidentes</h3>
                          <span class="badge badge-outline-success">Top</span>
                        </div>
                        <?php if (empty($opsTopDomainGroups)): ?>
                          <p class="mut">Sin dominios reincidentes.</p>
                        <?php else: ?>
                          <div class="ops-domain-list">
                            <?php foreach ($opsTopDomainGroups as $opsDom): ?>
                              <div class="ops-domain-item">
                                <span class="mono"><?= clickfix_h((string) ($opsDom['hostname'] ?? '-')); ?></span>
                                <span class="mono"><?= (int) ($opsDom['events'] ?? 0); ?> ev</span>
                              </div>
                            <?php endforeach; ?>
                          </div>
                        <?php endif; ?>
                      </div>
                    </div>
                  </aside>
            <?php endif; ?>
            <div class="event-workbench">
            <aside class="event-feed" id="event-feed">
              <?php if (empty($eventWorkbenchRows)): ?>
                <div class="event-empty"><?= $page === 'search' ? 'Sin resultados de bÃºsqueda.' : 'Sin eventos recientes.'; ?></div>
              <?php else: ?>
                <?php
                  $eventFeedGroups = [];
                  foreach ($eventWorkbenchRows as $eventIndex => $eventRow) {
                      $scoreValue = isset($eventRow['score_total']) && is_numeric($eventRow['score_total']) ? (int) $eventRow['score_total'] : 0;
                      $firstReason = !empty($eventRow['reason_list']) ? (string) $eventRow['reason_list'][0] : (string) ($eventRow['message'] ?? '');
                      $hostLabel = (string) ($eventRow['hostname'] ?: '-');
                      $groupKey = strtolower($hostLabel . '|' . $firstReason);
                      $eventTime = (string) ($eventRow['activity_at'] ?? $eventRow['received_at'] ?? '');
                      $eventBlockedFeed = !empty($eventRow['blocked']) || !empty($eventRow['host_blocked_before']) || ($canSrViewer && !empty($eventRow['ip_blocked_before']));
                      if (!isset($eventFeedGroups[$groupKey])) {
                          $eventFeedGroups[$groupKey] = [
                              'host' => $hostLabel,
                              'reason' => $firstReason,
                              'items' => [],
                              'primary' => $eventIndex,
                              'maxScore' => $scoreValue,
                              'latestTime' => $eventTime,
                              'blocked' => $eventBlockedFeed,
                          ];
                      }
                      $eventFeedGroups[$groupKey]['items'][] = [
                          'index' => $eventIndex,
                          'score' => $scoreValue,
                          'time' => $eventTime,
                          'blocked' => $eventBlockedFeed,
                          'row' => $eventRow,
                      ];
                      if ($scoreValue > $eventFeedGroups[$groupKey]['maxScore']) {
                          $eventFeedGroups[$groupKey]['maxScore'] = $scoreValue;
                      }
                      if (!$eventFeedGroups[$groupKey]['blocked'] && $eventBlockedFeed) {
                          $eventFeedGroups[$groupKey]['blocked'] = true;
                      }
                  }
                ?>
                <?php foreach ($eventFeedGroups as $group): ?>
                  <?php
                    $items = $group['items'];
                    $primaryItem = $items[0];
                    $eventIndex = (int) $primaryItem['index'];
                    $scoreValue = (int) $primaryItem['score'];
                    $severityClass = cfseverityclass($scoreValue);
                    $eventBlockedFeed = !empty($group['blocked']);
                    $count = count($items);
                  ?>
                  <div class="event-group<?= $eventBlockedFeed ? ' is-blocked' : ''; ?>" data-severity="<?= clickfix_h($severityClass); ?>">
                    <div class="event-group-head">
                      <button type="button" class="event-feed-item<?= $eventIndex === 0 ? ' is-active' : ''; ?><?= $eventBlockedFeed ? ' is-blocked' : ''; ?>" data-event-index="<?= $eventIndex; ?>">
                        <span class="event-feed-sev <?= clickfix_h($severityClass); ?>"></span>
                        <span class="event-feed-main">
                          <span class="event-feed-host"><?= clickfix_h($group['host']); ?></span>
                          <span class="event-feed-meta"><?= clickfix_h($group['latestTime']); ?> | <?= (int) $group['maxScore']; ?>/100</span>
                          <span class="event-feed-reason"><?= clickfix_h($group['reason']); ?></span>
                          <?php if ($eventBlockedFeed): ?>
                            <span class="event-feed-flag">BLOQUEADO</span>
                          <?php endif; ?>
                          <?php if ($count > 1): ?>
                            <span class="event-feed-count">x<?= $count; ?></span>
                          <?php endif; ?>
                        </span>
                      </button>
                      <?php if ($count > 1): ?>
                        <button type="button" class="event-group-toggle" data-group-toggle aria-expanded="false">Ver <?= $count - 1; ?> mÃ¡s</button>
                      <?php endif; ?>
                    </div>
                    <?php if ($count > 1): ?>
                      <div class="event-group-items" hidden>
                        <?php foreach ($items as $itemIndex => $item): ?>
                          <?php if ($itemIndex === 0) continue; ?>
                          <?php
                            $childScore = (int) $item['score'];
                            $childSeverity = cfseverityclass($childScore);
                          ?>
                          <button type="button" class="event-feed-item event-feed-item--child<?= !empty($item['blocked']) ? ' is-blocked' : ''; ?>" data-event-index="<?= (int) $item['index']; ?>">
                            <span class="event-feed-sev <?= clickfix_h($childSeverity); ?>"></span>
                            <span class="event-feed-main">
                              <span class="event-feed-meta"><?= clickfix_h($item['time']); ?> | <?= $childScore; ?>/100</span>
                              <span class="event-feed-reason"><?= clickfix_h($group['reason']); ?></span>
                            </span>
                          </button>
                        <?php endforeach; ?>
                      </div>
                    <?php endif; ?>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </aside>
            <section class="event-detail-shell">
              <div id="event-empty" class="event-empty">
                <?= $page === 'search' ? 'Selecciona un resultado para ver el detalle enriquecido.' : 'Selecciona un evento para ver el detalle enriquecido.'; ?>
              </div>
              <div id="event-detail" hidden>
                <div class="event-topline">
                  <h3 id="event-title" class="event-title"></h3>
                  <div id="event-badges" class="event-badges"></div>
                </div>
                <section class="event-ai-summary" id="event-ai-summary">
                  <div class="event-ai-summary-head">
                    <h3>Resumen asistido</h3>
                    <span class="event-ai-summary-tag">local</span>
                  </div>
                  <p id="event-ai-summary-text" class="event-ai-summary-text">Selecciona un evento para ver un resumen corto.</p>
                  <div id="event-ai-summary-points" class="event-ai-summary-points"></div>
                </section>
                <div class="event-grid">
                  <div class="event-kv"><b>Fecha</b><span id="event-time"></span></div>
                    <div class="event-kv"><b>PaÃ­s</b><span id="event-country"></span></div>
                  <div class="event-kv"><b>URL</b><span id="event-url"></span></div>
                  <div class="event-kv"><b>URL previa</b><span id="event-prev-url"></span></div>
                  <div class="event-kv"><b>IP (manual report)</b><span id="event-ip"></span></div>
                  <div class="event-kv"><b>Extension (manual report)</b><span id="event-extension"></span></div>
                  <div class="event-kv"><b>Dominio ya bloqueado</b><span id="event-domain-history"></span></div>
                  <?php if ($canSrViewer): ?>
                    <div class="event-kv"><b>IP ya bloqueada</b><span id="event-ip-history"></span></div>
                  <?php endif; ?>
                </div>
                <?php if ($loggedIn): ?>
                  <div class="event-ioc" id="event-ioc" hidden>
                    <h3>IOC del archivo</h3>
                    <div class="event-grid">
                      <div class="event-kv"><b>SHA256</b><span id="event-ioc-hash"></span></div>
                      <div class="event-kv"><b>Nombre</b><span id="event-ioc-name"></span></div>
                      <div class="event-kv"><b>Ruta</b><span id="event-ioc-path"></span></div>
                      <div class="event-kv"><b>Sitio descarga</b><span id="event-ioc-site"></span></div>
                    </div>
                    <div class="mut" style="margin-top:6px;font-size:.78rem">Fecha de detecciÃ³n: <span id="event-ioc-date"></span></div>
                  </div>
                <?php endif; ?>
                <div class="event-columns">
                  <div>
                    <h3>Motivos detectados</h3>
                    <ul id="event-reasons" class="event-list"></ul>
                  </div>
                  <div>
                    <h3>Snippets detectados</h3>
                    <div id="event-snippets"></div>
                  </div>
                </div>
                <div class="event-columns" id="event-evidence">
                  <div>
                    <h3>Evidencias (signals)</h3>
                    <ul id="event-signals" class="event-list"></ul>
                  </div>
                  <div>
                    <h3>Detalle de score</h3>
                    <div id="event-score-details"></div>
                  </div>
                </div>
                <?php if ($loggedIn): ?>
                  <div class="event-related">
                    <div class="event-related-head">
                      <h3 style="margin:0">Alertas relacionadas (dominio/IP)</h3>
                      <button class="btn" id="event-related-load" type="button">Ver relacionadas</button>
                    </div>
                    <div id="event-related-status" class="event-related-note">No se cargan autom?ticamente. Pulsa "Ver relacionadas" para consultar historial relacionado.</div>
                    <div id="event-related-wrap" class="event-related-table-wrap" hidden>
      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>Fecha</th>
            <th>Host</th>
            <th>IP web</th>
            <th>Score</th>
            <th>Estado</th>
            <th>Relacion</th>
            <th>Evidencias</th>
            <th>Abrir</th>
          </tr>
        </thead>
        <tbody id="event-related-body"></tbody>
      </table>
                    </div>
                  </div>
                <?php endif; ?>
                <div class="mitre-blueprint" id="event-mitre">
                  <h4>MITRE ATT&CK Blueprint</h4>
                  <div class="mitre-blueprint-grid" id="event-mitre-grid"></div>
                  <div class="mitre-empty" id="event-mitre-empty" hidden>Sin TTPs detectadas para este evento.</div>
                </div>
                <div class="event-context<?= $canViewExactEventContext ? ' event-context-exact' : ''; ?>">
                  <h3 id="event-context-title"><?= clickfix_h($canViewExactEventContext ? 'Contexto completo de pagina' : 'Contexto resaltado'); ?></h3>
                  <pre id="event-context"></pre>
                </div>
                <?php if ($loggedIn && cfcan($user, 'analyst_mid')): ?>
                  <form id="event-review-form" method="post" class="mono" style="margin-top:10px;">
                    <input type="hidden" id="event-review-action" name="action" value="review">
                    <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                    <input type="hidden" id="event-review-id" name="report_id" value="">
                    <div id="event-review-bulk-ids"></div>
                    <div id="event-review-scope" class="event-scope-picker" hidden>
                      <span class="event-scope-label">Aplicar veredicto a</span>
                      <div class="event-scope-toggle" role="group" aria-label="Alcance del veredicto">
                        <button type="button" class="is-active" data-review-scope="single">Solo este evento</button>
                        <button type="button" data-review-scope="group">Todo el grupo</button>
                      </div>
                      <div id="event-review-scope-note" class="event-scope-note"></div>
                    </div>
	                    <select id="event-review-status" name="status">
	                      <option value="pending"><?= clickfix_h(cft('review_pending')); ?></option>
	                      <option value="accepted"><?= clickfix_h(cft('review_accepted')); ?></option>
	                      <option value="rejected"><?= clickfix_h(cft('review_rejected')); ?></option>
	                      <option value="allowlisted"><?= clickfix_h(cft('review_allowlisted')); ?></option>
	                    </select>
                    <button class="btn btn-primary btn-sm" type="submit">Actualizar revisiÃ³n</button>
                  </form>
                  <div class="mut" style="margin-top:8px;font-size:.76rem;line-height:1.45">
                    <b><?= clickfix_h(cft('review_legend_title')); ?></b><br>
	                    <?= clickfix_h(cft('review_legend_pending')); ?><br>
	                    <?= clickfix_h(cft('review_legend_accepted')); ?><br>
	                    <?= clickfix_h(cft('review_legend_rejected')); ?><br>
	                    <?= clickfix_h(cft('review_legend_allowlisted')); ?>
	                  </div>
                <?php endif; ?>
                <?php if ($loggedIn && (cfcan($user, 'analyst_jr') || cfcan($user, 'analyst_sr'))): ?>
                  <div class="event-ops" style="margin-top:10px">
                    <h3 style="margin:0 0 6px">Operaciones rÃ¡pidas</h3>
                    <div class="intel-toolbar">
                      <?php if (cfcan($user, 'analyst_sr')): ?>
                        <form method="post" class="event-quick-form" style="display:flex;gap:8px;align-items:center;width:auto;">
                          <input type="hidden" name="action" value="report_quick_action">
                          <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                          <input type="hidden" data-event-report-id name="report_id" value="">
                          <input type="hidden" name="quick_mode" value="block_domain">
                          <button type="submit" class="btn btn-outline-light btn-sm">Bloquear dominio</button>
                        </form>
                        <form method="post" class="event-quick-form" style="display:flex;gap:8px;align-items:center;width:auto;">
                          <input type="hidden" name="action" value="report_quick_action">
                          <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                          <input type="hidden" data-event-report-id name="report_id" value="">
                          <input type="hidden" name="quick_mode" value="send_investigation_list">
                          <button type="submit" class="btn btn-outline-light btn-sm">Mandar a investigaciÃ³n</button>
                        </form>
                      <?php endif; ?>
                      <?php if (cfcan($user, 'analyst_jr')): ?>
                        <form method="post" class="event-quick-form" style="display:flex;gap:8px;align-items:center;width:auto;">
                          <input type="hidden" name="action" value="report_quick_action">
                          <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                          <input type="hidden" data-event-report-id name="report_id" value="">
                          <input type="hidden" name="quick_mode" value="create_investigation">
                          <button class="btn btn-primary btn-sm" type="submit">Generar investigacion</button>
                        </form>
                        <form method="post" class="event-quick-form" style="display:flex;gap:8px;align-items:center;width:auto;">
                          <input type="hidden" name="action" value="report_quick_action_llm">
                          <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                          <input type="hidden" data-event-report-id name="report_id" value="">
                          <input type="hidden" name="quick_mode" value="create_investigation_llm">
                          <select name="llm_profile_id" class="form-select" style="width:auto;min-width:130px;font-size:.76rem;padding:4px 8px">
                            <option value="0">-- LLM profile --</option>
                            <?php foreach ($llmProfiles as $lp): ?><option value="<?= (int) ($lp['id'] ?? 0); ?>"><?= clickfix_h((string) ($lp['label'] ?? 'Profile')); ?></option><?php endforeach; ?>
                          </select>
                          <button class="btn btn-primary btn-sm" type="submit" style="background:var(--accent);border-color:var(--accent)">Investigar con LLM</button>
                        </form>
                      <?php endif; ?>
                      <?php if (cfcan($user, 'admin')): ?>
                        <form method="post" class="event-quick-form" style="display:flex;gap:8px;align-items:center;width:auto;" onsubmit="return confirm('Eliminar esta detecciÃ³n de forma permanente?');">
                          <input type="hidden" name="action" value="report_quick_action">
                          <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                          <input type="hidden" data-event-report-id name="report_id" value="">
                          <input type="hidden" name="quick_mode" value="delete_report">
                          <button class="btn btn-primary btn-sm" type="submit">Eliminar detecciÃ³n</button>
                        </form>
                      <?php endif; ?>
                    </div>
                  </div>
                <?php endif; ?>
                <details class="event-raw">
                  <summary>Raw JSON</summary>
                  <pre id="event-raw"></pre>
                </details>
              </div>
            </section>
          </div>
          <?php if ($page === 'ops'): ?><div class="ops-lower-grid"><?php endif; ?>
          <details class="legacy-events legacy-card">
            <summary>Eventos por dominio (agrupados)</summary>
            <table>
              <thead><tr><th>Dominio</th><th>Eventos</th><th>Impactos (dup)</th><th>Bloqueos</th><th>Score max</th><th>Ãšltima actividad</th></tr></thead>
              <tbody>
                <?php foreach ($eventDomainGroups as $domainGroup): ?>
                  <tr>
                    <td class="mono"><?= clickfix_h((string) ($domainGroup['hostname'] ?? '(sin dominio)')); ?></td>
                    <td class="mono"><?= (int) ($domainGroup['events'] ?? 0); ?></td>
                    <td class="mono"><?= (int) ($domainGroup['duplicate_hits'] ?? 0); ?></td>
                    <td class="mono"><?= (int) ($domainGroup['blocked'] ?? 0); ?></td>
                    <td class="mono"><?= (int) ($domainGroup['max_score'] ?? 0); ?>/100</td>
                    <td class="mono"><?= clickfix_h((string) ($domainGroup['latest_activity_at'] ?? '')); ?></td>
                  </tr>
                <?php endforeach; ?>
                <?php if (empty($eventDomainGroups)): ?>
                  <tr><td colspan="6" class="mut">Sin eventos para agrupar.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </details>
          <details class="legacy-events legacy-card">
            <summary>Capturas web (before/after)</summary>
            <?php
              $opsScan = is_array($latestScanPreview) ? $latestScanPreview : null;
              $opsAssets = is_array($latestScanAssetsApproved) ? $latestScanAssetsApproved : ['before' => null, 'after' => null];
              $opsAssetsReview = is_array($latestScanAssetsReview) ? $latestScanAssetsReview : ['before_exists' => false, 'after_exists' => false];
              $opsScanId = (int) ($opsScan['id'] ?? 0);
              $opsScanQueue = [];
              if (!empty($scanReviewQueue) && $opsScanId > 0) {
                  foreach ($scanReviewQueue as $queueRow) {
                      if ((int) ($queueRow['report_id'] ?? 0) === $opsScanId) {
                          $opsScanQueue[] = $queueRow;
                      }
                  }
              }
            ?>
            <?php if ($opsScan === null): ?>
              <p class="mut">Sin capturas disponibles.</p>
            <?php else: ?>
              <div class="scan-capture-head">
                <div>
                  <h3 class="scan-title">Último escaneo</h3>
                  <p class="mono">scan_id: <?= (int) ($opsScan['id'] ?? 0); ?> | <?= clickfix_h((string) ($opsScan['hostname'] ?? '-')); ?> | <?= clickfix_h((string) ($opsScan['received_at'] ?? '')); ?></p>
                </div>
                <?php if ($canAdminViewer && $opsScanId > 0): ?>
                  <form method="post" class="scan-swap" onsubmit="return confirm('Intercambiar BEFORE y AFTER del scan #<?= $opsScanId; ?>?');">
                    <input type="hidden" name="action" value="scan_image_swap">
                    <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                    <input type="hidden" name="scan_report_id" value="<?= $opsScanId; ?>">
                    <input type="hidden" name="return_page" value="ops">
                    <button class="btn btn-primary btn-sm" type="submit">Intercambiar orden</button>
                  </form>
                <?php endif; ?>
              </div>
              <div class="scan-capture-grid">
                <?php foreach (['before' => 'Antes', 'after' => 'Después'] as $scanKind => $scanLabel): ?>
                  <?php
                    $approvedUrl = (string) ($opsAssets[$scanKind] ?? '');
                    $assetExists = !empty($opsAssetsReview[$scanKind . '_exists']);
                    $assetStatus = (string) ($opsAssetsReview[$scanKind . '_status'] ?? 'missing');
                    $adminPreviewUrl = $opsScanId > 0 ? clickfix_scan_image_url($opsScanId, $scanKind, true) : '';
                  ?>
                  <div class="scan-card">
                    <div class="scan-card-head">
                      <h4 class="mono"><?= clickfix_h($scanLabel); ?></h4>
                      <span class="badge badge-outline-light"><?= clickfix_h($assetStatus); ?></span>
                    </div>
                    <div class="scan-card-media">
                      <?php if (!empty($approvedUrl)): ?>
                        <img src="<?= clickfix_h($approvedUrl); ?>" alt="<?= clickfix_h($scanKind . ' scan'); ?>" loading="lazy">
                      <?php else: ?>
                        <div class="scan-placeholder">Sin captura aprobada</div>
                      <?php endif; ?>
                    </div>
                    <?php if ($canAdminViewer && $opsScanId > 0 && $assetExists): ?>
                      <div class="scan-card-actions">
                        <a class="btn btn-outline-light btn-sm" href="<?= clickfix_h($adminPreviewUrl); ?>" target="_blank" rel="noopener noreferrer">Abrir admin</a>
                        <form method="post" class="scan-inline-form">
                          <input type="hidden" name="action" value="scan_image_review">
                          <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                          <input type="hidden" name="scan_report_id" value="<?= $opsScanId; ?>">
                          <input type="hidden" name="scan_kind" value="<?= clickfix_h($scanKind); ?>">
                          <input type="hidden" name="scan_status" value="approved">
                          <input type="hidden" name="scan_note" value="approved from ops capture">
                          <input type="hidden" name="return_page" value="ops">
                          <button class="btn btn-primary btn-sm" type="submit">Aprobar y fijar</button>
                        </form>
                        <form method="post" class="scan-inline-form" onsubmit="return confirm('Eliminar captura <?= clickfix_h($scanKind); ?> del scan #<?= $opsScanId; ?>?');">
                          <input type="hidden" name="action" value="scan_image_delete">
                          <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                          <input type="hidden" name="scan_report_id" value="<?= $opsScanId; ?>">
                          <input type="hidden" name="scan_kind" value="<?= clickfix_h($scanKind); ?>">
                          <input type="hidden" name="return_page" value="ops">
                          <button class="btn btn-outline-light btn-sm" type="submit">Eliminar</button>
                        </form>
                        <form method="post" class="scan-inline-form">
                          <input type="hidden" name="action" value="scan_image_assign">
                          <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                          <input type="hidden" name="scan_report_id" value="<?= $opsScanId; ?>">
                          <input type="hidden" name="scan_source_kind" value="<?= clickfix_h($scanKind); ?>">
                          <input type="hidden" name="scan_target_kind" value="<?= $scanKind === 'before' ? 'after' : 'before'; ?>">
                          <input type="hidden" name="return_page" value="ops">
                          <button class="btn btn-outline-light btn-sm" type="submit">Usar como <?= $scanKind === 'before' ? 'Después' : 'Antes'; ?></button>
                        </form>
                      </div>
                    <?php elseif ($canAdminViewer && $opsScanId > 0): ?>
                      <div class="scan-card-actions">
                        <form method="post" enctype="multipart/form-data" class="scan-inline-form">
                          <input type="hidden" name="action" value="scan_image_upload">
                          <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                          <input type="hidden" name="scan_report_id" value="<?= $opsScanId; ?>">
                          <input type="hidden" name="scan_kind" value="<?= clickfix_h($scanKind); ?>">
                          <input type="hidden" name="return_page" value="ops">
                          <input type="file" name="scan_upload_file" accept="image/png,image/jpeg,image/webp,image/gif,image/bmp,image/avif" required>
                          <select name="scan_upload_status">
                            <option value="approved" selected>approved</option>
                            <option value="pending">pending</option>
                            <option value="rejected">rejected</option>
                          </select>
                          <button class="btn btn-primary btn-sm" type="submit">Subir <?= clickfix_h($scanLabel); ?></button>
                        </form>
                      </div>
                    <?php endif; ?>
                  </div>
                <?php endforeach; ?>
              </div>
              <?php if ($canAdminViewer): ?>
                <div class="scan-gallery">
                  <h4 class="scan-subtitle">Capturas recibidas</h4>
                  <?php if (empty($scanReviewQueue)): ?>
                    <p class="mut">No hay capturas pendientes.</p>
                  <?php else: ?>
                    <?php $queueView = !empty($opsScanQueue) ? $opsScanQueue : array_slice($scanReviewQueue, 0, 40); ?>
                    <div class="scan-gallery-grid">
                      <?php foreach ($queueView as $pendingScan): ?>
                        <?php
                          $pendingReportId = (int) ($pendingScan['report_id'] ?? 0);
                          $pendingKind = (string) ($pendingScan['kind'] ?? '');
                          $pendingExists = !empty($pendingScan['asset_exists']);
                          $pendingPreviewUrl = (string) ($pendingScan['preview_url'] ?? '');
                          $pendingStatus = (string) ($pendingScan['status'] ?? 'pending');
                        ?>
                        <div class="scan-thumb-card">
                          <div class="scan-thumb-head">
                            <span class="mono">#<?= $pendingReportId; ?></span>
                            <span class="badge badge-outline-light"><?= clickfix_h($pendingKind); ?></span>
                          </div>
                          <div class="scan-thumb-media">
                            <?php if ($pendingExists): ?>
                              <img src="<?= clickfix_h($pendingPreviewUrl); ?>" alt="preview scan" loading="lazy">
                            <?php else: ?>
                              <div class="scan-placeholder">Sin preview</div>
                            <?php endif; ?>
                          </div>
                          <div class="scan-thumb-meta">
                            <span class="mono"><?= clickfix_h((string) ($pendingScan['hostname'] ?? '-')); ?></span>
                            <span class="mut"><?= clickfix_h((string) ($pendingScan['received_at'] ?? '')); ?></span>
                            <span class="badge badge-outline-light"><?= clickfix_h($pendingStatus); ?></span>
                          </div>
                          <div class="scan-thumb-actions">
                            <?php if ($pendingExists): ?>
                              <a class="btn btn-outline-light btn-sm" href="<?= clickfix_h($pendingPreviewUrl); ?>" target="_blank" rel="noopener noreferrer">Abrir</a>
                            <?php endif; ?>
                            <form method="post" class="scan-inline-form">
                              <input type="hidden" name="action" value="scan_image_review">
                              <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                              <input type="hidden" name="scan_report_id" value="<?= $pendingReportId; ?>">
                              <input type="hidden" name="scan_kind" value="<?= clickfix_h($pendingKind); ?>">
                              <input type="hidden" name="scan_status" value="approved">
                              <input type="hidden" name="scan_note" value="approved from ops gallery">
                              <input type="hidden" name="return_page" value="ops">
                              <button class="btn btn-primary btn-sm" type="submit">Aprobar</button>
                            </form>
                            <form method="post" class="scan-inline-form">
                              <input type="hidden" name="action" value="scan_image_review">
                              <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                              <input type="hidden" name="scan_report_id" value="<?= $pendingReportId; ?>">
                              <input type="hidden" name="scan_kind" value="<?= clickfix_h($pendingKind); ?>">
                              <input type="hidden" name="return_page" value="ops">
                              <select name="scan_status">
                                <option value="approved">approved</option>
                                <option value="rejected">rejected</option>
                                <option value="pending" selected>pending</option>
                              </select>
                              <button class="btn btn-outline-light btn-sm" type="submit">Aplicar</button>
                            </form>
                          </div>
                        </div>
                      <?php endforeach; ?>
                    </div>
                    <?php if (empty($opsScanQueue)): ?>
                      <p class="mut" style="margin-top:10px">Mostrando capturas recientes. Filtra por scan seleccionando un evento del feed.</p>
                    <?php endif; ?>
                  <?php endif; ?>
                </div>
              <?php endif; ?>
            <?php endif; ?>
          </details>
          <details class="legacy-events legacy-card">
            <summary>Vista tabular clasica</summary>
            <?php
              $canTableReview = $loggedIn && cfcan($user, 'analyst_mid');
              $canTableOps = $loggedIn && (cfcan($user, 'analyst_jr') || cfcan($user, 'analyst_sr'));
            ?>
            <?php if ($canTableReview): ?>
              <form id="bulk-review-form" method="post" class="bulk-review-toolbar">
                <input type="hidden" name="action" value="review_bulk">
                <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                <div class="bulk-review-count" id="bulk-review-count">0 seleccionadas</div>
	                <select name="status" id="bulk-review-status" style="max-width:260px">
	                  <option value="pending"><?= clickfix_h(cft('review_pending')); ?></option>
	                  <option value="accepted"><?= clickfix_h(cft('review_accepted')); ?></option>
	                  <option value="rejected"><?= clickfix_h(cft('review_rejected')); ?></option>
	                  <option value="allowlisted"><?= clickfix_h(cft('review_allowlisted')); ?></option>
	                </select>
                <div class="bulk-review-actions">
                  <button type="button" class="secondary" id="bulk-review-select-pending">Solo pendientes</button>
                  <button type="button" class="secondary" id="bulk-review-clear">Limpiar</button>
                  <button class="btn btn-primary btn-sm" type="submit" id="bulk-review-submit" disabled>Aplicar veredicto masivo</button>
                </div>
              </form>
            <?php endif; ?>
            <div class="legacy-table-wrap">
            <table id="alerts-classic-table" class="table table-striped ops-table legacy-table"><thead><tr><?php if ($canTableReview): ?><th class="bulk-review-select-cell" data-sortable="false"><input type="checkbox" id="bulk-review-select-all" aria-label="Seleccionar todas"></th><?php endif; ?><th>Fecha</th><th>Dominio</th><th>Marcado</th><?php if ($canSrViewer): ?><th>IP (manual)</th><th>Extension (manual)</th><?php endif; ?><th>Mensaje</th><th>Estado</th><?php if ($canTableReview): ?><th>RevisiÃ³n</th><?php endif; ?><?php if ($canTableOps): ?><th>Operaciones</th><?php endif; ?></tr></thead><tbody>
            <?php foreach ($reports as $r): ?>
              <?php
                $tableEventType = strtolower((string) ($r['event_type'] ?? 'clickfix_alert'));
                $isManualReport = $tableEventType === 'manual_report';
                $manualReportIp = $isManualReport ? (string) ($r['ip'] ?? '') : '';
                $manualReportExt = $isManualReport ? (string) ($r['extension_version'] ?? '') : '';
                $tableHostKey = strtolower(trim((string) ($r['hostname'] ?? '')));
                $tableHostHistory = is_array($blockedHistoryByHostname[$tableHostKey] ?? null)
                    ? $blockedHistoryByHostname[$tableHostKey]
                    : ['total_count' => 0, 'blocked_count' => 0];
                $tableIpKey = trim((string) ($r['ip'] ?? ''));
                $tableIpHistory = is_array($blockedHistoryByIp[$tableIpKey] ?? null)
                    ? $blockedHistoryByIp[$tableIpKey]
                    : ['total_count' => 0, 'blocked_count' => 0];
                $tableHostBlocked = (int) ($tableHostHistory['blocked_count'] ?? 0) > 0;
                $tableIpBlocked = $canSrViewer && $tableIpKey !== '' && (int) ($tableIpHistory['blocked_count'] ?? 0) > 0;
                $tableMarkerText = '-';
                if ($tableHostBlocked || $tableIpBlocked) {
                    $markerParts = [];
                    if ($tableHostBlocked) {
                        $markerParts[] = 'DOM x' . (int) ($tableHostHistory['blocked_count'] ?? 0);
                    }
                    if ($tableIpBlocked) {
                        $markerParts[] = 'IP x' . (int) ($tableIpHistory['blocked_count'] ?? 0);
                    }
                    $tableMarkerText = 'REINCIDENTE ' . implode(' | ', $markerParts);
                }
              ?>
              <tr>
                <?php if ($canTableReview): ?>
                  <td class="bulk-review-select-cell">
                    <input
                      type="checkbox"
                      class="bulk-review-checkbox"
                      form="bulk-review-form"
                      name="report_ids[]"
                      value="<?= (int) ($r['id'] ?? 0); ?>"
                      data-review-status="<?= clickfix_h((string) ($r['review_status'] ?? 'pending')); ?>"
                      aria-label="Seleccionar alerta #<?= (int) ($r['id'] ?? 0); ?>"
                    >
                  </td>
                <?php endif; ?>
                <td class="mono"><?= clickfix_h((string) ($r['received_at'] ?? '')); ?></td>
                <td class="mono"><?= clickfix_h((string) ($r['hostname'] ?? '-')); ?></td>
                <td class="mono"><?= clickfix_h($tableMarkerText); ?></td>
                <?php if ($canSrViewer): ?>
                  <td class="mono"><?= clickfix_h($manualReportIp !== '' ? $manualReportIp : '-'); ?></td>
                  <td class="mono"><?= clickfix_h($manualReportExt !== '' ? $manualReportExt : '-'); ?></td>
                <?php endif; ?>
                <td class="message-cell"><?= nl2br(clickfix_h((string) ($r['message'] ?? ''))); ?></td>
                <td><span class="badge <?= clickfix_h((string) ($r['review_status'] ?? 'pending')); ?>"><?= clickfix_h((string) ($r['review_status'] ?? 'pending')); ?></span></td>
                <?php if ($canTableReview): ?><td><form method="post" class="mono"><input type="hidden" name="action" value="review"><input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>"><input type="hidden" name="report_id" value="<?= (int) ($r['id'] ?? 0); ?>"><select name="status"><option value="pending"<?= (($r['review_status'] ?? 'pending') === 'pending') ? ' selected' : ''; ?>><?= clickfix_h(cft('review_pending')); ?></option><option value="accepted"<?= (($r['review_status'] ?? 'pending') === 'accepted') ? ' selected' : ''; ?>><?= clickfix_h(cft('review_accepted')); ?></option><option value="rejected"<?= (($r['review_status'] ?? 'pending') === 'rejected') ? ' selected' : ''; ?>><?= clickfix_h(cft('review_rejected')); ?></option><option value="allowlisted"<?= (($r['review_status'] ?? 'pending') === 'allowlisted') ? ' selected' : ''; ?>><?= clickfix_h(cft('review_allowlisted')); ?></option></select><button class="btn btn-primary btn-sm" type="submit">OK</button></form></td><?php endif; ?>
                <?php if ($canTableOps): ?>
                  <td>
                    <div style="display:flex;gap:6px;flex-wrap:wrap">
                      <?php if (cfcan($user, 'analyst_sr')): ?>
                        <form method="post" class="mono" style="display:flex;gap:6px;align-items:center;width:auto">
                          <input type="hidden" name="action" value="report_quick_action">
                          <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                          <input type="hidden" name="report_id" value="<?= (int) ($r['id'] ?? 0); ?>">
                          <input type="hidden" name="quick_mode" value="block_domain">
                          <button type="submit" class="btn btn-outline-light btn-sm">Bloquear</button>
                        </form>
                        <form method="post" class="mono" style="display:flex;gap:6px;align-items:center;width:auto">
                          <input type="hidden" name="action" value="report_quick_action">
                          <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                          <input type="hidden" name="report_id" value="<?= (int) ($r['id'] ?? 0); ?>">
                          <input type="hidden" name="quick_mode" value="send_investigation_list">
                          <button type="submit" class="btn btn-outline-light btn-sm">A investigatelist</button>
                        </form>
                      <?php endif; ?>
                      <?php if (cfcan($user, 'analyst_jr')): ?>
                        <form method="post" class="mono" style="display:flex;gap:6px;align-items:center;width:auto">
                          <input type="hidden" name="action" value="report_quick_action">
                          <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                          <input type="hidden" name="report_id" value="<?= (int) ($r['id'] ?? 0); ?>">
                          <input type="hidden" name="quick_mode" value="create_investigation">
                          <button class="btn btn-primary btn-sm" type="submit">Generar investigacion</button>
                        </form>
                        <form method="post" class="mono" style="display:flex;gap:6px;align-items:center;width:auto">
                          <input type="hidden" name="action" value="report_quick_action_llm">
                          <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                          <input type="hidden" name="report_id" value="<?= (int) ($r['id'] ?? 0); ?>">
                          <input type="hidden" name="quick_mode" value="create_investigation_llm">
                          <button class="btn btn-primary btn-sm" type="submit" style="background:var(--accent);border-color:var(--accent)">LLM</button>
                        </form>
                      <?php endif; ?>
                      <?php if (cfcan($user, 'admin')): ?>
                        <form method="post" class="mono" style="display:flex;gap:6px;align-items:center;width:auto" onsubmit="return confirm('Eliminar esta detecciÃ³n de forma permanente?');">
                          <input type="hidden" name="action" value="report_quick_action">
                          <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                          <input type="hidden" name="report_id" value="<?= (int) ($r['id'] ?? 0); ?>">
                          <input type="hidden" name="quick_mode" value="delete_report">
                          <button class="btn btn-primary btn-sm" type="submit">Eliminar detecciÃ³n</button>
                        </form>
                      <?php endif; ?>
                    </div>
                  </td>
                <?php endif; ?>
              </tr>
            <?php endforeach; ?>
            </tbody></table>
            </div>
          </details>
          <?php if ($page === 'ops'): ?></div><?php endif; ?>
        </article>
        
        <?php if ($page === 'ops'): ?>
                </div>
              </div>
            <?php endif; ?>
      </section>
    <?php endif; ?>

    <?php if ($loggedIn && $page === 'intel_stats'): ?>
      <?php
        $corrJobs = is_array($intelCorrelationStats['jobs'] ?? null) ? $intelCorrelationStats['jobs'] : [];
        $corrArtifacts = is_array($intelCorrelationStats['artifacts'] ?? null) ? $intelCorrelationStats['artifacts'] : [];
        $corrAlerts = is_array($intelCorrelationStats['alerts'] ?? null) ? $intelCorrelationStats['alerts'] : [];
        $corrInvestigations = is_array($intelCorrelationStats['investigations'] ?? null) ? $intelCorrelationStats['investigations'] : [];
        $corrStages = is_array($intelCorrelationStats['stages'] ?? null) ? $intelCorrelationStats['stages'] : [];
        $corrStageDistribution = is_array($corrStages['distribution'] ?? null) ? $corrStages['distribution'] : [];
        $corrTopChains = is_array($corrStages['top_chains'] ?? null) ? $corrStages['top_chains'] : [];
        $corrMalwareTypes = is_array($intelCorrelationStats['malware_types'] ?? null) ? $intelCorrelationStats['malware_types'] : [];
        $corrArtifactTypes = is_array($intelCorrelationStats['artifact_types'] ?? null) ? $intelCorrelationStats['artifact_types'] : [];
        $corrTopCommands = is_array($intelCorrelationStats['top_commands'] ?? null) ? $intelCorrelationStats['top_commands'] : [];
        $corrRecentJobs = is_array($intelCorrelationStats['recent_jobs'] ?? null) ? $intelCorrelationStats['recent_jobs'] : [];
        $corrMalwareMax = 1;
        foreach ($corrMalwareTypes as $row) {
            $corrMalwareMax = max($corrMalwareMax, (int) ($row['total'] ?? 0));
        }
        $corrArtifactTypeMax = 1;
        foreach ($corrArtifactTypes as $row) {
            $corrArtifactTypeMax = max($corrArtifactTypeMax, (int) ($row['total'] ?? 0));
        }
        $corrCommandMax = 1;
        foreach ($corrTopCommands as $row) {
            $corrCommandMax = max($corrCommandMax, (int) ($row['total'] ?? 0));
        }
        $corrStageMax = 1;
        foreach ($corrStageDistribution as $row) {
            $corrStageMax = max($corrStageMax, (int) ($row['investigations'] ?? 0));
        }
      ?>
      <section class="row" style="margin-bottom:8px">
        <article class="card">
          <h2>Correlation / Investigation Stats</h2>
          <p class="mut">Vista consolidada del pipeline de correlaciÃ³n: veredictos, familias, artefactos, stages y ejecucion de jobs.</p>
          <div class="analytics-kpi-grid">
            <div class="analytics-kpi"><div class="k">jobs totales</div><div class="v"><?= (int) ($corrJobs['total'] ?? 0); ?></div></div>
            <div class="analytics-kpi"><div class="k">jobs completados</div><div class="v"><?= (int) ($corrJobs['completed'] ?? 0); ?></div></div>
            <div class="analytics-kpi"><div class="k">artefactos</div><div class="v"><?= (int) ($corrArtifacts['total'] ?? 0); ?></div></div>
            <div class="analytics-kpi"><div class="k">payloads descargados</div><div class="v"><?= (int) ($corrArtifacts['fetched_payloads'] ?? 0); ?></div></div>
            <div class="analytics-kpi"><div class="k">investigaciones con pipeline</div><div class="v"><?= (int) ($corrInvestigations['with_pipeline'] ?? 0); ?></div></div>
            <div class="analytics-kpi"><div class="k">media stages</div><div class="v"><?= number_format((float) ($corrStages['avg'] ?? 0), 2); ?></div></div>
            <div class="analytics-kpi"><div class="k">max stages</div><div class="v"><?= (int) ($corrStages['max'] ?? 0); ?></div></div>
            <div class="analytics-kpi"><div class="k">an?lisis hechos</div><div class="v"><?= (int) ($corrArtifacts['analysis_done'] ?? 0); ?></div></div>
            </div>
          </div>
        </article>
      </section>

      <section class="row" style="margin-bottom:8px">
        <article class="card">
          <h2>Veredictos de alertas</h2>
          <div class="mini-chart">
            <?php foreach ([
                ['label' => 'accepted', 'value' => (int) ($corrAlerts['accepted'] ?? 0)],
                ['label' => 'rejected', 'value' => (int) ($corrAlerts['rejected'] ?? 0)],
                ['label' => 'pending', 'value' => (int) ($corrAlerts['pending'] ?? 0)],
                ['label' => 'blocked', 'value' => (int) ($corrAlerts['blocked'] ?? 0)],
            ] as $distRow): ?>
              <?php $distWidth = max(2, (int) round(((int) ($distRow['value'] ?? 0) / max(1, (int) max($corrAlerts ?: [1]))) * 100)); ?>
              <div class="mini-row">
                <div class="mono"><?= clickfix_h((string) ($distRow['label'] ?? '')); ?></div>
                <div class="mini-bar"><span style="width:<?= $distWidth; ?>%"></span></div>
                <div class="mini-score"><?= (int) ($distRow['value'] ?? 0); ?></div>
              </div>
            <?php endforeach; ?>
          </div>
          <p class="mut mono" style="margin-top:8px">accepted: <?= (int) ($corrAlerts['accepted'] ?? 0); ?> | rejected: <?= (int) ($corrAlerts['rejected'] ?? 0); ?> | pending: <?= (int) ($corrAlerts['pending'] ?? 0); ?></p>
        </article>
        <article class="card">
          <h2>Veredictos de investigaciones</h2>
          <div class="mini-chart">
            <?php foreach ([
                ['label' => 'malicious', 'value' => (int) ($corrInvestigations['malicious'] ?? 0)],
                ['label' => 'suspicious', 'value' => (int) ($corrInvestigations['suspicious'] ?? 0)],
                ['label' => 'investigating', 'value' => (int) ($corrInvestigations['investigating'] ?? 0)],
                ['label' => 'clean', 'value' => (int) ($corrInvestigations['clean'] ?? 0)],
                ['label' => 'unknown', 'value' => (int) ($corrInvestigations['unknown'] ?? 0)],
            ] as $distRow): ?>
              <?php $distWidth = max(2, (int) round(((int) ($distRow['value'] ?? 0) / max(1, (int) max($corrInvestigations ?: [1]))) * 100)); ?>
              <div class="mini-row">
                <div class="mono"><?= clickfix_h((string) ($distRow['label'] ?? '')); ?></div>
                <div class="mini-bar"><span style="width:<?= $distWidth; ?>%"></span></div>
                <div class="mini-score"><?= (int) ($distRow['value'] ?? 0); ?></div>
              </div>
            <?php endforeach; ?>
          </div>
          <p class="mut mono" style="margin-top:8px">with_pipeline: <?= (int) ($corrInvestigations['with_pipeline'] ?? 0); ?></p>
        </article>
      </section>

      <section class="row" style="margin-bottom:8px">
        <article class="card">
          <h2>Tipos de malware / tags</h2>
          <div class="mini-chart">
            <?php foreach ($corrMalwareTypes as $row): ?>
              <?php $width = max(2, (int) round(((int) ($row['total'] ?? 0) / $corrMalwareMax) * 100)); ?>
              <div class="mini-row">
                <div class="mono"><?= clickfix_h((string) ($row['tag'] ?? '')); ?></div>
                <div class="mini-bar"><span style="width:<?= $width; ?>%"></span></div>
                <div class="mini-score"><?= (int) ($row['total'] ?? 0); ?></div>
              </div>
            <?php endforeach; ?>
            <?php if (empty($corrMalwareTypes)): ?>
              <p class="mut">Sin tags de malware todavÃ­a.</p>
            <?php endif; ?>
          </div>
        </article>
        <article class="card">
          <h2>Tipos de artefacto</h2>
          <div class="mini-chart">
            <?php foreach ($corrArtifactTypes as $row): ?>
              <?php $width = max(2, (int) round(((int) ($row['total'] ?? 0) / $corrArtifactTypeMax) * 100)); ?>
              <div class="mini-row">
                <div class="mono"><?= clickfix_h((string) ($row['kind'] ?? 'unknown')); ?></div>
                <div class="mini-bar"><span style="width:<?= $width; ?>%"></span></div>
                <div class="mini-score"><?= (int) ($row['total'] ?? 0); ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        </article>
      </section>

      <section class="row" style="margin-bottom:8px">
        <article class="card">
          <h2>DistribuciÃ³n de stages</h2>
          <div class="mini-chart">
            <?php foreach ($corrStageDistribution as $row): ?>
              <?php $width = max(2, (int) round(((int) ($row['investigations'] ?? 0) / $corrStageMax) * 100)); ?>
              <div class="mini-row">
                <div class="mono"><?= (int) ($row['stage_count'] ?? 1); ?> stages</div>
                <div class="mini-bar"><span style="width:<?= $width; ?>%"></span></div>
                <div class="mini-score"><?= (int) ($row['investigations'] ?? 0); ?></div>
              </div>
            <?php endforeach; ?>
            <?php if (empty($corrStageDistribution)): ?>
              <p class="mut">Sin datos de stages todavÃ­a.</p>
            <?php endif; ?>
          </div>
        </article>
        <article class="card">
          <h2>Comandos mas repetidos</h2>
          <div class="mini-chart">
            <?php foreach ($corrTopCommands as $row): ?>
              <?php $width = max(2, (int) round(((int) ($row['total'] ?? 0) / $corrCommandMax) * 100)); ?>
              <div class="mini-row">
                <div class="mono"><?= clickfix_h(substr((string) ($row['label'] ?? ''), 0, 42)); ?></div>
                <div class="mini-bar"><span style="width:<?= $width; ?>%"></span></div>
                <div class="mini-score"><?= (int) ($row['total'] ?? 0); ?></div>
              </div>
            <?php endforeach; ?>
            <?php if (empty($corrTopCommands)): ?>
              <p class="mut">Sin comandos correlacionados todavÃ­a.</p>
            <?php endif; ?>
          </div>
        </article>
      </section>

      <section class="row" style="margin-bottom:8px">
        <article class="card">
          <h2>Top cadenas por numero de stages</h2>
          <div class="analytics-table-wrap">
            <table class="compact-table">
              <thead><tr><th>Graph</th><th>Título</th><th>Dominio</th><th>Stages</th><th>Artefactos</th><th>AcciÃ³n</th></tr></thead>
              <tbody>
                <?php foreach ($corrTopChains as $row): ?>
                  <tr>
                    <td class="mono">#<?= (int) ($row['graph_id'] ?? 0); ?></td>
                    <td><?= clickfix_h((string) ($row['title'] ?? 'Investigation')); ?></td>
                    <td class="mono"><?= clickfix_h((string) ($row['site_domain'] ?? '')); ?></td>
                    <td class="mono"><?= (int) ($row['stages'] ?? 0); ?></td>
                    <td class="mono"><?= (int) ($row['artifact_total'] ?? 0); ?></td>
                    <td><a class="btn" href="<?= clickfix_h(cfurl('intel', false, ['graph_id' => (int) ($row['graph_id'] ?? 0)])); ?>">Abrir</a></td>
                  </tr>
                <?php endforeach; ?>
                <?php if (empty($corrTopChains)): ?>
                  <tr><td colspan="6" class="mut">Sin cadenas calculadas todavÃ­a.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </article>
      </section>

      <section class="row">
        <article class="card">
          <h2>Jobs recientes del pipeline</h2>
          <div class="analytics-table-wrap">
            <table class="compact-table">
              <thead><tr><th>Job</th><th>Graph</th><th>Estado</th><th>Modo</th><th>Procesados</th><th>Actualizado</th><th>Error</th></tr></thead>
              <tbody>
                <?php foreach ($corrRecentJobs as $row): ?>
                  <tr>
                    <td class="mono">#<?= (int) ($row['id'] ?? 0); ?></td>
                    <td class="mono"><a href="<?= clickfix_h(cfurl('intel', false, ['graph_id' => (int) ($row['graph_id'] ?? 0)])); ?>">#<?= (int) ($row['graph_id'] ?? 0); ?></a></td>
                    <td class="mono"><?= clickfix_h((string) ($row['status'] ?? 'queued')); ?></td>
                    <td class="mono"><?= clickfix_h((string) ($row['mode'] ?? 'alert_correlation')); ?></td>
                    <td class="mono"><?= (int) ($row['processed_artifacts'] ?? 0); ?></td>
                    <td class="mono"><?= clickfix_h((string) ($row['updated_at'] ?? '')); ?></td>
                    <td><?= clickfix_h((string) ($row['last_error'] ?? '')); ?></td>
                  </tr>
                <?php endforeach; ?>
                <?php if (empty($corrRecentJobs)): ?>
                  <tr><td colspan="7" class="mut">Sin jobs recientes.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </article>
      </section>
    <?php endif; ?>

    <?php if ($loggedIn && $page === 'analytics'): ?>
      <?php
        $trendLabels = is_array($analyticsOverview['labels'] ?? null) ? $analyticsOverview['labels'] : [];
        $trendAlerts = is_array($analyticsOverview['alerts'] ?? null) ? $analyticsOverview['alerts'] : [];
        $trendBlocks = is_array($analyticsOverview['blocks'] ?? null) ? $analyticsOverview['blocks'] : [];
        $trendReviewPending = is_array($analyticsOverview['review_pending'] ?? null) ? $analyticsOverview['review_pending'] : [];
        $trendReviewed = is_array($analyticsOverview['reviewed'] ?? null) ? $analyticsOverview['reviewed'] : [];
        $trendManualReports = is_array($analyticsOverview['manual_reports'] ?? null) ? $analyticsOverview['manual_reports'] : [];
        $trendHighRisk = is_array($analyticsOverview['high_risk'] ?? null) ? $analyticsOverview['high_risk'] : [];
        $trendAvgScore = is_array($analyticsOverview['avg_score'] ?? null) ? $analyticsOverview['avg_score'] : [];
        $trendUniqueHosts = is_array($analyticsOverview['unique_hosts'] ?? null) ? $analyticsOverview['unique_hosts'] : [];
        $eventTypeLabels = is_array($analyticsOverview['event_type_labels'] ?? null) ? $analyticsOverview['event_type_labels'] : [];
        $eventTypeCounts = is_array($analyticsOverview['event_type_counts'] ?? null) ? $analyticsOverview['event_type_counts'] : [];
        $trendMax = 1;
        foreach ($trendAlerts as $v) { if ((int) $v > $trendMax) { $trendMax = (int) $v; } }
        foreach ($trendBlocks as $v) { if ((int) $v > $trendMax) { $trendMax = (int) $v; } }
        $trendReviewMax = 1;
        foreach ($trendReviewPending as $v) { if ((int) $v > $trendReviewMax) { $trendReviewMax = (int) $v; } }
        foreach ($trendReviewed as $v) { if ((int) $v > $trendReviewMax) { $trendReviewMax = (int) $v; } }
        $trendRiskMax = 1;
        foreach ($trendManualReports as $v) { if ((int) $v > $trendRiskMax) { $trendRiskMax = (int) $v; } }
        foreach ($trendHighRisk as $v) { if ((int) $v > $trendRiskMax) { $trendRiskMax = (int) $v; } }
        $eventTypeMax = 1;
        foreach ($eventTypeCounts as $v) { if ((int) $v > $eventTypeMax) { $eventTypeMax = (int) $v; } }

        $trendCount = count($trendLabels);
        $sumAlertsPeriod = array_sum(array_map('intval', $trendAlerts));
        $sumBlocksPeriod = array_sum(array_map('intval', $trendBlocks));
        $avgAlertsPerDay = $trendCount > 0 ? round($sumAlertsPeriod / $trendCount, 2) : 0.0;
        $avgBlocksPerDay = $trendCount > 0 ? round($sumBlocksPeriod / $trendCount, 2) : 0.0;
        $peakAlerts = 0;
        $peakAlertsDay = '-';
        foreach ($trendAlerts as $idx => $value) {
            $valueInt = (int) $value;
            if ($valueInt >= $peakAlerts) {
                $peakAlerts = $valueInt;
                $peakAlertsDay = (string) ($trendLabels[$idx] ?? '-');
            }
        }
        $bestBlockRate = 0.0;
        $bestBlockRateDay = '-';
        foreach ($trendLabels as $idx => $value) {
            $a = (int) ($trendAlerts[$idx] ?? 0);
            $b = (int) ($trendBlocks[$idx] ?? 0);
            if ($a <= 0) {
                continue;
            }
            $rate = ($b / $a) * 100;
            if ($rate >= $bestBlockRate) {
                $bestBlockRate = $rate;
                $bestBlockRateDay = (string) $value;
            }
        }
        $zeroAlertDays = 0;
        foreach ($trendAlerts as $value) {
            if ((int) $value === 0) {
                $zeroAlertDays++;
            }
        }
        $avgRiskScorePeriod = 0.0;
        if (!empty($trendAvgScore)) {
            $avgRiskScorePeriod = round(array_sum(array_map('floatval', $trendAvgScore)) / max(1, count($trendAvgScore)), 2);
        }

        $latestScan = is_array($latestScanPreview) ? $latestScanPreview : null;
        $latestAssets = is_array($latestScanAssetsApproved) ? $latestScanAssetsApproved : ['before' => null, 'after' => null];
        $latestAssetsReviewState = is_array($latestScanAssetsReview) ? $latestScanAssetsReview : $latestAssets;
        $scanAdminView = cfcan($user, 'admin');
        $mlSampleInsights = is_array($mlInsights['sample_300'] ?? null) ? $mlInsights['sample_300'] : $mlInsights;
        $mlHistoricalInsights = is_array($mlInsights['historical_all'] ?? null) ? $mlInsights['historical_all'] : [];
        $mlTopKeywords = is_array($mlSampleInsights['top_keywords'] ?? null) ? $mlSampleInsights['top_keywords'] : [];
        $mlHistoricalKeywords = is_array($mlHistoricalInsights['top_keywords'] ?? null) ? $mlHistoricalInsights['top_keywords'] : [];
        $mlKeywordsWindows = is_array($mlInsights['keywords_windows'] ?? null) ? $mlInsights['keywords_windows'] : [];
        $mlWindowLabels = [
            'total_histÃ³rico' => 'Total histÃ³rico',
            '?ltima_semana' => 'Ãšltima semana',
            'Ãšltimo_mes' => 'ltimo mes',
            'Ãšltimos_3_meses' => 'ltimos 3 meses',
            'Ãšltimos_6_meses' => 'ltimos 6 meses',
        ];
        $mlTopPredictions = is_array($mlInsights['top_predictions'] ?? null) ? $mlInsights['top_predictions'] : [];
        $mlBurstDomains = is_array($mlInsights['burst_domains'] ?? null) ? $mlInsights['burst_domains'] : [];
        $anomalySummaryRows = is_array($anomalyInsights['summary'] ?? null) ? $anomalyInsights['summary'] : [];
        $anomalyDomainSpikes = is_array($anomalyInsights['domain_spikes'] ?? null) ? $anomalyInsights['domain_spikes'] : [];
        $analyticsPendingRows = array_slice($pendingOutsideReports, 0, 100);
        $mlDistMax = max(
            1,
            (int) ($mlSampleInsights['malicious_predicted'] ?? 0),
            (int) ($mlSampleInsights['suspicious_predicted'] ?? 0),
            (int) ($mlSampleInsights['low_risk_predicted'] ?? 0)
        );
        $mlHistoricalDistMax = max(
            1,
            (int) ($mlHistoricalInsights['malicious_predicted'] ?? 0),
            (int) ($mlHistoricalInsights['suspicious_predicted'] ?? 0),
            (int) ($mlHistoricalInsights['low_risk_predicted'] ?? 0)
        );
        $mlKeywordMax = 1;
        foreach ($mlTopKeywords as $mlk) {
            $hits = (int) ($mlk['hits'] ?? 0);
            if ($hits > $mlKeywordMax) {
                $mlKeywordMax = $hits;
            }
        }
        $mlHistoricalKeywordMax = 1;
        foreach ($mlHistoricalKeywords as $mlk) {
            $hits = (int) ($mlk['hits'] ?? 0);
            if ($hits > $mlHistoricalKeywordMax) {
                $mlHistoricalKeywordMax = $hits;
            }
        }
      ?>
      <section class="analytics-shell">
        <div class="analytics-header">
          <div>
            <h1>Centro de m?tricas operativas</h1>
            <p class="mut">Vista tipo OpenCTI: rendimiento, riesgo, backlog y actividad de analistas.</p>
          </div>
          <div class="analytics-header-actions">
            <div class="range-chips">
              <button type="button" class="chip is-active">14 dÃ­as</button>
              <button type="button" class="chip">30 dÃ­as</button>
              <button type="button" class="chip">90 dÃ­as</button>
            </div>
            <div class="kpi-pill">alertas 24h <b><?= (int) ($metrics['alerts_24h'] ?? 0); ?></b></div>
            <div class="kpi-pill">bloqueos 24h <b><?= (int) ($metrics['blocks_24h'] ?? 0); ?></b></div>
            <div class="kpi-pill">ratio 24h <b><?= number_format($blockRate24h ?? 0, 2); ?>%</b></div>
          </div>
        </div>
      <section class="row">
        <article class="card">
          <h2>GrÃ¡ficos y mÃ©tricas operativas</h2>
          <p class="mut">Alertas, bloqueos, revisiÃ³n, riesgo, actividad manual y capacidad de respuesta diaria.</p>
          <div class="analytics-kpi-grid">
            <div class="analytics-kpi"><div class="k">alertas / da (media)</div><div class="v"><?= number_format($avgAlertsPerDay, 2); ?></div></div>
            <div class="analytics-kpi"><div class="k">bloqueos / da (media)</div><div class="v"><?= number_format($avgBlocksPerDay, 2); ?></div></div>
            <div class="analytics-kpi"><div class="k">score medio periodo</div><div class="v"><?= number_format($avgRiskScorePeriod, 2); ?></div></div>
            <div class="analytics-kpi"><div class="k">pico alertas</div><div class="v"><?= (int) $peakAlerts; ?> <span class="mut-mini">(<?= clickfix_h($peakAlertsDay); ?>)</span></div></div>
            <div class="analytics-kpi"><div class="k">mejor ratio bloqueo</div><div class="v"><?= number_format($bestBlockRate, 2); ?>% <span class="mut-mini">(<?= clickfix_h($bestBlockRateDay); ?>)</span></div></div>
            <div class="analytics-kpi"><div class="k">das sin alertas</div><div class="v"><?= (int) $zeroAlertDays; ?>/<?= $trendCount; ?></div></div>
          </div>
          <div class="chart-stack">
            <div class="chart-card">
              <p class="chart-title">Tendencia diaria</p>
              <canvas
                id="analytics-trend-chart"
                class="chart-canvas"
                data-labels='<?= clickfix_h(json_encode($trendLabels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?>'
                data-alerts='<?= clickfix_h(json_encode($trendAlerts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?>'
                data-blocks='<?= clickfix_h(json_encode($trendBlocks, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?>'
              ></canvas>
              <div class="chart-legend">
                <span><i class="dot" style="background:#14b8ff"></i>alertas</span>
                <span><i class="dot" style="background:#38d17a"></i>bloqueos</span>
              </div>
            </div>
            <div class="chart-card">
              <p class="chart-title">Ratio de bloqueo por dÃ­a</p>
              <canvas
                id="analytics-ratio-chart"
                class="chart-canvas"
                data-labels='<?= clickfix_h(json_encode($trendLabels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?>'
                data-alerts='<?= clickfix_h(json_encode($trendAlerts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?>'
                data-blocks='<?= clickfix_h(json_encode($trendBlocks, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?>'
              ></canvas>
              <div class="chart-legend">
                <span><i class="dot" style="background:#ffd166"></i>% bloqueo</span>
              </div>
            </div>
            <div class="chart-card">
              <p class="chart-title">RevisiÃ³n diaria (revisado vs pendiente)</p>
              <canvas
                id="analytics-review-chart"
                class="chart-canvas"
                data-labels='<?= clickfix_h(json_encode($trendLabels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?>'
                data-alerts='<?= clickfix_h(json_encode($trendReviewed, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?>'
                data-blocks='<?= clickfix_h(json_encode($trendReviewPending, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?>'
              ></canvas>
              <div class="chart-legend">
                <span><i class="dot" style="background:#57f0be"></i>revisado</span>
                <span><i class="dot" style="background:#ff8e9f"></i>pendiente</span>
              </div>
            </div>
            <div class="chart-card">
              <p class="chart-title">Actividad manual vs riesgo alto</p>
              <canvas
                id="analytics-risk-chart"
                class="chart-canvas"
                data-labels='<?= clickfix_h(json_encode($trendLabels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?>'
                data-alerts='<?= clickfix_h(json_encode($trendHighRisk, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?>'
                data-blocks='<?= clickfix_h(json_encode($trendManualReports, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?>'
              ></canvas>
              <div class="chart-legend">
                <span><i class="dot" style="background:#ff9f4a"></i>high risk (score &gt;= 80)</span>
                <span><i class="dot" style="background:#7dd3fc"></i>manual report</span>
              </div>
            </div>
          </div>
          <div class="chart-grid-advanced">
            <div class="chart-card">
              <p class="chart-title">Hosts Ãºnicos (tendencia)</p>
              <canvas
                id="analytics-hosts-chart"
                class="chart-canvas"
                data-labels='<?= clickfix_h(json_encode($trendLabels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?>'
                data-series='<?= clickfix_h(json_encode($trendUniqueHosts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?>'
              ></canvas>
              <div class="chart-legend">
                <span><i class="dot" style="background:#a78bfa"></i>hosts Ãºnicos</span>
              </div>
            </div>
            <div class="chart-card">
              <p class="chart-title">Score medio diario</p>
              <canvas
                id="analytics-score-chart"
                class="chart-canvas"
                data-labels='<?= clickfix_h(json_encode($trendLabels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?>'
                data-series='<?= clickfix_h(json_encode($trendAvgScore, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?>'
              ></canvas>
              <div class="chart-legend">
                <span><i class="dot" style="background:#f97316"></i>avg score</span>
              </div>
            </div>
            <div class="chart-card">
              <p class="chart-title">Backlog de revisiÃ³n (pendiente)</p>
              <canvas
                id="analytics-pending-chart"
                class="chart-canvas"
                data-labels='<?= clickfix_h(json_encode($trendLabels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?>'
                data-counts='<?= clickfix_h(json_encode($trendReviewPending, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?>'
              ></canvas>
              <div class="chart-legend">
                <span><i class="dot" style="background:#ff8e9f"></i>pendientes</span>
              </div>
            </div>
            <div class="chart-card">
              <p class="chart-title">Manual vs alto riesgo (comparativa)</p>
              <canvas
                id="analytics-manual-chart"
                class="chart-canvas"
                data-labels='<?= clickfix_h(json_encode($trendLabels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?>'
                data-counts='<?= clickfix_h(json_encode($trendManualReports, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?>'
              ></canvas>
              <div class="chart-legend">
                <span><i class="dot" style="background:#7dd3fc"></i>manual</span>
              </div>
            </div>
          </div>
          <input class="analytics-search" id="analytics-daily-search" type="text" placeholder="Buscar en tabla diaria (fecha, valores...)">
          <div class="analytics-table-wrap">
          <table class="compact-table">
            <thead><tr><th>Dia</th><th>Alertas</th><th>Bloqueos</th><th>Revisado</th><th>Pendiente</th><th>High Risk</th><th>Manual</th><th>Hosts unicos</th><th>Tendencia</th></tr></thead>
            <tbody id="analytics-daily-body">
              <?php foreach ($trendLabels as $idx => $label): ?>
                <?php
                  $a = isset($trendAlerts[$idx]) ? (int) $trendAlerts[$idx] : 0;
                  $b = isset($trendBlocks[$idx]) ? (int) $trendBlocks[$idx] : 0;
                  $rev = isset($trendReviewed[$idx]) ? (int) $trendReviewed[$idx] : 0;
                  $pen = isset($trendReviewPending[$idx]) ? (int) $trendReviewPending[$idx] : 0;
                  $hr = isset($trendHighRisk[$idx]) ? (int) $trendHighRisk[$idx] : 0;
                  $man = isset($trendManualReports[$idx]) ? (int) $trendManualReports[$idx] : 0;
                  $uHosts = isset($trendUniqueHosts[$idx]) ? (int) $trendUniqueHosts[$idx] : 0;
                  $aWidth = max(2, (int) round(($a / $trendMax) * 100));
                  $bWidth = max(2, (int) round(($b / $trendMax) * 100));
                ?>
                <tr data-day-row="1">
                  <td class="mono"><?= clickfix_h((string) $label); ?></td>
                  <td class="mono"><?= $a; ?></td>
                  <td class="mono"><?= $b; ?></td>
                  <td class="mono"><?= $rev; ?></td>
                  <td class="mono"><?= $pen; ?></td>
                  <td class="mono"><?= $hr; ?></td>
                  <td class="mono"><?= $man; ?></td>
                  <td class="mono"><?= $uHosts; ?></td>
                  <td>
                    <div style="display:grid;gap:4px">
                      <div style="height:8px;border-radius:999px;background:#18435e"><div style="width:<?= $aWidth; ?>%;height:100%;border-radius:999px;background:#14b8ff"></div></div>
                      <div style="height:8px;border-radius:999px;background:#18435e"><div style="width:<?= $bWidth; ?>%;height:100%;border-radius:999px;background:#33d17a"></div></div>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
              <tr id="analytics-daily-empty" hidden><td colspan="9" class="mut">Sin coincidencias para el filtro.</td></tr>
            </tbody>
          </table>
          </div>
        </article>
        <article class="card">
          <h2>DistribuciÃ³n por tipo y nuevos dominios</h2>
          <div class="chart-card" style="margin-bottom:8px">
            <p class="chart-title">Tipos de evento (periodo)</p>
            <canvas
              id="analytics-type-chart"
              class="chart-canvas"
              data-labels='<?= clickfix_h(json_encode($eventTypeLabels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?>'
              data-counts='<?= clickfix_h(json_encode($eventTypeCounts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?>'
            ></canvas>
            <div class="chart-legend">
              <span><i class="dot" style="background:#63d9ff"></i>conteo por event_type</span>
            </div>
          </div>
          <div class="analytics-table-wrap">
            <table class="compact-table">
              <thead><tr><th>Dominio</th><th>Primera vez</th><th>Hits</th></tr></thead>
              <tbody>
                <?php foreach (array_slice((array) ($analyticsOverview['new_domains'] ?? []), 0, 40) as $nd): ?>
                  <tr>
                    <td class="mono"><?= clickfix_h((string) ($nd['hostname'] ?? '')); ?></td>
                    <td class="mono"><?= clickfix_h((string) ($nd['first_seen'] ?? '')); ?></td>
                    <td class="mono"><?= (int) ($nd['hits'] ?? 0); ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </article>
      </section>
      <section class="row" style="margin-bottom:8px">
        <article class="card">
          <h2>Detector de anomalÃ­as (24h vs baseline)</h2>
          <p class="mut">Comparativa de hoy frente al comportamiento histÃ³rico reciente.</p>
          <div class="analytics-table-wrap">
            <table class="compact-table">
              <thead><tr><th>Metrica</th><th>Actual</th><th>Media baseline</th><th>Std baseline</th><th>Z-score</th><th>Delta %</th><th>Anomalia</th></tr></thead>
              <tbody>
                <?php foreach ($anomalySummaryRows as $signal): ?>
                  <?php
                    $metricKey = (string) ($signal['metric'] ?? '');
                    $metricLabel = $metricKey;
                    if ($metricKey === 'alerts_24h') { $metricLabel = 'alertas_24h'; }
                    if ($metricKey === 'high_risk_24h') { $metricLabel = 'alto_riesgo_24h'; }
                    if ($metricKey === 'block_rate_24h_pct') { $metricLabel = 'ratio_bloqueo_24h_%'; }
                  ?>
                  <tr>
                    <td class="mono"><?= clickfix_h($metricLabel); ?></td>
                    <td class="mono"><?= clickfix_h((string) ($signal['current'] ?? 0)); ?></td>
                    <td class="mono"><?= clickfix_h((string) ($signal['baseline_mean'] ?? 0)); ?></td>
                    <td class="mono"><?= clickfix_h((string) ($signal['baseline_std'] ?? 0)); ?></td>
                    <td class="mono"><?= clickfix_h((string) ($signal['z_score'] ?? 0)); ?></td>
                    <td class="mono"><?= clickfix_h((string) ($signal['delta_pct'] ?? 0)); ?>%</td>
                    <td class="mono"><?= !empty($signal['is_anomaly']) ? ('si (' . clickfix_h((string) ($signal['severity'] ?? 'low')) . ')') : 'no'; ?></td>
                  </tr>
                <?php endforeach; ?>
                <?php if (empty($anomalySummaryRows)): ?>
                  <tr><td colspan="7" class="mut">Sin datos suficientes para calcular anomalias.</td></tr>
                      </section>
    <?php endif; ?>
              </tbody>
            </table>
          </div>
        </article>
        <article class="card">
          <h2>Picos anÃ³malos por dominio</h2>
          <p class="mut">Dominios con subida anormal en 24h respecto a los 6 dÃ­as previos.</p>
          <div class="analytics-table-wrap">
            <table class="compact-table">
              <thead><tr><th>Dominio</th><th>Hits 24h</th><th>Baseline/24h</th><th>Ratio</th><th>Z-score</th><th>High risk 24h</th><th>Bloqueados 24h</th></tr></thead>
              <tbody>
                <?php foreach ($anomalyDomainSpikes as $spike): ?>
                  <tr>
                    <td class="mono"><?= clickfix_h((string) ($spike['hostname'] ?? '')); ?></td>
                    <td class="mono"><?= (int) ($spike['hits_24h'] ?? 0); ?></td>
                    <td class="mono"><?= clickfix_h((string) ($spike['baseline_per_24h'] ?? 0)); ?></td>
                    <td class="mono"><?= clickfix_h((string) ($spike['ratio'] ?? 0)); ?>x</td>
                    <td class="mono"><?= clickfix_h((string) ($spike['z_score'] ?? 0)); ?></td>
                    <td class="mono"><?= (int) ($spike['high_risk_24h'] ?? 0); ?></td>
                    <td class="mono"><?= (int) ($spike['blocked_24h'] ?? 0); ?></td>
                  </tr>
                <?php endforeach; ?>
                <?php if (empty($anomalyDomainSpikes)): ?>
                  <tr><td colspan="7" class="mut">No se detectaron picos anÃ³malos en 24h.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </article>
      </section>
      <section class="card" style="margin-bottom:8px">
        <h2>Pendientes reales fuera de allowlist/blocklist</h2>
        <p class="mut">Alertas pendientes que no est?n cubiertas por listas, para vaciar backlog real.</p>
        <div class="analytics-kpi-grid" style="margin-bottom:10px">
          <div class="analytics-kpi"><div class="k">alertas pendientes reales</div><div class="v"><?= (int) ($pendingOutsideSummary['alerts'] ?? 0); ?></div></div>
          <div class="analytics-kpi"><div class="k">dominios pendientes reales</div><div class="v"><?= (int) ($pendingOutsideSummary['domains'] ?? 0); ?></div></div>
        </div>
        <input class="analytics-search" id="analytics-pending-search" type="text" placeholder="Buscar pendiente (id, dominio, mensaje, score, tipo)">
        <div class="analytics-table-wrap">
          <table class="compact-table">
            <thead><tr><th>ID</th><th>Fecha</th><th>Dominio</th><th>Score</th><th>Tipo</th><th>Bloqueado</th><th>Mensaje</th><th>AcciÃ³n</th></tr></thead>
            <tbody id="analytics-pending-body">
              <?php foreach ($analyticsPendingRows as $pendingRow): ?>
                <?php $pendingReportId = (int) ($pendingRow['id'] ?? 0); ?>
                <tr data-analytics-pending-row="1">
                  <td class="mono"><?= $pendingReportId; ?></td>
                  <td class="mono"><?= clickfix_h((string) ($pendingRow['received_at'] ?? '')); ?></td>
                  <td class="mono"><?= clickfix_h((string) ($pendingRow['hostname'] ?? '')); ?></td>
                  <td class="mono"><?= (int) ($pendingRow['score_total'] ?? 0); ?></td>
                  <td class="mono"><?= clickfix_h((string) ($pendingRow['event_type'] ?? 'clickfix_alert')); ?></td>
                  <td class="mono"><?= !empty($pendingRow['blocked']) ? 'yes' : 'no'; ?></td>
                  <td><?= clickfix_h((string) ($pendingRow['message'] ?? '')); ?></td>
                  <td><a class="btn" href="<?= clickfix_h(cfurl('ops', false, ['report_id' => (string) $pendingReportId])); ?>">Abrir</a></td>
                </tr>
              <?php endforeach; ?>
              <?php if (empty($analyticsPendingRows)): ?>
                <tr><td colspan="8" class="mut">No hay pendientes fuera de listas para este periodo.</td></tr>
              <?php endif; ?>
              <tr id="analytics-pending-empty" hidden><td colspan="8" class="mut">Sin coincidencias para el filtro.</td></tr>
            </tbody>
          </table>
        </div>
      </section>
      <section class="viz-grid" style="margin-bottom:8px">
        <article class="card">
          <h2>ML Insights (Ãšltimas 300)</h2>
          <p class="mut">ClasificaciÃ³n heurÃ­stica sobre las Ãšltimas <?= (int) ($mlSampleInsights['sample_size'] ?? 0); ?> alertas.</p>
          <div class="mini-chart">
            <?php
              $mlDist = [
                  ['label' => 'malicious', 'value' => (int) ($mlSampleInsights['malicious_predicted'] ?? 0)],
                  ['label' => 'suspicious', 'value' => (int) ($mlSampleInsights['suspicious_predicted'] ?? 0)],
                  ['label' => 'low_risk', 'value' => (int) ($mlSampleInsights['low_risk_predicted'] ?? 0)],
              ];
            ?>
            <?php foreach ($mlDist as $distRow): ?>
              <?php $distWidth = max(2, (int) round(((int) ($distRow['value'] ?? 0) / $mlDistMax) * 100)); ?>
              <div class="mini-row">
                <div class="mono"><?= clickfix_h((string) ($distRow['label'] ?? '')); ?></div>
                <div class="mini-bar"><span style="width:<?= $distWidth; ?>%"></span></div>
                <div class="mini-score"><?= (int) ($distRow['value'] ?? 0); ?></div>
              </div>
            <?php endforeach; ?>
          </div>
          <p class="mut mono" style="margin-top:8px">avg_risk_score: <?= number_format((float) ($mlSampleInsights['avg_risk_score'] ?? 0.0), 2); ?> | high_confidence: <?= (int) ($mlSampleInsights['high_confidence_count'] ?? 0); ?></p>
        </article>
        <article class="card">
          <h2>ML Insights (histÃ³rico total)</h2>
          <p class="mut">ClasificaciÃ³n heurÃ­stica sobre todo el histÃ³rico de alertas.</p>
          <div class="mini-chart">
            <?php
              $mlHistoricalDist = [
                  ['label' => 'malicious', 'value' => (int) ($mlHistoricalInsights['malicious_predicted'] ?? 0)],
                  ['label' => 'suspicious', 'value' => (int) ($mlHistoricalInsights['suspicious_predicted'] ?? 0)],
                  ['label' => 'low_risk', 'value' => (int) ($mlHistoricalInsights['low_risk_predicted'] ?? 0)],
              ];
            ?>
            <?php foreach ($mlHistoricalDist as $distRow): ?>
              <?php $distWidth = max(2, (int) round(((int) ($distRow['value'] ?? 0) / $mlHistoricalDistMax) * 100)); ?>
              <div class="mini-row">
                <div class="mono"><?= clickfix_h((string) ($distRow['label'] ?? '')); ?></div>
                <div class="mini-bar"><span style="width:<?= $distWidth; ?>%"></span></div>
                <div class="mini-score"><?= (int) ($distRow['value'] ?? 0); ?></div>
              </div>
            <?php endforeach; ?>
          </div>
          <p class="mut mono" style="margin-top:8px">sample_size: <?= (int) ($mlHistoricalInsights['sample_size'] ?? 0); ?> | avg_risk_score: <?= number_format((float) ($mlHistoricalInsights['avg_risk_score'] ?? 0.0), 2); ?> | high_confidence: <?= (int) ($mlHistoricalInsights['high_confidence_count'] ?? 0); ?></p>
        </article>
        <article class="card">
          <h2>Anomalias de volumen</h2>
          <table class="table table-striped settings-table">
            <thead><tr><th>Dominio</th><th>Hits 24h</th><th>Burst ratio</th></tr></thead>
            <tbody>
              <?php foreach (array_slice($mlBurstDomains, 0, 10) as $burstRow): ?>
                <tr>
                  <td class="mono"><?= clickfix_h((string) ($burstRow['hostname'] ?? '')); ?></td>
                  <td class="mono"><?= (int) ($burstRow['hits_24h'] ?? 0); ?></td>
                  <td class="mono"><?= number_format((float) ($burstRow['burst_ratio'] ?? 0.0), 2); ?>x</td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </article>
      </section>
      <section class="row" style="margin-bottom:8px">
        <article class="card">
          <h2>Keywords dominantes (Ãšltimas 300)</h2>
          <p class="mut mono" style="margin-top:0">Enrichment: para eventos con score &gt; 20 se analiza HTML y recursos enlazados (cacheado).</p>
          <div class="mini-chart">
            <?php foreach (array_slice($mlTopKeywords, 0, 10) as $keywordRow): ?>
              <?php
                $kwHits = (int) ($keywordRow['hits'] ?? 0);
                $kwWidth = max(2, (int) round(($kwHits / $mlKeywordMax) * 100));
              ?>
              <div class="mini-row">
                <div class="mono"><?= clickfix_h((string) ($keywordRow['keyword'] ?? '-')); ?></div>
                <div class="mini-bar"><span style="width:<?= $kwWidth; ?>%"></span></div>
                <div class="mini-score"><?= $kwHits; ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        </article>
        <article class="card">
          <h2>Keywords dominantes (histÃ³rico total)</h2>
          <div class="mini-chart">
            <?php foreach (array_slice($mlHistoricalKeywords, 0, 10) as $keywordRow): ?>
              <?php
                $kwHits = (int) ($keywordRow['hits'] ?? 0);
                $kwWidth = max(2, (int) round(($kwHits / $mlHistoricalKeywordMax) * 100));
              ?>
              <div class="mini-row">
                <div class="mono"><?= clickfix_h((string) ($keywordRow['keyword'] ?? '-')); ?></div>
                <div class="mini-bar"><span style="width:<?= $kwWidth; ?>%"></span></div>
                <div class="mini-score"><?= $kwHits; ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        </article>
      </section>
      <section class="card" style="margin-bottom:8px">
        <h2>Keywords por ventana temporal</h2>
        <p class="mut">Top keywords para histÃ³rico total, Ãšltima semana, Ãšltimo mes, Ãšltimos 3 meses y Ãšltimos 6 meses.</p>
        <div class="analytics-table-wrap">
          <table class="compact-table">
            <thead><tr><th>Ventana</th><th>Top keywords</th></tr></thead>
            <tbody>
              <?php foreach ($mlWindowLabels as $windowKey => $windowLabel): ?>
                <?php $windowRows = is_array($mlKeywordsWindows[$windowKey] ?? null) ? $mlKeywordsWindows[$windowKey] : []; ?>
                <tr>
                  <td class="mono"><?= clickfix_h($windowLabel); ?></td>
                  <td class="mono">
                    <?php if (empty($windowRows)): ?>
                      <span class="mut">Sin datos</span>
                    <?php else: ?>
                      <?php
                        $windowParts = [];
                        foreach (array_slice($windowRows, 0, 8) as $windowRow) {
                            $windowParts[] = (string) ($windowRow['keyword'] ?? '-') . ' (' . (int) ($windowRow['hits'] ?? 0) . ')';
                        }
                        echo clickfix_h(implode(' | ', $windowParts));
                      ?>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
      <section class="card" style="margin-bottom:8px">
        <h2>Predicciones de riesgo (Top)</h2>
        <p class="mut mono" style="margin-top:0">Thresholds: low_risk &lt; 15 | suspicious 15-38 | malicious &gt; 38 (Ãšltimas 300 alertas)</p>
        <table class="table table-striped settings-table">
          <thead><tr><th>ID</th><th>Fecha</th><th>Dominio</th><th>Score actual</th><th>ML score</th><th>Etiqueta</th><th>Bloqueado</th></tr></thead>
          <tbody>
            <?php foreach (array_slice($mlTopPredictions, 0, 20) as $predRow): ?>
              <tr>
                <td class="mono"><?= (int) ($predRow['id'] ?? 0); ?></td>
                <td class="mono"><?= clickfix_h((string) ($predRow['received_at'] ?? '')); ?></td>
                <td class="mono"><?= clickfix_h((string) ($predRow['hostname'] ?? '-')); ?></td>
                <td class="mono"><?= (int) ($predRow['score_total'] ?? 0); ?></td>
                <td class="mono"><?= number_format((float) ($predRow['predicted_score'] ?? 0.0), 2); ?></td>
                <td><span class="pred-badge <?= clickfix_h((string) ($predRow['predicted_label'] ?? 'low_risk')); ?>"><?= clickfix_h((string) ($predRow['predicted_label'] ?? 'low_risk')); ?></span></td>
                <td class="mono"><?= !empty($predRow['blocked']) ? 'yes' : 'no'; ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </section>
      <section class="row">
        <article class="card">
          <h2>Último escaneo (antes / despues)</h2>
          <?php if ($latestScan === null): ?>
            <p class="mut">No hay escaneos disponibles.</p>
          <?php else: ?>
            <p class="mono">scan_id: <?= (int) ($latestScan['id'] ?? 0); ?> | <?= clickfix_h((string) ($latestScan['hostname'] ?? '-')); ?> | <?= clickfix_h((string) ($latestScan['received_at'] ?? '')); ?></p>
            <div class="split">
              <?php foreach (['before' => 'ANTES', 'after' => 'DESPUÃ‰S'] as $scanKind => $scanLabel): ?>
                <?php
                  $approvedUrl = (string) ($latestAssets[$scanKind] ?? '');
                  $assetExists = !empty($latestAssetsReviewState[$scanKind . '_exists']);
                  $assetStatus = (string) ($latestAssetsReviewState[$scanKind . '_status'] ?? 'missing');
                  $scanReportId = (int) ($latestScan['id'] ?? 0);
                  $adminPreviewUrl = clickfix_scan_image_url($scanReportId, $scanKind, true);
                  $adminDownloadUrl = $adminPreviewUrl . '&download=1';
                  $publicApprovedUrl = $assetStatus === 'approved' ? clickfix_scan_image_url($scanReportId, $scanKind, false) : '';
                  $inlineTarget = 'scan-inline-analytics-' . $scanKind;
                ?>
                <div>
                  <h3 class="mono"><?= clickfix_h($scanLabel); ?></h3>
                  <?php if (!$scanAdminView && $approvedUrl !== ''): ?>
                    <img src="<?= clickfix_h($approvedUrl); ?>" alt="<?= clickfix_h($scanKind . ' scan'); ?>" loading="lazy" style="max-width:100%;border-radius:10px;border:1px solid #5dc8ff33">
                  <?php elseif ($scanAdminView && $assetExists): ?>
                    <p class="mut mono">estado: <?= clickfix_h($assetStatus); ?></p>
                    <div style="display:flex;gap:6px;flex-wrap:wrap">
                      <button class="btn" type="button" data-scan-inline-src="<?= clickfix_h($adminPreviewUrl); ?>" data-scan-inline-target="<?= clickfix_h($inlineTarget); ?>">Ver aqui (manual)</button>
                      <a class="btn" href="<?= clickfix_h($adminPreviewUrl); ?>" target="_blank" rel="noopener noreferrer">Abrir pestaa</a>
                      <a class="btn" href="<?= clickfix_h($adminDownloadUrl); ?>">Descargar</a>
                      <button class="btn" type="button" data-copy-text="<?= clickfix_h($adminPreviewUrl); ?>">Copiar URL admin</button>
                      <?php if ($publicApprovedUrl !== ''): ?>
                        <a class="btn" href="<?= clickfix_h($publicApprovedUrl); ?>" target="_blank" rel="noopener noreferrer">Abrir URL pÃºblica</a>
                        <button class="btn" type="button" data-copy-text="<?= clickfix_h($publicApprovedUrl); ?>">Copiar URL pÃºblica</button>
                      <?php endif; ?>
                    </div>
                    <div id="<?= clickfix_h($inlineTarget); ?>" class="mut mono" style="margin-top:8px">Pulsa "Ver aqui (manual)" para cargar la captura en el panel.</div>
                    <form method="post" style="margin-top:8px;display:inline-block">
                      <input type="hidden" name="action" value="scan_image_review">
                      <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                      <input type="hidden" name="scan_report_id" value="<?= $scanReportId; ?>">
                      <input type="hidden" name="scan_kind" value="<?= clickfix_h($scanKind); ?>">
                      <input type="hidden" name="scan_status" value="approved">
                      <input type="hidden" name="scan_note" value="approved from analytics quick-use">
                      <input type="hidden" name="return_page" value="analytics">
                      <button class="btn btn-primary btn-sm" type="submit">Aprobar y usar en PÃºblico</button>
                    </form>
                    <form method="post" style="margin-top:8px">
                      <input type="hidden" name="action" value="scan_image_review">
                      <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                      <input type="hidden" name="scan_report_id" value="<?= $scanReportId; ?>">
                      <input type="hidden" name="scan_kind" value="<?= clickfix_h($scanKind); ?>">
                      <input type="hidden" name="return_page" value="analytics">
                      <select name="scan_status">
                        <option value="pending"<?= $assetStatus === 'pending' ? ' selected' : ''; ?>>pending</option>
                        <option value="approved"<?= $assetStatus === 'approved' ? ' selected' : ''; ?>>approved</option>
                        <option value="rejected"<?= $assetStatus === 'rejected' ? ' selected' : ''; ?>>rejected</option>
                      </select>
                      <input type="text" name="scan_note" maxlength="500" placeholder="nota de revisiÃ³n (opcional)">
                      <button class="btn btn-primary btn-sm" type="submit">Guardar revisiÃ³n</button>
                    </form>
                    <form method="post" style="margin-top:8px" onsubmit="return confirm('Eliminar captura <?= clickfix_h($scanKind); ?> del scan #<?= $scanReportId; ?>?');">
                      <input type="hidden" name="action" value="scan_image_delete">
                      <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                      <input type="hidden" name="scan_report_id" value="<?= $scanReportId; ?>">
                      <input type="hidden" name="scan_kind" value="<?= clickfix_h($scanKind); ?>">
                      <input type="hidden" name="return_page" value="analytics">
                      <button class="btn btn-primary btn-sm" type="submit">Eliminar captura</button>
                    </form>
                    <form method="post" style="margin-top:8px">
                      <input type="hidden" name="action" value="scan_image_assign">
                      <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                      <input type="hidden" name="scan_report_id" value="<?= $scanReportId; ?>">
                      <input type="hidden" name="scan_source_kind" value="<?= clickfix_h($scanKind); ?>">
                      <input type="hidden" name="scan_target_kind" value="<?= $scanKind === 'before' ? 'after' : 'before'; ?>">
                      <input type="hidden" name="return_page" value="analytics">
                      <button class="btn btn-primary btn-sm" type="submit">Usar esta como <?= $scanKind === 'before' ? 'AFTER' : 'BEFORE'; ?></button>
                    </form>
                    <p class="mut" style="margin-top:6px">Cuando una captura queda en <b>approved</b>, se puede reutilizar en dashboard/index PÃºblico.</p>
                  <?php else: ?>
                    <p class="mut">Sin capturas disponibles.</p>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </article>
        <article class="card">
          <h2>BÃºsqueda avanzada</h2>
          <form method="get">
            <input type="hidden" name="page" value="analytics">
            <input name="domain" value="<?= clickfix_h($domainFilter); ?>" placeholder="dominio">
            <input name="command" value="<?= clickfix_h($commandFilter); ?>" placeholder="comando">
            <input type="date" name="date_from" value="<?= clickfix_h($dateFromFilter); ?>">
            <input type="date" name="date_to" value="<?= clickfix_h($dateToFilter); ?>">
            <button class="btn btn-primary btn-sm" type="submit">Filtrar</button>
          </form>
          <div style="margin-top:10px;max-height:280px;overflow:auto">
            <table>
              <thead><tr><th>Fecha</th><th>Dominio</th><th>Score</th><?php if ($canAdminViewer): ?><th>AcciÃ³n</th><?php endif; ?></tr></thead>
              <tbody>
                <?php foreach (array_slice($filteredReports, 0, 40) as $fr): ?>
                  <tr>
                    <td class="mono"><?= clickfix_h((string) ($fr['received_at'] ?? '')); ?></td>
                    <td class="mono"><?= clickfix_h((string) ($fr['hostname'] ?? '-')); ?></td>
                    <td class="mono"><?= isset($fr['score_total']) ? (int) $fr['score_total'] : 0; ?></td>
                    <?php if ($canAdminViewer): ?>
                      <td>
                        <form method="post" class="mono" onsubmit="return confirm('Eliminar alerta/detecciÃ³n #<?= (int) ($fr['id'] ?? 0); ?> de forma permanente?');">
                          <input type="hidden" name="action" value="report_quick_action">
                          <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                          <input type="hidden" name="report_id" value="<?= (int) ($fr['id'] ?? 0); ?>">
                          <input type="hidden" name="quick_mode" value="delete_alert">
                          <input type="hidden" name="return_page" value="analytics">
                          <button class="btn btn-primary btn-sm" type="submit">Eliminar</button>
                        </form>
                      </td>
                    <?php endif; ?>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </article>
      </section>
    <?php endif; ?>

    <?php if ($loggedIn && $page === 'extensions' && cfcan($user, 'analyst_sr')): ?>
      <section class="row">
        <article class="card">
          <div class="extensions-group-grid">
            <section class="extensions-group-card">
              <div class="extensions-group-head">
                <h3>Agrupado por huella</h3>
                <span class="mut">Versi?n + canal + source + user agent</span>
              </div>
              <div class="extensions-group-list">
                <?php foreach (array_slice(array_values($extensionFingerprintGroups), 0, 8) as $groupRow): ?>
                  <div class="extensions-group-item">
                    <div>
                      <strong class="mono"><?= clickfix_h((string) ($groupRow['fingerprint'] ?? 'Sin firma')); ?></strong>
                      <div class="mut">
                        <?= clickfix_h((string) ($groupRow['version'] ?? '-')); ?>
                        ? <?= clickfix_h((string) ($groupRow['channel'] ?? '-')); ?>
                        ? <?= clickfix_h((string) ($groupRow['source'] ?? '-')); ?>
                      </div>
                    </div>
                    <div class="mono extensions-group-metrics">
                      <span><?= (int) ($groupRow['client_count'] ?? 0); ?> clientes</span>
                      <span><?= (int) ($groupRow['total_events'] ?? 0); ?> ev</span>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            </section>
            <section class="extensions-group-card">
              <div class="extensions-group-head">
                <h3>IPs compartidas</h3>
                <span class="mut">Pivot r?pido entre instalaciones</span>
              </div>
              <div class="extensions-group-list">
                <?php foreach (array_slice(array_values($extensionIpGroups), 0, 8) as $groupRow): ?>
                  <div class="extensions-group-item">
                    <div>
                      <strong class="mono"><?= clickfix_h((string) ($groupRow['ip'] ?? '-')); ?></strong>
                      <div class="mut">
                        <?= clickfix_h(implode(', ', array_slice(array_map('strval', (array) ($groupRow['client_ids'] ?? [])), 0, 3))); ?><?= count((array) ($groupRow['client_ids'] ?? [])) > 3 ? '?' : ''; ?>
                      </div>
                    </div>
                    <div class="mono extensions-group-metrics">
                      <span><?= (int) ($groupRow['client_count'] ?? 0); ?> clientes</span>
                      <span><?= (int) ($groupRow['total_blocks'] ?? 0); ?> bloq</span>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            </section>
            <section class="extensions-group-card">
              <div class="extensions-group-head">
                <h3>Versiones</h3>
                <span class="mut">Detecci?n de rollouts y outliers</span>
              </div>
              <div class="extensions-group-list">
                <?php foreach (array_slice(array_values($extensionVersionGroups), 0, 8) as $groupRow): ?>
                  <div class="extensions-group-item">
                    <div>
                      <strong class="mono"><?= clickfix_h((string) ($groupRow['version'] ?? 'desconocida')); ?></strong>
                    </div>
                    <div class="mono extensions-group-metrics">
                      <span><?= (int) ($groupRow['client_count'] ?? 0); ?> clientes</span>
                      <span><?= (int) ($groupRow['total_events'] ?? 0); ?> ev</span>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            </section>
          </div>
          <h2>Usuarios de extensiones</h2>
          <div class="table-responsive extensions-table-wrap">
          <table class="table table-striped settings-table extensions-clients-table">
            <thead><tr><th>Huella</th><th>Client ID</th><th>Versi?n</th><th>D?as activo</th><th>Total eventos</th><th>Bloqueos</th><th>IPs</th><th>Asociado</th><th>?ltimo seen</th><th>Detalle</th></tr></thead>
            <tbody>
              <?php foreach ($extensionClients as $ec): ?>
                <?php $cid = (string) ($ec['client_id'] ?? 'unknown'); ?>
                <tr>
                  <td class="mono"><?= clickfix_h((string) ($ec['fingerprint_label'] ?? 'Sin firma')); ?><div class="mut"><?= (int) ($extensionFingerprintCounts[(string) ($ec['fingerprint_key'] ?? '')] ?? 1); ?> clientes</div></td>
                  <td class="mono"><?= clickfix_h($cid); ?></td>
                  <td class="mono"><?= clickfix_h((string) ($ec['extension_version'] ?? '')); ?></td>
                  <td class="mono"><?= (int) ($ec['days_active'] ?? 0); ?></td>
                  <td class="mono"><?= (int) ($ec['total_events'] ?? 0); ?></td>
                  <td class="mono"><?= (int) ($ec['total_blocks'] ?? 0); ?></td>
                  <td class="mono"><?= (int) ($ec['ip_count'] ?? 0); ?></td>
                  <td class="mono">
                    <?= (int) ($ec['linked_user_count'] ?? 0); ?>
                    <?php if (!empty($ec['linked_users_label'])): ?>
                      <div class="mut"><?= clickfix_h((string) ($ec['linked_users_label'] ?? '')); ?></div>
                    <?php endif; ?>
                  </td>
                  <td class="mono"><?= clickfix_h((string) ($ec['last_seen'] ?? '')); ?></td>
                  <td><a href="<?= clickfix_h(cfurl('extensions', false, ['client_id' => $cid])); ?>">ver</a></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          </div>
        </article>
        <article class="card">
          <h2>Historial de cliente</h2>
          <?php if ($selectedClientId === ''): ?>
            <p class="mut">Selecciona un cliente para ver historial de IPs y bloqueos.</p>
          <?php else: ?>
            <p class="mono">client_id: <?= clickfix_h($selectedClientId); ?></p>
            <?php
              $selectedMeta = null;
              foreach ($extensionClients as $metaCandidate) {
                  if ((string) ($metaCandidate['client_id'] ?? '') === $selectedClientId) {
                      $selectedMeta = $metaCandidate;
                      break;
                  }
              }
              $ipHistory = trim((string) ($selectedMeta['ip_history'] ?? ''));
            ?>
            <?php if ($selectedMeta !== null): ?>
              <p class="mono">VersiÃ³n: <?= clickfix_h((string) ($selectedMeta['extension_version'] ?? '')); ?> | Channel: <?= clickfix_h((string) ($selectedMeta['install_channel'] ?? '-')); ?> | Source: <?= clickfix_h((string) ($selectedMeta['install_source'] ?? '-')); ?></p>
            <?php endif; ?>
            <?php if (!empty($selectedClientLinks)): ?>
              <details>
                <summary>Usuarios web asociados</summary>
                <ul class="mini-list">
                  <?php foreach ($selectedClientLinks as $linkRow): ?>
                    <li>
                      <span><a class="user-link" href="<?= clickfix_h(cfprofileurl((int) ($linkRow['user_id'] ?? 0))); ?>"><?= clickfix_h((string) ($linkRow['username'] ?? 'user')); ?></a><?php if (!empty($linkRow['email'])): ?> (<?= clickfix_h((string) $linkRow['email']); ?>)<?php endif; ?></span>
                      <span class="mono"><?= clickfix_h((string) ($linkRow['role_label'] ?? '')); ?></span>
                    </li>
                  <?php endforeach; ?>
                </ul>
              </details>
            <?php endif; ?>
            <?php if ($ipHistory !== ''): ?>
              <details>
                <summary>Historial de IPs</summary>
                <pre class="mono"><?= clickfix_h(str_replace(',', PHP_EOL, $ipHistory)); ?></pre>
              </details>
            <?php endif; ?>
            <table>
              <thead><tr><th>Fecha</th><th>Dominio</th><th>IP</th><th>Bloqueado</th></tr></thead>
              <tbody>
                <?php foreach ($extensionClientEvents as $ev): ?>
                  <tr>
                    <td class="mono"><?= clickfix_h((string) ($ev['received_at'] ?? '')); ?></td>
                    <td class="mono"><?= clickfix_h((string) ($ev['hostname'] ?? '-')); ?></td>
                    <td class="mono"><?= clickfix_h((string) ($ev['ip'] ?? '-')); ?></td>
                    <td class="mono"><?= !empty($ev['blocked']) ? 'yes' : 'no'; ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
            <h3 style="margin-top:10px">Top dominios bloqueados</h3>
            <table>
              <thead><tr><th>Dominio</th><th>Bloqueos</th></tr></thead>
              <tbody>
                <?php foreach (array_slice($extensionBlockedDomains, 0, 15, true) as $domain => $hits): ?>
                  <tr>
                    <td class="mono"><?= clickfix_h((string) $domain); ?></td>
                    <td class="mono"><?= (int) $hits; ?></td>
                  </tr>
                <?php endforeach; ?>
                <?php if (empty($extensionBlockedDomains)): ?>
                  <tr><td colspan="2" class="mut">No hay bloqueos para este cliente.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
            <h3 style="margin-top:14px">Baseline privado del cliente</h3>
            <p class="mut">Solo guarda hosts habituales por <span class="mono">client_id</span>, con agregados de uso y revisiones. No expone rutas, querystrings ni identidad real.</p>
            <table>
              <thead><tr><th>Host</th><th>Trust</th><th>Visitas</th><th>Dias</th><th>Alertas</th><th>Bloq.</th><th>Revisiones</th><th>Estado</th></tr></thead>
              <tbody>
                <?php foreach ($selectedClientBaselineHosts as $baselineRow): ?>
                  <?php
                    $acceptedHits = (int) ($baselineRow['accepted_count'] ?? 0);
                    $rejectedHits = (int) ($baselineRow['rejected_count'] ?? 0);
                    $allowlistedHits = (int) ($baselineRow['allowlisted_count'] ?? 0);
                    $reviewSummary = 'A:' . $acceptedHits . ' / FP:' . $rejectedHits . ' / WL:' . $allowlistedHits;
                    $stateSummary = !empty($baselineRow['local_allowlisted'])
                        ? 'Allowlist local'
                        : ((string) ($baselineRow['last_verdict'] ?? '') !== '' ? (string) ($baselineRow['last_verdict'] ?? '') : '-');
                  ?>
                  <tr>
                    <td class="mono"><?= clickfix_h((string) ($baselineRow['hostname'] ?? '')); ?></td>
                    <td class="mono"><?= (int) ($baselineRow['trust_score'] ?? 0); ?></td>
                    <td class="mono"><?= (int) ($baselineRow['visits_count'] ?? 0); ?></td>
                    <td class="mono"><?= (int) ($baselineRow['days_seen'] ?? 0); ?></td>
                    <td class="mono"><?= (int) ($baselineRow['alert_count'] ?? 0); ?></td>
                    <td class="mono"><?= (int) ($baselineRow['blocked_count'] ?? 0); ?></td>
                    <td class="mono"><?= clickfix_h($reviewSummary); ?></td>
                    <td class="mono"><?= clickfix_h($stateSummary); ?></td>
                  </tr>
                <?php endforeach; ?>
                <?php if (empty($selectedClientBaselineHosts)): ?>
                  <tr><td colspan="8" class="mut">Todavia no hay baseline agregado para este cliente.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </article>
      </section>
      <section class="row">
        <article class="card">
          <h2>Hosts habituales y candidatos benignos</h2>
          <p class="mut">Este listado sirve para bajar falsos positivos sin rastrear usuarios reales: agrupa por host y por <span class="mono">client_id</span> pseudonimo.</p>
          <table class="table table-striped settings-table">
            <thead><tr><th>Host</th><th>Clientes</th><th>Trust medio</th><th>Visitas</th><th>Alertas</th><th>Aceptadas</th><th>FP</th><th>Allowlist</th><th>Ultimo seen</th></tr></thead>
            <tbody>
              <?php foreach ($globalBaselineCandidates as $candidate): ?>
                <tr>
                  <td class="mono"><?= clickfix_h((string) ($candidate['hostname'] ?? '')); ?></td>
                  <td class="mono"><?= (int) ($candidate['clients'] ?? 0); ?></td>
                  <td class="mono"><?= (int) round((float) ($candidate['avg_trust'] ?? 0)); ?></td>
                  <td class="mono"><?= (int) ($candidate['visits'] ?? 0); ?></td>
                  <td class="mono"><?= (int) ($candidate['alerts'] ?? 0); ?></td>
                  <td class="mono"><?= (int) ($candidate['accepted'] ?? 0); ?></td>
                  <td class="mono"><?= (int) ($candidate['rejected'] ?? 0); ?></td>
                  <td class="mono"><?= (int) ($candidate['allowlisted'] ?? 0); ?></td>
                  <td class="mono"><?= clickfix_h((string) ($candidate['last_seen_at'] ?? '')); ?></td>
                </tr>
              <?php endforeach; ?>
              <?php if (empty($globalBaselineCandidates)): ?>
                <tr><td colspan="9" class="mut">Sin datos globales de baseline todavia.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </article>
      </section>
      <section class="row">
        <article class="card">
          <h2>Asociar usuario web con extensi?n</h2>
          <p class="mut">Relaciona un usuario del dashboard con uno o varios <span class="mono">client_id</span> de extensión para mensajerÃ­a individual.</p>
          <form method="post">
            <input type="hidden" name="action" value="extension_link_add">
            <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
            <select name="link_user_id" required>
              <option value="">Seleccionar usuario web</option>
              <?php foreach ($usersDirectory as $directoryUser): ?>
                <option value="<?= (int) ($directoryUser['id'] ?? 0); ?>">
                  <a class="user-link" href="<?= clickfix_h(cfprofileurl((int) ($directoryUser['id'] ?? 0))); ?>"><?= clickfix_h((string) ($directoryUser['username'] ?? '')); ?></a>
                  <?php if (!empty($directoryUser['email'])): ?>
                    (<?= clickfix_h((string) $directoryUser['email']); ?>)
                  <?php endif; ?>
                </option>
              <?php endforeach; ?>
            </select>
            <input type="text" name="link_client_id" list="known-extension-clients" required placeholder="client_id de extensi?n" value="<?= clickfix_h($selectedClientId); ?>">
            <datalist id="known-extension-clients">
              <?php foreach ($extensionClients as $knownClient): ?>
                <?php $knownClientId = (string) ($knownClient['client_id'] ?? ''); ?>
                <?php if ($knownClientId !== ''): ?>
                  <option value="<?= clickfix_h($knownClientId); ?>"></option>
                <?php endif; ?>
              <?php endforeach; ?>
            </datalist>
            <input type="text" name="link_note" maxlength="280" placeholder="nota (opcional)">
            <button class="btn btn-primary btn-sm" type="submit">Guardar asociaci?n</button>
          </form>
        </article>
        <article class="card">
          <h2>Asociaci?nes activas</h2>
          <table class="table table-striped settings-table">
            <thead><tr><th>Usuario web</th><th>Client ID</th><th>Eventos</th><th>Bloqueos</th><th>Ãšltimo seen</th><th>Nota</th><th>AcciÃ³n</th></tr></thead>
            <tbody>
              <?php foreach ($extensionUserLinks as $link): ?>
                <tr>
                  <td>
                    <div class="mono"><a class="user-link" href="<?= clickfix_h(cfprofileurl((int) ($link['user_id'] ?? 0))); ?>"><?= clickfix_h((string) ($link['username'] ?? '')); ?></a></div>
                    <?php if (!empty($link['email'])): ?>
                      <div class="mut mono"><?= clickfix_h((string) $link['email']); ?></div>
                    <?php endif; ?>
                  </td>
                  <td class="mono"><?= clickfix_h((string) ($link['client_id'] ?? '')); ?></td>
                  <td class="mono"><?= (int) ($link['total_events'] ?? 0); ?></td>
                  <td class="mono"><?= (int) ($link['total_blocks'] ?? 0); ?></td>
                  <td class="mono"><?= clickfix_h((string) ($link['last_seen'] ?? '-')); ?></td>
                  <td><?= clickfix_h((string) ($link['note'] ?? '')); ?></td>
                  <td>
                    <form method="post">
                      <input type="hidden" name="action" value="extension_link_remove">
                      <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                      <input type="hidden" name="link_id" value="<?= (int) ($link['id'] ?? 0); ?>">
                      <button class="btn btn-primary btn-sm" type="submit">Quitar</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if (empty($extensionUserLinks)): ?>
                <tr><td colspan="7" class="mut">Sin asociaci?nes activas.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </article>
      </section>
    <?php endif; ?>

    <?php if ($loggedIn && $page === 'domain_feeds' && cfcan($user, 'analyst_mid')): ?>
      <?php
        require_once __DIR__ . '/src/clickfix_domain_feeds.php';
        clickfix_domain_feeds_ensure_table($pdo);
        $feedStats = clickfix_domain_feeds_get_stats($pdo);
        $feedEntries = clickfix_domain_feeds_get_entries($pdo, 200);
        $feedLog = clickfix_domain_feeds_log_recent($pdo, 10);
      ?>
      <section class="row">
        <article class="card">
          <div class="settings-head">
            <div>
              <h2>ClickFix Domain Feeds</h2>
              <p class="text-muted">External domain intelligence from GitHub Gist (cdup07) and Carson ClickFix tracker. Auto-fetched every 12-24h. Import to blocklist with one click.</p>
            </div>
            <span class="settings-pill">Intel</span>
          </div>
          <div class="intel-kpi-grid" style="margin:14px 0">
            <div class="intel-kpi"><b>Total Domains</b><span><?= (int) ($feedStats['total'] ?? 0); ?></span></div>
            <div class="intel-kpi"><b>Imported</b><span><?= (int) ($feedStats['imported'] ?? 0); ?></span></div>
            <div class="intel-kpi"><b>Pending Import</b><span><?= (int) ($feedStats['not_imported'] ?? 0); ?></span></div>
            <?php foreach (($feedStats['by_source'] ?? []) as $src): ?>
              <div class="intel-kpi"><b><?= clickfix_h((string) ($src['source_label'] ?? $src['source_key'] ?? '')); ?></b><span><?= (int) ($src['cnt'] ?? 0); ?></span></div>
            <?php endforeach; ?>
          </div>
          <div class="intel-toolbar" style="margin-bottom:12px">
            <form method="post" style="display:flex;gap:8px;align-items:center">
              <input type="hidden" name="action" value="domain_feed_fetch">
              <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
              <button class="btn btn-primary btn-sm" type="submit">Fetch Now</button>
            </form>
            <form method="post" style="display:flex;gap:8px;align-items:center">
              <input type="hidden" name="action" value="domain_feed_import_all">
              <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
              <button class="btn btn-sm" type="submit">Import All New to Blocklist</button>
            </form>
          </div>
          <div class="analytics-table-wrap" style="max-height:540px;overflow-y:auto">
            <table class="compact-table">
              <thead><tr><th>Domain</th><th>Source</th><th>First Seen</th><th>Last Seen</th><th>Hits</th><th>Imported</th><th>Action</th></tr></thead>
              <tbody>
                <?php foreach ($feedEntries as $entry): ?>
                  <tr>
                    <td class="mono"><?= clickfix_h((string) ($entry['domain'] ?? '')); ?></td>
                    <td><?= clickfix_h((string) ($entry['source_label'] ?? '')); ?></td>
                    <td class="mono"><?= clickfix_h((string) ($entry['first_seen'] ?? '')); ?></td>
                    <td class="mono"><?= clickfix_h((string) ($entry['last_seen'] ?? '')); ?></td>
                    <td class="mono"><?= (int) ($entry['hits'] ?? 0); ?></td>
                    <td><?= !empty($entry['imported_to_blocklist']) ? '<span class="badge accepted">YES</span>' : '<span class="badge pending">NO</span>'; ?></td>
                    <td>
                      <?php if (empty($entry['imported_to_blocklist'])): ?>
                        <div style="display:flex;gap:6px">
                          <form method="post">
                            <input type="hidden" name="action" value="domain_feed_import_one">
                            <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                            <input type="hidden" name="feed_entry_id" value="<?= (int) ($entry['id'] ?? 0); ?>">
                            <button class="btn btn-primary btn-sm" type="submit">Block</button>
                          </form>
                          <?php $entryUrl = (string) ($entry['url'] ?? ''); if ($entryUrl !== ''): ?>
                            <a class="btn btn-sm" href="<?= clickfix_h($entryUrl); ?>" target="_blank" rel="noopener">Details</a>
                          <?php endif; ?>
                        </div>
                      <?php else: ?>
                        <a class="btn btn-sm" href="<?= clickfix_h(cfurl('search', false, ['q' => (string) ($entry['domain'] ?? '')])); ?>">Search</a>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
                <?php if (empty($feedEntries)): ?>
                  <tr><td colspan="7" class="mut">No domain feeds fetched yet. Click "Fetch Now" to pull data from external sources.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </article>
        <?php if (!empty($feedLog)): ?>
        <article class="card" style="margin-top:14px">
          <h3>Fetch History</h3>
          <table class="compact-table">
            <thead><tr><th>Source</th><th>Status</th><th>Items</th><th>New</th><th>Error</th><th>Fetched At</th></tr></thead>
            <tbody>
              <?php foreach ($feedLog as $logEntry): ?>
                <tr>
                  <td class="mono"><?= clickfix_h((string) ($logEntry['source_key'] ?? '')); ?></td>
                  <td><span class="badge <?= ($logEntry['status'] ?? 'ok') === 'ok' ? 'accepted' : 'rejected'; ?>"><?= clickfix_h((string) ($logEntry['status'] ?? 'ok')); ?></span></td>
                  <td class="mono"><?= (int) ($logEntry['items_fetched'] ?? 0); ?></td>
                  <td class="mono"><?= (int) ($logEntry['items_new'] ?? 0); ?></td>
                  <td><?= clickfix_h((string) ($logEntry['error'] ?? '')); ?></td>
                  <td class="mono"><?= clickfix_h((string) ($logEntry['fetched_at'] ?? '')); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </article>
        <?php endif; ?>
      </section>
    <?php endif; ?>

    <?php if ($loggedIn && $page === 'lists' && cfcan($user, 'analyst_sr')): ?>
      <section class="row">
        <article class="card">
          <h2>Gestion de listas</h2>
          <p class="mut">Acciones individuales y masivas sobre allowlist, blacklist, alertlist e investigatelist.</p>
          <form method="post">
            <input type="hidden" name="action" value="list_action">
            <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
            <select name="list_type">
              <option value="blocklist">blocklist</option>
              <option value="allowlist">allowlist</option>
              <option value="alertlist">alertlist</option>
              <option value="investigatelist">investigatelist</option>
            </select>
            <select name="operation">
              <option value="add">add</option>
              <option value="remove">remove</option>
            </select>
            <input type="text" name="domain" required placeholder="domain.tld">
            <input type="text" name="reason" value="manual dashboard update">
            <button class="btn btn-primary btn-sm" type="submit">Aplicar acciÃ³n individual</button>
          </form>
          <hr>
          <form method="post">
            <input type="hidden" name="action" value="list_bulk_action">
            <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
            <select name="list_type">
              <option value="blocklist">blocklist</option>
              <option value="allowlist">allowlist</option>
              <option value="alertlist">alertlist</option>
              <option value="investigatelist">investigatelist</option>
            </select>
            <select name="operation">
              <option value="add">add</option>
              <option value="remove">remove</option>
            </select>
            <textarea name="domains_raw" placeholder="uno por linea o separados por coma&#10;bad-example.tld&#10;phishing.site"></textarea>
            <input type="text" name="reason" value="bulk dashboard update">
            <button class="btn btn-primary btn-sm" type="submit">Aplicar acciÃ³n masiva</button>
          </form>
        </article>
        <article class="card">
          <h2>Estado actual</h2>
          <div class="split">
            <div>
              <h3 class="mono">blocklist</h3>
              <pre class="mono"><?= clickfix_h(implode(PHP_EOL, array_slice($lists['blocklist'], 0, 40))); ?></pre>
            </div>
            <div>
              <h3 class="mono">allowlist</h3>
              <pre class="mono"><?= clickfix_h(implode(PHP_EOL, array_slice($lists['allowlist'], 0, 40))); ?></pre>
            </div>
            <div>
              <h3 class="mono">alertlist</h3>
              <pre class="mono"><?= clickfix_h(implode(PHP_EOL, array_slice($lists['alertlist'], 0, 40))); ?></pre>
            </div>
          </div>
          <div style="margin-top:10px">
            <h3 class="mono">investigatelist</h3>
            <pre class="mono"><?= clickfix_h(implode(PHP_EOL, array_slice($lists['investigatelist'], 0, 60))); ?></pre>
          </div>
        </article>
      </section>
      <section class="card">
        <h2>Alertas fuera de allowlist/blocklist</h2>
        <p class="mut">Dominios detectados en alertas que no estan cubiertos por allowlist ni blocklist, para triage r?pido.</p>
        <table class="table table-striped settings-table">
          <thead><tr><th>Dominio</th><th>Alertas</th><th>Ãšltima alerta</th><th>Acciones</th></tr></thead>
          <tbody>
            <?php foreach ($unlistedAlertDomains as $row): ?>
              <?php $pendingDomain = (string) ($row['hostname'] ?? ''); ?>
              <tr>
                <td class="mono"><?= clickfix_h($pendingDomain); ?></td>
                <td class="mono"><?= (int) ($row['alerts'] ?? 0); ?></td>
                <td class="mono"><?= clickfix_h((string) ($row['last_seen'] ?? '')); ?></td>
                <td class="mono">
                  <form method="post" style="display:inline-block">
                    <input type="hidden" name="action" value="list_action">
                    <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                    <input type="hidden" name="list_type" value="blocklist">
                    <input type="hidden" name="operation" value="add">
                    <input type="hidden" name="domain" value="<?= clickfix_h($pendingDomain); ?>">
                    <input type="hidden" name="reason" value="triage pending alert domain">
                    <button class="btn btn-primary btn-sm" type="submit">Bloquear</button>
                  </form>
                  <form method="post" style="display:inline-block">
                    <input type="hidden" name="action" value="list_action">
                    <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                    <input type="hidden" name="list_type" value="allowlist">
                    <input type="hidden" name="operation" value="add">
                    <input type="hidden" name="domain" value="<?= clickfix_h($pendingDomain); ?>">
                    <input type="hidden" name="reason" value="triage pending alert domain">
                    <button class="btn btn-primary btn-sm" type="submit">Permitir</button>
                  </form>
                  <form method="post" style="display:inline-block">
                    <input type="hidden" name="action" value="list_action">
                    <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                    <input type="hidden" name="list_type" value="investigatelist">
                    <input type="hidden" name="operation" value="add">
                    <input type="hidden" name="domain" value="<?= clickfix_h($pendingDomain); ?>">
                    <input type="hidden" name="reason" value="triage pending alert domain">
                    <button class="btn btn-primary btn-sm" type="submit">Investigar</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (empty($unlistedAlertDomains)): ?>
              <tr><td colspan="4" class="mut">No hay dominios pendientes fuera de allowlist/blocklist.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </section>
      <section class="card">
        <h2>Pendientes detallados fuera de allowlist/blocklist</h2>
        <p class="mut">Vista por alerta para ver exactamente que queda pendiente y abrirlo en Operaciones.</p>
        <input class="analytics-search" id="pending-outside-search" type="text" placeholder="Buscar pendiente (id, fecha, dominio, mensaje, tipo...)">
        <div class="analytics-table-wrap">
          <table class="compact-table">
            <thead><tr><th>ID</th><th>Fecha</th><th>Dominio</th><th>Score</th><th>Tipo</th><th>Bloqueado</th><th>Mensaje</th><th>AcciÃ³n</th></tr></thead>
            <tbody id="pending-outside-body">
              <?php foreach (array_slice($pendingOutsideReports, 0, 260) as $pendingRow): ?>
                <?php $pendingReportId = (int) ($pendingRow['id'] ?? 0); ?>
                <tr data-pending-outside-row="1">
                  <td class="mono"><?= $pendingReportId; ?></td>
                  <td class="mono"><?= clickfix_h((string) ($pendingRow['received_at'] ?? '')); ?></td>
                  <td class="mono"><?= clickfix_h((string) ($pendingRow['hostname'] ?? '')); ?></td>
                  <td class="mono"><?= (int) ($pendingRow['score_total'] ?? 0); ?></td>
                  <td class="mono"><?= clickfix_h((string) ($pendingRow['event_type'] ?? 'clickfix_alert')); ?></td>
                  <td class="mono"><?= !empty($pendingRow['blocked']) ? 'yes' : 'no'; ?></td>
                  <td><?= clickfix_h((string) ($pendingRow['message'] ?? '')); ?></td>
                  <td><a class="btn" href="<?= clickfix_h(cfurl('ops', false, ['report_id' => (string) $pendingReportId])); ?>">Abrir</a></td>
                </tr>
              <?php endforeach; ?>
              <?php if (empty($pendingOutsideReports)): ?>
                <tr><td colspan="8" class="mut">No hay alertas pendientes fuera de allowlist/blocklist.</td></tr>
              <?php endif; ?>
              <tr id="pending-outside-empty" hidden><td colspan="8" class="mut">Sin coincidencias para el filtro.</td></tr>
            </tbody>
          </table>
        </div>
      </section>
      <section class="card"><h2>Auditoria</h2><table><thead><tr><th>Fecha</th><th>Usuario</th><th>Tipo</th><th>AcciÃ³n</th><th>Dominio</th></tr></thead><tbody><?php foreach ($actions as $a): ?><tr><td class="mono"><?= clickfix_h((string) ($a['created_at'] ?? '')); ?></td><td><?php if (!empty($a['user_id'])): ?><a class="user-link" href="<?= clickfix_h(cfprofileurl((int) ($a['user_id'] ?? 0))); ?>"><?= clickfix_h((string) ($a['username'] ?? 'system')); ?></a><?php else: ?><?= clickfix_h((string) ($a['username'] ?? 'system')); ?><?php endif; ?></td><td class="mono"><?= clickfix_h((string) ($a['list_type'] ?? '')); ?></td><td class="mono"><?= clickfix_h((string) ($a['action'] ?? '')); ?></td><td class="mono"><?= clickfix_h((string) ($a['domain'] ?? '')); ?></td></tr><?php endforeach; ?></tbody></table></section>
    <?php endif; ?>

    <?php if ($loggedIn && $page === 'messaging' && cfcan($user, 'analyst_sr')): ?>
      <section class="row">
        <article class="card">
          <h2><?= clickfix_h(cft('msg_title')); ?></h2>
          <p class="mut">Envia avisos operativos en modo masivo o segmentado: por uno o varios <span class="mono">client_id</span>, por uno o varios usuarios web, solo asociadas o solo no asociadas.</p>
          <form method="post">
            <input type="hidden" name="action" value="message_send">
            <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
            <select name="msg_scope" id="msg-scope">
              <option value="all">Masivo (todos)</option>
              <option value="client">Por extensi?n ID (uno o varios)</option>
              <option value="user">Por usuario web (uno o varios)</option>
              <option value="linked">Todas las extensiones con usuario</option>
              <option value="unlinked">No asociadas (sin usuario web)</option>
            </select>
            <input type="hidden" name="msg_client_id" value="">
            <input type="hidden" name="msg_user_id" value="0">
            <input type="text" name="msg_client_ids_raw" id="msg-client-ids" list="known-extension-clients-msg" placeholder="client_id(s), separados por coma o espacio (scope=client)">
            <datalist id="known-extension-clients-msg">
              <?php foreach ($extensionClients as $knownClient): ?>
                <?php $knownClientId = (string) ($knownClient['client_id'] ?? ''); ?>
                <?php if ($knownClientId !== ''): ?>
                  <option value="<?= clickfix_h($knownClientId); ?>"></option>
                <?php endif; ?>
              <?php endforeach; ?>
            </datalist>
            <select name="msg_user_ids[]" id="msg-user-ids" multiple size="7">
              <?php foreach ($messagingUserTargets as $targetUser): ?>
                <?php
                  $targetUserId = (int) ($targetUser['user_id'] ?? 0);
                  $targetUserName = (string) ($targetUser['username'] ?? '');
                  $targetUserEmail = (string) ($targetUser['email'] ?? '');
                  $targetUserClients = is_array($targetUser['client_ids'] ?? null) ? $targetUser['client_ids'] : [];
                ?>
                <option value="<?= $targetUserId; ?>">
                  <?= clickfix_h($targetUserName); ?><?php if ($targetUserEmail !== ''): ?> (<?= clickfix_h($targetUserEmail); ?>)<?php endif; ?> - <?= count($targetUserClients); ?> client(s)
                </option>
              <?php endforeach; ?>
            </select>
            <input type="text" name="msg_title" maxlength="180" required placeholder="Título del mensaje">
            <textarea name="msg_body" maxlength="5000" required placeholder="Contenido"></textarea>
            <select name="msg_severity">
              <option value="info">info</option>
              <option value="warning">warning</option>
              <option value="critical">critical</option>
            </select>
            <label for="msg-expires-at">Fecha fin (no mostrar despues de este dia)</label>
            <input type="date" name="msg_expires_at" id="msg-expires-at" min="<?= clickfix_h(gmdate('Y-m-d')); ?>" value="<?= clickfix_h(gmdate('Y-m-d', time() + 7 * 86400)); ?>">
            <label for="msg-expires-days">Dias de vigencia (fallback si no hay fecha fin)</label>
            <input type="number" name="msg_expires_days" min="1" max="90" value="7">
            <button class="btn btn-primary btn-sm" type="submit">Enviar mensaje</button>
          </form>
          <p class="mut" style="margin-top:8px">Si seleccionas <b>scope=client</b>, puedes indicar uno o varios <span class="mono">client_id</span> separados por coma o espacio.</p>
          <p class="mut">Si seleccionas <b>scope=user</b>, puedes seleccionar uno o varios usuarios; se notificara a todos sus clientes asociados.</p>
          <p class="mut">Si seleccionas <b>scope=linked</b>, se notificara a todas las extensiones con usuario asociado (actualmente: <b><?= (int) $linkedExtensionClientCount; ?></b>).</p>
          <p class="mut">Si seleccionas <b>scope=unlinked</b>, se notificara a extensiones sin usuario asociado (actualmente: <b><?= (int) $unlinkedExtensionClientCount; ?></b>).</p>
          <p class="mut">La <b>fecha fin</b> aplica a cualquier scope; pasada esa fecha, el mensaje deja de entregarse a la extensi?n.</p>
        </article>
        <article class="card">
          <h2>Historial de mensajes</h2>
          <form method="post" style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;margin-bottom:10px">
            <input type="hidden" name="action" value="message_history_clear">
            <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
            <label style="display:grid;gap:4px">
              <span class="mut-mini">Limpieza</span>
              <select name="clear_mode">
                <option value="inactive">Solo inactivos/expirados</option>
                <option value="all">Todo el historial</option>
              </select>
            </label>
            <button class="btn btn-primary btn-sm" type="submit" onclick="return confirm('Limpiar historial de mensajes?');">Limpiar historial</button>
          </form>
          <table class="table table-striped settings-table">
            <thead><tr><th>Fecha</th><th>Scope</th><th>Target</th><th>Severidad</th><th>Título</th><th>Expira</th><th>Activo</th><th>By</th><th>AcciÃ³n</th></tr></thead>
            <tbody>
              <?php foreach ($extensionMessages as $msg): ?>
                <?php
                  $msgId = (int) ($msg['id'] ?? 0);
                  $msgTitle = (string) ($msg['title'] ?? '');
                  $msgBody = (string) ($msg['body'] ?? '');
                  $msgSeverity = strtolower(trim((string) ($msg['severity'] ?? 'info')));
                  if (!in_array($msgSeverity, ['info', 'warning', 'critical'], true)) {
                      $msgSeverity = 'info';
                  }
                  $msgExpiresAt = trim((string) ($msg['expires_at'] ?? ''));
                  $msgExpiresDate = (preg_match('/^\d{4}-\d{2}-\d{2}/', $msgExpiresAt) === 1) ? substr($msgExpiresAt, 0, 10) : gmdate('Y-m-d', time() + 7 * 86400);
                  $msgActive = !empty($msg['active']);
                ?>
                <tr>
                  <td class="mono"><?= clickfix_h((string) ($msg['created_at'] ?? '')); ?></td>
                  <td class="mono"><?= clickfix_h((string) ($msg['target_scope'] ?? '')); ?></td>
                  <td class="mono"><?= clickfix_h((string) ($msg['target_client_id'] ?? '-')); ?></td>
                  <td class="mono"><?= clickfix_h($msgSeverity); ?></td>
                  <td><?= clickfix_h($msgTitle); ?></td>
                  <td class="mono"><?= clickfix_h($msgExpiresAt); ?></td>
                  <td class="mono"><?= $msgActive ? '1' : '0'; ?></td>
                  <td class="mono"><?php if (!empty($msg['created_by'])): ?><a class="user-link" href="<?= clickfix_h(cfprofileurl((int) ($msg['created_by'] ?? 0))); ?>"><?= clickfix_h((string) ($msg['created_by_username'] ?? '-')); ?></a><?php else: ?><?= clickfix_h((string) ($msg['created_by_username'] ?? '-')); ?><?php endif; ?></td>
                  <td class="mono">
                    <?php if ($msgActive): ?>
                      <form method="post" onsubmit="return confirm('Detener entrega de este mensaje?');" style="margin-bottom:6px">
                        <input type="hidden" name="action" value="message_delete">
                        <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                        <input type="hidden" name="message_id" value="<?= $msgId; ?>">
                        <button class="btn btn-primary btn-sm" type="submit">Detener entrega</button>
                      </form>
                    <?php else: ?><span class="mut">inactivo</span><?php endif; ?>
                    <form method="post" onsubmit="return confirm('Eliminar este mensaje definitivamente de la plataforma?');" style="margin-bottom:6px">
                      <input type="hidden" name="action" value="message_hard_delete">
                      <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                      <input type="hidden" name="message_id" value="<?= $msgId; ?>">
                      <button class="btn btn-primary btn-sm" type="submit">Eliminar de plataforma</button>
                    </form>
                    <details style="margin-top:6px">
                      <summary>Rectificar</summary>
                      <form method="post" style="display:grid;gap:6px;margin-top:6px;min-width:260px">
                        <input type="hidden" name="action" value="message_edit">
                        <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                        <input type="hidden" name="message_id" value="<?= $msgId; ?>">
                        <input type="text" name="msg_title" maxlength="180" value="<?= clickfix_h($msgTitle); ?>" required>
                        <textarea name="msg_body" maxlength="5000" rows="4" required><?= clickfix_h($msgBody); ?></textarea>
                        <select name="msg_severity">
                          <option value="info"<?= $msgSeverity === 'info' ? ' selected' : ''; ?>>info</option>
                          <option value="warning"<?= $msgSeverity === 'warning' ? ' selected' : ''; ?>>warning</option>
                          <option value="critical"<?= $msgSeverity === 'critical' ? ' selected' : ''; ?>>critical</option>
                        </select>
                        <input type="date" name="msg_expires_at" value="<?= clickfix_h($msgExpiresDate); ?>">
                        <input type="hidden" name="msg_expires_days" value="7">
                        <select name="msg_active">
                          <option value="1"<?= $msgActive ? ' selected' : ''; ?>>activo</option>
                          <option value="0"<?= !$msgActive ? ' selected' : ''; ?>>inactivo (no enviar)</option>
                        </select>
                        <button class="btn btn-primary btn-sm" type="submit">Guardar rectificacion</button>
                      </form>
                    </details>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if (empty($extensionMessages)): ?>
                <tr><td colspan="9" class="mut">Sin mensajes en historial.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </article>
      </section>
    <?php endif; ?>

    <?php if ($loggedIn && $page === 'data_center' && cfcan($user, 'analyst_sr')): ?>
      <section class="card">
        <h2><?= clickfix_h(cft('dc_title')); ?></h2>
        <p class="mut"><?= clickfix_h(cft('dc_sub')); ?></p>
        <table class="table table-striped settings-table">
          <thead><tr><th>Tabla</th><th>Registros</th><th>Ãšltima actividad</th><th>Abrir</th></tr></thead>
          <tbody>
            <?php foreach ($dataCenterSnapshot as $snapshot): ?>
              <?php $tableName = (string) ($snapshot['table'] ?? ''); ?>
              <tr>
                <td class="mono"><?= clickfix_h($tableName); ?></td>
                <td class="mono"><?= (int) ($snapshot['rows'] ?? 0); ?></td>
                <td class="mono"><?= clickfix_h((string) ($snapshot['latest'] ?? '')); ?></td>
                <td><a href="<?= clickfix_h(cfurl('data_center', false, ['table' => $tableName])); ?>">ver filas</a></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </section>
      <section class="card">
        <h2>Vista de tabla: <span class="mono"><?= clickfix_h($dataTable); ?></span></h2>
        <div style="overflow:auto">
          <table class="table table-striped settings-table">
            <thead>
              <tr>
                <?php if (!empty($dataCenterRows)): ?>
                  <?php foreach (array_keys((array) $dataCenterRows[0]) as $header): ?>
                    <th><?= clickfix_h((string) $header); ?></th>
                  <?php endforeach; ?>
                <?php else: ?>
                  <th>Sin datos</th>
                <?php endif; ?>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($dataCenterRows as $row): ?>
                <tr>
                  <?php foreach ((array) $row as $field => $value): ?>
                    <?php
                      $fieldName = strtolower((string) $field);
                      $isSensitive = (strpos($fieldName, 'password') !== false)
                          || (strpos($fieldName, 'token') !== false)
                          || (strpos($fieldName, 'secret') !== false)
                          || (strpos($fieldName, 'hash') !== false);
                      $cellValue = $isSensitive
                          ? '[redacted]'
                          : (is_scalar($value) || $value === null
                              ? (string) $value
                              : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                    ?>
                    <td class="mono"><?= clickfix_h((string) $cellValue); ?></td>
                  <?php endforeach; ?>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
    <?php endif; ?>

    <?php if ($loggedIn && $page === 'configs' && cfcan($user, 'admin')): ?>
      <section class="row">
        <article class="card">
          <h2><?= clickfix_h(cft('cfg_title')); ?> (basic)</h2>
          <p class="mut">Editor JSON del score config no premium.</p>
          <form method="post">
            <input type="hidden" name="action" value="score_config_save">
            <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
            <input type="hidden" name="config_tier" value="basic">
            <textarea name="config_json" style="min-height:420px"><?= clickfix_h($scoreConfigBasicJson); ?></textarea>
            <button class="btn btn-primary btn-sm" type="submit">Guardar basic</button>
          </form>
        </article>
        <article class="card">
          <h2><?= clickfix_h(cft('cfg_title')); ?> (premium)</h2>
          <p class="mut">Editor JSON del score config premium.</p>
          <form method="post">
            <input type="hidden" name="action" value="score_config_save">
            <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
            <input type="hidden" name="config_tier" value="premium">
            <textarea name="config_json" style="min-height:420px"><?= clickfix_h($scoreConfigPremiumJson); ?></textarea>
            <button class="btn btn-primary btn-sm" type="submit">Guardar premium</button>
          </form>
        </article>
      </section>
      <section class="row">
        <article class="card">
          <h2>Politica global de anuncios internos</h2>
          <p class="mut">Controla si los anuncios se muestran en index/dashboard y a que perfiles se les permite verlos. Por defecto: guest, junior y mid si; senior y admin no.</p>
          <form method="post">
            <input type="hidden" name="action" value="internal_ad_settings_save">
            <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
            <label><input type="checkbox" name="ads_enabled_global" value="1"<?= !empty($internalAdSettings['enabled_global']) ? ' checked' : ''; ?>> Activar anuncios internos</label><br>
            <label><input type="checkbox" name="ads_show_guest" value="1"<?= !empty($internalAdSettings['show_guest']) ? ' checked' : ''; ?>> Mostrar a guest</label><br>
            <label><input type="checkbox" name="ads_show_analyst_jr" value="1"<?= !empty($internalAdSettings['show_analyst_jr']) ? ' checked' : ''; ?>> Mostrar a analyst_jr</label><br>
            <label><input type="checkbox" name="ads_show_analyst_mid" value="1"<?= !empty($internalAdSettings['show_analyst_mid']) ? ' checked' : ''; ?>> Mostrar a analyst_mid</label><br>
            <label><input type="checkbox" name="ads_show_analyst_sr" value="1"<?= !empty($internalAdSettings['show_analyst_sr']) ? ' checked' : ''; ?>> Mostrar a analyst_sr</label><br>
            <label><input type="checkbox" name="ads_show_admin" value="1"<?= !empty($internalAdSettings['show_admin']) ? ' checked' : ''; ?>> Mostrar a admin</label><br>
            <button class="btn btn-primary btn-sm" type="submit">Guardar politica</button>
          </form>
        </article>
        <article class="card">
          <h2>Crear anuncio interno</h2>
          <p class="mut">Crea slots de prueba o anuncios reales con placement y targeting por rol. No depende de terceros.</p>
          <form method="post">
            <input type="hidden" name="action" value="internal_ad_save">
            <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
            <input type="text" name="ad_title" maxlength="180" required placeholder="Título del anuncio">
            <textarea name="ad_body" maxlength="2000" required placeholder="Texto del anuncio"></textarea>
            <div class="split">
              <select name="ad_placement">
                <option value="both">both</option>
                <option value="index">index</option>
                <option value="dashboard">dashboard</option>
              </select>
              <select name="ad_theme">
                <option value="cyan">cyan</option>
                <option value="lime">lime</option>
                <option value="amber">amber</option>
                <option value="fuchsia">fuchsia</option>
              </select>
              <input type="number" name="ad_priority" value="100" min="0" max="10000" placeholder="priority">
            </div>
            <div class="split">
              <input type="text" name="ad_cta_label" maxlength="80" placeholder="CTA label">
              <input type="url" name="ad_cta_url" maxlength="500" placeholder="https://...">
              <select name="ad_active">
                <option value="1">activo</option>
                <option value="0">inactivo</option>
              </select>
            </div>
            <div class="split">
              <input type="datetime-local" name="ad_starts_at" placeholder="Inicio">
              <input type="datetime-local" name="ad_expires_at" placeholder="Fin">
              <div></div>
            </div>
            <div class="rbac" style="grid-template-columns:repeat(5,minmax(0,1fr))">
              <label class="item"><b><input type="checkbox" name="ad_target_guest" value="1" checked> guest</b><span>Index y PÃºblico</span></label>
              <label class="item"><b><input type="checkbox" name="ad_target_analyst_jr" value="1" checked> analyst_jr</b><span>Junior</span></label>
              <label class="item"><b><input type="checkbox" name="ad_target_analyst_mid" value="1" checked> analyst_mid</b><span>Mid</span></label>
              <label class="item"><b><input type="checkbox" name="ad_target_analyst_sr" value="1"> analyst_sr</b><span>Senior</span></label>
              <label class="item"><b><input type="checkbox" name="ad_target_admin" value="1"> admin</b><span>Administrador</span></label>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px">
              <button class="btn btn-primary btn-sm" type="submit">Guardar anuncio</button>
            </div>
          </form>
          <form method="post" style="margin-top:10px">
            <input type="hidden" name="action" value="internal_ads_seed_test">
            <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
            <button class="btn btn-primary btn-sm" type="submit">Generar anuncios de test</button>
          </form>
        </article>
      </section>
      <section class="card">
        <h2>Inventario de anuncios internos</h2>
        <table class="table table-striped settings-table">
          <thead><tr><th>ID</th><th>TÃ­tulo</th><th>Placement</th><th>Targets</th><th>Priority</th><th>Ventana</th><th>Activo</th><th>By</th><th>Acciones</th></tr></thead>
          <tbody>
            <?php foreach ($internalAdsAdminList as $adRow): ?>
              <?php
                $targets = [];
                if (!empty($adRow['target_guest'])) { $targets[] = 'guest'; }
                if (!empty($adRow['target_analyst_jr'])) { $targets[] = 'jr'; }
                if (!empty($adRow['target_analyst_mid'])) { $targets[] = 'mid'; }
                if (!empty($adRow['target_analyst_sr'])) { $targets[] = 'sr'; }
                if (!empty($adRow['target_admin'])) { $targets[] = 'admin'; }
              ?>
              <tr>
                <td class="mono"><?= (int) ($adRow['id'] ?? 0); ?></td>
                <td>
                  <div><b><?= clickfix_h((string) ($adRow['title'] ?? '')); ?></b></div>
                  <div class="mut"><?= clickfix_h((string) ($adRow['body'] ?? '')); ?></div>
                </td>
                <td class="mono"><?= clickfix_h((string) ($adRow['placement'] ?? 'both')); ?></td>
                <td class="mono"><?= clickfix_h(implode(', ', $targets)); ?></td>
                <td class="mono"><?= (int) ($adRow['priority'] ?? 0); ?></td>
                <td class="mono"><?= clickfix_h(trim((string) ($adRow['starts_at'] ?? '')) . ' -> ' . trim((string) ($adRow['expires_at'] ?? ''))); ?></td>
                <td class="mono"><?= !empty($adRow['active']) ? '1' : '0'; ?></td>
                <td class="mono"><?= clickfix_h((string) ($adRow['created_by_username'] ?? '-')); ?></td>
                <td class="mono">
                  <form method="post" style="display:inline-block;margin-bottom:6px">
                    <input type="hidden" name="action" value="internal_ad_toggle">
                    <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                    <input type="hidden" name="ad_id" value="<?= (int) ($adRow['id'] ?? 0); ?>">
                    <input type="hidden" name="ad_active" value="<?= !empty($adRow['active']) ? '0' : '1'; ?>">
                    <button class="btn btn-primary btn-sm" type="submit"><?= !empty($adRow['active']) ? 'Desactivar' : 'Activar'; ?></button>
                  </form>
                  <form method="post" style="display:inline-block" onsubmit="return confirm('Eliminar anuncio interno?');">
                    <input type="hidden" name="action" value="internal_ad_delete">
                    <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                    <input type="hidden" name="ad_id" value="<?= (int) ($adRow['id'] ?? 0); ?>">
                    <button class="btn btn-primary btn-sm" type="submit">Eliminar</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (empty($internalAdsAdminList)): ?>
              <tr><td colspan="9" class="mut">Sin anuncios internos.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </section>
    <?php endif; ?>

    <?php if ($loggedIn && $page === 'reports' && cfcan($user, 'admin')): ?>
      <section class="row">
        <article class="card">
          <h2>Panel de borrado confidencial</h2>
          <p class="mut">Elimina alertas y evidencias asociadas a un dominio. Esta acciÃ³n es irreversible.</p>
          <form method="post" class="stack" onsubmit="return confirm('Esta acciÃ³n elimina reportes y caches del dominio. Â¿Continuar?');">
            <input type="hidden" name="action" value="domain_purge">
            <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
            <input type="text" name="purge_domain" placeholder="dominio o URL (ej: ejemplo.com)" required>
            <label class="item"><b><input type="checkbox" name="purge_include_subdomains" value="1" checked> incluir subdominios</b><span>borra alertas de *.dominio</span></label>
            <label class="item"><b><input type="checkbox" name="purge_include_url" value="1" checked> borrar coincidencias en URL</b><span>URL contiene el dominio</span></label>
            <label class="item"><b><input type="checkbox" name="purge_include_previous_url" value="1" checked> borrar coincidencias en URL previa</b><span>previous_url contiene el dominio</span></label>
            <label class="item"><b><input type="checkbox" name="purge_delete_caches" value="1" checked> borrar caches de dominio</b><span>domain_intel_cache y whatweb_cache</span></label>
            <label class="item"><b><input type="checkbox" name="purge_delete_investigations" value="1"> borrar investigaciones con site_domain</b><span>borra investigaciones internas ligadas al dominio</span></label>
            <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:6px">
              <button class="btn btn-primary btn-sm" type="submit">Borrar dominio</button>
            </div>
          </form>
        </article>
        <article class="card">
          <h2>Dominios frecuentes</h2>
          <table class="table table-striped settings-table">
            <thead><tr><th>Dominio</th><th>Alertas</th><th>Ãšltima vez</th><th>AcciÃ³n</th></tr></thead>
            <tbody>
              <?php foreach ($purgeDomainCandidates as $row): ?>
                <tr>
                  <td class="mono"><?= clickfix_h((string) ($row['hostname'] ?? '')); ?></td>
                  <td class="mono"><?= (int) ($row['hits'] ?? 0); ?></td>
                  <td class="mono"><?= clickfix_h((string) ($row['last_seen'] ?? '')); ?></td>
                  <td>
                    <form method="post" onsubmit="return confirm('Eliminar reportes del dominio <?= clickfix_h((string) ($row['hostname'] ?? '')); ?>?');">
                      <input type="hidden" name="action" value="domain_purge">
                      <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                      <input type="hidden" name="purge_domain" value="<?= clickfix_h((string) ($row['hostname'] ?? '')); ?>">
                      <input type="hidden" name="purge_include_subdomains" value="1">
                      <input type="hidden" name="purge_include_url" value="1">
                      <input type="hidden" name="purge_include_previous_url" value="1">
                      <input type="hidden" name="purge_delete_caches" value="1">
                      <button class="btn btn-primary btn-sm" type="submit">Borrar</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if (empty($purgeDomainCandidates)): ?>
                <tr><td colspan="4" class="mut">Sin dominios recientes.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </article>
      </section>
      <section class="row">
        <article class="card">
          <h2><?= clickfix_h(cft('reports_title')); ?></h2>
          <p class="mut">Define receptores y frecuencia. El generador crea JSON en <span class="mono">data/reports/</span>.</p>
          <form method="post">
            <input type="hidden" name="action" value="report_schedule_save">
            <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
            <select name="period">
              <option value="daily">daily</option>
              <option value="weekly">weekly</option>
              <option value="monthly">monthly</option>
            </select>
            <input type="text" name="recipient" required placeholder="email o canal destino">
            <select name="enabled">
              <option value="1">enabled</option>
              <option value="0">disabled</option>
            </select>
            <button class="btn btn-primary btn-sm" type="submit">Guardar programacion</button>
          </form>
          <form method="post" style="margin-top:10px">
            <input type="hidden" name="action" value="report_run_now">
            <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
            <button class="btn btn-primary btn-sm" type="submit">Ejecutar ahora</button>
          </form>
          <p class="mut" style="margin-top:8px">Cron recomendado: <span class="mono">*/15 * * * * php /home/parthenoun/ClickFix/scripts/run_scheduled_reports.php</span></p>
        </article>
        <article class="card">
          <h2>Programaciones activas</h2>
          <table class="table table-striped settings-table">
            <thead><tr><th>Periodo</th><th>Destino</th><th>Enabled</th><th>Ãšltima</th><th>PrÃ³xima</th></tr></thead>
            <tbody>
              <?php foreach ($reportSchedules as $rs): ?>
                <tr>
                  <td class="mono"><?= clickfix_h((string) ($rs['period'] ?? '')); ?></td>
                  <td class="mono"><?= clickfix_h((string) ($rs['recipient'] ?? '')); ?></td>
                  <td class="mono"><?= !empty($rs['enabled']) ? 'yes' : 'no'; ?></td>
                  <td class="mono"><?= clickfix_h((string) ($rs['last_run_at'] ?? '')); ?></td>
                  <td class="mono"><?= clickfix_h((string) ($rs['next_run_at'] ?? '')); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </article>
      </section>
      <section class="card">
        <h2>Previsualizacion de reporte</h2>
        <form method="get" class="split">
          <input type="hidden" name="page" value="reports">
          <select name="period">
            <option value="daily"<?= $reportPeriodPreview === 'daily' ? ' selected' : ''; ?>>daily</option>
            <option value="weekly"<?= $reportPeriodPreview === 'weekly' ? ' selected' : ''; ?>>weekly</option>
            <option value="monthly"<?= $reportPeriodPreview === 'monthly' ? ' selected' : ''; ?>>monthly</option>
          </select>
          <button class="btn btn-primary btn-sm" type="submit">Actualizar preview</button>
        </form>
        <p class="mono">period: <?= clickfix_h((string) ($reportPreview['period'] ?? 'daily')); ?> | from: <?= clickfix_h((string) ($reportPreview['from'] ?? '')); ?> | generated_at: <?= clickfix_h((string) ($reportPreview['generated_at'] ?? '')); ?></p>
        <table class="table table-striped settings-table">
          <thead><tr><th>Total alertas</th><th>Total bloqueos</th><th>Hosts unicos</th><th>Clientes activos</th></tr></thead>
          <tbody>
            <tr>
              <td class="mono"><?= (int) (($reportPreview['summary']['total_alerts'] ?? 0)); ?></td>
              <td class="mono"><?= (int) (($reportPreview['summary']['total_blocks'] ?? 0)); ?></td>
              <td class="mono"><?= (int) (($reportPreview['summary']['unique_hosts'] ?? 0)); ?></td>
              <td class="mono"><?= (int) (($reportPreview['summary']['active_clients'] ?? 0)); ?></td>
            </tr>
          </tbody>
        </table>
        <h3 style="margin-top:8px">Top Sources of Attack</h3>
        <table class="table table-striped settings-table">
          <thead><tr><th>Attacking Host</th><th>Number of Attacks</th><th>Percent Total</th><th>Blocked</th><th>Last seen</th></tr></thead>
          <tbody>
            <?php foreach ((array) ($reportPreview['top_sources_of_attack'] ?? $reportPreview['top_domains'] ?? []) as $domainRow): ?>
              <tr>
                <td class="mono"><?= clickfix_h((string) ($domainRow['attacking_host'] ?? $domainRow['hostname'] ?? '')); ?></td>
                <td class="mono"><?= (int) ($domainRow['number_of_attacks'] ?? $domainRow['hits'] ?? 0); ?></td>
                <td class="mono"><?= number_format((float) ($domainRow['percent_total'] ?? 0), 2); ?>%</td>
                <td class="mono"><?= (int) ($domainRow['blocked_attacks'] ?? $domainRow['blocked_hits'] ?? 0); ?></td>
                <td class="mono"><?= clickfix_h((string) ($domainRow['last_seen'] ?? '')); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <div class="row" style="margin-top:8px">
          <article class="card">
            <h3>Event Type Distribution</h3>
            <table class="table table-striped settings-table">
              <thead><tr><th>Event Type</th><th>Hits</th><th>Percent Total</th><th>Blocked</th></tr></thead>
              <tbody>
                <?php foreach ((array) ($reportPreview['event_type_distribution'] ?? []) as $eventRow): ?>
                  <tr>
                    <td class="mono"><?= clickfix_h((string) ($eventRow['event_type'] ?? '')); ?></td>
                    <td class="mono"><?= (int) ($eventRow['hits'] ?? 0); ?></td>
                    <td class="mono"><?= number_format((float) ($eventRow['percent_total'] ?? 0), 2); ?>%</td>
                    <td class="mono"><?= (int) ($eventRow['blocked_hits'] ?? 0); ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </article>
          <article class="card">
            <h3>Severity Distribution</h3>
            <table class="table table-striped settings-table">
              <thead><tr><th>Severity</th><th>Hits</th><th>Percent Total</th><th>Blocked</th></tr></thead>
              <tbody>
                <?php foreach ((array) ($reportPreview['severity_distribution'] ?? []) as $severityRow): ?>
                  <tr>
                    <td class="mono"><?= clickfix_h((string) ($severityRow['severity'] ?? '')); ?></td>
                    <td class="mono"><?= (int) ($severityRow['hits'] ?? 0); ?></td>
                    <td class="mono"><?= number_format((float) ($severityRow['percent_total'] ?? 0), 2); ?>%</td>
                    <td class="mono"><?= (int) ($severityRow['blocked_hits'] ?? 0); ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </article>
        </div>
      </section>
    <?php endif; ?>

    <?php if ($loggedIn && $page === 'requests' && cfcan($user, 'analyst_sr')): ?>
      <section class="row">
        <article class="card">
          <h2>Desistimientos</h2>
          <table class="table table-striped settings-table">
            <thead><tr><th>Fecha</th><th>Dominio</th><th>Estado</th><th>AcciÃ³n</th></tr></thead>
            <tbody>
              <?php foreach ($appeals as $ap): ?>
                <tr>
                  <td class="mono"><?= clickfix_h((string) ($ap['created_at'] ?? '')); ?></td>
                  <td class="mono"><?= clickfix_h((string) ($ap['domain'] ?? '')); ?></td>
                  <td><?= clickfix_h((string) ($ap['status'] ?? 'pending')); ?></td>
                  <td>
                    <form method="post">
                      <input type="hidden" name="action" value="appeal_status">
                      <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                      <input type="hidden" name="appeal_id" value="<?= (int) ($ap['id'] ?? 0); ?>">
                      <select name="status"><option>pending</option><option>approved</option><option>rejected</option></select>
                      <button class="btn btn-primary btn-sm" type="submit">OK</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </article>
        <article class="card">
          <h2>Solicitudes de eliminacion</h2>
          <table class="table table-striped settings-table">
            <thead><tr><th>Fecha</th><th>Host</th><th>URL</th><th>Motivo</th><th>Cliente</th></tr></thead>
            <tbody>
              <?php foreach ($deleteRequests as $req): ?>
                <tr>
                  <td class="mono"><?= clickfix_h((string) ($req['received_at'] ?? '')); ?></td>
                  <td class="mono"><?= clickfix_h((string) ($req['hostname'] ?? '')); ?></td>
                  <td class="mono"><?= clickfix_h((string) ($req['url'] ?? '')); ?></td>
                  <td><?= clickfix_h((string) ($req['message'] ?? '')); ?></td>
                  <td class="mono"><?= clickfix_h((string) ($req['client_id'] ?? '')); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </article>
        <article class="card">
          <h2>Solicitudes de acceso</h2>
          <table class="table table-striped settings-table">
            <thead><tr><th>Email</th><th>LinkedIn</th><th>Veces</th><th>Ãšltima</th><th>AcciÃ³n</th></tr></thead>
            <tbody>
              <?php foreach ($requestsPending as $rq): ?>
                <tr>
                  <td class="mono"><?= clickfix_h((string) ($rq['email'] ?? '')); ?></td>
                  <td class="mono"><?= clickfix_h((string) ($rq['linkedin_url'] ?? '')); ?></td>
                  <td class="mono"><?= (int) ($rq['request_count'] ?? 1); ?></td>
                  <td class="mono"><?= clickfix_h((string) ($rq['last_seen_at'] ?? '')); ?></td>
                  <td>
                    <form method="post">
                      <input type="hidden" name="action" value="access_request_status">
                      <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                      <input type="hidden" name="request_id" value="<?= (int) ($rq['id'] ?? 0); ?>">
                      <select name="status">
                        <option value="approved">aprobar</option>
                        <option value="denied">denegar</option>
                        <option value="pending">pendiente</option>
                      </select>
                      <button class="btn btn-primary btn-sm" type="submit">OK</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </article>
        <article class="card">
          <h2>Accesos denegados</h2>
          <table class="table table-striped settings-table">
            <thead><tr><th>Email</th><th>LinkedIn</th><th>Veces</th><th>Ãšltima</th></tr></thead>
            <tbody>
              <?php foreach ($requestsDenied as $rq): ?>
                <tr>
                  <td class="mono"><?= clickfix_h((string) ($rq['email'] ?? '')); ?></td>
                  <td class="mono"><?= clickfix_h((string) ($rq['linkedin_url'] ?? '')); ?></td>
                  <td class="mono"><?= (int) ($rq['request_count'] ?? 1); ?></td>
                  <td class="mono"><?= clickfix_h((string) ($rq['last_seen_at'] ?? '')); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </article>
      </section>
    <?php endif; ?>

    <?php if ($loggedIn && $page === 'users' && cfcan($user, 'admin')): ?>
      <?php
        $usersTotal = count($users);
        $usersVerified = 0;
        $usersPendingCount = 0;
        $usersAdminCount = 0;
        foreach ($users as $uRow) {
            if (!empty($uRow['verified'])) {
                $usersVerified++;
            } else {
                $usersPendingCount++;
            }
            if (($uRow['role'] ?? '') === 'admin') {
                $usersAdminCount++;
            }
        }
      ?>
      <section class="row">
        <article class="card users-admin-hero">
          <div class="card-body">
            <div class="users-hero-head">
              <div>
                <h2>AdministraciÃ³n de usuarios</h2>
                <p class="mut">Alta, roles, verificaciÃ³n y control de reputaciÃ³n en un panel centralizado.</p>
              </div>
              <span class="badge badge-outline-info">Admin only</span>
            </div>
            <div class="users-kpi-grid">
              <div class="users-kpi">
                <span>Total</span>
                <strong><?= $usersTotal; ?></strong>
              </div>
              <div class="users-kpi">
                <span>Verificados</span>
                <strong><?= $usersVerified; ?></strong>
              </div>
              <div class="users-kpi">
                <span>Pendientes</span>
                <strong><?= $usersPendingCount; ?></strong>
              </div>
              <div class="users-kpi">
                <span>Admins</span>
                <strong><?= $usersAdminCount; ?></strong>
              </div>
            </div>
          </div>
        </article>
        <article class="card users-card">
          <div class="card-body">
            <h2>Nuevo usuario</h2>
            <p class="mut">Solo administradores pueden crear cuentas y definir el rol operativo.</p>
            <form method="post" class="users-form-grid">
              <input type="hidden" name="action" value="user_create">
              <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
              <input type="text" name="new_username" maxlength="80" required placeholder="username">
              <input type="email" name="new_email" maxlength="190" required placeholder="email">
              <input type="password" name="new_password" minlength="10" required placeholder="password mÃ­nimo 10 chars">
              <select name="new_role">
                <option value="analyst_jr">Analista Jr</option>
                <option value="analyst_mid">Analista Mid</option>
                <option value="analyst_sr">Analista Sr</option>
                <option value="admin">Administrador</option>
              </select>
              <select name="new_verified">
                <option value="1">Verificado</option>
                <option value="0">Pendiente</option>
              </select>
              <select name="new_lang">
                <option value="en" selected>Idioma EN</option>
                <option value="es">Idioma ES</option>
                <option value="ca">Idioma CA</option>
                <option value="de">Idioma DE</option>
                <option value="fr">Idioma FR</option>
              </select>
              <button class="btn btn-primary btn-sm" type="submit">Crear usuario</button>
            </form>
          </div>
        </article>
        <article class="card users-card">
          <div class="card-body">
            <h2>GuÃ­a rÃ¡pida de administraciÃ³n</h2>
            <div class="rbac">
              <div class="item"><b>Solicitudes</b><span>Revisa peticiones de acceso desde index y valida legitimidad.</span></div>
              <div class="item"><b>Alta de cuenta</b><span>Crea usuario con email, password y permisos de trabajo.</span></div>
              <div class="item"><b>Mantenimiento</b><span>Actualiza estado, credenciales y email cuando sea necesario.</span></div>
              <div class="item"><b>AuditorÃ­a</b><span>Corrobora actividad en paneles de datos, reportes y trazabilidad.</span></div>
            </div>
          </div>
        </article>
      </section>
      <section class="card users-card">
        <div class="card-body">
        <div class="users-table-head">
          <div>
            <h2>Usuarios</h2>
            <p class="mut">Gestiona roles, verificaciÃ³n, idioma y reputaciÃ³n desde un solo panel.</p>
          </div>
          <div class="users-table-actions">
            <input class="form-control" id="admin-users-search" type="text" placeholder="Buscar usuario, email, rol...">
          </div>
        </div>
        <div class="analytics-table-wrap users-table-wrap">
        <table class="table table-striped settings-table users-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Usuario</th>
              <th>Email</th>
              <th>Rol</th>
              <th>Idioma</th>
              <th>REP</th>
              <th>Estado</th>
              <th>Creado</th>
              <th>Actualizar</th>
            </tr>
          </thead>
          <tbody id="admin-users-body">
            <?php foreach ($users as $u): ?>
              <tr data-user-row="1">
                <td class="mono"><?= (int) ($u['id'] ?? 0); ?></td>
                <td class="mono"><a class="user-link" href="<?= clickfix_h(cfprofileurl((int) ($u['id'] ?? 0), [], false)); ?>"><?= clickfix_h((string) ($u['username'] ?? '')); ?></a></td>
                <td class="mono"><?= clickfix_h((string) ($u['email'] ?? '')); ?></td>
                <td><?= clickfix_h((string) ($u['role_label'] ?? clickfix_role_label((string) ($u['role'] ?? 'analyst_jr')))); ?></td>
                <td class="mono"><?= clickfix_h((string) ($u['preferred_lang'] ?? 'en')); ?></td>
                <td class="mono"><?= (int) ($u['reputation'] ?? 0); ?></td>
                <td><?= !empty($u['verified']) ? 'verificado' : 'pendiente'; ?></td>
                <td class="mono"><?= clickfix_h((string) ($u['created_at'] ?? '')); ?></td>
                <td>
                  <form method="post" class="mono user-inline-form">
                    <input type="hidden" name="action" value="user_update">
                    <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                    <input type="hidden" name="edit_user_id" value="<?= (int) ($u['id'] ?? 0); ?>">
                    <select name="edit_role">
                      <option value="analyst_jr"<?= (($u['role'] ?? '') === 'analyst_jr') ? ' selected' : ''; ?>>Analista Jr</option>
                      <option value="analyst_mid"<?= (($u['role'] ?? '') === 'analyst_mid') ? ' selected' : ''; ?>>Analista Mid</option>
                      <option value="analyst_sr"<?= (($u['role'] ?? '') === 'analyst_sr') ? ' selected' : ''; ?>>Analista Sr</option>
                      <option value="admin"<?= (($u['role'] ?? '') === 'admin') ? ' selected' : ''; ?>>Administrador</option>
                    </select>
                    <select name="edit_verified">
                      <option value="1"<?= !empty($u['verified']) ? ' selected' : ''; ?>>Verificado</option>
                      <option value="0"<?= empty($u['verified']) ? ' selected' : ''; ?>>Pendiente</option>
                    </select>
                    <?php $editLang = clickfix_normalize_user_language((string) ($u['preferred_lang'] ?? 'en')); ?>
                    <select name="edit_lang">
                      <option value="en"<?= $editLang === 'en' ? ' selected' : ''; ?>>en</option>
                      <option value="es"<?= $editLang === 'es' ? ' selected' : ''; ?>>es</option>
                      <option value="ca"<?= $editLang === 'ca' ? ' selected' : ''; ?>>ca</option>
                      <option value="de"<?= $editLang === 'de' ? ' selected' : ''; ?>>de</option>
                      <option value="fr"<?= $editLang === 'fr' ? ' selected' : ''; ?>>fr</option>
                    </select>
                    <input type="number" name="edit_reputation" value="<?= (int) ($u['reputation'] ?? 0); ?>" min="-1000" max="100000" placeholder="REP">
                    <input type="email" name="edit_email" maxlength="190" value="<?= clickfix_h((string) ($u['email'] ?? '')); ?>" placeholder="email">
                    <input type="password" name="edit_password" minlength="10" placeholder="nuevo password (opcional)">
                    <button class="btn btn-primary btn-sm" type="submit">Guardar</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
            <tr id="admin-users-empty" hidden><td colspan="9" class="mut">Sin coincidencias para el filtro.</td></tr>
          </tbody>
        </table>
        </div>
        </div>
      </section>
      <section class="card">
        <h2>Solicitudes de acceso desde index.php (Trabaja con nosotros)</h2>
        <p class="mut">Estas solicitudes no crean usuarios automÃ¡ticamente. Solo Admin crea cuenta y rol desde el panel superior.</p>
        <table class="table table-striped settings-table">
          <thead><tr><th>Email</th><th>LinkedIn</th><th>Web</th><th>Veces</th><th>Primera</th><th>Ãšltima</th><th>Idioma</th><th>IP</th></tr></thead>
          <tbody>
            <?php foreach ($requests as $rq): ?>
              <tr>
                <td class="mono"><?= clickfix_h((string) ($rq['email'] ?? '')); ?></td>
                <td class="mono"><?= clickfix_h((string) ($rq['linkedin_url'] ?? '')); ?></td>
                <td class="mono"><?= clickfix_h((string) ($rq['company_website'] ?? '')); ?></td>
                <td class="mono"><?= (int) ($rq['request_count'] ?? 1); ?></td>
                <td class="mono"><?= clickfix_h((string) ($rq['first_seen_at'] ?? '')); ?></td>
                <td class="mono"><?= clickfix_h((string) ($rq['last_seen_at'] ?? '')); ?></td>
                <td class="mono"><?= clickfix_h((string) ($rq['request_lang'] ?? '')); ?></td>
                <td class="mono"><?= clickfix_h((string) ($rq['request_ip'] ?? '')); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </section>
    <?php endif; ?>
      </main>
    </div>
    <div class="dashboard-side-col">
      <aside class="side-column">
        <?php if ($page === 'ops'): ?>
        <div class="side-card-grid side-card-grid--ops">
        <?php endif; ?>
        <section class="card side-card side-card--geo">
          <div class="side-card-head side-card-head--geo">
            <div>
              <h3>Top pa&iacute;ses</h3>
              <p class="mut side-card-subtitle">Mapa mundial de afectaci&oacute;n por volumen de eventos.</p>
            </div>
          </div>
          <div class="card-body">
            <h4 class="card-title">Radar rÃ¡pido</h4>
            <div class="row">
              <div class="col-6 mb-3">
                <p class="text-muted mb-1">alertas 24h</p>
                <h5 class="mb-0" data-live-metric="alerts_24h"><?= (int) ($metrics['alerts_24h'] ?? 0); ?></h5>
              </div>
              <div class="col-6 mb-3">
                <p class="text-muted mb-1">bloqueos 24h</p>
                <h5 class="mb-0" data-live-metric="blocks_24h"><?= (int) ($metrics['blocks_24h'] ?? 0); ?></h5>
              </div>
              <div class="col-6">
                <p class="text-muted mb-1">dominios Ãºnicos</p>
                <h5 class="mb-0" data-live-metric="unique_hosts"><?= (int) ($metrics['unique_hosts'] ?? 0); ?></h5>
              </div>
              <div class="col-6">
                <p class="text-muted mb-1">pend. revisiÃ³n</p>
                <h5 class="mb-0" data-live-metric="pending_review_total"><?= (int) ($metrics['pending_review_total'] ?? 0); ?></h5>
              </div>
            </div>
          </div>
        </section>
        <?php if ($showAnnouncementAside): ?>
        <section class="card side-card announcement-aside">
          <details<?= $investigationFocusMode ? '' : ' open'; ?>>
            <summary>
              <div>
                <b><?= clickfix_h(cft('announce_title')); ?></b>
                <span><?= clickfix_h(cft('announce_sub')); ?></span>
              </div>
            </summary>
            <?php if ($investigationFocusMode): ?>
              <p class="announcement-focus"><?= clickfix_h(cft('announce_focus_note')); ?></p>
            <?php endif; ?>
            <ul class="announcement-list">
              <?php if ($loggedIn): ?>
                <?php foreach ($sidebarAnnouncements as $announcement): ?>
                  <?php
                    $severity = strtolower(trim((string) ($announcement['severity'] ?? 'info')));
                    if (!in_array($severity, ['info', 'warning', 'critical'], true)) {
                        $severity = 'info';
                    }
                    $expiresAtLabel = '';
                    $expiresAtRaw = trim((string) ($announcement['expires_at'] ?? ''));
                    if ($expiresAtRaw !== '') {
                        $expiresAtTs = strtotime($expiresAtRaw);
                        if ($expiresAtTs !== false) {
                            $expiresAtLabel = gmdate('Y-m-d', $expiresAtTs);
                        }
                    }
                    $announcementTitle = trim((string) ($announcement['title'] ?? ''));
                    if ($announcementTitle === '') {
                        $announcementTitle = 'Aviso';
                    }
                  ?>
                  <li class="announcement-item">
                    <div class="announcement-item-head">
                      <span class="announcement-severity <?= clickfix_h($severity); ?>"><?= clickfix_h($severity); ?></span>
                      <?php if ($expiresAtLabel !== ''): ?>
                        <span class="announcement-meta"><?= clickfix_h(cft('announce_until')); ?> <?= clickfix_h($expiresAtLabel); ?></span>
                      <?php endif; ?>
                    </div>
                    <b><?= clickfix_h($announcementTitle); ?></b>
                    <?php if (trim((string) ($announcement['body'] ?? '')) !== ''): ?>
                      <p><?= nl2br(clickfix_h((string) ($announcement['body'] ?? ''))); ?></p>
                    <?php endif; ?>
                  </li>
                <?php endforeach; ?>
                <?php if (empty($sidebarAnnouncements)): ?>
                  <li class="announcement-item">
                    <p style="margin:0"><?= clickfix_h(cft('announce_empty')); ?></p>
                  </li>
                <?php endif; ?>
              <?php else: ?>
                <li class="announcement-item">
                  <b><?= clickfix_h(cft('announce_guest_title')); ?></b>
                  <p><?= clickfix_h(cft('announce_guest_text')); ?></p>
                </li>
              <?php endif; ?>
            </ul>
          </details>
        </section>
        <?php endif; ?>
        <section class="card side-card side-card--geo">
          <div class="side-card-head side-card-head--geo">
            <div>
              <h3>Top pa&iacute;ses</h3>
              <p class="mut side-card-subtitle">Mapa mundial de afectaci&oacute;n por volumen de eventos.</p>
            </div>
          </div>
          <div class="side-geo-layout">
          <div class="geo-mini-wrap">
            <?php
              $sideCountriesAll = [];
              if (is_array($metrics['countries'] ?? null)) {
                  foreach ((array) $metrics['countries'] as $countryKey => $countryValue) {
                      if (is_array($countryValue)) {
                          $name = (string) ($countryValue['country'] ?? $countryValue['code'] ?? $countryKey);
                          $code = (string) ($countryValue['code'] ?? '');
                          $hits = (int) ($countryValue['hits'] ?? $countryValue['count'] ?? 0);
                      } else {
                          $name = (string) $countryKey;
                          $code = '';
                          $hits = (int) $countryValue;
                      }
                      if ($code === '' && preg_match('/^[A-Z]{2}$/', strtoupper($name))) {
                          $code = strtoupper($name);
                      }
                      if ($name !== '') {
                          $sideCountriesAll[] = ['name' => strtoupper($name), 'code' => strtoupper($code), 'hits' => $hits];
                      }
                  }
              }
              usort($sideCountriesAll, static function (array $a, array $b): int {
                  return $b['hits'] <=> $a['hits'];
              });
              $sideCountries = array_slice($sideCountriesAll, 0, 6);
              $sideMaxHits = 0;
              foreach ($sideCountriesAll as $countryRow) {
                  if ((int) $countryRow['hits'] > $sideMaxHits) {
                      $sideMaxHits = (int) $countryRow['hits'];
                  }
              }
              $sideTopCode = '';
              $sideTopName = '';
              if (!empty($sideCountries)) {
                  $sideTopCode = (string) ($sideCountries[0]['code'] ?? '');
                  $sideTopName = (string) ($sideCountries[0]['name'] ?? '');
              }
              $sideCountryHits = [];
              foreach ($sideCountriesAll as $countryRow) {
                  $code = (string) ($countryRow['code'] ?? '');
                  if ($code !== '') {
                      $sideCountryHits[$code] = (int) ($countryRow['hits'] ?? 0);
                  }
              }
            ?>
            <div class="geo-map-meta">
              <div class="geo-map-stat">
                <span class="label">Pa&iacute;s m&aacute;s afectado</span>
                <b><?= $sideTopCode !== '' ? clickfix_h($sideTopCode) : '-'; ?><?= $sideTopName !== '' ? ' - ' . clickfix_h($sideTopName) : ''; ?></b>
              </div>
              <div class="geo-map-stat">
                <span class="label">Pa&iacute;ses afectados</span>
                <b><?= count($sideCountryHits); ?></b>
              </div>
            </div>
            <div id="sidebar-geo-map" class="geo-mini-map"
                 data-top-country="<?= clickfix_h($sideTopCode); ?>"
                 data-countries='<?= clickfix_h(json_encode($sideCountryHits, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)); ?>'
                 data-max="<?= (int) $sideMaxHits; ?>"></div>
            <div class="geo-mini-scale" aria-hidden="true">
              <span>Baja</span>
              <i></i>
              <span>Alta</span>
            </div>
            <div class="geo-mini-legend">
              <span class="mut">Intensidad por volumen de eventos detectados.</span>
            </div>
          </div>
          <div class="side-country-ranking">
            <ul class="mini-list mini-list--countries">
              <?php if (empty($sideCountries)): ?>
                <li><span class="mut">Sin datos recientes</span><span class="mono">-</span></li>
              <?php else: ?>
                <?php foreach ($sideCountries as $countryRow): ?>
                  <?php
                    $countryCode = strtolower((string) ($countryRow['code'] ?? ''));
                    $barWidth = $sideMaxHits > 0 ? (int) round(($countryRow['hits'] / $sideMaxHits) * 100) : 0;
                  ?>
                  <li class="country-row">
                    <span class="country-label">
                      <?php if ($countryCode !== ''): ?>
                        <span class="flag-icon flag-icon-<?= clickfix_h($countryCode); ?> country-flag" title="<?= clickfix_h((string) $countryRow['name']); ?>"></span>
                      <?php endif; ?>
                      <span class="mono"><?= clickfix_h((string) $countryRow['name']); ?></span>
                    </span>
                    <span class="mono"><?= (int) $countryRow['hits']; ?></span>
                    <div class="country-bar"><span style="width:<?= $barWidth; ?>%"></span></div>
                  </li>
                <?php endforeach; ?>
              <?php endif; ?>
            </ul>
          </div>
          </div>
        </section>
        <?php if ($page === 'ops'): ?>
        </div>
        <?php endif; ?>
        <section class="card side-card">
          <h3>Estado de sesi&oacute;n</h3>
          <ul class="mini-list">
            <li><span>Modo</span><span class="mono"><?= $loggedIn ? 'Autenticado' : 'P&uacute;blico'; ?></span></li>
            <li><span>Rol</span><span class="mono"><?= clickfix_h($viewerRoleLabel); ?></span></li>
            <li><span>Idioma</span><span class="mono"><?= clickfix_h(strtoupper($lang)); ?></span></li>
            <?php if ($loggedIn && $sessionExpiresAt > 0): ?>
              <li><span>Sesi&oacute;n</span><span class="mono"><?= clickfix_h(gmdate('H:i', $sessionExpiresAt)); ?> UTC</span></li>
            <?php else: ?>
              <li><span>Sesi&oacute;n</span><span class="mono">-</span></li>
            <?php endif; ?>
          </ul>
          <?php if (!$loggedIn): ?>
            <div class="mini-links" style="margin-top:8px">
              <a href="<?= clickfix_h(cfurl('access', true)); ?>"><?= clickfix_h(cft('nav_access')); ?></a>
            </div>
          <?php endif; ?>
        </section>
        <?php if ($loggedIn && cfcan($user, 'admin')): ?>
          <section class="card side-card">
            <?php
              $enabledSchedules = 0;
              foreach ($reportSchedules as $scheduleRow) {
                  if (!empty($scheduleRow['enabled'])) {
                      $enabledSchedules++;
                  }
              }
            ?>
            <h3>Reportes automÃ¡ticos</h3>
            <ul class="mini-list">
              <li><span>programaciones</span><span class="mono"><?= count($reportSchedules); ?></span></li>
              <li><span>activas</span><span class="mono"><?= (int) $enabledSchedules; ?></span></li>
              <li><span>preview</span><span class="mono"><?= clickfix_h($reportPeriodPreview); ?></span></li>
            </ul>
          </section>
        <?php endif; ?>
        <?php if ($showInternalDashboardAdsPanel): ?>
          <section class="card side-card">
            <h3>Espacios patrocinados</h3>
            <div class="internal-ads-stack">
              <?php foreach ($internalDashboardAds as $adRow): ?>
                <?php
                  $adTheme = clickfix_internal_ad_theme((string) ($adRow['theme'] ?? 'cyan'));
                  $adUrl = clickfix_sanitize_http_url((string) ($adRow['cta_url'] ?? ''));
                  $adLabel = trim((string) ($adRow['cta_label'] ?? ''));
                  if ($adLabel === '') {
                      $adLabel = 'Abrir';
                  }
                ?>
                <article class="internal-ad-card <?= clickfix_h($adTheme); ?>">
                  <span class="internal-ad-kicker">test ad | dashboard</span>
                  <b><?= clickfix_h((string) ($adRow['title'] ?? 'Sponsored slot')); ?></b>
                  <p><?= nl2br(clickfix_h((string) ($adRow['body'] ?? ''))); ?></p>
                  <?php if ($adUrl !== ''): ?>
                    <a class="btn" href="<?= clickfix_h($adUrl); ?>" target="_blank" rel="noopener noreferrer"><?= clickfix_h($adLabel); ?></a>
                  <?php endif; ?>
                </article>
              <?php endforeach; ?>
            </div>
          </section>
        <?php endif; ?>
        <?php if ($showMonetizationPanel): ?>
          <section class="card side-card">
            <h3><?= clickfix_h(cft('support_title')); ?></h3>
            <p class="support-note"><?= clickfix_h(cft('support_sub')); ?></p>
            <?php if (!empty($monetization['has_donations'])): ?>
              <div class="mini-links" style="margin-bottom:8px">
                <?php if (!empty($monetization['donation_paypal_url'])): ?>
                  <a href="<?= clickfix_h((string) $monetization['donation_paypal_url']); ?>" target="_blank" rel="noopener noreferrer"><?= clickfix_h(cft('support_paypal')); ?></a>
                <?php endif; ?>
                <?php if (!empty($monetization['donation_kofi_url'])): ?>
                  <a href="<?= clickfix_h((string) $monetization['donation_kofi_url']); ?>" target="_blank" rel="noopener noreferrer"><?= clickfix_h(cft('support_kofi')); ?></a>
                <?php endif; ?>
                <?php if (!empty($monetization['donation_stripe_url'])): ?>
                  <a href="<?= clickfix_h((string) $monetization['donation_stripe_url']); ?>" target="_blank" rel="noopener noreferrer"><?= clickfix_h(cft('support_stripe')); ?></a>
                <?php endif; ?>
              </div>
            <?php endif; ?>
            <?php if (!empty($monetization['show_ads'])): ?>
              <div class="mut mono" style="margin:0 0 6px"><?= clickfix_h(cft('support_ads')); ?></div>
              <div class="ad-slot">
                <ins class="adsbygoogle"
                     style="display:block"
                     data-ad-client="<?= clickfix_h((string) $monetization['adsense_client']); ?>"
                     data-ad-slot="<?= clickfix_h((string) $monetization['adsense_slot']); ?>"
                     data-ad-format="auto"
                     data-full-width-responsive="true"></ins>
              </div>
            <?php else: ?>
              <div class="mut mono" style="margin:0 0 6px">Espacio de anuncio (configurable)</div>
              <div class="ad-slot">
                <div class="mut" style="padding:10px;text-align:center">Configura `CLICKFIX_ADSENSE_ENABLED=1`, `CLICKFIX_ADSENSE_CLIENT` y `CLICKFIX_ADSENSE_SLOT` para activar este bloque.</div>
              </div>
            <?php endif; ?>
          </section>
        <?php endif; ?>
      </aside>
    </div>
  </div>
        </div>
      </div>
    </div>
  </div>

  <div id="session-timeout-modal" class="session-timeout-modal" hidden>
    <div class="session-timeout-card">
      <h3>SesiÃ³n a punto de expirar</h3>
      <p class="mut">Tu Sesin expira en <span id="session-timeout-countdown">00:00</span>. Puedes extender <?= (int) $sessionExtendMinutes; ?> minutos o cerrar ahora.</p>
      <div class="split" style="margin-top:10px">
        <button class="btn" type="button" id="session-extend-btn">Seguir <?= (int) $sessionExtendMinutes; ?> min</button>
        <button class="btn secondary" type="button" id="session-logout-btn">Cerrar SesiÃ³n</button>
      </div>
    </div>
  </div>

  <?php if ($showMonetizationPanel && !empty($monetization['show_ads'])): ?>
    <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=<?= urlencode((string) $monetization['adsense_client']); ?>" crossorigin="anonymous"></script>
    <script>
      (adsbygoogle = window.adsbygoogle || []).push({});
    </script>
  <?php endif; ?>
  <?php if ($enableHomeGeoPanels || $enableSidebarGeoMap): ?>
    <script src="<?= clickfix_h($leafletJsUrl); ?>"></script>
  <?php endif; ?>

  <script src="<?= clickfix_h($templateBaseUrl); ?>/vendors/js/vendor.bundle.base.js"></script>
  <script src="<?= clickfix_h($templateBaseUrl); ?>/js/off-canvas.js"></script>
  <script src="<?= clickfix_h($templateBaseUrl); ?>/js/misc.js"></script>
  <script src="<?= clickfix_h($templateBaseUrl); ?>/js/settings.js"></script>
  <script src="<?= clickfix_h($templateBaseUrl); ?>/js/todolist.js"></script>
  <?php require __DIR__ . '/partials/dashboard_chat_bubble.php'; ?>
  <?php require __DIR__ . '/partials/dashboard_chat_js.php'; ?>
  <?php require __DIR__ . '/partials/dashboard_autoinv_js.php'; ?>
  <?php require __DIR__ . '/partials/dashboard_scripts.php'; ?>
  <script id="cf-event-workbench-data" type="application/json"><?= $eventWorkbenchJson; ?></script>
  <script>
    (function () {
      const dataNode = document.getElementById('cf-event-workbench-data');
      const feed = document.getElementById('event-feed');
      const empty = document.getElementById('event-empty');
      const detail = document.getElementById('event-detail');
      const title = document.getElementById('event-title');
      const severityFilter = document.getElementById('ops-severity-filter');
      if (!dataNode || !feed || !empty || !detail || !title) {
        return;
      }

      let rows = [];
      try {
        rows = JSON.parse(dataNode.textContent || '[]');
      } catch (error) {
        rows = [];
      }
      if (!Array.isArray(rows) || !rows.length) {
        return;
      }

      const byId = (id) => document.getElementById(id);
      const severityBucketForScore = (score) => {
        const numeric = Number(score || 0);
        if (numeric >= 70) return 'high';
        if (numeric >= 40) return 'medium';
        return 'low';
      };
      const getEventGroupKey = (event) => {
        if (!event || typeof event !== 'object') return '';
        const hostname = String(event.hostname || '').trim().toLowerCase();
        const reasons = Array.isArray(event.reason_list) ? event.reason_list.filter(Boolean) : [];
        const firstReason = String(reasons[0] || event.message || '').trim().toLowerCase();
        return `${hostname}|${firstReason}`;
      };
      const eventGroups = new Map();
      rows.forEach((row, index) => {
        const key = getEventGroupKey(row);
        if (!key) return;
        if (!eventGroups.has(key)) {
          eventGroups.set(key, []);
        }
        eventGroups.get(key).push(index);
      });
      let currentGroupIndices = [];
      let currentReviewScope = 'single';
      const setText = (id, value, fallback = '-') => {
        const node = byId(id);
        if (!node) return;
        const next = String(value ?? '').trim();
        node.textContent = next !== '' ? next : fallback;
      };
      const setList = (id, values, emptyText) => {
        const node = byId(id);
        if (!node) return;
        node.innerHTML = '';
        const items = Array.isArray(values) ? values.filter(Boolean) : [];
        if (!items.length) {
          const li = document.createElement(node.tagName === 'UL' ? 'li' : 'div');
          li.className = 'event-empty';
          li.textContent = emptyText;
          node.appendChild(li);
          return;
        }
        items.forEach((value) => {
          const child = document.createElement(node.tagName === 'UL' ? 'li' : 'div');
          if (node.tagName !== 'UL') {
            child.className = 'event-snippet';
          }
          child.textContent = String(value);
          node.appendChild(child);
        });
      };
      const summarizeEvent = (event) => {
        const score = Number(event.score_total || 0);
        const hostname = String(event.hostname || '(sin dominio)');
        const country = String(event.country || '').trim();
        const type = String(event.event_type || 'clickfix_alert');
        const blocked = Boolean(event.blocked || event.host_blocked_before || event.ip_blocked_before);
        const duplicateCount = Math.max(1, Number(event.duplicate_count || 1));
        const reasons = Array.isArray(event.reason_list) ? event.reason_list.filter(Boolean) : [];
        const snippets = Array.isArray(event.snippets) ? event.snippets.filter(Boolean) : [];
        const signals = Array.isArray(event.signals) ? event.signals.filter(Boolean) : [];
        let riskLabel = 'bajo';
        if (score >= 80) {
          riskLabel = 'critico';
        } else if (score >= 60) {
          riskLabel = 'alto';
        } else if (score >= 35) {
          riskLabel = 'medio';
        }
        const parts = [
          `${hostname} presenta un evento de riesgo ${riskLabel} con score ${score}/100.`,
          country ? `La actividad se asocia a ${country}.` : 'No hay pais fiable asociado.',
          blocked ? 'Consta como bloqueado o con historial de bloqueo.' : 'No consta bloqueo previo confirmado.',
          duplicateCount > 1 ? `Se observan ${duplicateCount} impactos similares en el grupo.` : 'Solo se observa un impacto en este grupo.'
        ];
        const points = [];
        points.push(`Tipo: ${type}`);
        if (reasons.length) {
          points.push(`Motivo principal: ${String(reasons[0])}`);
        } else if (event.message) {
          points.push(`Motivo principal: ${String(event.message)}`);
        }
        if (snippets.length) {
          points.push(`Snippets: ${snippets.length}`);
        }
        if (signals.length) {
          points.push(`Evidencias: ${signals.length}`);
        }
        if (event.url) {
          points.push(`URL: ${String(event.url)}`);
        }
        return {
          summary: parts.join(' '),
          points: points.slice(0, 5)
        };
      };
      const renderSummary = (event) => {
        const textNode = byId('event-ai-summary-text');
        const pointsNode = byId('event-ai-summary-points');
        if (!textNode || !pointsNode) return;
        const summary = summarizeEvent(event);
        textNode.textContent = summary.summary;
        pointsNode.innerHTML = '';
        summary.points.forEach((point) => {
          const chip = document.createElement('span');
          chip.className = 'event-ai-point';
          chip.textContent = point;
          pointsNode.appendChild(chip);
        });
      };
      const syncReviewScopeUi = () => {
        const scopeWrap = byId('event-review-scope');
        const scopeNote = byId('event-review-scope-note');
        const actionInput = byId('event-review-action');
        const bulkIdsWrap = byId('event-review-bulk-ids');
        const reviewId = byId('event-review-id');
        if (!scopeWrap || !scopeNote || !actionInput || !bulkIdsWrap || !reviewId) {
          return;
        }
        const actionableGroup = currentGroupIndices.filter((idx) => {
          const row = rows[idx];
          return row && Number(row.id || 0) > 0;
        });
        const hasGroupChoice = actionableGroup.length > 1;
        scopeWrap.hidden = !hasGroupChoice;
        if (!hasGroupChoice) {
          currentReviewScope = 'single';
        }
        bulkIdsWrap.innerHTML = '';
        scopeWrap.querySelectorAll('[data-review-scope]').forEach((button) => {
          button.classList.toggle('is-active', button.getAttribute('data-review-scope') === currentReviewScope);
        });
        if (currentReviewScope === 'group' && hasGroupChoice) {
          actionInput.value = 'review_bulk';
          actionableGroup.forEach((idx) => {
            const row = rows[idx];
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'report_ids[]';
            input.value = String(Number(row.id || 0));
            bulkIdsWrap.appendChild(input);
          });
          scopeNote.textContent = `Se aplicarÃ¡ a ${actionableGroup.length} alertas del grupo seleccionado.`;
        } else {
          actionInput.value = 'review';
          scopeNote.textContent = hasGroupChoice
            ? `Se aplicarÃ¡ solo a la alerta abierta. El grupo contiene ${actionableGroup.length} alertas.`
            : 'Se aplicarÃ¡ solo a esta alerta.';
        }
      };
      const setBadges = (event) => {
        const node = byId('event-badges');
        if (!node) return;
        node.innerHTML = '';
        [
          `score ${Number(event.score_total || 0)}/100`,
          event.blocked ? 'blocked' : 'alert-only',
          String(event.review_status || 'pending'),
          `x${Math.max(1, Number(event.duplicate_count || 1))}`,
          String(event.event_type || 'clickfix_alert')
        ].forEach((label) => {
          const chip = document.createElement('span');
          chip.className = 'event-chip';
          chip.textContent = label;
          node.appendChild(chip);
        });
      };
      const scoreLabelMap = {
        weighted: 'Weighted',
        signals: 'SeÃ±ales',
        clipboard: 'Clipboard',
        context: 'Contexto',
        scoreComponentSignals: 'SeÃ±ales',
        scoreComponentClipboard: 'Clipboard',
        scoreComponentContext: 'Contexto',
        scoreSignalCommandMatch: 'PatrÃ³n de comando',
        scoreSignalShellHint: 'Indicio de shell',
        scoreSignalEvasionHint: 'Indicio de evasiÃ³n',
        scoreSignalClipboardWarning: 'ManipulaciÃ³n de portapapeles',
        scoreClipboardCommand: 'Comando en clipboard',
        scoreClipboardExecutionHint: 'Indicio de ejecuciÃ³n',
        scoreClipboardUrl: 'URL en clipboard',
        scoreClipboardHighEntropy: 'Alta entropÃ­a',
      };
      const humanizeScoreKey = (value) => {
        const raw = String(value || '').trim();
        if (!raw) return '-';
        if (scoreLabelMap[raw]) return scoreLabelMap[raw];
        const camel = raw
          .replace(/^score(Component|Signal|Clipboard)/, '')
          .replace(/([a-z0-9])([A-Z])/g, '$1 $2')
          .replace(/[_-]+/g, ' ')
          .trim();
        if (!camel) return raw;
        return camel.charAt(0).toUpperCase() + camel.slice(1);
      };
      const buildScoreDetailsView = (details) => {
        const shell = document.createElement('div');
        shell.className = 'score-detail-shell';

        const toolbar = document.createElement('div');
        toolbar.className = 'score-detail-toolbar';

        const total = document.createElement('div');
        total.className = 'score-detail-total';
        total.innerHTML = `<span>Total</span><strong>${Number(details.total || 0)}/100</strong><span>${humanizeScoreKey(details.method || 'weighted')}</span>`;
        toolbar.appendChild(total);

        const toggle = document.createElement('div');
        toggle.className = 'score-detail-toggle';
        const visualBtn = document.createElement('button');
        visualBtn.type = 'button';
        visualBtn.className = 'is-active';
        visualBtn.textContent = 'Vista';
        const jsonBtn = document.createElement('button');
        jsonBtn.type = 'button';
        jsonBtn.textContent = 'JSON';
        toggle.appendChild(visualBtn);
        toggle.appendChild(jsonBtn);
        toolbar.appendChild(toggle);
        shell.appendChild(toolbar);

        const panels = document.createElement('div');
        panels.className = 'score-detail-panels';

        const visualPanel = document.createElement('div');
        visualPanel.className = 'score-detail-visual';
        const componentList = document.createElement('div');
        componentList.className = 'score-component-list';

        const components = Array.isArray(details.components) ? details.components : [];
        if (components.length) {
          components.forEach((component) => {
            const score = Number(component.score || 0);
            const weight = Number(component.weight || 0);
            const contributions = Array.isArray(component.contributions) ? component.contributions : [];

            const card = document.createElement('section');
            card.className = 'score-component-card';

            const head = document.createElement('div');
            head.className = 'score-component-head';
            head.innerHTML =
              `<div class="score-component-title">` +
                `<strong>${humanizeScoreKey(component.labelKey || component.id || 'Componente')}</strong>` +
                `<div class="score-component-meta">` +
                  `<span class="score-component-pill">score ${score}/100</span>` +
                  `<span class="score-component-pill">peso ${(weight * 100).toFixed(0)}%</span>` +
                  `<span class="score-component-pill">${component.available === false ? 'sin datos' : 'activo'}</span>` +
                `</div>` +
              `</div>`;
            card.appendChild(head);

            const bar = document.createElement('div');
            bar.className = 'score-component-bar';
            const fill = document.createElement('span');
            fill.style.width = `${Math.max(0, Math.min(100, score))}%`;
            bar.appendChild(fill);
            card.appendChild(bar);

            if (contributions.length) {
              const contribList = document.createElement('div');
              contribList.className = 'score-contrib-list';
              contributions.forEach((contribution) => {
                const item = document.createElement('div');
                item.className = 'score-contrib';
                item.innerHTML =
                  `<div class="score-contrib-label">${humanizeScoreKey(contribution.key || '')}</div>` +
                  `<div class="score-contrib-points">+${Number(contribution.points || 0)}</div>`;
                contribList.appendChild(item);
              });
              card.appendChild(contribList);
            } else {
              const emptyNote = document.createElement('div');
              emptyNote.className = 'score-empty-note';
              emptyNote.textContent = 'Sin contribuciones detalladas en este componente.';
              card.appendChild(emptyNote);
            }

            componentList.appendChild(card);
          });
        } else {
          const emptyNote = document.createElement('div');
          emptyNote.className = 'score-empty-note';
          emptyNote.textContent = 'Sin componentes estructurados para el score.';
          componentList.appendChild(emptyNote);
        }
        visualPanel.appendChild(componentList);

        const jsonPanel = document.createElement('div');
        jsonPanel.className = 'score-detail-json';
        jsonPanel.hidden = true;
        const pre = document.createElement('pre');
        pre.className = 'event-snippet';
        pre.textContent = JSON.stringify(details, null, 2);
        jsonPanel.appendChild(pre);

        visualBtn.addEventListener('click', () => {
          visualBtn.classList.add('is-active');
          jsonBtn.classList.remove('is-active');
          visualPanel.hidden = false;
          jsonPanel.hidden = true;
        });
        jsonBtn.addEventListener('click', () => {
          jsonBtn.classList.add('is-active');
          visualBtn.classList.remove('is-active');
          visualPanel.hidden = true;
          jsonPanel.hidden = false;
        });

        panels.appendChild(visualPanel);
        panels.appendChild(jsonPanel);
        shell.appendChild(panels);
        return shell;
      };
      const render = (index) => {
        const safeIndex = Number(index);
        const event = Number.isInteger(safeIndex) ? rows[safeIndex] : null;
        if (!event) return;
        const groupKey = getEventGroupKey(event);
        currentGroupIndices = groupKey && eventGroups.has(groupKey) ? eventGroups.get(groupKey).slice() : [safeIndex];
        currentReviewScope = currentGroupIndices.length > 1 ? currentReviewScope : 'single';
        feed.querySelectorAll('.event-feed-item').forEach((item) => {
          item.classList.toggle('is-active', Number(item.getAttribute('data-event-index')) === safeIndex);
        });
        empty.hidden = true;
        detail.hidden = false;
        setText('event-title', event.hostname, '(sin dominio)');
        renderSummary(event);
        setText('event-time', event.activity_at || event.received_at, '-');
        setText('event-country', event.country, '-');
        setText('event-url', event.url, '-');
        setText('event-prev-url', event.previous_url, '-');
        const isManualReport = String(event.event_type || '') === 'manual_report';
        setText('event-ip', isManualReport ? event.ip : '-', '-');
        setText('event-extension', isManualReport ? event.extension_version : '-', '-');

        const domainHistory = byId('event-domain-history');
        if (domainHistory) {
          const blocked = Boolean(event.host_blocked_before);
          const total = Number(event.host_total_count || 0);
          const count = Number(event.host_blocked_count || 0);
          const lastAt = String(event.host_last_blocked_at || '');
          domainHistory.textContent = blocked
            ? `SI (${count} bloqueos / ${total} reportes${lastAt ? `, ultimo ${lastAt}` : ''})`
            : `No (${total} reportes)`;
        }
        const ipHistory = byId('event-ip-history');
        if (ipHistory) {
          const blocked = Boolean(event.ip_blocked_before);
          const total = Number(event.ip_total_count || 0);
          const count = Number(event.ip_blocked_count || 0);
          const lastAt = String(event.ip_last_blocked_at || '');
          ipHistory.textContent = blocked
            ? `SI (${count} bloqueos / ${total} reportes${lastAt ? `, ultimo ${lastAt}` : ''})`
            : `No (${total} reportes)`;
        }

        const iocWrap = byId('event-ioc');
        if (iocWrap) {
          const isUnsafeDownload = String(event.event_type || '') === 'unsafe_download';
          if (isUnsafeDownload) {
            const ioc = event.download_ioc || {};
            setText('event-ioc-hash', ioc.hash, 'No disponible');
            setText('event-ioc-name', ioc.filename || event.detected_content, '-');
            setText('event-ioc-path', ioc.path, '-');
            setText('event-ioc-site', ioc.url || ioc.site || event.url || event.hostname, '-');
            setText('event-ioc-date', event.activity_at || event.received_at, '-');
            iocWrap.hidden = false;
          } else {
            iocWrap.hidden = true;
          }
        }

        setBadges(event);
        setList('event-reasons', Array.isArray(event.reason_list) && event.reason_list.length ? event.reason_list : [event.message || 'Sin motivo clasificado'], 'Sin motivos.');
        setList('event-snippets', Array.isArray(event.snippets) ? event.snippets : [], 'Sin snippets almacenados.');
        setList('event-signals', Array.isArray(event.signals) ? event.signals : [], 'Sin signals capturados.');

        const scoreDetails = byId('event-score-details');
        if (scoreDetails) {
          scoreDetails.innerHTML = '';
          const details = event.score_details;
          if (details && typeof details === 'object') {
            scoreDetails.appendChild(buildScoreDetailsView(details));
          } else if (typeof details === 'string' && details.trim() !== '') {
            const pre = document.createElement('pre');
            pre.className = 'event-snippet';
            pre.textContent = details;
            scoreDetails.appendChild(pre);
          } else {
            const div = document.createElement('div');
            div.className = 'event-empty';
            div.textContent = 'Sin detalle de score.';
            scoreDetails.appendChild(div);
          }
        }

        setText('event-context-title', 'Contexto del evento', 'Contexto del evento');
        const contextNode = byId('event-context');
        if (contextNode) {
          contextNode.textContent = String(event.detected_content || event.full_context || 'Sin contexto capturado.');
        }
        const rawNode = byId('event-raw');
        if (rawNode) {
          rawNode.textContent = JSON.stringify(event, null, 2);
        }
        const relatedStatus = byId('event-related-status');
        if (relatedStatus) {
          relatedStatus.textContent = 'No se cargan autom?ticamente. Pulsa "Ver relacionadas" para consultar historial relacionado.';
        }
        const relatedWrap = byId('event-related-wrap');
        if (relatedWrap) {
          relatedWrap.hidden = true;
        }
        const relatedBody = byId('event-related-body');
        if (relatedBody) {
          relatedBody.innerHTML = '';
        }
        const relatedLoad = byId('event-related-load');
        if (relatedLoad) {
          relatedLoad.dataset.reportId = String(event.id || '');
          relatedLoad.disabled = false;
        }
        const reviewId = byId('event-review-id');
        if (reviewId) {
          reviewId.value = String(event.id || '');
        }
        syncReviewScopeUi();
        const reviewStatus = byId('event-review-status');
        if (reviewStatus) {
          const next = String(event.review_status || 'pending');
          reviewStatus.value = ['pending', 'accepted', 'rejected', 'allowlisted'].includes(next) ? next : 'pending';
        }
        document.querySelectorAll('[data-event-report-id]').forEach((input) => {
          input.value = String(event.id || '');
        });
      };

      let activeSeverityFilter = 'all';
      const applySeverityFilter = () => {
        const groups = [...feed.querySelectorAll('.event-group')];
        let visibleCount = 0;
        groups.forEach((group) => {
          const bucket = String(group.getAttribute('data-severity') || 'low');
          const visible = activeSeverityFilter === 'all' || bucket === activeSeverityFilter;
          group.hidden = !visible;
          if (visible) {
            visibleCount++;
          }
        });
        if (!visibleCount) {
          empty.hidden = false;
          empty.textContent = 'No hay eventos en esta severidad.';
          detail.hidden = true;
          return;
        }
        empty.hidden = true;
        const activeItem = feed.querySelector('.event-feed-item.is-active');
        const activeGroup = activeItem ? activeItem.closest('.event-group') : null;
        if (!activeGroup || activeGroup.hidden) {
          const firstVisible = feed.querySelector('.event-group:not([hidden]) .event-feed-item');
          if (firstVisible) {
            render(Number(firstVisible.getAttribute('data-event-index')));
          }
        }
      };

      feed.addEventListener('click', (ev) => {
        const target = ev.target;
        if (!(target instanceof Element)) return;
        const toggle = target.closest('.event-group-toggle');
        if (toggle) {
          const group = toggle.closest('.event-group');
          const items = group ? group.querySelector('.event-group-items') : null;
          if (!items) return;
          const willOpen = items.hasAttribute('hidden');
          if (willOpen) {
            items.removeAttribute('hidden');
            toggle.setAttribute('aria-expanded', 'true');
            toggle.textContent = 'Ocultar';
          } else {
            items.setAttribute('hidden', '');
            toggle.setAttribute('aria-expanded', 'false');
            const count = Math.max(0, items.querySelectorAll('.event-feed-item').length);
            toggle.textContent = `Ver ${count} mas`;
          }
          return;
        }
        const item = target.closest('.event-feed-item');
        if (!item) return;
        render(Number(item.getAttribute('data-event-index')));
      });

      const reviewScope = byId('event-review-scope');
      if (reviewScope) {
        reviewScope.addEventListener('click', (ev) => {
          const target = ev.target;
          if (!(target instanceof Element)) return;
          const button = target.closest('[data-review-scope]');
          if (!button) return;
          currentReviewScope = button.getAttribute('data-review-scope') === 'group' ? 'group' : 'single';
          syncReviewScopeUi();
        });
      }

      const reviewForm = byId('event-review-form');
      if (reviewForm) {
        reviewForm.addEventListener('submit', (ev) => {
          const actionableGroup = currentGroupIndices.filter((idx) => {
            const row = rows[idx];
            return row && Number(row.id || 0) > 0;
          });
          if (currentReviewScope === 'group' && actionableGroup.length > 1) {
            const statusNode = byId('event-review-status');
            const nextStatus = String(statusNode?.value || 'pending');
            if (!window.confirm(`Aplicar el veredicto "${nextStatus}" a las ${actionableGroup.length} alertas del grupo?`)) {
              ev.preventDefault();
              return;
            }
          }
          syncReviewScopeUi();
        });
      }

      if (severityFilter) {
        severityFilter.addEventListener('click', (ev) => {
          const target = ev.target;
          if (!(target instanceof Element)) return;
          const button = target.closest('[data-ops-severity]');
          if (!button) return;
          activeSeverityFilter = String(button.getAttribute('data-ops-severity') || 'all');
          severityFilter.querySelectorAll('[data-ops-severity]').forEach((node) => {
            node.classList.toggle('is-active', node === button);
          });
          applySeverityFilter();
        });
      }

      render(0);
      applySeverityFilter();
    })();
  </script>
  <?php if ($enableSidebarGeoMap): ?>
    <script>
      (function () {
        if (!window.L) return;
        const el = document.getElementById('sidebar-geo-map');
        if (!el) return;
        const topCode = (el.getAttribute('data-top-country') || '').toUpperCase();
        const rawCountries = el.getAttribute('data-countries') || '{}';
        let countries = {};
        try { countries = JSON.parse(rawCountries); } catch (e) { countries = {}; }
        const maxHits = parseInt(el.getAttribute('data-max') || '0', 10) || 0;
        const iso2to3 = {
          ES: 'ESP', US: 'USA', DE: 'DEU', SG: 'SGP', BR: 'BRA', FR: 'FRA', IT: 'ITA',
          GB: 'GBR', UK: 'GBR', PT: 'PRT', NL: 'NLD', BE: 'BEL', CH: 'CHE', AT: 'AUT',
          SE: 'SWE', NO: 'NOR', FI: 'FIN', DK: 'DNK', IE: 'IRL', PL: 'POL', CZ: 'CZE',
          RO: 'ROU', BG: 'BGR', GR: 'GRC', HU: 'HUN', UA: 'UKR', RU: 'RUS', CN: 'CHN',
          JP: 'JPN', KR: 'KOR', AU: 'AUS', NZ: 'NZL', MX: 'MEX', AR: 'ARG', CL: 'CHL',
          CO: 'COL', PE: 'PER', VE: 'VEN', ZA: 'ZAF', EG: 'EGY', MA: 'MAR', DZ: 'DZA',
          TR: 'TUR', IL: 'ISR', AE: 'ARE', SA: 'SAU', IN: 'IND', ID: 'IDN', TH: 'THA',
          MY: 'MYS', PH: 'PHL', VN: 'VNM', CA: 'CAN'
        };
        const iso3to2 = Object.entries(iso2to3).reduce((acc, [iso2, iso3]) => {
          if (!acc[iso3]) acc[iso3] = iso2;
          return acc;
        }, {});
        const normalizeIso2 = code => {
          const normalized = String(code || '').trim().toUpperCase();
          if (/^[A-Z]{2}$/.test(normalized)) return normalized;
          if (/^[A-Z]{3}$/.test(normalized) && iso3to2[normalized]) return iso3to2[normalized];
          return '';
        };
        const normalizeIso3 = code => {
          const normalized = String(code || '').trim().toUpperCase();
          if (/^[A-Z]{3}$/.test(normalized)) return normalized;
          if (/^[A-Z]{2}$/.test(normalized) && iso2to3[normalized]) return iso2to3[normalized];
          return '';
        };
        const normalizedCountries = {};
        Object.entries(countries || {}).forEach(([rawCode, rawHits]) => {
          const hits = Number(rawHits || 0);
          if (!Number.isFinite(hits) || hits <= 0) return;
          const iso2 = normalizeIso2(rawCode);
          const iso3 = normalizeIso3(rawCode);
          if (iso2) normalizedCountries[iso2] = Math.max(Number(normalizedCountries[iso2] || 0), hits);
          if (iso3) normalizedCountries[iso3] = Math.max(Number(normalizedCountries[iso3] || 0), hits);
        });
        countries = normalizedCountries;
        const targetCode2 = normalizeIso2(topCode);
        const targetCode3 = normalizeIso3(topCode);
        const map = L.map(el, {
          zoomControl: false,
          attributionControl: false,
          dragging: false,
          scrollWheelZoom: false,
          doubleClickZoom: false,
          boxZoom: false,
          keyboard: false,
          tap: false
        }).setView([18, 8], 1.45);
        const colorForIntensity = (intensity) => {
          if (intensity >= 0.85) return '#ff5f6d';
          if (intensity >= 0.6) return '#ff9f43';
          if (intensity >= 0.35) return '#ffd166';
          if (intensity > 0) return '#34d399';
          return '#17212b';
        };
        fetch('<?= clickfix_h($leafletWorldGeoJsonUrl); ?>')
          .then(r => r.json())
          .then(data => {
            const layer = L.geoJSON(data, {
              style: feature => {
                const code3 = (feature.id || '').toUpperCase();
                const code2 = normalizeIso2((feature.properties && (feature.properties.ISO_A2 || feature.properties.iso_a2)) || '');
                const code3Normalized = normalizeIso3(code3);
                const hitValue = Math.max(
                  code2 ? Number(countries[code2] || 0) : 0,
                  code3Normalized ? Number(countries[code3Normalized] || 0) : 0
                );
                const intensity = maxHits > 0 ? Math.min(1, hitValue / maxHits) : 0;
                const isTop =
                  (targetCode3 !== '' && code3Normalized === targetCode3)
                  || (targetCode2 !== '' && code2 === targetCode2);
                const fill = colorForIntensity(intensity);
                return {
                  color: isTop ? '#ecfeff' : '#324556',
                  weight: isTop ? 1.5 : 0.75,
                  fillColor: isTop ? '#ff5f6d' : fill,
                  fillOpacity: intensity > 0 ? 0.82 : 0.48
                };
              },
              onEachFeature: (feature, featureLayer) => {
                const code2 = normalizeIso2((feature.properties && (feature.properties.ISO_A2 || feature.properties.iso_a2)) || '');
                const code3 = normalizeIso3(feature.id || '');
                const hitValue = Math.max(
                  code2 ? Number(countries[code2] || 0) : 0,
                  code3 ? Number(countries[code3] || 0) : 0
                );
                const countryName =
                  String(feature.properties?.ADMIN || feature.properties?.NAME || code2 || code3 || 'PaÃ­s');
                featureLayer.bindTooltip(`${countryName}: ${hitValue} eventos`, {
                  sticky: true,
                  direction: 'auto',
                  opacity: 0.92
                });
              }
            }).addTo(map);
            if (layer.getBounds && layer.getBounds().isValid()) {
              map.fitWorld({ padding: [4, 4] });
            }
          })
          .catch(() => {});
      })();
    </script>
  <?php endif; ?>
</body>
</html>
<?php
$dashboardOutput = ob_get_clean();
echo cfdashboardtranslateoutput($dashboardOutput, (string) $lang);




