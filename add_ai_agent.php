<?php
/**
 * @file        add_ai_agent.php
 * @brief       Vapi-Style Voice AI Agent Creator
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

if ($user && $user->getUserRole() == CRM_DEFAULTS_USER_ROLE_AGENT) {
    header("location: agent.php");
    exit();
}

$llms = $aiHandler->getAvailableLLMs();
$voices = $aiHandler->getAvailableVoices();
$stts = $aiHandler->getAvailableSTTs();
$defaultTools = $aiHandler->getDefaultTools();

$defaultPrompt = "# PERSONA E OBJETIVO\n" .
"Você é Júlia, assistente virtual inteligente da empresa.\n" .
"Seu objetivo é entender o motivo do contato do cliente, tirar dúvidas com cordialidade e qualificar o atendimento.\n\n" .
"# DIRETRIZES DA CONVERSA\n" .
"1. Seja direta, natural e fale no máximo 2 frases por turno para manter o diálogo ágil.\n" .
"2. Se o cliente solicitar encerramento ou agradecer, encerre educadamente usando a ferramenta end_call.\n" .
"3. Se o cliente pedir para falar com um humano, transfira usando a ferramenta transfer_call.";
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Dial GO - Criar Agente de Voz com IA (Vapi Mode)</title>
    <meta content='width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no' name='viewport'>

    <?php 
        print $ui->standardizedThemeCSS(); 
        print $ui->creamyThemeCSS();
    ?>
    <link href="css/style.css" rel="stylesheet" type="text/css" />
    
    <style>
        .vapi-container {
            background-color: #0d0f12;
            color: #f1f5f9;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            min-height: calc(100vh - 50px);
            padding: 24px 32px;
        }
        .vapi-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-bottom: 20px;
            border-bottom: 1px solid #1e222b;
            margin-bottom: 24px;
        }
        .vapi-title-area {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .vapi-agent-icon {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            background: #1e293b;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #10b981;
            font-size: 18px;
            border: 1px solid #334155;
        }
        .vapi-agent-name-input {
            background: #14171f;
            border: 1px solid #334155;
            color: #ffffff;
            font-size: 18px;
            font-weight: 700;
            padding: 6px 12px;
            border-radius: 6px;
            width: 340px;
        }
        .vapi-agent-name-input:focus {
            border-color: #10b981;
            outline: none;
        }
        .vapi-badge {
            background: #1e293b;
            color: #94a3b8;
            font-size: 11px;
            font-weight: 600;
            padding: 3px 8px;
            border-radius: 4px;
            border: 1px solid #334155;
        }
        .vapi-badge-new {
            background: rgba(56, 189, 248, 0.15);
            color: #38bdf8;
            border-color: rgba(56, 189, 248, 0.3);
        }
        .vapi-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .vapi-btn {
            background: #1e222b;
            color: #f1f5f9;
            border: 1px solid #2e3545;
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }
        .vapi-btn:hover {
            background: #282e3c;
            border-color: #475569;
            color: #fff;
        }
        .vapi-btn-primary {
            background: #10b981;
            color: #000;
            border-color: #10b981;
        }
        .vapi-btn-primary:hover {
            background: #059669;
            color: #fff;
        }
        .vapi-tabs {
            display: flex;
            gap: 24px;
            border-bottom: 1px solid #1e222b;
            margin-bottom: 24px;
        }
        .vapi-tab {
            padding: 10px 4px;
            color: #94a3b8;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            transition: all 0.2s;
        }
        .vapi-tab:hover { color: #f1f5f9; }
        .vapi-tab.active { color: #10b981; border-bottom-color: #10b981; }

        .vapi-telemetry {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-bottom: 20px;
        }
        .vapi-telemetry-item {
            background: #14171f;
            border: 1px solid #1e2430;
            border-radius: 10px;
            padding: 16px 20px;
        }
        .vapi-telemetry-header {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            margin-bottom: 10px;
        }
        .vapi-telemetry-label {
            font-size: 12px;
            color: #94a3b8;
            text-transform: uppercase;
            font-weight: 700;
            letter-spacing: 0.5px;
        }
        .vapi-telemetry-value {
            font-size: 22px;
            font-weight: 800;
            color: #f8fafc;
        }
        .vapi-progress-bar {
            height: 6px;
            background: #272d3b;
            border-radius: 3px;
            overflow: hidden;
            display: flex;
        }
        .vapi-seg-stt { background: #10b981; }
        .vapi-seg-llm { background: #f59e0b; }
        .vapi-seg-tts { background: #ec4899; }

        .vapi-presets-bar {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }
        .vapi-preset-label {
            font-size: 12px;
            color: #64748b;
            font-weight: 600;
            margin-right: 8px;
        }
        .vapi-preset-pill {
            background: #14171f;
            border: 1px solid #222834;
            color: #cbd5e1;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        .vapi-preset-pill:hover { border-color: #475569; color: #fff; }
        .vapi-preset-pill.active { background: #1e293b; border-color: #38bdf8; color: #38bdf8; }

        .vapi-cards-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 24px;
        }
        .vapi-card {
            background: #14171f;
            border: 1px solid #202634;
            border-radius: 12px;
            padding: 20px;
            position: relative;
            cursor: pointer;
            transition: all 0.2s;
        }
        .vapi-card:hover {
            border-color: #3b82f6;
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.4);
        }
        .vapi-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }
        .vapi-card-type {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .vapi-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; }
        .vapi-dot-stt { background: #10b981; box-shadow: 0 0 8px #10b981; }
        .vapi-dot-llm { background: #3b82f6; box-shadow: 0 0 8px #3b82f6; }
        .vapi-dot-tts { background: #ec4899; box-shadow: 0 0 8px #ec4899; }
        
        .vapi-card-title { font-size: 16px; font-weight: 700; color: #ffffff; margin-bottom: 4px; }
        .vapi-card-provider { font-size: 12px; color: #94a3b8; margin-bottom: 16px; display: flex; align-items: center; gap: 6px; }
        .vapi-card-stats {
            display: flex;
            justify-content: space-between;
            border-top: 1px solid #1e2430;
            padding-top: 12px;
            font-size: 11px;
        }
        .vapi-card-stat-label { color: #64748b; margin-bottom: 2px; }
        .vapi-card-stat-val { color: #f1f5f9; font-weight: 700; }

        .vapi-section-box {
            background: #14171f;
            border: 1px solid #202634;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
        }
        .vapi-section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 14px;
        }
        .vapi-section-title { font-size: 14px; font-weight: 700; color: #ffffff; display: flex; align-items: center; gap: 8px; }
        .vapi-select-dark {
            background: #1b202a;
            border: 1px solid #2d3545;
            color: #f1f5f9;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
        }
        .vapi-textarea-dark {
            width: 100%;
            background: #0d0f14;
            border: 1px solid #222834;
            border-radius: 8px;
            color: #f1f5f9;
            padding: 14px;
            font-size: 13px;
            line-height: 1.6;
            resize: vertical;
            box-sizing: border-box;
        }
        .vapi-prompt-editor {
            font-family: 'Fira Code', 'Consolas', 'Courier New', monospace;
            font-size: 13px;
            line-height: 1.5;
            background: #090a0d;
        }
        .vapi-modal-backdrop {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.75);
            z-index: 9999;
            display: none;
            justify-content: flex-end;
        }
        .vapi-drawer {
            background: #11141a;
            width: 440px;
            max-width: 100%;
            height: 100%;
            overflow-y: auto;
            border-left: 1px solid #202734;
            padding: 28px;
            box-sizing: border-box;
            box-shadow: -10px 0 30px rgba(0,0,0,0.8);
        }
        .vapi-form-group { margin-bottom: 20px; }
        .vapi-form-label { display: block; font-size: 12px; font-weight: 700; color: #cbd5e1; margin-bottom: 6px; }
        .vapi-input-dark {
            width: 100%;
            background: #181d26;
            border: 1px solid #283142;
            color: #f1f5f9;
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 13px;
            box-sizing: border-box;
        }
        .vapi-switch-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #1c222e;
        }
        .vapi-switch {
            position: relative;
            display: inline-block;
            width: 44px;
            height: 24px;
        }
        .vapi-switch input { opacity: 0; width: 0; height: 0; }
        .vapi-slider {
            position: absolute; cursor: pointer;
            top: 0; left: 0; right: 0; bottom: 0;
            background-color: #272d3b;
            transition: .3s;
            border-radius: 24px;
        }
        .vapi-slider:before {
            position: absolute; content: "";
            height: 18px; width: 18px;
            left: 3px; bottom: 3px;
            background-color: white;
            transition: .3s;
            border-radius: 50%;
        }
        input:checked + .vapi-slider { background-color: #10b981; }
        input:checked + .vapi-slider:before { transform: translateX(20px); }
        .vapi-range-slider { width: 100%; accent-color: #10b981; height: 6px; background: #272d3b; border-radius: 3px; }
        .vapi-pill-group { display: flex; background: #14171f; border: 1px solid #222834; border-radius: 8px; padding: 3px; gap: 4px; }
        .vapi-pill-opt { flex: 1; text-align: center; padding: 6px 10px; font-size: 12px; font-weight: 600; border-radius: 6px; cursor: pointer; color: #94a3b8; }
        .vapi-pill-opt.active { background: #222b3a; color: #38bdf8; }
    </style>
</head>

<?php print $ui->creamyBody(); ?>
<div class="wrapper">
    <?php print $ui->creamyHeader($user); ?>
    <?php print $ui->getSidebar($user->getUserId(), $user->getUserName(), $user->getUserRole(), $user->getUserAvatar()); ?>

    <aside class="right-side" style="margin-left: 220px; background-color: #0d0f12;">
        <form id="form_add_vapi_agent" method="POST" action="php/SaveAIAgent.php">
            <input type="hidden" name="agent_id" value="0" />
            <input type="hidden" name="model_preset" id="input_model_preset" value="balanced" />
            <input type="hidden" name="first_message_mode" id="input_first_message_mode" value="assistant_speaks_first" />
            
            <input type="hidden" name="stt_provider" id="input_stt_provider" value="deepgram" />
            <input type="hidden" name="stt_model" id="input_stt_model" value="nova-2" />
            <input type="hidden" name="stt_language" id="input_stt_language" value="pt-BR" />
            <input type="hidden" name="intelligent_turn_taking" id="input_intelligent_turn_taking" value="Y" />
            <input type="hidden" name="background_denoising" id="input_background_denoising" value="Y" />
            <input type="hidden" name="silence_timeout_ms" id="input_silence_timeout_ms" value="500" />
            
            <input type="hidden" name="llm_provider" id="input_llm_provider" value="groq" />
            <input type="hidden" name="llm_model" id="input_llm_model" value="llama-3.3-70b-versatile" />
            <input type="hidden" name="temperature" id="input_temperature" value="0.7" />
            <input type="hidden" name="max_tokens" id="input_max_tokens" value="250" />
            <input type="hidden" name="prompt_cache_retention" id="input_prompt_cache_retention" value="in_memory" />
            <input type="hidden" name="tool_strict_compatibility" id="input_tool_strict_compatibility" value="N" />

            <input type="hidden" name="voice_provider" id="input_voice_provider" value="cartesia" />
            <input type="hidden" name="voice_id" id="input_voice_id" value="cartesia-pt-br-sofia" />
            <input type="hidden" name="voice_name" id="input_voice_name" value="Sofia (Cartesia Português BR)" />
            <input type="hidden" name="voice_speed" id="input_voice_speed" value="1.00" />
            <input type="hidden" name="voice_stability" id="input_voice_stability" value="0.70" />
            <input type="hidden" name="voice_clarity" id="input_voice_clarity" value="0.60" />
            <input type="hidden" name="voice_style_exaggeration" id="input_voice_style_exaggeration" value="0.20" />
            <input type="hidden" name="voice_optimize_latency" id="input_voice_optimize_latency" value="1" />
            <input type="hidden" name="background_sound" id="input_background_sound" value="off" />

            <input type="hidden" name="tools_json" id="input_tools_json" value="<?=htmlspecialchars(json_encode($defaultTools))?>" />
            <input type="hidden" name="status" value="Y" />
            <input type="hidden" name="transfer_phone_or_queue" value="8300" />

            <div class="vapi-container">
                <div class="vapi-header">
                    <div class="vapi-title-area">
                        <div class="vapi-agent-icon"><i class="fa fa-magic"></i></div>
                        <div>
                            <input type="text" name="agent_name" class="vapi-agent-name-input" placeholder="Nome do Agente (Ex: Sofia SDR)" required />
                            <div style="display: flex; gap: 8px; align-items: center; margin-top: 4px; padding-left: 4px;">
                                <span class="vapi-badge vapi-badge-new"><i class="fa fa-plus"></i> Novo Agente</span>
                                <span class="vapi-badge">v1.0</span>
                            </div>
                        </div>
                    </div>
                    <div class="vapi-actions">
                        <a href="ai_agents.php" class="vapi-btn"><i class="fa fa-arrow-left"></i> Cancelar</a>
                        <button type="submit" class="vapi-btn vapi-btn-primary" id="btn_create_agent"><i class="fa fa-check"></i> Criar & Publicar Agente</button>
                    </div>
                </div>

                <!-- Telemetry -->
                <div class="vapi-telemetry">
                    <div class="vapi-telemetry-item">
                        <div class="vapi-telemetry-header">
                            <span class="vapi-telemetry-label">Custo Estimado</span>
                            <span class="vapi-telemetry-value" id="disp_total_cost">~$0.038<span style="font-size:14px; color:#94a3b8;">/min</span></span>
                        </div>
                        <div class="vapi-progress-bar">
                            <div class="vapi-seg-stt" style="width: 25%;"></div>
                            <div class="vapi-seg-llm" style="width: 35%;"></div>
                            <div class="vapi-seg-tts" style="width: 40%;"></div>
                        </div>
                    </div>

                    <div class="vapi-telemetry-item">
                        <div class="vapi-telemetry-header">
                            <span class="vapi-telemetry-label">Latência Estimada</span>
                            <span class="vapi-telemetry-value" id="disp_total_latency">~460<span style="font-size:14px; color:#94a3b8;">ms</span></span>
                        </div>
                        <div class="vapi-progress-bar">
                            <div class="vapi-seg-stt" style="width: 26%;"></div>
                            <div class="vapi-seg-llm" style="width: 39%;"></div>
                            <div class="vapi-seg-tts" style="width: 35%;"></div>
                        </div>
                    </div>
                </div>

                <!-- Presets -->
                <div class="vapi-presets-bar">
                    <span class="vapi-preset-label">Model Presets:</span>
                    <div class="vapi-preset-pill active" data-preset="balanced">Balanced</div>
                    <div class="vapi-preset-pill" data-preset="high_intelligence">High Intelligence</div>
                    <div class="vapi-preset-pill" data-preset="ultra_fast">Ultra Fast (<350ms)</div>
                    <div class="vapi-preset-pill" data-preset="cost_saver">Cost Saver</div>
                    <div class="vapi-preset-pill" data-preset="customized">Customized</div>
                </div>

                <!-- 3 Cards -->
                <div class="vapi-cards-grid">
                    <div class="vapi-card" id="card_transcriber">
                        <div class="vapi-card-header">
                            <span class="vapi-card-type"><span class="vapi-dot vapi-dot-stt"></span> TRANSCRIBER</span>
                            <button type="button" class="btn btn-link text-muted"><i class="fa fa-pencil"></i></button>
                        </div>
                        <div class="vapi-card-title">Deepgram Nova-2</div>
                        <div class="vapi-card-provider"><i class="fa fa-globe text-green"></i> Deepgram • Brazilian Portuguese</div>
                        <div class="vapi-card-stats">
                            <div><div class="vapi-card-stat-label">Latency</div><div class="vapi-card-stat-val">120ms</div></div>
                            <div><div class="vapi-card-stat-label">Cost</div><div class="vapi-card-stat-val">$0.005/min</div></div>
                            <div><div class="vapi-card-stat-label">Accuracy</div><div class="vapi-card-stat-val">98.4%</div></div>
                        </div>
                    </div>

                    <div class="vapi-card" id="card_model">
                        <div class="vapi-card-header">
                            <span class="vapi-card-type"><span class="vapi-dot vapi-dot-llm"></span> MODEL</span>
                            <button type="button" class="btn btn-link text-muted"><i class="fa fa-pencil"></i></button>
                        </div>
                        <div class="vapi-card-title">Llama 3.3 70B</div>
                        <div class="vapi-card-provider"><i class="fa fa-bolt text-blue"></i> Groq • Versatile</div>
                        <div class="vapi-card-stats">
                            <div><div class="vapi-card-stat-label">Latency</div><div class="vapi-card-stat-val">180ms</div></div>
                            <div><div class="vapi-card-stat-label">Cost</div><div class="vapi-card-stat-val">$0.008/min</div></div>
                            <div><div class="vapi-card-stat-label">Intelligence</div><div class="vapi-card-stat-val">92</div></div>
                        </div>
                    </div>

                    <div class="vapi-card" id="card_voice">
                        <div class="vapi-card-header">
                            <span class="vapi-card-type"><span class="vapi-dot vapi-dot-tts"></span> VOICE</span>
                            <button type="button" class="btn btn-link text-muted"><i class="fa fa-pencil"></i></button>
                        </div>
                        <div class="vapi-card-title">Sofia (Natural PT-BR)</div>
                        <div class="vapi-card-provider"><i class="fa fa-volume-up text-purple"></i> Cartesia • Sonic Multilingual</div>
                        <div class="vapi-card-stats">
                            <div><div class="vapi-card-stat-label">Latency</div><div class="vapi-card-stat-val">90ms</div></div>
                            <div><div class="vapi-card-stat-label">Cost</div><div class="vapi-card-stat-val">$0.020/min</div></div>
                            <div><div class="vapi-card-stat-label">Humanness</div><div class="vapi-card-stat-val">96</div></div>
                        </div>
                    </div>
                </div>

                <!-- First Message -->
                <div class="vapi-section-box">
                    <div class="vapi-section-header">
                        <span class="vapi-section-title"><i class="fa fa-commenting-o text-green"></i> First Message</span>
                        <select id="select_first_message_mode" class="vapi-select-dark">
                            <option value="assistant_speaks_first">Assistant speaks first (A IA fala primeiro)</option>
                            <option value="user_speaks_first">User speaks first (A IA aguarda o cliente falar)</option>
                        </select>
                    </div>
                    <textarea name="greeting_message" class="vapi-textarea-dark" rows="3" placeholder="Olá! Eu sou a Sofia da DDM. Como posso te ajudar hoje?">Olá, tudo bem? Falo com o titular da linha?</textarea>
                </div>

                <!-- System Prompt -->
                <div class="vapi-section-box">
                    <div class="vapi-section-header">
                        <span class="vapi-section-title"><i class="fa fa-code text-blue"></i> System Prompt & Instructions</span>
                    </div>
                    <textarea name="system_prompt" id="textarea_prompt" class="vapi-textarea-dark vapi-prompt-editor" rows="12" required><?=htmlspecialchars($defaultPrompt)?></textarea>
                </div>
            </div>
        </form>
    </aside>
</div>

<?php print $ui->standardizedThemeJS(); ?>
<script type="text/javascript">
$(document).ready(function() {
    $('#select_first_message_mode').change(function() {
        $('#input_first_message_mode').val($(this).val());
    });

    $('#form_add_vapi_agent').submit(function(e) {
        e.preventDefault();
        var $btn = $('#btn_create_agent');
        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Criando Agente...');

        var formData = $(this).serialize();
        $.post('php/SaveAIAgent.php', formData, function(res) {
            $btn.prop('disabled', false).html('<i class="fa fa-check"></i> Criar & Publicar Agente');
            if (res.status == 1) {
                alert("✨ Agente de IA criado e publicado com sucesso!");
                window.location.href = 'edit_ai_agent.php?id=' + (res.agent_id || 1);
            } else {
                alert(res.message || 'Erro ao criar agente.');
            }
        }, 'json').fail(function() {
            $btn.prop('disabled', false).html('<i class="fa fa-check"></i> Criar & Publicar Agente');
            alert('Erro de comunicação com o servidor.');
        });
    });
});
</script>
</body>
</html>
