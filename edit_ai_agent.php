<?php
/**
 * @file        edit_ai_agent.php
 * @brief       Vapi-Style Voice AI Agent Builder & Manager
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

$agent_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$agent = $aiHandler->getAgentById($agent_id);

if (!$agent) {
    header("location: ai_agents.php");
    exit;
}

$llms = $aiHandler->getAvailableLLMs();
$voices = $aiHandler->getAvailableVoices();
$stts = $aiHandler->getAvailableSTTs();
$defaultTools = $aiHandler->getDefaultTools();

$agentTools = !empty($agent['tools_json']) ? json_decode($agent['tools_json'], true) : $defaultTools;
if (!is_array($agentTools)) $agentTools = $defaultTools;

// Defaults for new Vapi fields
$first_message_mode = !empty($agent['first_message_mode']) ? $agent['first_message_mode'] : 'assistant_speaks_first';
$model_preset = !empty($agent['model_preset']) ? $agent['model_preset'] : 'balanced';
$silence_timeout_ms = !empty($agent['silence_timeout_ms']) ? (int)$agent['silence_timeout_ms'] : 500;
$intelligent_turn_taking = isset($agent['intelligent_turn_taking']) ? $agent['intelligent_turn_taking'] : 'Y';
$background_denoising = isset($agent['background_denoising']) ? $agent['background_denoising'] : 'Y';
$background_sound = !empty($agent['background_sound']) ? $agent['background_sound'] : 'off';
$voice_speed = !empty($agent['voice_speed']) ? (float)$agent['voice_speed'] : 1.0;
$voice_stability = !empty($agent['voice_stability']) ? (float)$agent['voice_stability'] : 0.7;
$voice_clarity = !empty($agent['voice_clarity']) ? (float)$agent['voice_clarity'] : 0.6;
$voice_style_exaggeration = !empty($agent['voice_style_exaggeration']) ? (float)$agent['voice_style_exaggeration'] : 0.2;
$voice_optimize_latency = isset($agent['voice_optimize_latency']) ? (int)$agent['voice_optimize_latency'] : 1;
$max_tokens = !empty($agent['max_tokens']) ? (int)$agent['max_tokens'] : 250;
$prompt_cache_retention = !empty($agent['prompt_cache_retention']) ? $agent['prompt_cache_retention'] : 'in_memory';
$tool_strict_compatibility = isset($agent['tool_strict_compatibility']) ? $agent['tool_strict_compatibility'] : 'N';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Dial GO - <?=htmlspecialchars($agent['agent_name'])?> (Assistant Builder)</title>
    <meta content='width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no' name='viewport'>

    <?php 
        print $ui->standardizedThemeCSS(); 
        print $ui->creamyThemeCSS();
    ?>
    <link href="css/style.css" rel="stylesheet" type="text/css" />
    
    <style>
        /* VAPI DARK THEME & LAYOUT REPLICATION */
        .vapi-container {
            background-color: #0d0f12;
            color: #f1f5f9;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            min-height: calc(100vh - 50px);
            padding: 24px 32px;
        }

        /* Top Header */
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
            color: #38bdf8;
            font-size: 18px;
            border: 1px solid #334155;
        }
        .vapi-agent-name-input {
            background: transparent;
            border: 1px solid transparent;
            color: #ffffff;
            font-size: 20px;
            font-weight: 700;
            padding: 4px 8px;
            border-radius: 6px;
            transition: all 0.2s;
        }
        .vapi-agent-name-input:hover, .vapi-agent-name-input:focus {
            background: #1a1d24;
            border-color: #334155;
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
        .vapi-badge-published {
            background: rgba(16, 185, 129, 0.15);
            color: #10b981;
            border-color: rgba(16, 185, 129, 0.3);
        }

        /* Top Actions */
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
        .vapi-btn-talk {
            background: #064e3b;
            color: #34d399;
            border-color: #059669;
        }
        .vapi-btn-talk:hover {
            background: #047857;
            color: #fff;
        }

        /* Tab Navigation */
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
        .vapi-tab:hover {
            color: #f1f5f9;
        }
        .vapi-tab.active {
            color: #10b981;
            border-bottom-color: #10b981;
        }

        /* Telemetry Section */
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

        /* Presets Bar */
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
        .vapi-preset-pill:hover {
            border-color: #475569;
            color: #fff;
        }
        .vapi-preset-pill.active {
            background: #1e293b;
            border-color: #38bdf8;
            color: #38bdf8;
        }

        /* 3 Pipeline Cards */
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
        .vapi-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
        }
        .vapi-dot-stt { background: #10b981; box-shadow: 0 0 8px #10b981; }
        .vapi-dot-llm { background: #3b82f6; box-shadow: 0 0 8px #3b82f6; }
        .vapi-dot-tts { background: #ec4899; box-shadow: 0 0 8px #ec4899; }
        
        .vapi-card-edit-btn {
            background: transparent;
            border: none;
            color: #64748b;
            font-size: 14px;
            padding: 4px;
            cursor: pointer;
            border-radius: 4px;
        }
        .vapi-card:hover .vapi-card-edit-btn {
            color: #38bdf8;
        }
        .vapi-card-title {
            font-size: 16px;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 4px;
        }
        .vapi-card-provider {
            font-size: 12px;
            color: #94a3b8;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .vapi-card-stats {
            display: flex;
            justify-content: space-between;
            border-top: 1px solid #1e2430;
            padding-top: 12px;
            font-size: 11px;
        }
        .vapi-card-stat-label {
            color: #64748b;
            margin-bottom: 2px;
        }
        .vapi-card-stat-val {
            color: #f1f5f9;
            font-weight: 700;
        }

        /* First Message Block */
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
        .vapi-section-title {
            font-size: 14px;
            font-weight: 700;
            color: #ffffff;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .vapi-select-dark {
            background: #1b202a;
            border: 1px solid #2d3545;
            color: #f1f5f9;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
        }
        .vapi-select-dark:focus {
            border-color: #38bdf8;
            outline: none;
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
            transition: border-color 0.2s;
        }
        .vapi-textarea-dark:focus {
            border-color: #10b981;
            outline: none;
        }
        .vapi-prompt-editor {
            font-family: 'Fira Code', 'Consolas', 'Courier New', monospace;
            font-size: 13px;
            line-height: 1.5;
            background: #090a0d;
        }

        /* Tools Table */
        .vapi-tools-table {
            width: 100%;
            border-collapse: collapse;
        }
        .vapi-tools-table th {
            text-align: left;
            padding: 12px 16px;
            color: #64748b;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            border-bottom: 1px solid #1e2430;
        }
        .vapi-tools-table td {
            padding: 16px;
            border-bottom: 1px solid #191e28;
            vertical-align: middle;
        }
        .vapi-tool-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            margin-right: 12px;
        }
        .vapi-tool-name {
            font-weight: 700;
            color: #ffffff;
            font-size: 14px;
        }
        .vapi-tool-desc {
            font-size: 12px;
            color: #94a3b8;
            margin-top: 4px;
            max-width: 600px;
        }

        /* Settings Modals / Drawers */
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
            animation: slideIn 0.25s cubic-bezier(0.16, 1, 0.3, 1);
        }
        @keyframes slideIn {
            from { transform: translateX(100%); }
            to { transform: translateX(0); }
        }
        .vapi-drawer-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }
        .vapi-drawer-title {
            font-size: 18px;
            font-weight: 800;
            color: #ffffff;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .vapi-drawer-subtitle {
            font-size: 12px;
            color: #94a3b8;
            margin-bottom: 24px;
            line-height: 1.5;
        }
        .vapi-form-group {
            margin-bottom: 20px;
        }
        .vapi-form-label {
            display: block;
            font-size: 12px;
            font-weight: 700;
            color: #cbd5e1;
            margin-bottom: 6px;
        }
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
        .vapi-input-dark:focus {
            border-color: #38bdf8;
            outline: none;
        }
        .vapi-switch-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #1c222e;
        }
        .vapi-switch-info {
            max-width: 80%;
        }
        .vapi-switch-title {
            font-size: 13px;
            font-weight: 600;
            color: #ffffff;
        }
        .vapi-switch-desc {
            font-size: 11px;
            color: #64748b;
            margin-top: 2px;
        }
        
        /* Toggle Switch */
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

        /* Range Slider */
        .vapi-range-slider {
            width: 100%;
            accent-color: #10b981;
            height: 6px;
            background: #272d3b;
            border-radius: 3px;
            outline: none;
        }

        /* Collapsible Section */
        .vapi-accordion-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px 0;
            border-top: 1px solid #1c222e;
            cursor: pointer;
            color: #cbd5e1;
            font-weight: 700;
            font-size: 13px;
        }

        /* Pill segmented control */
        .vapi-pill-group {
            display: flex;
            background: #14171f;
            border: 1px solid #222834;
            border-radius: 8px;
            padding: 3px;
            gap: 4px;
        }
        .vapi-pill-opt {
            flex: 1;
            text-align: center;
            padding: 6px 10px;
            font-size: 12px;
            font-weight: 600;
            border-radius: 6px;
            cursor: pointer;
            color: #94a3b8;
            transition: all 0.2s;
        }
        .vapi-pill-opt.active {
            background: #222b3a;
            color: #38bdf8;
        }
    </style>
