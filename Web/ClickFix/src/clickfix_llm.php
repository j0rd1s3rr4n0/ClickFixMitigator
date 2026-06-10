<?php
declare(strict_types=1);

require_once __DIR__ . '/clickfix_core.php';

function clickfix_llm_provider_types(): array
{
    return ['openai', 'lmstudio', 'anthropic', 'custom'];
}

function clickfix_llm_provider_label(string $provider): string
{
    $map = [
        'openai' => 'OpenAI Compatible',
        'lmstudio' => 'LM Studio',
        'anthropic' => 'Anthropic Compatible',
        'custom' => 'Custom Endpoint',
    ];
    return $map[strtolower(trim($provider))] ?? $provider;
}

function clickfix_llm_default_headers(string $provider): array
{
    $headers = [
        'User-Agent' => 'ClickFixMitigator/1.0',
        'Content-Type' => 'application/json',
    ];
    switch (strtolower(trim($provider))) {
        case 'openai':
            $headers['Authorization'] = 'Bearer ' . clickfix_env('CLICKFIX_LLM_OPENAI_KEY', '');
            break;
        case 'lmstudio':
            break;
        case 'anthropic':
            $headers['x-api-key'] = clickfix_env('CLICKFIX_LLM_ANTHROPIC_KEY', '');
            $headers['anthropic-version'] = '2023-06-01';
            break;
    }
    return $headers;
}

function clickfix_llm_configured_providers(PDO $pdo, int $userId = 0): array
{
    $profiles = [];
    if (clickfix_has_table($pdo, 'user_llm_profiles')) {
        $hasUserIdCol = clickfix_has_column($pdo, 'user_llm_profiles', 'user_id');
        $sql = 'SELECT id, label, provider, base_url, model, api_key, extra_headers_json, is_active, created_at, updated_at FROM user_llm_profiles WHERE is_active = 1';
        if ($hasUserIdCol && $userId > 0) {
            $sql .= ' AND (user_id = :uid OR user_id = 0)';
        }
        $sql .= ' ORDER BY label ASC';
        $stmt = $pdo->prepare($sql);
        if ($hasUserIdCol && $userId > 0) {
            $stmt->execute([':uid' => $userId]);
        } else {
            $stmt->execute();
        }
        while ($row = $stmt->fetch()) {
            $extra = json_decode((string) ($row['extra_headers_json'] ?? '{}'), true);
            $profiles[] = [
                'id' => (int) ($row['id'] ?? 0),
                'user_id' => $hasUserIdCol ? (int) ($row['user_id'] ?? 0) : 0,
                'label' => (string) ($row['label'] ?? ''),
                'provider' => strtolower(trim((string) ($row['provider'] ?? 'openai'))),
                'base_url' => rtrim((string) ($row['base_url'] ?? ''), '/'),
                'model' => (string) ($row['model'] ?? ''),
                'api_key' => (string) ($row['api_key'] ?? ''),
                'extra_headers' => is_array($extra) ? $extra : [],
                'is_active' => !empty($row['is_active']),
            ];
        }
    }
    $envProfiles = clickfix_llm_env_profiles();
    foreach ($envProfiles as $ep) {
        $ep['user_id'] = 0;
        $profiles[] = $ep;
    }
    return $profiles;
}

function clickfix_llm_env_profiles(): array
{
    $profiles = [];
    $openaiUrl = trim((string) clickfix_env('CLICKFIX_LLM_OPENAI_URL', ''));
    $openaiKey = trim((string) clickfix_env('CLICKFIX_LLM_OPENAI_KEY', ''));
    $openaiModel = trim((string) clickfix_env('CLICKFIX_LLM_OPENAI_MODEL', 'gpt-4o'));
    if ($openaiUrl !== '' && $openaiKey !== '') {
        $profiles[] = [
            'id' => 0,
            'label' => 'OpenAI (env)',
            'provider' => 'openai',
            'base_url' => rtrim($openaiUrl, '/'),
            'model' => $openaiModel !== '' ? $openaiModel : 'gpt-4o',
            'api_key' => $openaiKey,
            'extra_headers' => [],
            'is_active' => true,
        ];
    }
    $lmstudioUrl = trim((string) clickfix_env('CLICKFIX_LLM_LMSTUDIO_URL', ''));
    $lmstudioModel = trim((string) clickfix_env('CLICKFIX_LLM_LMSTUDIO_MODEL', ''));
    if ($lmstudioUrl !== '') {
        $profiles[] = [
            'id' => 0,
            'label' => 'LM Studio (env)',
            'provider' => 'lmstudio',
            'base_url' => rtrim($lmstudioUrl, '/'),
            'model' => $lmstudioModel !== '' ? $lmstudioModel : 'local-model',
            'api_key' => '',
            'extra_headers' => [],
            'is_active' => true,
        ];
    }
    $anthropicUrl = trim((string) clickfix_env('CLICKFIX_LLM_ANTHROPIC_URL', ''));
    $anthropicKey = trim((string) clickfix_env('CLICKFIX_LLM_ANTHROPIC_KEY', ''));
    $anthropicModel = trim((string) clickfix_env('CLICKFIX_LLM_ANTHROPIC_MODEL', 'claude-sonnet-4-20250514'));
    if ($anthropicUrl !== '' && $anthropicKey !== '') {
        $profiles[] = [
            'id' => 0,
            'label' => 'Anthropic (env)',
            'provider' => 'anthropic',
            'base_url' => rtrim($anthropicUrl, '/'),
            'model' => $anthropicModel !== '' ? $anthropicModel : 'claude-sonnet-4-20250514',
            'api_key' => $anthropicKey,
            'extra_headers' => [],
            'is_active' => true,
        ];
    }
    $customUrl = trim((string) clickfix_env('CLICKFIX_LLM_CUSTOM_URL', ''));
    $customKey = trim((string) clickfix_env('CLICKFIX_LLM_CUSTOM_KEY', ''));
    $customModel = trim((string) clickfix_env('CLICKFIX_LLM_CUSTOM_MODEL', ''));
    if ($customUrl !== '') {
        $profiles[] = [
            'id' => 0,
            'label' => 'Custom (env)',
            'provider' => 'custom',
            'base_url' => rtrim($customUrl, '/'),
            'model' => $customModel !== '' ? $customModel : 'default',
            'api_key' => $customKey,
            'extra_headers' => [],
            'is_active' => true,
        ];
    }
    return $profiles;
}

