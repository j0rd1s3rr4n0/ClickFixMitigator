<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must run in CLI mode.\n");
    exit(1);
}

require_once dirname(__DIR__) . '/src/clickfix_core.php';

$options = getopt('', ['job-id::', 'limit::', 'fetch']);
$jobId = max(0, (int) ($options['job-id'] ?? 0));
$limit = max(1, min(50, (int) ($options['limit'] ?? 5)));
$fetchEnabled = array_key_exists('fetch', $options);

try {
    $pdo = clickfix_open_db(true);
} catch (Throwable $exception) {
    fwrite(STDERR, "[error] unable to open DB: {$exception->getMessage()}\n");
    exit(1);
}

$jobs = [];
if ($jobId > 0) {
    $job = clickfix_investigation_analysis_job_by_id($pdo, $jobId);
    if (!$job) {
        fwrite(STDERR, "[error] job #{$jobId} not found.\n");
        exit(1);
    }
    $jobs[] = $job;
} else {
    $stmt = $pdo->prepare(
        "SELECT *
         FROM investigation_analysis_jobs
         WHERE status IN ('queued', 'running')
         ORDER BY id ASC
         LIMIT :limit"
    );
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $jobs = $stmt->fetchAll() ?: [];
}

if (empty($jobs)) {
    echo "[ok] no queued correlation jobs.\n";
    exit(0);
}

foreach ($jobs as $job) {
    $currentJobId = (int) ($job['id'] ?? 0);
    $graphId = (int) ($job['graph_id'] ?? 0);
    echo "[run] job #{$currentJobId} graph #{$graphId}\n";
    try {
        $result = clickfix_investigation_run_correlation_job($pdo, $currentJobId, [
            'enable_fetch' => $fetchEnabled,
        ]);
        if (!empty($result['ok'])) {
            echo "[ok] processed artifacts: " . (int) ($result['processed_artifacts'] ?? 0) . "\n";
        } else {
            $error = (string) ($result['error'] ?? 'unknown error');
            echo "[error] {$error}\n";
        }
    } catch (Throwable $exception) {
        clickfix_investigation_analysis_job_set_state($pdo, $currentJobId, 'failed', 0, $exception->getMessage());
        fwrite(STDERR, "[error] job #{$currentJobId} failed: {$exception->getMessage()}\n");
    }
}