</head>

<?php print $ui->creamyBody(); ?>
<div class="wrapper">
    <?php print $ui->creamyHeader($user); ?>
    <?php print $ui->getSidebar($user->getUserId(), $user->getUserName(), $user->getUserRole(), $user->getUserAvatar()); ?>

    <aside class="right-side" style="margin-left: 220px; background-color: #0d0f12;">
        <form id="form_vapi_agent" method="POST" action="php/SaveAIAgent.php">
            <input type="hidden" name="agent_id" value="<?=$agent['agent_id']?>" />
            <input type="hidden" name="model_preset" id="input_model_preset" value="<?=$model_preset?>" />
            <input type="hidden" name="first_message_mode" id="input_first_message_mode" value="<?=$first_message_mode?>" />
            <input type="hidden" name="stt_provider" id="input_stt_provider" value="<?=$agent['stt_provider']?>" />
            <input type="hidden" name="stt_model" id="input_stt_model" value="<?=$agent['stt_model']?>" />
            <input type="hidden" name="stt_language" id="input_stt_language" value="<?=$agent['stt_language']?>" />
            <input type="hidden" name="intelligent_turn_taking" id="input_intelligent_turn_taking" value="<?=$intelligent_turn_taking?>" />
            <input type="hidden" name="background_denoising" id="input_background_denoising" value="<?=$background_denoising?>" />
            <input type="hidden" name="silence_timeout_ms" id="input_silence_timeout_ms" value="<?=$silence_timeout_ms?>" />
            <input type="hidden" name="stt_fallback_provider" id="input_stt_fallback_provider" value="<?=$agent['stt_fallback_provider']?>" />
            
            <input type="hidden" name="llm_provider" id="input_llm_provider" value="<?=$agent['llm_provider']?>" />
            <input type="hidden" name="llm_model" id="input_llm_model" value="<?=$agent['llm_model']?>" />
            <input type="hidden" name="temperature" id="input_temperature" value="<?=$agent['temperature']?>" />
            <input type="hidden" name="max_tokens" id="input_max_tokens" value="<?=$max_tokens?>" />
            <input type="hidden" name="prompt_cache_retention" id="input_prompt_cache_retention" value="<?=$prompt_cache_retention?>" />
            <input type="hidden" name="tool_strict_compatibility" id="input_tool_strict_compatibility" value="<?=$tool_strict_compatibility?>" />

            <input type="hidden" name="voice_provider" id="input_voice_provider" value="<?=$agent['voice_provider']?>" />
            <input type="hidden" name="voice_id" id="input_voice_id" value="<?=$agent['voice_id']?>" />
            <input type="hidden" name="voice_name" id="input_voice_name" value="<?=$agent['voice_name']?>" />
            <input type="hidden" name="voice_speed" id="input_voice_speed" value="<?=$voice_speed?>" />
            <input type="hidden" name="voice_stability" id="input_voice_stability" value="<?=$voice_stability?>" />
            <input type="hidden" name="voice_clarity" id="input_voice_clarity" value="<?=$voice_clarity?>" />
            <input type="hidden" name="voice_style_exaggeration" id="input_voice_style_exaggeration" value="<?=$voice_style_exaggeration?>" />
            <input type="hidden" name="voice_optimize_latency" id="input_voice_optimize_latency" value="<?=$voice_optimize_latency?>" />
            <input type="hidden" name="background_sound" id="input_background_sound" value="<?=$background_sound?>" />

            <input type="hidden" name="tools_json" id="input_tools_json" value="<?=htmlspecialchars(json_encode($agentTools))?>" />
            <input type="hidden" name="status" id="input_status" value="<?=$agent['status']?>" />
            <input type="hidden" name="transfer_phone_or_queue" id="input_transfer_phone_or_queue" value="<?=htmlspecialchars($agent['transfer_phone_or_queue'])?>" />

            <div class="vapi-container">
                <!-- Top Navigation & Actions Bar -->
                <div class="vapi-header">
                    <div class="vapi-title-area">
                        <div class="vapi-agent-icon"><i class="fa fa-microphone"></i></div>
                        <div>
                            <input type="text" name="agent_name" class="vapi-agent-name-input" value="<?=htmlspecialchars($agent['agent_name'])?>" required />
                            <div style="display: flex; gap: 8px; align-items: center; margin-top: 4px; padding-left: 8px;">
                                <span class="vapi-badge">ID: #<?=$agent['agent_id']?></span>
                                <span class="vapi-badge">v1.2</span>
                                <span class="vapi-badge vapi-badge-published"><i class="fa fa-check-circle"></i> Published</span>
                            </div>
                        </div>
                    </div>
                    <div class="vapi-actions">
                        <button type="button" class="vapi-btn" id="btn_composer"><i class="fa fa-magic text-yellow"></i> Composer</button>
                        <button type="button" class="vapi-btn vapi-btn-talk" id="btn_test_talk"><i class="fa fa-phone"></i> Talk / Testar Chamada</button>
                        <button type="submit" class="vapi-btn vapi-btn-primary" id="btn_save_agent"><i class="fa fa-floppy-o"></i> Salvar Agente</button>
                    </div>
                </div>

                <!-- Tabs -->
                <div class="vapi-tabs">
                    <a href="#tab_assistant" class="vapi-tab active" data-tab="assistant"><i class="fa fa-cube"></i> Assistant</a>
                    <a href="#tab_logs" class="vapi-tab" data-tab="logs"><i class="fa fa-list-alt"></i> Logs</a>
                    <a href="#tab_tools" class="vapi-tab" data-tab="tools"><i class="fa fa-wrench"></i> Tools</a>
                    <a href="#tab_analysis" class="vapi-tab" data-tab="analysis"><i class="fa fa-line-chart"></i> Analysis</a>
                    <a href="#tab_advanced" class="vapi-tab" data-tab="advanced"><i class="fa fa-sliders"></i> Advanced</a>
                </div>

                <!-- TAB 1: ASSISTANT -->
                <div id="view_assistant" class="vapi-tab-view">
                    <!-- Telemetry Header -->
                    <div class="vapi-telemetry">
                        <div class="vapi-telemetry-item">
                            <div class="vapi-telemetry-header">
                                <span class="vapi-telemetry-label">Custo Estimado</span>
                                <span class="vapi-telemetry-value" id="disp_total_cost">~$0.038<span style="font-size:14px; color:#94a3b8;">/min</span></span>
                            </div>
                            <div class="vapi-progress-bar">
                                <div class="vapi-seg-stt" id="cost_bar_stt" style="width: 25%;"></div>
                                <div class="vapi-seg-llm" id="cost_bar_llm" style="width: 35%;"></div>
                                <div class="vapi-seg-tts" id="cost_bar_tts" style="width: 40%;"></div>
                            </div>
                        </div>

                        <div class="vapi-telemetry-item">
                            <div class="vapi-telemetry-header">
                                <span class="vapi-telemetry-label">Latência de Turno</span>
                                <span class="vapi-telemetry-value" id="disp_total_latency">~460<span style="font-size:14px; color:#94a3b8;">ms</span></span>
                            </div>
                            <div class="vapi-progress-bar">
                                <div class="vapi-seg-stt" id="lat_bar_stt" style="width: 26%;"></div>
                                <div class="vapi-seg-llm" id="lat_bar_llm" style="width: 39%;"></div>
                                <div class="vapi-seg-tts" id="lat_bar_tts" style="width: 35%;"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Model Presets -->
                    <div class="vapi-presets-bar">
                        <span class="vapi-preset-label">Model Presets:</span>
                        <div class="vapi-preset-pill <?=($model_preset=='balanced'?'active':'')?>" data-preset="balanced">Balanced</div>
                        <div class="vapi-preset-pill <?=($model_preset=='high_intelligence'?'active':'')?>" data-preset="high_intelligence">High Intelligence</div>
                        <div class="vapi-preset-pill <?=($model_preset=='ultra_fast'?'active':'')?>" data-preset="ultra_fast">Ultra Fast (<350ms)</div>
                        <div class="vapi-preset-pill <?=($model_preset=='cost_saver'?'active':'')?>" data-preset="cost_saver">Cost Saver</div>
                        <div class="vapi-preset-pill <?=($model_preset=='customized'?'active':'')?>" data-preset="customized">Customized</div>
                    </div>

                    <!-- 3 Pipeline Cards -->
                    <div class="vapi-cards-grid">
                        <!-- Card 1: TRANSCRIBER -->
                        <div class="vapi-card" id="card_transcriber">
                            <div class="vapi-card-header">
                                <span class="vapi-card-type"><span class="vapi-dot vapi-dot-stt"></span> TRANSCRIBER</span>
                                <button type="button" class="vapi-card-edit-btn"><i class="fa fa-pencil"></i></button>
                            </div>
                            <div class="vapi-card-title" id="disp_stt_title">Deepgram Nova-2</div>
                            <div class="vapi-card-provider" id="disp_stt_sub"><i class="fa fa-globe text-green"></i> Deepgram • Brazilian Portuguese</div>
                            <div class="vapi-card-stats">
                                <div>
                                    <div class="vapi-card-stat-label">Latency</div>
                                    <div class="vapi-card-stat-val" id="disp_stt_lat">120ms</div>
                                </div>
                                <div>
                                    <div class="vapi-card-stat-label">Cost</div>
                                    <div class="vapi-card-stat-val" id="disp_stt_cost">$0.005/min</div>
                                </div>
                                <div>
                                    <div class="vapi-card-stat-label">Accuracy</div>
                                    <div class="vapi-card-stat-val">98.4%</div>
                                </div>
                            </div>
                        </div>

                        <!-- Card 2: MODEL -->
                        <div class="vapi-card" id="card_model">
                            <div class="vapi-card-header">
                                <span class="vapi-card-type"><span class="vapi-dot vapi-dot-llm"></span> MODEL</span>
                                <button type="button" class="vapi-card-edit-btn"><i class="fa fa-pencil"></i></button>
                            </div>
                            <div class="vapi-card-title" id="disp_llm_title">Llama 3.3 70B</div>
                            <div class="vapi-card-provider" id="disp_llm_sub"><i class="fa fa-bolt text-blue"></i> Groq • Versatile</div>
                            <div class="vapi-card-stats">
                                <div>
                                    <div class="vapi-card-stat-label">Latency</div>
                                    <div class="vapi-card-stat-val" id="disp_llm_lat">180ms</div>
                                </div>
                                <div>
                                    <div class="vapi-card-stat-label">Cost</div>
                                    <div class="vapi-card-stat-val" id="disp_llm_cost">$0.008/min</div>
                                </div>
                                <div>
                                    <div class="vapi-card-stat-label">Intelligence</div>
                                    <div class="vapi-card-stat-val">92</div>
                                </div>
                            </div>
                        </div>

                        <!-- Card 3: VOICE -->
                        <div class="vapi-card" id="card_voice">
                            <div class="vapi-card-header">
                                <span class="vapi-card-type"><span class="vapi-dot vapi-dot-tts"></span> VOICE</span>
                                <button type="button" class="vapi-card-edit-btn"><i class="fa fa-pencil"></i></button>
                            </div>
                            <div class="vapi-card-title" id="disp_voice_title">Sofia (Natural PT-BR)</div>
                            <div class="vapi-card-provider" id="disp_voice_sub"><i class="fa fa-volume-up text-purple"></i> Cartesia • Sonic Multilingual</div>
                            <div class="vapi-card-stats">
                                <div>
                                    <div class="vapi-card-stat-label">Latency</div>
                                    <div class="vapi-card-stat-val" id="disp_voice_lat">90ms</div>
                                </div>
                                <div>
                                    <div class="vapi-card-stat-label">Cost</div>
                                    <div class="vapi-card-stat-val" id="disp_voice_cost">$0.020/min</div>
                                </div>
                                <div>
                                    <div class="vapi-card-stat-label">Humanness</div>
                                    <div class="vapi-card-stat-val">96</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- First Message -->
                    <div class="vapi-section-box">
                        <div class="vapi-section-header">
                            <span class="vapi-section-title"><i class="fa fa-commenting-o text-green"></i> First Message</span>
                            <select id="select_first_message_mode" class="vapi-select-dark">
                                <option value="assistant_speaks_first" <?=$first_message_mode=='assistant_speaks_first'?'selected':''?>>Assistant speaks first (A IA fala primeiro)</option>
                                <option value="user_speaks_first" <?=$first_message_mode=='user_speaks_first'?'selected':''?>>User speaks first (A IA aguarda o cliente falar)</option>
                            </select>
                        </div>
                        <textarea name="greeting_message" id="textarea_greeting" class="vapi-textarea-dark" rows="3" placeholder="Olá! Eu sou a Sofia da DDM. Como posso te ajudar hoje?"><?=htmlspecialchars($agent['greeting_message'])?></textarea>
                    </div>

                    <!-- System Prompt -->
                    <div class="vapi-section-box">
                        <div class="vapi-section-header">
                            <span class="vapi-section-title"><i class="fa fa-code text-blue"></i> System Prompt & Instructions</span>
                            <div style="display: flex; gap: 8px;">
                                <button type="button" class="vapi-btn" style="padding:4px 10px; font-size:12px;" id="btn_template_sales"><i class="fa fa-bullhorn text-yellow"></i> Template Vendas</button>
                                <button type="button" class="vapi-btn" style="padding:4px 10px; font-size:12px;" id="btn_template_support"><i class="fa fa-life-ring text-green"></i> Template Suporte/SAC</button>
                                <button type="button" class="vapi-btn" style="padding:4px 10px; font-size:12px;" id="btn_template_qualify"><i class="fa fa-filter text-purple"></i> Template Qualificação</button>
                            </div>
                        </div>
                        <textarea name="system_prompt" id="textarea_prompt" class="vapi-textarea-dark vapi-prompt-editor" rows="14" required><?=htmlspecialchars($agent['system_prompt'])?></textarea>
                        <div style="margin-top: 10px; display: flex; gap: 6px; flex-wrap: wrap; align-items: center;">
                            <span style="font-size:11px; color:#64748b; font-weight:700;">VARIÁVEIS DINÂMICAS:</span>
                            <span class="vapi-badge" style="cursor:pointer;" onclick="insertVar('{{customer_name}}')">{{customer_name}}</span>
                            <span class="vapi-badge" style="cursor:pointer;" onclick="insertVar('{{phone_number}}')">{{phone_number}}</span>
                            <span class="vapi-badge" style="cursor:pointer;" onclick="insertVar('{{cpf}}')">{{cpf}}</span>
                            <span class="vapi-badge" style="cursor:pointer;" onclick="insertVar('{{empresa}}')">{{empresa}}</span>
                            <span class="vapi-badge" style="cursor:pointer;" onclick="insertVar('{{data_hora}}')">{{data_hora}}</span>
                        </div>
                    </div>
                </div>

                <!-- TAB 2: LOGS -->
                <div id="view_logs" class="vapi-tab-view" style="display:none;">
                    <div class="vapi-section-box">
                        <div class="vapi-section-header">
                            <span class="vapi-section-title"><i class="fa fa-history text-yellow"></i> Histórico & Transcrições do Agente</span>
                            <a href="ai_call_logs.php?agent_id=<?=$agent['agent_id']?>" class="vapi-btn" target="_blank"><i class="fa fa-external-link"></i> Abrir Painel Completo de Logs</a>
                        </div>
                        <div id="agent_call_logs_wrap">
                            <p style="color:#94a3b8; font-size:13px;"><i class="fa fa-info-circle"></i> As chamadas realizadas por este agente e suas transcrições completas são gravadas automaticamente em tempo real.</p>
                            <a href="ai_call_logs.php?agent_id=<?=$agent['agent_id']?>" class="btn btn-primary btn-sm"><i class="fa fa-table"></i> Ver Transcrições e Gravações</a>
                        </div>
                    </div>
                </div>

                <!-- TAB 3: TOOLS -->
                <div id="view_tools" class="vapi-tab-view" style="display:none;">
                    <div class="vapi-section-box">
                        <div class="vapi-section-header">
                            <span class="vapi-section-title"><i class="fa fa-wrench text-orange"></i> Available Tools & Function Calling</span>
                            <button type="button" class="vapi-btn" id="btn_add_custom_tool"><i class="fa fa-plus"></i> Add Tool</button>
                        </div>

                        <table class="vapi-tools-table">
                            <thead>
                                <tr>
                                    <th>Tool Name</th>
                                    <th>Type</th>
                                    <th>Status</th>
                                    <th>Assign Tool Version</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="tools_table_body">
                                <?php foreach ($agentTools as $t): ?>
                                    <tr data-tool-id="<?=htmlspecialchars($t['id'])?>">
                                        <td>
                                            <div style="display: flex; align-items: flex-start;">
                                                <div class="vapi-tool-icon" style="background: <?=($t['id']=='end_call'?'#ef4444':($t['id']=='voicemail_tool'?'#8b5cf6':($t['id']=='transfer_call'?'#3b82f6':'#f59e0b')))?>; color:#fff;">
                                                    <i class="fa <?=($t['id']=='end_call'?'fa-phone':($t['id']=='voicemail_tool'?'fa-microphone-slash':($t['id']=='transfer_call'?'fa-exchange':'fa-bolt')))?>"></i>
                                                </div>
                                                <div>
                                                    <span class="vapi-tool-name"><?=htmlspecialchars($t['name'])?></span>
                                                    <div class="vapi-tool-desc"><?=htmlspecialchars($t['description'])?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td><span class="vapi-badge"><?=htmlspecialchars($t['type'])?></span></td>
                                        <td>
                                            <label class="vapi-switch">
                                                <input type="checkbox" class="tool-toggle" data-tool-id="<?=htmlspecialchars($t['id'])?>" <?=!empty($t['enabled'])?'checked':''?> />
                                                <span class="vapi-slider"></span>
                                            </label>
                                        </td>
                                        <td><span class="vapi-badge"><?=!empty($t['version'])?htmlspecialchars($t['version']):'Latest'?></span></td>
                                        <td>
                                            <button type="button" class="btn btn-xs btn-default btn-edit-tool" data-tool-id="<?=htmlspecialchars($t['id'])?>"><i class="fa fa-cog"></i></button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- TAB 4: ANALYSIS -->
                <div id="view_analysis" class="vapi-tab-view" style="display:none;">
                    <div class="vapi-section-box">
                        <div class="vapi-section-header">
                            <span class="vapi-section-title"><i class="fa fa-pie-chart text-cyan"></i> Structured Summary & Evaluation Schema</span>
                        </div>
                        <p style="color:#94a3b8; font-size:13px;">Defina o esquema de qualificação automática de leads ao término de cada ligação.</p>
                        <div class="vapi-form-group">
                            <label class="vapi-form-label">Critérios de Sucesso da Chamada (Structured Output)</label>
                            <textarea name="analysis_schema_json" class="vapi-textarea-dark vapi-prompt-editor" rows="8">{"qualification": ["Interessado", "Sem interesse", "Caixa Postal", "Pediu para retornar"], "extract_fields": ["nome", "cpf", "horario_preferencial", "resumo_conversa"]}</textarea>
                        </div>
                    </div>
                </div>

                <!-- TAB 5: ADVANCED -->
                <div id="view_advanced" class="vapi-tab-view" style="display:none;">
                    <div class="vapi-section-box">
                        <div class="vapi-section-header">
                            <span class="vapi-section-title"><i class="fa fa-cogs text-purple"></i> Configurações Avançadas de Telefonia & SIP</span>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="vapi-form-group">
                                    <label class="vapi-form-label">Ramal / Fila de Transferência Humana</label>
                                    <input type="text" name="transfer_phone_or_queue" class="vapi-input-dark" value="<?=htmlspecialchars($agent['transfer_phone_or_queue'])?>" placeholder="Ex: 8300 ou ramal SIP" />
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="vapi-form-group">
                                    <label class="vapi-form-label">Tempo Máximo da Chamada (Segundos)</label>
                                    <input type="number" name="max_duration_seconds" class="vapi-input-dark" value="<?=htmlspecialchars($agent['max_duration_seconds'] ? $agent['max_duration_seconds'] : 600)?>" />
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="vapi-form-group">
                                    <label class="vapi-form-label">Sensibilidade de Interrupção (Barge-in)</label>
                                    <input type="range" name="interruption_sensitivity" class="vapi-range-slider" min="0.1" max="1.0" step="0.05" value="<?=$agent['interruption_sensitivity'] ? $agent['interruption_sensitivity'] : 0.8?>" />
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="vapi-form-group">
                                    <label class="vapi-form-label">Status do Agente</label>
                                    <select name="status" class="vapi-select-dark" style="width:100%;">
                                        <option value="Y" <?=$agent['status']=='Y'?'selected':''?>>Ativo (Pronto para discar)</option>
                                        <option value="N" <?=$agent['status']=='N'?'selected':''?>>Inativo</option>
                                    </select>
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
<div class="vapi-modal-backdrop" id="modal_transcriber">
    <div class="vapi-drawer">
        <div class="vapi-drawer-header">
            <span class="vapi-drawer-title"><i class="fa fa-microphone text-green"></i> Transcriber Settings</span>
            <button type="button" class="btn btn-link text-muted btn-close-drawer" style="font-size:18px;"><i class="fa fa-times"></i></button>
        </div>
        <div class="vapi-drawer-subtitle">
            Configure the speech-to-text transcriber that converts caller speech into text for the LLM.
        </div>

        <div class="vapi-form-group">
            <label class="vapi-form-label">Model / Provider</label>
            <select id="modal_stt_provider_select" class="vapi-input-dark">
                <option value="deepgram|nova-2">Deepgram Nova-2 (120ms • $0.005/min)</option>
                <option value="groq|whisper-large-v3-turbo">Groq Whisper Turbo (100ms • $0.004/min)</option>
                <option value="openai|whisper-1">OpenAI Whisper (1040ms • $0.012/min)</option>
                <option value="azure|azure-default">Azure Speech (750ms • $0.034/min)</option>
            </select>
        </div>

        <div class="vapi-form-group">
            <label class="vapi-form-label"><i class="fa fa-globe"></i> Language</label>
            <select id="modal_stt_language_select" class="vapi-input-dark">
                <option value="pt-BR">Brazilian Portuguese</option>
                <option value="en-US">English (US)</option>
                <option value="es-ES">Spanish</option>
            </select>
        </div>

        <div class="vapi-switch-row">
            <div class="vapi-switch-info">
                <div class="vapi-switch-title">Intelligent Turn-Taking</div>
                <div class="vapi-switch-desc">Detects when the caller has finished speaking.</div>
            </div>
            <label class="vapi-switch">
                <input type="checkbox" id="modal_turn_taking" <?=$intelligent_turn_taking=='Y'?'checked':''?> />
                <span class="vapi-slider"></span>
            </label>
        </div>

        <div class="vapi-switch-row">
            <div class="vapi-switch-info">
                <div class="vapi-switch-title">Background Denoising</div>
                <div class="vapi-switch-desc">Filter background noise while the user is talking.</div>
            </div>
            <label class="vapi-switch">
                <input type="checkbox" id="modal_denoising" <?=$background_denoising=='Y'?'checked':''?> />
                <span class="vapi-slider"></span>
            </label>
        </div>

        <!-- Collapsible Advanced -->
        <div class="vapi-accordion-header" id="adv_stt_toggle">
            <span>Advanced</span>
            <i class="fa fa-chevron-down"></i>
        </div>
        <div id="adv_stt_body" style="padding-top: 15px;">
            <div class="vapi-form-group">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                    <label class="vapi-form-label" style="margin-bottom:0;"><i class="fa fa-clock-o"></i> Silence Timeout</label>
                    <span class="vapi-badge" id="disp_silence_timeout_badge"><?=$silence_timeout_ms?> ms</span>
                </div>
                <div style="font-size:11px; color:#64748b; margin-bottom:8px;">How much silence this transcriber needs before it closes a segment of speech.</div>
                <input type="range" id="modal_silence_slider" class="vapi-range-slider" min="100" max="3000" step="50" value="<?=$silence_timeout_ms?>" />
                <div style="display:flex; justify-content:space-between; font-size:10px; color:#64748b; margin-top:4px;">
                    <span>100ms</span>
                    <span>3000ms</span>
                </div>
            </div>

            <div class="vapi-form-group">
                <label class="vapi-form-label"><i class="fa fa-shield"></i> Transcriber Fallback Provider</label>
                <select id="modal_stt_fallback_select" class="vapi-input-dark">
                    <option value="openai">OpenAI Whisper (Auto-Fallback)</option>
                    <option value="groq">Groq Whisper Large v3</option>
                    <option value="deepgram">Deepgram Nova-2</option>
                </select>
            </div>
        </div>

        <div style="margin-top: 30px;">
            <button type="button" class="vapi-btn vapi-btn-primary btn-close-drawer" style="width:100%; justify-content:center;">Apply Transcriber Settings</button>
        </div>
    </div>
