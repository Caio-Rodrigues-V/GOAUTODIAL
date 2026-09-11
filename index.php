<?php
/**
 * @file        index.php
 * @brief       DIALog DDM - Voice AI Executive Dashboard (Design System 2.0)
 * @copyright   (c) DIALog DDM - Grupo DDM
 */

error_reporting(E_ERROR | E_PARSE);

require_once('./php/CRMDefaults.php');
require_once('./php/APIHandler.php');
require_once('./php/UIHandler.php');
require_once('./php/LanguageHandler.php');
require_once('./php/DbHandler.php');
require_once('./php/Session.php');
require_once('./php/AIAgentHandler.php');

$ui = \creamy\UIHandler::getInstance();
$lh = \creamy\LanguageHandler::getInstance();
$api = \creamy\APIHandler::getInstance();
$aiHandler = \creamy\AIAgentHandler::getInstance();

// Authentication
try {
    $user = \creamy\CreamyUser::currentUser();
} catch (\Exception $e) {
    header("location: ./logout.php");
    exit();
}

// Redirect agents to agent interface
if ($user->getUserRole() == CRM_DEFAULTS_USER_ROLE_AGENT) {
    header("location: agent.php");
    exit();
}

// Load AI Stats & Data
$stats = $aiHandler->getCallStats();
$recentCalls = $aiHandler->getCallLogs(8);
$agents = $aiHandler->getAllAgents();

$activeAgentsCount = 0;
foreach ($agents as $ag) {
    if (isset($ag['status']) && $ag['status'] === 'Y') {
        $activeAgentsCount++;
    }
}

// Calculate percentages & metrics
$totalCalls = max(0, (int)$stats['total']);
$answeredCalls = max(0, (int)$stats['answered']);
$totalDurationSec = max(0, (int)$stats['duration']);
$totalDurationMin = round($totalDurationSec / 60, 1);
$totalDurationHours = round($totalDurationSec / 3600, 1);
$leadsCount = max(0, (int)$stats['leads']);
$totalCost = max(0, (float)$stats['cost']);

$answerRate = $totalCalls > 0 ? round(($answeredCalls / $totalCalls) * 100, 1) : 0;
$leadRate = $answeredCalls > 0 ? round(($leadsCount / $answeredCalls) * 100, 1) : 0;
$avgDuration = $answeredCalls > 0 ? round($totalDurationSec / $answeredCalls) : 0;

