<?php
/**
 * @file        api_docs.php
 * @brief       Dialog DDM - Vapi Compatible REST API Documentation & Playground
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
$defaultAgentId = !empty($agents) ? $agents[0]['agent_id'] : 2;

$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || 
            (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ? 'https' : 'http';
$host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'dialddm.grupoddm.ia.br';
$apiBaseUrl = "{$protocol}://{$host}/v1";
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Dialog DDM - Documentação da API (Vapi Compatible)</title>
    <meta content='width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no' name='viewport'>

    <?php 
        print $ui->standardizedThemeCSS(); 
        print $ui->creamyThemeCSS();
    ?>
    <link href="css/style.css" rel="stylesheet" type="text/css" />
    <link href="css/dialog_ddm.css" rel="stylesheet" type="text/css" />
    <style>
        .doc-section {
            background: var(--ddm-surface);
            border: 1px solid var(--ddm-border);
            border-radius: var(--ddm-radius-lg);
            padding: 28px;
            margin-bottom: 24px;
            box-shadow: var(--ddm-shadow-xs);
        }
        .doc-section h2 {
            font-size: 19px;
            font-weight: 800;
            color: var(--ddm-text-primary);
            margin: 0 0 8px 0;
            letter-spacing: -0.3px;
        }
        .doc-section p {
            color: var(--ddm-text-secondary);
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 20px;
        }
        .endpoint-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 12px;
            border-radius: 8px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 14px;
        }
        .method-post {
            background: #ECFDF5;
            color: #047857;
            border: 1px solid #A7F3D0;
        }
        .method-get {
            background: #EFF6FF;
            color: #1D4ED8;
            border: 1px solid #BFDBFE;
        }
        .method-delete {
            background: #FEF2F2;
            color: #B91C1C;
            border: 1px solid #FECACA;
        }
        .code-block {
            background: #0F172A;
            color: #F8FAFC;
            padding: 18px 20px;
            border-radius: var(--ddm-radius-md);
            font-family: 'JetBrains Mono', monospace;
            font-size: 12.5px;
            line-height: 1.5;
            overflow-x: auto;
            position: relative;
            margin-bottom: 20px;
            border: 1px solid #334155;
        }
        .code-copy-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            color: #CBD5E1;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 11px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .code-copy-btn:hover {
            background: rgba(255,255,255,0.25);
            color: #FFF;
        }
        .param-table {
            width: 100%;
            margin-bottom: 20px;
        }
        .param-table th {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--ddm-text-secondary);
            border-bottom: 2px solid var(--ddm-border);
            padding: 10px 12px;
        }
        .param-table td {
            padding: 12px;
            border-top: 1px solid var(--ddm-border);
            font-size: 13px;
            vertical-align: top;
        }
        .param-name {
            font-family: 'JetBrains Mono', monospace;
            font-weight: 700;
            color: var(--ddm-primary);
        }
        .param-type {
            font-family: 'JetBrains Mono', monospace;
            font-size: 11px;
            color: var(--ddm-text-muted);
        }
        .nav-tabs-custom {
            border-bottom: 2px solid var(--ddm-border);
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
        }
        .nav-tab-btn {
            background: transparent;
            border: none;
            border-bottom: 2px solid transparent;
            padding: 8px 16px;
            font-size: 13.5px;
            font-weight: 600;
            color: var(--ddm-text-secondary);
            cursor: pointer;
            margin-bottom: -2px;
            transition: all 0.2s;
        }
        .nav-tab-btn.active {
            color: var(--ddm-primary);
            border-bottom-color: var(--ddm-primary);
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
                <a href="ai_agents.php">Agentes de IA</a>
                <span class="separator">/</span>
                <span class="current">Documentação da API</span>
            </div>

            <!-- Top Header -->
            <div class="ddm-header">
                <div class="ddm-header-left">
                    <div class="ddm-agent-avatar" style="background: linear-gradient(135deg, #FFF7ED 0%, #FFEDD5 100%); color: var(--ddm-primary);">
                        <i class="fa fa-code"></i>
                    </div>
                    <div>
                        <div style="font-size: 22px; font-weight: 800; color: var(--ddm-text-primary); letter-spacing: -0.4px;">
                            API REST & Gateway Vapi
                        </div>
                        <div style="font-size: 13px; color: var(--ddm-text-secondary); margin-top: 2px;">
                            Base URL: <strong style="font-family: 'JetBrains Mono', monospace; color: var(--ddm-primary);"><?php echo $apiBaseUrl; ?></strong> &bull; Tronco Oktor (500 Canais & 100 CPS)
                        </div>
                    </div>
                </div>
                <div class="ddm-header-actions">
                    <a href="<?php echo $apiBaseUrl; ?>/concurrency" target="_blank" class="ddm-btn ddm-btn-secondary">
                        <i class="fa fa-bolt"></i> Testar Concorrência
                    </a>
                </div>
            </div>

            <!-- Quickstart: Como usar no projeto vapi-caio -->
            <div class="doc-section" style="border-left: 4px solid var(--ddm-primary);">
                <h2><i class="fa fa-rocket text-orange"></i> Drop-in Replacement para Vapi (Microsserviços e vapi-caio)</h2>
                <p>
                    O gateway do Dialog DDM implementa a especificação exata da <strong>API Vapi (vapi.ai)</strong>. Para migrar seu backend Node.js, TypeScript ou Python para a nossa infraestrutura própria (economizando até 87% em custos de telefonia e IA), basta apontar a variável de ambiente:
                </p>
                <div class="code-block">
                    <button class="code-copy-btn" onclick="copyCode(this)">Copiar</button>
# Configuração no arquivo .env do seu projeto (ex: vapi-caio)
VAPI_BASE_URL=<?php echo $apiBaseUrl; ?>

VAPI_API_KEY=dialddm_live_key
DEFAULT_ASSISTANT_ID=<?php echo $defaultAgentId; ?>
                </div>
            </div>

            <!-- ENDPOINT 1: POST /v1/call/phone -->
            <div class="doc-section">
                <div class="endpoint-badge method-post">
                    <span>POST</span>
                    <span>/v1/call/phone</span>
                </div>
                <h2>Disparar Chamada Telefônica de Voz com IA</h2>
                <p>
                    Origina uma chamada telefônica através do Tronco SIP Oktor Telecom. Suporta personalização de variáveis dinâmicas no prompt, webhook de callback e metadados arbitrários.
                </p>

                <h4 style="font-size: 14px; font-weight: 700; color: var(--ddm-text-primary); margin-bottom: 12px;">Parâmetros do Body (JSON)</h4>
                <table class="param-table">
                    <thead>
                        <tr>
                            <th>Campo</th>
                            <th>Tipo</th>
                            <th>Obrigatório</th>
                            <th>Descrição</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><span class="param-name">assistantId</span></td>
                            <td><span class="param-type">string | number</span></td>
                            <td>Sim</td>
                            <td>ID do agente de IA cadastrado no Dialog DDM (ex: <code>"<?php echo $defaultAgentId; ?>"</code>).</td>
                        </tr>
                        <tr>
                            <td><span class="param-name">customer.number</span></td>
                            <td><span class="param-type">string</span></td>
                            <td>Sim</td>
                            <td>Telefone do cliente com DDD (ex: <code>"+5511999999999"</code> ou <code>"11999999999"</code>).</td>
                        </tr>
                        <tr>
                            <td><span class="param-name">customer.name</span></td>
                            <td><span class="param-type">string</span></td>
                            <td>Não</td>
                            <td>Nome do cliente para injeção automática no diálogo.</td>
                        </tr>
                        <tr>
                            <td><span class="param-name">serverUrl</span></td>
                            <td><span class="param-type">string</span></td>
                            <td>Não</td>
                            <td>URL do Webhook que receberá o relatório pós-chamada (<code>end-of-call-report</code>).</td>
                        </tr>
                        <tr>
                            <td><span class="param-name">maxConcurrency</span></td>
                            <td><span class="param-type">number</span></td>
                            <td>Não</td>
                            <td>Limite máximo de canais simultâneos para este disparo (Padrão: 50). Evita saturar os 500 canais totais.</td>
                        </tr>
                        <tr>
                            <td><span class="param-name">assistantOverrides.variableValues</span></td>
                            <td><span class="param-type">object</span></td>
                            <td>Não</td>
                            <td>Dicionário de variáveis dinâmicas (ex: <code>{"valor_total": "R$ 1.500,00", "empresa": "DDM"}</code>).</td>
                        </tr>
                        <tr>
                            <td><span class="param-name">metadata</span></td>
                            <td><span class="param-type">object</span></td>
                            <td>Não</td>
                            <td>Objeto JSON arbitrário que será devolvido intacto no webhook final (ex: <code>{"campaignCallId": 123}</code>).</td>
                        </tr>
                    </tbody>
                </table>

                <h4 style="font-size: 14px; font-weight: 700; color: var(--ddm-text-primary); margin-bottom: 10px;">Exemplo de Requisição (cURL & Node.js)</h4>
                
                <div class="code-block">
                    <button class="code-copy-btn" onclick="copyCode(this)">Copiar</button>
curl -X POST "<?php echo $apiBaseUrl; ?>/call/phone" \
  -H "Content-Type: application/json" \
  -H "X-Max-Concurrency: 50" \
  -d '{
    "assistantId": "<?php echo $defaultAgentId; ?>",
    "phoneNumberId": "oktor_sip_500ch",
    "customer": {
      "number": "+5511999999999",
      "name": "João Silva"
    },
    "metadata": {
      "campaignCallId": 456,
      "contactId": 789
    },
    "serverUrl": "https://seu-sistema.com/api/webhook/vapi",
    "assistantOverrides": {
      "firstMessage": "Olá João, tudo bem? Aqui é a Sofia da Dialog DDM.",
      "variableValues": {
        "valor_total": "R$ 1.250,00",
        "instituicao": "Anhanguera"
      }
    }
  }'
                </div>

                <h4 style="font-size: 14px; font-weight: 700; color: var(--ddm-text-primary); margin-bottom: 10px;">Resposta de Sucesso (HTTP 201 Created)</h4>
                <div class="code-block">
{
  "id": "c9284fa0-8172-4cf3-a12b-819a84b019aa",
  "orgId": "org_dialog_ddm",
  "createdAt": "2026-09-11T14:30:00.000Z",
  "type": "outboundPhoneCall",
  "status": "queued",
  "assistantId": "<?php echo $defaultAgentId; ?>",
  "customer": {
    "number": "+5511999999999",
    "name": "João Silva"
  }
}
                </div>
            </div>

            <!-- ENDPOINT 2: GET /v1/call/{id}/transcript -->
            <div class="doc-section">
                <div class="endpoint-badge method-get">
                    <span>GET</span>
                    <span>/v1/call/{id}/transcript</span>
                </div>
                <h2>Obter Transcrição Completa e Áudio Gravado</h2>
                <p>
                    Recupera o histórico estruturado da conversa, mensagens separadas por turnos de fala (<code>assistant</code> e <code>user</code>), duração exata e o link público para download do áudio <code>.wav</code> gravado.
                </p>
                
                <div class="code-block">
                    <button class="code-copy-btn" onclick="copyCode(this)">Copiar</button>
curl -X GET "<?php echo $apiBaseUrl; ?>/call/c9284fa0-8172-4cf3-a12b-819a84b019aa/transcript"
                </div>

                <h4 style="font-size: 14px; font-weight: 700; color: var(--ddm-text-primary); margin-bottom: 10px;">Resposta (JSON)</h4>
                <div class="code-block">
{
  "id": "c9284fa0-8172-4cf3-a12b-819a84b019aa",
  "status": "completed",
  "duration_seconds": 55,
  "transcript": "Sofia: Olá João, tudo bem?\nCliente: Oi, quem fala?\nSofia: Sou a Sofia da Dialog DDM...",
  "messages": [
    { "role": "assistant", "message": "Olá João, tudo bem?" },
    { "role": "user", "message": "Oi, quem fala?" },
    { "role": "assistant", "message": "Sou a Sofia da Dialog DDM..." }
  ],
  "recordingUrl": "https://<?php echo $host; ?>/recordings/ai_call_c9284fa0.wav",
  "tabulation": "Conversa Concluída",
  "tabulation_code": "HUMAN_COMPLETED",
  "endedReason": "customer-ended-call",
  "cost_estimate_brl": 0.1423
}
                </div>
            </div>

            <!-- ENDPOINT 3: GET /v1/concurrency -->
            <div class="doc-section">
                <div class="endpoint-badge method-get">
                    <span>GET</span>
                    <span>/v1/concurrency</span>
                </div>
                <h2>Telemetria de Capacidade e Concorrência</h2>
                <p>
                    Informa a quantidade de canais simultâneos em uso, canais livres da Oktor Telecom (capacidade de 500 canais) e a velocidade instantânea de chamadas por segundo (CPS).
                </p>

                <div class="code-block">
                    <button class="code-copy-btn" onclick="copyCode(this)">Copiar</button>
curl -X GET "<?php echo $apiBaseUrl; ?>/concurrency"
                </div>

                <div class="code-block">
{
  "provider": "Dialog DDM Voice AI",
  "active_channels": 8,
  "max_channels": 500,
  "available_channels": 492,
  "channel_utilization_pct": 1.6,
  "current_cps": 3,
  "max_cps": 100
}
                </div>
            </div>

            <!-- Webhook Payload Format: end-of-call-report -->
            <div class="doc-section">
                <h2><i class="fa fa-send text-orange"></i> Formato do Webhook (end-of-call-report)</h2>
                <p>
                    Quando uma chamada termina, o motor envia automaticamente um payload compatível com o parser <code>ProcessVapiWebhook.ts</code> e ferramentas de automação (n8n, CRM, Zapier):
                </p>
                <div class="code-block">
{
  "event": "call.completed",
  "message": {
    "type": "end-of-call-report",
    "status": "ended",
    "endedReason": "customer-ended-call",
    "call": {
      "id": "c9284fa0-8172-4cf3-a12b-819a84b019aa",
      "status": "ended",
      "metadata": {
        "campaignCallId": 456,
        "contactId": 789
      }
    },
    "customer": {
      "number": "+5511999999999",
      "metadata": {
        "campaignCallId": 456,
        "contactId": 789
      }
    },
    "metadata": {
      "campaignCallId": 456,
      "contactId": 789
    },
    "transcript": "Sofia: Olá João...\nCliente: Oi...",
    "artifact": {
      "transcript": "Sofia: Olá João...\nCliente: Oi...",
      "recordingUrl": "https://<?php echo $host; ?>/recordings/ai_call_c9284fa0.wav",
      "messages": [
        { "role": "assistant", "message": "Olá João..." },
        { "role": "user", "message": "Oi..." }
      ]
    },
    "cost": 0.1423,
    "durationSeconds": 55
  }
}
                </div>
            </div>

        </div>
    </aside>
</div>

<script src="js/jquery-2.1.1.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/app.min.js"></script>
<script>
function copyCode(btn) {
    var block = $(btn).closest('.code-block').clone();
    block.find('.code-copy-btn').remove();
    var text = block.text().trim();
    
    if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(function() {
            var oldText = $(btn).text();
            $(btn).text('Copiado!');
            setTimeout(function() { $(btn).text(oldText); }, 2000);
        });
    }
}
</script>
</body>
</html>