function clickfix_llm_profile_by_id(PDO $pdo, int $profileId, int $userId = 0): ?array
{
    foreach (clickfix_llm_configured_providers($pdo, $userId) as $profile) {
        if ((int) ($profile['id'] ?? 0) === $profileId) {
            return $profile;
        }
    }
    return null;
}

function clickfix_llm_resolve_endpoint(string $provider, string $baseUrl, string $operation): string
{
    $base = rtrim($baseUrl, '/');
    $provider = strtolower(trim($provider));
    if ($provider === 'custom') {
        $override = trim((string) clickfix_env('CLICKFIX_LLM_CUSTOM_CHAT_PATH', ''));
        if ($operation === 'chat' && $override !== '') {
            return $base . '/' . ltrim($override, '/');
        }
        $override = trim((string) clickfix_env('CLICKFIX_LLM_CUSTOM_MODELS_PATH', ''));
        if ($operation === 'models' && $override !== '') {
            return $base . '/' . ltrim($override, '/');
        }
        $override = trim((string) clickfix_env('CLICKFIX_LLM_CUSTOM_EMBED_PATH', ''));
        if ($operation === 'embeddings' && $override !== '') {
            return $base . '/' . ltrim($override, '/');
        }
    }
    $endpoints = [
        'openai' => [
            'models' => '/v1/models',
            'chat' => '/v1/chat/completions',
            'completions' => '/v1/completions',
            'embeddings' => '/v1/embeddings',
            'responses' => '/v1/responses',
        ],
        'lmstudio' => [
            'models' => '/api/v1/models',
            'chat' => '/api/v1/chat',
            'load' => '/api/v1/models/load',
            'download' => '/api/v1/models/download',
        ],
        'anthropic' => [
            'chat' => '/v1/messages',
            'models' => '/v1/models',
        ],
        'custom' => [
            'models' => '/v1/models',
            'chat' => '/v1/chat/completions',
            'completions' => '/v1/completions',
            'embeddings' => '/v1/embeddings',
        ],
    ];
    return $base . ($endpoints[$provider][$operation] ?? $endpoints['custom'][$operation] ?? '/v1/chat/completions');
}

function clickfix_llm_build_body(string $provider, string $model, array $messages, array $options = []): array
{
    $provider = strtolower(trim($provider));
    $maxTokens = (int) ($options['max_tokens'] ?? 4096);
    $temperature = (float) ($options['temperature'] ?? 0.7);
    $systemPrompt = (string) ($options['system'] ?? '');
    if ($systemPrompt !== '') {
        array_unshift($messages, ['role' => 'system', 'content' => $systemPrompt]);
    }
    if ($provider === 'anthropic') {
        $systemMsg = '';
        $chatMessages = [];
        foreach ($messages as $msg) {
            if (($msg['role'] ?? '') === 'system') {
                $systemMsg = (string) ($msg['content'] ?? '');
            } else {
                $chatMessages[] = $msg;
            }
        }
        $body = [
            'model' => $model,
            'max_tokens' => $maxTokens,
            'messages' => $chatMessages,
        ];
        if ($systemMsg !== '') {
            $body['system'] = $systemMsg;
        }
        if ($temperature >= 0) {
            $body['temperature'] = $temperature;
        }
        return $body;
    }
    $body = [
        'model' => $model,
        'messages' => $messages,
        'max_tokens' => $maxTokens,
        'temperature' => $temperature,
    ];
    if (($options['stream'] ?? false)) {
        $body['stream'] = true;
    }
    if (($options['top_p'] ?? null) !== null) {
        $body['top_p'] = (float) $options['top_p'];
    }
    if (($options['frequency_penalty'] ?? null) !== null) {
        $body['frequency_penalty'] = (float) $options['frequency_penalty'];
    }
    if (($options['presence_penalty'] ?? null) !== null) {
        $body['presence_penalty'] = (float) $options['presence_penalty'];
    }
    return $body;
}

