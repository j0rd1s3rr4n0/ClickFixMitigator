<?php
declare(strict_types=1);

require_once __DIR__ . '/clickfix_core.php';
require_once __DIR__ . '/clickfix_llm.php';

function clickfix_auto_investigation_setting(PDO $pdo, string $key, string $default = ''): string
{
    if (!clickfix_has_table($pdo, 'auto_investigation_settings')) {
        return $default;
    }
    $stmt = $pdo->prepare('SELECT setting_value FROM auto_investigation_settings WHERE setting_key = :key LIMIT 1');
    $stmt->execute([':key' => $key]);
    $val = $stmt->fetchColumn();
    return is_string($val) ? $val : $default;
}

function clickfix_auto_investigation_set_settings(PDO $pdo, array $settings): bool
{
    if (!clickfix_has_table($pdo, 'auto_investigation_settings')) {
        return false;
    }
    $stmt = $pdo->prepare('INSERT OR REPLACE INTO auto_investigation_settings (setting_key, setting_value, updated_at) VALUES (:key, :val, :at)');
    foreach ($settings as $key => $value) {
        $stmt->execute([':key' => (string) $key, ':val' => (string) $value, ':at' => gmdate('c')]);
    }
    return true;
}

function clickfix_auto_investigation_is_enabled(PDO $pdo): bool
{
    return clickfix_auto_investigation_setting($pdo, 'enabled', '0') === '1';
}

function clickfix_auto_investigation_scan_new_alerts(PDO $pdo): array
{
    $minScore = max(0, min(100, (int) clickfix_auto_investigation_setting($pdo, 'min_score', '60')));
    $stmt = $pdo->prepare(
        "SELECT id, hostname, url, previous_url, detected_content, message, full_context, score_total, received_at, country,
                signals_json, reason_entries_json, matched_snippets_json, client_id
         FROM reports
         WHERE review_status IS NULL OR review_status = '' OR LOWER(TRIM(review_status)) = 'pending'
           AND score_total >= :min_score
           AND id NOT IN (SELECT DISTINCT COALESCE(report_id, 0) FROM auto_investigation_jobs WHERE report_id IS NOT NULL)
         ORDER BY score_total DESC, id DESC
         LIMIT 20"
    );
    $stmt->execute([':min_score' => $minScore]);
    return $stmt->fetchAll();
}

