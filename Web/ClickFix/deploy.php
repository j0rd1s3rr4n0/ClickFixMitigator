<?php
declare(strict_types=1);

$token = trim((string) ($_GET['token'] ?? ''));
$expectedToken = trim((string) (getenv('CLICKFIX_DEPLOY_TOKEN') ?: ''));

if ($token === '' || $expectedToken === '' || !hash_equals($expectedToken, $token)) {
    http_response_code(403);
    die('Unauthorized');
}

$repoPath = '/home/parthenoun/ClickFix';
$output = [];
$returnCode = 0;

chdir($repoPath);
exec('git pull origin main 2>&1', $output, $returnCode);

echo "Return code: {$returnCode}\n";
echo implode("\n", $output) . "\n";

if ($returnCode === 0) {
    exec('chmod -R 755 ' . escapeshellarg($repoPath . '/Web/ClickFix/src') . ' ' . escapeshellarg($repoPath . '/Web/ClickFix/api') . ' ' . escapeshellarg($repoPath . '/Web/ClickFix/scripts') . ' ' . escapeshellarg($repoPath . '/Web/ClickFix/partials') . ' 2>&1', $o2, $rc2);
    exec('chmod 775 ' . escapeshellarg($repoPath . '/Web/ClickFix/data') . ' 2>&1', $o3, $rc3);
    echo "Permissions set\n";
    exec('php ' . escapeshellarg($repoPath . '/Web/ClickFix/scripts/fetch_all.php') . ' 2>&1', $o4, $rc4);
    echo implode("\n", $o4) . "\n";
}
