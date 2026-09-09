<?php
/**
 * @file        api_ai_calls.php
 * @brief       Dial GO Voice AI - REST API Endpoint for External Integrations & Webhooks
 * @copyright   (c) Dial GO Voice AI Engine
 */

require_once('php/CRMDefaults.php');
require_once('php/AIAgentHandler.php');

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$handler = \creamy\AIAgentHandler::getInstance();
$action = isset($_GET['action']) ? $_GET['action'] : (isset($_POST['action']) ? $_POST['action'] : 'list');

switch ($action) {
    case 'list':
        $limit = isset($_GET['limit']) ? min((int)$_GET['limit'], 200) : 50;
        $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
        $agentId = isset($_GET['agent_id']) ? (int)$_GET['agent_id'] : null;
        $phone = isset($_GET['phone']) ? trim($_GET['phone']) : null;
        $status = isset($_GET['status']) ? trim($_GET['status']) : null;

        $calls = $handler->getCallLogs($limit, $offset, $agentId, $phone, $status);
        echo json_encode(array(
            'status' => 'success',
            'count' => count($calls),
            'limit' => $limit,
            'offset' => $offset,
            'data' => $calls
        ));
        break;

    case 'get':
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $call = $handler->getCallLogById($id);
        if (!$call) {
            http_response_code(404);
            echo json_encode(array('status' => 'error', 'message' => 'Call not found'));
            exit;
        }

        $transcripts = array();
        if (!empty($call['transcript_json'])) {
            $transcripts = json_decode($call['transcript_json'], true) ?: array();
        }

        echo json_encode(array(
            'status' => 'success',
            'call' => $call,
            'transcripts' => $transcripts
        ));
        break;

    case 'stats':
        $stats = $handler->getCallStats();
        echo json_encode(array(
            'status' => 'success',
            'stats' => $stats
        ));
        break;

    case 'dial':
        $raw = file_get_contents('php://input');
        $payload = json_decode($raw, true) ?: $_POST;

        $agentId = isset($payload['agent_id']) ? (int)$payload['agent_id'] : 0;
        $phone = isset($payload['phone_number']) ? trim($payload['phone_number']) : '';

        if (!$agentId || !$phone) {
            http_response_code(400);
            echo json_encode(array('status' => 'error', 'message' => 'agent_id and phone_number are required'));
            exit;
        }

        // Delegate to MakeAICall logic
        $agent = $handler->getAgentById($agentId);
        if (!$agent) {
            http_response_code(404);
            echo json_encode(array('status' => 'error', 'message' => 'Agent not found'));
            exit;
        }

        $apiKeys = array(
            'openai_api_key' => $handler->getSetting('openai_api_key', ''),
            'groq_api_key' => $handler->getSetting('groq_api_key', ''),
            'elevenlabs_api_key' => $handler->getSetting('elevenlabs_api_key', ''),
            'cartesia_api_key' => $handler->getSetting('cartesia_api_key', ''),
            'deepgram_api_key' => $handler->getSetting('deepgram_api_key', ''),
            'gemini_api_key' => $handler->getSetting('gemini_api_key', ''),
            'anthropic_api_key' => $handler->getSetting('anthropic_api_key', ''),
            'deepseek_api_key' => $handler->getSetting('deepseek_api_key', ''),
            'azure_speech_key' => $handler->getSetting('azure_speech_key', ''),
            'azure_speech_region' => $handler->getSetting('azure_speech_region', 'eastus')
        );

        $aiServerUrl = $handler->getSetting('ai_server_url', 'http://127.0.0.1:8765');
        $dialEndpoint = rtrim($aiServerUrl, '/') . '/api/dial';

        $ch = curl_init($dialEndpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(array(
            'agent_id' => $agentId,
            'phone_number' => $phone,
            'agent_config' => $agent,
            'api_keys' => $apiKeys
        )));
        curl_setopt($ch, CURLOPT_TIMEOUT, 8);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $response) {
            echo $response;
        } else {
            echo json_encode(array(
                'status' => 'error',
                'message' => 'O Motor de Voz (ai_engine) não está rodando no servidor na porta 8765. No terminal do servidor, execute: cd ai_engine && bash setup_service.sh (ou python3 server.py)'
            ));
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(array('status' => 'error', 'message' => 'Invalid action'));
        break;
}
?>
