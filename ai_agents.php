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

	//proper user redirects
	if ($user && $user->getUserRole() == CRM_DEFAULTS_USER_ROLE_AGENT) {
		header("location: agent.php");
		exit();
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
                                        <button class="btn btn-success btn-xs btn-test-agent" data-id="<?=$ag['agent_id']?>" data-name="<?=htmlspecialchars($ag['agent_name'])?>" data-greeting="<?=htmlspecialchars($ag['greeting_message'])?>" title="Testar Conversa no Navegador"><i class="fa fa-headphones"></i> Testar Web</button>
                                        <button class="btn btn-primary btn-xs btn-open-dial-modal" data-id="<?=$ag['agent_id']?>" data-name="<?=htmlspecialchars($ag['agent_name'])?>" title="Fazer Ligação de Teste para Telefone"><i class="fa fa-phone"></i> <b>Ligar p/ Celular</b></button>
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

<!-- Modal Ligar para Telefone via Oktor -->
<div class="modal fade" id="modal_dial_phone" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-sm" style="width: 420px;">
        <div class="modal-content" style="border-radius: 8px;">
            <div class="modal-header bg-green" style="color: #fff; border-top-left-radius: 8px; border-top-right-radius: 8px;">
                <button type="button" class="close" data-dismiss="modal" style="color: #fff; opacity: 0.9;">&times;</button>
                <h4 class="modal-title"><i class="fa fa-phone"></i> Teste Telefônico com IA</h4>
            </div>
            <form id="form_make_ai_call">
                <input type="hidden" name="agent_id" id="dial_agent_id" value="" />
                <div class="modal-body" style="padding: 20px;">
                    <p>O discador vai usar o <strong>Tronco SIP da Oktor (Tech Prefix 5908355)</strong> para ligar para o seu número. Ao atender, a <strong id="dial_agent_name_display">Sofia</strong> vai conversar com você no telefone!</p>
                    
                    <div class="form-group">
                        <label><i class="fa fa-mobile-phone"></i> Seu Telefone / Celular (com DDD):</label>
                        <input type="text" name="phone_number" id="dial_phone_input" class="form-control input-lg" placeholder="Ex: 11987654321" required autofocus />
                        <small class="text-muted">Digite apenas DDD + Número (ex: 11987654321 ou 21999998888).</small>
                    </div>

                    <div id="dial_call_status" style="display:none; margin-top: 10px;" class="alert alert-info">
                        <!-- Call status message -->
                    </div>
                </div>
                <div class="modal-footer" style="background: #f8fafc;">
                    <button type="submit" class="btn btn-success btn-lg btn-block" id="btn_submit_dial_call">
                        <i class="fa fa-phone"></i> <strong>Discar Agora</strong>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Teste de Agente / Simulador de Voz -->
<div class="modal fade" id="modal_test_agent" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-md">
        <div class="modal-content" style="border-radius: 8px;">
            <div class="modal-header bg-primary" style="color: #fff; border-top-left-radius: 8px; border-top-right-radius: 8px;">
                <button type="button" class="close" data-dismiss="modal" style="color: #fff; opacity: 0.9;">&times;</button>
                <h4 class="modal-title"><i class="fa fa-headphones"></i> Teste de Voz ao Vivo: <span id="test_agent_title_name"></span></h4>
            </div>
            <div class="modal-body" style="background: #f8fafc; padding: 15px;">
                <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 6px; padding: 15px; height: 320px; overflow-y: auto;" id="test_chat_box">
                    <!-- Dynamic chat bubbles -->
                </div>
                
                <div id="test_agent_status" style="margin-top: 8px; font-size: 13px; color: #64748b; font-weight: 500;">
                    <i class="fa fa-circle text-success"></i> Pronto para conversar
                </div>

                <!-- Hidden audio player for TTS playback -->
                <audio id="tts_audio_player" style="display:none;"></audio>
            </div>
            <div class="modal-footer" style="background: #fff; border-bottom-left-radius: 8px; border-bottom-right-radius: 8px;">
                <form id="form_send_test_message" style="display: flex; gap: 8px;">
                    <input type="text" id="test_user_input" class="form-control input-lg" placeholder="Fale algo com a IA (ex: Olá, quem é você?)..." required autocomplete="off" />
                    <button type="submit" class="btn btn-primary btn-lg" id="btn_send_test_msg">
                        <i class="fa fa-paper-plane"></i> Enviar
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php print $ui->standardizedThemeJS(); ?>
<script type="text/javascript">
var currentTestAgentId = null;
var testConversationHistory = [];

function appendChatMessage(sender, text, isAI, audioUrl) {
    var $box = $('#test_chat_box');
    var align = isAI ? 'left' : 'right';
    var bg = isAI ? '#e0f2fe' : '#2563eb';
    var color = isAI ? '#0369a1' : '#ffffff';
    var icon = isAI ? '<i class="fa fa-magic"></i>' : '<i class="fa fa-user"></i>';

    var audioBtn = '';
    if (isAI && audioUrl) {
        audioBtn = '<div style="margin-top: 6px;"><button type="button" class="btn btn-xs btn-primary btn-play-msg-audio" style="background:#0284c7; border:none; padding: 2px 8px;"><i class="fa fa-volume-up"></i> Ouvir Voz</button></div>';
    }

    var $msgElem = $('<div style="margin-bottom: 12px; text-align: ' + align + ';">' +
        '<div style="display: inline-block; max-width: 80%; text-align: left; padding: 10px 14px; border-radius: 12px; background: ' + bg + '; color: ' + color + '; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">' +
            '<div style="font-size: 11px; opacity: 0.85; margin-bottom: 3px;">' + icon + ' <strong>' + sender + '</strong></div>' +
            '<div style="font-size: 13px; line-height: 1.4;">' + text + '</div>' +
            audioBtn +
        '</div>' +
    '</div>');

    if (audioUrl) {
        $msgElem.find('.btn-play-msg-audio').click(function() {
            playVoiceAudio(audioUrl);
        });
    }

    $box.append($msgElem);
    $box.scrollTop($box[0].scrollHeight);
}

function playVoiceAudio(audioUrl) {
    if (!audioUrl) return;
    try {
        var audio = new Audio(audioUrl);
        audio.play().catch(function(e) {
            console.log('Autoplay notice:', e);
            $('#test_agent_status').html('<span class="text-info"><i class="fa fa-info-circle"></i> Clique em "Ouvir Voz" no balão para escutar o áudio.</span>');
        });
    } catch(e) {
        console.error(e);
    }
}

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

    // Open Test Modal
    $(document).on('click', '.btn-test-agent', function() {
        currentTestAgentId = $(this).data('id');
        var agentName = $(this).data('name');
        var greeting = $(this).data('greeting') || "Olá, tudo bem? Como posso te ajudar?";

        $('#test_agent_title_name').text(agentName);
        $('#test_chat_box').empty();
        testConversationHistory = [];

        $('#modal_test_agent').modal('show');
        setTimeout(function() {
            $('#test_user_input').focus();
        }, 500);

        // Fetch initial greeting audio
        $('#test_agent_status').html('<i class="fa fa-spinner fa-spin text-primary"></i> Gerando voz inicial...');
        $.post('php/TestAIAgent.php', {
            agent_id: currentTestAgentId,
            message: '',
            history: JSON.stringify([])
        }, function(res) {
            $('#test_agent_status').html('<i class="fa fa-circle text-success"></i> Pronto para conversar');
            var greetingText = (res.response_text && res.response_text.length > 0) ? res.response_text : greeting;
            appendChatMessage(agentName, greetingText, true, res.audio_url);
            testConversationHistory.push({ role: 'assistant', content: greetingText });

            if (res.status === 1 && res.audio_url) {
                playVoiceAudio(res.audio_url);
            } else if (res.tts_error) {
                $('#test_agent_status').html('<span class="text-warning"><i class="fa fa-exclamation-triangle"></i> ' + res.tts_error + '</span>');
            }
        }, 'json').fail(function() {
            appendChatMessage(agentName, greeting, true);
            testConversationHistory.push({ role: 'assistant', content: greeting });
            $('#test_agent_status').html('<i class="fa fa-circle text-success"></i> Pronto para conversar');
        });
    });

    // Send Test Message
    $('#form_send_test_message').submit(function(e) {
        e.preventDefault();
        var userText = $.trim($('#test_user_input').val());
        if (!userText || !currentTestAgentId) return;

        appendChatMessage('Você', userText, false);
        testConversationHistory.push({ role: 'user', content: userText });
        $('#test_user_input').val('');

        $('#test_agent_status').html('<i class="fa fa-spinner fa-spin text-primary"></i> Sofia está pensando e sintetizando a voz...');
        $('#btn_send_test_msg').prop('disabled', true);

        $.post('php/TestAIAgent.php', {
            agent_id: currentTestAgentId,
            message: userText,
            history: JSON.stringify(testConversationHistory)
        }, function(res) {
            $('#btn_send_test_msg').prop('disabled', false);
            $('#test_agent_status').html('<i class="fa fa-circle text-success"></i> Pronto para conversar');

            if (res.status === 1 && res.response_text) {
                appendChatMessage(res.agent_name || 'IA', res.response_text, true, res.audio_url);
                testConversationHistory.push({ role: 'assistant', content: res.response_text });

                if (res.audio_url) {
                    playVoiceAudio(res.audio_url);
                } else if (res.tts_error) {
                    $('#test_agent_status').html('<span class="text-warning"><i class="fa fa-exclamation-triangle"></i> ' + res.tts_error + '</span>');
                }
            } else {
                alert(res.message || 'Erro ao processar resposta da IA.');
            }
        }, 'json').fail(function() {
            $('#btn_send_test_msg').prop('disabled', false);
            $('#test_agent_status').html('<i class="fa fa-times text-danger"></i> Erro de comunicação com o servidor.');
        });
    });

    // Open Dial Phone Modal
    $(document).on('click', '.btn-open-dial-modal', function() {
        var agentId = $(this).data('id');
        var agentName = $(this).data('name');
        $('#dial_agent_id').val(agentId);
        $('#dial_agent_name_display').text(agentName);
        $('#dial_call_status').hide().removeClass('alert-danger alert-success alert-info');
        $('#modal_dial_phone').modal('show');
        setTimeout(function() {
            $('#dial_phone_input').focus();
        }, 500);
    });

    // Make AI Call
    $('#form_make_ai_call').submit(function(e) {
        e.preventDefault();
        var $btn = $('#btn_submit_dial_call');
        var $status = $('#dial_call_status');

        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Originando chamada via Oktor...');
        $status.show().removeClass('alert-danger alert-success').addClass('alert-info').html('<i class="fa fa-spinner fa-spin"></i> Conectando ao tronco Oktor e discando...');

        var formData = $(this).serialize();
        $.post('php/MakeAICall.php', formData, function(res) {
            $btn.prop('disabled', false).html('<i class="fa fa-phone"></i> <strong>Discar Novamente</strong>');
            if (res.status === 1) {
                $status.removeClass('alert-info alert-danger').addClass('alert-success').html('<i class="fa fa-check-circle"></i> ' + res.message);
            } else {
                $status.removeClass('alert-info alert-success').addClass('alert-danger').html('<i class="fa fa-exclamation-triangle"></i> ' + (res.message || 'Erro ao discar.'));
            }
        }, 'json').fail(function() {
            $btn.prop('disabled', false).html('<i class="fa fa-phone"></i> <strong>Discar Agora</strong>');
            $status.removeClass('alert-info alert-success').addClass('alert-danger').html('<i class="fa fa-times"></i> Erro de conexão com o servidor de telefonia.');
        });
    });

    $(document).on('click', '.btn-delete-agent', function() {
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
