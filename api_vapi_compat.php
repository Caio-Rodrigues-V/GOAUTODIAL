<?php
/**
 * @file        api_vapi_compat.php
 * @brief       Dialog DDM - Vapi Compatible REST API Gateway (Drop-in Replacement for Vapi)
 * @copyright   (c) Dialog DDM - Grupo DDM
 */

error_reporting(0);
ini_set('display_errors', 0);

require_once('php/CRMDefaults.php');
require_once('php/AIAgentHandler.php');

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key, X-Max-Concurrency');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$handler = \creamy\AIAgentHandler::getInstance();

// -------------------------------------------------------------
// 1. Extracao da Rota RESTful
// -------------------------------------------------------------
$requestUri = $_SERVER['REQUEST_URI'];
$parsedUrl = parse_url($requestUri);
$path = isset($parsedUrl['path']) ? $parsedUrl['path'] : '';

// Remove prefixos como /api_vapi_compat.php ou /v1
if (strpos($path, '/api_vapi_compat.php') === 0) {
    $path = substr($path, strlen('/api_vapi_compat.php'));
}
if (strpos($path, '/v1') === 0) {
    $path = substr($path, strlen('/v1'));
}
$path = '/' . ltrim($path, '/');
$segments = explode('/', trim($path, '/'));

$method = $_SERVER['REQUEST_METHOD'];
$rawBody = file_get_contents('php://input');
$body = json_decode($rawBody, true) ?: array();

// -------------------------------------------------------------
// 2. Helper de Host & URLs
// -------------------------------------------------------------
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || 
            (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ? 'https' : 'http';
$host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '129.121.42.250';
$baseUrl = "{$protocol}://{$host}";

// -------------------------------------------------------------
// 3. Verificador de Concorrencia & Capacidade do Motor SIP
// -------------------------------------------------------------
function getEngineLiveTelemetry($handler) {
    $aiServerUrl = $handler->getSetting('ai_server_url', 'http://127.0.0.1:8765');
    $endpoint = rtrim($aiServerUrl, '/') . '/api/monitor/live';
    
    $ch = curl_init($endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 2);
    $res = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200 && $res) {
        return json_decode($res, true) ?: array();
    }
    return array('active_channels' => 0, 'max_channels' => 500, 'current_cps' => 0);
}

// -------------------------------------------------------------
// 4. Roteamento de Endpoints (Vapi 1-to-1)
// -------------------------------------------------------------

// ENDPOINT: GET /v1/concurrency ou GET /v1/capacity
if (($segments[0] === 'concurrency' || $segments[0] === 'capacity' || $segments[0] === 'channels') && $method === 'GET') {
    $telemetry = getEngineLiveTelemetry($handler);
    $active = isset($telemetry['active_channels']) ? (int)$telemetry['active_channels'] : 0;
    $max = isset($telemetry['max_channels']) ? (int)$telemetry['max_channels'] : 500;
    
    echo json_encode(array(
        'provider' => 'Dialog DDM Voice AI',
        'active_channels' => $active,
        'max_channels' => $max,
        'available_channels' => max(0, $max - $active),
        'channel_utilization_pct' => round(($active / $max) * 100, 1),
        'current_cps' => isset($telemetry['current_cps']) ? (int)$telemetry['current_cps'] : 0,
        'max_cps' => 100
    ));
    exit;
}

// ENDPOINT: GET /v1/assistants ou GET /v1/assistant/{id}
if ($segments[0] === 'assistant' || $segments[0] === 'assistants') {
    $agentId = isset($segments[1]) ? (int)$segments[1] : (isset($_GET['id']) ? (int)$_GET['id'] : null);
    if ($agentId) {
        $ag = $handler->getAgentById($agentId);
        if (!$ag) {
            http_response_code(404);
            echo json_encode(array('error' => 'Not Found', 'message' => "Assistant {$agentId} not found"));
            exit;
        }
        echo json_encode(array(
            'id' => (string)$ag['agent_id'],
            'name' => $ag['agent_name'],
            'firstMessage' => $ag['greeting_message'],
            'model' => array(
                'provider' => $ag['llm_provider'],
                'model' => $ag['llm_model'],
                'temperature' => (float)$ag['temperature'],
                'messages' => array(array('role' => 'system', 'content' => $ag['system_prompt']))
            ),
            'voice' => array(
                'provider' => $ag['voice_provider'],
                'voiceId' => $ag['voice_id'],
                'speed' => (float)$ag['voice_speed']
            )
        ));
        exit;
    } else {
        $list = $handler->getAllAgents();
        $formatted = array();
        foreach ($list as $ag) {
            $formatted[] = array(
                'id' => (string)$ag['agent_id'],
                'name' => $ag['agent_name'],
                'firstMessage' => $ag['greeting_message'],
                'voiceProvider' => $ag['voice_provider'],
                'status' => $ag['status'] === 'Y' ? 'active' : 'inactive'
            );
        }
        echo json_encode($formatted);
        exit;
    }
}

