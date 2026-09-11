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
    <title>Dialog DDM - Monitor em Tempo Real</title>
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
            transition: all 0.2s;
        }
        .ddm-stat-card:hover {
            box-shadow: var(--ddm-shadow-sm);
            border-color: var(--ddm-border-strong);
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
            font-size: 30px;
            font-weight: 800;
            margin: 0 0 4px 0;
            color: var(--ddm-text-primary);
            letter-spacing: -0.5px;
            line-height: 1.1;
        }
        .ddm-stat-card p {
            font-size: 11.5px;
            margin: 0 0 10px 0;
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
            width: 0%;
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

        .ddm-agent-table-card {
            background: var(--ddm-surface);
            border: 1px solid var(--ddm-border);
            border-radius: var(--ddm-radius-lg);
            padding: 24px;
            box-shadow: var(--ddm-shadow-xs);
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
            padding: 14px !important;
            vertical-align: middle !important;
            border-top: 1px solid var(--ddm-border) !important;
            color: var(--ddm-text-primary);
            font-size: 13.5px;
        }
        .ddm-table tbody tr:hover {
            background-color: var(--ddm-surface-subtle) !important;
        }

        .empty-state-box {
            text-align: center;
            padding: 50px 20px;
        }
        .empty-state-icon {
            font-size: 44px;
            color: var(--ddm-border-strong);
            margin-bottom: 14px;
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
            <div class="ddm-header">
                <div class="ddm-header-left">
                    <div class="ddm-agent-avatar">
                        <i class="fa fa-line-chart"></i>
                    </div>
                    <div>
                        <div style="font-size: 22px; font-weight: 800; color: var(--ddm-text-primary); letter-spacing: -0.4px;">
                            Monitor em Tempo Real
                        </div>
                        <div style="font-size: 13px; color: var(--ddm-text-secondary); margin-top: 2px;">
                            Capacidade: <strong>500 Canais Simultâneos</strong> &bull; <strong>100 CPS</strong> (Tronco SIP Oktor Telecom)
                        </div>
                    </div>
                </div>
                <div class="ddm-header-actions">
                    <span class="ddm-live-beacon">
                        <span class="ddm-live-dot"></span>
                        <span id="live_indicator_text">Ao Vivo &bull; 1.0s</span>
                    </span>
                    <button type="button" class="ddm-btn ddm-btn-secondary" onclick="fetchLiveTelemetry();">
                        <i class="fa fa-refresh"></i> Atualizar
                    </button>
                </div>
            </div>

            <!-- 4 Hero Metric Cards -->
            <div class="row">
                <!-- Card 1: Canais Simultâneos -->
                <div class="col-md-3 col-sm-6 col-xs-12">
                    <div class="ddm-stat-card" style="border-left: 3px solid #6366F1;">
                        <i class="fa fa-phone stat-icon-bg" style="color: rgba(99, 102, 241, 0.08);"></i>
                        <p>Canais Simultâneos</p>
                        <h3 id="val_active_channels">0 <small style="font-size: 16px; font-weight: 600; color: var(--ddm-text-muted);">/ 500</small></h3>
                        <div class="stat-progress-bg">
                            <div class="stat-progress-fill" id="bar_channel_fill" style="background: linear-gradient(90deg, #6366F1, #8B5CF6);"></div>
                        </div>
                        <div class="stat-subtext" id="val_channel_pct">0.0% de utilização</div>
                    </div>
                </div>

                <!-- Card 2: CPS -->
                <div class="col-md-3 col-sm-6 col-xs-12">
                    <div class="ddm-stat-card" style="border-left: 3px solid var(--ddm-primary);">
                        <i class="fa fa-bolt stat-icon-bg" style="color: rgba(249, 115, 22, 0.08);"></i>
                        <p>Velocidade de Disparo</p>
                        <h3 id="val_current_cps">0 <small style="font-size: 16px; font-weight: 600; color: var(--ddm-text-muted);">CPS</small></h3>
                        <div class="stat-progress-bg">
                            <div class="stat-progress-fill" id="bar_cps_fill" style="background: linear-gradient(90deg, var(--ddm-primary), #F59E0B);"></div>
                        </div>
                        <div class="stat-subtext" id="val_cps_subtext">Limite: 100 CPS (6.000/min)</div>
                    </div>
                </div>

                <!-- Card 3: Tocando (Ringing) -->
                <div class="col-md-3 col-sm-6 col-xs-12">
                    <div class="ddm-stat-card" style="border-left: 3px solid var(--ddm-warning);">
                        <i class="fa fa-bell-o stat-icon-bg" style="color: rgba(245, 158, 11, 0.08);"></i>
                        <p>Tocando (Ringing)</p>
                        <h3 id="val_ringing_count" style="color: var(--ddm-warning);">0</h3>
                        <div class="stat-progress-bg">
                            <div class="stat-progress-fill" id="bar_ringing_fill" style="background: var(--ddm-warning);"></div>
                        </div>
                        <div class="stat-subtext">Aguardando atendimento</div>
                    </div>
                </div>

                <!-- Card 4: Falando com IA -->
                <div class="col-md-3 col-sm-6 col-xs-12">
                    <div class="ddm-stat-card" style="border-left: 3px solid var(--ddm-success);">
                        <i class="fa fa-microphone stat-icon-bg" style="color: rgba(16, 185, 129, 0.08);"></i>
                        <p>Em Conversa com a IA</p>
                        <h3 id="val_in_call_count" style="color: var(--ddm-success);">0</h3>
                        <div class="stat-progress-bg">
                            <div class="stat-progress-fill" id="bar_incall_fill" style="background: var(--ddm-success);"></div>
                        </div>
                        <div class="stat-subtext">Diálogos ativos em tempo real</div>
                    </div>
                </div>
            </div>

            <!-- Tabela de Chamadas Ativas -->
            <div class="ddm-agent-table-card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px;">
                    <div>
                        <h3 style="margin: 0; font-size: 17px; font-weight: 800; color: var(--ddm-text-primary);">
                            <i class="fa fa-headphones text-orange"></i> Chamadas em Andamento
                        </h3>
                        <p style="margin: 3px 0 0 0; color: var(--ddm-text-secondary); font-size: 13px;">Sessões de voz ativas no motor SIP da infraestrutura</p>
                    </div>
                </div>

                <div id="live_calls_container">
                    <div class="empty-state-box" id="empty_state_view">
                        <div class="empty-state-icon"><i class="fa fa-phone-square"></i></div>
                        <h4 style="color: var(--ddm-text-primary); font-weight: 700; margin-bottom: 6px; font-size: 16px;">Nenhuma chamada ativa no momento</h4>
                        <p style="color: var(--ddm-text-muted); font-size: 13.5px; max-width: 480px; margin: 0 auto; line-height: 1.5;">
                            Todos os 500 canais SIP da Oktor Telecom estão livres e prontos para receber disparos individuais, via API ou em lote.
                        </p>
                    </div>

                    <div class="table-responsive" id="table_state_view" style="display: none;">
                        <table class="table ddm-table">
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
        $('#val_active_channels').html(activeChannels + ' <small style="font-size: 16px; font-weight: 600; color: var(--ddm-text-muted);">/ ' + maxChannels + '</small>');
        $('#val_channel_pct').text(pct + '% de utilização');
        $('#bar_channel_fill').css('width', Math.min(100, Math.max(0, pct)) + '%');

        $('#val_current_cps').html(cps + ' <small style="font-size: 16px; font-weight: 600; color: var(--ddm-text-muted);">CPS</small>');
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
                    statusBadge = '<span class="ddm-badge ddm-badge-active"><span class="ddm-dot-indicator ddm-dot-active"></span> Em Conversa</span>';
                } else if (c.status === 'ringing') {
                    statusBadge = '<span class="ddm-badge" style="background: var(--ddm-warning-light); color: #B45309; border-color: var(--ddm-warning-border);"><i class="fa fa-bell-o"></i> Tocando...</span>';
                } else {
                    statusBadge = '<span class="ddm-badge ddm-badge-orange"><i class="fa fa-spinner fa-spin"></i> Conectando</span>';
                }

                html += '<tr>' +
                    '<td style="font-weight: 700;"><i class="fa fa-phone text-muted" style="margin-right: 6px;"></i> ' + formatPhone(c.phone_number) + '</td>' +
                    '<td><span class="ddm-badge ddm-badge-id">' + (c.agent_name || 'Agente IA') + '</span></td>' +
                    '<td>' + statusBadge + '</td>' +
                    '<td style="font-family: \'JetBrains Mono\', monospace; font-size: 13.5px; font-weight: 700; color: var(--ddm-primary);">' + c.duration_formatted + '</td>' +
                    '<td style="color: var(--ddm-text-secondary); font-size: 13px; max-width: 280px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><em>"' + (c.last_dialogue || 'Iniciando áudio...') + '"</em></td>' +
                    '<td style="text-align: right;">' +
                        '<button class="ddm-btn ddm-btn-secondary ddm-btn-xs" onclick="hangupCall(\'' + c.call_id + '\')" title="Desconectar Chamada" style="color: var(--ddm-danger) !important; border-color: var(--ddm-danger-border);">' +
                            '<i class="fa fa-phone"></i> Encerrar' +
                        '</button>' +
                    '</td>' +
                '</tr>';
            }
            $('#live_calls_tbody').html(html);
        }
    }).fail(function() {
        $('#live_indicator_text').html('<span style="color: var(--ddm-danger);">Motor Offline</span>');
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
