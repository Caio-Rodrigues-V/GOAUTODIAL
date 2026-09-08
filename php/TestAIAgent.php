<?php
/**
 * @file        TestAIAgent.php
 * @brief       Real-time web simulator for AI Voice Agent testing
 * @copyright   (c) Dial GO Voice AI Engine
 */

require_once('CRMDefaults.php');
require_once('Session.php');
require_once('AIAgentHandler.php');

header('Content-Type: application/json');

$aiHandler = \creamy\AIAgentHandler::getInstance();

$agentId = isset($_POST['agent_id']) ? (int)$_POST['agent_id'] : 0;
$userMessage = isset($_POST['message']) ? trim($_POST['message']) : '';
$historyJson = isset($_POST['history']) ? $_POST['history'] : '[]';

$agent = $aiHandler->getAgentById($agentId);
if (!$agent) {
    echo json_encode(array('status' => 0, 'message' => 'Agente não encontrado.'));
    exit;
}

$history = json_decode($historyJson, true);
if (!is_array($history)) $history = array();

$llmProvider = $agent['llm_provider'];
$llmModel = $agent['llm_model'];
$voiceProvider = $agent['voice_provider'];
$voiceId = $agent['voice_id'];
$systemPrompt = $agent['system_prompt'];

$messages = array();
$messages[] = array('role' => 'system', 'content' => $systemPrompt);

foreach ($history as $msg) {
    if (isset($msg['role']) && isset($msg['content'])) {
        $messages[] = array('role' => $msg['role'], 'content' => $msg['content']);
    }
}

if (!empty($userMessage)) {
    $messages[] = array('role' => 'user', 'content' => $userMessage);
}

$aiResponseText = '';

// 1. Execute LLM
if ($llmProvider === 'openai') {
    $apiKey = $aiHandler->getSetting('openai_api_key', '');
    if (empty($apiKey)) {
        echo json_encode(array('status' => 0, 'message' => 'Chave da OpenAI não configurada nas Configurações de IA.'));
        exit;
    }

    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json'
    ));
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(array(
        'model' => $llmModel,
        'messages' => $messages,
        'temperature' => (float)$agent['temperature']
    )));
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $res = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && $res) {
        $data = json_decode($res, true);
        if (isset($data['choices'][0]['message']['content'])) {
            $aiResponseText = trim($data['choices'][0]['message']['content']);
        }
    } else {
        echo json_encode(array('status' => 0, 'message' => 'Erro ao comunicar com OpenAI (HTTP ' . $httpCode . '): ' . $res));
        exit;
    }
} elseif ($llmProvider === 'groq') {
    $apiKey = $aiHandler->getSetting('groq_api_key', '');
    if (empty($apiKey)) {
        echo json_encode(array('status' => 0, 'message' => 'Chave do Groq não configurada nas Configurações de IA.'));
        exit;
    }

    $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json'
    ));
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(array(
        'model' => $llmModel,
        'messages' => $messages,
        'temperature' => (float)$agent['temperature']
    )));
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $res = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && $res) {
        $data = json_decode($res, true);
        if (isset($data['choices'][0]['message']['content'])) {
            $aiResponseText = trim($data['choices'][0]['message']['content']);
        }
    } else {
        echo json_encode(array('status' => 0, 'message' => 'Erro ao comunicar com Groq (HTTP ' . $httpCode . '): ' . $res));
        exit;
    }
} else {
    // Fallback simple response
    $aiResponseText = "Olá! Sou a " . $agent['agent_name'] . ". Como posso te ajudar hoje?";
}

if (empty($aiResponseText)) {
    echo json_encode(array('status' => 0, 'message' => 'Não foi possível gerar resposta com o provedor de IA.'));
    exit;
}

// 2. Generate Audio (TTS)
$audioBase64 = '';
$ttsError = '';

if ($voiceProvider === 'elevenlabs') {
    $elevenKey = $aiHandler->getSetting('elevenlabs_api_key', '');
    if (empty($elevenKey)) {
        $ttsError = 'Chave da ElevenLabs não configurada nas Configurações de IA.';
    } else {
        // Fallback default voice if not specified
        $targetVoice = !empty($voiceId) ? $voiceId : '21m00Tcm4TlvDq8ikWAM';
        $ch = curl_init('https://api.elevenlabs.io/v1/text-to-speech/' . urlencode($targetVoice));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'xi-api-key: ' . $elevenKey,
            'Content-Type: application/json',
            'Accept: audio/mpeg'
        ));
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(array(
            'text' => $aiResponseText,
            'model_id' => 'eleven_multilingual_v2',
            'voice_settings' => array(
                'stability' => 0.5,
                'similarity_boost' => 0.75
            )
        )));
        curl_setopt($ch, CURLOPT_TIMEOUT, 25);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $audioData = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $audioData) {
            $audioBase64 = 'data:audio/mpeg;base64,' . base64_encode($audioData);
        } else {
            $ttsError = 'Erro ElevenLabs (HTTP ' . $httpCode . '): ' . $audioData;
        }
    }
} elseif ($voiceProvider === 'cartesia') {
    $cartesiaKey = $aiHandler->getSetting('cartesia_api_key', '');
    if (empty($cartesiaKey)) {
        $ttsError = 'Chave do Cartesia não configurada nas Configurações de IA.';
    } else {
        $ch = curl_init('https://api.cartesia.ai/tts/bytes');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'X-API-Key: ' . $cartesiaKey,
            'Cartesia-Version: 2024-06-10',
            'Content-Type: application/json'
        ));
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(array(
            'model_id' => 'sonic-multilingual',
            'transcript' => $aiResponseText,
            'voice' => array(
                'mode' => 'id',
                'id' => (!empty($voiceId) && $voiceId !== 'custom') ? $voiceId : '79a125e8-cd45-4c13-8a67-188112f4dd22'
            ),
            'output_format' => array(
                'container' => 'wav',
                'encoding' => 'pcm_s16le',
                'sample_rate' => 44100
            )
        )));
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $audioData = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $audioData) {
            $audioBase64 = 'data:audio/wav;base64,' . base64_encode($audioData);
        } else {
            $ttsError = 'Erro Cartesia (HTTP ' . $httpCode . '): ' . $audioData;
        }
    }
} elseif ($voiceProvider === 'openai') {
    $openAIKey = $aiHandler->getSetting('openai_api_key', '');
    if (empty($openAIKey)) {
        $ttsError = 'Chave da OpenAI não configurada nas Configurações de IA.';
    } else {
        $ch = curl_init('https://api.openai.com/v1/audio/speech');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization: Bearer ' . $openAIKey,
            'Content-Type: application/json'
        ));
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(array(
            'model' => 'tts-1',
            'input' => $aiResponseText,
            'voice' => !empty($voiceId) ? $voiceId : 'nova'
        )));
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $audioData = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $audioData) {
            $audioBase64 = 'data:audio/mpeg;base64,' . base64_encode($audioData);
        } else {
            $ttsError = 'Erro OpenAI TTS (HTTP ' . $httpCode . '): ' . $audioData;
        }
    }
}

echo json_encode(array(
    'status' => 1,
    'agent_name' => $agent['agent_name'],
    'response_text' => $aiResponseText,
    'audio_url' => $audioBase64,
    'tts_error' => $ttsError
));
?>