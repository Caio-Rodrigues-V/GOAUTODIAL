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
    <title>DIALog DDM - Agentes de Voz IA</title>
    <meta content='width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no' name='viewport'>

    <?php 
        print $ui->standardizedThemeCSS(); 
        print $ui->creamyThemeCSS();
        print $ui->dataTablesTheme();
    ?>
    <link href="css/dialog_ddm.css?v=2.6.0" rel="stylesheet" type="text/css" />
</head>

<?php print $ui->creamyBody(); ?>
<div class="wrapper">
    <?php print $ui->creamyHeader($user); ?>
    <?php print $ui->getSidebar($user->getUserId(), $user->getUserName(), $user->getUserRole(), $user->getUserAvatar()); ?>

    <aside class="right-side">
        <div class="ddm-container">
            
            <!-- Breadcrumbs -->
            <div class="ddm-breadcrumbs">
                <a href="index.php">Dashboard</a>
                <span class="separator">/</span>
                <span class="current">Agentes de Voz com IA</span>
            </div>

            <!-- Page Header (Clean Enterprise Layout) -->
            <div class="ddm-page-header">
                <div>
                    <h1 class="ddm-page-title">Agentes de Voz com IA</h1>
                    <p class="ddm-page-subtitle">Gerencie seus agentes conversacionais, modelos de linguagem, vozes e configurações de telefonia.</p>
                </div>
                <div class="ddm-page-actions">
                    <a href="ai_settings.php" class="ddm-btn ddm-btn-secondary">
                        <i data-lucide="key" style="width:16px;height:16px;"></i>
                        <span>Chaves de API</span>
                    </a>
                    <a href="add_ai_agent.php" class="ddm-btn ddm-btn-primary">
                        <i data-lucide="plus" style="width:16px;height:16px;"></i>
                        <span>Novo Agente</span>
                    </a>
                </div>
            </div>

            <!-- Agents List Table Container -->
            <div class="ddm-table-container">
                <div style="padding: 18px 20px; border-bottom: 1px solid var(--ddm-border); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
                    <div>
                        <div class="ddm-section-title">Agentes Configurados</div>
                        <div class="ddm-section-desc" style="margin-bottom:0;">Selecione um agente para ajustar parâmetros de voz, prompt, LLM ou realizar teste telefônico.</div>
                    </div>
                    <div>
                        <span class="ddm-badge ddm-badge-neutral">
                            Total: <strong><?=count($agents)?></strong> agentes
                        </span>
                    </div>
                </div>

                <div style="overflow-x:auto;">
                    <table class="table ddm-table" id="table_ai_agents" width="100%">
                        <thead>
                            <tr>
                                <th>Agente</th>
                                <th>Modelo LLM</th>
                                <th>Voz TTS</th>
                                <th>Saudação Inicial</th>
                                <th>Transfere para</th>
                                <th>Status</th>
                                <th style="text-align: right; width: 180px;">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($agents)): ?>
                                <?php foreach ($agents as $ag): ?>
                                <tr>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 12px;">
                                            <div style="width: 34px; height: 34px; border-radius: 8px; background: var(--ddm-surface-subtle); border: 1px solid var(--ddm-border); display: flex; align-items: center; justify-content: center; color: var(--ddm-primary);">
                                                <i data-lucide="bot" style="width:18px;height:18px;"></i>
                                            </div>
                                            <div>
                                                <div style="font-weight: 600; color: var(--ddm-text-primary); font-size: 14px;">
                                                    <a href="edit_ai_agent.php?id=<?=$ag['agent_id']?>" style="color: inherit; text-decoration: none;">
                                                        <?=htmlspecialchars($ag['agent_name'])?>
                                                    </a>
                                                </div>
                                                <div style="font-size: 12px; color: var(--ddm-text-secondary); margin-top: 1px;">
                                                    ID #<?=$ag['agent_id']?> <?=!empty($ag['description']) ? '• ' . htmlspecialchars(mb_strimwidth($ag['description'], 0, 40, '...')) : ''?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="ddm-badge ddm-badge-neutral" style="font-weight: 600;">
                                            <?=strtoupper($ag['llm_provider'])?>
                                        </span>
                                        <div style="font-size: 12px; color: var(--ddm-text-secondary); margin-top: 3px; font-family: monospace;">
                                            <?=htmlspecialchars($ag['llm_model'])?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="ddm-badge ddm-badge-brand" style="font-weight: 600;">
                                            <?=strtoupper($ag['voice_provider'])?>
                                        </span>
                                        <div style="font-size: 12px; color: var(--ddm-text-secondary); margin-top: 3px;">
                                            <?=htmlspecialchars(!empty($ag['voice_name']) ? $ag['voice_name'] : $ag['voice_id'])?>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="font-size: 12.5px; color: var(--ddm-text-secondary); max-width: 260px; font-style: italic;">
                                            "<?=htmlspecialchars(mb_strimwidth($ag['greeting_message'] ?? '', 0, 50, '...'))?>"
                                        </div>
                                    </td>
                                    <td>
                                        <?php if (!empty($ag['transfer_phone_or_queue'])): ?>
                                            <span class="ddm-badge ddm-badge-neutral">
                                                <i data-lucide="phone-forwarded" style="width:13px;height:13px;"></i>
                                                Ramal <?=htmlspecialchars($ag['transfer_phone_or_queue'])?>
                                            </span>
                                        <?php else: ?>
                                            <span style="font-size: 12px; color: var(--ddm-text-muted);">Nenhum</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($ag['status'] == 'Y'): ?>
                                            <span class="ddm-badge ddm-badge-success">
                                                <span class="ddm-dot-indicator ddm-dot-success"></span> Ativo
                                            </span>
                                        <?php else: ?>
                                            <span class="ddm-badge ddm-badge-neutral">
                                                <span class="ddm-dot-indicator ddm-dot-neutral"></span> Inativo
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: right; white-space: nowrap;">
                                        <button class="ddm-btn ddm-btn-secondary ddm-btn-xs btn-open-dial-modal" data-id="<?=$ag['agent_id']?>" data-name="<?=htmlspecialchars($ag['agent_name'])?>" title="Fazer Ligação de Teste">
                                            <i data-lucide="phone" style="width:13px;height:13px;color:var(--ddm-primary);"></i>
                                            <span>Ligar</span>
                                        </button>
                                        <a href="edit_ai_agent.php?id=<?=$ag['agent_id']?>" class="ddm-btn ddm-btn-secondary ddm-btn-xs" title="Editar Agente">
                                            <i data-lucide="edit-3" style="width:13px;height:13px;"></i>
                                        </a>
                                        <button class="ddm-btn ddm-btn-ghost ddm-btn-xs btn-delete-agent" data-id="<?=$ag['agent_id']?>" data-name="<?=htmlspecialchars($ag['agent_name'])?>" title="Excluir Agente" style="color:var(--ddm-danger) !important;">
                                            <i data-lucide="trash-2" style="width:13px;height:13px;"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; padding: 48px 20px;">
                                        <div style="width:48px; height:48px; border-radius:12px; background:var(--ddm-surface-subtle); display:flex; align-items:center; justify-content:center; margin:0 auto 14px auto; color:var(--ddm-text-muted);">
                                            <i data-lucide="bot" style="width:24px;height:24px;"></i>
                                        </div>
                                        <div style="color:var(--ddm-text-primary); font-size:15px; font-weight:600;">Nenhum agente de IA cadastrado no momento</div>
                                        <div style="color:var(--ddm-text-secondary); font-size:13px; margin-top:4px;">Crie seu primeiro agente conversacional para começar a operar.</div>
                                        <a href="add_ai_agent.php" class="ddm-btn ddm-btn-primary" style="margin-top:16px;">
                                            <i data-lucide="plus" style="width:16px;height:16px;"></i> Criar Primeiro Agente
                                        </a>
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
        <div class="modal-content">
            <div class="modal-header">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div style="width: 32px; height: 32px; border-radius: 8px; background: var(--ddm-primary-soft); display: flex; align-items: center; justify-content: center; color: var(--ddm-primary);">
                        <i data-lucide="phone-call" style="width:16px;height:16px;"></i>
                    </div>
                    <h4 class="modal-title">Teste Telefônico com IA</h4>
                </div>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form id="form_make_ai_call">
                <input type="hidden" name="agent_id" id="dial_agent_id" value="" />
                <div class="modal-body">
                    <p style="font-size: 13px; color: var(--ddm-text-secondary); margin-bottom: 18px; line-height: 1.5;">
                        O discador originará uma chamada SIP para o número informado. Ao atender, o assistente <strong id="dial_agent_name_display" style="color:var(--ddm-text-primary);">Sofia</strong> conversará em tempo real.
                    </p>
                    
                    <div class="ddm-form-group">
                        <label class="ddm-form-label">Número de Telefone / Celular (com DDD):</label>
                        <input type="text" name="phone_number" id="dial_phone_input" class="ddm-input" placeholder="Ex: 11987654321" required autofocus />
                        <div class="ddm-form-hint">Formato: DDD + Número (apenas dígitos).</div>
                    </div>

                    <div id="dial_call_status" style="display:none; margin-top: 14px;" class="alert alert-info">
                        <!-- Call status message -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="ddm-btn ddm-btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="ddm-btn ddm-btn-primary" id="btn_submit_dial_call">
                        <i data-lucide="phone-outgoing" style="width:16px;height:16px;"></i>
                        <span>Discar Agora</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Teste de Agente / Simulador de Voz Web -->
