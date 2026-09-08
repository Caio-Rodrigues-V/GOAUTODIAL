<?php
/**
 * @file        ai_agents.php
 * @brief       AI Voice Agents Management
 * @copyright   (c) Dial GO Voice AI Engine
 */

require_once('php/UIHandler.php');
require_once('php/APIHandler.php');
require_once('php/CRMDefaults.php');
require_once('php/LanguageHandler.php');
require_once('php/Session.php');
require_once('php/AIAgentHandler.php');

$ui = \creamy\UIHandler::getInstance();
$api = \creamy\APIHandler::getInstance();
$lh = \creamy\LanguageHandler::getInstance();
$user = \creamy\CreamyUser::currentUser();
$aiHandler = \creamy\AIAgentHandler::getInstance();

if ($user->getUserRole() != CRM_DEFAULTS_USER_ROLE_ADMIN) {
    header("location: agent.php");
    exit;
}

$agents = $aiHandler->getAllAgents();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Dial GO - Agentes de IA (Voice AI)</title>
    <meta content='width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no' name='viewport'>

    <?php 
        print $ui->standardizedThemeCSS(); 
        print $ui->creamyThemeCSS();
        print $ui->dataTablesTheme();
    ?>
    <link href="css/style.css" rel="stylesheet" type="text/css" />
    <style>
        .badge-active { background-color: #00a65a; color: #fff; padding: 4px 10px; border-radius: 12px; font-weight: 600; }
        .badge-inactive { background-color: #dd4b39; color: #fff; padding: 4px 10px; border-radius: 12px; font-weight: 600; }
        .agent-avatar { width: 36px; height: 36px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; background: #3c8dbc; color: #fff; font-weight: bold; margin-right: 10px; }
        .tech-pill { display: inline-block; padding: 2px 8px; border-radius: 4px; background: #eef2f7; border: 1px solid #d2d6de; font-size: 11px; margin-right: 4px; }
    </style>
</head>

<?php print $ui->creamyBody(); ?>
<div class="wrapper">
    <?php print $ui->creamyHeader($user); ?>
    <?php print $ui->getSidebar($user->getUserId(), $user->getUserName(), $user->getUserRole(), $user->getUserAvatar()); ?>

    <aside class="right-side">
        <section class="content-header">
            <h1>
                <i class="fa fa-magic"></i> Agentes de Voz com IA
                <small>Gerencie seus robôs de voz com inteligência artificial para discagem e atendimento</small>
            </h1>
            <ol class="breadcrumb">
                <li><a href="./index.php"><i class="fa fa-home"></i> Home</a></li>
                <li class="active">Agentes de IA</li>
            </ol>
        </section>

        <section class="content">
            <div class="row" style="margin-bottom: 15px;">
                <div class="col-md-12">
                    <a href="add_ai_agent.php" class="btn btn-primary btn-flat"><i class="fa fa-plus-circle"></i> Criar Novo Agente de IA</a>
                    <a href="ai_settings.php" class="btn btn-default btn-flat pull-right"><i class="fa fa-key"></i> Configurar Chaves de API (OpenAI / Groq / Cartesia / Deepgram)</a>
                </div>
            </div>

            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title"><i class="fa fa-users"></i> Seus Agentes de IA Cadastrados</h3>
                </div>
                <div class="box-body">
                    <table class="table table-bordered table-striped" id="table_ai_agents" width="100%">
                        <thead>
                            <tr>
                                <th>Agente</th>
                                <th>Modelo LLM</th>
                                <th>Voz TTS</th>
                                <th>Saudação Inicial</th>
                                <th>Transfere para</th>
                                <th>Status</th>
                                <th style="width: 140px; text-align: center;">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($agents)): ?>
                                <?php foreach ($agents as $ag): ?>
                                <tr>
                                    <td>
                                        <div style="display: flex; align-items: center;">
                                            <div class="agent-avatar"><?=substr($ag['agent_name'], 0, 1)?></div>
                                            <div>
                                                <strong><?=htmlspecialchars($ag['agent_name'])?></strong><br/>
                                                <small class="text-muted"><?=htmlspecialchars($ag['description'])?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="tech-pill"><b><?=strtoupper($ag['llm_provider'])?></b></span><br/>
                                        <small><?=htmlspecialchars($ag['llm_model'])?></small>
                                    </td>
                                    <td>
                                        <span class="tech-pill"><b><?=strtoupper($ag['voice_provider'])?></b></span><br/>
                                        <small><?=htmlspecialchars(!empty($ag['voice_name']) ? $ag['voice_name'] : $ag['voice_id'])?></small>
                                    </td>
                                    <td>
                                        <small><i>"<?=htmlspecialchars(mb_strimwidth($ag['greeting_message'], 0, 60, '...'))?>"</i></small>
                                    </td>
                                    <td>
                                        <?php if (!empty($ag['transfer_phone_or_queue'])): ?>
                                            <span class="label label-info"><i class="fa fa-phone"></i> Ramal/Fila: <?=htmlspecialchars($ag['transfer_phone_or_queue'])?></span>
                                        <?php else: ?>
                                            <span class="text-muted">Sem transferência</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($ag['status'] == 'Y'): ?>
                                            <span class="badge-active">ATIVO</span>
                                        <?php else: ?>
                                            <span class="badge-inactive">INATIVO</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <a href="edit_ai_agent.php?id=<?=$ag['agent_id']?>" class="btn btn-default btn-xs" title="Editar Agente"><i class="fa fa-pencil"></i></a>
                                        <button class="btn btn-danger btn-xs btn-delete-agent" data-id="<?=$ag['agent_id']?>" data-name="<?=htmlspecialchars($ag['agent_name'])?>" title="Excluir"><i class="fa fa-trash"></i></button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; padding: 30px;">
                                        <p class="text-muted">Nenhum agente de IA cadastrado no momento.</p>
                                        <a href="add_ai_agent.php" class="btn btn-sm btn-primary"><i class="fa fa-plus"></i> Criar Primeiro Agente</a>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </aside>
</div>

<?php print $ui->standardizedThemeJS(); ?>
<script type="text/javascript">
$(document).ready(function() {
    $('#table_ai_agents').DataTable({
        responsive: true,
        language: {
            search: "Buscar:",
            lengthMenu: "Mostrar _MENU_ agentes",
            info: "Mostrando _START_ a _END_ de _TOTAL_ agentes",
            paginate: { first: "Primeiro", previous: "Anterior", next: "Próximo", last: "Último" }
        }
    });

    $('.btn-delete-agent').click(function() {
        var agentId = $(this).data('id');
        var agentName = $(this).data('name');
        if (confirm("Deseja realmente excluir o agente '" + agentName + "'?")) {
            $.post('php/DeleteAIAgent.php', { agent_id: agentId }, function(res) {
                if (res.status == 1) {
                    location.reload();
                } else {
                    alert(res.message || 'Erro ao excluir agente.');
                }
            }, 'json').fail(function() {
                alert('Erro de conexão ao excluir agente.');
            });
        }
    });
});
</script>
</body>
</html>
