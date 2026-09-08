<?php
/**
 * @file        SaveAIAgent.php
 * @brief       Save or update AI Agent
 */
require_once('CRMDefaults.php');
require_once('Session.php');
require_once('AIAgentHandler.php');

header('Content-Type: application/json');

$handler = \creamy\AIAgentHandler::getInstance();

$agent_id = isset($_POST['agent_id']) ? (int)$_POST['agent_id'] : 0;
$agent_name = isset($_POST['agent_name']) ? trim($_POST['agent_name']) : '';

if (empty($agent_name)) {
    echo json_encode(array('status' => 0, 'message' => 'Nome do agente é obrigatório.'));
    exit;
}

$data = array(
    'agent_name' => $agent_name,
    'description' => isset($_POST['description']) ? trim($_POST['description']) : '',
    'status' => (isset($_POST['status']) && $_POST['status'] == 'Y') ? 'Y' : 'N',
    'llm_provider' => isset($_POST['llm_provider']) ? $_POST['llm_provider'] : 'groq',
    'llm_model' => isset($_POST['llm_model']) ? $_POST['llm_model'] : 'llama-3.3-70b-versatile',
    'voice_provider' => isset($_POST['voice_provider']) ? $_POST['voice_provider'] : 'cartesia',
    'voice_id' => isset($_POST['voice_id']) ? $_POST['voice_id'] : 'cartesia-pt-br-sofia',
    'voice_name' => isset($_POST['voice_name']) ? $_POST['voice_name'] : '',
    'stt_provider' => 'deepgram',
    'stt_model' => 'nova-2',
    'stt_language' => 'pt-BR',
    'greeting_message' => isset($_POST['greeting_message']) ? trim($_POST['greeting_message']) : '',
    'system_prompt' => isset($_POST['system_prompt']) ? trim($_POST['system_prompt']) : '',
    'temperature' => isset($_POST['temperature']) ? (float)$_POST['temperature'] : 0.7,
    'max_duration_seconds' => isset($_POST['max_duration_seconds']) ? (int)$_POST['max_duration_seconds'] : 600,
    'interruption_sensitivity' => isset($_POST['interruption_sensitivity']) ? (float)$_POST['interruption_sensitivity'] : 0.8,
    'transfer_phone_or_queue' => isset($_POST['transfer_phone_or_queue']) ? trim($_POST['transfer_phone_or_queue']) : '',
    'transfer_on_interest' => (isset($_POST['transfer_on_interest']) && $_POST['transfer_on_interest'] == 'Y') ? 'Y' : 'N',
    'hangup_on_voicemail' => (isset($_POST['hangup_on_voicemail']) && $_POST['hangup_on_voicemail'] == 'Y') ? 'Y' : 'N'
);

if ($agent_id > 0) {
    $res = $handler->updateAgent($agent_id, $data);
    if ($res) {
        echo json_encode(array('status' => 1, 'message' => 'Agente de IA atualizado com sucesso!'));
    } else {
        echo json_encode(array('status' => 0, 'message' => 'Erro ao atualizar agente no banco.'));
    }
} else {
    $new_id = $handler->createAgent($data);
    if ($new_id) {
        echo json_encode(array('status' => 1, 'agent_id' => $new_id, 'message' => 'Agente de IA criado com sucesso!'));
    } else {
        echo json_encode(array('status' => 0, 'message' => 'Erro ao cadastrar agente no banco.'));
    }
}
?>
