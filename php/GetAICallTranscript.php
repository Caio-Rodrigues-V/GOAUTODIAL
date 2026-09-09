<?php
/**
 * @file        GetAICallTranscript.php
 * @brief       Fetch Full Call Transcript & Diagnostics for Modal
 * @copyright   (c) Dial GO Voice AI Engine
 */

require_once('CRMDefaults.php');
require_once('Session.php');
require_once('AIAgentHandler.php');

header('Content-Type: application/json');

$id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['id']) ? (int)$_POST['id'] : 0);

if (!$id) {
    echo json_encode(array('status' => 0, 'message' => 'ID da chamada não informado'));
    exit;
}

$handler = \creamy\AIAgentHandler::getInstance();
$call = $handler->getCallLogById($id);

if (!$call) {
    echo json_encode(array('status' => 0, 'message' => 'Registro de chamada não encontrado'));
    exit;
}

$transcripts = array();
if (!empty($call['transcript_json'])) {
    $decoded = json_decode($call['transcript_json'], true);
    if (is_array($decoded)) {
        $transcripts = $decoded;
    }
}

$audioUrl = '';
if (!empty($call['recording_url'])) {
    $audioUrl = $call['recording_url'];
} else {
    // Check if file exists in recordings folder by call_id
    $cleanId = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $call['call_id']);
    $possibleFile = 'recordings/ai_call_' . $cleanId . '.wav';
    if (file_exists(__DIR__ . '/../' . $possibleFile)) {
        $audioUrl = $possibleFile;
    }
}

echo json_encode(array(
    'status' => 1,
    'call' => $call,
    'transcripts' => $transcripts,
    'audio_url' => $audioUrl
));
?>
