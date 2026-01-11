<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

$sessionDir = __DIR__ . '/data/sessions';
if (!is_dir($sessionDir)) {
    @mkdir($sessionDir, 0775, true);
}
if (is_dir($sessionDir) && is_writable($sessionDir)) {
    session_save_path($sessionDir);
}

session_start();

$sessionStatus = (is_dir($sessionDir) && is_writable($sessionDir)) ? 'ok' : 'warning';

$supportedLanguages = ['es', 'en'];
$requestedLanguage = (string) ($_GET['lang'] ?? '');
if ($requestedLanguage !== '' && in_array($requestedLanguage, $supportedLanguages, true)) {
    $_SESSION['lang'] = $requestedLanguage;
}
$currentLanguage = (string) ($_SESSION['lang'] ?? 'es');
if (!in_array($currentLanguage, $supportedLanguages, true)) {
    $currentLanguage = 'es';
}

$translations = [
    'es' => [
        'app_title' => 'ClickFix Command Center',
        'last_update' => 'Última actualización',
        'extension_status_unknown' => 'Estado extensión: sin datos',
        'extension_status_activity' => 'Actividad detectada',
        'extension_enabled' => 'Extensión activa',
        'extension_disabled' => 'Extensión pausada',
        'session_active' => 'Sesión',
        'session_none' => 'Sin sesión activa',
        'dashboard_title' => 'Visión 360° de ClickFix',
        'dashboard_subtitle' => 'Monitorea alertas, actividad por país y el estado de listas en una sola vista accionable.',
        'recent_alerts' => 'Alertas recientes',
        'manual_sites' => 'Sitios manuales',
        'coverage' => 'Cobertura',
        'countries_label' => 'países',
        'alerted_sites' => 'Sitios alertados',
        'block_rate' => 'Tasa de bloqueo',
        'block_rate_help' => 'Bloqueos sobre alertas totales',
        'top_countries' => 'Top países',
        'events' => 'eventos',
        'no_geo_data' => 'Sin datos geográficos.',
        'total_alerts' => 'Alertas totales',
        'total_blocks' => 'Bloqueos totales',
        'extension_history' => 'Histórico de la extensión',
        'confirmed_preventions' => 'Prevenciones confirmadas',
        'manual_domains' => 'Dominios cargados manualmente',
        'recent_events' => 'Últimos eventos visibles',
        'pending_review' => 'Pendientes de revisión',
        'containment_efficiency' => 'Eficiencia de contención',
        'public_lists' => 'Listas públicas',
        'visible_to_all' => 'Visibles para todos',
        'allowlist' => 'Allowlist',
        'blocklist' => 'Blocklist',
        'empty_domains' => 'Sin dominios.',
        'appeal_title' => '¿Está tu dominio bloqueado? Realiza el desistimiento aquí',
        'domain' => 'Dominio',
        'appeal_reason' => 'Motivo del desistimiento',
        'contact_optional' => 'Contacto (opcional)',
        'submit_appeal' => 'Enviar desistimiento',
        'admin_lists' => 'Administrar listas',
        'admin_only' => 'Solo administradores',
        'list_type' => 'Tipo de lista',
        'reason' => 'Motivo',
        'add' => 'Agregar',
        'remove' => 'Quitar',
        'suggest_list_changes' => 'Sugerir cambios de listas',
        'analyst_only' => 'Los analistas solo pueden sugerir',
        'send_suggestion' => 'Enviar sugerencia',
        'suggestions_review' => 'Revisión de sugerencias',
        'suggestions_subtitle' => 'Control de cambios propuestos por analistas',
        'no_suggestions' => 'Sin sugerencias registradas.',
        'type' => 'Tipo',
        'requested_by' => 'Solicitado por',
        'approve_apply' => 'Aprobar y aplicar',
        'reject' => 'Rechazar',
        'alert_analytics' => 'Analítica de alertas',
        'latest_reports' => 'Últimos reportes registrados',
        'alerts_by_day' => 'Alertas por día',
        'alerts_by_hour' => 'Alertas por hora',
        'country_distribution' => 'Distribución por país',
        'signal_types' => 'Tipos de señales',
        'recent_detections' => 'Detecciones recientes',
        'no_detections' => 'Sin detecciones con contenido registrado.',
        'blocked' => 'Bloqueado',
        'url' => 'URL',
        'summary' => 'Resumen',
        'detected_signals' => 'Señales detectadas',
        'detected_content' => 'Contenido detectado',
        'full_context' => 'Contexto completo',
        'recent_appeals' => 'Desistimientos recientes',
        'no_requests' => 'Sin solicitudes.',
        'status' => 'Estado',
        'mark_resolved' => 'Marcar como resuelto',
        'approve' => 'Aprobar',
        'reject_appeal' => 'Rechazar',
        'appeal_actions' => 'Acciones',
        'flash_appeal_approved' => 'Desistimiento aprobado.',
        'flash_appeal_rejected' => 'Desistimiento rechazado.',
        'recent_logs' => 'Logs recientes',
        'latest_entries' => 'Últimas entradas',
        'no_logs' => 'Sin registros.',
        'countries' => 'Países',
        'no_data' => 'Sin datos.',
        'manual_sites_title' => 'Sitios manuales',
        'alerted_sites_title' => 'Sitios alertados',
        'quick_guide' => 'Guía de respuesta rápida',
        'guide_item_1' => 'Valida alertas recientes y su contexto completo.',
        'guide_item_2' => 'Actualiza allowlist/blocklist según la evidencia.',
        'guide_item_3' => 'Resuelve desistimientos una vez revisados.',
        'guide_item_4' => 'Monitorea señales repetidas para detectar campañas.',
        'access_panel' => 'Acceso y registro',
        'session_panel' => 'Panel de sesión',
        'access_state' => 'Estado',
        'access_storage' => 'Almacenamiento',
        'access_role' => 'Rol actual',
        'active' => 'Activo',
        'review_permissions' => 'Revisar permisos',
        'not_assigned' => 'No asignado',
        'login' => 'Iniciar sesión',
        'login_hint' => 'Usuario sin distinguir mayúsculas',
        'username' => 'Usuario',
        'password' => 'Contraseña',
        'quick_register' => 'Registro rápido',
        'register_hint' => 'Analistas o administradores',
        'admin_code_optional' => 'Código administrador (opcional)',
        'register' => 'Registrarse',
        'logout' => 'Cerrar sesión',
        'accepted' => 'Aceptado',
        'mark_accepted' => 'Marcar como aceptado',
        'clear_unaccepted' => 'Limpiar no aceptadas',
        'clear_unaccepted_help' => 'Elimina detecciones recientes sin validar.',
        'log_table_title' => 'Entradas de alertas',
        'log_table_empty' => 'Sin entradas estructuradas.',
        'log_col_time' => 'Fecha',
        'log_col_url' => 'URL',
        'log_col_host' => 'Host',
        'log_col_message' => 'Mensaje',
        'log_col_detected' => 'Detectado',
        'log_col_blocked' => 'Bloqueado',
        'log_col_country' => 'País',
        'confirm_clear' => '¿Eliminar detecciones no aceptadas?',
        'intel_section' => 'Fuentes de inteligencia',
        'intel_subtitle' => 'Scraping de referencia para enriquecer señales',
        'intel_refresh' => 'Actualizar',
        'intel_last_fetch' => 'Última sincronización',
        'intel_status' => 'Estado',
        'intel_status_ok' => 'Sincronizado',
        'intel_status_error' => 'Error',
        'intel_highlights' => 'Patrones destacados',
        'language' => 'Idioma',
        'flash_invalid_session' => 'Sesión inválida, recarga la página.',
        'flash_required_credentials' => 'Usuario y contraseña son obligatorios.',
        'flash_duplicate_user' => 'El usuario ya existe con una variación de mayúsculas/minúsculas.',
        'flash_register_success' => 'Registro completado. Ahora puedes iniciar sesión.',
        'flash_register_failure' => 'No se pudo registrar. Usa otro usuario.',
        'flash_login_success' => 'Sesión iniciada.',
        'flash_invalid_credentials' => 'Credenciales inválidas.',
        'flash_logout' => 'Sesión cerrada.',
        'flash_default_admin' => 'Usuario admin creado: admin / ',
        'flash_appeal_required' => 'Dominio y motivo son obligatorios.',
        'flash_appeal_success' => 'Desistimiento enviado. Revisaremos tu solicitud.',
        'flash_admin_required' => 'Se requiere un usuario administrador.',
        'flash_list_updated' => 'Lista actualizada.',
        'flash_list_failed' => 'No se pudo actualizar la lista.',
        'flash_login_required' => 'Necesitas iniciar sesión para sugerir cambios.',
        'flash_suggestion_admin' => 'Sugerencia registrada. Puedes aprobarla en la sección de revisión.',
        'flash_suggestion_user' => 'Sugerencia enviada. Un administrador la revisará.',
        'flash_suggestion_reviewed' => 'La sugerencia ya fue revisada.',
        'flash_suggestion_applied' => 'Sugerencia aprobada y aplicada.',
        'flash_suggestion_rejected' => 'Sugerencia rechazada.',
        'flash_appeal_updated' => 'Desistimiento actualizado.',
        'flash_detection_accepted' => 'Detección marcada como aceptada.',
        'flash_detection_cleared' => 'Detecciones no aceptadas eliminadas.',
        'flash_detection_clear_failed' => 'No se pudieron limpiar las detecciones.',
        'no_domain' => 'Sin dominio'
    ],
    'en' => [
        'app_title' => 'ClickFix Command Center',
        'last_update' => 'Last update',
        'extension_status_unknown' => 'Extension status: no data',
        'extension_status_activity' => 'Activity detected',
        'extension_enabled' => 'Extension enabled',
        'extension_disabled' => 'Extension paused',
        'session_active' => 'Session',
        'session_none' => 'No active session',
        'dashboard_title' => 'ClickFix 360° overview',
        'dashboard_subtitle' => 'Monitor alerts, country activity, and list status in a single actionable view.',
        'recent_alerts' => 'Recent alerts',
        'manual_sites' => 'Manual sites',
        'coverage' => 'Coverage',
        'countries_label' => 'countries',
        'alerted_sites' => 'Alerted sites',
        'block_rate' => 'Block rate',
        'block_rate_help' => 'Blocks over total alerts',
        'top_countries' => 'Top countries',
        'events' => 'events',
        'no_geo_data' => 'No geographic data.',
        'total_alerts' => 'Total alerts',
        'total_blocks' => 'Total blocks',
        'extension_history' => 'Extension history',
        'confirmed_preventions' => 'Confirmed preventions',
        'manual_domains' => 'Manually loaded domains',
        'recent_events' => 'Latest visible events',
        'pending_review' => 'Pending review',
        'containment_efficiency' => 'Containment efficiency',
        'public_lists' => 'Public lists',
        'visible_to_all' => 'Visible to everyone',
        'allowlist' => 'Allowlist',
        'blocklist' => 'Blocklist',
        'empty_domains' => 'No domains.',
        'appeal_title' => 'Is your domain blocked? Submit an appeal here',
        'domain' => 'Domain',
        'appeal_reason' => 'Appeal reason',
        'contact_optional' => 'Contact (optional)',
        'submit_appeal' => 'Submit appeal',
        'admin_lists' => 'Manage lists',
        'admin_only' => 'Admins only',
        'list_type' => 'List type',
        'reason' => 'Reason',
        'add' => 'Add',
        'remove' => 'Remove',
        'suggest_list_changes' => 'Suggest list changes',
        'analyst_only' => 'Analysts can only suggest',
        'send_suggestion' => 'Send suggestion',
        'suggestions_review' => 'Suggestion review',
        'suggestions_subtitle' => 'Track proposed changes from analysts',
        'no_suggestions' => 'No suggestions recorded.',
        'type' => 'Type',
        'requested_by' => 'Requested by',
        'approve_apply' => 'Approve and apply',
        'reject' => 'Reject',
        'alert_analytics' => 'Alert analytics',
        'latest_reports' => 'Latest reports',
        'alerts_by_day' => 'Alerts per day',
        'alerts_by_hour' => 'Alerts per hour',
        'country_distribution' => 'Country distribution',
        'signal_types' => 'Signal types',
        'recent_detections' => 'Recent detections',
        'no_detections' => 'No detections with recorded content.',
        'blocked' => 'Blocked',
        'url' => 'URL',
        'summary' => 'Summary',
        'detected_signals' => 'Detected signals',
        'detected_content' => 'Detected content',
        'full_context' => 'Full context',
        'recent_appeals' => 'Recent appeals',
        'no_requests' => 'No requests.',
        'status' => 'Status',
        'mark_resolved' => 'Mark as resolved',
        'approve' => 'Approve',
        'reject_appeal' => 'Reject',
        'appeal_actions' => 'Actions',
        'flash_appeal_approved' => 'Appeal approved.',
        'flash_appeal_rejected' => 'Appeal rejected.',
        'recent_logs' => 'Recent logs',
        'latest_entries' => 'Latest entries',
        'no_logs' => 'No logs.',
        'countries' => 'Countries',
        'no_data' => 'No data.',
        'manual_sites_title' => 'Manual sites',
        'alerted_sites_title' => 'Alerted sites',
        'quick_guide' => 'Rapid response guide',
        'guide_item_1' => 'Validate recent alerts and full context.',
        'guide_item_2' => 'Update allowlist/blocklist based on evidence.',
        'guide_item_3' => 'Resolve appeals after review.',
        'guide_item_4' => 'Monitor repeated signals to detect campaigns.',
        'access_panel' => 'Access & registration',
        'session_panel' => 'Session panel',
        'access_state' => 'State',
        'access_storage' => 'Storage',
        'access_role' => 'Current role',
        'active' => 'Active',
        'review_permissions' => 'Check permissions',
        'not_assigned' => 'Not assigned',
        'login' => 'Sign in',
        'login_hint' => 'Username is case-insensitive',
        'username' => 'Username',
        'password' => 'Password',
        'quick_register' => 'Quick registration',
        'register_hint' => 'Analysts or administrators',
        'admin_code_optional' => 'Admin code (optional)',
        'register' => 'Register',
        'logout' => 'Sign out',
        'accepted' => 'Accepted',
        'mark_accepted' => 'Mark as accepted',
        'clear_unaccepted' => 'Clear unaccepted',
        'clear_unaccepted_help' => 'Remove recent detections that are not validated.',
        'log_table_title' => 'Alert entries',
        'log_table_empty' => 'No structured entries.',
        'log_col_time' => 'Date',
        'log_col_url' => 'URL',
        'log_col_host' => 'Host',
        'log_col_message' => 'Message',
        'log_col_detected' => 'Detected',
        'log_col_blocked' => 'Blocked',
        'log_col_country' => 'Country',
        'confirm_clear' => 'Remove unaccepted detections?',
        'intel_section' => 'Intel sources',
        'intel_subtitle' => 'Reference scraping to enrich signals',
        'intel_refresh' => 'Refresh',
        'intel_last_fetch' => 'Last sync',
        'intel_status' => 'Status',
        'intel_status_ok' => 'Synced',
        'intel_status_error' => 'Error',
        'intel_highlights' => 'Highlighted patterns',
        'language' => 'Language',
        'flash_invalid_session' => 'Invalid session, reload the page.',
        'flash_required_credentials' => 'Username and password are required.',
        'flash_duplicate_user' => 'The username already exists with different casing.',
        'flash_register_success' => 'Registration complete. You can sign in now.',
        'flash_register_failure' => 'Registration failed. Try another username.',
        'flash_login_success' => 'Signed in.',
        'flash_invalid_credentials' => 'Invalid credentials.',
        'flash_logout' => 'Signed out.',
        'flash_default_admin' => 'Admin user created: admin / ',
        'flash_appeal_required' => 'Domain and reason are required.',
        'flash_appeal_success' => 'Appeal submitted. We will review it.',
        'flash_admin_required' => 'Administrator access required.',
        'flash_list_updated' => 'List updated.',
        'flash_list_failed' => 'Could not update the list.',
        'flash_login_required' => 'You must sign in to suggest changes.',
        'flash_suggestion_admin' => 'Suggestion recorded. You can approve it in the review section.',
        'flash_suggestion_user' => 'Suggestion sent. An administrator will review it.',
        'flash_suggestion_reviewed' => 'The suggestion has already been reviewed.',
        'flash_suggestion_applied' => 'Suggestion approved and applied.',
        'flash_suggestion_rejected' => 'Suggestion rejected.',
        'flash_appeal_updated' => 'Appeal updated.',
        'flash_detection_accepted' => 'Detection marked as accepted.',
        'flash_detection_cleared' => 'Unaccepted detections cleared.',
        'flash_detection_clear_failed' => 'Could not clear detections.',
        'no_domain' => 'No domain'
    ]
];