</div>

<!-- 2. MODEL SETTINGS DRAWER -->
<div class="vapi-modal-backdrop" id="modal_model">
    <div class="vapi-drawer">
        <div class="vapi-drawer-header">
            <span class="vapi-drawer-title"><i class="fa fa-cogs text-blue"></i> Model Settings</span>
            <button type="button" class="btn btn-link text-muted btn-close-drawer" style="font-size:18px;"><i class="fa fa-times"></i></button>
        </div>
        <div class="vapi-drawer-subtitle">
            Configure the LLM brain that generates conversational responses and handles function calling.
        </div>

        <div class="vapi-form-group">
            <label class="vapi-form-label">Model</label>
            <select id="modal_llm_model_select" class="vapi-input-dark">
                <option value="groq|llama-3.3-70b-versatile">Groq Llama 3.3 70B (180ms • $0.008/min • 92 Intel)</option>
                <option value="groq|llama-3.1-8b-instant">Groq Llama 3.1 8B Instant (80ms • $0.002/min • 75 Intel)</option>
                <option value="openai|gpt-4o-mini">OpenAI GPT-4o Mini (420ms • $0.006/min • 88 Intel)</option>
                <option value="openai|gpt-4o">OpenAI GPT-4o (690ms • $0.023/min • 99 Intel)</option>
                <option value="anthropic|claude-3-5-sonnet-20241022">Claude 3.5 Sonnet (750ms • $0.030/min • 98 Intel)</option>
                <option value="deepseek|deepseek-chat">DeepSeek V3 (300ms • $0.003/min • 90 Intel)</option>
            </select>
        </div>

        <div class="vapi-form-group">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                <label class="vapi-form-label" style="margin-bottom:0;"><i class="fa fa-thermometer-half"></i> Temperature</label>
                <span class="vapi-badge" id="disp_temp_badge"><?=$agent['temperature']?></span>
            </div>
            <div style="font-size:11px; color:#64748b; margin-bottom:8px;">Controls randomness. Lower is more deterministic, higher is more creative.</div>
            <input type="range" id="modal_temp_slider" class="vapi-range-slider" min="0.0" max="1.0" step="0.05" value="<?=$agent['temperature']?>" />
            <div style="display:flex; justify-content:space-between; font-size:10px; color:#64748b; margin-top:4px;">
                <span>Precise (0.0)</span>
                <span>Creative (1.0)</span>
            </div>
        </div>

        <!-- Collapsible Advanced -->
        <div class="vapi-accordion-header" id="adv_llm_toggle">
            <span>Advanced</span>
            <i class="fa fa-chevron-down"></i>
        </div>
        <div id="adv_llm_body" style="padding-top: 15px;">
            <div class="vapi-form-group">
                <label class="vapi-form-label">Max Tokens</label>
                <input type="number" id="modal_max_tokens_input" class="vapi-input-dark" value="<?=$max_tokens?>" />
                <small style="color:#64748b; font-size:11px;">Max number of tokens generated per turn (Recomendado 150-300 para voz ágil).</small>
            </div>

            <div class="vapi-form-group">
                <label class="vapi-form-label">Prompt Cache Retention</label>
                <div class="vapi-pill-group">
                    <div class="vapi-pill-opt <?=($prompt_cache_retention=='in_memory'?'active':'')?>" data-cache="in_memory">In memory</div>
                    <div class="vapi-pill-opt <?=($prompt_cache_retention=='24_hours'?'active':'')?>" data-cache="24_hours">24 hours</div>
                </div>
            </div>

            <div class="vapi-switch-row">
                <div class="vapi-switch-info">
                    <div class="vapi-switch-title">Tool Strict Compatibility</div>
                    <div class="vapi-switch-desc">Adapt tool schemas for strict-mode validation endpoints.</div>
                </div>
                <label class="vapi-switch">
                    <input type="checkbox" id="modal_strict_tools" <?=$tool_strict_compatibility=='Y'?'checked':''?> />
                    <span class="vapi-slider"></span>
                </label>
            </div>
        </div>

        <div style="margin-top: 30px;">
            <button type="button" class="vapi-btn vapi-btn-primary btn-close-drawer" style="width:100%; justify-content:center;">Apply Model Settings</button>
        </div>
    </div>
