<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

$statsFile = __DIR__ . '/clickfix-stats.log';
$reportFile = __DIR__ . '/clickfix-report.log';

$stats = [
    'total_alerts' => 0,
    'total_blocks' => 0,
    'manual_sites' => [],
    'countries' => [],
    'last_update' => null
];

if (is_readable($statsFile)) {
    $lines = file($statsFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach ($lines as $line) {
        $entry = json_decode($line, true);
        if (!is_array($entry)) {
            continue;
        }
        $statsData = $entry['stats'] ?? [];
        if (!is_array($statsData)) {
            continue;
        }
        $stats['total_alerts'] = max($stats['total_alerts'], (int) ($statsData['alert_count'] ?? 0));
        $stats['total_blocks'] = max($stats['total_blocks'], (int) ($statsData['block_count'] ?? 0));
        $stats['manual_sites'] = array_unique(array_merge($stats['manual_sites'], $statsData['manual_sites'] ?? []));
        $country = $entry['country'] ?? '';
        if ($country !== '') {
            $stats['countries'][$country] = ($stats['countries'][$country] ?? 0) + 1;
        }
        $stats['last_update'] = $entry['received_at'] ?? $stats['last_update'];
    }
}

$recentDetections = [];
if (is_readable($reportFile)) {
    $lines = array_slice(file($reportFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [], -50);
    foreach ($lines as $line) {
        $entry = json_decode($line, true);
        if (!is_array($entry)) {
            continue;
        }
        $detected = trim((string) ($entry['detected_content'] ?? ''));
        if ($detected === '') {
            continue;
        }
        $recentDetections[] = [
            'hostname' => (string) ($entry['hostname'] ?? ''),
            'timestamp' => (string) ($entry['received_at'] ?? ''),
            'detected' => $detected
        ];
    }
}

$stats['manual_sites'] = array_values(array_filter($stats['manual_sites']));
arsort($stats['countries']);
?>
<!doctype html>
<html lang="es">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>ClickFix Dashboard</title>
    <style>
      body {
        font-family: "Segoe UI", system-ui, sans-serif;
        margin: 0;
        padding: 24px;
        background: #f8fafc;
        color: #0f172a;
      }
      header {
        margin-bottom: 24px;
      }
      h1 {
        margin: 0 0 8px;
        font-size: 26px;
      }
      .muted {
        color: #64748b;
        font-size: 14px;
      }
      .grid {
        display: grid;
        gap: 16px;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      }
      .card {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 16px;
      }
      h2 {
        font-size: 18px;
        margin: 0 0 12px;
      }
      ul {
        list-style: none;
        padding: 0;
        margin: 0;
      }
      li {
        padding: 6px 0;
        border-bottom: 1px solid #e2e8f0;
      }
      li:last-child {
        border-bottom: none;
      }
      pre {
        white-space: pre-wrap;
        background: #0f172a;
        color: #e2e8f0;
        padding: 12px;
        border-radius: 10px;
        font-size: 13px;
      }
    </style>
  </head>
  <body>
    <header>
      <h1>ClickFix Dashboard</h1>
      <div class="muted">Última actualización: <?= htmlspecialchars((string) ($stats['last_update'] ?? 'N/D'), ENT_QUOTES, 'UTF-8'); ?></div>
    </header>

    <section class="grid">
      <div class="card">
        <h2>Alertas totales</h2>
        <strong><?= (int) $stats['total_alerts']; ?></strong>
      </div>
      <div class="card">
        <h2>Bloqueos totales</h2>
        <strong><?= (int) $stats['total_blocks']; ?></strong>
      </div>
      <div class="card">
        <h2>Países</h2>
        <?php if (empty($stats['countries'])): ?>
          <div class="muted">Sin datos.</div>
        <?php else: ?>
          <ul>
            <?php foreach ($stats['countries'] as $country => $count): ?>
              <li><?= htmlspecialchars($country, ENT_QUOTES, 'UTF-8'); ?>: <?= (int) $count; ?></li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
      <div class="card">
        <h2>Sitios manuales</h2>
        <?php if (empty($stats['manual_sites'])): ?>
          <div class="muted">Sin sitios.</div>
        <?php else: ?>
          <ul>
            <?php foreach ($stats['manual_sites'] as $site): ?>
              <li><?= htmlspecialchars($site, ENT_QUOTES, 'UTF-8'); ?></li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </section>

    <section class="card" style="margin-top: 24px;">
      <h2>Detecciones recientes</h2>
      <?php if (empty($recentDetections)): ?>
        <div class="muted">Sin detecciones con contenido registrado.</div>
      <?php else: ?>
        <?php foreach ($recentDetections as $entry): ?>
          <p class="muted">
            <?= htmlspecialchars($entry['hostname'], ENT_QUOTES, 'UTF-8'); ?> —
            <?= htmlspecialchars($entry['timestamp'], ENT_QUOTES, 'UTF-8'); ?>
          </p>
          <pre><?= htmlspecialchars($entry['detected'], ENT_QUOTES, 'UTF-8'); ?></pre>
        <?php endforeach; ?>
      <?php endif; ?>
    </section>
  </body>
</html>
