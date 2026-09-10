<?php
/**
 * @file        batch_dialer.php
 * @brief       DIAL GO Voice AI - Disparador em Lote / Batch Calling
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

$agents = $aiHandler->getAllAgents();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>DIAL GO - Disparo em Lote (Batch Calling)</title>
    <meta content='width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no' name='viewport'>

    <?php 
        print $ui->standardizedThemeCSS(); 
        print $ui->creamyThemeCSS();
        print $ui->dataTablesTheme();
    ?>
    <link href="css/style.css" rel="stylesheet" type="text/css" />
    <link href="css/dialog_ddm.css" rel="stylesheet" type="text/css" />
    <style>
        .batch-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-bottom: 24px;
        }
        @media (max-width: 991px) {
            .batch-grid {
                grid-template-columns: 1fr;
            }
        }
        .batch-card {
            background: var(--ddm-surface);
            border: 1px solid var(--ddm-border);
            border-radius: var(--ddm-radius-lg);
            padding: 24px;
            box-shadow: var(--ddm-shadow-xs);
        }
        .batch-metric-box {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            margin-bottom: 20px;
        }
        .batch-stat {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--ddm-border);
            border-radius: 8px;
            padding: 14px;
            text-align: center;
        }
        .batch-stat-val {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 4px;
        }
        .batch-stat-lbl {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--ddm-text-muted);
        }
        .progress-bar-container {
            background: rgba(255, 255, 255, 0.08);
            border-radius: 8px;
            height: 12px;
            overflow: hidden;
            margin: 16px 0;
            position: relative;
        }
        .progress-bar-fill {
            background: linear-gradient(90deg, #6366f1, #a855f7);
            height: 100%;
            width: 0%;
            transition: width 0.3s ease;
        }
        .batch-log-table {
            max-height: 400px;
            overflow-y: auto;
            border: 1px solid var(--ddm-border);
            border-radius: 8px;
        }
        .pulse-active {
            animation: pulse-ring 1.5s infinite;
        }
        @keyframes pulse-ring {
            0% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.6; transform: scale(1.05); }
            100% { opacity: 1; transform: scale(1); }
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
                <a href="index.php"><i class="fa fa-home"></i> DIAL GO</a>
                <span class="separator">/</span>
                <a href="ai_agents.php">Agentes de Voz IA</a>
                <span class="separator">/</span>
                <span class="current">Disparo em Lote (Batch)</span>
            </div>

            <!-- Header -->
            <div class="ddm-header">
                <div class="ddm-header-left">
                    <div class="ddm-agent-avatar" style="background: linear-gradient(135deg, #6366f1, #a855f7);">
                        <i class="fa fa-rocket"></i>
                    </div>
                    <div>
                        <h1 class="ddm-header-title">Disparador em Lote (Batch Calling)</h1>
                        <p class="ddm-header-subtitle">Dispare centenas de chamadas simultâneas via IA com controle de concorrência e monitoramento em tempo real.</p>
                    </div>
                </div>
                <div class="ddm-header-actions">
                    <a href="ai_call_logs.php" class="ddm-btn ddm-btn-secondary">
                        <i class="fa fa-phone"></i> Ver Logs & Gravações
                    </a>
                </div>
            </div>

            <div class="batch-grid">
                
                <!-- Card 1: Formulário de Configuração do Lote -->
                <div class="batch-card">
                    <h3 style="margin-top: 0; font-size: 18px; font-weight: 600; margin-bottom: 20px;">
                        <i class="fa fa-sliders text-purple"></i> Configuração da Campanha
                    </h3>

                    <form id="batchDialForm">
                        <div class="form-group" style="margin-bottom: 18px;">
                            <label class="ddm-form-label" for="batch_agent_id">Selecione o Agente de IA</label>
                            <select id="batch_agent_id" name="agent_id" class="form-control" style="background: rgba(0,0,0,0.2); border: 1px solid var(--ddm-border); color: #fff; height: 42px; border-radius: 8px;" required>
                                <?php if (!empty($agents)): ?>
                                    <?php foreach ($agents as $ag): ?>
                                        <option value="<?=$ag['id']?>">
                                            Agente #<?=$ag['id']?> - <?=htmlspecialchars($ag['agent_name'])?> (<?=htmlspecialchars($ag['voice_provider'])?> / <?=htmlspecialchars($ag['voice_id'])?>)
                                        </option>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <option value="">Nenhum agente cadastrado</option>
                                <?php endif; ?>
                            </select>
                        </div>

                        <div class="row" style="margin-bottom: 18px;">
                            <div class="col-xs-6">
                                <label class="ddm-form-label" for="batch_concurrency">
                                    Canais Simultâneos: <span id="concurrency_val" style="color: #a855f7; font-weight: bold;">5</span>
                                </label>
                                <input type="range" id="batch_concurrency" name="concurrency" min="1" max="30" value="5" class="form-control" style="padding: 0; background: transparent;" oninput="$('#concurrency_val').text(this.value);" />
                                <small style="color: var(--ddm-text-muted); font-size: 11px;">Máx. de ligações ao mesmo tempo</small>
                            </div>
                            <div class="col-xs-6">
                                <label class="ddm-form-label" for="batch_delay">Espaçamento entre Ligações</label>
                                <select id="batch_delay" name="delay_ms" class="form-control" style="background: rgba(0,0,0,0.2); border: 1px solid var(--ddm-border); color: #fff; height: 36px; border-radius: 6px;">
                                    <option value="150">150ms (Ultrarrápido)</option>
                                    <option value="300" selected>300ms (Recomendado)</option>
                                    <option value="600">600ms (Suave)</option>
                                    <option value="1000">1.0s (Pausado)</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group" style="margin-bottom: 18px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                <label class="ddm-form-label" style="margin-bottom: 0;">Lista de Telefones (ou CSV)</label>
                                <span id="contactCountBadge" class="label label-info" style="font-size: 11px;">0 contatos detectados</span>
                            </div>
                            
                            <textarea id="batch_contacts_text" name="contacts" rows="7" class="form-control" placeholder="Cole aqui os telefones (1 por linha) ou com nome separado por vírgula:&#10;21984354821, Caio Vicente&#10;11987654321, Maria Silva&#10;31999998888, João Carlos" style="background: rgba(0,0,0,0.3); border: 1px solid var(--ddm-border); color: #fff; font-family: monospace; border-radius: 8px;" required></textarea>
                        </div>

                        <div class="form-group" style="margin-bottom: 24px;">
                            <label class="ddm-form-label" style="font-size: 12px; color: var(--ddm-text-muted);">Ou importe um arquivo .CSV:</label>
                            <input type="file" id="csv_file_input" accept=".csv, .txt" class="form-control" style="background: transparent; border: 1px dashed var(--ddm-border); color: #fff; border-radius: 6px; padding: 6px;" />
                        </div>

                        <button type="submit" id="btnStartBatch" class="ddm-btn ddm-btn-primary" style="width: 100%; justify-content: center; padding: 12px; font-size: 15px; font-weight: 600;">
                            <i class="fa fa-play"></i> Iniciar Disparo em Lote
                        </button>
                    </form>
                </div>

                <!-- Card 2: Monitor de Progresso em Tempo Real -->
                <div class="batch-card" id="batchProgressCard">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                        <h3 style="margin: 0; font-size: 18px; font-weight: 600;">
                            <i class="fa fa-dashboard text-aqua"></i> Painel em Tempo Real
                        </h3>
                        <span id="batchStatusBadge" class="label label-default" style="font-size: 12px; padding: 4px 10px;">Aguardando Início</span>
                    </div>

                    <!-- Métricas Rápidas -->
                    <div class="batch-metric-box">
                        <div class="batch-stat">
                            <div class="batch-stat-val text-aqua" id="stat_total">0</div>
                            <div class="batch-stat-lbl">Total</div>
                        </div>
                        <div class="batch-stat">
                            <div class="batch-stat-val text-yellow" id="stat_in_progress">0</div>
                            <div class="batch-stat-lbl">Em Curso</div>
                        </div>
                        <div class="batch-stat">
                            <div class="batch-stat-val text-green" id="stat_completed">0</div>
                            <div class="batch-stat-lbl">Concluídas</div>
                        </div>
                        <div class="batch-stat">
                            <div class="batch-stat-val text-red" id="stat_failed">0</div>
                            <div class="batch-stat-lbl">Falhas</div>
                        </div>
                    </div>

                    <!-- Barra de Progresso -->
                    <div>
                        <div style="display: flex; justify-content: space-between; font-size: 12px; color: var(--ddm-text-muted);">
                            <span>Progresso do Lote</span>
                            <span id="progress_percent_text">0%</span>
                        </div>
                        <div class="progress-bar-container">
                            <div class="progress-bar-fill" id="progressBarFill"></div>
                        </div>
                    </div>

                    <!-- Controles do Lote -->
                    <div style="display: flex; gap: 8px; margin-bottom: 20px;" id="batchControlButtons">
                        <button type="button" class="btn btn-warning btn-sm" id="btnPauseBatch" style="display:none;" onclick="controlBatch('pause')">
                            <i class="fa fa-pause"></i> Pausar
                        </button>
                        <button type="button" class="btn btn-success btn-sm" id="btnResumeBatch" style="display:none;" onclick="controlBatch('resume')">
                            <i class="fa fa-play"></i> Retomar
                        </button>
                        <button type="button" class="btn btn-danger btn-sm" id="btnCancelBatch" style="display:none;" onclick="controlBatch('cancel')">
                            <i class="fa fa-stop"></i> Cancelar Disparo
                        </button>
                    </div>

                    <!-- Tabela de Chamadas do Lote -->
                    <h4 style="font-size: 14px; font-weight: 600; margin-bottom: 10px; color: var(--ddm-text-muted);">
                        Lista de Chamadas do Lote
                    </h4>
                    <div class="batch-log-table">
                        <table class="table table-hover" style="margin-bottom: 0; font-size: 13px;">
                            <thead>
                                <tr style="background: rgba(255,255,255,0.02); color: var(--ddm-text-muted);">
                                    <th>#</th>
                                    <th>Telefone</th>
                                    <th>Nome / Dado</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody id="batchContactsTbody">
                                <tr>
                                    <td colspan="4" class="text-center" style="padding: 24px; color: var(--ddm-text-muted);">
                                        Nenhum lote em execução. Configure os números e clique em "Iniciar Disparo".
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>

        </div>
    </aside>
</div>

<?php 
    print $ui->standardizedThemeJS(); 
    print $ui->creamyThemeJS();
?>

<script>
let currentBatchId = null;
let pollTimer = null;

// Contador automático de linhas do Textarea
function updateContactCounter() {
    let raw = $('#batch_contacts_text').val() || '';
    let lines = raw.split('\n').filter(l => l.trim().length > 0);
    $('#contactCountBadge').text(lines.length + (lines.length === 1 ? ' contato detectado' : ' contatos detectados'));
}
$('#batch_contacts_text').on('input change keyup paste', function() {
    setTimeout(updateContactCounter, 50);
});

// Leitor de arquivo CSV
$('#csv_file_input').on('change', function(e) {
    let file = e.target.files[0];
    if (!file) return;

    let reader = new FileReader();
    reader.onload = function(e) {
        let content = e.target.result;
        $('#batch_contacts_text').val(content);
        let lines = content.split('\n').filter(l => l.trim().length > 0);
        $('#contactCountBadge').text(lines.length + ' contatos importados do CSV');
    };
    reader.readAsText(file);
});

// Iniciar Disparo em Lote
$('#batchDialForm').on('submit', function(e) {
    e.preventDefault();

    let agentId = $('#batch_agent_id').val();
    let contacts = $('#batch_contacts_text').val().trim();
    let concurrency = $('#batch_concurrency').val();
    let delayMs = $('#batch_delay').val();

    if (!contacts) {
        alert('Por favor, informe ao menos um número de telefone para disparo.');
        return;
    }

    $('#btnStartBatch').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Iniciando Disparo...');

    $.ajax({
        url: 'php/BatchDialProxy.php',
        type: 'POST',
        data: {
            action: 'start',
            agent_id: agentId,
            contacts: contacts,
            concurrency: concurrency,
            delay_ms: delayMs
        },
        dataType: 'json',
        success: function(resp) {
            $('#btnStartBatch').prop('disabled', false).html('<i class="fa fa-play"></i> Iniciar Disparo em Lote');

            if (resp.status === 'success' && resp.batch_id) {
                currentBatchId = resp.batch_id;
                $('#batchStatusBadge').removeClass().addClass('label label-primary pulse-active').text('Disparando Chamadas...');
                $('#btnPauseBatch, #btnCancelBatch').show();
                $('#btnResumeBatch').hide();

                // Iniciar Polling de Atualização em Tempo Real (a cada 800ms)
                if (pollTimer) clearInterval(pollTimer);
                pollTimer = setInterval(pollBatchStatus, 800);
            } else {
                alert('Erro ao iniciar lote: ' + (resp.message || 'Falha na resposta do servidor'));
            }
        },
        error: function(xhr) {
            $('#btnStartBatch').prop('disabled', false).html('<i class="fa fa-play"></i> Iniciar Disparo em Lote');
            alert('Erro de comunicação com o servidor de voz na porta 8765.');
        }
    });
});

// Polling de Status do Lote
function pollBatchStatus() {
    if (!currentBatchId) return;

    $.ajax({
        url: 'php/BatchDialProxy.php',
        type: 'GET',
        data: { action: 'status', batch_id: currentBatchId },
        dataType: 'json',
        success: function(resp) {
            if (resp.status === 'success' && resp.batch) {
                let b = resp.batch;
                $('#stat_total').text(b.total);
                $('#stat_in_progress').text(b.in_progress);
                $('#stat_completed').text(b.completed);
                $('#stat_failed').text(b.failed);

                let percent = b.total > 0 ? Math.round(((b.completed + b.failed) / b.total) * 100) : 0;
                $('#progressBarFill').css('width', percent + '%');
                $('#progress_percent_text').text(percent + '% (' + (b.completed + b.failed) + '/' + b.total + ')');

                if (b.status === 'running') {
                    $('#batchStatusBadge').removeClass().addClass('label label-primary pulse-active').text('Em Execução (' + b.in_progress + ' simultâneas)');
                } else if (b.status === 'paused') {
                    $('#batchStatusBadge').removeClass().addClass('label label-warning').text('Pausado');
                    $('#btnPauseBatch').hide();
                    $('#btnResumeBatch').show();
                } else if (b.status === 'completed') {
                    $('#batchStatusBadge').removeClass().addClass('label label-success').text('Concluído 100%');
                    $('#btnPauseBatch, #btnResumeBatch, #btnCancelBatch').hide();
                    clearInterval(pollTimer);
                } else if (b.status === 'cancelled') {
                    $('#batchStatusBadge').removeClass().addClass('label label-danger').text('Cancelado');
                    $('#btnPauseBatch, #btnResumeBatch, #btnCancelBatch').hide();
                    clearInterval(pollTimer);
                }

                // Renderizar tabela de contatos
                let html = '';
                b.contacts.forEach(function(c, i) {
                    let stBadge = '<span class="label label-default">Na Fila</span>';
                    if (c.status === 'dialing') {
                        stBadge = '<span class="label label-warning pulse-active"><i class="fa fa-phone"></i> Discando...</span>';
                    } else if (c.status === 'completed') {
                        stBadge = '<span class="label label-success"><i class="fa fa-check"></i> Concluída</span>';
                    } else if (c.status === 'failed') {
                        stBadge = '<span class="label label-danger"><i class="fa fa-times"></i> Falha</span>';
                    }

                    html += '<tr>' +
                        '<td>' + (i + 1) + '</td>' +
                        '<td style="font-weight:600;">' + c.phone_number + '</td>' +
                        '<td>' + (c.name || '-') + '</td>' +
                        '<td>' + stBadge + '</td>' +
                    '</tr>';
                });
                $('#batchContactsTbody').html(html);
            }
        }
    });
}

// Controle de Pausa/Retomada/Cancelamento
function controlBatch(action) {
    if (!currentBatchId) return;

    $.ajax({
        url: 'php/BatchDialProxy.php',
        type: 'POST',
        data: { action: action, batch_id: currentBatchId },
        dataType: 'json',
        success: function(resp) {
            if (action === 'pause') {
                $('#btnPauseBatch').hide();
                $('#btnResumeBatch').show();
            } else if (action === 'resume') {
                $('#btnResumeBatch').hide();
                $('#btnPauseBatch').show();
            } else if (action === 'cancel') {
                $('#btnPauseBatch, #btnResumeBatch, #btnCancelBatch').hide();
            }
            pollBatchStatus();
        }
    });
}
</script>
</body>
</html>