<div class="modal fade" id="modal_test_agent" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-md">
        <div class="modal-content">
            <div class="modal-header">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div style="width: 32px; height: 32px; border-radius: 8px; background: var(--ddm-primary-soft); display: flex; align-items: center; justify-content: center; color: var(--ddm-primary);">
                        <i data-lucide="headphones" style="width:16px;height:16px;"></i>
                    </div>
                    <h4 class="modal-title">Simulador de Voz: <span id="test_agent_title_name"></span></h4>
                </div>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body" style="background: var(--ddm-surface-subtle);">
                <div style="background: #FFFFFF; border: 1px solid var(--ddm-border); border-radius: var(--ddm-radius-input); padding: 16px; height: 320px; overflow-y: auto;" id="test_chat_box">
                    <!-- Dynamic chat bubbles -->
                </div>
                
                <div id="test_agent_status" style="margin-top: 10px; font-size: 12px; color: var(--ddm-text-secondary); font-weight: 500;">
                    <span class="ddm-dot-indicator ddm-dot-success" style="margin-right:4px;"></span> Pronto para conversar
                </div>

                <!-- Hidden audio player for TTS playback -->
                <audio id="tts_audio_player" style="display:none;"></audio>
            </div>
            <div class="modal-footer" style="background: var(--ddm-surface);">
                <form id="form_send_test_message" style="display: flex; gap: 8px; width: 100%;">
                    <input type="text" id="test_user_input" class="ddm-input" placeholder="Digite uma mensagem de teste..." required autocomplete="off" />
                    <button type="submit" class="ddm-btn ddm-btn-primary" id="btn_send_test_msg">
                        <i data-lucide="send" style="width:15px;height:15px;"></i>
                        <span>Enviar</span>
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
    var bg = isAI ? 'var(--ddm-surface-subtle)' : 'var(--ddm-primary-soft)';
    var color = isAI ? 'var(--ddm-text-primary)' : 'var(--ddm-primary-hover)';
    var border = isAI ? '1px solid var(--ddm-border)' : '1px solid var(--ddm-primary-border)';
    var icon = isAI ? '<i data-lucide="bot" style="width:13px;height:13px;"></i>' : '<i data-lucide="user" style="width:13px;height:13px;"></i>';

    var audioBtn = '';
    if (isAI && audioUrl) {
        audioBtn = '<div style="margin-top: 6px;"><button type="button" class="ddm-btn ddm-btn-secondary ddm-btn-xs btn-play-msg-audio"><i data-lucide="volume-2" style="width:12px;height:12px;color:var(--ddm-primary);"></i> Ouvir Áudio</button></div>';
    }

    var $msgElem = $('<div style="margin-bottom: 12px; text-align: ' + align + ';">' +
        '<div style="display: inline-block; max-width: 82%; text-align: left; padding: 10px 14px; border-radius: 10px; background: ' + bg + '; color: ' + color + '; border: ' + border + '; box-shadow: 0 1px 2px rgba(16,24,40,0.04);">' +
            '<div style="font-size: 11px; opacity: 0.85; margin-bottom: 3px; font-weight: 600;">' + icon + ' ' + sender + '</div>' +
            '<div style="font-size: 13px; line-height: 1.45; color: var(--ddm-text-primary);">' + text + '</div>' +
            audioBtn +
        '</div>' +
    '</div>');

    if (audioUrl) {
        $msgElem.find('.btn-play-msg-audio').click(function() {
            playVoiceAudio(audioUrl);
        });
    }

    $box.append($msgElem);
    if (typeof lucide !== 'undefined') { lucide.createIcons(); }
    $box.scrollTop($box[0].scrollHeight);
}