function t(array $translations, string $lang, string $key): string
{
    return $translations[$lang][$key] ?? $translations['es'][$key] ?? $key;
}

$intelSources = [
    [
        'id' => 'hudsonrock',
        'label' => 'Hudson Rock',
        'url' => 'https://www.hudsonrock.com/blog/5766'
    ],
    [
        'id' => 'clickfix-carson',
        'label' => 'ClickFix Carson',
        'url' => 'https://clickfix.carsonww.com/'
    ],
    [
        'id' => 'clickgrab-techniques',
        'label' => 'ClickGrab Techniques',
        'url' => 'https://mhaggis.github.io/ClickGrab/techniques.html'
    ],
    [
        'id' => 'clickfix-patterns',
        'label' => 'ClickFix Patterns',
        'url' => 'https://don-san-sec.github.io/clickfix-patterns/'
    ]
];
$intelCachePath = __DIR__ . '/data/intel-cache.json';
$intelTtlSeconds = 3600;

function loadIntelCache(string $path): array
{
    if (!is_readable($path)) {
        return [];
    }
    $decoded = json_decode((string) file_get_contents($path), true);
    return is_array($decoded) ? $decoded : [];
}

function saveIntelCache(string $path, array $payload): void
{
    $dir = dirname($path);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}

function parseIntelHtml(string $html): array
{
    $title = '';
    $description = '';
    $highlights = [];
    if (!class_exists('DOMDocument')) {
        return ['title' => $title, 'description' => $description, 'highlights' => $highlights];
    }
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    if ($dom->loadHTML($html)) {
        $titleNode = $dom->getElementsByTagName('title')->item(0);
        if ($titleNode) {
            $title = trim((string) $titleNode->textContent);
        }
        foreach ($dom->getElementsByTagName('meta') as $meta) {
            $name = strtolower((string) $meta->getAttribute('name'));
            if ($name === 'description') {
                $description = trim((string) $meta->getAttribute('content'));
                break;
            }
        }
        foreach (['h2', 'h3', 'li'] as $tag) {
            foreach ($dom->getElementsByTagName($tag) as $node) {
                $text = trim((string) $node->textContent);
                if ($text !== '' && !in_array($text, $highlights, true)) {
                    $highlights[] = $text;
                }
                if (count($highlights) >= 6) {
                    break 2;
                }
            }
        }
    }
    libxml_clear_errors();
    return ['title' => $title, 'description' => $description, 'highlights' => $highlights];
}

function fetchIntelSources(array $sources): array
{
    $entries = [];
    $allowUrlFopen = filter_var((string) ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN);
    foreach ($sources as $source) {
        $html = '';
        if ($allowUrlFopen) {
            $context = stream_context_create([
                'http' => [
                    'timeout' => 6,
                    'header' => "User-Agent: ClickFixDashboard/1.0\r\n"
                ]
            ]);
            $html = (string) (@file_get_contents((string) $source['url'], false, $context) ?: '');
        }
        $payload = [
            'id' => (string) ($source['id'] ?? ''),
            'label' => (string) ($source['label'] ?? ''),
            'url' => (string) ($source['url'] ?? ''),
            'title' => '',
            'description' => '',
            'highlights' => [],
            'status' => 'error',
            'fetched_at' => gmdate('c')
        ];
        if (is_string($html) && $html !== '') {
            $parsed = parseIntelHtml($html);
            $payload['title'] = $parsed['title'];
            $payload['description'] = $parsed['description'];
            $payload['highlights'] = $parsed['highlights'];
            $payload['status'] = 'ok';
        }
        $entries[] = $payload;
    }
    return $entries;
}

$dbPath = __DIR__ . '/data/clickfix.sqlite';
$schemaPath = null;
$preferredSchema = __DIR__ . '/data/clickfix.sql';
if (is_readable($preferredSchema)) {
    $schemaPath = $preferredSchema;
} else {
    $schemaCandidates = glob(__DIR__ . '/data/*.sql') ?: [];
    foreach ($schemaCandidates as $candidate) {
        if (is_readable($candidate)) {
            $schemaPath = $candidate;
            break;
        }
    }
}

$defaultSchemaSql = <<<SQL
CREATE TABLE IF NOT EXISTS reports (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    received_at TEXT NOT NULL,
    url TEXT,
    hostname TEXT,
    message TEXT,
    detected_content TEXT,
    full_context TEXT,
    signals_json TEXT,
    blocked INTEGER DEFAULT 0,
    accepted INTEGER DEFAULT 0,
    accepted_by INTEGER,
    accepted_at TEXT,
    user_agent TEXT,
    ip TEXT,
    country TEXT
);

CREATE TABLE IF NOT EXISTS stats (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    received_at TEXT NOT NULL,
    enabled INTEGER,
    alert_count INTEGER,
    block_count INTEGER,
    manual_sites_json TEXT,
    country TEXT
);

CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    created_at TEXT NOT NULL,
    username TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    role TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS appeals (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    created_at TEXT NOT NULL,
    domain TEXT NOT NULL,
    reason TEXT NOT NULL,
    contact TEXT,
    status TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS list_actions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    created_at TEXT NOT NULL,
    user_id INTEGER,
    action TEXT NOT NULL,
    list_type TEXT NOT NULL,
    domain TEXT NOT NULL,
    reason TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS list_suggestions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    created_at TEXT NOT NULL,
    user_id INTEGER,
    list_type TEXT NOT NULL,
    domain TEXT NOT NULL,
    reason TEXT NOT NULL,
    status TEXT NOT NULL
);
SQL;

$stats = [
    'total_alerts' => 0,
    'total_blocks' => 0,
    'manual_sites' => [],
    'countries' => [],
    'last_update' => null,
    'extension_enabled' => null,
    'recent_count' => 0,
    'alert_sites' => []
];

$recentDetections = [];
$alertSites = [];
$alertsitesFile = __DIR__ . '/alertsites';
$blocklistFile = __DIR__ . '/clickfixlist';
$allowlistFile = __DIR__ . '/clickfixallowlist';
$reportLogEntries = [];
$reportLogStructured = [];
$debugLogEntries = [];
$reportLogCountries = [];
$flashErrors = [];
$flashNotices = [];
$currentUser = null;
$adminCode = getenv('CLICKFIX_ADMIN_CODE') ?: '24091238460913470129384701!92384709123874!';
$chartData = [
    'daily' => [],
    'hourly' => array_fill(0, 24, 0),
    'countries' => [],
    'signals' => []
];

$signalLabels = [
    'mismatch' => 'Discrepancia',
    'commandMatch' => 'Comando',
    'winRHint' => 'Win + R',
    'winXHint' => 'Win + X',
    'browserErrorHint' => 'Error navegador',
    'fixActionHint' => 'Acción de arreglo',
    'captchaHint' => 'Captcha',
    'consoleHint' => 'Consola',
    'shellHint' => 'Shell',
    'pasteSequenceHint' => 'Secuencia pegado',
    'fileExplorerHint' => 'Explorador',
    'copyTriggerHint' => 'Disparador copia',
    'evasionHint' => 'Evasión'
];

function loadListFile(string $path): array
{
    if (!is_readable($path)) {
        return [];
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES) ?: [];
    $items = [];
    foreach ($lines as $line) {
        $line = trim((string) $line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        $items[] = $line;
    }
    return array_values(array_unique($items));
}

function loadLogEntries(string $path, int $limit = 50): array
{
    if (!is_readable($path)) {
        return [];
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES) ?: [];
    if ($limit > 0) {
        $lines = array_slice($lines, -$limit);
    }
    $entries = [];
    foreach ($lines as $line) {
        $decoded = json_decode($line, true);
        if (is_array($decoded)) {
            unset($decoded['ip']);
            $entries[] = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            continue;
        }
        $entries[] = $line;
    }
    return array_reverse($entries);
}

function loadLogCountries(string $path, int $limit = 200): array
{
    if (!is_readable($path)) {
        return [];
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES) ?: [];
    if ($limit > 0) {
        $lines = array_slice($lines, -$limit);
    }
    $counts = [];
    foreach ($lines as $line) {
        $decoded = json_decode($line, true);
        if (!is_array($decoded)) {
            continue;
        }
        $country = strtoupper((string) ($decoded['country'] ?? ''));
        if ($country === '') {
            continue;
        }
        $counts[$country] = ($counts[$country] ?? 0) + 1;
    }
    arsort($counts);
    return $counts;
}

function loadStructuredLogEntries(string $path, int $limit = 50): array
{
    if (!is_readable($path)) {
        return [];
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES) ?: [];
    if ($limit > 0) {
        $lines = array_slice($lines, -$limit);
    }
    $entries = [];
    foreach ($lines as $line) {
        $decoded = json_decode($line, true);
        if (!is_array($decoded)) {
            continue;
        }
        $timestampRaw = $decoded['timestamp'] ?? null;
        $timestamp = '';
        if (is_numeric($timestampRaw)) {
            $timestamp = gmdate('Y-m-d H:i:s', (int) ((int) $timestampRaw / 1000));
        } elseif (!empty($decoded['received_at'])) {
            $timestamp = (string) $decoded['received_at'];
        }
        $entries[] = [
            'received_at' => $timestamp !== '' ? $timestamp : (string) ($decoded['received_at'] ?? ''),
            'url' => (string) ($decoded['url'] ?? ''),
            'hostname' => (string) ($decoded['hostname'] ?? ''),
            'message' => (string) ($decoded['message'] ?? ''),
            'detected_content' => (string) ($decoded['detected_content'] ?? ''),
            'blocked' => !empty($decoded['blocked']),
            'country' => (string) ($decoded['country'] ?? '')
        ];
    }
    return array_reverse($entries);
}

function ensureAdminTables(PDO $pdo): void
{
    $statements = [
        'CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            created_at TEXT NOT NULL,
            username TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            role TEXT NOT NULL
        )',
        'CREATE TABLE IF NOT EXISTS appeals (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            created_at TEXT NOT NULL,
            domain TEXT NOT NULL,
            reason TEXT NOT NULL,
            contact TEXT,
            status TEXT NOT NULL
        )',
        'CREATE TABLE IF NOT EXISTS list_actions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            created_at TEXT NOT NULL,
            user_id INTEGER,
            action TEXT NOT NULL,
            list_type TEXT NOT NULL,
            domain TEXT NOT NULL,
            reason TEXT NOT NULL
        )',
        'CREATE TABLE IF NOT EXISTS list_suggestions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            created_at TEXT NOT NULL,
            user_id INTEGER,
            list_type TEXT NOT NULL,
            domain TEXT NOT NULL,
            reason TEXT NOT NULL,
            status TEXT NOT NULL
        )'
    ];
    foreach ($statements as $statement) {
        $pdo->exec($statement);
    }

    $columns = $pdo->query('PRAGMA table_info(users)')->fetchAll(PDO::FETCH_ASSOC);
    $existing = [];
    foreach ($columns as $column) {
        $existing[(string) ($column['name'] ?? '')] = true;
    }
    if (!isset($existing['created_at'])) {
        $pdo->exec('ALTER TABLE users ADD COLUMN created_at TEXT');
    }
    if (!isset($existing['role'])) {
        $pdo->exec('ALTER TABLE users ADD COLUMN role TEXT');
    }

    $columns = $pdo->query('PRAGMA table_info(appeals)')->fetchAll(PDO::FETCH_ASSOC);
    $existing = [];
    foreach ($columns as $column) {
        $existing[(string) ($column['name'] ?? '')] = true;
    }
    if (!isset($existing['contact'])) {
        $pdo->exec('ALTER TABLE appeals ADD COLUMN contact TEXT');
    }
    if (!isset($existing['status'])) {
        $pdo->exec('ALTER TABLE appeals ADD COLUMN status TEXT');
    }

    $columns = $pdo->query('PRAGMA table_info(reports)')->fetchAll(PDO::FETCH_ASSOC);
    $existing = [];
    foreach ($columns as $column) {
        $existing[(string) ($column['name'] ?? '')] = true;
    }
    if (!isset($existing['accepted'])) {
        $pdo->exec('ALTER TABLE reports ADD COLUMN accepted INTEGER DEFAULT 0');
    }
    if (!isset($existing['accepted_by'])) {
        $pdo->exec('ALTER TABLE reports ADD COLUMN accepted_by INTEGER');
    }
    if (!isset($existing['accepted_at'])) {
        $pdo->exec('ALTER TABLE reports ADD COLUMN accepted_at TEXT');
    }
}

