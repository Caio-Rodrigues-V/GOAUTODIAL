<?php
/**
 * @file        api_ai_calls.php
 * @brief       Dial GO Voice AI - REST API Endpoint for External Integrations & Webhooks
 * @copyright   (c) Dial GO Voice AI Engine
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once('php/CRMDefaults.php');
require_once('php/AIAgentHandler.php');

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$rawInput = file_get_contents('php://input');
$rawPayload = json_decode($rawInput, true);
if (!is_array($rawPayload)) {
    $rawPayload = $_POST;
}

$action = isset($_GET['action']) ? $_GET['action'] : (isset($_POST['action']) ? $_POST['action'] : null);
if (!$action) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' || !empty($rawPayload)) {
        // Se for array de contatos no topo ou no body
        if (isset($rawPayload['contacts']) || (isset($rawPayload[0]['body']['contacts']))) {
            $action = 'batch';
        } else {
            $action = 'dial';
        }
    } else {
        $action = 'list';
    }
}

switch ($action) {
    case 'list':
        $limit = isset($_GET['limit']) ? min((int)$_GET['limit'], 200) : 50;
        $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
        $agentId = isset($_GET['agent_id']) ? (int)$_GET['agent_id'] : null;
        $phone = isset($_GET['phone']) ? trim($_GET['phone']) : null;
        $status = isset($_GET['status']) ? trim($_GET['status']) : null;

        $calls = $handler->getCallLogs($limit, $offset, $agentId, $phone, $status);
        echo json_encode(array(
            'status' => 'success',
            'count' => count($calls),
            'limit' => $limit,
            'offset' => $offset,
            'data' => $calls
        ));
        break;

    case 'get':
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $call = $handler->getCallLogById($id);
        if (!$call) {
            http_response_code(404);
            echo json_encode(array('status' => 'error', 'message' => 'Call not found'));
            exit;
        }

        $transcripts = array();
        if (!empty($call['transcript_json'])) {
            $transcripts = json_decode($call['transcript_json'], true) ?: array();
        }

        echo json_encode(array(
            'status' => 'success',
            'call' => $call,
            'transcripts' => $transcripts
        ));
        break;

    case 'stats':
        $stats = $handler->getCallStats();
        echo json_encode(array(
            'status' => 'success',
            'stats' => $stats
        ));
        break;

    case 'dial':
        $payload = $rawPayload;

        // Se o n8n ou webhook enviar array [ { "body": { ... } } ]
        if (is_array($payload) && isset($payload[0]) && is_array($payload[0])) {
            $payload = $payload[0];
        }

        $topWebhookUrl = '';
        if (!empty($payload['webhookUrl'])) {
            $topWebhookUrl = (string)$payload['webhookUrl'];
        } elseif (!empty($payload['webhook_url'])) {
            $topWebhookUrl = (string)$payload['webhook_url'];
        }

        // Se estiver encapsulado em "body"
        if (isset($payload['body']) && is_array($payload['body'])) {
            $inner = $payload['body'];
            if (empty($inner['webhookUrl']) && !empty($topWebhookUrl)) {
                $inner['webhookUrl'] = $topWebhookUrl;
            }
            $payload = $inner;
        }

        // 1. Agent ID
        $agentId = isset($_GET['agent_id']) ? (int)$_GET['agent_id'] : (isset($payload['agent_id']) ? (int)$payload['agent_id'] : (isset($payload['agentId']) ? (int)$payload['agentId'] : 0));
        if (!$agentId) {
            $allAgents = $handler->getAllAgents();
            if (!empty($allAgents)) {
                $agentId = (int)$allAgents[0]['id'];
            } else {
                $agentId = 2; // fallback padrão
            }
        }

        // 2. Extração do Número de Telefone
        $rawPhone = '';
        if (!empty($payload['phone_number'])) {
            $rawPhone = (string)$payload['phone_number'];
        } elseif (!empty($payload['customerNumber'])) {
            $rawPhone = (string)$payload['customerNumber'];
        } elseif (!empty($payload['customer_number'])) {
            $rawPhone = (string)$payload['customer_number'];
        } elseif (!empty($payload['telefone'])) {
            $rawPhone = (string)$payload['telefone'];
        } elseif (!empty($payload['phone'])) {
            $rawPhone = (string)$payload['phone'];
        } elseif (!empty($payload['aluno']['telefones']) && is_array($payload['aluno']['telefones'])) {
            $rawPhone = (string)$payload['aluno']['telefones'][0];
        }

        $cleanPhone = preg_replace('/\D/', '', $rawPhone);
        if ((strlen($cleanPhone) == 12 || strlen($cleanPhone) == 13) && substr($cleanPhone, 0, 2) === '55') {
            $cleanPhone = substr($cleanPhone, 2);
        }
        $phone = $cleanPhone;

        if (!$phone) {
            http_response_code(400);
            echo json_encode(array('status' => 'error', 'message' => 'Número de telefone obrigatório (campo customerNumber ou phone_number)'));
            exit;
        }

        $agent = $handler->getAgentById($agentId);
        if (!$agent) {
            http_response_code(404);
            echo json_encode(array('status' => 'error', 'message' => "Agente com ID {$agentId} não foi encontrado."));
            exit;
        }

        // 3. Webhook / Callback URL
        $callbackWebhook = '';
        if (!empty($payload['callbackUrl'])) {
            $callbackWebhook = (string)$payload['callbackUrl'];
        } elseif (!empty($payload['callback_url'])) {
            $callbackWebhook = (string)$payload['callback_url'];
        } elseif (!empty($payload['webhookUrl'])) {
            $callbackWebhook = (string)$payload['webhookUrl'];
        } elseif (!empty($payload['webhook_url'])) {
            $callbackWebhook = (string)$payload['webhook_url'];
        }

        if (!empty($callbackWebhook)) {
            $agent['webhook_url'] = $callbackWebhook;
        }

        // 4. Metadados e IDs externos para devolver no callback
        $contactId = !empty($payload['contactId']) ? (string)$payload['contactId'] : (!empty($payload['contact_id']) ? (string)$payload['contact_id'] : '');
        $campaignContactId = !empty($payload['campaignContactId']) ? (string)$payload['campaignContactId'] : (!empty($payload['campaign_contact_id']) ? (string)$payload['campaign_contact_id'] : '');
        $campaignId = !empty($payload['campaignId']) ? (string)$payload['campaignId'] : (!empty($payload['campaign_id']) ? (string)$payload['campaign_id'] : '');

        $agent['contact_id'] = $contactId;
        $agent['campaign_contact_id'] = $campaignContactId;
        $agent['campaign_id'] = $campaignId;
        $agent['metadata'] = array(
            'contactId' => $contactId,
            'campaignContactId' => $campaignContactId,
            'campaignId' => $campaignId,
            'customerNumber' => $rawPhone,
            'cpf' => !empty($payload['cpf']) ? $payload['cpf'] : (!empty($payload['customerCpf']) ? $payload['customerCpf'] : '')
        );

        // 5. Extração de Informações do Cliente, Instituição e Dívidas
        $customerName = '';
        if (!empty($payload['customerName'])) {
            $customerName = $payload['customerName'];
        } elseif (!empty($payload['customer_name'])) {
            $customerName = $payload['customer_name'];
        } elseif (!empty($payload['name'])) {
            $customerName = $payload['name'];
        } elseif (!empty($payload['aluno']['nome'])) {
            $customerName = $payload['aluno']['nome'];
        }

        $instituicaoNome = '';
        if (!empty($payload['instituicao']['nome_fantasia'])) {
            $instituicaoNome = $payload['instituicao']['nome_fantasia'];
        } elseif (!empty($payload['instituicao']['nome'])) {
            $instituicaoNome = $payload['instituicao']['nome'];
        }

        $customVars = isset($payload['variables']) && is_array($payload['variables']) ? $payload['variables'] : (isset($payload['custom_data']) && is_array($payload['custom_data']) ? $payload['custom_data'] : array());

        if ($instituicaoNome && empty($customVars['instituicao'])) {
            $customVars['instituicao'] = $instituicaoNome;
            $customVars['escola'] = $instituicaoNome;
        }

        // Formatação de valores de dívida
        $valorTotal = '';
        if (isset($payload['resumo_dividas']['valor_total_restante'])) {
            $valorNum = (float)$payload['resumo_dividas']['valor_total_restante'];
            $valorTotal = 'R$ ' . number_format($valorNum, 2, ',', '.');
        } elseif (isset($payload['dividas'][0]['valor_original'])) {
            $valorNum = (float)$payload['dividas'][0]['valor_original'];
            $valorTotal = 'R$ ' . number_format($valorNum, 2, ',', '.');
        }
        if ($valorTotal) {
            $customVars['valor_total'] = $valorTotal;
            $customVars['valor_divida'] = $valorTotal;
            $customVars['valor_fatura'] = $valorTotal;
        }

        if (!empty($payload['regra_negociacao']['max_parcelas'])) {
            $customVars['max_parcelas'] = $payload['regra_negociacao']['max_parcelas'];
        }
        if (isset($payload['regra_negociacao']['desconto_a_vista_percentual'])) {
            $customVars['desconto_a_vista'] = $payload['regra_negociacao']['desconto_a_vista_percentual'] . '%';
        }

        // 6. Substituição inteligente de variáveis no prompt
        $greeting = isset($agent['greeting_message']) ? $agent['greeting_message'] : '';
        $systemP = isset($agent['system_prompt']) ? $agent['system_prompt'] : '';

        if ($customerName) {
            $greeting = str_replace(array('{nome}', '{name}', '{aluno}', '{cliente}'), $customerName, $greeting);
            $systemP = str_replace(array('{nome}', '{name}', '{aluno}', '{cliente}'), $customerName, $systemP);
        }
        foreach ($customVars as $k => $v) {
            if (is_scalar($v)) {
                $greeting = str_replace("{{$k}}", (string)$v, $greeting);
                $systemP = str_replace("{{$k}}", (string)$v, $systemP);
            }
        }

        // Injeta bloco de dados do atendimento para o LLM ter todo o contexto
        $contextLines = array();
        if ($customerName) $contextLines[] = "- Nome do Aluno/Cliente: {$customerName}";
        if (!empty($payload['cpf'])) $contextLines[] = "- CPF: " . $payload['cpf'];
        if ($instituicaoNome) $contextLines[] = "- Instituição de Ensino / Credor: {$instituicaoNome}";
        if ($valorTotal) $contextLines[] = "- Valor Total da Pendência: {$valorTotal}";
        if (!empty($payload['regra_negociacao'])) {
            $rn = $payload['regra_negociacao'];
            $maxP = isset($rn['max_parcelas']) ? $rn['max_parcelas'] : 1;
            $descV = isset($rn['desconto_a_vista_percentual']) ? $rn['desconto_a_vista_percentual'] : 0;
            $contextLines[] = "- Condições de Pagamento: Em até {$maxP}x. Desconto à vista: {$descV}%.";
        }
        if (!empty($contextLines)) {
            $systemP .= "\n\n[DADOS DO CLIENTE E CONDIÇÕES DE NEGOCIAÇÃO]:\n" . implode("\n", $contextLines);
        }

        $agent['greeting_message'] = $greeting;
        $agent['system_prompt'] = $systemP;

        $apiKeys = array(
            'openai_api_key' => $handler->getSetting('openai_api_key', ''),
            'groq_api_key' => $handler->getSetting('groq_api_key', ''),
            'elevenlabs_api_key' => $handler->getSetting('elevenlabs_api_key', ''),
            'cartesia_api_key' => $handler->getSetting('cartesia_api_key', ''),
            'deepgram_api_key' => $handler->getSetting('deepgram_api_key', ''),
            'gemini_api_key' => $handler->getSetting('gemini_api_key', ''),
            'anthropic_api_key' => $handler->getSetting('anthropic_api_key', ''),
            'deepseek_api_key' => $handler->getSetting('deepseek_api_key', ''),
            'azure_speech_key' => $handler->getSetting('azure_speech_key', ''),
            'azure_speech_region' => $handler->getSetting('azure_speech_region', 'eastus')
        );

        $aiServerUrl = $handler->getSetting('ai_server_url', 'http://127.0.0.1:8765');
        $dialEndpoint = rtrim($aiServerUrl, '/') . '/api/dial';

        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ? 'https' : 'http';
        $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '129.121.42.250';
        $scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
        $callbackUrl = $protocol . '://' . $host . ($scriptDir ? $scriptDir : '') . '/php/SaveAICallLog.php';
        $agent['callback_url'] = $callbackUrl;

        $ch = curl_init($dialEndpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(array(
            'agent_id' => $agentId,
            'phone_number' => $phone,
            'agent_config' => $agent,
            'api_keys' => $apiKeys
        )));
        curl_setopt($ch, CURLOPT_TIMEOUT, 8);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $response) {
            echo $response;
        } else {
            echo json_encode(array(
                'status' => 'error',
                'message' => 'O Motor de Voz (ai_engine) não está rodando no servidor na porta 8765. No terminal do servidor, execute: cd ai_engine && python3 server.py'
            ));
        }
        break;

    case 'batch':
        $raw = file_get_contents('php://input');
        $payload = json_decode($raw, true) ?: $_POST;

        $agentId = isset($payload['agent_id']) ? (int)$payload['agent_id'] : 0;
        $contacts = isset($payload['contacts']) ? $payload['contacts'] : array();
        $concurrency = isset($payload['concurrency']) ? (int)$payload['concurrency'] : 5;
        $delayMs = isset($payload['delay_ms']) ? (int)$payload['delay_ms'] : 250;

        if (!$agentId || empty($contacts)) {
            http_response_code(400);
            echo json_encode(array('status' => 'error', 'message' => 'agent_id and contacts array are required'));
            exit;
        }

        $agent = $handler->getAgentById($agentId);
        if (!$agent) {
            http_response_code(404);
            echo json_encode(array('status' => 'error', 'message' => 'Agent not found'));
            exit;
        }

        if (!empty($payload['webhook_url'])) {
            $agent['webhook_url'] = $payload['webhook_url'];
        }

        $apiKeys = array(
            'openai_api_key' => $handler->getSetting('openai_api_key', ''),
            'groq_api_key' => $handler->getSetting('groq_api_key', ''),
            'elevenlabs_api_key' => $handler->getSetting('elevenlabs_api_key', ''),
            'cartesia_api_key' => $handler->getSetting('cartesia_api_key', ''),
            'deepgram_api_key' => $handler->getSetting('deepgram_api_key', ''),
            'gemini_api_key' => $handler->getSetting('gemini_api_key', ''),
            'anthropic_api_key' => $handler->getSetting('anthropic_api_key', ''),
            'deepseek_api_key' => $handler->getSetting('deepseek_api_key', '')
        );

        $aiServerUrl = $handler->getSetting('ai_server_url', 'http://127.0.0.1:8765');
        $batchEndpoint = rtrim($aiServerUrl, '/') . '/api/dial/batch';

        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ? 'https' : 'http';
        $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '129.121.42.250';
        $scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
        $agent['callback_url'] = $protocol . '://' . $host . ($scriptDir ? $scriptDir : '') . '/php/SaveAICallLog.php';

        $ch = curl_init($batchEndpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(array(
            'agent_id' => $agentId,
            'contacts' => $contacts,
            'concurrency' => $concurrency,
            'delay_ms' => $delayMs,
            'agent_config' => $agent,
            'api_keys' => $apiKeys
        )));
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $response) {
            echo $response;
        } else {
            echo json_encode(array(
                'status' => 'error',
                'message' => 'Falha ao conectar com o motor de voz na porta 8765.'
            ));
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(array('status' => 'error', 'message' => 'Invalid action. Supported: dial, batch, list, get, stats'));
        break;
}
?>
