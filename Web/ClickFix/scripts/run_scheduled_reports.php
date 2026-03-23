<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/clickfix_core.php';

clickfix_bootstrap();
$pdo = clickfix_open_db(true);
$results = clickfix_run_due_report_schedules($pdo);

$payload = [
    'status' => 'ok',
    'generated_at' => gmdate('c'),
    'executed' => count($results),
    'results' => $results,
];

echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
