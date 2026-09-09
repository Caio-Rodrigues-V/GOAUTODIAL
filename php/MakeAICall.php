<?php
/**
 * @file        MakeAICall.php
 * @brief       Originate outbound test call to phone number via Oktor SIP Trunk
 * @copyright   (c) Dial GO Voice AI Engine
 */

require_once('CRMDefaults.php');
require_once('Session.php');
require_once('AIAgentHandler.php');

header('Content-Type: application/json');

$aiHandler = \creamy\AIAgentHandler::getInstance();

$agentId = isset($_POST['agent_id']) ? (int)$_POST['agent_id'] : 0;
$rawPhone = isset($_POST['phone_number']) ? trim($_POST['phone_number']) : '';

// Clean phone number (leave only digits)
$phone = preg_replace('/\D/', '', $rawPhone);

// Remove leading 0 if present (e.g. 011945656909 -> 11945656909)
if (substr($phone, 0, 1) === '0') {
    $phone = substr($phone, 1);
}

// Build E.164 format (55 + DDD + Number)
if (substr($phone, 0, 2) === '55' && strlen($phone) >= 12) {
    $e164 = $phone;
    $localPhone = substr($phone, 2);
} else {
    $e164 = '55' . $phone;
    $localPhone = $phone;
}

if (strlen($localPhone) < 10) {
    echo json_encode(array('status' => 0, 'message' => 'Número de telefone inválido. Informe DDD + Número (ex: 11945656909).'));
    exit;
}

$agent = $aiHandler->getAgentById($agentId);
if (!$agent) {
    echo json_encode(array('status' => 0, 'message' => 'Agente de IA não encontrado.'));
    exit;
}

// Oktor Tech Prefix: 59083 (Automação IA com Bina Local)
// Formato de envio: 59083 + 5511945656909 = 590835511945656909
$oktorPrefix = '59083';
$aiServerUrl = $aiHandler->getSetting('ai_server_url', 'http://127.0.0.1:8765');
$dialEndpoint = rtrim($aiServerUrl, '/') . '/api/dial';

$apiKeys = array(
    'openai_api_key' => $aiHandler->getSetting('openai_api_key', ''),
    'groq_api_key' => $aiHandler->getSetting('groq_api_key', ''),
    'elevenlabs_api_key' => $aiHandler->getSetting('elevenlabs_api_key', ''),
    'cartesia_api_key' => $aiHandler->getSetting('cartesia_api_key', ''),
    'deepgram_api_key' => $aiHandler->getSetting('deepgram_api_key', ''),
    'gemini_api_key' => $aiHandler->getSetting('gemini_api_key', ''),
    'anthropic_api_key' => $aiHandler->getSetting('anthropic_api_key', ''),
    'deepseek_api_key' => $aiHandler->getSetting('deepseek_api_key', '')
);

$dialNumber = "59083{$e164}";

$agent['callback_url'] = 'http://127.0.0.1/php/SaveAICallLog.php';

// 1. Send HTTP request to Python Direct SIP Server
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
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr = curl_error($ch);
curl_close($ch);

if ($httpCode === 200 && $response) {
    $resData = json_decode($response, true);
    echo json_encode(array(
        'status' => 1,
        'message' => "Chamada SIP enviada com sucesso para a OKTOR Telecom! Destino: {$dialNumber}. Aguarde o toque no seu celular.",
        'dial_number' => $dialNumber,
        'data' => $resData
    ));
    exit;
}

// Fallback: If python server is not running on 8765, inform user to start it
echo json_encode(array(
    'status' => 0,
    'message' => "O Servidor de Voz Python não está rodando no momento. No terminal do servidor, execute: 'cd /home/grpia/apps/goautodial/ai_engine && python3 server.py' para ligar o discador!",
    'dial_number' => $dialNumber
));
exit;
?>