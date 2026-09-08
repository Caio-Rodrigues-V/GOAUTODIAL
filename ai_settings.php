<?php
/**
 * @file        ai_settings.php
 * @brief       AI Voice Engine API Keys & Settings
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

$groqKey = $aiHandler->getSetting('groq_api_key', '');
$deepgramKey = $aiHandler->getSetting('deepgram_api_key', '');
$cartesiaKey = $aiHandler->getSetting('cartesia_api_key', '');
$openaiKey = $aiHandler->getSetting('openai_api_key', '');
$elevenlabsKey = $aiHandler->getSetting('elevenlabs_api_key', '');
$geminiKey = $aiHandler->getSetting('gemini_api_key', '');
$anthropicKey = $aiHandler->getSetting('anthropic_api_key', '');
$deepseekKey = $aiHandler->getSetting('deepseek_api_key', '');
$aiServerUrl = $aiHandler->getSetting('ai_server_url', 'http://127.0.0.1:8765');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Dial GO - Chaves de API de IA</title>
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
                <i class="fa fa-key"></i> Provedores de IA & Chaves de API
                <small>Insira suas chaves de API diretas dos provedores para pagar sem intermediários</small>
            </h1>
            <ol class="breadcrumb">
                <li><a href="./index.php"><i class="fa fa-home"></i> Home</a></li>
                <li><a href="ai_agents.php">Agentes de IA</a></li>
                <li class="active">Configurações</li>
            </ol>
        </section>

        <section class="content">
            <div class="row">
                <div class="col-md-8 col-md-offset-2">
                    <div class="box box-primary">
                        <div class="box-header with-border">
                            <h3 class="box-title"><i class="fa fa-plug"></i> Conexões de API (Zero Markup Vapi / Retell)</h3>
                        </div>
                        <form id="form_ai_settings" method="POST" action="php/SaveAISettings.php">
                            <div class="box-body" style="padding: 25px;">
                                
                                <div class="form-group">
                                    <label><i class="fa fa-bolt text-yellow"></i> Groq API Key (LLM Ultra-Rápido ~120ms)</label>
                                    <input type="password" name="groq_api_key" class="form-control" value="<?=htmlspecialchars($groqKey)?>" placeholder="gsk_..." />
                                    <small class="text-muted">Obtenha em: <a href="https://console.groq.com" target="_blank">console.groq.com</a></small>
                                </div>

                                <div class="form-group">
                                    <label><i class="fa fa-microphone text-blue"></i> Deepgram API Key (Transcrição STT em Tempo Real)</label>
                                    <input type="password" name="deepgram_api_key" class="form-control" value="<?=htmlspecialchars($deepgramKey)?>" placeholder="Token Deepgram Nova-2" />
                                    <small class="text-muted">Obtenha em: <a href="https://console.deepgram.com" target="_blank">console.deepgram.com</a></small>
                                </div>

                                <div class="form-group">
                                    <label><i class="fa fa-volume-up text-green"></i> Cartesia API Key (Voz TTS Sonic Ultrarrealista)</label>
                                    <input type="password" name="cartesia_api_key" class="form-control" value="<?=htmlspecialchars($cartesiaKey)?>" placeholder="cartesia_..." />
                                    <small class="text-muted">Obtenha em: <a href="https://play.cartesia.ai" target="_blank">play.cartesia.ai</a></small>
                                </div>

                                <hr/>

                                <div class="form-group">
                                    <label><i class="fa fa-cube"></i> OpenAI API Key</label>
                                    <input type="password" name="openai_api_key" class="form-control" value="<?=htmlspecialchars($openaiKey)?>" placeholder="sk-proj-..." />
                                    <small class="text-muted">Obtenha em: <a href="https://platform.openai.com" target="_blank">platform.openai.com</a></small>
                                </div>

                                <div class="form-group">
                                    <label><i class="fa fa-music text-purple"></i> ElevenLabs API Key</label>
                                    <input type="password" name="elevenlabs_api_key" class="form-control" value="<?=htmlspecialchars($elevenlabsKey)?>" placeholder="xi-api-key..." />
                                    <small class="text-muted">Obtenha em: <a href="https://elevenlabs.io" target="_blank">elevenlabs.io</a></small>
                                </div>

                                <div class="form-group">
                                    <label><i class="fa fa-comments text-orange"></i> Anthropic Claude API Key</label>
                                    <input type="password" name="anthropic_api_key" class="form-control" value="<?=htmlspecialchars($anthropicKey)?>" placeholder="sk-ant-api..." />
                                    <small class="text-muted">Obtenha em: <a href="https://console.anthropic.com" target="_blank">console.anthropic.com</a></small>
                                </div>

                                <div class="form-group">
                                    <label><i class="fa fa-rocket text-aqua"></i> DeepSeek API Key</label>
                                    <input type="password" name="deepseek_api_key" class="form-control" value="<?=htmlspecialchars($deepseekKey)?>" placeholder="sk-..." />
                                    <small class="text-muted">Obtenha em: <a href="https://platform.deepseek.com" target="_blank">platform.deepseek.com</a></small>
                                </div>

                                <div class="form-group">
                                    <label><i class="fa fa-google text-red"></i> Google Gemini API Key</label>
                                    <input type="password" name="gemini_api_key" class="form-control" value="<?=htmlspecialchars($geminiKey)?>" placeholder="AIzaSy..." />
                                    <small class="text-muted">Obtenha em: <a href="https://aistudio.google.com" target="_blank">aistudio.google.com</a></small>
                                </div>

                                <hr/>

                                <div class="form-group">
                                    <label><i class="fa fa-server"></i> Dial GO AI Server URL (Media WebSocket Engine)</label>
                                    <input type="text" name="ai_server_url" class="form-control" value="<?=htmlspecialchars($aiServerUrl)?>" placeholder="http://127.0.0.1:8765" />
                                    <small class="text-muted">Endereço do microserviço de áudio do Dial GO Voice AI.</small>
                                </div>

                            </div>

                            <div class="box-footer" style="padding: 20px;">
                                <button type="submit" class="btn btn-primary btn-lg"><i class="fa fa-save"></i> Salvar Configurações</button>
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
$(document).ready(function() {
    $('#form_ai_settings').submit(function(e) {
        e.preventDefault();
        var formData = $(this).serialize();
        $.post('php/SaveAISettings.php', formData, function(res) {
            if (res.status == 1) {
                alert(res.message);
                window.location.href = 'ai_agents.php';
            } else {
                alert(res.message || 'Erro ao salvar configurações.');
            }
        }, 'json').fail(function() {
            alert('Erro de conexão com o servidor.');
        });
    });
});
</script>
</body>
</html>
