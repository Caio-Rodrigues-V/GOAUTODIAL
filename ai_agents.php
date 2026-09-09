<?php
/**
 * @file        ai_agents.php
 * @brief       Dialog DDM - AI Voice Agents Dashboard (Design System 2.0)
 * @copyright   (c) Dialog DDM - Grupo DDM
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

if ($user && $user->getUserRole() == CRM_DEFAULTS_USER_ROLE_AGENT) {
    header("location: agent.php");
    exit();
}

$agents = $aiHandler->getAllAgents();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Dialog DDM - Agentes de Voz IA</title>
    <meta content='width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no' name='viewport'>

    <?php 
        print $ui->standardizedThemeCSS(); 
        print $ui->creamyThemeCSS();
        print $ui->dataTablesTheme();
    ?>
    <link href="css/style.css" rel="stylesheet" type="text/css" />
    <link href="css/dialog_ddm.css" rel="stylesheet" type="text/css" />
    <style>
        .ddm-agent-table-card {
            background: var(--ddm-surface);
            border: 1px solid var(--ddm-border);
            border-radius: var(--ddm-radius-lg);
            padding: 24px;
            box-shadow: var(--ddm-shadow-xs);
        }
        .ddm-agent-table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 12px;
        }
    </style>
</head>

<?php print $ui->creamyBody(); ?>
<div class="wrapper">
    <?php print $ui->creamyHeader($user); ?>
    <?php print $ui->getSidebar($user->getUserId(), $user->getUserName(), $user->getUserRole(), $user->getUserAvatar()); ?>

    <aside class="right-side">
        <div class="ddm-container">
            
            <!-- Breadcrumbs -->
            <div class="ddm-breadcrumbs">
                <a href="index.php"><i class="fa fa-home"></i> Dialog DDM</a>
                <span class="separator">/</span>
                <span class="current">Agentes de Voz IA</span>
            </div>

            <!-- Header -->
            <div class="ddm-header">
                <div class="ddm-header-left">
                    <div class="ddm-agent-avatar">
                        <i class="fa fa-microphone"></i>
                    </div>
                    <div>
                        <div style="font-size: 22px; font-weight: 800; color: var(--ddm-text-primary); letter-spacing: -0.4px;">
                            Agentes de Voz com IA
                        </div>
                        <div style="font-size: 13px; color: var(--ddm-text-secondary); margin-top: 2px;">
                            Gerencie seus robôs conversacionais inteligentes para atendimento receptivo e discagem ativa.
                        </div>
                    </div>
                </div>
                
                <div class="ddm-header-actions">
                    <a href="ai_settings.php" class="ddm-btn ddm-btn-secondary">
                        <i class="fa fa-key"></i> Chaves de API
                    </a>
                    <a href="add_ai_agent.php" class="ddm-btn ddm-btn-primary">
                        <i class="fa fa-plus"></i> Criar Novo Agente
                    </a>
                </div>
            </div>

            <!-- Agents List Card -->
            <div class="ddm-agent-table-card">
                <div class="ddm-agent-table-header">
                    <div>
                        <div class="ddm-section-title"><i class="fa fa-users text-orange"></i> Agentes Cadastrados</div>
                        <div class="ddm-section-desc">Selecione um agente para editar parâmetros de voz, prompt, LLM ou testar em tempo real.</div>
                    </div>
                </div>

                <div style="overflow-x:auto;">
                    <table class="table table-bordered table-striped ddm-tools-table" id="table_ai_agents" width="100%">
                        <thead>
                            <tr>
                                <th>Agente</th>
                                <th>Modelo LLM</th>
                                <th>Voz TTS</th>
                                <th>Saudação Inicial</th>
                                <th>Transfere para</th>
                                <th>Status</th>
                                <th style="width: 170px; text-align: right;">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($agents)): ?>
                                <?php foreach ($agents as $ag): ?>
                                <tr>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <div class="ddm-agent-avatar" style="width:36px; height:36px; font-size:15px; border-radius:8px;">
                                                <i class="fa fa-microphone"></i>
                                            </div>
                                            <div>
                                                <div style="font-weight:700; color:var(--ddm-text-primary); font-size:14px;">
                                                    <a href="edit_ai_agent.php?id=<?=$ag['agent_id']?>" style="color:inherit; text-decoration:none;">
                                                        <?=htmlspecialchars($ag['agent_name'])?>
                                                    </a>
                                                </div>
                                                <div style="font-size:11px; color:var(--ddm-text-muted);">
                                                    ID #<?=$ag['agent_id']?> <?=!empty($ag['description']) ? '• ' . htmlspecialchars($ag['description']) : ''?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="ddm-badge ddm-badge-id"><b><?=strtoupper($ag['llm_provider'])?></b></span>
                                        <div style="font-size:11px; color:var(--ddm-text-secondary); margin-top:2px; font-weight:600;">
                                            <?=htmlspecialchars($ag['llm_model'])?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="ddm-badge ddm-badge-orange"><b><?=strtoupper($ag['voice_provider'])?></b></span>
                                        <div style="font-size:11px; color:var(--ddm-text-secondary); margin-top:2px;">
                                            <?=htmlspecialchars(!empty($ag['voice_name']) ? $ag['voice_name'] : $ag['voice_id'])?>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="font-size:12px; color:var(--ddm-text-secondary); max-width:280px; font-style:italic;">
                                            "<?=htmlspecialchars(mb_strimwidth($ag['greeting_message'], 0, 55, '...'))?>"
                                        </div>
                                    </td>
                                    <td>
                                        <?php if (!empty($ag['transfer_phone_or_queue'])): ?>
                                            <span class="ddm-badge ddm-badge-active"><i class="fa fa-phone"></i> Ramal <?=htmlspecialchars($ag['transfer_phone_or_queue'])?></span>
                                        <?php else: ?>
                                            <span style="font-size:12px; color:var(--ddm-text-muted);">Sem transferência</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($ag['status'] == 'Y'): ?>
                                            <span class="ddm-badge ddm-badge-active"><span class="ddm-dot-indicator ddm-dot-active"></span> Ativo</span>
                                        <?php else: ?>
                                            <span class="ddm-badge ddm-badge-inactive"><span class="ddm-dot-indicator ddm-dot-inactive"></span> Inativo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: right; white-space: nowrap;">
                                        <button class="ddm-btn ddm-btn-talk ddm-btn-xs btn-open-dial-modal" data-id="<?=$ag['agent_id']?>" data-name="<?=htmlspecialchars($ag['agent_name'])?>" title="Fazer Ligação Telefônica de Teste">
                                            <i class="fa fa-phone"></i> Ligar
                                        </button>
                                        <a href="edit_ai_agent.php?id=<?=$ag['agent_id']?>" class="ddm-btn ddm-btn-secondary ddm-btn-xs" title="Editar Agente">
                                            <i class="fa fa-pencil"></i>
                                        </a>
                                        <button class="ddm-btn ddm-btn-secondary ddm-btn-xs btn-delete-agent" data-id="<?=$ag['agent_id']?>" data-name="<?=htmlspecialchars($ag['agent_name'])?>" title="Excluir" style="color:#EF4444 !important;">
                                            <i class="fa fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; padding: 40px;">
                                        <div style="font-size:32px; color:var(--ddm-text-muted); margin-bottom:12px;"><i class="fa fa-microphone-slash"></i></div>
                                        <p style="color:var(--ddm-text-secondary); font-size:14px; font-weight:600;">Nenhum agente de IA cadastrado no momento.</p>
                                        <a href="add_ai_agent.php" class="ddm-btn ddm-btn-primary" style="margin-top:10px;"><i class="fa fa-plus"></i> Criar Primeiro Agente</a>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </aside>
</div>

<!-- Modal Ligar para Telefone via Oktor -->
<div class="modal fade" id="modal_dial_phone" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-sm" style="width: 440px;">
        <div class="modal-content" style="border-radius: var(--ddm-radius-lg); border: 1px solid var(--ddm-border); box-shadow: var(--ddm-shadow-xl);">
            <div class="modal-header" style="background: var(--ddm-surface); border-bottom: 1px solid var(--ddm-border); border-top-left-radius: var(--ddm-radius-lg); border-top-right-radius: var(--ddm-radius-lg); padding: 18px 24px;">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title" style="font-weight: 800; font-size: 16px; color: var(--ddm-text-primary);"><i class="fa fa-phone text-orange"></i> Teste Telefônico com IA</h4>
            </div>
            <form id="form_make_ai_call">
                <input type="hidden" name="agent_id" id="dial_agent_id" value="" />
                <div class="modal-body" style="padding: 24px;">
                    <p style="font-size: 13px; color: var(--ddm-text-secondary); margin-bottom: 18px;">
                        O discador Dialog DDM originará a chamada para o número indicado. Ao atender, o assistente <strong id="dial_agent_name_display" style="color:var(--ddm-primary);">Sofia</strong> conversará com você em tempo real!
                    </p>
                    
                    <div class="ddm-form-group">
                        <label class="ddm-form-label"><i class="fa fa-mobile-phone"></i> Seu Telefone / Celular (com DDD):</label>
                        <input type="text" name="phone_number" id="dial_phone_input" class="ddm-input" placeholder="Ex: 11987654321" required autofocus />
                        <div class="ddm-form-hint">Digite DDD + Número (ex: 11987654321).</div>
                    </div>

                    <div id="dial_call_status" style="display:none; margin-top: 14px;" class="alert alert-info">
                        <!-- Call status message -->
                    </div>
                </div>
                <div class="modal-footer" style="background: var(--ddm-surface-subtle); border-top: 1px solid var(--ddm-border); border-bottom-left-radius: var(--ddm-radius-lg); border-bottom-right-radius: var(--ddm-radius-lg); padding: 16px 24px;">
                    <button type="submit" class="ddm-btn ddm-btn-primary" style="width:100%; justify-content:center;" id="btn_submit_dial_call">
                        <i class="fa fa-phone"></i> Discar Agora
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Teste de Agente / Simulador de Voz Web -->
<div class="modal fade" id="modal_test_agent" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-md">
        <div class="modal-content" style="border-radius: var(--ddm-radius-lg); border: 1px solid var(--ddm-border); box-shadow: var(--ddm-shadow-xl);">
            <div class="modal-header" style="background: var(--ddm-surface); border-bottom: 1px solid var(--ddm-border); border-top-left-radius: var(--ddm-radius-lg); border-top-right-radius: var(--ddm-radius-lg); padding: 18px 24px;">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title" style="font-weight: 800; font-size: 16px; color: var(--ddm-text-primary);"><i class="fa fa-headphones text-orange"></i> Teste de Voz ao Vivo: <span id="test_agent_title_name"></span></h4>
            </div>
            <div class="modal-body" style="background: var(--ddm-surface-subtle); padding: 20px;">
                <div style="background: #fff; border: 1px solid var(--ddm-border); border-radius: var(--ddm-radius-md); padding: 16px; height: 320px; overflow-y: auto;" id="test_chat_box">
                    <!-- Dynamic chat bubbles -->
                </div>
                
                <div id="test_agent_status" style="margin-top: 10px; font-size: 12px; color: var(--ddm-text-secondary); font-weight: 600;">
                    <i class="fa fa-circle text-success"></i> Pronto para conversar
                </div>

                <!-- Hidden audio player for TTS playback -->
                <audio id="tts_audio_player" style="display:none;"></audio>
            </div>
            <div class="modal-footer" style="background: var(--ddm-surface); border-top: 1px solid var(--ddm-border); border-bottom-left-radius: var(--ddm-radius-lg); border-bottom-right-radius: var(--ddm-radius-lg); padding: 16px 20px;">
                <form id="form_send_test_message" style="display: flex; gap: 8px;">
                    <input type="text" id="test_user_input" class="ddm-input" placeholder="Fale algo com a IA (ex: Olá, quem é você?)..." required autocomplete="off" />
                    <button type="submit" class="ddm-btn ddm-btn-primary" id="btn_send_test_msg">
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
    var bg = isAI ? '#FFF7ED' : '#F97316';
    var color = isAI ? '#C2410C' : '#ffffff';
    var border = isAI ? '1px solid #FFEDD5' : 'none';
    var icon = isAI ? '<i class="fa fa-magic"></i>' : '<i class="fa fa-user"></i>';

    var audioBtn = '';
    if (isAI && audioUrl) {
        audioBtn = '<div style="margin-top: 6px;"><button type="button" class="ddm-btn ddm-btn-secondary ddm-btn-xs btn-play-msg-audio"><i class="fa fa-volume-up text-orange"></i> Ouvir Voz</button></div>';
    }

    var $msgElem = $('<div style="margin-bottom: 12px; text-align: ' + align + ';">' +
        '<div style="display: inline-block; max-width: 80%; text-align: left; padding: 10px 14px; border-radius: 12px; background: ' + bg + '; color: ' + color + '; border: ' + border + '; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">' +
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

        $('#test_agent_status').html('<i class="fa fa-spinner fa-spin text-orange"></i> Sintetizando voz inicial...');
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

        $('#test_agent_status').html('<i class="fa fa-spinner fa-spin text-orange"></i> Processando resposta e gerando áudio...');
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

        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Originando chamada...');
        $status.show().removeClass('alert-danger alert-success').addClass('alert-info').html('<i class="fa fa-spinner fa-spin"></i> Conectando ao servidor telefônico e discando...');

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