// ENDPOINT: POST /v1/call/phone ou POST /v1/call (Disparo de Chamadas)
if (($segments[0] === 'call' && (isset($segments[1]) && $segments[1] === 'phone' || !isset($segments[1]))) && $method === 'POST') {
    
    // 1. Limite de Concorrencia Customizado
    $maxConcurrency = 50; // Default por projeto para proteger os 500 canais
    if (isset($_SERVER['HTTP_X_MAX_CONCURRENCY'])) {
        $maxConcurrency = max(1, min(500, (int)$_SERVER['HTTP_X_MAX_CONCURRENCY']));
    } elseif (isset($body['max_concurrency']) || isset($body['maxConcurrency'])) {
        $val = isset($body['max_concurrency']) ? $body['max_concurrency'] : $body['maxConcurrency'];
        $maxConcurrency = max(1, min(500, (int)$val));
    }

    $telemetry = getEngineLiveTelemetry($handler);
    $currentActive = isset($telemetry['active_channels']) ? (int)$telemetry['active_channels'] : 0;
    
    if ($currentActive >= $maxConcurrency) {
        http_response_code(429);
        echo json_encode(array(
            'error' => 'Too Many Requests',
            'message' => "Limite de concorrencia atingido ({$currentActive}/{$maxConcurrency} canais ocupados). Aguarde a conclusao de chamadas ativas.",
            'active_channels' => $currentActive,
            'max_concurrency' => $maxConcurrency
        ));
        exit;
    }

    // 2. Extracao dos Parametros no padrao Vapi
    $assistantId = !empty($body['assistantId']) ? (int)$body['assistantId'] : (isset($_GET['agent_id']) ? (int)$_GET['agent_id'] : 2);
    $agent = $handler->getAgentById($assistantId);
    if (!$agent) {
        $all = $handler->getAllAgents();
        $agent = !empty($all) ? $all[0] : null;
    }
    if (!$agent) {
        http_response_code(400);
        echo json_encode(array('error' => 'Bad Request', 'message' => 'Nenhum Agente de IA configurado na plataforma.'));
        exit;
    }

    $customer = isset($body['customer']) && is_array($body['customer']) ? $body['customer'] : array();
    $rawPhone = !empty($customer['number']) ? $customer['number'] : (isset($body['phone_number']) ? $body['phone_number'] : (isset($body['customerNumber']) ? $body['customerNumber'] : ''));
    $customerName = !empty($customer['name']) ? $customer['name'] : (isset($body['customerName']) ? $body['customerName'] : '');

    $cleanPhone = preg_replace('/\D/', '', $rawPhone);
    if ((strlen($cleanPhone) == 12 || strlen($cleanPhone) == 13) && substr($cleanPhone, 0, 2) === '55') {
        $cleanPhone = substr($cleanPhone, 2);
    }
    if (strlen($cleanPhone) < 10) {
        http_response_code(400);
        echo json_encode(array('error' => 'Bad Request', 'message' => 'customer.number invalido. Informe DDD + Numero (ex: +5511999999999).'));
        exit;
    }

    $metadata = isset($body['metadata']) && is_array($body['metadata']) ? $body['metadata'] : array();
    $overrides = isset($body['assistantOverrides']) && is_array($body['assistantOverrides']) ? $body['assistantOverrides'] : array();

    // 3. Webhook URL (prioridade: assistantOverrides.serverUrl > serverUrl > metadata.webhookUrl > agent default)
    $webhookUrl = '';
    if (!empty($overrides['serverUrl'])) {
        $webhookUrl = (string)$overrides['serverUrl'];
    } elseif (!empty($overrides['server']['url'])) {
        $webhookUrl = (string)$overrides['server']['url'];
    } elseif (!empty($body['serverUrl'])) {
        $webhookUrl = (string)$body['serverUrl'];
    } elseif (!empty($metadata['webhookUrl'])) {
        $webhookUrl = (string)$metadata['webhookUrl'];
    } elseif (!empty($agent['webhook_url'])) {
        $webhookUrl = $agent['webhook_url'];
    }

    // 4. Overrides dinamicos de prompt e variaveis
    $greeting = !empty($overrides['firstMessage']) ? $overrides['firstMessage'] : $agent['greeting_message'];
    $systemPrompt = $agent['system_prompt'];

    $variableValues = isset($overrides['variableValues']) && is_array($overrides['variableValues']) ? $overrides['variableValues'] : array();
    if ($customerName && empty($variableValues['nome'])) {
        $variableValues['nome'] = $customerName;
    }

    // Injeta substituicao de variaveis nas tags {variavel} e {{variavel}}
    if ($customerName) {
        $greeting = str_replace(array('{nome}', '{name}', '{aluno}', '{cliente}'), $customerName, $greeting);
        $systemPrompt = str_replace(array('{nome}', '{name}', '{aluno}', '{cliente}'), $customerName, $systemPrompt);
    }
    foreach ($variableValues as $k => $v) {
        if (is_scalar($v)) {
            $greeting = str_replace(array("{{$k}}", "{{{$k}}}"), (string)$v, $greeting);
            $systemPrompt = str_replace(array("{{$k}}", "{{{$k}}}"), (string)$v, $systemPrompt);
        }
    }

    $agent['greeting_message'] = $greeting;
    $agent['system_prompt'] = $systemPrompt;
    $agent['webhook_url'] = $webhookUrl;
    $agent['metadata'] = $metadata;

    // Repassa chaves de API configuradas no painel
    $apiKeys = array(
        'openai_api_key' => $handler->getSetting('openai_api_key', ''),
        'groq_api_key' => $handler->getSetting('groq_api_key', ''),
        'elevenlabs_api_key' => $handler->getSetting('elevenlabs_api_key', ''),
        'cartesia_api_key' => $handler->getSetting('cartesia_api_key', ''),
        'deepgram_api_key' => $handler->getSetting('deepgram_api_key', ''),
        'gemini_api_key' => $handler->getSetting('gemini_api_key', ''),
        'anthropic_api_key' => $handler->getSetting('anthropic_api_key', ''),
        'deepseek_api_key' => $handler->getSetting('deepseek_api_key', '')
    );

    $agent['callback_url'] = "{$baseUrl}/php/SaveAICallLog.php";

    $aiServerUrl = $handler->getSetting('ai_server_url', 'http://127.0.0.1:8765');
    $dialEndpoint = rtrim($aiServerUrl, '/') . '/api/dial';

    $ch = curl_init($dialEndpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(array(
        'agent_id' => (int)$agent['agent_id'],
        'phone_number' => $cleanPhone,
        'agent_config' => $agent,
        'api_keys' => $apiKeys
    )));
    curl_setopt($ch, CURLOPT_TIMEOUT, 6);
    $resp = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $dialRes = json_decode($resp, true) ?: array();
    $callId = isset($dialRes['call_id']) ? $dialRes['call_id'] : 'call_' . uniqid();

    // 5. Resposta identica ao Vapi call object
    http_response_code(201);
    echo json_encode(array(
        'id' => $callId,
        'orgId' => 'org_dialog_ddm',
        'createdAt' => date('c'),
        'updatedAt' => date('c'),
        'type' => 'outboundPhoneCall',
        'status' => 'queued',
        'assistantId' => (string)$agent['agent_id'],
        'phoneNumberId' => isset($body['phoneNumberId']) ? (string)$body['phoneNumberId'] : 'oktor_sip_trunk_500ch',
        'customer' => array(
            'number' => "+55{$cleanPhone}",
            'name' => $customerName
        ),
        'metadata' => $metadata,
        'serverUrl' => $webhookUrl
    ));
    exit;
}

