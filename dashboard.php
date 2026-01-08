<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

$dbPath = __DIR__ . '/data/clickfix.sqlite';
$schemaPath = __DIR__ . '/data/clickfix.sql';

$stats = [
    'total_alerts' => 0,
    'total_blocks' => 0,
    'manual_sites' => [],
    'countries' => [],
    'last_update' => null
];

$recentDetections = [];

if (!file_exists($dbPath) && is_readable($schemaPath)) {
    try {
        $pdo = new PDO('sqlite:' . $dbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $schemaSql = file_get_contents($schemaPath);
        if ($schemaSql !== false) {
            $pdo->exec($schemaSql);
        }
    } catch (Throwable $exception) {
        $pdo = null;
    }
}

if (is_readable($dbPath)) {
    try {
        $pdo = new PDO('sqlite:' . $dbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $statsRows = $pdo->query(
            'SELECT received_at, enabled, alert_count, block_count, manual_sites_json, country
             FROM stats
             ORDER BY received_at DESC
             LIMIT 50'
        )->fetchAll(PDO::FETCH_ASSOC);

        foreach ($statsRows as $entry) {
            $stats['total_alerts'] = max($stats['total_alerts'], (int) ($entry['alert_count'] ?? 0));
            $stats['total_blocks'] = max($stats['total_blocks'], (int) ($entry['block_count'] ?? 0));
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

        $reportRows = $pdo->query(
            'SELECT received_at, hostname, detected_content, message
             FROM reports
             ORDER BY received_at DESC
             LIMIT 50'
        )->fetchAll(PDO::FETCH_ASSOC);

        foreach ($reportRows as $entry) {
            $detected = trim((string) ($entry['detected_content'] ?? ''));
            if ($detected === '') {
                $detected = trim((string) ($entry['message'] ?? ''));
            }
            if ($detected === '') {
                continue;
            }
            $recentDetections[] = [
                'hostname' => (string) ($entry['hostname'] ?? ''),
                'timestamp' => (string) ($entry['received_at'] ?? ''),
                'detected' => $detected
            ];
        }
    } catch (Throwable $exception) {
        $stats = $stats;
    }
    $recentDetections = array_slice($recentDetections, 0, 50);
}

ksort($chartData['daily']);
arsort($chartData['countries']);
arsort($chartData['signals']);

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

$signalChartLabels = [];
$signalChartValues = [];
foreach ($chartData['signals'] as $signal => $count) {
    $signalChartLabels[] = $signalLabels[$signal] ?? $signal;
    $signalChartValues[] = $count;
}

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
    ]
];
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
      .section-title {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 12px;
      }
      .chart-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 16px;
      }
      .chart-card {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 12px;
      }
      .chart-card h3 {
        margin: 0 0 10px;
        font-size: 15px;
        color: #0f172a;
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
      .report-card {
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 12px 14px;
        margin-bottom: 12px;
        background: #f8fafc;
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
        color: #64748b;
      }
      .report-section {
        margin-top: 10px;
      }
      .context-panel {
        margin-top: 8px;
        border: 1px dashed #cbd5f5;
        border-radius: 10px;
        padding: 10px;
        background: #eef2ff;
      }
      .context-panel pre {
        background: #111827;
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
      <div class="section-title">
        <h2>Analítica de alertas</h2>
        <span class="muted">Últimos reportes registrados</span>
      </div>
      <div class="chart-grid">
        <div class="chart-card">
          <h3>Alertas por día</h3>
          <canvas id="chart-alerts-day" height="140"></canvas>
        </div>
        <div class="chart-card">
          <h3>Alertas por hora</h3>
          <canvas id="chart-alerts-hour" height="140"></canvas>
        </div>
        <div class="chart-card">
          <h3>Distribución por país</h3>
          <canvas id="chart-alerts-country" height="140"></canvas>
        </div>
        <div class="chart-card">
          <h3>Tipos de señales</h3>
          <canvas id="chart-alerts-signals" height="140"></canvas>
        </div>
      </div>
    </section>

    <section class="card" style="margin-top: 24px;">
      <h2>Detecciones recientes</h2>
      <?php if (empty($recentDetections)): ?>
        <div class="muted">Sin detecciones con contenido registrado.</div>
      <?php else: ?>
        <?php foreach ($recentDetections as $entry): ?>
          <details class="report-card">
            <summary>
              <?= htmlspecialchars($entry['hostname'], ENT_QUOTES, 'UTF-8'); ?>
              <span class="report-meta">
                <?= htmlspecialchars($entry['timestamp'], ENT_QUOTES, 'UTF-8'); ?>
              </span>
            </summary>
            <?php if (!empty($entry['message'])): ?>
              <div class="report-section">
                <strong>Resumen</strong>
                <div class="muted"><?= htmlspecialchars($entry['message'], ENT_QUOTES, 'UTF-8'); ?></div>
              </div>
            <?php endif; ?>
            <?php if (!empty($entry['detected'])): ?>
              <div class="report-section">
                <strong>Contenido detectado</strong>
                <pre><?= htmlspecialchars($entry['detected'], ENT_QUOTES, 'UTF-8'); ?></pre>
              </div>
            <?php endif; ?>
            <?php if (!empty($entry['full_context'])): ?>
              <div class="report-section context-panel">
                <strong>Contexto completo</strong>
                <pre><?= htmlspecialchars($entry['full_context'], ENT_QUOTES, 'UTF-8'); ?></pre>
              </div>
            <?php endif; ?>
          </details>
        <?php endforeach; ?>
      <?php endif; ?>
    </section>
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
          label: "Alertas",
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
          label: "Alertas",
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
          label: "Señales",
          data: chartPayload.signals.values,
          backgroundColor: "#6366f1"
        }]
      }, {
        indexAxis: "y"
      });
    </script>
  </body>
</html>
