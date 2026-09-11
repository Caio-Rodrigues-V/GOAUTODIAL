<?php
/**
 * @file        BatchDialProxy.php
 * @brief       PHP Backend Proxy for Batch Dialing (Disparo em Lote)
 * @copyright   (c) Dial GO Voice AI Engine
 */

require_once('CRMDefaults.php');
require_once('Session.php');
require_once('AIAgentHandler.php');

header('Content-Type: application/json');

$aiHandler = \creamy\AIAgentHandler::getInstance();
$aiServerUrl = $aiHandler->getSetting('ai_server_url', 'http://127.0.0.1:8765');
$action = isset($_REQUEST['action']) ? trim($_REQUEST['action']) : 'start';

function sendPost($url, $data = array(), $timeout = 8) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $res = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return array('code' => $httpCode, 'body' => $res);
}

function sendGet($url, $timeout = 5) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $res = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return array('code' => $httpCode, 'body' => $res);
}

if ($action === 'start') {
    $agentId = isset($_POST['agent_id']) ? (int)$_POST['agent_id'] : 0;
    $concurrency = isset($_POST['concurrency']) ? (int)$_POST['concurrency'] : 5;
    $delayMs = isset($_POST['delay_ms']) ? (int)$_POST['delay_ms'] : 250;
    $rawContacts = isset($_POST['contacts']) ? $_POST['contacts'] : '';

    $agent = $aiHandler->getAgentById($agentId);
    if (!$agent) {
        echo json_encode(array('status' => 0, 'message' => 'Agente de IA não encontrado.'));
        exit;
    }

    $parsedContacts = array();

    // Se veio array JSON
    if (is_array($rawContacts)) {
        $parsedContacts = $rawContacts;
    } elseif (is_string($rawContacts)) {
        // Se for JSON codificado
        $decoded = json_decode($rawContacts, true);
        if (is_array($decoded)) {
            $parsedContacts = $decoded;
        } else {
            // Se for linhas de texto / CSV (Telefone, Nome, etc.)
            $lines = explode("\n", str_replace("\r", "", $rawContacts));
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) continue;
                $parts = str_getcsv($line, ",");
                $rawPhone = isset($parts[0]) ? trim($parts[0]) : '';
                $name = isset($parts[1]) ? trim($parts[1]) : '';
                $custom1 = isset($parts[2]) ? trim($parts[2]) : '';

                $cleanPhone = preg_replace('/\D/', '', $rawPhone);
                if (substr($cleanPhone, 0, 1) === '0') $cleanPhone = substr($cleanPhone, 1);
                if (strlen($cleanPhone) >= 10) {
                    $customData = array();
                    if (!empty($custom1)) $customData['info'] = $custom1;
                    $parsedContacts[] = array(
                        'phone_number' => $cleanPhone,
                        'name' => $name,
                        'custom_data' => $customData
                    );
                }
            }
        }
    }

    if (count($parsedContacts) === 0) {
        echo json_encode(array('status' => 0, 'message' => 'Nenhum número de telefone válido encontrado na lista informada.'));
        exit;
    }

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

    $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? "https://" : "http://";
    $host = !empty($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : (!empty($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : '127.0.0.1');
    $scriptDir = dirname($_SERVER['SCRIPT_NAME']);
    $cleanDir = str_replace('\\', '/', $scriptDir);
    if ($cleanDir === '/' || $cleanDir === '.') $cleanDir = '';
    $agent['callback_url'] = "{$proto}{$host}{$cleanDir}/SaveAICallLog.php";

    $endpoint = rtrim($aiServerUrl, '/') . '/api/dial/batch';
    $payload = array(
        'agent_id' => $agentId,
        'contacts' => $parsedContacts,
        'concurrency' => $concurrency,
        'delay_ms' => $delayMs,
        'agent_config' => $agent,
        'api_keys' => $apiKeys
    );

    $res = sendPost($endpoint, $payload, 10);
    if ($res['code'] === 200 && $res['body']) {
        echo $res['body'];
        exit;
    }

    echo json_encode(array(
        'status' => 'error',
        'message' => 'Não foi possível conectar ao servidor de voz na porta 8765. Certifique-se de que o Python server.py está ativo.'
    ));
    exit;

} elseif ($action === 'status') {
    $batchId = isset($_GET['batch_id']) ? trim($_GET['batch_id']) : '';
    if (empty($batchId)) {
        echo json_encode(array('status' => 'error', 'message' => 'Batch ID não fornecido.'));
        exit;
    }
    $endpoint = rtrim($aiServerUrl, '/') . "/api/batch/{$batchId}";
    $res = sendGet($endpoint);
    if ($res['code'] === 200 && $res['body']) {
        echo $res['body'];
        exit;
    }
    echo json_encode(array('status' => 'error', 'message' => 'Erro ao obter status do lote.'));
    exit;

} elseif ($action === 'pause' || $action === 'resume' || $action === 'cancel') {
    $batchId = isset($_POST['batch_id']) ? trim($_POST['batch_id']) : '';
    if (empty($batchId)) {
        echo json_encode(array('status' => 'error', 'message' => 'Batch ID não fornecido.'));
        exit;
    }
    $endpoint = rtrim($aiServerUrl, '/') . "/api/batch/{$batchId}/{$action}";
    $res = sendPost($endpoint, array());
    if ($res['code'] === 200 && $res['body']) {
        echo $res['body'];
        exit;
    }
    echo json_encode(array('status' => 'error', 'message' => "Erro ao executar {$action} no lote."));
    exit;

} elseif ($action === 'list') {
    $endpoint = rtrim($aiServerUrl, '/') . "/api/batches";
    $res = sendGet($endpoint);
    if ($res['code'] === 200 && $res['body']) {
        echo $res['body'];
        exit;
    }
    echo json_encode(array('status' => 'error', 'message' => 'Erro ao listar lotes.'));
    exit;
}
