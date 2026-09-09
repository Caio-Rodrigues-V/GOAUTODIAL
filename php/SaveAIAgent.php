<?php
/**
 * @file        SaveAIAgent.php
 * @brief       Save or update AI Agent with Vapi-compatible parameters
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

$llm_model = isset($_POST['llm_model']) ? $_POST['llm_model'] : 'llama-3.3-70b-versatile';
if ($llm_model === 'custom' && !empty($_POST['custom_llm_model'])) {
    $llm_model = trim($_POST['custom_llm_model']);
}

$voice_id = isset($_POST['voice_id']) ? trim($_POST['voice_id']) : 'cartesia-pt-br-sofia';
$voice_name = isset($_POST['voice_name']) ? trim($_POST['voice_name']) : '';
if ($voice_id === 'custom' && !empty($_POST['custom_voice_id'])) {
    $voice_id = trim($_POST['custom_voice_id']);
    $voice_name = 'Custom (' . substr($voice_id, 0, 12) . '...)';
}
if (empty($voice_name)) {
    $voice_name = 'Custom (' . substr($voice_id, 0, 12) . '...)';
}

$tools_json = isset($_POST['tools_json']) ? $_POST['tools_json'] : null;
if (is_array($tools_json)) {
    $tools_json = json_encode($tools_json);
}

$analysis_schema_json = isset($_POST['analysis_schema_json']) ? $_POST['analysis_schema_json'] : null;
if (is_array($analysis_schema_json)) {
    $analysis_schema_json = json_encode($analysis_schema_json);
}

$data = array(
    'agent_name' => $agent_name,
    'description' => isset($_POST['description']) ? trim($_POST['description']) : '',
    'status' => (isset($_POST['status']) && $_POST['status'] == 'Y') ? 'Y' : 'N',
    'first_message_mode' => isset($_POST['first_message_mode']) ? $_POST['first_message_mode'] : 'assistant_speaks_first',
    'model_preset' => isset($_POST['model_preset']) ? $_POST['model_preset'] : 'balanced',
    'llm_provider' => isset($_POST['llm_provider']) ? $_POST['llm_provider'] : 'groq',
    'llm_model' => $llm_model,
    'temperature' => isset($_POST['temperature']) ? (float)$_POST['temperature'] : 0.7,
    'max_tokens' => isset($_POST['max_tokens']) ? (int)$_POST['max_tokens'] : 250,
    'prompt_cache_retention' => isset($_POST['prompt_cache_retention']) ? $_POST['prompt_cache_retention'] : 'in_memory',
    'prompt_cache_key' => isset($_POST['prompt_cache_key']) ? trim($_POST['prompt_cache_key']) : '',
    'tool_strict_compatibility' => (isset($_POST['tool_strict_compatibility']) && $_POST['tool_strict_compatibility'] == 'Y') ? 'Y' : 'N',
    'voice_provider' => isset($_POST['voice_provider']) ? $_POST['voice_provider'] : 'cartesia',
    'voice_id' => $voice_id,
    'voice_name' => $voice_name,
    'voice_speed' => isset($_POST['voice_speed']) ? (float)$_POST['voice_speed'] : 1.0,
    'voice_stability' => isset($_POST['voice_stability']) ? (float)$_POST['voice_stability'] : 0.7,
    'voice_clarity' => isset($_POST['voice_clarity']) ? (float)$_POST['voice_clarity'] : 0.6,
    'voice_style_exaggeration' => isset($_POST['voice_style_exaggeration']) ? (float)$_POST['voice_style_exaggeration'] : 0.2,
    'voice_optimize_latency' => isset($_POST['voice_optimize_latency']) ? (int)$_POST['voice_optimize_latency'] : 1,
    'voice_speaker_boost' => (isset($_POST['voice_speaker_boost']) && $_POST['voice_speaker_boost'] == 'Y') ? 'Y' : 'N',
    'voice_caching' => (isset($_POST['voice_caching']) && $_POST['voice_caching'] == 'N') ? 'N' : 'Y',
    'background_sound' => isset($_POST['background_sound']) ? $_POST['background_sound'] : 'off',
    'background_sound_volume' => isset($_POST['background_sound_volume']) ? (float)$_POST['background_sound_volume'] : 0.10,
    'stt_provider' => isset($_POST['stt_provider']) ? $_POST['stt_provider'] : 'deepgram',
    'stt_model' => isset($_POST['stt_model']) ? $_POST['stt_model'] : 'nova-2',
    'stt_language' => isset($_POST['stt_language']) ? $_POST['stt_language'] : 'pt-BR',
    'intelligent_turn_taking' => (isset($_POST['intelligent_turn_taking']) && $_POST['intelligent_turn_taking'] == 'N') ? 'N' : 'Y',
    'background_denoising' => (isset($_POST['background_denoising']) && $_POST['background_denoising'] == 'N') ? 'N' : 'Y',
    'silence_timeout_ms' => isset($_POST['silence_timeout_ms']) ? (int)$_POST['silence_timeout_ms'] : 500,
    'stt_fallback_provider' => isset($_POST['stt_fallback_provider']) ? $_POST['stt_fallback_provider'] : 'openai',
    'stt_fallback_model' => isset($_POST['stt_fallback_model']) ? $_POST['stt_fallback_model'] : 'whisper-1',
    'greeting_message' => isset($_POST['greeting_message']) ? trim($_POST['greeting_message']) : '',
    'system_prompt' => isset($_POST['system_prompt']) ? trim($_POST['system_prompt']) : '',
    'tools_json' => $tools_json,
    'analysis_schema_json' => $analysis_schema_json,
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