// ENDPOINT: GET /v1/call/{id}/transcript (Recuperacao de Transcricao e Audio)
if ($segments[0] === 'call' && isset($segments[1]) && isset($segments[2]) && $segments[2] === 'transcript' && $method === 'GET') {
    $callId = trim($segments[1]);
    $call = $handler->getCallLogById($callId);

    if (!$call) {
        http_response_code(404);
        echo json_encode(array('error' => 'Not Found', 'message' => "Chamada '{$callId}' nao encontrada"));
        exit;
    }

    $rawTrans = json_decode($call['transcript_json'], true) ?: array();
    $plainText = '';
    $messages = array();
    foreach ($rawTrans as $t) {
        $role = isset($t['role']) ? $t['role'] : (isset($t['sender']) && strpos(strtolower($t['sender']), 'ia') !== false ? 'assistant' : 'user');
        $text = isset($t['message']) ? $t['message'] : (isset($t['text']) ? $t['text'] : (isset($t['content']) ? $t['content'] : ''));
        if ($text) {
            $plainText .= ($role === 'assistant' ? "Sofia: {$text}\n" : "Cliente: {$text}\n");
            $messages[] = array('role' => $role, 'message' => $text);
        }
    }

    $recordingUrl = !empty($call['recording_url']) ? "{$baseUrl}/" . ltrim($call['recording_url'], '/') : '';

    echo json_encode(array(
        'id' => $call['call_id'],
        'status' => $call['status'],
        'duration_seconds' => (int)$call['duration_seconds'],
        'transcript' => trim($plainText),
        'messages' => $messages,
        'recordingUrl' => $recordingUrl,
        'tabulation' => $call['tabulation'],
        'tabulation_code' => $call['tabulation_code'],
        'endedReason' => $call['status'] === 'completed' ? 'customer-ended-call' : ($call['status'] === 'voicemail' ? 'voicemail' : 'assistant-ended-call'),
        'cost_estimate_brl' => (float)$call['cost_estimate']
    ));
    exit;
}