// Estimated operational savings (avg human agent cost R$ 0.85/min vs AI R$ 0.18/min)
$estimatedHumanCost = $totalDurationMin * 0.85;
$estimatedAiCost = $totalCost > 0 ? $totalCost : ($totalDurationMin * 0.18);
$estimatedSavings = max(0, $estimatedHumanCost - $estimatedAiCost);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>DIALog DDM - Visão Geral Voice AI</title>
    <meta content='width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no' name='viewport'>

    <?php 
        print $ui->standardizedThemeCSS(); 
        print $ui->creamyThemeCSS();
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
                <a href="index.php">DIALog DDM</a>
                <span class="separator">/</span>
                <span class="current">Visão Geral & Dashboard</span>
            </div>

            <!-- Page Header -->
            <div class="ddm-page-header">
                <div>
                    <h1 class="ddm-page-title">Plataforma de Voz com Inteligência Artificial</h1>
                    <p class="ddm-page-subtitle">Operação de Alta Concorrência &bull; <strong>500 Canais SIP</strong> &bull; <strong>100 CPS</strong> &bull; Gateway Vapi Ativo</p>
                </div>
                <div class="ddm-page-actions">
                    <a href="live_monitor.php" class="ddm-btn ddm-btn-secondary">
                        <i data-lucide="activity"></i> Monitor ao Vivo
                    </a>
                    <a href="disparador.php" class="ddm-btn ddm-btn-secondary">
                        <i data-lucide="zap"></i> Disparo em Lote
                    </a>
                    <a href="ai_agents.php" class="ddm-btn ddm-btn-primary">
                        <i data-lucide="bot"></i> Agentes de Voz
                    </a>
                </div>
            </div>

            <!-- Hero Telemetry Banner (SaaS B2B Clean Style) -->
            <div class="ddm-content-card" style="padding: 24px; margin-bottom: 24px; background: linear-gradient(135deg, #101828 0%, #1D2939 100%); color: #FFFFFF; border: none; box-shadow: 0 10px 20px -5px rgba(16, 24, 40, 0.1);">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px;">
                    <div>
                        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
                            <span class="ddm-badge" style="background: rgba(18, 183, 106, 0.2); border: 1px solid rgba(18, 183, 106, 0.4); color: #6CE9A6; font-size: 11px;">
                                <span class="ddm-dot-indicator ddm-dot-success" style="background: #12B76A; margin-right: 4px;"></span> Motor de Voz Operacional
                            </span>
                            <span style="font-size: 12px; color: #98A2B3;">Latência Média: <strong style="color:#FFF;">~450ms</strong></span>
                        </div>
                        <div style="font-size: 18px; font-weight: 700; color: #FFFFFF; margin-bottom: 4px;">
                            Gateway de Telefonia & Inteligência Conversacional
                        </div>
                        <div style="font-size: 13px; color: #D0D5DD; max-width: 620px; line-height: 1.5;">
                            Reconhecimento de fala Deepgram Nova-2, raciocínio Groq LLaMA 3.3 70B e síntese ultrarrealista Cartesia Sonic em português brasileiro.
                        </div>
                    </div>

                    <div style="display: flex; gap: 24px; flex-wrap: wrap;">
                        <div style="border-left: 2px solid rgba(255, 255, 255, 0.15); padding-left: 16px;">
                            <div style="font-size: 22px; font-weight: 700; color: #FFFFFF;" id="hero-active-channels">0 / 500</div>
                            <div style="font-size: 11px; color: #98A2B3; text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px;">Canais em Uso</div>
                        </div>
                        <div style="border-left: 2px solid rgba(255, 255, 255, 0.15); padding-left: 16px;">
                            <div style="font-size: 22px; font-weight: 700; color: #FFFFFF;" id="hero-current-cps">0.0</div>
                            <div style="font-size: 11px; color: #98A2B3; text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px;">CPS Atual</div>
                        </div>
                        <div style="border-left: 2px solid rgba(255, 255, 255, 0.15); padding-left: 16px;">
                            <div style="font-size: 22px; font-weight: 700; color: #FFFFFF;"><?php echo number_format($activeAgentsCount, 0, ',', '.'); ?></div>
                            <div style="font-size: 11px; color: #98A2B3; text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px;">Agentes Ativos</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Primary KPI Grid -->
            <div class="row">
                <!-- Total Calls -->
                <div class="col-md-3 col-sm-6">
                    <div class="ddm-stat-card">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                            <span class="ddm-stat-label">Total de Ligações AI</span>
                            <div style="width: 32px; height: 32px; border-radius: 6px; background: #FFF4EB; color: #FF6B00; display: flex; align-items: center; justify-content: center;">
                                <i data-lucide="phone-call" style="width: 16px; height: 16px;"></i>
                            </div>
                        </div>
                        <div class="ddm-stat-value"><?php echo number_format($totalCalls, 0, ',', '.'); ?></div>
                        <div class="ddm-stat-trend">
                            <span class="ddm-badge ddm-badge-brand" style="font-size: 11px; padding: 1px 6px;">
                                <?php echo $answerRate; ?>% atendidas
                            </span>
                            <span style="color: #667085; font-size: 12px;"><?php echo number_format($answeredCalls, 0, ',', '.'); ?> completadas</span>
                        </div>
                    </div>
                </div>

                <!-- Total Minutes -->
                <div class="col-md-3 col-sm-6">
                    <div class="ddm-stat-card">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                            <span class="ddm-stat-label">Minutos Conversados</span>
                            <div style="width: 32px; height: 32px; border-radius: 6px; background: #ECFDF3; color: #027A48; display: flex; align-items: center; justify-content: center;">
                                <i data-lucide="clock" style="width: 16px; height: 16px;"></i>
                            </div>
                        </div>
                        <div class="ddm-stat-value"><?php echo number_format($totalDurationMin, 0, ',', '.'); ?> <span style="font-size: 14px; font-weight: 500; color: #667085;">min</span></div>
                        <div class="ddm-stat-trend">
                            <span class="ddm-badge ddm-badge-success" style="font-size: 11px; padding: 1px 6px;">
                                ~<?php echo $avgDuration; ?>s / chamada
                            </span>
                            <span style="color: #667085; font-size: 12px;"><?php echo number_format($totalDurationHours, 1, ',', '.'); ?> horas totais</span>
                        </div>
                    </div>
                </div>

                <!-- Success / Agreements Rate -->
                <div class="col-md-3 col-sm-6">
                    <div class="ddm-stat-card">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                            <span class="ddm-stat-label">Acordos & Qualificados</span>
                            <div style="width: 32px; height: 32px; border-radius: 6px; background: #F4EBFF; color: #6941C6; display: flex; align-items: center; justify-content: center;">
                                <i data-lucide="check-circle-2" style="width: 16px; height: 16px;"></i>
                            </div>
                        </div>
                        <div class="ddm-stat-value"><?php echo number_format($leadsCount, 0, ',', '.'); ?> <span style="font-size: 14px; font-weight: 500; color: #667085;">(<?php echo $leadRate; ?>%)</span></div>
                        <div class="ddm-stat-trend">
                            <span class="ddm-badge ddm-badge-neutral" style="font-size: 11px; padding: 1px 6px;">
                                Intenção Positiva
                            </span>
                            <span style="color: #667085; font-size: 12px;">leads convertidos</span>
                        </div>
                    </div>
                </div>

                <!-- Savings / Cost -->
                <div class="col-md-3 col-sm-6">
                    <div class="ddm-stat-card">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                            <span class="ddm-stat-label">Economia Estimada</span>
                            <div style="width: 32px; height: 32px; border-radius: 6px; background: #ECFDF3; color: #027A48; display: flex; align-items: center; justify-content: center;">
                                <i data-lucide="trending-up" style="width: 16px; height: 16px;"></i>
                            </div>
                        </div>
                        <div class="ddm-stat-value" style="color: #027A48;">R$ <?php echo number_format($estimatedSavings, 2, ',', '.'); ?></div>
                        <div class="ddm-stat-trend">
                            <span class="ddm-badge ddm-badge-success" style="font-size: 11px; padding: 1px 6px;">
                                -78% vs Humano
                            </span>
                            <span style="color: #667085; font-size: 12px;">eficiência de custo</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content Area -->
            <div class="row">
                <!-- Left Column: Recent Calls & Active Agents -->
                <div class="col-md-8">
                    
                    <!-- Recent AI Calls Card -->
                    <div class="ddm-content-card">
                        <div class="ddm-card-header">
                            <div class="ddm-card-title">
                                <i data-lucide="history" style="width: 18px; height: 18px; color: #FF6B00;"></i>
                                <span>Últimas Chamadas de Voz IA</span>
                            </div>
                            <a href="ai_call_logs.php" class="ddm-btn ddm-btn-secondary" style="padding: 6px 12px; font-size: 12.5px;">
                                <span>Ver Histórico Completo</span>
                                <i data-lucide="arrow-right" style="width: 14px; height: 14px;"></i>
                            </a>
                        </div>

                        <?php if (empty($recentCalls)): ?>
                            <div style="text-align: center; padding: 48px 20px; color: #667085;">
                                <div style="width: 48px; height: 48px; border-radius: 50%; background: #F2F4F7; display: flex; align-items: center; justify-content: center; margin: 0 auto 12px auto; color: #98A2B3;">
                                    <i data-lucide="phone-missed" style="width: 24px; height: 24px;"></i>
                                </div>
                                <div style="font-size: 15px; font-weight: 600; color: #101828;">Nenhuma chamada registrada recentemente</div>
                                <div style="font-size: 13px; margin-top: 4px;">Inicie um disparo em lote ou faça uma chamada de teste através dos Agentes de IA.</div>
                                <a href="disparador.php" class="ddm-btn ddm-btn-primary" style="margin-top: 16px; display: inline-flex;">
                                    <i data-lucide="zap"></i> Iniciar Disparo
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive" style="margin: 0; overflow-x: auto;">
                                <table class="ddm-table" style="width: 100%;">
                                    <thead>
                                        <tr>
                                            <th style="min-width: 130px;">Telefone</th>
                                            <th style="min-width: 140px;">Agente de Voz</th>
                                            <th style="min-width: 80px;">Duração</th>
                                            <th style="min-width: 150px;">Qualificação</th>
                                            <th style="min-width: 130px;">Data / Hora</th>
                                            <th style="text-align: right; min-width: 80px; white-space: nowrap;">Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentCalls as $call): ?>
                                            <?php 
                                                $qual = !empty($call['qualification']) ? $call['qualification'] : (!empty($call['tabulation']) ? $call['tabulation'] : 'Não Tabulado');
                                                $badgeClass = 'ddm-badge-neutral';
                                                
                                                if (stripos($qual, 'Interessado') !== false || stripos($qual, 'Acordo') !== false || stripos($qual, 'Positiv') !== false) {
                                                    $badgeClass = 'ddm-badge-success';
                                                } elseif (stripos($qual, 'Recus') !== false || stripos($qual, 'Negativ') !== false) {
                                                    $badgeClass = 'ddm-badge-error';
                                                } elseif (stripos($qual, 'Caixa') !== false || stripos($qual, 'Voicemail') !== false) {
                                                    $badgeClass = 'ddm-badge-warning';
                                                }

                                                $dur = isset($call['duration_seconds']) ? (int)$call['duration_seconds'] : 0;
                                                $durFmt = sprintf('%02d:%02d', floor($dur / 60), $dur % 60);
                                                $callDate = isset($call['created_at']) ? date('d/m/Y H:i', strtotime($call['created_at'])) : '-';
                                                $agentName = !empty($call['agent_name']) ? htmlspecialchars($call['agent_name']) : 'Sofia - Qualificadora';
                                                $phone = !empty($call['phone_number']) ? htmlspecialchars($call['phone_number']) : '-';
                                            ?>
                                            <tr>
                                                <td style="font-weight: 600; font-family: 'JetBrains Mono', monospace; font-size: 13px;">
                                                    <?php echo $phone; ?>
                                                </td>
                                                <td>
                                                    <span style="font-weight: 500; color: #101828;">
                                                        <?php echo $agentName; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span style="font-family: 'JetBrains Mono', monospace; font-size: 12.5px; color: #475467;">
                                                        <?php echo $durFmt; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="ddm-badge <?php echo $badgeClass; ?>">
                                                        <?php echo htmlspecialchars($qual); ?>
                                                    </span>
                                                </td>
                                                <td style="font-size: 12.5px; color: #667085;">
                                                    <?php echo $callDate; ?>
                                                </td>
                                                <td style="text-align: right; white-space: nowrap;">
                                                    <a href="ai_call_logs.php?call_id=<?php echo isset($call['id']) ? $call['id'] : ''; ?>" class="ddm-btn ddm-btn-secondary" style="padding: 4px 8px; font-size: 12px;" title="Ver detalhes e transcrição">
                                                        <i data-lucide="file-text" style="width: 14px; height: 14px;"></i>
                                                        <span>Ver</span>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Voice AI Assistants Overview -->
                    <div class="ddm-content-card">
                        <div class="ddm-card-header">
                            <div class="ddm-card-title">
                                <i data-lucide="bot" style="width: 18px; height: 18px; color: #FF6B00;"></i>
                                <span>Agentes de Voz em Produção</span>
                            </div>
                            <a href="add_ai_agent.php" class="ddm-btn ddm-btn-primary" style="padding: 6px 12px; font-size: 12.5px;">
                                <i data-lucide="plus"></i> Novo Agente
                            </a>
                        </div>

                        <?php if (empty($agents)): ?>
                            <div style="text-align: center; padding: 30px 20px; color: #667085;">
                                <p>Nenhum agente configurado ainda.</p>
                                <a href="add_ai_agent.php" class="ddm-btn ddm-btn-primary">Criar Primeiro Agente</a>
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach (array_slice($agents, 0, 4) as $ag): ?>
                                    <div class="col-md-6" style="margin-bottom: 16px;">
                                        <div style="background: #F9FAFB; border: 1px solid #EAECF0; border-radius: 8px; padding: 16px; height: 100%; display: flex; flex-direction: column; justify-content: space-between;">
                                            <div>
                                                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                                                    <div style="font-weight: 600; font-size: 14.5px; color: #101828;">
                                                        <?php echo htmlspecialchars($ag['agent_name']); ?>
                                                    </div>
                                                    <span class="ddm-badge <?php echo ($ag['status'] === 'Y') ? 'ddm-badge-success' : 'ddm-badge-neutral'; ?>">
                                                        <?php echo ($ag['status'] === 'Y') ? 'ATIVO' : 'INATIVO'; ?>
                                                    </span>
                                                </div>
                                                <div style="font-size: 12.5px; color: #667085; margin-bottom: 12px; line-height: 1.4;">
                                                    <?php echo htmlspecialchars(mb_strimwidth($ag['description'] ?? 'Agente de voz inteligente', 0, 80, '...')); ?>
                                                </div>
                                            </div>

                                            <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #EAECF0; padding-top: 10px; font-size: 11.5px; color: #98A2B3;">
                                                <div>
                                                    <span style="color: #475467; font-weight: 500;"><?php echo strtoupper($ag['llm_provider'] ?? 'Groq'); ?></span> &bull; 
                                                    <span><?php echo ucfirst($ag['voice_provider'] ?? 'Cartesia'); ?></span>
                                                </div>
                                                <a href="edit_ai_agent.php?id=<?php echo $ag['agent_id']; ?>" style="font-weight: 600; color: #FF6B00; text-decoration: none; display: flex; align-items: center; gap: 4px;">
                                                    <span>Configurar</span>
                                                    <i data-lucide="chevron-right" style="width: 14px; height: 14px;"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                </div>

                <!-- Right Column: System Infrastructure & Quick Access -->
                <div class="col-md-4">
                    
                    <!-- Infrastructure Status Card -->
                    <div class="ddm-content-card">
                        <div class="ddm-card-header">
                            <div class="ddm-card-title">
                                <i data-lucide="server" style="width: 18px; height: 18px; color: #FF6B00;"></i>
                                <span>Infraestrutura & Gateways</span>
                            </div>
                        </div>

                        <!-- Vapi API Gateway -->
                        <div class="infra-badge">
                            <div class="infra-badge-left">
                                <div class="infra-icon" style="background: #EFF8FF; color: #175CD3;">
                                    <i data-lucide="plug" style="width: 16px; height: 16px;"></i>
                                </div>
                                <div>
                                    <div style="font-size: 13px; font-weight: 600; color: #101828;">Vapi API Gateway</div>
                                    <div style="font-size: 11px; color: #667085;">/v1/call/phone &bull; 100% Compatível</div>
                                </div>
                            </div>
                            <span class="ddm-badge ddm-badge-success">ONLINE</span>
                        </div>

                        <!-- SIP Trunk Oktor -->
                        <div class="infra-badge">
                            <div class="infra-badge-left">
                                <div class="infra-icon" style="background: #FFF4EB; color: #B93815;">
                                    <i data-lucide="phone-forwarded" style="width: 16px; height: 16px;"></i>
                                </div>
                                <div>
                                    <div style="font-size: 13px; font-weight: 600; color: #101828;">Tronco SIP Oktor</div>
                                    <div style="font-size: 11px; color: #667085;">500 Canais &bull; 100 CPS</div>
                                </div>
                            </div>
                            <span class="ddm-badge ddm-badge-success">CONECTADO</span>
                        </div>

                        <!-- Deepgram Nova-2 -->
                        <div class="infra-badge">
                            <div class="infra-badge-left">
                                <div class="infra-icon" style="background: #ECFDF3; color: #027A48;">
                                    <i data-lucide="mic" style="width: 16px; height: 16px;"></i>
                                </div>
                                <div>
                                    <div style="font-size: 13px; font-weight: 600; color: #101828;">Deepgram Nova-2 (STT)</div>
                                    <div style="font-size: 11px; color: #667085;">Transcrição Real-Time pt-BR</div>
                                </div>
                            </div>
                            <span class="ddm-badge ddm-badge-success">~120ms</span>
                        </div>

                        <!-- Groq LLM -->
                        <div class="infra-badge">
                            <div class="infra-badge-left">
                                <div class="infra-icon" style="background: #F4EBFF; color: #6941C6;">
                                    <i data-lucide="zap" style="width: 16px; height: 16px;"></i>
                                </div>
                                <div>
                                    <div style="font-size: 13px; font-weight: 600; color: #101828;">Groq LLaMA 3.3 70B</div>
                                    <div style="font-size: 11px; color: #667085;">Raciocínio & Tools de Cobrança</div>
                                </div>
                            </div>
                            <span class="ddm-badge ddm-badge-success">~150ms</span>
                        </div>

                        <!-- Cartesia Sonic -->
                        <div class="infra-badge">
                            <div class="infra-badge-left">
                                <div class="infra-icon" style="background: #F0F9FF; color: #026AA2;">
                                    <i data-lucide="volume-2" style="width: 16px; height: 16px;"></i>
                                </div>
                                <div>
                                    <div style="font-size: 13px; font-weight: 600; color: #101828;">Cartesia Sonic (TTS)</div>
                                    <div style="font-size: 11px; color: #667085;">Síntese de Voz Ultrarrealista</div>
                                </div>
                            </div>
                            <span class="ddm-badge ddm-badge-success">~90ms</span>
                        </div>

                        <!-- DDM Acordos -->
                        <div class="infra-badge">
                            <div class="infra-badge-left">
                                <div class="infra-icon" style="background: #FEF3F2; color: #B42318;">
                                    <i data-lucide="shield-check" style="width: 16px; height: 16px;"></i>
                                </div>
                                <div>
                                    <div style="font-size: 13px; font-weight: 600; color: #101828;">DDM Acordos API</div>
                                    <div style="font-size: 11px; color: #667085;">Busca de CPF & Negociação</div>
                                </div>
                            </div>
                            <span class="ddm-badge ddm-badge-success">ATIVO</span>
                        </div>
                    </div>

                    <!-- Developer Quick Links Card -->
                    <div class="ddm-content-card">
                        <div class="ddm-card-header">
                            <div class="ddm-card-title">
                                <i data-lucide="code-2" style="width: 18px; height: 18px; color: #FF6B00;"></i>
                                <span>Integração & Desenvolvedor</span>
                            </div>
                        </div>

                        <p style="font-size: 12.5px; color: #667085; line-height: 1.5; margin-bottom: 14px;">
                            Integre seu disparador externo ou CRM diretamente com o motor DIALog DDM utilizando a especificação padrão Vapi.
                        </p>

                        <div style="background: #101828; color: #E2E8F0; padding: 12px 14px; border-radius: 8px; font-family: 'JetBrains Mono', monospace; font-size: 11.5px; margin-bottom: 14px; word-break: break-all;">
                            <span style="color: #7CD4FD; font-weight: 600;">POST</span> https://dialddm.grupoddm.ia.br/v1/call/phone
                        </div>

                        <div style="display: flex; flex-direction: column; gap: 8px;">
                            <a href="api_docs.php" class="ddm-btn ddm-btn-secondary" style="width: 100%; justify-content: center;">
                                <i data-lucide="book-open"></i> Ver Documentação Completa
                            </a>
                            <a href="ai_settings.php" class="ddm-btn ddm-btn-secondary" style="width: 100%; justify-content: center;">
                                <i data-lucide="key"></i> Chaves de API & Webhooks
                            </a>
                        </div>
                    </div>

                </div>
            </div>

        </div>
    </aside>
</div>

<?php print $ui->standardizedThemeJS(); ?>
<script>
$(document).ready(function() {
    // Real-time live stats poller from Voice Engine
    function pollLiveDashboardStats() {
        $.ajax({
            url: 'php/LiveMonitorProxy.php?action=status',
            type: 'GET',
            dataType: 'json',
            timeout: 3000,
            success: function(data) {
                if (data) {
                    var activeChannels = data.active_channels !== undefined ? data.active_channels : 0;
                    var maxChannels = data.max_channels !== undefined ? data.max_channels : 500;
                    var currentCps = data.current_cps !== undefined ? parseFloat(data.current_cps).toFixed(1) : '0.0';

                    $('#hero-active-channels').text(activeChannels + ' / ' + maxChannels);
                    $('#hero-current-cps').text(currentCps);
                }
            },
            error: function() {
                // Keep default display on temporary network glitch
            }
        });
    }

    // Initial poll & periodic update every 4 seconds
    pollLiveDashboardStats();
    setInterval(pollLiveDashboardStats, 4000);
});
</script>
</body>
</html>