function clickfix_llm_call(PDO $pdo, int $profileId, array $messages, array $options = []): array
{
    $profile = clickfix_llm_profile_by_id($pdo, $profileId);
    if ($profile === null) {
        return ['ok' => false, 'error' => 'llm_profile_not_found', 'content' => '', 'tokens' => null];
    }
    $provider = (string) ($profile['provider'] ?? 'openai');
    $baseUrl = (string) ($profile['base_url'] ?? '');
    $model = (string) ($profile['model'] ?? '');
    $apiKey = (string) ($profile['api_key'] ?? '');
    $extraHeaders = is_array($profile['extra_headers'] ?? null) ? $profile['extra_headers'] : [];
    $overrideModel = (string) ($options['model'] ?? '');
    if ($overrideModel !== '') {
        $model = $overrideModel;
    }
    if ($baseUrl === '') {
        return ['ok' => false, 'error' => 'llm_no_base_url', 'content' => '', 'tokens' => null];
    }
    if ($model === '') {
        return ['ok' => false, 'error' => 'llm_no_model', 'content' => '', 'tokens' => null];
    }
    if (empty($messages)) {
        return ['ok' => false, 'error' => 'llm_no_messages', 'content' => '', 'tokens' => null];
    }
    $endpoint = clickfix_llm_resolve_endpoint($provider, $baseUrl, 'chat');
    $body = clickfix_llm_build_body($provider, $model, $messages, $options);
    $headers = clickfix_llm_default_headers($provider);
    if ($apiKey !== '') {
        if ($provider === 'anthropic') {
            $headers['x-api-key'] = $apiKey;
        } else {
            $headers['Authorization'] = 'Bearer ' . $apiKey;
        }
    }
    $overrideAgent = (string) ($options['user_agent'] ?? '');
    if ($overrideAgent !== '') {
        $headers['User-Agent'] = $overrideAgent;
    }
    $overrideBearer = (string) ($options['bearer_token'] ?? '');
    if ($overrideBearer !== '') {
        $headers['Authorization'] = 'Bearer ' . $overrideBearer;
    }
    foreach ($extraHeaders as $hKey => $hValue) {
        $headers[(string) $hKey] = (string) $hValue;
    }
    $customHeaders = is_array($options['headers'] ?? null) ? $options['headers'] : [];
    foreach ($customHeaders as $hKey => $hValue) {
        $headers[(string) $hKey] = (string) $hValue;
    }
    $headerLines = [];
    foreach ($headers as $k => $v) {
        if ($v === '') {
            continue;
        }
        $headerLines[] = $k . ': ' . $v;
    }
    $jsonBody = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($jsonBody === false) {
        return ['ok' => false, 'error' => 'llm_json_encode_failed', 'content' => '', 'tokens' => null];
    }
    $timeout = max(5, min(300, (int) ($options['timeout'] ?? 120)));
    $response = clickfix_http_fetch($endpoint, [
        'method' => 'POST',
        'body' => $jsonBody,
        'headers' => $headers,
        'timeout' => $timeout,
    ]);
    if ($response === null) {
        return ['ok' => false, 'error' => 'llm_http_failed', 'content' => '', 'tokens' => null];
    }
    $data = json_decode($response, true);
    if (!is_array($data)) {
        return ['ok' => false, 'error' => 'llm_invalid_json_response', 'raw' => substr($response, 0, 500), 'content' => '', 'tokens' => null];
    }
    if (isset($data['error'])) {
        $errMsg = is_array($data['error']) ? ((string) ($data['error']['message'] ?? 'API error')) : (string) $data['error'];
        return ['ok' => false, 'error' => $errMsg, 'content' => '', 'tokens' => null];
    }
    return clickfix_llm_parse_response($provider, $data);
}

function clickfix_llm_parse_response(string $provider, array $data): array
{
    $provider = strtolower(trim($provider));
    $content = '';
    $tokens = null;
    if ($provider === 'anthropic') {
        $contentBlocks = is_array($data['content'] ?? null) ? $data['content'] : [];
        foreach ($contentBlocks as $block) {
            if (($block['type'] ?? '') === 'text') {
                $content .= (string) ($block['text'] ?? '');
            }
        }
        $usage = is_array($data['usage'] ?? null) ? $data['usage'] : null;
        if ($usage !== null) {
            $tokens = [
                'input' => (int) ($usage['input_tokens'] ?? 0),
                'output' => (int) ($usage['output_tokens'] ?? 0),
                'total' => ((int) ($usage['input_tokens'] ?? 0)) + ((int) ($usage['output_tokens'] ?? 0)),
            ];
        }
    } elseif ($provider === 'lmstudio') {
        $choices = is_array($data['choices'] ?? null) ? $data['choices'] : [];
        if (!empty($choices)) {
            $content = (string) ($choices[0]['message']['content'] ?? $choices[0]['text'] ?? '');
        }
        $usage = is_array($data['usage'] ?? null) ? $data['usage'] : null;
        if ($usage !== null) {
            $tokens = [
                'input' => (int) ($usage['prompt_tokens'] ?? 0),
                'output' => (int) ($usage['completion_tokens'] ?? 0),
                'total' => (int) ($usage['total_tokens'] ?? 0),
            ];
        }
    } else {
        $choices = is_array($data['choices'] ?? null) ? $data['choices'] : [];
        if (!empty($choices)) {
            $content = (string) ($choices[0]['message']['content'] ?? $choices[0]['text'] ?? '');
        }
        $usage = is_array($data['usage'] ?? null) ? $data['usage'] : null;
        if ($usage !== null) {
            $tokens = [
                'input' => (int) ($usage['prompt_tokens'] ?? 0),
                'output' => (int) ($usage['completion_tokens'] ?? 0),
                'total' => (int) ($usage['total_tokens'] ?? 0),
            ];
        }
    }
    return [
        'ok' => true,
        'content' => $content,
        'tokens' => $tokens,
        'model' => (string) ($data['model'] ?? ''),
        'id' => (string) ($data['id'] ?? ''),
        'raw' => $data,
    ];
}

