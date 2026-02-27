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
        'skip_to_content' => 'Saltar al contenido',
        'last_update' => 'Última actualización',
        'extension_status_unknown' => 'Estado extensión: sin datos',
        'extension_status_activity' => 'Actividad detectada',
        'extension_enabled' => 'Extensión activa',
        'extension_disabled' => 'Extensión pausada',
        'session_active' => 'Sesión',
        'session_none' => 'Sin sesión activa',
        'dashboard_title' => 'Visión 360° de ClickFix',
        'dashboard_subtitle' => 'Monitorea alertas, actividad por país y el estado de listas en una sola vista accionable.',
        'public_hero_kicker' => 'Analítica pública',
        'public_hero_title' => 'Pulso público de ClickFix',
        'public_hero_subtitle' => 'Datos agregados de actividad de la extensión. No incluye URLs ni IPs.',
        'public_view' => 'Vista pública',
        'public_access_title' => 'Acceso de administrador',
        'public_access_subtitle' => 'Inicia sesión para habilitar gestión y logs privados.',
        'public_analytics_title' => 'Panel público',
        'public_analytics_subtitle' => 'Gráficos agregados en tiempo real (últimas 24h).',
        'total_domains' => 'Dominios totales',
        'last_30_days' => 'Últimos 30 días',
        'nav_overview' => 'Resumen',
        'nav_metrics' => 'Métricas',
        'nav_analytics' => 'Analítica',
        'nav_access' => 'Acceso',
        'nav_lists' => 'Listas',
        'nav_intel' => 'Inteligencia',
        'nav_logs' => 'Logs',
        'nav_home' => 'Inicio',
        'nav_search' => 'Buscar',
        'nav_about' => 'Acerca de',
        'new_domains_title' => 'Nuevos dominios',
        'new_domains_subtitle' => 'Detecciones recientes del radar pÃºblico.',
        'search_title' => 'Busqueda',
        'search_subtitle' => 'Explora dominios, comandos y actividad reciente.',
        'search_placeholder' => 'Busca dominio, comando o contenido...',
        'search_button' => 'Buscar',
        'search_export' => 'Exportar',
        'search_filter_label' => 'Campo',
        'search_filter_any' => 'Cualquiera',
        'search_filter_domain' => 'Dominio',
        'search_filter_command' => 'Comando',
        'search_advanced' => 'Filtros avanzados',
        'search_clear' => 'Limpiar filtros',
        'search_col_action' => 'Acciones',
        'search_col_domain' => 'Dominio',
        'search_col_date' => 'Fecha',
        'search_col_command' => 'Command line',
        'search_action_view' => 'Ver',
        'recent_scan_title' => 'Ultimo escaneo',
        'recent_scan_subtitle' => 'Vista previa antes y despues de la interaccion.',
        'before_label' => 'Antes',
        'after_label' => 'Despues',
        'no_snapshots' => 'Sin capturas disponibles.',
        'about_title' => 'Sobre ClickFix',
        'about_body' => 'ClickFix Mitigator monitorea patrones de comando, secuestro de portapapeles y flujos de ingenieria social.',
        'about_collection_title' => 'Identificacion y coleccion',
        'about_collection_body' => 'Los dominios se detectan por telemetria y reports. Las muestras se priorizan por riesgo y señales activas.',
        'about_public_title' => 'Acceso publico',
        'about_public_body' => 'Las metricas se publican sin exponer URLs ni datos sensibles.',
        'sponsor_label' => 'Patrocinado',
        'sponsor_text' => 'Inteligencia comunitaria para rastrear dominios ClickFix.',
        'recent_alerts' => 'Alertas recientes',
        'manual_sites' => 'Sitios manuales',
        'coverage' => 'Cobertura',
        'countries_label' => 'países',
        'alerted_sites' => 'Sitios alertados',
        'block_rate' => 'Tasa de bloqueo',
        'block_rate_help' => 'Bloqueos sobre alertas totales',
        'alerts_24h' => 'Alertas 24h',
        'blocks_24h' => 'Bloqueos 24h',
        'top_countries' => 'Top países',
        'events' => 'eventos',
        'no_geo_data' => 'Sin datos geográficos.',
        'total_alerts' => 'Alertas totales',
        'total_blocks' => 'Bloqueos totales',
        'extension_history' => 'Histórico de la extensión',
        'confirmed_preventions' => 'Prevenciones confirmadas',
        'unique_hosts' => 'Dominios únicos',
        'unique_hosts_help' => 'Últimos reportes',
        'unique_users' => 'Usuarios unicos',
        'unique_users_help' => 'Ultimas 24h (UA)',
        'manual_domains' => 'Dominios cargados manualmente',
        'recent_events' => 'Últimos eventos visibles',
        'pending_review' => 'Pendientes de revisión',
        'containment_efficiency' => 'Eficiencia de contención',
        'last_24h' => 'Últimas 24h',
        'public_lists' => 'Listas públicas',
        'visible_to_all' => 'Visibles para todos',
        'allowlist' => 'Allowlist',
        'blocklist' => 'Blocklist',
        'alertlist' => 'Lista de alertas',
        'empty_domains' => 'Sin dominios.',
        'list_bulk_actions' => 'Acciones masivas de listas',
        'list_bulk_help' => 'Selecciona dominios para mover o eliminar en lote.',
        'bulk_remove' => 'Eliminar seleccionados',
        'bulk_move_allow' => 'Pasar a allowlist',
        'bulk_move_block' => 'Pasar a blocklist',
        'bulk_move_alert' => 'Pasar a lista de alertas',
        'bulk_clear' => 'Limpiar selección',
        'bulk_selected' => 'Seleccionados',
        'bulk_reason_optional' => 'Motivo (opcional)',
        'bulk_action_reason' => 'Acción masiva de lista',
        'bulk_no_selection' => 'Selecciona al menos un dominio.',
        'investigation_panel' => 'Investigación',
        'investigation_hint' => 'Haz click en un dominio, alerta o evento para investigar.',
        'investigation_empty' => 'Selecciona un elemento para ver acciones rápidas.',
        'investigation_domain' => 'Dominio',
        'investigation_url' => 'URL',
        'investigation_context' => 'Contexto',
        'investigation_time' => 'Fecha',
        'investigation_open' => 'Abrir sitio',
        'investigation_copy' => 'Copiar dominio',
        'investigation_actions' => 'Enviar a lista',
        'investigation_reason' => 'Investigación rápida',
        'tab_investigation' => 'Investigación',
        'tab_lists' => 'Listas',
        'tab_users' => 'Usuarios',
        'tab_access' => 'Acceso',
        'tab_data' => 'Datos',
        'tab_alerts' => 'Alertas',
        'tab_intel' => 'Inteligencia',
        'data_vault_title' => 'Centro de datos',
        'data_vault_subtitle' => 'Acceso completo a todas las tablas almacenadas en la base de datos.',
        'data_table_rows' => 'Filas',
        'data_table_last' => 'Ãšltima actualizaciÃ³n',
        'data_table_export' => 'Exportar CSV',
        'data_table_sensitive' => 'Datos sensibles enmascarados.',
        'data_table_empty' => 'Sin registros.',
        'data_table_reports' => 'Reportes',
        'data_table_stats' => 'EstadÃ­sticas',
        'data_table_appeals' => 'Desistimientos',
        'data_table_list_actions' => 'Acciones de listas',
        'data_table_list_suggestions' => 'Sugerencias de listas',
        'data_table_users' => 'Usuarios',
        'data_table_system' => 'Sistema',
        'action_allow_desc' => 'Permite el dominio y evita bloqueos.',
        'action_block_desc' => 'Bloquea el dominio en todos los equipos.',
        'action_alert_desc' => 'Marca el dominio para seguimiento.',
        'action_remove_desc' => 'Elimina dominios de las listas.',
        'action_clear_desc' => 'Limpia la selección actual.',
        'action_move_allow_desc' => 'Mueve dominios a allowlist.',
        'action_move_block_desc' => 'Mueve dominios a blocklist.',
        'action_move_alert_desc' => 'Mueve dominios a alertlist.',
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
        'alerts_baseline' => 'Baseline de alertas (7d)',
        'blocks_by_day' => 'Bloqueos por d?a',
        'blocks_by_hour' => 'Bloqueos por hora',
        'block_rate_trend' => 'Tasa de bloqueo (tendencia)',
        'review_status_chart' => 'Estado de revisi?n',
        'top_hosts_chart' => 'Top dominios',
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
        'telemetry_logs' => 'Telemetria de la extension',
        'telemetry_subtitle' => 'Ultimas estadisticas recibidas (type=stats).',
        'countries' => 'Países',
        'os_breakdown' => 'Sistemas operativos',
        'browser_breakdown' => 'Navegadores',
        'install_channel_chart' => 'Canal de instalación',
        'install_channel_webstore' => 'Chrome Web Store',
        'install_channel_development' => 'Dev mode',
        'install_channel_sideload' => 'GitHub .zip / local',
        'install_channel_policy' => 'Por política',
        'install_channel_normal' => 'Instalación normal',
        'install_channel_other' => 'Otro',
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
        'access_restricted_title' => 'Acceso restringido',
        'access_restricted_body' => 'Este panel contiene datos sensibles. Inicia sesion con una cuenta administradora.',
        'access_restricted_note' => 'Solo administradores pueden ver y editar datos.',
        'admin_portal' => 'Portal de administracion',
        'access_state' => 'Estado',
        'access_storage' => 'Almacenamiento',
        'access_role' => 'Rol actual',
        'active' => 'Activo',
        'review_permissions' => 'Revisar permisos',
        'not_assigned' => 'No asignado',
        'verified_account' => 'Cuenta verificada',
        'unverified_account' => 'Pendiente verificación',
        'login' => 'Iniciar sesión',
        'login_hint' => 'Usuario sin distinguir mayúsculas',
        'username' => 'Usuario',
        'password' => 'Contraseña',
        'password_optional' => 'Contraseña (opcional)',
        'quick_register' => 'Registro rápido',
        'register_hint' => 'Analistas o administradores',
        'admin_code_optional' => 'Código administrador (opcional)',
        'register' => 'Registrarse',
        'logout' => 'Cerrar sesión',
        'manage_users' => 'Gestión de usuarios',
        'manage_users_hint' => 'Crear, verificar y asignar roles',
        'create_user' => 'Crear usuario',
        'role' => 'Rol',
        'verified' => 'Verificado',
        'actions' => 'Acciones',
        'save' => 'Guardar',
        'delete' => 'Eliminar',
        'verify' => 'Verificar',
        'unverify' => 'Quitar verificación',
        'confirm_delete_user' => '¿Eliminar este usuario?',
        'accepted' => 'Aceptado',
        'not_accepted' => 'No aceptado',
        'accepted_legend' => 'Aceptado = validado por un admin. No aceptado = pendiente de validaciÃ³n.',
        'mark_accepted' => 'Marcar como aceptado',
        'review_status' => 'Estado de revision',
        'review_pending' => 'Pendiente',
        'review_allow' => 'Allowlist',
        'review_block' => 'Blocklist',
        'review_investigate' => 'Investigar',
        'review_actions' => 'Acciones de revision',
        'reviewed_at' => 'Revisado',
        'delete_detection' => 'Eliminar detección',
        'clear_unaccepted' => 'Limpiar no aceptadas',
        'clear_unaccepted_help' => 'Elimina detecciones recientes sin validar.',
        'clear_accepted' => 'Limpiar aceptadas',
        'clear_accepted_help' => 'Elimina detecciones aceptadas.',
        'delete_request' => 'Eliminar solicitud',
        'edit_request' => 'Editar solicitud',
        'update_request' => 'Guardar cambios',
        'remove_domain' => 'Eliminar dominio',
        'log_table_title' => 'Entradas de alertas',
        'log_table_empty' => 'Sin entradas estructuradas.',
        'log_col_time' => 'Fecha',
        'log_col_url' => 'URL',
        'log_col_host' => 'Host',
        'log_col_message' => 'Mensaje',
        'log_col_detected' => 'Detectado',
        'log_col_blocked' => 'Bloqueado',
        'log_col_country' => 'País',
        'log_col_enabled' => 'Extension',
        'log_col_alert_count' => 'Alertas',
        'log_col_block_count' => 'Bloqueos',
        'log_col_manual_sites' => 'Manual sites',
        'log_col_alert_sites' => 'Alert sites',
        'log_col_install_channel' => 'Canal',
        'log_col_install_source' => 'Fuente',
        'log_col_install_type' => 'Tipo',
        'log_col_user_agent' => 'User-Agent',
        'log_value_enabled' => 'Activa',
        'log_value_disabled' => 'Pausada',
        'log_col_raw' => 'Detalle',
        'confirm_clear' => '¿Eliminar detecciones no aceptadas?',
        'confirm_clear_accepted' => '¿Eliminar detecciones aceptadas?',
        'confirm_delete' => '¿Eliminar este registro?',
        'confirm_remove_domain' => '¿Eliminar este dominio de la lista?',
        'default_remove_reason' => 'Eliminación administrativa',
        'intel_section' => 'Fuentes de inteligencia',
        'intel_subtitle' => 'Scraping de referencia para enriquecer señales',
        'intel_refresh' => 'Actualizar',
        'intel_last_fetch' => 'Última sincronización',
        'intel_status' => 'Estado',
        'intel_status_ok' => 'Sincronizado',
        'intel_status_error' => 'Error',
        'intel_highlights' => 'Patrones destacados',
        'intel_focus' => 'Cobertura de amenazas',
        'intel_focus_subtitle' => 'Enfoques clave aprendidos de ClickGrab y PasteEater',
        'language' => 'Idioma',
        'theme' => 'Tema',
        'theme_system' => 'Sistema',
        'theme_light' => 'Claro',
        'theme_dark' => 'Oscuro',
        'version' => 'Versión',
        'filter_table' => 'Filtrar tabla',
        'filter_placeholder' => 'Buscar en la tabla...',
        'filter_no_results' => 'Sin resultados.',
        'toast_refresh_error' => 'No se pudo actualizar la sección.',
        'toast_action_error' => 'Error al procesar la acción.',
        'toast_copy_success' => 'Copiado al portapapeles.',
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
        'flash_detection_reviewed' => 'Deteccion revisada.',
        'flash_detection_review_failed' => 'No se pudo revisar la deteccion.',
        'flash_detection_review_not_found' => 'Deteccion no encontrada.',
        'flash_detection_review_domain' => 'No se encontro un dominio valido para esta deteccion.',
        'review_reason' => 'Revision de deteccion',
        'flash_suggestion_applied' => 'Sugerencia aprobada y aplicada.',
        'flash_suggestion_rejected' => 'Sugerencia rechazada.',
        'flash_appeal_updated' => 'Desistimiento actualizado.',
        'flash_appeal_deleted' => 'Desistimiento eliminado.',
        'flash_appeal_delete_failed' => 'No se pudo eliminar el desistimiento.',
        'flash_appeal_update_failed' => 'No se pudo actualizar el desistimiento.',
        'flash_detection_accepted' => 'Detección marcada como aceptada.',
        'flash_detection_deleted' => 'Detección eliminada.',
        'flash_detection_delete_failed' => 'No se pudo eliminar la detección.',
        'flash_detection_cleared' => 'Detecciones no aceptadas eliminadas.',
        'flash_detection_clear_failed' => 'No se pudieron limpiar las detecciones.',
        'flash_detection_accepted_cleared' => 'Detecciones aceptadas eliminadas.',
        'flash_suggestion_deleted' => 'Sugerencia eliminada.',
        'flash_suggestion_delete_failed' => 'No se pudo eliminar la sugerencia.',
        'flash_suggestion_updated' => 'Sugerencia actualizada.',
        'flash_suggestion_update_failed' => 'No se pudo actualizar la sugerencia.',
        'no_domain' => 'Sin dominio'
    ],
    'en' => [
        'app_title' => 'ClickFix Command Center',
        'skip_to_content' => 'Skip to content',
        'last_update' => 'Last update',
        'extension_status_unknown' => 'Extension status: no data',
        'extension_status_activity' => 'Activity detected',
        'extension_enabled' => 'Extension enabled',
        'extension_disabled' => 'Extension paused',
        'session_active' => 'Session',
        'session_none' => 'No active session',
        'dashboard_title' => 'ClickFix 360° overview',
        'dashboard_subtitle' => 'Monitor alerts, country activity, and list status in a single actionable view.',
        'public_hero_kicker' => 'Public analytics',
        'public_hero_title' => 'Public ClickFix pulse',
        'public_hero_subtitle' => 'Aggregated extension activity. No URLs or IPs included.',
        'public_view' => 'Public view',
        'public_access_title' => 'Administrator access',
        'public_access_subtitle' => 'Sign in to enable management and private logs.',
        'public_analytics_title' => 'Public dashboard',
        'public_analytics_subtitle' => 'Live aggregated charts (last 24h).',
        'total_domains' => 'Total domains',
        'last_30_days' => 'Last 30 days',
        'nav_overview' => 'Overview',
        'nav_metrics' => 'Metrics',
        'nav_analytics' => 'Analytics',
        'nav_access' => 'Access',
        'nav_lists' => 'Lists',
        'nav_intel' => 'Intel',
        'nav_logs' => 'Logs',
        'nav_home' => 'Home',
        'nav_search' => 'Search',
        'nav_about' => 'About',
        'new_domains_title' => 'New domains',
        'new_domains_subtitle' => 'Latest detections from the public radar.',
        'search_title' => 'Search',
        'search_subtitle' => 'Explore domains, commands, and recent activity.',
        'search_placeholder' => 'Search domain, command, or content...',
        'search_button' => 'Search',
        'search_export' => 'Export',
        'search_filter_label' => 'Field',
        'search_filter_any' => 'Any',
        'search_filter_domain' => 'Domain',
        'search_filter_command' => 'Command',
        'search_advanced' => 'Advanced filters',
        'search_clear' => 'Clear filters',
        'search_col_action' => 'Actions',
        'search_col_domain' => 'Domain',
        'search_col_date' => 'Date',
        'search_col_command' => 'Command line',
        'search_action_view' => 'View',
        'recent_scan_title' => 'Most recent scan',
        'recent_scan_subtitle' => 'Preview before and after interaction.',
        'before_label' => 'Before',
        'after_label' => 'After',
        'no_snapshots' => 'No snapshots available.',
        'about_title' => 'About ClickFix',
        'about_body' => 'ClickFix Mitigator monitors command patterns, clipboard hijacking, and social engineering flows.',
        'about_collection_title' => 'Identification & collection',
        'about_collection_body' => 'Domains are detected via telemetry and reports. Samples are prioritized by risk and active signals.',
        'about_public_title' => 'Public access',
        'about_public_body' => 'Metrics are published without exposing URLs or sensitive data.',
        'sponsor_label' => 'Sponsored',
        'sponsor_text' => 'Community intelligence for tracking ClickFix domains.',
        'recent_alerts' => 'Recent alerts',
        'manual_sites' => 'Manual sites',
        'coverage' => 'Coverage',
        'countries_label' => 'countries',
        'alerted_sites' => 'Alerted sites',
        'block_rate' => 'Block rate',
        'block_rate_help' => 'Blocks over total alerts',
        'alerts_24h' => 'Alerts 24h',
        'blocks_24h' => 'Blocks 24h',
        'top_countries' => 'Top countries',
        'events' => 'events',
        'no_geo_data' => 'No geographic data.',
        'total_alerts' => 'Total alerts',
        'total_blocks' => 'Total blocks',
        'extension_history' => 'Extension history',
        'confirmed_preventions' => 'Confirmed preventions',
        'unique_hosts' => 'Unique domains',
        'unique_hosts_help' => 'Latest reports',
        'unique_users' => 'Unique users',
        'unique_users_help' => 'Last 24h (UA)',
        'manual_domains' => 'Manually loaded domains',
        'recent_events' => 'Latest visible events',
        'pending_review' => 'Pending review',
        'containment_efficiency' => 'Containment efficiency',
        'last_24h' => 'Last 24h',
        'public_lists' => 'Public lists',
        'visible_to_all' => 'Visible to everyone',
        'allowlist' => 'Allowlist',
        'blocklist' => 'Blocklist',
        'alertlist' => 'Alert list',
        'empty_domains' => 'No domains.',
        'list_bulk_actions' => 'Bulk list actions',
        'list_bulk_help' => 'Select domains to move or remove in bulk.',
        'bulk_remove' => 'Remove selected',
        'bulk_move_allow' => 'Move to allowlist',
        'bulk_move_block' => 'Move to blocklist',
        'bulk_move_alert' => 'Move to alert list',
        'bulk_clear' => 'Clear selection',
        'bulk_selected' => 'Selected',
        'bulk_reason_optional' => 'Reason (optional)',
        'bulk_action_reason' => 'Bulk list action',
        'bulk_no_selection' => 'Select at least one domain.',
        'investigation_panel' => 'Investigation',
        'investigation_hint' => 'Click a domain, alert, or event to investigate.',
        'investigation_empty' => 'Select an item to see quick actions.',
        'investigation_domain' => 'Domain',
        'investigation_url' => 'URL',
        'investigation_context' => 'Context',
        'investigation_time' => 'Timestamp',
        'investigation_open' => 'Open site',
        'investigation_copy' => 'Copy domain',
        'investigation_actions' => 'Send to list',
        'investigation_reason' => 'Quick investigation',
        'tab_investigation' => 'Investigation',
        'tab_lists' => 'Lists',
        'tab_users' => 'Users',
        'tab_access' => 'Access',
        'tab_data' => 'Data',
        'tab_alerts' => 'Alerts',
        'tab_intel' => 'Intel',
        'data_vault_title' => 'Data vault',
        'data_vault_subtitle' => 'Full access to every table stored in the database.',
        'data_table_rows' => 'Rows',
        'data_table_last' => 'Last update',
        'data_table_export' => 'Export CSV',
        'data_table_sensitive' => 'Sensitive values are masked.',
        'data_table_empty' => 'No records yet.',
        'data_table_reports' => 'Reports',
        'data_table_stats' => 'Stats',
        'data_table_appeals' => 'Appeals',
        'data_table_list_actions' => 'List actions',
        'data_table_list_suggestions' => 'List suggestions',
        'data_table_users' => 'Users',
        'data_table_system' => 'System',
        'action_allow_desc' => 'Allow the domain and skip blocks.',
        'action_block_desc' => 'Block the domain across all devices.',
        'action_alert_desc' => 'Flag the domain for follow up.',
        'action_remove_desc' => 'Remove domains from lists.',
        'action_clear_desc' => 'Clear the current selection.',
        'action_move_allow_desc' => 'Move domains to allowlist.',
        'action_move_block_desc' => 'Move domains to blocklist.',
        'action_move_alert_desc' => 'Move domains to alertlist.',
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
        'alerts_baseline' => 'Alerts baseline (7d)',
        'blocks_by_day' => 'Blocks by day',
        'blocks_by_hour' => 'Blocks by hour',
        'block_rate_trend' => 'Block rate trend',
        'review_status_chart' => 'Review status',
        'top_hosts_chart' => 'Top hosts',
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
        'telemetry_logs' => 'Extension telemetry',
        'telemetry_subtitle' => 'Latest stats payloads (type=stats).',
        'countries' => 'Countries',
        'os_breakdown' => 'Operating systems',
        'browser_breakdown' => 'Browsers',
        'install_channel_chart' => 'Install channel',
        'install_channel_webstore' => 'Chrome Web Store',
        'install_channel_development' => 'Dev mode',
        'install_channel_sideload' => 'GitHub .zip / local',
        'install_channel_policy' => 'Policy',
        'install_channel_normal' => 'Normal install',
        'install_channel_other' => 'Other',
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
        'access_restricted_title' => 'Restricted access',
        'access_restricted_body' => 'This panel contains sensitive data. Sign in with an administrator account.',
        'access_restricted_note' => 'Only administrators can view and edit data.',
        'admin_portal' => 'Administration portal',
        'access_state' => 'State',
        'access_storage' => 'Storage',
        'access_role' => 'Current role',
        'active' => 'Active',
        'review_permissions' => 'Check permissions',
        'not_assigned' => 'Not assigned',
        'verified_account' => 'Verified account',
        'unverified_account' => 'Pending verification',
        'login' => 'Sign in',
        'login_hint' => 'Username is case-insensitive',
        'username' => 'Username',
        'password' => 'Password',
        'password_optional' => 'Password (optional)',
        'quick_register' => 'Quick registration',
        'register_hint' => 'Analysts or administrators',
        'admin_code_optional' => 'Admin code (optional)',
        'register' => 'Register',
        'logout' => 'Sign out',
        'manage_users' => 'User management',
        'manage_users_hint' => 'Create, verify, and assign roles',
        'create_user' => 'Create user',
        'role' => 'Role',
        'verified' => 'Verified',
        'actions' => 'Actions',
        'save' => 'Save',
        'delete' => 'Delete',
        'verify' => 'Verify',
        'unverify' => 'Remove verification',
        'confirm_delete_user' => 'Delete this user?',
        'accepted' => 'Accepted',
        'not_accepted' => 'Not accepted',
        'accepted_legend' => 'Accepted = validated by an admin. Not accepted = pending validation.',
        'mark_accepted' => 'Mark as accepted',
        'review_status' => 'Review status',
        'review_pending' => 'Pending',
        'review_allow' => 'Allowlist',
        'review_block' => 'Blocklist',
        'review_investigate' => 'Investigate',
        'review_actions' => 'Review actions',
        'reviewed_at' => 'Reviewed at',
        'delete_detection' => 'Delete detection',
        'clear_unaccepted' => 'Clear unaccepted',
        'clear_unaccepted_help' => 'Remove recent detections that are not validated.',
        'clear_accepted' => 'Clear accepted',
        'clear_accepted_help' => 'Remove accepted detections.',
        'delete_request' => 'Delete request',
        'edit_request' => 'Edit request',
        'update_request' => 'Save changes',
        'remove_domain' => 'Remove domain',
        'log_table_title' => 'Alert entries',
        'log_table_empty' => 'No structured entries.',
        'log_col_time' => 'Date',
        'log_col_url' => 'URL',
        'log_col_host' => 'Host',
        'log_col_message' => 'Message',
        'log_col_detected' => 'Detected',
        'log_col_blocked' => 'Blocked',
        'log_col_country' => 'Country',
        'log_col_enabled' => 'Extension',
        'log_col_alert_count' => 'Alerts',
        'log_col_block_count' => 'Blocks',
        'log_col_manual_sites' => 'Manual sites',
        'log_col_alert_sites' => 'Alert sites',
        'log_col_install_channel' => 'Channel',
        'log_col_install_source' => 'Source',
        'log_col_install_type' => 'Type',
        'log_col_user_agent' => 'User-Agent',
        'log_value_enabled' => 'Enabled',
        'log_value_disabled' => 'Disabled',
        'log_col_raw' => 'Detail',
        'confirm_clear' => 'Remove unaccepted detections?',
        'confirm_clear_accepted' => 'Remove accepted detections?',
        'confirm_delete' => 'Delete this record?',
        'confirm_remove_domain' => 'Remove this domain from the list?',
        'default_remove_reason' => 'Administrative removal',
        'intel_section' => 'Intel sources',
        'intel_subtitle' => 'Reference scraping to enrich signals',
        'intel_refresh' => 'Refresh',
        'intel_last_fetch' => 'Last sync',
        'intel_status' => 'Status',
        'intel_status_ok' => 'Synced',
        'intel_status_error' => 'Error',
        'intel_highlights' => 'Highlighted patterns',
        'intel_focus' => 'Threat coverage',
        'intel_focus_subtitle' => 'Key focus areas learned from ClickGrab and PasteEater',
        'language' => 'Language',
        'theme' => 'Theme',
        'theme_system' => 'System',
        'theme_light' => 'Light',
        'theme_dark' => 'Dark',
        'version' => 'Version',
        'filter_table' => 'Filter table',
        'filter_placeholder' => 'Search within table...',
        'filter_no_results' => 'No results.',
        'toast_refresh_error' => 'Could not refresh the section.',
        'toast_action_error' => 'Error while processing the action.',
        'toast_copy_success' => 'Copied to clipboard.',
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
        'flash_detection_reviewed' => 'Detection reviewed.',
        'flash_detection_review_failed' => 'Could not review the detection.',
        'flash_detection_review_not_found' => 'Detection not found.',
        'flash_detection_review_domain' => 'No valid domain found for this detection.',
        'review_reason' => 'Detection review',
        'flash_suggestion_applied' => 'Suggestion approved and applied.',
        'flash_suggestion_rejected' => 'Suggestion rejected.',
        'flash_appeal_updated' => 'Appeal updated.',
        'flash_appeal_deleted' => 'Appeal deleted.',
        'flash_appeal_delete_failed' => 'Unable to delete appeal.',
        'flash_appeal_update_failed' => 'Unable to update appeal.',
        'flash_detection_accepted' => 'Detection marked as accepted.',
        'flash_detection_deleted' => 'Detection deleted.',
        'flash_detection_delete_failed' => 'Unable to delete detection.',
        'flash_detection_cleared' => 'Unaccepted detections cleared.',
        'flash_detection_clear_failed' => 'Could not clear detections.',
        'flash_detection_accepted_cleared' => 'Accepted detections cleared.',
        'flash_suggestion_deleted' => 'Suggestion deleted.',
        'flash_suggestion_delete_failed' => 'Unable to delete suggestion.',
        'flash_suggestion_updated' => 'Suggestion updated.',
        'flash_suggestion_update_failed' => 'Unable to update suggestion.',
        'no_domain' => 'No domain'
    ]
];

function t(array $translations, string $lang, string $key): string
{
    return $translations[$lang][$key] ?? $translations['es'][$key] ?? $key;
}

function safeSubstr(string $value, int $start, int $length): string
{
    if (function_exists('mb_substr')) {
        return mb_substr($value, $start, $length);
    }

    return substr($value, $start, $length);
}

function redactCredentials(?string $value, bool $isAdmin): string
{
    if ($value === null) {
        return '';
    }
    $value = (string) $value;
    if ($isAdmin || $value === '') {
        return $value;
    }
    $patterns = [
        '/("?(?:user(?:name)?|login|email)"?\s*[:=]\s*)("?[^\s",;]+")/i' => '$1REDACTED',
        '/("?(?:pass(?:word)?|passwd)"?\s*[:=]\s*)("?[^\s",;]+")/i' => '$1REDACTED',
        '/("?(?:token|access[_-]?token|refresh[_-]?token|api[_-]?key|secret|client[_-]?secret)"?\s*[:=]\s*)("?[^\s",;]+")/i' => '$1REDACTED',
        '/("?(?:user(?:name)?|login|email)"?\s*=>\s*)("?[^\s",;]+")/i' => '$1REDACTED',
        '/("?(?:pass(?:word)?|passwd)"?\s*=>\s*)("?[^\s",;]+")/i' => '$1REDACTED',
        '/("?(?:token|access[_-]?token|refresh[_-]?token|api[_-]?key|secret|client[_-]?secret)"?\s*=>\s*)("?[^\s",;]+")/i' => '$1REDACTED',
        '/(\b(?:user(?:name)?|login|email)\b\s+)(\S+)/i' => '$1REDACTED',
        '/(\b(?:pass(?:word)?|passwd)\b\s+)(\S+)/i' => '$1REDACTED',
        '/(\b(?:token|access[_-]?token|refresh[_-]?token|api[_-]?key|secret|client[_-]?secret)\b\s+)(\S+)/i' => '$1REDACTED',
        '/\bBearer\s+[A-Za-z0-9\-._~+/]+=*\b/i' => 'Bearer REDACTED',
        '/\b[A-Za-z0-9\-_]{10,}\.[A-Za-z0-9\-_]{10,}\.[A-Za-z0-9\-_]{10,}\b/' => 'REDACTED',
        '/\b[A-Z]{2}\d{2}[A-Z0-9]{11,30}\b/' => 'REDACTED',
        '/\bIBAN\b\s*[:=]\s*([A-Z0-9 ]{11,34})/i' => 'IBAN=REDACTED',
        '/\b\d{3}-\d{2}-\d{4}\b/' => 'REDACTED',
        '/(\b(?:dni|nie|nif|passport|pasaporte|cedula|c[eé]dula|documento|identidad|id)\b)\s*[:=]\s*([A-Z0-9\-]{5,})/i' => '$1=REDACTED'
    ];
    foreach ($patterns as $pattern => $replacement) {
        $result = @preg_replace($pattern, $replacement, $value);
        if (is_string($result)) {
            $value = $result;
        }
    }
    return $value;
}

function stripUrlParams(string $url): string
{
    $url = trim($url);
    if ($url === '') {
        return $url;
    }
    $parts = parse_url($url);
    if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
        return $url;
    }
    $clean = $parts['scheme'] . '://' . $parts['host'];
    if (!empty($parts['port'])) {
        $clean .= ':' . $parts['port'];
    }
    if (!empty($parts['path'])) {
        $clean .= $parts['path'];
    }
    return $clean;
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
    ],
    [
        'id' => 'pasteeater',
        'label' => 'PasteEater',
        'url' => 'https://github.com/wmetcalf/PasteEater'
    ],
    [
        'id' => 'clickgrab',
        'label' => 'ClickGrab',
        'url' => 'https://github.com/cdup07/ClickGrab'
    ]
];
$intelCachePath = __DIR__ . '/data/intel-cache.json';
$intelTtlSeconds = 3600;
$carsonScrapeScript = __DIR__ . '/scripts/scrape_carson_domains.php';
$carsonScrapeMetaPath = __DIR__ . '/data/carson-scrape.json';
$carsonScrapeIntervalSeconds = 6 * 3600;
$intelFocusAreas = [
    [
        'icon' => '🔐',
        'title' => 'PowerShell Detection',
        'description' => 'Detecta comandos ofuscados, payloads codificados y descargas remotas.'
    ],
    [
        'icon' => '📋',
        'title' => 'Clipboard Hijacking',
        'description' => 'Monitorea manipulación del portapapeles y reemplazo de direcciones.'
    ],
    [
        'icon' => '🤖',
        'title' => 'Fake CAPTCHA',
        'description' => 'Identifica flujos de verificación falsos usados para ingeniería social.'
    ],
    [
        'icon' => '🔍',
        'title' => 'Code Obfuscation',
        'description' => 'Analiza hex/base64 y técnicas de ocultación en scripts.'
    ],
    [
        'icon' => '🌐',
        'title' => 'URL Analysis',
        'description' => 'Extrae URLs maliciosas y rastrea infraestructura C2.'
    ],
    [
        'icon' => '🛡️',
        'title' => 'Mitigation Guidance',
        'description' => 'Resume controles defensivos propuestos en ClickGrab/PasteEater.'
    ]
];

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