function clickfix_auto_investigation_create_from_alert(PDO $pdo, array $reportRow, int $profileId = 0, int $actorId = 0): ?array
{
    $reportId = (int) ($reportRow['id'] ?? 0);
    $hostname = clickfix_normalize_domain((string) ($reportRow['hostname'] ?? ''));
    $url = (string) ($reportRow['url'] ?? '');
    $message = (string) ($reportRow['message'] ?? '');
    $detectedContent = (string) ($reportRow['detected_content'] ?? '');
    $fullContext = (string) ($reportRow['full_context'] ?? '');
    $score = (int) ($reportRow['score_total'] ?? 0);
    $reasonEntriesJson = (string) ($reportRow['reason_entries_json'] ?? '[]');
    $signalsJson = (string) ($reportRow['signals_json'] ?? '[]');
    $snippetsJson = (string) ($reportRow['matched_snippets_json'] ?? '[]');
    $reasons = json_decode($reasonEntriesJson, true) ?: [];
    $signals = json_decode($signalsJson, true) ?: [];
    $snippets = json_decode($snippetsJson, true) ?: [];
    $title = 'Auto: ' . ($hostname !== '' ? $hostname : (parse_url($url, PHP_URL_HOST) ?: 'Alert #' . $reportId));
    $summaryParts = [];
    if (!empty($reasons)) {
        $summaryParts[] = 'Reasons: ' . implode(', ', array_slice(array_map(static function ($r) { return is_array($r) ? ((string) ($r['label'] ?? $r['reason'] ?? '')) : (string) $r; }, $reasons), 0, 5));
    }
    if (!empty($signals)) {
        $signalLabels = is_array($signals) ? array_slice(array_map(static function ($s) { return is_array($s) ? ((string) ($s['label'] ?? $s['signal'] ?? '')) : (string) $s; }, $signals), 0, 4) : [];
        $summaryParts[] = 'Signals: ' . implode(', ', $signalLabels);
    }
    $summary = implode('. ', $summaryParts);
    if ($summary === '') {
        $summary = 'Auto-generated investigation from alert #' . $reportId . ' (score: ' . $score . '/100).';
    }
    $nodes = [];
    $edges = [];
    $nodeId = static function (string $prefix, int $idx): string {
        return $prefix . '_' . $idx;
    };
    $nodes[] = [
        'id' => $nodeId('n', 1),
        'type' => 'alert',
        'label' => $hostname !== '' ? $hostname : 'Alert #' . $reportId,
        'content' => 'Score: ' . $score . '/100 | ' . substr($message, 0, 200),
        'x' => 400, 'y' => 300,
    ];
    $nodeIdx = 2;
    if ($hostname !== '') {
        $nodes[] = ['id' => $nodeId('n', $nodeIdx), 'type' => 'domain', 'label' => $hostname, 'content' => 'Primary domain from alert', 'x' => 600, 'y' => 300];
        $edges[] = ['id' => $nodeId('e', 1), 'source' => $nodeId('n', 1), 'target' => $nodeId('n', $nodeIdx), 'label' => 'evento detectado'];
        $nodeIdx++;
    }
    $iocText = implode("\n", array_filter([$message, $detectedContent, $fullContext]));
    $extractedIocs = cfintel_extract_artifacts_from_text($iocText);
    foreach (array_slice($extractedIocs, 0, 15) as $ioc) {
        $nodes[] = ['id' => $nodeId('n', $nodeIdx), 'type' => (string) ($ioc['type'] ?? 'unknown'), 'label' => (string) ($ioc['value'] ?? ''), 'content' => 'Extracted from alert context', 'x' => 400 + ($nodeIdx * 50) % 400, 'y' => 500 + intdiv($nodeIdx, 4) * 80];
        $edges[] = ['id' => $nodeId('e', $nodeIdx - 1), 'source' => $nodeId('n', 1), 'target' => $nodeId('n', $nodeIdx), 'label' => 'contiene IOC'];
        $nodeIdx++;
    }
    if (!empty($snippets)) {
        $snipNodeId = $nodeId('n', $nodeIdx);
        $nodes[] = ['id' => $snipNodeId, 'type' => 'evidence', 'label' => 'Snippets (' . count($snippets) . ')', 'content' => implode("\n", array_slice(array_map(static function ($s) { return is_array($s) ? ((string) ($s['snippet'] ?? '')) : (string) $s; }, $snippets), 0, 5)), 'x' => 200, 'y' => 500];
        $edges[] = ['id' => $nodeId('e', $nodeIdx), 'source' => $snipNodeId, 'target' => $nodeId('n', 1), 'label' => 'evidencia'];
        $nodeIdx++;
    }
    $graph = ['nodes' => $nodes, 'edges' => $edges];
    $verdict = $score >= 80 ? 'suspicious' : ($score >= 60 ? 'investigating' : 'unknown');
    $saved = clickfix_investigation_save(
        $pdo,
        0,
        $actorId,
        $title,
        $hostname,
        $verdict,
        $summary,
        $graph,
        'draft',
        '',
        false,
        false,
        $reportId,
        []
    );
    if ($saved === null) {
        return null;
    }
    $maxDepth = max(1, min(8, (int) clickfix_auto_investigation_setting($pdo, 'max_depth', '3')));
    clickfix_investigation_enqueue_alert_correlation($pdo, (int) ($saved['id'] ?? 0), $actorId, $maxDepth);
    return $saved;
}

function clickfix_auto_investigation_enqueue_job(PDO $pdo, int $reportId, int $graphId, int $profileId, string $stage = 'detect'): ?int
{
    if (!clickfix_has_table($pdo, 'auto_investigation_jobs')) {
        return null;
    }
    $stmt = $pdo->prepare('INSERT INTO auto_investigation_jobs (report_id, graph_id, profile_id, status, stage, created_at) VALUES (:rid, :gid, :pid, :status, :stage, :at)');
    $stmt->execute([':rid' => $reportId > 0 ? $reportId : null, ':gid' => $graphId > 0 ? $graphId : null, ':pid' => $profileId, ':status' => 'queued', ':stage' => $stage, ':at' => gmdate('c')]);
    return (int) $pdo->lastInsertId();
}