function clickfix_llm_list_models(PDO $pdo, int $profileId): array
{
    $profile = clickfix_llm_profile_by_id($pdo, $profileId);
    if ($profile === null) {
        return ['ok' => false, 'error' => 'llm_profile_not_found', 'models' => []];
    }
    $provider = (string) ($profile['provider'] ?? 'openai');
    $baseUrl = (string) ($profile['base_url'] ?? '');
    $apiKey = (string) ($profile['api_key'] ?? '');
    $extraHeaders = is_array($profile['extra_headers'] ?? null) ? $profile['extra_headers'] : [];
    if ($baseUrl === '') {
        return ['ok' => false, 'error' => 'llm_no_base_url', 'models' => []];
    }
    $endpoint = clickfix_llm_resolve_endpoint($provider, $baseUrl, 'models');
    $headers = clickfix_llm_default_headers($provider);
    if ($apiKey !== '') {
        if ($provider === 'anthropic') { $headers['x-api-key'] = $apiKey; }
        else { $headers['Authorization'] = 'Bearer ' . $apiKey; }
    }
    foreach ($extraHeaders as $hKey => $hValue) { $headers[(string) $hKey] = (string) $hValue; }
    $response = clickfix_http_fetch($endpoint, ['method' => 'GET', 'headers' => $headers, 'timeout' => 20]);
    if ($response === null) {
        return ['ok' => false, 'error' => 'http_failed', 'models' => []];
    }
    $data = json_decode($response, true);
    if (!is_array($data)) {
        return ['ok' => false, 'error' => 'invalid_json', 'models' => []];
    }
    $models = [];
    $rawModels = is_array($data['data'] ?? null) ? $data['data'] : (is_array($data['models'] ?? null) ? $data['models'] : []);
    foreach ($rawModels as $m) {
        $models[] = [
            'id' => (string) ($m['id'] ?? $m['name'] ?? $m['model'] ?? ''),
            'name' => (string) ($m['id'] ?? $m['name'] ?? $m['model'] ?? ''),
            'provider' => $provider,
        ];
    }
    if (empty($models) && is_array($data) && count($data) > 0) {
        foreach ($data as $key => $m) {
            if (is_array($m) && isset($m['id'])) {
                $models[] = ['id' => (string) $m['id'], 'name' => (string) $m['id'], 'provider' => $provider];
            }
        }
    }
    return ['ok' => true, 'models' => $models];
}

function clickfix_llm_summarize_investigation(PDO $pdo, int $graphId, array $options = []): array
{
    $profileId = (int) ($options['profile_id'] ?? clickfix_llm_default_profile_id($pdo));
    if ($profileId <= 0) {
        return ['ok' => false, 'error' => 'no_llm_profile', 'content' => ''];
    }
    $investigation = clickfix_get_investigation_any($pdo, $graphId);
    if ($investigation === null) {
        return ['ok' => false, 'error' => 'investigation_not_found', 'content' => ''];
    }
    $graph = is_array($investigation['graph'] ?? null) ? $investigation['graph'] : ['nodes' => [], 'edges' => []];
    $nodes = is_array($graph['nodes'] ?? null) ? $graph['nodes'] : [];
    $edges = is_array($graph['edges'] ?? null) ? $graph['edges'] : [];
    $contextText = "Investigation: " . ((string) ($investigation['title'] ?? 'Untitled')) . "\n";
    $contextText .= "Domain: " . ((string) ($investigation['site_domain'] ?? 'N/A')) . "\n";
    $contextText .= "Verdict: " . ((string) ($investigation['verdict'] ?? 'unknown')) . "\n";
    $contextText .= "Summary: " . ((string) ($investigation['summary'] ?? 'N/A')) . "\n\n";
    $contextText .= "Graph Nodes (" . count($nodes) . "):\n";
    foreach (array_slice($nodes, 0, 40) as $node) {
        $contextText .= "- [" . ((string) ($node['type'] ?? '?')) . "] " . ((string) ($node['label'] ?? $node['id'] ?? '')) . "\n";
        if (!empty($node['content'])) {
            $contextText .= "  " . substr((string) $node['content'], 0, 200) . "\n";
        }
    }
    $contextText .= "\nEdges (" . count($edges) . "):\n";
    foreach (array_slice($edges, 0, 30) as $edge) {
        $contextText .= "- " . ((string) ($edge['source'] ?? '')) . " -> " . ((string) ($edge['target'] ?? '')) . " [" . ((string) ($edge['label'] ?? '')) . "]\n";
    }
    $systemPrompt = 'You are a cybersecurity threat intelligence analyst. Analyze the investigation data and provide a concise summary, identify key threat actors, infrastructure patterns, and recommend next steps for further investigation. Format in Markdown.';
    $messages = [
        ['role' => 'user', 'content' => "Please analyze this ClickFix threat investigation and provide:\n1. Executive Summary (2-3 sentences)\n2. Key Findings (bullet points)\n3. Infrastructure Patterns\n4. Recommended Next Steps\n\nInvestigation Data:\n" . $contextText],
    ];
    $result = clickfix_llm_call($pdo, $profileId, $messages, array_merge($options, ['system' => $systemPrompt, 'temperature' => 0.3, 'max_tokens' => 2048]));
    if (!$result['ok']) {
        return $result;
    }
    return [
        'ok' => true,
        'content' => $result['content'],
        'tokens' => $result['tokens'],
        'model' => $result['model'],
    ];
}

