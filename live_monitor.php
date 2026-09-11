<?php
/**
 * @file        live_monitor.php
 * @brief       DIAL GO Voice AI - Live Real-Time Channels Monitor (500 Channels / 100 CPS)
 * @copyright   (c) DIAL GO Voice AI Engine
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
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>DIAL GO - Monitor em Tempo Real (500 Canais)</title>
    <meta content='width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no' name='viewport'>

    <?php 
        print $ui->standardizedThemeCSS(); 
        print $ui->creamyThemeCSS();
    ?>
    <link href="css/style.css" rel="stylesheet" type="text/css" />
    <link href="css/dialog_ddm.css" rel="stylesheet" type="text/css" />
    <style>
        .live-badge-beacon {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(16, 185, 129, 0.15);
            border: 1px solid rgba(16, 185, 129, 0.35);
            color: #34d399;
            padding: 6px 14px;
            border-radius: 9999px;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        .live-pulse-dot {
            width: 9px;
            height: 9px;
            background: #10b981;
            border-radius: 50%;
            box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
            animation: pulseDot 1.5s infinite;
        }
        @keyframes pulseDot {
            0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
            70% { transform: scale(1); box-shadow: 0 0 0 8px rgba(16, 185, 129, 0); }
            100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
        }

        .monitor-metrics-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 24px;
        }
        @media (max-width: 1199px) {
            .monitor-metrics-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 576px) {
            .monitor-metrics-grid { grid-template-columns: 1fr; }
        }

        .metric-card-live {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 14px;
            padding: 20px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.25);
            transition: transform 0.2s, border-color 0.2s;
        }
        .metric-card-live:hover {
            transform: translateY(-2px);
            border-color: #6366f1;
        }
        .metric-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }
        .metric-card-title {
            font-size: 13px;
            font-weight: 600;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .metric-card-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
        }
        .metric-card-value {
            font-size: 32px;
            font-weight: 800;
            color: #f8fafc;
            line-height: 1.1;
            margin-bottom: 6px;
        }
        .metric-card-subtext {
            font-size: 12px;
            color: #64748b;
        }
        .metric-progress-bar-bg {
            height: 6px;
            background: #334155;
            border-radius: 999px;
            margin-top: 12px;
            overflow: hidden;
        }
        .metric-progress-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, #10b981, #6366f1);
            border-radius: 999px;
            width: 0%;
            transition: width 0.4s ease;
        }

        .monitor-table-card {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 14px;
            padding: 24px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.25);
        }

        .live-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 8px;
        }
        .live-table th {
            color: #64748b;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 8px 16px;
            border: none;
        }
        .live-table td {
            background: #0f172a;
            padding: 14px 16px;
            color: #e2e8f0;
            font-size: 14px;
            border: none;
            border-top: 1px solid #1e293b;
            border-bottom: 1px solid #1e293b;
        }
        .live-table tr td:first-child {
            border-left: 1px solid #1e293b;
            border-top-left-radius: 10px;
            border-bottom-left-radius: 10px;
        }
        .live-table tr td:last-child {
            border-right: 1px solid #1e293b;
            border-top-right-radius: 10px;
            border-bottom-right-radius: 10px;
        }

        .status-badge-live {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 700;
        }
        .status-badge-ringing {
            background: rgba(245, 158, 11, 0.15);
            color: #fbbf24;
            border: 1px solid rgba(245, 158, 11, 0.3);
        }
        .status-badge-incall {
            background: rgba(16, 185, 129, 0.15);
            color: #34d399;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .empty-monitor-state {
            text-align: center;
            padding: 60px 20px;
            color: #64748b;
        }
        .empty-monitor-icon {
            font-size: 48px;
            color: #334155;
            margin-bottom: 16px;
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
                <span class="current">Monitor em Tempo Real</span>
            </div>

            <!-- Top Header -->
            <div class="ddm-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 24px;">
                <div class="ddm-header-left" style="display: flex; align-items: center; gap: 16px;">
                    <div class="ddm-agent-avatar" style="background: linear-gradient(135deg, #6366f1, #a855f7); color: #fff; width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 22px;">
                        <i class="fa fa-line-chart"></i>
                    </div>
                    <div>
                        <div style="font-size: 22px; font-weight: 800; color: #f8fafc; letter-spacing: -0.4px;">
                            Monitor em Tempo Real
                        </div>
                        <div style="font-size: 13px; color: #94a3b8;">
                            Capacidade Total: <strong>500 Canais</strong> &bull; <strong>100 CPS</strong> (Oktor SIP Trunking)
                        </div>
                    </div>
                </div>
                <div>
                    <span class="live-badge-beacon">
                        <span class="live-pulse-dot"></span>
                        <span id="live_indicator_text">Ao Vivo &bull; 1.0s</span>
                    </span>
                </div>
            </div>

            <!-- Metric Cards -->
            <div class="monitor-metrics-grid">
                <!-- Card 1: Canais Ocupados -->
                <div class="metric-card-live">
                    <div class="metric-card-header">
                        <span class="metric-card-title">Canais Simultâneos</span>
                        <div class="metric-card-icon" style="background: rgba(99, 102, 241, 0.15); color: #818cf8;">
                            <i class="fa fa-phone"></i>
                        </div>
                    </div>
                    <div class="metric-card-value" id="val_active_channels">0 <span style="font-size: 16px; font-weight: 500; color: #64748b;">/ 500</span></div>
                    <div class="metric-card-subtext" id="val_channel_pct">0.0% de utilização</div>
                    <div class="metric-progress-bar-bg">
                        <div class="metric-progress-bar-fill" id="bar_channel_fill"></div>
                    </div>
                </div>

                <!-- Card 2: Velocidade de Disparo CPS -->
                <div class="metric-card-live">
                    <div class="metric-card-header">
                        <span class="metric-card-title">Velocidade de Disparo</span>
                        <div class="metric-card-icon" style="background: rgba(236, 72, 153, 0.15); color: #f472b6;">
                            <i class="fa fa-bolt"></i>
                        </div>
                    </div>
                    <div class="metric-card-value" id="val_current_cps">0 <span style="font-size: 16px; font-weight: 500; color: #64748b;">CPS</span></div>
                    <div class="metric-card-subtext" id="val_cps_subtext">Máx suportado: 100 CPS (6.000/min)</div>
                    <div class="metric-progress-bar-bg">
                        <div class="metric-progress-bar-fill" id="bar_cps_fill" style="background: linear-gradient(90deg, #ec4899, #f59e0b);"></div>
                    </div>
                </div>

                <!-- Card 3: Tocando no Aparelho -->
                <div class="metric-card-live">
                    <div class="metric-card-header">
                        <span class="metric-card-title">Tocando (Ringing)</span>
                        <div class="metric-card-icon" style="background: rgba(245, 158, 11, 0.15); color: #fbbf24;">
                            <i class="fa fa-bell-o"></i>
                        </div>
                    </div>
                    <div class="metric-card-value" id="val_ringing_count">0</div>
                    <div class="metric-card-subtext">Aguardando atendimento do cliente</div>
                    <div class="metric-progress-bar-bg">
                        <div class="metric-progress-bar-fill" id="bar_ringing_fill" style="background: #fbbf24;"></div>
                    </div>
                </div>

                <!-- Card 4: Falando com a IA -->
                <div class="metric-card-live">
                    <div class="metric-card-header">
                        <span class="metric-card-title">Em Conversa com a IA</span>
                        <div class="metric-card-icon" style="background: rgba(16, 185, 129, 0.15); color: #34d399;">
                            <i class="fa fa-comments-o"></i>
                        </div>
                    </div>
                    <div class="metric-card-value" id="val_in_call_count">0</div>
                    <div class="metric-card-subtext">Diálogos ativos em tempo real</div>
                    <div class="metric-progress-bar-bg">
                        <div class="metric-progress-bar-fill" id="bar_incall_fill" style="background: #34d399;"></div>
                    </div>
                </div>
            </div>

            <!-- Tabela de Chamadas Ativas -->
            <div class="monitor-table-card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <div>
                        <h3 style="margin: 0; font-size: 18px; font-weight: 700; color: #f8fafc;">
                            <i class="fa fa-microphone text-purple"></i> Chamadas em Andamento
                        </h3>
                        <p style="margin: 4px 0 0 0; color: #64748b; font-size: 13px;">Sessões de voz ativas no motor SIP</p>
                    </div>
                    <div>
                        <button class="btn btn-sm btn-default" onclick="fetchLiveTelemetry()" style="background: #0f172a; border-color: #334155; color: #94a3b8;">
                            <i class="fa fa-refresh"></i> Atualizar
                        </button>
                    </div>
                </div>

                <div id="live_calls_container">
                    <div class="empty-monitor-state" id="empty_state_view">
                        <div class="empty-monitor-icon"><i class="fa fa-headphones"></i></div>
                        <h4 style="color: #94a3b8; font-weight: 700; margin-bottom: 6px;">Nenhuma chamada ativa no momento</h4>
                        <p style="color: #64748b; font-size: 14px; max-width: 450px; margin: 0 auto;">
                            Todos os 500 canais SIP da Oktor Telecom estão livres e prontos para receber disparos individuais, via API ou em lote.
                        </p>
                    </div>

                    <div class="table-responsive" id="table_state_view" style="display: none;">
                        <table class="live-table">
                            <thead>
                                <tr>
                                    <th>Telefone Destino</th>
                                    <th>Agente IA</th>
                                    <th>Status ao Vivo</th>
                                    <th>Duração</th>
                                    <th>Última Fala</th>
                                    <th style="text-align: right;">Ação</th>
                                </tr>
                            </thead>
                            <tbody id="live_calls_tbody">
                                <!-- Preenchido dinamicamente via JS -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </aside>
</div>

<script src="js/jquery-2.1.1.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/app.min.js"></script>
<script>
var pollInterval = null;

function formatPhone(phone) {
    var clean = String(phone).replace(/\D/g, '');
    if (clean.length === 11) {
        return '(' + clean.substring(0, 2) + ') ' + clean.substring(2, 7) + '-' + clean.substring(7);
    } else if (clean.length === 10) {
        return '(' + clean.substring(0, 2) + ') ' + clean.substring(2, 6) + '-' + clean.substring(6);
    }
    return phone;
}

function fetchLiveTelemetry() {
    $.getJSON('php/LiveMonitorProxy.php', function(data) {
        if (!data) return;

        var activeChannels = data.active_channels || 0;
        var maxChannels = data.max_channels || 500;
        var pct = data.channel_utilization_pct || 0;
        var cps = data.current_cps || 0;
        var ringing = data.ringing_count || 0;
        var inCall = data.in_call_count || 0;

        // Atualiza Cards
        $('#val_active_channels').html(activeChannels + ' <span style="font-size: 16px; font-weight: 500; color: #64748b;">/ ' + maxChannels + '</span>');
        $('#val_channel_pct').text(pct + '% de utilização');
        $('#bar_channel_fill').css('width', Math.min(100, Math.max(2, pct)) + '%');

        $('#val_current_cps').html(cps + ' <span style="font-size: 16px; font-weight: 500; color: #64748b;">CPS</span>');
        $('#bar_cps_fill').css('width', Math.min(100, (cps / 100) * 100) + '%');

        $('#val_ringing_count').text(ringing);
        $('#bar_ringing_fill').css('width', Math.min(100, (ringing / 100) * 100) + '%');

        $('#val_in_call_count').text(inCall);
        $('#bar_incall_fill').css('width', Math.min(100, (inCall / 500) * 100) + '%');

        // Atualiza Tabela
        var calls = data.active_calls || [];
        if (calls.length === 0) {
            $('#empty_state_view').show();
            $('#table_state_view').hide();
        } else {
            $('#empty_state_view').hide();
            $('#table_state_view').show();

            var html = '';
            for (var i = 0; i < calls.length; i++) {
                var c = calls[i];
                var statusBadge = '';
                if (c.status === 'in_call' || c.answered) {
                    statusBadge = '<span class="status-badge-live status-badge-incall"><i class="fa fa-microphone"></i> Em Conversa</span>';
                } else if (c.status === 'ringing') {
                    statusBadge = '<span class="status-badge-live status-badge-ringing"><i class="fa fa-bell-o"></i> Tocando...</span>';
                } else {
                    statusBadge = '<span class="status-badge-live" style="background: rgba(99,102,241,0.15); color: #818cf8;"><i class="fa fa-spinner fa-spin"></i> Conectando</span>';
                }

                html += '<tr>' +
                    '<td style="font-weight: 700;"><i class="fa fa-mobile text-muted" style="margin-right: 6px; font-size: 16px;"></i> ' + formatPhone(c.phone_number) + '</td>' +
                    '<td style="color: #cbd5e1;"><span class="badge" style="background: #334155; font-weight: 600;">' + (c.agent_name || 'Agente IA') + '</span></td>' +
                    '<td>' + statusBadge + '</td>' +
                    '<td style="font-family: monospace; font-size: 15px; font-weight: 700; color: #38bdf8;">' + c.duration_formatted + '</td>' +
                    '<td style="color: #94a3b8; font-size: 13px; max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><em>"' + (c.last_dialogue || '') + '"</em></td>' +
                    '<td style="text-align: right;">' +
                        '<button class="btn btn-xs btn-danger" onclick="hangupCall(\'' + c.call_id + '\')" title="Desconectar Chamada" style="border-radius: 6px; padding: 4px 10px;">' +
                            '<i class="fa fa-phone"></i> Encerrar' +
                        '</button>' +
                    '</td>' +
                '</tr>';
            }
            $('#live_calls_tbody').html(html);
        }
    }).fail(function() {
        $('#live_indicator_text').html('<span style="color: #ef4444;">Motor Offline</span>');
    });
}

function hangupCall(callId) {
    if (!confirm('Deseja realmente derrubar esta chamada ativa agora?')) return;
    $.post('php/LiveMonitorProxy.php?action=hangup', { call_id: callId }, function(res) {
        fetchLiveTelemetry();
    });
}

$(document).ready(function() {
    fetchLiveTelemetry();
    pollInterval = setInterval(fetchLiveTelemetry, 1000);
});

$(window).on('beforeunload', function() {
    if (pollInterval) clearInterval(pollInterval);
});
</script>
</body>
</html>