function clickfix_auto_investigation_job_set_status(PDO $pdo, int $jobId, string $status, string $stage = '', array $result = [], string $error = ''): bool
{
    if (!clickfix_has_table($pdo, 'auto_investigation_jobs')) {
        return false;
    }
    $fields = [];
    $params = [':id' => $jobId];
    if ($status !== '') {
        $fields[] = 'status = :status';
        $params[':status'] = $status;
    }
    if ($stage !== '') {
        $fields[] = 'stage = :stage';
        $params[':stage'] = $stage;
    }
    if ($status === 'running' && $stage === 'detect') {
        $fields[] = 'started_at = :started';
        $params[':started'] = gmdate('c');
    }
    if (in_array($status, ['completed', 'failed'], true)) {
        $fields[] = 'completed_at = :completed';
        $params[':completed'] = gmdate('c');
    }
    if (!empty($result)) {
        $fields[] = 'result_json = :result';
        $params[':result'] = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    if ($error !== '') {
        $fields[] = 'error = :error';
        $params[':error'] = $error;
    }
    if (empty($fields)) {
        return false;
    }
    $sql = 'UPDATE auto_investigation_jobs SET ' . implode(', ', $fields) . ' WHERE id = :id';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->rowCount() > 0;
}

function clickfix_auto_investigation_pending_jobs(PDO $pdo, int $limit = 10): array
{
    if (!clickfix_has_table($pdo, 'auto_investigation_jobs')) {
        return [];
    }
    $stmt = $pdo->prepare('SELECT * FROM auto_investigation_jobs WHERE status = :status ORDER BY id ASC LIMIT :limit');
    $stmt->bindValue(':status', 'queued', PDO::PARAM_STR);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function clickfix_auto_investigation_recent_jobs(PDO $pdo, int $limit = 30): array
{
    if (!clickfix_has_table($pdo, 'auto_investigation_jobs')) {
        return [];
    }
    $stmt = $pdo->prepare(
        "SELECT aij.*, r.hostname AS report_hostname, r.score_total AS report_score,
                ig.title AS graph_title, ig.site_domain AS graph_domain
         FROM auto_investigation_jobs aij
         LEFT JOIN reports r ON r.id = aij.report_id
         LEFT JOIN investigation_graphs ig ON ig.id = aij.graph_id
         ORDER BY aij.id DESC
         LIMIT :limit"
    );
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function clickfix_auto_investigation_run_job(PDO $pdo, int $jobId): array
{
    if (!clickfix_has_table($pdo, 'auto_investigation_jobs')) {
        return ['ok' => false, 'error' => 'no_table'];
    }
    $stmt = $pdo->prepare('SELECT * FROM auto_investigation_jobs WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $jobId]);
    $job = $stmt->fetch();
    if (!$job) {
        return ['ok' => false, 'error' => 'job_not_found'];
    }
    clickfix_auto_investigation_job_set_status($pdo, $jobId, 'running', 'detect');
    $reportId = (int) ($job['report_id'] ?? 0);
    $graphId = (int) ($job['graph_id'] ?? 0);
    $profileId = (int) ($job['profile_id'] ?? 0);
    if ($reportId > 0 && $graphId <= 0) {
        $report = clickfix_report_by_id($pdo, $reportId);
        if ($report === null) {
            clickfix_auto_investigation_job_set_status($pdo, $jobId, 'failed', '', [], 'report_not_found');
            return ['ok' => false, 'error' => 'report_not_found'];
        }
        $investigation = clickfix_auto_investigation_create_from_alert($pdo, $report, $profileId);
        if ($investigation === null) {
            clickfix_auto_investigation_job_set_status($pdo, $jobId, 'failed', '', [], 'investigation_create_failed');
            return ['ok' => false, 'error' => 'investigation_create_failed'];
        }
        $graphId = (int) ($investigation['id'] ?? 0);
        $pdo->prepare('UPDATE auto_investigation_jobs SET graph_id = :gid WHERE id = :id')->execute([':gid' => $graphId, ':id' => $jobId]);
    }
    $enrichmentResults = [];
    if ($graphId > 0) {
        $enrichDone = clickfix_investigation_rebuild_pipeline_graph($pdo, $graphId, 0);
        $enrichmentResults['pipeline_rebuild'] = $enrichDone;
        $maxDepth = max(1, min(8, (int) clickfix_auto_investigation_setting($pdo, 'max_depth', '3')));
        clickfix_investigation_enqueue_alert_correlation($pdo, $graphId, 0, $maxDepth);
        $enrichmentResults['correlation_enqueued'] = true;
        $llmEnrich = clickfix_auto_investigation_setting($pdo, 'llm_enrich', '0') === '1';
        if ($llmEnrich && $profileId > 0) {
            $llmProfileId = max(0, (int) clickfix_auto_investigation_setting($pdo, 'llm_profile_id', (string) $profileId));
            if ($llmProfileId > 0) {
                $llmResult = clickfix_llm_summarize_investigation($pdo, $graphId, ['profile_id' => $llmProfileId, 'temperature' => 0.3]);
                $enrichmentResults['llm_summary'] = $llmResult['ok'] ?? false;
                if ($llmResult['ok']) {
                    $pdo->prepare('UPDATE investigation_graphs SET summary = :summary WHERE id = :id')->execute([':summary' => substr(((string) ($investigation['summary'] ?? '')) . "\n\n[AI Analysis]\n" . $llmResult['content'], 0, 5000), ':id' => $graphId]);
                }
                $iocsText = (string) ($report['message'] ?? '') . "\n" . (string) ($report['detected_content'] ?? '');
                $iocResult = clickfix_llm_extract_iocs($pdo, $iocsText, ['profile_id' => $llmProfileId, 'temperature' => 0.1]);
                $enrichmentResults['llm_iocs_extracted'] = count($iocResult['iocs'] ?? []);
                foreach (($iocResult['iocs'] ?? []) as $ioc) {
                    clickfix_investigation_artifact_upsert($pdo, [
                        'graph_id' => $graphId,
                        'job_id' => 0,
                        'type' => (string) ($ioc['type'] ?? 'unknown'),
                        'value' => (string) ($ioc['value'] ?? ''),
                        'source' => 'llm_extraction',
                        'confidence' => 70,
                        'notes_json' => '{}',
                    ]);
                }
            }
        }
    }
    clickfix_auto_investigation_job_set_status($pdo, $jobId, 'completed', 'enrich', $enrichmentResults);
    return ['ok' => true, 'graph_id' => $graphId, 'enrichment' => $enrichmentResults];
}

function clickfix_auto_investigation_run_pending(PDO $pdo, int $maxJobs = 5): array
{
    if (!clickfix_auto_investigation_is_enabled($pdo)) {
        return ['ok' => false, 'error' => 'auto_investigation_disabled', 'processed' => 0];
    }
    $newAlerts = clickfix_auto_investigation_scan_new_alerts($pdo);
    $created = 0;
    foreach ($newAlerts as $alert) {
        $reportId = (int) ($alert['id'] ?? 0);
        $profileId = max(0, (int) clickfix_auto_investigation_setting($pdo, 'llm_profile_id', '0'));
        $jobId = clickfix_auto_investigation_enqueue_job($pdo, $reportId, 0, $profileId, 'detect');
        if ($jobId !== null) {
            $created++;
        }
    }
    $pending = clickfix_auto_investigation_pending_jobs($pdo, $maxJobs);
    $processed = 0;
    $results = [];
    foreach ($pending as $job) {
        $result = clickfix_auto_investigation_run_job($pdo, (int) ($job['id'] ?? 0));
        $results[] = array_merge(['job_id' => (int) ($job['id'] ?? 0)], $result);
        if ($result['ok']) {
            $processed++;
        }
        if ($processed >= $maxJobs) {
            break;
        }
    }
    return ['ok' => true, 'new_alerts' => count($newAlerts), 'jobs_enqueued' => $created, 'jobs_processed' => $processed, 'results' => $results];
}

function clickfix_auto_investigation_worker_batch(PDO $pdo): array
{
    clickfix_llm_ensure_table($pdo);
    return clickfix_auto_investigation_run_pending($pdo, 8);
}
