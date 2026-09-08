<?php
/**
 * @file        DeleteAIAgent.php
 * @brief       Delete AI Agent
 */
require_once('CRMDefaults.php');
require_once('Session.php');
require_once('AIAgentHandler.php');

header('Content-Type: application/json');

$handler = \creamy\AIAgentHandler::getInstance();
$agent_id = isset($_POST['agent_id']) ? (int)$_POST['agent_id'] : 0;

if ($agent_id <= 0) {
    echo json_encode(array('status' => 0, 'message' => 'ID de agente inválido.'));
    exit;
}

$res = $handler->deleteAgent($agent_id);
if ($res) {
    echo json_encode(array('status' => 1, 'message' => 'Agente excluído com sucesso!'));
} else {
    echo json_encode(array('status' => 0, 'message' => 'Erro ao excluir agente.'));
}
?>