function clickfix_llm_extract_iocs(PDO $pdo, string $text, array $options = []): array
{
    $profileId = (int) ($options['profile_id'] ?? clickfix_llm_default_profile_id($pdo));
    if ($profileId <= 0) {
        return ['ok' => false, 'error' => 'no_llm_profile', 'iocs' => []];
    }
    $systemPrompt = 'You are a cybersecurity IOC (Indicators of Compromise) extraction tool. Extract all IOCs from the provided text and return them as a JSON array of objects with "type" (one of: domain, ip, url, md5, sha1, sha256, email, cve, registry, filepath) and "value" fields. Only return the JSON array, nothing else.';
    $messages = [
        ['role' => 'user', 'content' => "Extract all IOCs from this text. Return ONLY a JSON array:\n\n" . $text],
    ];
    $result = clickfix_llm_call($pdo, $profileId, $messages, array_merge($options, ['system' => $systemPrompt, 'temperature' => 0.1, 'max_tokens' => 2048]));
    if (!$result['ok']) {
        return $result;
    }
    $content = trim($result['content']);
    $content = preg_replace('/^```(?:json)?\s*/i', '', $content);
    $content = preg_replace('/\s*```$/i', '', $content);
    $iocs = json_decode($content, true);
    if (!is_array($iocs)) {
        return ['ok' => true, 'iocs' => [], 'raw' => $content, 'warning' => 'Could not parse JSON from LLM response'];
    }
    return [
        'ok' => true,
        'iocs' => $iocs,
        'tokens' => $result['tokens'],
    ];
}

function clickfix_llm_chat_investigation(PDO $pdo, int $graphId, string $userMessage, array $options = []): array
{
    $profileId = (int) ($options['profile_id'] ?? clickfix_llm_default_profile_id($pdo));
    if ($profileId <= 0) {
        return ['ok' => false, 'error' => 'no_llm_profile', 'content' => ''];
    }
    $investigation = clickfix_get_investigation_any($pdo, $graphId);
    if ($investigation === null) {
        return ['ok' => false, 'error' => 'investigation_not_found', 'content' => ''];
    }
    $graph = is_array($investigation['graph'] ?? null) ? $investigation['graph'] : ['nodes' => [], 'edges' => []];
    $nodes = is_array($graph['nodes'] ?? null) ? $graph['nodes'] : [];
    $edges = is_array($graph['edges'] ?? null) ? $graph['edges'] : [];
    $contextText = "INVESTIGATION CONTEXT:\nTitle: " . ((string) ($investigation['title'] ?? 'Untitled')) . "\nDomain: " . ((string) ($investigation['site_domain'] ?? 'N/A')) . "\nVerdict: " . ((string) ($investigation['verdict'] ?? 'unknown')) . "\nSummary: " . ((string) ($investigation['summary'] ?? 'N/A')) . "\nNodes: " . count($nodes) . " | Edges: " . count($edges) . "\n";
    $systemPrompt = "You are a cybersecurity threat intelligence analyst assistant integrated into the ClickFix Mitigator platform. You help analysts investigate social engineering and ClickFix attacks. You have access to the current investigation context. Be concise, technical, and actionable. Format responses in Markdown when appropriate.\n\n" . $contextText;
    $chatHistory = is_array($options['history'] ?? null) ? $options['history'] : [];
    $messages = [];
    foreach ($chatHistory as $msg) {
        $messages[] = ['role' => (string) ($msg['role'] ?? 'user'), 'content' => (string) ($msg['content'] ?? '')];
    }
    $messages[] = ['role' => 'user', 'content' => $userMessage];
    return clickfix_llm_call($pdo, $profileId, $messages, array_merge($options, ['system' => $systemPrompt, 'temperature' => 0.5, 'max_tokens' => 4096]));
}

function clickfix_llm_default_profile_id(PDO $pdo, int $userId = 0): int
{
    $profiles = clickfix_llm_configured_providers($pdo, $userId);
    foreach ($profiles as $profile) {
        if (!empty($profile['is_active'])) {
            return (int) ($profile['id'] ?? 0);
        }
    }
    return 0;
}

