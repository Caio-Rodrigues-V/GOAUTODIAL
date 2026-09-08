<?php
/**
 * @file        SaveAISettings.php
 * @brief       Save AI Providers API Keys
 */
require_once('CRMDefaults.php');
require_once('Session.php');
require_once('AIAgentHandler.php');

header('Content-Type: application/json');

$handler = \creamy\AIAgentHandler::getInstance();

$keys = array(
    'groq_api_key',
    'deepgram_api_key',
    'cartesia_api_key',
    'openai_api_key',
    'elevenlabs_api_key',
    'gemini_api_key',
    'ai_server_url'
);

foreach ($keys as $k) {
    if (isset($_POST[$k])) {
        $handler->saveSetting($k, trim($_POST[$k]));
    }
}

echo json_encode(array('status' => 1, 'message' => 'Configurações de IA salvas com sucesso!'));
?>
