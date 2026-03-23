<?php
declare(strict_types=1);

require_once __DIR__ . '/src/clickfix_core.php';
clickfix_bootstrap();

function clickfix_scan_image_error(int $statusCode, string $message): void
{
    http_response_code($statusCode);
    header('Content-Type: text/plain; charset=utf-8');
    echo $message;
    exit;
}

$reportId = (int) ($_GET['report_id'] ?? 0);
$kind = clickfix_scan_kind_normalize((string) ($_GET['kind'] ?? ''));
$previewMode = ((string) ($_GET['preview'] ?? '0')) === '1';
$downloadMode = ((string) ($_GET['download'] ?? '0')) === '1';

if ($reportId <= 0 || $kind === '') {
    clickfix_scan_image_error(400, 'invalid_request');
}

try {
    $pdo = clickfix_open_db(true);
} catch (Throwable $exception) {
    clickfix_scan_image_error(500, 'db_error');
}

$path = clickfix_scan_asset_absolute_path($reportId, $kind);
if ($path === null || !is_file($path)) {
    clickfix_scan_image_error(404, 'not_found');
}

$status = clickfix_scan_image_review_status($pdo, $reportId, $kind);
$viewer = clickfix_current_user();
$viewerIsAdmin = is_array($viewer) && clickfix_user_has_min_role($viewer, 'admin');
if ($status !== 'approved' && !($previewMode && $viewerIsAdmin)) {
    clickfix_scan_image_error(403, 'approval_required');
}

$extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
$contentType = 'application/octet-stream';
if ($extension === 'png') {
    $contentType = 'image/png';
} elseif ($extension === 'jpg' || $extension === 'jpeg') {
    $contentType = 'image/jpeg';
} elseif ($extension === 'webp') {
    $contentType = 'image/webp';
}

header('Content-Type: ' . $contentType);
header('X-Content-Type-Options: nosniff');
if ($downloadMode) {
    $filename = 'scan-' . $reportId . '-' . $kind . '.' . ($extension !== '' ? $extension : 'bin');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
}
if ($status === 'approved') {
    header('Cache-Control: public, max-age=300');
} else {
    header('Cache-Control: no-store');
}

$size = @filesize($path);
if (is_int($size) && $size > 0) {
    header('Content-Length: ' . (string) $size);
}

@readfile($path);
exit;