// ENDPOINT: GET /v1/call/{id} (Consulta de Status da Chamada)
if ($segments[0] === 'call' && isset($segments[1]) && $method === 'GET') {
    $callId = trim($segments[1]);
    $call = $handler->getCallLogById($callId);

    if ($call) {
        $rawTrans = json_decode($call['transcript_json'], true) ?: array();
        $plainText = '';
        $messages = array();
        foreach ($rawTrans as $t) {
            $role = isset($t['role']) ? $t['role'] : (isset($t['sender']) && strpos(strtolower($t['sender']), 'ia') !== false ? 'assistant' : 'user');
            $text = isset($t['message']) ? $t['message'] : (isset($t['text']) ? $t['text'] : (isset($t['content']) ? $t['content'] : ''));
            if ($text) {
                $plainText .= ($role === 'assistant' ? "Sofia: {$text}\n" : "Cliente: {$text}\n");
                $messages[] = array('role' => $role, 'message' => $text);
            }
        }
        $recordingUrl = !empty($call['recording_url']) ? "{$baseUrl}/" . ltrim($call['recording_url'], '/') : '';

        echo json_encode(array(
            'id' => $call['call_id'],
            'type' => 'outboundPhoneCall',
            'status' => $call['status'] === 'completed' ? 'ended' : $call['status'],
            'startedAt' => $call['created_at'],
            'endedAt' => $call['ended_at'],
            'duration' => (int)$call['duration_seconds'],
            'cost' => (float)$call['cost_estimate'],
            'customer' => array(
                'number' => "+55{$call['phone_number']}"
            ),
            'transcript' => trim($plainText),
            'artifact' => array(
                'transcript' => trim($plainText),
                'recordingUrl' => $recordingUrl,
                'messages' => $messages
            ),
            'endedReason' => $call['status'] === 'completed' ? 'customer-ended-call' : ($call['status'] === 'voicemail' ? 'voicemail' : 'failed')
        ));
        exit;
    }

    // Se ainda nao salvou no banco, consulta no motor SIP em memoria
    $telemetry = getEngineLiveTelemetry($handler);
    $activeCalls = isset($telemetry['active_calls']) ? $telemetry['active_calls'] : array();
    foreach ($activeCalls as $ac) {
        if ($ac['call_id'] === $callId) {
            echo json_encode(array(
                'id' => $callId,
                'type' => 'outboundPhoneCall',
                'status' => $ac['status'] === 'in_call' ? 'in-progress' : ($ac['status'] === 'ringing' ? 'ringing' : 'queued'),
                'customer' => array('number' => "+55{$ac['phone_number']}"),
                'duration' => (int)$ac['duration_seconds']
            ));
            exit;
        }
    }

    http_response_code(404);
    echo json_encode(array('error' => 'Not Found', 'message' => "Chamada '{$callId}' nao encontrada."));
    exit;
}

// ENDPOINT: DELETE /v1/call/{id} ou POST /v1/call/{id}/hangup (Desconectar Chamada)
if ($segments[0] === 'call' && isset($segments[1]) && ($method === 'DELETE' || (isset($segments[2]) && $segments[2] === 'hangup'))) {
    $callId = trim($segments[1]);
    
    $aiServerUrl = $handler->getSetting('ai_server_url', 'http://127.0.0.1:8765');
    $hangupEndpoint = rtrim($aiServerUrl, '/') . '/api/monitor/hangup/' . urlencode($callId);

    $ch = curl_init($hangupEndpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 3);
    $res = curl_exec($ch);
    curl_close($ch);

    echo json_encode(array(
        'id' => $callId,
        'status' => 'ended',
        'message' => "Chamada {$callId} encerrada com sucesso."
    ));
    exit;
}

// ROTA NAO RECONHECIDA
http_response_code(404);
echo json_encode(array(
    'error' => 'Not Found',
    'message' => "Endpoint '{$path}' nao suportado pelo gateway Vapi.",
    'available_endpoints' => array(
        'POST /v1/call/phone' => 'Dispara chamada outbound (Vapi compativel)',
        'GET /v1/call/{id}' => 'Consulta status da ligacao',
        'GET /v1/call/{id}/transcript' => 'Retorna transcricao completa, historico de turnos e audio gravado',
        'DELETE /v1/call/{id}' => 'Desconecta chamada em andamento',
        'GET /v1/concurrency' => 'Telemetria de canais ativos e CPS em tempo real',
        'GET /v1/assistants' => 'Lista assistentes de IA cadastrados'
    )
));
