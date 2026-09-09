<?php
/**
 * @file        edit_ai_agent.php
 * @brief       Vapi-Style Voice AI Agent Builder & Manager (Fully Dynamic)
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

$defaultTools = $aiHandler->getDefaultTools();
$agentTools = !empty($agent['tools_json']) ? json_decode($agent['tools_json'], true) : $defaultTools;
if (!is_array($agentTools)) $agentTools = $defaultTools;

// Defaults for Vapi fields
$first_message_mode = !empty($agent['first_message_mode']) ? $agent['first_message_mode'] : 'assistant_speaks_first';
$model_preset = !empty($agent['model_preset']) ? $agent['model_preset'] : 'balanced';
$silence_timeout_ms = !empty($agent['silence_timeout_ms']) ? (int)$agent['silence_timeout_ms'] : 500;
$intelligent_turn_taking = isset($agent['intelligent_turn_taking']) ? $agent['intelligent_turn_taking'] : 'Y';
$background_denoising = isset($agent['background_denoising']) ? $agent['background_denoising'] : 'Y';
$background_sound = !empty($agent['background_sound']) ? $agent['background_sound'] : 'off';
$background_sound_volume = isset($agent['background_sound_volume']) ? (float)$agent['background_sound_volume'] : 0.10;
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
        /* MODERN VAPI DARK THEME */
        body, .right-side, .wrapper {
            background-color: #0b0d11 !important;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Inter", "Helvetica Neue", Arial, sans-serif !important;
        }

        .vapi-container {
            background-color: #0b0d11;
            color: #f1f5f9;
            min-height: calc(100vh - 50px);
            padding: 24px 36px;
            max-width: 1440px;
            margin: 0 auto;
        }

        /* Top Header */
        .vapi-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-bottom: 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            margin-bottom: 24px;
        }
        .vapi-title-area {
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .vapi-agent-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: linear-gradient(135deg, rgba(56, 189, 248, 0.15), rgba(16, 185, 129, 0.1));
            display: flex;
            align-items: center;
            justify-content: center;
            color: #38bdf8;
            font-size: 20px;
            border: 1px solid rgba(56, 189, 248, 0.3);
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.3);
        }
        .vapi-agent-name-input {
            background: transparent;
            border: 1px solid transparent;
            color: #ffffff;
            font-size: 22px;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 8px;
            transition: all 0.2s;
            letter-spacing: -0.3px;
        }
        .vapi-agent-name-input:hover, .vapi-agent-name-input:focus {
            background: #14171f;
            border-color: rgba(255, 255, 255, 0.15);
            outline: none;
        }
        .vapi-badge {
            background: rgba(255, 255, 255, 0.06);
            color: #94a3b8;
            font-size: 11px;
            font-weight: 600;
            padding: 3px 9px;
            border-radius: 6px;
            border: 1px solid rgba(255, 255, 255, 0.08);
            letter-spacing: 0.2px;
        }
        .vapi-badge-published {
            background: rgba(16, 185, 129, 0.12);
            color: #34d399;
            border-color: rgba(16, 185, 129, 0.25);
        }

        /* Top Actions */
        .vapi-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .vapi-btn {
            background: #151922;
            color: #f1f5f9;
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 9px 18px;
            border-radius: 9px;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
            box-shadow: 0 2px 6px rgba(0,0,0,0.2);
        }
        .vapi-btn:hover {
            background: #1d2330;
            border-color: rgba(255, 255, 255, 0.2);
            color: #fff;
            transform: translateY(-1px);
        }
        .vapi-btn-primary {
            background: #10b981;
            color: #0b0d11;
            border-color: #10b981;
            font-weight: 700;
        }
        .vapi-btn-primary:hover {
            background: #059669;
            border-color: #059669;
            color: #ffffff;
            box-shadow: 0 4px 14px rgba(16, 185, 129, 0.3);
        }
        .vapi-btn-talk {
            background: rgba(16, 185, 129, 0.12);
            color: #34d399;
            border-color: rgba(16, 185, 129, 0.3);
        }
        .vapi-btn-talk:hover {
            background: #059669;
            color: #fff;
            box-shadow: 0 4px 14px rgba(16, 185, 129, 0.3);
        }

        /* Tab Navigation */
        .vapi-tabs {
            display: flex;
            gap: 28px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            margin-bottom: 24px;
        }
        .vapi-tab {
            padding: 12px 6px;
            color: #8392a5;
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
            color: #ffffff;
        }
        .vapi-tab.active {
            color: #38bdf8;
            border-bottom-color: #38bdf8;
        }

        /* Telemetry Section */
        .vapi-telemetry {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 24px;
        }
        .vapi-telemetry-item {
            background: linear-gradient(180deg, #131720 0%, #0f1218 100%);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 12px;
            padding: 18px 22px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
        }
        .vapi-telemetry-header {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            margin-bottom: 12px;
        }
        .vapi-telemetry-label {
            font-size: 11px;
            color: #8392a5;
            text-transform: uppercase;
            font-weight: 700;
            letter-spacing: 0.8px;
        }
        .vapi-telemetry-value {
            font-size: 22px;
            font-weight: 800;
            color: #ffffff;
            letter-spacing: -0.5px;
        }
        .vapi-progress-bar {
            height: 7px;
            background: #1c222e;
            border-radius: 4px;
            overflow: hidden;
            display: flex;
        }
        .vapi-seg-stt { background: #10b981; transition: width 0.3s; }
        .vapi-seg-llm { background: #38bdf8; transition: width 0.3s; }
        .vapi-seg-tts { background: #a855f7; transition: width 0.3s; }

        /* Presets Bar */
        .vapi-presets-bar {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 24px;
            background: rgba(255, 255, 255, 0.02);
            padding: 10px 16px;
            border-radius: 10px;
            border: 1px solid rgba(255, 255, 255, 0.05);
            flex-wrap: wrap;
        }
        .vapi-preset-label {
            font-size: 12px;
            font-weight: 700;
            color: #8392a5;
            margin-right: 6px;
        }
        .vapi-preset-pill {
            background: #141822;
            color: #94a3b8;
            font-size: 12px;
            font-weight: 600;
            padding: 6px 14px;
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.08);
            cursor: pointer;
            transition: all 0.2s;
        }
        .vapi-preset-pill:hover {
            background: #1e2433;
            color: #ffffff;
            border-color: rgba(255, 255, 255, 0.2);
        }
        .vapi-preset-pill.active {
            background: rgba(56, 189, 248, 0.15);
            color: #38bdf8;
            border-color: rgba(56, 189, 248, 0.4);
            font-weight: 700;
        }

        /* 3 Main Pipeline Cards Grid */
        .vapi-cards-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 24px;
        }
        .vapi-card {
            background: linear-gradient(180deg, #131720 0%, #0f1218 100%);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 14px;
            padding: 22px;
            cursor: pointer;
            transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
            position: relative;
            box-shadow: 0 4px 18px rgba(0, 0, 0, 0.25);
        }
        .vapi-card:hover {
            border-color: rgba(56, 189, 248, 0.4);
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4);
        }
        .vapi-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 14px;
        }
        .vapi-card-type {
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .vapi-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
        }
        .vapi-dot-stt { background: #10b981; box-shadow: 0 0 8px #10b981; }
        .vapi-dot-llm { background: #38bdf8; box-shadow: 0 0 8px #38bdf8; }
        .vapi-dot-tts { background: #a855f7; box-shadow: 0 0 8px #a855f7; }

        .vapi-card-edit-btn {
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.08);
            color: #94a3b8;
            width: 28px;
            height: 28px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            transition: all 0.2s;
        }
        .vapi-card:hover .vapi-card-edit-btn {
            background: #38bdf8;
            color: #0b0d11;
            border-color: #38bdf8;
        }

        .vapi-card-title {
            font-size: 17px;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 4px;
            letter-spacing: -0.2px;
        }
        .vapi-card-provider {
            font-size: 12px;
            color: #8392a5;
            margin-bottom: 18px;
        }
        .vapi-card-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
            padding-top: 14px;
            border-top: 1px solid rgba(255, 255, 255, 0.06);
            font-size: 11px;
        }
        .vapi-card-stat-label {
            color: #64748b;
            font-weight: 600;
            margin-bottom: 2px;
        }
        .vapi-card-stat-val {
            color: #f1f5f9;
            font-weight: 700;
            font-size: 12px;
        }

        /* First Message Block & Prompt Section */
        .vapi-section-box {
            background: linear-gradient(180deg, #131720 0%, #0f1218 100%);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 14px;
            padding: 22px;
            margin-bottom: 24px;
            box-shadow: 0 4px 18px rgba(0,0,0,0.25);
        }
        .vapi-section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }
        .vapi-section-title {
            font-size: 14px;
            font-weight: 700;
            color: #ffffff;
            display: flex;
            align-items: center;
            gap: 10px;
            letter-spacing: -0.2px;
        }
        .vapi-select-dark {
            background: #181d27;
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #f1f5f9;
            padding: 7px 14px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
        }
        .vapi-select-dark:focus {
            border-color: #38bdf8;
            outline: none;
        }
        .vapi-textarea-dark {
            width: 100%;
            background: #0a0c10;
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 10px;
            color: #f1f5f9;
            padding: 16px;
            font-size: 13px;
            line-height: 1.6;
            resize: vertical;
            box-sizing: border-box;
            transition: border-color 0.2s;
        }
        .vapi-textarea-dark:focus {
            border-color: #10b981;
            outline: none;
            box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.15);
        }
        .vapi-prompt-editor {
            font-family: 'Fira Code', 'Consolas', 'Courier New', monospace;
            font-size: 13px;
            line-height: 1.6;
            background: #08090d;
        }

        /* Tools Table */
        .vapi-tools-table {
            width: 100%;
            border-collapse: collapse;
        }
        .vapi-tools-table th {
            text-align: left;
            padding: 14px 18px;
            color: #64748b;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }
        .vapi-tools-table td {
            padding: 18px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            vertical-align: middle;
        }
        .vapi-tool-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
            margin-right: 14px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.3);
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
            max-width: 650px;
            line-height: 1.4;
        }
        .vapi-action-btn {
            background: #181d26;
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #94a3b8;
            padding: 6px 11px;
            border-radius: 7px;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .vapi-action-btn:hover {
            background: #232a38;
            color: #ffffff;
            border-color: rgba(255, 255, 255, 0.25);
        }
        .vapi-action-btn.btn-danger-tool:hover {
            background: rgba(239, 68, 68, 0.15);
            color: #ef4444;
            border-color: rgba(239, 68, 68, 0.4);
        }

        /* Settings Modals / Drawers */
        .vapi-modal-backdrop {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.8);
            backdrop-filter: blur(4px);
            z-index: 9999;
            display: none;
            justify-content: flex-end;
        }
        .vapi-drawer {
            background: #0f1218;
            width: 500px;
            max-width: 100%;
            height: 100%;
            overflow-y: auto;
            border-left: 1px solid rgba(255, 255, 255, 0.1);
            padding: 32px 28px;
            box-sizing: border-box;
            box-shadow: -15px 0 40px rgba(0,0,0,0.8);
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
            font-size: 20px;
            font-weight: 800;
            color: #ffffff;
            display: flex;
            align-items: center;
            gap: 12px;
            letter-spacing: -0.3px;
        }
        .vapi-drawer-subtitle {
            font-size: 13px;
            color: #94a3b8;
            margin-bottom: 24px;
            line-height: 1.5;
        }
        .vapi-form-group {
            margin-bottom: 22px;
        }
        .vapi-form-label {
            display: block;
            font-size: 12px;
            font-weight: 700;
            color: #cbd5e1;
            margin-bottom: 8px;
            letter-spacing: 0.2px;
        }
        .vapi-input-dark {
            width: 100%;
            background: #141720;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            color: #ffffff;
            padding: 10px 14px;
            font-size: 13px;
            box-sizing: border-box;
            transition: all 0.2s;
        }
        .vapi-input-dark:focus {
            border-color: #38bdf8;
            outline: none;
            box-shadow: 0 0 0 2px rgba(56, 189, 248, 0.2);
        }

        /* Switches & Sliders */
        .vapi-switch-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px 0;
            border-top: 1px solid rgba(255, 255, 255, 0.05);
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
        
        .vapi-switch {
            position: relative;
            display: inline-block;
            width: 44px;
            height: 24px;
            margin-bottom: 0;
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

        .vapi-range-slider {
            width: 100%;
            accent-color: #10b981;
            height: 6px;
            background: #272d3b;
            border-radius: 3px;
            outline: none;
            cursor: pointer;
        }

        .vapi-accordion-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px 0;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            cursor: pointer;
            color: #cbd5e1;
            font-weight: 700;
            font-size: 13px;
        }

        .vapi-pill-group {
            display: flex;
            background: #14171f;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            padding: 3px;
            gap: 4px;
        }
        .vapi-pill-opt {
            flex: 1;
            text-align: center;
            padding: 7px 10px;
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

    <aside class="right-side" style="background-color: #0b0d11;">
        <form id="form_vapi_agent" method="POST" action="php/SaveAIAgent.php">
            <input type="hidden" name="agent_id" id="input_agent_id" value="<?=$agent['agent_id']?>" />
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
            <input type="hidden" name="background_sound_volume" id="input_background_sound_volume" value="<?=$background_sound_volume?>" />

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
                                <span class="vapi-badge" id="badge_status_indicator"><?=$agent['status']=='Y'?'Ativo':'Inativo'?></span>
                                <span class="vapi-badge vapi-badge-published"><i class="fa fa-check-circle"></i> Published</span>
                            </div>
                        </div>
                    </div>
                    <div class="vapi-actions">
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
                                    <div class="vapi-card-stat-val" id="disp_stt_acc">98.4%</div>
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
                                    <div class="vapi-card-stat-val" id="disp_llm_intel">92</div>
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
                                    <div class="vapi-card-stat-val" id="disp_voice_hum">96</div>
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
                        <textarea name="greeting_message" id="textarea_greeting" class="vapi-textarea-dark" rows="3" placeholder="Olá! Eu sou a Sofia da nossa empresa. Como posso te ajudar hoje?"><?=htmlspecialchars($agent['greeting_message'])?></textarea>
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
                            <div>
                                <span class="vapi-section-title"><i class="fa fa-wrench text-orange"></i> Tools (Funções & Webhooks da IA)</span>
                                <div style="font-size:12px; color:#8392a5; margin-top:4px;">Ferramentas que o modelo de IA pode chamar automaticamente durante a conversa.</div>
                            </div>
                            <button type="button" class="vapi-btn vapi-btn-primary" id="btn_add_custom_tool"><i class="fa fa-plus"></i> Criar Custom Tool</button>
                        </div>

                        <table class="vapi-tools-table" id="tools_table_element">
                            <thead>
                                <tr>
                                    <th style="width: 50px;">Status</th>
                                    <th>Ferramenta</th>
                                    <th>Tipo</th>
                                    <th>Descrição / Ação</th>
                                    <th style="width: 110px; text-align: right;">Ações</th>
                                </tr>
                            </thead>
                            <tbody id="tools_table_tbody">
                                <!-- Rendered dynamically via JS -->
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
                            <label class="vapi-form-label">Critérios de Sucesso da Chamada (Structured Output JSON)</label>
                            <textarea name="analysis_schema_json" class="vapi-textarea-dark vapi-prompt-editor" rows="8"><?=!empty($agent['analysis_schema_json']) ? htmlspecialchars($agent['analysis_schema_json']) : '{"qualification": ["Interessado", "Sem interesse", "Caixa Postal", "Pediu para retornar"], "extract_fields": ["nome", "cpf", "horario_preferencial", "resumo_conversa"]}'?></textarea>
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
                                    <select name="status" id="select_agent_status" class="vapi-select-dark" style="width:100%;">
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
                <option value="groq|whisper-large-v3-turbo">Groq Whisper Turbo (90ms • $0.003/min)</option>
                <option value="elevenlabs|scribe_v1">ElevenLabs Scribe v1 (450ms • $0.010/min)</option>
                <option value="openai|whisper-1">OpenAI Whisper (950ms • $0.012/min)</option>
                <option value="azure|azure-default">Azure Speech (650ms • $0.025/min)</option>
            </select>
        </div>

        <div class="vapi-form-group">
            <label class="vapi-form-label"><i class="fa fa-globe"></i> Language</label>
            <select id="modal_stt_language_select" class="vapi-input-dark">
                <option value="pt-BR">Brazilian Portuguese (pt-BR)</option>
                <option value="en-US">English (en-US)</option>
                <option value="es-ES">Spanish (es-ES)</option>
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
                    <option value="groq">Groq Whisper Turbo</option>
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
            Configure the LLM model that powers your assistant's reasoning and conversation abilities.
        </div>

        <div class="vapi-form-group">
            <label class="vapi-form-label">Model <i class="fa fa-info-circle text-muted"></i></label>
            <select id="modal_llm_model_select" class="vapi-input-dark">
                <optgroup label="Groq (Ultra-Rápido)">
                    <option value="groq|llama-3.3-70b-versatile">Groq Llama 3.3 70B (180ms • $0.008/min • 92 Intel)</option>
                    <option value="groq|llama-3.1-8b-instant">Groq Llama 3.1 8B Instant (80ms • $0.002/min • 75 Intel)</option>
                </optgroup>
                <optgroup label="OpenAI (Modelos Oficiais 2025/2026)">
                    <option value="openai|gpt-4o-mini">OpenAI GPT-4o Mini (320ms • $0.004/min • 88 Intel)</option>
                    <option value="openai|gpt-4o">OpenAI GPT-4o (620ms • $0.022/min • 99 Intel)</option>
                    <option value="openai|chatgpt-4o-latest">OpenAI ChatGPT-4o Latest (590ms • $0.022/min • 99 Intel)</option>
                    <option value="openai|gpt-5.6">OpenAI GPT-5.6 Preview (650ms • $0.035/min • 100 Intel)</option>
                    <option value="openai|gpt-5.5">OpenAI GPT-5.5 Preview (580ms • $0.030/min • 100 Intel)</option>
                    <option value="openai|gpt-5-preview">OpenAI GPT-5 Preview (520ms • $0.028/min • 100 Intel)</option>
                    <option value="openai|gpt-4.5-preview">OpenAI GPT-4.5 Preview (850ms • $0.050/min • 100 Intel)</option>
                    <option value="openai|o3-mini">OpenAI o3-mini (550ms • $0.012/min • 97 Intel - Reasoning)</option>
                    <option value="openai|o3">OpenAI o3 (950ms • $0.045/min • 100 Intel - Full Reasoning)</option>
                    <option value="openai|o1-mini">OpenAI o1-mini (750ms • $0.015/min • 95 Intel - Reasoning)</option>
                    <option value="openai|o1">OpenAI o1 (1200ms • $0.080/min • 100 Intel - Deep Reasoning)</option>
                    <option value="openai|gpt-4-turbo">OpenAI GPT-4 Turbo (720ms • $0.040/min • 96 Intel)</option>
                    <option value="openai|gpt-3.5-turbo">OpenAI GPT-3.5 Turbo (240ms • $0.003/min • 72 Intel)</option>
                </optgroup>
                <optgroup label="Anthropic & DeepSeek">
                    <option value="anthropic|claude-3-5-sonnet-20241022">Claude 3.5 Sonnet (750ms • $0.030/min • 98 Intel)</option>
                    <option value="deepseek|deepseek-chat">DeepSeek V3 (280ms • $0.003/min • 90 Intel)</option>
                </optgroup>
            </select>
        </div>

        <div class="vapi-form-group">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                <label class="vapi-form-label" style="margin-bottom:0;"><i class="fa fa-thermometer-half"></i> Temperature</label>
                <span class="vapi-badge" id="disp_temp_badge"><?=$agent['temperature']?></span>
            </div>
            <div style="font-size:11px; color:#64748b; margin-bottom:8px;">Controls randomness. Lower values are more deterministic, higher values more creative.</div>
            <input type="range" id="modal_temp_slider" class="vapi-range-slider" min="0.0" max="1.0" step="0.05" value="<?=$agent['temperature']?>" />
            <div style="display:flex; justify-content:space-between; font-size:10px; color:#64748b; margin-top:4px;">
                <span>Precise</span>
                <span>Creative</span>
            </div>
        </div>

        <!-- Collapsible Advanced -->
        <div class="vapi-accordion-header" id="adv_llm_toggle">
            <span>Advanced</span>
            <i class="fa fa-chevron-down"></i>
        </div>
        <div id="adv_llm_body" style="padding-top: 15px;">
            <div class="vapi-form-group">
                <label class="vapi-form-label"><i class="fa fa-comment"></i> Max Tokens</label>
                <div style="font-size:11px; color:#64748b; margin-bottom:6px;">Max number of tokens the assistant can generate in each turn of the conversation.</div>
                <input type="number" id="modal_max_tokens_input" class="vapi-input-dark" value="<?=$max_tokens?>" />
            </div>

            <div class="vapi-form-group">
                <label class="vapi-form-label"><i class="fa fa-database"></i> Prompt Cache Retention</label>
                <select id="modal_cache_select" class="vapi-input-dark">
                    <option value="in_memory">In-Memory Prompt Cache (Fastest)</option>
                    <option value="disabled">Disabled</option>
                </select>
            </div>

            <div class="vapi-form-group">
                <label class="vapi-form-label"><i class="fa fa-check-square-o"></i> Tool Strict Compatibility</label>
                <select id="modal_tool_strict_select" class="vapi-input-dark">
                    <option value="N" <?=$tool_strict_compatibility=='N'?'selected':''?>>Off</option>
                    <option value="Y" <?=$tool_strict_compatibility=='Y'?'selected':''?>>On (Strict Mode)</option>
                </select>
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
            Configure the text-to-speech voice your assistant uses to speak.
        </div>

        <div class="vapi-form-group">
            <label class="vapi-form-label">Voice Provider & Engine</label>
            <select id="modal_voice_provider_select" class="vapi-input-dark">
                <option value="cartesia">Cartesia Sonic (90ms • $0.020/min • 96 Humanness)</option>
                <option value="elevenlabs">ElevenLabs Multilingual v2 (650ms • $0.036/min • 92 Humanness)</option>
                <option value="openai">OpenAI TTS-1 (350ms • $0.015/min • 84 Humanness)</option>
                <option value="azure">Microsoft Azure Speech (400ms • $0.016/min • 90 Humanness)</option>
            </select>
        </div>

        <div class="vapi-form-group">
            <label class="vapi-form-label">Voice Model</label>
            <div class="vapi-pill-group" style="margin-bottom: 8px;">
                <div class="vapi-pill-opt active" id="tab_voice_library">Voice library</div>
                <div class="vapi-pill-opt" id="tab_custom_voice_id">Custom voice ID</div>
            </div>
            
            <div id="voice_library_view">
                <select id="modal_voice_id_select" class="vapi-input-dark">
                    <option value="cartesia|cartesia-pt-br-sofia">Sofia • Cartesia Sonic (Natural PT-BR Female)</option>
                    <option value="cartesia|cartesia-pt-br-lucas">Lucas • Cartesia Sonic (Professional PT-BR Male)</option>
                    <option value="elevenlabs|21m00Tcm4TlvDq8ikWAM">Rachel • ElevenLabs (Conversational Female)</option>
                    <option value="elevenlabs|EXAVITQu4vr4xnSDxMaL">Sarah • ElevenLabs (Warm & Reassuring Female)</option>
                    <option value="elevenlabs|AZnzlk1XvdvUeBnXmlld">Domi • ElevenLabs (Energetic Female)</option>
                    <option value="elevenlabs|pNInz6obpgDQGcFmaJgB">Adam • ElevenLabs (Deep & Professional Male)</option>
                    <option value="elevenlabs|TxGEqnHWrfWFTfGW9XjX">Josh • ElevenLabs (Conversational Young Male)</option>
                    <option value="elevenlabs|XrExE9yKIg1WjnnlVkGX">Matilda • ElevenLabs (Expressive Female)</option>
                    <option value="openai|nova">Nova • OpenAI (Energetic & Friendly)</option>
                    <option value="openai|alloy">Alloy • OpenAI (Neutral Male)</option>
                    <option value="azure|pt-BR-FranciscaNeural">Francisca • Azure Speech (Natural PT-BR Female)</option>
                    <option value="azure|pt-BR-AntonioNeural">Antonio • Azure Speech (Professional PT-BR Male)</option>
                    <option value="azure|pt-BR-ThalitaNeural">Thalita • Azure Speech (Expressive PT-BR Female)</option>
                </select>
            </div>

            <div id="custom_voice_id_view" style="display:none;">
                <input type="text" id="modal_custom_voice_id_input" class="vapi-input-dark" placeholder="Cole o Voice ID customizado aqui..." value="<?=$agent['voice_id']?>" />
            </div>
        </div>

        <div class="vapi-form-group">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                <label class="vapi-form-label" style="margin-bottom:0;"><i class="fa fa-tachometer"></i> Speed</label>
                <span class="vapi-badge" id="disp_speed_badge"><?=$voice_speed?>x</span>
            </div>
            <input type="range" id="modal_speed_slider" class="vapi-range-slider" min="0.75" max="1.50" step="0.05" value="<?=$voice_speed?>" />
            <div style="display:flex; justify-content:space-between; font-size:10px; color:#64748b; margin-top:4px;">
                <span>Slower (0.75x)</span>
                <span>Normal (1.0x)</span>
                <span>Faster (1.5x)</span>
            </div>
        </div>

        <div class="vapi-form-group">
            <label class="vapi-form-label"><i class="fa fa-volume-up"></i> Ruído de Fundo (Ambiente de Escritório / Call Center)</label>
            <div style="font-size:11px; color:#64748b; margin-bottom:8px;">Simula um ambiente sonoro realista para eliminar o silêncio robótico na chamada.</div>
            <div class="vapi-pill-group" id="bg_sound_pills">
                <div class="vapi-pill-opt <?=($background_sound=='off'?'active':'')?>" data-bgsound="off">Desativado</div>
                <div class="vapi-pill-opt <?=($background_sound=='office'||$background_sound=='default'||$background_sound=='callcenter'?'active':'')?>" data-bgsound="office">Escritório / Call Center</div>
                <div class="vapi-pill-opt <?=($background_sound=='typing'?'active':'')?>" data-bgsound="typing">Digitação no Teclado</div>
                <div class="vapi-pill-opt <?=($background_sound=='room'?'active':'')?>" data-bgsound="room">Ruído de Sala</div>
            </div>
        </div>

        <div class="vapi-form-group" id="bg_volume_group" style="<?=($background_sound=='off'?'display:none;':'')?>">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <label class="vapi-form-label"><i class="fa fa-sliders"></i> Volume do Som de Fundo</label>
                <span id="modal_bg_vol_val" style="font-size:12px; color:#38bdf8; font-weight:700;"><?=intval($background_sound_volume * 100)?>%</span>
            </div>
            <input type="range" id="modal_bg_vol_slider" class="vapi-range-slider" min="0.02" max="0.30" step="0.01" value="<?=$background_sound_volume?>" />
            <div style="display:flex; justify-content:space-between; font-size:10px; color:#64748b; margin-top:4px;">
                <span>Sutil (2%)</span>
                <span>Recomendado (10%)</span>
                <span>Forte (30%)</span>
            </div>
        </div>

        <!-- Collapsible Advanced -->
        <div class="vapi-accordion-header" id="adv_voice_toggle">
            <span>Advanced</span>
            <i class="fa fa-chevron-down"></i>
        </div>
        <div id="adv_voice_body" style="padding-top: 15px;">
            <div class="vapi-form-group">
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

            <div class="vapi-form-group">
                <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
                    <label class="vapi-form-label" style="margin-bottom:0;">Style Exaggeration</label>
                    <span class="vapi-badge" id="disp_style_badge"><?=$voice_style_exaggeration?></span>
                </div>
                <input type="range" id="modal_style_slider" class="vapi-range-slider" min="0.0" max="1.0" step="0.05" value="<?=$voice_style_exaggeration?>" />
            </div>

            <div class="vapi-form-group">
                <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
                    <label class="vapi-form-label" style="margin-bottom:0;">Optimize Latency</label>
                    <span class="vapi-badge" id="disp_opt_lat_badge"><?=$voice_optimize_latency?></span>
                </div>
                <input type="range" id="modal_opt_lat_slider" class="vapi-range-slider" min="0" max="4" step="1" value="<?=$voice_optimize_latency?>" />
            </div>
        </div>

        <div style="margin-top: 30px;">
            <button type="button" class="vapi-btn vapi-btn-primary btn-close-drawer" style="width:100%; justify-content:center;">Apply Voice Settings</button>
        </div>
    </div>
</div>

<!-- 4. CUSTOM TOOL DRAWER -->
<div class="vapi-modal-backdrop" id="modal_tool_drawer">
    <div class="vapi-drawer" style="width: 540px;">
        <div class="vapi-drawer-header">
            <span class="vapi-drawer-title" id="tool_drawer_title"><i class="fa fa-wrench text-orange"></i> Custom Tool</span>
            <button type="button" class="btn btn-link text-muted btn-close-drawer" style="font-size:18px;"><i class="fa fa-times"></i></button>
        </div>
        <div class="vapi-drawer-subtitle">
            Configure an external function, webhook or action that the assistant can execute.
        </div>

        <input type="hidden" id="tool_drawer_mode" value="add" />
        <input type="hidden" id="tool_edit_orig_id" value="" />

        <div class="vapi-form-group">
            <label class="vapi-form-label">Modelo Rápido / Template</label>
            <select id="tool_quick_template_select" class="vapi-input-dark">
                <option value="">-- Selecione um Modelo Pré-configurado --</option>
                <option value="custom_webhook">Custom Webhook (Chamar API Externa / CRM)</option>
                <option value="end_call">End Call (Encerrar Ligação Telefônica)</option>
                <option value="transfer_call">Transfer Call (Transferir para Ramal/Humano)</option>
                <option value="voicemail_tool">Voicemail Tool (Detecção de Caixa Postal)</option>
                <option value="capturar_cpf">Capturar & Validar CPF</option>
            </select>
        </div>

        <div class="vapi-form-group">
            <label class="vapi-form-label">Identificador / Nome da Função (snake_case)</label>
            <input type="text" id="tool_input_name" class="vapi-input-dark" placeholder="ex: transfer_call ou consultar_saldo" />
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="vapi-form-group">
                    <label class="vapi-form-label">Tipo da Ferramenta</label>
                    <select id="tool_input_type" class="vapi-input-dark">
                        <option value="Custom tool">Custom tool (Webhook / API)</option>
                        <option value="Transfer call">Transfer call</option>
                        <option value="End call">End call</option>
                        <option value="Voicemail tool">Voicemail tool</option>
                        <option value="Function">Function</option>
                    </select>
                </div>
            </div>
            <div class="col-md-6">
                <div class="vapi-form-group">
                    <label class="vapi-form-label">Versão</label>
                    <input type="text" id="tool_input_version" class="vapi-input-dark" value="Latest" />
                </div>
            </div>
        </div>

        <div class="vapi-form-group">
            <label class="vapi-form-label">Descrição (Como o modelo deve saber quando chamar)</label>
            <textarea id="tool_input_desc" class="vapi-textarea-dark" rows="3" placeholder="Descreva claramente o que esta função faz para que o LLM a acione na hora certa..."></textarea>
        </div>

        <div id="tool_webhook_fields">
            <div class="vapi-form-group">
                <label class="vapi-form-label">URL do Endpoint Webhook</label>
                <input type="text" id="tool_input_url" class="vapi-input-dark" placeholder="https://api.empresa.com.br/v1/webhook" />
            </div>
            <div class="vapi-form-group">
                <label class="vapi-form-label">Método HTTP</label>
                <select id="tool_input_method" class="vapi-input-dark">
                    <option value="POST">POST (application/json)</option>
                    <option value="GET">GET</option>
                </select>
            </div>
        </div>

        <div class="vapi-form-group">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                <label class="vapi-form-label" style="margin-bottom:0;">Parâmetros (JSON Schema)</label>
                <button type="button" class="vapi-btn" id="btn_tool_format_json" style="padding:2px 8px; font-size:11px;"><i class="fa fa-code"></i> Formatar JSON</button>
            </div>
            <textarea id="tool_input_params" class="vapi-textarea-dark vapi-prompt-editor" rows="6">{}</textarea>
        </div>

        <div class="vapi-switch-row">
            <div class="vapi-switch-info">
                <div class="vapi-switch-title">Ferramenta Habilitada</div>
                <div class="vapi-switch-desc">Se ativada, a IA terá permissão de acioná-la durante a ligação.</div>
            </div>
            <label class="vapi-switch">
                <input type="checkbox" id="tool_input_enabled" checked />
                <span class="vapi-slider"></span>
            </label>
        </div>

        <div style="margin-top: 30px;">
            <button type="button" class="vapi-btn vapi-btn-primary" id="btn_save_tool_drawer" style="width:100%; justify-content:center;">Salvar Ferramenta</button>
        </div>
    </div>
</div>

<?php print $ui->standardizedThemeJS(); ?>
<script type="text/javascript">
// ==========================================
// VAPI REACTIVE CATALOG & LIVE PIPELINE SYNC
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
        'openai|gpt-4o-mini': { name: 'GPT-4o Mini', provider: 'openai', model: 'gpt-4o-mini', sub: 'OpenAI • Ultra-Fast & Cost Effective', latency: 320, cost: 0.004, intel: 88, icon: 'fa fa-cube text-green' },
        'openai|gpt-4o': { name: 'GPT-4o', provider: 'openai', model: 'gpt-4o', sub: 'OpenAI • Flagship Multimodal', latency: 620, cost: 0.022, intel: 99, icon: 'fa fa-cube text-green' },
        'openai|chatgpt-4o-latest': { name: 'ChatGPT-4o Latest', provider: 'openai', model: 'chatgpt-4o-latest', sub: 'OpenAI • Dynamic Latest GPT-4o Checkpoint', latency: 590, cost: 0.022, intel: 99, icon: 'fa fa-cube text-green' },
        'openai|gpt-5.6': { name: 'GPT-5.6 Preview', provider: 'openai', model: 'gpt-5.6', sub: 'OpenAI • Next-Gen 5.6 Frontier Model', latency: 650, cost: 0.035, intel: 100, icon: 'fa fa-cube text-green' },
        'openai|gpt-5.5': { name: 'GPT-5.5 Preview', provider: 'openai', model: 'gpt-5.5', sub: 'OpenAI • Next-Gen 5.5 Frontier Model', latency: 580, cost: 0.030, intel: 100, icon: 'fa fa-cube text-green' },
        'openai|gpt-5-preview': { name: 'GPT-5 Preview', provider: 'openai', model: 'gpt-5-preview', sub: 'OpenAI • Next-Gen Flagship Model', latency: 520, cost: 0.028, intel: 100, icon: 'fa fa-cube text-green' },
        'openai|gpt-4.5-preview': { name: 'GPT-4.5 Preview', provider: 'openai', model: 'gpt-4.5-preview', sub: 'OpenAI • Ultra-Deep Knowledge & Frontier', latency: 850, cost: 0.050, intel: 100, icon: 'fa fa-cube text-green' },
        'openai|o3-mini': { name: 'o3-mini', provider: 'openai', model: 'o3-mini', sub: 'OpenAI • Next-Gen Fast Reasoning', latency: 550, cost: 0.012, intel: 97, icon: 'fa fa-cube text-green' },
        'openai|o3': { name: 'o3 (Full Reasoning)', provider: 'openai', model: 'o3', sub: 'OpenAI • Full-Scale Frontier Reasoning', latency: 950, cost: 0.045, intel: 100, icon: 'fa fa-cube text-green' },
        'openai|o1-mini': { name: 'o1-mini', provider: 'openai', model: 'o1-mini', sub: 'OpenAI • STEM & Coding Reasoning', latency: 750, cost: 0.015, intel: 95, icon: 'fa fa-cube text-green' },
        'openai|o1': { name: 'o1 (Deep Reasoning)', provider: 'openai', model: 'o1', sub: 'OpenAI • Deep Complex Reasoning', latency: 1200, cost: 0.080, intel: 100, icon: 'fa fa-cube text-green' },
        'openai|gpt-4-turbo': { name: 'GPT-4 Turbo', provider: 'openai', model: 'gpt-4-turbo', sub: 'OpenAI • High Precision 128k Context', latency: 720, cost: 0.040, intel: 96, icon: 'fa fa-cube text-green' },
        'openai|gpt-3.5-turbo': { name: 'GPT-3.5 Turbo', provider: 'openai', model: 'gpt-3.5-turbo', sub: 'OpenAI • Legacy Fast Standard', latency: 240, cost: 0.003, intel: 72, icon: 'fa fa-cube text-green' },
        'anthropic|claude-3-5-sonnet-20241022': { name: 'Claude 3.5 Sonnet', provider: 'anthropic', model: 'claude-3-5-sonnet-20241022', sub: 'Anthropic • Deep Reasoning', latency: 750, cost: 0.030, intel: 98, icon: 'fa fa-diamond text-orange' },
        'deepseek|deepseek-chat': { name: 'DeepSeek V3', provider: 'deepseek', model: 'deepseek-chat', sub: 'DeepSeek • Low Cost & High Intel', latency: 280, cost: 0.003, intel: 90, icon: 'fa fa-star text-blue' }
    },
    voice: {
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

var currentTools = <?=json_encode($agentTools)?>;
if (!Array.isArray(currentTools)) currentTools = [];

function syncToolsHiddenInput() {
    $('#input_tools_json').val(JSON.stringify(currentTools));
}

function renderToolsTable() {
    var $tbody = $('#tools_table_tbody');
    $tbody.empty();

    if (!currentTools || currentTools.length === 0) {
        $tbody.html('<tr><td colspan="5" style="text-align:center; color:#64748b; padding:30px;">Nenhuma ferramenta cadastrada. Clique em "Criar Custom Tool" para adicionar.</td></tr>');
        return;
    }

    currentTools.forEach(function(tool) {
        var isChecked = tool.enabled !== false ? 'checked' : '';
        var typeBadgeColor = 'bg-blue';
        var iconClass = 'fa-wrench text-blue';
        var iconBg = 'rgba(56, 189, 248, 0.15)';

        if (tool.type === 'Transfer call') {
            typeBadgeColor = 'bg-green';
            iconClass = 'fa-phone-square text-green';
            iconBg = 'rgba(16, 185, 129, 0.15)';
        } else if (tool.type === 'End call') {
            typeBadgeColor = 'bg-red';
            iconClass = 'fa-phone-slash text-red';
            iconBg = 'rgba(239, 68, 68, 0.15)';
        } else if (tool.type === 'Voicemail tool') {
            typeBadgeColor = 'bg-yellow';
            iconClass = 'fa-envelope-open text-yellow';
            iconBg = 'rgba(245, 158, 11, 0.15)';
        }

        var html = '<tr>' +
            '<td>' +
                '<label class="vapi-switch">' +
                    '<input type="checkbox" class="tool-toggle" data-tool-id="' + tool.id + '" ' + isChecked + ' />' +
                    '<span class="vapi-slider"></span>' +
                '</label>' +
            '</td>' +
            '<td>' +
                '<div style="display:flex; align-items:center;">' +
                    '<div class="vapi-tool-icon" style="background:' + iconBg + ';">' +
                        '<i class="fa ' + iconClass + '"></i>' +
                    '</div>' +
                    '<div>' +
                        '<div class="vapi-tool-name">' + (tool.name || tool.id) + '</div>' +
                        '<div style="font-size:11px; color:#64748b;">v' + (tool.version || 'Latest') + '</div>' +
                    '</div>' +
                '</div>' +
            '</td>' +
            '<td><span class="vapi-badge">' + (tool.type || 'Custom tool') + '</span></td>' +
            '<td><div class="vapi-tool-desc">' + (tool.description || 'Sem descrição') + '</div></td>' +
            '<td style="text-align: right;">' +
                '<button type="button" class="vapi-action-btn btn-edit-tool" data-tool-id="' + tool.id + '" title="Editar"><i class="fa fa-pencil"></i></button> ' +
                '<button type="button" class="vapi-action-btn btn-danger-tool btn-delete-tool" data-tool-id="' + tool.id + '" title="Remover"><i class="fa fa-trash"></i></button>' +
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
        $('#tool_drawer_title').html('<i class="fa fa-wrench text-orange"></i> Criar Custom Tool');
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

// Update all 3 Cards, Telemetry, and Sync Hidden Inputs
function updatePipelineUI() {
    var sttKey = $('#input_stt_provider').val() + '|' + $('#input_stt_model').val();
    var llmKey = $('#input_llm_provider').val() + '|' + $('#input_llm_model').val();
    var curProv = $('#input_voice_provider').val() || 'cartesia';
    var curVoiceId = $('#input_voice_id').val() || 'cartesia-pt-br-sofia';
    var voiceKey = curProv + '|' + curVoiceId;

    var stt = VAPI_CONFIG.stt[sttKey] || { name: $('#input_stt_model').val(), sub: $('#input_stt_provider').val(), latency: 120, cost: 0.005, accuracy: '98.0%' };
    var llm = VAPI_CONFIG.llm[llmKey] || { name: $('#input_llm_model').val(), sub: $('#input_llm_provider').val(), latency: 250, cost: 0.008, intel: 85 };
    
    // Voice resolution: check if known in library
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
            sub: '<span style="color:#38bdf8; font-weight:700;">' + curProv.toUpperCase() + '</span> • ID: <span style="color:#f43f5e; font-family:monospace; font-weight:bold;">' + curVoiceId + '</span>', 
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

    $('#disp_total_latency').html('~' + totalLat + '<span style="font-size:14px; color:#94a3b8;">ms</span>');
    $('#disp_total_cost').html('~$' + totalCost.toFixed(3) + '<span style="font-size:14px; color:#94a3b8;">/min</span>');

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
    // Initial Render
    renderToolsTable();
    updatePipelineUI();

    // Tab Navigation
    $('.vapi-tab').click(function(e) {
        e.preventDefault();
        $('.vapi-tab').removeClass('active');
        $(this).addClass('active');
        var target = $(this).data('tab');
        $('.vapi-tab-view').hide();
        $('#view_' + target).show();
    });

    // Drawers Open
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

    // Transcriber Drawer Events
    $('#modal_stt_provider_select').change(function() {
        var parts = $(this).val().split('|');
        $('#input_stt_provider').val(parts[0]);
        $('#input_stt_model').val(parts[1]);
        updatePipelineUI();
    });

    $('#modal_stt_language_select').change(function() {
        $('#input_stt_language').val($(this).val());
    });

    $('#modal_turn_taking').change(function() {
        $('#input_intelligent_turn_taking').val($(this).is(':checked') ? 'Y' : 'N');
    });

    $('#modal_denoising').change(function() {
        $('#input_background_denoising').val($(this).is(':checked') ? 'Y' : 'N');
    });

    $('#modal_silence_slider').on('input', function() {
        var val = $(this).val();
        $('#disp_silence_timeout_badge').text(val + ' ms');
        $('#input_silence_timeout_ms').val(val);
    });

    $('#modal_stt_fallback_select').change(function() {
        $('#input_stt_fallback_provider').val($(this).val());
    });

    // Model Drawer Events
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

    $('#modal_max_tokens_input').on('input', function() {
        $('#input_max_tokens').val($(this).val());
    });

    $('#modal_cache_select').change(function() {
        $('#input_prompt_cache_retention').val($(this).val());
    });

    $('#modal_tool_strict_select').change(function() {
        $('#input_tool_strict_compatibility').val($(this).val());
    });

    // Voice Drawer Events
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

    // Background Sound Pills
    $('#bg_sound_pills .vapi-pill-opt').click(function() {
        $('#bg_sound_pills .vapi-pill-opt').removeClass('active');
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
        $('.vapi-pill-opt', $(this).parent()).removeClass('active');
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
        $('.vapi-pill-opt', $(this).parent()).removeClass('active');
        $(this).addClass('active');
        $('#voice_library_view').hide();
        $('#custom_voice_id_view').show();

        var customVal = $('#modal_custom_voice_id_input').val().trim();
        if (customVal) {
            $('#input_voice_id').val(customVal);
            $('#input_voice_name').val('Custom (' + (customVal.length > 12 ? customVal.substring(0, 12) + '...' : customVal) + ')');
        }
        updatePipelineUI();
    });

    $('#select_first_message_mode').change(function() {
        $('#input_first_message_mode').val($(this).val());
    });

    $('#select_agent_status').change(function() {
        var val = $(this).val();
        $('#input_status').val(val);
        $('#badge_status_indicator').text(val === 'Y' ? 'Ativo' : 'Inativo');
    });

    // Presets Click Handler
    $('.vapi-preset-pill').click(function() {
        $('.vapi-preset-pill').removeClass('active');
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

    // Prompt Templates
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

    // Test Call Button
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
                    alert("📞 Ligação disparada com sucesso para " + phone + "! O telefone tocará em instantes.");
                } else {
                    alert(res.message || 'Erro ao discar: ' + JSON.stringify(res));
                }
            }, 'json').fail(function(xhr) {
                $(self).prop('disabled', false).html('<i class="fa fa-phone"></i> Talk / Testar Chamada');
                var msg = "Erro ao contatar servidor de telefonia.";
                try {
                    var data = JSON.parse(xhr.responseText);
                    if (data && data.message) msg = data.message;
                } catch(e) {}
                alert(msg);
            });
        }
    });

    // Custom Tools Handling
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

    // Form Submission with Save & Feedback
    $('#form_vapi_agent').submit(function(e) {
        e.preventDefault();

        // Ensure custom voice ID is persisted
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
        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Salvando...');

        syncToolsHiddenInput();
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
