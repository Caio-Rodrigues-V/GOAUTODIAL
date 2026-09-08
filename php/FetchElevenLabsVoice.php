<?php
/**
 * @file        FetchElevenLabsVoice.php
 * @brief       Import and verify ElevenLabs voice by ID or fetch account voices
 * @copyright   (c) Dial GO Voice AI Engine
 */

require_once('CRMDefaults.php');
require_once('Session.php');
require_once('AIAgentHandler.php');

header('Content-Type: application/json');

$aiHandler = \creamy\AIAgentHandler::getInstance();
$savedApiKey = $aiHandler->getSetting('elevenlabs_api_key', '');

$apiKey = !empty($_POST['api_key']) ? trim($_POST['api_key']) : $savedApiKey;
$action = isset($_POST['action']) ? trim($_POST['action']) : 'fetch_one';
$voiceId = isset($_POST['voice_id']) ? trim($_POST['voice_id']) : '';

if (empty($apiKey)) {
    echo json_encode(array(
        'status' => 0, 
        'message' => 'Chave da ElevenLabs não configurada. Insira sua ElevenLabs API Key nas Configurações de IA primeiro.'
    ));
    exit;
}

if ($action === 'fetch_all') {
    // Fetch all voices available in user's ElevenLabs account
    $ch = curl_init('https://api.elevenlabs.io/v1/voices');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'xi-api-key: ' . $apiKey,
        'Content-Type: application/json'
    ));
    curl_setopt($ch, CURLOPT_TIMEOUT, 12);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($httpCode === 200 && $response) {
        $data = json_decode($response, true);
        if (isset($data['voices']) && is_array($data['voices'])) {
            $formatted = array();
            foreach ($data['voices'] as $v) {
                $category = isset($v['category']) ? $v['category'] : 'premade';
                $labelDesc = '';
                if (isset($v['labels']) && is_array($v['labels'])) {
                    $pairs = array();
                    foreach ($v['labels'] as $k => $val) {
                        $pairs[] = "$k: $val";
                    }
                    if (!empty($pairs)) $labelDesc = ' (' . implode(', ', array_slice($pairs, 0, 2)) . ')';
                }
                $badge = ($category === 'cloned') ? ' [Clonada]' : (($category === 'generated') ? ' [Gerada]' : '');

                $formatted[] = array(
                    'voice_id' => $v['voice_id'],
                    'name' => $v['name'] . $badge . $labelDesc,
                    'raw_name' => $v['name'],
                    'category' => $category,
                    'preview_url' => isset($v['preview_url']) ? $v['preview_url'] : ''
                );
            }
            echo json_encode(array(
                'status' => 1,
                'voices' => $formatted,
                'count' => count($formatted)
            ));
            exit;
        }
    }

    echo json_encode(array(
        'status' => 0,
        'message' => 'Falha ao buscar vozes na ElevenLabs. Verifique sua chave de API. Código HTTP: ' . $httpCode
    ));
    exit;
}

// Fetch single voice by ID
if (empty($voiceId)) {
    echo json_encode(array('status' => 0, 'message' => 'Por favor, informe o Voice ID da ElevenLabs.'));
    exit;
}

$ch = curl_init('https://api.elevenlabs.io/v1/voices/' . urlencode($voiceId));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'xi-api-key: ' . $apiKey,
    'Content-Type: application/json'
));
curl_setopt($ch, CURLOPT_TIMEOUT, 12);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr = curl_error($ch);
curl_close($ch);

if ($httpCode === 200 && $response) {
    $v = json_decode($response, true);
    if (isset($v['voice_id'])) {
        $category = isset($v['category']) ? $v['category'] : 'cloned';
        $labelDesc = '';
        if (isset($v['labels']) && is_array($v['labels'])) {
            $pairs = array();
            foreach ($v['labels'] as $k => $val) {
                $pairs[] = "$k: $val";
            }
            if (!empty($pairs)) $labelDesc = ' (' . implode(', ', array_slice($pairs, 0, 2)) . ')';
        }
        $badge = ($category === 'cloned') ? ' [Voz Clonada]' : '';
        $displayVoiceName = $v['name'] . $badge . $labelDesc;

        echo json_encode(array(
            'status' => 1,
            'voice' => array(
                'voice_id' => $v['voice_id'],
                'name' => $displayVoiceName,
                'raw_name' => $v['name'],
                'category' => $category,
                'preview_url' => isset($v['preview_url']) ? $v['preview_url'] : ''
            ),
            'message' => 'Voz "' . $v['name'] . '" importada com sucesso da ElevenLabs!'
        ));
        exit;
    }
}

echo json_encode(array(
    'status' => 0,
    'message' => 'Voice ID "' . $voiceId . '" não foi encontrado na ElevenLabs ou chave de API inválida (HTTP ' . $httpCode . ').'
));
?>