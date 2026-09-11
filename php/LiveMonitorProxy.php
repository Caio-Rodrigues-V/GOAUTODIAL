<?php
/**
 * @file        LiveMonitorProxy.php
 * @brief       Backend proxy connecting Live Monitor UI to Python Voice Engine
 * @copyright   (c) Dial GO Voice AI Engine
 */

require_once('CRMDefaults.php');
require_once('AIAgentHandler.php');
require_once('Session.php');

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$user = \creamy\CreamyUser::currentUser();
if (!$user) {
    http_response_code(401);
    echo json_encode(array('status' => 'error', 'message' => 'Não autorizado'));
    exit;
}

$handler = \creamy\AIAgentHandler::getInstance();
$aiServerUrl = $handler->getSetting('ai_server_url', 'http://127.0.0.1:8765');
$action = isset($_GET['action']) ? $_GET['action'] : (isset($_POST['action']) ? $_POST['action'] : 'status');

if ($action === 'hangup') {
    $callId = isset($_POST['call_id']) ? $_POST['call_id'] : (isset($_GET['call_id']) ? $_GET['call_id'] : '');
    if (!$callId) {
        http_response_code(400);
        echo json_encode(array('status' => 'error', 'message' => 'call_id é obrigatório'));
        exit;
    }

    $endpoint = rtrim($aiServerUrl, '/') . '/api/monitor/hangup/' . urlencode($callId);
    $ch = curl_init($endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 4);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && $response) {
        echo $response;
    } else {
        echo json_encode(array('status' => 'error', 'message' => 'Falha ao encerrar chamada no motor de voz'));
    }
    exit;
}

// Default: fetch live monitor status
$endpoint = rtrim($aiServerUrl, '/') . '/api/monitor/live';
$ch = curl_init($endpoint);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 3);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200 && $response) {
    echo $response;
} else {
    echo json_encode(array(
        'status' => 'offline',
        'max_channels' => 500,
        'max_cps' => 100,
        'active_channels' => 0,
        'channel_utilization_pct' => 0,
        'current_cps' => 0,
        'calls_last_minute' => 0,
        'total_calls_today' => 0,
        'ringing_count' => 0,
        'in_call_count' => 0,
        'active_calls' => array(),
        'message' => 'Motor de voz offline ou iniciando...'
    ));
}
