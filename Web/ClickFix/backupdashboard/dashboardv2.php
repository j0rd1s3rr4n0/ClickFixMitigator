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
    .wrap{max-width:900px;margin:28px auto;padding:0 16px}
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
$redactSensitiveForViewer = $loggedIn
    && clickfix_user_has_min_role($user, 'analyst_jr')
    && !clickfix_user_has_min_role($user, 'analyst_sr');
$publicPages = ['home', 'search', 'about', 'coverage', 'access', 'investigation', 'profile'];
$pageAccess = [
    'ops' => 'analyst_jr',
    'analytics' => 'analyst_jr',
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
$langSupported = ['en', 'es'];
$langParam = strtolower(trim((string) ($_GET['lang'] ?? '')));
if (!in_array($langParam, $langSupported, true)) {
    $langParam = '';
}
if ($langParam !== '') {
    $_SESSION['clickfix_lang'] = $langParam;
}
$lang = strtolower(trim((string) ($_SESSION['clickfix_lang'] ?? '')));
if (!in_array($lang, $langSupported, true)) {
    $lang = $loggedIn ? clickfix_normalize_user_language((string) ($user['preferred_lang'] ?? 'en')) : 'en';
    if (!in_array($lang, $langSupported, true)) {
        $lang = 'en';
    }
    $_SESSION['clickfix_lang'] = $lang;
}
$page = strtolower(trim((string) ($_GET['page'] ?? 'home')));
if (!in_array($page, $allPages, true)) {
    $page = 'home';
}
$focusReportId = (int) ($_GET['report_id'] ?? 0);
$postReturnPage = in_array($page, ['ops', 'home', 'search', 'analytics'], true) ? $page : 'ops';
if (in_array($page, $privatePages, true) && !$loggedIn) {
    $page = 'home';
}
if ($loggedIn && isset($pageAccess[$page]) && !clickfix_user_has_min_role($user, (string) $pageAccess[$page])) {
    clickfix_flash('Tu rol no tiene permisos para esa seccion.');
    $page = 'ops';
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
            clickfix_flash('Sesion iniciada.');
            clickfix_redirect('dashboard.php?page=ops');
        }
        clickfix_flash('Credenciales incorrectas.');
        clickfix_redirect('dashboard.php?page=access&public=1');
    }
    if ($action === 'logout') {
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
    if ($action === 'request_access') {
        clickfix_store_access_request($pdo, (string) ($_POST['access_email'] ?? ''), (string) ($_POST['request_lang'] ?? 'en'));
        clickfix_flash('Solicitud enviada.');
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

    if ($action === 'user_self_update_lang') {
        $preferredLang = (string) ($_POST['self_lang'] ?? 'en');
        $ok = clickfix_user_update_preferences($pdo, $actorId, $preferredLang);
        if ($ok) {
            clickfix_user_reload_session($pdo, $actorId);
            $nextLang = clickfix_normalize_user_language($preferredLang);
            $_SESSION['clickfix_lang'] = in_array($nextLang, ['en', 'es'], true) ? $nextLang : 'en';
            clickfix_flash('Idioma por defecto actualizado.');
        } else {
            clickfix_flash('No se pudo actualizar el idioma por defecto.');
        }
        clickfix_redirect('dashboard.php?page=access');
    }

    if ($action === 'user_self_change_password') {
        $ok = clickfix_user_change_password(
            $pdo,
            $actorId,
            (string) ($_POST['self_current_password'] ?? ''),
            (string) ($_POST['self_new_password'] ?? '')
        );
        clickfix_flash($ok ? 'Contrasena actualizada.' : 'No se pudo cambiar la contrasena (revisa tu clave actual y el minimo de 10 caracteres).');
        clickfix_redirect('dashboard.php?page=access');
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
                clickfix_is_admin()
            );
            if ($savedId !== null) {
                clickfix_flash('Investigacion creada desde alerta #' . $reportId . '.');
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
        clickfix_flash($ok ? 'Revision actualizada.' : 'No hubo cambios en la revision (verifica el evento y el estado).');
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
        clickfix_flash('Revision masiva aplicada: ' . $updatedCount . '/' . $totalCount . ($failedCount > 0 ? (' (' . $failedCount . ' sin cambios)') : '') . '.');
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
    if ($action === 'investigation_save') {
        if (!clickfix_user_has_min_role($actor, 'analyst_jr')) {
            clickfix_flash('Permisos insuficientes para guardar investigaciones.');
            clickfix_redirect('dashboard.php?page=home&public=1');
        }
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
            clickfix_flash('Investigacion guardada.');
            clickfix_redirect('dashboard.php?page=intel&graph_id=' . (int) $savedId);
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
        $redirect = 'dashboard.php?page=intel';
        if ($graphId > 0) {
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
        $redirect = 'dashboard.php?page=intel';
        if ($graphId > 0) {
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
        $redirect = 'dashboard.php?page=intel';
        if ($graphId > 0) {
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
        $redirect = 'dashboard.php?page=intel';
        if ($graphId > 0) {
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
            clickfix_flash('No tienes API key activa para ese proveedor.');
            $redirect = 'dashboard.php?page=intel';
            if ($graphId > 0) {
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
            clickfix_flash($lookupStored ? 'Consulta API completada y guardada en historial.' : 'Consulta API completada (no se pudo guardar historial).');
        } else {
            $baseError = (string) ($lookup['error'] ?? 'error');
            clickfix_flash($lookupStored ? ('Consulta API sin exito: ' . $baseError . ' (guardada en historial).') : ('Consulta API sin exito: ' . $baseError));
        }
        $redirect = 'dashboard.php?page=intel';
        if ($graphId > 0) {
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
            if (in_array($selfLang, ['en', 'es'], true)) {
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
    $payload = [
        'status' => 'ok',
        'generated_at' => gmdate('c'),
        'stats' => $metrics,
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
    $sourceIp = $canSrForRelated ? trim((string) ($sourceReport['ip'] ?? '')) : '';
    $relatedRows = clickfix_related_reports($pdo, $reportId, $sourceHost, $sourceIp, 30);
    if ($redactSensitiveForViewer) {
        $relatedRows = array_map(static function (array $row): array {
            foreach (['url', 'previous_url', 'message', 'detected_content', 'full_context'] as $field) {
                if (isset($row[$field]) && is_scalar($row[$field])) {
                    $row[$field] = clickfix_dashboard_redact_sensitive((string) $row[$field]);
                }
            }
            if (isset($row['matched_snippets']) && is_array($row['matched_snippets'])) {
                $row['matched_snippets'] = array_map(static function ($snippet): string {
                    return clickfix_dashboard_redact_sensitive((string) $snippet);
                }, $row['matched_snippets']);
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
            'ip' => $canSrForRelated ? (string) ($row['ip'] ?? '') : '',
            'score_total' => isset($row['score_total']) ? (int) $row['score_total'] : 0,
            'review_status' => (string) ($row['review_status'] ?? 'pending'),
            'blocked' => !empty($row['blocked']),
            'event_type' => (string) ($row['event_type'] ?? 'clickfix_alert'),
            'duplicate_count' => (int) ($row['duplicate_count'] ?? 1),
            'related_by_domain' => !empty($row['related_by_domain']),
            'related_by_ip' => $canSrForRelated && !empty($row['related_by_ip']),
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
$pageNeedsVtReportedStats = $loggedIn && clickfix_user_has_min_role($user, 'analyst_jr') && $page === 'intel';

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

$appeals = $pageNeedsAppealsData ? clickfix_recent_appeals($pdo, 20) : [];
$requests = $pageNeedsRequestsData ? clickfix_recent_access_requests($pdo, 30) : [];
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
$usersDirectory = $pageNeedsUserDirectory ? clickfix_recent_users($pdo, 400) : [];
$users = $pageNeedsUsersAdmin ? clickfix_recent_users($pdo, 200) : [];
$analyticsDays = ($page === 'home' || $page === 'analytics') ? 30 : 7;
$analyticsOverview = clickfix_analytics_overview($pdo, $analyticsDays);
$latestScanPreview = is_array($analyticsOverview['latest_scan'] ?? null) ? $analyticsOverview['latest_scan'] : null;
$latestScanAssetsApproved = is_array($analyticsOverview['latest_scan_assets'] ?? null)
    ? $analyticsOverview['latest_scan_assets']
    : ['before' => null, 'after' => null, 'before_exists' => false, 'after_exists' => false, 'before_status' => 'missing', 'after_status' => 'missing'];
$latestScanAssetsReview = $latestScanAssetsApproved;
if ($canAdminViewer && is_array($latestScanPreview) && !empty($latestScanPreview['id'])) {
    $latestScanAssetsReview = clickfix_scan_preview_assets($pdo, (int) $latestScanPreview['id'], false);
}
$scanReviewQueue = $pageNeedsScanReviewQueue ? clickfix_scan_image_review_queue($pdo, 120) : [];
$mlInsights = $pageNeedsMlInsights ? clickfix_ml_insights($pdo, 300) : [];
$anomalyInsights = $pageNeedsAnomalyData ? clickfix_anomaly_detector($pdo, 35, 24) : [];
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
$intelUserApiKeys = [];
$platformApiKeys = [];
$platformApiKeyJustCreated = null;
$intelApiLookupResult = null;
$intelApiLookupHistory = [];
if ($loggedIn && $page === 'intel') {
    $investigations = clickfix_recent_investigations($pdo, (int) ($user['id'] ?? 0), clickfix_is_admin(), 120);
    $requestedGraphId = (int) ($_GET['graph_id'] ?? 0);
    if ($requestedGraphId > 0) {
        $selectedInvestigation = clickfix_get_investigation($pdo, $requestedGraphId, (int) ($user['id'] ?? 0), clickfix_is_admin());
        if ($selectedInvestigation === null && clickfix_user_has_min_role($user, 'analyst_mid')) {
            $candidate = clickfix_get_investigation_any($pdo, $requestedGraphId);
            if (is_array($candidate) && !empty($candidate['submitted_to_community'])) {
                $selectedInvestigation = $candidate;
            }
        }
    }
    if ($selectedInvestigation === null && !empty($investigations)) {
        $selectedInvestigation = $investigations[0];
    }
    if ($selectedInvestigation !== null) {
        $investigationEvents = clickfix_investigation_events($pdo, (int) ($selectedInvestigation['id'] ?? 0), 220);
    }
    $intelUserApiKeys = clickfix_user_api_keys($pdo, (int) ($user['id'] ?? 0), true);
    $platformApiKeys = clickfix_user_platform_api_keys($pdo, (int) ($user['id'] ?? 0));
    $lookupStore = (isset($_SESSION['clickfix_intel_api_lookup']) && is_array($_SESSION['clickfix_intel_api_lookup']))
        ? $_SESSION['clickfix_intel_api_lookup']
        : [];
    $platformKeyStore = (isset($_SESSION['clickfix_platform_api_key_once']) && is_array($_SESSION['clickfix_platform_api_key_once']))
        ? $_SESSION['clickfix_platform_api_key_once']
        : [];
    $viewerId = (int) ($user['id'] ?? 0);
    if ($viewerId > 0 && isset($lookupStore[$viewerId]) && is_array($lookupStore[$viewerId])) {
        $intelApiLookupResult = $lookupStore[$viewerId];
        unset($_SESSION['clickfix_intel_api_lookup'][$viewerId]);
    }
    if ($viewerId > 0 && isset($platformKeyStore[$viewerId]) && is_array($platformKeyStore[$viewerId])) {
        $platformApiKeyJustCreated = $platformKeyStore[$viewerId];
        unset($_SESSION['clickfix_platform_api_key_once'][$viewerId]);
    }
    if ($viewerId > 0) {
        $historyGraphId = (int) ($selectedInvestigation['id'] ?? 0);
        $intelApiLookupHistory = clickfix_investigation_api_lookup_recent($pdo, $viewerId, 18, $historyGraphId);
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
if (!in_array($profileTab, ['investigations', 'reports'], true)) {
    $profileTab = 'investigations';
}
$profileUser = null;
$profileInvestigations = [];
$profileReports = [];
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
            'nav_ops' => 'Operaciones',
            'nav_graphs' => 'Graficos',
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
            'lang_es' => 'Espanol',
            'lang_en' => 'English',
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
            'intel_api_key_save' => 'Guardar clave',
            'intel_api_key_delete' => 'Eliminar clave',
            'intel_api_key_masked' => 'Guardada',
            'intel_api_key_updated' => 'Actualizada',
            'intel_api_lookup_title' => 'Consulta IOC con tus APIs',
            'intel_api_lookup_sub' => 'Usa tu clave guardada para consultar dominio/IP/URL segun proveedor.',
            'intel_api_lookup_target' => 'Indicador (dominio, IP o URL)',
            'intel_api_lookup_button' => 'Consultar',
            'intel_api_lookup_result' => 'Resultado de consulta',
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
            'nav_ops' => 'Operations',
            'nav_graphs' => 'Analytics',
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
            'intel_api_key_save' => 'Save key',
            'intel_api_key_delete' => 'Delete key',
            'intel_api_key_masked' => 'Stored',
            'intel_api_key_updated' => 'Updated',
            'intel_api_lookup_title' => 'IOC lookup with your APIs',
            'intel_api_lookup_sub' => 'Use your saved key to query domain/IP/URL depending on provider.',
            'intel_api_lookup_target' => 'Indicator (domain, IP, or URL)',
            'intel_api_lookup_button' => 'Lookup',
            'intel_api_lookup_result' => 'Lookup result',
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
    if (isset($dict[$lang][$key])) {
        return (string) $dict[$lang][$key];
    }
    if (isset($dict['es'][$key])) {
        return (string) $dict['es'][$key];
    }
    return $key;
}

function cfworkflowlabel(string $status, string $lang): string
{
    $status = clickfix_investigation_workflow_status($status);
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
    ];
    if (isset($labels[$lang][$status])) {
        return (string) $labels[$lang][$status];
    }
    return (string) ($labels['en'][$status] ?? $status);
}

function cfmalwarelabel(string $classification, string $lang): string
{
    $classification = strtolower(trim($classification));
    $labels = [
        'en' => ['malware' => 'Malware', 'legit' => 'Legit', 'neutral' => 'Neutral'],
        'es' => ['malware' => 'Malware', 'legit' => 'Legitimo', 'neutral' => 'Neutral'],
    ];
    if (isset($labels[$lang][$classification])) {
        return (string) $labels[$lang][$classification];
    }
    return (string) ($labels['en']['neutral'] ?? 'Neutral');
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

$reportBlockedHistory = [
    'hostnames' => [],
    'ips' => [],
];
if (!empty($reports)) {
    $historyHostnames = [];
    $historyIps = [];
    foreach ($reports as $historyRow) {
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
foreach ($reports as $reportRow) {
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
$eventWorkbenchJson = json_encode($eventWorkbenchRows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
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
$pageLabelMap = [
    'home' => cft('nav_home'),
    'search' => cft('nav_search'),
    'coverage' => cft('nav_coverage'),
    'about' => cft('nav_about'),
    'access' => cft('nav_access'),
    'ops' => cft('nav_ops'),
    'analytics' => cft('nav_graphs'),
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
?>
<!doctype html>
<html lang="<?= clickfix_h($lang); ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>ClickFix Security Operations Center</title>
  <?php if ($enableHomeGeoPanels): ?>
  <link rel="stylesheet" href="<?= clickfix_h($leafletCssUrl); ?>">
  <?php endif; ?>
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Sora:wght@500;600;700;800&family=Manrope:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap');
    :root{
      --bg:#03060d;
      --bg-layer:#091325;
      --bg-soft:#10223f;
      --card:#0d1d34cc;
      --line:#355a86;
      --line-soft:#2a4565;
      --txt:#ecf4ff;
      --mut:#9eb7d8;
      --brand:#63d9ff;
      --brand-2:#57f0be;
      --warn:#ffd470;
      --danger:#ff8e9f;
      --ok:#52dfb0;
      --shadow:0 22px 56px rgba(2,8,20,.45);
      --radius:18px;
      --radius-sm:13px;
    }
    *{box-sizing:border-box}
    html,body{height:100%;scroll-behavior:smooth}
    body{
      margin:0;
      color:var(--txt);
      font-family:'Manrope',sans-serif;
      background:
        radial-gradient(1100px 500px at 7% -8%,rgba(99,217,255,.22),transparent 62%),
        radial-gradient(960px 460px at 95% -10%,rgba(87,240,190,.18),transparent 58%),
        radial-gradient(760px 320px at 52% 108%,rgba(109,129,255,.12),transparent 62%),
        repeating-linear-gradient(90deg,rgba(255,255,255,.018) 0 1px,transparent 1px 92px),
        linear-gradient(145deg,var(--bg),var(--bg-layer) 45%,#0d1a31 100%);
      min-height:100vh;
    }
    .wrap{width:min(1920px,99vw);margin:auto;padding:10px 0 18px}
    .workspace{
      display:grid;
      grid-template-columns:minmax(0,1fr) 350px;
      gap:8px;
      align-items:start;
    }
    .main-column{min-width:0}
    .side-column{
      display:flex;
      flex-direction:column;
      gap:8px;
      position:sticky;
      top:128px;
      align-self:start;
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
    .support-note{margin:0 0 8px;color:#bcd6ef;font-size:.8rem;line-height:1.4}
    .top,.card{
      background:linear-gradient(170deg,rgba(15,37,64,.76),rgba(10,24,45,.78));
      border:1px solid rgba(84,134,188,.44);
      border-radius:var(--radius);
      box-shadow:var(--shadow);
      backdrop-filter:blur(12px);
    }
    .top{
      display:flex;
      justify-content:space-between;
      gap:14px;
      padding:12px 14px;
      margin-bottom:8px;
      align-items:flex-start;
      position:sticky;
      top:10px;
      z-index:12;
    }
    .mono,code{font-family:'JetBrains Mono',monospace}
    .top b{font:700 1rem 'Sora',sans-serif;letter-spacing:.25px}
    .top .mut{font-size:.79rem}
    .module-chip{
      margin-top:8px;
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
    .top-status{display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end}
    .status-chip{
      padding:5px 10px;
      border-radius:999px;
      border:1px solid #4a76a2;
      background:linear-gradient(135deg,#152f4a,#11263b);
      font:.7rem 'JetBrains Mono',monospace;
      color:#e5f2ff;
    }
    .status-chip a{color:#c9f2ff;text-decoration:none}
    nav{
      display:flex;
      flex-wrap:wrap;
      gap:8px;
      margin:0 0 8px;
      padding:8px;
      border-radius:var(--radius-sm);
      border:1px solid rgba(92,139,192,.42);
      background:linear-gradient(170deg,rgba(13,30,50,.8),rgba(10,23,39,.82));
      position:sticky;
      top:74px;
      z-index:11;
      box-shadow:0 14px 36px rgba(4,11,24,.38);
      backdrop-filter:blur(10px);
    }
    .nav-spacer{margin-left:auto}
    .nav-actions{
      margin-left:auto;
      display:flex;
      align-items:center;
      gap:8px;
      flex-wrap:wrap;
    }
    .nav-actions form{margin:0}
    .nav-actions .nav-btn{
      text-decoration:none;
      color:#dff1ff;
      padding:7px 10px;
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
    .nav-actions .nav-btn:hover{
      border-color:#79dbff;
      transform:translateY(-1px);
      box-shadow:0 10px 20px rgba(16,78,129,.3);
    }
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
    nav a{
      text-decoration:none;
      color:#dff1ff;
      padding:7px 10px;
      border-radius:12px;
      border:1px solid #3f6690;
      background:linear-gradient(140deg,#132d49,#10253d);
      font:600 .76rem 'JetBrains Mono',monospace;
      letter-spacing:.1px;
      transition:.22s ease;
    }
    nav a:hover{
      border-color:#79dbff;
      transform:translateY(-1px);
      box-shadow:0 10px 20px rgba(16,78,129,.3);
    }
    nav a.active{
      color:#06182a;
      border-color:transparent;
      background:linear-gradient(135deg,var(--brand),var(--brand-2));
      box-shadow:0 12px 26px rgba(99,217,255,.32);
    }
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
    .grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:8px;margin-bottom:8px}
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
    .viz-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px}
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
    table{width:100%;border-collapse:separate;border-spacing:0;font-size:.79rem}
    th,td{
      padding:7px 8px;
      border-bottom:1px solid rgba(58,96,132,.4);
      text-align:left;
      vertical-align:top;
    }
    th{
      font:700 .68rem 'JetBrains Mono',monospace;
      text-transform:uppercase;
      color:#c7ddf4;
      letter-spacing:.28px;
      background:linear-gradient(120deg,rgba(24,49,75,.52),rgba(17,34,54,.45));
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
    tr:hover td{background:rgba(14,32,51,.45)}
    input,select,textarea,button{
      width:100%;
      padding:7px 9px;
      border-radius:11px;
      border:1px solid #3a5f88;
      background:linear-gradient(150deg,#102742,#0d2137);
      color:var(--txt);
      font:500 .84rem 'Manrope',sans-serif;
      transition:border-color .2s ease,box-shadow .2s ease,transform .18s ease;
    }
    input[type=checkbox],input[type=radio]{width:auto;padding:0;transform:translateY(1px)}
    label{display:flex;gap:8px;align-items:center}
    input:focus,select:focus,textarea:focus{
      outline:none;
      border-color:#8be4ff;
      box-shadow:0 0 0 3px rgba(99,217,255,.2);
    }
    textarea{min-height:76px}
    .btn{
      background:linear-gradient(120deg,var(--brand),var(--brand-2));
      border:none;
      color:#032037;
      font-weight:700;
      letter-spacing:.1px;
      cursor:pointer;
      box-shadow:0 12px 22px rgba(83,198,255,.24);
      transition:.2s ease;
    }
    .btn:hover{transform:translateY(-1px);box-shadow:0 16px 30px rgba(83,198,255,.34)}
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
    .legacy-events{margin-top:8px}
    .legacy-events summary{cursor:pointer;color:#cbeefd}
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
    .intel-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}
    .intel-grid-full{grid-column:1 / -1}
    .intel-toolbar{display:flex;gap:8px;flex-wrap:wrap;margin:10px 0}
    .intel-toolbar button{width:auto}
    .intel-canvas-wrap{position:relative;height:470px;border:1px solid #2f577f;border-radius:11px;background:radial-gradient(circle at 18% 12%,#214865 0,#0d2438 42%,#091728 100%);overflow:hidden}
    .intel-canvas-wrap svg{position:absolute;inset:0;width:100%;height:100%}
    .intel-node-layer{position:absolute;inset:0}
    .intel-node{position:absolute;transform:translate(-50%,-50%);min-width:96px;max-width:180px;padding:8px 10px;border-radius:12px;color:#fff;font:700 .74rem 'Manrope',sans-serif;border:1px solid #ffffff55;cursor:move;user-select:none;box-shadow:0 8px 18px -12px #000a;word-break:break-word}
    .intel-node.active{outline:2px solid #ffd266;z-index:3}
    .intel-edge-label{position:absolute;transform:translate(-50%,-50%);font:.68rem 'JetBrains Mono',monospace;color:#d6ebf8;background:#10314a;border:1px solid #3d6991;padding:2px 5px;border-radius:6px;pointer-events:none}
    .intel-side{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;margin-top:10px}
    .intel-side h3{margin:.2rem 0}
    .intel-side .card-box{padding:8px;border:1px solid #2d5277;border-radius:10px;background:#0f2740}
    .intel-side .card-box button{margin-top:6px}
    .intel-share{margin-top:10px;padding:8px;border:1px solid #2ea579;border-radius:10px;background:#103b3f}
    .intel-share a{color:#9de9ff;word-break:break-all}
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
      padding:8px;
      display:grid;
      gap:8px;
    }
    .api-lookup-history h4{
      margin:0;
      font:700 .86rem 'Manrope',sans-serif;
      color:#d8ecff;
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
    .intel-public{max-width:1280px;margin:18px auto;padding:0 12px}
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
    .compact-table th,.compact-table td{padding:5px 7px;font-size:.74rem}
    .chart-card .chart-title{margin:0 0 6px;font:.72rem 'JetBrains Mono',monospace;color:#bcd8f2}
    .chart-canvas{width:100%;height:180px;display:block;border-radius:8px;background:#0a1d30}
    .chart-legend{display:flex;gap:8px;flex-wrap:wrap;margin-top:6px}
    .chart-legend .dot{width:8px;height:8px;border-radius:999px;display:inline-block;margin-right:5px}
    .chart-legend span{font:.68rem 'JetBrains Mono',monospace;color:#a7c6e3}
    .profile-shell{display:grid;grid-template-columns:minmax(280px,340px) minmax(0,1fr);gap:10px}
    .profile-card{padding:12px;border:1px solid #355a82;border-radius:12px;background:#0c253d}
    .profile-avatar{width:78px;height:78px;border-radius:999px;display:grid;place-items:center;font:800 1.5rem 'Sora',sans-serif;background:linear-gradient(145deg,#63d9ff,#57f0be);color:#032640;margin-bottom:10px}
    .profile-name{margin:0;font-size:1.15rem}
    .profile-nick{font:.8rem 'JetBrains Mono',monospace;color:#9cc6e5}
    .profile-meta{display:grid;gap:6px;margin-top:10px}
    .profile-meta .rowx{display:flex;justify-content:space-between;gap:8px;border:1px solid #2f557a;border-radius:9px;padding:6px 8px;background:#112d47}
    .profile-tabs{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:8px}
    .profile-tab{padding:6px 10px;border-radius:10px;border:1px solid #335a80;background:#112b44;color:#d9ecff;text-decoration:none;font:.78rem 'JetBrains Mono',monospace}
    .profile-tab.active{border-color:#58dcaf;background:#12483d;color:#d9fff3}
    hr{border:none;border-top:1px solid #2a4b6d;margin:10px 0}
    pre{white-space:pre-wrap;word-break:break-word}
    @media(min-width:1700px){
      .grid{grid-template-columns:repeat(8,minmax(0,1fr))}
      .kpi{min-height:84px}
      .kpi .mut{font-size:.67rem}
    }
    @media(max-width:1320px){
      .workspace{grid-template-columns:1fr}
      .side-column{position:static}
    }
    @media(max-width:1140px){
      .grid{grid-template-columns:repeat(2,minmax(0,1fr))}
      .hero,.row,.split,.event-workbench,.event-columns,.event-grid,.intel-layout,.intel-grid,.intel-side,.intel-public-meta,.rbac,.viz-grid,.profile-shell,.chart-stack,.analytics-kpi-grid,.intel-kpi-grid,.intel-stage-bar{grid-template-columns:1fr}
      nav{top:72px}
      .nav-spacer{display:none}
      .nav-actions{width:100%;justify-content:flex-end}
    }
    @media(max-width:700px){
      .wrap{width:min(100%,98vw)}
      .top{position:static}
      nav{position:static}
      .grid{grid-template-columns:1fr}
      .top-status{justify-content:flex-start}
      .nav-actions{justify-content:flex-start}
    }
  </style>
</head>
<body>
  <div class="wrap">
    <header class="top">
      <div>
        <b>ClickFix Unified Operations Center</b>
        <div class="mut mono">fusion build | telemetry intelligence | secure workflows</div>
        <div class="module-chip"><?= clickfix_h(cft('label_module')); ?>: <?= clickfix_h($currentPageLabel); ?></div>
      </div>
      <div class="top-status">
        <span class="status-chip">event stream: protected</span>
        <span class="status-chip">records: <?= (int) ($metrics['total_alerts'] ?? 0); ?></span>
        <?php if ($loggedIn): ?><span class="status-chip">operator: <a class="user-link" href="<?= clickfix_h(cfprofileurl((int) ($user['id'] ?? 0), [], false)); ?>"><?= clickfix_h((string) $user['username']); ?></a> | workspace: authenticated</span><?php endif; ?>
        <span class="status-chip">
          <?= clickfix_h(cft('lang_label')); ?>:
          <a href="<?= clickfix_h(cfurl($page, !$loggedIn, ['lang' => 'es'])); ?>">ES</a> |
          <a href="<?= clickfix_h(cfurl($page, !$loggedIn, ['lang' => 'en'])); ?>">EN</a>
        </span>
      </div>
    </header>
    <nav>
      <a class="<?= $page === 'home' ? 'active' : ''; ?>" href="<?= clickfix_h(cfurl('home', true)); ?>"><?= clickfix_h(cft('nav_home')); ?></a>
      <a class="<?= $page === 'search' ? 'active' : ''; ?>" href="<?= clickfix_h(cfurl('search', true)); ?>"><?= clickfix_h(cft('nav_search')); ?></a>
      <a class="<?= $page === 'coverage' ? 'active' : ''; ?>" href="<?= clickfix_h(cfurl('coverage', true)); ?>"><?= clickfix_h(cft('nav_coverage')); ?></a>
      <a class="<?= $page === 'about' ? 'active' : ''; ?>" href="<?= clickfix_h(cfurl('about', true)); ?>"><?= clickfix_h(cft('nav_about')); ?></a>
      <?php if ($loggedIn): ?>
        <a class="<?= $page === 'profile' ? 'active' : ''; ?>" href="<?= clickfix_h(cfprofileurl((int) ($user['id'] ?? 0), [], false)); ?>"><?= clickfix_h(cft('nav_profile')); ?></a>
      <?php endif; ?>
      <?php if ($loggedIn): ?>
        <a class="<?= $page === 'ops' ? 'active' : ''; ?>" href="<?= clickfix_h(cfurl('ops')); ?>"><?= clickfix_h(cft('nav_ops')); ?></a>
        <a class="<?= $page === 'analytics' ? 'active' : ''; ?>" href="<?= clickfix_h(cfurl('analytics')); ?>"><?= clickfix_h(cft('nav_graphs')); ?></a>
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
      <span class="nav-spacer"></span>
      <div class="nav-actions">
        <a class="nav-btn<?= $page === 'access' ? ' active' : ''; ?>" href="<?= clickfix_h(cfurl('access', true)); ?>"><?= clickfix_h(cft('nav_access')); ?></a>
        <?php if ($loggedIn): ?>
          <form method="post">
            <input type="hidden" name="action" value="logout">
            <button type="submit" class="nav-btn logout">Cerrar sesion</button>
          </form>
        <?php endif; ?>
      </div>
    </nav>
    <div class="workspace">
      <main class="main-column">

    
    <?php if ($flash): ?><div class="flash"><?= clickfix_h($flash); ?></div><?php endif; ?>

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

    <section class="grid">
      <article class="card kpi"><div class="kpi-top"><div class="mut mono">alertas totales</div><span class="kpi-icon"><svg viewBox="0 0 24 24"><path d="M12 9v4"/><path d="M12 17h.01"/><path d="M10.3 3.6 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.6a2 2 0 0 0-3.4 0z"/></svg></span></div><b data-live-metric="total_alerts"><?= (int) $metrics['total_alerts']; ?></b></article>
      <article class="card kpi"><div class="kpi-top"><div class="mut mono">bloqueos totales</div><span class="kpi-icon"><svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="10" rx="2"/><path d="M7 11V8a5 5 0 0 1 10 0v3"/></svg></span></div><b data-live-metric="total_blocks"><?= (int) $metrics['total_blocks']; ?></b></article>
      <article class="card kpi"><div class="kpi-top"><div class="mut mono">dominios unicos</div><span class="kpi-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M3 12h18"/><path d="M12 3a14 14 0 0 1 0 18"/><path d="M12 3a14 14 0 0 0 0 18"/></svg></span></div><b data-live-metric="unique_hosts"><?= (int) $metrics['unique_hosts']; ?></b></article>
      <article class="card kpi"><div class="kpi-top"><div class="mut mono">usuarios 24h</div><span class="kpi-icon"><svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="3"/><path d="M3 20a6 6 0 0 1 12 0"/><circle cx="17" cy="9" r="2"/><path d="M15 20a5 5 0 0 1 6 0"/></svg></span></div><b data-live-metric="unique_users"><?= (int) $metrics['unique_users']; ?></b></article>
      <article class="card kpi"><div class="kpi-top"><div class="mut mono">alertas 24h</div><span class="kpi-icon"><svg viewBox="0 0 24 24"><path d="M3 12h5l2-4 4 8 2-4h5"/></svg></span></div><b data-live-metric="alerts_24h"><?= (int) $metrics['alerts_24h']; ?></b></article>
      <article class="card kpi"><div class="kpi-top"><div class="mut mono">bloqueos 24h</div><span class="kpi-icon"><svg viewBox="0 0 24 24"><path d="M5 12l4 4L19 6"/></svg></span></div><b data-live-metric="blocks_24h"><?= (int) $metrics['blocks_24h']; ?></b></article>
      <article class="card kpi"><div class="kpi-top"><div class="mut mono">ratio bloqueo 24h</div><span class="kpi-icon"><svg viewBox="0 0 24 24"><path d="M4 20V10"/><path d="M10 20V4"/><path d="M16 20v-7"/><path d="M22 20v-3"/></svg></span></div><b data-live-metric="block_rate_24h"><?= number_format((float) ($metrics['block_rate_24h'] ?? 0.0), 2); ?>%</b></article>
      <article class="card kpi"><div class="kpi-top"><div class="mut mono">alto riesgo 24h</div><span class="kpi-icon"><svg viewBox="0 0 24 24"><path d="M12 2v8"/><path d="M12 14v8"/><path d="m4.9 4.9 5.7 5.7"/><path d="m13.4 13.4 5.7 5.7"/><path d="M2 12h8"/><path d="M14 12h8"/><path d="m4.9 19.1 5.7-5.7"/><path d="m13.4 10.6 5.7-5.7"/></svg></span></div><b data-live-metric="high_risk_24h"><?= (int) ($metrics['high_risk_24h'] ?? 0); ?></b></article>
      <article class="card kpi"><div class="kpi-top"><div class="mut mono">nuevos dominios 24h</div><span class="kpi-icon"><svg viewBox="0 0 24 24"><path d="M12 3v18"/><path d="M3 12h18"/><path d="M4 7h16"/><path d="M4 17h16"/></svg></span></div><b data-live-metric="new_domains_24h"><?= (int) ($metrics['new_domains_24h'] ?? 0); ?></b></article>
      <article class="card kpi"><div class="kpi-top"><div class="mut mono">clientes ext 24h</div><span class="kpi-icon"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="14" rx="2"/><path d="M8 20h8"/><path d="M10 17v3"/><path d="M14 17v3"/></svg></span></div><b data-live-metric="active_extension_clients_24h"><?= (int) ($metrics['active_extension_clients_24h'] ?? 0); ?></b></article>
      <article class="card kpi"><div class="kpi-top"><div class="mut mono">revisadas</div><span class="kpi-icon"><svg viewBox="0 0 24 24"><path d="M5 12l4 4L19 6"/><path d="M3 3h18v18H3z"/></svg></span></div><b data-live-metric="reviewed_total"><?= (int) ($metrics['reviewed_total'] ?? 0); ?></b></article>
      <article class="card kpi"><div class="kpi-top"><div class="mut mono">cobertura revision</div><span class="kpi-icon"><svg viewBox="0 0 24 24"><path d="M12 3a9 9 0 1 0 9 9"/><path d="M12 12 19 5"/></svg></span></div><b data-live-metric="review_coverage_pct"><?= number_format((float) ($metrics['review_coverage_pct'] ?? 0.0), 2); ?>%</b></article>
      <article class="card kpi"><div class="kpi-top"><div class="mut mono">sitios manuales</div><span class="kpi-icon"><svg viewBox="0 0 24 24"><path d="M8 6h13"/><path d="M8 12h13"/><path d="M8 18h13"/><path d="M3 6h.01"/><path d="M3 12h.01"/><path d="M3 18h.01"/></svg></span></div><b data-live-metric="manual_sites_count"><?= (int) $metrics['manual_sites_count']; ?></b></article>
      <article class="card kpi"><div class="kpi-top"><div class="mut mono">pendientes</div><span class="kpi-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></svg></span></div><b data-live-metric="pending_review"><?= (int) $metrics['pending_review']; ?></b></article>
    </section>

    <?php if ($loggedIn): ?>
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
                  <a class="btn" href="<?= clickfix_h($adminPreviewUrl); ?>" target="_blank" rel="noopener noreferrer">Abrir pestaÃ±a</a>
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
                <p class="mut" style="margin-top:6px">Cuando una captura queda en <b>approved</b>, se puede reutilizar en dashboard/index publico.</p>
              <?php else: ?>
                <p class="mut">Sin capturas disponibles.</p>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>

    <?php if ($enableHomeGeoPanels): ?>
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
          <table>
            <thead><tr><th>Fecha</th><th>Dominio</th><th>Mensaje</th><th>Score</th></tr></thead>
            <tbody>
              <?php foreach ($filteredReports as $fr): ?>
                <tr>
                  <td class="mono"><?= clickfix_h((string) ($fr['received_at'] ?? '')); ?></td>
                  <td class="mono"><?= clickfix_h((string) ($fr['hostname'] ?? '-')); ?></td>
                  <td><?= clickfix_h((string) ($fr['message'] ?? '')); ?></td>
                  <td class="mono"><?= isset($fr['score_total']) ? (int) $fr['score_total'] : 0; ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
    <?php elseif ($page === 'access'): ?>
      <section class="row">
        <article class="card">
          <h2>Acceso y login</h2>
          <form method="post"><input type="hidden" name="action" value="request_access"><input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>"><input type="email" name="access_email" required placeholder="email"><select name="request_lang"><option value="en" selected>en</option><option value="es">es</option><option value="fr">fr</option><option value="de">de</option></select><button class="btn" type="submit">Solicitar acceso</button></form>
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
            <p class="mut">Gestiona tu idioma por defecto y tu contrasena (solo tu cuenta).</p>
            <div class="rbac">
              <div class="item"><b>Usuario</b><span class="mono"><a class="user-link" href="<?= clickfix_h(cfprofileurl((int) ($user['id'] ?? 0), [], false)); ?>"><?= clickfix_h((string) ($user['username'] ?? '')); ?></a></span></div>
              <div class="item"><b>Email</b><span class="mono"><?= clickfix_h((string) ($user['email'] ?? '-')); ?></span></div>
              <div class="item"><b>Rol</b><span><?= clickfix_h((string) ($user['role_label'] ?? '-')); ?></span></div>
              <div class="item"><b>REP</b><span class="mono"><?= (int) ($user['reputation'] ?? 0); ?></span></div>
            </div>
            <hr>
            <form method="post">
              <input type="hidden" name="action" value="user_self_update_lang">
              <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
              <label for="self-lang">Idioma por defecto</label>
              <?php $selfLang = clickfix_normalize_user_language((string) ($user['preferred_lang'] ?? 'en')); ?>
              <select id="self-lang" name="self_lang">
                <option value="en"<?= $selfLang === 'en' ? ' selected' : ''; ?>>en</option>
                <option value="es"<?= $selfLang === 'es' ? ' selected' : ''; ?>>es</option>
              </select>
              <button class="btn" type="submit">Guardar idioma</button>
            </form>
            <hr>
            <form method="post">
              <input type="hidden" name="action" value="user_self_change_password">
              <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
              <input type="password" name="self_current_password" required placeholder="Contrasena actual">
              <input type="password" name="self_new_password" minlength="10" required placeholder="Nueva contrasena (min 10 chars)">
              <button class="btn" type="submit">Cambiar mi contrasena</button>
            </form>
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
            $tabInvestigationUrl = cfprofileurl((int) ($profileUser['id'] ?? 0), ['tab' => 'investigations']);
            $tabReportsUrl = cfprofileurl((int) ($profileUser['id'] ?? 0), ['tab' => 'reports']);
          ?>
          <div class="profile-shell">
            <aside class="profile-card">
              <div class="profile-avatar"><?= clickfix_h($avatarSeed); ?></div>
              <h3 class="profile-name"><?= clickfix_h((string) ($profileUser['display_name'] ?? '')); ?></h3>
              <div class="profile-nick">@<?= clickfix_h((string) ($profileUser['username'] ?? '')); ?></div>
              <div class="profile-meta">
                <div class="rowx"><span>Rol</span><b><?= clickfix_h((string) ($profileUser['role_label'] ?? '-')); ?></b></div>
                <div class="rowx"><span>REP</span><b class="mono"><?= (int) ($profileUser['reputation'] ?? 0); ?></b></div>
                <div class="rowx"><span>Idioma</span><b class="mono"><?= clickfix_h((string) ($profileUser['preferred_lang'] ?? 'en')); ?></b></div>
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
              </div>
              <?php if ($profileCanEdit): ?>
                <article class="profile-card" style="margin-bottom:10px">
                  <h3 style="margin-top:0">Editar perfil</h3>
                  <p class="mut">Controla que informacion de contacto/cuentas se muestra publicamente.</p>
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
                <?php if ($profileTab === 'reports'): ?>
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
    <?php elseif ($page === 'investigation'): ?>
      <section class="intel-public">
        <?php if ($sharedGraph === null): ?>
          <article class="card"><h2>Investigacion no disponible</h2><p>El enlace compartido no existe o ha sido desactivado.</p></article>
        <?php else: ?>
          <?php
            $sharedGraphData = is_array($sharedGraph['graph'] ?? null) ? $sharedGraph['graph'] : ['nodes' => [], 'edges' => []];
            $sharedNodeCount = count(is_array($sharedGraphData['nodes'] ?? null) ? $sharedGraphData['nodes'] : []);
            $sharedEdgeCount = count(is_array($sharedGraphData['edges'] ?? null) ? $sharedGraphData['edges'] : []);
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
          </article>
          <article class="card">
            <h2>Grafo de investigacion</h2>
            <div class="intel-canvas-wrap" id="shared-canvas-wrap">
              <svg id="shared-svg"></svg>
              <div id="shared-node-layer" class="intel-node-layer"></div>
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
      ?>
      <?php
        $activeGraphData = is_array($selectedInvestigation['graph'] ?? null) ? $selectedInvestigation['graph'] : ['nodes' => [], 'edges' => []];
        $activeNodeCount = count(is_array($activeGraphData['nodes'] ?? null) ? $activeGraphData['nodes'] : []);
        $activeEdgeCount = count(is_array($activeGraphData['edges'] ?? null) ? $activeGraphData['edges'] : []);
        $activeWorkflowStatus = clickfix_investigation_workflow_status((string) ($selectedInvestigation['workflow_status'] ?? 'draft'));
      ?>
      <section class="card intel-shell">
        <div class="intel-topbar">
          <div class="intel-topline">
            <div class="intel-title-wrap">
              <h2>Investigaciones de sitios</h2>
              <p class="mut">Workspace de analisis centrado en entidades, relaciones y evidencia trazable.</p>
            </div>
            <div class="intel-chip-row">
              <span class="intel-chip">casos: <?= count($investigations); ?></span>
              <span class="intel-chip<?= $activeGraphId > 0 ? ' ok' : ''; ?>">activo: <?= $activeGraphId > 0 ? ('#' . $activeGraphId) : 'nuevo'; ?></span>
              <span class="intel-chip warn"><?= clickfix_h(cfworkflowlabel($activeWorkflowStatus, $lang)); ?></span>
              <?php if (!empty($selectedInvestigation['submitted_to_community'])): ?>
                <span class="intel-chip critical">community</span>
              <?php endif; ?>
            </div>
          </div>
          <div class="intel-kpi-grid">
            <div class="intel-kpi"><b>Dominio foco</b><span class="mono"><?= clickfix_h((string) ($selectedInvestigation['site_domain'] ?? '-')); ?></span></div>
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
        <div class="intel-layout">
          <aside class="intel-list">
            <div class="intel-list-head">
              <b>Case queue</b>
              <span>Prioriza por dominio, veredicto y estado de workflow.</span>
            </div>
            <a class="intel-item<?= $activeGraphId === 0 ? ' active' : ''; ?>" href="<?= clickfix_h(cfurl('intel')); ?>">
              <b>Nueva investigacion</b>
              <div class="meta mono">grafo vacio</div>
              <div class="summary">Empieza desde cero y guarda cuando tengas la narrativa.</div>
            </a>
            <?php foreach ($investigations as $graphRow): ?>
              <?php $graphRowId = (int) ($graphRow['id'] ?? 0); ?>
              <a class="intel-item<?= $graphRowId === $activeGraphId ? ' active' : ''; ?>" href="<?= clickfix_h(cfurl('intel', false, ['graph_id' => $graphRowId])); ?>">
                <b><?= clickfix_h((string) ($graphRow['title'] ?? 'Investigacion')); ?></b>
                <div class="meta mono"><?= clickfix_h((string) ($graphRow['site_domain'] ?? '-')); ?> | <?= clickfix_h((string) ($graphRow['verdict'] ?? 'unknown')); ?></div>
                <div class="meta mono"><?= clickfix_h(cfworkflowlabel((string) ($graphRow['workflow_status'] ?? 'draft'), $lang)); ?><?php if (!empty($graphRow['submitted_to_community'])): ?> | community<?php endif; ?></div>
                <div class="meta mono">creada: <?= clickfix_h((string) ($graphRow['created_at'] ?? '')); ?></div>
                <div class="meta mono">actualizada: <?= clickfix_h((string) ($graphRow['updated_at'] ?? '')); ?><?php if (!empty($graphRow['username'])): ?> | <a class="user-link" href="<?= clickfix_h(cfprofileurl((int) ($graphRow['user_id'] ?? 0))); ?>"><?= clickfix_h((string) $graphRow['username']); ?></a><?php endif; ?></div>
                <div class="summary"><?= clickfix_h((string) ($graphRow['summary'] ?? '')); ?></div>
              </a>
            <?php endforeach; ?>
          </aside>
          <section class="intel-editor">
            <div class="intel-editor-section">
              <form id="intel-save-form" method="post">
                <input type="hidden" name="action" value="investigation_save">
                <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                <input type="hidden" name="graph_id" id="intel-graph-id" value="<?= $activeGraphId; ?>">
                <input type="hidden" name="graph_json" id="intel-graph-json" value="<?= clickfix_h($activeGraphJson); ?>">
                <div class="intel-grid">
                  <div><label>Titulo</label><input type="text" name="title" id="intel-title" maxlength="180" value="<?= clickfix_h((string) ($selectedInvestigation['title'] ?? '')); ?>" required></div>
                  <div><label>Dominio principal</label><input type="text" name="site_domain" id="intel-domain" maxlength="180" value="<?= clickfix_h((string) ($selectedInvestigation['site_domain'] ?? '')); ?>" placeholder="ejemplo.com"></div>
                  <div><label>Veredicto</label><select name="verdict" id="intel-verdict">
                    <?php $v = (string) ($selectedInvestigation['verdict'] ?? 'suspicious'); ?>
                    <option value="investigating"<?= $v === 'investigating' ? ' selected' : ''; ?>>investigating</option>
                    <option value="malicious"<?= $v === 'malicious' ? ' selected' : ''; ?>>malicious</option>
                    <option value="suspicious"<?= $v === 'suspicious' ? ' selected' : ''; ?>>suspicious</option>
                    <option value="clean"<?= $v === 'clean' ? ' selected' : ''; ?>>clean</option>
                    <option value="unknown"<?= $v === 'unknown' ? ' selected' : ''; ?>>unknown</option>
                  </select></div>
                  <div><label>Tags globales</label><input type="text" name="tags" id="intel-tags" value="<?= clickfix_h(implode(', ', is_array($selectedInvestigation['tags'] ?? null) ? $selectedInvestigation['tags'] : [])); ?>" placeholder="phishing, fake-captcha, powershell"></div>
                  <div class="intel-grid-full"><label>Resumen de la investigacion</label><textarea name="summary" id="intel-summary" maxlength="5000" placeholder="Explica por que se considera malicioso o no."><?= clickfix_h((string) ($selectedInvestigation['summary'] ?? '')); ?></textarea></div>
                </div>
                <div class="intel-toolbar">
                  <button class="btn" type="submit">Guardar investigacion</button>
                </div>
              </form>
            </div>

            <div class="intel-editor-section">
              <h3>Fuentes de investigacion</h3>
              <p class="mut">Gestion de API keys personales y consultas historicas de enrichment.</p>
              <div class="card-box" style="margin-bottom:10px">
                <h3><?= clickfix_h(cft('intel_api_keys_title')); ?></h3>
                <p class="mut"><?= clickfix_h(cft('intel_api_keys_sub')); ?></p>
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
                        $providerInputId = 'intel-api-key-' . preg_replace('/[^a-z0-9_]/', '_', strtolower($providerKey));
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
                            <input type="hidden" name="graph_id" value="<?= $activeGraphId; ?>">
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
                              <input type="hidden" name="graph_id" value="<?= $activeGraphId; ?>">
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

                <div style="margin-top:16px">
                  <h3>API de plataforma (con API key)</h3>
                  <p class="mut">
                    Genera API keys personales para consumir <code>/api/intel.php</code> y <code>/api/lookup.php</code>.
                    Se guarda solo hash, se puede revocar y tiene expiracion/rate-limit por clave.
                    Documentacion: <a class="user-link" href="api/INTEGRATIONS.md" target="_blank" rel="noreferrer noopener">API INTEGRATIONS</a>.
                  </p>

                  <?php if (is_array($platformApiKeyJustCreated) && !empty($platformApiKeyJustCreated['api_key'])): ?>
                    <?php
                      $apiHost = trim((string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
                      $apiScheme = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') ? 'https' : 'http';
                      $apiBase = $apiScheme . '://' . $apiHost;
                    ?>
                    <div class="api-result">
                      <b>API key nueva (se muestra una sola vez)</b>
                      <div class="mut">Copiala ahora y guardala en un vault seguro. No se volvera a mostrar completa.</div>
                      <pre><?= clickfix_h((string) ($platformApiKeyJustCreated['api_key'] ?? '')); ?></pre>
                      <pre><?= clickfix_h('curl -H "X-API-Key: ' . (string) ($platformApiKeyJustCreated['api_key'] ?? '') . '" "' . $apiBase . '/api/intel.php?view=iocs&limit=50"'); ?></pre>
                    </div>
                  <?php endif; ?>

                  <form method="post" style="margin-top:8px">
                    <input type="hidden" name="action" value="platform_api_key_create">
                    <input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>">
                    <input type="hidden" name="graph_id" value="<?= $activeGraphId; ?>">
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
                                    <input type="hidden" name="graph_id" value="<?= $activeGraphId; ?>">
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
                </div>

                <?php
                  $lookupProviderDefault = (string) ($intelApiLookupResult['provider'] ?? 'virustotal');
                  $lookupTargetDefault = trim((string) ($intelApiLookupResult['target'] ?? (string) ($selectedInvestigation['site_domain'] ?? '')));
                ?>
                <div style="margin-top:10px">
                  <h3><?= clickfix_h(cft('intel_api_lookup_title')); ?></h3>
                  <p class="mut"><?= clickfix_h(cft('intel_api_lookup_sub')); ?></p>
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
                    <b><?= clickfix_h(cft('intel_api_lookup_result')); ?></b>
                    <div class="mono">provider: <?= clickfix_h((string) ($intelApiLookupResult['provider'] ?? '-')); ?> | status: <?= (int) ($intelApiLookupResult['status'] ?? 0); ?> | target: <?= clickfix_h((string) ($intelApiLookupResult['target'] ?? '')); ?> | at: <?= clickfix_h((string) ($intelApiLookupResult['captured_at'] ?? '')); ?></div>
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
                    <?php if (!empty($lookupSummary)): ?>
                      <pre><?= clickfix_h(json_encode($lookupSummary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?: '{}'); ?></pre>
                    <?php endif; ?>
                    <?php if (trim((string) ($intelApiLookupResult['response_json'] ?? '')) !== ''): ?>
                      <pre><?= clickfix_h((string) ($intelApiLookupResult['response_json'] ?? '')); ?></pre>
                    <?php endif; ?>
                  </div>
                <?php endif; ?>

                <div class="api-lookup-history">
                  <h4>Historial de consultas API (guardado automatico)</h4>
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
                          <?php if ($historySummaryPretty !== ''): ?>
                            <pre><?= clickfix_h($historySummaryPretty); ?></pre>
                          <?php endif; ?>
                          <?php if ($historyResponse !== ''): ?>
                            <pre><?= clickfix_h($historyResponse); ?></pre>
                          <?php endif; ?>
                        </div>
                      </details>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <div class="mut">Sin historial todavia para esta investigacion.</div>
                  <?php endif; ?>
                </div>

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

            <div class="intel-editor-section">
              <h3>Mapa relacional</h3>
              <p class="mut">Visualiza la cadena de ataque, mueve entidades y modela el contexto operativo.</p>
              <div class="intel-canvas-wrap" id="intel-canvas-wrap">
                <svg id="intel-svg"></svg>
                <div id="intel-node-layer" class="intel-node-layer"></div>
              </div>
            </div>

            <div class="intel-editor-section">
              <h3>Editor de entidades y relaciones</h3>
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
              <div class="intel-editor-section">
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
                  </div>
                </div>
              </div>
            <?php endif; ?>

            <?php if ($activeGraphId > 0): ?>
              <div class="intel-timeline">
                <h3>Timeline de investigacion (que y cuando)</h3>
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
        <article class="card">
          <h2>Eventos recientes</h2>
          <div class="event-workbench">
            <aside class="event-feed" id="event-feed">
              <?php if (empty($eventWorkbenchRows)): ?>
                <div class="event-empty">Sin eventos recientes.</div>
              <?php else: ?>
                <?php foreach ($eventWorkbenchRows as $eventIndex => $eventRow): ?>
                  <?php
                    $scoreValue = isset($eventRow['score_total']) && is_numeric($eventRow['score_total']) ? (int) $eventRow['score_total'] : 0;
                    $severityClass = cfseverityclass($scoreValue);
                    $firstReason = !empty($eventRow['reason_list']) ? (string) $eventRow['reason_list'][0] : (string) ($eventRow['message'] ?? '');
                  ?>
                  <button type="button" class="event-feed-item<?= $eventIndex === 0 ? ' is-active' : ''; ?>" data-event-index="<?= (int) $eventIndex; ?>">
                    <span class="event-feed-sev <?= clickfix_h($severityClass); ?>"></span>
                    <span class="event-feed-main">
                      <span class="event-feed-host"><?= clickfix_h((string) ($eventRow['hostname'] ?: '-')); ?></span>
                      <span class="event-feed-meta"><?= clickfix_h((string) ($eventRow['activity_at'] ?? $eventRow['received_at'] ?? '')); ?> | <?= $scoreValue; ?>/100</span>
                      <span class="event-feed-reason"><?= clickfix_h($firstReason); ?></span>
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
              <div id="event-empty" class="event-empty">Selecciona un evento para ver el detalle enriquecido.</div>
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
                            <th>IP</th>
                            <th>Score</th>
                            <th>Estado</th>
                            <th>Relacion</th>
                            <th>Abrir</th>
                          </tr>
                        </thead>
                        <tbody id="event-related-body"></tbody>
                      </table>
                    </div>
                  </div>
                <?php endif; ?>
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
                      <a class="btn" href="<?= clickfix_h($adminPreviewUrl); ?>" target="_blank" rel="noopener noreferrer">Abrir pestaÃ±a</a>
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
    <?php endif; ?>

    <?php if ($loggedIn && $page === 'reports' && cfcan($user, 'admin')): ?>
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
        <article class="card"><h2>Desistimientos</h2><table><thead><tr><th>Fecha</th><th>Dominio</th><th>Estado</th><th>Accion</th></tr></thead><tbody><?php foreach ($appeals as $ap): ?><tr><td class="mono"><?= clickfix_h((string) ($ap['created_at'] ?? '')); ?></td><td class="mono"><?= clickfix_h((string) ($ap['domain'] ?? '')); ?></td><td><?= clickfix_h((string) ($ap['status'] ?? 'pending')); ?></td><td><form method="post"><input type="hidden" name="action" value="appeal_status"><input type="hidden" name="csrf_token" value="<?= clickfix_h($csrf); ?>"><input type="hidden" name="appeal_id" value="<?= (int) ($ap['id'] ?? 0); ?>"><select name="status"><option>pending</option><option>approved</option><option>rejected</option></select><button class="btn" type="submit">OK</button></form></td></tr><?php endforeach; ?></tbody></table></article>
        <article class="card"><h2>Solicitudes de acceso</h2><table><thead><tr><th>Email</th><th>Veces</th><th>Ultima</th></tr></thead><tbody><?php foreach ($requests as $rq): ?><tr><td class="mono"><?= clickfix_h((string) ($rq['email'] ?? '')); ?></td><td class="mono"><?= (int) ($rq['request_count'] ?? 1); ?></td><td class="mono"><?= clickfix_h((string) ($rq['last_seen_at'] ?? '')); ?></td></tr><?php endforeach; ?></tbody></table></article>
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
          <thead><tr><th>Email</th><th>Veces</th><th>Primera</th><th>Ultima</th><th>Idioma</th><th>IP</th></tr></thead>
          <tbody>
            <?php foreach ($requests as $rq): ?>
              <tr>
                <td class="mono"><?= clickfix_h((string) ($rq['email'] ?? '')); ?></td>
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
            <div class="side-metric"><span>pendientes</span><b data-live-metric="pending_review"><?= (int) ($metrics['pending_review'] ?? 0); ?></b></div>
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
    const selectedInvestigation = <?= $selectedInvestigationJson; ?>;
    const sharedInvestigation = <?= $sharedGraphJson; ?>;
    const eventWorkbenchData = <?= $eventWorkbenchJson; ?>;
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
    const eventReasons = document.getElementById('event-reasons');
    const eventSnippets = document.getElementById('event-snippets');
    const eventContextTitle = document.getElementById('event-context-title');
    const eventContext = document.getElementById('event-context');
    const eventRaw = document.getElementById('event-raw');
    const eventRelatedLoad = document.getElementById('event-related-load');
    const eventRelatedStatus = document.getElementById('event-related-status');
    const eventRelatedWrap = document.getElementById('event-related-wrap');
    const eventRelatedBody = document.getElementById('event-related-body');
    const canViewExactEventContext = <?= $canViewExactEventContext ? 'true' : 'false'; ?>;
    const eventReviewForm = document.getElementById('event-review-form');
    const eventReviewId = document.getElementById('event-review-id');
    const eventReviewStatus = document.getElementById('event-review-status');
    const eventQuickForms = Array.from(document.querySelectorAll('.event-quick-form'));
    const focusReportId = <?= $focusReportId; ?>;
    const msgScope = document.getElementById('msg-scope');
    const msgClientIds = document.getElementById('msg-client-ids');
    const msgUserIds = document.getElementById('msg-user-ids');
    const homeLeafletCssUrl = <?= json_encode($leafletCssUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    const homeLeafletJsUrl = <?= json_encode($leafletJsUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    let leafletEnsurePromise = null;

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

    const escapeHtml = (value) =>
      String(value || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

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

    function makeGraphRenderer({ wrap, svg, nodeLayer, graph, readOnly, onSelectNode, edgeListSelect, edgeFromSelect, edgeToSelect, nodeListSelect }) {
      if (!wrap || !svg || !nodeLayer) {
        return null;
      }
      const state = {
        graph: normalizeGraphPayload(graph),
        selectedNodeId: null,
        drag: null
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

      function render() {
        const bounds = wrap.getBoundingClientRect();
        const width = Math.max(200, bounds.width);
        const height = Math.max(200, bounds.height);
        svg.setAttribute('viewBox', `0 0 ${Math.round(width)} ${Math.round(height)}`);
        svg.innerHTML = '';
        nodeLayer.innerHTML = '';

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
          svg.appendChild(line);
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
            svg.appendChild(text);
          }
        });

        state.graph.nodes.forEach((node) => {
          const el = document.createElement('div');
          el.className = 'intel-node' + (state.selectedNodeId === node.id ? ' active' : '');
          el.dataset.nodeId = node.id;
          el.style.left = `${node.x}px`;
          el.style.top = `${node.y}px`;
          el.style.background = node.color || '#5dc8ff';
          el.style.cursor = readOnly ? 'pointer' : 'move';
          const hasNotes = String(node.notes || '').trim() !== '';
          const tags = Array.isArray(node.tags) ? node.tags.filter(Boolean) : [];
          el.textContent = `${node.label}${hasNotes ? ' *' : ''}`;
          el.title = `${node.label}${tags.length ? `\nTags: ${tags.join(', ')}` : ''}${hasNotes ? `\nNotas: ${String(node.notes).slice(0, 300)}` : ''}`;
          el.addEventListener('click', (ev) => {
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
              state.selectedNodeId = node.id;
              const rect = wrap.getBoundingClientRect();
              state.drag = {
                id: node.id,
                offsetX: ev.clientX - rect.left - node.x,
                offsetY: ev.clientY - rect.top - node.y
              };
              el.setPointerCapture(ev.pointerId);
            });
          }
          nodeLayer.appendChild(el);
        });

        fillNodeSelect(edgeFromSelect);
        fillNodeSelect(edgeToSelect);
        fillEdgeList();
        fillNodeList();
      }

      if (!readOnly) {
        nodeLayer.addEventListener('pointermove', (ev) => {
          if (!state.drag) return;
          const node = nodeById(state.drag.id);
          if (!node) return;
          const rect = wrap.getBoundingClientRect();
          node.x = Math.max(30, Math.min(rect.width - 30, ev.clientX - rect.left - state.drag.offsetX));
          node.y = Math.max(24, Math.min(rect.height - 24, ev.clientY - rect.top - state.drag.offsetY));
          render();
        });
        nodeLayer.addEventListener('pointerup', () => {
          state.drag = null;
        });
        wrap.addEventListener('click', () => {
          state.selectedNodeId = null;
          render();
          if (typeof onSelectNode === 'function') {
            onSelectNode(null);
          }
        });
      }

      window.addEventListener('resize', () => render());
      render();

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
        td.colSpan = 8;
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
        const relationText = relation.length ? relation.join(' + ') : '-';
        const cells = [
          String(row.id || ''),
          String(row.activity_at || row.received_at || '-'),
          String(row.hostname || '-'),
          String(row.ip || '-'),
          `${Number(row.score_total || 0)}/100`,
          String(row.review_status || 'pending'),
          relationText,
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
      eventItems.forEach((item) => {
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
          div.textContent = String(snippet);
          eventSnippets.appendChild(div);
        });
      } else {
        const div = document.createElement('div');
        div.className = 'event-empty';
        div.textContent = 'Sin snippets almacenados.';
        eventSnippets.appendChild(div);
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
        score_details: event.score_details
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

    if (eventItems.length && eventWorkbenchData.length) {
      eventItems.forEach((item) => {
        item.addEventListener('click', () => {
          renderEventDetail(Number(item.dataset.eventIndex));
        });
      });
      const preferredIndex = focusReportId > 0
        ? eventWorkbenchData.findIndex((entry) => Number(entry?.id || 0) === Number(focusReportId))
        : -1;
      renderEventDetail(preferredIndex >= 0 ? preferredIndex : 0);
    }

    const intelWrap = document.getElementById('intel-canvas-wrap');
    const intelSvg = document.getElementById('intel-svg');
    const intelNodeLayer = document.getElementById('intel-node-layer');
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

    if (intelWrap && intelSvg && intelNodeLayer) {
      const initialGraph = selectedInvestigation?.graph || { nodes: [], edges: [] };
      const editor = makeGraphRenderer({
        wrap: intelWrap,
        svg: intelSvg,
        nodeLayer: intelNodeLayer,
        graph: initialGraph,
        readOnly: false,
        nodeListSelect,
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
    }

    const sharedWrap = document.getElementById('shared-canvas-wrap');
    const sharedSvg = document.getElementById('shared-svg');
    const sharedNodeLayer = document.getElementById('shared-node-layer');
    const sharedNodeLabel = document.getElementById('shared-node-label');
    const sharedNodeTags = document.getElementById('shared-node-tags');
    const sharedNodeNotes = document.getElementById('shared-node-notes');
    if (sharedWrap && sharedSvg && sharedNodeLayer && sharedInvestigation?.graph) {
      makeGraphRenderer({
        wrap: sharedWrap,
        svg: sharedSvg,
        nodeLayer: sharedNodeLayer,
        graph: sharedInvestigation.graph,
        readOnly: true,
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
        'Offline Grid': createOfflineTileLayer(),
        'OpenStreetMap': L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
          attribution: '&copy; OpenStreetMap contributors',
          maxNativeZoom: 19
        }),
        'Carto Light': L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
          attribution: '&copy; OpenStreetMap contributors &copy; CARTO',
          subdomains: 'abcd',
          maxNativeZoom: 20
        }),
        'OpenTopoMap': L.tileLayer('https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png', {
          attribution: '&copy; OpenStreetMap contributors, SRTM | map style: OpenTopoMap (CC-BY-SA)',
          maxNativeZoom: 17
        })
      };
      baseLayers['Offline Grid'].addTo(map);
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