function ensureDefaultAdmin(PDO $pdo, array &$flashNotices, array $translations, string $language): void
{
    $result = $pdo->query('SELECT COUNT(*) as total FROM users')->fetch(PDO::FETCH_ASSOC);
    $total = (int) ($result['total'] ?? 0);
    if ($total > 0) {
        return;
    }
    $password = 'P@ssword!123#';
    $statement = $pdo->prepare(
        'INSERT INTO users (created_at, username, password_hash, role)
         VALUES (:created_at, :username, :password_hash, :role)'
    );
    $statement->execute([
        ':created_at' => gmdate('c'),
        ':username' => 'admin',
        ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
        ':role' => 'admin'
    ]);
    $flashNotices[] = t($translations, $language, 'flash_default_admin') . $password;
}

function ensureDatabase(string $dbPath, ?string $schemaPath, string $schemaSqlFallback): void
{
    if (file_exists($dbPath)) {
        return;
    }
    $dataDir = dirname($dbPath);
    if (!is_dir($dataDir)) {
        @mkdir($dataDir, 0775, true);
    }
    try {
        $pdo = new PDO('sqlite:' . $dbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $schemaSql = null;
        if ($schemaPath !== null) {
            $schemaSql = file_get_contents($schemaPath);
        }
        if (!is_string($schemaSql) || $schemaSql === '') {
            $schemaSql = $schemaSqlFallback;
        }
        $pdo->exec($schemaSql);
        ensureAdminTables($pdo);
    } catch (Throwable $exception) {
        return;
    }
}

function normalizeDomain(string $domain): string
{
    $trimmed = trim($domain);
    if ($trimmed === '') {
        return '';
    }
    $trimmed = strtolower($trimmed);
    $trimmed = preg_replace('/^https?:\/\//', '', $trimmed);
    $trimmed = preg_replace('/\/.*$/', '', $trimmed);
    return $trimmed ?? '';
}

function isValidDomain(string $domain): bool
{
    return $domain !== '' && preg_match('/^[a-z0-9.-]+$/i', $domain) === 1;
}

function ensureListFile(string $path): void
{
    if (is_readable($path)) {
        return;
    }
    @file_put_contents($path, "# Managed ClickFix list\n", FILE_APPEND | LOCK_EX);
}

function updateListFile(string $path, string $domain, string $mode): bool
{
    ensureListFile($path);
    $lines = is_readable($path) ? (file($path, FILE_IGNORE_NEW_LINES) ?: []) : [];
    $normalized = strtolower($domain);
    $existing = [];
    foreach ($lines as $line) {
        $line = trim((string) $line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        $existing[strtolower($line)] = $line;
    }
    if ($mode === 'add') {
        if (isset($existing[$normalized])) {
            return true;
        }
        $lines[] = $domain;
    } else {
        if (!isset($existing[$normalized])) {
            return true;
        }
        $lines = array_filter($lines, static function ($line) use ($normalized) {
            $line = trim((string) $line);
            if ($line === '' || str_starts_with($line, '#')) {
                return true;
            }
            return strtolower($line) !== $normalized;
        });
    }
    $content = implode(PHP_EOL, $lines);
    if ($content !== '') {
        $content .= PHP_EOL;
    }
    return file_put_contents($path, $content, LOCK_EX) !== false;
}

function requireCsrfToken(): ?string
{
    return $_SESSION['csrf_token'] ?? null;
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = [
        'count' => 0,
        'last' => 0
    ];
}

$alertSites = loadListFile($alertsitesFile);
$stats['alert_sites'] = $alertSites;
$reportLogEntries = loadLogEntries(__DIR__ . '/clickfix-report.log', 60);
$reportLogStructured = loadStructuredLogEntries(__DIR__ . '/clickfix-report.log', 40);
$debugLogEntries = loadLogEntries(__DIR__ . '/clickfix-debug.log', 60);
$reportLogCountries = loadLogCountries(__DIR__ . '/clickfix-report.log', 200);

$intelCache = loadIntelCache($intelCachePath);
$intelUpdatedAt = (string) ($intelCache['updated_at'] ?? '');
$intelEntries = is_array($intelCache['entries'] ?? null) ? $intelCache['entries'] : [];
$shouldRefreshIntel = false;
if ($intelUpdatedAt === '') {
    $shouldRefreshIntel = true;
} else {
    $updatedTimestamp = strtotime($intelUpdatedAt);
    if ($updatedTimestamp === false || (time() - $updatedTimestamp) > $intelTtlSeconds) {
        $shouldRefreshIntel = true;
    }
}

ensureDatabase($dbPath, $schemaPath, $defaultSchemaSql);

$pdo = null;
if (is_readable($dbPath)) {
    try {
        $pdo = new PDO('sqlite:' . $dbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        ensureAdminTables($pdo);
        ensureDefaultAdmin($pdo, $flashNotices, $translations, $currentLanguage);
    } catch (Throwable $exception) {
        $pdo = null;
    }
}

if ($pdo instanceof PDO && isset($_SESSION['user_id'])) {
    $statement = $pdo->prepare('SELECT id, username, role FROM users WHERE id = :id');
    $statement->execute([':id' => (int) $_SESSION['user_id']]);
    $currentUser = $statement->fetch(PDO::FETCH_ASSOC) ?: null;
    if (!$currentUser) {
        unset($_SESSION['user_id']);
    }
}
$isAdmin = $currentUser && ($currentUser['role'] ?? '') === 'admin';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = (string) ($_POST['csrf_token'] ?? '');
    if (!hash_equals(requireCsrfToken() ?? '', $csrfToken)) {
        $flashErrors[] = t($translations, $currentLanguage, 'flash_invalid_session');
    } else {
        $now = time();
        $attempts = $_SESSION['login_attempts'] ?? ['count' => 0, 'last' => 0];
        if (!is_array($attempts)) {
            $attempts = ['count' => 0, 'last' => 0];
        }
        if ($now - (int) ($attempts['last'] ?? 0) > 600) {
            $attempts = ['count' => 0, 'last' => $now];
        }

        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'register' && $pdo instanceof PDO) {
            $username = trim((string) ($_POST['username'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');
            $adminInput = trim((string) ($_POST['admin_code'] ?? ''));
            $username = mb_substr($username, 0, 64);
            $password = mb_substr($password, 0, 128);
            if ($username === '' || $password === '') {
                $flashErrors[] = t($translations, $currentLanguage, 'flash_required_credentials');
            } else {
                $role = ($adminCode !== '' && hash_equals($adminCode, $adminInput)) ? 'admin' : 'analyst';
                try {
                    $existingUser = $pdo->prepare('SELECT id FROM users WHERE LOWER(username) = LOWER(:username)');
                    $existingUser->execute([':username' => $username]);
                    if ($existingUser->fetch(PDO::FETCH_ASSOC)) {
                        $flashErrors[] = t($translations, $currentLanguage, 'flash_duplicate_user');
                        throw new RuntimeException('duplicate user');
                    }
                    $statement = $pdo->prepare(
                        'INSERT INTO users (created_at, username, password_hash, role)
                         VALUES (:created_at, :username, :password_hash, :role)'
                    );
                    $statement->execute([
                        ':created_at' => gmdate('c'),
                        ':username' => $username,
                        ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
                        ':role' => $role
                    ]);
                    $flashNotices[] = t($translations, $currentLanguage, 'flash_register_success');
                } catch (Throwable $exception) {
                    if (empty($flashErrors)) {
                        $flashErrors[] = t($translations, $currentLanguage, 'flash_register_failure');
                    }
                }
            }
        } elseif ($action === 'login' && $pdo instanceof PDO) {
            $username = trim((string) ($_POST['username'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');
            $username = mb_substr($username, 0, 64);
            $password = mb_substr($password, 0, 128);
            $rateLimited = ($attempts['count'] ?? 0) >= 8;
            if ($rateLimited) {
                $flashErrors[] = t($translations, $currentLanguage, 'flash_invalid_credentials');
                $_SESSION['login_attempts'] = $attempts;
            }
            $statement = $pdo->prepare(
                'SELECT id, username, role, password_hash FROM users WHERE LOWER(username) = LOWER(:username)'
            );
            if (!$rateLimited) {
                $statement->execute([':username' => $username]);
            }
            $user = $rateLimited ? null : $statement->fetch(PDO::FETCH_ASSOC);
            if ($user && password_verify($password, (string) $user['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = (int) $user['id'];
                $currentUser = ['id' => $user['id'], 'username' => $user['username'], 'role' => $user['role']];
                $flashNotices[] = t($translations, $currentLanguage, 'flash_login_success');
                $_SESSION['login_attempts'] = ['count' => 0, 'last' => $now];
            } else {
                if (!$rateLimited) {
                    $flashErrors[] = t($translations, $currentLanguage, 'flash_invalid_credentials');
                }
                $attempts['count'] = (int) ($attempts['count'] ?? 0) + 1;
                $attempts['last'] = $now;
                $_SESSION['login_attempts'] = $attempts;
            }
        } elseif ($action === 'logout') {
            unset($_SESSION['user_id']);
            $currentUser = null;
            $flashNotices[] = t($translations, $currentLanguage, 'flash_logout');
        } elseif ($action === 'appeal' && $pdo instanceof PDO) {
            $domain = normalizeDomain((string) ($_POST['domain'] ?? ''));
            $reason = trim((string) ($_POST['reason'] ?? ''));
            $contact = trim((string) ($_POST['contact'] ?? ''));
            if (!isValidDomain($domain) || $reason === '') {
                $flashErrors[] = t($translations, $currentLanguage, 'flash_appeal_required');
            } else {
                $statement = $pdo->prepare(
                    'INSERT INTO appeals (created_at, domain, reason, contact, status)
                     VALUES (:created_at, :domain, :reason, :contact, :status)'
                );
                $statement->execute([
                    ':created_at' => gmdate('c'),
                    ':domain' => $domain,
                    ':reason' => $reason,
                    ':contact' => $contact,
                    ':status' => 'open'
                ]);
                $flashNotices[] = t($translations, $currentLanguage, 'flash_appeal_success');
            }
        } elseif ($action === 'list_action' && $pdo instanceof PDO) {
            $isAdmin = $currentUser && ($currentUser['role'] ?? '') === 'admin';
            $domain = normalizeDomain((string) ($_POST['domain'] ?? ''));
            $reason = trim((string) ($_POST['reason'] ?? ''));
            $listType = (string) ($_POST['list_type'] ?? '');
            $mode = (string) ($_POST['mode'] ?? '');
            if (!$isAdmin) {
                $flashErrors[] = t($translations, $currentLanguage, 'flash_admin_required');
            } elseif (!isValidDomain($domain) || $reason === '') {
                $flashErrors[] = t($translations, $currentLanguage, 'flash_appeal_required');
            } else {
                $listPath = $listType === 'allow' ? $allowlistFile : $blocklistFile;
                $ok = updateListFile($listPath, $domain, $mode === 'remove' ? 'remove' : 'add');
                if ($ok) {
                    $statement = $pdo->prepare(
                        'INSERT INTO list_actions (created_at, user_id, action, list_type, domain, reason)
                         VALUES (:created_at, :user_id, :action, :list_type, :domain, :reason)'
                    );
                    $statement->execute([
                        ':created_at' => gmdate('c'),
                        ':user_id' => (int) ($currentUser['id'] ?? 0),
                        ':action' => $mode === 'remove' ? 'remove' : 'add',
                        ':list_type' => $listType,
                        ':domain' => $domain,
                        ':reason' => $reason
                    ]);
                    $flashNotices[] = t($translations, $currentLanguage, 'flash_list_updated');
                } else {
                    $flashErrors[] = t($translations, $currentLanguage, 'flash_list_failed');
                }
            }
        } elseif ($action === 'list_suggest' && $pdo instanceof PDO) {
            $isAdmin = $currentUser && ($currentUser['role'] ?? '') === 'admin';
            $domain = normalizeDomain((string) ($_POST['domain'] ?? ''));
            $reason = trim((string) ($_POST['reason'] ?? ''));
            $listType = (string) ($_POST['list_type'] ?? '');
            if (!$currentUser) {
                $flashErrors[] = t($translations, $currentLanguage, 'flash_login_required');
            } elseif (!isValidDomain($domain) || $reason === '') {
                $flashErrors[] = t($translations, $currentLanguage, 'flash_appeal_required');
            } else {
                $statement = $pdo->prepare(
                    'INSERT INTO list_suggestions (created_at, user_id, list_type, domain, reason, status)
                     VALUES (:created_at, :user_id, :list_type, :domain, :reason, :status)'
                );
                $statement->execute([
                    ':created_at' => gmdate('c'),
                    ':user_id' => (int) ($currentUser['id'] ?? 0),
                    ':list_type' => $listType === 'allow' ? 'allow' : 'block',
                    ':domain' => $domain,
                    ':reason' => $reason,
                    ':status' => 'pending'
                ]);
                $flashNotices[] = $isAdmin
                    ? t($translations, $currentLanguage, 'flash_suggestion_admin')
                    : t($translations, $currentLanguage, 'flash_suggestion_user');
            }
        } elseif ($action === 'list_suggestion_update' && $pdo instanceof PDO) {
            $isAdmin = $currentUser && ($currentUser['role'] ?? '') === 'admin';
            $suggestionId = (int) ($_POST['suggestion_id'] ?? 0);
            $decision = (string) ($_POST['decision'] ?? '');
            if (!$isAdmin) {
                $flashErrors[] = t($translations, $currentLanguage, 'flash_admin_required');
            } else {
                $statement = $pdo->prepare(
                    'SELECT id, list_type, domain, reason, status FROM list_suggestions WHERE id = :id'
                );
                $statement->execute([':id' => $suggestionId]);
                $suggestion = $statement->fetch(PDO::FETCH_ASSOC);
                if (!$suggestion || ($suggestion['status'] ?? '') !== 'pending') {
                    $flashErrors[] = t($translations, $currentLanguage, 'flash_suggestion_reviewed');
                } elseif ($decision === 'approve') {
                    $listPath = ($suggestion['list_type'] ?? '') === 'allow' ? $allowlistFile : $blocklistFile;
                    $ok = updateListFile($listPath, (string) $suggestion['domain'], 'add');
                    if ($ok) {
                        $statement = $pdo->prepare(
                            'INSERT INTO list_actions (created_at, user_id, action, list_type, domain, reason)
                             VALUES (:created_at, :user_id, :action, :list_type, :domain, :reason)'
                        );
                        $statement->execute([
                            ':created_at' => gmdate('c'),
                            ':user_id' => (int) ($currentUser['id'] ?? 0),
                            ':action' => 'approve',
                            ':list_type' => (string) $suggestion['list_type'],
                            ':domain' => (string) $suggestion['domain'],
                            ':reason' => (string) $suggestion['reason']
                        ]);
                        $pdo->prepare('UPDATE list_suggestions SET status = :status WHERE id = :id')
                            ->execute([':status' => 'approved', ':id' => $suggestionId]);
                        $flashNotices[] = t($translations, $currentLanguage, 'flash_suggestion_applied');
                    } else {
                        $flashErrors[] = t($translations, $currentLanguage, 'flash_list_failed');
                    }
                } else {
                    $pdo->prepare('UPDATE list_suggestions SET status = :status WHERE id = :id')
                        ->execute([':status' => 'rejected', ':id' => $suggestionId]);
                    $flashNotices[] = t($translations, $currentLanguage, 'flash_suggestion_rejected');
                }
            }
        } elseif ($action === 'appeal_resolve' && $pdo instanceof PDO) {
            $isAdmin = $currentUser && ($currentUser['role'] ?? '') === 'admin';
            $appealId = (int) ($_POST['appeal_id'] ?? 0);
            if (!$isAdmin) {
                $flashErrors[] = t($translations, $currentLanguage, 'flash_admin_required');
            } else {
                $statement = $pdo->prepare('UPDATE appeals SET status = :status WHERE id = :id');
                $statement->execute([':status' => 'resolved', ':id' => $appealId]);
                $flashNotices[] = t($translations, $currentLanguage, 'flash_appeal_updated');
            }
        } elseif ($action === 'appeal_decision' && $pdo instanceof PDO) {
            $isAdmin = $currentUser && ($currentUser['role'] ?? '') === 'admin';
            $appealId = (int) ($_POST['appeal_id'] ?? 0);
            $decision = (string) ($_POST['decision'] ?? '');
            if (!$isAdmin) {
                $flashErrors[] = t($translations, $currentLanguage, 'flash_admin_required');
            } else {
                $newStatus = $decision === 'approve' ? 'approved' : 'rejected';
                $statement = $pdo->prepare('UPDATE appeals SET status = :status WHERE id = :id');
                $statement->execute([':status' => $newStatus, ':id' => $appealId]);
                $flashNotices[] = $decision === 'approve'
                    ? t($translations, $currentLanguage, 'flash_appeal_approved')
                    : t($translations, $currentLanguage, 'flash_appeal_rejected');
            }
        } elseif ($action === 'accept_detection' && $pdo instanceof PDO) {
            $isAdmin = $currentUser && ($currentUser['role'] ?? '') === 'admin';
            $detectionId = (int) ($_POST['detection_id'] ?? 0);
            if (!$isAdmin) {
                $flashErrors[] = t($translations, $currentLanguage, 'flash_admin_required');
            } else {
                $statement = $pdo->prepare(
                    'UPDATE reports
                     SET accepted = 1, accepted_by = :user_id, accepted_at = :accepted_at
                     WHERE id = :id'
                );
                $statement->execute([
                    ':user_id' => (int) ($currentUser['id'] ?? 0),
                    ':accepted_at' => gmdate('c'),
                    ':id' => $detectionId
                ]);
                $flashNotices[] = t($translations, $currentLanguage, 'flash_detection_accepted');
            }
        } elseif ($action === 'clear_unaccepted' && $pdo instanceof PDO) {
            $isAdmin = $currentUser && ($currentUser['role'] ?? '') === 'admin';
            if (!$isAdmin) {
                $flashErrors[] = t($translations, $currentLanguage, 'flash_admin_required');
            } else {
                $statement = $pdo->prepare('DELETE FROM reports WHERE accepted IS NULL OR accepted = 0');
                $ok = $statement->execute();
                if ($ok) {
                    $flashNotices[] = t($translations, $currentLanguage, 'flash_detection_cleared');
                } else {
                    $flashErrors[] = t($translations, $currentLanguage, 'flash_detection_clear_failed');
                }
            }
        }
    }
}

if ($isAdmin && isset($_GET['refresh_intel'])) {
    $shouldRefreshIntel = true;
}
if ($shouldRefreshIntel) {
    $intelEntries = fetchIntelSources($intelSources);
    $intelUpdatedAt = gmdate('c');
    saveIntelCache($intelCachePath, [
        'updated_at' => $intelUpdatedAt,
        'entries' => $intelEntries
    ]);
}

if (is_readable($dbPath)) {
    try {
        if (!$pdo instanceof PDO) {
            $pdo = new PDO('sqlite:' . $dbPath);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }
        $statsRows = $pdo->query(
            'SELECT received_at, enabled, alert_count, block_count, manual_sites_json, country
             FROM stats
             ORDER BY received_at DESC
             LIMIT 50'
        )->fetchAll(PDO::FETCH_ASSOC);

        foreach ($statsRows as $entry) {
            $stats['total_alerts'] = max($stats['total_alerts'], (int) ($entry['alert_count'] ?? 0));
            $stats['total_blocks'] = max($stats['total_blocks'], (int) ($entry['block_count'] ?? 0));
            if ($stats['extension_enabled'] === null && isset($entry['enabled'])) {
                $stats['extension_enabled'] = (bool) $entry['enabled'];
            }
            $manualSites = json_decode((string) ($entry['manual_sites_json'] ?? ''), true);
            if (is_array($manualSites)) {
                $stats['manual_sites'] = array_unique(array_merge($stats['manual_sites'], $manualSites));
            }
            $country = (string) ($entry['country'] ?? '');
            if ($country !== '') {
                $stats['countries'][$country] = ($stats['countries'][$country] ?? 0) + 1;
            }
            if ($stats['last_update'] === null && !empty($entry['received_at'])) {
                $stats['last_update'] = (string) $entry['received_at'];
            }
        }
        try {
            $reportRows = $pdo->query(
            'SELECT id, received_at, url, hostname, message, detected_content, full_context, signals_json, blocked, accepted, accepted_at, country
             FROM reports
             ORDER BY received_at DESC
             LIMIT 200'
            )->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $exception) {
            $reportRows = $pdo->query(
            'SELECT id, received_at, url, hostname, message, detected_content, signals_json, country
             FROM reports
             ORDER BY received_at DESC
             LIMIT 200'
            )->fetchAll(PDO::FETCH_ASSOC);
        }

        foreach ($reportRows as $entry) {
            $detected = trim((string) ($entry['detected_content'] ?? ''));
            $message = trim((string) ($entry['message'] ?? ''));
            if ($detected === '' && $message === '') {
                continue;
            }

            $timestamp = strtotime((string) ($entry['received_at'] ?? ''));
            if ($timestamp !== false) {
                $dateKey = date('Y-m-d', $timestamp);
                $hourKey = (int) date('G', $timestamp);
                $chartData['daily'][$dateKey] = ($chartData['daily'][$dateKey] ?? 0) + 1;
                if (isset($chartData['hourly'][$hourKey])) {
                    $chartData['hourly'][$hourKey] += 1;
                }
            }

            $country = (string) ($entry['country'] ?? '');
            if ($country !== '') {
                $chartData['countries'][$country] = ($chartData['countries'][$country] ?? 0) + 1;
            }

            $signals = json_decode((string) ($entry['signals_json'] ?? ''), true);
            $signalList = [];
            if (is_array($signals)) {
                foreach ($signals as $signal => $enabled) {
                    if ($enabled) {
                        $chartData['signals'][$signal] = ($chartData['signals'][$signal] ?? 0) + 1;
                        $signalList[] = $signalLabels[$signal] ?? $signal;
                    }
                }
            }

            $hostname = trim((string) ($entry['hostname'] ?? ''));
            if ($hostname === '') {
                $url = (string) ($entry['url'] ?? '');
                if ($url !== '') {
                    $parsedUrl = parse_url($url);
                    $hostname = (string) ($parsedUrl['host'] ?? '');
                }
            }

            $recentDetections[] = [
                'id' => (int) ($entry['id'] ?? 0),
                'hostname' => $hostname !== '' ? $hostname : t($translations, $currentLanguage, 'no_domain'),
                'url' => (string) ($entry['url'] ?? ''),
                'timestamp' => (string) ($entry['received_at'] ?? ''),
                'message' => $message,
                'detected' => $detected,
                'full_context' => trim((string) ($entry['full_context'] ?? '')),
                'blocked' => (bool) ($entry['blocked'] ?? false),
                'accepted' => (bool) ($entry['accepted'] ?? false),
                'accepted_at' => (string) ($entry['accepted_at'] ?? ''),
                'signals' => $signalList
            ];
        }
    } catch (Throwable $exception) {
        $stats = $stats;
    }
    $recentDetections = array_slice($recentDetections, 0, 50);
    $stats['recent_count'] = count($recentDetections);
}

if ($stats['last_update'] === null) {
    if (!empty($reportLogStructured)) {
        $stats['last_update'] = $reportLogStructured[0]['received_at'] ?? null;
    } elseif (is_readable(__DIR__ . '/clickfix-report.log')) {
        $stats['last_update'] = gmdate('c', (int) filemtime(__DIR__ . '/clickfix-report.log'));
    }
}

$blockRate = 0.0;
if ($stats['total_alerts'] > 0) {
    $blockRate = ($stats['total_blocks'] / $stats['total_alerts']) * 100;
}
$topCountries = $stats['countries'];
arsort($topCountries);
$topCountries = array_slice($topCountries, 0, 4, true);

$blocklistItems = loadListFile($blocklistFile);
$allowlistItems = loadListFile($allowlistFile);
$appeals = [];
$listSuggestions = [];
if ($pdo instanceof PDO) {
    try {
        $appeals = $pdo->query(
            'SELECT id, created_at, domain, reason, contact, status
             FROM appeals
             ORDER BY created_at DESC
             LIMIT 100'
        )->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $exception) {
        $appeals = [];
    }
    try {
        $listSuggestions = $pdo->query(
            'SELECT ls.id, ls.created_at, ls.domain, ls.reason, ls.list_type, ls.status, u.username
             FROM list_suggestions ls
             LEFT JOIN users u ON ls.user_id = u.id
             ORDER BY ls.created_at DESC
             LIMIT 200'
        )->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $exception) {
        $listSuggestions = [];
    }
}

ksort($chartData['daily']);
if (empty($chartData['countries'])) {
    $chartData['countries'] = $reportLogCountries;
}
arsort($chartData['signals']);

$signalChartLabels = [];
$signalChartValues = [];
foreach ($chartData['signals'] as $signal => $count) {
    $signalChartLabels[] = $signalLabels[$signal] ?? $signal;
    $signalChartValues[] = $count;
}

$chartLabels = [
    'alerts' => t($translations, $currentLanguage, 'total_alerts'),
    'signals' => t($translations, $currentLanguage, 'signal_types')
];

  $chartPayload = [
    'daily' => [
        'labels' => array_keys($chartData['daily']),
        'values' => array_values($chartData['daily'])
    ],
    'hourly' => [
        'labels' => range(0, 23),
        'values' => array_values($chartData['hourly'])
    ],
    'countries' => [
        'labels' => array_keys($chartData['countries']),
        'values' => array_values($chartData['countries'])
    ],
    'signals' => [
        'labels' => $signalChartLabels,
        'values' => $signalChartValues
    ],
    'labels' => $chartLabels
];
?>
<!doctype html>
<html lang="es">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?= htmlspecialchars(t($translations, $currentLanguage, 'app_title'), ENT_QUOTES, 'UTF-8'); ?></title>
    <style>
      :root {
        color-scheme: dark;
        --bg: #0b1020;
        --surface: rgba(16, 23, 42, 0.85);
        --surface-alt: rgba(30, 41, 59, 0.7);
        --border: rgba(148, 163, 184, 0.16);
        --text: #e2e8f0;
        --muted: #94a3b8;
        --primary: #60a5fa;
        --primary-soft: rgba(59, 130, 246, 0.18);
        --success: #22c55e;
        --danger: #f43f5e;
        --warning: #f59e0b;
        --shadow: 0 25px 60px -32px rgba(15, 23, 42, 0.8);
        --glow: 0 0 35px rgba(96, 165, 250, 0.25);
      }
      * {
        box-sizing: border-box;
      }
      body {
        font-family: "Inter", "Segoe UI", system-ui, sans-serif;
        margin: 0;
        background: radial-gradient(circle at top, rgba(59, 130, 246, 0.12) 0%, rgba(15, 23, 42, 0.9) 40%, #06070d 100%);
        color: var(--text);
      }
      .page {
        max-width: 1280px;
        margin: 0 auto;
        padding: 28px 24px 48px;
      }
      .top-bar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        margin-bottom: 18px;
      }
      .brand h1 {
        margin: 0;
        font-size: 28px;
        letter-spacing: -0.02em;
      }
      .brand .muted {
        margin-top: 6px;
      }
      .muted {
        color: var(--muted);
        font-size: 14px;
      }
      .status-pill {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 6px 12px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 600;
        background: var(--surface-alt);
        color: var(--text);
      }
      .status-pill.enabled {
        background: #dcfce7;
        color: #166534;
      }
      .status-pill.disabled {
        background: #fee2e2;
        color: #991b1b;
      }
      .status-pill.pending {
        background: #fee2e2;
        color: #991b1b;
      }
      .status-pill.approved {
        background: #dcfce7;
        color: #166534;
      }
      .status-pill.rejected {
        background: #e2e8f0;
        color: #475569;
      }
      .grid {
        display: grid;
        gap: 16px;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      }
      .card {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 18px;
        padding: 18px;
        box-shadow: var(--shadow);
        backdrop-filter: blur(16px);
      }
      .hero {
        display: grid;
        gap: 16px;
        grid-template-columns: minmax(0, 2fr) minmax(240px, 1fr);
        align-items: stretch;
      }
      .hero-summary h2 {
        margin: 0 0 6px;
        font-size: 22px;
      }
      .hero-summary p {
        margin: 0;
        color: var(--muted);
      }
      .hero-actions {
        display: grid;
        gap: 12px;
      }
      .hero-action {
        padding: 12px;
        border-radius: 12px;
        background: linear-gradient(140deg, rgba(30, 41, 59, 0.7), rgba(15, 23, 42, 0.7));
        border: 1px solid rgba(148, 163, 184, 0.22);
        box-shadow: var(--glow);
      }
      .stat-card {
        display: flex;
        flex-direction: column;
        gap: 10px;
        padding: 18px;
        border-radius: 18px;
        background: linear-gradient(135deg, rgba(15, 23, 42, 0.8), rgba(30, 41, 59, 0.8));
        border: 1px solid rgba(148, 163, 184, 0.2);
        box-shadow: var(--shadow);
      }
      .stat-label {
        font-size: 12px;
        color: var(--muted);
        text-transform: uppercase;
        letter-spacing: 0.12em;
      }
      .stat-value {
        font-size: 30px;
        font-weight: 700;
        color: var(--text);
      }
      .stat-footnote {
        font-size: 12px;
        color: #94a3b8;
      }
      .layout {
        display: grid;
        gap: 22px;
        grid-template-columns: minmax(0, 2.1fr) minmax(280px, 0.9fr);
        margin-top: 24px;
      }
      .section-title {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 12px;
      }
      .chart-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 16px;
      }
      .chart-card {
        background: rgba(15, 23, 42, 0.85);
        border: 1px solid rgba(148, 163, 184, 0.2);
        border-radius: 14px;
        padding: 12px;
        max-height: 170pt;
        box-shadow: var(--glow);
      }
      .chart-card h3 {
        margin: 0 0 10px;
        font-size: 15px;
        color: var(--text);
      }
      .table-wrap {
        overflow-x: auto;
      }
      table {
        width: 100%;
        border-collapse: collapse;
        font-size: 12px;
      }
      th,
      td {
        text-align: left;
        padding: 8px 10px;
        border-bottom: 1px solid var(--border);
        vertical-align: top;
      }
      th {
        text-transform: uppercase;
        letter-spacing: 0.08em;
        font-size: 11px;
        color: var(--muted);
      }
      .intel-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 16px;
      }
      .intel-card {
        border-radius: 16px;
        border: 1px solid rgba(226, 232, 240, 0.8);
        background: rgba(255, 255, 255, 0.82);
        backdrop-filter: blur(10px);
        padding: 16px;
        box-shadow: 0 12px 30px -20px rgba(15, 23, 42, 0.45);
        display: grid;
        gap: 8px;
      }
      .intel-title {
        font-weight: 700;
        font-size: 14px;
      }
      .intel-meta {
        font-size: 12px;
        color: var(--muted);
      }
      .intel-highlights {
        margin: 8px 0 0;
        padding-left: 18px;
        color: var(--text);
        font-size: 12px;
      }
      .intel-highlights li {
        border-bottom: none;
        padding: 2px 0;
      }
      .status-dot {
        width: 8px;
        height: 8px;
        border-radius: 999px;
        display: inline-block;
        margin-right: 6px;
      }
      .status-dot.ok {
        background: #22c55e;
      }
      .status-dot.error {
        background: #ef4444;
      }
      .chip-list {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
      }
      .chip {
        padding: 6px 10px;
        background: rgba(59, 130, 246, 0.2);
        color: #bfdbfe;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 600;
      }
      .list-block {
        max-height: 240px;
        overflow-y: auto;
        overflow-x: hidden;
        padding-right: 6px;
        word-break: break-word;
      }
      .list-block li {
        word-break: break-word;
        overflow-wrap: anywhere;
      }
      .badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 10px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.03em;
        text-transform: uppercase;
        background: var(--surface-alt);
        color: var(--text);
      }
      .badge-blocked {
        background: #fee2e2;
        color: #991b1b;
      }
      .meta-row {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        align-items: center;
      }
      .signal-chip {
        background: #e0f2fe;
        color: #0369a1;
      }
      h2 {
        font-size: 18px;
        margin: 0 0 12px;
      }
      h3 {
        font-size: 16px;
        margin: 0 0 8px;
      }
      ul {
        list-style: none;
        padding: 0;
        margin: 0;
      }
      li {
        padding: 6px 0;
        border-bottom: 1px solid var(--border);
      }
      li:last-child {
        border-bottom: none;
      }
      pre {
        white-space: pre-wrap;
        background: rgba(15, 23, 42, 0.9);
        color: #e2e8f0;
        padding: 12px;
        border-radius: 12px;
        font-size: 13px;
        border: 1px solid rgba(148, 163, 184, 0.2);
      }
      .report-card {
        border: 1px solid rgba(148, 163, 184, 0.2);
        border-radius: 16px;
        padding: 12px 14px;
        margin-bottom: 12px;
        background: rgba(15, 23, 42, 0.85);
        box-shadow: var(--shadow);
      }
      .report-card summary {
        cursor: pointer;
        font-weight: 600;
        display: flex;
        flex-direction: column;
        gap: 4px;
      }
      .report-meta {
        font-size: 12px;
        color: var(--muted);
      }
      .report-section {
        margin-top: 10px;
      }
      .context-panel {
        margin-top: 8px;
        border: 1px dashed rgba(148, 163, 184, 0.4);
        border-radius: 12px;
        padding: 10px;
        background: rgba(30, 41, 59, 0.8);
      }
      .context-panel pre {
        background: #111827;
      }
      .log-grid {
        display: grid;
        gap: 16px;
      }
      .log-entry {
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 12px;
        background: rgba(15, 23, 42, 0.85);
      }
      .log-entry pre {
        margin: 0;
        font-size: 12px;
      }
      .form-grid {
        display: grid;
        gap: 12px;
      }
      .form-grid input,
      .form-grid textarea,
      .form-grid select {
        width: 100%;
        padding: 10px 12px;
        border-radius: 10px;
        border: 1px solid rgba(148, 163, 184, 0.25);
        background: rgba(15, 23, 42, 0.8);
        color: var(--text);
        font-family: inherit;
      }
      .form-grid textarea {
        min-height: 90px;
      }
      .form-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
      }
      .button-primary {
        background: linear-gradient(135deg, #3b82f6, #60a5fa);
        color: #0b1020;
        border: none;
        border-radius: 10px;
        padding: 10px 14px;
        cursor: pointer;
        font-weight: 600;
        box-shadow: var(--glow);
      }
      .button-secondary {
        background: rgba(148, 163, 184, 0.2);
        color: #e2e8f0;
        border: 1px solid rgba(148, 163, 184, 0.3);
        border-radius: 10px;
        padding: 10px 14px;
        cursor: pointer;
        font-weight: 600;
      }
      .button-ghost {
        background: transparent;
        color: #93c5fd;
        border: 1px solid rgba(59, 130, 246, 0.4);
        border-radius: 10px;
        padding: 8px 12px;
        font-weight: 600;
      }
      .alert-box {
        padding: 10px 12px;
        border-radius: 10px;
        margin-bottom: 12px;
        font-size: 14px;
      }
      .alert-box.error {
        background: rgba(239, 68, 68, 0.2);
        color: #fecaca;
        border: 1px solid rgba(239, 68, 68, 0.35);
      }
      .alert-box.notice {
        background: rgba(34, 197, 94, 0.2);
        color: #bbf7d0;
        border: 1px solid rgba(34, 197, 94, 0.35);
      }
      .button-approve {
        background: #22c55e;
        color: #fff;
        border: none;
      }
      .button-reject {
        background: #ef4444;
        color: #fff;
        border: none;
      }
      .role-badge {
        display: inline-flex;
        align-items: center;
        padding: 4px 10px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 600;
        background: var(--surface-alt);
        color: #1e293b;
      }
      .role-badge.admin {
        background: #fef3c7;
        color: #92400e;
      }
      .role-badge.analyst {
        background: #e0f2fe;
        color: #0369a1;
      }
      .admin-panel {
        border: 1px dashed #cbd5f5;
        background: #f8fafc;
      }
      .highlight {
        background: rgba(59, 130, 246, 0.15);
        border: 1px solid rgba(59, 130, 246, 0.3);
        padding: 12px;
        border-radius: 12px;
      }
      .info-list li {
        display: flex;
        justify-content: space-between;
        gap: 8px;
      }
      .info-list span {
        color: var(--muted);
      }
      .panel-stack {
        display: grid;
        gap: 16px;
      }
      @media (max-width: 980px) {
        .layout,
        .hero {
          grid-template-columns: 1fr;
        }
      }
      @media (max-width: 640px) {
        .page {
          padding: 24px 16px 40px;
        }
        .stat-value {
          font-size: 24px;
        }
      }
    </style>
  </head>
  <body>
    <div class="page">
      <div class="top-bar">
        <div class="brand">
          <h1><?= htmlspecialchars(t($translations, $currentLanguage, 'app_title'), ENT_QUOTES, 'UTF-8'); ?></h1>
          <div class="muted">
            <?= htmlspecialchars(t($translations, $currentLanguage, 'last_update'), ENT_QUOTES, 'UTF-8'); ?>:
            <?= htmlspecialchars((string) ($stats['last_update'] ?? 'N/D'), ENT_QUOTES, 'UTF-8'); ?>
          </div>
        </div>
        <div class="meta-row">
          <?php
            $extensionStatus = $stats['extension_enabled'];
            $statusClass = $extensionStatus === null ? '' : ($extensionStatus ? 'enabled' : 'disabled');
            $statusLabel = $extensionStatus === null
                ? t($translations, $currentLanguage, 'extension_status_unknown')
                : ($extensionStatus
                    ? t($translations, $currentLanguage, 'extension_enabled')
                    : t($translations, $currentLanguage, 'extension_disabled'));
            if ($extensionStatus === null && $stats['recent_count'] > 0) {
                $statusClass = 'enabled';
                $statusLabel = t($translations, $currentLanguage, 'extension_status_activity');
            }
          ?>
          <span class="status-pill <?= htmlspecialchars($statusClass, ENT_QUOTES, 'UTF-8'); ?>">
            <?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8'); ?>
          </span>
          <?php if ($currentUser): ?>
            <span class="badge">
              <?= htmlspecialchars(t($translations, $currentLanguage, 'session_active'), ENT_QUOTES, 'UTF-8'); ?>:
              <?= htmlspecialchars((string) $currentUser['username'], ENT_QUOTES, 'UTF-8'); ?>
            </span>
            <span class="role-badge <?= htmlspecialchars((string) $currentUser['role'], ENT_QUOTES, 'UTF-8'); ?>">
              <?= htmlspecialchars((string) $currentUser['role'], ENT_QUOTES, 'UTF-8'); ?>
            </span>
          <?php else: ?>
            <span class="badge"><?= htmlspecialchars(t($translations, $currentLanguage, 'session_none'), ENT_QUOTES, 'UTF-8'); ?></span>
          <?php endif; ?>
          <form method="get" class="form-actions">
            <label class="muted" style="display: flex; align-items: center; gap: 8px;">
              <?= htmlspecialchars(t($translations, $currentLanguage, 'language'), ENT_QUOTES, 'UTF-8'); ?>
              <select name="lang" onchange="this.form.submit()">
                <?php foreach ($supportedLanguages as $languageOption): ?>
                  <option value="<?= htmlspecialchars($languageOption, ENT_QUOTES, 'UTF-8'); ?>" <?= $currentLanguage === $languageOption ? 'selected' : ''; ?>>
                    <?= strtoupper(htmlspecialchars($languageOption, ENT_QUOTES, 'UTF-8')); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </label>
          </form>
        </div>
      </div>

      <?php foreach ($flashErrors as $error): ?>
        <div class="alert-box error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
      <?php endforeach; ?>
      <?php foreach ($flashNotices as $notice): ?>
        <div class="alert-box notice"><?= htmlspecialchars($notice, ENT_QUOTES, 'UTF-8'); ?></div>
      <?php endforeach; ?>

      <section class="card hero">
        <div class="hero-summary">
          <h2><?= htmlspecialchars(t($translations, $currentLanguage, 'dashboard_title'), ENT_QUOTES, 'UTF-8'); ?></h2>
          <p><?= htmlspecialchars(t($translations, $currentLanguage, 'dashboard_subtitle'), ENT_QUOTES, 'UTF-8'); ?></p>
          <div class="chip-list" style="margin-top: 12px;">
            <span class="chip"><?= htmlspecialchars(t($translations, $currentLanguage, 'recent_alerts'), ENT_QUOTES, 'UTF-8'); ?>: <?= (int) $stats['recent_count']; ?></span>
            <span class="chip"><?= htmlspecialchars(t($translations, $currentLanguage, 'manual_sites'), ENT_QUOTES, 'UTF-8'); ?>: <?= (int) count($stats['manual_sites']); ?></span>
            <span class="chip">
              <?= htmlspecialchars(t($translations, $currentLanguage, 'coverage'), ENT_QUOTES, 'UTF-8'); ?>:
              <?= (int) count($stats['countries']); ?> <?= htmlspecialchars(t($translations, $currentLanguage, 'countries_label'), ENT_QUOTES, 'UTF-8'); ?>
            </span>
            <?php if ($isAdmin): ?>
              <span class="chip"><?= htmlspecialchars(t($translations, $currentLanguage, 'alerted_sites'), ENT_QUOTES, 'UTF-8'); ?>: <?= (int) count($stats['alert_sites']); ?></span>
            <?php endif; ?>
          </div>
        </div>
        <div class="hero-actions">
          <div class="hero-action">
            <div class="muted"><?= htmlspecialchars(t($translations, $currentLanguage, 'block_rate'), ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="stat-value"><?= number_format($blockRate, 1); ?>%</div>
            <div class="muted"><?= htmlspecialchars(t($translations, $currentLanguage, 'block_rate_help'), ENT_QUOTES, 'UTF-8'); ?></div>
          </div>
          <div class="hero-action">
            <div class="muted"><?= htmlspecialchars(t($translations, $currentLanguage, 'top_countries'), ENT_QUOTES, 'UTF-8'); ?></div>
            <?php if (empty($topCountries)): ?>
              <div class="muted"><?= htmlspecialchars(t($translations, $currentLanguage, 'no_geo_data'), ENT_QUOTES, 'UTF-8'); ?></div>
            <?php else: ?>
              <ul class="info-list">
                <?php foreach ($topCountries as $country => $count): ?>
                  <li>
                    <strong><?= htmlspecialchars($country, ENT_QUOTES, 'UTF-8'); ?></strong>
                    <span><?= (int) $count; ?> <?= htmlspecialchars(t($translations, $currentLanguage, 'events'), ENT_QUOTES, 'UTF-8'); ?></span>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </div>
        </div>
      </section>

      <section class="grid">
        <div class="stat-card">
          <span class="stat-label"><?= htmlspecialchars(t($translations, $currentLanguage, 'total_alerts'), ENT_QUOTES, 'UTF-8'); ?></span>
          <div class="stat-value"><?= (int) $stats['total_alerts']; ?></div>
          <span class="stat-footnote"><?= htmlspecialchars(t($translations, $currentLanguage, 'extension_history'), ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
        <div class="stat-card">
          <span class="stat-label"><?= htmlspecialchars(t($translations, $currentLanguage, 'total_blocks'), ENT_QUOTES, 'UTF-8'); ?></span>
          <div class="stat-value"><?= (int) $stats['total_blocks']; ?></div>
          <span class="stat-footnote"><?= htmlspecialchars(t($translations, $currentLanguage, 'confirmed_preventions'), ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
        <div class="stat-card">
          <span class="stat-label"><?= htmlspecialchars(t($translations, $currentLanguage, 'manual_sites'), ENT_QUOTES, 'UTF-8'); ?></span>
          <div class="stat-value"><?= (int) count($stats['manual_sites']); ?></div>
          <span class="stat-footnote"><?= htmlspecialchars(t($translations, $currentLanguage, 'manual_domains'), ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
        <div class="stat-card">
          <span class="stat-label"><?= htmlspecialchars(t($translations, $currentLanguage, 'recent_alerts'), ENT_QUOTES, 'UTF-8'); ?></span>
          <div class="stat-value"><?= (int) $stats['recent_count']; ?></div>
          <span class="stat-footnote"><?= htmlspecialchars(t($translations, $currentLanguage, 'recent_events'), ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
        <?php if ($isAdmin): ?>
          <div class="stat-card">
            <span class="stat-label"><?= htmlspecialchars(t($translations, $currentLanguage, 'alerted_sites'), ENT_QUOTES, 'UTF-8'); ?></span>
            <div class="stat-value"><?= (int) count($stats['alert_sites']); ?></div>
            <span class="stat-footnote"><?= htmlspecialchars(t($translations, $currentLanguage, 'pending_review'), ENT_QUOTES, 'UTF-8'); ?></span>
          </div>
        <?php endif; ?>
        <div class="stat-card">
          <span class="stat-label"><?= htmlspecialchars(t($translations, $currentLanguage, 'block_rate'), ENT_QUOTES, 'UTF-8'); ?></span>
          <div class="stat-value"><?= number_format($blockRate, 1); ?>%</div>
          <span class="stat-footnote"><?= htmlspecialchars(t($translations, $currentLanguage, 'containment_efficiency'), ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
      </section>

      <div class="layout">
        <div>
          <section class="card">
            <div class="section-title">
              <h2><?= htmlspecialchars(t($translations, $currentLanguage, 'public_lists'), ENT_QUOTES, 'UTF-8'); ?></h2>
              <span class="muted"><?= htmlspecialchars(t($translations, $currentLanguage, 'visible_to_all'), ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <div class="grid">
              <div>
                <h3><?= htmlspecialchars(t($translations, $currentLanguage, 'allowlist'), ENT_QUOTES, 'UTF-8'); ?></h3>
                <?php if (empty($allowlistItems)): ?>
                  <div class="muted"><?= htmlspecialchars(t($translations, $currentLanguage, 'empty_domains'), ENT_QUOTES, 'UTF-8'); ?></div>
                <?php else: ?>
                  <div class="list-block">
                    <ul>
                      <?php foreach ($allowlistItems as $domain): ?>
                        <li><?= htmlspecialchars($domain, ENT_QUOTES, 'UTF-8'); ?></li>
                      <?php endforeach; ?>
                    </ul>
                  </div>
                <?php endif; ?>
              </div>
              <div>
                <h3><?= htmlspecialchars(t($translations, $currentLanguage, 'blocklist'), ENT_QUOTES, 'UTF-8'); ?></h3>
                <?php if (empty($blocklistItems)): ?>
                  <div class="muted"><?= htmlspecialchars(t($translations, $currentLanguage, 'empty_domains'), ENT_QUOTES, 'UTF-8'); ?></div>
                <?php else: ?>
                  <div class="list-block">
                    <ul>
                      <?php foreach ($blocklistItems as $domain): ?>
                        <li><?= htmlspecialchars($domain, ENT_QUOTES, 'UTF-8'); ?></li>
                      <?php endforeach; ?>
                    </ul>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          </section>

          <section class="card" style="margin-top: 24px;">
            <h2><?= htmlspecialchars(t($translations, $currentLanguage, 'appeal_title'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <form method="post" class="form-grid">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
              <input type="hidden" name="action" value="appeal" />
              <label>
                <?= htmlspecialchars(t($translations, $currentLanguage, 'domain'), ENT_QUOTES, 'UTF-8'); ?>
                <input type="text" name="domain" placeholder="example.com" required />
              </label>
              <label>
                <?= htmlspecialchars(t($translations, $currentLanguage, 'appeal_reason'), ENT_QUOTES, 'UTF-8'); ?>
                <textarea name="reason" required></textarea>
              </label>
              <label>
                <?= htmlspecialchars(t($translations, $currentLanguage, 'contact_optional'), ENT_QUOTES, 'UTF-8'); ?>
                <input type="text" name="contact" placeholder="mail@domain.com" />
              </label>
              <div class="form-actions">
                <button class="button-primary" type="submit"><?= htmlspecialchars(t($translations, $currentLanguage, 'submit_appeal'), ENT_QUOTES, 'UTF-8'); ?></button>
              </div>
            </form>
          </section>

          <?php if ($isAdmin): ?>
            <section class="card admin-panel" style="margin-top: 24px;">
              <details>
                <summary class="section-title">
                  <h2><?= htmlspecialchars(t($translations, $currentLanguage, 'admin_lists'), ENT_QUOTES, 'UTF-8'); ?></h2>
                  <span class="muted"><?= htmlspecialchars(t($translations, $currentLanguage, 'admin_only'), ENT_QUOTES, 'UTF-8'); ?></span>
                </summary>
                <form method="post" class="form-grid" style="margin-top: 12px;">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                  <input type="hidden" name="action" value="list_action" />
                  <label>
                    <?= htmlspecialchars(t($translations, $currentLanguage, 'domain'), ENT_QUOTES, 'UTF-8'); ?>
                    <input type="text" name="domain" placeholder="example.com" required />
                  </label>
                  <label>
                    <?= htmlspecialchars(t($translations, $currentLanguage, 'list_type'), ENT_QUOTES, 'UTF-8'); ?>
                    <select name="list_type">
                      <option value="allow"><?= htmlspecialchars(t($translations, $currentLanguage, 'allowlist'), ENT_QUOTES, 'UTF-8'); ?></option>
                      <option value="block"><?= htmlspecialchars(t($translations, $currentLanguage, 'blocklist'), ENT_QUOTES, 'UTF-8'); ?></option>
                    </select>
                  </label>
                  <label>
                    <?= htmlspecialchars(t($translations, $currentLanguage, 'reason'), ENT_QUOTES, 'UTF-8'); ?>
                    <textarea name="reason" required></textarea>
                  </label>
                  <div class="form-actions">
                    <button class="button-primary" type="submit" name="mode" value="add"><?= htmlspecialchars(t($translations, $currentLanguage, 'add'), ENT_QUOTES, 'UTF-8'); ?></button>
                    <button class="button-secondary" type="submit" name="mode" value="remove"><?= htmlspecialchars(t($translations, $currentLanguage, 'remove'), ENT_QUOTES, 'UTF-8'); ?></button>
                  </div>
                </form>
              </details>
            </section>
          <?php elseif ($currentUser): ?>
            <section class="card" style="margin-top: 24px;">
              <details open>
                <summary class="section-title">
                  <h2><?= htmlspecialchars(t($translations, $currentLanguage, 'suggest_list_changes'), ENT_QUOTES, 'UTF-8'); ?></h2>
                  <span class="muted"><?= htmlspecialchars(t($translations, $currentLanguage, 'analyst_only'), ENT_QUOTES, 'UTF-8'); ?></span>
                </summary>
                <form method="post" class="form-grid" style="margin-top: 12px;">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                  <input type="hidden" name="action" value="list_suggest" />
                  <label>
                    <?= htmlspecialchars(t($translations, $currentLanguage, 'domain'), ENT_QUOTES, 'UTF-8'); ?>
                    <input type="text" name="domain" placeholder="example.com" required />
                  </label>
                  <label>
                    <?= htmlspecialchars(t($translations, $currentLanguage, 'list_type'), ENT_QUOTES, 'UTF-8'); ?>
                    <select name="list_type">
                      <option value="allow"><?= htmlspecialchars(t($translations, $currentLanguage, 'allowlist'), ENT_QUOTES, 'UTF-8'); ?></option>
                      <option value="block"><?= htmlspecialchars(t($translations, $currentLanguage, 'blocklist'), ENT_QUOTES, 'UTF-8'); ?></option>
                    </select>
                  </label>
                  <label>
                    <?= htmlspecialchars(t($translations, $currentLanguage, 'reason'), ENT_QUOTES, 'UTF-8'); ?>
                    <textarea name="reason" required></textarea>
                  </label>
                  <div class="form-actions">
                    <button class="button-primary" type="submit"><?= htmlspecialchars(t($translations, $currentLanguage, 'send_suggestion'), ENT_QUOTES, 'UTF-8'); ?></button>
                  </div>
                </form>
              </details>
            </section>
          <?php endif; ?>

          <?php if ($isAdmin): ?>
            <section class="card admin-panel" style="margin-top: 24px;">
              <div class="section-title">
                <h2><?= htmlspecialchars(t($translations, $currentLanguage, 'suggestions_review'), ENT_QUOTES, 'UTF-8'); ?></h2>
                <span class="muted"><?= htmlspecialchars(t($translations, $currentLanguage, 'suggestions_subtitle'), ENT_QUOTES, 'UTF-8'); ?></span>
              </div>
              <?php if (empty($listSuggestions)): ?>
                <div class="muted"><?= htmlspecialchars(t($translations, $currentLanguage, 'no_suggestions'), ENT_QUOTES, 'UTF-8'); ?></div>
              <?php else: ?>
                <?php foreach ($listSuggestions as $suggestion): ?>
                  <details class="report-card">
                    <summary>
                      <?= htmlspecialchars((string) ($suggestion['domain'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                      <span class="status-pill <?= htmlspecialchars((string) ($suggestion['status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                        <?= htmlspecialchars((string) ($suggestion['status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                      </span>
                      <span class="report-meta">
                        <?= htmlspecialchars((string) ($suggestion['created_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                      </span>
                    </summary>
                    <div class="report-section">
                      <strong><?= htmlspecialchars(t($translations, $currentLanguage, 'type'), ENT_QUOTES, 'UTF-8'); ?></strong>
                      <div class="muted"><?= htmlspecialchars((string) ($suggestion['list_type'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>
                    <div class="report-section">
                      <strong><?= htmlspecialchars(t($translations, $currentLanguage, 'reason'), ENT_QUOTES, 'UTF-8'); ?></strong>
                      <div class="muted"><?= htmlspecialchars((string) ($suggestion['reason'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>
                    <?php if (!empty($suggestion['username'])): ?>
                      <div class="report-section">
                        <strong><?= htmlspecialchars(t($translations, $currentLanguage, 'requested_by'), ENT_QUOTES, 'UTF-8'); ?></strong>
                        <div class="muted"><?= htmlspecialchars((string) ($suggestion['username'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                      </div>
                    <?php endif; ?>
                    <?php if (($suggestion['status'] ?? '') === 'pending'): ?>
                      <form method="post" class="form-actions" style="margin-top: 12px;">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                        <input type="hidden" name="action" value="list_suggestion_update" />
                        <input type="hidden" name="suggestion_id" value="<?= (int) ($suggestion['id'] ?? 0); ?>" />
                        <button class="button-primary" type="submit" name="decision" value="approve"><?= htmlspecialchars(t($translations, $currentLanguage, 'approve_apply'), ENT_QUOTES, 'UTF-8'); ?></button>
                        <button class="button-secondary" type="submit" name="decision" value="reject"><?= htmlspecialchars(t($translations, $currentLanguage, 'reject'), ENT_QUOTES, 'UTF-8'); ?></button>
                      </form>
                    <?php endif; ?>
                  </details>
                <?php endforeach; ?>
              <?php endif; ?>
            </section>
          <?php endif; ?>

          <section class="card">
            <div class="section-title">
              <h2><?= htmlspecialchars(t($translations, $currentLanguage, 'alert_analytics'), ENT_QUOTES, 'UTF-8'); ?></h2>
              <span class="muted"><?= htmlspecialchars(t($translations, $currentLanguage, 'latest_reports'), ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <div class="chart-grid">
              <div class="chart-card">
                <h3><?= htmlspecialchars(t($translations, $currentLanguage, 'alerts_by_day'), ENT_QUOTES, 'UTF-8'); ?></h3>
                <canvas id="chart-alerts-day" height="140"></canvas>
              </div>
              <div class="chart-card">
                <h3><?= htmlspecialchars(t($translations, $currentLanguage, 'alerts_by_hour'), ENT_QUOTES, 'UTF-8'); ?></h3>
                <canvas id="chart-alerts-hour" height="140"></canvas>
              </div>
              <div class="chart-card">
                <h3><?= htmlspecialchars(t($translations, $currentLanguage, 'country_distribution'), ENT_QUOTES, 'UTF-8'); ?></h3>
                <canvas id="chart-alerts-country" height="140"></canvas>
              </div>
              <div class="chart-card">
                <h3><?= htmlspecialchars(t($translations, $currentLanguage, 'signal_types'), ENT_QUOTES, 'UTF-8'); ?></h3>
                <canvas id="chart-alerts-signals" height="140"></canvas>
              </div>
            </div>
          </section>

          <section class="card" style="margin-top: 24px;">
            <div class="section-title">
              <h2><?= htmlspecialchars(t($translations, $currentLanguage, 'intel_section'), ENT_QUOTES, 'UTF-8'); ?></h2>
              <span class="muted"><?= htmlspecialchars(t($translations, $currentLanguage, 'intel_subtitle'), ENT_QUOTES, 'UTF-8'); ?></span>
              <?php if ($isAdmin): ?>
                <form method="get" class="form-actions">
                  <input type="hidden" name="refresh_intel" value="1" />
                  <input type="hidden" name="lang" value="<?= htmlspecialchars($currentLanguage, ENT_QUOTES, 'UTF-8'); ?>" />
                  <button class="button-secondary" type="submit"><?= htmlspecialchars(t($translations, $currentLanguage, 'intel_refresh'), ENT_QUOTES, 'UTF-8'); ?></button>
                </form>
              <?php endif; ?>
            </div>
            <div class="intel-grid">
              <?php foreach ($intelEntries as $entry): ?>
                <?php
                  $status = (string) ($entry['status'] ?? 'error');
                  $statusLabel = $status === 'ok'
                      ? t($translations, $currentLanguage, 'intel_status_ok')
                      : t($translations, $currentLanguage, 'intel_status_error');
                ?>
                <div class="intel-card">
                  <div class="intel-title"><?= htmlspecialchars((string) ($entry['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                  <div class="muted"><?= htmlspecialchars((string) ($entry['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                  <?php if (!empty($entry['description'])): ?>
                    <div class="intel-meta"><?= htmlspecialchars((string) $entry['description'], ENT_QUOTES, 'UTF-8'); ?></div>
                  <?php endif; ?>
                  <?php if (!empty($entry['highlights']) && is_array($entry['highlights'])): ?>
                    <div class="intel-meta"><?= htmlspecialchars(t($translations, $currentLanguage, 'intel_highlights'), ENT_QUOTES, 'UTF-8'); ?></div>
                    <ul class="intel-highlights">
                      <?php foreach (array_slice($entry['highlights'], 0, 6) as $highlight): ?>
                        <li><?= htmlspecialchars((string) $highlight, ENT_QUOTES, 'UTF-8'); ?></li>
                      <?php endforeach; ?>
                    </ul>
                  <?php endif; ?>
                  <a href="<?= htmlspecialchars((string) ($entry['url'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">
                    <?= htmlspecialchars((string) ($entry['url'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                  </a>
                  <div class="intel-meta">
                    <span class="status-dot <?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>"></span>
                    <?= htmlspecialchars(t($translations, $currentLanguage, 'intel_status'), ENT_QUOTES, 'UTF-8'); ?>:
                    <?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8'); ?>
                  </div>
                  <div class="intel-meta">
                    <?= htmlspecialchars(t($translations, $currentLanguage, 'intel_last_fetch'), ENT_QUOTES, 'UTF-8'); ?>:
                    <?= htmlspecialchars((string) ($entry['fetched_at'] ?? $intelUpdatedAt), ENT_QUOTES, 'UTF-8'); ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </section>

          <section class="card" style="margin-top: 24px;">
            <div class="section-title">
              <h2><?= htmlspecialchars(t($translations, $currentLanguage, 'recent_detections'), ENT_QUOTES, 'UTF-8'); ?></h2>
              <?php if ($isAdmin): ?>
                <form method="post" class="form-actions">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                  <input type="hidden" name="action" value="clear_unaccepted" />
                  <button class="button-secondary" type="submit" onclick="return confirm('<?= htmlspecialchars(t($translations, $currentLanguage, 'confirm_clear'), ENT_QUOTES, 'UTF-8'); ?>');">
                    <?= htmlspecialchars(t($translations, $currentLanguage, 'clear_unaccepted'), ENT_QUOTES, 'UTF-8'); ?>
                  </button>
                  <span class="muted"><?= htmlspecialchars(t($translations, $currentLanguage, 'clear_unaccepted_help'), ENT_QUOTES, 'UTF-8'); ?></span>
                </form>
              <?php endif; ?>
            </div>
            <?php if (empty($recentDetections)): ?>
              <div class="muted"><?= htmlspecialchars(t($translations, $currentLanguage, 'no_detections'), ENT_QUOTES, 'UTF-8'); ?></div>
            <?php else: ?>
              <?php foreach ($recentDetections as $entry): ?>
                <details class="report-card">
                  <summary>
                    <?= htmlspecialchars($entry['hostname'], ENT_QUOTES, 'UTF-8'); ?>
                    <?php if ($entry['blocked']): ?>
                      <span class="badge badge-blocked"><?= htmlspecialchars(t($translations, $currentLanguage, 'blocked'), ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php endif; ?>
                    <?php if ($entry['accepted']): ?>
                      <span class="badge"><?= htmlspecialchars(t($translations, $currentLanguage, 'accepted'), ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php endif; ?>
                    <span class="report-meta">
                      <?= htmlspecialchars($entry['timestamp'], ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                  </summary>
                  <?php if (!empty($entry['url'])): ?>
                    <div class="report-section">
                      <strong><?= htmlspecialchars(t($translations, $currentLanguage, 'url'), ENT_QUOTES, 'UTF-8'); ?></strong>
                      <div class="muted">
                        <a href="<?= htmlspecialchars($entry['url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">
                          <?= htmlspecialchars($entry['url'], ENT_QUOTES, 'UTF-8'); ?>
                        </a>
                      </div>
                    </div>
                  <?php endif; ?>
                  <?php if (!empty($entry['message'])): ?>
                    <div class="report-section">
                      <strong><?= htmlspecialchars(t($translations, $currentLanguage, 'summary'), ENT_QUOTES, 'UTF-8'); ?></strong>
                      <div class="muted"><?= htmlspecialchars($entry['message'], ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>
                  <?php endif; ?>
                  <?php if (!empty($entry['signals'])): ?>
                    <div class="report-section">
                      <strong><?= htmlspecialchars(t($translations, $currentLanguage, 'detected_signals'), ENT_QUOTES, 'UTF-8'); ?></strong>
                      <div class="chip-list">
                        <?php foreach ($entry['signals'] as $signalLabel): ?>
                          <span class="chip signal-chip"><?= htmlspecialchars($signalLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php endforeach; ?>
                      </div>
                    </div>
                  <?php endif; ?>
                  <?php if (!empty($entry['detected'])): ?>
                    <div class="report-section">
                      <strong><?= htmlspecialchars(t($translations, $currentLanguage, 'detected_content'), ENT_QUOTES, 'UTF-8'); ?></strong>
                      <pre><?= htmlspecialchars($entry['detected'], ENT_QUOTES, 'UTF-8'); ?></pre>
                    </div>
                  <?php endif; ?>
                  <?php if (!empty($entry['full_context'])): ?>
                    <div class="report-section context-panel">
                      <strong><?= htmlspecialchars(t($translations, $currentLanguage, 'full_context'), ENT_QUOTES, 'UTF-8'); ?></strong>
                      <pre><?= htmlspecialchars($entry['full_context'], ENT_QUOTES, 'UTF-8'); ?></pre>
                    </div>
                  <?php endif; ?>
                  <?php if ($isAdmin && !$entry['accepted']): ?>
                    <form method="post" class="form-actions" style="margin-top: 12px;">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                      <input type="hidden" name="action" value="accept_detection" />
                      <input type="hidden" name="detection_id" value="<?= (int) $entry['id']; ?>" />
                      <button class="button-primary" type="submit"><?= htmlspecialchars(t($translations, $currentLanguage, 'mark_accepted'), ENT_QUOTES, 'UTF-8'); ?></button>
                    </form>
                  <?php endif; ?>
                </details>
              <?php endforeach; ?>
            <?php endif; ?>
          </section>

          <?php if ($isAdmin): ?>
            <section class="card" style="margin-top: 24px;">
              <h2><?= htmlspecialchars(t($translations, $currentLanguage, 'recent_appeals'), ENT_QUOTES, 'UTF-8'); ?></h2>
              <?php if (empty($appeals)): ?>
                <div class="muted"><?= htmlspecialchars(t($translations, $currentLanguage, 'no_requests'), ENT_QUOTES, 'UTF-8'); ?></div>
              <?php else: ?>
                <?php foreach ($appeals as $appeal): ?>
                  <details class="report-card">
                    <summary>
                      <?= htmlspecialchars((string) ($appeal['domain'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                      <span class="report-meta">
                        <?= htmlspecialchars((string) ($appeal['created_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                      </span>
                    </summary>
                    <div class="report-section">
                      <strong><?= htmlspecialchars(t($translations, $currentLanguage, 'reason'), ENT_QUOTES, 'UTF-8'); ?></strong>
                      <div class="muted"><?= htmlspecialchars((string) ($appeal['reason'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>
                    <?php if (!empty($appeal['contact'])): ?>
                      <div class="report-section">
                        <strong><?= htmlspecialchars(t($translations, $currentLanguage, 'contact_optional'), ENT_QUOTES, 'UTF-8'); ?></strong>
                        <div class="muted"><?= htmlspecialchars((string) ($appeal['contact'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                      </div>
                    <?php endif; ?>
                    <div class="report-section">
                      <strong><?= htmlspecialchars(t($translations, $currentLanguage, 'status'), ENT_QUOTES, 'UTF-8'); ?></strong>
                      <div class="muted"><?= htmlspecialchars((string) ($appeal['status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>
                    <?php if (!in_array((string) ($appeal['status'] ?? ''), ['approved', 'rejected'], true)): ?>
                      <form method="post" class="form-actions" style="margin-top: 12px;">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                        <input type="hidden" name="action" value="appeal_decision" />
                        <input type="hidden" name="appeal_id" value="<?= (int) ($appeal['id'] ?? 0); ?>" />
                        <button class="button-primary button-approve" type="submit" name="decision" value="approve">
                          <?= htmlspecialchars(t($translations, $currentLanguage, 'approve'), ENT_QUOTES, 'UTF-8'); ?>
                        </button>
                        <button class="button-secondary button-reject" type="submit" name="decision" value="reject">
                          <?= htmlspecialchars(t($translations, $currentLanguage, 'reject_appeal'), ENT_QUOTES, 'UTF-8'); ?>
                        </button>
                        <span class="muted"><?= htmlspecialchars(t($translations, $currentLanguage, 'appeal_actions'), ENT_QUOTES, 'UTF-8'); ?></span>
                      </form>
                    <?php endif; ?>
                  </details>
                <?php endforeach; ?>
              <?php endif; ?>
            </section>
          <?php endif; ?>

          <?php if ($isAdmin): ?>
            <section class="card" style="margin-top: 24px;">
              <h2><?= htmlspecialchars(t($translations, $currentLanguage, 'recent_logs'), ENT_QUOTES, 'UTF-8'); ?></h2>
              <div class="log-grid">
                <div class="log-entry">
                  <div class="section-title">
                    <h3>clickfix-report.log</h3>
                    <span class="muted"><?= htmlspecialchars(t($translations, $currentLanguage, 'latest_entries'), ENT_QUOTES, 'UTF-8'); ?></span>
                  </div>
                  <?php if (empty($reportLogStructured)): ?>
                    <div class="muted"><?= htmlspecialchars(t($translations, $currentLanguage, 'log_table_empty'), ENT_QUOTES, 'UTF-8'); ?></div>
                  <?php else: ?>
                    <div class="table-wrap">
                      <table>
                        <thead>
                          <tr>
                            <th><?= htmlspecialchars(t($translations, $currentLanguage, 'log_col_time'), ENT_QUOTES, 'UTF-8'); ?></th>
                            <th><?= htmlspecialchars(t($translations, $currentLanguage, 'log_col_url'), ENT_QUOTES, 'UTF-8'); ?></th>
                            <th><?= htmlspecialchars(t($translations, $currentLanguage, 'log_col_host'), ENT_QUOTES, 'UTF-8'); ?></th>
                            <th><?= htmlspecialchars(t($translations, $currentLanguage, 'log_col_message'), ENT_QUOTES, 'UTF-8'); ?></th>
                            <th><?= htmlspecialchars(t($translations, $currentLanguage, 'log_col_detected'), ENT_QUOTES, 'UTF-8'); ?></th>
                            <th><?= htmlspecialchars(t($translations, $currentLanguage, 'log_col_blocked'), ENT_QUOTES, 'UTF-8'); ?></th>
                            <th><?= htmlspecialchars(t($translations, $currentLanguage, 'log_col_country'), ENT_QUOTES, 'UTF-8'); ?></th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php foreach ($reportLogStructured as $entry): ?>
                            <tr>
                              <td><?= htmlspecialchars($entry['received_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                              <td>
                                <?php if ($entry['url'] !== ''): ?>
                                  <a href="<?= htmlspecialchars($entry['url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">
                                    <?= htmlspecialchars($entry['url'], ENT_QUOTES, 'UTF-8'); ?>
                                  </a>
                                <?php endif; ?>
                              </td>
                              <td><?= htmlspecialchars($entry['hostname'], ENT_QUOTES, 'UTF-8'); ?></td>
                              <td><?= htmlspecialchars($entry['message'], ENT_QUOTES, 'UTF-8'); ?></td>
                              <td><?= htmlspecialchars($entry['detected_content'], ENT_QUOTES, 'UTF-8'); ?></td>
                              <td><?= $entry['blocked'] ? htmlspecialchars(t($translations, $currentLanguage, 'blocked'), ENT_QUOTES, 'UTF-8') : ''; ?></td>
                              <td><?= htmlspecialchars($entry['country'], ENT_QUOTES, 'UTF-8'); ?></td>
                            </tr>
                          <?php endforeach; ?>
                        </tbody>
                      </table>
                    </div>
                  <?php endif; ?>
                </div>
                <div class="log-entry">
                  <div class="section-title">
                    <h3>clickfix-debug.log</h3>
                    <span class="muted"><?= htmlspecialchars(t($translations, $currentLanguage, 'latest_entries'), ENT_QUOTES, 'UTF-8'); ?></span>
                  </div>
                  <?php if (empty($debugLogEntries)): ?>
                    <div class="muted"><?= htmlspecialchars(t($translations, $currentLanguage, 'no_logs'), ENT_QUOTES, 'UTF-8'); ?></div>
                  <?php else: ?>
                    <?php foreach ($debugLogEntries as $logLine): ?>
                      <pre><?= htmlspecialchars($logLine, ENT_QUOTES, 'UTF-8'); ?></pre>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </div>
              </div>
            </section>
          <?php endif; ?>
        </div>

        <aside>
          <div class="panel-stack">
            <section class="card">
              <div class="section-title">
                <h2><?= htmlspecialchars(t($translations, $currentLanguage, 'access_panel'), ENT_QUOTES, 'UTF-8'); ?></h2>
                <span class="muted"><?= htmlspecialchars(t($translations, $currentLanguage, 'session_panel'), ENT_QUOTES, 'UTF-8'); ?></span>
              </div>
              <div class="highlight">
                <ul class="info-list">
                  <li>
                    <strong><?= htmlspecialchars(t($translations, $currentLanguage, 'access_state'), ENT_QUOTES, 'UTF-8'); ?></strong>
                    <span><?= $currentUser ? htmlspecialchars(t($translations, $currentLanguage, 'active'), ENT_QUOTES, 'UTF-8') : htmlspecialchars(t($translations, $currentLanguage, 'session_none'), ENT_QUOTES, 'UTF-8'); ?></span>
                  </li>
                  <li>
                    <strong><?= htmlspecialchars(t($translations, $currentLanguage, 'access_storage'), ENT_QUOTES, 'UTF-8'); ?></strong>
                    <span><?= $sessionStatus === 'ok' ? 'OK' : htmlspecialchars(t($translations, $currentLanguage, 'review_permissions'), ENT_QUOTES, 'UTF-8'); ?></span>
                  </li>
                  <li>
                    <strong><?= htmlspecialchars(t($translations, $currentLanguage, 'access_role'), ENT_QUOTES, 'UTF-8'); ?></strong>
                    <span><?= $currentUser ? htmlspecialchars((string) ($currentUser['role'] ?? ''), ENT_QUOTES, 'UTF-8') : htmlspecialchars(t($translations, $currentLanguage, 'not_assigned'), ENT_QUOTES, 'UTF-8'); ?></span>
                  </li>
                </ul>
              </div>
              <details open style="margin-top: 12px;">
                <summary class="section-title">
                  <h3><?= htmlspecialchars(t($translations, $currentLanguage, 'login'), ENT_QUOTES, 'UTF-8'); ?></h3>
                  <span class="muted"><?= htmlspecialchars(t($translations, $currentLanguage, 'login_hint'), ENT_QUOTES, 'UTF-8'); ?></span>
                </summary>
                <form method="post" class="form-grid" style="margin-top: 12px;">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                  <input type="hidden" name="action" value="login" />
                  <label>
                    <?= htmlspecialchars(t($translations, $currentLanguage, 'username'), ENT_QUOTES, 'UTF-8'); ?>
                    <input type="text" name="username" required />
                  </label>
                  <label>
                    <?= htmlspecialchars(t($translations, $currentLanguage, 'password'), ENT_QUOTES, 'UTF-8'); ?>
                    <input type="password" name="password" required />
                  </label>
                  <div class="form-actions">
                    <button class="button-primary" type="submit"><?= htmlspecialchars(t($translations, $currentLanguage, 'login'), ENT_QUOTES, 'UTF-8'); ?></button>
                  </div>
                </form>
              </details>
              <details style="margin-top: 12px;">
                <summary class="section-title">
                  <h3><?= htmlspecialchars(t($translations, $currentLanguage, 'quick_register'), ENT_QUOTES, 'UTF-8'); ?></h3>
                  <span class="muted"><?= htmlspecialchars(t($translations, $currentLanguage, 'register_hint'), ENT_QUOTES, 'UTF-8'); ?></span>
                </summary>
                <form method="post" class="form-grid" style="margin-top: 12px;">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                  <input type="hidden" name="action" value="register" />
                  <label>
                    <?= htmlspecialchars(t($translations, $currentLanguage, 'username'), ENT_QUOTES, 'UTF-8'); ?>
                    <input type="text" name="username" required />
                  </label>
                  <label>
                    <?= htmlspecialchars(t($translations, $currentLanguage, 'password'), ENT_QUOTES, 'UTF-8'); ?>
                    <input type="password" name="password" required />
                  </label>
                  <label>
                    <?= htmlspecialchars(t($translations, $currentLanguage, 'admin_code_optional'), ENT_QUOTES, 'UTF-8'); ?>
                    <input type="text" name="admin_code" />
                  </label>
                  <div class="form-actions">
                    <button class="button-secondary" type="submit"><?= htmlspecialchars(t($translations, $currentLanguage, 'register'), ENT_QUOTES, 'UTF-8'); ?></button>
                  </div>
                </form>
              </details>
              <?php if ($currentUser): ?>
                <form method="post" class="form-actions" style="margin-top: 16px;">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                  <input type="hidden" name="action" value="logout" />
                  <button class="button-ghost" type="submit"><?= htmlspecialchars(t($translations, $currentLanguage, 'logout'), ENT_QUOTES, 'UTF-8'); ?></button>
                </form>
              <?php endif; ?>
            </section>

            <section class="card">
              <h2><?= htmlspecialchars(t($translations, $currentLanguage, 'quick_guide'), ENT_QUOTES, 'UTF-8'); ?></h2>
              <ul>
                <li><?= htmlspecialchars(t($translations, $currentLanguage, 'guide_item_1'), ENT_QUOTES, 'UTF-8'); ?></li>
                <li><?= htmlspecialchars(t($translations, $currentLanguage, 'guide_item_2'), ENT_QUOTES, 'UTF-8'); ?></li>
                <li><?= htmlspecialchars(t($translations, $currentLanguage, 'guide_item_3'), ENT_QUOTES, 'UTF-8'); ?></li>
                <li><?= htmlspecialchars(t($translations, $currentLanguage, 'guide_item_4'), ENT_QUOTES, 'UTF-8'); ?></li>
              </ul>
            </section>

            <section class="card">
              <h2><?= htmlspecialchars(t($translations, $currentLanguage, 'countries'), ENT_QUOTES, 'UTF-8'); ?></h2>
              <?php if (empty($stats['countries'])): ?>
                <div class="muted"><?= htmlspecialchars(t($translations, $currentLanguage, 'no_data'), ENT_QUOTES, 'UTF-8'); ?></div>
              <?php else: ?>
                <div class="list-block">
                  <ul>
                    <?php foreach ($stats['countries'] as $country => $count): ?>
                      <li><?= htmlspecialchars($country, ENT_QUOTES, 'UTF-8'); ?>: <?= (int) $count; ?></li>
                    <?php endforeach; ?>
                  </ul>
                </div>
              <?php endif; ?>
            </section>

            <section class="card">
              <h2><?= htmlspecialchars(t($translations, $currentLanguage, 'manual_sites_title'), ENT_QUOTES, 'UTF-8'); ?></h2>
              <?php if (empty($stats['manual_sites'])): ?>
                <div class="muted"><?= htmlspecialchars(t($translations, $currentLanguage, 'no_data'), ENT_QUOTES, 'UTF-8'); ?></div>
              <?php else: ?>
                <div class="chip-list">
                  <?php foreach ($stats['manual_sites'] as $site): ?>
                    <span class="chip"><?= htmlspecialchars($site, ENT_QUOTES, 'UTF-8'); ?></span>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </section>

            <?php if ($isAdmin): ?>
              <section class="card">
                <h2><?= htmlspecialchars(t($translations, $currentLanguage, 'alerted_sites_title'), ENT_QUOTES, 'UTF-8'); ?></h2>
                <?php if (empty($stats['alert_sites'])): ?>
                  <div class="muted"><?= htmlspecialchars(t($translations, $currentLanguage, 'no_data'), ENT_QUOTES, 'UTF-8'); ?></div>
                <?php else: ?>
                  <div class="list-block">
                    <ul>
                      <?php foreach ($stats['alert_sites'] as $site): ?>
                        <li><?= htmlspecialchars($site, ENT_QUOTES, 'UTF-8'); ?></li>
                      <?php endforeach; ?>
                    </ul>
                  </div>
                <?php endif; ?>
              </section>
            <?php endif; ?>
          </div>
        </aside>
      </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
      const chartPayload = <?= json_encode($chartPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
      const createChart = (id, type, data, options = {}) => {
        const canvas = document.getElementById(id);
        if (!canvas) {
          return;
        }
        // eslint-disable-next-line no-new
        new Chart(canvas.getContext("2d"), {
          type,
          data,
          options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
              legend: { display: false }
            },
            ...options
          }
        });
      };

      createChart("chart-alerts-day", "line", {
        labels: chartPayload.daily.labels,
        datasets: [{
          label: chartPayload.labels.alerts,
          data: chartPayload.daily.values,
          borderColor: "#2563eb",
          backgroundColor: "rgba(37, 99, 235, 0.2)",
          tension: 0.3,
          fill: true
        }]
      });

      createChart("chart-alerts-hour", "bar", {
        labels: chartPayload.hourly.labels,
        datasets: [{
          label: chartPayload.labels.alerts,
          data: chartPayload.hourly.values,
          backgroundColor: "#f97316"
        }]
      }, {
        scales: { x: { ticks: { stepSize: 1 } } }
      });

      createChart("chart-alerts-country", "doughnut", {
        labels: chartPayload.countries.labels,
        datasets: [{
          data: chartPayload.countries.values,
          backgroundColor: ["#0ea5e9", "#22c55e", "#a855f7", "#facc15", "#ef4444", "#14b8a6", "#f97316"]
        }]
      });

      createChart("chart-alerts-signals", "bar", {
        labels: chartPayload.signals.labels,
        datasets: [{
          label: chartPayload.labels.signals,
          data: chartPayload.signals.values,
          backgroundColor: "#6366f1"
        }]
      }, {
        indexAxis: "y"
      });
    </script>
  </body>
</html>
