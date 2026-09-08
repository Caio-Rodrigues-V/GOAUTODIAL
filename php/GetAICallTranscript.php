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

echo json_encode(array(
    'status' => 1,
    'call' => $call,
    'transcripts' => $transcripts
));
?>
