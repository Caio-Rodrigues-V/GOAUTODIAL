<?php
/**
 * @file        add_ai_agent.php
 * @brief       Dialog DDM - Create New Voice AI Agent (Design System 2.0)
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

$defaultTools = $aiHandler->getDefaultTools();

$defaultPrompt = "# PERSONA E OBJETIVO\n" .
"Você é Sofia, consultora virtual inteligente da {{empresa}}.\n" .
"Seu objetivo é entender o motivo do contato do cliente {{customer_name}}, tirar dúvidas com cordialidade e qualificar o atendimento.\n\n" .
"# DIRETRIZES DA CONVERSA\n" .
"1. Seja direta, natural e fale no máximo 2 frases por turno para manter o diálogo ágil.\n" .
"2. Se o cliente solicitar encerramento ou agradecer, encerre educadamente usando a ferramenta end_call.\n" .
"3. Se o cliente pedir para falar com um atendente humano, transfira usando a ferramenta transfer_call.";
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Dialog DDM - Criar Agente de Voz IA</title>
    <meta content='width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no' name='viewport'>

    <?php 
        print $ui->standardizedThemeCSS(); 
        print $ui->creamyThemeCSS();
    ?>
    <link href="css/style.css" rel="stylesheet" type="text/css" />
    <link href="css/dialog_ddm.css" rel="stylesheet" type="text/css" />
</head>

<?php print $ui->creamyBody(); ?>
<div class="wrapper">
    <?php print $ui->creamyHeader($user); ?>
    <?php print $ui->getSidebar($user->getUserId(), $user->getUserName(), $user->getUserRole(), $user->getUserAvatar()); ?>

    <aside class="right-side">
        <form id="form_add_vapi_agent" method="POST" action="php/SaveAIAgent.php">
            <input type="hidden" name="agent_id" id="input_agent_id" value="0" />
            <input type="hidden" name="model_preset" id="input_model_preset" value="balanced" />
            <input type="hidden" name="first_message_mode" id="input_first_message_mode" value="assistant_speaks_first" />
            <input type="hidden" name="stt_provider" id="input_stt_provider" value="deepgram" />
            <input type="hidden" name="stt_model" id="input_stt_model" value="nova-2" />
            <input type="hidden" name="stt_language" id="input_stt_language" value="pt-BR" />
            <input type="hidden" name="intelligent_turn_taking" id="input_intelligent_turn_taking" value="Y" />
            <input type="hidden" name="background_denoising" id="input_background_denoising" value="Y" />
            <input type="hidden" name="silence_timeout_ms" id="input_silence_timeout_ms" value="500" />
            <input type="hidden" name="stt_fallback_provider" id="input_stt_fallback_provider" value="openai" />
            
            <input type="hidden" name="llm_provider" id="input_llm_provider" value="groq" />
            <input type="hidden" name="llm_model" id="input_llm_model" value="llama-3.3-70b-versatile" />
            <input type="hidden" name="temperature" id="input_temperature" value="0.7" />
            <input type="hidden" name="max_tokens" id="input_max_tokens" value="250" />
            <input type="hidden" name="prompt_cache_retention" id="input_prompt_cache_retention" value="in_memory" />
            <input type="hidden" name="tool_strict_compatibility" id="input_tool_strict_compatibility" value="N" />

            <input type="hidden" name="voice_provider" id="input_voice_provider" value="cartesia" />
            <input type="hidden" name="voice_id" id="input_voice_id" value="cartesia-pt-br-sofia" />
            <input type="hidden" name="voice_name" id="input_voice_name" value="Sofia (Cartesia Sonic)" />
            <input type="hidden" name="voice_speed" id="input_voice_speed" value="1.0" />
            <input type="hidden" name="voice_stability" id="input_voice_stability" value="0.7" />
            <input type="hidden" name="voice_clarity" id="input_voice_clarity" value="0.6" />
            <input type="hidden" name="voice_style_exaggeration" id="input_voice_style_exaggeration" value="0.2" />
            <input type="hidden" name="voice_optimize_latency" id="input_voice_optimize_latency" value="1" />
            <input type="hidden" name="background_sound" id="input_background_sound" value="off" />
            <input type="hidden" name="background_sound_volume" id="input_background_sound_volume" value="0.10" />

            <input type="hidden" name="tools_json" id="input_tools_json" value="<?=htmlspecialchars(json_encode($defaultTools))?>" />
            <input type="hidden" name="status" id="input_status" value="Y" />
            <input type="hidden" name="transfer_phone_or_queue" id="input_transfer_phone_or_queue" value="" />

            <div class="ddm-container">
                
                <!-- Breadcrumbs -->
                <div class="ddm-breadcrumbs">
                    <a href="index.php"><i class="fa fa-home"></i> Dialog DDM</a>
                    <span class="separator">/</span>
                    <a href="ai_agents.php">Agentes de Voz IA</a>
                    <span class="separator">/</span>
                    <span class="current">Novo Agente</span>
                </div>

                <!-- Modern SaaS Top Header -->
                <div class="ddm-header">
                    <div class="ddm-header-left">
                        <div class="ddm-agent-avatar">
                            <i class="fa fa-plus"></i>
                        </div>
                        <div>
                            <div class="ddm-agent-title-row">
                                <input type="text" name="agent_name" class="ddm-agent-name-input vapi-agent-name-input" placeholder="Nome do Agente (Ex: Sofia SDR)" value="Sofia - Assistente Virtual" required />
                            </div>
                            <div class="ddm-meta-tags">
                                <span class="ddm-badge ddm-badge-id">Novo Agente</span>
                                <span class="ddm-badge ddm-badge-active" id="badge_status_wrap">
                                    <span class="ddm-dot-indicator ddm-dot-active"></span>
                                    <span id="badge_status_indicator">Ativo</span>
                                </span>
                                <span class="ddm-badge ddm-badge-orange"><i class="fa fa-pencil"></i> Rascunho</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="ddm-header-actions">
                        <button type="submit" class="ddm-btn ddm-btn-primary vapi-btn vapi-btn-primary" id="btn_save_agent">
                            <i class="fa fa-save"></i> Criar e Publicar Agente
                        </button>
                    </div>
                </div>

                <!-- Tab Navigation Bar -->
                <div class="ddm-tabs-container vapi-tabs">
                    <a href="#tab_assistant" class="ddm-tab vapi-tab active" data-tab="assistant">
                        <i class="fa fa-sliders"></i> Pipeline & Assistente
                    </a>
                    <a href="#tab_tools" class="ddm-tab vapi-tab" data-tab="tools">
                        <i class="fa fa-wrench"></i> Ferramentas (Tools)
                    </a>
                    <a href="#tab_analysis" class="ddm-tab vapi-tab" data-tab="analysis">
                        <i class="fa fa-pie-chart"></i> Análise & Qualificação
                    </a>
                    <a href="#tab_advanced" class="ddm-tab vapi-tab" data-tab="advanced">
                        <i class="fa fa-cogs"></i> Configurações Avançadas
                    </a>
                </div>

                <!-- ==================== TAB 1: ASSISTANT ==================== -->
                <div id="view_assistant" class="vapi-tab-view">
                    
                    <!-- Live Telemetry & Pipeline Metrics -->
                    <div class="ddm-telemetry-grid vapi-telemetry">
                        <!-- Cost Card -->
                        <div class="ddm-telemetry-card vapi-telemetry-item">
                            <div class="ddm-telemetry-header vapi-telemetry-header">
                                <span class="ddm-telemetry-label vapi-telemetry-label"><i class="fa fa-dollar text-orange"></i> Custo Estimado Total</span>
                                <span class="ddm-telemetry-value vapi-telemetry-value" id="disp_total_cost">~$0.033<span class="ddm-telemetry-unit">/min</span></span>
                            </div>
                            <div class="ddm-progress-bar vapi-progress-bar">
                                <div class="ddm-seg-stt vapi-seg-stt" id="cost_bar_stt" style="width: 25%;"></div>
                                <div class="ddm-seg-llm vapi-seg-llm" id="cost_bar_llm" style="width: 35%;"></div>
                                <div class="ddm-seg-tts vapi-seg-tts" id="cost_bar_tts" style="width: 40%;"></div>
                            </div>
                            <div class="ddm-telemetry-legend">
                                <div class="ddm-legend-item"><span class="ddm-legend-dot" style="background:#10B981;"></span> Transcrição (STT)</div>
                                <div class="ddm-legend-item"><span class="ddm-legend-dot" style="background:#0284C7;"></span> Raciocínio (LLM)</div>
                                <div class="ddm-legend-item"><span class="ddm-legend-dot" style="background:#8B5CF6;"></span> Voz (TTS)</div>
                            </div>
                        </div>

                        <!-- Latency Card -->
                        <div class="ddm-telemetry-card vapi-telemetry-item">
                            <div class="ddm-telemetry-header vapi-telemetry-header">
                                <span class="ddm-telemetry-label vapi-telemetry-label"><i class="fa fa-bolt text-orange"></i> Latência Total de Turno</span>
                                <span class="ddm-telemetry-value vapi-telemetry-value" id="disp_total_latency">~390<span class="ddm-telemetry-unit">ms</span></span>
                            </div>
                            <div class="ddm-progress-bar vapi-progress-bar">
                                <div class="ddm-seg-stt vapi-seg-stt" id="lat_bar_stt" style="width: 26%;"></div>
                                <div class="ddm-seg-llm vapi-seg-llm" id="lat_bar_llm" style="width: 39%;"></div>
                                <div class="ddm-seg-tts vapi-seg-tts" id="lat_bar_tts" style="width: 35%;"></div>
                            </div>
                            <div class="ddm-telemetry-legend">
                                <div class="ddm-legend-item"><span class="ddm-legend-dot" style="background:#10B981;"></span> STT</div>
                                <div class="ddm-legend-item"><span class="ddm-legend-dot" style="background:#0284C7;"></span> LLM</div>
                                <div class="ddm-legend-item"><span class="ddm-legend-dot" style="background:#8B5CF6;"></span> TTS</div>
                            </div>
                        </div>
                    </div>

                    <!-- Presets Selector -->
                    <div class="ddm-presets-bar vapi-presets-bar">
                        <span class="ddm-preset-label"><i class="fa fa-magic text-orange"></i> Presets de Pipeline:</span>
                        <div class="ddm-preset-pill vapi-preset-pill active" data-preset="balanced">⚡ Equilibrado (Balanced)</div>
                        <div class="ddm-preset-pill vapi-preset-pill" data-preset="high_intelligence">🧠 Alta Inteligência (GPT-4o)</div>
                        <div class="ddm-preset-pill vapi-preset-pill" data-preset="ultra_fast">🚀 Ultra-Rápido (&lt;350ms)</div>
                        <div class="ddm-preset-pill vapi-preset-pill" data-preset="cost_saver">💰 Econômico (Cost Saver)</div>
                    </div>

                    <!-- 3 Core Pipeline Cards -->
                    <div class="ddm-cards-grid vapi-cards-grid">
                        
                        <!-- 1. TRANSCRIBER CARD -->
                        <div class="ddm-card-pipeline vapi-card" id="card_transcriber">
                            <div>
                                <div class="ddm-card-header vapi-card-header">
                                    <span class="ddm-card-badge-type ddm-badge-type-stt vapi-card-type">
                                        <i class="fa fa-microphone"></i> TRANSCRIBER
                                    </span>
                                    <button type="button" class="ddm-card-edit-btn vapi-card-edit-btn" title="Configurar Transcritor"><i class="fa fa-pencil"></i></button>
                                </div>
                                <div class="ddm-card-main-title vapi-card-title" id="disp_stt_title">Deepgram Nova-2</div>
                                <div class="ddm-card-sub-title vapi-card-provider" id="disp_stt_sub"><i class="fa fa-globe text-green"></i> Deepgram • Brazilian Portuguese</div>
                            </div>
                            <div class="ddm-card-stats vapi-card-stats">
                                <div>
                                    <div class="ddm-stat-label vapi-card-stat-label">Latência</div>
                                    <div class="ddm-stat-val vapi-card-stat-val" id="disp_stt_lat">120ms</div>
                                </div>
                                <div>
                                    <div class="ddm-stat-label vapi-card-stat-label">Custo</div>
                                    <div class="ddm-stat-val vapi-card-stat-val" id="disp_stt_cost">$0.005/min</div>
                                </div>
                                <div>
                                    <div class="ddm-stat-label vapi-card-stat-label">Precisão</div>
                                    <div class="ddm-stat-val vapi-card-stat-val" id="disp_stt_acc">98.4%</div>
                                </div>
                            </div>
                        </div>

                        <!-- 2. MODEL CARD -->
                        <div class="ddm-card-pipeline vapi-card" id="card_model">
                            <div>
                                <div class="ddm-card-header vapi-card-header">
                                    <span class="ddm-card-badge-type ddm-badge-type-llm vapi-card-type">
                                        <i class="fa fa-brain"></i> MODELO LLM
                                    </span>
                                    <button type="button" class="ddm-card-edit-btn vapi-card-edit-btn" title="Configurar Modelo"><i class="fa fa-pencil"></i></button>
                                </div>
                                <div class="ddm-card-main-title vapi-card-title" id="disp_llm_title">Llama 3.3 70B</div>
                                <div class="ddm-card-sub-title vapi-card-provider" id="disp_llm_sub"><i class="fa fa-bolt text-blue"></i> Groq • Versatile & Fast</div>
                            </div>
                            <div class="ddm-card-stats vapi-card-stats">
                                <div>
                                    <div class="ddm-stat-label vapi-card-stat-label">Latência</div>
                                    <div class="ddm-stat-val vapi-card-stat-val" id="disp_llm_lat">180ms</div>
                                </div>
                                <div>
                                    <div class="ddm-stat-label vapi-card-stat-label">Custo</div>
                                    <div class="ddm-stat-val vapi-card-stat-val" id="disp_llm_cost">$0.008/min</div>
                                </div>
                                <div>
                                    <div class="ddm-stat-label vapi-card-stat-label">Inteligência</div>
                                    <div class="ddm-stat-val vapi-card-stat-val" id="disp_llm_intel">92</div>
                                </div>
                            </div>
                        </div>

                        <!-- 3. VOICE CARD -->
                        <div class="ddm-card-pipeline vapi-card" id="card_voice">
                            <div>
                                <div class="ddm-card-header vapi-card-header">
                                    <span class="ddm-card-badge-type ddm-badge-type-tts vapi-card-type">
                                        <i class="fa fa-volume-up"></i> VOZ TTS
                                    </span>
                                    <button type="button" class="ddm-card-edit-btn vapi-card-edit-btn" title="Configurar Voz"><i class="fa fa-pencil"></i></button>
                                </div>
                                <div class="ddm-card-main-title vapi-card-title" id="disp_voice_title">Sofia (Natural PT-BR)</div>
                                <div class="ddm-card-sub-title vapi-card-provider" id="disp_voice_sub"><i class="fa fa-volume-up text-purple"></i> Cartesia • Sonic Multilingual</div>
                            </div>
                            <div class="ddm-card-stats vapi-card-stats">
                                <div>
                                    <div class="ddm-stat-label vapi-card-stat-label">Latência</div>
                                    <div class="ddm-stat-val vapi-card-stat-val" id="disp_voice_lat">90ms</div>
                                </div>
                                <div>
                                    <div class="ddm-stat-label vapi-card-stat-label">Custo</div>
                                    <div class="ddm-stat-val vapi-card-stat-val" id="disp_voice_cost">$0.020/min</div>
                                </div>
                                <div>
                                    <div class="ddm-stat-label vapi-card-stat-label">Humanização</div>
                                    <div class="ddm-stat-val vapi-card-stat-val" id="disp_voice_hum">96</div>
                                </div>
                            </div>
                        </div>

                    </div>

                    <!-- First Message -->
                    <div class="ddm-section-box vapi-section-box">
                        <div class="ddm-section-header vapi-section-header">
                            <div>
                                <span class="ddm-section-title vapi-section-title"><i class="fa fa-comment-o text-orange"></i> Primeira Mensagem (Greeting)</span>
                                <div class="ddm-section-desc">Defina como o assistente iniciará a interação telefônica com o cliente.</div>
                            </div>
                            <div style="min-width: 280px;">
                                <select id="select_first_message_mode" class="ddm-select vapi-select-dark">
                                    <option value="assistant_speaks_first" selected>A IA fala primeiro (Recomendado)</option>
                                    <option value="user_speaks_first">A IA aguarda o cliente falar</option>
                                </select>
                            </div>
                        </div>
                        <textarea name="greeting_message" id="textarea_greeting" class="ddm-textarea vapi-textarea-dark" rows="3" placeholder="Olá! Eu sou a Sofia da nossa empresa. Como posso te ajudar hoje?">Olá! Eu sou a Sofia da nossa empresa. Como posso te ajudar hoje?</textarea>
                    </div>

                    <!-- System Prompt & Instructions -->
                    <div class="ddm-section-box vapi-section-box">
                        <div class="ddm-section-header vapi-section-header">
                            <div>
                                <span class="ddm-section-title vapi-section-title"><i class="fa fa-code text-orange"></i> System Prompt & Instruções do Agente</span>
                                <div class="ddm-section-desc">Instruções completas de comportamento, regras de diálogo e tom de voz da IA.</div>
                            </div>
                            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                <button type="button" class="ddm-btn ddm-btn-secondary ddm-btn-sm vapi-btn" id="btn_template_sales"><i class="fa fa-bullhorn text-orange"></i> Template Vendas</button>
                                <button type="button" class="ddm-btn ddm-btn-secondary ddm-btn-sm vapi-btn" id="btn_template_support"><i class="fa fa-life-ring text-green"></i> Template Suporte / SAC</button>
                                <button type="button" class="ddm-btn ddm-btn-secondary ddm-btn-sm vapi-btn" id="btn_template_qualify"><i class="fa fa-filter text-purple"></i> Template Qualificação</button>
                            </div>
                        </div>
                        
                        <textarea name="system_prompt" id="textarea_prompt" class="ddm-textarea ddm-prompt-editor vapi-textarea-dark vapi-prompt-editor" rows="14" required><?=htmlspecialchars($defaultPrompt)?></textarea>
                        
                        <div style="margin-top: 14px; display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
                            <span style="font-size: 11px; color: var(--ddm-text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">Variáveis Dinâmicas:</span>
                            <span class="ddm-var-pill" onclick="insertVar('{{customer_name}}')">{{customer_name}}</span>
                            <span class="ddm-var-pill" onclick="insertVar('{{phone_number}}')">{{phone_number}}</span>
                            <span class="ddm-var-pill" onclick="insertVar('{{cpf}}')">{{cpf}}</span>
                            <span class="ddm-var-pill" onclick="insertVar('{{empresa}}')">{{empresa}}</span>
                            <span class="ddm-var-pill" onclick="insertVar('{{data_hora}}')">{{data_hora}}</span>
                        </div>
                    </div>
                </div>

                <!-- ==================== TAB 2: TOOLS ==================== -->
                <div id="view_tools" class="vapi-tab-view" style="display:none;">
                    <div class="ddm-section-box vapi-section-box">
                        <div class="ddm-section-header vapi-section-header">
                            <div>
                                <span class="ddm-section-title vapi-section-title"><i class="fa fa-wrench text-orange"></i> Ferramentas e Webhooks da IA (Function Calling)</span>
                                <div class="ddm-section-desc">Habilite ações que o modelo pode executar em tempo real (ex: transferir chamada, consultar CRM, validar CPF).</div>
                            </div>
                            <button type="button" class="ddm-btn ddm-btn-primary vapi-btn vapi-btn-primary" id="btn_add_custom_tool">
                                <i class="fa fa-plus"></i> Criar Nova Ferramenta
                            </button>
                        </div>

                        <div style="overflow-x:auto; border: 1px solid var(--ddm-border); border-radius: var(--ddm-radius-md);">
                            <table class="ddm-tools-table vapi-tools-table" id="tools_table_element">
                                <thead>
                                    <tr>
                                        <th style="width: 70px;">Status</th>
                                        <th>Ferramenta</th>
                                        <th>Tipo</th>
                                        <th>Descrição / Ação</th>
                                        <th style="width: 120px; text-align: right;">Ações</th>
                                    </tr>
                                </thead>
                                <tbody id="tools_table_tbody">
                                    <!-- Rendered dynamically via JS -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- ==================== TAB 3: ANALYSIS ==================== -->
                <div id="view_analysis" class="vapi-tab-view" style="display:none;">
                    <div class="ddm-section-box vapi-section-box">
                        <div class="ddm-section-header vapi-section-header">
                            <div>
                                <span class="ddm-section-title vapi-section-title"><i class="fa fa-pie-chart text-orange"></i> Esquema de Qualificação & Extração Estruturada (JSON)</span>
                                <div class="ddm-section-desc">Configure os campos e critérios automáticos de pós-atendimento para categorizar cada chamada.</div>
                            </div>
                        </div>
                        <div class="ddm-form-group">
                            <label class="ddm-form-label">Critérios de Sucesso e Extração (Structured Output JSON)</label>
                            <textarea name="analysis_schema_json" class="ddm-textarea ddm-prompt-editor vapi-textarea-dark vapi-prompt-editor" rows="9">{"qualification": ["Interessado", "Sem interesse", "Caixa Postal", "Pediu para retornar"], "extract_fields": ["nome", "cpf", "horario_preferencial", "resumo_conversa"]}</textarea>
                            <div class="ddm-form-hint">O resultado estruturado é gerado pela IA ao finalizar a ligação e salvo no histórico de chamadas.</div>
                        </div>
                    </div>
                </div>

                <!-- ==================== TAB 4: ADVANCED ==================== -->
                <div id="view_advanced" class="vapi-tab-view" style="display:none;">
                    
                    <!-- ACCORDION 1: FALLBACKS -->
                    <div class="ddm-accordion-item vapi-section-box">
                        <div class="ddm-accordion-header vapi-accordion-header" data-target="#adv_sec_fallbacks">
                            <div class="ddm-accordion-title">
                                <i class="fa fa-shield text-green"></i>
                                <div>
                                    <div>Fallbacks & Redundância de Provedores</div>
                                    <div class="ddm-accordion-subtitle">Voz e transcrição de backup em caso de oscilação do provedor primário</div>
                                </div>
                            </div>
                            <i class="fa fa-chevron-down text-muted"></i>
                        </div>
                        <div id="adv_sec_fallbacks" class="ddm-accordion-body" style="display:none;">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="ddm-form-group">
                                        <label class="ddm-form-label"><i class="fa fa-volume-up"></i> Provedor de Voz de Backup</label>
                                        <select name="voice_fallback_provider" class="ddm-select vapi-input-dark">
                                            <option value="openai" selected>OpenAI (Nova / Alloy)</option>
                                            <option value="cartesia">Cartesia Sonic</option>
                                            <option value="azure">Azure Speech (Francisca)</option>
                                            <option value="elevenlabs">ElevenLabs (Sarah)</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="ddm-form-group">
                                        <label class="ddm-form-label"><i class="fa fa-tag"></i> ID da Voz de Backup</label>
                                        <input type="text" name="voice_fallback_id" class="ddm-input vapi-input-dark" value="nova" placeholder="ex: nova, alloy, EXAVITQu4vr4xnSDxMaL" />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ACCORDION 2: WEBHOOK SERVER -->
                    <div class="ddm-accordion-item vapi-section-box">
                        <div class="ddm-accordion-header vapi-accordion-header" data-target="#adv_sec_webhook">
                            <div class="ddm-accordion-title">
                                <i class="fa fa-globe text-blue"></i>
                                <div>
                                    <div>Servidor Webhook & Integrações</div>
                                    <div class="ddm-accordion-subtitle">Conecte eventos de início/fim de chamada e ferramentas externas</div>
                                </div>
                            </div>
                            <i class="fa fa-chevron-down text-muted"></i>
                        </div>
                        <div id="adv_sec_webhook" class="ddm-accordion-body" style="display:none;">
                            <div class="ddm-form-group">
                                <label class="ddm-form-label">URL do Endpoint Webhook</label>
                                <input type="text" name="webhook_url" class="ddm-input vapi-input-dark" value="" placeholder="https://api.seusistema.com.br/v1/webhook" />
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="ddm-form-group">
                                        <label class="ddm-form-label">Timeout (1s a 300s)</label>
                                        <input type="number" name="webhook_timeout_seconds" class="ddm-input vapi-input-dark" min="1" max="300" value="10" />
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="ddm-form-group">
                                        <label class="ddm-form-label">Custom HTTP Headers (JSON)</label>
                                        <input type="text" name="webhook_headers" class="ddm-input vapi-input-dark" value="" placeholder='{"Authorization": "Bearer token", "X-Custom": "Val"}' />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ACCORDION 3: START SPEAKING PLAN -->
                    <div class="ddm-accordion-item vapi-section-box">
                        <div class="ddm-accordion-header vapi-accordion-header" data-target="#adv_sec_start_speaking">
                            <div class="ddm-accordion-title">
                                <i class="fa fa-play-circle text-info"></i>
                                <div>
                                    <div>Start Speaking Plan (Início da Fala)</div>
                                    <div class="ddm-accordion-subtitle">Sensibilidade e tempo de espera para a IA começar a responder</div>
                                </div>
                            </div>
                            <i class="fa fa-chevron-down text-muted"></i>
                        </div>
                        <div id="adv_sec_start_speaking" class="ddm-accordion-body" style="display:none;">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="ddm-form-group">
                                        <div style="display:flex; justify-content:space-between; margin-bottom:6px;">
                                            <label class="ddm-form-label" style="margin-bottom:0;">Tempo de Espera (Wait Seconds)</label>
                                            <span class="ddm-badge ddm-badge-orange">0.4s</span>
                                        </div>
                                        <input type="range" name="start_speaking_wait_seconds" class="ddm-range-slider vapi-range-slider" min="0.0" max="5.0" step="0.1" value="0.4" />
                                        <div class="ddm-form-hint">Tempo que a IA aguarda antes de iniciar a resposta após o cliente parar de falar.</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="ddm-switch-row" style="border-top:none; padding-top:0;">
                                        <div class="ddm-switch-info">
                                            <div class="ddm-switch-title">Smart Endpointing</div>
                                            <div class="ddm-switch-desc">Detecção inteligente de pausas naturais na fala do cliente.</div>
                                        </div>
                                        <label class="ddm-switch vapi-switch">
                                            <input type="checkbox" name="smart_endpointing" value="Y" checked />
                                            <span class="ddm-slider vapi-slider"></span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ACCORDION 4: STOP SPEAKING PLAN -->
                    <div class="ddm-accordion-item vapi-section-box">
                        <div class="ddm-accordion-header vapi-accordion-header" data-target="#adv_sec_stop_speaking">
                            <div class="ddm-accordion-title">
                                <i class="fa fa-stop-circle text-orange"></i>
                                <div>
                                    <div>Stop Speaking Plan (Interrupção / Barge-in)</div>
                                    <div class="ddm-accordion-subtitle">Regras de quando o assistente deve parar de falar ao ser interrompido</div>
                                </div>
                            </div>
                            <i class="fa fa-chevron-down text-muted"></i>
                        </div>
                        <div id="adv_sec_stop_speaking" class="ddm-accordion-body" style="display:none;">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="ddm-form-group">
                                        <label class="ddm-form-label">Número de Palavras (0 a 10)</label>
                                        <input type="number" name="stop_speaking_num_words" class="ddm-input vapi-input-dark" min="0" max="10" value="2" />
                                        <div class="ddm-form-hint">Quantidade de palavras faladas pelo cliente para acionar a interrupção.</div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="ddm-form-group">
                                        <label class="ddm-form-label">Segundos de Voz (0s a 0.5s)</label>
                                        <input type="number" name="stop_speaking_voice_seconds" class="ddm-input vapi-input-dark" step="0.05" min="0.0" max="0.5" value="0.2" />
                                        <div class="ddm-form-hint">Duração mínima de áudio antes de pausar a fala da IA.</div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="ddm-form-group">
                                        <label class="ddm-form-label">Backoff Seconds (0s a 10s)</label>
                                        <input type="number" name="stop_speaking_backoff_seconds" class="ddm-input vapi-input-dark" step="0.1" min="0.0" max="10.0" value="1.0" />
                                        <div class="ddm-form-hint">Tempo de espera antes da IA retomar a fala.</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ACCORDION 5: VOICEMAIL DETECTION -->
                    <div class="ddm-accordion-item vapi-section-box">
                        <div class="ddm-accordion-header vapi-accordion-header" data-target="#adv_sec_voicemail">
                            <div class="ddm-accordion-title">
                                <i class="fa fa-envelope-o text-yellow"></i>
                                <div>
                                    <div>Detecção de Caixa Postal (Voicemail / AMD)</div>
                                    <div class="ddm-accordion-subtitle">Identificação de secretária eletrônica e comportamento automático</div>
                                </div>
                            </div>
                            <i class="fa fa-chevron-down text-muted"></i>
                        </div>
                        <div id="adv_sec_voicemail" class="ddm-accordion-body" style="display:none;">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="ddm-form-group">
                                        <label class="ddm-form-label">Provedor de Detecção</label>
                                        <select name="voicemail_detection_provider" class="ddm-select vapi-input-dark">
                                            <option value="vapi" selected>Dialog DDM AI Engine (Recomendado)</option>
                                            <option value="openai">OpenAI Fast Classifier</option>
                                            <option value="twilio_amd">Asterisk / SIP AMD</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="ddm-form-group">
                                        <label class="ddm-form-label">Ação ao Detectar Caixa Postal</label>
                                        <select name="hangup_on_voicemail" class="ddm-select vapi-input-dark">
                                            <option value="Y" selected>Desligar Imediatamente (Hangup)</option>
                                            <option value="N">Deixar Recado de Caixa Postal</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="ddm-form-group">
                                <label class="ddm-form-label">Mensagem de Caixa Postal (Recado)</label>
                                <textarea name="voicemail_message" class="ddm-textarea vapi-textarea-dark" rows="2" placeholder="Olá, tentei entrar em contato sobre a sua solicitação. Retornarei em breve..."></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- ACCORDION 6: TIMEOUT SETTINGS -->
                    <div class="ddm-accordion-item vapi-section-box">
                        <div class="ddm-accordion-header vapi-accordion-header" data-target="#adv_sec_timeout">
                            <div class="ddm-accordion-title">
                                <i class="fa fa-clock-o text-cyan"></i>
                                <div>
                                    <div>Timeouts e Duração da Chamada</div>
                                    <div class="ddm-accordion-subtitle">Limites de inatividade, silêncio e tempo máximo de conversação</div>
                                </div>
                            </div>
                            <i class="fa fa-chevron-down text-muted"></i>
                        </div>
                        <div id="adv_sec_timeout" class="ddm-accordion-body" style="display:none;">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="ddm-form-group">
                                        <label class="ddm-form-label">Silence Timeout (5s a 3600s)</label>
                                        <input type="number" name="silence_timeout_ms" class="ddm-input vapi-input-dark" value="500" />
                                        <div class="ddm-form-hint">Tempo máximo de silêncio contínuo antes de encerrar.</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="ddm-form-group">
                                        <label class="ddm-form-label">Duração Máxima da Chamada (Segundos)</label>
                                        <input type="number" name="max_duration_seconds" class="ddm-input vapi-input-dark" value="600" />
                                        <div class="ddm-form-hint">Limite total de duração da chamada telefônica.</div>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="ddm-form-group">
                                        <label class="ddm-form-label">Ramal / Fila de Transferência Humana Padrão</label>
                                        <input type="text" name="transfer_phone_or_queue" class="ddm-input vapi-input-dark" value="" placeholder="Ex: 8300 ou ramal SIP" />
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="ddm-form-group">
                                        <label class="ddm-form-label">Sensibilidade de Interrupção</label>
                                        <input type="range" name="interruption_sensitivity" class="ddm-range-slider vapi-range-slider" min="0.1" max="1.0" step="0.05" value="0.8" />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ACCORDION 7: KEYPAD INPUT (DTMF) -->
                    <div class="ddm-accordion-item vapi-section-box">
                        <div class="ddm-accordion-header vapi-accordion-header" data-target="#adv_sec_keypad">
                            <div class="ddm-accordion-title">
                                <i class="fa fa-th text-purple"></i>
                                <div>
                                    <div>Entrada por Teclado Numérico (DTMF)</div>
                                    <div class="ddm-accordion-subtitle">Permitir que o cliente digite números no teclado do telefone</div>
                                </div>
                            </div>
                            <i class="fa fa-chevron-down text-muted"></i>
                        </div>
                        <div id="adv_sec_keypad" class="ddm-accordion-body" style="display:none;">
                            <div class="ddm-switch-row" style="border-top:none; padding-top:0;">
                                <div class="ddm-switch-info">
                                    <div class="ddm-switch-title">Habilitar Entrada DTMF (Teclado)</div>
                                    <div class="ddm-switch-desc">Aceita digitação de números durante a chamada telefônica.</div>
                                </div>
                                <label class="ddm-switch vapi-switch">
                                    <input type="checkbox" name="enable_keypad_input" value="Y" />
                                    <span class="ddm-slider vapi-slider"></span>
                                </label>
                            </div>
                            <div class="row" style="margin-top:12px;">
                                <div class="col-md-6">
                                    <div class="ddm-form-group">
                                        <label class="ddm-form-label">Timeout de Digitação (Segundos)</label>
                                        <input type="number" name="keypad_timeout_seconds" class="ddm-input vapi-input-dark" min="0" max="10" value="5" />
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="ddm-form-group">
                                        <label class="ddm-form-label">Caractere Delimitador (# ou *)</label>
                                        <input type="text" name="keypad_delimiter" class="ddm-input vapi-input-dark" value="#" />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ACCORDION 8: RECORDING & ARTIFACTS -->
                    <div class="ddm-accordion-item vapi-section-box">
                        <div class="ddm-accordion-header vapi-accordion-header" data-target="#adv_sec_recording">
                            <div class="ddm-accordion-title">
                                <i class="fa fa-file-audio-o text-pink"></i>
                                <div>
                                    <div>Gravação, Áudio e Status</div>
                                    <div class="ddm-accordion-subtitle">Formato de gravação estereofônica e disponibilidade do agente</div>
                                </div>
                            </div>
                            <i class="fa fa-chevron-down text-muted"></i>
                        </div>
                        <div id="adv_sec_recording" class="ddm-accordion-body" style="display:none;">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="ddm-form-group">
                                        <label class="ddm-form-label">Formato do Áudio Gravado</label>
                                        <select name="recording_audio_format" class="ddm-select vapi-input-dark">
                                            <option value="wav" selected>WAV (PCM sem compressão - Alta Qualidade)</option>
                                            <option value="mp3">MP3 (Compactado)</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="ddm-form-group">
                                        <label class="ddm-form-label">Status Operacional do Agente</label>
                                        <select name="status" id="select_agent_status" class="ddm-select vapi-select-dark">
                                            <option value="Y" selected>Ativo (Pronto para discar / receber)</option>
                                            <option value="N">Inativo</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ACCORDION 9: MESSAGING & IDLE -->
                    <div class="ddm-accordion-item vapi-section-box">
                        <div class="ddm-accordion-header vapi-accordion-header" data-target="#adv_sec_messaging">
                            <div class="ddm-accordion-title">
                                <i class="fa fa-comments-o text-teal"></i>
                                <div>
                                    <div>Mensagens de Inatividade & Finalização</div>
                                    <div class="ddm-accordion-subtitle">Frases de encerramento e alertas quando o cliente ficar em silêncio</div>
                                </div>
                            </div>
                            <i class="fa fa-chevron-down text-muted"></i>
                        </div>
                        <div id="adv_sec_messaging" class="ddm-accordion-body" style="display:none;">
                            <div class="ddm-form-group">
                                <label class="ddm-form-label">Mensagem de Encerramento (End Call Message)</label>
                                <input type="text" name="end_call_message" class="ddm-input vapi-input-dark" value="" placeholder="Mensagem falada pelo assistente ao encerrar." />
                            </div>
                            <div class="ddm-form-group">
                                <label class="ddm-form-label">Frases de Gatilho para Desligamento (Separadas por vírgula)</label>
                                <input type="text" name="end_call_phrases" class="ddm-input vapi-input-dark" value="" placeholder="tenha um bom dia, tchau tchau, até logo, agradeço o contato" />
                                <div class="ddm-form-hint">Quando a IA falar uma dessas frases, a chamada será encerrada imediatamente.</div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="ddm-form-group">
                                        <label class="ddm-form-label">Mensagens de Inatividade (Quando o cliente não responder)</label>
                                        <input type="text" name="idle_messages" class="ddm-input vapi-input-dark" value="" placeholder="Você ainda está aí?, Olá, pode me ouvir?" />
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="ddm-form-group">
                                        <label class="ddm-form-label">Máximo de Mensagens</label>
                                        <input type="number" name="max_idle_messages" class="ddm-input vapi-input-dark" min="1" max="10" value="3" />
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="ddm-form-group">
                                        <label class="ddm-form-label">Timeout de Inatividade (s)</label>
                                        <input type="number" name="idle_timeout_seconds" class="ddm-input vapi-input-dark" min="5" max="60" value="7" />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

            </div>
        </form>
    </aside>
</div>

<!-- ==================== MODALS / DRAWERS ==================== -->

<!-- 1. TRANSCRIBER SETTINGS DRAWER -->
<div class="ddm-drawer-backdrop vapi-modal-backdrop" id="modal_transcriber">
    <div class="ddm-drawer vapi-drawer">
        <div>
            <div class="ddm-drawer-header vapi-drawer-header">
                <span class="ddm-drawer-title vapi-drawer-title"><i class="fa fa-microphone text-green"></i> Configurações do Transcritor</span>
                <button type="button" class="ddm-drawer-close btn-close-drawer"><i class="fa fa-times"></i></button>
            </div>
            <div class="ddm-drawer-subtitle vapi-drawer-subtitle">
                Configure o modelo de reconhecimento de fala (STT) que converte o áudio do cliente em texto com ultra-baixa latência.
            </div>

            <div class="ddm-form-group vapi-form-group">
                <label class="ddm-form-label vapi-form-label">Modelo / Provedor STT</label>
                <select id="modal_stt_provider_select" class="ddm-select vapi-input-dark">
                    <option value="deepgram|nova-2">Deepgram Nova-2 (120ms • $0.005/min)</option>
                    <option value="groq|whisper-large-v3-turbo">Groq Whisper Turbo (90ms • $0.003/min)</option>
                    <option value="elevenlabs|scribe_v1">ElevenLabs Scribe v1 (450ms • $0.010/min)</option>
                    <option value="openai|whisper-1">OpenAI Whisper (950ms • $0.012/min)</option>
                    <option value="azure|azure-default">Azure Speech (650ms • $0.025/min)</option>
                </select>
            </div>

            <div class="ddm-form-group vapi-form-group">
                <label class="ddm-form-label vapi-form-label"><i class="fa fa-globe"></i> Idioma Principal</label>
                <select id="modal_stt_language_select" class="ddm-select vapi-input-dark">
                    <option value="pt-BR">Português do Brasil (pt-BR)</option>
                    <option value="en-US">English (en-US)</option>
                    <option value="es-ES">Español (es-ES)</option>
                </select>
            </div>

            <div class="ddm-switch-row vapi-switch-row">
                <div class="ddm-switch-info vapi-switch-info">
                    <div class="ddm-switch-title vapi-switch-title">Intelligent Turn-Taking</div>
                    <div class="ddm-switch-desc vapi-switch-desc">Detecta com precisão quando o cliente concluiu a fala.</div>
                </div>
                <label class="ddm-switch vapi-switch">
                    <input type="checkbox" id="modal_turn_taking" checked />
                    <span class="ddm-slider vapi-slider"></span>
                </label>
            </div>

            <div class="ddm-switch-row vapi-switch-row">
                <div class="ddm-switch-info vapi-switch-info">
                    <div class="ddm-switch-title vapi-switch-title">Redução de Ruído de Fundo (Denoising)</div>
                    <div class="ddm-switch-desc vapi-switch-desc">Filtra ruídos de fundo da linha do cliente.</div>
                </div>
                <label class="ddm-switch vapi-switch">
                    <input type="checkbox" id="modal_denoising" checked />
                    <span class="ddm-slider vapi-slider"></span>
                </label>
            </div>

            <!-- Collapsible Advanced -->
            <div class="ddm-accordion-header vapi-accordion-header" id="adv_stt_toggle" style="margin-top:12px; padding:10px 0;">
                <span style="font-weight:700; font-size:13px; color:var(--ddm-text-primary);">Configurações Avançadas</span>
                <i class="fa fa-chevron-down"></i>
            </div>
            <div id="adv_stt_body" style="padding-top: 10px;">
                <div class="ddm-form-group vapi-form-group">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                        <label class="ddm-form-label vapi-form-label" style="margin-bottom:0;"><i class="fa fa-clock-o"></i> Timeout de Silêncio</label>
                        <span class="ddm-badge ddm-badge-orange" id="disp_silence_timeout_badge">500 ms</span>
                    </div>
                    <div class="ddm-form-hint">Tempo de silêncio necessário para fechar o bloco de transcrição.</div>
                    <input type="range" id="modal_silence_slider" class="ddm-range-slider vapi-range-slider" min="100" max="3000" step="50" value="500" />
                    <div style="display:flex; justify-content:space-between; font-size:10px; color:var(--ddm-text-muted); margin-top:4px;">
                        <span>100ms (Mais Rápido)</span>
                        <span>3000ms</span>
                    </div>
                </div>

                <div class="ddm-form-group vapi-form-group">
                    <label class="ddm-form-label vapi-form-label"><i class="fa fa-shield"></i> Provedor STT de Fallback</label>
                    <select id="modal_stt_fallback_select" class="ddm-select vapi-input-dark">
                        <option value="openai">OpenAI Whisper (Auto-Fallback)</option>
                        <option value="groq">Groq Whisper Turbo</option>
                        <option value="deepgram">Deepgram Nova-2</option>
                    </select>
                </div>
            </div>
        </div>

        <div style="margin-top: 24px;">
            <button type="button" class="ddm-btn ddm-btn-primary btn-close-drawer" style="width:100%; justify-content:center;">
                Aplicar Configurações de Transcrição
            </button>
        </div>
    </div>
</div>

<!-- 2. MODEL SETTINGS DRAWER -->
<div class="ddm-drawer-backdrop vapi-modal-backdrop" id="modal_model">
    <div class="ddm-drawer vapi-drawer">
        <div>
            <div class="ddm-drawer-header vapi-drawer-header">
                <span class="ddm-drawer-title vapi-drawer-title"><i class="fa fa-brain text-blue"></i> Configurações do Modelo LLM</span>
                <button type="button" class="ddm-drawer-close btn-close-drawer"><i class="fa fa-times"></i></button>
            </div>
            <div class="ddm-drawer-subtitle vapi-drawer-subtitle">
                Selecione o modelo de linguagem generativa que processa o raciocínio, lógica e contexto do assistente.
            </div>

            <div class="ddm-form-group vapi-form-group">
                <label class="ddm-form-label vapi-form-label">Modelo LLM</label>
                <select id="modal_llm_model_select" class="ddm-select vapi-input-dark">
                    <optgroup label="OpenAI (Vapi Frontier & Next-Gen Clusters)">
                        <option value="openai|gpt-4.1">GPT-4.1 (690ms • $0.023/min • 20 Intel)</option>
                        <option value="openai|gpt-5.6-sol">GPT-5.6 Sol (1060ms • $0.041/min • 42 Intel)</option>
                        <option value="openai|gpt-5.6-terra">GPT-5.6 Terra (870ms • $0.021/min • 35 Intel)</option>
                        <option value="openai|gpt-5.6-luna">GPT-5.6 Luna (770ms • $0.010/min • 27 Intel)</option>
                        <option value="openai|gpt-5.5">GPT-5.5 (1250ms • $0.052/min • 36 Intel)</option>
                        <option value="openai|chatgpt-4o-latest">GPT Instant (latest) (700ms • $0.052/min • 99 Intel)</option>
                        <option value="openai|gpt-5.4">GPT-5.4 (800ms • $0.026/min • 28 Intel)</option>
                        <option value="openai|gpt-5.4-mini">GPT-5.4 Mini (720ms • $0.010/min • 17 Intel)</option>
                        <option value="openai|gpt-5.4-nano">GPT-5.4 Nano (1160ms • $0.010/min • 18 Intel)</option>
                        <option value="openai|gpt-5.2">GPT-5.2 (830ms • $0.019/min • 27 Intel)</option>
                        <option value="openai|gpt-5.1">GPT-5.1 (810ms • $0.013/min • 21 Intel)</option>
                        <option value="openai|gpt-5">GPT-5 (840ms • $0.013/min • 17 Intel)</option>
                        <option value="openai|gpt-5-mini">GPT-5 Mini (820ms • $0.010/min • 14 Intel)</option>
                        <option value="openai|gpt-5-nano">GPT-5 Nano (710ms • $0.010/min • 8 Intel)</option>
                        <option value="openai|gpt-4.1-mini">GPT-4.1 Mini (600ms • $0.010/min • 15 Intel)</option>
                        <option value="openai|gpt-4.1-nano">GPT-4.1 Nano (450ms • $0.010/min • 10 Intel)</option>
                        <option value="openai|gpt-4o-mini-cluster">GPT-4o Mini Cluster (560ms • $0.010/min • 7 Intel)</option>
                        <option value="openai|gpt-4o-cluster">GPT-4o Cluster (670ms • $0.045/min • 11 Intel)</option>
                        <option value="openai|gpt-realtime-cluster">GPT Realtime Cluster (450ms • $0.290/min • 95 Intel)</option>
                        <option value="openai|gpt-realtime-mini">GPT Realtime Mini (400ms • $0.060/min • 90 Intel)</option>
                        <option value="openai|gpt-o3-cluster">GPT o3 Cluster (1000ms • $0.036/min • 31 Intel)</option>
                        <option value="openai|gpt-4o-mini">OpenAI GPT-4o Mini (320ms • $0.004/min • 88 Intel)</option>
                        <option value="openai|gpt-4o">OpenAI GPT-4o (620ms • $0.022/min • 99 Intel)</option>
                        <option value="openai|o3-mini">OpenAI o3-mini (550ms • $0.012/min • 97 Intel - Reasoning)</option>
                        <option value="openai|o1-mini">OpenAI o1-mini (750ms • $0.015/min • 95 Intel - Reasoning)</option>
                        <option value="openai|o1">OpenAI o1 (1200ms • $0.080/min • 100 Intel - Deep Reasoning)</option>
                    </optgroup>
                    <optgroup label="Groq (Ultra-Rápido)">
                        <option value="groq|llama-3.3-70b-versatile" selected>Groq Llama 3.3 70B (180ms • $0.008/min • 92 Intel)</option>
                        <option value="groq|llama-3.1-8b-instant">Groq Llama 3.1 8B Instant (80ms • $0.002/min • 75 Intel)</option>
                    </optgroup>
                    <optgroup label="Anthropic & DeepSeek">
                        <option value="anthropic|claude-3-5-sonnet-20241022">Claude 3.5 Sonnet (750ms • $0.030/min • 98 Intel)</option>
                        <option value="deepseek|deepseek-chat">DeepSeek V3 (280ms • $0.003/min • 90 Intel)</option>
                    </optgroup>
                </select>
            </div>

            <div class="ddm-form-group vapi-form-group">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                    <label class="ddm-form-label vapi-form-label" style="margin-bottom:0;"><i class="fa fa-thermometer-half"></i> Temperatura (Criatividade)</label>
                    <span class="ddm-badge ddm-badge-orange" id="disp_temp_badge">0.7</span>
                </div>
                <div class="ddm-form-hint">Valores menores tornam as respostas mais precisas e objetivas.</div>
                <input type="range" id="modal_temp_slider" class="ddm-range-slider vapi-range-slider" min="0.0" max="1.0" step="0.05" value="0.7" />
                <div style="display:flex; justify-content:space-between; font-size:10px; color:var(--ddm-text-muted); margin-top:4px;">
                    <span>0.0 (Preciso & Direto)</span>
                    <span>1.0 (Criativo)</span>
                </div>
            </div>

            <!-- Collapsible Advanced -->
            <div class="ddm-accordion-header vapi-accordion-header" id="adv_llm_toggle" style="margin-top:12px; padding:10px 0;">
                <span style="font-weight:700; font-size:13px; color:var(--ddm-text-primary);">Configurações Avançadas</span>
                <i class="fa fa-chevron-down"></i>
            </div>
            <div id="adv_llm_body" style="padding-top: 10px;">
                <div class="ddm-form-group vapi-form-group">
                    <label class="ddm-form-label vapi-form-label"><i class="fa fa-comment"></i> Max Tokens por Turno</label>
                    <input type="number" id="modal_max_tokens_input" class="ddm-input vapi-input-dark" value="250" />
                </div>

                <div class="ddm-form-group vapi-form-group">
                    <label class="ddm-form-label vapi-form-label"><i class="fa fa-database"></i> Prompt Cache Retention</label>
                    <select id="modal_cache_select" class="ddm-select vapi-input-dark">
                        <option value="in_memory">In-Memory Prompt Cache (Ultra Rápido)</option>
                        <option value="disabled">Desabilitado</option>
                    </select>
                </div>

                <div class="ddm-form-group vapi-form-group">
                    <label class="ddm-form-label vapi-form-label"><i class="fa fa-check-square-o"></i> Tool Strict Compatibility</label>
                    <select id="modal_tool_strict_select" class="ddm-select vapi-input-dark">
                        <option value="N">Desativado</option>
                        <option value="Y">Ativado (Strict Mode)</option>
                    </select>
                </div>
            </div>
        </div>

        <div style="margin-top: 24px;">
            <button type="button" class="ddm-btn ddm-btn-primary btn-close-drawer" style="width:100%; justify-content:center;">
                Aplicar Configurações do Modelo
            </button>
        </div>
    </div>
</div>

<!-- 3. VOICE SETTINGS DRAWER -->
<div class="ddm-drawer-backdrop vapi-modal-backdrop" id="modal_voice">
    <div class="ddm-drawer vapi-drawer">
        <div>
            <div class="ddm-drawer-header vapi-drawer-header">
                <span class="ddm-drawer-title vapi-drawer-title"><i class="fa fa-volume-up text-purple"></i> Configurações de Voz (TTS)</span>
                <button type="button" class="ddm-drawer-close btn-close-drawer"><i class="fa fa-times"></i></button>
            </div>
            <div class="ddm-drawer-subtitle vapi-drawer-subtitle">
                Personalize o timbre, emoção, velocidade e ambiente sonoro da voz gerada por IA.
            </div>

            <div class="ddm-form-group vapi-form-group">
                <label class="ddm-form-label vapi-form-label">Provedor & Motor de Síntese</label>
                <select id="modal_voice_provider_select" class="ddm-select vapi-input-dark">
                    <option value="cartesia" selected>Cartesia Sonic (90ms • $0.020/min • 96 Humanização)</option>
                    <option value="elevenlabs">ElevenLabs Multilingual v2 (650ms • $0.036/min • 92 Humanização)</option>
                    <option value="openai">OpenAI TTS-1 (350ms • $0.015/min • 84 Humanização)</option>
                    <option value="azure">Microsoft Azure Speech (400ms • $0.016/min • 90 Humanização)</option>
                </select>
            </div>

            <div class="ddm-form-group vapi-form-group">
                <label class="ddm-form-label vapi-form-label">Biblioteca de Vozes</label>
                <div class="ddm-pill-group vapi-pill-group" style="margin-bottom: 8px;">
                    <div class="ddm-pill-opt vapi-pill-opt active" id="tab_voice_library">Catálogo Oficial</div>
                    <div class="ddm-pill-opt vapi-pill-opt" id="tab_custom_voice_id">Voice ID Customizado</div>
                </div>
                
                <div id="voice_library_view">
                    <select id="modal_voice_id_select" class="ddm-select vapi-input-dark">
                        <option value="elevenlabs|PznTnBc8X6pvixs9UkQm">PznTnBc8X6pvixs9UkQm • ElevenLabs (Multilingual v2 - Vapi Clone)</option>
                        <option value="elevenlabs|tMzxR2W7o3RLIY7zWBbG">Júlia (Voz Oficial) • ElevenLabs (Natural Conversational PT-BR)</option>
                        <option value="elevenlabs|21m00Tcm4TlvDq8ikWAM">Rachel • ElevenLabs (Conversational Female)</option>
                        <option value="elevenlabs|EXAVITQu4vr4xnSDxMaL">Sarah • ElevenLabs (Warm & Reassuring Female)</option>
                        <option value="elevenlabs|AZnzlk1XvdvUeBnXmlld">Domi • ElevenLabs (Energetic Female)</option>
                        <option value="elevenlabs|pNInz6obpgDQGcFmaJgB">Adam • ElevenLabs (Deep & Professional Male)</option>
                        <option value="elevenlabs|TxGEqnHWrfWFTfGW9XjX">Josh • ElevenLabs (Conversational Young Male)</option>
                        <option value="elevenlabs|XrExE9yKIg1WjnnlVkGX">Matilda • ElevenLabs (Expressive Female)</option>
                        <option value="cartesia|cartesia-pt-br-sofia" selected>Sofia • Cartesia Sonic (Natural PT-BR Female)</option>
                        <option value="cartesia|cartesia-pt-br-lucas">Lucas • Cartesia Sonic (Professional PT-BR Male)</option>
                        <option value="openai|nova">Nova • OpenAI (Energetic & Friendly)</option>
                        <option value="openai|alloy">Alloy • OpenAI (Neutral Male)</option>
                        <option value="azure|pt-BR-FranciscaNeural">Francisca • Azure Speech (Natural PT-BR Female)</option>
                        <option value="azure|pt-BR-AntonioNeural">Antonio • Azure Speech (Professional PT-BR Male)</option>
                        <option value="azure|pt-BR-ThalitaNeural">Thalita • Azure Speech (Expressive PT-BR Female)</option>
                    </select>
                </div>

                <div id="custom_voice_id_view" style="display:none;">
                    <input type="text" id="modal_custom_voice_id_input" class="ddm-input vapi-input-dark" placeholder="Cole o Voice ID customizado aqui..." value="" />
                </div>
            </div>

            <div class="ddm-form-group vapi-form-group">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                    <label class="ddm-form-label vapi-form-label" style="margin-bottom:0;"><i class="fa fa-tachometer"></i> Velocidade de Fala (Speed)</label>
                    <span class="ddm-badge ddm-badge-orange" id="disp_speed_badge">1.0x</span>
                </div>
                <input type="range" id="modal_speed_slider" class="ddm-range-slider vapi-range-slider" min="0.75" max="1.50" step="0.05" value="1.0" />
                <div style="display:flex; justify-content:space-between; font-size:10px; color:var(--ddm-text-muted); margin-top:4px;">
                    <span>0.75x (Mais Lento)</span>
                    <span>1.00x (Natural)</span>
                    <span>1.50x (Rápido)</span>
                </div>
            </div>

            <div class="ddm-form-group vapi-form-group">
                <label class="ddm-form-label vapi-form-label"><i class="fa fa-volume-up"></i> Ruído de Fundo (Ambiente Call Center / Escritório)</label>
                <div class="ddm-form-hint" style="margin-bottom: 8px;">Simula ambiente sonoro humanizado para eliminar o silêncio de linha estéril.</div>
                <div class="ddm-pill-group vapi-pill-group" id="bg_sound_pills">
                    <div class="ddm-pill-opt vapi-pill-opt active" data-bgsound="off">Desativado</div>
                    <div class="ddm-pill-opt vapi-pill-opt" data-bgsound="office">Escritório</div>
                    <div class="ddm-pill-opt vapi-pill-opt" data-bgsound="typing">Teclado</div>
                    <div class="ddm-pill-opt vapi-pill-opt" data-bgsound="room">Sala</div>
                </div>
            </div>

            <div class="ddm-form-group vapi-form-group" id="bg_volume_group" style="display:none;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                    <label class="ddm-form-label vapi-form-label" style="margin-bottom:0;"><i class="fa fa-sliders"></i> Volume do Som de Fundo</label>
                    <span class="ddm-badge ddm-badge-orange" id="modal_bg_vol_val">10%</span>
                </div>
                <input type="range" id="modal_bg_vol_slider" class="ddm-range-slider vapi-range-slider" min="0.02" max="0.30" step="0.01" value="0.10" />
                <div style="display:flex; justify-content:space-between; font-size:10px; color:var(--ddm-text-muted); margin-top:4px;">
                    <span>Sutil (2%)</span>
                    <span>Recomendado (10%)</span>
                    <span>Forte (30%)</span>
                </div>
            </div>

            <!-- Collapsible Advanced -->
            <div class="ddm-accordion-header vapi-accordion-header" id="adv_voice_toggle" style="margin-top:12px; padding:10px 0;">
                <span style="font-weight:700; font-size:13px; color:var(--ddm-text-primary);">Configurações Avançadas</span>
                <i class="fa fa-chevron-down"></i>
            </div>
            <div id="adv_voice_body" style="padding-top: 10px;">
                <div class="ddm-form-group vapi-form-group">
                    <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
                        <label class="ddm-form-label vapi-form-label" style="margin-bottom:0;">Estabilidade (Stability)</label>
                        <span class="ddm-badge ddm-badge-orange" id="disp_stability_badge">0.7</span>
                    </div>
                    <input type="range" id="modal_stability_slider" class="ddm-range-slider vapi-range-slider" min="0.0" max="1.0" step="0.05" value="0.7" />
                </div>

                <div class="ddm-form-group vapi-form-group">
                    <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
                        <label class="ddm-form-label vapi-form-label" style="margin-bottom:0;">Clareza e Similaridade</label>
                        <span class="ddm-badge ddm-badge-orange" id="disp_clarity_badge">0.6</span>
                    </div>
                    <input type="range" id="modal_clarity_slider" class="ddm-range-slider vapi-range-slider" min="0.0" max="1.0" step="0.05" value="0.6" />
                </div>

                <div class="ddm-form-group vapi-form-group">
                    <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
                        <label class="ddm-form-label vapi-form-label" style="margin-bottom:0;">Exagero de Estilo</label>
                        <span class="ddm-badge ddm-badge-orange" id="disp_style_badge">0.2</span>
                    </div>
                    <input type="range" id="modal_style_slider" class="ddm-range-slider vapi-range-slider" min="0.0" max="1.0" step="0.05" value="0.2" />
                </div>

                <div class="ddm-form-group vapi-form-group">
                    <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
                        <label class="ddm-form-label vapi-form-label" style="margin-bottom:0;">Otimização de Latência</label>
                        <span class="ddm-badge ddm-badge-orange" id="disp_opt_lat_badge">1</span>
                    </div>
                    <input type="range" id="modal_opt_lat_slider" class="ddm-range-slider vapi-range-slider" min="0" max="4" step="1" value="1" />
                </div>
            </div>
        </div>

        <div style="margin-top: 24px;">
            <button type="button" class="ddm-btn ddm-btn-primary btn-close-drawer" style="width:100%; justify-content:center;">
                Aplicar Configurações de Voz
            </button>
        </div>
    </div>
</div>

<!-- 4. CUSTOM TOOL DRAWER -->
<div class="ddm-drawer-backdrop vapi-modal-backdrop" id="modal_tool_drawer">
    <div class="ddm-drawer vapi-drawer" style="width: 560px;">
        <div>
            <div class="ddm-drawer-header vapi-drawer-header">
                <span class="ddm-drawer-title vapi-drawer-title" id="tool_drawer_title"><i class="fa fa-wrench text-orange"></i> Ferramenta / Tool</span>
                <button type="button" class="ddm-drawer-close btn-close-drawer"><i class="fa fa-times"></i></button>
            </div>
            <div class="ddm-drawer-subtitle vapi-drawer-subtitle">
                Configure uma função, webhook ou ação externa invocável pelo modelo de IA durante o diálogo.
            </div>

            <input type="hidden" id="tool_drawer_mode" value="add" />
            <input type="hidden" id="tool_edit_orig_id" value="" />

            <div class="ddm-form-group vapi-form-group">
                <label class="ddm-form-label vapi-form-label">Modelo Rápido / Template</label>
                <select id="tool_quick_template_select" class="ddm-select vapi-input-dark">
                    <option value="">-- Selecione um Template Pré-configurado --</option>
                    <option value="custom_webhook">Custom Webhook (Chamar API Externa / CRM)</option>
                    <option value="end_call">End Call (Encerrar Ligação Telefônica)</option>
                    <option value="transfer_call">Transfer Call (Transferir para Ramal/Humano)</option>
                    <option value="voicemail_tool">Voicemail Tool (Detecção de Caixa Postal)</option>
                    <option value="capturar_cpf">Capturar & Validar CPF</option>
                </select>
            </div>

            <div class="ddm-form-group vapi-form-group">
                <label class="ddm-form-label vapi-form-label">Identificador / Nome da Função (snake_case)</label>
                <input type="text" id="tool_input_name" class="ddm-input vapi-input-dark" placeholder="ex: transfer_call ou consultar_saldo" />
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="ddm-form-group vapi-form-group">
                        <label class="ddm-form-label vapi-form-label">Tipo da Ferramenta</label>
                        <select id="tool_input_type" class="ddm-select vapi-input-dark">
                            <option value="Custom tool">Custom tool (Webhook / API)</option>
                            <option value="Transfer call">Transfer call</option>
                            <option value="End call">End call</option>
                            <option value="Voicemail tool">Voicemail tool</option>
                            <option value="Function">Function</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="ddm-form-group vapi-form-group">
                        <label class="ddm-form-label vapi-form-label">Versão</label>
                        <input type="text" id="tool_input_version" class="ddm-input vapi-input-dark" value="Latest" />
                    </div>
                </div>
            </div>

            <div class="ddm-form-group vapi-form-group">
                <label class="ddm-form-label vapi-form-label">Descrição (Como o modelo sabe quando chamar)</label>
                <textarea id="tool_input_desc" class="ddm-textarea vapi-textarea-dark" rows="3" placeholder="Descreva claramente o que esta função faz para que o LLM a acione na hora certa..."></textarea>
            </div>

            <div id="tool_webhook_fields">
                <div class="ddm-form-group vapi-form-group">
                    <label class="ddm-form-label vapi-form-label">URL do Endpoint Webhook</label>
                    <input type="text" id="tool_input_url" class="ddm-input vapi-input-dark" placeholder="https://api.empresa.com.br/v1/webhook" />
                </div>
                <div class="ddm-form-group vapi-form-group">
                    <label class="ddm-form-label vapi-form-label">Método HTTP</label>
                    <select id="tool_input_method" class="ddm-select vapi-input-dark">
                        <option value="POST">POST (application/json)</option>
                        <option value="GET">GET</option>
                    </select>
                </div>
            </div>

            <div class="ddm-form-group vapi-form-group">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                    <label class="ddm-form-label vapi-form-label" style="margin-bottom:0;">Parâmetros (JSON Schema)</label>
                    <button type="button" class="ddm-btn ddm-btn-secondary ddm-btn-xs" id="btn_tool_format_json"><i class="fa fa-code"></i> Formatar JSON</button>
                </div>
                <textarea id="tool_input_params" class="ddm-textarea ddm-prompt-editor vapi-textarea-dark vapi-prompt-editor" rows="6">{}</textarea>
            </div>

            <div class="ddm-switch-row vapi-switch-row">
                <div class="ddm-switch-info vapi-switch-info">
                    <div class="ddm-switch-title vapi-switch-title">Ferramenta Habilitada</div>
                    <div class="ddm-switch-desc vapi-switch-desc">Se ativada, o assistente poderá chamá-la durante a chamada.</div>
                </div>
                <label class="ddm-switch vapi-switch">
                    <input type="checkbox" id="tool_input_enabled" checked />
                    <span class="ddm-slider vapi-slider"></span>
                </label>
            </div>
        </div>

        <div style="margin-top: 24px;">
            <button type="button" class="ddm-btn ddm-btn-primary" id="btn_save_tool_drawer" style="width:100%; justify-content:center;">
                Salvar Ferramenta
            </button>
        </div>
    </div>
</div>

<?php print $ui->standardizedThemeJS(); ?>
<script type="text/javascript">
// ==========================================
// DIALOG DDM REACTIVE PIPELINE ENGINE
// ==========================================
var VAPI_CONFIG = {
    stt: {
        'deepgram|nova-2': { name: 'Deepgram Nova-2', provider: 'deepgram', model: 'nova-2', sub: 'Deepgram • Brazilian Portuguese', latency: 120, cost: 0.005, accuracy: '98.4%', icon: 'fa fa-globe text-green' },
        'groq|whisper-large-v3-turbo': { name: 'Groq Whisper Turbo', provider: 'groq', model: 'whisper-large-v3-turbo', sub: 'Groq • Ultra-fast Whisper', latency: 90, cost: 0.003, accuracy: '97.8%', icon: 'fa fa-bolt text-yellow' },
        'elevenlabs|scribe_v1': { name: 'ElevenLabs Scribe', provider: 'elevenlabs', model: 'scribe_v1', sub: 'ElevenLabs • Scribe v1 Multilingual', latency: 450, cost: 0.010, accuracy: '98.6%', icon: 'fa fa-music text-purple' },
        'openai|whisper-1': { name: 'OpenAI Whisper', provider: 'openai', model: 'whisper-1', sub: 'OpenAI • Audio API', latency: 950, cost: 0.012, accuracy: '98.9%', icon: 'fa fa-circle-o-notch text-blue' },
        'azure|azure-default': { name: 'Azure Speech', provider: 'azure', model: 'azure-default', sub: 'Microsoft Azure Speech', latency: 650, cost: 0.025, accuracy: '98.1%', icon: 'fa fa-windows text-info' }
    },
    llm: {
        'groq|llama-3.3-70b-versatile': { name: 'Llama 3.3 70B', provider: 'groq', model: 'llama-3.3-70b-versatile', sub: 'Groq • Versatile & Fast', latency: 180, cost: 0.008, intel: 92, icon: 'fa fa-bolt text-blue' },
        'groq|llama-3.1-8b-instant': { name: 'Llama 3.1 8B Instant', provider: 'groq', model: 'llama-3.1-8b-instant', sub: 'Groq • Ultra Fast (<100ms)', latency: 80, cost: 0.002, intel: 75, icon: 'fa fa-bolt text-yellow' },
        'openai|gpt-4.1': { name: 'GPT-4.1', provider: 'openai', model: 'gpt-4.1', sub: 'OpenAI • Frontier High Precision', latency: 690, cost: 0.023, intel: 20, icon: 'fa fa-cube text-green' },
        'openai|gpt-5.6-sol': { name: 'GPT-5.6 Sol', provider: 'openai', model: 'gpt-5.6-sol', sub: 'OpenAI • Maximum Intelligence Cluster', latency: 1060, cost: 0.041, intel: 42, icon: 'fa fa-cube text-green' },
        'openai|gpt-5.6-terra': { name: 'GPT-5.6 Terra', provider: 'openai', model: 'gpt-5.6-terra', sub: 'OpenAI • Balanced Intelligence Cluster', latency: 870, cost: 0.021, intel: 35, icon: 'fa fa-cube text-green' },
        'openai|gpt-5.6-luna': { name: 'GPT-5.6 Luna', provider: 'openai', model: 'gpt-5.6-luna', sub: 'OpenAI • Ultra Fast & Affordable Cluster', latency: 770, cost: 0.010, intel: 27, icon: 'fa fa-cube text-green' },
        'openai|gpt-5.5': { name: 'GPT-5.5', provider: 'openai', model: 'gpt-5.5', sub: 'OpenAI • Deep Reasoning Flagship', latency: 1250, cost: 0.052, intel: 36, icon: 'fa fa-cube text-green' },
        'openai|chatgpt-4o-latest': { name: 'GPT Instant (latest)', provider: 'openai', model: 'chatgpt-4o-latest', sub: 'OpenAI • Dynamic Realtime Checkpoint', latency: 700, cost: 0.052, intel: 99, icon: 'fa fa-cube text-green' },
        'openai|gpt-5.4': { name: 'GPT-5.4', provider: 'openai', model: 'gpt-5.4', sub: 'OpenAI • Advanced Conversational Model', latency: 800, cost: 0.026, intel: 28, icon: 'fa fa-cube text-green' },
        'openai|gpt-5.4-mini': { name: 'GPT-5.4 Mini', provider: 'openai', model: 'gpt-5.4-mini', sub: 'OpenAI • Fast & Compact Model', latency: 720, cost: 0.010, intel: 17, icon: 'fa fa-cube text-green' },
        'openai|gpt-5.4-nano': { name: 'GPT-5.4 Nano', provider: 'openai', model: 'gpt-5.4-nano', sub: 'OpenAI • Ultralight Conversational', latency: 1160, cost: 0.010, intel: 18, icon: 'fa fa-cube text-green' },
        'openai|gpt-5.2': { name: 'GPT-5.2', provider: 'openai', model: 'gpt-5.2', sub: 'OpenAI • Frontier High Quality', latency: 830, cost: 0.019, intel: 27, icon: 'fa fa-cube text-green' },
        'openai|gpt-5.1': { name: 'GPT-5.1', provider: 'openai', model: 'gpt-5.1', sub: 'OpenAI • Fast Conversational Standard', latency: 810, cost: 0.013, intel: 21, icon: 'fa fa-cube text-green' },
        'openai|gpt-5': { name: 'GPT-5', provider: 'openai', model: 'gpt-5', sub: 'OpenAI • Next-Gen Baseline', latency: 840, cost: 0.013, intel: 17, icon: 'fa fa-cube text-green' },
        'openai|gpt-5-mini': { name: 'GPT-5 Mini', provider: 'openai', model: 'gpt-5-mini', sub: 'OpenAI • Compact Next-Gen', latency: 820, cost: 0.010, intel: 14, icon: 'fa fa-cube text-green' },
        'openai|gpt-5-nano': { name: 'GPT-5 Nano', provider: 'openai', model: 'gpt-5-nano', sub: 'OpenAI • Nano Streamlined', latency: 710, cost: 0.010, intel: 8, icon: 'fa fa-cube text-green' },
        'openai|gpt-4.1-mini': { name: 'GPT-4.1 Mini', provider: 'openai', model: 'gpt-4.1-mini', sub: 'OpenAI • Fast Efficient 4.1', latency: 600, cost: 0.010, intel: 15, icon: 'fa fa-cube text-green' },
        'openai|gpt-4.1-nano': { name: 'GPT-4.1 Nano', provider: 'openai', model: 'gpt-4.1-nano', sub: 'OpenAI • Lowest Latency 4.1', latency: 450, cost: 0.010, intel: 10, icon: 'fa fa-cube text-green' },
        'openai|gpt-4o-mini-cluster': { name: 'GPT-4o Mini Cluster', provider: 'openai', model: 'gpt-4o-mini-cluster', sub: 'OpenAI • Ultra-Low Latency Cluster', latency: 560, cost: 0.010, intel: 7, icon: 'fa fa-cube text-green' },
        'openai|gpt-4o-cluster': { name: 'GPT-4o Cluster', provider: 'openai', model: 'gpt-4o-cluster', sub: 'OpenAI • Dedicated 4o Cluster', latency: 670, cost: 0.045, intel: 11, icon: 'fa fa-cube text-green' },
        'openai|gpt-realtime-cluster': { name: 'GPT Realtime Cluster', provider: 'openai', model: 'gpt-realtime-cluster', sub: 'OpenAI • High-Speed Realtime', latency: 450, cost: 0.290, intel: 95, icon: 'fa fa-cube text-green' },
        'openai|gpt-realtime-mini': { name: 'GPT Realtime Mini', provider: 'openai', model: 'gpt-realtime-mini', sub: 'OpenAI • Realtime Mini Low Cost', latency: 400, cost: 0.060, intel: 90, icon: 'fa fa-cube text-green' },
        'openai|gpt-o3-cluster': { name: 'GPT o3 Cluster', provider: 'openai', model: 'gpt-o3-cluster', sub: 'OpenAI • Reasoning Cluster', latency: 1000, cost: 0.036, intel: 31, icon: 'fa fa-cube text-green' },
        'openai|gpt-4o-mini': { name: 'GPT-4o Mini', provider: 'openai', model: 'gpt-4o-mini', sub: 'OpenAI • Ultra-Fast & Cost Effective', latency: 320, cost: 0.004, intel: 88, icon: 'fa fa-cube text-green' },
        'openai|gpt-4o': { name: 'GPT-4o', provider: 'openai', model: 'gpt-4o', sub: 'OpenAI • Flagship Multimodal', latency: 620, cost: 0.022, intel: 99, icon: 'fa fa-cube text-green' },
        'openai|o3-mini': { name: 'o3-mini', provider: 'openai', model: 'o3-mini', sub: 'OpenAI • Next-Gen Fast Reasoning', latency: 550, cost: 0.012, intel: 97, icon: 'fa fa-cube text-green' },
        'openai|o1-mini': { name: 'o1-mini', provider: 'openai', model: 'o1-mini', sub: 'OpenAI • STEM & Coding Reasoning', latency: 750, cost: 0.015, intel: 95, icon: 'fa fa-cube text-green' },
        'openai|o1': { name: 'o1 (Deep Reasoning)', provider: 'openai', model: 'o1', sub: 'OpenAI • Deep Complex Reasoning', latency: 1200, cost: 0.080, intel: 100, icon: 'fa fa-cube text-green' },
        'anthropic|claude-3-5-sonnet-20241022': { name: 'Claude 3.5 Sonnet', provider: 'anthropic', model: 'claude-3-5-sonnet-20241022', sub: 'Anthropic • Deep Reasoning', latency: 750, cost: 0.030, intel: 98, icon: 'fa fa-diamond text-orange' },
        'deepseek|deepseek-chat': { name: 'DeepSeek V3', provider: 'deepseek', model: 'deepseek-chat', sub: 'DeepSeek • Low Cost & High Intel', latency: 280, cost: 0.003, intel: 90, icon: 'fa fa-star text-blue' }
    },
    voice: {
        'elevenlabs|PznTnBc8X6pvixs9UkQm': { name: 'PznTnBc8X6pvixs9UkQm (ElevenLabs)', provider: 'elevenlabs', id: 'PznTnBc8X6pvixs9UkQm', sub: 'ElevenLabs • Eleven Multilingual v2', latency: 810, cost: 0.036, humanness: 96, icon: 'fa fa-volume-up text-pink' },
        'elevenlabs|tMzxR2W7o3RLIY7zWBbG': { name: 'Júlia (ElevenLabs)', provider: 'elevenlabs', id: 'tMzxR2W7o3RLIY7zWBbG', sub: 'ElevenLabs • Natural Conversational PT-BR', latency: 350, cost: 0.036, humanness: 96, icon: 'fa fa-volume-up text-pink' },
        'cartesia|cartesia-pt-br-sofia': { name: 'Sofia (Cartesia Sonic)', provider: 'cartesia', id: 'cartesia-pt-br-sofia', sub: 'Cartesia Sonic • Natural PT-BR Female', latency: 90, cost: 0.020, humanness: 96, icon: 'fa fa-volume-up text-purple' },
        'cartesia|cartesia-pt-br-lucas': { name: 'Lucas (Cartesia Sonic)', provider: 'cartesia', id: 'cartesia-pt-br-lucas', sub: 'Cartesia Sonic • Professional PT-BR Male', latency: 90, cost: 0.020, humanness: 95, icon: 'fa fa-volume-up text-purple' },
        'elevenlabs|21m00Tcm4TlvDq8ikWAM': { name: 'Rachel (ElevenLabs)', provider: 'elevenlabs', id: '21m00Tcm4TlvDq8ikWAM', sub: 'ElevenLabs • Conversational Female', latency: 650, cost: 0.036, humanness: 92, icon: 'fa fa-volume-up text-pink' },
        'elevenlabs|EXAVITQu4vr4xnSDxMaL': { name: 'Sarah (ElevenLabs)', provider: 'elevenlabs', id: 'EXAVITQu4vr4xnSDxMaL', sub: 'ElevenLabs • Warm & Reassuring Female', latency: 650, cost: 0.036, humanness: 93, icon: 'fa fa-volume-up text-pink' },
        'elevenlabs|AZnzlk1XvdvUeBnXmlld': { name: 'Domi (ElevenLabs)', provider: 'elevenlabs', id: 'AZnzlk1XvdvUeBnXmlld', sub: 'ElevenLabs • Energetic Female', latency: 650, cost: 0.036, humanness: 91, icon: 'fa fa-volume-up text-pink' },
        'elevenlabs|pNInz6obpgDQGcFmaJgB': { name: 'Adam (ElevenLabs)', provider: 'elevenlabs', id: 'pNInz6obpgDQGcFmaJgB', sub: 'ElevenLabs • Deep & Professional Male', latency: 650, cost: 0.036, humanness: 94, icon: 'fa fa-volume-up text-pink' },
        'elevenlabs|TxGEqnHWrfWFTfGW9XjX': { name: 'Josh (ElevenLabs)', provider: 'elevenlabs', id: 'TxGEqnHWrfWFTfGW9XjX', sub: 'ElevenLabs • Young Conversational Male', latency: 650, cost: 0.036, humanness: 92, icon: 'fa fa-volume-up text-pink' },
        'elevenlabs|XrExE9yKIg1WjnnlVkGX': { name: 'Matilda (ElevenLabs)', provider: 'elevenlabs', id: 'XrExE9yKIg1WjnnlVkGX', sub: 'ElevenLabs • Expressive & Warm Female', latency: 650, cost: 0.036, humanness: 93, icon: 'fa fa-volume-up text-pink' },
        'openai|nova': { name: 'Nova (OpenAI)', provider: 'openai', id: 'nova', sub: 'OpenAI TTS • Friendly Female', latency: 350, cost: 0.015, humanness: 84, icon: 'fa fa-volume-up text-green' },
        'openai|alloy': { name: 'Alloy (OpenAI)', provider: 'openai', id: 'alloy', sub: 'OpenAI TTS • Neutral Male', latency: 350, cost: 0.015, humanness: 82, icon: 'fa fa-volume-up text-green' },
        'azure|pt-BR-FranciscaNeural': { name: 'Francisca (Azure Speech)', provider: 'azure', id: 'pt-BR-FranciscaNeural', sub: 'Microsoft Azure • Natural PT-BR Female', latency: 400, cost: 0.016, humanness: 90, icon: 'fa fa-windows text-info' },
        'azure|pt-BR-AntonioNeural': { name: 'Antonio (Azure Speech)', provider: 'azure', id: 'pt-BR-AntonioNeural', sub: 'Microsoft Azure • Professional PT-BR Male', latency: 400, cost: 0.016, humanness: 90, icon: 'fa fa-windows text-info' },
        'azure|pt-BR-ThalitaNeural': { name: 'Thalita (Azure Speech)', provider: 'azure', id: 'pt-BR-ThalitaNeural', sub: 'Microsoft Azure • Expressive PT-BR Female', latency: 400, cost: 0.016, humanness: 91, icon: 'fa fa-windows text-info' }
    }
};

var currentTools = <?=json_encode($defaultTools)?>;
if (!Array.isArray(currentTools)) currentTools = [];

function syncToolsHiddenInput() {
    $('#input_tools_json').val(JSON.stringify(currentTools));
}

function renderToolsTable() {
    var $tbody = $('#tools_table_tbody');
    $tbody.empty();

    if (!currentTools || currentTools.length === 0) {
        $tbody.html('<tr><td colspan="5" style="text-align:center; color:var(--ddm-text-muted); padding:36px;"><i class="fa fa-wrench" style="font-size:24px; margin-bottom:8px; display:block;"></i>Nenhuma ferramenta cadastrada. Clique em "Criar Nova Ferramenta" para adicionar.</td></tr>');
        return;
    }

    currentTools.forEach(function(tool) {
        var isChecked = tool.enabled !== false ? 'checked' : '';
        var typeBadgeClass = 'ddm-badge-orange';
        var iconClass = 'fa-wrench';
        var iconColor = '#F97316';
        var iconBg = '#FFF7ED';

        if (tool.type === 'Transfer call') {
            typeBadgeClass = 'ddm-badge-active';
            iconClass = 'fa-phone-square';
            iconColor = '#10B981';
            iconBg = '#ECFDF5';
        } else if (tool.type === 'End call') {
            typeBadgeClass = 'ddm-badge-inactive';
            iconClass = 'fa-phone-slash';
            iconColor = '#EF4444';
            iconBg = '#FEF2F2';
        } else if (tool.type === 'Voicemail tool') {
            typeBadgeClass = 'ddm-badge-orange';
            iconClass = 'fa-envelope-open';
            iconColor = '#F59E0B';
            iconBg = '#FFFBEB';
        }

        var html = '<tr>' +
            '<td>' +
                '<label class="ddm-switch vapi-switch">' +
                    '<input type="checkbox" class="tool-toggle" data-tool-id="' + tool.id + '" ' + isChecked + ' />' +
                    '<span class="ddm-slider vapi-slider"></span>' +
                '</label>' +
            '</td>' +
            '<td>' +
                '<div style="display:flex; align-items:center;">' +
                    '<div class="ddm-tool-icon-box" style="background:' + iconBg + '; color:' + iconColor + ';">' +
                        '<i class="fa ' + iconClass + '"></i>' +
                    '</div>' +
                    '<div>' +
                        '<div class="ddm-tool-name">' + (tool.name || tool.id) + '</div>' +
                        '<div style="font-size:11px; color:var(--ddm-text-muted);">v' + (tool.version || 'Latest') + '</div>' +
                    '</div>' +
                '</div>' +
            '</td>' +
            '<td><span class="ddm-badge ' + typeBadgeClass + '">' + (tool.type || 'Custom tool') + '</span></td>' +
            '<td><div class="ddm-tool-desc">' + (tool.description || 'Sem descrição') + '</div></td>' +
            '<td style="text-align: right;">' +
                '<button type="button" class="ddm-btn ddm-btn-secondary ddm-btn-xs btn-edit-tool" data-tool-id="' + tool.id + '" title="Editar"><i class="fa fa-pencil"></i></button> ' +
                '<button type="button" class="ddm-btn ddm-btn-secondary ddm-btn-xs btn-delete-tool" data-tool-id="' + tool.id + '" title="Remover" style="color:#EF4444 !important;"><i class="fa fa-trash"></i></button>' +
            '</td>' +
        '</tr>';

        $tbody.append(html);
    });
}

function openToolDrawer(mode, toolObj) {
    $('#tool_drawer_mode').val(mode);
    if (mode === 'edit' && toolObj) {
        $('#tool_drawer_title').html('<i class="fa fa-pencil text-blue"></i> Editar Ferramenta: ' + toolObj.name);
        $('#tool_edit_orig_id').val(toolObj.id);
        $('#tool_input_name').val(toolObj.name || toolObj.id);
        $('#tool_input_type').val(toolObj.type || 'Custom tool');
        $('#tool_input_version').val(toolObj.version || 'Latest');
        $('#tool_input_desc').val(toolObj.description || '');
        $('#tool_input_url').val(toolObj.url || '');
        $('#tool_input_method').val(toolObj.method || 'POST');
        $('#tool_input_params').val(toolObj.parameters ? JSON.stringify(toolObj.parameters, null, 2) : '{}');
        $('#tool_input_enabled').prop('checked', toolObj.enabled !== false);
        $('#tool_quick_template_select').val('');
    } else {
        $('#tool_drawer_title').html('<i class="fa fa-wrench text-orange"></i> Criar Nova Ferramenta');
        $('#tool_edit_orig_id').val('');
        $('#tool_input_name').val('');
        $('#tool_input_type').val('Custom tool');
        $('#tool_input_version').val('Latest');
        $('#tool_input_desc').val('');
        $('#tool_input_url').val('');
        $('#tool_input_method').val('POST');
        $('#tool_input_params').val('{}');
        $('#tool_input_enabled').prop('checked', true);
        $('#tool_quick_template_select').val('');
    }
    $('#modal_tool_drawer').fadeIn(200).css('display', 'flex');
}

function updatePipelineUI() {
    var sttKey = $('#input_stt_provider').val() + '|' + $('#input_stt_model').val();
    var llmKey = $('#input_llm_provider').val() + '|' + $('#input_llm_model').val();
    var curProv = $('#input_voice_provider').val() || 'cartesia';
    var curVoiceId = $('#input_voice_id').val() || 'cartesia-pt-br-sofia';
    var voiceKey = curProv + '|' + curVoiceId;

    var stt = VAPI_CONFIG.stt[sttKey] || { name: $('#input_stt_model').val(), sub: $('#input_stt_provider').val(), latency: 120, cost: 0.005, accuracy: '98.0%' };
    var llm = VAPI_CONFIG.llm[llmKey] || { name: $('#input_llm_model').val(), sub: $('#input_llm_provider').val(), latency: 250, cost: 0.008, intel: 85 };
    
    var isKnownVoice = !!VAPI_CONFIG.voice[voiceKey];
    var isCustomTab = $('#tab_custom_voice_id').hasClass('active') || !isKnownVoice;
    
    var voiceLatency = 150;
    var voiceCost = 0.020;
    if (curProv === 'elevenlabs') { voiceLatency = 650; voiceCost = 0.036; }
    else if (curProv === 'cartesia') { voiceLatency = 90; voiceCost = 0.020; }
    else if (curProv === 'openai') { voiceLatency = 350; voiceCost = 0.015; }
    else if (curProv === 'azure') { voiceLatency = 400; voiceCost = 0.016; }

    var voice = VAPI_CONFIG.voice[voiceKey];
    if (isCustomTab || !voice) {
        var shortId = curVoiceId.length > 14 ? (curVoiceId.substring(0, 14) + '...') : curVoiceId;
        voice = { 
            name: 'Custom Voice (' + shortId + ')', 
            sub: '<span style="color:#F97316; font-weight:700;">' + curProv.toUpperCase() + '</span> • ID: <span style="font-family:monospace; font-weight:bold;">' + curVoiceId + '</span>', 
            latency: voiceLatency, 
            cost: voiceCost, 
            humanness: 95 
        };
    }

    // 1. Update STT Card
    $('#disp_stt_title').text(stt.name);
    $('#disp_stt_sub').html('<i class="fa fa-globe text-green"></i> ' + stt.sub);
    $('#disp_stt_lat').text(stt.latency + 'ms');
    $('#disp_stt_cost').text('$' + stt.cost.toFixed(3) + '/min');
    $('#disp_stt_acc').text(stt.accuracy || '98.4%');

    // 2. Update LLM Card
    $('#disp_llm_title').text(llm.name);
    $('#disp_llm_sub').html('<i class="fa fa-bolt text-blue"></i> ' + llm.sub);
    $('#disp_llm_lat').text(llm.latency + 'ms');
    $('#disp_llm_cost').text('$' + llm.cost.toFixed(3) + '/min');
    $('#disp_llm_intel').text(llm.intel || 90);

    // 3. Update Voice Card
    $('#disp_voice_title').text(voice.name);
    $('#disp_voice_sub').html('<i class="fa fa-volume-up text-purple"></i> ' + voice.sub);
    $('#disp_voice_lat').text(voice.latency + 'ms');
    $('#disp_voice_cost').text('$' + voice.cost.toFixed(3) + '/min');
    $('#disp_voice_hum').text(voice.humanness || 95);

    // 4. Update Telemetry Totals
    var totalLat = stt.latency + llm.latency + voice.latency;
    var totalCost = stt.cost + llm.cost + voice.cost;

    $('#disp_total_latency').html('~' + totalLat + '<span class="ddm-telemetry-unit">ms</span>');
    $('#disp_total_cost').html('~$' + totalCost.toFixed(3) + '<span class="ddm-telemetry-unit">/min</span>');

    // Latency Progress Bar
    var latSttPct = Math.round((stt.latency / totalLat) * 100);
    var latLlmPct = Math.round((llm.latency / totalLat) * 100);
    var latTtsPct = 100 - latSttPct - latLlmPct;
    $('#lat_bar_stt').css('width', latSttPct + '%');
    $('#lat_bar_llm').css('width', latLlmPct + '%');
    $('#lat_bar_tts').css('width', latTtsPct + '%');

    // Cost Progress Bar
    var costSttPct = Math.round((stt.cost / totalCost) * 100);
    var costLlmPct = Math.round((llm.cost / totalCost) * 100);
    var costTtsPct = 100 - costSttPct - costLlmPct;
    $('#cost_bar_stt').css('width', costSttPct + '%');
    $('#cost_bar_llm').css('width', costLlmPct + '%');
    $('#cost_bar_tts').css('width', costTtsPct + '%');

    // Drawer Selects & Tabs Sync
    $('#modal_stt_provider_select').val(sttKey);
    $('#modal_llm_model_select').val(llmKey);
    $('#modal_voice_provider_select').val(curProv);

    if (isKnownVoice) {
        if (!$('#tab_custom_voice_id').hasClass('active')) {
            $('#tab_voice_library').addClass('active');
            $('#tab_custom_voice_id').removeClass('active');
            $('#voice_library_view').show();
            $('#custom_voice_id_view').hide();
            $('#modal_voice_id_select').val(voiceKey);
        }
    } else {
        $('#tab_custom_voice_id').addClass('active');
        $('#tab_voice_library').removeClass('active');
        $('#voice_library_view').hide();
        $('#custom_voice_id_view').show();
        $('#modal_custom_voice_id_input').val(curVoiceId);
    }
}

$(document).ready(function() {
    renderToolsTable();
    updatePipelineUI();

    $('.ddm-tab, .vapi-tab').click(function(e) {
        e.preventDefault();
        $('.ddm-tab, .vapi-tab').removeClass('active');
        $(this).addClass('active');
        var target = $(this).data('tab');
        $('.vapi-tab-view').hide();
        $('#view_' + target).fadeIn(150);
    });

    $('#card_transcriber').click(function() { $('#modal_transcriber').fadeIn(200).css('display', 'flex'); });
    $('#card_model').click(function() { $('#modal_model').fadeIn(200).css('display', 'flex'); });
    $('#card_voice').click(function() { $('#modal_voice').fadeIn(200).css('display', 'flex'); });

    $('.btn-close-drawer, .ddm-drawer-backdrop, .vapi-modal-backdrop').click(function(e) {
        if (e.target === this || $(this).hasClass('btn-close-drawer') || $(this).hasClass('ddm-drawer-close')) {
            $('.ddm-drawer-backdrop, .vapi-modal-backdrop').fadeOut(200);
        }
    });

    $('#adv_stt_toggle').click(function() { $('#adv_stt_body').slideToggle(200); });
    $('#adv_llm_toggle').click(function() { $('#adv_llm_body').slideToggle(200); });
    $('#adv_voice_toggle').click(function() { $('#adv_voice_body').slideToggle(200); });

    $('.ddm-accordion-header[data-target], .vapi-accordion-header[data-target]').click(function() {
        var target = $(this).data('target');
        $(target).slideToggle(200);
        var $icon = $('i.fa-chevron-down, i.fa-chevron-up', this);
        if ($icon.hasClass('fa-chevron-down')) {
            $icon.removeClass('fa-chevron-down').addClass('fa-chevron-up');
        } else {
            $icon.removeClass('fa-chevron-up').addClass('fa-chevron-down');
        }
    });

    $('#modal_stt_provider_select').change(function() {
        var parts = $(this).val().split('|');
        $('#input_stt_provider').val(parts[0]);
        $('#input_stt_model').val(parts[1]);
        updatePipelineUI();
    });

    $('#modal_stt_language_select').change(function() { $('#input_stt_language').val($(this).val()); });
    $('#modal_turn_taking').change(function() { $('#input_intelligent_turn_taking').val($(this).is(':checked') ? 'Y' : 'N'); });
    $('#modal_denoising').change(function() { $('#input_background_denoising').val($(this).is(':checked') ? 'Y' : 'N'); });

    $('#modal_silence_slider').on('input', function() {
        var val = $(this).val();
        $('#disp_silence_timeout_badge').text(val + ' ms');
        $('#input_silence_timeout_ms').val(val);
    });

    $('#modal_stt_fallback_select').change(function() { $('#input_stt_fallback_provider').val($(this).val()); });

    $('#modal_llm_model_select').change(function() {
        var parts = $(this).val().split('|');
        $('#input_llm_provider').val(parts[0]);
        $('#input_llm_model').val(parts[1]);
        updatePipelineUI();
    });

    $('#modal_temp_slider').on('input', function() {
        var val = $(this).val();
        $('#disp_temp_badge').text(val);
        $('#input_temperature').val(val);
    });

    $('#modal_max_tokens_input').on('input', function() { $('#input_max_tokens').val($(this).val()); });
    $('#modal_cache_select').change(function() { $('#input_prompt_cache_retention').val($(this).val()); });
    $('#modal_tool_strict_select').change(function() { $('#input_tool_strict_compatibility').val($(this).val()); });

    $('#modal_voice_provider_select').change(function() {
        var prov = $(this).val();
        $('#input_voice_provider').val(prov);
        
        var isCustom = $('#tab_custom_voice_id').hasClass('active') || $('#custom_voice_id_view').is(':visible');
        if (isCustom) {
            var customVal = $('#modal_custom_voice_id_input').val().trim();
            if (customVal) {
                $('#input_voice_id').val(customVal);
                $('#input_voice_name').val('Custom (' + (customVal.length > 12 ? customVal.substring(0, 12) + '...' : customVal) + ')');
            }
        } else {
            var firstMatch = Object.keys(VAPI_CONFIG.voice).find(function(k) { return k.startsWith(prov + '|'); });
            if (firstMatch) {
                var v = VAPI_CONFIG.voice[firstMatch];
                $('#input_voice_id').val(v.id);
                $('#input_voice_name').val(v.name);
                $('#modal_voice_id_select').val(firstMatch);
            }
        }
        updatePipelineUI();
    });

    $('#modal_voice_id_select').change(function() {
        var key = $(this).val();
        var v = VAPI_CONFIG.voice[key];
        if (v) {
            $('#input_voice_provider').val(v.provider);
            $('#input_voice_id').val(v.id);
            $('#input_voice_name').val(v.name);
            $('#modal_voice_provider_select').val(v.provider);
            updatePipelineUI();
        }
    });

    $('#modal_custom_voice_id_input').on('input change keyup paste', function() {
        var prov = $('#modal_voice_provider_select').val() || 'elevenlabs';
        $('#input_voice_provider').val(prov);
        var val = $(this).val().trim();
        if (val) {
            $('#input_voice_id').val(val);
            $('#input_voice_name').val('Custom (' + (val.length > 12 ? val.substring(0, 12) + '...' : val) + ')');
        } else {
            $('#input_voice_id').val('');
            $('#input_voice_name').val('');
        }
        updatePipelineUI();
    });

    $('#modal_speed_slider').on('input', function() {
        var val = $(this).val();
        $('#disp_speed_badge').text(val + 'x');
        $('#input_voice_speed').val(val);
    });

    $('#modal_stability_slider').on('input', function() {
        $('#disp_stability_badge').text($(this).val());
        $('#input_voice_stability').val($(this).val());
    });

    $('#modal_clarity_slider').on('input', function() {
        $('#disp_clarity_badge').text($(this).val());
        $('#input_voice_clarity').val($(this).val());
    });

    $('#modal_style_slider').on('input', function() {
        $('#disp_style_badge').text($(this).val());
        $('#input_voice_style_exaggeration').val($(this).val());
    });

    $('#modal_opt_lat_slider').on('input', function() {
        $('#disp_opt_lat_badge').text($(this).val());
        $('#input_voice_optimize_latency').val($(this).val());
    });

    $('#bg_sound_pills .ddm-pill-opt, #bg_sound_pills .vapi-pill-opt').click(function() {
        $('#bg_sound_pills .ddm-pill-opt, #bg_sound_pills .vapi-pill-opt').removeClass('active');
        $(this).addClass('active');
        var bgsound = $(this).data('bgsound');
        $('#input_background_sound').val(bgsound);
        if (bgsound === 'off') {
            $('#bg_volume_group').slideUp(150);
        } else {
            $('#bg_volume_group').slideDown(150);
        }
    });

    $('#modal_bg_vol_slider').on('input', function() {
        var vol = parseFloat($(this).val());
        var pct = Math.round(vol * 100) + '%';
        $('#modal_bg_vol_val').text(pct);
        $('#input_background_sound_volume').val(vol.toFixed(2));
    });

    $('#tab_voice_library').click(function() {
        $('.ddm-pill-opt, .vapi-pill-opt', $(this).parent()).removeClass('active');
        $(this).addClass('active');
        $('#voice_library_view').show();
        $('#custom_voice_id_view').hide();

        var key = $('#modal_voice_id_select').val();
        if (!key) {
            var prov = $('#input_voice_provider').val() || 'cartesia';
            key = Object.keys(VAPI_CONFIG.voice).find(function(k) { return k.startsWith(prov + '|'); }) || 'cartesia|cartesia-pt-br-sofia';
            $('#modal_voice_id_select').val(key);
        }
        var v = VAPI_CONFIG.voice[key];
        if (v) {
            $('#input_voice_provider').val(v.provider);
            $('#input_voice_id').val(v.id);
            $('#input_voice_name').val(v.name);
            $('#modal_voice_provider_select').val(v.provider);
        }
        updatePipelineUI();
    });

    $('#tab_custom_voice_id').click(function() {
        $('.ddm-pill-opt, .vapi-pill-opt', $(this).parent()).removeClass('active');
        $(this).addClass('active');
        $('#voice_library_view').hide();
        $('#custom_voice_id_view').show();

        var prov = $('#modal_voice_provider_select').val() || 'elevenlabs';
        $('#input_voice_provider').val(prov);

        var customVal = $('#modal_custom_voice_id_input').val().trim();
        if (customVal) {
            $('#input_voice_id').val(customVal);
            $('#input_voice_name').val('Custom (' + (customVal.length > 12 ? customVal.substring(0, 12) + '...' : customVal) + ')');
        }
        updatePipelineUI();
    });

    $('#select_first_message_mode').change(function() { $('#input_first_message_mode').val($(this).val()); });
    $('#select_agent_status').change(function() {
        var val = $(this).val();
        $('#input_status').val(val);
        $('#badge_status_indicator').text(val === 'Y' ? 'Ativo' : 'Inativo');
    });

    $('.ddm-preset-pill, .vapi-preset-pill').click(function() {
        $('.ddm-preset-pill, .vapi-preset-pill').removeClass('active');
        $(this).addClass('active');
        var preset = $(this).data('preset');
        $('#input_model_preset').val(preset);

        if (preset === 'balanced') {
            $('#input_stt_provider').val('deepgram');
            $('#input_stt_model').val('nova-2');
            $('#input_llm_provider').val('groq');
            $('#input_llm_model').val('llama-3.3-70b-versatile');
            $('#input_voice_provider').val('cartesia');
            $('#input_voice_id').val('cartesia-pt-br-sofia');
            $('#input_voice_name').val('Sofia (Cartesia Sonic)');
        } else if (preset === 'ultra_fast') {
            $('#input_stt_provider').val('groq');
            $('#input_stt_model').val('whisper-large-v3-turbo');
            $('#input_llm_provider').val('groq');
            $('#input_llm_model').val('llama-3.1-8b-instant');
            $('#input_voice_provider').val('cartesia');
            $('#input_voice_id').val('cartesia-pt-br-sofia');
            $('#input_voice_name').val('Sofia (Cartesia Sonic)');
        } else if (preset === 'high_intelligence') {
            $('#input_stt_provider').val('deepgram');
            $('#input_stt_model').val('nova-2');
            $('#input_llm_provider').val('openai');
            $('#input_llm_model').val('gpt-4o');
            $('#input_voice_provider').val('cartesia');
            $('#input_voice_id').val('cartesia-pt-br-sofia');
            $('#input_voice_name').val('Sofia (Cartesia Sonic)');
        } else if (preset === 'cost_saver') {
            $('#input_stt_provider').val('groq');
            $('#input_stt_model').val('whisper-large-v3-turbo');
            $('#input_llm_provider').val('deepseek');
            $('#input_llm_model').val('deepseek-chat');
            $('#input_voice_provider').val('openai');
            $('#input_voice_id').val('nova');
            $('#input_voice_name').val('Nova (OpenAI)');
        }
        updatePipelineUI();
    });

    $('#btn_template_sales').click(function() {
        $('#textarea_prompt').val(
            "# PERSONA E OBJETIVO\n" +
            "Você é a Sofia, consultora comercial especializada da {{empresa}}.\n" +
            "Seu objetivo é apresentar nossa solução de forma clara, cordial e dinâmica para o cliente {{customer_name}}, entender o momento dele e agendar uma demonstração.\n\n" +
            "# DIRETRIZES DA CONVERSA\n" +
            "1. Fale sempre em no máximo 2 frases curtas e naturais por turno.\n" +
            "2. Ouça com atenção e faça perguntas abertas para qualificar o interesse.\n" +
            "3. Se houver interesse em prosseguir, use a ferramenta transfer_call para transferir ao especialista."
        );
    });

    $('#btn_template_support').click(function() {
        $('#textarea_prompt').val(
            "# PERSONA E OBJETIVO\n" +
            "Você é Júlia, assistente virtual de atendimento ao cliente, suporte e SAC da {{empresa}}.\n" +
            "Seu objetivo é acolher o cliente {{customer_name}}, identificar a necessidade dele e resolver com agilidade e cordialidade.\n\n" +
            "# DIRETRIZES DA CONVERSA\n" +
            "1. Seja ágil, empática e prestativa.\n" +
            "2. Mantenha as respostas objetivas e focadas na solução.\n" +
            "3. Quando o atendimento estiver concluído ou o cliente se despedir, encerre cordialmente usando a ferramenta end_call."
        );
    });

    $('#btn_template_qualify').click(function() {
        $('#textarea_prompt').val(
            "# PERSONA E OBJETIVO\n" +
            "Você é o Lucas, especialista em pré-atendimento e qualificação cadastral (SDR) da {{empresa}}.\n" +
            "Seu objetivo é confirmar os 3 primeiros dígitos do CPF do cliente {{customer_name}} para validar a elegibilidade.\n\n" +
            "# DIRETRIZES DA CONVERSA\n" +
            "1. Seja profissional e direto.\n" +
            "2. Quando o cliente informar os números, acione a ferramenta capturar_cpf.\n" +
            "3. Se qualificado, direcione para transferência com transfer_call."
        );
    });

    $('#btn_add_custom_tool').click(function() { openToolDrawer('add'); });

    $(document).on('click', '.btn-edit-tool', function(e) {
        e.preventDefault();
        e.stopPropagation();
        var toolId = String($(this).data('tool-id'));
        var tool = currentTools.find(function(t) { return String(t.id) === toolId; });
        if (tool) { openToolDrawer('edit', tool); }
    });

    $(document).on('click', '.btn-delete-tool', function(e) {
        e.preventDefault();
        e.stopPropagation();
        var toolId = String($(this).data('tool-id'));
        if (confirm('Tem certeza que deseja remover esta ferramenta (' + toolId + ')?')) {
            currentTools = currentTools.filter(function(t) { return String(t.id) !== toolId; });
            syncToolsHiddenInput();
            renderToolsTable();
        }
    });

    $(document).on('change', '.tool-toggle', function() {
        var toolId = String($(this).data('tool-id'));
        var isChecked = $(this).is(':checked');
        for (var i = 0; i < currentTools.length; i++) {
            if (String(currentTools[i].id) === toolId) {
                currentTools[i].enabled = isChecked;
                break;
            }
        }
        syncToolsHiddenInput();
    });

    $('#btn_tool_format_json').click(function() {
        var raw = $('#tool_input_params').val().trim();
        if (!raw) return;
        try {
            var parsed = JSON.parse(raw);
            $('#tool_input_params').val(JSON.stringify(parsed, null, 2));
        } catch(err) {
            alert('JSON inválido: ' + err.message);
        }
    });

    $('#tool_quick_template_select').change(function() {
        var val = $(this).val();
        if (val === 'custom_webhook') {
            $('#tool_input_name').val('consultar_api');
            $('#tool_input_type').val('Custom tool');
            $('#tool_input_desc').val('Envia os parâmetros coletados durante a chamada para um endpoint webhook externo e injeta a resposta na conversa.');
            $('#tool_input_url').val('https://api.empresa.com.br/v1/webhook');
            $('#tool_input_method').val('POST');
            $('#tool_input_params').val(JSON.stringify({ type: "object", properties: { parametro: { type: "string", description: "Descrição do parâmetro" } }, required: ["parametro"] }, null, 2));
        } else if (val === 'end_call') {
            $('#tool_input_name').val('end_call');
            $('#tool_input_type').val('End call');
            $('#tool_input_desc').val('Encerra a chamada telefônica imediatamente após o cliente se despedir ou concluir o atendimento.');
            $('#tool_input_params').val(JSON.stringify({ type: "object", properties: { reason: { type: "string", description: "Motivo da finalização da chamada" } } }, null, 2));
        } else if (val === 'transfer_call') {
            $('#tool_input_name').val('transfer_call');
            $('#tool_input_type').val('Transfer call');
            $('#tool_input_desc').val('Transfere a ligação em andamento para um ramal ou atendente humano especializado.');
            $('#tool_input_params').val(JSON.stringify({ type: "object", properties: { destination: { type: "string", description: "Ramal ou fila de destino (Ex: 8300)" }, summary: { type: "string", description: "Resumo do que o cliente precisa para o atendente humano" } } }, null, 2));
        } else if (val === 'voicemail_tool') {
            $('#tool_input_name').val('voicemail_tool');
            $('#tool_input_type').val('Voicemail tool');
            $('#tool_input_desc').val('Acionado automaticamente ao detectar caixa postal, secretária eletrônica ou mensagem gravada.');
            $('#tool_input_params').val(JSON.stringify({ type: "object", properties: {} }, null, 2));
        } else if (val === 'capturar_cpf') {
            $('#tool_input_name').val('capturar_cpf');
            $('#tool_input_type').val('Custom tool');
            $('#tool_input_desc').val('Armazena e valida o número de CPF informado pelo cliente para prosseguir com a consulta.');
            $('#tool_input_params').val(JSON.stringify({ type: "object", properties: { cpf: { type: "string", description: "CPF com 11 dígitos numéricos" } }, required: ["cpf"] }, null, 2));
        }
    });

    $('#btn_save_tool_drawer').click(function() {
        var mode = $('#tool_drawer_mode').val();
        var origId = $('#tool_edit_orig_id').val();
        var name = $('#tool_input_name').val().trim().replace(/[^a-zA-Z0-9_-]/g, '_');
        var type = $('#tool_input_type').val();
        var version = $('#tool_input_version').val().trim() || 'Latest';
        var desc = $('#tool_input_desc').val().trim();
        var url = $('#tool_input_url').val().trim();
        var method = $('#tool_input_method').val();
        var rawParams = $('#tool_input_params').val().trim();
        var enabled = $('#tool_input_enabled').is(':checked');

        if (!name || !desc) { alert('Nome e Descrição são obrigatórios.'); return; }
        var parsedParams = {};
        if (rawParams) {
            try { parsedParams = JSON.parse(rawParams); } catch(e) { alert('JSON inválido.'); return; }
        }
        var toolObj = { id: name, name: name, type: type, version: version, description: desc, url: url, method: method, parameters: parsedParams, enabled: enabled };

        if (mode === 'edit' && origId) {
            var found = false;
            for (var i = 0; i < currentTools.length; i++) {
                if (String(currentTools[i].id) === String(origId)) { currentTools[i] = toolObj; found = true; break; }
            }
            if (!found) currentTools.push(toolObj);
        } else {
            var existingIdx = -1;
            for (var j = 0; j < currentTools.length; j++) {
                if (String(currentTools[j].id) === name) { existingIdx = j; break; }
            }
            if (existingIdx !== -1) {
                if (!confirm('Uma ferramenta com o identificador "' + name + '" já existe. Deseja sobrescrevê-la?')) {
                    return;
                }
                currentTools[existingIdx] = toolObj;
            } else {
                currentTools.push(toolObj);
            }
        }

        syncToolsHiddenInput();
        renderToolsTable();
        $('#modal_tool_drawer').fadeOut(200);
    });

    $('#form_add_vapi_agent').submit(function(e) {
        e.preventDefault();

        if ($('#tab_custom_voice_id').hasClass('active') || $('#custom_voice_id_view').is(':visible')) {
            var customVal = $('#modal_custom_voice_id_input').val().trim();
            if (customVal) {
                $('#input_voice_id').val(customVal);
                if (!$('#input_voice_name').val() || $('#input_voice_name').val().startsWith('Sofia') || $('#input_voice_name').val().startsWith('Lucas')) {
                    $('#input_voice_name').val('Custom (' + (customVal.length > 12 ? customVal.substring(0, 12) + '...' : customVal) + ')');
                }
            }
        }

        var $btn = $('#btn_save_agent');
        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Criando...');

        syncToolsHiddenInput();
        var formData = $(this).serialize();

        $.post('php/SaveAIAgent.php', formData, function(res) {
            $btn.prop('disabled', false).html('<i class="fa fa-save"></i> Criar e Publicar Agente');
            if (res.status == 1) {
                alert("✨ Agente de voz criado com sucesso no Dialog DDM!");
                window.location.href = 'edit_ai_agent.php?id=' + res.agent_id;
            } else {
                alert(res.message || 'Erro ao criar agente.');
            }
        }, 'json').fail(function() {
            $btn.prop('disabled', false).html('<i class="fa fa-save"></i> Criar e Publicar Agente');
            alert('Erro de comunicação com o servidor.');
        });
    });
});

function insertVar(varName) {
    var textarea = document.getElementById('textarea_prompt');
    var start = textarea.selectionStart;
    var end = textarea.selectionEnd;
    var text = textarea.value;
    textarea.value = text.substring(0, start) + varName + text.substring(end);
    textarea.focus();
    textarea.selectionEnd = start + varName.length;
}
</script>
</body>
</html>
