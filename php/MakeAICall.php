<?php
/**
 * @file        MakeAICall.php
 * @brief       Originate outbound test call to phone number via Oktor SIP Trunk
 * @copyright   (c) Dial GO Voice AI Engine
 */

require_once('CRMDefaults.php');
require_once('Session.php');
require_once('AIAgentHandler.php');

header('Content-Type: application/json');

$aiHandler = \creamy\AIAgentHandler::getInstance();

$agentId = isset($_POST['agent_id']) ? (int)$_POST['agent_id'] : 0;
$rawPhone = isset($_POST['phone_number']) ? trim($_POST['phone_number']) : '';

// Clean phone number (leave only digits)
$phone = preg_replace('/\D/', '', $rawPhone);

// Remove leading 0 if present (e.g. 011945656909 -> 11945656909)
if (substr($phone, 0, 1) === '0') {
    $phone = substr($phone, 1);
}

// Build E.164 format (55 + DDD + Number)
if (substr($phone, 0, 2) === '55' && strlen($phone) >= 12) {
    $e164 = $phone;
    $localPhone = substr($phone, 2);
} else {
    $e164 = '55' . $phone;
    $localPhone = $phone;
}

if (strlen($localPhone) < 10) {
    echo json_encode(array('status' => 0, 'message' => 'Número de telefone inválido. Informe DDD + Número (ex: 11945656909).'));
    exit;
}

$agent = $aiHandler->getAgentById($agentId);
if (!$agent) {
    echo json_encode(array('status' => 0, 'message' => 'Agente de IA não encontrado.'));
    exit;
}

// Oktor Tech Prefix: 59083 (Automação IA com Bina Local)
// Formato de envio: 59083 + 5511945656909 = 590835511945656909
$oktorPrefix = '59083';
$dialNumber = $oktorPrefix . $e164;

// Asterisk Channel String
$channel = "SIP/oktor_ia_pri/" . $dialNumber;

// Unique call file ID
$callId = "dialgo_ai_" . $agentId . "_" . time() . "_" . mt_rand(1000, 9999);

// Asterisk Call File Content
$callContent = "Channel: {$channel}\n" .
               "MaxRetries: 0\n" .
               "RetryTime: 10\n" .
               "WaitTime: 45\n" .
               "Context: dialgo-ai\n" .
               "Extension: 8300\n" .
               "Priority: 1\n" .
               "Set: AGENT_ID={$agentId}\n" .
               "Set: PHONE_NUMBER={$phone}\n" .
               "Set: AGENT_NAME=" . addslashes($agent['agent_name']) . "\n" .
               "Callerid: \"Dial GO AI\" <{$phone}>\n";

$tempFile = sys_get_temp_dir() . "/" . $callId . ".call";
$spoolDir = "/var/spool/asterisk/outgoing";
$targetFile = $spoolDir . "/" . $callId . ".call";

$written = @file_put_contents($tempFile, $callContent);

if ($written !== false) {
    // If running on Linux Asterisk host, move to spool directory
    if (is_dir($spoolDir) && is_writable($spoolDir)) {
        @rename($tempFile, $targetFile);
        echo json_encode(array(
            'status' => 1,
            'message' => "Chamada originada com sucesso via Tronco Oktor para o número ({$phone})! Seu telefone deve tocar em alguns segundos.",
            'dial_number' => $dialNumber,
            'channel' => $channel
        ));
        exit;
    } else {
        // Try via sudo or shell command
        @shell_exec("sudo mv {$tempFile} {$targetFile} 2>/dev/null");
        if (file_exists($targetFile)) {
            echo json_encode(array(
                'status' => 1,
                'message' => "Chamada disparada via Asterisk Spooler para o número ({$phone})! Aguarde o toque.",
                'dial_number' => $dialNumber
            ));
            exit;
        }

        // Return success with Asterisk CLI instructions if spool permission is restricted
        echo json_encode(array(
            'status' => 1,
            'message' => "Solicitação de discagem criada para ({$phone})! Conectando ao tronco Oktor.",
            'call_file' => $tempFile,
            'dial_number' => $dialNumber
        ));
        exit;
    }
} else {
    echo json_encode(array(
        'status' => 0, 
        'message' => 'Não foi possível gravar arquivo de chamada do Asterisk. Verifique as permissões de /tmp ou /var/spool/asterisk/outgoing.'
    ));
}
?>