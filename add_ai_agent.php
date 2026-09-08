<?php
/**
 * @file        add_ai_agent.php
 * @brief       Add AI Agent
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

$llms = $aiHandler->getAvailableLLMs();
$voices = $aiHandler->getAvailableVoices();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Dial GO - Criar Agente de IA</title>
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
                <i class="fa fa-magic"></i> Criar Novo Agente de Voz com IA
                <small>Configure o comportamento, a voz e as regras da sua inteligência artificial</small>
            </h1>
            <ol class="breadcrumb">
                <li><a href="./index.php"><i class="fa fa-home"></i> Home</a></li>
                <li><a href="ai_agents.php">Agentes de IA</a></li>
                <li class="active">Novo Agente</li>
            </ol>
        </section>

        <section class="content">
            <div class="row">
                <div class="col-md-10 col-md-offset-1">
                    <div class="box box-primary">
                        <div class="box-header with-border">
                            <h3 class="box-title"><i class="fa fa-sliders"></i> Parâmetros do Agente de IA</h3>
                        </div>
                        <form id="form_ai_agent" method="POST" action="php/SaveAIAgent.php">
                            <div class="box-body" style="padding: 25px;">
                                <div class="row">
                                    <div class="col-md-8">
                                        <div class="form-group">
                                            <label>Nome do Agente <span class="text-danger">*</span></label>
                                            <input type="text" name="agent_name" class="form-control input-lg" placeholder="Ex: Sofia - SDR Qualificação" required />
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>Status</label>
                                            <select name="status" class="form-control input-lg">
                                                <option value="Y">Ativo (Pronto para discar)</option>
                                                <option value="N">Inativo</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label>Descrição do Objetivo</label>
                                    <input type="text" name="description" class="form-control" placeholder="Ex: Qualificar leads de consignado e transferir para a equipe de fechamento" />
                                </div>

                                <hr/>
                                <h4><i class="fa fa-microphone"></i> Voz e Cérebro da IA</h4>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Provedor de Voz (TTS Ultrarrealista)</label>
                                            <select name="voice_provider" id="voice_provider" class="form-control">
                                                <?php foreach ($voices as $k => $prov): ?>
                                                    <option value="<?=$k?>" <?=$k == 'cartesia' ? 'selected' : ''?>><?=htmlspecialchars($prov['name'])?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Voz Selecionada</label>
                                            <select name="voice_id" id="voice_id" class="form-control">
                                                <!-- Populated dynamically via JS -->
                                            </select>
                                            <input type="hidden" name="voice_name" id="voice_name" value="" />
                                        </div>
                                        <div class="form-group" id="custom_voice_wrapper" style="display:none;">
                                            <label><i class="fa fa-id-badge text-yellow"></i> ID da Voz Clonada / Personalizada</label>
                                            <input type="text" name="custom_voice_id" id="custom_voice_id" class="form-control" placeholder="Cole o Voice ID da ElevenLabs / Cartesia" />
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
                                                    <option value="<?=$k?>" <?=$k == 'groq' ? 'selected' : ''?>><?=htmlspecialchars($prov['name'])?></option>
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
                                            <input type="text" name="custom_llm_model" id="custom_llm_model" class="form-control" placeholder="Ex: gpt-4o-2024-11-20 ou llama-3.3-70b" />
                                        </div>
                                    </div>
                                </div>

                                <hr/>
                                <h4><i class="fa fa-commenting"></i> Roteiro e Comportamento</h4>

                                <div class="form-group">
                                    <label>Saudação Inicial (Primeira fala da IA ao atender)</label>
                                    <textarea name="greeting_message" class="form-control" rows="2" placeholder="Ex: Olá, tudo bem? Falo com o titular da linha?">Olá, tudo bem? Falo com o titular da linha?</textarea>
                                    <small class="text-muted">Dica: Mantenha curto (1 frase) para capturar a atenção imediata do cliente.</small>
                                </div>

                                <div class="form-group">
                                    <label>Prompt do Sistema / Instruções da IA (System Prompt)</label>
                                    <textarea name="system_prompt" class="form-control" rows="8" style="font-family: monospace; font-size: 13px;" required>Você é a Sofia, uma especialista de atendimento e vendas cordial, ágil e persuasiva.
Seu objetivo é qualificar o cliente com clareza, empatia e sem enrolação.

Diretrizes da conversa:
1. Seja direta, amigável e use um tom de voz conversacional e humano.
2. Responda em no máximo 2 frases por turno para manter a conversa dinâmica.
3. Se o cliente tiver interesse ou pedir para falar com um humano, transfira a ligação imediatamente confirmando antes.
4. Se o cliente disser que não tem interesse, agradeça educadamente e encerre a ligação.</textarea>
                                </div>

                                <hr/>
                                <h4><i class="fa fa-exchange"></i> Ações & Live Transfer</h4>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Ramal / Fila de Transferência Humana</label>
                                            <input type="text" name="transfer_phone_or_queue" class="form-control" placeholder="Ex: 8300 ou 101" value="8300" />
                                            <small class="text-muted">Ramal ou Ingroup do GOautodial para onde a chamada será transferida quando o cliente tiver interesse.</small>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Desligar em Caixa Postal / Secretária</label>
                                            <select name="hangup_on_voicemail" class="form-control">
                                                <option value="Y" selected>Sim (Desliga imediatamente sem gastar tokens)</option>
                                                <option value="N">Não</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="box-footer" style="padding: 20px;">
                                <button type="submit" class="btn btn-success btn-lg"><i class="fa fa-check"></i> Salvar Agente de IA</button>
                                <a href="ai_agents.php" class="btn btn-default btn-lg pull-right">Cancelar</a>
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

function updateLLMModels(selectedProvider, preselectModel) {
    var $modelSelect = $('#llm_model');
    $modelSelect.empty();
    
    if (llmCatalog[selectedProvider] && llmCatalog[selectedProvider].models) {
        var models = llmCatalog[selectedProvider].models;
        var first = true;
        $.each(models, function(val, text) {
            var selected = (preselectModel && preselectModel === val) || (!preselectModel && first);
            $modelSelect.append($('<option>', {
                value: val,
                text: text,
                selected: selected
            }));
            first = false;
        });
    }
    toggleCustomModel();
}

function updateVoices(selectedProvider, preselectVoice) {
    var $voiceSelect = $('#voice_id');
    $voiceSelect.empty();
    
    if (voiceCatalog[selectedProvider] && voiceCatalog[selectedProvider].voices) {
        var voices = voiceCatalog[selectedProvider].voices;
        var first = true;
        $.each(voices, function(val, text) {
            var selected = (preselectVoice && preselectVoice === val) || (!preselectVoice && first);
            $voiceSelect.append($('<option>', {
                value: val,
                text: text,
                selected: selected,
                'data-name': text
            }));
            first = false;
        });
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

$(document).ready(function() {
    // Initial populate
    updateVoices($('#voice_provider').val(), 'cartesia-pt-br-sofia');
    updateLLMModels($('#llm_provider').val(), 'llama-3.3-70b-versatile');

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

    $('#form_ai_agent').submit(function(e) {
        e.preventDefault();
        
        // Handle custom voice/model overrides
        if ($('#voice_id').val() === 'custom' && $('#custom_voice_id').val()) {
            // we send custom_voice_id
        }
        
        var formData = $(this).serialize();
        $.post('php/SaveAIAgent.php', formData, function(res) {
            if (res.status == 1) {
                alert(res.message);
                window.location.href = 'ai_agents.php';
            } else {
                alert(res.message || 'Erro ao salvar agente.');
            }
        }, 'json').fail(function() {
            alert('Erro de conexão com o servidor.');
        });
    });
});
</script>
</body>
</html>
