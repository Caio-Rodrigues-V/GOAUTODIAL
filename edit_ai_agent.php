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
        /* MODERN VAPI DARK THEME & PREMIUM POLISH */
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
            font-size: 24px;
            font-weight: 800;
            color: #ffffff;
            letter-spacing: -0.5px;
        }
        .vapi-progress-bar {
            height: 6px;
            background: rgba(255, 255, 255, 0.06);
            border-radius: 4px;
            overflow: hidden;
            display: flex;
            gap: 2px;
        }
        .vapi-seg-stt { background: #10b981; border-radius: 3px; }
        .vapi-seg-llm { background: #38bdf8; border-radius: 3px; }
        .vapi-seg-tts { background: #ec4899; border-radius: 3px; }

        /* Presets Bar */
        .vapi-presets-bar {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 24px;
            flex-wrap: wrap;
            background: #11141b;
            padding: 8px 14px;
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.06);
        }
        .vapi-preset-label {
            font-size: 12px;
            color: #64748b;
            font-weight: 700;
            margin-right: 4px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .vapi-preset-pill {
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.06);
            color: #cbd5e1;
            padding: 6px 14px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        .vapi-preset-pill:hover {
            background: rgba(255, 255, 255, 0.08);
            border-color: rgba(255, 255, 255, 0.15);
            color: #fff;
        }
        .vapi-preset-pill.active {
            background: rgba(56, 189, 248, 0.12);
            border-color: #38bdf8;
            color: #38bdf8;
            font-weight: 700;
        }

        /* 3 Pipeline Cards */
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
            position: relative;
            cursor: pointer;
            transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
            box-shadow: 0 4px 18px rgba(0,0,0,0.25);
        }
        .vapi-card:hover {
            border-color: rgba(56, 189, 248, 0.4);
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.45);
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
            letter-spacing: 0.9px;
            display: flex;
            align-items: center;
            gap: 8px;
            color: #94a3b8;
        }
        .vapi-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
        }
        .vapi-dot-stt { background: #10b981; box-shadow: 0 0 10px #10b981; }
        .vapi-dot-llm { background: #38bdf8; box-shadow: 0 0 10px #38bdf8; }
        .vapi-dot-tts { background: #ec4899; box-shadow: 0 0 10px #ec4899; }
        
        .vapi-card-edit-btn {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.08);
            color: #94a3b8;
            font-size: 13px;
            width: 28px;
            height: 28px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            border-radius: 6px;
            transition: all 0.2s;
        }
        .vapi-card:hover .vapi-card-edit-btn {
            color: #38bdf8;
            background: rgba(56, 189, 248, 0.1);
            border-color: rgba(56, 189, 248, 0.3);
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
            color: #94a3b8;
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .vapi-card-stats {
            display: flex;
            justify-content: space-between;
            border-top: 1px solid rgba(255, 255, 255, 0.06);
            padding-top: 14px;
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
            width: 480px;
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
            background: #161922;
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #f1f5f9;
            padding: 11px 16px;
            border-radius: 9px;
            font-size: 13px;
            box-sizing: border-box;
            transition: all 0.2s;
        }
        .vapi-input-dark:focus {
            border-color: #38bdf8;
            outline: none;
            background: #1b202b;
            box-shadow: 0 0 0 2px rgba(56, 189, 248, 0.2);
        }
        .vapi-switch-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
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
            line-height: 1.4;
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
            background-color: #232a38;
            transition: .25s;
            border-radius: 24px;
        }
        .vapi-slider:before {
            position: absolute; content: "";
            height: 18px; width: 18px;
            left: 3px; bottom: 3px;
            background-color: white;
            transition: .25s;
            border-radius: 50%;
            box-shadow: 0 2px 4px rgba(0,0,0,0.4);
        }
        input:checked + .vapi-slider { background-color: #10b981; }
        input:checked + .vapi-slider:before { transform: translateX(20px); }

        /* Range Slider */
        .vapi-range-slider {
            width: 100%;
            accent-color: #10b981;
            height: 6px;
            background: #232a38;
            border-radius: 3px;
            outline: none;
        }

        /* Collapsible Section */
        .vapi-accordion-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 0;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            cursor: pointer;
            color: #cbd5e1;
            font-weight: 700;
            font-size: 13px;
        }

        /* Pill segmented control */
        .vapi-pill-group {
            display: flex;
            background: #14171f;
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 9px;
            padding: 3px;
            gap: 4px;
        }
        .vapi-pill-opt {
            flex: 1;
            text-align: center;
            padding: 7px 10px;
            font-size: 12px;
            font-weight: 600;
            border-radius: 7px;
            cursor: pointer;
            color: #94a3b8;
            transition: all 0.2s;
        }
        .vapi-pill-opt.active {
            background: #222b3a;
            color: #38bdf8;
            font-weight: 700;
        }
    </style>
</head>

<?php print $ui->creamyBody(); ?>
<div class="wrapper">
    <?php print $ui->creamyHeader($user); ?>
    <?php print $ui->getSidebar($user->getUserId(), $user->getUserName(), $user->getUserRole(), $user->getUserAvatar()); ?>

    <aside class="right-side" style="background-color: #0d0f12;">
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
                                            <div style="display: flex; gap: 6px;">
                                                <button type="button" class="vapi-action-btn btn-edit-tool" data-tool-id="<?=htmlspecialchars($t['id'])?>" title="Editar Ferramenta"><i class="fa fa-pencil"></i></button>
                                                <button type="button" class="vapi-action-btn btn-danger-tool btn-delete-tool" data-tool-id="<?=htmlspecialchars($t['id'])?>" title="Excluir Ferramenta"><i class="fa fa-trash"></i></button>
                                            </div>
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
            Configure the LLM model that powers your assistant's reasoning, and conversation abilities.
        </div>

        <div class="vapi-form-group">
            <label class="vapi-form-label">Model <i class="fa fa-info-circle text-muted"></i></label>
            <select id="modal_llm_model_select" class="vapi-input-dark">
                <option value="groq|llama-3.3-70b-versatile">Groq Llama 3.3 70B (180ms • $0.008/min • 92 Intel)</option>
                <option value="groq|llama-3.1-8b-instant">Groq Llama 3.1 8B Instant (80ms • $0.002/min • 75 Intel)</option>
                <option value="openai|gpt-4o-mini">OpenAI GPT-4o Mini (420ms • $0.006/min • 88 Intel)</option>
                <option value="openai|gpt-4o">OpenAI GPT-4o (690ms • $0.023/min • 99 Intel)</option>
                <option value="openai|gpt-4.1">OpenAI GPT-4.1 (690ms • $0.023/min • 20 Intel)</option>
                <option value="anthropic|claude-3-5-sonnet-20241022">Claude 3.5 Sonnet (750ms • $0.030/min • 98 Intel)</option>
                <option value="deepseek|deepseek-chat">DeepSeek V3 (300ms • $0.003/min • 90 Intel)</option>
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
                <label class="vapi-form-label"><i class="fa fa-forward"></i> Fast Turns</label>
                <div style="font-size:11px; color:#64748b; margin-bottom:6px;">How many turns at the start of the call to answer with a smaller, faster model before switching.</div>
                <input type="number" id="modal_fast_turns_input" class="vapi-input-dark" value="0" min="0" max="10" />
            </div>

            <div class="vapi-form-group">
                <label class="vapi-form-label"><i class="fa fa-database"></i> Prompt Cache Retention</label>
                <div style="font-size:11px; color:#64748b; margin-bottom:6px;">Controls how long cached prompts are retained. Use 24h for extended caching.</div>
                <div class="vapi-pill-group">
                    <div class="vapi-pill-opt <?=($prompt_cache_retention=='in_memory'?'active':'')?>" data-cache="in_memory">In memory</div>
                    <div class="vapi-pill-opt <?=($prompt_cache_retention=='24_hours'?'active':'')?>" data-cache="24_hours">24 hours</div>
                </div>
            </div>

            <div class="vapi-form-group">
                <label class="vapi-form-label"><i class="fa fa-key"></i> Prompt Cache Key</label>
                <div style="font-size:11px; color:#64748b; margin-bottom:6px;">Optional key to share cached prefixes across requests. Max 64 characters.</div>
                <input type="text" id="modal_prompt_cache_key" class="vapi-input-dark" placeholder="Optional cache key" maxlength="64" />
            </div>

            <div class="vapi-form-group">
                <label class="vapi-form-label"><i class="fa fa-wrench"></i> Tool Strict Compatibility</label>
                <div style="font-size:11px; color:#64748b; margin-bottom:6px;">How to adapt tool schemas for endpoints that reject strict-mode validation keywords.</div>
                <select id="modal_tool_strict_select" class="vapi-input-dark">
                    <option value="N">Off</option>
                    <option value="Y">On (Strict Mode)</option>
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
            <label class="vapi-form-label">Voice model <i class="fa fa-info-circle text-muted"></i></label>
            <select id="modal_voice_provider_select" class="vapi-input-dark">
                <option value="elevenlabs|eleven_multilingual_v2">Eleven Multilingual v2 (810ms • $0.036/min • 76 Humanness)</option>
                <option value="cartesia|sonic-multilingual">Cartesia Sonic (90ms • $0.020/min • 96 Humanness)</option>
                <option value="openai|tts-1">OpenAI TTS-1 (380ms • $0.015/min • 82 Humanness)</option>
                <option value="deepgram|aura">Deepgram Aura (140ms • $0.015/min • 80 Humanness)</option>
            </select>
        </div>

        <div class="vapi-form-group">
            <label class="vapi-form-label">Voice</label>
            <div class="vapi-pill-group" style="margin-bottom: 8px;">
                <div class="vapi-pill-opt active" id="tab_voice_library">Voice library</div>
                <div class="vapi-pill-opt" id="tab_custom_voice_id">Custom voice ID</div>
            </div>
            
            <div id="voice_library_view">
                <select id="modal_voice_id_select" class="vapi-input-dark">
                    <option value="cartesia-pt-br-sofia">Sofia • Young adult woman with natural, confident tone (PT-BR)</option>
                    <option value="cartesia-pt-br-lucas">Lucas • Professional, authoritative male voice (PT-BR)</option>
                    <option value="21m00Tcm4TlvDq8ikWAM">Rachel • Calm and conversational (ElevenLabs)</option>
                    <option value="PznTnBc8X6pvixs9UkQm">Sarah • Mature, reassuring young adult woman (ElevenLabs)</option>
                    <option value="AZnzlk1XvdvUeBnXmlld">Domi • Strong and energetic (ElevenLabs)</option>
                    <option value="nova">Nova • Energetic and friendly (OpenAI)</option>
                </select>
            </div>

            <div id="custom_voice_id_view" style="display:none;">
                <input type="text" id="modal_custom_voice_id_input" class="vapi-input-dark" placeholder="Cole o Voice ID customizado aqui..." />
            </div>
        </div>

        <div class="vapi-form-group">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                <label class="vapi-form-label" style="margin-bottom:0;"><i class="fa fa-tachometer"></i> Speed</label>
                <span class="vapi-badge" id="disp_speed_badge"><?=$voice_speed?>x</span>
            </div>
            <div style="font-size:11px; color:#64748b; margin-bottom:8px;">The speed of the voice output.</div>
            <input type="range" id="modal_speed_slider" class="vapi-range-slider" min="0.75" max="1.50" step="0.05" value="<?=$voice_speed?>" />
            <div style="display:flex; justify-content:space-between; font-size:10px; color:#64748b; margin-top:4px;">
                <span>Slower</span>
                <span>Faster</span>
            </div>
        </div>

        <div class="vapi-form-group">
            <label class="vapi-form-label"><i class="fa fa-music"></i> Background Sound</label>
            <div style="font-size:11px; color:#64748b; margin-bottom:8px;">Background sound played in the call. Default for phone calls is 'office' and for web calls is 'off'.</div>
            <div class="vapi-pill-group" id="bg_sound_pills">
                <div class="vapi-pill-opt <?=($background_sound=='off'?'active':'')?>" data-bgsound="off">Off</div>
                <div class="vapi-pill-opt <?=($background_sound=='office'?'active':'')?>" data-bgsound="office">Office</div>
                <div class="vapi-pill-opt <?=($background_sound=='default'?'active':'')?>" data-bgsound="default">Default</div>
                <div class="vapi-pill-opt <?=($background_sound=='custom'?'active':'')?>" data-bgsound="custom">Custom</div>
            </div>
        </div>

        <div class="vapi-form-group">
            <label class="vapi-form-label"><i class="fa fa-book"></i> Pronunciation Dictionaries</label>
            <div style="font-size:11px; color:#64748b; margin-bottom:6px;">Override pronunciation for specific words on this voice.</div>
            <button type="button" class="vapi-btn" style="width:100%; justify-content:center;"><i class="fa fa-plus"></i> Create dictionary</button>
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
                    <div class="vapi-switch-desc">Reuses audio for repeated phrases instead of re-synthesising them, which cuts latency and cost.</div>
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
                <div style="display:flex; justify-content:space-between; font-size:10px; color:#64748b; margin-top:4px;">
                    <span>More Variable <i class="fa fa-info-circle"></i></span>
                    <span>More Stable <i class="fa fa-info-circle"></i></span>
                </div>
            </div>

            <div class="vapi-form-group">
                <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
                    <label class="vapi-form-label" style="margin-bottom:0;">Clarity + Similarity</label>
                    <span class="vapi-badge" id="disp_clarity_badge"><?=$voice_clarity?></span>
                </div>
                <input type="range" id="modal_clarity_slider" class="vapi-range-slider" min="0.0" max="1.0" step="0.05" value="<?=$voice_clarity?>" />
                <div style="display:flex; justify-content:space-between; font-size:10px; color:#64748b; margin-top:4px;">
                    <span>Low <i class="fa fa-info-circle"></i></span>
                    <span>High <i class="fa fa-info-circle"></i></span>
                </div>
            </div>

            <div class="vapi-form-group">
                <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
                    <label class="vapi-form-label" style="margin-bottom:0;">Style Exaggeration</label>
                    <span class="vapi-badge" id="disp_style_badge"><?=$voice_style_exaggeration?></span>
                </div>
                <input type="range" id="modal_style_slider" class="vapi-range-slider" min="0.0" max="1.0" step="0.05" value="<?=$voice_style_exaggeration?>" />
                <div style="display:flex; justify-content:space-between; font-size:10px; color:#64748b; margin-top:4px;">
                    <span>None (Fastest)</span>
                    <span>Exaggerated</span>
                </div>
            </div>

            <div class="vapi-form-group">
                <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
                    <label class="vapi-form-label" style="margin-bottom:0;">Optimize Streaming Latency</label>
                    <span class="vapi-badge" id="disp_opt_lat_badge"><?=$voice_optimize_latency?></span>
                </div>
                <input type="range" id="modal_opt_lat_slider" class="vapi-range-slider" min="0" max="4" step="1" value="<?=$voice_optimize_latency?>" />
                <div style="display:flex; justify-content:space-between; font-size:10px; color:#64748b; margin-top:4px;">
                    <span>More Latency</span>
                    <span>Less Latency</span>
                </div>
            </div>

            <div class="vapi-switch-row">
                <div class="vapi-switch-info">
                    <div class="vapi-switch-title">Use speaker boost</div>
                    <div class="vapi-switch-desc">Boost the similarity of the synthesized speech and the voice at the cost of some generation speed.</div>
                </div>
                <label class="vapi-switch">
                    <input type="checkbox" id="modal_speaker_boost" />
                    <span class="vapi-slider"></span>
                </label>
            </div>

            <div class="vapi-switch-row">
                <div class="vapi-switch-info">
                    <div class="vapi-switch-title">Auto mode</div>
                    <div class="vapi-switch-desc">Reduces latency for complete sentences but may affect quality with partial phrases.</div>
                </div>
                <label class="vapi-switch">
                    <input type="checkbox" id="modal_auto_mode" />
                    <span class="vapi-slider"></span>
                </label>
            </div>

            <div class="vapi-switch-row">
                <div class="vapi-switch-info">
                    <div class="vapi-switch-title"><i class="fa fa-code"></i> SSML Parsing</div>
                    <div class="vapi-switch-desc">Interprets SSML tags in the text, so you can control pronunciation and pauses. Off by default to save latency.</div>
                </div>
                <label class="vapi-switch">
                    <input type="checkbox" id="modal_ssml_parsing" />
                    <span class="vapi-slider"></span>
                </label>
            </div>

            <!-- Fallback Voices -->
            <div style="margin-top: 20px; border-top: 1px solid #1c222e; padding-top: 16px;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 12px;">
                    <div>
                        <div style="font-weight:700; color:#fff; font-size:13px;"><i class="fa fa-shield text-yellow"></i> Fallback Voices</div>
                        <div style="font-size:11px; color:#64748b;">Voice used when the primary voice fails.</div>
                    </div>
                    <button type="button" class="vapi-btn" style="padding:4px 10px; font-size:12px;"><i class="fa fa-plus"></i> Add</button>
                </div>

                <div style="background:#161922; border: 1px solid #232a38; border-radius:8px; padding:14px;">
                    <div style="display:flex; justify-content:space-between; margin-bottom:10px;">
                        <span style="font-weight:700; font-size:12px; color:#cbd5e1;">Fallback 1 • ElevenLabs</span>
                        <i class="fa fa-trash text-muted" style="cursor:pointer;"></i>
                    </div>
                    <div class="vapi-form-group" style="margin-bottom:10px;">
                        <label class="vapi-form-label" style="font-size:11px;">Provider</label>
                        <select class="vapi-input-dark" style="padding:6px 10px; font-size:12px;">
                            <option>ElevenLabs</option>
                            <option>Cartesia</option>
                            <option>OpenAI</option>
                        </select>
                    </div>
                    <div class="vapi-form-group" style="margin-bottom:10px;">
                        <label class="vapi-form-label" style="font-size:11px;">Voice</label>
                        <select class="vapi-input-dark" style="padding:6px 10px; font-size:12px;">
                            <option>Sarah mature, reassuring (Young adult woman)</option>
                            <option>Rachel (Calm & conversational)</option>
                        </select>
                    </div>
                    <div class="vapi-form-group" style="margin-bottom:0;">
                        <label class="vapi-form-label" style="font-size:11px;">Model</label>
                        <select class="vapi-input-dark" style="padding:6px 10px; font-size:12px;">
                            <option>Eleven Multilingual v2 (810ms • $0.036/min • 76 Humanness)</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div style="margin-top: 30px;">
            <button type="button" class="vapi-btn vapi-btn-primary btn-close-drawer" style="width:100%; justify-content:center;">Apply Voice Settings</button>
        </div>
    </div>
</div>

<!-- 4. TOOLS SETTINGS / CREATE / EDIT DRAWER -->
<div class="vapi-modal-backdrop" id="modal_tool_drawer">
    <div class="vapi-drawer" style="width: 540px;">
        <div class="vapi-drawer-header">
            <span class="vapi-drawer-title" id="tool_drawer_modal_title"><i class="fa fa-wrench text-orange"></i> Ferramenta / Tool Settings</span>
            <button type="button" class="btn btn-link text-muted btn-close-drawer" style="font-size:18px;"><i class="fa fa-times"></i></button>
        </div>
        <div class="vapi-drawer-subtitle">
            Configure funções e webhooks executados pelo agente de IA durante a chamada de voz.
        </div>

        <input type="hidden" id="tool_edit_orig_id" value="" />
        <input type="hidden" id="tool_drawer_mode" value="add" />

        <!-- Quick Templates -->
        <div class="vapi-form-group">
            <label class="vapi-form-label">Modelo Pré-configurado (Template Rápido)</label>
            <select id="tool_quick_template_select" class="vapi-input-dark">
                <option value="">-- Selecione um modelo ou configure abaixo --</option>
                <option value="custom_webhook">Custom Webhook / Integração Externa (API)</option>
                <option value="end_call">End Call (Desligar a Chamada)</option>
                <option value="transfer_call">Transfer Call (Transferir para Humano / Fila)</option>
                <option value="voicemail_tool">Voicemail Detection (Caixa Postal)</option>
                <option value="capturar_cpf">Capturar Dados / CPF do Cliente</option>
                <option value="response_control">Response Control (Controle de Turno)</option>
            </select>
        </div>

        <div class="vapi-form-group">
            <label class="vapi-form-label">Nome da Função (Identifier / Chamada pelo Modelo) <span style="color:#ef4444;">*</span></label>
            <input type="text" id="tool_input_name" class="vapi-input-dark" placeholder="Ex: consultar_pedido, transfer_call, end_call" required />
            <div style="font-size:11px; color:#64748b; margin-top:4px;">Apenas letras minúsculas, números e sublinhados (_). Sem espaços.</div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="vapi-form-group">
                    <label class="vapi-form-label">Tipo da Ferramenta</label>
                    <select id="tool_input_type" class="vapi-input-dark">
                        <option value="Custom tool">Custom tool (Webhook)</option>
                        <option value="Function">Function</option>
                        <option value="End call">End call</option>
                        <option value="Transfer call">Transfer call</option>
                        <option value="Voicemail tool">Voicemail tool</option>
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
            <label class="vapi-form-label">Descrição / Prompt da Ferramenta <span style="color:#ef4444;">*</span></label>
            <textarea id="tool_input_desc" class="vapi-textarea-dark" rows="3" placeholder="Instruções claras para o LLM saber quando e como invocar esta ferramenta..."></textarea>
        </div>

        <!-- Webhook Settings -->
        <div id="tool_webhook_fields" style="background:#131720; border:1px solid rgba(255,255,255,0.08); border-radius:10px; padding:16px; margin-bottom:20px;">
            <div style="font-size:13px; font-weight:700; color:#cbd5e1; margin-bottom:12px; display:flex; align-items:center; gap:8px;">
                <i class="fa fa-plug text-blue"></i> Configurações de Webhook / API
            </div>
            <div class="vapi-form-group" style="margin-bottom:12px;">
                <label class="vapi-form-label">Server / Webhook URL (POST)</label>
                <input type="url" id="tool_input_url" class="vapi-input-dark" placeholder="https://sua-api.com.br/v1/ai-webhook" />
            </div>
            <div class="vapi-form-group" style="margin-bottom:0;">
                <label class="vapi-form-label">Método HTTP</label>
                <select id="tool_input_method" class="vapi-input-dark" style="width:120px;">
                    <option value="POST">POST</option>
                    <option value="GET">GET</option>
                    <option value="PUT">PUT</option>
                </select>
            </div>
        </div>

        <!-- Parameters Schema -->
        <div class="vapi-form-group">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                <label class="vapi-form-label" style="margin-bottom:0;">Esquema de Parâmetros (JSON Schema)</label>
                <button type="button" class="btn btn-xs btn-link text-info" id="btn_tool_format_json"><i class="fa fa-magic"></i> Formatar JSON</button>
            </div>
            <textarea id="tool_input_params" class="vapi-textarea-dark vapi-prompt-editor" rows="6" placeholder="Defina o JSON schema com type, properties e required..."></textarea>
        </div>

        <div class="vapi-switch-row">
            <div class="vapi-switch-info">
                <div class="vapi-switch-title">Ferramenta Ativa</div>
                <div class="vapi-switch-desc">Permite que o assistente de voz utilize esta ferramenta em tempo real.</div>
            </div>
            <label class="vapi-switch">
                <input type="checkbox" id="tool_input_enabled" checked />
                <span class="vapi-slider"></span>
            </label>
        </div>

        <div style="margin-top: 30px; display:flex; gap:10px;">
            <button type="button" class="vapi-btn btn-close-drawer" style="flex:1; justify-content:center;">Cancelar</button>
            <button type="button" class="vapi-btn vapi-btn-primary" id="btn_save_tool_drawer" style="flex:2; justify-content:center;"><i class="fa fa-check"></i> Salvar Ferramenta</button>
        </div>
    </div>
</div>

<?php print $ui->standardizedThemeJS(); ?>
<script type="text/javascript">
var currentTools = <?=json_encode($agentTools)?> || [];

function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function getToolIconConfig(id, type) {
    var toolId = (id || '').toLowerCase();
    var toolType = (type || '').toLowerCase();
    if (toolId === 'end_call' || toolType.indexOf('end') !== -1) {
        return { icon: 'fa-phone', bg: '#ef4444' };
    } else if (toolId === 'voicemail_tool' || toolType.indexOf('voicemail') !== -1) {
        return { icon: 'fa-microphone-slash', bg: '#8b5cf6' };
    } else if (toolId === 'transfer_call' || toolType.indexOf('transfer') !== -1) {
        return { icon: 'fa-exchange', bg: '#3b82f6' };
    } else if (toolId.indexOf('cpf') !== -1 || toolId.indexOf('captur') !== -1 || toolId.indexOf('lead') !== -1) {
        return { icon: 'fa-id-card-o', bg: '#10b981' };
    } else {
        return { icon: 'fa-bolt', bg: '#f59e0b' };
    }
}

function renderToolsTable() {
    var $tbody = $('#tools_table_body');
    $tbody.empty();

    if (!currentTools || currentTools.length === 0) {
        $tbody.append('<tr><td colspan="5" style="text-align:center; padding:30px; color:#64748b;"><i class="fa fa-info-circle"></i> Nenhuma ferramenta configurada. Clique em "+ Add Tool" para adicionar uma ferramenta ou integração.</td></tr>');
        return;
    }

    currentTools.forEach(function(t) {
        var cfg = getToolIconConfig(t.id, t.type);
        var isEnabled = (t.enabled !== false && t.enabled !== 0 && t.enabled !== '0' && t.enabled !== 'N');
        var version = t.version || 'Latest';
        var desc = t.description || '';
        if (t.url) {
            desc += ' <span style="color:#38bdf8; font-size:11px; font-family:monospace;">• Webhook: ' + escapeHtml(t.url) + '</span>';
        }

        var row = '<tr data-tool-id="' + escapeHtml(t.id) + '">' +
            '<td>' +
                '<div style="display: flex; align-items: flex-start;">' +
                    '<div class="vapi-tool-icon" style="background:' + cfg.bg + '; color:#fff;">' +
                        '<i class="fa ' + cfg.icon + '"></i>' +
                    '</div>' +
                    '<div>' +
                        '<span class="vapi-tool-name">' + escapeHtml(t.name || t.id) + '</span>' +
                        '<div class="vapi-tool-desc">' + desc + '</div>' +
                    '</div>' +
                '</div>' +
            '</td>' +
            '<td><span class="vapi-badge">' + escapeHtml(t.type || 'Custom tool') + '</span></td>' +
            '<td>' +
                '<label class="vapi-switch">' +
                    '<input type="checkbox" class="tool-toggle" data-tool-id="' + escapeHtml(t.id) + '" ' + (isEnabled ? 'checked' : '') + ' />' +
                    '<span class="vapi-slider"></span>' +
                '</label>' +
            '</td>' +
            '<td><span class="vapi-badge">' + escapeHtml(version) + '</span></td>' +
            '<td>' +
                '<div style="display: flex; gap: 6px;">' +
                    '<button type="button" class="vapi-action-btn btn-edit-tool" data-tool-id="' + escapeHtml(t.id) + '" title="Editar Ferramenta"><i class="fa fa-pencil"></i></button>' +
                    '<button type="button" class="vapi-action-btn btn-danger-tool btn-delete-tool" data-tool-id="' + escapeHtml(t.id) + '" title="Excluir Ferramenta"><i class="fa fa-trash"></i></button>' +
                '</div>' +
            '</td>' +
        '</tr>';

        $tbody.append(row);
    });
}

function syncToolsHiddenInput() {
    $('#input_tools_json').val(JSON.stringify(currentTools));
}

function openToolDrawer(mode, toolData) {
    $('#tool_drawer_mode').val(mode);
    $('#tool_quick_template_select').val('');

    if (mode === 'edit' && toolData) {
        $('#tool_drawer_modal_title').html('<i class="fa fa-pencil text-yellow"></i> Editar Ferramenta (' + escapeHtml(toolData.name || toolData.id) + ')');
        $('#tool_edit_orig_id').val(toolData.id);
        $('#tool_input_name').val(toolData.name || toolData.id);
        $('#tool_input_type').val(toolData.type || 'Custom tool');
        $('#tool_input_version').val(toolData.version || 'Latest');
        $('#tool_input_desc').val(toolData.description || '');
        $('#tool_input_url').val(toolData.url || '');
        $('#tool_input_method').val(toolData.method || 'POST');

        var paramsStr = '';
        if (toolData.parameters) {
            if (typeof toolData.parameters === 'object') {
                paramsStr = JSON.stringify(toolData.parameters, null, 2);
            } else {
                try {
                    paramsStr = JSON.stringify(JSON.parse(toolData.parameters), null, 2);
                } catch(e) {
                    paramsStr = toolData.parameters;
                }
            }
        } else {
            paramsStr = '{\n  "type": "object",\n  "properties": {},\n  "required": []\n}';
        }
        $('#tool_input_params').val(paramsStr);
        var isEnabled = (toolData.enabled !== false && toolData.enabled !== 0 && toolData.enabled !== '0' && toolData.enabled !== 'N');
        $('#tool_input_enabled').prop('checked', isEnabled);
    } else {
        $('#tool_drawer_modal_title').html('<i class="fa fa-plus text-orange"></i> Adicionar Nova Ferramenta');
        $('#tool_edit_orig_id').val('');
        $('#tool_input_name').val('');
        $('#tool_input_type').val('Custom tool');
        $('#tool_input_version').val('Latest');
        $('#tool_input_desc').val('');
        $('#tool_input_url').val('');
        $('#tool_input_method').val('POST');
        $('#tool_input_params').val('{\n  "type": "object",\n  "properties": {\n    "parametro": {\n      "type": "string",\n      "description": "Exemplo de parâmetro"\n    }\n  },\n  "required": []\n}');
        $('#tool_input_enabled').prop('checked', true);
    }

    $('#modal_tool_drawer').fadeIn(200).css('display', 'flex');
}

$(document).ready(function() {
    // Initial Render of tools table
    renderToolsTable();

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

    // ==================== TOOLS EVENT HANDLERS ====================

    // Open Add Tool Modal
    $('#btn_add_custom_tool').click(function() {
        openToolDrawer('add');
    });

    // Open Edit Tool Modal
    $(document).on('click', '.btn-edit-tool', function(e) {
        e.preventDefault();
        e.stopPropagation();
        var toolId = String($(this).data('tool-id'));
        var tool = currentTools.find(function(t) { return String(t.id) === toolId; });
        if (tool) {
            openToolDrawer('edit', tool);
        }
    });

    // Delete Tool
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

    // Toggle Tool Active/Inactive Switch
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

    // Format JSON Schema button
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

    // Quick Template selector in Tool Drawer
    $('#tool_quick_template_select').change(function() {
        var val = $(this).val();
        if (val === 'custom_webhook') {
            $('#tool_input_name').val('consultar_api');
            $('#tool_input_type').val('Custom tool');
            $('#tool_input_desc').val('Envia os parâmetros coletados durante a chamada para um endpoint webhook externo e injeta a resposta na conversa.');
            $('#tool_input_url').val('https://api.empresa.com.br/v1/webhook');
            $('#tool_input_method').val('POST');
            $('#tool_input_params').val(JSON.stringify({
                type: "object",
                properties: {
                    parametro: { type: "string", description: "Descrição do parâmetro" }
                },
                required: ["parametro"]
            }, null, 2));
        } else if (val === 'end_call') {
            $('#tool_input_name').val('end_call');
            $('#tool_input_type').val('End call');
            $('#tool_input_desc').val('Encerra a chamada telefônica imediatamente após o cliente se despedir ou concluir o atendimento.');
            $('#tool_input_url').val('');
            $('#tool_input_params').val(JSON.stringify({
                type: "object",
                properties: {
                    reason: { type: "string", description: "Motivo da finalização da chamada" }
                }
            }, null, 2));
        } else if (val === 'transfer_call') {
            $('#tool_input_name').val('transfer_call');
            $('#tool_input_type').val('Transfer call');
            $('#tool_input_desc').val('Transfere a ligação em andamento para um ramal ou atendente humano especializado.');
            $('#tool_input_url').val('');
            $('#tool_input_params').val(JSON.stringify({
                type: "object",
                properties: {
                    destination: { type: "string", description: "Ramal ou fila de destino (Ex: 8300)" },
                    summary: { type: "string", description: "Resumo do que o cliente precisa para o atendente humano" }
                }
            }, null, 2));
        } else if (val === 'voicemail_tool') {
            $('#tool_input_name').val('voicemail_tool');
            $('#tool_input_type').val('Voicemail tool');
            $('#tool_input_desc').val('Acionado automaticamente ao detectar caixa postal, secretária eletrônica ou mensagem gravada.');
            $('#tool_input_url').val('');
            $('#tool_input_params').val(JSON.stringify({
                type: "object",
                properties: {}
            }, null, 2));
        } else if (val === 'capturar_cpf') {
            $('#tool_input_name').val('capturar_cpf');
            $('#tool_input_type').val('Custom tool');
            $('#tool_input_desc').val('Armazena e valida o número de CPF informado pelo cliente para prosseguir com a consulta.');
            $('#tool_input_url').val('');
            $('#tool_input_params').val(JSON.stringify({
                type: "object",
                properties: {
                    cpf: { type: "string", description: "CPF com 11 dígitos numéricos" }
                },
                required: ["cpf"]
            }, null, 2));
        } else if (val === 'response_control') {
            $('#tool_input_name').val('response_control');
            $('#tool_input_type').val('Function');
            $('#tool_input_desc').val('Permite ao modelo ajustar pausas, tempo de resposta e dinâmica de fala.');
            $('#tool_input_url').val('');
            $('#tool_input_params').val(JSON.stringify({
                type: "object",
                properties: {}
            }, null, 2));
        }
    });

    // Save Tool in Drawer
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

        if (!name) {
            alert('Por favor, informe o Nome da Função (Identifier).');
            $('#tool_input_name').focus();
            return;
        }

        if (!desc) {
            alert('Por favor, forneça uma descrição clara para o modelo saber quando invocar a ferramenta.');
            $('#tool_input_desc').focus();
            return;
        }

        var parsedParams = {};
        if (rawParams) {
            try {
                parsedParams = JSON.parse(rawParams);
            } catch(e) {
                alert('O Esquema de Parâmetros não é um JSON válido: ' + e.message);
                $('#tool_input_params').focus();
                return;
            }
        }

        var toolObj = {
            id: name,
            name: name,
            type: type,
            version: version,
            description: desc,
            url: url,
            method: method,
            parameters: parsedParams,
            enabled: enabled
        };

        if (mode === 'edit' && origId) {
            var found = false;
            for (var i = 0; i < currentTools.length; i++) {
                if (String(currentTools[i].id) === String(origId)) {
                    currentTools[i] = toolObj;
                    found = true;
                    break;
                }
            }
            if (!found) {
                currentTools.push(toolObj);
            }
        } else {
            // Check if tool with same id already exists
            var existingIdx = -1;
            for (var j = 0; j < currentTools.length; j++) {
                if (String(currentTools[j].id) === name) {
                    existingIdx = j;
                    break;
                }
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

    // Form submission
    $('#form_vapi_agent').submit(function(e) {
        e.preventDefault();
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