</div>

<!-- 3. VOICE SETTINGS DRAWER -->
<div class="vapi-modal-backdrop" id="modal_voice">
    <div class="vapi-drawer">
        <div class="vapi-drawer-header">
            <span class="vapi-drawer-title"><i class="fa fa-volume-up text-purple"></i> Voice Settings</span>
            <button type="button" class="btn btn-link text-muted btn-close-drawer" style="font-size:18px;"><i class="fa fa-times"></i></button>
        </div>
        <div class="vapi-drawer-subtitle">
            Configure the text-to-speech voice and acoustic parameters your assistant uses to speak.
        </div>

        <div class="vapi-form-group">
            <label class="vapi-form-label">Voice Provider & Model</label>
            <select id="modal_voice_provider_select" class="vapi-input-dark">
                <option value="cartesia|cartesia-pt-br-sofia|Sofia (Cartesia Português BR)">Cartesia Sonic (90ms • $0.020/min • Sofia PT-BR)</option>
                <option value="cartesia|cartesia-pt-br-lucas|Lucas (Cartesia Português BR)">Cartesia Sonic (90ms • $0.020/min • Lucas PT-BR)</option>
                <option value="elevenlabs|21m00Tcm4TlvDq8ikWAM|Rachel (ElevenLabs)">ElevenLabs Turbo v2.5 (320ms • $0.036/min • Rachel)</option>
                <option value="elevenlabs|AZnzlk1XvdvUeBnXmlld|Domi (ElevenLabs)">ElevenLabs Turbo v2.5 (320ms • $0.036/min • Domi)</option>
                <option value="openai|nova|Nova (OpenAI TTS)">OpenAI TTS-1 (380ms • $0.015/min • Nova)</option>
                <option value="openai|alloy|Alloy (OpenAI TTS)">OpenAI TTS-1 (380ms • $0.015/min • Alloy)</option>
            </select>
        </div>

        <div class="vapi-form-group">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                <label class="vapi-form-label" style="margin-bottom:0;"><i class="fa fa-tachometer"></i> Speed</label>
                <span class="vapi-badge" id="disp_speed_badge"><?=$voice_speed?>x</span>
            </div>
            <input type="range" id="modal_speed_slider" class="vapi-range-slider" min="0.75" max="1.50" step="0.05" value="<?=$voice_speed?>" />
            <div style="display:flex; justify-content:space-between; font-size:10px; color:#64748b; margin-top:4px;">
                <span>Slower (0.75x)</span>
                <span>Faster (1.50x)</span>
            </div>
        </div>

        <div class="vapi-form-group">
            <label class="vapi-form-label"><i class="fa fa-music"></i> Background Sound</label>
            <div class="vapi-pill-group" id="bg_sound_pills">
                <div class="vapi-pill-opt <?=($background_sound=='off'?'active':'')?>" data-bgsound="off">Off</div>
                <div class="vapi-pill-opt <?=($background_sound=='office'?'active':'')?>" data-bgsound="office">Office</div>
                <div class="vapi-pill-opt <?=($background_sound=='default'?'active':'')?>" data-bgsound="default">Default</div>
                <div class="vapi-pill-opt <?=($background_sound=='custom'?'active':'')?>" data-bgsound="custom">Custom</div>
            </div>
            <small style="color:#64748b; font-size:11px;">Background sound played in the call (e.g. ambient office noise for human realism).</small>
        </div>

        <!-- Collapsible Advanced -->
        <div class="vapi-accordion-header" id="adv_voice_toggle">
            <span>Advanced</span>
            <i class="fa fa-chevron-down"></i>
        </div>
        <div id="adv_voice_body" style="padding-top: 15px;">
            <div class="vapi-switch-row">
                <div class="vapi-switch-info">
                    <div class="vapi-switch-title">Voice Caching</div>
                    <div class="vapi-switch-desc">Reuses audio for repeated phrases instead of re-synthesising them.</div>
                </div>
                <label class="vapi-switch">
                    <input type="checkbox" id="modal_voice_caching" checked />
                    <span class="vapi-slider"></span>
                </label>
            </div>

            <div class="vapi-form-group" style="margin-top:14px;">
                <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
                    <label class="vapi-form-label" style="margin-bottom:0;">Stability</label>
                    <span class="vapi-badge" id="disp_stability_badge"><?=$voice_stability?></span>
                </div>
                <input type="range" id="modal_stability_slider" class="vapi-range-slider" min="0.0" max="1.0" step="0.05" value="<?=$voice_stability?>" />
            </div>

            <div class="vapi-form-group">
                <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
                    <label class="vapi-form-label" style="margin-bottom:0;">Clarity + Similarity</label>
                    <span class="vapi-badge" id="disp_clarity_badge"><?=$voice_clarity?></span>
                </div>
                <input type="range" id="modal_clarity_slider" class="vapi-range-slider" min="0.0" max="1.0" step="0.05" value="<?=$voice_clarity?>" />
            </div>
        </div>

        <div style="margin-top: 30px;">
            <button type="button" class="vapi-btn vapi-btn-primary btn-close-drawer" style="width:100%; justify-content:center;">Apply Voice Settings</button>
        </div>
    </div>
