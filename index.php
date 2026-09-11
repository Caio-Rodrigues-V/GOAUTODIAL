<?php
/**
 * @file        index.php
 * @brief       Dialog DDM - Voice AI Executive Dashboard (Design System 2.0)
 * @copyright   (c) Dialog DDM - Grupo DDM
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
    <title>Dialog DDM - Painel Executivo Voice AI</title>
    <meta content='width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no' name='viewport'>

    <?php 
        print $ui->standardizedThemeCSS(); 
        print $ui->creamyThemeCSS();
    ?>
    <link href="css/style.css" rel="stylesheet" type="text/css" />
    <link href="css/dialog_ddm.css" rel="stylesheet" type="text/css" />
    <style>
        .ddm-stat-card {
            background: var(--ddm-surface);
            border: 1px solid var(--ddm-border);
            border-radius: var(--ddm-radius-lg);
            padding: 22px 24px;
            margin-bottom: 20px;
            box-shadow: var(--ddm-shadow-xs);
            position: relative;
            overflow: hidden;
            transition: all 0.2s ease;
        }
        .ddm-stat-card:hover {
            box-shadow: var(--ddm-shadow-sm);
            border-color: var(--ddm-border-strong);
            transform: translateY(-2px);
        }
        .ddm-stat-card .stat-icon-bg {
            position: absolute;
            right: 18px;
            bottom: 14px;
            font-size: 42px;
            color: var(--ddm-surface-subtle);
            pointer-events: none;
        }
        .ddm-stat-card h3 {
            font-size: 28px;
            font-weight: 800;
            margin: 0 0 4px 0;
            color: var(--ddm-text-primary);
            letter-spacing: -0.5px;
            line-height: 1.1;
        }
        .ddm-stat-card p {
            font-size: 11.5px;
            margin: 0 0 8px 0;
            text-transform: uppercase;
            font-weight: 700;
            color: var(--ddm-text-secondary);
            letter-spacing: 0.5px;
        }
        .ddm-stat-card .stat-subtext {
            font-size: 12px;
            color: var(--ddm-text-muted);
            margin-top: 8px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .stat-progress-bg {
            height: 6px;
            background: var(--ddm-surface-subtle);
            border-radius: 999px;
            margin-top: 10px;
            overflow: hidden;
        }
        .stat-progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--ddm-primary), var(--ddm-purple));
            border-radius: 999px;
            transition: width 0.4s ease;
        }

        .ddm-live-beacon {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--ddm-success-light);
            border: 1px solid var(--ddm-success-border);
            color: #047857;
            padding: 6px 14px;
            border-radius: 9999px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.4px;
            text-transform: uppercase;
        }
        .ddm-live-dot {
            width: 8px;
            height: 8px;
            background: var(--ddm-success);
            border-radius: 50%;
            box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
            animation: pulseBeacon 1.5s infinite;
        }
        @keyframes pulseBeacon {
            0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
            70% { transform: scale(1); box-shadow: 0 0 0 7px rgba(16, 185, 129, 0); }
            100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
        }

        .ddm-content-card {
            background: var(--ddm-surface);
            border: 1px solid var(--ddm-border);
            border-radius: var(--ddm-radius-lg);
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: var(--ddm-shadow-xs);
        }
        .ddm-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 14px;
            border-bottom: 1px solid var(--ddm-border);
        }
        .ddm-card-title {
            font-size: 16px;
            font-weight: 700;
            color: var(--ddm-text-primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .ddm-card-title i {
            color: var(--ddm-primary);
        }

        .ddm-table th {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--ddm-text-secondary);
            border-bottom: 2px solid var(--ddm-border) !important;
            padding: 12px 14px !important;
        }
        .ddm-table td {
            padding: 12px 14px !important;
            vertical-align: middle !important;
            border-top: 1px solid var(--ddm-border) !important;
            color: var(--ddm-text-primary);
            font-size: 13.5px;
        }
        .ddm-table tbody tr:hover {
            background-color: var(--ddm-surface-subtle) !important;
        }

        .infra-badge {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 14px;
            background: var(--ddm-surface-subtle);
            border: 1px solid var(--ddm-border);
            border-radius: var(--ddm-radius);
            margin-bottom: 10px;
            transition: all 0.2s;
        }
        .infra-badge:hover {
            border-color: var(--ddm-border-strong);
            background: #F8FAFC;
        }
        .infra-badge-left {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .infra-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }

        .hero-banner {
            background: linear-gradient(135deg, #1E293B 0%, #0F172A 100%);
            border-radius: var(--ddm-radius-lg);
            padding: 24px 28px;
            color: #FFFFFF;
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
            box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.2);
            position: relative;
            overflow: hidden;
        }
        .hero-banner::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(249, 115, 22, 0.15) 0%, rgba(249, 115, 22, 0) 70%);
            pointer-events: none;
        }
        .hero-stats-group {
            display: flex;
            gap: 28px;
            flex-wrap: wrap;
        }
        .hero-stat-item {
            border-left: 2px solid rgba(255, 255, 255, 0.15);
            padding-left: 16px;
        }
        .hero-stat-item .val {
            font-size: 22px;
            font-weight: 800;
            color: #FFFFFF;
            line-height: 1.2;
        }
        .hero-stat-item .lbl {
            font-size: 11px;
            color: #94A3B8;
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.5px;
            margin-top: 2px;
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
                <span class="current">Painel Executivo Voice AI</span>
            </div>

            <!-- Page Header -->
            <div class="ddm-header">
                <div class="ddm-header-left">
                    <div class="ddm-agent-avatar" style="background: linear-gradient(135deg, #F97316, #EA580C); color: #FFF;">
                        <i class="fa fa-microphone"></i>
                    </div>
                    <div>
                        <div style="font-size: 22px; font-weight: 800; color: var(--ddm-text-primary); letter-spacing: -0.4px;">
                            Plataforma de Voz com Inteligência Artificial
                        </div>
                        <div style="font-size: 13px; color: var(--ddm-text-secondary); margin-top: 2px;">
                            Operação de Alta Concorrência &bull; <strong>500 Canais SIP</strong> &bull; <strong>100 CPS</strong> &bull; Gateway Vapi Ativo
                        </div>
                    </div>
                </div>
                <div class="ddm-header-actions">
                    <a href="live_monitor.php" class="ddm-btn ddm-btn-secondary">
                        <i class="fa fa-line-chart"></i> Monitor ao Vivo
                    </a>
                    <a href="disparador.php" class="ddm-btn ddm-btn-secondary">
                        <i class="fa fa-bolt"></i> Disparo em Lote
                    </a>
                    <a href="ai_agents.php" class="ddm-btn ddm-btn-primary">
                        <i class="fa fa-plus-circle"></i> Agentes de Voz
                    </a>
                </div>
            </div>

            <!-- Hero Telemetry Banner -->
            <div class="hero-banner">
                <div>
                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
                        <span class="ddm-live-beacon" style="background: rgba(16, 185, 129, 0.2); border-color: rgba(16, 185, 129, 0.4); color: #34D399;">
                            <span class="ddm-live-dot" style="background: #34D399;"></span> Motor de Voz Operacional
                        </span>
                        <span style="font-size: 12px; color: #94A3B8;">&bull; Latência Média de Conversação: <strong>~450ms</strong></span>
                    </div>
                    <div style="font-size: 17px; font-weight: 700; color: #F8FAFC;">
                        Gateway de Telefonia & Inteligência Conversacional
                    </div>
                    <div style="font-size: 13px; color: #CBD5E1; margin-top: 4px; max-width: 600px;">
                        Atendimento humanizado com reconhecimento Deepgram Nova-2, raciocínio Groq LLaMA 3.3 70B e síntese Cartesia Sonic em português brasileiro.
                    </div>
                </div>

                <div class="hero-stats-group">
                    <div class="hero-stat-item">
                        <div class="val" id="hero-active-channels">0 / 500</div>
                        <div class="lbl">Canais em Uso</div>
                    </div>
                    <div class="hero-stat-item">
                        <div class="val" id="hero-current-cps">0.0</div>
                        <div class="lbl">CPS Atual</div>
                    </div>
                    <div class="hero-stat-item">
                        <div class="val"><?php echo number_format($activeAgentsCount, 0, ',', '.'); ?></div>
                        <div class="lbl">Agentes Ativos</div>
                    </div>
                </div>
            </div>

            <!-- Primary KPI Grid -->
            <div class="row">
                <!-- Total Calls -->
                <div class="col-md-3 col-sm-6">
                    <div class="ddm-stat-card">
                        <p>Total de Ligações AI</p>
                        <h3><?php echo number_format($totalCalls, 0, ',', '.'); ?></h3>
                        <div class="stat-progress-bg">
                            <div class="stat-progress-fill" style="width: <?php echo min(100, max(5, $answerRate)); ?>%;"></div>
                        </div>
                        <div class="stat-subtext">
                            <i class="fa fa-phone" style="color: var(--ddm-primary);"></i>
                            <span><strong><?php echo $answerRate; ?>%</strong> taxa de atendimento</span>
                        </div>
                        <div class="stat-icon-bg"><i class="fa fa-phone-square"></i></div>
                    </div>
                </div>

                <!-- Total Minutes -->
                <div class="col-md-3 col-sm-6">
                    <div class="ddm-stat-card">
                        <p>Minutos Conversados</p>
                        <h3><?php echo number_format($totalDurationMin, 0, ',', '.'); ?> <span style="font-size: 15px; font-weight: 600; color: var(--ddm-text-secondary);">min</span></h3>
                        <div class="stat-progress-bg">
                            <div class="stat-progress-fill" style="width: 75%; background: linear-gradient(90deg, #10B981, #059669);"></div>
                        </div>
                        <div class="stat-subtext">
                            <i class="fa fa-clock-o" style="color: #10B981;"></i>
                            <span>Média de <strong><?php echo $avgDuration; ?>s</strong> por chamada</span>
                        </div>
                        <div class="stat-icon-bg"><i class="fa fa-clock-o"></i></div>
                    </div>
                </div>

                <!-- Success / Agreements Rate -->
                <div class="col-md-3 col-sm-6">
                    <div class="ddm-stat-card">
                        <p>Acordos & Qualificações</p>
                        <h3><?php echo number_format($leadsCount, 0, ',', '.'); ?> <span style="font-size: 15px; font-weight: 600; color: var(--ddm-text-secondary);">(<?php echo $leadRate; ?>%)</span></h3>
                        <div class="stat-progress-bg">
                            <div class="stat-progress-fill" style="width: <?php echo min(100, max(5, $leadRate)); ?>%; background: linear-gradient(90deg, #8B5CF6, #6D28D9);"></div>
                        </div>
                        <div class="stat-subtext">
                            <i class="fa fa-check-circle" style="color: #8B5CF6;"></i>
                            <span>Leads com intenção positiva</span>
                        </div>
                        <div class="stat-icon-bg"><i class="fa fa-thumbs-up"></i></div>
                    </div>
                </div>

                <!-- Savings / Cost -->
                <div class="col-md-3 col-sm-6">
                    <div class="ddm-stat-card">
                        <p>Economia Estimada</p>
                        <h3 style="color: #047857;">R$ <?php echo number_format($estimatedSavings, 2, ',', '.'); ?></h3>
                        <div class="stat-progress-bg">
                            <div class="stat-progress-fill" style="width: 90%; background: linear-gradient(90deg, #047857, #10B981);"></div>
                        </div>
                        <div class="stat-subtext">
                            <i class="fa fa-money" style="color: #047857;"></i>
                            <span>vs. Operação Humana Tradicional</span>
                        </div>
                        <div class="stat-icon-bg"><i class="fa fa-line-chart"></i></div>
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
                                <i class="fa fa-history"></i> Últimas Chamadas de Voz IA
                            </div>
                            <a href="ai_call_logs.php" class="ddm-btn ddm-btn-secondary" style="padding: 5px 12px; font-size: 12px;">
                                Ver Histórico Completo <i class="fa fa-arrow-right"></i>
                            </a>
                        </div>

                        <?php if (empty($recentCalls)): ?>
                            <div style="text-align: center; padding: 40px 20px; color: var(--ddm-text-secondary);">
                                <i class="fa fa-phone" style="font-size: 38px; color: var(--ddm-border-strong); margin-bottom: 12px;"></i>
                                <div style="font-size: 15px; font-weight: 600; color: var(--ddm-text-primary);">Nenhuma chamada registrada recentemente</div>
                                <div style="font-size: 13px; margin-top: 4px;">Inicie um disparo em lote ou faça uma chamada de teste através dos Agentes de IA.</div>
                                <a href="disparador.php" class="ddm-btn ddm-btn-primary" style="margin-top: 16px;">
                                    <i class="fa fa-bolt"></i> Iniciar Disparo
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table ddm-table">
                                    <thead>
                                        <tr>
                                            <th>Telefone</th>
                                            <th>Agente de Voz</th>
                                            <th>Duração</th>
                                            <th>Qualificação</th>
                                            <th>Data / Hora</th>
                                            <th style="text-align: right;">Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentCalls as $call): ?>
                                            <?php 
                                                $qual = !empty($call['qualification']) ? $call['qualification'] : (!empty($call['tabulation']) ? $call['tabulation'] : 'Não Tabulado');
                                                $qualBadge = 'background: #F1F5F9; color: #475569; border: 1px solid #CBD5E1;';
                                                
                                                if (stripos($qual, 'Interessado') !== false || stripos($qual, 'Acordo') !== false || stripos($qual, 'Positiv') !== false) {
                                                    $qualBadge = 'background: #DCFCE7; color: #166534; border: 1px solid #86EFAC;';
                                                } elseif (stripos($qual, 'Recus') !== false || stripos($qual, 'Negativ') !== false) {
                                                    $qualBadge = 'background: #FEE2E2; color: #991B1B; border: 1px solid #FCA5A5;';
                                                } elseif (stripos($qual, 'Caixa') !== false || stripos($qual, 'Voicemail') !== false) {
                                                    $qualBadge = 'background: #FEF3C7; color: #92400E; border: 1px solid #FCD34D;';
                                                }

                                                $dur = isset($call['duration_seconds']) ? (int)$call['duration_seconds'] : 0;
                                                $durFmt = sprintf('%02d:%02d', floor($dur / 60), $dur % 60);
                                                $callDate = isset($call['created_at']) ? date('d/m/Y H:i', strtotime($call['created_at'])) : '-';
                                                $agentName = !empty($call['agent_name']) ? htmlspecialchars($call['agent_name']) : 'Sofia - Qualificadora';
                                                $phone = !empty($call['phone_number']) ? htmlspecialchars($call['phone_number']) : '-';
                                            ?>
                                            <tr>
                                                <td style="font-weight: 700; font-family: monospace; font-size: 13.5px;">
                                                    <i class="fa fa-phone" style="color: var(--ddm-text-secondary); margin-right: 4px;"></i>
                                                    <?php echo $phone; ?>
                                                </td>
                                                <td>
                                                    <span style="font-weight: 600; color: var(--ddm-text-primary);">
                                                        <?php echo $agentName; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span style="font-family: monospace; font-weight: 600; color: var(--ddm-text-secondary);">
                                                        <?php echo $durFmt; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span style="display: inline-block; padding: 3px 10px; border-radius: 9999px; font-size: 11.5px; font-weight: 700; <?php echo $qualBadge; ?>">
                                                        <?php echo htmlspecialchars($qual); ?>
                                                    </span>
                                                </td>
                                                <td style="font-size: 12.5px; color: var(--ddm-text-secondary);">
                                                    <?php echo $callDate; ?>
                                                </td>
                                                <td style="text-align: right;">
                                                    <a href="ai_call_logs.php?call_id=<?php echo isset($call['id']) ? $call['id'] : ''; ?>" class="ddm-btn ddm-btn-secondary" style="padding: 4px 10px; font-size: 12px;" title="Ver detalhes e transcrição">
                                                        <i class="fa fa-file-text-o"></i> Detalhes
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
                                <i class="fa fa-users"></i> Agentes de Voz em Produção
                            </div>
                            <a href="ai_agent_edit.php" class="ddm-btn ddm-btn-primary" style="padding: 5px 12px; font-size: 12px;">
                                <i class="fa fa-plus"></i> Novo Agente
                            </a>
                        </div>

                        <?php if (empty($agents)): ?>
                            <div style="text-align: center; padding: 30px 20px; color: var(--ddm-text-secondary);">
                                <p>Nenhum agente configurado ainda.</p>
                                <a href="ai_agent_edit.php" class="ddm-btn ddm-btn-primary">Criar Primeiro Agente</a>
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach (array_slice($agents, 0, 4) as $ag): ?>
                                    <div class="col-md-6" style="margin-bottom: 16px;">
                                        <div style="background: var(--ddm-surface-subtle); border: 1px solid var(--ddm-border); border-radius: var(--ddm-radius); padding: 16px; height: 100%; display: flex; flex-direction: column; justify-content: space-between;">
                                            <div>
                                                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                                                    <div style="font-weight: 800; font-size: 15px; color: var(--ddm-text-primary);">
                                                        <?php echo htmlspecialchars($ag['agent_name']); ?>
                                                    </div>
                                                    <span style="font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 999px; <?php echo ($ag['status'] === 'Y') ? 'background: #DCFCE7; color: #166534;' : 'background: #F1F5F9; color: #64748B;'; ?>">
                                                        <?php echo ($ag['status'] === 'Y') ? 'ATIVO' : 'INATIVO'; ?>
                                                    </span>
                                                </div>
                                                <div style="font-size: 12px; color: var(--ddm-text-secondary); margin-bottom: 12px; line-height: 1.4;">
                                                    <?php echo htmlspecialchars(mb_strimwidth($ag['description'] ?? 'Agente de voz inteligente', 0, 80, '...')); ?>
                                                </div>
                                            </div>

                                            <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--ddm-border); padding-top: 10px; font-size: 11.5px; color: var(--ddm-text-muted);">
                                                <div>
                                                    <i class="fa fa-microchip"></i> <?php echo strtoupper($ag['llm_provider'] ?? 'Groq'); ?> &bull; 
                                                    <i class="fa fa-volume-up"></i> <?php echo ucfirst($ag['voice_provider'] ?? 'Cartesia'); ?>
                                                </div>
                                                <a href="ai_agent_edit.php?id=<?php echo $ag['agent_id']; ?>" style="font-weight: 700; color: var(--ddm-primary);">
                                                    Editar <i class="fa fa-chevron-right"></i>
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
                                <i class="fa fa-server"></i> Infraestrutura & Gateways
                            </div>
                        </div>

                        <!-- Vapi API Gateway -->
                        <div class="infra-badge">
                            <div class="infra-badge-left">
                                <div class="infra-icon" style="background: #EFF6FF; color: #2563EB;">
                                    <i class="fa fa-plug"></i>
                                </div>
                                <div>
                                    <div style="font-size: 13px; font-weight: 700; color: var(--ddm-text-primary);">Vapi API Gateway</div>
                                    <div style="font-size: 11px; color: var(--ddm-text-secondary);">/v1/call/phone &bull; 100% Compatível</div>
                                </div>
                            </div>
                            <span style="font-size: 11px; font-weight: 700; color: #166534; background: #DCFCE7; padding: 2px 8px; border-radius: 999px;">
                                ONLINE
                            </span>
                        </div>

                        <!-- SIP Trunk Oktor -->
                        <div class="infra-badge">
                            <div class="infra-badge-left">
                                <div class="infra-icon" style="background: #FFF7ED; color: #EA580C;">
                                    <i class="fa fa-phone"></i>
                                </div>
                                <div>
                                    <div style="font-size: 13px; font-weight: 700; color: var(--ddm-text-primary);">Tronco SIP Oktor</div>
                                    <div style="font-size: 11px; color: var(--ddm-text-secondary);">500 Canais &bull; 100 CPS</div>
                                </div>
                            </div>
                            <span style="font-size: 11px; font-weight: 700; color: #166534; background: #DCFCE7; padding: 2px 8px; border-radius: 999px;">
                                CONECTADO
                            </span>
                        </div>

                        <!-- Deepgram Nova-2 -->
                        <div class="infra-badge">
                            <div class="infra-badge-left">
                                <div class="infra-icon" style="background: #F0FDF4; color: #16A34A;">
                                    <i class="fa fa-microphone"></i>
                                </div>
                                <div>
                                    <div style="font-size: 13px; font-weight: 700; color: var(--ddm-text-primary);">Deepgram Nova-2 (STT)</div>
                                    <div style="font-size: 11px; color: var(--ddm-text-secondary);">Transcrição Real-Time pt-BR</div>
                                </div>
                            </div>
                            <span style="font-size: 11px; font-weight: 700; color: #166534; background: #DCFCE7; padding: 2px 8px; border-radius: 999px;">
                                ~120ms
                            </span>
                        </div>

                        <!-- Groq LLM -->
                        <div class="infra-badge">
                            <div class="infra-badge-left">
                                <div class="infra-icon" style="background: #FAF5FF; color: #9333EA;">
                                    <i class="fa fa-bolt"></i>
                                </div>
                                <div>
                                    <div style="font-size: 13px; font-weight: 700; color: var(--ddm-text-primary);">Groq LLaMA 3.3 70B</div>
                                    <div style="font-size: 11px; color: var(--ddm-text-secondary);">Raciocínio & Ferramentas de CRM</div>
                                </div>
                            </div>
                            <span style="font-size: 11px; font-weight: 700; color: #166534; background: #DCFCE7; padding: 2px 8px; border-radius: 999px;">
                                ~150ms
                            </span>
                        </div>

                        <!-- Cartesia Sonic -->
                        <div class="infra-badge">
                            <div class="infra-badge-left">
                                <div class="infra-icon" style="background: #ECFEFF; color: #0891B2;">
                                    <i class="fa fa-volume-up"></i>
                                </div>
                                <div>
                                    <div style="font-size: 13px; font-weight: 700; color: var(--ddm-text-primary);">Cartesia Sonic (TTS)</div>
                                    <div style="font-size: 11px; color: var(--ddm-text-secondary);">Síntese de Voz Ultrarrealista</div>
                                </div>
                            </div>
                            <span style="font-size: 11px; font-weight: 700; color: #166534; background: #DCFCE7; padding: 2px 8px; border-radius: 999px;">
                                ~90ms
                            </span>
                        </div>

                        <!-- DDM Acordos -->
                        <div class="infra-badge">
                            <div class="infra-badge-left">
                                <div class="infra-icon" style="background: #FEF2F2; color: #DC2626;">
                                    <i class="fa fa-shield"></i>
                                </div>
                                <div>
                                    <div style="font-size: 13px; font-weight: 700; color: var(--ddm-text-primary);">DDM Acordos API</div>
                                    <div style="font-size: 11px; color: var(--ddm-text-secondary);">Busca de CPF & Negociação</div>
                                </div>
                            </div>
                            <span style="font-size: 11px; font-weight: 700; color: #166534; background: #DCFCE7; padding: 2px 8px; border-radius: 999px;">
                                ATIVO
                            </span>
                        </div>
                    </div>

                    <!-- Developer Quick Links Card -->
                    <div class="ddm-content-card">
                        <div class="ddm-card-header">
                            <div class="ddm-card-title">
                                <i class="fa fa-code"></i> Integração & Desenvolvedor
                            </div>
                        </div>

                        <p style="font-size: 12.5px; color: var(--ddm-text-secondary); line-height: 1.5; margin-bottom: 14px;">
                            Integre seu disparador externo ou CRM diretamente com o motor Dialog DDM utilizando a especificação padrão Vapi.
                        </p>

                        <div style="background: #0F172A; color: #E2E8F0; padding: 12px 14px; border-radius: var(--ddm-radius); font-family: monospace; font-size: 12px; margin-bottom: 14px; word-break: break-all;">
                            <span style="color: #38BDF8;">POST</span> https://dialddm.grupoddm.ia.br/v1/call/phone
                        </div>

                        <div style="display: flex; flex-direction: column; gap: 8px;">
                            <a href="api_docs.php" class="ddm-btn ddm-btn-secondary" style="width: 100%; justify-content: center;">
                                <i class="fa fa-book"></i> Ver Documentação Completa da API
                            </a>
                            <a href="api_settings.php" class="ddm-btn ddm-btn-secondary" style="width: 100%; justify-content: center;">
                                <i class="fa fa-key"></i> Chaves de API & Webhooks
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