function clickfix_llm_save_profile(PDO $pdo, array $data, int $userId = 0): ?array
{
    if (!clickfix_has_table($pdo, 'user_llm_profiles')) {
        return null;
    }
    $hasUserIdCol = clickfix_has_column($pdo, 'user_llm_profiles', 'user_id');
    $id = (int) ($data['id'] ?? 0);
    $label = trim((string) ($data['label'] ?? ''));
    $provider = strtolower(trim((string) ($data['provider'] ?? 'openai')));
    $baseUrl = rtrim((string) ($data['base_url'] ?? ''), '/');
    $model = trim((string) ($data['model'] ?? ''));
    $apiKey = trim((string) ($data['api_key'] ?? ''));
    $extraHeaders = is_array($data['extra_headers'] ?? null) ? $data['extra_headers'] : [];
    $isActive = !empty($data['is_active']);
    if ($label === '' || $baseUrl === '') {
        return null;
    }
    if (!in_array($provider, clickfix_llm_provider_types(), true)) {
        $provider = 'custom';
    }
    $extraJson = json_encode($extraHeaders, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($id > 0) {
        $sql = 'UPDATE user_llm_profiles SET label = :label, provider = :provider, base_url = :base_url, model = :model, api_key = :api_key, extra_headers_json = :extra, is_active = :active, updated_at = :at WHERE id = :id';
        $params = [':id' => $id, ':label' => $label, ':provider' => $provider, ':base_url' => $baseUrl, ':model' => $model, ':api_key' => $apiKey, ':extra' => $extraJson, ':active' => $isActive ? 1 : 0, ':at' => gmdate('c')];
        if ($hasUserIdCol && $userId > 0) {
            $sql .= ' AND user_id = :uid';
            $params[':uid'] = $userId;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return ['id' => $id, 'label' => $label, 'provider' => $provider, 'base_url' => $baseUrl, 'model' => $model];
    }
    if ($hasUserIdCol) {
        $stmt = $pdo->prepare('INSERT INTO user_llm_profiles (label, provider, base_url, model, api_key, extra_headers_json, is_active, user_id, created_at, updated_at) VALUES (:label, :provider, :base_url, :model, :api_key, :extra, :active, :uid, :at, :at)');
        $stmt->execute([':label' => $label, ':provider' => $provider, ':base_url' => $baseUrl, ':model' => $model, ':api_key' => $apiKey, ':extra' => $extraJson, ':active' => $isActive ? 1 : 0, ':uid' => $userId, ':at' => gmdate('c')]);
    } else {
        $stmt = $pdo->prepare('INSERT INTO user_llm_profiles (label, provider, base_url, model, api_key, extra_headers_json, is_active, created_at, updated_at) VALUES (:label, :provider, :base_url, :model, :api_key, :extra, :active, :at, :at)');
        $stmt->execute([':label' => $label, ':provider' => $provider, ':base_url' => $baseUrl, ':model' => $model, ':api_key' => $apiKey, ':extra' => $extraJson, ':active' => $isActive ? 1 : 0, ':at' => gmdate('c')]);
    }
    return ['id' => (int) $pdo->lastInsertId(), 'label' => $label, 'provider' => $provider, 'base_url' => $baseUrl, 'model' => $model];
}

function clickfix_llm_delete_profile(PDO $pdo, int $profileId, int $userId = 0): bool
{
    if (!clickfix_has_table($pdo, 'user_llm_profiles')) {
        return false;
    }
    $hasUserIdCol = clickfix_has_column($pdo, 'user_llm_profiles', 'user_id');
    if ($hasUserIdCol && $userId > 0) {
        $stmt = $pdo->prepare('DELETE FROM user_llm_profiles WHERE id = :id AND user_id = :uid');
        $stmt->execute([':id' => $profileId, ':uid' => $userId]);
    } else {
        $stmt = $pdo->prepare('DELETE FROM user_llm_profiles WHERE id = :id');
        $stmt->execute([':id' => $profileId]);
    }
    return $stmt->rowCount() > 0;
}

function clickfix_llm_build_enriched_prompt(PDO $pdo, array $reportRow, array $enrichmentResults = []): string
{
    $reportId = (int) ($reportRow['id'] ?? 0);
    $hostname = (string) ($reportRow['hostname'] ?? '');
    $url = (string) ($reportRow['url'] ?? '');
    $previousUrl = (string) ($reportRow['previous_url'] ?? '');
    $message = (string) ($reportRow['message'] ?? '');
    $detectedContent = (string) ($reportRow['detected_content'] ?? '');
    $fullContext = (string) ($reportRow['full_context'] ?? '');
    $score = (int) ($reportRow['score_total'] ?? 0);
    $receivedAt = (string) ($reportRow['received_at'] ?? '');
    $country = (string) ($reportRow['country'] ?? '');
    $reviewStatus = (string) ($reportRow['review_status'] ?? 'pending');
    $blocked = !empty($reportRow['blocked']);
    $duplicateCount = (int) ($reportRow['duplicate_count'] ?? 1);
    $ip = (string) ($reportRow['ip'] ?? '');
    $userAgent = (string) ($reportRow['user_agent'] ?? '');
    $clientId = (string) ($reportRow['client_id'] ?? '');
    $signalsJson = (string) ($reportRow['signals_json'] ?? '[]');
    $reasonsJson = (string) ($reportRow['reason_entries_json'] ?? '[]');
    $snippetsJson = (string) ($reportRow['matched_snippets_json'] ?? '[]');
    $scoreDetailsJson = (string) ($reportRow['score_details_json'] ?? '{}');
    $signals = json_decode($signalsJson, true) ?: [];
    $reasons = json_decode($reasonsJson, true) ?: [];
    $snippets = json_decode($snippetsJson, true) ?: [];
    $scoreDetails = json_decode($scoreDetailsJson, true) ?: [];
    $prompt = "=== CLICKFIX DETECTION REPORT ===\n";
    $prompt .= "Alert ID: #{$reportId}\n";
    $prompt .= "Timestamp: {$receivedAt}\n";
    $prompt .= "Country: " . ($country !== '' ? $country : 'unknown') . "\n";
    $prompt .= "Review Status: {$reviewStatus}\n";
    $prompt .= "Blocked: " . ($blocked ? 'YES' : 'NO') . "\n";
    $prompt .= "Duplicate Count: {$duplicateCount}\n";
    $prompt .= "Score: {$score}/100\n";
    $prompt .= "\n--- TARGET ---\n";
    $prompt .= "Hostname: " . ($hostname !== '' ? $hostname : 'N/A') . "\n";
    $prompt .= "URL: " . ($url !== '' ? $url : 'N/A') . "\n";
    $prompt .= "Previous URL: " . ($previousUrl !== '' ? $previousUrl : 'N/A') . "\n";
    $prompt .= "IP: " . ($ip !== '' ? $ip : 'N/A') . "\n";
    $prompt .= "User Agent: " . ($userAgent !== '' ? $userAgent : 'N/A') . "\n";
    $prompt .= "Client ID: " . ($clientId !== '' ? $clientId : 'N/A') . "\n";
    $prompt .= "\n--- DETECTION MESSAGE ---\n{$message}\n";
    if ($detectedContent !== '') {
        $prompt .= "\n--- DETECTED CONTENT ---\n" . substr($detectedContent, 0, 3000) . "\n";
    }
    if ($fullContext !== '') {
        $prompt .= "\n--- FULL PAGE CONTEXT ---\n" . substr($fullContext, 0, 4000) . "\n";
    }
    if (!empty($signals)) {
        $prompt .= "\n--- DETECTION SIGNALS ---\n";
        foreach ($signals as $sig) {
            $sigLabel = is_array($sig) ? ((string) ($sig['label'] ?? $sig['signal'] ?? '')) : (string) $sig;
            if ($sigLabel !== '') {
                $prompt .= "- {$sigLabel}\n";
            }
        }
    }
    if (!empty($reasons)) {
        $prompt .= "\n--- DETECTION REASONS ---\n";
        foreach ($reasons as $r) {
            $rLabel = is_array($r) ? ((string) ($r['label'] ?? $r['reason'] ?? '')) : (string) $r;
            if ($rLabel !== '') {
                $prompt .= "- {$rLabel}\n";
            }
        }
    }
    if (!empty($snippets)) {
        $prompt .= "\n--- MATCHED SNIPPETS ---\n";
        foreach (array_slice($snippets, 0, 10) as $snip) {
            $snipText = is_array($snip) ? ((string) ($snip['snippet'] ?? $snip['text'] ?? '')) : (string) $snip;
            if ($snipText !== '') {
                $prompt .= "- " . substr($snipText, 0, 300) . "\n";
            }
        }
    }
    if (!empty($scoreDetails)) {
        $prompt .= "\n--- SCORE BREAKDOWN ---\n";
        $prompt .= json_encode($scoreDetails, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n";
    }
    if (!empty($enrichmentResults)) {
        $prompt .= "\n--- API ENRICHMENT RESULTS ---\n";
        foreach ($enrichmentResults as $provider => $result) {
            $prompt .= "\n[Provider: {$provider}]\n";
            if (is_array($result)) {
                $prompt .= json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n";
            } else {
                $prompt .= (string) $result . "\n";
            }
        }
    }
    return $prompt;
}

function clickfix_llm_investigate_alert(PDO $pdo, int $reportId, int $userId, array $options = []): array
{
    $profileId = (int) ($options['profile_id'] ?? clickfix_llm_default_profile_id($pdo, $userId));
    if ($profileId <= 0) {
        return ['ok' => false, 'error' => 'no_llm_profile_configured', 'content' => ''];
    }
    $report = clickfix_report_by_id($pdo, $reportId);
    if ($report === null) {
        return ['ok' => false, 'error' => 'report_not_found', 'content' => ''];
    }
    $enrichment = [];
    $hostname = (string) ($report['hostname'] ?? '');
    $ip = (string) ($report['ip'] ?? '');
    $url = (string) ($report['url'] ?? '');
    $detectedContent = (string) ($report['detected_content'] ?? '');
    if ($hostname !== '' || $ip !== '' || $url !== '') {
        $lookupTarget = $hostname !== '' ? $hostname : ($ip !== '' ? $ip : $url);
        $lookup = clickfix_api_lookup_indicator($pdo, $lookupTarget, 10);
        if (!empty($lookup)) {
            $enrichment['internal_lookup'] = [
                'type' => (string) ($lookup['type'] ?? 'unknown'),
                'already_reported' => !empty($lookup['already_reported']),
                'stats' => $lookup['stats'] ?? [],
                'list_membership' => $lookup['list_membership'] ?? [],
                'related_investigations_count' => count($lookup['related_investigations'] ?? []),
            ];
        }
        try {
            $vtKey = trim((string) clickfix_env('CLICKFIX_PROVIDER_VIRUSTOTAL_API_KEY', ''));
            if ($vtKey !== '' && $hostname !== '') {
                $vtData = clickfix_http_fetch_json('https://www.virustotal.com/api/v3/domains/' . urlencode($hostname), ['headers' => ['x-apikey' => $vtKey], 'timeout' => 12]);
                if (is_array($vtData)) {
                    $enrichment['virustotal'] = [
                        'malicious' => (int) ($vtData['data']['attributes']['last_analysis_stats']['malicious'] ?? 0),
                        'suspicious' => (int) ($vtData['data']['attributes']['last_analysis_stats']['suspicious'] ?? 0),
                        'harmless' => (int) ($vtData['data']['attributes']['last_analysis_stats']['harmless'] ?? 0),
                    ];
                }
            }
        } catch (Throwable $e) {}
        try {
            $abuseKey = trim((string) clickfix_env('CLICKFIX_PROVIDER_ABUSEIPDB_API_KEY', ''));
            if ($abuseKey !== '' && $ip !== '' && filter_var($ip, FILTER_VALIDATE_IP)) {
                $abData = clickfix_http_fetch_json('https://api.abuseipdb.com/api/v2/check?ipAddress=' . urlencode($ip) . '&maxAgeInDays=90', ['headers' => ['Key' => $abuseKey, 'Accept' => 'application/json'], 'timeout' => 12]);
                if (is_array($abData) && isset($abData['data'])) {
                    $enrichment['abuseipdb'] = [
                        'abuse_confidence_score' => (int) ($abData['data']['abuseConfidenceScore'] ?? 0),
                        'total_reports' => (int) ($abData['data']['totalReports'] ?? 0),
                        'country' => (string) ($abData['data']['countryCode'] ?? ''),
                        'isp' => (string) ($abData['data']['isp'] ?? ''),
                    ];
                }
            }
        } catch (Throwable $e) {}
    }
    $prompt = clickfix_llm_build_enriched_prompt($pdo, $report, $enrichment);
    $systemPrompt = 'You are a cybersecurity SOC analyst specializing in ClickFix/social engineering attacks. Analyze the detection report and API enrichment data. Provide: 1) Executive threat assessment (malicious/suspicious/benign), 2) Key indicators and TTPs identified, 3) Infrastructure analysis, 4) Recommended containment and remediation actions. Format as Markdown. Be concise and actionable.';
    $messages = [
        ['role' => 'user', 'content' => "Analyze this ClickFix detection:\n\n" . $prompt],
    ];
    $result = clickfix_llm_call($pdo, $profileId, $messages, array_merge($options, ['system' => $systemPrompt, 'temperature' => 0.3, 'max_tokens' => 4096]));
    $result['enrichment'] = $enrichment;
    return $result;
}
{
    if (!clickfix_has_table($pdo, 'user_llm_profiles')) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS user_llm_profiles (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL DEFAULT 0,
            label TEXT NOT NULL DEFAULT '',
            provider TEXT NOT NULL DEFAULT 'openai',
            base_url TEXT NOT NULL DEFAULT '',
            model TEXT NOT NULL DEFAULT '',
            api_key TEXT NOT NULL DEFAULT '',
            extra_headers_json TEXT NOT NULL DEFAULT '{}',
            is_active INTEGER NOT NULL DEFAULT 1,
            created_at TEXT NOT NULL DEFAULT '',
            updated_at TEXT NOT NULL DEFAULT ''
        )");
    }
    if (clickfix_has_table($pdo, 'user_llm_profiles') && !clickfix_has_column($pdo, 'user_llm_profiles', 'user_id')) {
        @$pdo->exec("ALTER TABLE user_llm_profiles ADD COLUMN user_id INTEGER NOT NULL DEFAULT 0");
    }
    if (!clickfix_has_table($pdo, 'auto_investigation_jobs')) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS auto_investigation_jobs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            report_id INTEGER,
            graph_id INTEGER,
            profile_id INTEGER NOT NULL DEFAULT 0,
            status TEXT NOT NULL DEFAULT 'queued',
            stage TEXT NOT NULL DEFAULT 'detect',
            result_json TEXT NOT NULL DEFAULT '{}',
            error TEXT NOT NULL DEFAULT '',
            created_at TEXT NOT NULL DEFAULT '',
            started_at TEXT NOT NULL DEFAULT '',
            completed_at TEXT NOT NULL DEFAULT ''
        )");
    }
    if (!clickfix_has_table($pdo, 'blog_feed_cache')) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS blog_feed_cache (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            source_url TEXT NOT NULL DEFAULT '',
            source_label TEXT NOT NULL DEFAULT '',
            title TEXT NOT NULL DEFAULT '',
            link TEXT NOT NULL DEFAULT '',
            description TEXT NOT NULL DEFAULT '',
            pub_date TEXT NOT NULL DEFAULT '',
            author TEXT NOT NULL DEFAULT '',
            categories_json TEXT NOT NULL DEFAULT '[]',
            fetched_at TEXT NOT NULL DEFAULT '',
            expires_at TEXT NOT NULL DEFAULT ''
        )");
        $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_blog_feed_link ON blog_feed_cache(source_url, link)");
    }
    if (!clickfix_has_table($pdo, 'auto_investigation_settings')) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS auto_investigation_settings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            setting_key TEXT NOT NULL UNIQUE,
            setting_value TEXT NOT NULL DEFAULT '',
            updated_at TEXT NOT NULL DEFAULT ''
        )");
        $pdo->prepare("INSERT OR IGNORE INTO auto_investigation_settings (setting_key, setting_value, updated_at) VALUES ('enabled', '0', :at)")->execute([':at' => gmdate('c')]);
        $pdo->prepare("INSERT OR IGNORE INTO auto_investigation_settings (setting_key, setting_value, updated_at) VALUES ('min_score', '60', :at)")->execute([':at' => gmdate('c')]);
        $pdo->prepare("INSERT OR IGNORE INTO auto_investigation_settings (setting_key, setting_value, updated_at) VALUES ('max_depth', '3', :at)")->execute([':at' => gmdate('c')]);
        $pdo->prepare("INSERT OR IGNORE INTO auto_investigation_settings (setting_key, setting_value, updated_at) VALUES ('llm_enrich', '0', :at)")->execute([':at' => gmdate('c')]);
        $pdo->prepare("INSERT OR IGNORE INTO auto_investigation_settings (setting_key, setting_value, updated_at) VALUES ('llm_profile_id', '0', :at)")->execute([':at' => gmdate('c')]);
        $pdo->prepare("INSERT OR IGNORE INTO auto_investigation_settings (setting_key, setting_value, updated_at) VALUES ('schedule_interval_minutes', '15', :at)")->execute([':at' => gmdate('c')]);
    }
}
