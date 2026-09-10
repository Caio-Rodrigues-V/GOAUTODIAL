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

$tabCode = isset($data['tabulation_code']) ? $data['tabulation_code'] : 'HUMAN_COMPLETED';
$callStatus = isset($data['status']) ? $data['status'] : 'completed';
if ($tabCode == 'MUTE_SILENCE' || $tabCode == 'NO_ANSWER') {
    $callStatus = 'no_answer';
} elseif ($tabCode == 'VOICEMAIL') {
    $callStatus = 'voicemail';
} elseif ($tabCode == 'CALL_DROPPED') {
    $callStatus = 'dropped';
} elseif ($tabCode == 'BUSY') {
    $callStatus = 'busy';
}

$isUnanswered = ($callStatus == 'no_answer' || $callStatus == 'busy' || $callStatus == 'invalid_number' || $tabCode == 'MUTE_SILENCE' || $tabCode == 'NO_ANSWER' || $tabCode == 'BUSY' || $tabCode == 'INVALID_NUMBER');

// Process audio_base64 only for answered calls with dialogue
if (!$isUnanswered && !empty($data['audio_base64'])) {
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

if ($isUnanswered) {
    $recordingUrl = '';
}

$logData = array(
    'call_id' => $callId,
    'agent_id' => isset($data['agent_id']) ? (int)$data['agent_id'] : null,
    'agent_name' => isset($data['agent_name']) ? $data['agent_name'] : 'Agente IA',
    'phone_number' => $data['phone_number'],
    'status' => $callStatus,
    'duration_seconds' => isset($data['duration_seconds']) ? (int)$data['duration_seconds'] : 0,
    'llm_provider' => isset($data['llm_provider']) ? $data['llm_provider'] : '',
    'llm_model' => isset($data['llm_model']) ? $data['llm_model'] : '',
    'voice_provider' => isset($data['voice_provider']) ? $data['voice_provider'] : '',
    'voice_id' => isset($data['voice_id']) ? $data['voice_id'] : '',
    'stt_provider' => isset($data['stt_provider']) ? $data['stt_provider'] : '',
    'transcript_json' => isset($data['transcript_json']) ? (is_array($data['transcript_json']) ? json_encode($data['transcript_json']) : $data['transcript_json']) : '[]',
    'recording_url' => $recordingUrl,
    'tabulation_code' => isset($data['tabulation_code']) ? $data['tabulation_code'] : 'HUMAN_COMPLETED',
    'qualification' => isset($data['qualification']) ? $data['qualification'] : (isset($data['tabulation']) ? $data['tabulation'] : 'Conversa Concluída'),
    'tabulation' => isset($data['tabulation']) ? $data['tabulation'] : (isset($data['qualification']) ? $data['qualification'] : 'Conversa Concluída'),
    'call_summary' => isset($data['call_summary']) ? $data['call_summary'] : '',
    'sentiment' => isset($data['sentiment']) ? $data['sentiment'] : 'Neutro',
    'action_needed' => isset($data['action_needed']) ? $data['action_needed'] : '',
    'cost_estimate' => isset($data['cost_estimate']) ? (float)$data['cost_estimate'] : 0.00,
    'created_at' => isset($data['created_at']) ? $data['created_at'] : date('Y-m-d H:i:s'),
    'ended_at' => isset($data['ended_at']) ? $data['ended_at'] : date('Y-m-d H:i:s')
);

$res = $handler->saveCallLog($logData);

// -------------------------------------------------------------
// Disparo Automático de Webhook para Sistemas Externos (n8n, CRM, Zapier, Webhook.site)
// -------------------------------------------------------------
$webhookUrl = !empty($data['webhook_url']) ? $data['webhook_url'] : '';
if (empty($webhookUrl) && !empty($logData['agent_id'])) {
    $agentObj = $handler->getAgentById($logData['agent_id']);
    if ($agentObj && !empty($agentObj['webhook_url'])) {
        $webhookUrl = $agentObj['webhook_url'];
    }
}

if (!empty($webhookUrl)) {
    $serverHost = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '129.121.42.250';
    $fullRecordingUrl = !empty($recordingUrl) ? "http://{$serverHost}/" . ltrim($recordingUrl, '/') : '';

    $webhookPayload = array(
        'event' => 'call.completed',
        'call_id' => $logData['call_id'],
        'agent_id' => $logData['agent_id'],
        'agent_name' => $logData['agent_name'],
        'phone_number' => $logData['phone_number'],
        'status' => $logData['status'],
        'duration_seconds' => $logData['duration_seconds'],
        'tabulation' => $logData['tabulation'],
        'tabulation_code' => $logData['tabulation_code'],
        'call_summary' => $logData['call_summary'],
        'sentiment' => $logData['sentiment'],
        'cost_estimate_brl' => $logData['cost_estimate'],
        'recording_url' => $fullRecordingUrl,
        'transcript' => json_decode($logData['transcript_json'], true) ?: array(),
        'created_at' => $logData['created_at'],
        'ended_at' => $logData['ended_at']
    );

    try {
        $ch = curl_init($webhookUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'User-Agent: DIAL-GO-VoiceAI-Webhook/1.0'));
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($webhookPayload));
        curl_setopt($ch, CURLOPT_TIMEOUT, 4);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_exec($ch);
        curl_close($ch);
    } catch (\Throwable $t) {}
}

if ($res) {
    echo json_encode(array('status' => 1, 'message' => 'Log e transcrição gravados com sucesso!', 'id' => $res, 'recording_url' => $recordingUrl));
} else {
    echo json_encode(array('status' => 0, 'message' => 'Falha ao gravar log da chamada'));
}
?>
