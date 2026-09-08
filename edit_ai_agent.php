<?php
/**
 * @file        edit_ai_agent.php
 * @brief       Edit AI Agent
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

$agent_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$agent = $aiHandler->getAgentById($agent_id);

if (!$agent) {
    header("location: ai_agents.php");
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Dial GO - Editar Agente de IA</title>
    <meta content='width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no' name='viewport'>

    <?php 
        print $ui->standardizedThemeCSS(); 
        print $ui->creamyThemeCSS();
    ?>
    <link href="css/style.css" rel="stylesheet" type="text/css" />
</head>

<?php print $ui->creamyBody(); ?>
<div class="wrapper">
    <?php print $ui->creamyHeader($user); ?>
    <?php print $ui->getSidebar($user->getUserId(), $user->getUserName(), $user->getUserRole(), $user->getUserAvatar()); ?>

    <aside class="right-side">
        <section class="content-header">
            <h1>
                <i class="fa fa-pencil"></i> Editar Agente de Voz com IA: <?=htmlspecialchars($agent['agent_name'])?>
            </h1>
            <ol class="breadcrumb">
                <li><a href="./index.php"><i class="fa fa-home"></i> Home</a></li>
                <li><a href="ai_agents.php">Agentes de IA</a></li>
                <li class="active">Editar Agente</li>
            </ol>
        </section>

        <section class="content">
            <div class="row">
                <div class="col-md-10 col-md-offset-1">
                    <div class="box box-primary">
                        <div class="box-header with-border">
                            <h3 class="box-title"><i class="fa fa-sliders"></i> Parâmetros do Agente #<?=$agent['agent_id']?></h3>
                        </div>
                        <form id="form_edit_ai_agent" method="POST" action="php/SaveAIAgent.php">
                            <input type="hidden" name="agent_id" value="<?=$agent['agent_id']?>" />
                            
                            <div class="box-body" style="padding: 25px;">
                                <div class="row">
                                    <div class="col-md-8">
                                        <div class="form-group">
                                            <label>Nome do Agente <span class="text-danger">*</span></label>
                                            <input type="text" name="agent_name" class="form-control input-lg" value="<?=htmlspecialchars($agent['agent_name'])?>" required />
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>Status</label>
                                            <select name="status" class="form-control input-lg">
                                                <option value="Y" <?=$agent['status'] == 'Y' ? 'selected' : ''?>>Ativo (Pronto para discar)</option>
                                                <option value="N" <?=$agent['status'] == 'N' ? 'selected' : ''?>>Inativo</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label>Descrição do Objetivo</label>
                                    <input type="text" name="description" class="form-control" value="<?=htmlspecialchars($agent['description'])?>" />
                                </div>

<?php
$llms = $aiHandler->getAvailableLLMs();
$voices = $aiHandler->getAvailableVoices();
?>
                                <hr/>
                                <h4><i class="fa fa-microphone"></i> Voz e Cérebro da IA</h4>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Provedor de Voz (TTS Ultrarrealista)</label>
                                            <select name="voice_provider" id="voice_provider" class="form-control">
                                                <?php foreach ($voices as $k => $prov): ?>
                                                    <option value="<?=$k?>" <?=$agent['voice_provider'] == $k ? 'selected' : ''?>><?=htmlspecialchars($prov['name'])?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Voz Selecionada</label>
                                            <div class="input-group" style="width:100%;">
                                                <select name="voice_id" id="voice_id" class="form-control">
                                                    <!-- Populated dynamically via JS -->
                                                </select>
                                            </div>
                                            <input type="hidden" name="voice_name" id="voice_name" value="<?=htmlspecialchars($agent['voice_name'])?>" />
                                        </div>

                                        <!-- ElevenLabs Quick Actions Toolbar -->
                                        <div id="elevenlabs_tools" style="display:none; margin-bottom: 12px;">
                                            <button type="button" class="btn btn-default btn-sm" id="btn_toggle_import_modal" style="margin-right: 5px;">
                                                <i class="fa fa-plus-circle text-purple"></i> <strong>Importar Voz por ID</strong>
                                            </button>
                                            <button type="button" class="btn btn-default btn-sm" id="btn_sync_eleven_voices">
                                                <i class="fa fa-refresh text-blue"></i> Sincronizar Minhas Vozes
                                            </button>
                                            <span id="sync_status" style="margin-left: 8px; font-size: 12px;"></span>
                                        </div>

                                        <!-- ElevenLabs Import Box -->
                                        <div id="elevenlabs_import_box" class="callout callout-info" style="display:none; background-color: #f4f7fb !important; border-left-color: #7c4dff !important; color: #333 !important; padding: 12px; margin-bottom: 15px;">
                                            <h5 style="margin-top: 0; color: #512da8; font-weight: bold;"><i class="fa fa-id-card"></i> Importar Voz da ElevenLabs por ID</h5>
                                            <div class="input-group">
                                                <input type="text" id="import_voice_id_input" class="form-control" placeholder="Cole o Voice ID aqui (Ex: pNInz6obpgDQGcFmaJgB)" />
                                                <span class="input-group-btn">
                                                    <button class="btn btn-primary" type="button" id="btn_do_import_voice">
                                                        <i class="fa fa-search"></i> Buscar Voz
                                                    </button>
                                                </span>
                                            </div>
                                            <div id="import_voice_preview" style="display:none; margin-top: 10px; padding: 10px; background: #fff; border: 1px solid #ddd; border-radius: 4px;">
                                                <div id="import_voice_info" style="font-weight: bold; margin-bottom: 5px;"></div>
                                                <div id="import_voice_audio_wrap" style="margin-bottom: 8px;"></div>
                                                <button type="button" class="btn btn-success btn-sm" id="btn_apply_imported_voice">
                                                    <i class="fa fa-check"></i> Selecionar Esta Voz Para o Agente
                                                </button>
                                            </div>
                                        </div>

                                        <div class="form-group" id="custom_voice_wrapper" style="display:none;">
                                            <label><i class="fa fa-id-badge text-yellow"></i> ID da Voz Clonada / Personalizada</label>
                                            <input type="text" name="custom_voice_id" id="custom_voice_id" class="form-control" value="<?=htmlspecialchars($agent['voice_id'])?>" placeholder="Cole o Voice ID da ElevenLabs / Cartesia" />
                                            <small class="text-muted">Ex: <code>pNInz6obpgDQGcFmaJgB</code> (ElevenLabs) ou ID do Cartesia.</small>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Provedor de LLM (Inteligência Artificial)</label>
                                            <select name="llm_provider" id="llm_provider" class="form-control">
                                                <?php foreach ($llms as $k => $prov): ?>
                                                    <option value="<?=$k?>" <?=$agent['llm_provider'] == $k ? 'selected' : ''?>><?=htmlspecialchars($prov['name'])?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Modelo LLM</label>
                                            <select name="llm_model" id="llm_model" class="form-control">
                                                <!-- Populated dynamically via JS -->
                                            </select>
                                        </div>
                                        <div class="form-group" id="custom_model_wrapper" style="display:none;">
                                            <label><i class="fa fa-cogs text-yellow"></i> ID do Modelo Personalizado</label>
                                            <input type="text" name="custom_llm_model" id="custom_llm_model" class="form-control" value="<?=htmlspecialchars($agent['llm_model'])?>" placeholder="Ex: gpt-4o-2024-11-20 ou llama-3.3-70b" />
                                        </div>
                                    </div>
                                </div>

                                <hr/>
                                <h4><i class="fa fa-commenting"></i> Roteiro e Comportamento</h4>

                                <div class="form-group">
                                    <label>Saudação Inicial (Primeira fala da IA ao atender)</label>
                                    <textarea name="greeting_message" class="form-control" rows="2"><?=htmlspecialchars($agent['greeting_message'])?></textarea>
                                </div>

                                <div class="form-group">
                                    <label>Prompt do Sistema / Instruções da IA (System Prompt)</label>
                                    <textarea name="system_prompt" class="form-control" rows="8" style="font-family: monospace; font-size: 13px;" required><?=htmlspecialchars($agent['system_prompt'])?></textarea>
                                </div>

                                <hr/>
                                <h4><i class="fa fa-exchange"></i> Ações & Live Transfer</h4>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Ramal / Fila de Transferência Humana</label>
                                            <input type="text" name="transfer_phone_or_queue" class="form-control" value="<?=htmlspecialchars($agent['transfer_phone_or_queue'])?>" />
                                            <small class="text-muted">Ramal ou Ingroup do GOautodial para onde a chamada será transferida quando o cliente tiver interesse.</small>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Desligar em Caixa Postal / Secretária</label>
                                            <select name="hangup_on_voicemail" class="form-control">
                                                <option value="Y" <?=$agent['hangup_on_voicemail'] == 'Y' ? 'selected' : ''?>>Sim (Desliga imediatamente sem gastar tokens)</option>
                                                <option value="N" <?=$agent['hangup_on_voicemail'] == 'N' ? 'selected' : ''?>>Não</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="box-footer" style="padding: 20px;">
                                <button type="submit" class="btn btn-success btn-lg"><i class="fa fa-save"></i> Atualizar Agente de IA</button>
                                <a href="ai_agents.php" class="btn btn-default btn-lg pull-right">Voltar</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </section>
    </aside>
</div>

<?php print $ui->standardizedThemeJS(); ?>
<script type="text/javascript">
var llmCatalog = <?=json_encode($llms)?>;
var voiceCatalog = <?=json_encode($voices)?>;
var currentVoiceId = <?=json_encode($agent['voice_id'])?>;
var currentLlmModel = <?=json_encode($agent['llm_model'])?>;

function updateLLMModels(selectedProvider, preselectModel) {
    var $modelSelect = $('#llm_model');
    $modelSelect.empty();
    
    var found = false;
    if (llmCatalog[selectedProvider] && llmCatalog[selectedProvider].models) {
        var models = llmCatalog[selectedProvider].models;
        $.each(models, function(val, text) {
            var selected = (preselectModel && preselectModel === val);
            if (selected) found = true;
            $modelSelect.append($('<option>', {
                value: val,
                text: text,
                selected: selected
            }));
        });
    }
    
    // If current model is not in standard list, select custom
    if (!found && preselectModel) {
        $modelSelect.val('custom');
        $('#custom_llm_model').val(preselectModel);
    }
    toggleCustomModel();
}

function updateVoices(selectedProvider, preselectVoice) {
    var $voiceSelect = $('#voice_id');
    $voiceSelect.empty();
    
    var found = false;
    if (voiceCatalog[selectedProvider] && voiceCatalog[selectedProvider].voices) {
        var voices = voiceCatalog[selectedProvider].voices;
        $.each(voices, function(val, text) {
            var selected = (preselectVoice && preselectVoice === val);
            if (selected) found = true;
            $voiceSelect.append($('<option>', {
                value: val,
                text: text,
                selected: selected,
                'data-name': text
            }));
        });
    }

    if (selectedProvider === 'elevenlabs') {
        $('#elevenlabs_tools').show();
    } else {
        $('#elevenlabs_tools').hide();
        $('#elevenlabs_import_box').hide();
    }

    // If current voice is not in standard list, select custom or insert it
    if (!found && preselectVoice) {
        $voiceSelect.val('custom');
        $('#custom_voice_id').val(preselectVoice);
    }

    updateVoiceName();
    toggleCustomVoice();
}

function updateVoiceName() {
    var selected = $('#voice_id option:selected');
    if ($('#voice_id').val() === 'custom') {
        var customId = $('#custom_voice_id').val();
        $('#voice_name').val('Voz Customizada (' + (customId || 'ID') + ')');
    } else {
        $('#voice_name').val(selected.data('name') || selected.text());
    }
}

function toggleCustomModel() {
    if ($('#llm_model').val() === 'custom') {
        $('#custom_model_wrapper').show();
        $('#custom_llm_model').attr('required', true);
    } else {
        $('#custom_model_wrapper').hide();
        $('#custom_llm_model').removeAttr('required');
    }
}

function toggleCustomVoice() {
    if ($('#voice_id').val() === 'custom') {
        $('#custom_voice_wrapper').show();
        $('#custom_voice_id').attr('required', true);
    } else {
        $('#custom_voice_wrapper').hide();
        $('#custom_voice_id').removeAttr('required');
    }
}

var lastImportedVoice = null;

$(document).ready(function() {
    // Initial populate with saved data
    updateVoices($('#voice_provider').val(), currentVoiceId);
    updateLLMModels($('#llm_provider').val(), currentLlmModel);

    $('#voice_provider').change(function() {
        updateVoices($(this).val());
    });

    $('#voice_id').change(function() {
        updateVoiceName();
        toggleCustomVoice();
    });

    $('#custom_voice_id').on('input', function() {
        updateVoiceName();
    });

    $('#llm_provider').change(function() {
        updateLLMModels($(this).val());
    });

    $('#llm_model').change(function() {
        toggleCustomModel();
    });

    // ElevenLabs Toggle Import Box
    $('#btn_toggle_import_modal').click(function() {
        $('#elevenlabs_import_box').slideToggle();
        $('#import_voice_id_input').focus();
    });

    // ElevenLabs Fetch Voice by ID
    $('#btn_do_import_voice').click(function() {
        var vId = $.trim($('#import_voice_id_input').val());
        if (!vId) {
            alert('Por favor, cole o Voice ID da ElevenLabs.');
            return;
        }

        var $btn = $(this);
        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Buscando...');
        $('#import_voice_preview').hide();

        $.post('php/FetchElevenLabsVoice.php', { action: 'fetch_one', voice_id: vId }, function(res) {
            $btn.prop('disabled', false).html('<i class="fa fa-search"></i> Buscar Voz');
            if (res.status === 1 && res.voice) {
                lastImportedVoice = res.voice;
                $('#import_voice_info').html('<span class="text-success"><i class="fa fa-check-circle"></i> ' + res.voice.name + '</span> <small class="text-muted">(ID: ' + res.voice.voice_id + ')</small>');
                
                if (res.voice.preview_url) {
                    $('#import_voice_audio_wrap').html('<audio controls style="width:100%; height:32px;"><source src="' + res.voice.preview_url + '" type="audio/mpeg">Seu navegador não suporta áudio.</audio>');
                } else {
                    $('#import_voice_audio_wrap').html('<small class="text-muted">Sem áudio de prévia disponível na API.</small>');
                }

                $('#import_voice_preview').slideDown();
            } else {
                alert(res.message || 'Erro ao importar voz da ElevenLabs.');
            }
        }, 'json').fail(function() {
            $btn.prop('disabled', false).html('<i class="fa fa-search"></i> Buscar Voz');
            alert('Erro de comunicação ao importar voz. Verifique sua chave da ElevenLabs.');
        });
    });

    // Apply Imported Voice
    $('#btn_apply_imported_voice').click(function() {
        if (!lastImportedVoice) return;
        
        var vId = lastImportedVoice.voice_id;
        var vName = lastImportedVoice.name;

        // Check if option already exists
        var exists = $('#voice_id option[value="' + vId + '"]').length > 0;
        if (!exists) {
            $('#voice_id').prepend($('<option>', {
                value: vId,
                text: vName,
                'data-name': vName
            }));
        }

        $('#voice_id').val(vId);
        $('#voice_name').val(vName);
        $('#elevenlabs_import_box').slideUp();
        toggleCustomVoice();
        alert('Voz "' + lastImportedVoice.raw_name + '" selecionada com sucesso!');
    });

    // ElevenLabs Sync All Account Voices
    $('#btn_sync_eleven_voices').click(function() {
        var $btn = $(this);
        var $status = $('#sync_status');
        $btn.prop('disabled', true);
        $status.html('<i class="fa fa-spinner fa-spin text-blue"></i> Sincronizando vozes...');

        $.post('php/FetchElevenLabsVoice.php', { action: 'fetch_all' }, function(res) {
            $btn.prop('disabled', false);
            if (res.status === 1 && res.voices) {
                var $voiceSelect = $('#voice_id');
                $voiceSelect.empty();

                $.each(res.voices, function(i, v) {
                    $voiceSelect.append($('<option>', {
                        value: v.voice_id,
                        text: v.name,
                        'data-name': v.name
                    }));
                });

                $voiceSelect.append($('<option>', {
                    value: 'custom',
                    text: 'Voz Clonada / Personalizada (Digitar ID)',
                    'data-name': 'Voz Customizada'
                }));

                updateVoiceName();
                $status.html('<span class="text-green"><i class="fa fa-check"></i> ' + res.count + ' vozes sincronizadas!</span>');
            } else {
                $status.html('<span class="text-danger"><i class="fa fa-times"></i> ' + (res.message || 'Erro ao sincronizar.') + '</span>');
            }
        }, 'json').fail(function() {
            $btn.prop('disabled', false);
            $status.html('<span class="text-danger"><i class="fa fa-times"></i> Erro de conexão com ElevenLabs.</span>');
        });
    });

    $('#form_edit_ai_agent').submit(function(e) {
        e.preventDefault();
        var formData = $(this).serialize();
        $.post('php/SaveAIAgent.php', formData, function(res) {
            if (res.status == 1) {
                alert(res.message);
                window.location.href = 'ai_agents.php';
            } else {
                alert(res.message || 'Erro ao atualizar agente.');
            }
        }, 'json').fail(function() {
            alert('Erro de conexão com o servidor.');
        });
    });
});
</script>
</body>
</html>