function readJsonPayload(string $path): array
{
    if (!is_readable($path)) {
        return [];
    }
    $raw = (string) file_get_contents($path);
    if ($raw === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function writeJsonPayload(string $path, array $payload): void
{
    $dir = dirname($path);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    @file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
}

function shouldRunCarsonScrape(string $metaPath, int $intervalSeconds): bool
{
    if ($intervalSeconds <= 0) {
        return true;
    }
    $meta = readJsonPayload($metaPath);
    $lastRun = isset($meta['last_run']) ? strtotime((string) $meta['last_run']) : false;
    if ($lastRun === false) {
        return true;
    }
    return (time() - $lastRun) >= $intervalSeconds;
}

function spawnBackgroundProcess(string $command): bool
{
    $osFamily = PHP_OS_FAMILY;
    if (stripos($osFamily, 'Windows') !== false) {
        if (!function_exists('popen')) {
            return false;
        }
        $handle = @popen('start /B "" ' . $command, 'r');
        if ($handle === false) {
            return false;
        }
        @pclose($handle);
        return true;
    }
    if (function_exists('exec')) {
        @exec($command . ' > /dev/null 2>&1 &', $output, $code);
        return $code === 0;
    }
    if (function_exists('shell_exec')) {
        @shell_exec($command . ' > /dev/null 2>&1 &');
        return true;
    }
    return false;
}

function triggerCarsonScrape(string $scriptPath, string $metaPath, int $intervalSeconds): void
{
    if (!is_readable($scriptPath)) {
        return;
    }
    if (!shouldRunCarsonScrape($metaPath, $intervalSeconds)) {
        return;
    }
    $meta = readJsonPayload($metaPath);
    $meta['last_run'] = gmdate('c');
    $meta['status'] = 'queued';
    writeJsonPayload($metaPath, $meta);

    $php = defined('PHP_BINARY') ? PHP_BINARY : 'php';
    $command = escapeshellarg($php) . ' ' . escapeshellarg($scriptPath);
    spawnBackgroundProcess($command);
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
    previous_url TEXT,
    hostname TEXT,
    message TEXT,
    detected_content TEXT,
    full_context TEXT,
    signals_json TEXT,
    blocked INTEGER DEFAULT 0,
    accepted INTEGER DEFAULT 0,
    accepted_by INTEGER,
    accepted_at TEXT,
    review_status TEXT DEFAULT 'pending',
    reviewed_by INTEGER,
    reviewed_at TEXT,
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
    user_agent TEXT,
    install_type TEXT,
    install_source TEXT,
    install_channel TEXT,
    country TEXT
);

CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    created_at TEXT NOT NULL,
    username TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    role TEXT NOT NULL,
    verified INTEGER DEFAULT 0
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
    'alerts_24h' => 0,
    'blocks_24h' => 0,
    'unique_hosts' => 0,
    'unique_users' => 0,
    'manual_sites' => [],
    'countries' => [],
    'os_stats' => [],
    'browser_stats' => [],
    'install_stats' => [],
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
    'daily_blocks' => [],
    'hourly' => array_fill(0, 24, 0),
    'hourly_blocks' => array_fill(0, 24, 0),
    'countries' => [],
    'signals' => [],
    'reviews' => [],
    'hosts' => []
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

function hasTableColumn(PDO $pdo, string $table, string $column): bool
{
    try {
        $columns = $pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $exception) {
        return false;
    }
    foreach ($columns as $entry) {
        if (strcasecmp((string) ($entry['name'] ?? ''), $column) === 0) {
            return true;
        }
    }
    return false;
}

function parseUserAgentSummary(string $userAgent): array
{
    $ua = strtolower($userAgent);
    $os = 'Other';
    if (strpos($ua, 'windows nt') !== false) {
        $os = 'Windows';
    } elseif (strpos($ua, 'android') !== false) {
        $os = 'Android';
    } elseif (strpos($ua, 'iphone') !== false || strpos($ua, 'ipad') !== false || strpos($ua, 'ipod') !== false) {
        $os = 'iOS';
    } elseif (strpos($ua, 'mac os x') !== false) {
        $os = 'macOS';
    } elseif (strpos($ua, 'cros') !== false) {
        $os = 'ChromeOS';
    } elseif (strpos($ua, 'linux') !== false || strpos($ua, 'x11') !== false) {
        $os = 'Linux';
    }

    $browser = 'Other';
    if (strpos($ua, 'edg/') !== false || strpos($ua, 'edgios') !== false || strpos($ua, 'edga') !== false) {
        $browser = 'Edge';
    } elseif (strpos($ua, 'opr/') !== false || strpos($ua, 'opera') !== false) {
        $browser = 'Opera';
    } elseif (strpos($ua, 'vivaldi') !== false) {
        $browser = 'Vivaldi';
    } elseif (strpos($ua, 'brave') !== false) {
        $browser = 'Brave';
    } elseif (strpos($ua, 'samsungbrowser') !== false) {
        $browser = 'Samsung Internet';
    } elseif (strpos($ua, 'ucbrowser') !== false) {
        $browser = 'UC Browser';
    } elseif (strpos($ua, 'firefox') !== false || strpos($ua, 'fxios') !== false) {
        $browser = 'Firefox';
    } elseif (strpos($ua, 'crios') !== false || strpos($ua, 'chrome/') !== false) {
        $browser = 'Chrome';
    } elseif (strpos($ua, 'safari') !== false) {
        $browser = 'Safari';
    } elseif (strpos($ua, 'msie') !== false || strpos($ua, 'trident') !== false) {
        $browser = 'IE';
    }

    return ['os' => $os, 'browser' => $browser];
}

function normalizeCountMap(array $counts, int $limit = 8): array
{
    arsort($counts);
    if ($limit > 0) {
        return array_slice($counts, 0, $limit, true);
    }
    return $counts;
}

function normalizeInstallChannelValue(string $channel, string $type = '', string $source = ''): string
{
    $channel = strtolower(trim($channel));
    $type = strtolower(trim($type));
    $source = strtolower(trim($source));
    if ($channel !== '') {
        return $channel;
    }
    if (str_contains($source, 'webstore') || str_contains($source, 'chrome_webstore')) {
        return 'chrome_webstore';
    }
    if ($type === 'development') {
        return 'development';
    }
    if ($type === 'sideload' || $source === 'local') {
        return 'sideload';
    }
    if ($type === 'normal' && $source === 'policy') {
        return 'policy';
    }
    if ($type === 'normal') {
        return 'normal';
    }
    return 'other';
}

function labelInstallChannel(string $channel, array $translations, string $language): string
{
    $normalized = normalizeInstallChannelValue($channel);
    $map = [
        'chrome_webstore' => 'install_channel_webstore',
        'webstore' => 'install_channel_webstore',
        'development' => 'install_channel_development',
        'dev' => 'install_channel_development',
        'sideload' => 'install_channel_sideload',
        'local' => 'install_channel_sideload',
        'zip' => 'install_channel_sideload',
        'policy' => 'install_channel_policy',
        'normal' => 'install_channel_normal',
        'other' => 'install_channel_other'
    ];
    $key = $map[$normalized] ?? 'install_channel_other';
    return t($translations, $language, $key);
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
        $type = strtolower((string) ($decoded['type'] ?? ''));
        if ($type !== '' && $type !== 'alert') {
            continue;
        }
        $messageRaw = trim((string) ($decoded['message'] ?? ''));
        $detectedRaw = trim((string) ($decoded['detected_content'] ?? ''));
        if ($messageRaw === '' && $detectedRaw === '' && trim((string) ($decoded['hostname'] ?? '')) === '') {
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

function loadStatsLogEntries(string $path, int $limit = 50): array
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
        $type = strtolower((string) ($decoded['type'] ?? ''));
        if ($type !== 'stats') {
            continue;
        }
        $stats = is_array($decoded['stats'] ?? null) ? $decoded['stats'] : [];
        $timestampRaw = $decoded['timestamp'] ?? null;
        $timestamp = '';
        if (is_numeric($timestampRaw)) {
            $timestamp = gmdate('Y-m-d H:i:s', (int) ((int) $timestampRaw / 1000));
        } elseif (!empty($decoded['received_at'])) {
            $timestamp = (string) $decoded['received_at'];
        }
        $manualSites = $stats['manual_sites'] ?? [];
        if (!is_array($manualSites)) {
            $manualSites = [];
        }
        $alertSites = $stats['alert_sites'] ?? [];
        if (!is_array($alertSites)) {
            $alertSites = [];
        }
        $entries[] = [
            'received_at' => $timestamp !== '' ? $timestamp : (string) ($decoded['received_at'] ?? ''),
            'enabled' => !empty($stats['enabled']),
            'alert_count' => (int) ($stats['alert_count'] ?? 0),
            'block_count' => (int) ($stats['block_count'] ?? 0),
            'manual_sites_count' => count($manualSites),
            'alert_sites_count' => count($alertSites),
            'install_channel' => (string) ($stats['install_channel'] ?? ''),
            'install_source' => (string) ($stats['install_source'] ?? ''),
            'install_type' => (string) ($stats['install_type'] ?? ''),
            'user_agent' => (string) ($decoded['user_agent'] ?? ''),
            'country' => (string) ($decoded['country'] ?? '')
        ];
    }
    return array_reverse($entries);
}

function resolveInstallChannelLabel(string $channel, array $translations, string $language): string
{
    $channel = strtolower(trim($channel));
    if ($channel === '') {
        return '';
    }
    $map = [
        'webstore' => 'install_channel_webstore',
        'development' => 'install_channel_development',
        'sideload' => 'install_channel_sideload',
        'policy' => 'install_channel_policy',
        'normal' => 'install_channel_normal'
    ];
    $key = $map[$channel] ?? 'install_channel_other';
    return t($translations, $language, $key);
}

function formatLogValue(mixed $value): string
{
    if (is_array($value)) {
        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '';
    }
    if (is_bool($value)) {
        return $value ? 'true' : 'false';
    }
    if ($value === null) {
        return '';
    }
    return (string) $value;
}

function formatDataCellHtml(mixed $value, string $table, string $column, bool $isAdmin): string
{
    $raw = formatLogValue($value);
    if ($column === 'password_hash') {
        if ($raw === '') {
            return '<span class="muted">—</span>';
        }
        $masked = safeSubstr($raw, 0, 6);
        $tail = safeSubstr($raw, max(0, (function_exists('mb_strlen') ? mb_strlen($raw) : strlen($raw)) - 4), 4);
        return '<code class="cell-code">' . htmlspecialchars($masked . '…' . $tail, ENT_QUOTES, 'UTF-8') . '</code>';
    }

    if ($raw === '') {
        return '<span class="muted">—</span>';
    }

    $raw = redactCredentials($raw, $isAdmin);
    $length = function_exists('mb_strlen') ? mb_strlen($raw) : strlen($raw);
    $safe = htmlspecialchars($raw, ENT_QUOTES, 'UTF-8');

    if ($length > 220) {
        $preview = safeSubstr($raw, 0, 220) . '…';
        $safePreview = htmlspecialchars($preview, ENT_QUOTES, 'UTF-8');
        return '<details class="cell-expand" data-allow-toggle="true"><summary><code class="cell-code">'
            . $safePreview . '</code></summary><pre>' . $safe . '</pre></details>';
    }
    if ($length > 80 || $column === 'signals_json' || $column === 'full_context' || $column === 'detected_content' || $column === 'message') {
        return '<code class="cell-code">' . $safe . '</code>';
    }
    return $safe;
}

function loadLogTableEntries(string $path, int $limit = 50): array
{
    if (!is_readable($path)) {
        return ['columns' => [], 'rows' => []];
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES) ?: [];
    if ($limit > 0) {
        $lines = array_slice($lines, -$limit);
    }
    $columns = [];
    $rows = [];
    foreach ($lines as $line) {
        $decoded = json_decode($line, true);
        if (is_array($decoded)) {
            unset($decoded['ip']);
            $row = [];
            foreach ($decoded as $key => $value) {
                $row[$key] = formatLogValue($value);
                if (!in_array($key, $columns, true)) {
                    $columns[] = $key;
                }
            }
            $rows[] = $row;
        } else {
            $rows[] = ['raw' => $line];
            if (!in_array('raw', $columns, true)) {
                $columns[] = 'raw';
            }
        }
    }
    $preferred = ['timestamp', 'received_at', 'level', 'message', 'url', 'hostname', 'country', 'blocked', 'detected_content'];
    $ordered = [];
    foreach ($preferred as $key) {
        if (in_array($key, $columns, true)) {
            $ordered[] = $key;
        }
    }
    foreach ($columns as $key) {
        if (!in_array($key, $ordered, true)) {
            $ordered[] = $key;
        }
    }
    return ['columns' => $ordered, 'rows' => array_reverse($rows)];
}

function ensureAdminTables(PDO $pdo): void
{
    $statements = [
        'CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            created_at TEXT NOT NULL,
            username TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            role TEXT NOT NULL,
            verified INTEGER DEFAULT 0
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
    if (!isset($existing['verified'])) {
        $pdo->exec('ALTER TABLE users ADD COLUMN verified INTEGER DEFAULT 0');
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
    if (!isset($existing['review_status'])) {
        $pdo->exec("ALTER TABLE reports ADD COLUMN review_status TEXT DEFAULT 'pending'");
    }
    if (!isset($existing['reviewed_by'])) {
        $pdo->exec('ALTER TABLE reports ADD COLUMN reviewed_by INTEGER');
    }
    if (!isset($existing['reviewed_at'])) {
        $pdo->exec('ALTER TABLE reports ADD COLUMN reviewed_at TEXT');
    }
    if (!isset($existing['previous_url'])) {
        $pdo->exec('ALTER TABLE reports ADD COLUMN previous_url TEXT');
    }
}

function ensureDefaultAdmin(PDO $pdo, array &$flashNotices, array $translations, string $language): void
{
    $result = $pdo->query('SELECT COUNT(*) as total FROM users')->fetch(PDO::FETCH_ASSOC);
    $total = (int) ($result['total'] ?? 0);
    if ($total > 0) {
        return;
    }
    $password = 'Cl1ckfixP4ssword';
    $statement = $pdo->prepare(
        'INSERT INTO users (created_at, username, password_hash, role, verified)
         VALUES (:created_at, :username, :password_hash, :role, :verified)'
    );
    $statement->execute([
        ':created_at' => gmdate('c'),
        ':username' => 'admin',
        ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
        ':role' => 'admin',
        ':verified' => 1
    ]);
    $flashNotices[] = t($translations, $language, 'flash_default_admin') . $password;
}

function parseSchemaTables(string $schemaSql): array
{
    $tables = [];
    if ($schemaSql === '') {
        return $tables;
    }
    $pattern = '/CREATE\\s+TABLE\\s+IF\\s+NOT\\s+EXISTS\\s+([a-zA-Z0-9_]+)\\s*\\((.*?)\\)\\s*;/si';
    if (!preg_match_all($pattern, $schemaSql, $matches, PREG_SET_ORDER)) {
        return $tables;
    }
    foreach ($matches as $match) {
        $tableName = (string) $match[1];
        $body = (string) $match[2];
        $segments = [];
        $current = '';
        $depth = 0;
        $length = strlen($body);
        for ($i = 0; $i < $length; $i++) {
            $char = $body[$i];
            if ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth = max(0, $depth - 1);
            } elseif ($char === ',' && $depth === 0) {
                $segments[] = $current;
                $current = '';
                continue;
            }
            $current .= $char;
        }
        if (trim($current) !== '') {
            $segments[] = $current;
        }

        $columns = [];
        foreach ($segments as $segment) {
            $line = trim($segment);
            if ($line === '') {
                continue;
            }
            $upper = strtoupper($line);
            if (str_starts_with($upper, 'PRIMARY KEY')
                || str_starts_with($upper, 'UNIQUE')
                || str_starts_with($upper, 'FOREIGN KEY')
                || str_starts_with($upper, 'CONSTRAINT')
                || str_starts_with($upper, 'CHECK')) {
                continue;
            }
            $parts = preg_split('/\\s+/', $line, 2);
            if (!$parts || $parts[0] === '') {
                continue;
            }
            $columnName = trim($parts[0], "`\"'");
            if ($columnName === '') {
                continue;
            }
            $columns[$columnName] = $line;
        }
        if (!empty($columns)) {
            $tables[$tableName] = $columns;
        }
    }
    return $tables;
}

function normalizeColumnForAlter(string $definition): string
{
    $definition = trim($definition);
    if ($definition === '') {
        return '';
    }
    $upper = strtoupper($definition);
    if (str_contains($upper, 'PRIMARY KEY') || str_contains($upper, 'AUTOINCREMENT')) {
        return '';
    }
    $definition = preg_replace('/\\bUNIQUE\\b/i', '', $definition) ?? $definition;
    $definition = preg_replace('/\\s+/', ' ', trim($definition)) ?? $definition;
    if (str_contains($upper, 'NOT NULL') && !str_contains($upper, 'DEFAULT')) {
        $defaultValue = "DEFAULT ''";
        if (preg_match('/\\b(INT|INTEGER|REAL|FLOAT|DOUBLE|NUMERIC)\\b/i', $definition)) {
            $defaultValue = 'DEFAULT 0';
        }
        $definition .= ' ' . $defaultValue;
    }
    return trim($definition);
}

function syncSchema(PDO $pdo, string $schemaSql): void
{
    if ($schemaSql === '') {
        return;
    }
    $pdo->exec($schemaSql);
    $tables = parseSchemaTables($schemaSql);
    foreach ($tables as $tableName => $columns) {
        $info = $pdo->query('PRAGMA table_info(' . $tableName . ')')->fetchAll(PDO::FETCH_ASSOC);
        $existing = [];
        foreach ($info as $column) {
            if (!empty($column['name'])) {
                $existing[(string) $column['name']] = true;
            }
        }
        foreach ($columns as $columnName => $definition) {
            if (isset($existing[$columnName])) {
                continue;
            }
            $alterDefinition = normalizeColumnForAlter($definition);
            if ($alterDefinition === '') {
                continue;
            }
            $pdo->exec('ALTER TABLE ' . $tableName . ' ADD COLUMN ' . $alterDefinition);
        }
    }
}

function ensureDatabase(string $dbPath, ?string $schemaPath, string $schemaSqlFallback): void
{
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
        syncSchema($pdo, (string) $schemaSql);
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

$isAjaxRequest = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';

$reportLogEntries = loadLogEntries(__DIR__ . '/clickfix-report.log', 60);
$reportLogStructured = loadStructuredLogEntries(__DIR__ . '/clickfix-report.log', 40);
$statsLogEntries = loadStatsLogEntries(__DIR__ . '/clickfix-report.log', 60);
$debugLogTable = loadLogTableEntries(__DIR__ . '/clickfix-debug.log', 60);
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
    $statement = $pdo->prepare('SELECT id, username, role, verified FROM users WHERE id = :id');
    $statement->execute([':id' => (int) $_SESSION['user_id']]);
    $currentUser = $statement->fetch(PDO::FETCH_ASSOC) ?: null;
    if (!$currentUser) {
        unset($_SESSION['user_id']);
    }
}
$isAdmin = $currentUser && ($currentUser['role'] ?? '') === 'admin';
$isAnalyst = $currentUser && ($currentUser['role'] ?? '') === 'analyst';
$isVerified = $currentUser && (int) ($currentUser['verified'] ?? 0) === 1;
$canViewLogs = $isAdmin;
$canViewDashboard = $isAdmin || $isAnalyst;
$publicAnalyticsEnabled = true;
$allowedPages = ['home', 'search', 'about', 'access', 'data', 'dashboard'];
$page = (string) ($_GET['page'] ?? '');
if (!in_array($page, $allowedPages, true)) {
    $page = '';
}
$forcePublicView = $publicAnalyticsEnabled && isset($_GET['public']);
$isPublicView = $forcePublicView || ($publicAnalyticsEnabled && !$canViewDashboard);
$publicPage = $page !== '' ? $page : 'home';
$showPublicHome = $isPublicView && ($page === '' || $page === 'home');
$showPublicSearch = $isPublicView && ($page === '' || $page === 'search');
$showPublicAbout = $isPublicView && ($page === '' || $page === 'about');
$showPublicAccess = $isPublicView && ($page === '' || $page === 'access');
$showAdminDashboard = $canViewDashboard && ($page === '' || $page === 'dashboard');
$showAdminData = $canViewDashboard && ($page === '' || $page === 'data');

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
            $username = safeSubstr($username, 0, 64);
            $password = safeSubstr($password, 0, 128);
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
                        'INSERT INTO users (created_at, username, password_hash, role, verified)
                         VALUES (:created_at, :username, :password_hash, :role, :verified)'
                    );
                    $statement->execute([
                        ':created_at' => gmdate('c'),
                        ':username' => $username,
                        ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
                        ':role' => $role,
                        ':verified' => $role === 'admin' ? 1 : 0
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
            $username = safeSubstr($username, 0, 64);
            $password = safeSubstr($password, 0, 128);
            $rateLimited = ($attempts['count'] ?? 0) >= 8;
            if ($rateLimited) {
                $flashErrors[] = t($translations, $currentLanguage, 'flash_invalid_credentials');
                $_SESSION['login_attempts'] = $attempts;
            }
            $statement = $pdo->prepare(
                'SELECT id, username, role, verified, password_hash FROM users WHERE LOWER(username) = LOWER(:username)'
            );
            if (!$rateLimited) {
                $statement->execute([':username' => $username]);
            }
            $user = $rateLimited ? null : $statement->fetch(PDO::FETCH_ASSOC);
            if ($user && password_verify($password, (string) $user['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = (int) $user['id'];
                $currentUser = [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'role' => $user['role'],
                    'verified' => $user['verified'] ?? 0
                ];
                $isAdmin = ($currentUser['role'] ?? '') === 'admin';
                $isAnalyst = ($currentUser['role'] ?? '') === 'analyst';
                $isVerified = (int) ($currentUser['verified'] ?? 0) === 1;
                $canViewLogs = $isAdmin;
                $canViewDashboard = $isAdmin || $isAnalyst;
                $isPublicView = $forcePublicView || ($publicAnalyticsEnabled && !$canViewDashboard);
                $flashNotices[] = t($translations, $currentLanguage, 'flash_login_success');
                $_SESSION['login_attempts'] = ['count' => 0, 'last' => $now];
                if ($isAdmin) {
                    triggerCarsonScrape($carsonScrapeScript, $carsonScrapeMetaPath, $carsonScrapeIntervalSeconds);
                }
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
            $isAdmin = false;
            $isAnalyst = false;
            $isVerified = false;
            $canViewLogs = false;
            $canViewDashboard = false;
            $isPublicView = $forcePublicView || ($publicAnalyticsEnabled && !$canViewDashboard);
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
            $listType = in_array($listType, ['allow', 'block', 'alert'], true) ? $listType : '';
            $mode = (string) ($_POST['mode'] ?? '');
            $isRemove = $mode === 'remove';
            if (!$isAdmin) {
                $flashErrors[] = t($translations, $currentLanguage, 'flash_admin_required');
            } elseif ($listType === '' || !isValidDomain($domain) || (!$isRemove && $reason === '')) {
                $flashErrors[] = t($translations, $currentLanguage, 'flash_appeal_required');
            } else {
                if ($isRemove && $reason === '') {
                    $reason = t($translations, $currentLanguage, 'default_remove_reason');
                }
                $listPath = $listType === 'allow'
                    ? $allowlistFile
                    : ($listType === 'alert' ? $alertsitesFile : $blocklistFile);
                $ok = updateListFile($listPath, $domain, $isRemove ? 'remove' : 'add');
                if ($ok) {
                    if (!$isRemove && $listType === 'alert') {
                        updateListFile($allowlistFile, $domain, 'remove');
                        updateListFile($blocklistFile, $domain, 'remove');
                    }
                    $statement = $pdo->prepare(
                        'INSERT INTO list_actions (created_at, user_id, action, list_type, domain, reason)
                         VALUES (:created_at, :user_id, :action, :list_type, :domain, :reason)'
                    );
                    $statement->execute([
                        ':created_at' => gmdate('c'),
                        ':user_id' => (int) ($currentUser['id'] ?? 0),
                        ':action' => $isRemove ? 'remove' : 'add',
                        ':list_type' => $listType,
                        ':domain' => $domain,
                        ':reason' => $reason
                    ]);
                    $flashNotices[] = t($translations, $currentLanguage, 'flash_list_updated');
                } else {
                    $flashErrors[] = t($translations, $currentLanguage, 'flash_list_failed');
                }
            }
        } elseif ($action === 'list_bulk_action' && $pdo instanceof PDO) {
            $isAdmin = $currentUser && ($currentUser['role'] ?? '') === 'admin';
            $operation = (string) ($_POST['operation'] ?? '');
            $targetList = (string) ($_POST['target_list'] ?? '');
            $reason = trim((string) ($_POST['reason'] ?? ''));
            if ($reason === '') {
                $reason = t($translations, $currentLanguage, 'bulk_action_reason');
            }
            $domainInputs = $_POST['domains'] ?? [];
            $domains = [];
            if (is_array($domainInputs)) {
                foreach ($domainInputs as $domainInput) {
                    $domains[] = (string) $domainInput;
                }
            }
            $domainsJson = (string) ($_POST['domains_json'] ?? '');
            if ($domainsJson !== '') {
                $decoded = json_decode($domainsJson, true);
                if (is_array($decoded)) {
                    foreach ($decoded as $item) {
                        if (is_string($item)) {
                            $domains[] = $item;
                        } elseif (is_array($item) && isset($item['domain'])) {
                            $domains[] = (string) $item['domain'];
                        }
                    }
                }
            }
            $normalizedDomains = [];
            foreach ($domains as $domainInput) {
                $candidate = normalizeDomain((string) $domainInput);
                if (isValidDomain($candidate)) {
                    $normalizedDomains[$candidate] = true;
                }
            }
            $operation = in_array($operation, ['remove', 'move'], true) ? $operation : '';
            $targetList = in_array($targetList, ['allow', 'block', 'alert'], true) ? $targetList : '';
            if (!$isAdmin) {
                $flashErrors[] = t($translations, $currentLanguage, 'flash_admin_required');
            } elseif ($operation === '' || ($operation === 'move' && $targetList === '') || empty($normalizedDomains)) {
                $flashErrors[] = t($translations, $currentLanguage, 'flash_list_failed');
            } else {
                $allOk = true;
                $actionLabel = $operation === 'move' ? 'bulk_move' : 'bulk_remove';
                $listLabel = $operation === 'move' ? $targetList : 'all';
                foreach (array_keys($normalizedDomains) as $domain) {
                    if ($operation === 'remove') {
                        $allOk = updateListFile($allowlistFile, $domain, 'remove') && $allOk;
                        $allOk = updateListFile($blocklistFile, $domain, 'remove') && $allOk;
                        $allOk = updateListFile($alertsitesFile, $domain, 'remove') && $allOk;
                    } else {
                        $targetPath = $targetList === 'allow'
                            ? $allowlistFile
                            : ($targetList === 'alert' ? $alertsitesFile : $blocklistFile);
                        $allOk = updateListFile($targetPath, $domain, 'add') && $allOk;
                        if ($targetList !== 'allow') {
                            updateListFile($allowlistFile, $domain, 'remove');
                        }
                        if ($targetList !== 'block') {
                            updateListFile($blocklistFile, $domain, 'remove');
                        }
                        if ($targetList !== 'alert') {
                            updateListFile($alertsitesFile, $domain, 'remove');
                        }
                    }
                    if ($allOk) {
                        $statement = $pdo->prepare(
                            'INSERT INTO list_actions (created_at, user_id, action, list_type, domain, reason)
                             VALUES (:created_at, :user_id, :action, :list_type, :domain, :reason)'
                        );
                        $statement->execute([
                            ':created_at' => gmdate('c'),
                            ':user_id' => (int) ($currentUser['id'] ?? 0),
                            ':action' => $actionLabel,
                            ':list_type' => $listLabel,
                            ':domain' => $domain,
                            ':reason' => $reason
                        ]);
                    }
                }
                if ($allOk) {
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
            $listType = in_array($listType, ['allow', 'block', 'alert'], true) ? $listType : '';
            if (!$currentUser) {
                $flashErrors[] = t($translations, $currentLanguage, 'flash_login_required');
            } elseif ($listType === '' || !isValidDomain($domain) || $reason === '') {
                $flashErrors[] = t($translations, $currentLanguage, 'flash_appeal_required');
            } else {
                $statement = $pdo->prepare(
                    'INSERT INTO list_suggestions (created_at, user_id, list_type, domain, reason, status)
                     VALUES (:created_at, :user_id, :list_type, :domain, :reason, :status)'
                );
                $statement->execute([
                    ':created_at' => gmdate('c'),
                    ':user_id' => (int) ($currentUser['id'] ?? 0),
                    ':list_type' => $listType,
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
                    $suggestionType = (string) ($suggestion['list_type'] ?? '');
                    $listPath = $suggestionType === 'allow'
                        ? $allowlistFile
                        : ($suggestionType === 'alert' ? $alertsitesFile : $blocklistFile);
                    $ok = updateListFile($listPath, (string) $suggestion['domain'], 'add');
                    if ($ok) {
                        if ($suggestionType === 'alert') {
                            updateListFile($allowlistFile, (string) $suggestion['domain'], 'remove');
                            updateListFile($blocklistFile, (string) $suggestion['domain'], 'remove');
                        }
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
        } elseif ($action === 'list_suggestion_edit' && $pdo instanceof PDO) {
            $isAdmin = $currentUser && ($currentUser['role'] ?? '') === 'admin';
            $suggestionId = (int) ($_POST['suggestion_id'] ?? 0);
            $domain = normalizeDomain((string) ($_POST['domain'] ?? ''));
            $reason = trim((string) ($_POST['reason'] ?? ''));
            $listType = (string) ($_POST['list_type'] ?? '');
            $listType = in_array($listType, ['allow', 'block', 'alert'], true) ? $listType : '';
            if (!$isAdmin) {
                $flashErrors[] = t($translations, $currentLanguage, 'flash_admin_required');
            } elseif ($listType === '' || !isValidDomain($domain) || $reason === '') {
                $flashErrors[] = t($translations, $currentLanguage, 'flash_appeal_required');
            } else {
                $statement = $pdo->prepare(
                    'UPDATE list_suggestions
                     SET domain = :domain, reason = :reason, list_type = :list_type
                     WHERE id = :id'
                );
                $ok = $statement->execute([
                    ':domain' => $domain,
                    ':reason' => $reason,
                    ':list_type' => $listType,
                    ':id' => $suggestionId
                ]);
                $flashNotices[] = $ok
                    ? t($translations, $currentLanguage, 'flash_suggestion_updated')
                    : t($translations, $currentLanguage, 'flash_suggestion_update_failed');
            }
        } elseif ($action === 'list_suggestion_delete' && $pdo instanceof PDO) {
            $isAdmin = $currentUser && ($currentUser['role'] ?? '') === 'admin';
            $suggestionId = (int) ($_POST['suggestion_id'] ?? 0);
            if (!$isAdmin) {
                $flashErrors[] = t($translations, $currentLanguage, 'flash_admin_required');
            } else {
                $statement = $pdo->prepare('DELETE FROM list_suggestions WHERE id = :id');
                $ok = $statement->execute([':id' => $suggestionId]);
                $flashNotices[] = $ok
                    ? t($translations, $currentLanguage, 'flash_suggestion_deleted')
                    : t($translations, $currentLanguage, 'flash_suggestion_delete_failed');
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
        } elseif ($action === 'appeal_update' && $pdo instanceof PDO) {
            $isAdmin = $currentUser && ($currentUser['role'] ?? '') === 'admin';
            $appealId = (int) ($_POST['appeal_id'] ?? 0);
            $domain = normalizeDomain((string) ($_POST['domain'] ?? ''));
            $reason = trim((string) ($_POST['reason'] ?? ''));
            $contact = trim((string) ($_POST['contact'] ?? ''));
            if (!$isAdmin) {
                $flashErrors[] = t($translations, $currentLanguage, 'flash_admin_required');
            } elseif (!isValidDomain($domain) || $reason === '') {
                $flashErrors[] = t($translations, $currentLanguage, 'flash_appeal_required');
            } else {
                $statement = $pdo->prepare(
                    'UPDATE appeals
                     SET domain = :domain, reason = :reason, contact = :contact
                     WHERE id = :id'
                );
                $ok = $statement->execute([
                    ':domain' => $domain,
                    ':reason' => $reason,
                    ':contact' => $contact,
                    ':id' => $appealId
                ]);
                $flashNotices[] = $ok
                    ? t($translations, $currentLanguage, 'flash_appeal_updated')
                    : t($translations, $currentLanguage, 'flash_appeal_update_failed');
            }
        } elseif ($action === 'appeal_delete' && $pdo instanceof PDO) {
            $isAdmin = $currentUser && ($currentUser['role'] ?? '') === 'admin';
            $appealId = (int) ($_POST['appeal_id'] ?? 0);
            if (!$isAdmin) {
                $flashErrors[] = t($translations, $currentLanguage, 'flash_admin_required');
            } else {
                $statement = $pdo->prepare('DELETE FROM appeals WHERE id = :id');
                $ok = $statement->execute([':id' => $appealId]);
                $flashNotices[] = $ok
                    ? t($translations, $currentLanguage, 'flash_appeal_deleted')
                    : t($translations, $currentLanguage, 'flash_appeal_delete_failed');
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
        } elseif ($action === 'review_detection' && $pdo instanceof PDO) {
            $isAdmin = $currentUser && ($currentUser['role'] ?? '') === 'admin';
            $detectionId = (int) ($_POST['detection_id'] ?? 0);
            $decision = (string) ($_POST['decision'] ?? '');
            $decision = in_array($decision, ['allow', 'block', 'investigate'], true) ? $decision : '';
            if (!$isAdmin) {
                $flashErrors[] = t($translations, $currentLanguage, 'flash_admin_required');
            } elseif ($decision === '' || $detectionId <= 0) {
                $flashErrors[] = t($translations, $currentLanguage, 'flash_detection_review_failed');
            } else {
                $statement = $pdo->prepare('SELECT id, hostname, url FROM reports WHERE id = :id');
                $statement->execute([':id' => $detectionId]);
                $report = $statement->fetch(PDO::FETCH_ASSOC);
                if (!$report) {
                    $flashErrors[] = t($translations, $currentLanguage, 'flash_detection_review_not_found');
                } else {
                    $domain = normalizeDomain((string) ($report['hostname'] ?? ''));
                    if ($domain === '') {
                        $url = (string) ($report['url'] ?? '');
                        if ($url !== '') {
                            $parsedUrl = parse_url($url);
                            $domain = normalizeDomain((string) ($parsedUrl['host'] ?? ''));
                        }
                    }
                    if (!isValidDomain($domain)) {
                        $flashErrors[] = t($translations, $currentLanguage, 'flash_detection_review_domain');
                    } else {
                        $reviewedAt = gmdate('c');
                        $listType = $decision === 'investigate' ? 'alert' : $decision;
                        $targetPath = $listType === 'allow'
                            ? $allowlistFile
                            : ($listType === 'alert' ? $alertsitesFile : $blocklistFile);
                        $reason = t($translations, $currentLanguage, 'review_reason') . ' #' . $detectionId;
                        $ok = updateListFile($targetPath, $domain, 'add');
                        if (!$ok) {
                            $flashErrors[] = t($translations, $currentLanguage, 'flash_detection_review_failed');
                        } else {
                            if ($listType !== 'allow') {
                                updateListFile($allowlistFile, $domain, 'remove');
                            }
                            if ($listType !== 'block') {
                                updateListFile($blocklistFile, $domain, 'remove');
                            }
                            if ($listType !== 'alert') {
                                updateListFile($alertsitesFile, $domain, 'remove');
                            }
                            $statement = $pdo->prepare(
                                'INSERT INTO list_actions (created_at, user_id, action, list_type, domain, reason)
                                 VALUES (:created_at, :user_id, :action, :list_type, :domain, :reason)'
                            );
                            $statement->execute([
                                ':created_at' => $reviewedAt,
                                ':user_id' => (int) ($currentUser['id'] ?? 0),
                                ':action' => 'review',
                                ':list_type' => $listType,
                                ':domain' => $domain,
                                ':reason' => $reason
                            ]);
                            $statement = $pdo->prepare(
                                'UPDATE reports
                                 SET accepted = 1,
                                     accepted_by = :user_id,
                                     accepted_at = :accepted_at,
                                     review_status = :review_status,
                                     reviewed_by = :reviewed_by,
                                     reviewed_at = :reviewed_at
                                 WHERE id = :id'
                            );
                            $statement->execute([
                                ':user_id' => (int) ($currentUser['id'] ?? 0),
                                ':accepted_at' => $reviewedAt,
                                ':review_status' => $decision,
                                ':reviewed_by' => (int) ($currentUser['id'] ?? 0),
                                ':reviewed_at' => $reviewedAt,
                                ':id' => $detectionId
                            ]);
                            $flashNotices[] = t($translations, $currentLanguage, 'flash_detection_reviewed');
                        }
                    }
                }
            }
        } elseif ($action === 'delete_detection' && $pdo instanceof PDO) {
            $isAdmin = $currentUser && ($currentUser['role'] ?? '') === 'admin';
            $detectionId = (int) ($_POST['detection_id'] ?? 0);
            if (!$isAdmin) {
                $flashErrors[] = t($translations, $currentLanguage, 'flash_admin_required');
            } else {
                $statement = $pdo->prepare('DELETE FROM reports WHERE id = :id');
                $ok = $statement->execute([':id' => $detectionId]);
                $flashNotices[] = $ok
                    ? t($translations, $currentLanguage, 'flash_detection_deleted')
                    : t($translations, $currentLanguage, 'flash_detection_delete_failed');
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
        } elseif ($action === 'clear_accepted' && $pdo instanceof PDO) {
            $isAdmin = $currentUser && ($currentUser['role'] ?? '') === 'admin';
            if (!$isAdmin) {
                $flashErrors[] = t($translations, $currentLanguage, 'flash_admin_required');
            } else {
                $statement = $pdo->prepare('DELETE FROM reports WHERE accepted = 1');
                $ok = $statement->execute();
                if ($ok) {
                    $flashNotices[] = t($translations, $currentLanguage, 'flash_detection_accepted_cleared');
                } else {
                    $flashErrors[] = t($translations, $currentLanguage, 'flash_detection_clear_failed');
                }
            }
        } elseif ($action === 'user_create' && $pdo instanceof PDO) {
            $isAdmin = $currentUser && ($currentUser['role'] ?? '') === 'admin';
            $username = trim((string) ($_POST['username'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');
            $role = (string) ($_POST['role'] ?? 'analyst');
            $verified = (int) ($_POST['verified'] ?? 0);
            $username = safeSubstr($username, 0, 64);
            $password = safeSubstr($password, 0, 128);
            if (!$isAdmin) {
                $flashErrors[] = t($translations, $currentLanguage, 'flash_admin_required');
            } elseif ($username === '' || $password === '') {
                $flashErrors[] = t($translations, $currentLanguage, 'flash_required_credentials');
            } else {
                try {
                    $statement = $pdo->prepare(
                        'INSERT INTO users (created_at, username, password_hash, role, verified)
                         VALUES (:created_at, :username, :password_hash, :role, :verified)'
                    );
                    $statement->execute([
                        ':created_at' => gmdate('c'),
                        ':username' => $username,
                        ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
                        ':role' => in_array($role, ['admin', 'analyst'], true) ? $role : 'analyst',
                        ':verified' => $verified === 1 ? 1 : 0
                    ]);
                    $flashNotices[] = t($translations, $currentLanguage, 'flash_register_success');
                } catch (Throwable $exception) {
                    $flashErrors[] = t($translations, $currentLanguage, 'flash_register_failure');
                }
            }
        } elseif ($action === 'user_update' && $pdo instanceof PDO) {
            $isAdmin = $currentUser && ($currentUser['role'] ?? '') === 'admin';
            $userId = (int) ($_POST['user_id'] ?? 0);
            $role = (string) ($_POST['role'] ?? 'analyst');
            $verified = (int) ($_POST['verified'] ?? 0);
            $password = (string) ($_POST['password'] ?? '');
            $password = safeSubstr(trim($password), 0, 128);
            if (!$isAdmin) {
                $flashErrors[] = t($translations, $currentLanguage, 'flash_admin_required');
            } else {
                if ($password !== '') {
                    $statement = $pdo->prepare(
                        'UPDATE users SET role = :role, verified = :verified, password_hash = :password_hash WHERE id = :id'
                    );
                    $statement->execute([
                        ':role' => in_array($role, ['admin', 'analyst'], true) ? $role : 'analyst',
                        ':verified' => $verified === 1 ? 1 : 0,
                        ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
                        ':id' => $userId
                    ]);
                } else {
                    $statement = $pdo->prepare(
                        'UPDATE users SET role = :role, verified = :verified WHERE id = :id'
                    );
                    $statement->execute([
                        ':role' => in_array($role, ['admin', 'analyst'], true) ? $role : 'analyst',
                        ':verified' => $verified === 1 ? 1 : 0,
                        ':id' => $userId
                    ]);
                }
                $flashNotices[] = t($translations, $currentLanguage, 'flash_list_updated');
            }
        } elseif ($action === 'user_delete' && $pdo instanceof PDO) {
            $isAdmin = $currentUser && ($currentUser['role'] ?? '') === 'admin';
            $userId = (int) ($_POST['user_id'] ?? 0);
            if (!$isAdmin) {
                $flashErrors[] = t($translations, $currentLanguage, 'flash_admin_required');
            } elseif ($userId === (int) ($currentUser['id'] ?? 0)) {
                $flashErrors[] = t($translations, $currentLanguage, 'flash_admin_required');
            } else {
                $statement = $pdo->prepare('DELETE FROM users WHERE id = :id');
                $statement->execute([':id' => $userId]);
                $flashNotices[] = t($translations, $currentLanguage, 'flash_list_updated');
            }
        }
    }
}

if ($isAjaxRequest && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(
        [
            'ok' => empty($flashErrors),
            'errors' => array_values($flashErrors),
            'notices' => array_values($flashNotices)
        ],
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );
    exit;
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

        if (hasTableColumn($pdo, 'stats', 'user_agent')) {
            $cutoff = gmdate('c', time() - 86400);
            $hasInstallType = hasTableColumn($pdo, 'stats', 'install_type');
            $hasInstallSource = hasTableColumn($pdo, 'stats', 'install_source');
            $hasInstallChannel = hasTableColumn($pdo, 'stats', 'install_channel');
            $selectFields = ['user_agent'];
            if ($hasInstallType) {
                $selectFields[] = 'install_type';
            }
            if ($hasInstallSource) {
                $selectFields[] = 'install_source';
            }
            if ($hasInstallChannel) {
                $selectFields[] = 'install_channel';
            }
            $rows = [];
            try {
                $uaStatement = $pdo->prepare(
                    'SELECT ' . implode(', ', $selectFields) . ' FROM stats WHERE received_at >= :cutoff AND user_agent IS NOT NULL AND user_agent != "" ORDER BY received_at DESC'
                );
                $uaStatement->execute([':cutoff' => $cutoff]);
                $rows = $uaStatement->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (Throwable $exception) {
                $rows = [];
            }
            $osCounts = [];
            $browserCounts = [];
            $installCounts = [];
            $uniqueAgents = [];
            foreach ($rows as $row) {
                $agent = trim((string) ($row['user_agent'] ?? ''));
                if ($agent === '' || isset($uniqueAgents[$agent])) {
                    continue;
                }
                $uniqueAgents[$agent] = true;
                $parsed = parseUserAgentSummary($agent);
                $osCounts[$parsed['os']] = ($osCounts[$parsed['os']] ?? 0) + 1;
                $browserCounts[$parsed['browser']] = ($browserCounts[$parsed['browser']] ?? 0) + 1;

                $channel = normalizeInstallChannelValue(
                    (string) ($row['install_channel'] ?? ''),
                    (string) ($row['install_type'] ?? ''),
                    (string) ($row['install_source'] ?? '')
                );
                $installCounts[$channel] = ($installCounts[$channel] ?? 0) + 1;
            }
            $stats['unique_users'] = count($uniqueAgents);
            $stats['os_stats'] = normalizeCountMap($osCounts);
            $stats['browser_stats'] = normalizeCountMap($browserCounts);
            $stats['install_stats'] = normalizeCountMap($installCounts);
        }
        try {
            $reportRows = $pdo->query(
            'SELECT id, received_at, url, hostname, message, detected_content, full_context, signals_json, blocked, accepted, accepted_at, review_status, reviewed_at, country
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

        $uniqueHosts = [];
        $recentWindow = time() - 86400;
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

            $blockedEntry = !empty($entry['blocked']);
            if ($timestamp !== false && $blockedEntry) {
                $chartData['daily_blocks'][$dateKey] = ($chartData['daily_blocks'][$dateKey] ?? 0) + 1;
                if (isset($chartData['hourly_blocks'][$hourKey])) {
                    $chartData['hourly_blocks'][$hourKey] += 1;
                }
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
            if ($hostname !== '') {
                $uniqueHosts[$hostname] = true;
                $chartData['hosts'][$hostname] = ($chartData['hosts'][$hostname] ?? 0) + 1;
            }
            if ($timestamp !== false && $timestamp >= $recentWindow) {
                $stats['alerts_24h'] += 1;
                if ($blockedEntry) {
                    $stats['blocks_24h'] += 1;
                }
            }

            $reviewStatus = trim((string) ($entry['review_status'] ?? ''));
            if ($reviewStatus === '') {
                $reviewStatus = 'pending';
            }
            $reviewLabels = [
                'allow' => t($translations, $currentLanguage, 'review_allow'),
                'block' => t($translations, $currentLanguage, 'review_block'),
                'investigate' => t($translations, $currentLanguage, 'review_investigate'),
                'pending' => t($translations, $currentLanguage, 'review_pending')
            ];
            $reviewLabel = $reviewLabels[$reviewStatus] ?? $reviewStatus;
            $reviewClass = $reviewStatus === 'pending' ? 'pending' : ('review-' . $reviewStatus);
            $chartData['reviews'][$reviewStatus] = ($chartData['reviews'][$reviewStatus] ?? 0) + 1;

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
                'review_status' => $reviewStatus,
                'review_label' => $reviewLabel,
                'review_class' => $reviewClass,
                'reviewed_at' => (string) ($entry['reviewed_at'] ?? ''),
                'signals' => $signalList
            ];
        }
        $stats['unique_hosts'] = count($uniqueHosts);
    } catch (Throwable $exception) {
        $stats = $stats;
    }
    $recentDetections = array_slice($recentDetections, 0, 50);
    $stats['recent_count'] = count($recentDetections);
    if ($isAnalyst) {
        foreach ($recentDetections as &$entry) {
            $entry['url'] = stripUrlParams((string) ($entry['url'] ?? ''));
            $entry['detected'] = '';
            $entry['full_context'] = '';
        }
        unset($entry);
    }
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
    $blockRate = min(100.0, max(0.0, $blockRate));
}
$topCountries = $stats['countries'];
arsort($topCountries);
$topCountries = array_slice($topCountries, 0, 4, true);

$publicTotalDomains = (int) $stats['unique_hosts'];
$publicLast30Days = 0;
$cutoff30 = time() - (30 * 86400);
foreach ($chartData['daily'] as $date => $count) {
    $timestamp = strtotime((string) $date);
    if ($timestamp !== false && $timestamp >= $cutoff30) {
        $publicLast30Days += (int) $count;
    }
}

$recentDomains = [];
foreach ($reportLogStructured as $entry) {
    $domain = normalizeDomain((string) ($entry['hostname'] ?? ''));
    $url = (string) ($entry['url'] ?? '');
    if ($domain === '' && $url !== '') {
        $parsedUrl = parse_url($url);
        if (is_array($parsedUrl) && !empty($parsedUrl['host'])) {
            $domain = normalizeDomain((string) $parsedUrl['host']);
        }
    }
    if ($domain === '') {
        continue;
    }
    $command = trim((string) ($entry['detected_content'] ?? ''));
    if ($command === '') {
        $command = trim((string) ($entry['message'] ?? ''));
    }
    $command = redactCredentials($command, $isAdmin);
    $command = safeSubstr($command, 0, 220);
    $recentDomains[] = [
        'domain' => $domain,
        'date' => (string) ($entry['received_at'] ?? ''),
        'command' => $command,
        'url' => $url
    ];
    if (count($recentDomains) >= 25) {
        break;
    }
}
$recentDomainsHome = array_slice($recentDomains, 0, 8);

$blocklistItems = loadListFile($blocklistFile);
$allowlistItems = loadListFile($allowlistFile);
$alertSites = loadListFile($alertsitesFile);
$blocklistLookup = [];
foreach ($blocklistItems as $item) {
    if (is_string($item) && $item !== '') {
        $blocklistLookup[] = strtolower($item);
    }
}
$allowlistLookup = [];
foreach ($allowlistItems as $item) {
    if (is_string($item) && $item !== '') {
        $allowlistLookup[] = strtolower($item);
    }
}
$matchesList = function (string $domain, array $list): bool {
    foreach ($list as $entry) {
        if ($domain === $entry || str_ends_with($domain, '.' . $entry)) {
            return true;
        }
    }
    return false;
};
$alertSites = array_values(array_filter($alertSites, function ($domain) use ($blocklistLookup, $allowlistLookup, $matchesList) {
    if (!is_string($domain) || $domain === '') {
        return false;
    }
    $normalized = strtolower($domain);
    return !$matchesList($normalized, $blocklistLookup) && !$matchesList($normalized, $allowlistLookup);
}));
$stats['alert_sites'] = $alertSites;
$alertlistItems = $alertSites;
$appeals = [];
$listSuggestions = [];
$userRows = [];
$dataExplorer = [
    'tables' => [],
    'summary' => []
];
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
    try {
        $userRows = $pdo->query(
            'SELECT id, username, role, verified, created_at
             FROM users
             ORDER BY created_at DESC'
        )->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $exception) {
        $userRows = [];
    }

    if ($isAdmin) {
        $tableConfigs = [
            'reports' => [
                'label' => t($translations, $currentLanguage, 'data_table_reports'),
                'order' => 'received_at DESC'
            ],
            'stats' => [
                'label' => t($translations, $currentLanguage, 'data_table_stats'),
                'order' => 'received_at DESC'
            ],
            'appeals' => [
                'label' => t($translations, $currentLanguage, 'data_table_appeals'),
                'order' => 'created_at DESC'
            ],
            'list_actions' => [
                'label' => t($translations, $currentLanguage, 'data_table_list_actions'),
                'order' => 'created_at DESC'
            ],
            'list_suggestions' => [
                'label' => t($translations, $currentLanguage, 'data_table_list_suggestions'),
                'order' => 'created_at DESC'
            ],
            'users' => [
                'label' => t($translations, $currentLanguage, 'data_table_users'),
                'order' => 'created_at DESC'
            ],
            'sqlite_sequence' => [
                'label' => t($translations, $currentLanguage, 'data_table_system'),
                'order' => 'name ASC'
            ]
        ];
        foreach ($tableConfigs as $tableName => $config) {
            try {
                $columnsMeta = $pdo->query('PRAGMA table_info(' . $tableName . ')')->fetchAll(PDO::FETCH_ASSOC);
                $columns = array_map(static fn ($col) => (string) ($col['name'] ?? ''), $columnsMeta);
                $orderClause = $config['order'] ? ' ORDER BY ' . $config['order'] : '';
                $rows = $pdo->query('SELECT * FROM ' . $tableName . $orderClause)->fetchAll(PDO::FETCH_ASSOC);
                $count = (int) ($pdo->query('SELECT COUNT(*) FROM ' . $tableName)->fetchColumn() ?: 0);
                $timestampColumn = '';
                foreach (['received_at', 'created_at', 'reviewed_at', 'accepted_at'] as $candidate) {
                    if (in_array($candidate, $columns, true)) {
                        $timestampColumn = $candidate;
                        break;
                    }
                }
                $lastUpdated = '';
                if ($timestampColumn !== '') {
                    $lastUpdated = (string) ($pdo->query('SELECT MAX(' . $timestampColumn . ') FROM ' . $tableName)->fetchColumn() ?: '');
                }
                $dataExplorer['tables'][] = [
                    'name' => $tableName,
                    'label' => $config['label'],
                    'columns' => $columns,
                    'rows' => $rows,
                    'count' => $count,
                    'last_updated' => $lastUpdated,
                    'sensitive' => in_array('password_hash', $columns, true)
                ];
            } catch (Throwable $exception) {
                $dataExplorer['tables'][] = [
                    'name' => $tableName,
                    'label' => $config['label'],
                    'columns' => [],
                    'rows' => [],
                    'count' => 0,
                    'last_updated' => '',
                    'sensitive' => false
                ];
            }
        }
    }
}

ksort($chartData['daily']);
if (empty($chartData['countries'])) {
    $chartData['countries'] = $reportLogCountries;
}
arsort($chartData['signals']);

$dailyLabels = array_keys($chartData['daily']);
$dailyValues = array_values($chartData['daily']);
$dailyBlocks = [];
foreach ($dailyLabels as $date) {
    $dailyBlocks[] = (int) ($chartData['daily_blocks'][$date] ?? 0);
}
$baselineValues = [];
$windowSize = 7;
$dailyCount = count($dailyValues);
for ($i = 0; $i < $dailyCount; $i++) {
    $start = max(0, $i - $windowSize + 1);
    $slice = array_slice($dailyValues, $start, $i - $start + 1);
    $avg = 0.0;
    if (!empty($slice)) {
        $avg = array_sum($slice) / count($slice);
    }
    $baselineValues[] = round($avg, 1);
}
$blockRateTrend = [];
foreach ($dailyValues as $index => $value) {
    $blocks = $dailyBlocks[$index] ?? 0;
    $rate = $value > 0 ? ($blocks / $value) * 100 : 0;
    $blockRateTrend[] = round($rate, 1);
}

$reviewOrder = ['pending', 'allow', 'block', 'investigate'];
$reviewChartLabels = [];
$reviewChartValues = [];
$reviewLabelMap = [
    'pending' => t($translations, $currentLanguage, 'review_pending'),
    'allow' => t($translations, $currentLanguage, 'review_allow'),
    'block' => t($translations, $currentLanguage, 'review_block'),
    'investigate' => t($translations, $currentLanguage, 'review_investigate')
];
foreach ($reviewOrder as $status) {
    if (!isset($chartData['reviews'][$status])) {
        $chartData['reviews'][$status] = 0;
    }
    $reviewChartLabels[] = $reviewLabelMap[$status] ?? $status;
    $reviewChartValues[] = (int) $chartData['reviews'][$status];
}

arsort($chartData['hosts']);
$topHosts = array_slice($chartData['hosts'], 0, 8, true);
$topHostLabels = array_keys($topHosts);
$topHostValues = array_values($topHosts);

$signalChartLabels = [];

$signalChartValues = [];
foreach ($chartData['signals'] as $signal => $count) {
    $signalChartLabels[] = $signalLabels[$signal] ?? $signal;
    $signalChartValues[] = $count;
}

$installChannelLabels = [];
$installChannelValues = [];
foreach ($stats['install_stats'] as $channel => $count) {
    $installChannelLabels[] = labelInstallChannel($channel, $translations, $currentLanguage);
    $installChannelValues[] = $count;
}

$osChartLabels = array_keys($stats['os_stats']);
$osChartValues = array_values($stats['os_stats']);
$browserChartLabels = array_keys($stats['browser_stats']);
$browserChartValues = array_values($stats['browser_stats']);

$chartLabels = [
    'alerts' => t($translations, $currentLanguage, 'total_alerts'),
    'blocks' => t($translations, $currentLanguage, 'total_blocks'),
    'baseline' => t($translations, $currentLanguage, 'alerts_baseline'),
    'block_rate' => t($translations, $currentLanguage, 'block_rate'),
    'reviews' => t($translations, $currentLanguage, 'review_status'),
    'top_hosts' => t($translations, $currentLanguage, 'top_hosts_chart'),
    'signals' => t($translations, $currentLanguage, 'signal_types'),
    'install_channels' => t($translations, $currentLanguage, 'install_channel_chart'),
    'os' => t($translations, $currentLanguage, 'os_breakdown'),
    'browsers' => t($translations, $currentLanguage, 'browser_breakdown')
];

$dashboardVersion = '0.8.0';

$chartPayload = [
    'daily' => [
        'labels' => $dailyLabels,
        'values' => $dailyValues
    ],
    'dailyBlocks' => [
        'labels' => $dailyLabels,
        'values' => $dailyBlocks
    ],
    'baseline' => [
        'labels' => $dailyLabels,
        'values' => $baselineValues
    ],
    'blockRate' => [
        'labels' => $dailyLabels,
        'values' => $blockRateTrend
    ],
    'hourly' => [
        'labels' => range(0, 23),
        'values' => array_values($chartData['hourly'])
    ],
    'hourlyBlocks' => [
        'labels' => range(0, 23),
        'values' => array_values($chartData['hourly_blocks'])
    ],
    'countries' => [
        'labels' => array_keys($chartData['countries']),
        'values' => array_values($chartData['countries'])
    ],
    'reviews' => [
        'labels' => $reviewChartLabels,
        'values' => $reviewChartValues
    ],
    'topHosts' => [
        'labels' => $topHostLabels,
        'values' => $topHostValues
    ],
    'signals' => [
        'labels' => $signalChartLabels,
        'values' => $signalChartValues
    ],
    'installChannels' => [
        'labels' => $installChannelLabels,
        'values' => $installChannelValues
    ],
    'os' => [
        'labels' => $osChartLabels,
        'values' => $osChartValues
    ],
    'browsers' => [
        'labels' => $browserChartLabels,
        'values' => $browserChartValues
    ],
    'labels' => $chartLabels
];

$chartPayloadPublic = $chartPayload;
$chartPayloadPublic['topHosts'] = ['labels' => [], 'values' => []];

$baseLiveStats = [
    'last_update' => (string) ($stats['last_update'] ?? 'N/D'),
    'total_alerts' => (int) $stats['total_alerts'],
    'total_blocks' => (int) $stats['total_blocks'],
    'unique_users' => (int) $stats['unique_users'],
    'unique_hosts' => (int) $stats['unique_hosts'],
    'alerts_24h' => (int) $stats['alerts_24h'],
    'blocks_24h' => (int) $stats['blocks_24h'],
    'countries_count' => (int) count($stats['countries']),
    'total_domains' => (int) $publicTotalDomains,
    'last_30_days' => (int) $publicLast30Days,
    'block_rate' => round($blockRate, 1)
];

$liveStatsPublic = $baseLiveStats;
$liveStatsPrivate = $baseLiveStats + [
    'manual_sites_count' => (int) count($stats['manual_sites']),
    'recent_count' => (int) $stats['recent_count'],
    'alert_sites_count' => (int) count($stats['alert_sites'])
];

$livePayloadPublic = [
    'access' => 'public',
    'stats' => $liveStatsPublic,
    'charts' => $chartPayloadPublic,
    'recent_domains' => $recentDomains
];
$livePayloadPrivate = [
    'access' => 'private',
    'stats' => $liveStatsPrivate,
    'charts' => $chartPayload,
    'recent_domains' => $recentDomains
];

if (isset($_GET['format']) && $_GET['format'] === 'live') {
    header('Content-Type: application/json; charset=utf-8');
    $forcePublic = $publicAnalyticsEnabled && isset($_GET['public']);
    $payload = ($forcePublic || $isPublicView) ? $livePayloadPublic : $livePayloadPrivate;
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if ($publicAnalyticsEnabled && isset($_GET['public']) && ($_GET['format'] ?? '') === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($chartPayloadPublic, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}
?>
<!doctype html>
<html lang="es">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?= htmlspecialchars(t($translations, $currentLanguage, 'app_title'), ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="icon" type="image/x-icon" href="favicon.ico" />
    <script>
      (() => {
        const storedTheme = localStorage.getItem("cf_theme");
        if (storedTheme === "dark" || storedTheme === "light") {
          document.documentElement.dataset.theme = storedTheme;
        }
      })();
    </script>
    <style>
      @import url('https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700&family=Sora:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap');
      :root {
        color-scheme: light;
        --bg: #f7f3ee;
        --bg-accent-1: rgba(14, 116, 144, 0.16);
        --bg-accent-2: rgba(245, 158, 11, 0.16);
        --orb-primary: rgba(14, 116, 144, 0.35);
        --orb-accent: rgba(245, 158, 11, 0.25);
        --surface: #ffffff;
        --surface-strong: #f4efe8;
        --surface-alt: #f8f1e9;
        --surface-soft: #fffbf5;
        --surface-muted: #f1e9df;
        --surface-glass: rgba(255, 255, 255, 0.92);
        --border: #e4dbcf;
        --outline: #d5c7b6;
        --text: #1f2933;
        --muted: #6c6a64;
        --primary: #0f766e;
        --primary-soft: rgba(15, 118, 110, 0.18);
        --accent: #f59e0b;
        --success: #15803d;
        --danger: #b91c1c;
        --warning: #f59e0b;
        --shadow: 0 28px 60px -42px rgba(15, 118, 110, 0.35);
        --glow: 0 0 32px rgba(245, 158, 11, 0.22);
        --hero-gradient: linear-gradient(140deg, rgba(255, 255, 255, 0.98), rgba(247, 243, 238, 0.92));
        --hero-action-gradient: linear-gradient(135deg, rgba(15, 118, 110, 0.14), rgba(255, 255, 255, 0.92));
        --hero-action-border: rgba(15, 118, 110, 0.25);
        --stat-gradient: linear-gradient(145deg, rgba(255, 255, 255, 0.98), rgba(244, 239, 232, 0.9));
        --stat-orb: rgba(15, 118, 110, 0.22);
      }
      :root[data-theme="dark"] {
        color-scheme: dark;
        --bg: #0d1117;
        --bg-accent-1: rgba(20, 184, 166, 0.2);
        --bg-accent-2: rgba(245, 158, 11, 0.2);
        --orb-primary: rgba(45, 212, 191, 0.3);
        --orb-accent: rgba(251, 191, 36, 0.28);
        --surface: #111827;
        --surface-strong: #162033;
        --surface-alt: #0f172a;
        --surface-soft: #111d34;
        --surface-muted: #1a2438;
        --surface-glass: rgba(15, 23, 42, 0.9);
        --border: #22304a;
        --outline: #2c3b57;
        --text: #e2e8f0;
        --muted: #94a3b8;
        --primary: #5eead4;
        --primary-soft: rgba(94, 234, 212, 0.2);
        --accent: #fbbf24;
        --success: #4ade80;
        --danger: #f87171;
        --warning: #fbbf24;
        --shadow: 0 30px 60px -40px rgba(2, 6, 23, 0.85);
        --glow: 0 0 30px rgba(94, 234, 212, 0.3);
        --hero-gradient: linear-gradient(145deg, rgba(17, 24, 39, 0.95), rgba(12, 18, 32, 0.98));
        --hero-action-gradient: linear-gradient(135deg, rgba(94, 234, 212, 0.16), rgba(15, 23, 42, 0.86));
        --hero-action-border: rgba(94, 234, 212, 0.32);
        --stat-gradient: linear-gradient(145deg, rgba(17, 24, 39, 0.94), rgba(12, 18, 32, 0.98));
        --stat-orb: rgba(94, 234, 212, 0.25);
      }
      :root[data-theme="light"] {
        color-scheme: light;
      }
      @media (prefers-color-scheme: dark) {
        :root:not([data-theme]) {
          color-scheme: dark;
          --bg: #0d1117;
          --bg-accent-1: rgba(20, 184, 166, 0.2);
          --bg-accent-2: rgba(245, 158, 11, 0.2);
          --orb-primary: rgba(45, 212, 191, 0.3);
          --orb-accent: rgba(251, 191, 36, 0.28);
          --surface: #111827;
          --surface-strong: #162033;
          --surface-alt: #0f172a;
          --surface-soft: #111d34;
          --surface-muted: #1a2438;
          --surface-glass: rgba(15, 23, 42, 0.9);
          --border: #22304a;
          --outline: #2c3b57;
          --text: #e2e8f0;
          --muted: #94a3b8;
          --primary: #5eead4;
          --primary-soft: rgba(94, 234, 212, 0.2);
          --accent: #fbbf24;
          --success: #4ade80;
          --danger: #f87171;
          --warning: #fbbf24;
          --shadow: 0 30px 60px -40px rgba(2, 6, 23, 0.85);
          --glow: 0 0 30px rgba(94, 234, 212, 0.3);
          --hero-gradient: linear-gradient(145deg, rgba(17, 24, 39, 0.95), rgba(12, 18, 32, 0.98));
          --hero-action-gradient: linear-gradient(135deg, rgba(94, 234, 212, 0.16), rgba(15, 23, 42, 0.86));
          --hero-action-border: rgba(94, 234, 212, 0.32);
          --stat-gradient: linear-gradient(145deg, rgba(17, 24, 39, 0.94), rgba(12, 18, 32, 0.98));
          --stat-orb: rgba(94, 234, 212, 0.25);
        }
      }
      * {
        box-sizing: border-box;
      }
      :focus-visible {
        outline: 2px solid var(--primary);
        outline-offset: 3px;
      }
      .skip-link {
        position: absolute;
        left: 16px;
        top: -48px;
        padding: 10px 16px;
        border-radius: 999px;
        background: var(--primary);
        color: #04110c;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        font-size: 11px;
        z-index: 999;
        transition: top 0.2s ease;
      }
      .skip-link:focus {
        top: 16px;
      }
      body {
        font-family: "Manrope", "Segoe UI", sans-serif;
        margin: 0;
        background: radial-gradient(circle at top left, var(--bg-accent-1), transparent 55%),
          radial-gradient(circle at 20% 30%, var(--bg-accent-2), transparent 60%),
          linear-gradient(120deg, rgba(255, 255, 255, 0.4), transparent 55%),
          var(--bg);
        color: var(--text);
        min-height: 100vh;
        overflow-x: hidden;
      }
      body[data-dashboard-access="public"] {
        color-scheme: dark;
        --bg: #0b0f14;
        --bg-accent-1: rgba(34, 197, 94, 0.18);
        --bg-accent-2: rgba(20, 184, 166, 0.16);
        --orb-primary: rgba(34, 197, 94, 0.28);
        --orb-accent: rgba(20, 184, 166, 0.25);
        --surface: #0f141b;
        --surface-strong: #0e1724;
        --surface-alt: #0b1118;
        --surface-soft: #111b27;
        --surface-muted: #111a25;
        --surface-glass: rgba(8, 12, 18, 0.92);
        --border: #1c2838;
        --outline: #223346;
        --text: #d8f3e8;
        --muted: #8aa0a4;
        --primary: #20f2a5;
        --primary-soft: rgba(32, 242, 165, 0.18);
        --accent: #22c55e;
        --shadow: 0 30px 70px -40px rgba(0, 0, 0, 0.75);
        --glow: 0 0 40px rgba(32, 242, 165, 0.18);
        --hero-gradient: linear-gradient(145deg, rgba(10, 15, 22, 0.96), rgba(11, 18, 28, 0.98));
        --hero-action-gradient: linear-gradient(135deg, rgba(32, 242, 165, 0.12), rgba(10, 16, 24, 0.88));
        --hero-action-border: rgba(32, 242, 165, 0.28);
        --stat-gradient: linear-gradient(145deg, rgba(10, 15, 22, 0.96), rgba(11, 18, 28, 0.98));
        --stat-orb: rgba(32, 242, 165, 0.18);
      }
      body[data-dashboard-access="public"] .top-bar {
        position: sticky;
        top: 12px;
        z-index: 20;
        border-radius: 999px;
        padding: 14px 18px;
        background: rgba(7, 12, 18, 0.92);
        border: 1px solid rgba(32, 242, 165, 0.2);
      }
      body[data-dashboard-access="public"] .brand h1 {
        font-size: 26px;
        letter-spacing: 0.02em;
      }
      body[data-dashboard-access="public"] .badge,
      body[data-dashboard-access="public"] .version-pill {
        background: rgba(16, 185, 129, 0.18);
        color: #b6f7dc;
        border: 1px solid rgba(16, 185, 129, 0.35);
      }
      body[data-dashboard-access="public"] .sponsor-bar {
        background: rgba(16, 185, 129, 0.12);
        border-color: rgba(16, 185, 129, 0.3);
      }
      body[data-dashboard-access="public"] .table-wrap {
        background: rgba(10, 16, 24, 0.6);
      }
      body[data-dashboard-access="public"] th {
        background: rgba(16, 185, 129, 0.12);
        color: #b6f7dc;
      }
      body[data-dashboard-access="public"] .public-hero {
        border-color: rgba(32, 242, 165, 0.35);
        box-shadow: 0 40px 90px -60px rgba(32, 242, 165, 0.6);
      }
      body[data-dashboard-access="public"] .public-hero::before {
        background: radial-gradient(circle, rgba(32, 242, 165, 0.25), transparent 70%);
      }
      body[data-dashboard-access="public"] .public-hero::after {
        background: radial-gradient(circle, rgba(14, 165, 233, 0.2), transparent 70%);
      }
      body[data-dashboard-access="public"] .public-access {
        border-color: rgba(32, 242, 165, 0.35);
        background: linear-gradient(140deg, rgba(6, 12, 18, 0.96), rgba(12, 20, 28, 0.92));
      }
      body[data-dashboard-access="public"] .button-primary {
        background: var(--primary);
        color: #03130d;
        border-color: transparent;
        box-shadow: 0 0 20px rgba(32, 242, 165, 0.35);
      }
      body[data-dashboard-access="public"] .button-secondary {
        border-color: rgba(32, 242, 165, 0.35);
        color: var(--text);
      }
      body[data-dashboard-access="public"] .button-ghost {
        border-color: rgba(32, 242, 165, 0.25);
        color: var(--text);
      }
      body::before,
      body::after {
        content: "";
        position: fixed;
        inset: auto;
        z-index: -1;
        opacity: 0.55;
        pointer-events: none;
      }
      body::before {
        top: -120px;
        right: -120px;
        width: 280px;
        height: 280px;
        border-radius: 50%;
        background: radial-gradient(circle, var(--orb-primary), transparent 70%);
        animation: floatOrb 22s ease-in-out infinite;
      }
      body::after {
        bottom: -140px;
        left: -120px;
        width: 320px;
        height: 320px;
        border-radius: 50%;
        background: radial-gradient(circle, var(--orb-accent), transparent 70%);
        animation: floatOrb 26s ease-in-out infinite reverse;
      }
      @media (prefers-reduced-motion: reduce) {
        *,
        *::before,
        *::after {
          animation-duration: 0.01ms !important;
          animation-iteration-count: 1 !important;
          transition-duration: 0.01ms !important;
          scroll-behavior: auto !important;
        }
      }
      h1,
      h2,
      h3,
      summary {
        font-family: "Sora", "Segoe UI", sans-serif;
        letter-spacing: -0.02em;
      }
      a {
        color: var(--primary);
        text-decoration: none;
      }
      a:hover {
        text-decoration: underline;
      }
      code {
        font-family: "JetBrains Mono", "Consolas", monospace;
        font-size: 12px;
        background: var(--surface-muted);
        border: 1px solid var(--border);
        border-radius: 8px;
        padding: 2px 6px;
        color: var(--text);
      }
      .page {
        max-width: min(1680px, 96vw);
        width: 100%;
        margin: 0 auto;
        padding: clamp(20px, 3vw, 40px) clamp(18px, 3vw, 48px) 80px;
        min-height: 100vh;
      }
      .main-content {
        display: flex;
        flex-direction: column;
        gap: 20px;
      }
      section {
        scroll-margin-top: 120px;
      }
      .top-bar {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
        padding: 20px 24px;
        border-radius: 26px;
        background: var(--surface-glass);
        border: 1px solid var(--outline);
        box-shadow: var(--shadow);
        backdrop-filter: blur(14px);
        margin-bottom: 20px;
      }
      .section-nav {
        position: sticky;
        top: 16px;
        z-index: 15;
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        padding: 12px 16px;
        border-radius: 18px;
        background: var(--surface-glass);
        border: 1px solid var(--outline);
        box-shadow: var(--shadow);
        backdrop-filter: blur(14px);
        margin-bottom: 24px;
      }
      @media (min-width: 1200px) {
        .section-nav,
        .top-bar {
          padding-left: 24px;
          padding-right: 24px;
        }
      }
      .public-nav {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 10px;
        padding: 6px 8px;
        border-radius: 999px;
        background: var(--surface-soft);
        border: 1px solid var(--border);
        box-shadow: var(--shadow);
      }
      .public-nav a {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 12px;
        border-radius: 999px;
        border: 1px solid transparent;
        color: var(--text);
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.12em;
        font-weight: 600;
      }
      .public-nav a:hover {
        text-decoration: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 1px var(--primary-soft);
      }
      .public-nav a.is-active {
        border-color: var(--primary);
        background: var(--surface-muted);
        box-shadow: 0 0 0 1px var(--primary-soft);
      }
      .public-nav a.nav-cta {
        background: var(--primary);
        color: #03130d;
        border-color: transparent;
        box-shadow: 0 0 18px rgba(32, 242, 165, 0.35);
      }
      .sponsor-bar {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        padding: 10px 14px;
        border-radius: 12px;
        background: rgba(14, 116, 144, 0.18);
        border: 1px solid rgba(14, 116, 144, 0.35);
        color: var(--text);
        font-size: 12px;
        margin: 10px 0 18px;
      }
      .sponsor-bar .badge {
        padding: 4px 8px;
        border-radius: 999px;
        background: var(--primary-soft);
        border: 1px solid var(--border);
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.12em;
      }
      .section-nav a {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 12px;
        border-radius: 999px;
        border: 1px solid var(--border);
        background: var(--surface-soft);
        color: var(--text);
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.1em;
      }
      .section-nav a:hover {
        text-decoration: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 1px var(--primary-soft);
      }
      .public-totals {
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      }
      .search-section .search-bar {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        align-items: center;
        margin: 10px 0 16px;
      }
      .search-section .search-bar input {
        flex: 1;
        min-width: 220px;
      }
      .search-filters {
        border: 1px solid var(--border);
        border-radius: 14px;
        padding: 12px;
        background: var(--surface-soft);
        margin-bottom: 12px;
      }
      .search-filters summary {
        cursor: pointer;
        font-weight: 600;
      }
      .search-filters .filter-grid {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        align-items: center;
        margin-top: 12px;
      }
      .search-filters label {
        display: flex;
        flex-direction: column;
        gap: 6px;
        font-size: 12px;
        color: var(--muted);
      }
      .scan-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 16px;
      }
      .scan-card {
        border: 1px solid var(--outline);
        border-radius: 16px;
        padding: 14px;
        background: var(--surface-soft);
      }
      .scan-label {
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.12em;
        color: var(--muted);
        margin-bottom: 10px;
      }
      .scan-placeholder {
        height: 180px;
        border-radius: 12px;
        background: linear-gradient(135deg, var(--surface-muted), var(--surface));
        display: grid;
        place-items: center;
        color: var(--muted);
        font-size: 12px;
      }
      .data-summary .stat-card {
        min-height: 110px;
      }
      .data-table-stack {
        display: grid;
        gap: 16px;
      }
      .data-table-card {
        border: 1px solid var(--border);
        border-radius: 20px;
        padding: 16px;
        background: var(--surface-soft);
      }
      .data-table-card > summary {
        list-style: none;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        cursor: pointer;
      }
      .data-table-card > summary::-webkit-details-marker {
        display: none;
      }
      .data-table-actions {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        margin-bottom: 12px;
      }
      .cell-code {
        display: inline-block;
        max-width: 360px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        vertical-align: top;
      }
      .cell-expand summary {
        cursor: pointer;
        list-style: none;
      }
      .cell-expand summary::-webkit-details-marker {
        display: none;
      }
      .cell-expand pre {
        margin: 8px 0 0;
        padding: 10px;
        border-radius: 10px;
        background: var(--surface-muted);
        border: 1px solid var(--border);
        white-space: pre-wrap;
        word-break: break-word;
        font-size: 12px;
      }
      .new-domains .table-wrap {
        max-height: 260px;
      }
      .new-domains code {
        display: block;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
      }
      .about-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 16px;
        margin-top: 16px;
      }
      .meta-row {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 10px;
      }
      .brand {
        display: flex;
        flex-direction: column;
        gap: 8px;
      }
      .brand-title {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 12px;
      }
      .brand h1 {
        margin: 0;
        font-size: 32px;
      }
      .muted {
        color: var(--muted);
        font-size: 13px;
      }
      .status-pill {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 6px 12px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 600;
        background: var(--surface-strong);
        color: var(--text);
        border: 1px solid var(--border);
      }
      .version-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 10px;
        border-radius: 999px;
        border: 1px solid var(--outline);
        background: var(--primary-soft);
        color: var(--primary);
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.08em;
      }
      .status-pill.enabled {
        background: rgba(47, 158, 68, 0.15);
        color: #1e6430;
        border-color: rgba(47, 158, 68, 0.25);
      }
      .status-pill.disabled,
      .status-pill.pending {
        background: rgba(201, 42, 42, 0.12);
        color: #8d1d1d;
        border-color: rgba(201, 42, 42, 0.25);
      }
      .status-pill.approved {
        background: rgba(47, 158, 68, 0.15);
        color: #1e6430;
        border-color: rgba(47, 158, 68, 0.25);
      }
      .status-pill.rejected {
        background: rgba(109, 106, 99, 0.15);
        color: #4b4740;
        border-color: rgba(109, 106, 99, 0.25);
      }
      .status-pill.review-allow {
        background: rgba(47, 158, 68, 0.15);
        color: #1e6430;
        border-color: rgba(47, 158, 68, 0.25);
      }
      .status-pill.review-block {
        background: rgba(201, 42, 42, 0.12);
        color: #8d1d1d;
        border-color: rgba(201, 42, 42, 0.25);
      }
      .status-pill.review-investigate {
        background: rgba(245, 159, 0, 0.16);
        color: #8a5b00;
        border-color: rgba(245, 159, 0, 0.25);
      }
      .grid {
        display: grid;
        gap: 16px;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      }
      .card {
        background: linear-gradient(160deg, var(--surface), var(--surface-soft));
        border: 1px solid var(--border);
        border-radius: 24px;
        padding: 20px;
        box-shadow: var(--shadow);
        max-width: 100%;
        position: relative;
        overflow: hidden;
        animation: fadeUp 0.6s ease both;
      }
      .card:nth-child(2n) {
        animation-delay: 0.04s;
      }
      .accordion-card {
        padding: 0;
        overflow: hidden;
      }
      .accordion-header {
        width: 100%;
        border: none;
        background: transparent;
        padding: 18px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        color: var(--text);
        cursor: pointer;
        text-align: left;
        font: inherit;
      }
      .accordion-header .section-title {
        margin: 0;
        flex: 1;
      }
      .accordion-header h2,
      .accordion-header h3 {
        margin: 0;
      }
      .accordion-chevron {
        font-size: 18px;
        opacity: 0.7;
        transition: transform 0.2s ease;
      }
      .accordion-content {
        padding: 0 18px 18px;
      }
      .accordion-collapsed .accordion-content {
        display: none;
      }
      .accordion-collapsed .accordion-chevron {
        transform: rotate(-90deg);
      }
      .accordion-title-suppressed {
        display: none;
      }
      .table-filter {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 12px;
      }
      .table-filter label {
        display: flex;
        align-items: center;
        gap: 8px;
        width: 100%;
      }
      .table-filter input {
        flex: 1;
        padding: 8px 10px;
        border-radius: 10px;
        border: 1px solid var(--border);
        background: var(--surface-soft);
        color: var(--text);
      }
      .table-empty-row td {
        text-align: center;
        color: var(--muted);
        padding: 16px;
      }
      .toast-container {
        position: fixed;
        top: 18px;
        right: 18px;
        display: flex;
        flex-direction: column;
        gap: 10px;
        z-index: 9999;
      }
      .toast {
        min-width: 220px;
        max-width: 360px;
        padding: 12px 14px;
        border-radius: 12px;
        border: 1px solid var(--border);
        background: var(--surface-glass);
        box-shadow: var(--shadow);
        color: var(--text);
        font-size: 14px;
        display: flex;
        align-items: flex-start;
        gap: 8px;
      }
      .toast.success {
        border-color: rgba(47, 158, 68, 0.6);
      }
      .toast.error {
        border-color: rgba(201, 42, 42, 0.6);
      }
      .hero {
        display: grid;
        gap: 16px;
        grid-template-columns: minmax(0, 2fr) minmax(240px, 1fr);
        align-items: stretch;
        background: var(--hero-gradient);
        border: 1px solid var(--outline);
      }
      .hero-kicker {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 6px 12px;
        border-radius: 999px;
        border: 1px solid var(--hero-action-border);
        background: var(--surface-muted);
        color: var(--primary);
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.12em;
        width: fit-content;
      }
      .hero-summary h2 {
        margin: 0 0 6px;
        font-size: 28px;
      }
      .hero-summary p {
        margin: 0;
        color: var(--muted);
        font-size: 15px;
      }
      .hero-metrics {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
        gap: 12px;
        margin-top: 16px;
      }
      .mini-stat {
        padding: 12px;
        border-radius: 14px;
        border: 1px solid var(--border);
        background: var(--surface-soft);
        display: grid;
        gap: 6px;
      }
      .mini-stat span {
        font-size: 11px;
        color: var(--muted);
        text-transform: uppercase;
        letter-spacing: 0.08em;
      }
      .mini-stat strong {
        font-size: 18px;
      }
      .hero-actions {
        display: grid;
        gap: 12px;
      }
      .hero-cta {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-top: 16px;
      }
      .hero-action {
        padding: 14px;
        border-radius: 14px;
        background: var(--hero-action-gradient);
        border: 1px solid var(--hero-action-border);
        box-shadow: var(--glow);
      }
      .public-hero {
        position: relative;
        overflow: hidden;
        border: 1px solid rgba(245, 158, 11, 0.35);
        box-shadow: 0 40px 90px -60px rgba(245, 158, 11, 0.7);
        padding: 26px;
      }
      .public-hero::before {
        content: "";
        position: absolute;
        inset: -40% -10% auto auto;
        width: 320px;
        height: 320px;
        background: radial-gradient(circle, rgba(245, 158, 11, 0.25), transparent 70%);
        opacity: 0.8;
      }
      .public-hero::after {
        content: "";
        position: absolute;
        inset: auto auto -50% -10%;
        width: 360px;
        height: 360px;
        background: radial-gradient(circle, rgba(15, 118, 110, 0.2), transparent 70%);
        opacity: 0.7;
      }
      .public-hero .hero-actions {
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
      }
      .public-hero .hero-action {
        backdrop-filter: blur(14px);
      }
      .public-analytics {
        border: 1px solid var(--outline);
        background: linear-gradient(180deg, var(--surface), var(--surface-muted));
        box-shadow: 0 30px 60px -50px rgba(15, 118, 110, 0.35);
      }
      .public-access {
        border: 1px dashed rgba(245, 158, 11, 0.45);
        background: linear-gradient(140deg, rgba(255, 255, 255, 0.96), rgba(248, 242, 232, 0.92));
        box-shadow: 0 26px 60px -50px rgba(245, 158, 11, 0.5);
      }
      .public-access .form-actions {
        justify-content: flex-start;
      }
      .stat-card {
        display: flex;
        flex-direction: column;
        gap: 10px;
        padding: 18px;
        border-radius: 18px;
        background: var(--stat-gradient);
        border: 1px solid var(--border);
        box-shadow: var(--shadow);
        position: relative;
        overflow: hidden;
      }
      .stat-card::after {
        content: "";
        position: absolute;
        inset: -40% 60% auto auto;
        width: 120px;
        height: 120px;
        background: radial-gradient(circle, var(--stat-orb), transparent 70%);
        opacity: 0.55;
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
        color: var(--muted);
      }
      .layout {
        display: grid;
        gap: 22px;
        grid-template-columns: minmax(0, 2.6fr) minmax(320px, 1fr);
        margin-top: 20px;
      }
      .section-title {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 12px;
        position: relative;
        padding-left: 12px;
      }
      .section-title::before {
        content: "";
        position: absolute;
        left: 0;
        top: 6px;
        width: 6px;
        height: 24px;
        border-radius: 999px;
        background: var(--accent);
        box-shadow: 0 0 12px rgba(245, 158, 11, 0.4);
      }
      .section-title h2,
      .section-title h3 {
        margin: 0;
        font-size: 20px;
      }
      .chart-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 16px;
      }
      .chart-card {
        background: linear-gradient(180deg, var(--surface), var(--surface-soft));
        border: 1px solid var(--outline);
        border-radius: 18px;
        padding: 14px;
        max-height: 180pt;
        box-shadow: 0 18px 36px -28px rgba(15, 23, 42, 0.25);
      }
      .chart-card h3 {
        margin: 0 0 10px;
        font-size: 14px;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: var(--muted);
      }
      .table-wrap {
        width: 100%;
        overflow: auto;
        max-height: 320px;
        border-radius: 14px;
        border: 1px solid var(--border);
        background: var(--surface-soft);
      }
      @media (min-width: 1200px) {
        .grid {
          grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        }
        .chart-grid {
          grid-template-columns: repeat(3, minmax(0, 1fr));
        }
        .table-wrap {
          max-height: 380px;
        }
      }
      table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
      }
      th,
      td {
        padding: 10px 12px;
        text-align: left;
        border-bottom: 1px solid var(--border);
      }
      th {
        background: rgba(37, 99, 235, 0.08);
        color: #1f4f5c;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        font-size: 11px;
      }
      tbody tr:hover,
      tbody tr:focus-within {
        background: var(--surface-alt);
      }
      .badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 10px;
        border-radius: 999px;
        background: rgba(37, 99, 235, 0.12);
        color: #1f4f5c;
        font-size: 12px;
        font-weight: 600;
      }
      .badge.badge-accepted {
        background: rgba(34, 197, 94, 0.16);
        color: #166534;
        border: 1px solid rgba(34, 197, 94, 0.3);
      }
      .badge.badge-pending {
        background: rgba(14, 165, 233, 0.16);
        color: #1d4ed8;
        border: 1px solid rgba(14, 165, 233, 0.3);
      }
      .chip-list {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
      }
      .chip {
        padding: 6px 10px;
        border-radius: 999px;
        border: 1px solid var(--border);
        background: var(--surface-soft);
        font-size: 12px;
      }
      .list-block {
        padding: 10px;
        background: var(--surface-soft);
        border-radius: 12px;
        border: 1px solid var(--border);
      }
      .list-block ul {
        margin: 0;
        padding-left: 18px;
      }
      .list-domains {
        max-height: 240px;
        overflow: auto;
      }
      .list-domains ul {
        margin: 0;
        padding: 0;
        list-style: none;
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
      }
      .list-domains li {
        margin: 0;
      }
      .list-item {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 12px;
        border-radius: 999px;
        border: 1px solid var(--border);
        background: var(--surface-soft);
        font-size: 12px;
        color: var(--text);
        cursor: pointer;
        transition: border-color 0.2s ease, transform 0.2s ease, box-shadow 0.2s ease;
      }
      .list-item:hover {
        border-color: rgba(37, 99, 235, 0.45);
        box-shadow: 0 10px 20px -18px rgba(37, 99, 235, 0.45);
        transform: translateY(-1px);
      }
      .list-item.selected {
        background: rgba(37, 99, 235, 0.12);
        border-color: rgba(37, 99, 235, 0.5);
      }
      .chip-button {
        cursor: pointer;
        border: 1px solid var(--border);
        background: var(--surface-soft);
      }
      .chip-button:hover {
        border-color: rgba(37, 99, 235, 0.45);
      }
      .report-card {
        border: 1px solid var(--border);
        border-radius: 14px;
        padding: 12px;
        margin-bottom: 12px;
        background: var(--surface-soft);
      }
      .report-card summary {
        font-weight: 600;
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        align-items: center;
        cursor: pointer;
      }
      details.no-toggle > summary {
        cursor: default;
        list-style: none;
      }
      details.no-toggle > summary::marker {
        content: "";
      }
      details.no-toggle > summary::-webkit-details-marker {
        display: none;
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
        border: 1px dashed rgba(37, 99, 235, 0.35);
        border-radius: 12px;
        padding: 10px;
        background: rgba(37, 99, 235, 0.06);
      }
      .context-panel pre {
        background: var(--surface-muted);
      }
      .report-card pre {
        max-height: 220px;
        overflow: auto;
      }
      .log-grid {
        display: grid;
        gap: 16px;
        grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
      }
      .log-entry {
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 12px;
        background: var(--surface-soft);
      }
      .log-entry pre {
        margin: 0;
        font-size: 12px;
      }
      .form-grid {
        display: grid;
        gap: 12px;
      }
      select {
        padding: 8px 10px;
        border-radius: 10px;
        border: 1px solid var(--border);
        background: var(--surface-soft);
        color: var(--text);
        font-family: inherit;
        transition: border-color 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
      }
      .form-grid input,
      .form-grid textarea,
      .form-grid select {
        width: 100%;
        padding: 10px 12px;
        border-radius: 12px;
        border: 1px solid var(--border);
        background: var(--surface-soft);
        color: var(--text);
        font-family: inherit;
        transition: border-color 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
      }
      .form-grid input::placeholder,
      .form-grid textarea::placeholder {
        color: var(--muted);
      }
      .form-grid input:focus,
      .form-grid textarea:focus,
      .form-grid select:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px var(--primary-soft);
        background: var(--surface);
      }
      .form-grid input:disabled,
      .form-grid textarea:disabled,
      .form-grid select:disabled {
        opacity: 0.6;
        cursor: not-allowed;
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
        background: linear-gradient(135deg, #2563eb, #3b82f6);
        color: #fffaf4;
        border: none;
        border-radius: 12px;
        padding: 10px 16px;
        cursor: pointer;
        font-weight: 600;
        box-shadow: var(--glow);
        transition: transform 0.15s ease, box-shadow 0.15s ease;
      }
      .button-primary:hover {
        transform: translateY(-1px);
        box-shadow: 0 10px 24px -12px rgba(37, 99, 235, 0.5);
      }
      .button-secondary {
        background: rgba(37, 99, 235, 0.1);
        color: #1f4f5c;
        border: 1px solid rgba(37, 99, 235, 0.25);
        border-radius: 12px;
        padding: 10px 14px;
        cursor: pointer;
        font-weight: 600;
      }
      .button-ghost {
        background: transparent;
        color: var(--primary);
        border: 1px solid rgba(37, 99, 235, 0.4);
        border-radius: 12px;
        padding: 8px 12px;
        font-weight: 600;
      }
      button:disabled,
      .button-primary:disabled,
      .button-secondary:disabled,
      .button-ghost:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        box-shadow: none;
        transform: none;
      }
      .alert-box {
        padding: 10px 12px;
        border-radius: 12px;
        margin-bottom: 12px;
        font-size: 14px;
      }
      .alert-box.error {
        background: rgba(201, 42, 42, 0.12);
        color: #7c1b1b;
        border: 1px solid rgba(201, 42, 42, 0.3);
      }
      .alert-box.notice {
        background: rgba(47, 158, 68, 0.12);
        color: #1e6430;
        border: 1px solid rgba(47, 158, 68, 0.3);
      }
      .button-approve {
        background: #2f9e44;
        color: #fff;
        border: none;
      }
      .button-reject {
        background: #c92a2a;
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
        background: rgba(37, 99, 235, 0.12);
        color: #1f4f5c;
        border: 1px solid rgba(37, 99, 235, 0.25);
      }
      .role-badge.admin {
        background: rgba(224, 122, 95, 0.2);
        color: #7a3c2d;
        border-color: rgba(224, 122, 95, 0.35);
      }
      .role-badge.analyst {
        background: rgba(37, 99, 235, 0.15);
        color: #1f4f5c;
      }
      .admin-panel {
        border: 1px dashed rgba(37, 99, 235, 0.5);
        background: rgba(37, 99, 235, 0.05);
      }
      .highlight {
        background: rgba(37, 99, 235, 0.08);
        border: 1px solid rgba(37, 99, 235, 0.2);
        padding: 12px;
        border-radius: 12px;
      }
      .info-list {
        list-style: none;
        margin: 0;
        padding: 0;
        display: grid;
        gap: 8px;
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
        max-height: 85vh;
        overflow: auto;
      }
      .workspace-tabs {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        padding: 10px 14px;
        border-radius: 999px;
        background: linear-gradient(120deg, var(--surface-glass), var(--surface-soft));
        border: 1px solid var(--outline);
        box-shadow: var(--shadow);
      }
      .workspace-tab {
        appearance: none;
        border: 1px solid transparent;
        background: transparent;
        padding: 10px 18px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 700;
        cursor: pointer;
        color: var(--muted);
        text-transform: uppercase;
        letter-spacing: 0.08em;
        transition: border-color 0.2s ease, transform 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
      }
      .workspace-tab:hover {
        border-color: var(--primary);
        box-shadow: 0 12px 24px -20px rgba(15, 118, 110, 0.45);
        transform: translateY(-1px);
        color: var(--primary);
      }
      .workspace-tab.is-active {
        background: var(--primary);
        border-color: var(--primary);
        color: #fff;
        box-shadow: var(--glow);
      }
      .workspace-section + .workspace-section {
        margin-top: 28px;
        padding-top: 22px;
        border-top: 1px solid var(--border);
      }
      .workspace-right {
        position: sticky;
        top: 18px;
        align-self: start;
      }
      .action-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
        gap: 12px;
      }
      .action-block {
        display: grid;
        gap: 6px;
        padding: 10px 12px;
        border-radius: 14px;
        border: 1px dashed rgba(37, 99, 235, 0.35);
        background: rgba(37, 99, 235, 0.06);
      }
      .action-block sub {
        color: var(--muted);
        font-size: 11px;
        line-height: 1.3;
      }
      .bulk-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
      }
      .selection-meta {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 10px;
        font-size: 12px;
        color: var(--muted);
      }
      .investigation-panel {
        position: sticky;
        top: 16px;
        max-height: 72vh;
        overflow: auto;
      }
      .investigation-details {
        margin-top: 12px;
        display: grid;
        gap: 10px;
      }
      .investigation-empty {
        color: var(--muted);
        font-size: 13px;
      }
      .access-hero {
        display: grid;
        gap: 18px;
        grid-template-columns: minmax(0, 1.4fr) minmax(0, 1fr);
      }
      .access-message {
        padding: 22px;
        border-radius: 24px;
        background: linear-gradient(140deg, rgba(255, 255, 255, 0.95), rgba(243, 238, 230, 0.92));
        border: 1px solid var(--border);
        box-shadow: 0 30px 60px -45px rgba(15, 118, 110, 0.35);
        position: relative;
        overflow: hidden;
      }
      .access-message::after {
        content: "";
        position: absolute;
        inset: -40% -20% auto auto;
        width: 220px;
        height: 220px;
        background: radial-gradient(circle, rgba(15, 118, 110, 0.2), transparent 70%);
        opacity: 0.8;
      }
      .access-kicker {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 10px;
        border-radius: 999px;
        background: rgba(224, 122, 95, 0.18);
        color: #7a3c2d;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        font-weight: 700;
      }
      .access-note {
        margin-top: 14px;
        padding: 12px 14px;
        border-radius: 14px;
        background: rgba(245, 158, 11, 0.12);
        border: 1px solid rgba(245, 158, 11, 0.25);
      }
      .access-grid {
        display: grid;
        gap: 16px;
      }
      .access-card {
        background: var(--surface-soft);
        border: 1px solid var(--outline);
        box-shadow: var(--shadow);
      }
      .access-card .section-title h3 {
        font-size: 18px;
      }
      #access-section details,
      .access-card details {
        border: 1px solid var(--border);
        border-radius: 16px;
        padding: 12px;
        background: var(--surface-soft);
      }
      #access-section details + details,
      .access-card details + details {
        margin-top: 12px;
      }
      #access-section summary.section-title,
      .access-card summary.section-title {
        cursor: pointer;
      }
      #access-section details[open],
      .access-card details[open] {
        box-shadow: var(--shadow);
      }
      @keyframes fadeUp {
        from {
          opacity: 0;
          transform: translateY(12px);
        }
        to {
          opacity: 1;
          transform: translateY(0);
        }
      }
      @keyframes floatOrb {
        0%,
        100% {
          transform: translate(0, 0) scale(1);
        }
        50% {
          transform: translate(18px, -14px) scale(1.05);
        }
      }
      @media (max-width: 980px) {
        .layout,
        .hero,
        .access-hero {
          grid-template-columns: 1fr;
        }
        .panel-stack {
          max-height: none;
        }
        .workspace-right {
          position: static;
        }
        .investigation-panel {
          position: static;
          max-height: none;
        }
      }
      @media (max-width: 760px) {
        .section-title {
          flex-direction: column;
          align-items: flex-start;
        }
        .top-bar {
          align-items: flex-start;
        }
        .card {
          padding: 16px;
        }
      }
      @media (max-width: 640px) {
        .page {
          padding: 24px 16px 40px;
        }
        .stat-value {
          font-size: 24px;
        }
        table {
          font-size: 11px;
        }
      }
    </style>
  </head>
  <body data-dashboard-access="<?= $isPublicView ? 'public' : 'private'; ?>">
    <a class="skip-link" href="#main-content"><?= htmlspecialchars(t($translations, $currentLanguage, 'skip_to_content'), ENT_QUOTES, 'UTF-8'); ?></a>
    <div class="page">
      <div class="toast-container" aria-live="polite" aria-atomic="true"></div>
      <div class="top-bar" data-live-section="top-bar">
        <div class="brand">
          <div class="brand-title">
            <h1><?= htmlspecialchars(t($translations, $currentLanguage, 'app_title'), ENT_QUOTES, 'UTF-8'); ?></h1>
            <span class="version-pill" title="<?= htmlspecialchars(t($translations, $currentLanguage, 'version'), ENT_QUOTES, 'UTF-8'); ?>">
              <?= htmlspecialchars(t($translations, $currentLanguage, 'version'), ENT_QUOTES, 'UTF-8'); ?>
              <?= htmlspecialchars($dashboardVersion, ENT_QUOTES, 'UTF-8'); ?>
            </span>
          </div>
          <div class="muted">
            <?php if ($canViewDashboard || $isPublicView): ?>
              <?= htmlspecialchars(t($translations, $currentLanguage, 'last_update'), ENT_QUOTES, 'UTF-8'); ?>:
              <span data-live-metric="last_update">
                <?= htmlspecialchars((string) ($stats['last_update'] ?? 'N/D'), ENT_QUOTES, 'UTF-8'); ?>
              </span>
            <?php else: ?>
              <?= htmlspecialchars(t($translations, $currentLanguage, 'access_restricted_note'), ENT_QUOTES, 'UTF-8'); ?>
            <?php endif; ?>
          </div>
        </div>
        <?php if ($isPublicView): ?>
          <nav class="public-nav" aria-label="Primary">
            <a href="home.php" class="<?= $publicPage === 'home' ? 'is-active' : ''; ?>">
              <?= htmlspecialchars(t($translations, $currentLanguage, 'nav_home'), ENT_QUOTES, 'UTF-8'); ?>
            </a>
            <a href="search.php" class="<?= $publicPage === 'search' ? 'is-active' : ''; ?>">
              <?= htmlspecialchars(t($translations, $currentLanguage, 'nav_search'), ENT_QUOTES, 'UTF-8'); ?>
            </a>
            <a href="about.php" class="<?= $publicPage === 'about' ? 'is-active' : ''; ?>">
              <?= htmlspecialchars(t($translations, $currentLanguage, 'nav_about'), ENT_QUOTES, 'UTF-8'); ?>
            </a>
            <a class="nav-cta <?= $publicPage === 'access' ? 'is-active' : ''; ?>" href="access.php">
              <?= htmlspecialchars(t($translations, $currentLanguage, 'nav_access'), ENT_QUOTES, 'UTF-8'); ?>
            </a>
          </nav>
        <?php endif; ?>
        <div class="meta-row">
          <?php if ($isAdmin): ?>
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
          <?php endif; ?>
          <?php if ($isPublicView): ?>
            <span class="badge"><?= htmlspecialchars(t($translations, $currentLanguage, 'public_view'), ENT_QUOTES, 'UTF-8'); ?></span>
          <?php elseif ($currentUser): ?>
            <span class="badge">
              <?= htmlspecialchars(t($translations, $currentLanguage, 'session_active'), ENT_QUOTES, 'UTF-8'); ?>:
              <?= htmlspecialchars((string) $currentUser['username'], ENT_QUOTES, 'UTF-8'); ?>
            </span>
            <span class="badge">
              <?= htmlspecialchars($isVerified ? t($translations, $currentLanguage, 'verified_account') : t($translations, $currentLanguage, 'unverified_account'), ENT_QUOTES, 'UTF-8'); ?>
              <?= $isVerified ? '✅' : '⏳'; ?>
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
          <label class="muted" style="display: flex; align-items: center; gap: 8px;">
            <?= htmlspecialchars(t($translations, $currentLanguage, 'theme'), ENT_QUOTES, 'UTF-8'); ?>
            <select id="theme-select">
              <option value="system"><?= htmlspecialchars(t($translations, $currentLanguage, 'theme_system'), ENT_QUOTES, 'UTF-8'); ?></option>
              <option value="light"><?= htmlspecialchars(t($translations, $currentLanguage, 'theme_light'), ENT_QUOTES, 'UTF-8'); ?></option>
              <option value="dark"><?= htmlspecialchars(t($translations, $currentLanguage, 'theme_dark'), ENT_QUOTES, 'UTF-8'); ?></option>
            </select>
          </label>
        </div>
      </div>

      <?php if ($isPublicView): ?>
        <div class="sponsor-bar" data-live-section="sponsor-bar">
          <span class="badge"><?= htmlspecialchars(t($translations, $currentLanguage, 'sponsor_label'), ENT_QUOTES, 'UTF-8'); ?></span>
          <span><?= htmlspecialchars(t($translations, $currentLanguage, 'sponsor_text'), ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
      <?php endif; ?>

      <?php if (!$isPublicView): ?>
      <nav class="section-nav" aria-label="Sections">
        <a href="<?= $isAdmin ? '#overview' : '#alert-analytics-section'; ?>"><?= htmlspecialchars(t($translations, $currentLanguage, 'nav_overview'), ENT_QUOTES, 'UTF-8'); ?></a>
        <?php if ($isAdmin): ?>
          <a href="#stats-overview"><?= htmlspecialchars(t($translations, $currentLanguage, 'nav_metrics'), ENT_QUOTES, 'UTF-8'); ?></a>
        <?php endif; ?>
        <a href="#alert-analytics-section"><?= htmlspecialchars(t($translations, $currentLanguage, 'nav_analytics'), ENT_QUOTES, 'UTF-8'); ?></a>
        <a href="#public-lists-section"><?= htmlspecialchars(t($translations, $currentLanguage, 'nav_lists'), ENT_QUOTES, 'UTF-8'); ?></a>
        <a href="#intel-section"><?= htmlspecialchars(t($translations, $currentLanguage, 'nav_intel'), ENT_QUOTES, 'UTF-8'); ?></a>
        <a href="#access-section"><?= htmlspecialchars(t($translations, $currentLanguage, 'nav_access'), ENT_QUOTES, 'UTF-8'); ?></a>
        <?php if ($isAdmin): ?>
          <a href="#logs-section"><?= htmlspecialchars(t($translations, $currentLanguage, 'nav_logs'), ENT_QUOTES, 'UTF-8'); ?></a>
        <?php endif; ?>
      </nav>
      <?php endif; ?>

      <main id="main-content" class="main-content">
      <?php foreach ($flashErrors as $error): ?>
        <div class="alert-box error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
      <?php endforeach; ?>
      <?php foreach ($flashNotices as $notice): ?>
        <div class="alert-box notice"><?= htmlspecialchars($notice, ENT_QUOTES, 'UTF-8'); ?></div>
      <?php endforeach; ?>

      <?php if ($isPublicView): ?>
      <?php if ($showPublicHome): ?>
      <section class="card hero public-hero" id="public-hero" data-live-section="public-hero">
        <div class="hero-summary">
          <span class="hero-kicker"><?= htmlspecialchars(t($translations, $currentLanguage, 'public_hero_kicker'), ENT_QUOTES, 'UTF-8'); ?></span>
          <h2><?= htmlspecialchars(t($translations, $currentLanguage, 'public_hero_title'), ENT_QUOTES, 'UTF-8'); ?></h2>
          <p><?= htmlspecialchars(t($translations, $currentLanguage, 'public_hero_subtitle'), ENT_QUOTES, 'UTF-8'); ?></p>
          <div class="chip-list" style="margin-top: 12px;">
            <span class="chip">
              <?= htmlspecialchars(t($translations, $currentLanguage, 'total_alerts'), ENT_QUOTES, 'UTF-8'); ?>:
              <strong data-live-metric="total_alerts"><?= (int) $stats['total_alerts']; ?></strong>
            </span>
            <span class="chip">
              <?= htmlspecialchars(t($translations, $currentLanguage, 'total_blocks'), ENT_QUOTES, 'UTF-8'); ?>:
              <strong data-live-metric="total_blocks"><?= (int) $stats['total_blocks']; ?></strong>
            </span>
            <span class="chip">
              <?= htmlspecialchars(t($translations, $currentLanguage, 'unique_users'), ENT_QUOTES, 'UTF-8'); ?>:
              <strong data-live-metric="unique_users"><?= (int) $stats['unique_users']; ?></strong>
            </span>
            <span class="chip">
              <?= htmlspecialchars(t($translations, $currentLanguage, 'unique_hosts'), ENT_QUOTES, 'UTF-8'); ?>:
              <strong data-live-metric="unique_hosts"><?= (int) $stats['unique_hosts']; ?></strong>
            </span>
          </div>
          <div class="hero-cta">
            <a class="button-primary" href="#search">
              <?= htmlspecialchars(t($translations, $currentLanguage, 'search_button'), ENT_QUOTES, 'UTF-8'); ?>
            </a>
            <a class="button-ghost" href="#about">
              <?= htmlspecialchars(t($translations, $currentLanguage, 'nav_about'), ENT_QUOTES, 'UTF-8'); ?>
            </a>
          </div>
        </div>
        <div class="hero-actions">
          <div class="hero-action">
            <div class="muted"><?= htmlspecialchars(t($translations, $currentLanguage, 'block_rate'), ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="stat-value" data-live-metric="block_rate" data-live-format="percent"><?= number_format($blockRate, 1); ?>%</div>
            <div class="muted"><?= htmlspecialchars(t($translations, $currentLanguage, 'block_rate_help'), ENT_QUOTES, 'UTF-8'); ?></div>
          </div>
          <div class="hero-action">
            <div class="muted"><?= htmlspecialchars(t($translations, $currentLanguage, 'coverage'), ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="stat-value" data-live-metric="countries_count"><?= (int) count($stats['countries']); ?></div>
            <div class="muted"><?= htmlspecialchars(t($translations, $currentLanguage, 'countries_label'), ENT_QUOTES, 'UTF-8'); ?></div>
          </div>
        </div>
      </section>

      <section class="grid public-totals" id="public-totals" data-live-section="public-totals">
        <div class="stat-card">
          <span class="stat-label"><?= htmlspecialchars(t($translations, $currentLanguage, 'total_domains'), ENT_QUOTES, 'UTF-8'); ?></span>
          <div class="stat-value" data-live-metric="total_domains"><?= (int) $publicTotalDomains; ?></div>
          <span class="stat-footnote"><?= htmlspecialchars(t($translations, $currentLanguage, 'unique_hosts'), ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
        <div class="stat-card">
          <span class="stat-label"><?= htmlspecialchars(t($translations, $currentLanguage, 'last_30_days'), ENT_QUOTES, 'UTF-8'); ?></span>
          <div class="stat-value" data-live-metric="last_30_days"><?= (int) $publicLast30Days; ?></div>
          <span class="stat-footnote"><?= htmlspecialchars(t($translations, $currentLanguage, 'public_analytics_subtitle'), ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
      </section>

      <section class="card public-metrics" id="public-metrics" data-live-section="public-metrics">
        <div class="section-title">
          <h2><?= htmlspecialchars(t($translations, $currentLanguage, 'nav_metrics'), ENT_QUOTES, 'UTF-8'); ?></h2>
          <span class="muted"><?= htmlspecialchars(t($translations, $currentLanguage, 'last_24h'), ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
        <div class="grid">
          <div class="stat-card">
            <span class="stat-label"><?= htmlspecialchars(t($translations, $currentLanguage, 'alerts_24h'), ENT_QUOTES, 'UTF-8'); ?></span>
            <div class="stat-value" data-live-metric="alerts_24h"><?= (int) $stats['alerts_24h']; ?></div>
            <span class="stat-footnote"><?= htmlspecialchars(t($translations, $currentLanguage, 'last_24h'), ENT_QUOTES, 'UTF-8'); ?></span>
          </div>
          <div class="stat-card">
            <span class="stat-label"><?= htmlspecialchars(t($translations, $currentLanguage, 'blocks_24h'), ENT_QUOTES, 'UTF-8'); ?></span>
            <div class="stat-value" data-live-metric="blocks_24h"><?= (int) $stats['blocks_24h']; ?></div>
            <span class="stat-footnote"><?= htmlspecialchars(t($translations, $currentLanguage, 'last_24h'), ENT_QUOTES, 'UTF-8'); ?></span>
          </div>
          <div class="stat-card">
            <span class="stat-label"><?= htmlspecialchars(t($translations, $currentLanguage, 'unique_users'), ENT_QUOTES, 'UTF-8'); ?></span>
            <div class="stat-value" data-live-metric="unique_users"><?= (int) $stats['unique_users']; ?></div>
            <span class="stat-footnote"><?= htmlspecialchars(t($translations, $currentLanguage, 'unique_users_help'), ENT_QUOTES, 'UTF-8'); ?></span>
          </div>
          <div class="stat-card">
            <span class="stat-label"><?= htmlspecialchars(t($translations, $currentLanguage, 'unique_hosts'), ENT_QUOTES, 'UTF-8'); ?></span>
            <div class="stat-value" data-live-metric="unique_hosts"><?= (int) $stats['unique_hosts']; ?></div>
            <span class="stat-footnote"><?= htmlspecialchars(t($translations, $currentLanguage, 'unique_hosts_help'), ENT_QUOTES, 'UTF-8'); ?></span>
          </div>
        </div>
      </section>

      <section class="card new-domains" id="new-domains" data-live-section="public-domains">
        <div class="section-title">
          <h2><?= htmlspecialchars(t($translations, $currentLanguage, 'new_domains_title'), ENT_QUOTES, 'UTF-8'); ?></h2>
          <span class="muted"><?= htmlspecialchars(t($translations, $currentLanguage, 'new_domains_subtitle'), ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
        <div class="table-wrap" data-filter-mode="manual">
          <table data-domains-table data-domains-limit="8"
                 data-view-label="<?= htmlspecialchars(t($translations, $currentLanguage, 'search_action_view'), ENT_QUOTES, 'UTF-8'); ?>"
                 data-no-results="<?= htmlspecialchars(t($translations, $currentLanguage, 'no_detections'), ENT_QUOTES, 'UTF-8'); ?>">
            <thead>
              <tr>
                <th><?= htmlspecialchars(t($translations, $currentLanguage, 'search_col_action'), ENT_QUOTES, 'UTF-8'); ?></th>
                <th><?= htmlspecialchars(t($translations, $currentLanguage, 'search_col_domain'), ENT_QUOTES, 'UTF-8'); ?></th>
                <th><?= htmlspecialchars(t($translations, $currentLanguage, 'search_col_date'), ENT_QUOTES, 'UTF-8'); ?></th>
                <th><?= htmlspecialchars(t($translations, $currentLanguage, 'search_col_command'), ENT_QUOTES, 'UTF-8'); ?></th>
              </tr>
            </thead>
            <tbody data-domains-body>
              <?php if (empty($recentDomainsHome)): ?>
                <tr>
                  <td colspan="4" class="muted"><?= htmlspecialchars(t($translations, $currentLanguage, 'no_detections'), ENT_QUOTES, 'UTF-8'); ?></td>
                </tr>
              <?php else: ?>
                <?php foreach ($recentDomainsHome as $row): ?>
                  <?php
                    $viewUrl = trim((string) ($row['url'] ?? ''));
                    if ($viewUrl === '' && $row['domain'] !== '') {
                        $viewUrl = 'https://' . $row['domain'];
                    }
                  ?>
                  <tr>
                    <td>
                      <?php if ($viewUrl !== ''): ?>
                        <a class="button-ghost" href="<?= htmlspecialchars($viewUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">
                          <?= htmlspecialchars(t($translations, $currentLanguage, 'search_action_view'), ENT_QUOTES, 'UTF-8'); ?>
                        </a>
                      <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars((string) $row['domain'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?= htmlspecialchars((string) $row['date'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><code><?= htmlspecialchars((string) $row['command'], ENT_QUOTES, 'UTF-8'); ?></code></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>

      <section class="card public-analytics" id="public-analytics" data-live-section="public-analytics">
        <div class="section-title">
          <h2><?= htmlspecialchars(t($translations, $currentLanguage, 'public_analytics_title'), ENT_QUOTES, 'UTF-8'); ?></h2>
          <span class="muted"><?= htmlspecialchars(t($translations, $currentLanguage, 'public_analytics_subtitle'), ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
        <div class="chart-grid">
          <div class="chart-card">
            <h3><?= htmlspecialchars(t($translations, $currentLanguage, 'alerts_by_day'), ENT_QUOTES, 'UTF-8'); ?></h3>
            <canvas id="chart-alerts-day" height="140"></canvas>
          </div>
          <div class="chart-card">
            <h3><?= htmlspecialchars(t($translations, $currentLanguage, 'blocks_by_day'), ENT_QUOTES, 'UTF-8'); ?></h3>
            <canvas id="chart-blocks-day" height="140"></canvas>
          </div>
          <div class="chart-card">
            <h3><?= htmlspecialchars(t($translations, $currentLanguage, 'block_rate_trend'), ENT_QUOTES, 'UTF-8'); ?></h3>
            <canvas id="chart-block-rate" height="140"></canvas>
          </div>
          <div class="chart-card">
            <h3><?= htmlspecialchars(t($translations, $currentLanguage, 'alerts_by_hour'), ENT_QUOTES, 'UTF-8'); ?></h3>
            <canvas id="chart-alerts-hour" height="140"></canvas>
          </div>
              <div class="chart-card">
                <h3><?= htmlspecialchars(t($translations, $currentLanguage, 'blocks_by_hour'), ENT_QUOTES, 'UTF-8'); ?></h3>
                <canvas id="chart-blocks-hour" height="140"></canvas>
              </div>
          <div class="chart-card">
            <h3><?= htmlspecialchars(t($translations, $currentLanguage, 'country_distribution'), ENT_QUOTES, 'UTF-8'); ?></h3>
            <canvas id="chart-alerts-country" height="140"></canvas>
          </div>
          <div class="chart-card">
            <h3><?= htmlspecialchars(t($translations, $currentLanguage, 'signal_types'), ENT_QUOTES, 'UTF-8'); ?></h3>
            <canvas id="chart-alerts-signals" height="140"></canvas>
          </div>
          <div class="chart-card">
            <h3><?= htmlspecialchars(t($translations, $currentLanguage, 'os_breakdown'), ENT_QUOTES, 'UTF-8'); ?></h3>
            <canvas id="chart-os" height="140"></canvas>
          </div>
          <div class="chart-card">
            <h3><?= htmlspecialchars(t($translations, $currentLanguage, 'browser_breakdown'), ENT_QUOTES, 'UTF-8'); ?></h3>
            <canvas id="chart-browsers" height="140"></canvas>
          </div>
          <div class="chart-card">
            <h3><?= htmlspecialchars(t($translations, $currentLanguage, 'install_channel_chart'), ENT_QUOTES, 'UTF-8'); ?></h3>
            <canvas id="chart-install-channels" height="140"></canvas>
          </div>
        </div>
      </section>
      <?php endif; ?>

      <?php if ($showPublicSearch): ?>
      <section class="card search-section" id="search" data-live-section="public-search" data-search-panel="true">
        <div class="section-title">
          <h2><?= htmlspecialchars(t($translations, $currentLanguage, 'search_title'), ENT_QUOTES, 'UTF-8'); ?></h2>
          <div class="meta-row">
            <button class="button-secondary" type="button" data-search-export>
              <?= htmlspecialchars(t($translations, $currentLanguage, 'search_export'), ENT_QUOTES, 'UTF-8'); ?>
            </button>
          </div>
        </div>
        <div class="muted"><?= htmlspecialchars(t($translations, $currentLanguage, 'search_subtitle'), ENT_QUOTES, 'UTF-8'); ?></div>
        <div class="search-bar">
          <input type="search" data-search-input placeholder="<?= htmlspecialchars(t($translations, $currentLanguage, 'search_placeholder'), ENT_QUOTES, 'UTF-8'); ?>" />
          <button class="button-primary" type="button" data-search-button>
            <?= htmlspecialchars(t($translations, $currentLanguage, 'search_button'), ENT_QUOTES, 'UTF-8'); ?>
          </button>
        </div>
        <details class="search-filters" open>
          <summary><?= htmlspecialchars(t($translations, $currentLanguage, 'search_advanced'), ENT_QUOTES, 'UTF-8'); ?></summary>
          <div class="filter-grid">
            <label>
              <?= htmlspecialchars(t($translations, $currentLanguage, 'search_filter_label'), ENT_QUOTES, 'UTF-8'); ?>
              <select data-search-field>
                <option value="any"><?= htmlspecialchars(t($translations, $currentLanguage, 'search_filter_any'), ENT_QUOTES, 'UTF-8'); ?></option>
                <option value="domain"><?= htmlspecialchars(t($translations, $currentLanguage, 'search_filter_domain'), ENT_QUOTES, 'UTF-8'); ?></option>
                <option value="command"><?= htmlspecialchars(t($translations, $currentLanguage, 'search_filter_command'), ENT_QUOTES, 'UTF-8'); ?></option>
              </select>
            </label>
            <button class="button-ghost" type="button" data-search-clear>
              <?= htmlspecialchars(t($translations, $currentLanguage, 'search_clear'), ENT_QUOTES, 'UTF-8'); ?>
            </button>
          </div>
        </details>
        <div class="table-wrap" data-filter-mode="manual">
          <table data-search-table data-domains-table data-filter="manual"
                 data-view-label="<?= htmlspecialchars(t($translations, $currentLanguage, 'search_action_view'), ENT_QUOTES, 'UTF-8'); ?>"
                 data-no-results="<?= htmlspecialchars(t($translations, $currentLanguage, 'no_detections'), ENT_QUOTES, 'UTF-8'); ?>">
            <thead>
              <tr>
                <th><?= htmlspecialchars(t($translations, $currentLanguage, 'search_col_action'), ENT_QUOTES, 'UTF-8'); ?></th>
                <th><?= htmlspecialchars(t($translations, $currentLanguage, 'search_col_domain'), ENT_QUOTES, 'UTF-8'); ?></th>
                <th><?= htmlspecialchars(t($translations, $currentLanguage, 'search_col_date'), ENT_QUOTES, 'UTF-8'); ?></th>
                <th><?= htmlspecialchars(t($translations, $currentLanguage, 'search_col_command'), ENT_QUOTES, 'UTF-8'); ?></th>
              </tr>
            </thead>
            <tbody data-search-body data-domains-body>
              <?php if (empty($recentDomains)): ?>
                <tr>
                  <td colspan="4" class="muted"><?= htmlspecialchars(t($translations, $currentLanguage, 'no_detections'), ENT_QUOTES, 'UTF-8'); ?></td>
                </tr>
              <?php else: ?>
                <?php foreach ($recentDomains as $row): ?>
                  <?php
                    $viewUrl = trim((string) ($row['url'] ?? ''));
                    if ($viewUrl === '' && $row['domain'] !== '') {
                        $viewUrl = 'https://' . $row['domain'];
                    }
                  ?>
                  <tr data-search-row>
                    <td>
                      <?php if ($viewUrl !== ''): ?>
                        <a class="button-ghost" href="<?= htmlspecialchars($viewUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">
                          <?= htmlspecialchars(t($translations, $currentLanguage, 'search_action_view'), ENT_QUOTES, 'UTF-8'); ?>
                        </a>
                      <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars((string) $row['domain'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?= htmlspecialchars((string) $row['date'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><code><?= htmlspecialchars((string) $row['command'], ENT_QUOTES, 'UTF-8'); ?></code></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>

      <section class="card scan-section" id="recent-scan" data-live-section="public-scan">
        <div class="section-title">
          <h2><?= htmlspecialchars(t($translations, $currentLanguage, 'recent_scan_title'), ENT_QUOTES, 'UTF-8'); ?></h2>
          <span class="muted"><?= htmlspecialchars(t($translations, $currentLanguage, 'recent_scan_subtitle'), ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
        <div class="scan-grid">
          <div class="scan-card">
            <div class="scan-label"><?= htmlspecialchars(t($translations, $currentLanguage, 'before_label'), ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="scan-placeholder"><?= htmlspecialchars(t($translations, $currentLanguage, 'no_snapshots'), ENT_QUOTES, 'UTF-8'); ?></div>
          </div>
          <div class="scan-card">
            <div class="scan-label"><?= htmlspecialchars(t($translations, $currentLanguage, 'after_label'), ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="scan-placeholder"><?= htmlspecialchars(t($translations, $currentLanguage, 'no_snapshots'), ENT_QUOTES, 'UTF-8'); ?></div>
          </div>
        </div>
      </section>
      <?php endif; ?>

      <?php if ($showPublicAbout): ?>
      <section class="card about-section" id="about" data-live-section="public-about">
        <div class="section-title">
          <h2><?= htmlspecialchars(t($translations, $currentLanguage, 'about_title'), ENT_QUOTES, 'UTF-8'); ?></h2>
        </div>
        <p class="muted"><?= htmlspecialchars(t($translations, $currentLanguage, 'about_body'), ENT_QUOTES, 'UTF-8'); ?></p>
        <div class="about-grid">
          <div class="card" style="padding: 16px;">
            <h3><?= htmlspecialchars(t($translations, $currentLanguage, 'about_collection_title'), ENT_QUOTES, 'UTF-8'); ?></h3>
            <p class="muted"><?= htmlspecialchars(t($translations, $currentLanguage, 'about_collection_body'), ENT_QUOTES, 'UTF-8'); ?></p>
          </div>
          <div class="card" style="padding: 16px;">
            <h3><?= htmlspecialchars(t($translations, $currentLanguage, 'about_public_title'), ENT_QUOTES, 'UTF-8'); ?></h3>
            <p class="muted"><?= htmlspecialchars(t($translations, $currentLanguage, 'about_public_body'), ENT_QUOTES, 'UTF-8'); ?></p>
          </div>
        </div>
      </section>
      <?php endif; ?>

      <?php if ($showPublicAccess): ?>
      <section class="card public-access" id="public-access" data-live-section="public-access">
        <div class="section-title">
          <h2><?= htmlspecialchars(t($translations, $currentLanguage, 'public_access_title'), ENT_QUOTES, 'UTF-8'); ?></h2>
          <span class="muted"><?= htmlspecialchars(t($translations, $currentLanguage, 'public_access_subtitle'), ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
        <?php if (!$currentUser): ?>
          <form method="post" class="form-grid" style="margin-top: 12px;">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
            <input type="hidden" name="action" value="login" />
            <label>
              <?= htmlspecialchars(t($translations, $currentLanguage, 'username'), ENT_QUOTES, 'UTF-8'); ?>
              <input type="text" name="username" required autocomplete="username" autocapitalize="none" spellcheck="false" />
            </label>
            <label>
              <?= htmlspecialchars(t($translations, $currentLanguage, 'password'), ENT_QUOTES, 'UTF-8'); ?>
              <input type="password" name="password" required autocomplete="current-password" />
            </label>
            <div class="form-actions">
              <button class="button-primary" type="submit"><?= htmlspecialchars(t($translations, $currentLanguage, 'login'), ENT_QUOTES, 'UTF-8'); ?></button>
            </div>
          </form>
        <?php else: ?>
          <div class="highlight" style="margin-top: 12px;">
            <span class="badge">
              <?= htmlspecialchars(t($translations, $currentLanguage, 'session_active'), ENT_QUOTES, 'UTF-8'); ?>:
              <?= htmlspecialchars((string) $currentUser['username'], ENT_QUOTES, 'UTF-8'); ?>
            </span>
          </div>
        <?php endif; ?>
      </section>
      <?php endif; ?>
      <?php endif; ?>

      <?php if ($canViewDashboard): ?>
      <?php if ($isAdmin): ?>
      <section class="card hero" id="overview" data-accordion-title="<?= htmlspecialchars(t($translations, $currentLanguage, 'dashboard_title'), ENT_QUOTES, 'UTF-8'); ?>" data-live-section="hero">
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
          <div class="hero-metrics">
            <div class="mini-stat">
              <span><?= htmlspecialchars(t($translations, $currentLanguage, 'alerts_24h'), ENT_QUOTES, 'UTF-8'); ?></span>
              <strong><?= (int) $stats['alerts_24h']; ?></strong>
            </div>
            <div class="mini-stat">
              <span><?= htmlspecialchars(t($translations, $currentLanguage, 'blocks_24h'), ENT_QUOTES, 'UTF-8'); ?></span>
              <strong><?= (int) $stats['blocks_24h']; ?></strong>
            </div>
            <div class="mini-stat">
              <span><?= htmlspecialchars(t($translations, $currentLanguage, 'unique_hosts'), ENT_QUOTES, 'UTF-8'); ?></span>
              <strong><?= (int) $stats['unique_hosts']; ?></strong>
            </div>
            <div class="mini-stat">
              <span><?= htmlspecialchars(t($translations, $currentLanguage, 'unique_users'), ENT_QUOTES, 'UTF-8'); ?></span>
              <strong><?= (int) $stats['unique_users']; ?></strong>
            </div>
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

      <?php endif; ?>

      <?php if ($isAdmin): ?>
      <section class="grid" id="stats-overview" data-live-section="stats">
        <div class="stat-card">
          <span class="stat-label"><?= htmlspecialchars(t($translations, $currentLanguage, 'total_alerts'), ENT_QUOTES, 'UTF-8'); ?></span>
          <div class="stat-value" data-live-metric="total_alerts"><?= (int) $stats['total_alerts']; ?></div>
          <span class="stat-footnote"><?= htmlspecialchars(t($translations, $currentLanguage, 'extension_history'), ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
        <div class="stat-card">
          <span class="stat-label"><?= htmlspecialchars(t($translations, $currentLanguage, 'total_blocks'), ENT_QUOTES, 'UTF-8'); ?></span>
          <div class="stat-value" data-live-metric="total_blocks"><?= (int) $stats['total_blocks']; ?></div>
          <span class="stat-footnote"><?= htmlspecialchars(t($translations, $currentLanguage, 'confirmed_preventions'), ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
        <div class="stat-card">
          <span class="stat-label"><?= htmlspecialchars(t($translations, $currentLanguage, 'alerts_24h'), ENT_QUOTES, 'UTF-8'); ?></span>
          <div class="stat-value" data-live-metric="alerts_24h"><?= (int) $stats['alerts_24h']; ?></div>
          <span class="stat-footnote"><?= htmlspecialchars(t($translations, $currentLanguage, 'last_24h'), ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
        <div class="stat-card">
          <span class="stat-label"><?= htmlspecialchars(t($translations, $currentLanguage, 'blocks_24h'), ENT_QUOTES, 'UTF-8'); ?></span>
          <div class="stat-value" data-live-metric="blocks_24h"><?= (int) $stats['blocks_24h']; ?></div>
          <span class="stat-footnote"><?= htmlspecialchars(t($translations, $currentLanguage, 'last_24h'), ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
        <div class="stat-card">
          <span class="stat-label"><?= htmlspecialchars(t($translations, $currentLanguage, 'unique_hosts'), ENT_QUOTES, 'UTF-8'); ?></span>
          <div class="stat-value" data-live-metric="unique_hosts"><?= (int) $stats['unique_hosts']; ?></div>
          <span class="stat-footnote"><?= htmlspecialchars(t($translations, $currentLanguage, 'unique_hosts_help'), ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
        <div class="stat-card">
          <span class="stat-label"><?= htmlspecialchars(t($translations, $currentLanguage, 'unique_users'), ENT_QUOTES, 'UTF-8'); ?></span>
          <div class="stat-value" data-live-metric="unique_users"><?= (int) $stats['unique_users']; ?></div>
          <span class="stat-footnote"><?= htmlspecialchars(t($translations, $currentLanguage, 'unique_users_help'), ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
        <div class="stat-card">
          <span class="stat-label"><?= htmlspecialchars(t($translations, $currentLanguage, 'manual_sites'), ENT_QUOTES, 'UTF-8'); ?></span>
          <div class="stat-value" data-live-metric="manual_sites_count"><?= (int) count($stats['manual_sites']); ?></div>
          <span class="stat-footnote"><?= htmlspecialchars(t($translations, $currentLanguage, 'manual_domains'), ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
        <div class="stat-card">
          <span class="stat-label"><?= htmlspecialchars(t($translations, $currentLanguage, 'recent_alerts'), ENT_QUOTES, 'UTF-8'); ?></span>
          <div class="stat-value" data-live-metric="recent_count"><?= (int) $stats['recent_count']; ?></div>
          <span class="stat-footnote"><?= htmlspecialchars(t($translations, $currentLanguage, 'recent_events'), ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
        <?php if ($isAdmin): ?>
          <div class="stat-card">
            <span class="stat-label"><?= htmlspecialchars(t($translations, $currentLanguage, 'alerted_sites'), ENT_QUOTES, 'UTF-8'); ?></span>
            <div class="stat-value" data-live-metric="alert_sites_count"><?= (int) count($stats['alert_sites']); ?></div>
            <span class="stat-footnote"><?= htmlspecialchars(t($translations, $currentLanguage, 'pending_review'), ENT_QUOTES, 'UTF-8'); ?></span>
          </div>
        <?php endif; ?>
        <div class="stat-card">
          <span class="stat-label"><?= htmlspecialchars(t($translations, $currentLanguage, 'block_rate'), ENT_QUOTES, 'UTF-8'); ?></span>
          <div class="stat-value" data-live-metric="block_rate" data-live-format="percent"><?= number_format($blockRate, 1); ?>%</div>
          <span class="stat-footnote"><?= htmlspecialchars(t($translations, $currentLanguage, 'containment_efficiency'), ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
      </section>
      <?php endif; ?>

      <div class="workspace-tabs" id="workspace-tabs" data-default-tab="alerts">
        <button type="button" class="workspace-tab is-active" data-workspace-tab="alerts">
          <?= htmlspecialchars(t($translations, $currentLanguage, 'tab_alerts'), ENT_QUOTES, 'UTF-8'); ?>
        </button>
        <button type="button" class="workspace-tab" data-workspace-tab="lists">
          <?= htmlspecialchars(t($translations, $currentLanguage, 'tab_lists'), ENT_QUOTES, 'UTF-8'); ?>
        </button>
        <button type="button" class="workspace-tab" data-workspace-tab="intel">
          <?= htmlspecialchars(t($translations, $currentLanguage, 'tab_intel'), ENT_QUOTES, 'UTF-8'); ?>
        </button>
        <?php if ($isAdmin): ?>
          <button type="button" class="workspace-tab" data-workspace-tab="users">
            <?= htmlspecialchars(t($translations, $currentLanguage, 'tab_users'), ENT_QUOTES, 'UTF-8'); ?>
          </button>
          <button type="button" class="workspace-tab" data-workspace-tab="data">
            <?= htmlspecialchars(t($translations, $currentLanguage, 'tab_data'), ENT_QUOTES, 'UTF-8'); ?>
          </button>
        <?php endif; ?>
        <button type="button" class="workspace-tab" data-workspace-tab="access">
          <?= htmlspecialchars(t($translations, $currentLanguage, 'tab_access'), ENT_QUOTES, 'UTF-8'); ?>
        </button>
      </div>

      <div class="layout">
        <div>
          <section class="card workspace-section" id="alert-analytics-section" data-workspace-section="alerts" data-live-section="alert-analytics">
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
                <h3><?= htmlspecialchars(t($translations, $currentLanguage, 'blocks_by_day'), ENT_QUOTES, 'UTF-8'); ?></h3>
                <canvas id="chart-blocks-day" height="140"></canvas>
              </div>
              <div class="chart-card">
                <h3><?= htmlspecialchars(t($translations, $currentLanguage, 'alerts_baseline'), ENT_QUOTES, 'UTF-8'); ?></h3>
                <canvas id="chart-alerts-baseline" height="140"></canvas>
              </div>
              <div class="chart-card">
                <h3><?= htmlspecialchars(t($translations, $currentLanguage, 'block_rate_trend'), ENT_QUOTES, 'UTF-8'); ?></h3>
                <canvas id="chart-block-rate" height="140"></canvas>
              </div>
              <div class="chart-card">
                <h3><?= htmlspecialchars(t($translations, $currentLanguage, 'alerts_by_hour'), ENT_QUOTES, 'UTF-8'); ?></h3>
                <canvas id="chart-alerts-hour" height="140"></canvas>
              </div>
              <div class="chart-card">
                <h3><?= htmlspecialchars(t($translations, $currentLanguage, 'blocks_by_hour'), ENT_QUOTES, 'UTF-8'); ?></h3>
                <canvas id="chart-blocks-hour" height="140"></canvas>
              </div>
              <div class="chart-card">
                <h3><?= htmlspecialchars(t($translations, $currentLanguage, 'country_distribution'), ENT_QUOTES, 'UTF-8'); ?></h3>
                <canvas id="chart-alerts-country" height="140"></canvas>
              </div>
              <div class="chart-card">
                <h3><?= htmlspecialchars(t($translations, $currentLanguage, 'os_breakdown'), ENT_QUOTES, 'UTF-8'); ?></h3>
                <canvas id="chart-os" height="140"></canvas>
              </div>
              <div class="chart-card">
                <h3><?= htmlspecialchars(t($translations, $currentLanguage, 'browser_breakdown'), ENT_QUOTES, 'UTF-8'); ?></h3>
                <canvas id="chart-browsers" height="140"></canvas>
              </div>
              <div class="chart-card">
                <h3><?= htmlspecialchars(t($translations, $currentLanguage, 'signal_types'), ENT_QUOTES, 'UTF-8'); ?></h3>
                <canvas id="chart-alerts-signals" height="140"></canvas>
              </div>
              <div class="chart-card">
                <h3><?= htmlspecialchars(t($translations, $currentLanguage, 'review_status_chart'), ENT_QUOTES, 'UTF-8'); ?></h3>
                <canvas id="chart-review-status" height="140"></canvas>
              </div>
              <div class="chart-card">
                <h3><?= htmlspecialchars(t($translations, $currentLanguage, 'top_hosts_chart'), ENT_QUOTES, 'UTF-8'); ?></h3>
                <canvas id="chart-top-hosts" height="140"></canvas>
              </div>
              <div class="chart-card">
                <h3><?= htmlspecialchars(t($translations, $currentLanguage, 'install_channel_chart'), ENT_QUOTES, 'UTF-8'); ?></h3>
                <canvas id="chart-install-channels" height="140"></canvas>
              </div>
            </div>
          </section>

          <section class="card workspace-section" style="margin-top: 24px;" id="recent-detections-section" data-workspace-section="alerts" data-live-section="recent-detections">
            <div class="section-title">
              <h2><?= htmlspecialchars(t($translations, $currentLanguage, 'recent_detections'), ENT_QUOTES, 'UTF-8'); ?></h2>
              <span class="muted"><?= htmlspecialchars(t($translations, $currentLanguage, 'accepted_legend'), ENT_QUOTES, 'UTF-8'); ?></span>
              <?php if ($isAdmin): ?>
                <form method="post" class="form-actions js-ajax" data-refresh-target="#recent-detections-section">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                  <input type="hidden" name="action" value="clear_unaccepted" />
                  <button class="button-secondary" type="submit" onclick="return confirm('<?= htmlspecialchars(t($translations, $currentLanguage, 'confirm_clear'), ENT_QUOTES, 'UTF-8'); ?>');">
                    <?= htmlspecialchars(t($translations, $currentLanguage, 'clear_unaccepted'), ENT_QUOTES, 'UTF-8'); ?>
                  </button>
                  <span class="muted"><?= htmlspecialchars(t($translations, $currentLanguage, 'clear_unaccepted_help'), ENT_QUOTES, 'UTF-8'); ?></span>
                </form>
                <form method="post" class="form-actions js-ajax" data-refresh-target="#recent-detections-section">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                  <input type="hidden" name="action" value="clear_accepted" />
                  <button class="button-secondary" type="submit" onclick="return confirm('<?= htmlspecialchars(t($translations, $currentLanguage, 'confirm_clear_accepted'), ENT_QUOTES, 'UTF-8'); ?>');">
                    <?= htmlspecialchars(t($translations, $currentLanguage, 'clear_accepted'), ENT_QUOTES, 'UTF-8'); ?>
                  </button>
                  <span class="muted"><?= htmlspecialchars(t($translations, $currentLanguage, 'clear_accepted_help'), ENT_QUOTES, 'UTF-8'); ?></span>
                </form>
              <?php endif; ?>
            </div>
            <?php if (empty($recentDetections)): ?>
              <div class="muted"><?= htmlspecialchars(t($translations, $currentLanguage, 'no_detections'), ENT_QUOTES, 'UTF-8'); ?></div>
            <?php else: ?>
              <?php foreach ($recentDetections as $entry): ?>
                <?php
                  $displayMessage = redactCredentials((string) $entry['message'], $isAdmin);
                  $displayDetected = redactCredentials((string) $entry['detected'], $isAdmin);
                  $displayContext = redactCredentials((string) $entry['full_context'], $isAdmin);
                ?>
                <details class="report-card">
                  <summary class="investigable js-investigate" data-investigable="true" data-domain="<?= htmlspecialchars(isValidDomain($entry['hostname']) ? $entry['hostname'] : '', ENT_QUOTES, 'UTF-8'); ?>" data-url="<?= htmlspecialchars($entry['url'], ENT_QUOTES, 'UTF-8'); ?>" data-context="detection" data-timestamp="<?= htmlspecialchars($entry['timestamp'], ENT_QUOTES, 'UTF-8'); ?>">
                    <?= htmlspecialchars($entry['hostname'], ENT_QUOTES, 'UTF-8'); ?>
                    <?php if ($entry['blocked']): ?>
                      <span class="badge badge-blocked"><?= htmlspecialchars(t($translations, $currentLanguage, 'blocked'), ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php endif; ?>
                    <?php if ($entry['accepted']): ?>
                      <span class="badge badge-accepted"><?= htmlspecialchars(t($translations, $currentLanguage, 'accepted'), ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php else: ?>
                      <span class="badge badge-pending"><?= htmlspecialchars(t($translations, $currentLanguage, 'not_accepted'), ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php endif; ?>
                    <span class="status-pill <?= htmlspecialchars($entry['review_class'], ENT_QUOTES, 'UTF-8'); ?>">
                      <?= htmlspecialchars($entry['review_label'], ENT_QUOTES, 'UTF-8'); ?>
                    </span>
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
                  <div class="report-section">
                    <strong><?= htmlspecialchars(t($translations, $currentLanguage, 'review_status'), ENT_QUOTES, 'UTF-8'); ?></strong>
                    <div class="muted"><?= htmlspecialchars($entry['review_label'], ENT_QUOTES, 'UTF-8'); ?></div>
                  </div>
                  <?php if (!empty($entry['reviewed_at'])): ?>
                    <div class="report-section">
                      <strong><?= htmlspecialchars(t($translations, $currentLanguage, 'reviewed_at'), ENT_QUOTES, 'UTF-8'); ?></strong>
                      <div class="muted"><?= htmlspecialchars($entry['reviewed_at'], ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>
                  <?php endif; ?>
                  <?php if (!empty($entry['message'])): ?>
                    <div class="report-section">
                      <strong><?= htmlspecialchars(t($translations, $currentLanguage, 'summary'), ENT_QUOTES, 'UTF-8'); ?></strong>
                      <div class="muted"><?= htmlspecialchars($displayMessage, ENT_QUOTES, 'UTF-8'); ?></div>
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
                      <pre><?= htmlspecialchars($displayDetected, ENT_QUOTES, 'UTF-8'); ?></pre>
                    </div>
                  <?php endif; ?>
                  <?php if (!empty($entry['full_context'])): ?>
                    <div class="report-section context-panel">
                      <strong><?= htmlspecialchars(t($translations, $currentLanguage, 'full_context'), ENT_QUOTES, 'UTF-8'); ?></strong>
                      <pre><?= htmlspecialchars($displayContext, ENT_QUOTES, 'UTF-8'); ?></pre>
                    </div>
                  <?php endif; ?>
                  <?php if ($isAdmin && !$entry['accepted']): ?>
                    <form method="post" class="form-actions js-ajax" style="margin-top: 12px;" data-refresh-target="#recent-detections-section">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                      <input type="hidden" name="action" value="accept_detection" />
                      <input type="hidden" name="detection_id" value="<?= (int) $entry['id']; ?>" />
                      <button class="button-primary" type="submit"><?= htmlspecialchars(t($translations, $currentLanguage, 'mark_accepted'), ENT_QUOTES, 'UTF-8'); ?></button>
                    </form>
                  <?php endif; ?>
                  <?php if ($isAdmin): ?>
                    <form method="post" class="form-actions js-ajax" style="margin-top: 12px;" data-refresh-target="#recent-detections-section">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                      <input type="hidden" name="action" value="review_detection" />
                      <input type="hidden" name="detection_id" value="<?= (int) $entry['id']; ?>" />
                      <button class="button-secondary" type="submit" name="decision" value="allow"><?= htmlspecialchars(t($translations, $currentLanguage, 'review_allow'), ENT_QUOTES, 'UTF-8'); ?></button>
                      <button class="button-primary" type="submit" name="decision" value="block"><?= htmlspecialchars(t($translations, $currentLanguage, 'review_block'), ENT_QUOTES, 'UTF-8'); ?></button>
                      <button class="button-ghost" type="submit" name="decision" value="investigate"><?= htmlspecialchars(t($translations, $currentLanguage, 'review_investigate'), ENT_QUOTES, 'UTF-8'); ?></button>
                      <span class="muted"><?= htmlspecialchars(t($translations, $currentLanguage, 'review_actions'), ENT_QUOTES, 'UTF-8'); ?></span>
                    </form>
                  <?php endif; ?>
                  <?php if ($isAdmin): ?>
                    <form method="post" class="form-actions js-ajax" style="margin-top: 12px;" data-refresh-target="#recent-detections-section">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                      <input type="hidden" name="action" value="delete_detection" />
                      <input type="hidden" name="detection_id" value="<?= (int) $entry['id']; ?>" />
                      <button class="button-secondary" type="submit" onclick="return confirm('<?= htmlspecialchars(t($translations, $currentLanguage, 'confirm_delete'), ENT_QUOTES, 'UTF-8'); ?>');">
                        <?= htmlspecialchars(t($translations, $currentLanguage, 'delete_detection'), ENT_QUOTES, 'UTF-8'); ?>
                      </button>
                    </form>
                  <?php endif; ?>
                </details>
              <?php endforeach; ?>
            <?php endif; ?>
          </section>

          <?php if ($isAdmin): ?>
            <section class="card workspace-section" style="margin-top: 24px;" data-workspace-section="alerts">
              <h2><?= htmlspecialchars(t($translations, $currentLanguage, 'appeal_title'), ENT_QUOTES, 'UTF-8'); ?></h2>
              <form method="post" class="form-grid js-ajax" data-reset="true">
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
          <?php endif; ?>

          <?php if ($isAdmin): ?>
            <section class="card workspace-section" style="margin-top: 24px;" id="recent-appeals-section" data-workspace-section="alerts" data-live-section="recent-appeals">
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
                    <form method="post" class="form-grid js-ajax" style="margin-top: 12px;" data-refresh-target="#recent-appeals-section">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                      <input type="hidden" name="action" value="appeal_update" />
                      <input type="hidden" name="appeal_id" value="<?= (int) ($appeal['id'] ?? 0); ?>" />
                      <label>
                        <?= htmlspecialchars(t($translations, $currentLanguage, 'domain'), ENT_QUOTES, 'UTF-8'); ?>
                        <input type="text" name="domain" value="<?= htmlspecialchars((string) ($appeal['domain'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required />
                      </label>
                      <label>
                        <?= htmlspecialchars(t($translations, $currentLanguage, 'reason'), ENT_QUOTES, 'UTF-8'); ?>
                        <textarea name="reason" required><?= htmlspecialchars((string) ($appeal['reason'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                      </label>
                      <label>
                        <?= htmlspecialchars(t($translations, $currentLanguage, 'contact_optional'), ENT_QUOTES, 'UTF-8'); ?>
                        <input type="text" name="contact" value="<?= htmlspecialchars((string) ($appeal['contact'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                      </label>
                      <div class="form-actions">
                        <button class="button-secondary" type="submit"><?= htmlspecialchars(t($translations, $currentLanguage, 'update_request'), ENT_QUOTES, 'UTF-8'); ?></button>
                      </div>
                    </form>
                    <form method="post" class="form-actions js-ajax" style="margin-top: 12px;" data-refresh-target="#recent-appeals-section">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                      <input type="hidden" name="action" value="appeal_delete" />
                      <input type="hidden" name="appeal_id" value="<?= (int) ($appeal['id'] ?? 0); ?>" />
                      <button class="button-secondary" type="submit" onclick="return confirm('<?= htmlspecialchars(t($translations, $currentLanguage, 'confirm_delete'), ENT_QUOTES, 'UTF-8'); ?>');">
                        <?= htmlspecialchars(t($translations, $currentLanguage, 'delete_request'), ENT_QUOTES, 'UTF-8'); ?>
                      </button>
                    </form>
                    <?php if (!in_array((string) ($appeal['status'] ?? ''), ['approved', 'rejected'], true)): ?>
                      <form method="post" class="form-actions js-ajax" style="margin-top: 12px;" data-refresh-target="#recent-appeals-section">
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

          <section class="card workspace-section" style="margin-top: 24px;" id="alert-analytics-section" data-workspace-section="alerts" data-live-section="alert-analytics">
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
                <h3><?= htmlspecialchars(t($translations, $currentLanguage, 'blocks_by_day'), ENT_QUOTES, 'UTF-8'); ?></h3>
                <canvas id="chart-blocks-day" height="140"></canvas>
              </div>
              <div class="chart-card">
                <h3><?= htmlspecialchars(t($translations, $currentLanguage, 'alerts_baseline'), ENT_QUOTES, 'UTF-8'); ?></h3>
                <canvas id="chart-alerts-baseline" height="140"></canvas>
              </div>
              <div class="chart-card">
                <h3><?= htmlspecialchars(t($translations, $currentLanguage, 'block_rate_trend'), ENT_QUOTES, 'UTF-8'); ?></h3>
                <canvas id="chart-block-rate" height="140"></canvas>
              </div>
              <div class="chart-card">
                <h3><?= htmlspecialchars(t($translations, $currentLanguage, 'alerts_by_hour'), ENT_QUOTES, 'UTF-8'); ?></h3>
                <canvas id="chart-alerts-hour" height="140"></canvas>
              </div>
              <div class="chart-card">
                <h3><?= htmlspecialchars(t($translations, $currentLanguage, 'blocks_by_hour'), ENT_QUOTES, 'UTF-8'); ?></h3>
                <canvas id="chart-blocks-hour" height="140"></canvas>
              </div>
              <div class="chart-card">
                <h3><?= htmlspecialchars(t($translations, $currentLanguage, 'country_distribution'), ENT_QUOTES, 'UTF-8'); ?></h3>
                <canvas id="chart-alerts-country" height="140"></canvas>
              </div>
              <div class="chart-card">
                <h3><?= htmlspecialchars(t($translations, $currentLanguage, 'os_breakdown'), ENT_QUOTES, 'UTF-8'); ?></h3>
                <canvas id="chart-os" height="140"></canvas>
              </div>
              <div class="chart-card">
                <h3><?= htmlspecialchars(t($translations, $currentLanguage, 'browser_breakdown'), ENT_QUOTES, 'UTF-8'); ?></h3>
                <canvas id="chart-browsers" height="140"></canvas>
              </div>
              <div class="chart-card">
                <h3><?= htmlspecialchars(t($translations, $currentLanguage, 'signal_types'), ENT_QUOTES, 'UTF-8'); ?></h3>
                <canvas id="chart-alerts-signals" height="140"></canvas>
              </div>
              <div class="chart-card">
                <h3><?= htmlspecialchars(t($translations, $currentLanguage, 'review_status_chart'), ENT_QUOTES, 'UTF-8'); ?></h3>
                <canvas id="chart-review-status" height="140"></canvas>
              </div>
              <div class="chart-card">
                <h3><?= htmlspecialchars(t($translations, $currentLanguage, 'top_hosts_chart'), ENT_QUOTES, 'UTF-8'); ?></h3>
                <canvas id="chart-top-hosts" height="140"></canvas>
              </div>
              <div class="chart-card">
                <h3><?= htmlspecialchars(t($translations, $currentLanguage, 'install_channel_chart'), ENT_QUOTES, 'UTF-8'); ?></h3>
                <canvas id="chart-install-channels" height="140"></canvas>
              </div>
            </div>
          </section>

          <section class="card workspace-section" style="margin-top: 24px;" id="recent-detections-section" data-workspace-section="alerts" data-live-section="recent-detections">
            <div class="section-title">
              <h2><?= htmlspecialchars(t($translations, $currentLanguage, 'recent_detections'), ENT_QUOTES, 'UTF-8'); ?></h2>
              <?php if ($isAdmin): ?>
                <form method="post" class="form-actions js-ajax" data-refresh-target="#recent-detections-section">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                  <input type="hidden" name="action" value="clear_unaccepted" />
                  <button class="button-secondary" type="submit" onclick="return confirm('<?= htmlspecialchars(t($translations, $currentLanguage, 'confirm_clear'), ENT_QUOTES, 'UTF-8'); ?>');">
                    <?= htmlspecialchars(t($translations, $currentLanguage, 'clear_unaccepted'), ENT_QUOTES, 'UTF-8'); ?>
                  </button>
                  <span class="muted"><?= htmlspecialchars(t($translations, $currentLanguage, 'clear_unaccepted_help'), ENT_QUOTES, 'UTF-8'); ?></span>
                </form>
                <form method="post" class="form-actions js-ajax" data-refresh-target="#recent-detections-section">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                  <input type="hidden" name="action" value="clear_accepted" />
                  <button class="button-secondary" type="submit" onclick="return confirm('<?= htmlspecialchars(t($translations, $currentLanguage, 'confirm_clear_accepted'), ENT_QUOTES, 'UTF-8'); ?>');">
                    <?= htmlspecialchars(t($translations, $currentLanguage, 'clear_accepted'), ENT_QUOTES, 'UTF-8'); ?>
                  </button>
                  <span class="muted"><?= htmlspecialchars(t($translations, $currentLanguage, 'clear_accepted_help'), ENT_QUOTES, 'UTF-8'); ?></span>
                </form>
              <?php endif; ?>
            </div>
            <?php if (empty($recentDetections)): ?>
              <div class="muted"><?= htmlspecialchars(t($translations, $currentLanguage, 'no_detections'), ENT_QUOTES, 'UTF-8'); ?></div>
            <?php else: ?>
              <?php foreach ($recentDetections as $entry): ?>
                <?php
                  $displayMessage = redactCredentials((string) $entry['message'], $isAdmin);
                  $displayDetected = redactCredentials((string) $entry['detected'], $isAdmin);
                  $displayContext = redactCredentials((string) $entry['full_context'], $isAdmin);
                ?>
                <details class="report-card">
                  <summary class="investigable js-investigate" data-investigable="true" data-domain="<?= htmlspecialchars(isValidDomain($entry['hostname']) ? $entry['hostname'] : '', ENT_QUOTES, 'UTF-8'); ?>" data-url="<?= htmlspecialchars($entry['url'], ENT_QUOTES, 'UTF-8'); ?>" data-context="detection" data-timestamp="<?= htmlspecialchars($entry['timestamp'], ENT_QUOTES, 'UTF-8'); ?>">
                    <?= htmlspecialchars($entry['hostname'], ENT_QUOTES, 'UTF-8'); ?>
                    <?php if ($entry['blocked']): ?>
                      <span class="badge badge-blocked"><?= htmlspecialchars(t($translations, $currentLanguage, 'blocked'), ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php endif; ?>
                    <?php if ($entry['accepted']): ?>
                      <span class="badge"><?= htmlspecialchars(t($translations, $currentLanguage, 'accepted'), ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php endif; ?>
                    <span class="status-pill <?= htmlspecialchars($entry['review_class'], ENT_QUOTES, 'UTF-8'); ?>">
                      <?= htmlspecialchars($entry['review_label'], ENT_QUOTES, 'UTF-8'); ?>
                    </span>
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
                  <div class="report-section">
                    <strong><?= htmlspecialchars(t($translations, $currentLanguage, 'review_status'), ENT_QUOTES, 'UTF-8'); ?></strong>
                    <div class="muted"><?= htmlspecialchars($entry['review_label'], ENT_QUOTES, 'UTF-8'); ?></div>
                  </div>
                  <?php if (!empty($entry['reviewed_at'])): ?>
                    <div class="report-section">
                      <strong><?= htmlspecialchars(t($translations, $currentLanguage, 'reviewed_at'), ENT_QUOTES, 'UTF-8'); ?></strong>
                      <div class="muted"><?= htmlspecialchars($entry['reviewed_at'], ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>
                  <?php endif; ?>
                  <?php if (!empty($entry['message'])): ?>
                    <div class="report-section">
                      <strong><?= htmlspecialchars(t($translations, $currentLanguage, 'summary'), ENT_QUOTES, 'UTF-8'); ?></strong>
                      <div class="muted"><?= htmlspecialchars($displayMessage, ENT_QUOTES, 'UTF-8'); ?></div>
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
                      <pre><?= htmlspecialchars($displayDetected, ENT_QUOTES, 'UTF-8'); ?></pre>
                    </div>
                  <?php endif; ?>
                  <?php if (!empty($entry['full_context'])): ?>
                    <div class="report-section context-panel">
                      <strong><?= htmlspecialchars(t($translations, $currentLanguage, 'full_context'), ENT_QUOTES, 'UTF-8'); ?></strong>
                      <pre><?= htmlspecialchars($displayContext, ENT_QUOTES, 'UTF-8'); ?></pre>
                    </div>
                  <?php endif; ?>
                  <?php if ($isAdmin && !$entry['accepted']): ?>
                    <form method="post" class="form-actions js-ajax" style="margin-top: 12px;" data-refresh-target="#recent-detections-section">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                      <input type="hidden" name="action" value="accept_detection" />
                      <input type="hidden" name="detection_id" value="<?= (int) $entry['id']; ?>" />
                      <button class="button-primary" type="submit"><?= htmlspecialchars(t($translations, $currentLanguage, 'mark_accepted'), ENT_QUOTES, 'UTF-8'); ?></button>
                    </form>
                  <?php endif; ?>
                  <?php if ($isAdmin): ?>
                    <form method="post" class="form-actions js-ajax" style="margin-top: 12px;" data-refresh-target="#recent-detections-section">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                      <input type="hidden" name="action" value="review_detection" />
                      <input type="hidden" name="detection_id" value="<?= (int) $entry['id']; ?>" />
                      <button class="button-secondary" type="submit" name="decision" value="allow"><?= htmlspecialchars(t($translations, $currentLanguage, 'review_allow'), ENT_QUOTES, 'UTF-8'); ?></button>
                      <button class="button-primary" type="submit" name="decision" value="block"><?= htmlspecialchars(t($translations, $currentLanguage, 'review_block'), ENT_QUOTES, 'UTF-8'); ?></button>
                      <button class="button-ghost" type="submit" name="decision" value="investigate"><?= htmlspecialchars(t($translations, $currentLanguage, 'review_investigate'), ENT_QUOTES, 'UTF-8'); ?></button>
                      <span class="muted"><?= htmlspecialchars(t($translations, $currentLanguage, 'review_actions'), ENT_QUOTES, 'UTF-8'); ?></span>
                    </form>
                  <?php endif; ?>
                  <?php if ($isAdmin): ?>
                    <form method="post" class="form-actions js-ajax" style="margin-top: 12px;" data-refresh-target="#recent-detections-section">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                      <input type="hidden" name="action" value="delete_detection" />
                      <input type="hidden" name="detection_id" value="<?= (int) $entry['id']; ?>" />
                      <button class="button-secondary" type="submit" onclick="return confirm('<?= htmlspecialchars(t($translations, $currentLanguage, 'confirm_delete'), ENT_QUOTES, 'UTF-8'); ?>');">
                        <?= htmlspecialchars(t($translations, $currentLanguage, 'delete_detection'), ENT_QUOTES, 'UTF-8'); ?>
                      </button>
                    </form>
                  <?php endif; ?>
                </details>
              <?php endforeach; ?>
            <?php endif; ?>
          </section>

          <?php if ($isAdmin): ?>
            <section class="card workspace-section" style="margin-top: 24px;" data-workspace-section="alerts">
              <h2><?= htmlspecialchars(t($translations, $currentLanguage, 'appeal_title'), ENT_QUOTES, 'UTF-8'); ?></h2>
              <form method="post" class="form-grid js-ajax" data-reset="true">
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
          <?php endif; ?>

          <?php if ($isAdmin): ?>
            <section class="card workspace-section" style="margin-top: 24px;" id="recent-appeals-section" data-workspace-section="alerts" data-live-section="recent-appeals">
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
                    <form method="post" class="form-grid js-ajax" style="margin-top: 12px;" data-refresh-target="#recent-appeals-section">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                      <input type="hidden" name="action" value="appeal_update" />
                      <input type="hidden" name="appeal_id" value="<?= (int) ($appeal['id'] ?? 0); ?>" />
                      <label>
                        <?= htmlspecialchars(t($translations, $currentLanguage, 'domain'), ENT_QUOTES, 'UTF-8'); ?>
                        <input type="text" name="domain" value="<?= htmlspecialchars((string) ($appeal['domain'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required />
                      </label>
                      <label>
                        <?= htmlspecialchars(t($translations, $currentLanguage, 'reason'), ENT_QUOTES, 'UTF-8'); ?>
                        <textarea name="reason" required><?= htmlspecialchars((string) ($appeal['reason'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                      </label>
                      <label>
                        <?= htmlspecialchars(t($translations, $currentLanguage, 'contact_optional'), ENT_QUOTES, 'UTF-8'); ?>
                        <input type="text" name="contact" value="<?= htmlspecialchars((string) ($appeal['contact'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                      </label>
                      <div class="form-actions">
                        <button class="button-secondary" type="submit"><?= htmlspecialchars(t($translations, $currentLanguage, 'update_request'), ENT_QUOTES, 'UTF-8'); ?></button>
                      </div>
                    </form>
                    <form method="post" class="form-actions js-ajax" style="margin-top: 12px;" data-refresh-target="#recent-appeals-section">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                      <input type="hidden" name="action" value="appeal_delete" />
                      <input type="hidden" name="appeal_id" value="<?= (int) ($appeal['id'] ?? 0); ?>" />
                      <button class="button-secondary" type="submit" onclick="return confirm('<?= htmlspecialchars(t($translations, $currentLanguage, 'confirm_delete'), ENT_QUOTES, 'UTF-8'); ?>');">
                        <?= htmlspecialchars(t($translations, $currentLanguage, 'delete_request'), ENT_QUOTES, 'UTF-8'); ?>
                      </button>
                    </form>
                    <?php if (!in_array((string) ($appeal['status'] ?? ''), ['approved', 'rejected'], true)): ?>
                      <form method="post" class="form-actions js-ajax" style="margin-top: 12px;" data-refresh-target="#recent-appeals-section">
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

          <section class="card workspace-section" style="margin-top: 24px;" id="alert-analytics-section" data-workspace-section="alerts" data-live-section="alert-analytics">
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
                <h3><?= htmlspecialchars(t($translations, $currentLanguage, 'blocks_by_day'), ENT_QUOTES, 'UTF-8'); ?></h3>
                <canvas id="chart-blocks-day" height="140"></canvas>
              </div>
              <div class="chart-card">
                <h3><?= htmlspecialchars(t($translations, $currentLanguage, 'alerts_baseline'), ENT_QUOTES, 'UTF-8'); ?></h3>
                <canvas id="chart-alerts-baseline" height="140"></canvas>
              </div>
              <div class="chart-card">
                <h3><?= htmlspecialchars(t($translations, $currentLanguage, 'block_rate_trend'), ENT_QUOTES, 'UTF-8'); ?></h3>
                <canvas id="chart-block-rate" height="140"></canvas>
              </div>
              <div class="chart-card">
                <h3><?= htmlspecialchars(t($translations, $currentLanguage, 'alerts_by_hour'), ENT_QUOTES, 'UTF-8'); ?></h3>
                <canvas id="chart-alerts-hour" height="140"></canvas>
              </div>
              <div class="chart-card">
                <h3><?= htmlspecialchars(t($translations, $currentLanguage, 'blocks_by_hour'), ENT_QUOTES, 'UTF-8'); ?></h3>
                <canvas id="chart-blocks-hour" height="140"></canvas>
              </div>
              <div class="chart-card">
                <h3><?= htmlspecialchars(t($translations, $currentLanguage, 'country_distribution'), ENT_QUOTES, 'UTF-8'); ?></h3>
                <canvas id="chart-alerts-country" height="140"></canvas>
              </div>
              <div class="chart-card">
                <h3><?= htmlspecialchars(t($translations, $currentLanguage, 'os_breakdown'), ENT_QUOTES, 'UTF-8'); ?></h3>
                <canvas id="chart-os" height="140"></canvas>
              </div>
              <div class="chart-card">
                <h3><?= htmlspecialchars(t($translations, $currentLanguage, 'browser_breakdown'), ENT_QUOTES, 'UTF-8'); ?></h3>
                <canvas id="chart-browsers" height="140"></canvas>
              </div>
              <div class="chart-card">
                <h3><?= htmlspecialchars(t($translations, $currentLanguage, 'signal_types'), ENT_QUOTES, 'UTF-8'); ?></h3>
                <canvas id="chart-alerts-signals" height="140"></canvas>
              </div>
              <div class="chart-card">
                <h3><?= htmlspecialchars(t($translations, $currentLanguage, 'review_status_chart'), ENT_QUOTES, 'UTF-8'); ?></h3>
                <canvas id="chart-review-status" height="140"></canvas>
              </div>
              <div class="chart-card">
                <h3><?= htmlspecialchars(t($translations, $currentLanguage, 'top_hosts_chart'), ENT_QUOTES, 'UTF-8'); ?></h3>
                <canvas id="chart-top-hosts" height="140"></canvas>
              </div>
              <div class="chart-card">
                <h3><?= htmlspecialchars(t($translations, $currentLanguage, 'install_channel_chart'), ENT_QUOTES, 'UTF-8'); ?></h3>
                <canvas id="chart-install-channels" height="140"></canvas>
              </div>
            </div>
          </section>

          <section class="card workspace-section" style="margin-top: 24px;" id="recent-detections-section" data-workspace-section="alerts" data-live-section="recent-detections">
            <div class="section-title">
              <h2><?= htmlspecialchars(t($translations, $currentLanguage, 'recent_detections'), ENT_QUOTES, 'UTF-8'); ?></h2>
              <?php if ($isAdmin): ?>
                <form method="post" class="form-actions js-ajax" data-refresh-target="#recent-detections-section">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                  <input type="hidden" name="action" value="clear_unaccepted" />
                  <button class="button-secondary" type="submit" onclick="return confirm('<?= htmlspecialchars(t($translations, $currentLanguage, 'confirm_clear'), ENT_QUOTES, 'UTF-8'); ?>');">
                    <?= htmlspecialchars(t($translations, $currentLanguage, 'clear_unaccepted'), ENT_QUOTES, 'UTF-8'); ?>
                  </button>
                  <span class="muted"><?= htmlspecialchars(t($translations, $currentLanguage, 'clear_unaccepted_help'), ENT_QUOTES, 'UTF-8'); ?></span>
                </form>
                <form method="post" class="form-actions js-ajax" data-refresh-target="#recent-detections-section">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                  <input type="hidden" name="action" value="clear_accepted" />
                  <button class="button-secondary" type="submit" onclick="return confirm('<?= htmlspecialchars(t($translations, $currentLanguage, 'confirm_clear_accepted'), ENT_QUOTES, 'UTF-8'); ?>');">
                    <?= htmlspecialchars(t($translations, $currentLanguage, 'clear_accepted'), ENT_QUOTES, 'UTF-8'); ?>
                  </button>
                  <span class="muted"><?= htmlspecialchars(t($translations, $currentLanguage, 'clear_accepted_help'), ENT_QUOTES, 'UTF-8'); ?></span>
                </form>
              <?php endif; ?>
            </div>
            <?php if (empty($recentDetections)): ?>
              <div class="muted"><?= htmlspecialchars(t($translations, $currentLanguage, 'no_detections'), ENT_QUOTES, 'UTF-8'); ?></div>
            <?php else: ?>
              <?php foreach ($recentDetections as $entry): ?>
                <?php
                  $displayMessage = redactCredentials((string) $entry['message'], $isAdmin);
                  $displayDetected = redactCredentials((string) $entry['detected'], $isAdmin);
                  $displayContext = redactCredentials((string) $entry['full_context'], $isAdmin);
                ?>
                <details class="report-card">
                  <summary class="investigable js-investigate" data-investigable="true" data-domain="<?= htmlspecialchars(isValidDomain($entry['hostname']) ? $entry['hostname'] : '', ENT_QUOTES, 'UTF-8'); ?>" data-url="<?= htmlspecialchars($entry['url'], ENT_QUOTES, 'UTF-8'); ?>" data-context="detection" data-timestamp="<?= htmlspecialchars($entry['timestamp'], ENT_QUOTES, 'UTF-8'); ?>">
                    <?= htmlspecialchars($entry['hostname'], ENT_QUOTES, 'UTF-8'); ?>
                    <?php if ($entry['blocked']): ?>
                      <span class="badge badge-blocked"><?= htmlspecialchars(t($translations, $currentLanguage, 'blocked'), ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php endif; ?>
                    <?php if ($entry['accepted']): ?>
                      <span class="badge"><?= htmlspecialchars(t($translations, $currentLanguage, 'accepted'), ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php endif; ?>
                    <span class="status-pill <?= htmlspecialchars($entry['review_class'], ENT_QUOTES, 'UTF-8'); ?>">
                      <?= htmlspecialchars($entry['review_label'], ENT_QUOTES, 'UTF-8'); ?>
                    </span>
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
                  <div class="report-section">
                    <strong><?= htmlspecialchars(t($translations, $currentLanguage, 'review_status'), ENT_QUOTES, 'UTF-8'); ?></strong>
                    <div class="muted"><?= htmlspecialchars($entry['review_label'], ENT_QUOTES, 'UTF-8'); ?></div>
                  </div>
                  <?php if (!empty($entry['reviewed_at'])): ?>
                    <div class="report-section">
                      <strong><?= htmlspecialchars(t($translations, $currentLanguage, 'reviewed_at'), ENT_QUOTES, 'UTF-8'); ?></strong>
                      <div class="muted"><?= htmlspecialchars($entry['reviewed_at'], ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>
                  <?php endif; ?>
                  <?php if (!empty($entry['message'])): ?>
                    <div class="report-section">
                      <strong><?= htmlspecialchars(t($translations, $currentLanguage, 'summary'), ENT_QUOTES, 'UTF-8'); ?></strong>
                      <div class="muted"><?= htmlspecialchars($displayMessage, ENT_QUOTES, 'UTF-8'); ?></div>
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
                      <pre><?= htmlspecialchars($displayDetected, ENT_QUOTES, 'UTF-8'); ?></pre>
                    </div>
                  <?php endif; ?>
                  <?php if (!empty($entry['full_context'])): ?>
                    <div class="report-section context-panel">
                      <strong><?= htmlspecialchars(t($translations, $currentLanguage, 'full_context'), ENT_QUOTES, 'UTF-8'); ?></strong>
                      <pre><?= htmlspecialchars($displayContext, ENT_QUOTES, 'UTF-8'); ?></pre>
                    </div>
                  <?php endif; ?>
                  <?php if ($isAdmin && !$entry['accepted']): ?>
                    <form method="post" class="form-actions js-ajax" style="margin-top: 12px;" data-refresh-target="#recent-detections-section">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                      <input type="hidden" name="action" value="accept_detection" />
                      <input type="hidden" name="detection_id" value="<?= (int) $entry['id']; ?>" />
                      <button class="button-primary" type="submit"><?= htmlspecialchars(t($translations, $currentLanguage, 'mark_accepted'), ENT_QUOTES, 'UTF-8'); ?></button>
                    </form>
                  <?php endif; ?>
                  <?php if ($isAdmin): ?>
                    <form method="post" class="form-actions js-ajax" style="margin-top: 12px;" data-refresh-target="#recent-detections-section">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                      <input type="hidden" name="action" value="review_detection" />
                      <input type="hidden" name="detection_id" value="<?= (int) $entry['id']; ?>" />
                      <button class="button-secondary" type="submit" name="decision" value="allow"><?= htmlspecialchars(t($translations, $currentLanguage, 'review_allow'), ENT_QUOTES, 'UTF-8'); ?></button>
                      <button class="button-primary" type="submit" name="decision" value="block"><?= htmlspecialchars(t($translations, $currentLanguage, 'review_block'), ENT_QUOTES, 'UTF-8'); ?></button>
                      <button class="button-ghost" type="submit" name="decision" value="investigate"><?= htmlspecialchars(t($translations, $currentLanguage, 'review_investigate'), ENT_QUOTES, 'UTF-8'); ?></button>
                      <span class="muted"><?= htmlspecialchars(t($translations, $currentLanguage, 'review_actions'), ENT_QUOTES, 'UTF-8'); ?></span>
                    </form>
                  <?php endif; ?>
                  <?php if ($isAdmin): ?>
                    <form method="post" class="form-actions js-ajax" style="margin-top: 12px;" data-refresh-target="#recent-detections-section">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                      <input type="hidden" name="action" value="delete_detection" />
                      <input type="hidden" name="detection_id" value="<?= (int) $entry['id']; ?>" />
                      <button class="button-secondary" type="submit" onclick="return confirm('<?= htmlspecialchars(t($translations, $currentLanguage, 'confirm_delete'), ENT_QUOTES, 'UTF-8'); ?>');">
                        <?= htmlspecialchars(t($translations, $currentLanguage, 'delete_detection'), ENT_QUOTES, 'UTF-8'); ?>
                      </button>
                    </form>
                  <?php endif; ?>
                </details>
              <?php endforeach; ?>
            <?php endif; ?>
          </section>

          <?php if ($isAdmin): ?>
            <section class="card workspace-section" style="margin-top: 24px;" data-workspace-section="alerts">
              <h2><?= htmlspecialchars(t($translations, $currentLanguage, 'appeal_title'), ENT_QUOTES, 'UTF-8'); ?></h2>
              <form method="post" class="form-grid js-ajax" data-reset="true">
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
          <?php endif; ?>

          <?php if ($isAdmin): ?>
            <section class="card workspace-section" style="margin-top: 24px;" id="recent-appeals-section" data-workspace-section="alerts" data-live-section="recent-appeals">
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
                    <form method="post" class="form-grid js-ajax" style="margin-top: 12px;" data-refresh-target="#recent-appeals-section">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                      <input type="hidden" name="action" value="appeal_update" />
                      <input type="hidden" name="appeal_id" value="<?= (int) ($appeal['id'] ?? 0); ?>" />
                      <label>
                        <?= htmlspecialchars(t($translations, $currentLanguage, 'domain'), ENT_QUOTES, 'UTF-8'); ?>
                        <input type="text" name="domain" value="<?= htmlspecialchars((string) ($appeal['domain'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required />
                      </label>
                      <label>
                        <?= htmlspecialchars(t($translations, $currentLanguage, 'reason'), ENT_QUOTES, 'UTF-8'); ?>
                        <textarea name="reason" required><?= htmlspecialchars((string) ($appeal['reason'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                      </label>
                      <label>
                        <?= htmlspecialchars(t($translations, $currentLanguage, 'contact_optional'), ENT_QUOTES, 'UTF-8'); ?>
                        <input type="text" name="contact" value="<?= htmlspecialchars((string) ($appeal['contact'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                      </label>
                      <div class="form-actions">
                        <button class="button-secondary" type="submit"><?= htmlspecialchars(t($translations, $currentLanguage, 'update_request'), ENT_QUOTES, 'UTF-8'); ?></button>
                      </div>
                    </form>
                    <form method="post" class="form-actions js-ajax" style="margin-top: 12px;" data-refresh-target="#recent-appeals-section">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                      <input type="hidden" name="action" value="appeal_delete" />
                      <input type="hidden" name="appeal_id" value="<?= (int) ($appeal['id'] ?? 0); ?>" />
                      <button class="button-secondary" type="submit" onclick="return confirm('<?= htmlspecialchars(t($translations, $currentLanguage, 'confirm_delete'), ENT_QUOTES, 'UTF-8'); ?>');">
                        <?= htmlspecialchars(t($translations, $currentLanguage, 'delete_request'), ENT_QUOTES, 'UTF-8'); ?>
                      </button>
                    </form>
                    <?php if (!in_array((string) ($appeal['status'] ?? ''), ['approved', 'rejected'], true)): ?>
                      <form method="post" class="form-actions js-ajax" style="margin-top: 12px;" data-refresh-target="#recent-appeals-section">
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

          <section class="card workspace-section" style="margin-top: 24px;" id="public-lists-section" data-workspace-section="lists" data-live-section="public-lists">
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
                  <div class="list-block list-domains">
                    <ul>
                      <?php foreach ($allowlistItems as $domain): ?>
                        <li>
                          <button type="button" class="list-item js-selectable js-investigate"
                            data-domain="<?= htmlspecialchars($domain, ENT_QUOTES, 'UTF-8'); ?>"
                            data-url="https://<?= htmlspecialchars($domain, ENT_QUOTES, 'UTF-8'); ?>"
                            data-list="allow"
                            data-context="allowlist">
                            <?= htmlspecialchars($domain, ENT_QUOTES, 'UTF-8'); ?>
                          </button>
                        </li>
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
                  <div class="list-block list-domains">
                    <ul>
                      <?php foreach ($blocklistItems as $domain): ?>
                        <li>
                          <button type="button" class="list-item js-selectable js-investigate"
                            data-domain="<?= htmlspecialchars($domain, ENT_QUOTES, 'UTF-8'); ?>"
                            data-url="https://<?= htmlspecialchars($domain, ENT_QUOTES, 'UTF-8'); ?>"
                            data-list="block"
                            data-context="blocklist">
                            <?= htmlspecialchars($domain, ENT_QUOTES, 'UTF-8'); ?>
                          </button>
                        </li>
                      <?php endforeach; ?>
                    </ul>
                  </div>
                <?php endif; ?>
              </div>
              <div>
                <h3><?= htmlspecialchars(t($translations, $currentLanguage, 'alertlist'), ENT_QUOTES, 'UTF-8'); ?></h3>
                <?php if (empty($alertlistItems)): ?>
                  <div class="muted"><?= htmlspecialchars(t($translations, $currentLanguage, 'empty_domains'), ENT_QUOTES, 'UTF-8'); ?></div>
                <?php else: ?>
                  <div class="list-block list-domains">
                    <ul>
                      <?php foreach ($alertlistItems as $domain): ?>
                        <li>
                          <button type="button" class="list-item js-selectable js-investigate"
                            data-domain="<?= htmlspecialchars($domain, ENT_QUOTES, 'UTF-8'); ?>"
                            data-url="https://<?= htmlspecialchars($domain, ENT_QUOTES, 'UTF-8'); ?>"
                            data-list="alert"
                            data-context="alertlist">
                            <?= htmlspecialchars($domain, ENT_QUOTES, 'UTF-8'); ?>
                          </button>
                        </li>
                      <?php endforeach; ?>
                    </ul>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          </section>

          <?php if ($isAdmin): ?>
            <section class="card admin-panel workspace-section" style="margin-top: 24px;" id="bulk-list-actions" data-workspace-section="lists">
              <div class="section-title">
                <h2><?= htmlspecialchars(t($translations, $currentLanguage, 'list_bulk_actions'), ENT_QUOTES, 'UTF-8'); ?></h2>
                <span class="muted"><?= htmlspecialchars(t($translations, $currentLanguage, 'list_bulk_help'), ENT_QUOTES, 'UTF-8'); ?></span>
              </div>
              <form method="post" class="form-grid js-ajax" data-refresh-target="#public-lists-section" id="bulk-list-form">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                <input type="hidden" name="action" value="list_bulk_action" />
                <input type="hidden" name="operation" value="" />
                <input type="hidden" name="target_list" value="" />
                <input type="hidden" name="domains_json" value="" />
                <label>
                  <?= htmlspecialchars(t($translations, $currentLanguage, 'bulk_reason_optional'), ENT_QUOTES, 'UTF-8'); ?>
                  <textarea name="reason"></textarea>
                </label>
                <div class="action-grid">
                  <div class="action-block">
                    <button class="button-secondary" type="button" data-bulk-action="remove">
                      <?= htmlspecialchars(t($translations, $currentLanguage, 'bulk_remove'), ENT_QUOTES, 'UTF-8'); ?>
                    </button>
                    <sub><?= htmlspecialchars(t($translations, $currentLanguage, 'action_remove_desc'), ENT_QUOTES, 'UTF-8'); ?></sub>
                  </div>
                  <div class="action-block">
                    <button class="button-secondary" type="button" data-bulk-action="move" data-target-list="allow">
                      <?= htmlspecialchars(t($translations, $currentLanguage, 'bulk_move_allow'), ENT_QUOTES, 'UTF-8'); ?>
                    </button>
                    <sub><?= htmlspecialchars(t($translations, $currentLanguage, 'action_move_allow_desc'), ENT_QUOTES, 'UTF-8'); ?></sub>
                  </div>
                  <div class="action-block">
                    <button class="button-primary" type="button" data-bulk-action="move" data-target-list="block">
                      <?= htmlspecialchars(t($translations, $currentLanguage, 'bulk_move_block'), ENT_QUOTES, 'UTF-8'); ?>
                    </button>
                    <sub><?= htmlspecialchars(t($translations, $currentLanguage, 'action_move_block_desc'), ENT_QUOTES, 'UTF-8'); ?></sub>
                  </div>
                  <div class="action-block">
                    <button class="button-ghost" type="button" data-bulk-action="move" data-target-list="alert">
                      <?= htmlspecialchars(t($translations, $currentLanguage, 'bulk_move_alert'), ENT_QUOTES, 'UTF-8'); ?>
                    </button>
                    <sub><?= htmlspecialchars(t($translations, $currentLanguage, 'action_move_alert_desc'), ENT_QUOTES, 'UTF-8'); ?></sub>
                  </div>
                  <div class="action-block">
                    <button class="button-ghost" type="button" data-bulk-action="clear">
                      <?= htmlspecialchars(t($translations, $currentLanguage, 'bulk_clear'), ENT_QUOTES, 'UTF-8'); ?>
                    </button>
                    <sub><?= htmlspecialchars(t($translations, $currentLanguage, 'action_clear_desc'), ENT_QUOTES, 'UTF-8'); ?></sub>
                  </div>
                </div>
                <div class="selection-meta">
                  <span><?= htmlspecialchars(t($translations, $currentLanguage, 'bulk_selected'), ENT_QUOTES, 'UTF-8'); ?>:</span>
                  <strong id="selected-count">0</strong>
                </div>
              </form>
            </section>
          <?php endif; ?>

          <?php if ($isAdmin): ?>
            <section class="card admin-panel workspace-section" style="margin-top: 24px;" data-workspace-section="lists">
              <details>
                <summary class="section-title">
                  <h2><?= htmlspecialchars(t($translations, $currentLanguage, 'admin_lists'), ENT_QUOTES, 'UTF-8'); ?></h2>
                  <span class="muted"><?= htmlspecialchars(t($translations, $currentLanguage, 'admin_only'), ENT_QUOTES, 'UTF-8'); ?></span>
                </summary>
                <form method="post" class="form-grid js-ajax" style="margin-top: 12px;" data-refresh-target="#public-lists-section" data-reset="true">
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
                      <option value="alert"><?= htmlspecialchars(t($translations, $currentLanguage, 'alertlist'), ENT_QUOTES, 'UTF-8'); ?></option>
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
          <?php endif; ?>

          <?php if ($isAdmin): ?>
            <section class="card admin-panel workspace-section" style="margin-top: 24px;" id="suggestions-section" data-workspace-section="lists" data-live-section="list-suggestions">
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
                      <?php
                        $suggestionType = (string) ($suggestion['list_type'] ?? '');
                        if ($suggestionType === 'allow') {
                            $suggestionTypeLabel = t($translations, $currentLanguage, 'allowlist');
                        } elseif ($suggestionType === 'block') {
                            $suggestionTypeLabel = t($translations, $currentLanguage, 'blocklist');
                        } else {
                            $suggestionTypeLabel = t($translations, $currentLanguage, 'alertlist');
                        }
                      ?>
                      <div class="muted"><?= htmlspecialchars($suggestionTypeLabel, ENT_QUOTES, 'UTF-8'); ?></div>
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
                    <form method="post" class="form-grid js-ajax" style="margin-top: 12px;" data-refresh-target="#suggestions-section">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                      <input type="hidden" name="action" value="list_suggestion_edit" />
                      <input type="hidden" name="suggestion_id" value="<?= (int) ($suggestion['id'] ?? 0); ?>" />
                      <label>
                        <?= htmlspecialchars(t($translations, $currentLanguage, 'domain'), ENT_QUOTES, 'UTF-8'); ?>
                        <input type="text" name="domain" value="<?= htmlspecialchars((string) ($suggestion['domain'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required />
                      </label>
                      <label>
                        <?= htmlspecialchars(t($translations, $currentLanguage, 'list_type'), ENT_QUOTES, 'UTF-8'); ?>
                        <select name="list_type">
                          <option value="allow" <?= ($suggestion['list_type'] ?? '') === 'allow' ? 'selected' : ''; ?>>
                            <?= htmlspecialchars(t($translations, $currentLanguage, 'allowlist'), ENT_QUOTES, 'UTF-8'); ?>
                          </option>
                          <option value="block" <?= ($suggestion['list_type'] ?? '') === 'block' ? 'selected' : ''; ?>>
                            <?= htmlspecialchars(t($translations, $currentLanguage, 'blocklist'), ENT_QUOTES, 'UTF-8'); ?>
                          </option>
                          <option value="alert" <?= ($suggestion['list_type'] ?? '') === 'alert' ? 'selected' : ''; ?>>
                            <?= htmlspecialchars(t($translations, $currentLanguage, 'alertlist'), ENT_QUOTES, 'UTF-8'); ?>
                          </option>
                        </select>
                      </label>
                      <label>
                        <?= htmlspecialchars(t($translations, $currentLanguage, 'reason'), ENT_QUOTES, 'UTF-8'); ?>
                        <textarea name="reason" required><?= htmlspecialchars((string) ($suggestion['reason'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                      </label>
                      <div class="form-actions">
                        <button class="button-secondary" type="submit"><?= htmlspecialchars(t($translations, $currentLanguage, 'update_request'), ENT_QUOTES, 'UTF-8'); ?></button>
                      </div>
                    </form>
                    <form method="post" class="form-actions js-ajax" style="margin-top: 12px;" data-refresh-target="#suggestions-section">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                      <input type="hidden" name="action" value="list_suggestion_delete" />
                      <input type="hidden" name="suggestion_id" value="<?= (int) ($suggestion['id'] ?? 0); ?>" />
                      <button class="button-secondary" type="submit" onclick="return confirm('<?= htmlspecialchars(t($translations, $currentLanguage, 'confirm_delete'), ENT_QUOTES, 'UTF-8'); ?>');">
                        <?= htmlspecialchars(t($translations, $currentLanguage, 'delete_request'), ENT_QUOTES, 'UTF-8'); ?>
                      </button>
                    </form>
                    <?php if (($suggestion['status'] ?? '') === 'pending'): ?>
                      <form method="post" class="form-actions js-ajax" style="margin-top: 12px;" data-refresh-target="#suggestions-section">
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

          <section class="grid workspace-section" style="margin-top: 24px;" data-workspace-section="alerts">
            <?php if ($isAdmin): ?>
              <section class="card">
                <h2><?= htmlspecialchars(t($translations, $currentLanguage, 'quick_guide'), ENT_QUOTES, 'UTF-8'); ?></h2>
                <ul>
                  <li><?= htmlspecialchars(t($translations, $currentLanguage, 'guide_item_1'), ENT_QUOTES, 'UTF-8'); ?></li>
                  <li><?= htmlspecialchars(t($translations, $currentLanguage, 'guide_item_2'), ENT_QUOTES, 'UTF-8'); ?></li>
                  <li><?= htmlspecialchars(t($translations, $currentLanguage, 'guide_item_3'), ENT_QUOTES, 'UTF-8'); ?></li>
                  <li><?= htmlspecialchars(t($translations, $currentLanguage, 'guide_item_4'), ENT_QUOTES, 'UTF-8'); ?></li>
                </ul>
              </section>
            <?php endif; ?>
            <?php if ($isAdmin || $isAnalyst): ?>
              <section class="card" data-live-section="countries">
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
              <section class="card" data-live-section="os-breakdown">
                <h2><?= htmlspecialchars(t($translations, $currentLanguage, 'os_breakdown'), ENT_QUOTES, 'UTF-8'); ?></h2>
                <span class="muted"><?= htmlspecialchars(t($translations, $currentLanguage, 'last_24h'), ENT_QUOTES, 'UTF-8'); ?></span>
                <?php if (empty($stats['os_stats'])): ?>
                  <div class="muted"><?= htmlspecialchars(t($translations, $currentLanguage, 'no_data'), ENT_QUOTES, 'UTF-8'); ?></div>
                <?php else: ?>
                  <div class="list-block">
                    <ul>
                      <?php foreach ($stats['os_stats'] as $os => $count): ?>
                        <li><?= htmlspecialchars($os, ENT_QUOTES, 'UTF-8'); ?>: <?= (int) $count; ?></li>
                      <?php endforeach; ?>
                    </ul>
                  </div>
                <?php endif; ?>
              </section>
              <section class="card" data-live-section="browser-breakdown">
                <h2><?= htmlspecialchars(t($translations, $currentLanguage, 'browser_breakdown'), ENT_QUOTES, 'UTF-8'); ?></h2>
                <span class="muted"><?= htmlspecialchars(t($translations, $currentLanguage, 'last_24h'), ENT_QUOTES, 'UTF-8'); ?></span>
                <?php if (empty($stats['browser_stats'])): ?>
                  <div class="muted"><?= htmlspecialchars(t($translations, $currentLanguage, 'no_data'), ENT_QUOTES, 'UTF-8'); ?></div>
                <?php else: ?>
                  <div class="list-block">
                    <ul>
                      <?php foreach ($stats['browser_stats'] as $browser => $count): ?>
                        <li><?= htmlspecialchars($browser, ENT_QUOTES, 'UTF-8'); ?>: <?= (int) $count; ?></li>
                      <?php endforeach; ?>
                    </ul>
                  </div>
                <?php endif; ?>
              </section>
            <?php endif; ?>
            <?php if ($isAdmin): ?>
              <section class="card">
                <h2><?= htmlspecialchars(t($translations, $currentLanguage, 'manual_sites_title'), ENT_QUOTES, 'UTF-8'); ?></h2>
                <?php if (empty($stats['manual_sites'])): ?>
                  <div class="muted"><?= htmlspecialchars(t($translations, $currentLanguage, 'no_data'), ENT_QUOTES, 'UTF-8'); ?></div>
                <?php else: ?>
                  <div class="chip-list">
                    <?php foreach ($stats['manual_sites'] as $site): ?>
                      <button type="button" class="chip chip-button js-investigate"
                        data-domain="<?= htmlspecialchars($site, ENT_QUOTES, 'UTF-8'); ?>"
                        data-url="https://<?= htmlspecialchars($site, ENT_QUOTES, 'UTF-8'); ?>"
                        data-context="manual">
                        <?= htmlspecialchars($site, ENT_QUOTES, 'UTF-8'); ?>
                      </button>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </section>
            <?php endif; ?>
            <?php if ($isAdmin): ?>
              <section class="card">
                <h2><?= htmlspecialchars(t($translations, $currentLanguage, 'alerted_sites_title'), ENT_QUOTES, 'UTF-8'); ?></h2>
                <?php if (empty($stats['alert_sites'])): ?>
                  <div class="muted"><?= htmlspecialchars(t($translations, $currentLanguage, 'no_data'), ENT_QUOTES, 'UTF-8'); ?></div>
                <?php else: ?>
                  <div class="list-block list-domains">
                    <ul>
                      <?php foreach ($stats['alert_sites'] as $site): ?>
                        <li>
                          <button type="button" class="list-item js-investigate"
                            data-domain="<?= htmlspecialchars($site, ENT_QUOTES, 'UTF-8'); ?>"
                            data-url="https://<?= htmlspecialchars($site, ENT_QUOTES, 'UTF-8'); ?>"
                            data-context="alerted">
                            <?= htmlspecialchars($site, ENT_QUOTES, 'UTF-8'); ?>
                          </button>
                        </li>
                      <?php endforeach; ?>
                    </ul>
                  </div>
                <?php endif; ?>
              </section>
            <?php endif; ?>
          </section>

          <section class="card workspace-section" style="margin-top: 24px;" id="intel-section" data-workspace-section="intel" data-live-section="intel">
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
            <div class="section-title" style="margin-top: 18px;">
              <h3><?= htmlspecialchars(t($translations, $currentLanguage, 'intel_focus'), ENT_QUOTES, 'UTF-8'); ?></h3>
              <span class="muted"><?= htmlspecialchars(t($translations, $currentLanguage, 'intel_focus_subtitle'), ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <div class="intel-focus-grid">
              <?php foreach ($intelFocusAreas as $area): ?>
                <div class="intel-focus-card">
                  <div class="meta-row">
                    <span class="chip"><?= htmlspecialchars((string) $area['icon'], ENT_QUOTES, 'UTF-8'); ?></span>
                    <h4><?= htmlspecialchars((string) $area['title'], ENT_QUOTES, 'UTF-8'); ?></h4>
                  </div>
                  <p><?= htmlspecialchars((string) $area['description'], ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
              <?php endforeach; ?>
            </div>
          </section>

          <section class="card workspace-section" style="margin-top: 24px;" id="access-section" data-workspace-section="access">
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
            <?php if ($currentUser): ?>
              <form method="post" class="form-actions" style="margin-top: 16px;">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                <input type="hidden" name="action" value="logout" />
                <button class="button-ghost" type="submit"><?= htmlspecialchars(t($translations, $currentLanguage, 'logout'), ENT_QUOTES, 'UTF-8'); ?></button>
              </form>
            <?php else: ?>
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
                    <input type="text" name="username" required autocomplete="username" autocapitalize="none" spellcheck="false" />
                  </label>
                  <label>
                    <?= htmlspecialchars(t($translations, $currentLanguage, 'password'), ENT_QUOTES, 'UTF-8'); ?>
                    <input type="password" name="password" required autocomplete="current-password" />
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
                    <input type="text" name="username" required autocomplete="username" autocapitalize="none" spellcheck="false" />
                  </label>
                  <label>
                    <?= htmlspecialchars(t($translations, $currentLanguage, 'password'), ENT_QUOTES, 'UTF-8'); ?>
                    <input type="password" name="password" required autocomplete="new-password" />
                  </label>
                  <label>
                    <?= htmlspecialchars(t($translations, $currentLanguage, 'admin_code_optional'), ENT_QUOTES, 'UTF-8'); ?>
                    <input type="text" name="admin_code" autocomplete="off" spellcheck="false" />
                  </label>
                  <div class="form-actions">
                    <button class="button-secondary" type="submit"><?= htmlspecialchars(t($translations, $currentLanguage, 'register'), ENT_QUOTES, 'UTF-8'); ?></button>
                  </div>
                </form>
              </details>
            <?php endif; ?>
          </section>

          <?php if ($isAdmin): ?>
            <section class="card admin-panel workspace-section" style="margin-top: 24px;" id="users-section" data-workspace-section="users">
              <div class="section-title">
                <h2><?= htmlspecialchars(t($translations, $currentLanguage, 'manage_users'), ENT_QUOTES, 'UTF-8'); ?></h2>
                <span class="muted"><?= htmlspecialchars(t($translations, $currentLanguage, 'manage_users_hint'), ENT_QUOTES, 'UTF-8'); ?></span>
              </div>
              <form method="post" class="form-grid js-ajax" style="margin-top: 12px;" data-refresh-target="#users-section" data-reset="true">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                <input type="hidden" name="action" value="user_create" />
                <label>
                  <?= htmlspecialchars(t($translations, $currentLanguage, 'username'), ENT_QUOTES, 'UTF-8'); ?>
                  <input type="text" name="username" required autocomplete="username" autocapitalize="none" spellcheck="false" />
                </label>
                <label>
                  <?= htmlspecialchars(t($translations, $currentLanguage, 'password'), ENT_QUOTES, 'UTF-8'); ?>
                  <input type="password" name="password" required autocomplete="new-password" />
                </label>
                <label>
                  <?= htmlspecialchars(t($translations, $currentLanguage, 'role'), ENT_QUOTES, 'UTF-8'); ?>
                  <select name="role">
                    <option value="analyst">Analyst</option>
                    <option value="admin">Admin</option>
                  </select>
                </label>
                <label>
                  <?= htmlspecialchars(t($translations, $currentLanguage, 'verified'), ENT_QUOTES, 'UTF-8'); ?>
                  <select name="verified">
                    <option value="1"><?= htmlspecialchars(t($translations, $currentLanguage, 'verified_account'), ENT_QUOTES, 'UTF-8'); ?></option>
                    <option value="0" selected><?= htmlspecialchars(t($translations, $currentLanguage, 'unverified_account'), ENT_QUOTES, 'UTF-8'); ?></option>
                  </select>
                </label>
                <div class="form-actions">
                  <button class="button-primary" type="submit"><?= htmlspecialchars(t($translations, $currentLanguage, 'create_user'), ENT_QUOTES, 'UTF-8'); ?></button>
                </div>
              </form>
              <div class="table-wrap" style="margin-top: 16px;">
                <table>
                  <thead>
                    <tr>
                      <th><?= htmlspecialchars(t($translations, $currentLanguage, 'username'), ENT_QUOTES, 'UTF-8'); ?></th>
                      <th><?= htmlspecialchars(t($translations, $currentLanguage, 'role'), ENT_QUOTES, 'UTF-8'); ?></th>
                      <th><?= htmlspecialchars(t($translations, $currentLanguage, 'verified'), ENT_QUOTES, 'UTF-8'); ?></th>
                      <th><?= htmlspecialchars(t($translations, $currentLanguage, 'actions'), ENT_QUOTES, 'UTF-8'); ?></th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($userRows as $userRow): ?>
                      <tr>
                        <td><?= htmlspecialchars((string) ($userRow['username'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td>
                          <form method="post" class="form-actions js-ajax" data-refresh-target="#users-section">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                            <input type="hidden" name="action" value="user_update" />
                            <input type="hidden" name="user_id" value="<?= (int) ($userRow['id'] ?? 0); ?>" />
                            <select name="role">
                              <option value="analyst" <?= ($userRow['role'] ?? '') === 'analyst' ? 'selected' : ''; ?>>Analyst</option>
                              <option value="admin" <?= ($userRow['role'] ?? '') === 'admin' ? 'selected' : ''; ?>>Admin</option>
                            </select>
                            <select name="verified">
                              <option value="1" <?= (int) ($userRow['verified'] ?? 0) === 1 ? 'selected' : ''; ?>>
                                <?= htmlspecialchars(t($translations, $currentLanguage, 'verified_account'), ENT_QUOTES, 'UTF-8'); ?>
                              </option>
                              <option value="0" <?= (int) ($userRow['verified'] ?? 0) === 0 ? 'selected' : ''; ?>>
                                <?= htmlspecialchars(t($translations, $currentLanguage, 'unverified_account'), ENT_QUOTES, 'UTF-8'); ?>
                              </option>
                            </select>
                            <input type="password" name="password" placeholder="<?= htmlspecialchars(t($translations, $currentLanguage, 'password_optional'), ENT_QUOTES, 'UTF-8'); ?>" />
                            <button class="button-secondary" type="submit"><?= htmlspecialchars(t($translations, $currentLanguage, 'save'), ENT_QUOTES, 'UTF-8'); ?></button>
                          </form>
                        </td>
                        <td><?= (int) ($userRow['verified'] ?? 0) === 1 ? 'OK' : 'Pending'; ?></td>
                        <td>
                          <form method="post" class="form-actions js-ajax" data-refresh-target="#users-section">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                            <input type="hidden" name="action" value="user_delete" />
                            <input type="hidden" name="user_id" value="<?= (int) ($userRow['id'] ?? 0); ?>" />
                            <button class="button-secondary" type="submit" onclick="return confirm('<?= htmlspecialchars(t($translations, $currentLanguage, 'confirm_delete_user'), ENT_QUOTES, 'UTF-8'); ?>');">
                              <?= htmlspecialchars(t($translations, $currentLanguage, 'delete'), ENT_QUOTES, 'UTF-8'); ?>
                            </button>
                          </form>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </section>
          <?php endif; ?>

          <?php if ($canViewLogs): ?>
            <section class="card workspace-section" style="margin-top: 24px;" id="logs-section" data-workspace-section="alerts" data-live-section="logs">
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
                            <?php
                              $displayMessage = redactCredentials((string) $entry['message'], $isAdmin);
                              $displayDetected = redactCredentials((string) $entry['detected_content'], $isAdmin);
                            ?>
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
                              <td><?= htmlspecialchars($displayMessage, ENT_QUOTES, 'UTF-8'); ?></td>
                              <td><?= htmlspecialchars($displayDetected, ENT_QUOTES, 'UTF-8'); ?></td>
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
                    <h3><?= htmlspecialchars(t($translations, $currentLanguage, 'telemetry_logs'), ENT_QUOTES, 'UTF-8'); ?></h3>
                    <span class="muted"><?= htmlspecialchars(t($translations, $currentLanguage, 'telemetry_subtitle'), ENT_QUOTES, 'UTF-8'); ?></span>
                  </div>
                  <?php if (empty($statsLogEntries)): ?>
                    <div class="muted"><?= htmlspecialchars(t($translations, $currentLanguage, 'no_logs'), ENT_QUOTES, 'UTF-8'); ?></div>
                  <?php else: ?>
                    <div class="table-wrap">
                      <table>
                        <thead>
                          <tr>
                            <th><?= htmlspecialchars(t($translations, $currentLanguage, 'log_col_time'), ENT_QUOTES, 'UTF-8'); ?></th>
                            <th><?= htmlspecialchars(t($translations, $currentLanguage, 'log_col_enabled'), ENT_QUOTES, 'UTF-8'); ?></th>
                            <th><?= htmlspecialchars(t($translations, $currentLanguage, 'log_col_alert_count'), ENT_QUOTES, 'UTF-8'); ?></th>
                            <th><?= htmlspecialchars(t($translations, $currentLanguage, 'log_col_block_count'), ENT_QUOTES, 'UTF-8'); ?></th>
                            <th><?= htmlspecialchars(t($translations, $currentLanguage, 'log_col_manual_sites'), ENT_QUOTES, 'UTF-8'); ?></th>
                            <th><?= htmlspecialchars(t($translations, $currentLanguage, 'log_col_alert_sites'), ENT_QUOTES, 'UTF-8'); ?></th>
                            <th><?= htmlspecialchars(t($translations, $currentLanguage, 'log_col_install_channel'), ENT_QUOTES, 'UTF-8'); ?></th>
                            <th><?= htmlspecialchars(t($translations, $currentLanguage, 'log_col_install_source'), ENT_QUOTES, 'UTF-8'); ?></th>
                            <th><?= htmlspecialchars(t($translations, $currentLanguage, 'log_col_install_type'), ENT_QUOTES, 'UTF-8'); ?></th>
                            <th><?= htmlspecialchars(t($translations, $currentLanguage, 'log_col_user_agent'), ENT_QUOTES, 'UTF-8'); ?></th>
                            <th><?= htmlspecialchars(t($translations, $currentLanguage, 'log_col_country'), ENT_QUOTES, 'UTF-8'); ?></th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php foreach ($statsLogEntries as $entry): ?>
                            <?php
                              $channelRaw = (string) $entry['install_channel'];
                              $channelLabel = resolveInstallChannelLabel($channelRaw, $translations, $currentLanguage);
                              $channelDisplay = $channelLabel !== '' ? $channelLabel : $channelRaw;
                              $uaRaw = (string) $entry['user_agent'];
                              $uaShort = $uaRaw !== '' ? safeSubstr($uaRaw, 0, 160) : '';
                              if ($uaRaw !== '' && strlen($uaRaw) > 160) {
                                  $uaShort .= '...';
                              }
                            ?>
                            <tr>
                              <td><?= htmlspecialchars($entry['received_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                              <td><?= htmlspecialchars($entry['enabled'] ? t($translations, $currentLanguage, 'log_value_enabled') : t($translations, $currentLanguage, 'log_value_disabled'), ENT_QUOTES, 'UTF-8'); ?></td>
                              <td><?= (int) $entry['alert_count']; ?></td>
                              <td><?= (int) $entry['block_count']; ?></td>
                              <td><?= (int) $entry['manual_sites_count']; ?></td>
                              <td><?= (int) $entry['alert_sites_count']; ?></td>
                              <td>
                                <?php if ($channelDisplay !== ''): ?>
                                  <span title="<?= htmlspecialchars($channelRaw, ENT_QUOTES, 'UTF-8'); ?>">
                                    <?= htmlspecialchars($channelDisplay, ENT_QUOTES, 'UTF-8'); ?>
                                  </span>
                                <?php endif; ?>
                              </td>
                              <td><?= htmlspecialchars((string) $entry['install_source'], ENT_QUOTES, 'UTF-8'); ?></td>
                              <td><?= htmlspecialchars((string) $entry['install_type'], ENT_QUOTES, 'UTF-8'); ?></td>
                              <td title="<?= htmlspecialchars($uaRaw, ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars($uaShort, ENT_QUOTES, 'UTF-8'); ?></td>
                              <td><?= htmlspecialchars((string) $entry['country'], ENT_QUOTES, 'UTF-8'); ?></td>
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
                  <?php if (empty($debugLogTable['rows'])): ?>
                    <div class="muted"><?= htmlspecialchars(t($translations, $currentLanguage, 'no_logs'), ENT_QUOTES, 'UTF-8'); ?></div>
                  <?php else: ?>
                    <div class="table-wrap">
                      <table>
                        <thead>
                          <tr>
                            <?php foreach ($debugLogTable['columns'] as $column): ?>
                              <th>
                                <?= htmlspecialchars($column === 'raw' ? t($translations, $currentLanguage, 'log_col_raw') : $column, ENT_QUOTES, 'UTF-8'); ?>
                              </th>
                            <?php endforeach; ?>
                          </tr>
                        </thead>
                        <tbody>
                          <?php foreach ($debugLogTable['rows'] as $row): ?>
                            <tr>
                              <?php foreach ($debugLogTable['columns'] as $column): ?>
                                <td><?= htmlspecialchars((string) ($row[$column] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                              <?php endforeach; ?>
                            </tr>
                          <?php endforeach; ?>
                        </tbody>
                      </table>
                    </div>
                  <?php endif; ?>
                </div>
              </div>
            </section>
          <?php endif; ?>

          <?php if ($isAdmin): ?>
            <section class="card workspace-section" style="margin-top: 24px;" id="data-section" data-workspace-section="data">
              <div class="section-title">
                <h2><?= htmlspecialchars(t($translations, $currentLanguage, 'data_vault_title'), ENT_QUOTES, 'UTF-8'); ?></h2>
                <span class="muted"><?= htmlspecialchars(t($translations, $currentLanguage, 'data_vault_subtitle'), ENT_QUOTES, 'UTF-8'); ?></span>
              </div>

              <?php if (empty($dataExplorer['tables'])): ?>
                <div class="muted"><?= htmlspecialchars(t($translations, $currentLanguage, 'data_table_empty'), ENT_QUOTES, 'UTF-8'); ?></div>
              <?php else: ?>
                <div class="grid data-summary" style="margin-top: 12px;">
                  <?php foreach ($dataExplorer['tables'] as $table): ?>
                    <div class="stat-card">
                      <span class="stat-label"><?= htmlspecialchars((string) ($table['label'] ?? $table['name']), ENT_QUOTES, 'UTF-8'); ?></span>
                      <div class="stat-value"><?= (int) ($table['count'] ?? 0); ?></div>
                      <span class="stat-footnote">
                        <?= htmlspecialchars(t($translations, $currentLanguage, 'data_table_last'), ENT_QUOTES, 'UTF-8'); ?>:
                        <?= htmlspecialchars((string) ($table['last_updated'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?>
                      </span>
                    </div>
                  <?php endforeach; ?>
                </div>

                <div class="data-table-stack" style="margin-top: 18px;">
                  <?php foreach ($dataExplorer['tables'] as $index => $table): ?>
                    <details class="data-table-card" data-allow-toggle="true" <?= $index === 0 ? 'open' : ''; ?>>
                      <summary class="section-title">
                        <h3><?= htmlspecialchars((string) ($table['label'] ?? $table['name']), ENT_QUOTES, 'UTF-8'); ?></h3>
                        <span class="muted">
                          <?= htmlspecialchars(t($translations, $currentLanguage, 'data_table_rows'), ENT_QUOTES, 'UTF-8'); ?>:
                          <?= (int) ($table['count'] ?? 0); ?>
                        </span>
                      </summary>
                      <?php if (!empty($table['sensitive'])): ?>
                        <div class="muted" style="margin-bottom: 8px;">
                          <?= htmlspecialchars(t($translations, $currentLanguage, 'data_table_sensitive'), ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                      <?php endif; ?>
                      <div class="data-table-actions">
                        <button class="button-secondary" type="button"
                                data-export-table="data-table-<?= htmlspecialchars((string) $table['name'], ENT_QUOTES, 'UTF-8'); ?>"
                                data-export-name="clickfix-<?= htmlspecialchars((string) $table['name'], ENT_QUOTES, 'UTF-8'); ?>.csv">
                          <?= htmlspecialchars(t($translations, $currentLanguage, 'data_table_export'), ENT_QUOTES, 'UTF-8'); ?>
                        </button>
                      </div>
                      <div class="table-wrap">
                        <table id="data-table-<?= htmlspecialchars((string) $table['name'], ENT_QUOTES, 'UTF-8'); ?>">
                          <thead>
                            <tr>
                              <?php foreach ($table['columns'] as $column): ?>
                                <th><?= htmlspecialchars((string) $column, ENT_QUOTES, 'UTF-8'); ?></th>
                              <?php endforeach; ?>
                            </tr>
                          </thead>
                          <tbody>
                            <?php if (empty($table['rows'])): ?>
                              <tr>
                                <td colspan="<?= max(1, count($table['columns'])); ?>" class="muted">
                                  <?= htmlspecialchars(t($translations, $currentLanguage, 'data_table_empty'), ENT_QUOTES, 'UTF-8'); ?>
                                </td>
                              </tr>
                            <?php else: ?>
                              <?php foreach ($table['rows'] as $row): ?>
                                <tr>
                                  <?php foreach ($table['columns'] as $column): ?>
                                    <td>
                                      <?= formatDataCellHtml($row[$column] ?? null, (string) $table['name'], (string) $column, $isAdmin); ?>
                                    </td>
                                  <?php endforeach; ?>
                                </tr>
                              <?php endforeach; ?>
                            <?php endif; ?>
                          </tbody>
                        </table>
                      </div>
                    </details>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </section>
          <?php endif; ?>
        </div>
        <aside class="workspace-right" id="investigation-rail">
          <?php if ($isAdmin): ?>
            <section class="card investigation-panel" id="investigation-panel">
              <div class="section-title">
                <h2><?= htmlspecialchars(t($translations, $currentLanguage, 'investigation_panel'), ENT_QUOTES, 'UTF-8'); ?></h2>
                <span class="muted" id="investigation-hint">
                  <?= htmlspecialchars(t($translations, $currentLanguage, 'investigation_hint'), ENT_QUOTES, 'UTF-8'); ?>
                </span>
              </div>
              <div class="investigation-empty" id="investigation-empty">
                <?= htmlspecialchars(t($translations, $currentLanguage, 'investigation_empty'), ENT_QUOTES, 'UTF-8'); ?>
              </div>
              <div class="investigation-details" id="investigation-details" hidden>
                <ul class="info-list">
                  <li>
                    <strong><?= htmlspecialchars(t($translations, $currentLanguage, 'investigation_domain'), ENT_QUOTES, 'UTF-8'); ?></strong>
                    <span id="investigation-domain">-</span>
                  </li>
                  <li>
                    <strong><?= htmlspecialchars(t($translations, $currentLanguage, 'investigation_url'), ENT_QUOTES, 'UTF-8'); ?></strong>
                    <span id="investigation-url">-</span>
                  </li>
                  <li>
                    <strong><?= htmlspecialchars(t($translations, $currentLanguage, 'investigation_context'), ENT_QUOTES, 'UTF-8'); ?></strong>
                    <span id="investigation-context">-</span>
                  </li>
                  <li>
                    <strong><?= htmlspecialchars(t($translations, $currentLanguage, 'investigation_time'), ENT_QUOTES, 'UTF-8'); ?></strong>
                    <span id="investigation-time">-</span>
                  </li>
                </ul>
                <div class="form-actions">
                  <a class="button-secondary" id="investigation-open" href="#" target="_blank" rel="noopener">
                    <?= htmlspecialchars(t($translations, $currentLanguage, 'investigation_open'), ENT_QUOTES, 'UTF-8'); ?>
                  </a>
                  <button class="button-ghost" type="button" id="investigation-copy-domain">
                    <?= htmlspecialchars(t($translations, $currentLanguage, 'investigation_copy'), ENT_QUOTES, 'UTF-8'); ?>
                  </button>
                </div>
                <div class="section-title" style="margin-top: 12px;">
                  <span class="muted"><?= htmlspecialchars(t($translations, $currentLanguage, 'investigation_actions'), ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <div class="action-grid">
                  <div class="action-block">
                    <form method="post" class="form-actions js-ajax" data-refresh-target="#public-lists-section">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                      <input type="hidden" name="action" value="list_action" />
                      <input type="hidden" name="mode" value="add" />
                      <input type="hidden" name="list_type" value="allow" />
                      <input type="hidden" name="domain" value="" class="investigation-domain-input" />
                      <input type="hidden" name="reason" value="<?= htmlspecialchars(t($translations, $currentLanguage, 'investigation_reason'), ENT_QUOTES, 'UTF-8'); ?>" />
                      <button class="button-secondary" type="submit">
                        <?= htmlspecialchars(t($translations, $currentLanguage, 'review_allow'), ENT_QUOTES, 'UTF-8'); ?>
                      </button>
                    </form>
                    <sub><?= htmlspecialchars(t($translations, $currentLanguage, 'action_allow_desc'), ENT_QUOTES, 'UTF-8'); ?></sub>
                  </div>
                  <div class="action-block">
                    <form method="post" class="form-actions js-ajax" data-refresh-target="#public-lists-section">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                      <input type="hidden" name="action" value="list_action" />
                      <input type="hidden" name="mode" value="add" />
                      <input type="hidden" name="list_type" value="block" />
                      <input type="hidden" name="domain" value="" class="investigation-domain-input" />
                      <input type="hidden" name="reason" value="<?= htmlspecialchars(t($translations, $currentLanguage, 'investigation_reason'), ENT_QUOTES, 'UTF-8'); ?>" />
                      <button class="button-primary" type="submit">
                        <?= htmlspecialchars(t($translations, $currentLanguage, 'review_block'), ENT_QUOTES, 'UTF-8'); ?>
                      </button>
                    </form>
                    <sub><?= htmlspecialchars(t($translations, $currentLanguage, 'action_block_desc'), ENT_QUOTES, 'UTF-8'); ?></sub>
                  </div>
                  <div class="action-block">
                    <form method="post" class="form-actions js-ajax" data-refresh-target="#public-lists-section">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                      <input type="hidden" name="action" value="list_action" />
                      <input type="hidden" name="mode" value="add" />
                      <input type="hidden" name="list_type" value="alert" />
                      <input type="hidden" name="domain" value="" class="investigation-domain-input" />
                      <input type="hidden" name="reason" value="<?= htmlspecialchars(t($translations, $currentLanguage, 'investigation_reason'), ENT_QUOTES, 'UTF-8'); ?>" />
                      <button class="button-ghost" type="submit">
                        <?= htmlspecialchars(t($translations, $currentLanguage, 'review_investigate'), ENT_QUOTES, 'UTF-8'); ?>
                      </button>
                    </form>
                    <sub><?= htmlspecialchars(t($translations, $currentLanguage, 'action_alert_desc'), ENT_QUOTES, 'UTF-8'); ?></sub>
                  </div>
                </div>
              </div>
            </section>
          <?php else: ?>
            <section class="card investigation-panel">
              <h2><?= htmlspecialchars(t($translations, $currentLanguage, 'access_restricted_title'), ENT_QUOTES, 'UTF-8'); ?></h2>
              <p class="muted"><?= htmlspecialchars(t($translations, $currentLanguage, 'access_restricted_note'), ENT_QUOTES, 'UTF-8'); ?></p>
            </section>
          <?php endif; ?>
        </aside>

      </div>
      <?php elseif (!$isPublicView): ?>
        <section class="access-hero">
          <div class="access-message">
            <span class="access-kicker"><?= htmlspecialchars(t($translations, $currentLanguage, 'admin_portal'), ENT_QUOTES, 'UTF-8'); ?></span>
            <h2><?= htmlspecialchars(t($translations, $currentLanguage, 'access_restricted_title'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <p class="muted"><?= htmlspecialchars(t($translations, $currentLanguage, 'access_restricted_body'), ENT_QUOTES, 'UTF-8'); ?></p>
            <div class="access-note">
              <?= htmlspecialchars(t($translations, $currentLanguage, 'access_restricted_note'), ENT_QUOTES, 'UTF-8'); ?>
            </div>
            <?php if ($currentUser): ?>
              <div class="chip-list" style="margin-top: 12px;">
                <span class="badge">
                  <?= htmlspecialchars(t($translations, $currentLanguage, 'session_active'), ENT_QUOTES, 'UTF-8'); ?>:
                  <?= htmlspecialchars((string) $currentUser['username'], ENT_QUOTES, 'UTF-8'); ?>
                </span>
                <span class="role-badge <?= htmlspecialchars((string) $currentUser['role'], ENT_QUOTES, 'UTF-8'); ?>">
                  <?= htmlspecialchars((string) $currentUser['role'], ENT_QUOTES, 'UTF-8'); ?>
                </span>
              </div>
            <?php endif; ?>
          </div>
          <div class="access-grid">
            <?php if ($currentUser): ?>
              <section class="card access-card">
                <div class="section-title">
                  <h3><?= htmlspecialchars(t($translations, $currentLanguage, 'session_panel'), ENT_QUOTES, 'UTF-8'); ?></h3>
                  <span class="muted"><?= htmlspecialchars(t($translations, $currentLanguage, 'access_panel'), ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <div class="highlight">
                  <ul class="info-list">
                    <li>
                      <strong><?= htmlspecialchars(t($translations, $currentLanguage, 'access_state'), ENT_QUOTES, 'UTF-8'); ?></strong>
                      <span><?= htmlspecialchars(t($translations, $currentLanguage, 'active'), ENT_QUOTES, 'UTF-8'); ?></span>
                    </li>
                    <li>
                      <strong><?= htmlspecialchars(t($translations, $currentLanguage, 'access_role'), ENT_QUOTES, 'UTF-8'); ?></strong>
                      <span><?= htmlspecialchars((string) ($currentUser['role'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                    </li>
                  </ul>
                </div>
                <form method="post" class="form-actions" style="margin-top: 16px;">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                  <input type="hidden" name="action" value="logout" />
                  <button class="button-ghost" type="submit"><?= htmlspecialchars(t($translations, $currentLanguage, 'logout'), ENT_QUOTES, 'UTF-8'); ?></button>
                </form>
              </section>
            <?php else: ?>
              <section class="card access-card">
                <div class="section-title">
                  <h3><?= htmlspecialchars(t($translations, $currentLanguage, 'login'), ENT_QUOTES, 'UTF-8'); ?></h3>
                  <span class="muted"><?= htmlspecialchars(t($translations, $currentLanguage, 'login_hint'), ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <form method="post" class="form-grid" style="margin-top: 12px;">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                  <input type="hidden" name="action" value="login" />
                  <label>
                    <?= htmlspecialchars(t($translations, $currentLanguage, 'username'), ENT_QUOTES, 'UTF-8'); ?>
                    <input type="text" name="username" required autocomplete="username" autocapitalize="none" spellcheck="false" />
                  </label>
                  <label>
                    <?= htmlspecialchars(t($translations, $currentLanguage, 'password'), ENT_QUOTES, 'UTF-8'); ?>
                    <input type="password" name="password" required autocomplete="current-password" />
                  </label>
                  <div class="form-actions">
                    <button class="button-primary" type="submit"><?= htmlspecialchars(t($translations, $currentLanguage, 'login'), ENT_QUOTES, 'UTF-8'); ?></button>
                  </div>
                </form>
              </section>
              <section class="card access-card">
                <div class="section-title">
                  <h3><?= htmlspecialchars(t($translations, $currentLanguage, 'quick_register'), ENT_QUOTES, 'UTF-8'); ?></h3>
                  <span class="muted"><?= htmlspecialchars(t($translations, $currentLanguage, 'register_hint'), ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <form method="post" class="form-grid" style="margin-top: 12px;">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                  <input type="hidden" name="action" value="register" />
                  <label>
                    <?= htmlspecialchars(t($translations, $currentLanguage, 'username'), ENT_QUOTES, 'UTF-8'); ?>
                    <input type="text" name="username" required autocomplete="username" autocapitalize="none" spellcheck="false" />
                  </label>
                  <label>
                    <?= htmlspecialchars(t($translations, $currentLanguage, 'password'), ENT_QUOTES, 'UTF-8'); ?>
                    <input type="password" name="password" required autocomplete="new-password" />
                  </label>
                  <label>
                    <?= htmlspecialchars(t($translations, $currentLanguage, 'admin_code_optional'), ENT_QUOTES, 'UTF-8'); ?>
                    <input type="text" name="admin_code" autocomplete="off" spellcheck="false" />
                  </label>
                  <div class="form-actions">
                    <button class="button-secondary" type="submit"><?= htmlspecialchars(t($translations, $currentLanguage, 'register'), ENT_QUOTES, 'UTF-8'); ?></button>
                  </div>
                </form>
              </section>
            <?php endif; ?>
          </div>
        </section>
      <?php endif; ?>
      </main>
    </div>
    <?php if ($canViewDashboard || $publicAnalyticsEnabled): ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script type="application/json" id="chart-payload-public">
      <?= htmlspecialchars(json_encode($chartPayloadPublic, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), ENT_NOQUOTES, 'UTF-8'); ?>
    </script>
    <script type="application/json" id="chart-payload-private">
      <?= htmlspecialchars(json_encode($chartPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), ENT_NOQUOTES, 'UTF-8'); ?>
    </script>
    <script>
      const chartPayloadPublicNode = document.getElementById("chart-payload-public");
      const chartPayloadPrivateNode = document.getElementById("chart-payload-private");
      const chartPayloadPublic = chartPayloadPublicNode
        ? JSON.parse(chartPayloadPublicNode.textContent || "{}")
        : <?= json_encode($chartPayloadPublic, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
      const chartPayloadPrivate = chartPayloadPrivateNode
        ? JSON.parse(chartPayloadPrivateNode.textContent || "{}")
        : <?= json_encode($chartPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
      const dashboardAccess = document.body?.dataset?.dashboardAccess || "public";
      const chartPayload = dashboardAccess === "private" ? chartPayloadPrivate : chartPayloadPublic;
      const tableFilterLabels = {
        label: "<?= htmlspecialchars(t($translations, $currentLanguage, 'filter_table'), ENT_QUOTES, 'UTF-8'); ?>",
        placeholder: "<?= htmlspecialchars(t($translations, $currentLanguage, 'filter_placeholder'), ENT_QUOTES, 'UTF-8'); ?>",
        empty: "<?= htmlspecialchars(t($translations, $currentLanguage, 'filter_no_results'), ENT_QUOTES, 'UTF-8'); ?>"
      };
      const toastMessages = {
        refreshError: "<?= htmlspecialchars(t($translations, $currentLanguage, 'toast_refresh_error'), ENT_QUOTES, 'UTF-8'); ?>",
        actionError: "<?= htmlspecialchars(t($translations, $currentLanguage, 'toast_action_error'), ENT_QUOTES, 'UTF-8'); ?>",
        bulkEmpty: "<?= htmlspecialchars(t($translations, $currentLanguage, 'bulk_no_selection'), ENT_QUOTES, 'UTF-8'); ?>",
        copySuccess: "<?= htmlspecialchars(t($translations, $currentLanguage, 'toast_copy_success'), ENT_QUOTES, 'UTF-8'); ?>"
      };
      const investigationContextLabels = {
        allowlist: "<?= htmlspecialchars(t($translations, $currentLanguage, 'allowlist'), ENT_QUOTES, 'UTF-8'); ?>",
        blocklist: "<?= htmlspecialchars(t($translations, $currentLanguage, 'blocklist'), ENT_QUOTES, 'UTF-8'); ?>",
        alertlist: "<?= htmlspecialchars(t($translations, $currentLanguage, 'alertlist'), ENT_QUOTES, 'UTF-8'); ?>",
        detection: "<?= htmlspecialchars(t($translations, $currentLanguage, 'recent_detections'), ENT_QUOTES, 'UTF-8'); ?>",
        manual: "<?= htmlspecialchars(t($translations, $currentLanguage, 'manual_sites'), ENT_QUOTES, 'UTF-8'); ?>",
        alerted: "<?= htmlspecialchars(t($translations, $currentLanguage, 'alerted_sites'), ENT_QUOTES, 'UTF-8'); ?>"
      };

      const toastContainer = document.querySelector(".toast-container");
      const showToast = (message, variant = "success") => {
        if (!toastContainer || !message) {
          return;
        }
        const toast = document.createElement("div");
        toast.className = `toast ${variant}`;
        toast.textContent = message;
        toastContainer.appendChild(toast);
        setTimeout(() => {
          toast.remove();
        }, 4200);
      };

      let activateWorkspaceTab = null;
      const setupWorkspaceTabs = (root = document) => {
        const container = document.getElementById("workspace-tabs");
        if (!container || container.dataset.tabsReady === "true") {
          return;
        }
        container.dataset.tabsReady = "true";
        const buttons = Array.from(container.querySelectorAll("[data-workspace-tab]"));
        const getSections = () => Array.from(document.querySelectorAll("[data-workspace-section]"));
        const rightRail = document.getElementById("investigation-rail");
        if (!buttons.length) {
          return;
        }
        const availableTabs = new Set(buttons.map((button) => button.dataset.workspaceTab));
        const setActiveTab = (tabId, { persist = true } = {}) => {
          if (!availableTabs.has(tabId)) {
            return;
          }
          buttons.forEach((button) => {
            const isActive = button.dataset.workspaceTab === tabId;
            button.classList.toggle("is-active", isActive);
            button.setAttribute("aria-selected", isActive ? "true" : "false");
          });
          getSections().forEach((section) => {
            section.hidden = section.dataset.workspaceSection !== tabId;
          });
          if (rightRail) {
            rightRail.hidden = false;
          }
          if (persist) {
            sessionStorage.setItem("cf_workspace_tab", tabId);
          }
        };

        buttons.forEach((button) => {
          button.addEventListener("click", () => {
            setActiveTab(button.dataset.workspaceTab || "");
          });
        });

        const saved = sessionStorage.getItem("cf_workspace_tab");
        const defaultTab = container.dataset.defaultTab || buttons[0]?.dataset.workspaceTab || "alerts";
        const initialTab = saved && availableTabs.has(saved) ? saved : defaultTab;
        setActiveTab(initialTab, { persist: false });
        activateWorkspaceTab = (tabId) => setActiveTab(tabId);
      };

      const setupLiveUpdates = (root = document) => {
        if (root !== document) {
          return;
        }
        if (document.body.dataset.liveReady === "true") {
          return;
        }
        document.body.dataset.liveReady = "true";
        const refreshIntervalMs = 15000;
        let liveTimer = null;
        let inFlight = false;
        const buildLiveUrl = () => {
          const url = new URL(window.location.href);
          url.searchParams.set("format", "live");
          if (dashboardAccess === "public") {
            url.searchParams.set("public", "1");
          } else {
            url.searchParams.delete("public");
          }
          return url.toString();
        };

        const applyMetrics = (stats) => {
          if (!stats) {
            return;
          }
          document.querySelectorAll("[data-live-metric]").forEach((node) => {
            const key = node.dataset.liveMetric;
            if (!key || !(key in stats)) {
              return;
            }
            const value = stats[key];
            if (value === null || value === undefined) {
              return;
            }
            if (node.dataset.liveFormat === "percent") {
              const numeric = Number(value);
              if (!Number.isNaN(numeric)) {
                node.textContent = `${numeric.toFixed(1)}%`;
                return;
              }
            }
            node.textContent = String(value);
          });
        };

        const applyRecentDomains = (domains) => {
          if (!Array.isArray(domains)) {
            return;
          }
          document.querySelectorAll("[data-domains-table]").forEach((table) => {
            const body = table.querySelector("[data-domains-body]") || table.querySelector("tbody");
            if (!body) {
              return;
            }
            const panel = table.closest("[data-search-panel]");
            const viewLabel = table.dataset.viewLabel || "View";
            const emptyLabel = table.dataset.noResults || "No results.";
            const limitRaw = Number.parseInt(table.dataset.domainsLimit || "", 10);
            const sliceLimit = Number.isFinite(limitRaw) && limitRaw > 0 ? limitRaw : null;
            const rows = sliceLimit ? domains.slice(0, sliceLimit) : domains;
            body.innerHTML = "";
            if (!rows.length) {
              const row = document.createElement("tr");
              const cell = document.createElement("td");
              cell.colSpan = 4;
              cell.className = "muted";
              cell.textContent = emptyLabel;
              row.appendChild(cell);
              body.appendChild(row);
              return;
            }
            rows.forEach((item) => {
              const row = document.createElement("tr");
              row.dataset.searchRow = "true";
              const actionCell = document.createElement("td");
              const domainCell = document.createElement("td");
              const dateCell = document.createElement("td");
              const commandCell = document.createElement("td");
              const url = (item?.url || "").trim();
              const domain = String(item?.domain || "");
              const viewUrl = url || (domain ? `https://${domain}` : "");
              if (viewUrl) {
                const link = document.createElement("a");
                link.className = "button-ghost";
                link.href = viewUrl;
                link.target = "_blank";
                link.rel = "noopener";
                link.textContent = viewLabel;
                actionCell.appendChild(link);
              }
              domainCell.textContent = domain;
              dateCell.textContent = String(item?.date || "");
              const code = document.createElement("code");
              code.textContent = String(item?.command || "");
              commandCell.appendChild(code);
              row.appendChild(actionCell);
              row.appendChild(domainCell);
              row.appendChild(dateCell);
              row.appendChild(commandCell);
              body.appendChild(row);
            });
            if (panel && typeof panel._applyFilter === "function") {
              panel._applyFilter();
            }
          });
        };

        const runRefresh = async () => {
          if (document.hidden || inFlight) {
            return;
          }
          inFlight = true;
          try {
            const response = await fetch(buildLiveUrl(), {
              headers: { "X-Requested-With": "fetch" }
            });
            if (!response.ok) {
              throw new Error("live refresh failed");
            }
            const payload = await response.json();
            if (payload?.charts && typeof renderCharts === "function") {
              renderCharts(payload.charts);
            }
            if (payload?.stats) {
              applyMetrics(payload.stats);
            }
            if (payload?.recent_domains) {
              applyRecentDomains(payload.recent_domains);
              setupSearchPanel(document);
            }
          } catch (error) {
            // Silent: avoid spamming toasts on background refresh failures.
          } finally {
            inFlight = false;
          }
        };

        const startTimer = () => {
          if (liveTimer) {
            return;
          }
          liveTimer = setInterval(runRefresh, refreshIntervalMs);
        };

        const stopTimer = () => {
          if (liveTimer) {
            clearInterval(liveTimer);
            liveTimer = null;
          }
        };

        document.addEventListener("visibilitychange", () => {
          if (document.hidden) {
            stopTimer();
            return;
          }
          startTimer();
          runRefresh();
        });

        startTimer();
      };

      const dedupeAnalyticsSections = (root = document) => {
        if (root !== document) {
          return;
        }
        const sections = Array.from(document.querySelectorAll("[data-live-section=\"alert-analytics\"]"));
        if (sections.length <= 1) {
          return;
        }
        sections.slice(1).forEach((section) => section.remove());
      };

      const flattenDetails = (root = document) => {
        const details = [];
        if (root.matches && root.matches("details")) {
          details.push(root);
        }
        root.querySelectorAll?.("details").forEach((node) => details.push(node));
        details.forEach((node) => {
          if (node.dataset.flattenReady === "true") {
            return;
          }
          if (node.dataset.allowToggle === "true") {
            return;
          }
          node.dataset.flattenReady = "true";
          node.open = true;
          node.classList.add("no-toggle");
          const summary = node.querySelector("summary");
          if (summary) {
            summary.addEventListener("click", (event) => {
              event.preventDefault();
            });
            summary.addEventListener("keydown", (event) => {
              if (event.key === "Enter" || event.key === " ") {
                event.preventDefault();
              }
            });
          }
        });
      };

      const setupTableFilters = (root = document) => {
        const tables = [];
        if (root.matches && root.matches("table")) {
          tables.push(root);
        }
        root.querySelectorAll?.("table").forEach((table) => tables.push(table));

        tables.forEach((table) => {
          if (table.dataset.filterReady === "true") {
            return;
          }
          if (table.dataset.filter === "manual" || table.closest?.("[data-filter-mode=\"manual\"]")) {
            return;
          }
          const tbody = table.querySelector("tbody");
          if (!tbody) {
            return;
          }
          table.dataset.filterReady = "true";
          const wrapper = table.closest(".table-wrap") || table.parentElement;
          const filter = document.createElement("div");
          filter.className = "table-filter";
          const label = document.createElement("label");
          label.className = "muted";
          label.textContent = tableFilterLabels.label;
          const input = document.createElement("input");
          input.type = "search";
          input.placeholder = tableFilterLabels.placeholder;
          label.appendChild(input);
          filter.appendChild(label);
          wrapper?.insertBefore(filter, table);

          const emptyRow = document.createElement("tr");
          emptyRow.className = "table-empty-row";
          const emptyCell = document.createElement("td");
          const columnCount = table.querySelectorAll("thead th").length
            || table.querySelectorAll("tbody tr:first-child td").length
            || 1;
          emptyCell.colSpan = columnCount;
          emptyCell.textContent = tableFilterLabels.empty;
          emptyRow.appendChild(emptyCell);

          const rows = Array.from(tbody.querySelectorAll("tr"));
          const applyFilter = () => {
            const query = input.value.trim().toLowerCase();
            let visibleCount = 0;
            rows.forEach((row) => {
              if (row === emptyRow) {
                return;
              }
              const match = row.textContent.toLowerCase().includes(query);
              row.style.display = match ? "" : "none";
              if (match) {
                visibleCount += 1;
              }
            });
            if (visibleCount === 0) {
              if (!tbody.contains(emptyRow)) {
                tbody.appendChild(emptyRow);
              }
            } else {
              emptyRow.remove();
            }
          };

          input.addEventListener("input", applyFilter);
        });
      };

      const setupAccordions = (root = document) => {
        const cards = [];
        if (root.matches && root.matches("section.card")) {
          cards.push(root);
        }
        root.querySelectorAll?.("section.card").forEach((card) => cards.push(card));

        cards.forEach((card) => {
          if (card.dataset.accordionReady === "true") {
            return;
          }
          card.dataset.accordionReady = "true";
          const directTitle = card.querySelector(":scope > .section-title")
            || card.querySelector(":scope > h2")
            || card.querySelector(":scope > h3");
          const fallbackTitle = card.dataset.accordionTitle
            || card.querySelector("h2, h3")?.textContent?.trim()
            || "Section";

          if (!directTitle && card.dataset.accordionTitle) {
            const firstHeading = card.querySelector("h2, h3");
            if (firstHeading) {
              firstHeading.classList.add("accordion-title-suppressed");
            }
          }

          const header = document.createElement("button");
          header.type = "button";
          header.className = "accordion-header";
          header.setAttribute("aria-expanded", "true");
          const titleWrapper = document.createElement("span");
          if (directTitle) {
            directTitle.remove();
            titleWrapper.appendChild(directTitle);
          } else {
            titleWrapper.textContent = fallbackTitle;
          }
          const chevron = document.createElement("span");
          chevron.className = "accordion-chevron";
          chevron.textContent = "▾";
          header.appendChild(titleWrapper);
          header.appendChild(chevron);

          const content = document.createElement("div");
          content.className = "accordion-content";
          while (card.firstChild) {
            content.appendChild(card.firstChild);
          }
          card.classList.add("accordion-card", "accordion-open");
          card.appendChild(header);
          card.appendChild(content);

          header.addEventListener("click", () => {
            const isCollapsed = card.classList.toggle("accordion-collapsed");
            header.setAttribute("aria-expanded", String(!isCollapsed));
          });
        });
      };

      const refreshSection = async (selector) => {
        if (!selector) {
          return;
        }
        const current = document.querySelector(selector);
        if (!current) {
          return;
        }
        try {
          const response = await fetch(window.location.href, {
            headers: { "X-Requested-With": "fetch" }
          });
          if (!response.ok) {
            throw new Error("refresh failed");
          }
          const html = await response.text();
          const doc = new DOMParser().parseFromString(html, "text/html");
          const updated = doc.querySelector(selector);
          if (!updated) {
            return;
          }
          current.replaceWith(updated);
          initializeDashboard(updated);
        } catch (error) {
          showToast(toastMessages.refreshError, "error");
        }
      };

      const setupAjaxForms = (root = document) => {
        const forms = [];
        if (root.matches && root.matches("form.js-ajax")) {
          forms.push(root);
        }
        root.querySelectorAll?.("form.js-ajax").forEach((form) => forms.push(form));

        forms.forEach((form) => {
          if (form.dataset.ajaxReady === "true") {
            return;
          }
          form.dataset.ajaxReady = "true";
          form.addEventListener("submit", async (event) => {
            event.preventDefault();
            const submitButtons = Array.from(form.querySelectorAll("button, input[type=\"submit\"]"));
            submitButtons.forEach((button) => {
              button.disabled = true;
            });
            try {
              const response = await fetch(window.location.href, {
                method: "POST",
                body: new FormData(form),
                headers: { "X-Requested-With": "fetch" }
              });
              if (!response.ok) {
                throw new Error("request failed");
              }
              const payload = await response.json();
              const errors = payload.errors || [];
              const notices = payload.notices || [];
              if (errors.length) {
                errors.forEach((message) => showToast(message, "error"));
              }
              if (notices.length) {
                notices.forEach((message) => showToast(message, "success"));
              }
              if (payload.ok && form.dataset.reset === "true") {
                form.reset();
              }
              if (payload.ok && form.id === "bulk-list-form") {
                selectionState.domains.clear();
                updateSelectionUI();
              }
              if (payload.ok && form.dataset.refreshTarget) {
                await refreshSection(form.dataset.refreshTarget);
              }
            } catch (error) {
              showToast(toastMessages.actionError, "error");
            } finally {
              submitButtons.forEach((button) => {
                button.disabled = false;
              });
            }
          });
        });
      };

      const selectionState = {
        domains: new Set()
      };

      const updateSelectionUI = () => {
        const countEl = document.getElementById("selected-count");
        if (countEl) {
          countEl.textContent = String(selectionState.domains.size);
        }
        const form = document.getElementById("bulk-list-form");
        if (form) {
          const domainsInput = form.querySelector("input[name=\"domains_json\"]");
          if (domainsInput) {
            domainsInput.value = JSON.stringify(Array.from(selectionState.domains));
          }
        }
        document.querySelectorAll(".js-selectable").forEach((item) => {
          const domain = item.dataset.domain || "";
          item.classList.toggle("selected", selectionState.domains.has(domain));
        });
      };

      const setupBulkActions = (root = document) => {
        const form = document.getElementById("bulk-list-form");
        if (!form || form.dataset.bulkReady === "true") {
          return;
        }
        form.dataset.bulkReady = "true";
        const operationInput = form.querySelector("input[name=\"operation\"]");
        const targetInput = form.querySelector("input[name=\"target_list\"]");
        form.querySelectorAll("[data-bulk-action]").forEach((button) => {
          button.addEventListener("click", () => {
            const action = button.dataset.bulkAction;
            if (action === "clear") {
              selectionState.domains.clear();
              updateSelectionUI();
              return;
            }
            if (selectionState.domains.size === 0) {
              showToast(toastMessages.bulkEmpty, "error");
              return;
            }
            if (operationInput) {
              operationInput.value = action || "";
            }
            if (targetInput) {
              targetInput.value = button.dataset.targetList || "";
            }
            updateSelectionUI();
            form.requestSubmit();
          });
        });
      };

      const setupSelectableItems = (root = document) => {
        root.querySelectorAll?.(".js-selectable").forEach((item) => {
          if (item.dataset.selectReady === "true") {
            return;
          }
          item.dataset.selectReady = "true";
          item.addEventListener("click", (event) => {
            event.preventDefault();
            const domain = item.dataset.domain || "";
            if (!domain) {
              return;
            }
            if (selectionState.domains.has(domain)) {
              selectionState.domains.delete(domain);
            } else {
              selectionState.domains.add(domain);
            }
            updateSelectionUI();
          });
        });
        updateSelectionUI();
      };

      const setupInvestigationPanel = (root = document) => {
        const panel = document.getElementById("investigation-panel");
        if (!panel) {
          return;
        }
        if (!panel.dataset.investigationReady) {
          panel.dataset.investigationReady = "true";
          panel.dataset.investigationHandlerId = "ready";
          panel._investigation = {
            emptyState: document.getElementById("investigation-empty"),
            details: document.getElementById("investigation-details"),
            domainEl: document.getElementById("investigation-domain"),
            urlEl: document.getElementById("investigation-url"),
            contextEl: document.getElementById("investigation-context"),
            timeEl: document.getElementById("investigation-time"),
            openLink: document.getElementById("investigation-open"),
            copyButton: document.getElementById("investigation-copy-domain"),
            domainInputs: panel.querySelectorAll(".investigation-domain-input")
          };
          panel._investigation.setPanelData = ({ domain, url, context, timestamp } = {}) => {
            if (!domain) {
              return;
            }
            const {
              emptyState,
              details,
              domainEl,
              urlEl,
              contextEl,
              timeEl,
              openLink,
              domainInputs
            } = panel._investigation;
            if (emptyState) {
              emptyState.hidden = true;
            }
            if (details) {
              details.hidden = false;
            }
            if (domainEl) {
              domainEl.textContent = domain;
            }
            if (contextEl) {
              contextEl.textContent = investigationContextLabels[context] || context || "-";
            }
            if (timeEl) {
              timeEl.textContent = timestamp || "-";
            }
            const resolvedUrl = url || (domain ? `https://${domain}` : "");
            if (urlEl) {
              urlEl.textContent = resolvedUrl || "-";
            }
            if (openLink) {
              if (resolvedUrl) {
                openLink.href = resolvedUrl;
                openLink.removeAttribute("aria-disabled");
              } else {
                openLink.href = "#";
                openLink.setAttribute("aria-disabled", "true");
              }
            }
            domainInputs.forEach((input) => {
              input.value = domain;
            });
            panel.scrollIntoView({ behavior: "smooth", block: "center" });
          };
          if (panel._investigation.copyButton) {
            panel._investigation.copyButton.addEventListener("click", async () => {
              const { domainEl } = panel._investigation;
              if (!domainEl || !domainEl.textContent || domainEl.textContent === "-") {
                return;
              }
              try {
                await navigator.clipboard.writeText(domainEl.textContent.trim());
                showToast(toastMessages.copySuccess, "success");
              } catch (error) {
                showToast(toastMessages.actionError, "error");
              }
            });
          }
        }

        const handleInvestigate = (event) => {
          const target = event.currentTarget;
          const domain = target.dataset.domain || "";
          if (!domain) {
            return;
          }
          panel._investigation?.setPanelData({
            domain,
            url: target.dataset.url || "",
            context: target.dataset.context || "",
            timestamp: target.dataset.timestamp || ""
          });
        };

        root.querySelectorAll?.(".js-investigate").forEach((item) => {
          if (item.dataset.investigateReady === "true") {
            return;
          }
          item.dataset.investigateReady = "true";
          item.addEventListener("click", handleInvestigate);
        });
      };

      const THEME_STORAGE_KEY = "cf_theme";
      const normalizeTheme = (value) => {
        return value === "dark" || value === "light" ? value : "system";
      };
      const applyTheme = (value) => {
        const theme = normalizeTheme(value);
        if (theme === "system") {
          document.documentElement.removeAttribute("data-theme");
        } else {
          document.documentElement.dataset.theme = theme;
        }
        return theme;
      };
      const setupThemeToggle = (root = document) => {
        const select = document.getElementById("theme-select");
        if (!select || select.dataset.themeReady === "true") {
          return;
        }
        select.dataset.themeReady = "true";
        const stored = normalizeTheme(localStorage.getItem(THEME_STORAGE_KEY));
        select.value = stored;
        applyTheme(stored);
        select.addEventListener("change", () => {
          const nextTheme = normalizeTheme(select.value);
          if (nextTheme === "system") {
            localStorage.removeItem(THEME_STORAGE_KEY);
          } else {
            localStorage.setItem(THEME_STORAGE_KEY, nextTheme);
          }
          applyTheme(nextTheme);
        });
      };

      const initializeDashboard = (root = document) => {
        setupWorkspaceTabs(root);
        setupLiveUpdates(root);
        dedupeAnalyticsSections(root);
        flattenDetails(root);
        setupTableFilters(root);
        setupSearchPanel(root);
        setupTableExports(root);
        setupAjaxForms(root);
        setupSelectableItems(root);
        setupBulkActions(root);
        setupInvestigationPanel(root);
        setupThemeToggle(root);
      };
      const charts = new Map();
      const upsertChart = (id, type, data, options = {}) => {
        const canvas = document.getElementById(id);
        if (!canvas) {
          return;
        }
        const existing = charts.get(id);
        if (existing && existing.canvas !== canvas) {
          existing.destroy();
          charts.delete(id);
        }
        if (charts.has(id)) {
          const chart = charts.get(id);
          chart.data = data;
          chart.options = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
              legend: { display: false }
            },
            ...options
          };
          chart.update();
          return;
        }
        const chart = new Chart(canvas.getContext("2d"), {
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
        charts.set(id, chart);
      };

      const renderCharts = (payload) => {
        if (!payload || !payload.labels) {
          return;
        }
        upsertChart("chart-alerts-day", "line", {
          labels: payload.daily.labels,
          datasets: [{
            label: payload.labels.alerts,
            data: payload.daily.values,
            borderColor: "#2563eb",
            backgroundColor: "rgba(37, 99, 235, 0.2)",
            tension: 0.3,
            fill: true
          }]
        });

        upsertChart("chart-blocks-day", "bar", {
          labels: payload.dailyBlocks.labels,
          datasets: [{
            label: payload.labels.blocks,
            data: payload.dailyBlocks.values,
            backgroundColor: "#1d4ed8"
          }]
        });

        upsertChart("chart-alerts-baseline", "line", {
          labels: payload.baseline.labels,
          datasets: [{
            label: payload.labels.alerts,
            data: payload.daily.values,
            borderColor: "#2563eb",
            backgroundColor: "rgba(37, 99, 235, 0.12)",
            tension: 0.3,
            fill: true
          }, {
            label: payload.labels.baseline,
            data: payload.baseline.values,
            borderColor: "#38bdf8",
            borderDash: [6, 6],
            tension: 0.3,
            fill: false
          }]
        });

        upsertChart("chart-block-rate", "line", {
          labels: payload.blockRate.labels,
          datasets: [{
            label: payload.labels.block_rate,
            data: payload.blockRate.values,
            borderColor: "#0ea5e9",
            backgroundColor: "rgba(14, 165, 233, 0.2)",
            tension: 0.3,
            fill: true
          }]
        }, {
          scales: { y: { min: 0, max: 100 } }
        });

        upsertChart("chart-alerts-hour", "bar", {
          labels: payload.hourly.labels,
          datasets: [{
            label: payload.labels.alerts,
            data: payload.hourly.values,
            backgroundColor: "#38bdf8"
          }]
        }, {
          scales: { x: { ticks: { stepSize: 1 } } }
        });

        upsertChart("chart-blocks-hour", "bar", {
          labels: payload.hourlyBlocks.labels,
          datasets: [{
            label: payload.labels.blocks,
            data: payload.hourlyBlocks.values,
            backgroundColor: "#0f766e"
          }]
        }, {
          scales: { x: { ticks: { stepSize: 1 } } }
        });

        upsertChart("chart-alerts-country", "doughnut", {
          labels: payload.countries.labels,
          datasets: [{
            data: payload.countries.values,
            backgroundColor: ["#2563eb", "#38bdf8", "#1d4ed8", "#0ea5e9", "#60a5fa", "#93c5fd", "#1e40af"]
          }]
        });

        upsertChart("chart-os", "doughnut", {
          labels: payload.os.labels,
          datasets: [{
            data: payload.os.values,
            backgroundColor: ["#0f766e", "#14b8a6", "#2dd4bf", "#99f6e4", "#0ea5e9", "#1d4ed8", "#f59e0b"]
          }]
        }, {
          plugins: { legend: { display: true, position: "bottom" } }
        });

        upsertChart("chart-browsers", "doughnut", {
          labels: payload.browsers.labels,
          datasets: [{
            data: payload.browsers.values,
            backgroundColor: ["#f97316", "#fb7185", "#60a5fa", "#22c55e", "#a855f7", "#f59e0b", "#0ea5e9"]
          }]
        }, {
          plugins: { legend: { display: true, position: "bottom" } }
        });

        upsertChart("chart-alerts-signals", "bar", {
          labels: payload.signals.labels,
          datasets: [{
            label: payload.labels.signals,
            data: payload.signals.values,
            backgroundColor: "#2563eb"
          }]
        }, {
          indexAxis: "y"
        });

        upsertChart("chart-review-status", "doughnut", {
          labels: payload.reviews.labels,
          datasets: [{
            data: payload.reviews.values,
            backgroundColor: ["#94a3b8", "#22c55e", "#ef4444", "#38bdf8"]
          }]
        });

        upsertChart("chart-top-hosts", "bar", {
          labels: payload.topHosts.labels,
          datasets: [{
            label: payload.labels.top_hosts,
            data: payload.topHosts.values,
            backgroundColor: "#2563eb"
          }]
        }, {
          indexAxis: "y"
        });

        upsertChart("chart-install-channels", "doughnut", {
          labels: payload.installChannels.labels,
          datasets: [{
            data: payload.installChannels.values,
            backgroundColor: ["#2563eb", "#38bdf8", "#1d4ed8", "#0ea5e9", "#60a5fa", "#93c5fd", "#1e40af"]
          }]
        }, {
          plugins: { legend: { display: true, position: "bottom" } }
        });
      };

      const setupSearchPanel = (root = document) => {
        const panel = root.querySelector?.("[data-search-panel]");
        if (!panel || panel.dataset.searchReady === "true") {
          return;
        }
        panel.dataset.searchReady = "true";
        const input = panel.querySelector("[data-search-input]");
        const fieldSelect = panel.querySelector("[data-search-field]");
        const button = panel.querySelector("[data-search-button]");
        const clear = panel.querySelector("[data-search-clear]");
        const exportButton = panel.querySelector("[data-search-export]");
        const applyFilter = () => {
          const term = (input?.value || "").trim().toLowerCase();
          const scope = fieldSelect?.value || "any";
          const rows = panel.querySelectorAll("[data-search-row]");
          rows.forEach((row) => {
            const cells = row.querySelectorAll("td");
            const domain = (cells[1]?.textContent || "").toLowerCase();
            const date = (cells[2]?.textContent || "").toLowerCase();
            const command = (cells[3]?.textContent || "").toLowerCase();
            const haystack = scope === "domain"
              ? domain
              : scope === "command"
                ? command
                : `${domain} ${date} ${command}`;
            const match = term === "" || haystack.includes(term);
            row.style.display = match ? "" : "none";
          });
        };
        panel._applyFilter = applyFilter;
        button?.addEventListener("click", applyFilter);
        input?.addEventListener("input", () => {
          if (input.value.trim() === "") {
            applyFilter();
          }
        });
        input?.addEventListener("keydown", (event) => {
          if (event.key === "Enter") {
            event.preventDefault();
            applyFilter();
          }
        });
        clear?.addEventListener("click", () => {
          if (input) {
            input.value = "";
          }
          if (fieldSelect) {
            fieldSelect.value = "any";
          }
          applyFilter();
        });
        exportButton?.addEventListener("click", () => {
          const table = panel.querySelector("[data-search-table]");
          if (!table) {
            return;
          }
          const headers = Array.from(table.querySelectorAll("thead th")).map((th) => `"${(th.textContent || "").trim().replace(/"/g, '""')}"`);
          const rows = Array.from(table.querySelectorAll("tbody tr"))
            .filter((row) => row.style.display !== "none")
            .map((row) => {
              const cols = Array.from(row.querySelectorAll("td")).map((cell) => {
                const text = (cell.textContent || "").trim().replace(/"/g, '""');
                return `"${text}"`;
              });
              return cols.join(",");
            });
          const csv = [headers.join(","), ...rows].join("\n");
          const blob = new Blob([csv], { type: "text/csv;charset=utf-8;" });
          const link = document.createElement("a");
          link.href = URL.createObjectURL(blob);
          link.download = "clickfix-search.csv";
          document.body.appendChild(link);
          link.click();
          link.remove();
        });
        applyFilter();
      };

      const setupTableExports = (root = document) => {
        const buttons = root.querySelectorAll?.("[data-export-table]") || [];
        buttons.forEach((button) => {
          if (button.dataset.exportReady === "true") {
            return;
          }
          button.dataset.exportReady = "true";
          button.addEventListener("click", () => {
            const tableId = button.dataset.exportTable;
            const filename = button.dataset.exportName || "export.csv";
            if (!tableId) {
              return;
            }
            const table = document.getElementById(tableId);
            if (!table) {
              return;
            }
            const headers = Array.from(table.querySelectorAll("thead th")).map((th) => `"${(th.textContent || "").trim().replace(/"/g, '""')}"`);
            const rows = Array.from(table.querySelectorAll("tbody tr")).map((row) => {
              const cols = Array.from(row.querySelectorAll("td")).map((cell) => {
                const text = (cell.textContent || "").trim().replace(/"/g, '""');
                return `"${text}"`;
              });
              return cols.join(",");
            });
            const csv = [headers.join(","), ...rows].join("\n");
            const blob = new Blob([csv], { type: "text/csv;charset=utf-8;" });
            const link = document.createElement("a");
            link.href = URL.createObjectURL(blob);
            link.download = filename;
            document.body.appendChild(link);
            link.click();
            link.remove();
          });
        });
      };

      const extractChartPayload = (doc) => {
        const payloadNode = doc?.getElementById("chart-payload");
        if (!payloadNode) {
          return null;
        }
        try {
          return JSON.parse(payloadNode.textContent || "{}");
        } catch (error) {
          return null;
        }
      };

      renderCharts(chartPayload);
      initializeDashboard();
    </script>
    <?php endif; ?>
  </body>
</html>
