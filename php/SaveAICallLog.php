<?php
/**
 * @file        SaveAICallLog.php
 * @brief       Save AI Call Log & Transcripts from Python Engine
 * @copyright   (c) Dial GO Voice AI Engine
 */

require_once('CRMDefaults.php');
require_once('AIAgentHandler.php');

header('Content-Type: application/json');

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (!$data) {
    $data = $_POST;
}

if (empty($data['phone_number'])) {
    echo json_encode(array('status' => 0, 'message' => 'Número de telefone é obrigatório'));
    exit;
}

$handler = \creamy\AIAgentHandler::getInstance();

$callId = isset($data['call_id']) ? $data['call_id'] : uniqid('call_');
$recordingUrl = isset($data['recording_url']) ? $data['recording_url'] : '';

// Process audio_base64 if provided
if (!empty($data['audio_base64'])) {
    try {
        $cleanId = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $callId);
        $recordingsDir = __DIR__ . '/../recordings';
        if (!is_dir($recordingsDir)) {
            @mkdir($recordingsDir, 0777, true);
        }
        $filePath = $recordingsDir . '/ai_call_' . $cleanId . '.wav';
        $audioBytes = base64_decode($data['audio_base64']);
        if ($audioBytes && strlen($audioBytes) > 0) {
            file_put_contents($filePath, $audioBytes);
            $recordingUrl = 'recordings/ai_call_' . $cleanId . '.wav';
        }
    } catch (\Throwable $t) {}
}

$logData = array(
    'call_id' => $callId,
    'agent_id' => isset($data['agent_id']) ? (int)$data['agent_id'] : null,
    'agent_name' => isset($data['agent_name']) ? $data['agent_name'] : 'Agente IA',
    'phone_number' => $data['phone_number'],
    'status' => isset($data['status']) ? $data['status'] : 'completed',
    'duration_seconds' => isset($data['duration_seconds']) ? (int)$data['duration_seconds'] : 0,
    'llm_provider' => isset($data['llm_provider']) ? $data['llm_provider'] : '',
    'llm_model' => isset($data['llm_model']) ? $data['llm_model'] : '',
    'voice_provider' => isset($data['voice_provider']) ? $data['voice_provider'] : '',
    'voice_id' => isset($data['voice_id']) ? $data['voice_id'] : '',
    'stt_provider' => isset($data['stt_provider']) ? $data['stt_provider'] : '',
    'transcript_json' => isset($data['transcript_json']) ? (is_array($data['transcript_json']) ? json_encode($data['transcript_json']) : $data['transcript_json']) : '[]',
    'recording_url' => $recordingUrl,
    'qualification' => isset($data['qualification']) ? $data['qualification'] : 'Atendida',
    'cost_estimate' => isset($data['cost_estimate']) ? (float)$data['cost_estimate'] : 0.00,
    'created_at' => isset($data['created_at']) ? $data['created_at'] : date('Y-m-d H:i:s'),
    'ended_at' => isset($data['ended_at']) ? $data['ended_at'] : date('Y-m-d H:i:s')
);

$res = $handler->saveCallLog($logData);

if ($res) {
    echo json_encode(array('status' => 1, 'message' => 'Log e transcrição gravados com sucesso!', 'id' => $res, 'recording_url' => $recordingUrl));
} else {
    echo json_encode(array('status' => 0, 'message' => 'Falha ao gravar log da chamada'));
}
?>
