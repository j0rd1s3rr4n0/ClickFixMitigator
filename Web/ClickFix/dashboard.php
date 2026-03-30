<?php
declare(strict_types=1);

require_once __DIR__ . '/src/clickfix_core.php';
clickfix_bootstrap();

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
        '/((?:["\']?\b(?:password|passwd|pwd|pass|contrasena|contrase(?:n|Ã±)a|clave)\b["\']?)\s*[:=]\s*)(?:"[^"]*"|\'[^\']*\'|[^\s,;|]+)/iu',
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
        clickfix_flash('Tu sesion ha sido cerrada porque se inicio en otro dispositivo.');
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
$publicPages = ['home', 'search', 'about', 'coverage', 'access', 'investigation', 'profile'];
$pageAccess = [
    'settings' => 'analyst_jr',
    'ops' => 'analyst_jr',
    'analytics' => 'analyst_jr',
    'intel_stats' => 'analyst_jr',
    'intel' => 'analyst_jr',
    'community' => 'analyst_jr',
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
        clickfix_flash('Sesion expirada por inactividad.');
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
            clickfix_flash('Sesion iniciada.');
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
        clickfix_flash('Sesion cerrada.');
        clickfix_redirect('dashboard.php?page=home&public=1');
    }
    if (!clickfix_verify_csrf($csrf)) {
        clickfix_flash('CSRF invalido.');
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
        clickfix_flash($ok ? 'Contraseña actualizada.' : 'No se pudo cambiar la contraseña (revisa tu clave actual y el minimo de 10 caracteres).');
        clickfix_redirect('dashboard.php?page=settings');
    }

    if ($action === 'user_self_profile_update') {
        $ok = clickfix_user_update_public_profile($pdo, $actorId, [
            'full_name' => (string) ($_POST['full_name'] ?? ''),
            'profile_email_public' => (string) ($_POST['profile_email_public'] ?? '0'),
            'profile_threatrip_public' => (string) ($_POST['profile_threatrip_public'] ?? '0'),
            'profile_threatrip_id' => (string) ($_POST['profile_threatrip_id'] ?? ''),
            'profile_vt_public' => (string) ($_POST['profile_vt_public'] ?? '0'),
            'profile_vt_handle' => (string) ($_POST['profile_vt_handle'] ?? ''),
            'profile_abuseipdb_public' => (string) ($_POST['profile_abuseipdb_public'] ?? '0'),
            'profile_abuseipdb_id' => (string) ($_POST['profile_abuseipdb_id'] ?? ''),
            'profile_github_public' => (string) ($_POST['profile_github_public'] ?? '0'),
            'profile_github_handle' => (string) ($_POST['profile_github_handle'] ?? ''),
        ]);
        clickfix_flash($ok ? 'Perfil actualizado.' : 'No se pudo actualizar el perfil.');
        clickfix_redirect('dashboard.php?page=profile&user_id=' . $actorId);
    }

    if ($action === 'investigation_submit_community') {
        $graphId = (int) ($_POST['graph_id'] ?? 0);
        if ($graphId <= 0) {
            clickfix_flash('Investigacion no valida para enviar a comunidad.');
            clickfix_redirect('dashboard.php?page=intel');
        }
        $ok = clickfix_investigation_submit_community(
            $pdo,
            $graphId,
            $actorId,
            (string) ($actor['role'] ?? 'analyst_jr'),
            clickfix_is_admin()
        );
        clickfix_flash($ok ? 'Investigacion enviada a Community (fase JR).' : 'No se pudo enviar la investigacion a Community.');
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
            clickfix_flash('No se pudo ejecutar la accion: report_id invalido.');
            clickfix_redirect('dashboard.php?page=' . urlencode($postReturnPage));
        }
        $report = clickfix_report_by_id($pdo, $reportId);
        if ($report === null) {
            clickfix_flash('No se encontro la alerta para esa accion rapida.');
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
                clickfix_flash('No se pudo bloquear: la alerta no tiene dominio valido.');
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
                clickfix_flash('No se pudo anadir a investigatelist: dominio invalido.');
                clickfix_redirect('dashboard.php?page=' . urlencode($postReturnPage) . '&report_id=' . $reportId);
            }
            $ok = clickfix_apply_list_action($pdo, $actorId, 'investigatelist', 'add', $domain, 'quick action from report #' . $reportId);
            clickfix_flash($ok ? ('Dominio enviado a investigacion: ' . $domain) : 'No se pudo actualizar investigatelist.');
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
                'Investigacion generada desde alerta #' . $reportId . '.',
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
                'Investigacion alerta #' . $reportId . ' - ' . $domainForInvestigation,
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
                    clickfix_flash('Investigacion creada desde alerta #' . $reportId . ' y correlacion encolada (#' . $queuedJobId . ').');
                } else {
                    clickfix_flash('Investigacion creada desde alerta #' . $reportId . '. No se pudo encolar la correlacion automatica.');
                }
                clickfix_redirect('dashboard.php?page=intel&graph_id=' . $savedId);
            }
            clickfix_flash('No se pudo crear la investigacion automatica.');
            clickfix_redirect('dashboard.php?page=' . urlencode($postReturnPage) . '&report_id=' . $reportId);
        }

        clickfix_flash('Accion rapida no valida.');
        clickfix_redirect('dashboard.php?page=' . urlencode($postReturnPage) . '&report_id=' . $reportId);
    }
    if ($action === 'extension_link_add') {
        if (!$canManageExtensionLinks) {
            clickfix_flash('Permisos insuficientes para asociar usuarios de extension.');
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
        clickfix_flash($ok ? 'Asociacion guardada.' : 'No se pudo guardar la asociacion.');
        $redirect = 'dashboard.php?page=extensions';
        if ($targetClient !== '') {
            $redirect .= '&client_id=' . urlencode($targetClient);
        }
        clickfix_redirect($redirect);
    }
    if ($action === 'extension_link_remove') {
        if (!$canManageExtensionLinks) {
            clickfix_flash('Permisos insuficientes para editar asociaciones de extension.');
            clickfix_redirect('dashboard.php?page=ops');
        }
        $ok = clickfix_unlink_user_extension_client($pdo, (int) ($_POST['link_id'] ?? 0));
        clickfix_flash($ok ? 'Asociacion desactivada.' : 'No se pudo desactivar la asociacion.');
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
            clickfix_flash('No se pudo actualizar la revision: alerta no encontrada.');
            clickfix_redirect('dashboard.php?page=' . urlencode($postReturnPage));
        }
        $ok = clickfix_update_report_review($pdo, $reportId, $status, $actorId);
        if ($ok && $status === 'accepted') {
            clickfix_flash('Revision actualizada. El dominio ha quedado bloqueado automaticamente si era valido.');
        } else {
            clickfix_flash($ok ? 'Revision actualizada.' : 'No hubo cambios en la revision (verifica el evento y el estado).');
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
            clickfix_flash('Selecciona al menos una alerta para revision masiva.');
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
        $bulkMessage = 'Revision masiva aplicada: ' . $updatedCount . '/' . $totalCount . ($failedCount > 0 ? (' (' . $failedCount . ' sin cambios)') : '') . '.';
        if ($status === 'accepted' && $updatedCount > 0) {
            $bulkMessage .= ' Los dominios aceptados se han bloqueado automaticamente cuando eran validos.';
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
        clickfix_flash('Accion masiva: ' . (int) ($bulk['applied'] ?? 0) . '/' . (int) ($bulk['total'] ?? 0) . ' aplicada.');
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
        clickfix_flash($ok ? 'Revision de captura actualizada.' : 'No se pudo actualizar la revision de captura.');
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
            clickfix_flash('No se recibio un archivo valido para la captura.');
            clickfix_redirect('dashboard.php?page=' . urlencode($returnPage));
        }
        $tmpName = (string) ($upload['tmp_name'] ?? '');
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            clickfix_flash('Archivo temporal de subida no valido.');
            clickfix_redirect('dashboard.php?page=' . urlencode($returnPage));
        }
        $size = (int) ($upload['size'] ?? 0);
        if ($size <= 0 || $size > 8 * 1024 * 1024) {
            clickfix_flash('Tamano de captura invalido (max 8 MB).');
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
        ];
        $ext = (string) ($extByMime[$mime] ?? '');
        if ($ext === '') {
            $name = strtolower((string) ($upload['name'] ?? ''));
            if (preg_match('/\.png$/', $name)) {
                $ext = 'png';
            } elseif (preg_match('/\.jpe?g$/', $name)) {
                $ext = 'jpg';
            } elseif (preg_match('/\.webp$/', $name)) {
                $ext = 'webp';
            }
        }
        if ($ext === '') {
            clickfix_flash('Formato no permitido. Usa PNG, JPG o WEBP.');
            clickfix_redirect('dashboard.php?page=' . urlencode($returnPage));
        }
        $bytes = @file_get_contents($tmpName);
        if (!is_string($bytes) || $bytes === '') {
            clickfix_flash('No se pudo leer el archivo subido.');
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
            clickfix_flash('Permisos insuficientes para mensajeria.');
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
                clickfix_flash('No se pudo enviar: indica uno o varios client_id validos.');
            } else {
                clickfix_flash('No se pudo enviar el mensaje.');
            }
        }
        clickfix_redirect('dashboard.php?page=messaging');
    }
    if ($action === 'message_delete') {
        if (!$canManageMessaging) {
            clickfix_flash('Permisos insuficientes para mensajeria.');
            clickfix_redirect('dashboard.php?page=ops');
        }
        $messageId = (int) ($_POST['message_id'] ?? 0);
        $ok = clickfix_deactivate_extension_message($pdo, $messageId);
        clickfix_flash($ok ? 'Entrega detenida: el mensaje ya no se enviara a mas extensiones.' : 'No se pudo detener la entrega del mensaje.');
        clickfix_redirect('dashboard.php?page=messaging');
    }
    if ($action === 'message_hard_delete') {
        if (!$canManageMessaging) {
            clickfix_flash('Permisos insuficientes para mensajeria.');
            clickfix_redirect('dashboard.php?page=ops');
        }
        $messageId = (int) ($_POST['message_id'] ?? 0);
        $ok = clickfix_delete_extension_message($pdo, $messageId);
        clickfix_flash($ok ? 'Mensaje eliminado de la plataforma.' : 'No se pudo eliminar el mensaje de la plataforma.');
        clickfix_redirect('dashboard.php?page=messaging');
    }
    if ($action === 'message_edit') {
        if (!$canManageMessaging) {
            clickfix_flash('Permisos insuficientes para mensajeria.');
            clickfix_redirect('dashboard.php?page=ops');
        }
        $messageId = (int) ($_POST['message_id'] ?? 0);
        $active = ((string) ($_POST['msg_active'] ?? '1')) === '1';
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
            clickfix_flash('Permisos insuficientes para mensajeria.');
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
            'enabled_global' => ((string) ($_POST['ads_enabled_global'] ?? '0')) === '1',
            'show_guest' => ((string) ($_POST['ads_show_guest'] ?? '0')) === '1',
            'show_analyst_jr' => ((string) ($_POST['ads_show_analyst_jr'] ?? '0')) === '1',
            'show_analyst_mid' => ((string) ($_POST['ads_show_analyst_mid'] ?? '0')) === '1',
            'show_analyst_sr' => ((string) ($_POST['ads_show_analyst_sr'] ?? '0')) === '1',
            'show_admin' => ((string) ($_POST['ads_show_admin'] ?? '0')) === '1',
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
            'active' => ((string) ($_POST['ad_active'] ?? '1')) === '1',
            'target_guest' => ((string) ($_POST['ad_target_guest'] ?? '0')) === '1',
            'target_analyst_jr' => ((string) ($_POST['ad_target_analyst_jr'] ?? '0')) === '1',
            'target_analyst_mid' => ((string) ($_POST['ad_target_analyst_mid'] ?? '0')) === '1',
            'target_analyst_sr' => ((string) ($_POST['ad_target_analyst_sr'] ?? '0')) === '1',
            'target_admin' => ((string) ($_POST['ad_target_admin'] ?? '0')) === '1',
        ], $actorId);
        clickfix_flash($ok ? 'Anuncio guardado.' : 'No se pudo guardar el anuncio. Revisa titulo, contenido, URL o targets.');
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
            ((string) ($_POST['enabled'] ?? '1')) === '1'
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
            'include_subdomains' => ((string) ($_POST['purge_include_subdomains'] ?? '1')) === '1',
            'include_url' => ((string) ($_POST['purge_include_url'] ?? '1')) === '1',
            'include_previous_url' => ((string) ($_POST['purge_include_previous_url'] ?? '1')) === '1',
            'delete_caches' => ((string) ($_POST['purge_delete_caches'] ?? '1')) === '1',
            'delete_investigations' => ((string) ($_POST['purge_delete_investigations'] ?? '0')) === '1',
        ]);
        if (($result['host'] ?? '') === '') {
            clickfix_flash('Dominio invalido. Usa un dominio o URL valida.');
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
            clickfix_flash('Investigacion guardada.');
            clickfix_redirect('dashboard.php?page=intel&graph_id=' . (int) $savedId);
        }
        if ($autoSave) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['status' => 'error', 'message' => 'save_failed']);
            exit;
        }
        clickfix_flash('No se pudo guardar la investigacion.');
        clickfix_redirect('dashboard.php?page=intel');
    }
    if ($action === 'investigation_delete') {
        if (!clickfix_user_has_min_role($actor, 'analyst_jr')) {
            clickfix_flash('Permisos insuficientes para eliminar investigaciones.');
            clickfix_redirect('dashboard.php?page=home&public=1');
        }
        $graphId = (int) ($_POST['graph_id'] ?? 0);
        if (clickfix_investigation_delete($pdo, $graphId, $actorId, clickfix_is_admin())) {
            clickfix_flash('Investigacion eliminada.');
        } else {
            clickfix_flash('No se pudo eliminar la investigacion.');
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
            clickfix_flash('Enlace publico generado.');
            clickfix_redirect('dashboard.php?page=intel&graph_id=' . $graphId);
        }
        clickfix_flash('Comparticion desactivada.');
        clickfix_redirect('dashboard.php?page=intel&graph_id=' . $graphId);
    }
    if ($action === 'investigation_api_key_save') {
        if (!clickfix_user_has_min_role($actor, 'analyst_jr')) {
            clickfix_flash('Permisos insuficientes para gestionar API keys de investigacion.');
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
            clickfix_flash('Permisos insuficientes para gestionar API keys de investigacion.');
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
    if ($action === 'investigation_api_lookup') {
        if (!clickfix_user_has_min_role($actor, 'analyst_jr')) {
            clickfix_flash('Permisos insuficientes para consultas de investigacion.');
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
            clickfix_flash('Investigacion no encontrada.');
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
            clickfix_flash('No se detectaron IOCs validos (dominio, IP o URL).');
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
            clickfix_flash('No se anadieron IOCs nuevos (duplicados o invalidos).');
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
            $summary = 'IOC(s) anadidos: ' . $added;
            if ($skipped > 0) {
                $summary .= ' | omitidos: ' . $skipped;
            }
            clickfix_flash($summary);
        } else {
            clickfix_flash('No se pudo guardar la investigacion con los IOCs nuevos.');
        }
        clickfix_redirect('dashboard.php?page=intel&graph_id=' . $graphId);
    }
    if ($action === 'investigation_ioc_workbench') {
        if (!clickfix_user_has_min_role($actor, 'analyst_jr')) {
            clickfix_flash('Permisos insuficientes para enrichment de investigacion.');
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
                ((string) ($_POST['new_verified'] ?? '1')) === '1',
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
            ((string) ($_POST['edit_verified'] ?? '1')) === '1',
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
$pageNeedsScanReviewQueue = $canAdminViewer && $page === 'home';
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
        clickfix_flash('No hay IOCs exportables en esta investigacion.');
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
            'nav_search' => 'Busqueda',
            'nav_coverage' => 'Cobertura',
            'nav_about' => 'Acerca',
            'nav_access' => 'Acceso',
            'nav_profile' => 'Perfil',
            'nav_settings' => 'Ajustes',
            'nav_ops' => 'Operaciones',
            'nav_graphs' => 'Graficos',
            'nav_intel_stats' => 'Intel Stats',
            'nav_investigation' => 'Investigacion',
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
            'lang_es' => 'Español',
            'lang_en' => 'English',
            'lang_ca' => 'Catala',
            'lang_de' => 'Aleman',
            'lang_fr' => 'Frances',
            'lang_it' => 'Italiano',
            'label_module' => 'Modulo',
            'label_role' => 'Rol',
            'dc_title' => 'Centro de datos',
            'dc_sub' => 'Estado de tablas, volumen y consulta rapida del contenido operacional.',
            'msg_title' => 'Mensajeria para extensiones',
            'cfg_title' => 'Editor de score config',
            'reports_title' => 'Reportes automaticos',
            'support_title' => 'Apoya el proyecto',
            'support_sub' => 'Ayuda a mantener ClickFix con donaciones o patrocinio.',
            'support_ads' => 'Patrocinado',
            'support_donations' => 'Donaciones',
            'support_paypal' => 'Donar con PayPal',
            'support_kofi' => 'Invitar un cafe (Ko-fi)',
            'support_stripe' => 'Aportar con Stripe',
            'intel_api_keys_title' => 'API keys privadas',
            'intel_api_keys_sub' => 'Solo tu usuario puede ver, modificar y usar estas claves en investigacion.',
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
            'intel_iocs_title' => 'IOCs detectados en esta investigacion',
            'intel_iocs_sub' => 'IPs, dominios y URLs extraidos del dominio principal, resumen, nodos, tags y notas. Puedes lanzarlos directamente a proveedores sin copiar/pegar.',
            'intel_iocs_empty' => 'No se han detectado IOCs reutilizables todavia dentro del grafo actual.',
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
            'intel_briefing_sub' => 'Define el foco, la narrativa analitica y los datos clave antes de enriquecer o compartir.',
            'intel_autosave_label' => 'Autosave:',
            'intel_briefing_title_label' => 'Titulo',
            'intel_briefing_domain_label' => 'Dominio principal',
            'intel_briefing_domain_placeholder' => 'ejemplo.com',
            'intel_briefing_verdict_label' => 'Veredicto',
            'intel_briefing_tags_label' => 'Tags globales',
            'intel_briefing_tags_placeholder' => 'phishing, fake-captcha, powershell',
            'intel_briefing_summary_label' => 'Resumen de la investigacion',
            'intel_briefing_summary_placeholder' => 'Explica por que se considera malicioso o no.',
            'intel_briefing_save' => 'Guardar investigacion',
            'intel_enrichment_kicker' => 'Enrichment',
            'intel_enrichment_title' => 'Fuentes de investigacion',
            'intel_enrichment_sub_full' => 'Gestion de credenciales personales, pivotes y consultas historicas de enrichment.',
            'intel_enrichment_sub_lite' => 'Consulta proveedores de inteligencia sin exponer credenciales en interfaz.',
            'intel_platform_api_title' => 'API de plataforma (con API key)',
            'intel_platform_api_sub_line1' => 'Genera API keys personales para consumir /api/intel.php y /api/lookup.php.',
            'intel_platform_api_sub_line2' => 'Se guarda solo hash, se puede revocar y tiene expiracion/rate-limit por clave.',
            'intel_platform_api_sub_line3' => 'Documentacion:',
            'intel_platform_api_docs' => 'API INTEGRATIONS',
            'intel_platform_api_new_title' => 'API key nueva (se muestra una sola vez)',
            'intel_platform_api_new_sub' => 'Copiala ahora y guardala en un vault seguro. No se volvera a mostrar completa.',
            'intel_platform_api_label' => 'Etiqueta',
            'intel_platform_api_expires' => 'Expira en dias (1-365)',
            'intel_platform_api_rpm' => 'Rate limit RPM (30-2000)',
            'intel_platform_api_generate' => 'Generar API key segura',
            'intel_platform_api_hidden' => 'La gestion manual de credenciales y la API de plataforma estan ocultas en esta vista.',
            'announce_title' => 'Anuncios operativos',
            'announce_sub' => 'Lateral, discreto y no intrusivo.',
            'announce_focus_note' => 'Modo investigacion activo: el panel se muestra minimizado para mantener el foco.',
            'announce_empty' => 'No hay anuncios activos.',
            'announce_until' => 'hasta',
            'announce_guest_title' => 'Patrocinios discretos',
            'announce_guest_text' => 'Los anuncios se muestran solo en el lateral para no interrumpir analisis ni investigaciones.',
            'review_pending' => 'pending - pendiente de revision',
            'review_accepted' => 'accepted - malicioso confirmado (ilegitimo)',
            'review_rejected' => 'rejected - legitimo o falso positivo',
            'review_legend_title' => 'Guia de revision',
            'review_legend_pending' => 'pending: evento aun no validado por analista.',
            'review_legend_accepted' => 'accepted: la alerta se confirma como amenaza real (evento ilegitimo/malicioso).',
            'review_legend_rejected' => 'rejected: la alerta se marca como legitima o falso positivo.',
            'about_project_title' => 'Mision y enfoque',
            'about_project_intro' => 'ClickFix Mitigator es una plataforma defensiva para detectar, contener y analizar ataques basados en ingenieria social web (ClickFix y variantes de ejecucion guiada).',
            'about_project_p1' => 'Diseno orientado a operaciones reales: baja friccion para usuario final y alta trazabilidad para equipos SOC.',
            'about_project_p2' => 'Cobertura integral: extension, backend de correlacion, workflows de triage, evidencias y analitica operacional.',
            'about_project_p3' => 'Modelo de madurez: desde prevencion inmediata en navegador hasta investigacion profunda con contexto historico.',
            'about_project_p4' => 'Principio de seguridad: minimo privilegio, control por roles, reduccion de superficie de ataque y auditoria de acciones.',
            'about_project_p5' => 'Objetivo de negocio: reducir riesgo operativo, acelerar respuesta a incidentes y mejorar la resiliencia de usuarios y organizaciones.',
            'about_owner_title' => 'Direccion del proyecto y contacto',
            'about_owner_text' => 'Proyecto mantenido activamente con foco en calidad tecnica, hardening continuo y utilidad practica para investigacion y defensa.',
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
            'review_legend_title' => 'Review guide',
            'review_legend_pending' => 'pending: event has not been validated by an analyst yet.',
            'review_legend_accepted' => 'accepted: alert is confirmed as a real threat (illegitimate/malicious event).',
            'review_legend_rejected' => 'rejected: alert is considered legitimate activity or a false positive.',
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
            'sr_review' => 'Revision Senior',
            'verified_public' => 'Verificada Publica',
            'verified_internal' => 'Verificada Interna',
            'rejected' => 'Rechazada',
        ],
        'ca' => [
            'draft' => 'Esborrany',
            'jr_submitted' => 'Enviat per JR',
            'mid_verified' => 'Validat per Mid',
            'sr_review' => 'Revisio Senior',
            'verified_public' => 'Verificada publica',
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
        'Cerrar sesion' => ['ca' => 'Tancar sessio', 'de' => 'Abmelden', 'fr' => 'Se deconnecter'],
        'ClickFix Command Center' => ['ca' => 'ClickFix Centre de Comandament', 'de' => 'ClickFix Command Center', 'fr' => 'ClickFix Centre de Commandement'],
        'Deteccion, explicabilidad y respuesta en una sola superficie de control.' => ['ca' => 'Deteccio, explicabilitat i resposta en una sola superficie de control.', 'de' => 'Erkennung, Erklarbarkeit und Reaktion auf einer zentralen Oberflache.', 'fr' => 'Detection, explicabilite et reponse sur une surface unique de pilotage.'],
        'Busqueda forense' => ['ca' => 'Cerca forense', 'de' => 'Forensische Suche', 'fr' => 'Recherche forensique'],
        'Operaciones' => ['ca' => 'Operacions', 'de' => 'Betrieb', 'fr' => 'Operations'],
        'Investigacion' => ['ca' => 'Investigacio', 'de' => 'Untersuchung', 'fr' => 'Investigation'],
        'Inicio rapido' => ['ca' => 'Inici rapid', 'de' => 'Schnellstart', 'fr' => 'Demarrage rapide'],
        'Workspace autenticado' => ['ca' => 'Workspace autenticat', 'de' => 'Authentifizierter Workspace', 'fr' => 'Workspace authentifie'],
        'Vista publica' => ['ca' => 'Vista publica', 'de' => 'Offentliche Ansicht', 'fr' => 'Vue publique'],
        'alertas totales' => ['ca' => 'alertes totals', 'de' => 'gesamtwarnungen', 'fr' => 'alertes totales'],
        'bloqueos totales' => ['ca' => 'bloquejos totals', 'de' => 'gesamtblockierungen', 'fr' => 'blocages totaux'],
        'dominios unicos' => ['ca' => 'dominis unics', 'de' => 'eindeutige Domains', 'fr' => 'domaines uniques'],
        'usuarios 24h' => ['ca' => 'usuaris 24h', 'de' => 'Benutzer 24h', 'fr' => 'utilisateurs 24h'],
        'alertas 24h' => ['ca' => 'alertes 24h', 'de' => 'Warnungen 24h', 'fr' => 'alertes 24h'],
        'bloqueos 24h' => ['ca' => 'bloquejos 24h', 'de' => 'Blockierungen 24h', 'fr' => 'blocages 24h'],
        'ratio bloqueo 24h' => ['ca' => 'ratio bloqueig 24h', 'de' => 'Blockierungsquote 24h', 'fr' => 'ratio de blocage 24h'],
        'alto riesgo 24h' => ['ca' => 'alt risc 24h', 'de' => 'hohes Risiko 24h', 'fr' => 'haut risque 24h'],
        'nuevos dominios 24h' => ['ca' => 'nous dominis 24h', 'de' => 'neue Domains 24h', 'fr' => 'nouveaux domaines 24h'],
        'clientes ext 24h' => ['ca' => 'clients ext 24h', 'de' => 'Erweiterungs-Clients 24h', 'fr' => 'clients extension 24h'],
        'revisadas' => ['ca' => 'revisades', 'de' => 'gepruft', 'fr' => 'revues'],
        'cobertura revision' => ['ca' => 'cobertura revisio', 'de' => 'Review-Abdeckung', 'fr' => 'couverture revue'],
        'sitios manuales' => ['ca' => 'llocs manuals', 'de' => 'manuelle Sites', 'fr' => 'sites manuels'],
        'pendientes' => ['ca' => 'pendents', 'de' => 'ausstehend', 'fr' => 'en attente'],
        'Pendientes reales (fuera de allowlist/blocklist)' => ['ca' => 'Pendents reals (fora de allowlist/blocklist)', 'de' => 'Reale offene Falle (ausserhalb allowlist/blocklist)', 'fr' => 'En attente reels (hors allowlist/blocklist)'],
        'Ultimo escaneo' => ['ca' => 'Ultim escaneig', 'de' => 'Letzter Scan', 'fr' => 'Dernier scan'],
        'Sin capturas disponibles.' => ['ca' => 'Sense captures disponibles.', 'de' => 'Keine Screenshots verfugbar.', 'fr' => 'Aucune capture disponible.'],
        'Antes' => ['ca' => 'Abans', 'de' => 'Vorher', 'fr' => 'Avant'],
        'Despues' => ['ca' => 'Despres', 'de' => 'Nachher', 'fr' => 'Apres'],
        'Ver aqui (manual)' => ['ca' => 'Veure aqui (manual)', 'de' => 'Hier ansehen (manuell)', 'fr' => 'Voir ici (manuel)'],
        'Descargar' => ['ca' => 'Descarregar', 'de' => 'Herunterladen', 'fr' => 'Telecharger'],
        'Guardar revision' => ['ca' => 'Desar revisio', 'de' => 'Review speichern', 'fr' => 'Enregistrer la revue'],
        'Eliminar captura' => ['ca' => 'Eliminar captura', 'de' => 'Screenshot loschen', 'fr' => 'Supprimer capture'],
        'Aprobar y usar en publico' => ['ca' => 'Aprovar i usar en public', 'de' => 'Freigeben und offentlich nutzen', 'fr' => 'Approuver et utiliser en public'],
        'Mapa usuarios extension' => ['ca' => 'Mapa usuaris extensio', 'de' => 'Karte Erweiterungs-Benutzer', 'fr' => 'Carte utilisateurs extension'],
        'Mapa webs detectadas' => ['ca' => 'Mapa webs detectades', 'de' => 'Karte erkannter Webseiten', 'fr' => 'Carte des sites detectes'],
        'Graficos globales (14 dias)' => ['ca' => 'Grafics globals (14 dies)', 'de' => 'Globale Diagramme (14 Tage)', 'fr' => 'Graphiques globaux (14 jours)'],
        'Tendencia diaria' => ['ca' => 'Tendencia diaria', 'de' => 'Taglicher Trend', 'fr' => 'Tendance quotidienne'],
        'Ratio de bloqueo por dia' => ['ca' => 'Ratio de bloqueig per dia', 'de' => 'Blockierungsquote pro Tag', 'fr' => 'Ratio de blocage par jour'],
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
        'Cambiar mi contraseña' => ['ca' => 'Canviar la meva contrasenya', 'de' => 'Mein Passwort andern', 'fr' => 'Changer mon mot de passe'],
        'Perfil no encontrado' => ['ca' => 'Perfil no trobat', 'de' => 'Profil nicht gefunden', 'fr' => 'Profil introuvable'],
        'Editar perfil' => ['ca' => 'Editar perfil', 'de' => 'Profil bearbeiten', 'fr' => 'Modifier profil'],
        'Guardar perfil' => ['ca' => 'Desar perfil', 'de' => 'Profil speichern', 'fr' => 'Enregistrer profil'],
        'Investigacion no disponible' => ['ca' => 'Investigacio no disponible', 'de' => 'Untersuchung nicht verfugbar', 'fr' => 'Investigation non disponible'],
        'Grafo de investigacion' => ['ca' => 'Graf dinvestigacio', 'de' => 'Untersuchungsgraph', 'fr' => 'Graphe dinvestigation'],
        'Detalle del nodo seleccionado' => ['ca' => 'Detall del node seleccionat', 'de' => 'Details des ausgewahlten Knotens', 'fr' => 'Detail du noeud selectionne'],
        'Sin nodo seleccionado.' => ['ca' => 'Cap node seleccionat.', 'de' => 'Kein Knoten ausgewahlt.', 'fr' => 'Aucun noeud selectionne.'],
        'Investigaciones de sitios' => ['ca' => 'Investigacions de llocs', 'de' => 'Site-Untersuchungen', 'fr' => 'Investigations de sites'],
        'Case queue' => ['ca' => 'Cua de casos', 'de' => 'Fall-Warteschlange', 'fr' => 'File de cas'],
        'Nueva investigacion' => ['ca' => 'Nova investigacio', 'de' => 'Neue Untersuchung', 'fr' => 'Nouvelle investigation'],
        'Guardar investigacion' => ['ca' => 'Desar investigacio', 'de' => 'Untersuchung speichern', 'fr' => 'Enregistrer investigation'],
        'Eventos recientes' => ['ca' => 'Esdeveniments recents', 'de' => 'Neueste Ereignisse', 'fr' => 'Evenements recents'],
        'Sin eventos recientes.' => ['ca' => 'Sense esdeveniments recents.', 'de' => 'Keine aktuellen Ereignisse.', 'fr' => 'Aucun evenement recent.'],
        'Motivos detectados' => ['ca' => 'Motius detectats', 'de' => 'Erkannte Grunde', 'fr' => 'Motifs detectes'],
        'Snippets detectados' => ['ca' => 'Snippets detectats', 'de' => 'Erkannte Snippets', 'fr' => 'Snippets detectes'],
        'Ver relacionadas' => ['ca' => 'Veure relacionades', 'de' => 'Verwandte anzeigen', 'fr' => 'Voir liees'],
        'Bloquear dominio' => ['ca' => 'Bloquejar domini', 'de' => 'Domain blockieren', 'fr' => 'Bloquer domaine'],
        'Mandar a investigacion' => ['ca' => 'Enviar a investigacio', 'de' => 'Zur Untersuchung senden', 'fr' => 'Envoyer en investigation'],
        'Generar investigacion' => ['ca' => 'Generar investigacio', 'de' => 'Untersuchung erzeugen', 'fr' => 'Generer investigation'],
        'Eliminar deteccion' => ['ca' => 'Eliminar deteccio', 'de' => 'Erkennung loschen', 'fr' => 'Supprimer detection'],
        'Capturas web (before/after)' => ['ca' => 'Captures web (before/after)', 'de' => 'Web-Screenshots (before/after)', 'fr' => 'Captures web (before/after)'],
        'Vista tabular clasica' => ['ca' => 'Vista tabular classica', 'de' => 'Klassische Tabellenansicht', 'fr' => 'Vue tabulaire classique'],
        'Solo pendientes' => ['ca' => 'Nomes pendents', 'de' => 'Nur ausstehend', 'fr' => 'Seulement en attente'],
        'Aplicar veredicto masivo' => ['ca' => 'Aplicar veredicte massiu', 'de' => 'Massenverdict anwenden', 'fr' => 'Appliquer verdict massif'],
        'Graficos y metricas operativas' => ['ca' => 'Grafics i metriques operatives', 'de' => 'Operative Diagramme und Metriken', 'fr' => 'Graphiques et metriques operationnelles'],
        'Detector de anomalias (24h vs baseline)' => ['ca' => 'Detector danomalies (24h vs baseline)', 'de' => 'Anomalie-Detektor (24h vs Baseline)', 'fr' => 'Detecteur danomalies (24h vs baseline)'],
        'Keywords por ventana temporal' => ['ca' => 'Keywords per finestra temporal', 'de' => 'Keywords nach Zeitfenster', 'fr' => 'Keywords par fenetre temporelle'],
        'Predicciones de riesgo (Top)' => ['ca' => 'Prediccions de risc (Top)', 'de' => 'Risikovorhersagen (Top)', 'fr' => 'Predictions de risque (Top)'],
        'Busqueda avanzada' => ['ca' => 'Cerca avancada', 'de' => 'Erweiterte Suche', 'fr' => 'Recherche avancee'],
        'Gestion de listas' => ['ca' => 'Gestio de llistes', 'de' => 'Listenverwaltung', 'fr' => 'Gestion des listes'],
        'Historial de mensajes' => ['ca' => 'Historial de missatges', 'de' => 'Nachrichtenverlauf', 'fr' => 'Historique des messages'],
        'Limpiar historial' => ['ca' => 'Netejar historial', 'de' => 'Verlauf leeren', 'fr' => 'Nettoyer historique'],
        'Eliminar de plataforma' => ['ca' => 'Eliminar de plataforma', 'de' => 'Von Plattform loschen', 'fr' => 'Supprimer de la plateforme'],
        'Solicitudes de acceso' => ['ca' => 'Sol-licituds dacces', 'de' => 'Zugriffsanfragen', 'fr' => 'Demandes dacces'],
        'Nuevo usuario' => ['ca' => 'Nou usuari', 'de' => 'Neuer Benutzer', 'fr' => 'Nouvel utilisateur'],
        'Crear usuario' => ['ca' => 'Crear usuari', 'de' => 'Benutzer erstellen', 'fr' => 'Creer utilisateur'],
        'Radar rapido' => ['ca' => 'Radar rapid', 'de' => 'Schnellradar', 'fr' => 'Radar rapide'],
        'Top paises' => ['ca' => 'Top paisos', 'de' => 'Top Lander', 'fr' => 'Top pays'],
        'Sin datos recientes' => ['ca' => 'Sense dades recents', 'de' => 'Keine aktuellen Daten', 'fr' => 'Aucune donnee recente'],
        'Copiar' => ['ca' => 'Copiar', 'de' => 'Kopieren', 'fr' => 'Copier'],
        'Copiado' => ['ca' => 'Copiat', 'de' => 'Kopiert', 'fr' => 'Copie'],
        'Error copia' => ['ca' => 'Error de copia', 'de' => 'Kopierfehler', 'fr' => 'Erreur de copie'],
        'Cargando captura...' => ['ca' => 'Carregant captura...', 'de' => 'Screenshot wird geladen...', 'fr' => 'Chargement de la capture...'],
        'No se pudo cargar la captura.' => ['ca' => 'No sha pogut carregar la captura.', 'de' => 'Screenshot konnte nicht geladen werden.', 'fr' => 'Impossible de charger la capture.'],
        'No se encontraron alertas relacionadas.' => ['ca' => 'No shan trobat alertes relacionades.', 'de' => 'Keine verwandten Warnungen gefunden.', 'fr' => 'Aucune alerte liee trouvee.'],
        'Sin contexto capturado.' => ['ca' => 'Sense context capturat.', 'de' => 'Kein erfasster Kontext.', 'fr' => 'Aucun contexte capture.'],
        'Cargando alertas relacionadas...' => ['ca' => 'Carregant alertes relacionades...', 'de' => 'Verwandte Warnungen werden geladen...', 'fr' => 'Chargement des alertes liees...'],
        'Selecciona al menos una alerta para revision masiva.' => ['ca' => 'Selecciona almenys una alerta per a revisio massiva.', 'de' => 'Bitte mindestens eine Warnung fur Massenreview auswahlen.', 'fr' => 'Selectionnez au moins une alerte pour la revue massive.'],
        'Mapa no disponible (Leaflet no cargado).' => ['ca' => 'Mapa no disponible (Leaflet no carregat).', 'de' => 'Karte nicht verfugbar (Leaflet nicht geladen).', 'fr' => 'Carte indisponible (Leaflet non charge).'],
        'No se pudo cargar geointeligencia de usuarios.' => ['ca' => 'No sha pogut carregar geointel-ligencia dusuaris.', 'de' => 'Geointelligence der Benutzer konnte nicht geladen werden.', 'fr' => 'Impossible de charger la geointelligence des utilisateurs.'],
        'No se pudo cargar geointeligencia de webs.' => ['ca' => 'No sha pogut carregar geointel-ligencia de webs.', 'de' => 'Geointelligence der Webseiten konnte nicht geladen werden.', 'fr' => 'Impossible de charger la geointelligence des sites.'],
        'Sin datos de geointeligencia.' => ['ca' => 'Sense dades de geointel-ligencia.', 'de' => 'Keine Geointelligence-Daten.', 'fr' => 'Aucune donnee de geointelligence.'],
    ];

    foreach ($phrases as $source => $targets) {
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
    if (!in_array($lang, ['ca', 'de', 'fr'], true)) {
        return $output;
    }
    $maps = cfdashboardliteralmaps();
    $map = $maps[$lang] ?? [];
    if (empty($map)) {
        return $output;
    }
    return strtr($output, $map);
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
$leafletCssUrl = ($scriptDirUrl !== '' ? $scriptDirUrl : '') . '/assets/vendor/leaflet/leaflet.css';
$leafletJsUrl = ($scriptDirUrl !== '' ? $scriptDirUrl : '') . '/assets/vendor/leaflet/leaflet.js';
$leafletWorldGeoJsonUrl = ($scriptDirUrl !== '' ? $scriptDirUrl : '') . '/assets/vendor/leaflet/data/world-countries.geo.json';
$activeTheme = $loggedIn ? clickfix_profile_normalize_theme((string) ($user['profile_theme'] ?? 'default')) : 'default';
$bodyClass = 'theme-' . $activeTheme;
ob_start();
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>ClickFix Security Operations Center</title>
  <?php if ($enableHomeGeoPanels): ?>
  <link rel="stylesheet" href="<?= clickfix_h($leafletCssUrl); ?>">
  <?php endif; ?>
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Sora:wght@500;600;700;800&family=Manrope:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&family=Public+Sans:wght@400;600;700&family=DM+Sans:wght@400;500;700&family=Nunito+Sans:wght@400;600;700&display=swap');
    :root{
      --bg:#05070f;
      --bg-layer:#0a1222;
      --bg-soft:#141f35;
      --card:#0d1729cc;
      --line:#2d3f5f;
      --line-soft:#22334f;
      --txt:#e9f4ff;
      --mut:#97afca;
      --brand:#6ff6ff;
      --brand-2:#7fffbf;
      --accent:#9aa4ff;
      --warn:#ffd070;
      --danger:#ff6f92;
      --ok:#59f2b5;
      --shadow:0 26px 70px rgba(2,8,18,.55);
      --radius:20px;
      --radius-sm:12px;
      --glow:0 0 24px rgba(111,246,255,.18);
      --font-main:'Manrope',sans-serif;
    }
    body.theme-teal{
      --brand:#56d6c9;
      --brand-2:#4fe38d;
      --bg:#051211;
      --bg-layer:#0a2221;
      --bg-soft:#113732;
      --line:#2f6f64;
      --line-soft:#25564f;
    }
    body.theme-sunset{
      --brand:#ffb75e;
      --brand-2:#ff8f70;
      --bg:#170b08;
      --bg-layer:#2a1410;
      --bg-soft:#3c1d15;
      --line:#89543c;
      --line-soft:#6f432f;
    }
    body.theme-mono{
      --brand:#b9c2cf;
      --brand-2:#dee4ec;
      --bg:#07090d;
      --bg-layer:#11161f;
      --bg-soft:#1a212d;
      --line:#4a5568;
      --line-soft:#394353;
    }
    *{box-sizing:border-box}
    html,body{height:100%;scroll-behavior:smooth}
    html{-webkit-text-size-adjust:100%}
    body{
      margin:0;
      color:var(--txt);
      font-family:var(--font-main);
      background:
        radial-gradient(820px 420px at 12% -8%,rgba(111,246,255,.28),transparent 65%),
        radial-gradient(880px 420px at 92% -12%,rgba(127,255,191,.18),transparent 60%),
        radial-gradient(700px 300px at 60% 108%,rgba(154,164,255,.12),transparent 62%),
        linear-gradient(120deg,rgba(255,255,255,.04) 0 1px,transparent 1px 22px),
        linear-gradient(145deg,var(--bg),var(--bg-layer) 50%,#0b1628 100%);
      min-height:100vh;
    }
    body.ui-light{
      --bg:#f3f7fb;
      --bg-layer:#e8eef5;
      --bg-soft:#ffffff;
      --card:#ffffff;
      --line:#c8d4e3;
      --line-soft:#d6e0ec;
      --txt:#0c1726;
      --mut:#3e4a5d;
      --brand:#1a6bff;
      --brand-2:#06c47a;
      --accent:#2b5cff;
      --warn:#b97300;
      --danger:#b5283f;
      --ok:#148a64;
      --shadow:0 18px 40px rgba(7,12,20,.14);
      --glow:none;
    }
    body.ui-light .card,
    body.ui-light .panel,
    body.ui-light table{
      background:rgba(255,255,255,.92);
      border-color:rgba(140,160,190,.5);
    }
    body.ui-light .top,
    body.ui-light .nav,
    body.ui-light .intel-workspace-nav{
      background:rgba(255,255,255,.85);
      border-color:rgba(140,160,190,.45);
    }
    body.ui-contrast{
      --line:#5c7aa8;
      --line-soft:#476a97;
      --mut:#c8ddf4;
    }
    body.ui-compact .card{padding:8px}
    body.ui-compact .grid,
    body.ui-compact .row,
    body.ui-compact .viz-grid,
    body.ui-compact .intel-grid,
    body.ui-compact .intel-side,
    body.ui-compact .intel-workbench-grid{
      gap:6px;
    }
    body.ui-compact .intel-workspace-nav{gap:6px}
    body.ui-reduced-motion *,
    body.ui-reduced-motion *::before,
    body.ui-reduced-motion *::after{
      animation:none !important;
      transition:none !important;
      scroll-behavior:auto !important;
    }
    body.ui-no-decor{
      background:linear-gradient(160deg,var(--bg),var(--bg-layer));
    }
    body.ui-no-decor .card,
    body.ui-no-decor .panel,
    body.ui-no-decor .nav,
    body.ui-no-decor .top{
      box-shadow:none !important;
    }
    body.ui-accent-blue{--accent:#5b8bff;--brand:#66b5ff}
    body.ui-accent-green{--accent:#37e3a7;--brand:#37e3a7}
    body.ui-accent-purple{--accent:#a685ff;--brand:#b59bff}
    body.ui-accent-amber{--accent:#ffb454;--brand:#ffb454}
    body.ui-accent-red{--accent:#ff7a86;--brand:#ff7a86}
    body.ui-accent-cyan{--accent:#5fd8ff;--brand:#5fd8ff}
    body.ui-font-public{--font-main:'Public Sans',sans-serif}
    body.ui-font-dm{--font-main:'DM Sans',sans-serif}
    body.ui-font-nunito{--font-main:'Nunito Sans',sans-serif}
    body.ui-font-sora{--font-main:'Sora',sans-serif}
    a{
      color:#b7f4ff;
      text-decoration:none;
      transition:color .2s ease,opacity .2s ease;
    }
    a:hover{color:#e9ffff;opacity:.95}
    code{
      font-family:'JetBrains Mono',monospace;
      background:rgba(16,33,55,.7);
      border:1px solid rgba(68,104,150,.45);
      border-radius:8px;
      padding:1px 6px;
    }
    pre{
      background:rgba(9,18,34,.8);
      border:1px solid rgba(62,96,142,.45);
      border-radius:12px;
      padding:10px;
      color:#d7e8ff;
      box-shadow:0 12px 28px rgba(3,10,22,.28) inset;
    }
    input::placeholder,textarea::placeholder{color:rgba(167,189,214,.75)}
    ::selection{background:rgba(111,246,255,.28);color:#031321}
    body.nav-open-mobile{
      overflow:hidden;
    }
    :root{
      --sticky-header-offset:136px;
    }
    .wrap{
      width:100%;
      max-width:none;
      margin:auto;
      padding:
        calc(10px + env(safe-area-inset-top))
        clamp(8px,1.6vw,18px)
        calc(18px + env(safe-area-inset-bottom))
        clamp(8px,1.6vw,18px);
    }
    .workspace{
      display:grid;
      grid-template-columns:minmax(0,1fr) clamp(280px,22vw,380px);
      gap:10px;
      align-items:start;
      min-height:calc(100vh - var(--sticky-header-offset));
    }
    .main-column{min-width:0;min-height:calc(100vh - var(--sticky-header-offset))}
    .side-column{
      display:flex;
      flex-direction:column;
      gap:8px;
      position:sticky;
      top:var(--sticky-header-offset);
      align-self:start;
      max-height:calc(100vh - var(--sticky-header-offset) - 12px);
      overflow:auto;
    }
    .side-card{padding:10px}
    .side-card h3{margin:0 0 8px;font-size:.86rem}
    .side-metrics{
      display:grid;
      grid-template-columns:repeat(2,minmax(0,1fr));
      gap:7px;
    }
    .side-metric{
      border:1px solid #3b608a;
      border-radius:12px;
      background:linear-gradient(150deg,#102843,#0c2038);
      padding:8px;
    }
    .side-metric b{display:block;font-size:1.05rem;line-height:1.1}
    .side-metric span{font:.64rem 'JetBrains Mono',monospace;color:#a9c9e7}
    .mini-list{margin:0;padding:0;list-style:none}
    .mini-list li{
      display:flex;
      justify-content:space-between;
      gap:8px;
      padding:6px 0;
      border-bottom:1px solid rgba(58,96,132,.35);
      font-size:.8rem;
    }
    .mini-list li:last-child{border-bottom:none}
    .mini-links{display:grid;gap:6px}
    .mini-links a{
      text-decoration:none;
      color:#e3f2ff;
      padding:8px 9px;
      border-radius:11px;
      border:1px solid #355f86;
      background:linear-gradient(135deg,#112742,#0f2238);
      font:600 .72rem 'JetBrains Mono',monospace;
      transition:.22s ease;
    }
    .mini-links a:hover{border-color:#75dcff;background:#183857;transform:translateY(-1px)}
    .announcement-aside details{
      border:1px solid #325978;
      border-radius:12px;
      background:linear-gradient(150deg,#0f2841,#0b2136);
      padding:8px;
    }
    .announcement-aside summary{
      list-style:none;
      cursor:pointer;
      display:flex;
      align-items:flex-start;
      justify-content:space-between;
      gap:10px;
    }
    .announcement-aside summary::-webkit-details-marker{display:none}
    .announcement-aside summary::after{
      content:'+';
      display:inline-flex;
      align-items:center;
      justify-content:center;
      width:18px;
      height:18px;
      border-radius:999px;
      border:1px solid #4d7498;
      color:#d9edff;
      background:#153553;
      flex-shrink:0;
      font:700 .85rem 'JetBrains Mono',monospace;
      line-height:1;
    }
    .announcement-aside details[open] > summary::after{content:'-'}
    .announcement-aside summary b{font:700 .78rem 'Sora',sans-serif}
    .announcement-aside summary span{display:block;color:#9fc0df;font:.67rem 'JetBrains Mono',monospace}
    .announcement-focus{
      margin:8px 0 0;
      padding:6px 8px;
      border-radius:9px;
      border:1px solid #365f85;
      background:#102740;
      color:#bdd7f0;
      font-size:.76rem;
      line-height:1.35;
    }
    .announcement-list{list-style:none;margin:8px 0 0;padding:0;display:grid;gap:7px}
    .announcement-item{
      border:1px solid #31577b;
      border-radius:10px;
      background:#0d2338;
      padding:7px 8px;
    }
    .announcement-item-head{
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:8px;
      margin-bottom:5px;
    }
    .announcement-severity{
      display:inline-flex;
      align-items:center;
      border-radius:999px;
      border:1px solid #4e7294;
      padding:2px 7px;
      font:.62rem 'JetBrains Mono',monospace;
      text-transform:uppercase;
      letter-spacing:.25px;
    }
    .announcement-severity.info{border-color:#2e7aa5;background:#113953;color:#bdeaff}
    .announcement-severity.warning{border-color:#8f7a41;background:#3a3016;color:#ffe5ab}
    .announcement-severity.critical{border-color:#9f4d5e;background:#491b27;color:#ffd5dd}
    .announcement-meta{font:.64rem 'JetBrains Mono',monospace;color:#a9c7e2}
    .announcement-item b{display:block;font-size:.78rem;line-height:1.25}
    .announcement-item p{margin:4px 0 0;color:#c3dbf2;font-size:.76rem;line-height:1.35}
    .ad-slot{
      border:1px dashed #4ea9db;
      border-radius:10px;
      background:#0b1d2f;
      min-height:120px;
      display:grid;
      place-items:center;
      overflow:hidden;
    }
    .internal-ads-stack{
      display:grid;
      gap:8px;
    }
    .internal-ad-card{
      border:1px solid #355f86;
      border-radius:14px;
      background:linear-gradient(155deg,#102840,#0d2136);
      padding:10px;
      display:grid;
      gap:8px;
    }
    .internal-ad-card.cyan{border-color:#2f82a8;background:linear-gradient(155deg,#0f2941,#0b2235)}
    .internal-ad-card.lime{border-color:#4e8d5c;background:linear-gradient(155deg,#132d2b,#0f2230)}
    .internal-ad-card.amber{border-color:#9b6d35;background:linear-gradient(155deg,#312412,#14263b)}
    .internal-ad-card.fuchsia{border-color:#8a4db6;background:linear-gradient(155deg,#2d1836,#0f2237)}
    .internal-ad-kicker{
      font:.64rem 'JetBrains Mono',monospace;
      color:#b9d8f0;
      text-transform:uppercase;
      letter-spacing:.08em;
    }
    .internal-ad-card b{
      display:block;
      font:700 .9rem 'Sora',sans-serif;
      color:#f3fbff;
      line-height:1.25;
    }
    .internal-ad-card p{
      margin:0;
      color:#c7def2;
      font-size:.78rem;
      line-height:1.42;
    }
    .internal-ad-card .btn{
      width:auto;
    }
    .support-note{margin:0 0 8px;color:#bcd6ef;font-size:.8rem;line-height:1.4}
    .top,.card{
      background:
        radial-gradient(circle at top right,rgba(111,246,255,.08),transparent 40%),
        linear-gradient(165deg,rgba(13,26,44,.92),rgba(9,19,34,.9));
      border:1px solid rgba(84,126,178,.48);
      border-radius:var(--radius);
      box-shadow:var(--shadow);
      backdrop-filter:blur(14px);
    }
    .top{
      display:grid;
      gap:12px;
      padding:14px 16px;
      margin-bottom:8px;
      position:sticky;
      top:10px;
      z-index:28;
      background:
        radial-gradient(circle at top right,rgba(127,255,191,.18),transparent 34%),
        linear-gradient(170deg,rgba(13,30,52,.95),rgba(7,15,28,.95));
      border-color:rgba(104,155,208,.58);
      box-shadow:0 22px 50px rgba(3,10,22,.5),var(--glow);
    }
    .app-header-main{
      display:flex;
      align-items:flex-start;
      justify-content:space-between;
      gap:16px;
      flex-wrap:wrap;
    }
    .nav-toggle{
      display:none;
      align-items:center;
      justify-content:center;
      gap:8px;
      min-height:42px;
      padding:9px 12px;
      border-radius:12px;
      border:1px solid #3f6690;
      background:linear-gradient(140deg,#132d49,#10253d);
      color:#e6f4ff;
      font:700 .74rem 'JetBrains Mono',monospace;
      letter-spacing:.08em;
      text-transform:uppercase;
      cursor:pointer;
      transition:.22s ease;
      box-shadow:0 10px 22px rgba(8,26,44,.22);
    }
    .nav-toggle:hover{
      border-color:#79dbff;
      transform:translateY(-1px);
      box-shadow:0 12px 24px rgba(16,78,129,.32);
    }
    .nav-toggle-lines{
      display:inline-grid;
      gap:3px;
      width:15px;
    }
    .nav-toggle-lines span{
      display:block;
      width:15px;
      height:2px;
      border-radius:999px;
      background:currentColor;
    }
    .app-header-brand{
      display:grid;
      gap:6px;
      min-width:280px;
      flex:1 1 420px;
    }
    .app-header-title{
      display:flex;
      align-items:center;
      gap:10px;
      flex-wrap:wrap;
    }
    .app-header-title b{font:700 1.06rem 'Sora',sans-serif;letter-spacing:.2px}
    .app-header-subline{
      display:flex;
      gap:8px;
      flex-wrap:wrap;
      align-items:center;
      color:#b9d4ec;
      font:.78rem 'JetBrains Mono',monospace;
    }
    .app-header-subline .sep{opacity:.55}
    .mono,code{font-family:'JetBrains Mono',monospace}
    .top .mut{font-size:.79rem}
    .module-chip{
      display:inline-flex;
      align-items:center;
      gap:7px;
      padding:5px 10px;
      border-radius:999px;
      border:1px solid #79dbff55;
      background:linear-gradient(135deg,#173f62,#11324f);
      font:600 .7rem 'JetBrains Mono',monospace;
      color:#e0f5ff;
    }
    .top-status{
      display:flex;
      gap:8px;
      flex-wrap:wrap;
      justify-content:flex-end;
      align-items:flex-start;
      flex:1 1 500px;
    }
    .status-chip{
      padding:5px 10px;
      border-radius:999px;
      border:1px solid #4a76a2;
      background:linear-gradient(135deg,#152f4a,#11263b);
      font:.7rem 'JetBrains Mono',monospace;
      color:#e5f2ff;
    }
    .status-chip a{color:#c9f2ff;text-decoration:none}
    .app-header-navrow{
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:12px;
      flex-wrap:wrap;
      padding-top:10px;
      border-top:1px solid rgba(96,137,177,.28);
    }
    .header-nav{
      display:flex;
      flex-wrap:wrap;
      gap:8px;
      flex:1 1 720px;
      min-width:0;
    }
    .header-nav a,
    .nav-actions{
      display:flex;
      align-items:center;
      gap:8px;
      flex-wrap:wrap;
    }
    .nav-actions{
      justify-content:flex-end;
      flex:0 1 auto;
    }
    .nav-actions form{margin:0}
    .header-nav a,
    .nav-actions .nav-btn{
      text-decoration:none;
      color:#dff1ff;
      padding:8px 11px;
      border-radius:12px;
      border:1px solid #3f6690;
      background:linear-gradient(140deg,#132d49,#10253d);
      font:600 .76rem 'JetBrains Mono',monospace;
      letter-spacing:.1px;
      transition:.22s ease;
      display:inline-flex;
      align-items:center;
      width:auto;
      cursor:pointer;
    }
    .header-nav a:hover,
    .nav-actions .nav-btn:hover{
      border-color:#79dbff;
      transform:translateY(-1px);
      box-shadow:0 10px 20px rgba(16,78,129,.3);
    }
    .header-nav a.active,
    .nav-actions .nav-btn.active{
      color:#06182a;
      border-color:transparent;
      background:linear-gradient(135deg,var(--brand),var(--brand-2));
      box-shadow:0 12px 26px rgba(99,217,255,.32);
    }
    .nav-actions .nav-btn.logout{
      border-color:#7b4450;
      background:linear-gradient(140deg,#4d1f2b,#3a1720);
      color:#ffd9e1;
    }
    .nav-actions .nav-btn.logout:hover{
      border-color:#e2879a;
      box-shadow:0 10px 20px rgba(145,42,68,.35);
    }
    .display-settings-panel{
      position:fixed;
      right:18px;
      top:78px;
      width:min(360px,92vw);
      max-height:80vh;
      overflow:auto;
      border-radius:20px;
      border:1px solid rgba(255,255,255,.08);
      background:linear-gradient(160deg,rgba(12,20,32,.96),rgba(9,15,24,.96));
      box-shadow:0 28px 70px rgba(0,0,0,.5);
      padding:14px;
      z-index:1200;
      display:none;
    }
    .display-settings-panel.open{display:block}
    .display-settings-header{
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:10px;
      margin-bottom:10px;
    }
    .display-settings-header h4{margin:0;font:700 .9rem 'Sora',sans-serif}
    .display-settings-grid{
      display:grid;
      grid-template-columns:repeat(2,minmax(0,1fr));
      gap:10px;
    }
    .display-toggle{
      border:1px solid #1f2f46;
      border-radius:16px;
      padding:12px;
      background:linear-gradient(160deg,#121c2b,#0e1826);
      display:grid;
      gap:10px;
    }
    .display-toggle .label{font:.75rem 'Manrope',sans-serif;color:#d6e6f7}
    .switch{
      display:inline-flex;
      align-items:center;
      gap:10px;
    }
    .switch input{display:none}
    .switch-track{
      width:42px;height:24px;border-radius:999px;
      border:1px solid #42597a;background:#101f30;position:relative;
      transition:background .2s ease,border-color .2s ease;
    }
    .switch-thumb{
      position:absolute;top:2px;left:2px;width:18px;height:18px;border-radius:50%;
      background:#8197b7;transition:transform .2s ease,background .2s ease;
    }
    .switch input:checked + .switch-track{background:#17324d;border-color:#66b4ff}
    .switch input:checked + .switch-track .switch-thumb{transform:translateX(18px);background:#9de2ff}
    .display-section{margin-top:14px}
    .display-section h5{margin:0 0 8px;font:.72rem 'JetBrains Mono',monospace;text-transform:uppercase;letter-spacing:.12em;color:#a9c5df}
    .preset-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px}
    .preset-btn{
      border:1px solid #20354d;border-radius:12px;padding:10px;background:#0c1826;
      display:flex;align-items:center;justify-content:center;cursor:pointer;
    }
    .preset-btn span{display:block;width:26px;height:26px;border-radius:8px}
    .font-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}
    .font-btn{
      border:1px solid #20354d;border-radius:14px;padding:10px;background:#0c1826;cursor:pointer;
      display:grid;gap:4px;text-align:left;
    }
    .font-btn b{font-size:.86rem}
    .font-btn span{font-size:.68rem;color:#9ab3cc}
    .hero{display:grid;grid-template-columns:2fr .9fr;gap:8px;margin-bottom:8px}
    .hero-main,.hero-side{padding:11px}
    .hero-main h1{margin:0 0 6px;font-size:clamp(1.14rem,1.45vw,1.48rem);line-height:1.15}
    .hero-main p{margin:0;color:#c8d9ef}
    .hero-kicker{
      display:inline-block;
      padding:5px 9px;
      border-radius:999px;
      border:1px solid #3f739a;
      background:#11314a;
      font:.69rem 'JetBrains Mono',monospace;
      color:#d0ebff;
      margin-bottom:6px;
    }
    .role-chip{
      display:inline-flex;
      align-items:center;
      gap:6px;
      padding:5px 10px;
      border-radius:999px;
      font:.7rem 'JetBrains Mono',monospace;
      border:1px solid #2ca97788;
      background:#10382d;
      color:#bff8df;
    }
    .role-chip.guest{border-color:#927138;background:#3d2f12;color:#ffe8ac}
    .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:8px;margin-bottom:8px}
    .card{
      padding:10px;
      position:relative;
      overflow:hidden;
      transition:border-color .2s ease,box-shadow .22s ease,transform .22s ease;
    }
    .card:hover{
      border-color:rgba(125,193,255,.62);
      box-shadow:0 22px 46px rgba(8,20,40,.44);
    }
    h2{margin:0 0 7px;font:700 .92rem 'Sora',sans-serif;letter-spacing:.14px}
    h3{margin:0 0 6px;font:600 .8rem 'Sora',sans-serif}
    p{line-height:1.35}
    ul{margin:0;padding-left:18px}
    li{margin:4px 0}
    .kpi{
      background:linear-gradient(155deg,rgba(21,45,73,.86),rgba(14,31,51,.86));
      border-color:rgba(88,141,197,.56);
    }
    .kpi-top{display:flex;align-items:flex-start;justify-content:space-between;gap:8px}
    .kpi-icon{
      width:28px;
      height:28px;
      border-radius:10px;
      border:1px solid #4a749a;
      display:inline-flex;
      align-items:center;
      justify-content:center;
      background:linear-gradient(145deg,#12324f,#0d243a);
      box-shadow:0 8px 18px rgba(0,0,0,.24);
      flex-shrink:0;
    }
    .kpi-icon svg{width:15px;height:15px;stroke:#c8ebff;stroke-width:1.8;fill:none;stroke-linecap:round;stroke-linejoin:round}
    .kpi b{font-size:1.34rem;font-family:'Sora',sans-serif;font-weight:700;line-height:1.1}
    .viz-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:8px}
    .mini-chart{display:grid;gap:6px}
    .mini-row{display:grid;grid-template-columns:140px 1fr 52px;align-items:center;gap:8px}
    .mini-bar{height:8px;border-radius:999px;background:#183f5d;overflow:hidden}
    .mini-bar span{display:block;height:100%;border-radius:999px;background:linear-gradient(90deg,#56d4ff,#33d17a)}
    .mini-score{font:.72rem 'JetBrains Mono',monospace;color:#a8cae8;text-align:right}
    .pred-badge{padding:2px 7px;border-radius:999px;border:1px solid #3f6c95;background:#13324f;font:.66rem 'JetBrains Mono',monospace}
    .pred-badge.malicious{border-color:#a94a56;color:#ffd2d8;background:#3f1822}
    .pred-badge.suspicious{border-color:#8f7844;color:#ffebbe;background:#3e3014}
    .pred-badge.low_risk{border-color:#2b7c5f;color:#c7ffe8;background:#10352b}
    .mut{color:var(--mut)}
    .user-link{color:#9de9ff;text-decoration:none;border-bottom:1px dashed #4ea7d8}
    .user-link:hover{color:#d5f4ff;border-bottom-color:#8fe4ff}
    .flash{
      border:1px solid #2b8466;
      background:linear-gradient(150deg,rgba(31,101,81,.48),rgba(17,62,52,.45));
      padding:8px 10px;
      border-radius:11px;
      margin-bottom:8px;
      box-shadow:0 10px 20px rgba(0,0,0,.2) inset;
    }
    .row{display:grid;grid-template-columns:2fr 1fr;gap:8px;margin-bottom:8px}
    .stack{display:grid;gap:8px}
    table{
      width:100%;
      border-collapse:separate;
      border-spacing:0;
      font-size:.79rem;
      background:rgba(9,18,32,.45);
      border:1px solid rgba(55,87,130,.45);
      border-radius:14px;
      overflow:hidden;
    }
    th,td{
      padding:7px 8px;
      border-bottom:1px solid rgba(58,96,132,.3);
      text-align:left;
      vertical-align:top;
    }
    th{
      font:700 .68rem 'JetBrains Mono',monospace;
      text-transform:uppercase;
      color:#c7ddf4;
      letter-spacing:.28px;
      background:linear-gradient(120deg,rgba(24,49,75,.75),rgba(12,28,46,.6));
      border-bottom:1px solid rgba(90,130,178,.55);
    }
    th.sortable{
      cursor:pointer;
      user-select:none;
      position:relative;
      padding-right:18px;
    }
    th.sortable::after{
      content:'â†•';
      position:absolute;
      right:7px;
      top:50%;
      transform:translateY(-50%);
      font-size:.68rem;
      color:#84a6c8;
      opacity:.9;
    }
    th.sortable.sort-asc::after{content:'â†‘';color:#d8efff}
    th.sortable.sort-desc::after{content:'â†“';color:#d8efff}
    tr:hover td{background:rgba(16,32,55,.58)}
    input,select,textarea,button{
      width:100%;
      padding:9px 11px;
      border-radius:12px;
      border:1px solid rgba(70,104,150,.7);
      background:
        linear-gradient(150deg,rgba(20,39,64,.95),rgba(10,24,42,.95));
      color:var(--txt);
      font:600 .84rem 'Manrope',sans-serif;
      transition:border-color .2s ease,box-shadow .2s ease,transform .18s ease,background .2s ease;
      box-shadow:0 6px 16px rgba(3,10,22,.2);
    }
    select{
      appearance:none;
      background-image:
        linear-gradient(45deg,transparent 50%,rgba(180,210,255,.9) 50%),
        linear-gradient(135deg,rgba(180,210,255,.9) 50%,transparent 50%),
        linear-gradient(to right,transparent,transparent);
      background-position:
        calc(100% - 16px) calc(50% - 2px),
        calc(100% - 10px) calc(50% - 2px),
        calc(100% - 26px) 0.55rem;
      background-size:6px 6px,6px 6px,1px 1.4rem;
      background-repeat:no-repeat;
      padding-right:30px;
    }
    select:focus{background-image:
        linear-gradient(45deg,transparent 50%,rgba(255,255,255,.95) 50%),
        linear-gradient(135deg,rgba(255,255,255,.95) 50%,transparent 50%),
        linear-gradient(to right,transparent,transparent);}
    input[type=checkbox],input[type=radio]{width:auto;padding:0;transform:translateY(1px)}
    label{display:flex;gap:8px;align-items:center}
    input:focus,select:focus,textarea:focus{
      outline:none;
      border-color:#9af1ff;
      box-shadow:0 0 0 3px rgba(111,246,255,.18),0 10px 24px rgba(3,12,22,.25);
      background:linear-gradient(160deg,rgba(24,49,78,.95),rgba(10,24,42,.95));
    }
    textarea{min-height:76px}
    .btn{
      background:
        linear-gradient(120deg,var(--brand),var(--brand-2));
      border:none;
      color:#041322;
      font-weight:800;
      letter-spacing:.2px;
      text-transform:uppercase;
      font-size:.73rem;
      cursor:pointer;
      box-shadow:0 14px 30px rgba(86,210,255,.3),var(--glow);
      transition:.2s ease;
    }
    .btn:hover{
      transform:translateY(-1px);
      box-shadow:0 18px 36px rgba(86,210,255,.4),0 0 20px rgba(127,255,191,.25);
    }
    .btn:active{transform:translateY(0);box-shadow:0 10px 20px rgba(86,210,255,.26)}
    .split{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px}
    .badge{padding:4px 8px;border-radius:999px;border:1px solid #5b7f9f;font:.68rem 'JetBrains Mono',monospace}
    .pending{color:var(--warn)}.accepted{color:var(--ok)}.rejected{color:var(--danger)}
    .bulk-review-toolbar{
      margin:8px 0;
      display:flex;
      flex-wrap:wrap;
      align-items:center;
      gap:8px;
      border:1px solid rgba(68,112,150,.58);
      border-radius:12px;
      background:linear-gradient(150deg,#10253d,#0d2135);
      padding:8px;
    }
    .bulk-review-toolbar .bulk-review-count{
      font:.73rem 'JetBrains Mono',monospace;
      color:#bcd6ef;
      min-width:120px;
    }
    .bulk-review-toolbar .bulk-review-actions{
      display:flex;
      gap:6px;
      flex-wrap:wrap;
      margin-left:auto;
    }
    .bulk-review-toolbar .bulk-review-actions button{
      width:auto;
      min-width:0;
      padding:6px 10px;
      font:.72rem 'JetBrains Mono',monospace;
    }
    .bulk-review-toolbar .bulk-review-actions button.secondary{
      background:linear-gradient(145deg,#16324e,#102742);
      border:1px solid #486e95;
      color:#d8edff;
      box-shadow:none;
    }
    .bulk-review-select-cell{
      width:38px;
      text-align:center;
    }
    .bulk-review-select-cell input[type=checkbox]{
      width:16px;
      height:16px;
      margin:0;
      transform:none;
      cursor:pointer;
    }
    .event-workbench{display:grid;grid-template-columns:minmax(240px,312px) minmax(0,1fr);gap:8px;margin-top:6px}
    .event-feed{display:flex;flex-direction:column;gap:6px;max-height:560px;overflow:auto;padding-right:4px}
    .event-feed-item{
      display:flex;align-items:flex-start;gap:8px;text-align:left;
      border:1px solid #315a80;border-radius:11px;background:#0c2137;padding:8px;
      color:var(--txt);cursor:pointer;transition:.14s ease;
    }
    .event-feed-item:hover{border-color:#73d4ff;background:#143150}
    .event-feed-item.is-active{border-color:#67edc1;background:linear-gradient(145deg,#194867,#143d59)}
    .event-feed-item.is-blocked{
      border-color:#ff6d7d;
      background:linear-gradient(145deg,#3f1c29,#2b1520);
      box-shadow:inset 0 0 0 1px #ff899766;
    }
    .event-feed-item.is-blocked:hover{
      border-color:#ffa8b3;
      background:linear-gradient(145deg,#4a1f2d,#341724);
    }
    .event-feed-item.is-active.is-blocked{
      border-color:#ff8f9f;
      background:linear-gradient(145deg,#5a2435,#3d1b27);
      box-shadow:inset 0 0 0 1px #ffb2bc88;
    }
    .event-feed-item.is-blocked .event-feed-host{color:#ffdce1}
    .event-feed-item.is-blocked .event-feed-meta{color:#f2c2cb}
    .event-feed-main{display:flex;flex-direction:column;gap:2px;min-width:0}
    .event-feed-host{font-weight:700;word-break:break-word}
    .event-feed-meta{font:.72rem 'JetBrains Mono',monospace;color:var(--mut);word-break:break-word}
    .event-feed-reason{font-size:.77rem;color:#d7e7fb;line-height:1.3;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
    .event-feed-flag{
      display:inline-flex;align-items:center;gap:4px;margin-top:4px;width:max-content;
      padding:2px 7px;border-radius:999px;border:1px solid #ff7a8f;background:#5f1b2a;color:#ffd9df;
      font:.66rem 'JetBrains Mono',monospace;letter-spacing:.2px
    }
    .event-feed-sev{width:8px;height:8px;border-radius:999px;margin-top:5px;flex-shrink:0;background:#4de09f}
    .event-feed-sev.low{background:#4de09f}.event-feed-sev.medium{background:#f5c14b}.event-feed-sev.high{background:#ff8f3f}.event-feed-sev.critical{background:#ff6d7d}
    .event-detail-shell{border:1px solid #3d638b;border-radius:14px;padding:12px;background:linear-gradient(150deg,#102741,#0b2137)}
    .event-empty{color:var(--mut);font-size:.88rem}
    .event-title{margin:0 0 4px}
    .event-topline{display:flex;justify-content:space-between;gap:8px;align-items:flex-start;flex-wrap:wrap}
    .event-badges{display:flex;gap:6px;flex-wrap:wrap}
    .event-chip{padding:2px 8px;border-radius:999px;border:1px solid #365f85;background:#12314b;font:.7rem 'JetBrains Mono',monospace}
    .event-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:6px;margin:8px 0}
    .event-kv{padding:6px;border:1px solid #2e5478;border-radius:9px;background:#0f2841}
    .event-kv b{display:block;font-size:.68rem;color:#b4d2ef;font-family:'JetBrains Mono',monospace}
    .event-kv span{font-size:.83rem;word-break:break-word}
    .event-columns{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;margin-top:8px}
    .event-related{
      margin-top:10px;
      border:1px solid #2f5579;
      border-radius:11px;
      background:#0c2238;
      padding:8px;
    }
    .event-related-head{
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:8px;
      flex-wrap:wrap;
      margin-bottom:6px;
    }
    .event-related-note{font:.74rem 'JetBrains Mono',monospace;color:#a8c6e4}
    .event-related-table-wrap{max-height:260px;overflow:auto;margin-top:6px}
    .event-related-link{
      color:#9de9ff;
      text-decoration:none;
      border-bottom:1px dashed #58bde4;
    }
    .event-related-link:hover{color:#d5f4ff;border-bottom-color:#9de9ff}
    .event-list{margin:6px 0 0 16px;padding:0}
    .event-list li{margin:2px 0;color:#c8e0f7}
    .event-snippet{padding:7px 8px;border-radius:8px;border:1px solid #2f597f;background:#13304a;font:.73rem 'JetBrains Mono',monospace;white-space:pre-wrap;word-break:break-word}
    .event-context{margin-top:8px}
    .event-context pre{margin:0;padding:8px;border-radius:10px;border:1px solid #2d5377;background:#081929;max-height:180px;overflow:auto;white-space:pre-wrap;font:.74rem 'JetBrains Mono',monospace}
    .event-context.event-context-exact pre{max-height:420px}
    .event-context mark{background:#ffd26666;color:#fff;border-radius:3px;padding:0 2px}
    .event-raw{margin-top:8px}
    .event-raw pre{margin:6px 0 0;padding:8px;border:1px solid #2d5377;border-radius:10px;max-height:220px;overflow:auto;background:#081929;font:.73rem 'JetBrains Mono',monospace;white-space:pre-wrap}
    .session-timeout-modal{position:fixed;inset:0;background:rgba(5,12,22,.78);display:flex;align-items:center;justify-content:center;z-index:9999}
    .session-timeout-modal[hidden]{display:none}
    .session-timeout-card{max-width:420px;width:92%;border-radius:14px;padding:16px;border:1px solid #2d557a;background:linear-gradient(160deg,#0c2236,#0b1c2c);box-shadow:0 20px 50px rgba(0,0,0,.45)}
    .session-timeout-card h3{margin:0 0 6px 0}
    .session-timeout-card .mut{font-size:.86rem}
    .legacy-events{margin-top:8px}
    .legacy-events summary{cursor:pointer;color:#cbeefd}
    .mitre-blueprint{margin-top:8px;border:1px solid #2d557a;border-radius:12px;padding:10px;background:linear-gradient(160deg,#0c2236,#0b1c2c)}
    .mitre-blueprint h4{margin:0 0 6px 0;font-size:.9rem}
    .mitre-blueprint-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:8px}
    .mitre-tactic{border:1px solid #2a4b69;border-radius:10px;padding:8px;background:#0e2337;display:grid;gap:6px}
    .mitre-tactic-title{font:.7rem 'JetBrains Mono',monospace;color:#9dc3e1;text-transform:uppercase;letter-spacing:.12em}
    .mitre-tech-list{display:flex;flex-wrap:wrap;gap:6px}
    .mitre-tech{padding:4px 6px;border-radius:8px;border:1px solid #3c6a8f;background:#10304b;font:.7rem 'JetBrains Mono',monospace;color:#d7ecff}
    .mitre-tech b{display:block;font-size:.68rem;color:#87c7ff}
    .mitre-empty{color:var(--mut);font-size:.8rem}
    .intel-shell{display:grid;gap:12px}
    .intel-topbar{
      border:1px solid #2b5378;
      border-radius:14px;
      padding:12px;
      background:linear-gradient(155deg,#0f2842,#0c2136 62%,#0a1c2d);
      display:grid;
      gap:10px;
    }
    .intel-topline{
      display:flex;
      justify-content:space-between;
      align-items:flex-start;
      gap:10px;
      flex-wrap:wrap;
    }
    .intel-title-wrap h2{margin:0}
    .intel-title-wrap p{margin:4px 0 0;color:#b5d1ea}
    .intel-chip-row{display:flex;flex-wrap:wrap;gap:7px}
    .intel-chip{
      display:inline-flex;
      align-items:center;
      gap:6px;
      padding:3px 9px;
      border-radius:999px;
      border:1px solid #39658e;
      background:#12304a;
      font:.69rem 'JetBrains Mono',monospace;
      color:#d1e8ff;
    }
    .intel-chip.critical{border-color:#8e3f51;background:#3a1d28;color:#ffd7dd}
    .intel-chip.ok{border-color:#3f7d66;background:#17392f;color:#c9ffea}
    .intel-chip.warn{border-color:#80643b;background:#392f1b;color:#ffeecb}
    .intel-kpi-grid{
      display:grid;
      grid-template-columns:repeat(4,minmax(0,1fr));
      gap:8px;
    }
    .intel-kpi{
      border:1px solid #2e557b;
      border-radius:11px;
      background:#0f2740;
      padding:9px;
    }
    .intel-kpi b{
      display:block;
      font:.7rem 'JetBrains Mono',monospace;
      color:#a8c6e4;
      margin-bottom:5px;
      letter-spacing:.2px;
      text-transform:uppercase;
    }
    .intel-kpi span{
      font:700 .95rem 'Manrope',sans-serif;
      color:#ecf7ff;
      word-break:break-word;
    }
    .intel-stage-bar{
      display:grid;
      grid-template-columns:repeat(5,minmax(0,1fr));
      gap:8px;
    }
    .intel-stage{
      border:1px solid #2f567d;
      border-radius:10px;
      background:#0d2439;
      padding:8px;
      text-align:center;
      font:.72rem 'JetBrains Mono',monospace;
      color:#c5def5;
    }
    .intel-stage.active{
      border-color:#4de09f99;
      background:#154159;
      color:#e8fff6;
      font-weight:700;
    }
    .intel-layout{display:grid;grid-template-columns:minmax(280px,340px) minmax(0,1fr);gap:12px}
    .intel-layout.workspace-only{grid-template-columns:minmax(0,1fr)}
    .intel-selector-shell{
      display:grid;
      gap:14px;
      border:1px solid #2b5378;
      border-radius:16px;
      background:linear-gradient(155deg,#0a1f31,#0b2438);
      padding:16px;
      box-shadow:0 24px 60px -44px #000;
    }
    .intel-selector-head{
      display:flex;
      align-items:flex-start;
      justify-content:space-between;
      gap:14px;
      flex-wrap:wrap;
    }
    .intel-selector-head h3{margin:0 0 6px}
    .intel-selector-head p{margin:0;max-width:720px}
    .intel-selector-grid{
      display:grid;
      grid-template-columns:repeat(3,minmax(0,1fr));
      gap:12px;
      align-items:start;
    }
    .intel-selector-column{
      display:grid;
      gap:10px;
      border:1px solid #294c6d;
      border-radius:14px;
      background:linear-gradient(160deg,#0d253a,#0c2235);
      padding:12px;
      min-height:100%;
    }
    .intel-selector-column h4{margin:0}
    .intel-selector-column .mut{margin:0}
    .intel-picker-stack{display:grid;gap:10px}
    .intel-picker-card{
      display:grid;
      gap:8px;
      padding:12px;
      border:1px solid #2f567d;
      border-radius:12px;
      background:#10283f;
      box-shadow:inset 0 1px 0 #ffffff08;
    }
    .intel-picker-card strong{display:block;font-size:.96rem;color:#f5fbff}
    .intel-picker-card .mono{font-size:.72rem}
    .intel-picker-card .summary{
      font-size:.82rem;
      color:#d4e9fa;
      display:-webkit-box;
      -webkit-line-clamp:3;
      -webkit-box-orient:vertical;
      overflow:hidden;
    }
    .intel-picker-actions{display:flex;gap:8px;flex-wrap:wrap}
    .intel-picker-actions .btn{width:auto}
    .intel-empty-state{
      padding:18px 14px;
      border:1px dashed #3c668c;
      border-radius:12px;
      background:#0d2438;
      color:#bedbf0;
    }
    .intel-selector-v2{
      border-color:#2f5b84;
      background:
        radial-gradient(circle at top left, rgba(68,151,218,.12), transparent 55%),
        linear-gradient(155deg,#0a1f31,#0b2236 60%,#0a1f31);
      padding:18px;
    }
    .intel-focus-hero{
      display:grid;
      grid-template-columns:minmax(0,1fr) minmax(220px,260px);
      gap:16px;
      align-items:start;
    }
    .intel-focus-eyebrow{
      font-size:.7rem;
      letter-spacing:.16em;
      text-transform:uppercase;
      color:#6db3ff;
      margin-bottom:6px;
    }
    .intel-focus-steps{
      display:flex;
      gap:8px;
      flex-wrap:wrap;
      margin-top:10px;
    }
    .intel-step{
      padding:4px 10px;
      border-radius:999px;
      border:1px solid #295d84;
      font-size:.72rem;
      color:#b7d8f7;
      background:#0f2840;
    }
    .intel-step.active{
      border-color:#5ad0ff;
      color:#e7f7ff;
      box-shadow:0 0 0 1px #5ad0ff33 inset;
    }
    .intel-focus-meta{
      display:grid;
      gap:10px;
      border:1px solid #2b567d;
      border-radius:14px;
      padding:12px;
      background:#0f2740;
    }
    .intel-stat{
      display:flex;
      align-items:baseline;
      justify-content:space-between;
      gap:8px;
      padding:6px 8px;
      border-radius:10px;
      background:#0b2135;
      border:1px solid #234d72;
    }
    .intel-stat .k{font-size:.7rem;color:#9fbfe0;text-transform:uppercase;letter-spacing:.12em}
    .intel-stat .v{font-size:1.15rem;font-weight:700;color:#f5fbff}
    .intel-focus-search{
      display:grid;
      gap:6px;
      font-size:.72rem;
      color:#b8d5ef;
    }
    .intel-focus-search input{
      width:100%;
      padding:9px 10px;
      border-radius:10px;
      border:1px solid #2a587f;
      background:#0a1b2a;
      color:#e6f4ff;
    }
    .intel-focus-tabs{
      display:flex;
      gap:8px;
      flex-wrap:wrap;
      margin-top:10px;
    }
    .intel-tab{
      border:1px solid #2b567d;
      background:#0c2236;
      color:#c7def4;
      padding:8px 12px;
      border-radius:10px;
      font-weight:600;
      cursor:pointer;
    }
    .intel-tab.is-active{
      background:#14324c;
      border-color:#64b5ff;
      color:#f2f9ff;
      box-shadow:0 0 0 1px #64b5ff33 inset;
    }
    .intel-focus-panels{margin-top:10px;display:grid;gap:12px}
    .intel-panel-head{
      display:flex;
      justify-content:space-between;
      align-items:flex-start;
      gap:10px;
      flex-wrap:wrap;
    }
    .intel-panel-head h4{margin:0}
    .intel-panel-chip{
      padding:6px 10px;
      border-radius:999px;
      font-size:.72rem;
      border:1px solid #2b5a82;
      background:#0c2338;
      color:#b9d6f0;
    }
    .intel-focus-list{display:grid;gap:10px}
    .intel-focus-card{
      display:grid;
      gap:10px;
      padding:14px;
      border-radius:14px;
      border:1px solid #2f567d;
      background:#0f263c;
      box-shadow:inset 0 1px 0 #ffffff08;
    }
    .intel-focus-card.hero{
      background:linear-gradient(145deg,#102a42,#0c2032);
      border-color:#3b6e9a;
    }
    .intel-focus-main strong{
      display:block;
      font-size:1rem;
      color:#f5fbff;
      margin-bottom:6px;
    }
    .intel-meta-row{
      display:flex;
      gap:6px;
      flex-wrap:wrap;
      align-items:center;
      font-size:.72rem;
      color:#b9d7ef;
    }
    .intel-badge{
      padding:3px 8px;
      border-radius:999px;
      border:1px solid #2a587f;
      background:#0b2135;
      color:#c4dcf2;
      text-transform:lowercase;
    }
    .intel-badge.score{border-color:#4aa6ff;color:#e6f4ff;background:#103251}
    .intel-badge.soft{opacity:.9}
    .intel-badge.critical{border-color:#a94a56;color:#ffd2d8;background:#3f1822}
    .intel-focus-summary .summary{
      font-size:.82rem;
      color:#d4e9fa;
      display:-webkit-box;
      -webkit-line-clamp:3;
      -webkit-box-orient:vertical;
      overflow:hidden;
    }
    .intel-summary-details{
      margin-top:6px;
      font-size:.75rem;
      color:#b8d5ef;
    }
    .intel-summary-details summary{
      cursor:pointer;
      color:#8ac5ff;
    }
    .intel-focus-actions{display:flex;gap:8px;flex-wrap:wrap}
    .intel-focus-card.is-hidden{display:none}
    .intel-focus-bar{
      position:sticky;
      top:calc(var(--sticky-header-offset) + 8px);
      z-index:18;
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:12px;
      flex-wrap:wrap;
      margin:12px 0;
      padding:11px 12px;
      border:1px solid #315c82;
      border-radius:13px;
      background:linear-gradient(155deg,rgba(14,38,58,.96),rgba(11,31,49,.96));
      backdrop-filter:blur(14px);
      box-shadow:0 18px 40px -34px #000;
    }
    .intel-focus-main{display:grid;gap:4px}
    .intel-focus-main strong{font-size:1rem;color:#f6fbff}
    .intel-focus-main span{color:#bddcff;font:.76rem 'JetBrains Mono',monospace}
    .intel-focus-actions{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
    .intel-focus-actions .btn{width:auto}
    .intel-cockpit{
      display:grid;
      grid-template-columns:repeat(6,minmax(0,1fr));
      gap:10px;
      margin:0 0 12px;
    }
    .intel-cockpit-card{
      border:1px solid #2c5478;
      border-radius:14px;
      background:linear-gradient(160deg,#0f2a42,#0a1f31);
      padding:12px;
      display:grid;
      gap:6px;
      min-height:108px;
      box-shadow:0 16px 32px -28px #000;
    }
    .intel-cockpit-card .k{
      font:.68rem 'JetBrains Mono',monospace;
      color:#9fc2de;
      text-transform:uppercase;
      letter-spacing:.04em;
    }
    .intel-cockpit-card .v{
      font:700 1.08rem 'Sora',sans-serif;
      color:#f2faff;
      word-break:break-word;
    }
    .intel-cockpit-card .mut{margin:0}
    .intel-cockpit-card.actions{
      grid-column:span 2;
      align-content:start;
    }
    .intel-quick-grid{
      display:flex;
      flex-wrap:wrap;
      gap:8px;
    }
    .intel-quick-grid .btn{width:auto}
    .intel-workspace-nav{
      position:sticky;
      top:calc(var(--sticky-header-offset) + 8px);
      z-index:17;
      display:flex;
      gap:8px;
      flex-wrap:wrap;
      margin:0 0 12px;
      padding:10px;
      border:1px solid #315a7f;
      border-radius:13px;
      background:linear-gradient(155deg,rgba(14,36,55,.94),rgba(10,27,43,.94));
      backdrop-filter:blur(14px);
      box-shadow:0 16px 38px -34px #000;
    }
    .intel-workspace-nav .btn{
      width:auto;
      min-width:0;
      padding:8px 12px;
      border-radius:999px;
    }
    .intel-workspace-nav .btn.active{
      border-color:#4de09f99;
      background:#154159;
      color:#effff7;
    }
    .intel-section-head{
      display:flex;
      align-items:flex-start;
      justify-content:space-between;
      gap:10px;
      flex-wrap:wrap;
      margin-bottom:10px;
    }
    .intel-section-head h3{margin:0}
    .intel-section-head p{margin:4px 0 0}
    .intel-section-kicker{
      display:inline-flex;
      align-items:center;
      gap:6px;
      padding:4px 9px;
      border:1px solid #3d6b93;
      border-radius:999px;
      background:#12314a;
      color:#d5eeff;
      font:.68rem 'JetBrains Mono',monospace;
      text-transform:uppercase;
      letter-spacing:.04em;
    }
    .intel-list{
      display:flex;
      flex-direction:column;
      gap:8px;
      max-height:840px;
      overflow:auto;
      padding-right:4px;
      border:1px solid #2b5378;
      border-radius:12px;
      background:#0a1d2f;
      padding:10px;
    }
    .intel-list-head{
      position:sticky;
      top:0;
      z-index:2;
      border:1px solid #2f567d;
      border-radius:10px;
      background:linear-gradient(160deg,#11314c,#0e273e);
      padding:8px 9px;
      margin-bottom:2px;
    }
    .intel-list-head b{display:block}
    .intel-list-head span{color:#b8d4ec;font-size:.78rem}
    .intel-item{
      display:block;
      text-decoration:none;
      color:var(--txt);
      padding:10px 11px;
      border:1px solid #2f567d;
      border-radius:11px;
      background:#0d243a;
      transition:.14s ease;
    }
    .intel-item:hover{border-color:#53a8db;background:#12304a}
    .intel-item.active{border-color:#4de09f99;background:#154159}
    .intel-item b{display:block}
    .intel-item .meta{font:.7rem 'JetBrains Mono',monospace;color:var(--mut);margin-top:3px}
    .intel-item .summary{font-size:.8rem;color:#d9eafc;margin-top:5px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
    .intel-editor{
      border:1px solid #2b5378;
      border-radius:12px;
      padding:12px;
      background:linear-gradient(160deg,#0b2135,#091b2b);
    }
    .intel-editor h2{margin-bottom:8px}
    .intel-editor-section{
      border:1px solid #2d5277;
      border-radius:11px;
      background:#0f2740;
      padding:10px;
      margin-bottom:10px;
    }
    [data-intel-section]{scroll-margin-top:calc(var(--sticky-header-offset) + 110px)}
    .intel-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}
    .intel-grid-full{grid-column:1 / -1}
    .intel-toolbar{display:flex;gap:8px;flex-wrap:wrap;margin:10px 0}
    .intel-toolbar button{width:auto}
    .intel-workbench-panel{
      display:grid;
      gap:12px;
      margin:14px 0 18px;
      padding:12px;
      border:1px solid #2f5579;
      border-radius:14px;
      background:linear-gradient(160deg,#0d243a,#0b1d2f);
    }
    .intel-workbench-grid{
      display:grid;
      grid-template-columns:minmax(0,1.3fr) minmax(260px,.9fr);
      gap:12px;
      align-items:start;
    }
    .intel-workbench-side{display:grid;gap:8px}
    .intel-workbench-note{
      margin:0;
      color:#b6d2eb;
      font-size:.78rem;
      line-height:1.45;
    }
    .intel-workbench-provider-list{display:flex;flex-wrap:wrap;gap:8px}
    .intel-workbench-provider-list label{
      gap:6px;
      padding:7px 10px;
      border-radius:10px;
      border:1px solid #335d83;
      background:#102741;
      font:.72rem 'JetBrains Mono',monospace;
      color:#d3e9ff;
    }
    .intel-workbench-provider-list input[type=checkbox]{accent-color:#5fd8ff}
    .intel-workbench-decode{
      border:1px solid #2f5579;
      border-radius:10px;
      background:#0d2237;
      padding:8px;
      display:grid;
      gap:8px;
    }
    .intel-workbench-decode summary{
      list-style:none;
      cursor:pointer;
      font:700 .82rem 'Manrope',sans-serif;
      color:#d8ecff;
    }
    .intel-workbench-decode summary::-webkit-details-marker{display:none}
    .intel-workbench-decode[open] summary{
      border-bottom:1px solid #2f5579;
      padding-bottom:6px;
    }
    .intel-workbench-suggestions{
      display:grid;
      gap:6px;
    }
    .intel-workbench-kpis{
      display:grid;
      grid-template-columns:repeat(4,minmax(0,1fr));
      gap:8px;
    }
    .intel-workbench-kpi{
      padding:8px 9px;
      border:1px solid #30577b;
      border-radius:11px;
      background:#0f2740;
      min-height:72px;
    }
    .intel-workbench-kpi b{
      display:block;
      margin-bottom:5px;
      font:.66rem 'JetBrains Mono',monospace;
      color:#a8c6e4;
      text-transform:uppercase;
      letter-spacing:.05em;
    }
    .intel-workbench-kpi span{
      display:block;
      font:700 1.05rem 'Sora',sans-serif;
      color:#edf7ff;
    }
    .intel-artifact-grid,
    .intel-batch-grid,
    .intel-decode-grid{display:grid;gap:8px}
    .intel-artifact-grid{grid-template-columns:repeat(auto-fit,minmax(220px,1fr))}
    .intel-artifact-card,
    .intel-batch-card,
    .intel-decode-card{
      border:1px solid #30577b;
      border-radius:12px;
      background:#0f2740;
      padding:10px;
      display:grid;
      gap:6px;
    }
    .intel-artifact-type{
      display:inline-flex;
      align-items:center;
      width:max-content;
      padding:2px 8px;
      border-radius:999px;
      border:1px solid #47719a;
      background:#14324d;
      font:.66rem 'JetBrains Mono',monospace;
      color:#d3ebff;
      text-transform:uppercase;
    }
    .intel-artifact-value{
      margin:0;
      color:#f3fbff;
      font:600 .8rem 'JetBrains Mono',monospace;
      word-break:break-word;
    }
    .intel-decode-card pre,
    .intel-batch-card pre{
      margin:0;
      padding:8px;
      border-radius:10px;
      border:1px solid #284a69;
      background:#091a2a;
      max-height:180px;
      overflow:auto;
      white-space:pre-wrap;
      font:.72rem 'JetBrains Mono',monospace;
    }
    .intel-batch-head{
      display:flex;
      justify-content:space-between;
      gap:8px;
      align-items:flex-start;
      flex-wrap:wrap;
    }
    .intel-batch-status{
      display:inline-flex;
      align-items:center;
      gap:6px;
      padding:3px 8px;
      border-radius:999px;
      border:1px solid #3b6e96;
      background:#13304a;
      font:.67rem 'JetBrains Mono',monospace;
      color:#d9efff;
    }
    .intel-batch-status.ok{border-color:#3f7d66;background:#17392f;color:#cbffea}
    .intel-batch-status.ko{border-color:#8e3f51;background:#3a1d28;color:#ffd7dd}
    .intel-map-shell{display:grid;gap:10px}
    .intel-map-toolbar{
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:10px;
      flex-wrap:wrap;
      padding:8px 10px;
      border:1px solid #315c82;
      border-radius:11px;
      background:linear-gradient(155deg,#0d253a,#102e49);
    }
    .intel-map-toolbar-group{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
    .intel-map-toolbar label{margin:0;font:.72rem 'JetBrains Mono',monospace;color:#bddcff}
    .intel-map-toolbar select{width:auto;min-width:180px;max-width:240px}
    .intel-map-toolbar button{width:auto;min-width:0}
    .intel-map-toolbar .map-stat{
      display:inline-flex;
      align-items:center;
      gap:6px;
      padding:5px 10px;
      border-radius:999px;
      border:1px solid #3e6e98;
      background:#12314a;
      color:#d5eeff;
      font:.7rem 'JetBrains Mono',monospace;
    }
    .intel-canvas-wrap{position:relative;height:470px;border:1px solid #2f577f;border-radius:11px;background:radial-gradient(circle at 18% 12%,#214865 0,#0d2438 42%,#091728 100%);overflow:hidden;touch-action:none}
    .intel-canvas-wrap svg{position:absolute;inset:0;width:100%;height:100%}
    .intel-node-layer{position:absolute;inset:0;transform-origin:0 0}
    .intel-canvas-wrap.is-panning{cursor:grabbing}
    .intel-canvas-wrap.is-fullscreen{
      height:100vh;
      border-radius:0;
      border-color:#4a7aa1;
    }
    .intel-canvas-dock{
      position:absolute;
      right:14px;
      bottom:14px;
      z-index:6;
      display:grid;
      gap:8px;
      width:min(100%,420px);
      padding:10px;
      border:1px solid #335d83;
      border-radius:14px;
      background:linear-gradient(160deg,rgba(9,23,36,.9),rgba(13,36,56,.88));
      backdrop-filter:blur(14px);
      box-shadow:0 20px 44px -30px #000;
    }
    .intel-canvas-dock-head{
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:10px;
      flex-wrap:wrap;
    }
    .intel-canvas-dock-title{
      display:grid;
      gap:3px;
    }
    .intel-canvas-dock-title b{font-size:.9rem}
    .intel-canvas-dock-title span{font:.68rem 'JetBrains Mono',monospace;color:#b9d9ee}
    .intel-canvas-dock-actions,
    .intel-canvas-dock-tools{
      display:flex;
      gap:8px;
      flex-wrap:wrap;
      align-items:center;
    }
    .intel-canvas-dock .btn{
      width:auto;
      min-width:0;
      padding:8px 11px;
    }
    .intel-node{position:absolute;transform:translate(-50%,-50%);min-width:96px;max-width:200px;padding:8px 10px;border-radius:12px;color:#fff;font:700 .74rem 'Manrope',sans-serif;border:1px solid #ffffff55;cursor:move;user-select:none;box-shadow:0 8px 18px -12px #000a;word-break:break-word}
    .intel-node.active{outline:2px solid #ffd266;z-index:3}
    .intel-node .intel-node-label{font:700 .74rem 'Manrope',sans-serif}
    .intel-node .intel-node-meta{display:flex;gap:4px;flex-wrap:wrap;margin-top:4px}
    .intel-node .node-chip{font:.56rem 'JetBrains Mono',monospace;letter-spacing:.08em;text-transform:uppercase;padding:2px 6px;border-radius:999px;border:1px solid #ffffff44;background:#10263b;color:#d7e7fb}
    .intel-node .node-chip.source{border-color:#7bb9ff;background:#0f2b47;color:#cfe4ff}
    .intel-node .node-chip.hash{border-color:#d9b45a;background:#2d2412;color:#ffe7b6}
    .intel-node .node-chip.ioc{border-color:#6fe0a6;background:#103427;color:#d4ffe8}
    .intel-node .node-chip.artifact{border-color:#ff9f6b;background:#3a1d10;color:#ffe0cf}
    .intel-node .node-chip.vt{border-color:#ff9f43;background:#3a2412;color:#ffe2c2}
    .intel-edge-label{position:absolute;transform:translate(-50%,-50%);font:.68rem 'JetBrains Mono',monospace;color:#d6ebf8;background:#10314a;border:1px solid #3d6991;padding:2px 5px;border-radius:6px;pointer-events:none}
    .intel-side{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;margin-top:10px}
    .intel-side h3{margin:.2rem 0}
    .intel-side .card-box{padding:8px;border:1px solid #2d5277;border-radius:10px;background:#0f2740}
    .intel-side .card-box button{margin-top:6px}
    .intel-share{margin-top:10px;padding:8px;border:1px solid #2ea579;border-radius:10px;background:#103b3f}
    .intel-share a{color:#9de9ff;word-break:break-all}
    .intel-api-map-insights{
      border:1px solid #2e557c;
      border-radius:10px;
      background:linear-gradient(155deg,#102940,#0c2438);
      padding:8px;
      margin:6px 0 10px;
      display:grid;
      gap:7px;
    }
    .intel-api-map-head{
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:8px;
      flex-wrap:wrap;
      font:.72rem 'JetBrains Mono',monospace;
      color:#cbe7ff;
    }
    .intel-api-keywords{
      display:flex;
      gap:6px;
      flex-wrap:wrap;
      align-items:center;
      min-height:28px;
    }
    .intel-api-keyword-chip{
      display:inline-flex;
      align-items:center;
      gap:6px;
      border:1px solid #44729b;
      border-radius:999px;
      padding:3px 10px;
      background:#12314a;
      color:#dcf2ff;
      font:.68rem 'JetBrains Mono',monospace;
      line-height:1;
    }
    .intel-api-keyword-chip b{
      font:700 .67rem 'JetBrains Mono',monospace;
      color:#ffd98e;
    }
    .api-key-row-form{
      display:flex;
      gap:6px;
      align-items:center;
    }
    .api-key-row-form input[type="password"],
    .api-key-row-form input[type="text"]{
      min-width:220px;
    }
    .api-key-toggle{
      width:auto;
      padding:7px 9px;
      border-radius:10px;
      border:1px solid #3a5f88;
      background:linear-gradient(150deg,#102742,#0d2137);
      color:var(--txt);
      font:600 .72rem 'JetBrains Mono',monospace;
      cursor:pointer;
    }
    .api-key-delete-form{display:inline-flex}
    .api-result{
      margin-top:8px;
      border:1px solid #2f5579;
      border-radius:10px;
      background:#0d2237;
      padding:8px;
    }
    .api-result pre{
      margin:6px 0 0;
      max-height:230px;
      overflow:auto;
      border:1px solid #2f5579;
      border-radius:8px;
      background:#091828;
      padding:8px;
      font:.72rem 'JetBrains Mono',monospace;
      white-space:pre-wrap;
      word-break:break-word;
    }
    .vt-summary{
      margin-top:8px;
      border:1px solid #2f5579;
      border-radius:8px;
      background:#0a1d2f;
      padding:8px;
      display:grid;
      gap:8px;
    }
    .vt-summary-head{
      display:flex;
      flex-wrap:wrap;
      gap:8px;
      align-items:center;
      justify-content:space-between;
      font:.72rem 'JetBrains Mono',monospace;
      color:#c8e5ff;
    }
    .vt-stat-grid{
      display:grid;
      grid-template-columns:repeat(auto-fit,minmax(120px,1fr));
      gap:6px;
    }
    .vt-stat-chip{
      border:1px solid #345f84;
      border-radius:10px;
      padding:7px;
      display:grid;
      gap:4px;
      background:#10273d;
      font:.72rem 'JetBrains Mono',monospace;
    }
    .vt-stat-chip span{opacity:.95}
    .vt-stat-chip b{font:800 .94rem 'Sora',sans-serif}
    .vt-stat-malicious{border-color:#8e3444;background:#351422;color:#ffd4dc}
    .vt-stat-suspicious{border-color:#8c7639;background:#332812;color:#ffe9be}
    .vt-stat-harmless{border-color:#2f7a58;background:#123427;color:#c9ffe3}
    .vt-stat-undetected{border-color:#3a5f86;background:#10263b;color:#cde6ff}
    .api-lookup-history{
      margin-top:10px;
      border:1px solid #2f5579;
      border-radius:10px;
      background:#0d2237;
      display:grid;
      gap:8px;
    }
    .api-lookup-history summary{
      list-style:none;
      cursor:pointer;
      margin:0;
      padding:8px;
      font:700 .86rem 'Manrope',sans-serif;
      color:#d8ecff;
    }
    .api-lookup-history summary::-webkit-details-marker{display:none}
    .api-lookup-history[open] summary{
      border-bottom:1px solid #2f5579;
    }
    .api-lookup-history > *:not(summary){
      padding:0 8px 8px 8px;
    }
    .api-lookup-item{
      border:1px solid #2f5579;
      border-radius:8px;
      background:#0a1d2e;
      overflow:hidden;
    }
    .api-lookup-item summary{
      list-style:none;
      cursor:pointer;
      padding:8px;
      display:grid;
      gap:4px;
    }
    .api-lookup-item summary::-webkit-details-marker{display:none}
    .api-lookup-meta{
      font:.72rem 'JetBrains Mono',monospace;
      color:#b4d9f7;
      word-break:break-word;
    }
    .api-lookup-status-ok{color:#89e6b7}
    .api-lookup-status-ko{color:#ffb0b0}
    .api-lookup-body{
      border-top:1px solid #2f5579;
      padding:8px;
      display:grid;
      gap:8px;
    }
    .api-lookup-body pre{
      margin:0;
      max-height:260px;
      overflow:auto;
      border:1px solid #2f5579;
      border-radius:8px;
      background:#091828;
      padding:8px;
      font:.72rem 'JetBrains Mono',monospace;
      white-space:pre-wrap;
      word-break:break-word;
    }
    .intel-accordion{
      border:1px solid #284968;
      border-radius:12px;
      background:#0a1926;
      padding:8px 12px;
      margin-top:10px;
    }
    .intel-accordion > summary{
      cursor:pointer;
      font:700 .8rem 'Manrope',sans-serif;
      color:#dcecff;
      list-style:none;
    }
    .intel-accordion > summary::-webkit-details-marker{display:none}
    .intel-accordion[open]{
      box-shadow:0 10px 24px -18px #000a;
    }
    .vt-reported-wrap{
      margin-top:10px;
      border:1px solid #2f5579;
      border-radius:10px;
      background:#0d2237;
      padding:8px;
      display:grid;
      gap:8px;
    }
    .vt-reported-kpis{
      display:grid;
      gap:6px;
      grid-template-columns:repeat(auto-fit,minmax(130px,1fr));
    }
    .vt-reported-kpi{
      border:1px solid #325b80;
      border-radius:9px;
      background:#102a43;
      padding:7px;
      display:grid;
      gap:3px;
    }
    .vt-reported-kpi .k{
      font:.66rem 'JetBrains Mono',monospace;
      color:#a9cbe7;
      text-transform:uppercase;
    }
    .vt-reported-kpi .v{
      font:800 1.02rem 'Sora',sans-serif;
      color:#e8f6ff;
    }
    .vt-reported-domain-table{
      max-height:260px;
      overflow:auto;
      border:1px solid #2f5579;
      border-radius:8px;
    }
    .vt-domain-badge{
      display:inline-flex;
      align-items:center;
      gap:4px;
      padding:2px 8px;
      border-radius:999px;
      border:1px solid #3f688c;
      background:#10263b;
      font:700 .66rem 'JetBrains Mono',monospace;
      text-transform:uppercase;
      letter-spacing:.2px;
    }
    .vt-domain-badge.malicious{border-color:#a94a56;color:#ffd2d8;background:#3f1822}
    .vt-domain-badge.suspicious{border-color:#8f7844;color:#ffebbe;background:#3e3014}
    .vt-domain-badge.harmless_or_undetected{border-color:#377a57;color:#d7ffea;background:#123626}
    .intel-timeline{
      border:1px solid #2d5277;
      border-radius:11px;
      background:#0f2740;
      padding:10px;
      margin-top:10px;
    }
    .intel-timeline h3{margin:0 0 8px}
    .intel-timeline-list{display:grid;gap:8px;max-height:380px;overflow:auto;padding-right:2px}
    .intel-event-card{
      border:1px solid #2e557b;
      border-radius:10px;
      background:#0b2135;
      padding:8px;
      display:grid;
      gap:5px;
    }
    .intel-event-head{
      display:flex;
      justify-content:space-between;
      align-items:center;
      gap:8px;
      flex-wrap:wrap;
      font:.72rem 'JetBrains Mono',monospace;
      color:#b7d3eb;
    }
    .intel-event-action{
      display:inline-flex;
      align-items:center;
      gap:4px;
      padding:2px 7px;
      border:1px solid #3a648b;
      border-radius:999px;
      background:#12304a;
      color:#d6ecff;
      font:.67rem 'JetBrains Mono',monospace;
    }
    .intel-event-detail{
      font:.74rem 'JetBrains Mono',monospace;
      color:#dceeff;
      line-height:1.35;
      white-space:pre-wrap;
      word-break:break-word;
    }
    .intel-public{max-width:1760px;margin:18px auto;padding:0 12px}
    .intel-public .card{margin-bottom:10px}
    .intel-public-meta{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px}
    .intel-public-meta .m{padding:8px;border:1px solid #2e5378;border-radius:9px;background:#102842}
    .rbac{margin-top:8px;display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:8px}
    .rbac .item{padding:8px;border-radius:10px;border:1px solid #2f5579;background:#102842}
    .rbac .item b{display:block;font-size:.82rem}
    .rbac .item span{color:#bcdced;font-size:.78rem}
    .geo-map{height:360px;border:1px solid #2e5378;border-radius:10px;overflow:hidden;background:#0f2236}
    .geo-map-legend{display:flex;gap:8px;flex-wrap:wrap;margin:8px 0 0}
    .geo-chip{padding:3px 8px;border-radius:999px;border:1px solid #355b82;background:#102742;font:.7rem 'JetBrains Mono',monospace}
    .geo-chip b{color:#fff}
    .geo-subtitle{margin:0 0 8px;color:#b8d1ea}
    .geo-table-wrap{max-height:280px;overflow:auto;margin-top:8px}
    .trend-bar{height:8px;border-radius:999px;background:#183f5d;overflow:hidden}
    .trend-bar > span{display:block;height:100%;border-radius:999px}
    .trend-bar.alerts > span{background:#14b8ff}
    .trend-bar.blocks > span{background:#38d17a}
    .mut-mini{font-size:.75rem;color:#9fb6d1}
    .chart-stack{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;margin:8px 0 10px}
    .chart-card{
      border:1px solid #2f5579;
      border-radius:10px;
      background:linear-gradient(160deg,#0f2a43,#0b2035);
      padding:8px;
    }
    .analytics-kpi-grid{
      display:grid;
      grid-template-columns:repeat(3,minmax(0,1fr));
      gap:8px;
      margin:8px 0;
    }
    .analytics-kpi{
      border:1px solid #2f5579;
      border-radius:10px;
      background:linear-gradient(160deg,#102941,#0c2237);
      padding:8px;
    }
    .analytics-kpi .k{font:.66rem 'JetBrains Mono',monospace;color:#a7c6e3}
    .analytics-kpi .v{font:700 1.05rem 'Sora',sans-serif;color:#e6f3ff;margin-top:3px}
    .analytics-search{
      max-width:320px;
      margin:6px 0 8px;
    }
    .analytics-table-wrap{
      max-height:290px;
      overflow:auto;
      border:1px solid #2f5579;
      border-radius:10px;
      background:#0b2033;
    }
    .analytics-table-wrap table{margin:0}
    .analytics-table-wrap thead th{
      position:sticky;
      top:0;
      z-index:2;
      background:linear-gradient(120deg,rgba(24,49,75,.95),rgba(17,34,54,.95));
    }
    .exposure-grid{
      display:grid;
      grid-template-columns:repeat(auto-fit,minmax(320px,1fr));
      gap:8px;
      margin-top:8px;
    }
    .exposure-kpi-grid{
      grid-template-columns:repeat(auto-fit,minmax(160px,1fr));
    }
    .exposure-note{
      margin:6px 0 0;
      color:#a9c8e6;
      font:.74rem 'JetBrains Mono',monospace;
      line-height:1.45;
    }
    .exposure-flag-wrap{
      display:flex;
      flex-wrap:wrap;
      gap:5px;
    }
    .exposure-flag{
      display:inline-flex;
      align-items:center;
      gap:4px;
      padding:2px 8px;
      border-radius:999px;
      border:1px solid #40688e;
      background:#102842;
      color:#dcefff;
      font:.66rem 'JetBrains Mono',monospace;
      text-transform:uppercase;
      letter-spacing:.03em;
    }
    .exposure-flag.referrer_match{border-color:#7d5df6;color:#eadfff;background:#211642}
    .exposure-flag.direct_ip_overlap{border-color:#c95f5f;color:#ffd8d8;background:#3a171e}
    .exposure-flag.network_overlap{border-color:#d49a43;color:#ffe9bf;background:#3a2a14}
    .exposure-flag.proxy,.exposure-flag.hosting{border-color:#b04f6a;color:#ffd8e4;background:#391524}
    .exposure-flag.mobile{border-color:#3d7db4;color:#d2edff;background:#10293f}
    .exposure-domains{
      font:.68rem 'JetBrains Mono',monospace;
      color:#bcdced;
      line-height:1.4;
      word-break:break-word;
    }
    .compact-table th,.compact-table td{padding:5px 7px;font-size:.74rem}
    .chart-card .chart-title{margin:0 0 6px;font:.72rem 'JetBrains Mono',monospace;color:#bcd8f2}
    .chart-canvas{width:100%;height:180px;display:block;border-radius:8px;background:#0a1d30}
    .chart-legend{display:flex;gap:8px;flex-wrap:wrap;margin-top:6px}
    .chart-legend .dot{width:8px;height:8px;border-radius:999px;display:inline-block;margin-right:5px}
    .chart-legend span{font:.68rem 'JetBrains Mono',monospace;color:#a7c6e3}
    .profile-shell{display:grid;grid-template-columns:minmax(280px,340px) minmax(0,1fr);gap:10px}
    .profile-card{padding:12px;border:1px solid #355a82;border-radius:12px;background:#0c253d}
    .profile-avatar{width:78px;height:78px;border-radius:999px;display:grid;place-items:center;font:800 1.5rem 'Sora',sans-serif;background:linear-gradient(145deg,#63d9ff,#57f0be);color:#032640;margin-bottom:10px}
    .profile-avatar.has-image{background:#0d2036;padding:0;overflow:hidden}
    .profile-avatar.has-image img{width:100%;height:100%;object-fit:cover;display:block}
    .profile-name{margin:0;font-size:1.15rem}
    .profile-nick{font:.8rem 'JetBrains Mono',monospace;color:#9cc6e5}
    .profile-meta{display:grid;gap:6px;margin-top:10px}
    .profile-meta .rowx{display:flex;justify-content:space-between;gap:8px;border:1px solid #2f557a;border-radius:9px;padding:6px 8px;background:#112d47}
    .profile-tabs{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:8px}
    .profile-tab{padding:6px 10px;border-radius:10px;border:1px solid #335a80;background:#112b44;color:#d9ecff;text-decoration:none;font:.78rem 'JetBrains Mono',monospace}
    .profile-tab.active{border-color:#58dcaf;background:#12483d;color:#d9fff3}
    hr{border:none;border-top:1px solid #2a4b6d;margin:10px 0}
    pre{white-space:pre-wrap;word-break:break-word}
    input,select,textarea,button{max-width:100%}
    @media(min-width:1700px){
      .grid{grid-template-columns:repeat(auto-fit,minmax(220px,1fr))}
      .kpi{min-height:84px}
      .kpi .mut{font-size:.67rem}
    }
    @media(min-width:1900px){
      .workspace{grid-template-columns:minmax(0,1fr) clamp(320px,18vw,420px)}
    }
    @media(max-width:1320px){
      .workspace{grid-template-columns:1fr}
      .side-column{position:static}
    }
    @media(max-width:920px){
      .wrap{
        width:min(100%,99vw);
        padding:
          calc(8px + env(safe-area-inset-top))
          max(6px, env(safe-area-inset-right))
          calc(16px + env(safe-area-inset-bottom))
          max(6px, env(safe-area-inset-left));
      }
      .top{
        top:max(6px, env(safe-area-inset-top));
        padding:12px;
      }
      .app-header-main{
        gap:10px;
        align-items:center;
      }
      .app-header-brand,
      .top-status{
        min-width:0;
        width:100%;
        flex-basis:100%;
      }
      .app-header-brand{
        gap:4px;
      }
      .app-header-title{
        gap:8px;
      }
      .app-header-title b{
        font-size:.96rem;
      }
      .app-header-subline{
        display:none;
      }
      .top-status{
        justify-content:flex-start;
        flex-wrap:nowrap;
        overflow-x:auto;
        -webkit-overflow-scrolling:touch;
        scrollbar-width:none;
        padding-bottom:2px;
      }
      .top-status::-webkit-scrollbar{
        display:none;
      }
      .status-chip,
      .module-chip{
        flex:0 0 auto;
        white-space:nowrap;
      }
      .nav-toggle{
        display:inline-flex;
        margin-left:auto;
      }
      .app-header-navrow{
        display:none;
        width:100%;
        padding-top:8px;
        margin-top:2px;
        max-height:calc(100dvh - 132px - env(safe-area-inset-top));
        overflow:auto;
        -webkit-overflow-scrolling:touch;
      }
      .top.is-nav-open .app-header-navrow{
        display:grid;
        gap:10px;
      }
      .header-nav{
        overflow:visible;
        flex:0 1 auto;
        width:100%;
        display:grid;
        grid-template-columns:repeat(2,minmax(0,1fr));
        gap:8px;
        padding-bottom:0;
      }
      .header-nav a,
      .nav-actions .nav-btn,
      .module-chip,
      .status-chip{
        min-height:44px;
      }
      .header-nav a{
        width:100%;
        justify-content:center;
      }
      .nav-actions{
        width:100%;
        justify-content:flex-start;
        display:grid;
        grid-template-columns:repeat(2,minmax(0,1fr));
        gap:8px;
      }
      .nav-actions form{
        margin:0;
        display:contents;
      }
      .nav-actions .nav-btn{
        width:100%;
        justify-content:center;
      }
      input,
      select,
      textarea,
      button{
        font-size:16px;
        min-height:44px;
      }
      textarea{min-height:110px}
      .hero-main,
      .hero-side,
      .card,
      .side-card,
      .intel-editor-section,
      .intel-selector-shell{
        padding-left:10px;
        padding-right:10px;
      }
      .geo-map{
        height:300px !important;
      }
      .chart-canvas{
        height:200px;
      }
    }
    @media(max-width:1140px){
      .grid{grid-template-columns:repeat(2,minmax(0,1fr))}
      .hero,.row,.split,.event-workbench,.event-columns,.event-grid,.intel-layout,.intel-grid,.intel-side,.intel-public-meta,.rbac,.viz-grid,.profile-shell,.chart-stack,.analytics-kpi-grid,.intel-kpi-grid,.intel-stage-bar,.intel-selector-grid,.intel-cockpit,.intel-workbench-grid,.intel-workbench-kpis{grid-template-columns:1fr}
      .app-header-navrow{align-items:flex-start}
      .nav-actions{width:100%;justify-content:flex-start}
      .intel-focus-hero{grid-template-columns:1fr}
    }
    @media(max-width:700px){
      :root{--sticky-header-offset:12px}
      .wrap{
        width:min(100%,98vw);
        padding:
          max(8px, env(safe-area-inset-top))
          max(4px, env(safe-area-inset-right))
          calc(18px + env(safe-area-inset-bottom))
          max(4px, env(safe-area-inset-left));
      }
      body{overflow-x:hidden}
      .top{
        top:max(6px, env(safe-area-inset-top));
        padding:12px;
        position:static;
      }
      .side-column{
        position:static;
      }
      .grid{grid-template-columns:1fr}
      .top-status{justify-content:flex-start}
      .nav-actions{justify-content:flex-start}
      .top,
      .card,
      .profile-card,
      .intel-editor-section,
      .event-detail-shell,
      .intel-editor,
      .intel-selector-shell,
      .intel-cockpit-card,
      .intel-topbar,
      .intel-list,
      .analytics-table-wrap{
        border-radius:14px;
      }
      .intel-focus-tabs{gap:6px}
      .intel-tab{flex:1 1 100%;text-align:center}
      .intel-focus-meta{padding:10px}
      .top,
      .card{
        padding-left:10px;
        padding-right:10px;
      }
      .app-header-navrow{
        gap:6px;
      }
      .header-nav{
        grid-template-columns:1fr;
      }
      .header-nav a,
      .nav-actions .nav-btn{
        min-height:44px;
      }
      .intel-focus-bar,
      .intel-workspace-nav{
        position:static;
        top:auto;
      }
      .nav-actions{
        width:100%;
        gap:6px;
        grid-template-columns:1fr;
      }
      .nav-actions .nav-btn,
      .bulk-review-toolbar .bulk-review-actions button,
      .intel-toolbar button,
      .intel-toolbar .btn,
      .intel-map-toolbar button,
      .intel-focus-actions .btn,
      .intel-canvas-dock .btn{
        min-height:44px;
      }
      input,
      select,
      textarea,
      button{
        font-size:16px;
        min-height:44px;
      }
      textarea{min-height:110px}
      .app-header-title b{font-size:.92rem}
      .module-chip,
      .status-chip,
      .badge,
      .event-chip,
      .intel-chip{
        font-size:.68rem;
      }
      table{
        display:block;
        width:100%;
        max-width:100%;
        overflow-x:auto;
        -webkit-overflow-scrolling:touch;
      }
      table thead,
      table tbody{
        width:max-content;
        min-width:100%;
      }
      th,
      td{
        white-space:nowrap;
        vertical-align:top;
      }
      td .btn,
      td button,
      td select,
      td input[type="text"],
      td input[type="number"],
      td input[type="file"]{
        width:100%;
        min-width:0;
      }
      td pre,
      td .mono,
      td .mut-mini,
      .event-context pre,
      .api-result pre{
        white-space:pre-wrap;
        word-break:break-word;
      }
      .event-feed{
        max-height:none;
        padding-right:0;
      }
      .event-feed-item,
      .intel-item{
        padding:10px;
      }
      .event-grid,
      .event-columns,
      .intel-grid,
      .intel-side,
      .profile-meta,
      .intel-kpi-grid,
      .intel-workbench-kpis,
      .side-metrics,
      .vt-stat-grid,
      .intel-selector-grid,
      .intel-cockpit{
        grid-template-columns:1fr;
      }
      .workspace,
      .row,
      .split,
      .event-workbench,
      .intel-layout,
      .intel-workbench-grid,
      .profile-shell,
      .chart-stack,
      .intel-selector-grid,
      .intel-cockpit{
        gap:10px;
      }
      .event-detail-shell,
      .intel-editor,
      .intel-editor-section,
      .profile-card{
        overflow:hidden;
      }
      .intel-focus-bar,
      .intel-map-toolbar,
      .intel-selector-head,
      .intel-section-head,
      .bulk-review-toolbar,
      .vt-summary-head,
      .event-topline,
      .event-related-head{
        align-items:stretch;
      }
      .intel-map-toolbar-group,
      .intel-focus-actions,
      .intel-picker-actions,
      .intel-quick-grid,
      .intel-canvas-dock-actions,
      .intel-canvas-dock-tools,
      .bulk-review-toolbar .bulk-review-actions{
        width:100%;
      }
      .intel-map-toolbar select,
      .intel-map-toolbar button,
      .intel-focus-actions .btn,
      .intel-picker-actions .btn,
      .intel-quick-grid .btn,
      .intel-workspace-nav .btn,
      .intel-canvas-dock .btn,
      .intel-toolbar form,
      .intel-toolbar a,
      .event-quick-form,
      td form,
      .api-key-row-form,
      .api-key-row-form input[type="password"],
      .api-key-row-form input[type="text"]{
        width:100%;
        min-width:0;
      }
      .event-quick-form,
      td form,
      form[style*="display:flex"]{
        display:flex !important;
        flex-direction:column !important;
        align-items:stretch !important;
        gap:8px !important;
      }
      .event-quick-form > *,
      td form > *,
      form[style*="display:flex"] > *{
        width:100% !important;
        min-width:0 !important;
      }
      .intel-canvas-dock{
        left:10px;
        right:10px;
        bottom:10px;
        width:auto;
        max-height:min(42vh, 360px);
        overflow:auto;
      }
      .api-key-row-form{
        flex-direction:column;
        align-items:stretch;
      }
      .intel-canvas-wrap{
        height:58vh;
        min-height:320px;
        max-height:520px;
      }
      .geo-map{
        height:320px !important;
      }
      .chart-canvas{
        height:210px;
      }
      .profile-avatar{
        width:64px;
        height:64px;
      }
      .announcement-aside summary{
        align-items:center;
      }
    }
    @media(max-width:520px){
      .wrap{width:100%}
      .top{
        padding:10px;
        gap:10px;
      }
      .app-header-main,
      .app-header-navrow{
        gap:10px;
      }
      .top-status,
      .top .mut{
        width:100%;
      }
      .side-card,
      .profile-card,
      .intel-editor-section,
      .intel-selector-shell{
        padding:9px;
      }
      .intel-cockpit-card.actions{
        grid-column:auto;
      }
      .event-feed-host,
      .event-kv span,
      .intel-kpi span,
      .profile-name{
        word-break:break-word;
      }
      .intel-canvas-wrap{
        height:52vh;
        min-height:280px;
      }
      .intel-canvas-dock{
        max-height:min(48vh, 340px);
      }
      .geo-map{
        height:280px !important;
      }
      .compact-table th,
      .compact-table td{
        padding:6px;
        font-size:.72rem;
      }
      .chart-legend span,
      .mut,
      .event-feed-meta,
      .event-related-note{
        font-size:.72rem;
      }
    }
  </style>
</head>
<body class="<?= clickfix_h($bodyClass); ?>">
  <div class="wrap">
    <header class="top" id="app-header">
      <div class="app-header-main">
        <div class="app-header-brand">
          <div class="app-header-title">
            <b>ClickFix Unified Operations Center</b>
            <div class="module-chip"><?= clickfix_h(cft('label_module')); ?>: <?= clickfix_h($currentPageLabel); ?></div>
          </div>
          <div class="app-header-subline">
            <span>telemetry intelligence</span>
            <span class="sep">|</span>
            <span>investigation workspace</span>
            <span class="sep">|</span>
            <span>secure operations</span>
          </div>
        </div>
        <div class="top-status">
          <span class="status-chip">records: <?= (int) ($metrics['total_alerts'] ?? 0); ?></span>
          <span class="status-chip">module: <?= clickfix_h($currentPageLabel); ?></span>
          <?php if ($loggedIn): ?><span class="status-chip">operator: <a class="user-link" href="<?= clickfix_h(cfprofileurl((int) ($user['id'] ?? 0), [], false)); ?>"><?= clickfix_h((string) $user['username']); ?></a></span><?php endif; ?>
          <span class="status-chip">
            <?= clickfix_h(cft('lang_label')); ?>:
            <a href="<?= clickfix_h(cfurl($page, !$loggedIn, ['lang' => 'es'])); ?>">ES</a> |
            <a href="<?= clickfix_h(cfurl($page, !$loggedIn, ['lang' => 'en'])); ?>">EN</a> |
            <a href="<?= clickfix_h(cfurl($page, !$loggedIn, ['lang' => 'ca'])); ?>">CA</a> |
            <a href="<?= clickfix_h(cfurl($page, !$loggedIn, ['lang' => 'de'])); ?>">DE</a> |
            <a href="<?= clickfix_h(cfurl($page, !$loggedIn, ['lang' => 'fr'])); ?>">FR</a> |
            <a href="<?= clickfix_h(cfurl($page, !$loggedIn, ['lang' => 'it'])); ?>">IT</a>
          </span>
        </div>
        <button class="nav-toggle" type="button" id="nav-toggle" aria-expanded="false" aria-controls="app-header-navrow">
          <span class="nav-toggle-lines" aria-hidden="true"><span></span><span></span><span></span></span>
          <span>Menu</span>
        </button>
      </div>
      <div class="app-header-navrow" id="app-header-navrow">
        <nav class="header-nav" aria-label="Primary">
          <a class="<?= $page === 'home' ? 'active' : ''; ?>" href="<?= clickfix_h(cfurl('home', true)); ?>"><?= clickfix_h(cft('nav_home')); ?></a>
          <a class="<?= $page === 'search' ? 'active' : ''; ?>" href="<?= clickfix_h(cfurl('search', true)); ?>"><?= clickfix_h(cft('nav_search')); ?></a>
          <a class="<?= $page === 'coverage' ? 'active' : ''; ?>" href="<?= clickfix_h(cfurl('coverage', true)); ?>"><?= clickfix_h(cft('nav_coverage')); ?></a>
          <a class="<?= $page === 'about' ? 'active' : ''; ?>" href="<?= clickfix_h(cfurl('about', true)); ?>"><?= clickfix_h(cft('nav_about')); ?></a>
          <?php if ($loggedIn): ?>
            <a class="<?= $page === 'profile' ? 'active' : ''; ?>" href="<?= clickfix_h(cfprofileurl((int) ($user['id'] ?? 0), [], false)); ?>"><?= clickfix_h(cft('nav_profile')); ?></a>
            <a class="<?= $page === 'settings' ? 'active' : ''; ?>" href="<?= clickfix_h(cfurl('settings')); ?>"><?= clickfix_h(cft('nav_settings')); ?></a>
            <a class="<?= $page === 'ops' ? 'active' : ''; ?>" href="<?= clickfix_h(cfurl('ops')); ?>"><?= clickfix_h(cft('nav_ops')); ?></a>
            <a class="<?= $page === 'analytics' ? 'active' : ''; ?>" href="<?= clickfix_h(cfurl('analytics')); ?>"><?= clickfix_h(cft('nav_graphs')); ?></a>
            <a class="<?= $page === 'intel_stats' ? 'active' : ''; ?>" href="<?= clickfix_h(cfurl('intel_stats')); ?>"><?= clickfix_h(cft('nav_intel_stats')); ?></a>
            <a class="<?= ($page === 'intel' || $page === 'investigation') ? 'active' : ''; ?>" href="<?= clickfix_h(cfurl('intel')); ?>"><?= clickfix_h(cft('nav_investigation')); ?></a>
            <a class="<?= $page === 'community' ? 'active' : ''; ?>" href="<?= clickfix_h(cfurl('community')); ?>"><?= clickfix_h(cft('nav_community')); ?></a>
            <?php if (cfcan($user, 'analyst_sr')): ?>
              <a class="<?= $page === 'extensions' ? 'active' : ''; ?>" href="<?= clickfix_h(cfurl('extensions')); ?>"><?= clickfix_h(cft('nav_extensions')); ?></a>
              <a class="<?= $page === 'lists' ? 'active' : ''; ?>" href="<?= clickfix_h(cfurl('lists')); ?>"><?= clickfix_h(cft('nav_lists')); ?></a>
              <a class="<?= $page === 'requests' ? 'active' : ''; ?>" href="<?= clickfix_h(cfurl('requests')); ?>"><?= clickfix_h(cft('nav_requests')); ?></a>
              <a class="<?= $page === 'messaging' ? 'active' : ''; ?>" href="<?= clickfix_h(cfurl('messaging')); ?>"><?= clickfix_h(cft('nav_messaging')); ?></a>
              <a class="<?= $page === 'data_center' ? 'active' : ''; ?>" href="<?= clickfix_h(cfurl('data_center')); ?>"><?= clickfix_h(cft('nav_data_center')); ?></a>
            <?php endif; ?>
            <?php if (cfcan($user, 'admin')): ?>
              <a class="<?= $page === 'configs' ? 'active' : ''; ?>" href="<?= clickfix_h(cfurl('configs')); ?>"><?= clickfix_h(cft('nav_score_config')); ?></a>
              <a class="<?= $page === 'reports' ? 'active' : ''; ?>" href="<?= clickfix_h(cfurl('reports')); ?>"><?= clickfix_h(cft('nav_reports')); ?></a>
              <a class="<?= $page === 'users' ? 'active' : ''; ?>" href="<?= clickfix_h(cfurl('users')); ?>"><?= clickfix_h(cft('nav_users')); ?></a>
            <?php endif; ?>
          <?php endif; ?>
        </nav>
        <div class="nav-actions">
          <a class="nav-btn" href="/">⌂ /</a>
          <button class="nav-btn" type="button" id="display-settings-toggle">Display</button>
          <a class="nav-btn<?= $page === 'access' ? ' active' : ''; ?>" href="<?= clickfix_h(cfurl('access', true)); ?>"><?= clickfix_h(cft('nav_access')); ?></a>
          <?php if ($loggedIn): ?>
            <form method="post">
              <input type="hidden" name="action" value="logout">
              <button type="submit" class="nav-btn logout">Cerrar sesion</button>
            </form>
          <?php endif; ?>
        </div>
      </div>
    </header>
    <div class="display-settings-panel" id="display-settings-panel" aria-hidden="true">
      <div class="display-settings-header">
        <h4>Display Settings</h4>
        <button class="nav-btn" type="button" id="display-settings-close">×</button>
      </div>
      <div class="display-settings-grid">
        <div class="display-toggle">
          <div class="label">Dark mode</div>
          <label class="switch">
            <input type="checkbox" data-setting="dark">
            <span class="switch-track"><span class="switch-thumb"></span></span>
          </label>
        </div>
        <div class="display-toggle">
          <div class="label">Contrast</div>
          <label class="switch">
            <input type="checkbox" data-setting="contrast">
            <span class="switch-track"><span class="switch-thumb"></span></span>
          </label>
        </div>
        <div class="display-toggle">
          <div class="label">Compact</div>
          <label class="switch">
            <input type="checkbox" data-setting="compact">
            <span class="switch-track"><span class="switch-thumb"></span></span>
          </label>
        </div>
        <div class="display-toggle">
          <div class="label">Reduce Motion</div>
          <label class="switch">
            <input type="checkbox" data-setting="reducedMotion">
            <span class="switch-track"><span class="switch-thumb"></span></span>
          </label>
        </div>
        <div class="display-toggle">
          <div class="label">Decorations</div>
          <label class="switch">
            <input type="checkbox" data-setting="decorations">
            <span class="switch-track"><span class="switch-thumb"></span></span>
          </label>
        </div>
      </div>
      <div class="display-section">
        <h5>Presets</h5>
        <div class="preset-grid">
          <button class="preset-btn" type="button" data-accent="blue"><span style="background:#5b8bff"></span></button>
          <button class="preset-btn" type="button" data-accent="green"><span style="background:#37e3a7"></span></button>
          <button class="preset-btn" type="button" data-accent="purple"><span style="background:#a685ff"></span></button>
          <button class="preset-btn" type="button" data-accent="amber"><span style="background:#ffb454"></span></button>
          <button class="preset-btn" type="button" data-accent="red"><span style="background:#ff7a86"></span></button>
          <button class="preset-btn" type="button" data-accent="cyan"><span style="background:#5fd8ff"></span></button>
        </div>
      </div>
      <div class="display-section">
        <h5>Font</h5>
        <div class="font-grid">
          <button class="font-btn" type="button" data-font="public"><b>Public Sans</b><span>UI sans</span></button>
          <button class="font-btn" type="button" data-font="dm"><b>DM Sans</b><span>Modern sans</span></button>
          <button class="font-btn" type="button" data-font="nunito"><b>Nunito Sans</b><span>Rounded</span></button>
          <button class="font-btn" type="button" data-font="sora"><b>Sora</b><span>Tech display</span></button>
        </div>
      </div>
    </div>
    <div class="workspace">
      <main class="main-column">

    
    <?php if ($flash): ?><div class="flash"><?= clickfix_h($flash); ?></div><?php endif; ?>

    <?php if ($page === 'home'): ?>
      <section class="hero">
        <article class="card hero-main">
          <span class="hero-kicker">ClickFix Command Center</span>
          <h1>Deteccion, explicabilidad y respuesta en una sola superficie de control.</h1>
          <p>Fusion entre la version anterior y la actual: inteligencia publica, operaciones privadas, investigacion en grafo, listas de control y mensajeria con la extension.</p>
          <div class="rbac">
            <div class="item"><b>Busqueda forense</b><span>Filtra por dominio, comando, fecha y score para detectar patrones.</span></div>
            <div class="item"><b>Operaciones</b><span>Actualiza revision, bloquea dominios y manda casos a investigacion.</span></div>
            <div class="item"><b>Investigacion</b><span>Construye grafos con nodos, relaciones, notas y evidencias compartibles.</span></div>
            <div class="item"><b>Cobertura</b><span>Revisa fuentes de inteligencia, listas y configuracion de scoring.</span></div>
          </div>
        </article>
        <article class="card hero-side">
          <div class="mut mono" style="margin-bottom:6px">Inicio rapido</div>
          <div class="role-chip<?= $loggedIn ? '' : ' guest'; ?>"><?= clickfix_h($loggedIn ? 'Workspace autenticado' : 'Vista publica'); ?></div>
          <p class="mut" style="margin:10px 0 0;">
            <?php if ($loggedIn): ?>
              Empieza en Inicio para metricas y mapas, sigue en Operaciones para triage y termina en Investigacion para documentar el caso.
            <?php else: ?>
              Vista publica activa. Puedes consultar cobertura y buscar eventos; inicia sesion para ejecutar acciones operativas.
            <?php endif; ?>
          </p>
          <ul class="mut" style="margin:10px 0 0 16px;">
            <li>1) Revisa KPIs y alertas recientes.</li>
            <li>2) Filtra dominios/comandos sospechosos.</li>
            <li>3) Ejecuta bloqueo o abre investigacion.</li>
          </ul>
        </article>
      </section>
    <?php endif; ?>

    <?php if ($page === 'home'): ?>
    <section class="grid">
      <article class="card kpi"><div class="kpi-top"><div class="mut mono">alertas totales</div><span class="kpi-icon"><svg viewBox="0 0 24 24"><path d="M12 9v4"/><path d="M12 17h.01"/><path d="M10.3 3.6 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.6a2 2 0 0 0-3.4 0z"/></svg></span></div><b data-live-metric="total_alerts"><?= (int) $metrics['total_alerts']; ?></b></article>
      <article class="card kpi"><div class="kpi-top"><div class="mut mono">bloqueos totales</div><span class="kpi-icon"><svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="10" rx="2"/><path d="M7 11V8a5 5 0 0 1 10 0v3"/></svg></span></div><b data-live-metric="total_blocks"><?= (int) $metrics['total_blocks']; ?></b></article>
      <article class="card kpi"><div class="kpi-top"><div class="mut mono">dominios unicos</div><span class="kpi-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M3 12h18"/><path d="M12 3a14 14 0 0 1 0 18"/><path d="M12 3a14 14 0 0 0 0 18"/></svg></span></div><b data-live-metric="unique_hosts"><?= (int) $metrics['unique_hosts']; ?></b></article>
      <article class="card kpi"><div class="kpi-top"><div class="mut mono">regiones monitorizadas</div><span class="kpi-icon"><svg viewBox="0 0 24 24"><path d="M3 12h18"/><path d="M12 3a14 14 0 0 1 0 18"/><path d="M12 3a14 14 0 0 0 0 18"/><circle cx="12" cy="12" r="9"/></svg></span></div><b data-live-metric="countries_count"><?= (int) ($metrics['countries_count'] ?? 0); ?></b></article>
      <article class="card kpi"><div class="kpi-top"><div class="mut mono">alertas 24h</div><span class="kpi-icon"><svg viewBox="0 0 24 24"><path d="M3 12h5l2-4 4 8 2-4h5"/></svg></span></div><b data-live-metric="alerts_24h"><?= (int) $metrics['alerts_24h']; ?></b></article>
      <article class="card kpi"><div class="kpi-top"><div class="mut mono">bloqueos 24h</div><span class="kpi-icon"><svg viewBox="0 0 24 24"><path d="M5 12l4 4L19 6"/></svg></span></div><b data-live-metric="blocks_24h"><?= (int) $metrics['blocks_24h']; ?></b></article>
      <article class="card kpi"><div class="kpi-top"><div class="mut mono">ratio bloqueo 24h</div><span class="kpi-icon"><svg viewBox="0 0 24 24"><path d="M4 20V10"/><path d="M10 20V4"/><path d="M16 20v-7"/><path d="M22 20v-3"/></svg></span></div><b data-live-metric="block_rate_24h"><?= number_format((float) ($metrics['block_rate_24h'] ?? 0.0), 2); ?>%</b></article>
      <article class="card kpi"><div class="kpi-top"><div class="mut mono">alto riesgo 24h</div><span class="kpi-icon"><svg viewBox="0 0 24 24"><path d="M12 2v8"/><path d="M12 14v8"/><path d="m4.9 4.9 5.7 5.7"/><path d="m13.4 13.4 5.7 5.7"/><path d="M2 12h8"/><path d="M14 12h8"/><path d="m4.9 19.1 5.7-5.7"/><path d="m13.4 10.6 5.7-5.7"/></svg></span></div><b data-live-metric="high_risk_24h"><?= (int) ($metrics['high_risk_24h'] ?? 0); ?></b></article>
      <article class="card kpi"><div class="kpi-top"><div class="mut mono">nuevos dominios 24h</div><span class="kpi-icon"><svg viewBox="0 0 24 24"><path d="M12 3v18"/><path d="M3 12h18"/><path d="M4 7h16"/><path d="M4 17h16"/></svg></span></div><b data-live-metric="new_domains_24h"><?= (int) ($metrics['new_domains_24h'] ?? 0); ?></b></article>
      <article class="card kpi"><div class="kpi-top"><div class="mut mono">pend. fuera listas</div><span class="kpi-icon"><svg viewBox="0 0 24 24"><path d="M4 7h16"/><path d="M4 12h10"/><path d="M4 17h8"/><circle cx="18" cy="17" r="3"/></svg></span></div><b data-live-metric="pending_domains_outside_lists"><?= (int) ($metrics['pending_domains_outside_lists'] ?? 0); ?></b></article>
      <article class="card kpi"><div class="kpi-top"><div class="mut mono">revisadas</div><span class="kpi-icon"><svg viewBox="0 0 24 24"><path d="M5 12l4 4L19 6"/><path d="M3 3h18v18H3z"/></svg></span></div><b data-live-metric="reviewed_total"><?= (int) ($metrics['reviewed_total'] ?? 0); ?></b></article>
      <article class="card kpi"><div class="kpi-top"><div class="mut mono">cobertura revision</div><span class="kpi-icon"><svg viewBox="0 0 24 24"><path d="M12 3a9 9 0 1 0 9 9"/><path d="M12 12 19 5"/></svg></span></div><b data-live-metric="review_coverage_pct"><?= number_format((float) ($metrics['review_coverage_pct'] ?? 0.0), 2); ?>%</b></article>
      <article class="card kpi"><div class="kpi-top"><div class="mut mono">sitios manuales</div><span class="kpi-icon"><svg viewBox="0 0 24 24"><path d="M8 6h13"/><path d="M8 12h13"/><path d="M8 18h13"/><path d="M3 6h.01"/><path d="M3 12h.01"/><path d="M3 18h.01"/></svg></span></div><b data-live-metric="manual_sites_count"><?= (int) $metrics['manual_sites_count']; ?></b></article>
      <article class="card kpi"><div class="kpi-top"><div class="mut mono">pend. revision</div><span class="kpi-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></svg></span></div><b data-live-metric="pending_review_total"><?= (int) ($metrics['pending_review_total'] ?? 0); ?></b></article>
    </section>

    <?php if ($loggedIn): ?>
      <section class="card" style="margin-bottom:8px">
        <h2>Pendientes de revision (todas las alertas)</h2>
        <p class="mut">Incluye alertas que ya estan en listas (allow/block). Los pendientes fuera de listas son un subconjunto para triage rapido.</p>
        <div class="analytics-kpi-grid" style="margin-bottom:10px">
          <div class="analytics-kpi"><div class="k">pendientes revision</div><div class="v"><?= (int) ($metrics['pending_review_total'] ?? 0); ?></div></div>
          <div class="analytics-kpi"><div class="k">pendientes fuera de listas</div><div class="v"><?= (int) ($pendingOutsideSummary['alerts'] ?? 0); ?></div></div>
        </div>
        <?php if (empty($pendingReviewRows)): ?>
          <p class="mut">No hay alertas pendientes de revision en este momento.</p>
        <?php else: ?>
          <div class="analytics-table-wrap">
            <table class="compact-table">
              <thead><tr><th>ID</th><th>Fecha</th><th>Dominio</th><th>Score</th><th>Tipo</th><th>Bloqueado</th><th>Accion</th></tr></thead>
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
              <thead><tr><th>ID</th><th>Fecha</th><th>Dominio</th><th>Score</th><th>Tipo</th><th>Bloqueado</th><th>Accion</th></tr></thead>
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
      <h2>Ultimo escaneo</h2>
      <p class="mut">Vista previa: <b>Despues</b> llega desde la extension al detectar alerta y <b>Antes</b> se genera en servidor (Site-Shot) tras recibir ese after.</p>
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
                  <a class="btn" href="<?= clickfix_h($adminPreviewUrl); ?>" target="_blank" rel="noopener noreferrer">Abrir pestaña</a>
                  <a class="btn" href="<?= clickfix_h($adminDownloadUrl); ?>">Descargar</a>
                  <button class="btn" type="button" data-copy-text="<?= clickfix_h($adminPreviewUrl); ?>">Copiar URL admin</button>
                  <?php if ($publicApprovedUrl !== ''): ?>
                    <a class="btn" href="<?= clickfix_h($publicApprovedUrl); ?>" target="_blank" rel="noopener noreferrer">Abrir URL publica</a>
                    <button class="btn" type="button" data-copy-text="<?= clickfix_h($publicApprovedUrl); ?>">Copiar URL publica</button>
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
                  <button class="btn" type="submit">Aprobar y usar en publico</button>
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
                  <input type="text" name="scan_note" maxlength="500" placeholder="nota de revision (opcional)">
                  <button class="btn" type="submit">Guardar revision</button>
                </form>
                <form method="post" style="margin-top:8px" onsubmit="return confirm('Eliminar captura <?= clickfix_h($scanKind); ?> del scan #<?= $scanReportId; ?>?');">
                  <input type="hidden" name="action" value="scan_image_delete">
                  <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                  <input type="hidden" name="scan_report_id" value="<?= $scanReportId; ?>">
                  <input type="hidden" name="scan_kind" value="<?= clickfix_h($scanKind); ?>">
                  <input type="hidden" name="return_page" value="home">
                  <button class="btn" type="submit">Eliminar captura</button>
                </form>
                <form method="post" style="margin-top:8px">
                  <input type="hidden" name="action" value="scan_image_assign">
                  <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                  <input type="hidden" name="scan_report_id" value="<?= $scanReportId; ?>">
                  <input type="hidden" name="scan_source_kind" value="<?= clickfix_h($scanKind); ?>">
                  <input type="hidden" name="scan_target_kind" value="<?= $scanKind === 'before' ? 'after' : 'before'; ?>">
                  <input type="hidden" name="return_page" value="home">
                  <button class="btn" type="submit">Usar esta como <?= $scanKind === 'before' ? 'AFTER' : 'BEFORE'; ?></button>
                </form>
                <p class="mut" style="margin-top:6px">Cuando una captura queda en <b>approved</b>, se puede reutilizar en dashboard/index publico.</p>
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
      <p class="mut">Las define admin desde Investigacion. En la portada publica solo salen si tambien estan compartidas en publico.</p>
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
                $featuredSummary = 'Sin resumen todavia.';
            }
          ?>
          <article class="card" style="margin-top:12px;padding:14px;border:1px solid #5dc8ff22;background:#07111a">
            <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;align-items:flex-start">
              <div style="flex:1 1 320px;min-width:280px">
                <h3 style="margin:0 0 6px 0"><?= clickfix_h((string) ($featuredGraph['title'] ?? ('Investigacion #' . $featuredGraphId))); ?></h3>
                <p class="mono" style="margin:0 0 8px 0">graph #<?= $featuredGraphId; ?> | dominio: <?= clickfix_h($featuredDomain); ?> | verdict: <?= clickfix_h((string) ($featuredGraph['verdict'] ?? 'unknown')); ?> | report_id: <?= $featuredSourceReportId > 0 ? $featuredSourceReportId : '-'; ?> | posicion: <?= (int) ($featuredGraph['home_position'] ?? 0); ?></p>
                <div style="margin:0 0 10px 0;white-space:pre-line"><?= nl2br(clickfix_h(substr($featuredSummary, 0, 900))); ?></div>
                <div style="display:flex;gap:8px;flex-wrap:wrap">
                  <a class="btn" href="<?= clickfix_h(cfurl('intel', false, ['graph_id' => $featuredGraphId])); ?>">Abrir investigacion</a>
                  <?php if (!empty($featuredGraph['is_public']) && !empty($featuredGraph['share_token'])): ?>
                    <a class="btn" href="<?= clickfix_h('dashboard.php?page=investigation&share=' . urlencode((string) $featuredGraph['share_token'])); ?>" target="_blank" rel="noreferrer">Abrir version publica</a>
                  <?php else: ?>
                    <span class="mono mut">Aun no es publica.</span>
                  <?php endif; ?>
                </div>
              </div>
              <div style="flex:1 1 360px;min-width:280px">
                <div class="split">
                  <?php foreach (['before' => 'Antes', 'after' => 'Despues'] as $featuredKind => $featuredLabel): ?>
                    <div>
                      <h3 class="mono"><?= clickfix_h($featuredLabel); ?></h3>
                      <?php if (!empty($featuredAssets[$featuredKind])): ?>
                        <img src="<?= clickfix_h((string) $featuredAssets[$featuredKind]); ?>" alt="<?= clickfix_h($featuredLabel . ' investigacion'); ?>" loading="lazy" style="max-width:100%;border-radius:10px;border:1px solid #5dc8ff33">
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
          <h2>Mapa usuarios extension</h2>
          <p class="geo-subtitle">Puntos rojos por pais con total de usuarios de la extension.</p>
          <div id="home-extension-map" class="geo-map"></div>
          <div class="geo-map-legend">
            <span class="geo-chip"><b id="home-extension-total">0</b> usuarios geolocalizados</span>
            <span class="geo-chip"><b id="home-extension-countries">0</b> paises con actividad</span>
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
          <p class="geo-subtitle">Ubicacion aproximada de webs detectadas con IP, ISP, pais e idioma principal.</p>
          <div id="home-web-map" class="geo-map"></div>
          <div class="geo-map-legend">
            <span class="geo-chip"><b id="home-web-count">0</b> webs con coordenadas</span>
            <span class="geo-chip"><b id="home-web-last">-</b> ultima actualizacion</span>
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
        <section class="card">
          <h2>Exposicion del proyecto y cruces de infraestructura</h2>
          <p class="exposure-note">Senales inferidas a partir de visitas publicas al proyecto, referrers externos y solapamientos con dominios reportados o su infraestructura. Esto sirve como triage, no como atribucion definitiva.</p>
          <div class="analytics-kpi-grid exposure-kpi-grid">
            <div class="analytics-kpi"><div class="k">Hits publicos</div><div class="v"><?= (int) ($exposureSummary['hits_total'] ?? 0); ?></div></div>
            <div class="analytics-kpi"><div class="k">IPs unicas</div><div class="v"><?= (int) ($exposureSummary['unique_ips'] ?? 0); ?></div></div>
            <div class="analytics-kpi"><div class="k">Referrers externos</div><div class="v"><?= (int) ($exposureSummary['external_referrer_hits'] ?? 0); ?></div></div>
            <div class="analytics-kpi"><div class="k">Solapes referrer</div><div class="v"><?= (int) ($exposureSummary['referrer_overlap_hits'] ?? 0); ?></div></div>
            <div class="analytics-kpi"><div class="k">Solapes infra</div><div class="v"><?= (int) ($exposureSummary['infra_overlap_hits'] ?? 0); ?></div></div>
            <div class="analytics-kpi"><div class="k">Red sospechosa</div><div class="v"><?= (int) ($exposureSummary['suspicious_hits'] ?? 0); ?></div></div>
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
                  <thead><tr><th>Host</th><th>Hits</th><th>Reportes</th><th>Overlap</th><th>Ultimo</th></tr></thead>
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
          <h2>Eventos de exposicion investigables</h2>
          <?php if (empty($exposureEvents)): ?>
            <p class="mut">No hay cruces suficientes para mostrar eventos investigables.</p>
          <?php else: ?>
            <div class="analytics-table-wrap">
              <table class="compact-table">
                <thead><tr><th>Fecha</th><th>Path</th><th>IP</th><th>Ubicacion</th><th>Red</th><th>Referrer</th><th>Flags</th><th>Dominios relacionados</th></tr></thead>
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
        </section>
      <?php endif; ?>
      <section class="card">
        <h2>Graficos globales (14 dias)</h2>
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
            <p class="chart-title">Ratio de bloqueo por dia</p>
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
        <table>
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
              <thead><tr><th>Report</th><th>Fecha</th><th>Dominio</th><th>Tipo</th><th>Preview</th><th>Accion</th></tr></thead>
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
                        <button class="btn" type="submit">Aprobar y usar</button>
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
                        <button class="btn" type="submit">Aplicar</button>
                      </form>
                      <form method="post" style="margin-top:6px" onsubmit="return confirm('Eliminar captura <?= clickfix_h($pendingKind); ?> del report #<?= $pendingReportId; ?>?');">
                        <input type="hidden" name="action" value="scan_image_delete">
                        <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                        <input type="hidden" name="scan_report_id" value="<?= $pendingReportId; ?>">
                        <input type="hidden" name="scan_kind" value="<?= clickfix_h($pendingKind); ?>">
                        <input type="hidden" name="return_page" value="home">
                        <button class="btn" type="submit">Eliminar captura</button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </section>
      <?php endif; ?>
    <?php endif; ?>

    <?php if ($page === 'about'): ?>
      <section class="row">
        <article class="card">
          <h2><?= clickfix_h(cft('about_project_title')); ?></h2>
          <p><?= clickfix_h(cft('about_project_intro')); ?></p>
          <ul>
            <li><?= clickfix_h(cft('about_project_p1')); ?></li>
            <li><?= clickfix_h(cft('about_project_p2')); ?></li>
            <li><?= clickfix_h(cft('about_project_p3')); ?></li>
            <li><?= clickfix_h(cft('about_project_p4')); ?></li>
            <li><?= clickfix_h(cft('about_project_p5')); ?></li>
          </ul>
        </article>
        <article class="card">
          <h2><?= clickfix_h(cft('about_owner_title')); ?></h2>
          <p><b><?= clickfix_h($ownerName); ?></b></p>
          <p><?= clickfix_h(cft('about_owner_text')); ?></p>
          <p class="mut" style="margin-top:10px"><?= clickfix_h(cft('about_contact_links')); ?></p>
          <div class="tags" style="margin-top:8px">
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
        </article>
      </section>
    <?php elseif ($page === 'coverage'): ?>
      <section class="row">
        <article class="card">
          <h2>Fuentes de inteligencia</h2>
          <table>
            <thead><tr><th>Fuente</th><th>Cobertura</th><th>Uso</th></tr></thead>
            <tbody>
              <tr><td>Runtime Signals</td><td>ClickFix / command lures</td><td>Deteccion inline en extension</td></tr>
              <tr><td>Server Scoring</td><td>Correlacion de eventos</td><td>Veredicto backend por riesgo</td></tr>
              <tr><td>Listas de dominio</td><td>allow/block/alert/investigate</td><td>Contencion y excepciones</td></tr>
              <tr><td>Investigaciones</td><td>Grafo explicativo</td><td>Trazabilidad analista por caso</td></tr>
              <tr><td>YARA / reglas</td><td>Unsafe downloads</td><td>Prevencion pre-ejecucion</td></tr>
            </tbody>
          </table>
        </article>
        <article class="card">
          <h2>Cobertura de amenazas</h2>
          <ul>
            <li>Phishing operativo y secuestro de confianza.</li>
            <li>ClickFix y secuencias copiar-pegar-ejecutar.</li>
            <li>Descargas inseguras con reglas preventivas.</li>
            <li>Uso Shadow AI con riesgo de fuga de datos.</li>
          </ul>
          <p class="mut">La cobertura se enriquece con nuevas reglas, listas y feedback de analistas.</p>
        </article>
      </section>
    <?php elseif ($page === 'search'): ?>
      <section class="card">
        <h2>Busqueda forense</h2>
        <form method="get" class="split">
          <input type="hidden" name="public" value="1">
          <input type="hidden" name="page" value="search">
          <input name="q" maxlength="180" value="<?= clickfix_h($search); ?>" placeholder="texto libre">
          <input name="domain" maxlength="180" value="<?= clickfix_h($domainFilter); ?>" placeholder="dominio">
          <input name="command" maxlength="180" value="<?= clickfix_h($commandFilter); ?>" placeholder="comando / snippet">
          <input type="date" name="date_from" value="<?= clickfix_h($dateFromFilter); ?>">
          <input type="date" name="date_to" value="<?= clickfix_h($dateToFilter); ?>">
          <button class="btn" type="submit">Buscar</button>
        </form>
        <div style="margin-top:10px">
          <?php
            $searchDetailBase = [
                'q' => $search,
                'domain' => $domainFilter,
                'command' => $commandFilter,
                'date_from' => $dateFromFilter,
                'date_to' => $dateToFilter,
            ];
          ?>
          <table>
            <thead><tr><th>Fecha</th><th>Dominio</th><th>Mensaje</th><th>Score</th><th>Detalle</th></tr></thead>
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
                  <td><?= clickfix_h((string) ($fr['message'] ?? '')); ?></td>
                  <td class="mono"><?= isset($fr['score_total']) ? (int) $fr['score_total'] : 0; ?></td>
                  <td class="mono"><a class="event-related-link" href="<?= clickfix_h($detailUrl); ?>">Ver detalle</a></td>
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
      <section class="row">
        <article class="card">
          <h2>Acceso y login</h2>
          <form method="post"><input type="hidden" name="action" value="request_access"><input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>"><input type="url" name="access_linkedin" required placeholder="https://www.linkedin.com/in/..."><input type="url" name="company_website" placeholder="https://company.com"><input type="email" name="access_email" required placeholder="email"><select name="request_lang"><option value="en" selected>en</option><option value="es">es</option><option value="ca">ca</option><option value="fr">fr</option><option value="de">de</option></select><button class="btn" type="submit">Solicitar acceso</button></form>
          <hr>
          <form method="post"><input type="hidden" name="action" value="login"><input type="text" name="username" required placeholder="usuario"><input type="password" name="password" required placeholder="password"><button class="btn" type="submit">Entrar</button></form>
          <?php if ($loggedIn): ?><form method="post" style="margin-top:8px"><input type="hidden" name="action" value="logout"><button class="btn" type="submit">Cerrar sesion</button></form><?php endif; ?>
        </article>
        <article class="card">
          <h2>Desistimiento</h2>
          <form method="post"><input type="hidden" name="action" value="submit_appeal"><input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>"><input type="text" name="appeal_domain" required placeholder="dominio"><input type="text" name="appeal_contact" placeholder="contacto opcional"><textarea name="appeal_reason" required placeholder="motivo"></textarea><button class="btn" type="submit">Enviar desistimiento</button></form>
        </article>
        <?php if ($loggedIn): ?>
          <article class="card">
            <h2>Mi cuenta</h2>
            <p class="mut">Los ajustes de cuenta estan separados para mantener el acceso limpio y rapido.</p>
            <div class="rbac">
              <div class="item"><b>Usuario</b><span class="mono"><a class="user-link" href="<?= clickfix_h(cfprofileurl((int) ($user['id'] ?? 0), [], false)); ?>"><?= clickfix_h((string) ($user['username'] ?? '')); ?></a></span></div>
              <div class="item"><b>Email</b><span class="mono"><?= clickfix_h((string) ($user['email'] ?? '-')); ?></span></div>
              <div class="item"><b>Rol</b><span><?= clickfix_h((string) ($user['role_label'] ?? '-')); ?></span></div>
              <div class="item"><b>REP</b><span class="mono"><?= (int) ($user['reputation'] ?? 0); ?></span></div>
            </div>
            <hr>
            <div class="split">
              <a class="btn" href="<?= clickfix_h(cfprofileurl((int) ($user['id'] ?? 0), [], false)); ?>">Abrir perfil</a>
              <a class="btn" href="<?= clickfix_h(cfurl('settings')); ?>">Abrir settings</a>
            </div>
          </article>
        <?php endif; ?>
      </section>
    <?php elseif ($page === 'profile'): ?>
      <section class="card">
        <?php if ($profileUser === null): ?>
          <h2>Perfil no encontrado</h2>
          <p class="mut">No existe el usuario solicitado o no hay datos disponibles.</p>
        <?php else: ?>
          <?php
            $avatarSeed = strtoupper(substr((string) ($profileUser['username'] ?? 'U'), 0, 1));
            $profileAvatarUrl = (string) ($profileUser['profile_avatar_url'] ?? '');
            $tabInvestigationUrl = cfprofileurl((int) ($profileUser['id'] ?? 0), ['tab' => 'investigations']);
            $tabReportsUrl = cfprofileurl((int) ($profileUser['id'] ?? 0), ['tab' => 'reports']);
            $tabSessionsUrl = cfprofileurl((int) ($profileUser['id'] ?? 0), ['tab' => 'sessions']);
          ?>
          <div class="profile-shell">
            <aside class="profile-card">
              <div class="profile-avatar<?= $profileAvatarUrl !== '' ? ' has-image' : ''; ?>">
                <?php if ($profileAvatarUrl !== ''): ?>
                  <img src="<?= clickfix_h($profileAvatarUrl); ?>" alt="avatar">
                <?php else: ?>
                  <?= clickfix_h($avatarSeed); ?>
                <?php endif; ?>
              </div>
              <h3 class="profile-name"><?= clickfix_h((string) ($profileUser['display_name'] ?? '')); ?></h3>
              <div class="profile-nick">@<?= clickfix_h((string) ($profileUser['username'] ?? '')); ?></div>
              <div class="profile-meta">
                <div class="rowx"><span>Rol</span><b><?= clickfix_h((string) ($profileUser['role_label'] ?? '-')); ?></b></div>
                <div class="rowx"><span>REP</span><b class="mono"><?= (int) ($profileUser['reputation'] ?? 0); ?></b></div>
                <div class="rowx"><span>Idioma</span><b class="mono"><?= clickfix_h((string) ($profileUser['preferred_lang'] ?? 'en')); ?></b></div>
                <div class="rowx"><span>Theme</span><b class="mono"><?= clickfix_h((string) ($profileUser['profile_theme'] ?? 'default')); ?></b></div>
                <div class="rowx"><span>Email</span><b class="mono"><?= clickfix_h((string) ($profileUser['email_visible'] !== '' ? $profileUser['email_visible'] : 'private')); ?></b></div>
                <div class="rowx"><span>Threat.rip</span><b class="mono"><?php if (!empty($profileUser['account_threatrip']['visible'])): ?><a class="user-link" target="_blank" rel="noreferrer" href="<?= clickfix_h((string) ($profileUser['account_threatrip']['url'] ?? '')); ?>">#<?= clickfix_h((string) ($profileUser['account_threatrip']['id'] ?? '')); ?></a><?php else: ?>private<?php endif; ?></b></div>
                <div class="rowx"><span>VirusTotal</span><b class="mono"><?php if (!empty($profileUser['account_virustotal']['visible'])): ?><a class="user-link" target="_blank" rel="noreferrer" href="<?= clickfix_h((string) ($profileUser['account_virustotal']['url'] ?? '')); ?>"><?= clickfix_h((string) ($profileUser['account_virustotal']['handle'] ?? '')); ?></a><?php else: ?>private<?php endif; ?></b></div>
                <div class="rowx"><span>AbuseIPDB</span><b class="mono"><?php if (!empty($profileUser['account_abuseipdb']['visible'])): ?><a class="user-link" target="_blank" rel="noreferrer" href="<?= clickfix_h((string) ($profileUser['account_abuseipdb']['url'] ?? '')); ?>">#<?= clickfix_h((string) ($profileUser['account_abuseipdb']['id'] ?? '')); ?></a><?php else: ?>private<?php endif; ?></b></div>
                <div class="rowx"><span>GitHub</span><b class="mono"><?php if (!empty($profileUser['account_github']['visible'])): ?><a class="user-link" target="_blank" rel="noreferrer" href="<?= clickfix_h((string) ($profileUser['account_github']['url'] ?? '')); ?>"><?= clickfix_h((string) ($profileUser['account_github']['handle'] ?? '')); ?></a><?php else: ?>private<?php endif; ?></b></div>
              </div>
            </aside>
            <section>
              <div class="profile-tabs">
                <a class="profile-tab<?= $profileTab === 'investigations' ? ' active' : ''; ?>" href="<?= clickfix_h($tabInvestigationUrl); ?>">Investigaciones (<?= count($profileInvestigations); ?>)</a>
                <a class="profile-tab<?= $profileTab === 'reports' ? ' active' : ''; ?>" href="<?= clickfix_h($tabReportsUrl); ?>">Reportes (<?= count($profileReports); ?>)</a>
                <a class="profile-tab<?= $profileTab === 'sessions' ? ' active' : ''; ?>" href="<?= clickfix_h($tabSessionsUrl); ?>">Sesiones (<?= count($profileSessionHistory); ?>)</a>
                <?php if ($profileCanEdit): ?>
                  <a class="profile-tab" href="<?= clickfix_h(cfurl('settings')); ?>">Ir a settings</a>
                <?php endif; ?>
              </div>
              <?php if ($profileCanEdit): ?>
                <article class="profile-card" style="margin-bottom:10px">
                  <h3 style="margin-top:0">Editar perfil</h3>
                  <p class="mut">Controla que informacion de contacto/cuentas se muestra publicamente. El tema, idioma, foto y contraseña se gestionan en Settings.</p>
                  <form method="post">
                    <input type="hidden" name="action" value="user_self_profile_update">
                    <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                    <input type="text" name="full_name" maxlength="120" value="<?= clickfix_h((string) ($profileUser['full_name'] ?? '')); ?>" placeholder="Nombre visible">
                    <label><input type="hidden" name="profile_email_public" value="0"><input type="checkbox" name="profile_email_public" value="1"<?= !empty($profileUser['email_is_public']) ? ' checked' : ''; ?>> Mostrar email</label>
                    <div class="split">
                      <div>
                        <input type="text" name="profile_threatrip_id" value="<?= clickfix_h((string) ($profileUser['account_threatrip']['id'] ?? '')); ?>" placeholder="Threat.rip user ID">
                        <label><input type="hidden" name="profile_threatrip_public" value="0"><input type="checkbox" name="profile_threatrip_public" value="1"<?= !empty($profileUser['account_threatrip']['is_public']) ? ' checked' : ''; ?>> Publico</label>
                      </div>
                      <div>
                        <input type="text" name="profile_vt_handle" value="<?= clickfix_h((string) ($profileUser['account_virustotal']['handle'] ?? '')); ?>" placeholder="VirusTotal handle">
                        <label><input type="hidden" name="profile_vt_public" value="0"><input type="checkbox" name="profile_vt_public" value="1"<?= !empty($profileUser['account_virustotal']['is_public']) ? ' checked' : ''; ?>> Publico</label>
                      </div>
                      <div>
                        <input type="text" name="profile_abuseipdb_id" value="<?= clickfix_h((string) ($profileUser['account_abuseipdb']['id'] ?? '')); ?>" placeholder="AbuseIPDB user ID">
                        <label><input type="hidden" name="profile_abuseipdb_public" value="0"><input type="checkbox" name="profile_abuseipdb_public" value="1"<?= !empty($profileUser['account_abuseipdb']['is_public']) ? ' checked' : ''; ?>> Publico</label>
                      </div>
                    </div>
                    <input type="text" name="profile_github_handle" value="<?= clickfix_h((string) ($profileUser['account_github']['handle'] ?? '')); ?>" placeholder="GitHub handle">
                    <label><input type="hidden" name="profile_github_public" value="0"><input type="checkbox" name="profile_github_public" value="1"<?= !empty($profileUser['account_github']['is_public']) ? ' checked' : ''; ?>> GitHub publico</label>
                    <button class="btn" type="submit">Guardar perfil</button>
                  </form>
                </article>
              <?php endif; ?>
              <article class="card">
                <?php if ($profileTab === 'sessions'): ?>
                  <h2>Historial de sesiones del usuario</h2>
                  <?php if (!$profileCanViewPrivate): ?>
                    <p class="mut">El historial de sesiones es privado.</p>
                  <?php else: ?>
                    <table>
                      <thead><tr><th>Fecha</th><th>Evento</th><th>IP</th><th>Sesion</th><th>User-Agent</th></tr></thead>
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
                            <td><?= clickfix_h((string) ($sessionEvent['user_agent'] ?? '')); ?></td>
                          </tr>
                        <?php endforeach; ?>
                        <?php if (empty($profileSessionHistory)): ?><tr><td colspan="5" class="mut">Sin sesiones registradas.</td></tr><?php endif; ?>
                      </tbody>
                    </table>
                  <?php endif; ?>
                <?php elseif ($profileTab === 'reports'): ?>
                  <h2>Reportes del usuario</h2>
                  <?php if (!$profileCanViewPrivate): ?>
                    <p class="mut">Los reportes del usuario son privados.</p>
                  <?php endif; ?>
                  <table>
                    <thead><tr><th>Fecha</th><th>Dominio</th><th>Mensaje</th><th>Estado</th><th>Accion</th></tr></thead>
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
                          <td><?= clickfix_h((string) ($pr['message'] ?? '')); ?></td>
                          <td class="mono"><?= clickfix_h((string) ($pr['review_status'] ?? 'pending')); ?></td>
                          <td class="mono"><?= clickfix_h($actionLabel); ?></td>
                        </tr>
                      <?php endforeach; ?>
                      <?php if (empty($profileReports)): ?><tr><td colspan="5" class="mut">Sin reportes asociados.</td></tr><?php endif; ?>
                    </tbody>
                  </table>
                <?php else: ?>
                  <h2>Investigaciones del usuario</h2>
                  <table>
                    <thead><tr><th>Actualizada</th><th>Titulo</th><th>Dominio</th><th>Veredicto</th><th>Estado</th></tr></thead>
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
                <?php endif; ?>
              </article>
            </section>
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
      <section class="row">
        <article class="card">
          <h2>Settings de cuenta</h2>
          <p class="mut">Gestiona idioma, theme y foto de usuario para tu cuenta.</p>
          <form method="post">
            <input type="hidden" name="action" value="user_self_update_account">
            <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
            <label for="self-lang">Idioma por defecto</label>
            <select id="self-lang" name="self_lang">
              <option value="en"<?= $selfLang === 'en' ? ' selected' : ''; ?>>en</option>
              <option value="es"<?= $selfLang === 'es' ? ' selected' : ''; ?>>es</option>
              <option value="ca"<?= $selfLang === 'ca' ? ' selected' : ''; ?>>ca</option>
              <option value="de"<?= $selfLang === 'de' ? ' selected' : ''; ?>>de</option>
              <option value="fr"<?= $selfLang === 'fr' ? ' selected' : ''; ?>>fr</option>
            </select>
            <label for="self-theme">Theme</label>
            <select id="self-theme" name="self_theme">
              <option value="default"<?= $selfTheme === 'default' ? ' selected' : ''; ?>>default</option>
              <option value="teal"<?= $selfTheme === 'teal' ? ' selected' : ''; ?>>teal</option>
              <option value="sunset"<?= $selfTheme === 'sunset' ? ' selected' : ''; ?>>sunset</option>
              <option value="mono"<?= $selfTheme === 'mono' ? ' selected' : ''; ?>>mono</option>
            </select>
            <label for="self-avatar-url">Foto de usuario (URL)</label>
            <input type="url" id="self-avatar-url" name="self_avatar_url" maxlength="420" value="<?= clickfix_h($selfAvatarUrl); ?>" placeholder="https://example.com/avatar.png">
            <button class="btn" type="submit">Guardar ajustes</button>
          </form>
        </article>
        <article class="card">
          <h2>Seguridad</h2>
          <p class="mut">Cambio de contraseña para tu cuenta.</p>
          <form method="post">
            <input type="hidden" name="action" value="user_self_change_password">
            <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
            <input type="password" name="self_current_password" required placeholder="Contraseña actual">
            <input type="password" name="self_new_password" minlength="10" required placeholder="Nueva contraseña (min 10 chars)">
            <button class="btn" type="submit">Cambiar contraseña</button>
          </form>
        </article>
      </section>
      <?php if ($showApiUi): ?>
      <section class="card">
        <h2>APIs de investigacion y plataforma</h2>
        <p class="mut">Las API keys son por usuario: solo tu puedes verlas, cambiarlas y usarlas.</p>
        <table>
          <thead>
            <tr>
              <th>Proveedor</th>
              <th>API key</th>
              <th><?= clickfix_h(cft('intel_api_key_masked')); ?></th>
              <th><?= clickfix_h(cft('intel_api_key_updated')); ?></th>
              <th>Accion</th>
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
                      name="api_key"
                      value="<?= clickfix_h($providerMasked); ?>"
                      data-api-key-masked="<?= clickfix_h($providerMasked); ?>"
                      data-api-key-plain="<?= clickfix_h($providerPlain); ?>"
                      data-api-key-revealed="0"
                      maxlength="600"
                      autocomplete="off"
                      placeholder="API key"
                    >
                    <button type="button" class="api-key-toggle" data-toggle-api-key="<?= clickfix_h($providerInputId); ?>">ver</button>
                    <button class="btn" type="submit"><?= clickfix_h(cft('intel_api_key_save')); ?></button>
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
                      <button type="submit"><?= clickfix_h(cft('intel_api_key_delete')); ?></button>
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
            <div class="mut">Copiala ahora y guardala en un vault seguro.</div>
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
              <input type="text" name="platform_api_label" maxlength="80" placeholder="opencti-main">
            </div>
            <div>
              <label>Expira en dias (1-365)</label>
              <input type="number" name="platform_api_expires_days" min="1" max="365" value="90">
            </div>
            <div>
              <label>Rate limit RPM (30-2000)</label>
              <input type="number" name="platform_api_max_rpm" min="30" max="2000" value="120">
            </div>
          </div>
          <div class="intel-toolbar">
            <button class="btn" type="submit">Generar API key segura</button>
          </div>
        </form>

        <div style="margin-top:10px;overflow:auto">
          <table>
            <thead>
              <tr>
                <th>ID</th>
                <th>Etiqueta</th>
                <th>Prefijo</th>
                <th>Scopes</th>
                <th>RPM</th>
                <th>Ultimo uso</th>
                <th>Expira</th>
                <th>Estado</th>
                <th>Accion</th>
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
                          <button type="submit">Revocar</button>
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
      </section>
      <section class="card">
        <h2>Consulta IOC (con tus APIs)</h2>
        <form method="post">
          <input type="hidden" name="action" value="investigation_api_lookup">
          <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
          <input type="hidden" name="return_page" value="settings">
          <input type="hidden" name="graph_id" value="0">
          <div class="intel-grid">
            <div>
              <label>Proveedor</label>
              <select name="provider">
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
              <input type="text" name="lookup_target" maxlength="500" value="<?= clickfix_h($settingsLookupTargetDefault); ?>" placeholder="example.com | 1.2.3.4 | https://example.com/path">
            </div>
          </div>
          <div class="intel-toolbar">
            <button class="btn" type="submit">Consultar</button>
          </div>
        </form>
        <?php if (is_array($intelApiLookupResult)): ?>
          <div class="api-result">
            <b>Ultimo resultado</b>
            <div class="mono">provider: <?= clickfix_h((string) ($intelApiLookupResult['provider'] ?? '-')); ?> | status: <?= (int) ($intelApiLookupResult['status'] ?? 0); ?> | target: <?= clickfix_h((string) ($intelApiLookupResult['target'] ?? '')); ?> | at: <?= clickfix_h((string) ($intelApiLookupResult['captured_at'] ?? '')); ?></div>
            <?php if (!empty($intelApiLookupResult['error'])): ?>
              <div class="mut">error: <?= clickfix_h((string) ($intelApiLookupResult['error'] ?? '')); ?></div>
            <?php endif; ?>
            <pre><?= clickfix_h((string) ($intelApiLookupResult['response_json'] ?? '')); ?></pre>
          </div>
        <?php endif; ?>
        <?php if (!empty($intelApiLookupHistory)): ?>
          <h3>Historial reciente</h3>
          <table>
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
      </section>
      <?php else: ?>
      <section class="card">
        <h2>Integraciones de inteligencia</h2>
        <p class="mut">
          La gestion de credenciales e integraciones avanzadas esta oculta en esta interfaz.
          Los proveedores (por ejemplo VirusTotal, AbuseIPDB y URLScan) siguen operativos desde backend.
        </p>
      </section>
      <?php endif; ?>
    <?php elseif ($page === 'investigation'): ?>
      <section class="intel-public">
        <?php if ($sharedGraph === null): ?>
          <article class="card"><h2>Investigacion no disponible</h2><p>El enlace compartido no existe o ha sido desactivado.</p></article>
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
          <div class="intel-shell">
          <article class="intel-topbar">
            <div class="intel-topline">
              <div class="intel-title-wrap">
                <h2><?= clickfix_h((string) ($sharedGraph['title'] ?? 'Investigacion')); ?></h2>
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
              <h4>MITRE ATT&CK Blueprint (publico)</h4>
              <div class="mitre-blueprint-grid" id="shared-mitre-grid"></div>
              <div class="mitre-empty" id="shared-mitre-empty" hidden>Sin TTPs detectadas para esta investigacion.</div>
            </div>
          </article>
          <article class="card">
            <h2>Grafo de investigacion</h2>
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
        $intelFocusLabel = $activeGraphId > 0 ? ('Investigacion #' . $activeGraphId) : 'Nueva investigacion';
        $intelFocusDomain = trim((string) ($selectedInvestigation['site_domain'] ?? ''));
      ?>
      <section class="card intel-shell">
        <?php if (!$intelWorkspaceActive): ?>
          <div class="intel-selector-shell intel-selector-v2" data-intel-focus="1">
            <div class="intel-focus-hero">
              <div class="intel-focus-copy">
                <div class="intel-focus-eyebrow">Workspace de investigacion</div>
                <h3>Selecciona el foco antes de investigar</h3>
                <p class="mut">Primero elige una investigacion existente, una alerta para abrir un caso nuevo o un lienzo vacio. Hasta que no escojas foco no se cargan datos de otras investigaciones dentro del workspace.</p>
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
                  <input id="intel-focus-search" type="search" placeholder="Dominio, titulo, alerta, veredicto..." autocomplete="off">
                </label>
              </div>
            </div>
            <div class="intel-focus-tabs" role="tablist" aria-label="Seleccion de foco">
              <button type="button" class="intel-tab is-active" data-intel-tab="investigations" aria-selected="true">Continuar investigacion</button>
              <button type="button" class="intel-tab" data-intel-tab="alerts" aria-selected="false">Crear desde alerta</button>
              <button type="button" class="intel-tab" data-intel-tab="new" aria-selected="false">Empezar desde cero</button>
            </div>
            <div class="intel-focus-panels">
              <section class="intel-focus-panel" data-intel-panel="investigations">
                <div class="intel-panel-head">
                  <div>
                    <h4>Continuar investigacion</h4>
                    <p class="mut">Retoma un caso existente y entra directamente en su workspace aislado.</p>
                  </div>
                  <div class="intel-panel-chip">Mostrando <?= min(12, count($investigations)); ?> recientes</div>
                </div>
                <div class="intel-focus-list">
                  <?php if (!empty($investigations)): ?>
                    <?php foreach (array_slice($investigations, 0, 12) as $graphRow): ?>
                      <?php
                        $graphRowId = (int) ($graphRow['id'] ?? 0);
                        $graphTitle = (string) ($graphRow['title'] ?? 'Investigacion');
                        $graphDomain = (string) ($graphRow['site_domain'] ?? '-');
                        $graphVerdict = (string) ($graphRow['verdict'] ?? 'unknown');
                        $graphWorkflow = (string) ($graphRow['workflow_status'] ?? 'draft');
                        $graphSummary = (string) ($graphRow['summary'] ?? 'Sin resumen todavia.');
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
                    <div class="intel-empty-state">No tienes investigaciones guardadas todavia.</div>
                  <?php endif; ?>
                </div>
              </section>
              <section class="intel-focus-panel" data-intel-panel="alerts" hidden>
                <div class="intel-panel-head">
                  <div>
                    <h4>Crear desde alerta</h4>
                    <p class="mut">Convierte una alerta reciente en una investigacion con contexto inicial y grafo base.</p>
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
                            <button class="btn" type="submit">Crear caso</button>
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
                    <p class="mut">Abre un lienzo vacio cuando quieras modelar una investigacion sin depender de una alerta previa.</p>
                  </div>
                </div>
                <article class="intel-focus-card hero">
                  <div class="intel-focus-main">
                    <strong>Investigacion vacia</strong>
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
          <div class="intel-topbar">
            <div class="intel-topline">
              <div class="intel-title-wrap">
                <h2>Investigaciones de sitios</h2>
                <p class="mut">Workspace de analisis centrado en un unico caso, entidades, relaciones y evidencia trazable.</p>
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
            </div>
            <div class="intel-focus-actions">
              <a class="btn" href="<?= clickfix_h(cfurl('intel')); ?>">Cambiar foco</a>
              <button class="btn" type="submit" form="intel-save-form">Guardar</button>
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
              <p class="mut"><?= clickfix_h((string) ($selectedInvestigation['title'] ?? 'Sin titulo')); ?></p>
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
              <div class="k">Acciones rapidas</div>
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
                  <p class="mut">No se encontro la alerta origen o no hay permisos para verla.</p>
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
                      <div class="event-kv"><b>Extension (manual report)</b><span><?= clickfix_h((string) ($sourceEvent['extension_version'] ?? '-')); ?></span></div>
                      <div class="event-kv"><b>Dominio ya bloqueado</b><span>
                        <?= !empty($sourceEvent['host_blocked_before'])
                          ? ('SI (' . (int) ($sourceEvent['host_blocked_count'] ?? 0) . ' bloqueos / ' . (int) ($sourceEvent['host_total_count'] ?? 0) . ' reportes' . (!empty($sourceEvent['host_last_blocked_at']) ? ', ultimo ' . (string) $sourceEvent['host_last_blocked_at'] : '') . ')')
                          : ('No (' . (int) ($sourceEvent['host_total_count'] ?? 0) . ' reportes)'); ?>
                      </span></div>
                      <?php if ($canSrViewer): ?>
                        <div class="event-kv"><b>IP ya bloqueada</b><span>
                          <?= !empty($sourceEvent['ip_blocked_before'])
                            ? ('SI (' . (int) ($sourceEvent['ip_blocked_count'] ?? 0) . ' bloqueos / ' . (int) ($sourceEvent['ip_total_count'] ?? 0) . ' reportes' . (!empty($sourceEvent['ip_last_blocked_at']) ? ', ultimo ' . (string) $sourceEvent['ip_last_blocked_at'] : '') . ')')
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
                  <button class="btn" type="submit"><?= clickfix_h(cft('intel_briefing_save')); ?></button>
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
                        <p class="intel-workbench-note">El batch se limita a 15 IOCs reutilizables por ejecucion y solo usa proveedores compatibles con cada tipo. Los resultados se guardan tambien en tu historial de enrichment.</p>
                        <div class="intel-toolbar" style="margin-top:auto">
                          <button class="btn" type="submit">Procesar intake</button>
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
                  <?php if (clickfix_user_has_min_role($actor, 'analyst_jr')): ?>
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
                          <button class="btn" type="submit"><?= clickfix_h(cft('intel_manual_ioc_button')); ?></button>
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
                              <button class="btn" type="submit">VT</button>
                            </form>
                            <?php if ($abuseAllowed): ?>
                              <form method="post" style="display:inline-flex;gap:6px;align-items:center">
                                <input type="hidden" name="action" value="investigation_api_lookup">
                                <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                                <input type="hidden" name="graph_id" value="<?= $activeGraphId; ?>">
                                <input type="hidden" name="provider" value="abuseipdb">
                                <input type="hidden" name="lookup_target" value="<?= clickfix_h($iocValue); ?>">
                                <button class="btn" type="submit">AbuseIPDB</button>
                              </form>
                            <?php endif; ?>
                            <?php if ($iocType === 'url' || $iocType === 'domain'): ?>
                              <form method="post" style="display:inline-flex;gap:6px;align-items:center">
                                <input type="hidden" name="action" value="investigation_api_lookup">
                                <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                                <input type="hidden" name="graph_id" value="<?= $activeGraphId; ?>">
                                <input type="hidden" name="provider" value="urlscan">
                                <input type="hidden" name="lookup_target" value="<?= clickfix_h($iocValue); ?>">
                                <button class="btn" type="submit">urlscan</button>
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
                        <select name="provider">
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
                        <input type="text" name="lookup_target" maxlength="500" value="<?= clickfix_h($lookupTargetDefault); ?>" placeholder="example.com | 1.2.3.4 | https://example.com/path">
                      </div>
                    </div>
                    <div class="intel-toolbar">
                      <button class="btn" type="submit"><?= clickfix_h(cft('intel_api_lookup_button')); ?></button>
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
                    <div class="mut">Sin historial todavia para esta investigacion.</div>
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
                    <button type="button" class="btn" id="vt-reported-generate">Generar graficos VT</button>
                  </div>
                  <p class="mut" style="margin:0">Cruza webs reportadas en plataforma con el ultimo lookup guardado de VirusTotal por dominio.</p>
                  <div id="vt-reported-panel" hidden>
                    <div class="vt-reported-kpis">
                      <div class="vt-reported-kpi"><div class="k">reportadas</div><div class="v"><?= (int) ($vtStats['reported_domains_total'] ?? 0); ?></div></div>
                      <div class="vt-reported-kpi"><div class="k">con VT</div><div class="v"><?= (int) ($vtStats['domains_with_vt'] ?? 0); ?></div></div>
                      <div class="vt-reported-kpi"><div class="k">sin VT</div><div class="v"><?= (int) ($vtStats['domains_without_vt'] ?? 0); ?></div></div>
                      <div class="vt-reported-kpi"><div class="k">detectadas</div><div class="v"><?= (int) ($vtStats['detected_any'] ?? 0); ?></div></div>
                    </div>
                    <div class="chart-stack">
                      <div class="chart-card">
                        <p class="chart-title">Clasificacion por dominio reportado</p>
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
                        <thead><tr><th>Dominio</th><th>Veredicto VT</th><th>Mal</th><th>Sus</th><th>Har</th><th>Und</th><th>Motores</th><th>Ultimo lookup</th></tr></thead>
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
                  <span class="mono mut" id="intel-api-map-meta">Sin consultas recientes de proveedores para esta investigacion.</span>
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
                        <span>Siguen visibles tambien en pantalla completa.</span>
                      </div>
                      <span class="map-stat" id="intel-dock-zoom-status">zoom 100%</span>
                    </div>
                    <div class="intel-canvas-dock-actions">
                      <a class="btn" href="<?= clickfix_h(cfurl('intel')); ?>">Cambiar foco</a>
                      <button class="btn" type="submit" form="intel-save-form">Guardar</button>
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
                    <div><b>Enlace publico:</b> <a href="<?= clickfix_h($shareUrl); ?>" target="_blank" rel="noreferrer"><?= clickfix_h($shareUrl); ?></a></div>
                  <?php endif; ?>
                  <div class="intel-toolbar">
                    <form method="post" style="display:flex;gap:8px;align-items:center;width:auto;">
                      <input type="hidden" name="action" value="investigation_submit_community">
                      <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                      <input type="hidden" name="graph_id" value="<?= $activeGraphId; ?>">
                      <button class="btn" type="submit">
                        <?= !empty($selectedInvestigation['submitted_to_community']) ? 'Reenviar a Community' : 'Enviar a Community'; ?>
                      </button>
                    </form>
                    <form method="post" style="display:flex;gap:8px;align-items:center;width:auto;">
                      <input type="hidden" name="action" value="investigation_share">
                      <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                      <input type="hidden" name="graph_id" value="<?= $activeGraphId; ?>">
                      <input type="hidden" name="share_mode" value="on">
                      <button class="btn" type="submit">Compartir publico</button>
                    </form>
                    <form method="post" style="display:flex;gap:8px;align-items:center;width:auto;">
                      <input type="hidden" name="action" value="investigation_share">
                      <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                      <input type="hidden" name="graph_id" value="<?= $activeGraphId; ?>">
                      <input type="hidden" name="share_mode" value="off">
                      <button type="submit">Quitar comparticion</button>
                    </form>
                    <form method="post" style="display:flex;gap:8px;align-items:center;width:auto;" onsubmit="return confirm('Eliminar investigacion?');">
                      <input type="hidden" name="action" value="investigation_delete">
                      <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                      <input type="hidden" name="graph_id" value="<?= $activeGraphId; ?>">
                      <button type="submit">Eliminar investigacion</button>
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
                        <input type="number" name="home_position" min="0" max="9999" value="<?= (int) ($selectedInvestigation['home_position'] ?? 0); ?>" placeholder="posicion" style="width:96px">
                        <input type="number" name="source_report_id" min="0" value="<?= $selectedInvestigationSourceReportId; ?>" placeholder="report_id capturas" style="width:148px">
                        <button type="submit">Guardar Inicio</button>
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
                    <div class="mut mono" style="margin-top:8px">Inicio reutiliza las capturas aprobadas del `source_report_id`. Si la investigacion no esta compartida en publico, solo se vera en el dashboard interno.</div>
                  <?php endif; ?>
                </div>
              </div>
            <?php endif; ?>

            <?php if ($activeGraphId > 0): ?>
              <div class="intel-timeline" id="intel-section-timeline" data-intel-section="intel-section-timeline">
                <div class="intel-section-head">
                  <div>
                    <span class="intel-section-kicker">Timeline</span>
                    <h3>Timeline de investigacion (que y cuando)</h3>
                    <p class="mut">Seguimiento cronologico de cambios, revisiones y decisiones sobre este caso.</p>
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
                  <h4>MITRE ATT&CK Blueprint (investigacion)</h4>
                  <div class="mitre-blueprint-grid" id="investigation-mitre-grid"></div>
                  <div class="mitre-empty" id="investigation-mitre-empty" hidden>Sin TTPs detectadas para esta investigacion.</div>
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
      ?>
      <section class="card intel-shell">
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
              <span>Votacion, verificacion y trazabilidad por rol.</span>
            </div>
            <?php if (empty($communityInvestigations)): ?>
              <div class="intel-item active">
                <b>Sin investigaciones en Community</b>
                <div class="summary">Envia una investigacion desde el modulo Investigacion.</div>
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
                <b><?= clickfix_h((string) ($communityRow['title'] ?? 'Investigacion')); ?></b>
                <div class="meta mono"><?= clickfix_h((string) ($communityRow['site_domain'] ?? '-')); ?></div>
                <div class="meta mono"><?= clickfix_h(cfworkflowlabel($communityWorkflow, $lang)); ?> | <?= clickfix_h(clickfix_role_label((string) ($communityRow['community_origin_role'] ?? 'analyst_jr'))); ?></div>
                <div class="meta mono"><?= $classificationSymbol; ?> | score <?= (int) ($communityRow['vote_score'] ?? 0); ?> | +<?= (int) ($communityRow['upvotes'] ?? 0); ?> / -<?= (int) ($communityRow['downvotes'] ?? 0); ?></div>
                <div class="meta mono">author REP: <?= (int) ($communityRow['author_reputation'] ?? 0); ?> | <a class="user-link" href="<?= clickfix_h(cfprofileurl((int) ($communityRow['user_id'] ?? 0))); ?>"><?= clickfix_h((string) ($communityRow['username'] ?? '')); ?></a></div>
              </a>
            <?php endforeach; ?>
          </aside>
          <section class="intel-editor">
            <?php if ($selectedCommunityInvestigation === null): ?>
              <div class="event-empty">Selecciona una investigacion de la lista para revisar.</div>
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
                  <div><label>Titulo</label><div class="mono"><?= clickfix_h((string) ($selectedCommunityInvestigation['title'] ?? '')); ?></div></div>
                  <div><label>Dominio principal</label><div class="mono"><?= clickfix_h((string) ($selectedCommunityInvestigation['site_domain'] ?? '')); ?></div></div>
                  <div><label>Veredicto</label><div class="mono"><?= clickfix_h((string) ($selectedCommunityInvestigation['verdict'] ?? 'unknown')); ?></div></div>
                  <div><label>Estado workflow</label><div class="mono"><?= clickfix_h(cfworkflowlabel($communityStatus, $lang)); ?></div></div>
                  <div><label>Origen</label><div class="mono"><?= clickfix_h(clickfix_role_label((string) ($selectedCommunityInvestigation['community_origin_role'] ?? 'analyst_jr'))); ?></div></div>
                  <div><label>Autor</label><div class="mono"><a class="user-link" href="<?= clickfix_h(cfprofileurl((int) ($selectedCommunityInvestigation['user_id'] ?? 0))); ?>"><?= clickfix_h((string) ($selectedCommunityInvestigation['username'] ?? '')); ?></a> | REP <?= (int) ($selectedCommunityInvestigation['author_reputation'] ?? $selectedCommunityInvestigation['reputation'] ?? 0); ?></div></div>
                  <div><label>Malware scoring</label><div class="mono"><?= $classSymbol; ?> <?= clickfix_h($classLabel); ?> | score <?= (int) ($selectedCommunityVote['score'] ?? 0); ?> | +<?= (int) ($selectedCommunityVote['upvotes'] ?? 0); ?> / -<?= (int) ($selectedCommunityVote['downvotes'] ?? 0); ?></div></div>
                  <div><label>Actualizado</label><div class="mono"><?= clickfix_h((string) ($selectedCommunityInvestigation['updated_at'] ?? '')); ?></div></div>
                  <?php if (!empty($selectedCommunityInvestigation['is_public']) && !empty($selectedCommunityInvestigation['share_token'])): ?>
                    <div class="intel-grid-full"><label>Link publico</label><div class="mono"><a href="<?= clickfix_h(cfurl('investigation', true, ['share' => (string) $selectedCommunityInvestigation['share_token']])); ?>" target="_blank" rel="noreferrer">Abrir investigacion publica</a></div></div>
                  <?php endif; ?>
                  <div class="intel-grid-full"><label>Resumen</label><pre class="mono"><?= clickfix_h((string) ($selectedCommunityInvestigation['summary'] ?? '')); ?></pre></div>
                </div>
                <div class="intel-toolbar">
                  <form method="post" style="display:flex;gap:8px;align-items:center;width:auto;">
                    <input type="hidden" name="action" value="investigation_vote">
                    <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                    <input type="hidden" name="graph_id" value="<?= $communityActiveId; ?>">
                    <input type="hidden" name="vote" value="1">
                    <button class="btn" type="submit">[M+] +1 Malware</button>
                  </form>
                  <form method="post" style="display:flex;gap:8px;align-items:center;width:auto;">
                    <input type="hidden" name="action" value="investigation_vote">
                    <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                    <input type="hidden" name="graph_id" value="<?= $communityActiveId; ?>">
                    <input type="hidden" name="vote" value="-1">
                    <button type="submit">[L-] -1 Legit</button>
                  </form>
                  <a class="btn" href="<?= clickfix_h(cfurl('intel', false, ['graph_id' => $communityActiveId])); ?>">Abrir editor de investigacion</a>
                </div>
              </div>
              <?php if ($canMidReview): ?>
                <div class="intel-editor-section">
                  <h3>Revision Mid</h3>
                  <div class="intel-toolbar">
                    <form method="post" style="display:flex;gap:8px;align-items:center;width:auto;">
                      <input type="hidden" name="action" value="investigation_workflow">
                      <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                      <input type="hidden" name="graph_id" value="<?= $communityActiveId; ?>">
                      <input type="hidden" name="workflow_status" value="mid_verified">
                      <input type="text" name="workflow_note" placeholder="nota de validacion mid (opcional)">
                      <button class="btn" type="submit">[MID] Validar</button>
                    </form>
                    <form method="post" style="display:flex;gap:8px;align-items:center;width:auto;">
                      <input type="hidden" name="action" value="investigation_workflow">
                      <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                      <input type="hidden" name="graph_id" value="<?= $communityActiveId; ?>">
                      <input type="hidden" name="workflow_status" value="sr_review">
                      <input type="text" name="workflow_note" placeholder="nota para senior (opcional)">
                      <button type="submit">[MID->SR] Escalar</button>
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
                      <input type="text" name="workflow_note" placeholder="nota de verificacion publica (opcional)">
                      <button class="btn" type="submit">[SR][PUB] Verificar y publicar</button>
                    </form>
                    <form method="post" style="display:flex;gap:8px;align-items:center;width:auto;">
                      <input type="hidden" name="action" value="investigation_workflow">
                      <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                      <input type="hidden" name="graph_id" value="<?= $communityActiveId; ?>">
                      <input type="hidden" name="workflow_status" value="verified_internal">
                      <input type="text" name="workflow_note" placeholder="nota de verificacion interna (opcional)">
                      <button type="submit">[SR][INT] Verificar interno</button>
                    </form>
                    <form method="post" style="display:flex;gap:8px;align-items:center;width:auto;">
                      <input type="hidden" name="action" value="investigation_workflow">
                      <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                      <input type="hidden" name="graph_id" value="<?= $communityActiveId; ?>">
                      <input type="hidden" name="workflow_status" value="rejected">
                      <input type="text" name="workflow_note" placeholder="motivo de rechazo">
                      <button type="submit">[SR][X] Rechazar</button>
                    </form>
                  </div>
                </div>
              <?php endif; ?>
            <?php endif; ?>
          </section>
        </div>
      </section>
    <?php endif; ?>

    <?php if ($page === 'ops' || $page === 'home' || $page === 'search'): ?>
      <section class="row">
        <article class="card" id="event-workbench">
          <h2><?= $page === 'search' ? 'Resultados de busqueda' : 'Eventos recientes'; ?></h2>
          <div class="event-workbench">
            <aside class="event-feed" id="event-feed">
              <?php if (empty($eventWorkbenchRows)): ?>
                <div class="event-empty"><?= $page === 'search' ? 'Sin resultados de busqueda.' : 'Sin eventos recientes.'; ?></div>
              <?php else: ?>
                <?php foreach ($eventWorkbenchRows as $eventIndex => $eventRow): ?>
                  <?php
                    $scoreValue = isset($eventRow['score_total']) && is_numeric($eventRow['score_total']) ? (int) $eventRow['score_total'] : 0;
                    $severityClass = cfseverityclass($scoreValue);
                    $firstReason = !empty($eventRow['reason_list']) ? (string) $eventRow['reason_list'][0] : (string) ($eventRow['message'] ?? '');
                    $eventBlockedFeed = !empty($eventRow['blocked']) || !empty($eventRow['host_blocked_before']) || ($canSrViewer && !empty($eventRow['ip_blocked_before']));
                  ?>
                  <button type="button" class="event-feed-item<?= $eventIndex === 0 ? ' is-active' : ''; ?><?= $eventBlockedFeed ? ' is-blocked' : ''; ?>" data-event-index="<?= (int) $eventIndex; ?>">
                    <span class="event-feed-sev <?= clickfix_h($severityClass); ?>"></span>
                    <span class="event-feed-main">
                      <span class="event-feed-host"><?= clickfix_h((string) ($eventRow['hostname'] ?: '-')); ?></span>
                      <span class="event-feed-meta"><?= clickfix_h((string) ($eventRow['activity_at'] ?? $eventRow['received_at'] ?? '')); ?> | <?= $scoreValue; ?>/100</span>
                      <span class="event-feed-reason"><?= clickfix_h($firstReason); ?></span>
                      <?php if ($eventBlockedFeed): ?>
                        <span class="event-feed-flag">BLOQUEADO</span>
                      <?php endif; ?>
                      <?php if (!empty($eventRow['host_blocked_before']) || (!empty($eventRow['ip_blocked_before']) && $canSrViewer)): ?>
                        <?php
                          $feedFlags = [];
                          if (!empty($eventRow['host_blocked_before'])) {
                              $feedFlags[] = 'DOM x' . (int) ($eventRow['host_blocked_count'] ?? 0);
                          }
                          if ($canSrViewer && !empty($eventRow['ip_blocked_before'])) {
                              $feedFlags[] = 'IP x' . (int) ($eventRow['ip_blocked_count'] ?? 0);
                          }
                        ?>
                        <span class="event-feed-flag">REINCIDENTE <?= clickfix_h(implode(' | ', $feedFlags)); ?></span>
                      <?php endif; ?>
                    </span>
                  </button>
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
                <div class="event-grid">
                  <div class="event-kv"><b>Fecha</b><span id="event-time"></span></div>
                  <div class="event-kv"><b>Pais</b><span id="event-country"></span></div>
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
                    <div class="mut" style="margin-top:6px;font-size:.78rem">Fecha de deteccion: <span id="event-ioc-date"></span></div>
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
                    <div id="event-related-status" class="event-related-note">No se cargan automaticamente. Pulsa "Ver relacionadas" para consultar historial relacionado.</div>
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
                    <input type="hidden" name="action" value="review">
                    <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                    <input type="hidden" id="event-review-id" name="report_id" value="">
                    <select id="event-review-status" name="status">
                      <option value="pending"><?= clickfix_h(cft('review_pending')); ?></option>
                      <option value="accepted"><?= clickfix_h(cft('review_accepted')); ?></option>
                      <option value="rejected"><?= clickfix_h(cft('review_rejected')); ?></option>
                    </select>
                    <button class="btn" type="submit">Actualizar revision</button>
                  </form>
                  <div class="mut" style="margin-top:8px;font-size:.76rem;line-height:1.45">
                    <b><?= clickfix_h(cft('review_legend_title')); ?></b><br>
                    <?= clickfix_h(cft('review_legend_pending')); ?><br>
                    <?= clickfix_h(cft('review_legend_accepted')); ?><br>
                    <?= clickfix_h(cft('review_legend_rejected')); ?>
                  </div>
                <?php endif; ?>
                <?php if ($loggedIn && (cfcan($user, 'analyst_jr') || cfcan($user, 'analyst_sr'))): ?>
                  <div class="event-ops" style="margin-top:10px">
                    <h3 style="margin:0 0 6px">Operaciones rapidas</h3>
                    <div class="intel-toolbar">
                      <?php if (cfcan($user, 'analyst_sr')): ?>
                        <form method="post" class="event-quick-form" style="display:flex;gap:8px;align-items:center;width:auto;">
                          <input type="hidden" name="action" value="report_quick_action">
                          <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                          <input type="hidden" data-event-report-id name="report_id" value="">
                          <input type="hidden" name="quick_mode" value="block_domain">
                          <button type="submit">Bloquear dominio</button>
                        </form>
                        <form method="post" class="event-quick-form" style="display:flex;gap:8px;align-items:center;width:auto;">
                          <input type="hidden" name="action" value="report_quick_action">
                          <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                          <input type="hidden" data-event-report-id name="report_id" value="">
                          <input type="hidden" name="quick_mode" value="send_investigation_list">
                          <button type="submit">Mandar a investigacion</button>
                        </form>
                      <?php endif; ?>
                      <?php if (cfcan($user, 'analyst_jr')): ?>
                        <form method="post" class="event-quick-form" style="display:flex;gap:8px;align-items:center;width:auto;">
                          <input type="hidden" name="action" value="report_quick_action">
                          <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                          <input type="hidden" data-event-report-id name="report_id" value="">
                          <input type="hidden" name="quick_mode" value="create_investigation">
                          <button class="btn" type="submit">Generar investigacion</button>
                        </form>
                      <?php endif; ?>
                      <?php if (cfcan($user, 'admin')): ?>
                        <form method="post" class="event-quick-form" style="display:flex;gap:8px;align-items:center;width:auto;" onsubmit="return confirm('Eliminar esta deteccion de forma permanente?');">
                          <input type="hidden" name="action" value="report_quick_action">
                          <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                          <input type="hidden" data-event-report-id name="report_id" value="">
                          <input type="hidden" name="quick_mode" value="delete_report">
                          <button class="btn" type="submit">Eliminar deteccion</button>
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
          <details class="legacy-events">
            <summary>Eventos por dominio (agrupados)</summary>
            <table>
              <thead><tr><th>Dominio</th><th>Eventos</th><th>Impactos (dup)</th><th>Bloqueos</th><th>Score max</th><th>Ultima actividad</th></tr></thead>
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
          <details class="legacy-events">
            <summary>Capturas web (before/after)</summary>
            <?php
              $opsScan = is_array($latestScanPreview) ? $latestScanPreview : null;
              $opsAssets = is_array($latestScanAssetsApproved) ? $latestScanAssetsApproved : ['before' => null, 'after' => null];
              $opsAssetsReview = is_array($latestScanAssetsReview) ? $latestScanAssetsReview : ['before_exists' => false, 'after_exists' => false];
              $opsScanId = (int) ($opsScan['id'] ?? 0);
            ?>
            <?php if ($opsScan === null): ?>
              <p class="mut">Sin capturas aprobadas disponibles.</p>
            <?php else: ?>
              <p class="mono">scan_id: <?= (int) ($opsScan['id'] ?? 0); ?> | <?= clickfix_h((string) ($opsScan['hostname'] ?? '-')); ?> | <?= clickfix_h((string) ($opsScan['received_at'] ?? '')); ?></p>
              <div class="split">
                <div>
                  <h3 class="mono">ANTES</h3>
                  <?php if (!empty($opsAssets['before'])): ?>
                    <img src="<?= clickfix_h((string) $opsAssets['before']); ?>" alt="before scan" loading="lazy" style="max-width:100%;border-radius:10px;border:1px solid #5dc8ff33">
                  <?php else: ?>
                    <p class="mut">Sin captura before aprobada.</p>
                  <?php endif; ?>
                  <?php if ($canAdminViewer && !empty($opsAssetsReview['before_exists']) && $opsScanId > 0): ?>
                    <form method="post" style="margin-top:8px" onsubmit="return confirm('Eliminar captura before del scan #<?= $opsScanId; ?>?');">
                      <input type="hidden" name="action" value="scan_image_delete">
                      <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                      <input type="hidden" name="scan_report_id" value="<?= $opsScanId; ?>">
                      <input type="hidden" name="scan_kind" value="before">
                      <input type="hidden" name="return_page" value="ops">
                      <button class="btn" type="submit">Eliminar captura before</button>
                    </form>
                    <form method="post" style="margin-top:8px">
                      <input type="hidden" name="action" value="scan_image_assign">
                      <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                      <input type="hidden" name="scan_report_id" value="<?= $opsScanId; ?>">
                      <input type="hidden" name="scan_source_kind" value="before">
                      <input type="hidden" name="scan_target_kind" value="after">
                      <input type="hidden" name="return_page" value="ops">
                      <button class="btn" type="submit">Usar este BEFORE como AFTER</button>
                    </form>
                  <?php endif; ?>
                  <?php if ($canAdminViewer && $opsScanId > 0): ?>
                    <form method="post" enctype="multipart/form-data" style="margin-top:8px">
                      <input type="hidden" name="action" value="scan_image_upload">
                      <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                      <input type="hidden" name="scan_report_id" value="<?= $opsScanId; ?>">
                      <input type="hidden" name="scan_kind" value="before">
                      <input type="hidden" name="return_page" value="ops">
                      <input type="file" name="scan_upload_file" accept="image/png,image/jpeg,image/webp" required>
                      <select name="scan_upload_status" style="margin-top:6px">
                        <option value="approved" selected>approved</option>
                        <option value="pending">pending</option>
                        <option value="rejected">rejected</option>
                      </select>
                      <button class="btn" type="submit" style="margin-top:6px">Subir BEFORE manual</button>
                    </form>
                  <?php endif; ?>
                </div>
                <div>
                  <h3 class="mono">DESPUES</h3>
                  <?php if (!empty($opsAssets['after'])): ?>
                    <img src="<?= clickfix_h((string) $opsAssets['after']); ?>" alt="after scan" loading="lazy" style="max-width:100%;border-radius:10px;border:1px solid #5dc8ff33">
                  <?php else: ?>
                    <p class="mut">Sin captura after aprobada.</p>
                  <?php endif; ?>
                  <?php if ($canAdminViewer && !empty($opsAssetsReview['after_exists']) && $opsScanId > 0): ?>
                    <form method="post" style="margin-top:8px" onsubmit="return confirm('Eliminar captura after del scan #<?= $opsScanId; ?>?');">
                      <input type="hidden" name="action" value="scan_image_delete">
                      <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                      <input type="hidden" name="scan_report_id" value="<?= $opsScanId; ?>">
                      <input type="hidden" name="scan_kind" value="after">
                      <input type="hidden" name="return_page" value="ops">
                      <button class="btn" type="submit">Eliminar captura after</button>
                    </form>
                    <form method="post" style="margin-top:8px">
                      <input type="hidden" name="action" value="scan_image_assign">
                      <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                      <input type="hidden" name="scan_report_id" value="<?= $opsScanId; ?>">
                      <input type="hidden" name="scan_source_kind" value="after">
                      <input type="hidden" name="scan_target_kind" value="before">
                      <input type="hidden" name="return_page" value="ops">
                      <button class="btn" type="submit">Usar este AFTER como BEFORE</button>
                    </form>
                  <?php endif; ?>
                  <?php if ($canAdminViewer && $opsScanId > 0): ?>
                    <form method="post" enctype="multipart/form-data" style="margin-top:8px">
                      <input type="hidden" name="action" value="scan_image_upload">
                      <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                      <input type="hidden" name="scan_report_id" value="<?= $opsScanId; ?>">
                      <input type="hidden" name="scan_kind" value="after">
                      <input type="hidden" name="return_page" value="ops">
                      <input type="file" name="scan_upload_file" accept="image/png,image/jpeg,image/webp" required>
                      <select name="scan_upload_status" style="margin-top:6px">
                        <option value="approved" selected>approved</option>
                        <option value="pending">pending</option>
                        <option value="rejected">rejected</option>
                      </select>
                      <button class="btn" type="submit" style="margin-top:6px">Subir AFTER manual</button>
                    </form>
                  <?php endif; ?>
                </div>
              </div>
              <?php if ($canAdminViewer && $opsScanId > 0): ?>
                <form method="post" style="margin-top:10px" onsubmit="return confirm('Intercambiar BEFORE y AFTER del scan #<?= $opsScanId; ?>?');">
                  <input type="hidden" name="action" value="scan_image_swap">
                  <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                  <input type="hidden" name="scan_report_id" value="<?= $opsScanId; ?>">
                  <input type="hidden" name="return_page" value="ops">
                  <button class="btn" type="submit">Intercambiar BEFORE <-> AFTER</button>
                </form>
              <?php endif; ?>
            <?php endif; ?>
          </details>
          <details class="legacy-events">
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
                </select>
                <div class="bulk-review-actions">
                  <button type="button" class="secondary" id="bulk-review-select-pending">Solo pendientes</button>
                  <button type="button" class="secondary" id="bulk-review-clear">Limpiar</button>
                  <button class="btn" type="submit" id="bulk-review-submit" disabled>Aplicar veredicto masivo</button>
                </div>
              </form>
            <?php endif; ?>
            <table id="alerts-classic-table"><thead><tr><?php if ($canTableReview): ?><th class="bulk-review-select-cell" data-sortable="false"><input type="checkbox" id="bulk-review-select-all" aria-label="Seleccionar todas"></th><?php endif; ?><th>Fecha</th><th>Dominio</th><th>Marcado</th><?php if ($canSrViewer): ?><th>IP (manual)</th><th>Extension (manual)</th><?php endif; ?><th>Mensaje</th><th>Estado</th><?php if ($canTableReview): ?><th>Revision</th><?php endif; ?><?php if ($canTableOps): ?><th>Operaciones</th><?php endif; ?></tr></thead><tbody>
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
                <td><?= clickfix_h((string) ($r['message'] ?? '')); ?></td>
                <td><span class="badge <?= clickfix_h((string) ($r['review_status'] ?? 'pending')); ?>"><?= clickfix_h((string) ($r['review_status'] ?? 'pending')); ?></span></td>
                <?php if ($canTableReview): ?><td><form method="post" class="mono"><input type="hidden" name="action" value="review"><input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>"><input type="hidden" name="report_id" value="<?= (int) ($r['id'] ?? 0); ?>"><select name="status"><option value="pending"<?= (($r['review_status'] ?? 'pending') === 'pending') ? ' selected' : ''; ?>><?= clickfix_h(cft('review_pending')); ?></option><option value="accepted"<?= (($r['review_status'] ?? 'pending') === 'accepted') ? ' selected' : ''; ?>><?= clickfix_h(cft('review_accepted')); ?></option><option value="rejected"<?= (($r['review_status'] ?? 'pending') === 'rejected') ? ' selected' : ''; ?>><?= clickfix_h(cft('review_rejected')); ?></option></select><button class="btn" type="submit">OK</button></form></td><?php endif; ?>
                <?php if ($canTableOps): ?>
                  <td>
                    <div style="display:flex;gap:6px;flex-wrap:wrap">
                      <?php if (cfcan($user, 'analyst_sr')): ?>
                        <form method="post" class="mono" style="display:flex;gap:6px;align-items:center;width:auto">
                          <input type="hidden" name="action" value="report_quick_action">
                          <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                          <input type="hidden" name="report_id" value="<?= (int) ($r['id'] ?? 0); ?>">
                          <input type="hidden" name="quick_mode" value="block_domain">
                          <button type="submit">Bloquear</button>
                        </form>
                        <form method="post" class="mono" style="display:flex;gap:6px;align-items:center;width:auto">
                          <input type="hidden" name="action" value="report_quick_action">
                          <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                          <input type="hidden" name="report_id" value="<?= (int) ($r['id'] ?? 0); ?>">
                          <input type="hidden" name="quick_mode" value="send_investigation_list">
                          <button type="submit">A investigatelist</button>
                        </form>
                      <?php endif; ?>
                      <?php if (cfcan($user, 'analyst_jr')): ?>
                        <form method="post" class="mono" style="display:flex;gap:6px;align-items:center;width:auto">
                          <input type="hidden" name="action" value="report_quick_action">
                          <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                          <input type="hidden" name="report_id" value="<?= (int) ($r['id'] ?? 0); ?>">
                          <input type="hidden" name="quick_mode" value="create_investigation">
                          <button class="btn" type="submit">Generar investigacion</button>
                        </form>
                      <?php endif; ?>
                      <?php if (cfcan($user, 'admin')): ?>
                        <form method="post" class="mono" style="display:flex;gap:6px;align-items:center;width:auto" onsubmit="return confirm('Eliminar esta deteccion de forma permanente?');">
                          <input type="hidden" name="action" value="report_quick_action">
                          <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                          <input type="hidden" name="report_id" value="<?= (int) ($r['id'] ?? 0); ?>">
                          <input type="hidden" name="quick_mode" value="delete_report">
                          <button class="btn" type="submit">Eliminar deteccion</button>
                        </form>
                      <?php endif; ?>
                    </div>
                  </td>
                <?php endif; ?>
              </tr>
            <?php endforeach; ?>
            </tbody></table>
          </details>
        </article>
        <article class="card"><h2>Resumen</h2><p class="mono">last_update: <?= clickfix_h((string) $metrics['last_update']); ?></p><p class="mono">alerts_24h: <?= (int) $metrics['alerts_24h']; ?></p><p class="mono">blocks_24h: <?= (int) $metrics['blocks_24h']; ?></p><p class="mono">countries: <?= count($metrics['countries']); ?></p></article>
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
          <p class="mut">Vista consolidada del pipeline de correlacion: veredictos, familias, artefactos, stages y ejecucion de jobs.</p>
          <div class="analytics-kpi-grid">
            <div class="analytics-kpi"><div class="k">jobs totales</div><div class="v"><?= (int) ($corrJobs['total'] ?? 0); ?></div></div>
            <div class="analytics-kpi"><div class="k">jobs completados</div><div class="v"><?= (int) ($corrJobs['completed'] ?? 0); ?></div></div>
            <div class="analytics-kpi"><div class="k">artefactos</div><div class="v"><?= (int) ($corrArtifacts['total'] ?? 0); ?></div></div>
            <div class="analytics-kpi"><div class="k">payloads descargados</div><div class="v"><?= (int) ($corrArtifacts['fetched_payloads'] ?? 0); ?></div></div>
            <div class="analytics-kpi"><div class="k">investigaciones con pipeline</div><div class="v"><?= (int) ($corrInvestigations['with_pipeline'] ?? 0); ?></div></div>
            <div class="analytics-kpi"><div class="k">media stages</div><div class="v"><?= number_format((float) ($corrStages['avg'] ?? 0), 2); ?></div></div>
            <div class="analytics-kpi"><div class="k">max stages</div><div class="v"><?= (int) ($corrStages['max'] ?? 0); ?></div></div>
            <div class="analytics-kpi"><div class="k">analisis hechos</div><div class="v"><?= (int) ($corrArtifacts['analysis_done'] ?? 0); ?></div></div>
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
              <p class="mut">Sin tags de malware todavia.</p>
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
          <h2>Distribucion de stages</h2>
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
              <p class="mut">Sin datos de stages todavia.</p>
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
              <p class="mut">Sin comandos correlacionados todavia.</p>
            <?php endif; ?>
          </div>
        </article>
      </section>

      <section class="row" style="margin-bottom:8px">
        <article class="card">
          <h2>Top cadenas por numero de stages</h2>
          <div class="analytics-table-wrap">
            <table class="compact-table">
              <thead><tr><th>Graph</th><th>Titulo</th><th>Dominio</th><th>Stages</th><th>Artefactos</th><th>Accion</th></tr></thead>
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
                  <tr><td colspan="6" class="mut">Sin cadenas calculadas todavia.</td></tr>
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
            'total_historico' => 'Total historico',
            'ultima_semana' => 'Ultima semana',
            'ultimo_mes' => 'Ultimo mes',
            'ultimos_3_meses' => 'Ultimos 3 meses',
            'ultimos_6_meses' => 'Ultimos 6 meses',
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
      <section class="row">
        <article class="card">
          <h2>Graficos y metricas operativas</h2>
          <p class="mut">Alertas, bloqueos, revision, riesgo, actividad manual y capacidad de respuesta diaria.</p>
          <div class="analytics-kpi-grid">
            <div class="analytics-kpi"><div class="k">alertas / dia (media)</div><div class="v"><?= number_format($avgAlertsPerDay, 2); ?></div></div>
            <div class="analytics-kpi"><div class="k">bloqueos / dia (media)</div><div class="v"><?= number_format($avgBlocksPerDay, 2); ?></div></div>
            <div class="analytics-kpi"><div class="k">score medio periodo</div><div class="v"><?= number_format($avgRiskScorePeriod, 2); ?></div></div>
            <div class="analytics-kpi"><div class="k">pico alertas</div><div class="v"><?= (int) $peakAlerts; ?> <span class="mut-mini">(<?= clickfix_h($peakAlertsDay); ?>)</span></div></div>
            <div class="analytics-kpi"><div class="k">mejor ratio bloqueo</div><div class="v"><?= number_format($bestBlockRate, 2); ?>% <span class="mut-mini">(<?= clickfix_h($bestBlockRateDay); ?>)</span></div></div>
            <div class="analytics-kpi"><div class="k">dias sin alertas</div><div class="v"><?= (int) $zeroAlertDays; ?>/<?= $trendCount; ?></div></div>
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
              <p class="chart-title">Ratio de bloqueo por dia</p>
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
              <p class="chart-title">Revision diaria (revisado vs pendiente)</p>
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
          <h2>Distribucion por tipo y nuevos dominios</h2>
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
          <h2>Detector de anomalias (24h vs baseline)</h2>
          <p class="mut">Comparativa de hoy frente al comportamiento historico reciente.</p>
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
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </article>
        <article class="card">
          <h2>Picos anomalos por dominio</h2>
          <p class="mut">Dominios con subida anormal en 24h respecto a los 6 dias previos.</p>
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
                  <tr><td colspan="7" class="mut">No se detectaron picos anomalos en 24h.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </article>
      </section>
      <section class="card" style="margin-bottom:8px">
        <h2>Pendientes reales fuera de allowlist/blocklist</h2>
        <p class="mut">Alertas pendientes que no estan cubiertas por listas, para vaciar backlog real.</p>
        <div class="analytics-kpi-grid" style="margin-bottom:10px">
          <div class="analytics-kpi"><div class="k">alertas pendientes reales</div><div class="v"><?= (int) ($pendingOutsideSummary['alerts'] ?? 0); ?></div></div>
          <div class="analytics-kpi"><div class="k">dominios pendientes reales</div><div class="v"><?= (int) ($pendingOutsideSummary['domains'] ?? 0); ?></div></div>
        </div>
        <input class="analytics-search" id="analytics-pending-search" type="text" placeholder="Buscar pendiente (id, dominio, mensaje, score, tipo)">
        <div class="analytics-table-wrap">
          <table class="compact-table">
            <thead><tr><th>ID</th><th>Fecha</th><th>Dominio</th><th>Score</th><th>Tipo</th><th>Bloqueado</th><th>Mensaje</th><th>Accion</th></tr></thead>
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
          <h2>ML Insights (ultimas 300)</h2>
          <p class="mut">Clasificacion heuristica sobre las ultimas <?= (int) ($mlSampleInsights['sample_size'] ?? 0); ?> alertas.</p>
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
          <h2>ML Insights (historico total)</h2>
          <p class="mut">Clasificacion heuristica sobre todo el historico de alertas.</p>
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
          <table>
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
          <h2>Keywords dominantes (ultimas 300)</h2>
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
          <h2>Keywords dominantes (historico total)</h2>
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
        <p class="mut">Top keywords para historico total, ultima semana, ultimo mes, ultimos 3 meses y ultimos 6 meses.</p>
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
        <p class="mut mono" style="margin-top:0">Thresholds: low_risk &lt; 15 | suspicious 15-38 | malicious &gt; 38 (ultimas 300 alertas)</p>
        <table>
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
          <h2>Ultimo escaneo (antes / despues)</h2>
          <?php if ($latestScan === null): ?>
            <p class="mut">No hay escaneos disponibles.</p>
          <?php else: ?>
            <p class="mono">scan_id: <?= (int) ($latestScan['id'] ?? 0); ?> | <?= clickfix_h((string) ($latestScan['hostname'] ?? '-')); ?> | <?= clickfix_h((string) ($latestScan['received_at'] ?? '')); ?></p>
            <div class="split">
              <?php foreach (['before' => 'ANTES', 'after' => 'DESPUES'] as $scanKind => $scanLabel): ?>
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
                      <a class="btn" href="<?= clickfix_h($adminPreviewUrl); ?>" target="_blank" rel="noopener noreferrer">Abrir pestaña</a>
                      <a class="btn" href="<?= clickfix_h($adminDownloadUrl); ?>">Descargar</a>
                      <button class="btn" type="button" data-copy-text="<?= clickfix_h($adminPreviewUrl); ?>">Copiar URL admin</button>
                      <?php if ($publicApprovedUrl !== ''): ?>
                        <a class="btn" href="<?= clickfix_h($publicApprovedUrl); ?>" target="_blank" rel="noopener noreferrer">Abrir URL publica</a>
                        <button class="btn" type="button" data-copy-text="<?= clickfix_h($publicApprovedUrl); ?>">Copiar URL publica</button>
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
                      <button class="btn" type="submit">Aprobar y usar en publico</button>
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
                      <input type="text" name="scan_note" maxlength="500" placeholder="nota de revision (opcional)">
                      <button class="btn" type="submit">Guardar revision</button>
                    </form>
                    <form method="post" style="margin-top:8px" onsubmit="return confirm('Eliminar captura <?= clickfix_h($scanKind); ?> del scan #<?= $scanReportId; ?>?');">
                      <input type="hidden" name="action" value="scan_image_delete">
                      <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                      <input type="hidden" name="scan_report_id" value="<?= $scanReportId; ?>">
                      <input type="hidden" name="scan_kind" value="<?= clickfix_h($scanKind); ?>">
                      <input type="hidden" name="return_page" value="analytics">
                      <button class="btn" type="submit">Eliminar captura</button>
                    </form>
                    <form method="post" style="margin-top:8px">
                      <input type="hidden" name="action" value="scan_image_assign">
                      <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                      <input type="hidden" name="scan_report_id" value="<?= $scanReportId; ?>">
                      <input type="hidden" name="scan_source_kind" value="<?= clickfix_h($scanKind); ?>">
                      <input type="hidden" name="scan_target_kind" value="<?= $scanKind === 'before' ? 'after' : 'before'; ?>">
                      <input type="hidden" name="return_page" value="analytics">
                      <button class="btn" type="submit">Usar esta como <?= $scanKind === 'before' ? 'AFTER' : 'BEFORE'; ?></button>
                    </form>
                    <p class="mut" style="margin-top:6px">Cuando una captura queda en <b>approved</b>, se puede reutilizar en dashboard/index publico.</p>
                  <?php else: ?>
                    <p class="mut">Sin capturas disponibles.</p>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </article>
        <article class="card">
          <h2>Busqueda avanzada</h2>
          <form method="get">
            <input type="hidden" name="page" value="analytics">
            <input name="domain" value="<?= clickfix_h($domainFilter); ?>" placeholder="dominio">
            <input name="command" value="<?= clickfix_h($commandFilter); ?>" placeholder="comando">
            <input type="date" name="date_from" value="<?= clickfix_h($dateFromFilter); ?>">
            <input type="date" name="date_to" value="<?= clickfix_h($dateToFilter); ?>">
            <button class="btn" type="submit">Filtrar</button>
          </form>
          <div style="margin-top:10px;max-height:280px;overflow:auto">
            <table>
              <thead><tr><th>Fecha</th><th>Dominio</th><th>Score</th><?php if ($canAdminViewer): ?><th>Accion</th><?php endif; ?></tr></thead>
              <tbody>
                <?php foreach (array_slice($filteredReports, 0, 40) as $fr): ?>
                  <tr>
                    <td class="mono"><?= clickfix_h((string) ($fr['received_at'] ?? '')); ?></td>
                    <td class="mono"><?= clickfix_h((string) ($fr['hostname'] ?? '-')); ?></td>
                    <td class="mono"><?= isset($fr['score_total']) ? (int) $fr['score_total'] : 0; ?></td>
                    <?php if ($canAdminViewer): ?>
                      <td>
                        <form method="post" class="mono" onsubmit="return confirm('Eliminar alerta/deteccion #<?= (int) ($fr['id'] ?? 0); ?> de forma permanente?');">
                          <input type="hidden" name="action" value="report_quick_action">
                          <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                          <input type="hidden" name="report_id" value="<?= (int) ($fr['id'] ?? 0); ?>">
                          <input type="hidden" name="quick_mode" value="delete_alert">
                          <input type="hidden" name="return_page" value="analytics">
                          <button class="btn" type="submit">Eliminar</button>
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
          <h2>Usuarios de extensiones</h2>
          <table>
            <thead><tr><th>Client ID</th><th>Version</th><th>Dias activo</th><th>Total eventos</th><th>Bloqueos</th><th>IPs</th><th>Asociado</th><th>Ultimo seen</th><th>Detalle</th></tr></thead>
            <tbody>
              <?php foreach ($extensionClients as $ec): ?>
                <?php $cid = (string) ($ec['client_id'] ?? 'unknown'); ?>
                <tr>
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
              <p class="mono">Version: <?= clickfix_h((string) ($selectedMeta['extension_version'] ?? '')); ?> | Channel: <?= clickfix_h((string) ($selectedMeta['install_channel'] ?? '-')); ?> | Source: <?= clickfix_h((string) ($selectedMeta['install_source'] ?? '-')); ?></p>
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
          <?php endif; ?>
        </article>
      </section>
      <section class="row">
        <article class="card">
          <h2>Asociar usuario web con extension</h2>
          <p class="mut">Relaciona un usuario del dashboard con uno o varios <span class="mono">client_id</span> de extension para mensajeria individual.</p>
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
            <input type="text" name="link_client_id" list="known-extension-clients" required placeholder="client_id de extension" value="<?= clickfix_h($selectedClientId); ?>">
            <datalist id="known-extension-clients">
              <?php foreach ($extensionClients as $knownClient): ?>
                <?php $knownClientId = (string) ($knownClient['client_id'] ?? ''); ?>
                <?php if ($knownClientId !== ''): ?>
                  <option value="<?= clickfix_h($knownClientId); ?>"></option>
                <?php endif; ?>
              <?php endforeach; ?>
            </datalist>
            <input type="text" name="link_note" maxlength="280" placeholder="nota (opcional)">
            <button class="btn" type="submit">Guardar asociacion</button>
          </form>
        </article>
        <article class="card">
          <h2>Asociaciones activas</h2>
          <table>
            <thead><tr><th>Usuario web</th><th>Client ID</th><th>Eventos</th><th>Bloqueos</th><th>Ultimo seen</th><th>Nota</th><th>Accion</th></tr></thead>
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
                      <button class="btn" type="submit">Quitar</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if (empty($extensionUserLinks)): ?>
                <tr><td colspan="7" class="mut">Sin asociaciones activas.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </article>
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
            <button class="btn" type="submit">Aplicar accion individual</button>
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
            <button class="btn" type="submit">Aplicar accion masiva</button>
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
        <p class="mut">Dominios detectados en alertas que no estan cubiertos por allowlist ni blocklist, para triage rapido.</p>
        <table>
          <thead><tr><th>Dominio</th><th>Alertas</th><th>Ultima alerta</th><th>Acciones</th></tr></thead>
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
                    <button class="btn" type="submit">Bloquear</button>
                  </form>
                  <form method="post" style="display:inline-block">
                    <input type="hidden" name="action" value="list_action">
                    <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                    <input type="hidden" name="list_type" value="allowlist">
                    <input type="hidden" name="operation" value="add">
                    <input type="hidden" name="domain" value="<?= clickfix_h($pendingDomain); ?>">
                    <input type="hidden" name="reason" value="triage pending alert domain">
                    <button class="btn" type="submit">Permitir</button>
                  </form>
                  <form method="post" style="display:inline-block">
                    <input type="hidden" name="action" value="list_action">
                    <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                    <input type="hidden" name="list_type" value="investigatelist">
                    <input type="hidden" name="operation" value="add">
                    <input type="hidden" name="domain" value="<?= clickfix_h($pendingDomain); ?>">
                    <input type="hidden" name="reason" value="triage pending alert domain">
                    <button class="btn" type="submit">Investigar</button>
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
            <thead><tr><th>ID</th><th>Fecha</th><th>Dominio</th><th>Score</th><th>Tipo</th><th>Bloqueado</th><th>Mensaje</th><th>Accion</th></tr></thead>
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
      <section class="card"><h2>Auditoria</h2><table><thead><tr><th>Fecha</th><th>Usuario</th><th>Tipo</th><th>Accion</th><th>Dominio</th></tr></thead><tbody><?php foreach ($actions as $a): ?><tr><td class="mono"><?= clickfix_h((string) ($a['created_at'] ?? '')); ?></td><td><?php if (!empty($a['user_id'])): ?><a class="user-link" href="<?= clickfix_h(cfprofileurl((int) ($a['user_id'] ?? 0))); ?>"><?= clickfix_h((string) ($a['username'] ?? 'system')); ?></a><?php else: ?><?= clickfix_h((string) ($a['username'] ?? 'system')); ?><?php endif; ?></td><td class="mono"><?= clickfix_h((string) ($a['list_type'] ?? '')); ?></td><td class="mono"><?= clickfix_h((string) ($a['action'] ?? '')); ?></td><td class="mono"><?= clickfix_h((string) ($a['domain'] ?? '')); ?></td></tr><?php endforeach; ?></tbody></table></section>
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
              <option value="client">Por extension ID (uno o varios)</option>
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
            <input type="text" name="msg_title" maxlength="180" required placeholder="Titulo del mensaje">
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
            <button class="btn" type="submit">Enviar mensaje</button>
          </form>
          <p class="mut" style="margin-top:8px">Si seleccionas <b>scope=client</b>, puedes indicar uno o varios <span class="mono">client_id</span> separados por coma o espacio.</p>
          <p class="mut">Si seleccionas <b>scope=user</b>, puedes seleccionar uno o varios usuarios; se notificara a todos sus clientes asociados.</p>
          <p class="mut">Si seleccionas <b>scope=linked</b>, se notificara a todas las extensiones con usuario asociado (actualmente: <b><?= (int) $linkedExtensionClientCount; ?></b>).</p>
          <p class="mut">Si seleccionas <b>scope=unlinked</b>, se notificara a extensiones sin usuario asociado (actualmente: <b><?= (int) $unlinkedExtensionClientCount; ?></b>).</p>
          <p class="mut">La <b>fecha fin</b> aplica a cualquier scope; pasada esa fecha, el mensaje deja de entregarse a la extension.</p>
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
            <button class="btn" type="submit" onclick="return confirm('Limpiar historial de mensajes?');">Limpiar historial</button>
          </form>
          <table>
            <thead><tr><th>Fecha</th><th>Scope</th><th>Target</th><th>Severidad</th><th>Titulo</th><th>Expira</th><th>Activo</th><th>By</th><th>Accion</th></tr></thead>
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
                        <button class="btn" type="submit">Detener entrega</button>
                      </form>
                    <?php else: ?><span class="mut">inactivo</span><?php endif; ?>
                    <form method="post" onsubmit="return confirm('Eliminar este mensaje definitivamente de la plataforma?');" style="margin-bottom:6px">
                      <input type="hidden" name="action" value="message_hard_delete">
                      <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                      <input type="hidden" name="message_id" value="<?= $msgId; ?>">
                      <button class="btn" type="submit">Eliminar de plataforma</button>
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
                        <button class="btn" type="submit">Guardar rectificacion</button>
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
        <table>
          <thead><tr><th>Tabla</th><th>Registros</th><th>Ultima actividad</th><th>Abrir</th></tr></thead>
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
          <table>
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
            <button class="btn" type="submit">Guardar basic</button>
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
            <button class="btn" type="submit">Guardar premium</button>
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
            <button class="btn" type="submit">Guardar politica</button>
          </form>
        </article>
        <article class="card">
          <h2>Crear anuncio interno</h2>
          <p class="mut">Crea slots de prueba o anuncios reales con placement y targeting por rol. No depende de terceros.</p>
          <form method="post">
            <input type="hidden" name="action" value="internal_ad_save">
            <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
            <input type="text" name="ad_title" maxlength="180" required placeholder="Titulo del anuncio">
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
              <label class="item"><b><input type="checkbox" name="ad_target_guest" value="1" checked> guest</b><span>Index y publico</span></label>
              <label class="item"><b><input type="checkbox" name="ad_target_analyst_jr" value="1" checked> analyst_jr</b><span>Junior</span></label>
              <label class="item"><b><input type="checkbox" name="ad_target_analyst_mid" value="1" checked> analyst_mid</b><span>Mid</span></label>
              <label class="item"><b><input type="checkbox" name="ad_target_analyst_sr" value="1"> analyst_sr</b><span>Senior</span></label>
              <label class="item"><b><input type="checkbox" name="ad_target_admin" value="1"> admin</b><span>Administrador</span></label>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px">
              <button class="btn" type="submit">Guardar anuncio</button>
            </div>
          </form>
          <form method="post" style="margin-top:10px">
            <input type="hidden" name="action" value="internal_ads_seed_test">
            <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
            <button class="btn" type="submit">Generar anuncios de test</button>
          </form>
        </article>
      </section>
      <section class="card">
        <h2>Inventario de anuncios internos</h2>
        <table>
          <thead><tr><th>ID</th><th>Titulo</th><th>Placement</th><th>Targets</th><th>Priority</th><th>Ventana</th><th>Activo</th><th>By</th><th>Acciones</th></tr></thead>
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
                    <button class="btn" type="submit"><?= !empty($adRow['active']) ? 'Desactivar' : 'Activar'; ?></button>
                  </form>
                  <form method="post" style="display:inline-block" onsubmit="return confirm('Eliminar anuncio interno?');">
                    <input type="hidden" name="action" value="internal_ad_delete">
                    <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                    <input type="hidden" name="ad_id" value="<?= (int) ($adRow['id'] ?? 0); ?>">
                    <button class="btn" type="submit">Eliminar</button>
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
          <p class="mut">Elimina alertas y evidencias asociadas a un dominio. Esta accion es irreversible.</p>
          <form method="post" class="stack" onsubmit="return confirm('Esta accion elimina reportes y caches del dominio. Continuar?');">
            <input type="hidden" name="action" value="domain_purge">
            <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
            <input type="text" name="purge_domain" placeholder="dominio o URL (ej: ejemplo.com)" required>
            <label class="item"><b><input type="checkbox" name="purge_include_subdomains" value="1" checked> incluir subdominios</b><span>borra alertas de *.dominio</span></label>
            <label class="item"><b><input type="checkbox" name="purge_include_url" value="1" checked> borrar coincidencias en URL</b><span>URL contiene el dominio</span></label>
            <label class="item"><b><input type="checkbox" name="purge_include_previous_url" value="1" checked> borrar coincidencias en URL previa</b><span>previous_url contiene el dominio</span></label>
            <label class="item"><b><input type="checkbox" name="purge_delete_caches" value="1" checked> borrar caches de dominio</b><span>domain_intel_cache y whatweb_cache</span></label>
            <label class="item"><b><input type="checkbox" name="purge_delete_investigations" value="1"> borrar investigaciones con site_domain</b><span>borra investigaciones internas ligadas al dominio</span></label>
            <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:6px">
              <button class="btn" type="submit">Borrar dominio</button>
            </div>
          </form>
        </article>
        <article class="card">
          <h2>Dominios frecuentes</h2>
          <table>
            <thead><tr><th>Dominio</th><th>Alertas</th><th>Ultima vez</th><th>Accion</th></tr></thead>
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
                      <button class="btn" type="submit">Borrar</button>
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
            <button class="btn" type="submit">Guardar programacion</button>
          </form>
          <form method="post" style="margin-top:10px">
            <input type="hidden" name="action" value="report_run_now">
            <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
            <button class="btn" type="submit">Ejecutar ahora</button>
          </form>
          <p class="mut" style="margin-top:8px">Cron recomendado: <span class="mono">*/15 * * * * php /home/parthenoun/ClickFix/scripts/run_scheduled_reports.php</span></p>
        </article>
        <article class="card">
          <h2>Programaciones activas</h2>
          <table>
            <thead><tr><th>Periodo</th><th>Destino</th><th>Enabled</th><th>Ultima</th><th>Proxima</th></tr></thead>
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
          <button class="btn" type="submit">Actualizar preview</button>
        </form>
        <p class="mono">period: <?= clickfix_h((string) ($reportPreview['period'] ?? 'daily')); ?> | from: <?= clickfix_h((string) ($reportPreview['from'] ?? '')); ?> | generated_at: <?= clickfix_h((string) ($reportPreview['generated_at'] ?? '')); ?></p>
        <table>
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
        <h3 style="margin-top:8px">Top dominios</h3>
        <table>
          <thead><tr><th>Dominio</th><th>Hits</th></tr></thead>
          <tbody>
            <?php foreach ((array) ($reportPreview['top_domains'] ?? []) as $domainRow): ?>
              <tr>
                <td class="mono"><?= clickfix_h((string) ($domainRow['hostname'] ?? '')); ?></td>
                <td class="mono"><?= (int) ($domainRow['hits'] ?? 0); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </section>
    <?php endif; ?>

    <?php if ($loggedIn && $page === 'requests' && cfcan($user, 'analyst_sr')): ?>
      <section class="row">
        <article class="card">
          <h2>Desistimientos</h2>
          <table>
            <thead><tr><th>Fecha</th><th>Dominio</th><th>Estado</th><th>Accion</th></tr></thead>
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
                      <button class="btn" type="submit">OK</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </article>
        <article class="card">
          <h2>Solicitudes de eliminacion</h2>
          <table>
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
          <table>
            <thead><tr><th>Email</th><th>LinkedIn</th><th>Veces</th><th>Ultima</th><th>Accion</th></tr></thead>
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
                      <button class="btn" type="submit">OK</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </article>
        <article class="card">
          <h2>Accesos denegados</h2>
          <table>
            <thead><tr><th>Email</th><th>LinkedIn</th><th>Veces</th><th>Ultima</th></tr></thead>
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
      <section class="row">
        <article class="card">
          <h2>Nuevo usuario</h2>
          <p class="mut">Solo administradores pueden crear cuentas y definir el rol operativo.</p>
          <form method="post">
            <input type="hidden" name="action" value="user_create">
            <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
            <input type="text" name="new_username" maxlength="80" required placeholder="username">
            <input type="email" name="new_email" maxlength="190" required placeholder="email">
            <input type="password" name="new_password" minlength="10" required placeholder="password minimo 10 chars">
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
              <option value="en" selected>Idioma en</option>
              <option value="es">Idioma es</option>
            </select>
            <button class="btn" type="submit">Crear usuario</button>
          </form>
        </article>
        <article class="card">
          <h2>Guia rapida de administracion</h2>
          <div class="rbac">
            <div class="item"><b>Solicitudes</b><span>Revisa peticiones de acceso desde index y valida legitimidad.</span></div>
            <div class="item"><b>Alta de cuenta</b><span>Crea usuario con email, password y permisos de trabajo.</span></div>
            <div class="item"><b>Mantenimiento</b><span>Actualiza estado, credenciales y email cuando sea necesario.</span></div>
            <div class="item"><b>Auditoria</b><span>Corrobora actividad en paneles de datos, reportes y trazabilidad.</span></div>
          </div>
        </article>
      </section>
      <section class="card">
        <h2>Usuarios</h2>
        <table>
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
          <tbody>
            <?php foreach ($users as $u): ?>
              <tr>
                <td class="mono"><?= (int) ($u['id'] ?? 0); ?></td>
                <td class="mono"><a class="user-link" href="<?= clickfix_h(cfprofileurl((int) ($u['id'] ?? 0), [], false)); ?>"><?= clickfix_h((string) ($u['username'] ?? '')); ?></a></td>
                <td class="mono"><?= clickfix_h((string) ($u['email'] ?? '')); ?></td>
                <td><?= clickfix_h((string) ($u['role_label'] ?? clickfix_role_label((string) ($u['role'] ?? 'analyst_jr')))); ?></td>
                <td class="mono"><?= clickfix_h((string) ($u['preferred_lang'] ?? 'en')); ?></td>
                <td class="mono"><?= (int) ($u['reputation'] ?? 0); ?></td>
                <td><?= !empty($u['verified']) ? 'verificado' : 'pendiente'; ?></td>
                <td class="mono"><?= clickfix_h((string) ($u['created_at'] ?? '')); ?></td>
                <td>
                  <form method="post" class="mono">
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
                    <button class="btn" type="submit">Guardar</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </section>
      <section class="card">
        <h2>Solicitudes de acceso desde index.php (Trabaja con nosotros)</h2>
        <p class="mut">Estas solicitudes no crean usuarios automaticamente. Solo Admin crea cuenta y rol desde el panel superior.</p>
        <table>
          <thead><tr><th>Email</th><th>LinkedIn</th><th>Web</th><th>Veces</th><th>Primera</th><th>Ultima</th><th>Idioma</th><th>IP</th></tr></thead>
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
      <aside class="side-column">
        <section class="card side-card">
          <h3>Radar rapido</h3>
          <div class="side-metrics">
            <div class="side-metric"><span>alertas 24h</span><b data-live-metric="alerts_24h"><?= (int) ($metrics['alerts_24h'] ?? 0); ?></b></div>
            <div class="side-metric"><span>bloqueos 24h</span><b data-live-metric="blocks_24h"><?= (int) ($metrics['blocks_24h'] ?? 0); ?></b></div>
            <div class="side-metric"><span>dominios unicos</span><b data-live-metric="unique_hosts"><?= (int) ($metrics['unique_hosts'] ?? 0); ?></b></div>
            <div class="side-metric"><span>pend. revision</span><b data-live-metric="pending_review_total"><?= (int) ($metrics['pending_review_total'] ?? 0); ?></b></div>
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
        <section class="card side-card">
          <h3>Top paises</h3>
          <ul class="mini-list">
            <?php
              $sideCountries = [];
              if (is_array($metrics['countries'] ?? null)) {
                  foreach ((array) $metrics['countries'] as $countryKey => $countryValue) {
                      if (is_array($countryValue)) {
                          $name = (string) ($countryValue['country'] ?? $countryValue['code'] ?? $countryKey);
                          $hits = (int) ($countryValue['hits'] ?? $countryValue['count'] ?? 0);
                      } else {
                          $name = (string) $countryKey;
                          $hits = (int) $countryValue;
                      }
                      if ($name !== '') {
                          $sideCountries[] = ['name' => strtoupper($name), 'hits' => $hits];
                      }
                  }
              }
              usort($sideCountries, static function (array $a, array $b): int {
                  return $b['hits'] <=> $a['hits'];
              });
              $sideCountries = array_slice($sideCountries, 0, 6);
            ?>
            <?php if (empty($sideCountries)): ?>
              <li><span class="mut">Sin datos recientes</span><span class="mono">-</span></li>
            <?php else: ?>
              <?php foreach ($sideCountries as $countryRow): ?>
                <li><span class="mono"><?= clickfix_h((string) $countryRow['name']); ?></span><span class="mono"><?= (int) $countryRow['hits']; ?></span></li>
              <?php endforeach; ?>
            <?php endif; ?>
          </ul>
        </section>
        <section class="card side-card">
          <h3>Atajos</h3>
          <div class="mini-links">
            <a href="<?= clickfix_h(cfurl('home', !$loggedIn)); ?>"><?= clickfix_h(cft('nav_home')); ?></a>
            <a href="<?= clickfix_h(cfurl('search', true)); ?>"><?= clickfix_h(cft('nav_search')); ?></a>
            <a href="<?= clickfix_h(cfurl('coverage', true)); ?>"><?= clickfix_h(cft('nav_coverage')); ?></a>
            <?php if ($loggedIn): ?>
              <a href="<?= clickfix_h(cfprofileurl((int) ($user['id'] ?? 0), [], false)); ?>"><?= clickfix_h(cft('nav_profile')); ?></a>
              <a href="<?= clickfix_h(cfurl('settings')); ?>"><?= clickfix_h(cft('nav_settings')); ?></a>
              <a href="<?= clickfix_h(cfurl('ops')); ?>"><?= clickfix_h(cft('nav_ops')); ?></a>
              <a href="<?= clickfix_h(cfurl('analytics')); ?>"><?= clickfix_h(cft('nav_graphs')); ?></a>
              <a href="<?= clickfix_h(cfurl('intel')); ?>"><?= clickfix_h(cft('nav_investigation')); ?></a>
              <a href="<?= clickfix_h(cfurl('community')); ?>"><?= clickfix_h(cft('nav_community')); ?></a>
              <?php if (cfcan($user, 'analyst_sr')): ?>
                <a href="<?= clickfix_h(cfurl('extensions')); ?>"><?= clickfix_h(cft('nav_extensions')); ?></a>
                <a href="<?= clickfix_h(cfurl('lists')); ?>"><?= clickfix_h(cft('nav_lists')); ?></a>
              <?php endif; ?>
              <?php if (cfcan($user, 'admin')): ?>
                <a href="<?= clickfix_h(cfurl('users')); ?>"><?= clickfix_h(cft('nav_users')); ?></a>
                <a href="<?= clickfix_h(cfurl('reports')); ?>"><?= clickfix_h(cft('nav_reports')); ?></a>
              <?php endif; ?>
            <?php else: ?>
              <a href="<?= clickfix_h(cfurl('access', true)); ?>"><?= clickfix_h(cft('nav_access')); ?></a>
            <?php endif; ?>
          </div>
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
            <h3>Reportes automaticos</h3>
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

  <div id="session-timeout-modal" class="session-timeout-modal" hidden>
    <div class="session-timeout-card">
      <h3>Sesion a punto de expirar</h3>
      <p class="mut">Tu sesion expira en <span id="session-timeout-countdown">00:00</span>. Puedes extender <?= (int) $sessionExtendMinutes; ?> minutos o cerrar ahora.</p>
      <div class="split" style="margin-top:10px">
        <button class="btn" type="button" id="session-extend-btn">Seguir <?= (int) $sessionExtendMinutes; ?> min</button>
        <button class="btn secondary" type="button" id="session-logout-btn">Cerrar sesion</button>
      </div>
    </div>
  </div>

  <?php if ($showMonetizationPanel && !empty($monetization['show_ads'])): ?>
    <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=<?= urlencode((string) $monetization['adsense_client']); ?>" crossorigin="anonymous"></script>
    <script>
      (adsbygoogle = window.adsbygoogle || []).push({});
    </script>
  <?php endif; ?>
  <?php if ($enableHomeGeoPanels): ?>
    <script src="<?= clickfix_h($leafletJsUrl); ?>"></script>
  <?php endif; ?>

  <script>
    const appHeader = document.getElementById('app-header');
    const navToggle = document.getElementById('nav-toggle');
    const headerNavRow = document.getElementById('app-header-navrow');

    function syncStickyHeaderOffset() {
      if (!appHeader) {
        return;
      }
      const rect = appHeader.getBoundingClientRect();
      const computed = window.getComputedStyle(appHeader);
      const marginBottom = parseFloat(computed.marginBottom || '0') || 0;
      const offset = Math.ceil(rect.height + marginBottom + 20);
      document.documentElement.style.setProperty('--sticky-header-offset', `${offset}px`);
    }

    syncStickyHeaderOffset();
    window.addEventListener('resize', syncStickyHeaderOffset);
    window.addEventListener('load', syncStickyHeaderOffset);

    function syncMobileHeaderState(forceOpen = null) {
      if (!appHeader || !navToggle || !headerNavRow) {
        return;
      }
      if (window.innerWidth > 920) {
        appHeader.classList.remove('is-nav-open');
        document.body.classList.remove('nav-open-mobile');
        navToggle.setAttribute('aria-expanded', 'false');
        syncStickyHeaderOffset();
        return;
      }
      const nextOpen = typeof forceOpen === 'boolean'
        ? forceOpen
        : !appHeader.classList.contains('is-nav-open');
      appHeader.classList.toggle('is-nav-open', nextOpen);
      document.body.classList.toggle('nav-open-mobile', nextOpen);
      navToggle.setAttribute('aria-expanded', nextOpen ? 'true' : 'false');
      syncStickyHeaderOffset();
    }

    if (navToggle) {
      navToggle.addEventListener('click', () => {
        syncMobileHeaderState();
      });
    }
    if (headerNavRow) {
      headerNavRow.querySelectorAll('a, button').forEach((node) => {
        node.addEventListener('click', () => {
          if (window.innerWidth <= 920) {
            syncMobileHeaderState(false);
          }
        });
      });
    }
    window.addEventListener('resize', () => {
      if (window.innerWidth > 920) {
        syncMobileHeaderState(false);
      } else {
        syncStickyHeaderOffset();
      }
    });
    document.addEventListener('click', (event) => {
      if (window.innerWidth > 920 || !appHeader || !appHeader.classList.contains('is-nav-open')) {
        return;
      }
      const target = event.target;
      if (target instanceof Node && !appHeader.contains(target)) {
        syncMobileHeaderState(false);
      }
    });
    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && window.innerWidth <= 920) {
        syncMobileHeaderState(false);
      }
    });

    const selectedInvestigation = <?= $selectedInvestigationJson; ?>;
    const sharedInvestigation = <?= $sharedGraphJson; ?>;
    const eventWorkbenchData = <?= $eventWorkbenchJson; ?>;
    const intelApiLookupMapRows = <?= $intelApiLookupMapRowsJson; ?>;
    const intelApiCommonKeywords = <?= $intelApiCommonKeywordsJson; ?>;
    const eventFeed = document.getElementById('event-feed');
    const eventItems = eventFeed ? Array.from(eventFeed.querySelectorAll('.event-feed-item')) : [];
    const eventEmpty = document.getElementById('event-empty');
    const eventDetail = document.getElementById('event-detail');
    const eventTitle = document.getElementById('event-title');
    const eventBadges = document.getElementById('event-badges');
    const eventTime = document.getElementById('event-time');
    const eventCountry = document.getElementById('event-country');
    const eventUrl = document.getElementById('event-url');
    const eventPrevUrl = document.getElementById('event-prev-url');
    const eventIp = document.getElementById('event-ip');
    const eventExtension = document.getElementById('event-extension');
    const eventDomainHistory = document.getElementById('event-domain-history');
    const eventIpHistory = document.getElementById('event-ip-history');
    const eventIoc = document.getElementById('event-ioc');
    const eventIocHash = document.getElementById('event-ioc-hash');
    const eventIocName = document.getElementById('event-ioc-name');
    const eventIocPath = document.getElementById('event-ioc-path');
    const eventIocSite = document.getElementById('event-ioc-site');
    const eventIocDate = document.getElementById('event-ioc-date');
    const eventReasons = document.getElementById('event-reasons');
    const eventSnippets = document.getElementById('event-snippets');
    const eventContextTitle = document.getElementById('event-context-title');
    const eventContext = document.getElementById('event-context');
    const eventRaw = document.getElementById('event-raw');
    const eventSignals = document.getElementById('event-signals');
    const eventScoreDetails = document.getElementById('event-score-details');
    const eventRelatedLoad = document.getElementById('event-related-load');
    const eventRelatedStatus = document.getElementById('event-related-status');
    const eventRelatedWrap = document.getElementById('event-related-wrap');
    const eventRelatedBody = document.getElementById('event-related-body');
    const eventMitreGrid = document.getElementById('event-mitre-grid');
    const eventMitreEmpty = document.getElementById('event-mitre-empty');
    const canViewExactEventContext = <?= $canViewExactEventContext ? 'true' : 'false'; ?>;
    const eventReviewForm = document.getElementById('event-review-form');
    const eventReviewId = document.getElementById('event-review-id');
    const eventReviewStatus = document.getElementById('event-review-status');
    const eventQuickForms = Array.from(document.querySelectorAll('.event-quick-form'));
    const focusReportId = <?= $focusReportId; ?>;
    const msgScope = document.getElementById('msg-scope');
    const msgClientIds = document.getElementById('msg-client-ids');
    const msgUserIds = document.getElementById('msg-user-ids');
    const sessionExpiresAt = <?= (int) $sessionExpiresAt; ?>;
    const sessionWarningMinutes = <?= (int) $sessionWarningMinutes; ?>;
    const sessionExtendMinutes = <?= (int) $sessionExtendMinutes; ?>;
    const csrfToken = <?= json_encode($csrf, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    const homeLeafletCssUrl = <?= json_encode($leafletCssUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    const homeLeafletJsUrl = <?= json_encode($leafletJsUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    const homeLeafletWorldGeoJsonUrl = <?= json_encode($leafletWorldGeoJsonUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    let leafletEnsurePromise = null;
    let localWorldGeoPromise = null;

    const displayPanel = document.getElementById('display-settings-panel');
    const displayToggleBtn = document.getElementById('display-settings-toggle');
    const displayCloseBtn = document.getElementById('display-settings-close');
    const displayStorageKey = 'cf_display_settings_v1';
    const displayDefaults = {
      dark: true,
      contrast: false,
      compact: false,
      reducedMotion: false,
      decorations: true,
      accent: 'blue',
      font: 'sora'
    };
    function applyDisplaySettings(settings) {
      const body = document.body;
      if (!body) return;
      body.classList.toggle('ui-light', !settings.dark);
      body.classList.toggle('ui-contrast', !!settings.contrast);
      body.classList.toggle('ui-compact', !!settings.compact);
      body.classList.toggle('ui-reduced-motion', !!settings.reducedMotion);
      body.classList.toggle('ui-no-decor', !settings.decorations);
      ['blue','green','purple','amber','red','cyan'].forEach((key) => body.classList.remove(`ui-accent-${key}`));
      if (settings.accent) body.classList.add(`ui-accent-${settings.accent}`);
      ['public','dm','nunito','sora'].forEach((key) => body.classList.remove(`ui-font-${key}`));
      if (settings.font) body.classList.add(`ui-font-${settings.font}`);
    }
    function loadDisplaySettings() {
      try {
        const raw = localStorage.getItem(displayStorageKey);
        if (!raw) return { ...displayDefaults };
        const parsed = JSON.parse(raw);
        return { ...displayDefaults, ...(parsed || {}) };
      } catch (error) {
        return { ...displayDefaults };
      }
    }
    function saveDisplaySettings(settings) {
      try {
        localStorage.setItem(displayStorageKey, JSON.stringify(settings));
      } catch (error) {
        // ignore
      }
    }
    function syncDisplayInputs(settings) {
      document.querySelectorAll('[data-setting]').forEach((input) => {
        const key = String(input.getAttribute('data-setting') || '');
        if (!key) return;
        input.checked = !!settings[key];
      });
    }
    window.addEventListener('DOMContentLoaded', () => {
      let current = loadDisplaySettings();
      applyDisplaySettings(current);
      syncDisplayInputs(current);
      if (displayToggleBtn && displayPanel) {
        displayToggleBtn.addEventListener('click', () => {
          displayPanel.classList.toggle('open');
          displayPanel.setAttribute('aria-hidden', displayPanel.classList.contains('open') ? 'false' : 'true');
        });
      }
      if (displayCloseBtn && displayPanel) {
        displayCloseBtn.addEventListener('click', () => {
          displayPanel.classList.remove('open');
          displayPanel.setAttribute('aria-hidden', 'true');
        });
      }
      document.addEventListener('click', (ev) => {
        if (!displayPanel || !displayPanel.classList.contains('open')) return;
        const target = ev.target;
        if (!(target instanceof HTMLElement)) return;
        if (displayPanel.contains(target) || (displayToggleBtn && displayToggleBtn.contains(target))) return;
        displayPanel.classList.remove('open');
        displayPanel.setAttribute('aria-hidden', 'true');
      });
      document.querySelectorAll('[data-setting]').forEach((input) => {
        input.addEventListener('change', () => {
          const key = String(input.getAttribute('data-setting') || '');
          if (!key) return;
          current = { ...current, [key]: !!input.checked };
          applyDisplaySettings(current);
          saveDisplaySettings(current);
        });
      });
      document.querySelectorAll('[data-accent]').forEach((btn) => {
        btn.addEventListener('click', () => {
          const accent = String(btn.getAttribute('data-accent') || '');
          if (!accent) return;
          current = { ...current, accent };
          applyDisplaySettings(current);
          saveDisplaySettings(current);
        });
      });
      document.querySelectorAll('[data-font]').forEach((btn) => {
        btn.addEventListener('click', () => {
          const font = String(btn.getAttribute('data-font') || '');
          if (!font) return;
          current = { ...current, font };
          applyDisplaySettings(current);
          saveDisplaySettings(current);
        });
      });
    });

    const MITRE_TACTIC_ORDER = [
      'Initial Access',
      'Execution',
      'Persistence',
      'Privilege Escalation',
      'Defense Evasion',
      'Credential Access',
      'Discovery',
      'Lateral Movement',
      'Collection',
      'Command and Control',
      'Exfiltration',
      'Impact',
    ];

    const MITRE_LIBRARY = [
      { id: 'T1059.003', name: 'Windows Command Shell', tactic: 'Execution', patterns: [/\bcmd(?:\.exe)?\b/i, /\/c\s+[^\n]+/i, /\bcommand prompt\b/i] },
      { id: 'T1059.001', name: 'PowerShell', tactic: 'Execution', patterns: [/\bpowershell(?:\.exe)?\b/i, /\bpwsh\b/i, /\bIEX\b/i, /\binvoke-expression\b/i] },
      { id: 'T1059.005', name: 'Visual Basic', tactic: 'Execution', patterns: [/\bwscript\b/i, /\bcscript\b/i, /\.vbs\b/i, /\bvbscript\b/i] },
      { id: 'T1218.005', name: 'Mshta', tactic: 'Defense Evasion', patterns: [/\bmshta\b/i, /\.hta\b/i] },
      { id: 'T1218.011', name: 'Rundll32', tactic: 'Defense Evasion', patterns: [/\brundll32(?:\.exe)?\b/i] },
      { id: 'T1218.010', name: 'Regsvr32', tactic: 'Defense Evasion', patterns: [/\bregsvr32(?:\.exe)?\b/i] },
      { id: 'T1047', name: 'Windows Management Instrumentation', tactic: 'Execution', patterns: [/\bwmic\b/i, /\bwmiprvse\b/i, /win32_process/i] },
      { id: 'T1053.005', name: 'Scheduled Task', tactic: 'Persistence', patterns: [/\bschtasks\b/i, /\/create\s+\/tn/i] },
      { id: 'T1105', name: 'Ingress Tool Transfer', tactic: 'Command and Control', patterns: [/\bcurl\b/i, /\bwget\b/i, /\bInvoke-WebRequest\b/i, /\bcertutil\b/i, /\bbitsadmin\b/i] },
      { id: 'T1071.001', name: 'Web Protocols', tactic: 'Command and Control', patterns: [/https?:\/\//i, /\bwinhttp\b/i] },
      { id: 'T1027', name: 'Obfuscated/Encoded File or Information', tactic: 'Defense Evasion', patterns: [/\bbase64\b/i, /\bencoded\b/i, /\b-enc\b/i, /frombase64string/i] },
      { id: 'T1021.002', name: 'SMB/Windows Admin Shares', tactic: 'Lateral Movement', patterns: [/\bnet\s+use\b/i, /\\\\[a-z0-9\.\-]+\\[a-z0-9$]+/i] },
      { id: 'T1218.007', name: 'Msiexec', tactic: 'Defense Evasion', patterns: [/\bmsiexec\b/i, /\/i\s+https?:\/\//i] },
      { id: 'T1204.002', name: 'User Execution: Malicious File', tactic: 'Execution', patterns: [/\.exe\b/i, /\.scr\b/i, /\.msi\b/i, /\.bat\b/i] },
    ];

    function extractMitreMatches(sourceText) {
      const text = String(sourceText || '');
      if (!text) return [];
      const matches = new Map();
      for (const entry of MITRE_LIBRARY) {
        if (!entry || !Array.isArray(entry.patterns)) continue;
        if (entry.patterns.some((pattern) => pattern.test(text))) {
          matches.set(entry.id, entry);
        }
      }
      return Array.from(matches.values());
    }

    function groupMitreByTactic(matches) {
      const grouped = {};
      (matches || []).forEach((entry) => {
        const tactic = String(entry.tactic || 'Other');
        if (!grouped[tactic]) grouped[tactic] = [];
        grouped[tactic].push(entry);
      });
      return grouped;
    }

    function renderMitreBlueprint(container, emptyNode, matches) {
      if (!container) return;
      const list = Array.isArray(matches) ? matches : [];
      container.innerHTML = '';
      if (!list.length) {
        if (emptyNode) emptyNode.hidden = false;
        return;
      }
      if (emptyNode) emptyNode.hidden = true;
      const grouped = groupMitreByTactic(list);
      const tactics = [...MITRE_TACTIC_ORDER, ...Object.keys(grouped).filter((t) => !MITRE_TACTIC_ORDER.includes(t))];
      tactics.forEach((tactic) => {
        const entries = grouped[tactic];
        if (!entries || !entries.length) return;
        const card = document.createElement('div');
        card.className = 'mitre-tactic';
        const title = document.createElement('div');
        title.className = 'mitre-tactic-title';
        title.textContent = tactic;
        const listWrap = document.createElement('div');
        listWrap.className = 'mitre-tech-list';
        entries.forEach((entry) => {
          const chip = document.createElement('div');
          chip.className = 'mitre-tech';
          const id = document.createElement('b');
          id.textContent = entry.id;
          const name = document.createElement('span');
          name.textContent = entry.name;
          chip.appendChild(id);
          chip.appendChild(name);
          listWrap.appendChild(chip);
        });
        card.appendChild(title);
        card.appendChild(listWrap);
        container.appendChild(card);
      });
    }

    function decodeBase64Candidate(value) {
      const raw = String(value || '').trim();
      if (!raw || raw.length < 24) return '';
      if (!/^[A-Za-z0-9+/=]+$/.test(raw)) return '';
      try {
        const decoded = atob(raw.replace(/\s+/g, ''));
        const printable = decoded.replace(/[^\x09\x0A\x0D\x20-\x7E]/g, '');
        if (printable.length < Math.floor(decoded.length * 0.6)) {
          return '';
        }
        return decoded;
      } catch (error) {
        return '';
      }
    }

    function expandMitreSource(source) {
      const base = String(source || '');
      if (!base) return '';
      const extras = [];
      if (/%[0-9A-Fa-f]{2}/.test(base)) {
        try {
          const decoded = decodeURIComponent(base);
          if (decoded && decoded !== base) extras.push(decoded);
        } catch (error) {
          // ignore decode errors
        }
      }
      const base64Candidates = base.match(/[A-Za-z0-9+/=]{24,}/g) || [];
      base64Candidates.slice(0, 6).forEach((candidate) => {
        const decoded = decodeBase64Candidate(candidate);
        if (decoded) extras.push(decoded);
      });
      const cleaned = base.replace(/[`^]/g, '');
      if (cleaned !== base) extras.push(cleaned);
      return [base, ...extras].filter(Boolean).join('\n');
    }

    function buildMitreSourceFromEvent(event) {
      const parts = [
        event?.message,
        event?.detected_content,
        event?.full_context,
        event?.url,
        event?.previous_url,
        Array.isArray(event?.snippets) ? event.snippets.join('\n') : '',
        Array.isArray(event?.signals) ? event.signals.join('\n') : '',
      ];
      const raw = parts.filter(Boolean).join('\n');
      return expandMitreSource(raw);
    }

    function updateEventMitre(event) {
      if (!eventMitreGrid) return;
      const source = buildMitreSourceFromEvent(event);
      const matches = extractMitreMatches(source);
      renderMitreBlueprint(eventMitreGrid, eventMitreEmpty, matches);
    }

    function updateInvestigationMitre() {
      const container = document.getElementById('investigation-mitre-grid');
      const emptyNode = document.getElementById('investigation-mitre-empty');
      const wrapper = document.getElementById('investigation-mitre');
      if (!wrapper || !container) return;
      const source = expandMitreSource(wrapper.getAttribute('data-mitre-source') || '');
      const matches = extractMitreMatches(source);
      renderMitreBlueprint(container, emptyNode, matches);
    }

    function updateSharedInvestigationMitre() {
      const container = document.getElementById('shared-mitre-grid');
      const emptyNode = document.getElementById('shared-mitre-empty');
      const wrapper = document.getElementById('shared-mitre');
      if (!wrapper || !container) return;
      const source = expandMitreSource(wrapper.getAttribute('data-mitre-source') || '');
      const matches = extractMitreMatches(source);
      renderMitreBlueprint(container, emptyNode, matches);
    }

    function updateSourceAlertMitre() {
      const container = document.getElementById('source-alert-mitre-grid');
      const emptyNode = document.getElementById('source-alert-mitre-empty');
      const wrapper = document.getElementById('source-alert-mitre');
      if (!wrapper || !container) return;
      const source = expandMitreSource(wrapper.getAttribute('data-mitre-source') || '');
      const matches = extractMitreMatches(source);
      renderMitreBlueprint(container, emptyNode, matches);
    }

    const syncMessagingScope = () => {
      if (!msgScope) {
        return;
      }
      const scope = String(msgScope.value || 'all');
      if (msgClientIds) {
        const enabled = scope === 'client';
        msgClientIds.disabled = !enabled;
        msgClientIds.required = enabled;
        if (!enabled) {
          msgClientIds.value = '';
        }
      }
      if (msgUserIds) {
        const enabled = scope === 'user';
        msgUserIds.disabled = !enabled;
        msgUserIds.required = enabled;
        if (!enabled) {
          Array.from(msgUserIds.options).forEach((option) => {
            option.selected = false;
          });
        }
      }
    };
    if (msgScope) {
      msgScope.addEventListener('change', syncMessagingScope);
      syncMessagingScope();
    }

    (function initSessionTimeoutModal() {
      if (!sessionExpiresAt || sessionExpiresAt <= 0) {
        return;
      }
      const modal = document.getElementById('session-timeout-modal');
      const countdown = document.getElementById('session-timeout-countdown');
      const extendBtn = document.getElementById('session-extend-btn');
      const logoutBtn = document.getElementById('session-logout-btn');
      if (!modal || !countdown || !extendBtn || !logoutBtn) {
        return;
      }
      let expiresAt = Number(sessionExpiresAt || 0) * 1000;
      let warningTimer = null;
      let tickTimer = null;

      const renderCountdown = () => {
        const remainingMs = Math.max(0, expiresAt - Date.now());
        const totalSeconds = Math.floor(remainingMs / 1000);
        const minutes = String(Math.floor(totalSeconds / 60)).padStart(2, '0');
        const seconds = String(totalSeconds % 60).padStart(2, '0');
        countdown.textContent = `${minutes}:${seconds}`;
        if (remainingMs <= 0) {
          window.location.href = 'dashboard.php?page=access&public=1';
        }
      };

      const showModal = () => {
        modal.hidden = false;
        renderCountdown();
        if (tickTimer) window.clearInterval(tickTimer);
        tickTimer = window.setInterval(renderCountdown, 1000);
      };

      const scheduleWarning = () => {
        if (warningTimer) window.clearTimeout(warningTimer);
        const warningAt = expiresAt - (sessionWarningMinutes * 60 * 1000);
        const delay = Math.max(0, warningAt - Date.now());
        warningTimer = window.setTimeout(showModal, delay);
      };

      extendBtn.addEventListener('click', async () => {
        const formData = new FormData();
        formData.set('action', 'session_extend');
        formData.set('csrf_token', String(csrfToken || ''));
        try {
          const response = await fetch('dashboard.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
          });
          const payload = await response.json();
          if (payload && payload.status === 'ok' && payload.expires_at) {
            expiresAt = Number(payload.expires_at) * 1000;
            modal.hidden = true;
            if (tickTimer) window.clearInterval(tickTimer);
            scheduleWarning();
          }
        } catch (error) {
          // ignore
        }
      });

      logoutBtn.addEventListener('click', () => {
        window.location.href = 'dashboard.php?page=access&public=1';
      });

      scheduleWarning();
    })();

    (function initIntelAutosave() {
      const intelSaveForm = document.getElementById('intel-save-form');
      if (!intelSaveForm) {
        return;
      }
      const status = document.getElementById('intel-autosave-status');
      let dirty = false;
      let saveInFlight = false;

      const markDirty = () => {
        dirty = true;
        if (status) {
          status.textContent = 'Autosave: cambios pendientes...';
        }
      };

      intelSaveForm.addEventListener('input', markDirty);
      intelSaveForm.addEventListener('change', markDirty);

      const runAutosave = async () => {
        if (!dirty || saveInFlight) {
          return;
        }
        saveInFlight = true;
        const formData = new FormData(intelSaveForm);
        formData.set('auto_save', '1');
        try {
          const response = await fetch('dashboard.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
          });
          const payload = await response.json();
          if (payload && payload.status === 'ok') {
            dirty = false;
            const graphIdInput = document.getElementById('intel-graph-id');
            if (graphIdInput && payload.graph_id) {
              graphIdInput.value = String(payload.graph_id);
            }
            if (status) {
              status.textContent = `Autosave: ${String(payload.saved_at || '')}`;
            }
          }
        } catch (error) {
          if (status) {
            status.textContent = 'Autosave: error';
          }
        } finally {
          saveInFlight = false;
        }
      };

      setInterval(runAutosave, 45000);
    })();

    const escapeHtml = (value) =>
      String(value || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

    const escapeRegex = (value) =>
      String(value || '').replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

    function boldMatchedSnippets(source, snippets) {
      const text = String(source || '');
      const list = Array.isArray(snippets) ? snippets.filter(Boolean) : [];
      if (!text || !list.length) {
        return escapeHtml(text);
      }
      const ranges = [];
      const lowerSource = text.toLowerCase();
      list.forEach((snippet) => {
        const raw = String(snippet || '').trim();
        if (!raw) return;
        const needle = raw.toLowerCase();
        let cursor = 0;
        while (cursor < lowerSource.length) {
          const idx = lowerSource.indexOf(needle, cursor);
          if (idx === -1) break;
          ranges.push({ start: idx, end: idx + needle.length });
          cursor = idx + needle.length;
        }
      });
      if (!ranges.length) {
        return escapeHtml(text);
      }
      ranges.sort((a, b) => a.start - b.start || b.end - a.end);
      const merged = [];
      ranges.forEach((range) => {
        const last = merged[merged.length - 1];
        if (last && range.start <= last.end) {
          last.end = Math.max(last.end, range.end);
        } else {
          merged.push({ ...range });
        }
      });
      let output = '';
      let cursor = 0;
      merged.forEach((range) => {
        output += escapeHtml(text.slice(cursor, range.start));
        output += '<strong>' + escapeHtml(text.slice(range.start, range.end)) + '</strong>';
        cursor = range.end;
      });
      output += escapeHtml(text.slice(cursor));
      return output;
    }

    function parseChartData(canvas) {
      if (!canvas) {
        return { labels: [], alerts: [], blocks: [] };
      }
      const parseArray = (raw) => {
        try {
          const value = JSON.parse(String(raw || '[]'));
          return Array.isArray(value) ? value : [];
        } catch (error) {
          return [];
        }
      };
      return {
        labels: parseArray(canvas.dataset.labels),
        alerts: parseArray(canvas.dataset.alerts).map((v) => Number(v || 0)),
        blocks: parseArray(canvas.dataset.blocks).map((v) => Number(v || 0))
      };
    }

    function setupCanvasSize(canvas) {
      if (!canvas) return null;
      const width = Math.max(240, Math.floor(canvas.clientWidth || 240));
      const height = Math.max(120, Math.floor(canvas.clientHeight || 180));
      const ratio = Math.max(1, Math.floor(window.devicePixelRatio || 1));
      canvas.width = width * ratio;
      canvas.height = height * ratio;
      const ctx = canvas.getContext('2d');
      if (!ctx) return null;
      ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
      return { ctx, width, height };
    }

    function drawTrendChart(canvas) {
      const parsed = parseChartData(canvas);
      const setup = setupCanvasSize(canvas);
      if (!setup) return;
      const { ctx, width, height } = setup;
      ctx.clearRect(0, 0, width, height);
      const labels = parsed.labels;
      const alerts = parsed.alerts;
      const blocks = parsed.blocks;
      const count = Math.min(labels.length, alerts.length, blocks.length);
      if (count <= 0) return;
      const maxValue = Math.max(1, ...alerts, ...blocks);
      const padLeft = 28;
      const padRight = 8;
      const padTop = 10;
      const padBottom = 18;
      const plotW = Math.max(40, width - padLeft - padRight);
      const plotH = Math.max(40, height - padTop - padBottom);

      ctx.strokeStyle = '#1d3a55';
      ctx.lineWidth = 1;
      for (let i = 0; i <= 4; i += 1) {
        const y = padTop + (plotH * i) / 4;
        ctx.beginPath();
        ctx.moveTo(padLeft, y);
        ctx.lineTo(width - padRight, y);
        ctx.stroke();
      }

      const drawSeries = (series, color) => {
        ctx.strokeStyle = color;
        ctx.lineWidth = 2;
        ctx.beginPath();
        for (let i = 0; i < count; i += 1) {
          const x = padLeft + (plotW * (count === 1 ? 0.5 : i / (count - 1)));
          const y = padTop + plotH - (plotH * Number(series[i] || 0)) / maxValue;
          if (i === 0) ctx.moveTo(x, y);
          else ctx.lineTo(x, y);
        }
        ctx.stroke();
      };

      drawSeries(alerts, '#14b8ff');
      drawSeries(blocks, '#38d17a');
    }

    function drawRatioChart(canvas) {
      const parsed = parseChartData(canvas);
      const setup = setupCanvasSize(canvas);
      if (!setup) return;
      const { ctx, width, height } = setup;
      ctx.clearRect(0, 0, width, height);
      const alerts = parsed.alerts;
      const blocks = parsed.blocks;
      const count = Math.min(alerts.length, blocks.length);
      if (count <= 0) return;
      const padLeft = 16;
      const padRight = 8;
      const padTop = 10;
      const padBottom = 18;
      const plotW = Math.max(40, width - padLeft - padRight);
      const plotH = Math.max(40, height - padTop - padBottom);
      const barGap = 4;
      const barWidth = Math.max(2, (plotW - barGap * (count - 1)) / count);

      ctx.strokeStyle = '#1d3a55';
      ctx.lineWidth = 1;
      for (let i = 0; i <= 4; i += 1) {
        const y = padTop + (plotH * i) / 4;
        ctx.beginPath();
        ctx.moveTo(padLeft, y);
        ctx.lineTo(width - padRight, y);
        ctx.stroke();
      }

      for (let i = 0; i < count; i += 1) {
        const alertValue = Number(alerts[i] || 0);
        const blockValue = Number(blocks[i] || 0);
        const ratio = alertValue > 0 ? Math.min(100, (blockValue / alertValue) * 100) : 0;
        const barH = (plotH * ratio) / 100;
        const x = padLeft + i * (barWidth + barGap);
        const y = padTop + plotH - barH;
        ctx.fillStyle = '#ffd166';
        ctx.fillRect(x, y, barWidth, barH);
      }
    }

    function drawCategoryBarChart(canvas) {
      if (!canvas) return;
      const parseArray = (raw) => {
        try {
          const value = JSON.parse(String(raw || '[]'));
          return Array.isArray(value) ? value : [];
        } catch (error) {
          return [];
        }
      };
      const labels = parseArray(canvas.dataset.labels).map((v) => String(v || '').slice(0, 24));
      const counts = parseArray(canvas.dataset.counts).map((v) => Number(v || 0));
      const count = Math.min(labels.length, counts.length);
      const setup = setupCanvasSize(canvas);
      if (!setup) return;
      const { ctx, width, height } = setup;
      ctx.clearRect(0, 0, width, height);
      if (count <= 0) return;

      const maxValue = Math.max(1, ...counts);
      const padLeft = 14;
      const padRight = 12;
      const padTop = 12;
      const padBottom = 46;
      const plotW = Math.max(40, width - padLeft - padRight);
      const plotH = Math.max(40, height - padTop - padBottom);
      const gap = 8;
      const barW = Math.max(8, Math.floor((plotW - gap * (count - 1)) / count));

      ctx.strokeStyle = '#1d3a55';
      ctx.lineWidth = 1;
      ctx.beginPath();
      ctx.moveTo(padLeft, padTop + plotH);
      ctx.lineTo(width - padRight, padTop + plotH);
      ctx.stroke();

      for (let i = 0; i < count; i += 1) {
        const value = Number(counts[i] || 0);
        const barH = Math.max(1, (plotH * value) / maxValue);
        const x = padLeft + i * (barW + gap);
        const y = padTop + plotH - barH;
        ctx.fillStyle = '#63d9ff';
        ctx.fillRect(x, y, barW, barH);
        ctx.fillStyle = '#bcd8f2';
        ctx.font = '10px monospace';
        ctx.textAlign = 'center';
        ctx.fillText(String(value), x + Math.floor(barW / 2), y - 4);
        const label = labels[i] || '-';
        const trimmedLabel = label.length > 12 ? `${label.slice(0, 11)}â€¦` : label;
        ctx.fillStyle = '#8fb7d6';
        ctx.fillText(trimmedLabel, x + Math.floor(barW / 2), padTop + plotH + 14);
      }
    }

    function renderDashboardCharts() {
      drawTrendChart(document.getElementById('home-trend-chart'));
      drawRatioChart(document.getElementById('home-ratio-chart'));
      drawTrendChart(document.getElementById('analytics-trend-chart'));
      drawRatioChart(document.getElementById('analytics-ratio-chart'));
      drawTrendChart(document.getElementById('analytics-review-chart'));
      drawTrendChart(document.getElementById('analytics-risk-chart'));
      drawCategoryBarChart(document.getElementById('analytics-type-chart'));
      drawCategoryBarChart(document.getElementById('vt-reported-class-chart'));
      drawCategoryBarChart(document.getElementById('vt-reported-engine-chart'));
    }

    let chartResizeTimer = null;
    window.addEventListener('resize', () => {
      if (chartResizeTimer) {
        clearTimeout(chartResizeTimer);
      }
      chartResizeTimer = setTimeout(() => {
        renderDashboardCharts();
      }, 120);
    });

    function wireTableSearch(inputId, bodyId, rowSelector, emptyId) {
      const searchInput = document.getElementById(inputId);
      const tableBody = document.getElementById(bodyId);
      const emptyRow = document.getElementById(emptyId);
      const rows = tableBody ? Array.from(tableBody.querySelectorAll(rowSelector)) : [];
      if (!searchInput || !rows.length) {
        return;
      }
      const runFilter = () => {
        const term = String(searchInput.value || '').trim().toLowerCase();
        let visible = 0;
        rows.forEach((row) => {
          const haystack = String(row.textContent || '').toLowerCase();
          const show = term === '' || haystack.includes(term);
          row.hidden = !show;
          if (show) {
            visible += 1;
          }
        });
        if (emptyRow) {
          emptyRow.hidden = visible > 0;
        }
      };
      searchInput.addEventListener('input', runFilter);
      runFilter();
    }

    wireTableSearch('analytics-daily-search', 'analytics-daily-body', 'tr[data-day-row="1"]', 'analytics-daily-empty');
    wireTableSearch('analytics-pending-search', 'analytics-pending-body', 'tr[data-analytics-pending-row="1"]', 'analytics-pending-empty');
    wireTableSearch('pending-outside-search', 'pending-outside-body', 'tr[data-pending-outside-row="1"]', 'pending-outside-empty');

    function initVtReportedCharts() {
      const button = document.getElementById('vt-reported-generate');
      const panel = document.getElementById('vt-reported-panel');
      const classCanvas = document.getElementById('vt-reported-class-chart');
      const engineCanvas = document.getElementById('vt-reported-engine-chart');
      if (!button || !panel || !classCanvas || !engineCanvas) {
        return;
      }

      const render = () => {
        drawCategoryBarChart(classCanvas);
        drawCategoryBarChart(engineCanvas);
      };

      const updateButtonLabel = () => {
        button.textContent = panel.hidden ? 'Generar graficos VT' : 'Ocultar graficos VT';
      };

      button.addEventListener('click', () => {
        panel.hidden = !panel.hidden;
        updateButtonLabel();
        if (!panel.hidden) {
          requestAnimationFrame(() => {
            render();
          });
        }
      });

      updateButtonLabel();
    }

    initVtReportedCharts();

    async function copyTextToClipboard(value) {
      const text = String(value || '');
      if (!text) return false;
      try {
        if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
          await navigator.clipboard.writeText(text);
          return true;
        }
      } catch (error) {
        console.debug(error);
      }
      const helper = document.createElement('textarea');
      helper.value = text;
      helper.setAttribute('readonly', 'readonly');
      helper.style.position = 'fixed';
      helper.style.opacity = '0';
      helper.style.pointerEvents = 'none';
      document.body.appendChild(helper);
      helper.select();
      helper.setSelectionRange(0, text.length);
      let copied = false;
      try {
        copied = document.execCommand('copy');
      } catch (error) {
        console.debug(error);
      }
      document.body.removeChild(helper);
      return copied;
    }

    document.querySelectorAll('[data-copy-text]').forEach((button) => {
      button.addEventListener('click', async () => {
        const payload = button.getAttribute('data-copy-text') || '';
        const ok = await copyTextToClipboard(payload);
        const original = button.textContent || 'Copiar';
        button.textContent = ok ? 'Copiado' : 'Error copia';
        setTimeout(() => {
          button.textContent = original;
        }, 1400);
      });
    });

    const intelNavButtons = Array.from(document.querySelectorAll('[data-scroll-target]'));
    const intelSections = Array.from(document.querySelectorAll('[data-intel-section]'));

    function setActiveIntelSection(sectionId) {
      intelNavButtons.forEach((button) => {
        const target = String(button.getAttribute('data-scroll-target') || '');
        button.classList.toggle('active', target !== '' && target === sectionId);
      });
    }

    intelNavButtons.forEach((button) => {
      button.addEventListener('click', () => {
        const targetId = String(button.getAttribute('data-scroll-target') || '');
        if (!targetId) {
          return;
        }
        const target = document.getElementById(targetId);
        if (!target) {
          return;
        }
        setActiveIntelSection(targetId);
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
      });
    });

    if (intelSections.length) {
      const observer = new IntersectionObserver((entries) => {
        const visible = entries
          .filter((entry) => entry.isIntersecting)
          .sort((a, b) => b.intersectionRatio - a.intersectionRatio)[0];
        if (!visible) {
          return;
        }
        const sectionId = String(visible.target.getAttribute('data-intel-section') || visible.target.id || '');
        if (sectionId) {
          setActiveIntelSection(sectionId);
        }
      }, {
        root: null,
        threshold: [0.25, 0.45, 0.7],
        rootMargin: '-110px 0px -40% 0px'
      });
      intelSections.forEach((section) => observer.observe(section));
      const initialSection = String(intelSections[0].getAttribute('data-intel-section') || intelSections[0].id || '');
      if (initialSection) {
        setActiveIntelSection(initialSection);
      }
    }

    document.querySelectorAll('[data-scan-inline-src][data-scan-inline-target]').forEach((button) => {
      button.addEventListener('click', () => {
        const src = String(button.getAttribute('data-scan-inline-src') || '');
        const targetId = String(button.getAttribute('data-scan-inline-target') || '');
        if (!src || !targetId) {
          return;
        }
        const target = document.getElementById(targetId);
        if (!target) {
          return;
        }
        if (target.getAttribute('data-scan-loaded') === '1') {
          return;
        }
        target.textContent = 'Cargando captura...';
        const img = document.createElement('img');
        img.src = src;
        img.loading = 'lazy';
        img.alt = 'scan preview';
        img.style.maxWidth = '100%';
        img.style.borderRadius = '10px';
        img.style.border = '1px solid #5dc8ff33';
        img.addEventListener('load', () => {
          target.innerHTML = '';
          target.appendChild(img);
          target.setAttribute('data-scan-loaded', '1');
        });
        img.addEventListener('error', () => {
          target.textContent = 'No se pudo cargar la captura.';
        });
      });
    });

    function normalizeGraphPayload(raw) {
      const base = raw && typeof raw === 'object' ? raw : {};
      const nodes = Array.isArray(base.nodes) ? base.nodes : [];
      const edges = Array.isArray(base.edges) ? base.edges : [];
      const cleanedNodes = [];
      const seenNodes = new Set();
      nodes.forEach((node) => {
        if (!node || typeof node !== 'object') return;
        const id = String(node.id || '').replace(/[^a-zA-Z0-9._-]/g, '') || `n_${Math.random().toString(16).slice(2, 10)}`;
        if (seenNodes.has(id)) return;
        seenNodes.add(id);
        cleanedNodes.push({
          id,
          label: String(node.label || 'node').slice(0, 120),
          color: /^#[0-9a-fA-F]{6}$/.test(String(node.color || '')) ? String(node.color) : '#5dc8ff',
          x: Number.isFinite(Number(node.x)) ? Number(node.x) : 120,
          y: Number.isFinite(Number(node.y)) ? Number(node.y) : 120,
          tags: Array.isArray(node.tags) ? node.tags.map((t) => String(t).slice(0, 40)).filter(Boolean) : [],
          notes: String(node.notes || '').slice(0, 400)
        });
      });
      const cleanedEdges = [];
      const seenEdges = new Set();
      edges.forEach((edge) => {
        if (!edge || typeof edge !== 'object') return;
        const from = String(edge.from || '').replace(/[^a-zA-Z0-9._-]/g, '');
        const to = String(edge.to || '').replace(/[^a-zA-Z0-9._-]/g, '');
        if (!from || !to || !seenNodes.has(from) || !seenNodes.has(to)) return;
        const id = String(edge.id || '').replace(/[^a-zA-Z0-9._-]/g, '') || `e_${Math.random().toString(16).slice(2, 10)}`;
        if (seenEdges.has(id)) return;
        seenEdges.add(id);
        cleanedEdges.push({
          id,
          from,
          to,
          label: String(edge.label || '').slice(0, 120),
          color: /^#[0-9a-fA-F]{6}$/.test(String(edge.color || '')) ? String(edge.color) : '#94a3b8'
        });
      });
      return { nodes: cleanedNodes, edges: cleanedEdges };
    }

    function makeGraphRenderer({ wrap, svg, nodeLayer, graph, readOnly, onSelectNode, edgeListSelect, edgeFromSelect, edgeToSelect, nodeListSelect, controls = {}, vtLookupIndex = null }) {
      if (!wrap || !svg || !nodeLayer) {
        return null;
      }
      const state = {
        graph: normalizeGraphPayload(graph),
        selectedNodeId: null,
        drag: null,
        lastInteractionMoved: false,
        camera: {
          x: 40,
          y: 40,
          scale: 1,
          minScale: 0.35,
          maxScale: 2.6
        }
      };

      const controlRefs = {
        layoutSelect: controls.layoutSelect || null,
        layoutApplyButton: controls.layoutApplyButton || null,
        fitButton: controls.fitButton || null,
        zoomInButton: controls.zoomInButton || null,
        zoomOutButton: controls.zoomOutButton || null,
        zoomResetButton: controls.zoomResetButton || null,
        fullscreenButton: controls.fullscreenButton || null,
        fullscreenButtonAlt: controls.fullscreenButtonAlt || null,
        zoomStatus: controls.zoomStatus || null,
        zoomStatusAlt: controls.zoomStatusAlt || null
      };

      function nodeById(id) {
        return state.graph.nodes.find((n) => n.id === id) || null;
      }

      function edgeById(id) {
        return state.graph.edges.find((e) => e.id === id) || null;
      }

      function fillNodeSelect(selectEl, fallback = '') {
        if (!selectEl) return;
        const prev = fallback || selectEl.value || '';
        selectEl.innerHTML = '';
        state.graph.nodes.forEach((node) => {
          const opt = document.createElement('option');
          opt.value = node.id;
          opt.textContent = `${node.label} (${node.id})`;
          selectEl.appendChild(opt);
        });
        if (prev && state.graph.nodes.some((n) => n.id === prev)) {
          selectEl.value = prev;
        } else if (state.graph.nodes[0]) {
          selectEl.value = state.graph.nodes[0].id;
        }
      }

      function fillNodeList() {
        if (!nodeListSelect) return;
        const prev = state.selectedNodeId || nodeListSelect.value || '';
        nodeListSelect.innerHTML = '';
        state.graph.nodes.forEach((node) => {
          const opt = document.createElement('option');
          const tagsCount = Array.isArray(node.tags) ? node.tags.length : 0;
          const hasNotes = String(node.notes || '').trim() !== '';
          opt.value = node.id;
          opt.textContent = `${node.label}${tagsCount ? ` | tags:${tagsCount}` : ''}${hasNotes ? ' | notes' : ''}`;
          nodeListSelect.appendChild(opt);
        });
        if (prev && state.graph.nodes.some((n) => n.id === prev)) {
          nodeListSelect.value = prev;
        } else if (state.graph.nodes[0]) {
          nodeListSelect.value = state.graph.nodes[0].id;
        }
      }

      function fillEdgeList() {
        if (!edgeListSelect) return;
        const prev = edgeListSelect.value || '';
        edgeListSelect.innerHTML = '';
        state.graph.edges.forEach((edge) => {
          const from = nodeById(edge.from);
          const to = nodeById(edge.to);
          const opt = document.createElement('option');
          opt.value = edge.id;
          opt.textContent = `${from ? from.label : edge.from} -> ${to ? to.label : edge.to}${edge.label ? ` | ${edge.label}` : ''}`;
          edgeListSelect.appendChild(opt);
        });
        if (prev && state.graph.edges.some((e) => e.id === prev)) {
          edgeListSelect.value = prev;
        }
      }

      function nodeBounds() {
        if (!state.graph.nodes.length) {
          return { minX: 0, maxX: 0, minY: 0, maxY: 0, width: 0, height: 0, centerX: 0, centerY: 0 };
        }
        const xs = state.graph.nodes.map((node) => Number(node.x || 0));
        const ys = state.graph.nodes.map((node) => Number(node.y || 0));
        const minX = Math.min(...xs);
        const maxX = Math.max(...xs);
        const minY = Math.min(...ys);
        const maxY = Math.max(...ys);
        return {
          minX,
          maxX,
          minY,
          maxY,
          width: Math.max(1, maxX - minX),
          height: Math.max(1, maxY - minY),
          centerX: minX + (maxX - minX) / 2,
          centerY: minY + (maxY - minY) / 2
        };
      }

      function syncZoomStatus() {
        const label = `zoom ${Math.round(state.camera.scale * 100)}%`;
        [controlRefs.zoomStatus, controlRefs.zoomStatusAlt].forEach((node) => {
          if (node) {
            node.textContent = label;
          }
        });
      }

      function centerWorldPoint(worldX, worldY) {
        const rect = wrap.getBoundingClientRect();
        state.camera.x = rect.width / 2 - worldX * state.camera.scale;
        state.camera.y = rect.height / 2 - worldY * state.camera.scale;
      }

      function fitGraph(padding = 90) {
        const rect = wrap.getBoundingClientRect();
        if (!rect.width || !rect.height) return;
        const bounds = nodeBounds();
        if (!state.graph.nodes.length) {
          state.camera.scale = 1;
          state.camera.x = rect.width / 2;
          state.camera.y = rect.height / 2;
          render();
          return;
        }
        const availableWidth = Math.max(120, rect.width - padding * 2);
        const availableHeight = Math.max(120, rect.height - padding * 2);
        const scaleX = availableWidth / Math.max(bounds.width, 120);
        const scaleY = availableHeight / Math.max(bounds.height, 120);
        state.camera.scale = Math.max(state.camera.minScale, Math.min(state.camera.maxScale, Math.min(scaleX, scaleY, 1.65)));
        centerWorldPoint(bounds.centerX, bounds.centerY);
        render();
      }

      function resetZoom() {
        const bounds = nodeBounds();
        state.camera.scale = 1;
        centerWorldPoint(bounds.centerX, bounds.centerY);
        render();
      }

      function clientToWorld(clientX, clientY) {
        const rect = wrap.getBoundingClientRect();
        return {
          x: (clientX - rect.left - state.camera.x) / state.camera.scale,
          y: (clientY - rect.top - state.camera.y) / state.camera.scale
        };
      }

      function setZoom(nextScale, anchorClientX = null, anchorClientY = null) {
        const rect = wrap.getBoundingClientRect();
        const clamped = Math.max(state.camera.minScale, Math.min(state.camera.maxScale, nextScale));
        if (Math.abs(clamped - state.camera.scale) < 0.0001) {
          syncZoomStatus();
          return;
        }
        const anchorX = anchorClientX ?? (rect.left + rect.width / 2);
        const anchorY = anchorClientY ?? (rect.top + rect.height / 2);
        const world = clientToWorld(anchorX, anchorY);
        state.camera.scale = clamped;
        state.camera.x = anchorX - rect.left - world.x * state.camera.scale;
        state.camera.y = anchorY - rect.top - world.y * state.camera.scale;
        render();
      }

      function zoomBy(factor) {
        const rect = wrap.getBoundingClientRect();
        setZoom(state.camera.scale * factor, rect.left + rect.width / 2, rect.top + rect.height / 2);
      }

      function orderedNodeIds(rootId) {
        const adjacency = new Map();
        const incoming = new Map();
        state.graph.nodes.forEach((node) => {
          adjacency.set(node.id, []);
          incoming.set(node.id, 0);
        });
        state.graph.edges.forEach((edge) => {
          if (!adjacency.has(edge.from) || !adjacency.has(edge.to)) return;
          adjacency.get(edge.from).push(edge.to);
          incoming.set(edge.to, Number(incoming.get(edge.to) || 0) + 1);
        });
        adjacency.forEach((list) => list.sort());
        const fallbackRoot = rootId && adjacency.has(rootId)
          ? rootId
          : [...incoming.entries()].sort((a, b) => a[1] - b[1] || String(a[0]).localeCompare(String(b[0])))[0]?.[0];
        const visited = new Set();
        const queue = fallbackRoot ? [fallbackRoot] : [];
        const ordered = [];
        while (queue.length) {
          const current = queue.shift();
          if (!current || visited.has(current)) continue;
          visited.add(current);
          ordered.push(current);
          (adjacency.get(current) || []).forEach((nextId) => {
            if (!visited.has(nextId)) queue.push(nextId);
          });
        }
        state.graph.nodes
          .map((node) => node.id)
          .filter((id) => !visited.has(id))
          .sort()
          .forEach((id) => ordered.push(id));
        return { ordered, adjacency, incoming, rootId: fallbackRoot || '' };
      }

      function shouldAutoLayout() {
        const total = state.graph.nodes.length;
        if (!total) return false;
        let valid = 0;
        let maxCount = 0;
        const counts = new Map();
        state.graph.nodes.forEach((node) => {
          const x = Number(node.x);
          const y = Number(node.y);
          if (!Number.isFinite(x) || !Number.isFinite(y)) {
            return;
          }
          valid += 1;
          const key = `${Math.round(x)}:${Math.round(y)}`;
          const next = (counts.get(key) || 0) + 1;
          counts.set(key, next);
          if (next > maxCount) {
            maxCount = next;
          }
        });
        if (valid === 0) return true;
        return (maxCount / total) >= 0.6;
      }

      function estimateNodeRadius(node) {
        const label = String(node?.label || '');
        const base = 38;
        const extra = Math.min(80, label.length * 2.4);
        return base + extra;
      }

      function runForceLayout({ iterations = 200, stepsPerFrame = 6 } = {}) {
        if (!state.graph.nodes.length) {
          render();
          return;
        }
        const nodes = state.graph.nodes;
        const edges = state.graph.edges;
        const velocity = new Map();
        nodes.forEach((node) => {
          velocity.set(node.id, { x: 0, y: 0 });
          if (!Number.isFinite(node.x) || !Number.isFinite(node.y)) {
            node.x = 200 + Math.random() * 240;
            node.y = 160 + Math.random() * 180;
          }
        });
        const rect = wrap.getBoundingClientRect();
        const center = {
          x: rect.width > 0 ? rect.width / 2 : 420,
          y: rect.height > 0 ? rect.height / 2 : 260
        };
        const repulsion = 5200;
        const spring = 0.012;
        const desired = 170;
        const collisionStrength = 0.28;
        const centerStrength = 0.004;
        const damping = 0.86;
        let iter = 0;

        function stepSimulation() {
          const forces = new Map();
          nodes.forEach((node) => forces.set(node.id, { x: 0, y: 0 }));

          for (let i = 0; i < nodes.length; i += 1) {
            const a = nodes[i];
            for (let j = i + 1; j < nodes.length; j += 1) {
              const b = nodes[j];
              let dx = a.x - b.x;
              let dy = a.y - b.y;
              let dist2 = dx * dx + dy * dy;
              if (dist2 < 12) {
                dx += (Math.random() - 0.5) * 4;
                dy += (Math.random() - 0.5) * 4;
                dist2 = dx * dx + dy * dy;
              }
              const dist = Math.sqrt(dist2);
              const rep = repulsion / Math.max(40, dist2);
              const fx = (dx / dist) * rep;
              const fy = (dy / dist) * rep;
              forces.get(a.id).x += fx;
              forces.get(a.id).y += fy;
              forces.get(b.id).x -= fx;
              forces.get(b.id).y -= fy;

              const minDist = estimateNodeRadius(a) + estimateNodeRadius(b) + 24;
              if (dist < minDist) {
                const push = (minDist - dist) * collisionStrength;
                const cx = (dx / dist) * push;
                const cy = (dy / dist) * push;
                forces.get(a.id).x += cx;
                forces.get(a.id).y += cy;
                forces.get(b.id).x -= cx;
                forces.get(b.id).y -= cy;
              }
            }
          }

          edges.forEach((edge) => {
            const from = nodeById(edge.from);
            const to = nodeById(edge.to);
            if (!from || !to) return;
            const dx = to.x - from.x;
            const dy = to.y - from.y;
            const dist = Math.max(20, Math.sqrt(dx * dx + dy * dy));
            const force = (dist - desired) * spring;
            const fx = (dx / dist) * force;
            const fy = (dy / dist) * force;
            forces.get(from.id).x += fx;
            forces.get(from.id).y += fy;
            forces.get(to.id).x -= fx;
            forces.get(to.id).y -= fy;
          });

          nodes.forEach((node) => {
            const f = forces.get(node.id);
            const vx = velocity.get(node.id);
            vx.x = (vx.x + f.x + (center.x - node.x) * centerStrength) * damping;
            vx.y = (vx.y + f.y + (center.y - node.y) * centerStrength) * damping;
            node.x += vx.x;
            node.y += vx.y;
          });
        }

        function tick() {
          for (let s = 0; s < stepsPerFrame && iter < iterations; s += 1) {
            stepSimulation();
            iter += 1;
          }
          render();
          if (iter < iterations && !state.drag) {
            requestAnimationFrame(tick);
          } else {
            fitGraph(110);
          }
        }
        tick();
      }

      function applyLayout(mode = 'tree-vertical') {
        if (!state.graph.nodes.length) {
          render();
          return;
        }
        if (mode === 'force') {
          runForceLayout({ iterations: 240, stepsPerFrame: 8 });
          return;
        }
        const { ordered, adjacency, incoming, rootId } = orderedNodeIds(state.selectedNodeId || state.graph.nodes[0]?.id || '');
        const root = rootId || ordered[0] || '';
        const levels = new Map();
        const queue = root ? [{ id: root, depth: 0 }] : [];
        while (queue.length) {
          const current = queue.shift();
          if (!current || levels.has(current.id)) continue;
          levels.set(current.id, current.depth);
          (adjacency.get(current.id) || []).forEach((nextId) => {
            if (!levels.has(nextId)) {
              queue.push({ id: nextId, depth: current.depth + 1 });
            }
          });
        }
        ordered.forEach((id, index) => {
          if (!levels.has(id)) {
            levels.set(id, Math.max(1, Math.floor(index / 4) + 1));
          }
        });

        const layers = new Map();
        ordered.forEach((id) => {
          const depth = Number(levels.get(id) || 0);
          if (!layers.has(depth)) layers.set(depth, []);
          layers.get(depth).push(id);
        });
        layers.forEach((list) => {
          list.sort((a, b) => {
            const inDiff = Number(incoming.get(a) || 0) - Number(incoming.get(b) || 0);
            if (inDiff !== 0) return inDiff;
            return ordered.indexOf(a) - ordered.indexOf(b);
          });
        });

        const startX = 160;
        const startY = 110;
        const gapX = 190;
        const gapY = 110;

        if (mode === 'tree-vertical' || mode === 'tree-horizontal') {
          [...layers.entries()].sort((a, b) => a[0] - b[0]).forEach(([depth, ids]) => {
            const count = ids.length;
            ids.forEach((id, index) => {
              const node = nodeById(id);
              if (!node) return;
              const spread = (index - (count - 1) / 2);
              if (mode === 'tree-vertical') {
                node.x = startX + depth * gapX;
                node.y = startY + spread * gapY + 180;
              } else {
                node.x = startX + spread * gapX + 280;
                node.y = startY + depth * gapY;
              }
            });
          });
        } else if (mode === 'cascade') {
          ordered.forEach((id, index) => {
            const node = nodeById(id);
            if (!node) return;
            const row = Math.floor(index / 5);
            const col = index % 5;
            node.x = 150 + row * 130 + col * 85;
            node.y = 100 + row * 74 + col * 56;
          });
        } else if (mode === 'radial') {
          const bounds = nodeBounds();
          const centerX = bounds.centerX || 420;
          const centerY = bounds.centerY || 250;
          const byDepth = [...layers.entries()].sort((a, b) => a[0] - b[0]);
          byDepth.forEach(([depth, ids]) => {
            const radius = depth === 0 ? 0 : 120 + (depth - 1) * 105;
            ids.forEach((id, index) => {
              const node = nodeById(id);
              if (!node) return;
              if (depth === 0) {
                node.x = centerX;
                node.y = centerY;
                return;
              }
              const angle = (index / Math.max(1, ids.length)) * Math.PI * 2;
              node.x = Math.round(centerX + Math.cos(angle) * radius);
              node.y = Math.round(centerY + Math.sin(angle) * Math.max(70, radius * 0.66));
            });
          });
        } else if (mode === 'grid') {
          const columns = Math.max(2, Math.ceil(Math.sqrt(state.graph.nodes.length)));
          ordered.forEach((id, index) => {
            const node = nodeById(id);
            if (!node) return;
            const col = index % columns;
            const row = Math.floor(index / columns);
            node.x = 150 + col * 180;
            node.y = 110 + row * 115;
          });
        }

        render();
        fitGraph(100);
      }

      function toggleFullscreen() {
        if (!document.fullscreenEnabled) return;
        if (document.fullscreenElement === wrap) {
          document.exitFullscreen().catch(() => {});
          return;
        }
        wrap.requestFullscreen?.().catch(() => {});
      }

      function render() {
        const bounds = wrap.getBoundingClientRect();
        const width = Math.max(200, bounds.width);
        const height = Math.max(200, bounds.height);
        svg.setAttribute('viewBox', `0 0 ${Math.round(width)} ${Math.round(height)}`);
        svg.innerHTML = '';
        nodeLayer.innerHTML = '';
        nodeLayer.style.transform = `translate(${state.camera.x}px, ${state.camera.y}px) scale(${state.camera.scale})`;
        nodeLayer.style.transformOrigin = '0 0';
        syncZoomStatus();

        const scene = document.createElementNS('http://www.w3.org/2000/svg', 'g');
        scene.setAttribute('transform', `translate(${state.camera.x} ${state.camera.y}) scale(${state.camera.scale})`);
        svg.appendChild(scene);

        state.graph.edges.forEach((edge) => {
          const from = nodeById(edge.from);
          const to = nodeById(edge.to);
          if (!from || !to) return;
          const line = document.createElementNS('http://www.w3.org/2000/svg', 'line');
          line.setAttribute('x1', String(from.x));
          line.setAttribute('y1', String(from.y));
          line.setAttribute('x2', String(to.x));
          line.setAttribute('y2', String(to.y));
          line.setAttribute('stroke', edge.color || '#94a3b8');
          line.setAttribute('stroke-width', '2');
          line.setAttribute('stroke-linecap', 'round');
          scene.appendChild(line);
          if (edge.label) {
            const tx = (from.x + to.x) / 2;
            const ty = (from.y + to.y) / 2;
            const text = document.createElementNS('http://www.w3.org/2000/svg', 'text');
            text.setAttribute('x', String(tx));
            text.setAttribute('y', String(ty));
            text.setAttribute('fill', '#d6ebf8');
            text.setAttribute('font-size', '10');
            text.setAttribute('text-anchor', 'middle');
            text.textContent = edge.label;
            scene.appendChild(text);
          }
        });

        state.graph.nodes.forEach((node) => {
          const el = document.createElement('div');
          const kind = detectNodeKind(node);
          const kindClass = kind.key ? ` type-${kind.key}` : '';
          el.className = 'intel-node' + kindClass + (state.selectedNodeId === node.id ? ' active' : '');
          el.dataset.nodeId = node.id;
          el.style.left = `${node.x}px`;
          el.style.top = `${node.y}px`;
          el.style.background = node.color || '#5dc8ff';
          el.style.cursor = readOnly ? 'pointer' : 'move';
          const hasNotes = String(node.notes || '').trim() !== '';
          const tags = Array.isArray(node.tags) ? node.tags.filter(Boolean) : [];
          const labelWrap = document.createElement('div');
          labelWrap.className = 'intel-node-label';
          const flag = flagForNode(node);
          labelWrap.textContent = `${flag ? `${flag} ` : ''}${node.label}${hasNotes ? ' *' : ''}`;
          el.appendChild(labelWrap);
          const metaWrap = document.createElement('div');
          metaWrap.className = 'intel-node-meta';
          if (kind.label) {
            const kindChip = document.createElement('span');
            kindChip.className = `node-chip ${kind.key}`;
            kindChip.textContent = kind.label;
            metaWrap.appendChild(kindChip);
          }
          const vtLookup = lookupVtForNode(node, vtLookupIndex);
          if (vtLookup && vtLookup.summary && typeof vtLookup.summary === 'object') {
            const mal = Number(vtLookup.summary.malicious || 0);
            const sus = Number(vtLookup.summary.suspicious || 0);
            const har = Number(vtLookup.summary.harmless || 0);
            const und = Number(vtLookup.summary.undetected || 0);
            const vtChip = document.createElement('span');
            vtChip.className = 'node-chip vt';
            vtChip.textContent = `VT ${mal}/${sus}/${har}/${und}`;
            metaWrap.appendChild(vtChip);
          }
          if (metaWrap.childNodes.length) {
            el.appendChild(metaWrap);
          }
          el.title = `${node.label}${tags.length ? `\nTags: ${tags.join(', ')}` : ''}${hasNotes ? `\nNotas: ${String(node.notes).slice(0, 300)}` : ''}`;
          el.addEventListener('click', (ev) => {
            if (state.drag && state.drag.moved) {
              ev.preventDefault();
              return;
            }
            ev.stopPropagation();
            state.selectedNodeId = node.id;
            render();
            if (typeof onSelectNode === 'function') {
              onSelectNode(node);
            }
          });
          if (!readOnly) {
            el.addEventListener('pointerdown', (ev) => {
              ev.preventDefault();
              ev.stopPropagation();
              state.selectedNodeId = node.id;
              const world = clientToWorld(ev.clientX, ev.clientY);
              state.drag = {
                type: 'node',
                id: node.id,
                offsetX: world.x - node.x,
                offsetY: world.y - node.y,
                moved: false
              };
              render();
            });
          }
          nodeLayer.appendChild(el);
        });

        fillNodeSelect(edgeFromSelect);
        fillNodeSelect(edgeToSelect);
        fillEdgeList();
        fillNodeList();
      }

      wrap.addEventListener('pointerdown', (ev) => {
        const target = ev.target;
        const isNode = target instanceof HTMLElement && target.classList.contains('intel-node');
        if (isNode) {
          return;
        }
        state.drag = {
          type: 'pan',
          startClientX: ev.clientX,
          startClientY: ev.clientY,
          startX: state.camera.x,
          startY: state.camera.y,
          moved: false
        };
        wrap.classList.add('is-panning');
      });

      window.addEventListener('pointermove', (ev) => {
        if (!state.drag) return;
        if (state.drag.type === 'node' && !readOnly) {
          const node = nodeById(state.drag.id);
          if (!node) return;
          const world = clientToWorld(ev.clientX, ev.clientY);
          node.x = world.x - state.drag.offsetX;
          node.y = world.y - state.drag.offsetY;
          state.drag.moved = true;
          render();
          return;
        }
        if (state.drag.type === 'pan') {
          state.camera.x = state.drag.startX + (ev.clientX - state.drag.startClientX);
          state.camera.y = state.drag.startY + (ev.clientY - state.drag.startClientY);
          state.drag.moved = true;
          render();
        }
      });

      window.addEventListener('pointerup', () => {
        state.lastInteractionMoved = Boolean(state.drag && state.drag.moved);
        wrap.classList.remove('is-panning');
        state.drag = null;
      });

      wrap.addEventListener('click', (ev) => {
        if (state.lastInteractionMoved) {
          state.lastInteractionMoved = false;
          return;
        }
        const target = ev.target;
        const isNode = target instanceof HTMLElement && target.classList.contains('intel-node');
        if (isNode) return;
        state.selectedNodeId = null;
        render();
        if (typeof onSelectNode === 'function') {
          onSelectNode(null);
        }
      });

      wrap.addEventListener('wheel', (ev) => {
        ev.preventDefault();
        const factor = ev.deltaY > 0 ? 0.9 : 1.1;
        setZoom(state.camera.scale * factor, ev.clientX, ev.clientY);
      }, { passive: false });

      controlRefs.zoomInButton?.addEventListener('click', () => zoomBy(1.18));
      controlRefs.zoomOutButton?.addEventListener('click', () => zoomBy(0.84));
      controlRefs.zoomResetButton?.addEventListener('click', () => resetZoom());
      controlRefs.fitButton?.addEventListener('click', () => fitGraph(90));
      controlRefs.layoutApplyButton?.addEventListener('click', () => applyLayout(String(controlRefs.layoutSelect?.value || 'tree-vertical')));
      [controlRefs.fullscreenButton, controlRefs.fullscreenButtonAlt].forEach((buttonRef) => {
        buttonRef?.addEventListener('click', () => toggleFullscreen());
      });

      document.addEventListener('fullscreenchange', () => {
        const isActive = document.fullscreenElement === wrap;
        wrap.classList.toggle('is-fullscreen', isActive);
        [controlRefs.fullscreenButton, controlRefs.fullscreenButtonAlt].forEach((buttonRef) => {
          if (buttonRef) {
            buttonRef.textContent = isActive ? 'Salir pantalla completa' : 'Pantalla completa';
          }
        });
        render();
      });

      window.addEventListener('resize', () => render());
      const initialLayoutMode = String(controlRefs.layoutSelect?.value || 'tree-vertical');
      if (shouldAutoLayout()) {
        applyLayout('force');
      } else {
        render();
        fitGraph(90);
      }

      return {
        getGraph() {
          return normalizeGraphPayload(state.graph);
        },
        getSelectedNode() {
          return nodeById(state.selectedNodeId);
        },
        addNode(payload) {
          state.graph.nodes.push({
            id: payload.id,
            label: payload.label,
            color: payload.color,
            x: payload.x,
            y: payload.y,
            tags: payload.tags || [],
            notes: payload.notes || ''
          });
          state.selectedNodeId = payload.id;
          render();
          if (typeof onSelectNode === 'function') {
            onSelectNode(nodeById(payload.id));
          }
        },
        updateSelectedNode(payload) {
          const node = nodeById(state.selectedNodeId);
          if (!node) return false;
          node.label = payload.label;
          node.color = payload.color;
          node.tags = payload.tags || [];
          node.notes = payload.notes || '';
          render();
          if (typeof onSelectNode === 'function') {
            onSelectNode(node);
          }
          return true;
        },
        removeSelectedNode() {
          if (!state.selectedNodeId) return false;
          const nodeId = state.selectedNodeId;
          state.graph.nodes = state.graph.nodes.filter((n) => n.id !== nodeId);
          state.graph.edges = state.graph.edges.filter((e) => e.from !== nodeId && e.to !== nodeId);
          state.selectedNodeId = null;
          render();
          if (typeof onSelectNode === 'function') {
            onSelectNode(null);
          }
          return true;
        },
        addEdge(payload) {
          state.graph.edges.push(payload);
          render();
        },
        removeEdge(edgeId) {
          state.graph.edges = state.graph.edges.filter((e) => e.id !== edgeId);
          render();
        },
        edgeById,
        selectNode(nodeId) {
          if (!nodeId || !nodeById(nodeId)) {
            state.selectedNodeId = null;
            render();
            if (typeof onSelectNode === 'function') {
              onSelectNode(null);
            }
            return false;
          }
          state.selectedNodeId = nodeId;
          render();
          if (typeof onSelectNode === 'function') {
            onSelectNode(nodeById(nodeId));
          }
          return true;
        },
        fitGraph() {
          fitGraph(90);
        },
        resetZoom() {
          resetZoom();
        },
        zoomIn() {
          zoomBy(1.18);
        },
        zoomOut() {
          zoomBy(0.84);
        },
        applyLayout(mode) {
          applyLayout(mode);
        },
        toggleFullscreen() {
          toggleFullscreen();
        }
      };
    }

    function buildHighlightedContext(text, snippets) {
      if (!text) return '';
      const source = String(text);
      const lowerSource = source.toLowerCase();
      const list = Array.isArray(snippets) ? snippets : [];
      const uniqueSnippets = [...new Set(list.map((entry) => String(entry || '').trim()).filter(Boolean))];
      if (!uniqueSnippets.length) {
        return escapeHtml(source);
      }
      const ranges = [];
      uniqueSnippets.forEach((snippet) => {
        const lowerSnippet = snippet.toLowerCase();
        let cursor = 0;
        while (cursor < lowerSource.length) {
          const index = lowerSource.indexOf(lowerSnippet, cursor);
          if (index === -1) break;
          ranges.push({ start: index, end: index + lowerSnippet.length });
          cursor = index + lowerSnippet.length;
        }
      });
      if (!ranges.length) {
        return escapeHtml(source);
      }
      ranges.sort((a, b) => a.start - b.start || b.end - a.end);
      const merged = [];
      ranges.forEach((range) => {
        const last = merged[merged.length - 1];
        if (last && range.start <= last.end) {
          last.end = Math.max(last.end, range.end);
        } else {
          merged.push({ ...range });
        }
      });
      let output = '';
      let cursor = 0;
      merged.forEach((range) => {
        output += escapeHtml(source.slice(cursor, range.start));
        output += '<mark>' + escapeHtml(source.slice(range.start, range.end)) + '</mark>';
        cursor = range.end;
      });
      output += escapeHtml(source.slice(cursor));
      return output;
    }

    function eventSeverity(score) {
      const numeric = Number(score || 0);
      if (numeric > 40) return 'critical';
      if (numeric >= 30) return 'high';
      if (numeric > 15) return 'medium';
      return 'low';
    }

    const relatedEventsCache = new Map();

    function buildEventFocusLink(reportId) {
      const url = new URL(window.location.href);
      url.searchParams.set('report_id', String(reportId || ''));
      url.searchParams.delete('format');
      return `${url.pathname}?${url.searchParams.toString()}`;
    }

    function renderRelatedRows(rows) {
      if (!eventRelatedBody || !eventRelatedWrap) {
        return;
      }
      eventRelatedBody.innerHTML = '';
      if (!Array.isArray(rows) || !rows.length) {
        const tr = document.createElement('tr');
        const td = document.createElement('td');
        td.colSpan = 9;
        td.className = 'mut';
        td.textContent = 'No se encontraron alertas relacionadas.';
        tr.appendChild(td);
        eventRelatedBody.appendChild(tr);
        eventRelatedWrap.hidden = false;
        return;
      }
      rows.forEach((row) => {
        const tr = document.createElement('tr');
        const relation = [];
        if (row.related_by_domain) relation.push('dominio');
        if (row.related_by_ip) relation.push('ip');
        if (row.related_by_ttp) relation.push('ttp');
        if (row.related_by_snippet) relation.push('snippet');
        const relationText = relation.length ? relation.join(' + ') : '-';
        const sharedReasons = Array.isArray(row.shared_reasons) ? row.shared_reasons : [];
        const sharedSignals = Array.isArray(row.shared_signals) ? row.shared_signals : [];
        const sharedSnippets = Array.isArray(row.shared_snippets) ? row.shared_snippets : [];
        const evidenceParts = [];
        if (sharedSnippets.length) {
          evidenceParts.push(`snippet: ${sharedSnippets[0]}`);
        }
        if (sharedReasons.length) {
          evidenceParts.push(`reason: ${sharedReasons[0]}`);
        }
        if (sharedSignals.length) {
          evidenceParts.push(`signal: ${sharedSignals[0]}`);
        }
        const evidenceText = evidenceParts.join(' | ') || '-';
        const cells = [
          String(row.id || ''),
          String(row.activity_at || row.received_at || '-'),
          String(row.hostname || '-'),
          String(row.ip || '-'),
          `${Number(row.score_total || 0)}/100`,
          String(row.review_status || 'pending'),
          relationText,
          evidenceText,
        ];
        cells.forEach((value) => {
          const td = document.createElement('td');
          td.textContent = value;
          if (/^\d+$/.test(value) || value.includes('/100')) {
            td.className = 'mono';
          }
          tr.appendChild(td);
        });
        const actionTd = document.createElement('td');
        const link = document.createElement('a');
        link.className = 'event-related-link';
        link.href = buildEventFocusLink(row.id || 0);
        link.textContent = 'Abrir';
        actionTd.appendChild(link);
        if (row.hostname) {
          const sep = document.createTextNode(' | ');
          actionTd.appendChild(sep);
          const searchLink = document.createElement('a');
          searchLink.className = 'event-related-link';
          searchLink.href = `dashboard.php?page=search&domain=${encodeURIComponent(String(row.hostname || ''))}`;
          searchLink.textContent = 'Buscar';
          actionTd.appendChild(searchLink);
        }
        tr.appendChild(actionTd);
        eventRelatedBody.appendChild(tr);
      });
      eventRelatedWrap.hidden = false;
    }

    async function loadRelatedReports(reportId) {
      const url = `dashboard.php?format=related_reports&report_id=${encodeURIComponent(String(reportId || 0))}`;
      const response = await fetch(url, { cache: 'no-store' });
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }
      const payload = await response.json();
      if (!payload || payload.status !== 'ok') {
        throw new Error(String(payload?.message || 'invalid_response'));
      }
      return Array.isArray(payload.related) ? payload.related : [];
    }

    function renderEventDetail(index) {
      if (!eventDetail || !eventEmpty || !eventWorkbenchData[index]) {
        return;
      }
      const event = eventWorkbenchData[index];
      const liveItems = eventFeed ? Array.from(eventFeed.querySelectorAll('.event-feed-item')) : [];
      liveItems.forEach((item) => {
        item.classList.toggle('is-active', Number(item.dataset.eventIndex) === index);
      });
      eventEmpty.hidden = true;
      eventDetail.hidden = false;
      eventTitle.textContent = event.hostname || '(sin dominio)';
      eventTime.textContent = event.activity_at || event.received_at || '-';
      eventCountry.textContent = event.country || '-';
      eventUrl.textContent = event.url || '-';
      eventPrevUrl.textContent = event.previous_url || '-';
      const isManualReport = String(event.event_type || '') === 'manual_report';
      if (eventIp) {
        eventIp.textContent = isManualReport ? (event.ip || '-') : '-';
      }
      if (eventExtension) {
        eventExtension.textContent = isManualReport ? (event.extension_version || '-') : '-';
      }
      if (eventDomainHistory) {
        const domainBlockedCount = Number(event.host_blocked_count || 0);
        const domainTotalCount = Number(event.host_total_count || 0);
        const domainBlocked = Boolean(event.host_blocked_before);
        const domainLastBlockedAt = String(event.host_last_blocked_at || '');
        eventDomainHistory.textContent = domainBlocked
          ? `SI (${domainBlockedCount} bloqueos / ${domainTotalCount} reportes${domainLastBlockedAt ? `, ultimo ${domainLastBlockedAt}` : ''})`
          : `No (${domainTotalCount} reportes)`;
      }
      if (eventIpHistory) {
        const ipBlockedCount = Number(event.ip_blocked_count || 0);
        const ipTotalCount = Number(event.ip_total_count || 0);
        const ipBlocked = Boolean(event.ip_blocked_before);
        const ipLastBlockedAt = String(event.ip_last_blocked_at || '');
        eventIpHistory.textContent = ipBlocked
          ? `SI (${ipBlockedCount} bloqueos / ${ipTotalCount} reportes${ipLastBlockedAt ? `, ultimo ${ipLastBlockedAt}` : ''})`
          : `No (${ipTotalCount} reportes)`;
      }
      if (eventIoc) {
        const isUnsafeDownload = String(event.event_type || '') === 'unsafe_download';
        if (isUnsafeDownload) {
          const ioc = event.download_ioc || {};
          if (eventIocHash) {
            eventIocHash.textContent = ioc.hash ? String(ioc.hash) : 'No disponible';
          }
          if (eventIocName) {
            eventIocName.textContent = ioc.filename ? String(ioc.filename) : (event.detected_content || '-');
          }
          if (eventIocPath) {
            eventIocPath.textContent = ioc.path ? String(ioc.path) : '-';
          }
          if (eventIocSite) {
            eventIocSite.textContent = ioc.url || ioc.site || event.url || event.hostname || '-';
          }
          if (eventIocDate) {
            eventIocDate.textContent = event.activity_at || event.received_at || '-';
          }
          eventIoc.hidden = false;
        } else {
          eventIoc.hidden = true;
        }
      }
      if (eventReviewId) {
        eventReviewId.value = event.id || '';
      }
      if (eventReviewStatus) {
        const nextStatus = String(event.review_status || 'pending');
        eventReviewStatus.value = ['pending', 'accepted', 'rejected'].includes(nextStatus) ? nextStatus : 'pending';
      }
      document.querySelectorAll('[data-event-report-id]').forEach((input) => {
        input.value = event.id || '';
      });

      eventBadges.innerHTML = '';
      const badges = [
        `score ${Number(event.score_total || 0)}/100`,
        event.blocked ? 'blocked' : 'alert-only',
        String(event.review_status || 'pending'),
        `x${Math.max(1, Number(event.duplicate_count || 1))}`,
        String(event.event_type || 'clickfix_alert')
      ];
      if (Boolean(event.host_blocked_before)) {
        badges.push(`domain_blocked x${Number(event.host_blocked_count || 0)}`);
      }
      if (Boolean(event.ip_blocked_before)) {
        badges.push(`ip_blocked x${Number(event.ip_blocked_count || 0)}`);
      }
      badges.forEach((label) => {
        const chip = document.createElement('span');
        chip.className = 'event-chip';
        chip.textContent = label;
        eventBadges.appendChild(chip);
      });

      eventReasons.innerHTML = '';
      const reasonList = Array.isArray(event.reason_list) && event.reason_list.length
        ? event.reason_list
        : [event.message || 'Sin motivo clasificado'];
      reasonList.forEach((reason) => {
        const li = document.createElement('li');
        li.textContent = String(reason);
        eventReasons.appendChild(li);
      });

      eventSnippets.innerHTML = '';
      const snippets = Array.isArray(event.snippets) ? event.snippets.filter(Boolean) : [];
      if (snippets.length) {
        snippets.forEach((snippet) => {
          const div = document.createElement('div');
          div.className = 'event-snippet';
          div.innerHTML = boldMatchedSnippets(String(snippet), [snippet]);
          eventSnippets.appendChild(div);
        });
      } else {
        const div = document.createElement('div');
        div.className = 'event-empty';
        div.textContent = 'Sin snippets almacenados.';
        eventSnippets.appendChild(div);
      }

      if (eventSignals) {
        eventSignals.innerHTML = '';
        const signals = Array.isArray(event.signals) ? event.signals.filter(Boolean) : [];
        if (signals.length) {
          signals.forEach((signal) => {
            const li = document.createElement('li');
            li.textContent = String(signal);
            eventSignals.appendChild(li);
          });
        } else {
          const li = document.createElement('li');
          li.className = 'event-empty';
          li.textContent = 'Sin signals capturados.';
          eventSignals.appendChild(li);
        }
      }

      if (eventScoreDetails) {
        eventScoreDetails.innerHTML = '';
        const details = event.score_details;
        if (details && typeof details === 'object') {
          const pre = document.createElement('pre');
          pre.className = 'event-snippet';
          pre.textContent = JSON.stringify(details, null, 2);
          eventScoreDetails.appendChild(pre);
        } else if (typeof details === 'string' && details.trim() !== '') {
          const pre = document.createElement('pre');
          pre.className = 'event-snippet';
          pre.textContent = details;
          eventScoreDetails.appendChild(pre);
        } else {
          const div = document.createElement('div');
          div.className = 'event-empty';
          div.textContent = 'Sin detalle de score.';
          eventScoreDetails.appendChild(div);
        }
      }

      const fullContextText = String(event.full_context || '');
      const detectedContextText = String(event.detected_content || '');
      if (canViewExactEventContext) {
        if (eventContextTitle) {
          eventContextTitle.textContent = 'Contexto completo de pagina';
        }
        const contextText = fullContextText || detectedContextText;
        eventContext.textContent = contextText || 'Sin contexto capturado.';
      } else {
        if (eventContextTitle) {
          eventContextTitle.textContent = 'Contexto resaltado';
        }
        const contextText = detectedContextText || fullContextText;
        if (contextText) {
          eventContext.innerHTML = buildHighlightedContext(contextText, snippets);
        } else {
          eventContext.textContent = 'Sin contexto capturado.';
        }
      }

      const rawPayload = {
        id: event.id,
        received_at: event.received_at,
        last_seen: event.last_seen,
        activity_at: event.activity_at,
        hostname: event.hostname,
        url: event.url,
        previous_url: event.previous_url,
        message: event.message,
        detected_content: event.detected_content,
        full_context: canViewExactEventContext ? event.full_context : undefined,
        score_total: event.score_total,
        event_type: event.event_type,
        ip: event.ip,
        extension_version: event.extension_version,
        host_blocked_before: event.host_blocked_before,
        host_blocked_count: event.host_blocked_count,
        host_total_count: event.host_total_count,
        host_last_blocked_at: event.host_last_blocked_at,
        ip_blocked_before: event.ip_blocked_before,
        ip_blocked_count: event.ip_blocked_count,
        ip_total_count: event.ip_total_count,
        ip_last_blocked_at: event.ip_last_blocked_at,
        review_status: event.review_status,
        blocked: event.blocked,
        duplicate_count: event.duplicate_count,
        reasons: event.reason_list,
        snippets: event.snippets,
        signals: event.signals,
        score_details: event.score_details,
        download_ioc: event.download_ioc
      };
      eventRaw.textContent = JSON.stringify(rawPayload, null, 2);

      const severity = eventSeverity(event.score_total);
      eventTitle.dataset.severity = severity;

      if (eventRelatedLoad) {
        eventRelatedLoad.disabled = false;
        eventRelatedLoad.dataset.reportId = String(event.id || '');
      }
      if (eventRelatedStatus) {
        eventRelatedStatus.textContent = 'No se cargan automaticamente. Pulsa "Ver relacionadas" para consultar historial relacionado.';
      }
      if (eventRelatedBody) {
        eventRelatedBody.innerHTML = '';
      }
      if (eventRelatedWrap) {
        eventRelatedWrap.hidden = true;
      }
      updateEventMitre(event);
    }

    if (eventReviewForm) {
      eventReviewForm.addEventListener('submit', (ev) => {
        const reportId = Number(eventReviewId?.value || 0);
        if (!Number.isFinite(reportId) || reportId <= 0) {
          ev.preventDefault();
          alert('Selecciona un evento valido antes de actualizar la revision.');
        }
      });
    }
    eventQuickForms.forEach((form) => {
      form.addEventListener('submit', (ev) => {
        const idInput = form.querySelector('[data-event-report-id]');
        const reportId = Number(idInput?.value || 0);
        if (!Number.isFinite(reportId) || reportId <= 0) {
          ev.preventDefault();
          alert('Selecciona un evento valido antes de ejecutar la accion.');
        }
      });
    });

    if (eventRelatedLoad) {
      eventRelatedLoad.addEventListener('click', async () => {
        const reportId = Number(eventRelatedLoad.dataset.reportId || 0);
        if (!Number.isFinite(reportId) || reportId <= 0) {
          if (eventRelatedStatus) {
            eventRelatedStatus.textContent = 'Selecciona primero un evento valido.';
          }
          return;
        }
        if (eventRelatedStatus) {
          eventRelatedStatus.textContent = 'Cargando alertas relacionadas...';
        }
        eventRelatedLoad.disabled = true;
        try {
          if (!relatedEventsCache.has(reportId)) {
            const rows = await loadRelatedReports(reportId);
            relatedEventsCache.set(reportId, rows);
          }
          const rows = relatedEventsCache.get(reportId) || [];
          renderRelatedRows(rows);
          if (eventRelatedStatus) {
            eventRelatedStatus.textContent = `Relacionadas encontradas: ${Array.isArray(rows) ? rows.length : 0}.`;
          }
        } catch (error) {
          if (eventRelatedStatus) {
            eventRelatedStatus.textContent = `No se pudieron cargar las relacionadas (${String(error?.message || 'error')}).`;
          }
          if (eventRelatedBody) {
            eventRelatedBody.innerHTML = '';
          }
          if (eventRelatedWrap) {
            eventRelatedWrap.hidden = true;
          }
        } finally {
          eventRelatedLoad.disabled = false;
        }
      });
    }

    updateInvestigationMitre();
    updateSharedInvestigationMitre();
    updateSourceAlertMitre();
    if (eventFeed && eventWorkbenchData.length) {
      eventFeed.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof Element)) {
          return;
        }
        const btn = target.closest('.event-feed-item');
        if (!btn || !eventFeed.contains(btn)) {
          return;
        }
        renderEventDetail(Number(btn.dataset.eventIndex));
      });
      const preferredIndex = focusReportId > 0
        ? eventWorkbenchData.findIndex((entry) => Number(entry?.id || 0) === Number(focusReportId))
        : -1;
      renderEventDetail(preferredIndex >= 0 ? preferredIndex : 0);
    }

    const focusShell = document.querySelector('.intel-selector-shell[data-intel-focus="1"]');
    if (focusShell) {
      const tabButtons = Array.from(focusShell.querySelectorAll('[data-intel-tab]'));
      const panels = Array.from(focusShell.querySelectorAll('[data-intel-panel]'));
      const searchInput = focusShell.querySelector('#intel-focus-search');
      const cards = Array.from(focusShell.querySelectorAll('.intel-focus-card'));

      const activateTab = (tabId) => {
        tabButtons.forEach((btn) => {
          const isActive = btn.dataset.intelTab === tabId;
          btn.classList.toggle('is-active', isActive);
          btn.setAttribute('aria-selected', isActive ? 'true' : 'false');
        });
        panels.forEach((panel) => {
          panel.hidden = panel.dataset.intelPanel !== tabId;
        });
      };

      tabButtons.forEach((btn) => {
        btn.addEventListener('click', () => {
          activateTab(btn.dataset.intelTab || 'investigations');
        });
      });

      if (searchInput) {
        const runFilter = () => {
          const query = String(searchInput.value || '').trim().toLowerCase();
          cards.forEach((card) => {
            const haystack = String(card.dataset.search || '');
            const match = !query || haystack.includes(query);
            card.classList.toggle('is-hidden', !match);
          });
        };
        searchInput.addEventListener('input', runFilter);
      }
    }

    const intelWrap = document.getElementById('intel-canvas-wrap');
    const intelSvg = document.getElementById('intel-svg');
    const intelNodeLayer = document.getElementById('intel-node-layer');
    const intelLayoutMode = document.getElementById('intel-layout-mode');
    const intelLayoutApply = document.getElementById('intel-layout-apply');
    const intelFitGraph = document.getElementById('intel-fit-graph');
    const intelZoomIn = document.getElementById('intel-zoom-in');
    const intelZoomOut = document.getElementById('intel-zoom-out');
    const intelZoomReset = document.getElementById('intel-zoom-reset');
    const intelZoomStatus = document.getElementById('intel-zoom-status');
    const intelFullscreen = document.getElementById('intel-fullscreen');
    const intelWorkspaceFullscreen = document.getElementById('intel-workspace-fullscreen');
    const intelLayoutCycle = document.getElementById('intel-layout-cycle');
    const intelDockFit = document.getElementById('intel-dock-fit');
    const intelDockZoomIn = document.getElementById('intel-dock-zoom-in');
    const intelDockZoomOut = document.getElementById('intel-dock-zoom-out');
    const intelDockZoomReset = document.getElementById('intel-dock-zoom-reset');
    const intelDockZoomStatus = document.getElementById('intel-dock-zoom-status');
    const intelDockFullscreen = document.getElementById('intel-dock-fullscreen');
    const intelGraphJsonInput = document.getElementById('intel-graph-json');
    const intelSaveForm = document.getElementById('intel-save-form');
    const nodeLabelInput = document.getElementById('node-label');
    const nodeColorInput = document.getElementById('node-color');
    const nodeTagsInput = document.getElementById('node-tags');
    const nodeNotesInput = document.getElementById('node-notes');
    const nodeAddButton = document.getElementById('node-add');
    const nodeUpdateButton = document.getElementById('node-update');
    const nodeDeleteButton = document.getElementById('node-delete');
    const nodeListSelect = document.getElementById('node-list');
    const nodePreviewLabel = document.getElementById('node-preview-label');
    const nodePreviewTags = document.getElementById('node-preview-tags');
    const nodePreviewNotes = document.getElementById('node-preview-notes');
    const edgeFromSelect = document.getElementById('edge-from');
    const edgeToSelect = document.getElementById('edge-to');
    const edgeLabelInput = document.getElementById('edge-label');
    const edgeColorInput = document.getElementById('edge-color');
    const edgeAddButton = document.getElementById('edge-add');
    const edgeListSelect = document.getElementById('edge-list');
    const edgeDeleteButton = document.getElementById('edge-delete');
    const intelApiMapMeta = document.getElementById('intel-api-map-meta');
    const intelApiKeywordsWrap = document.getElementById('intel-api-keywords');

    function tagsFromInput(value) {
      return String(value || '')
        .split(',')
        .map((item) => item.trim())
        .filter(Boolean)
        .slice(0, 20);
    }

    function makeNodeId() {
      return `n_${Date.now().toString(36)}_${Math.random().toString(16).slice(2, 7)}`;
    }

    function makeEdgeId() {
      return `e_${Date.now().toString(36)}_${Math.random().toString(16).slice(2, 7)}`;
    }

    function tinyHash(value) {
      const raw = String(value || '');
      if (!raw) return '0';
      let hash = 0;
      for (let i = 0; i < raw.length; i += 1) {
        hash = ((hash << 5) - hash) + raw.charCodeAt(i);
        hash |= 0;
      }
      return Math.abs(hash).toString(16);
    }

    function makeStableGraphId(prefix, rawValue) {
      const clean = String(rawValue || '')
        .toLowerCase()
        .replace(/[^a-z0-9._-]+/g, '_')
        .replace(/^_+|_+$/g, '')
        .slice(0, 34) || 'item';
      return `${prefix}_${clean}_${tinyHash(rawValue).slice(0, 6)}`;
    }

    function normalizeDomainValue(value) {
      const raw = String(value || '').trim();
      if (!raw) return '';
      try {
        const maybeUrl = /^https?:\/\//i.test(raw) ? new URL(raw) : null;
        if (maybeUrl) {
          return String(maybeUrl.hostname || '').toLowerCase().replace(/^www\./, '');
        }
      } catch (error) {
        // Ignore URL parse errors.
      }
      if (/^\d{1,3}(?:\.\d{1,3}){3}$/.test(raw)) {
        return '';
      }
      return raw.toLowerCase().replace(/^www\./, '').replace(/:\d+$/, '');
    }

    function extractCountryCode(label) {
      const raw = String(label || '').trim();
      if (!raw) return '';
      const match = raw.match(/\(([A-Z]{2})\)/);
      if (match) return match[1];
      if (/^[A-Z]{2}$/.test(raw)) return raw;
      return '';
    }

    function countryCodeToFlag(code) {
      const safe = String(code || '').trim().toUpperCase();
      if (!/^[A-Z]{2}$/.test(safe)) return '';
      const base = 0x1f1e6;
      const first = safe.charCodeAt(0) - 65;
      const second = safe.charCodeAt(1) - 65;
      if (first < 0 || first > 25 || second < 0 || second > 25) return '';
      return String.fromCodePoint(base + first, base + second);
    }

    function flagForNode(node) {
      const label = String(node?.label || '');
      const tags = Array.isArray(node?.tags) ? node.tags.map((t) => String(t).toLowerCase()) : [];
      const code = extractCountryCode(label);
      if (!code) return '';
      if (!tags.includes('geo') && !/\([A-Z]{2}\)/.test(label) && !/^[A-Z]{2}$/.test(label.trim())) {
        return '';
      }
      return countryCodeToFlag(code);
    }

    function detectNodeKind(node) {
      const label = String(node?.label || '');
      const tags = Array.isArray(node?.tags) ? node.tags.map((t) => String(t).toLowerCase()) : [];
      if (tags.includes('api-provider') || tags.includes('source') || tags.includes('origin') || tags.includes('alert')) {
        return { key: 'source', label: 'fuente' };
      }
      if (label.trim().startsWith('#') || tags.includes('keyword')) {
        return { key: 'hash', label: '#' };
      }
      const isSha256 = /^[a-f0-9]{64}$/i.test(label.trim());
      const isUrl = /^https?:\/\//i.test(label.trim());
      const isIp = /^\d{1,3}(?:\.\d{1,3}){3}$/.test(label.trim());
      const isDomain = !!normalizeDomainValue(label);
      if (isSha256 || isUrl || isIp || isDomain || tags.includes('ioc') || tags.includes('domain') || tags.includes('ip') || tags.includes('url')) {
        return { key: 'ioc', label: 'ioc' };
      }
      if (tags.includes('artifact') || tags.includes('evidence') || tags.includes('snippet') || tags.includes('file') || tags.includes('download')) {
        return { key: 'artifact', label: 'artefacto' };
      }
      return { key: '', label: '' };
    }

    function normalizeLookupKey(type, value) {
      const raw = String(value || '').trim();
      if (!raw) return '';
      if (type === 'domain') {
        const domain = normalizeDomainValue(raw);
        return domain ? `domain:${domain}` : '';
      }
      if (type === 'ip') {
        return /^\d{1,3}(?:\.\d{1,3}){3}$/.test(raw) ? `ip:${raw}` : '';
      }
      if (type === 'url') {
        try {
          const parsed = new URL(raw);
          return `url:${parsed.toString()}`;
        } catch (error) {
          return '';
        }
      }
      if (type === 'hash') {
        return /^[a-f0-9]{64}$/i.test(raw) ? `hash:${raw.toLowerCase()}` : '';
      }
      return '';
    }

    function buildVtLookupIndex(lookupRows) {
      const index = new Map();
      const rows = Array.isArray(lookupRows) ? lookupRows : [];
      rows.forEach((row) => {
        if (!row || String(row.provider || '').toLowerCase() !== 'virustotal') {
          return;
        }
        const target = String(row.target || '');
        const targetType = String(row.target_type || '');
        const meta = parseLookupTargetMeta(target, targetType);
        let key = '';
        if (/^[a-f0-9]{64}$/i.test(target.trim()) || targetType === 'sha256') {
          key = normalizeLookupKey('hash', target.trim());
        } else {
          key = normalizeLookupKey(meta.type, meta.display || target);
        }
        if (!key) return;
        index.set(key, row);
      });
      return index;
    }

    function lookupVtForNode(node, vtIndex) {
      if (!vtIndex || !(vtIndex instanceof Map)) return null;
      const label = String(node?.label || '').trim();
      const tags = Array.isArray(node?.tags) ? node.tags.map((t) => String(t).toLowerCase()) : [];
      let key = '';
      if (/^[a-f0-9]{64}$/i.test(label)) {
        key = normalizeLookupKey('hash', label);
      } else if (tags.includes('url') || /^https?:\/\//i.test(label)) {
        key = normalizeLookupKey('url', label);
      } else if (tags.includes('ip') || /^\d{1,3}(?:\.\d{1,3}){3}$/.test(label)) {
        key = normalizeLookupKey('ip', label);
      } else {
        key = normalizeLookupKey('domain', label);
      }
      if (!key) return null;
      return vtIndex.get(key) || null;
    }

    function parseLookupTargetMeta(target, targetType) {
      const raw = String(target || '').trim();
      const declaredType = String(targetType || '').toLowerCase();
      let type = declaredType || 'unknown';
      let display = raw;
      let domain = '';
      if (/^\d{1,3}(?:\.\d{1,3}){3}$/.test(raw)) {
        type = 'ip';
      } else if (/^https?:\/\//i.test(raw)) {
        type = 'url';
        try {
          const parsed = new URL(raw);
          domain = normalizeDomainValue(parsed.hostname);
          display = parsed.toString();
        } catch (error) {
          // Keep raw value.
        }
      } else {
        const normalizedDomain = normalizeDomainValue(raw);
        if (normalizedDomain) {
          type = 'domain';
          domain = normalizedDomain;
          display = normalizedDomain;
        }
      }
      if (!domain && type === 'domain') {
        domain = normalizeDomainValue(raw);
      }
      return { type, display, domain };
    }

    function providerColor(provider) {
      const key = String(provider || '').toLowerCase();
      if (key === 'virustotal') return '#ff9f43';
      if (key === 'abuseipdb') return '#ff6b6b';
      if (key === 'urlscan') return '#4fd1c5';
      if (key === 'threatrip') return '#a78bfa';
      return '#6cb6ff';
    }

    function ensureGraphNode(graph, payload) {
      const existing = graph.nodes.find((node) => String(node.id) === String(payload.id));
      if (existing) {
        return existing;
      }
      const node = {
        id: String(payload.id),
        label: String(payload.label || 'node').slice(0, 120),
        color: /^#[0-9a-fA-F]{6}$/.test(String(payload.color || '')) ? String(payload.color) : '#5dc8ff',
        x: Number.isFinite(Number(payload.x)) ? Number(payload.x) : 120,
        y: Number.isFinite(Number(payload.y)) ? Number(payload.y) : 120,
        tags: Array.isArray(payload.tags) ? payload.tags.map((tag) => String(tag).slice(0, 40)).filter(Boolean) : [],
        notes: String(payload.notes || '').slice(0, 400)
      };
      graph.nodes.push(node);
      return node;
    }

    function ensureGraphEdge(graph, payload) {
      const from = String(payload.from || '');
      const to = String(payload.to || '');
      const label = String(payload.label || '').slice(0, 120);
      if (!from || !to || from === to) return;
      const dup = graph.edges.some((edge) =>
        String(edge.from) === from && String(edge.to) === to && String(edge.label || '') === label
      );
      if (dup) return;
      graph.edges.push({
        id: String(payload.id || makeEdgeId()),
        from,
        to,
        label,
        color: /^#[0-9a-fA-F]{6}$/.test(String(payload.color || '')) ? String(payload.color) : '#94a3b8'
      });
    }

    function summarizeLookup(lookup, meta) {
      const provider = String(lookup?.provider || 'unknown');
      const status = Number(lookup?.status || 0);
      const createdAt = String(lookup?.created_at || '');
      const summary = (lookup && typeof lookup.summary === 'object' && lookup.summary) ? lookup.summary : {};
      const details = (lookup && typeof lookup.details === 'object' && lookup.details) ? lookup.details : {};
      const summaryParts = Object.entries(summary)
        .slice(0, 6)
        .map(([key, value]) => `${key}: ${String(value)}`);
      const lines = [
        `provider=${provider}`,
        `target=${String(meta.display || lookup?.target || '')}`,
        `type=${String(meta.type || lookup?.target_type || 'unknown')}`,
        `status=${status}`,
      ];
      if (createdAt) {
        lines.push(`at=${createdAt}`);
      }
      if (summaryParts.length) {
        lines.push(summaryParts.join(' | '));
      }
      const detailParts = [];
      if (details.related_ip) detailParts.push(`ip=${String(details.related_ip)}`);
      if (details.related_domain) detailParts.push(`domain=${String(details.related_domain)}`);
      if (details.country_code || details.country_name) {
        detailParts.push(`country=${String(details.country_name || details.country_code)}`);
      }
      if (details.abuse_score) detailParts.push(`abuse_score=${Number(details.abuse_score)}`);
      if (details.total_reports) detailParts.push(`reports=${Number(details.total_reports)}`);
      if (details.vt_reputation) detailParts.push(`vt_reputation=${Number(details.vt_reputation)}`);
      if (details.vt_registrar) detailParts.push(`registrar=${String(details.vt_registrar)}`);
      if (details.vt_cert_issuer) detailParts.push(`issuer=${String(details.vt_cert_issuer)}`);
      if (Array.isArray(details.vt_malicious_labels) && details.vt_malicious_labels.length) {
        detailParts.push(`labels=${details.vt_malicious_labels.slice(0, 4).join(',')}`);
      }
      if (Array.isArray(details.vt_malicious_engines) && details.vt_malicious_engines.length) {
        detailParts.push(`engines=${details.vt_malicious_engines.slice(0, 4).join(',')}`);
      }
      if (detailParts.length) {
        lines.push(detailParts.join(' | '));
      }
      return lines.join('\n').slice(0, 390);
    }

    function renderIntelApiInsights(lookupRows, keywordRows) {
      const lookups = Array.isArray(lookupRows) ? lookupRows : [];
      const keywords = Array.isArray(keywordRows) ? keywordRows : [];
      if (intelApiMapMeta) {
        if (!lookups.length) {
          intelApiMapMeta.textContent = 'Sin consultas recientes de proveedores para esta investigacion.';
        } else {
          const providers = {};
          let highRisk = 0;
          lookups.forEach((row) => {
            const provider = String(row?.provider || 'unknown').toLowerCase();
            providers[provider] = (providers[provider] || 0) + 1;
            const mal = Number(row?.summary?.malicious || 0);
            const sus = Number(row?.summary?.suspicious || 0);
            const abuse = Number(row?.details?.abuse_score || 0);
            if (mal > 0 || sus > 0 || abuse >= 40) {
              highRisk += 1;
            }
          });
          const providerLabel = Object.entries(providers)
            .sort((a, b) => b[1] - a[1])
            .slice(0, 3)
            .map(([provider, hits]) => `${provider}:${hits}`)
            .join(' | ');
          intelApiMapMeta.textContent = `${lookups.length} consultas | high-risk:${highRisk} | ${keywords.length} keywords | ${providerLabel || 'sin proveedor'}`;
        }
      }
      if (!intelApiKeywordsWrap) return;
      intelApiKeywordsWrap.innerHTML = '';
      if (!keywords.length) {
        const empty = document.createElement('span');
        empty.className = 'mut';
        empty.textContent = lookups.length ? 'No hay keywords frecuentes en los resultados de proveedores.' : 'Sin keywords comunes detectadas.';
        intelApiKeywordsWrap.appendChild(empty);
        return;
      }
      keywords.slice(0, 12).forEach((row) => {
        const chip = document.createElement('span');
        chip.className = 'intel-api-keyword-chip';
        const keywordLabel = String(row?.keyword || '-').replace(/_/g, ' ');
        chip.textContent = keywordLabel;
        const hits = document.createElement('b');
        hits.textContent = `x${Number(row?.hits || 0)}`;
        chip.appendChild(hits);
        intelApiKeywordsWrap.appendChild(chip);
      });
    }

    function enrichGraphWithApiResults(baseGraph, lookupRows, keywordRows) {
      const graph = normalizeGraphPayload(baseGraph || { nodes: [], edges: [] });
      const lookups = Array.isArray(lookupRows) ? lookupRows.filter((row) => row && typeof row === 'object') : [];
      const keywords = Array.isArray(keywordRows) ? keywordRows.filter((row) => row && typeof row === 'object') : [];
      if (!lookups.length) {
        return graph;
      }

      const investigationDomain = normalizeDomainValue(
        selectedInvestigation?.site_domain || selectedInvestigation?.hostname || selectedInvestigation?.title || ''
      );
      let rootNode = graph.nodes.find((node) => {
        const labelDomain = normalizeDomainValue(node?.label || '');
        const tags = Array.isArray(node?.tags) ? node.tags : [];
        return (investigationDomain && labelDomain === investigationDomain)
          || tags.some((tag) => normalizeDomainValue(tag) === investigationDomain);
      }) || null;

      if (!rootNode) {
        const centerX = Number(intelWrap?.clientWidth || 920) * 0.5;
        const centerY = Number(intelWrap?.clientHeight || 470) * 0.46;
        rootNode = ensureGraphNode(graph, {
          id: makeStableGraphId('root', investigationDomain || selectedInvestigation?.title || 'investigation'),
          label: investigationDomain || String(selectedInvestigation?.title || 'Investigacion'),
          color: '#5dc8ff',
          x: Math.round(centerX),
          y: Math.round(centerY),
          tags: ['investigation', investigationDomain || 'scope'],
          notes: 'Nodo raiz de investigacion (contexto de proveedores)'
        });
      }

      const providerNodeMap = new Map();
      const baseRadius = 165;
      lookups.slice(0, 30).forEach((lookup, index) => {
        const provider = String(lookup?.provider || 'unknown').toLowerCase();
        const target = String(lookup?.target || '').trim();
        if (!target) return;
        const meta = parseLookupTargetMeta(target, lookup?.target_type || '');
        const angle = (index / Math.max(1, Math.min(lookups.length, 30))) * Math.PI * 2;
        const radius = baseRadius + (index % 4) * 20;
        const indicatorX = Math.round(Number(rootNode.x || 450) + Math.cos(angle) * radius);
        const indicatorY = Math.round(Number(rootNode.y || 235) + Math.sin(angle) * (radius * 0.65));

        const summary = (lookup && typeof lookup.summary === 'object' && lookup.summary) ? lookup.summary : {};
        const details = (lookup && typeof lookup.details === 'object' && lookup.details) ? lookup.details : {};
        const tags = ['api', provider, String(meta.type || 'unknown')];
        if (Number(summary.malicious || 0) > 0) tags.push('malicious');
        if (Number(summary.suspicious || 0) > 0) tags.push('suspicious');
        if (Number(summary.abuseConfidenceScore || 0) >= 40) tags.push('abuse-high');
        if (Number(details.abuse_score || 0) >= 40) tags.push('abuse-high');
        if (Number(details.vt_reputation || 0) < 0) tags.push('negative-reputation');
        if (Array.isArray(details.vt_malicious_labels)) {
          details.vt_malicious_labels.slice(0, 2).forEach((label) => {
            const normalized = String(label || '').trim().toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
            if (normalized) {
              tags.push(`label:${normalized}`);
            }
          });
        }
        if (!lookup?.ok) tags.push('error');

        const indicatorNode = ensureGraphNode(graph, {
          id: makeStableGraphId('api_i', `${provider}:${meta.display || target}`),
          label: String(meta.display || target).slice(0, 72),
          color: providerColor(provider),
          x: indicatorX,
          y: indicatorY,
          tags,
          notes: summarizeLookup(lookup, meta)
        });

        ensureGraphEdge(graph, {
          id: makeStableGraphId('api_e', `${rootNode.id}>${indicatorNode.id}>lookup`),
          from: rootNode.id,
          to: indicatorNode.id,
          label: `lookup:${provider}`,
          color: '#7eb6de'
        });

        if (!providerNodeMap.has(provider)) {
          const providerIndex = providerNodeMap.size;
          const providerNode = ensureGraphNode(graph, {
            id: makeStableGraphId('api_p', provider),
            label: provider,
            color: providerColor(provider),
            x: Math.round(Number(rootNode.x || 450) - 210 + providerIndex * 95),
            y: Math.round(Number(rootNode.y || 235) - 150),
            tags: ['api-provider', provider],
            notes: `Proveedor: ${provider}`
          });
          providerNodeMap.set(provider, providerNode);
        }
        const providerNode = providerNodeMap.get(provider);
        if (providerNode) {
          ensureGraphEdge(graph, {
            id: makeStableGraphId('api_ep', `${providerNode.id}>${indicatorNode.id}`),
            from: providerNode.id,
            to: indicatorNode.id,
            label: `status:${Number(lookup?.status || 0)}`,
            color: '#8ba9be'
          });
        }

        if (meta.domain && meta.domain !== investigationDomain) {
          const domainNode = ensureGraphNode(graph, {
            id: makeStableGraphId('api_d', meta.domain),
            label: meta.domain,
            color: '#7ab8ff',
            x: Math.round(indicatorX + 54),
            y: Math.round(indicatorY + 42),
            tags: ['domain', 'resolved-domain'],
            notes: `Dominio asociado por consulta de proveedor: ${meta.domain}`
          });
          ensureGraphEdge(graph, {
            id: makeStableGraphId('api_ed', `${indicatorNode.id}>${domainNode.id}`),
            from: indicatorNode.id,
            to: domainNode.id,
            label: 'resolved-domain',
            color: '#7fa4c2'
          });
        }

        const detailDomain = normalizeDomainValue(String(details.related_domain || ''));
        if (detailDomain && detailDomain !== investigationDomain && detailDomain !== meta.domain) {
          const relatedDomainNode = ensureGraphNode(graph, {
            id: makeStableGraphId('api_rd', detailDomain),
            label: detailDomain,
            color: '#82c2ff',
            x: Math.round(indicatorX - 56),
            y: Math.round(indicatorY + 54),
            tags: ['domain', 'api-derived'],
            notes: `Dominio derivado de ${provider}`
          });
          ensureGraphEdge(graph, {
            id: makeStableGraphId('api_erd', `${indicatorNode.id}>${relatedDomainNode.id}`),
            from: indicatorNode.id,
            to: relatedDomainNode.id,
            label: 'related-domain',
            color: '#7fa4c2'
          });
        }

        const detailIp = String(details.related_ip || '').trim();
        if (/^\d{1,3}(?:\.\d{1,3}){3}$/.test(detailIp) && detailIp !== target) {
          const ipNode = ensureGraphNode(graph, {
            id: makeStableGraphId('api_ip', detailIp),
            label: detailIp,
            color: '#9aa8ff',
            x: Math.round(indicatorX + 72),
            y: Math.round(indicatorY - 48),
            tags: ['ip', 'api-derived'],
            notes: `IP relacionada por ${provider}`
          });
          ensureGraphEdge(graph, {
            id: makeStableGraphId('api_eip', `${indicatorNode.id}>${ipNode.id}`),
            from: indicatorNode.id,
            to: ipNode.id,
            label: 'related-ip',
            color: '#8b95cd'
          });
        }

        const countryCode = String(details.country_code || '').trim().toUpperCase();
        const countryName = String(details.country_name || '').trim();
        if (countryCode || countryName) {
          const countryLabel = countryName ? `${countryName}${countryCode ? ` (${countryCode})` : ''}` : countryCode;
          const countryNode = ensureGraphNode(graph, {
            id: makeStableGraphId('api_cc', countryLabel),
            label: countryLabel,
            color: '#7ac6bf',
            x: Math.round(indicatorX - 78),
            y: Math.round(indicatorY - 52),
            tags: ['geo', 'api-derived'],
            notes: `Geolocalizacion detectada por ${provider}`
          });
          ensureGraphEdge(graph, {
            id: makeStableGraphId('api_ec', `${indicatorNode.id}>${countryNode.id}`),
            from: indicatorNode.id,
            to: countryNode.id,
            label: 'country',
            color: '#7ea4b1'
          });
        }

        const hostnames = Array.isArray(details.hostnames) ? details.hostnames : [];
        hostnames.slice(0, 3).forEach((hostname, hostIndex) => {
          const hostLabel = normalizeDomainValue(String(hostname || ''));
          if (!hostLabel) return;
          const hostNode = ensureGraphNode(graph, {
            id: makeStableGraphId('api_hn', hostLabel),
            label: hostLabel,
            color: '#95b7ff',
            x: Math.round(indicatorX + 36 + hostIndex * 24),
            y: Math.round(indicatorY + 78 + hostIndex * 18),
            tags: ['hostname', 'api-derived'],
            notes: `Hostname asociado (${provider})`
          });
          ensureGraphEdge(graph, {
            id: makeStableGraphId('api_eh', `${indicatorNode.id}>${hostNode.id}`),
            from: indicatorNode.id,
            to: hostNode.id,
            label: 'hostname',
            color: '#8ba9be'
          });
        });
      });

      if (keywords.length) {
        const keywordHub = ensureGraphNode(graph, {
          id: makeStableGraphId('api_kw', `${rootNode.id}:hub`),
          label: 'api-keywords',
          color: '#7ac6bf',
          x: Math.round(Number(rootNode.x || 450)),
          y: Math.round(Number(rootNode.y || 235) + 170),
          tags: ['keywords', 'api'],
          notes: 'Keywords comunes detectadas en resultados de proveedores'
        });
        ensureGraphEdge(graph, {
          id: makeStableGraphId('api_kw_e', `${rootNode.id}>${keywordHub.id}`),
          from: rootNode.id,
          to: keywordHub.id,
          label: 'api-keywords',
          color: '#7eb6de'
        });
        keywords.slice(0, 8).forEach((row, index) => {
          const keyword = String(row?.keyword || '').trim();
          if (!keyword) return;
          const hits = Number(row?.hits || 0);
          const angle = (index / Math.max(1, Math.min(keywords.length, 8))) * Math.PI * 2;
          const keywordNode = ensureGraphNode(graph, {
            id: makeStableGraphId('kw', keyword),
            label: `#${keyword}`.slice(0, 58),
            color: '#58bfa7',
            x: Math.round(Number(keywordHub.x || 450) + Math.cos(angle) * 130),
            y: Math.round(Number(keywordHub.y || 360) + Math.sin(angle) * 62),
            tags: ['keyword', 'api'],
            notes: `Apariciones en consultas de proveedores: ${hits}`
          });
          ensureGraphEdge(graph, {
            id: makeStableGraphId('kw_e', `${keywordHub.id}>${keywordNode.id}`),
            from: keywordHub.id,
            to: keywordNode.id,
            label: `hits:${hits}`,
            color: '#7fa4c2'
          });
        });
      }

      return graph;
    }

    if (intelWrap && intelSvg && intelNodeLayer) {
      renderIntelApiInsights(intelApiLookupMapRows, intelApiCommonKeywords);
      const baseGraph = selectedInvestigation?.graph || { nodes: [], edges: [] };
      const initialGraph = enrichGraphWithApiResults(baseGraph, intelApiLookupMapRows, intelApiCommonKeywords);
      const vtLookupIndex = buildVtLookupIndex(intelApiLookupMapRows);
      const editor = makeGraphRenderer({
        wrap: intelWrap,
        svg: intelSvg,
        nodeLayer: intelNodeLayer,
        graph: initialGraph,
        readOnly: false,
        nodeListSelect,
        vtLookupIndex,
        controls: {
          layoutSelect: intelLayoutMode,
          layoutApplyButton: intelLayoutApply,
          fitButton: intelFitGraph,
          zoomInButton: intelZoomIn,
          zoomOutButton: intelZoomOut,
          zoomResetButton: intelZoomReset,
          fullscreenButton: intelFullscreen,
          fullscreenButtonAlt: intelDockFullscreen || intelWorkspaceFullscreen,
          zoomStatus: intelZoomStatus,
          zoomStatusAlt: intelDockZoomStatus
        },
        onSelectNode(node) {
          if (!node) {
            if (nodeLabelInput) nodeLabelInput.value = '';
            if (nodeColorInput) nodeColorInput.value = '#5dc8ff';
            if (nodeTagsInput) nodeTagsInput.value = '';
            if (nodeNotesInput) nodeNotesInput.value = '';
            if (nodePreviewLabel) nodePreviewLabel.textContent = 'Sin nodo seleccionado.';
            if (nodePreviewTags) nodePreviewTags.textContent = '';
            if (nodePreviewNotes) nodePreviewNotes.textContent = '';
            return;
          }
          if (nodeLabelInput) nodeLabelInput.value = node.label || '';
          if (nodeColorInput) nodeColorInput.value = /^#[0-9a-fA-F]{6}$/.test(String(node.color || '')) ? node.color : '#5dc8ff';
          if (nodeTagsInput) nodeTagsInput.value = Array.isArray(node.tags) ? node.tags.join(', ') : '';
          if (nodeNotesInput) nodeNotesInput.value = node.notes || '';
          if (nodePreviewLabel) nodePreviewLabel.textContent = `Nodo: ${String(node.label || '')} (${String(node.id || '')})`;
          if (nodePreviewTags) nodePreviewTags.textContent = `Tags: ${Array.isArray(node.tags) && node.tags.length ? node.tags.join(', ') : '-'}`;
          if (nodePreviewNotes) nodePreviewNotes.textContent = String(node.notes || '').trim() || 'Sin notas en este nodo.';
        },
        edgeListSelect,
        edgeFromSelect,
        edgeToSelect
      });

      nodeListSelect?.addEventListener('change', () => {
        if (!editor) return;
        const nodeId = String(nodeListSelect.value || '');
        editor.selectNode(nodeId);
      });

      nodeAddButton?.addEventListener('click', () => {
        if (!editor) return;
        const label = (nodeLabelInput?.value || '').trim() || 'node';
        const color = /^#[0-9a-fA-F]{6}$/.test(String(nodeColorInput?.value || '')) ? String(nodeColorInput?.value) : '#5dc8ff';
        const tags = tagsFromInput(nodeTagsInput?.value || '');
        const notes = (nodeNotesInput?.value || '').trim().slice(0, 400);
        const bounds = intelWrap.getBoundingClientRect();
        editor.addNode({
          id: makeNodeId(),
          label: label.slice(0, 120),
          color,
          x: Math.max(40, Math.min(bounds.width - 40, 80 + Math.random() * (bounds.width - 160))),
          y: Math.max(40, Math.min(bounds.height - 40, 80 + Math.random() * (bounds.height - 160))),
          tags,
          notes
        });
      });

      nodeUpdateButton?.addEventListener('click', () => {
        if (!editor) return;
        const ok = editor.updateSelectedNode({
          label: ((nodeLabelInput?.value || '').trim() || 'node').slice(0, 120),
          color: /^#[0-9a-fA-F]{6}$/.test(String(nodeColorInput?.value || '')) ? String(nodeColorInput?.value) : '#5dc8ff',
          tags: tagsFromInput(nodeTagsInput?.value || ''),
          notes: (nodeNotesInput?.value || '').trim().slice(0, 400)
        });
        if (!ok) {
          alert('Selecciona un nodo para actualizar.');
        }
      });

      nodeDeleteButton?.addEventListener('click', () => {
        if (!editor) return;
        const ok = editor.removeSelectedNode();
        if (!ok) {
          alert('Selecciona un nodo para eliminar.');
        }
      });

      edgeAddButton?.addEventListener('click', () => {
        if (!editor || !edgeFromSelect || !edgeToSelect) return;
        const from = edgeFromSelect.value;
        const to = edgeToSelect.value;
        if (!from || !to || from === to) {
          alert('Selecciona nodos origen y destino distintos.');
          return;
        }
        editor.addEdge({
          id: makeEdgeId(),
          from,
          to,
          label: (edgeLabelInput?.value || '').trim().slice(0, 120),
          color: /^#[0-9a-fA-F]{6}$/.test(String(edgeColorInput?.value || '')) ? String(edgeColorInput?.value) : '#94a3b8'
        });
      });

      edgeDeleteButton?.addEventListener('click', () => {
        if (!editor || !edgeListSelect) return;
        if (!edgeListSelect.value) {
          alert('Selecciona una conexion para eliminar.');
          return;
        }
        editor.removeEdge(edgeListSelect.value);
      });

      intelSaveForm?.addEventListener('submit', () => {
        if (!editor || !intelGraphJsonInput) return;
        intelGraphJsonInput.value = JSON.stringify(editor.getGraph());
      });

      const proxyClick = (targetButton, triggerButton) => {
        triggerButton?.addEventListener('click', () => {
          targetButton?.click();
        });
      };
      proxyClick(intelFitGraph, intelDockFit);
      proxyClick(intelZoomIn, intelDockZoomIn);
      proxyClick(intelZoomOut, intelDockZoomOut);
      proxyClick(intelZoomReset, intelDockZoomReset);
      proxyClick(intelFullscreen, intelWorkspaceFullscreen);

      intelLayoutCycle?.addEventListener('click', () => {
        if (!(intelLayoutMode instanceof HTMLSelectElement)) {
          return;
        }
        const optionCount = intelLayoutMode.options.length;
        if (!optionCount) {
          return;
        }
        intelLayoutMode.selectedIndex = (intelLayoutMode.selectedIndex + 1) % optionCount;
        intelLayoutApply?.click();
      });

      document.addEventListener('fullscreenchange', () => {
        if (!intelWorkspaceFullscreen || !intelWrap) {
          return;
        }
        const isActive = document.fullscreenElement === intelWrap;
        intelWorkspaceFullscreen.textContent = isActive ? 'Salir pantalla completa' : 'Pantalla completa';
      });
    }

    const sharedWrap = document.getElementById('shared-canvas-wrap');
    const sharedSvg = document.getElementById('shared-svg');
    const sharedNodeLayer = document.getElementById('shared-node-layer');
    const sharedLayoutMode = document.getElementById('shared-layout-mode');
    const sharedLayoutApply = document.getElementById('shared-layout-apply');
    const sharedFitGraph = document.getElementById('shared-fit-graph');
    const sharedZoomIn = document.getElementById('shared-zoom-in');
    const sharedZoomOut = document.getElementById('shared-zoom-out');
    const sharedZoomReset = document.getElementById('shared-zoom-reset');
    const sharedZoomStatus = document.getElementById('shared-zoom-status');
    const sharedFullscreen = document.getElementById('shared-fullscreen');
    const sharedNodeLabel = document.getElementById('shared-node-label');
    const sharedNodeTags = document.getElementById('shared-node-tags');
    const sharedNodeNotes = document.getElementById('shared-node-notes');
    if (sharedWrap && sharedSvg && sharedNodeLayer && sharedInvestigation?.graph) {
      const sharedVtIndex = buildVtLookupIndex(intelApiLookupMapRows);
      makeGraphRenderer({
        wrap: sharedWrap,
        svg: sharedSvg,
        nodeLayer: sharedNodeLayer,
        graph: sharedInvestigation.graph,
        readOnly: true,
        vtLookupIndex: sharedVtIndex,
        controls: {
          layoutSelect: sharedLayoutMode,
          layoutApplyButton: sharedLayoutApply,
          fitButton: sharedFitGraph,
          zoomInButton: sharedZoomIn,
          zoomOutButton: sharedZoomOut,
          zoomResetButton: sharedZoomReset,
          fullscreenButton: sharedFullscreen,
          zoomStatus: sharedZoomStatus
        },
        onSelectNode(node) {
          if (!node) {
            if (sharedNodeLabel) sharedNodeLabel.textContent = 'Sin nodo seleccionado.';
            if (sharedNodeTags) sharedNodeTags.textContent = '';
            if (sharedNodeNotes) sharedNodeNotes.textContent = '';
            return;
          }
          if (sharedNodeLabel) sharedNodeLabel.textContent = `Nodo: ${String(node.label || '')} (${String(node.id || '')})`;
          if (sharedNodeTags) sharedNodeTags.textContent = `Tags: ${Array.isArray(node.tags) && node.tags.length ? node.tags.join(', ') : '-'}`;
          if (sharedNodeNotes) sharedNodeNotes.textContent = String(node.notes || '').trim() || 'Sin notas en este nodo.';
        }
      });
    }

    const apiKeyToggleButtons = Array.from(document.querySelectorAll('[data-toggle-api-key]'));
    apiKeyToggleButtons.forEach((button) => {
      button.addEventListener('click', () => {
        const targetId = String(button.getAttribute('data-toggle-api-key') || '');
        if (!targetId) return;
        const input = document.getElementById(targetId);
        if (!(input instanceof HTMLInputElement)) return;
        const masked = String(input.dataset.apiKeyMasked || '');
        const plain = String(input.dataset.apiKeyPlain || '');
        const revealed = String(input.dataset.apiKeyRevealed || '0') === '1';
        if (revealed) {
          input.value = masked;
          input.dataset.apiKeyRevealed = '0';
          button.textContent = 'ver';
          return;
        }
        if (plain !== '') {
          input.value = plain;
          input.dataset.apiKeyRevealed = '1';
          button.textContent = 'ocultar';
          return;
        }
        const visible = input.type === 'text';
        input.type = visible ? 'password' : 'text';
        button.textContent = visible ? 'ver' : 'ocultar';
      });
    });

    const apiKeyForms = Array.from(document.querySelectorAll('.api-key-row-form'));
    apiKeyForms.forEach((form) => {
      form.addEventListener('submit', () => {
        const input = form.querySelector('input[name="api_key"]');
        if (!(input instanceof HTMLInputElement)) return;
        const masked = String(input.dataset.apiKeyMasked || '');
        const plain = String(input.dataset.apiKeyPlain || '');
        if (plain && masked && input.value.trim() === masked) {
          // Keep existing key if user submits without revealing/editing.
          input.value = plain;
        }
      });
    });

    const homeExtensionMapEl = document.getElementById('home-extension-map');
    const homeWebMapEl = document.getElementById('home-web-map');
    const homeExtensionBody = document.getElementById('home-extension-country-body');
    const homeWebBody = document.getElementById('home-web-body');
    const homeExtensionTotal = document.getElementById('home-extension-total');
    const homeExtensionCountries = document.getElementById('home-extension-countries');
    const homeWebCount = document.getElementById('home-web-count');
    const homeWebLast = document.getElementById('home-web-last');

    function setTableMessage(tableBodyEl, message, columns) {
      if (!tableBodyEl) return;
      tableBodyEl.innerHTML = '';
      const tr = document.createElement('tr');
      const td = document.createElement('td');
      td.colSpan = columns;
      td.className = 'mut';
      td.textContent = message;
      tr.appendChild(td);
      tableBodyEl.appendChild(tr);
    }

    function parseSortableValue(raw) {
      const text = String(raw || '').replace(/\s+/g, ' ').trim();
      if (!text) {
        return { type: 'empty', value: '' };
      }

      const maybeDate = Date.parse(text);
      if (
        Number.isFinite(maybeDate) &&
        /\d/.test(text) &&
        (text.includes('-') || text.includes('/') || text.includes(':') || text.includes('T'))
      ) {
        return { type: 'date', value: maybeDate };
      }

      let numericText = text
        .replace(/[%â‚¬$Â£Â¥]/g, '')
        .replace(/\(([^)]+)\)/g, '-$1')
        .replace(/[^\d,.\-]/g, '');
      if (/,/.test(numericText) && /\./.test(numericText)) {
        if (numericText.lastIndexOf(',') > numericText.lastIndexOf('.')) {
          numericText = numericText.replace(/\./g, '').replace(',', '.');
        } else {
          numericText = numericText.replace(/,/g, '');
        }
      } else if (/,/.test(numericText) && !/\./.test(numericText)) {
        const parts = numericText.split(',');
        if (parts.length === 2 && parts[1].length <= 2) {
          numericText = `${parts[0].replace(/,/g, '')}.${parts[1]}`;
        } else {
          numericText = numericText.replace(/,/g, '');
        }
      }
      const numericValue = Number(numericText);
      if (numericText && Number.isFinite(numericValue)) {
        return { type: 'number', value: numericValue };
      }

      return { type: 'text', value: text.toLocaleLowerCase() };
    }

    function compareSortableValues(a, b) {
      const rank = { number: 0, date: 1, text: 2, empty: 3 };
      const ra = Object.prototype.hasOwnProperty.call(rank, a.type) ? rank[a.type] : 9;
      const rb = Object.prototype.hasOwnProperty.call(rank, b.type) ? rank[b.type] : 9;
      if (ra !== rb) {
        return ra - rb;
      }
      if (a.type === 'number' || a.type === 'date') {
        return a.value - b.value;
      }
      if (a.type === 'empty') {
        return 0;
      }
      return String(a.value).localeCompare(String(b.value), undefined, { numeric: true, sensitivity: 'base' });
    }

    function sortTableByColumn(table, columnIndex, direction) {
      const tbody = table?.tBodies?.[0];
      if (!tbody) return;
      const rows = Array.from(tbody.rows || []);
      if (rows.length < 2) return;

      const prepared = rows.map((row, index) => {
        const cell = row.cells[columnIndex];
        const raw = cell?.dataset?.sortValue ?? cell?.textContent ?? '';
        return {
          row,
          index,
          parsed: parseSortableValue(raw)
        };
      });

      prepared.sort((left, right) => {
        const base = compareSortableValues(left.parsed, right.parsed);
        if (base !== 0) {
          return direction === 'desc' ? -base : base;
        }
        return left.index - right.index;
      });

      prepared.forEach((item) => tbody.appendChild(item.row));
    }

    function initSortableTables(root = document) {
      const tables = Array.from(root.querySelectorAll('table'));
      tables.forEach((table) => {
        const thead = table.tHead;
        const tbody = table.tBodies?.[0];
        if (!thead || !tbody || tbody.rows.length < 2) {
          return;
        }
        if (table.dataset.sortableReady === 'true') {
          return;
        }
        const headerRow = thead.rows[thead.rows.length - 1];
        if (!headerRow) {
          return;
        }
        const headers = Array.from(headerRow.cells || []);
        if (!headers.length) {
          return;
        }

        table.dataset.sortableReady = 'true';
        headers.forEach((th, columnIndex) => {
          if (th.dataset.sortable === 'false') {
            return;
          }
          th.classList.add('sortable');
          th.tabIndex = 0;
          th.setAttribute('role', 'button');
          th.dataset.sortDir = '';

          const triggerSort = () => {
            const nextDir = th.dataset.sortDir === 'asc' ? 'desc' : 'asc';
            headers.forEach((other) => {
              if (other === th) return;
              other.dataset.sortDir = '';
              other.classList.remove('sort-asc', 'sort-desc');
            });
            th.dataset.sortDir = nextDir;
            th.classList.toggle('sort-asc', nextDir === 'asc');
            th.classList.toggle('sort-desc', nextDir === 'desc');
            sortTableByColumn(table, columnIndex, nextDir);
          };

          th.addEventListener('click', triggerSort);
          th.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') {
              event.preventDefault();
              triggerSort();
            }
          });
        });
      });
    }

    function initBulkReviewActions() {
      const form = document.getElementById('bulk-review-form');
      if (!form) {
        return;
      }
      const checkboxes = Array.from(document.querySelectorAll('.bulk-review-checkbox'));
      const selectAll = document.getElementById('bulk-review-select-all');
      const selectPending = document.getElementById('bulk-review-select-pending');
      const clearButton = document.getElementById('bulk-review-clear');
      const submitButton = document.getElementById('bulk-review-submit');
      const countEl = document.getElementById('bulk-review-count');
      const statusSelect = document.getElementById('bulk-review-status');

      const updateState = () => {
        const selected = checkboxes.filter((checkbox) => checkbox.checked).length;
        if (countEl) {
          countEl.textContent = `${selected} seleccionadas`;
        }
        if (submitButton) {
          submitButton.disabled = selected <= 0;
        }
        if (selectAll) {
          const allSelected = checkboxes.length > 0 && selected === checkboxes.length;
          const hasAnySelected = selected > 0 && selected < checkboxes.length;
          selectAll.checked = allSelected;
          selectAll.indeterminate = hasAnySelected;
        }
      };

      checkboxes.forEach((checkbox) => {
        checkbox.addEventListener('change', updateState);
      });

      if (selectAll) {
        selectAll.addEventListener('change', () => {
          const checked = Boolean(selectAll.checked);
          checkboxes.forEach((checkbox) => {
            checkbox.checked = checked;
          });
          updateState();
        });
      }

      if (selectPending) {
        selectPending.addEventListener('click', () => {
          checkboxes.forEach((checkbox) => {
            checkbox.checked = String(checkbox.dataset.reviewStatus || 'pending') === 'pending';
          });
          updateState();
        });
      }

      if (clearButton) {
        clearButton.addEventListener('click', () => {
          checkboxes.forEach((checkbox) => {
            checkbox.checked = false;
          });
          updateState();
        });
      }

      form.addEventListener('submit', (event) => {
        const selected = checkboxes.filter((checkbox) => checkbox.checked).length;
        if (selected <= 0) {
          event.preventDefault();
          alert('Selecciona al menos una alerta para revision masiva.');
          return;
        }
        const status = String(statusSelect?.value || 'pending');
        if (!confirm(`Aplicar estado "${status}" a ${selected} alertas seleccionadas?`)) {
          event.preventDefault();
        }
      });

      updateState();
    }

    function normalizeUrlCandidate(value) {
      try {
        return new URL(String(value || ''), window.location.href).toString();
      } catch (error) {
        return String(value || '');
      }
    }

    function ensureStylesheetLoaded(href) {
      const normalizedHref = normalizeUrlCandidate(href);
      if (!normalizedHref) return;
      const links = Array.from(document.querySelectorAll('link[rel="stylesheet"][href]'));
      if (links.some((link) => normalizeUrlCandidate(link.getAttribute('href')) === normalizedHref)) {
        return;
      }
      const link = document.createElement('link');
      link.rel = 'stylesheet';
      link.href = normalizedHref;
      document.head.appendChild(link);
    }

    function loadScriptOnce(src) {
      const normalizedSrc = normalizeUrlCandidate(src);
      if (!normalizedSrc) return Promise.resolve(false);
      const scripts = Array.from(document.querySelectorAll('script[src]'));
      const existing = scripts.find((script) => normalizeUrlCandidate(script.getAttribute('src')) === normalizedSrc);
      if (existing) {
        if (window.L && typeof window.L.map === 'function') {
          return Promise.resolve(true);
        }
        return new Promise((resolve) => {
          existing.addEventListener('load', () => resolve(!!(window.L && typeof window.L.map === 'function')), { once: true });
          existing.addEventListener('error', () => resolve(false), { once: true });
          setTimeout(() => resolve(!!(window.L && typeof window.L.map === 'function')), 2500);
        });
      }
      return new Promise((resolve) => {
        const script = document.createElement('script');
        script.src = normalizedSrc;
        script.async = true;
        script.defer = true;
        script.addEventListener('load', () => resolve(!!(window.L && typeof window.L.map === 'function')), { once: true });
        script.addEventListener('error', () => resolve(false), { once: true });
        document.head.appendChild(script);
      });
    }

    async function ensureLeafletLoaded() {
      if (window.L && typeof window.L.map === 'function') {
        return true;
      }
      if (!leafletEnsurePromise) {
        leafletEnsurePromise = (async () => {
          const cssCandidates = [
            homeLeafletCssUrl,
            'assets/vendor/leaflet/leaflet.css',
            '/assets/vendor/leaflet/leaflet.css'
          ];
          cssCandidates.forEach((href) => ensureStylesheetLoaded(href));

          const jsCandidates = [
            homeLeafletJsUrl,
            'assets/vendor/leaflet/leaflet.js',
            '/assets/vendor/leaflet/leaflet.js'
          ];
          for (const src of jsCandidates) {
            if (await loadScriptOnce(src)) {
              return true;
            }
          }
          return !!(window.L && typeof window.L.map === 'function');
        })();
      }
      return leafletEnsurePromise;
    }

    function createOfflineTileLayer() {
      const fallback = L.gridLayer({ attribution: 'Offline base', noWrap: true, opacity: 0.95 });
      fallback.createTile = function (coords) {
        const size = this.getTileSize();
        const canvas = document.createElement('canvas');
        canvas.width = size.x;
        canvas.height = size.y;
        const ctx = canvas.getContext('2d');
        if (!ctx) return canvas;
        ctx.fillStyle = '#0c2235';
        ctx.fillRect(0, 0, size.x, size.y);
        ctx.strokeStyle = 'rgba(99, 217, 255, 0.18)';
        ctx.strokeRect(0.5, 0.5, size.x - 1, size.y - 1);
        ctx.strokeStyle = 'rgba(87, 240, 190, 0.1)';
        ctx.beginPath();
        ctx.moveTo(0, size.y / 2);
        ctx.lineTo(size.x, size.y / 2);
        ctx.moveTo(size.x / 2, 0);
        ctx.lineTo(size.x / 2, size.y);
        ctx.stroke();
        ctx.fillStyle = 'rgba(220, 238, 255, 0.65)';
        ctx.font = '11px JetBrains Mono, monospace';
        ctx.fillText(`${coords.z}/${coords.x}/${coords.y}`, 8, 16);
        return canvas;
      };
      return fallback;
    }

    async function loadLocalWorldGeoJson() {
      if (localWorldGeoPromise) {
        return localWorldGeoPromise;
      }
      localWorldGeoPromise = (async () => {
        const candidates = [
          homeLeafletWorldGeoJsonUrl,
          'assets/vendor/leaflet/data/world-countries.geo.json',
          '/assets/vendor/leaflet/data/world-countries.geo.json'
        ];
        for (const candidate of candidates) {
          try {
            const response = await fetch(candidate, { cache: 'force-cache' });
            if (!response.ok) {
              continue;
            }
            const json = await response.json();
            if (json && String(json.type || '') === 'FeatureCollection' && Array.isArray(json.features)) {
              return json;
            }
          } catch (error) {
            // Ignore and try next candidate.
          }
        }
        return null;
      })();
      return localWorldGeoPromise;
    }

    function createOfflineCountriesLayer() {
      const group = L.layerGroup();
      loadLocalWorldGeoJson().then((geojson) => {
        if (!geojson) {
          return;
        }
        L.geoJSON(geojson, {
          interactive: false,
          attribution: 'Countries: Natural Earth (local cache)',
          style: () => ({
            color: '#33577a',
            weight: 0.8,
            opacity: 0.95,
            fillColor: '#10263b',
            fillOpacity: 0.9
          })
        }).addTo(group);
      }).catch(() => {
        // Keep map available even if local geojson could not be loaded.
      });
      return group;
    }

    function makeLeafletMap(targetEl, center = [20, 0], zoom = 2) {
      if (!targetEl || !window.L) return null;
      const map = L.map(targetEl, {
        center,
        zoom,
        minZoom: 2,
        maxZoom: 7,
        zoomControl: true,
        worldCopyJump: true
      });
      const baseLayers = {
        'Offline Countries (local)': createOfflineCountriesLayer(),
        'Offline Grid': createOfflineTileLayer()
      };
      baseLayers['Offline Countries (local)'].addTo(map);
      L.control.layers(baseLayers, null, { position: 'topright', collapsed: true }).addTo(map);
      return map;
    }

    async function loadHomeGeoData() {
      if (!homeExtensionMapEl && !homeWebMapEl) {
        return;
      }
      const leafletReady = await ensureLeafletLoaded();
      if (!leafletReady || !window.L) {
        setTableMessage(homeExtensionBody, 'Mapa no disponible (Leaflet no cargado).', 4);
        setTableMessage(homeWebBody, 'Mapa no disponible (Leaflet no cargado).', 7);
        return;
      }
      let payload = null;
      try {
        const response = await fetch('dashboard.php?format=home_geo', { cache: 'no-store' });
        if (!response.ok) {
          throw new Error(`home_geo_failed_${response.status}`);
        }
        payload = await response.json();
      } catch (error) {
        setTableMessage(homeExtensionBody, 'No se pudo cargar geointeligencia de usuarios.', 4);
        setTableMessage(homeWebBody, 'No se pudo cargar geointeligencia de webs.', 7);
        return;
      }
      const data = payload && payload.status === 'ok' ? payload.data : null;
      if (!data || typeof data !== 'object') {
        setTableMessage(homeExtensionBody, 'Sin datos de geointeligencia.', 4);
        setTableMessage(homeWebBody, 'Sin datos de geointeligencia.', 7);
        return;
      }

      const extensionPoints = Array.isArray(data.extension_points) ? data.extension_points : [];
      const extensionCountriesData = Array.isArray(data.extension_country_counts) ? data.extension_country_counts : [];
      const websitePoints = Array.isArray(data.website_points) ? data.website_points : [];
      const websiteRows = Array.isArray(data.website_rows) ? data.website_rows : [];

      const extensionMap = makeLeafletMap(homeExtensionMapEl);
      if (extensionMap) {
        extensionPoints.forEach((point) => {
          const lat = Number(point.lat || 0);
          const lon = Number(point.lon || 0);
          if (!Number.isFinite(lat) || !Number.isFinite(lon) || (lat === 0 && lon === 0)) return;
          const users = Number(point.users || 0);
          L.circleMarker([lat, lon], {
            radius: Math.max(5, Math.min(22, 4 + Math.log2(Math.max(1, users)) * 3)),
            color: '#ff4d4f',
            fillColor: '#ff2d2f',
            fillOpacity: 0.75,
            weight: 1
          })
            .bindPopup(
              `<b>${escapeHtml(String(point.country_name || point.country_code || '-'))}</b><br>` +
              `Usuarios extension: ${escapeHtml(String(users))}<br>` +
              `Codigo: ${escapeHtml(String(point.country_code || '-'))}`
            )
            .addTo(extensionMap);
        });
        setTimeout(() => extensionMap.invalidateSize(), 80);
      }

      if (homeExtensionTotal) {
        homeExtensionTotal.textContent = String(Number(data.extension_users_total || 0));
      }
      if (homeExtensionCountries) {
        homeExtensionCountries.textContent = String(extensionCountriesData.length);
      }
      if (homeExtensionBody) {
        homeExtensionBody.innerHTML = '';
        if (!extensionCountriesData.length) {
          setTableMessage(homeExtensionBody, 'No hay paises con actividad reciente.', 4);
        } else {
          extensionCountriesData.forEach((row) => {
            const tr = document.createElement('tr');
            const languages = Array.isArray(row.languages) && row.languages.length ? row.languages.join(', ') : '-';
            tr.innerHTML =
              `<td>${escapeHtml(String(row.country_name || row.country_code || '-'))}</td>` +
              `<td class="mono">${escapeHtml(String(row.country_code || '-'))}</td>` +
              `<td class="mono">${escapeHtml(String(row.users || 0))}</td>` +
              `<td>${escapeHtml(languages)}</td>`;
            homeExtensionBody.appendChild(tr);
          });
        }
      }

      const webMap = makeLeafletMap(homeWebMapEl);
      if (webMap) {
        websitePoints.forEach((point) => {
          const lat = Number(point.lat || 0);
          const lon = Number(point.lon || 0);
          if (!Number.isFinite(lat) || !Number.isFinite(lon) || (lat === 0 && lon === 0)) return;
          const hits = Number(point.hits || 0);
          L.circleMarker([lat, lon], {
            radius: Math.max(4, Math.min(18, 4 + Math.log2(Math.max(1, hits)) * 2)),
            color: '#ffb347',
            fillColor: '#ff8f1f',
            fillOpacity: 0.65,
            weight: 1
          })
            .bindPopup(
              `<b>${escapeHtml(String(point.hostname || '-'))}</b><br>` +
              `IP: ${escapeHtml(String(point.ip || '-'))}<br>` +
              `ISP: ${escapeHtml(String(point.isp || '-'))}<br>` +
              `Servidor HTTP: ${escapeHtml(String(point.http_server || '-'))}<br>` +
              `Pais: ${escapeHtml(String(point.country_name || point.country_code || '-'))}<br>` +
              `Idioma: ${escapeHtml(String(point.language || '-'))}<br>` +
              `Servicios: ${escapeHtml(Array.isArray(point.services) && point.services.length ? point.services.join(', ') : '-') }<br>` +
              `Hits: ${escapeHtml(String(hits))}`
            )
            .addTo(webMap);
        });
        setTimeout(() => webMap.invalidateSize(), 80);
      }

      if (homeWebCount) {
        homeWebCount.textContent = String(websitePoints.length);
      }
      if (homeWebLast) {
        homeWebLast.textContent = String(data.generated_at || '-');
      }
      if (homeWebBody) {
        homeWebBody.innerHTML = '';
        if (!websiteRows.length) {
          setTableMessage(homeWebBody, 'No hay webs suficientes para geolocalizar.', 7);
        } else {
          websiteRows.slice(0, 80).forEach((row) => {
            const tr = document.createElement('tr');
            const services = Array.isArray(row.services) && row.services.length
              ? row.services.slice(0, 5).join(', ')
              : (row.http_server ? String(row.http_server) : '-');
            tr.innerHTML =
              `<td class="mono">${escapeHtml(String(row.hostname || '-'))}</td>` +
              `<td class="mono">${escapeHtml(String(row.ip || '-'))}</td>` +
              `<td>${escapeHtml(String(row.isp || '-'))}</td>` +
              `<td>${escapeHtml(String(row.country_name || row.country_code || '-'))}</td>` +
              `<td class="mono">${escapeHtml(String(row.language || '-'))}</td>` +
              `<td>${escapeHtml(services)}</td>` +
              `<td class="mono">${escapeHtml(String(row.hits || 0))}</td>`;
            homeWebBody.appendChild(tr);
          });
        }
      }
      initSortableTables();
    }
    initSortableTables();
    initBulkReviewActions();
    loadHomeGeoData();
    renderDashboardCharts();

    setInterval(async () => {
      try {
        const r = await fetch('dashboard.php?public=1&format=live', { cache: 'no-store' });
        if (!r.ok) return;
        const p = await r.json();
        if (!p.stats) return;
        document.querySelectorAll('[data-live-metric]').forEach((n) => {
          const k = n.getAttribute('data-live-metric');
          if (!k || !Object.prototype.hasOwnProperty.call(p.stats, k)) return;
          const rawValue = p.stats[k];
          if (k === 'review_coverage_pct' || k === 'block_rate_24h') {
            const numeric = Number(rawValue);
            n.textContent = `${Number.isFinite(numeric) ? numeric.toFixed(2) : '0.00'}%`;
            return;
          }
          n.textContent = String(rawValue);
        });
      } catch (e) { console.debug(e); }
    }, 45000);
  </script>
</body>
</html>
<?php
$dashboardOutput = ob_get_clean();
echo cfdashboardtranslateoutput($dashboardOutput, (string) $lang);