function playVoiceAudio(audioUrl) {
    if (!audioUrl) return;
    try {
        var audio = new Audio(audioUrl);
        audio.play().catch(function(e) {
            console.log('Autoplay notice:', e);
            $('#test_agent_status').html('<span class="text-info">Clique em "Ouvir Áudio" para reproduzir.</span>');
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

    if (typeof lucide !== 'undefined') { lucide.createIcons(); }

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
            if (typeof lucide !== 'undefined') { lucide.createIcons(); }
        }, 500);
    });

    // Make AI Call
    $('#form_make_ai_call').submit(function(e) {
        e.preventDefault();
        var $btn = $('#btn_submit_dial_call');
        var $status = $('#dial_call_status');

        $btn.prop('disabled', true).html('Disparando chamada...');
        $status.show().removeClass('alert-danger alert-success').addClass('alert-info').html('Conectando ao tronco SIP e discando...');

        var formData = $(this).serialize();
        $.post('php/MakeAICall.php', formData, function(res) {
            $btn.prop('disabled', false).html('<i data-lucide="phone-outgoing" style="width:16px;height:16px;"></i> <span>Discar Novamente</span>');
            if (res.status === 1) {
                $status.removeClass('alert-info alert-danger').addClass('alert-success').html(res.message);
            } else {
                $status.removeClass('alert-info alert-success').addClass('alert-danger').html(res.message || 'Erro ao discar.');
            }
            if (typeof lucide !== 'undefined') { lucide.createIcons(); }
        }, 'json').fail(function() {
            $btn.prop('disabled', false).html('<i data-lucide="phone-outgoing" style="width:16px;height:16px;"></i> <span>Discar Agora</span>');
            $status.removeClass('alert-info alert-success').addClass('alert-danger').html('Erro de conexão com o servidor telefônico.');
            if (typeof lucide !== 'undefined') { lucide.createIcons(); }
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