</div>

<?php print $ui->standardizedThemeJS(); ?>
<script type="text/javascript">
$(document).ready(function() {
    // Tab switching
    $('.vapi-tab').click(function(e) {
        e.preventDefault();
        $('.vapi-tab').removeClass('active');
        $(this).addClass('active');
        var target = $(this).data('tab');
        $('.vapi-tab-view').hide();
        $('#view_' + target).show();
    });

    // Modals / Drawers Open & Close
    $('#card_transcriber').click(function() { $('#modal_transcriber').fadeIn(200).css('display', 'flex'); });
    $('#card_model').click(function() { $('#modal_model').fadeIn(200).css('display', 'flex'); });
    $('#card_voice').click(function() { $('#modal_voice').fadeIn(200).css('display', 'flex'); });

    $('.btn-close-drawer, .vapi-modal-backdrop').click(function(e) {
        if (e.target === this || $(this).hasClass('btn-close-drawer')) {
            $('.vapi-modal-backdrop').fadeOut(200);
        }
    });

    // Accordions
    $('#adv_stt_toggle').click(function() { $('#adv_stt_body').slideToggle(200); });
    $('#adv_llm_toggle').click(function() { $('#adv_llm_body').slideToggle(200); });
    $('#adv_voice_toggle').click(function() { $('#adv_voice_body').slideToggle(200); });

    // Slider live updates
    $('#modal_silence_slider').on('input', function() {
        var val = $(this).val();
        $('#disp_silence_timeout_badge').text(val + ' ms');
        $('#input_silence_timeout_ms').val(val);
    });

    $('#modal_temp_slider').on('input', function() {
        var val = $(this).val();
        $('#disp_temp_badge').text(val);
        $('#input_temperature').val(val);
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

    // Pill selectors
    $('#bg_sound_pills .vapi-pill-opt').click(function() {
        $('#bg_sound_pills .vapi-pill-opt').removeClass('active');
        $(this).addClass('active');
        $('#input_background_sound').val($(this).data('bgsound'));
    });

    // First message mode
    $('#select_first_message_mode').change(function() {
        $('#input_first_message_mode').val($(this).val());
    });

    // Presets Click Handler
    $('.vapi-preset-pill').click(function() {
        $('.vapi-preset-pill').removeClass('active');
        $(this).addClass('active');
        var preset = $(this).data('preset');
        $('#input_model_preset').val(preset);

        if (preset === 'ultra_fast') {
            $('#input_stt_provider').val('deepgram');
            $('#input_stt_model').val('nova-2');
            $('#disp_stt_title').text('Deepgram Nova-2');
            $('#disp_stt_lat').text('100ms');

            $('#input_llm_provider').val('groq');
            $('#input_llm_model').val('llama-3.1-8b-instant');
            $('#disp_llm_title').text('Llama 3.1 8B Instant');
            $('#disp_llm_lat').text('80ms');

            $('#input_voice_provider').val('cartesia');
            $('#input_voice_id').val('cartesia-pt-br-sofia');
            $('#disp_voice_title').text('Sofia (Cartesia Sonic)');
            $('#disp_voice_lat').text('90ms');

            $('#disp_total_latency').html('~270<span style="font-size:14px; color:#94a3b8;">ms</span>');
            $('#disp_total_cost').html('~$0.027<span style="font-size:14px; color:#94a3b8;">/min</span>');
        } else if (preset === 'high_intelligence') {
            $('#input_llm_provider').val('openai');
            $('#input_llm_model').val('gpt-4o');
            $('#disp_llm_title').text('GPT-4o');
            $('#disp_llm_lat').text('690ms');

            $('#disp_total_latency').html('~900<span style="font-size:14px; color:#94a3b8;">ms</span>');
            $('#disp_total_cost').html('~$0.065<span style="font-size:14px; color:#94a3b8;">/min</span>');
        } else if (preset === 'cost_saver') {
            $('#input_llm_provider').val('deepseek');
            $('#input_llm_model').val('deepseek-chat');
            $('#disp_llm_title').text('DeepSeek V3');
            $('#disp_llm_lat').text('300ms');

            $('#disp_total_latency').html('~490<span style="font-size:14px; color:#94a3b8;">ms</span>');
            $('#disp_total_cost').html('~$0.021<span style="font-size:14px; color:#94a3b8;">/min</span>');
        }
    });

    // Template buttons
    $('#btn_template_sales').click(function() {
        $('#textarea_prompt').val(
            "# PERSONA E OBJETIVO\n" +
            "Você é a Sofia, consultora de vendas da {{empresa}}. Seu objetivo é apresentar com energia e cordialidade nossa solução para o cliente {{customer_name}} e agendar uma demonstração.\n\n" +
            "# DIRETRIZES\n" +
            "1. Fale sempre em no máximo 2 frases curtas e naturais por turno.\n" +
            "2. Ouça com atenção e valide o interesse do cliente.\n" +
            "3. Se houver interesse, use a ferramenta transfer_call para transferir ao especialista."
        );
    });

    $('#btn_template_support').click(function() {
        $('#textarea_prompt').val(
            "# 3. AGENTE DE ATENDIMENTO, SUPORTE E SAC\n\n" +
            "## FIRST MESSAGE\n" +
            "Olá! Eu sou a Júlia, assistente virtual de atendimento. Me conta como posso te ajudar hoje?\n\n" +
            "# PERSONA\n" +
            "Você é Júlia, assistente virtual especializada em atendimento ao cliente, suporte e SAC.\n" +
            "Seu objetivo é entender a solicitação do cliente e resolver da forma mais rápida possível.\n\n" +
            "# REGRAS\n" +
            "1. Seja ágil, empática e prestativa.\n" +
            "2. Se o cliente solicitar encerramento, use end_call."
        );
    });

    $('#btn_template_qualify').click(function() {
        $('#textarea_prompt').val(
            "# AGENTE DE QUALIFICAÇÃO (SDR)\n\n" +
            "Você é o Lucas, especialista em pré-atendimento e qualificação de crédito e serviços.\n" +
            "Seu objetivo é confirmar os 3 primeiros dígitos do CPF do cliente {{customer_name}} para validar elegibilidade.\n\n" +
            "# REGRAS\n" +
            "1. Confirme os dados e acione a ferramenta capturar_cpf quando o cliente informar."
        );
    });

    // Talk / Test call button
    $('#btn_test_talk').click(function() {
        var phone = prompt("Digite o número de telefone com DDD para testar a chamada agora:", "11999999999");
        if (phone && phone.trim().length >= 8) {
            $(this).prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Discando...');
            var self = this;
            $.post('api_ai_calls.php?action=dial', {
                agent_id: <?=$agent['agent_id']?>,
                phone_number: phone.trim()
            }, function(res) {
                $(self).prop('disabled', false).html('<i class="fa fa-phone"></i> Talk / Testar Chamada');
                if (res.status === 'ringing' || res.status === 1 || res.status === 'ok') {
                    alert("📞 Ligação disparada com sucesso para " + phone + "! Seu telefone tocará em instantes.");
                } else {
                    alert("Resposta da discagem: " + (res.message || JSON.stringify(res)));
                }
            }, 'json').fail(function() {
                $(self).prop('disabled', false).html('<i class="fa fa-phone"></i> Talk / Testar Chamada');
                alert("Erro ao contatar servidor de telefonia.");
            });
        }
    });

    // Form submission
    $('#form_vapi_agent').submit(function(e) {
        e.preventDefault();
        var $btn = $('#btn_save_agent');
        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Salvando...');

        var formData = $(this).serialize();
        $.post('php/SaveAIAgent.php', formData, function(res) {
            $btn.prop('disabled', false).html('<i class="fa fa-floppy-o"></i> Salvar Agente');
            if (res.status == 1) {
                alert("✨ Agente de IA salvo e publicado com sucesso!");
            } else {
                alert(res.message || 'Erro ao salvar agente.');
            }
        }, 'json').fail(function() {
            $btn.prop('disabled', false).html('<i class="fa fa-floppy-o"></i> Salvar Agente');
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
